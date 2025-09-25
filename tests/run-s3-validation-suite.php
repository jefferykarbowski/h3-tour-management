<?php
/**
 * Master S3 Validation Suite Runner
 *
 * Orchestrates all S3-only system validation tests and provides
 * a comprehensive deployment readiness assessment.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 * @version 1.4.6
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Master S3 Validation Suite Runner
 */
class H3TM_S3_Master_Validation_Runner {

    private $validation_results = [];
    private $test_suite_status = [];
    private $overall_deployment_score = 0;
    private $critical_blockers = [];
    private $warnings = [];
    private $recommendations = [];

    public function __construct() {
        $this->initialize_validation_environment();
    }

    /**
     * Run complete S3 validation suite
     */
    public function run_complete_validation_suite() {
        echo "<div class='wrap'><h1>🧪 H3 Tour Management - Complete S3 Validation Suite</h1>";
        echo "<p class='description'>Comprehensive validation to ensure S3-only system is ready for deployment</p>";

        echo "<div class='validation-suite-container'>";

        $this->display_validation_overview();
        $this->run_all_validation_tests();
        $this->calculate_deployment_readiness();
        $this->generate_executive_summary();
        $this->provide_deployment_guidance();

        echo "</div></div>";
    }

    /**
     * Initialize validation environment
     */
    private function initialize_validation_environment() {
        // Ensure all required classes are loaded
        $this->load_validation_classes();

        // Set up error reporting for validation
        $this->setup_error_reporting();

        // Record baseline metrics
        $this->record_baseline_metrics();
    }

    /**
     * Load all validation classes
     */
    private function load_validation_classes() {
        $validation_files = [
            'comprehensive-s3-only-validation.php',
            's3-deployment-checklist.php',
            's3-performance-validator.php',
        ];

        foreach ($validation_files as $file) {
            $file_path = __DIR__ . '/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Setup error reporting for validation
     */
    private function setup_error_reporting() {
        // Capture errors during validation
        set_error_handler([$this, 'validation_error_handler']);
    }

    /**
     * Display validation overview
     */
    private function display_validation_overview() {
        echo "<div class='validation-overview'>";
        echo "<h2>🎯 Validation Suite Overview</h2>";

        $test_suites = [
            'comprehensive' => [
                'name' => 'Comprehensive S3-Only Validation',
                'description' => 'Complete system validation including code completeness, functionality, and error handling',
                'class' => 'H3TM_S3_Only_System_Validator',
                'priority' => 'CRITICAL',
                'estimated_time' => '5-10 minutes'
            ],
            'deployment_checklist' => [
                'name' => 'Deployment Readiness Checklist',
                'description' => 'Systematic checklist to ensure deployment readiness across all components',
                'class' => 'H3TM_S3_Deployment_Checklist',
                'priority' => 'HIGH',
                'estimated_time' => '3-5 minutes'
            ],
            'performance' => [
                'name' => 'Performance Validation',
                'description' => 'Performance testing for large file handling and system optimization',
                'class' => 'H3TM_S3_Performance_Validator',
                'priority' => 'MEDIUM',
                'estimated_time' => '5-8 minutes'
            ]
        ];

        echo "<table class='wp-list-table widefat fixed striped test-suite-overview'>";
        echo "<thead><tr><th>Test Suite</th><th>Description</th><th>Priority</th><th>Est. Time</th><th>Status</th></tr></thead>";
        echo "<tbody>";

        foreach ($test_suites as $suite_key => $suite) {
            $class_available = class_exists($suite['class']);
            $status_icon = $class_available ? '✅' : '❌';
            $status_text = $class_available ? 'Ready' : 'Class Missing';
            $status_class = $class_available ? 'status-ready' : 'status-error';

            echo "<tr class='{$status_class}'>";
            echo "<td><strong>{$suite['name']}</strong></td>";
            echo "<td>{$suite['description']}</td>";
            echo "<td><span class='priority priority-{$suite['priority']}'>{$suite['priority']}</span></td>";
            echo "<td>{$suite['estimated_time']}</td>";
            echo "<td>{$status_icon} {$status_text}</td>";
            echo "</tr>";

            $this->test_suite_status[$suite_key] = [
                'available' => $class_available,
                'class' => $suite['class'],
                'name' => $suite['name']
            ];
        }

        echo "</tbody></table>";
        echo "</div>";
    }

    /**
     * Run all validation tests
     */
    private function run_all_validation_tests() {
        echo "<div class='validation-tests-execution'>";
        echo "<h2>🚀 Executing Validation Tests</h2>";

        // Test Suite 1: Comprehensive S3-Only Validation
        $this->run_comprehensive_validation();

        // Test Suite 2: Deployment Checklist
        $this->run_deployment_checklist();

        // Test Suite 3: Performance Validation
        $this->run_performance_validation();

        echo "</div>";
    }

    /**
     * Run comprehensive S3-only validation
     */
    private function run_comprehensive_validation() {
        echo "<div class='test-suite-section'>";
        echo "<h3>🔍 Comprehensive S3-Only System Validation</h3>";

        if (!$this->test_suite_status['comprehensive']['available']) {
            echo "<div class='notice notice-error'><p>❌ H3TM_S3_Only_System_Validator class not available</p></div>";
            $this->validation_results['comprehensive'] = ['status' => 'UNAVAILABLE', 'score' => 0];
            echo "</div>";
            return;
        }

        try {
            $start_time = microtime(true);

            // Capture output for analysis
            ob_start();
            $validator = new H3TM_S3_Only_System_Validator();
            $validator->run_complete_validation();
            $output = ob_get_clean();

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;

            // Analyze validation output
            $comprehensive_results = $this->analyze_validation_output($output, 'comprehensive');
            $comprehensive_results['execution_time'] = $execution_time;

            $this->validation_results['comprehensive'] = $comprehensive_results;

            // Display results
            echo "<div class='test-results-summary'>";
            echo "<p><strong>Execution Time:</strong> " . number_format($execution_time, 2) . " seconds</p>";
            echo "<p><strong>Overall Status:</strong> " . $this->get_status_display($comprehensive_results['status']) . "</p>";
            echo "<p><strong>Score:</strong> {$comprehensive_results['score']}/100</p>";
            echo "</div>";

            // Display the actual validation output
            echo "<div class='test-output-container'>";
            echo "<details><summary>View Detailed Results</summary>";
            echo $output;
            echo "</details>";
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='notice notice-error'>";
            echo "<p>❌ Comprehensive validation failed: " . $e->getMessage() . "</p>";
            echo "</div>";

            $this->validation_results['comprehensive'] = [
                'status' => 'ERROR',
                'score' => 0,
                'error' => $e->getMessage()
            ];
        }

        echo "</div>";
    }

    /**
     * Run deployment checklist
     */
    private function run_deployment_checklist() {
        echo "<div class='test-suite-section'>";
        echo "<h3>📋 Deployment Readiness Checklist</h3>";

        if (!$this->test_suite_status['deployment_checklist']['available']) {
            echo "<div class='notice notice-error'><p>❌ H3TM_S3_Deployment_Checklist class not available</p></div>";
            $this->validation_results['deployment_checklist'] = ['status' => 'UNAVAILABLE', 'score' => 0];
            echo "</div>";
            return;
        }

        try {
            $start_time = microtime(true);

            // Capture output for analysis
            ob_start();
            $checklist = new H3TM_S3_Deployment_Checklist();
            $checklist->run_deployment_checklist();
            $output = ob_get_clean();

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;

            // Analyze checklist output
            $checklist_results = $this->analyze_validation_output($output, 'checklist');
            $checklist_results['execution_time'] = $execution_time;

            $this->validation_results['deployment_checklist'] = $checklist_results;

            // Display results
            echo "<div class='test-results-summary'>";
            echo "<p><strong>Execution Time:</strong> " . number_format($execution_time, 2) . " seconds</p>";
            echo "<p><strong>Overall Status:</strong> " . $this->get_status_display($checklist_results['status']) . "</p>";
            echo "<p><strong>Score:</strong> {$checklist_results['score']}/100</p>";
            echo "</div>";

            // Display the actual checklist output
            echo "<div class='test-output-container'>";
            echo "<details><summary>View Detailed Checklist</summary>";
            echo $output;
            echo "</details>";
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='notice notice-error'>";
            echo "<p>❌ Deployment checklist failed: " . $e->getMessage() . "</p>";
            echo "</div>";

            $this->validation_results['deployment_checklist'] = [
                'status' => 'ERROR',
                'score' => 0,
                'error' => $e->getMessage()
            ];
        }

        echo "</div>";
    }

    /**
     * Run performance validation
     */
    private function run_performance_validation() {
        echo "<div class='test-suite-section'>";
        echo "<h3>⚡ Performance Validation</h3>";

        if (!$this->test_suite_status['performance']['available']) {
            echo "<div class='notice notice-error'><p>❌ H3TM_S3_Performance_Validator class not available</p></div>";
            $this->validation_results['performance'] = ['status' => 'UNAVAILABLE', 'score' => 0];
            echo "</div>";
            return;
        }

        try {
            $start_time = microtime(true);

            // Capture output for analysis
            ob_start();
            $performance_validator = new H3TM_S3_Performance_Validator();
            $performance_validator->run_performance_validation();
            $output = ob_get_clean();

            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;

            // Analyze performance output
            $performance_results = $this->analyze_validation_output($output, 'performance');
            $performance_results['execution_time'] = $execution_time;

            $this->validation_results['performance'] = $performance_results;

            // Display results
            echo "<div class='test-results-summary'>";
            echo "<p><strong>Execution Time:</strong> " . number_format($execution_time, 2) . " seconds</p>";
            echo "<p><strong>Overall Status:</strong> " . $this->get_status_display($performance_results['status']) . "</p>";
            echo "<p><strong>Score:</strong> {$performance_results['score']}/100</p>";
            echo "</div>";

            // Display the actual performance output
            echo "<div class='test-output-container'>";
            echo "<details><summary>View Detailed Performance Results</summary>";
            echo $output;
            echo "</details>";
            echo "</div>";

        } catch (Exception $e) {
            echo "<div class='notice notice-error'>";
            echo "<p>❌ Performance validation failed: " . $e->getMessage() . "</p>";
            echo "</div>";

            $this->validation_results['performance'] = [
                'status' => 'ERROR',
                'score' => 0,
                'error' => $e->getMessage()
            ];
        }

        echo "</div>";
    }

    /**
     * Analyze validation output to extract results
     */
    private function analyze_validation_output($output, $test_type) {
        $results = [
            'status' => 'UNKNOWN',
            'score' => 0,
            'pass_count' => 0,
            'fail_count' => 0,
            'warning_count' => 0,
            'total_tests' => 0
        ];

        // Count status indicators in output
        $pass_count = preg_match_all('/✅|PASS/i', $output);
        $fail_count = preg_match_all('/❌|FAIL/i', $output);
        $warning_count = preg_match_all('/⚠️|WARNING/i', $output);

        $results['pass_count'] = $pass_count;
        $results['fail_count'] = $fail_count;
        $results['warning_count'] = $warning_count;
        $results['total_tests'] = $pass_count + $fail_count + $warning_count;

        // Calculate score
        if ($results['total_tests'] > 0) {
            $results['score'] = round(($pass_count / $results['total_tests']) * 100);
        }

        // Determine overall status
        if ($fail_count === 0 && $results['score'] >= 90) {
            $results['status'] = 'EXCELLENT';
        } elseif ($fail_count === 0 && $results['score'] >= 75) {
            $results['status'] = 'GOOD';
        } elseif ($fail_count <= 2 && $results['score'] >= 60) {
            $results['status'] = 'FAIR';
        } else {
            $results['status'] = 'POOR';
        }

        // Extract specific indicators based on test type
        switch ($test_type) {
            case 'comprehensive':
                $this->extract_comprehensive_indicators($output, $results);
                break;
            case 'checklist':
                $this->extract_checklist_indicators($output, $results);
                break;
            case 'performance':
                $this->extract_performance_indicators($output, $results);
                break;
        }

        return $results;
    }

    /**
     * Extract comprehensive validation indicators
     */
    private function extract_comprehensive_indicators($output, &$results) {
        // Look for deployment readiness indicators
        if (preg_match('/READY FOR DEPLOYMENT/', $output)) {
            $results['deployment_ready'] = true;
        } elseif (preg_match('/NOT READY FOR DEPLOYMENT/', $output)) {
            $results['deployment_ready'] = false;
        }

        // Look for critical issues
        if (preg_match_all('/critical.*issue/i', $output, $matches)) {
            $results['critical_issues'] = count($matches[0]);
            foreach ($matches[0] as $issue) {
                $this->critical_blockers[] = "Comprehensive Validation: $issue";
            }
        }
    }

    /**
     * Extract checklist indicators
     */
    private function extract_checklist_indicators($output, &$results) {
        // Look for checklist completion rate
        if (preg_match('/(\d+)\/(\d+) items completed/', $output, $matches)) {
            $completed = intval($matches[1]);
            $total = intval($matches[2]);
            $results['checklist_completion'] = ($completed / $total) * 100;
        }

        // Look for critical failures
        if (preg_match_all('/CRITICAL|FAIL/', $output, $matches)) {
            foreach ($matches[0] as $failure) {
                $this->critical_blockers[] = "Deployment Checklist: Critical issue detected";
            }
        }
    }

    /**
     * Extract performance indicators
     */
    private function extract_performance_indicators($output, &$results) {
        // Look for performance score
        if (preg_match('/Performance Score:\s*(\d+)\/100/', $output, $matches)) {
            $results['performance_score'] = intval($matches[1]);
        }

        // Look for memory issues
        if (preg_match('/memory.*critical|critical.*memory/i', $output)) {
            $this->warnings[] = "Performance: Memory usage concerns detected";
        }
    }

    /**
     * Calculate overall deployment readiness
     */
    private function calculate_deployment_readiness() {
        echo "<div class='deployment-readiness-calculation'>";
        echo "<h2>📊 Deployment Readiness Calculation</h2>";

        $total_score = 0;
        $weight_sum = 0;
        $test_weights = [
            'comprehensive' => 0.5,    // 50% weight - most critical
            'deployment_checklist' => 0.3,  // 30% weight - important
            'performance' => 0.2       // 20% weight - nice to have
        ];

        echo "<table class='wp-list-table widefat fixed striped readiness-calculation'>";
        echo "<thead><tr><th>Test Suite</th><th>Score</th><th>Weight</th><th>Weighted Score</th><th>Status</th></tr></thead>";
        echo "<tbody>";

        foreach ($this->validation_results as $test_name => $results) {
            $weight = $test_weights[$test_name] ?? 0;
            $weighted_score = $results['score'] * $weight;

            if ($results['status'] !== 'UNAVAILABLE') {
                $total_score += $weighted_score;
                $weight_sum += $weight;
            }

            echo "<tr>";
            echo "<td>" . ucwords(str_replace('_', ' ', $test_name)) . "</td>";
            echo "<td>{$results['score']}/100</td>";
            echo "<td>" . ($weight * 100) . "%</td>";
            echo "<td>" . number_format($weighted_score, 1) . "</td>";
            echo "<td>" . $this->get_status_display($results['status']) . "</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";

        // Calculate final score
        $this->overall_deployment_score = $weight_sum > 0 ? round($total_score / $weight_sum) : 0;

        echo "<div class='final-score-display'>";
        echo "<h3>🎯 Overall Deployment Readiness Score</h3>";
        echo "<div class='score-display score-{$this->get_score_category($this->overall_deployment_score)}'>";
        echo "<span class='score-number'>{$this->overall_deployment_score}</span>";
        echo "<span class='score-denominator'>/100</span>";
        echo "</div>";
        echo "<p class='score-description'>" . $this->get_score_description($this->overall_deployment_score) . "</p>";
        echo "</div>";

        echo "</div>";
    }

    /**
     * Generate executive summary
     */
    private function generate_executive_summary() {
        echo "<div class='executive-summary'>";
        echo "<h2>📋 Executive Summary</h2>";

        $deployment_recommendation = $this->get_deployment_recommendation();

        echo "<div class='summary-card {$deployment_recommendation['class']}'>";
        echo "<div class='summary-header'>";
        echo "<span class='summary-icon'>{$deployment_recommendation['icon']}</span>";
        echo "<h3>{$deployment_recommendation['title']}</h3>";
        echo "</div>";

        echo "<div class='summary-content'>";
        echo "<p><strong>Overall Assessment:</strong> {$deployment_recommendation['assessment']}</p>";
        echo "<p><strong>Recommendation:</strong> {$deployment_recommendation['recommendation']}</p>";

        // Key metrics
        echo "<h4>Key Metrics</h4>";
        echo "<ul class='key-metrics'>";
        echo "<li><strong>Deployment Score:</strong> {$this->overall_deployment_score}/100</li>";

        $total_tests = 0;
        $total_passes = 0;
        $total_failures = 0;
        foreach ($this->validation_results as $results) {
            $total_tests += $results['total_tests'];
            $total_passes += $results['pass_count'];
            $total_failures += $results['fail_count'];
        }

        echo "<li><strong>Total Tests:</strong> {$total_tests}</li>";
        echo "<li><strong>Passed:</strong> {$total_passes}</li>";
        echo "<li><strong>Failed:</strong> {$total_failures}</li>";
        echo "<li><strong>Critical Blockers:</strong> " . count($this->critical_blockers) . "</li>";
        echo "<li><strong>Warnings:</strong> " . count($this->warnings) . "</li>";
        echo "</ul>";

        echo "</div>";
        echo "</div>";

        // Critical issues summary
        if (!empty($this->critical_blockers)) {
            echo "<div class='critical-issues-summary'>";
            echo "<h3>🚨 Critical Issues Requiring Immediate Attention</h3>";
            echo "<ul class='critical-issues-list'>";
            foreach ($this->critical_blockers as $blocker) {
                echo "<li>{$blocker}</li>";
            }
            echo "</ul>";
            echo "</div>";
        }

        // Warnings summary
        if (!empty($this->warnings)) {
            echo "<div class='warnings-summary'>";
            echo "<h3>⚠️ Warnings to Address</h3>";
            echo "<ul class='warnings-list'>";
            foreach (array_slice($this->warnings, 0, 10) as $warning) {
                echo "<li>{$warning}</li>";
            }
            if (count($this->warnings) > 10) {
                echo "<li><em>... and " . (count($this->warnings) - 10) . " more warnings</em></li>";
            }
            echo "</ul>";
            echo "</div>";
        }

        echo "</div>";
    }

    /**
     * Provide deployment guidance
     */
    private function provide_deployment_guidance() {
        echo "<div class='deployment-guidance'>";
        echo "<h2>🚀 Deployment Guidance</h2>";

        $guidance = $this->generate_deployment_guidance();

        foreach ($guidance as $section => $content) {
            echo "<div class='guidance-section'>";
            echo "<h3>{$content['title']}</h3>";

            if (isset($content['items']) && is_array($content['items'])) {
                echo "<ul class='guidance-list'>";
                foreach ($content['items'] as $item) {
                    echo "<li>{$item}</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>{$content['description']}</p>";
            }

            echo "</div>";
        }

        // Final deployment checklist
        echo "<div class='final-checklist'>";
        echo "<h3>✅ Final Pre-Deployment Checklist</h3>";

        $final_checklist = $this->generate_final_checklist();

        echo "<div class='checklist-grid'>";
        foreach ($final_checklist as $category => $items) {
            echo "<div class='checklist-category'>";
            echo "<h4>{$category}</h4>";
            echo "<ul>";
            foreach ($items as $item) {
                $status_icon = $item['completed'] ? '✅' : '⏳';
                $item_class = $item['completed'] ? 'completed' : 'pending';
                echo "<li class='{$item_class}'>{$status_icon} {$item['text']}</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        echo "</div>";

        echo "</div>";
        echo "</div>";
    }

    /**
     * Generate deployment guidance based on results
     */
    private function generate_deployment_guidance() {
        $guidance = [];

        if ($this->overall_deployment_score >= 85) {
            $guidance['immediate'] = [
                'title' => '🟢 Immediate Deployment Actions',
                'items' => [
                    'Run final tests in staging environment',
                    'Backup current system before deployment',
                    'Schedule deployment during low-traffic period',
                    'Prepare rollback plan just in case',
                    'Monitor system closely after deployment'
                ]
            ];
        } elseif ($this->overall_deployment_score >= 70) {
            $guidance['pre_deployment'] = [
                'title' => '🟡 Pre-Deployment Requirements',
                'items' => [
                    'Address all critical issues identified in validation',
                    'Review and resolve high-priority warnings',
                    'Test in staging environment with production-like data',
                    'Update documentation and user guides',
                    'Plan phased rollout if possible'
                ]
            ];
        } else {
            $guidance['development_needed'] = [
                'title' => '🔴 Development Work Required',
                'items' => [
                    'Resolve all critical validation failures',
                    'Complete S3-only implementation',
                    'Implement proper error handling',
                    'Optimize performance for large files',
                    'Re-run validation after fixes'
                ]
            ];
        }

        // Always include monitoring guidance
        $guidance['monitoring'] = [
            'title' => '📊 Post-Deployment Monitoring',
            'items' => [
                'Monitor S3 upload success rates',
                'Track large file processing performance',
                'Watch for error patterns in logs',
                'Verify user experience with real uploads',
                'Monitor server resource usage'
            ]
        ];

        return $guidance;
    }

    /**
     * Generate final checklist
     */
    private function generate_final_checklist() {
        return [
            'Code Quality' => [
                ['text' => 'All chunked upload code removed', 'completed' => $this->is_requirement_met('chunked_removal')],
                ['text' => 'S3 classes properly implemented', 'completed' => $this->is_requirement_met('s3_classes')],
                ['text' => 'Error handling comprehensive', 'completed' => $this->is_requirement_met('error_handling')],
                ['text' => 'Code review completed', 'completed' => false] // Manual task
            ],
            'Configuration' => [
                ['text' => 'S3 configuration validated', 'completed' => $this->is_requirement_met('s3_config')],
                ['text' => 'Environment variables set', 'completed' => $this->is_requirement_met('environment')],
                ['text' => 'WordPress settings configured', 'completed' => $this->is_requirement_met('wp_settings')],
                ['text' => 'Backup strategy in place', 'completed' => false] // Manual task
            ],
            'Testing' => [
                ['text' => 'All validation tests passed', 'completed' => $this->overall_deployment_score >= 85],
                ['text' => 'Performance benchmarks met', 'completed' => $this->is_requirement_met('performance')],
                ['text' => 'Large file testing completed', 'completed' => $this->is_requirement_met('large_files')],
                ['text' => 'User acceptance testing done', 'completed' => false] // Manual task
            ],
            'Documentation' => [
                ['text' => 'Setup documentation updated', 'completed' => $this->is_requirement_met('setup_docs')],
                ['text' => 'User guides available', 'completed' => $this->is_requirement_met('user_docs')],
                ['text' => 'Troubleshooting guide ready', 'completed' => $this->is_requirement_met('troubleshooting')],
                ['text' => 'Changelog updated', 'completed' => $this->is_requirement_met('changelog')]
            ]
        ];
    }

    /**
     * Check if a requirement is met based on validation results
     */
    private function is_requirement_met($requirement) {
        // This would be based on analysis of validation results
        switch ($requirement) {
            case 'chunked_removal':
            case 's3_classes':
            case 'error_handling':
                return isset($this->validation_results['comprehensive']) &&
                       $this->validation_results['comprehensive']['score'] >= 80;

            case 's3_config':
            case 'environment':
            case 'wp_settings':
                return isset($this->validation_results['deployment_checklist']) &&
                       $this->validation_results['deployment_checklist']['score'] >= 75;

            case 'performance':
            case 'large_files':
                return isset($this->validation_results['performance']) &&
                       $this->validation_results['performance']['score'] >= 70;

            case 'setup_docs':
            case 'user_docs':
            case 'troubleshooting':
            case 'changelog':
                return false; // These need manual verification

            default:
                return false;
        }
    }

    /**
     * Helper methods
     */

    private function record_baseline_metrics() {
        $this->baseline_metrics = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ];
    }

    private function get_status_display($status) {
        $status_map = [
            'EXCELLENT' => '🟢 Excellent',
            'GOOD' => '🟢 Good',
            'FAIR' => '🟡 Fair',
            'POOR' => '🔴 Poor',
            'ERROR' => '❌ Error',
            'UNAVAILABLE' => '⭕ Unavailable',
            'UNKNOWN' => '❓ Unknown'
        ];

        return $status_map[$status] ?? $status;
    }

    private function get_score_category($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        return 'poor';
    }

    private function get_score_description($score) {
        if ($score >= 90) {
            return 'Excellent! The system is ready for production deployment with confidence.';
        } elseif ($score >= 75) {
            return 'Good performance with minor issues to address. Suitable for deployment with monitoring.';
        } elseif ($score >= 60) {
            return 'Fair performance. Address identified issues before deployment.';
        } else {
            return 'Poor performance. Significant work needed before deployment.';
        }
    }

    private function get_deployment_recommendation() {
        $score = $this->overall_deployment_score;
        $critical_count = count($this->critical_blockers);

        if ($score >= 85 && $critical_count === 0) {
            return [
                'icon' => '🚀',
                'title' => 'Ready for Deployment',
                'class' => 'deployment-ready',
                'assessment' => 'The S3-only system has passed all critical validations and is ready for production deployment.',
                'recommendation' => 'Proceed with deployment following standard release procedures. Monitor closely during initial rollout.'
            ];
        } elseif ($score >= 70 && $critical_count <= 1) {
            return [
                'icon' => '⚠️',
                'title' => 'Deploy with Caution',
                'class' => 'deployment-caution',
                'assessment' => 'The system is functional but has some issues that should be addressed.',
                'recommendation' => 'Address critical issues first, then proceed with phased deployment and close monitoring.'
            ];
        } else {
            return [
                'icon' => '🛑',
                'title' => 'Not Ready for Deployment',
                'class' => 'deployment-blocked',
                'assessment' => 'Significant issues were identified that must be resolved before deployment.',
                'recommendation' => 'Complete development work to address all critical issues, then re-run validation.'
            ];
        }
    }

    /**
     * Error handler for validation
     */
    public function validation_error_handler($severity, $message, $file, $line) {
        $this->warnings[] = "Validation Error: {$message} in {$file}:{$line}";
        return true; // Don't execute PHP's internal error handler
    }
}

// Auto-execute if accessed directly
if (defined('ABSPATH') && current_user_can('manage_options')) {
    echo "<style>
        .validation-suite-container { max-width: 1200px; }
        .test-suite-overview th, .readiness-calculation th { background: #f1f1f1; }
        .status-ready { background: #d4edda; }
        .status-error { background: #f8d7da; }
        .priority-CRITICAL { background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
        .priority-HIGH { background: #fd7e14; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
        .priority-MEDIUM { background: #ffc107; color: black; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
        .test-results-summary { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #007cba; }
        .test-output-container { margin: 15px 0; }
        .test-output-container details { border: 1px solid #ccd0d4; border-radius: 4px; }
        .test-output-container summary { background: #f1f1f1; padding: 10px; cursor: pointer; font-weight: bold; }
        .final-score-display { text-align: center; margin: 20px 0; }
        .score-display { font-size: 3em; font-weight: bold; margin: 10px 0; }
        .score-excellent { color: #28a745; }
        .score-good { color: #17a2b8; }
        .score-fair { color: #ffc107; }
        .score-poor { color: #dc3545; }
        .score-denominator { font-size: 0.5em; color: #6c757d; }
        .executive-summary { margin: 30px 0; }
        .summary-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin: 15px 0; }
        .deployment-ready { border-left: 5px solid #28a745; background: #d4edda; }
        .deployment-caution { border-left: 5px solid #ffc107; background: #fff3cd; }
        .deployment-blocked { border-left: 5px solid #dc3545; background: #f8d7da; }
        .summary-header { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .summary-icon { font-size: 2em; }
        .key-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .critical-issues-list, .warnings-list { color: #721c24; }
        .guidance-section { margin: 20px 0; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; }
        .checklist-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .checklist-category { background: #f8f9fa; padding: 15px; border-radius: 4px; }
        .checklist-category ul { list-style: none; padding-left: 0; }
        .checklist-category li { padding: 5px 0; }
        .checklist-category li.completed { color: #28a745; }
        .checklist-category li.pending { color: #6c757d; }
    </style>";

    $master_runner = new H3TM_S3_Master_Validation_Runner();
    $master_runner->run_complete_validation_suite();
}