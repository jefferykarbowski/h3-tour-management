<?php
/**
 * S3 Configuration Validation Tests
 *
 * Tests for H3 Tour Management S3 integration configuration detection
 * and validation across different WordPress contexts.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/h3-tour-management.php';
}

class H3TM_S3_Configuration_Tests {

    private $test_results = [];
    private $debug_info = [];

    public function __construct() {
        error_log('H3TM S3 Config Tests: Initializing configuration tests');
    }

    /**
     * Run all configuration validation tests
     */
    public function run_all_tests() {
        $this->test_results = [];
        $this->debug_info = [];

        error_log('H3TM S3 Config Tests: Starting comprehensive configuration tests');

        // Test 1: Environment Variable Detection
        $this->test_environment_variable_detection();

        // Test 2: Database Option Detection
        $this->test_database_option_detection();

        // Test 3: Configuration Priority (Environment vs Database)
        $this->test_configuration_priority();

        // Test 4: S3 Integration Class Configuration Loading
        $this->test_s3_integration_configuration();

        // Test 5: AJAX Context Configuration Consistency
        $this->test_ajax_context_configuration();

        // Test 6: WordPress Admin Context Configuration
        $this->test_admin_context_configuration();

        // Test 7: Frontend Context Configuration
        $this->test_frontend_context_configuration();

        // Test 8: Configuration Validation Methods
        $this->test_configuration_validation_methods();

        return $this->generate_test_report();
    }

    /**
     * Test environment variable detection
     */
    private function test_environment_variable_detection() {
        $test_name = 'Environment Variable Detection';
        error_log("H3TM S3 Config Tests: Running $test_name");

        $results = [
            'H3_S3_BUCKET' => [
                'defined' => defined('H3_S3_BUCKET'),
                'value' => defined('H3_S3_BUCKET') ? H3_S3_BUCKET : null,
                'empty' => defined('H3_S3_BUCKET') ? empty(H3_S3_BUCKET) : true
            ],
            'H3_S3_REGION' => [
                'defined' => defined('H3_S3_REGION'),
                'value' => defined('H3_S3_REGION') ? H3_S3_REGION : null,
                'empty' => defined('H3_S3_REGION') ? empty(H3_S3_REGION) : true
            ],
            'AWS_ACCESS_KEY_ID' => [
                'defined' => defined('AWS_ACCESS_KEY_ID'),
                'value' => defined('AWS_ACCESS_KEY_ID') ? substr(AWS_ACCESS_KEY_ID, 0, 4) . '***' : null,
                'empty' => defined('AWS_ACCESS_KEY_ID') ? empty(AWS_ACCESS_KEY_ID) : true
            ],
            'AWS_SECRET_ACCESS_KEY' => [
                'defined' => defined('AWS_SECRET_ACCESS_KEY'),
                'value' => defined('AWS_SECRET_ACCESS_KEY') ? 'SET' : null,
                'empty' => defined('AWS_SECRET_ACCESS_KEY') ? empty(AWS_SECRET_ACCESS_KEY) : true
            ]
        ];

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_env_vars($results),
            'recommendation' => $this->get_env_var_recommendation($results)
        ];
    }

    /**
     * Test database option detection
     */
    private function test_database_option_detection() {
        $test_name = 'Database Option Detection';
        error_log("H3TM S3 Config Tests: Running $test_name");

        $results = [
            'h3tm_s3_bucket' => [
                'value' => get_option('h3tm_s3_bucket', ''),
                'empty' => empty(get_option('h3tm_s3_bucket', ''))
            ],
            'h3tm_s3_region' => [
                'value' => get_option('h3tm_s3_region', 'us-east-1'),
                'empty' => empty(get_option('h3tm_s3_region', ''))
            ],
            'h3tm_aws_access_key' => [
                'value' => get_option('h3tm_aws_access_key', '') ? substr(get_option('h3tm_aws_access_key', ''), 0, 4) . '***' : '',
                'empty' => empty(get_option('h3tm_aws_access_key', ''))
            ],
            'h3tm_aws_secret_key' => [
                'value' => get_option('h3tm_aws_secret_key', '') ? 'SET' : '',
                'empty' => empty(get_option('h3tm_aws_secret_key', ''))
            ]
        ];

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_db_options($results),
            'recommendation' => $this->get_db_option_recommendation($results)
        ];
    }

    /**
     * Test configuration priority logic
     */
    private function test_configuration_priority() {
        $test_name = 'Configuration Priority';
        error_log("H3TM S3 Config Tests: Running $test_name");

        $results = [
            'bucket_name' => [
                'env_defined' => defined('H3_S3_BUCKET'),
                'env_value' => defined('H3_S3_BUCKET') ? H3_S3_BUCKET : null,
                'db_value' => get_option('h3tm_s3_bucket', ''),
                'final_value' => defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', ''),
                'source' => defined('H3_S3_BUCKET') && !empty(H3_S3_BUCKET) ? 'environment' : 'database'
            ],
            'region' => [
                'env_defined' => defined('H3_S3_REGION'),
                'env_value' => defined('H3_S3_REGION') ? H3_S3_REGION : null,
                'db_value' => get_option('h3tm_s3_region', 'us-east-1'),
                'final_value' => defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1'),
                'source' => defined('H3_S3_REGION') && !empty(H3_S3_REGION) ? 'environment' : 'database'
            ],
            'access_key' => [
                'env_defined' => defined('AWS_ACCESS_KEY_ID'),
                'env_value' => defined('AWS_ACCESS_KEY_ID') ? substr(AWS_ACCESS_KEY_ID, 0, 4) . '***' : null,
                'db_value' => get_option('h3tm_aws_access_key', '') ? substr(get_option('h3tm_aws_access_key', ''), 0, 4) . '***' : '',
                'final_value' => defined('AWS_ACCESS_KEY_ID') ? substr(AWS_ACCESS_KEY_ID, 0, 4) . '***' : (get_option('h3tm_aws_access_key', '') ? substr(get_option('h3tm_aws_access_key', ''), 0, 4) . '***' : ''),
                'source' => defined('AWS_ACCESS_KEY_ID') && !empty(AWS_ACCESS_KEY_ID) ? 'environment' : 'database'
            ],
            'secret_key' => [
                'env_defined' => defined('AWS_SECRET_ACCESS_KEY'),
                'env_value' => defined('AWS_SECRET_ACCESS_KEY') ? 'SET' : null,
                'db_value' => get_option('h3tm_aws_secret_key', '') ? 'SET' : '',
                'final_value' => defined('AWS_SECRET_ACCESS_KEY') ? 'SET' : (get_option('h3tm_aws_secret_key', '') ? 'SET' : ''),
                'source' => defined('AWS_SECRET_ACCESS_KEY') && !empty(AWS_SECRET_ACCESS_KEY) ? 'environment' : 'database'
            ]
        ];

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_priority_logic($results),
            'recommendation' => $this->get_priority_recommendation($results)
        ];
    }

    /**
     * Test S3 Integration class configuration loading
     */
    private function test_s3_integration_configuration() {
        $test_name = 'S3 Integration Configuration';
        error_log("H3TM S3 Config Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();
            $config = $s3_integration->get_s3_config();

            $results = [
                'class_instantiated' => true,
                'is_configured' => $s3_integration->is_configured(),
                'config_data' => $config,
                'configuration_source' => $this->detect_configuration_source(),
                'error' => null
            ];

            // Test configuration consistency by creating multiple instances
            $instance2 = new H3TM_S3_Integration();
            $config2 = $instance2->get_s3_config();

            $results['consistency_check'] = [
                'configured_match' => ($s3_integration->is_configured() === $instance2->is_configured()),
                'bucket_match' => ($config['bucket'] === $config2['bucket']),
                'region_match' => ($config['region'] === $config2['region']),
                'consistent' => ($config === $config2)
            ];

        } catch (Exception $e) {
            $results = [
                'class_instantiated' => false,
                'error' => $e->getMessage(),
                'is_configured' => null,
                'config_data' => null
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_s3_integration($results),
            'recommendation' => $this->get_s3_integration_recommendation($results)
        ];
    }

    /**
     * Test configuration in AJAX context
     */
    private function test_ajax_context_configuration() {
        $test_name = 'AJAX Context Configuration';
        error_log("H3TM S3 Config Tests: Running $test_name");

        // Simulate AJAX context
        $_REQUEST['action'] = 'h3tm_get_s3_presigned_url';
        define('DOING_AJAX', true);

        try {
            $s3_integration = new H3TM_S3_Integration();

            $results = [
                'ajax_context' => defined('DOING_AJAX') && DOING_AJAX,
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config(),
                'handlers_registered' => $this->check_ajax_handlers_registered(),
                'error' => null
            ];

        } catch (Exception $e) {
            $results = [
                'ajax_context' => defined('DOING_AJAX') && DOING_AJAX,
                'error' => $e->getMessage(),
                'is_configured' => null,
                'config' => null
            ];
        }

        // Clean up
        unset($_REQUEST['action']);

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_ajax_context($results),
            'recommendation' => $this->get_ajax_context_recommendation($results)
        ];
    }

    /**
     * Test configuration in WordPress admin context
     */
    private function test_admin_context_configuration() {
        $test_name = 'Admin Context Configuration';
        error_log("H3TM S3 Config Tests: Running $test_name");

        // Simulate admin context if not already in admin
        $was_admin = is_admin();
        if (!$was_admin) {
            define('WP_ADMIN', true);
        }

        try {
            $s3_integration = new H3TM_S3_Integration();

            $results = [
                'admin_context' => is_admin(),
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config(),
                'can_manage_options' => current_user_can('manage_options'),
                'error' => null
            ];

        } catch (Exception $e) {
            $results = [
                'admin_context' => is_admin(),
                'error' => $e->getMessage(),
                'is_configured' => null,
                'config' => null
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_admin_context($results),
            'recommendation' => $this->get_admin_context_recommendation($results)
        ];
    }

    /**
     * Test configuration in frontend context
     */
    private function test_frontend_context_configuration() {
        $test_name = 'Frontend Context Configuration';
        error_log("H3TM S3 Config Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            $results = [
                'frontend_context' => !is_admin() && !defined('DOING_AJAX'),
                'is_configured' => $s3_integration->is_configured(),
                'config' => $s3_integration->get_s3_config(),
                'error' => null
            ];

        } catch (Exception $e) {
            $results = [
                'frontend_context' => !is_admin() && !defined('DOING_AJAX'),
                'error' => $e->getMessage(),
                'is_configured' => null,
                'config' => null
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_frontend_context($results),
            'recommendation' => $this->get_frontend_context_recommendation($results)
        ];
    }

    /**
     * Test configuration validation methods
     */
    private function test_configuration_validation_methods() {
        $test_name = 'Configuration Validation Methods';
        error_log("H3TM S3 Config Tests: Running $test_name");

        try {
            $s3_integration = new H3TM_S3_Integration();

            $results = [
                'is_configured' => $s3_integration->is_configured(),
                'config_components' => $this->test_individual_config_components($s3_integration),
                'validation_logic' => $this->test_validation_logic(),
                'error' => null
            ];

        } catch (Exception $e) {
            $results = [
                'error' => $e->getMessage(),
                'is_configured' => null
            ];
        }

        $this->test_results[$test_name] = $results;
        $this->debug_info[$test_name] = [
            'summary' => $this->summarize_validation_methods($results),
            'recommendation' => $this->get_validation_recommendation($results)
        ];
    }

    /**
     * Helper Methods
     */

    private function check_ajax_handlers_registered() {
        global $wp_filter;

        $handlers = [
            'wp_ajax_h3tm_get_s3_presigned_url' => false,
            'wp_ajax_h3tm_process_s3_upload' => false,
            'wp_ajax_h3tm_test_s3_connection' => false
        ];

        foreach ($handlers as $action => &$registered) {
            if (isset($wp_filter[$action])) {
                $registered = !empty($wp_filter[$action]->callbacks);
            }
        }

        return $handlers;
    }

    private function detect_configuration_source() {
        $sources = [];

        if (defined('H3_S3_BUCKET') && !empty(H3_S3_BUCKET)) {
            $sources['bucket'] = 'environment';
        } elseif (!empty(get_option('h3tm_s3_bucket', ''))) {
            $sources['bucket'] = 'database';
        } else {
            $sources['bucket'] = 'none';
        }

        if (defined('AWS_ACCESS_KEY_ID') && !empty(AWS_ACCESS_KEY_ID)) {
            $sources['credentials'] = 'environment';
        } elseif (!empty(get_option('h3tm_aws_access_key', ''))) {
            $sources['credentials'] = 'database';
        } else {
            $sources['credentials'] = 'none';
        }

        return $sources;
    }

    private function test_individual_config_components($s3_integration) {
        // Use reflection to access private properties for testing
        $reflection = new ReflectionClass($s3_integration);

        $components = [];

        try {
            $bucket_property = $reflection->getProperty('bucket_name');
            $bucket_property->setAccessible(true);
            $components['bucket_name'] = [
                'value' => $bucket_property->getValue($s3_integration),
                'empty' => empty($bucket_property->getValue($s3_integration))
            ];
        } catch (ReflectionException $e) {
            $components['bucket_name'] = ['error' => $e->getMessage()];
        }

        try {
            $access_key_property = $reflection->getProperty('access_key');
            $access_key_property->setAccessible(true);
            $access_key_value = $access_key_property->getValue($s3_integration);
            $components['access_key'] = [
                'value' => !empty($access_key_value) ? substr($access_key_value, 0, 4) . '***' : '',
                'empty' => empty($access_key_value)
            ];
        } catch (ReflectionException $e) {
            $components['access_key'] = ['error' => $e->getMessage()];
        }

        try {
            $secret_key_property = $reflection->getProperty('secret_key');
            $secret_key_property->setAccessible(true);
            $secret_key_value = $secret_key_property->getValue($s3_integration);
            $components['secret_key'] = [
                'value' => !empty($secret_key_value) ? 'SET' : '',
                'empty' => empty($secret_key_value)
            ];
        } catch (ReflectionException $e) {
            $components['secret_key'] = ['error' => $e->getMessage()];
        }

        return $components;
    }

    private function test_validation_logic() {
        $test_scenarios = [];

        // Test with empty values
        $test_scenarios['all_empty'] = $this->simulate_is_configured('', '', '');

        // Test with only bucket
        $test_scenarios['bucket_only'] = $this->simulate_is_configured('test-bucket', '', '');

        // Test with bucket and access key
        $test_scenarios['bucket_and_access'] = $this->simulate_is_configured('test-bucket', 'AKIA123', '');

        // Test with all values
        $test_scenarios['all_values'] = $this->simulate_is_configured('test-bucket', 'AKIA123', 'secret123');

        return $test_scenarios;
    }

    private function simulate_is_configured($bucket, $access_key, $secret_key) {
        $result = !empty($bucket) && !empty($access_key) && !empty($secret_key);
        return [
            'bucket' => $bucket,
            'access_key' => $access_key ? substr($access_key, 0, 4) . '***' : '',
            'secret_key' => $secret_key ? 'SET' : '',
            'is_configured' => $result
        ];
    }

    /**
     * Summary Methods
     */

    private function summarize_env_vars($results) {
        $defined_count = 0;
        $empty_count = 0;

        foreach ($results as $var => $data) {
            if ($data['defined']) $defined_count++;
            if ($data['empty']) $empty_count++;
        }

        return "Defined: $defined_count/4, Empty: $empty_count/4";
    }

    private function summarize_db_options($results) {
        $empty_count = 0;

        foreach ($results as $option => $data) {
            if ($data['empty']) $empty_count++;
        }

        return "Empty options: $empty_count/4";
    }

    private function summarize_priority_logic($results) {
        $env_sources = 0;
        $db_sources = 0;

        foreach ($results as $config => $data) {
            if ($data['source'] === 'environment') $env_sources++;
            if ($data['source'] === 'database') $db_sources++;
        }

        return "Environment: $env_sources/4, Database: $db_sources/4";
    }

    private function summarize_s3_integration($results) {
        if (isset($results['error'])) {
            return "Error: " . $results['error'];
        }

        $configured = $results['is_configured'] ? 'Yes' : 'No';
        $consistent = isset($results['consistency_check']) && $results['consistency_check']['consistent'] ? 'Yes' : 'No';

        return "Configured: $configured, Consistent: $consistent";
    }

    private function summarize_ajax_context($results) {
        if (isset($results['error'])) {
            return "Error: " . $results['error'];
        }

        $configured = $results['is_configured'] ? 'Yes' : 'No';
        $ajax = $results['ajax_context'] ? 'Yes' : 'No';

        return "AJAX Context: $ajax, Configured: $configured";
    }

    private function summarize_admin_context($results) {
        if (isset($results['error'])) {
            return "Error: " . $results['error'];
        }

        $configured = $results['is_configured'] ? 'Yes' : 'No';
        $admin = $results['admin_context'] ? 'Yes' : 'No';

        return "Admin Context: $admin, Configured: $configured";
    }

    private function summarize_frontend_context($results) {
        if (isset($results['error'])) {
            return "Error: " . $results['error'];
        }

        $configured = $results['is_configured'] ? 'Yes' : 'No';
        $frontend = $results['frontend_context'] ? 'Yes' : 'No';

        return "Frontend Context: $frontend, Configured: $configured";
    }

    private function summarize_validation_methods($results) {
        if (isset($results['error'])) {
            return "Error: " . $results['error'];
        }

        $configured = $results['is_configured'] ? 'Yes' : 'No';
        return "Validation Working: $configured";
    }

    /**
     * Recommendation Methods
     */

    private function get_env_var_recommendation($results) {
        $recommendations = [];

        foreach ($results as $var => $data) {
            if (!$data['defined'] || $data['empty']) {
                $recommendations[] = "Set $var in environment or wp-config.php";
            }
        }

        return empty($recommendations) ? 'All environment variables properly set' : implode('; ', $recommendations);
    }

    private function get_db_option_recommendation($results) {
        $recommendations = [];

        foreach ($results as $option => $data) {
            if ($data['empty']) {
                $recommendations[] = "Set $option in database options";
            }
        }

        return empty($recommendations) ? 'All database options properly set' : implode('; ', $recommendations);
    }

    private function get_priority_recommendation($results) {
        $env_count = 0;
        foreach ($results as $config => $data) {
            if ($data['source'] === 'environment') $env_count++;
        }

        if ($env_count === 0) {
            return 'Consider using environment variables for better security';
        } elseif ($env_count < 4) {
            return 'Mixed configuration sources detected - consider standardizing';
        } else {
            return 'Good: Using environment variables for all configuration';
        }
    }

    private function get_s3_integration_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix class instantiation error: ' . $results['error'];
        }

        if (!$results['is_configured']) {
            return 'Configure S3 credentials and bucket settings';
        }

        if (isset($results['consistency_check']) && !$results['consistency_check']['consistent']) {
            return 'Configuration inconsistency detected - check for race conditions';
        }

        return 'S3 integration properly configured and consistent';
    }

    private function get_ajax_context_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix AJAX context error: ' . $results['error'];
        }

        if (!$results['is_configured']) {
            return 'This is the main issue - S3 not configured in AJAX context';
        }

        return 'AJAX context configuration working properly';
    }

    private function get_admin_context_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix admin context error: ' . $results['error'];
        }

        return 'Admin context configuration working properly';
    }

    private function get_frontend_context_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix frontend context error: ' . $results['error'];
        }

        return 'Frontend context configuration working properly';
    }

    private function get_validation_recommendation($results) {
        if (isset($results['error'])) {
            return 'Fix validation method error: ' . $results['error'];
        }

        return 'Configuration validation methods working properly';
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

        // Log comprehensive results
        error_log('H3TM S3 Config Tests: Test completed with ' . count($this->test_results) . ' test suites');
        error_log('H3TM S3 Config Tests: Overall Assessment: ' . $report['overall_assessment']);

        return $report;
    }

    private function get_overall_assessment() {
        $issues = [];

        foreach ($this->test_results as $test_name => $results) {
            if (isset($results['error'])) {
                $issues[] = "$test_name: " . $results['error'];
            }
        }

        if (empty($issues)) {
            // Check for configuration issues
            if (isset($this->test_results['AJAX Context Configuration'])) {
                $ajax_results = $this->test_results['AJAX Context Configuration'];
                if (!$ajax_results['is_configured']) {
                    $issues[] = 'CRITICAL: S3 not configured in AJAX context';
                }
            }
        }

        if (empty($issues)) {
            return 'All tests passed - S3 configuration appears consistent across contexts';
        } else {
            return 'Issues detected: ' . implode('; ', $issues);
        }
    }

    private function get_action_items() {
        $actions = [];

        foreach ($this->debug_info as $test_name => $info) {
            if (strpos($info['recommendation'], 'Fix') === 0 || strpos($info['recommendation'], 'CRITICAL') === 0) {
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
            $file_path = $upload_dir['basedir'] . '/h3tm-s3-config-test-' . date('Y-m-d-H-i-s') . '.json';
        }

        $report = $this->generate_test_report();
        file_put_contents($file_path, json_encode($report, JSON_PRETTY_PRINT));

        return $file_path;
    }
}

// Allow direct execution for testing
if (defined('WP_CLI') || (defined('ABSPATH') && !defined('DOING_AJAX') && current_user_can('manage_options'))) {

    function run_h3tm_s3_config_tests() {
        $tester = new H3TM_S3_Configuration_Tests();
        $results = $tester->run_all_tests();

        if (defined('WP_CLI')) {
            WP_CLI::success('S3 Configuration Tests completed');
            WP_CLI::log(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
        }

        return $results;
    }

    // Auto-run if accessed directly
    if (isset($_GET['run_s3_config_tests'])) {
        run_h3tm_s3_config_tests();
    }
}