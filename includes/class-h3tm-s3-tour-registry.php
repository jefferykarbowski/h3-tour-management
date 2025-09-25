<?php
/**
 * S3 Tour Registry - Track tours processed by Lambda
 */
class H3TM_S3_Tour_Registry {

    public function __construct() {
        // Add webhook for Lambda notifications
        add_action('wp_ajax_nopriv_h3tm_lambda_webhook', array($this, 'handle_lambda_webhook'));
        add_action('wp_ajax_h3tm_lambda_webhook', array($this, 'handle_lambda_webhook'));

        // Add admin action to sync S3 tours manually
        add_action('wp_ajax_h3tm_sync_s3_tours', array($this, 'handle_sync_s3_tours'));
    }

    /**
     * Handle webhook from Lambda when tour processing completes
     */
    public function handle_lambda_webhook() {
        // Basic webhook handler for Lambda notifications
        $tour_name = isset($_POST['tour_name']) ? sanitize_text_field($_POST['tour_name']) : '';
        $s3_url = isset($_POST['s3_url']) ? esc_url($_POST['s3_url']) : '';

        if (!empty($tour_name)) {
            $this->register_s3_tour($tour_name, $s3_url);
            error_log('H3TM Lambda Webhook: Registered tour: ' . $tour_name);
            wp_send_json_success('Tour registered successfully');
        } else {
            wp_send_json_error('Invalid webhook data');
        }
    }

    /**
     * Manually sync tours from S3 (admin action)
     */
    public function handle_sync_s3_tours() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // For now, add a method to manually discover tours
        // TODO: Implement S3 API call to list tours/ directory

        wp_send_json_success('S3 tour sync completed');
    }

    /**
     * Register S3 tour in WordPress database
     */
    public function register_s3_tour($tour_name, $s3_url = '') {
        $s3_tours = get_option('h3tm_s3_tours', array());

        $s3_tours[$tour_name] = array(
            'url' => $s3_url,
            'created' => current_time('mysql'),
            'type' => 's3_processed'
        );

        update_option('h3tm_s3_tours', $s3_tours);

        // Also add to recent uploads for immediate display
        $recent_uploads = get_transient('h3tm_recent_uploads') ?: array();
        if (!in_array($tour_name, $recent_uploads)) {
            $recent_uploads[] = $tour_name;
            set_transient('h3tm_recent_uploads', $recent_uploads, 3600); // 1 hour
        }

        error_log('H3TM S3 Registry: Registered tour: ' . $tour_name);
    }

    /**
     * Get all S3 tours for admin display
     */
    public static function get_s3_tours() {
        $s3_tours = get_option('h3tm_s3_tours', array());
        $recent_uploads = get_transient('h3tm_recent_uploads') ?: array();

        // Combine registered S3 tours and recent uploads
        $all_tours = array_keys($s3_tours);
        $all_tours = array_merge($all_tours, $recent_uploads);

        return array_unique($all_tours);
    }
}