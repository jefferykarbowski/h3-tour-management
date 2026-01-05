<?php
/**
 * S3 URL Proxy - Makes S3 tours appear as local /h3panos/ URLs
 */
class H3TM_S3_Proxy {

    private $cdn_helper;

    public function __construct() {
        // Initialize CDN helper for CloudFront support
        if (class_exists('H3TM_CDN_Helper')) {
            $this->cdn_helper = H3TM_CDN_Helper::get_instance();
        }

        // Add rewrite rules for tour URLs
        add_action('init', array($this, 'add_rewrite_rules'));

        // Universal early handler - catches h3panos requests before template_redirect
        // Works on all hosts (Pantheon, Bluehost, etc.)
        add_action('init', array($this, 'early_tour_handler'), 999);

        // Standard WordPress hook for tour handling (fallback)
        add_action('template_redirect', array($this, 'handle_tour_requests'));

        // Update tour manager to show local URLs
        add_filter('h3tm_tour_url', array($this, 'convert_s3_to_local_url'), 10, 2);
    }

    /**
     * Add rewrite rules for h3panos tours
     */
    public function add_rewrite_rules() {
        // Add query vars first
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Add multiple rewrite rules to handle different URL patterns
        add_rewrite_rule(
            '^h3panos/([^/]+)/?$',
            'index.php?h3tm_tour=$matches[1]&h3tm_file=index.htm',
            'top'
        );

        add_rewrite_rule(
            '^h3panos/([^/]+)/(.+)$',
            'index.php?h3tm_tour=$matches[1]&h3tm_file=$matches[2]',
            'top'
        );

        // Force flush rewrite rules for S3 proxy
        add_action('admin_init', array($this, 'maybe_flush_rewrite_rules'));
    }

    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'h3tm_tour';
        $vars[] = 'h3tm_file';
        return $vars;
    }

    /**
     * Flush rewrite rules if needed
     */
    public function maybe_flush_rewrite_rules() {
        if (get_option('h3tm_s3_rewrite_rules_flushed') !== H3TM_VERSION) {
            flush_rewrite_rules();
            update_option('h3tm_s3_rewrite_rules_flushed', H3TM_VERSION);
            error_log('H3TM S3 Proxy: Flushed rewrite rules for version ' . H3TM_VERSION);
        }
    }

    /**
     * Universal early tour handler
     * Catches h3panos requests before WordPress fully processes them
     * Works on all hosts (Pantheon, Bluehost, etc.)
     */
    public function early_tour_handler() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Only process h3panos requests
        if (strpos($request_uri, '/h3panos/') === false) {
            return;
        }

        error_log('H3TM S3 Proxy: Early handler for: ' . $request_uri);

        // Parse the URL to extract tour name and file
        if (preg_match('#/h3panos/([^/?\#]+)(/([^?\#]*))?#', $request_uri, $matches)) {
            $tour_name = urldecode($matches[1]);
            $file_path = isset($matches[3]) && $matches[3] ? $matches[3] : 'index.htm';

            error_log('H3TM S3 Proxy: Parsed tour=' . $tour_name . ', file=' . $file_path);

            // Resolve tour identifier (slug or display name) to tour_id for S3 lookup
            $resolved = $this->resolve_tour_identifier($tour_name);

            // Check if we need to redirect to current slug (old slug accessed)
            if (is_array($resolved) && isset($resolved['redirect']) && $resolved['redirect']) {
                $redirect_url = site_url('/h3panos/' . $resolved['current_slug']);
                if ($file_path && $file_path !== 'index.htm') {
                    $redirect_url .= '/' . $file_path;
                } else {
                    $redirect_url .= '/';
                }
                error_log('H3TM S3 Proxy: 301 redirect from old slug to: ' . $redirect_url);
                wp_redirect($redirect_url, 301);
                die();
            }

            $resolved_tour_id = $resolved; // It's a string (tour_id)

            // Redirect to directory URL for proper base tag resolution (with loop prevention)
            if ($file_path === 'index.htm' &&
                !preg_match('#/h3panos/[^/]+/$#', $request_uri) &&
                !isset($_GET['_redirected'])) {  // Prevent infinite redirect loops

                $redirect_url = add_query_arg('_redirected', '1',
                    site_url('/h3panos/' . rawurlencode($tour_name) . '/')
                );
                error_log('H3TM S3 Proxy: Redirecting to directory URL: ' . $redirect_url);
                wp_redirect($redirect_url, 301);
                die();
            }

            // Get S3 configuration
            $s3_simple = new H3TM_S3_Simple();
            $s3_config = $s3_simple->get_s3_config();

            if (!$s3_config['configured']) {
                wp_die('S3 not configured for tour delivery', 'Configuration Error', array('response' => 503));
            }

            // Use CDN helper if available, otherwise fallback to direct S3
            if ($this->cdn_helper) {
                // Use resolved tour_id for CDN URLs
                $urls = $this->cdn_helper->get_tour_urls($resolved_tour_id, $file_path);
                error_log('H3TM S3 Proxy: Using CDN helper, trying URLs: ' . implode(', ', $urls));

                $success = false;
                foreach ($urls as $url) {
                    if ($this->try_proxy_s3_file($url, $file_path)) {
                        $success = true;
                        break;
                    }
                }

                if (!$success) {
                    error_log('H3TM S3 Proxy: All URLs failed');
                    wp_die('Tour file not found', 'Tour Not Found', array('response' => 404));
                }
            } else {
                // Use resolved tour_id for S3 URLs
                // Check if this is a tour_id (immutable identifier)
                if ($this->is_tour_id($resolved_tour_id)) {
                    // Tour IDs are used directly as S3 folder names
                    $s3_url = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/tours/' . rawurlencode($resolved_tour_id) . '/' . $file_path;
                    error_log('H3TM S3 Proxy: Using tour_id format: ' . $s3_url);
                    $this->proxy_s3_file($s3_url, $file_path);
                } else {
                    // Legacy: Fallback to original logic with resolved identifier
                    $s3_url_with_spaces = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/tours/' . rawurlencode($resolved_tour_id) . '/' . $file_path;
                    $s3_url_with_dashes = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/tours/' . rawurlencode(str_replace(' ', '-', $resolved_tour_id)) . '/' . $file_path;

                    error_log('H3TM S3 Proxy: Trying S3 URLs - spaces: ' . $s3_url_with_spaces . ', dashes: ' . $s3_url_with_dashes);

                    // Try with spaces first, then with dashes if that fails
                    if (!$this->try_proxy_s3_file($s3_url_with_spaces, $file_path)) {
                        error_log('H3TM S3 Proxy: Spaces version failed, trying with dashes');
                        $this->proxy_s3_file($s3_url_with_dashes, $file_path);
                    }
                }
            }

            // Properly terminate after serving content
            die();
        }
    }

    /**
     * Handle tour file requests and proxy from S3
     */
    public function handle_tour_requests() {
        // Debug: Always log template_redirect calls
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, 'h3panos') !== false) {
            error_log('H3TM S3 Proxy: template_redirect called for URI: ' . $request_uri);
        }

        $tour_name = get_query_var('h3tm_tour');
        $file_path = get_query_var('h3tm_file');

        // Decode URL encoding (convert %20 to spaces)
        $tour_name = urldecode($tour_name);

        if (empty($tour_name)) {
            // Fallback: Parse URL directly if query vars not set
            if (strpos($request_uri, 'h3panos') !== false && preg_match('#/h3panos/([^/]+)(/(.*))?#', $request_uri, $matches)) {
                error_log('H3TM S3 Proxy: Fallback - parsing URL directly');
                $tour_name = urldecode($matches[1]);
                $file_path = isset($matches[3]) && $matches[3] ? $matches[3] : 'index.htm';
                error_log('H3TM S3 Proxy: Parsed tour_name=' . $tour_name . ', file_path=' . $file_path);
            } else {
                return; // Not a tour request
            }
        }

        error_log('H3TM S3 Proxy: Processing tour request for=' . $tour_name . ', file=' . $file_path);

        // Resolve tour identifier (slug or display name) to tour_id for S3 lookup
        $resolved = $this->resolve_tour_identifier($tour_name);

        // Check if we need to redirect to current slug (old slug accessed)
        if (is_array($resolved) && isset($resolved['redirect']) && $resolved['redirect']) {
            $redirect_url = site_url('/h3panos/' . $resolved['current_slug']);
            if ($file_path && $file_path !== 'index.htm') {
                $redirect_url .= '/' . $file_path;
            } else {
                $redirect_url .= '/';
            }
            error_log('H3TM S3 Proxy: 301 redirect from old slug to: ' . $redirect_url);
            wp_redirect($redirect_url, 301);
            die();
        }

        $resolved_tour_id = $resolved; // It's a string (tour_id)

        // Default to index.htm if no file specified
        if (empty($file_path)) {
            $file_path = 'index.htm';
        }

        // Redirect to directory URL for proper base tag resolution (with loop prevention)
        if ($file_path === 'index.htm' &&
            $_SERVER['REQUEST_URI'] &&
            !preg_match('#/h3panos/[^/]+/$#', $_SERVER['REQUEST_URI']) &&
            !isset($_GET['_redirected'])) {  // Prevent infinite redirect loops

            $redirect_url = add_query_arg('_redirected', '1',
                site_url('/h3panos/' . rawurlencode($tour_name) . '/')
            );
            error_log('H3TM S3 Proxy: Redirecting to directory URL: ' . $redirect_url);
            wp_redirect($redirect_url, 301);
            // Use WordPress die() instead of exit() for Pantheon compatibility
            die();
        }

        // Get S3 configuration
        $s3_simple = new H3TM_S3_Simple();
        $s3_config = $s3_simple->get_s3_config();

        if (!$s3_config['configured']) {
            wp_die('S3 not configured for tour delivery');
        }

        // Use CDN helper if available, otherwise fallback to direct S3
        if ($this->cdn_helper) {
            // Use resolved tour_id for CDN URLs
            $urls = $this->cdn_helper->get_tour_urls($resolved_tour_id, $file_path);
            error_log('H3TM S3 Proxy: Using CDN helper for "' . $tour_name . '"');
            error_log('H3TM S3 Proxy: CDN URLs: ' . implode(', ', $urls));

            $success = false;
            foreach ($urls as $url) {
                if ($this->try_proxy_s3_file($url, $file_path)) {
                    $success = true;
                    break;
                }
                error_log('H3TM S3 Proxy: Failed to fetch from ' . $url);
            }

            if (!$success) {
                error_log('H3TM S3 Proxy: All CDN URLs failed');
                wp_die('Tour file not found', 'Tour Not Found', array('response' => 404));
            }
        } else {
            // Use resolved tour_id for S3 URLs
            // Check if this is a tour_id (immutable identifier)
            if ($this->is_tour_id($resolved_tour_id)) {
                // Tour IDs are used directly as S3 folder names
                $s3_url = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/tours/' . rawurlencode($resolved_tour_id) . '/' . $file_path;
                error_log('H3TM S3 Proxy: Using tour_id format: ' . $s3_url);
                $this->proxy_s3_file($s3_url, $file_path);
            } else {
                // Legacy: Fallback to original logic with resolved identifier
                $s3_url_with_spaces = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/tours/' . rawurlencode($resolved_tour_id) . '/' . $file_path;
                $s3_url_with_dashes = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/tours/' . rawurlencode(str_replace(' ', '-', $resolved_tour_id)) . '/' . $file_path;

                error_log('H3TM S3 Proxy: Trying both naming conventions for "' . $resolved_tour_id . '"');
                error_log('H3TM S3 Proxy: URL with spaces: ' . $s3_url_with_spaces);
                error_log('H3TM S3 Proxy: URL with dashes: ' . $s3_url_with_dashes);

                // Try with spaces first, then with dashes if that fails
                if (!$this->try_proxy_s3_file($s3_url_with_spaces, $file_path)) {
                    error_log('H3TM S3 Proxy: Spaces version failed, trying with dashes');
                    $this->proxy_s3_file($s3_url_with_dashes, $file_path);
                }
            }
        }

        // For Pantheon compatibility, use wp_die() instead of exit()
        wp_die('', '', array('response' => 200));
    }

    /**
     * Try to proxy file content from S3 (returns true on success, false on failure)
     */
    private function try_proxy_s3_file($s3_url, $file_path) {
        // Clear any output buffers to prevent conflicts
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Check if this is a HEAD request (browser checking availability)
        $is_head_request = ($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD';

        // For HEAD requests, use head method to save bandwidth
        if ($is_head_request) {
            $response = wp_remote_head($s3_url, array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => '*/*',
                    'User-Agent' => 'H3TM S3 Proxy/2.2'
                )
            ));
        } else {
            // Get file from S3
            $response = wp_remote_get($s3_url, array(
                'timeout' => 30,
                'headers' => array(
                    'Accept' => '*/*',
                    'User-Agent' => 'H3TM S3 Proxy/2.2'
                )
            ));
        }

        if (is_wp_error($response)) {
            error_log('H3TM S3 Proxy: Try failed - ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('H3TM S3 Proxy: Try failed - S3 returned ' . $response_code);
            return false;
        }

        // Success! Send the content
        $content_type = $this->get_content_type($file_path);

        // For HEAD requests, only send headers
        if ($is_head_request) {
            $content_length = wp_remote_retrieve_header($response, 'content-length');

            // Pantheon-compatible: Set proper status code first
            status_header(200);

            // Set appropriate headers
            header('Content-Type: ' . $content_type);
            if ($content_length) {
                header('Content-Length: ' . $content_length);
            }

            // Cache headers for better performance
            header('Cache-Control: public, max-age=3600');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));

            // Security headers
            header('X-Content-Type-Options: nosniff');

            // For HEAD requests, no body is sent
            return true;
        }

        // Get content for GET requests
        $content = wp_remote_retrieve_body($response);

        // Pantheon-compatible: Set proper status code first
        status_header(200);

        // Set appropriate headers
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($content));

        // Cache headers for better performance
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));

        // Security headers
        header('X-Content-Type-Options: nosniff');

        // Output the content
        echo $content;

        return true;
    }

    /**
     * Proxy file content from S3 (dies with error if not found)
     */
    private function proxy_s3_file($s3_url, $file_path) {
        // Clear any output buffers to prevent conflicts
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Check if this is a HEAD request (browser checking availability)
        $is_head_request = ($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD';

        // For HEAD requests, use head method to save bandwidth
        if ($is_head_request) {
            $response = wp_remote_head($s3_url, array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => '*/*',
                    'User-Agent' => 'H3TM S3 Proxy/2.2'
                )
            ));
        } else {
            // Get file from S3
            $response = wp_remote_get($s3_url, array(
                'timeout' => 30,
                'headers' => array(
                    'Accept' => '*/*',
                    'User-Agent' => 'H3TM S3 Proxy/2.2'
                )
            ));
        }

        if (is_wp_error($response)) {
            error_log('H3TM S3 Proxy Error: ' . $response->get_error_message());
            status_header(404);
            wp_die('Tour file not found', 'Tour Not Found', array('response' => 404));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('H3TM S3 Proxy: S3 returned ' . $response_code . ' for ' . $s3_url);
            status_header(404);
            wp_die('Tour file not found', 'Tour Not Found', array('response' => 404));
        }

        // Get content type
        $content_type = $this->get_content_type($file_path);

        // Get optimized cache headers from CDN helper if available
        $cache_headers = array();
        if ($this->cdn_helper) {
            $cache_headers = $this->cdn_helper->get_cache_headers($file_path);
        } else {
            $cache_headers['Cache-Control'] = 'public, max-age=3600';
            $cache_headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', time() + 3600);
        }

        // For HEAD requests, only send headers
        if ($is_head_request) {
            $content_length = wp_remote_retrieve_header($response, 'content-length');

            // Pantheon-compatible: Set proper status code first
            status_header(200);

            // Set appropriate headers
            header('Content-Type: ' . $content_type);
            if ($content_length) {
                header('Content-Length: ' . $content_length);
            }

            // Apply cache headers
            foreach ($cache_headers as $header => $value) {
                header($header . ': ' . $value);
            }

            // Security headers
            header('X-Content-Type-Options: nosniff');

            // For HEAD requests, no body is sent
            return;
        }

        // Get content for GET requests
        $content = wp_remote_retrieve_body($response);

        // Pantheon-compatible: Set proper status code first
        status_header(200);

        // Set appropriate headers
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($content));

        // Cache headers for better performance
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));

        // Security headers
        header('X-Content-Type-Options: nosniff');

        // Output the content - no exit() to avoid nginx issues
        echo $content;

        // Let WordPress know we've handled the request
        add_filter('wp_die_handler', function() {
            return function() { die(); };
        });
    }

    /**
     * Convert S3 URL to local /h3panos/ URL
     */
    public function convert_s3_to_local_url($url, $tour_name) {
        // Convert S3 URLs to local /h3panos/ URLs for display
        return site_url('/h3panos/' . rawurlencode($tour_name) . '/');
    }

    /**
     * Get content type for file
     */
    private function get_content_type($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        $content_types = array(
            'html' => 'text/html',
            'htm' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'json' => 'application/json',
            'txt' => 'text/plain'
        );

        return isset($content_types[$ext]) ? $content_types[$ext] : 'application/octet-stream';
    }

    /**
     * Check if a string matches tour_id pattern
     * Format: 20250114_173045_8k3j9d2m (timestamp + 8-char random)
     */
    private function is_tour_id($str) {
        return preg_match('/^\d{8}_\d{6}_[a-z0-9]{8}$/', $str) === 1;
    }

    /**
     * Resolve tour identifier (slug or display name) to tour_id for S3 lookup
     * Returns either tour_id string OR array with 'redirect' info for old slugs
     */
    private function resolve_tour_identifier($identifier) {
        // Check if it's already a tour_id
        if ($this->is_tour_id($identifier)) {
            return $identifier;
        }

        // Try to resolve slug to tour_id from metadata
        if (class_exists('H3TM_Tour_Metadata')) {
            $metadata = new H3TM_Tour_Metadata();

            // Normalize identifier (handle spaces, capitals, etc.)
            $normalized_slug = sanitize_title(urldecode($identifier));

            // First try current slug
            $tour = $metadata->get_by_slug($normalized_slug);

            if ($tour && !empty($tour->tour_id)) {
                error_log('H3TM S3 Proxy: Resolved slug "' . $identifier . '" (normalized: "' . $normalized_slug . '") to tour_id: ' . $tour->tour_id);
                return $tour->tour_id;
            }

            // Check if this is an old slug that needs 301 redirect
            $tour = $metadata->find_by_old_slug($normalized_slug);

            if ($tour && !empty($tour->tour_slug)) {
                error_log('H3TM S3 Proxy: Old slug "' . $identifier . '" found, should redirect to: ' . $tour->tour_slug);
                return array(
                    'redirect' => true,
                    'current_slug' => $tour->tour_slug,
                    'tour_id' => $tour->tour_id
                );
            }
        }

        // Fallback: assume it's a legacy tour name (convert spaces to dashes for S3)
        error_log('H3TM S3 Proxy: No metadata found for "' . $identifier . '", using as legacy tour name');
        return $identifier;
    }

    /**
     * Clean up old uploads (can be called manually or via cron)
     */
    public static function cleanup_old_uploads() {
        $s3_simple = new H3TM_S3_Simple();
        $s3_config = $s3_simple->get_s3_config();

        if (!$s3_config['configured']) {
            return false;
        }

        // This would require AWS SDK to list and delete old uploads
        // For now, we rely on S3 lifecycle policies to clean up old uploads
        error_log('H3TM S3 Proxy: Cleanup should be handled by S3 lifecycle policies');
        return true;
    }
}