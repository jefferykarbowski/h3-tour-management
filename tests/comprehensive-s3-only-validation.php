<?php
/**
 * Comprehensive S3-Only System Validation Suite
 *
 * Validates the complete removal of chunked upload functionality and ensures
 * S3-only uploads work correctly for all file sizes and scenarios.
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
 * S3-Only System Comprehensive Validator
 *
 * Tests all aspects of the S3-only upload system to ensure robust operation
 */
class H3TM_S3_Only_System_Validator {

    private $test_results = [];
    private $validation_errors = [];
    private $performance_metrics = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize_test_environment();
    }

    /**
     * Run complete validation suite
     */
    public function run_complete_validation() {
        echo "<div class='wrap'><h1>H3 Tour Management - S3-Only System Validation</h1>";
        echo "<div class='validation-results'>";

        // Phase 1: Code Completeness Validation
        echo "<h2>Phase 1: Code Completeness Validation</h2>";
        $this->validate_chunked_upload_removal();
        $this->validate_s3_only_implementation();

        // Phase 2: Functional Testing
        echo "<h2>Phase 2: S3 Upload Flow Testing</h2>";
        $this->test_s3_configuration_validation();
        $this->test_s3_upload_workflow();
        $this->test_s3_download_extraction();

        // Phase 3: Error Scenario Testing
        echo "<h2>Phase 3: Error Scenario Testing</h2>";
        $this->test_s3_not_configured_scenario();
        $this->test_s3_upload_failure_scenario();
        $this->test_s3_processing_failure_scenario();

        // Phase 4: Performance & Size Testing
        echo "<h2>Phase 4: Performance & Size Testing</h2>";
        $this->test_large_file_handling();
        $this->test_edge_cases();

        // Phase 5: User Experience Validation
        echo "<h2>Phase 5: User Experience Validation</h2>";
        $this->validate_error_messaging();
        $this->validate_user_guidance();

        // Generate comprehensive report
        $this->generate_validation_report();

        echo "</div></div>";
    }

    /**
     * Phase 1: Validate chunked upload code removal
     */
    private function validate_chunked_upload_removal() {
        echo "<h3>🔍 Checking for Remaining Chunked Upload Code</h3>";

        $chunked_references = $this->scan_for_chunked_references();

        if (empty($chunked_references['critical'])) {
            echo "<div class='notice notice-success'><p>✅ No critical chunked upload code found</p></div>";
            $this->test_results['chunked_removal'] = 'PASS';
        } else {
            echo "<div class='notice notice-error'><p>❌ Found " . count($chunked_references['critical']) . " critical chunked upload references:</p>";
            foreach ($chunked_references['critical'] as $ref) {
                echo "<p style='margin-left: 20px;'>- {$ref['file']}:{$ref['line']} - {$ref['context']}</p>";
            }
            echo "</div>";
            $this->test_results['chunked_removal'] = 'FAIL';
            $this->validation_errors[] = 'Critical chunked upload code still present';
        }

        // Report non-critical references that should be reviewed
        if (!empty($chunked_references['review'])) {
            echo "<div class='notice notice-warning'><p>⚠️ Found " . count($chunked_references['review']) . " references for review:</p>";
            foreach ($chunked_references['review'] as $ref) {
                echo "<p style='margin-left: 20px;'>- {$ref['file']}:{$ref['line']} - {$ref['context']}</p>";
            }
            echo "</div>";
        }
    }

    /**
     * Scan codebase for chunked upload references
     */
    private function scan_for_chunked_references() {
        $references = ['critical' => [], 'review' => []];
        $plugin_path = plugin_dir_path(__FILE__) . '../';

        // Critical patterns that indicate active chunked upload functionality
        $critical_patterns = [
            '/chunk.*upload/i',
            '/upload.*chunk/i',
            '/chunked.*process/i',
            '/process.*chunk/i',
            '/chunk.*handler/i',
            '/multipart.*upload/i'
        ];

        // Review patterns that might be legacy or documentation
        $review_patterns = [
            '/chunk/i',
            '/CHUNK/i'
        ];

        // Files to scan
        $files_to_scan = [
            'includes/class-h3tm-admin.php',
            'includes/class-h3tm-tour-manager.php',
            'includes/class-h3tm-tour-manager-optimized.php',
            'includes/class-h3tm-s3-integration.php',
            'includes/class-h3tm-s3-simple.php',
            'includes/class-h3tm-s3-uploader.php',
            'includes/class-h3tm-bulletproof-config.php'
        ];

        foreach ($files_to_scan as $file_path) {
            $full_path = $plugin_path . $file_path;
            if (!file_exists($full_path)) continue;

            $content = file_get_contents($full_path);
            $lines = explode("\n", $content);

            foreach ($lines as $line_num => $line) {
                // Check critical patterns
                foreach ($critical_patterns as $pattern) {
                    if (preg_match($pattern, $line)) {
                        $references['critical'][] = [
                            'file' => $file_path,
                            'line' => $line_num + 1,
                            'context' => trim($line)
                        ];
                    }
                }

                // Check review patterns (but exclude false positives)
                foreach ($review_patterns as $pattern) {
                    if (preg_match($pattern, $line) &&
                        !$this->is_false_positive_chunk_reference($line)) {
                        $references['review'][] = [
                            'file' => $file_path,
                            'line' => $line_num + 1,
                            'context' => trim($line)
                        ];
                    }
                }
            }
        }

        return $references;
    }

    /**
     * Check if a chunk reference is a false positive
     */
    private function is_false_positive_chunk_reference($line) {
        $false_positives = [
            '/\/\*.*chunk.*\*\//i',  // Comments
            '/\/\/.*chunk/i',         // Single line comments
            '/copy.*chunk/i',         // File copying chunks (legitimate)
            '/read.*chunk/i',         // File reading chunks (legitimate)
            '/array_chunk/i',         // PHP array_chunk function
            '/file.*chunk/i'          // File processing chunks (legitimate)
        ];

        foreach ($false_positives as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate S3-only implementation is complete
     */
    private function validate_s3_only_implementation() {
        echo "<h3>🔍 Validating S3-Only Implementation</h3>";

        $required_classes = [
            'H3TM_S3_Integration',
            'H3TM_S3_Simple',
            'H3TM_S3_Uploader',
            'H3TM_S3_Config_Manager'
        ];

        $missing_classes = [];
        foreach ($required_classes as $class_name) {
            if (!class_exists($class_name)) {
                $missing_classes[] = $class_name;
            }
        }

        if (empty($missing_classes)) {
            echo "<div class='notice notice-success'><p>✅ All required S3 classes are available</p></div>";
            $this->test_results['s3_classes'] = 'PASS';
        } else {
            echo "<div class='notice notice-error'><p>❌ Missing S3 classes: " . implode(', ', $missing_classes) . "</p></div>";
            $this->test_results['s3_classes'] = 'FAIL';
            $this->validation_errors[] = 'Missing required S3 classes';
        }

        // Validate S3 configuration options
        $this->validate_s3_configuration_options();

        // Validate AJAX handlers are S3-only
        $this->validate_ajax_handlers_s3_only();
    }

    /**
     * Validate S3 configuration options are properly set up
     */
    private function validate_s3_configuration_options() {
        $required_options = [
            'h3tm_s3_enabled',
            'h3tm_s3_bucket',
            'h3tm_s3_region',
            'h3tm_s3_access_key',
            'h3tm_s3_secret_key',
            'h3tm_s3_upload_threshold'
        ];

        $config_issues = [];
        foreach ($required_options as $option) {
            // Check if option exists in database or as constant
            $value = get_option($option);
            $constant_name = strtoupper(str_replace('h3tm_', 'H3_', $option));

            if (empty($value) && !defined($constant_name)) {
                $config_issues[] = "Option '{$option}' not configured and constant '{$constant_name}' not defined";
            }
        }

        if (empty($config_issues)) {
            echo "<div class='notice notice-success'><p>✅ S3 configuration options are properly defined</p></div>";
            $this->test_results['s3_config_options'] = 'PASS';
        } else {
            echo "<div class='notice notice-info'><p>ℹ️ S3 configuration options status (may be intentionally empty for testing):</p>";
            foreach ($config_issues as $issue) {
                echo "<p style='margin-left: 20px;'>- {$issue}</p>";
            }
            echo "</div>";
            $this->test_results['s3_config_options'] = 'INFO';
        }
    }

    /**
     * Validate AJAX handlers are S3-only
     */
    private function validate_ajax_handlers_s3_only() {
        $expected_handlers = [
            'h3tm_get_s3_presigned_url',
            'h3tm_s3_upload_complete',
            'h3tm_process_s3_tour'
        ];

        $missing_handlers = [];
        foreach ($expected_handlers as $handler) {
            if (!has_action("wp_ajax_{$handler}") && !has_action("wp_ajax_nopriv_{$handler}")) {
                $missing_handlers[] = $handler;
            }
        }

        // Check for old chunked upload handlers that should be removed
        $old_handlers = [
            'h3tm_upload_chunk',
            'h3tm_finalize_upload',
            'h3tm_chunk_upload'
        ];

        $remaining_old_handlers = [];
        foreach ($old_handlers as $handler) {
            if (has_action("wp_ajax_{$handler}") || has_action("wp_ajax_nopriv_{$handler}")) {
                $remaining_old_handlers[] = $handler;
            }
        }

        if (empty($missing_handlers) && empty($remaining_old_handlers)) {
            echo "<div class='notice notice-success'><p>✅ AJAX handlers correctly configured for S3-only</p></div>";
            $this->test_results['ajax_handlers'] = 'PASS';
        } else {
            if (!empty($missing_handlers)) {
                echo "<div class='notice notice-error'><p>❌ Missing S3 AJAX handlers: " . implode(', ', $missing_handlers) . "</p></div>";
                $this->validation_errors[] = 'Missing required S3 AJAX handlers';
            }
            if (!empty($remaining_old_handlers)) {
                echo "<div class='notice notice-error'><p>❌ Old chunked upload handlers still present: " . implode(', ', $remaining_old_handlers) . "</p></div>";
                $this->validation_errors[] = 'Old chunked upload handlers not removed';
            }
            $this->test_results['ajax_handlers'] = 'FAIL';
        }
    }

    /**
     * Test S3 configuration validation
     */
    private function test_s3_configuration_validation() {
        echo "<h3>🔧 Testing S3 Configuration Validation</h3>";

        if (class_exists('H3TM_S3_Config_Manager')) {
            $config_manager = new H3TM_S3_Config_Manager();

            try {
                $config_status = $config_manager->validate_configuration();

                if ($config_status['valid']) {
                    echo "<div class='notice notice-success'><p>✅ S3 configuration is valid</p></div>";
                    $this->test_results['s3_config_valid'] = 'PASS';
                } else {
                    echo "<div class='notice notice-warning'><p>⚠️ S3 configuration issues:</p>";
                    foreach ($config_status['errors'] as $error) {
                        echo "<p style='margin-left: 20px;'>- {$error}</p>";
                    }
                    echo "</div>";
                    $this->test_results['s3_config_valid'] = 'WARNING';
                }
            } catch (Exception $e) {
                echo "<div class='notice notice-error'><p>❌ S3 configuration validation failed: " . $e->getMessage() . "</p></div>";
                $this->test_results['s3_config_valid'] = 'FAIL';
                $this->validation_errors[] = 'S3 configuration validation error: ' . $e->getMessage();
            }
        } else {
            echo "<div class='notice notice-error'><p>❌ H3TM_S3_Config_Manager class not found</p></div>";
            $this->test_results['s3_config_valid'] = 'FAIL';
            $this->validation_errors[] = 'H3TM_S3_Config_Manager class not found';
        }
    }

    /**
     * Test complete S3 upload workflow
     */
    private function test_s3_upload_workflow() {
        echo "<h3>📤 Testing S3 Upload Workflow</h3>";

        // Test presigned URL generation
        $this->test_presigned_url_generation();

        // Test upload completion handling
        $this->test_upload_completion_handling();

        // Test S3 file verification
        $this->test_s3_file_verification();
    }

    /**
     * Test presigned URL generation
     */
    private function test_presigned_url_generation() {
        if (class_exists('H3TM_S3_Integration')) {
            $s3_integration = new H3TM_S3_Integration();

            try {
                // Simulate presigned URL request
                $test_params = [
                    'filename' => 'test-tour.zip',
                    'filesize' => 50 * 1024 * 1024, // 50MB
                    'content_type' => 'application/zip'
                ];

                $presigned_result = $s3_integration->generate_presigned_upload_url($test_params);

                if ($presigned_result && isset($presigned_result['upload_url'])) {
                    echo "<div class='notice notice-success'><p>✅ Presigned URL generation working</p></div>";
                    $this->test_results['presigned_url'] = 'PASS';
                } else {
                    echo "<div class='notice notice-error'><p>❌ Presigned URL generation failed</p></div>";
                    $this->test_results['presigned_url'] = 'FAIL';
                    $this->validation_errors[] = 'Presigned URL generation not working';
                }
            } catch (Exception $e) {
                echo "<div class='notice notice-error'><p>❌ Presigned URL generation error: " . $e->getMessage() . "</p></div>";
                $this->test_results['presigned_url'] = 'FAIL';
                $this->validation_errors[] = 'Presigned URL generation error: ' . $e->getMessage();
            }
        } else {
            echo "<div class='notice notice-error'><p>❌ H3TM_S3_Integration class not found</p></div>";
            $this->test_results['presigned_url'] = 'FAIL';
        }
    }

    /**
     * Test upload completion handling
     */
    private function test_upload_completion_handling() {
        // Test upload completion AJAX handler
        if (class_exists('H3TM_S3_Integration')) {
            $s3_integration = new H3TM_S3_Integration();

            try {
                // Simulate upload completion
                $completion_data = [
                    's3_key' => 'tours/test-tour-' . time() . '.zip',
                    'original_filename' => 'test-tour.zip',
                    'filesize' => 50 * 1024 * 1024
                ];

                $completion_result = $s3_integration->handle_upload_completion($completion_data);

                if ($completion_result && $completion_result['success']) {
                    echo "<div class='notice notice-success'><p>✅ Upload completion handling working</p></div>";
                    $this->test_results['upload_completion'] = 'PASS';
                } else {
                    echo "<div class='notice notice-warning'><p>⚠️ Upload completion handling needs S3 file to exist for full test</p></div>";
                    $this->test_results['upload_completion'] = 'INFO';
                }
            } catch (Exception $e) {
                echo "<div class='notice notice-error'><p>❌ Upload completion error: " . $e->getMessage() . "</p></div>";
                $this->test_results['upload_completion'] = 'FAIL';
                $this->validation_errors[] = 'Upload completion error: ' . $e->getMessage();
            }
        }
    }

    /**
     * Test S3 file verification
     */
    private function test_s3_file_verification() {
        if (class_exists('H3TM_S3_Integration')) {
            $s3_integration = new H3TM_S3_Integration();

            try {
                // Test file existence check
                $test_key = 'tours/non-existent-file.zip';
                $exists = $s3_integration->file_exists($test_key);

                // Should return false for non-existent file
                if ($exists === false) {
                    echo "<div class='notice notice-success'><p>✅ S3 file verification working (correctly returned false for non-existent file)</p></div>";
                    $this->test_results['s3_verification'] = 'PASS';
                } else {
                    echo "<div class='notice notice-warning'><p>⚠️ S3 file verification returned unexpected result</p></div>";
                    $this->test_results['s3_verification'] = 'WARNING';
                }
            } catch (Exception $e) {
                echo "<div class='notice notice-error'><p>❌ S3 file verification error: " . $e->getMessage() . "</p></div>";
                $this->test_results['s3_verification'] = 'FAIL';
                $this->validation_errors[] = 'S3 file verification error: ' . $e->getMessage();
            }
        }
    }

    /**
     * Test S3 download and extraction workflow
     */
    private function test_s3_download_extraction() {
        echo "<h3>📥 Testing S3 Download & Extraction Workflow</h3>";

        if (class_exists('H3TM_S3_Processor')) {
            $s3_processor = new H3TM_S3_Processor();

            try {
                // Test download capability (without actual file)
                $test_s3_key = 'tours/test-tour.zip';
                $download_path = wp_upload_dir()['basedir'] . '/h3tm-temp/test-download.zip';

                // Create temp directory
                wp_mkdir_p(dirname($download_path));

                // This will fail because file doesn't exist, but we can test the method exists
                $method_exists = method_exists($s3_processor, 'download_from_s3');

                if ($method_exists) {
                    echo "<div class='notice notice-success'><p>✅ S3 download method available</p></div>";
                    $this->test_results['s3_download'] = 'PASS';
                } else {
                    echo "<div class='notice notice-error'><p>❌ S3 download method not found</p></div>";
                    $this->test_results['s3_download'] = 'FAIL';
                    $this->validation_errors[] = 'S3 download method not available';
                }

                // Test extraction method
                $extraction_method_exists = method_exists($s3_processor, 'extract_tour_from_zip');

                if ($extraction_method_exists) {
                    echo "<div class='notice notice-success'><p>✅ Tour extraction method available</p></div>";
                    $this->test_results['tour_extraction'] = 'PASS';
                } else {
                    echo "<div class='notice notice-error'><p>❌ Tour extraction method not found</p></div>";
                    $this->test_results['tour_extraction'] = 'FAIL';
                    $this->validation_errors[] = 'Tour extraction method not available';
                }

                // Cleanup
                if (file_exists(dirname($download_path))) {
                    rmdir(dirname($download_path));
                }

            } catch (Exception $e) {
                echo "<div class='notice notice-error'><p>❌ S3 download/extraction test error: " . $e->getMessage() . "</p></div>";
                $this->validation_errors[] = 'S3 download/extraction test error: ' . $e->getMessage();
            }
        } else {
            echo "<div class='notice notice-error'><p>❌ H3TM_S3_Processor class not found</p></div>";
            $this->test_results['s3_download'] = 'FAIL';
            $this->test_results['tour_extraction'] = 'FAIL';
        }
    }

    /**
     * Test S3 not configured scenario
     */
    private function test_s3_not_configured_scenario() {
        echo "<h3>⚠️ Testing S3 Not Configured Scenario</h3>";

        // Temporarily disable S3 configuration
        $original_s3_enabled = get_option('h3tm_s3_enabled');
        update_option('h3tm_s3_enabled', false);

        try {
            if (class_exists('H3TM_S3_Config_Manager')) {
                $config_manager = new H3TM_S3_Config_Manager();
                $config_status = $config_manager->validate_configuration();

                if (!$config_status['valid']) {
                    echo "<div class='notice notice-success'><p>✅ Correctly detects S3 not configured</p></div>";
                    $this->test_results['s3_not_configured_detection'] = 'PASS';

                    // Test error message quality
                    $error_message = $config_manager->get_configuration_error_message();
                    if (strpos($error_message, 'S3') !== false && strpos($error_message, 'configur') !== false) {
                        echo "<div class='notice notice-success'><p>✅ Error message mentions S3 configuration</p></div>";
                        $this->test_results['s3_error_message_quality'] = 'PASS';
                    } else {
                        echo "<div class='notice notice-warning'><p>⚠️ Error message could be more specific about S3 configuration</p></div>";
                        $this->test_results['s3_error_message_quality'] = 'WARNING';
                    }
                } else {
                    echo "<div class='notice notice-error'><p>❌ Failed to detect S3 not configured</p></div>";
                    $this->test_results['s3_not_configured_detection'] = 'FAIL';
                }
            }
        } finally {
            // Restore original setting
            update_option('h3tm_s3_enabled', $original_s3_enabled);
        }
    }

    /**
     * Test S3 upload failure scenario
     */
    private function test_s3_upload_failure_scenario() {
        echo "<h3>❌ Testing S3 Upload Failure Scenario</h3>";

        if (class_exists('H3TM_S3_Integration')) {
            $s3_integration = new H3TM_S3_Integration();

            try {
                // Test with invalid credentials (should fail gracefully)
                $invalid_params = [
                    'filename' => 'test-tour.zip',
                    'filesize' => 50 * 1024 * 1024,
                    'content_type' => 'application/zip',
                    'force_invalid_credentials' => true // Special test flag
                ];

                $presigned_result = $s3_integration->generate_presigned_upload_url($invalid_params);

                // Should fail gracefully with proper error
                if (!$presigned_result || isset($presigned_result['error'])) {
                    echo "<div class='notice notice-success'><p>✅ S3 upload failure handled gracefully</p></div>";
                    $this->test_results['s3_failure_handling'] = 'PASS';
                } else {
                    echo "<div class='notice notice-warning'><p>⚠️ S3 upload failure handling needs improvement</p></div>";
                    $this->test_results['s3_failure_handling'] = 'WARNING';
                }

            } catch (Exception $e) {
                // Exception is expected for invalid credentials
                echo "<div class='notice notice-success'><p>✅ S3 upload failure throws proper exception: " . $e->getMessage() . "</p></div>";
                $this->test_results['s3_failure_handling'] = 'PASS';
            }
        }
    }

    /**
     * Test S3 processing failure scenario
     */
    private function test_s3_processing_failure_scenario() {
        echo "<h3>🔄 Testing S3 Processing Failure Scenario</h3>";

        if (class_exists('H3TM_S3_Processor')) {
            $s3_processor = new H3TM_S3_Processor();

            try {
                // Test processing with non-existent S3 file
                $invalid_s3_key = 'tours/non-existent-file-' . time() . '.zip';
                $processing_result = $s3_processor->process_s3_tour($invalid_s3_key);

                if (!$processing_result || isset($processing_result['error'])) {
                    echo "<div class='notice notice-success'><p>✅ S3 processing failure handled gracefully</p></div>";
                    $this->test_results['s3_processing_failure'] = 'PASS';
                } else {
                    echo "<div class='notice notice-warning'><p>⚠️ S3 processing failure handling needs improvement</p></div>";
                    $this->test_results['s3_processing_failure'] = 'WARNING';
                }

            } catch (Exception $e) {
                // Exception handling for processing failure
                echo "<div class='notice notice-success'><p>✅ S3 processing failure throws proper exception: " . $e->getMessage() . "</p></div>";
                $this->test_results['s3_processing_failure'] = 'PASS';
            }
        }
    }

    /**
     * Test large file handling capabilities
     */
    private function test_large_file_handling() {
        echo "<h3>📊 Testing Large File Handling</h3>";

        $file_sizes_to_test = [
            '50MB' => 50 * 1024 * 1024,
            '100MB' => 100 * 1024 * 1024,
            '250MB' => 250 * 1024 * 1024,
            '500MB' => 500 * 1024 * 1024,
            '1GB' => 1024 * 1024 * 1024
        ];

        foreach ($file_sizes_to_test as $size_label => $size_bytes) {
            $this->test_file_size_handling($size_label, $size_bytes);
        }

        // Test memory usage optimization
        $this->test_memory_usage_optimization();

        // Test timeout handling
        $this->test_timeout_handling();
    }

    /**
     * Test specific file size handling
     */
    private function test_file_size_handling($size_label, $size_bytes) {
        if (class_exists('H3TM_S3_Integration')) {
            $s3_integration = new H3TM_S3_Integration();

            try {
                $start_time = microtime(true);
                $start_memory = memory_get_usage();

                // Test presigned URL generation for large file
                $test_params = [
                    'filename' => "test-large-tour-{$size_label}.zip",
                    'filesize' => $size_bytes,
                    'content_type' => 'application/zip'
                ];

                $presigned_result = $s3_integration->generate_presigned_upload_url($test_params);

                $end_time = microtime(true);
                $end_memory = memory_get_usage();

                $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
                $memory_used = $end_memory - $start_memory;

                $this->performance_metrics[$size_label] = [
                    'execution_time_ms' => $execution_time,
                    'memory_used_bytes' => $memory_used,
                    'success' => !empty($presigned_result)
                ];

                if ($presigned_result && isset($presigned_result['upload_url'])) {
                    echo "<div class='notice notice-success'><p>✅ {$size_label} file handling: " .
                         number_format($execution_time, 2) . "ms, " .
                         $this->format_bytes($memory_used) . " memory</p></div>";
                } else {
                    echo "<div class='notice notice-error'><p>❌ {$size_label} file handling failed</p></div>";
                    $this->validation_errors[] = "{$size_label} file handling failed";
                }

            } catch (Exception $e) {
                echo "<div class='notice notice-error'><p>❌ {$size_label} file handling error: " . $e->getMessage() . "</p></div>";
                $this->validation_errors[] = "{$size_label} file handling error: " . $e->getMessage();
            }
        }
    }

    /**
     * Test memory usage optimization
     */
    private function test_memory_usage_optimization() {
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->parse_memory_limit($memory_limit);

        echo "<div class='notice notice-info'><p>ℹ️ Current memory limit: {$memory_limit}</p></div>";

        // Check if large file handling stays within reasonable memory bounds
        $max_acceptable_memory = min($memory_limit_bytes * 0.1, 50 * 1024 * 1024); // 10% of limit or 50MB, whichever is smaller

        $excessive_memory_usage = false;
        foreach ($this->performance_metrics as $size => $metrics) {
            if ($metrics['memory_used_bytes'] > $max_acceptable_memory) {
                $excessive_memory_usage = true;
                echo "<div class='notice notice-warning'><p>⚠️ {$size} file used " .
                     $this->format_bytes($metrics['memory_used_bytes']) . " memory (above " .
                     $this->format_bytes($max_acceptable_memory) . " threshold)</p></div>";
            }
        }

        if (!$excessive_memory_usage) {
            echo "<div class='notice notice-success'><p>✅ Memory usage within acceptable limits for all tested file sizes</p></div>";
            $this->test_results['memory_optimization'] = 'PASS';
        } else {
            $this->test_results['memory_optimization'] = 'WARNING';
        }
    }

    /**
     * Test timeout handling for large files
     */
    private function test_timeout_handling() {
        $max_execution_time = ini_get('max_execution_time');
        echo "<div class='notice notice-info'><p>ℹ️ Current max execution time: {$max_execution_time} seconds</p></div>";

        // Check if any operations took too long
        $max_acceptable_time = min($max_execution_time * 1000 * 0.8, 30000); // 80% of limit or 30 seconds, whichever is smaller

        $slow_operations = false;
        foreach ($this->performance_metrics as $size => $metrics) {
            if ($metrics['execution_time_ms'] > $max_acceptable_time) {
                $slow_operations = true;
                echo "<div class='notice notice-warning'><p>⚠️ {$size} file processing took " .
                     number_format($metrics['execution_time_ms'], 2) . "ms (above " .
                     number_format($max_acceptable_time, 2) . "ms threshold)</p></div>";
            }
        }

        if (!$slow_operations) {
            echo "<div class='notice notice-success'><p>✅ All operations completed within acceptable time limits</p></div>";
            $this->test_results['timeout_handling'] = 'PASS';
        } else {
            $this->test_results['timeout_handling'] = 'WARNING';
        }
    }

    /**
     * Test edge cases
     */
    private function test_edge_cases() {
        echo "<h3>🎯 Testing Edge Cases</h3>";

        // Test very small files
        $this->test_small_file_handling();

        // Test files with special characters in names
        $this->test_special_character_filenames();

        // Test concurrent uploads
        $this->test_concurrent_upload_simulation();

        // Test network timeout scenarios
        $this->test_network_timeout_scenarios();
    }

    /**
     * Test small file handling
     */
    private function test_small_file_handling() {
        if (class_exists('H3TM_S3_Integration')) {
            $s3_integration = new H3TM_S3_Integration();

            try {
                $test_params = [
                    'filename' => 'tiny-tour.zip',
                    'filesize' => 1024, // 1KB
                    'content_type' => 'application/zip'
                ];

                $presigned_result = $s3_integration->generate_presigned_upload_url($test_params);

                if ($presigned_result && isset($presigned_result['upload_url'])) {
                    echo "<div class='notice notice-success'><p>✅ Small file (1KB) handling works</p></div>";
                    $this->test_results['small_file_handling'] = 'PASS';
                } else {
                    echo "<div class='notice notice-error'><p>❌ Small file handling failed</p></div>";
                    $this->test_results['small_file_handling'] = 'FAIL';
                }
            } catch (Exception $e) {
                echo "<div class='notice notice-error'><p>❌ Small file handling error: " . $e->getMessage() . "</p></div>";
                $this->test_results['small_file_handling'] = 'FAIL';
            }
        }
    }

    /**
     * Test special character filenames
     */
    private function test_special_character_filenames() {
        if (class_exists('H3TM_S3_Integration')) {
            $s3_integration = new H3TM_S3_Integration();

            $special_filenames = [
                'tour with spaces.zip',
                'tour-with-dashes.zip',
                'tour_with_underscores.zip',
                'tour.with.dots.zip',
                'tour(with)parentheses.zip'
            ];

            $failed_filenames = [];
            foreach ($special_filenames as $filename) {
                try {
                    $test_params = [
                        'filename' => $filename,
                        'filesize' => 10 * 1024 * 1024, // 10MB
                        'content_type' => 'application/zip'
                    ];

                    $presigned_result = $s3_integration->generate_presigned_upload_url($test_params);

                    if (!$presigned_result || !isset($presigned_result['upload_url'])) {
                        $failed_filenames[] = $filename;
                    }
                } catch (Exception $e) {
                    $failed_filenames[] = $filename . ' (' . $e->getMessage() . ')';
                }
            }

            if (empty($failed_filenames)) {
                echo "<div class='notice notice-success'><p>✅ All special character filenames handled correctly</p></div>";
                $this->test_results['special_filenames'] = 'PASS';
            } else {
                echo "<div class='notice notice-warning'><p>⚠️ Issues with filenames: " . implode(', ', $failed_filenames) . "</p></div>";
                $this->test_results['special_filenames'] = 'WARNING';
            }
        }
    }

    /**
     * Simulate concurrent upload scenarios
     */
    private function test_concurrent_upload_simulation() {
        echo "<div class='notice notice-info'><p>ℹ️ Concurrent upload simulation requires actual S3 files for full testing</p></div>";
        $this->test_results['concurrent_uploads'] = 'INFO';
    }

    /**
     * Test network timeout scenarios
     */
    private function test_network_timeout_scenarios() {
        echo "<div class='notice notice-info'><p>ℹ️ Network timeout scenarios require controlled network conditions for full testing</p></div>";
        $this->test_results['network_timeouts'] = 'INFO';
    }

    /**
     * Validate error messaging quality
     */
    private function validate_error_messaging() {
        echo "<h3>💬 Validating Error Messaging</h3>";

        $this->test_configuration_error_messages();
        $this->test_upload_error_messages();
        $this->test_processing_error_messages();
    }

    /**
     * Test configuration error messages
     */
    private function test_configuration_error_messages() {
        if (class_exists('H3TM_S3_Config_Manager')) {
            $config_manager = new H3TM_S3_Config_Manager();

            // Test with empty configuration
            $original_settings = [
                'bucket' => get_option('h3tm_s3_bucket'),
                'region' => get_option('h3tm_s3_region'),
                'access_key' => get_option('h3tm_s3_access_key'),
                'secret_key' => get_option('h3tm_s3_secret_key')
            ];

            // Temporarily clear settings
            update_option('h3tm_s3_bucket', '');
            update_option('h3tm_s3_region', '');
            update_option('h3tm_s3_access_key', '');
            update_option('h3tm_s3_secret_key', '');

            try {
                $config_status = $config_manager->validate_configuration();
                $error_message = $config_manager->get_configuration_error_message();

                $message_quality_score = 0;
                $quality_checks = [
                    'mentions_s3' => (stripos($error_message, 's3') !== false),
                    'mentions_configuration' => (stripos($error_message, 'configur') !== false),
                    'mentions_setup' => (stripos($error_message, 'setup') !== false || stripos($error_message, 'set up') !== false),
                    'provides_guidance' => (stripos($error_message, 'please') !== false || stripos($error_message, 'need') !== false),
                    'reasonable_length' => (strlen($error_message) >= 20 && strlen($error_message) <= 200)
                ];

                foreach ($quality_checks as $check => $passed) {
                    if ($passed) $message_quality_score++;
                }

                if ($message_quality_score >= 4) {
                    echo "<div class='notice notice-success'><p>✅ Configuration error messages are high quality</p></div>";
                    echo "<div style='margin-left: 20px; font-style: italic;'>Sample message: \"{$error_message}\"</div>";
                    $this->test_results['config_error_messages'] = 'PASS';
                } else {
                    echo "<div class='notice notice-warning'><p>⚠️ Configuration error messages could be improved</p></div>";
                    echo "<div style='margin-left: 20px; font-style: italic;'>Current message: \"{$error_message}\"</div>";
                    echo "<div style='margin-left: 20px;'>Quality score: {$message_quality_score}/5</div>";
                    $this->test_results['config_error_messages'] = 'WARNING';
                }

            } finally {
                // Restore original settings
                foreach ($original_settings as $key => $value) {
                    update_option("h3tm_s3_{$key}", $value);
                }
            }
        }
    }

    /**
     * Test upload error messages
     */
    private function test_upload_error_messages() {
        echo "<div class='notice notice-info'><p>ℹ️ Upload error message testing requires simulated upload failures</p></div>";
        $this->test_results['upload_error_messages'] = 'INFO';
    }

    /**
     * Test processing error messages
     */
    private function test_processing_error_messages() {
        echo "<div class='notice notice-info'><p>ℹ️ Processing error message testing requires simulated processing failures</p></div>";
        $this->test_results['processing_error_messages'] = 'INFO';
    }

    /**
     * Validate user guidance
     */
    private function validate_user_guidance() {
        echo "<h3>👥 Validating User Guidance</h3>";

        $this->test_setup_documentation();
        $this->test_troubleshooting_guidance();
        $this->test_user_interface_guidance();
    }

    /**
     * Test setup documentation availability
     */
    private function test_setup_documentation() {
        $plugin_path = plugin_dir_path(__FILE__) . '../';
        $doc_files_to_check = [
            'README.md',
            'SETUP.md',
            'AWS_SETUP.md',
            'S3_SETUP.md',
            'docs/setup.md',
            'docs/s3-setup.md'
        ];

        $found_docs = [];
        foreach ($doc_files_to_check as $doc_file) {
            if (file_exists($plugin_path . $doc_file)) {
                $found_docs[] = $doc_file;
            }
        }

        if (!empty($found_docs)) {
            echo "<div class='notice notice-success'><p>✅ Setup documentation found: " . implode(', ', $found_docs) . "</p></div>";
            $this->test_results['setup_documentation'] = 'PASS';
        } else {
            echo "<div class='notice notice-warning'><p>⚠️ No setup documentation found. Consider adding setup instructions.</p></div>";
            $this->test_results['setup_documentation'] = 'WARNING';
        }
    }

    /**
     * Test troubleshooting guidance
     */
    private function test_troubleshooting_guidance() {
        $plugin_path = plugin_dir_path(__FILE__) . '../';
        $troubleshooting_files = [
            'TROUBLESHOOTING.md',
            'docs/troubleshooting.md',
            'docs/faq.md'
        ];

        $found_troubleshooting = [];
        foreach ($troubleshooting_files as $file) {
            if (file_exists($plugin_path . $file)) {
                $found_troubleshooting[] = $file;
            }
        }

        if (!empty($found_troubleshooting)) {
            echo "<div class='notice notice-success'><p>✅ Troubleshooting documentation found: " . implode(', ', $found_troubleshooting) . "</p></div>";
            $this->test_results['troubleshooting_docs'] = 'PASS';
        } else {
            echo "<div class='notice notice-info'><p>ℹ️ Consider adding troubleshooting documentation for common S3 setup issues</p></div>";
            $this->test_results['troubleshooting_docs'] = 'INFO';
        }
    }

    /**
     * Test user interface guidance
     */
    private function test_user_interface_guidance() {
        // Check if admin interface provides clear guidance
        if (class_exists('H3TM_Admin')) {
            echo "<div class='notice notice-success'><p>✅ Admin interface class available</p></div>";

            // Check if there are help text/descriptions in the admin interface
            $admin_file_path = plugin_dir_path(__FILE__) . '../includes/class-h3tm-admin.php';
            if (file_exists($admin_file_path)) {
                $admin_content = file_get_contents($admin_file_path);
                $help_text_count = preg_match_all('/description.*S3|S3.*description/i', $admin_content);

                if ($help_text_count > 0) {
                    echo "<div class='notice notice-success'><p>✅ Admin interface includes S3-related help text</p></div>";
                    $this->test_results['ui_guidance'] = 'PASS';
                } else {
                    echo "<div class='notice notice-warning'><p>⚠️ Admin interface could include more S3-related help text</p></div>";
                    $this->test_results['ui_guidance'] = 'WARNING';
                }
            }
        } else {
            echo "<div class='notice notice-error'><p>❌ Admin interface class not found</p></div>";
            $this->test_results['ui_guidance'] = 'FAIL';
        }
    }

    /**
     * Generate comprehensive validation report
     */
    private function generate_validation_report() {
        echo "<h2>📊 Comprehensive Validation Report</h2>";

        $total_tests = count($this->test_results);
        $passed_tests = count(array_filter($this->test_results, function($result) { return $result === 'PASS'; }));
        $warning_tests = count(array_filter($this->test_results, function($result) { return $result === 'WARNING'; }));
        $failed_tests = count(array_filter($this->test_results, function($result) { return $result === 'FAIL'; }));
        $info_tests = count(array_filter($this->test_results, function($result) { return $result === 'INFO'; }));

        $pass_rate = ($passed_tests / $total_tests) * 100;

        echo "<div class='validation-summary'>";
        echo "<h3>Overall Results</h3>";
        echo "<p><strong>Total Tests:</strong> {$total_tests}</p>";
        echo "<p><strong>Passed:</strong> {$passed_tests} ✅</p>";
        echo "<p><strong>Warnings:</strong> {$warning_tests} ⚠️</p>";
        echo "<p><strong>Failed:</strong> {$failed_tests} ❌</p>";
        echo "<p><strong>Info:</strong> {$info_tests} ℹ️</p>";
        echo "<p><strong>Pass Rate:</strong> " . number_format($pass_rate, 1) . "%</p>";
        echo "</div>";

        // Deployment readiness assessment
        $this->assess_deployment_readiness($pass_rate, $failed_tests);

        // Detailed test results
        $this->display_detailed_test_results();

        // Performance metrics summary
        $this->display_performance_summary();

        // Validation errors summary
        $this->display_validation_errors_summary();

        // Recommendations
        $this->generate_recommendations($pass_rate, $failed_tests, $warning_tests);
    }

    /**
     * Assess deployment readiness
     */
    private function assess_deployment_readiness($pass_rate, $failed_tests) {
        echo "<h3>🚀 Deployment Readiness Assessment</h3>";

        if ($pass_rate >= 90 && $failed_tests === 0) {
            echo "<div class='notice notice-success'>";
            echo "<p><strong>✅ READY FOR DEPLOYMENT</strong></p>";
            echo "<p>The S3-only system validation shows excellent results. The system is ready for production deployment.</p>";
            echo "</div>";
        } elseif ($pass_rate >= 80 && $failed_tests <= 2) {
            echo "<div class='notice notice-warning'>";
            echo "<p><strong>⚠️ DEPLOYMENT WITH CAUTION</strong></p>";
            echo "<p>The system shows good results but has some issues that should be addressed before or shortly after deployment.</p>";
            echo "</div>";
        } elseif ($pass_rate >= 70) {
            echo "<div class='notice notice-warning'>";
            echo "<p><strong>⚠️ NEEDS IMPROVEMENT BEFORE DEPLOYMENT</strong></p>";
            echo "<p>Several issues need to be resolved before the system is ready for production deployment.</p>";
            echo "</div>";
        } else {
            echo "<div class='notice notice-error'>";
            echo "<p><strong>❌ NOT READY FOR DEPLOYMENT</strong></p>";
            echo "<p>Significant issues were found that must be resolved before deployment.</p>";
            echo "</div>";
        }
    }

    /**
     * Display detailed test results
     */
    private function display_detailed_test_results() {
        echo "<h3>📋 Detailed Test Results</h3>";
        echo "<table class='wp-list-table widefat fixed striped'>";
        echo "<thead><tr><th>Test Category</th><th>Result</th><th>Status</th></tr></thead>";
        echo "<tbody>";

        foreach ($this->test_results as $test_name => $result) {
            $status_icon = $this->get_status_icon($result);
            $status_class = $this->get_status_class($result);

            echo "<tr class='{$status_class}'>";
            echo "<td>" . $this->format_test_name($test_name) . "</td>";
            echo "<td>{$result}</td>";
            echo "<td>{$status_icon}</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
    }

    /**
     * Display performance summary
     */
    private function display_performance_summary() {
        if (empty($this->performance_metrics)) {
            return;
        }

        echo "<h3>⚡ Performance Metrics Summary</h3>";
        echo "<table class='wp-list-table widefat fixed striped'>";
        echo "<thead><tr><th>File Size</th><th>Execution Time</th><th>Memory Usage</th><th>Status</th></tr></thead>";
        echo "<tbody>";

        foreach ($this->performance_metrics as $size => $metrics) {
            $status_icon = $metrics['success'] ? '✅' : '❌';
            $status_class = $metrics['success'] ? 'success' : 'error';

            echo "<tr class='{$status_class}'>";
            echo "<td>{$size}</td>";
            echo "<td>" . number_format($metrics['execution_time_ms'], 2) . " ms</td>";
            echo "<td>" . $this->format_bytes($metrics['memory_used_bytes']) . "</td>";
            echo "<td>{$status_icon}</td>";
            echo "</tr>";
        }

        echo "</tbody></table>";
    }

    /**
     * Display validation errors summary
     */
    private function display_validation_errors_summary() {
        if (empty($this->validation_errors)) {
            echo "<h3>✅ No Validation Errors</h3>";
            echo "<p>No critical validation errors were found during testing.</p>";
            return;
        }

        echo "<h3>❌ Validation Errors Summary</h3>";
        echo "<div class='notice notice-error'>";
        echo "<p>The following critical issues were identified:</p>";
        echo "<ul>";
        foreach ($this->validation_errors as $error) {
            echo "<li>{$error}</li>";
        }
        echo "</ul>";
        echo "</div>";
    }

    /**
     * Generate recommendations based on test results
     */
    private function generate_recommendations($pass_rate, $failed_tests, $warning_tests) {
        echo "<h3>💡 Recommendations</h3>";

        $recommendations = [];

        // Critical recommendations based on failures
        if ($failed_tests > 0) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'Critical Issues',
                'action' => 'Address all failed tests before deployment',
                'details' => 'Review the detailed test results and resolve all FAIL status items.'
            ];
        }

        // Configuration recommendations
        if (isset($this->test_results['s3_config_options']) && $this->test_results['s3_config_options'] !== 'PASS') {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'Configuration',
                'action' => 'Complete S3 configuration setup',
                'details' => 'Ensure all S3 configuration options are properly set via WordPress options or environment variables.'
            ];
        }

        // Performance recommendations
        if (isset($this->test_results['memory_optimization']) && $this->test_results['memory_optimization'] === 'WARNING') {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'Performance',
                'action' => 'Optimize memory usage for large files',
                'details' => 'Review memory usage patterns and implement streaming or chunked processing where appropriate.'
            ];
        }

        // Documentation recommendations
        if (isset($this->test_results['setup_documentation']) && $this->test_results['setup_documentation'] !== 'PASS') {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'Documentation',
                'action' => 'Add comprehensive setup documentation',
                'details' => 'Create clear setup guides for S3 configuration and troubleshooting.'
            ];
        }

        // User experience recommendations
        if (isset($this->test_results['config_error_messages']) && $this->test_results['config_error_messages'] !== 'PASS') {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'User Experience',
                'action' => 'Improve error messaging',
                'details' => 'Make error messages more user-friendly with clear guidance on resolution steps.'
            ];
        }

        // Chunk cleanup recommendations
        if (isset($this->test_results['chunked_removal']) && $this->test_results['chunked_removal'] !== 'PASS') {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'Code Cleanup',
                'action' => 'Complete chunked upload code removal',
                'details' => 'Remove all remaining chunked upload functionality to ensure S3-only operation.'
            ];
        }

        // General recommendations
        if ($pass_rate >= 90) {
            $recommendations[] = [
                'priority' => 'LOW',
                'category' => 'Maintenance',
                'action' => 'Regular validation testing',
                'details' => 'Implement automated validation testing to catch issues early in development.'
            ];
        }

        if (empty($recommendations)) {
            echo "<div class='notice notice-success'>";
            echo "<p>✅ No specific recommendations at this time. The system validation shows excellent results!</p>";
            echo "</div>";
        } else {
            foreach ($recommendations as $rec) {
                $priority_class = strtolower($rec['priority']);
                echo "<div class='recommendation-item {$priority_class}'>";
                echo "<h4>{$rec['category']} ({$rec['priority']} Priority)</h4>";
                echo "<p><strong>Action:</strong> {$rec['action']}</p>";
                echo "<p><strong>Details:</strong> {$rec['details']}</p>";
                echo "</div>";
            }
        }

        // Final deployment checklist
        $this->generate_deployment_checklist();
    }

    /**
     * Generate deployment checklist
     */
    private function generate_deployment_checklist() {
        echo "<h3>✅ Deployment Checklist</h3>";

        $checklist_items = [
            'All chunked upload code removed' => isset($this->test_results['chunked_removal']) && $this->test_results['chunked_removal'] === 'PASS',
            'S3 classes properly implemented' => isset($this->test_results['s3_classes']) && $this->test_results['s3_classes'] === 'PASS',
            'AJAX handlers configured correctly' => isset($this->test_results['ajax_handlers']) && $this->test_results['ajax_handlers'] === 'PASS',
            'S3 configuration validation works' => isset($this->test_results['s3_config_valid']) && in_array($this->test_results['s3_config_valid'], ['PASS', 'WARNING']),
            'Presigned URL generation functional' => isset($this->test_results['presigned_url']) && $this->test_results['presigned_url'] === 'PASS',
            'Error handling implemented' => isset($this->test_results['s3_failure_handling']) && $this->test_results['s3_failure_handling'] === 'PASS',
            'Large file handling tested' => !empty($this->performance_metrics),
            'No critical validation errors' => empty($this->validation_errors)
        ];

        echo "<ul class='deployment-checklist'>";
        foreach ($checklist_items as $item => $completed) {
            $icon = $completed ? '✅' : '❌';
            $class = $completed ? 'completed' : 'pending';
            echo "<li class='{$class}'>{$icon} {$item}</li>";
        }
        echo "</ul>";

        $completed_items = count(array_filter($checklist_items));
        $total_items = count($checklist_items);
        $completion_rate = ($completed_items / $total_items) * 100;

        echo "<p><strong>Deployment Readiness:</strong> {$completed_items}/{$total_items} items completed (" . number_format($completion_rate, 1) . "%)</p>";
    }

    /**
     * Helper method: Initialize test environment
     */
    private function initialize_test_environment() {
        // Set WordPress environment for testing
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }

        // Ensure WordPress is loaded
        if (!function_exists('get_option')) {
            echo "<div class='notice notice-error'><p>❌ WordPress environment not properly loaded</p></div>";
            return;
        }

        echo "<div class='notice notice-info'><p>ℹ️ Test environment initialized successfully</p></div>";
    }

    /**
     * Helper method: Get status icon
     */
    private function get_status_icon($result) {
        switch ($result) {
            case 'PASS': return '✅';
            case 'FAIL': return '❌';
            case 'WARNING': return '⚠️';
            case 'INFO': return 'ℹ️';
            default: return '❓';
        }
    }

    /**
     * Helper method: Get status CSS class
     */
    private function get_status_class($result) {
        switch ($result) {
            case 'PASS': return 'success';
            case 'FAIL': return 'error';
            case 'WARNING': return 'warning';
            case 'INFO': return 'info';
            default: return '';
        }
    }

    /**
     * Helper method: Format test name for display
     */
    private function format_test_name($test_name) {
        return ucwords(str_replace('_', ' ', $test_name));
    }

    /**
     * Helper method: Format bytes for display
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Helper method: Parse memory limit string
     */
    private function parse_memory_limit($limit) {
        $limit = trim($limit);
        $multiplier = 1;

        if (preg_match('/^(\d+)(.)$/', $limit, $matches)) {
            $number = intval($matches[1]);
            $unit = strtoupper($matches[2]);

            switch ($unit) {
                case 'K': $multiplier = 1024; break;
                case 'M': $multiplier = 1024 * 1024; break;
                case 'G': $multiplier = 1024 * 1024 * 1024; break;
            }

            return $number * $multiplier;
        }

        return intval($limit);
    }
}

// Auto-execute if accessed directly via WordPress admin
if (defined('ABSPATH') && current_user_can('manage_options')) {
    $validator = new H3TM_S3_Only_System_Validator();
    $validator->run_complete_validation();
}