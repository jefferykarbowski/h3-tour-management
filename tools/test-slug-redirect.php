#!/usr/bin/env php
<?php
/**
 * Test 301 Redirect for Old Slugs
 *
 * This script:
 * 1. Changes tour slug from 'my-tour-jeff' to 'jeffs-test-tour'
 * 2. Adds 'my-tour-jeff' to url_history
 * 3. Clears cache
 * 4. Provides test URLs
 */

// WordPress environment
define('WP_USE_THEMES', false);

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
    die("ERROR: Could not find wp-load.php\n");
}

require_once($wp_load);

echo "=== Testing 301 Redirect for Old Slugs ===\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'h3tm_tour_metadata';
$tour_id = '20251014_204411_mhy3v057';

// Get current tour state
$tour = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_name WHERE tour_id = %s",
    $tour_id
));

if (!$tour) {
    die("❌ Tour not found: $tour_id\n");
}

echo "📊 Current State:\n";
echo "   Tour ID: " . $tour->tour_id . "\n";
echo "   Display Name: " . $tour->display_name . "\n";
echo "   Current Slug: " . ($tour->tour_slug ?: '[NULL]') . "\n";
echo "   URL History: " . ($tour->url_history ?: '[EMPTY]') . "\n\n";

// Prepare new state
$old_slug = $tour->tour_slug ?: 'my-tour-jeff';
$new_slug = 'jeffs-test-tour';

echo "🔄 Changing URL:\n";
echo "   Old Slug: $old_slug\n";
echo "   New Slug: $new_slug\n\n";

// Update url_history
$url_history = !empty($tour->url_history) ? json_decode($tour->url_history, true) : [];
if (!is_array($url_history)) {
    $url_history = [];
}

// Add old slug to history if not already there
if (!in_array($old_slug, $url_history)) {
    $url_history[] = $old_slug;
}

// Update database
$updated = $wpdb->update(
    $table_name,
    array(
        'tour_slug' => $new_slug,
        'url_history' => json_encode($url_history)
    ),
    array('tour_id' => $tour_id),
    array('%s', '%s'),
    array('%s')
);

if ($updated === false) {
    die("❌ Database update failed: " . $wpdb->last_error . "\n");
}

echo "✅ Database updated successfully!\n\n";

// Clear cache
delete_transient('h3tm_s3_tours_cache');
echo "✅ Cache cleared\n\n";

// Verify update
$updated_tour = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_name WHERE tour_id = %s",
    $tour_id
));

echo "📊 New State:\n";
echo "   Current Slug: " . $updated_tour->tour_slug . "\n";
echo "   URL History: " . $updated_tour->url_history . "\n\n";

echo "🧪 Test URLs:\n";
echo "   New URL (should work): https://h3vt.local/h3panos/$new_slug/\n";
echo "   Old URL (should 301 redirect): https://h3vt.local/h3panos/$old_slug/\n\n";

echo "✅ Ready to test! Use Playwright or browser to verify:\n";
echo "   1. New URL loads the tour\n";
echo "   2. Old URL performs 301 redirect to new URL\n";
echo "   3. Check browser network tab for 301 status code\n\n";
