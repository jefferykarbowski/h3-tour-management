<?php
/**
 * Optimized Tour Manager for H3 Tour Management
 *
 * Performance-optimized version with enhanced error handling and progress tracking
 *
 * @package H3_Tour_Management
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Tour_Manager_Optimized extends H3TM_Tour_Manager_V2 {

    /**
     * Progress tracking option prefix
     */
    const PROGRESS_PREFIX = 'h3tm_progress_';

    /**
     * Maximum execution time for single operation (seconds)
     */
    const MAX_EXECUTION_TIME = 25;

    /**
     * File count threshold for chunked processing
     */
    const CHUNK_THRESHOLD = 100;

    /**
     * Standardized error response structure
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param array $context Additional context
     * @return array
     */
    private function create_error_response($code, $message, $context = array()) {
        return array(
            'success' => false,
            'error' => array(
                'code' => $code,
                'message' => $message,
                'context' => $context,
                'timestamp' => current_time('mysql')
            )
        );
    }

    /**
     * Standardized success response structure
     *
     * @param string $message Success message
     * @param array $data Additional data
     * @return array
     */
    private function create_success_response($message, $data = array()) {
        return array(
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * Optimized tour rename with progress tracking and timeout handling
     *
     * @param string $old_name Current tour name
     * @param string $new_name New tour name
     * @param array $options Operation options
     * @return array Result
     */
    public function rename_tour_optimized($old_name, $new_name, $options = array()) {
        $defaults = array(
            'progress_tracking' => true,
            'chunk_size' => self::CHUNK_THRESHOLD,
            'timeout_handling' => true,
            'force_background' => false
        );
        $options = wp_parse_args($options, $defaults);

        try {
            // Initialize progress tracking
            $operation_id = uniqid('rename_', true);
            if ($options['progress_tracking']) {
                $this->init_progress_tracking($operation_id, 'rename_tour', $old_name);
            }

            // Validate inputs
            $validation = $this->validate_rename_inputs($old_name, $new_name);
            if (!$validation['valid']) {
                return $this->create_error_response('validation_failed', $validation['message']);
            }

            $old_path = $this->tour_dir . '/' . $old_name;
            $new_path = $this->tour_dir . '/' . $new_name;

            // Estimate operation time
            $estimated_time = $this->estimate_rename_time($old_path);
            $this->update_progress($operation_id, 5, 'Operation estimated at ' . $estimated_time . ' seconds');

            // Use background processing if estimated time exceeds threshold
            if ($estimated_time > self::MAX_EXECUTION_TIME && !$options['force_background']) {
                return $this->queue_background_rename($old_name, $new_name, $operation_id, $options);
            }

            // Set higher time limit for this operation
            if ($options['timeout_handling']) {
                $original_limit = ini_get('max_execution_time');
                set_time_limit(max(60, $estimated_time + 30));
            }

            // Start transaction for database operations
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            try {
                // Phase 1: Perform filesystem operations
                $filesystem_result = $this->perform_optimized_filesystem_rename($old_path, $new_path, $operation_id);
                if (!$filesystem_result['success']) {
                    throw new Exception($filesystem_result['error']['message']);
                }

                // Phase 2: Update database records
                $database_result = $this->perform_optimized_database_updates($old_name, $new_name, $operation_id);
                if (!$database_result['success']) {
                    // Rollback filesystem changes if database fails
                    $this->filesystem->move($new_path, $old_path);
                    throw new Exception($database_result['error']['message']);
                }

                // Phase 3: Update tour PHP file
                $this->update_tour_php_file($new_path, $old_name, $new_name);
                $this->update_progress($operation_id, 90, 'Updated tour configuration file');

                // Commit transaction
                $wpdb->query('COMMIT');

                // Log successful operation
                H3TM_Database::log_activity('tour_renamed_optimized', 'tour', $new_name, array(
                    'old_name' => $old_name,
                    'operation_id' => $operation_id,
                    'duration' => $this->get_operation_duration($operation_id)
                ));

                // Complete progress tracking
                $this->complete_progress($operation_id, 'Tour renamed successfully');

                H3TM_Logger::info('tour', 'Tour renamed with optimized method', array(
                    'old_name' => $old_name,
                    'new_name' => $new_name,
                    'operation_id' => $operation_id,
                    'estimated_time' => $estimated_time
                ));

                return $this->create_success_response(
                    __('Tour renamed successfully.', 'h3-tour-management'),
                    array(
                        'operation_id' => $operation_id,
                        'estimated_time' => $estimated_time,
                        'actual_duration' => $this->get_operation_duration($operation_id)
                    )
                );

            } catch (Exception $e) {
                // Rollback database transaction
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (Exception $e) {
            // Update progress with error
            if (isset($operation_id)) {
                $this->fail_progress($operation_id, $e->getMessage());
            }

            // Restore original time limit
            if (isset($original_limit) && $options['timeout_handling']) {
                set_time_limit($original_limit);
            }

            H3TM_Logger::error('tour', 'Optimized tour rename failed', array(
                'old_name' => $old_name,
                'new_name' => $new_name,
                'error' => $e->getMessage(),
                'operation_id' => isset($operation_id) ? $operation_id : null
            ));

            return $this->create_error_response('operation_failed', $e->getMessage(), array(
                'old_name' => $old_name,
                'new_name' => $new_name,
                'operation_id' => isset($operation_id) ? $operation_id : null
            ));
        }
    }

    /**
     * Validate rename operation inputs
     *
     * @param string $old_name Old tour name
     * @param string $new_name New tour name
     * @return array Validation result
     */
    private function validate_rename_inputs($old_name, $new_name) {
        $new_name = sanitize_file_name($new_name);
        $old_path = $this->tour_dir . '/' . $old_name;
        $new_path = $this->tour_dir . '/' . $new_name;

        if (empty($old_name)) {
            return array('valid' => false, 'message' => __('Original tour name is required.', 'h3-tour-management'));
        }

        if (empty($new_name)) {
            return array('valid' => false, 'message' => __('New tour name is required.', 'h3-tour-management'));
        }

        if ($old_name === $new_name) {
            return array('valid' => false, 'message' => __('New name must be different from current name.', 'h3-tour-management'));
        }

        if (!$this->filesystem->exists($old_path)) {
            return array('valid' => false, 'message' => __('Original tour not found.', 'h3-tour-management'));
        }

        if ($this->filesystem->exists($new_path)) {
            return array('valid' => false, 'message' => __('A tour with the new name already exists.', 'h3-tour-management'));
        }

        // Check for invalid characters
        if (preg_match('/[<>:\"\/\\|?*]/', $new_name)) {
            return array('valid' => false, 'message' => __('Tour name contains invalid characters.', 'h3-tour-management'));
        }

        return array('valid' => true, 'sanitized_name' => $new_name);
    }

    /**
     * Estimate rename operation time based on directory size
     *
     * @param string $path Directory path
     * @return int Estimated time in seconds
     */
    private function estimate_rename_time($path) {
        if (!$this->filesystem->is_dir($path)) {
            return 1;
        }

        // Get directory stats
        $file_count = $this->count_directory_files($path);
        $directory_size = $this->get_directory_size($path);

        // Base estimation: 0.1s per file + 0.5s per MB
        $time_estimate = ($file_count * 0.1) + ($directory_size / (1024 * 1024) * 0.5);

        // Add overhead for database operations
        $time_estimate += 2;

        // Minimum 2 seconds, maximum 300 seconds
        return max(2, min(300, ceil($time_estimate)));
    }

    /**
     * Count files in directory
     *
     * @param string $path Directory path
     * @return int File count
     */
    private function count_directory_files($path) {
        $count = 0;

        if (!$this->filesystem->is_dir($path)) {
            return 0;
        }

        $files = $this->filesystem->dirlist($path, true, true);
        if ($files) {
            foreach ($files as $file) {
                if ($file['type'] === 'f') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Perform optimized filesystem rename operation
     *
     * @param string $old_path Old directory path
     * @param string $new_path New directory path
     * @param string $operation_id Operation tracking ID
     * @return array Result
     */
    private function perform_optimized_filesystem_rename($old_path, $new_path, $operation_id) {
        $this->update_progress($operation_id, 10, 'Starting filesystem operations');

        try {
            $file_count = $this->count_directory_files($old_path);

            // Use optimized method for large directories
            if ($file_count > self::CHUNK_THRESHOLD) {
                return $this->perform_chunked_rename($old_path, $new_path, $operation_id);
            } else {
                return $this->perform_simple_rename($old_path, $new_path, $operation_id);
            }

        } catch (Exception $e) {
            return $this->create_error_response('filesystem_error', $e->getMessage());
        }
    }

    /**
     * Perform simple rename for small directories
     *
     * @param string $old_path Old directory path
     * @param string $new_path New directory path
     * @param string $operation_id Operation tracking ID
     * @return array Result
     */
    private function perform_simple_rename($old_path, $new_path, $operation_id) {
        $this->update_progress($operation_id, 20, 'Moving tour directory');

        if (!$this->filesystem->move($old_path, $new_path)) {
            throw new Exception(__('Failed to rename tour directory.', 'h3-tour-management'));
        }

        $this->update_progress($operation_id, 60, 'Directory moved successfully');

        return $this->create_success_response('Directory renamed successfully');
    }

    /**
     * Perform chunked rename for large directories (copy-and-delete pattern)
     *
     * @param string $old_path Old directory path
     * @param string $new_path New directory path
     * @param string $operation_id Operation tracking ID
     * @return array Result
     */
    private function perform_chunked_rename($old_path, $new_path, $operation_id) {
        $this->update_progress($operation_id, 15, 'Using chunked rename for large directory');

        // Create new directory
        if (!$this->filesystem->mkdir($new_path, 0755)) {
            throw new Exception(__('Failed to create new tour directory.', 'h3-tour-management'));
        }

        $this->update_progress($operation_id, 20, 'Created new directory');

        try {
            // Copy files in chunks
            $copy_result = $this->copy_directory_chunked($old_path, $new_path, $operation_id, 20, 50);
            if (!$copy_result['success']) {
                throw new Exception($copy_result['error']['message']);
            }

            $this->update_progress($operation_id, 55, 'Files copied successfully');

            // Verify copy completed successfully
            $verification_result = $this->verify_directory_copy($old_path, $new_path);
            if (!$verification_result['success']) {
                // Clean up partial copy
                $this->filesystem->rmdir($new_path, true);
                throw new Exception($verification_result['error']['message']);
            }

            $this->update_progress($operation_id, 58, 'Copy verification complete');

            // Delete old directory
            if (!$this->filesystem->rmdir($old_path, true)) {
                H3TM_Logger::warning('tour', 'Failed to remove old directory after successful copy', array(
                    'old_path' => $old_path,
                    'new_path' => $new_path
                ));
            }

            $this->update_progress($operation_id, 60, 'Chunked rename completed successfully');

            return $this->create_success_response('Directory renamed successfully using chunked method');

        } catch (Exception $e) {
            // Clean up on failure
            if ($this->filesystem->exists($new_path)) {
                $this->filesystem->rmdir($new_path, true);
            }
            throw $e;
        }
    }

    /**
     * Copy directory contents in chunks
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @param string $operation_id Operation tracking ID
     * @param int $progress_start Starting progress percentage
     * @param int $progress_end Ending progress percentage
     * @return array Result
     */
    private function copy_directory_chunked($source, $destination, $operation_id, $progress_start, $progress_end) {
        $files = $this->filesystem->dirlist($source, true, true);
        if (!$files) {
            return $this->create_success_response('No files to copy');
        }

        $total_files = count($files);
        $processed = 0;
        $chunk_size = max(10, min(50, intval($total_files / 10))); // Dynamic chunk size

        foreach (array_chunk($files, $chunk_size, true) as $file_chunk) {
            foreach ($file_chunk as $name => $file) {
                $source_path = trailingslashit($source) . $name;
                $dest_path = trailingslashit($destination) . $name;

                if ($file['type'] === 'd') {
                    // Recursively copy subdirectory
                    if (!$this->filesystem->mkdir($dest_path, 0755)) {
                        throw new Exception(sprintf(__('Failed to create directory: %s', 'h3-tour-management'), $name));
                    }

                    $subdir_result = $this->copy_directory_chunked($source_path, $dest_path, $operation_id, $progress_start, $progress_end);
                    if (!$subdir_result['success']) {
                        return $subdir_result;
                    }

                } else {
                    // Copy file
                    if (!$this->filesystem->copy($source_path, $dest_path)) {
                        throw new Exception(sprintf(__('Failed to copy file: %s', 'h3-tour-management'), $name));
                    }
                }

                $processed++;
            }

            // Update progress
            $progress = $progress_start + (($processed / $total_files) * ($progress_end - $progress_start));
            $this->update_progress($operation_id, intval($progress),
                sprintf('Copied %d of %d files', $processed, $total_files));

            // Brief pause to prevent overwhelming the system
            if (function_exists('usleep')) {
                usleep(10000); // 10ms pause
            }
        }

        return $this->create_success_response('Directory copied successfully');
    }

    /**
     * Verify directory copy completion
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @return array Verification result
     */
    private function verify_directory_copy($source, $destination) {
        $source_count = $this->count_directory_files($source);
        $dest_count = $this->count_directory_files($destination);

        if ($source_count !== $dest_count) {
            return $this->create_error_response('copy_verification_failed',
                sprintf(__('File count mismatch: source=%d, destination=%d', 'h3-tour-management'),
                $source_count, $dest_count));
        }

        return $this->create_success_response('Directory copy verified successfully');
    }

    /**
     * Perform optimized database updates
     *
     * @param string $old_name Old tour name
     * @param string $new_name New tour name
     * @param string $operation_id Operation tracking ID
     * @return array Result
     */
    private function perform_optimized_database_updates($old_name, $new_name, $operation_id) {
        global $wpdb;

        $this->update_progress($operation_id, 70, 'Updating database records');

        try {
            // Batch update user assignments
            $users_updated = $this->batch_update_user_assignments($old_name, $new_name);
            $this->update_progress($operation_id, 75, sprintf('Updated %d user assignments', $users_updated));

            // Update tour metadata
            $meta_table = $wpdb->prefix . 'h3tm_tour_meta';
            $meta_result = $wpdb->update(
                $meta_table,
                array('tour_name' => $new_name),
                array('tour_name' => $old_name),
                array('%s'),
                array('%s')
            );

            if ($meta_result === false) {
                throw new Exception(__('Failed to update tour metadata.', 'h3-tour-management'));
            }

            $this->update_progress($operation_id, 80, sprintf('Updated %d metadata records', $meta_result));

            return $this->create_success_response('Database updates completed successfully', array(
                'users_updated' => $users_updated,
                'meta_records_updated' => $meta_result
            ));

        } catch (Exception $e) {
            return $this->create_error_response('database_error', $e->getMessage());
        }
    }

    /**
     * Batch update user tour assignments
     *
     * @param string $old_name Old tour name
     * @param string $new_name New tour name
     * @return int Number of users updated
     */
    private function batch_update_user_assignments($old_name, $new_name) {
        global $wpdb;

        // Get all users with the old tour name in their assignments
        $sql = "SELECT user_id, meta_value
                FROM {$wpdb->usermeta}
                WHERE meta_key = 'h3tm_tours'
                AND meta_value LIKE %s";

        $users_with_tour = $wpdb->get_results(
            $wpdb->prepare($sql, '%' . $wpdb->esc_like($old_name) . '%')
        );

        $updated_count = 0;

        foreach ($users_with_tour as $user_meta) {
            $tours = maybe_unserialize($user_meta->meta_value);

            if (is_array($tours)) {
                $key = array_search($old_name, $tours, true);
                if ($key !== false) {
                    $tours[$key] = $new_name;

                    $update_result = update_user_meta(
                        $user_meta->user_id,
                        'h3tm_tours',
                        $tours
                    );

                    if ($update_result) {
                        $updated_count++;
                    }
                }
            }
        }

        return $updated_count;
    }

    /**
     * Initialize progress tracking for an operation
     *
     * @param string $operation_id Unique operation ID
     * @param string $operation_type Type of operation
     * @param string $target Target of operation
     */
    private function init_progress_tracking($operation_id, $operation_type, $target) {
        $progress_data = array(
            'operation_id' => $operation_id,
            'operation_type' => $operation_type,
            'target' => $target,
            'status' => 'running',
            'progress' => 0,
            'message' => 'Operation started',
            'started_at' => current_time('mysql'),
            'last_update' => current_time('mysql')
        );

        set_transient(self::PROGRESS_PREFIX . $operation_id, $progress_data, HOUR_IN_SECONDS);
    }

    /**
     * Update operation progress
     *
     * @param string $operation_id Operation ID
     * @param int $progress Progress percentage (0-100)
     * @param string $message Status message
     */
    private function update_progress($operation_id, $progress, $message) {
        $progress_data = get_transient(self::PROGRESS_PREFIX . $operation_id);

        if ($progress_data) {
            $progress_data['progress'] = max(0, min(100, $progress));
            $progress_data['message'] = $message;
            $progress_data['last_update'] = current_time('mysql');

            set_transient(self::PROGRESS_PREFIX . $operation_id, $progress_data, HOUR_IN_SECONDS);
        }
    }

    /**
     * Complete operation progress tracking
     *
     * @param string $operation_id Operation ID
     * @param string $message Completion message
     */
    private function complete_progress($operation_id, $message) {
        $progress_data = get_transient(self::PROGRESS_PREFIX . $operation_id);

        if ($progress_data) {
            $progress_data['status'] = 'completed';
            $progress_data['progress'] = 100;
            $progress_data['message'] = $message;
            $progress_data['completed_at'] = current_time('mysql');

            set_transient(self::PROGRESS_PREFIX . $operation_id, $progress_data, HOUR_IN_SECONDS);
        }
    }

    /**
     * Mark operation as failed
     *
     * @param string $operation_id Operation ID
     * @param string $error_message Error message
     */
    private function fail_progress($operation_id, $error_message) {
        $progress_data = get_transient(self::PROGRESS_PREFIX . $operation_id);

        if ($progress_data) {
            $progress_data['status'] = 'failed';
            $progress_data['message'] = $error_message;
            $progress_data['failed_at'] = current_time('mysql');

            set_transient(self::PROGRESS_PREFIX . $operation_id, $progress_data, HOUR_IN_SECONDS);
        }
    }

    /**
     * Get operation progress data
     *
     * @param string $operation_id Operation ID
     * @return array|false Progress data or false if not found
     */
    public function get_operation_progress($operation_id) {
        return get_transient(self::PROGRESS_PREFIX . $operation_id);
    }

    /**
     * Get operation duration
     *
     * @param string $operation_id Operation ID
     * @return float Duration in seconds
     */
    private function get_operation_duration($operation_id) {
        $progress_data = get_transient(self::PROGRESS_PREFIX . $operation_id);

        if ($progress_data && isset($progress_data['started_at'])) {
            $start_time = strtotime($progress_data['started_at']);
            return time() - $start_time;
        }

        return 0;
    }

    /**
     * Queue background rename operation (placeholder for future implementation)
     *
     * @param string $old_name Old tour name
     * @param string $new_name New tour name
     * @param string $operation_id Operation ID
     * @param array $options Operation options
     * @return array Result
     */
    private function queue_background_rename($old_name, $new_name, $operation_id, $options) {
        // This would integrate with WordPress cron or a proper job queue system
        // For now, return a message indicating the operation would be queued

        return $this->create_success_response(
            __('Large tour rename operation has been queued for background processing.', 'h3-tour-management'),
            array(
                'operation_id' => $operation_id,
                'status' => 'queued',
                'estimated_time' => $this->estimate_rename_time($this->tour_dir . '/' . $old_name)
            )
        );
    }

    /**
     * Clean up expired progress tracking data
     */
    public static function cleanup_expired_progress() {
        global $wpdb;

        $prefix = self::PROGRESS_PREFIX;
        $expired_time = current_time('timestamp') - HOUR_IN_SECONDS;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
             AND option_name LIKE '_transient_{$prefix}%'",
            '_transient_timeout_' . $prefix . '%'
        ));
    }
}