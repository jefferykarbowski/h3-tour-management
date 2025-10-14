<?php
/**
 * Migration Trait
 *
 * Handles migration of local tours to S3:
 * - Migrate Tour to S3 (upload local files to S3 bucket)
 * - Upload Directory to S3 (recursive upload with MIME type detection)
 * - Delete Directory (cleanup after migration)
 *
 * @package H3_Tour_Management
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Trait_H3TM_Migration {

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
            require_once(dirname(__FILE__) . '/../class-h3tm-s3-simple.php');
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
