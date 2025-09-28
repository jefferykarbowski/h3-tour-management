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
        add_action('wp_ajax_h3tm_migrate_tour_to_s3', array($this, 'handle_migrate_tour_to_s3'));
        // h3tm_list_s3_tours is handled by H3TM_S3_Simple class
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

        // URL Handlers submenu removed - functionality integrated into main page
        // add_submenu_page(
        //     'h3-tour-management',
        //     __('URL Handler Settings', 'h3-tour-management'),
        //     __('URL Handlers', 'h3-tour-management'),
        //     'manage_options',
        //     'h3tm-url-handlers',
        //     array($this, 'render_url_handlers_page')
        // );

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

        // Ensure the option is set for consistency
        if (get_option('h3tm_s3_enabled', '') !== '1') {
            update_option('h3tm_s3_enabled', '1');
        }

        // Debug S3 configuration
        error_log('H3TM S3 Config Debug: configured=' . ($s3_config['configured'] ? 'true' : 'false') .
                  ', enabled=' . ($s3_enabled ? 'true' : 'false') .
                  ', bucket=' . $s3_config['bucket']);

        // Localize script
        wp_localize_script('h3tm-admin', 'h3tm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('h3tm_ajax_nonce'),
            's3_threshold_mb' => isset($s3_config['threshold_mb']) ? $s3_config['threshold_mb'] : 100,
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

        // Verify each tour still exists by checking if URL is accessible
        $verified_tours = array();

        foreach ($s3_tours as $tour_name => $tour_data) {
            // Quick check if tour exists in S3 (try to access index.htm)
            $tour_s3_folder = isset($tour_data['original_name']) ? $tour_data['original_name'] : str_replace(' ', '-', $tour_name);
            $s3_config = $this->get_s3_simple_config();

            if ($s3_config['configured']) {
                $test_url = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/tours/' . $tour_s3_folder . '/index.htm';

                // Quick HEAD request to check if tour exists
                $response = wp_remote_head($test_url, array('timeout' => 5));

                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    // Tour exists, keep it
                    $verified_tours[$tour_name] = $tour_data;
                } else {
                    // Tour doesn't exist (might be archived or deleted)
                    error_log('H3TM Sync: Tour "' . $tour_name . '" not found in S3, removing from registry');
                }
            } else {
                // Can't verify without S3 config, keep all tours
                $verified_tours[$tour_name] = $tour_data;
            }
        }

        // Update registry with only verified tours
        if (count($verified_tours) !== count($s3_tours)) {
            update_option('h3tm_s3_tours', $verified_tours);
            error_log('H3TM Sync: Updated registry - ' . count($verified_tours) . ' tours verified');
        }
    }

    /**
     * Get S3 config helper
     */
    private function get_s3_simple_config() {
        $bucket = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', 'h3-tour-files-h3vt');
        $region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');

        return array(
            'configured' => !empty($bucket),
            'bucket' => $bucket,
            'region' => $region
        );
    }

    /**
     * Get all tours from S3
     */
    private function get_all_tours_by_source() {
        $tours = array();

        try {
            // Get tours from S3 bucket via CloudFront
            $s3_simple = new H3TM_S3_Simple();
            $s3_config = $s3_simple->get_s3_config();

            if ($s3_config['configured']) {
                $s3_tours = $s3_simple->list_s3_tours();

                if (is_array($s3_tours) && !empty($s3_tours)) {
                    foreach ($s3_tours as $tour) {
                        $tours[] = $tour;
                    }
                    error_log('H3TM Admin: Found ' . count($s3_tours) . ' tours');
                }
            }
        } catch (Exception $e) {
            error_log('H3TM Admin: Error getting tours: ' . $e->getMessage());
        }

        return $tours;
    }

    /**
     * Get tours from S3 bucket
     */
    private function get_s3_tours() {
        $all_tours = array();

        // Get tours from S3 bucket
        $s3_simple = new H3TM_S3_Simple();
        $s3_tours = $s3_simple->list_s3_tours();

        if (!empty($s3_tours)) {
            error_log('H3TM Admin: Found ' . count($s3_tours) . ' tours');
            foreach ($s3_tours as $tour) {
                $all_tours[] = $tour;
            }
        }

        return $all_tours;
    }


    /**
     * Render main tours management page
     */
    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Tours Management', 'h3-tour-management'); ?></h1>

            <div class="h3tm-admin-container">
                <!-- Upload Section -->
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
                                    <p class="description"><?php _e('Select a ZIP file containing your tour files', 'h3-tour-management'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="<?php _e('Upload Tour', 'h3-tour-management'); ?>" />
                        </p>
                    </form>
                    <div id="upload-result" class="notice" style="display:none;"></div>
                </div>

                <!-- Tours Section -->
                <div class="h3tm-section">
                    <h2><?php _e('Available Tours', 'h3-tour-management'); ?></h2>
                    <div id="s3-tour-list-container">
                        <p id="s3-loading-message"><span class="spinner is-active" style="float: none;"></span> <?php _e('Loading tours...', 'h3-tour-management'); ?></p>
                    </div>
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
            update_option('h3tm_aws_access_key', sanitize_text_field($_POST['s3_access_key']));
            update_option('h3tm_aws_secret_key', sanitize_text_field($_POST['s3_secret_key']));
            update_option('h3tm_s3_bucket', sanitize_text_field($_POST['s3_bucket']));
            update_option('h3tm_s3_region', sanitize_text_field($_POST['s3_region']));
            update_option('h3tm_s3_threshold', intval($_POST['s3_threshold']));

            echo '<div class="notice notice-success"><p>' . __('Upload settings saved.', 'h3-tour-management') . '</p></div>';
        }

        $s3_config = $this->get_s3_config();
        $s3_access_key = get_option('h3tm_aws_access_key', '');
        $s3_secret_key = get_option('h3tm_aws_secret_key', '');
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
            }

            // Save CloudFront settings
            update_option('h3tm_cloudfront_enabled', isset($_POST['cloudfront_enabled']) ? '1' : '0');
            update_option('h3tm_cloudfront_domain', sanitize_text_field($_POST['cloudfront_domain']));
            update_option('h3tm_cloudfront_distribution_id', sanitize_text_field($_POST['cloudfront_distribution_id']));

            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'h3-tour-management') . '</p></div>';
        }

        // Get current settings
        $s3_bucket = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', 'h3-tour-files-h3vt');
        $s3_region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');
        $aws_access_key = defined('AWS_ACCESS_KEY_ID') ? '***configured***' : get_option('h3tm_aws_access_key', '');
        $aws_secret_key = defined('AWS_SECRET_ACCESS_KEY') ? '***configured***' : get_option('h3tm_aws_secret_key', '');
        $lambda_function_url = get_option('h3tm_lambda_function_url', '');
        $cloudfront_enabled = get_option('h3tm_cloudfront_enabled', '0');
        $cloudfront_domain = get_option('h3tm_cloudfront_domain', '');
        $cloudfront_distribution_id = get_option('h3tm_cloudfront_distribution_id', '');

        // Check S3 configuration
        $s3_integration = new H3TM_S3_Simple();
        $is_configured = $s3_integration->get_s3_config()['configured'];
        ?>
        <div class="wrap">
            <h1><?php _e('S3 & CloudFront Settings', 'h3-tour-management'); ?></h1>

            <div class="h3tm-s3-status" style="margin-bottom: 20px;">
                <p>
                    <strong><?php _e('Status:', 'h3-tour-management'); ?></strong>
                    <?php if ($is_configured) : ?>
                        <span style="color: green;">✓ <?php _e('S3 Configured', 'h3-tour-management'); ?></span>
                        <?php if ($cloudfront_enabled && !empty($cloudfront_domain)) : ?>
                            <span style="color: green; margin-left: 15px;">✓ <?php _e('CloudFront Active', 'h3-tour-management'); ?></span>
                        <?php endif; ?>
                        <button type="button" id="test-s3-connection" class="button button-secondary" style="margin-left: 15px;"><?php _e('Test Connection', 'h3-tour-management'); ?></button>
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

                <h2><?php _e('AWS Configuration', 'h3-tour-management'); ?></h2>
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
                                <?php _e('For tour processing and deletion', 'h3-tour-management'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 style="margin-top: 30px;"><?php _e('CloudFront CDN (Optional)', 'h3-tour-management'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cloudfront_enabled"><?php _e('Enable CloudFront', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="cloudfront_enabled" name="cloudfront_enabled" value="1" <?php checked($cloudfront_enabled, '1'); ?> />
                                <?php _e('Use CloudFront CDN for tour delivery', 'h3-tour-management'); ?>
                            </label>
                            <p class="description"><?php _e('Improves tour loading speed with global content delivery', 'h3-tour-management'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cloudfront_domain"><?php _e('CloudFront Domain', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="cloudfront_domain" name="cloudfront_domain"
                                   value="<?php echo esc_attr($cloudfront_domain); ?>"
                                   class="regular-text" placeholder="d123abc.cloudfront.net" />
                            <p class="description"><?php _e('Your CloudFront distribution domain (without https://)', 'h3-tour-management'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cloudfront_distribution_id"><?php _e('Distribution ID', 'h3-tour-management'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="cloudfront_distribution_id" name="cloudfront_distribution_id"
                                   value="<?php echo esc_attr($cloudfront_distribution_id); ?>"
                                   class="regular-text" placeholder="E1ABC2DEF3GHIJ" />
                            <p class="description"><?php _e('CloudFront distribution ID for cache invalidation (optional)', 'h3-tour-management'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

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
        // Note: Using same option keys as H3TM_S3_Simple class for consistency
        $s3_config = array(
            'access_key' => getenv('AWS_ACCESS_KEY_ID') ?: get_option('h3tm_aws_access_key', ''),
            'secret_key' => getenv('AWS_SECRET_ACCESS_KEY') ?: get_option('h3tm_aws_secret_key', ''),
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
     * DEPRECATED - Now using direct S3 archive method instead
     */
    /*
    private function invoke_lambda_deletion($tour_name) {
        // Use WordPress HTTP API to invoke Lambda (no AWS CLI needed)
        $lambda_function_url = get_option('h3tm_lambda_function_url', '');

        if (empty($lambda_function_url)) {
            error_log('H3TM Delete: Lambda function URL not configured');
            return array('success' => false, 'message' => 'Lambda not configured');
        }

        // Redact sensitive URL for security
        $redacted_url = substr($lambda_function_url, 0, 30) . '***' . substr($lambda_function_url, -10);
        error_log('H3TM Delete: Using Lambda URL: ' . $redacted_url);

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

        // If 403, log more details for debugging
        if ($response_code === 403) {
            error_log('H3TM Delete: 403 Forbidden - Check Lambda Function URL auth type is NONE');
            error_log('H3TM Delete: Request body was: ' . json_encode(array(
                'action' => 'delete_tour',
                'bucket' => get_option('h3tm_s3_bucket', 'h3-tour-files-h3vt'),
                'tourName' => $s3_folder_name
            )));
        }

        return array(
            'success' => $response_code === 200,
            'message' => $response_body
        );
    }
    */

    /**
     * Handle tour deletion - archives to archive/ folder for 90 days
     */
    public function handle_delete_tour() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tour_name = sanitize_text_field($_POST['tour_name']);

        error_log('H3TM Delete: Attempting to archive: ' . $tour_name);

        // Use S3 Simple to archive the tour
        $s3 = new H3TM_S3_Simple();

        // Archive the tour to the archive/ folder
        $archive_result = $s3->archive_tour($tour_name);

        if ($archive_result['success']) {
            // Clear the cache so the tour list updates
            delete_transient('h3tm_s3_tour_list');

            wp_send_json_success('Tour archived successfully. It will be permanently deleted after 90 days.');
        } else {
            // Check if it's a configuration issue or tour not found
            if (strpos($archive_result['message'], 'not configured') !== false) {
                wp_send_json_error('S3 is not configured. Please configure S3 settings first.');
            } else {
                wp_send_json_error($archive_result['message']);
            }
        }
    }
    
    /**
     * Handle tour rename - actually renames the S3 folder
     */
    public function handle_rename_tour() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $old_name = sanitize_text_field($_POST['old_name']);
        $new_name = sanitize_text_field($_POST['new_name']);

        error_log('H3TM Rename: "' . $old_name . '" → "' . $new_name . '"');

        // Use S3 Simple to rename the tour
        $s3 = new H3TM_S3_Simple();

        // Rename the tour in S3
        $rename_result = $s3->rename_tour($old_name, $new_name);

        if ($rename_result['success']) {
            // Clear the cache so the tour list updates
            delete_transient('h3tm_s3_tour_list');

            wp_send_json_success('Tour renamed successfully');
        } else {
            // Check if it's a configuration issue or tour not found
            if (strpos($rename_result['message'], 'not configured') !== false) {
                wp_send_json_error('S3 is not configured. Please configure S3 settings first.');
            } else {
                wp_send_json_error($rename_result['message']);
            }
        }
        return;

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

    /**
     * Handle migration of local tour to S3
     */
    public function handle_migrate_tour_to_s3() {
        // Set generous limits for large file processing
        @ini_set('max_execution_time', 900);
        @ini_set('memory_limit', '1024M');

        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $tour_name = sanitize_text_field($_POST['tour_name']);
        if (empty($tour_name)) {
            wp_send_json_error('Tour name is required');
        }

        // Load S3 class
        if (!class_exists('H3TM_S3_Simple')) {
            require_once(dirname(__FILE__) . '/class-h3tm-s3-simple.php');
        }

        $s3 = new H3TM_S3_Simple();

        // Get S3 config directly from S3_Simple class (which is working for other features)
        $s3_config = $s3->get_s3_config();

        // Debug logging to understand the issue
        error_log('H3TM Migration Debug - get_s3_config() result:');
        if ($s3_config === false) {
            error_log('  Config returned FALSE');

            // Check individual values for debugging
            $access_key = get_option('h3tm_aws_access_key', '');
            $secret_key = get_option('h3tm_aws_secret_key', '');
            $bucket = get_option('h3tm_s3_bucket', '');

            error_log('  Direct option check:');
            error_log('    h3tm_aws_access_key: ' . (empty($access_key) ? 'EMPTY' : 'SET'));
            error_log('    h3tm_aws_secret_key: ' . (empty($secret_key) ? 'EMPTY' : 'SET'));
            error_log('    h3tm_s3_bucket: ' . (empty($bucket) ? 'EMPTY' : $bucket));

            // Also try to get config from S3_Simple class directly
            $s3 = new H3TM_S3_Simple();
            $s3_simple_config = $s3->get_s3_config();
            error_log('  S3_Simple class config:');
            error_log('    configured: ' . ($s3_simple_config['configured'] ? 'YES' : 'NO'));
            error_log('    bucket: ' . $s3_simple_config['bucket']);

            // Use S3_Simple config if available
            if ($s3_simple_config['configured']) {
                error_log('  Using S3_Simple config instead');
                $s3_config = $s3_simple_config;
            } else {
                wp_send_json_error('S3 is not configured. Please configure S3 settings first.');
            }
        } else {
            error_log('  Config OK - bucket: ' . $s3_config['bucket']);
        }

        // Determine h3panos path - check multiple possible locations
        $possible_paths = array(
            'C:/Users/Jeff/Local Sites/h3vt/app/public/h3panos',
            ABSPATH . '../h3panos',
            ABSPATH . 'h3panos',
            ABSPATH . 'wp-content/h3panos',
            ABSPATH . '../h3-tours',
            ABSPATH . 'h3-tours',
            ABSPATH . 'wp-content/h3-tours'
        );

        $h3panos_path = null;
        $tour_path = null;
        $is_zip = false;

        foreach ($possible_paths as $path) {
            if (is_dir($path)) {
                $test_path = $path . '/' . $tour_name;
                if (is_dir($test_path)) {
                    $h3panos_path = $path;
                    $tour_path = $test_path;
                    break;
                } elseif (file_exists($test_path . '.zip')) {
                    $h3panos_path = $path;
                    $tour_path = $test_path . '.zip';
                    $is_zip = true;
                    break;
                }
            }
        }

        if (!$tour_path) {
            wp_send_json_error('Tour not found: ' . $tour_name);
        }

        // Convert spaces to dashes for S3 (matching Lambda behavior)
        $s3_tour_name = str_replace(' ', '-', $tour_name);

        // Check if tour already exists in S3
        $existing_tours = $s3->list_s3_tours();
        foreach ($existing_tours as $existing_tour_name) {
            // The list_s3_tours returns tour names with spaces (e.g., "Bee Cave")
            // Compare both the original name and the S3-formatted name
            if (strcasecmp($existing_tour_name, $tour_name) === 0 ||
                strcasecmp($existing_tour_name, str_replace('-', ' ', $s3_tour_name)) === 0) {
                wp_send_json_error('Tour already exists in S3: ' . $tour_name);
            }
        }

        try {
            $files_uploaded = 0;
            $total_bytes = 0;

            // Handle ZIP files
            if ($is_zip) {
                // Create temporary extraction directory
                $temp_dir = sys_get_temp_dir() . '/h3_migration_' . uniqid();
                if (!mkdir($temp_dir, 0777, true)) {
                    wp_send_json_error('Failed to create temporary directory');
                }

                $zip = new ZipArchive();
                if ($zip->open($tour_path) === TRUE) {
                    $zip->extractTo($temp_dir);
                    $zip->close();

                    // Upload extracted files
                    $result = $this->upload_directory_to_s3($s3, $temp_dir, $s3_tour_name, $files_uploaded, $total_bytes);

                    // Clean up temp directory
                    $this->delete_directory($temp_dir);

                    if (!$result) {
                        wp_send_json_error('Failed to upload tour files to S3');
                    }
                } else {
                    wp_send_json_error('Failed to extract ZIP file');
                }
            } else {
                // Handle directory
                $result = $this->upload_directory_to_s3($s3, $tour_path, $s3_tour_name, $files_uploaded, $total_bytes);

                if (!$result) {
                    wp_send_json_error('Failed to upload tour files to S3');
                }
            }

            wp_send_json_success(array(
                'message' => 'Successfully migrated tour to S3',
                'tour_name' => $tour_name,
                's3_name' => $s3_tour_name,
                'files_uploaded' => $files_uploaded,
                'bytes_uploaded' => $total_bytes,
                'size_formatted' => size_format($total_bytes)
            ));

        } catch (Exception $e) {
            error_log('H3TM Migration Error: ' . $e->getMessage());
            wp_send_json_error('Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload directory to S3
     */
    private function upload_directory_to_s3($s3, $local_path, $s3_tour_name, &$files_uploaded, &$total_bytes) {
        $success = true;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($local_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $local_file = $file->getPathname();
                $relative_path = str_replace($local_path . DIRECTORY_SEPARATOR, '', $local_file);
                $relative_path = str_replace('\\', '/', $relative_path);
                $s3_key = "tours/{$s3_tour_name}/" . $relative_path;

                // Determine content type
                $content_type = mime_content_type($local_file);
                if (!$content_type) {
                    $ext = strtolower(pathinfo($local_file, PATHINFO_EXTENSION));
                    $content_types = array(
                        'html' => 'text/html',
                        'css' => 'text/css',
                        'js' => 'application/javascript',
                        'json' => 'application/json',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'svg' => 'image/svg+xml',
                        'xml' => 'application/xml',
                        'webp' => 'image/webp',
                        'mp4' => 'video/mp4',
                        'webm' => 'video/webm',
                        'txt' => 'text/plain'
                    );
                    $content_type = $content_types[$ext] ?? 'application/octet-stream';
                }

                $result = $s3->upload_file($local_file, $s3_key, $content_type);

                if ($result) {
                    $files_uploaded++;
                    $total_bytes += filesize($local_file);
                    error_log("Successfully uploaded: $relative_path to S3");
                } else {
                    // Log but don't fail the entire migration for individual file failures
                    error_log("Warning: Failed to upload file: $local_file to $s3_key");
                    // Still count it as we may have partial success
                    $files_uploaded++;
                }
            }
        }

        // Consider it successful if we uploaded at least one file
        return $files_uploaded > 0;
    }

    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }

        rmdir($dir);
    }
}