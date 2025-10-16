# Legacy Tour Migration Guide

## Overview

This guide explains how to convert legacy tours (without `tour_id`) to the new ID-based system, enabling them to use rename and change URL features.

## What is the Migration?

### Current System

**New ID-Based Tours:**
- Have `tour_id` (format: `YYYYMMDD_HHMMSS_8random`)
- S3 files stored in: `tours/{tour_id}/`
- Metadata in `wp_h3tm_tour_metadata` table
- Can be renamed (updates `display_name` only)
- Can change URL (updates `tour_slug`, stores old slugs in `url_history`)
- 301 redirects work for old URLs

**Legacy Tours:**
- No `tour_id` in metadata (or no metadata entry at all)
- S3 files stored in: `tours/{TourName}/`
- Cannot be renamed (would require moving S3 files)
- Cannot change URL (no metadata system for redirects)
- Limited functionality in admin interface

### Migration Benefits

After migration, legacy tours will:
- ✅ Have a unique `tour_id`
- ✅ Appear in metadata table with proper tracking
- ✅ Support rename operations (display name only)
- ✅ Support URL changes with 301 redirects
- ✅ Work with all new features
- ✅ Keep S3 files in current location (no risky file moves)

## Migration Process

### How It Works

1. **Discovery**: Script scans all tours in S3
2. **Identification**: Finds tours without `tour_id` in metadata
3. **ID Generation**: Creates unique `tour_id` for each legacy tour
4. **Metadata Creation**: Adds database entry with:
   - `tour_id`: Generated unique identifier
   - `display_name`: Current tour name from S3
   - `tour_slug`: Sanitized URL-friendly version of name
   - `s3_folder`: Actual S3 location (`tours/{TourName}/`)
   - `url_history`: Empty array (ready for future slug changes)
   - `status`: active
5. **No File Operations**: S3 files stay exactly where they are

### Safety Features

- **Dry-Run Mode**: Preview what will happen before making changes
- **Non-Destructive**: No S3 file operations, only database updates
- **Idempotent**: Safe to run multiple times (skips already migrated tours)
- **Atomic**: Each tour is updated in a single database transaction
- **Rollback Safe**: Can truncate metadata table and rebuild if needed

## Usage Methods

### Method 1: Admin Interface (Recommended)

1. **Navigate**: Go to WordPress Admin → Manage Tours
2. **Check for Legacy Tours**: Look for orange "(X legacy)" indicator in header
3. **Preview First**: Click "Preview Migration" button
   - Shows what will be migrated
   - No changes are made
   - Review the details
4. **Run Migration**: Click "Migrate Legacy Tours" button
   - Confirm the action
   - Wait for completion message
   - Tours table refreshes automatically

**UI Features:**
- Migration buttons only appear when legacy tours exist
- Shows count of legacy tours in header
- Loading spinner during migration
- Detailed success/error messages
- Automatic refresh after migration

### Method 2: CLI Script

```bash
# Navigate to tools directory
cd /path/to/h3-tour-management/tools

# Dry-run (preview only)
php migrate-legacy-tours.php

# Live migration
php migrate-legacy-tours.php --live
```

**CLI Output:**
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
console.log(data.data.results);

// Live migration
formData.set('dry_run', 'false');
```

## Migration Scenarios

### Scenario 1: All Tours Already Migrated

```
Summary:
  Total tours in S3: 10
  Legacy tours found: 0
  Migrated: 0
  Skipped: 10

All tours already have tour_id!
```

**Action**: None needed, all tours are using the new system.

### Scenario 2: Mixed Tours

```
Summary:
  Total tours in S3: 15
  Legacy tours found: 5
  Migrated: 5
  Skipped: 10

Details:
  ✅ Casa Blanca Virtual Tour → Migrated (tour_id: 20251015_003851_abc12345)
  ✅ Downtown Loft 3D → Migrated (tour_id: 20251015_003852_def67890)
  ⏭️ My Tour Jeff → Already has tour_id
```

**Result**: 5 legacy tours upgraded, 10 modern tours unchanged.

### Scenario 3: First-Time Migration

```
Summary:
  Total tours in S3: 20
  Legacy tours found: 20
  Migrated: 20
  Skipped: 0

All legacy tours successfully migrated!
```

**Result**: All tours now support modern features.

## Verification

### Verify in Admin Interface

1. Go to Manage Tours
2. Check that all tours show:
   - Tour ID display (small gray text under name)
   - Rename button (pencil icon)
   - Change URL button (link icon)
3. Orange "(X legacy)" indicator should be gone

### Verify in Database

```sql
-- Check all tours have tour_id
SELECT
  id,
  tour_id,
  display_name,
  tour_slug,
  s3_folder,
  status
FROM wp_h3tm_tour_metadata
WHERE tour_id IS NULL OR tour_id = '';

-- Should return 0 rows if migration successful

-- Verify migration details
SELECT
  tour_id,
  display_name,
  tour_slug,
  s3_folder,
  LENGTH(tour_id) as id_length
FROM wp_h3tm_tour_metadata
ORDER BY created_at DESC
LIMIT 10;

-- tour_id should be 24 characters (YYYYMMDD_HHMMSS_8random)
```

### Verify S3 Files Unchanged

```bash
# S3 file locations should NOT have changed
# tours/{TourName}/ structure should still exist for migrated tours
```

**Check in AWS Console:**
- Navigate to S3 bucket → tours/ folder
- Verify all tour folders still exist with original names
- No new `tours/{tour_id}/` folders created for legacy tours

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
SELECT
  display_name,
  COUNT(*)
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

### Problem: Want to Undo Migration

**Solution 1: Rebuild Metadata (Recommended)**
```javascript
// In Admin interface, use "Rebuild Metadata" button
// This will re-scan S3 and rebuild with current logic

// Or via AJAX:
const formData = new FormData();
formData.append('action', 'h3tm_rebuild_metadata');
formData.append('nonce', window.h3tm_ajax.nonce);
```

**Solution 2: Manual Database Cleanup**
```sql
-- Delete migrated entries (CAREFUL!)
DELETE FROM wp_h3tm_tour_metadata
WHERE created_at > '2025-10-14 23:00:00'  -- Adjust timestamp
AND tour_id LIKE '%_%_%';  -- Only ID-based format

-- Then clear cache
DELETE FROM wp_options WHERE option_name = '_transient_h3tm_s3_tours_cache';
```

## Best Practices

### Before Migration

1. **Backup Database**:
   ```bash
   wp db export backup-before-migration.sql
   ```

2. **Test in Staging**: Run migration in staging environment first

3. **Review Legacy Tours**: Check which tours will be affected
   ```bash
   php migrate-legacy-tours.php  # Dry-run
   ```

4. **Verify S3 Access**: Ensure S3 connection is working
   ```bash
   # Test in admin: Settings → S3 Settings → Test Connection
   ```

### During Migration

1. **Use Preview First**: Always run dry-run before live migration
2. **Monitor Progress**: Watch for error messages
3. **Don't Interrupt**: Let migration complete fully
4. **Check Results**: Review the detailed output

### After Migration

1. **Verify All Features**: Test rename and change URL on migrated tours
2. **Clear Caches**: Flush any object/page caches
3. **Monitor Logs**: Check for any errors in production
4. **User Communication**: Inform team about new capabilities

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
- Unique: Timestamp + random = virtually no collisions
- Sortable: Date/time prefix allows chronological sorting
- Immutable: Never changes, even if tour is renamed
- URL-Safe: Only lowercase letters and numbers

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

### Code Flow

1. **Frontend**: `ToursTable.tsx` → `handleMigrateLegacyTours()`
2. **AJAX**: POST to `admin-ajax.php` with action `h3tm_migrate_legacy_tours`
3. **Handler**: `class-h3tm-admin.php` → `handle_migrate_legacy_tours()`
4. **Script**: Loads `tools/migrate-legacy-tours.php`
5. **Migration**: `migrate_legacy_tours($dry_run)`
6. **Response**: Returns results with details
7. **Frontend**: Shows message, refreshes tour list

## Files Modified

| File | Purpose | Changes |
|------|---------|---------|
| `tools/migrate-legacy-tours.php` | Migration script | Complete migration logic |
| `includes/class-h3tm-admin.php` | AJAX handler | Lines 49, 2135-2169 |
| `frontend/src/components/ToursTable.tsx` | UI | Lines 1-12, 42, 122-184, 343-399 |

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

**Status**: ✅ Migration system complete and tested
**Version**: 1.0
**Date**: 2025-10-15
**Last Updated**: 2025-10-15
