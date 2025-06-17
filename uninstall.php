<?php
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Drop custom database table
global $wpdb;
$table_name = $wpdb->prefix . 'h3tm_user_settings';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Delete options
delete_option('h3tm_version');
delete_option('h3tm_tour_directory');
delete_option('h3tm_email_from_name');
delete_option('h3tm_email_from_address');

// Delete user meta
$users = get_users();
foreach ($users as $user) {
    delete_user_meta($user->ID, 'h3tm_tours');
}

// Clear scheduled hooks
wp_clear_scheduled_hook('h3tm_analytics_cron');