<?php
/**
 * Robust Tour URL Handler - Multiple Fallback Methods for h3panos URLs
 * 
 * This system ensures reliable serving of S3 tour content through local /h3panos/ URLs
 * regardless of WordPress rewrite rule issues or hosting environment limitations.
 * 
 * Priority Methods:
 * 1. WordPress Hooks (wp, parse_request, template_redirect)
 * 2. Direct URL Interception (init hook with early detection)
 * 3. Query String Fallback (?h3tour=tourname)
 * 4. REST API Endpoint (/wp-json/h3tm/v1/tour/)
 * 5. PHP Handler Fallback (h3tour-handler.php)
 */
class H3TM_Tour_URL_Handler {

    private $s3_config;
    private $cache = array();
    private $debug_mode;
    
    public function __construct() {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $this->init_multiple_handlers();
    }

    /**
     * Initialize all fallback methods
     */
    private function init_multiple_handlers() {
        // Method 1: Early WordPress hooks (highest priority)
        add_action('wp', array($this, 'wp_hook_handler'), 1);
        add_action('parse_request', array($this, 'parse_request_handler'), 1);
        add_action('template_redirect', array($this, 'template_redirect_handler'), 1);
        
        // Method 2: URL pattern detection at init
        add_action('init', array($this, 'early_url_detection'), 1);
        
        // Method 3: Query string fallback
        add_action('init', array($this, 'register_query_vars'));
        add_action('wp', array($this, 'query_string_handler'));
        
        // Method 4: REST API endpoint
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        
        // Method 5: Rewrite rules (as backup)
        add_action('init', array($this, 'add_rewrite_rules'), 10);
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Performance optimizations
        add_action('init', array($this, 'init_caching'));
        
        // Debug helper
        if ($this->debug_mode) {
            add_action('wp_footer', array($this, 'debug_output'));
        }
    }

    /**
     * Method 1: wp hook handler (most reliable)
     */
    public function wp_hook_handler() {
        $request_uri = $this->get_request_uri();
        
        if ($this->is_tour_request($request_uri)) {
            $this->log('wp_hook_handler: Processing ' . $request_uri);
            $this->handle_tour_request($request_uri);
        }
    }

    /**
     * Method 2: parse_request handler
     */
    public function parse_request_handler($wp_request) {
        $request_uri = $this->get_request_uri();
        
        if ($this->is_tour_request($request_uri)) {
            $this->log('parse_request_handler: Processing ' . $request_uri);
            $this->handle_tour_request($request_uri);
        }
    }

    /**
     * Method 3: template_redirect handler
     */
    public function template_redirect_handler() {
        $request_uri = $this->get_request_uri();
        
        if ($this->is_tour_request($request_uri)) {
            $this->log('template_redirect_handler: Processing ' . $request_uri);
            $this->handle_tour_request($request_uri);
        }
    }

    /**
     * Method 4: Early URL detection at init
     */
    public function early_url_detection() {
        $request_uri = $this->get_request_uri();
        
        if ($this->is_tour_request($request_uri)) {
            $this->log('early_url_detection: Processing ' . $request_uri);
            // Set flag for other handlers to know this was processed
            define('H3TM_TOUR_REQUEST_DETECTED', true);
            $this->handle_tour_request($request_uri);
        }
    }

    /**
     * Method 5: Query string fallback handler
     */
    public function query_string_handler() {
        $tour_name = get_query_var('h3tour');
        $file_path = get_query_var('h3file', 'index.htm');
        
        if (!empty($tour_name)) {
            $this->log('query_string_handler: Processing tour=' . $tour_name . ', file=' . $file_path);
            $this->serve_tour_content($tour_name, $file_path);
        }
    }

    /**
     * Register query vars for fallback method
     */
    public function register_query_vars() {
        global $wp;
        $wp->add_query_var('h3tour');
        $wp->add_query_var('h3file');
    }

    /**
     * Method 6: REST API endpoints
     */
    public function register_rest_endpoints() {
        register_rest_route('h3tm/v1', '/tour/(?P<tour>[a-zA-Z0-9\-_]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_tour_handler'),
            'permission_callback' => '__return_true',
            'args' => array(
                'tour' => array(
                    'validate_callback' => function($param) {
                        return is_string($param) && preg_match('/^[a-zA-Z0-9\-_]+$/', $param);
                    }
                )
            )
        ));
        
        register_rest_route('h3tm/v1', '/tour/(?P<tour>[a-zA-Z0-9\-_]+)/(?P<file>.+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_tour_file_handler'),
            'permission_callback' => '__return_true',
            'args' => array(
                'tour' => array(
                    'validate_callback' => function($param) {
                        return is_string($param) && preg_match('/^[a-zA-Z0-9\-_]+$/', $param);
                    }
                ),
                'file' => array(
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen($param) > 0;
                    }
                )
            )
        ));
    }

    /**
     * REST API tour handler
     */
    public function rest_tour_handler($request) {
        $tour_name = $request->get_param('tour');
        $this->log('rest_tour_handler: Processing ' . $tour_name);
        
        ob_start();
        $this->serve_tour_content($tour_name, 'index.htm');
        $content = ob_get_clean();
        
        return new WP_REST_Response($content, 200, array(
            'Content-Type' => 'text/html'
        ));
    }

    /**
     * REST API tour file handler
     */
    public function rest_tour_file_handler($request) {
        $tour_name = $request->get_param('tour');
        $file_path = $request->get_param('file');
        
        $this->log('rest_tour_file_handler: Processing ' . $tour_name . '/' . $file_path);
        
        ob_start();
        $this->serve_tour_content($tour_name, $file_path);
        $content = ob_get_clean();
        
        $content_type = $this->get_content_type($file_path);
        
        return new WP_REST_Response($content, 200, array(
            'Content-Type' => $content_type
        ));
    }

    /**
     * Legacy rewrite rules (Method 7 - backup)
     */
    public function add_rewrite_rules() {
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
        
        // Flush rules if needed
        if (get_option('h3tm_url_handler_version') !== H3TM_VERSION) {
            flush_rewrite_rules();
            update_option('h3tm_url_handler_version', H3TM_VERSION);
        }
    }

    /**
     * Add query vars for rewrite rules
     */
    public function add_query_vars($vars) {
        $vars[] = 'h3tm_tour';
        $vars[] = 'h3tm_file';
        return $vars;
    }

    /**
     * Initialize caching system
     */
    public function init_caching() {
        // Load S3 config once and cache it
        $this->s3_config = $this->get_s3_config();
    }

    /**
     * Check if current request is for a tour
     */
    private function is_tour_request($request_uri) {
        // Skip if already processed
        if (defined('H3TM_TOUR_REQUEST_PROCESSED')) {
            return false;
        }
        
        // Multiple patterns to catch tour requests
        $patterns = array(
            '/^\/h3panos\/([^\/\?]+)\/?/',
            '/^\/h3panos\/([^\/\?]+)\/(.+)/',
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $request_uri)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get clean request URI
     */
    private function get_request_uri() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Remove query string
        if (($pos = strpos($request_uri, '?')) !== false) {
            $request_uri = substr($request_uri, 0, $pos);
        }
        
        return $request_uri;
    }

    /**
     * Main tour request handler
     */
    private function handle_tour_request($request_uri) {
        // Prevent double processing
        if (defined('H3TM_TOUR_REQUEST_PROCESSED')) {
            return;
        }
        
        define('H3TM_TOUR_REQUEST_PROCESSED', true);
        
        // Parse tour name and file from URL
        $tour_data = $this->parse_tour_url($request_uri);
        
        if ($tour_data) {
            $this->serve_tour_content($tour_data['tour'], $tour_data['file']);
        } else {
            // Try fallback from rewrite rules
            $tour_name = get_query_var('h3tm_tour');
            $file_path = get_query_var('h3tm_file', 'index.htm');
            
            if (!empty($tour_name)) {
                $this->serve_tour_content($tour_name, $file_path);
            }
        }
    }

    /**
     * Parse tour URL to extract tour name and file
     */
    private function parse_tour_url($request_uri) {
        // Pattern 1: /h3panos/TourName/
        if (preg_match('/^\/h3panos\/([^\/\?]+)\/?$/', $request_uri, $matches)) {
            return array(
                'tour' => $matches[1],
                'file' => 'index.htm'
            );
        }
        
        // Pattern 2: /h3panos/TourName/file.ext
        if (preg_match('/^\/h3panos\/([^\/\?]+)\/(.+)$/', $request_uri, $matches)) {
            return array(
                'tour' => $matches[1],
                'file' => $matches[2]
            );
        }
        
        return false;
    }

    /**
     * Serve tour content from S3
     */
    private function serve_tour_content($tour_name, $file_path = 'index.htm') {
        // Validate inputs
        $tour_name = $this->sanitize_tour_name($tour_name);
        $file_path = $this->sanitize_file_path($file_path);
        
        if (!$tour_name || !$file_path) {
            $this->send_404('Invalid tour or file name');
            return;
        }
        
        $this->log('serve_tour_content: ' . $tour_name . '/' . $file_path);
        
        // Check S3 configuration
        if (!$this->s3_config['configured']) {
            $this->send_error('S3 not configured', 500);
            return;
        }
        
        // Try cache first
        $cache_key = 'tour_' . $tour_name . '_' . md5($file_path);
        $cached_content = $this->get_cached_content($cache_key);
        
        if ($cached_content) {
            $this->serve_content($cached_content['content'], $cached_content['content_type']);
            return;
        }
        
        // Fetch from S3
        $s3_url = $this->build_s3_url($tour_name, $file_path);
        $content = $this->fetch_s3_content($s3_url);
        
        if ($content) {
            $content_type = $this->get_content_type($file_path);
            
            // Cache successful responses
            $this->cache_content($cache_key, $content, $content_type);
            
            $this->serve_content($content, $content_type);
        } else {
            $this->send_404('Tour file not found: ' . $tour_name . '/' . $file_path);
        }
    }

    /**
     * Build S3 URL for tour file
     */
    private function build_s3_url($tour_name, $file_path) {
        $bucket = $this->s3_config['bucket'];
        $region = $this->s3_config['region'];

        // Convert spaces to dashes for S3 path
        // AWS/Lambda converts "Bee Cave" to "Bee-Cave" when uploading
        $s3_tour_folder = str_replace(' ', '-', $tour_name);

        return sprintf(
            'https://%s.s3.%s.amazonaws.com/tours/%s/%s',
            $bucket,
            $region,
            rawurlencode($s3_tour_folder),
            $file_path
        );
    }

    /**
     * Fetch content from S3
     */
    private function fetch_s3_content($s3_url) {
        $this->log('fetch_s3_content: ' . $s3_url);
        
        $response = wp_remote_get($s3_url, array(
            'timeout' => 30,
            'user-agent' => 'H3TM Tour Handler/1.0'
        ));
        
        if (is_wp_error($response)) {
            $this->log('S3 Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log('S3 returned ' . $response_code . ' for ' . $s3_url);
            return false;
        }
        
        return wp_remote_retrieve_body($response);
    }

    /**
     * Serve content with appropriate headers
     */
    private function serve_content($content, $content_type) {
        // Clear any existing output
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Set headers
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        
        // Output content
        echo $content;
        exit();
    }

    /**
     * Send 404 error
     */
    private function send_404($message = 'Not Found') {
        status_header(404);
        header('Content-Type: text/html');
        
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body>';
        echo '<h1>404 Not Found</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<p>The requested tour could not be found.</p>';
        echo '</body></html>';
        
        exit();
    }

    /**
     * Send error response
     */
    private function send_error($message, $code = 500) {
        status_header($code);
        header('Content-Type: text/html');
        
        echo '<!DOCTYPE html><html><head><title>Error ' . $code . '</title></head><body>';
        echo '<h1>Error ' . $code . '</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</body></html>';
        
        exit();
    }

    /**
     * Get S3 configuration
     */
    private function get_s3_config() {
        if (class_exists('H3TM_S3_Simple')) {
            $s3_simple = new H3TM_S3_Simple();
            return $s3_simple->get_s3_config();
        }
        
        // Fallback manual configuration
        $bucket = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : get_option('h3tm_s3_bucket', '');
        $region = defined('H3_S3_REGION') ? H3_S3_REGION : get_option('h3tm_s3_region', 'us-east-1');
        
        return array(
            'bucket' => $bucket,
            'region' => $region,
            'configured' => !empty($bucket)
        );
    }

    /**
     * Get content type for file extension
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
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'ico' => 'image/x-icon'
        );
        
        return $content_types[$ext] ?? 'application/octet-stream';
    }

    /**
     * Sanitize tour name
     */
    private function sanitize_tour_name($tour_name) {
        // Allow letters, numbers, hyphens, underscores, spaces, and some special chars
        $tour_name = preg_replace('/[^a-zA-Z0-9\-_\s\+\(\)]/', '', $tour_name);
        return !empty($tour_name) ? $tour_name : false;
    }

    /**
     * Sanitize file path
     */
    private function sanitize_file_path($file_path) {
        // Prevent directory traversal
        $file_path = str_replace(array('..', '\\'), '', $file_path);
        
        // Allow standard file characters
        $file_path = preg_replace('/[^a-zA-Z0-9\-_\.\/]/', '', $file_path);
        
        return !empty($file_path) ? $file_path : false;
    }

    /**
     * Cache content
     */
    private function cache_content($cache_key, $content, $content_type) {
        $cache_data = array(
            'content' => $content,
            'content_type' => $content_type,
            'timestamp' => time()
        );
        
        set_transient('h3tm_' . $cache_key, $cache_data, 3600); // 1 hour cache
    }

    /**
     * Get cached content
     */
    private function get_cached_content($cache_key) {
        return get_transient('h3tm_' . $cache_key);
    }

    /**
     * Log debug messages
     */
    private function log($message) {
        if ($this->debug_mode) {
            error_log('H3TM Tour URL Handler: ' . $message);
        }
    }

    /**
     * Debug output for troubleshooting
     */
    public function debug_output() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $request_uri = $this->get_request_uri();
        
        echo '<!-- H3TM Tour URL Handler Debug -->';
        echo '<!-- Request URI: ' . esc_html($request_uri) . ' -->';
        echo '<!-- Is Tour Request: ' . ($this->is_tour_request($request_uri) ? 'Yes' : 'No') . ' -->';
        echo '<!-- S3 Configured: ' . ($this->s3_config['configured'] ? 'Yes' : 'No') . ' -->';
        
        if (defined('H3TM_TOUR_REQUEST_DETECTED')) {
            echo '<!-- Tour Request Detected: Yes -->';
        }
        
        if (defined('H3TM_TOUR_REQUEST_PROCESSED')) {
            echo '<!-- Tour Request Processed: Yes -->';
        }
    }

    /**
     * Get tour URL for display (filter hook)
     */
    public function get_tour_url($tour_name) {
        return site_url('/h3panos/' . rawurlencode($tour_name) . '/');
    }

    /**
     * Static method to test if tour URL handler is working
     */
    public static function test_handler() {
        $test_results = array(
            'hooks_registered' => false,
            'rewrite_rules' => false,
            'query_vars' => false,
            's3_configured' => false,
            'test_url' => site_url('/h3panos/test-tour/'),
            'fallback_url' => site_url('/?h3tour=test-tour')
        );
        
        // Check if hooks are registered
        global $wp_filter;
        $test_results['hooks_registered'] = 
            isset($wp_filter['wp']) ||
            isset($wp_filter['parse_request']) ||
            isset($wp_filter['template_redirect']);
        
        // Check rewrite rules
        global $wp_rewrite;
        $rules = $wp_rewrite->wp_rewrite_rules();
        $test_results['rewrite_rules'] = isset($rules['^h3panos/([^/]+)/?$']);
        
        // Check query vars
        global $wp;
        $test_results['query_vars'] = 
            in_array('h3tm_tour', $wp->public_query_vars) ||
            in_array('h3tour', $wp->public_query_vars);
        
        // Check S3 configuration
        if (class_exists('H3TM_S3_Simple')) {
            $s3_simple = new H3TM_S3_Simple();
            $s3_config = $s3_simple->get_s3_config();
            $test_results['s3_configured'] = $s3_config['configured'];
        }
        
        return $test_results;
    }
}
