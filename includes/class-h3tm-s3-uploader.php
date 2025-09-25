<?php
/**
 * S3 Direct Upload Handler for H3 Tour Management
 *
 * Handles secure S3 direct uploads with presigned URLs and zero credential exposure
 *
 * @package H3_Tour_Management
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_S3_Uploader {

    /**
     * Upload states for tracking
     */
    const STATE_PENDING = 'pending';
    const STATE_UPLOADING = 'uploading';
    const STATE_COMPLETED = 'completed';
    const STATE_FAILED = 'failed';

    /**
     * Initialize S3 uploader
     */
    public function __construct() {
        add_action('wp_ajax_h3tm_get_s3_upload_url', array($this, 'handle_get_upload_url'));
        add_action('wp_ajax_h3tm_s3_upload_complete', array($this, 'handle_upload_complete'));
        add_action('wp_ajax_h3tm_s3_upload_status', array($this, 'handle_upload_status'));
    }

    /**
     * Handle request for S3 presigned upload URL
     */
    public function handle_get_upload_url() {
        // Verify security
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_s3_upload')) {
            wp_die(__('Security check failed', 'h3-tour-management'));
        }

        // Check rate limiting
        if (!H3TM_Security::check_rate_limit('s3_upload_request', get_current_user_id())) {
            wp_send_json_error(array(
                'message' => __('Rate limit exceeded. Please wait before requesting another upload.', 'h3-tour-management')
            ));
        }

        // Validate input
        $filename = sanitize_file_name($_POST['filename'] ?? '');
        $filesize = intval($_POST['filesize'] ?? 0);

        if (empty($filename) || $filesize <= 0) {
            wp_send_json_error(array(
                'message' => __('Invalid file parameters', 'h3-tour-management')
            ));
        }

        // Validate file
        $validation_result = $this->validate_upload_request($filename, $filesize);
        if (!$validation_result['valid']) {
            wp_send_json_error(array(
                'message' => $validation_result['error']
            ));
        }

        // Check AWS configuration
        if (!H3TM_AWS_Security::has_credentials()) {
            wp_send_json_error(array(
                'message' => __('AWS S3 not configured. Please configure AWS settings.', 'h3-tour-management')
            ));
        }

        // Generate unique object key
        $object_key = $this->generate_object_key($filename);

        // Create upload session
        $upload_session = $this->create_upload_session($object_key, $filename, $filesize);
        if (!$upload_session) {
            wp_send_json_error(array(
                'message' => __('Failed to create upload session', 'h3-tour-management')
            ));
        }

        // Generate presigned URL
        $upload_conditions = array(
            array('content-length-range', $filesize, $filesize), // Exact file size
            array('eq', '$Content-Type', 'application/zip')
        );

        $presigned_data = H3TM_AWS_Security::generate_presigned_upload_url($object_key, $upload_conditions);
        if (!$presigned_data) {
            wp_send_json_error(array(
                'message' => __('Failed to generate upload URL. Please check AWS configuration.', 'h3-tour-management')
            ));
        }

        // Log upload request
        H3TM_Security::log_security_event('s3_upload_requested', array(
            'session_id' => $upload_session['id'],
            'filename' => $filename,
            'filesize' => $filesize,
            'object_key' => $object_key
        ));

        wp_send_json_success(array(
            'upload_url' => $presigned_data['url'],
            'fields' => $presigned_data['fields'],
            'session_id' => $upload_session['id'],
            'object_key' => $object_key,
            'expires' => $presigned_data['expires']
        ));
    }

    /**
     * Handle upload completion notification
     */
    public function handle_upload_complete() {
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_s3_upload')) {
            wp_die(__('Security check failed', 'h3-tour-management'));
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $success = filter_var($_POST['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $error_message = sanitize_text_field($_POST['error'] ?? '');

        if (empty($session_id)) {
            wp_send_json_error(array(
                'message' => __('Invalid session ID', 'h3-tour-management')
            ));
        }

        // Get upload session
        $session = $this->get_upload_session($session_id);
        if (!$session) {
            wp_send_json_error(array(
                'message' => __('Upload session not found', 'h3-tour-management')
            ));
        }

        if ($success) {
            // Verify the upload actually succeeded by checking S3
            $verification_result = $this->verify_s3_upload($session['object_key']);

            if ($verification_result['verified']) {
                // Update session status
                $this->update_upload_session($session_id, array(
                    'status' => self::STATE_COMPLETED,
                    'completed_at' => current_time('mysql'),
                    'file_size_actual' => $verification_result['size']
                ));

                // Process the uploaded tour
                $process_result = $this->process_uploaded_tour($session);

                H3TM_Security::log_security_event('s3_upload_completed', array(
                    'session_id' => $session_id,
                    'object_key' => $session['object_key'],
                    'file_size' => $verification_result['size']
                ));

                wp_send_json_success(array(
                    'message' => __('Upload completed successfully', 'h3-tour-management'),
                    'tour_id' => $process_result['tour_id'] ?? null
                ));

            } else {
                // Upload verification failed
                $this->update_upload_session($session_id, array(
                    'status' => self::STATE_FAILED,
                    'error_message' => 'Upload verification failed'
                ));

                H3TM_Security::log_security_event('s3_upload_verification_failed', array(
                    'session_id' => $session_id,
                    'object_key' => $session['object_key']
                ), 'error');

                wp_send_json_error(array(
                    'message' => __('Upload verification failed', 'h3-tour-management')
                ));
            }

        } else {
            // Upload failed
            $this->update_upload_session($session_id, array(
                'status' => self::STATE_FAILED,
                'error_message' => $error_message
            ));

            H3TM_Security::log_security_event('s3_upload_failed', array(
                'session_id' => $session_id,
                'error' => $error_message
            ), 'warning');

            wp_send_json_error(array(
                'message' => $error_message ?: __('Upload failed', 'h3-tour-management')
            ));
        }
    }

    /**
     * Handle upload status check
     */
    public function handle_upload_status() {
        if (!H3TM_Security::verify_ajax_request($_POST['nonce'] ?? '', 'h3tm_s3_upload')) {
            wp_die(__('Security check failed', 'h3-tour-management'));
        }

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');

        if (empty($session_id)) {
            wp_send_json_error(array(
                'message' => __('Invalid session ID', 'h3-tour-management')
            ));
        }

        $session = $this->get_upload_session($session_id);
        if (!$session) {
            wp_send_json_error(array(
                'message' => __('Upload session not found', 'h3-tour-management')
            ));
        }

        wp_send_json_success(array(
            'status' => $session['status'],
            'progress' => $session['progress'] ?? 0,
            'error_message' => $session['error_message'] ?? null,
            'created_at' => $session['created_at'],
            'updated_at' => $session['updated_at']
        ));
    }

    /**
     * Validate upload request parameters
     *
     * @param string $filename Original filename
     * @param int $filesize File size in bytes
     * @return array Validation result
     */
    private function validate_upload_request($filename, $filesize) {
        $result = array('valid' => false, 'error' => '');

        // Validate filename
        $file_info = pathinfo($filename);
        $extension = strtolower($file_info['extension'] ?? '');

        if ($extension !== 'zip') {
            $result['error'] = __('Only ZIP files are allowed', 'h3-tour-management');
            return $result;
        }

        // Validate file size (max 1GB for S3 direct upload)
        if ($filesize > H3TM_AWS_Security::MAX_OBJECT_SIZE) {
            $result['error'] = sprintf(
                __('File size exceeds maximum allowed size of %s', 'h3-tour-management'),
                size_format(H3TM_AWS_Security::MAX_OBJECT_SIZE)
            );
            return $result;
        }

        if ($filesize < 1024) { // Minimum 1KB
            $result['error'] = __('File is too small to be a valid tour', 'h3-tour-management');
            return $result;
        }

        $result['valid'] = true;
        return $result;
    }

    /**
     * Generate unique object key for S3
     *
     * @param string $filename Original filename
     * @return string S3 object key
     */
    private function generate_object_key($filename) {
        $file_info = pathinfo($filename);
        $name = sanitize_file_name($file_info['filename']);
        $extension = $file_info['extension'];

        // Add timestamp and random component for uniqueness
        $timestamp = date('Y/m/d');
        $random = substr(H3TM_Security::generate_token(16), 0, 8);

        return "tours/{$timestamp}/{$name}_{$random}.{$extension}";
    }

    /**
     * Create upload session for tracking
     *
     * @param string $object_key S3 object key
     * @param string $filename Original filename
     * @param int $filesize File size
     * @return array|false Session data or false on failure
     */
    private function create_upload_session($object_key, $filename, $filesize) {
        global $wpdb;

        $session_id = H3TM_Security::generate_token(24);
        $user_id = get_current_user_id();
        $created_at = current_time('mysql');

        $table_name = $wpdb->prefix . 'h3tm_upload_sessions';

        // Create table if it doesn't exist
        $this->create_upload_sessions_table();

        $result = $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'user_id' => $user_id,
                'object_key' => $object_key,
                'filename' => $filename,
                'filesize' => $filesize,
                'status' => self::STATE_PENDING,
                'created_at' => $created_at,
                'updated_at' => $created_at
            ),
            array('%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return array(
            'id' => $session_id,
            'object_key' => $object_key,
            'filename' => $filename,
            'filesize' => $filesize,
            'status' => self::STATE_PENDING,
            'created_at' => $created_at
        );
    }

    /**
     * Get upload session by ID
     *
     * @param string $session_id Session ID
     * @return array|false Session data or false if not found
     */
    private function get_upload_session($session_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'h3tm_upload_sessions';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE session_id = %s AND user_id = %d",
                $session_id,
                get_current_user_id()
            ),
            ARRAY_A
        );
    }

    /**
     * Update upload session
     *
     * @param string $session_id Session ID
     * @param array $data Data to update
     * @return bool Success status
     */
    private function update_upload_session($session_id, $data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'h3tm_upload_sessions';
        $data['updated_at'] = current_time('mysql');

        return $wpdb->update(
            $table_name,
            $data,
            array(
                'session_id' => $session_id,
                'user_id' => get_current_user_id()
            )
        ) !== false;
    }

    /**
     * Verify S3 upload by checking object existence and properties
     *
     * @param string $object_key S3 object key
     * @return array Verification result
     */
    private function verify_s3_upload($object_key) {
        $result = array('verified' => false, 'size' => 0);

        try {
            $credentials = H3TM_AWS_Security::get_credentials();
            if (!$credentials) {
                return $result;
            }

            $s3_client = new Aws\S3\S3Client(array(
                'version' => 'latest',
                'region' => $credentials['region'],
                'credentials' => array(
                    'key' => $credentials['access_key'],
                    'secret' => $credentials['secret_key']
                )
            ));

            $response = $s3_client->headObject(array(
                'Bucket' => $credentials['bucket'],
                'Key' => $object_key
            ));

            $result['verified'] = true;
            $result['size'] = $response['ContentLength'] ?? 0;

        } catch (Exception $e) {
            // Object doesn't exist or other error
        }

        return $result;
    }

    /**
     * Process uploaded tour file from S3
     *
     * @param array $session Upload session data
     * @return array Processing result
     */
    private function process_uploaded_tour($session) {
        // Generate download URL for processing
        $download_url = H3TM_AWS_Security::generate_presigned_download_url($session['object_key'], 3600);

        if (!$download_url) {
            return array('success' => false, 'error' => 'Failed to generate download URL');
        }

        // Download and process the tour file
        $temp_file = $this->download_s3_file($download_url);
        if (!$temp_file) {
            return array('success' => false, 'error' => 'Failed to download file from S3');
        }

        // Use existing tour manager to process the file
        $tour_manager = new H3TM_Tour_Manager();

        // Create a temporary $_FILES array for compatibility
        $file_array = array(
            'name' => $session['filename'],
            'type' => 'application/zip',
            'tmp_name' => $temp_file,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($temp_file)
        );

        $result = $tour_manager->process_uploaded_file($file_array);

        // Clean up temporary file
        unlink($temp_file);

        return $result;
    }

    /**
     * Download file from S3 to temporary location
     *
     * @param string $download_url Presigned download URL
     * @return string|false Temporary file path or false on failure
     */
    private function download_s3_file($download_url) {
        $temp_file = wp_tempnam();
        if (!$temp_file) {
            return false;
        }

        $response = wp_remote_get($download_url, array(
            'timeout' => 300, // 5 minutes for large files
            'stream' => true,
            'filename' => $temp_file
        ));

        if (is_wp_error($response)) {
            unlink($temp_file);
            return false;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            unlink($temp_file);
            return false;
        }

        return $temp_file;
    }

    /**
     * Create upload sessions table
     */
    private function create_upload_sessions_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'h3tm_upload_sessions';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(48) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            object_key varchar(512) NOT NULL,
            filename varchar(255) NOT NULL,
            filesize bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            progress int(3) unsigned DEFAULT 0,
            error_message text,
            file_size_actual bigint(20) unsigned,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            completed_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Clean up old upload sessions
     *
     * @param int $days Days to keep sessions
     */
    public static function cleanup_old_sessions($days = 7) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'h3tm_upload_sessions';
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );
    }
}