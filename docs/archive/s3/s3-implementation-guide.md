# S3 Integration Implementation Guide

This guide provides step-by-step instructions for implementing the AWS S3 integration in the H3 Tour Management plugin.

## Prerequisites

1. **AWS Account** with S3 access
2. **AWS CLI** installed (optional, for testing)
3. **Composer** for PHP dependencies
4. **WordPress** admin access

## Step 1: Install AWS SDK

Add the AWS SDK for PHP to your project:

```bash
cd /path/to/h3-tour-management
composer require aws/aws-sdk-php
```

Update the main plugin file to include the autoloader:

```php
// In h3-tour-management.php, after other includes:
if (file_exists(H3TM_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once H3TM_PLUGIN_DIR . 'vendor/autoload.php';
}
```

## Step 2: AWS S3 Setup

### Create S3 Bucket

1. **Log in to AWS Console** → S3
2. **Create bucket** with these settings:
   - **Name**: `h3-tour-uploads` (or your preferred name)
   - **Region**: Choose closest to your users
   - **Block Public Access**: Disable (we need presigned URLs)
   - **Versioning**: Enable (recommended)
   - **Server-side encryption**: Enable with S3 managed keys

### Configure CORS Policy

Add this CORS configuration to your bucket:

```json
[
    {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
        "AllowedOrigins": ["https://yourdomain.com"],
        "ExposeHeaders": ["ETag"],
        "MaxAgeSeconds": 3600
    }
]
```

### Set Lifecycle Policy

Configure automatic cleanup of temporary files:

```json
{
    "Rules": [
        {
            "ID": "TempFileCleanup",
            "Status": "Enabled",
            "Filter": {
                "Prefix": "temp/"
            },
            "Expiration": {
                "Days": 1
            }
        },
        {
            "ID": "ProcessedFileArchive",
            "Status": "Enabled",
            "Filter": {
                "Prefix": "processed/"
            },
            "Transitions": [
                {
                    "Days": 30,
                    "StorageClass": "STANDARD_IA"
                },
                {
                    "Days": 90,
                    "StorageClass": "GLACIER"
                }
            ]
        }
    ]
}
```

## Step 3: IAM Configuration

### Create IAM Policy

Create a new policy with minimal required permissions:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "S3BucketAccess",
            "Effect": "Allow",
            "Action": [
                "s3:ListBucket"
            ],
            "Resource": "arn:aws:s3:::h3-tour-uploads"
        },
        {
            "Sid": "S3ObjectAccess",
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:PutObjectAcl",
                "s3:GetObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::h3-tour-uploads/*"
        }
    ]
}
```

### Create IAM User

1. **Create new user**: `h3-tour-uploads-user`
2. **Access type**: Programmatic access
3. **Attach policy**: The policy created above
4. **Save credentials**: Access Key ID and Secret Access Key

## Step 4: WordPress Integration

### Include New Classes

Update your main plugin file to include the S3 classes:

```php
// In h3-tour-management.php, after existing includes:
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-s3-integration.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-s3-processor.php';
require_once H3TM_PLUGIN_DIR . 'admin/s3-settings.php';

// In the h3tm_init function:
function h3tm_init() {
    // Existing initializations...

    // Initialize S3 integration if enabled
    if (get_option('h3tm_s3_enabled', false)) {
        new H3TM_S3_Integration();
        new H3TM_S3_Processor();
    }

    // Always initialize settings page
    if (is_admin()) {
        new H3TM_S3_Settings();
    }
}
```

### Update Admin Scripts

Modify the admin script enqueue function:

```php
// In class-h3tm-admin.php, enqueue_admin_scripts method:
public function enqueue_admin_scripts($hook) {
    if (strpos($hook, 'h3-tour-management') === false && strpos($hook, 'h3tm') === false) {
        return;
    }

    // Existing scripts...

    // Enqueue S3 uploader if enabled
    if (get_option('h3tm_s3_enabled', false)) {
        wp_enqueue_script(
            'h3tm-s3-uploader',
            H3TM_PLUGIN_URL . 'assets/js/s3-uploader.js',
            array('jquery', 'h3tm-admin'),
            H3TM_VERSION,
            true
        );
    }
}
```

### Modify Upload Form

Update the upload form HTML to include method selection:

```php
// In class-h3tm-admin.php, render_main_page method:
<div class="h3tm-upload-method">
    <h4><?php _e('Upload Method', 'h3-tour-management'); ?></h4>
    <label>
        <input type="radio" name="upload_method" value="auto" checked>
        <?php _e('Automatic (S3 for large files, chunked for small files)', 'h3-tour-management'); ?>
    </label>
    <?php if (get_option('h3tm_s3_enabled', false)): ?>
    <label>
        <input type="radio" name="upload_method" value="s3">
        <?php _e('Always use S3 Direct Upload', 'h3-tour-management'); ?>
    </label>
    <?php endif; ?>
    <label>
        <input type="radio" name="upload_method" value="chunked">
        <?php _e('Always use Chunked Upload', 'h3-tour-management'); ?>
    </label>
</div>
```

## Step 5: Enhanced Tour Manager

Create a new tour manager class that supports S3:

```php
// Create includes/class-h3tm-tour-manager-s3.php
class H3TM_Tour_Manager_S3 extends H3TM_Tour_Manager {

    private $s3_integration;

    public function __construct() {
        parent::__construct();
        $this->s3_integration = new H3TM_S3_Integration();
    }

    public function upload_tour($tour_name, $file, $upload_source = 'local') {
        switch ($upload_source) {
            case 's3':
                return $this->process_s3_upload($tour_name, $file);
            case 'local':
            default:
                return parent::upload_tour($tour_name, $file);
        }
    }

    private function process_s3_upload($tour_name, $s3_file_info) {
        // Download from S3, process, then clean up
        // Implementation handled by H3TM_S3_Processor
        $processor = new H3TM_S3_Processor();
        return $processor->process_s3_upload($s3_file_info['upload_id']);
    }
}
```

Update the admin class to use the S3-aware tour manager:

```php
// In class-h3tm-admin.php, modify get_tour_manager method:
private function get_tour_manager() {
    if (get_option('h3tm_s3_enabled', false) && class_exists('H3TM_Tour_Manager_S3')) {
        return new H3TM_Tour_Manager_S3();
    }

    if ($this->use_optimized && class_exists('H3TM_Tour_Manager_Optimized')) {
        return new H3TM_Tour_Manager_Optimized();
    }

    return new H3TM_Tour_Manager();
}
```

## Step 6: WordPress Cron Setup

Add cron hooks for processing and cleanup:

```php
// In h3-tour-management.php or in the activator class:
register_activation_hook(__FILE__, function() {
    // Schedule cleanup cron
    if (!wp_next_scheduled('h3tm_s3_cleanup')) {
        wp_schedule_event(time(), 'daily', 'h3tm_s3_cleanup');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('h3tm_s3_cleanup');
});

// Add cron action
add_action('h3tm_s3_cleanup', function() {
    if (class_exists('H3TM_S3_Processor')) {
        $processor = new H3TM_S3_Processor();
        $processor->cleanup_old_processing_data();
    }
});
```

## Step 7: Configuration

1. **Go to WordPress Admin** → 3D Tours → S3 Settings
2. **Enable S3 Integration**: Check the box
3. **Enter S3 Bucket Name**: `h3-tour-uploads`
4. **Select AWS Region**: Choose your bucket's region
5. **Enter AWS Credentials**: Access Key ID and Secret Key
6. **Set Upload Threshold**: Default 50MB (files larger will use S3)
7. **Save Settings**
8. **Test Connection**: Click "Test S3 Connection" button

## Step 8: Testing

### Test Small Files (Chunked Upload)
1. Upload a file smaller than your threshold
2. Should use existing chunked upload method

### Test Large Files (S3 Upload)
1. Upload a file larger than your threshold
2. Should use S3 direct upload
3. Monitor progress in browser developer tools
4. Check WordPress admin for processing status

### Test Fallback
1. Temporarily disable S3 (uncheck enable box)
2. Upload large file - should fall back to chunked upload
3. Re-enable S3 and test again

## Step 9: Monitoring and Maintenance

### CloudWatch Metrics

Set up basic CloudWatch monitoring:

1. **S3 Bucket Metrics**: Enable in S3 console
2. **Request Metrics**: Monitor PUT/GET operations
3. **Storage Metrics**: Track storage usage
4. **Error Metrics**: Monitor 4xx/5xx errors

### WordPress Monitoring

Check the S3 Settings page regularly for:
- Upload success/failure rates
- Processing statistics
- Failed uploads requiring retry

### Maintenance Tasks

Schedule these regular maintenance tasks:

1. **Weekly**: Review upload statistics
2. **Monthly**: Clean up old processed files
3. **Quarterly**: Review S3 costs and optimize
4. **Annually**: Rotate AWS access keys

## Troubleshooting

### Common Issues

**"S3 connection failed"**
- Check AWS credentials
- Verify IAM permissions
- Check bucket name and region

**"Upload failed with status 403"**
- Review CORS policy
- Check IAM permissions
- Verify bucket public access settings

**"Processing timeout"**
- Increase PHP max_execution_time
- Check available disk space
- Review memory_limit setting

**"File not found in S3"**
- Check S3 lifecycle policies
- Verify upload completion
- Review CloudWatch logs

### Debug Mode

Enable debug logging by adding to wp-config.php:

```php
define('H3TM_S3_DEBUG', true);
```

This will log detailed S3 operations to the WordPress error log.

### Performance Optimization

1. **Use CloudFront**: CDN for faster downloads
2. **Optimize Chunk Size**: Test different sizes for your network
3. **Parallel Processing**: Consider AWS Lambda for processing
4. **Regional Optimization**: Choose S3 region close to users

## Security Best Practices

1. **Rotate Keys Regularly**: Change AWS access keys quarterly
2. **Use IAM Roles**: Consider EC2 IAM roles if hosting on AWS
3. **Monitor Access**: Enable CloudTrail for S3 access logging
4. **Encrypt Data**: Enable S3 server-side encryption
5. **Network Security**: Use VPC endpoints for private access
6. **Audit Permissions**: Regularly review IAM policies

## Cost Optimization

1. **Lifecycle Policies**: Automatically transition old files to cheaper storage
2. **Monitor Usage**: Set up billing alerts
3. **Delete Temp Files**: Ensure cleanup processes work correctly
4. **Optimize Transfers**: Consider S3 Transfer Acceleration for global users
5. **Request Optimization**: Batch operations where possible

This implementation provides a robust, scalable solution for handling large file uploads while maintaining backward compatibility with existing functionality.