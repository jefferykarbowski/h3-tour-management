<?php
/**
 * AWS Lambda Integration Manager
 *
 * Manages integration between WordPress and AWS Lambda tour processing system
 * Provides admin interface for Lambda configuration and monitoring
 *
 * @package H3_Tour_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Lambda_Integration {

    private $webhook_handler;

    public function __construct() {
        $this->webhook_handler = new H3TM_Lambda_Webhook();

        // Add admin hooks
        add_action('wp_ajax_h3tm_get_lambda_stats', array($this, 'get_lambda_stats'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Add Lambda processing option to upload forms
        add_action('h3tm_upload_options', array($this, 'add_lambda_option'));
        add_filter('h3tm_process_upload_method', array($this, 'determine_processing_method'), 10, 2);
    }

    /**
     * Enqueue admin scripts for Lambda integration
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'h3tm') === false) {
            return;
        }

        wp_enqueue_script('h3tm-lambda-admin', plugin_dir_url(__FILE__) . '../assets/js/lambda-admin.js', array('jquery'), '2.2.0', true);

        wp_localize_script('h3tm-lambda-admin', 'h3tm_lambda', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('h3tm_ajax_nonce'),
            'webhook_url' => $this->webhook_handler->get_webhook_url()
        ));
    }

    /**
     * Get Lambda processing statistics for admin display
     */
    public function get_lambda_stats() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        try {
            $stats = $this->webhook_handler->get_webhook_stats();
            wp_send_json_success($stats);
        } catch (Exception $e) {
            wp_send_json_error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    /**
     * Add Lambda processing option to upload forms
     */
    public function add_lambda_option() {
        $lambda_enabled = get_option('h3tm_lambda_enabled', false);
        $webhook_config = $this->webhook_handler->get_webhook_config();

        ?>
        <div class="lambda-processing-option">
            <h4><?php _e('Processing Method', 'h3-tour-management'); ?></h4>

            <?php if ($webhook_config['enabled']): ?>
                <label>
                    <input type="radio" name="processing_method" value="lambda" <?php checked($lambda_enabled, true); ?>>
                    <strong><?php _e('AWS Lambda Processing', 'h3-tour-management'); ?></strong>
                    <p class="description">
                        <?php _e('Serverless processing with no memory limits, faster extraction, and automatic handling of large files.', 'h3-tour-management'); ?>
                    </p>
                </label>

                <label>
                    <input type="radio" name="processing_method" value="wordpress" <?php checked($lambda_enabled, false); ?>>
                    <strong><?php _e('WordPress Processing', 'h3-tour-management'); ?></strong>
                    <p class="description">
                        <?php _e('Traditional WordPress processing (limited to smaller files due to memory constraints).', 'h3-tour-management'); ?>
                    </p>
                </label>
            <?php else: ?>
                <div class="notice notice-info inline">
                    <p>
                        <?php _e('AWS Lambda processing is not configured. Using WordPress processing.', 'h3-tour-management'); ?>
                        <a href="<?php echo admin_url('admin.php?page=h3tm-s3-settings'); ?>"><?php _e('Configure Lambda', 'h3-tour-management'); ?></a>
                    </p>
                </div>

                <input type="hidden" name="processing_method" value="wordpress">
            <?php endif; ?>
        </div>

        <style>
        .lambda-processing-option {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }

        .lambda-processing-option label {
            display: block;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .lambda-processing-option input[type="radio"] {
            margin-right: 8px;
        }

        .lambda-processing-option .description {
            margin-left: 24px;
            margin-top: 5px;
            font-size: 13px;
            color: #666;
        }
        </style>
        <?php
    }

    /**
     * Determine processing method based on user selection and file characteristics
     */
    public function determine_processing_method($current_method, $file_data) {
        // Check if Lambda is available
        $webhook_config = $this->webhook_handler->get_webhook_config();
        if (!$webhook_config['enabled']) {
            return 'wordpress';
        }

        // Check user preference
        $selected_method = $_POST['processing_method'] ?? 'wordpress';

        if ($selected_method === 'lambda') {
            return 'lambda';
        }

        // Auto-select Lambda for large files
        $file_size = $file_data['size'] ?? 0;
        $size_threshold = get_option('h3tm_lambda_auto_threshold', 100 * 1024 * 1024); // 100MB default

        if ($file_size > $size_threshold) {
            return 'lambda';
        }

        return $current_method;
    }

    /**
     * Get Lambda deployment status and configuration
     */
    public function get_deployment_status() {
        $webhook_config = $this->webhook_handler->get_webhook_config();
        $stats = $this->webhook_handler->get_webhook_stats();

        return array(
            'deployed' => $webhook_config['enabled'],
            'webhook_url' => $webhook_config['url'],
            'secret_configured' => !empty($webhook_config['secret']),
            'total_processed' => $stats['total_webhooks'],
            'success_rate' => $stats['success_rate'],
            'avg_processing_time' => $stats['avg_processing_time'],
            'last_30_days' => array(
                'successful' => $stats['successful'],
                'failed' => $stats['failed']
            )
        );
    }

    /**
     * Generate deployment instructions for admin
     */
    public function get_deployment_instructions() {
        $webhook_url = $this->webhook_handler->get_webhook_url();
        $bucket_name = get_option('h3tm_s3_bucket_name', '');

        return array(
            'terraform_vars' => array(
                'bucket_name' => $bucket_name,
                'webhook_url' => $webhook_url,
                'environment' => 'prod',
                'aws_region' => get_option('h3tm_aws_region', 'us-west-2')
            ),
            'deployment_steps' => array(
                '1. Download Lambda deployment package from plugin directory',
                '2. Configure terraform.tfvars with the values shown below',
                '3. Run: cd deployment && ./deploy.sh deploy',
                '4. Test deployment with validation script',
                '5. Enable Lambda processing in WordPress settings'
            ),
            'terraform_config' => sprintf(
                'bucket_name = "%s"' . "\n" .
                'webhook_url = "%s"' . "\n" .
                'environment = "prod"' . "\n" .
                'aws_region = "%s"',
                $bucket_name,
                $webhook_url,
                get_option('h3tm_aws_region', 'us-west-2')
            )
        );
    }

    /**
     * Test Lambda connectivity and configuration
     */
    public function test_lambda_connectivity() {
        // This would test the Lambda function if deployed
        // For now, we test webhook endpoint accessibility

        $webhook_url = $this->webhook_handler->get_webhook_url();

        // Test webhook endpoint
        $response = wp_remote_post($webhook_url, array(
            'timeout' => 10,
            'body' => json_encode(array(
                'test' => true,
                'source' => 'wordpress_admin'
            )),
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Webhook endpoint test failed: ' . $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            return array(
                'success' => true,
                'message' => 'Webhook endpoint is accessible and responding correctly'
            );
        } else {
            return array(
                'success' => false,
                'message' => "Webhook endpoint returned HTTP $status_code"
            );
        }
    }

    /**
     * Get Lambda processing benefits for admin display
     */
    public function get_lambda_benefits() {
        return array(
            'No Memory Limits' => 'Process files up to 5GB without WordPress memory constraints',
            'No Download Restrictions' => 'Lambda has native S3 access, eliminating 403 errors',
            'Automatic Processing' => 'Files are processed immediately upon S3 upload',
            'Faster Processing' => 'Dedicated compute resources with up to 10GB RAM',
            'Cost Effective' => 'Pay only when processing tours (~$0.02 per tour)',
            'Built-in Monitoring' => 'CloudWatch logs and metrics included',
            'Error Handling' => 'Automatic retry and error notification via SNS',
            'Scalable' => 'Handle multiple concurrent uploads automatically'
        );
    }

    /**
     * Get processing comparison data
     */
    public function get_processing_comparison() {
        return array(
            'WordPress Processing' => array(
                'Max File Size' => '500MB (memory dependent)',
                'Processing Time' => '2-5 minutes (limited by PHP timeout)',
                'Reliability' => 'Subject to server resources and conflicts',
                'Cost' => 'Server resources used during processing',
                'Monitoring' => 'WordPress logs only',
                'Concurrent Processing' => 'Limited by server capacity'
            ),
            'Lambda Processing' => array(
                'Max File Size' => '5GB (configurable)',
                'Processing Time' => '30 seconds - 15 minutes (no PHP limits)',
                'Reliability' => 'Dedicated resources, isolated processing',
                'Cost' => '~$0.02 per tour processed',
                'Monitoring' => 'CloudWatch metrics, SNS alerts, detailed logs',
                'Concurrent Processing' => 'Up to 1000 simultaneous extractions'
            )
        );
    }
}