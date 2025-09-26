<?php
/**
 * Enhanced S3 URL Proxy with improved debugging and fallback approaches
 * Alternative implementation with multiple fix strategies
 */
class H3TM_S3_Proxy_Enhanced {

    private $debug_mode = true;

    public function __construct() {
        // Primary approach: WordPress rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'), 1); // Higher priority
        add_action('template_redirect', array($this, 'handle_tour_requests'), 1);

        // Fallback approach: Early request interception
        add_action('parse_request', array($this, 'parse_request_fallback'), 1);

        // Alternative approach: Direct wp action
        add_action('wp', array($this, 'wp_action_fallback'), 1);

        // Update tour manager to show local URLs
        add_filter('h3tm_tour_url', array($this, 'convert_s3_to_local_url'), 10, 2);

        // Add admin notices for debugging
        if (is_admin()) {
            add_action('admin_notices', array($this, 'admin_debug_notices'));
        }
    }

    /**
     * Enhanced rewrite rules with better debugging
     */
    public function add_rewrite_rules() {
        $this->log('🔧 Adding enhanced rewrite rules...');

        // Register query vars first
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Multiple patterns to handle different cases
        $rules = array(
            // Basic tour access
            '^h3panos/([^/]+)/?$' => 'index.php?h3tm_tour=$matches[1]&h3tm_file=index.htm',

            // Tour with file path
            '^h3panos/([^/]+)/(.+)$' => 'index.php?h3tm_tour=$matches[1]&h3tm_file=$matches[2]',

            // Handle URL-encoded spaces
            '^h3panos/([^/\s]+\s[^/]+)/?$' => 'index.php?h3tm_tour=$matches[1]&h3tm_file=index.htm',
            '^h3panos/([^/\s]+\s[^/]+)/(.+)$' => 'index.php?h3tm_tour=$matches[1]&h3tm_file=$matches[2]',
        );

        foreach ($rules as $pattern => $replacement) {
            add_rewrite_rule($pattern, $replacement, 'top');
            $this->log("   Rule added: $pattern → $replacement");
        }

        $this->log('✅ Enhanced rewrite rules registered');

        // Auto-flush on version change
        add_action('admin_init', array($this, 'maybe_flush_rewrite_rules'));
    }

    /**
     * Enhanced query vars with validation
     */
    public function add_query_vars($vars) {
        $vars[] = 'h3tm_tour';
        $vars[] = 'h3tm_file';
        $vars[] = 'h3tm_debug'; // For debugging
        $this->log('✅ Query vars registered: h3tm_tour, h3tm_file, h3tm_debug');
        return $vars;
    }

    /**
     * Enhanced flush with better version tracking
     */
    public function maybe_flush_rewrite_rules() {
        $flush_key = 'h3tm_enhanced_rewrite_rules_flushed';
        $current_version = H3TM_VERSION . '_enhanced';

        if (get_option($flush_key) !== $current_version) {
            flush_rewrite_rules();
            update_option($flush_key, $current_version);
            $this->log("🔄 Flushed rewrite rules for version $current_version");

            // Show admin notice
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p><strong>H3TM:</strong> Rewrite rules refreshed successfully!</p></div>';
            });
        }
    }

    /**
     * Enhanced tour request handling with better debugging
     */
    public function handle_tour_requests() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Always log h3panos requests for debugging
        if (strpos($request_uri, 'h3panos') !== false) {
            $this->log("🌐 Template redirect called for: $request_uri");
        }

        $tour_name = get_query_var('h3tm_tour');
        $file_path = get_query_var('h3tm_file');
        $debug = get_query_var('h3tm_debug');

        // Debug mode - show what WordPress sees
        if ($debug === '1') {
            $this->show_debug_info($request_uri, $tour_name, $file_path);
            exit;
        }

        $this->log("📊 Query vars: tour='{$tour_name}', file='{$file_path}'");

        if (empty($tour_name)) {
            if (strpos($request_uri, 'h3panos') !== false) {
                $this->log("❌ h3panos URL detected but no tour_name - rewrite rules may not be working");

                // Try fallback parsing
                if (preg_match('#/h3panos/([^/]+)(?:/(.+))?#', $request_uri, $matches)) {
                    $tour_name = urldecode($matches[1]);
                    $file_path = isset($matches[2]) ? urldecode($matches[2]) : 'index.htm';
                    $this->log("🔄 Fallback parsing successful: tour='{$tour_name}', file='{$file_path}'");
                } else {
                    return;
                }
            } else {
                return; // Not a tour request
            }
        }

        // Process the tour request
        $this->serve_tour_file($tour_name, $file_path);
    }

    /**
     * Fallback #1: Parse request hook
     */
    public function parse_request_fallback($wp) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        if (strpos($request_uri, '/h3panos/') === false) {
            return;
        }

        $this->log("🔄 Parse request fallback triggered for: $request_uri");

        // Check if rewrite rules already handled this
        if (!empty(get_query_var('h3tm_tour'))) {
            $this->log("   → Rewrite rules working, skipping fallback");
            return;
        }

        // Manual URL parsing
        if (preg_match('#/h3panos/([^/]+)(?:/(.+))?#', $request_uri, $matches)) {
            $wp->query_vars['h3tm_tour'] = urldecode($matches[1]);
            $wp->query_vars['h3tm_file'] = isset($matches[2]) ? urldecode($matches[2]) : 'index.htm';

            $this->log("   ✅ Fallback parsing: tour='{$wp->query_vars['h3tm_tour']}', file='{$wp->query_vars['h3tm_file']}'");
        }
    }

    /**
     * Fallback #2: WP action hook
     */
    public function wp_action_fallback() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        if (strpos($request_uri, '/h3panos/') === false) {
            return;
        }

        $tour_name = get_query_var('h3tm_tour');

        if (!empty($tour_name)) {
            return; // Already handled
        }

        $this->log("🔄 WP action fallback triggered for: $request_uri");

        // Direct URL processing
        if (preg_match('#/h3panos/([^/]+)(?:/(.+))?#', $request_uri, $matches)) {
            $tour_name = urldecode($matches[1]);
            $file_path = isset($matches[2]) ? urldecode($matches[2]) : 'index.htm';

            $this->log("   ✅ WP action fallback processing: tour='{$tour_name}', file='{$file_path}'");
            $this->serve_tour_file($tour_name, $file_path);
        }
    }

    /**
     * Serve tour file from S3 with enhanced error handling
     */
    private function serve_tour_file($tour_name, $file_path = 'index.htm') {
        $this->log("🚀 Serving tour file: tour='{$tour_name}', file='{$file_path}'");

        // Validate inputs
        if (empty($tour_name)) {
            $this->log("❌ Invalid tour name");
            wp_die('Invalid tour name', 'Tour Error', array('response' => 400));
        }

        // Default file
        if (empty($file_path)) {
            $file_path = 'index.htm';
        }

        // Get S3 configuration
        $s3_simple = new H3TM_S3_Simple();
        $s3_config = $s3_simple->get_s3_config();

        if (!$s3_config['configured']) {
            $this->log("❌ S3 not configured");
            wp_die('S3 configuration not found', 'Configuration Error', array('response' => 503));
        }

        // Build S3 URL
        $tour_s3_name = sanitize_file_name($tour_name);
        $s3_url = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/tours/' . $tour_s3_name . '/' . $file_path;

        $this->log("📡 Proxying from S3: $s3_url");

        // Proxy the file
        $this->proxy_s3_file($s3_url, $file_path);
        exit();
    }

    /**
     * Enhanced S3 file proxy with better error handling
     */
    private function proxy_s3_file($s3_url, $file_path) {
        // Try to get file from S3
        $response = wp_remote_get($s3_url, array(
            'timeout' => 30,
            'user-agent' => 'H3TM S3 Proxy/1.0'
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log("❌ S3 request failed: $error_message");

            status_header(404);
            wp_die(
                "Tour file not available: $error_message",
                'File Not Found',
                array('response' => 404)
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log("❌ S3 returned HTTP $response_code for: $s3_url");

            status_header($response_code);
            wp_die(
                "Tour file not available (HTTP $response_code)",
                'File Not Found',
                array('response' => $response_code)
            );
        }

        // Get content and headers
        $content = wp_remote_retrieve_body($response);
        $content_type = $this->get_content_type($file_path);
        $content_length = strlen($content);

        $this->log("✅ Serving file: $content_length bytes, type: $content_type");

        // Set headers
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . $content_length);
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
        header('X-Served-By: H3TM-S3-Proxy');

        // Output content
        echo $content;
    }

    /**
     * Show debug information
     */
    private function show_debug_info($request_uri, $tour_name, $file_path) {
        header('Content-Type: text/html');

        echo "<h1>🐛 H3TM S3 Proxy Debug Info</h1>";
        echo "<strong>Request URI:</strong> $request_uri<br>";
        echo "<strong>Tour Name:</strong> " . ($tour_name ?: '<em>empty</em>') . "<br>";
        echo "<strong>File Path:</strong> " . ($file_path ?: '<em>empty</em>') . "<br>";
        echo "<strong>Time:</strong> " . date('Y-m-d H:i:s') . "<br><br>";

        echo "<h2>WordPress Query Variables:</h2>";
        echo "<pre>";
        global $wp_query;
        print_r($wp_query->query_vars);
        echo "</pre>";

        echo "<h2>Rewrite Rules (h3panos):</h2>";
        $rewrite_rules = get_option('rewrite_rules');
        echo "<pre>";
        foreach ($rewrite_rules as $pattern => $replacement) {
            if (strpos($pattern, 'h3panos') !== false) {
                echo "$pattern → $replacement\n";
            }
        }
        echo "</pre>";

        echo "<h2>Server Info:</h2>";
        echo "<pre>";
        echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
        echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'not set') . "\n";
        echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
        echo "</pre>";
    }

    /**
     * Admin debug notices
     */
    public function admin_debug_notices() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_h3tm-admin') {
            $rewrite_rules = get_option('rewrite_rules');
            $has_h3panos_rules = false;

            if (is_array($rewrite_rules)) {
                foreach ($rewrite_rules as $pattern => $replacement) {
                    if (strpos($pattern, 'h3panos') !== false) {
                        $has_h3panos_rules = true;
                        break;
                    }
                }
            }

            if (!$has_h3panos_rules) {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>H3TM Warning:</strong> No h3panos rewrite rules found. ';
                echo '<a href="' . admin_url('options-permalink.php') . '">Flush rewrite rules</a> ';
                echo 'or <a href="?h3tm_debug=1" target="_blank">debug the issue</a>.';
                echo '</p></div>';
            }
        }
    }

    /**
     * Convert S3 URL to local URL
     */
    public function convert_s3_to_local_url($url, $tour_name) {
        return site_url('/h3panos/' . rawurlencode($tour_name) . '/');
    }

    /**
     * Get content type for file extension
     */
    private function get_content_type($file_path) {
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        $content_types = array(
            'html' => 'text/html; charset=UTF-8',
            'htm' => 'text/html; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'json' => 'application/json',
            'txt' => 'text/plain; charset=UTF-8',
            'xml' => 'application/xml; charset=UTF-8',
            'pdf' => 'application/pdf'
        );

        return isset($content_types[$ext]) ? $content_types[$ext] : 'application/octet-stream';
    }

    /**
     * Enhanced logging
     */
    private function log($message) {
        if (!$this->debug_mode) {
            return;
        }

        $log_message = '[' . date('Y-m-d H:i:s') . '] H3TM S3 Proxy Enhanced: ' . $message;
        error_log($log_message);

        // Also log to custom file if possible
        $log_file = WP_CONTENT_DIR . '/uploads/h3tm-s3-proxy.log';
        @file_put_contents($log_file, $log_message . "\n", FILE_APPEND | LOCK_EX);
    }
}