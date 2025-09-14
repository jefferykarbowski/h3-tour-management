<?php
/**
 * H3TM Pantheon Helper
 * Environment detection and path management for Pantheon hosting
 */

class H3TM_Pantheon_Helper {
    
    /**
     * Detect if running on Pantheon
     */
    public static function is_pantheon() {
        return (
            isset($_ENV['PANTHEON_ENVIRONMENT']) || 
            isset($_SERVER['PANTHEON_ENVIRONMENT']) ||
            defined('PANTHEON_ENVIRONMENT') ||
            (defined('ABSPATH') && strpos(ABSPATH, '/code/') === 0)
        );
    }
    
    /**
     * Get the appropriate h3panos directory path
     * Pantheon: /files/h3panos (writeable)
     * Other hosts: /h3panos (traditional)
     */
    public static function get_h3panos_path() {
        if (self::is_pantheon()) {
            // Pantheon: Use writeable files directory
            $upload_dir = wp_upload_dir();
            return $upload_dir['basedir'] . '/h3panos';
        } else {
            // Traditional hosting: Use site root
            return ABSPATH . 'h3panos';
        }
    }
    
    /**
     * Get the appropriate h3panos URL 
     * Pantheon: /wp-content/uploads/h3panos
     * Other hosts: /h3panos
     */
    public static function get_h3panos_url() {
        if (self::is_pantheon()) {
            // Pantheon: Use uploads URL
            $upload_dir = wp_upload_dir();
            return $upload_dir['baseurl'] . '/h3panos';
        } else {
            // Traditional hosting: Use site root URL
            return home_url('/h3panos');
        }
    }
    
    /**
     * Get writeable directory for disk space checks
     */
    public static function get_writeable_path() {
        if (self::is_pantheon()) {
            // Pantheon: Use uploads directory (always writeable)
            $upload_dir = wp_upload_dir();
            return $upload_dir['basedir'];
        } else {
            // Other hosts: Check h3panos or fall back to uploads
            $h3panos_path = ABSPATH . 'h3panos';
            return is_writeable($h3panos_path) ? $h3panos_path : wp_upload_dir()['basedir'];
        }
    }
    
    /**
     * Create h3panos directory if it doesn't exist
     */
    public static function ensure_h3panos_directory() {
        $h3panos_path = self::get_h3panos_path();
        
        if (!file_exists($h3panos_path)) {
            if (!wp_mkdir_p($h3panos_path)) {
                return new WP_Error('directory_creation_failed', 'Failed to create h3panos directory: ' . $h3panos_path);
            }
        }
        
        // Add index.php for security
        $index_file = $h3panos_path . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
        
        return true;
    }
    
    /**
     * Get environment info for debugging
     */
    public static function get_debug_info() {
        return array(
            'is_pantheon' => self::is_pantheon(),
            'h3panos_path' => self::get_h3panos_path(),
            'h3panos_url' => self::get_h3panos_url(),
            'writeable_path' => self::get_writeable_path(),
            'abspath' => ABSPATH,
            'pantheon_env' => $_ENV['PANTHEON_ENVIRONMENT'] ?? $_SERVER['PANTHEON_ENVIRONMENT'] ?? 'not_set',
            'uploads_info' => wp_upload_dir()
        );
    }
}
?>