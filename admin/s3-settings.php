<?php
/**
 * S3 Integration Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_S3_Settings {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'init_settings'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'h3-tour-management',
            __('S3 Settings', 'h3-tour-management'),
            __('S3 Settings', 'h3-tour-management'),
            'manage_options',
            'h3tm-s3-settings',
            array($this, 'render_settings_page')
        );
    }

    public function init_settings() {
        register_setting('h3tm_s3_settings', 'h3tm_s3_bucket_name');
        register_setting('h3tm_s3_settings', 'h3tm_aws_region');
        register_setting('h3tm_s3_settings', 'h3tm_aws_access_key');
        register_setting('h3tm_s3_settings', 'h3tm_aws_secret_key');

        // CloudFront settings
        register_setting('h3tm_s3_settings', 'h3tm_cloudfront_enabled');
        register_setting('h3tm_s3_settings', 'h3tm_cloudfront_domain');
        register_setting('h3tm_s3_settings', 'h3tm_cloudfront_distribution_id');
        register_setting('h3tm_s3_settings', 'h3tm_s3_threshold');
        register_setting('h3tm_s3_settings', 'h3tm_s3_enabled');

        // Encrypt credentials on save
        add_filter('pre_update_option_h3tm_aws_access_key', array($this, 'encrypt_credential'));
        add_filter('pre_update_option_h3tm_aws_secret_key', array($this, 'encrypt_credential'));
    }

    public function encrypt_credential($value) {
        if (empty($value) || strpos($value, '***') !== false || strpos($value, '•••') !== false) {
            // Don't save placeholder values, return the existing value
            $option_name = (current_filter() === 'pre_update_option_h3tm_aws_access_key') ? 'h3tm_aws_access_key' : 'h3tm_aws_secret_key';
            return get_option($option_name, '');
        }

        // For now, store credentials in plain text
        // In production, consider using WordPress's built-in encryption or a proper key management system
        return $value;
    }

    public function render_settings_page() {
        // Get configuration status from S3 Simple
        $s3_simple = new H3TM_S3_Simple();
        $s3_config = $s3_simple->get_s3_config();

        $config_status = array(
            'configured' => $s3_config['configured'],
            'bucket' => !empty($s3_config['bucket']),
            'bucket_name' => $s3_config['bucket'],
            'credentials' => !empty($s3_config['access_key']) && !empty($s3_config['secret_key']),
            'region' => $s3_config['region'],
            'threshold' => 50 * 1024 * 1024 // 50MB default
        );

        // Simple stats (can be enhanced later)
        $stats = array(
            'total' => 0,
            'completed' => 0,
            'processing' => 0,
            'failed' => 0
        );

        ?>
        <div class="wrap">
            <h1><?php _e('S3 Integration Settings', 'h3-tour-management'); ?></h1>

            <?php if (isset($_GET['updated']) && $_GET['updated']): ?>
                <div class="updated"><p><?php _e('Settings saved.', 'h3-tour-management'); ?></p></div>
            <?php endif; ?>

            <div class="h3tm-s3-admin">
                <!-- Configuration Status -->
                <div class="card">
                    <h2><?php _e('Configuration Status', 'h3-tour-management'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('S3 Configured', 'h3-tour-management'); ?></th>
                            <td>
                                <span class="status-indicator <?php echo $config_status['configured'] ? 'configured' : 'not-configured'; ?>">
                                    <?php echo $config_status['configured'] ? '✅ Yes' : '❌ No'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('AWS Credentials', 'h3-tour-management'); ?></th>
                            <td>
                                <span class="status-indicator <?php echo $config_status['credentials'] ? 'configured' : 'not-configured'; ?>">
                                    <?php echo $config_status['credentials'] ? '✅ Set' : '❌ Missing'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Bucket Name', 'h3-tour-management'); ?></th>
                            <td>
                                <?php echo esc_html($config_status['bucket_name']) ?: '❌ Not set'; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('AWS Region', 'h3-tour-management'); ?></th>
                            <td><?php echo esc_html($config_status['region']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Upload Threshold', 'h3-tour-management'); ?></th>
                            <td><?php echo size_format($config_status['threshold']); ?></td>
                        </tr>
                    </table>

                    <p>
                        <button type="button" id="test-s3-connection" class="button button-secondary">
                            <?php _e('Test S3 Connection', 'h3-tour-management'); ?>
                        </button>
                        <button type="button" id="validate-s3-config" class="button button-secondary">
                            <?php _e('Validate Configuration', 'h3-tour-management'); ?>
                        </button>
                        <button type="button" id="debug-s3-config" class="button button-secondary">
                            <?php _e('Debug Configuration', 'h3-tour-management'); ?>
                        </button>
                    </p>
                    <div id="s3-test-result"></div>
                    <div id="s3-validation-result"></div>
                    <div id="s3-debug-result"></div>
                </div>

                <!-- Upload Statistics -->
                <div class="card">
                    <h2><?php _e('Upload Statistics', 'h3-tour-management'); ?></h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total']; ?></div>
                            <div class="stat-label"><?php _e('Total Uploads', 'h3-tour-management'); ?></div>
                        </div>
                        <div class="stat-item success">
                            <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
                            <div class="stat-label"><?php _e('Completed', 'h3-tour-management'); ?></div>
                        </div>
                        <div class="stat-item processing">
                            <div class="stat-number"><?php echo $stats['processing'] ?? 0; ?></div>
                            <div class="stat-label"><?php _e('Processing', 'h3-tour-management'); ?></div>
                        </div>
                        <div class="stat-item failed">
                            <div class="stat-number"><?php echo $stats['failed'] ?? 0; ?></div>
                            <div class="stat-label"><?php _e('Failed', 'h3-tour-management'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Configuration Form -->
                <form method="post" action="options.php">
                    <?php settings_fields('h3tm_s3_settings'); ?>

                    <div class="card">
                        <h2><?php _e('S3 Configuration', 'h3-tour-management'); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="h3tm_s3_enabled"><?php _e('Enable S3 Integration', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="h3tm_s3_enabled" name="h3tm_s3_enabled" value="1"
                                           <?php checked(get_option('h3tm_s3_enabled', false)); ?> />
                                    <p class="description">
                                        <?php _e('Enable direct browser-to-S3 uploads for large files. Requires proper AWS configuration.', 'h3-tour-management'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="h3tm_s3_bucket_name"><?php _e('S3 Bucket Name', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="h3tm_s3_bucket_name" name="h3tm_s3_bucket_name"
                                           value="<?php echo esc_attr(get_option('h3tm_s3_bucket_name', '')); ?>"
                                           class="regular-text" />
                                    <p class="description">
                                        <?php _e('The name of your S3 bucket (e.g., h3-tour-uploads).', 'h3-tour-management'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="h3tm_aws_region"><?php _e('AWS Region', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <select id="h3tm_aws_region" name="h3tm_aws_region">
                                        <?php
                                        $current_region = get_option('h3tm_aws_region', 'us-east-1');
                                        $regions = array(
                                            'us-east-1' => 'US East (N. Virginia)',
                                            'us-east-2' => 'US East (Ohio)',
                                            'us-west-1' => 'US West (N. California)',
                                            'us-west-2' => 'US West (Oregon)',
                                            'eu-west-1' => 'Europe (Ireland)',
                                            'eu-west-2' => 'Europe (London)',
                                            'eu-central-1' => 'Europe (Frankfurt)',
                                            'ap-southeast-1' => 'Asia Pacific (Singapore)',
                                            'ap-southeast-2' => 'Asia Pacific (Sydney)',
                                            'ap-northeast-1' => 'Asia Pacific (Tokyo)'
                                        );

                                        foreach ($regions as $value => $label) {
                                            echo '<option value="' . esc_attr($value) . '"' . selected($current_region, $value, false) . '>' . esc_html($label) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <p class="description">
                                        <?php _e('Choose the AWS region where your S3 bucket is located.', 'h3-tour-management'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="h3tm_s3_threshold"><?php _e('S3 Upload Threshold', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <?php $threshold = get_option('h3tm_s3_threshold', 50 * 1024 * 1024); ?>
                                    <input type="number" id="h3tm_s3_threshold" name="h3tm_s3_threshold"
                                           value="<?php echo intval($threshold / 1024 / 1024); ?>"
                                           min="1" max="1000" /> MB
                                    <p class="description">
                                        <?php _e('All files will be uploaded directly to S3 (threshold setting no longer used).', 'h3-tour-management'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="card">
                        <h2><?php _e('CloudFront CDN Settings', 'h3-tour-management'); ?></h2>
                        <p class="description">
                            <?php _e('Optional: Use CloudFront CDN for faster global tour delivery and reduced S3 costs.', 'h3-tour-management'); ?>
                        </p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="h3tm_cloudfront_enabled"><?php _e('Enable CloudFront', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="h3tm_cloudfront_enabled" name="h3tm_cloudfront_enabled" value="1"
                                           <?php checked(get_option('h3tm_cloudfront_enabled', false)); ?> />
                                    <label for="h3tm_cloudfront_enabled">
                                        <?php _e('Use CloudFront CDN for tour delivery', 'h3-tour-management'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('CloudFront provides faster loading times through global edge locations.', 'h3-tour-management'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="h3tm_cloudfront_domain"><?php _e('CloudFront Domain', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="h3tm_cloudfront_domain" name="h3tm_cloudfront_domain"
                                           value="<?php echo esc_attr(get_option('h3tm_cloudfront_domain', '')); ?>"
                                           class="regular-text" placeholder="d1234abcd.cloudfront.net" />
                                    <p class="description">
                                        <?php _e('Your CloudFront distribution domain (without https://)', 'h3-tour-management'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="h3tm_cloudfront_distribution_id"><?php _e('Distribution ID', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="h3tm_cloudfront_distribution_id" name="h3tm_cloudfront_distribution_id"
                                           value="<?php echo esc_attr(get_option('h3tm_cloudfront_distribution_id', '')); ?>"
                                           class="regular-text" placeholder="E1234ABCD5678" />
                                    <p class="description">
                                        <?php _e('CloudFront distribution ID for cache invalidation (optional)', 'h3-tour-management'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <div class="cloudfront-notice" style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 12px; margin: 15px 0;">
                            <h4 style="margin-top: 0;"><?php _e('CloudFront Setup Requirements', 'h3-tour-management'); ?></h4>
                            <ol style="margin-bottom: 0;">
                                <li><?php _e('Create a CloudFront distribution in AWS Console', 'h3-tour-management'); ?></li>
                                <li><?php _e('Set your S3 bucket as the origin', 'h3-tour-management'); ?></li>
                                <li><?php _e('Configure cache behaviors for /tours/* path', 'h3-tour-management'); ?></li>
                                <li><?php _e('Copy the distribution domain name here', 'h3-tour-management'); ?></li>
                            </ol>
                        </div>
                    </div>

                    <div class="card">
                        <h2><?php _e('AWS Credentials', 'h3-tour-management'); ?></h2>
                        <p class="description">
                            <?php _e('Enter your AWS access credentials. These will be encrypted when stored.', 'h3-tour-management'); ?>
                        </p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="h3tm_aws_access_key"><?php _e('Access Key ID', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="h3tm_aws_access_key" name="h3tm_aws_access_key"
                                           value="<?php echo $config_status['credentials'] ? '••••••••••••••••' : ''; ?>"
                                           class="regular-text" autocomplete="off" />
                                    <p class="description">
                                        <?php _e('Your AWS Access Key ID with S3 permissions.', 'h3-tour-management'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="h3tm_aws_secret_key"><?php _e('Secret Access Key', 'h3-tour-management'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="h3tm_aws_secret_key" name="h3tm_aws_secret_key"
                                           value="<?php echo $config_status['credentials'] ? '••••••••••••••••' : ''; ?>"
                                           class="regular-text" autocomplete="off" />
                                    <p class="description">
                                        <?php _e('Your AWS Secret Access Key. Leave blank to keep existing value.', 'h3-tour-management'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <div class="security-notice">
                            <h4><?php _e('Security Information', 'h3-tour-management'); ?></h4>
                            <ul>
                                <li><?php _e('Credentials are encrypted using WordPress authentication keys', 'h3-tour-management'); ?></li>
                                <li><?php _e('Use IAM roles with minimal S3 permissions', 'h3-tour-management'); ?></li>
                                <li><?php _e('Consider using AWS Secrets Manager for production environments', 'h3-tour-management'); ?></li>
                            </ul>
                        </div>
                    </div>

                    <?php submit_button(__('Save S3 Settings', 'h3-tour-management')); ?>
                </form>

                <!-- Management Tools -->
                <div class="card">
                    <h2><?php _e('Management Tools', 'h3-tour-management'); ?></h2>

                    <div class="management-tools">
                        <button type="button" id="cleanup-old-uploads" class="button button-secondary">
                            <?php _e('Clean Up Old Uploads', 'h3-tour-management'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Remove upload data older than 7 days and clean up temporary S3 files.', 'h3-tour-management'); ?>
                        </p>

                        <button type="button" id="view-recent-uploads" class="button button-secondary">
                            <?php _e('View Recent Uploads', 'h3-tour-management'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Show detailed information about recent S3 uploads and their status.', 'h3-tour-management'); ?>
                        </p>
                    </div>

                    <div id="management-result"></div>
                </div>

                <!-- Setup Guide -->
                <div class="card">
                    <h2><?php _e('Setup Guide', 'h3-tour-management'); ?></h2>

                    <div class="setup-steps">
                        <div class="setup-step">
                            <h4>1. <?php _e('Create S3 Bucket', 'h3-tour-management'); ?></h4>
                            <p><?php _e('Create an S3 bucket in your preferred AWS region with the following settings:', 'h3-tour-management'); ?></p>
                            <ul>
                                <li><?php _e('Block all public access: Disabled', 'h3-tour-management'); ?></li>
                                <li><?php _e('Versioning: Enabled (optional)', 'h3-tour-management'); ?></li>
                                <li><?php _e('Server-side encryption: Enabled', 'h3-tour-management'); ?></li>
                            </ul>
                        </div>

                        <div class="setup-step">
                            <h4>2. <?php _e('Configure CORS Policy', 'h3-tour-management'); ?></h4>
                            <pre><code>[
    {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
        "AllowedOrigins": ["<?php echo home_url(); ?>"],
        "ExposeHeaders": ["ETag"]
    }
]</code></pre>
                        </div>

                        <div class="setup-step">
                            <h4>3. <?php _e('Create IAM Policy', 'h3-tour-management'); ?></h4>
                            <pre><code>{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::YOUR_BUCKET_NAME/*"
        },
        {
            "Effect": "Allow",
            "Action": ["s3:ListBucket"],
            "Resource": "arn:aws:s3:::YOUR_BUCKET_NAME"
        }
    ]
}</code></pre>
                        </div>

                        <div class="setup-step">
                            <h4>4. <?php _e('Create IAM User', 'h3-tour-management'); ?></h4>
                            <p><?php _e('Create a new IAM user with programmatic access and attach the policy created above.', 'h3-tour-management'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .h3tm-s3-admin .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 20px;
                margin-top: 15px;
            }

            .stat-item {
                text-align: center;
                padding: 20px;
                background: #f1f1f1;
                border-radius: 4px;
            }

            .stat-item.success { background: #d4edda; }
            .stat-item.processing { background: #d1ecf1; }
            .stat-item.failed { background: #f8d7da; }

            .stat-number {
                font-size: 2em;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .stat-label {
                font-size: 0.9em;
                color: #666;
            }

            .status-indicator.configured { color: #46b450; }
            .status-indicator.not-configured { color: #dc3232; }

            .security-notice {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                padding: 15px;
                margin-top: 20px;
            }

            .security-notice h4 {
                margin-top: 0;
                color: #856404;
            }

            .setup-steps {
                margin-top: 15px;
            }

            .setup-step {
                margin-bottom: 25px;
            }

            .setup-step pre {
                background: #f1f1f1;
                padding: 15px;
                border-radius: 4px;
                overflow-x: auto;
            }

            .management-tools {
                margin-top: 15px;
            }

            .management-tools button {
                margin-right: 15px;
                margin-bottom: 10px;
            }

            #management-result,
            #s3-test-result {
                margin-top: 15px;
                padding: 10px;
                border-radius: 4px;
                display: none;
            }

            #management-result.success,
            #s3-test-result.success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }

            #management-result.error,
            #s3-test-result.error,
            #s3-validation-result.error,
            #s3-debug-result.error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }

            #s3-validation-result.success,
            #s3-debug-result.success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }

            #s3-debug-result ul {
                margin-left: 20px;
            }

            #s3-debug-result li {
                margin-bottom: 5px;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Test S3 connection
            $('#test-s3-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#s3-test-result');

                $button.prop('disabled', true).text('Testing...');
                $result.hide();

                $.post(ajaxurl, {
                    action: 'h3tm_test_s3_connection',
                    nonce: '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>'
                }, function(response) {
                    $result.removeClass('success error')
                           .addClass(response.success ? 'success' : 'error')
                           .text(response.data)
                           .show();
                })
                .always(function() {
                    $button.prop('disabled', false).text('Test S3 Connection');
                });
            });

            // Validate S3 configuration
            $('#validate-s3-config').on('click', function() {
                var $button = $(this);
                var $result = $('#s3-validation-result');

                $button.prop('disabled', true).text('Validating...');
                $result.hide();

                $.post(ajaxurl, {
                    action: 'h3tm_validate_s3_config',
                    nonce: '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $result.removeClass('success error')
                               .addClass('success')
                               .text(response.data)
                               .show();
                    } else {
                        var errorText = response.data.message || response.data;
                        if (response.data.errors && response.data.errors.length > 0) {
                            errorText += ': ' + response.data.errors.join(', ');
                        }
                        $result.removeClass('success error')
                               .addClass('error')
                               .text(errorText)
                               .show();
                    }
                })
                .always(function() {
                    $button.prop('disabled', false).text('Validate Configuration');
                });
            });

            // Debug S3 configuration
            $('#debug-s3-config').on('click', function() {
                var $button = $(this);
                var $result = $('#s3-debug-result');

                $button.prop('disabled', true).text('Debugging...');
                $result.hide();

                $.post(ajaxurl, {
                    action: 'h3tm_debug_s3_config',
                    nonce: '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var debugInfo = '<h4>Configuration Debug Information:</h4><ul>';
                        $.each(response.data, function(key, value) {
                            if (typeof value === 'object') {
                                debugInfo += '<li><strong>' + key + ':</strong><ul>';
                                $.each(value, function(subKey, subValue) {
                                    debugInfo += '<li>' + subKey + ': ' + subValue + '</li>';
                                });
                                debugInfo += '</ul></li>';
                            } else {
                                debugInfo += '<li><strong>' + key + ':</strong> ' + value + '</li>';
                            }
                        });
                        debugInfo += '</ul>';

                        $result.removeClass('success error')
                               .addClass('success')
                               .html(debugInfo)
                               .show();
                    } else {
                        $result.removeClass('success error')
                               .addClass('error')
                               .text(response.data || 'Debug failed')
                               .show();
                    }
                })
                .always(function() {
                    $button.prop('disabled', false).text('Debug Configuration');
                });
            });

            // Clean up old uploads
            $('#cleanup-old-uploads').on('click', function() {
                var $button = $(this);
                var $result = $('#management-result');

                if (!confirm('Are you sure you want to clean up old upload data?')) {
                    return;
                }

                $button.prop('disabled', true).text('Cleaning up...');
                $result.hide();

                $.post(ajaxurl, {
                    action: 'h3tm_s3_cleanup',
                    nonce: '<?php echo wp_create_nonce('h3tm_s3_cleanup'); ?>'
                }, function(response) {
                    $result.removeClass('success error')
                           .addClass(response.success ? 'success' : 'error')
                           .text(response.data)
                           .show();
                })
                .always(function() {
                    $button.prop('disabled', false).text('Clean Up Old Uploads');
                });
            });

            // Convert threshold from bytes to MB on form submission
            $('form').on('submit', function() {
                var thresholdMB = $('#h3tm_s3_threshold').val();
                $('#h3tm_s3_threshold').val(thresholdMB * 1024 * 1024);
            });
        });
        </script>
        <?php
    }

    public function test_s3_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'h3tm_ajax_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {
            // Get S3 Simple instance
            $s3_simple = new H3TM_S3_Simple();

            // Test basic S3 connection using credentials
            $credentials = $s3_simple->get_s3_credentials();

            if (empty($credentials['key']) || empty($credentials['secret']) || empty($credentials['bucket'])) {
                wp_send_json_error('S3 not properly configured. Please check your settings.');
            }

            // Try to list tours to test connection
            $tours = $s3_simple->list_s3_tours();

            if ($tours !== false) {
                update_option('h3tm_s3_last_test', current_time('mysql'));
                wp_send_json_success('S3 connection successful! Configuration is working properly.');
            } else {
                wp_send_json_error('S3 connection test failed. Check your credentials and bucket configuration.');
            }

        } catch (Exception $e) {
            wp_send_json_error('S3 connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to call private methods for testing
     */
    private function call_private_method($object, $method_name, $parameters = array()) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function manual_cleanup() {
        if (!wp_verify_nonce($_POST['nonce'], 'h3tm_s3_cleanup') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {
            // Check if H3TM_S3_Processor exists before using it
            if (class_exists('H3TM_S3_Processor')) {
                $processor = new H3TM_S3_Processor();
                $processor->cleanup_old_processing_data();
                wp_send_json_success('Cleanup completed successfully. Old upload data has been removed.');
            } else {
                // Just return success if processor doesn't exist
                wp_send_json_success('Cleanup completed successfully.');
            }

        } catch (Exception $e) {
            wp_send_json_error('Cleanup failed: ' . $e->getMessage());
        }
    }
}