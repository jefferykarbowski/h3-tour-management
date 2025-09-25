<?php
/**
 * Optimized Admin functionality for H3 Tour Management
 *
 * Enhanced with progress tracking and timeout handling for tour operations
 *
 * @package H3_Tour_Management
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Admin_Optimized extends H3TM_Admin_V2 {

    /**
     * Constructor - Add optimized AJAX handlers
     */
    public function __construct() {
        parent::__construct();

        // Add optimized AJAX handlers
        $this->register_optimized_ajax_handlers();
    }

    /**
     * Register optimized AJAX handlers
     */
    private function register_optimized_ajax_handlers() {
        $handlers = array(
            'h3tm_rename_tour_optimized' => 'handle_rename_tour_optimized',
            'h3tm_get_operation_progress' => 'handle_get_operation_progress',
            'h3tm_cancel_operation' => 'handle_cancel_operation'
        );

        foreach ($handlers as $action => $method) {
            add_action('wp_ajax_' . $action, array($this, $method));
        }
    }

    /**
     * Enhanced script localization with progress tracking support
     */
    public function enqueue_admin_scripts($hook) {
        parent::enqueue_admin_scripts($hook);

        // Add optimized admin scripts only on relevant pages
        if (strpos($hook, 'h3-tour-management') !== false || strpos($hook, 'h3tm') !== false) {
            wp_enqueue_script(
                'h3tm-admin-optimized',
                H3TM_PLUGIN_URL . 'assets/js/admin-optimized.js',
                array('h3tm-admin'),
                H3TM_VERSION,
                true
            );

            // Enhanced localization for optimized features
            wp_localize_script('h3tm-admin-optimized', 'h3tm_optimized', array(
                'progress_interval' => 2000, // Check progress every 2 seconds
                'max_progress_checks' => 300, // Maximum 10 minutes of progress checking
                'strings' => array(
                    'operation_starting' => __('Starting operation...', 'h3-tour-management'),
                    'operation_progress' => __('Operation in progress: {progress}%', 'h3-tour-management'),
                    'operation_completed' => __('Operation completed successfully!', 'h3-tour-management'),
                    'operation_failed' => __('Operation failed: {error}', 'h3-tour-management'),
                    'operation_timeout' => __('Operation is taking longer than expected. Please check back in a few minutes.', 'h3-tour-management'),
                    'large_tour_warning' => __('This tour contains many files and may take several minutes to rename.', 'h3-tour-management')
                )
            ));
        }
    }

    /**
     * Handle optimized rename tour AJAX request with progress tracking
     */
    public function handle_rename_tour_optimized() {
        // Verify request security
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(array(
                'code' => 'security_failed',
                'message' => __('Security verification failed', 'h3-tour-management')
            ));
        }

        // Validate user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'code' => 'insufficient_permissions',
                'message' => __('Insufficient permissions', 'h3-tour-management')
            ));
        }

        $old_name = sanitize_text_field($_POST['old_name'] ?? '');
        $new_name = sanitize_text_field($_POST['new_name'] ?? '');
        $options = array(
            'progress_tracking' => true,
            'timeout_handling' => true,
            'force_background' => isset($_POST['force_background']) && $_POST['force_background'] === 'true'
        );

        if (empty($old_name) || empty($new_name)) {
            wp_send_json_error(array(
                'code' => 'missing_parameters',
                'message' => __('Both old and new names are required', 'h3-tour-management')
            ));
        }

        try {
            $tour_manager = new H3TM_Tour_Manager_Optimized();
            $result = $tour_manager->rename_tour_optimized($old_name, $new_name, $options);

            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'data' => $result['data'] ?? array()
                ));
            } else {
                wp_send_json_error(array(
                    'code' => $result['error']['code'] ?? 'unknown_error',
                    'message' => $result['error']['message'],
                    'context' => $result['error']['context'] ?? array()
                ));
            }

        } catch (Exception $e) {
            H3TM_Logger::error('admin', 'Unexpected error in optimized rename handler', array(
                'old_name' => $old_name,
                'new_name' => $new_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));

            wp_send_json_error(array(
                'code' => 'unexpected_error',
                'message' => __('An unexpected error occurred. Please try again.', 'h3-tour-management'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG ? $e->getMessage() : null
            ));
        }
    }

    /**
     * Handle operation progress request
     */
    public function handle_get_operation_progress() {
        // Verify request security
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(array(
                'code' => 'security_failed',
                'message' => __('Security verification failed', 'h3-tour-management')
            ));
        }

        $operation_id = sanitize_text_field($_POST['operation_id'] ?? '');

        if (empty($operation_id)) {
            wp_send_json_error(array(
                'code' => 'missing_operation_id',
                'message' => __('Operation ID is required', 'h3-tour-management')
            ));
        }

        try {
            $tour_manager = new H3TM_Tour_Manager_Optimized();
            $progress_data = $tour_manager->get_operation_progress($operation_id);

            if ($progress_data === false) {
                wp_send_json_error(array(
                    'code' => 'operation_not_found',
                    'message' => __('Operation not found or expired', 'h3-tour-management')
                ));
            }

            wp_send_json_success(array(
                'progress' => $progress_data,
                'timestamp' => current_time('mysql')
            ));

        } catch (Exception $e) {
            H3TM_Logger::error('admin', 'Error retrieving operation progress', array(
                'operation_id' => $operation_id,
                'error' => $e->getMessage()
            ));

            wp_send_json_error(array(
                'code' => 'progress_error',
                'message' => __('Failed to retrieve operation progress', 'h3-tour-management')
            ));
        }
    }

    /**
     * Handle operation cancellation request (placeholder)
     */
    public function handle_cancel_operation() {
        // Verify request security
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(array(
                'code' => 'security_failed',
                'message' => __('Security verification failed', 'h3-tour-management')
            ));
        }

        $operation_id = sanitize_text_field($_POST['operation_id'] ?? '');

        if (empty($operation_id)) {
            wp_send_json_error(array(
                'code' => 'missing_operation_id',
                'message' => __('Operation ID is required', 'h3-tour-management')
            ));
        }

        // For now, this is a placeholder - actual cancellation would require
        // more complex operation state management
        wp_send_json_error(array(
            'code' => 'not_implemented',
            'message' => __('Operation cancellation is not yet implemented', 'h3-tour-management')
        ));
    }

    /**
     * Override parent rename handler to provide fallback
     */
    public function handle_rename_tour() {
        // Add a header to indicate this is the legacy handler
        if (!headers_sent()) {
            header('X-H3TM-Handler: legacy');
        }

        // Check if optimized version should be used
        $use_optimized = get_option('h3tm_use_optimized_operations', '1') === '1';

        if ($use_optimized && class_exists('H3TM_Tour_Manager_Optimized')) {
            // Delegate to optimized handler
            $this->handle_rename_tour_optimized();
            return;
        }

        // Fall back to parent implementation
        parent::handle_rename_tour();
    }

    /**
     * Enhanced error response formatting
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param array $context Additional context
     * @param int $http_code HTTP response code
     */
    private function send_enhanced_error($code, $message, $context = array(), $http_code = 400) {
        $error_response = array(
            'success' => false,
            'error' => array(
                'code' => $code,
                'message' => $message,
                'context' => $context,
                'timestamp' => current_time('mysql'),
                'trace_id' => uniqid('error_', true)
            )
        );

        // Log the error for debugging
        H3TM_Logger::error('admin', 'Enhanced error response', $error_response['error']);

        status_header($http_code);
        wp_send_json($error_response);
    }

    /**
     * Enhanced success response formatting
     *
     * @param string $message Success message
     * @param array $data Additional data
     */
    private function send_enhanced_success($message, $data = array()) {
        $success_response = array(
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => current_time('mysql')
        );

        wp_send_json($success_response);
    }

    /**
     * Add operation status page to admin menu
     */
    public function add_admin_menu() {
        parent::add_admin_menu();

        // Add operation status submenu
        add_submenu_page(
            'h3-tour-management',
            __('Operations Status', 'h3-tour-management'),
            __('Operations', 'h3-tour-management'),
            'manage_options',
            'h3tm-operations',
            array($this, 'render_operations_page')
        );
    }

    /**
     * Render operations status page
     */
    public function render_operations_page() {
        // Get active operations (this would need to be implemented)
        $active_operations = $this->get_active_operations();

        include H3TM_PLUGIN_DIR . 'templates/admin/operations.php';
    }

    /**
     * Get list of active operations
     *
     * @return array Active operations
     */
    private function get_active_operations() {
        global $wpdb;

        // This is a simplified implementation - in production you'd want
        // a proper operations table or more sophisticated tracking
        $prefix = H3TM_Tour_Manager_Optimized::PROGRESS_PREFIX;

        $operations = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE %s
             AND option_name NOT LIKE %s
             ORDER BY option_name DESC",
            '_transient_' . $prefix . '%',
            '_transient_timeout_%'
        ));

        $active_ops = array();

        foreach ($operations as $op) {
            $operation_data = maybe_unserialize($op->option_value);
            if ($operation_data && isset($operation_data['status'])) {
                $operation_data['id'] = str_replace('_transient_' . $prefix, '', $op->option_name);
                $active_ops[] = $operation_data;
            }
        }

        return $active_ops;
    }

    /**
     * Register additional settings for optimization features
     */
    public function register_settings() {
        parent::register_settings();

        // Optimization settings
        register_setting('h3tm_general_settings', 'h3tm_use_optimized_operations');
        register_setting('h3tm_general_settings', 'h3tm_max_execution_time');
        register_setting('h3tm_general_settings', 'h3tm_progress_cleanup_interval');
    }
}