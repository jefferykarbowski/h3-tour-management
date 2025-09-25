<?php
/**
 * Plugin Name: H3 Tour Management
 * Plugin URI: https://github.com/jefferykarbowski/h3-tour-management
 * Description: Comprehensive 3D Tour Management system with analytics, email notifications, and user management
 * Version: 1.5.1
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
define('H3TM_VERSION', '1.5.1');
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
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-s3-integration.php';
// Use simplified analytics display
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
    new H3TM_S3_Integration();
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

