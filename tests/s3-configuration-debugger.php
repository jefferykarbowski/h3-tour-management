<?php
/**
 * S3 Configuration Debugger and Diagnostic Utility
 *
 * Comprehensive debugging tool for H3 Tour Management S3 integration
 * configuration issues. Provides detailed diagnostics and step-by-step
 * troubleshooting guidance.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/h3-tour-management.php';
}

class H3TM_S3_Configuration_Debugger {

    private $debug_results = [];
    private $config_trace = [];
    private $recommendations = [];
    private $severity_levels = ['info', 'warning', 'error', 'critical'];

    public function __construct() {
        error_log('H3TM S3 Configuration Debugger: Initializing diagnostic utility');
    }

    /**
     * Run comprehensive S3 configuration diagnostics
     */
    public function run_full_diagnostics() {
        $this->debug_results = [];
        $this->config_trace = [];
        $this->recommendations = [];

        error_log('H3TM S3 Configuration Debugger: Starting full diagnostics');

        // Phase 1: Environment Analysis
        $this->analyze_environment();

        // Phase 2: Configuration Detection
        $this->detect_configuration_sources();

        // Phase 3: Configuration Validation
        $this->validate_configuration();

        // Phase 4: Class Instantiation Testing
        $this->test_class_instantiation();

        // Phase 5: AJAX Context Analysis
        $this->analyze_ajax_context();

        // Phase 6: Network Connectivity Testing
        $this->test_network_connectivity();

        // Phase 7: AWS Authentication Testing
        $this->test_aws_authentication();

        // Phase 8: Cross-Context Consistency Check
        $this->check_cross_context_consistency();

        return $this->generate_diagnostic_report();
    }

    /**
     * Analyze environment and system requirements
     */
    private function analyze_environment() {
        $this->add_debug_section('Environment Analysis', 'info');

        // WordPress Environment
        $wp_info = [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'allow_url_fopen' => ini_get('allow_url_fopen') ? 'enabled' : 'disabled',
            'curl_enabled' => function_exists('curl_init') ? 'enabled' : 'disabled',
            'openssl_enabled' => extension_loaded('openssl') ? 'enabled' : 'disabled',
            'zip_extension' => extension_loaded('zip') ? 'enabled' : 'disabled'
        ];

        $this->add_debug_entry('WordPress Info', $wp_info, 'info');

        // Check critical requirements
        $requirements = [
            'curl_available' => function_exists('curl_init'),
            'openssl_available' => extension_loaded('openssl'),
            'zip_available' => extension_loaded('zip'),
            'sufficient_memory' => $this->parse_memory_limit($wp_info['memory_limit']) >= 128,
            'sufficient_execution_time' => (int)$wp_info['max_execution_time'] >= 60 || $wp_info['max_execution_time'] === '0'
        ];

        $this->add_debug_entry('Requirements Check', $requirements, 'info');

        // Check for requirement issues
        foreach ($requirements as $requirement => $met) {
            if (!$met) {
                $this->add_recommendation("Fix requirement: $requirement", 'error');
            }
        }

        // Plugin Environment
        $plugin_info = [
            'plugin_active' => is_plugin_active(plugin_basename(dirname(__DIR__) . '/h3-tour-management.php')),
            'plugin_version' => defined('H3TM_VERSION') ? H3TM_VERSION : 'unknown',
            'plugin_dir' => defined('H3TM_PLUGIN_DIR') ? H3TM_PLUGIN_DIR : 'undefined',
            'required_classes' => [
                'H3TM_S3_Integration' => class_exists('H3TM_S3_Integration'),
                'H3TM_Tour_Manager' => class_exists('H3TM_Tour_Manager'),
                'H3TM_Pantheon_Helper' => class_exists('H3TM_Pantheon_Helper')
            ]
        ];

        $this->add_debug_entry('Plugin Environment', $plugin_info, 'info');

        // Check for missing classes
        foreach ($plugin_info['required_classes'] as $class => $exists) {
            if (!$exists) {
                $this->add_recommendation("Missing required class: $class", 'error');
            }
        }
    }

    /**
     * Detect all configuration sources
     */
    private function detect_configuration_sources() {
        $this->add_debug_section('Configuration Source Detection', 'info');

        // Environment Variables
        $env_config = [
            'H3_S3_BUCKET' => [
                'defined' => defined('H3_S3_BUCKET'),
                'value' => defined('H3_S3_BUCKET') ? H3_S3_BUCKET : null,
                'empty' => defined('H3_S3_BUCKET') ? empty(H3_S3_BUCKET) : true,
                'source' => 'environment'
            ],
            'H3_S3_REGION' => [
                'defined' => defined('H3_S3_REGION'),
                'value' => defined('H3_S3_REGION') ? H3_S3_REGION : 'us-east-1',
                'empty' => defined('H3_S3_REGION') ? empty(H3_S3_REGION) : true,
                'source' => 'environment'
            ],
            'AWS_ACCESS_KEY_ID' => [
                'defined' => defined('AWS_ACCESS_KEY_ID'),
                'value' => defined('AWS_ACCESS_KEY_ID') ? $this->mask_credential(AWS_ACCESS_KEY_ID) : null,
                'empty' => defined('AWS_ACCESS_KEY_ID') ? empty(AWS_ACCESS_KEY_ID) : true,
                'source' => 'environment'
            ],
            'AWS_SECRET_ACCESS_KEY' => [
                'defined' => defined('AWS_SECRET_ACCESS_KEY'),
                'value' => defined('AWS_SECRET_ACCESS_KEY') ? 'SET' : null,
                'empty' => defined('AWS_SECRET_ACCESS_KEY') ? empty(AWS_SECRET_ACCESS_KEY) : true,
                'source' => 'environment'
            ]
        ];

        $this->add_debug_entry('Environment Variables', $env_config, 'info');
        $this->config_trace['environment'] = $env_config;

        // Database Options
        $db_config = [
            'h3tm_s3_bucket' => [
                'value' => get_option('h3tm_s3_bucket', ''),
                'empty' => empty(get_option('h3tm_s3_bucket', '')),
                'source' => 'database'
            ],
            'h3tm_s3_region' => [
                'value' => get_option('h3tm_s3_region', 'us-east-1'),
                'empty' => empty(get_option('h3tm_s3_region', '')),
                'source' => 'database'
            ],
            'h3tm_aws_access_key' => [
                'value' => get_option('h3tm_aws_access_key', '') ? $this->mask_credential(get_option('h3tm_aws_access_key', '')) : '',
                'empty' => empty(get_option('h3tm_aws_access_key', '')),
                'source' => 'database'
            ],
            'h3tm_aws_secret_key' => [
                'value' => get_option('h3tm_aws_secret_key', '') ? 'SET' : '',
                'empty' => empty(get_option('h3tm_aws_secret_key', '')),
                'source' => 'database'
            ]
        ];

        $this->add_debug_entry('Database Options', $db_config, 'info');
        $this->config_trace['database'] = $db_config;

        // Configuration Priority Analysis
        $priority_analysis = $this->analyze_configuration_priority($env_config, $db_config);
        $this->add_debug_entry('Configuration Priority', $priority_analysis, 'info');
    }

    /**
     * Validate configuration values
     */
    private function validate_configuration() {
        $this->add_debug_section('Configuration Validation', 'info');

        // Get final resolved configuration
        $resolved_config = $this->resolve_final_configuration();
        $this->add_debug_entry('Resolved Configuration', $resolved_config, 'info');

        // Validate each component
        $validation_results = [];

        // Bucket Name Validation
        $bucket_validation = $this->validate_bucket_name($resolved_config['bucket']);
        $validation_results['bucket'] = $bucket_validation;
        $this->add_debug_entry('Bucket Validation', $bucket_validation,
            $bucket_validation['valid'] ? 'info' : 'error');

        // Region Validation
        $region_validation = $this->validate_region($resolved_config['region']);
        $validation_results['region'] = $region_validation;
        $this->add_debug_entry('Region Validation', $region_validation,
            $region_validation['valid'] ? 'info' : 'warning');

        // Access Key Validation
        $access_key_validation = $this->validate_access_key($resolved_config['access_key']);
        $validation_results['access_key'] = $access_key_validation;
        $this->add_debug_entry('Access Key Validation', $access_key_validation,
            $access_key_validation['valid'] ? 'info' : 'error');

        // Secret Key Validation
        $secret_key_validation = $this->validate_secret_key($resolved_config['secret_key']);
        $validation_results['secret_key'] = $secret_key_validation;
        $this->add_debug_entry('Secret Key Validation', $secret_key_validation,
            $secret_key_validation['valid'] ? 'info' : 'error');

        // Overall Configuration Status
        $overall_valid = array_reduce($validation_results, function($carry, $result) {
            return $carry && $result['valid'];
        }, true);

        $this->add_debug_entry('Overall Configuration Valid', $overall_valid,
            $overall_valid ? 'info' : 'error');

        if (!$overall_valid) {
            $this->add_recommendation('Fix configuration validation errors above', 'critical');
        }

        return $validation_results;
    }

    /**
     * Test class instantiation in different contexts
     */
    private function test_class_instantiation() {
        $this->add_debug_section('Class Instantiation Testing', 'info');

        // Test S3 Integration Class
        $s3_test = $this->test_s3_integration_instantiation();
        $this->add_debug_entry('S3 Integration Class Test', $s3_test,
            $s3_test['success'] ? 'info' : 'error');

        // Test Tour Manager Class
        $tour_test = $this->test_tour_manager_instantiation();
        $this->add_debug_entry('Tour Manager Class Test', $tour_test,
            $tour_test['success'] ? 'info' : 'error');

        // Test Configuration Methods
        if ($s3_test['success']) {
            $config_methods_test = $this->test_configuration_methods($s3_test['instance']);
            $this->add_debug_entry('Configuration Methods Test', $config_methods_test,
                $config_methods_test['all_working'] ? 'info' : 'warning');
        }
    }

    /**
     * Analyze AJAX context behavior
     */
    private function analyze_ajax_context() {
        $this->add_debug_section('AJAX Context Analysis', 'info');

        // Current Context Detection
        $current_context = $this->detect_current_context();
        $this->add_debug_entry('Current Context', $current_context, 'info');

        // AJAX Handler Registration Check
        $ajax_handlers = $this->check_ajax_handler_registration();
        $this->add_debug_entry('AJAX Handler Registration', $ajax_handlers,
            $ajax_handlers['all_registered'] ? 'info' : 'error');

        // Simulate AJAX Context
        $ajax_simulation = $this->simulate_ajax_context_configuration();
        $this->add_debug_entry('AJAX Context Simulation', $ajax_simulation,
            $ajax_simulation['configuration_consistent'] ? 'info' : 'critical');

        if (!$ajax_simulation['configuration_consistent']) {
            $this->add_recommendation('CRITICAL: Configuration inconsistent in AJAX context', 'critical');
        }
    }

    /**
     * Test network connectivity to AWS S3
     */
    private function test_network_connectivity() {
        $this->add_debug_section('Network Connectivity Testing', 'info');

        // Basic connectivity test
        $connectivity_test = $this->test_basic_s3_connectivity();
        $this->add_debug_entry('Basic S3 Connectivity', $connectivity_test,
            $connectivity_test['can_connect'] ? 'info' : 'error');

        // DNS resolution test
        $dns_test = $this->test_dns_resolution();
        $this->add_debug_entry('DNS Resolution Test', $dns_test,
            $dns_test['resolved'] ? 'info' : 'error');

        // SSL/TLS test
        $ssl_test = $this->test_ssl_connectivity();
        $this->add_debug_entry('SSL/TLS Connectivity', $ssl_test,
            $ssl_test['ssl_working'] ? 'info' : 'error');
    }

    /**
     * Test AWS authentication
     */
    private function test_aws_authentication() {
        $this->add_debug_section('AWS Authentication Testing', 'info');

        $resolved_config = $this->resolve_final_configuration();

        if (!$this->is_configuration_complete($resolved_config)) {
            $this->add_debug_entry('Authentication Test Skipped',
                'Configuration incomplete - cannot test authentication', 'warning');
            return;
        }

        // Test S3 Connection
        $s3_connection_test = $this->test_s3_connection_with_credentials($resolved_config);
        $this->add_debug_entry('S3 Connection Test', $s3_connection_test,
            $s3_connection_test['success'] ? 'info' : 'error');

        // Test Presigned URL Generation
        $presigned_test = $this->test_presigned_url_generation($resolved_config);
        $this->add_debug_entry('Presigned URL Test', $presigned_test,
            $presigned_test['success'] ? 'info' : 'error');

        // Test AWS Signature
        $signature_test = $this->test_aws_signature_generation($resolved_config);
        $this->add_debug_entry('AWS Signature Test', $signature_test,
            $signature_test['success'] ? 'info' : 'error');
    }

    /**
     * Check configuration consistency across contexts
     */
    private function check_cross_context_consistency() {
        $this->add_debug_section('Cross-Context Consistency Check', 'info');

        $contexts = ['normal', 'ajax', 'admin'];
        $context_configs = [];

        foreach ($contexts as $context) {
            $context_configs[$context] = $this->get_configuration_in_context($context);
        }

        $this->add_debug_entry('Context Configurations', $context_configs, 'info');

        // Compare configurations
        $consistency_check = $this->compare_context_configurations($context_configs);
        $this->add_debug_entry('Consistency Analysis', $consistency_check,
            $consistency_check['all_consistent'] ? 'info' : 'critical');

        if (!$consistency_check['all_consistent']) {
            $this->add_recommendation('CRITICAL: Configuration inconsistent across contexts', 'critical');
            $this->add_recommendation('This is likely the root cause of AJAX handler failures', 'critical');
        }
    }

    /**
     * Helper Methods for Configuration Analysis
     */

    private function mask_credential($credential) {
        if (empty($credential)) return '';
        if (strlen($credential) <= 8) return str_repeat('*', strlen($credential));
        return substr($credential, 0, 4) . str_repeat('*', strlen($credential) - 8) . substr($credential, -4);
    }

    private function parse_memory_limit($memory_limit) {
        $memory_limit = strtoupper($memory_limit);
        $multiplier = 1;

        if (strpos($memory_limit, 'G') !== false) {
            $multiplier = 1024;
            $memory_limit = str_replace('G', '', $memory_limit);
        } elseif (strpos($memory_limit, 'M') !== false) {
            $memory_limit = str_replace('M', '', $memory_limit);
        } elseif (strpos($memory_limit, 'K') !== false) {
            $multiplier = 1 / 1024;
            $memory_limit = str_replace('K', '', $memory_limit);
        }

        return (int)$memory_limit * $multiplier;
    }

    private function analyze_configuration_priority($env_config, $db_config) {
        $priority_analysis = [];

        foreach ($env_config as $key => $env_data) {
            $db_key = $this->map_env_to_db_key($key);
            $db_data = isset($db_config[$db_key]) ? $db_config[$db_key] : null;

            $analysis = [
                'env_available' => $env_data['defined'] && !$env_data['empty'],
                'db_available' => $db_data && !$db_data['empty'],
                'final_source' => null,
                'final_value' => null
            ];

            if ($analysis['env_available']) {
                $analysis['final_source'] = 'environment';
                $analysis['final_value'] = $env_data['value'];
            } elseif ($analysis['db_available']) {
                $analysis['final_source'] = 'database';
                $analysis['final_value'] = $db_data['value'];
            } else {
                $analysis['final_source'] = 'none';
                $analysis['final_value'] = null;
            }

            $priority_analysis[$key] = $analysis;
        }

        return $priority_analysis;
    }

    private function map_env_to_db_key($env_key) {
        $mapping = [
            'H3_S3_BUCKET' => 'h3tm_s3_bucket',
            'H3_S3_REGION' => 'h3tm_s3_region',
            'AWS_ACCESS_KEY_ID' => 'h3tm_aws_access_key',
            'AWS_SECRET_ACCESS_KEY' => 'h3tm_aws_secret_key'
        ];

        return $mapping[$env_key] ?? null;
    }

    private function resolve_final_configuration() {
        return [
            'bucket' => defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', ''),
            'region' => defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1'),
            'access_key' => defined('AWS_ACCESS_KEY_ID') ? AWS_ACCESS_KEY_ID : get_option('h3tm_aws_access_key', ''),
            'secret_key' => defined('AWS_SECRET_ACCESS_KEY') ? AWS_SECRET_ACCESS_KEY : get_option('h3tm_aws_secret_key', '')
        ];
    }

    /**
     * Validation Methods
     */

    private function validate_bucket_name($bucket) {
        $validation = [
            'value' => $bucket,
            'valid' => false,
            'errors' => []
        ];

        if (empty($bucket)) {
            $validation['errors'][] = 'Bucket name is empty';
            return $validation;
        }

        // AWS S3 bucket naming rules
        if (strlen($bucket) < 3 || strlen($bucket) > 63) {
            $validation['errors'][] = 'Bucket name must be 3-63 characters long';
        }

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $bucket)) {
            $validation['errors'][] = 'Bucket name contains invalid characters';
        }

        if (preg_match('/\.\./', $bucket)) {
            $validation['errors'][] = 'Bucket name cannot contain consecutive periods';
        }

        if (preg_match('/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/', $bucket)) {
            $validation['errors'][] = 'Bucket name cannot be formatted as IP address';
        }

        $validation['valid'] = empty($validation['errors']);
        return $validation;
    }

    private function validate_region($region) {
        $validation = [
            'value' => $region,
            'valid' => false,
            'errors' => []
        ];

        if (empty($region)) {
            $validation['errors'][] = 'Region is empty';
            return $validation;
        }

        // List of valid AWS regions (simplified)
        $valid_regions = [
            'us-east-1', 'us-east-2', 'us-west-1', 'us-west-2',
            'eu-west-1', 'eu-west-2', 'eu-west-3', 'eu-central-1',
            'ap-southeast-1', 'ap-southeast-2', 'ap-northeast-1',
            'ca-central-1', 'sa-east-1'
        ];

        if (!in_array($region, $valid_regions)) {
            $validation['errors'][] = 'Region may not be valid AWS region';
            // Don't mark as invalid since AWS adds new regions
        }

        $validation['valid'] = empty($validation['errors']);
        if (!$validation['valid'] && count($validation['errors']) === 1 &&
            $validation['errors'][0] === 'Region may not be valid AWS region') {
            $validation['valid'] = true; // Allow unknown regions
        }

        return $validation;
    }

    private function validate_access_key($access_key) {
        $validation = [
            'value' => $this->mask_credential($access_key),
            'valid' => false,
            'errors' => []
        ];

        if (empty($access_key)) {
            $validation['errors'][] = 'Access key is empty';
            return $validation;
        }

        // AWS Access Key format validation
        if (strlen($access_key) !== 20) {
            $validation['errors'][] = 'Access key should be 20 characters long';
        }

        if (!preg_match('/^[A-Z0-9]+$/', $access_key)) {
            $validation['errors'][] = 'Access key should contain only uppercase letters and numbers';
        }

        if (!preg_match('/^AKIA/', $access_key) && !preg_match('/^ASIA/', $access_key)) {
            $validation['errors'][] = 'Access key should start with AKIA or ASIA';
        }

        $validation['valid'] = empty($validation['errors']);
        return $validation;
    }

    private function validate_secret_key($secret_key) {
        $validation = [
            'value' => !empty($secret_key) ? 'SET' : 'EMPTY',
            'valid' => false,
            'errors' => []
        ];

        if (empty($secret_key)) {
            $validation['errors'][] = 'Secret key is empty';
            return $validation;
        }

        // AWS Secret Key format validation
        if (strlen($secret_key) !== 40) {
            $validation['errors'][] = 'Secret key should be 40 characters long';
        }

        if (!preg_match('/^[A-Za-z0-9\/+=]+$/', $secret_key)) {
            $validation['errors'][] = 'Secret key contains invalid characters';
        }

        $validation['valid'] = empty($validation['errors']);
        return $validation;
    }

    /**
     * Class Instantiation Testing
     */

    private function test_s3_integration_instantiation() {
        try {
            $instance = new H3TM_S3_Integration();

            return [
                'success' => true,
                'instance' => $instance,
                'class_exists' => class_exists('H3TM_S3_Integration'),
                'methods_available' => get_class_methods($instance),
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'instance' => null,
                'class_exists' => class_exists('H3TM_S3_Integration'),
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_tour_manager_instantiation() {
        try {
            $instance = new H3TM_Tour_Manager();

            return [
                'success' => true,
                'instance' => $instance,
                'class_exists' => class_exists('H3TM_Tour_Manager'),
                'methods_available' => get_class_methods($instance),
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'instance' => null,
                'class_exists' => class_exists('H3TM_Tour_Manager'),
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_configuration_methods($s3_instance) {
        $methods_test = [
            'is_configured' => [
                'exists' => method_exists($s3_instance, 'is_configured'),
                'callable' => false,
                'result' => null,
                'error' => null
            ],
            'get_s3_config' => [
                'exists' => method_exists($s3_instance, 'get_s3_config'),
                'callable' => false,
                'result' => null,
                'error' => null
            ]
        ];

        foreach ($methods_test as $method => &$test) {
            if ($test['exists']) {
                try {
                    $test['result'] = call_user_func([$s3_instance, $method]);
                    $test['callable'] = true;
                } catch (Exception $e) {
                    $test['error'] = $e->getMessage();
                }
            }
        }

        $all_working = array_reduce($methods_test, function($carry, $test) {
            return $carry && $test['exists'] && $test['callable'];
        }, true);

        return [
            'methods' => $methods_test,
            'all_working' => $all_working
        ];
    }

    /**
     * Context Analysis Methods
     */

    private function detect_current_context() {
        return [
            'is_admin' => is_admin(),
            'is_ajax' => defined('DOING_AJAX') && DOING_AJAX,
            'is_cron' => defined('DOING_CRON') && DOING_CRON,
            'is_cli' => defined('WP_CLI') && WP_CLI,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'has_action_param' => !empty($_REQUEST['action']),
            'action_param' => $_REQUEST['action'] ?? null,
            'user_logged_in' => is_user_logged_in(),
            'current_user_can_manage_options' => current_user_can('manage_options')
        ];
    }

    private function check_ajax_handler_registration() {
        global $wp_filter;

        $expected_handlers = [
            'wp_ajax_h3tm_get_s3_presigned_url',
            'wp_ajax_h3tm_process_s3_upload',
            'wp_ajax_h3tm_test_s3_connection'
        ];

        $registration_status = [];
        $all_registered = true;

        foreach ($expected_handlers as $handler) {
            $registered = isset($wp_filter[$handler]) && !empty($wp_filter[$handler]->callbacks);
            $registration_status[$handler] = [
                'registered' => $registered,
                'callback_count' => $registered ? count($wp_filter[$handler]->callbacks) : 0
            ];

            if (!$registered) {
                $all_registered = false;
            }
        }

        return [
            'handlers' => $registration_status,
            'all_registered' => $all_registered,
            'total_expected' => count($expected_handlers),
            'total_registered' => count(array_filter($registration_status, function($status) {
                return $status['registered'];
            }))
        ];
    }

    private function simulate_ajax_context_configuration() {
        // Save current state
        $original_doing_ajax = defined('DOING_AJAX');
        $original_request = $_REQUEST;

        // Simulate AJAX context
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        $_REQUEST['action'] = 'h3tm_get_s3_presigned_url';

        try {
            // Test configuration in simulated AJAX context
            $s3_integration = new H3TM_S3_Integration();
            $ajax_config = [
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config()
            ];

            // Get normal context configuration
            $_REQUEST = $original_request;
            unset($_REQUEST['action']);

            $normal_integration = new H3TM_S3_Integration();
            $normal_config = [
                'is_configured' => $normal_integration->is_configured(),
                'config' => $normal_integration->get_s3_config()
            ];

            // Compare configurations
            $configuration_consistent = (
                $ajax_config['is_configured'] === $normal_config['is_configured'] &&
                $ajax_config['config'] === $normal_config['config']
            );

            return [
                'ajax_context_config' => $ajax_config,
                'normal_context_config' => $normal_config,
                'configuration_consistent' => $configuration_consistent,
                'differences' => $configuration_consistent ? [] : $this->find_config_differences($normal_config, $ajax_config),
                'error' => null
            ];

        } catch (Exception $e) {
            return [
                'configuration_consistent' => false,
                'error' => $e->getMessage()
            ];
        } finally {
            // Restore original state
            $_REQUEST = $original_request;
        }
    }

    /**
     * Network Connectivity Testing
     */

    private function test_basic_s3_connectivity() {
        $s3_endpoints = [
            'us-east-1' => 's3.amazonaws.com',
            'us-west-2' => 's3-us-west-2.amazonaws.com',
            'eu-west-1' => 's3-eu-west-1.amazonaws.com'
        ];

        $connectivity_results = [];
        $overall_connectivity = false;

        foreach ($s3_endpoints as $region => $endpoint) {
            $result = $this->test_endpoint_connectivity($endpoint);
            $connectivity_results[$region] = $result;

            if ($result['can_connect']) {
                $overall_connectivity = true;
            }
        }

        return [
            'can_connect' => $overall_connectivity,
            'endpoints_tested' => $connectivity_results,
            'total_endpoints' => count($s3_endpoints),
            'successful_connections' => count(array_filter($connectivity_results, function($result) {
                return $result['can_connect'];
            }))
        ];
    }

    private function test_endpoint_connectivity($endpoint) {
        $url = "https://$endpoint";

        $response = wp_remote_head($url, [
            'timeout' => 10,
            'user-agent' => 'H3TM-Config-Debugger/1.0'
        ]);

        if (is_wp_error($response)) {
            return [
                'endpoint' => $endpoint,
                'can_connect' => false,
                'error' => $response->get_error_message(),
                'response_code' => null
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);

        return [
            'endpoint' => $endpoint,
            'can_connect' => $response_code !== false && $response_code < 500,
            'response_code' => $response_code,
            'error' => null
        ];
    }

    private function test_dns_resolution() {
        $test_domains = ['s3.amazonaws.com', 's3-us-west-2.amazonaws.com'];
        $dns_results = [];
        $overall_resolved = false;

        foreach ($test_domains as $domain) {
            $ip = gethostbyname($domain);
            $resolved = ($ip !== $domain); // gethostbyname returns domain name if resolution fails

            $dns_results[$domain] = [
                'resolved' => $resolved,
                'ip' => $resolved ? $ip : null
            ];

            if ($resolved) {
                $overall_resolved = true;
            }
        }

        return [
            'resolved' => $overall_resolved,
            'dns_results' => $dns_results,
            'dns_function_available' => function_exists('gethostbyname')
        ];
    }

    private function test_ssl_connectivity() {
        $ssl_test_url = 'https://s3.amazonaws.com';

        $response = wp_remote_get($ssl_test_url, [
            'timeout' => 10,
            'sslverify' => true,
            'user-agent' => 'H3TM-Config-Debugger/1.0'
        ]);

        if (is_wp_error($response)) {
            return [
                'ssl_working' => false,
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code()
            ];
        }

        return [
            'ssl_working' => true,
            'response_code' => wp_remote_retrieve_response_code($response),
            'error' => null
        ];
    }

    /**
     * AWS Authentication Testing
     */

    private function is_configuration_complete($config) {
        return !empty($config['bucket']) &&
               !empty($config['access_key']) &&
               !empty($config['secret_key']);
    }

    private function test_s3_connection_with_credentials($config) {
        try {
            $s3_integration = new H3TM_S3_Integration();

            // Use reflection to call private test method if available
            $reflection = new ReflectionClass($s3_integration);

            if ($reflection->hasMethod('test_s3_connection')) {
                $method = $reflection->getMethod('test_s3_connection');
                $method->setAccessible(true);
                $result = $method->invoke($s3_integration);

                return [
                    'success' => $result,
                    'method' => 'internal_test_method',
                    'error' => $result ? null : 'S3 connection test failed'
                ];
            } else {
                // Fallback to basic configuration check
                return [
                    'success' => $s3_integration->is_configured(),
                    'method' => 'configuration_check',
                    'error' => null
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'method' => 'exception_thrown',
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_presigned_url_generation($config) {
        try {
            $s3_integration = new H3TM_S3_Integration();

            // Use reflection to test private method
            $reflection = new ReflectionClass($s3_integration);

            if ($reflection->hasMethod('generate_presigned_url')) {
                $method = $reflection->getMethod('generate_presigned_url');
                $method->setAccessible(true);

                $test_key = 'test/debug-' . time() . '.zip';
                $test_size = 1000000;

                $url = $method->invoke($s3_integration, $test_key, $test_size);

                return [
                    'success' => !empty($url),
                    'url_generated' => !empty($url),
                    'url_format_valid' => $this->validate_presigned_url_format($url),
                    'error' => empty($url) ? 'No URL generated' : null
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'generate_presigned_url method not found'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_aws_signature_generation($config) {
        try {
            // Test if we can generate a basic AWS signature
            $test_string = 'test-string-' . time();
            $test_key = 'test-key';

            $signature = hash_hmac('sha256', $test_string, $test_key);

            return [
                'success' => !empty($signature),
                'signature_generated' => !empty($signature),
                'hash_function_available' => function_exists('hash_hmac'),
                'error' => empty($signature) ? 'Signature generation failed' : null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function validate_presigned_url_format($url) {
        if (empty($url)) return false;

        $parsed = parse_url($url);
        if (!$parsed) return false;

        // Basic validation
        $validations = [
            'scheme_https' => $parsed['scheme'] === 'https',
            'has_host' => !empty($parsed['host']),
            's3_domain' => strpos($parsed['host'], '.s3.') !== false || strpos($parsed['host'], 's3-') !== false,
            'has_query' => !empty($parsed['query'])
        ];

        if ($validations['has_query']) {
            parse_str($parsed['query'], $query_params);
            $validations['has_signature'] = isset($query_params['X-Amz-Signature']);
            $validations['has_algorithm'] = isset($query_params['X-Amz-Algorithm']);
            $validations['has_credential'] = isset($query_params['X-Amz-Credential']);
        }

        return $validations;
    }

    /**
     * Cross-Context Configuration Testing
     */

    private function get_configuration_in_context($context) {
        try {
            switch ($context) {
                case 'ajax':
                    return $this->get_ajax_context_configuration();
                case 'admin':
                    return $this->get_admin_context_configuration();
                case 'normal':
                default:
                    return $this->get_normal_context_configuration();
            }
        } catch (Exception $e) {
            return [
                'context' => $context,
                'error' => $e->getMessage(),
                'configuration' => null
            ];
        }
    }

    private function get_normal_context_configuration() {
        $s3_integration = new H3TM_S3_Integration();
        return [
            'context' => 'normal',
            'is_configured' => $s3_integration->is_configured(),
            'config' => $s3_integration->get_s3_config(),
            'timestamp' => microtime(true)
        ];
    }

    private function get_ajax_context_configuration() {
        // Simulate AJAX context
        $original_doing_ajax = defined('DOING_AJAX');
        $original_action = $_REQUEST['action'] ?? null;

        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        $_REQUEST['action'] = 'h3tm_get_s3_presigned_url';

        try {
            $s3_integration = new H3TM_S3_Integration();
            $config = [
                'context' => 'ajax',
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config(),
                'timestamp' => microtime(true)
            ];
        } catch (Exception $e) {
            $config = [
                'context' => 'ajax',
                'error' => $e->getMessage(),
                'timestamp' => microtime(true)
            ];
        }

        // Restore original state
        if ($original_action !== null) {
            $_REQUEST['action'] = $original_action;
        } else {
            unset($_REQUEST['action']);
        }

        return $config;
    }

    private function get_admin_context_configuration() {
        // For simplicity, this is similar to normal context
        // In a real implementation, you might need to simulate admin context
        return $this->get_normal_context_configuration();
    }

    private function compare_context_configurations($context_configs) {
        $contexts = array_keys($context_configs);
        $first_context = $contexts[0];
        $base_config = $context_configs[$first_context];

        $differences = [];
        $all_consistent = true;

        for ($i = 1; $i < count($contexts); $i++) {
            $compare_context = $contexts[$i];
            $compare_config = $context_configs[$compare_context];

            if (isset($base_config['error']) || isset($compare_config['error'])) {
                $differences[] = "Error in context comparison: $compare_context";
                $all_consistent = false;
                continue;
            }

            // Compare is_configured
            if ($base_config['is_configured'] !== $compare_config['is_configured']) {
                $differences[] = "is_configured differs between $first_context and $compare_context";
                $all_consistent = false;
            }

            // Compare configuration details
            if ($base_config['config'] !== $compare_config['config']) {
                $differences[] = "config differs between $first_context and $compare_context";
                $all_consistent = false;
            }
        }

        return [
            'all_consistent' => $all_consistent,
            'differences' => $differences,
            'contexts_compared' => $contexts,
            'total_comparisons' => count($contexts) - 1
        ];
    }

    private function find_config_differences($config1, $config2) {
        $differences = [];

        // Compare is_configured
        if ($config1['is_configured'] !== $config2['is_configured']) {
            $differences['is_configured'] = [
                'config1' => $config1['is_configured'],
                'config2' => $config2['is_configured']
            ];
        }

        // Compare config arrays
        if (isset($config1['config']) && isset($config2['config'])) {
            foreach ($config1['config'] as $key => $value1) {
                if (!isset($config2['config'][$key]) || $config2['config'][$key] !== $value1) {
                    $differences["config[$key]"] = [
                        'config1' => $value1,
                        'config2' => $config2['config'][$key] ?? 'missing'
                    ];
                }
            }
        }

        return $differences;
    }

    /**
     * Debug Logging and Report Generation
     */

    private function add_debug_section($title, $severity = 'info') {
        $this->debug_results[] = [
            'type' => 'section',
            'title' => $title,
            'severity' => $severity,
            'timestamp' => microtime(true)
        ];
    }

    private function add_debug_entry($title, $data, $severity = 'info') {
        $this->debug_results[] = [
            'type' => 'entry',
            'title' => $title,
            'data' => $data,
            'severity' => $severity,
            'timestamp' => microtime(true)
        ];
    }

    private function add_recommendation($recommendation, $severity = 'info') {
        $this->recommendations[] = [
            'recommendation' => $recommendation,
            'severity' => $severity,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Generate comprehensive diagnostic report
     */
    public function generate_diagnostic_report() {
        $report = [
            'meta' => [
                'timestamp' => current_time('mysql'),
                'debug_version' => '1.0',
                'total_entries' => count($this->debug_results),
                'total_recommendations' => count($this->recommendations)
            ],
            'summary' => $this->generate_diagnostic_summary(),
            'debug_results' => $this->debug_results,
            'recommendations' => $this->group_recommendations_by_severity(),
            'configuration_trace' => $this->config_trace,
            'quick_fixes' => $this->generate_quick_fixes(),
            'next_steps' => $this->generate_next_steps()
        ];

        error_log('H3TM S3 Configuration Debugger: Diagnostic completed');
        error_log('H3TM S3 Configuration Debugger: ' . $report['summary']['overall_status']);

        return $report;
    }

    private function generate_diagnostic_summary() {
        $severity_counts = [
            'critical' => 0,
            'error' => 0,
            'warning' => 0,
            'info' => 0
        ];

        foreach ($this->debug_results as $result) {
            if (isset($result['severity'])) {
                $severity_counts[$result['severity']]++;
            }
        }

        foreach ($this->recommendations as $recommendation) {
            $severity_counts[$recommendation['severity']]++;
        }

        // Determine overall status
        $overall_status = 'healthy';
        if ($severity_counts['critical'] > 0) {
            $overall_status = 'critical_issues';
        } elseif ($severity_counts['error'] > 0) {
            $overall_status = 'errors_found';
        } elseif ($severity_counts['warning'] > 0) {
            $overall_status = 'warnings_present';
        }

        return [
            'overall_status' => $overall_status,
            'severity_counts' => $severity_counts,
            'total_issues' => $severity_counts['critical'] + $severity_counts['error'],
            'configuration_status' => $this->determine_configuration_status(),
            'primary_issue' => $this->identify_primary_issue()
        ];
    }

    private function group_recommendations_by_severity() {
        $grouped = [
            'critical' => [],
            'error' => [],
            'warning' => [],
            'info' => []
        ];

        foreach ($this->recommendations as $recommendation) {
            $grouped[$recommendation['severity']][] = $recommendation;
        }

        return $grouped;
    }

    private function generate_quick_fixes() {
        $quick_fixes = [];

        foreach ($this->recommendations as $recommendation) {
            $fix = $this->generate_quick_fix_for_recommendation($recommendation);
            if ($fix) {
                $quick_fixes[] = $fix;
            }
        }

        return $quick_fixes;
    }

    private function generate_quick_fix_for_recommendation($recommendation) {
        $text = $recommendation['recommendation'];

        // Pattern matching for common issues
        if (strpos($text, 'Configuration inconsistent') !== false) {
            return [
                'issue' => 'Configuration inconsistent across contexts',
                'quick_fix' => 'Check that S3 Integration class is instantiated consistently',
                'code_check' => 'Verify that the same configuration source is used in all contexts',
                'severity' => $recommendation['severity']
            ];
        }

        if (strpos($text, 'AJAX handler') !== false) {
            return [
                'issue' => 'AJAX handler registration issues',
                'quick_fix' => 'Ensure S3 Integration class is instantiated during WordPress init',
                'code_check' => 'Check that add_action calls are executed properly',
                'severity' => $recommendation['severity']
            ];
        }

        if (strpos($text, 'Fix requirement') !== false) {
            return [
                'issue' => 'System requirement not met',
                'quick_fix' => 'Install/enable required PHP extension or adjust server settings',
                'code_check' => 'Contact hosting provider if needed',
                'severity' => $recommendation['severity']
            ];
        }

        return null;
    }

    private function generate_next_steps() {
        $critical_count = count($this->recommendations);
        $has_critical = false;

        foreach ($this->recommendations as $rec) {
            if ($rec['severity'] === 'critical') {
                $has_critical = true;
                break;
            }
        }

        if ($has_critical) {
            return [
                '1. Address critical issues first',
                '2. Run diagnostics again after fixes',
                '3. Test S3 upload functionality',
                '4. Monitor error logs for remaining issues'
            ];
        } else {
            return [
                '1. Review and address any warnings',
                '2. Test S3 upload functionality end-to-end',
                '3. Monitor system performance',
                '4. Run diagnostics periodically'
            ];
        }
    }

    private function determine_configuration_status() {
        $config = $this->resolve_final_configuration();

        if (empty($config['bucket']) || empty($config['access_key']) || empty($config['secret_key'])) {
            return 'incomplete';
        }

        return 'complete';
    }

    private function identify_primary_issue() {
        // Look for critical recommendations first
        foreach ($this->recommendations as $rec) {
            if ($rec['severity'] === 'critical') {
                return $rec['recommendation'];
            }
        }

        // Then errors
        foreach ($this->recommendations as $rec) {
            if ($rec['severity'] === 'error') {
                return $rec['recommendation'];
            }
        }

        return 'No critical issues identified';
    }

    /**
     * Export diagnostic report to file
     */
    public function export_diagnostic_report($file_path = null) {
        if ($file_path === null) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/h3tm-s3-diagnostic-' . date('Y-m-d-H-i-s') . '.json';
        }

        $report = $this->generate_diagnostic_report();

        // Also create a human-readable version
        $readable_path = str_replace('.json', '-readable.txt', $file_path);
        $readable_content = $this->generate_readable_report($report);

        file_put_contents($file_path, json_encode($report, JSON_PRETTY_PRINT));
        file_put_contents($readable_path, $readable_content);

        return [
            'json_report' => $file_path,
            'readable_report' => $readable_path
        ];
    }

    private function generate_readable_report($report) {
        $output = "H3 Tour Management S3 Configuration Diagnostic Report\n";
        $output .= "Generated: " . $report['meta']['timestamp'] . "\n";
        $output .= str_repeat("=", 60) . "\n\n";

        $output .= "OVERALL STATUS: " . strtoupper($report['summary']['overall_status']) . "\n";
        $output .= "Configuration Status: " . ucfirst($report['summary']['configuration_status']) . "\n";
        $output .= "Primary Issue: " . $report['summary']['primary_issue'] . "\n\n";

        $output .= "ISSUE SUMMARY:\n";
        foreach ($report['summary']['severity_counts'] as $severity => $count) {
            if ($count > 0) {
                $output .= "- " . ucfirst($severity) . ": $count\n";
            }
        }
        $output .= "\n";

        if (!empty($report['recommendations']['critical'])) {
            $output .= "CRITICAL ISSUES:\n";
            foreach ($report['recommendations']['critical'] as $i => $rec) {
                $output .= ($i + 1) . ". " . $rec['recommendation'] . "\n";
            }
            $output .= "\n";
        }

        if (!empty($report['recommendations']['error'])) {
            $output .= "ERRORS:\n";
            foreach ($report['recommendations']['error'] as $i => $rec) {
                $output .= ($i + 1) . ". " . $rec['recommendation'] . "\n";
            }
            $output .= "\n";
        }

        $output .= "NEXT STEPS:\n";
        foreach ($report['next_steps'] as $i => $step) {
            $output .= ($i + 1) . ". $step\n";
        }

        return $output;
    }

    /**
     * Quick diagnostic method for immediate issues
     */
    public function quick_diagnostic() {
        // Just run the most important checks
        $quick_results = [
            'timestamp' => current_time('mysql'),
            'configuration_complete' => false,
            'classes_available' => false,
            'ajax_handlers_registered' => false,
            'primary_issue' => null,
            'quick_fix' => null
        ];

        // Check configuration
        $config = $this->resolve_final_configuration();
        $quick_results['configuration_complete'] = $this->is_configuration_complete($config);

        // Check classes
        $quick_results['classes_available'] = class_exists('H3TM_S3_Integration') &&
                                            class_exists('H3TM_Tour_Manager');

        // Check AJAX handlers
        $ajax_check = $this->check_ajax_handler_registration();
        $quick_results['ajax_handlers_registered'] = $ajax_check['all_registered'];

        // Determine primary issue
        if (!$quick_results['configuration_complete']) {
            $quick_results['primary_issue'] = 'S3 configuration incomplete';
            $quick_results['quick_fix'] = 'Configure S3 bucket, access key, and secret key';
        } elseif (!$quick_results['classes_available']) {
            $quick_results['primary_issue'] = 'Required classes not available';
            $quick_results['quick_fix'] = 'Check that H3 Tour Management plugin is active';
        } elseif (!$quick_results['ajax_handlers_registered']) {
            $quick_results['primary_issue'] = 'AJAX handlers not registered';
            $quick_results['quick_fix'] = 'Ensure S3 Integration class is instantiated during WordPress init';
        } else {
            $quick_results['primary_issue'] = 'No obvious issues - run full diagnostic';
            $quick_results['quick_fix'] = 'Configuration appears correct';
        }

        return $quick_results;
    }
}

// Allow direct execution for testing
if (defined('WP_CLI') || (defined('ABSPATH') && !defined('DOING_AJAX') && current_user_can('manage_options'))) {

    function run_h3tm_s3_configuration_debugger() {
        $debugger = new H3TM_S3_Configuration_Debugger();

        if (isset($_GET['quick'])) {
            $results = $debugger->quick_diagnostic();
            echo '<h2>H3TM S3 Configuration Quick Diagnostic</h2>';
            echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        } else {
            $results = $debugger->run_full_diagnostics();

            if (defined('WP_CLI')) {
                WP_CLI::success('S3 Configuration Diagnostics completed');
                WP_CLI::log(json_encode($results, JSON_PRETTY_PRINT));
            } else {
                echo '<h2>H3TM S3 Configuration Diagnostic Report</h2>';
                echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
            }
        }

        return $results;
    }

    // Auto-run based on query parameters
    if (isset($_GET['run_s3_config_debugger']) || isset($_GET['h3tm_debug'])) {
        run_h3tm_s3_configuration_debugger();
    }
}