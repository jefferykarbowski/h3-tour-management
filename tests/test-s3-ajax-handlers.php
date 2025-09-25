<?php
/**
 * S3 AJAX Handler Registration and Execution Tests
 *
 * Tests for H3 Tour Management S3 AJAX handlers to identify
 * registration issues and execution context problems.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/h3-tour-management.php';
}

class H3TM_S3_Ajax_Handler_Tests {

    private $test_results = [];
    private $debug_info = [];

    public function __construct() {
        error_log('H3TM S3 AJAX Tests: Initializing AJAX handler tests');
    }

    /**
     * Run all AJAX handler tests
     */
    public function run_all_tests() {
        $this->test_results = [];
        $this->debug_info = [];

        error_log('H3TM S3 AJAX Tests: Starting comprehensive AJAX handler tests');

        // Test 1: Handler Registration Detection
        $this->test_ajax_handler_registration();

        // Test 2: Handler Callback Verification
        $this->test_handler_callback_verification();

        // Test 3: AJAX Context Simulation
        $this->test_ajax_context_simulation();

        // Test 4: Handler Execution with Configuration
        $this->test_handler_execution_with_config();

        // Test 5: Error Handling in AJAX Context
        $this->test_ajax_error_handling();

        // Test 6: Nonce Verification
        $this->test_nonce_verification();

        // Test 7: Permission Verification
        $this->test_permission_verification();

        // Test 8: Configuration Loading in AJAX
        $this->test_configuration_loading_in_ajax();

        return $this->generate_test_report();
    }

    /**
     * Test if AJAX handlers are properly registered
     */
    private function test_ajax_handler_registration() {
        $test_name = 'AJAX Handler Registration';
        error_log("H3TM S3 AJAX Tests: Running $test_name");

        global $wp_filter;

        $expected_handlers = [
            'wp_ajax_h3tm_get_s3_presigned_url',
            'wp_ajax_h3tm_process_s3_upload',
            'wp_ajax_h3tm_test_s3_connection',
            'wp_ajax_h3tm_test_ajax_handler'
        ];

        $results = [];
        $registration_issues = [];

        foreach ($expected_handlers as $action) {
            $registered = isset($wp_filter[$action]) && !empty($wp_filter[$action]->callbacks);
            $results[$action] = [
                'registered' => $registered,
                'callbacks' => $registered ? count($wp_filter[$action]->callbacks) : 0,
                'priority_levels' => $registered ? array_keys($wp_filter[$action]->callbacks) : []
            ];

            if (!$registered) {
                $registration_issues[] = $action;
            }
        }

        $results['summary'] = [
            'total_expected' => count($expected_handlers),
            'registered_count' => count($expected_handlers) - count($registration_issues),
            'missing_handlers' => $registration_issues
        ];

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_handler_registration($results),
            'recommendation' => $this->get_registration_recommendation($results)
        ];
    }

    /**
     * Test handler callback verification
     */
    private function test_handler_callback_verification() {
        $test_name = 'Handler Callback Verification';
        error_log("H3TM S3 AJAX Tests: Running $test_name");

        global $wp_filter;

        $handlers_to_check = [
            'wp_ajax_h3tm_get_s3_presigned_url' => [
                'expected_class' => 'H3TM_S3_Integration',
                'expected_method' => 'handle_get_presigned_url'
            ],
            'wp_ajax_h3tm_process_s3_upload' => [
                'expected_class' => 'H3TM_S3_Integration',
                'expected_method' => 'handle_process_s3_upload'
            ],
            'wp_ajax_h3tm_test_s3_connection' => [
                'expected_class' => 'H3TM_S3_Integration',
                'expected_method' => 'handle_test_s3_connection'
            ]
        ];

        $results = [];

        foreach ($handlers_to_check as $action => $expected) {
            if (isset($wp_filter[$action])) {
                $callbacks = [];
                foreach ($wp_filter[$action]->callbacks as $priority => $priority_callbacks) {
                    foreach ($priority_callbacks as $callback_data) {
                        $callback_info = $this->analyze_callback($callback_data['function']);
                        $callbacks[] = [
                            'priority' => $priority,
                            'callback_info' => $callback_info,
                            'matches_expected' => $this->matches_expected_callback($callback_info, $expected)
                        ];
                    }
                }

                $results[$action] = [
                    'has_callbacks' => !empty($callbacks),
                    'callback_count' => count($callbacks),
                    'callbacks' => $callbacks,
                    'properly_configured' => $this->has_proper_callback($callbacks, $expected)
                ];
            } else {
                $results[$action] = [
                    'has_callbacks' => false,
                    'callback_count' => 0,
                    'error' => 'Handler not registered'
                ];
            }
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_callback_verification($results),
            'recommendation' => $this->get_callback_recommendation($results)
        ];
    }

    /**
     * Test AJAX context simulation
     */
    private function test_ajax_context_simulation() {
        $test_name = 'AJAX Context Simulation';
        error_log("H3TM S3 AJAX Tests: Running $test_name");

        $original_doing_ajax = defined('DOING_AJAX');
        $original_request = $_REQUEST;
        $original_post = $_POST;

        // Simulate AJAX context
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        $_REQUEST['action'] = 'h3tm_test_ajax_handler';
        $_POST['action'] = 'h3tm_test_ajax_handler';

        $results = [
            'doing_ajax_defined' => defined('DOING_AJAX'),
            'doing_ajax_value' => DOING_AJAX,
            'request_action_set' => isset($_REQUEST['action']),
            'post_action_set' => isset($_POST['action']),
            'is_ajax_context' => $this->is_ajax_context(),
            'context_detection' => $this->detect_request_context()
        ];

        // Test configuration loading in simulated AJAX context
        try {
            $s3_integration = new H3TM_S3_Integration();
            $results['config_in_ajax'] = [
                'class_loaded' => true,
                'is_configured' => $s3_integration->is_configured(),
                'config_data' => $s3_integration->get_s3_config()
            ];
        } catch (Exception $e) {
            $results['config_in_ajax'] = [
                'class_loaded' => false,
                'error' => $e->getMessage()
            ];
        }

        // Restore original state
        $_REQUEST = $original_request;
        $_POST = $original_post;

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_ajax_simulation($results),
            'recommendation' => $this->get_ajax_simulation_recommendation($results)
        ];
    }

    /**
     * Test handler execution with different configuration states
     */
    private function test_handler_execution_with_config() {
        $test_name = 'Handler Execution with Configuration';
        error_log("H3TM S3 AJAX Tests: Running $test_name");

        $results = [];

        // Test scenarios with different configuration states
        $test_scenarios = [
            'no_config' => $this->simulate_handler_execution_no_config(),
            'env_config' => $this->simulate_handler_execution_env_config(),
            'db_config' => $this->simulate_handler_execution_db_config(),
            'mixed_config' => $this->simulate_handler_execution_mixed_config()
        ];

        $results['scenarios'] = $test_scenarios;
        $results['configuration_impact'] = $this->analyze_configuration_impact($test_scenarios);

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_handler_execution($results),
            'recommendation' => $this->get_execution_recommendation($results)
        ];
    }

    /**
     * Test error handling in AJAX context
     */
    private function test_ajax_error_handling() {
        $test_name = 'AJAX Error Handling';
        error_log("H3TM S3 AJAX Tests: Running $test_name");

        $results = [];

        // Test error scenarios
        $error_scenarios = [
            'invalid_nonce' => $this->test_invalid_nonce_handling(),
            'insufficient_permissions' => $this->test_insufficient_permissions_handling(),
            'missing_config' => $this->test_missing_config_handling(),
            'invalid_parameters' => $this->test_invalid_parameters_handling()
        ];

        $results['error_scenarios'] = $error_scenarios;
        $results['error_handling_quality'] = $this->evaluate_error_handling($error_scenarios);

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_error_handling($results),
            'recommendation' => $this->get_error_handling_recommendation($results)
        ];
    }

    /**
     * Test nonce verification
     */
    private function test_nonce_verification() {
        $test_name = 'Nonce Verification';
        error_log("H3TM S3 AJAX Tests: Running $test_name");

        $results = [
            'nonce_field_name' => 'h3tm_ajax_nonce',
            'nonce_action' => 'h3tm_ajax_nonce',
            'valid_nonce_test' => $this->test_valid_nonce(),
            'invalid_nonce_test' => $this->test_invalid_nonce(),
            'missing_nonce_test' => $this->test_missing_nonce()
        ];

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_nonce_verification($results),
            'recommendation' => $this->get_nonce_recommendation($results)
        ];
    }

    /**
     * Test permission verification
     */
    private function test_permission_verification() {
        $test_name = 'Permission Verification';
        error_log("H3TM S3 AJAX Tests: Running $test_name");

        $current_user_id = get_current_user_id();
        $original_user = wp_get_current_user();

        $results = [
            'current_user_can_manage_options' => current_user_can('manage_options'),
            'admin_permission_test' => $this->test_admin_permissions(),
            'non_admin_permission_test' => $this->test_non_admin_permissions()
        ];

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_permission_verification($results),
            'recommendation' => $this->get_permission_recommendation($results)
        ];
    }

    /**
     * Test configuration loading specifically in AJAX context
     */
    private function test_configuration_loading_in_ajax() {
        $test_name = 'Configuration Loading in AJAX';
        error_log("H3TM S3 AJAX Tests: Running $test_name");

        // Save original state
        $original_doing_ajax = defined('DOING_AJAX');

        // Test configuration loading in different contexts
        $results = [];

        // Test 1: Normal context
        $results['normal_context'] = $this->test_config_loading_context('normal');

        // Test 2: AJAX context
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        $_REQUEST['action'] = 'h3tm_get_s3_presigned_url';

        $results['ajax_context'] = $this->test_config_loading_context('ajax');

        // Clean up
        unset($_REQUEST['action']);

        // Test 3: Compare configurations
        $results['context_comparison'] = $this->compare_context_configurations(
            $results['normal_context'],
            $results['ajax_context']
        );

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_config_loading($results),
            'recommendation' => $this->get_config_loading_recommendation($results)
        ];
    }

    /**
     * Helper Methods
     */

    private function analyze_callback($callback) {
        if (is_array($callback)) {
            if (is_object($callback[0])) {
                return [
                    'type' => 'object_method',
                    'class' => get_class($callback[0]),
                    'method' => $callback[1],
                    'object_id' => spl_object_hash($callback[0])
                ];
            } elseif (is_string($callback[0])) {
                return [
                    'type' => 'static_method',
                    'class' => $callback[0],
                    'method' => $callback[1]
                ];
            }
        } elseif (is_string($callback)) {
            return [
                'type' => 'function',
                'function' => $callback
            ];
        } elseif ($callback instanceof Closure) {
            return [
                'type' => 'closure',
                'closure_info' => 'anonymous_function'
            ];
        }

        return [
            'type' => 'unknown',
            'callback' => $callback
        ];
    }

    private function matches_expected_callback($callback_info, $expected) {
        if ($callback_info['type'] === 'object_method') {
            return $callback_info['class'] === $expected['expected_class'] &&
                   $callback_info['method'] === $expected['expected_method'];
        }
        return false;
    }

    private function has_proper_callback($callbacks, $expected) {
        foreach ($callbacks as $callback) {
            if ($callback['matches_expected']) {
                return true;
            }
        }
        return false;
    }

    private function is_ajax_context() {
        return defined('DOING_AJAX') && DOING_AJAX && !empty($_REQUEST['action']);
    }

    private function detect_request_context() {
        $context = [];
        $context['is_admin'] = is_admin();
        $context['is_ajax'] = defined('DOING_AJAX') && DOING_AJAX;
        $context['is_cron'] = defined('DOING_CRON') && DOING_CRON;
        $context['is_frontend'] = !is_admin() && !defined('DOING_AJAX');
        $context['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $context['has_action'] = !empty($_REQUEST['action']);
        $context['action'] = $_REQUEST['action'] ?? null;

        return $context;
    }

    private function simulate_handler_execution_no_config() {
        // Temporarily remove configuration
        $original_bucket = get_option('h3tm_s3_bucket', '');
        $original_access = get_option('h3tm_aws_access_key', '');
        $original_secret = get_option('h3tm_aws_secret_key', '');

        update_option('h3tm_s3_bucket', '');
        update_option('h3tm_aws_access_key', '');
        update_option('h3tm_aws_secret_key', '');

        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = [
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config(),
                'error' => null
            ];
        } catch (Exception $e) {
            $result = [
                'is_configured' => false,
                'config' => null,
                'error' => $e->getMessage()
            ];
        }

        // Restore original configuration
        update_option('h3tm_s3_bucket', $original_bucket);
        update_option('h3tm_aws_access_key', $original_access);
        update_option('h3tm_aws_secret_key', $original_secret);

        return $result;
    }

    private function simulate_handler_execution_env_config() {
        // This would test environment variable configuration
        // For now, just test current state assuming env vars might be set
        try {
            $has_env_config = defined('H3_S3_BUCKET') && defined('AWS_ACCESS_KEY_ID') && defined('AWS_SECRET_ACCESS_KEY');

            if ($has_env_config) {
                $s3_integration = new H3TM_S3_Integration();
                $result = [
                    'has_env_config' => true,
                    'is_configured' => $s3_integration->is_configured(),
                    'config' => $s3_integration->get_s3_config(),
                    'error' => null
                ];
            } else {
                $result = [
                    'has_env_config' => false,
                    'is_configured' => false,
                    'config' => null,
                    'error' => 'Environment variables not defined'
                ];
            }
        } catch (Exception $e) {
            $result = [
                'has_env_config' => $has_env_config ?? false,
                'error' => $e->getMessage()
            ];
        }

        return $result;
    }

    private function simulate_handler_execution_db_config() {
        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = [
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config(),
                'config_source' => 'database',
                'error' => null
            ];
        } catch (Exception $e) {
            $result = [
                'is_configured' => false,
                'config' => null,
                'error' => $e->getMessage()
            ];
        }

        return $result;
    }

    private function simulate_handler_execution_mixed_config() {
        // Test with mixed environment and database configuration
        $env_vars = [
            'H3_S3_BUCKET' => defined('H3_S3_BUCKET'),
            'AWS_ACCESS_KEY_ID' => defined('AWS_ACCESS_KEY_ID'),
            'AWS_SECRET_ACCESS_KEY' => defined('AWS_SECRET_ACCESS_KEY')
        ];

        $db_options = [
            'h3tm_s3_bucket' => !empty(get_option('h3tm_s3_bucket', '')),
            'h3tm_aws_access_key' => !empty(get_option('h3tm_aws_access_key', '')),
            'h3tm_aws_secret_key' => !empty(get_option('h3tm_aws_secret_key', ''))
        ];

        try {
            $s3_integration = new H3TM_S3_Integration();
            $result = [
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config(),
                'env_vars' => $env_vars,
                'db_options' => $db_options,
                'mixed_sources' => $this->detect_mixed_sources($env_vars, $db_options),
                'error' => null
            ];
        } catch (Exception $e) {
            $result = [
                'error' => $e->getMessage(),
                'env_vars' => $env_vars,
                'db_options' => $db_options
            ];
        }

        return $result;
    }

    private function detect_mixed_sources($env_vars, $db_options) {
        $env_count = array_sum($env_vars);
        $db_count = array_sum($db_options);

        return [
            'env_count' => $env_count,
            'db_count' => $db_count,
            'is_mixed' => ($env_count > 0 && $db_count > 0),
            'source_breakdown' => [
                'environment_only' => ($env_count > 0 && $db_count === 0),
                'database_only' => ($env_count === 0 && $db_count > 0),
                'mixed_sources' => ($env_count > 0 && $db_count > 0)
            ]
        ];
    }

    private function analyze_configuration_impact($scenarios) {
        $impact_analysis = [];

        foreach ($scenarios as $scenario_name => $scenario_data) {
            $impact_analysis[$scenario_name] = [
                'configured' => $scenario_data['is_configured'] ?? false,
                'has_error' => isset($scenario_data['error']),
                'error_message' => $scenario_data['error'] ?? null
            ];
        }

        $configured_count = count(array_filter($impact_analysis, function($item) {
            return $item['configured'];
        }));

        $error_count = count(array_filter($impact_analysis, function($item) {
            return $item['has_error'];
        }));

        return [
            'total_scenarios' => count($scenarios),
            'configured_scenarios' => $configured_count,
            'error_scenarios' => $error_count,
            'configuration_consistency' => ($configured_count === count($scenarios) - $error_count)
        ];
    }

    private function test_invalid_nonce_handling() {
        // This would simulate invalid nonce scenarios
        return [
            'scenario' => 'invalid_nonce',
            'expected_behavior' => 'Should reject request with wp_die',
            'test_result' => 'Simulated - would need actual AJAX call to fully test'
        ];
    }

    private function test_insufficient_permissions_handling() {
        return [
            'scenario' => 'insufficient_permissions',
            'expected_behavior' => 'Should reject request with wp_die',
            'test_result' => 'Simulated - would need user context switching to fully test'
        ];
    }

    private function test_missing_config_handling() {
        return [
            'scenario' => 'missing_config',
            'expected_behavior' => 'Should return error JSON response',
            'test_result' => 'Handled in configuration simulation tests'
        ];
    }

    private function test_invalid_parameters_handling() {
        return [
            'scenario' => 'invalid_parameters',
            'expected_behavior' => 'Should validate and reject invalid parameters',
            'test_result' => 'Would need actual parameter validation testing'
        ];
    }

    private function evaluate_error_handling($scenarios) {
        $handled_properly = 0;
        $total_scenarios = count($scenarios);

        foreach ($scenarios as $scenario) {
            if (strpos($scenario['test_result'], 'Should') !== false) {
                $handled_properly++;
            }
        }

        return [
            'total_scenarios' => $total_scenarios,
            'properly_handled' => $handled_properly,
            'error_handling_score' => $total_scenarios > 0 ? ($handled_properly / $total_scenarios) * 100 : 0
        ];
    }

    private function test_valid_nonce() {
        $nonce = wp_create_nonce('h3tm_ajax_nonce');
        return [
            'nonce_created' => !empty($nonce),
            'nonce_value' => $nonce,
            'verification_ready' => true
        ];
    }

    private function test_invalid_nonce() {
        $invalid_nonce = 'invalid_nonce_value';
        $verification = wp_verify_nonce($invalid_nonce, 'h3tm_ajax_nonce');
        return [
            'nonce_value' => $invalid_nonce,
            'verification_result' => $verification,
            'properly_rejected' => ($verification === false)
        ];
    }

    private function test_missing_nonce() {
        return [
            'scenario' => 'missing_nonce',
            'expected_behavior' => 'Should be handled by check_ajax_referer',
            'verification_ready' => false
        ];
    }

    private function test_admin_permissions() {
        return [
            'current_user_can_manage_options' => current_user_can('manage_options'),
            'is_admin' => is_admin(),
            'user_id' => get_current_user_id(),
            'should_allow_access' => current_user_can('manage_options')
        ];
    }

    private function test_non_admin_permissions() {
        // This is a simulation since we can't easily switch user context
        return [
            'scenario' => 'non_admin_user',
            'expected_behavior' => 'Should be rejected with wp_die',
            'test_note' => 'Would need user context switching to fully test'
        ];
    }

    private function test_config_loading_context($context_name) {
        try {
            $s3_integration = new H3TM_S3_Integration();
            return [
                'context' => $context_name,
                'class_loaded' => true,
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config(),
                'timestamp' => microtime(true),
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'context' => $context_name,
                'class_loaded' => false,
                'error' => $e->getMessage(),
                'timestamp' => microtime(true)
            ];
        }
    }

    private function compare_context_configurations($normal_config, $ajax_config) {
        if (isset($normal_config['error']) || isset($ajax_config['error'])) {
            return [
                'comparison_possible' => false,
                'normal_error' => $normal_config['error'] ?? null,
                'ajax_error' => $ajax_config['error'] ?? null
            ];
        }

        return [
            'comparison_possible' => true,
            'configured_match' => ($normal_config['is_configured'] === $ajax_config['is_configured']),
            'config_match' => ($normal_config['config'] === $ajax_config['config']),
            'differences' => $this->find_config_differences($normal_config['config'], $ajax_config['config']),
            'consistency_score' => $this->calculate_consistency_score($normal_config, $ajax_config)
        ];
    }

    private function find_config_differences($config1, $config2) {
        $differences = [];

        if ($config1['configured'] !== $config2['configured']) {
            $differences['configured'] = [
                'normal' => $config1['configured'],
                'ajax' => $config2['configured']
            ];
        }

        if ($config1['bucket'] !== $config2['bucket']) {
            $differences['bucket'] = [
                'normal' => $config1['bucket'],
                'ajax' => $config2['bucket']
            ];
        }

        if ($config1['region'] !== $config2['region']) {
            $differences['region'] = [
                'normal' => $config1['region'],
                'ajax' => $config2['region']
            ];
        }

        return $differences;
    }

    private function calculate_consistency_score($normal_config, $ajax_config) {
        if (!isset($normal_config['config']) || !isset($ajax_config['config'])) {
            return 0;
        }

        $matches = 0;
        $total_fields = 0;

        $fields_to_compare = ['configured', 'bucket', 'region'];

        foreach ($fields_to_compare as $field) {
            $total_fields++;
            if (isset($normal_config['config'][$field]) && isset($ajax_config['config'][$field])) {
                if ($normal_config['config'][$field] === $ajax_config['config'][$field]) {
                    $matches++;
                }
            }
        }

        return $total_fields > 0 ? ($matches / $total_fields) * 100 : 0;
    }

    /**
     * Summary Methods
     */

    private function summarize_handler_registration($results) {
        $registered = $results['summary']['registered_count'];
        $total = $results['summary']['total_expected'];
        $missing = count($results['summary']['missing_handlers']);

        return "Registered: $registered/$total, Missing: $missing";
    }

    private function summarize_callback_verification($results) {
        $properly_configured = 0;
        $total = count($results);

        foreach ($results as $action => $data) {
            if (isset($data['properly_configured']) && $data['properly_configured']) {
                $properly_configured++;
            }
        }

        return "Properly configured: $properly_configured/$total";
    }

    private function summarize_ajax_simulation($results) {
        $ajax_context = $results['is_ajax_context'] ? 'Yes' : 'No';
        $config_loaded = isset($results['config_in_ajax']['class_loaded']) && $results['config_in_ajax']['class_loaded'] ? 'Yes' : 'No';

        return "AJAX Context: $ajax_context, Config Loaded: $config_loaded";
    }

    private function summarize_handler_execution($results) {
        $configured_scenarios = $results['configuration_impact']['configured_scenarios'];
        $total_scenarios = $results['configuration_impact']['total_scenarios'];

        return "Configured scenarios: $configured_scenarios/$total_scenarios";
    }

    private function summarize_error_handling($results) {
        $score = $results['error_handling_quality']['error_handling_score'];
        return "Error handling score: $score%";
    }

    private function summarize_nonce_verification($results) {
        $valid_created = $results['valid_nonce_test']['nonce_created'] ? 'Yes' : 'No';
        $invalid_rejected = $results['invalid_nonce_test']['properly_rejected'] ? 'Yes' : 'No';

        return "Valid nonce created: $valid_created, Invalid rejected: $invalid_rejected";
    }

    private function summarize_permission_verification($results) {
        $can_manage = $results['admin_permission_test']['should_allow_access'] ? 'Yes' : 'No';
        return "Can manage options: $can_manage";
    }

    private function summarize_config_loading($results) {
        if (!isset($results['context_comparison']['comparison_possible']) || !$results['context_comparison']['comparison_possible']) {
            return "Comparison not possible due to errors";
        }

        $consistency_score = $results['context_comparison']['consistency_score'];
        return "Context consistency score: $consistency_score%";
    }

    /**
     * Recommendation Methods
     */

    private function get_registration_recommendation($results) {
        $missing = $results['summary']['missing_handlers'];

        if (empty($missing)) {
            return 'All AJAX handlers properly registered';
        } else {
            return 'Missing handlers: ' . implode(', ', $missing) . '. Check S3 Integration class instantiation.';
        }
    }

    private function get_callback_recommendation($results) {
        $issues = [];

        foreach ($results as $action => $data) {
            if (isset($data['error'])) {
                $issues[] = "$action: " . $data['error'];
            } elseif (isset($data['properly_configured']) && !$data['properly_configured']) {
                $issues[] = "$action: incorrect callback configuration";
            }
        }

        if (empty($issues)) {
            return 'All callback configurations are correct';
        } else {
            return 'Issues found: ' . implode('; ', $issues);
        }
    }

    private function get_ajax_simulation_recommendation($results) {
        if (isset($results['config_in_ajax']['error'])) {
            return 'CRITICAL: Configuration loading fails in AJAX context - ' . $results['config_in_ajax']['error'];
        }

        if (!$results['config_in_ajax']['is_configured']) {
            return 'CRITICAL: S3 not configured in AJAX context - this is the main issue';
        }

        return 'AJAX context simulation working properly';
    }

    private function get_execution_recommendation($results) {
        $impact = $results['configuration_impact'];

        if ($impact['error_scenarios'] > 0) {
            return 'Configuration errors detected in ' . $impact['error_scenarios'] . ' scenarios';
        }

        if (!$impact['configuration_consistency']) {
            return 'Configuration inconsistency detected across different scenarios';
        }

        return 'Handler execution working properly across all configuration scenarios';
    }

    private function get_error_handling_recommendation($results) {
        $score = $results['error_handling_quality']['error_handling_score'];

        if ($score < 50) {
            return 'Poor error handling - implement proper error responses';
        } elseif ($score < 80) {
            return 'Error handling needs improvement';
        } else {
            return 'Error handling appears adequate';
        }
    }

    private function get_nonce_recommendation($results) {
        if (!$results['valid_nonce_test']['nonce_created']) {
            return 'Nonce creation failing';
        }

        if (!$results['invalid_nonce_test']['properly_rejected']) {
            return 'Invalid nonce not properly rejected';
        }

        return 'Nonce verification working properly';
    }

    private function get_permission_recommendation($results) {
        if (!$results['admin_permission_test']['should_allow_access']) {
            return 'Current user lacks manage_options capability';
        }

        return 'Permission verification working properly';
    }

    private function get_config_loading_recommendation($results) {
        if (!isset($results['context_comparison']['comparison_possible']) || !$results['context_comparison']['comparison_possible']) {
            return 'Fix configuration loading errors to enable comparison';
        }

        $score = $results['context_comparison']['consistency_score'];

        if ($score < 100) {
            return "CRITICAL: Configuration inconsistent between contexts (score: $score%). This is likely the root cause.";
        }

        return 'Configuration loading consistent across contexts';
    }

    /**
     * Generate comprehensive test report
     */
    public function generate_test_report() {
        $report = [
            'timestamp' => current_time('mysql'),
            'test_count' => count($this->test_results),
            'results' => $this->test_results,
            'debug_info' => $this->debug_info,
            'overall_assessment' => $this->get_overall_assessment(),
            'action_items' => $this->get_action_items()
        ];

        error_log('H3TM S3 AJAX Tests: Test completed with ' . count($this->test_results) . ' test suites');
        error_log('H3TM S3 AJAX Tests: Overall Assessment: ' . $report['overall_assessment']);

        return $report;
    }

    private function get_overall_assessment() {
        $critical_issues = [];
        $warnings = [];

        foreach ($this->debug_info as $test_name => $info) {
            if (strpos($info['recommendation'], 'CRITICAL') === 0) {
                $critical_issues[] = "$test_name: " . $info['recommendation'];
            } elseif (strpos($info['recommendation'], 'Missing') === 0 || strpos($info['recommendation'], 'Issues') === 0) {
                $warnings[] = "$test_name: " . $info['recommendation'];
            }
        }

        if (!empty($critical_issues)) {
            return 'CRITICAL ISSUES FOUND: ' . implode('; ', $critical_issues);
        }

        if (!empty($warnings)) {
            return 'WARNINGS: ' . implode('; ', $warnings);
        }

        return 'All AJAX handler tests passed successfully';
    }

    private function get_action_items() {
        $actions = [];

        foreach ($this->debug_info as $test_name => $info) {
            if (strpos($info['recommendation'], 'CRITICAL') === 0 ||
                strpos($info['recommendation'], 'Missing') === 0 ||
                strpos($info['recommendation'], 'Fix') === 0) {
                $actions[] = "$test_name: " . $info['recommendation'];
            }
        }

        return $actions;
    }

    /**
     * Export test results to file
     */
    public function export_results_to_file($file_path = null) {
        if ($file_path === null) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/h3tm-s3-ajax-test-' . date('Y-m-d-H-i-s') . '.json';
        }

        $report = $this->generate_test_report();
        file_put_contents($file_path, json_encode($report, JSON_PRETTY_PRINT));

        return $file_path;
    }
}

// Allow direct execution for testing
if (defined('WP_CLI') || (defined('ABSPATH') && !defined('DOING_AJAX') && current_user_can('manage_options'))) {

    function run_h3tm_s3_ajax_tests() {
        $tester = new H3TM_S3_Ajax_Handler_Tests();
        $results = $tester->run_all_tests();

        if (defined('WP_CLI')) {
            WP_CLI::success('S3 AJAX Handler Tests completed');
            WP_CLI::log(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        }

        return $results;
    }

    // Auto-run if accessed directly
    if (isset($_GET['run_s3_ajax_tests'])) {
        run_h3tm_s3_ajax_tests();
    }
}