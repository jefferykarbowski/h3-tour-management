<?php
/**
 * WordPress Action Hook Approach - Intercept requests using 'wp' action
 * This bypasses the rewrite system by hooking into the 'wp' action early
 */
class H3TM_Action_Hook {

    private $s3_config;

    public function __construct() {
        // Hook into 'wp' action with high priority to intercept requests early
        add_action('wp', array($this, 'intercept_h3panos_requests'), 1);

        // Also hook into 'parse_request' as a backup
        add_action('parse_request', array($this, 'parse_h3panos_requests'), 1);

        // Get S3 config
        $s3_simple = new H3TM_S3_Simple();
        $this->s3_config = $s3_simple->get_s3_config();

        error_log('H3TM Action Hook: Initialized with S3 configured=' . ($this->s3_config['configured'] ? 'YES' : 'NO'));
    }

    /**
     * Intercept h3panos requests during 'wp' action
     */
    public function intercept_h3panos_requests() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Only process h3panos URLs
        if (strpos($request_uri, '/h3panos/') === false) {
            return;
        }

        error_log('H3TM Action Hook: Intercepting wp action for: ' . $request_uri);

        $this->handle_h3panos_request($request_uri);
    }

    /**
     * Parse h3panos requests during 'parse_request' action
     */
    public function parse_h3panos_requests($wp) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Only process h3panos URLs that haven't been handled yet
        if (strpos($request_uri, '/h3panos/') === false) {
            return;
        }

        error_log('H3TM Action Hook: Intercepting parse_request for: ' . $request_uri);

        $this->handle_h3panos_request($request_uri);
    }

    /**
     * Handle h3panos requests
     */
    private function handle_h3panos_request($request_uri) {
        // Parse the URL
        $parsed = $this->parse_h3panos_url($request_uri);

        if (!$parsed) {
            error_log('H3TM Action Hook: Failed to parse URL: ' . $request_uri);
            return;
        }

        error_log('H3TM Action Hook: Parsed tour=' . $parsed['tour_name'] . ', file=' . $parsed['file_path']);

        // Check if S3 is configured
        if (!$this->s3_config['configured']) {
            error_log('H3TM Action Hook: S3 not configured');
            $this->send_error(503, 'Tour service not configured');
            return;
        }

        // Serve the content and exit
        $this->serve_s3_content($parsed['tour_name'], $parsed['file_path']);
        exit();
    }

    /**
     * Parse h3panos URL to extract tour name and file path
     */
    private function parse_h3panos_url($url) {
        // Remove query parameters and decode
        $url = strtok($url, '?');
        $url = ltrim($url, '/');
        $url = urldecode($url);

        // Pattern matching for h3panos URLs
        if (!preg_match('#^h3panos/([^/]+)(?:/(.*))?$#', $url, $matches)) {
            return false;
        }

        $tour_name = $this->sanitize_tour_name($matches[1]);
        $file_path = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : 'index.htm';

        return array(
            'tour_name' => $tour_name,
            'file_path' => $file_path
        );
    }

    /**
     * Sanitize tour name while preserving readability
     */
    private function sanitize_tour_name($name) {
        // URL decode and handle various space encodings
        $name = rawurldecode($name);
        $name = str_replace(array('%20', '+', '_'), ' ', $name);

        // Remove potentially dangerous characters but keep readable ones
        $name = preg_replace('/[^\w\s\-.]/', '', $name);

        // Clean up multiple spaces and trim
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $name;
    }

    /**
     * Serve content from S3
     */
    private function serve_s3_content($tour_name, $file_path) {
        // Sanitize tour name for S3 URL
        $tour_s3_name = $this->sanitize_for_s3($tour_name);

        // Build S3 URL
        $s3_url = sprintf(
            'https://%s.s3.%s.amazonaws.com/tours/%s/%s',
            $this->s3_config['bucket'],
            $this->s3_config['region'],
            $tour_s3_name,
            $file_path
        );

        error_log('H3TM Action Hook: Serving from S3: ' . $s3_url);

        // Check if tour exists (for non-index files)
        if ($file_path !== 'index.htm' && !$this->tour_exists($tour_s3_name)) {
            error_log('H3TM Action Hook: Tour does not exist: ' . $tour_name);
            $this->send_error(404, 'Tour not found: ' . htmlspecialchars($tour_name));
            return;
        }

        // Proxy the file from S3
        $this->proxy_s3_file($s3_url, $file_path, $tour_name);
    }

    /**
     * Check if tour exists on S3
     */
    private function tour_exists($tour_s3_name) {
        $index_url = sprintf(
            'https://%s.s3.%s.amazonaws.com/tours/%s/index.htm',
            $this->s3_config['bucket'],
            $this->s3_config['region'],
            $tour_s3_name
        );

        $response = wp_remote_head($index_url, array(
            'timeout' => 10,
            'user_agent' => 'H3TM-Action-Hook/1.0'
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Sanitize tour name for S3 compatibility
     */
    private function sanitize_for_s3($name) {
        // Replace spaces with hyphens and remove special characters
        $name = preg_replace('/\s+/', '-', $name);
        $name = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $name);
        return $name;
    }

    /**
     * Proxy file from S3 with enhanced error handling
     */
    private function proxy_s3_file($s3_url, $file_path, $tour_name) {
        // Set timeout based on file type
        $timeout = $this->get_timeout_for_file($file_path);

        // Make request to S3
        $response = wp_remote_get($s3_url, array(
            'timeout' => $timeout,
            'headers' => array(
                'User-Agent' => 'H3TM-Action-Hook/1.0'
            )
        ));

        // Handle errors
        if (is_wp_error($response)) {
            error_log('H3TM Action Hook Error: ' . $response->get_error_message());
            $this->send_error(502, 'Failed to load tour content');
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('H3TM Action Hook: S3 returned ' . $response_code . ' for ' . $s3_url);

            if ($response_code === 404) {
                $this->send_error(404, 'Tour file not found: ' . htmlspecialchars($file_path));
            } else {
                $this->send_error(502, 'Tour service error (HTTP ' . $response_code . ')');
            }
            return;
        }

        // Get content and prepare response
        $content = wp_remote_retrieve_body($response);
        $content_type = $this->get_content_type($file_path);

        // Inject analytics for HTML files
        if ($this->should_inject_analytics($content_type)) {
            $content = $this->inject_analytics($content, $tour_name, $file_path);
        }

        // Set response headers
        $this->set_response_headers($content_type, $content, $file_path);

        // Output content
        echo $content;
    }

    /**
     * Determine if analytics should be injected
     */
    private function should_inject_analytics($content_type) {
        return get_option('h3tm_analytics_enabled', false) &&
               strpos($content_type, 'text/html') !== false &&
               !empty(get_option('h3tm_ga_tracking_id', ''));
    }

    /**
     * Inject analytics code into HTML content
     */
    private function inject_analytics($content, $tour_name, $file_path) {
        $ga_tracking_id = get_option('h3tm_ga_tracking_id', '');

        // Create analytics code
        $analytics_code = sprintf(
            '
<!-- Google Analytics 4 - Injected by H3TM Action Hook -->
<script async src="https://www.googletagmanager.com/gtag/js?id=%s"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag(\'js\', new Date());
  gtag(\'config\', \'%s\', {
    page_title: \'H3 Tour: %s\',
    page_path: \'/h3panos/%s/%s\',
    custom_map: {
      \'tour_name\': \'%s\',
      \'file_name\': \'%s\'
    }
  });
</script>',
            esc_attr($ga_tracking_id),
            esc_attr($ga_tracking_id),
            esc_js($tour_name),
            esc_js($tour_name),
            esc_js($file_path),
            esc_js($tour_name),
            esc_js($file_path)
        );

        // Try injection points in order of preference
        $injection_points = array(
            '</head>' => $analytics_code . "\n</head>",
            '</body>' => $analytics_code . "\n</body>",
            '</html>' => $analytics_code . "\n</html>"
        );

        foreach ($injection_points as $target => $replacement) {
            if (stripos($content, $target) !== false) {
                $content = str_ireplace($target, $replacement, $content);
                error_log('H3TM Action Hook: Analytics injected before ' . $target);
                break;
            }
        }

        return $content;
    }

    /**
     * Set appropriate response headers
     */
    private function set_response_headers($content_type, $content, $file_path) {
        if (headers_sent()) {
            return;
        }

        // Clear any existing headers
        header_remove();

        // Set content headers
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($content));

        // Set caching headers
        $this->set_cache_headers($file_path);

        // Set security headers for HTML content
        if (strpos($content_type, 'text/html') !== false) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }

        // CORS headers for API-like files
        if (strpos($content_type, 'application/json') !== false) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
        }
    }

    /**
     * Set caching headers based on file type
     */
    private function set_cache_headers($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        // Different cache strategies for different file types
        $cache_strategies = array(
            // Long cache for static assets
            'static' => array(
                'extensions' => array('js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf'),
                'max_age' => 604800, // 1 week
                'immutable' => true
            ),
            // Medium cache for HTML
            'html' => array(
                'extensions' => array('html', 'htm'),
                'max_age' => 3600, // 1 hour
                'revalidate' => true
            ),
            // Short cache for dynamic content
            'dynamic' => array(
                'extensions' => array('json', 'xml'),
                'max_age' => 300, // 5 minutes
                'revalidate' => true
            )
        );

        foreach ($cache_strategies as $strategy => $config) {
            if (in_array($ext, $config['extensions'])) {
                $cache_control = 'public, max-age=' . $config['max_age'];

                if (isset($config['immutable']) && $config['immutable']) {
                    $cache_control .= ', immutable';
                }

                if (isset($config['revalidate']) && $config['revalidate']) {
                    $cache_control .= ', must-revalidate';
                }

                header('Cache-Control: ' . $cache_control);
                header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $config['max_age']));
                return;
            }
        }

        // Default cache for unknown file types
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
    }

    /**
     * Get timeout based on file type and size expectations
     */
    private function get_timeout_for_file($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        $timeout_map = array(
            // Large media files
            'mp4' => 120,
            'mov' => 120,
            'avi' => 120,
            'zip' => 120,
            'pdf' => 60,
            // Regular assets
            'js' => 30,
            'css' => 30,
            'html' => 30,
            'htm' => 30,
            // Images
            'png' => 20,
            'jpg' => 20,
            'jpeg' => 20,
            'gif' => 20
        );

        return isset($timeout_map[$ext]) ? $timeout_map[$ext] : 30;
    }

    /**
     * Get content type for file
     */
    private function get_content_type($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        $content_types = array(
            'html' => 'text/html; charset=UTF-8',
            'htm' => 'text/html; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'xml' => 'application/xml; charset=UTF-8',
            'txt' => 'text/plain; charset=UTF-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        );

        return isset($content_types[$ext]) ? $content_types[$ext] : 'application/octet-stream';
    }

    /**
     * Send error response
     */
    private function send_error($code, $message) {
        if (!headers_sent()) {
            status_header($code);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $title = ($code === 404) ? 'Tour Not Found' : 'Service Error';

        echo sprintf('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>%s</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0; padding: 40px;
            background: #f8f9fa;
            color: #333;
        }
        .error-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #dc3545; margin-top: 0; }
        .error-code { font-size: 64px; font-weight: bold; color: #6c757d; margin-bottom: 20px; }
        .home-link {
            display: inline-block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        .home-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">%d</div>
        <h1>%s</h1>
        <p>%s</p>
        <a href="/" class="home-link">&larr; Return to homepage</a>
    </div>
</body>
</html>',
            htmlspecialchars($title),
            $code,
            htmlspecialchars($title),
            htmlspecialchars($message)
        );

        exit();
    }

    /**
     * Get status information for admin
     */
    public function get_status() {
        return array(
            'active' => true,
            'method' => 'WordPress Action Hook',
            'hooks' => array('wp', 'parse_request'),
            's3_configured' => $this->s3_config['configured'],
            'analytics_enabled' => get_option('h3tm_analytics_enabled', false)
        );
    }

    /**
     * Test the action hook handler
     */
    public function test_handler($tour_name = 'test-tour') {
        $test_url = home_url('/h3panos/' . rawurlencode($tour_name) . '/');

        $response = wp_remote_get($test_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'H3TM-Action-Hook-Test/1.0'
            )
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Test failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);

        return array(
            'success' => in_array($response_code, array(200, 404)),
            'response_code' => $response_code,
            'message' => 'Action hook handler responded with status: ' . $response_code
        );
    }
}