<?php
/**
 * Master Test Runner for S3 Configuration Validation
 *
 * Orchestrates all S3 configuration tests to provide a comprehensive
 * analysis of the "all credentials missing" issue in AJAX contexts.
 *
 * @package H3_Tour_Management
 * @subpackage Tests
 */

if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/h3-tour-management.php';
}

// Include all test classes
require_once __DIR__ . '/comprehensive-s3-context-validator.php';
require_once __DIR__ . '/s3-edge-case-validator.php';
require_once __DIR__ . '/s3-debug-utilities.php';

class H3TM_S3_Master_Test_Runner {

    private $test_results = [];
    private $execution_log = [];
    private $start_time;

    public function __construct() {
        $this->start_time = microtime(true);
        error_log('H3TM S3 Master Test Runner: Initializing comprehensive S3 testing suite');
    }

    /**
     * Run all comprehensive S3 tests
     */
    public function run_all_tests() {
        $this->log_execution('Starting comprehensive S3 testing suite');

        // Phase 1: Quick Diagnostic
        $this->test_results['quick_diagnostic'] = $this->run_quick_diagnostic();

        // Phase 2: Comprehensive Context Validation
        $this->test_results['context_validation'] = $this->run_context_validation();

        // Phase 3: Edge Case Testing
        $this->test_results['edge_case_testing'] = $this->run_edge_case_testing();

        // Phase 4: Configuration Path Tracing
        $this->test_results['configuration_tracing'] = $this->run_configuration_tracing();

        // Phase 5: Context Difference Analysis
        $this->test_results['context_analysis'] = $this->run_context_analysis();

        // Phase 6: Recovery Testing
        $this->test_results['recovery_testing'] = $this->run_recovery_testing();

        // Generate Master Report
        $master_report = $this->generate_master_report();

        $this->log_execution('Comprehensive S3 testing suite completed');
        return $master_report;
    }

    /**
     * Phase 1: Quick Diagnostic
     */
    private function run_quick_diagnostic() {
        $this->log_execution('Phase 1: Running quick diagnostic');

        try {
            $debug_utilities = new H3TM_S3_Debug_Utilities(true);
            $results = $debug_utilities->quick_configuration_test();

            return [
                'phase' => 'quick_diagnostic',
                'success' => true,
                'results' => $results,
                'summary' => $this->summarize_quick_diagnostic($results)
            ];
        } catch (Exception $e) {
            return [
                'phase' => 'quick_diagnostic',
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => 'Quick diagnostic failed to execute'
            ];
        }
    }

    /**
     * Phase 2: Comprehensive Context Validation
     */
    private function run_context_validation() {
        $this->log_execution('Phase 2: Running comprehensive context validation');

        try {
            $context_validator = new H3TM_S3_Context_Validator();
            $results = $context_validator->validate_all_contexts();

            return [
                'phase' => 'context_validation',
                'success' => true,
                'results' => $results,
                'summary' => $this->summarize_context_validation($results)
            ];
        } catch (Exception $e) {
            return [
                'phase' => 'context_validation',
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => 'Context validation failed to execute'
            ];
        }
    }

    /**
     * Phase 3: Edge Case Testing
     */
    private function run_edge_case_testing() {
        $this->log_execution('Phase 3: Running edge case testing');

        try {
            $edge_case_validator = new H3TM_S3_Edge_Case_Validator();
            $results = $edge_case_validator->validate_edge_cases();

            return [
                'phase' => 'edge_case_testing',
                'success' => true,
                'results' => $results,
                'summary' => $this->summarize_edge_case_testing($results)
            ];
        } catch (Exception $e) {
            return [
                'phase' => 'edge_case_testing',
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => 'Edge case testing failed to execute'
            ];
        }
    }

    /**
     * Phase 4: Configuration Path Tracing
     */
    private function run_configuration_tracing() {
        $this->log_execution('Phase 4: Running configuration path tracing');

        try {
            $debug_utilities = new H3TM_S3_Debug_Utilities(true);
            $results = $debug_utilities->trace_configuration_loading_path();

            return [
                'phase' => 'configuration_tracing',
                'success' => true,
                'results' => $results,
                'summary' => $this->summarize_configuration_tracing($results)
            ];
        } catch (Exception $e) {
            return [
                'phase' => 'configuration_tracing',
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => 'Configuration tracing failed to execute'
            ];
        }
    }

    /**
     * Phase 5: Context Analysis
     */
    private function run_context_analysis() {
        $this->log_execution('Phase 5: Running context difference analysis');

        try {
            $debug_utilities = new H3TM_S3_Debug_Utilities(true);
            $results = $debug_utilities->analyze_context_differences();

            return [
                'phase' => 'context_analysis',
                'success' => true,
                'results' => $results,
                'summary' => $this->summarize_context_analysis($results)
            ];
        } catch (Exception $e) {
            return [
                'phase' => 'context_analysis',
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => 'Context analysis failed to execute'
            ];
        }
    }

    /**
     * Phase 6: Recovery Testing
     */
    private function run_recovery_testing() {
        $this->log_execution('Phase 6: Running recovery testing');

        try {
            $debug_utilities = new H3TM_S3_Debug_Utilities(true);
            $results = $debug_utilities->test_configuration_recovery_methods();

            return [
                'phase' => 'recovery_testing',
                'success' => true,
                'results' => $results,
                'summary' => $this->summarize_recovery_testing($results)
            ];
        } catch (Exception $e) {
            return [
                'phase' => 'recovery_testing',
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => 'Recovery testing failed to execute'
            ];
        }
    }

    /**
     * Generate comprehensive master report
     */
    private function generate_master_report() {
        $execution_time = microtime(true) - $this->start_time;

        $master_report = [
            'test_suite_info' => [
                'name' => 'H3TM S3 Configuration Comprehensive Test Suite',
                'version' => '1.0.0',
                'executed_at' => current_time('mysql'),
                'execution_time_seconds' => $execution_time,
                'total_phases' => count($this->test_results)
            ],
            'executive_summary' => $this->generate_executive_summary(),
            'phase_results' => $this->test_results,
            'critical_findings' => $this->extract_critical_findings(),
            'root_cause_analysis' => $this->perform_comprehensive_root_cause_analysis(),
            'recommended_actions' => $this->generate_comprehensive_recommendations(),
            'technical_details' => [
                'execution_log' => $this->execution_log,
                'test_methodology' => $this->describe_test_methodology(),
                'environment_info' => $this->capture_environment_info()
            ]
        ];

        // Log final summary
        $this->log_execution('Master report generated - Root cause: ' . ($master_report['root_cause_analysis']['primary_cause'] ?? 'Unknown'));

        return $master_report;
    }

    /**
     * Summary Methods for Each Phase
     */

    private function summarize_quick_diagnostic($results) {
        $diagnosis = $results['quick_diagnosis'] ?? [];
        $status = $diagnosis['overall_status'] ?? 'unknown';
        $critical_count = count($diagnosis['critical_issues'] ?? []);

        return [
            'status' => $status,
            'critical_issues' => $critical_count,
            'assessment' => $status === 'healthy' ? 'Configuration appears healthy' :
                          ($critical_count > 0 ? "Found $critical_count critical issues" : 'Status unknown')
        ];
    }

    private function summarize_context_validation($results) {
        $findings = $results['critical_findings'] ?? [];
        $root_cause = $results['root_cause_analysis']['probable_root_cause'] ?? 'unknown';

        return [
            'critical_findings' => count($findings),
            'probable_root_cause' => $root_cause,
            'assessment' => empty($findings) ? 'No critical context differences found' :
                           'Context-dependent configuration issues detected'
        ];
    }

    private function summarize_edge_case_testing($results) {
        $critical_cases = $results['critical_edge_cases'] ?? [];
        $patterns = $results['failure_patterns'] ?? [];

        return [
            'critical_edge_cases' => count($critical_cases),
            'failure_patterns' => count($patterns),
            'assessment' => empty($critical_cases) ? 'No critical edge cases detected' :
                           'Critical edge cases found that may explain the issue'
        ];
    }

    private function summarize_configuration_tracing($results) {
        $loading_steps = $results['loading_steps'] ?? [];
        $successful_steps = 0;

        foreach ($loading_steps as $step) {
            if (isset($step['success']) && $step['success']) {
                $successful_steps++;
            }
        }

        return [
            'total_steps' => count($loading_steps),
            'successful_steps' => $successful_steps,
            'assessment' => $successful_steps === count($loading_steps) ?
                           'All configuration loading steps successful' :
                           'Some configuration loading steps failed'
        ];
    }

    private function summarize_context_analysis($results) {
        $critical_findings = $results['critical_findings'] ?? [];
        $indicators = $results['root_cause_indicators'] ?? [];

        return [
            'critical_findings' => count($critical_findings),
            'root_cause_indicators' => count($indicators),
            'assessment' => empty($critical_findings) ? 'No critical context differences' :
                           'Significant differences found between admin and AJAX contexts'
        ];
    }

    private function summarize_recovery_testing($results) {
        $recovery_methods = $results['recovery_methods'] ?? [];
        $effectiveness = $results['effectiveness_analysis'] ?? [];

        $effective_methods = 0;
        foreach ($effectiveness as $method => $result) {
            if (isset($result['effective']) && $result['effective']) {
                $effective_methods++;
            }
        }

        return [
            'methods_tested' => count($recovery_methods),
            'effective_methods' => $effective_methods,
            'assessment' => $effective_methods > 0 ?
                           "$effective_methods recovery methods are effective" :
                           'No effective recovery methods found'
        ];
    }

    /**
     * Executive Summary Generation
     */
    private function generate_executive_summary() {
        $successful_phases = 0;
        $total_critical_findings = 0;

        foreach ($this->test_results as $phase => $result) {
            if ($result['success']) {
                $successful_phases++;
            }

            // Count critical findings from each phase
            if (isset($result['results']['critical_findings'])) {
                $total_critical_findings += count($result['results']['critical_findings']);
            }
            if (isset($result['results']['critical_edge_cases'])) {
                $total_critical_findings += count($result['results']['critical_edge_cases']);
            }
        }

        $issue_confirmed = $this->is_ajax_issue_confirmed();
        $root_cause_identified = $this->is_root_cause_identified();

        return [
            'test_execution' => [
                'total_phases' => count($this->test_results),
                'successful_phases' => $successful_phases,
                'completion_rate' => count($this->test_results) > 0 ?
                    ($successful_phases / count($this->test_results)) * 100 : 0
            ],
            'issue_analysis' => [
                'ajax_issue_confirmed' => $issue_confirmed,
                'root_cause_identified' => $root_cause_identified,
                'total_critical_findings' => $total_critical_findings
            ],
            'overall_assessment' => $this->generate_overall_assessment($issue_confirmed, $root_cause_identified, $total_critical_findings),
            'confidence_level' => $this->calculate_confidence_level($successful_phases, $total_critical_findings)
        ];
    }

    /**
     * Extract Critical Findings Across All Phases
     */
    private function extract_critical_findings() {
        $all_critical_findings = [];

        foreach ($this->test_results as $phase => $result) {
            if ($result['success']) {
                // Extract from different result structures
                if (isset($result['results']['critical_findings'])) {
                    foreach ($result['results']['critical_findings'] as $finding) {
                        $finding['source_phase'] = $phase;
                        $all_critical_findings[] = $finding;
                    }
                }

                if (isset($result['results']['critical_edge_cases'])) {
                    foreach ($result['results']['critical_edge_cases'] as $finding) {
                        $finding['source_phase'] = $phase;
                        $all_critical_findings[] = $finding;
                    }
                }

                // Extract from context analysis
                if ($phase === 'context_analysis' && isset($result['results']['critical_findings'])) {
                    foreach ($result['results']['critical_findings'] as $finding) {
                        $finding['source_phase'] = $phase;
                        $all_critical_findings[] = $finding;
                    }
                }
            }
        }

        return $all_critical_findings;
    }

    /**
     * Comprehensive Root Cause Analysis
     */
    private function perform_comprehensive_root_cause_analysis() {
        $root_cause_analysis = [
            'methodology' => 'Cross-phase pattern analysis with confidence scoring',
            'evidence_collected' => [],
            'patterns_identified' => [],
            'primary_cause' => null,
            'contributing_factors' => [],
            'confidence_score' => 0
        ];

        // Collect evidence from all phases
        $root_cause_analysis['evidence_collected'] = $this->collect_root_cause_evidence();

        // Identify patterns across phases
        $root_cause_analysis['patterns_identified'] = $this->identify_cross_phase_patterns();

        // Determine primary cause
        $root_cause_analysis['primary_cause'] = $this->determine_primary_cause($root_cause_analysis['patterns_identified']);

        // Identify contributing factors
        $root_cause_analysis['contributing_factors'] = $this->identify_contributing_factors();

        // Calculate confidence score
        $root_cause_analysis['confidence_score'] = $this->calculate_root_cause_confidence($root_cause_analysis);

        return $root_cause_analysis;
    }

    private function collect_root_cause_evidence() {
        $evidence = [];

        // From quick diagnostic
        if (isset($this->test_results['quick_diagnostic']['results']['quick_diagnosis'])) {
            $diagnosis = $this->test_results['quick_diagnostic']['results']['quick_diagnosis'];
            if ($diagnosis['overall_status'] !== 'healthy') {
                $evidence[] = [
                    'source' => 'quick_diagnostic',
                    'type' => 'configuration_health',
                    'finding' => 'Configuration reported as unhealthy',
                    'details' => $diagnosis
                ];
            }
        }

        // From context validation
        if (isset($this->test_results['context_validation']['results']['root_cause_analysis'])) {
            $context_analysis = $this->test_results['context_validation']['results']['root_cause_analysis'];
            if (isset($context_analysis['probable_root_cause']) && $context_analysis['probable_root_cause'] !== null) {
                $evidence[] = [
                    'source' => 'context_validation',
                    'type' => 'context_dependency',
                    'finding' => 'Context-dependent configuration issue identified',
                    'details' => $context_analysis
                ];
            }
        }

        // From edge case testing
        if (isset($this->test_results['edge_case_testing']['results']['failure_patterns'])) {
            $patterns = $this->test_results['edge_case_testing']['results']['failure_patterns'];
            foreach ($patterns as $pattern) {
                $evidence[] = [
                    'source' => 'edge_case_testing',
                    'type' => 'failure_pattern',
                    'finding' => $pattern['description'],
                    'details' => $pattern
                ];
            }
        }

        // From context analysis
        if (isset($this->test_results['context_analysis']['results']['root_cause_indicators'])) {
            $indicators = $this->test_results['context_analysis']['results']['root_cause_indicators'];
            foreach ($indicators as $indicator) {
                $evidence[] = [
                    'source' => 'context_analysis',
                    'type' => 'root_cause_indicator',
                    'finding' => $indicator['description'],
                    'details' => $indicator
                ];
            }
        }

        return $evidence;
    }

    private function identify_cross_phase_patterns() {
        $patterns = [];

        // Pattern 1: AJAX context failures
        $ajax_failures = 0;
        foreach ($this->test_results as $phase => $result) {
            if (isset($result['results']) && $this->contains_ajax_failure_evidence($result['results'])) {
                $ajax_failures++;
            }
        }

        if ($ajax_failures >= 2) {
            $patterns[] = [
                'pattern' => 'ajax_context_failures',
                'frequency' => $ajax_failures,
                'description' => 'Multiple phases detected AJAX context configuration failures',
                'confidence' => min(($ajax_failures / count($this->test_results)) * 100, 100)
            ];
        }

        // Pattern 2: Configuration loading issues
        $loading_issues = 0;
        foreach ($this->test_results as $phase => $result) {
            if (isset($result['results']) && $this->contains_loading_failure_evidence($result['results'])) {
                $loading_issues++;
            }
        }

        if ($loading_issues >= 2) {
            $patterns[] = [
                'pattern' => 'configuration_loading_failures',
                'frequency' => $loading_issues,
                'description' => 'Multiple phases detected configuration loading issues',
                'confidence' => min(($loading_issues / count($this->test_results)) * 100, 100)
            ];
        }

        // Pattern 3: Context-dependent behavior
        $context_issues = 0;
        foreach ($this->test_results as $phase => $result) {
            if (isset($result['results']) && $this->contains_context_dependency_evidence($result['results'])) {
                $context_issues++;
            }
        }

        if ($context_issues >= 2) {
            $patterns[] = [
                'pattern' => 'context_dependent_behavior',
                'frequency' => $context_issues,
                'description' => 'Multiple phases detected context-dependent configuration behavior',
                'confidence' => min(($context_issues / count($this->test_results)) * 100, 100)
            ];
        }

        return $patterns;
    }

    private function determine_primary_cause($patterns) {
        if (empty($patterns)) {
            return 'insufficient_evidence';
        }

        // Sort patterns by confidence score
        usort($patterns, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });

        $highest_confidence_pattern = $patterns[0];

        // Map pattern to primary cause
        switch ($highest_confidence_pattern['pattern']) {
            case 'ajax_context_failures':
                return 'ajax_context_configuration_failure';
            case 'configuration_loading_failures':
                return 'configuration_loading_mechanism_failure';
            case 'context_dependent_behavior':
                return 'context_dependent_configuration_access';
            default:
                return 'unknown_configuration_issue';
        }
    }

    private function identify_contributing_factors() {
        $factors = [];

        // Check for cache issues
        if ($this->evidence_suggests_cache_issues()) {
            $factors[] = [
                'factor' => 'cache_interference',
                'description' => 'Configuration caching may be interfering with fresh data access'
            ];
        }

        // Check for timing issues
        if ($this->evidence_suggests_timing_issues()) {
            $factors[] = [
                'factor' => 'wordpress_initialization_timing',
                'description' => 'WordPress initialization timing may affect configuration availability'
            ];
        }

        // Check for class instantiation issues
        if ($this->evidence_suggests_instantiation_issues()) {
            $factors[] = [
                'factor' => 'class_instantiation_context_dependency',
                'description' => 'Class instantiation behaves differently in different WordPress contexts'
            ];
        }

        return $factors;
    }

    private function calculate_root_cause_confidence($analysis) {
        $confidence = 0;
        $evidence_count = count($analysis['evidence_collected']);
        $pattern_count = count($analysis['patterns_identified']);

        // Base confidence from evidence
        $confidence += min($evidence_count * 10, 40);

        // Confidence from patterns
        $confidence += min($pattern_count * 15, 45);

        // Bonus for consistent patterns
        if ($pattern_count > 1) {
            $confidence += 15;
        }

        return min($confidence, 100);
    }

    /**
     * Generate Comprehensive Recommendations
     */
    private function generate_comprehensive_recommendations() {
        $recommendations = [
            'immediate_actions' => [],
            'short_term_fixes' => [],
            'long_term_improvements' => [],
            'monitoring_recommendations' => []
        ];

        $root_cause = $this->perform_comprehensive_root_cause_analysis()['primary_cause'];

        switch ($root_cause) {
            case 'ajax_context_configuration_failure':
                $recommendations['immediate_actions'][] = [
                    'priority' => 'critical',
                    'action' => 'Fix AJAX context configuration loading',
                    'details' => 'Ensure S3 configuration is properly loaded during AJAX requests',
                    'implementation' => 'Check configuration manager instantiation in AJAX handlers'
                ];
                break;

            case 'configuration_loading_mechanism_failure':
                $recommendations['immediate_actions'][] = [
                    'priority' => 'critical',
                    'action' => 'Investigate configuration loading mechanism',
                    'details' => 'Configuration loading process has fundamental issues',
                    'implementation' => 'Debug configuration manager and integration class loading'
                ];
                break;

            case 'context_dependent_configuration_access':
                $recommendations['immediate_actions'][] = [
                    'priority' => 'high',
                    'action' => 'Implement context-agnostic configuration loading',
                    'details' => 'Make configuration access work consistently across WordPress contexts',
                    'implementation' => 'Review and fix context-dependent code paths'
                ];
                break;
        }

        // Add general recommendations
        $this->add_general_recommendations($recommendations);

        return $recommendations;
    }

    private function add_general_recommendations(&$recommendations) {
        $recommendations['short_term_fixes'][] = [
            'priority' => 'high',
            'action' => 'Implement configuration caching strategy',
            'details' => 'Ensure configuration cache is properly invalidated and refreshed'
        ];

        $recommendations['long_term_improvements'][] = [
            'priority' => 'medium',
            'action' => 'Implement comprehensive configuration validation',
            'details' => 'Add validation checks at multiple points in the configuration loading process'
        ];

        $recommendations['monitoring_recommendations'][] = [
            'priority' => 'low',
            'action' => 'Set up configuration monitoring',
            'details' => 'Monitor configuration loading success rates across different contexts'
        ];
    }

    /**
     * Helper Methods
     */

    private function log_execution($message) {
        $this->execution_log[] = [
            'timestamp' => microtime(true),
            'elapsed' => microtime(true) - $this->start_time,
            'message' => $message
        ];

        error_log("H3TM S3 Master Test Runner: $message");
    }

    private function is_ajax_issue_confirmed() {
        // Check if multiple phases confirm AJAX-specific issues
        $ajax_confirmations = 0;

        foreach ($this->test_results as $phase => $result) {
            if (isset($result['results']) && $this->contains_ajax_failure_evidence($result['results'])) {
                $ajax_confirmations++;
            }
        }

        return $ajax_confirmations >= 2;
    }

    private function is_root_cause_identified() {
        $root_cause_analysis = $this->perform_comprehensive_root_cause_analysis();
        return isset($root_cause_analysis['primary_cause']) &&
               $root_cause_analysis['primary_cause'] !== 'insufficient_evidence' &&
               $root_cause_analysis['confidence_score'] >= 70;
    }

    private function generate_overall_assessment($issue_confirmed, $root_cause_identified, $critical_findings) {
        if ($issue_confirmed && $root_cause_identified) {
            return "CRITICAL: AJAX configuration issue confirmed with high confidence. Root cause identified with $critical_findings critical findings.";
        } elseif ($issue_confirmed) {
            return "WARNING: AJAX configuration issue confirmed but root cause needs further investigation.";
        } elseif ($critical_findings > 0) {
            return "ATTENTION: $critical_findings critical findings detected but AJAX-specific issue not confirmed.";
        } else {
            return "INFO: Testing completed but no critical issues definitively identified.";
        }
    }

    private function calculate_confidence_level($successful_phases, $critical_findings) {
        $completion_confidence = ($successful_phases / count($this->test_results)) * 50;
        $finding_confidence = min($critical_findings * 10, 50);
        return min($completion_confidence + $finding_confidence, 100);
    }

    // Evidence detection helper methods
    private function contains_ajax_failure_evidence($results) {
        // Check for AJAX-specific failure indicators
        if (isset($results['diagnostic_summary']) &&
            strpos(strtolower($results['diagnostic_summary']), 'ajax') !== false &&
            strpos(strtolower($results['diagnostic_summary']), 'fail') !== false) {
            return true;
        }

        if (isset($results['root_cause_analysis']['probable_root_cause']) &&
            strpos($results['root_cause_analysis']['probable_root_cause'], 'ajax') !== false) {
            return true;
        }

        return false;
    }

    private function contains_loading_failure_evidence($results) {
        if (isset($results['critical_findings'])) {
            foreach ($results['critical_findings'] as $finding) {
                if (isset($finding['type']) &&
                    (strpos($finding['type'], 'loading') !== false ||
                     strpos($finding['type'], 'instantiation') !== false)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function contains_context_dependency_evidence($results) {
        if (isset($results['root_cause_indicators'])) {
            foreach ($results['root_cause_indicators'] as $indicator) {
                if (isset($indicator['type']) &&
                    strpos($indicator['type'], 'context_dependency') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function evidence_suggests_cache_issues() {
        foreach ($this->test_results as $phase => $result) {
            if (isset($result['results']['failure_patterns'])) {
                foreach ($result['results']['failure_patterns'] as $pattern) {
                    if (isset($pattern['type']) && strpos($pattern['type'], 'cache') !== false) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function evidence_suggests_timing_issues() {
        foreach ($this->test_results as $phase => $result) {
            if (isset($result['results']['failure_patterns'])) {
                foreach ($result['results']['failure_patterns'] as $pattern) {
                    if (isset($pattern['type']) && strpos($pattern['type'], 'timing') !== false) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function evidence_suggests_instantiation_issues() {
        foreach ($this->test_results as $phase => $result) {
            if (isset($result['results']['critical_findings'])) {
                foreach ($result['results']['critical_findings'] as $finding) {
                    if (isset($finding['type']) && strpos($finding['type'], 'instantiation') !== false) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function describe_test_methodology() {
        return [
            'approach' => 'Multi-phase comprehensive testing with cross-validation',
            'phases' => [
                'Phase 1: Quick Diagnostic - Rapid initial assessment',
                'Phase 2: Context Validation - Deep context comparison',
                'Phase 3: Edge Case Testing - Failure scenario analysis',
                'Phase 4: Configuration Tracing - Step-by-step path analysis',
                'Phase 5: Context Analysis - Detailed difference identification',
                'Phase 6: Recovery Testing - Solution validation'
            ],
            'validation_strategy' => 'Multiple independent test approaches for cross-validation',
            'confidence_methodology' => 'Evidence-based confidence scoring with pattern analysis'
        ];
    }

    private function capture_environment_info() {
        return [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => defined('H3TM_VERSION') ? H3TM_VERSION : 'unknown',
            'test_context' => [
                'is_admin' => is_admin(),
                'doing_ajax' => defined('DOING_AJAX') && DOING_AJAX,
                'user_id' => get_current_user_id(),
                'can_manage_options' => current_user_can('manage_options')
            ],
            'memory_usage' => [
                'current' => memory_get_usage(),
                'peak' => memory_get_peak_usage(),
                'limit' => ini_get('memory_limit')
            ]
        ];
    }

    /**
     * Export master test results
     */
    public function export_master_results($file_path = null) {
        if ($file_path === null) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/h3tm-s3-master-test-results-' . date('Y-m-d-H-i-s') . '.json';
        }

        $master_report = $this->run_all_tests();
        file_put_contents($file_path, json_encode($master_report, JSON_PRETTY_PRINT));

        return $file_path;
    }
}

// Allow direct execution for testing
if (defined('WP_CLI') || (defined('ABSPATH') && !defined('DOING_AJAX') && current_user_can('manage_options'))) {

    function run_master_s3_test_suite() {
        $master_runner = new H3TM_S3_Master_Test_Runner();
        $results = $master_runner->run_all_tests();

        if (defined('WP_CLI')) {
            WP_CLI::success('Master S3 Test Suite completed');
            WP_CLI::log('Executive Summary: ' . $results['executive_summary']['overall_assessment']);
            WP_CLI::log('Root Cause: ' . ($results['root_cause_analysis']['primary_cause'] ?? 'Unknown'));
            WP_CLI::log('Confidence: ' . ($results['root_cause_analysis']['confidence_score'] ?? 0) . '%');
        } else {
            echo '<h1>H3TM S3 Configuration Master Test Results</h1>';
            echo '<h2>Executive Summary</h2>';
            echo '<p><strong>' . $results['executive_summary']['overall_assessment'] . '</strong></p>';

            if (isset($results['root_cause_analysis']['primary_cause'])) {
                echo '<h2>Root Cause Analysis</h2>';
                echo '<p><strong>Primary Cause:</strong> ' . $results['root_cause_analysis']['primary_cause'] . '</p>';
                echo '<p><strong>Confidence:</strong> ' . $results['root_cause_analysis']['confidence_score'] . '%</p>';
            }

            if (!empty($results['recommended_actions']['immediate_actions'])) {
                echo '<h2>Immediate Actions Required</h2>';
                echo '<ul>';
                foreach ($results['recommended_actions']['immediate_actions'] as $action) {
                    echo '<li><strong>' . $action['action'] . '</strong> - ' . $action['details'] . '</li>';
                }
                echo '</ul>';
            }

            echo '<h2>Full Report</h2>';
            echo '<details><summary>Click to expand full technical report</summary>';
            echo '<pre>' . json_encode($results, JSON_PRETTY_PRINT) . '</pre>';
            echo '</details>';
        }

        return $results;
    }

    // Auto-run if accessed directly
    if (isset($_GET['run_master_s3_tests'])) {
        run_master_s3_test_suite();
    }
}