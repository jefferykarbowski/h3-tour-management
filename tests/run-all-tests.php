<?php
/**
 * Master Test Runner for H3 Tour Management S3 Integration Tests
 *
 * Executes all S3 integration tests and generates consolidated reports
 * to identify configuration issues and verify system functionality.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/h3-tour-management.php';
}

class H3TM_S3_Master_Test_Runner {

    private $test_results = [];
    private $test_files = [];
    private $execution_log = [];
    private $start_time;

    public function __construct() {
        $this->start_time = microtime(true);
        error_log('H3TM S3 Master Test Runner: Initializing comprehensive test suite');
        $this->setup_test_files();
    }

    /**
     * Run all S3 integration tests
     */
    public function run_all_tests($include_slow_tests = true) {
        error_log('H3TM S3 Master Test Runner: Starting complete test suite execution');

        $this->log_execution('Test suite started', 'info');

        // Phase 1: Quick Diagnostic
        $this->log_execution('Phase 1: Quick Diagnostic', 'info');
        $this->run_quick_diagnostic();

        // Phase 2: Configuration Tests
        $this->log_execution('Phase 2: Configuration Validation Tests', 'info');
        $this->run_configuration_tests();

        // Phase 3: AJAX Handler Tests
        $this->log_execution('Phase 3: AJAX Handler Tests', 'info');
        $this->run_ajax_handler_tests();

        // Phase 4: Presigned URL Tests
        $this->log_execution('Phase 4: Presigned URL Tests', 'info');
        $this->run_presigned_url_tests();

        // Phase 5: Error Handling Tests
        $this->log_execution('Phase 5: Error Handling Tests', 'info');
        $this->run_error_handling_tests();

        // Phase 6: Integration Pipeline Tests (slow)
        if ($include_slow_tests) {
            $this->log_execution('Phase 6: Integration Pipeline Tests', 'info');
            $this->run_integration_pipeline_tests();
        } else {
            $this->log_execution('Phase 6: Skipped (slow tests disabled)', 'info');
        }

        // Generate consolidated report
        $this->log_execution('Generating consolidated report', 'info');
        $consolidated_report = $this->generate_consolidated_report();

        $execution_time = round(microtime(true) - $this->start_time, 2);
        $this->log_execution("Test suite completed in {$execution_time} seconds", 'info');

        return $consolidated_report;
    }

    /**
     * Setup test file registry
     */
    private function setup_test_files() {
        $this->test_files = [
            'quick_diagnostic' => [
                'file' => 's3-configuration-debugger.php',
                'class' => 'H3TM_S3_Configuration_Debugger',
                'method' => 'quick_diagnostic',
                'description' => 'Quick system diagnostic',
                'estimated_time' => 5
            ],
            'configuration' => [
                'file' => 'test-s3-configuration.php',
                'class' => 'H3TM_S3_Configuration_Tests',
                'method' => 'run_all_tests',
                'description' => 'Configuration validation across contexts',
                'estimated_time' => 15
            ],
            'ajax_handlers' => [
                'file' => 'test-s3-ajax-handlers.php',
                'class' => 'H3TM_S3_Ajax_Handler_Tests',
                'method' => 'run_all_tests',
                'description' => 'AJAX handler registration and execution',
                'estimated_time' => 20
            ],
            'presigned_urls' => [
                'file' => 'test-s3-presigned-urls.php',
                'class' => 'H3TM_S3_Presigned_URL_Tests',
                'method' => 'run_all_tests',
                'description' => 'Presigned URL generation and validation',
                'estimated_time' => 25
            ],
            'error_handling' => [
                'file' => 'test-s3-error-handling.php',
                'class' => 'H3TM_S3_Error_Handling_Tests',
                'method' => 'run_all_tests',
                'description' => 'Error handling and fallback mechanisms',
                'estimated_time' => 30
            ],
            'integration_pipeline' => [
                'file' => 'test-s3-integration-pipeline.php',
                'class' => 'H3TM_S3_Integration_Pipeline_Tests',
                'method' => 'run_all_tests',
                'description' => 'End-to-end integration pipeline testing',
                'estimated_time' => 60
            ]
        ];
    }

    /**
     * Run quick diagnostic
     */
    private function run_quick_diagnostic() {
        $test_info = $this->test_files['quick_diagnostic'];
        $this->log_execution("Running: {$test_info['description']}", 'info');

        try {
            $this->require_test_file($test_info['file']);

            if (class_exists($test_info['class'])) {
                $test_instance = new $test_info['class']();
                $result = call_user_func([$test_instance, $test_info['method']]);

                $this->test_results['quick_diagnostic'] = [
                    'status' => 'completed',
                    'result' => $result,
                    'execution_time' => $this->get_phase_execution_time(),
                    'critical_issues' => $this->extract_critical_issues($result),
                    'primary_issue' => $result['primary_issue'] ?? 'Unknown'
                ];

                $this->log_execution("Quick diagnostic completed: {$result['primary_issue']}", 'info');
            } else {
                throw new Exception("Class {$test_info['class']} not found");
            }
        } catch (Exception $e) {
            $this->test_results['quick_diagnostic'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'execution_time' => $this->get_phase_execution_time()
            ];
            $this->log_execution("Quick diagnostic failed: {$e->getMessage()}", 'error');
        }
    }

    /**
     * Run configuration tests
     */
    private function run_configuration_tests() {
        $test_info = $this->test_files['configuration'];
        $this->log_execution("Running: {$test_info['description']}", 'info');

        try {
            $this->require_test_file($test_info['file']);

            if (class_exists($test_info['class'])) {
                $test_instance = new $test_info['class']();
                $result = call_user_func([$test_instance, $test_info['method']]);

                $this->test_results['configuration'] = [
                    'status' => 'completed',
                    'result' => $result,
                    'execution_time' => $this->get_phase_execution_time(),
                    'critical_issues' => $this->extract_critical_issues($result),
                    'test_count' => $result['test_count'] ?? 0
                ];

                $this->log_execution("Configuration tests completed: {$result['test_count']} tests", 'info');
            } else {
                throw new Exception("Class {$test_info['class']} not found");
            }
        } catch (Exception $e) {
            $this->test_results['configuration'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'execution_time' => $this->get_phase_execution_time()
            ];
            $this->log_execution("Configuration tests failed: {$e->getMessage()}", 'error');
        }
    }

    /**
     * Run AJAX handler tests
     */
    private function run_ajax_handler_tests() {
        $test_info = $this->test_files['ajax_handlers'];
        $this->log_execution("Running: {$test_info['description']}", 'info');

        try {
            $this->require_test_file($test_info['file']);

            if (class_exists($test_info['class'])) {
                $test_instance = new $test_info['class']();
                $result = call_user_func([$test_instance, $test_info['method']]);

                $this->test_results['ajax_handlers'] = [
                    'status' => 'completed',
                    'result' => $result,
                    'execution_time' => $this->get_phase_execution_time(),
                    'critical_issues' => $this->extract_critical_issues($result),
                    'test_count' => $result['test_count'] ?? 0
                ];

                $this->log_execution("AJAX handler tests completed: {$result['test_count']} tests", 'info');
            } else {
                throw new Exception("Class {$test_info['class']} not found");
            }
        } catch (Exception $e) {
            $this->test_results['ajax_handlers'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'execution_time' => $this->get_phase_execution_time()
            ];
            $this->log_execution("AJAX handler tests failed: {$e->getMessage()}", 'error');
        }
    }

    /**
     * Run presigned URL tests
     */
    private function run_presigned_url_tests() {
        $test_info = $this->test_files['presigned_urls'];
        $this->log_execution("Running: {$test_info['description']}", 'info');

        try {
            $this->require_test_file($test_info['file']);

            if (class_exists($test_info['class'])) {
                $test_instance = new $test_info['class']();
                $result = call_user_func([$test_instance, $test_info['method']]);

                $this->test_results['presigned_urls'] = [
                    'status' => 'completed',
                    'result' => $result,
                    'execution_time' => $this->get_phase_execution_time(),
                    'critical_issues' => $this->extract_critical_issues($result),
                    'test_count' => $result['test_count'] ?? 0
                ];

                $this->log_execution("Presigned URL tests completed: {$result['test_count']} tests", 'info');
            } else {
                throw new Exception("Class {$test_info['class']} not found");
            }
        } catch (Exception $e) {
            $this->test_results['presigned_urls'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'execution_time' => $this->get_phase_execution_time()
            ];
            $this->log_execution("Presigned URL tests failed: {$e->getMessage()}", 'error');
        }
    }

    /**
     * Run error handling tests
     */
    private function run_error_handling_tests() {
        $test_info = $this->test_files['error_handling'];
        $this->log_execution("Running: {$test_info['description']}", 'info');

        try {
            $this->require_test_file($test_info['file']);

            if (class_exists($test_info['class'])) {
                $test_instance = new $test_info['class']();
                $result = call_user_func([$test_instance, $test_info['method']]);

                $this->test_results['error_handling'] = [
                    'status' => 'completed',
                    'result' => $result,
                    'execution_time' => $this->get_phase_execution_time(),
                    'critical_issues' => $this->extract_critical_issues($result),
                    'test_count' => $result['test_count'] ?? 0
                ];

                $this->log_execution("Error handling tests completed: {$result['test_count']} tests", 'info');
            } else {
                throw new Exception("Class {$test_info['class']} not found");
            }
        } catch (Exception $e) {
            $this->test_results['error_handling'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'execution_time' => $this->get_phase_execution_time()
            ];
            $this->log_execution("Error handling tests failed: {$e->getMessage()}", 'error');
        }
    }

    /**
     * Run integration pipeline tests
     */
    private function run_integration_pipeline_tests() {
        $test_info = $this->test_files['integration_pipeline'];
        $this->log_execution("Running: {$test_info['description']}", 'info');

        try {
            $this->require_test_file($test_info['file']);

            if (class_exists($test_info['class'])) {
                $test_instance = new $test_info['class']();
                $result = call_user_func([$test_instance, $test_info['method']]);

                $this->test_results['integration_pipeline'] = [
                    'status' => 'completed',
                    'result' => $result,
                    'execution_time' => $this->get_phase_execution_time(),
                    'critical_issues' => $this->extract_critical_issues($result),
                    'test_count' => $result['test_count'] ?? 0
                ];

                $this->log_execution("Integration pipeline tests completed: {$result['test_count']} tests", 'info');
            } else {
                throw new Exception("Class {$test_info['class']} not found");
            }
        } catch (Exception $e) {
            $this->test_results['integration_pipeline'] = [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'execution_time' => $this->get_phase_execution_time()
            ];
            $this->log_execution("Integration pipeline tests failed: {$e->getMessage()}", 'error');
        }
    }

    /**
     * Require test file with error handling
     */
    private function require_test_file($filename) {
        $file_path = dirname(__FILE__) . '/' . $filename;

        if (!file_exists($file_path)) {
            throw new Exception("Test file not found: $filename");
        }

        require_once $file_path;
    }

    /**
     * Extract critical issues from test results
     */
    private function extract_critical_issues($result) {
        $critical_issues = [];

        // Extract from overall assessment
        if (isset($result['overall_assessment']) &&
            (strpos($result['overall_assessment'], 'CRITICAL') === 0 ||
             strpos($result['overall_assessment'], 'CRITICAL ISSUES') !== false)) {
            $critical_issues[] = $result['overall_assessment'];
        }

        // Extract from action items
        if (isset($result['action_items']) && is_array($result['action_items'])) {
            foreach ($result['action_items'] as $action) {
                if (strpos($action, 'CRITICAL') === 0 || strpos($action, 'Fix') === 0) {
                    $critical_issues[] = $action;
                }
            }
        }

        // Extract from debug info recommendations
        if (isset($result['debug_info'])) {
            foreach ($result['debug_info'] as $test_name => $info) {
                if (isset($info['recommendation']) &&
                    (strpos($info['recommendation'], 'CRITICAL') === 0 ||
                     strpos($info['recommendation'], 'Fix') === 0)) {
                    $critical_issues[] = "$test_name: {$info['recommendation']}";
                }
            }
        }

        return array_unique($critical_issues);
    }

    /**
     * Generate consolidated test report
     */
    public function generate_consolidated_report() {
        $total_execution_time = round(microtime(true) - $this->start_time, 2);

        // Collect all critical issues
        $all_critical_issues = [];
        $total_tests = 0;
        $successful_test_suites = 0;
        $failed_test_suites = 0;

        foreach ($this->test_results as $test_name => $test_result) {
            if ($test_result['status'] === 'completed') {
                $successful_test_suites++;
                if (isset($test_result['test_count'])) {
                    $total_tests += $test_result['test_count'];
                }
            } else {
                $failed_test_suites++;
            }

            if (isset($test_result['critical_issues'])) {
                $all_critical_issues = array_merge($all_critical_issues, $test_result['critical_issues']);
            }
        }

        // Determine overall system status
        $system_status = $this->determine_overall_system_status($all_critical_issues);

        // Generate primary recommendations
        $primary_recommendations = $this->generate_primary_recommendations($all_critical_issues);

        // Create consolidated report
        $report = [
            'meta' => [
                'timestamp' => current_time('mysql'),
                'test_suite_version' => '1.0',
                'total_execution_time_seconds' => $total_execution_time,
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => defined('H3TM_VERSION') ? H3TM_VERSION : 'unknown'
            ],
            'executive_summary' => [
                'system_status' => $system_status,
                'total_test_suites' => count($this->test_results),
                'successful_test_suites' => $successful_test_suites,
                'failed_test_suites' => $failed_test_suites,
                'total_individual_tests' => $total_tests,
                'critical_issues_count' => count($all_critical_issues),
                'primary_issue' => $this->identify_primary_issue($all_critical_issues),
                'system_ready_for_s3' => $this->is_system_ready_for_s3($all_critical_issues)
            ],
            'detailed_results' => $this->test_results,
            'critical_issues' => $all_critical_issues,
            'primary_recommendations' => $primary_recommendations,
            'execution_log' => $this->execution_log,
            'next_steps' => $this->generate_next_steps($system_status, $all_critical_issues),
            'support_information' => $this->generate_support_information()
        ];

        // Log final status
        error_log("H3TM S3 Master Test Runner: System Status - $system_status");
        error_log("H3TM S3 Master Test Runner: Critical Issues - " . count($all_critical_issues));

        return $report;
    }

    /**
     * Determine overall system status
     */
    private function determine_overall_system_status($critical_issues) {
        if (empty($critical_issues)) {
            return 'healthy';
        }

        // Check for specific critical patterns
        foreach ($critical_issues as $issue) {
            if (strpos($issue, 'Configuration inconsistent') !== false ||
                strpos($issue, 'AJAX context') !== false) {
                return 'ajax_configuration_issue';
            }
            if (strpos($issue, 'S3 not configured') !== false) {
                return 's3_not_configured';
            }
            if (strpos($issue, 'Class') !== false && strpos($issue, 'not found') !== false) {
                return 'plugin_issue';
            }
        }

        if (count($critical_issues) > 5) {
            return 'multiple_critical_issues';
        } elseif (count($critical_issues) > 2) {
            return 'several_issues';
        } else {
            return 'minor_issues';
        }
    }

    /**
     * Generate primary recommendations based on issues
     */
    private function generate_primary_recommendations($critical_issues) {
        $recommendations = [];

        if (empty($critical_issues)) {
            $recommendations[] = [
                'priority' => 'low',
                'action' => 'System appears healthy - run periodic tests to monitor',
                'estimated_time' => '5 minutes'
            ];
            return $recommendations;
        }

        // Analyze issues for specific recommendations
        $ajax_issues = array_filter($critical_issues, function($issue) {
            return strpos($issue, 'AJAX') !== false || strpos($issue, 'Configuration inconsistent') !== false;
        });

        $config_issues = array_filter($critical_issues, function($issue) {
            return strpos($issue, 'S3 not configured') !== false || strpos($issue, 'configuration') !== false;
        });

        $class_issues = array_filter($critical_issues, function($issue) {
            return strpos($issue, 'Class') !== false || strpos($issue, 'not found') !== false;
        });

        if (!empty($ajax_issues)) {
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'Fix AJAX context configuration inconsistency - ensure S3 Integration class is instantiated consistently',
                'estimated_time' => '30-60 minutes',
                'technical_details' => 'The S3 configuration works in admin context but fails in AJAX context, indicating an instantiation or loading issue.'
            ];
        }

        if (!empty($config_issues) && empty($ajax_issues)) {
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'Complete S3 configuration - set AWS credentials and bucket information',
                'estimated_time' => '15-30 minutes',
                'technical_details' => 'S3 integration requires AWS access key, secret key, and bucket name to be configured.'
            ];
        }

        if (!empty($class_issues)) {
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'Fix plugin installation - ensure H3 Tour Management plugin is properly activated',
                'estimated_time' => '10-15 minutes',
                'technical_details' => 'Required plugin classes are not available, indicating plugin activation or file permission issues.'
            ];
        }

        if (count($critical_issues) > count($ajax_issues) + count($config_issues) + count($class_issues)) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Address remaining system issues - review detailed test results for specific problems',
                'estimated_time' => '60-120 minutes',
                'technical_details' => 'Multiple issues detected that require individual attention.'
            ];
        }

        return $recommendations;
    }

    /**
     * Identify the primary issue
     */
    private function identify_primary_issue($critical_issues) {
        if (empty($critical_issues)) {
            return 'No critical issues identified';
        }

        // Priority order for issue identification
        $issue_patterns = [
            'Configuration inconsistent.*AJAX' => 'AJAX context configuration inconsistency (most likely root cause)',
            'S3 not configured' => 'S3 configuration incomplete',
            'Class.*not found' => 'Plugin installation or activation issue',
            'AJAX.*handler' => 'AJAX handler registration problem',
            'AWS.*credential' => 'AWS authentication issue',
            'Network.*connectivity' => 'Network connectivity problem'
        ];

        foreach ($issue_patterns as $pattern => $description) {
            foreach ($critical_issues as $issue) {
                if (preg_match("/$pattern/i", $issue)) {
                    return $description;
                }
            }
        }

        return 'Multiple issues detected - see detailed results';
    }

    /**
     * Check if system is ready for S3 usage
     */
    private function is_system_ready_for_s3($critical_issues) {
        // System is ready if no critical configuration or AJAX issues
        foreach ($critical_issues as $issue) {
            if (strpos($issue, 'Configuration inconsistent') !== false ||
                strpos($issue, 'S3 not configured') !== false ||
                strpos($issue, 'AJAX') !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate next steps based on system status
     */
    private function generate_next_steps($system_status, $critical_issues) {
        switch ($system_status) {
            case 'healthy':
                return [
                    '1. System is ready for S3 uploads',
                    '2. Test with a small file upload to verify functionality',
                    '3. Monitor system performance during actual usage',
                    '4. Run diagnostics monthly or after any configuration changes'
                ];

            case 'ajax_configuration_issue':
                return [
                    '1. CRITICAL: Fix AJAX context configuration inconsistency',
                    '2. Ensure S3 Integration class is instantiated during WordPress init',
                    '3. Verify configuration is accessible in AJAX context',
                    '4. Re-run tests after fix to confirm resolution'
                ];

            case 's3_not_configured':
                return [
                    '1. Configure AWS S3 settings (access key, secret key, bucket name)',
                    '2. Verify AWS credentials have proper S3 permissions',
                    '3. Test S3 connectivity after configuration',
                    '4. Run full test suite to verify functionality'
                ];

            case 'plugin_issue':
                return [
                    '1. Verify H3 Tour Management plugin is active',
                    '2. Check file permissions and plugin file integrity',
                    '3. Deactivate and reactivate plugin if necessary',
                    '4. Re-run tests after plugin fix'
                ];

            case 'multiple_critical_issues':
                return [
                    '1. Address critical issues one at a time, starting with configuration',
                    '2. Re-run diagnostics after each fix to track progress',
                    '3. Consider fresh plugin installation if issues persist',
                    '4. Contact support with detailed test results if needed'
                ];

            default:
                return [
                    '1. Review detailed test results to understand specific issues',
                    '2. Address highest priority issues first',
                    '3. Re-run tests after each fix',
                    '4. Monitor system behavior during testing'
                ];
        }
    }

    /**
     * Generate support information
     */
    private function generate_support_information() {
        return [
            'system_info' => [
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'plugin_version' => defined('H3TM_VERSION') ? H3TM_VERSION : 'unknown',
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ],
            'log_locations' => [
                'wordpress_debug_log' => WP_CONTENT_DIR . '/debug.log',
                'test_results_location' => wp_upload_dir()['basedir'] . '/h3tm-test-results/',
                'error_log_filter' => 'grep "H3TM" /path/to/error.log'
            ],
            'useful_commands' => [
                'quick_diagnostic' => 'wp eval-file tests/s3-configuration-debugger.php',
                'check_plugin_status' => 'wp plugin list | grep h3-tour-management',
                'run_specific_test' => 'wp eval-file tests/test-s3-configuration.php',
                'view_recent_errors' => 'tail -50 /path/to/debug.log | grep H3TM'
            ]
        ];
    }

    /**
     * Helper methods
     */

    private function log_execution($message, $level = 'info') {
        $this->execution_log[] = [
            'timestamp' => microtime(true),
            'level' => $level,
            'message' => $message
        ];

        error_log("H3TM Master Test Runner [$level]: $message");
    }

    private function get_phase_execution_time() {
        $current_time = microtime(true);
        $last_log_time = !empty($this->execution_log) ?
            end($this->execution_log)['timestamp'] : $this->start_time;

        return round($current_time - $last_log_time, 2);
    }

    /**
     * Export consolidated report to files
     */
    public function export_report($report, $base_filename = null) {
        if ($base_filename === null) {
            $base_filename = 'h3tm-s3-comprehensive-test-' . date('Y-m-d-H-i-s');
        }

        $upload_dir = wp_upload_dir();
        $report_dir = $upload_dir['basedir'] . '/h3tm-test-results';

        if (!file_exists($report_dir)) {
            wp_mkdir_p($report_dir);
        }

        // Export full JSON report
        $json_file = $report_dir . '/' . $base_filename . '.json';
        file_put_contents($json_file, json_encode($report, JSON_PRETTY_PRINT));

        // Export executive summary
        $summary_file = $report_dir . '/' . $base_filename . '-summary.txt';
        $summary_content = $this->generate_summary_text($report);
        file_put_contents($summary_file, $summary_content);

        // Export action plan
        $action_file = $report_dir . '/' . $base_filename . '-action-plan.txt';
        $action_content = $this->generate_action_plan_text($report);
        file_put_contents($action_file, $action_content);

        return [
            'json_report' => $json_file,
            'summary_report' => $summary_file,
            'action_plan' => $action_file,
            'report_directory' => $report_dir
        ];
    }

    private function generate_summary_text($report) {
        $summary = "H3 Tour Management S3 Integration - Test Summary\n";
        $summary .= "Generated: " . $report['meta']['timestamp'] . "\n";
        $summary .= str_repeat("=", 60) . "\n\n";

        $summary .= "SYSTEM STATUS: " . strtoupper($report['executive_summary']['system_status']) . "\n";
        $summary .= "Ready for S3: " . ($report['executive_summary']['system_ready_for_s3'] ? 'YES' : 'NO') . "\n";
        $summary .= "Primary Issue: " . $report['executive_summary']['primary_issue'] . "\n";
        $summary .= "Critical Issues: " . $report['executive_summary']['critical_issues_count'] . "\n\n";

        $summary .= "TEST RESULTS:\n";
        $summary .= "- Total Test Suites: " . $report['executive_summary']['total_test_suites'] . "\n";
        $summary .= "- Successful: " . $report['executive_summary']['successful_test_suites'] . "\n";
        $summary .= "- Failed: " . $report['executive_summary']['failed_test_suites'] . "\n";
        $summary .= "- Individual Tests: " . $report['executive_summary']['total_individual_tests'] . "\n";
        $summary .= "- Execution Time: " . $report['meta']['total_execution_time_seconds'] . " seconds\n\n";

        if (!empty($report['critical_issues'])) {
            $summary .= "CRITICAL ISSUES:\n";
            foreach ($report['critical_issues'] as $i => $issue) {
                $summary .= ($i + 1) . ". " . $issue . "\n";
            }
            $summary .= "\n";
        }

        $summary .= "NEXT STEPS:\n";
        foreach ($report['next_steps'] as $step) {
            $summary .= $step . "\n";
        }

        return $summary;
    }

    private function generate_action_plan_text($report) {
        $action_plan = "H3 Tour Management S3 Integration - Action Plan\n";
        $action_plan .= "Generated: " . $report['meta']['timestamp'] . "\n";
        $action_plan .= str_repeat("=", 60) . "\n\n";

        if (!empty($report['primary_recommendations'])) {
            $action_plan .= "PRIMARY RECOMMENDATIONS:\n";
            foreach ($report['primary_recommendations'] as $i => $rec) {
                $action_plan .= ($i + 1) . ". [" . strtoupper($rec['priority']) . "] " . $rec['action'] . "\n";
                $action_plan .= "   Estimated Time: " . $rec['estimated_time'] . "\n";
                if (isset($rec['technical_details'])) {
                    $action_plan .= "   Details: " . $rec['technical_details'] . "\n";
                }
                $action_plan .= "\n";
            }
        }

        $action_plan .= "NEXT STEPS:\n";
        foreach ($report['next_steps'] as $step) {
            $action_plan .= $step . "\n";
        }
        $action_plan .= "\n";

        $action_plan .= "USEFUL COMMANDS:\n";
        foreach ($report['support_information']['useful_commands'] as $purpose => $command) {
            $action_plan .= ucwords(str_replace('_', ' ', $purpose)) . ": $command\n";
        }

        return $action_plan;
    }
}

// Allow direct execution
if (defined('WP_CLI') || (defined('ABSPATH') && !defined('DOING_AJAX') && current_user_can('manage_options'))) {

    function run_h3tm_s3_comprehensive_tests($include_slow_tests = true) {
        $master_runner = new H3TM_S3_Master_Test_Runner();
        $results = $master_runner->run_all_tests($include_slow_tests);

        // Export reports
        $exported_files = $master_runner->export_report($results);

        if (defined('WP_CLI')) {
            WP_CLI::success('H3TM S3 Comprehensive Test Suite completed');
            WP_CLI::log('System Status: ' . $results['executive_summary']['system_status']);
            WP_CLI::log('Primary Issue: ' . $results['executive_summary']['primary_issue']);
            WP_CLI::log('Critical Issues: ' . $results['executive_summary']['critical_issues_count']);
            WP_CLI::log('Reports exported to: ' . dirname($exported_files['json_report']));

            if (!$results['executive_summary']['system_ready_for_s3']) {
                WP_CLI::warning('System NOT ready for S3 uploads - address critical issues first');
            } else {
                WP_CLI::success('System appears ready for S3 uploads');
            }
        } else {
            echo '<h2>H3TM S3 Comprehensive Test Results</h2>';
            echo '<div style="background: #f1f1f1; padding: 20px; margin: 20px 0;">';
            echo '<h3>Executive Summary</h3>';
            echo '<p><strong>System Status:</strong> ' . $results['executive_summary']['system_status'] . '</p>';
            echo '<p><strong>Ready for S3:</strong> ' . ($results['executive_summary']['system_ready_for_s3'] ? 'YES' : 'NO') . '</p>';
            echo '<p><strong>Primary Issue:</strong> ' . $results['executive_summary']['primary_issue'] . '</p>';
            echo '<p><strong>Critical Issues:</strong> ' . $results['executive_summary']['critical_issues_count'] . '</p>';
            echo '</div>';

            if (!empty($results['critical_issues'])) {
                echo '<div style="background: #ffebe8; border-left: 4px solid #d63638; padding: 20px; margin: 20px 0;">';
                echo '<h3>Critical Issues</h3>';
                echo '<ul>';
                foreach ($results['critical_issues'] as $issue) {
                    echo '<li>' . esc_html($issue) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            echo '<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 20px; margin: 20px 0;">';
            echo '<h3>Next Steps</h3>';
            echo '<ol>';
            foreach ($results['next_steps'] as $step) {
                echo '<li>' . esc_html($step) . '</li>';
            }
            echo '</ol>';
            echo '</div>';

            echo '<p><strong>Detailed reports saved to:</strong> ' . esc_html(dirname($exported_files['json_report'])) . '</p>';

            echo '<details style="margin: 20px 0;">';
            echo '<summary>Full Test Results (Click to expand)</summary>';
            echo '<pre style="background: #f9f9f9; padding: 15px; overflow: auto; max-height: 500px;">';
            echo json_encode($results, JSON_PRETTY_PRINT);
            echo '</pre>';
            echo '</details>';
        }

        return $results;
    }

    // Auto-run based on query parameters
    if (isset($_GET['run_all_s3_tests']) ||
        isset($_GET['h3tm_comprehensive_test']) ||
        (isset($argv) && in_array('--run-all-tests', $argv))) {

        $include_slow_tests = !isset($_GET['fast_only']) && !in_array('--fast-only', $argv ?? []);
        run_h3tm_s3_comprehensive_tests($include_slow_tests);
    }
}