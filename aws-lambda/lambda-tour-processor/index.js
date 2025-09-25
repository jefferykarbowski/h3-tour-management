/**
 * AWS Lambda Tour Extraction Function
 *
 * Serverless tour processing that eliminates WordPress download/processing limitations:
 * - Automatically triggered by S3 uploads to uploads/ prefix
 * - Handles nested ZIP extraction (TourName.zip → TourName/Web.zip → Web/ → tour files)
 * - Uploads extracted tour files to public tours/ directory
 * - Sends completion webhook to WordPress
 * - Comprehensive error handling and monitoring
 */

const AWS = require('aws-sdk');
const unzipper = require('unzipper');
const path = require('path');
const crypto = require('crypto');

// AWS SDK Configuration
const s3 = new AWS.S3();
const sns = new AWS.SNS();

// Configuration from environment variables
const CONFIG = {
    BUCKET_NAME: process.env.BUCKET_NAME,
    WEBHOOK_URL: process.env.WEBHOOK_URL,
    SNS_TOPIC_ARN: process.env.SNS_TOPIC_ARN,
    MAX_FILE_SIZE: parseInt(process.env.MAX_FILE_SIZE || '1073741824'), // 1GB
    MAX_PROCESSING_TIME: parseInt(process.env.MAX_PROCESSING_TIME || '840000'), // 14 minutes
    SUPPORTED_FORMATS: ['zip'],
    UPLOAD_PREFIX: 'uploads/',
    TOURS_PREFIX: 'tours/',
    TEMP_PREFIX: 'temp/',
    FAILED_PREFIX: 'failed/',
    PROCESSED_PREFIX: 'processed/'
};

/**
 * Main Lambda handler
 */
exports.handler = async (event) => {
    console.log('Lambda Tour Processor Started', { event: JSON.stringify(event, null, 2) });

    const startTime = Date.now();
    let processingResult = {
        success: false,
        message: '',
        processingTime: 0,
        filesExtracted: 0,
        totalSize: 0,
        tourName: '',
        s3Key: ''
    };

    try {
        // Validate and parse S3 event
        const s3Event = parseS3Event(event);
        if (!s3Event) {
            throw new Error('Invalid S3 event structure');
        }

        processingResult.s3Key = s3Event.key;
        processingResult.tourName = extractTourName(s3Event.key);

        console.log('Processing S3 object:', {
            bucket: s3Event.bucket,
            key: s3Event.key,
            tourName: processingResult.tourName
        });

        // Validate file before processing
        await validateS3File(s3Event);

        // Process the tour ZIP file
        const extractionResult = await processTourFile(s3Event);

        processingResult.success = true;
        processingResult.filesExtracted = extractionResult.filesExtracted;
        processingResult.totalSize = extractionResult.totalSize;
        processingResult.processingTime = Date.now() - startTime;
        processingResult.message = `Successfully processed tour: ${processingResult.tourName}`;

        // Move original file to processed directory
        await moveFileToProcessed(s3Event);

        // Send success webhook to WordPress
        await sendWebhook(processingResult, true);

        console.log('Tour processing completed successfully:', processingResult);

        return {
            statusCode: 200,
            body: JSON.stringify(processingResult)
        };

    } catch (error) {
        console.error('Tour processing failed:', error);

        processingResult.success = false;
        processingResult.message = error.message;
        processingResult.processingTime = Date.now() - startTime;

        // Move failed file to failed directory
        if (processingResult.s3Key) {
            try {
                await moveFileToFailed(processingResult.s3Key);
            } catch (moveError) {
                console.error('Failed to move failed file:', moveError);
            }
        }

        // Send failure notifications
        await sendWebhook(processingResult, false);
        await sendErrorAlert(error, processingResult);

        return {
            statusCode: 500,
            body: JSON.stringify(processingResult)
        };
    }
};

/**
 * Parse and validate S3 event
 */
function parseS3Event(event) {
    try {
        if (!event.Records || !Array.isArray(event.Records)) {
            return null;
        }

        const s3Record = event.Records.find(record =>
            record.eventSource === 'aws:s3' &&
            record.s3
        );

        if (!s3Record) {
            return null;
        }

        const bucket = s3Record.s3.bucket.name;
        const key = decodeURIComponent(s3Record.s3.object.key.replace(/\+/g, ' '));

        // Only process files in the uploads directory
        if (!key.startsWith(CONFIG.UPLOAD_PREFIX)) {
            console.log('Ignoring file outside uploads directory:', key);
            return null;
        }

        // Only process ZIP files
        if (!key.toLowerCase().endsWith('.zip')) {
            console.log('Ignoring non-ZIP file:', key);
            return null;
        }

        return { bucket, key };
    } catch (error) {
        console.error('Error parsing S3 event:', error);
        return null;
    }
}

/**
 * Extract tour name from S3 key
 */
function extractTourName(s3Key) {
    const filename = path.basename(s3Key, '.zip');
    return filename.replace(/[^a-zA-Z0-9-_]/g, '_');
}

/**
 * Validate S3 file before processing
 */
async function validateS3File(s3Event) {
    try {
        const headResult = await s3.headObject({
            Bucket: s3Event.bucket,
            Key: s3Event.key
        }).promise();

        // Check file size
        if (headResult.ContentLength > CONFIG.MAX_FILE_SIZE) {
            throw new Error(`File size ${headResult.ContentLength} exceeds maximum ${CONFIG.MAX_FILE_SIZE} bytes`);
        }

        // Check content type
        const contentType = headResult.ContentType || '';
        if (!contentType.includes('zip') && !contentType.includes('application/octet-stream')) {
            console.warn('Unexpected content type:', contentType);
        }

        console.log('File validation passed:', {
            size: headResult.ContentLength,
            contentType: headResult.ContentType,
            lastModified: headResult.LastModified
        });

    } catch (error) {
        if (error.code === 'NoSuchKey') {
            throw new Error(`File not found in S3: ${s3Event.key}`);
        }
        throw new Error(`File validation failed: ${error.message}`);
    }
}

/**
 * Main tour file processing function
 */
async function processTourFile(s3Event) {
    const tourName = extractTourName(s3Event.key);
    let filesExtracted = 0;
    let totalSize = 0;

    console.log('Starting tour extraction:', { tourName, s3Key: s3Event.key });

    // Download the main ZIP file from S3
    const zipBuffer = await downloadS3File(s3Event.bucket, s3Event.key);
    console.log('Downloaded main ZIP file, size:', zipBuffer.length);

    // Extract the main ZIP file
    const mainEntries = await extractZipBuffer(zipBuffer);
    console.log('Main ZIP contains', mainEntries.length, 'entries');

    // Look for nested Web.zip file
    const webZipEntry = findWebZipEntry(mainEntries);

    if (webZipEntry) {
        console.log('Found nested Web.zip, extracting...');

        // Extract the nested Web.zip
        const webZipBuffer = webZipEntry.buffer;
        const webEntries = await extractZipBuffer(webZipBuffer);

        console.log('Web.zip contains', webEntries.length, 'entries');

        // Upload all web entries to tours directory
        for (const entry of webEntries) {
            if (!entry.isDirectory && entry.buffer && entry.buffer.length > 0) {
                const s3Key = `${CONFIG.TOURS_PREFIX}${tourName}/${entry.path}`;

                await uploadFileToS3(s3Event.bucket, s3Key, entry.buffer, entry.contentType);

                filesExtracted++;
                totalSize += entry.buffer.length;

                if (filesExtracted % 10 === 0) {
                    console.log(`Extracted ${filesExtracted} files so far...`);
                }
            }
        }
    } else {
        console.log('No Web.zip found, extracting main ZIP directly...');

        // Extract main ZIP directly to tours directory
        for (const entry of mainEntries) {
            if (!entry.isDirectory && entry.buffer && entry.buffer.length > 0) {
                const s3Key = `${CONFIG.TOURS_PREFIX}${tourName}/${entry.path}`;

                await uploadFileToS3(s3Event.bucket, s3Key, entry.buffer, entry.contentType);

                filesExtracted++;
                totalSize += entry.buffer.length;

                if (filesExtracted % 10 === 0) {
                    console.log(`Extracted ${filesExtracted} files so far...`);
                }
            }
        }
    }

    // Create a metadata file for the tour
    const metadata = {
        tourName,
        originalFile: s3Event.key,
        extractedAt: new Date().toISOString(),
        filesCount: filesExtracted,
        totalSize,
        structure: webZipEntry ? 'nested' : 'flat'
    };

    await uploadFileToS3(
        s3Event.bucket,
        `${CONFIG.TOURS_PREFIX}${tourName}/tour-metadata.json`,
        Buffer.from(JSON.stringify(metadata, null, 2)),
        'application/json'
    );

    console.log('Tour extraction completed:', { filesExtracted, totalSize, tourName });

    return { filesExtracted, totalSize };
}

/**
 * Download file from S3 as buffer
 */
async function downloadS3File(bucket, key) {
    try {
        const params = { Bucket: bucket, Key: key };
        const data = await s3.getObject(params).promise();
        return data.Body;
    } catch (error) {
        throw new Error(`Failed to download S3 file ${key}: ${error.message}`);
    }
}

/**
 * Extract ZIP buffer and return entries
 */
async function extractZipBuffer(buffer) {
    return new Promise((resolve, reject) => {
        const entries = [];
        const stream = require('stream');

        const bufferStream = new stream.PassThrough();
        bufferStream.end(buffer);

        bufferStream
            .pipe(unzipper.Parse())
            .on('entry', (entry) => {
                const fileName = entry.path;
                const type = entry.type;

                if (type === 'File') {
                    const chunks = [];

                    entry.on('data', (chunk) => {
                        chunks.push(chunk);
                    });

                    entry.on('end', () => {
                        const buffer = Buffer.concat(chunks);
                        entries.push({
                            path: fileName,
                            buffer: buffer,
                            isDirectory: false,
                            contentType: getContentType(fileName)
                        });
                    });
                } else {
                    entries.push({
                        path: fileName,
                        buffer: null,
                        isDirectory: true
                    });
                    entry.autodrain();
                }
            })
            .on('close', () => {
                resolve(entries);
            })
            .on('error', (error) => {
                reject(new Error(`ZIP extraction failed: ${error.message}`));
            });
    });
}

/**
 * Find Web.zip entry in extracted files
 */
function findWebZipEntry(entries) {
    return entries.find(entry =>
        !entry.isDirectory &&
        entry.path.toLowerCase().includes('web.zip')
    );
}

/**
 * Upload file to S3
 */
async function uploadFileToS3(bucket, key, buffer, contentType) {
    try {
        const params = {
            Bucket: bucket,
            Key: key,
            Body: buffer,
            ContentType: contentType || 'application/octet-stream',
            CacheControl: 'max-age=31536000', // 1 year cache for tour files
        };

        await s3.upload(params).promise();
    } catch (error) {
        throw new Error(`Failed to upload ${key}: ${error.message}`);
    }
}

/**
 * Get content type from file extension
 */
function getContentType(filename) {
    const ext = path.extname(filename).toLowerCase();
    const contentTypes = {
        '.html': 'text/html',
        '.htm': 'text/html',
        '.js': 'application/javascript',
        '.css': 'text/css',
        '.json': 'application/json',
        '.xml': 'application/xml',
        '.jpg': 'image/jpeg',
        '.jpeg': 'image/jpeg',
        '.png': 'image/png',
        '.gif': 'image/gif',
        '.svg': 'image/svg+xml',
        '.ico': 'image/x-icon',
        '.pdf': 'application/pdf',
        '.mp3': 'audio/mpeg',
        '.mp4': 'video/mp4',
        '.zip': 'application/zip'
    };

    return contentTypes[ext] || 'application/octet-stream';
}

/**
 * Move processed file to processed directory
 */
async function moveFileToProcessed(s3Event) {
    try {
        const sourceKey = s3Event.key;
        const targetKey = sourceKey.replace(CONFIG.UPLOAD_PREFIX, CONFIG.PROCESSED_PREFIX);

        // Copy to processed directory
        await s3.copyObject({
            Bucket: s3Event.bucket,
            CopySource: `${s3Event.bucket}/${sourceKey}`,
            Key: targetKey,
            MetadataDirective: 'COPY'
        }).promise();

        // Delete original
        await s3.deleteObject({
            Bucket: s3Event.bucket,
            Key: sourceKey
        }).promise();

        console.log('Moved processed file:', { from: sourceKey, to: targetKey });
    } catch (error) {
        console.error('Failed to move processed file:', error);
        // Don't throw here - this is cleanup, not critical for function success
    }
}

/**
 * Move failed file to failed directory
 */
async function moveFileToFailed(s3Key) {
    try {
        const sourceKey = s3Key;
        const targetKey = sourceKey.replace(CONFIG.UPLOAD_PREFIX, CONFIG.FAILED_PREFIX);

        // Copy to failed directory
        await s3.copyObject({
            Bucket: CONFIG.BUCKET_NAME,
            CopySource: `${CONFIG.BUCKET_NAME}/${sourceKey}`,
            Key: targetKey,
            MetadataDirective: 'COPY'
        }).promise();

        // Delete original
        await s3.deleteObject({
            Bucket: CONFIG.BUCKET_NAME,
            Key: sourceKey
        }).promise();

        console.log('Moved failed file:', { from: sourceKey, to: targetKey });
    } catch (error) {
        console.error('Failed to move failed file:', error);
    }
}

/**
 * Send webhook notification to WordPress
 */
async function sendWebhook(result, success) {
    if (!CONFIG.WEBHOOK_URL) {
        console.log('No webhook URL configured, skipping notification');
        return;
    }

    try {
        const https = require('https');
        const url = require('url');

        const webhookUrl = new URL(CONFIG.WEBHOOK_URL);
        const postData = JSON.stringify({
            success,
            tourName: result.tourName,
            s3Key: result.s3Key,
            message: result.message,
            filesExtracted: result.filesExtracted,
            totalSize: result.totalSize,
            processingTime: result.processingTime,
            timestamp: new Date().toISOString()
        });

        const options = {
            hostname: webhookUrl.hostname,
            port: webhookUrl.port || 443,
            path: webhookUrl.pathname + webhookUrl.search,
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(postData),
                'User-Agent': 'AWS-Lambda-Tour-Processor/1.0'
            }
        };

        return new Promise((resolve, reject) => {
            const req = https.request(options, (res) => {
                let responseBody = '';

                res.on('data', (chunk) => {
                    responseBody += chunk;
                });

                res.on('end', () => {
                    if (res.statusCode >= 200 && res.statusCode < 300) {
                        console.log('Webhook sent successfully:', res.statusCode);
                        resolve(responseBody);
                    } else {
                        console.error('Webhook failed:', res.statusCode, responseBody);
                        reject(new Error(`Webhook failed with status ${res.statusCode}`));
                    }
                });
            });

            req.on('error', (error) => {
                console.error('Webhook request error:', error);
                reject(error);
            });

            req.setTimeout(30000, () => {
                req.destroy();
                reject(new Error('Webhook request timeout'));
            });

            req.write(postData);
            req.end();
        });

    } catch (error) {
        console.error('Failed to send webhook:', error);
    }
}

/**
 * Send error alert via SNS
 */
async function sendErrorAlert(error, result) {
    if (!CONFIG.SNS_TOPIC_ARN) {
        return;
    }

    try {
        const message = {
            error: error.message,
            tourName: result.tourName,
            s3Key: result.s3Key,
            processingTime: result.processingTime,
            timestamp: new Date().toISOString(),
            lambdaRequestId: process.env.AWS_LAMBDA_REQUEST_ID
        };

        await sns.publish({
            TopicArn: CONFIG.SNS_TOPIC_ARN,
            Subject: `Tour Processing Failed: ${result.tourName}`,
            Message: JSON.stringify(message, null, 2)
        }).promise();

        console.log('Error alert sent via SNS');
    } catch (snsError) {
        console.error('Failed to send SNS alert:', snsError);
    }
}