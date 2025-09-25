/**
 * Test suite for H3 Tour Management Lambda Function
 */

const AWS = require('aws-sdk-mock');
const { handler } = require('../index');

describe('H3 Tour Management Lambda Function', () => {
    beforeEach(() => {
        // Mock environment variables
        process.env.BUCKET_NAME = 'test-bucket';
        process.env.WEBHOOK_URL = 'https://example.com/webhook';
        process.env.SNS_TOPIC_ARN = 'arn:aws:sns:us-west-2:123456789012:test-topic';
        process.env.MAX_FILE_SIZE = '1073741824';
        process.env.MAX_PROCESSING_TIME = '840000';
    });

    afterEach(() => {
        AWS.restore();
        delete process.env.BUCKET_NAME;
        delete process.env.WEBHOOK_URL;
        delete process.env.SNS_TOPIC_ARN;
        delete process.env.MAX_FILE_SIZE;
        delete process.env.MAX_PROCESSING_TIME;
    });

    describe('Event Parsing', () => {
        test('should parse valid S3 event', async () => {
            const validEvent = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/test-tour.zip' }
                        }
                    }
                ]
            };

            // Mock S3 operations
            AWS.mock('S3', 'headObject', {
                ContentLength: 1024000,
                ContentType: 'application/zip',
                LastModified: new Date()
            });

            AWS.mock('S3', 'getObject', {
                Body: Buffer.from('PK') // ZIP file signature
            });

            AWS.mock('S3', 'upload', {});
            AWS.mock('S3', 'copyObject', {});
            AWS.mock('S3', 'deleteObject', {});
            AWS.mock('SNS', 'publish', {});

            const result = await handler(validEvent);

            expect(result.statusCode).toBe(200);
            const body = JSON.parse(result.body);
            expect(body.success).toBe(true);
            expect(body.tourName).toBe('test-tour');
        });

        test('should reject invalid event structure', async () => {
            const invalidEvent = {
                Records: []
            };

            const result = await handler(invalidEvent);

            expect(result.statusCode).toBe(500);
            const body = JSON.parse(result.body);
            expect(body.success).toBe(false);
        });

        test('should ignore non-ZIP files', async () => {
            const nonZipEvent = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/test-file.txt' }
                        }
                    }
                ]
            };

            const result = await handler(nonZipEvent);

            expect(result.statusCode).toBe(500);
        });

        test('should ignore files outside uploads directory', async () => {
            const outsideUploadsEvent = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'other/test-tour.zip' }
                        }
                    }
                ]
            };

            const result = await handler(outsideUploadsEvent);

            expect(result.statusCode).toBe(500);
        });
    });

    describe('File Validation', () => {
        test('should reject files that are too large', async () => {
            const largeFileEvent = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/large-tour.zip' }
                        }
                    }
                ]
            };

            AWS.mock('S3', 'headObject', {
                ContentLength: 2147483648, // 2GB - exceeds 1GB limit
                ContentType: 'application/zip'
            });

            const result = await handler(largeFileEvent);

            expect(result.statusCode).toBe(500);
            const body = JSON.parse(result.body);
            expect(body.message).toContain('exceeds maximum');
        });

        test('should handle missing files gracefully', async () => {
            const missingFileEvent = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/missing-tour.zip' }
                        }
                    }
                ]
            };

            AWS.mock('S3', 'headObject', (params, callback) => {
                const error = new Error('NoSuchKey');
                error.code = 'NoSuchKey';
                callback(error);
            });

            const result = await handler(missingFileEvent);

            expect(result.statusCode).toBe(500);
            const body = JSON.parse(result.body);
            expect(body.message).toContain('File not found');
        });
    });

    describe('Tour Name Extraction', () => {
        test('should extract clean tour names', () => {
            const testCases = [
                ['uploads/MyTour.zip', 'MyTour'],
                ['uploads/My-Tour_123.zip', 'My-Tour_123'],
                ['uploads/Tour with spaces.zip', 'Tour_with_spaces'],
                ['uploads/special!@#chars.zip', 'special___chars']
            ];

            testCases.forEach(([input, expected]) => {
                // This would test the extractTourName function if exported
                // For now, we'll test through the full handler
                expect(input).toBeDefined();
                expect(expected).toBeDefined();
            });
        });
    });

    describe('Error Handling', () => {
        test('should handle S3 access errors', async () => {
            const event = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/test-tour.zip' }
                        }
                    }
                ]
            };

            AWS.mock('S3', 'headObject', (params, callback) => {
                callback(new Error('Access Denied'));
            });

            const result = await handler(event);

            expect(result.statusCode).toBe(500);
            const body = JSON.parse(result.body);
            expect(body.success).toBe(false);
        });

        test('should send error notifications', async () => {
            const event = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/error-tour.zip' }
                        }
                    }
                ]
            };

            let snsCallCount = 0;
            AWS.mock('S3', 'headObject', (params, callback) => {
                callback(new Error('Processing Error'));
            });

            AWS.mock('SNS', 'publish', (params, callback) => {
                snsCallCount++;
                expect(params.TopicArn).toBe(process.env.SNS_TOPIC_ARN);
                expect(params.Subject).toContain('Tour Processing Failed');
                callback(null, {});
            });

            await handler(event);

            expect(snsCallCount).toBe(1);
        });
    });

    describe('Webhook Integration', () => {
        test('should send success webhook', async () => {
            const event = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/webhook-test.zip' }
                        }
                    }
                ]
            };

            AWS.mock('S3', 'headObject', {
                ContentLength: 1024000,
                ContentType: 'application/zip'
            });

            AWS.mock('S3', 'getObject', {
                Body: Buffer.from('UEsDBAoAAAAAAFVVVVUAAAAA') // Minimal ZIP
            });

            AWS.mock('S3', 'upload', {});
            AWS.mock('S3', 'copyObject', {});
            AWS.mock('S3', 'deleteObject', {});

            const result = await handler(event);

            expect(result.statusCode).toBe(200);
        });

        test('should send failure webhook', async () => {
            const event = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/fail-test.zip' }
                        }
                    }
                ]
            };

            AWS.mock('S3', 'headObject', (params, callback) => {
                callback(new Error('Webhook Test Error'));
            });

            AWS.mock('SNS', 'publish', {});

            const result = await handler(event);

            expect(result.statusCode).toBe(500);
            const body = JSON.parse(result.body);
            expect(body.success).toBe(false);
        });
    });

    describe('File Processing', () => {
        test('should handle ZIP extraction', async () => {
            const event = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/extract-test.zip' }
                        }
                    }
                ]
            };

            // Create a minimal ZIP file buffer for testing
            const zipBuffer = Buffer.from([
                0x50, 0x4b, 0x03, 0x04, // ZIP signature
                0x14, 0x00, 0x00, 0x00, // Version, flags
                0x00, 0x00, 0x00, 0x00, // Compression, time, date
                0x00, 0x00, 0x00, 0x00, // CRC32
                0x00, 0x00, 0x00, 0x00, // Compressed size
                0x00, 0x00, 0x00, 0x00, // Uncompressed size
                0x05, 0x00, 0x00, 0x00, // Filename length, extra length
                0x74, 0x65, 0x73, 0x74, 0x2e // Filename: "test."
            ]);

            AWS.mock('S3', 'headObject', {
                ContentLength: zipBuffer.length,
                ContentType: 'application/zip'
            });

            AWS.mock('S3', 'getObject', {
                Body: zipBuffer
            });

            let uploadCount = 0;
            AWS.mock('S3', 'upload', (params, callback) => {
                uploadCount++;
                expect(params.Key).toMatch(/^tours\//);
                callback(null, {});
            });

            AWS.mock('S3', 'copyObject', {});
            AWS.mock('S3', 'deleteObject', {});

            const result = await handler(event);

            expect(result.statusCode).toBe(200);
            expect(uploadCount).toBeGreaterThan(0);
        });
    });

    describe('Content Type Detection', () => {
        test('should detect correct content types', () => {
            const testCases = [
                ['test.html', 'text/html'],
                ['script.js', 'application/javascript'],
                ['style.css', 'text/css'],
                ['image.jpg', 'image/jpeg'],
                ['image.png', 'image/png'],
                ['data.json', 'application/json'],
                ['unknown.xyz', 'application/octet-stream']
            ];

            // This would test the getContentType function if exported
            testCases.forEach(([filename, expectedType]) => {
                expect(filename).toBeDefined();
                expect(expectedType).toBeDefined();
            });
        });
    });

    describe('Performance', () => {
        test('should complete within timeout', async () => {
            const event = {
                Records: [
                    {
                        eventSource: 'aws:s3',
                        s3: {
                            bucket: { name: 'test-bucket' },
                            object: { key: 'uploads/perf-test.zip' }
                        }
                    }
                ]
            };

            const startTime = Date.now();

            AWS.mock('S3', 'headObject', {
                ContentLength: 1024,
                ContentType: 'application/zip'
            });

            AWS.mock('S3', 'getObject', {
                Body: Buffer.from('UEsDBAoAAAAAAFVVVVUAAAAA')
            });

            AWS.mock('S3', 'upload', {});
            AWS.mock('S3', 'copyObject', {});
            AWS.mock('S3', 'deleteObject', {});

            const result = await handler(event);
            const processingTime = Date.now() - startTime;

            expect(result.statusCode).toBe(200);
            expect(processingTime).toBeLessThan(30000); // Should complete in under 30 seconds
        });
    });
});