#!/usr/bin/env php
<?php
/**
 * Flush WordPress Rewrite Rules
 *
 * Run this after updating tour URL handlers to apply changes
 */

// WordPress environment
define('WP_USE_THEMES', false);

// Try to find wp-load.php (Local by Flywheel installation)
$wp_load_paths = [
    'C:/Users/Jeff/Local Sites/h3vt/app/public/wp-load.php',
    __DIR__ . '/../../../h3vt/wp-load.php',
    __DIR__ . '/../../../../Local Sites/h3vt/app/public/wp-load.php'
];

$wp_load = null;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        $wp_load = $path;
        break;
    }
}

if (!$wp_load) {
    die("ERROR: Could not find wp-load.php. Please run this from WordPress admin instead:\n" .
        "Go to Settings > Permalinks and click 'Save Changes' to flush rewrite rules.\n");
}

require_once($wp_load);

echo "=== Flushing WordPress Rewrite Rules ===\n\n";

// Flush rewrite rules
flush_rewrite_rules();

echo "✅ Rewrite rules flushed successfully!\n";
echo "✅ Tour URL handlers updated to support tour_id format\n\n";

echo "Next steps:\n";
echo "1. Try clicking the tour link again\n";
echo "2. If still not working, check error logs for 'H3TM S3 Proxy' messages\n\n";
