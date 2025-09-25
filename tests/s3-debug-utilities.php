<?php
/**
 * S3 Debug Utilities
 *
 * Collection of debugging tools to trace exact configuration loading paths
 * and identify divergence points between admin and AJAX contexts.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 */

if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/h3-tour-management.php';
}

class H3TM_S3_Debug_Utilities {

    private $trace_log = [];
    private $configuration_snapshots = [];
    private $debug_mode = false;

    public function __construct($debug_mode = false) {
        $this->debug_mode = $debug_mode;
        error_log('H3TM S3 Debug Utilities: Initializing debug utilities');
    }

    /**
     * DELIVERABLE 1: Quick Configuration Tester
     */
    public function quick_configuration_test() {
        $this->add_trace('Starting quick configuration test');

        $test_results = [
            'timestamp' => current_time('mysql'),
            'context_detection' => $this->detect_current_context(),
            'environment_variables' => $this->test_environment_variables(),
            'database_options' => $this->test_database_options(),
            'class_instantiation' => $this->test_class_instantiation(),
            'configuration_loading' => $this->test_configuration_loading(),
            'validation_results' => $this->test_configuration_validation(),
            'quick_diagnosis' => $this->quick_diagnosis()
        ];

        $this->add_trace('Quick configuration test completed');
        return $test_results;
    }

    /**
     * DELIVERABLE 2: Configuration Path Tracer
     */
    public function trace_configuration_loading_path() {
        $this->add_trace('Starting configuration loading path trace');

        $trace_results = [
            'timestamp' => current_time('mysql'),
            'context' => $this->detect_current_context(),
            'loading_steps' => [],
            'decision_points' => [],
            'final_configuration' => null,
            'trace_log' => []
        ];

        // Step 1: Environment variable resolution
        $trace_results['loading_steps']['environment_resolution'] = $this->trace_environment_resolution();

        // Step 2: Database option resolution
        $trace_results['loading_steps']['database_resolution'] = $this->trace_database_resolution();

        // Step 3: Priority logic execution
        $trace_results['loading_steps']['priority_logic'] = $this->trace_priority_logic();

        // Step 4: Configuration manager instantiation
        $trace_results['loading_steps']['config_manager_instantiation'] = $this->trace_config_manager_instantiation();

        // Step 5: S3 integration class loading
        $trace_results['loading_steps']['s3_integration_loading'] = $this->trace_s3_integration_loading();

        // Step 6: Final configuration compilation
        $trace_results['loading_steps']['final_compilation'] = $this->trace_final_compilation();

        $trace_results['trace_log'] = $this->trace_log;
        $this->add_trace('Configuration loading path trace completed');

        return $trace_results;
    }

    /**
     * DELIVERABLE 3: Context Difference Analyzer
     */
    public function analyze_context_differences() {
        $this->add_trace('Starting context difference analysis');

        $analysis = [
            'timestamp' => current_time('mysql'),
            'contexts_tested' => [],
            'difference_analysis' => [],
            'critical_findings' => [],
            'root_cause_indicators' => []
        ];

        // Test normal context
        $analysis['contexts_tested']['normal'] = $this->capture_context_snapshot('normal');

        // Test AJAX context
        $analysis['contexts_tested']['ajax'] = $this->capture_ajax_context_snapshot();

        // Compare contexts
        $analysis['difference_analysis'] = $this->compare_context_snapshots(
            $analysis['contexts_tested']['normal'],
            $analysis['contexts_tested']['ajax']
        );

        // Identify critical differences
        $analysis['critical_findings'] = $this->identify_critical_differences($analysis['difference_analysis']);

        // Determine root cause indicators
        $analysis['root_cause_indicators'] = $this->determine_root_cause_indicators($analysis['critical_findings']);

        $this->add_trace('Context difference analysis completed');
        return $analysis;
    }

    /**
     * DELIVERABLE 4: Real-time Configuration Monitor
     */
    public function monitor_configuration_changes($duration_seconds = 30) {
        $this->add_trace('Starting real-time configuration monitoring');

        $monitor_results = [
            'started_at' => current_time('mysql'),
            'duration_seconds' => $duration_seconds,
            'snapshots' => [],
            'changes_detected' => [],
            'stability_analysis' => []
        ];

        $start_time = time();
        $snapshot_interval = 2; // seconds
        $snapshot_count = 0;

        while ((time() - $start_time) < $duration_seconds) {
            $snapshot_count++;
            $current_snapshot = $this->capture_detailed_snapshot("monitor_$snapshot_count");
            $monitor_results['snapshots'][] = $current_snapshot;

            // Compare with previous snapshot if exists
            if ($snapshot_count > 1) {
                $previous_snapshot = $monitor_results['snapshots'][$snapshot_count - 2];
                $changes = $this->detect_configuration_changes($previous_snapshot, $current_snapshot);
                if (!empty($changes)) {
                    $monitor_results['changes_detected'][] = [
                        'at_snapshot' => $snapshot_count,
                        'changes' => $changes
                    ];
                }
            }

            sleep($snapshot_interval);
        }

        $monitor_results['ended_at'] = current_time('mysql');
        $monitor_results['total_snapshots'] = $snapshot_count;
        $monitor_results['stability_analysis'] = $this->analyze_configuration_stability($monitor_results);

        $this->add_trace('Real-time configuration monitoring completed');
        return $monitor_results;
    }

    /**
     * DELIVERABLE 5: Configuration Recovery Tester
     */
    public function test_configuration_recovery_methods() {
        $this->add_trace('Starting configuration recovery testing');

        $recovery_tests = [
            'timestamp' => current_time('mysql'),
            'baseline_test' => $this->establish_recovery_baseline(),
            'recovery_methods' => [],
            'effectiveness_analysis' => []
        ];

        // Test different recovery methods
        $recovery_methods = [
            'cache_clear' => function() { return $this->test_cache_clear_recovery(); },
            'class_reinstantiation' => function() { return $this->test_class_reinstantiation_recovery(); },
            'option_reload' => function() { return $this->test_option_reload_recovery(); },
            'transient_clear' => function() { return $this->test_transient_clear_recovery(); },
            'memory_refresh' => function() { return $this->test_memory_refresh_recovery(); }
        ];

        foreach ($recovery_methods as $method_name => $recovery_function) {
            $this->add_trace("Testing recovery method: $method_name");
            $recovery_tests['recovery_methods'][$method_name] = $recovery_function();
        }

        // Analyze effectiveness
        $recovery_tests['effectiveness_analysis'] = $this->analyze_recovery_effectiveness($recovery_tests['recovery_methods']);

        $this->add_trace('Configuration recovery testing completed');
        return $recovery_tests;
    }

    /**
     * Helper Methods for Quick Configuration Test
     */

    private function detect_current_context() {
        return [
            'is_admin' => is_admin(),
            'doing_ajax' => defined('DOING_AJAX') && DOING_AJAX,
            'doing_cron' => defined('DOING_CRON') && DOING_CRON,
            'wp_cli' => defined('WP_CLI') && WP_CLI,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'current_action' => $_REQUEST['action'] ?? null,
            'user_id' => get_current_user_id(),
            'can_manage_options' => current_user_can('manage_options'),
            'wordpress_loaded' => did_action('wp_loaded') > 0,
            'init_done' => did_action('init') > 0,
            'admin_init_done' => did_action('admin_init') > 0,
            'plugins_loaded' => did_action('plugins_loaded') > 0
        ];
    }

    private function test_environment_variables() {
        $env_vars = [
            'H3_S3_BUCKET',
            'H3TM_S3_BUCKET',
            'AWS_S3_BUCKET',
            'H3_S3_REGION',
            'H3TM_S3_REGION',
            'AWS_DEFAULT_REGION',
            'AWS_REGION',
            'AWS_ACCESS_KEY_ID',
            'H3TM_AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'H3TM_AWS_SECRET_ACCESS_KEY'
        ];

        $test_results = [];
        foreach ($env_vars as $var) {
            $test_results[$var] = [
                'defined' => defined($var),
                'constant_value' => defined($var) ? $this->safe_credential_preview(constant($var)) : null,
                'getenv_value' => getenv($var) !== false ? $this->safe_credential_preview(getenv($var)) : null,
                'server_value' => isset($_SERVER[$var]) ? $this->safe_credential_preview($_SERVER[$var]) : null,
                'accessible' => defined($var) && !empty(constant($var)),
                'source' => $this->determine_env_var_source($var)
            ];
        }

        return $test_results;
    }

    private function test_database_options() {
        $options = [
            'h3tm_s3_bucket',
            'h3tm_s3_region',
            'h3tm_aws_access_key',
            'h3tm_aws_secret_key',
            'h3tm_s3_enabled',
            'h3tm_s3_threshold'
        ];

        $test_results = [];
        foreach ($options as $option) {
            $value = get_option($option, '__NOT_FOUND__');
            $test_results[$option] = [
                'exists' => $value !== '__NOT_FOUND__',
                'value_preview' => $value !== '__NOT_FOUND__' ? $this->safe_credential_preview($value) : null,
                'empty' => empty($value),
                'type' => gettype($value),
                'accessible' => $value !== '__NOT_FOUND__' && !empty($value)
            ];
        }

        return $test_results;
    }

    private function test_class_instantiation() {
        $class_tests = [];

        // Test H3TM_S3_Integration
        try {
            $start_time = microtime(true);
            $s3_integration = new H3TM_S3_Integration();
            $end_time = microtime(true);

            $class_tests['H3TM_S3_Integration'] = [
                'instantiable' => true,
                'instantiation_time' => $end_time - $start_time,
                'class_methods' => get_class_methods($s3_integration),
                'has_get_s3_config' => method_exists($s3_integration, 'get_s3_config'),
                'has_is_configured' => method_exists($s3_integration, 'is_configured'),
                'error' => null
            ];
        } catch (Exception $e) {
            $class_tests['H3TM_S3_Integration'] = [
                'instantiable' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        // Test H3TM_S3_Config_Manager
        try {
            $start_time = microtime(true);
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $end_time = microtime(true);

            $class_tests['H3TM_S3_Config_Manager'] = [
                'instantiable' => true,
                'instantiation_time' => $end_time - $start_time,
                'is_singleton' => $config_manager === H3TM_S3_Config_Manager::getInstance(),
                'class_methods' => get_class_methods($config_manager),
                'has_get_configuration' => method_exists($config_manager, 'get_configuration'),
                'has_is_configured' => method_exists($config_manager, 'is_configured'),
                'error' => null
            ];
        } catch (Exception $e) {
            $class_tests['H3TM_S3_Config_Manager'] = [
                'instantiable' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return $class_tests;
    }

    private function test_configuration_loading() {
        $loading_tests = [];

        // Test S3 Integration configuration loading
        try {
            $s3_integration = new H3TM_S3_Integration();
            $config = $s3_integration->get_s3_config();

            $loading_tests['s3_integration'] = [
                'success' => true,
                'config_loaded' => $config !== null,
                'is_configured' => $s3_integration->is_configured(),
                'config_structure' => $this->analyze_config_structure($config),
                'error' => null
            ];
        } catch (Exception $e) {
            $loading_tests['s3_integration'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        // Test Config Manager configuration loading
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $config = $config_manager->get_configuration();

            $loading_tests['config_manager'] = [
                'success' => true,
                'config_loaded' => $config !== null,
                'is_configured' => $config_manager->is_configured(),
                'config_structure' => $this->analyze_config_structure($config),
                'status' => $config_manager->get_status(),
                'error' => null
            ];
        } catch (Exception $e) {
            $loading_tests['config_manager'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        return $loading_tests;
    }

    private function test_configuration_validation() {
        $validation_tests = [];

        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $validation_result = $config_manager->validate_configuration();

            $validation_tests['config_manager_validation'] = [
                'success' => true,
                'validation_result' => $validation_result,
                'is_valid' => $validation_result['valid'] ?? false,
                'errors' => $validation_result['errors'] ?? [],
                'warnings' => $validation_result['warnings'] ?? []
            ];
        } catch (Exception $e) {
            $validation_tests['config_manager_validation'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        try {
            $s3_integration = new H3TM_S3_Integration();
            $is_configured = $s3_integration->is_configured();

            $validation_tests['s3_integration_validation'] = [
                'success' => true,
                'is_configured' => $is_configured
            ];
        } catch (Exception $e) {
            $validation_tests['s3_integration_validation'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        return $validation_tests;
    }

    private function quick_diagnosis() {
        $diagnosis = [
            'overall_status' => 'unknown',
            'critical_issues' => [],
            'warnings' => [],
            'recommendations' => []
        ];

        // Analyze the results to provide quick diagnosis
        $config_test = $this->test_configuration_loading();
        $env_test = $this->test_environment_variables();
        $db_test = $this->test_database_options();

        // Check for critical issues
        if (isset($config_test['s3_integration']['success']) && !$config_test['s3_integration']['success']) {
            $diagnosis['critical_issues'][] = 'S3 Integration class cannot be instantiated: ' . ($config_test['s3_integration']['error'] ?? 'Unknown error');
        }

        if (isset($config_test['config_manager']['success']) && !$config_test['config_manager']['success']) {
            $diagnosis['critical_issues'][] = 'Config Manager class cannot be instantiated: ' . ($config_test['config_manager']['error'] ?? 'Unknown error');
        }

        if (isset($config_test['s3_integration']['is_configured']) && !$config_test['s3_integration']['is_configured']) {
            $diagnosis['critical_issues'][] = 'S3 Integration reports not configured';
        }

        // Check configuration sources
        $env_configured = $this->has_complete_env_configuration($env_test);
        $db_configured = $this->has_complete_db_configuration($db_test);

        if (!$env_configured && !$db_configured) {
            $diagnosis['critical_issues'][] = 'No complete configuration found in environment variables or database';
        } elseif (!$env_configured && $db_configured) {
            $diagnosis['warnings'][] = 'Configuration only available in database (less secure)';
            $diagnosis['recommendations'][] = 'Consider using environment variables for better security';
        }

        // Set overall status
        if (empty($diagnosis['critical_issues'])) {
            $diagnosis['overall_status'] = 'healthy';
        } else {
            $diagnosis['overall_status'] = 'critical_issues_detected';
        }

        return $diagnosis;
    }

    /**
     * Helper Methods for Configuration Path Tracer
     */

    private function trace_environment_resolution() {
        $this->add_trace('Tracing environment variable resolution');

        $resolution_steps = [];
        $env_vars = [
            'bucket' => ['H3_S3_BUCKET', 'H3TM_S3_BUCKET', 'AWS_S3_BUCKET'],
            'region' => ['H3_S3_REGION', 'H3TM_S3_REGION', 'AWS_DEFAULT_REGION', 'AWS_REGION'],
            'access_key' => ['AWS_ACCESS_KEY_ID', 'H3TM_AWS_ACCESS_KEY_ID'],
            'secret_key' => ['AWS_SECRET_ACCESS_KEY', 'H3TM_AWS_SECRET_ACCESS_KEY']
        ];

        foreach ($env_vars as $config_key => $possible_vars) {
            $resolution_steps[$config_key] = [
                'possible_variables' => $possible_vars,
                'resolution_order' => [],
                'final_value' => null,
                'source' => null
            ];

            foreach ($possible_vars as $var) {
                $defined = defined($var);
                $value = $defined ? constant($var) : null;
                $empty = empty($value);

                $resolution_steps[$config_key]['resolution_order'][] = [
                    'variable' => $var,
                    'defined' => $defined,
                    'has_value' => !$empty,
                    'value_preview' => $this->safe_credential_preview($value),
                    'selected' => $defined && !$empty && $resolution_steps[$config_key]['final_value'] === null
                ];

                if ($defined && !$empty && $resolution_steps[$config_key]['final_value'] === null) {
                    $resolution_steps[$config_key]['final_value'] = $this->safe_credential_preview($value);
                    $resolution_steps[$config_key]['source'] = $var;
                }
            }
        }

        $this->add_trace('Environment variable resolution completed');
        return $resolution_steps;
    }

    private function trace_database_resolution() {
        $this->add_trace('Tracing database option resolution');

        $db_options = [
            'bucket' => 'h3tm_s3_bucket',
            'region' => 'h3tm_s3_region',
            'access_key' => 'h3tm_aws_access_key',
            'secret_key' => 'h3tm_aws_secret_key'
        ];

        $resolution_steps = [];
        foreach ($db_options as $config_key => $option_name) {
            $value = get_option($option_name, '__NOT_FOUND__');

            $resolution_steps[$config_key] = [
                'option_name' => $option_name,
                'exists' => $value !== '__NOT_FOUND__',
                'has_value' => !empty($value),
                'value_preview' => $value !== '__NOT_FOUND__' ? $this->safe_credential_preview($value) : null,
                'value_type' => gettype($value)
            ];
        }

        $this->add_trace('Database option resolution completed');
        return $resolution_steps;
    }

    private function trace_priority_logic() {
        $this->add_trace('Tracing configuration priority logic');

        $env_resolution = $this->trace_environment_resolution();
        $db_resolution = $this->trace_database_resolution();

        $priority_decisions = [];
        $config_keys = ['bucket', 'region', 'access_key', 'secret_key'];

        foreach ($config_keys as $key) {
            $env_available = isset($env_resolution[$key]) && $env_resolution[$key]['final_value'] !== null;
            $db_available = isset($db_resolution[$key]) && $db_resolution[$key]['has_value'];

            $priority_decisions[$key] = [
                'env_available' => $env_available,
                'db_available' => $db_available,
                'decision' => $env_available ? 'environment' : ($db_available ? 'database' : 'none'),
                'final_source' => $env_available ? $env_resolution[$key]['source'] :
                                 ($db_available ? $db_resolution[$key]['option_name'] : null),
                'final_value' => $env_available ? $env_resolution[$key]['final_value'] :
                                ($db_available ? $db_resolution[$key]['value_preview'] : null)
            ];
        }

        $this->add_trace('Configuration priority logic completed');
        return $priority_decisions;
    }

    private function trace_config_manager_instantiation() {
        $this->add_trace('Tracing config manager instantiation');

        try {
            $start_time = microtime(true);
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $end_time = microtime(true);

            $instantiation_trace = [
                'success' => true,
                'instantiation_time' => $end_time - $start_time,
                'is_singleton' => $config_manager === H3TM_S3_Config_Manager::getInstance(),
                'methods_available' => get_class_methods($config_manager),
                'configuration_loaded' => $config_manager->get_configuration() !== null,
                'error' => null
            ];
        } catch (Exception $e) {
            $instantiation_trace = [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        $this->add_trace('Config manager instantiation traced');
        return $instantiation_trace;
    }

    private function trace_s3_integration_loading() {
        $this->add_trace('Tracing S3 integration loading');

        try {
            $start_time = microtime(true);
            $s3_integration = new H3TM_S3_Integration();
            $end_time = microtime(true);

            $config = $s3_integration->get_s3_config();

            $loading_trace = [
                'success' => true,
                'instantiation_time' => $end_time - $start_time,
                'config_loaded' => $config !== null,
                'is_configured' => $s3_integration->is_configured(),
                'config_structure' => $this->analyze_config_structure($config),
                'methods_available' => get_class_methods($s3_integration),
                'error' => null
            ];
        } catch (Exception $e) {
            $loading_trace = [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        $this->add_trace('S3 integration loading traced');
        return $loading_trace;
    }

    private function trace_final_compilation() {
        $this->add_trace('Tracing final configuration compilation');

        $compilation_trace = [
            'config_manager_result' => null,
            's3_integration_result' => null,
            'results_match' => false,
            'compilation_issues' => []
        ];

        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $manager_config = $config_manager->get_configuration();
            $manager_configured = $config_manager->is_configured();

            $compilation_trace['config_manager_result'] = [
                'config' => $this->sanitize_config_for_trace($manager_config),
                'is_configured' => $manager_configured,
                'status' => $config_manager->get_status()
            ];
        } catch (Exception $e) {
            $compilation_trace['config_manager_result'] = ['error' => $e->getMessage()];
        }

        try {
            $s3_integration = new H3TM_S3_Integration();
            $integration_config = $s3_integration->get_s3_config();
            $integration_configured = $s3_integration->is_configured();

            $compilation_trace['s3_integration_result'] = [
                'config' => $this->sanitize_config_for_trace($integration_config),
                'is_configured' => $integration_configured
            ];
        } catch (Exception $e) {
            $compilation_trace['s3_integration_result'] = ['error' => $e->getMessage()];
        }

        // Check if results match
        if (isset($compilation_trace['config_manager_result']['is_configured']) &&
            isset($compilation_trace['s3_integration_result']['is_configured'])) {
            $compilation_trace['results_match'] =
                $compilation_trace['config_manager_result']['is_configured'] ===
                $compilation_trace['s3_integration_result']['is_configured'];
        }

        $this->add_trace('Final configuration compilation traced');
        return $compilation_trace;
    }

    /**
     * Helper Methods for Context Analysis
     */

    private function capture_context_snapshot($context_name) {
        $this->add_trace("Capturing context snapshot: $context_name");

        return [
            'context_name' => $context_name,
            'timestamp' => microtime(true),
            'wordpress_context' => $this->detect_current_context(),
            'environment_variables' => $this->test_environment_variables(),
            'database_options' => $this->test_database_options(),
            'class_instantiation' => $this->test_class_instantiation(),
            'configuration_loading' => $this->test_configuration_loading(),
            'validation_results' => $this->test_configuration_validation()
        ];
    }

    private function capture_ajax_context_snapshot() {
        $this->add_trace('Capturing AJAX context snapshot');

        // Save original state
        $original_doing_ajax = defined('DOING_AJAX');
        $original_request = $_REQUEST;

        // Simulate AJAX context
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        $_REQUEST['action'] = 'debug_s3_config';

        $snapshot = $this->capture_context_snapshot('ajax_simulated');

        // Restore original state
        $_REQUEST = $original_request;

        return $snapshot;
    }

    private function compare_context_snapshots($normal_snapshot, $ajax_snapshot) {
        $this->add_trace('Comparing context snapshots');

        $comparison = [
            'environment_variables' => $this->compare_environment_variables($normal_snapshot, $ajax_snapshot),
            'database_options' => $this->compare_database_options($normal_snapshot, $ajax_snapshot),
            'class_instantiation' => $this->compare_class_instantiation($normal_snapshot, $ajax_snapshot),
            'configuration_loading' => $this->compare_configuration_loading($normal_snapshot, $ajax_snapshot),
            'validation_results' => $this->compare_validation_results($normal_snapshot, $ajax_snapshot)
        ];

        return $comparison;
    }

    private function compare_environment_variables($normal, $ajax) {
        $differences = [];
        $normal_env = $normal['environment_variables'] ?? [];
        $ajax_env = $ajax['environment_variables'] ?? [];

        foreach ($normal_env as $var => $normal_data) {
            $ajax_data = $ajax_env[$var] ?? [];

            if ($normal_data['accessible'] !== ($ajax_data['accessible'] ?? false)) {
                $differences[$var] = [
                    'type' => 'accessibility_difference',
                    'normal' => $normal_data['accessible'],
                    'ajax' => $ajax_data['accessible'] ?? false
                ];
            }

            if ($normal_data['constant_value'] !== ($ajax_data['constant_value'] ?? null)) {
                $differences[$var] = [
                    'type' => 'value_difference',
                    'normal' => $normal_data['constant_value'],
                    'ajax' => $ajax_data['constant_value'] ?? null
                ];
            }
        }

        return $differences;
    }

    private function compare_database_options($normal, $ajax) {
        $differences = [];
        $normal_db = $normal['database_options'] ?? [];
        $ajax_db = $ajax['database_options'] ?? [];

        foreach ($normal_db as $option => $normal_data) {
            $ajax_data = $ajax_db[$option] ?? [];

            if ($normal_data['accessible'] !== ($ajax_data['accessible'] ?? false)) {
                $differences[$option] = [
                    'type' => 'accessibility_difference',
                    'normal' => $normal_data['accessible'],
                    'ajax' => $ajax_data['accessible'] ?? false
                ];
            }

            if ($normal_data['value_preview'] !== ($ajax_data['value_preview'] ?? null)) {
                $differences[$option] = [
                    'type' => 'value_difference',
                    'normal' => $normal_data['value_preview'],
                    'ajax' => $ajax_data['value_preview'] ?? null
                ];
            }
        }

        return $differences;
    }

    private function compare_class_instantiation($normal, $ajax) {
        $differences = [];
        $normal_classes = $normal['class_instantiation'] ?? [];
        $ajax_classes = $ajax['class_instantiation'] ?? [];

        foreach ($normal_classes as $class => $normal_data) {
            $ajax_data = $ajax_classes[$class] ?? [];

            if (($normal_data['instantiable'] ?? false) !== ($ajax_data['instantiable'] ?? false)) {
                $differences[$class] = [
                    'type' => 'instantiation_difference',
                    'normal' => $normal_data['instantiable'] ?? false,
                    'ajax' => $ajax_data['instantiable'] ?? false,
                    'normal_error' => $normal_data['error'] ?? null,
                    'ajax_error' => $ajax_data['error'] ?? null
                ];
            }
        }

        return $differences;
    }

    private function compare_configuration_loading($normal, $ajax) {
        $differences = [];
        $normal_loading = $normal['configuration_loading'] ?? [];
        $ajax_loading = $ajax['configuration_loading'] ?? [];

        foreach ($normal_loading as $loader => $normal_data) {
            $ajax_data = $ajax_loading[$loader] ?? [];

            if (($normal_data['success'] ?? false) !== ($ajax_data['success'] ?? false)) {
                $differences[$loader] = [
                    'type' => 'loading_success_difference',
                    'normal' => $normal_data['success'] ?? false,
                    'ajax' => $ajax_data['success'] ?? false
                ];
            }

            if (($normal_data['is_configured'] ?? false) !== ($ajax_data['is_configured'] ?? false)) {
                $differences[$loader . '_configured'] = [
                    'type' => 'configuration_status_difference',
                    'normal' => $normal_data['is_configured'] ?? false,
                    'ajax' => $ajax_data['is_configured'] ?? false
                ];
            }
        }

        return $differences;
    }

    private function compare_validation_results($normal, $ajax) {
        $differences = [];
        $normal_validation = $normal['validation_results'] ?? [];
        $ajax_validation = $ajax['validation_results'] ?? [];

        foreach ($normal_validation as $validator => $normal_data) {
            $ajax_data = $ajax_validation[$validator] ?? [];

            if (($normal_data['success'] ?? false) !== ($ajax_data['success'] ?? false)) {
                $differences[$validator] = [
                    'type' => 'validation_success_difference',
                    'normal' => $normal_data['success'] ?? false,
                    'ajax' => $ajax_data['success'] ?? false
                ];
            }
        }

        return $differences;
    }

    private function identify_critical_differences($difference_analysis) {
        $critical_findings = [];

        foreach ($difference_analysis as $category => $differences) {
            foreach ($differences as $item => $difference) {
                if ($difference['type'] === 'accessibility_difference' ||
                    $difference['type'] === 'configuration_status_difference' ||
                    $difference['type'] === 'instantiation_difference') {

                    $critical_findings[] = [
                        'category' => $category,
                        'item' => $item,
                        'difference_type' => $difference['type'],
                        'details' => $difference,
                        'severity' => $this->assess_difference_severity($difference)
                    ];
                }
            }
        }

        return $critical_findings;
    }

    private function determine_root_cause_indicators($critical_findings) {
        $indicators = [];

        $configuration_failures = 0;
        $instantiation_failures = 0;
        $accessibility_failures = 0;

        foreach ($critical_findings as $finding) {
            switch ($finding['difference_type']) {
                case 'configuration_status_difference':
                    $configuration_failures++;
                    break;
                case 'instantiation_difference':
                    $instantiation_failures++;
                    break;
                case 'accessibility_difference':
                    $accessibility_failures++;
                    break;
            }
        }

        if ($configuration_failures > 0) {
            $indicators[] = [
                'type' => 'configuration_context_dependency',
                'count' => $configuration_failures,
                'description' => 'Configuration status differs between admin and AJAX contexts'
            ];
        }

        if ($instantiation_failures > 0) {
            $indicators[] = [
                'type' => 'class_instantiation_context_dependency',
                'count' => $instantiation_failures,
                'description' => 'Class instantiation fails in specific contexts'
            ];
        }

        if ($accessibility_failures > 0) {
            $indicators[] = [
                'type' => 'data_accessibility_context_dependency',
                'count' => $accessibility_failures,
                'description' => 'Configuration data access differs between contexts'
            ];
        }

        return $indicators;
    }

    /**
     * Additional Helper Methods
     */

    private function add_trace($message) {
        $this->trace_log[] = [
            'timestamp' => microtime(true),
            'message' => $message,
            'memory_usage' => memory_get_usage(),
            'context' => $this->detect_current_context()['doing_ajax'] ? 'AJAX' : 'NORMAL'
        ];

        if ($this->debug_mode) {
            error_log("H3TM S3 Debug Trace: $message");
        }
    }

    private function safe_credential_preview($value) {
        if (empty($value) || !is_string($value)) {
            return $value;
        }

        if (strlen($value) > 8) {
            return substr($value, 0, 4) . '***' . substr($value, -2);
        } elseif (strlen($value) > 4) {
            return substr($value, 0, 4) . '***';
        } else {
            return 'SET';
        }
    }

    private function analyze_config_structure($config) {
        if (!is_array($config)) {
            return ['type' => gettype($config), 'structure' => 'not_array'];
        }

        $structure = [
            'type' => 'array',
            'keys' => array_keys($config),
            'key_count' => count($config),
            'has_bucket' => isset($config['bucket']) || isset($config['bucket_name']),
            'has_region' => isset($config['region']),
            'has_credentials' => (isset($config['access_key']) || isset($config['configured'])),
            'configured_field' => $config['configured'] ?? null
        ];

        return $structure;
    }

    private function determine_env_var_source($var) {
        if (defined($var)) {
            return 'defined_constant';
        } elseif (getenv($var) !== false) {
            return 'getenv';
        } elseif (isset($_SERVER[$var])) {
            return 'server_var';
        } else {
            return 'not_available';
        }
    }

    private function has_complete_env_configuration($env_test) {
        $required_vars = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY'];
        $bucket_vars = ['H3_S3_BUCKET', 'H3TM_S3_BUCKET', 'AWS_S3_BUCKET'];

        $has_credentials = true;
        foreach ($required_vars as $var) {
            if (!isset($env_test[$var]) || !$env_test[$var]['accessible']) {
                $has_credentials = false;
                break;
            }
        }

        $has_bucket = false;
        foreach ($bucket_vars as $var) {
            if (isset($env_test[$var]) && $env_test[$var]['accessible']) {
                $has_bucket = true;
                break;
            }
        }

        return $has_credentials && $has_bucket;
    }

    private function has_complete_db_configuration($db_test) {
        $required_options = ['h3tm_s3_bucket', 'h3tm_aws_access_key', 'h3tm_aws_secret_key'];

        foreach ($required_options as $option) {
            if (!isset($db_test[$option]) || !$db_test[$option]['accessible']) {
                return false;
            }
        }

        return true;
    }

    private function sanitize_config_for_trace($config) {
        if (!is_array($config)) {
            return $config;
        }

        $sanitized = $config;
        $credential_fields = ['access_key', 'secret_key', 'aws_access_key', 'aws_secret_key'];

        foreach ($credential_fields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = $this->safe_credential_preview($sanitized[$field]);
            }
        }

        return $sanitized;
    }

    private function assess_difference_severity($difference) {
        if ($difference['type'] === 'configuration_status_difference' ||
            $difference['type'] === 'instantiation_difference') {
            return 'critical';
        } elseif ($difference['type'] === 'accessibility_difference') {
            return 'high';
        } else {
            return 'medium';
        }
    }

    private function capture_detailed_snapshot($snapshot_id) {
        return [
            'id' => $snapshot_id,
            'timestamp' => microtime(true),
            'datetime' => current_time('mysql'),
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'configuration_test' => $this->quick_configuration_test()
        ];
    }

    private function detect_configuration_changes($previous, $current) {
        $changes = [];

        if ($previous['configuration_test']['quick_diagnosis']['overall_status'] !==
            $current['configuration_test']['quick_diagnosis']['overall_status']) {
            $changes[] = [
                'type' => 'overall_status_change',
                'from' => $previous['configuration_test']['quick_diagnosis']['overall_status'],
                'to' => $current['configuration_test']['quick_diagnosis']['overall_status']
            ];
        }

        return $changes;
    }

    private function analyze_configuration_stability($monitor_results) {
        return [
            'total_changes' => count($monitor_results['changes_detected']),
            'stable' => empty($monitor_results['changes_detected']),
            'analysis' => empty($monitor_results['changes_detected']) ?
                'Configuration remained stable during monitoring period' :
                'Configuration changes detected - may indicate instability'
        ];
    }

    // Recovery test methods
    private function establish_recovery_baseline() {
        return $this->quick_configuration_test();
    }

    private function test_cache_clear_recovery() {
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            if (method_exists($config_manager, 'clear_cache')) {
                $config_manager->clear_cache();
            }

            return [
                'method' => 'cache_clear',
                'success' => true,
                'post_recovery_test' => $this->quick_configuration_test()
            ];
        } catch (Exception $e) {
            return [
                'method' => 'cache_clear',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_class_reinstantiation_recovery() {
        try {
            $s3_integration = new H3TM_S3_Integration();
            return [
                'method' => 'class_reinstantiation',
                'success' => true,
                'post_recovery_test' => $this->quick_configuration_test()
            ];
        } catch (Exception $e) {
            return [
                'method' => 'class_reinstantiation',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_option_reload_recovery() {
        try {
            // Force option reload by clearing wp cache
            wp_cache_flush();
            return [
                'method' => 'option_reload',
                'success' => true,
                'post_recovery_test' => $this->quick_configuration_test()
            ];
        } catch (Exception $e) {
            return [
                'method' => 'option_reload',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_transient_clear_recovery() {
        try {
            // Clear all transients related to S3 configuration
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_h3tm_s3_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_h3tm_s3_%'");

            return [
                'method' => 'transient_clear',
                'success' => true,
                'post_recovery_test' => $this->quick_configuration_test()
            ];
        } catch (Exception $e) {
            return [
                'method' => 'transient_clear',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function test_memory_refresh_recovery() {
        try {
            // Force garbage collection
            gc_collect_cycles();

            return [
                'method' => 'memory_refresh',
                'success' => true,
                'post_recovery_test' => $this->quick_configuration_test()
            ];
        } catch (Exception $e) {
            return [
                'method' => 'memory_refresh',
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function analyze_recovery_effectiveness($recovery_methods) {
        $effectiveness = [];

        foreach ($recovery_methods as $method_name => $result) {
            if (isset($result['post_recovery_test']['quick_diagnosis']['overall_status'])) {
                $post_status = $result['post_recovery_test']['quick_diagnosis']['overall_status'];
                $effectiveness[$method_name] = [
                    'effective' => $post_status === 'healthy',
                    'post_status' => $post_status
                ];
            } else {
                $effectiveness[$method_name] = [
                    'effective' => false,
                    'error' => $result['error'] ?? 'Unknown error'
                ];
            }
        }

        return $effectiveness;
    }

    /**
     * Export debug results to file
     */
    public function export_debug_results($results, $test_name, $file_path = null) {
        if ($file_path === null) {
            $upload_dir = wp_upload_dir();
            $safe_test_name = preg_replace('/[^a-z0-9_-]/i', '_', strtolower($test_name));
            $file_path = $upload_dir['basedir'] . "/h3tm-s3-debug-$safe_test_name-" . date('Y-m-d-H-i-s') . '.json';
        }

        $debug_export = [
            'test_name' => $test_name,
            'generated_at' => current_time('mysql'),
            'debug_mode' => $this->debug_mode,
            'results' => $results,
            'trace_log' => $this->trace_log,
            'configuration_snapshots' => $this->configuration_snapshots
        ];

        file_put_contents($file_path, json_encode($debug_export, JSON_PRETTY_PRINT));
        return $file_path;
    }
}

// Allow direct execution for testing
if (defined('WP_CLI') || (defined('ABSPATH') && !defined('DOING_AJAX') && current_user_can('manage_options'))) {

    function run_s3_debug_utilities($test_type = 'quick') {
        $debug_utilities = new H3TM_S3_Debug_Utilities(true);

        switch ($test_type) {
            case 'quick':
                $results = $debug_utilities->quick_configuration_test();
                break;
            case 'trace':
                $results = $debug_utilities->trace_configuration_loading_path();
                break;
            case 'context':
                $results = $debug_utilities->analyze_context_differences();
                break;
            case 'monitor':
                $duration = $_GET['duration'] ?? 30;
                $results = $debug_utilities->monitor_configuration_changes($duration);
                break;
            case 'recovery':
                $results = $debug_utilities->test_configuration_recovery_methods();
                break;
            default:
                $results = $debug_utilities->quick_configuration_test();
        }

        if (defined('WP_CLI')) {
            WP_CLI::success("S3 Debug Utilities ($test_type) completed");
            WP_CLI::log(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            echo "<h2>H3TM S3 Debug Utilities - $test_type Test</h2>";
            echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        }

        return $results;
    }

    // Auto-run based on GET parameters
    if (isset($_GET['run_s3_debug'])) {
        $test_type = $_GET['test_type'] ?? 'quick';
        run_s3_debug_utilities($test_type);
    }
}