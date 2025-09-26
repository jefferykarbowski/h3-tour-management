// Tour deletion handler function
async function handleTourDeletion(event, s3Client) {
    const { bucket, tourName } = event;
    const tourFolder = tourName.replace(/ /g, '-'); // Convert spaces to dashes

    console.log('Deleting tour:', tourName, 'from folder:', tourFolder);

    try {
        // List all files in the tour directory
        const { S3Client, ListObjectsV2Command, DeleteObjectCommand, CopyObjectCommand } = require('@aws-sdk/client-s3');
        const s3 = s3Client || new S3Client({ region: 'us-east-1' });

        const listResponse = await s3.send(new ListObjectsV2Command({
            Bucket: bucket,
            Prefix: `tours/${tourFolder}/`
        }));

        if (!listResponse.Contents || listResponse.Contents.length === 0) {
            return { statusCode: 200, body: 'No files to delete' };
        }

        console.log(`Found ${listResponse.Contents.length} files to delete`);

        // Archive all files (move to archive/ instead of delete)
        for (const object of listResponse.Contents) {
            // Move file to archive directory
            const archiveKey = object.Key.replace('tours/', 'archive/');

            await s3.send(new CopyObjectCommand({
                Bucket: bucket,
                CopySource: `${bucket}/${object.Key}`,
                Key: archiveKey
            }));

            // Delete from tours/ after copying to archive/
            await s3.send(new DeleteObjectCommand({
                Bucket: bucket,
                Key: object.Key
            }));
        }

        console.log(`Successfully archived ${listResponse.Contents.length} files`);

        return {
            statusCode: 200,
            body: JSON.stringify({ success: true, deletedCount: listResponse.Contents.length })
        };

    } catch (error) {
        console.error('Deletion failed:', error);
        return { statusCode: 500, body: JSON.stringify({ success: false, error: error.message }) };
    }
}

module.exports = { handleTourDeletion };