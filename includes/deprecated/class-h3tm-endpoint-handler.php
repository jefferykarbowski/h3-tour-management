<?php
/**
 * Custom Endpoint Approach - Use WordPress REST API or custom endpoint
 * This creates a dedicated endpoint that handles tour requests
 */
class H3TM_Endpoint_Handler {

    private $s3_config;
    private $endpoint_base = 'h3tours';

    public function __construct() {
        // Initialize S3 config
        $s3_simple = new H3TM_S3_Simple();
        $this->s3_config = $s3_simple->get_s3_config();

        // Add REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Add custom query vars and rewrite rules for endpoint
        add_action('init', array($this, 'add_endpoint_rules'));
        add_filter('query_vars', array($this, 'add_endpoint_query_vars'));
        add_action('template_redirect', array($this, 'handle_endpoint_requests'));

        // Add custom rewrite rules that redirect h3panos to endpoint
        add_action('init', array($this, 'add_redirect_rules'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('h3tm/v1', '/tour/(?P<tour_name>[^/]+)/?(?P<file_path>.*)?', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_rest_tour_request'),
            'permission_callback' => '__return_true',
            'args' => array(
                'tour_name' => array(
                    'required' => true,
                    'sanitize_callback' => array($this, 'sanitize_tour_name')
                ),
                'file_path' => array(
                    'default' => 'index.htm',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        // Route to check tour availability
        register_rest_route('h3tm/v1', '/tour/(?P<tour_name>[^/]+)/check', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_tour_availability'),
            'permission_callback' => '__return_true',
            'args' => array(
                'tour_name' => array(
                    'required' => true,
                    'sanitize_callback' => array($this, 'sanitize_tour_name')
                )
            )
        ));
    }

    /**
     * Add endpoint rewrite rules
     */
    public function add_endpoint_rules() {
        // Add rewrite rules for the custom endpoint
        add_rewrite_rule(
            '^' . $this->endpoint_base . '/([^/]+)/?$',
            'index.php?' . $this->endpoint_base . '=$matches[1]&tour_file=index.htm',
            'top'
        );

        add_rewrite_rule(
            '^' . $this->endpoint_base . '/([^/]+)/(.+)$',
            'index.php?' . $this->endpoint_base . '=$matches[1]&tour_file=$matches[2]',
            'top'
        );

        // Flush rewrite rules if needed
        if (get_option('h3tm_endpoint_rules_flushed') !== H3TM_VERSION) {
            flush_rewrite_rules();
            update_option('h3tm_endpoint_rules_flushed', H3TM_VERSION);
        }
    }

    /**
     * Add redirect rules for h3panos URLs
     */
    public function add_redirect_rules() {
        // Redirect h3panos URLs to the endpoint
        add_rewrite_rule(
            '^h3panos/([^/]+)/?$',
            'index.php?' . $this->endpoint_base . '=$matches[1]&tour_file=index.htm&h3panos_redirect=1',
            'top'
        );

        add_rewrite_rule(
            '^h3panos/([^/]+)/(.+)$',
            'index.php?' . $this->endpoint_base . '=$matches[1]&tour_file=$matches[2]&h3panos_redirect=1',
            'top'
        );
    }

    /**
     * Add query vars for endpoint
     */
    public function add_endpoint_query_vars($vars) {
        $vars[] = $this->endpoint_base;
        $vars[] = 'tour_file';
        $vars[] = 'h3panos_redirect';
        return $vars;
    }

    /**
     * Handle endpoint requests
     */
    public function handle_endpoint_requests() {
        $tour_name = get_query_var($this->endpoint_base);
        $file_path = get_query_var('tour_file', 'index.htm');
        $is_redirect = get_query_var('h3panos_redirect');

        if (empty($tour_name)) {
            return;
        }

        error_log('H3TM Endpoint: Handling request - tour=' . $tour_name . ', file=' . $file_path . ', redirect=' . $is_redirect);

        // Sanitize inputs
        $tour_name = $this->sanitize_tour_name($tour_name);
        $file_path = sanitize_text_field($file_path);

        if (empty($file_path)) {
            $file_path = 'index.htm';
        }

        // Serve the tour content
        $this->serve_tour_content($tour_name, $file_path);
        exit();
    }

    /**
     * Handle REST API tour requests
     */
    public function handle_rest_tour_request($request) {
        $tour_name = $request->get_param('tour_name');
        $file_path = $request->get_param('file_path');

        if (empty($file_path)) {
            $file_path = 'index.htm';
        }

        error_log('H3TM Endpoint: REST request - tour=' . $tour_name . ', file=' . $file_path);

        // For REST API, we return JSON responses for API calls
        // but serve content directly for file requests
        if ($request->get_header('Accept') && strpos($request->get_header('Accept'), 'application/json') !== false) {
            return $this->get_tour_info($tour_name, $file_path);
        }

        // Serve content directly
        $this->serve_tour_content($tour_name, $file_path);
        exit();
    }

    /**
     * Check tour availability via REST API
     */
    public function check_tour_availability($request) {
        $tour_name = $request->get_param('tour_name');

        if (!$this->s3_config['configured']) {
            return new WP_Error('s3_not_configured', 'S3 service not configured', array('status' => 503));
        }

        $exists = $this->tour_exists($tour_name);

        return rest_ensure_response(array(
            'tour_name' => $tour_name,
            'exists' => $exists,
            'url' => $exists ? $this->get_tour_url($tour_name) : null,
            'endpoint_url' => rest_url('h3tm/v1/tour/' . rawurlencode($tour_name))
        ));
    }

    /**
     * Get tour information
     */
    private function get_tour_info($tour_name, $file_path) {
        if (!$this->s3_config['configured']) {
            return new WP_Error('s3_not_configured', 'S3 service not configured', array('status' => 503));
        }

        $tour_exists = $this->tour_exists($tour_name);

        if (!$tour_exists) {
            return new WP_Error('tour_not_found', 'Tour not found: ' . $tour_name, array('status' => 404));
        }

        $s3_url = $this->get_s3_url($tour_name, $file_path);

        return rest_ensure_response(array(
            'tour_name' => $tour_name,
            'file_path' => $file_path,
            'exists' => true,
            'local_url' => $this->get_tour_url($tour_name) . $file_path,
            's3_url' => $s3_url,
            'endpoint_url' => rest_url('h3tm/v1/tour/' . rawurlencode($tour_name) . '/' . $file_path)
        ));
    }

    /**
     * Serve tour content from S3
     */
    private function serve_tour_content($tour_name, $file_path) {
        if (!$this->s3_config['configured']) {
            $this->send_error(503, 'Tour service not configured');
            return;
        }

        // Check if tour exists for non-index files
        if ($file_path !== 'index.htm' && !$this->tour_exists($tour_name)) {
            $this->send_error(404, 'Tour not found: ' . htmlspecialchars($tour_name));
            return;
        }

        // Get S3 URL
        $s3_url = $this->get_s3_url($tour_name, $file_path);

        error_log('H3TM Endpoint: Serving from S3: ' . $s3_url);

        // Proxy content from S3
        $this->proxy_s3_content($s3_url, $file_path, $tour_name);
    }

    /**
     * Get S3 URL for tour file
     */
    private function get_s3_url($tour_name, $file_path) {
        $tour_s3_name = $this->sanitize_for_s3($tour_name);

        return sprintf(
            'https://%s.s3.%s.amazonaws.com/tours/%s/%s',
            $this->s3_config['bucket'],
            $this->s3_config['region'],
            $tour_s3_name,
            $file_path
        );
    }

    /**
     * Check if tour exists on S3
     */
    private function tour_exists($tour_name) {
        $tour_s3_name = $this->sanitize_for_s3($tour_name);
        $check_url = sprintf(
            'https://%s.s3.%s.amazonaws.com/tours/%s/index.htm',
            $this->s3_config['bucket'],
            $this->s3_config['region'],
            $tour_s3_name
        );

        $response = wp_remote_head($check_url, array(
            'timeout' => 10,
            'user_agent' => 'H3TM-Endpoint/1.0'
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Proxy content from S3
     */
    private function proxy_s3_content($s3_url, $file_path, $tour_name) {
        $timeout = $this->get_file_timeout($file_path);

        $response = wp_remote_get($s3_url, array(
            'timeout' => $timeout,
            'headers' => array(
                'User-Agent' => 'H3TM-Endpoint/1.0',
                'Accept' => '*/*'
            )
        ));

        if (is_wp_error($response)) {
            error_log('H3TM Endpoint Error: ' . $response->get_error_message());
            $this->send_error(502, 'Failed to load tour content');
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            error_log('H3TM Endpoint: S3 returned ' . $response_code . ' for ' . $s3_url);

            if ($response_code === 404) {
                $this->send_error(404, 'Tour file not found: ' . htmlspecialchars($file_path));
            } else {
                $this->send_error(502, 'Tour service error (HTTP ' . $response_code . ')');
            }
            return;
        }

        // Get content and set up response
        $content = wp_remote_retrieve_body($response);
        $content_type = $this->get_content_type($file_path);

        // Inject analytics for HTML files
        if ($this->should_inject_analytics($content_type)) {
            $content = $this->inject_analytics($content, $tour_name, $file_path);
        }

        // Set headers and output
        $this->set_response_headers($content_type, $content, $file_path);
        echo $content;
    }

    /**
     * Should analytics be injected?
     */
    private function should_inject_analytics($content_type) {
        return get_option('h3tm_analytics_enabled', false) &&
               strpos($content_type, 'text/html') !== false &&
               !empty(get_option('h3tm_ga_tracking_id', ''));
    }

    /**
     * Inject analytics code
     */
    private function inject_analytics($content, $tour_name, $file_path) {
        $ga_tracking_id = get_option('h3tm_ga_tracking_id', '');

        $analytics_code = sprintf(
            '
<!-- Google Analytics 4 - Injected by H3TM Endpoint Handler -->
<script async src="https://www.googletagmanager.com/gtag/js?id=%s"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag(\'js\', new Date());
  gtag(\'config\', \'%s\', {
    page_title: \'H3 Tour: %s\',
    page_path: \'/h3panos/%s/%s\',
    transport_type: \'xhr\',
    custom_map: {
      \'tour_name\': \'%s\',
      \'file_name\': \'%s\',
      \'delivery_method\': \'endpoint\'
    }
  });

  // Track tour interactions
  gtag(\'event\', \'tour_view\', {
    \'tour_name\': \'%s\',
    \'file_name\': \'%s\',
    \'method\': \'endpoint\'
  });
</script>',
            esc_attr($ga_tracking_id),
            esc_attr($ga_tracking_id),
            esc_js($tour_name),
            esc_js($tour_name),
            esc_js($file_path),
            esc_js($tour_name),
            esc_js($file_path),
            esc_js($tour_name),
            esc_js($file_path)
        );

        // Inject analytics code
        if (stripos($content, '</head>') !== false) {
            $content = str_ireplace('</head>', $analytics_code . "\n</head>", $content);
        } elseif (stripos($content, '</body>') !== false) {
            $content = str_ireplace('</body>', $analytics_code . "\n</body>", $content);
        } else {
            $content .= "\n" . $analytics_code;
        }

        return $content;
    }

    /**
     * Set response headers
     */
    private function set_response_headers($content_type, $content, $file_path) {
        if (headers_sent()) {
            return;
        }

        header_remove();

        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($content));

        // Caching headers
        $this->set_cache_headers($file_path);

        // Security headers
        if (strpos($content_type, 'text/html') !== false) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }

        // CORS headers for API usage
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }

    /**
     * Set cache headers
     */
    private function set_cache_headers($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if (in_array($ext, array('js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2'))) {
            // Long cache for static assets
            header('Cache-Control: public, max-age=604800, immutable');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 604800));
        } elseif (in_array($ext, array('html', 'htm'))) {
            // Shorter cache for HTML with revalidation
            header('Cache-Control: public, max-age=3600, must-revalidate');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
        } else {
            // Default cache
            header('Cache-Control: public, max-age=1800');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 1800));
        }
    }

    /**
     * Get appropriate timeout for file type
     */
    private function get_file_timeout($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        $timeout_map = array(
            'mp4' => 120, 'mov' => 120, 'avi' => 120,
            'zip' => 90, 'pdf' => 60,
            'js' => 30, 'css' => 30, 'html' => 30, 'htm' => 30,
            'png' => 20, 'jpg' => 20, 'jpeg' => 20, 'gif' => 20
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
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2'
        );

        return isset($content_types[$ext]) ? $content_types[$ext] : 'application/octet-stream';
    }

    /**
     * Sanitize tour name for display
     */
    public function sanitize_tour_name($name) {
        $name = rawurldecode($name);
        $name = str_replace(array('%20', '+', '_'), ' ', $name);
        $name = preg_replace('/[^\w\s\-.]/', '', $name);
        return trim($name);
    }

    /**
     * Sanitize tour name for S3 URL
     */
    private function sanitize_for_s3($name) {
        $name = preg_replace('/\s+/', '-', $name);
        return preg_replace('/[^a-zA-Z0-9\-_.]/', '', $name);
    }

    /**
     * Get tour URL for admin display
     */
    public function get_tour_url($tour_name) {
        return site_url('/h3panos/' . rawurlencode($tour_name) . '/');
    }

    /**
     * Get endpoint URL for tour
     */
    public function get_endpoint_url($tour_name) {
        return site_url('/' . $this->endpoint_base . '/' . rawurlencode($tour_name) . '/');
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
        body { font-family: system-ui, sans-serif; margin: 40px; background: #f5f5f5; }
        .error { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc3545; margin-top: 0; }
        .code { font-size: 48px; font-weight: bold; color: #666; margin-bottom: 20px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="error">
        <div class="code">%d</div>
        <h1>%s</h1>
        <p>%s</p>
        <p><a href="/">&larr; Return to homepage</a></p>
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
     * Get handler status
     */
    public function get_status() {
        return array(
            'active' => true,
            'method' => 'WordPress Endpoint',
            'endpoint_base' => $this->endpoint_base,
            'rest_namespace' => 'h3tm/v1',
            's3_configured' => $this->s3_config['configured'],
            'analytics_enabled' => get_option('h3tm_analytics_enabled', false),
            'urls' => array(
                'endpoint' => site_url('/' . $this->endpoint_base . '/'),
                'rest_api' => rest_url('h3tm/v1/tour/'),
                'h3panos_redirect' => site_url('/h3panos/')
            )
        );
    }
}