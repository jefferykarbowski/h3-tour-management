<?php
/**
 * H3TM Configuration Adapter
 *
 * Provides seamless integration between the bulletproof configuration system
 * and existing code, ensuring backward compatibility while upgrading reliability.
 *
 * @package H3_Tour_Management
 * @since 1.5.5
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Config_Adapter {

    /**
     * Bulletproof config instance
     */
    private $bulletproof_config;

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Configuration compatibility cache
     */
    private $compatibility_cache = null;

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
     * Constructor
     */
    private function __construct() {
        $this->bulletproof_config = H3TM_Bulletproof_Config::getInstance();
    }

    /**
     * Get S3 configuration in legacy format for backward compatibility
     */
    public function get_s3_config_legacy() {
        if ($this->compatibility_cache !== null) {
            return $this->compatibility_cache;
        }

        $s3_config = $this->bulletproof_config->get_section('s3', []);

        // Convert to legacy format expected by existing code
        $legacy_config = [
            // Primary configuration
            'bucket_name' => $s3_config['bucket_name'] ?? '',
            'bucket' => $s3_config['bucket_name'] ?? '', // Alternative key used in some places
            'region' => $s3_config['region'] ?? 'us-east-1',
            'access_key' => $s3_config['access_key'] ?? '',
            'secret_key' => $s3_config['secret_key'] ?? '',

            // Status flags
            'configured' => $this->bulletproof_config->is_s3_configured(),
            'enabled' => $s3_config['enabled'] ?? false,

            // Settings
            'threshold' => $s3_config['threshold'] ?? (50 * 1024 * 1024),
            'threshold_mb' => round(($s3_config['threshold'] ?? 0) / 1024 / 1024, 2),
            'endpoint' => $s3_config['endpoint'] ?? null,

            // Environment settings
            'verify_ssl' => $this->bulletproof_config->get('security.ssl_verify', true),

            // Metadata for debugging
            'source' => $this->get_configuration_source(),
            'loaded_at' => $this->bulletproof_config->get('_metadata.loaded_at', current_time('mysql')),
            'context' => $this->bulletproof_config->get('_metadata.context', 'unknown')
        ];

        // Cache for this request
        $this->compatibility_cache = $legacy_config;

        return $legacy_config;
    }

    /**
     * Get configuration source information for debugging
     */
    private function get_configuration_source() {
        $sources_used = $this->bulletproof_config->get('_metadata.sources_used', []);
        $primary_sources = [];

        foreach ($sources_used as $source_info) {
            if (strpos($source_info, 'bucket_name:') === 0 ||
                strpos($source_info, 'access_key:') === 0 ||
                strpos($source_info, 'secret_key:') === 0) {

                $parts = explode(':', $source_info);
                if (isset($parts[1]) && !in_array($parts[1], $primary_sources)) {
                    $primary_sources[] = $parts[1];
                }
            }
        }

        return empty($primary_sources) ? 'none' : implode(',', $primary_sources);
    }

    /**
     * Get S3 credentials (for secure internal use)
     */
    public function get_s3_credentials() {
        $s3_config = $this->bulletproof_config->get_section('s3', []);

        return [
            'access_key' => $s3_config['access_key'] ?? '',
            'secret_key' => $s3_config['secret_key'] ?? ''
        ];
    }

    /**
     * Check if S3 is configured (proxy method)
     */
    public function is_s3_configured() {
        return $this->bulletproof_config->is_s3_configured();
    }

    /**
     * Validate S3 configuration (proxy method)
     */
    public function validate_s3_configuration() {
        return $this->bulletproof_config->validate_s3_configuration();
    }

    /**
     * Get specific S3 configuration value with fallback
     */
    public function get_s3_value($key, $default = null) {
        return $this->bulletproof_config->get("s3.$key", $default);
    }

    /**
     * Get bucket name
     */
    public function get_bucket_name() {
        return $this->get_s3_value('bucket_name', '');
    }

    /**
     * Get region
     */
    public function get_region() {
        return $this->get_s3_value('region', 'us-east-1');
    }

    /**
     * Get threshold in bytes
     */
    public function get_threshold() {
        return $this->get_s3_value('threshold', 50 * 1024 * 1024);
    }

    /**
     * Check if S3 is enabled
     */
    public function is_s3_enabled() {
        return $this->get_s3_value('enabled', false);
    }

    /**
     * Get configuration status for admin display
     */
    public function get_configuration_status() {
        $s3_config = $this->get_s3_config_legacy();
        $validation = $this->validate_s3_configuration();

        return [
            'configured' => $s3_config['configured'],
            'credentials' => !empty($s3_config['access_key']) && !empty($s3_config['secret_key']),
            'bucket_name' => $s3_config['bucket_name'],
            'region' => $s3_config['region'],
            'threshold' => $s3_config['threshold'],
            'enabled' => $s3_config['enabled'],
            'validation_errors' => $validation['valid'] ? [] : $validation['errors'],
            'last_test' => get_option('h3tm_s3_last_test', 'Never'),
            'source' => $s3_config['source'],
            'loaded_at' => $s3_config['loaded_at']
        ];
    }

    /**
     * Get frontend-safe configuration
     */
    public function get_frontend_config() {
        return $this->bulletproof_config->get_frontend_safe_config();
    }

    /**
     * Test S3 connection
     */
    public function test_connection() {
        return $this->bulletproof_config->test_connection();
    }

    /**
     * Get debug information
     */
    public function get_debug_info() {
        $bulletproof_debug = $this->bulletproof_config->get_debug_info();
        $legacy_config = $this->get_s3_config_legacy();

        return [
            'bulletproof_config' => $bulletproof_debug,
            'legacy_compatibility' => [
                'cache_loaded' => $this->compatibility_cache !== null,
                'bucket_name' => $legacy_config['bucket_name'] ?: 'MISSING',
                'region' => $legacy_config['region'],
                'configured' => $legacy_config['configured'],
                'enabled' => $legacy_config['enabled'],
                'source' => $legacy_config['source']
            ],
            'comparison' => [
                'bulletproof_s3_configured' => $bulletproof_debug['s3_configured'],
                'legacy_configured' => $legacy_config['configured'],
                'match' => $bulletproof_debug['s3_configured'] === $legacy_config['configured']
            ]
        ];
    }

    /**
     * Clear configuration cache
     */
    public function clear_cache() {
        $this->compatibility_cache = null;
        $this->bulletproof_config->clear_cache();
    }

    /**
     * Force configuration reload
     */
    public function reload() {
        $this->clear_cache();
        return $this->get_s3_config_legacy();
    }

    /**
     * Update configuration option (with cache invalidation)
     */
    public function update_option($key, $value) {
        $option_map = [
            'bucket_name' => 'h3tm_s3_bucket',
            'region' => 'h3tm_s3_region',
            'access_key' => 'h3tm_aws_access_key',
            'secret_key' => 'h3tm_aws_secret_key',
            'enabled' => 'h3tm_s3_enabled',
            'threshold' => 'h3tm_s3_threshold'
        ];

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
     * Provide method aliases for backward compatibility
     */
    public function get_configuration() {
        return $this->get_s3_config_legacy();
    }

    /**
     * Get specific configuration value (legacy method signature)
     */
    public function get($key, $default = null) {
        $config = $this->get_s3_config_legacy();
        return array_key_exists($key, $config) ? $config[$key] : $default;
    }

    /**
     * Magic method to handle legacy method calls
     */
    public function __call($method_name, $arguments) {
        // Handle legacy method patterns
        if (strpos($method_name, 'get_') === 0) {
            $config_key = substr($method_name, 4); // Remove 'get_' prefix
            $default = isset($arguments[0]) ? $arguments[0] : null;
            return $this->get($config_key, $default);
        }

        // Forward unknown methods to bulletproof config if they exist
        if (method_exists($this->bulletproof_config, $method_name)) {
            return call_user_func_array([$this->bulletproof_config, $method_name], $arguments);
        }

        throw new BadMethodCallException("Method $method_name does not exist");
    }

    /**
     * Magic property access for legacy code compatibility
     */
    public function __get($property_name) {
        $config = $this->get_s3_config_legacy();

        // Handle common property name mappings
        $property_map = [
            'bucket' => 'bucket_name',
            'aws_region' => 'region'
        ];

        $key = isset($property_map[$property_name]) ? $property_map[$property_name] : $property_name;

        return isset($config[$key]) ? $config[$key] : null;
    }

    /**
     * Check if configuration property exists
     */
    public function __isset($property_name) {
        $config = $this->get_s3_config_legacy();
        return isset($config[$property_name]);
    }
}