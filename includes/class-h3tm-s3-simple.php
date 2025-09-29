<?php
/**
 * Simple S3 Integration - No Complex Dependencies
 */
class H3TM_S3_Simple {

    private $cdn_helper = null;
    private $is_configured = false;
    private $s3_bucket = '';
    private $s3_region = '';
    private $aws_access_key = '';
    private $aws_secret_key = '';

    public function __construct() {
        // Initialize S3 configuration
        $this->init_s3_config();

        // Initialize CDN helper for CloudFront support
        if (class_exists('H3TM_CDN_Helper')) {
            $this->cdn_helper = H3TM_CDN_Helper::get_instance();
        }
        // Add AJAX handlers
        add_action('wp_ajax_h3tm_get_s3_presigned_url', array($this, 'handle_get_presigned_url'));
        add_action('wp_ajax_h3tm_process_s3_upload', array($this, 'handle_process_s3_upload'));
        add_action('wp_ajax_h3tm_test_s3_connection', array($this, 'handle_test_s3_connection'));
        add_action('wp_ajax_h3tm_list_s3_tours', array($this, 'handle_list_s3_tours'));
    }

    /**
     * Initialize S3 configuration properties
     */
    private function init_s3_config() {
        $config = $this->get_s3_credentials();
        $this->s3_bucket = $config['bucket'];
        $this->s3_region = $config['region'];
        $this->aws_access_key = $config['access_key'];
        $this->aws_secret_key = $config['secret_key'];
        $this->is_configured = $config['configured'];
    }

    /**
     * Get S3 configuration directly
     */
    private function get_s3_credentials() {
        $bucket = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', '');
        $region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');
        $access_key = defined('AWS_ACCESS_KEY_ID') ? AWS_ACCESS_KEY_ID : get_option('h3tm_aws_access_key', '');
        $secret_key = defined('AWS_SECRET_ACCESS_KEY') ? AWS_SECRET_ACCESS_KEY : get_option('h3tm_aws_secret_key', '');

        // Enhanced debugging
        error_log('H3TM S3 Credentials Debug:');
        error_log('  - Bucket: ' . (!empty($bucket) ? $bucket : 'NOT SET'));
        error_log('  - Region: ' . $region);
        error_log('  - Access Key from constant: ' . (defined('AWS_ACCESS_KEY_ID') ? 'YES' : 'NO'));
        error_log('  - Access Key from DB: ' . (!empty(get_option('h3tm_aws_access_key', '')) ? 'YES' : 'NO'));
        error_log('  - Secret Key from constant: ' . (defined('AWS_SECRET_ACCESS_KEY') ? 'YES' : 'NO'));
        error_log('  - Secret Key from DB: ' . (!empty(get_option('h3tm_aws_secret_key', '')) ? 'YES' : 'NO'));
        error_log('  - Final Access Key: ' . (empty($access_key) ? 'EMPTY' : substr($access_key, 0, 4) . '...'));
        error_log('  - Final Secret Key: ' . (empty($secret_key) ? 'EMPTY' : 'SET'));

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
     * List tours directly from S3 bucket
     */
    public function list_s3_tours() {
        $config = $this->get_s3_credentials();

        if (!$config['configured']) {
            error_log('H3TM S3: Not configured, cannot list tours');
            return array();
        }

        error_log('H3TM S3: Starting list_s3_tours with bucket=' . $config['bucket'] . ' region=' . $config['region']);

        $all_tour_folders = array();
        $continuation_token = null;
        $page_count = 0;
        $max_pages = 5; // Should only need 1 page for directories, but allow a few more for safety
        $start_time = time();
        $max_execution_time = 30; // 30 seconds should be plenty for listing directories

        try {
            do {
                $page_count++;

                // Check time limit (shouldn't happen with delimiter)
                if ((time() - $start_time) > $max_execution_time) {
                    error_log('H3TM S3: Stopping due to time limit (' . $max_execution_time . ' seconds)');
                    error_log('H3TM S3: Retrieved ' . count($all_tour_folders) . ' tours in ' . $page_count . ' pages');
                    $hit_time_limit = true;
                    break;
                }

                if ($page_count > $max_pages) {
                    error_log('H3TM S3: Reached maximum pages limit of ' . $max_pages);
                    error_log('H3TM S3: Total tours found so far: ' . count($all_tour_folders));
                    // Add a flag that we hit the limit
                    $hit_page_limit = true;
                    break;
                }

                // Create signed request for S3 ListObjectsV2
                $service = 's3';
                $host = $config['bucket'] . '.s3.' . $config['region'] . '.amazonaws.com';
                $method = 'GET';
                $uri = '/';

                // Build query parameters properly for canonical request
                // AWS requires parameters to be URL encoded and sorted alphabetically
                // IMPORTANT: Use delimiter to get only 'folders', not all files
                $query_params = array(
                    'list-type' => '2',
                    'max-keys' => '1000',
                    'prefix' => 'tours/',
                    'delimiter' => '/'  // This makes S3 return only 'directories'
                );

                // Add continuation token if this is not the first page
                if ($continuation_token) {
                    $query_params['continuation-token'] = $continuation_token;
                    error_log('H3TM S3: Fetching page ' . $page_count . ' with continuation token');
                }

            // Sort parameters alphabetically for canonical query string
            ksort($query_params);

            // Build canonical query string with proper encoding
            $canonical_querystring_parts = array();
            foreach ($query_params as $key => $value) {
                $canonical_querystring_parts[] = rawurlencode($key) . '=' . rawurlencode($value);
            }
            $canonical_querystring = implode('&', $canonical_querystring_parts);

                // Build regular query string for the URL
                $query_string = 'list-type=2&max-keys=1000&prefix=' . urlencode('tours/') . '&delimiter=' . urlencode('/');
                if ($continuation_token) {
                    $query_string .= '&continuation-token=' . urlencode($continuation_token);
                }

            error_log('H3TM S3: Canonical query string: ' . $canonical_querystring);
            error_log('H3TM S3: Request URL will be: https://' . $host . $uri . '?' . $query_string);

            // Create date/time strings
            $datetime = gmdate('Ymd\THis\Z');
            $date = gmdate('Ymd');

            // Build canonical request
            $canonical_uri = $uri;
            $payload_hash = hash('sha256', ''); // Empty payload for GET request
            $canonical_headers = 'host:' . $host . "\n" .
                               'x-amz-content-sha256:' . $payload_hash . "\n" .
                               'x-amz-date:' . $datetime . "\n";
            $signed_headers = 'host;x-amz-content-sha256;x-amz-date';

            $canonical_request = $method . "\n" .
                                $canonical_uri . "\n" .
                                $canonical_querystring . "\n" .
                                $canonical_headers . "\n" .
                                $signed_headers . "\n" .
                                $payload_hash;

            // Debug logging for signature calculation
            error_log('H3TM S3: Debug - Method: ' . $method);
            error_log('H3TM S3: Debug - Canonical URI: ' . $canonical_uri);
            error_log('H3TM S3: Debug - Canonical Query String: ' . $canonical_querystring);
            error_log('H3TM S3: Debug - Signed Headers: ' . $signed_headers);
            error_log('H3TM S3: Debug - Canonical Request (first 500 chars): ' . substr($canonical_request, 0, 500));

            // Create string to sign
            $algorithm = 'AWS4-HMAC-SHA256';
            $credential_scope = $date . '/' . $config['region'] . '/' . $service . '/aws4_request';
            $string_to_sign = $algorithm . "\n" .
                             $datetime . "\n" .
                             $credential_scope . "\n" .
                             hash('sha256', $canonical_request);

            // Calculate signature
            $signing_key = $this->getSignatureKey($config['secret_key'], $date, $config['region'], $service);
            $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

            // Build authorization header
            $authorization_header = $algorithm . ' ' .
                                  'Credential=' . $config['access_key'] . '/' . $credential_scope . ', ' .
                                  'SignedHeaders=' . $signed_headers . ', ' .
                                  'Signature=' . $signature;

            // Make the request
            $url = 'https://' . $host . $uri . '?' . $query_string;

            error_log('H3TM S3: Making request to: ' . $url);
            error_log('H3TM S3: Authorization header: ' . substr($authorization_header, 0, 100) . '...');

            $response = wp_remote_get($url, array(
                'timeout' => 30, // Increased timeout for Pantheon environment
                'headers' => array(
                    'x-amz-date' => $datetime,
                    'x-amz-content-sha256' => $payload_hash,
                    'Authorization' => $authorization_header
                ),
                'sslverify' => true, // Ensure SSL verification for security
                'httpversion' => '1.1' // Use HTTP/1.1 for better compatibility
            ));

            if (is_wp_error($response)) {
                error_log('H3TM S3: List request error: ' . $response->get_error_message());
                return array();
            }

            $body = wp_remote_retrieve_body($response);
            $status = wp_remote_retrieve_response_code($response);

            error_log('H3TM S3: Response status: ' . $status);
            error_log('H3TM S3: Response body (first 1000 chars): ' . substr($body, 0, 1000));

            if ($status !== 200) {
                error_log('H3TM S3: List returned status ' . $status . ': ' . substr($body, 0, 500));
                return array();
            }

            // Parse XML response
            $tours = array();
            if (!empty($body)) {
                // Try to parse XML
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($body);

                if ($xml === false) {
                    $errors = libxml_get_errors();
                    error_log('H3TM S3: XML parsing error: ' . print_r($errors, true));
                    libxml_clear_errors();
                    return array();
                }

                // Check for NextContinuationToken for pagination
                $has_more = false;
                if (isset($xml->IsTruncated) && (string)$xml->IsTruncated === 'true') {
                    $has_more = true;
                    if (isset($xml->NextContinuationToken)) {
                        $continuation_token = (string)$xml->NextContinuationToken;
                        error_log('H3TM S3: Page ' . $page_count . ' has more results, continuation token received');
                    } else {
                        $has_more = false; // No continuation token even though truncated
                        error_log('H3TM S3: Truncated but no continuation token found');
                    }
                } else {
                    $has_more = false;
                    error_log('H3TM S3: Page ' . $page_count . ' is the last page');
                }

                // When using delimiter, S3 returns CommonPrefixes for 'directories'
                if (isset($xml->CommonPrefixes)) {
                    error_log('H3TM S3: Found ' . count($xml->CommonPrefixes) . ' tour folders on page ' . $page_count);

                    foreach ($xml->CommonPrefixes as $prefix) {
                        $prefix_str = (string)$prefix->Prefix;
                        // Extract tour folder name from "tours/Tour-Name/"
                        if (preg_match('/^tours\/([^\/]+)\/$/', $prefix_str, $matches)) {
                            $tour_folder = $matches[1];
                            $all_tour_folders[] = $tour_folder;
                            error_log('H3TM S3: Found tour folder: ' . $tour_folder);
                        }
                    }
                } else {
                    error_log('H3TM S3: No CommonPrefixes found on page ' . $page_count);
                }

                // Also check Contents in case there are files directly in tours/ (shouldn't be any)
                if (isset($xml->Contents)) {
                    error_log('H3TM S3: Found ' . count($xml->Contents) . ' files in tours/ root (unusual)');
                }
            } else {
                error_log('H3TM S3: Response body is empty on page ' . $page_count);
            }

            } while ($has_more && $page_count < $max_pages);

            // Convert folder names to display names after collecting all pages
            $tours = array();
            foreach ($all_tour_folders as $tour_folder) {
                // Convert dashes back to spaces for display
                // AWS/Lambda converts spaces to dashes when uploading
                // e.g., "Bee Cave.zip" becomes "Bee-Cave/" in S3
                // But "Onion Creek" stays as "Onion Creek" (no dash conversion if originally uploaded that way)
                $tour_display_name = str_replace('-', ' ', $tour_folder);
                $tours[] = $tour_display_name;
                error_log('H3TM S3: Added tour: ' . $tour_display_name . ' (S3 folder: ' . $tour_folder . ')');
            }

            error_log('H3TM S3: Total unique tours found across ' . $page_count . ' page(s): ' . count($tours));
            error_log('H3TM S3: Successfully listed ' . count($tours) . ' tours');

            // If we hit limits, add a notice
            if (isset($hit_page_limit) && $hit_page_limit) {
                error_log('H3TM S3: NOTE: Stopped at page limit. More tours may exist beyond the ' . ($max_pages * 1000) . ' objects scanned');
            }
            if (isset($hit_time_limit) && $hit_time_limit) {
                error_log('H3TM S3: NOTE: Stopped due to time limit. More tours may exist.');
            }

            return $tours;

        } catch (Exception $e) {
            error_log('H3TM S3: Exception listing tours: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Clear the tour list cache
     */
    public function clear_tour_cache() {
        delete_transient('h3tm_s3_tours_cache');
        error_log('H3TM S3: Tour list cache cleared');
    }

    /**
     * AJAX handler to list S3 tours ONLY
     */
    public function handle_list_s3_tours() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $s3_tours_only = array();
        $errors = array();

        // DO NOT include local tours - this handler is specifically for S3 tours
        // The admin page shows S3 tours and local tours in separate sections

        // Check S3 configuration first
        $config = $this->get_s3_credentials();
        if (!$config['configured']) {
            error_log('H3TM: S3 not configured - Access Key: ' .
                     (empty($config['access_key']) ? 'MISSING' : 'SET') .
                     ', Secret Key: ' . (empty($config['secret_key']) ? 'MISSING' : 'SET') .
                     ', Bucket: ' . $config['bucket']);
            $errors[] = 'S3 credentials not configured';
        } else {
            // Check if force refresh is requested
            $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';

            // Try to get cached S3 tours first (2 hour cache for better performance)
            $cache_key = 'h3tm_s3_tours_cache';
            $cache_duration = 2 * HOUR_IN_SECONDS; // 2 hours

            if (!$force_refresh) {
                $cached_tours = get_transient($cache_key);
                if ($cached_tours !== false) {
                    error_log('H3TM: Using cached S3 tours list (' . count($cached_tours) . ' tours)');
                    $s3_tours = $cached_tours;
                } else {
                    $s3_tours = false;
                }
            } else {
                error_log('H3TM: Force refresh requested - bypassing cache');
                $s3_tours = false;
            }

            if ($s3_tours === false) {
                // Get S3 tours with timeout handling
                try {
                    // Set a shorter execution time limit for this operation
                    $original_time_limit = ini_get('max_execution_time');
                    @set_time_limit(45); // 45 seconds should be enough

                    $s3_tours = $this->list_s3_tours();

                    // Cache the results for 2 hours to reduce API calls
                    if (is_array($s3_tours) && !empty($s3_tours)) {
                        set_transient($cache_key, $s3_tours, $cache_duration);
                        error_log('H3TM: Cached ' . count($s3_tours) . ' S3 tours for ' . ($cache_duration / 3600) . ' hours');
                    }

                    // Restore original time limit
                    @set_time_limit($original_time_limit);

                } catch (Exception $e) {
                    error_log('H3TM: Error listing S3 tours: ' . $e->getMessage());
                    $errors[] = 'S3 connection error: ' . $e->getMessage();

                    // Fall back to database records if S3 API fails
                    $db_tours = get_option('h3tm_s3_tours', array());
                    if (!empty($db_tours)) {
                        $s3_tours = array_keys($db_tours);
                        error_log('H3TM: Falling back to database records (' . count($s3_tours) . ' tours)');
                        $errors[] = 'Using cached tour list (S3 API timeout)';
                    } else {
                        $s3_tours = array();
                    }
                }
            }

            // Use S3 tours directly - no need to check for duplicates with local tours
            if (is_array($s3_tours)) {
                $s3_tours_only = $s3_tours;
                error_log('H3TM: Successfully retrieved ' . count($s3_tours) . ' tours from S3');
            }
        }

        // Skip database tours to avoid duplicates - we already have them from S3
        // The database can have stale entries, so we rely on the actual S3 listing

        sort($s3_tours_only);

        // Send response with any errors
        if (!empty($errors)) {
            wp_send_json_success(array(
                'tours' => $s3_tours_only,
                'errors' => $errors,
                'partial' => true
            ));
        } else {
            wp_send_json_success($s3_tours_only);
        }
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
     * Encode URI path for AWS Signature Version 4
     * Each path segment must be URI-encoded separately
     */
    private function encode_uri_for_signature($path) {
        // If path starts with /, remove it temporarily
        $leading_slash = '';
        if (substr($path, 0, 1) === '/') {
            $leading_slash = '/';
            $path = substr($path, 1);
        }

        // Split by / and encode each segment
        $segments = explode('/', $path);
        $encoded_segments = array_map('rawurlencode', $segments);

        // Rejoin with / and add leading slash back
        return $leading_slash . implode('/', $encoded_segments);
    }

    /**
     * Create AWS Signature V4 authentication headers
     *
     * @param string $method HTTP method (GET, PUT, DELETE, etc.)
     * @param string $uri Request URI (e.g., '/tours/tour-name/file.html')
     * @param string $payload Request payload (empty string for GET/DELETE)
     * @param array $additional_headers Additional headers to sign (e.g., ['x-amz-copy-source' => '/bucket/key'])
     * @return array Headers array with Authorization and required headers
     */
    private function create_auth_headers($method, $uri, $payload = '', $additional_headers = array()) {
        if (!$this->is_configured) {
            return array();
        }

        $host = $this->s3_bucket . '.s3.' . $this->s3_region . '.amazonaws.com';
        $service = 's3';

        // Create date/time strings
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        // Parse query string from URI if present
        $uri_parts = parse_url($uri);
        $path = $uri_parts['path'] ?? $uri;
        $canonical_querystring = $uri_parts['query'] ?? '';

        // Encode the URI path for the canonical request
        $canonical_uri = $this->encode_uri_for_signature($path);

        // Build headers for signing
        $payload_hash = hash('sha256', $payload);
        $headers_to_sign = array(
            'host' => $host,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date' => $datetime
        );

        // Add any additional headers that need to be signed
        foreach ($additional_headers as $key => $value) {
            $headers_to_sign[strtolower($key)] = $value;
        }

        // Sort headers by key for canonical format
        ksort($headers_to_sign);

        // Build canonical headers string and signed headers list
        $canonical_headers = '';
        $signed_headers_array = array();
        foreach ($headers_to_sign as $key => $value) {
            $canonical_headers .= $key . ':' . $value . "\n";
            $signed_headers_array[] = $key;
        }
        $signed_headers = implode(';', $signed_headers_array);

        $canonical_request = $method . "\n" .
                            $canonical_uri . "\n" .
                            $canonical_querystring . "\n" .
                            $canonical_headers . "\n" .
                            $signed_headers . "\n" .
                            $payload_hash;

        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date . '/' . $this->s3_region . '/' . $service . '/aws4_request';
        $string_to_sign = $algorithm . "\n" .
                         $datetime . "\n" .
                         $credential_scope . "\n" .
                         hash('sha256', $canonical_request);

        // Calculate signature
        $signing_key = $this->getSignatureKey($this->aws_secret_key, $date, $this->s3_region, $service);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        // Build authorization header
        $authorization_header = $algorithm . ' ' .
                              'Credential=' . $this->aws_access_key . '/' . $credential_scope . ', ' .
                              'SignedHeaders=' . $signed_headers . ', ' .
                              'Signature=' . $signature;

        // Build return headers array
        $return_headers = array(
            'Authorization' => $authorization_header,
            'x-amz-date' => $datetime,
            'x-amz-content-sha256' => $payload_hash
        );

        // Add additional headers to the return array
        foreach ($additional_headers as $key => $value) {
            $return_headers[$key] = $value;
        }

        return $return_headers;
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

            // Step 6: Clear the tour list cache so the new tour appears immediately
            $this->clear_tour_cache();

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

        // Fix HTML files before uploading
        $this->fix_html_references($temp_extract_dir, $tour_s3_name);

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
            // Use CDN helper if available for tour URL generation
            if ($this->cdn_helper) {
                return $this->cdn_helper->get_tour_base_url($tour_name);
            } else {
                return 'https://' . $config['bucket'] . '.s3.' . $config['region'] . '.amazonaws.com/tours/' . $tour_s3_name . '/';
            }
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

    /**
     * Fix HTML file references for S3 hosting
     */
    private function fix_html_references($temp_extract_dir, $tour_s3_name) {
        // Note: Analytics injection is now handled by Lambda function
        // This method can be used for other HTML fixes if needed in the future
        // For now, we'll just log that files are ready for Lambda processing
        error_log('H3TM S3: Tour files ready for Lambda processing with analytics injection');
    }

    private function register_s3_tour($tour_name, $s3_tour_url) {
        $tours = get_option('h3tm_s3_tours', array());
        $tours[$tour_name] = array(
            'url' => $s3_tour_url,
            'created' => current_time('mysql'),
            'original_name' => sanitize_file_name($tour_name),
            'type' => 's3_direct'
        );
        update_option('h3tm_s3_tours', $tours);

        // Also add to recent uploads transient for immediate visibility
        $recent_uploads = get_transient('h3tm_recent_uploads') ?: array();
        if (!in_array($tour_name, $recent_uploads)) {
            $recent_uploads[] = $tour_name;
            // Keep only the last 10 recent uploads
            if (count($recent_uploads) > 10) {
                array_shift($recent_uploads);
            }
            set_transient('h3tm_recent_uploads', $recent_uploads, DAY_IN_SECONDS * 7);
        }

        error_log('H3TM: Tour registered successfully - ' . $tour_name . ' at ' . $s3_tour_url);
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

    /**
     * Public method to upload a file to S3
     * Used by migration handler
     */
    public function upload_file($local_file, $s3_key, $content_type = null) {
        $config = $this->get_s3_credentials();

        if (!$config['configured']) {
            error_log('H3TM S3: Not configured for upload');
            return false;
        }

        // Auto-detect content type if not provided
        if (!$content_type) {
            $content_type = $this->get_content_type($local_file);
        }

        try {
            // Generate presigned URL for PUT (method already defaults to PUT)
            $presigned_url = $this->generate_simple_presigned_url($config, $s3_key);

            // Read file content
            $content = file_get_contents($local_file);
            if ($content === false) {
                error_log('H3TM S3: Failed to read file: ' . $local_file);
                return false;
            }

            // Upload to S3
            $content_hash = hash('sha256', $content);
            $response = wp_remote_request($presigned_url, array(
                'method' => 'PUT',
                'body' => $content,
                'timeout' => 60,
                'headers' => array(
                    'Content-Type' => $content_type,
                    'Content-Length' => strlen($content),
                    'x-amz-content-sha256' => $content_hash
                )
            ));

            if (is_wp_error($response)) {
                error_log('H3TM S3: Upload error - ' . $response->get_error_message());
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code >= 200 && $response_code < 300) {
                error_log('H3TM S3: Successfully uploaded ' . basename($local_file) . ' to ' . $s3_key);
                return true;
            } else {
                error_log('H3TM S3: Upload failed with code ' . $response_code . ' for ' . $s3_key);
                return false;
            }

        } catch (Exception $e) {
            error_log('H3TM S3: Upload exception - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Archive a tour to the archive/ folder in S3
     *
     * @param string $tour_name Tour name to archive
     * @return array Result with success status and message
     */
    public function archive_tour($tour_name) {
        if (!$this->is_configured) {
            return array('success' => false, 'message' => 'S3 not configured');
        }

        try {
            $tour_s3_name = str_replace(' ', '-', $tour_name);
            $source_prefix = 'tours/' . $tour_s3_name . '/';
            $archive_prefix = 'archive/' . $tour_s3_name . '_' . date('Ymd_His') . '/';

            error_log('H3TM S3 Archive: Moving ' . $source_prefix . ' to ' . $archive_prefix);

            // List all files in the tour
            $files = $this->list_tour_files($tour_s3_name);

            if (empty($files)) {
                return array('success' => false, 'message' => 'Tour not found in S3');
            }

            $moved = 0;
            $errors = 0;

            // Copy each file to archive location
            foreach ($files as $file) {
                $source_key = 'tours/' . $tour_s3_name . '/' . $file;
                $dest_key = 'archive/' . $tour_s3_name . '_' . date('Ymd_His') . '/' . $file;

                if ($this->copy_s3_object($source_key, $dest_key)) {
                    // Delete the original after successful copy
                    if ($this->delete_s3_object($source_key)) {
                        $moved++;
                    } else {
                        $errors++;
                        error_log('H3TM S3 Archive: Failed to delete ' . $source_key);
                    }
                } else {
                    $errors++;
                    error_log('H3TM S3 Archive: Failed to copy ' . $source_key . ' to ' . $dest_key);
                }
            }

            error_log('H3TM S3 Archive: Moved ' . $moved . ' files, ' . $errors . ' errors');

            if ($errors === 0) {
                return array('success' => true, 'message' => 'Tour archived successfully');
            } else {
                return array('success' => false, 'message' => 'Partial archive: ' . $moved . ' files moved, ' . $errors . ' errors');
            }

        } catch (Exception $e) {
            error_log('H3TM S3 Archive Error: ' . $e->getMessage());
            return array('success' => false, 'message' => 'Archive failed: ' . $e->getMessage());
        }
    }

    /**
     * List all files in a tour
     */
    private function list_tour_files($tour_s3_name) {
        $files = array();
        $continuation_token = null;

        do {
            $query_params = array(
                'list-type' => '2',
                'max-keys' => '1000',
                'prefix' => 'tours/' . $tour_s3_name . '/'
            );

            if ($continuation_token) {
                $query_params['continuation-token'] = $continuation_token;
            }

            $request_uri = '/?' . http_build_query($query_params);
            $url = 'https://' . $this->s3_bucket . '.s3.' . $this->s3_region . '.amazonaws.com' . $request_uri;

            $headers = $this->create_auth_headers('GET', $request_uri);

            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'headers' => $headers
            ));

            if (is_wp_error($response)) {
                error_log('H3TM S3: List files error - ' . $response->get_error_message());
                break;
            }

            $body = wp_remote_retrieve_body($response);
            $xml = simplexml_load_string($body);

            if (isset($xml->Contents)) {
                foreach ($xml->Contents as $content) {
                    $key = (string)$content->Key;
                    // Extract relative path from the key
                    $relative_path = str_replace('tours/' . $tour_s3_name . '/', '', $key);
                    if (!empty($relative_path)) {
                        $files[] = $relative_path;
                    }
                }
            }

            $continuation_token = isset($xml->NextContinuationToken) ? (string)$xml->NextContinuationToken : null;

        } while ($continuation_token);

        return $files;
    }

    /**
     * Copy an S3 object
     */
    private function copy_s3_object($source_key, $dest_key) {
        try {
            // URL encode the source path for the copy-source header
            // AWS requires the bucket and key to be URL encoded but slashes preserved
            $encoded_source_key = str_replace('%2F', '/', rawurlencode($source_key));
            $copy_source = '/' . $this->s3_bucket . '/' . $encoded_source_key;

            // For the destination, use the raw key for signing
            $request_uri = '/' . $dest_key;

            // Build the actual request URL with proper encoding
            $encoded_dest_key = str_replace('%2F', '/', rawurlencode($dest_key));
            $url = 'https://' . $this->s3_bucket . '.s3.' . $this->s3_region . '.amazonaws.com/' . $encoded_dest_key;

            // Create auth headers with x-amz-copy-source included in the signature
            $additional_headers = array(
                'x-amz-copy-source' => $copy_source
            );
            $headers = $this->create_auth_headers('PUT', $request_uri, '', $additional_headers);

            $response = wp_remote_request($url, array(
                'method' => 'PUT',
                'timeout' => 30,
                'headers' => $headers
            ));

            if (is_wp_error($response)) {
                error_log('H3TM S3: Copy error - ' . $response->get_error_message());
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code < 200 || $response_code >= 300) {
                $body = wp_remote_retrieve_body($response);
                error_log('H3TM S3: Copy failed with code ' . $response_code . ' for ' . $source_key . ' to ' . $dest_key);
                error_log('H3TM S3: Response body: ' . $body);
                return false;
            }

            return true;

        } catch (Exception $e) {
            error_log('H3TM S3: Copy exception - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an S3 object
     */
    private function delete_s3_object($key) {
        try {
            // Use raw key for signing
            $request_uri = '/' . $key;

            // Build the actual request URL with proper encoding
            $encoded_key = str_replace('%2F', '/', rawurlencode($key));
            $url = 'https://' . $this->s3_bucket . '.s3.' . $this->s3_region . '.amazonaws.com/' . $encoded_key;

            // Create auth headers (encoding will be handled internally)
            $headers = $this->create_auth_headers('DELETE', $request_uri);

            $response = wp_remote_request($url, array(
                'method' => 'DELETE',
                'timeout' => 30,
                'headers' => $headers
            ));

            if (is_wp_error($response)) {
                error_log('H3TM S3: Delete error - ' . $response->get_error_message());
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            return $response_code >= 200 && $response_code < 300;

        } catch (Exception $e) {
            error_log('H3TM S3: Delete exception - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Rename a tour in S3 (actually copies to new location and deletes old)
     *
     * @param string $old_name Current tour name
     * @param string $new_name New tour name
     * @return array Result with success status and message
     */
    public function rename_tour($old_name, $new_name) {
        if (!$this->is_configured) {
            return array('success' => false, 'message' => 'S3 not configured');
        }

        try {
            $old_s3_name = str_replace(' ', '-', $old_name);
            $new_s3_name = str_replace(' ', '-', $new_name);

            error_log('H3TM S3 Rename: ' . $old_s3_name . ' to ' . $new_s3_name);

            // List all files in the old tour
            $files = $this->list_tour_files($old_s3_name);

            if (empty($files)) {
                return array('success' => false, 'message' => 'Tour not found in S3');
            }

            $copied = 0;
            $errors = 0;

            // Copy each file to new location
            foreach ($files as $file) {
                $source_key = 'tours/' . $old_s3_name . '/' . $file;
                $dest_key = 'tours/' . $new_s3_name . '/' . $file;

                if ($this->copy_s3_object($source_key, $dest_key)) {
                    $copied++;
                } else {
                    $errors++;
                    error_log('H3TM S3 Rename: Failed to copy ' . $source_key . ' to ' . $dest_key);
                }
            }

            // If all files copied successfully, delete the old ones
            if ($errors === 0) {
                foreach ($files as $file) {
                    $old_key = 'tours/' . $old_s3_name . '/' . $file;
                    $this->delete_s3_object($old_key);
                }

                return array('success' => true, 'message' => 'Tour renamed successfully');
            } else {
                // Clean up any partial copies
                foreach ($files as $file) {
                    $new_key = 'tours/' . $new_s3_name . '/' . $file;
                    $this->delete_s3_object($new_key);
                }

                return array('success' => false, 'message' => 'Rename failed: ' . $errors . ' copy errors');
            }

        } catch (Exception $e) {
            error_log('H3TM S3 Rename Error: ' . $e->getMessage());
            return array('success' => false, 'message' => 'Rename failed: ' . $e->getMessage());
        }
    }
}