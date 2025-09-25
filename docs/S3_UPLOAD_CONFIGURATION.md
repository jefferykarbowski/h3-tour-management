# S3 Direct Upload Configuration Guide

This guide explains how to configure Amazon S3 direct uploads for large tour files (>100MB) in the H3 Tour Management plugin.

## Overview

The plugin now supports two upload methods:
- **Chunked Upload** (default): For files under 100MB, uploads in 1MB chunks through WordPress
- **S3 Direct Upload**: For files over 100MB, uploads directly to Amazon S3 for better performance

## Benefits of S3 Direct Upload

- **Faster uploads**: Direct to S3, bypassing WordPress server limits
- **Larger file support**: Handle files over 300MB reliably
- **Better reliability**: Reduced server timeout issues
- **Automatic fallback**: Falls back to chunked upload if S3 is unavailable

## Configuration Options

### Option 1: Environment Variables (Recommended)

Set these environment variables on your server:

```bash
AWS_ACCESS_KEY_ID=your_access_key_here
AWS_SECRET_ACCESS_KEY=your_secret_key_here
AWS_S3_BUCKET=your-bucket-name
AWS_S3_REGION=us-east-1
```

### Option 2: WordPress Options

Add these to your WordPress database options:

```php
update_option('h3tm_s3_access_key', 'your_access_key_here');
update_option('h3tm_s3_secret_key', 'your_secret_key_here');
update_option('h3tm_s3_bucket', 'your-bucket-name');
update_option('h3tm_s3_region', 'us-east-1');
```

## AWS S3 Setup

1. **Create an S3 Bucket**:
   - Log into AWS Console
   - Create a new S3 bucket
   - Note the bucket name and region

2. **Create IAM User**:
   - Create IAM user with programmatic access
   - Attach policy with these permissions:
   ```json
   {
       "Version": "2012-10-17",
       "Statement": [
           {
               "Effect": "Allow",
               "Action": [
                   "s3:PutObject",
                   "s3:GetObject",
                   "s3:DeleteObject"
               ],
               "Resource": "arn:aws:s3:::your-bucket-name/*"
           }
       ]
   }
   ```

3. **Configure CORS** (if needed):
   ```json
   [
       {
           "AllowedHeaders": ["*"],
           "AllowedMethods": ["PUT", "POST", "GET"],
           "AllowedOrigins": ["https://your-website.com"],
           "ExposeHeaders": []
       }
   ]
   ```

## How It Works

1. **File Selection**: User selects a file for upload
2. **Method Detection**: Plugin checks file size
   - Files ≤100MB → Chunked upload
   - Files >100MB → Attempt S3 direct upload
3. **S3 Attempt**:
   - Request presigned URL from WordPress
   - If S3 configured → Direct upload to S3
   - If S3 not configured → Fall back to chunked
4. **Processing**: WordPress downloads from S3 and processes the tour
5. **Cleanup**: S3 file is deleted after successful processing

## Upload Method Indicators

The interface shows which method will be used:

- **Large files**: "S3 Direct Upload (large file optimization)"
- **Standard files**: "Chunked Upload (standard processing)"
- **Progress bar**: Shows "(S3 Direct)" or "(Chunked)" during upload

## Troubleshooting

### S3 Upload Failures

If S3 upload fails, the system automatically falls back to chunked upload. Common issues:

1. **Invalid credentials**: Check AWS access key and secret
2. **Bucket permissions**: Ensure IAM user has S3 permissions
3. **CORS issues**: Configure CORS if uploading from different domain
4. **Network timeouts**: Check server internet connectivity

### Fallback Scenarios

The system falls back to chunked upload when:
- S3 credentials not configured
- S3 bucket not accessible
- AWS SDK fails to load
- S3 upload fails for any reason

### Debug Information

Enable WordPress debug logging to see detailed upload information:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for S3 upload details.

## Security Considerations

- Use environment variables for credentials (never commit to code)
- Restrict IAM permissions to minimum required
- Use HTTPS for all uploads
- Consider bucket policies to restrict access
- Regularly rotate access keys

## Performance Tips

1. **Choose optimal region**: Use S3 region closest to your server
2. **Monitor transfer speeds**: Large files may take time even with S3
3. **Consider multipart uploads**: For files >100MB, AWS automatically uses multipart
4. **Bandwidth limits**: Consider your server's upload bandwidth to S3

## File Size Limits

- **Chunked upload**: Limited by server PHP settings and disk space
- **S3 direct upload**: AWS S3 limit is 5TB per file
- **Plugin default**: Switches to S3 at 100MB (configurable)

## Cost Considerations

AWS S3 charges for:
- Storage (minimal for temporary files)
- PUT/GET requests
- Data transfer out (downloads from S3)

For most use cases, costs are minimal since files are temporary.