<?php
/**
 * 404 Handler Approach - Intercept 404 errors for h3panos URLs
 * This approach bypasses WordPress rewrite rules entirely by capturing 404s
 */
class H3TM_404_Handler {

    private $s3_config;

    public function __construct() {
        // Hook into 404 handling
        add_action('wp', array($this, 'intercept_404_requests'), 5);
        add_action('template_redirect', array($this, 'handle_404_redirect'), 1);

        // Get S3 config once
        $s3_simple = new H3TM_S3_Simple();
        $this->s3_config = $s3_simple->get_s3_config();
    }

    /**
     * Intercept requests before they become 404s
     */
    public function intercept_404_requests() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Only process h3panos URLs
        if (strpos($request_uri, '/h3panos/') === false) {
            return;
        }

        error_log('H3TM 404 Handler: Intercepting request: ' . $request_uri);

        // Parse the URL manually
        $parsed = $this->parse_h3panos_url($request_uri);
        if (!$parsed) {
            return;
        }

        error_log('H3TM 404 Handler: Parsed - tour: ' . $parsed['tour_name'] . ', file: ' . $parsed['file_path']);

        // Serve S3 content directly
        $this->serve_s3_content($parsed['tour_name'], $parsed['file_path']);
    }

    /**
     * Handle template redirect for 404 errors
     */
    public function handle_404_redirect() {
        if (!is_404()) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Only handle h3panos 404s
        if (strpos($request_uri, '/h3panos/') === false) {
            return;
        }

        error_log('H3TM 404 Handler: Handling 404 for: ' . $request_uri);

        $parsed = $this->parse_h3panos_url($request_uri);
        if (!$parsed) {
            return;
        }

        // Serve S3 content and exit
        $this->serve_s3_content($parsed['tour_name'], $parsed['file_path']);
        exit();
    }

    /**
     * Parse h3panos URL manually
     */
    private function parse_h3panos_url($url) {
        // Remove query parameters
        $url = strtok($url, '?');

        // Remove leading slash and decode URL
        $url = ltrim($url, '/');
        $url = urldecode($url);

        error_log('H3TM 404 Handler: Parsing URL: ' . $url);

        // Pattern: h3panos/TourName or h3panos/TourName/file.ext
        if (!preg_match('#^h3panos/([^/]+)(?:/(.*))?$#', $url, $matches)) {
            error_log('H3TM 404 Handler: URL does not match h3panos pattern');
            return false;
        }

        $tour_name = $matches[1];
        $file_path = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : 'index.htm';

        // Handle special characters and spaces in tour names
        $tour_name = $this->sanitize_tour_name($tour_name);

        return array(
            'tour_name' => $tour_name,
            'file_path' => $file_path
        );
    }

    /**
     * Sanitize tour name while preserving spaces and special characters
     */
    private function sanitize_tour_name($name) {
        // URL decode first
        $name = rawurldecode($name);

        // Replace encoded spaces with regular spaces
        $name = str_replace(array('%20', '+'), ' ', $name);

        // Remove dangerous characters but keep spaces, hyphens, underscores
        $name = preg_replace('/[^\w\s\-_.]/', '', $name);

        // Trim whitespace
        $name = trim($name);

        return $name;
    }

    /**
     * Serve S3 content directly
     */
    private function serve_s3_content($tour_name, $file_path) {
        if (!$this->s3_config['configured']) {
            error_log('H3TM 404 Handler: S3 not configured');
            status_header(503);
            wp_die('Tour service temporarily unavailable', 'Service Unavailable', array('response' => 503));
        }

        // Build S3 URL for the tour file
        $tour_s3_name = sanitize_file_name($tour_name);
        $s3_url = 'https://' . $this->s3_config['bucket'] . '.s3.' . $this->s3_config['region'] . '.amazonaws.com/tours/' . $tour_s3_name . '/' . $file_path;

        error_log('H3TM 404 Handler: Serving from S3: ' . $s3_url);

        // First check if tour exists by trying to get index.htm
        if ($file_path !== 'index.htm') {
            $index_check_url = 'https://' . $this->s3_config['bucket'] . '.s3.' . $this->s3_config['region'] . '.amazonaws.com/tours/' . $tour_s3_name . '/index.htm';
            $check_response = wp_remote_head($index_check_url, array('timeout' => 5));

            if (is_wp_error($check_response) || wp_remote_retrieve_response_code($check_response) !== 200) {
                error_log('H3TM 404 Handler: Tour does not exist: ' . $tour_name);
                status_header(404);
                wp_die('Tour not found', 'Tour Not Found', array('response' => 404));
            }
        }

        // Proxy the file from S3
        $this->proxy_s3_file($s3_url, $file_path, $tour_name);
    }

    /**
     * Proxy file content from S3 with improved error handling
     */
    private function proxy_s3_file($s3_url, $file_path, $tour_name) {
        // Set longer timeout for large files
        $timeout = $this->get_timeout_for_file($file_path);

        $response = wp_remote_get($s3_url, array(
            'timeout' => $timeout,
            'headers' => array(
                'User-Agent' => 'H3TM-404-Handler/1.0'
            )
        ));

        if (is_wp_error($response)) {
            error_log('H3TM 404 Handler Error: ' . $response->get_error_message());
            status_header(502);
            wp_die('Failed to load tour content', 'Service Error', array('response' => 502));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('H3TM 404 Handler: S3 returned ' . $response_code . ' for ' . $s3_url);

            if ($response_code === 404) {
                status_header(404);
                wp_die('Tour file not found: ' . htmlspecialchars($file_path), 'File Not Found', array('response' => 404));
            } else {
                status_header(502);
                wp_die('Tour service error', 'Service Error', array('response' => 502));
            }
        }

        // Get content and headers
        $content = wp_remote_retrieve_body($response);
        $content_type = $this->get_content_type($file_path);

        // For HTML files, inject analytics if enabled
        if (in_array($content_type, array('text/html')) && get_option('h3tm_analytics_enabled', false)) {
            $content = $this->inject_analytics($content, $tour_name);
        }

        // Set headers
        $this->set_response_headers($content_type, strlen($content), $file_path);

        // Output content
        echo $content;
        exit();
    }

    /**
     * Get appropriate timeout based on file type
     */
    private function get_timeout_for_file($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        // Longer timeouts for potentially large files
        $long_timeout_extensions = array('mp4', 'mov', 'avi', 'zip', 'pdf');

        return in_array($ext, $long_timeout_extensions) ? 120 : 30;
    }

    /**
     * Inject analytics into HTML content
     */
    private function inject_analytics($content, $tour_name) {
        $analytics_code = $this->get_analytics_code($tour_name);

        if (empty($analytics_code)) {
            return $content;
        }

        // Try to inject before closing </head> tag
        if (stripos($content, '</head>') !== false) {
            $content = str_ireplace('</head>', $analytics_code . "\n</head>", $content);
        }
        // Fallback: inject before closing </body> tag
        elseif (stripos($content, '</body>') !== false) {
            $content = str_ireplace('</body>', $analytics_code . "\n</body>", $content);
        }
        // Last resort: append to end
        else {
            $content .= "\n" . $analytics_code;
        }

        return $content;
    }

    /**
     * Get analytics code for tour
     */
    private function get_analytics_code($tour_name) {
        $ga_tracking_id = get_option('h3tm_ga_tracking_id', '');

        if (empty($ga_tracking_id)) {
            return '';
        }

        // Google Analytics 4 code
        return "
<!-- Google Analytics 4 - Injected by H3TM 404 Handler -->
<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . esc_attr($ga_tracking_id) . "\"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '" . esc_attr($ga_tracking_id) . "', {
    page_title: 'H3 Tour: " . esc_js($tour_name) . "',
    page_path: '/h3panos/" . esc_js($tour_name) . "/'
  });
</script>";
    }

    /**
     * Set appropriate response headers
     */
    private function set_response_headers($content_type, $content_length, $file_path) {
        // Remove any previous headers
        if (!headers_sent()) {
            header_remove();

            // Set content type
            header('Content-Type: ' . $content_type);
            header('Content-Length: ' . $content_length);

            // Set caching headers based on file type
            $this->set_cache_headers($file_path);

            // Security headers for HTML content
            if (strpos($content_type, 'text/html') !== false) {
                header('X-Frame-Options: SAMEORIGIN');
                header('X-Content-Type-Options: nosniff');
            }
        }
    }

    /**
     * Set cache headers based on file type
     */
    private function set_cache_headers($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        // Long cache for static assets
        $static_extensions = array('js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'woff', 'woff2', 'ttf', 'svg');

        if (in_array($ext, $static_extensions)) {
            // Cache static assets for 1 week
            header('Cache-Control: public, max-age=604800, immutable');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 604800));
        } elseif (in_array($ext, array('html', 'htm'))) {
            // Cache HTML for 1 hour but allow revalidation
            header('Cache-Control: public, max-age=3600, must-revalidate');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
        } else {
            // Default cache for other files
            header('Cache-Control: public, max-age=3600');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
        }
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
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'json' => 'application/json',
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf'
        );

        return isset($content_types[$ext]) ? $content_types[$ext] : 'application/octet-stream';
    }

    /**
     * Check if tour exists on S3
     */
    public function tour_exists($tour_name) {
        if (!$this->s3_config['configured']) {
            return false;
        }

        $tour_s3_name = sanitize_file_name($tour_name);
        $check_url = 'https://' . $this->s3_config['bucket'] . '.s3.' . $this->s3_config['region'] . '.amazonaws.com/tours/' . $tour_s3_name . '/index.htm';

        $response = wp_remote_head($check_url, array('timeout' => 10));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get tour URL for admin display
     */
    public function get_tour_url($tour_name) {
        return site_url('/h3panos/' . rawurlencode($tour_name) . '/');
    }
}