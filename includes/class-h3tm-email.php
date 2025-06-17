<?php
/**
 * Email functionality
 */
class H3TM_Email {
    
    public function __construct() {
        // Nothing to initialize yet
    }
    
    /**
     * Get email headers
     */
    public static function get_email_headers() {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $from_name = get_option('h3tm_email_from_name', 'H3 Photography');
        $from_email = get_option('h3tm_email_from_address', get_option('admin_email'));
        
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        
        return $headers;
    }
}