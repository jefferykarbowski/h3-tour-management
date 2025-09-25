<?php
/**
 * Test script for H3TM Bulletproof Configuration System
 *
 * This script provides comprehensive testing of the bulletproof configuration
 * system across all WordPress contexts.
 *
 * @package H3_Tour_Management
 * @since 1.5.5
 */

// WordPress environment check
if (!defined('ABSPATH')) {
    // Standalone execution for testing
    define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');
    require_once ABSPATH . 'wp-load.php';
}

// Include required test files
require_once dirname(__FILE__) . '/../includes/class-h3tm-bulletproof-config.php';
require_once dirname(__FILE__) . '/../includes/class-h3tm-config-adapter.php';
require_once dirname(__FILE__) . '/class-h3tm-config-validator.php';

/**
 * Bulletproof Configuration Test Runner
 */
class H3TM_Bulletproof_Config_Test {

    /**
     * Test results
     */
    private $test_results = [];

    /**
     * Configuration instances
     */
    private $bulletproof_config;
    private $config_adapter;
    private $config_validator;

    /**
     * Constructor
     */
    public function __construct() {
        $this->bulletproof_config = H3TM_Bulletproof_Config::getInstance();
        $this->config_adapter = H3TM_Config_Adapter::getInstance();
        $this->config_validator = new H3TM_Config_Validator();
    }

    /**
     * Run all tests
     */
    public function run_all_tests() {
        echo "<h1>H3TM Bulletproof Configuration System Test Results</h1>\n";
        echo "<p>Testing Date: " . current_time('mysql') . "</p>\n";
        echo "<p>WordPress Version: " . get_bloginfo('version') . "</p>\n";
        echo "<p>PHP Version: " . PHP_VERSION . "</p>\n";

        // Test 1: Basic Configuration Loading
        $this->test_basic_configuration_loading();

        // Test 2: Context Simulation
        $this->test_context_simulation();

        // Test 3: AJAX Context Reliability
        $this->test_ajax_context_reliability();

        // Test 4: Legacy Compatibility
        $this->test_legacy_compatibility();

        // Test 5: Validation System
        $this->test_validation_system();

        // Test 6: Performance Testing
        $this->test_performance();

        // Test 7: Error Handling
        $this->test_error_handling();

        // Test 8: Comprehensive Validation
        $this->test_comprehensive_validation();

        // Display results
        $this->display_test_results();

        return $this->test_results;
    }

    /**
     * Test basic configuration loading
     */
    private function test_basic_configuration_loading() {
        $test_name = 'Basic Configuration Loading';
        $start_time = microtime(true);

        try {
            // Clear cache to ensure fresh test
            $this->bulletproof_config->clear_cache();

            // Test configuration loading
            $config = $this->bulletproof_config->get_configuration();

            if (!is_array($config)) {
                throw new Exception('Configuration is not an array');
            }

            if (!isset($config['s3'])) {
                throw new Exception('S3 configuration section missing');
            }

            if (!isset($config['_metadata'])) {
                throw new Exception('Configuration metadata missing');
            }

            $s3_config = $config['s3'];
            $required_keys = ['bucket_name', 'region', 'access_key', 'secret_key', 'enabled'];

            foreach ($required_keys as $key) {
                if (!array_key_exists($key, $s3_config)) {
                    throw new Exception("Missing required S3 configuration key: $key");
                }
            }

            $execution_time = microtime(true) - $start_time;

            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Configuration loaded successfully with all required sections',
                'details' => [
                    'sections' => array_keys($config),
                    's3_keys' => array_keys($s3_config),
                    'bucket_name' => !empty($s3_config['bucket_name']) ? 'SET' : 'EMPTY',
                    'context' => $config['_metadata']['context'] ?? 'unknown'
                ],
                'execution_time' => $execution_time
            ];

        } catch (Exception $e) {
            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'details' => [],
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }

    /**
     * Test context simulation
     */
    private function test_context_simulation() {
        $test_name = 'Context Simulation';
        $start_time = microtime(true);

        try {
            // Test different context detection
            $contexts = [];

            // Current context
            $contexts['current'] = $this->bulletproof_config->get('_metadata.context', 'unknown');

            // Simulate AJAX context
            if (!defined('DOING_AJAX')) {
                define('DOING_AJAX', true);
            }

            // Clear cache and reload
            $this->bulletproof_config->clear_cache();
            $config_ajax = $this->bulletproof_config->get_configuration();
            $contexts['ajax'] = $config_ajax['_metadata']['context'] ?? 'unknown';

            // Test that configuration remains consistent
            $bucket1 = $this->bulletproof_config->get('s3.bucket_name', '');

            // Clear memory cache but not transient
            $reflection = new ReflectionClass('H3TM_Bulletproof_Config');
            $memory_cache_property = $reflection->getProperty('memory_cache');
            $memory_cache_property->setAccessible(true);
            $memory_cache_property->setValue(null, null);

            $bucket2 = $this->bulletproof_config->get('s3.bucket_name', '');

            if ($bucket1 !== $bucket2) {
                throw new Exception("Configuration inconsistent across context changes: '$bucket1' vs '$bucket2'");
            }

            $execution_time = microtime(true) - $start_time;

            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Context simulation working correctly',
                'details' => [
                    'contexts_detected' => $contexts,
                    'bucket_consistency' => $bucket1 === $bucket2,
                    'bucket_value' => $bucket1 ?: 'EMPTY'
                ],
                'execution_time' => $execution_time
            ];

        } catch (Exception $e) {
            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'details' => [],
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }

    /**
     * Test AJAX context reliability
     */
    private function test_ajax_context_reliability() {
        $test_name = 'AJAX Context Reliability';
        $start_time = microtime(true);

        try {
            // Test direct database access in AJAX context
            global $wpdb;

            // Create a test option
            $test_option = 'h3tm_test_ajax_config_' . time();
            $test_value = 'ajax_test_value_' . rand(1000, 9999);

            update_option($test_option, $test_value);

            // Test that bulletproof config can retrieve it
            $reflection = new ReflectionClass('H3TM_Bulletproof_Config');
            $method = $reflection->getMethod('get_option_with_fallback');
            $method->setAccessible(true);

            $retrieved_value = $method->invokeArgs($this->bulletproof_config, [$test_option, 'default']);

            // Clean up
            delete_option($test_option);

            if ($retrieved_value !== $test_value) {
                throw new Exception("AJAX option retrieval failed: expected '$test_value', got '$retrieved_value'");
            }

            // Test adapter compatibility in AJAX context
            $ajax_config = $this->config_adapter->get_s3_config_legacy();

            if (!is_array($ajax_config) || !isset($ajax_config['bucket_name'])) {
                throw new Exception('Config adapter failed in AJAX context');
            }

            $execution_time = microtime(true) - $start_time;

            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'AJAX context reliability confirmed',
                'details' => [
                    'option_retrieval' => 'SUCCESS',
                    'adapter_working' => 'YES',
                    'bucket_name' => !empty($ajax_config['bucket_name']) ? 'SET' : 'EMPTY',
                    'configured' => $ajax_config['configured'] ?? false
                ],
                'execution_time' => $execution_time
            ];

        } catch (Exception $e) {
            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'details' => [],
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }

    /**
     * Test legacy compatibility
     */
    private function test_legacy_compatibility() {
        $test_name = 'Legacy Compatibility';
        $start_time = microtime(true);

        try {
            // Test legacy config format
            $legacy_config = $this->config_adapter->get_s3_config_legacy();

            $required_legacy_keys = ['bucket_name', 'bucket', 'region', 'configured', 'enabled', 'threshold_mb'];
            foreach ($required_legacy_keys as $key) {
                if (!array_key_exists($key, $legacy_config)) {
                    throw new Exception("Missing legacy compatibility key: $key");
                }
            }

            // Test magic method access
            $bucket_magic = $this->config_adapter->bucket_name;
            $bucket_get = $this->config_adapter->get('bucket_name');

            if ($bucket_magic !== $bucket_get) {
                throw new Exception('Magic method inconsistent with get method');
            }

            // Test method aliases
            $config_alias = $this->config_adapter->get_configuration();
            if (!is_array($config_alias) || !isset($config_alias['bucket_name'])) {
                throw new Exception('Method alias not working');
            }

            // Test is_configured method
            $is_configured = $this->config_adapter->is_s3_configured();
            if (!is_bool($is_configured)) {
                throw new Exception('is_configured should return boolean');
            }

            $execution_time = microtime(true) - $start_time;

            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Legacy compatibility working correctly',
                'details' => [
                    'legacy_keys_present' => $required_legacy_keys,
                    'magic_method_working' => $bucket_magic === $bucket_get,
                    'method_aliases_working' => true,
                    'is_configured' => $is_configured
                ],
                'execution_time' => $execution_time
            ];

        } catch (Exception $e) {
            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'details' => [],
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }

    /**
     * Test validation system
     */
    private function test_validation_system() {
        $test_name = 'Validation System';
        $start_time = microtime(true);

        try {
            // Test validation structure
            $validation = $this->bulletproof_config->validate_s3_configuration();

            $required_validation_keys = ['valid', 'errors', 'warnings', 'tested_at'];
            foreach ($required_validation_keys as $key) {
                if (!array_key_exists($key, $validation)) {
                    throw new Exception("Missing validation key: $key");
                }
            }

            if (!is_bool($validation['valid'])) {
                throw new Exception('Validation valid flag should be boolean');
            }

            if (!is_array($validation['errors'])) {
                throw new Exception('Validation errors should be array');
            }

            // Test specific validation methods
            $reflection = new ReflectionClass('H3TM_Bulletproof_Config');

            // Test bucket name validation
            $bucket_method = $reflection->getMethod('is_valid_bucket_name');
            $bucket_method->setAccessible(true);

            $invalid_buckets = ['invalid..bucket', 'INVALID', 'xn--test', 'sthree-test'];
            foreach ($invalid_buckets as $invalid_bucket) {
                if ($bucket_method->invokeArgs($this->bulletproof_config, [$invalid_bucket])) {
                    throw new Exception("Should reject invalid bucket name: $invalid_bucket");
                }
            }

            $valid_buckets = ['valid-bucket-name', 'test-bucket-123', 'my-s3-bucket'];
            foreach ($valid_buckets as $valid_bucket) {
                if (!$bucket_method->invokeArgs($this->bulletproof_config, [$valid_bucket])) {
                    throw new Exception("Should accept valid bucket name: $valid_bucket");
                }
            }

            $execution_time = microtime(true) - $start_time;

            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Validation system working correctly',
                'details' => [
                    'validation_structure' => 'VALID',
                    'bucket_validation' => 'WORKING',
                    'current_validation' => $validation
                ],
                'execution_time' => $execution_time
            ];

        } catch (Exception $e) {
            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'details' => [],
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }

    /**
     * Test performance
     */
    private function test_performance() {
        $test_name = 'Performance Testing';
        $start_time = microtime(true);

        try {
            $iterations = 50;

            // Clear cache for fresh start
            $this->bulletproof_config->clear_cache();

            // Time first load (should be slower - builds cache)
            $first_load_start = microtime(true);
            $config1 = $this->bulletproof_config->get_configuration();
            $first_load_time = microtime(true) - $first_load_start;

            // Time subsequent loads (should be faster - uses cache)
            $cached_load_times = [];
            for ($i = 0; $i < $iterations; $i++) {
                $load_start = microtime(true);
                $config = $this->bulletproof_config->get_configuration();
                $cached_load_times[] = microtime(true) - $load_start;
            }

            $avg_cached_time = array_sum($cached_load_times) / count($cached_load_times);
            $avg_cached_time_ms = $avg_cached_time * 1000;

            // Performance should be reasonable
            if ($avg_cached_time_ms > 5) { // 5ms threshold
                throw new Exception("Performance issue: average cached load time {$avg_cached_time_ms}ms");
            }

            // Time key access performance
            $key_access_times = [];
            for ($i = 0; $i < $iterations; $i++) {
                $access_start = microtime(true);
                $bucket = $this->bulletproof_config->get('s3.bucket_name');
                $key_access_times[] = microtime(true) - $access_start;
            }

            $avg_key_access_time = array_sum($key_access_times) / count($key_access_times);
            $avg_key_access_ms = $avg_key_access_time * 1000;

            $execution_time = microtime(true) - $start_time;

            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Performance acceptable',
                'details' => [
                    'first_load_ms' => round($first_load_time * 1000, 3),
                    'avg_cached_load_ms' => round($avg_cached_time_ms, 3),
                    'avg_key_access_ms' => round($avg_key_access_ms, 3),
                    'iterations_tested' => $iterations,
                    'cache_efficiency' => round((($first_load_time - $avg_cached_time) / $first_load_time) * 100, 1) . '%'
                ],
                'execution_time' => $execution_time
            ];

        } catch (Exception $e) {
            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'details' => [],
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }

    /**
     * Test error handling
     */
    private function test_error_handling() {
        $test_name = 'Error Handling';
        $start_time = microtime(true);

        try {
            // Test graceful handling of missing keys
            $missing_value = $this->bulletproof_config->get('non.existent.key', 'default');
            if ($missing_value !== 'default') {
                throw new Exception('Default value not returned for missing key');
            }

            // Test invalid dot notation
            $invalid_value = $this->bulletproof_config->get('s3.non_existent_key', null);
            if ($invalid_value !== null) {
                throw new Exception('Null not returned for invalid key');
            }

            // Test connection test error handling
            $connection_test = $this->bulletproof_config->test_connection();
            if (!isset($connection_test['success'])) {
                throw new Exception('Connection test should return success flag');
            }

            // Test adapter error handling
            try {
                $this->config_adapter->non_existent_method();
                throw new Exception('Should throw exception for non-existent method');
            } catch (BadMethodCallException $e) {
                // Expected behavior
            }

            $execution_time = microtime(true) - $start_time;

            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Error handling working correctly',
                'details' => [
                    'missing_key_handling' => 'WORKING',
                    'invalid_key_handling' => 'WORKING',
                    'method_error_handling' => 'WORKING',
                    'connection_test_structure' => 'VALID'
                ],
                'execution_time' => $execution_time
            ];

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Should throw exception') !== false) {
                // This means the error handling test itself failed
                $this->test_results[] = [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => $e->getMessage(),
                    'details' => [],
                    'execution_time' => microtime(true) - $start_time
                ];
            } else {
                // Unexpected error
                $this->test_results[] = [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Unexpected error: ' . $e->getMessage(),
                    'details' => [],
                    'execution_time' => microtime(true) - $start_time
                ];
            }
        }
    }

    /**
     * Test comprehensive validation
     */
    private function test_comprehensive_validation() {
        $test_name = 'Comprehensive Validation';
        $start_time = microtime(true);

        try {
            // Run the validator's comprehensive test
            $validation_report = $this->config_validator->run_quick_validation();

            if (!isset($validation_report['summary'])) {
                throw new Exception('Validation report missing summary');
            }

            $summary = $validation_report['summary'];
            if (!isset($summary['total_tests'], $summary['passed'], $summary['failed'])) {
                throw new Exception('Validation summary missing required fields');
            }

            $execution_time = microtime(true) - $start_time;

            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Comprehensive validation completed',
                'details' => [
                    'validator_tests' => $summary['total_tests'],
                    'validator_passed' => $summary['passed'],
                    'validator_failed' => $summary['failed'],
                    'validator_success_rate' => $summary['success_rate'] . '%',
                    'validator_status' => $summary['overall_status']
                ],
                'execution_time' => $execution_time
            ];

        } catch (Exception $e) {
            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'details' => [],
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }

    /**
     * Display test results
     */
    private function display_test_results() {
        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, function($result) {
            return $result['status'] === 'PASS';
        }));
        $failed_tests = $total_tests - $passed_tests;
        $success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0;

        echo "<h2>Test Summary</h2>\n";
        echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
        echo "<tr><th>Metric</th><th>Value</th></tr>\n";
        echo "<tr><td>Total Tests</td><td>$total_tests</td></tr>\n";
        echo "<tr><td>Passed</td><td style='color: green;'>$passed_tests</td></tr>\n";
        echo "<tr><td>Failed</td><td style='color: red;'>$failed_tests</td></tr>\n";
        echo "<tr><td>Success Rate</td><td>$success_rate%</td></tr>\n";
        echo "<tr><td>Overall Status</td><td style='color: " . ($failed_tests === 0 ? 'green' : 'red') . ";'>" . ($failed_tests === 0 ? 'PASS' : 'FAIL') . "</td></tr>\n";
        echo "</table>\n";

        echo "<h2>Detailed Test Results</h2>\n";
        echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
        echo "<tr><th>Test</th><th>Status</th><th>Message</th><th>Execution Time (s)</th><th>Details</th></tr>\n";

        foreach ($this->test_results as $result) {
            $status_color = $result['status'] === 'PASS' ? 'green' : 'red';
            $details_html = '';

            if (!empty($result['details'])) {
                $details_html = '<pre>' . htmlspecialchars(print_r($result['details'], true)) . '</pre>';
            }

            echo "<tr>\n";
            echo "<td>{$result['test']}</td>\n";
            echo "<td style='color: $status_color;'>{$result['status']}</td>\n";
            echo "<td>{$result['message']}</td>\n";
            echo "<td>" . number_format($result['execution_time'], 4) . "</td>\n";
            echo "<td style='font-size: 10px;'>$details_html</td>\n";
            echo "</tr>\n";
        }

        echo "</table>\n";

        // Display debug information
        echo "<h2>Configuration Debug Information</h2>\n";
        $debug_info = $this->config_adapter->get_debug_info();
        echo "<pre>" . htmlspecialchars(print_r($debug_info, true)) . "</pre>\n";
    }
}

// Run the tests if this file is accessed directly
if (isset($_GET['run_test']) || php_sapi_name() === 'cli') {
    $test_runner = new H3TM_Bulletproof_Config_Test();
    $test_runner->run_all_tests();
}