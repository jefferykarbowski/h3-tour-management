// Use AWS SDK v3 (built into Node.js 18 Lambda)
const { S3Client, CopyObjectCommand, DeleteObjectCommand, GetObjectCommand, PutObjectCommand, ListObjectsV2Command } = require('@aws-sdk/client-s3');
const s3 = new S3Client({ region: 'us-east-1' });

// Import migration handler
const migrationHandler = require('./migrate-tours');

exports.handler = async (event) => {
    console.log('H3 Tour Processor Lambda triggered');

    // Parse event body if it's a Function URL request (comes as base64 encoded JSON)
    let parsedEvent = event;

    if (event.body) {
        console.log('📦 Function URL request detected, parsing body...');
        try {
            // Decode and parse the JSON body
            const body = event.isBase64Encoded ? Buffer.from(event.body, 'base64').toString() : event.body;
            parsedEvent = JSON.parse(body);
            console.log('Parsed event:', JSON.stringify(parsedEvent, null, 2));
        } catch (error) {
            console.error('❌ Failed to parse event body:', error);
            return {
                statusCode: 400,
                body: JSON.stringify({ error: 'Invalid request body' })
            };
        }
    }

    // Check if this is a migration request
    if (parsedEvent.action === 'migrate_tours') {
        console.log('🔄 Migration action detected - routing to migration handler');
        return await migrationHandler.handler(parsedEvent);
    }

    // Check if this is a direct invocation for deletion
    if (parsedEvent.action === 'delete_tour') {
        console.log('🗑️ Deletion action detected');
        return await handleTourDeletion(parsedEvent);
    }

    // Check if this is a tour update request
    if (parsedEvent.action === 'update_tour') {
        console.log('🔄 Update tour action detected');
        return await handleTourUpdate(parsedEvent);
    }

    // Check if this is an S3 event (has Records array)
    if (!parsedEvent.Records || !Array.isArray(parsedEvent.Records)) {
        console.log('❌ Unknown event type - neither deletion nor S3 event');
        console.log('Event structure:', Object.keys(parsedEvent));
        return {
            statusCode: 400,
            body: JSON.stringify({ error: 'Invalid event type', receivedKeys: Object.keys(parsedEvent) })
        };
    }

    // Extract S3 event information (for new uploads)
    for (const record of parsedEvent.Records) {
        if (record.eventName.startsWith('ObjectCreated')) {
            const bucket = record.s3.bucket.name;
            const key = decodeURIComponent(record.s3.object.key);

            console.log(`Processing file: ${key} from bucket: ${bucket}`);

            try {
                const tourId = extractTourId(key);
                console.log(`🎯 Extracted tour_id: ${tourId}`);
                const tourName = extractTourNameFromZip(key);
                console.log(`📝 Extracted tour name from ZIP filename: ${tourName}`);

                // Step 1: Download ZIP from S3
                console.log('⬇️ Step 1: Downloading ZIP from S3...');
                const zipData = await downloadZipFromS3(bucket, key);

                if (!zipData) {
                    throw new Error('Failed to download ZIP from S3');
                }

                console.log(`📦 Downloaded ${zipData.length} bytes`);

                // Step 2: Extract ZIP and handle nested structure
                console.log('📂 Step 2: Extracting ZIP and processing nested structure...');
                const extractedFiles = await extractAndProcessZip(zipData, tourId);

                if (!extractedFiles || extractedFiles.length === 0) {
                    throw new Error('No files extracted from ZIP');
                }

                console.log(`📋 Extracted ${extractedFiles.length} files`);

                // Step 3: Upload extracted files to tours/ directory
                console.log('⬆️ Step 3: Uploading extracted files to S3 tours/...');
                let uploadedCount = 0;

                for (const file of extractedFiles) {
                    const s3Key = `tours/${tourId}/${file.path}`;
                    let fileData = file.data;

                    // Inject analytics script into index.htm files
                    if (file.path === 'index.htm' || file.path === 'index.html') {
                        console.log('📊 Injecting analytics script into index file...');
                        fileData = injectAnalyticsScript(file.data.toString(), tourName, tourId);
                    }

                    await s3.send(new PutObjectCommand({
                        Bucket: bucket,
                        Key: s3Key,
                        Body: fileData,
                        ContentType: getContentType(file.path)
                        // Bucket policy handles public access - ACLs are disabled on this bucket
                    }));

                    uploadedCount++;

                    if (uploadedCount % 10 === 0) {
                        console.log(`📤 Uploaded ${uploadedCount}/${extractedFiles.length} files...`);
                    }
                }

                console.log(`✅ Successfully uploaded ${uploadedCount} tour files`);

                // Step 4: Clean up original upload
                console.log('🧹 Step 4: Cleaning up original upload...');
                await s3.send(new DeleteObjectCommand({
                    Bucket: bucket,
                    Key: key
                }));

                console.log(`🧹 Cleaned up original upload: ${key}`);
                console.log(`🎉 Tour "${tourName}" (ID: ${tourId}) processed successfully!`);
                console.log(`🌐 Available at: https://${bucket}.s3.us-east-1.amazonaws.com/tours/${tourId}/index.htm`);

                // Step 5: Notify WordPress to register the tour
                console.log('📞 Step 5: Notifying WordPress...');
                await notifyWordPress(tourName, tourId, bucket, uploadedCount);

                console.log(`✅ Complete! Tour "${tourName}" is ready!`);

            } catch (error) {
                console.error('Processing failed:', error);
                throw error;
            }
        }
    }

    return { statusCode: 200, body: 'Tour processing completed' };
};

/**
 * Extract tour_id from S3 key
 * New format: uploads/{tour_id}/{tour_id}.zip
 * Example: uploads/20250114_173045_8k3j9d2m/20250114_173045_8k3j9d2m.zip
 */
function extractTourId(s3Key) {
    const parts = s3Key.split('/');
    // tour_id is the folder name (second part)
    return parts[1];
}

/**
 * Extract tour name from ZIP filename for display purposes
 * Note: This is kept for backward compatibility with analytics
 * The actual folder structure uses tour_id
 */
function extractTourNameFromZip(s3Key) {
    const parts = s3Key.split('/');
    const fileName = parts[parts.length - 1];
    return fileName.replace('.zip', '');
}

function getFileName(s3Key) {
    const parts = s3Key.split('/');
    return parts[parts.length - 1];
}

function getContentType(filePath) {
    const ext = filePath.split('.').pop().toLowerCase();
    const contentTypes = {
        'html': 'text/html',
        'htm': 'text/html',
        'js': 'application/javascript',
        'css': 'text/css',
        'png': 'image/png',
        'jpg': 'image/jpeg',
        'jpeg': 'image/jpeg',
        'gif': 'image/gif',
        'mp4': 'video/mp4',
        'json': 'application/json',
        'txt': 'text/plain'
    };
    return contentTypes[ext] || 'application/octet-stream';
}

// Inject analytics script tag into HTML
function injectAnalyticsScript(htmlContent, tourName, tourId) {
    try {
        console.log('📊 Analytics injection: Processing HTML content, length:', htmlContent.length);
        console.log('📊 Analytics injection: Tour name:', tourName);
        console.log('📊 Analytics injection: Tour ID:', tourId);

        // Check if </head> exists
        if (!htmlContent.includes('</head>')) {
            console.log('⚠️ Analytics injection: No </head> tag found in HTML');
            return htmlContent; // Return original if no </head> found
        }

        // Replace incorrect media paths (if any exist)
        // Change /h3panos/{anything}/media/ to just ./media/
        htmlContent = htmlContent.replace(/\/h3panos\/[^/]+\/media\//g, './media/');

        // Also fix any references to h3panos in general
        htmlContent = htmlContent.replace(/\/h3panos\/[^/]+\//g, './');

        // Inject external analytics script from WordPress site
        // This allows centralized management of analytics code
        const WORDPRESS_SITE = process.env.WORDPRESS_SITE || 'https://h3vt.com'; // Can be configured via environment variable

        // Get the page title from the HTML if it exists
        let pageTitle = tourName; // Default to tour name
        const titleMatch = htmlContent.match(/<title>([^<]*)<\/title>/i);
        if (titleMatch && titleMatch[1]) {
            pageTitle = titleMatch[1].trim();
        }

        // Build the analytics script URL with all parameters
        // Note: We pass both tour_id and tour_name for flexibility
        const analyticsParams = new URLSearchParams({
            tour: tourName,
            tour_id: tourId,
            title: pageTitle,
            path: `/tours/${tourId}/`
        });

        const analyticsTag = `
<!-- H3 Tour Analytics - Managed by WordPress -->
<script async src="${WORDPRESS_SITE}/h3-tour-analytics.js?${analyticsParams.toString()}"></script>
<!-- End H3 Tour Analytics -->
</head>`;

        // Replace </head> with analytics tag + </head>
        const updatedHtml = htmlContent.replace('</head>', analyticsTag);

        console.log('✅ External analytics script reference injected successfully');
        console.log('📏 Original HTML length:', htmlContent.length, '→ Updated:', updatedHtml.length);

        return updatedHtml;

    } catch (error) {
        console.error('❌ Analytics injection failed:', error);
        return htmlContent; // Return original on error
    }
}

/**
 * Send progress webhook to WordPress
 */
async function sendProgressWebhook(webhookUrl, webhookSecret, tourId, stage, progress, message, additionalData = {}) {
    if (!webhookUrl) return { success: false, error: 'No webhook URL provided' };

    const crypto = require('crypto');
    const https = require('https');

    const payload = {
        type: 'progress',
        tourId: tourId,
        status: 'processing',
        stage: stage,
        progress: progress,
        message: message,
        timestamp: new Date().toISOString(),
        ...additionalData
    };

    const signature = 'sha256=' + crypto
        .createHmac('sha256', webhookSecret)
        .update(JSON.stringify(payload))
        .digest('hex');

    return new Promise((resolve) => {
        try {
            const url = new URL(webhookUrl);
            const postData = JSON.stringify(payload);

            const options = {
                hostname: url.hostname,
                port: url.port || 443,
                path: url.pathname + url.search,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(postData),
                    'X-Webhook-Signature': signature
                },
                timeout: 10000
            };

            const req = https.request(options, (res) => {
                let body = '';
                res.on('data', (chunk) => body += chunk);
                res.on('end', () => {
                    if (res.statusCode >= 200 && res.statusCode < 300) {
                        console.log(`✅ Progress webhook sent: ${stage} ${progress}% - ${message}`);
                        resolve({ success: true, body });
                    } else {
                        console.warn(`⚠️ Progress webhook failed: ${res.statusCode}`);
                        resolve({ success: false, statusCode: res.statusCode });
                    }
                });
            });

            req.on('error', (error) => {
                console.error(`❌ Progress webhook error: ${error.message}`);
                resolve({ success: false, error: error.message });
            });

            req.on('timeout', () => {
                req.destroy();
                console.warn('⚠️ Progress webhook timeout');
                resolve({ success: false, error: 'timeout' });
            });

            req.write(postData);
            req.end();
        } catch (error) {
            console.error('❌ Progress webhook exception:', error);
            resolve({ success: false, error: error.message });
        }
    });
}

/**
 * Handle tour update request
 * Downloads ZIP from S3, extracts, uploads to tours/ directory, and invalidates cache
 */
async function handleTourUpdate(event) {
    const { bucket, tourId, s3Key, webhookUrl, webhookSecret, enableProgressUpdates = false } = event;
    const startTime = Date.now();

    console.log(`🔄 Starting tour update for: ${tourId}`);
    console.log(`📦 Source: s3://${bucket}/${s3Key}`);
    console.log(`📡 Progress updates: ${enableProgressUpdates ? 'enabled' : 'disabled'}`);

    try {
        // Extract tour name from tourId for display
        const tourName = tourId.replace(/\//g, '-');

        // Step 0: Initial progress
        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'initializing', 5,
                'Starting tour update process...');
        }

        // Step 1: Download ZIP from S3
        console.log('⬇️ Step 1: Downloading ZIP from S3...');
        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'downloading', 15,
                `Downloading ${s3Key.split('/').pop()} from S3...`);
        }

        const zipData = await downloadZipFromS3(bucket, s3Key);
        if (!zipData) {
            throw new Error('Failed to download ZIP from S3');
        }

        console.log(`📦 Downloaded ${zipData.length} bytes`);
        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'downloading', 30,
                'Download complete');
        }

        // Step 2: Extract ZIP
        console.log('📂 Step 2: Extracting ZIP...');
        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'extracting', 35,
                'Extracting tour files...');
        }

        const extractedFiles = await extractAndProcessZip(zipData, tourId);
        if (!extractedFiles || extractedFiles.length === 0) {
            throw new Error('No files extracted from ZIP');
        }

        console.log(`📋 Extracted ${extractedFiles.length} files`);
        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'extracting', 50,
                `Extracted ${extractedFiles.length} files`,
                { filesProcessed: extractedFiles.length, totalFiles: extractedFiles.length });
        }

        // Step 3: Upload to S3
        console.log('⬆️ Step 3: Uploading files to S3 tours/...');
        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'uploading', 55,
                `Uploading ${extractedFiles.length} files to S3...`,
                { filesProcessed: 0, totalFiles: extractedFiles.length });
        }

        let uploadedCount = 0;
        for (const file of extractedFiles) {
            const s3UploadKey = `tours/${tourId}/${file.path}`;
            let fileData = file.data;

            // Inject analytics script into index files
            if (file.path === 'index.htm' || file.path === 'index.html') {
                console.log('📊 Injecting analytics script into index file...');
                fileData = injectAnalyticsScript(file.data.toString(), tourName, tourId);
            }

            await s3.send(new PutObjectCommand({
                Bucket: bucket,
                Key: s3UploadKey,
                Body: fileData,
                ContentType: getContentType(file.path)
            }));

            uploadedCount++;

            // Send progress update every 10 files or at completion
            if (enableProgressUpdates && webhookUrl && webhookSecret &&
                (uploadedCount % 10 === 0 || uploadedCount === extractedFiles.length)) {
                const uploadProgress = 55 + Math.floor((uploadedCount / extractedFiles.length) * 30);
                await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'uploading', uploadProgress,
                    `Uploaded ${uploadedCount}/${extractedFiles.length} files`,
                    { filesProcessed: uploadedCount, totalFiles: extractedFiles.length });
            }

            if (uploadedCount % 10 === 0) {
                console.log(`📤 Uploaded ${uploadedCount}/${extractedFiles.length} files...`);
            }
        }

        console.log(`✅ Successfully uploaded ${uploadedCount} tour files`);

        // Step 4: CloudFront invalidation (if configured)
        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'invalidating', 88,
                'Invalidating CloudFront cache...');
        }

        // Note: CloudFront invalidation is now handled by WordPress after receiving the webhook
        console.log('📡 CloudFront invalidation will be handled by WordPress');

        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'invalidating', 95,
                'Cache invalidation queued');
        }

        // Step 5: Cleanup
        console.log('🧹 Step 5: Cleaning up uploaded ZIP...');
        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'cleanup', 97,
                'Cleaning up temporary files...');
        }

        await s3.send(new DeleteObjectCommand({
            Bucket: bucket,
            Key: s3Key
        }));

        console.log(`🧹 Cleaned up: ${s3Key}`);

        const processingTime = Date.now() - startTime;

        // Send completion progress
        if (enableProgressUpdates && webhookUrl && webhookSecret) {
            await sendProgressWebhook(webhookUrl, webhookSecret, tourId, 'completing', 100,
                'Tour update complete!');
        }

        // Step 6: Notify WordPress of completion
        console.log('📞 Step 6: Notifying WordPress...');
        await notifyWordPress(tourName, tourId, bucket, uploadedCount, webhookUrl, webhookSecret, zipData.length, processingTime);

        console.log(`🎉 Tour "${tourName}" (ID: ${tourId}) updated successfully!`);

        return {
            statusCode: 200,
            body: JSON.stringify({
                success: true,
                message: 'Tour updated successfully',
                tourId: tourId,
                filesUploaded: uploadedCount,
                processingTime: processingTime
            })
        };

    } catch (error) {
        console.error('❌ Tour update failed:', error);

        // Send failure webhook
        if (webhookUrl && webhookSecret) {
            const crypto = require('crypto');
            const failurePayload = {
                success: false,
                tourName: tourId.replace(/\//g, '-'),
                tourId: tourId,
                s3Key: s3Key,
                message: error.message,
                timestamp: new Date().toISOString(),
                processingTime: Date.now() - startTime
            };

            try {
                await fetch(webhookUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Webhook-Signature': 'sha256=' + crypto
                            .createHmac('sha256', webhookSecret)
                            .update(JSON.stringify(failurePayload))
                            .digest('hex')
                    },
                    body: JSON.stringify(failurePayload)
                });
            } catch (webhookError) {
                console.error('❌ Failed to send failure webhook:', webhookError);
            }
        }

        return {
            statusCode: 500,
            body: JSON.stringify({
                success: false,
                message: error.message,
                error: error.stack
            })
        };
    }
}

// Notify WordPress that tour processing is complete
async function notifyWordPress(tourName, tourId, bucket, filesCount, webhookUrl = null, webhookSecret = null, totalSize = 0, processingTime = 0) {
    try {
        // Use provided webhook URL or fall back to environment variable
        const effectiveWebhookUrl = webhookUrl || process.env.WORDPRESS_WEBHOOK_URL;
        const effectiveWebhookSecret = webhookSecret || '';

        if (!effectiveWebhookUrl) {
            console.log('⚠️ WORDPRESS_WEBHOOK_URL not configured, skipping notification');
            return false;
        }

        const payload = {
            success: true,
            tourName: tourName,
            tourId: tourId,  // Include tour_id for metadata updates
            s3Key: `uploads/${tourId}/${tourId}.zip`,  // Add s3Key for webhook validation
            s3FolderName: tourId,  // Folder name is now the tour_id
            s3Bucket: bucket,
            filesExtracted: filesCount,
            processingTime: processingTime,
            totalSize: totalSize,
            message: `Tour "${tourName}" (ID: ${tourId}) processed successfully`,
            timestamp: new Date().toISOString(),
            s3Url: `https://${bucket}.s3.us-east-1.amazonaws.com/tours/${tourId}/`
        };

        console.log(`📞 Sending webhook to: ${effectiveWebhookUrl}`);
        console.log(`📊 Payload:`, JSON.stringify(payload, null, 2));

        // Build headers
        const headers = {
            'Content-Type': 'application/json',
            'User-Agent': 'H3-Lambda-Processor/1.0'
        };

        // Add signature if webhook secret is provided
        if (effectiveWebhookSecret) {
            const crypto = require('crypto');
            const signature = 'sha256=' + crypto
                .createHmac('sha256', effectiveWebhookSecret)
                .update(JSON.stringify(payload))
                .digest('hex');
            headers['X-Webhook-Signature'] = signature;
            console.log('🔐 Added webhook signature to completion webhook');
        }

        const response = await fetch(effectiveWebhookUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(payload)
        });

        if (response.ok) {
            console.log('✅ WordPress notified successfully');
            const responseText = await response.text();
            console.log('Response:', responseText);
            return true;
        } else {
            console.error('❌ WordPress notification failed:', response.status, response.statusText);
            return false;
        }
    } catch (error) {
        console.error('❌ WordPress notification error:', error);
        return false;
    }
}

// Download ZIP file from S3
async function downloadZipFromS3(bucket, key) {
    try {
        console.log(`⬇️ Downloading ${key} from ${bucket}...`);

        const response = await s3.send(new GetObjectCommand({
            Bucket: bucket,
            Key: key
        }));

        // Convert stream to buffer
        const chunks = [];
        for await (const chunk of response.Body) {
            chunks.push(chunk);
        }

        const buffer = Buffer.concat(chunks);
        console.log(`📦 Downloaded ${buffer.length} bytes`);
        return buffer;

    } catch (error) {
        console.error('❌ Download failed:', error);
        return null;
    }
}

// Extract ZIP and handle nested Web.zip structure
async function extractAndProcessZip(zipData, tourId) {
    const AdmZip = require('adm-zip');

    try {
        console.log('📂 Opening main ZIP...');
        const zip = new AdmZip(zipData);
        const entries = zip.getEntries();

        console.log(`📋 Found ${entries.length} entries in main ZIP`);

        // Look for nested Web.zip structure
        let webZipEntry = null;
        for (const entry of entries) {
            console.log(`📄 Entry: ${entry.entryName}`);
            if (entry.entryName.endsWith('/Web.zip')) {
                webZipEntry = entry;
                console.log(`🎯 Found nested Web.zip: ${entry.entryName}`);
                break;
            }
        }

        if (webZipEntry) {
            // Extract the nested Web.zip
            console.log('📦 Extracting nested Web.zip...');
            const webZipData = webZipEntry.getData();
            const webZip = new AdmZip(webZipData);
            const webEntries = webZip.getEntries();

            console.log(`📁 Found ${webEntries.length} files in Web.zip`);

            const extractedFiles = [];
            for (const webEntry of webEntries) {
                if (!webEntry.isDirectory && webEntry.entryName.startsWith('Web/')) {
                    // Remove 'Web/' prefix to put files at tour root
                    const cleanPath = webEntry.entryName.substring(4);

                    // Skip system files
                    if (cleanPath.includes('__MACOSX') || cleanPath.includes('.DS_Store')) {
                        continue;
                    }

                    extractedFiles.push({
                        path: cleanPath,
                        data: webEntry.getData()
                    });

                    if (extractedFiles.length % 50 === 0) {
                        console.log(`📤 Processed ${extractedFiles.length} files...`);
                    }
                }
            }

            console.log(`✅ Extracted ${extractedFiles.length} tour files from Web.zip`);
            return extractedFiles;

        } else {
            // No nested structure, extract directly
            console.log('📂 No nested Web.zip found, extracting directly');
            const extractedFiles = [];
            for (const entry of entries) {
                if (!entry.isDirectory) {
                    extractedFiles.push({
                        path: entry.entryName,
                        data: entry.getData()
                    });
                }
            }
            return extractedFiles;
        }

    } catch (error) {
        console.error('❌ ZIP extraction failed:', error);
        return null;
    }
}
// Handle tour deletion - archive to archive/ folder
async function handleTourDeletion(event) {
    const { bucket, tourName } = event;

    console.log(`🗑️ Archiving tour: ${tourName} from S3 folder: ${tourName}`);

    try {
        // List all files in the tour directory
        const listResponse = await s3.send(new ListObjectsV2Command({
            Bucket: bucket,
            Prefix: `tours/${tourName}/`
        }));

        if (!listResponse.Contents || listResponse.Contents.length === 0) {
            console.log(`⚠️ No files found for tour: ${tourName}`);
            return {
                statusCode: 200,
                body: JSON.stringify({ success: false, message: 'No files to archive' })
            };
        }

        console.log(`📦 Found ${listResponse.Contents.length} files to archive`);

        // Archive all files (copy to archive/ then delete from tours/)
        let archivedCount = 0;
        for (const object of listResponse.Contents) {
            const archiveKey = object.Key.replace('tours/', 'archive/');

            await s3.send(new CopyObjectCommand({
                Bucket: bucket,
                CopySource: `${bucket}/${object.Key}`,
                Key: archiveKey
            }));

            await s3.send(new DeleteObjectCommand({
                Bucket: bucket,
                Key: object.Key
            }));

            archivedCount++;

            if (archivedCount % 10 === 0) {
                console.log(`📦 Archived ${archivedCount}/${listResponse.Contents.length} files...`);
            }
        }

        console.log(`✅ Archived ${archivedCount} files to archive/${tourName}/`);

        return {
            statusCode: 200,
            body: JSON.stringify({
                success: true,
                archivedCount: archivedCount,
                message: `Archived ${archivedCount} files`
            })
        };

    } catch (error) {
        console.error('❌ Archiving failed:', error);
        return {
            statusCode: 500,
            body: JSON.stringify({ success: false, error: error.message })
        };
    }
}
