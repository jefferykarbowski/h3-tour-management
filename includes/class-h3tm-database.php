<?php
/**
 * Database handler for H3 Tour Management
 * 
 * Manages database schema, migrations, and optimizations
 * 
 * @package H3_Tour_Management
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Database {
    
    /**
     * Current database version
     */
    const DB_VERSION = '2.0.0';
    
    /**
     * Option key for database version
     */
    const VERSION_OPTION = 'h3tm_db_version';
    
    /**
     * Create or update database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // User settings table with proper indexes
        $table_user_settings = $wpdb->prefix . 'h3tm_user_settings';
        $sql_user_settings = "CREATE TABLE $table_user_settings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            email_frequency varchar(20) DEFAULT 'monthly',
            last_email_sent datetime DEFAULT NULL,
            analytics_enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY email_frequency (email_frequency),
            KEY last_email_sent (last_email_sent),
            CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) 
                REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Analytics cache table
        $table_analytics_cache = $wpdb->prefix . 'h3tm_analytics_cache';
        $sql_analytics_cache = "CREATE TABLE $table_analytics_cache (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_data longtext NOT NULL,
            tour_name varchar(255) DEFAULT NULL,
            expiration datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY expiration (expiration),
            KEY tour_name (tour_name)
        ) $charset_collate;";
        
        // Activity log table
        $table_activity_log = $wpdb->prefix . 'h3tm_activity_log';
        $sql_activity_log = "CREATE TABLE $table_activity_log (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            action varchar(50) NOT NULL,
            object_type varchar(50) DEFAULT NULL,
            object_id varchar(255) DEFAULT NULL,
            details longtext DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at),
            KEY object_lookup (object_type, object_id)
        ) $charset_collate;";
        
        // Tour metadata table
        $table_tour_meta = $wpdb->prefix . 'h3tm_tour_meta';
        $sql_tour_meta = "CREATE TABLE $table_tour_meta (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            tour_name varchar(255) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tour_meta (tour_name, meta_key),
            KEY tour_name (tour_name),
            KEY meta_key (meta_key)
        ) $charset_collate;";
        
        // Email queue table
        $table_email_queue = $wpdb->prefix . 'h3tm_email_queue';
        $sql_email_queue = "CREATE TABLE $table_email_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            email_type varchar(50) NOT NULL,
            tour_name varchar(255) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            attempts tinyint(3) DEFAULT 0,
            scheduled_at datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status_scheduled (status, scheduled_at),
            KEY email_type (email_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        dbDelta($sql_user_settings);
        dbDelta($sql_analytics_cache);
        dbDelta($sql_activity_log);
        dbDelta($sql_tour_meta);
        dbDelta($sql_email_queue);
        
        // Update database version
        update_option(self::VERSION_OPTION, self::DB_VERSION);
        
        // Log the database update
        H3TM_Logger::info('database', 'Database tables created/updated', array(
            'version' => self::DB_VERSION
        ));
    }
    
    /**
     * Run database migrations
     */
    public static function migrate() {
        $current_version = get_option(self::VERSION_OPTION, '0');
        
        if (version_compare($current_version, self::DB_VERSION, '>=')) {
            return; // No migration needed
        }
        
        // Run migrations based on version
        if (version_compare($current_version, '1.1.0', '<')) {
            self::migrate_to_1_1_0();
        }
        
        if (version_compare($current_version, '2.0.0', '<')) {
            self::migrate_to_2_0_0();
        }
        
        // Create/update tables
        self::create_tables();
        
        // Clean up old data
        self::cleanup_old_data();
    }
    
    /**
     * Migration to version 1.1.0
     */
    private static function migrate_to_1_1_0() {
        global $wpdb;
        
        // Migrate user tour assignments to tour_meta table
        $users = get_users(array(
            'meta_key' => 'h3tm_tours',
            'meta_compare' => 'EXISTS'
        ));
        
        $table_tour_meta = $wpdb->prefix . 'h3tm_tour_meta';
        
        foreach ($users as $user) {
            $tours = get_user_meta($user->ID, 'h3tm_tours', true);
            
            if (is_array($tours)) {
                foreach ($tours as $tour) {
                    // Store user assignment in tour_meta
                    $wpdb->insert(
                        $table_tour_meta,
                        array(
                            'tour_name' => $tour,
                            'meta_key' => 'assigned_user_' . $user->ID,
                            'meta_value' => json_encode(array(
                                'user_id' => $user->ID,
                                'assigned_at' => current_time('mysql')
                            ))
                        ),
                        array('%s', '%s', '%s')
                    );
                }
            }
        }
        
        H3TM_Logger::info('database', 'Migrated to version 1.1.0');
    }
    
    /**
     * Migration to version 2.0.0
     */
    private static function migrate_to_2_0_0() {
        global $wpdb;
        
        // Add indexes to existing tables if they don't exist
        $table_user_settings = $wpdb->prefix . 'h3tm_user_settings';
        
        // Check if indexes exist before adding
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM $table_user_settings");
        $index_names = array_column($existing_indexes, 'Key_name');
        
        if (!in_array('email_frequency', $index_names)) {
            $wpdb->query("ALTER TABLE $table_user_settings ADD INDEX email_frequency (email_frequency)");
        }
        
        if (!in_array('last_email_sent', $index_names)) {
            $wpdb->query("ALTER TABLE $table_user_settings ADD INDEX last_email_sent (last_email_sent)");
        }
        
        H3TM_Logger::info('database', 'Migrated to version 2.0.0');
    }
    
    /**
     * Clean up old data
     */
    private static function cleanup_old_data() {
        global $wpdb;
        
        // Clean up old analytics cache (older than 30 days)
        $table_analytics_cache = $wpdb->prefix . 'h3tm_analytics_cache';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_analytics_cache WHERE expiration < %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // Clean up old activity logs (older than 90 days)
        $table_activity_log = $wpdb->prefix . 'h3tm_activity_log';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_activity_log WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
        
        // Clean up processed email queue items (older than 7 days)
        $table_email_queue = $wpdb->prefix . 'h3tm_email_queue';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_email_queue WHERE status IN ('sent', 'failed') AND created_at < %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
    }
    
    /**
     * Drop all plugin tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'h3tm_user_settings',
            $wpdb->prefix . 'h3tm_analytics_cache',
            $wpdb->prefix . 'h3tm_activity_log',
            $wpdb->prefix . 'h3tm_tour_meta',
            $wpdb->prefix . 'h3tm_email_queue'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Remove database version option
        delete_option(self::VERSION_OPTION);
        
        H3TM_Logger::info('database', 'All plugin tables dropped');
    }
    
    /**
     * Log activity
     * 
     * @param string $action Action performed
     * @param string $object_type Type of object
     * @param string $object_id Object identifier
     * @param array $details Additional details
     */
    public static function log_activity($action, $object_type = null, $object_id = null, $details = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'h3tm_activity_log';
        
        $data = array(
            'user_id' => get_current_user_id() ?: null,
            'action' => $action,
            'object_type' => $object_type,
            'object_id' => $object_id,
            'details' => json_encode($details),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        );
        
        $wpdb->insert($table, $data, array('%d', '%s', '%s', '%s', '%s', '%s', '%s'));
    }
    
    /**
     * Get tour metadata
     * 
     * @param string $tour_name Tour name
     * @param string $meta_key Meta key (optional)
     * @return mixed Meta value or array of all meta
     */
    public static function get_tour_meta($tour_name, $meta_key = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'h3tm_tour_meta';
        
        if ($meta_key) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM $table WHERE tour_name = %s AND meta_key = %s",
                $tour_name,
                $meta_key
            ));
        } else {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_key, meta_value FROM $table WHERE tour_name = %s",
                $tour_name
            ), ARRAY_A);
            
            $meta = array();
            foreach ($results as $row) {
                $meta[$row['meta_key']] = $row['meta_value'];
            }
            
            return $meta;
        }
    }
    
    /**
     * Update tour metadata
     * 
     * @param string $tour_name Tour name
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     */
    public static function update_tour_meta($tour_name, $meta_key, $meta_value) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'h3tm_tour_meta';
        
        // Serialize if needed
        if (is_array($meta_value) || is_object($meta_value)) {
            $meta_value = json_encode($meta_value);
        }
        
        $wpdb->replace(
            $table,
            array(
                'tour_name' => $tour_name,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * Delete tour metadata
     * 
     * @param string $tour_name Tour name
     * @param string $meta_key Meta key (optional, deletes all if not specified)
     */
    public static function delete_tour_meta($tour_name, $meta_key = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'h3tm_tour_meta';
        
        if ($meta_key) {
            $wpdb->delete(
                $table,
                array(
                    'tour_name' => $tour_name,
                    'meta_key' => $meta_key
                ),
                array('%s', '%s')
            );
        } else {
            $wpdb->delete(
                $table,
                array('tour_name' => $tour_name),
                array('%s')
            );
        }
    }
    
    /**
     * Queue an email for sending
     * 
     * @param int $user_id User ID
     * @param string $email_type Email type
     * @param string $tour_name Tour name (optional)
     * @param datetime $scheduled_at When to send
     * @return int|false Insert ID or false on failure
     */
    public static function queue_email($user_id, $email_type, $tour_name = null, $scheduled_at = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'h3tm_email_queue';
        
        if (!$scheduled_at) {
            $scheduled_at = current_time('mysql');
        }
        
        return $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'email_type' => $email_type,
                'tour_name' => $tour_name,
                'scheduled_at' => $scheduled_at
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get pending emails from queue
     * 
     * @param int $limit Maximum number of emails to retrieve
     * @return array Email queue items
     */
    public static function get_pending_emails($limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'h3tm_email_queue';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE status = 'pending' 
             AND scheduled_at <= %s 
             AND attempts < 3 
             ORDER BY scheduled_at ASC 
             LIMIT %d",
            current_time('mysql'),
            $limit
        ));
    }
    
    /**
     * Update email queue status
     * 
     * @param int $id Queue ID
     * @param string $status New status
     * @param string $error_message Error message (optional)
     */
    public static function update_email_status($id, $status, $error_message = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'h3tm_email_queue';
        
        $data = array(
            'status' => $status,
            'attempts' => ['raw' => 'attempts + 1']
        );
        
        if ($status === 'sent') {
            $data['sent_at'] = current_time('mysql');
        }
        
        if ($error_message) {
            $data['error_message'] = $error_message;
        }
        
        $wpdb->update(
            $table,
            $data,
            array('id' => $id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get database statistics
     * 
     * @return array Statistics
     */
    public static function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        // User settings
        $table_user_settings = $wpdb->prefix . 'h3tm_user_settings';
        $stats['user_settings'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_user_settings");
        
        // Analytics cache
        $table_analytics_cache = $wpdb->prefix . 'h3tm_analytics_cache';
        $stats['cache_entries'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_analytics_cache");
        $stats['cache_size'] = $wpdb->get_var("SELECT SUM(LENGTH(cache_data)) FROM $table_analytics_cache");
        
        // Activity log
        $table_activity_log = $wpdb->prefix . 'h3tm_activity_log';
        $stats['activity_entries'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_activity_log");
        
        // Email queue
        $table_email_queue = $wpdb->prefix . 'h3tm_email_queue';
        $stats['pending_emails'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_email_queue WHERE status = 'pending'");
        $stats['failed_emails'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_email_queue WHERE status = 'failed'");
        
        return $stats;
    }
}