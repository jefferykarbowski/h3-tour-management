<?php
/**
 * Environment-specific configuration for H3 Tour Management AWS integration
 *
 * This file provides secure configuration management across different environments
 * (development, staging, production) with proper security controls.
 *
 * @package H3_Tour_Management
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Environment_Config {

    /**
     * Environment types
     */
    const ENV_DEVELOPMENT = 'development';
    const ENV_STAGING = 'staging';
    const ENV_PRODUCTION = 'production';

    /**
     * Configuration cache duration (in seconds)
     */
    const CONFIG_CACHE_DURATION = 3600; // 1 hour

    /**
     * Get current environment
     *
     * @return string Current environment
     */
    public static function get_environment() {
        // Check for explicit environment setting
        if (defined('H3TM_ENVIRONMENT')) {
            return H3TM_ENVIRONMENT;
        }

        // Auto-detect based on various indicators
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $server_name = $_SERVER['SERVER_NAME'] ?? '';

        // Development environment detection
        if (
            strpos($host, 'localhost') !== false ||
            strpos($host, '.local') !== false ||
            strpos($host, '.test') !== false ||
            strpos($host, '.dev') !== false ||
            strpos($server_name, 'localhost') !== false ||
            (defined('WP_DEBUG') && WP_DEBUG === true)
        ) {
            return self::ENV_DEVELOPMENT;
        }

        // Staging environment detection
        if (
            strpos($host, 'staging') !== false ||
            strpos($host, 'dev-') !== false ||
            strpos($host, 'test-') !== false ||
            strpos($server_name, 'staging') !== false
        ) {
            return self::ENV_STAGING;
        }

        // Default to production
        return self::ENV_PRODUCTION;
    }

    /**
     * Get environment-specific configuration
     *
     * @return array Configuration array
     */
    public static function get_config() {
        $cache_key = 'h3tm_env_config_' . self::get_environment();
        $cached_config = get_transient($cache_key);

        if ($cached_config !== false) {
            return $cached_config;
        }

        $environment = self::get_environment();
        $config = array();

        switch ($environment) {
            case self::ENV_DEVELOPMENT:
                $config = self::get_development_config();
                break;

            case self::ENV_STAGING:
                $config = self::get_staging_config();
                break;

            case self::ENV_PRODUCTION:
                $config = self::get_production_config();
                break;

            default:
                $config = self::get_production_config(); // Safe default
                break;
        }

        // Cache the configuration
        set_transient($cache_key, $config, self::CONFIG_CACHE_DURATION);

        return $config;
    }

    /**
     * Development environment configuration
     *
     * @return array Development configuration
     */
    private static function get_development_config() {
        return array(
            'environment' => self::ENV_DEVELOPMENT,

            // AWS Configuration
            'aws' => array(
                'region' => defined('H3TM_AWS_REGION') ? H3TM_AWS_REGION : 'us-west-2',
                'bucket' => defined('H3TM_AWS_BUCKET') ? H3TM_AWS_BUCKET : 'h3-tours-dev',
                'verify_ssl' => defined('H3TM_VERIFY_SSL') ? H3TM_VERIFY_SSL : false,
                'endpoint' => null, // Use default AWS endpoints
            ),

            // Security Configuration
            'security' => array(
                'encryption_enabled' => true,
                'strict_validation' => false, // More lenient in dev
                'debug_logging' => true,
                'rate_limiting' => array(
                    's3_operations' => array(
                        'requests' => 1000,
                        'window' => 3600
                    ),
                    'presigned_urls' => array(
                        'requests' => 500,
                        'window' => 3600
                    )
                )
            ),

            // File Upload Configuration
            'uploads' => array(
                'max_file_size' => 1073741824, // 1GB
                'allowed_types' => array('zip'),
                'virus_scanning' => false, // Disabled in dev
                'content_validation' => true,
                'presigned_url_expiry' => 3600 // 1 hour
            ),

            // Logging Configuration
            'logging' => array(
                'level' => 'debug',
                'retention_days' => 7,
                'max_entries' => 1000,
                'email_alerts' => false,
                'log_to_file' => true,
                'log_to_database' => true
            ),

            // Backup Configuration
            'backup' => array(
                'enabled' => false,
                'retention_days' => 7
            ),

            // Monitoring Configuration
            'monitoring' => array(
                'cloudwatch_enabled' => false,
                'metrics_collection' => false,
                'performance_tracking' => true
            )
        );
    }

    /**
     * Staging environment configuration
     *
     * @return array Staging configuration
     */
    private static function get_staging_config() {
        return array(
            'environment' => self::ENV_STAGING,

            // AWS Configuration
            'aws' => array(
                'region' => defined('H3TM_AWS_REGION') ? H3TM_AWS_REGION : 'us-west-2',
                'bucket' => defined('H3TM_AWS_BUCKET') ? H3TM_AWS_BUCKET : 'h3-tours-staging',
                'verify_ssl' => true,
                'endpoint' => null,
            ),

            // Security Configuration
            'security' => array(
                'encryption_enabled' => true,
                'strict_validation' => true,
                'debug_logging' => false,
                'rate_limiting' => array(
                    's3_operations' => array(
                        'requests' => 500,
                        'window' => 3600
                    ),
                    'presigned_urls' => array(
                        'requests' => 200,
                        'window' => 3600
                    )
                )
            ),

            // File Upload Configuration
            'uploads' => array(
                'max_file_size' => 1073741824, // 1GB
                'allowed_types' => array('zip'),
                'virus_scanning' => true,
                'content_validation' => true,
                'presigned_url_expiry' => 3600 // 1 hour
            ),

            // Logging Configuration
            'logging' => array(
                'level' => 'info',
                'retention_days' => 30,
                'max_entries' => 5000,
                'email_alerts' => true,
                'log_to_file' => true,
                'log_to_database' => true
            ),

            // Backup Configuration
            'backup' => array(
                'enabled' => true,
                'retention_days' => 14
            ),

            // Monitoring Configuration
            'monitoring' => array(
                'cloudwatch_enabled' => true,
                'metrics_collection' => true,
                'performance_tracking' => true
            )
        );
    }

    /**
     * Production environment configuration
     *
     * @return array Production configuration
     */
    private static function get_production_config() {
        return array(
            'environment' => self::ENV_PRODUCTION,

            // AWS Configuration
            'aws' => array(
                'region' => defined('H3TM_AWS_REGION') ? H3TM_AWS_REGION : 'us-west-2',
                'bucket' => defined('H3TM_AWS_BUCKET') ? H3TM_AWS_BUCKET : 'h3-tours-prod',
                'verify_ssl' => true,
                'endpoint' => null,
            ),

            // Security Configuration
            'security' => array(
                'encryption_enabled' => true,
                'strict_validation' => true,
                'debug_logging' => false,
                'rate_limiting' => array(
                    's3_operations' => array(
                        'requests' => 100,
                        'window' => 3600
                    ),
                    'presigned_urls' => array(
                        'requests' => 50,
                        'window' => 3600
                    )
                )
            ),

            // File Upload Configuration
            'uploads' => array(
                'max_file_size' => 524288000, // 500MB (reduced for production)
                'allowed_types' => array('zip'),
                'virus_scanning' => true,
                'content_validation' => true,
                'presigned_url_expiry' => 1800 // 30 minutes (reduced for security)
            ),

            // Logging Configuration
            'logging' => array(
                'level' => 'warning', // Only warnings and errors
                'retention_days' => 90,
                'max_entries' => 10000,
                'email_alerts' => true,
                'log_to_file' => false, // Use database only
                'log_to_database' => true
            ),

            // Backup Configuration
            'backup' => array(
                'enabled' => true,
                'retention_days' => 90
            ),

            // Monitoring Configuration
            'monitoring' => array(
                'cloudwatch_enabled' => true,
                'metrics_collection' => true,
                'performance_tracking' => true
            )
        );
    }

    /**
     * Get specific configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) {
        $config = self::get_config();
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Check if current environment matches
     *
     * @param string $environment Environment to check
     * @return bool True if matches current environment
     */
    public static function is_environment($environment) {
        return self::get_environment() === $environment;
    }

    /**
     * Check if development environment
     *
     * @return bool True if development
     */
    public static function is_development() {
        return self::is_environment(self::ENV_DEVELOPMENT);
    }

    /**
     * Check if staging environment
     *
     * @return bool True if staging
     */
    public static function is_staging() {
        return self::is_environment(self::ENV_STAGING);
    }

    /**
     * Check if production environment
     *
     * @return bool True if production
     */
    public static function is_production() {
        return self::is_environment(self::ENV_PRODUCTION);
    }

    /**
     * Get AWS configuration for current environment
     *
     * @return array AWS configuration
     */
    public static function get_aws_config() {
        return self::get('aws', array());
    }

    /**
     * Get security configuration for current environment
     *
     * @return array Security configuration
     */
    public static function get_security_config() {
        return self::get('security', array());
    }

    /**
     * Get upload configuration for current environment
     *
     * @return array Upload configuration
     */
    public static function get_upload_config() {
        return self::get('uploads', array());
    }

    /**
     * Get logging configuration for current environment
     *
     * @return array Logging configuration
     */
    public static function get_logging_config() {
        return self::get('logging', array());
    }

    /**
     * Validate environment configuration
     *
     * @return array Validation result
     */
    public static function validate_config() {
        $config = self::get_config();
        $errors = array();
        $warnings = array();

        // Validate AWS configuration
        if (empty($config['aws']['region'])) {
            $errors[] = 'AWS region not configured';
        }

        if (empty($config['aws']['bucket'])) {
            $errors[] = 'AWS S3 bucket not configured';
        }

        // Validate security configuration
        if (self::is_production()) {
            if (!$config['aws']['verify_ssl']) {
                $errors[] = 'SSL verification must be enabled in production';
            }

            if ($config['security']['debug_logging']) {
                $warnings[] = 'Debug logging is enabled in production';
            }

            if ($config['uploads']['max_file_size'] > 1073741824) {
                $warnings[] = 'Large file uploads may impact performance in production';
            }
        }

        // Validate logging configuration
        if (!$config['logging']['log_to_database'] && !$config['logging']['log_to_file']) {
            $errors[] = 'No logging destination configured';
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'environment' => $config['environment']
        );
    }

    /**
     * Clear configuration cache
     */
    public static function clear_cache() {
        $environments = array(self::ENV_DEVELOPMENT, self::ENV_STAGING, self::ENV_PRODUCTION);

        foreach ($environments as $env) {
            delete_transient('h3tm_env_config_' . $env);
        }
    }

    /**
     * Get configuration summary for admin display
     *
     * @return array Configuration summary
     */
    public static function get_config_summary() {
        $config = self::get_config();

        return array(
            'environment' => $config['environment'],
            'aws_region' => $config['aws']['region'] ?? 'Not configured',
            'aws_bucket' => $config['aws']['bucket'] ?? 'Not configured',
            'ssl_verification' => $config['aws']['verify_ssl'] ? 'Enabled' : 'Disabled',
            'max_file_size' => size_format($config['uploads']['max_file_size'] ?? 0),
            'logging_level' => ucfirst($config['logging']['level'] ?? 'unknown'),
            'rate_limiting' => $config['security']['rate_limiting']['s3_operations']['requests'] ?? 0,
            'backup_enabled' => $config['backup']['enabled'] ? 'Yes' : 'No',
            'monitoring_enabled' => $config['monitoring']['cloudwatch_enabled'] ? 'Yes' : 'No'
        );
    }

    /**
     * Override configuration value (for testing)
     *
     * @param string $key Configuration key
     * @param mixed $value New value
     */
    public static function override_config($key, $value) {
        if (!self::is_development()) {
            return false; // Only allow overrides in development
        }

        $cache_key = 'h3tm_env_config_' . self::get_environment();
        $config = get_transient($cache_key);

        if ($config === false) {
            $config = self::get_config();
        }

        $keys = explode('.', $key);
        $current = &$config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = array();
                }
                $current = &$current[$k];
            }
        }

        set_transient($cache_key, $config, self::CONFIG_CACHE_DURATION);
        return true;
    }
}