// Use AWS SDK v3 (built into Node.js 18 Lambda)
const { S3Client, CopyObjectCommand, DeleteObjectCommand, GetObjectCommand, PutObjectCommand } = require('@aws-sdk/client-s3');
const s3 = new S3Client({ region: 'us-east-1' });

exports.handler = async (event) => {
    console.log('H3 Tour Processor Lambda triggered');
    console.log('Event:', JSON.stringify(event, null, 2));

    // Extract S3 event information
    for (const record of event.Records) {
        if (record.eventName.startsWith('ObjectCreated')) {
            const bucket = record.s3.bucket.name;
            const key = decodeURIComponent(record.s3.object.key);

            console.log(`Processing file: ${key} from bucket: ${bucket}`);

            try {
                const tourName = extractTourName(key);
                console.log(`🎯 Extracted tour name: ${tourName}`);

                // Step 1: Download ZIP from S3
                console.log('⬇️ Step 1: Downloading ZIP from S3...');
                const zipData = await downloadZipFromS3(bucket, key);

                if (!zipData) {
                    throw new Error('Failed to download ZIP from S3');
                }

                console.log(`📦 Downloaded ${zipData.length} bytes`);

                // Step 2: Extract ZIP and handle nested structure
                console.log('📂 Step 2: Extracting ZIP and processing nested structure...');
                const extractedFiles = await extractAndProcessZip(zipData, tourName);

                if (!extractedFiles || extractedFiles.length === 0) {
                    throw new Error('No files extracted from ZIP');
                }

                console.log(`📋 Extracted ${extractedFiles.length} files`);

                // Step 3: Upload extracted files to tours/ directory
                console.log('⬆️ Step 3: Uploading extracted files to S3 tours/...');
                let uploadedCount = 0;

                for (const file of extractedFiles) {
                    const s3Key = `tours/${tourName}/${file.path}`;
                    let fileData = file.data;

                    // Inject analytics script into index.htm files
                    if (file.path === 'index.htm' || file.path === 'index.html') {
                        console.log('📊 Injecting analytics script into index file...');
                        fileData = injectAnalyticsScript(file.data.toString(), tourName);
                    }

                    await s3.send(new PutObjectCommand({
                        Bucket: bucket,
                        Key: s3Key,
                        Body: fileData,
                        ContentType: getContentType(file.path)
                        // No ACL - bucket policy handles public access
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
                console.log(`🎉 Tour "${tourName}" processed successfully!`);
                console.log(`🌐 Available at: https://${bucket}.s3.us-east-1.amazonaws.com/tours/${tourName}/index.htm`);

                // Step 5: Notify WordPress to register the tour
                console.log('📞 Step 5: Notifying WordPress...');
                await notifyWordPress(tourName, bucket);

                console.log(`✅ Complete! Tour "${tourName}" is ready!`);

            } catch (error) {
                console.error('Processing failed:', error);
                throw error;
            }
        }
    }

    return { statusCode: 200, body: 'Tour processing completed' };
};

function extractTourName(s3Key) {
    // Extract tour name from uploads/uniqueid/Tour-Name.zip
    const parts = s3Key.split('/');
    const fileName = parts[parts.length - 1];
    return fileName.replace('.zip', '').replace(/-/g, ' ');
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
function injectAnalyticsScript(htmlContent, tourName) {
    // Create analytics script tag that loads from WordPress
    const scriptTag = `
<!-- H3 Tour Analytics -->
<script src="/h3-analytics.js" data-tour-name="${tourName}"></script>
<!-- End H3 Tour Analytics -->
</head>`;

    // Replace </head> with script tag + </head>
    const updatedHtml = htmlContent.replace('</head>', scriptTag);

    console.log('📊 Analytics script injected successfully');
    return updatedHtml;
}

// Notify WordPress that tour processing is complete
async function notifyWordPress(tourName, bucket) {
    try {
        // For now, just log that we would notify WordPress
        // TODO: Implement actual webhook to WordPress
        console.log(`📞 Would notify WordPress about tour: ${tourName}`);
        console.log(`📊 Tour registered with S3 URL`);

        // Could implement HTTP POST to WordPress webhook here
        // const webhook_url = 'https://yoursite.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook';
        // await fetch(webhook_url, { method: 'POST', body: JSON.stringify({...}) });

        return true;
    } catch (error) {
        console.error('WordPress notification failed:', error);
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
async function extractAndProcessZip(zipData, tourName) {
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