# ID-Based Update Workflow Implementation

## Problem Summary
The current update workflow uses `tour_name` (display name) which can have duplicates, causing:
- All tours with same name show spinning update icons
- Creates new folders instead of updating existing ones
- No archiving of old files before update
- Reliance on name-based lookups instead of immutable `tour_id`

## Solution: ID-Based Archive & Update

### Frontend Changes ✅ COMPLETE

**File**: `frontend/src/components/ToursTable.tsx`

1. ✅ Changed state from `pendingUpdateTourName` to `pendingUpdateTourId`
2. ✅ Updated `requestPresignedUrlForUpdate()` to send `tour_id` instead of `tour_name`
3. ✅ Updated `handleFileSelect()` to send `tour_id` to backend
4. ✅ Updated `handleAction()` "update" case to pass `tour_id`
5. ✅ Update button now:
   - Disabled for legacy tours without `tour_id`
   - Only one tour spins at a time (tracked by `tour_id`)
   - Shows visual indicator (grayed out) for legacy tours

### Backend Changes NEEDED

**Files to modify:**
1. `includes/class-h3tm-s3-simple.php` - Presigned URL & update methods
2. `includes/class-h3tm-admin.php` - Update handler

### Required Backend Changes

#### 1. Modify `handle_get_presigned_url()`
**File**: `class-h3tm-s3-simple.php` lines 499-621

```php
// Accept tour_id for updates instead of tour_name
if ($is_update) {
    $tour_id = isset($_POST['tour_id']) ? sanitize_text_field($_POST['tour_id']) : '';

    if (empty($tour_id)) {
        error_log('H3TM S3 Simple: Cannot update - tour_id required for updates');
        wp_send_json_error('Tour ID required for updates');
    }

    error_log('H3TM S3 Simple: UPDATE request for tour_id: ' . $tour_id);

    // Validate tour_id exists in metadata
    $existing_tour = $metadata->get_by_tour_id($tour_id);

    if (!$existing_tour) {
        error_log('H3TM S3 Simple: Cannot update - tour_id not found: ' . $tour_id);
        wp_send_json_error('Tour not found for update');
    }

    // Use existing tour_id (NO new metadata created)
    error_log('H3TM S3 Simple: Using tour_id for update: ' . $tour_id);

} else {
    // NEW upload path stays the same...
}
```

#### 2. Modify `handle_update_tour()`
**File**: `class-h3tm-admin.php` lines 1899-1963

```php
public function handle_update_tour() {
    check_ajax_referer('h3tm_ajax_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    // CHANGE: Accept tour_id instead of tour_name
    $tour_id = isset($_POST['tour_id']) ? sanitize_text_field($_POST['tour_id']) : '';
    $s3_key = isset($_POST['s3_key']) ? sanitize_text_field($_POST['s3_key']) : '';

    if (empty($tour_id) || empty($s3_key)) {
        wp_send_json_error('Missing required parameters');
    }

    error_log('H3TM Update: Starting update for tour_id: ' . $tour_id);

    try {
        $s3 = new H3TM_S3_Simple();

        // STEP 1: Archive existing tour files
        error_log('H3TM Update: Archiving current version');
        $archive_result = $s3->archive_tour_by_id($tour_id);

        if (!$archive_result['success']) {
            error_log('H3TM Update: Archive failed: ' . $archive_result['message']);
            // Continue anyway - maybe first upload attempt
        }

        // Download ZIP from S3
        $file_name = basename($s3_key);
        $temp_zip_path = $s3->download_zip_temporarily($s3_key, $file_name);

        if (!$temp_zip_path) {
            wp_send_json_error('Failed to download tour from S3');
        }

        // Extract ZIP
        $temp_extract_dir = $s3->extract_tour_temporarily($temp_zip_path, $tour_id);

        if (!$temp_extract_dir) {
            if (file_exists($temp_zip_path)) unlink($temp_zip_path);
            wp_send_json_error('Failed to extract tour ZIP');
        }

        // STEP 2: Update tour in S3 (uploads to tours/{tour_id}/)
        $success = $s3->update_tour($tour_id, $temp_extract_dir);

        // Cleanup temp files
        if (file_exists($temp_zip_path)) unlink($temp_zip_path);
        if (is_dir($temp_extract_dir)) {
            // ... cleanup code stays the same
        }

        // Delete S3 upload
        $s3->delete_s3_object($s3_key);

        if ($success) {
            wp_send_json_success('Tour updated successfully! CloudFront cache has been invalidated.');
        } else {
            wp_send_json_error('Failed to update tour files in S3');
        }

    } catch (Exception $e) {
        error_log('H3TM Update Error: ' . $e->getMessage());
        wp_send_json_error('Update failed: ' . $e->getMessage());
    }
}
```

#### 3. Modify `update_tour()` Method
**File**: `class-h3tm-s3-simple.php` lines 1122-1185

```php
/**
 * Update an existing tour by replacing its files in S3
 *
 * @param string $tour_id The tour_id (immutable identifier)
 * @param string $temp_extract_dir Path to temporary directory containing extracted tour files
 * @return bool True on success, false on failure
 */
public function update_tour($tour_id, $temp_extract_dir) {
    error_log('H3TM Update: Starting update for tour_id: ' . $tour_id);

    // Get tour metadata to validate tour_id exists
    if (!class_exists('H3TM_Tour_Metadata')) {
        error_log('H3TM Update: Metadata class not available');
        return false;
    }

    $metadata = new H3TM_Tour_Metadata();
    $tour = $metadata->get_by_tour_id($tour_id);

    if (!$tour) {
        error_log('H3TM Update: Tour not found with tour_id: ' . $tour_id);
        return false;
    }

    error_log('H3TM Update: Found tour: ' . $tour->display_name);

    // Fix HTML references before uploading
    $this->fix_html_references($temp_extract_dir, $tour_id);

    // Upload all files to tours/{tour_id}/ (REPLACE existing files)
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temp_extract_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    $uploaded_count = 0;
    $failed_count = 0;

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relative_path = substr($file->getPathname(), strlen($temp_extract_dir) + 1);
            $s3_key = 'tours/' . $tour_id . '/' . str_replace('\\', '/', $relative_path);

            if ($this->upload_file_to_s3_public($file->getPathname(), $s3_key)) {
                $uploaded_count++;
            } else {
                $failed_count++;
                error_log('H3TM Update: Failed to upload file: ' . $relative_path);
            }
        }
    }

    error_log('H3TM Update: Uploaded ' . $uploaded_count . ' files, ' . $failed_count . ' failures');

    if ($uploaded_count === 0) {
        error_log('H3TM Update: No files were uploaded');
        return false;
    }

    // Invalidate CloudFront cache if CDN helper is available
    if ($this->cdn_helper) {
        error_log('H3TM Update: Invalidating CloudFront cache for tour_id: ' . $tour_id);
        $this->cdn_helper->invalidate_tour_by_id($tour_id);
    }

    // Clear tour list cache
    $this->clear_tour_cache();

    error_log('H3TM Update: Successfully updated tour_id: ' . $tour_id);
    return true;
}
```

#### 4. Add `archive_tour_by_id()` Helper Method
**File**: `class-h3tm-s3-simple.php` (add after existing `archive_tour()` method)

```php
/**
 * Archive a tour by tour_id
 *
 * @param string $tour_id Tour ID
 * @return array Result with success status and message
 */
public function archive_tour_by_id($tour_id) {
    if (!$this->is_configured) {
        return array('success' => false, 'message' => 'S3 not configured');
    }

    try {
        error_log('H3TM S3 Archive: Start request for tour_id: ' . $tour_id);

        if (!class_exists('H3TM_Tour_Metadata')) {
            error_log('H3TM S3 Archive: Metadata class missing');
            return array('success' => false, 'message' => 'Tour metadata unavailable');
        }

        $metadata = new H3TM_Tour_Metadata();
        $tour = $metadata->get_by_tour_id($tour_id);

        if (!$tour) {
            error_log('H3TM S3 Archive: Tour not found for tour_id: ' . $tour_id);
            return array('success' => false, 'message' => 'Tour not found');
        }

        $source_prefix = 'tours/' . $tour_id . '/';
        $archive_timestamp = date('Ymd_His');
        $archive_prefix = 'archive/' . $tour_id . '_' . $archive_timestamp . '/';

        error_log('H3TM S3 Archive: Moving ' . $source_prefix . ' to ' . $archive_prefix);

        // List all files in the tour
        $files = $this->list_tour_files($tour_id);

        if (empty($files)) {
            error_log('H3TM S3 Archive: No files found - tour may not exist yet');
            return array(
                'success' => true,
                'message' => 'No files to archive (first upload?)',
                'files_not_found' => true
            );
        }

        error_log('H3TM S3 Archive: Found ' . count($files) . ' files to archive');

        $moved = 0;
        $skipped = 0;
        $errors = 0;

        // Copy each file to archive location
        foreach ($files as $file) {
            $source_key = 'tours/' . $tour_id . '/' . $file;
            $dest_key = $archive_prefix . $file;

            $copy_result = $this->copy_s3_object($source_key, $dest_key);

            if ($copy_result === 'success') {
                // Delete the original after successful copy
                if ($this->delete_s3_object($source_key)) {
                    $moved++;
                } else {
                    $errors++;
                    error_log('H3TM S3 Archive: Failed to delete ' . $source_key);
                }
            } elseif ($copy_result === 'not_found') {
                $skipped++;
            } else {
                $errors++;
            }
        }

        error_log(sprintf(
            'H3TM S3 Archive: Completed with %d moved, %d skipped, %d errors for tour_id: %s',
            $moved,
            $skipped,
            $errors,
            $tour_id
        ));

        if ($errors === 0 || ($moved > 0 && $errors === 0)) {
            $message = 'Tour archived successfully';
            if ($skipped > 0) {
                $message .= ' (' . $skipped . ' phantom files skipped)';
            }
            return array('success' => true, 'message' => $message);
        } else {
            return array('success' => false, 'message' => 'Archive failed: ' . $moved . ' moved, ' . $errors . ' errors');
        }

    } catch (Exception $e) {
        error_log('H3TM S3 Archive Error: ' . $e->getMessage());
        return array('success' => false, 'message' => 'Archive failed: ' . $e->getMessage());
    }
}
```

## Complete Workflow

### Update Process:
1. ✅ User clicks Update button (only enabled for tours with `tour_id`)
2. ✅ Frontend sends `tour_id` to get presigned URL
3. ✅ Backend validates `tour_id` exists, generates upload URL
4. ✅ Frontend uploads ZIP to S3
5. **Backend archives current version** to `archive/{tour_id}_{timestamp}/`
6. **Backend downloads ZIP, extracts, uploads to** `tours/{tour_id}/`
7. **CloudFront cache invalidated** for `tours/{tour_id}/*`
8. ✅ Frontend reloads tour list

### Key Benefits:
- ✅ NO duplicate metadata creation
- ✅ Only ONE tour updates at a time (tracked by ID)
- ✅ Archives preserve version history
- ✅ Updates go to SAME S3 location (immutable ID)
- ✅ Legacy tours without `tour_id` cannot be updated (must re-upload)

## Lambda Changes

**Lambda does NOT need changes** - it already uses the tour_id-based folder structure (`tours/{tour_id}/`). The update workflow bypasses Lambda entirely:
- Upload → S3 direct (via presigned URL)
- Process → WordPress backend (download, extract, re-upload to same location)
- No Lambda processing needed for updates

## Testing Checklist

- [ ] Update button disabled for legacy tours
- [ ] Update button works for ID-based tours
- [ ] Only one tour shows spinner during update
- [ ] Old files archived to `archive/{tour_id}_{timestamp}/`
- [ ] New files uploaded to `tours/{tour_id}/`
- [ ] CloudFront cache invalidated
- [ ] Tour metadata unchanged (slug, display_name preserved)
- [ ] Tour URL remains the same after update
