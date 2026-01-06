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
        self::maybe_upgrade_tour_metadata_table();
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

         // Table for tour metadata
          $metadata_table = $wpdb->prefix . 'h3tm_tour_metadata';
          $sql_metadata = "CREATE TABLE $metadata_table (
              id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              tour_id varchar(32) DEFAULT NULL,
              tour_slug varchar(255) NOT NULL,
              display_name varchar(255) NOT NULL,
              s3_folder varchar(255) NOT NULL,
              status varchar(20) DEFAULT 'completed',
              entry_file varchar(255) DEFAULT 'index.htm',
              url_history text,
              created_date datetime DEFAULT CURRENT_TIMESTAMP,
              updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY tour_id (tour_id),
              UNIQUE KEY tour_slug (tour_slug),
              KEY idx_tour_id (tour_id),
              KEY idx_tour_slug (tour_slug),
              KEY idx_s3_folder (s3_folder),
              KEY idx_status (status)
          ) $charset_collate;";
          dbDelta($sql_metadata);
          self::migrate_existing_tours();
    }

    /**
     * Migrate existing tours to metadata table
     */
    private static function migrate_existing_tours() {
        // Check if migration has already been run
        if (get_option('h3tm_metadata_migrated', false)) {
            return;
        }

        // Get S3 tours
        if (class_exists('H3TM_S3_Simple')) {
            $s3_simple = new H3TM_S3_Simple();
            $s3_tours = $s3_simple->list_s3_tours();

            if (!empty($s3_tours) && class_exists('H3TM_Tour_Metadata')) {
                $metadata = new H3TM_Tour_Metadata();

                foreach ($s3_tours as $tour_name) {
                    // Check if metadata already exists
                    if (!$metadata->get_by_display_name($tour_name)) {
                        // Create metadata entry
                        $tour_slug = sanitize_title($tour_name);
                        // Lambda creates folders WITH SPACES, not dashes
                        $s3_folder = 'tours/' . $tour_name;  // Preserve spaces to match Lambda

                        $metadata->create(array(
                            'tour_slug' => $tour_slug,
                            'display_name' => $tour_name,
                            's3_folder' => $s3_folder,  // "tours/Jeffs Test/" not "tours/Jeffs-Test/"
                            'url_history' => json_encode(array())
                        ));
                    }
                }
            }
        }

        // Mark migration as complete
        update_option('h3tm_metadata_migrated', true);
    }

    /**
     * Upgrade tour metadata table to add tour_id column
     * Runs on plugin activation to upgrade existing installations
     */
    private static function maybe_upgrade_tour_metadata_table() {
        global $wpdb;

        // Check if upgrade has already been run
        if (get_option('h3tm_metadata_upgraded_to_v2', false)) {
            return;
        }

        $metadata_table = $wpdb->prefix . 'h3tm_tour_metadata';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$metadata_table'") === $metadata_table;
        if (!$table_exists) {
            return; // Table will be created with new schema
        }

        // Check if tour_id column already exists
        $column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM `{$metadata_table}` LIKE 'tour_id'"
        );

        if (empty($column_exists)) {
            error_log('H3TM: Upgrading tour metadata table to add tour_id column');

            // Add tour_id column (nullable for backward compatibility)
            $wpdb->query("ALTER TABLE `{$metadata_table}`
                ADD COLUMN `tour_id` varchar(32) DEFAULT NULL AFTER `id`,
                ADD UNIQUE KEY `tour_id` (`tour_id`),
                ADD KEY `idx_tour_id` (`tour_id`)
            ");

            error_log('H3TM: Added tour_id column and indexes');
        }

        // Check if status column already exists
        $status_column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM `{$metadata_table}` LIKE 'status'"
        );

        if (empty($status_column_exists)) {
            error_log('H3TM: Adding status column to tour metadata table');

            // Add status column (default to 'completed' for existing tours)
            $wpdb->query("ALTER TABLE `{$metadata_table}`
                ADD COLUMN `status` varchar(20) DEFAULT 'completed' AFTER `s3_folder`,
                ADD KEY `idx_status` (`status`)
            ");

            error_log('H3TM: Added status column and index');
        }

        // Check if entry_file column already exists
        $entry_file_column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM `{$metadata_table}` LIKE 'entry_file'"
        );

        if (empty($entry_file_column_exists)) {
            error_log('H3TM: Adding entry_file column to tour metadata table');

            // Add entry_file column (default to 'index.htm' for existing tours)
            $wpdb->query("ALTER TABLE `{$metadata_table}`
                ADD COLUMN `entry_file` varchar(255) DEFAULT 'index.htm' AFTER `status`
            ");

            error_log('H3TM: Added entry_file column');
        }

        // Mark upgrade as complete
        update_option('h3tm_metadata_upgraded_to_v2', true);
        error_log('H3TM: Tour metadata table upgrade completed');
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
