<?php
/**
 * H3TM Bulletproof Configuration System
 *
 * Provides rock-solid configuration management that works consistently
 * across all WordPress contexts (admin, AJAX, cron, frontend).
 *
 * @package H3_Tour_Management
 * @since 1.5.5
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Bulletproof_Config {

    /**
     * Configuration cache duration (in seconds)
     */
    const CACHE_DURATION = 1800; // 30 minutes

    /**
     * Configuration version for cache invalidation
     */
    const CONFIG_VERSION = '1.0.0';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * In-memory configuration cache
     */
    private static $memory_cache = null;

    /**
     * Configuration sources priority order
     */
    private static $source_priority = [
        'constants',     // WordPress defined constants (highest priority)
        'environment',   // Server environment variables
        'options',       // WordPress database options
        'defaults'       // Hardcoded defaults (lowest priority)
    ];

    /**
     * Debug mode flag
     */
    private $debug_mode = false;

    /**
     * Configuration loading trace for debugging
     */
    private $loading_trace = [];

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
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;

        // Initialize configuration immediately
        $this->load_configuration();

        // Set up hooks for cache invalidation
        add_action('update_option', [$this, 'invalidate_cache_on_option_update'], 10, 3);
        add_action('delete_option', [$this, 'invalidate_cache_on_option_delete'], 10, 1);
    }

    /**
     * Load configuration with bulletproof context-aware approach
     */
    private function load_configuration() {
        // Check in-memory cache first (fastest)
        if (self::$memory_cache !== null) {
            return self::$memory_cache;
        }

        // Try WordPress transient cache (persistent across requests)
        $cache_key = $this->get_cache_key();
        $cached_config = get_transient($cache_key);

        if ($cached_config !== false && is_array($cached_config)) {
            self::$memory_cache = $cached_config;
            $this->trace('Configuration loaded from transient cache');
            return self::$memory_cache;
        }

        // Build configuration from scratch
        self::$memory_cache = $this->build_configuration();

        // Cache the configuration with error handling
        if (is_array(self::$memory_cache) && !empty(self::$memory_cache)) {
            set_transient($cache_key, self::$memory_cache, self::CACHE_DURATION);
            $this->trace('Configuration built and cached');
        }

        return self::$memory_cache;
    }

    /**
     * Build configuration from all available sources
     */
    private function build_configuration() {
        $config = [];
        $metadata = [
            'loaded_at' => current_time('mysql'),
            'context' => $this->get_current_context(),
            'sources_used' => [],
            'loading_trace' => []
        ];

        // S3 Configuration with bulletproof source priority
        $config['s3'] = $this->build_s3_config($metadata);

        // Environment Configuration
        $config['environment'] = $this->build_environment_config($metadata);

        // Upload Configuration
        $config['uploads'] = $this->build_uploads_config($metadata);

        // Security Configuration
        $config['security'] = $this->build_security_config($metadata);

        // Add metadata
        $config['_metadata'] = $metadata;
        $config['_metadata']['loading_trace'] = $this->loading_trace;

        return $config;
    }

    /**
     * Build S3 configuration with comprehensive source checking
     */
    private function build_s3_config(&$metadata) {
        $config = [];

        // Bucket Name - check all possible sources
        $bucket_sources = [
            'constants' => ['H3_S3_BUCKET', 'H3TM_S3_BUCKET', 'AWS_S3_BUCKET'],
            'environment' => ['H3_S3_BUCKET', 'AWS_S3_BUCKET'],
            'options' => ['h3tm_s3_bucket', 'h3tm_s3_bucket_name'],
            'defaults' => ['']
        ];
        $config['bucket_name'] = $this->get_config_value('bucket_name', $bucket_sources, $metadata);

        // Region - with fallback chain
        $region_sources = [
            'constants' => ['H3_S3_REGION', 'H3TM_S3_REGION', 'AWS_DEFAULT_REGION', 'AWS_REGION'],
            'environment' => ['AWS_DEFAULT_REGION', 'AWS_REGION', 'H3_S3_REGION'],
            'options' => ['h3tm_s3_region', 'h3tm_aws_region'],
            'defaults' => ['us-east-1']
        ];
        $config['region'] = $this->get_config_value('region', $region_sources, $metadata);

        // Access Key - secure sources first
        $access_key_sources = [
            'constants' => ['AWS_ACCESS_KEY_ID', 'H3TM_AWS_ACCESS_KEY_ID'],
            'environment' => ['AWS_ACCESS_KEY_ID'],
            'options' => ['h3tm_aws_access_key'],
            'defaults' => ['']
        ];
        $config['access_key'] = $this->get_config_value('access_key', $access_key_sources, $metadata);

        // Secret Key - secure sources first
        $secret_key_sources = [
            'constants' => ['AWS_SECRET_ACCESS_KEY', 'H3TM_AWS_SECRET_ACCESS_KEY'],
            'environment' => ['AWS_SECRET_ACCESS_KEY'],
            'options' => ['h3tm_aws_secret_key'],
            'defaults' => ['']
        ];
        $config['secret_key'] = $this->get_config_value('secret_key', $secret_key_sources, $metadata);

        // Additional S3 settings
        $config['enabled'] = $this->get_boolean_option('h3tm_s3_enabled', false);
        $config['threshold'] = $this->get_numeric_option('h3tm_s3_threshold', 50 * 1024 * 1024); // 50MB default
        $config['endpoint'] = $this->get_config_value('endpoint', [
            'constants' => ['H3TM_S3_ENDPOINT'],
            'options' => ['h3tm_s3_endpoint'],
            'defaults' => [null]
        ], $metadata);

        return $config;
    }

    /**
     * Build environment configuration
     */
    private function build_environment_config(&$metadata) {
        $environment = $this->detect_environment();

        // Use environment-specific config if available
        if (class_exists('H3TM_Environment_Config')) {
            try {
                $env_config = H3TM_Environment_Config::get_config();
                $this->trace('Environment config loaded from H3TM_Environment_Config');
                return array_merge(['type' => $environment], $env_config);
            } catch (Exception $e) {
                $this->trace('Failed to load H3TM_Environment_Config: ' . $e->getMessage());
            }
        }

        // Fallback environment config
        return [
            'type' => $environment,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'ssl_verify' => $environment === 'production'
        ];
    }

    /**
     * Build uploads configuration
     */
    private function build_uploads_config(&$metadata) {
        return [
            'max_file_size' => $this->get_numeric_option('h3tm_max_file_size', 1073741824), // 1GB
            'allowed_types' => ['zip'],
            'chunk_size' => $this->get_numeric_option('h3tm_chunk_size', 10 * 1024 * 1024), // 10MB
            'timeout' => $this->get_numeric_option('h3tm_upload_timeout', 300) // 5 minutes
        ];
    }

    /**
     * Build security configuration
     */
    private function build_security_config(&$metadata) {
        return [
            'encryption_enabled' => true,
            'rate_limiting' => [
                's3_operations' => [
                    'requests' => $this->get_numeric_option('h3tm_s3_rate_limit', 100),
                    'window' => 3600 // 1 hour
                ]
            ],
            'ssl_verify' => $this->get_boolean_option('h3tm_ssl_verify', true)
        ];
    }

    /**
     * Get configuration value with source priority and comprehensive fallback
     */
    private function get_config_value($key, $sources, &$metadata) {
        foreach (self::$source_priority as $source_type) {
            if (!isset($sources[$source_type])) {
                continue;
            }

            foreach ($sources[$source_type] as $source_key) {
                $value = null;

                switch ($source_type) {
                    case 'constants':
                        if (defined($source_key) && !empty(constant($source_key))) {
                            $value = constant($source_key);
                        }
                        break;

                    case 'environment':
                        // Check both $_SERVER and getenv()
                        if (!empty($_SERVER[$source_key])) {
                            $value = $_SERVER[$source_key];
                        } elseif (function_exists('getenv') && getenv($source_key) !== false) {
                            $value = getenv($source_key);
                        }
                        break;

                    case 'options':
                        // Use direct database query for AJAX context reliability
                        $value = $this->get_option_with_fallback($source_key);
                        break;

                    case 'defaults':
                        $value = $source_key; // The source_key IS the default value
                        break;
                }

                if ($value !== null && $value !== '') {
                    $metadata['sources_used'][] = "$key:$source_type:$source_key";
                    $this->trace("Config '$key' loaded from $source_type: $source_key");
                    return $value;
                }
            }
        }

        // No value found anywhere
        $metadata['sources_used'][] = "$key:none:fallback";
        $this->trace("Config '$key' not found in any source, using empty fallback");
        return '';
    }

    /**
     * Get WordPress option with database fallback for AJAX reliability
     */
    private function get_option_with_fallback($option_name, $default = '') {
        // Try standard WordPress get_option first
        $value = get_option($option_name, null);

        if ($value !== null) {
            return $value;
        }

        // Fallback to direct database query for AJAX context
        global $wpdb;

        try {
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option_name
            ));

            if ($result !== null) {
                $this->trace("Option '$option_name' loaded via direct database query");
                return maybe_unserialize($result);
            }
        } catch (Exception $e) {
            $this->trace("Database fallback failed for '$option_name': " . $e->getMessage());
        }

        return $default;
    }

    /**
     * Get boolean option with proper type casting
     */
    private function get_boolean_option($option_name, $default = false) {
        $value = $this->get_option_with_fallback($option_name, $default);

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on']);
        }

        return (bool) $value;
    }

    /**
     * Get numeric option with validation
     */
    private function get_numeric_option($option_name, $default = 0) {
        $value = $this->get_option_with_fallback($option_name, $default);

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Get complete configuration
     */
    public function get_configuration() {
        return $this->load_configuration();
    }

    /**
     * Get specific configuration section
     */
    public function get_section($section, $default = []) {
        $config = $this->get_configuration();
        return isset($config[$section]) ? $config[$section] : $default;
    }

    /**
     * Get specific configuration value using dot notation
     */
    public function get($path, $default = null) {
        $config = $this->get_configuration();
        $keys = explode('.', $path);
        $value = $config;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Check if S3 is fully configured
     */
    public function is_s3_configured() {
        $s3_config = $this->get_section('s3');

        $required_fields = ['bucket_name', 'access_key', 'secret_key'];

        foreach ($required_fields as $field) {
            if (empty($s3_config[$field])) {
                $this->trace("S3 not configured: missing $field");
                return false;
            }
        }

        return true;
    }

    /**
     * Validate S3 configuration
     */
    public function validate_s3_configuration() {
        $s3_config = $this->get_section('s3');
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'tested_at' => current_time('mysql')
        ];

        // Required fields validation
        $required_fields = [
            'bucket_name' => 'S3 bucket name is required',
            'access_key' => 'AWS access key is required',
            'secret_key' => 'AWS secret key is required'
        ];

        foreach ($required_fields as $field => $error_message) {
            if (empty($s3_config[$field])) {
                $validation['errors'][] = $error_message;
                $validation['valid'] = false;
            }
        }

        // Bucket name format validation
        if (!empty($s3_config['bucket_name'])) {
            if (!$this->is_valid_bucket_name($s3_config['bucket_name'])) {
                $validation['errors'][] = 'Invalid S3 bucket name format';
                $validation['valid'] = false;
            }
        }

        // Region validation
        if (!empty($s3_config['region'])) {
            if (!$this->is_valid_region($s3_config['region'])) {
                $validation['errors'][] = 'Invalid AWS region specified';
                $validation['valid'] = false;
            }
        }

        // Security warnings
        $environment = $this->get('environment.type', 'unknown');
        if ($environment === 'production' && !$this->get('environment.ssl_verify', true)) {
            $validation['warnings'][] = 'SSL verification should be enabled in production';
        }

        return $validation;
    }

    /**
     * Get configuration status for debugging
     */
    public function get_debug_info() {
        $config = $this->get_configuration();
        $s3_config = $this->get_section('s3', []);

        return [
            'configuration_loaded' => !empty($config),
            'cache_key' => $this->get_cache_key(),
            'context' => $this->get_current_context(),
            'memory_cached' => self::$memory_cache !== null,
            'sources_used' => $config['_metadata']['sources_used'] ?? [],
            'loading_trace' => $this->loading_trace,
            's3_configured' => $this->is_s3_configured(),
            'environment_detection' => [
                'detected_type' => $this->detect_environment(),
                'wp_environment_type' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'unknown',
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG
            ],
            'constants_status' => [
                'H3_S3_BUCKET' => defined('H3_S3_BUCKET') ? 'SET' : 'NOT_SET',
                'AWS_ACCESS_KEY_ID' => defined('AWS_ACCESS_KEY_ID') ? 'SET' : 'NOT_SET',
                'AWS_SECRET_ACCESS_KEY' => defined('AWS_SECRET_ACCESS_KEY') ? 'SET' : 'NOT_SET'
            ],
            'options_status' => [
                'h3tm_s3_bucket' => !empty($this->get_option_with_fallback('h3tm_s3_bucket')) ? 'SET' : 'NOT_SET',
                'h3tm_aws_access_key' => !empty($this->get_option_with_fallback('h3tm_aws_access_key')) ? 'SET' : 'NOT_SET',
                'h3tm_aws_secret_key' => !empty($this->get_option_with_fallback('h3tm_aws_secret_key')) ? 'SET' : 'NOT_SET'
            ],
            's3_values_preview' => [
                'bucket_name' => !empty($s3_config['bucket_name']) ? 'SET' : 'MISSING',
                'region' => $s3_config['region'] ?? 'NOT_SET',
                'access_key' => !empty($s3_config['access_key']) ? 'SET' : 'MISSING',
                'secret_key' => !empty($s3_config['secret_key']) ? 'SET' : 'MISSING',
                'enabled' => $s3_config['enabled'] ?? false
            ]
        ];
    }

    /**
     * Clear all caches
     */
    public function clear_cache() {
        self::$memory_cache = null;
        $this->loading_trace = [];

        // Clear WordPress transients
        $cache_patterns = [
            'h3tm_config_*',
            'h3tm_bulletproof_config_*'
        ];

        global $wpdb;
        foreach ($cache_patterns as $pattern) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 OR option_name LIKE %s",
                '_transient_' . str_replace('*', '%', $pattern),
                '_transient_timeout_' . str_replace('*', '%', $pattern)
            ));
        }

        $this->trace('Configuration cache cleared');
    }

    /**
     * Force configuration reload
     */
    public function reload() {
        $this->clear_cache();
        return $this->load_configuration();
    }

    /**
     * Test configuration connectivity
     */
    public function test_connection() {
        if (!$this->is_s3_configured()) {
            return [
                'success' => false,
                'message' => 'S3 configuration incomplete',
                'details' => $this->validate_s3_configuration()
            ];
        }

        $s3_config = $this->get_section('s3');
        $host = $s3_config['bucket_name'] . '.s3.' . $s3_config['region'] . '.amazonaws.com';
        $url = 'https://' . $host . '/';

        try {
            $response = wp_remote_head($url, [
                'timeout' => 10,
                'user-agent' => 'H3TM-WordPress-Plugin/' . (defined('H3TM_VERSION') ? H3TM_VERSION : '1.0.0'),
                'sslverify' => $this->get('security.ssl_verify', true)
            ]);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => 'Connection failed: ' . $response->get_error_message(),
                    'details' => ['error_code' => $response->get_error_code()]
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);

            return [
                'success' => true,
                'message' => 'Connection successful (HTTP ' . $response_code . ')',
                'details' => [
                    'host' => $host,
                    'response_code' => $response_code,
                    'tested_at' => current_time('mysql')
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'details' => ['exception' => get_class($e)]
            ];
        }
    }

    /**
     * Detect current environment
     */
    private function detect_environment() {
        // Check for explicit environment setting
        if (defined('H3TM_ENVIRONMENT')) {
            return H3TM_ENVIRONMENT;
        }

        if (function_exists('wp_get_environment_type')) {
            return wp_get_environment_type();
        }

        // Auto-detect based on domain and debug settings
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (
            strpos($host, 'localhost') !== false ||
            strpos($host, '.local') !== false ||
            strpos($host, '.test') !== false ||
            strpos($host, '.dev') !== false ||
            (defined('WP_DEBUG') && WP_DEBUG === true)
        ) {
            return 'development';
        }

        if (
            strpos($host, 'staging') !== false ||
            strpos($host, 'dev-') !== false ||
            strpos($host, 'test-') !== false
        ) {
            return 'staging';
        }

        return 'production';
    }

    /**
     * Get current WordPress context
     */
    private function get_current_context() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return 'ajax';
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return 'cron';
        }

        if (is_admin()) {
            return 'admin';
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'rest_api';
        }

        return 'frontend';
    }

    /**
     * Generate cache key
     */
    private function get_cache_key() {
        $factors = [
            self::CONFIG_VERSION,
            $this->get_current_context(),
            defined('H3TM_VERSION') ? H3TM_VERSION : '1.0.0',
            // Include option values that affect config to invalidate cache when they change
            md5(serialize([
                $this->get_option_with_fallback('h3tm_s3_bucket'),
                $this->get_option_with_fallback('h3tm_s3_region'),
                defined('AWS_ACCESS_KEY_ID') ? 'env_set' : 'env_unset'
            ]))
        ];

        return 'h3tm_bulletproof_config_' . md5(implode('|', $factors));
    }

    /**
     * Validate S3 bucket name format
     */
    private function is_valid_bucket_name($bucket_name) {
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
            'us-east-1', 'us-east-2', 'us-west-1', 'us-west-2',
            'eu-west-1', 'eu-west-2', 'eu-west-3', 'eu-central-1', 'eu-north-1',
            'ap-southeast-1', 'ap-southeast-2', 'ap-northeast-1', 'ap-northeast-2',
            'ap-south-1', 'sa-east-1', 'ca-central-1'
        ];

        return in_array($region, $valid_regions);
    }

    /**
     * Add trace message for debugging
     */
    private function trace($message) {
        if ($this->debug_mode) {
            $this->loading_trace[] = '[' . date('Y-m-d H:i:s') . '] ' . $message;

            // Also log to error log in debug mode
            error_log('H3TM_Bulletproof_Config: ' . $message);
        }
    }

    /**
     * Invalidate cache when relevant options are updated
     */
    public function invalidate_cache_on_option_update($option_name, $old_value, $new_value) {
        $s3_options = ['h3tm_s3_bucket', 'h3tm_s3_region', 'h3tm_aws_access_key', 'h3tm_aws_secret_key', 'h3tm_s3_enabled'];

        if (in_array($option_name, $s3_options)) {
            $this->clear_cache();
            $this->trace("Cache invalidated due to option update: $option_name");
        }
    }

    /**
     * Invalidate cache when relevant options are deleted
     */
    public function invalidate_cache_on_option_delete($option_name) {
        $s3_options = ['h3tm_s3_bucket', 'h3tm_s3_region', 'h3tm_aws_access_key', 'h3tm_aws_secret_key', 'h3tm_s3_enabled'];

        if (in_array($option_name, $s3_options)) {
            $this->clear_cache();
            $this->trace("Cache invalidated due to option deletion: $option_name");
        }
    }

    /**
     * Get safe configuration for frontend (excludes credentials)
     */
    public function get_frontend_safe_config() {
        $s3_config = $this->get_section('s3', []);

        return [
            'configured' => $this->is_s3_configured(),
            'enabled' => $s3_config['enabled'] ?? false,
            'bucket_name' => $s3_config['bucket_name'] ?? '',
            'region' => $s3_config['region'] ?? '',
            'threshold_mb' => round(($s3_config['threshold'] ?? 0) / 1024 / 1024, 2),
            'environment' => $this->get('environment.type', 'unknown')
        ];
    }
}