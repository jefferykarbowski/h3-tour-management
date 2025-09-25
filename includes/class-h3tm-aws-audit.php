<?php
/**
 * AWS Audit and Logging System for H3 Tour Management
 *
 * Provides comprehensive audit logging for all AWS operations with
 * security event tracking and compliance reporting capabilities.
 *
 * @package H3_Tour_Management
 * @since 2.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_AWS_Audit {

    /**
     * Log levels for audit events
     */
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Event categories
     */
    const CATEGORY_CREDENTIAL = 'credential';
    const CATEGORY_S3_OPERATION = 's3_operation';
    const CATEGORY_ACCESS_CONTROL = 'access_control';
    const CATEGORY_CONFIGURATION = 'configuration';
    const CATEGORY_SECURITY = 'security';

    /**
     * Maximum log entries to keep in database
     */
    const MAX_LOG_ENTRIES = 10000;

    /**
     * Log retention period (days)
     */
    const LOG_RETENTION_DAYS = 90;

    /**
     * Initialize audit system
     */
    public function __construct() {
        add_action('init', array($this, 'create_audit_table'));
        add_action('h3tm_daily_cleanup', array($this, 'cleanup_old_logs'));
    }

    /**
     * Log AWS security event with full context
     *
     * @param string $event_type Type of event
     * @param string $category Event category
     * @param array $data Event data
     * @param string $level Log level
     * @param string $user_id User ID (optional)
     * @return bool Success status
     */
    public static function log_event($event_type, $category, $data = array(), $level = self::LEVEL_INFO, $user_id = null) {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Sanitize and prepare data
        $sanitized_data = self::sanitize_log_data($data);

        // Collect request context
        $context = self::collect_request_context();

        $log_entry = array(
            'event_type' => sanitize_text_field($event_type),
            'category' => sanitize_text_field($category),
            'level' => sanitize_text_field($level),
            'user_id' => intval($user_id),
            'data' => wp_json_encode($sanitized_data),
            'context' => wp_json_encode($context),
            'ip_address' => self::get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 500),
            'timestamp' => current_time('mysql', true),
            'session_id' => self::get_session_id()
        );

        $table_name = $wpdb->prefix . 'h3tm_aws_audit_log';

        $result = $wpdb->insert($table_name, $log_entry, array(
            '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'
        ));

        // Clean up if we exceed max entries
        if ($result) {
            self::maintain_log_size();
        }

        // Send alerts for critical events
        if ($level === self::LEVEL_CRITICAL) {
            self::send_security_alert($event_type, $sanitized_data, $context);
        }

        return $result !== false;
    }

    /**
     * Sanitize log data to remove sensitive information
     *
     * @param array $data Raw log data
     * @return array Sanitized data
     */
    private static function sanitize_log_data($data) {
        $sensitive_keys = array(
            'access_key', 'secret_key', 'password', 'token', 'credential',
            'Authorization', 'X-Amz-Security-Token', 'AWS-Access-Key'
        );

        $sanitized = array();

        foreach ($data as $key => $value) {
            $key_lower = strtolower($key);

            // Check if key contains sensitive information
            $is_sensitive = false;
            foreach ($sensitive_keys as $sensitive_key) {
                if (strpos($key_lower, strtolower($sensitive_key)) !== false) {
                    $is_sensitive = true;
                    break;
                }
            }

            if ($is_sensitive) {
                if (is_string($value) && strlen($value) > 4) {
                    // Show only first 4 characters
                    $sanitized[$key] = substr($value, 0, 4) . str_repeat('*', min(8, strlen($value) - 4));
                } else {
                    $sanitized[$key] = '***REDACTED***';
                }
            } else {
                // Recursively sanitize nested arrays
                if (is_array($value)) {
                    $sanitized[$key] = self::sanitize_log_data($value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
        }

        return $sanitized;
    }

    /**
     * Collect request context for logging
     *
     * @return array Request context
     */
    private static function collect_request_context() {
        return array(
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => defined('H3TM_VERSION') ? H3TM_VERSION : 'unknown',
            'environment' => self::detect_environment(),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown'
        );
    }

    /**
     * Detect current environment
     *
     * @return string Environment type
     */
    private static function detect_environment() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (strpos($host, 'staging') !== false || strpos($host, 'dev') !== false) {
            return 'staging';
        }

        if (strpos($host, 'localhost') !== false || strpos($host, '.local') !== false) {
            return 'development';
        }

        return 'production';
    }

    /**
     * Get client IP address with proxy support
     *
     * @return string Client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_CLIENT_IP',            // Proxy
            'REMOTE_ADDR'               // Standard
        );

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip_list = explode(',', $_SERVER[$key]);
                $ip = trim($ip_list[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get session ID for tracking
     *
     * @return string Session ID
     */
    private static function get_session_id() {
        if (session_id()) {
            return session_id();
        }

        // Generate a session ID based on user and timestamp
        $user_id = get_current_user_id();
        $ip = self::get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return hash('sha256', $user_id . $ip . $user_agent . date('Y-m-d H'));
    }

    /**
     * Send security alert for critical events
     *
     * @param string $event_type Event type
     * @param array $data Event data
     * @param array $context Request context
     */
    private static function send_security_alert($event_type, $data, $context) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        $subject = sprintf(
            '[SECURITY ALERT] H3TM AWS: %s on %s',
            $event_type,
            get_bloginfo('name')
        );

        $message = "Security Alert: Critical AWS event detected\n\n";
        $message .= "Event Type: {$event_type}\n";
        $message .= "Environment: " . ($context['environment'] ?? 'unknown') . "\n";
        $message .= "Timestamp: " . current_time('Y-m-d H:i:s T') . "\n";
        $message .= "IP Address: " . self::get_client_ip() . "\n";
        $message .= "User ID: " . get_current_user_id() . "\n\n";

        if (!empty($data)) {
            $message .= "Event Data:\n";
            foreach ($data as $key => $value) {
                $message .= "  {$key}: " . (is_string($value) ? $value : wp_json_encode($value)) . "\n";
            }
        }

        $message .= "\nPlease investigate immediately.\n";
        $message .= "Login to WordPress admin to review full audit logs.";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Create audit log table
     */
    public function create_audit_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'h3tm_aws_audit_log';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            category varchar(50) NOT NULL,
            level varchar(20) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            data longtext,
            context longtext,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(500),
            timestamp datetime NOT NULL,
            session_id varchar(64),
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY category (category),
            KEY level (level),
            KEY user_id (user_id),
            KEY timestamp (timestamp),
            KEY ip_address (ip_address),
            KEY session_id (session_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Maintain log table size
     */
    private static function maintain_log_size() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'h3tm_aws_audit_log';

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($count > self::MAX_LOG_ENTRIES) {
            $excess = $count - self::MAX_LOG_ENTRIES;
            $wpdb->query("DELETE FROM {$table_name} ORDER BY timestamp ASC LIMIT {$excess}");
        }
    }

    /**
     * Clean up old log entries
     */
    public function cleanup_old_logs() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'h3tm_aws_audit_log';
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . self::LOG_RETENTION_DAYS . ' days'));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE timestamp < %s",
                $cutoff_date
            )
        );

        if ($deleted > 0) {
            self::log_event(
                'log_cleanup_completed',
                self::CATEGORY_CONFIGURATION,
                array('deleted_entries' => $deleted, 'cutoff_date' => $cutoff_date),
                self::LEVEL_INFO
            );
        }
    }

    /**
     * Get audit logs with filtering
     *
     * @param array $filters Filter parameters
     * @param int $limit Number of entries to return
     * @param int $offset Offset for pagination
     * @return array Log entries
     */
    public static function get_audit_logs($filters = array(), $limit = 50, $offset = 0) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'h3tm_aws_audit_log';

        $where_clauses = array();
        $where_values = array();

        // Apply filters
        if (!empty($filters['event_type'])) {
            $where_clauses[] = 'event_type = %s';
            $where_values[] = $filters['event_type'];
        }

        if (!empty($filters['category'])) {
            $where_clauses[] = 'category = %s';
            $where_values[] = $filters['category'];
        }

        if (!empty($filters['level'])) {
            $where_clauses[] = 'level = %s';
            $where_values[] = $filters['level'];
        }

        if (!empty($filters['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = intval($filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'timestamp >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'timestamp <= %s';
            $where_values[] = $filters['date_to'];
        }

        if (!empty($filters['ip_address'])) {
            $where_clauses[] = 'ip_address = %s';
            $where_values[] = $filters['ip_address'];
        }

        // Build query
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        $query = "SELECT * FROM {$table_name} {$where_sql} ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $where_values[] = intval($limit);
        $where_values[] = intval($offset);

        $prepared_query = $wpdb->prepare($query, $where_values);

        return $wpdb->get_results($prepared_query, ARRAY_A);
    }

    /**
     * Get audit log statistics
     *
     * @param string $period Period for stats (24h, 7d, 30d)
     * @return array Statistics
     */
    public static function get_audit_stats($period = '24h') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'h3tm_aws_audit_log';

        // Calculate date range
        switch ($period) {
            case '7d':
                $date_from = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30d':
                $date_from = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '24h':
            default:
                $date_from = date('Y-m-d H:i:s', strtotime('-24 hours'));
                break;
        }

        // Total events
        $total_events = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE timestamp >= %s",
                $date_from
            )
        );

        // Events by level
        $events_by_level = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT level, COUNT(*) as count FROM {$table_name} WHERE timestamp >= %s GROUP BY level",
                $date_from
            ),
            ARRAY_A
        );

        // Events by category
        $events_by_category = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT category, COUNT(*) as count FROM {$table_name} WHERE timestamp >= %s GROUP BY category",
                $date_from
            ),
            ARRAY_A
        );

        // Top event types
        $top_events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_type, COUNT(*) as count FROM {$table_name} WHERE timestamp >= %s GROUP BY event_type ORDER BY count DESC LIMIT 10",
                $date_from
            ),
            ARRAY_A
        );

        // Unique users
        $unique_users = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$table_name} WHERE timestamp >= %s AND user_id > 0",
                $date_from
            )
        );

        // Unique IPs
        $unique_ips = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT ip_address) FROM {$table_name} WHERE timestamp >= %s",
                $date_from
            )
        );

        return array(
            'period' => $period,
            'total_events' => intval($total_events),
            'events_by_level' => $events_by_level,
            'events_by_category' => $events_by_category,
            'top_events' => $top_events,
            'unique_users' => intval($unique_users),
            'unique_ips' => intval($unique_ips),
            'date_from' => $date_from,
            'generated_at' => current_time('mysql')
        );
    }

    /**
     * Export audit logs for compliance
     *
     * @param array $filters Export filters
     * @param string $format Export format (csv, json)
     * @return string|false Export data or false on failure
     */
    public static function export_audit_logs($filters = array(), $format = 'csv') {
        $logs = self::get_audit_logs($filters, 10000, 0); // Export up to 10k entries

        if (empty($logs)) {
            return false;
        }

        switch ($format) {
            case 'json':
                return wp_json_encode($logs, JSON_PRETTY_PRINT);

            case 'csv':
            default:
                $csv_data = "ID,Event Type,Category,Level,User ID,IP Address,Timestamp,Data\n";

                foreach ($logs as $log) {
                    $csv_data .= sprintf(
                        "%d,%s,%s,%s,%d,%s,%s,%s\n",
                        $log['id'],
                        $log['event_type'],
                        $log['category'],
                        $log['level'],
                        $log['user_id'],
                        $log['ip_address'],
                        $log['timestamp'],
                        str_replace('"', '""', $log['data'])
                    );
                }

                return $csv_data;
        }
    }

    /**
     * Convenience method for logging credential events
     */
    public static function log_credential_event($event_type, $data = array(), $level = self::LEVEL_INFO) {
        return self::log_event($event_type, self::CATEGORY_CREDENTIAL, $data, $level);
    }

    /**
     * Convenience method for logging S3 operations
     */
    public static function log_s3_operation($event_type, $data = array(), $level = self::LEVEL_INFO) {
        return self::log_event($event_type, self::CATEGORY_S3_OPERATION, $data, $level);
    }

    /**
     * Convenience method for logging security events
     */
    public static function log_security_event($event_type, $data = array(), $level = self::LEVEL_WARNING) {
        return self::log_event($event_type, self::CATEGORY_SECURITY, $data, $level);
    }

    /**
     * Convenience method for logging configuration events
     */
    public static function log_config_event($event_type, $data = array(), $level = self::LEVEL_INFO) {
        return self::log_event($event_type, self::CATEGORY_CONFIGURATION, $data, $level);
    }
}