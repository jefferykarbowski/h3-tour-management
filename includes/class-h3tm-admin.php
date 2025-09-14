<?php
/**
 * Admin functionality
 */
class H3TM_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_h3tm_test_email', array($this, 'handle_test_email'));
        add_action('wp_ajax_h3tm_upload_tour', array($this, 'handle_upload_tour'));
        add_action('wp_ajax_h3tm_upload_chunk', array($this, 'handle_upload_chunk'));
        add_action('wp_ajax_h3tm_process_upload', array($this, 'handle_process_upload'));
        add_action('wp_ajax_h3tm_delete_tour', array($this, 'handle_delete_tour'));
        add_action('wp_ajax_h3tm_rename_tour', array($this, 'handle_rename_tour'));
        // add_action('wp_ajax_h3tm_update_tours_analytics', array($this, 'handle_update_tours_analytics')); // Disabled with analytics settings
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            __('3D Tours Management', 'h3-tour-management'),
            __('3D Tours', 'h3-tour-management'),
            'manage_options',
            'h3-tour-management',
            array($this, 'render_main_page'),
            'dashicons-camera-alt',
            30
        );
        
        add_submenu_page(
            'h3-tour-management',
            __('Manage Tours', 'h3-tour-management'),
            __('Manage Tours', 'h3-tour-management'),
            'manage_options',
            'h3-tour-management',
            array($this, 'render_main_page')
        );
        
        add_submenu_page(
            'h3-tour-management',
            __('Email Settings', 'h3-tour-management'),
            __('Email Settings', 'h3-tour-management'),
            'manage_options',
            'h3tm-email-settings',
            array($this, 'render_email_settings_page')
        );
        
        add_submenu_page(
            'h3-tour-management',
            __('Analytics', 'h3-tour-management'),
            __('Analytics', 'h3-tour-management'),
            'manage_options',
            'h3tm-analytics',
            array($this, 'render_analytics_page')
        );
        
        // Analytics Settings page removed - not needed without PHP index files
        // add_submenu_page(
        //     'h3-tour-management',
        //     __('Analytics Settings', 'h3-tour-management'),
        //     __('Analytics Settings', 'h3-tour-management'),
        //     'manage_options',
        //     'h3tm-analytics-settings',
        //     array($this, 'render_analytics_settings_page')
        // );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'h3-tour-management') === false && strpos($hook, 'h3tm') === false) {
            return;
        }
        
        // Enqueue Select2
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        
        // Enqueue custom admin scripts
        wp_enqueue_script('h3tm-admin', H3TM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'select2'), H3TM_VERSION, true);
        wp_enqueue_style('h3tm-admin', H3TM_PLUGIN_URL . 'assets/css/admin.css', array(), H3TM_VERSION);
        
        // Localize script
        wp_localize_script('h3tm-admin', 'h3tm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('h3tm_ajax_nonce')
        ));
    }
    
    /**
     * Render main tours management page
     */
    public function render_main_page() {
        $tour_manager = new H3TM_Tour_Manager();
        $tours = $tour_manager->get_all_tours();
        ?>
        <div class="wrap">
            <h1><?php _e('3D Tours Management', 'h3-tour-management'); ?></h1>
            
            <div class="h3tm-admin-container">
                <div class="h3tm-section">
                    <h2><?php _e('Upload New Tour', 'h3-tour-management'); ?></h2>
                    <form id="h3tm-upload-form" enctype="multipart/form-data">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="tour_name"><?php _e('Tour Name', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="tour_name" name="tour_name" class="regular-text" required />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="tour_file"><?php _e('Tour ZIP File', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <input type="file" id="tour_file" name="tour_file" accept=".zip" required />
                                    <p class="description">
                                        <?php _e('Upload a ZIP file containing the tour files.', 'h3-tour-management'); ?><br>
                                        <?php _e('Large files will be uploaded in chunks (1MB each) to avoid server limits.', 'h3-tour-management'); ?>
                                    </p>
                                    <div id="file-info" style="margin-top: 5px; display: none;">
                                        <strong><?php _e('File:', 'h3-tour-management'); ?></strong> <span id="file-name"></span><br>
                                        <strong><?php _e('Size:', 'h3-tour-management'); ?></strong> <span id="file-size"></span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Upload Tour', 'h3-tour-management'); ?></button>
                            <span class="spinner"></span>
                        </p>
                    </form>
                    <div id="upload-result" class="notice" style="display:none;"></div>
                </div>
                
                <div class="h3tm-section">
                    <h2><?php _e('Existing Tours', 'h3-tour-management'); ?></h2>
                    <?php if (empty($tours)) : ?>
                        <p><?php _e('No tours found.', 'h3-tour-management'); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Tour Name', 'h3-tour-management'); ?></th>
                                    <th><?php _e('URL', 'h3-tour-management'); ?></th>
                                    <th><?php _e('Actions', 'h3-tour-management'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tours as $tour) : ?>
                                    <tr data-tour="<?php echo esc_attr($tour); ?>">
                                        <td><?php echo esc_html($tour); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url(site_url('/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour))); ?>" target="_blank">
                                                <?php echo esc_url(site_url('/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour))); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <button class="button rename-tour" data-tour="<?php echo esc_attr($tour); ?>">
                                                <?php _e('Rename', 'h3-tour-management'); ?>
                                            </button>
                                            <button class="button delete-tour" data-tour="<?php echo esc_attr($tour); ?>">
                                                <?php _e('Delete', 'h3-tour-management'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="h3tm-section">
                    <h2><?php _e('Test Email', 'h3-tour-management'); ?></h2>
                    <p><?php _e('Send a test analytics email to verify the system is working correctly.', 'h3-tour-management'); ?></p>
                    <form id="h3tm-test-email-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="test_user_id"><?php _e('Select User', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <select id="test_user_id" name="test_user_id" class="h3tm-user-select">
                                        <option value=""><?php _e('-- Select a User --', 'h3-tour-management'); ?></option>
                                        <?php
                                        $users = get_users();
                                        foreach ($users as $user) {
                                            $user_tours = get_user_meta($user->ID, 'h3tm_tours', true);
                                            if (!empty($user_tours)) {
                                                echo '<option value="' . $user->ID . '">' . esc_html($user->display_name . ' (' . $user->user_email . ')') . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Send Test Email', 'h3-tour-management'); ?></button>
                            <span class="spinner"></span>
                        </p>
                    </form>
                    <div id="test-email-result" class="notice" style="display:none;"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render email settings page
     */
    public function render_email_settings_page() {
        if (isset($_POST['submit'])) {
            update_option('h3tm_email_from_name', sanitize_text_field($_POST['email_from_name']));
            update_option('h3tm_email_from_address', sanitize_email($_POST['email_from_address']));
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'h3-tour-management') . '</p></div>';
        }
        
        $from_name = get_option('h3tm_email_from_name', 'H3 Photography');
        $from_address = get_option('h3tm_email_from_address', get_option('admin_email'));
        ?>
        <div class="wrap">
            <h1><?php _e('Email Settings', 'h3-tour-management'); ?></h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="email_from_name"><?php _e('From Name', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="email_from_name" name="email_from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="email_from_address"><?php _e('From Email Address', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="email_from_address" name="email_from_address" value="<?php echo esc_attr($from_address); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Analytics Overview', 'h3-tour-management'); ?></h1>
            
            <div class="h3tm-analytics-info">
                <h2><?php _e('Analytics Information', 'h3-tour-management'); ?></h2>
                <p><strong><?php _e('GA4 Property ID:', 'h3-tour-management'); ?></strong> properties/491286260</p>
                <p><strong><?php _e('Measurement ID:', 'h3-tour-management'); ?></strong> G-08Q1M637NJ</p>
                <p><strong><?php _e('Next Scheduled Email:', 'h3-tour-management'); ?></strong> 
                    <?php 
                    $next_run = wp_next_scheduled('h3tm_analytics_cron');
                    if ($next_run) {
                        echo date('Y-m-d H:i:s', $next_run) . ' (' . human_time_diff($next_run) . ' from now)';
                    } else {
                        echo __('Not scheduled', 'h3-tour-management');
                    }
                    ?>
                </p>
            </div>
            
            <div class="h3tm-analytics-shortcode">
                <h2><?php _e('Display Analytics', 'h3-tour-management'); ?></h2>
                <p><?php _e('Use the following shortcode to display tour analytics on any page:', 'h3-tour-management'); ?></p>
                <code>[tour_analytics_display]</code>
            </div>
            
            <div class="h3tm-analytics-info">
                <h2><?php _e('Email Configuration Status', 'h3-tour-management'); ?></h2>
                <?php
                $email_from = get_option('h3tm_email_from_address', get_option('admin_email'));
                $email_name = get_option('h3tm_email_from_name', 'H3 Photography');
                ?>
                <p><strong><?php _e('From Email:', 'h3-tour-management'); ?></strong> <?php echo esc_html($email_from); ?></p>
                <p><strong><?php _e('From Name:', 'h3-tour-management'); ?></strong> <?php echo esc_html($email_name); ?></p>
                
                <?php
                // Check for Google API dependencies
                $root = realpath($_SERVER["DOCUMENT_ROOT"]);
                $autoload_exists = file_exists($root . '/vendor/autoload.php');
                $credentials_exists = file_exists($root . '/service-account-credentials.json');
                ?>
                
                <h3><?php _e('Analytics API Status', 'h3-tour-management'); ?></h3>
                <p>
                    <strong><?php _e('Google API Library:', 'h3-tour-management'); ?></strong> 
                    <?php if ($autoload_exists) : ?>
                        <span style="color: green;">✓ <?php _e('Found', 'h3-tour-management'); ?></span>
                    <?php else : ?>
                        <span style="color: red;">✗ <?php _e('Not found', 'h3-tour-management'); ?></span>
                        <br><small><?php _e('Install via Composer: composer require google/apiclient', 'h3-tour-management'); ?></small>
                    <?php endif; ?>
                </p>
                <p>
                    <strong><?php _e('Service Account Credentials:', 'h3-tour-management'); ?></strong> 
                    <?php if ($credentials_exists) : ?>
                        <span style="color: green;">✓ <?php _e('Found', 'h3-tour-management'); ?></span>
                    <?php else : ?>
                        <span style="color: red;">✗ <?php _e('Not found', 'h3-tour-management'); ?></span>
                        <br><small><?php printf(__('Expected at: %s', 'h3-tour-management'), $root . '/service-account-credentials.json'); ?></small>
                    <?php endif; ?>
                </p>
                
                <?php if (!$autoload_exists || !$credentials_exists) : ?>
                    <div class="notice notice-warning inline" style="margin-top: 10px;">
                        <p><?php _e('Note: Test emails will work without these dependencies, but full analytics emails require both the Google API library and credentials.', 'h3-tour-management'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle test email AJAX request
     */
    public function handle_test_email() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id) {
            wp_send_json_error(__('Invalid user ID', 'h3-tour-management'));
        }
        
        try {
            // Real analytics only - no fallback
            $analytics = new H3TM_Analytics();
            $analytics->send_analytics_for_user($user_id);
            $user = get_user_by('id', $user_id);
            wp_send_json_success(sprintf(__('Analytics email successfully sent to %s', 'h3-tour-management'), $user->user_email));
        } catch (Exception $e) {
            // Return the actual error instead of falling back
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle tour upload (legacy - kept for compatibility)
     */
    public function handle_upload_tour() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $tour_name = sanitize_text_field($_POST['tour_name']);
        
        if (empty($tour_name)) {
            wp_send_json_error(__('Tour name is required', 'h3-tour-management'));
        }
        
        if (!isset($_FILES['tour_file']) || $_FILES['tour_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload failed', 'h3-tour-management'));
        }
        
        $tour_manager = new H3TM_Tour_Manager();
        $result = $tour_manager->upload_tour($tour_name, $_FILES['tour_file']);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle chunked upload
     */
    public function handle_upload_chunk() {
        // Increase limits for large uploads
        @ini_set('max_execution_time', 600);
        @ini_set('memory_limit', '512M');
        
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $chunk_number = intval($_POST['chunk_number']);
        $total_chunks = intval($_POST['total_chunks']);
        $unique_id = sanitize_text_field($_POST['unique_id']);
        $file_name = sanitize_file_name($_POST['file_name']);
        
        // Better error handling for chunk upload
        if (!isset($_FILES['chunk'])) {
            error_log('H3TM Upload Error - Chunk ' . $chunk_number . ': No chunk data received');
            wp_send_json_error(__('No chunk data received', 'h3-tour-management'));
        }
        
        if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'Chunk exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'Chunk exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'Chunk was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No chunk was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write chunk to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
            );
            $error_msg = isset($error_messages[$_FILES['chunk']['error']]) 
                ? $error_messages[$_FILES['chunk']['error']] 
                : 'Unknown upload error: ' . $_FILES['chunk']['error'];
            
            error_log('H3TM Upload Error - Chunk ' . $chunk_number . ': ' . $error_msg);
            wp_send_json_error($error_msg);
        }
        
        // Create temp directory for chunks
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/h3-tours-temp';
        if (!file_exists($temp_dir)) {
            if (!wp_mkdir_p($temp_dir)) {
                error_log('H3TM Upload Error: Failed to create temp directory: ' . $temp_dir);
                wp_send_json_error(__('Failed to create temp directory', 'h3-tour-management'));
            }
        }
        
        // Check disk space (need at least 100MB free) - use h3panos directory instead of uploads
        $h3panos_path = ABSPATH . 'h3panos';
        $check_path = file_exists($h3panos_path) ? $h3panos_path : $upload_dir['basedir'];
        $free_space = @disk_free_space($check_path);
        
        // Create debug info for admin display
        $debug_info = array(
            'check_path' => $check_path,
            'h3panos_exists' => file_exists($h3panos_path),
            'check_path_exists' => file_exists($check_path),
            'free_space_mb' => $free_space !== false ? round($free_space / 1024 / 1024) : 'UNKNOWN',
            'required_mb' => 100,
            'abspath' => ABSPATH,
            'upload_basedir' => $upload_dir['basedir']
        );
        
        if ($free_space !== false && $free_space < 100 * 1024 * 1024) {
            wp_send_json_error(array(
                'message' => __('Insufficient disk space on server', 'h3-tour-management'),
                'debug' => $debug_info
            ));
        }
        
        // Create unique directory for this upload
        $upload_temp_dir = $temp_dir . '/' . $unique_id;
        if (!file_exists($upload_temp_dir)) {
            if (!wp_mkdir_p($upload_temp_dir)) {
                error_log('H3TM Upload Error: Failed to create upload directory: ' . $upload_temp_dir);
                wp_send_json_error(__('Failed to create upload directory', 'h3-tour-management'));
            }
        }
        
        // Save chunk with padded number for proper sorting
        $chunk_file = $upload_temp_dir . '/chunk_' . str_pad($chunk_number, 6, '0', STR_PAD_LEFT);
        if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_file)) {
            error_log('H3TM Upload Error: Failed to move chunk ' . $chunk_number . ' to ' . $chunk_file);
            wp_send_json_error(__('Failed to save chunk ' . $chunk_number, 'h3-tour-management'));
        }
        
        // Log progress for large uploads
        if ($chunk_number % 100 === 0 || $chunk_number === $total_chunks - 1) {
            error_log('H3TM Upload Progress: Chunk ' . $chunk_number . ' of ' . $total_chunks . ' completed');
        }
        
        wp_send_json_success(array(
            'chunk' => $chunk_number,
            'total' => $total_chunks,
            'free_space' => $free_space !== false ? round($free_space / 1024 / 1024) . 'MB' : 'unknown'
        ));
    }
    
    /**
     * Process uploaded chunks
     */
    public function handle_process_upload() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $tour_name = sanitize_text_field($_POST['tour_name']);
        $unique_id = sanitize_text_field($_POST['unique_id']);
        $file_name = sanitize_file_name($_POST['file_name']);
        
        if (empty($tour_name)) {
            wp_send_json_error(__('Tour name is required', 'h3-tour-management'));
        }
        
        // Get temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/h3-tours-temp';
        $upload_temp_dir = $temp_dir . '/' . $unique_id;
        
        if (!file_exists($upload_temp_dir)) {
            wp_send_json_error(__('Upload directory not found', 'h3-tour-management'));
        }
        
        // Combine chunks
        $final_file = $upload_dir['basedir'] . '/h3-tours/' . $file_name;
        if (!file_exists(dirname($final_file))) {
            wp_mkdir_p(dirname($final_file));
        }
        
        $output = fopen($final_file, 'wb');
        if (!$output) {
            wp_send_json_error(__('Failed to create output file', 'h3-tour-management'));
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
                wp_send_json_error(__('Failed to read chunk', 'h3-tour-management'));
            }
            
            fwrite($output, $chunk_data);
            unlink($chunk_file);
        }
        
        fclose($output);
        
        // Clean up temp directory
        $this->cleanup_temp_dir($upload_temp_dir);
        
        // Process the uploaded file
        $tour_manager = new H3TM_Tour_Manager();
        $file_info = array(
            'name' => $file_name,
            'tmp_name' => $final_file,
            'error' => UPLOAD_ERR_OK
        );
        
        // Use the existing upload_tour method with the combined file
        $result = $tour_manager->upload_tour($tour_name, $file_info, true);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            // Clean up the final file if processing failed
            if (file_exists($final_file)) {
                unlink($final_file);
            }
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Clean up temporary directory
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
     * Handle tour deletion
     */
    public function handle_delete_tour() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $tour_name = sanitize_text_field($_POST['tour_name']);
        
        $tour_manager = new H3TM_Tour_Manager();
        $result = $tour_manager->delete_tour($tour_name);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Handle tour rename
     */
    public function handle_rename_tour() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $old_name = sanitize_text_field($_POST['old_name']);
        $new_name = sanitize_text_field($_POST['new_name']);
        
        $tour_manager = new H3TM_Tour_Manager();
        $result = $tour_manager->rename_tour($old_name, $new_name);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Render analytics settings page
     * DISABLED - Not needed without PHP index file creation
     */
    /*
    public function render_analytics_settings_page() {
        if (isset($_POST['submit'])) {
            update_option('h3tm_ga_measurement_id', sanitize_text_field($_POST['ga_measurement_id']));
            update_option('h3tm_analytics_enabled', isset($_POST['analytics_enabled']) ? '1' : '0');
            update_option('h3tm_track_interactions', isset($_POST['track_interactions']) ? '1' : '0');
            update_option('h3tm_track_time_spent', isset($_POST['track_time_spent']) ? '1' : '0');
            update_option('h3tm_custom_analytics_code', wp_kses_post($_POST['custom_analytics_code']));
            
            echo '<div class="notice notice-success"><p>' . __('Analytics settings saved.', 'h3-tour-management') . '</p></div>';
        }
        
        $ga_measurement_id = get_option('h3tm_ga_measurement_id', 'G-08Q1M637NJ');
        $analytics_enabled = get_option('h3tm_analytics_enabled', '1');
        $track_interactions = get_option('h3tm_track_interactions', '1');
        $track_time_spent = get_option('h3tm_track_time_spent', '1');
        $custom_analytics_code = get_option('h3tm_custom_analytics_code', '');
        ?>
        <div class="wrap">
            <h1><?php _e('Analytics Settings', 'h3-tour-management'); ?></h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ga_measurement_id"><?php _e('GA4 Measurement ID', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ga_measurement_id" name="ga_measurement_id" value="<?php echo esc_attr($ga_measurement_id); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your Google Analytics 4 Measurement ID (e.g., G-XXXXXXXXXX)', 'h3-tour-management'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Analytics Options', 'h3-tour-management'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="analytics_enabled" value="1" <?php checked($analytics_enabled, '1'); ?> />
                                    <?php _e('Enable Analytics Tracking', 'h3-tour-management'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="track_interactions" value="1" <?php checked($track_interactions, '1'); ?> />
                                    <?php _e('Track Panorama Interactions', 'h3-tour-management'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="track_time_spent" value="1" <?php checked($track_time_spent, '1'); ?> />
                                    <?php _e('Track Time Spent on Tours', 'h3-tour-management'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="custom_analytics_code"><?php _e('Custom Analytics Code', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <textarea id="custom_analytics_code" name="custom_analytics_code" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($custom_analytics_code); ?></textarea>
                            <p class="description"><?php _e('Add custom JavaScript code for additional analytics tracking. This code will be inserted into every tour page.', 'h3-tour-management'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Analytics Code Preview', 'h3-tour-management'); ?></h2>
                <div style="background: #f1f1f1; padding: 15px; border: 1px solid #ddd; font-family: monospace; font-size: 12px; overflow-x: auto;">
                    <pre><?php echo esc_html($this->get_analytics_code_preview()); ?></pre>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="h3tm-section" style="margin-top: 30px;">
                <h2><?php _e('Update Existing Tours', 'h3-tour-management'); ?></h2>
                <p><?php _e('If you have existing tours that need their analytics code updated, click the button below.', 'h3-tour-management'); ?></p>
                <button type="button" id="update-tours-analytics" class="button button-secondary">
                    <?php _e('Update Analytics Code in All Tours', 'h3-tour-management'); ?>
                </button>
                <span class="spinner" style="float: none;"></span>
                <div id="update-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#update-tours-analytics').on('click', function() {
                var $button = $(this);
                var $spinner = $button.next('.spinner');
                var $result = $('#update-result');
                
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $result.html('');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'h3tm_update_tours_analytics',
                        nonce: '<?php echo wp_create_nonce('h3tm_update_analytics'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                        } else {
                            $result.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error"><p>An error occurred.</p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }
    */
    
    /**
     * Get analytics code preview
     * DISABLED - Part of analytics settings page
     */
    /*
    private function get_analytics_code_preview() {
        $ga_measurement_id = get_option('h3tm_ga_measurement_id', 'G-08Q1M637NJ');
        $analytics_enabled = get_option('h3tm_analytics_enabled', '1');
        
        $preview = "<!-- Google Analytics -->\n";
        if ($analytics_enabled) {
            $preview .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $ga_measurement_id . '"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag(\'js\', new Date());
  
  gtag(\'config\', \'' . $ga_measurement_id . '\', {
    \'page_title\': \'[TOUR_TITLE]\',
    \'page_path\': \'/h3panos/[TOUR_NAME]/\'
  });
  
  // Track tour view event
  gtag(\'event\', \'tour_view\', {
    \'tour_name\': \'[TOUR_NAME]\',
    \'tour_title\': \'[TOUR_TITLE]\'
  });
</script>';
        }
        $preview .= "\n<!-- End Google Analytics -->";
        
        return $preview;
    }
    */
    
    /**
     * Handle update tours analytics AJAX request
     * DISABLED - Part of analytics settings page
     */
    /*
    public function handle_update_tours_analytics() {
        check_ajax_referer('h3tm_update_analytics', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $tour_manager = new H3TM_Tour_Manager();
        $tours = $tour_manager->get_all_tours();
        $updated = 0;
        $failed = 0;
        
        foreach ($tours as $tour) {
            if ($tour_manager->update_tour_analytics($tour)) {
                $updated++;
            } else {
                $failed++;
            }
        }
        
        if ($failed > 0) {
            wp_send_json_error(sprintf(
                __('Updated %d tours successfully, %d failed.', 'h3-tour-management'),
                $updated,
                $failed
            ));
        } else {
            wp_send_json_success(sprintf(
                __('Successfully updated analytics code in %d tours.', 'h3-tour-management'),
                $updated
            ));
        }
    }
    */
}