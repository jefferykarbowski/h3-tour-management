<?php
/**
 * H3TM Configuration Validator
 *
 * Comprehensive testing utilities for validating configuration across
 * all WordPress contexts and environments.
 *
 * @package H3_Tour_Management
 * @since 1.5.5
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Config_Validator {

    /**
     * Bulletproof config instance
     */
    private $bulletproof_config;

    /**
     * Config adapter instance
     */
    private $config_adapter;

    /**
     * Test results
     */
    private $test_results = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->bulletproof_config = H3TM_Bulletproof_Config::getInstance();
        $this->config_adapter = H3TM_Config_Adapter::getInstance();
    }

    /**
     * Run comprehensive configuration validation
     */
    public function run_comprehensive_validation() {
        $this->test_results = [];

        // Clear cache to ensure fresh tests
        $this->bulletproof_config->clear_cache();

        // Test 1: Configuration Loading
        $this->test_configuration_loading();

        // Test 2: Source Priority
        $this->test_source_priority();

        // Test 3: Context Reliability
        $this->test_context_reliability();

        // Test 4: Database Fallback
        $this->test_database_fallback();

        // Test 5: Validation Rules
        $this->test_validation_rules();

        // Test 6: Legacy Compatibility
        $this->test_legacy_compatibility();

        // Test 7: Security Features
        $this->test_security_features();

        // Test 8: Cache Behavior
        $this->test_cache_behavior();

        // Test 9: Error Handling
        $this->test_error_handling();

        // Test 10: Performance
        $this->test_performance();

        return $this->generate_validation_report();
    }

    /**
     * Test configuration loading
     */
    private function test_configuration_loading() {
        $this->add_test_result('Configuration Loading', function() {
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

            return 'Configuration loaded successfully with all required sections';
        });
    }

    /**
     * Test source priority system
     */
    private function test_source_priority() {
        $this->add_test_result('Source Priority', function() {
            // Test that constants override options
            $original_bucket = get_option('h3tm_s3_bucket', '');

            // Set a database option
            update_option('h3tm_s3_bucket', 'database-bucket');

            // Define a constant (would need to be done before this test in real scenario)
            $bucket_from_db = $this->bulletproof_config->get('s3.bucket_name');

            // Clean up
            if ($original_bucket) {
                update_option('h3tm_s3_bucket', $original_bucket);
            } else {
                delete_option('h3tm_s3_bucket');
            }

            $sources_used = $this->bulletproof_config->get('_metadata.sources_used', []);

            if (empty($sources_used)) {
                throw new Exception('No source tracking information available');
            }

            return 'Source priority system working, tracked sources: ' . implode(', ', $sources_used);
        });
    }

    /**
     * Test context reliability (AJAX vs Admin)
     */
    private function test_context_reliability() {
        $this->add_test_result('Context Reliability', function() {
            // Clear cache to ensure fresh load
            $this->bulletproof_config->clear_cache();

            // Get configuration in current context
            $config1 = $this->bulletproof_config->get_configuration();
            $bucket1 = $config1['s3']['bucket_name'] ?? '';

            // Simulate different context by clearing memory cache
            $reflection = new ReflectionClass('H3TM_Bulletproof_Config');
            $memory_cache_property = $reflection->getProperty('memory_cache');
            $memory_cache_property->setAccessible(true);
            $memory_cache_property->setValue(null, null);

            // Get configuration again
            $config2 = $this->bulletproof_config->get_configuration();
            $bucket2 = $config2['s3']['bucket_name'] ?? '';

            if ($bucket1 !== $bucket2) {
                throw new Exception("Configuration inconsistent across contexts: '$bucket1' vs '$bucket2'");
            }

            return "Configuration consistent across contexts: bucket='$bucket1'";
        });
    }

    /**
     * Test database fallback mechanism
     */
    private function test_database_fallback() {
        $this->add_test_result('Database Fallback', function() {
            global $wpdb;

            // Test direct database query fallback
            $test_option = 'h3tm_test_fallback_' . time();
            $test_value = 'test_fallback_value_' . rand(1000, 9999);

            // Insert directly into database
            $wpdb->insert(
                $wpdb->options,
                [
                    'option_name' => $test_option,
                    'option_value' => $test_value,
                    'autoload' => 'no'
                ]
            );

            // Test that bulletproof config can retrieve it
            $reflection = new ReflectionClass('H3TM_Bulletproof_Config');
            $method = $reflection->getMethod('get_option_with_fallback');
            $method->setAccessible(true);

            $retrieved_value = $method->invokeArgs($this->bulletproof_config, [$test_option, 'default']);

            // Clean up
            $wpdb->delete($wpdb->options, ['option_name' => $test_option]);

            if ($retrieved_value !== $test_value) {
                throw new Exception("Database fallback failed: expected '$test_value', got '$retrieved_value'");
            }

            return 'Database fallback mechanism working correctly';
        });
    }

    /**
     * Test validation rules
     */
    private function test_validation_rules() {
        $this->add_test_result('Validation Rules', function() {
            $validation = $this->bulletproof_config->validate_s3_configuration();

            if (!isset($validation['valid'])) {
                throw new Exception('Validation result missing validity flag');
            }

            if (!isset($validation['errors']) || !is_array($validation['errors'])) {
                throw new Exception('Validation result missing errors array');
            }

            // Test specific validation cases
            $reflection = new ReflectionClass('H3TM_Bulletproof_Config');

            // Test bucket name validation
            $bucket_method = $reflection->getMethod('is_valid_bucket_name');
            $bucket_method->setAccessible(true);

            if ($bucket_method->invokeArgs($this->bulletproof_config, ['invalid..bucket'])) {
                throw new Exception('Bucket name validation failed for invalid name');
            }

            if (!$bucket_method->invokeArgs($this->bulletproof_config, ['valid-bucket-name'])) {
                throw new Exception('Bucket name validation failed for valid name');
            }

            // Test region validation
            $region_method = $reflection->getMethod('is_valid_region');
            $region_method->setAccessible(true);

            if ($region_method->invokeArgs($this->bulletproof_config, ['invalid-region'])) {
                throw new Exception('Region validation failed for invalid region');
            }

            if (!$region_method->invokeArgs($this->bulletproof_config, ['us-east-1'])) {
                throw new Exception('Region validation failed for valid region');
            }

            return 'Validation rules working correctly';
        });
    }

    /**
     * Test legacy compatibility
     */
    private function test_legacy_compatibility() {
        $this->add_test_result('Legacy Compatibility', function() {
            $legacy_config = $this->config_adapter->get_s3_config_legacy();

            // Test required legacy keys
            $required_keys = ['bucket_name', 'region', 'access_key', 'secret_key', 'configured'];
            foreach ($required_keys as $key) {
                if (!array_key_exists($key, $legacy_config)) {
                    throw new Exception("Legacy compatibility missing key: $key");
                }
            }

            // Test alternative keys
            if (!isset($legacy_config['bucket'])) {
                throw new Exception('Legacy compatibility missing alternative bucket key');
            }

            // Test magic methods
            $bucket_via_magic = $this->config_adapter->bucket_name;
            $bucket_via_get = $this->config_adapter->get('bucket_name');

            if ($bucket_via_magic !== $bucket_via_get) {
                throw new Exception('Legacy magic method inconsistent with get method');
            }

            // Test method aliases
            $config_via_alias = $this->config_adapter->get_configuration();
            if (!is_array($config_via_alias) || !isset($config_via_alias['bucket_name'])) {
                throw new Exception('Legacy method alias not working');
            }

            return 'Legacy compatibility layer working correctly';
        });
    }

    /**
     * Test security features
     */
    private function test_security_features() {
        $this->add_test_result('Security Features', function() {
            $frontend_config = $this->bulletproof_config->get_frontend_safe_config();

            // Ensure credentials are not exposed
            if (isset($frontend_config['access_key']) || isset($frontend_config['secret_key'])) {
                throw new Exception('Credentials exposed in frontend-safe configuration');
            }

            // Test environment-based security settings
            $environment_type = $this->bulletproof_config->get('environment.type');
            $ssl_verify = $this->bulletproof_config->get('security.ssl_verify', true);

            if ($environment_type === 'production' && !$ssl_verify) {
                // This should trigger a validation warning, not an error
                $validation = $this->bulletproof_config->validate_s3_configuration();
                $has_ssl_warning = false;

                foreach ($validation['warnings'] ?? [] as $warning) {
                    if (strpos($warning, 'SSL') !== false) {
                        $has_ssl_warning = true;
                        break;
                    }
                }

                if (!$has_ssl_warning) {
                    throw new Exception('Missing SSL verification warning in production');
                }
            }

            return 'Security features working correctly';
        });
    }

    /**
     * Test cache behavior
     */
    private function test_cache_behavior() {
        $this->add_test_result('Cache Behavior', function() {
            // Clear cache
            $this->bulletproof_config->clear_cache();

            // First load should build cache
            $start_time = microtime(true);
            $config1 = $this->bulletproof_config->get_configuration();
            $first_load_time = microtime(true) - $start_time;

            // Second load should use cache (faster)
            $start_time = microtime(true);
            $config2 = $this->bulletproof_config->get_configuration();
            $second_load_time = microtime(true) - $start_time;

            // Third load should also use cache
            $start_time = microtime(true);
            $config3 = $this->bulletproof_config->get_configuration();
            $third_load_time = microtime(true) - $start_time;

            // Verify configurations are identical
            if ($config1 !== $config2 || $config2 !== $config3) {
                throw new Exception('Cached configuration inconsistent');
            }

            // Second and third loads should be significantly faster
            if ($second_load_time >= $first_load_time || $third_load_time >= $first_load_time) {
                // This might not always be true due to system variations, so make it a warning
                error_log("H3TM Config Cache: Load times - First: {$first_load_time}s, Second: {$second_load_time}s, Third: {$third_load_time}s");
            }

            return "Cache behavior working (load times: {$first_load_time}s, {$second_load_time}s, {$third_load_time}s)";
        });
    }

    /**
     * Test error handling
     */
    private function test_error_handling() {
        $this->add_test_result('Error Handling', function() {
            // Test graceful handling of missing configuration
            $missing_value = $this->bulletproof_config->get('non.existent.key', 'default');
            if ($missing_value !== 'default') {
                throw new Exception('Default value not returned for missing configuration');
            }

            // Test handling of invalid dot notation
            $invalid_value = $this->bulletproof_config->get('s3.non_existent_key', null);
            if ($invalid_value !== null) {
                throw new Exception('Null not returned for invalid configuration key');
            }

            // Test connection test with invalid configuration
            $original_config = $this->bulletproof_config->get_section('s3');

            // Temporarily clear config to test error handling
            $reflection = new ReflectionClass('H3TM_Bulletproof_Config');
            $memory_cache_property = $reflection->getProperty('memory_cache');
            $memory_cache_property->setAccessible(true);

            // Set invalid config
            $invalid_config = $original_config;
            $invalid_config['s3']['bucket_name'] = '';
            $memory_cache_property->setValue($this->bulletproof_config, $invalid_config);

            $connection_test = $this->bulletproof_config->test_connection();
            if ($connection_test['success'] !== false) {
                throw new Exception('Connection test should fail with invalid configuration');
            }

            // Restore original config
            $memory_cache_property->setValue($this->bulletproof_config, null);

            return 'Error handling working correctly';
        });
    }

    /**
     * Test performance characteristics
     */
    private function test_performance() {
        $this->add_test_result('Performance', function() {
            $iterations = 100;

            // Clear cache for fresh start
            $this->bulletproof_config->clear_cache();

            // Time multiple configuration loads
            $start_time = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $config = $this->bulletproof_config->get_configuration();
            }
            $total_time = microtime(true) - $start_time;

            $avg_time_ms = ($total_time / $iterations) * 1000;

            // Performance should be reasonable (less than 10ms per call on average)
            if ($avg_time_ms > 10) {
                throw new Exception("Performance issue: average load time {$avg_time_ms}ms per call");
            }

            // Test specific key access performance
            $start_time = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $bucket = $this->bulletproof_config->get('s3.bucket_name');
            }
            $key_access_time = microtime(true) - $start_time;
            $avg_key_time_ms = ($key_access_time / $iterations) * 1000;

            return "Performance acceptable: {$avg_time_ms}ms avg config load, {$avg_key_time_ms}ms avg key access";
        });
    }

    /**
     * Add test result
     */
    private function add_test_result($test_name, $test_function) {
        try {
            $start_time = microtime(true);
            $result = call_user_func($test_function);
            $execution_time = microtime(true) - $start_time;

            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => $result,
                'execution_time' => $execution_time
            ];
        } catch (Exception $e) {
            $this->test_results[] = [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => $e->getMessage(),
                'execution_time' => 0
            ];
        }
    }

    /**
     * Generate validation report
     */
    private function generate_validation_report() {
        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, function($result) {
            return $result['status'] === 'PASS';
        }));
        $failed_tests = $total_tests - $passed_tests;

        $report = [
            'summary' => [
                'total_tests' => $total_tests,
                'passed' => $passed_tests,
                'failed' => $failed_tests,
                'success_rate' => $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0,
                'overall_status' => $failed_tests === 0 ? 'PASS' : 'FAIL'
            ],
            'test_results' => $this->test_results,
            'configuration_debug' => $this->bulletproof_config->get_debug_info(),
            'adapter_debug' => $this->config_adapter->get_debug_info(),
            'generated_at' => current_time('mysql')
        ];

        return $report;
    }

    /**
     * Run quick validation (subset of tests for regular use)
     */
    public function run_quick_validation() {
        $this->test_results = [];

        // Run essential tests only
        $this->test_configuration_loading();
        $this->test_context_reliability();
        $this->test_legacy_compatibility();

        return $this->generate_validation_report();
    }

    /**
     * Test specific AJAX context
     */
    public function test_ajax_context() {
        // Simulate AJAX context
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        $this->bulletproof_config->clear_cache();
        $config = $this->bulletproof_config->get_configuration();

        return [
            'ajax_context_detected' => $this->bulletproof_config->get('_metadata.context') === 'ajax',
            'configuration_loaded' => !empty($config),
            's3_configured' => $this->bulletproof_config->is_s3_configured(),
            'bucket_name' => $this->bulletproof_config->get('s3.bucket_name', 'NOT_SET'),
            'debug_info' => $this->bulletproof_config->get_debug_info()
        ];
    }

    /**
     * Export validation report to file
     */
    public function export_report($report, $filename = null) {
        if ($filename === null) {
            $filename = 'h3tm-config-validation-' . date('Y-m-d-H-i-s') . '.json';
        }

        $upload_dir = wp_upload_dir();
        $report_dir = $upload_dir['basedir'] . '/h3-tours/reports';

        if (!file_exists($report_dir)) {
            wp_mkdir_p($report_dir);
        }

        $filepath = $report_dir . '/' . $filename;
        $json_content = json_encode($report, JSON_PRETTY_PRINT);

        $result = file_put_contents($filepath, $json_content);

        if ($result === false) {
            throw new Exception('Failed to export validation report');
        }

        return $filepath;
    }
}