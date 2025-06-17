<?php
/**
 * Logging service for H3 Tour Management
 * 
 * Provides consistent logging functionality with different levels and contexts
 * 
 * @package H3_Tour_Management
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Logger {
    
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    /**
     * Log contexts
     */
    const CONTEXT_GENERAL = 'general';
    const CONTEXT_SECURITY = 'security';
    const CONTEXT_ANALYTICS = 'analytics';
    const CONTEXT_EMAIL = 'email';
    const CONTEXT_UPLOAD = 'upload';
    const CONTEXT_TOUR = 'tour';
    
    /**
     * Maximum log file size (5MB)
     */
    const MAX_LOG_SIZE = 5242880;
    
    /**
     * Maximum number of log files to keep
     */
    const MAX_LOG_FILES = 10;
    
    /**
     * Log directory path
     * 
     * @var string
     */
    private static $log_dir;
    
    /**
     * Whether logging is enabled
     * 
     * @var bool
     */
    private static $enabled;
    
    /**
     * Minimum log level
     * 
     * @var string
     */
    private static $min_level;
    
    /**
     * Initialize logger
     */
    public static function init() {
        self::$enabled = get_option('h3tm_logging_enabled', true);
        self::$min_level = get_option('h3tm_log_level', self::LEVEL_INFO);
        
        // Set up log directory
        $upload_dir = wp_upload_dir();
        self::$log_dir = $upload_dir['basedir'] . '/h3tm-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
            
            // Add .htaccess to prevent direct access
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents(self::$log_dir . '/.htaccess', $htaccess_content);
            
            // Add index.php for extra security
            file_put_contents(self::$log_dir . '/index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Log a message
     * 
     * @param string $context Log context
     * @param mixed $message Message to log
     * @param string $level Log level
     * @param array $data Additional data
     */
    public static function log($context, $message, $level = self::LEVEL_INFO, $data = array()) {
        // Initialize if not already done
        if (self::$log_dir === null) {
            self::init();
        }
        
        // Check if logging is enabled
        if (!self::$enabled) {
            return;
        }
        
        // Check if level meets minimum threshold
        if (!self::should_log($level)) {
            return;
        }
        
        // Prepare log entry
        $entry = self::prepare_log_entry($context, $message, $level, $data);
        
        // Write to log file
        self::write_log($context, $entry);
        
        // Also log to WordPress debug.log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[H3TM] ' . $entry);
        }
    }
    
    /**
     * Log debug message
     */
    public static function debug($context, $message, $data = array()) {
        self::log($context, $message, self::LEVEL_DEBUG, $data);
    }
    
    /**
     * Log info message
     */
    public static function info($context, $message, $data = array()) {
        self::log($context, $message, self::LEVEL_INFO, $data);
    }
    
    /**
     * Log warning message
     */
    public static function warning($context, $message, $data = array()) {
        self::log($context, $message, self::LEVEL_WARNING, $data);
    }
    
    /**
     * Log error message
     */
    public static function error($context, $message, $data = array()) {
        self::log($context, $message, self::LEVEL_ERROR, $data);
    }
    
    /**
     * Log critical message
     */
    public static function critical($context, $message, $data = array()) {
        self::log($context, $message, self::LEVEL_CRITICAL, $data);
    }
    
    /**
     * Check if a level should be logged
     * 
     * @param string $level Log level
     * @return bool
     */
    private static function should_log($level) {
        $levels = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4
        );
        
        $current_level = $levels[$level] ?? 1;
        $min_level = $levels[self::$min_level] ?? 1;
        
        return $current_level >= $min_level;
    }
    
    /**
     * Prepare log entry
     * 
     * @param string $context Log context
     * @param mixed $message Message
     * @param string $level Log level
     * @param array $data Additional data
     * @return string Formatted log entry
     */
    private static function prepare_log_entry($context, $message, $level, $data) {
        // Convert message to string if needed
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        
        // Prepare entry components
        $timestamp = current_time('Y-m-d H:i:s');
        $level_upper = strtoupper($level);
        $context_upper = strtoupper($context);
        
        // Basic entry
        $entry = "[{$timestamp}] [{$level_upper}] [{$context_upper}] {$message}";
        
        // Add additional data if provided
        if (!empty($data)) {
            $entry .= ' | Data: ' . json_encode($data, JSON_UNESCAPED_SLASHES);
        }
        
        // Add memory usage for debugging
        if ($level === self::LEVEL_DEBUG) {
            $memory = size_format(memory_get_usage(true));
            $entry .= " | Memory: {$memory}";
        }
        
        return $entry;
    }
    
    /**
     * Write log entry to file
     * 
     * @param string $context Log context
     * @param string $entry Log entry
     */
    private static function write_log($context, $entry) {
        $date = current_time('Y-m-d');
        $filename = "h3tm-{$context}-{$date}.log";
        $filepath = self::$log_dir . '/' . $filename;
        
        // Check if log rotation is needed
        if (file_exists($filepath) && filesize($filepath) > self::MAX_LOG_SIZE) {
            self::rotate_log($filepath);
        }
        
        // Write to log file
        file_put_contents($filepath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
        
        // Clean up old logs
        self::cleanup_old_logs($context);
    }
    
    /**
     * Rotate log file
     * 
     * @param string $filepath Current log file path
     */
    private static function rotate_log($filepath) {
        $timestamp = current_time('Y-m-d-His');
        $rotated_path = str_replace('.log', '-' . $timestamp . '.log', $filepath);
        rename($filepath, $rotated_path);
    }
    
    /**
     * Clean up old log files
     * 
     * @param string $context Log context
     */
    private static function cleanup_old_logs($context) {
        $pattern = self::$log_dir . "/h3tm-{$context}-*.log";
        $files = glob($pattern);
        
        if (count($files) <= self::MAX_LOG_FILES) {
            return;
        }
        
        // Sort by modification time
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Remove oldest files
        $files_to_remove = count($files) - self::MAX_LOG_FILES;
        for ($i = 0; $i < $files_to_remove; $i++) {
            unlink($files[$i]);
        }
    }
    
    /**
     * Get recent log entries
     * 
     * @param string $context Log context (optional)
     * @param string $level Minimum log level (optional)
     * @param int $limit Number of entries to return
     * @return array Log entries
     */
    public static function get_recent_logs($context = null, $level = null, $limit = 100) {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $entries = array();
        $pattern = self::$log_dir . '/h3tm-';
        
        if ($context) {
            $pattern .= $context . '-';
        } else {
            $pattern .= '*-';
        }
        
        $pattern .= '*.log';
        $files = glob($pattern);
        
        if (empty($files)) {
            return $entries;
        }
        
        // Sort files by modification time (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Read entries from files
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            if ($lines === false) {
                continue;
            }
            
            // Read lines in reverse order (newest first)
            for ($i = count($lines) - 1; $i >= 0 && count($entries) < $limit; $i--) {
                $line = $lines[$i];
                
                // Parse log level if filtering is needed
                if ($level && !self::entry_matches_level($line, $level)) {
                    continue;
                }
                
                $entries[] = $line;
            }
            
            if (count($entries) >= $limit) {
                break;
            }
        }
        
        return $entries;
    }
    
    /**
     * Check if log entry matches minimum level
     * 
     * @param string $entry Log entry
     * @param string $min_level Minimum level
     * @return bool
     */
    private static function entry_matches_level($entry, $min_level) {
        $levels = array(
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4
        );
        
        // Extract level from entry
        if (preg_match('/\[([A-Z]+)\]/', $entry, $matches)) {
            $entry_level = strtolower($matches[1]);
            $entry_level_num = $levels[$entry_level] ?? 1;
            $min_level_num = $levels[$min_level] ?? 1;
            
            return $entry_level_num >= $min_level_num;
        }
        
        return false;
    }
    
    /**
     * Clear all logs
     * 
     * @param string $context Specific context to clear (optional)
     */
    public static function clear_logs($context = null) {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $pattern = self::$log_dir . '/h3tm-';
        
        if ($context) {
            $pattern .= $context . '-';
        } else {
            $pattern .= '*-';
        }
        
        $pattern .= '*.log';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Get log statistics
     * 
     * @return array Statistics
     */
    public static function get_stats() {
        if (self::$log_dir === null) {
            self::init();
        }
        
        $stats = array(
            'total_files' => 0,
            'total_size' => 0,
            'by_context' => array(),
            'by_level' => array(
                self::LEVEL_DEBUG => 0,
                self::LEVEL_INFO => 0,
                self::LEVEL_WARNING => 0,
                self::LEVEL_ERROR => 0,
                self::LEVEL_CRITICAL => 0
            )
        );
        
        $files = glob(self::$log_dir . '/h3tm-*.log');
        $stats['total_files'] = count($files);
        
        foreach ($files as $file) {
            $stats['total_size'] += filesize($file);
            
            // Extract context from filename
            if (preg_match('/h3tm-([a-z]+)-/', basename($file), $matches)) {
                $context = $matches[1];
                if (!isset($stats['by_context'][$context])) {
                    $stats['by_context'][$context] = 0;
                }
                $stats['by_context'][$context]++;
            }
            
            // Count entries by level (sample last 100 lines)
            $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -100);
            foreach ($lines as $line) {
                foreach (array_keys($stats['by_level']) as $level) {
                    if (strpos($line, '[' . strtoupper($level) . ']') !== false) {
                        $stats['by_level'][$level]++;
                        break;
                    }
                }
            }
        }
        
        return $stats;
    }
}