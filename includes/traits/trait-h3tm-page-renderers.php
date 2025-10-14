<?php
/**
 * Page Renderers Trait
 *
 * Handles all admin page rendering:
 * - Main tours management page
 * - Email settings page
 * - Analytics overview page
 * - S3 settings page
 * - Supporting AJAX handlers (test email, upload tour)
 *
 * @package H3_Tour_Management
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Trait_H3TM_Page_Renderers {
    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Tours Management', 'h3-tour-management'); ?></h1>

            <div class="h3tm-admin-container">
                <!-- Upload Section - React Component -->
                <div class="h3tm-section" style="margin-bottom: 30px;">
                    <?php
                    if (class_exists('H3TM_React_Uploader')) {
                        H3TM_React_Uploader::render_uploader();
                    } else {
                        echo '<div class="notice notice-error"><p>Error: React Uploader class not found</p></div>';
                    }
                    ?>
                </div>

                <!-- Tours Section - React Component -->
                <div class="h3tm-section">
                    <h2><?php _e('Available Tours', 'h3-tour-management'); ?></h2>
                    <?php
                    if (class_exists('H3TM_React_Tours_Table')) {
                        H3TM_React_Tours_Table::render_table();
                    } else {
                        echo '<div class="notice notice-error"><p>Error: React Tours Table class not found</p></div>';
                    }
                    ?>
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
            <h1><?php _e('Plugin Settings', 'h3-tour-management'); ?></h1>

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

            <div class="h3tm-section" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;"><?php _e('Tour Metadata Management', 'h3-tour-management'); ?></h3>
                <p><?php _e('If tour names or URLs are incorrect, rebuild the metadata to match the actual S3 folder structure.', 'h3-tour-management'); ?></p>
                <button type="button" id="rebuild-tour-metadata" class="button button-secondary">
                    <?php _e('Rebuild Tour Metadata', 'h3-tour-management'); ?>
                </button>
                <span class="spinner" style="float: none; margin-left: 10px;"></span>
                <div id="rebuild-metadata-result" style="margin-top: 10px;"></div>
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

            $('#rebuild-tour-metadata').on('click', function() {
                var $button = $(this);
                var $spinner = $button.next('.spinner');
                var $result = $('#rebuild-metadata-result');

                if (!confirm('This will rebuild all tour metadata. Continue?')) {
                    return;
                }

                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $result.html('');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'h3tm_rebuild_metadata',
                        nonce: '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p>Request failed</p></div>');
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
}
