<?php
/**
 * AWS Lambda Webhook Handler
 *
 * Handles webhook notifications from AWS Lambda tour processor
 * Replaces WordPress-based S3 download and processing limitations
 *
 * @package H3_Tour_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Lambda_Webhook {

    private $webhook_secret;
    private $tour_manager;

    public function __construct() {
        $this->webhook_secret = get_option('h3tm_lambda_webhook_secret', '');
        $this->tour_manager = new H3TM_Tour_Manager();

        // Register webhook endpoint
        add_action('wp_ajax_h3tm_lambda_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_nopriv_h3tm_lambda_webhook', array($this, 'handle_webhook'));

        // Admin hooks for webhook configuration
        add_action('admin_init', array($this, 'init_webhook_settings'));
        add_action('wp_ajax_h3tm_test_lambda_webhook', array($this, 'test_webhook_endpoint'));
        add_action('wp_ajax_h3tm_regenerate_webhook_secret', array($this, 'regenerate_webhook_secret'));
    }

    /**
     * Initialize webhook settings
     */
    public function init_webhook_settings() {
        register_setting('h3tm_s3_settings', 'h3tm_lambda_webhook_secret');
        register_setting('h3tm_s3_settings', 'h3tm_lambda_enabled');

        // Generate initial webhook secret if not exists
        if (empty($this->webhook_secret)) {
            $this->regenerate_webhook_secret(false);
        }
    }

    /**
     * Main webhook handler
     */
    public function handle_webhook() {
        // Enable detailed error logging for webhook debugging
        $log_prefix = 'H3TM Lambda Webhook: ';
        error_log($log_prefix . 'Webhook request received');

        try {
            // Verify request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                error_log($log_prefix . 'Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
                wp_send_json_error('Method not allowed', 405);
            }

            // Get raw POST data
            $raw_input = file_get_contents('php://input');
            if (empty($raw_input)) {
                error_log($log_prefix . 'Empty request body');
                wp_send_json_error('Empty request body', 400);
            }

            // Parse JSON payload
            $payload = json_decode($raw_input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log($log_prefix . 'Invalid JSON: ' . json_last_error_msg());
                wp_send_json_error('Invalid JSON payload', 400);
            }

            error_log($log_prefix . 'Payload received: ' . json_encode($payload, JSON_PRETTY_PRINT));

            // Validate payload structure
            $validation_result = $this->validate_webhook_payload($payload);
            if (!$validation_result['valid']) {
                error_log($log_prefix . 'Payload validation failed: ' . $validation_result['error']);
                wp_send_json_error($validation_result['error'], 400);
            }

            // Verify webhook authenticity (optional but recommended)
            if (!empty($this->webhook_secret)) {
                $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
                if (!$this->verify_webhook_signature($raw_input, $signature)) {
                    error_log($log_prefix . 'Webhook signature verification failed');
                    wp_send_json_error('Unauthorized', 401);
                }
            }

            // Process the webhook based on success/failure
            if ($payload['success']) {
                $result = $this->handle_processing_success($payload);
            } else {
                $result = $this->handle_processing_failure($payload);
            }

            if ($result['success']) {
                error_log($log_prefix . 'Webhook processed successfully');
                wp_send_json_success($result['message']);
            } else {
                error_log($log_prefix . 'Webhook processing failed: ' . $result['message']);
                wp_send_json_error($result['message'], 500);
            }

        } catch (Exception $e) {
            error_log($log_prefix . 'Exception: ' . $e->getMessage());
            wp_send_json_error('Internal server error', 500);
        }
    }

    /**
     * Validate webhook payload structure
     */
    private function validate_webhook_payload($payload) {
        $required_fields = ['success', 'tourName', 's3Key', 'message', 'timestamp'];

        foreach ($required_fields as $field) {
            if (!isset($payload[$field])) {
                return [
                    'valid' => false,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }

        // Validate tour name format
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $payload['tourName'])) {
            return [
                'valid' => false,
                'error' => 'Invalid tour name format'
            ];
        }

        // Validate S3 key format
        if (!preg_match('/^uploads\/.*\.zip$/', $payload['s3Key'])) {
            return [
                'valid' => false,
                'error' => 'Invalid S3 key format'
            ];
        }

        // Validate timestamp is recent (within last 24 hours)
        $timestamp = strtotime($payload['timestamp']);
        if ($timestamp === false || $timestamp < (time() - 24 * 3600)) {
            return [
                'valid' => false,
                'error' => 'Invalid or expired timestamp'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Verify webhook signature for security
     */
    private function verify_webhook_signature($payload, $signature) {
        if (empty($this->webhook_secret) || empty($signature)) {
            return false;
        }

        $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $this->webhook_secret);
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Handle successful tour processing
     */
    private function handle_processing_success($payload) {
        try {
            $tour_name = sanitize_text_field($payload['tourName']);
            $files_extracted = intval($payload['filesExtracted'] ?? 0);
            $total_size = intval($payload['totalSize'] ?? 0);
            $processing_time = intval($payload['processingTime'] ?? 0);

            // Update tour database record
            $tour_data = [
                'tour_name' => $tour_name,
                'status' => 'completed',
                'files_count' => $files_extracted,
                'total_size' => $total_size,
                'processing_time_ms' => $processing_time,
                'processed_at' => current_time('mysql'),
                'processing_method' => 'lambda'
            ];

            // Use tour manager to update or create tour record
            $tour_result = $this->tour_manager->register_completed_tour($tour_data);

            if (!$tour_result) {
                throw new Exception('Failed to register completed tour in database');
            }

            // Clear any processing transients
            $this->cleanup_processing_transients($payload['s3Key']);

            // Trigger success actions
            do_action('h3tm_tour_processing_success', $payload, $tour_data);

            // Send admin notification if configured
            $this->send_admin_notification($payload, true);

            return [
                'success' => true,
                'message' => "Tour '{$tour_name}' processed successfully: {$files_extracted} files extracted"
            ];

        } catch (Exception $e) {
            error_log('H3TM Lambda Webhook Success Handler Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to handle successful processing: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle failed tour processing
     */
    private function handle_processing_failure($payload) {
        try {
            $tour_name = sanitize_text_field($payload['tourName']);
            $error_message = sanitize_text_field($payload['message']);

            // Update tour database record
            $tour_data = [
                'tour_name' => $tour_name,
                'status' => 'failed',
                'error_message' => $error_message,
                'failed_at' => current_time('mysql'),
                'processing_method' => 'lambda'
            ];

            // Use tour manager to update tour record
            $this->tour_manager->register_failed_tour($tour_data);

            // Clear any processing transients
            $this->cleanup_processing_transients($payload['s3Key']);

            // Trigger failure actions
            do_action('h3tm_tour_processing_failure', $payload, $tour_data);

            // Send admin notification
            $this->send_admin_notification($payload, false);

            return [
                'success' => true,
                'message' => "Tour processing failure recorded for '{$tour_name}'"
            ];

        } catch (Exception $e) {
            error_log('H3TM Lambda Webhook Failure Handler Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to handle processing failure: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clean up processing transients and temporary data
     */
    private function cleanup_processing_transients($s3_key) {
        global $wpdb;

        // Find and delete related transients
        $upload_id = md5($s3_key);
        delete_transient('h3tm_s3_upload_' . $upload_id);
        delete_transient('h3tm_processing_' . $upload_id);

        // Clean up any temporary files
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/h3-s3-temp';

        if (is_dir($temp_dir)) {
            $temp_files = glob($temp_dir . '/*');
            foreach ($temp_files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 3600) { // Delete files older than 1 hour
                    unlink($file);
                }
            }
        }
    }

    /**
     * Send admin notification email
     */
    private function send_admin_notification($payload, $success) {
        if (!get_option('h3tm_lambda_notifications_enabled', false)) {
            return;
        }

        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $tour_name = $payload['tourName'];

        if ($success) {
            $subject = "Tour Processing Success - {$tour_name}";
            $message = "Tour '{$tour_name}' has been successfully processed by AWS Lambda.\n\n";
            $message .= "Files extracted: " . ($payload['filesExtracted'] ?? 'Unknown') . "\n";
            $message .= "Processing time: " . round(($payload['processingTime'] ?? 0) / 1000, 2) . " seconds\n";
            $message .= "Total size: " . size_format($payload['totalSize'] ?? 0) . "\n\n";
            $message .= "The tour is now available on your website.\n\n";
        } else {
            $subject = "Tour Processing Failed - {$tour_name}";
            $message = "Tour '{$tour_name}' processing failed in AWS Lambda.\n\n";
            $message .= "Error: " . $payload['message'] . "\n\n";
            $message .= "Please check the tour file and try uploading again.\n\n";
        }

        $message .= "Site: {$site_name}\n";
        $message .= "Time: " . current_time('mysql') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Test webhook endpoint (for admin testing)
     */
    public function test_webhook_endpoint() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        try {
            // Create test payload
            $test_payload = [
                'success' => true,
                'tourName' => 'test_tour_' . time(),
                's3Key' => 'uploads/test_tour.zip',
                'message' => 'Test webhook from WordPress admin',
                'filesExtracted' => 42,
                'totalSize' => 1024000,
                'processingTime' => 5000,
                'timestamp' => current_time('c')
            ];

            // Simulate webhook processing
            $result = $this->handle_processing_success($test_payload);

            if ($result['success']) {
                wp_send_json_success('Webhook test successful: ' . $result['message']);
            } else {
                wp_send_json_error('Webhook test failed: ' . $result['message']);
            }

        } catch (Exception $e) {
            wp_send_json_error('Webhook test error: ' . $e->getMessage());
        }
    }

    /**
     * Regenerate webhook secret
     */
    public function regenerate_webhook_secret($send_json = true) {
        if ($send_json && !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if ($send_json) {
            check_ajax_referer('h3tm_ajax_nonce', 'nonce');
        }

        // Generate cryptographically secure random secret
        $new_secret = bin2hex(random_bytes(32));
        update_option('h3tm_lambda_webhook_secret', $new_secret);
        $this->webhook_secret = $new_secret;

        if ($send_json) {
            wp_send_json_success([
                'message' => 'Webhook secret regenerated successfully',
                'webhook_url' => $this->get_webhook_url(),
                'secret_preview' => substr($new_secret, 0, 8) . '...'
            ]);
        }
    }

    /**
     * Get webhook URL for Lambda configuration
     */
    public function get_webhook_url() {
        return admin_url('admin-ajax.php?action=h3tm_lambda_webhook');
    }

    /**
     * Get webhook configuration for admin display
     */
    public function get_webhook_config() {
        return [
            'enabled' => !empty($this->webhook_secret),
            'url' => $this->get_webhook_url(),
            'secret' => $this->webhook_secret,
            'secret_preview' => !empty($this->webhook_secret) ? substr($this->webhook_secret, 0, 8) . '...' : 'Not generated'
        ];
    }

    /**
     * Get webhook statistics
     */
    public function get_webhook_stats() {
        global $wpdb;

        // Get stats from tour processing records
        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_webhooks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(CASE WHEN processing_time_ms > 0 THEN processing_time_ms ELSE NULL END) as avg_processing_time
             FROM {$wpdb->prefix}h3tm_tours
             WHERE processing_method = 'lambda'
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            ARRAY_A
        );

        return [
            'total_webhooks' => intval($stats['total_webhooks'] ?? 0),
            'successful' => intval($stats['successful'] ?? 0),
            'failed' => intval($stats['failed'] ?? 0),
            'success_rate' => $stats['total_webhooks'] > 0 ?
                round(($stats['successful'] / $stats['total_webhooks']) * 100, 1) : 0,
            'avg_processing_time' => $stats['avg_processing_time'] ?
                round($stats['avg_processing_time'] / 1000, 2) : 0
        ];
    }
}