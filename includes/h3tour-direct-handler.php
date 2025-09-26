<?php
/**
 * Direct PHP Handler for h3panos Tours
 *
 * This file can be accessed directly when WordPress rewrite rules fail.
 * Usage: /wp-content/plugins/h3-tour-management/includes/h3tour-direct-handler.php?tour=TourName&file=index.htm
 */

// Security check
if (!defined('ABSPATH')) {
    // Load WordPress if not already loaded
    $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        // Fallback for different WordPress structures
        $possible_paths = array(
            '../../../wp-load.php',
            '../../../../wp-load.php',
            '../../../wp-config.php',
            '../../../../wp-config.php'
        );

        $wp_loaded = false;
        foreach ($possible_paths as $path) {
            if (file_exists(__DIR__ . '/' . $path)) {
                require_once __DIR__ . '/' . $path;
                $wp_loaded = true;
                break;
            }
        }

        if (!$wp_loaded) {
            // Standalone mode - load basic configuration
            $config_file = __DIR__ . '/../config/standalone-config.php';
            if (file_exists($config_file)) {
                require_once $config_file;
            }
        }
    }
}

/**
 * Standalone Tour Handler (works without full WordPress)
 */
class H3TM_Direct_Handler {

    private $s3_config;
    private $debug_mode = false;

    public function __construct() {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $this->init();
    }

    private function init() {
        // Load S3 configuration
        $this->s3_config = $this->get_s3_config();

        // Handle the request
        $this->handle_request();
    }

    private function handle_request() {
        // Get parameters
        $tour_name = $this->get_parameter('tour');
        $file_path = $this->get_parameter('file', 'index.htm');

        // Validate inputs
        if (empty($tour_name)) {
            $this->send_error('Missing tour parameter', 400);
            return;
        }

        // Sanitize inputs
        $tour_name = $this->sanitize_tour_name($tour_name);
        $file_path = $this->sanitize_file_path($file_path);

        if (!$tour_name || !$file_path) {
            $this->send_error('Invalid tour or file name', 400);
            return;
        }

        $this->log('Direct handler: ' . $tour_name . '/' . $file_path);

        // Check S3 configuration
        if (!$this->s3_config['configured']) {
            $this->send_error('S3 not configured', 500);
            return;
        }

        // Serve the tour content
        $this->serve_tour_content($tour_name, $file_path);
    }

    private function serve_tour_content($tour_name, $file_path) {
        // Build S3 URL
        $s3_url = sprintf(
            'https://%s.s3.%s.amazonaws.com/tours/%s/%s',
            $this->s3_config['bucket'],
            $this->s3_config['region'],
            rawurlencode($tour_name),
            $file_path
        );

        $this->log('Fetching from S3: ' . $s3_url);

        // Fetch content from S3
        $content = $this->fetch_s3_content($s3_url);

        if ($content === false) {
            $this->send_error('Tour file not found: ' . $tour_name . '/' . $file_path, 404);
            return;
        }

        // Serve the content
        $content_type = $this->get_content_type($file_path);
        $this->serve_content($content, $content_type);
    }

    private function fetch_s3_content($s3_url) {
        // Try WordPress HTTP API first if available
        if (function_exists('wp_remote_get')) {
            $response = wp_remote_get($s3_url, array('timeout' => 30));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                return wp_remote_retrieve_body($response);
            }
        }

        // Fallback to cURL
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $s3_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'H3TM Direct Handler/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 && $content !== false) {
                return $content;
            }
        }

        // Fallback to file_get_contents
        if (function_exists('file_get_contents') && ini_get('allow_url_fopen')) {
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 30,
                    'user_agent' => 'H3TM Direct Handler/1.0'
                )
            ));

            $content = @file_get_contents($s3_url, false, $context);
            if ($content !== false) {
                return $content;
            }
        }

        return false;
    }

    private function serve_content($content, $content_type) {
        // Clear any existing output
        if (ob_get_level()) {
            ob_clean();
        }

        // Set headers
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
        header('X-Content-Type-Options: nosniff');

        // CORS headers for cross-domain requests
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $allowed_origins = array(
                parse_url(get_site_url(), PHP_URL_HOST),
                'localhost',
                '127.0.0.1'
            );

            $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
            if (in_array($origin, $allowed_origins)) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            }
        }

        echo $content;
        exit();
    }

    private function send_error($message, $code = 500) {
        http_response_code($code);
        header('Content-Type: text/html');

        echo '<!DOCTYPE html><html><head><title>Error ' . $code . '</title></head><body>';
        echo '<h1>Error ' . $code . '</h1>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><a href="' . (function_exists('site_url') ? site_url() : '/') . '">Return Home</a></p>';
        echo '</body></html>';

        exit();
    }

    private function get_parameter($name, $default = null) {
        if (isset($_GET[$name])) {
            return $_GET[$name];
        }

        if (isset($_POST[$name])) {
            return $_POST[$name];
        }

        return $default;
    }

    private function get_s3_config() {
        // Try to get config from WordPress options first
        if (function_exists('get_option')) {
            $bucket = get_option('h3tm_s3_bucket', '');
            $region = get_option('h3tm_s3_region', 'us-east-1');

            if (!empty($bucket)) {
                return array(
                    'bucket' => $bucket,
                    'region' => $region,
                    'configured' => true
                );
            }
        }

        // Try constants
        if (defined('H3_S3_BUCKET') && defined('H3_S3_REGION')) {
            return array(
                'bucket' => H3_S3_BUCKET,
                'region' => H3_S3_REGION,
                'configured' => true
            );
        }

        // Try environment variables
        $bucket = getenv('H3_S3_BUCKET') ?: $_SERVER['H3_S3_BUCKET'] ?? '';
        $region = getenv('H3_S3_REGION') ?: $_SERVER['H3_S3_REGION'] ?? 'us-east-1';

        if (!empty($bucket)) {
            return array(
                'bucket' => $bucket,
                'region' => $region,
                'configured' => true
            );
        }

        // Load from config file
        $config_file = __DIR__ . '/../config/s3-config.php';
        if (file_exists($config_file)) {
            $config = include $config_file;
            if (is_array($config) && !empty($config['bucket'])) {
                return array_merge($config, array('configured' => true));
            }
        }

        return array(
            'bucket' => '',
            'region' => 'us-east-1',
            'configured' => false
        );
    }

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

    private function sanitize_tour_name($tour_name) {
        // Allow letters, numbers, hyphens, underscores, spaces, and some special chars
        $tour_name = preg_replace('/[^a-zA-Z0-9\-_\s\+\(\)]/', '', $tour_name);
        return !empty($tour_name) ? $tour_name : false;
    }

    private function sanitize_file_path($file_path) {
        // Prevent directory traversal
        $file_path = str_replace(array('..', '\\'), '', $file_path);

        // Allow standard file characters
        $file_path = preg_replace('/[^a-zA-Z0-9\-_\.\/]/', '', $file_path);

        return !empty($file_path) ? $file_path : false;
    }

    private function log($message) {
        if ($this->debug_mode && function_exists('error_log')) {
            error_log('H3TM Direct Handler: ' . $message);
        }
    }
}

// Initialize the handler
new H3TM_Direct_Handler();