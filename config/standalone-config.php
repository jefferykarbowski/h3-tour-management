<?php
/**
 * Standalone Configuration for H3TM Tour Handler
 *
 * This file provides configuration when WordPress is not fully loaded
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('H3TM_STANDALONE')) {
    define('H3TM_STANDALONE', true);
}

// S3 Configuration
// These can be overridden by environment variables or wp-config.php constants
if (!defined('H3_S3_BUCKET')) {
    define('H3_S3_BUCKET', getenv('H3_S3_BUCKET') ?: '');
}

if (!defined('H3_S3_REGION')) {
    define('H3_S3_REGION', getenv('H3_S3_REGION') ?: 'us-east-1');
}

// Debug mode
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', getenv('WP_DEBUG') === 'true' || getenv('WP_DEBUG') === '1');
}

// Site URL for fallback
if (!function_exists('site_url')) {
    function site_url($path = '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $protocol . '://' . $host;

        // Try to detect WordPress directory
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        if (strpos($script_dir, '/wp-content') !== false) {
            $wp_dir = substr($script_dir, 0, strpos($script_dir, '/wp-content'));
            $base .= $wp_dir;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

// Basic option functions for standalone mode
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Simple file-based option storage for standalone mode
        $options_file = __DIR__ . '/options.json';

        if (file_exists($options_file)) {
            $options = json_decode(file_get_contents($options_file), true);
            return $options[$option] ?? $default;
        }

        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        $options_file = __DIR__ . '/options.json';

        $options = array();
        if (file_exists($options_file)) {
            $options = json_decode(file_get_contents($options_file), true) ?: array();
        }

        $options[$option] = $value;

        return file_put_contents($options_file, json_encode($options, JSON_PRETTY_PRINT)) !== false;
    }
}

// Basic transient functions
if (!function_exists('get_transient')) {
    function get_transient($transient) {
        $transients_file = __DIR__ . '/transients.json';

        if (file_exists($transients_file)) {
            $transients = json_decode(file_get_contents($transients_file), true);

            if (isset($transients[$transient])) {
                $data = $transients[$transient];

                // Check if expired
                if ($data['expires'] > time()) {
                    return $data['value'];
                } else {
                    // Clean up expired transient
                    unset($transients[$transient]);
                    file_put_contents($transients_file, json_encode($transients, JSON_PRETTY_PRINT));
                }
            }
        }

        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        $transients_file = __DIR__ . '/transients.json';

        $transients = array();
        if (file_exists($transients_file)) {
            $transients = json_decode(file_get_contents($transients_file), true) ?: array();
        }

        $transients[$transient] = array(
            'value' => $value,
            'expires' => time() + $expiration
        );

        return file_put_contents($transients_file, json_encode($transients, JSON_PRETTY_PRINT)) !== false;
    }
}

// Error logging function
if (!function_exists('error_log')) {
    function error_log($message, $message_type = 0, $destination = null, $extra_headers = null) {
        $log_file = __DIR__ . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message\n";

        return file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX) !== false;
    }
}

// Basic security functions
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(preg_replace('/[^\x20-\x7E]/', '', $str));
    }
}

// Load S3 configuration from file if available
$s3_config_file = __DIR__ . '/s3-config.php';
if (file_exists($s3_config_file)) {
    include_once $s3_config_file;
}