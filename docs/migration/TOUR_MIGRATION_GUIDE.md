# Tour Migration Guide
**Complete Guide to Migrating Legacy Tours to ID-Based System**

**Last Updated**: 2025-10-16
**Plugin Version**: 2.5.0+

---

## Table of Contents

1. [Overview](#overview)
2. [What Migration Does](#what-migration-does)
3. [Prerequisites](#prerequisites)
4. [Running the Migration](#running-the-migration)
5. [Testing & Verification](#testing--verification)
6. [Troubleshooting](#troubleshooting)
7. [Rollback Procedures](#rollback-procedures)
8. [Technical Details](#technical-details)

---

## Overview

### Purpose

This migration converts legacy tours (without `tour_id`) to the new ID-based system, enabling advanced features like instant renames, URL changes with 301 redirects, and better tour management.

### Benefits After Migration

- ✅ **Instant Renames** - Database update only (< 1 second vs 30-60 seconds)
- ✅ **URL Change Support** - Update tour slugs with automatic 301 redirects
- ✅ **Multiple URLs** - Same tour accessible via different URLs
- ✅ **Immutable Storage** - S3 folders never change once created
- ✅ **Cost Savings** - No S3 copy operations ($0.05-$0.50 saved per rename)
- ✅ **Better CDN** - Consistent URLs, no cache invalidation on rename
- ✅ **Future-Proof** - Enables versioning, A/B testing, tour variants

### Current vs New System

**Legacy Tours:**
- No `tour_id` in metadata
- S3 files stored in: `tours/{TourName}/`
- Cannot be renamed without moving S3 files
- Cannot change URL without affecting file paths
- Limited functionality

**ID-Based Tours:**
- Have `tour_id` (format: `YYYYMMDD_HHMMSS_8random`)
- S3 files stored in: `tours/{tour_id}/` or legacy location (tracked in metadata)
- Can be renamed (updates `display_name` only)
- Can change URL (updates `tour_slug`, stores old slugs in `url_history`)
- 301 redirects work for old URLs
- Full feature support

---

## What Migration Does

### ✅ Database Changes ONLY

The migration script **ONLY** modifies the WordPress database:

1. **Creates metadata entries** in `wp_h3tm_tour_metadata` table:
   ```sql
   INSERT INTO wp_h3tm_tour_metadata (
     tour_id,        -- NEW: '20251015_003851_abc12345'
     display_name,   -- From S3: 'Casa Blanca Virtual Tour'
     tour_slug,      -- Generated: 'casa-blanca-virtual-tour'
     s3_folder,      -- Current location: 'tours/Casa Blanca Virtual Tour/'
     url_history,    -- Empty: []
     status,         -- 'active'
     created_at,
     updated_at
   )
   ```

2. **Clears WordPress caches**:
   - `delete_transient('h3tm_s3_tour_list')`
   - `$s3->clear_tour_cache()`

### ❌ NO AWS/S3 Changes

**Absolutely NO changes to AWS:**
- ✅ S3 files stay in current location
- ✅ S3 folder names unchanged
- ✅ No file copies, moves, or deletes
- ✅ No S3 API calls (except existing cache read)
- ✅ No CloudFront invalidations
- ✅ No S3 permissions changes
- ✅ **Zero AWS costs from migration**

### Before vs After Migration

**Before Migration:**
```
S3 Bucket Structure:
  tours/
    ├─ Casa Blanca Virtual Tour/
    │  ├─ index.htm
    │  └─ app-files/
    └─ Downtown Loft/
       ├─ index.htm
       └─ app-files/

Database:
  wp_h3tm_tour_metadata: (empty or missing tour_id)
```

**After Migration:**
```
S3 Bucket Structure:  ← UNCHANGED! ←
  tours/
    ├─ Casa Blanca Virtual Tour/  ← Same location
    │  ├─ index.htm
    │  └─ app-files/
    └─ Downtown Loft/              ← Same location
       ├─ index.htm
       └─ app-files/

Database:
  wp_h3tm_tour_metadata:
    - tour_id: 20251015_003851_abc12345
      display_name: Casa Blanca Virtual Tour
      tour_slug: casa-blanca-virtual-tour
      s3_folder: tours/Casa Blanca Virtual Tour/  ← Points to existing location

    - tour_id: 20251015_003852_def67890
      display_name: Downtown Loft
      tour_slug: downtown-loft
      s3_folder: tours/Downtown Loft/             ← Points to existing location
```

### Safety Features

- **Non-Destructive**: No S3 file operations, only database updates
- **Idempotent**: Safe to run multiple times (skips already migrated tours)
- **Atomic**: Each tour updated in single database transaction
- **Reversible**: Can rebuild metadata from scratch if needed
- **No Downtime**: Tours accessible during migration
- **Dry-Run Available**: Preview what will happen before making changes

### Cost Analysis

**Migration Cost**: $0.00
- No S3 operations (read/write/delete)
- No CloudFront invalidations
- No data transfer
- Only database operations (free)

**Ongoing Cost**: No change
- Same S3 storage costs
- Same CloudFront delivery costs
- No additional AWS services used

---

## Prerequisites

### 1. System Requirements

- WordPress 5.0+
- H3 Tour Management Plugin 2.5.0+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.2+

### 2. Pre-Migration Checklist

#### ✅ Backup Database
```bash
# Via WP-CLI
wp db export backup-before-migration.sql

# Or via MySQL
mysqldump -u username -p database_name > backup-before-migration.sql
```

#### ✅ Verify Table Exists
```sql
SHOW TABLES LIKE 'wp_h3tm_tour_metadata';
-- Should return the table name

DESCRIBE wp_h3tm_tour_metadata;
-- Should show columns: id, tour_id, display_name, tour_slug, s3_folder, url_history, status, created_at, updated_at
```

#### ✅ Check S3 Connection
```bash
# In WordPress admin: Settings → S3 Settings → Test Connection
# Should show green checkmark
```

#### ✅ Review Legacy Tours
```sql
-- Count tours without tour_id
SELECT COUNT(*) as legacy_tours
FROM wp_h3tm_tour_metadata
WHERE tour_id IS NULL OR tour_id = '';

-- List legacy tours
SELECT id, display_name, tour_slug, s3_folder
FROM wp_h3tm_tour_metadata
WHERE tour_id IS NULL OR tour_id = ''
LIMIT 10;
```

### 3. Lambda Setup (If Using Lambda Method)

```bash
# Verify Lambda function
aws lambda get-function --function-name h3tm-tour-processor

# Verify environment variables
aws lambda get-function-configuration --function-name h3tm-tour-processor \
  | jq '.Environment.Variables'

# Required variables:
# - BUCKET_NAME
# - DB_CLUSTER_ARN
# - DB_SECRET_ARN
# - DB_NAME
```

---

## Running the Migration

### Method 1: WordPress Admin Interface (Recommended)

#### Step 1: Navigate to Migration Page
1. Log in to WordPress admin
2. Go to **H3 Tours → Manage Tours**
3. Look for orange **(X legacy)** indicator in header

#### Step 2: Preview Migration (Dry-Run)
1. Click **Preview Migration** button
2. Review what will be migrated
3. Check for any warnings or errors
4. No changes are made at this stage

#### Step 3: Execute Migration
1. Click **Migrate Legacy Tours** button
2. Confirm the action in popup
3. Wait for completion message (usually 5-30 seconds)
4. Tours table refreshes automatically

#### Step 4: Verify Results
- Orange legacy indicator should disappear
- All tours should show Tour ID in table
- No error messages in admin notices

**UI Features:**
- Migration buttons only appear when legacy tours exist
- Shows count of legacy tours in header
- Loading spinner during migration
- Detailed success/error messages
- Automatic refresh after migration

### Method 2: CLI Script

#### Dry-Run (Preview Only)
```bash
# Navigate to tools directory
cd /path/to/h3-tour-management/tools

# Preview migration
php migrate-legacy-tours.php
```

**Example Output:**
```
=== Legacy Tour Migration (DRY RUN) ===

Summary:
  Total tours in S3: 15
  Legacy tours found: 8
  Migrated: 0
  Skipped: 7

Details:
  ✅ Old Tour Name
      Tour ID: 20251015_003851_abc12345
      Slug: old-tour-name
      S3 Folder: tours/Old Tour Name/
      Would be migrated (dry-run)

  ⏭️ New Tour Name
      Already has tour_id

⚠️  This was a DRY RUN - no changes were made.
Run with --live flag to perform actual migration.
```

#### Live Migration
```bash
# Execute actual migration
php migrate-legacy-tours.php --live
```

### Method 3: WordPress AJAX (Programmatic)

```javascript
// Dry-run
const formData = new FormData();
formData.append('action', 'h3tm_migrate_legacy_tours');
formData.append('dry_run', 'true');
formData.append('nonce', window.h3tm_ajax.nonce);

const response = await fetch(window.h3tm_ajax.ajax_url, {
  method: 'POST',
  body: formData
});

const data = await response.json();
console.log('Migration Preview:', data.data.results);

// Live migration
formData.set('dry_run', 'false');
const liveResponse = await fetch(window.h3tm_ajax.ajax_url, {
  method: 'POST',
  body: formData
});
```

### Method 4: Lambda Invocation

```bash
# Invoke Lambda directly
aws lambda invoke \
  --function-name h3tm-tour-processor \
  --payload '{"action":"migrate_tours"}' \
  response.json

# Check response
cat response.json | jq '.'
```

---

## Testing & Verification

### 1. Verify Migration Completion

#### Check Database
```sql
-- All tours should have tour_id (should return 0)
SELECT COUNT(*) as tours_without_ids
FROM wp_h3tm_tour_metadata
WHERE tour_id IS NULL OR tour_id = '';

-- Verify tour_id format
SELECT tour_id, display_name, tour_slug
FROM wp_h3tm_tour_metadata
ORDER BY created_at DESC
LIMIT 10;
-- tour_id should be 24 characters (YYYYMMDD_HHMMSS_8random)

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
```

#### Verify S3 Files Unchanged
```bash
# List S3 tours before migration
aws s3 ls s3://your-bucket/tours/ --recursive > before.txt

# Run migration

# List S3 tours after migration
aws s3 ls s3://your-bucket/tours/ --recursive > after.txt

# Compare (should be identical)
diff before.txt after.txt
# Expected: No differences
```

### 2. Test Tour Access

#### Direct ID Access
```bash
# Pick a migrated tour ID from database
TOUR_ID="20251015_003851_abc12345"

# Test direct access via ID
curl -I "https://your-site.com/h3panos/${TOUR_ID}/index.htm"
# Should return 200 OK
```

#### Slug Access
```bash
# Test slug-based access
curl -I "https://your-site.com/h3panos/casa-blanca-virtual-tour/"
# Should return 200 OK and load the tour
```

### 3. Verify in WordPress Admin

1. Go to **H3 Tours → All Tours**
2. Verify all tours show with Tour IDs
3. Check that rename and change URL buttons appear
4. Click on a few tours to verify they load correctly
5. Verify tour metadata is complete

### 4. Test New Features

#### Test Rename Operation
1. Select a migrated tour
2. Click rename button (pencil icon)
3. Change name to something new
4. Save
5. **Expected**:
   - Rename completes in < 1 second
   - Tour accessible via new slug
   - S3 folder name unchanged
   - No S3 copy operations in logs

#### Test Change URL
1. Select a migrated tour
2. Click change URL button (link icon)
3. Enter new slug
4. Save
5. **Expected**:
   - Old slug saved to url_history
   - Tour accessible via new URL
   - Old URL redirects (301) to new URL
   - S3 folder unchanged

---

## Troubleshooting

### Problem: Migration Button Doesn't Appear

**Possible Causes:**
- No legacy tours exist (all already migrated)
- Frontend not rebuilt after code update
- JavaScript error preventing UI render

**Solutions:**
```bash
# Rebuild frontend
cd frontend
npm run build

# Check browser console for errors
# Verify tours are loaded in React state

# Manually check for legacy tours
```

### Problem: Migration Shows 0 Legacy Tours But Tours Exist

**Possible Causes:**
- Tours not in S3 (deleted or moved)
- Metadata table already has entries
- Cache not cleared

**Solutions:**
```bash
# Clear tour cache
DELETE FROM wp_options WHERE option_name = '_transient_h3tm_s3_tours_cache';

# Check S3 connection
# Verify tours exist in S3 bucket

# Check existing metadata
SELECT COUNT(*) FROM wp_h3tm_tour_metadata WHERE tour_id IS NOT NULL;
```

### Problem: Migration Fails with Database Error

**Error Message:**
```
Failed to migrate: Duplicate entry 'tour-slug' for key 'tour_slug'
```

**Cause**: Two tours would generate same slug after sanitization

**Solution:**
```sql
-- Find duplicates
SELECT display_name, COUNT(*)
FROM wp_h3tm_tour_metadata
GROUP BY tour_slug
HAVING COUNT(*) > 1;

-- Manually rename one of the conflicting tours in S3
-- Then re-run migration
```

### Problem: Tours Migrated But Features Don't Work

**Symptoms:**
- Rename button doesn't save
- Change URL fails
- Tour ID not displaying

**Solutions:**
```bash
# Clear all caches
wp cache flush  # If using WP-CLI
DELETE FROM wp_options WHERE option_name LIKE '%h3tm%transient%';

# Rebuild frontend
cd frontend
npm run build

# Check error logs
tail -f /path/to/wp-content/debug.log
```

### Problem: Lambda Timeout

**Solution**: Increase Lambda timeout to 300 seconds (5 minutes)
```bash
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --timeout 300
```

### Problem: Database Connection Error

**Solution**: Verify RDS Data API is enabled and credentials are correct
```bash
aws rds describe-db-clusters --db-cluster-identifier your-cluster
```

### Problem: S3 Access Denied

**Solution**: Verify Lambda IAM role has S3 permissions
```bash
aws iam get-role --role-name lambda-execution-role
```

---

## Rollback Procedures

### Option 1: Re-run Migration
Migration is idempotent (safe to run multiple times):
- Tours with IDs will be skipped
- Only failed tours will be retried

### Option 2: Rebuild Metadata
```javascript
// In Admin interface, use "Rebuild Metadata" button
// This will re-scan S3 and rebuild with current logic

// Or via AJAX:
const formData = new FormData();
formData.append('action', 'h3tm_rebuild_metadata');
formData.append('nonce', window.h3tm_ajax.nonce);

fetch(window.h3tm_ajax.ajax_url, {
  method: 'POST',
  body: formData
});
```

### Option 3: Manual Database Cleanup
```sql
-- Delete migrated entries (CAREFUL!)
DELETE FROM wp_h3tm_tour_metadata
WHERE created_at > '2025-10-16 00:00:00'  -- Adjust timestamp
AND tour_id LIKE '%_%_%';  -- Only ID-based format

-- Then clear cache
DELETE FROM wp_options WHERE option_name = '_transient_h3tm_s3_tours_cache';
```

### Option 4: Database Backup Restore
```bash
# If you created a backup before migration
mysql -u username -p database_name < backup_before_migration.sql

# Or via WP-CLI
wp db import backup_before_migration.sql
```

---

## Technical Details

### Tour ID Format

Generated tour IDs follow this pattern:
```
YYYYMMDD_HHMMSS_8random
```

**Example**: `20251015_003851_mhy3v057`

**Components**:
- `YYYYMMDD`: Date (20251015)
- `HHMMSS`: Time (003851)
- `8random`: 8 random alphanumeric characters (mhy3v057)

**Properties**:
- **Unique**: Timestamp + random = 1 in 2.8 trillion collision chance
- **Sortable**: Date/time prefix allows chronological sorting
- **Immutable**: Never changes, even if tour is renamed
- **URL-Safe**: Only lowercase letters and numbers

### Database Schema

**Migrated Tour Entry:**
```sql
INSERT INTO wp_h3tm_tour_metadata (
  tour_id,          -- Generated unique ID
  display_name,     -- Original tour name from S3
  tour_slug,        -- sanitize_title(display_name)
  s3_folder,        -- 'tours/{OriginalName}/'
  url_history,      -- JSON: []
  status,           -- 'active'
  created_at,       -- Migration timestamp
  updated_at        -- Migration timestamp
) VALUES (
  '20251015_003851_mhy3v057',
  'Casa Blanca Virtual Tour',
  'casa-blanca-virtual-tour',
  'tours/Casa Blanca Virtual Tour/',
  '[]',
  'active',
  '2025-10-15 00:38:51',
  '2025-10-15 00:38:51'
);
```

### S3 Folder Tracking

The `s3_folder` column preserves the original S3 path:

**For New Tours** (created after ID system):
- `s3_folder`: `tours/{tour_id}/`
- Example: `tours/20251014_204411_mhy3v057/`

**For Migrated Legacy Tours**:
- `s3_folder`: `tours/{OriginalName}/`
- Example: `tours/Casa Blanca Virtual Tour/`

This allows the system to find files regardless of when the tour was created.

### Migration Code Flow

1. **Frontend**: `ToursTable.tsx` → `handleMigrateLegacyTours()`
2. **AJAX**: POST to `admin-ajax.php` with action `h3tm_migrate_legacy_tours`
3. **Handler**: `class-h3tm-admin.php` → `handle_migrate_legacy_tours()`
4. **Script**: Loads `tools/migrate-legacy-tours.php`
5. **Migration**: `migrate_legacy_tours($dry_run)`
6. **Response**: Returns results with details
7. **Frontend**: Shows message, refreshes tour list

### Files Involved

| File | Purpose | Key Changes |
|------|---------|-------------|
| `tools/migrate-legacy-tours.php` | Migration script | Complete migration logic |
| `includes/class-h3tm-admin.php` | AJAX handler | Lines 49, 2135-2169 |
| `frontend/src/components/ToursTable.tsx` | UI | Lines 1-12, 42, 122-184, 343-399 |
| `includes/class-h3tm-tour-metadata.php` | Metadata management | ID generation and lookup methods |
| `includes/class-h3tm-url-redirector.php` | URL resolution | Dual-mode support for ID and slug lookups |

---

## FAQs

### Will migration move my S3 files?
**No.** Migration only creates metadata entries. S3 files stay in their current locations.

### Can I undo the migration?
**Yes.** Use the "Rebuild Metadata" button or delete metadata entries. S3 files are never touched.

### What if I run migration twice?
**Safe.** Already-migrated tours are skipped. The migration is idempotent.

### Will tours be offline during migration?
**No.** Tours remain accessible. Migration only adds database entries.

### Do I need to update URLs after migration?
**No.** Tours work at their current URLs. You can optionally change them later using the new feature.

### What happens to tour names with special characters?
Tour names are sanitized to create URL-friendly slugs:
- `Casa Blanca & Loft #1` → `casa-blanca-loft-1`
- `Tour (2024)` → `tour-2024`
- Special characters are removed or converted

### Can I migrate only specific tours?
**Currently no.** Migration processes all legacy tours. Future enhancement could add selective migration.

### What if two tours have the same name?
Slugs are made unique by the database. If collision occurs, migration will fail for that tour with a clear error message. Manually rename one tour in S3 and re-run.

---

## Success Criteria

After successful migration:

✅ All tours have valid tour_id (24 characters)
✅ No duplicate tour_ids
✅ No duplicate tour_slugs
✅ S3 files unchanged (verified with diff)
✅ Tours accessible via ID URLs
✅ Tours accessible via slug URLs
✅ Rename feature works (< 1 second)
✅ Change URL feature works with 301 redirects
✅ New uploads work correctly
✅ Analytics tracking works
✅ WordPress admin shows all tours correctly

---

## Support

**Documentation**: This guide
**Issues**: Review error logs in WordPress debug.log
**Lambda Logs**: `aws logs tail /aws/lambda/h3tm-tour-processor --follow`
**Rollback**: See "Rollback Procedures" section above

---

**Status**: ✅ Migration system complete and tested
**Version**: 2.0
**Date**: 2025-10-16
**Last Updated**: 2025-10-16
