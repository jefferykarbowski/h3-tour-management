<?php
/**
 * Configuration helper for environment-specific settings
 */
class H3TM_Config {
    
    /**
     * Get the path to the service account credentials file
     * 
     * @return string
     */
    public static function get_credentials_path() {
        // Check for environment-specific constant first
        if (defined('H3TM_CREDENTIALS_PATH')) {
            return H3TM_CREDENTIALS_PATH;
        }
        
        // Check multiple possible locations
        $possible_paths = array(
            // 1. Plugin directory (highest priority)
            H3TM_PLUGIN_DIR . 'service-account-credentials.json',
            
            // 2. WordPress content directory
            WP_CONTENT_DIR . '/h3tm-credentials/service-account-credentials.json',
            
            // 3. Above WordPress root (more secure)
            dirname(ABSPATH) . '/private/service-account-credentials.json',
            
            // 4. Document root (current default)
            realpath($_SERVER["DOCUMENT_ROOT"]) . '/service-account-credentials.json',
            
            // 5. WordPress root
            ABSPATH . 'service-account-credentials.json',
        );
        
        // Return the first existing file
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Return default path even if it doesn't exist (for error messages)
        return realpath($_SERVER["DOCUMENT_ROOT"]) . '/service-account-credentials.json';
    }
    
    /**
     * Get the vendor autoload path
     * 
     * @return string
     */
    public static function get_autoload_path() {
        // Check for environment-specific constant first
        if (defined('H3TM_VENDOR_PATH')) {
            return H3TM_VENDOR_PATH . '/autoload.php';
        }
        
        // Check multiple possible locations
        $possible_paths = array(
            // 1. Plugin vendor directory
            H3TM_PLUGIN_DIR . 'vendor/autoload.php',
            
            // 2. WordPress root vendor
            ABSPATH . 'vendor/autoload.php',
            
            // 3. Document root vendor (current default)
            realpath($_SERVER["DOCUMENT_ROOT"]) . '/vendor/autoload.php',
            
            // 4. One level above WordPress
            dirname(ABSPATH) . '/vendor/autoload.php',
        );
        
        // Return the first existing file
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Return default path
        return realpath($_SERVER["DOCUMENT_ROOT"]) . '/vendor/autoload.php';
    }
    
    /**
     * Check if we're in a development environment
     * 
     * @return bool
     */
    public static function is_development() {
        // Check for local development indicators
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        return (
            strpos($host, 'localhost') !== false ||
            strpos($host, '.local') !== false ||
            strpos($host, '.test') !== false ||
            defined('WP_DEBUG') && WP_DEBUG === true
        );
    }
    
    /**
     * Get SSL verification setting
     * 
     * @return bool
     */
    public static function should_verify_ssl() {
        // Allow override via constant
        if (defined('H3TM_VERIFY_SSL')) {
            return H3TM_VERIFY_SSL;
        }
        
        // Disable SSL verification for development
        return !self::is_development();
    }
}