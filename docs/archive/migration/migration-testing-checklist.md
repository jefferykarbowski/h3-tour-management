# Migration Testing Checklist

## Pre-Migration Testing

### 1. Verify Lambda Setup

```bash
# Check Lambda function exists
aws lambda get-function --function-name h3tm-tour-processor

# Verify environment variables
aws lambda get-function-configuration --function-name h3tm-tour-processor \
  | jq '.Environment.Variables'

# Required variables:
# - BUCKET_NAME
# - DB_CLUSTER_ARN
# - DB_SECRET_ARN
# - DB_NAME
# - WORDPRESS_WEBHOOK_URL (optional, for notifications)
```

### 2. Verify Database Setup

```sql
-- Check table exists
SHOW TABLES LIKE 'wp_h3tm_tour_metadata';

-- Check table structure
DESCRIBE wp_h3tm_tour_metadata;

-- Required columns:
-- - tour_id (varchar, primary key)
-- - display_name (varchar)
-- - tour_slug (varchar, unique)
-- - s3_folder (varchar)
-- - url_history (json)
-- - status (varchar)
-- - created_at (datetime)
-- - updated_at (datetime)
```

### 3. Verify S3 Access

```bash
# List tours in S3
aws s3 ls s3://h3-panos/tours/

# Count tours
aws s3 ls s3://h3-panos/tours/ | wc -l
```

### 4. Check Current State

```sql
-- Count total tours in database
SELECT COUNT(*) as total_tours FROM wp_h3tm_tour_metadata;

-- Count tours with IDs
SELECT COUNT(*) as tours_with_ids
FROM wp_h3tm_tour_metadata
WHERE tour_id IS NOT NULL AND tour_id != '';

-- Count legacy tours (no IDs)
SELECT COUNT(*) as legacy_tours
FROM wp_h3tm_tour_metadata
WHERE tour_id IS NULL OR tour_id = '';

-- Sample legacy tours
SELECT display_name, tour_slug, s3_folder
FROM wp_h3tm_tour_metadata
WHERE tour_id IS NULL OR tour_id = ''
LIMIT 10;
```

## Migration Test Run

### 1. Dry Run (via Lambda Test)

```bash
# Test the migration Lambda directly
aws lambda invoke \
  --function-name h3tm-tour-processor \
  --payload '{"action":"migrate_tours"}' \
  response.json

# Check response
cat response.json | jq '.'
```

### 2. Via WordPress Admin

1. Navigate to: **H3 Tours → Tour Migration**
2. Review migration status
3. Note counts:
   - Total Tours: _____
   - Tours with IDs: _____
   - Legacy Tours: _____
4. Click **Execute Migration**
5. Review results

### 3. Verify Results

```sql
-- Check all tours now have IDs
SELECT COUNT(*) as tours_without_ids
FROM wp_h3tm_tour_metadata
WHERE tour_id IS NULL OR tour_id = '';
-- Should return 0

-- Verify tour_id format (should be UUIDs)
SELECT tour_id, display_name
FROM wp_h3tm_tour_metadata
LIMIT 10;

-- Check for duplicate tour_ids (should be none)
SELECT tour_id, COUNT(*) as count
FROM wp_h3tm_tour_metadata
GROUP BY tour_id
HAVING count > 1;

-- Check for duplicate tour_slugs (should be none)
SELECT tour_slug, COUNT(*) as count
FROM wp_h3tm_tour_metadata
GROUP BY tour_slug
HAVING count > 1;

-- Verify s3_folder matches expected format
SELECT tour_id, display_name, s3_folder
FROM wp_h3tm_tour_metadata
LIMIT 10;
```

## Post-Migration Testing

### 1. Test Tour Access

```bash
# Pick a migrated tour ID from database
TOUR_ID="your-tour-id-here"

# Test direct access via ID
curl -I "https://h3vt.com/tour/${TOUR_ID}"
# Should return 200 OK

# Test legacy slug redirect (if applicable)
curl -I "https://h3vt.com/tour/legacy-slug-here"
# Should return 301 or 302 redirect to ID-based URL
```

### 2. Test WordPress Admin

1. Go to **H3 Tours → All Tours**
2. Verify all tours show with IDs
3. Click on a few tours to verify they load correctly
4. Check tour metadata is complete

### 3. Test Upload Flow

1. Upload a new tour via WordPress
2. Verify it gets assigned a new UUID
3. Check database entry is created correctly
4. Test tour is accessible via ID URL

### 4. Test Analytics

1. Visit a migrated tour
2. Check browser console for analytics calls
3. Verify analytics data includes both:
   - `tour_id` (UUID)
   - `tour_name` (display name)

## Rollback Plan

If migration fails or causes issues:

### Option 1: Re-run Migration
- Migration is idempotent (safe to run multiple times)
- Tours with IDs will be skipped
- Only failed tours will be retried

### Option 2: Manual Cleanup
```sql
-- If needed, remove incorrectly migrated tours
DELETE FROM wp_h3tm_tour_metadata
WHERE tour_id IN ('bad-id-1', 'bad-id-2');

-- Then re-run migration
```

### Option 3: Database Backup Restore
```bash
# If you created a backup before migration
mysql -u username -p database_name < backup_before_migration.sql
```

## Success Criteria

✅ All tours have valid UUID tour_ids
✅ No duplicate tour_ids
✅ No duplicate tour_slugs
✅ s3_folder values are correct
✅ Tours accessible via ID URLs
✅ Legacy slug redirects work (if applicable)
✅ New uploads work correctly
✅ Analytics tracking works
✅ WordPress admin shows all tours correctly

## Migration Report Template

```
Migration Completed: [DATE]
Total Tours: ____
Migrated: ____
Skipped (already had IDs): ____
Errors: ____

Verification:
- Tours without IDs: ____ (should be 0)
- Duplicate IDs: ____ (should be 0)
- Duplicate Slugs: ____ (should be 0)

Sample Migrated Tours:
- Tour 1: [name] → [tour_id]
- Tour 2: [name] → [tour_id]
- Tour 3: [name] → [tour_id]

Issues Found: [None / List issues]

Next Steps: [None / Action items]
```

## Troubleshooting

### Issue: Lambda Timeout
**Solution**: Increase Lambda timeout to 300 seconds (5 minutes)
```bash
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --timeout 300
```

### Issue: Database Connection Error
**Solution**: Verify RDS Data API is enabled and credentials are correct
```bash
aws rds describe-db-clusters --db-cluster-identifier your-cluster
```

### Issue: S3 Access Denied
**Solution**: Verify Lambda IAM role has S3 permissions
```bash
aws iam get-role --role-name lambda-execution-role
```

### Issue: Migration Results Show Errors
**Solution**: Check Lambda logs for specific error messages
```bash
aws logs tail /aws/lambda/h3tm-tour-processor --follow
```
