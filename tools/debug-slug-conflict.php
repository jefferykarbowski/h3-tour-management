<?php
/**
 * Debug Script: Check for slug conflicts in database
 *
 * Usage: Run this from WordPress admin or via WP-CLI
 * This will show ALL tours with a specific slug, including their status
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Check if we're in WordPress context
if (!function_exists('get_option')) {
    die('Error: WordPress not loaded. Run this from WordPress root or use WP-CLI.');
}

// Get the slug to check from command line or default
$check_slug = isset($argv[1]) ? $argv[1] : 'arden-pikesville';

echo "========================================\n";
echo "SLUG CONFLICT DEBUG TOOL\n";
echo "========================================\n";
echo "Checking slug: {$check_slug}\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'h3tm_tour_metadata';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if (!$table_exists) {
    echo "❌ ERROR: Table {$table_name} does not exist!\n";
    exit(1);
}

echo "✅ Table exists: {$table_name}\n\n";

// Get ALL tours with this slug (regardless of status)
echo "--- ALL TOURS WITH THIS SLUG ---\n";
$all_tours = $wpdb->get_results($wpdb->prepare(
    "SELECT id, tour_id, tour_slug, display_name, status, created_date, updated_date
     FROM {$table_name}
     WHERE tour_slug = %s",
    $check_slug
));

if (empty($all_tours)) {
    echo "✅ No tours found with slug '{$check_slug}'\n";
    echo "   This slug is available for use!\n\n";
} else {
    echo "Found " . count($all_tours) . " tour(s) with this slug:\n\n";
    foreach ($all_tours as $tour) {
        echo "Tour #{$tour->id}:\n";
        echo "  - Tour ID: {$tour->tour_id}\n";
        echo "  - Slug: {$tour->tour_slug}\n";
        echo "  - Display Name: {$tour->display_name}\n";
        echo "  - Status: {$tour->status}\n";
        echo "  - Created: {$tour->created_date}\n";
        echo "  - Updated: {$tour->updated_date}\n";
        echo "\n";
    }
}

// Get ACTIVE tours with this slug (what slug_exists checks)
echo "--- ACTIVE TOURS WITH THIS SLUG ---\n";
$active_tours = $wpdb->get_results($wpdb->prepare(
    "SELECT id, tour_id, tour_slug, display_name, status
     FROM {$table_name}
     WHERE tour_slug = %s
     AND status NOT IN ('deleted', 'archived', 'failed')",
    $check_slug
));

if (empty($active_tours)) {
    echo "✅ No ACTIVE tours found with slug '{$check_slug}'\n";
    echo "   slug_exists() should return FALSE\n\n";
} else {
    echo "❌ Found " . count($active_tours) . " ACTIVE tour(s):\n\n";
    foreach ($active_tours as $tour) {
        echo "Tour #{$tour->id}: {$tour->display_name} (Status: {$tour->status})\n";
    }
    echo "\n⚠️  slug_exists() will return TRUE - slug is blocked!\n\n";
}

// Show all tours in the table (summary)
echo "--- ALL TOURS IN DATABASE (Summary) ---\n";
$all_db_tours = $wpdb->get_results(
    "SELECT id, tour_id, tour_slug, display_name, status
     FROM {$table_name}
     ORDER BY updated_date DESC
     LIMIT 20"
);

echo "Total tours in database: " . $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") . "\n";
echo "Showing most recent 20:\n\n";
foreach ($all_db_tours as $tour) {
    $status_icon = in_array($tour->status, ['deleted', 'archived', 'failed']) ? '🗑️' : '✅';
    echo "{$status_icon} #{$tour->id}: {$tour->tour_slug} | {$tour->display_name} | Status: {$tour->status}\n";
}

echo "\n========================================\n";
echo "RECOMMENDATIONS\n";
echo "========================================\n";

if (!empty($all_tours) && empty($active_tours)) {
    echo "✅ There are deleted/archived tours with this slug\n";
    echo "   but they should NOT block reuse.\n";
    echo "   If you're still getting errors, check:\n";
    echo "   1. Clear all WordPress caches\n";
    echo "   2. Check browser console for errors\n";
    echo "   3. Look at PHP error logs\n";
} elseif (!empty($active_tours)) {
    echo "❌ There are ACTIVE tours blocking this slug!\n";
    echo "   Options:\n";
    echo "   1. Delete the conflicting tour properly\n";
    echo "   2. Change its slug first\n";
    echo "   3. Or manually update the database\n";
} else {
    echo "✅ Slug is completely free - should work!\n";
}

echo "\n";
