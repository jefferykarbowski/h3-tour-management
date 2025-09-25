<?php
/**
 * S3 File Processing Handler
 * Handles the download and processing of files uploaded to S3
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_S3_Processor {

    private $s3_integration;
    private $tour_manager;

    public function __construct() {
        $this->s3_integration = H3TM_S3_Integration::getInstance();
        $this->tour_manager = new H3TM_Tour_Manager();

        // Register WordPress cron hook
        add_action('h3tm_process_s3_upload', array($this, 'process_s3_upload'));
    }

    /**
     * Process uploaded S3 file (called via WordPress cron)
     */
    public function process_s3_upload($upload_id) {
        // Increase limits for processing
        @ini_set('max_execution_time', 900); // 15 minutes
        @ini_set('memory_limit', '1024M');

        error_log('H3TM S3 Processor: Starting processing for upload ID: ' . $upload_id);

        // Get upload metadata
        $metadata = get_transient('h3tm_s3_upload_' . $upload_id);
        if (!$metadata) {
            error_log('H3TM S3 Processor Error: Upload metadata not found for ID: ' . $upload_id);
            return;
        }

        try {
            $this->update_processing_status($upload_id, 'downloading');

            // Download file from S3
            $local_file = $this->download_file_from_s3($metadata);
            if (is_wp_error($local_file)) {
                throw new Exception('Download failed: ' . $local_file->get_error_message());
            }

            $this->update_processing_status($upload_id, 'extracting');

            // Process the tour using existing extraction logic
            $result = $this->process_tour_file($metadata, $local_file);
            if (is_wp_error($result)) {
                throw new Exception('Processing failed: ' . $result->get_error_message());
            }

            $this->update_processing_status($upload_id, 'cleaning_up');

            // Clean up local temp file
            unlink($local_file);

            // Move S3 file to processed directory
            $this->move_s3_file_to_processed($metadata);

            // Mark as completed
            $this->update_processing_status($upload_id, 'completed', [
                'tour_path' => $result['tour_path'],
                'completed_at' => current_time('mysql')
            ]);

            error_log('H3TM S3 Processor: Successfully processed upload ID: ' . $upload_id);

        } catch (Exception $e) {
            error_log('H3TM S3 Processor Error: ' . $e->getMessage() . ' (Upload ID: ' . $upload_id . ')');

            // Mark as failed
            $this->update_processing_status($upload_id, 'failed', [
                'error' => $e->getMessage(),
                'failed_at' => current_time('mysql')
            ]);

            // Move S3 file to failed directory
            $this->move_s3_file_to_failed($metadata);

            // Clean up local file if it exists
            if (isset($local_file) && file_exists($local_file)) {
                unlink($local_file);
            }
        }
    }

    /**
     * Download file from S3 to local temporary location
     */
    private function download_file_from_s3($metadata) {
        // Create temporary file path
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/h3-s3-temp';

        if (!file_exists($temp_dir)) {
            if (!wp_mkdir_p($temp_dir)) {
                return new WP_Error('temp_dir_failed', 'Failed to create temporary directory');
            }
        }

        $local_file = $temp_dir . '/' . basename($metadata['file_name']);

        // Download from S3
        $download_result = $this->s3_integration->download_s3_file($metadata['s3_key'], $local_file);

        if (is_wp_error($download_result)) {
            return $download_result;
        }

        // Verify file was downloaded and has correct size
        if (!file_exists($local_file)) {
            return new WP_Error('download_verification_failed', 'Downloaded file not found');
        }

        $downloaded_size = filesize($local_file);
        if ($downloaded_size !== $metadata['file_size']) {
            error_log("H3TM S3 Processor Warning: Size mismatch. Expected: {$metadata['file_size']}, Got: {$downloaded_size}");
        }

        return $local_file;
    }

    /**
     * Process the downloaded tour file using existing tour manager
     */
    private function process_tour_file($metadata, $local_file) {
        // Create a file array that mimics $_FILES structure
        $file_array = [
            'name' => $metadata['file_name'],
            'type' => 'application/zip',
            'tmp_name' => $local_file,
            'error' => UPLOAD_ERR_OK,
            'size' => $metadata['file_size']
        ];

        // Use existing tour manager upload logic
        $result = $this->tour_manager->upload_tour(
            $metadata['tour_name'],
            $file_array,
            true // is_pre_uploaded flag
        );

        if (!$result['success']) {
            return new WP_Error('tour_processing_failed', $result['message']);
        }

        return [
            'success' => true,
            'tour_path' => H3TM_Pantheon_Helper::get_h3panos_path() . '/' . $metadata['tour_name']
        ];
    }

    /**
     * Move processed file to processed directory in S3
     */
    private function move_s3_file_to_processed($metadata) {
        $current_key = $metadata['s3_key'];
        $new_key = str_replace('temp/', 'processed/', $current_key);

        try {
            // Copy to new location
            $this->s3_integration->get_s3_client()->copyObject([
                'Bucket' => $this->s3_integration->get_bucket_name(),
                'Key' => $new_key,
                'CopySource' => $this->s3_integration->get_bucket_name() . '/' . $current_key
            ]);

            // Delete original
            $this->s3_integration->delete_s3_file($current_key);

        } catch (Exception $e) {
            error_log('H3TM S3 Processor Warning: Failed to move processed file - ' . $e->getMessage());
        }
    }

    /**
     * Move failed file to failed directory in S3
     */
    private function move_s3_file_to_failed($metadata) {
        $current_key = $metadata['s3_key'];
        $new_key = str_replace('temp/', 'failed/', $current_key);

        try {
            // Copy to failed location
            $this->s3_integration->get_s3_client()->copyObject([
                'Bucket' => $this->s3_integration->get_bucket_name(),
                'Key' => $new_key,
                'CopySource' => $this->s3_integration->get_bucket_name() . '/' . $current_key
            ]);

            // Delete original
            $this->s3_integration->delete_s3_file($current_key);

        } catch (Exception $e) {
            error_log('H3TM S3 Processor Warning: Failed to move failed file - ' . $e->getMessage());
        }
    }

    /**
     * Update processing status
     */
    private function update_processing_status($upload_id, $status, $additional_data = []) {
        $metadata = get_transient('h3tm_s3_upload_' . $upload_id);
        if ($metadata) {
            $metadata['status'] = $status;
            $metadata['updated_at'] = current_time('mysql');
            $metadata = array_merge($metadata, $additional_data);

            set_transient('h3tm_s3_upload_' . $upload_id, $metadata, 24 * HOUR_IN_SECONDS);

            error_log('H3TM S3 Processor: Status updated to ' . $status . ' for upload ID: ' . $upload_id);
        }
    }

    /**
     * Retry failed processing (admin function)
     */
    public function retry_failed_processing($upload_id) {
        $metadata = get_transient('h3tm_s3_upload_' . $upload_id);

        if (!$metadata || $metadata['status'] !== 'failed') {
            return new WP_Error('invalid_retry', 'Upload not found or not in failed state');
        }

        // Check if file still exists in failed directory
        $failed_key = str_replace('temp/', 'failed/', $metadata['s3_key']);
        if (!$this->s3_integration->check_s3_file_exists($failed_key)) {
            return new WP_Error('file_not_found', 'Failed file no longer exists in S3');
        }

        // Move back to temp and retry
        $metadata['s3_key'] = str_replace('failed/', 'temp/', $failed_key);
        $metadata['status'] = 'uploaded';
        set_transient('h3tm_s3_upload_' . $upload_id, $metadata, 24 * HOUR_IN_SECONDS);

        // Schedule retry
        wp_schedule_single_event(time() + 60, 'h3tm_process_s3_upload', [$upload_id]);

        return true;
    }

    /**
     * Get processing statistics for admin dashboard
     */
    public function get_processing_stats() {
        global $wpdb;

        // Since we're using transients, we'll need to query the options table
        // This is not ideal for production - consider using a custom table
        $uploads = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_h3tm_s3_upload_%'",
            ARRAY_A
        );

        $stats = [
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
            'processing' => 0,
            'uploaded' => 0
        ];

        foreach ($uploads as $upload) {
            $data = maybe_unserialize($upload['option_value']);
            if (isset($data['status'])) {
                $stats['total']++;
                $stats[$data['status']] = ($stats[$data['status']] ?? 0) + 1;
            }
        }

        return $stats;
    }

    /**
     * Clean up old processing data
     */
    public function cleanup_old_processing_data() {
        global $wpdb;

        // Delete transients older than 7 days
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_h3tm_s3_upload_%'
             AND option_value < " . (time() - 7 * DAY_IN_SECONDS)
        );

        // Delete corresponding transient data
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_h3tm_s3_upload_%'
             AND option_name NOT IN (
                 SELECT REPLACE(option_name, '_transient_timeout_', '_transient_')
                 FROM {$wpdb->options}
                 WHERE option_name LIKE '_transient_timeout_h3tm_s3_upload_%'
             )"
        );
    }
}