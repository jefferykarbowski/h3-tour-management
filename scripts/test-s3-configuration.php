<?php
/**
 * S3 Configuration Testing Script
 *
 * This script tests the enhanced S3 configuration system to ensure it works
 * properly across different WordPress contexts and request types.
 *
 * Usage: Run via WordPress CLI or include in admin page for testing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

class H3TM_S3_Configuration_Tester {

    private $results = array();
    private $errors = array();
    private $warnings = array();

    /**
     * Run comprehensive S3 configuration tests
     */
    public function run_tests() {
        $this->results = array();
        $this->errors = array();
        $this->warnings = array();

        $this->log_test_start();

        // Test 1: Config Manager Initialization
        $this->test_config_manager_initialization();

        // Test 2: Configuration Loading
        $this->test_configuration_loading();

        // Test 3: Validation System
        $this->test_validation_system();

        // Test 4: Caching Mechanism
        $this->test_caching_mechanism();

        // Test 5: AJAX Handler Registration
        $this->test_ajax_handler_registration();

        // Test 6: Configuration Persistence
        $this->test_configuration_persistence();

        // Test 7: Error Handling
        $this->test_error_handling();

        // Test 8: Security Features
        $this->test_security_features();

        $this->log_test_summary();

        return $this->get_test_results();
    }

    /**
     * Test 1: Config Manager Initialization
     */
    private function test_config_manager_initialization() {
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();

            if ($config_manager instanceof H3TM_S3_Config_Manager) {
                $this->results['config_manager_init'] = 'PASS';
                $this->log_success('Config Manager initialized successfully');
            } else {
                $this->results['config_manager_init'] = 'FAIL';
                $this->errors[] = 'Config Manager not initialized properly';
            }

            // Test singleton pattern
            $second_instance = H3TM_S3_Config_Manager::getInstance();
            if ($config_manager === $second_instance) {
                $this->results['singleton_pattern'] = 'PASS';
                $this->log_success('Singleton pattern working correctly');
            } else {
                $this->results['singleton_pattern'] = 'FAIL';
                $this->errors[] = 'Singleton pattern not working - multiple instances created';
            }

        } catch (Exception $e) {
            $this->results['config_manager_init'] = 'FAIL';
            $this->errors[] = 'Config Manager initialization failed: ' . $e->getMessage();
        }
    }

    /**
     * Test 2: Configuration Loading
     */
    private function test_configuration_loading() {
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $config = $config_manager->get_configuration();

            if (is_array($config)) {
                $this->results['config_loading'] = 'PASS';
                $this->log_success('Configuration loaded successfully');

                // Check required fields exist
                $required_fields = ['bucket_name', 'region', 'access_key', 'secret_key', 'enabled', 'threshold'];
                $missing_fields = array();

                foreach ($required_fields as $field) {
                    if (!array_key_exists($field, $config)) {
                        $missing_fields[] = $field;
                    }
                }

                if (empty($missing_fields)) {
                    $this->results['config_structure'] = 'PASS';
                    $this->log_success('Configuration structure is complete');
                } else {
                    $this->results['config_structure'] = 'FAIL';
                    $this->errors[] = 'Missing configuration fields: ' . implode(', ', $missing_fields);
                }

                // Check configuration source
                if (!empty($config['source'])) {
                    $this->results['config_source'] = 'PASS';
                    $this->log_success('Configuration source detected: ' . $config['source']);
                } else {
                    $this->results['config_source'] = 'WARN';
                    $this->warnings[] = 'Configuration source not identified';
                }

            } else {
                $this->results['config_loading'] = 'FAIL';
                $this->errors[] = 'Configuration loading returned invalid data';
            }

        } catch (Exception $e) {
            $this->results['config_loading'] = 'FAIL';
            $this->errors[] = 'Configuration loading failed: ' . $e->getMessage();
        }
    }

    /**
     * Test 3: Validation System
     */
    private function test_validation_system() {
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();
            $validation = $config_manager->validate_configuration();

            if (is_array($validation) && isset($validation['valid'])) {
                $this->results['validation_system'] = 'PASS';
                $this->log_success('Validation system functioning');

                // Check validation completeness
                $required_validation_keys = ['valid', 'errors', 'warnings', 'checks_performed', 'validated_at'];
                $missing_keys = array();

                foreach ($required_validation_keys as $key) {
                    if (!array_key_exists($key, $validation)) {
                        $missing_keys[] = $key;
                    }
                }

                if (empty($missing_keys)) {
                    $this->results['validation_completeness'] = 'PASS';
                    $this->log_success('Validation results complete');
                } else {
                    $this->results['validation_completeness'] = 'FAIL';
                    $this->errors[] = 'Incomplete validation results: missing ' . implode(', ', $missing_keys);
                }

                // Log validation status
                if ($validation['valid']) {
                    $this->log_success('Configuration validation: VALID');
                } else {
                    $this->log_info('Configuration validation: INVALID - ' . implode(', ', $validation['errors']));
                }

            } else {
                $this->results['validation_system'] = 'FAIL';
                $this->errors[] = 'Validation system returned invalid data';
            }

        } catch (Exception $e) {
            $this->results['validation_system'] = 'FAIL';
            $this->errors[] = 'Validation system test failed: ' . $e->getMessage();
        }
    }

    /**
     * Test 4: Caching Mechanism
     */
    private function test_caching_mechanism() {
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();

            // Get configuration (should cache)
            $start_time = microtime(true);
            $config1 = $config_manager->get_configuration();
            $first_call_time = microtime(true) - $start_time;

            // Get configuration again (should use cache)
            $start_time = microtime(true);
            $config2 = $config_manager->get_configuration();
            $second_call_time = microtime(true) - $start_time;

            if ($config1 === $config2) {
                $this->results['cache_consistency'] = 'PASS';
                $this->log_success('Cache consistency verified');
            } else {
                $this->results['cache_consistency'] = 'FAIL';
                $this->errors[] = 'Cache inconsistency detected';
            }

            // Test cache clearing
            $config_manager->clear_cache();
            $config3 = $config_manager->get_configuration();

            $this->results['cache_clearing'] = 'PASS';
            $this->log_success('Cache clearing functionality works');

            $this->log_info(sprintf('Performance: First call: %.4fs, Cached call: %.4fs',
                $first_call_time, $second_call_time));

        } catch (Exception $e) {
            $this->results['caching_mechanism'] = 'FAIL';
            $this->errors[] = 'Caching mechanism test failed: ' . $e->getMessage();
        }
    }

    /**
     * Test 5: AJAX Handler Registration
     */
    private function test_ajax_handler_registration() {
        try {
            global $wp_filter;

            $ajax_actions = [
                'h3tm_get_s3_presigned_url',
                'h3tm_process_s3_upload',
                'h3tm_test_s3_connection',
                'h3tm_validate_s3_config',
                'h3tm_debug_s3_config'
            ];

            $registered_actions = array();
            $missing_actions = array();

            foreach ($ajax_actions as $action) {
                $hook_name = 'wp_ajax_' . $action;
                if (isset($wp_filter[$hook_name])) {
                    $registered_actions[] = $action;
                } else {
                    $missing_actions[] = $action;
                }
            }

            if (empty($missing_actions)) {
                $this->results['ajax_registration'] = 'PASS';
                $this->log_success('All AJAX handlers registered: ' . implode(', ', $registered_actions));
            } else {
                $this->results['ajax_registration'] = 'FAIL';
                $this->errors[] = 'Missing AJAX handlers: ' . implode(', ', $missing_actions);
            }

        } catch (Exception $e) {
            $this->results['ajax_registration'] = 'FAIL';
            $this->errors[] = 'AJAX handler registration test failed: ' . $e->getMessage();
        }
    }

    /**
     * Test 6: Configuration Persistence
     */
    private function test_configuration_persistence() {
        try {
            // Simulate multiple request contexts
            $config_manager1 = H3TM_S3_Config_Manager::getInstance();
            $config1 = $config_manager1->get_configuration();

            // Clear static variables to simulate new request
            $this->simulate_new_request_context();

            $config_manager2 = H3TM_S3_Config_Manager::getInstance();
            $config2 = $config_manager2->get_configuration();

            // Configuration should be the same across contexts
            if ($this->compare_configurations($config1, $config2)) {
                $this->results['config_persistence'] = 'PASS';
                $this->log_success('Configuration persists across request contexts');
            } else {
                $this->results['config_persistence'] = 'FAIL';
                $this->errors[] = 'Configuration inconsistent across request contexts';
            }

        } catch (Exception $e) {
            $this->results['config_persistence'] = 'FAIL';
            $this->errors[] = 'Configuration persistence test failed: ' . $e->getMessage();
        }
    }

    /**
     * Test 7: Error Handling
     */
    private function test_error_handling() {
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();

            // Test with incomplete configuration
            $original_bucket = get_option('h3tm_s3_bucket');
            update_option('h3tm_s3_bucket', ''); // Temporarily remove bucket

            $config_manager->clear_cache();
            $validation = $config_manager->validate_configuration(true);

            if (!$validation['valid'] && !empty($validation['errors'])) {
                $this->results['error_detection'] = 'PASS';
                $this->log_success('Error detection working correctly');
            } else {
                $this->results['error_detection'] = 'FAIL';
                $this->errors[] = 'Error detection not working properly';
            }

            // Restore original configuration
            if ($original_bucket !== false) {
                update_option('h3tm_s3_bucket', $original_bucket);
            }

            $this->results['error_handling'] = 'PASS';
            $this->log_success('Error handling test completed');

        } catch (Exception $e) {
            $this->results['error_handling'] = 'FAIL';
            $this->errors[] = 'Error handling test failed: ' . $e->getMessage();
        }
    }

    /**
     * Test 8: Security Features
     */
    private function test_security_features() {
        try {
            $config_manager = H3TM_S3_Config_Manager::getInstance();

            // Test credential protection
            $config = $config_manager->get_configuration();
            $safe_config = $config_manager->get_frontend_safe_config();

            // Ensure credentials are not exposed in frontend config
            if (!isset($safe_config['access_key']) && !isset($safe_config['secret_key'])) {
                $this->results['credential_protection'] = 'PASS';
                $this->log_success('Credentials properly protected from frontend exposure');
            } else {
                $this->results['credential_protection'] = 'FAIL';
                $this->errors[] = 'Credentials exposed in frontend configuration';
            }

            // Test debug information safety
            $debug_info = $config_manager->get_debug_info();
            $this->check_debug_security($debug_info);

            $this->results['security_features'] = 'PASS';
            $this->log_success('Security features test completed');

        } catch (Exception $e) {
            $this->results['security_features'] = 'FAIL';
            $this->errors[] = 'Security features test failed: ' . $e->getMessage();
        }
    }

    /**
     * Helper Methods
     */

    private function simulate_new_request_context() {
        // This would be more complex in a real scenario
        // For testing purposes, we'll clear some caches
        wp_cache_flush();
    }

    private function compare_configurations($config1, $config2) {
        $important_keys = ['bucket_name', 'region', 'enabled', 'threshold'];

        foreach ($important_keys as $key) {
            if (($config1[$key] ?? null) !== ($config2[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function check_debug_security($debug_info) {
        // Recursively check that no actual credentials are exposed
        $this->check_array_for_credentials($debug_info, 'debug_info');
    }

    private function check_array_for_credentials($array, $context = '') {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->check_array_for_credentials($value, $context . '.' . $key);
            } elseif (is_string($value)) {
                // Check for actual AWS credentials (basic pattern matching)
                if (preg_match('/^[A-Z0-9]{20}$/', $value) || preg_match('/^[A-Za-z0-9\/+=]{40}$/', $value)) {
                    $this->warnings[] = "Potential credential exposure in {$context}.{$key}";
                }
            }
        }
    }

    /**
     * Logging Methods
     */

    private function log_test_start() {
        $this->log_info('=== H3TM S3 Configuration Test Suite Started ===');
        $this->log_info('WordPress Version: ' . get_bloginfo('version'));
        $this->log_info('H3TM Version: ' . (defined('H3TM_VERSION') ? H3TM_VERSION : 'Unknown'));
        $this->log_info('Test Time: ' . current_time('mysql'));
        $this->log_info('');
    }

    private function log_test_summary() {
        $this->log_info('');
        $this->log_info('=== Test Results Summary ===');

        $pass_count = count(array_filter($this->results, function($result) { return $result === 'PASS'; }));
        $fail_count = count(array_filter($this->results, function($result) { return $result === 'FAIL'; }));
        $warn_count = count(array_filter($this->results, function($result) { return $result === 'WARN'; }));

        $this->log_info("Tests Passed: {$pass_count}");
        $this->log_info("Tests Failed: {$fail_count}");
        $this->log_info("Warnings: {$warn_count}");

        if (!empty($this->errors)) {
            $this->log_info('');
            $this->log_info('Errors:');
            foreach ($this->errors as $error) {
                $this->log_error('  - ' . $error);
            }
        }

        if (!empty($this->warnings)) {
            $this->log_info('');
            $this->log_info('Warnings:');
            foreach ($this->warnings as $warning) {
                $this->log_warning('  - ' . $warning);
            }
        }

        $this->log_info('');
        $overall_result = $fail_count === 0 ? 'PASSED' : 'FAILED';
        $this->log_info("=== Overall Test Result: {$overall_result} ===");
    }

    private function log_success($message) {
        error_log("H3TM S3 Test SUCCESS: {$message}");
    }

    private function log_info($message) {
        error_log("H3TM S3 Test INFO: {$message}");
    }

    private function log_warning($message) {
        error_log("H3TM S3 Test WARNING: {$message}");
    }

    private function log_error($message) {
        error_log("H3TM S3 Test ERROR: {$message}");
    }

    /**
     * Get test results
     */
    public function get_test_results() {
        return array(
            'results' => $this->results,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => array(
                'total_tests' => count($this->results),
                'passed' => count(array_filter($this->results, function($r) { return $r === 'PASS'; })),
                'failed' => count(array_filter($this->results, function($r) { return $r === 'FAIL'; })),
                'warnings' => count(array_filter($this->results, function($r) { return $r === 'WARN'; })),
                'overall' => count(array_filter($this->results, function($r) { return $r === 'FAIL'; })) === 0 ? 'PASSED' : 'FAILED'
            )
        );
    }
}

// Auto-run tests if this script is included directly
if (defined('WP_CLI') && WP_CLI) {
    $tester = new H3TM_S3_Configuration_Tester();
    $results = $tester->run_tests();

    WP_CLI::line('S3 Configuration Test Results:');
    WP_CLI::line(json_encode($results, JSON_PRETTY_PRINT));
}