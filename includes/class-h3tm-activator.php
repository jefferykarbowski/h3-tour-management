<?php
/**
 * Fired during plugin activation
 */
class H3TM_Activator {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::create_upload_directory();
        
        // Schedule cron job for analytics emails
        if (!wp_next_scheduled('h3tm_analytics_cron')) {
            wp_schedule_event(strtotime('00:00:00'), 'daily', 'h3tm_analytics_cron');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('h3tm_analytics_cron');
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for user email frequency settings
        $table_name = $wpdb->prefix . 'h3tm_user_settings';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            email_frequency varchar(20) DEFAULT 'monthly',
            last_email_sent datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        add_option('h3tm_version', H3TM_VERSION);
        add_option('h3tm_tour_directory', H3TM_TOUR_DIR);
        add_option('h3tm_email_from_name', 'H3 Photography');
        add_option('h3tm_email_from_address', get_option('admin_email'));
    }
    
    /**
     * Create upload directory for tours
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $tour_upload_dir = $upload_dir['basedir'] . '/h3-tours';
        
        if (!file_exists($tour_upload_dir)) {
            wp_mkdir_p($tour_upload_dir);
        }
    }
}