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

// Notify WordPress that tour processing is complete
async function notifyWordPress(tourName, tourId, bucket, filesCount) {
    try {
        const webhookUrl = process.env.WORDPRESS_WEBHOOK_URL;

        if (!webhookUrl) {
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
            processingTime: 0, // Could track this with start/end timestamps
            totalSize: 0, // Could sum file sizes
            message: `Tour "${tourName}" (ID: ${tourId}) processed successfully`,
            timestamp: new Date().toISOString(),
            s3Url: `https://${bucket}.s3.us-east-1.amazonaws.com/tours/${tourId}/`
        };

        console.log(`📞 Sending webhook to: ${webhookUrl}`);
        console.log(`📊 Payload:`, JSON.stringify(payload, null, 2));

        const response = await fetch(webhookUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'User-Agent': 'H3-Lambda-Processor/1.0'
            },
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
