# Integration Guide - H3 Tour Management Backend Optimizations

## Overview

This guide provides step-by-step instructions for integrating the optimized tour rename functionality into the existing H3 Tour Management plugin.

## Pre-Integration Checklist

### 1. Backup Current System
```bash
# Backup database
mysqldump -u username -p database_name > h3tm_backup_$(date +%Y%m%d).sql

# Backup plugin files
tar -czf h3tm_plugin_backup_$(date +%Y%m%d).tar.gz /path/to/wp-content/plugins/h3-tour-management/

# Backup tour directory
tar -czf h3tm_tours_backup_$(date +%Y%m%d).tar.gz /path/to/tours/directory/
```

### 2. Environment Requirements
- **WordPress**: 5.0+
- **PHP**: 7.4+
- **MySQL**: 5.6+
- **Memory Limit**: 256MB+ recommended
- **Execution Time**: 300s+ recommended for large operations

### 3. Testing Environment
- Set up staging environment identical to production
- Install test tours of various sizes (small, medium, large)
- Verify current performance benchmarks

## Phase 1: Core Optimization Files Integration

### Step 1: Add Optimized Tour Manager Class

1. **Copy the optimized class file**:
```bash
cp includes/class-h3tm-tour-manager-optimized.php /path/to/plugin/includes/
```

2. **Include in main plugin file** (`h3-tour-management.php`):
```php
// Add after existing includes
require_once plugin_dir_path(__FILE__) . 'includes/class-h3tm-tour-manager-optimized.php';
```

3. **Update class loading** in plugin initialization:
```php
// In main plugin class or initialization function
if (class_exists('H3TM_Tour_Manager_Optimized')) {
    // Use optimized version
    add_action('plugins_loaded', array('H3TM_Tour_Manager_Optimized', 'cleanup_expired_progress'));
}
```

### Step 2: Add Optimized Admin Class

1. **Copy the optimized admin class**:
```bash
cp includes/class-h3tm-admin-optimized.php /path/to/plugin/includes/
```

2. **Include in main plugin file**:
```php
// Add after other admin includes
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-h3tm-admin-optimized.php';
}
```

3. **Update admin initialization**:
```php
// Replace existing admin class initialization
if (is_admin()) {
    $admin_class = class_exists('H3TM_Admin_Optimized') ? 'H3TM_Admin_Optimized' : 'H3TM_Admin_V2';
    new $admin_class();
}
```

### Step 3: Add Optimized JavaScript

1. **Copy the JavaScript file**:
```bash
cp assets/js/admin-optimized.js /path/to/plugin/assets/js/
```

2. **Verify script enqueuing** (already handled by `H3TM_Admin_Optimized::enqueue_admin_scripts`)

### Step 4: Update Plugin Settings

Add new settings for optimization features in the settings registration:

```php
// In class-h3tm-admin-optimized.php or existing settings
register_setting('h3tm_general_settings', 'h3tm_use_optimized_operations', array(
    'type' => 'boolean',
    'default' => true,
    'description' => 'Use optimized tour operations for better performance'
));

register_setting('h3tm_general_settings', 'h3tm_chunk_threshold', array(
    'type' => 'integer',
    'default' => 100,
    'description' => 'File count threshold for chunked processing'
));

register_setting('h3tm_general_settings', 'h3tm_max_execution_time', array(
    'type' => 'integer',
    'default' => 60,
    'description' => 'Maximum execution time for operations (seconds)'
));
```

## Phase 2: Database Schema Updates

### Step 1: Add Progress Tracking Support

The optimized version uses WordPress transients for progress tracking, so no database schema changes are required. However, you can optionally add a dedicated operations table:

```sql
-- Optional: Create operations tracking table
CREATE TABLE `{$wpdb->prefix}h3tm_operations` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `operation_id` varchar(255) NOT NULL,
    `operation_type` varchar(50) NOT NULL,
    `target` varchar(255) DEFAULT NULL,
    `status` varchar(20) DEFAULT 'pending',
    `progress` tinyint(3) DEFAULT 0,
    `message` text DEFAULT NULL,
    `user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `completed_at` datetime DEFAULT NULL,
    `error_message` text DEFAULT NULL,
    `context_data` longtext DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `operation_id` (`operation_id`),
    KEY `status_started` (`status`, `started_at`),
    KEY `user_operations` (`user_id`, `operation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 2: Update Database Migration

Add to your existing database migration function:

```php
// In class-h3tm-database.php migrate() method
if (version_compare($current_version, '2.1.0', '<')) {
    self::migrate_to_2_1_0();
}

/**
 * Migration to version 2.1.0
 */
private static function migrate_to_2_1_0() {
    // Add optimization settings with defaults
    add_option('h3tm_use_optimized_operations', '1');
    add_option('h3tm_chunk_threshold', '100');
    add_option('h3tm_max_execution_time', '60');

    H3TM_Logger::info('database', 'Migrated to version 2.1.0 with optimization features');
}
```

## Phase 3: Configuration and Testing

### Step 1: Configure Optimization Settings

1. **Add settings page section** in admin templates:
```php
// Add to existing settings page template
<h3><?php _e('Performance Optimization', 'h3-tour-management'); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row"><?php _e('Use Optimized Operations', 'h3-tour-management'); ?></th>
        <td>
            <label>
                <input type="checkbox" name="h3tm_use_optimized_operations" value="1"
                       <?php checked(get_option('h3tm_use_optimized_operations', '1')); ?> />
                <?php _e('Enable optimized tour operations for better performance', 'h3-tour-management'); ?>
            </label>
            <p class="description">
                <?php _e('Recommended for sites with large tours. Provides progress tracking and better timeout handling.', 'h3-tour-management'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php _e('Chunked Processing Threshold', 'h3-tour-management'); ?></th>
        <td>
            <input type="number" name="h3tm_chunk_threshold"
                   value="<?php echo esc_attr(get_option('h3tm_chunk_threshold', '100')); ?>"
                   min="50" max="1000" />
            <p class="description">
                <?php _e('File count threshold for using chunked processing (recommended: 100)', 'h3-tour-management'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php _e('Maximum Execution Time', 'h3-tour-management'); ?></th>
        <td>
            <input type="number" name="h3tm_max_execution_time"
                   value="<?php echo esc_attr(get_option('h3tm_max_execution_time', '60')); ?>"
                   min="30" max="300" />
            <p class="description">
                <?php _e('Maximum time for operations in seconds (server limits may apply)', 'h3-tour-management'); ?>
            </p>
        </td>
    </tr>
</table>
```

### Step 2: Initial Testing

1. **Enable optimization features**:
```php
// Temporarily enable in wp-config.php for testing
define('H3TM_FORCE_OPTIMIZED', true);
```

2. **Test with small tour first**:
   - Create a test tour with 10-50 files
   - Attempt rename using optimized method
   - Verify progress tracking works
   - Check error handling

3. **Test with medium tour**:
   - Create a test tour with 100-300 files
   - Monitor progress updates in browser console
   - Verify completion success

### Step 3: Performance Validation

Run the performance testing protocol from `performance-testing-protocol.md`:

```bash
# Run baseline tests
./test-baseline-performance.sh

# Run optimized tests
./test-optimized-performance.sh

# Compare results
./compare-performance-results.sh
```

## Phase 4: Production Deployment

### Step 1: Gradual Rollout

1. **Feature Flag Implementation**:
```php
// Add feature flag check
function h3tm_use_optimized_operations() {
    // Check user preference first
    $user_enabled = get_option('h3tm_use_optimized_operations', '1') === '1';

    // Check for override
    if (defined('H3TM_FORCE_OPTIMIZED')) {
        return H3TM_FORCE_OPTIMIZED;
    }

    return $user_enabled;
}
```

2. **Selective Enablement**:
   - Start with admin users only
   - Enable for specific user roles
   - Gradually enable for all users

### Step 2: Monitoring Setup

1. **Add performance logging**:
```php
// In optimized tour manager
private function log_performance_metrics($operation_id, $metrics) {
    H3TM_Logger::info('performance', 'Operation completed', array(
        'operation_id' => $operation_id,
        'duration' => $metrics['duration'],
        'file_count' => $metrics['file_count'],
        'memory_peak' => $metrics['memory_peak'],
        'method' => $metrics['method'] // 'simple' or 'chunked'
    ));
}
```

2. **Setup monitoring alerts**:
```php
// Add to cron job or monitoring system
if ($operation_duration > 60) {
    wp_mail(
        get_option('admin_email'),
        'H3TM Long Operation Alert',
        "Operation {$operation_id} took {$operation_duration} seconds"
    );
}
```

### Step 3: User Communication

1. **Add admin notice for new features**:
```php
// In admin class
public function show_optimization_notice() {
    if (get_option('h3tm_optimization_notice_dismissed') !== '1') {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p>' . __('H3 Tour Management now includes performance optimizations for large tour operations!', 'h3-tour-management') . '</p>';
        echo '</div>';
    }
}
```

2. **Update user documentation**:
   - Add section about new progress tracking
   - Explain improved timeout handling
   - Document troubleshooting steps

## Phase 5: Maintenance and Optimization

### Step 1: Performance Monitoring

Set up automated monitoring:

```php
// Add to WP-Cron
wp_schedule_event(time(), 'hourly', 'h3tm_cleanup_expired_progress');

add_action('h3tm_cleanup_expired_progress', function() {
    H3TM_Tour_Manager_Optimized::cleanup_expired_progress();
});
```

### Step 2: Error Handling Improvements

Monitor error logs and improve based on real-world usage:

```php
// Add comprehensive error tracking
add_action('wp_ajax_h3tm_rename_tour_optimized', function() {
    try {
        // ... existing code
    } catch (Exception $e) {
        // Enhanced error reporting
        H3TM_Logger::error('operation', 'Rename failed', array(
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'server_load' => sys_getloadavg()
        ));

        // Send error to monitoring system
        wp_remote_post('your-monitoring-endpoint', array(
            'body' => json_encode(array(
                'type' => 'h3tm_operation_error',
                'message' => $e->getMessage(),
                'context' => array(
                    'plugin_version' => H3TM_VERSION,
                    'wp_version' => get_bloginfo('version'),
                    'php_version' => PHP_VERSION
                )
            ))
        ));

        wp_send_json_error(array(
            'code' => 'operation_failed',
            'message' => $e->getMessage()
        ));
    }
});
```

### Step 3: Continuous Optimization

1. **Monitor performance metrics**:
   - Track average operation times
   - Monitor success rates
   - Identify bottlenecks

2. **Update thresholds based on data**:
```php
// Dynamic threshold adjustment
function h3tm_get_optimal_chunk_size($file_count, $total_size) {
    // Adjust based on historical performance data
    if ($total_size > 100 * 1024 * 1024) { // 100MB
        return max(50, min(200, intval($file_count / 20)));
    }

    return get_option('h3tm_chunk_threshold', 100);
}
```

## Troubleshooting Guide

### Common Issues and Solutions

1. **Progress tracking not working**:
   - Check transient storage (object cache issues)
   - Verify AJAX security nonces
   - Check JavaScript console for errors

2. **Operations still timing out**:
   - Increase PHP max_execution_time
   - Lower chunk threshold
   - Check server resource limits

3. **Database errors during rename**:
   - Check MySQL connection limits
   - Verify transaction isolation level
   - Monitor for deadlocks

4. **Frontend not showing progress**:
   - Verify admin-optimized.js is loaded
   - Check for JavaScript conflicts
   - Ensure AJAX endpoints are accessible

### Rollback Plan

If issues occur, quick rollback procedure:

1. **Disable optimizations**:
```php
// In wp-config.php
define('H3TM_DISABLE_OPTIMIZED', true);
```

2. **Revert to previous version**:
```bash
# Restore backup
tar -xzf h3tm_plugin_backup_YYYYMMDD.tar.gz
```

3. **Clear any cached data**:
```php
// Clear transients
delete_transient('h3tm_progress_*');

// Clear object cache if using external cache
wp_cache_flush();
```

This integration guide ensures smooth deployment of the optimization features while maintaining system stability and providing clear rollback options.