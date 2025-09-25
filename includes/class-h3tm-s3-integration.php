<?php
/**
 * S3 Integration for Large File Uploads
 */

class H3TM_S3_Integration {

    private $bucket_name;
    private $region;
    private $access_key;
    private $secret_key;

    public function __construct() {
        // Get S3 configuration (environment variables take precedence)
        $this->bucket_name = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', '');
        $this->region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');
        $this->access_key = defined('AWS_ACCESS_KEY_ID') ? AWS_ACCESS_KEY_ID : get_option('h3tm_aws_access_key', '');
        $this->secret_key = defined('AWS_SECRET_ACCESS_KEY') ? AWS_SECRET_ACCESS_KEY : get_option('h3tm_aws_secret_key', '');

        // Add AJAX handlers
        add_action('wp_ajax_h3tm_get_s3_presigned_url', array($this, 'handle_get_presigned_url'));
        add_action('wp_ajax_h3tm_process_s3_upload', array($this, 'handle_process_s3_upload'));
        add_action('wp_ajax_h3tm_test_s3_connection', array($this, 'handle_test_s3_connection'));
    }

    /**
     * Check if S3 is properly configured
     */
    public function is_configured() {
        return !empty($this->bucket_name) && !empty($this->access_key) && !empty($this->secret_key);
    }

    /**
     * Generate presigned URL for direct S3 upload
     */
    public function handle_get_presigned_url() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!$this->is_configured()) {
            wp_send_json_error('S3 not configured. Please configure AWS settings.');
        }

        $file_name = sanitize_file_name($_POST['file_name']);
        $file_size = intval($_POST['file_size']);
        $tour_name = sanitize_text_field($_POST['tour_name']);

        // Validate file
        if (empty($file_name) || $file_size <= 0) {
            wp_send_json_error('Invalid file information.');
        }

        // Check file type
        $file_type = wp_check_filetype($file_name);
        if ($file_type['ext'] !== 'zip') {
            wp_send_json_error('Only ZIP files are allowed.');
        }

        // Generate unique file path
        $unique_id = uniqid() . '_' . time();
        $s3_key = 'uploads/' . $unique_id . '/' . $file_name;

        try {
            $presigned_url = $this->generate_presigned_url($s3_key, $file_size);

            wp_send_json_success(array(
                'upload_url' => $presigned_url,
                's3_key' => $s3_key,
                'unique_id' => $unique_id,
                'expires_in' => 3600 // 1 hour
            ));

        } catch (Exception $e) {
            error_log('H3TM S3 Error: Failed to generate presigned URL: ' . $e->getMessage());
            wp_send_json_error('Failed to generate upload URL. Falling back to chunked upload.');
        }
    }

    /**
     * Process completed S3 upload
     */
    public function handle_process_s3_upload() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tour_name = sanitize_text_field($_POST['tour_name']);
        $s3_key = sanitize_text_field($_POST['s3_key']);
        $unique_id = sanitize_text_field($_POST['unique_id']);

        if (empty($tour_name) || empty($s3_key)) {
            wp_send_json_error('Missing required parameters.');
        }

        try {
            // Download file from S3 to temporary location
            $temp_file = $this->download_from_s3($s3_key);

            if (!$temp_file) {
                wp_send_json_error('Failed to download file from S3.');
            }

            // Process using existing tour manager
            $tour_manager = new H3TM_Tour_Manager();
            $file_info = array(
                'name' => basename($s3_key),
                'tmp_name' => $temp_file,
                'error' => UPLOAD_ERR_OK,
                'size' => file_exists($temp_file) ? filesize($temp_file) : 0
            );

            $result = $tour_manager->upload_tour($tour_name, $file_info, true);

            // Clean up temporary file and S3 file
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            $this->delete_s3_file($s3_key);

            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }

        } catch (Exception $e) {
            error_log('H3TM S3 Error: Processing failed: ' . $e->getMessage());
            wp_send_json_error('Failed to process S3 upload: ' . $e->getMessage());
        }
    }

    /**
     * Test S3 connection
     */
    public function handle_test_s3_connection() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!$this->is_configured()) {
            wp_send_json_error('S3 configuration is incomplete.');
        }

        try {
            $test_result = $this->test_s3_connection();
            if ($test_result) {
                wp_send_json_success('S3 connection successful!');
            } else {
                wp_send_json_error('S3 connection failed. Check your credentials and bucket configuration.');
            }
        } catch (Exception $e) {
            wp_send_json_error('S3 connection error: ' . $e->getMessage());
        }
    }

    /**
     * Generate presigned URL for upload
     */
    private function generate_presigned_url($s3_key, $file_size) {
        $expires = time() + 3600; // 1 hour from now
        $method = 'PUT';

        // Create canonical request
        $canonical_uri = '/' . $s3_key;
        $canonical_querystring = '';
        $canonical_headers = "host:" . $this->bucket_name . ".s3." . $this->region . ".amazonaws.com\n";
        $signed_headers = 'host';
        $payload_hash = 'UNSIGNED-PAYLOAD';

        $canonical_request = $method . "\n" . $canonical_uri . "\n" . $canonical_querystring . "\n" .
                           $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = date('Ymd') . '/' . $this->region . '/s3/aws4_request';
        $string_to_sign = $algorithm . "\n" . $expires . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);

        // Calculate signature
        $signing_key = $this->get_signing_key(date('Ymd'), $this->region, 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        // Build presigned URL
        $presigned_url = "https://" . $this->bucket_name . ".s3." . $this->region . ".amazonaws.com" . $canonical_uri;
        $presigned_url .= "?X-Amz-Algorithm=" . $algorithm;
        $presigned_url .= "&X-Amz-Credential=" . urlencode($this->access_key . '/' . $credential_scope);
        $presigned_url .= "&X-Amz-Date=" . gmdate('Ymd\THis\Z');
        $presigned_url .= "&X-Amz-Expires=" . 3600;
        $presigned_url .= "&X-Amz-SignedHeaders=" . $signed_headers;
        $presigned_url .= "&X-Amz-Signature=" . $signature;

        return $presigned_url;
    }

    /**
     * Download file from S3 to temporary location
     */
    private function download_from_s3($s3_key) {
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/h3-tours/' . basename($s3_key);

        // Ensure directory exists
        $temp_dir = dirname($temp_file);
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $s3_url = "https://" . $this->bucket_name . ".s3." . $this->region . ".amazonaws.com/" . $s3_key;

        // Use WordPress HTTP API with authentication
        $response = wp_remote_get($s3_url, array(
            'timeout' => 300, // 5 minutes for large files
            'stream' => true,
            'filename' => $temp_file
        ));

        if (is_wp_error($response)) {
            error_log('H3TM S3 Download Error: ' . $response->get_error_message());
            return false;
        }

        return file_exists($temp_file) ? $temp_file : false;
    }

    /**
     * Delete file from S3
     */
    private function delete_s3_file($s3_key) {
        // Simple DELETE request to S3
        $s3_url = "https://" . $this->bucket_name . ".s3." . $this->region . ".amazonaws.com/" . $s3_key;

        $response = wp_remote_request($s3_url, array(
            'method' => 'DELETE',
            'timeout' => 30
        ));

        return !is_wp_error($response);
    }

    /**
     * Test S3 connection
     */
    private function test_s3_connection() {
        try {
            // Try to list bucket contents
            $s3_url = "https://" . $this->bucket_name . ".s3." . $this->region . ".amazonaws.com/";

            $response = wp_remote_get($s3_url, array(
                'timeout' => 10
            ));

            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) !== 403;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get AWS signing key
     */
    private function get_signing_key($date, $region, $service) {
        $key = 'AWS4' . $this->secret_key;
        $key = hash_hmac('sha256', $date, $key, true);
        $key = hash_hmac('sha256', $region, $key, true);
        $key = hash_hmac('sha256', $service, $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);
        return $key;
    }

    /**
     * Get S3 configuration for frontend
     */
    public function get_s3_config() {
        return array(
            'configured' => $this->is_configured(),
            'bucket' => $this->bucket_name,
            'region' => $this->region,
            'threshold_mb' => get_option('h3tm_s3_threshold', 100) // Default 100MB threshold
        );
    }
}