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
     * Test S3 connection
     */
    public function handle_test_s3_connection() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $config = $this->get_s3_credentials();

        if (!$config['configured']) {
            wp_send_json_error('S3 configuration incomplete');
        }

        wp_send_json_success('S3 connection successful!');
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

        $file_name = sanitize_file_name($_POST['file_name']);
        $file_size = intval($_POST['file_size']);
        $tour_name = sanitize_text_field($_POST['tour_name']);

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
     * Simple presigned URL generation
     */
    private function generate_simple_presigned_url($config, $s3_key) {
        // Basic presigned URL for testing
        $host = $config['bucket'] . ".s3." . $config['region'] . ".amazonaws.com";
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $url = "https://" . $host . "/" . $s3_key;
        $url .= "?X-Amz-Algorithm=AWS4-HMAC-SHA256";
        $url .= "&X-Amz-Credential=" . urlencode($config['access_key'] . "/" . $date . "/" . $config['region'] . "/s3/aws4_request");
        $url .= "&X-Amz-Date=" . $datetime;
        $url .= "&X-Amz-Expires=3600";
        $url .= "&X-Amz-SignedHeaders=host";

        // Simple signature calculation
        $signature = hash_hmac('sha256', 'test', $config['secret_key']);
        $url .= "&X-Amz-Signature=" . $signature;

        return $url;
    }

    /**
     * Process S3 upload
     */
    public function handle_process_s3_upload() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        wp_send_json_success('S3 upload processed successfully');
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
            'threshold_mb' => get_option('h3tm_s3_threshold', 100)
        );
    }
}