<?php
/**
 * Plugin Name: H3 Tour Management
 * Plugin URI: https://github.com/jefferykarbowski/h3-tour-management
 * Description: Comprehensive 3D Tour Management system with analytics, email notifications, and user management
 * Version: 1.7.0
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
define('H3TM_VERSION', '1.7.0');
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
// Include S3 proxy for URL rewriting (CRITICAL FIX)
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-s3-proxy.php';
// Only include simple S3 integration to avoid dependency issues
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-s3-simple.php';
// Use simplified analytics display
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-shortcodes-v4.php';

// Alternative URL handling approaches (rewrite-rule independent)
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-404-handler.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-direct-handler.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-action-hook.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-endpoint-handler.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-url-manager.php';

// Include robust tour URL handling system
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-tour-url-handler.php';
require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-tour-url-diagnostics.php';

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
    // CRITICAL FIX: Instantiate S3 Proxy for rewrite rules
    new H3TM_S3_Proxy();
    new H3TM_S3_Simple();
    new H3TM_Shortcodes_V4();

    // Initialize URL manager (handles all alternative URL approaches)
    new H3TM_URL_Manager();

    // Initialize robust tour URL handling (highest priority)
    new H3TM_Tour_URL_Handler();
    new H3TM_Tour_URL_Diagnostics();
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
