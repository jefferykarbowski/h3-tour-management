<?php
/**
 * Simple S3 Integration - No Complex Dependencies
 */
class H3TM_S3_Simple {

    public function __construct() {
        // Add AJAX handlers
        add_action('wp_ajax_h3tm_get_s3_presigned_url', array($this, 'handle_get_presigned_url'));
        add_action('wp_ajax_h3tm_process_s3_upload', array($this, 'handle_process_s3_upload'));
        add_action('wp_ajax_h3tm_test_s3_connection', array($this, 'handle_test_s3_connection'));
    }

    /**
     * Get S3 configuration directly
     */
    private function get_s3_credentials() {
        $bucket = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', '');
        $region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');
        $access_key = defined('AWS_ACCESS_KEY_ID') ? AWS_ACCESS_KEY_ID : get_option('h3tm_aws_access_key', '');
        $secret_key = defined('AWS_SECRET_ACCESS_KEY') ? AWS_SECRET_ACCESS_KEY : get_option('h3tm_aws_secret_key', '');

        error_log('H3TM S3 Simple: bucket=' . $bucket . ', region=' . $region);
        error_log('H3TM S3 Simple: access_key=' . (empty($access_key) ? 'EMPTY' : 'SET'));
        error_log('H3TM S3 Simple: secret_key=' . (empty($secret_key) ? 'EMPTY' : 'SET'));

        return array(
            'bucket' => $bucket,
            'region' => $region,
            'access_key' => $access_key,
            'secret_key' => $secret_key,
            'configured' => !empty($bucket) && !empty($access_key) && !empty($secret_key)
        );
    }

    /**
     * Public method to get S3 configuration (for tour URL handler)
     */
    public function get_s3_config() {
        return $this->get_s3_credentials();
    }

    /**
     * Test S3 connection by attempting to list bucket
     */
    public function handle_test_s3_connection() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $config = $this->get_s3_credentials();

        if (!$config['configured']) {
            $missing = array();
            if (empty($config['bucket'])) $missing[] = 'bucket';
            if (empty($config['access_key'])) $missing[] = 'access_key';
            if (empty($config['secret_key'])) $missing[] = 'secret_key';

            wp_send_json_error('S3 configuration incomplete: ' . implode(', ', $missing));
        }

        try {
            // Simple bucket test - attempt to generate a presigned URL
            $test_key = 'test/' . uniqid() . '.txt';
            $presigned_url = $this->generate_simple_presigned_url($config, $test_key);

            if (!empty($presigned_url)) {
                wp_send_json_success('S3 connection test successful!');
            } else {
                wp_send_json_error('Failed to generate presigned URL');
            }
        } catch (Exception $e) {
            wp_send_json_error('S3 test failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate presigned URL
     */
    public function handle_get_presigned_url() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        error_log('H3TM S3 Simple: Presigned URL request started');

        $config = $this->get_s3_credentials();

        if (!$config['configured']) {
            $missing = array();
            if (empty($config['bucket'])) $missing[] = 'bucket';
            if (empty($config['access_key'])) $missing[] = 'access_key';
            if (empty($config['secret_key'])) $missing[] = 'secret_key';

            error_log('H3TM S3 Simple: Missing configuration: ' . implode(', ', $missing));
            wp_send_json_error('Missing S3 configuration: ' . implode(', ', $missing));
        }

        $file_name = isset($_POST['file_name']) ? sanitize_file_name($_POST['file_name']) : '';
        $file_size = isset($_POST['file_size']) ? intval($_POST['file_size']) : 0;
        $tour_name = isset($_POST['tour_name']) ? sanitize_text_field($_POST['tour_name']) : '';

        if (empty($file_name) || empty($tour_name) || $file_size <= 0) {
            error_log('H3TM S3 Simple: Missing required parameters - file_name=' . $file_name . ', tour_name=' . $tour_name . ', file_size=' . $file_size);
            wp_send_json_error('Missing required upload parameters');
        }

        // Simple presigned URL generation
        $unique_id = uniqid() . '_' . time();
        $s3_key = 'uploads/' . $unique_id . '/' . $file_name;

        try {
            $presigned_url = $this->generate_simple_presigned_url($config, $s3_key);

            wp_send_json_success(array(
                'upload_url' => $presigned_url,
                's3_key' => $s3_key,
                'unique_id' => $unique_id
            ));

        } catch (Exception $e) {
            error_log('H3TM S3 Simple: Presigned URL generation failed: ' . $e->getMessage());
            wp_send_json_error('Failed to generate presigned URL: ' . $e->getMessage());
        }
    }

    /**
     * Generate proper AWS4 presigned URL
     */
    private function generate_simple_presigned_url($config, $s3_key) {
        $host = $config['bucket'] . ".s3." . $config['region'] . ".amazonaws.com";
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $expires = 3600; // 1 hour

        // Create canonical request
        $method = 'PUT';
        $canonical_uri = '/' . $s3_key;
        $canonical_querystring = 'X-Amz-Algorithm=AWS4-HMAC-SHA256';
        $canonical_querystring .= '&X-Amz-Credential=' . urlencode($config['access_key'] . '/' . $date . '/' . $config['region'] . '/s3/aws4_request');
        $canonical_querystring .= '&X-Amz-Date=' . $datetime;
        $canonical_querystring .= '&X-Amz-Expires=' . $expires;
        $canonical_querystring .= '&X-Amz-SignedHeaders=host';

        $canonical_headers = "host:" . $host . "\n";
        $signed_headers = 'host';
        $payload_hash = 'UNSIGNED-PAYLOAD';

        $canonical_request = $method . "\n" . $canonical_uri . "\n" . $canonical_querystring . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date . '/' . $config['region'] . '/s3/aws4_request';
        $string_to_sign = $algorithm . "\n" . $datetime . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);

        // Calculate signature
        $signing_key = $this->getSignatureKey($config['secret_key'], $date, $config['region'], 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        // Build final URL
        $url = "https://" . $host . $canonical_uri . '?' . $canonical_querystring . '&X-Amz-Signature=' . $signature;

        return $url;
    }

    /**
     * Generate AWS4 signing key
     */
    private function getSignatureKey($key, $dateStamp, $regionName, $serviceName) {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return $kSigning;
    }

    /**
     * Process S3 upload with S3-to-S3 workflow (no local storage)
     */
    public function handle_process_s3_upload() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $tour_name = isset($_POST['tour_name']) ? sanitize_text_field($_POST['tour_name']) : '';
        $s3_key = isset($_POST['s3_key']) ? sanitize_text_field($_POST['s3_key']) : '';
        $unique_id = isset($_POST['unique_id']) ? sanitize_text_field($_POST['unique_id']) : '';

        $file_name = isset($_POST['file_name']) ? sanitize_file_name($_POST['file_name']) : basename($s3_key);

        error_log('H3TM S3-to-S3: Processing tour=' . $tour_name . ', s3_key=' . $s3_key);

        if (empty($tour_name) || empty($s3_key)) {
            wp_send_json_error('Missing required parameters');
        }

        try {
            // Step 1: Download ZIP from S3 to temporary location
            error_log('H3TM S3-to-S3: Step 1 - Downloading ZIP from S3');
            $temp_zip_path = $this->download_zip_temporarily($s3_key, $file_name);

            if (!$temp_zip_path) {
                error_log('H3TM S3-to-S3: Failed to download ZIP from S3');
                wp_send_json_error('Failed to download tour from S3');
            }

            // Step 2: Extract ZIP locally to temporary directory
            error_log('H3TM S3-to-S3: Step 2 - Extracting ZIP locally from: ' . $temp_zip_path);
            error_log('H3TM S3-to-S3: ZIP file size: ' . filesize($temp_zip_path) . ' bytes');

            $temp_extract_dir = $this->extract_tour_temporarily($temp_zip_path, $tour_name);

            if (!$temp_extract_dir) {
                error_log('H3TM S3-to-S3: ZIP extraction failed');
                unlink($temp_zip_path);
                wp_send_json_error('Failed to extract tour ZIP');
            }

            error_log('H3TM S3-to-S3: ZIP extracted successfully to: ' . $temp_extract_dir);

            // Step 3: Upload extracted tour files to S3 public tours/ directory
            error_log('H3TM S3-to-S3: Step 3 - Uploading extracted tour to S3 tours/');
            $s3_tour_url = $this->upload_tour_to_s3_public($temp_extract_dir, $tour_name);

            if (!$s3_tour_url) {
                $this->cleanup_temp_files($temp_zip_path, $temp_extract_dir);
                wp_send_json_error('Failed to upload extracted tour to S3');
            }

            // Step 4: Register tour in WordPress with S3 URL
            error_log('H3TM S3-to-S3: Step 4 - Registering tour with S3 URL: ' . $s3_tour_url);
            $this->register_s3_tour($tour_name, $s3_tour_url);

            // Step 5: Cleanup temporary files and S3 upload
            $this->cleanup_temp_files($temp_zip_path, $temp_extract_dir);
            $this->delete_s3_upload($s3_key);

            error_log('H3TM S3-to-S3: SUCCESS - Tour available at: ' . $s3_tour_url);
            wp_send_json_success('Tour uploaded and processed successfully! Available at: ' . $s3_tour_url);

        } catch (Exception $e) {
            error_log('H3TM S3-to-S3 Error: ' . $e->getMessage());
            wp_send_json_error('S3 processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Download file from S3
     */
    private function download_from_s3($s3_key, $file_name) {
        $config = $this->get_s3_credentials();

        if (!$config['configured']) {
            throw new Exception('S3 not configured');
        }

        // Create download URL
        $download_url = "https://" . $config['bucket'] . ".s3." . $config['region'] . ".amazonaws.com/" . $s3_key;

        // Create local file path
        $upload_dir = wp_upload_dir();
        $h3_tours_dir = $upload_dir['basedir'] . '/h3-tours';

        if (!file_exists($h3_tours_dir)) {
            if (!wp_mkdir_p($h3_tours_dir)) {
                throw new Exception('Failed to create h3-tours directory');
            }
        }

        $local_file_path = $h3_tours_dir . '/' . $file_name;

        // Download file with proper authentication (simplified approach)
        $response = wp_remote_get($download_url, array(
            'timeout' => 600, // 10 minutes for large files
            'stream' => true,
            'filename' => $local_file_path
        ));

        if (is_wp_error($response)) {
            throw new Exception('Download failed: ' . $response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            throw new Exception('S3 download failed with status: ' . wp_remote_retrieve_response_code($response));
        }

        if (!file_exists($local_file_path) || filesize($local_file_path) === 0) {
            throw new Exception('Downloaded file is empty or missing');
        }

        return $local_file_path;
    }

    // get_s3_config() method already exists above - removed duplicate

    /**
     * S3-to-S3 Processing Helper Methods
     */

    private function download_zip_temporarily($s3_key, $file_name) {
        $config = $this->get_s3_credentials();

        // Try simple public URL first (since we updated bucket policy)
        $download_url = 'https://' . $config['bucket'] . '.s3.' . $config['region'] . '.amazonaws.com/' . $s3_key;

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/temp-s3-processing';
        if (!file_exists($temp_dir)) wp_mkdir_p($temp_dir);

        $temp_zip_path = $temp_dir . '/' . uniqid('s3_') . '_' . $file_name;

        error_log('H3TM S3-to-S3: Downloading from public URL: ' . $download_url);

        $response = wp_remote_get($download_url, array(
            'timeout' => 300,
            'stream' => true,
            'filename' => $temp_zip_path,
            'headers' => array(
                'User-Agent' => 'H3TM-WordPress-Plugin/1.7.0'
            )
        ));

        if (is_wp_error($response)) {
            error_log('H3TM S3-to-S3: Download error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        error_log('H3TM S3-to-S3: Download response code: ' . $response_code);

        if ($response_code !== 200) {
            error_log('H3TM S3-to-S3: Download failed with HTTP ' . $response_code);
            return false;
        }

        if (!file_exists($temp_zip_path)) {
            error_log('H3TM S3-to-S3: Downloaded file does not exist');
            return false;
        }

        $downloaded_size = filesize($temp_zip_path);
        error_log('H3TM S3-to-S3: Downloaded file size: ' . $downloaded_size . ' bytes');

        if ($downloaded_size < 1000) {
            // File too small - likely an error response
            $content = file_get_contents($temp_zip_path);
            error_log('H3TM S3-to-S3: Downloaded content (first 500 chars): ' . substr($content, 0, 500));
            unlink($temp_zip_path);
            return false;
        }

        return $temp_zip_path;
    }

    private function generate_signed_download_url($config, $s3_key) {
        // Generate proper AWS Signature V4 for GET request
        $host = $config['bucket'] . ".s3." . $config['region'] . ".amazonaws.com";
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $canonical_uri = '/' . ltrim($s3_key, '/');

        // Build canonical query string for download
        $canonical_querystring = http_build_query(array(
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $config['access_key'] . "/" . $date . "/" . $config['region'] . "/s3/aws4_request",
            'X-Amz-Date' => $datetime,
            'X-Amz-Expires' => 3600,
            'X-Amz-SignedHeaders' => 'host'
        ));

        // Create canonical request for GET
        $canonical_headers = "host:" . $host . "\n";
        $signed_headers = 'host';
        $payload_hash = 'UNSIGNED-PAYLOAD';

        $canonical_request = "GET\n" . $canonical_uri . "\n" . $canonical_querystring . "\n" .
                           $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;

        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date . '/' . $config['region'] . '/s3/aws4_request';
        $string_to_sign = $algorithm . "\n" . $datetime . "\n" . $credential_scope . "\n" .
                         hash('sha256', $canonical_request);

        // Calculate signature using proper AWS signing key
        $signing_key = $this->get_signing_key($date, $config['region'], 's3', $config['secret_key']);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        // Build final presigned URL
        $presigned_url = "https://" . $host . $canonical_uri . '?' . $canonical_querystring . '&X-Amz-Signature=' . $signature;

        return $presigned_url;
    }

    private function get_signing_key($date, $region, $service, $secret_key) {
        $key = 'AWS4' . $secret_key;
        $key = hash_hmac('sha256', $date, $key, true);
        $key = hash_hmac('sha256', $region, $key, true);
        $key = hash_hmac('sha256', $service, $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);
        return $key;
    }

    private function extract_tour_temporarily($zip_path, $tour_name) {
        error_log('H3TM S3-to-S3: Starting extraction of: ' . $zip_path);

        if (!file_exists($zip_path)) {
            error_log('H3TM S3-to-S3: ZIP file does not exist: ' . $zip_path);
            return false;
        }

        $upload_dir = wp_upload_dir();
        $temp_extract_dir = $upload_dir['basedir'] . '/temp-s3-processing/' . uniqid('extract_');

        error_log('H3TM S3-to-S3: Creating extract directory: ' . $temp_extract_dir);

        if (!wp_mkdir_p($temp_extract_dir)) {
            error_log('H3TM S3-to-S3: Failed to create extract directory');
            return false;
        }

        $zip = new ZipArchive();
        $open_result = $zip->open($zip_path);

        error_log('H3TM S3-to-S3: ZipArchive open result: ' . $open_result);

        if ($open_result === TRUE) {
            error_log('H3TM S3-to-S3: ZIP opened successfully, extracting...');
            $extract_result = $zip->extractTo($temp_extract_dir);
            error_log('H3TM S3-to-S3: Extract result: ' . ($extract_result ? 'SUCCESS' : 'FAILED'));
            $zip->close();

            if ($extract_result) {
                error_log('H3TM S3-to-S3: Starting structure fix...');
                $this->fix_s3_tour_structure($temp_extract_dir);
                error_log('H3TM S3-to-S3: Structure fix completed');
                return $temp_extract_dir;
            } else {
                error_log('H3TM S3-to-S3: ZIP extraction failed');
                return false;
            }
        } else {
            error_log('H3TM S3-to-S3: Failed to open ZIP file. Error code: ' . $open_result);
            return false;
        }
    }

    private function upload_tour_to_s3_public($temp_extract_dir, $tour_name) {
        $config = $this->get_s3_credentials();
        $tour_s3_name = sanitize_file_name($tour_name);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_extract_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $uploaded_count = 0;
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative_path = substr($file->getPathname(), strlen($temp_extract_dir) + 1);
                $s3_key = 'tours/' . $tour_s3_name . '/' . str_replace('\\', '/', $relative_path);

                if ($this->upload_file_to_s3_public($file->getPathname(), $s3_key)) {
                    $uploaded_count++;
                }
            }
        }

        if ($uploaded_count > 0) {
            return 'https://' . $config['bucket'] . '.s3.' . $config['region'] . '.amazonaws.com/tours/' . $tour_s3_name . '/';
        }
        return false;
    }

    private function upload_file_to_s3_public($local_file, $s3_key) {
        $config = $this->get_s3_credentials();
        $upload_url = $this->generate_simple_presigned_url($config, $s3_key);

        $content = file_get_contents($local_file);
        if ($content === false) return false;

        $response = wp_remote_request($upload_url, array(
            'method' => 'PUT',
            'body' => $content,
            'timeout' => 60,
            'headers' => array('Content-Type' => $this->get_content_type($local_file))
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    private function fix_s3_tour_structure($extract_dir) {
        $items = scandir($extract_dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $item_path = $extract_dir . '/' . $item;
            if (is_dir($item_path)) {
                $web_zip_path = $item_path . '/Web.zip';
                if (file_exists($web_zip_path)) {
                    $web_zip = new ZipArchive();
                    if ($web_zip->open($web_zip_path) === TRUE) {
                        $web_temp_dir = $item_path . '/web_temp';
                        wp_mkdir_p($web_temp_dir);
                        $web_zip->extractTo($web_temp_dir);
                        $web_zip->close();

                        $web_dir = $web_temp_dir . '/Web';
                        if (is_dir($web_dir)) {
                            $this->move_directory_contents($web_dir, $extract_dir);
                        }
                        $this->delete_directory($item_path);
                    }
                }
            }
        }
    }

    private function move_directory_contents($source, $destination) {
        $items = scandir($source);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $source_path = $source . '/' . $item;
            $dest_path = $destination . '/' . $item;
            if (is_dir($source_path)) {
                wp_mkdir_p($dest_path);
                $this->move_directory_contents($source_path, $dest_path);
            } else {
                copy($source_path, $dest_path);
            }
        }
    }

    private function delete_directory($dir) {
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            $this->delete_directory($dir . '/' . $item);
        }
        return rmdir($dir);
    }

    private function get_content_type($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $types = array('html' => 'text/html', 'htm' => 'text/html', 'js' => 'application/javascript',
                      'css' => 'text/css', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'mp4' => 'video/mp4');
        return isset($types[$ext]) ? $types[$ext] : 'application/octet-stream';
    }

    private function register_s3_tour($tour_name, $s3_tour_url) {
        $tours = get_option('h3tm_s3_tours', array());
        $tours[$tour_name] = array('url' => $s3_tour_url, 'created' => current_time('mysql'));
        update_option('h3tm_s3_tours', $tours);
    }

    private function cleanup_temp_files($temp_zip_path, $temp_extract_dir) {
        if (file_exists($temp_zip_path)) unlink($temp_zip_path);
        if (is_dir($temp_extract_dir)) $this->delete_directory($temp_extract_dir);
    }

    private function delete_s3_upload($s3_key) {
        $config = $this->get_s3_credentials();
        $delete_url = 'https://' . $config['bucket'] . '.s3.' . $config['region'] . '.amazonaws.com/' . $s3_key;
        wp_remote_request($delete_url, array('method' => 'DELETE', 'timeout' => 30));
    }
}