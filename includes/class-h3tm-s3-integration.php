<?php
/**
 * S3 Integration for Large File Uploads
 * Robust configuration management with persistent settings across request contexts
 */

class H3TM_S3_Integration {

    private static $instance = null;
    private $config_manager = null;
    private $encryption_key = null;

    public function __construct() {
        // Initialize config manager
        $this->config_manager = H3TM_S3_Config_Manager::getInstance();

        // Initialize encryption key
        $this->init_encryption_key();

        error_log('H3TM S3 Integration: Constructor called with enhanced config manager');

        // Register AJAX handlers with enhanced error handling
        add_action('wp_ajax_h3tm_get_s3_presigned_url', array($this, 'handle_get_presigned_url'));
        add_action('wp_ajax_h3tm_process_s3_upload', array($this, 'handle_process_s3_upload'));
        add_action('wp_ajax_h3tm_test_s3_connection', array($this, 'handle_test_s3_connection'));
        add_action('wp_ajax_h3tm_validate_s3_config', array($this, 'handle_validate_s3_config'));
        add_action('wp_ajax_h3tm_debug_s3_config', array($this, 'handle_debug_s3_config'));

        // Test AJAX handler registration
        add_action('wp_ajax_h3tm_test_ajax_handler', function() {
            error_log('H3TM S3 Integration: Test AJAX handler called successfully');
            wp_send_json_success('AJAX handler working');
        });

        error_log('H3TM S3 Integration: AJAX handlers registered with enhanced configuration management');
    }

    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize encryption key for secure credential storage
     */
    private function init_encryption_key() {
        if (defined('AUTH_KEY') && defined('SECURE_AUTH_KEY')) {
            $this->encryption_key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
        } else {
            $this->encryption_key = hash('sha256', 'h3tm_fallback_key_' . get_option('siteurl', ''));
        }
    }

    /**
     * Get S3 configuration with robust fallback mechanism
     */
    private function get_s3_config() {
        if ($this->config_cache !== null) {
            return $this->config_cache;
        }

        $config = array();

        // Priority 1: Environment variables (most secure)
        $config['bucket_name'] = $this->get_config_value('bucket', [
            'H3_S3_BUCKET',
            'H3TM_S3_BUCKET',
            'AWS_S3_BUCKET'
        ], 'h3tm_s3_bucket');

        $config['region'] = $this->get_config_value('region', [
            'H3_S3_REGION',
            'H3TM_S3_REGION',
            'AWS_DEFAULT_REGION',
            'AWS_REGION'
        ], 'h3tm_s3_region', 'us-east-1');

        $config['access_key'] = $this->get_config_value('access_key', [
            'AWS_ACCESS_KEY_ID',
            'H3TM_AWS_ACCESS_KEY_ID'
        ], 'h3tm_aws_access_key');

        $config['secret_key'] = $this->get_config_value('secret_key', [
            'AWS_SECRET_ACCESS_KEY',
            'H3TM_AWS_SECRET_ACCESS_KEY'
        ], 'h3tm_aws_secret_key');

        // Decrypt stored credentials if they exist
        if (!empty($config['access_key']) && !$this->is_env_variable_set($config['access_key'])) {
            $config['access_key'] = $this->decrypt_credential($config['access_key']);
        }

        if (!empty($config['secret_key']) && !$this->is_env_variable_set($config['secret_key'])) {
            $config['secret_key'] = $this->decrypt_credential($config['secret_key']);
        }

        // Cache the configuration
        $this->config_cache = $config;

        // Log configuration status (without sensitive data)
        error_log('H3TM S3 Config: bucket=' . ($config['bucket_name'] ? 'SET' : 'MISSING') .
                 ', region=' . $config['region'] .
                 ', access_key=' . ($config['access_key'] ? 'SET' : 'MISSING') .
                 ', secret_key=' . ($config['secret_key'] ? 'SET' : 'MISSING'));

        return $config;
    }

    /**
     * Get configuration value with fallback priority
     */
    private function get_config_value($type, $env_vars, $option_key, $default = '') {
        // Check environment variables first (highest priority)
        foreach ($env_vars as $env_var) {
            if (defined($env_var) && !empty(constant($env_var))) {
                return constant($env_var);
            }
        }

        // Fallback to WordPress options
        return get_option($option_key, $default);
    }

    /**
     * Check if value is from environment variable
     */
    private function is_env_variable_set($value) {
        $env_vars = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'H3TM_AWS_ACCESS_KEY_ID', 'H3TM_AWS_SECRET_ACCESS_KEY'];

        foreach ($env_vars as $env_var) {
            if (defined($env_var) && constant($env_var) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if S3 is properly configured with enhanced validation
     */
    public function is_configured() {
        $config = $this->get_s3_config();

        $required_fields = ['bucket_name', 'access_key', 'secret_key'];

        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                error_log('H3TM S3 Config: Missing required field: ' . $field);
                return false;
            }
        }

        return true;
    }

    /**
     * Get bucket name with validation
     */
    public function get_bucket_name() {
        $config = $this->get_s3_config();
        return $config['bucket_name'] ?? '';
    }

    /**
     * Get region with validation
     */
    public function get_region() {
        $config = $this->get_s3_config();
        return $config['region'] ?? 'us-east-1';
    }

    /**
     * Get access credentials (for internal use only)
     */
    private function get_credentials() {
        $config = $this->get_s3_config();
        return array(
            'access_key' => $config['access_key'] ?? '',
            'secret_key' => $config['secret_key'] ?? ''
        );
    }

    /**
     * Validate configuration with comprehensive checks
     */
    public function validate_configuration() {
        if ($this->config_validated) {
            return !empty($this->validation_errors) ? $this->validation_errors : true;
        }

        $this->validation_errors = array();
        $config = $this->get_s3_config();

        // Check required fields
        if (empty($config['bucket_name'])) {
            $this->validation_errors[] = 'S3 bucket name is required';
        }

        if (empty($config['access_key'])) {
            $this->validation_errors[] = 'AWS access key is required';
        }

        if (empty($config['secret_key'])) {
            $this->validation_errors[] = 'AWS secret key is required';
        }

        // Validate bucket name format
        if (!empty($config['bucket_name']) && !$this->is_valid_bucket_name($config['bucket_name'])) {
            $this->validation_errors[] = 'Invalid S3 bucket name format';
        }

        // Validate region
        if (!empty($config['region']) && !$this->is_valid_region($config['region'])) {
            $this->validation_errors[] = 'Invalid AWS region specified';
        }

        $this->config_validated = true;

        if (!empty($this->validation_errors)) {
            error_log('H3TM S3 Config Validation Errors: ' . implode(', ', $this->validation_errors));
        }

        return empty($this->validation_errors) ? true : $this->validation_errors;
    }

    /**
     * Validate S3 bucket name format
     */
    private function is_valid_bucket_name($bucket_name) {
        // AWS S3 bucket name rules
        return preg_match('/^[a-z0-9][a-z0-9\-]{1,61}[a-z0-9]$/', $bucket_name) &&
               !preg_match('/\-\-/', $bucket_name) &&
               !preg_match('/^\d+\.\d+\.\d+\.\d+$/', $bucket_name);
    }

    /**
     * Validate AWS region
     */
    private function is_valid_region($region) {
        $valid_regions = [
            'us-east-1', 'us-east-2', 'us-west-1', 'us-west-2',
            'eu-west-1', 'eu-west-2', 'eu-west-3', 'eu-central-1',
            'ap-southeast-1', 'ap-southeast-2', 'ap-northeast-1', 'ap-northeast-2',
            'ap-south-1', 'sa-east-1', 'ca-central-1'
        ];

        return in_array($region, $valid_regions);
    }

    /**
     * Generate presigned URL for direct S3 upload with enhanced error handling
     */
    public function handle_get_presigned_url() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');
        error_log("H3TM S3 Integration: handle_get_presigned_url called successfully (duplicate handler removed)");

        if (!current_user_can('manage_options')) {
            error_log('H3TM S3 Presigned URL Error: Unauthorized access attempt');
            wp_send_json_error('Unauthorized access');
        }

        error_log('H3TM S3 Presigned URL: Starting generation with enhanced validation');

        // Force fresh configuration check for AJAX requests
        $this->config_cache = null;
        $this->config_validated = false;

        // Validate configuration
        $validation_result = $this->validate_configuration();
        if ($validation_result !== true) {
            $error_message = 'S3 configuration errors: ' . implode(', ', $validation_result);
            error_log('H3TM S3 Presigned URL Error: ' . $error_message);
            wp_send_json_error($error_message);
        }

        $config = $this->get_s3_config();

        // Enhanced debug logging (without sensitive data)
        error_log('H3TM S3 Presigned URL: Config status - ' .
                 'bucket=' . ($config['bucket_name'] ? 'SET' : 'MISSING') .
                 ', region=' . $config['region'] .
                 ', access_key=' . ($config['access_key'] ? 'SET' : 'MISSING') .
                 ', secret_key=' . ($config['secret_key'] ? 'SET' : 'MISSING'));

        // Double-check configuration
        if (!$this->is_configured()) {
            error_log('H3TM S3 Presigned URL Error: Configuration validation failed');
            wp_send_json_error('S3 not properly configured. Please check your AWS settings.');
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
     * Generate presigned URL for upload using AWS Signature Version 4 with enhanced validation
     */
    private function generate_presigned_url($s3_key, $file_size) {
        $config = $this->get_s3_config();
        $credentials = $this->get_credentials();

        if (empty($config['bucket_name']) || empty($credentials['access_key']) || empty($credentials['secret_key'])) {
            throw new Exception('Missing required S3 configuration for presigned URL generation');
        }

        $host = $config['bucket_name'] . ".s3." . $config['region'] . ".amazonaws.com";
        $endpoint = "https://" . $host;
        $canonical_uri = '/' . ltrim($s3_key, '/');

        // AWS Signature Version 4
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date . '/' . $config['region'] . '/s3/aws4_request';
        $expires = 3600; // 1 hour

        // Build canonical query string for presigned URL
        $canonical_querystring = http_build_query(array(
            'X-Amz-Algorithm' => $algorithm,
            'X-Amz-Credential' => $credentials['access_key'] . '/' . $credential_scope,
            'X-Amz-Date' => $datetime,
            'X-Amz-Expires' => $expires,
            'X-Amz-SignedHeaders' => 'host'
        ));

        // Create canonical request
        $canonical_headers = "host:" . $host . "\n";
        $signed_headers = 'host';
        $payload_hash = 'UNSIGNED-PAYLOAD';

        $canonical_request = "PUT\n" . $canonical_uri . "\n" . $canonical_querystring . "\n" .
                           $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

        // Create string to sign
        $string_to_sign = $algorithm . "\n" . $datetime . "\n" . $credential_scope . "\n" .
                         hash('sha256', $canonical_request);

        // Calculate signature
        $signing_key = $this->get_signing_key($date, $config['region'], 's3', $credentials['secret_key']);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        // Build final presigned URL
        $presigned_url = $endpoint . $canonical_uri . '?' . $canonical_querystring . '&X-Amz-Signature=' . $signature;

        error_log('H3TM S3: Generated presigned URL for key: ' . $s3_key);
        return $presigned_url;
    }

    /**
     * Download file from S3 to temporary location with enhanced error handling
     */
    private function download_from_s3($s3_key) {
        $config = $this->get_s3_config();

        if (!$this->is_configured()) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/h3-tours/' . basename($s3_key);

        // Ensure directory exists
        $temp_dir = dirname($temp_file);
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $s3_url = "https://" . $config['bucket_name'] . ".s3." . $config['region'] . ".amazonaws.com/" . $s3_key;

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
     * Delete file from S3 with enhanced validation
     */
    private function delete_s3_file($s3_key) {
        $config = $this->get_s3_config();

        if (!$this->is_configured()) {
            return false;
        }

        // Simple DELETE request to S3
        $s3_url = "https://" . $config['bucket_name'] . ".s3." . $config['region'] . ".amazonaws.com/" . $s3_key;

        $response = wp_remote_request($s3_url, array(
            'method' => 'DELETE',
            'timeout' => 30
        ));

        return !is_wp_error($response);
    }

    /**
     * Test S3 connection with comprehensive validation and error reporting
     */
    private function test_s3_connection() {
        try {
            // Force fresh configuration for connection test
            $this->config_cache = null;
            $this->config_validated = false;

            $validation_result = $this->validate_configuration();
            if ($validation_result !== true) {
                throw new Exception('Configuration validation failed: ' . implode(', ', $validation_result));
            }

            $config = $this->get_s3_config();
            $credentials = $this->get_credentials();

            error_log('H3TM S3 Test: Starting connection test');
            error_log('H3TM S3 Test: Bucket=' . $config['bucket_name'] . ', Region=' . $config['region']);
            error_log('H3TM S3 Test: Access Key=' . (strlen($credentials['access_key']) > 4 ? substr($credentials['access_key'], 0, 4) . '***' : 'SET'));

            // Test 1: Check if bucket exists with HEAD request
            $s3_url = "https://" . $config['bucket_name'] . ".s3." . $config['region'] . ".amazonaws.com/";

            $response = wp_remote_head($s3_url, array(
                'timeout' => 10,
                'user-agent' => 'H3TM-WordPress-Plugin/1.5.0'
            ));

            $response_code = wp_remote_retrieve_response_code($response);
            error_log('H3TM S3 Test: HEAD response code: ' . $response_code);

            if (is_wp_error($response)) {
                error_log('H3TM S3 Test Error: ' . $response->get_error_message());
                return false;
            }

            // Test 2: Try to create a test presigned URL
            try {
                $test_key = 'test/' . time() . '.txt';
                $test_url = $this->generate_presigned_url($test_key, 100);
                error_log('H3TM S3 Test: Generated test presigned URL successfully');

                // Test the presigned URL with a small PUT request
                $test_response = wp_remote_request($test_url, array(
                    'method' => 'PUT',
                    'body' => 'test content',
                    'timeout' => 15,
                    'headers' => array(
                        'Content-Type' => 'text/plain'
                    ),
                    'user-agent' => 'H3TM-WordPress-Plugin/' . H3TM_VERSION
                ));

                $put_response_code = wp_remote_retrieve_response_code($test_response);
                error_log('H3TM S3 Test: PUT test response code: ' . $put_response_code);

                if ($put_response_code === 200) {
                    // Clean up test file
                    $this->delete_s3_file($test_key);
                    error_log('H3TM S3 Test: SUCCESS - Full upload test passed');
                    return true;
                } else {
                    error_log('H3TM S3 Test: PUT test failed with code ' . $put_response_code);
                    if (is_wp_error($test_response)) {
                        error_log('H3TM S3 Test PUT Error: ' . $test_response->get_error_message());
                    }
                    return false;
                }

            } catch (Exception $e) {
                error_log('H3TM S3 Test: Presigned URL generation failed: ' . $e->getMessage());
                return false;
            }

        } catch (Exception $e) {
            error_log('H3TM S3 Test: General error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get AWS signing key with explicit secret key parameter
     */
    private function get_signing_key($date, $region, $service, $secret_key) {
        $key = 'AWS4' . $secret_key;
        $key = hash_hmac('sha256', $date, $key, true);
        $key = hash_hmac('sha256', $region, $key, true);
        $key = hash_hmac('sha256', $service, $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);
        return $key;
    }

    /**
     * Encrypt credential for secure storage
     */
    public function encrypt_credential($credential) {
        if (empty($credential)) {
            return '';
        }

        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($credential, 'AES-256-CBC', $this->encryption_key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt credential for usage
     */
    private function decrypt_credential($encrypted_credential) {
        if (empty($encrypted_credential)) {
            return '';
        }

        $data = base64_decode($encrypted_credential);
        if ($data === false || strlen($data) < 16) {
            return $encrypted_credential; // Return as-is if decryption fails (might be plain text)
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : $encrypted_credential;
    }

    /**
     * Get S3 configuration for frontend (safe for client-side)
     */
    public function get_frontend_config() {
        $config = $this->get_s3_config();

        return array(
            'configured' => $this->is_configured(),
            'bucket' => $config['bucket_name'],
            'region' => $config['region'],
            'threshold_mb' => get_option('h3tm_s3_threshold', 100), // Default 100MB threshold
            'validation_errors' => $this->validation_errors
        );
    }

    /**
     * Get configuration status for admin dashboard
     */
    public function get_configuration_status() {
        $config = $this->get_s3_config();
        $validation_result = $this->validate_configuration();

        return array(
            'configured' => $this->is_configured(),
            'credentials' => !empty($config['access_key']) && !empty($config['secret_key']),
            'bucket_name' => $config['bucket_name'],
            'region' => $config['region'],
            'threshold' => get_option('h3tm_s3_threshold', 50 * 1024 * 1024),
            'validation_errors' => is_array($validation_result) ? $validation_result : array(),
            'last_test' => get_option('h3tm_s3_last_test', 'Never')
        );
    }

    /**
     * AJAX handler for configuration validation
     */
    public function handle_validate_s3_config() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Force fresh validation
        $this->config_cache = null;
        $this->config_validated = false;

        $validation_result = $this->validate_configuration();

        if ($validation_result === true) {
            wp_send_json_success('S3 configuration is valid');
        } else {
            wp_send_json_error(array(
                'message' => 'Configuration validation failed',
                'errors' => $validation_result
            ));
        }
    }

    /**
     * AJAX handler for configuration debugging
     */
    public function handle_debug_s3_config() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Force fresh configuration load
        $this->config_cache = null;
        $this->config_validated = false;

        $config = $this->get_s3_config();
        $validation_result = $this->validate_configuration();

        $debug_info = array(
            'configuration_loaded' => $config !== null,
            'bucket_name' => !empty($config['bucket_name']) ? 'SET' : 'MISSING',
            'region' => $config['region'] ?? 'NOT_SET',
            'access_key' => !empty($config['access_key']) ? 'SET' : 'MISSING',
            'secret_key' => !empty($config['secret_key']) ? 'SET' : 'MISSING',
            'validation_result' => $validation_result === true ? 'VALID' : 'INVALID',
            'validation_errors' => is_array($validation_result) ? $validation_result : array(),
            'is_configured' => $this->is_configured(),
            'environment_vars' => array(
                'H3_S3_BUCKET' => defined('H3_S3_BUCKET') ? 'SET' : 'NOT_SET',
                'H3_S3_REGION' => defined('H3_S3_REGION') ? 'SET' : 'NOT_SET',
                'AWS_ACCESS_KEY_ID' => defined('AWS_ACCESS_KEY_ID') ? 'SET' : 'NOT_SET',
                'AWS_SECRET_ACCESS_KEY' => defined('AWS_SECRET_ACCESS_KEY') ? 'SET' : 'NOT_SET'
            ),
            'wordpress_options' => array(
                'h3tm_s3_bucket' => get_option('h3tm_s3_bucket') ? 'SET' : 'NOT_SET',
                'h3tm_s3_region' => get_option('h3tm_s3_region', 'us-east-1'),
                'h3tm_aws_access_key' => get_option('h3tm_aws_access_key') ? 'SET' : 'NOT_SET',
                'h3tm_aws_secret_key' => get_option('h3tm_aws_secret_key') ? 'SET' : 'NOT_SET'
            )
        );

        wp_send_json_success($debug_info);
    }

    /**
     * Clear configuration cache (for debugging)
     */
    public function clear_config_cache() {
        $this->config_cache = null;
        $this->config_validated = false;
        $this->validation_errors = array();
    }
}