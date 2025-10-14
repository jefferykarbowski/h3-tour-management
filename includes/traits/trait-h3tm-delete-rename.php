<?php
/**
 * Delete & Rename Trait
 *
 * Handles tour deletion and renaming operations:
 * - Delete Tour (archive to S3 archive/ folder)
 * - Rename Tour (update display name in metadata)
 *
 * @package H3_Tour_Management
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Trait_H3TM_Delete_Rename {

    /**
     * Handle tour deletion - archives to archive/ folder for 90 days
     */
    public function handle_delete_tour() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tour_name = sanitize_text_field($_POST['tour_name']);

        error_log('H3TM Delete: Attempting to archive: ' . $tour_name);

        // Use S3 Simple to archive the tour
        $s3 = new H3TM_S3_Simple();

        // Archive the tour to the archive/ folder
        $archive_result = $s3->archive_tour($tour_name);

        if ($archive_result['success']) {
            // Clear the cache so the tour list updates
            delete_transient('h3tm_s3_tour_list');

            wp_send_json_success('Tour archived successfully. It will be permanently deleted after 90 days.');
        } else {
            // Check if it's a configuration issue or tour not found
            if (strpos($archive_result['message'], 'not configured') !== false) {
                wp_send_json_error('S3 is not configured. Please configure S3 settings first.');
            } else {
                wp_send_json_error($archive_result['message']);
            }
        }
    }

    /**
     * Handle tour rename - actually renames the S3 folder
     */
    public function handle_rename_tour() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $old_name = sanitize_text_field($_POST['old_name']);
        $new_name = sanitize_text_field($_POST['new_name']);

        error_log('H3TM Rename: "' . $old_name . '" → "' . $new_name . '"');

        // Use S3 Simple to rename the tour
        $s3 = new H3TM_S3_Simple();

        // Rename the tour in S3
        $rename_result = $s3->rename_tour($old_name, $new_name);

        if ($rename_result['success']) {
            // Clear the cache so the tour list updates
            delete_transient('h3tm_s3_tour_list');

            wp_send_json_success('Tour renamed successfully');
        } else {
            // Check if it's a configuration issue or tour not found
            if (strpos($rename_result['message'], 'not configured') !== false) {
                wp_send_json_error('S3 is not configured. Please configure S3 settings first.');
            } else {
                wp_send_json_error($rename_result['message']);
            }
        }
        return;

        // Try local tour rename (fallback for legacy systems - currently unused)
        @ini_set('max_execution_time', 900);
        @ini_set('memory_limit', '1024M');

        // Create debug info for troubleshooting (like upload handler)
        $h3panos_path = ABSPATH . 'h3panos';
        $upload_dir = wp_upload_dir();
        $debug_info = array(
            'operation' => 'rename_tour',
            'old_name' => $old_name,
            'new_name' => $new_name,
            'is_pantheon' => (defined('PANTHEON_ENVIRONMENT') || strpos(ABSPATH, '/code/') === 0),
            'h3panos_path' => $h3panos_path,
            'h3panos_exists' => file_exists($h3panos_path),
            'h3panos_writeable' => is_writeable($h3panos_path),
            'abspath' => ABSPATH,
            'upload_basedir' => $upload_dir['basedir'],
            'old_tour_path' => $h3panos_path . '/' . $old_name,
            'new_tour_path' => $h3panos_path . '/' . $new_name,
            'old_tour_exists' => file_exists($h3panos_path . '/' . $old_name),
            'new_tour_exists' => file_exists($h3panos_path . '/' . $new_name),
            'using_optimized' => $this->use_optimized,
            'handler' => 'h3tm_rename_tour'
        );

        try {
            $tour_manager = $this->get_tour_manager();
            $result = $tour_manager->rename_tour($old_name, $new_name);

            if ($result['success']) {
                // Include some debug info in success response for monitoring
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'debug' => array(
                        'is_pantheon' => $debug_info['is_pantheon'],
                        'using_optimized' => $debug_info['using_optimized']
                    )
                ));
            } else {
                // Enhanced error response with debug info (like upload handler)
                error_log('H3TM Rename Error: ' . $result['message'] . ' | Debug: ' . json_encode($debug_info));
                wp_send_json_error(array(
                    'message' => $result['message'],
                    'debug' => $debug_info
                ));
            }
        } catch (Exception $e) {
            // Catch any unexpected errors and provide debug info
            $error_msg = 'Rename operation failed: ' . $e->getMessage();
            error_log('H3TM Rename Exception: ' . $error_msg . ' | Debug: ' . json_encode($debug_info));
            wp_send_json_error(array(
                'message' => $error_msg,
                'debug' => $debug_info
            ));
        }
    }
}
