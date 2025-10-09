# Tour Status Tracking & Duplicate Prevention - Setup Guide

## Overview

This update fixes critical issues with tour processing visibility and duplicate folder names in the H3 Tour Management system.

## Issues Fixed

### ✅ Issue #1: Invisible Processing State
- **Before**: No way to tell if tour is still processing
- **After**: Real-time status tracking with 4 states (uploading → processing → completed/failed)

### ✅ Issue #2: Duplicate Folder Names
- **Before**: Inconsistent name normalization caused duplicate S3 folders
- **After**: Standardized naming (dashes in S3, spaces in display)

### ✅ Issue #3: Stale Data Display
- **Before**: 2-hour cache prevented seeing new tours
- **After**: Auto-cache clearing on status changes

### ✅ Issue #4: Fake Status Display
- **Before**: Hardcoded "Available" for all tours
- **After**: Database-backed dynamic status badges

## Database Changes

### New Table: `wp_h3tm_tour_processing`

The plugin will automatically create this table on activation. Contains:
- `tour_name` - Display name (e.g., "Bee Cave")
- `s3_folder_name` - S3 folder name (e.g., "Bee-Cave")
- `status` - Current state (uploading/processing/completed/failed)
- `files_count` - Number of files extracted
- `processing_started_at` - When Lambda started
- `processing_completed_at` - When processing finished
- `error_message` - Error details if failed

### Manual Table Creation (if needed)

If automatic creation fails, run this SQL in phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS wp_h3tm_tour_processing (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tour_name VARCHAR(255) NOT NULL,
    s3_folder_name VARCHAR(255) NOT NULL,
    status ENUM('uploading', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'uploading',
    s3_key VARCHAR(512),
    s3_bucket VARCHAR(255),
    files_count INT DEFAULT 0,
    total_size BIGINT DEFAULT 0,
    processing_started_at DATETIME,
    processing_completed_at DATETIME,
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tour (tour_name),
    KEY status_idx (status),
    KEY created_idx (created_at)
);
```

## Lambda Configuration

### Required Environment Variable

Add this to your Lambda function configuration:

**Environment Variable**: `WORDPRESS_WEBHOOK_URL`
**Value**: `https://your-site.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook`

Replace `your-site.com` with your actual WordPress site URL.

### How to Add Environment Variable

**Via AWS Console:**
1. Go to Lambda → Functions → your-function
2. Configuration → Environment variables → Edit
3. Add new variable:
   - Key: `WORDPRESS_WEBHOOK_URL`
   - Value: `https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook`
4. Save

**Via AWS CLI:**
```bash
aws lambda update-function-configuration \
  --function-name h3-tour-processor \
  --environment Variables={WORDPRESS_WEBHOOK_URL=https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook}
```

**Via Terraform (if using):**
```hcl
environment {
  variables = {
    WORDPRESS_WEBHOOK_URL = "https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook"
  }
}
```

## Deployment Steps

### 1. Update WordPress Plugin

```bash
# Deactivate plugin
wp plugin deactivate h3-tour-management

# Reactivate to trigger table creation
wp plugin activate h3-tour-management
```

### 2. Verify Database Table

Check that `wp_h3tm_tour_processing` table exists:

```bash
wp db query "DESCRIBE wp_h3tm_tour_processing"
```

### 3. Update Lambda Function

Re-deploy the Lambda function with updated `index.js`:

```bash
cd lambda
zip -r function.zip index.js delete-handler.js node_modules package.json
aws lambda update-function-code \
  --function-name h3-tour-processor \
  --zip-file fileb://function.zip
```

### 4. Configure Lambda Webhook URL

Add the environment variable as described above.

### 5. Test Upload

1. Upload a test tour via WordPress admin
2. Status should show: "⬆️ Uploading" immediately
3. After S3 upload: "⚙️ Processing" (pulsing badge)
4. After Lambda completes: "✅ Available"
5. If error occurs: "❌ Failed"

## Status Lifecycle

```
User uploads
    ↓
⬆️ Uploading (getting presigned URL, uploading to S3)
    ↓
⚙️ Processing (Lambda extracting and deploying)
    ↓
✅ Available (tour ready) OR ❌ Failed (error occurred)
```

## Troubleshooting

### Status Stuck on "Processing"
- Check Lambda CloudWatch logs for errors
- Verify `WORDPRESS_WEBHOOK_URL` is set correctly
- Check WordPress error logs for webhook failures
- Lambda may have timed out (15-minute max)

### Duplicate Folders Still Appearing
- Old tours uploaded before this fix may still have duplicates
- New uploads will use consistent naming (spaces→dashes)
- Manually archive or delete old duplicate folders via S3 console

### Status Not Updating
- Clear browser cache
- Click "Refresh Tour List" button
- Check database: `SELECT * FROM wp_h3tm_tour_processing;`
- Verify AJAX handlers registered: check PHP error log

### Webhook Not Receiving Data
- Test webhook endpoint manually:
```bash
curl -X POST https://your-site.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook \
  -H "Content-Type: application/json" \
  -d '{
    "success": true,
    "tourName": "Test Tour",
    "s3FolderName": "Test-Tour",
    "filesExtracted": 42,
    "timestamp": "2025-10-08T12:00:00Z"
  }'
```

Expected response: `{"success":true,"data":"Tour...processed successfully..."}`

## Cache Management

The system automatically clears the S3 tour cache when:
- Lambda webhook confirms tour completion
- User clicks "Refresh Tour List" button

Cache duration: 2 hours (to reduce S3 API calls)

## Monitoring

### Check Processing Status

```sql
-- Active uploads/processing
SELECT tour_name, status, processing_started_at
FROM wp_h3tm_tour_processing
WHERE status IN ('uploading', 'processing');

-- Recent completions
SELECT tour_name, status, files_count, processing_completed_at
FROM wp_h3tm_tour_processing
WHERE status = 'completed'
ORDER BY processing_completed_at DESC
LIMIT 10;

-- Failed uploads
SELECT tour_name, error_message, updated_at
FROM wp_h3tm_tour_processing
WHERE status = 'failed'
ORDER BY updated_at DESC;
```

### Cleanup Old Records

The system automatically cleans up records older than 30 days. Manual cleanup:

```sql
DELETE FROM wp_h3tm_tour_processing
WHERE status IN ('completed', 'failed')
AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

## Files Changed

### Lambda (JavaScript)
- `lambda/index.js` - Name normalization + webhook implementation

### WordPress (PHP)
- `includes/class-h3tm-tour-processing.php` - **NEW** - Database operations
- `includes/class-h3tm-lambda-webhook.php` - Status updates + cache clearing
- `includes/class-h3tm-s3-simple.php` - Name normalization + status lookup
- `h3-tour-management.php` - Load new classes

### Frontend (JavaScript/CSS)
- `assets/js/admin.js` - Dynamic status display + auto-polling
- `assets/css/admin.css` - Status badge styles

## Rollback Instructions

If issues occur, rollback by:

1. Remove new classes from `h3-tour-management.php`:
```php
// Comment out these lines:
// require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-tour-processing.php';
// require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-lambda-webhook.php';
// require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-lambda-integration.php';
```

2. Drop database table:
```sql
DROP TABLE IF EXISTS wp_h3tm_tour_processing;
```

3. Restore previous Lambda code from git:
```bash
cd lambda
git checkout HEAD~1 index.js
```

## Testing Checklist

- [ ] Upload tour with spaces in name (e.g., "Bee Cave")
- [ ] Verify S3 folder created as "Bee-Cave/"
- [ ] Verify display shows "Bee Cave"
- [ ] Status shows "⬆️ Uploading" during upload
- [ ] Status changes to "⚙️ Processing" after S3 upload
- [ ] Status changes to "✅ Available" after Lambda completes
- [ ] No duplicate folders in S3
- [ ] No duplicate entries in admin tour list
- [ ] "Refresh Tour List" button works
- [ ] Auto-polling updates status every 5 seconds
- [ ] Failed upload shows "❌ Failed" with error message
