<?php
/**
 * S3 URL Proxy - Makes S3 tours appear as local /h3panos/ URLs
 */
class H3TM_S3_Proxy {

    public function __construct() {
        // Add rewrite rules for tour URLs
        add_action('init', array($this, 'add_rewrite_rules'));
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

        // Debug: Log that rewrite rules are being added
        error_log('H3TM S3 Proxy: Adding rewrite rules...');

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

        error_log('H3TM S3 Proxy: Rewrite rules added');

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

        error_log('H3TM S3 Proxy: Query vars - tour_name=' . $tour_name . ', file_path=' . $file_path);

        if (empty($tour_name)) {
            if (strpos($request_uri, 'h3panos') !== false) {
                error_log('H3TM S3 Proxy: h3panos URL detected but no tour_name query var');
            }
            return; // Not a tour request
        }

        error_log('H3TM S3 Proxy: Processing tour request for=' . $tour_name . ', file=' . $file_path);

        // Default to index.htm if no file specified
        if (empty($file_path)) {
            $file_path = 'index.htm';
        }

        // Get S3 configuration
        $s3_simple = new H3TM_S3_Simple();
        $s3_config = $s3_simple->get_s3_config();

        if (!$s3_config['configured']) {
            wp_die('S3 not configured for tour delivery');
        }

        // Build S3 URL for the tour file
        // Convert tour name (with spaces) to S3 folder name (with dashes)
        $tour_s3_folder = str_replace(' ', '-', $tour_name);
        $s3_url = 'https://' . $s3_config['bucket'] . '.s3.' . $s3_config['region'] . '.amazonaws.com/tours/' . $tour_s3_folder . '/' . $file_path;

        error_log('H3TM S3 Proxy: Tour "' . $tour_name . '" → S3 folder "' . $tour_s3_folder . '"');

        error_log('H3TM S3 Proxy: Serving from S3: ' . $s3_url);

        // Proxy the file from S3
        $this->proxy_s3_file($s3_url, $file_path);
        exit();
    }

    /**
     * Proxy file content from S3
     */
    private function proxy_s3_file($s3_url, $file_path) {
        // Get file from S3
        $response = wp_remote_get($s3_url, array(
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log('H3TM S3 Proxy Error: ' . $response->get_error_message());
            status_header(404);
            wp_die('Tour file not found');
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('H3TM S3 Proxy: S3 returned ' . $response_code . ' for ' . $s3_url);
            status_header(404);
            wp_die('Tour file not found');
        }

        // Get content and content type
        $content = wp_remote_retrieve_body($response);
        $content_type = $this->get_content_type($file_path);

        // Set appropriate headers
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($content));

        // Cache headers for better performance
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));

        // Output the content
        echo $content;
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