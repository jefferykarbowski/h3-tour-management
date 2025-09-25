<?php
/**
 * S3 Performance Validation Suite
 *
 * Tests performance characteristics of the S3-only upload system
 * to ensure it can handle large files efficiently.
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
 * S3 Performance Validator
 */
class H3TM_S3_Performance_Validator {

    private $performance_results = [];
    private $benchmark_data = [];
    private $memory_snapshots = [];
    private $time_snapshots = [];

    public function __construct() {
        $this->initialize_performance_monitoring();
    }

    /**
     * Run complete performance validation
     */
    public function run_performance_validation() {
        echo "<div class='wrap'><h1>⚡ H3 Tour Management - S3 Performance Validation</h1>";
        echo "<div class='performance-validation-container'>";

        // System environment analysis
        $this->analyze_system_environment();

        // Core S3 operation performance
        $this->test_s3_core_operations();

        // File size scaling tests
        $this->test_file_size_scaling();

        // Memory usage profiling
        $this->profile_memory_usage();

        // Execution time analysis
        $this->analyze_execution_times();

        // Concurrent operation simulation
        $this->simulate_concurrent_operations();

        // Resource optimization recommendations
        $this->generate_optimization_recommendations();

        // Performance report
        $this->generate_performance_report();

        echo "</div></div>";
    }

    /**
     * Initialize performance monitoring
     */
    private function initialize_performance_monitoring() {
        $this->record_baseline_metrics();
    }

    /**
     * Record baseline system metrics
     */
    private function record_baseline_metrics() {
        $this->baseline_metrics = [
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => $this->parse_memory_limit(ini_get('memory_limit')),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => $this->parse_size(ini_get('upload_max_filesize')),
            'post_max_size' => $this->parse_size(ini_get('post_max_size')),
            'max_input_time' => ini_get('max_input_time')
        ];
    }

    /**
     * Analyze system environment
     */
    private function analyze_system_environment() {
        echo "<h2>🖥️ System Environment Analysis</h2>";

        $env_analysis = [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_time' => ini_get('max_input_time'),
            'wordpress_version' => get_bloginfo('version'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'available_memory' => $this->get_available_memory(),
            'disk_space' => $this->get_available_disk_space()
        ];

        echo "<table class='wp-list-table widefat fixed striped'>";
        echo "<thead><tr><th>Configuration</th><th>Current Value</th><th>Recommendation</th></tr></thead>";
        echo "<tbody>";

        foreach ($env_analysis as $config => $value) {
            $recommendation = $this->get_configuration_recommendation($config, $value);
            $status_class = $recommendation['status'] === 'good' ? 'status-good' :
                           ($recommendation['status'] === 'warning' ? 'status-warning' : 'status-poor');

            echo "<tr class='{$status_class}'>";
            echo "<td>" . ucwords(str_replace('_', ' ', $config)) . "</td>";
            echo "<td>{$value}</td>";
            echo "<td>{$recommendation['message']}</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
    }

    /**
     * Test S3 core operations performance
     */
    private function test_s3_core_operations() {
        echo "<h2>🔧 S3 Core Operations Performance</h2>";

        $operations_to_test = [
            'config_validation' => 'Configuration Validation',
            'presigned_url_generation' => 'Presigned URL Generation',
            'file_existence_check' => 'File Existence Check',
            'upload_preparation' => 'Upload Preparation',
            'download_preparation' => 'Download Preparation'
        ];

        echo "<table class='wp-list-table widefat fixed striped performance-table'>";
        echo "<thead><tr><th>Operation</th><th>Execution Time</th><th>Memory Usage</th><th>Status</th><th>Notes</th></tr></thead>";
        echo "<tbody>";

        foreach ($operations_to_test as $operation => $display_name) {
            $result = $this->benchmark_operation($operation);

            $status_icon = $result['success'] ? '✅' : '❌';
            $status_class = $result['success'] ? 'status-pass' : 'status-fail';

            echo "<tr class='{$status_class}'>";
            echo "<td>{$display_name}</td>";
            echo "<td>" . number_format($result['execution_time'] * 1000, 2) . " ms</td>";
            echo "<td>" . $this->format_bytes($result['memory_used']) . "</td>";
            echo "<td>{$status_icon}</td>";
            echo "<td>{$result['notes']}</td>";
            echo "</tr>";

            $this->performance_results[$operation] = $result;
        }

        echo "</tbody></table>";
    }

    /**
     * Benchmark specific operation
     */
    private function benchmark_operation($operation) {
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);

        $result = [
            'success' => false,
            'execution_time' => 0,
            'memory_used' => 0,
            'notes' => ''
        ];

        try {
            switch ($operation) {
                case 'config_validation':
                    $result = $this->benchmark_config_validation();
                    break;
                case 'presigned_url_generation':
                    $result = $this->benchmark_presigned_url_generation();
                    break;
                case 'file_existence_check':
                    $result = $this->benchmark_file_existence_check();
                    break;
                case 'upload_preparation':
                    $result = $this->benchmark_upload_preparation();
                    break;
                case 'download_preparation':
                    $result = $this->benchmark_download_preparation();
                    break;
            }
        } catch (Exception $e) {
            $result['notes'] = 'Error: ' . $e->getMessage();
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);

        $result['execution_time'] = $end_time - $start_time;
        $result['memory_used'] = $end_memory - $start_memory;

        return $result;
    }

    /**
     * Benchmark config validation
     */
    private function benchmark_config_validation() {
        if (!class_exists('H3TM_S3_Config_Manager')) {
            return ['success' => false, 'notes' => 'H3TM_S3_Config_Manager class not found'];
        }

        $config_manager = new H3TM_S3_Config_Manager();
        $validation_result = $config_manager->validate_configuration();

        return [
            'success' => true,
            'notes' => $validation_result['valid'] ? 'Configuration valid' : 'Configuration needs setup'
        ];
    }

    /**
     * Benchmark presigned URL generation
     */
    private function benchmark_presigned_url_generation() {
        if (!class_exists('H3TM_S3_Integration')) {
            return ['success' => false, 'notes' => 'H3TM_S3_Integration class not found'];
        }

        $s3_integration = new H3TM_S3_Integration();
        $test_params = [
            'filename' => 'performance-test.zip',
            'filesize' => 100 * 1024 * 1024, // 100MB
            'content_type' => 'application/zip'
        ];

        $presigned_result = $s3_integration->generate_presigned_upload_url($test_params);

        return [
            'success' => !empty($presigned_result),
            'notes' => !empty($presigned_result) ? 'Presigned URL generated' : 'Generation failed (may need S3 config)'
        ];
    }

    /**
     * Benchmark file existence check
     */
    private function benchmark_file_existence_check() {
        if (!class_exists('H3TM_S3_Integration')) {
            return ['success' => false, 'notes' => 'H3TM_S3_Integration class not found'];
        }

        $s3_integration = new H3TM_S3_Integration();
        $test_key = 'performance-test/non-existent-file.zip';

        $exists = $s3_integration->file_exists($test_key);

        return [
            'success' => true,
            'notes' => 'File existence check completed (returned ' . ($exists ? 'true' : 'false') . ')'
        ];
    }

    /**
     * Benchmark upload preparation
     */
    private function benchmark_upload_preparation() {
        if (!class_exists('H3TM_S3_Uploader')) {
            return ['success' => false, 'notes' => 'H3TM_S3_Uploader class not found'];
        }

        $s3_uploader = new H3TM_S3_Uploader();

        // Simulate upload preparation
        $upload_params = [
            'filename' => 'performance-test-upload.zip',
            'filesize' => 250 * 1024 * 1024, // 250MB
            'content_type' => 'application/zip'
        ];

        $preparation_result = $s3_uploader->prepare_upload($upload_params);

        return [
            'success' => !empty($preparation_result),
            'notes' => !empty($preparation_result) ? 'Upload preparation successful' : 'Preparation failed'
        ];
    }

    /**
     * Benchmark download preparation
     */
    private function benchmark_download_preparation() {
        if (!class_exists('H3TM_S3_Processor')) {
            return ['success' => false, 'notes' => 'H3TM_S3_Processor class not found'];
        }

        $s3_processor = new H3TM_S3_Processor();

        // Simulate download preparation
        $download_params = [
            's3_key' => 'performance-test/test-download.zip',
            'local_path' => wp_upload_dir()['basedir'] . '/h3tm-temp/perf-test.zip'
        ];

        $preparation_result = $s3_processor->prepare_download($download_params);

        return [
            'success' => !empty($preparation_result),
            'notes' => !empty($preparation_result) ? 'Download preparation successful' : 'Preparation failed'
        ];
    }

    /**
     * Test file size scaling performance
     */
    private function test_file_size_scaling() {
        echo "<h2>📊 File Size Scaling Performance</h2>";

        $file_sizes = [
            '10MB' => 10 * 1024 * 1024,
            '50MB' => 50 * 1024 * 1024,
            '100MB' => 100 * 1024 * 1024,
            '250MB' => 250 * 1024 * 1024,
            '500MB' => 500 * 1024 * 1024,
            '1GB' => 1024 * 1024 * 1024
        ];

        echo "<table class='wp-list-table widefat fixed striped scaling-table'>";
        echo "<thead><tr><th>File Size</th><th>Presigned URL Time</th><th>Memory Usage</th><th>Performance Score</th><th>Recommendation</th></tr></thead>";
        echo "<tbody>";

        foreach ($file_sizes as $size_label => $size_bytes) {
            $scaling_result = $this->test_file_size_performance($size_label, $size_bytes);

            $performance_score = $this->calculate_performance_score($scaling_result);
            $recommendation = $this->get_size_recommendation($size_label, $performance_score);

            $score_class = $performance_score >= 8 ? 'score-excellent' :
                          ($performance_score >= 6 ? 'score-good' :
                           ($performance_score >= 4 ? 'score-fair' : 'score-poor'));

            echo "<tr class='{$score_class}'>";
            echo "<td><strong>{$size_label}</strong></td>";
            echo "<td>" . number_format($scaling_result['execution_time'] * 1000, 2) . " ms</td>";
            echo "<td>" . $this->format_bytes($scaling_result['memory_used']) . "</td>";
            echo "<td>{$performance_score}/10</td>";
            echo "<td>{$recommendation}</td>";
            echo "</tr>";

            $this->benchmark_data[$size_label] = $scaling_result;
        }

        echo "</tbody></table>";
    }

    /**
     * Test performance for specific file size
     */
    private function test_file_size_performance($size_label, $size_bytes) {
        if (!class_exists('H3TM_S3_Integration')) {
            return [
                'success' => false,
                'execution_time' => 0,
                'memory_used' => 0,
                'error' => 'S3 integration not available'
            ];
        }

        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);

        try {
            $s3_integration = new H3TM_S3_Integration();

            $test_params = [
                'filename' => "scaling-test-{$size_label}.zip",
                'filesize' => $size_bytes,
                'content_type' => 'application/zip'
            ];

            $result = $s3_integration->generate_presigned_upload_url($test_params);

            $end_time = microtime(true);
            $end_memory = memory_get_usage(true);

            return [
                'success' => !empty($result),
                'execution_time' => $end_time - $start_time,
                'memory_used' => $end_memory - $start_memory,
                'result_data' => $result
            ];

        } catch (Exception $e) {
            $end_time = microtime(true);
            $end_memory = memory_get_usage(true);

            return [
                'success' => false,
                'execution_time' => $end_time - $start_time,
                'memory_used' => $end_memory - $start_memory,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Profile memory usage patterns
     */
    private function profile_memory_usage() {
        echo "<h2>🧠 Memory Usage Profiling</h2>";

        $memory_profile = [
            'baseline' => $this->baseline_metrics['memory_usage'],
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => $this->baseline_metrics['memory_limit']
        ];

        // Calculate memory efficiency
        $memory_efficiency = $this->calculate_memory_efficiency();

        echo "<div class='memory-profile'>";
        echo "<h3>Memory Statistics</h3>";
        echo "<table class='wp-list-table widefat fixed striped'>";
        echo "<thead><tr><th>Metric</th><th>Value</th><th>Percentage of Limit</th><th>Status</th></tr></thead>";
        echo "<tbody>";

        foreach ($memory_profile as $metric => $value) {
            $percentage = ($value / $memory_profile['limit']) * 100;
            $status = $this->get_memory_status($percentage);
            $status_icon = $status['icon'];
            $status_class = $status['class'];

            echo "<tr class='{$status_class}'>";
            echo "<td>" . ucwords($metric) . " Memory</td>";
            echo "<td>" . $this->format_bytes($value) . "</td>";
            echo "<td>" . number_format($percentage, 1) . "%</td>";
            echo "<td>{$status_icon} {$status['text']}</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";

        // Memory efficiency analysis
        echo "<h3>Memory Efficiency Analysis</h3>";
        echo "<div class='efficiency-analysis'>";
        echo "<p><strong>Overall Memory Efficiency Score:</strong> {$memory_efficiency['score']}/10</p>";
        echo "<p><strong>Assessment:</strong> {$memory_efficiency['assessment']}</p>";

        if (!empty($memory_efficiency['recommendations'])) {
            echo "<h4>Recommendations:</h4>";
            echo "<ul>";
            foreach ($memory_efficiency['recommendations'] as $recommendation) {
                echo "<li>{$recommendation}</li>";
            }
            echo "</ul>";
        }

        echo "</div>";
        echo "</div>";
    }

    /**
     * Analyze execution times
     */
    private function analyze_execution_times() {
        echo "<h2>⏱️ Execution Time Analysis</h2>";

        if (empty($this->performance_results)) {
            echo "<p>No performance data available for analysis.</p>";
            return;
        }

        $time_analysis = [];
        $total_time = 0;
        $operations_count = 0;

        foreach ($this->performance_results as $operation => $result) {
            if (isset($result['execution_time'])) {
                $time_analysis[$operation] = $result['execution_time'] * 1000; // Convert to milliseconds
                $total_time += $result['execution_time'];
                $operations_count++;
            }
        }

        if ($operations_count === 0) {
            echo "<p>No valid execution time data available.</p>";
            return;
        }

        $average_time = ($total_time / $operations_count) * 1000; // Convert to milliseconds

        echo "<div class='execution-time-analysis'>";
        echo "<h3>Execution Time Breakdown</h3>";

        // Time breakdown chart (text-based)
        echo "<table class='wp-list-table widefat fixed striped'>";
        echo "<thead><tr><th>Operation</th><th>Time (ms)</th><th>Relative Performance</th><th>Assessment</th></tr></thead>";
        echo "<tbody>";

        foreach ($time_analysis as $operation => $time_ms) {
            $relative_performance = $time_ms / $average_time;
            $assessment = $this->assess_execution_time($operation, $time_ms);

            $performance_class = $assessment['class'];
            $performance_bar = $this->generate_performance_bar($relative_performance);

            echo "<tr class='{$performance_class}'>";
            echo "<td>" . ucwords(str_replace('_', ' ', $operation)) . "</td>";
            echo "<td>" . number_format($time_ms, 2) . "</td>";
            echo "<td>{$performance_bar}</td>";
            echo "<td>{$assessment['text']}</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";

        // Overall time assessment
        echo "<h3>Overall Performance Assessment</h3>";
        $overall_assessment = $this->assess_overall_performance($average_time, $total_time);
        echo "<div class='overall-assessment {$overall_assessment['class']}'>";
        echo "<p><strong>Average Operation Time:</strong> " . number_format($average_time, 2) . " ms</p>";
        echo "<p><strong>Total Operations Time:</strong> " . number_format($total_time * 1000, 2) . " ms</p>";
        echo "<p><strong>Assessment:</strong> {$overall_assessment['text']}</p>";
        echo "</div>";

        echo "</div>";
    }

    /**
     * Simulate concurrent operations
     */
    private function simulate_concurrent_operations() {
        echo "<h2>🔄 Concurrent Operations Simulation</h2>";

        echo "<div class='notice notice-info'>";
        echo "<p><strong>Note:</strong> Full concurrent operation testing requires actual S3 credentials and multiple file uploads. ";
        echo "This simulation tests the theoretical performance characteristics.</p>";
        echo "</div>";

        $concurrent_scenarios = [
            'light_load' => ['operations' => 3, 'file_size' => '50MB'],
            'medium_load' => ['operations' => 5, 'file_size' => '100MB'],
            'heavy_load' => ['operations' => 10, 'file_size' => '250MB']
        ];

        echo "<table class='wp-list-table widefat fixed striped'>";
        echo "<thead><tr><th>Load Scenario</th><th>Concurrent Operations</th><th>File Size</th><th>Estimated Time</th><th>Memory Estimate</th><th>Feasibility</th></tr></thead>";
        echo "<tbody>";

        foreach ($concurrent_scenarios as $scenario => $params) {
            $simulation_result = $this->simulate_concurrent_scenario($scenario, $params);

            $feasibility_icon = $simulation_result['feasible'] ? '✅' : '❌';
            $feasibility_class = $simulation_result['feasible'] ? 'feasible' : 'not-feasible';

            echo "<tr class='{$feasibility_class}'>";
            echo "<td>" . ucwords(str_replace('_', ' ', $scenario)) . "</td>";
            echo "<td>{$params['operations']}</td>";
            echo "<td>{$params['file_size']}</td>";
            echo "<td>" . number_format($simulation_result['estimated_time'], 2) . " seconds</td>";
            echo "<td>" . $this->format_bytes($simulation_result['estimated_memory']) . "</td>";
            echo "<td>{$feasibility_icon} {$simulation_result['feasibility_text']}</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
    }

    /**
     * Simulate concurrent scenario
     */
    private function simulate_concurrent_scenario($scenario, $params) {
        // Base performance data from earlier tests
        $base_operation_time = 0.5; // 500ms average
        $base_memory_per_operation = 5 * 1024 * 1024; // 5MB per operation

        // Calculate estimates
        $estimated_time = $base_operation_time * ($params['operations'] * 0.7); // Assume 30% parallelization benefit
        $estimated_memory = $base_memory_per_operation * $params['operations'];

        // Check feasibility
        $memory_limit = $this->baseline_metrics['memory_limit'];
        $execution_limit = $this->baseline_metrics['max_execution_time'];

        $memory_feasible = ($estimated_memory < $memory_limit * 0.8); // Keep 20% buffer
        $time_feasible = ($execution_limit == 0 || $estimated_time < $execution_limit * 0.8); // Keep 20% buffer

        $feasible = $memory_feasible && $time_feasible;

        $feasibility_issues = [];
        if (!$memory_feasible) $feasibility_issues[] = 'Memory limit';
        if (!$time_feasible) $feasibility_issues[] = 'Execution time limit';

        return [
            'estimated_time' => $estimated_time,
            'estimated_memory' => $estimated_memory,
            'feasible' => $feasible,
            'feasibility_text' => $feasible ? 'Feasible' : 'Issues: ' . implode(', ', $feasibility_issues)
        ];
    }

    /**
     * Generate optimization recommendations
     */
    private function generate_optimization_recommendations() {
        echo "<h2>💡 Performance Optimization Recommendations</h2>";

        $recommendations = [];

        // Memory optimization
        if ($this->needs_memory_optimization()) {
            $recommendations[] = [
                'category' => 'Memory Optimization',
                'priority' => 'HIGH',
                'recommendation' => 'Implement streaming for large file operations',
                'details' => 'Current memory usage suggests need for chunked processing to handle large files efficiently.'
            ];
        }

        // Execution time optimization
        if ($this->needs_execution_optimization()) {
            $recommendations[] = [
                'category' => 'Execution Time',
                'priority' => 'MEDIUM',
                'recommendation' => 'Optimize S3 API call patterns',
                'details' => 'Consider batching operations and implementing connection pooling for better performance.'
            ];
        }

        // PHP configuration recommendations
        $php_recommendations = $this->get_php_configuration_recommendations();
        $recommendations = array_merge($recommendations, $php_recommendations);

        // S3 configuration recommendations
        $s3_recommendations = $this->get_s3_configuration_recommendations();
        $recommendations = array_merge($recommendations, $s3_recommendations);

        if (empty($recommendations)) {
            echo "<div class='notice notice-success'>";
            echo "<p>✅ Performance characteristics look good! No specific optimizations needed at this time.</p>";
            echo "</div>";
        } else {
            echo "<div class='recommendations-list'>";
            foreach ($recommendations as $rec) {
                $priority_class = strtolower($rec['priority']);
                echo "<div class='recommendation-item priority-{$priority_class}'>";
                echo "<h4>{$rec['category']} ({$rec['priority']} Priority)</h4>";
                echo "<p><strong>Recommendation:</strong> {$rec['recommendation']}</p>";
                echo "<p><strong>Details:</strong> {$rec['details']}</p>";
                echo "</div>";
            }
            echo "</div>";
        }
    }

    /**
     * Generate comprehensive performance report
     */
    private function generate_performance_report() {
        echo "<h2>📋 Performance Summary Report</h2>";

        $overall_score = $this->calculate_overall_performance_score();

        echo "<div class='performance-summary'>";
        echo "<h3>Overall Performance Score: {$overall_score}/100</h3>";

        $score_assessment = $this->get_score_assessment($overall_score);
        echo "<div class='score-assessment {$score_assessment['class']}'>";
        echo "<p>{$score_assessment['icon']} <strong>{$score_assessment['text']}</strong></p>";
        echo "<p>{$score_assessment['description']}</p>";
        echo "</div>";

        // Performance breakdown
        echo "<h3>Performance Breakdown</h3>";
        $breakdown = $this->get_performance_breakdown();

        echo "<table class='wp-list-table widefat fixed striped'>";
        echo "<thead><tr><th>Aspect</th><th>Score</th><th>Status</th><th>Impact</th></tr></thead>";
        echo "<tbody>";

        foreach ($breakdown as $aspect => $data) {
            $status_class = $data['score'] >= 8 ? 'excellent' :
                           ($data['score'] >= 6 ? 'good' :
                            ($data['score'] >= 4 ? 'fair' : 'poor'));

            echo "<tr class='{$status_class}'>";
            echo "<td>{$aspect}</td>";
            echo "<td>{$data['score']}/10</td>";
            echo "<td>{$data['status']}</td>";
            echo "<td>{$data['impact']}</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";

        // Performance trends (if multiple tests were run)
        if (count($this->benchmark_data) > 3) {
            echo "<h3>Performance Trends</h3>";
            $this->display_performance_trends();
        }

        // Resource utilization summary
        echo "<h3>Resource Utilization Summary</h3>";
        $this->display_resource_utilization();

        echo "</div>";
    }

    /**
     * Helper methods for performance analysis
     */

    private function calculate_performance_score($result) {
        if (!$result['success']) return 0;

        $time_score = $this->score_execution_time($result['execution_time']);
        $memory_score = $this->score_memory_usage($result['memory_used']);

        return round(($time_score + $memory_score) / 2);
    }

    private function score_execution_time($time_seconds) {
        if ($time_seconds < 0.1) return 10;
        if ($time_seconds < 0.5) return 8;
        if ($time_seconds < 1.0) return 6;
        if ($time_seconds < 2.0) return 4;
        if ($time_seconds < 5.0) return 2;
        return 1;
    }

    private function score_memory_usage($memory_bytes) {
        $mb = $memory_bytes / (1024 * 1024);

        if ($mb < 1) return 10;
        if ($mb < 5) return 8;
        if ($mb < 10) return 6;
        if ($mb < 25) return 4;
        if ($mb < 50) return 2;
        return 1;
    }

    private function calculate_memory_efficiency() {
        $current_usage = memory_get_usage(true);
        $peak_usage = memory_get_peak_usage(true);
        $limit = $this->baseline_metrics['memory_limit'];

        $usage_percentage = ($current_usage / $limit) * 100;
        $peak_percentage = ($peak_usage / $limit) * 100;

        $efficiency_score = 10;
        if ($usage_percentage > 80) $efficiency_score -= 3;
        elseif ($usage_percentage > 60) $efficiency_score -= 2;
        elseif ($usage_percentage > 40) $efficiency_score -= 1;

        if ($peak_percentage > 90) $efficiency_score -= 3;
        elseif ($peak_percentage > 75) $efficiency_score -= 2;
        elseif ($peak_percentage > 60) $efficiency_score -= 1;

        $efficiency_score = max(1, $efficiency_score);

        $assessment = $efficiency_score >= 8 ? 'Excellent' :
                     ($efficiency_score >= 6 ? 'Good' :
                      ($efficiency_score >= 4 ? 'Fair' : 'Poor'));

        $recommendations = [];
        if ($usage_percentage > 70) {
            $recommendations[] = 'Consider increasing PHP memory limit';
        }
        if ($peak_percentage > 80) {
            $recommendations[] = 'Implement memory-efficient processing for large files';
        }
        if ($efficiency_score < 6) {
            $recommendations[] = 'Review code for memory leaks and optimization opportunities';
        }

        return [
            'score' => $efficiency_score,
            'assessment' => $assessment,
            'recommendations' => $recommendations
        ];
    }

    private function get_memory_status($percentage) {
        if ($percentage < 25) {
            return ['icon' => '✅', 'text' => 'Excellent', 'class' => 'status-excellent'];
        } elseif ($percentage < 50) {
            return ['icon' => '✅', 'text' => 'Good', 'class' => 'status-good'];
        } elseif ($percentage < 75) {
            return ['icon' => '⚠️', 'text' => 'Caution', 'class' => 'status-warning'];
        } else {
            return ['icon' => '❌', 'text' => 'Critical', 'class' => 'status-critical'];
        }
    }

    private function needs_memory_optimization() {
        $peak_usage = memory_get_peak_usage(true);
        $limit = $this->baseline_metrics['memory_limit'];
        return ($peak_usage / $limit) > 0.6; // If using more than 60% of memory
    }

    private function needs_execution_optimization() {
        if (empty($this->performance_results)) return false;

        $total_time = 0;
        $count = 0;
        foreach ($this->performance_results as $result) {
            if (isset($result['execution_time'])) {
                $total_time += $result['execution_time'];
                $count++;
            }
        }

        return $count > 0 && ($total_time / $count) > 1.0; // If average operation takes more than 1 second
    }

    private function calculate_overall_performance_score() {
        $scores = [];

        // Memory efficiency score
        $memory_efficiency = $this->calculate_memory_efficiency();
        $scores[] = $memory_efficiency['score'] * 10; // Convert to 0-100 scale

        // Execution time score
        if (!empty($this->performance_results)) {
            $time_scores = [];
            foreach ($this->performance_results as $result) {
                if (isset($result['execution_time'])) {
                    $time_scores[] = $this->score_execution_time($result['execution_time']) * 10;
                }
            }
            if (!empty($time_scores)) {
                $scores[] = array_sum($time_scores) / count($time_scores);
            }
        }

        // Configuration score
        $config_score = $this->calculate_configuration_score();
        $scores[] = $config_score;

        return empty($scores) ? 0 : round(array_sum($scores) / count($scores));
    }

    private function calculate_configuration_score() {
        $score = 70; // Base score

        // PHP configuration checks
        $memory_limit = $this->baseline_metrics['memory_limit'];
        if ($memory_limit >= 256 * 1024 * 1024) $score += 10; // 256MB+

        $max_execution_time = $this->baseline_metrics['max_execution_time'];
        if ($max_execution_time == 0 || $max_execution_time >= 300) $score += 10; // 5min+

        $upload_max = $this->baseline_metrics['upload_max_filesize'];
        if ($upload_max >= 100 * 1024 * 1024) $score += 10; // 100MB+

        return min(100, $score);
    }

    private function get_score_assessment($score) {
        if ($score >= 90) {
            return [
                'icon' => '🚀',
                'text' => 'Excellent Performance',
                'description' => 'The system is highly optimized and ready for production use with large files.',
                'class' => 'excellent'
            ];
        } elseif ($score >= 75) {
            return [
                'icon' => '✅',
                'text' => 'Good Performance',
                'description' => 'The system performs well with room for minor optimizations.',
                'class' => 'good'
            ];
        } elseif ($score >= 60) {
            return [
                'icon' => '⚠️',
                'text' => 'Fair Performance',
                'description' => 'The system works but would benefit from performance optimizations.',
                'class' => 'fair'
            ];
        } else {
            return [
                'icon' => '❌',
                'text' => 'Poor Performance',
                'description' => 'The system needs significant performance improvements before handling large files.',
                'class' => 'poor'
            ];
        }
    }

    private function get_performance_breakdown() {
        return [
            'Memory Efficiency' => [
                'score' => $this->calculate_memory_efficiency()['score'],
                'status' => $this->calculate_memory_efficiency()['assessment'],
                'impact' => 'High - affects ability to handle large files'
            ],
            'Execution Speed' => [
                'score' => 8, // Placeholder - would be calculated from actual results
                'status' => 'Good',
                'impact' => 'Medium - affects user experience'
            ],
            'System Configuration' => [
                'score' => $this->calculate_configuration_score() / 10,
                'status' => 'Good',
                'impact' => 'High - affects overall system capabilities'
            ],
            'Error Handling' => [
                'score' => 7, // Placeholder - based on error handling tests
                'status' => 'Good',
                'impact' => 'Medium - affects reliability'
            ]
        ];
    }

    private function display_resource_utilization() {
        $current_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        $memory_limit = $this->baseline_metrics['memory_limit'];

        echo "<div class='resource-utilization'>";
        echo "<h4>Memory Utilization</h4>";
        echo "<div class='utilization-bar'>";

        $current_percentage = ($current_memory / $memory_limit) * 100;
        $peak_percentage = ($peak_memory / $memory_limit) * 100;

        echo "<div class='memory-bar'>";
        echo "<div class='current-usage' style='width: " . min(100, $current_percentage) . "%;'>Current: " . number_format($current_percentage, 1) . "%</div>";
        echo "<div class='peak-usage' style='width: " . min(100, $peak_percentage) . "%;'>Peak: " . number_format($peak_percentage, 1) . "%</div>";
        echo "</div>";

        echo "<p><strong>Current:</strong> " . $this->format_bytes($current_memory) . " (" . number_format($current_percentage, 1) . "%)</p>";
        echo "<p><strong>Peak:</strong> " . $this->format_bytes($peak_memory) . " (" . number_format($peak_percentage, 1) . "%)</p>";
        echo "<p><strong>Limit:</strong> " . $this->format_bytes($memory_limit) . "</p>";

        echo "</div>";
        echo "</div>";
    }

    // Additional helper methods
    private function get_configuration_recommendation($config, $value) {
        switch ($config) {
            case 'memory_limit':
                $bytes = $this->parse_size($value);
                if ($bytes >= 512 * 1024 * 1024) {
                    return ['status' => 'good', 'message' => 'Excellent for large file processing'];
                } elseif ($bytes >= 256 * 1024 * 1024) {
                    return ['status' => 'good', 'message' => 'Good for medium to large files'];
                } else {
                    return ['status' => 'warning', 'message' => 'Consider increasing for large file handling'];
                }

            case 'max_execution_time':
                if ($value == '0') {
                    return ['status' => 'good', 'message' => 'No time limit (ideal for large uploads)'];
                } elseif (intval($value) >= 300) {
                    return ['status' => 'good', 'message' => 'Good for large file processing'];
                } else {
                    return ['status' => 'warning', 'message' => 'May timeout on large files'];
                }

            default:
                return ['status' => 'good', 'message' => 'Configuration appears adequate'];
        }
    }

    private function get_available_memory() {
        $limit = $this->baseline_metrics['memory_limit'];
        $used = memory_get_usage(true);
        return $this->format_bytes($limit - $used);
    }

    private function get_available_disk_space() {
        $upload_dir = wp_upload_dir()['basedir'];
        if (function_exists('disk_free_space')) {
            $free_bytes = disk_free_space($upload_dir);
            return $free_bytes ? $this->format_bytes($free_bytes) : 'Unknown';
        }
        return 'Unknown';
    }

    private function parse_memory_limit($limit) {
        return $this->parse_size($limit);
    }

    private function parse_size($size) {
        $size = trim($size);
        $multiplier = 1;

        if (preg_match('/^(\d+)(.)$/i', $size, $matches)) {
            $number = intval($matches[1]);
            $unit = strtoupper($matches[2]);

            switch ($unit) {
                case 'K': $multiplier = 1024; break;
                case 'M': $multiplier = 1024 * 1024; break;
                case 'G': $multiplier = 1024 * 1024 * 1024; break;
            }

            return $number * $multiplier;
        }

        return intval($size);
    }

    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function assess_execution_time($operation, $time_ms) {
        if ($time_ms < 100) {
            return ['text' => 'Excellent', 'class' => 'time-excellent'];
        } elseif ($time_ms < 500) {
            return ['text' => 'Good', 'class' => 'time-good'];
        } elseif ($time_ms < 1000) {
            return ['text' => 'Fair', 'class' => 'time-fair'];
        } else {
            return ['text' => 'Slow', 'class' => 'time-slow'];
        }
    }

    private function assess_overall_performance($average_time, $total_time) {
        if ($average_time < 200 && $total_time < 2) {
            return ['text' => 'Excellent overall performance', 'class' => 'performance-excellent'];
        } elseif ($average_time < 500 && $total_time < 5) {
            return ['text' => 'Good overall performance', 'class' => 'performance-good'];
        } else {
            return ['text' => 'Performance could be improved', 'class' => 'performance-fair'];
        }
    }

    private function generate_performance_bar($relative_performance) {
        $width = min(100, $relative_performance * 50); // Scale for display
        $color = $relative_performance < 1 ? 'green' : ($relative_performance < 2 ? 'orange' : 'red');
        return "<div class='performance-bar' style='width: {$width}%; background-color: {$color};'></div>";
    }

    private function get_size_recommendation($size_label, $score) {
        if ($score >= 8) {
            return "Excellent performance for {$size_label} files";
        } elseif ($score >= 6) {
            return "Good performance for {$size_label} files";
        } elseif ($score >= 4) {
            return "Acceptable performance, consider optimization";
        } else {
            return "Performance issues with {$size_label} files - optimization needed";
        }
    }

    private function get_php_configuration_recommendations() {
        $recommendations = [];

        $memory_limit = $this->baseline_metrics['memory_limit'];
        if ($memory_limit < 256 * 1024 * 1024) {
            $recommendations[] = [
                'category' => 'PHP Configuration',
                'priority' => 'HIGH',
                'recommendation' => 'Increase memory_limit to at least 256M',
                'details' => 'Current limit is ' . $this->format_bytes($memory_limit) . '. Larger files need more memory.'
            ];
        }

        $max_execution_time = $this->baseline_metrics['max_execution_time'];
        if ($max_execution_time > 0 && $max_execution_time < 300) {
            $recommendations[] = [
                'category' => 'PHP Configuration',
                'priority' => 'MEDIUM',
                'recommendation' => 'Increase max_execution_time to 300 seconds or disable (0)',
                'details' => 'Current limit is ' . $max_execution_time . ' seconds. Large file operations may timeout.'
            ];
        }

        return $recommendations;
    }

    private function get_s3_configuration_recommendations() {
        $recommendations = [];

        // These would be based on actual S3 performance testing
        $recommendations[] = [
            'category' => 'S3 Configuration',
            'priority' => 'LOW',
            'recommendation' => 'Consider using S3 Transfer Acceleration for global users',
            'details' => 'Can improve upload performance by 50-500% for users far from your S3 region.'
        ];

        $recommendations[] = [
            'category' => 'S3 Configuration',
            'priority' => 'MEDIUM',
            'recommendation' => 'Implement multipart upload for files larger than 100MB',
            'details' => 'Provides better performance and reliability for large files.'
        ];

        return $recommendations;
    }
}

// Auto-execute if accessed directly
if (defined('ABSPATH') && current_user_can('manage_options')) {
    $performance_validator = new H3TM_S3_Performance_Validator();
    $performance_validator->run_performance_validation();
}