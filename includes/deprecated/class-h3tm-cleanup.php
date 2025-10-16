<?php
/**
 * Cleanup Service for H3 Tour Management
 * 
 * Handles cleanup of orphaned files, old logs, and temporary data
 * 
 * @package H3_Tour_Management
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Cleanup {
    
    /**
     * Run cleanup tasks
     * 
     * @param bool $force Force cleanup regardless of settings
     * @return array Cleanup results
     */
    public static function run($force = false) {
        $results = array(
            'temp_files' => 0,
            'orphaned_files' => 0,
            'old_logs' => 0,
            'old_cache' => 0,
            'old_activity' => 0,
            'total_size' => 0,
            'errors' => array()
        );
        
        // Check if cleanup is enabled
        if (!$force && !get_option('h3tm_cleanup_enabled', true)) {
            return $results;
        }
        
        H3TM_Logger::info('cleanup', 'Starting cleanup process');
        
        // Clean temporary upload files
        $results['temp_files'] = self::clean_temp_files();
        
        // Clean orphaned tour files
        $results['orphaned_files'] = self::clean_orphaned_tours();
        
        // Clean old logs
        $results['old_logs'] = self::clean_old_logs();
        
        // Clean database
        self::clean_database();
        
        // Calculate total cleaned size
        $results['total_size'] = array_sum(array(
            $results['temp_files']['size'] ?? 0,
            $results['orphaned_files']['size'] ?? 0,
            $results['old_logs']['size'] ?? 0
        ));
        
        H3TM_Logger::info('cleanup', 'Cleanup completed', $results);
        
        return $results;
    }
    
    /**
     * Clean temporary upload files
     * 
     * @return array Cleanup results
     */
    private static function clean_temp_files() {
        $result = array(
            'count' => 0,
            'size' => 0
        );
        
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/h3-tours-temp';
        
        if (!file_exists($temp_dir)) {
            return $result;
        }
        
        // Get age threshold (default 24 hours)
        $max_age = get_option('h3tm_temp_file_age', 24) * 3600;
        $threshold = time() - $max_age;
        
        // Scan temp directory
        $dirs = scandir($temp_dir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            
            $dir_path = $temp_dir . '/' . $dir;
            
            if (is_dir($dir_path)) {
                $dir_time = filemtime($dir_path);
                
                // Check if directory is old enough
                if ($dir_time < $threshold) {
                    $size = self::get_directory_size($dir_path);
                    
                    if (self::delete_directory($dir_path)) {
                        $result['count']++;
                        $result['size'] += $size;
                        
                        H3TM_Logger::debug('cleanup', 'Removed temp directory', array(
                            'path' => $dir,
                            'age' => time() - $dir_time,
                            'size' => $size
                        ));
                    }
                }
            }
        }
        
        // Also clean individual temp files
        $temp_files = glob($upload_dir['basedir'] . '/h3-tours/*.tmp');
        foreach ($temp_files as $file) {
            if (filemtime($file) < $threshold) {
                $size = filesize($file);
                if (unlink($file)) {
                    $result['count']++;
                    $result['size'] += $size;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Clean orphaned tour files
     * 
     * @return array Cleanup results
     */
    private static function clean_orphaned_tours() {
        $result = array(
            'count' => 0,
            'size' => 0
        );
        
        // Get list of tours assigned to users
        $assigned_tours = array();
        $users = get_users(array(
            'meta_key' => 'h3tm_tours',
            'meta_compare' => 'EXISTS'
        ));
        
        foreach ($users as $user) {
            $user_tours = get_user_meta($user->ID, 'h3tm_tours', true);
            if (is_array($user_tours)) {
                $assigned_tours = array_merge($assigned_tours, $user_tours);
            }
        }
        
        $assigned_tours = array_unique($assigned_tours);
        
        // Get tours in filesystem
        $tour_manager = new H3TM_Tour_Manager_V2();
        $all_tours = $tour_manager->get_all_tours(array('include_meta' => false));
        
        // Find orphaned tours
        foreach ($all_tours as $tour) {
            $should_keep = false;
            
            // Check if tour is assigned
            if (in_array($tour['name'], $assigned_tours)) {
                $should_keep = true;
            }
            
            // Check if tour has recent activity
            if (!$should_keep) {
                $last_activity = H3TM_Database::get_tour_meta($tour['name'], 'last_accessed');
                if ($last_activity) {
                    $days_old = (time() - strtotime($last_activity)) / 86400;
                    $max_orphan_days = get_option('h3tm_orphan_tour_days', 90);
                    
                    if ($days_old < $max_orphan_days) {
                        $should_keep = true;
                    }
                }
            }
            
            // Delete orphaned tour
            if (!$should_keep) {
                $size = $tour['size'];
                $tour_result = $tour_manager->delete_tour($tour['name']);
                
                if ($tour_result['success']) {
                    $result['count']++;
                    $result['size'] += $size;
                    
                    H3TM_Logger::info('cleanup', 'Removed orphaned tour', array(
                        'tour' => $tour['name'],
                        'size' => $size
                    ));
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Clean old log files
     * 
     * @return array Cleanup results
     */
    private static function clean_old_logs() {
        $result = array(
            'count' => 0,
            'size' => 0
        );
        
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/h3tm-logs';
        
        if (!file_exists($log_dir)) {
            return $result;
        }
        
        // Get age threshold (default 30 days)
        $max_age = get_option('h3tm_log_retention_days', 30) * 86400;
        $threshold = time() - $max_age;
        
        // Scan log files
        $logs = glob($log_dir . '/*.log');
        foreach ($logs as $log_file) {
            if (filemtime($log_file) < $threshold) {
                $size = filesize($log_file);
                if (unlink($log_file)) {
                    $result['count']++;
                    $result['size'] += $size;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Clean database tables
     */
    private static function clean_database() {
        global $wpdb;
        
        // Clean old analytics cache
        $cache_days = get_option('h3tm_cache_retention_days', 30);
        $table_cache = $wpdb->prefix . 'h3tm_analytics_cache';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_cache WHERE expiration < %s",
            date('Y-m-d H:i:s', strtotime("-{$cache_days} days"))
        ));
        
        if ($deleted > 0) {
            H3TM_Logger::debug('cleanup', 'Cleaned analytics cache', array(
                'rows' => $deleted
            ));
        }
        
        // Clean old activity logs
        $activity_days = get_option('h3tm_activity_retention_days', 90);
        $table_activity = $wpdb->prefix . 'h3tm_activity_log';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_activity WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime("-{$activity_days} days"))
        ));
        
        if ($deleted > 0) {
            H3TM_Logger::debug('cleanup', 'Cleaned activity log', array(
                'rows' => $deleted
            ));
        }
        
        // Clean processed email queue
        $email_days = get_option('h3tm_email_retention_days', 7);
        $table_email = $wpdb->prefix . 'h3tm_email_queue';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_email 
             WHERE status IN ('sent', 'failed') 
             AND created_at < %s",
            date('Y-m-d H:i:s', strtotime("-{$email_days} days"))
        ));
        
        if ($deleted > 0) {
            H3TM_Logger::debug('cleanup', 'Cleaned email queue', array(
                'rows' => $deleted
            ));
        }
        
        // Clean orphaned tour metadata
        self::clean_orphaned_metadata();
        
        // Optimize tables
        self::optimize_tables();
    }
    
    /**
     * Clean orphaned tour metadata
     */
    private static function clean_orphaned_metadata() {
        global $wpdb;
        
        // Get existing tours
        $tour_manager = new H3TM_Tour_Manager_V2();
        $tours = $tour_manager->get_all_tours(array('include_meta' => false));
        $tour_names = array_column($tours, 'name');
        
        if (empty($tour_names)) {
            return;
        }
        
        // Delete metadata for non-existent tours
        $table = $wpdb->prefix . 'h3tm_tour_meta';
        $placeholders = array_fill(0, count($tour_names), '%s');
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE tour_name NOT IN (" . implode(',', $placeholders) . ")",
            ...$tour_names
        ));
        
        if ($deleted > 0) {
            H3TM_Logger::debug('cleanup', 'Cleaned orphaned tour metadata', array(
                'rows' => $deleted
            ));
        }
    }
    
    /**
     * Optimize database tables
     */
    private static function optimize_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'h3tm_user_settings',
            $wpdb->prefix . 'h3tm_analytics_cache',
            $wpdb->prefix . 'h3tm_activity_log',
            $wpdb->prefix . 'h3tm_tour_meta',
            $wpdb->prefix . 'h3tm_email_queue'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
    }
    
    /**
     * Schedule cleanup cron job
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('h3tm_cleanup_cron')) {
            $schedule = get_option('h3tm_cleanup_schedule', 'daily');
            wp_schedule_event(time(), $schedule, 'h3tm_cleanup_cron');
        }
    }
    
    /**
     * Unschedule cleanup cron job
     */
    public static function unschedule_cleanup() {
        $timestamp = wp_next_scheduled('h3tm_cleanup_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'h3tm_cleanup_cron');
        }
    }
    
    /**
     * Get directory size recursively
     * 
     * @param string $dir Directory path
     * @return int Size in bytes
     */
    private static function get_directory_size($dir) {
        $size = 0;
        
        if (is_dir($dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        
        return $size;
    }
    
    /**
     * Delete directory recursively
     * 
     * @param string $dir Directory path
     * @return bool Success
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Get cleanup statistics
     * 
     * @return array Statistics
     */
    public static function get_stats() {
        $stats = array(
            'temp_files' => array(),
            'log_files' => array(),
            'database' => array(),
            'next_cleanup' => null
        );
        
        $upload_dir = wp_upload_dir();
        
        // Temp files stats
        $temp_dir = $upload_dir['basedir'] . '/h3-tours-temp';
        if (file_exists($temp_dir)) {
            $temp_dirs = glob($temp_dir . '/*', GLOB_ONLYDIR);
            $stats['temp_files']['count'] = count($temp_dirs);
            $stats['temp_files']['size'] = 0;
            
            foreach ($temp_dirs as $dir) {
                $stats['temp_files']['size'] += self::get_directory_size($dir);
            }
        }
        
        // Log files stats
        $log_dir = $upload_dir['basedir'] . '/h3tm-logs';
        if (file_exists($log_dir)) {
            $log_files = glob($log_dir . '/*.log');
            $stats['log_files']['count'] = count($log_files);
            $stats['log_files']['size'] = 0;
            
            foreach ($log_files as $file) {
                $stats['log_files']['size'] += filesize($file);
            }
        }
        
        // Database stats
        $stats['database'] = H3TM_Database::get_stats();
        
        // Next cleanup
        $next = wp_next_scheduled('h3tm_cleanup_cron');
        if ($next) {
            $stats['next_cleanup'] = array(
                'timestamp' => $next,
                'human' => human_time_diff($next)
            );
        }
        
        return $stats;
    }
}