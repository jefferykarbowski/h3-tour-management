<?php
/**
 * Admin functionality
 */
class H3TM_Admin {

    private $use_optimized = false;

    public function __construct() {
        // Enable backend optimizations if available
        if (class_exists('H3TM_Tour_Manager_Optimized')) {
            $this->use_optimized = true;
        }
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_h3tm_test_email', array($this, 'handle_test_email'));
        add_action('wp_ajax_h3tm_upload_tour', array($this, 'handle_upload_tour'));
        // S3 AJAX handlers are handled by H3TM_S3_Simple class:
        // - wp_ajax_h3tm_get_s3_presigned_url
        // - wp_ajax_h3tm_process_s3_upload
        // - wp_ajax_h3tm_test_s3_connection
        add_action('wp_ajax_h3tm_delete_tour', array($this, 'handle_delete_tour'));
        add_action('wp_ajax_h3tm_rename_tour', array($this, 'handle_rename_tour'));
        // add_action('wp_ajax_h3tm_update_tours_analytics', array($this, 'handle_update_tours_analytics')); // Disabled with analytics settings
    }

    /**
     * Get tour manager instance (optimized if available)
     */
    private function get_tour_manager() {
        if ($this->use_optimized && class_exists('H3TM_Tour_Manager_Optimized')) {
            return new H3TM_Tour_Manager_Optimized();
        }
        return new H3TM_Tour_Manager();
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

        add_submenu_page(
            'h3-tour-management',
            __('S3 Upload Settings', 'h3-tour-management'),
            __('S3 Settings', 'h3-tour-management'),
            'manage_options',
            'h3tm-s3-settings',
            array($this, 'render_s3_settings_page')
        );

        add_submenu_page(
            'h3-tour-management',
            __('URL Handler Settings', 'h3-tour-management'),
            __('URL Handlers', 'h3-tour-management'),
            'manage_options',
            'h3tm-url-handlers',
            array($this, 'render_url_handlers_page')
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
        
        // Get S3 configuration
        $s3_integration = new H3TM_S3_Simple();
        $s3_config = $s3_integration->get_s3_config();
        $s3_enabled = true; // Always enabled in S3-only system

        // Debug S3 configuration
        error_log('H3TM S3 Config Debug: configured=' . ($s3_config['configured'] ? 'true' : 'false') .
                  ', enabled=' . ($s3_enabled ? 'true' : 'false') .
                  ', bucket=' . $s3_config['bucket']);

        // Localize script
        wp_localize_script('h3tm-admin', 'h3tm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('h3tm_ajax_nonce'),
            's3_threshold_mb' => $s3_config['threshold_mb'],
            's3_configured' => $s3_config['configured'] && $s3_enabled,
            's3_enabled' => $s3_enabled,
            's3_bucket' => $s3_config['bucket'],
            's3_region' => $s3_config['region'],
            'debug_s3_check' => array(
                'configured' => $s3_config['configured'],
                'enabled' => $s3_enabled,
                'combined' => $s3_config['configured'] && $s3_enabled
            )
        ));
    }
    
    /**
     * Auto-register existing tours that Lambda has processed
     */
    private function auto_register_existing_tours() {
        $s3_tours = get_option('h3tm_s3_tours', array());

        // Known tours that Lambda has processed
        $known_tours = array('Bee Cave', 'Sugar Land', 'Onion Creek', 'Cedar Park');

        foreach ($known_tours as $tour_name) {
            if (!isset($s3_tours[$tour_name])) {
                $s3_tours[$tour_name] = array(
                    'url' => '',
                    'created' => current_time('mysql'),
                    'status' => 'completed',
                    'original_name' => str_replace(' ', '-', $tour_name)
                );
            }
        }

        update_option('h3tm_s3_tours', $s3_tours);
    }

    /**
     * Get tours from S3 instead of local directory
     */
    private function get_s3_tours() {
        // Simple approach: get tours from database and recent uploads
        $s3_tours = get_option('h3tm_s3_tours', array());
        $recent_uploads = get_transient('h3tm_recent_uploads') ?: array();

        // Register any tours that aren't in the registry yet
        $this->auto_register_existing_tours();

        // Get tours from registry
        $s3_tours = get_option('h3tm_s3_tours', array());

        // Combine all sources
        $all_tours = array_keys($s3_tours);
        $all_tours = array_merge($all_tours, $recent_uploads);
        $all_tours = array_merge($all_tours, $known_s3_tours);

        // Also include any existing local tours for backward compatibility
        $tour_manager = $this->get_tour_manager();
        $local_tours = $tour_manager->get_all_tours();
        $all_tours = array_merge($all_tours, $local_tours);

        return array_unique($all_tours);
    }

    /**
     * Render main tours management page
     */
    public function render_main_page() {
        $tour_manager = $this->get_tour_manager();

        // Get tours from S3 instead of local directory
        $tours = $this->get_s3_tours();
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
                                        <?php
                                        $s3_integration = new H3TM_S3_Simple();
                                        $s3_config = $s3_integration->get_s3_config();
                                        $s3_configured = $s3_config['configured'] && get_option('h3tm_s3_enabled', '0') === '1';
                                        if ($s3_configured) {
                                            _e('All files will be uploaded directly to S3 for optimal performance.', 'h3-tour-management');
                                        } else {
                                            _e('S3 Direct Upload must be configured to upload tour files.', 'h3-tour-management');
                                        }
                                        ?>
                                    </p>
                                    <?php if ($s3_configured): ?>
                                        <p class="description" style="color: #0073aa; font-weight: 500;">
                                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                            <?php _e('AWS S3 is configured and ready for all tour uploads.', 'h3-tour-management'); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="description" style="color: #d63384;">
                                            <span class="dashicons dashicons-warning"></span>
                                            <?php _e('S3 Direct Upload is required for file uploads.', 'h3-tour-management'); ?>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=h3tm-upload-settings')); ?>"> <?php _e('Configure S3 Now', 'h3-tour-management'); ?></a>
                                        </p>
                                    <?php endif; ?>
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
                                            <a href="<?php echo esc_url(site_url('/h3panos/' . rawurlencode($tour))); ?>" target="_blank">
                                                <?php echo esc_url(site_url('/h3panos/' . rawurlencode($tour))); ?>
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
     * Render upload settings page
     */
    public function render_upload_settings_page() {
        if (isset($_POST['submit'])) {
            // Save S3 settings
            update_option('h3tm_s3_access_key', sanitize_text_field($_POST['s3_access_key']));
            update_option('h3tm_s3_secret_key', sanitize_text_field($_POST['s3_secret_key']));
            update_option('h3tm_s3_bucket', sanitize_text_field($_POST['s3_bucket']));
            update_option('h3tm_s3_region', sanitize_text_field($_POST['s3_region']));
            update_option('h3tm_s3_threshold', intval($_POST['s3_threshold']));

            echo '<div class="notice notice-success"><p>' . __('Upload settings saved.', 'h3-tour-management') . '</p></div>';
        }

        $s3_config = $this->get_s3_config();
        $s3_access_key = get_option('h3tm_s3_access_key', '');
        $s3_secret_key = get_option('h3tm_s3_secret_key', '');
        $s3_bucket = get_option('h3tm_s3_bucket', '');
        $s3_region = get_option('h3tm_s3_region', 'us-east-1');
        $s3_threshold = get_option('h3tm_s3_threshold', 100); // MB

        // Check if using environment variables
        $using_env_vars = !empty(getenv('AWS_ACCESS_KEY_ID')) || !empty(getenv('AWS_S3_BUCKET'));

        ?>
        <div class="wrap">
            <h1><?php _e('Upload Settings', 'h3-tour-management'); ?></h1>

            <div class="h3tm-section">
                <h2><?php _e('Upload Methods', 'h3-tour-management'); ?></h2>
                <p><?php _e('The plugin supports two upload methods for optimal performance:', 'h3-tour-management'); ?></p>

                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Method', 'h3-tour-management'); ?></th>
                            <th><?php _e('File Size', 'h3-tour-management'); ?></th>
                            <th><?php _e('Description', 'h3-tour-management'); ?></th>
                            <th><?php _e('Status', 'h3-tour-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>S3 Direct Upload</strong></td>
                            <td>All files (required)</td>
                            <td>Uploads files directly from browser to Amazon S3. Fast, reliable, and supports unlimited file sizes.</td>
                            <td><span style="color: #00a32a;">✓ Required</span></td>
                        </tr>
                        <tr>
                            <td><strong>S3 Direct Upload</strong></td>
                            <td>Files > <?php echo $s3_threshold; ?>MB</td>
                            <td>Uploads large files directly to Amazon S3, then downloads to WordPress. Optimal for files over 100MB.</td>
                            <td>
                                <?php if ($s3_config): ?>
                                    <span style="color: #00a32a;">✓ Configured</span>
                                <?php else: ?>
                                    <span style="color: #d63384;">✗ Not Configured</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if ($using_env_vars): ?>
                <div class="notice notice-info">
                    <p><strong><?php _e('Environment Variables Detected', 'h3-tour-management'); ?></strong></p>
                    <p><?php _e('S3 configuration is being loaded from environment variables (recommended for security). The form below shows the current database values but environment variables will take precedence.', 'h3-tour-management'); ?></p>
                </div>
            <?php endif; ?>

            <div class="h3tm-section">
                <h2><?php _e('S3 Direct Upload Configuration', 'h3-tour-management'); ?></h2>
                <p><?php _e('Configure Amazon S3 for direct upload of all tour files. S3 configuration is required for uploads.', 'h3-tour-management'); ?></p>

                <form method="post" action="">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="s3_access_key"><?php _e('AWS Access Key ID', 'h3-tour-management'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="s3_access_key" name="s3_access_key" value="<?php echo esc_attr($s3_access_key); ?>" class="regular-text" />
                                <?php if ($using_env_vars): ?>
                                    <p class="description"><?php _e('Environment variable: AWS_ACCESS_KEY_ID', 'h3-tour-management'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="s3_secret_key"><?php _e('AWS Secret Access Key', 'h3-tour-management'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="s3_secret_key" name="s3_secret_key" value="<?php echo esc_attr($s3_secret_key); ?>" class="regular-text" />
                                <?php if ($using_env_vars): ?>
                                    <p class="description"><?php _e('Environment variable: AWS_SECRET_ACCESS_KEY', 'h3-tour-management'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="s3_bucket"><?php _e('S3 Bucket Name', 'h3-tour-management'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="s3_bucket" name="s3_bucket" value="<?php echo esc_attr($s3_bucket); ?>" class="regular-text" />
                                <?php if ($using_env_vars): ?>
                                    <p class="description"><?php _e('Environment variable: AWS_S3_BUCKET', 'h3-tour-management'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="s3_region"><?php _e('S3 Region', 'h3-tour-management'); ?></label>
                            </th>
                            <td>
                                <select id="s3_region" name="s3_region" class="regular-text">
                                    <option value="us-east-1" <?php selected($s3_region, 'us-east-1'); ?>>US East (N. Virginia)</option>
                                    <option value="us-west-1" <?php selected($s3_region, 'us-west-1'); ?>>US West (N. California)</option>
                                    <option value="us-west-2" <?php selected($s3_region, 'us-west-2'); ?>>US West (Oregon)</option>
                                    <option value="eu-west-1" <?php selected($s3_region, 'eu-west-1'); ?>>EU (Ireland)</option>
                                    <option value="eu-central-1" <?php selected($s3_region, 'eu-central-1'); ?>>EU (Frankfurt)</option>
                                    <option value="ap-southeast-1" <?php selected($s3_region, 'ap-southeast-1'); ?>>Asia Pacific (Singapore)</option>
                                    <option value="ap-southeast-2" <?php selected($s3_region, 'ap-southeast-2'); ?>>Asia Pacific (Sydney)</option>
                                    <option value="ap-northeast-1" <?php selected($s3_region, 'ap-northeast-1'); ?>>Asia Pacific (Tokyo)</option>
                                </select>
                                <?php if ($using_env_vars): ?>
                                    <p class="description"><?php _e('Environment variable: AWS_S3_REGION', 'h3-tour-management'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="s3_threshold"><?php _e('S3 Threshold (MB)', 'h3-tour-management'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="s3_threshold" name="s3_threshold" value="<?php echo esc_attr($s3_threshold); ?>" min="1" max="1000" class="small-text" />
                                <p class="description"><?php _e('Files larger than this size will use S3 direct upload. Recommended: 100MB', 'h3-tour-management'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>

            <div class="h3tm-section">
                <h2><?php _e('Configuration Status', 'h3-tour-management'); ?></h2>
                <?php
                $env_access_key = getenv('AWS_ACCESS_KEY_ID');
                $env_secret_key = getenv('AWS_SECRET_ACCESS_KEY');
                $env_bucket = getenv('AWS_S3_BUCKET');
                $env_region = getenv('AWS_S3_REGION');
                ?>

                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Configuration Source', 'h3-tour-management'); ?></th>
                            <th><?php _e('Access Key', 'h3-tour-management'); ?></th>
                            <th><?php _e('Secret Key', 'h3-tour-management'); ?></th>
                            <th><?php _e('Bucket', 'h3-tour-management'); ?></th>
                            <th><?php _e('Region', 'h3-tour-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Environment Variables</strong><br><small>(Recommended)</small></td>
                            <td><?php echo !empty($env_access_key) ? '<span style="color: #00a32a;">✓ Set</span>' : '<span style="color: #666;">Not set</span>'; ?></td>
                            <td><?php echo !empty($env_secret_key) ? '<span style="color: #00a32a;">✓ Set</span>' : '<span style="color: #666;">Not set</span>'; ?></td>
                            <td><?php echo !empty($env_bucket) ? '<span style="color: #00a32a;">' . esc_html($env_bucket) . '</span>' : '<span style="color: #666;">Not set</span>'; ?></td>
                            <td><?php echo !empty($env_region) ? esc_html($env_region) : 'us-east-1 (default)'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Database Options</strong><br><small>(Alternative)</small></td>
                            <td><?php echo !empty($s3_access_key) ? '<span style="color: #00a32a;">✓ Set</span>' : '<span style="color: #666;">Not set</span>'; ?></td>
                            <td><?php echo !empty($s3_secret_key) ? '<span style="color: #00a32a;">✓ Set</span>' : '<span style="color: #666;">Not set</span>'; ?></td>
                            <td><?php echo !empty($s3_bucket) ? '<span style="color: #00a32a;">' . esc_html($s3_bucket) . '</span>' : '<span style="color: #666;">Not set</span>'; ?></td>
                            <td><?php echo esc_html($s3_region); ?></td>
                        </tr>
                        <tr style="background: #f9f9f9;">
                            <td><strong>Final Configuration</strong></td>
                            <td colspan="4">
                                <?php if ($s3_config): ?>
                                    <span style="color: #00a32a; font-weight: bold;">✓ S3 Direct Upload Ready</span>
                                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                                        Using bucket: <strong><?php echo esc_html($s3_config['bucket']); ?></strong> in region <strong><?php echo esc_html($s3_config['region']); ?></strong>
                                    </p>
                                <?php else: ?>
                                    <span style="color: #d63384; font-weight: bold;">✗ S3 Direct Upload Not Available</span>
                                    <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">File uploads are disabled</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="h3tm-section">
                <h2><?php _e('Setup Instructions', 'h3-tour-management'); ?></h2>
                <ol>
                    <li><strong><?php _e('Create AWS S3 Bucket', 'h3-tour-management'); ?></strong><br>
                        <?php _e('Log into AWS Console and create a new S3 bucket in your preferred region.', 'h3-tour-management'); ?></li>

                    <li><strong><?php _e('Create IAM User', 'h3-tour-management'); ?></strong><br>
                        <?php _e('Create an IAM user with programmatic access and attach a policy with PutObject, GetObject, and DeleteObject permissions for your bucket.', 'h3-tour-management'); ?></li>

                    <li><strong><?php _e('Configure Settings', 'h3-tour-management'); ?></strong><br>
                        <?php _e('Either set environment variables (recommended) or use the form above to store credentials in the database.', 'h3-tour-management'); ?></li>

                    <li><strong><?php _e('Test Upload', 'h3-tour-management'); ?></strong><br>
                        <?php _e('Try uploading a file larger than your threshold to test S3 direct upload.', 'h3-tour-management'); ?></li>
                </ol>

                <p>
                    <strong><?php _e('For detailed setup instructions, see:', 'h3-tour-management'); ?></strong>
                    <a href="<?php echo esc_url(plugin_dir_url(__FILE__) . '../docs/S3_UPLOAD_CONFIGURATION.md'); ?>" target="_blank">
                        S3_UPLOAD_CONFIGURATION.md
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render S3 settings page
     */
    public function render_s3_settings_page() {
        if (isset($_POST['submit'])) {
            // Save S3 settings (only if not using environment variables)
            if (!defined('H3_S3_BUCKET')) {
                update_option('h3tm_s3_bucket', sanitize_text_field($_POST['s3_bucket']));
            }
            if (!defined('H3_S3_REGION')) {
                update_option('h3tm_s3_region', sanitize_text_field($_POST['s3_region']));
            }
            if (!defined('AWS_ACCESS_KEY_ID')) {
                update_option('h3tm_aws_access_key', sanitize_text_field($_POST['aws_access_key']));
            }
            if (!defined('AWS_SECRET_ACCESS_KEY')) {
                update_option('h3tm_aws_secret_key', sanitize_text_field($_POST['aws_secret_key']));
            }

            // Save Lambda Function URL
            if (isset($_POST['lambda_function_url'])) {
                update_option('h3tm_lambda_function_url', esc_url_raw($_POST['lambda_function_url']));
                error_log('H3TM Settings: Lambda URL saved: ' . $_POST['lambda_function_url']);
            }

            echo '<div class="notice notice-success"><p>' . __('S3 settings saved.', 'h3-tour-management') . '</p></div>';
        }

        // Get current settings
        $s3_bucket = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', 'h3-tour-files-h3vt');
        $s3_region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');
        $aws_access_key = defined('AWS_ACCESS_KEY_ID') ? '***configured***' : get_option('h3tm_aws_access_key', '');
        $aws_secret_key = defined('AWS_SECRET_ACCESS_KEY') ? '***configured***' : get_option('h3tm_aws_secret_key', '');
        $lambda_function_url = get_option('h3tm_lambda_function_url', '');
        // S3 is always enabled in S3-only system

        // Check S3 configuration
        $s3_integration = new H3TM_S3_Simple();
        $is_configured = $s3_integration->get_s3_config()['configured'];
        ?>
        <div class="wrap">
            <h1><?php _e('S3 Upload Settings', 'h3-tour-management'); ?></h1>

            <div class="h3tm-s3-status">
                <h2><?php _e('Configuration Status', 'h3-tour-management'); ?></h2>
                <p>
                    <strong><?php _e('S3 Integration:', 'h3-tour-management'); ?></strong>
                    <?php if ($is_configured) : ?>
                        <span style="color: green;">✓ <?php _e('Configured', 'h3-tour-management'); ?></span>
                        <button type="button" id="test-s3-connection" class="button button-secondary"><?php _e('Test Connection', 'h3-tour-management'); ?></button>
                    <?php else : ?>
                        <span style="color: red;">✗ <?php _e('Not Configured', 'h3-tour-management'); ?></span>
                    <?php endif; ?>
                </p>
                <div id="s3-test-result" style="margin-top: 10px;"></div>
            </div>

            <?php if (defined('H3_S3_BUCKET') || defined('AWS_ACCESS_KEY_ID')) : ?>
                <div class="notice notice-info">
                    <p><strong><?php _e('Note:', 'h3-tour-management'); ?></strong>
                    <?php _e('Some settings are configured via environment variables (wp-config.php) and cannot be changed here.', 'h3-tour-management'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="notice notice-info">
                    <p><strong><?php _e('S3-Only System:', 'h3-tour-management'); ?></strong>
                    <?php _e('All tour uploads now use AWS S3 directly. Traditional server uploads have been removed for better performance and reliability.', 'h3-tour-management'); ?></p>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="s3_bucket"><?php _e('S3 Bucket Name', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="s3_bucket" name="s3_bucket" value="<?php echo esc_attr($s3_bucket); ?>"
                                   class="regular-text" <?php echo defined('H3_S3_BUCKET') ? 'readonly' : ''; ?> />
                            <?php if (defined('H3_S3_BUCKET')) : ?>
                                <p class="description"><?php _e('Configured via environment variable.', 'h3-tour-management'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="s3_region"><?php _e('S3 Region', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <select id="s3_region" name="s3_region" <?php echo defined('H3_S3_REGION') ? 'disabled' : ''; ?>>
                                <option value="us-east-1" <?php selected($s3_region, 'us-east-1'); ?>>US East (N. Virginia)</option>
                                <option value="us-west-2" <?php selected($s3_region, 'us-west-2'); ?>>US West (Oregon)</option>
                                <option value="eu-west-1" <?php selected($s3_region, 'eu-west-1'); ?>>Europe (Ireland)</option>
                                <option value="ap-southeast-1" <?php selected($s3_region, 'ap-southeast-1'); ?>>Asia Pacific (Singapore)</option>
                            </select>
                            <?php if (defined('H3_S3_REGION')) : ?>
                                <p class="description"><?php _e('Configured via environment variable.', 'h3-tour-management'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aws_access_key"><?php _e('AWS Access Key ID', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="aws_access_key" name="aws_access_key"
                                   value="<?php echo esc_attr($aws_access_key); ?>"
                                   class="regular-text" <?php echo defined('AWS_ACCESS_KEY_ID') ? 'readonly' : ''; ?> />
                            <?php if (defined('AWS_ACCESS_KEY_ID')) : ?>
                                <p class="description"><?php _e('Configured via environment variable.', 'h3-tour-management'); ?></p>
                            <?php else : ?>
                                <p class="description"><?php _e('Enter your AWS Access Key ID from IAM user creation.', 'h3-tour-management'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="aws_secret_key"><?php _e('AWS Secret Access Key', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="aws_secret_key" name="aws_secret_key"
                                   value="<?php echo defined('AWS_SECRET_ACCESS_KEY') ? '' : esc_attr($aws_secret_key); ?>"
                                   class="regular-text" <?php echo defined('AWS_SECRET_ACCESS_KEY') ? 'readonly' : ''; ?> />
                            <?php if (defined('AWS_SECRET_ACCESS_KEY')) : ?>
                                <p class="description"><?php _e('Configured via environment variable.', 'h3-tour-management'); ?></p>
                            <?php else : ?>
                                <p class="description"><?php _e('Enter your AWS Secret Access Key from IAM user creation.', 'h3-tour-management'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lambda_function_url"><?php _e('Lambda Function URL', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="lambda_function_url" name="lambda_function_url"
                                   value="<?php echo esc_attr($lambda_function_url); ?>"
                                   class="regular-text" placeholder="https://abc123.lambda-url.us-east-1.on.aws/" />
                            <p class="description">
                                <?php _e('Lambda Function URL for tour deletion and management.', 'h3-tour-management'); ?><br>
                                <?php _e('Create with: <code>aws lambda create-function-url-config --function-name H3TourProcessor --auth-type NONE --region us-east-1</code>', 'h3-tour-management'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <div class="h3tm-s3-info">
                <h2><?php _e('Setup Instructions', 'h3-tour-management'); ?></h2>
                <ol>
                    <li><?php _e('Create an AWS account and set up S3 bucket (see documentation)', 'h3-tour-management'); ?></li>
                    <li><?php _e('Create IAM user with S3 permissions', 'h3-tour-management'); ?></li>
                    <li><?php _e('Enter AWS credentials above or add to wp-config.php:', 'h3-tour-management'); ?>
                        <pre>define('H3_S3_BUCKET', 'h3-tour-files-h3vt');
define('H3_S3_REGION', 'us-east-1');
define('AWS_ACCESS_KEY_ID', 'your-access-key');
define('AWS_SECRET_ACCESS_KEY', 'your-secret-key');</pre>
                    </li>
                    <li><?php _e('Test the connection to verify setup', 'h3-tour-management'); ?></li>
                </ol>

                <h3><?php _e('S3-Only System Benefits', 'h3-tour-management'); ?></h3>
                <ul>
                    <li>✅ <?php _e('Supports unlimited file sizes', 'h3-tour-management'); ?></li>
                    <li>✅ <?php _e('Eliminates all server limitations', 'h3-tour-management'); ?></li>
                    <li>✅ <?php _e('Faster uploads with direct browser-to-S3', 'h3-tour-management'); ?></li>
                    <li>✅ <?php _e('Professional cloud infrastructure', 'h3-tour-management'); ?></li>
                    <li>✅ <?php _e('No more upload failures due to server constraints', 'h3-tour-management'); ?></li>
                </ul>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#test-s3-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#s3-test-result');

                $button.prop('disabled', true).text('Testing...');
                $result.html('');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'h3tm_test_s3_connection',
                        nonce: '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p>Connection test failed.</p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Test Connection');
                    }
                });
            });
        });
        </script>
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
            // Real analytics only
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

        $tour_manager = $this->get_tour_manager();
        $result = $tour_manager->upload_tour($tour_name, $_FILES['tour_file']);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // All chunked upload functionality removed - S3 Direct Upload only

    // Temporary file cleanup methods removed - not needed for S3 uploads

    /**
     * Get S3 configuration from WordPress options or environment variables
     */
    private function get_s3_config() {
        // Check if S3 is configured via environment variables (recommended for security)
        $s3_config = array(
            'access_key' => getenv('AWS_ACCESS_KEY_ID') ?: get_option('h3tm_s3_access_key', ''),
            'secret_key' => getenv('AWS_SECRET_ACCESS_KEY') ?: get_option('h3tm_s3_secret_key', ''),
            'bucket' => getenv('AWS_S3_BUCKET') ?: get_option('h3tm_s3_bucket', ''),
            'region' => getenv('AWS_S3_REGION') ?: get_option('h3tm_s3_region', 'us-east-1')
        );

        // Return false if required config is missing
        if (empty($s3_config['access_key']) || empty($s3_config['secret_key']) || empty($s3_config['bucket'])) {
            return false;
        }

        return $s3_config;
    }

    /**
     * Handle S3 presigned URL request
     */
    public function handle_get_s3_presigned_url() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $s3_config = $this->get_s3_config();
        if (!$s3_config) {
            wp_send_json_error(array(
                'message' => 'S3 not configured',
                'error' => 'S3 required'
            ));
        }

        $tour_name = sanitize_text_field($_POST['tour_name']);
        $file_name = sanitize_file_name($_POST['file_name']);
        $file_size = intval($_POST['file_size']);
        $file_type = sanitize_text_field($_POST['file_type']);

        if (empty($tour_name) || empty($file_name)) {
            wp_send_json_error(array(
                'message' => 'Missing required parameters',
                'error' => 'Invalid parameters'
            ));
        }

        try {
            // Generate unique upload ID for tracking
            $upload_id = uniqid('s3_' . time() . '_', true);

            // Store upload metadata temporarily
            $upload_meta = array(
                'tour_name' => $tour_name,
                'file_name' => $file_name,
                'file_size' => $file_size,
                'file_type' => $file_type,
                'timestamp' => time(),
                's3_key' => 'h3tours/' . $upload_id . '/' . $file_name
            );

            set_transient('h3tm_s3_upload_' . $upload_id, $upload_meta, 3600); // 1 hour expiry

            // For now, return the direct S3 credentials and let the client handle the upload
            // In production, you would generate a presigned URL server-side for better security
            wp_send_json_success(array(
                'upload_url' => 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com',
                'upload_id' => $upload_id,
                'access_key' => $s3_config['access_key'],
                'secret_key' => $s3_config['secret_key'],
                'bucket' => $s3_config['bucket'],
                'region' => $s3_config['region'],
                'key' => $upload_meta['s3_key'],
                'method' => 's3_direct'
            ));

        } catch (Exception $e) {
            error_log('H3TM S3 Presigned URL Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to generate S3 upload URL',
                'error' => 'S3 configuration error'
            ));
        }
    }

    /**
     * Handle S3 upload completion and process the file
     */
    public function handle_process_s3_upload() {
        // Set generous limits for large file processing
        @ini_set('max_execution_time', 900);
        @ini_set('memory_limit', '1024M');

        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $upload_id = sanitize_text_field($_POST['upload_id']);
        $tour_name = sanitize_text_field($_POST['tour_name']);

        if (empty($upload_id) || empty($tour_name)) {
            wp_send_json_error('Missing required parameters');
        }

        // Get upload metadata
        $upload_meta = get_transient('h3tm_s3_upload_' . $upload_id);
        if (!$upload_meta) {
            wp_send_json_error('Upload session not found or expired');
        }

        // Clean up the transient
        delete_transient('h3tm_s3_upload_' . $upload_id);

        $s3_config = $this->get_s3_config();
        if (!$s3_config) {
            wp_send_json_error('S3 configuration not available');
        }

        try {
            // Download file from S3 to local temp location
            $temp_file = $this->download_from_s3($s3_config, $upload_meta['s3_key'], $upload_meta['file_name']);

            if (!$temp_file || !file_exists($temp_file)) {
                wp_send_json_error('Failed to download file from S3');
            }

            // Process the downloaded file using existing tour manager
            $tour_manager = $this->get_tour_manager();
            $file_info = array(
                'name' => $upload_meta['file_name'],
                'tmp_name' => $temp_file,
                'error' => UPLOAD_ERR_OK,
                'size' => file_exists($temp_file) ? filesize($temp_file) : 0
            );

            $result = $tour_manager->upload_tour($tour_name, $file_info, true);

            // Clean up temp file
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }

            // Clean up S3 file (optional - you might want to keep it as backup)
            $this->cleanup_s3_file($s3_config, $upload_meta['s3_key']);

            if ($result['success']) {
                wp_send_json_success($result['message'] . ' (via S3 Direct Upload)');
            } else {
                wp_send_json_error($result['message']);
            }

        } catch (Exception $e) {
            error_log('H3TM S3 Process Upload Error: ' . $e->getMessage());
            wp_send_json_error('S3 upload processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Download file from S3 to local temp location
     */
    private function download_from_s3($s3_config, $s3_key, $original_filename) {
        // Create temp file
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/h3-tours-temp';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $temp_file = $temp_dir . '/' . uniqid('s3_download_') . '_' . $original_filename;

        // Construct S3 URL
        $s3_url = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/' . $s3_key;

        // For production, you would use AWS SDK to download the file properly
        // For now, using a simplified approach with signed URLs or public access
        // This is a placeholder - implement proper S3 download using AWS SDK

        // Simple approach using cURL (requires proper S3 authentication)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $s3_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
        curl_setopt($ch, CURLOPT_FILE, fopen($temp_file, 'w+'));

        $success = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($success && $http_code === 200 && file_exists($temp_file) && filesize($temp_file) > 0) {
            return $temp_file;
        }

        // Clean up failed download
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }

        error_log('H3TM S3 Download Failed: HTTP ' . $http_code . ' for ' . $s3_url);
        return false;
    }

    /**
     * Clean up S3 file after processing
     */
    private function cleanup_s3_file($s3_config, $s3_key) {
        // For production, implement proper S3 file deletion using AWS SDK
        // This is a placeholder for S3 cleanup logic
        error_log('H3TM S3 Cleanup: Would delete ' . $s3_key . ' from bucket ' . $s3_config['bucket']);
    }

    /**
     * Invoke Lambda function to delete S3 tour files via AWS API
     */
    private function invoke_lambda_deletion($tour_name) {
        // Use WordPress HTTP API to invoke Lambda (no AWS CLI needed)
        $lambda_function_url = get_option('h3tm_lambda_function_url', '');

        if (empty($lambda_function_url)) {
            error_log('H3TM Delete: Lambda function URL not configured');
            return array('success' => false, 'message' => 'Lambda not configured');
        }

        // Get the actual S3 folder name (might be different from display name)
        $s3_tours = get_option('h3tm_s3_tours', array());
        $s3_folder_name = $tour_name;

        if (isset($s3_tours[$tour_name]) && !empty($s3_tours[$tour_name]['original_name'])) {
            // Use original S3 folder name if this is a renamed tour
            $s3_folder_name = $s3_tours[$tour_name]['original_name'];
        } else {
            // Convert spaces to dashes for S3 folder lookup
            $s3_folder_name = str_replace(' ', '-', $tour_name);
        }

        error_log('H3TM Delete: Tour "' . $tour_name . '" → S3 folder "' . $s3_folder_name . '"');

        // Invoke Lambda via HTTP (using Function URL)
        $response = wp_remote_post($lambda_function_url, array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'action' => 'delete_tour',
                'bucket' => get_option('h3tm_s3_bucket', 'h3-tour-files-h3vt'),
                'tourName' => $s3_folder_name // Use S3 folder name, not display name
            ))
        ));

        if (is_wp_error($response)) {
            error_log('H3TM Delete: Lambda invocation failed: ' . $response->get_error_message());
            return array('success' => false, 'message' => 'Lambda invocation failed');
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('H3TM Delete: Lambda response: ' . $response_code . ' - ' . $response_body);

        return array(
            'success' => $response_code === 200,
            'message' => $response_body
        );
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

        error_log('H3TM Delete: Attempting to delete: ' . $tour_name);

        // For S3 tours, check registry with flexible matching (spaces/dashes)
        $s3_tours = get_option('h3tm_s3_tours', array());

        // Try to find the tour (check exact match, with dashes, and with spaces)
        $found_key = null;
        if (isset($s3_tours[$tour_name])) {
            $found_key = $tour_name;
        } elseif (isset($s3_tours[str_replace(' ', '-', $tour_name)])) {
            $found_key = str_replace(' ', '-', $tour_name);
        } elseif (isset($s3_tours[str_replace('-', ' ', $tour_name)])) {
            $found_key = str_replace('-', ' ', $tour_name);
        }

        error_log('H3TM Delete: S3 registry search - Found key: ' . ($found_key ?? 'NOT FOUND'));

        if ($found_key) {
            // Call Lambda to archive S3 files
            $deletion_result = $this->invoke_lambda_deletion($found_key);

            // Remove from WordPress registry using the found key
            unset($s3_tours[$found_key]);
            update_option('h3tm_s3_tours', $s3_tours);

            if ($deletion_result['success']) {
                wp_send_json_success('Tour deleted successfully from S3 and WordPress');
            } else {
                wp_send_json_success('Tour removed from WordPress (S3 deletion may have failed - check manually)');
            }
        } else {
            // Try local tour delete
            $tour_manager = $this->get_tour_manager();
            $result = $tour_manager->delete_tour($tour_name);

            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        }
    }
    
    /**
     * Handle tour rename with enhanced error handling and Pantheon optimizations
     */
    public function handle_rename_tour() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $old_name = sanitize_text_field($_POST['old_name']);
        $new_name = sanitize_text_field($_POST['new_name']);

        // For S3 tours, update the registry and track original S3 folder name
        $s3_tours = get_option('h3tm_s3_tours', array());
        if (isset($s3_tours[$old_name])) {
            // Copy tour data to new name
            $tour_data = $s3_tours[$old_name];

            // Store original S3 folder name for URL mapping
            if (!isset($tour_data['original_name'])) {
                $tour_data['original_name'] = str_replace(' ', '-', $old_name);
            }

            $s3_tours[$new_name] = $tour_data;
            unset($s3_tours[$old_name]);
            update_option('h3tm_s3_tours', $s3_tours);

            error_log('H3TM Rename: "' . $old_name . '" → "' . $new_name . '" (S3 folder: ' . $tour_data['original_name'] . ')');

            wp_send_json_success('Tour renamed successfully');
            return;
        }

        // Try local tour rename
        @ini_set('max_execution_time', 900);
        @ini_set('memory_limit', '1024M');

        // Create debug info for troubleshooting (like upload handler)
        $h3panos_path = ABSPATH . 'h3panos';
        $upload_dir = wp_upload_dir();
        $debug_info = array(
            'operation' => 'rename_tour',
            'old_name' => $old_name,
            'new_name' => $new_name,
            'is_pantheon' => (defined('PANTHEON_ENVIRONMENT') || strpos(ABSPATH, '/code/') === 0),
            'h3panos_path' => $h3panos_path,
            'h3panos_exists' => file_exists($h3panos_path),
            'h3panos_writeable' => is_writeable($h3panos_path),
            'abspath' => ABSPATH,
            'upload_basedir' => $upload_dir['basedir'],
            'old_tour_path' => $h3panos_path . '/' . $old_name,
            'new_tour_path' => $h3panos_path . '/' . $new_name,
            'old_tour_exists' => file_exists($h3panos_path . '/' . $old_name),
            'new_tour_exists' => file_exists($h3panos_path . '/' . $new_name),
            'using_optimized' => $this->use_optimized,
            'handler' => 'h3tm_rename_tour'
        );

        try {
            $tour_manager = $this->get_tour_manager();
            $result = $tour_manager->rename_tour($old_name, $new_name);

            if ($result['success']) {
                // Include some debug info in success response for monitoring
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'debug' => array(
                        'is_pantheon' => $debug_info['is_pantheon'],
                        'using_optimized' => $debug_info['using_optimized']
                    )
                ));
            } else {
                // Enhanced error response with debug info (like upload handler)
                error_log('H3TM Rename Error: ' . $result['message'] . ' | Debug: ' . json_encode($debug_info));
                wp_send_json_error(array(
                    'message' => $result['message'],
                    'debug' => $debug_info
                ));
            }
        } catch (Exception $e) {
            // Catch any unexpected errors and provide debug info
            $error_msg = 'Rename operation failed: ' . $e->getMessage();
            error_log('H3TM Rename Exception: ' . $error_msg . ' | Debug: ' . json_encode($debug_info));
            wp_send_json_error(array(
                'message' => $error_msg,
                'debug' => $debug_info
            ));
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

        $tour_manager = $this->get_tour_manager();
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

    /**
     * Render URL handlers management page
     */
    public function render_url_handlers_page() {
        // Check if URL manager is available
        if (!class_exists('H3TM_URL_Manager')) {
            echo '<div class="wrap"><h1>URL Handler Settings</h1><div class="notice notice-error"><p>URL Manager not available. Please check your installation.</p></div></div>';
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . __('URL Handler Settings', 'h3-tour-management') . '</h1>';
        echo '<p>' . __('Manage different approaches for serving S3 tour content through local /h3panos/ URLs.', 'h3-tour-management') . '</p>';

        // Get URL manager instance
        $url_manager_class = 'H3TM_URL_Manager';
        $url_manager = new $url_manager_class();

        // Render the management panel
        echo $url_manager->render_admin_panel();

        echo '</div>';
    }
}