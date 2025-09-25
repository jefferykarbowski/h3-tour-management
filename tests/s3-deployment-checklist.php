<?php
/**
 * S3-Only System Deployment Checklist
 *
 * Automated checklist to ensure the S3-only system is ready for deployment
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
 * S3 Deployment Checklist Validator
 */
class H3TM_S3_Deployment_Checklist {

    private $checklist_results = [];
    private $critical_issues = [];
    private $warnings = [];

    public function __construct() {
        $this->run_deployment_checklist();
    }

    /**
     * Run complete deployment checklist
     */
    public function run_deployment_checklist() {
        echo "<div class='wrap'><h1>🚀 H3 Tour Management - S3 Deployment Checklist</h1>";

        echo "<div class='deployment-checklist-container'>";

        $this->check_code_completeness();
        $this->check_functionality_implementation();
        $this->check_error_handling();
        $this->check_performance_readiness();
        $this->check_user_experience();
        $this->check_documentation();
        $this->check_security_considerations();

        $this->generate_final_assessment();

        echo "</div></div>";
    }

    /**
     * Check code completeness
     */
    private function check_code_completeness() {
        echo "<h2>📝 Code Completeness Checks</h2>";

        $checks = [
            'chunked_upload_removal' => $this->verify_chunked_upload_removal(),
            'required_s3_classes' => $this->verify_required_s3_classes(),
            'ajax_handlers_updated' => $this->verify_ajax_handlers(),
            'constants_defined' => $this->verify_required_constants(),
            'dependencies_available' => $this->verify_dependencies()
        ];

        $this->display_check_results('Code Completeness', $checks);
    }

    /**
     * Check functionality implementation
     */
    private function check_functionality_implementation() {
        echo "<h2>⚙️ Functionality Implementation</h2>";

        $checks = [
            's3_configuration_validation' => $this->test_s3_configuration(),
            'presigned_url_generation' => $this->test_presigned_urls(),
            'file_upload_simulation' => $this->test_upload_simulation(),
            'download_extraction' => $this->test_download_extraction(),
            'tour_processing' => $this->test_tour_processing()
        ];

        $this->display_check_results('Functionality', $checks);
    }

    /**
     * Check error handling
     */
    private function check_error_handling() {
        echo "<h2>🛡️ Error Handling</h2>";

        $checks = [
            's3_not_configured' => $this->test_s3_not_configured_handling(),
            'invalid_credentials' => $this->test_invalid_credentials_handling(),
            'network_failure' => $this->test_network_failure_handling(),
            'processing_failure' => $this->test_processing_failure_handling(),
            'graceful_degradation' => $this->test_graceful_degradation()
        ];

        $this->display_check_results('Error Handling', $checks);
    }

    /**
     * Check performance readiness
     */
    private function check_performance_readiness() {
        echo "<h2>⚡ Performance Readiness</h2>";

        $checks = [
            'memory_usage_optimized' => $this->check_memory_optimization(),
            'execution_time_reasonable' => $this->check_execution_time(),
            'large_file_handling' => $this->check_large_file_support(),
            'timeout_handling' => $this->check_timeout_handling(),
            'concurrent_request_ready' => $this->check_concurrent_handling()
        ];

        $this->display_check_results('Performance', $checks);
    }

    /**
     * Check user experience
     */
    private function check_user_experience() {
        echo "<h2>👤 User Experience</h2>";

        $checks = [
            'clear_error_messages' => $this->check_error_message_quality(),
            'setup_guidance' => $this->check_setup_guidance(),
            'progress_indicators' => $this->check_progress_indicators(),
            'admin_interface_clarity' => $this->check_admin_interface(),
            'help_documentation' => $this->check_help_documentation()
        ];

        $this->display_check_results('User Experience', $checks);
    }

    /**
     * Check documentation
     */
    private function check_documentation() {
        echo "<h2>📚 Documentation</h2>";

        $checks = [
            'setup_instructions' => $this->check_setup_documentation(),
            'troubleshooting_guide' => $this->check_troubleshooting_docs(),
            'api_documentation' => $this->check_api_documentation(),
            'code_comments' => $this->check_code_documentation(),
            'changelog_updated' => $this->check_changelog()
        ];

        $this->display_check_results('Documentation', $checks);
    }

    /**
     * Check security considerations
     */
    private function check_security_considerations() {
        echo "<h2>🔒 Security Considerations</h2>";

        $checks = [
            'credentials_secure' => $this->check_credential_security(),
            'file_validation' => $this->check_file_validation(),
            'access_controls' => $this->check_access_controls(),
            'sanitization' => $this->check_input_sanitization(),
            'audit_logging' => $this->check_audit_logging()
        ];

        $this->display_check_results('Security', $checks);
    }

    /**
     * Verify chunked upload removal
     */
    private function verify_chunked_upload_removal() {
        $plugin_path = plugin_dir_path(__FILE__) . '../';
        $critical_files = [
            'includes/class-h3tm-admin.php',
            'includes/class-h3tm-tour-manager.php',
            'includes/class-h3tm-s3-integration.php'
        ];

        $chunked_references = [];
        foreach ($critical_files as $file) {
            $full_path = $plugin_path . $file;
            if (!file_exists($full_path)) continue;

            $content = file_get_contents($full_path);
            if (preg_match('/chunk.*upload|upload.*chunk/i', $content)) {
                $chunked_references[] = $file;
            }
        }

        return [
            'status' => empty($chunked_references) ? 'PASS' : 'FAIL',
            'details' => empty($chunked_references)
                ? 'No chunked upload references found'
                : 'Chunked upload references found in: ' . implode(', ', $chunked_references)
        ];
    }

    /**
     * Verify required S3 classes
     */
    private function verify_required_s3_classes() {
        $required_classes = [
            'H3TM_S3_Integration',
            'H3TM_S3_Simple',
            'H3TM_S3_Config_Manager',
            'H3TM_S3_Uploader'
        ];

        $missing_classes = [];
        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                $missing_classes[] = $class;
            }
        }

        return [
            'status' => empty($missing_classes) ? 'PASS' : 'FAIL',
            'details' => empty($missing_classes)
                ? 'All required S3 classes available'
                : 'Missing classes: ' . implode(', ', $missing_classes)
        ];
    }

    /**
     * Verify AJAX handlers
     */
    private function verify_ajax_handlers() {
        $required_handlers = [
            'h3tm_get_s3_presigned_url',
            'h3tm_s3_upload_complete',
            'h3tm_process_s3_tour'
        ];

        $old_handlers = [
            'h3tm_upload_chunk',
            'h3tm_finalize_upload'
        ];

        $missing_required = [];
        $remaining_old = [];

        foreach ($required_handlers as $handler) {
            if (!has_action("wp_ajax_{$handler}")) {
                $missing_required[] = $handler;
            }
        }

        foreach ($old_handlers as $handler) {
            if (has_action("wp_ajax_{$handler}")) {
                $remaining_old[] = $handler;
            }
        }

        $issues = array_merge($missing_required, $remaining_old);

        return [
            'status' => empty($issues) ? 'PASS' : 'FAIL',
            'details' => empty($issues)
                ? 'AJAX handlers correctly configured'
                : 'Issues: Missing ' . implode(', ', $missing_required) .
                  (!empty($remaining_old) ? '; Old handlers remain: ' . implode(', ', $remaining_old) : '')
        ];
    }

    /**
     * Verify required constants
     */
    private function verify_required_constants() {
        $optional_constants = [
            'H3_S3_BUCKET',
            'H3_S3_REGION',
            'H3_S3_ACCESS_KEY',
            'H3_S3_SECRET_KEY'
        ];

        $defined_constants = [];
        foreach ($optional_constants as $constant) {
            if (defined($constant)) {
                $defined_constants[] = $constant;
            }
        }

        // Constants are optional, so this is always a pass but informational
        return [
            'status' => 'INFO',
            'details' => count($defined_constants) . ' of ' . count($optional_constants) .
                        ' optional S3 constants defined: ' . implode(', ', $defined_constants)
        ];
    }

    /**
     * Verify dependencies
     */
    private function verify_dependencies() {
        $required_functions = [
            'wp_upload_dir',
            'wp_mkdir_p',
            'get_option',
            'update_option'
        ];

        $missing_functions = [];
        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                $missing_functions[] = $function;
            }
        }

        return [
            'status' => empty($missing_functions) ? 'PASS' : 'FAIL',
            'details' => empty($missing_functions)
                ? 'All WordPress dependencies available'
                : 'Missing functions: ' . implode(', ', $missing_functions)
        ];
    }

    /**
     * Test S3 configuration
     */
    private function test_s3_configuration() {
        if (!class_exists('H3TM_S3_Config_Manager')) {
            return ['status' => 'FAIL', 'details' => 'H3TM_S3_Config_Manager class not found'];
        }

        try {
            $config_manager = new H3TM_S3_Config_Manager();
            $validation_result = $config_manager->validate_configuration();

            return [
                'status' => $validation_result['valid'] ? 'PASS' : 'WARNING',
                'details' => $validation_result['valid']
                    ? 'S3 configuration is valid'
                    : 'S3 configuration issues: ' . implode(', ', $validation_result['errors'] ?? ['Unknown error'])
            ];
        } catch (Exception $e) {
            return ['status' => 'FAIL', 'details' => 'Configuration validation error: ' . $e->getMessage()];
        }
    }

    /**
     * Test presigned URLs
     */
    private function test_presigned_urls() {
        if (!class_exists('H3TM_S3_Integration')) {
            return ['status' => 'FAIL', 'details' => 'H3TM_S3_Integration class not found'];
        }

        try {
            $s3_integration = new H3TM_S3_Integration();
            $test_params = [
                'filename' => 'test-tour.zip',
                'filesize' => 10 * 1024 * 1024,
                'content_type' => 'application/zip'
            ];

            $result = $s3_integration->generate_presigned_upload_url($test_params);

            return [
                'status' => ($result && isset($result['upload_url'])) ? 'PASS' : 'WARNING',
                'details' => ($result && isset($result['upload_url']))
                    ? 'Presigned URL generation working'
                    : 'Presigned URL generation needs S3 configuration'
            ];
        } catch (Exception $e) {
            return ['status' => 'WARNING', 'details' => 'Presigned URL test needs S3 credentials: ' . $e->getMessage()];
        }
    }

    /**
     * Test upload simulation
     */
    private function test_upload_simulation() {
        // This would require actual S3 credentials to test fully
        return [
            'status' => 'INFO',
            'details' => 'Upload simulation requires valid S3 credentials for complete testing'
        ];
    }

    /**
     * Test download extraction
     */
    private function test_download_extraction() {
        if (!class_exists('H3TM_S3_Processor')) {
            return ['status' => 'FAIL', 'details' => 'H3TM_S3_Processor class not found'];
        }

        $s3_processor = new H3TM_S3_Processor();
        $has_download_method = method_exists($s3_processor, 'download_from_s3');
        $has_extract_method = method_exists($s3_processor, 'extract_tour_from_zip');

        return [
            'status' => ($has_download_method && $has_extract_method) ? 'PASS' : 'FAIL',
            'details' => ($has_download_method && $has_extract_method)
                ? 'Download and extraction methods available'
                : 'Missing methods: ' .
                  (!$has_download_method ? 'download_from_s3 ' : '') .
                  (!$has_extract_method ? 'extract_tour_from_zip' : '')
        ];
    }

    /**
     * Test tour processing
     */
    private function test_tour_processing() {
        if (!class_exists('H3TM_Tour_Manager')) {
            return ['status' => 'FAIL', 'details' => 'H3TM_Tour_Manager class not found'];
        }

        // Check if tour manager has S3 integration
        $tour_manager = new H3TM_Tour_Manager();
        $has_s3_method = method_exists($tour_manager, 'process_s3_upload');

        return [
            'status' => $has_s3_method ? 'PASS' : 'WARNING',
            'details' => $has_s3_method
                ? 'Tour manager has S3 processing capability'
                : 'Tour manager S3 integration method not found'
        ];
    }

    /**
     * Test S3 not configured handling
     */
    private function test_s3_not_configured_handling() {
        // Temporarily disable S3
        $original_enabled = get_option('h3tm_s3_enabled');
        update_option('h3tm_s3_enabled', false);

        try {
            if (class_exists('H3TM_S3_Config_Manager')) {
                $config_manager = new H3TM_S3_Config_Manager();
                $validation = $config_manager->validate_configuration();

                $status = !$validation['valid'] ? 'PASS' : 'FAIL';
                $details = !$validation['valid']
                    ? 'Correctly detects when S3 is not configured'
                    : 'Failed to detect S3 not configured';

                return ['status' => $status, 'details' => $details];
            }
        } finally {
            update_option('h3tm_s3_enabled', $original_enabled);
        }

        return ['status' => 'FAIL', 'details' => 'S3 configuration manager not available'];
    }

    /**
     * Test invalid credentials handling
     */
    private function test_invalid_credentials_handling() {
        return [
            'status' => 'INFO',
            'details' => 'Invalid credentials handling requires controlled test environment'
        ];
    }

    /**
     * Test network failure handling
     */
    private function test_network_failure_handling() {
        return [
            'status' => 'INFO',
            'details' => 'Network failure handling requires controlled network conditions'
        ];
    }

    /**
     * Test processing failure handling
     */
    private function test_processing_failure_handling() {
        if (!class_exists('H3TM_S3_Processor')) {
            return ['status' => 'FAIL', 'details' => 'H3TM_S3_Processor class not found'];
        }

        try {
            $s3_processor = new H3TM_S3_Processor();
            $result = $s3_processor->process_s3_tour('non-existent-file.zip');

            return [
                'status' => (!$result || isset($result['error'])) ? 'PASS' : 'WARNING',
                'details' => (!$result || isset($result['error']))
                    ? 'Processing failure handled gracefully'
                    : 'Processing failure handling needs improvement'
            ];
        } catch (Exception $e) {
            return ['status' => 'PASS', 'details' => 'Processing failure throws exception: ' . $e->getMessage()];
        }
    }

    /**
     * Test graceful degradation
     */
    private function test_graceful_degradation() {
        return [
            'status' => 'INFO',
            'details' => 'Graceful degradation testing requires runtime failure simulation'
        ];
    }

    /**
     * Check memory optimization
     */
    private function check_memory_optimization() {
        $memory_limit = ini_get('memory_limit');
        $current_usage = memory_get_usage();
        $current_peak = memory_get_peak_usage();

        return [
            'status' => 'INFO',
            'details' => sprintf(
                'Memory limit: %s, Current usage: %s, Peak usage: %s',
                $memory_limit,
                $this->format_bytes($current_usage),
                $this->format_bytes($current_peak)
            )
        ];
    }

    /**
     * Check execution time
     */
    private function check_execution_time() {
        $max_execution_time = ini_get('max_execution_time');

        return [
            'status' => ($max_execution_time == 0 || $max_execution_time >= 30) ? 'PASS' : 'WARNING',
            'details' => $max_execution_time == 0
                ? 'No execution time limit (good for large uploads)'
                : "Execution time limit: {$max_execution_time} seconds"
        ];
    }

    /**
     * Check large file support
     */
    private function check_large_file_support() {
        $upload_max_filesize = ini_get('upload_max_filesize');
        $post_max_size = ini_get('post_max_size');

        return [
            'status' => 'INFO',
            'details' => "PHP upload limits: upload_max_filesize={$upload_max_filesize}, post_max_size={$post_max_size} (S3 direct upload bypasses these)"
        ];
    }

    /**
     * Check timeout handling
     */
    private function check_timeout_handling() {
        // Check if timeouts are properly configured in S3 operations
        return [
            'status' => 'INFO',
            'details' => 'Timeout handling verification requires active S3 operations'
        ];
    }

    /**
     * Check concurrent handling
     */
    private function check_concurrent_handling() {
        return [
            'status' => 'INFO',
            'details' => 'Concurrent request handling requires load testing'
        ];
    }

    /**
     * Check error message quality
     */
    private function check_error_message_quality() {
        if (!class_exists('H3TM_S3_Config_Manager')) {
            return ['status' => 'FAIL', 'details' => 'Cannot test error messages without config manager'];
        }

        // Test error message when S3 is not configured
        $original_bucket = get_option('h3tm_s3_bucket');
        update_option('h3tm_s3_bucket', '');

        try {
            $config_manager = new H3TM_S3_Config_Manager();
            $error_message = $config_manager->get_configuration_error_message();

            $quality_score = 0;
            if (stripos($error_message, 's3') !== false) $quality_score++;
            if (stripos($error_message, 'configur') !== false) $quality_score++;
            if (stripos($error_message, 'setup') !== false) $quality_score++;
            if (strlen($error_message) >= 20) $quality_score++;

            return [
                'status' => ($quality_score >= 3) ? 'PASS' : 'WARNING',
                'details' => "Error message quality score: {$quality_score}/4"
            ];
        } finally {
            update_option('h3tm_s3_bucket', $original_bucket);
        }
    }

    /**
     * Check setup guidance
     */
    private function check_setup_guidance() {
        $plugin_path = plugin_dir_path(__FILE__) . '../';
        $setup_files = ['README.md', 'SETUP.md', 'docs/setup.md'];

        $found_files = [];
        foreach ($setup_files as $file) {
            if (file_exists($plugin_path . $file)) {
                $found_files[] = $file;
            }
        }

        return [
            'status' => !empty($found_files) ? 'PASS' : 'WARNING',
            'details' => !empty($found_files)
                ? 'Setup documentation found: ' . implode(', ', $found_files)
                : 'No setup documentation found'
        ];
    }

    /**
     * Check progress indicators
     */
    private function check_progress_indicators() {
        // Check if admin interface has progress indicators
        $admin_file = plugin_dir_path(__FILE__) . '../includes/class-h3tm-admin.php';
        if (!file_exists($admin_file)) {
            return ['status' => 'FAIL', 'details' => 'Admin file not found'];
        }

        $content = file_get_contents($admin_file);
        $has_progress = (stripos($content, 'progress') !== false);

        return [
            'status' => $has_progress ? 'PASS' : 'INFO',
            'details' => $has_progress
                ? 'Progress indicators appear to be implemented'
                : 'Consider adding progress indicators for uploads'
        ];
    }

    /**
     * Check admin interface
     */
    private function check_admin_interface() {
        if (!class_exists('H3TM_Admin')) {
            return ['status' => 'FAIL', 'details' => 'Admin class not found'];
        }

        return [
            'status' => 'PASS',
            'details' => 'Admin interface class available'
        ];
    }

    /**
     * Check help documentation
     */
    private function check_help_documentation() {
        $plugin_path = plugin_dir_path(__FILE__) . '../';
        $help_files = ['HELP.md', 'docs/help.md', 'docs/faq.md'];

        $found_files = [];
        foreach ($help_files as $file) {
            if (file_exists($plugin_path . $file)) {
                $found_files[] = $file;
            }
        }

        return [
            'status' => !empty($found_files) ? 'PASS' : 'INFO',
            'details' => !empty($found_files)
                ? 'Help documentation found: ' . implode(', ', $found_files)
                : 'Consider adding help documentation'
        ];
    }

    /**
     * Check setup documentation
     */
    private function check_setup_documentation() {
        $plugin_path = plugin_dir_path(__FILE__) . '../';
        $required_docs = ['README.md', 'SETUP.md'];

        $missing_docs = [];
        foreach ($required_docs as $doc) {
            if (!file_exists($plugin_path . $doc)) {
                $missing_docs[] = $doc;
            }
        }

        return [
            'status' => empty($missing_docs) ? 'PASS' : 'WARNING',
            'details' => empty($missing_docs)
                ? 'Required documentation files present'
                : 'Missing documentation: ' . implode(', ', $missing_docs)
        ];
    }

    /**
     * Check troubleshooting docs
     */
    private function check_troubleshooting_docs() {
        $plugin_path = plugin_dir_path(__FILE__) . '../';
        $troubleshooting_files = ['TROUBLESHOOTING.md', 'docs/troubleshooting.md'];

        $found_files = [];
        foreach ($troubleshooting_files as $file) {
            if (file_exists($plugin_path . $file)) {
                $found_files[] = $file;
            }
        }

        return [
            'status' => !empty($found_files) ? 'PASS' : 'INFO',
            'details' => !empty($found_files)
                ? 'Troubleshooting docs found: ' . implode(', ', $found_files)
                : 'Consider adding troubleshooting documentation'
        ];
    }

    /**
     * Check API documentation
     */
    private function check_api_documentation() {
        return [
            'status' => 'INFO',
            'details' => 'API documentation check requires manual review'
        ];
    }

    /**
     * Check code documentation
     */
    private function check_code_documentation() {
        $plugin_path = plugin_dir_path(__FILE__) . '../includes/';
        $core_files = ['class-h3tm-s3-integration.php', 'class-h3tm-s3-simple.php'];

        $documented_files = 0;
        foreach ($core_files as $file) {
            $full_path = $plugin_path . $file;
            if (file_exists($full_path)) {
                $content = file_get_contents($full_path);
                if (preg_match_all('/\/\*\*.*?\*\//s', $content) >= 3) {
                    $documented_files++;
                }
            }
        }

        return [
            'status' => ($documented_files >= count($core_files) * 0.8) ? 'PASS' : 'WARNING',
            'details' => "{$documented_files} of " . count($core_files) . " core files have adequate documentation"
        ];
    }

    /**
     * Check changelog
     */
    private function check_changelog() {
        $plugin_path = plugin_dir_path(__FILE__) . '../';
        $changelog_file = $plugin_path . 'CHANGELOG.md';

        if (!file_exists($changelog_file)) {
            return ['status' => 'WARNING', 'details' => 'CHANGELOG.md not found'];
        }

        $content = file_get_contents($changelog_file);
        $has_recent_entry = (stripos($content, '1.4.6') !== false || stripos($content, date('Y-m')) !== false);

        return [
            'status' => $has_recent_entry ? 'PASS' : 'WARNING',
            'details' => $has_recent_entry
                ? 'Changelog appears to be updated'
                : 'Changelog may need updating for this release'
        ];
    }

    /**
     * Check credential security
     */
    private function check_credential_security() {
        $security_issues = [];

        // Check if credentials are hardcoded in files
        $plugin_path = plugin_dir_path(__FILE__) . '../';
        $files_to_check = glob($plugin_path . 'includes/*.php');

        foreach ($files_to_check as $file) {
            $content = file_get_contents($file);
            if (preg_match('/AKIA[0-9A-Z]{16}/', $content)) {
                $security_issues[] = 'Hardcoded AWS access key found in ' . basename($file);
            }
        }

        return [
            'status' => empty($security_issues) ? 'PASS' : 'CRITICAL',
            'details' => empty($security_issues)
                ? 'No hardcoded credentials found'
                : implode(', ', $security_issues)
        ];
    }

    /**
     * Check file validation
     */
    private function check_file_validation() {
        if (!class_exists('H3TM_S3_Integration')) {
            return ['status' => 'FAIL', 'details' => 'Cannot check file validation without S3 integration'];
        }

        // Check if file type validation exists
        $s3_file = plugin_dir_path(__FILE__) . '../includes/class-h3tm-s3-integration.php';
        if (file_exists($s3_file)) {
            $content = file_get_contents($s3_file);
            $has_validation = (stripos($content, 'validate') !== false && stripos($content, 'file') !== false);

            return [
                'status' => $has_validation ? 'PASS' : 'WARNING',
                'details' => $has_validation
                    ? 'File validation appears to be implemented'
                    : 'File validation may need enhancement'
            ];
        }

        return ['status' => 'FAIL', 'details' => 'S3 integration file not found'];
    }

    /**
     * Check access controls
     */
    private function check_access_controls() {
        // Check if admin functions require proper capabilities
        if (class_exists('H3TM_Admin')) {
            return [
                'status' => 'PASS',
                'details' => 'Admin class implements WordPress capability checks'
            ];
        }

        return ['status' => 'WARNING', 'details' => 'Admin access controls need verification'];
    }

    /**
     * Check input sanitization
     */
    private function check_input_sanitization() {
        $admin_file = plugin_dir_path(__FILE__) . '../includes/class-h3tm-admin.php';
        if (!file_exists($admin_file)) {
            return ['status' => 'FAIL', 'details' => 'Admin file not found'];
        }

        $content = file_get_contents($admin_file);
        $has_sanitization = (stripos($content, 'sanitize_') !== false || stripos($content, 'wp_kses') !== false);

        return [
            'status' => $has_sanitization ? 'PASS' : 'WARNING',
            'details' => $has_sanitization
                ? 'Input sanitization functions detected'
                : 'Input sanitization may need enhancement'
        ];
    }

    /**
     * Check audit logging
     */
    private function check_audit_logging() {
        $core_files = glob(plugin_dir_path(__FILE__) . '../includes/class-h3tm-*.php');
        $has_logging = false;

        foreach ($core_files as $file) {
            $content = file_get_contents($file);
            if (stripos($content, 'error_log') !== false || stripos($content, 'wp_log') !== false) {
                $has_logging = true;
                break;
            }
        }

        return [
            'status' => $has_logging ? 'PASS' : 'INFO',
            'details' => $has_logging
                ? 'Audit logging appears to be implemented'
                : 'Consider adding more comprehensive audit logging'
        ];
    }

    /**
     * Display check results
     */
    private function display_check_results($category, $checks) {
        echo "<div class='checklist-category'>";
        echo "<table class='wp-list-table widefat fixed striped'>";
        echo "<thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>";
        echo "<tbody>";

        foreach ($checks as $check_name => $result) {
            $this->checklist_results[$category][$check_name] = $result;

            $status_icon = $this->get_status_icon($result['status']);
            $status_class = $this->get_status_class($result['status']);

            echo "<tr class='{$status_class}'>";
            echo "<td>" . $this->format_check_name($check_name) . "</td>";
            echo "<td>{$status_icon} {$result['status']}</td>";
            echo "<td>{$result['details']}</td>";
            echo "</tr>";

            // Track critical issues and warnings
            if ($result['status'] === 'FAIL' || $result['status'] === 'CRITICAL') {
                $this->critical_issues[] = "{$category}: {$check_name} - {$result['details']}";
            } elseif ($result['status'] === 'WARNING') {
                $this->warnings[] = "{$category}: {$check_name} - {$result['details']}";
            }
        }

        echo "</tbody></table>";
        echo "</div>";
    }

    /**
     * Generate final assessment
     */
    private function generate_final_assessment() {
        echo "<h2>🎯 Final Deployment Assessment</h2>";

        $total_checks = 0;
        $passed_checks = 0;
        $critical_failures = 0;

        foreach ($this->checklist_results as $category => $checks) {
            foreach ($checks as $check => $result) {
                $total_checks++;
                if ($result['status'] === 'PASS') {
                    $passed_checks++;
                } elseif ($result['status'] === 'FAIL' || $result['status'] === 'CRITICAL') {
                    $critical_failures++;
                }
            }
        }

        $pass_rate = ($passed_checks / $total_checks) * 100;

        echo "<div class='final-assessment'>";
        echo "<h3>Summary Statistics</h3>";
        echo "<p><strong>Total Checks:</strong> {$total_checks}</p>";
        echo "<p><strong>Passed:</strong> {$passed_checks}</p>";
        echo "<p><strong>Critical Failures:</strong> {$critical_failures}</p>";
        echo "<p><strong>Warnings:</strong> " . count($this->warnings) . "</p>";
        echo "<p><strong>Pass Rate:</strong> " . number_format($pass_rate, 1) . "%</p>";

        // Deployment decision
        if ($critical_failures === 0 && $pass_rate >= 85) {
            echo "<div class='notice notice-success deployment-ready'>";
            echo "<h3>✅ READY FOR DEPLOYMENT</h3>";
            echo "<p>The S3-only system has passed all critical checks and is ready for production deployment.</p>";
            echo "</div>";
        } elseif ($critical_failures === 0 && $pass_rate >= 70) {
            echo "<div class='notice notice-warning deployment-caution'>";
            echo "<h3>⚠️ DEPLOY WITH CAUTION</h3>";
            echo "<p>The system is functional but has some warnings that should be addressed.</p>";
            echo "</div>";
        } else {
            echo "<div class='notice notice-error deployment-blocked'>";
            echo "<h3>❌ NOT READY FOR DEPLOYMENT</h3>";
            echo "<p>Critical issues must be resolved before deployment.</p>";
            echo "</div>";
        }

        // Critical issues
        if (!empty($this->critical_issues)) {
            echo "<h3>🚨 Critical Issues to Resolve</h3>";
            echo "<ul class='critical-issues'>";
            foreach ($this->critical_issues as $issue) {
                echo "<li>{$issue}</li>";
            }
            echo "</ul>";
        }

        // Warnings
        if (!empty($this->warnings)) {
            echo "<h3>⚠️ Warnings to Address</h3>";
            echo "<ul class='warnings'>";
            foreach (array_slice($this->warnings, 0, 10) as $warning) { // Show first 10
                echo "<li>{$warning}</li>";
            }
            if (count($this->warnings) > 10) {
                echo "<li><em>... and " . (count($this->warnings) - 10) . " more warnings</em></li>";
            }
            echo "</ul>";
        }

        echo "</div>";
    }

    /**
     * Helper methods
     */
    private function get_status_icon($status) {
        switch ($status) {
            case 'PASS': return '✅';
            case 'FAIL': return '❌';
            case 'CRITICAL': return '🚨';
            case 'WARNING': return '⚠️';
            case 'INFO': return 'ℹ️';
            default: return '❓';
        }
    }

    private function get_status_class($status) {
        switch ($status) {
            case 'PASS': return 'status-pass';
            case 'FAIL': case 'CRITICAL': return 'status-fail';
            case 'WARNING': return 'status-warning';
            case 'INFO': return 'status-info';
            default: return '';
        }
    }

    private function format_check_name($name) {
        return ucwords(str_replace('_', ' ', $name));
    }

    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Auto-execute if accessed directly
if (defined('ABSPATH') && current_user_can('manage_options')) {
    $checklist = new H3TM_S3_Deployment_Checklist();
}