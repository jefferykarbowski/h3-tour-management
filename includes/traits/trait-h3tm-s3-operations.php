<?php
/**
 * S3 Operations Trait
 *
 * Handles S3-related helper methods and configuration:
 * - S3 configuration retrieval
 * - Tour listing from S3
 * - File download from S3
 * - File cleanup operations
 *
 * @package H3_Tour_Management
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Trait_H3TM_S3_Operations {

    /**
     * Get S3 config helper (simple version)
     */
    private function get_s3_simple_config() {
        $bucket = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', 'h3-tour-files-h3vt');
        $region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');

        return array(
            'configured' => !empty($bucket),
            'bucket' => $bucket,
            'region' => $region
        );
    }

    /**
     * Get S3 configuration from WordPress options or environment variables
     */
    private function get_s3_config() {
        // Check if S3 is configured via environment variables (recommended for security)
        // Note: Using same option keys as H3TM_S3_Simple class for consistency
        $s3_config = array(
            'access_key' => getenv('AWS_ACCESS_KEY_ID') ?: get_option('h3tm_aws_access_key', ''),
            'secret_key' => getenv('AWS_SECRET_ACCESS_KEY') ?: get_option('h3tm_aws_secret_key', ''),
            'bucket' => getenv('AWS_S3_BUCKET') ?: get_option('h3tm_s3_bucket', ''),
            'region' => getenv('AWS_S3_REGION') ?: get_option('h3tm_s3_region', 'us-east-1')
        );

        // Return false if required config is missing
        if (empty($s3_config['access_key']) || empty($s3_config['secret_key']) || empty($s3_config['bucket'])) {
            return false;
        }

        return $s3_config;
    }

    /**
     * Get all tours from S3
     */
    private function get_all_tours_by_source() {
        $tours = array();

        try {
            // Get tours from S3 bucket via CloudFront
            $s3_simple = new H3TM_S3_Simple();
            $s3_config = $s3_simple->get_s3_config();

            if ($s3_config['configured']) {
                $s3_tours = $s3_simple->list_s3_tours();

                if (is_array($s3_tours) && !empty($s3_tours)) {
                    foreach ($s3_tours as $tour) {
                        $tours[] = $tour;
                    }
                    error_log('H3TM Admin: Found ' . count($s3_tours) . ' tours');
                }
            }
        } catch (Exception $e) {
            error_log('H3TM Admin: Error getting tours: ' . $e->getMessage());
        }

        return $tours;
    }

    /**
     * Get tours from S3 bucket
     */
    private function get_s3_tours() {
        $all_tours = array();

        // Get tours from S3 bucket
        $s3_simple = new H3TM_S3_Simple();
        $s3_tours = $s3_simple->list_s3_tours();

        if (!empty($s3_tours)) {
            error_log('H3TM Admin: Found ' . count($s3_tours) . ' tours');
            foreach ($s3_tours as $tour) {
                $all_tours[] = $tour;
            }
        }

        return $all_tours;
    }

    /**
     * Download file from S3 to local temp location
     */
    private function download_from_s3($s3_config, $s3_key, $original_filename) {
        // Create temp file
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/h3-tours-temp';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $temp_file = $temp_dir . '/' . uniqid('s3_download_') . '_' . $original_filename;

        // Construct S3 URL
        $s3_url = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/' . $s3_key;

        // For production, you would use AWS SDK to download the file properly
        // For now, using a simplified approach with signed URLs or public access
        // This is a placeholder - implement proper S3 download using AWS SDK

        // Simple approach using cURL (requires proper S3 authentication)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $s3_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
        curl_setopt($ch, CURLOPT_FILE, fopen($temp_file, 'w+'));

        $success = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($success && $http_code === 200 && file_exists($temp_file) && filesize($temp_file) > 0) {
            return $temp_file;
        }

        // Clean up failed download
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }

        error_log('H3TM S3 Download Failed: HTTP ' . $http_code . ' for ' . $s3_url);
        return false;
    }

    /**
     * Clean up S3 file after processing
     */
    private function cleanup_s3_file($s3_config, $s3_key) {
        // For production, implement proper S3 file deletion using AWS SDK
        // This is a placeholder for S3 cleanup logic
        error_log('H3TM S3 Cleanup: Would delete ' . $s3_key . ' from bucket ' . $s3_config['bucket']);
    }
}
