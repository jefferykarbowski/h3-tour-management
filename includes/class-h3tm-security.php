<?php
/**
 * Security handler for H3 Tour Management
 * 
 * Handles file validation, rate limiting, and security checks
 * 
 * @package H3_Tour_Management
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Security {
    
    /**
     * Allowed file extensions for tour uploads
     */
    private static $allowed_extensions = array('zip');
    
    /**
     * Allowed MIME types for tour uploads
     */
    private static $allowed_mime_types = array(
        'application/zip',
        'application/x-zip-compressed',
        'multipart/x-zip',
        'application/x-zip'
    );
    
    /**
     * Rate limiting settings
     */
    private static $rate_limits = array(
        'upload' => array(
            'requests' => 10,
            'window' => 3600 // 1 hour
        ),
        'analytics' => array(
            'requests' => 50,
            'window' => 3600 // 1 hour
        ),
        'email' => array(
            'requests' => 5,
            'window' => 600 // 10 minutes
        )
    );
    
    /**
     * Validate uploaded file
     * 
     * @param array $file Uploaded file array
     * @param string $expected_name Expected filename (optional)
     * @return array Result array with 'valid' boolean and 'error' message
     */
    public static function validate_upload($file, $expected_name = '') {
        $result = array(
            'valid' => false,
            'error' => ''
        );
        
        // Check if file upload succeeded
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $result['error'] = self::get_upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE);
            return $result;
        }
        
        // Check file size (max 500MB)
        $max_size = 500 * 1024 * 1024; // 500MB in bytes
        if ($file['size'] > $max_size) {
            $result['error'] = sprintf(
                __('File size exceeds maximum allowed size of %s', 'h3-tour-management'),
                size_format($max_size)
            );
            return $result;
        }
        
        // Validate file extension
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension'] ?? '');
        
        if (!in_array($extension, self::$allowed_extensions, true)) {
            $result['error'] = sprintf(
                __('Invalid file type. Allowed types: %s', 'h3-tour-management'),
                implode(', ', self::$allowed_extensions)
            );
            return $result;
        }
        
        // Validate MIME type using WordPress function
        $file_type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        
        if (!$file_type['type'] || !in_array($file_type['type'], self::$allowed_mime_types, true)) {
            $result['error'] = __('Invalid file type detected. Please upload a valid ZIP file.', 'h3-tour-management');
            return $result;
        }
        
        // Additional ZIP validation
        if (!self::is_valid_zip($file['tmp_name'])) {
            $result['error'] = __('The uploaded file is not a valid ZIP archive.', 'h3-tour-management');
            return $result;
        }
        
        // Check for malicious content in ZIP
        if (!self::is_safe_zip($file['tmp_name'])) {
            $result['error'] = __('The ZIP file contains potentially unsafe content.', 'h3-tour-management');
            return $result;
        }
        
        $result['valid'] = true;
        return $result;
    }
    
    /**
     * Check if file is a valid ZIP archive
     * 
     * @param string $file_path Path to file
     * @return bool
     */
    private static function is_valid_zip($file_path) {
        $zip = new ZipArchive();
        $result = $zip->open($file_path, ZipArchive::CHECKCONS);
        
        if ($result === true) {
            $zip->close();
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if ZIP contains only safe files
     * 
     * @param string $file_path Path to ZIP file
     * @return bool
     */
    private static function is_safe_zip($file_path) {
        $zip = new ZipArchive();
        
        if ($zip->open($file_path) !== true) {
            return false;
        }
        
        $unsafe_extensions = array('php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'exe', 'sh', 'bat');
        $required_files = array('index.html', 'index.htm');
        $has_index = false;
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Check for directory traversal attempts
            if (strpos($filename, '..') !== false || strpos($filename, '/') === 0) {
                $zip->close();
                return false;
            }
            
            // Get file extension
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // Check for unsafe extensions
            if (in_array($ext, $unsafe_extensions, true)) {
                $zip->close();
                return false;
            }
            
            // Check for required index file
            $basename = basename($filename);
            if (in_array($basename, $required_files, true)) {
                $has_index = true;
            }
        }
        
        $zip->close();
        
        // Ensure ZIP contains an index file
        return $has_index;
    }
    
    /**
     * Sanitize file path to prevent directory traversal
     * 
     * @param string $path Path to sanitize
     * @param string $base_path Base directory path
     * @return string|false Sanitized path or false if invalid
     */
    public static function sanitize_path($path, $base_path) {
        // Remove any null bytes
        $path = str_replace(chr(0), '', $path);
        
        // Resolve to absolute path
        $real_base = realpath($base_path);
        $real_path = realpath($base_path . '/' . $path);
        
        // Ensure the path is within the base directory
        if ($real_path === false || strpos($real_path, $real_base) !== 0) {
            return false;
        }
        
        return $real_path;
    }
    
    /**
     * Check rate limit for an action
     * 
     * @param string $action Action identifier
     * @param int $user_id User ID (0 for anonymous)
     * @return bool True if within rate limit, false if exceeded
     */
    public static function check_rate_limit($action, $user_id = 0) {
        if (!isset(self::$rate_limits[$action])) {
            return true; // No rate limit defined
        }
        
        $limits = self::$rate_limits[$action];
        $key = 'h3tm_rate_' . $action . '_' . $user_id;
        $window_start = time() - $limits['window'];
        
        // Get current attempts
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            $attempts = array();
        }
        
        // Remove old attempts outside the window
        $attempts = array_filter($attempts, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        // Check if limit exceeded
        if (count($attempts) >= $limits['requests']) {
            return false;
        }
        
        // Add current attempt
        $attempts[] = time();
        set_transient($key, $attempts, $limits['window']);
        
        return true;
    }
    
    /**
     * Generate secure random token
     * 
     * @param int $length Token length
     * @return string
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Sanitize output to prevent XSS
     * 
     * @param mixed $data Data to sanitize
     * @param string $context Context for sanitization
     * @return mixed Sanitized data
     */
    public static function sanitize_output($data, $context = 'html') {
        if (is_array($data)) {
            return array_map(function($item) use ($context) {
                return self::sanitize_output($item, $context);
            }, $data);
        }
        
        switch ($context) {
            case 'html':
                return esc_html($data);
            case 'attr':
                return esc_attr($data);
            case 'url':
                return esc_url($data);
            case 'js':
                return esc_js($data);
            case 'textarea':
                return esc_textarea($data);
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Verify AJAX nonce with additional checks
     * 
     * @param string $nonce Nonce to verify
     * @param string $action Nonce action
     * @return bool
     */
    public static function verify_ajax_request($nonce, $action) {
        // Check nonce
        if (!wp_verify_nonce($nonce, $action)) {
            return false;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        // Check referer
        if (!check_ajax_referer($action, 'nonce', false)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get user-friendly upload error message
     * 
     * @param int $error_code Upload error code
     * @return string Error message
     */
    private static function get_upload_error_message($error_code) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE => __('The uploaded file exceeds the server size limit.', 'h3-tour-management'),
            UPLOAD_ERR_FORM_SIZE => __('The uploaded file exceeds the form size limit.', 'h3-tour-management'),
            UPLOAD_ERR_PARTIAL => __('The file was only partially uploaded. Please try again.', 'h3-tour-management'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'h3-tour-management'),
            UPLOAD_ERR_NO_TMP_DIR => __('Server error: Missing temporary folder.', 'h3-tour-management'),
            UPLOAD_ERR_CANT_WRITE => __('Server error: Failed to write file to disk.', 'h3-tour-management'),
            UPLOAD_ERR_EXTENSION => __('Server error: File upload stopped by extension.', 'h3-tour-management'),
        );
        
        return $messages[$error_code] ?? __('Unknown upload error occurred.', 'h3-tour-management');
    }
    
    /**
     * Log security event
     * 
     * @param string $event Event type
     * @param array $data Event data
     * @param string $level Log level (info, warning, error)
     */
    public static function log_security_event($event, $data = array(), $level = 'info') {
        if (class_exists('H3TM_Logger')) {
            H3TM_Logger::log('security', array(
                'event' => $event,
                'data' => $data,
                'user_id' => get_current_user_id(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ), $level);
        }
    }
}