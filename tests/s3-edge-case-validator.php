<?php
/**
 * S3 Edge Case and Recovery Validator
 *
 * Tests various failure scenarios and recovery mechanisms for S3 configuration
 * to identify specific conditions that cause AJAX context failures.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 */

if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/h3-tour-management.php';
}

class H3TM_S3_Edge_Case_Validator {

    private $test_results = [];
    private $original_values = [];
    private $test_scenarios = [];

    public function __construct() {
        error_log('H3TM S3 Edge Case Validator: Initializing edge case testing');
        $this->backup_original_configuration();
    }

    public function __destruct() {
        $this->restore_original_configuration();
    }

    /**
     * Run comprehensive edge case validation
     */
    public function validate_edge_cases() {
        $this->test_results = [];

        error_log('H3TM S3 Edge Case Validator: Starting edge case validation');

        // Test different configuration scenarios
        $this->test_missing_bucket_scenario();
        $this->test_missing_credentials_scenario();
        $this->test_partial_configuration_scenario();
        $this->test_mixed_source_scenarios();
        $this->test_cache_interference_scenarios();
        $this->test_timing_dependent_scenarios();
        $this->test_memory_pressure_scenarios();
        $this->test_hook_timing_scenarios();
        $this->test_class_instantiation_scenarios();
        $this->test_recovery_mechanisms();

        return $this->generate_edge_case_report();
    }

    /**
     * Test with missing bucket configuration
     */
    private function test_missing_bucket_scenario() {
        $test_name = 'Missing Bucket Scenario';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        // Clear bucket configuration
        $this->temporarily_set_configuration([
            'h3tm_s3_bucket' => '',
            'h3tm_aws_access_key' => 'AKIA123456789EXAMPLE',
            'h3tm_aws_secret_key' => 'test-secret-key-example-123456789'
        ]);

        $results = $this->test_configuration_in_both_contexts($test_name);

        $this->test_results[$test_name] = [
            'scenario' => 'missing_bucket',
            'expected_behavior' => 'Should report not configured in both contexts',
            'results' => $results,
            'analysis' => $this->analyze_missing_component_behavior($results, 'bucket')
        ];
    }

    /**
     * Test with missing credentials
     */
    private function test_missing_credentials_scenario() {
        $test_name = 'Missing Credentials Scenario';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        // Clear credentials but keep bucket
        $this->temporarily_set_configuration([
            'h3tm_s3_bucket' => 'test-bucket-name',
            'h3tm_aws_access_key' => '',
            'h3tm_aws_secret_key' => ''
        ]);

        $results = $this->test_configuration_in_both_contexts($test_name);

        $this->test_results[$test_name] = [
            'scenario' => 'missing_credentials',
            'expected_behavior' => 'Should report not configured in both contexts',
            'results' => $results,
            'analysis' => $this->analyze_missing_component_behavior($results, 'credentials')
        ];
    }

    /**
     * Test partial configuration scenarios
     */
    private function test_partial_configuration_scenario() {
        $test_name = 'Partial Configuration Scenario';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        $partial_scenarios = [
            'bucket_and_access_only' => [
                'h3tm_s3_bucket' => 'test-bucket',
                'h3tm_aws_access_key' => 'AKIA123456789EXAMPLE',
                'h3tm_aws_secret_key' => ''
            ],
            'bucket_and_secret_only' => [
                'h3tm_s3_bucket' => 'test-bucket',
                'h3tm_aws_access_key' => '',
                'h3tm_aws_secret_key' => 'test-secret-key'
            ],
            'credentials_no_bucket' => [
                'h3tm_s3_bucket' => '',
                'h3tm_aws_access_key' => 'AKIA123456789EXAMPLE',
                'h3tm_aws_secret_key' => 'test-secret-key'
            ]
        ];

        $scenario_results = [];
        foreach ($partial_scenarios as $scenario_name => $config) {
            $this->temporarily_set_configuration($config);
            $scenario_results[$scenario_name] = $this->test_configuration_in_both_contexts($scenario_name);
        }

        $this->test_results[$test_name] = [
            'scenario' => 'partial_configuration',
            'expected_behavior' => 'All scenarios should report not configured consistently',
            'scenario_results' => $scenario_results,
            'analysis' => $this->analyze_partial_configuration_consistency($scenario_results)
        ];
    }

    /**
     * Test mixed source scenarios (env + db)
     */
    private function test_mixed_source_scenarios() {
        $test_name = 'Mixed Source Scenarios';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        // Test different combinations of environment variables vs database options
        $mixed_scenarios = [];

        // Scenario 1: Environment bucket, database credentials
        if (!defined('H3_S3_BUCKET')) {
            define('H3_S3_BUCKET', 'env-test-bucket');
        }
        $this->temporarily_set_configuration([
            'h3tm_s3_bucket' => 'db-test-bucket', // Should be overridden by env
            'h3tm_aws_access_key' => 'AKIA123456789EXAMPLE',
            'h3tm_aws_secret_key' => 'test-secret-key'
        ]);

        $mixed_scenarios['env_bucket_db_credentials'] = $this->test_configuration_in_both_contexts('env_bucket_db_credentials');

        // Test different priority scenarios
        $this->test_results[$test_name] = [
            'scenario' => 'mixed_sources',
            'expected_behavior' => 'Environment variables should take priority',
            'mixed_scenarios' => $mixed_scenarios,
            'analysis' => $this->analyze_mixed_source_priority($mixed_scenarios)
        ];
    }

    /**
     * Test cache interference scenarios
     */
    private function test_cache_interference_scenarios() {
        $test_name = 'Cache Interference Scenarios';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        $cache_scenarios = [];

        // Test with valid configuration first (to populate cache)
        $this->temporarily_set_configuration([
            'h3tm_s3_bucket' => 'valid-bucket',
            'h3tm_aws_access_key' => 'AKIA123456789EXAMPLE',
            'h3tm_aws_secret_key' => 'valid-secret-key'
        ]);

        // Create config manager instance to populate cache
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $initial_config = $config_manager->get_configuration();
            $cache_scenarios['initial_valid_config'] = [
                'configured' => $config_manager->is_configured(),
                'config' => $initial_config
            ];
        } catch (Exception $e) {
            $cache_scenarios['initial_valid_config'] = ['error' => $e->getMessage()];
        }

        // Now change configuration without clearing cache
        $this->temporarily_set_configuration([
            'h3tm_s3_bucket' => '',
            'h3tm_aws_access_key' => '',
            'h3tm_aws_secret_key' => ''
        ], false); // Don't clear cache

        // Test if cache is interfering
        try {
            $config_manager2 = H3TM_S3_Config_Manager::getInstance();
            $cached_config = $config_manager2->get_configuration();
            $cache_scenarios['after_config_change_no_clear'] = [
                'configured' => $config_manager2->is_configured(),
                'config' => $cached_config,
                'same_as_initial' => ($initial_config === $cached_config)
            ];
        } catch (Exception $e) {
            $cache_scenarios['after_config_change_no_clear'] = ['error' => $e->getMessage()];
        }

        // Clear cache and test again
        if (method_exists($config_manager, 'clear_cache')) {
            $config_manager->clear_cache();
        }

        try {
            $config_manager3 = H3TM_S3_Config_Manager::getInstance();
            $fresh_config = $config_manager3->get_configuration();
            $cache_scenarios['after_cache_clear'] = [
                'configured' => $config_manager3->is_configured(),
                'config' => $fresh_config,
                'different_from_cached' => ($cached_config !== $fresh_config)
            ];
        } catch (Exception $e) {
            $cache_scenarios['after_cache_clear'] = ['error' => $e->getMessage()];
        }

        $this->test_results[$test_name] = [
            'scenario' => 'cache_interference',
            'expected_behavior' => 'Cache should update when configuration changes',
            'cache_scenarios' => $cache_scenarios,
            'analysis' => $this->analyze_cache_interference($cache_scenarios)
        ];
    }

    /**
     * Test timing-dependent scenarios
     */
    private function test_timing_dependent_scenarios() {
        $test_name = 'Timing Dependent Scenarios';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        $timing_scenarios = [];

        // Test configuration loading at different WordPress hooks
        $hook_points = [
            'early_init' => did_action('init') === 0,
            'after_init' => did_action('init') > 0,
            'admin_init_done' => did_action('admin_init') > 0,
            'wp_loaded_done' => did_action('wp_loaded') > 0
        ];

        foreach ($hook_points as $point => $condition) {
            if ($condition) {
                $timing_scenarios["at_$point"] = $this->test_configuration_loading_timing($point);
            }
        }

        // Test rapid successive configuration calls
        $timing_scenarios['rapid_succession'] = $this->test_rapid_successive_calls();

        // Test configuration under memory pressure
        $timing_scenarios['memory_pressure'] = $this->test_under_memory_pressure();

        $this->test_results[$test_name] = [
            'scenario' => 'timing_dependent',
            'hook_points' => $hook_points,
            'timing_scenarios' => $timing_scenarios,
            'analysis' => $this->analyze_timing_dependencies($timing_scenarios)
        ];
    }

    /**
     * Test memory pressure scenarios
     */
    private function test_memory_pressure_scenarios() {
        $test_name = 'Memory Pressure Scenarios';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        $memory_scenarios = [];

        // Get baseline memory usage
        $baseline_memory = memory_get_usage();
        $baseline_peak = memory_get_peak_usage();

        // Test normal configuration loading
        $this->temporarily_set_configuration([
            'h3tm_s3_bucket' => 'memory-test-bucket',
            'h3tm_aws_access_key' => 'AKIA123456789EXAMPLE',
            'h3tm_aws_secret_key' => 'memory-test-secret-key'
        ]);

        $memory_scenarios['baseline'] = $this->test_configuration_with_memory_tracking('baseline');

        // Create artificial memory pressure
        $large_arrays = [];
        for ($i = 0; $i < 10; $i++) {
            $large_arrays[] = array_fill(0, 10000, 'memory_pressure_test_data_' . $i);
        }

        $memory_scenarios['under_pressure'] = $this->test_configuration_with_memory_tracking('under_pressure');

        // Clean up
        unset($large_arrays);
        gc_collect_cycles();

        $memory_scenarios['after_cleanup'] = $this->test_configuration_with_memory_tracking('after_cleanup');

        $this->test_results[$test_name] = [
            'scenario' => 'memory_pressure',
            'baseline_memory' => $baseline_memory,
            'baseline_peak' => $baseline_peak,
            'memory_scenarios' => $memory_scenarios,
            'analysis' => $this->analyze_memory_impact($memory_scenarios)
        ];
    }

    /**
     * Test hook timing scenarios
     */
    private function test_hook_timing_scenarios() {
        $test_name = 'Hook Timing Scenarios';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        $hook_scenarios = [];

        // Test configuration loading before and after various hooks
        $critical_hooks = [
            'plugins_loaded' => did_action('plugins_loaded'),
            'init' => did_action('init'),
            'admin_init' => did_action('admin_init'),
            'wp_loaded' => did_action('wp_loaded'),
            'admin_menu' => did_action('admin_menu')
        ];

        foreach ($critical_hooks as $hook => $action_count) {
            $hook_scenarios[$hook] = [
                'action_count' => $action_count,
                'hook_done' => $action_count > 0,
                'config_test' => $this->test_configuration_at_hook_state($hook)
            ];
        }

        // Test AJAX-specific hook states
        $ajax_hooks = [
            'wp_ajax_h3tm_get_s3_presigned_url',
            'wp_ajax_h3tm_process_s3_upload',
            'wp_ajax_h3tm_test_s3_connection'
        ];

        $hook_scenarios['ajax_handlers'] = [];
        foreach ($ajax_hooks as $ajax_hook) {
            $hook_scenarios['ajax_handlers'][$ajax_hook] = $this->check_ajax_hook_registration($ajax_hook);
        }

        $this->test_results[$test_name] = [
            'scenario' => 'hook_timing',
            'critical_hooks' => $critical_hooks,
            'hook_scenarios' => $hook_scenarios,
            'analysis' => $this->analyze_hook_timing_impact($hook_scenarios)
        ];
    }

    /**
     * Test class instantiation scenarios
     */
    private function test_class_instantiation_scenarios() {
        $test_name = 'Class Instantiation Scenarios';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        $instantiation_scenarios = [];

        // Test multiple instantiation patterns
        $patterns = [
            'direct_new' => function() { return new H3TM_S3_Integration(); },
            'singleton_call' => function() { return H3TM_S3_Integration::getInstance(); },
            'config_manager_singleton' => function() { return H3TM_S3_Config_Manager::getInstance(); }
        ];

        foreach ($patterns as $pattern_name => $instantiator) {
            try {
                $start_time = microtime(true);
                $instance = $instantiator();
                $end_time = microtime(true);

                $instantiation_scenarios[$pattern_name] = [
                    'success' => true,
                    'instance_created' => $instance !== null,
                    'class_name' => $instance ? get_class($instance) : null,
                    'instantiation_time' => $end_time - $start_time,
                    'memory_used' => memory_get_usage(),
                    'error' => null
                ];

                // Test method availability
                if ($instance && method_exists($instance, 'is_configured')) {
                    $instantiation_scenarios[$pattern_name]['is_configured'] = $instance->is_configured();
                }

                if ($instance && method_exists($instance, 'get_s3_config')) {
                    $instantiation_scenarios[$pattern_name]['config'] = $instance->get_s3_config();
                }

            } catch (Exception $e) {
                $instantiation_scenarios[$pattern_name] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ];
            }
        }

        // Test instantiation in different contexts
        $context_tests = [];

        // Normal context
        $context_tests['normal'] = $this->test_instantiation_in_context('normal');

        // AJAX context
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        $_REQUEST['action'] = 'test_instantiation';

        $context_tests['ajax'] = $this->test_instantiation_in_context('ajax');

        unset($_REQUEST['action']);

        $this->test_results[$test_name] = [
            'scenario' => 'class_instantiation',
            'instantiation_scenarios' => $instantiation_scenarios,
            'context_tests' => $context_tests,
            'analysis' => $this->analyze_instantiation_patterns($instantiation_scenarios, $context_tests)
        ];
    }

    /**
     * Test recovery mechanisms
     */
    private function test_recovery_mechanisms() {
        $test_name = 'Recovery Mechanisms';
        error_log("H3TM S3 Edge Case Validator: Running $test_name");

        $recovery_scenarios = [];

        // Test cache clearing recovery
        $this->temporarily_set_configuration([
            'h3tm_s3_bucket' => '',
            'h3tm_aws_access_key' => '',
            'h3tm_aws_secret_key' => ''
        ]);

        $recovery_scenarios['before_recovery'] = $this->test_configuration_in_both_contexts('before_recovery');

        // Apply recovery mechanism 1: Clear cache
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            if (method_exists($config_manager, 'clear_cache')) {
                $config_manager->clear_cache();
            }
        } catch (Exception $e) {
            $recovery_scenarios['cache_clear_error'] = $e->getMessage();
        }

        // Set valid configuration
        $this->temporarily_set_configuration([
            'h3tm_s3_bucket' => 'recovery-test-bucket',
            'h3tm_aws_access_key' => 'AKIA123456789EXAMPLE',
            'h3tm_aws_secret_key' => 'recovery-test-secret'
        ]);

        $recovery_scenarios['after_cache_clear'] = $this->test_configuration_in_both_contexts('after_cache_clear');

        // Test configuration reload recovery
        $recovery_scenarios['after_config_reload'] = $this->test_configuration_reload_recovery();

        // Test class re-instantiation recovery
        $recovery_scenarios['after_class_reinstantiation'] = $this->test_class_reinstantiation_recovery();

        $this->test_results[$test_name] = [
            'scenario' => 'recovery_mechanisms',
            'recovery_scenarios' => $recovery_scenarios,
            'analysis' => $this->analyze_recovery_effectiveness($recovery_scenarios)
        ];
    }

    /**
     * Helper Methods for Testing
     */

    private function backup_original_configuration() {
        $this->original_values = [
            'h3tm_s3_bucket' => get_option('h3tm_s3_bucket', ''),
            'h3tm_s3_region' => get_option('h3tm_s3_region', ''),
            'h3tm_aws_access_key' => get_option('h3tm_aws_access_key', ''),
            'h3tm_aws_secret_key' => get_option('h3tm_aws_secret_key', ''),
            'h3tm_s3_enabled' => get_option('h3tm_s3_enabled', false),
            'h3tm_s3_threshold' => get_option('h3tm_s3_threshold', 0)
        ];
    }

    private function restore_original_configuration() {
        foreach ($this->original_values as $option => $value) {
            if ($value === '') {
                delete_option($option);
            } else {
                update_option($option, $value);
            }
        }

        // Clear any caches
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            if (method_exists($config_manager, 'clear_cache')) {
                $config_manager->clear_cache();
            }
        } catch (Exception $e) {
            error_log('Error clearing cache during restore: ' . $e->getMessage());
        }
    }

    private function temporarily_set_configuration($config, $clear_cache = true) {
        foreach ($config as $option => $value) {
            if ($value === '') {
                delete_option($option);
            } else {
                update_option($option, $value);
            }
        }

        if ($clear_cache) {
            try {
                $config_manager = H3TM_S3_Config_Manager::getInstance();
                if (method_exists($config_manager, 'clear_cache')) {
                    $config_manager->clear_cache();
                }
            } catch (Exception $e) {
                error_log('Error clearing cache: ' . $e->getMessage());
            }
        }
    }

    private function test_configuration_in_both_contexts($scenario_name) {
        $results = [];

        // Test in normal context
        $results['normal_context'] = $this->test_single_configuration($scenario_name . '_normal');

        // Test in AJAX context
        $original_doing_ajax = defined('DOING_AJAX');
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        $_REQUEST['action'] = 'test_' . $scenario_name;

        $results['ajax_context'] = $this->test_single_configuration($scenario_name . '_ajax');

        unset($_REQUEST['action']);

        // Compare results
        $results['comparison'] = $this->compare_context_results(
            $results['normal_context'],
            $results['ajax_context']
        );

        return $results;
    }

    private function test_single_configuration($test_id) {
        try {
            $s3_integration = new H3TM_S3_Integration();
            $config_manager = H3TM_S3_Config_Manager::getInstance();

            return [
                'test_id' => $test_id,
                'timestamp' => microtime(true),
                'integration_class' => [
                    'loaded' => true,
                    'is_configured' => $s3_integration->is_configured(),
                    'config' => $s3_integration->get_s3_config()
                ],
                'config_manager' => [
                    'loaded' => true,
                    'is_configured' => $config_manager->is_configured(),
                    'config' => $config_manager->get_configuration(),
                    'status' => $config_manager->get_status()
                ],
                'context_info' => [
                    'is_admin' => is_admin(),
                    'doing_ajax' => defined('DOING_AJAX') && DOING_AJAX,
                    'memory_usage' => memory_get_usage(),
                    'memory_peak' => memory_get_peak_usage()
                ],
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'test_id' => $test_id,
                'timestamp' => microtime(true),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    private function compare_context_results($normal, $ajax) {
        if (isset($normal['error']) || isset($ajax['error'])) {
            return [
                'comparable' => false,
                'normal_error' => $normal['error'] ?? null,
                'ajax_error' => $ajax['error'] ?? null
            ];
        }

        return [
            'comparable' => true,
            'integration_configured_match' => $normal['integration_class']['is_configured'] === $ajax['integration_class']['is_configured'],
            'manager_configured_match' => $normal['config_manager']['is_configured'] === $ajax['config_manager']['is_configured'],
            'config_match' => $normal['integration_class']['config'] === $ajax['integration_class']['config'],
            'differences' => $this->find_configuration_differences($normal, $ajax)
        ];
    }

    private function find_configuration_differences($normal, $ajax) {
        $differences = [];

        if ($normal['integration_class']['config'] !== $ajax['integration_class']['config']) {
            $differences['integration_config'] = [
                'normal' => $normal['integration_class']['config'],
                'ajax' => $ajax['integration_class']['config']
            ];
        }

        if ($normal['config_manager']['config'] !== $ajax['config_manager']['config']) {
            $differences['manager_config'] = [
                'normal' => $normal['config_manager']['config'],
                'ajax' => $ajax['config_manager']['config']
            ];
        }

        return $differences;
    }

    /**
     * Analysis Methods
     */

    private function analyze_missing_component_behavior($results, $component) {
        if (!$results['comparison']['comparable']) {
            return [
                'status' => 'error',
                'message' => 'Cannot compare due to errors',
                'normal_error' => $results['comparison']['normal_error'] ?? null,
                'ajax_error' => $results['comparison']['ajax_error'] ?? null
            ];
        }

        $consistent = $results['comparison']['integration_configured_match'] &&
                     $results['comparison']['manager_configured_match'];

        $both_unconfigured = !$results['normal_context']['integration_class']['is_configured'] &&
                            !$results['ajax_context']['integration_class']['is_configured'];

        return [
            'status' => 'analyzed',
            'consistent_behavior' => $consistent,
            'both_unconfigured' => $both_unconfigured,
            'expected_result' => $both_unconfigured && $consistent,
            'component_missing' => $component,
            'analysis' => $both_unconfigured && $consistent ?
                "Both contexts correctly report not configured when $component is missing" :
                "Inconsistent behavior when $component is missing - this may be the issue"
        ];
    }

    private function analyze_partial_configuration_consistency($scenario_results) {
        $consistency_scores = [];
        $all_consistent = true;

        foreach ($scenario_results as $scenario => $results) {
            if (isset($results['comparison']['comparable']) && $results['comparison']['comparable']) {
                $consistent = $results['comparison']['integration_configured_match'] &&
                             $results['comparison']['manager_configured_match'];
                $consistency_scores[$scenario] = $consistent;
                if (!$consistent) {
                    $all_consistent = false;
                }
            } else {
                $consistency_scores[$scenario] = false;
                $all_consistent = false;
            }
        }

        return [
            'all_scenarios_consistent' => $all_consistent,
            'consistency_scores' => $consistency_scores,
            'analysis' => $all_consistent ?
                'All partial configuration scenarios behave consistently across contexts' :
                'Some partial configuration scenarios show inconsistent behavior between contexts'
        ];
    }

    private function analyze_mixed_source_priority($mixed_scenarios) {
        $priority_working = true;
        $analysis = [];

        foreach ($mixed_scenarios as $scenario => $results) {
            if (isset($results['comparison']['comparable']) && $results['comparison']['comparable']) {
                $analysis[$scenario] = [
                    'consistent' => $results['comparison']['integration_configured_match'],
                    'configured_normal' => $results['normal_context']['integration_class']['is_configured'] ?? false,
                    'configured_ajax' => $results['ajax_context']['integration_class']['is_configured'] ?? false
                ];

                if (!$results['comparison']['integration_configured_match']) {
                    $priority_working = false;
                }
            }
        }

        return [
            'priority_working_correctly' => $priority_working,
            'scenario_analysis' => $analysis,
            'recommendation' => $priority_working ?
                'Configuration priority is working correctly across contexts' :
                'Configuration priority differs between admin and AJAX contexts'
        ];
    }

    private function analyze_cache_interference($cache_scenarios) {
        $cache_issues = [];

        if (isset($cache_scenarios['after_config_change_no_clear']['same_as_initial']) &&
            $cache_scenarios['after_config_change_no_clear']['same_as_initial']) {
            $cache_issues[] = 'Cache not updating when configuration changes';
        }

        if (isset($cache_scenarios['after_cache_clear']['different_from_cached']) &&
            !$cache_scenarios['after_cache_clear']['different_from_cached']) {
            $cache_issues[] = 'Cache clear not effective';
        }

        return [
            'cache_issues_found' => !empty($cache_issues),
            'issues' => $cache_issues,
            'analysis' => empty($cache_issues) ?
                'Cache system working correctly' :
                'Cache system may be causing configuration inconsistencies'
        ];
    }

    private function analyze_timing_dependencies($timing_scenarios) {
        $timing_issues = [];

        foreach ($timing_scenarios as $scenario => $results) {
            if (isset($results['error'])) {
                $timing_issues[] = "Timing issue in $scenario: " . $results['error'];
            }
        }

        return [
            'timing_issues_found' => !empty($timing_issues),
            'issues' => $timing_issues,
            'analysis' => empty($timing_issues) ?
                'No timing dependencies detected' :
                'Configuration may be timing-dependent'
        ];
    }

    private function analyze_memory_impact($memory_scenarios) {
        $memory_effects = [];

        $baseline_success = isset($memory_scenarios['baseline']['success']) ? $memory_scenarios['baseline']['success'] : false;
        $pressure_success = isset($memory_scenarios['under_pressure']['success']) ? $memory_scenarios['under_pressure']['success'] : false;
        $cleanup_success = isset($memory_scenarios['after_cleanup']['success']) ? $memory_scenarios['after_cleanup']['success'] : false;

        if ($baseline_success && !$pressure_success) {
            $memory_effects[] = 'Configuration fails under memory pressure';
        }

        if (!$pressure_success && $cleanup_success) {
            $memory_effects[] = 'Configuration recovers after memory cleanup';
        }

        return [
            'memory_sensitive' => !empty($memory_effects),
            'effects' => $memory_effects,
            'baseline_success' => $baseline_success,
            'pressure_success' => $pressure_success,
            'cleanup_success' => $cleanup_success,
            'analysis' => empty($memory_effects) ?
                'Configuration not memory-sensitive' :
                'Configuration may be affected by memory pressure'
        ];
    }

    private function analyze_hook_timing_impact($hook_scenarios) {
        $hook_issues = [];

        foreach ($hook_scenarios as $hook => $scenario) {
            if ($hook !== 'ajax_handlers' && isset($scenario['config_test']['error'])) {
                $hook_issues[] = "Configuration fails at $hook hook";
            }
        }

        // Check AJAX handler registration
        if (isset($hook_scenarios['ajax_handlers'])) {
            foreach ($hook_scenarios['ajax_handlers'] as $handler => $registered) {
                if (!$registered) {
                    $hook_issues[] = "AJAX handler $handler not registered";
                }
            }
        }

        return [
            'hook_timing_issues' => !empty($hook_issues),
            'issues' => $hook_issues,
            'analysis' => empty($hook_issues) ?
                'Hook timing not affecting configuration' :
                'Configuration may be affected by WordPress hook timing'
        ];
    }

    private function analyze_instantiation_patterns($instantiation_scenarios, $context_tests) {
        $instantiation_issues = [];

        foreach ($instantiation_scenarios as $pattern => $result) {
            if (!$result['success']) {
                $instantiation_issues[] = "Pattern $pattern failed: " . $result['error'];
            }
        }

        // Compare instantiation success across contexts
        $normal_success = isset($context_tests['normal']['success']) ? $context_tests['normal']['success'] : false;
        $ajax_success = isset($context_tests['ajax']['success']) ? $context_tests['ajax']['success'] : false;

        if ($normal_success && !$ajax_success) {
            $instantiation_issues[] = 'Class instantiation fails in AJAX context';
        }

        return [
            'instantiation_issues' => !empty($instantiation_issues),
            'issues' => $instantiation_issues,
            'normal_success' => $normal_success,
            'ajax_success' => $ajax_success,
            'analysis' => empty($instantiation_issues) ?
                'Class instantiation working correctly' :
                'Class instantiation has context-dependent issues'
        ];
    }

    private function analyze_recovery_effectiveness($recovery_scenarios) {
        $recovery_effectiveness = [];

        $before_configured = $recovery_scenarios['before_recovery']['normal_context']['integration_class']['is_configured'] ?? false;
        $after_clear_configured = $recovery_scenarios['after_cache_clear']['normal_context']['integration_class']['is_configured'] ?? false;

        if (!$before_configured && $after_clear_configured) {
            $recovery_effectiveness[] = 'Cache clearing effective for recovery';
        }

        return [
            'recovery_methods_effective' => !empty($recovery_effectiveness),
            'effective_methods' => $recovery_effectiveness,
            'before_configured' => $before_configured,
            'after_clear_configured' => $after_clear_configured,
            'analysis' => empty($recovery_effectiveness) ?
                'Recovery mechanisms not effective' :
                'Some recovery mechanisms are effective'
        ];
    }

    /**
     * Additional helper methods for specific tests
     */

    private function test_configuration_loading_timing($hook_point) {
        try {
            $start_time = microtime(true);
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $config = $config_manager->get_configuration();
            $end_time = microtime(true);

            return [
                'hook_point' => $hook_point,
                'success' => true,
                'loading_time' => $end_time - $start_time,
                'is_configured' => $config_manager->is_configured(),
                'config' => $config
            ];
        } catch (Exception $e) {
            return [
                'hook_point' => $hook_point,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_rapid_successive_calls() {
        $results = [];
        $call_count = 5;

        for ($i = 0; $i < $call_count; $i++) {
            $start_time = microtime(true);
            try {
                $config_manager = H3TM_S3_Config_Manager::getInstance();
                $is_configured = $config_manager->is_configured();
                $end_time = microtime(true);

                $results["call_$i"] = [
                    'success' => true,
                    'is_configured' => $is_configured,
                    'call_time' => $end_time - $start_time,
                    'memory_usage' => memory_get_usage()
                ];
            } catch (Exception $e) {
                $results["call_$i"] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            usleep(100); // 0.1ms delay
        }

        return $results;
    }

    private function test_under_memory_pressure() {
        try {
            $start_memory = memory_get_usage();
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $is_configured = $config_manager->is_configured();
            $end_memory = memory_get_usage();

            return [
                'success' => true,
                'is_configured' => $is_configured,
                'start_memory' => $start_memory,
                'end_memory' => $end_memory,
                'memory_used' => $end_memory - $start_memory
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_configuration_with_memory_tracking($phase) {
        $start_memory = memory_get_usage();
        $start_peak = memory_get_peak_usage();

        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $is_configured = $config_manager->is_configured();
            $config = $config_manager->get_configuration();

            $end_memory = memory_get_usage();
            $end_peak = memory_get_peak_usage();

            return [
                'phase' => $phase,
                'success' => true,
                'is_configured' => $is_configured,
                'start_memory' => $start_memory,
                'end_memory' => $end_memory,
                'memory_used' => $end_memory - $start_memory,
                'start_peak' => $start_peak,
                'end_peak' => $end_peak,
                'peak_increase' => $end_peak - $start_peak
            ];
        } catch (Exception $e) {
            return [
                'phase' => $phase,
                'success' => false,
                'error' => $e->getMessage(),
                'start_memory' => $start_memory,
                'end_memory' => memory_get_usage()
            ];
        }
    }

    private function test_configuration_at_hook_state($hook) {
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            return [
                'hook' => $hook,
                'success' => true,
                'is_configured' => $config_manager->is_configured(),
                'config_loaded' => $config_manager->get_configuration() !== null
            ];
        } catch (Exception $e) {
            return [
                'hook' => $hook,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function check_ajax_hook_registration($hook) {
        global $wp_filter;
        return isset($wp_filter[$hook]) && !empty($wp_filter[$hook]->callbacks);
    }

    private function test_instantiation_in_context($context) {
        try {
            $s3_integration = new H3TM_S3_Integration();
            return [
                'context' => $context,
                'success' => true,
                'is_configured' => $s3_integration->is_configured()
            ];
        } catch (Exception $e) {
            return [
                'context' => $context,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_configuration_reload_recovery() {
        try {
            // Force a fresh configuration load
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            if (method_exists($config_manager, 'clear_cache')) {
                $config_manager->clear_cache();
            }

            $config = $config_manager->get_configuration();
            return [
                'success' => true,
                'is_configured' => $config_manager->is_configured(),
                'config' => $config
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_class_reinstantiation_recovery() {
        try {
            $s3_integration = new H3TM_S3_Integration();
            return [
                'success' => true,
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate comprehensive edge case report
     */
    public function generate_edge_case_report() {
        $report = [
            'generated_at' => current_time('mysql'),
            'test_scenarios' => count($this->test_results),
            'edge_case_results' => $this->test_results,
            'critical_edge_cases' => $this->identify_critical_edge_cases(),
            'failure_patterns' => $this->identify_failure_patterns(),
            'recovery_recommendations' => $this->generate_recovery_recommendations(),
            'edge_case_summary' => $this->generate_edge_case_summary()
        ];

        error_log('H3TM S3 Edge Case Validator: Edge case validation completed');
        error_log('H3TM S3 Edge Case Validator: Critical edge cases found: ' . count($report['critical_edge_cases']));

        return $report;
    }

    private function identify_critical_edge_cases() {
        $critical = [];

        foreach ($this->test_results as $test_name => $result) {
            // Check for context inconsistencies
            if (isset($result['results']['comparison']['integration_configured_match']) &&
                !$result['results']['comparison']['integration_configured_match']) {
                $critical[] = [
                    'test' => $test_name,
                    'type' => 'context_inconsistency',
                    'details' => 'Configuration behaves differently in admin vs AJAX context'
                ];
            }

            // Check for analysis issues
            if (isset($result['analysis']['status']) && $result['analysis']['status'] === 'error') {
                $critical[] = [
                    'test' => $test_name,
                    'type' => 'analysis_error',
                    'details' => $result['analysis']['message'] ?? 'Analysis failed'
                ];
            }

            // Check for specific failure patterns
            if (isset($result['analysis']['expected_result']) && !$result['analysis']['expected_result']) {
                $critical[] = [
                    'test' => $test_name,
                    'type' => 'unexpected_behavior',
                    'details' => $result['analysis']['analysis'] ?? 'Unexpected behavior detected'
                ];
            }
        }

        return $critical;
    }

    private function identify_failure_patterns() {
        $patterns = [];

        // Look for consistent failure patterns across tests
        $ajax_failures = 0;
        $admin_failures = 0;
        $cache_issues = 0;
        $timing_issues = 0;

        foreach ($this->test_results as $test_name => $result) {
            if (isset($result['results']['ajax_context']['error'])) {
                $ajax_failures++;
            }

            if (isset($result['results']['normal_context']['error'])) {
                $admin_failures++;
            }

            if (strpos($test_name, 'Cache') !== false && isset($result['analysis']['cache_issues_found']) &&
                $result['analysis']['cache_issues_found']) {
                $cache_issues++;
            }

            if (strpos($test_name, 'Timing') !== false && isset($result['analysis']['timing_issues_found']) &&
                $result['analysis']['timing_issues_found']) {
                $timing_issues++;
            }
        }

        if ($ajax_failures > $admin_failures) {
            $patterns[] = [
                'type' => 'ajax_context_failures',
                'count' => $ajax_failures,
                'description' => 'Configuration consistently fails in AJAX context'
            ];
        }

        if ($cache_issues > 0) {
            $patterns[] = [
                'type' => 'cache_related_issues',
                'count' => $cache_issues,
                'description' => 'Cache system causing configuration issues'
            ];
        }

        if ($timing_issues > 0) {
            $patterns[] = [
                'type' => 'timing_dependent_issues',
                'count' => $timing_issues,
                'description' => 'Configuration dependent on WordPress loading timing'
            ];
        }

        return $patterns;
    }

    private function generate_recovery_recommendations() {
        $recommendations = [];

        $critical_edge_cases = $this->identify_critical_edge_cases();
        $failure_patterns = $this->identify_failure_patterns();

        foreach ($failure_patterns as $pattern) {
            switch ($pattern['type']) {
                case 'ajax_context_failures':
                    $recommendations[] = [
                        'priority' => 'critical',
                        'action' => 'Fix AJAX context configuration loading',
                        'details' => 'Ensure S3 configuration is properly loaded in AJAX requests'
                    ];
                    break;

                case 'cache_related_issues':
                    $recommendations[] = [
                        'priority' => 'high',
                        'action' => 'Implement proper cache invalidation',
                        'details' => 'Ensure configuration cache is cleared when settings change'
                    ];
                    break;

                case 'timing_dependent_issues':
                    $recommendations[] = [
                        'priority' => 'medium',
                        'action' => 'Implement lazy loading for configuration',
                        'details' => 'Load configuration on-demand rather than during class initialization'
                    ];
                    break;
            }
        }

        return $recommendations;
    }

    private function generate_edge_case_summary() {
        $total_tests = count($this->test_results);
        $critical_issues = count($this->identify_critical_edge_cases());
        $failure_patterns = count($this->identify_failure_patterns());

        if ($critical_issues === 0) {
            return "All $total_tests edge case tests passed. No critical configuration issues detected.";
        }

        return "Found $critical_issues critical edge cases out of $total_tests tests with $failure_patterns failure patterns. Configuration issues confirmed.";
    }

    /**
     * Export edge case results to file
     */
    public function export_edge_case_results($file_path = null) {
        if ($file_path === null) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/h3tm-s3-edge-case-validation-' . date('Y-m-d-H-i-s') . '.json';
        }

        $report = $this->generate_edge_case_report();
        file_put_contents($file_path, json_encode($report, JSON_PRETTY_PRINT));

        return $file_path;
    }
}

// Allow direct execution for testing
if (defined('WP_CLI') || (defined('ABSPATH') && !defined('DOING_AJAX') && current_user_can('manage_options'))) {

    function run_s3_edge_case_validation() {
        $validator = new H3TM_S3_Edge_Case_Validator();
        $results = $validator->validate_edge_cases();

        if (defined('WP_CLI')) {
            WP_CLI::success('S3 Edge Case Validation completed');
            WP_CLI::log(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            echo '<h2>H3TM S3 Edge Case Validation Results</h2>';
            echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        }

        return $results;
    }

    // Auto-run if accessed directly
    if (isset($_GET['run_edge_case_validation'])) {
        run_s3_edge_case_validation();
    }
}