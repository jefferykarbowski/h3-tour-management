# Lambda Deployment Fix - Tour ID System

## Issue Found

The uploaded tour (ID: `20251014_204411_mhy3v057`) was stuck with status='uploading' because:

1. **Lambda webhook payload missing required field**: Lambda was sending `s3FolderName` but webhook validator expected `s3Key`
2. **Metadata status not updated**: Webhook handler wasn't updating the metadata table status from 'uploading' to 'completed'
3. **Tours list not showing ID-based tours**: List handler only returned S3 folder names, didn't query metadata for display names

## Fixes Applied

### 1. Lambda Payload Fix (`lambda/index.js`)
**Added** `s3Key` field to webhook payload:
```javascript
s3Key: `uploads/${tourId}/${tourId}.zip`,  // Add s3Key for webhook validation
```

### 2. Webhook Handler Fix (`includes/class-h3tm-lambda-webhook.php`)
**Added** metadata status update in `handle_processing_success()`:
```php
// Update metadata status from 'uploading' to 'completed' using tour_id
if ($tour_id && class_exists('H3TM_Tour_Metadata')) {
    $metadata = new H3TM_Tour_Metadata();
    $tour = $metadata->get_by_tour_id($tour_id);

    if ($tour) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_tour_metadata';

        $wpdb->update(
            $table_name,
            array('status' => 'completed'),
            array('tour_id' => $tour_id),
            array('%s'),
            array('%s')
        );
    }
}
```

### 3. Tours List Fix (`includes/class-h3tm-s3-simple.php`)
**Updated** `list_s3_tours()` to:
- Detect tour_id pattern: `\d{8}_\d{6}_[a-z0-9]{8}`
- Query metadata table for display names
- Return structured data with tour_id, name, and status
- Maintain backward compatibility with legacy name-based tours

## Deployment Steps

### 1. Deploy Lambda Function
```bash
# Navigate to lambda directory
cd lambda

# Install dependencies (if not already done)
npm install

# Create deployment package
zip -r function.zip index.js node_modules package.json

# Upload to AWS Lambda
aws lambda update-function-code \
  --function-name h3-tour-processor \
  --zip-file fileb://function.zip \
  --region us-east-1
```

### 2. Manual Metadata Fix (Current Tour)
For the current stuck tour (`20251014_204411_mhy3v057`), manually update the database:

```sql
-- Update status to 'completed'
UPDATE wp_h3tm_tour_metadata
SET status = 'completed'
WHERE tour_id = '20251014_204411_mhy3v057';

-- Clear S3 tours cache
DELETE FROM wp_options WHERE option_name = 'h3tm_s3_tours_cache';
```

### 3. Verify Tour Appears
1. Refresh the tours table in WordPress admin
2. Tour should now appear with:
   - Display Name: "My Tour Jeff"
   - Tour ID: `20251014_204411_mhy3v057`
   - Status: "completed"
   - URL: `/h3panos/20251014_204411_mhy3v057/`

## Testing Plan

1. **Upload new tour** - Verify complete end-to-end flow
2. **Check metadata status** - Should transition from 'uploading' to 'completed'
3. **Verify tour appears** - Should show in Available Tours table immediately
4. **Test backward compatibility** - Old name-based tours still work
5. **Test instant rename** - Metadata update only, no S3 operations

## Expected Behavior After Fix

### Tour Upload Flow:
1. ✅ Frontend generates presigned URL with tour_id
2. ✅ Metadata created with status='uploading'
3. ✅ Browser uploads ZIP to S3 at `uploads/{tour_id}/{tour_id}.zip`
4. ✅ Lambda processes ZIP and deploys to `tours/{tour_id}/`
5. ✅ Lambda sends webhook with `s3Key` field
6. ✅ Webhook updates metadata status to 'completed'
7. ✅ Tours list queries metadata for display name
8. ✅ Tour appears in Available Tours table

### Database State After Upload:
```
wp_h3tm_tour_metadata:
  tour_id: 20251014_204411_mhy3v057
  display_name: My Tour Jeff
  s3_folder: tours/20251014_204411_mhy3v057/
  status: completed ← Changed from 'uploading'
```

## Rollback Plan

If issues occur, revert these commits:
1. `lambda/index.js` - Remove `s3Key` field
2. `includes/class-h3tm-lambda-webhook.php` - Remove metadata update
3. `includes/class-h3tm-s3-simple.php` - Revert tours list changes

No database changes needed for rollback (backward compatible).
