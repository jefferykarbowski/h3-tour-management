<?php
/**
 * Tour Handlers Trait
 *
 * Handles tour CRUD operations and utility functions:
 * - Update Tour (overwrite existing tour files)
 * - Get Embed Script (generate iframe embed codes)
 * - Change Tour URL (modify tour slug with redirect history)
 *
 * @package H3_Tour_Management
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Trait_H3TM_Tour_Handlers {

    /**
     * Handle Update Tour AJAX request
     * Overwrites existing tour files and invalidates CloudFront cache
     */
    public function handle_update_tour() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tour_name = isset($_POST['tour_name']) ? sanitize_text_field($_POST['tour_name']) : '';
        $s3_key = isset($_POST['s3_key']) ? sanitize_text_field($_POST['s3_key']) : '';

        if (empty($tour_name) || empty($s3_key)) {
            wp_send_json_error('Missing required parameters');
        }

        error_log('H3TM Update: Starting update for tour: ' . $tour_name);

        try {
            $s3 = new H3TM_S3_Simple();

            // Download ZIP from S3
            $file_name = basename($s3_key);
            $temp_zip_path = $s3->download_zip_temporarily($s3_key, $file_name);

            if (!$temp_zip_path) {
                wp_send_json_error('Failed to download tour from S3');
            }

            // Extract ZIP
            $temp_extract_dir = $s3->extract_tour_temporarily($temp_zip_path, $tour_name);

            if (!$temp_extract_dir) {
                if (file_exists($temp_zip_path)) unlink($temp_zip_path);
                wp_send_json_error('Failed to extract tour ZIP');
            }

            // Update tour in S3
            $success = $s3->update_tour($tour_name, $temp_extract_dir);

            // Cleanup
            if (file_exists($temp_zip_path)) unlink($temp_zip_path);
            if (is_dir($temp_extract_dir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($temp_extract_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $file) {
                    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                }
                rmdir($temp_extract_dir);
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

    /**
     * Handle Get Embed Script AJAX request
     * Returns iframe embed code for tour
     */
    public function handle_get_embed_script() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tour_name = isset($_POST['tour_name']) ? sanitize_text_field($_POST['tour_name']) : '';

        if (empty($tour_name)) {
            wp_send_json_error('Tour name is required');
        }

        // Get tour URL using metadata
        $tour_url = '';
        $tour = null;

        if (class_exists('H3TM_Tour_Metadata')) {
            $metadata = new H3TM_Tour_Metadata();

            // Try to find tour by display name (most common case from UI)
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

        // Fallback to sanitized name if tour not found in metadata (legacy tours)
        if (empty($tour_url)) {
            $tour_slug = sanitize_title($tour_name);
            $tour_url = home_url('/h3panos/' . $tour_slug . '/');
        }

        // Generate embed scripts
        $embed_script = '<iframe
  src="' . esc_url($tour_url) . '"
  width="100%"
  height="600"
  style="border: 0; border-radius: 8px; max-width: 100%;"
  allow="fullscreen; gyroscope; accelerometer"
  loading="lazy"
  title="' . esc_attr($tour_name) . ' - 3D Tour">
</iframe>';

        $embed_script_responsive = '<!-- Responsive 3D Tour Embed -->
<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">
  <iframe
    src="' . esc_url($tour_url) . '"
    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
    allow="fullscreen; gyroscope; accelerometer"
    loading="lazy"
    title="' . esc_attr($tour_name) . ' - 3D Tour">
  </iframe>
</div>';

        wp_send_json_success(array(
            'tour_name' => $tour_name,
            'tour_url' => $tour_url,
            'embed_script' => $embed_script,
            'embed_script_responsive' => $embed_script_responsive
        ));
    }

    /**
     * Handle Change Tour URL AJAX request
     * Changes tour slug and adds old slug to redirect history
     */
    public function handle_change_tour_url() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tour_name = isset($_POST['tour_name']) ? sanitize_text_field($_POST['tour_name']) : '';
        $new_slug = isset($_POST['new_slug']) ? sanitize_title($_POST['new_slug']) : '';

        if (empty($tour_name) || empty($new_slug)) {
            wp_send_json_error('Missing required parameters');
        }

        // Get current tour metadata
        if (!class_exists('H3TM_Tour_Metadata')) {
            wp_send_json_error('Tour metadata system not available');
        }

        $metadata = new H3TM_Tour_Metadata();
        $tour = $metadata->get_by_display_name($tour_name);

        if (!$tour) {
            wp_send_json_error('Tour not found');
        }

        // Only allow URL changes for new ID-based tours
        if (empty($tour->tour_id)) {
            wp_send_json_error('URL changes are only supported for new tours. Legacy tours cannot be renamed or have their URLs changed.');
        }

        $old_slug = $tour->tour_slug;

        // Check if new slug already exists
        if ($metadata->slug_exists($new_slug, $tour->id)) {
            wp_send_json_error('URL slug already in use by another tour');
        }

        // Check if new slug is in any tour's history
        if ($metadata->find_by_old_slug($new_slug)) {
            wp_send_json_error('This URL slug was previously used and cannot be reused');
        }

        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $new_slug)) {
            wp_send_json_error('URL slug can only contain lowercase letters, numbers, and hyphens');
        }

        // Change the slug
        $success = $metadata->change_slug($old_slug, $new_slug);

        if ($success) {
            // Clear tour cache
            if (class_exists('H3TM_S3_Simple')) {
                $s3 = new H3TM_S3_Simple();
                $s3->clear_tour_cache();
            }

            // Flush rewrite rules to activate new URL
            flush_rewrite_rules();

            wp_send_json_success(array(
                'message' => 'URL changed successfully',
                'old_slug' => $old_slug,
                'new_slug' => $new_slug,
                'new_url' => home_url('/h3panos/' . $new_slug . '/')
            ));
        } else {
            wp_send_json_error('Failed to change tour URL');
        }
    }
}
