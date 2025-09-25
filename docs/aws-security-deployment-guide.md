# AWS S3 Security Deployment Guide

## Overview

This guide provides production-grade deployment instructions for the H3 Tour Management plugin's AWS S3 integration with enterprise-level security controls.

## Prerequisites

- WordPress site with H3 Tour Management plugin
- AWS account with appropriate permissions
- Composer for AWS SDK installation
- SSL/HTTPS enabled on WordPress site

## 1. AWS Infrastructure Setup

### 1.1 Create S3 Bucket

```bash
# Create bucket with appropriate region
aws s3 mb s3://your-h3-tours-bucket --region us-west-2

# Enable versioning for data protection
aws s3api put-bucket-versioning \
  --bucket your-h3-tours-bucket \
  --versioning-configuration Status=Enabled

# Enable server-side encryption
aws s3api put-bucket-encryption \
  --bucket your-h3-tours-bucket \
  --server-side-encryption-configuration '{
    "Rules": [{
      "ApplyServerSideEncryptionByDefault": {
        "SSEAlgorithm": "AES256"
      }
    }]
  }'
```

### 1.2 Configure Bucket Policy

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "RestrictToToursDirectory",
      "Effect": "Deny",
      "Principal": "*",
      "Action": "s3:*",
      "Resource": [
        "arn:aws:s3:::your-h3-tours-bucket",
        "arn:aws:s3:::your-h3-tours-bucket/*"
      ],
      "Condition": {
        "StringNotLike": {
          "s3:prefix": ["tours/*"]
        }
      }
    },
    {
      "Sid": "AllowSSLRequestsOnly",
      "Effect": "Deny",
      "Principal": "*",
      "Action": "s3:*",
      "Resource": [
        "arn:aws:s3:::your-h3-tours-bucket",
        "arn:aws:s3:::your-h3-tours-bucket/*"
      ],
      "Condition": {
        "Bool": {
          "aws:SecureTransport": "false"
        }
      }
    }
  ]
}
```

### 1.3 Create IAM User and Policy

```bash
# Create dedicated IAM user
aws iam create-user --user-name h3tm-s3-upload

# Apply the minimal permissions policy
aws iam put-user-policy \
  --user-name h3tm-s3-upload \
  --policy-name H3TMMinimalS3Access \
  --policy-document file://config/aws-iam-policy.json

# Generate access keys
aws iam create-access-key --user-name h3tm-s3-upload
```

**Important**: Save the Access Key ID and Secret Access Key securely. They will only be shown once.

## 2. WordPress/Pantheon Configuration

### 2.1 Install AWS SDK

Add to your `composer.json`:

```json
{
  "require": {
    "aws/aws-sdk-php": "^3.275"
  }
}
```

Then run:
```bash
composer install
```

### 2.2 Environment-Specific Configuration

#### Development Environment
```php
// wp-config.php or environment-specific config
define('H3TM_AWS_REGION', 'us-west-2');
define('H3TM_AWS_BUCKET', 'your-h3-tours-bucket-dev');
define('H3TM_VERIFY_SSL', false); // For local development only
```

#### Staging Environment
```php
define('H3TM_AWS_REGION', 'us-west-2');
define('H3TM_AWS_BUCKET', 'your-h3-tours-bucket-staging');
define('H3TM_VERIFY_SSL', true);
```

#### Production Environment
```php
define('H3TM_AWS_REGION', 'us-west-2');
define('H3TM_AWS_BUCKET', 'your-h3-tours-bucket-prod');
define('H3TM_VERIFY_SSL', true);
```

### 2.3 Pantheon-Specific Setup

For Pantheon hosting, use environment variables:

```bash
# Set via Pantheon dashboard or Terminus
terminus env:set mysite.live H3TM_AWS_REGION us-west-2
terminus env:set mysite.live H3TM_AWS_BUCKET your-h3-tours-bucket-prod
```

## 3. Plugin Configuration

### 3.1 Initialize the Security Classes

Add to your plugin's main file:

```php
// Load AWS security classes
require_once plugin_dir_path(__FILE__) . 'includes/class-h3tm-aws-security.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-h3tm-s3-uploader.php';

// Initialize S3 uploader
if (is_admin()) {
    new H3TM_S3_Uploader();
}
```

### 3.2 Configure Credentials in WordPress Admin

1. Navigate to **3D Tours > Settings**
2. Enter AWS credentials in the secure configuration form
3. Test the connection using the validation button
4. Credentials are automatically encrypted and stored securely

### 3.3 Update Admin Interface

Modify your admin upload forms to use S3 direct upload:

```javascript
// Replace traditional file upload with S3 direct upload
function initializeS3Upload() {
    const fileInput = document.getElementById('tour-file');
    const uploadButton = document.getElementById('upload-btn');

    uploadButton.addEventListener('click', function(e) {
        e.preventDefault();

        const file = fileInput.files[0];
        if (!file) return;

        // Request presigned URL
        fetch(ajaxurl, {
            method: 'POST',
            body: new FormData([
                ['action', 'h3tm_get_s3_upload_url'],
                ['filename', file.name],
                ['filesize', file.size],
                ['nonce', h3tm_ajax.nonce]
            ])
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                uploadToS3(file, data.data);
            }
        });
    });
}
```

## 4. Security Hardening

### 4.1 WordPress Configuration

```php
// wp-config.php - Additional security settings
define('DISALLOW_FILE_EDIT', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Hide sensitive information from logs
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}
```

### 4.2 Server-Level Security

#### Nginx Configuration
```nginx
# Block access to sensitive files
location ~* \.(json|key|pem|crt)$ {
    deny all;
    return 404;
}

# Rate limiting for upload endpoints
location /wp-admin/admin-ajax.php {
    limit_req zone=ajax burst=10 nodelay;
}
```

#### Apache Configuration
```apache
# Block sensitive files
<FilesMatch "\.(json|key|pem|crt)$">
    Require all denied
</FilesMatch>

# Rate limiting (requires mod_evasive)
<Location "/wp-admin/admin-ajax.php">
    DOSPageCount 20
    DOSPageInterval 1
</Location>
```

### 4.3 Database Security

```sql
-- Ensure proper user permissions
REVOKE ALL PRIVILEGES ON *.* FROM 'wp_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON wp_database.* TO 'wp_user'@'%';

-- Remove dangerous functions
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';
```

## 5. Monitoring and Alerting

### 5.1 CloudWatch Alarms

```json
{
  "AlarmName": "H3TM-UnauthorizedS3Access",
  "MetricName": "4xxErrors",
  "Namespace": "AWS/S3",
  "Statistic": "Sum",
  "Period": 300,
  "EvaluationPeriods": 2,
  "Threshold": 10,
  "ComparisonOperator": "GreaterThanThreshold"
}
```

### 5.2 WordPress Security Monitoring

Add to your functions.php or security plugin:

```php
// Monitor failed login attempts
add_action('wp_login_failed', function($username) {
    error_log("Failed login attempt for user: {$username} from IP: " . $_SERVER['REMOTE_ADDR']);
});

// Monitor AWS credential access
add_action('h3tm_aws_credentials_accessed', function($context) {
    if (!in_array($context, ['validation', 'presigned_url_generation'])) {
        error_log("Unusual AWS credential access: {$context}");
    }
});
```

## 6. Backup and Disaster Recovery

### 6.1 S3 Cross-Region Replication

```json
{
  "Role": "arn:aws:iam::account:role/replication-role",
  "Rules": [{
    "Status": "Enabled",
    "Priority": 1,
    "Filter": {"Prefix": "tours/"},
    "Destination": {
      "Bucket": "arn:aws:s3:::your-h3-tours-backup-bucket",
      "StorageClass": "STANDARD_IA"
    }
  }]
}
```

### 6.2 Database Backup Strategy

```bash
#!/bin/bash
# Backup WordPress database with encryption
mysqldump --single-transaction wp_database | \
gpg --cipher-algo AES256 --compress-algo 2 --symmetric | \
aws s3 cp - s3://your-backup-bucket/db-backup-$(date +%Y%m%d).sql.gpg
```

## 7. Testing and Validation

### 7.1 Security Testing Checklist

- [ ] Credentials are encrypted at rest
- [ ] No credentials exposed in browser/network
- [ ] Rate limiting works correctly
- [ ] File validation prevents malicious uploads
- [ ] S3 permissions are minimal and functional
- [ ] SSL/TLS encryption for all connections
- [ ] Error messages don't leak sensitive information
- [ ] Audit logging captures security events

### 7.2 Load Testing

```bash
# Test concurrent uploads
for i in {1..10}; do
  curl -X POST "https://yoursite.com/wp-admin/admin-ajax.php" \
    -F "action=h3tm_get_s3_upload_url" \
    -F "filename=test${i}.zip" \
    -F "filesize=1048576" \
    -F "nonce=your_nonce" &
done
wait
```

## 8. Compliance and Auditing

### 8.1 GDPR Compliance

- Ensure user consent for file uploads
- Provide data export/deletion capabilities
- Document data processing activities
- Implement data retention policies

### 8.2 SOC 2 Considerations

- Implement access logging
- Regular security assessments
- Employee access controls
- Incident response procedures

## 9. Maintenance and Updates

### 9.1 Credential Rotation

```php
// Schedule credential rotation (quarterly)
wp_schedule_event(time(), 'quarterly', 'h3tm_rotate_aws_credentials');

add_action('h3tm_rotate_aws_credentials', function() {
    // Generate new credentials in AWS
    // Update WordPress configuration
    // Validate new credentials
    // Archive old credentials securely
});
```

### 9.2 Security Updates

```bash
# Regular security update script
#!/bin/bash
composer update aws/aws-sdk-php
wp plugin update h3-tour-management
wp core update
wp theme update --all
```

## 10. Troubleshooting

### 10.1 Common Issues

**Issue**: Presigned URLs not working
**Solution**: Check IAM permissions and S3 bucket policy

**Issue**: Upload failures on large files
**Solution**: Increase PHP limits and check network timeout

**Issue**: SSL verification errors
**Solution**: Update CA certificates or disable for development only

### 10.2 Debug Mode

Enable debug logging:
```php
define('H3TM_DEBUG_AWS', true);
define('WP_DEBUG_LOG', true);
```

Check logs at `/wp-content/debug.log` for AWS-related issues.

## Support

For additional support:
- Check plugin documentation
- Review AWS CloudTrail logs
- Contact support with specific error messages and environment details