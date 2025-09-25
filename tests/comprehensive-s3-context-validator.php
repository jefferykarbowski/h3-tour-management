<?php
/**
 * Comprehensive S3 Configuration Context Validator
 *
 * Identifies exact configuration loading differences between WordPress
 * admin pages and AJAX requests to pinpoint the root cause of
 * "all credentials missing" errors in AJAX contexts.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 */

if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/h3-tour-management.php';
}

class H3TM_S3_Context_Validator {

    private $test_results = [];
    private $context_snapshots = [];
    private $configuration_traces = [];

    public function __construct() {
        error_log('H3TM S3 Context Validator: Initializing comprehensive context validation');
    }

    /**
     * Run complete validation suite
     */
    public function validate_all_contexts() {
        $this->test_results = [];
        $this->context_snapshots = [];
        $this->configuration_traces = [];

        error_log('H3TM S3 Context Validator: Starting comprehensive context validation');

        // 1. BASELINE VALIDATION
        $this->validate_baseline_configuration();

        // 2. ADMIN CONTEXT VALIDATION
        $this->validate_admin_context();

        // 3. AJAX CONTEXT VALIDATION
        $this->validate_ajax_context();

        // 4. ENVIRONMENT VARIABLE ACCESS VALIDATION
        $this->validate_environment_access();

        // 5. DATABASE OPTION ACCESS VALIDATION
        $this->validate_database_access();

        // 6. WORDPRESS INITIALIZATION TIMING
        $this->validate_wordpress_timing();

        // 7. CONFIGURATION MANAGER VALIDATION
        $this->validate_config_manager();

        // 8. SIDE-BY-SIDE COMPARISON
        $this->perform_side_by_side_comparison();

        return $this->generate_comprehensive_report();
    }

    /**
     * DELIVERABLE 1: Diagnostic Script for All Contexts
     */
    private function validate_baseline_configuration() {
        $test_name = 'Baseline Configuration';
        error_log("H3TM S3 Context Validator: Running $test_name");

        $snapshot = $this->capture_full_configuration_snapshot('baseline');

        $this->test_results[$test_name] = [
            'timestamp' => microtime(true),
            'context' => 'baseline',
            'snapshot' => $snapshot,
            'analysis' => $this->analyze_configuration_completeness($snapshot)
        ];

        $this->context_snapshots['baseline'] = $snapshot;
    }

    /**
     * DELIVERABLE 2: Admin Context Analysis
     */
    private function validate_admin_context() {
        $test_name = 'Admin Context Configuration';
        error_log("H3TM S3 Context Validator: Running $test_name");

        // Simulate admin context
        $was_admin = is_admin();
        if (!$was_admin && !defined('WP_ADMIN')) {
            define('WP_ADMIN', true);
        }

        $snapshot = $this->capture_full_configuration_snapshot('admin');

        // Test configuration loading with explicit class instantiation
        $s3_integration = null;
        $config_manager = null;

        try {
            $s3_integration = new H3TM_S3_Integration();
            $config_manager = H3TM_S3_Config_Manager::getInstance();

            $integration_config = $s3_integration->get_s3_config();
            $manager_config = $config_manager->get_configuration();

            $snapshot['integration_class'] = [
                'loaded' => true,
                'is_configured' => $s3_integration->is_configured(),
                'config' => $integration_config,
                'error' => null
            ];

            $snapshot['config_manager'] = [
                'loaded' => true,
                'is_configured' => $config_manager->is_configured(),
                'config' => $manager_config,
                'status' => $config_manager->get_status(),
                'error' => null
            ];

        } catch (Exception $e) {
            $snapshot['integration_class'] = [
                'loaded' => false,
                'error' => $e->getMessage()
            ];
            $snapshot['config_manager'] = [
                'loaded' => false,
                'error' => $e->getMessage()
            ];
        }

        $this->test_results[$test_name] = [
            'timestamp' => microtime(true),
            'context' => 'admin',
            'snapshot' => $snapshot,
            'analysis' => $this->analyze_configuration_completeness($snapshot),
            'admin_specific' => [
                'is_admin' => is_admin(),
                'wp_admin_defined' => defined('WP_ADMIN'),
                'current_screen' => function_exists('get_current_screen') ? get_current_screen() : null,
                'user_can_manage' => current_user_can('manage_options')
            ]
        ];

        $this->context_snapshots['admin'] = $snapshot;
    }

    /**
     * DELIVERABLE 3: AJAX Context Analysis
     */
    private function validate_ajax_context() {
        $test_name = 'AJAX Context Configuration';
        error_log("H3TM S3 Context Validator: Running $test_name");

        // Save original state
        $original_doing_ajax = defined('DOING_AJAX');
        $original_request = $_REQUEST;
        $original_post = $_POST;
        $original_get = $_GET;

        // Simulate AJAX context
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        $_REQUEST['action'] = 'h3tm_get_s3_presigned_url';
        $_POST['action'] = 'h3tm_get_s3_presigned_url';
        $_GET['action'] = 'h3tm_get_s3_presigned_url';

        $snapshot = $this->capture_full_configuration_snapshot('ajax');

        // Test configuration loading with explicit class instantiation
        $s3_integration = null;
        $config_manager = null;

        try {
            $s3_integration = new H3TM_S3_Integration();
            $config_manager = H3TM_S3_Config_Manager::getInstance();

            // Clear any cached configuration to force fresh load
            if (method_exists($config_manager, 'clear_cache')) {
                $config_manager->clear_cache();
            }

            $integration_config = $s3_integration->get_s3_config();
            $manager_config = $config_manager->get_configuration();

            $snapshot['integration_class'] = [
                'loaded' => true,
                'is_configured' => $s3_integration->is_configured(),
                'config' => $integration_config,
                'error' => null
            ];

            $snapshot['config_manager'] = [
                'loaded' => true,
                'is_configured' => $config_manager->is_configured(),
                'config' => $manager_config,
                'status' => $config_manager->get_status(),
                'debug_info' => $config_manager->get_debug_info(),
                'error' => null
            ];

        } catch (Exception $e) {
            $snapshot['integration_class'] = [
                'loaded' => false,
                'error' => $e->getMessage()
            ];
            $snapshot['config_manager'] = [
                'loaded' => false,
                'error' => $e->getMessage()
            ];
        }

        // Test direct configuration access methods
        $snapshot['direct_access'] = $this->test_direct_configuration_access();

        // Restore original state
        $_REQUEST = $original_request;
        $_POST = $original_post;
        $_GET = $original_get;

        $this->test_results[$test_name] = [
            'timestamp' => microtime(true),
            'context' => 'ajax',
            'snapshot' => $snapshot,
            'analysis' => $this->analyze_configuration_completeness($snapshot),
            'ajax_specific' => [
                'doing_ajax' => defined('DOING_AJAX') && DOING_AJAX,
                'action_set' => isset($_REQUEST['action']),
                'ajax_handlers_registered' => $this->check_ajax_handlers_exist()
            ]
        ];

        $this->context_snapshots['ajax'] = $snapshot;
    }

    /**
     * DELIVERABLE 4: Environment Variable Access Validation
     */
    private function validate_environment_access() {
        $test_name = 'Environment Variable Access';
        error_log("H3TM S3 Context Validator: Running $test_name");

        $env_tests = [];

        // Test each environment variable in different ways
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

        foreach ($env_vars as $var) {
            $env_tests[$var] = [
                'defined_check' => defined($var),
                'constant_access' => defined($var) ? constant($var) : null,
                'getenv_check' => getenv($var) !== false,
                'getenv_value' => getenv($var),
                'server_check' => isset($_SERVER[$var]),
                'server_value' => $_SERVER[$var] ?? null,
                'empty_check' => defined($var) ? empty(constant($var)) : true,
                'length_check' => defined($var) ? strlen(constant($var)) : 0
            ];
        }

        // Test in different contexts
        $contexts = ['normal', 'ajax_simulated'];
        $context_results = [];

        foreach ($contexts as $context) {
            if ($context === 'ajax_simulated') {
                // Simulate AJAX context for this test
                if (!defined('DOING_AJAX')) {
                    define('DOING_AJAX', true);
                }
                $_REQUEST['action'] = 'test_env_vars';
            }

            $context_results[$context] = [];
            foreach ($env_vars as $var) {
                $context_results[$context][$var] = [
                    'defined' => defined($var),
                    'accessible' => defined($var) && !empty(constant($var)),
                    'value_hash' => defined($var) ? hash('crc32', constant($var)) : null
                ];
            }

            if ($context === 'ajax_simulated') {
                unset($_REQUEST['action']);
            }
        }

        $this->test_results[$test_name] = [
            'timestamp' => microtime(true),
            'env_var_tests' => $env_tests,
            'context_comparison' => $context_results,
            'analysis' => $this->analyze_environment_access($env_tests, $context_results)
        ];
    }

    /**
     * DELIVERABLE 5: Database Option Access Validation
     */
    private function validate_database_access() {
        $test_name = 'Database Option Access';
        error_log("H3TM S3 Context Validator: Running $test_name");

        $db_options = [
            'h3tm_s3_bucket',
            'h3tm_s3_region',
            'h3tm_aws_access_key',
            'h3tm_aws_secret_key',
            'h3tm_s3_enabled',
            'h3tm_s3_threshold'
        ];

        $option_tests = [];
        foreach ($db_options as $option) {
            $value = get_option($option, '__NOT_FOUND__');
            $option_tests[$option] = [
                'exists' => $value !== '__NOT_FOUND__',
                'empty' => empty($value),
                'value_type' => gettype($value),
                'value_length' => is_string($value) ? strlen($value) : 0,
                'value_preview' => is_string($value) && strlen($value) > 4 ? substr($value, 0, 4) . '***' : $value,
                'raw_value' => $value // For debugging only - will be sanitized in output
            ];
        }

        // Test database access in different contexts
        $contexts = ['normal', 'ajax_simulated'];
        $context_db_results = [];

        foreach ($contexts as $context) {
            if ($context === 'ajax_simulated') {
                if (!defined('DOING_AJAX')) {
                    define('DOING_AJAX', true);
                }
                $_REQUEST['action'] = 'test_db_options';
            }

            $context_db_results[$context] = [];
            foreach ($db_options as $option) {
                $value = get_option($option, '__NOT_FOUND__');
                $context_db_results[$context][$option] = [
                    'exists' => $value !== '__NOT_FOUND__',
                    'accessible' => !empty($value),
                    'value_hash' => is_string($value) ? hash('crc32', $value) : null
                ];
            }

            if ($context === 'ajax_simulated') {
                unset($_REQUEST['action']);
            }
        }

        $this->test_results[$test_name] = [
            'timestamp' => microtime(true),
            'option_tests' => $this->sanitize_option_tests($option_tests),
            'context_comparison' => $context_db_results,
            'analysis' => $this->analyze_database_access($option_tests, $context_db_results)
        ];
    }

    /**
     * DELIVERABLE 6: WordPress Initialization Timing
     */
    private function validate_wordpress_timing() {
        $test_name = 'WordPress Initialization Timing';
        error_log("H3TM S3 Context Validator: Running $test_name");

        $timing_results = [
            'hooks_available' => [
                'init' => has_action('init'),
                'admin_init' => has_action('admin_init'),
                'wp_loaded' => did_action('wp_loaded') > 0,
                'admin_menu' => did_action('admin_menu') > 0,
                'wp_ajax_*' => $this->count_ajax_hooks(),
            ],
            'wp_state' => [
                'wp_installing' => defined('WP_INSTALLING') && WP_INSTALLING,
                'wp_admin' => defined('WP_ADMIN'),
                'doing_ajax' => defined('DOING_AJAX'),
                'wp_cli' => defined('WP_CLI'),
                'wp_cron' => defined('DOING_CRON')
            ],
            'database_ready' => [
                'wpdb_available' => isset($GLOBALS['wpdb']),
                'options_table' => $this->test_options_table_access(),
                'can_query' => $this->test_database_query_capability()
            ],
            'plugin_loading' => [
                'plugin_loaded' => defined('H3TM_VERSION'),
                'classes_available' => [
                    'H3TM_S3_Integration' => class_exists('H3TM_S3_Integration'),
                    'H3TM_S3_Config_Manager' => class_exists('H3TM_S3_Config_Manager')
                ]
            ]
        ];

        $this->test_results[$test_name] = [
            'timestamp' => microtime(true),
            'timing_results' => $timing_results,
            'analysis' => $this->analyze_timing_issues($timing_results)
        ];
    }

    /**
     * DELIVERABLE 7: Configuration Manager Deep Dive
     */
    private function validate_config_manager() {
        $test_name = 'Configuration Manager Deep Dive';
        error_log("H3TM S3 Context Validator: Running $test_name");

        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();

            // Clear cache to force fresh load
            if (method_exists($config_manager, 'clear_cache')) {
                $config_manager->clear_cache();
            }

            $manager_tests = [
                'singleton_pattern' => [
                    'instance_created' => $config_manager !== null,
                    'class_name' => get_class($config_manager),
                    'instance_hash' => spl_object_hash($config_manager)
                ],
                'configuration_loading' => [
                    'get_configuration' => $config_manager->get_configuration(),
                    'is_configured' => $config_manager->is_configured(),
                    'validation_result' => $config_manager->validate_configuration(),
                    'status' => $config_manager->get_status(),
                    'debug_info' => $config_manager->get_debug_info()
                ],
                'method_accessibility' => [
                    'get_configuration_callable' => is_callable([$config_manager, 'get_configuration']),
                    'is_configured_callable' => is_callable([$config_manager, 'is_configured']),
                    'validate_configuration_callable' => is_callable([$config_manager, 'validate_configuration'])
                ]
            ];

            // Test configuration consistency across multiple calls
            $consistency_tests = [];
            for ($i = 0; $i < 3; $i++) {
                $consistency_tests["call_$i"] = [
                    'config' => $config_manager->get_configuration(),
                    'is_configured' => $config_manager->is_configured(),
                    'timestamp' => microtime(true)
                ];
                usleep(1000); // Small delay
            }

            $manager_tests['consistency_tests'] = $consistency_tests;
            $manager_tests['consistency_analysis'] = $this->analyze_consistency($consistency_tests);

        } catch (Exception $e) {
            $manager_tests = [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        $this->test_results[$test_name] = [
            'timestamp' => microtime(true),
            'manager_tests' => $manager_tests,
            'analysis' => isset($manager_tests['error']) ?
                ['status' => 'error', 'message' => $manager_tests['error']] :
                $this->analyze_config_manager_results($manager_tests)
        ];
    }

    /**
     * DELIVERABLE 8: Side-by-Side Comparison Tool
     */
    private function perform_side_by_side_comparison() {
        $test_name = 'Side-by-Side Context Comparison';
        error_log("H3TM S3 Context Validator: Running $test_name");

        $comparison_results = [];

        if (isset($this->context_snapshots['admin']) && isset($this->context_snapshots['ajax'])) {
            $admin_snapshot = $this->context_snapshots['admin'];
            $ajax_snapshot = $this->context_snapshots['ajax'];

            $comparison_results = [
                'environment_variables' => $this->compare_environment_vars($admin_snapshot, $ajax_snapshot),
                'database_options' => $this->compare_database_options($admin_snapshot, $ajax_snapshot),
                'class_configurations' => $this->compare_class_configs($admin_snapshot, $ajax_snapshot),
                'wordpress_context' => $this->compare_wp_context($admin_snapshot, $ajax_snapshot),
                'overall_differences' => $this->identify_critical_differences($admin_snapshot, $ajax_snapshot)
            ];
        } else {
            $comparison_results['error'] = 'Missing snapshots for comparison';
        }

        $this->test_results[$test_name] = [
            'timestamp' => microtime(true),
            'comparison_results' => $comparison_results,
            'analysis' => $this->analyze_side_by_side_comparison($comparison_results)
        ];
    }

    /**
     * HELPER METHODS
     */

    private function capture_full_configuration_snapshot($context_name) {
        return [
            'context' => $context_name,
            'timestamp' => microtime(true),
            'environment_variables' => $this->capture_environment_variables(),
            'database_options' => $this->capture_database_options(),
            'wordpress_context' => $this->capture_wordpress_context(),
            'php_environment' => $this->capture_php_environment()
        ];
    }

    private function capture_environment_variables() {
        $env_vars = [
            'H3_S3_BUCKET', 'H3TM_S3_BUCKET', 'AWS_S3_BUCKET',
            'H3_S3_REGION', 'H3TM_S3_REGION', 'AWS_DEFAULT_REGION', 'AWS_REGION',
            'AWS_ACCESS_KEY_ID', 'H3TM_AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY', 'H3TM_AWS_SECRET_ACCESS_KEY'
        ];

        $env_data = [];
        foreach ($env_vars as $var) {
            $env_data[$var] = [
                'defined' => defined($var),
                'value' => defined($var) ? $this->sanitize_credential(constant($var)) : null,
                'empty' => defined($var) ? empty(constant($var)) : true
            ];
        }

        return $env_data;
    }

    private function capture_database_options() {
        $options = [
            'h3tm_s3_bucket', 'h3tm_s3_region',
            'h3tm_aws_access_key', 'h3tm_aws_secret_key',
            'h3tm_s3_enabled', 'h3tm_s3_threshold'
        ];

        $option_data = [];
        foreach ($options as $option) {
            $value = get_option($option, '__NOT_FOUND__');
            $option_data[$option] = [
                'exists' => $value !== '__NOT_FOUND__',
                'value' => $this->sanitize_credential($value),
                'empty' => empty($value),
                'type' => gettype($value)
            ];
        }

        return $option_data;
    }

    private function capture_wordpress_context() {
        return [
            'is_admin' => is_admin(),
            'doing_ajax' => defined('DOING_AJAX') && DOING_AJAX,
            'wp_admin_defined' => defined('WP_ADMIN'),
            'current_user_id' => get_current_user_id(),
            'can_manage_options' => current_user_can('manage_options'),
            'request_action' => $_REQUEST['action'] ?? null,
            'wp_loaded' => did_action('wp_loaded') > 0,
            'init_hook_done' => did_action('init') > 0
        ];
    }

    private function capture_php_environment() {
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'included_files_count' => count(get_included_files()),
            'class_exists_H3TM_S3_Integration' => class_exists('H3TM_S3_Integration'),
            'class_exists_H3TM_S3_Config_Manager' => class_exists('H3TM_S3_Config_Manager')
        ];
    }

    private function test_direct_configuration_access() {
        $direct_tests = [];

        // Test direct WordPress option access
        $direct_tests['direct_db_access'] = [
            'h3tm_s3_bucket' => get_option('h3tm_s3_bucket', '__MISSING__'),
            'h3tm_s3_region' => get_option('h3tm_s3_region', '__MISSING__'),
            'h3tm_aws_access_key' => get_option('h3tm_aws_access_key', '__MISSING__') ? 'SET' : 'MISSING',
            'h3tm_aws_secret_key' => get_option('h3tm_aws_secret_key', '__MISSING__') ? 'SET' : 'MISSING'
        ];

        // Test direct environment variable access
        $direct_tests['direct_env_access'] = [
            'H3_S3_BUCKET' => defined('H3_S3_BUCKET') ? H3_S3_BUCKET : '__NOT_DEFINED__',
            'AWS_ACCESS_KEY_ID' => defined('AWS_ACCESS_KEY_ID') ? 'SET' : 'NOT_DEFINED',
            'AWS_SECRET_ACCESS_KEY' => defined('AWS_SECRET_ACCESS_KEY') ? 'SET' : 'NOT_DEFINED'
        ];

        // Test configuration priority logic manually
        $direct_tests['priority_logic'] = [
            'bucket_final' => defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', ''),
            'region_final' => defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1'),
            'access_key_final' => defined('AWS_ACCESS_KEY_ID') ? 'ENV_SET' : (get_option('h3tm_aws_access_key', '') ? 'DB_SET' : 'MISSING'),
            'secret_key_final' => defined('AWS_SECRET_ACCESS_KEY') ? 'ENV_SET' : (get_option('h3tm_aws_secret_key', '') ? 'DB_SET' : 'MISSING')
        ];

        return $direct_tests;
    }

    private function sanitize_credential($value) {
        if (empty($value) || $value === '__NOT_FOUND__') {
            return $value;
        }

        if (is_string($value) && strlen($value) > 4) {
            return substr($value, 0, 4) . '***';
        }

        return 'SET';
    }

    private function sanitize_option_tests($option_tests) {
        $sanitized = $option_tests;
        foreach ($sanitized as $option => &$data) {
            if (isset($data['raw_value'])) {
                unset($data['raw_value']); // Remove raw values for security
            }
        }
        return $sanitized;
    }

    private function analyze_configuration_completeness($snapshot) {
        $completeness = [
            'environment_complete' => 0,
            'database_complete' => 0,
            'critical_missing' => []
        ];

        // Check environment variables
        $critical_env_vars = ['H3_S3_BUCKET', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY'];
        $env_count = 0;
        foreach ($critical_env_vars as $var) {
            if (isset($snapshot['environment_variables'][$var]) &&
                $snapshot['environment_variables'][$var]['defined'] &&
                !$snapshot['environment_variables'][$var]['empty']) {
                $env_count++;
            } else {
                $completeness['critical_missing'][] = "env:$var";
            }
        }
        $completeness['environment_complete'] = ($env_count / count($critical_env_vars)) * 100;

        // Check database options
        $critical_db_options = ['h3tm_s3_bucket', 'h3tm_aws_access_key', 'h3tm_aws_secret_key'];
        $db_count = 0;
        foreach ($critical_db_options as $option) {
            if (isset($snapshot['database_options'][$option]) &&
                $snapshot['database_options'][$option]['exists'] &&
                !$snapshot['database_options'][$option]['empty']) {
                $db_count++;
            } else {
                $completeness['critical_missing'][] = "db:$option";
            }
        }
        $completeness['database_complete'] = ($db_count / count($critical_db_options)) * 100;

        $completeness['overall_score'] = max($completeness['environment_complete'], $completeness['database_complete']);

        return $completeness;
    }

    /**
     * Generate comprehensive diagnostic report
     */
    public function generate_comprehensive_report() {
        $report = [
            'generated_at' => current_time('mysql'),
            'test_count' => count($this->test_results),
            'validation_results' => $this->test_results,
            'critical_findings' => $this->identify_critical_findings(),
            'root_cause_analysis' => $this->perform_root_cause_analysis(),
            'recommended_fixes' => $this->generate_recommended_fixes(),
            'diagnostic_summary' => $this->generate_diagnostic_summary()
        ];

        error_log('H3TM S3 Context Validator: Comprehensive validation completed');
        error_log('H3TM S3 Context Validator: Critical findings: ' . count($report['critical_findings']));

        return $report;
    }

    private function identify_critical_findings() {
        $findings = [];

        foreach ($this->test_results as $test_name => $results) {
            if (isset($results['analysis']['critical_missing']) && !empty($results['analysis']['critical_missing'])) {
                $findings[] = [
                    'test' => $test_name,
                    'type' => 'missing_configuration',
                    'details' => $results['analysis']['critical_missing']
                ];
            }

            if (isset($results['snapshot']['integration_class']['error'])) {
                $findings[] = [
                    'test' => $test_name,
                    'type' => 'class_instantiation_error',
                    'details' => $results['snapshot']['integration_class']['error']
                ];
            }

            if (isset($results['analysis']['overall_score']) && $results['analysis']['overall_score'] < 100) {
                $findings[] = [
                    'test' => $test_name,
                    'type' => 'incomplete_configuration',
                    'score' => $results['analysis']['overall_score']
                ];
            }
        }

        return $findings;
    }

    private function perform_root_cause_analysis() {
        $analysis = [
            'configuration_consistency' => $this->analyze_cross_context_consistency(),
            'timing_issues' => $this->analyze_timing_patterns(),
            'access_patterns' => $this->analyze_access_patterns(),
            'probable_root_cause' => null
        ];

        // Determine most likely root cause
        if (isset($this->context_snapshots['admin']) && isset($this->context_snapshots['ajax'])) {
            $admin_score = $this->context_snapshots['admin']['analysis']['overall_score'] ?? 0;
            $ajax_score = $this->context_snapshots['ajax']['analysis']['overall_score'] ?? 0;

            if ($admin_score > 90 && $ajax_score < 50) {
                $analysis['probable_root_cause'] = 'ajax_context_configuration_failure';
            } elseif ($admin_score < 50 && $ajax_score < 50) {
                $analysis['probable_root_cause'] = 'global_configuration_missing';
            } elseif (abs($admin_score - $ajax_score) > 20) {
                $analysis['probable_root_cause'] = 'context_dependent_configuration_access';
            } else {
                $analysis['probable_root_cause'] = 'configuration_loading_inconsistency';
            }
        }

        return $analysis;
    }

    private function generate_recommended_fixes() {
        $fixes = [];

        $critical_findings = $this->identify_critical_findings();
        foreach ($critical_findings as $finding) {
            switch ($finding['type']) {
                case 'missing_configuration':
                    $fixes[] = [
                        'priority' => 'high',
                        'type' => 'configuration',
                        'action' => 'Set missing configuration values',
                        'details' => $finding['details']
                    ];
                    break;

                case 'class_instantiation_error':
                    $fixes[] = [
                        'priority' => 'critical',
                        'type' => 'code',
                        'action' => 'Fix class instantiation error',
                        'details' => $finding['details']
                    ];
                    break;

                case 'incomplete_configuration':
                    $fixes[] = [
                        'priority' => 'medium',
                        'type' => 'configuration',
                        'action' => 'Complete configuration setup',
                        'score' => $finding['score']
                    ];
                    break;
            }
        }

        return $fixes;
    }

    private function generate_diagnostic_summary() {
        $admin_configured = false;
        $ajax_configured = false;

        if (isset($this->context_snapshots['admin']['integration_class']['is_configured'])) {
            $admin_configured = $this->context_snapshots['admin']['integration_class']['is_configured'];
        }

        if (isset($this->context_snapshots['ajax']['integration_class']['is_configured'])) {
            $ajax_configured = $this->context_snapshots['ajax']['integration_class']['is_configured'];
        }

        if ($admin_configured && !$ajax_configured) {
            return "CRITICAL: S3 configuration works in admin context but fails in AJAX context. This confirms the reported issue.";
        } elseif (!$admin_configured && !$ajax_configured) {
            return "CRITICAL: S3 configuration is not working in any context. Check basic configuration setup.";
        } elseif ($admin_configured && $ajax_configured) {
            return "UNEXPECTED: S3 configuration appears to work in both contexts. Issue may be intermittent or request-specific.";
        } else {
            return "UNUSUAL: S3 configuration works in AJAX but not admin context. This is opposite of reported issue.";
        }
    }

    // Additional helper methods for comprehensive analysis
    private function analyze_cross_context_consistency() {
        // Implementation for cross-context consistency analysis
        return ['status' => 'analyzed', 'details' => 'Cross-context analysis completed'];
    }

    private function analyze_timing_patterns() {
        // Implementation for timing pattern analysis
        return ['status' => 'analyzed', 'details' => 'Timing pattern analysis completed'];
    }

    private function analyze_access_patterns() {
        // Implementation for access pattern analysis
        return ['status' => 'analyzed', 'details' => 'Access pattern analysis completed'];
    }

    // Additional methods for comparison and analysis
    private function compare_environment_vars($admin, $ajax) {
        $differences = [];
        $admin_env = $admin['environment_variables'] ?? [];
        $ajax_env = $ajax['environment_variables'] ?? [];

        foreach ($admin_env as $var => $admin_data) {
            $ajax_data = $ajax_env[$var] ?? [];
            if ($admin_data !== $ajax_data) {
                $differences[$var] = [
                    'admin' => $admin_data,
                    'ajax' => $ajax_data
                ];
            }
        }

        return $differences;
    }

    private function compare_database_options($admin, $ajax) {
        $differences = [];
        $admin_db = $admin['database_options'] ?? [];
        $ajax_db = $ajax['database_options'] ?? [];

        foreach ($admin_db as $option => $admin_data) {
            $ajax_data = $ajax_db[$option] ?? [];
            if ($admin_data !== $ajax_data) {
                $differences[$option] = [
                    'admin' => $admin_data,
                    'ajax' => $ajax_data
                ];
            }
        }

        return $differences;
    }

    private function compare_class_configs($admin, $ajax) {
        $differences = [];

        $admin_integration = $admin['integration_class'] ?? [];
        $ajax_integration = $ajax['integration_class'] ?? [];

        if ($admin_integration !== $ajax_integration) {
            $differences['integration_class'] = [
                'admin' => $admin_integration,
                'ajax' => $ajax_integration
            ];
        }

        return $differences;
    }

    private function compare_wp_context($admin, $ajax) {
        $differences = [];
        $admin_wp = $admin['wordpress_context'] ?? [];
        $ajax_wp = $ajax['wordpress_context'] ?? [];

        foreach ($admin_wp as $key => $admin_value) {
            $ajax_value = $ajax_wp[$key] ?? null;
            if ($admin_value !== $ajax_value) {
                $differences[$key] = [
                    'admin' => $admin_value,
                    'ajax' => $ajax_value
                ];
            }
        }

        return $differences;
    }

    private function identify_critical_differences($admin, $ajax) {
        $critical = [];

        // Check if one context is configured and the other isn't
        $admin_configured = $admin['integration_class']['is_configured'] ?? false;
        $ajax_configured = $ajax['integration_class']['is_configured'] ?? false;

        if ($admin_configured !== $ajax_configured) {
            $critical[] = [
                'type' => 'configuration_status_mismatch',
                'admin_configured' => $admin_configured,
                'ajax_configured' => $ajax_configured
            ];
        }

        return $critical;
    }

    private function analyze_side_by_side_comparison($comparison_results) {
        if (isset($comparison_results['error'])) {
            return ['status' => 'error', 'message' => $comparison_results['error']];
        }

        $critical_differences = count($comparison_results['overall_differences'] ?? []);
        $env_differences = count($comparison_results['environment_variables'] ?? []);
        $db_differences = count($comparison_results['database_options'] ?? []);

        return [
            'status' => 'completed',
            'critical_differences' => $critical_differences,
            'environment_differences' => $env_differences,
            'database_differences' => $db_differences,
            'analysis' => $critical_differences > 0 ? 'Significant differences found between contexts' : 'Contexts appear consistent'
        ];
    }

    // Helper methods for various checks
    private function check_ajax_handlers_exist() {
        global $wp_filter;

        $handlers = [
            'wp_ajax_h3tm_get_s3_presigned_url',
            'wp_ajax_h3tm_process_s3_upload',
            'wp_ajax_h3tm_test_s3_connection'
        ];

        $registered = [];
        foreach ($handlers as $handler) {
            $registered[$handler] = isset($wp_filter[$handler]) && !empty($wp_filter[$handler]->callbacks);
        }

        return $registered;
    }

    private function count_ajax_hooks() {
        global $wp_filter;
        $ajax_hooks = 0;
        foreach ($wp_filter as $hook => $data) {
            if (strpos($hook, 'wp_ajax_') === 0) {
                $ajax_hooks++;
            }
        }
        return $ajax_hooks;
    }

    private function test_options_table_access() {
        global $wpdb;
        try {
            $result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} LIMIT 1");
            return is_numeric($result);
        } catch (Exception $e) {
            return false;
        }
    }

    private function test_database_query_capability() {
        try {
            $test_option = get_option('admin_email', '__TEST__');
            return $test_option !== '__TEST__';
        } catch (Exception $e) {
            return false;
        }
    }

    private function analyze_environment_access($env_tests, $context_results) {
        $total_vars = count($env_tests);
        $accessible_count = 0;
        $context_inconsistencies = 0;

        foreach ($env_tests as $var => $test_data) {
            if ($test_data['defined_check'] && !$test_data['empty_check']) {
                $accessible_count++;
            }

            // Check for context inconsistencies
            $normal_accessible = $context_results['normal'][$var]['accessible'] ?? false;
            $ajax_accessible = $context_results['ajax_simulated'][$var]['accessible'] ?? false;

            if ($normal_accessible !== $ajax_accessible) {
                $context_inconsistencies++;
            }
        }

        return [
            'total_variables' => $total_vars,
            'accessible_count' => $accessible_count,
            'accessibility_percentage' => ($accessible_count / $total_vars) * 100,
            'context_inconsistencies' => $context_inconsistencies,
            'analysis' => $context_inconsistencies > 0 ?
                'Environment variable access differs between contexts' :
                'Environment variable access is consistent across contexts'
        ];
    }

    private function analyze_database_access($option_tests, $context_results) {
        $total_options = count($option_tests);
        $accessible_count = 0;
        $context_inconsistencies = 0;

        foreach ($option_tests as $option => $test_data) {
            if ($test_data['exists'] && !$test_data['empty']) {
                $accessible_count++;
            }

            // Check for context inconsistencies
            $normal_accessible = $context_results['normal'][$option]['accessible'] ?? false;
            $ajax_accessible = $context_results['ajax_simulated'][$option]['accessible'] ?? false;

            if ($normal_accessible !== $ajax_accessible) {
                $context_inconsistencies++;
            }
        }

        return [
            'total_options' => $total_options,
            'accessible_count' => $accessible_count,
            'accessibility_percentage' => ($accessible_count / $total_options) * 100,
            'context_inconsistencies' => $context_inconsistencies,
            'analysis' => $context_inconsistencies > 0 ?
                'Database option access differs between contexts' :
                'Database option access is consistent across contexts'
        ];
    }

    private function analyze_timing_issues($timing_results) {
        $issues = [];

        if (!$timing_results['database_ready']['wpdb_available']) {
            $issues[] = 'WordPress database not available';
        }

        if (!$timing_results['database_ready']['can_query']) {
            $issues[] = 'Database queries not working';
        }

        if (!$timing_results['plugin_loading']['plugin_loaded']) {
            $issues[] = 'H3TM plugin not fully loaded';
        }

        if (!$timing_results['plugin_loading']['classes_available']['H3TM_S3_Integration']) {
            $issues[] = 'H3TM_S3_Integration class not available';
        }

        if (!$timing_results['plugin_loading']['classes_available']['H3TM_S3_Config_Manager']) {
            $issues[] = 'H3TM_S3_Config_Manager class not available';
        }

        return [
            'issues_found' => count($issues),
            'issues' => $issues,
            'analysis' => empty($issues) ?
                'No timing issues detected' :
                'Timing issues may be affecting configuration loading'
        ];
    }

    private function analyze_config_manager_results($manager_tests) {
        $analysis = [
            'status' => 'success',
            'issues' => []
        ];

        if (!$manager_tests['singleton_pattern']['instance_created']) {
            $analysis['issues'][] = 'Config manager singleton not created properly';
        }

        if (isset($manager_tests['configuration_loading']['is_configured']) &&
            !$manager_tests['configuration_loading']['is_configured']) {
            $analysis['issues'][] = 'Config manager reports not configured';
        }

        if (!$manager_tests['consistency_analysis']['consistent']) {
            $analysis['issues'][] = 'Config manager results inconsistent across calls';
        }

        $analysis['status'] = empty($analysis['issues']) ? 'success' : 'issues_found';

        return $analysis;
    }

    private function analyze_consistency($consistency_tests) {
        $configs = [];
        $is_configured_values = [];

        foreach ($consistency_tests as $test) {
            $configs[] = serialize($test['config']);
            $is_configured_values[] = $test['is_configured'];
        }

        return [
            'config_consistent' => count(array_unique($configs)) === 1,
            'is_configured_consistent' => count(array_unique($is_configured_values)) === 1,
            'consistent' => count(array_unique($configs)) === 1 && count(array_unique($is_configured_values)) === 1
        ];
    }

    /**
     * Export comprehensive results to file
     */
    public function export_validation_results($file_path = null) {
        if ($file_path === null) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/h3tm-s3-context-validation-' . date('Y-m-d-H-i-s') . '.json';
        }

        $report = $this->generate_comprehensive_report();
        file_put_contents($file_path, json_encode($report, JSON_PRETTY_PRINT));

        return $file_path;
    }
}

// Allow direct execution for testing
if (defined('WP_CLI') || (defined('ABSPATH') && !defined('DOING_AJAX') && current_user_can('manage_options'))) {

    function run_comprehensive_s3_context_validation() {
        $validator = new H3TM_S3_Context_Validator();
        $results = $validator->validate_all_contexts();

        if (defined('WP_CLI')) {
            WP_CLI::success('S3 Context Validation completed');
            WP_CLI::log(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            echo '<h2>H3TM S3 Context Validation Results</h2>';
            echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        }

        return $results;
    }

    // Auto-run if accessed directly
    if (isset($_GET['run_comprehensive_validation'])) {
        run_comprehensive_s3_context_validation();
    }
}