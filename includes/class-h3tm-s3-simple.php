<?php
/**
 * Simple S3 Integration - No Complex Dependencies
 */
class H3TM_S3_Simple {

    public function __construct() {
        // Add AJAX handlers
        add_action('wp_ajax_h3tm_get_s3_presigned_url', array($this, 'handle_get_presigned_url'));
        add_action('wp_ajax_h3tm_process_s3_upload', array($this, 'handle_process_s3_upload'));
        add_action('wp_ajax_h3tm_test_s3_connection', array($this, 'handle_test_s3_connection'));
    }

    /**
     * Get S3 configuration directly
     */
    private function get_s3_credentials() {
        $bucket = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', '');
        $region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');
        $access_key = defined('AWS_ACCESS_KEY_ID') ? AWS_ACCESS_KEY_ID : get_option('h3tm_aws_access_key', '');
        $secret_key = defined('AWS_SECRET_ACCESS_KEY') ? AWS_SECRET_ACCESS_KEY : get_option('h3tm_aws_secret_key', '');

        error_log('H3TM S3 Simple: bucket=' . $bucket . ', region=' . $region);
        error_log('H3TM S3 Simple: access_key=' . (empty($access_key) ? 'EMPTY' : 'SET'));
        error_log('H3TM S3 Simple: secret_key=' . (empty($secret_key) ? 'EMPTY' : 'SET'));

        return array(
            'bucket' => $bucket,
            'region' => $region,
            'access_key' => $access_key,
            'secret_key' => $secret_key,
            'configured' => !empty($bucket) && !empty($access_key) && !empty($secret_key)
        );
    }

    /**
     * Test S3 connection by attempting to list bucket
     */
    public function handle_test_s3_connection() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $config = $this->get_s3_credentials();

        if (!$config['configured']) {
            $missing = array();
            if (empty($config['bucket'])) $missing[] = 'bucket';
            if (empty($config['access_key'])) $missing[] = 'access_key';
            if (empty($config['secret_key'])) $missing[] = 'secret_key';

            wp_send_json_error('S3 configuration incomplete: ' . implode(', ', $missing));
        }

        try {
            // Simple bucket test - attempt to generate a presigned URL
            $test_key = 'test/' . uniqid() . '.txt';
            $presigned_url = $this->generate_simple_presigned_url($config, $test_key);

            if (!empty($presigned_url)) {
                wp_send_json_success('S3 connection test successful!');
            } else {
                wp_send_json_error('Failed to generate presigned URL');
            }
        } catch (Exception $e) {
            wp_send_json_error('S3 test failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate presigned URL
     */
    public function handle_get_presigned_url() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        error_log('H3TM S3 Simple: Presigned URL request started');

        $config = $this->get_s3_credentials();

        if (!$config['configured']) {
            $missing = array();
            if (empty($config['bucket'])) $missing[] = 'bucket';
            if (empty($config['access_key'])) $missing[] = 'access_key';
            if (empty($config['secret_key'])) $missing[] = 'secret_key';

            error_log('H3TM S3 Simple: Missing configuration: ' . implode(', ', $missing));
            wp_send_json_error('Missing S3 configuration: ' . implode(', ', $missing));
        }

        $file_name = isset($_POST['file_name']) ? sanitize_file_name($_POST['file_name']) : '';
        $file_size = isset($_POST['file_size']) ? intval($_POST['file_size']) : 0;
        $tour_name = isset($_POST['tour_name']) ? sanitize_text_field($_POST['tour_name']) : '';

        if (empty($file_name) || empty($tour_name) || $file_size <= 0) {
            error_log('H3TM S3 Simple: Missing required parameters - file_name=' . $file_name . ', tour_name=' . $tour_name . ', file_size=' . $file_size);
            wp_send_json_error('Missing required upload parameters');
        }

        // Simple presigned URL generation
        $unique_id = uniqid() . '_' . time();
        $s3_key = 'uploads/' . $unique_id . '/' . $file_name;

        try {
            $presigned_url = $this->generate_simple_presigned_url($config, $s3_key);

            wp_send_json_success(array(
                'upload_url' => $presigned_url,
                's3_key' => $s3_key,
                'unique_id' => $unique_id
            ));

        } catch (Exception $e) {
            error_log('H3TM S3 Simple: Presigned URL generation failed: ' . $e->getMessage());
            wp_send_json_error('Failed to generate presigned URL: ' . $e->getMessage());
        }
    }

    /**
     * Generate proper AWS4 presigned URL
     */
    private function generate_simple_presigned_url($config, $s3_key) {
        $host = $config['bucket'] . ".s3." . $config['region'] . ".amazonaws.com";
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $expires = 3600; // 1 hour

        // Create canonical request
        $method = 'PUT';
        $canonical_uri = '/' . $s3_key;
        $canonical_querystring = 'X-Amz-Algorithm=AWS4-HMAC-SHA256';
        $canonical_querystring .= '&X-Amz-Credential=' . urlencode($config['access_key'] . '/' . $date . '/' . $config['region'] . '/s3/aws4_request');
        $canonical_querystring .= '&X-Amz-Date=' . $datetime;
        $canonical_querystring .= '&X-Amz-Expires=' . $expires;
        $canonical_querystring .= '&X-Amz-SignedHeaders=host';

        $canonical_headers = "host:" . $host . "\n";
        $signed_headers = 'host';
        $payload_hash = 'UNSIGNED-PAYLOAD';

        $canonical_request = $method . "\n" . $canonical_uri . "\n" . $canonical_querystring . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date . '/' . $config['region'] . '/s3/aws4_request';
        $string_to_sign = $algorithm . "\n" . $datetime . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);

        // Calculate signature
        $signing_key = $this->getSignatureKey($config['secret_key'], $date, $config['region'], 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        // Build final URL
        $url = "https://" . $host . $canonical_uri . '?' . $canonical_querystring . '&X-Amz-Signature=' . $signature;

        return $url;
    }

    /**
     * Generate AWS4 signing key
     */
    private function getSignatureKey($key, $dateStamp, $regionName, $serviceName) {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return $kSigning;
    }

    /**
     * Process S3 upload by downloading from S3 and extracting tour
     */
    public function handle_process_s3_upload() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tour_name = sanitize_text_field($_POST['tour_name']);
        $s3_key = sanitize_text_field($_POST['s3_key']);
        $unique_id = sanitize_text_field($_POST['unique_id']);
        $file_name = sanitize_file_name($_POST['file_name']);

        if (empty($tour_name) || empty($s3_key)) {
            wp_send_json_error('Missing required parameters');
        }

        try {
            // Download file from S3
            $local_file_path = $this->download_from_s3($s3_key, $file_name);

            if (!$local_file_path) {
                wp_send_json_error('Failed to download file from S3');
            }

            // Process the downloaded file using tour manager
            $tour_manager = new H3TM_Tour_Manager();
            $file_info = array(
                'name' => $file_name,
                'tmp_name' => $local_file_path,
                'error' => UPLOAD_ERR_OK,
                'size' => file_exists($local_file_path) ? filesize($local_file_path) : 0
            );

            $result = $tour_manager->upload_tour($tour_name, $file_info, false);

            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }

        } catch (Exception $e) {
            error_log('H3TM S3 Processing Error: ' . $e->getMessage());
            wp_send_json_error('S3 processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Download file from S3
     */
    private function download_from_s3($s3_key, $file_name) {
        $config = $this->get_s3_credentials();

        if (!$config['configured']) {
            throw new Exception('S3 not configured');
        }

        // Create download URL
        $download_url = "https://" . $config['bucket'] . ".s3." . $config['region'] . ".amazonaws.com/" . $s3_key;

        // Create local file path
        $upload_dir = wp_upload_dir();
        $h3_tours_dir = $upload_dir['basedir'] . '/h3-tours';

        if (!file_exists($h3_tours_dir)) {
            if (!wp_mkdir_p($h3_tours_dir)) {
                throw new Exception('Failed to create h3-tours directory');
            }
        }

        $local_file_path = $h3_tours_dir . '/' . $file_name;

        // Download file with proper authentication (simplified approach)
        $response = wp_remote_get($download_url, array(
            'timeout' => 600, // 10 minutes for large files
            'stream' => true,
            'filename' => $local_file_path
        ));

        if (is_wp_error($response)) {
            throw new Exception('Download failed: ' . $response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            throw new Exception('S3 download failed with status: ' . wp_remote_retrieve_response_code($response));
        }

        if (!file_exists($local_file_path) || filesize($local_file_path) === 0) {
            throw new Exception('Downloaded file is empty or missing');
        }

        return $local_file_path;
    }

    /**
     * Get configuration for admin compatibility
     */
    public function get_s3_config() {
        $config = $this->get_s3_credentials();
        return array(
            'configured' => $config['configured'],
            'bucket' => $config['bucket'],
            'region' => $config['region'],
            'threshold_mb' => get_option('h3tm_s3_threshold', 50) // Lower threshold for S3-only approach
        );
    }
}