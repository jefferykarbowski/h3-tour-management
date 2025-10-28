<?php
/**
 * Check Database for arden-pikesville Slug
 *
 * Usage: Upload to server and run via browser
 */

// Security check - only allow if logged in as admin
if (!defined('ABSPATH')) {
    require_once(__DIR__ . '/../../../wp-load.php');
}

if (!current_user_can('manage_options')) {
    die('Unauthorized: Admin access required');
}

echo "<pre>\n";
echo "========================================\n";
echo "ARDEN-PIKESVILLE SLUG INVESTIGATION\n";
echo "========================================\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'h3tm_tour_metadata';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if (!$table_exists) {
    echo "❌ ERROR: Table {$table_name} does not exist!\n";
    exit;
}

echo "✅ Table exists: {$table_name}\n\n";

// 1. Check for arden-pikesville slug
echo "--- CHECKING FOR 'arden-pikesville' SLUG ---\n";
$tours = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE tour_slug = %s",
    'arden-pikesville'
));

if (empty($tours)) {
    echo "✅ No tours found with slug 'arden-pikesville'\n\n";
} else {
    echo "🔍 Found " . count($tours) . " tour(s) with slug 'arden-pikesville':\n\n";
    foreach ($tours as $tour) {
        echo "Tour Details:\n";
        echo "  ID: {$tour->id}\n";
        echo "  Tour ID: {$tour->tour_id}\n";
        echo "  Display Name: {$tour->display_name}\n";
        echo "  Status: {$tour->status}\n";
        echo "  S3 Folder: {$tour->s3_folder}\n";
        echo "  Created: {$tour->created_date}\n";
        echo "  Updated: {$tour->updated_date}\n";
        echo "  URL History: {$tour->url_history}\n";
        echo "\n";
    }
}

// 2. Check for active tours with this slug
echo "--- CHECKING ACTIVE TOURS ONLY ---\n";
$active_tours = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE tour_slug = %s AND status NOT IN ('deleted', 'archived', 'failed')",
    'arden-pikesville'
));

if (empty($active_tours)) {
    echo "✅ No active tours with slug 'arden-pikesville'\n\n";
} else {
    echo "⚠️  Found " . count($active_tours) . " ACTIVE tour(s) blocking this slug:\n\n";
    foreach ($active_tours as $tour) {
        echo "BLOCKING TOUR:\n";
        echo "  ID: {$tour->id}\n";
        echo "  Tour ID: {$tour->tour_id}\n";
        echo "  Display Name: {$tour->display_name}\n";
        echo "  Status: {$tour->status}\n";
        echo "  S3 Folder: {$tour->s3_folder}\n\n";
    }
}

// 3. Check S3 to see if tour exists
echo "--- CHECKING S3 FOR TOUR ---\n";
if (class_exists('H3TM_S3_Simple')) {
    $s3 = new H3TM_S3_Simple();
    $s3_result = $s3->list_tours();

    if ($s3_result['success']) {
        $found_in_s3 = false;
        foreach ($s3_result['tours'] as $s3_tour) {
            if ($s3_tour['name'] === 'Arden-Pikesville' || stripos($s3_tour['name'], 'arden') !== false) {
                $found_in_s3 = true;
                echo "✅ Found in S3: {$s3_tour['name']}\n";
                echo "   Folder: {$s3_tour['folder']}\n\n";
            }
        }
        if (!$found_in_s3) {
            echo "❌ 'Arden-Pikesville' NOT found in S3\n\n";
        }
    } else {
        echo "❌ Could not list S3 tours: {$s3_result['message']}\n\n";
    }
} else {
    echo "⚠️  S3 class not available\n\n";
}

// 4. Summary of all tours in database
echo "--- DATABASE SUMMARY ---\n";
$total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "Total tours in database: {$total}\n\n";

$by_status = $wpdb->get_results("SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status");
echo "Tours by status:\n";
foreach ($by_status as $row) {
    echo "  {$row->status}: {$row->count}\n";
}

echo "\n========================================\n";
echo "RECOMMENDATION:\n";
echo "========================================\n";

if (!empty($tours) && empty($active_tours)) {
    echo "✅ Slug exists in database but NOT marked as active.\n";
    echo "   Safe to manually delete these entries.\n\n";
    echo "DELETE SQL:\n";
    echo "DELETE FROM {$table_name} WHERE tour_slug = 'arden-pikesville';\n";
} elseif (!empty($active_tours)) {
    echo "⚠️  ACTIVE tour is blocking this slug!\n";
    echo "   You need to either:\n";
    echo "   1. Delete the tour properly through admin UI\n";
    echo "   2. Update its status to 'deleted'\n";
    echo "   3. Manually remove from database\n";
}

echo "</pre>\n";
