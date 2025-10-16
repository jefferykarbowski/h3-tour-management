/**
 * One-Time Tour Migration Lambda
 * Converts all legacy tours to the new ID-based system
 *
 * This function:
 * 1. Lists all tours in S3
 * 2. Generates UUIDs for tours without IDs
 * 3. Creates metadata entries with tour_id, tour_slug, s3_folder
 * 4. Returns detailed migration report
 */

const { S3Client, ListObjectsV2Command } = require('@aws-sdk/client-s3');
const { RDSDataClient, ExecuteStatementCommand } = require('@aws-sdk/client-rds-data');
const crypto = require('crypto');

const s3Client = new S3Client({ region: process.env.AWS_REGION || 'us-east-1' });
const rdsClient = new RDSDataClient({ region: process.env.AWS_REGION || 'us-east-1' });

const BUCKET_NAME = process.env.BUCKET_NAME;
const DB_ARN = process.env.DB_CLUSTER_ARN;
const DB_SECRET_ARN = process.env.DB_SECRET_ARN;
const DB_NAME = process.env.DB_NAME || 'h3tm';
const TABLE_NAME = 'wp_h3tm_tour_metadata';

/**
 * Generate a unique tour ID (UUID v4)
 */
function generateTourId() {
    return crypto.randomUUID();
}

/**
 * Sanitize string to create URL-safe slug
 */
function sanitizeTitle(title) {
    return title
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * Get S3 folder name from tour name
 */
function getS3Folder(tourName) {
    return tourName.replace(/\s+/g, '');
}

/**
 * List all tours from S3
 */
async function listS3Tours() {
    const tours = new Set();
    let continuationToken;

    do {
        const command = new ListObjectsV2Command({
            Bucket: BUCKET_NAME,
            Delimiter: '/',
            ContinuationToken: continuationToken
        });

        const response = await s3Client.send(command);

        if (response.CommonPrefixes) {
            response.CommonPrefixes.forEach(prefix => {
                const tourName = prefix.Prefix.replace(/\/$/, '');
                if (tourName) {
                    tours.add(tourName);
                }
            });
        }

        continuationToken = response.NextContinuationToken;
    } while (continuationToken);

    return Array.from(tours);
}

/**
 * Get existing metadata from database
 */
async function getExistingMetadata() {
    const command = new ExecuteStatementCommand({
        resourceArn: DB_ARN,
        secretArn: DB_SECRET_ARN,
        database: DB_NAME,
        sql: `SELECT tour_id, display_name, tour_slug, s3_folder FROM ${TABLE_NAME}`
    });

    const response = await rdsClient.send(command);
    return response.records || [];
}

/**
 * Insert tour metadata into database
 */
async function insertTourMetadata(tourData) {
    const command = new ExecuteStatementCommand({
        resourceArn: DB_ARN,
        secretArn: DB_SECRET_ARN,
        database: DB_NAME,
        sql: `
            INSERT INTO ${TABLE_NAME}
            (tour_id, display_name, tour_slug, s3_folder, url_history, status, created_at, updated_at)
            VALUES
            (:tourId, :displayName, :tourSlug, :s3Folder, :urlHistory, :status, NOW(), NOW())
        `,
        parameters: [
            { name: 'tourId', value: { stringValue: tourData.tour_id } },
            { name: 'displayName', value: { stringValue: tourData.display_name } },
            { name: 'tourSlug', value: { stringValue: tourData.tour_slug } },
            { name: 's3Folder', value: { stringValue: tourData.s3_folder } },
            { name: 'urlHistory', value: { stringValue: '[]' } },
            { name: 'status', value: { stringValue: 'active' } }
        ]
    });

    return await rdsClient.send(command);
}

/**
 * Main migration handler
 */
exports.handler = async (event) => {
    console.log('Starting tour migration to ID-based system');

    const results = {
        success: true,
        timestamp: new Date().toISOString(),
        total_tours: 0,
        migrated: 0,
        skipped: 0,
        errors: 0,
        details: []
    };

    try {
        // Get all tours from S3
        const s3Tours = await listS3Tours();
        results.total_tours = s3Tours.length;
        console.log(`Found ${s3Tours.length} tours in S3`);

        if (s3Tours.length === 0) {
            return {
                statusCode: 200,
                body: JSON.stringify({
                    ...results,
                    message: 'No tours found in S3'
                })
            };
        }

        // Get existing metadata
        const existingMetadata = await getExistingMetadata();
        const existingMap = new Map();

        existingMetadata.forEach(record => {
            const displayName = record[1]?.stringValue;
            const tourSlug = record[2]?.stringValue;
            const tourId = record[0]?.stringValue;

            if (displayName) existingMap.set(displayName, { tourId, tourSlug });
            if (tourSlug) existingMap.set(tourSlug, { tourId, tourSlug });
        });

        // Process each S3 tour
        for (const s3Folder of s3Tours) {
            const detail = {
                s3_folder: s3Folder,
                action: 'none',
                reason: ''
            };

            try {
                // Check if tour already has metadata with tour_id
                const existing = existingMap.get(s3Folder) || existingMap.get(sanitizeTitle(s3Folder));

                if (existing && existing.tourId) {
                    detail.action = 'skipped';
                    detail.reason = 'Already has tour_id';
                    detail.tour_id = existing.tourId;
                    results.skipped++;
                } else {
                    // This is a legacy tour - needs migration
                    const tourId = generateTourId();
                    const tourSlug = sanitizeTitle(s3Folder);

                    const tourData = {
                        tour_id: tourId,
                        display_name: s3Folder,
                        tour_slug: tourSlug,
                        s3_folder: s3Folder
                    };

                    await insertTourMetadata(tourData);

                    detail.action = 'migrated';
                    detail.tour_id = tourId;
                    detail.tour_slug = tourSlug;
                    detail.reason = 'Successfully migrated to ID-based system';
                    results.migrated++;

                    console.log(`Migrated tour: ${s3Folder} -> ${tourId}`);
                }
            } catch (error) {
                detail.action = 'error';
                detail.reason = error.message;
                results.errors++;
                console.error(`Error migrating tour ${s3Folder}:`, error);
            }

            results.details.push(detail);
        }

        console.log('Migration complete:', {
            total: results.total_tours,
            migrated: results.migrated,
            skipped: results.skipped,
            errors: results.errors
        });

        return {
            statusCode: 200,
            headers: {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            body: JSON.stringify(results)
        };

    } catch (error) {
        console.error('Migration failed:', error);

        return {
            statusCode: 500,
            headers: {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            },
            body: JSON.stringify({
                success: false,
                error: error.message,
                stack: error.stack
            })
        };
    }
};
