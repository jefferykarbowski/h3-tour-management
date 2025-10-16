# One-Time Tour Migration Guide

## Overview

This document describes the one-time migration process to convert all legacy tours to the new ID-based system.

## What This Migration Does

1. **Scans S3**: Lists all tour folders in your S3 bucket
2. **Checks Database**: Identifies tours without `tour_id` in the metadata table
3. **Generates IDs**: Creates unique UUIDs for legacy tours
4. **Updates Database**: Inserts metadata entries with:
   - `tour_id` (UUID)
   - `display_name` (from S3 folder name)
   - `tour_slug` (sanitized URL-safe version)
   - `s3_folder` (original S3 folder name)
   - `url_history` (empty array)
   - `status` ('active')

## Prerequisites

### 1. Deploy Lambda Function

```bash
cd lambda
zip -r migrate-tours.zip migrate-tours.js node_modules/
aws lambda create-function \
  --function-name h3tm-migrate-tours \
  --runtime nodejs18.x \
  --handler migrate-tours.handler \
  --zip-file fileb://migrate-tours.zip \
  --role arn:aws:iam::YOUR_ACCOUNT:role/lambda-execution-role \
  --environment Variables="{
    BUCKET_NAME=your-bucket-name,
    DB_CLUSTER_ARN=your-db-arn,
    DB_SECRET_ARN=your-secret-arn,
    DB_NAME=h3tm
  }" \
  --timeout 300
```

### 2. Update Lambda Webhook Handler

The Lambda webhook handler needs to route migration requests to the new function.

### 3. Configure WordPress

Ensure the Lambda webhook URL is configured in WordPress settings:
- Go to: **H3 Tours → Settings**
- Set: Lambda Webhook URL

## Running the Migration

### Via WordPress Admin

1. Navigate to **H3 Tours → Tour Migration**
2. Review the migration status:
   - Total Tours
   - Tours with IDs
   - Legacy Tours (need migration)
3. Click **"🚀 Execute Migration"**
4. Review the detailed results

### Via PHP Script (Alternative)

```php
<?php
require_once 'wp-load.php';
require_once 'wp-content/plugins/h3-tour-management/includes/class-h3tm-tour-migration.php';

$result = H3TM_Tour_Migration::execute_migration();

if (is_wp_error($result)) {
    echo "Error: " . $result->get_error_message() . "\n";
} else {
    echo "Migration Results:\n";
    echo "Total Tours: " . $result['total_tours'] . "\n";
    echo "Migrated: " . $result['migrated'] . "\n";
    echo "Skipped: " . $result['skipped'] . "\n";
    echo "Errors: " . $result['errors'] . "\n";
}
?>
```

## Migration Results

The migration will return detailed results:

```json
{
  "success": true,
  "timestamp": "2025-10-15T12:00:00Z",
  "total_tours": 50,
  "migrated": 45,
  "skipped": 5,
  "errors": 0,
  "details": [
    {
      "s3_folder": "Tour Name",
      "action": "migrated",
      "tour_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "tour_slug": "tour-name",
      "reason": "Successfully migrated to ID-based system"
    },
    {
      "s3_folder": "Another Tour",
      "action": "skipped",
      "tour_id": "existing-id",
      "reason": "Already has tour_id"
    }
  ]
}
```

## Safety Features

1. **Idempotent**: Can be run multiple times safely
2. **Skip Existing**: Tours with IDs are automatically skipped
3. **Transaction Safety**: Uses database transactions
4. **Detailed Logging**: Full audit trail in Lambda logs
5. **Rollback Capability**: Database constraints prevent duplicates

## Post-Migration

After successful migration:

1. ✅ All tours will have unique IDs
2. ✅ URL system will use `/tour/{tour-id}` format
3. ✅ Legacy `/tour/{slug}` URLs will redirect automatically
4. ✅ No more manual slug management needed

## Troubleshooting

### Migration Fails

**Check Lambda Logs**:
```bash
aws logs tail /aws/lambda/h3tm-migrate-tours --follow
```

**Common Issues**:
- Lambda timeout: Increase timeout to 300 seconds
- Database permissions: Ensure RDS Data API access
- S3 permissions: Verify ListObjects permission

### Tours Not Found

- Verify S3 bucket name in Lambda environment
- Check S3 folder structure (should be top-level folders)
- Confirm Lambda has S3 read permissions

### Database Errors

- Verify RDS Data API is enabled
- Check database credentials in Secrets Manager
- Ensure table `wp_h3tm_tour_metadata` exists

## Cleanup (After Successful Migration)

Once migration is complete and verified:

1. **Remove Migration Page** (optional):
   - Comment out the menu registration in `class-h3tm-tour-migration.php`

2. **Archive Migration Lambda** (optional):
   - Keep for audit trail, but won't be needed again

3. **Remove Legacy Migration Scripts** (optional):
   - `tools/migrate-legacy-tours.php` can be archived

## Verification

After migration, verify:

```sql
-- All tours should have IDs
SELECT COUNT(*) FROM wp_h3tm_tour_metadata WHERE tour_id IS NULL;
-- Should return 0

-- Check tour distribution
SELECT
    COUNT(*) as total_tours,
    COUNT(tour_id) as tours_with_ids,
    COUNT(*) - COUNT(tour_id) as legacy_tours
FROM wp_h3tm_tour_metadata;
```

## Support

If you encounter issues:
1. Check Lambda CloudWatch logs
2. Review database table structure
3. Verify S3 bucket contents
4. Contact support with migration results JSON

---

**Remember**: This is a one-time operation. After successful migration, all tours will use the new ID-based system permanently.
