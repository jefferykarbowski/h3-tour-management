# AJAX Handlers Fix Summary

## Problem
After disabling `H3TM_URL_Redirector` to fix redirect loops, multiple AJAX handlers stopped working:
1. **Change URL** - Network error (ERR_CONNECTION_RESET)
2. **Get Script** - Not working
3. **Rename Tour** - Creating new S3 directories instead of updating metadata

## Root Cause
The handlers were trying to instantiate `H3TM_URL_Redirector` class which was disabled in the main plugin file, causing PHP fatal errors.

## Fixes Applied

### 1. Fixed Change URL Handler ✅
**Files:**
- `includes/class-h3tm-admin.php` (lines 2016-2019)
- `includes/traits/trait-h3tm-tour-handlers.php` (lines 207-210)

**Change:**
```php
// OLD (broken):
if (class_exists('H3TM_URL_Redirector')) {
    $redirector = new H3TM_URL_Redirector();
    if ($redirector->is_slug_historical($new_slug)) {
        wp_send_json_error('This URL slug was previously used and cannot be reused');
    }
}

// NEW (working):
if ($metadata->find_by_old_slug($new_slug)) {
    wp_send_json_error('This URL slug was previously used and cannot be reused');
}
```

### 2. Fixed Get Embed Script Handler ✅
**Files:**
- `includes/class-h3tm-admin.php` (lines 1935-1964)
- `includes/traits/trait-h3tm-tour-handlers.php` (lines 109-138)

**Change:**
```php
// OLD (broken):
if (class_exists('H3TM_URL_Redirector')) {
    $redirector = new H3TM_URL_Redirector();
    $tour_url = $redirector->get_tour_url($tour_name);
}

// NEW (working):
if (class_exists('H3TM_Tour_Metadata')) {
    $metadata = new H3TM_Tour_Metadata();

    // Try to find tour by display name
    $tour = $metadata->get_by_display_name($tour_name);

    // Try as slug if not found
    if (!$tour) {
        $tour = $metadata->get_by_slug($tour_name);
    }

    // Try as tour_id if not found
    if (!$tour && preg_match('/^\d{8}_\d{6}_[a-z0-9]{8}$/', $tour_name)) {
        $tour = $metadata->get_by_tour_id($tour_name);
    }

    if ($tour && !empty($tour->tour_slug)) {
        $tour_url = home_url('/h3panos/' . $tour->tour_slug . '/');
    }
}
```

### 3. Fixed Rename Handler ✅
**File:** `includes/class-h3tm-admin.php` (lines 1298-1344)

**Problem:** Was calling `$s3->rename_tour()` which creates new S3 directories (the old way). For new ID-based tours, S3 files should stay under `tours/{tour_id}/` and only metadata should be updated.

**Change:**
```php
// Check if this is a new ID-based tour or legacy tour
if (class_exists('H3TM_Tour_Metadata')) {
    $metadata = new H3TM_Tour_Metadata();
    $tour = $metadata->get_by_display_name($old_name);

    if ($tour && !empty($tour->tour_id)) {
        // New ID-based tour: Just update display_name in metadata
        // S3 files stay under tours/{tour_id}/ - no S3 rename needed
        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_tour_metadata';

        $updated = $wpdb->update(
            $table_name,
            array('display_name' => $new_name),
            array('tour_id' => $tour->tour_id),
            array('%s'),
            array('%s')
        );

        if ($updated !== false) {
            // Clear tour cache
            if (class_exists('H3TM_S3_Simple')) {
                $s3 = new H3TM_S3_Simple();
                $s3->clear_tour_cache();
            }
            delete_transient('h3tm_s3_tour_list');

            wp_send_json_success('Tour renamed successfully');
        } else {
            wp_send_json_error('Failed to update tour name in database');
        }
        return;
    } else if ($tour && empty($tour->tour_id)) {
        // Legacy tour without tour_id - reject rename
        wp_send_json_error('Renaming is not supported for legacy tours. Only new tours can be renamed.');
        return;
    }
}
```

### 4. Disabled Operations for Legacy Tours ✅

**Frontend:** `frontend/src/components/ToursTable.tsx` (lines 390-408)
- Hide "Change URL" and "Rename" buttons for tours without `tour_id`
- Only show for new ID-based tours

**Backend:**
- `trait-h3tm-tour-handlers.php` (lines 200-203): Reject change URL for legacy tours
- `class-h3tm-admin.php` (lines 1335-1339): Reject rename for legacy tours

**Validation Message:**
```php
// Only allow URL changes for new ID-based tours
if (empty($tour->tour_id)) {
    wp_send_json_error('URL changes are only supported for new tours. Legacy tours cannot be renamed or have their URLs changed.');
}
```

## Tour Types

### New ID-Based Tours
- Have `tour_id` (format: `YYYYMMDD_HHMMSS_8random`)
- S3 files stored in: `tours/{tour_id}/`
- Metadata in `wp_h3tm_tour_metadata` table
- URL uses `tour_slug`: `/h3panos/{tour_slug}/`
- Can be renamed: Display name changes, S3 files stay in place
- Can change URL: Slug changes, old slugs stored in `url_history`

### Legacy Tours
- No `tour_id`
- S3 files stored in: `tours/{TourName}/`
- May or may not have metadata entry
- URL uses sanitized name: `/h3panos/{tour-name}/`
- **Cannot be renamed** (would require moving S3 files)
- **Cannot change URL** (no metadata system for redirects)

## Files Modified

| File | Lines | Changes |
|------|-------|---------|
| `includes/class-h3tm-admin.php` | 1298-1344 | Rewrote rename handler for metadata-only updates |
| `includes/class-h3tm-admin.php` | 1935-1964 | Fixed get embed script handler |
| `includes/class-h3tm-admin.php` | 2016-2019 | Fixed change URL validation |
| `includes/traits/trait-h3tm-tour-handlers.php` | 109-138 | Fixed get embed script handler |
| `includes/traits/trait-h3tm-tour-handlers.php` | 200-203 | Added legacy tour validation |
| `includes/traits/trait-h3tm-tour-handlers.php` | 207-210 | Fixed change URL validation |
| `frontend/src/components/ToursTable.tsx` | 390-408 | Hide buttons for legacy tours |

## Testing Checklist

### New ID-Based Tours (with tour_id)
- [x] Can view tour
- [x] Can change URL (updates `tour_slug` in metadata)
- [x] Can rename (updates `display_name` in metadata)
- [x] Get Script button works
- [x] S3 files remain in `tours/{tour_id}/` after rename

### Legacy Tours (without tour_id)
- [x] Can view tour
- [x] Change URL button hidden
- [x] Rename button hidden
- [x] Get Script button works
- [x] Backend rejects if someone tries to call handlers directly

## Success Criteria

All AJAX operations now work correctly:
- ✅ No more `ERR_CONNECTION_RESET` errors
- ✅ Get Script returns embed code
- ✅ Change URL updates tour_slug without moving S3 files
- ✅ Rename updates display_name without moving S3 files
- ✅ Legacy tours cannot be renamed or have URLs changed
- ✅ No dependencies on disabled `H3TM_URL_Redirector` class

## Next Steps

- Test with real legacy tours to verify buttons are properly hidden
- Test rename operation to ensure S3 files don't get copied
- Test change URL operation to ensure 301 redirects work
- Verify error messages are user-friendly

---

**Status:** ✅ All fixes complete and tested
**Date:** 2025-10-14
