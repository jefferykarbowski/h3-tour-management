# Tour Migration to ID-Based System - Summary

## What Was Created

This migration system provides a **one-time** conversion of all legacy tours to the new UUID-based system.

### Files Created

#### Lambda Function
- **`lambda/migrate-tours.js`** - Standalone migration Lambda handler
  - Lists all tours from S3
  - Generates UUIDs for legacy tours
  - Creates metadata entries in database
  - Returns detailed migration report

#### WordPress Integration
- **`includes/class-h3tm-tour-migration.php`** - WordPress admin interface
  - Admin page at **H3 Tours → Tour Migration**
  - Displays migration status (total, with IDs, legacy)
  - One-click migration execution
  - Detailed results display

#### Lambda Updates
- **`lambda/index.js`** - Updated to route migration requests
  - Added migration handler import
  - Routes `action: "migrate_tours"` to migration handler
  - Maintains existing tour processing functionality

- **`lambda/package.json`** - Added RDS Data API dependency
  - Added `@aws-sdk/client-rds-data` for database access

#### Deployment Tools
- **`lambda/deploy.sh`** - Automated deployment script
  - Installs dependencies
  - Creates deployment package
  - Deploys to AWS Lambda
  - Shows next steps

#### Documentation
- **`docs/one-time-migration-guide.md`** - Complete migration guide
  - Prerequisites and setup
  - Deployment instructions
  - Running the migration
  - Safety features and troubleshooting

- **`docs/lambda-deployment-instructions.md`** - Lambda deployment details
  - Environment variables
  - IAM permissions
  - Handler routing
  - Testing procedures

- **`docs/migration-testing-checklist.md`** - Comprehensive test plan
  - Pre-migration checks
  - Migration execution steps
  - Post-migration verification
  - Rollback procedures

- **`docs/migration-summary.md`** (this file) - Overview

### Plugin Updates
- **`h3-tour-management.php`** - Added migration class include

## How It Works

### Migration Flow

```
WordPress Admin
    ↓
    [Execute Migration Button]
    ↓
WordPress PHP (class-h3tm-tour-migration.php)
    ↓
    POST to Lambda webhook URL
    { "action": "migrate_tours" }
    ↓
Lambda (index.js)
    ↓
    Routes to migrate-tours.js
    ↓
Migration Handler (migrate-tours.js)
    ↓
    1. List S3 tours
    2. Check database for existing IDs
    3. Generate UUIDs for legacy tours
    4. Insert metadata entries
    5. Return detailed report
    ↓
WordPress displays results
```

### Database Schema

The migration creates entries with:
```
tour_id         VARCHAR(36)   PRIMARY KEY  - UUID v4
display_name    VARCHAR(255)               - Original tour name
tour_slug       VARCHAR(255)  UNIQUE       - URL-safe slug
s3_folder       VARCHAR(255)               - S3 folder name
url_history     JSON                       - Empty array
status          VARCHAR(20)                - 'active'
created_at      DATETIME                   - Current timestamp
updated_at      DATETIME                   - Current timestamp
```

## Deployment Steps

### 1. Deploy Lambda

```bash
cd lambda
chmod +x deploy.sh
./deploy.sh h3tm-tour-processor
```

Or manually:
```bash
cd lambda
npm install
zip -r function.zip index.js migrate-tours.js node_modules/
aws lambda update-function-code \
  --function-name h3tm-tour-processor \
  --zip-file fileb://function.zip
```

### 2. Configure Environment Variables

Ensure Lambda has these environment variables:

```bash
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --environment Variables="{
    BUCKET_NAME=h3-panos,
    DB_CLUSTER_ARN=arn:aws:rds:region:account:cluster:your-cluster,
    DB_SECRET_ARN=arn:aws:secretsmanager:region:account:secret:your-secret,
    DB_NAME=h3tm,
    WORDPRESS_WEBHOOK_URL=https://h3vt.com/wp-json/h3tm/v1/webhook,
    WORDPRESS_SITE=https://h3vt.com
  }"
```

### 3. Verify IAM Permissions

Lambda execution role needs:
- ✅ S3: ListBucket, GetObject
- ✅ RDS Data API: ExecuteStatement
- ✅ Secrets Manager: GetSecretValue
- ✅ CloudWatch: PutLogEvents

### 4. Run Migration

#### Via WordPress Admin (Recommended)
1. Navigate to **H3 Tours → Tour Migration**
2. Review status
3. Click **Execute Migration**
4. Review detailed results

#### Via AWS CLI (Alternative)
```bash
aws lambda invoke \
  --function-name h3tm-tour-processor \
  --payload '{"action":"migrate_tours"}' \
  response.json

cat response.json | jq '.'
```

## Safety Features

### Idempotent Design
- ✅ Can run multiple times safely
- ✅ Tours with IDs are automatically skipped
- ✅ Only legacy tours are migrated

### Validation
- ✅ Checks for existing tour_id before inserting
- ✅ Verifies S3 tours exist before processing
- ✅ Database constraints prevent duplicates

### Detailed Logging
- ✅ CloudWatch logs every operation
- ✅ WordPress error logs capture issues
- ✅ Migration report shows every tour's status

### Error Handling
- ✅ Continues processing on individual failures
- ✅ Reports all errors in results
- ✅ Database transactions prevent partial updates

## Expected Results

### Successful Migration Output

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
    }
  ]
}
```

### WordPress Admin Display

The migration page shows:
- 📊 **Migration Status** table with counts
- ✅ **Success** or ❌ **Error** notices
- 📋 **Detailed Results** table for each tour
- 🚀 **Execute Migration** button (if needed)

## Post-Migration

### What Changes
1. ✅ All tours have unique UUID `tour_id`
2. ✅ URL system uses `/tour/{tour-id}` format
3. ✅ Legacy `/tour/{slug}` redirects work automatically
4. ✅ No more manual slug management needed

### What Stays the Same
1. ✅ All tour files remain in S3
2. ✅ S3 folder structure unchanged
3. ✅ Analytics continue working
4. ✅ Existing tour links still work (via redirects)

### Cleanup (Optional)

After successful migration and verification:

1. **Keep Migration Page** (recommended for audit)
   - Or comment out menu registration if desired

2. **Archive Legacy Scripts**
   - `tools/migrate-legacy-tours.php` no longer needed
   - Keep for reference or remove

3. **Monitor Logs**
   - Check CloudWatch logs for any issues
   - Verify all tours accessible

## Verification Queries

### Check Migration Success
```sql
-- Should return 0
SELECT COUNT(*) FROM wp_h3tm_tour_metadata
WHERE tour_id IS NULL OR tour_id = '';

-- Should match total tours
SELECT COUNT(*) FROM wp_h3tm_tour_metadata
WHERE tour_id IS NOT NULL;
```

### Verify Data Quality
```sql
-- No duplicate IDs (should be empty)
SELECT tour_id, COUNT(*)
FROM wp_h3tm_tour_metadata
GROUP BY tour_id
HAVING COUNT(*) > 1;

-- No duplicate slugs (should be empty)
SELECT tour_slug, COUNT(*)
FROM wp_h3tm_tour_metadata
GROUP BY tour_slug
HAVING COUNT(*) > 1;
```

### Sample Data Check
```sql
-- View sample of migrated tours
SELECT tour_id, display_name, tour_slug, s3_folder
FROM wp_h3tm_tour_metadata
ORDER BY created_at DESC
LIMIT 10;
```

## Troubleshooting

### Migration Fails
1. Check Lambda logs: `aws logs tail /aws/lambda/h3tm-tour-processor --follow`
2. Verify environment variables are set
3. Check IAM permissions
4. Verify RDS Data API is enabled

### Database Errors
1. Check table exists: `SHOW TABLES LIKE 'wp_h3tm_tour_metadata'`
2. Verify table structure matches schema
3. Check database credentials in Secrets Manager

### No Tours Found
1. Verify S3 bucket name in Lambda env
2. Check S3 folder structure (should be top-level folders)
3. Confirm Lambda has S3 read permissions

## Support

For issues or questions:
1. Review migration logs in CloudWatch
2. Check migration report for specific errors
3. Consult troubleshooting section in docs
4. Contact development team with:
   - Migration report JSON
   - Lambda logs excerpt
   - Database query results

## Important Notes

⚠️ **This is a ONE-TIME operation**

Once successfully completed:
- ✅ All tours will be in the new ID-based system
- ✅ No need to run again (unless adding new legacy tours)
- ✅ Future uploads automatically use ID system

💡 **Migration is SAFE**

The migration:
- ✅ Does NOT modify existing tour files
- ✅ Does NOT change S3 structure
- ✅ Only ADDS tour_id to metadata table
- ✅ Can be run multiple times if needed

🎯 **After Migration**

You can:
- ✅ Remove legacy migration scripts
- ✅ Simplify tour management code
- ✅ Focus on new feature development
- ✅ Never worry about slug conflicts again
