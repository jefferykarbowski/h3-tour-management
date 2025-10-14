<?php
/**
 * Debug React Integration
 *
 * Upload this to your WordPress root and access it via:
 * https://your-site.pantheonsite.io/debug-react-integration.php
 */

// Bootstrap WordPress
require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    die('You must be an administrator to run this debug script.');
}

header('Content-Type: text/plain');

echo "=== H3 Tour Management React Integration Debug ===\n\n";

// Check plugin version
echo "1. Plugin Version Check:\n";
if (defined('H3TM_VERSION')) {
    echo "   ✓ H3TM_VERSION: " . H3TM_VERSION . "\n";
} else {
    echo "   ✗ H3TM_VERSION not defined\n";
}
echo "\n";

// Check if React classes exist
echo "2. React Class Files Check:\n";
$uploader_class = H3TM_PLUGIN_DIR . 'includes/class-h3tm-react-uploader.php';
$table_class = H3TM_PLUGIN_DIR . 'includes/class-h3tm-react-tours-table.php';

if (file_exists($uploader_class)) {
    echo "   ✓ class-h3tm-react-uploader.php exists\n";
    echo "     Size: " . filesize($uploader_class) . " bytes\n";
    echo "     Modified: " . date('Y-m-d H:i:s', filemtime($uploader_class)) . "\n";
} else {
    echo "   ✗ class-h3tm-react-uploader.php NOT FOUND\n";
}

if (file_exists($table_class)) {
    echo "   ✓ class-h3tm-react-tours-table.php exists\n";
    echo "     Size: " . filesize($table_class) . " bytes\n";
    echo "     Modified: " . date('Y-m-d H:i:s', filemtime($table_class)) . "\n";
} else {
    echo "   ✗ class-h3tm-react-tours-table.php NOT FOUND\n";
}
echo "\n";

// Check if classes are loaded
echo "3. React Classes Loaded:\n";
if (class_exists('H3TM_React_Uploader')) {
    echo "   ✓ H3TM_React_Uploader class loaded\n";
} else {
    echo "   ✗ H3TM_React_Uploader class NOT loaded\n";
}

if (class_exists('H3TM_React_Tours_Table')) {
    echo "   ✓ H3TM_React_Tours_Table class loaded\n";
} else {
    echo "   ✗ H3TM_React_Tours_Table class NOT loaded\n";
}
echo "\n";

// Check trait file
echo "4. Trait File Check:\n";
$trait_file = H3TM_PLUGIN_DIR . 'includes/traits/trait-h3tm-page-renderers.php';
if (file_exists($trait_file)) {
    echo "   ✓ trait-h3tm-page-renderers.php exists\n";
    echo "     Size: " . filesize($trait_file) . " bytes\n";
    echo "     Modified: " . date('Y-m-d H:i:s', filemtime($trait_file)) . "\n";

    // Check if it contains React calls
    $trait_content = file_get_contents($trait_file);
    if (strpos($trait_content, 'H3TM_React_Uploader::render_uploader') !== false) {
        echo "   ✓ Contains H3TM_React_Uploader::render_uploader() call\n";
    } else {
        echo "   ✗ Does NOT contain H3TM_React_Uploader::render_uploader() call\n";
    }

    if (strpos($trait_content, 'H3TM_React_Tours_Table::render_table') !== false) {
        echo "   ✓ Contains H3TM_React_Tours_Table::render_table() call\n";
    } else {
        echo "   ✗ Does NOT contain H3TM_React_Tours_Table::render_table() call\n";
    }
} else {
    echo "   ✗ trait-h3tm-page-renderers.php NOT FOUND\n";
}
echo "\n";

// Check asset files
echo "5. React Asset Files Check:\n";
$dist_path = H3TM_PLUGIN_DIR . 'assets/dist/';
$assets = [
    'tour-uploader.js',
    'tours-table.js',
    'index.css',
    'chunks/index-Gzz08Kjg.js'
];

foreach ($assets as $asset) {
    $file_path = $dist_path . $asset;
    if (file_exists($file_path)) {
        echo "   ✓ {$asset} exists (" . round(filesize($file_path)/1024, 2) . " KB)\n";
    } else {
        echo "   ✗ {$asset} NOT FOUND\n";
    }
}
echo "\n";

// Check main plugin file
echo "6. Main Plugin File Check:\n";
$main_file = H3TM_PLUGIN_DIR . 'h3-tour-management.php';
$main_content = file_get_contents($main_file);

if (strpos($main_content, "require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-react-uploader.php';") !== false) {
    echo "   ✓ Requires class-h3tm-react-uploader.php\n";
} else {
    echo "   ✗ Does NOT require class-h3tm-react-uploader.php\n";
}

if (strpos($main_content, "require_once H3TM_PLUGIN_DIR . 'includes/class-h3tm-react-tours-table.php';") !== false) {
    echo "   ✓ Requires class-h3tm-react-tours-table.php\n";
} else {
    echo "   ✗ Does NOT require class-h3tm-react-tours-table.php\n";
}
echo "\n";

// OPcache status
echo "7. PHP OPcache Status:\n";
if (function_exists('opcache_get_status')) {
    $opcache_status = opcache_get_status();
    if ($opcache_status !== false) {
        echo "   OPcache enabled: " . ($opcache_status['opcache_enabled'] ? 'Yes' : 'No') . "\n";
        echo "   Cached scripts: " . $opcache_status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "   Memory used: " . round($opcache_status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
    } else {
        echo "   OPcache is disabled\n";
    }
} else {
    echo "   OPcache not available\n";
}

echo "\n=== End Debug Report ===\n";
