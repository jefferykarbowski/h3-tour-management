<?php
/**
 * Enhanced Admin functionality for H3 Tour Management
 * 
 * Improved security, performance, and user experience
 * 
 * @package H3_Tour_Management
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Admin_V2 {
    
    /**
     * Instance of tour manager
     * 
     * @var H3TM_Tour_Manager_V2
     */
    private $tour_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers with proper security
        $this->register_ajax_handlers();
        
        // Add admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        $handlers = array(
            'h3tm_test_email' => 'handle_test_email',
            'h3tm_upload_tour' => 'handle_upload_tour',
            'h3tm_upload_chunk' => 'handle_upload_chunk',
            'h3tm_process_upload' => 'handle_process_upload',
            'h3tm_delete_tour' => 'handle_delete_tour',
            'h3tm_rename_tour' => 'handle_rename_tour',
            'h3tm_update_tours_analytics' => 'handle_update_tours_analytics',
            'h3tm_get_tour_list' => 'handle_get_tour_list',
            'h3tm_test_analytics' => 'handle_test_analytics',
            'h3tm_clear_cache' => 'handle_clear_cache',
            'h3tm_view_logs' => 'handle_view_logs'
        );
        
        foreach ($handlers as $action => $method) {
            add_action('wp_ajax_' . $action, array($this, $method));
        }
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('3D Tours Management', 'h3-tour-management'),
            __('3D Tours', 'h3-tour-management'),
            'manage_options',
            'h3-tour-management',
            array($this, 'render_main_page'),
            'dashicons-camera-alt',
            30
        );
        
        // Submenu pages
        $submenus = array(
            array(
                'parent' => 'h3-tour-management',
                'title' => __('Dashboard', 'h3-tour-management'),
                'menu_title' => __('Dashboard', 'h3-tour-management'),
                'capability' => 'manage_options',
                'slug' => 'h3-tour-management',
                'callback' => array($this, 'render_main_page')
            ),
            array(
                'parent' => 'h3-tour-management',
                'title' => __('Tours', 'h3-tour-management'),
                'menu_title' => __('Tours', 'h3-tour-management'),
                'capability' => 'manage_options',
                'slug' => 'h3tm-tours',
                'callback' => array($this, 'render_tours_page')
            ),
            array(
                'parent' => 'h3-tour-management',
                'title' => __('Analytics', 'h3-tour-management'),
                'menu_title' => __('Analytics', 'h3-tour-management'),
                'capability' => 'manage_options',
                'slug' => 'h3tm-analytics',
                'callback' => array($this, 'render_analytics_page')
            ),
            array(
                'parent' => 'h3-tour-management',
                'title' => __('Email Settings', 'h3-tour-management'),
                'menu_title' => __('Email Settings', 'h3-tour-management'),
                'capability' => 'manage_options',
                'slug' => 'h3tm-email-settings',
                'callback' => array($this, 'render_email_settings_page')
            ),
            array(
                'parent' => 'h3-tour-management',
                'title' => __('Settings', 'h3-tour-management'),
                'menu_title' => __('Settings', 'h3-tour-management'),
                'capability' => 'manage_options',
                'slug' => 'h3tm-settings',
                'callback' => array($this, 'render_settings_page')
            ),
            array(
                'parent' => 'h3-tour-management',
                'title' => __('System Status', 'h3-tour-management'),
                'menu_title' => __('System Status', 'h3-tour-management'),
                'capability' => 'manage_options',
                'slug' => 'h3tm-status',
                'callback' => array($this, 'render_status_page')
            )
        );
        
        foreach ($submenus as $submenu) {
            add_submenu_page(
                $submenu['parent'],
                $submenu['title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['slug'],
                $submenu['callback']
            );
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'h3-tour-management') === false && strpos($hook, 'h3tm') === false) {
            return;
        }
        
        // Core dependencies
        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-util');
        
        // Lazy load Select2 only on pages that need it
        if (in_array($hook, array('3d-tours_page_h3tm-tours', '3d-tours_page_h3tm-email-settings'))) {
            wp_enqueue_script(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                array('jquery'),
                '4.1.0',
                true
            );
            wp_enqueue_style(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                array(),
                '4.1.0'
            );
        }
        
        // Plugin scripts and styles
        wp_enqueue_script(
            'h3tm-admin',
            H3TM_PLUGIN_URL . 'assets/js/admin-v2.js',
            array('jquery', 'wp-util'),
            H3TM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'h3tm-admin',
            H3TM_PLUGIN_URL . 'assets/css/admin-v2.css',
            array(),
            H3TM_VERSION
        );
        
        // Localize script with secure data
        wp_localize_script('h3tm-admin', 'h3tm_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('h3tm_ajax_nonce'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this tour?', 'h3-tour-management'),
                'uploading' => __('Uploading...', 'h3-tour-management'),
                'processing' => __('Processing...', 'h3-tour-management'),
                'error' => __('An error occurred', 'h3-tour-management'),
                'success' => __('Success!', 'h3-tour-management')
            ),
            'max_upload_size' => wp_max_upload_size(),
            'chunk_size' => 1048576, // 1MB chunks
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // General settings
        register_setting('h3tm_general_settings', 'h3tm_logging_enabled');
        register_setting('h3tm_general_settings', 'h3tm_log_level');
        register_setting('h3tm_general_settings', 'h3tm_cleanup_enabled');
        register_setting('h3tm_general_settings', 'h3tm_cleanup_days');
        
        // Analytics settings
        register_setting('h3tm_analytics_settings', 'h3tm_ga_measurement_id');
        register_setting('h3tm_analytics_settings', 'h3tm_analytics_enabled');
        register_setting('h3tm_analytics_settings', 'h3tm_track_interactions');
        register_setting('h3tm_analytics_settings', 'h3tm_track_time_spent');
        register_setting('h3tm_analytics_settings', 'h3tm_custom_analytics_code');
        
        // Email settings
        register_setting('h3tm_email_settings', 'h3tm_email_from_name');
        register_setting('h3tm_email_settings', 'h3tm_email_from_address');
        register_setting('h3tm_email_settings', 'h3tm_email_template');
    }
    
    /**
     * Render main dashboard page
     */
    public function render_main_page() {
        // Initialize tour manager
        $this->tour_manager = new H3TM_Tour_Manager_V2();
        
        // Get statistics
        $stats = $this->get_dashboard_stats();
        
        include H3TM_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
    
    /**
     * Render tours management page
     */
    public function render_tours_page() {
        $this->tour_manager = new H3TM_Tour_Manager_V2();
        
        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] !== '-1') {
            $this->handle_bulk_action($_POST['action'], $_POST['tours'] ?? array());
        }
        
        // Get tours with pagination
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        $tours = $this->tour_manager->get_all_tours(array(
            'page' => $page,
            'per_page' => $per_page,
            'include_meta' => true
        ));
        
        include H3TM_PLUGIN_DIR . 'templates/admin/tours.php';
    }
    
    /**
     * Handle AJAX test email request
     */
    public function handle_test_email() {
        // Verify request
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(__('Security verification failed', 'h3-tour-management'));
        }
        
        // Check rate limit
        if (!H3TM_Security::check_rate_limit('email', get_current_user_id())) {
            wp_send_json_error(__('Rate limit exceeded. Please try again later.', 'h3-tour-management'));
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error(__('Invalid user ID', 'h3-tour-management'));
        }
        
        try {
            $analytics = new H3TM_Analytics();
            
            // Try to send test email
            $analytics->send_test_email($user_id);
            $user = get_user_by('id', $user_id);
            
            H3TM_Logger::info('email', 'Test email sent', array(
                'user_id' => $user_id,
                'email' => $user->user_email
            ));
            
            wp_send_json_success(sprintf(
                __('Test email successfully sent to %s', 'h3-tour-management'),
                $user->user_email
            ));
            
        } catch (Exception $e) {
            H3TM_Logger::error('email', 'Test email failed', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error(__('Error sending email: ', 'h3-tour-management') . $e->getMessage());
        }
    }
    
    /**
     * Handle tour upload AJAX request
     */
    public function handle_upload_tour() {
        // Verify request
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(__('Security verification failed', 'h3-tour-management'));
        }
        
        $tour_name = sanitize_text_field($_POST['tour_name'] ?? '');
        
        if (empty($tour_name)) {
            wp_send_json_error(__('Tour name is required', 'h3-tour-management'));
        }
        
        if (!isset($_FILES['tour_file']) || $_FILES['tour_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload failed', 'h3-tour-management'));
        }
        
        $this->tour_manager = new H3TM_Tour_Manager_V2();
        $result = $this->tour_manager->upload_tour($tour_name, $_FILES['tour_file']);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'tour_url' => $result['tour_url'] ?? ''
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle chunked upload AJAX request
     */
    public function handle_upload_chunk() {
        // Verify request
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(__('Security verification failed', 'h3-tour-management'));
        }
        
        $chunk_number = intval($_POST['chunk_number'] ?? 0);
        $total_chunks = intval($_POST['total_chunks'] ?? 0);
        $unique_id = sanitize_text_field($_POST['unique_id'] ?? '');
        $file_name = sanitize_file_name($_POST['file_name'] ?? '');
        
        if (!$unique_id || !$file_name || !$chunk_number || !$total_chunks) {
            wp_send_json_error(__('Invalid chunk upload parameters', 'h3-tour-management'));
        }
        
        if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Chunk upload failed', 'h3-tour-management'));
        }
        
        try {
            // Create temp directory for chunks
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/h3-tours-temp';
            $upload_temp_dir = $temp_dir . '/' . $unique_id;
            
            if (!file_exists($upload_temp_dir)) {
                wp_mkdir_p($upload_temp_dir);
            }
            
            // Validate chunk
            $chunk_file = $upload_temp_dir . '/chunk_' . $chunk_number;
            
            // Save chunk using WordPress Filesystem API if possible
            global $wp_filesystem;
            if ($wp_filesystem) {
                $chunk_data = file_get_contents($_FILES['chunk']['tmp_name']);
                if (!$wp_filesystem->put_contents($chunk_file, $chunk_data, 0644)) {
                    throw new Exception(__('Failed to save chunk', 'h3-tour-management'));
                }
            } else {
                if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_file)) {
                    throw new Exception(__('Failed to save chunk', 'h3-tour-management'));
                }
            }
            
            wp_send_json_success(array(
                'chunk' => $chunk_number,
                'total' => $total_chunks,
                'message' => sprintf(
                    __('Chunk %d of %d uploaded', 'h3-tour-management'),
                    $chunk_number,
                    $total_chunks
                )
            ));
            
        } catch (Exception $e) {
            H3TM_Logger::error('upload', 'Chunk upload failed', array(
                'chunk' => $chunk_number,
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle process upload AJAX request
     */
    public function handle_process_upload() {
        // Verify request
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(__('Security verification failed', 'h3-tour-management'));
        }
        
        $tour_name = sanitize_text_field($_POST['tour_name'] ?? '');
        $unique_id = sanitize_text_field($_POST['unique_id'] ?? '');
        $file_name = sanitize_file_name($_POST['file_name'] ?? '');
        
        if (empty($tour_name) || empty($unique_id) || empty($file_name)) {
            wp_send_json_error(__('Invalid upload parameters', 'h3-tour-management'));
        }
        
        try {
            // Get temp directory
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/h3-tours-temp';
            $upload_temp_dir = $temp_dir . '/' . $unique_id;
            
            if (!file_exists($upload_temp_dir)) {
                throw new Exception(__('Upload directory not found', 'h3-tour-management'));
            }
            
            // Combine chunks
            $final_file = $upload_dir['basedir'] . '/h3-tours/' . $file_name;
            if (!file_exists(dirname($final_file))) {
                wp_mkdir_p(dirname($final_file));
            }
            
            $output = fopen($final_file, 'wb');
            if (!$output) {
                throw new Exception(__('Failed to create output file', 'h3-tour-management'));
            }
            
            // Get all chunk files
            $chunks = glob($upload_temp_dir . '/chunk_*');
            natsort($chunks);
            
            foreach ($chunks as $chunk_file) {
                $chunk_data = file_get_contents($chunk_file);
                if ($chunk_data === false) {
                    fclose($output);
                    unlink($final_file);
                    $this->cleanup_temp_dir($upload_temp_dir);
                    throw new Exception(__('Failed to read chunk', 'h3-tour-management'));
                }
                
                fwrite($output, $chunk_data);
                unlink($chunk_file);
            }
            
            fclose($output);
            
            // Clean up temp directory
            $this->cleanup_temp_dir($upload_temp_dir);
            
            // Process the uploaded file
            $this->tour_manager = new H3TM_Tour_Manager_V2();
            $file_info = array(
                'name' => $file_name,
                'tmp_name' => $final_file,
                'size' => filesize($final_file),
                'error' => UPLOAD_ERR_OK
            );
            
            $result = $this->tour_manager->upload_tour($tour_name, $file_info, true);
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'tour_url' => $result['tour_url'] ?? ''
                ));
            } else {
                // Clean up the final file if processing failed
                if (file_exists($final_file)) {
                    unlink($final_file);
                }
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            H3TM_Logger::error('upload', 'Process upload failed', array(
                'tour_name' => $tour_name,
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Clean up temporary directory
     * 
     * @param string $dir Directory path
     */
    private function cleanup_temp_dir($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                unlink($dir . '/' . $file);
            }
            rmdir($dir);
        }
    }
    
    /**
     * Handle delete tour AJAX request
     */
    public function handle_delete_tour() {
        // Verify request
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(__('Security verification failed', 'h3-tour-management'));
        }
        
        $tour_name = sanitize_text_field($_POST['tour_name'] ?? '');
        
        if (empty($tour_name)) {
            wp_send_json_error(__('Tour name is required', 'h3-tour-management'));
        }
        
        $this->tour_manager = new H3TM_Tour_Manager_V2();
        $result = $this->tour_manager->delete_tour($tour_name);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle rename tour AJAX request
     */
    public function handle_rename_tour() {
        // Verify request
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(__('Security verification failed', 'h3-tour-management'));
        }
        
        $old_name = sanitize_text_field($_POST['old_name'] ?? '');
        $new_name = sanitize_text_field($_POST['new_name'] ?? '');
        
        if (empty($old_name) || empty($new_name)) {
            wp_send_json_error(__('Both old and new names are required', 'h3-tour-management'));
        }
        
        $this->tour_manager = new H3TM_Tour_Manager_V2();
        $result = $this->tour_manager->rename_tour($old_name, $new_name);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle test analytics connection
     */
    public function handle_test_analytics() {
        // Verify request
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(__('Security verification failed', 'h3-tour-management'));
        }
        
        try {
            $test_result = H3TM_Analytics_Service::test_connection();
            
            if ($test_result['success']) {
                wp_send_json_success($test_result);
            } else {
                wp_send_json_error($test_result);
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Test failed: ', 'h3-tour-management') . $e->getMessage()
            ));
        }
    }
    
    /**
     * Handle clear cache request
     */
    public function handle_clear_cache() {
        // Verify request
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(__('Security verification failed', 'h3-tour-management'));
        }
        
        $cache_type = sanitize_text_field($_POST['cache_type'] ?? 'all');
        
        try {
            if ($cache_type === 'analytics') {
                H3TM_Analytics_Service::clear_cache();
            } else {
                // Clear all caches
                H3TM_Analytics_Service::clear_cache();
                wp_cache_flush();
            }
            
            wp_send_json_success(__('Cache cleared successfully', 'h3-tour-management'));
            
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to clear cache: ', 'h3-tour-management') . $e->getMessage());
        }
    }
    
    /**
     * Handle view logs request
     */
    public function handle_view_logs() {
        // Verify request
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_ajax_nonce')) {
            wp_send_json_error(__('Security verification failed', 'h3-tour-management'));
        }
        
        $context = sanitize_text_field($_POST['context'] ?? '');
        $level = sanitize_text_field($_POST['level'] ?? '');
        $limit = intval($_POST['limit'] ?? 100);
        
        try {
            $logs = H3TM_Logger::get_recent_logs($context ?: null, $level ?: null, $limit);
            
            wp_send_json_success(array(
                'logs' => $logs,
                'count' => count($logs)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to retrieve logs: ', 'h3-tour-management') . $e->getMessage());
        }
    }
    
    /**
     * Get dashboard statistics
     * 
     * @return array Statistics
     */
    private function get_dashboard_stats() {
        $stats = array(
            'total_tours' => 0,
            'total_users' => 0,
            'emails_sent_today' => 0,
            'storage_used' => 0,
            'recent_activity' => array()
        );
        
        // Get tour count
        $tours = $this->tour_manager->get_all_tours(array('include_meta' => false));
        $stats['total_tours'] = count($tours);
        
        // Calculate storage
        foreach ($tours as $tour) {
            $stats['storage_used'] += $tour['size'];
        }
        
        // Get user count with tours
        $users = get_users(array(
            'meta_key' => 'h3tm_tours',
            'meta_compare' => 'EXISTS'
        ));
        $stats['total_users'] = count($users);
        
        // Get email stats
        global $wpdb;
        $table = $wpdb->prefix . 'h3tm_email_queue';
        $stats['emails_sent_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'sent' AND DATE(sent_at) = %s",
            current_time('Y-m-d')
        ));
        
        // Get recent activity
        $activity_table = $wpdb->prefix . 'h3tm_activity_log';
        $stats['recent_activity'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $activity_table ORDER BY created_at DESC LIMIT %d",
            5
        ));
        
        // Get database stats
        $stats['db_stats'] = H3TM_Database::get_stats();
        
        // Get log stats
        $stats['log_stats'] = H3TM_Logger::get_stats();
        
        return $stats;
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check for system issues
        $issues = $this->check_system_issues();
        
        if (!empty($issues)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('H3 Tour Management - System Issues Detected:', 'h3-tour-management') . '</strong></p>';
            echo '<ul>';
            foreach ($issues as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        // Check for success messages
        if (isset($_GET['h3tm_message'])) {
            $message = '';
            switch ($_GET['h3tm_message']) {
                case 'tour_uploaded':
                    $message = __('Tour uploaded successfully!', 'h3-tour-management');
                    break;
                case 'tour_deleted':
                    $message = __('Tour deleted successfully!', 'h3-tour-management');
                    break;
                case 'settings_saved':
                    $message = __('Settings saved successfully!', 'h3-tour-management');
                    break;
            }
            
            if ($message) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . esc_html($message) . '</p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Check for system issues
     * 
     * @return array List of issues
     */
    private function check_system_issues() {
        $issues = array();
        
        // Check Google API dependencies
        if (!file_exists(ABSPATH . 'vendor/autoload.php')) {
            $issues[] = __('Google API client library not found. Analytics features will be limited.', 'h3-tour-management');
        }
        
        if (!file_exists(ABSPATH . 'service-account-credentials.json')) {
            $issues[] = __('Google Analytics credentials not found. Analytics emails will not include data.', 'h3-tour-management');
        }
        
        // Check file permissions
        $upload_dir = wp_upload_dir();
        $required_dirs = array(
            $upload_dir['basedir'] . '/h3-tours',
            $upload_dir['basedir'] . '/h3-tours-temp',
            $upload_dir['basedir'] . '/h3tm-logs'
        );
        
        foreach ($required_dirs as $dir) {
            if (file_exists($dir) && !is_writable($dir)) {
                $issues[] = sprintf(
                    __('Directory not writable: %s', 'h3-tour-management'),
                    basename($dir)
                );
            }
        }
        
        // Check max upload size
        $max_upload = wp_max_upload_size();
        if ($max_upload < 104857600) { // Less than 100MB
            $issues[] = sprintf(
                __('Maximum upload size is low: %s. Consider increasing it for large tours.', 'h3-tour-management'),
                size_format($max_upload)
            );
        }
        
        // Check if cron is working
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $issues[] = __('WordPress cron is disabled. Scheduled emails may not be sent.', 'h3-tour-management');
        }
        
        return $issues;
    }
    
    /**
     * Handle bulk actions
     * 
     * @param string $action Action to perform
     * @param array $tours Selected tours
     */
    private function handle_bulk_action($action, $tours) {
        if (empty($tours) || !is_array($tours)) {
            return;
        }
        
        $count = 0;
        $this->tour_manager = new H3TM_Tour_Manager_V2();
        
        switch ($action) {
            case 'delete':
                foreach ($tours as $tour) {
                    $result = $this->tour_manager->delete_tour($tour);
                    if ($result['success']) {
                        $count++;
                    }
                }
                
                if ($count > 0) {
                    add_settings_error(
                        'h3tm_messages',
                        'h3tm_bulk_delete',
                        sprintf(
                            _n('%d tour deleted.', '%d tours deleted.', $count, 'h3-tour-management'),
                            $count
                        ),
                        'success'
                    );
                }
                break;
        }
    }
}