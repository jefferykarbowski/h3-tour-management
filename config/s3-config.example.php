<?php
/**
 * S3 Configuration Template
 *
 * Copy this file to s3-config.php and fill in your S3 details
 * This provides fallback configuration for standalone operation
 */

return array(
    'bucket' => 'your-s3-bucket-name',
    'region' => 'us-east-1', // Change to your AWS region

    // Optional: If you need to override endpoint for testing
    // 'endpoint' => 'https://s3.amazonaws.com',

    // Performance settings
    'cache_duration' => 3600, // 1 hour in seconds

    // Security settings
    'allowed_extensions' => array(
        'html', 'htm', 'js', 'css', 'png', 'jpg', 'jpeg',
        'gif', 'svg', 'mp4', 'webm', 'json', 'xml', 'txt', 'pdf'
    ),

    'max_file_size' => 50 * 1024 * 1024, // 50MB limit
);