<?php
/**
 * H3TM S3 Configuration Manager
 *
 * Provides centralized, robust configuration management for S3 integration
 * with comprehensive validation, caching, and error handling.
 *
 * @package H3_Tour_Management
 * @since 1.5.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_S3_Config_Manager {

    /**
     * Configuration cache duration (in seconds)
     */
    const CACHE_DURATION = 3600; // 1 hour

    /**
     * Validation cache duration (in seconds)
     */
    const VALIDATION_CACHE_DURATION = 1800; // 30 minutes

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Configuration cache
     */
    private $config_cache = null;

    /**
     * Validation cache
     */
    private $validation_cache = null;

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
     * Private constructor to enforce singleton
     */
    private function __construct() {
        // Load configuration on first access
        $this->load_configuration();
    }

    /**
     * Load S3 configuration from all available sources
     */
    private function load_configuration() {
        $cache_key = 'h3tm_s3_config_' . $this->get_cache_version();

        // Try to get from cache first
        $this->config_cache = get_transient($cache_key);

        if ($this->config_cache === false) {
            $this->config_cache = $this->build_configuration();
            set_transient($cache_key, $this->config_cache, self::CACHE_DURATION);
        }
    }

    /**
     * Build configuration from available sources
     */
    private function build_configuration() {
        $config = array();

        // Environment-specific configuration
        $env_config = $this->get_environment_config();

        // Priority 1: Environment variables (most secure)
        $config['bucket_name'] = $this->get_config_value('bucket', [
            'H3_S3_BUCKET',
            'H3TM_S3_BUCKET',
            'AWS_S3_BUCKET'
        ], 'h3tm_s3_bucket', $env_config['aws']['bucket'] ?? '');

        $config['region'] = $this->get_config_value('region', [
            'H3_S3_REGION',
            'H3TM_S3_REGION',
            'AWS_DEFAULT_REGION',
            'AWS_REGION'
        ], 'h3tm_s3_region', $env_config['aws']['region'] ?? 'us-east-1');

        $config['access_key'] = $this->get_config_value('access_key', [
            'AWS_ACCESS_KEY_ID',
            'H3TM_AWS_ACCESS_KEY_ID'
        ], 'h3tm_aws_access_key');

        $config['secret_key'] = $this->get_config_value('secret_key', [
            'AWS_SECRET_ACCESS_KEY',
            'H3TM_AWS_SECRET_ACCESS_KEY'
        ], 'h3tm_aws_secret_key');

        // Additional configuration
        $config['enabled'] = (bool) get_option('h3tm_s3_enabled', false);
        $config['threshold'] = (int) get_option('h3tm_s3_threshold', 50 * 1024 * 1024); // 50MB default
        $config['endpoint'] = $env_config['aws']['endpoint'] ?? null;
        $config['verify_ssl'] = $env_config['aws']['verify_ssl'] ?? true;

        // Configuration metadata
        $config['loaded_at'] = current_time('mysql');
        $config['source'] = $this->determine_config_source($config);

        return $config;
    }

    /**
     * Get environment-specific configuration
     */
    private function get_environment_config() {
        if (class_exists('H3TM_Environment_Config')) {
            return H3TM_Environment_Config::get_config();
        }

        // Fallback configuration
        return array(
            'aws' => array(
                'region' => 'us-east-1',
                'bucket' => '',
                'verify_ssl' => true,
                'endpoint' => null
            )
        );
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
     * Determine the primary source of configuration
     */
    private function determine_config_source($config) {
        $sources = array();

        if (defined('H3_S3_BUCKET') || defined('AWS_ACCESS_KEY_ID')) {
            $sources[] = 'environment_variables';
        }

        if (get_option('h3tm_s3_bucket') || get_option('h3tm_aws_access_key')) {
            $sources[] = 'wordpress_options';
        }

        if (class_exists('H3TM_Environment_Config')) {
            $sources[] = 'environment_config_class';
        }

        return empty($sources) ? 'none' : implode(',', $sources);
    }

    /**
     * Get cache version for cache invalidation
     */
    private function get_cache_version() {
        return hash('crc32',
            get_option('h3tm_s3_bucket', '') .
            get_option('h3tm_s3_region', '') .
            (defined('AWS_ACCESS_KEY_ID') ? 'env_set' : 'env_unset') .
            H3TM_VERSION
        );
    }

    /**
     * Get complete configuration array
     */
    public function get_configuration() {
        if ($this->config_cache === null) {
            $this->load_configuration();
        }

        return $this->config_cache;
    }

    /**
     * Get specific configuration value
     */
    public function get($key, $default = null) {
        $config = $this->get_configuration();
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }

    /**
     * Check if S3 is properly configured
     */
    public function is_configured() {
        $config = $this->get_configuration();

        $required = ['bucket_name', 'access_key', 'secret_key'];

        foreach ($required as $field) {
            if (empty($config[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate configuration with comprehensive checks
     */
    public function validate_configuration($force_refresh = false) {
        $validation_cache_key = 'h3tm_s3_validation_' . $this->get_cache_version();

        if (!$force_refresh && $this->validation_cache !== null) {
            return $this->validation_cache;
        }

        $cached_validation = get_transient($validation_cache_key);
        if (!$force_refresh && $cached_validation !== false) {
            $this->validation_cache = $cached_validation;
            return $this->validation_cache;
        }

        $config = $this->get_configuration();
        $validation_result = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
            'checks_performed' => array(),
            'validated_at' => current_time('mysql')
        );

        // Required field checks
        $required_fields = [
            'bucket_name' => 'S3 bucket name is required',
            'access_key' => 'AWS access key is required',
            'secret_key' => 'AWS secret key is required'
        ];

        foreach ($required_fields as $field => $error_message) {
            $validation_result['checks_performed'][] = "required_field_{$field}";
            if (empty($config[$field])) {
                $validation_result['errors'][] = $error_message;
                $validation_result['valid'] = false;
            }
        }

        // Bucket name format validation
        if (!empty($config['bucket_name'])) {
            $validation_result['checks_performed'][] = 'bucket_name_format';
            if (!$this->is_valid_bucket_name($config['bucket_name'])) {
                $validation_result['errors'][] = 'Invalid S3 bucket name format';
                $validation_result['valid'] = false;
            }
        }

        // Region validation
        if (!empty($config['region'])) {
            $validation_result['checks_performed'][] = 'region_format';
            if (!$this->is_valid_region($config['region'])) {
                $validation_result['errors'][] = 'Invalid AWS region specified';
                $validation_result['valid'] = false;
            }
        }

        // Configuration source validation
        $validation_result['checks_performed'][] = 'config_source';
        if ($config['source'] === 'none') {
            $validation_result['warnings'][] = 'No configuration source detected';
        }

        // Environment-specific validations
        if (class_exists('H3TM_Environment_Config')) {
            if (H3TM_Environment_Config::is_production()) {
                $validation_result['checks_performed'][] = 'production_security';
                if (!$config['verify_ssl']) {
                    $validation_result['errors'][] = 'SSL verification must be enabled in production';
                    $validation_result['valid'] = false;
                }
            }
        }

        // Cache the validation result
        $this->validation_cache = $validation_result;
        set_transient($validation_cache_key, $validation_result, self::VALIDATION_CACHE_DURATION);

        return $validation_result;
    }

    /**
     * Validate S3 bucket name format
     */
    private function is_valid_bucket_name($bucket_name) {
        // AWS S3 bucket naming rules
        return preg_match('/^[a-z0-9][a-z0-9\-]{1,61}[a-z0-9]$/', $bucket_name) &&
               !preg_match('/\-\-/', $bucket_name) &&
               !preg_match('/^\d+\.\d+\.\d+\.\d+$/', $bucket_name) &&
               !preg_match('/^xn--/', $bucket_name) &&
               !preg_match('/^sthree-/', $bucket_name);
    }

    /**
     * Validate AWS region
     */
    private function is_valid_region($region) {
        $valid_regions = [
            // US Regions
            'us-east-1', 'us-east-2', 'us-west-1', 'us-west-2',
            // Europe Regions
            'eu-west-1', 'eu-west-2', 'eu-west-3', 'eu-central-1', 'eu-north-1', 'eu-south-1',
            // Asia Pacific Regions
            'ap-southeast-1', 'ap-southeast-2', 'ap-northeast-1', 'ap-northeast-2', 'ap-northeast-3',
            'ap-south-1', 'ap-east-1',
            // Other Regions
            'sa-east-1', 'ca-central-1', 'me-south-1', 'af-south-1'
        ];

        return in_array($region, $valid_regions);
    }

    /**
     * Get configuration status for admin display
     */
    public function get_status() {
        $config = $this->get_configuration();
        $validation = $this->validate_configuration();

        return array(
            'configured' => $this->is_configured(),
            'valid' => $validation['valid'],
            'source' => $config['source'],
            'loaded_at' => $config['loaded_at'],
            'validated_at' => $validation['validated_at'],
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'checks_performed' => $validation['checks_performed'],
            'bucket_name' => $config['bucket_name'] ?? 'Not configured',
            'region' => $config['region'] ?? 'Not configured',
            'enabled' => $config['enabled'] ?? false,
            'threshold_mb' => round(($config['threshold'] ?? 0) / 1024 / 1024, 2)
        );
    }

    /**
     * Get safe configuration for frontend use (no credentials)
     */
    public function get_frontend_safe_config() {
        $config = $this->get_configuration();
        $validation = $this->validate_configuration();

        return array(
            'configured' => $this->is_configured(),
            'valid' => $validation['valid'],
            'enabled' => $config['enabled'] ?? false,
            'bucket_name' => $config['bucket_name'] ?? '',
            'region' => $config['region'] ?? '',
            'threshold_mb' => round(($config['threshold'] ?? 0) / 1024 / 1024, 2),
            'errors' => $validation['errors'] ?? array()
        );
    }

    /**
     * Clear all caches (for testing and debugging)
     */
    public function clear_cache() {
        $this->config_cache = null;
        $this->validation_cache = null;

        // Clear WordPress transients
        delete_transient('h3tm_s3_config_' . $this->get_cache_version());
        delete_transient('h3tm_s3_validation_' . $this->get_cache_version());

        // Clear all possible cache versions
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_h3tm_s3_config_%'
             OR option_name LIKE '_transient_timeout_h3tm_s3_config_%'
             OR option_name LIKE '_transient_h3tm_s3_validation_%'
             OR option_name LIKE '_transient_timeout_h3tm_s3_validation_%'"
        );
    }

    /**
     * Test configuration connectivity
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'Configuration is incomplete',
                'details' => $this->validate_configuration()
            );
        }

        try {
            $config = $this->get_configuration();

            // Basic DNS/connectivity test
            $host = $config['bucket_name'] . '.s3.' . $config['region'] . '.amazonaws.com';
            $url = 'https://' . $host . '/';

            $response = wp_remote_head($url, array(
                'timeout' => 10,
                'user-agent' => 'H3TM-WordPress-Plugin/' . H3TM_VERSION,
                'sslverify' => $config['verify_ssl']
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Connection failed: ' . $response->get_error_message(),
                    'details' => array('host' => $host, 'error' => $response->get_error_code())
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);

            return array(
                'success' => true,
                'message' => 'Connection successful (HTTP ' . $response_code . ')',
                'details' => array(
                    'host' => $host,
                    'response_code' => $response_code,
                    'tested_at' => current_time('mysql')
                )
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'details' => array('exception' => get_class($e))
            );
        }
    }

    /**
     * Update configuration option
     */
    public function update_option($key, $value) {
        $option_map = array(
            'bucket_name' => 'h3tm_s3_bucket',
            'region' => 'h3tm_s3_region',
            'access_key' => 'h3tm_aws_access_key',
            'secret_key' => 'h3tm_aws_secret_key',
            'enabled' => 'h3tm_s3_enabled',
            'threshold' => 'h3tm_s3_threshold'
        );

        if (!array_key_exists($key, $option_map)) {
            return false;
        }

        $result = update_option($option_map[$key], $value);

        if ($result) {
            // Clear cache to force reload
            $this->clear_cache();
        }

        return $result;
    }

    /**
     * Get debug information
     */
    public function get_debug_info() {
        $config = $this->get_configuration();
        $validation = $this->validate_configuration();
        $status = $this->get_status();

        return array(
            'configuration' => array(
                'loaded' => $config !== null,
                'source' => $config['source'] ?? 'none',
                'loaded_at' => $config['loaded_at'] ?? 'never',
                'cache_version' => $this->get_cache_version()
            ),
            'validation' => array(
                'valid' => $validation['valid'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'checks_performed' => $validation['checks_performed'],
                'validated_at' => $validation['validated_at']
            ),
            'environment' => array(
                'h3_s3_bucket' => defined('H3_S3_BUCKET') ? 'SET' : 'NOT_SET',
                'aws_access_key_id' => defined('AWS_ACCESS_KEY_ID') ? 'SET' : 'NOT_SET',
                'aws_secret_access_key' => defined('AWS_SECRET_ACCESS_KEY') ? 'SET' : 'NOT_SET',
                'wp_environment_type' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'unknown'
            ),
            'wordpress_options' => array(
                'h3tm_s3_bucket' => get_option('h3tm_s3_bucket') ? 'SET' : 'NOT_SET',
                'h3tm_s3_region' => get_option('h3tm_s3_region', 'NOT_SET'),
                'h3tm_aws_access_key' => get_option('h3tm_aws_access_key') ? 'SET' : 'NOT_SET',
                'h3tm_aws_secret_key' => get_option('h3tm_aws_secret_key') ? 'SET' : 'NOT_SET',
                'h3tm_s3_enabled' => get_option('h3tm_s3_enabled') ? 'YES' : 'NO'
            ),
            'status' => $status
        );
    }
}