<?php
/**
 * H3TM Configuration AJAX Handlers
 *
 * AJAX endpoints for testing and debugging the bulletproof configuration
 * system across different WordPress contexts.
 *
 * @package H3_Tour_Management
 * @since 1.5.5
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Config_AJAX_Handlers {

    /**
     * Config adapter instance
     */
    private $config_adapter;

    /**
     * Config validator instance
     */
    private $config_validator;

    /**
     * Constructor
     */
    public function __construct() {
        $this->config_adapter = H3TM_Config_Adapter::getInstance();
        $this->config_validator = new H3TM_Config_Validator();

        // Register AJAX handlers
        add_action('wp_ajax_h3tm_test_bulletproof_config', [$this, 'handle_test_bulletproof_config']);
        add_action('wp_ajax_h3tm_validate_bulletproof_config', [$this, 'handle_validate_bulletproof_config']);
        add_action('wp_ajax_h3tm_debug_bulletproof_config', [$this, 'handle_debug_bulletproof_config']);
        add_action('wp_ajax_h3tm_test_ajax_context_config', [$this, 'handle_test_ajax_context_config']);
        add_action('wp_ajax_h3tm_clear_config_cache', [$this, 'handle_clear_config_cache']);
        add_action('wp_ajax_h3tm_export_config_report', [$this, 'handle_export_config_report']);

        // Test AJAX registration
        add_action('wp_ajax_h3tm_test_config_ajax_handler', [$this, 'handle_test_config_ajax_handler']);

        error_log('H3TM Config AJAX Handlers: All handlers registered successfully');
    }

    /**
     * Test bulletproof configuration system
     */
    public function handle_test_bulletproof_config() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        try {
            error_log('H3TM Config AJAX: Testing bulletproof configuration system');

            // Force fresh configuration load
            $this->config_adapter->clear_cache();

            // Test basic configuration access
            $config = $this->config_adapter->get_s3_config_legacy();

            $test_results = [
                'ajax_context' => defined('DOING_AJAX') && DOING_AJAX,
                'configuration_loaded' => !empty($config),
                'is_configured' => $this->config_adapter->is_s3_configured(),
                'bucket_name' => $this->config_adapter->get_bucket_name(),
                'region' => $this->config_adapter->get_region(),
                'source' => $config['source'] ?? 'unknown',
                'context' => $config['context'] ?? 'unknown',
                'loaded_at' => $config['loaded_at'] ?? 'unknown',
                'credentials_available' => [
                    'access_key' => !empty($config['access_key']),
                    'secret_key' => !empty($config['secret_key'])
                ]
            ];

            // Test connection if configured
            if ($this->config_adapter->is_s3_configured()) {
                $connection_test = $this->config_adapter->test_connection();
                $test_results['connection_test'] = $connection_test;
            } else {
                $test_results['connection_test'] = [
                    'success' => false,
                    'message' => 'Configuration incomplete - cannot test connection'
                ];
            }

            error_log('H3TM Config AJAX: Test completed - configured=' .
                     ($test_results['is_configured'] ? 'yes' : 'no') .
                     ', bucket=' . $test_results['bucket_name']);

            wp_send_json_success($test_results);

        } catch (Exception $e) {
            error_log('H3TM Config AJAX Error: ' . $e->getMessage());
            wp_send_json_error('Configuration test failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate bulletproof configuration
     */
    public function handle_validate_bulletproof_config() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        try {
            error_log('H3TM Config AJAX: Running configuration validation');

            $validation_type = sanitize_text_field($_POST['validation_type'] ?? 'quick');

            if ($validation_type === 'comprehensive') {
                $report = $this->config_validator->run_comprehensive_validation();
            } else {
                $report = $this->config_validator->run_quick_validation();
            }

            error_log('H3TM Config AJAX: Validation completed - status=' . $report['summary']['overall_status'] .
                     ', passed=' . $report['summary']['passed'] . '/' . $report['summary']['total_tests']);

            wp_send_json_success($report);

        } catch (Exception $e) {
            error_log('H3TM Config AJAX Validation Error: ' . $e->getMessage());
            wp_send_json_error('Configuration validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Debug bulletproof configuration
     */
    public function handle_debug_bulletproof_config() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        try {
            error_log('H3TM Config AJAX: Gathering debug information');

            // Force fresh configuration load for debugging
            $this->config_adapter->clear_cache();

            $debug_info = $this->config_adapter->get_debug_info();
            $validation = $this->config_adapter->validate_s3_configuration();

            $debug_response = [
                'debug_info' => $debug_info,
                'validation' => $validation,
                'wordpress_context' => [
                    'doing_ajax' => defined('DOING_AJAX') && DOING_AJAX,
                    'is_admin' => is_admin(),
                    'current_user_can_manage' => current_user_can('manage_options'),
                    'wp_version' => get_bloginfo('version'),
                    'plugin_version' => defined('H3TM_VERSION') ? H3TM_VERSION : 'unknown'
                ],
                'server_environment' => [
                    'php_version' => PHP_VERSION,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                    'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown'
                ]
            ];

            wp_send_json_success($debug_response);

        } catch (Exception $e) {
            error_log('H3TM Config AJAX Debug Error: ' . $e->getMessage());
            wp_send_json_error('Configuration debugging failed: ' . $e->getMessage());
        }
    }

    /**
     * Test AJAX context configuration specifically
     */
    public function handle_test_ajax_context_config() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        try {
            error_log('H3TM Config AJAX: Testing AJAX context specifically');

            $ajax_test_results = $this->config_validator->test_ajax_context();

            // Additional AJAX-specific checks
            $ajax_test_results['ajax_handlers_working'] = true;
            $ajax_test_results['nonce_verification'] = wp_verify_nonce($_POST['nonce'], 'h3tm_ajax_nonce');
            $ajax_test_results['user_capabilities'] = current_user_can('manage_options');

            // Test that we can access WordPress functions in AJAX context
            $ajax_test_results['wordpress_functions'] = [
                'get_option_works' => function_exists('get_option'),
                'wp_remote_get_works' => function_exists('wp_remote_get'),
                'current_time_works' => function_exists('current_time'),
                'database_accessible' => !empty($GLOBALS['wpdb'])
            ];

            // Test specific S3 configuration access
            $s3_config = $this->config_adapter->get_s3_config_legacy();
            $ajax_test_results['s3_config_access'] = [
                'legacy_config_loaded' => !empty($s3_config),
                'bucket_name' => $s3_config['bucket_name'] ?? 'NOT_LOADED',
                'configured_flag' => $s3_config['configured'] ?? false
            ];

            error_log('H3TM Config AJAX: AJAX context test completed - bucket=' .
                     $ajax_test_results['s3_config_access']['bucket_name']);

            wp_send_json_success($ajax_test_results);

        } catch (Exception $e) {
            error_log('H3TM Config AJAX Context Test Error: ' . $e->getMessage());
            wp_send_json_error('AJAX context test failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear configuration cache
     */
    public function handle_clear_config_cache() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        try {
            error_log('H3TM Config AJAX: Clearing configuration cache');

            $this->config_adapter->clear_cache();

            // Force reload to verify cache clearing worked
            $reloaded_config = $this->config_adapter->reload();

            $result = [
                'cache_cleared' => true,
                'config_reloaded' => !empty($reloaded_config),
                'bucket_name' => $reloaded_config['bucket_name'] ?? 'not_loaded',
                'loaded_at' => $reloaded_config['loaded_at'] ?? 'unknown'
            ];

            wp_send_json_success($result);

        } catch (Exception $e) {
            error_log('H3TM Config AJAX Cache Clear Error: ' . $e->getMessage());
            wp_send_json_error('Cache clearing failed: ' . $e->getMessage());
        }
    }

    /**
     * Export configuration validation report
     */
    public function handle_export_config_report() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        try {
            error_log('H3TM Config AJAX: Exporting configuration report');

            // Run comprehensive validation
            $report = $this->config_validator->run_comprehensive_validation();

            // Export to file
            $filename = $this->config_validator->export_report($report);

            $result = [
                'report_exported' => true,
                'filename' => basename($filename),
                'filepath' => $filename,
                'report_summary' => $report['summary']
            ];

            wp_send_json_success($result);

        } catch (Exception $e) {
            error_log('H3TM Config AJAX Export Error: ' . $e->getMessage());
            wp_send_json_error('Report export failed: ' . $e->getMessage());
        }
    }

    /**
     * Test AJAX handler registration
     */
    public function handle_test_config_ajax_handler() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        error_log('H3TM Config AJAX: Test handler called successfully');

        wp_send_json_success([
            'message' => 'Configuration AJAX handlers are working correctly',
            'timestamp' => current_time('mysql'),
            'context' => 'ajax',
            'doing_ajax' => defined('DOING_AJAX') && DOING_AJAX
        ]);
    }

    /**
     * Get AJAX handler status
     */
    public function get_handler_status() {
        return [
            'handlers_registered' => true,
            'total_handlers' => 6,
            'handlers' => [
                'h3tm_test_bulletproof_config',
                'h3tm_validate_bulletproof_config',
                'h3tm_debug_bulletproof_config',
                'h3tm_test_ajax_context_config',
                'h3tm_clear_config_cache',
                'h3tm_export_config_report'
            ]
        ];
    }
}