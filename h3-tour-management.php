<?php
/**
 * Plugin Name: H3 Tour Management
 * Plugin URI: https://github.com/jefferykarbowski/h3-tour-management
 * Description: Cloud-based Tour Management system with S3/CloudFront delivery, analytics, and user management
 * Version: 2.4.7
 * Author: H3 Photography
 * Author URI: https://h3vt.com/
 * License: GPL v2 or later
 * Text Domain: h3-tour-management
 * GitHub Plugin URI: https://github.com/jefferykarbowski/h3-tour-management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('H3TM_VERSION', '2.4.7');
define('H3TM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('H3TM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('H3TM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('H3TM_TOUR_DIR', 'h3panos');

// Include required files
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-activator.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-analytics.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-admin.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-user-fields.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-email.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-tour-manager.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-pantheon-helper.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-cron-analytics.php';
// Simple S3 integration only - no complex dependencies
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-cdn-helper.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-s3-simple.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-s3-proxy.php';
require_once H3TM_PLUGIN_DIR . 'admin/s3-settings.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-analytics-endpoint.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-shortcodes-v4.php';

// Activation hook
register_activation_hook(__FILE__, array('H3TM_Activator', 'activate'));

// Deactivation hook
register_deactivation_hook(__FILE__, array('H3TM_Activator', 'deactivate'));

// Initialize plugin
add_action('plugins_loaded', 'h3tm_init');
function h3tm_init() {
    // Initialize components
    new H3TM_Admin();
    new H3TM_User_Fields();
    new H3TM_Analytics();
    new H3TM_Email();
    new H3TM_Tour_Manager();
    new H3TM_S3_Simple();
    new H3TM_S3_Proxy();
    new H3TM_S3_Settings();
    new H3TM_Analytics_Endpoint();
    new H3TM_Shortcodes_V4();
}

// Disable new user notification emails (moved from functions.php)
if (!function_exists('wp_new_user_notification')) {
    function wp_new_user_notification($user_id, $deprecated = null, $notify = '') {
        return;
    }
}



require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Read the plugin file to extract the Plugin URI
$plugin_data = get_file_data(__FILE__, array('PluginURI' => 'Plugin URI'));
$plugin_uri = $plugin_data['PluginURI'];

// Parse the Plugin URI to get the slug
$plugin_slug = basename(parse_url($plugin_uri, PHP_URL_PATH));

$myUpdateChecker = PucFactory::buildUpdateChecker(
	$plugin_uri,
	__FILE__,
	$plugin_slug
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');
