<?php
/**
 * Cleanup Script: Remove orphaned database entries for deleted tours
 *
 * This script finds and removes database entries for tours that:
 * 1. Don't exist in S3 anymore
 * 2. Are blocking slug reuse
 *
 * Usage:
 * - Via browser: /wp-content/plugins/h3-tour-management/tools/cleanup-orphaned-slugs.php
 * - Via WP-CLI: wp eval-file cleanup-orphaned-slugs.php
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
echo "ORPHANED SLUG CLEANUP TOOL\n";
echo "========================================\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'h3tm_tour_metadata';

// Get all tours from database
$db_tours = $wpdb->get_results("SELECT * FROM {$table_name}");
echo "Found " . count($db_tours) . " tours in database\n\n";

// Check if S3 is configured
if (!class_exists('H3TM_S3_Simple')) {
    echo "❌ ERROR: H3TM_S3_Simple class not found\n";
    exit;
}

$s3 = new H3TM_S3_Simple();

// Get tours from S3
echo "Fetching tours from S3...\n";
$s3_result = $s3->list_tours();

if (!$s3_result['success']) {
    echo "❌ ERROR: Could not list S3 tours: " . $s3_result['message'] . "\n";
    exit;
}

$s3_tours = array();
foreach ($s3_result['tours'] as $tour) {
    $s3_tours[] = $tour['name']; // This is the tour_id
}

echo "Found " . count($s3_tours) . " tours in S3\n\n";

// Find orphaned tours
echo "--- CHECKING FOR ORPHANED DATABASE ENTRIES ---\n";
$orphaned = array();
$dry_run = !isset($_GET['execute']); // Safe by default

foreach ($db_tours as $tour) {
    // Check if tour exists in S3
    if (!in_array($tour->tour_id, $s3_tours)) {
        $orphaned[] = $tour;
        echo "🗑️  ORPHANED: {$tour->tour_slug} | {$tour->display_name} | Status: {$tour->status}\n";
        echo "    Tour ID: {$tour->tour_id} (Not found in S3)\n";
    }
}

if (empty($orphaned)) {
    echo "✅ No orphaned tours found!\n";
} else {
    echo "\n";
    echo "Found " . count($orphaned) . " orphaned database entries\n\n";

    if ($dry_run) {
        echo "========================================\n";
        echo "DRY RUN MODE (No changes made)\n";
        echo "========================================\n";
        echo "To actually delete these entries, add ?execute to the URL\n";
        echo "Example: cleanup-orphaned-slugs.php?execute\n\n";

        echo "WOULD DELETE:\n";
        foreach ($orphaned as $tour) {
            echo "  - ID #{$tour->id}: {$tour->tour_slug} ({$tour->display_name})\n";
        }
    } else {
        echo "========================================\n";
        echo "EXECUTING CLEANUP\n";
        echo "========================================\n";

        foreach ($orphaned as $tour) {
            $result = $wpdb->delete(
                $table_name,
                array('id' => $tour->id),
                array('%d')
            );

            if ($result) {
                echo "✅ DELETED: ID #{$tour->id}: {$tour->tour_slug} ({$tour->display_name})\n";
            } else {
                echo "❌ FAILED: ID #{$tour->id}: {$tour->tour_slug}\n";
            }
        }

        echo "\n✅ Cleanup complete!\n";
    }
}

echo "\n========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Database tours: " . count($db_tours) . "\n";
echo "S3 tours: " . count($s3_tours) . "\n";
echo "Orphaned: " . count($orphaned) . "\n";
echo "\n";

if ($dry_run && !empty($orphaned)) {
    echo "⚠️  RUN WITH ?execute TO CLEAN UP ORPHANED ENTRIES\n";
}

echo "</pre>\n";
