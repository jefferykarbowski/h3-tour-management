<?php
/**
 * Tour Processing Status Manager
 *
 * Manages tour processing status lifecycle in database
 * Tracks: uploading → processing → completed/failed
 *
 * @package H3_Tour_Management
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Tour_Processing {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'h3tm_tour_processing';

        // Register activation hook to create table
        register_activation_hook(H3TM_PLUGIN_FILE, array($this, 'create_table'));

        // Register AJAX handlers
        add_action('wp_ajax_h3tm_get_tour_status', array($this, 'ajax_get_tour_status'));
        add_action('wp_ajax_h3tm_get_all_tour_statuses', array($this, 'ajax_get_all_tour_statuses'));
        add_action('wp_ajax_h3tm_clear_tour_cache', array($this, 'ajax_clear_tour_cache'));
        add_action('wp_ajax_h3tm_mark_tour_processing', array($this, 'ajax_mark_tour_processing'));
    }

    /**
     * Create the tour processing table
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tour_name VARCHAR(255) NOT NULL,
            s3_folder_name VARCHAR(255) NOT NULL,
            status ENUM('uploading', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'uploading',
            s3_key VARCHAR(512),
            s3_bucket VARCHAR(255),
            files_count INT DEFAULT 0,
            total_size BIGINT DEFAULT 0,
            processing_started_at DATETIME,
            processing_completed_at DATETIME,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_tour (tour_name),
            KEY status_idx (status),
            KEY created_idx (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        error_log('H3TM: Tour processing table created/updated');
    }

    /**
     * Start tracking a new tour upload
     */
    public function start_upload($tour_name, $s3_key, $s3_bucket = '') {
        global $wpdb;

        $s3_folder_name = str_replace(' ', '-', $tour_name);

        $wpdb->replace(
            $this->table_name,
            array(
                'tour_name' => $tour_name,
                's3_folder_name' => $s3_folder_name,
                'status' => 'uploading',
                's3_key' => $s3_key,
                's3_bucket' => $s3_bucket,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        error_log("H3TM Processing: Started tracking upload for '{$tour_name}'");
    }

    /**
     * Update status to processing (Lambda started)
     */
    public function mark_processing($tour_name) {
        global $wpdb;

        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'processing',
                'processing_started_at' => current_time('mysql')
            ),
            array('tour_name' => $tour_name),
            array('%s', '%s'),
            array('%s')
        );

        error_log("H3TM Processing: '{$tour_name}' marked as processing");
    }

    /**
     * Mark tour as completed
     */
    public function mark_completed($tour_name, $files_count = 0, $total_size = 0) {
        global $wpdb;

        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'completed',
                'files_count' => $files_count,
                'total_size' => $total_size,
                'processing_completed_at' => current_time('mysql')
            ),
            array('tour_name' => $tour_name),
            array('%s', '%d', '%d', '%s'),
            array('%s')
        );

        error_log("H3TM Processing: '{$tour_name}' marked as completed ({$files_count} files)");
    }

    /**
     * Mark tour as failed
     */
    public function mark_failed($tour_name, $error_message = '') {
        global $wpdb;

        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'failed',
                'error_message' => $error_message,
                'processing_completed_at' => current_time('mysql')
            ),
            array('tour_name' => $tour_name),
            array('%s', '%s', '%s'),
            array('%s')
        );

        error_log("H3TM Processing: '{$tour_name}' marked as failed: {$error_message}");
    }

    /**
     * Get status for a tour
     */
    public function get_status($tour_name) {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE tour_name = %s",
            $tour_name
        ), ARRAY_A);

        return $result;
    }

    /**
     * Get all processing/uploading tours
     */
    public function get_active_tours() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name}
             WHERE status IN ('uploading', 'processing')
             ORDER BY created_at DESC",
            ARRAY_A
        );
    }

    /**
     * Get all tours with their status
     */
    public function get_all_with_status() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name}
             ORDER BY created_at DESC",
            ARRAY_A
        );
    }

    /**
     * Clean up old completed/failed tours (older than 30 days)
     */
    public function cleanup_old_records() {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$this->table_name}
             WHERE status IN ('completed', 'failed')
             AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        if ($deleted > 0) {
            error_log("H3TM Processing: Cleaned up {$deleted} old records");
        }

        return $deleted;
    }

    /**
     * Delete a tour record
     */
    public function delete_tour($tour_name) {
        global $wpdb;

        $wpdb->delete(
            $this->table_name,
            array('tour_name' => $tour_name),
            array('%s')
        );

        error_log("H3TM Processing: Deleted record for '{$tour_name}'");
    }

    /**
     * AJAX handler: Get status for a single tour
     */
    public function ajax_get_tour_status() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $tour_name = isset($_POST['tour_name']) ? sanitize_text_field($_POST['tour_name']) : '';

        if (empty($tour_name)) {
            wp_send_json_error('Tour name required');
        }

        $status = $this->get_status($tour_name);

        if ($status) {
            wp_send_json_success($status);
        } else {
            // Tour not in processing table - assume completed/available
            wp_send_json_success(array(
                'tour_name' => $tour_name,
                'status' => 'completed'
            ));
        }
    }

    /**
     * AJAX handler: Get status for all tours
     */
    public function ajax_get_all_tour_statuses() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $statuses = $this->get_all_with_status();

        // Create lookup map
        $status_map = array();
        foreach ($statuses as $status) {
            $status_map[$status['tour_name']] = $status['status'];
        }

        wp_send_json_success($status_map);
    }

    /**
     * AJAX handler: Clear tour cache
     */
    public function ajax_clear_tour_cache() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        delete_transient('h3tm_s3_tours_cache');
        error_log('H3TM: Tour cache cleared via AJAX');

        wp_send_json_success('Cache cleared');
    }

    /**
     * AJAX handler: Mark tour as processing (when S3 upload completes)
     */
    public function ajax_mark_tour_processing() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $tour_name = isset($_POST['tour_name']) ? sanitize_text_field($_POST['tour_name']) : '';

        if (empty($tour_name)) {
            wp_send_json_error('Tour name required');
        }

        $this->mark_processing($tour_name);

        wp_send_json_success('Tour marked as processing');
    }
}
