#!/usr/bin/env php
<?php
/**
 * Check Tour Slug in Database
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

echo "=== Checking Tour Slug for tour_id: 20251014_204411_mhy3v057 ===\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'h3tm_tour_metadata';

$tour = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table_name WHERE tour_id = %s",
    '20251014_204411_mhy3v057'
));

if ($tour) {
    echo "✅ Tour found in database:\n";
    echo "   ID: " . $tour->id . "\n";
    echo "   Tour ID: " . $tour->tour_id . "\n";
    echo "   Display Name: " . $tour->display_name . "\n";
    echo "   Tour Slug: " . ($tour->tour_slug ?: '[NULL/EMPTY]') . "\n";
    echo "   Status: " . $tour->status . "\n";
    echo "   Created: " . $tour->created_at . "\n\n";

    if (empty($tour->tour_slug)) {
        echo "⚠️  Tour slug is NULL/EMPTY - this is the problem!\n\n";
        echo "Generating slug from display name...\n";
        $generated_slug = sanitize_title($tour->display_name);
        echo "Generated slug: $generated_slug\n\n";

        echo "Would you like to update the database with this slug? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim($line) === 'y') {
            $updated = $wpdb->update(
                $table_name,
                array('tour_slug' => $generated_slug),
                array('tour_id' => '20251014_204411_mhy3v057'),
                array('%s'),
                array('%s')
            );

            if ($updated !== false) {
                echo "✅ Database updated successfully!\n";
                echo "   New tour_slug: $generated_slug\n";
                echo "   New URL: /h3panos/$generated_slug\n\n";

                // Clear cache
                delete_transient('h3tm_s3_tours_cache');
                echo "✅ Cache cleared\n";
            } else {
                echo "❌ Database update failed\n";
            }
        } else {
            echo "Skipped database update\n";
        }
    } else {
        echo "✅ Tour slug exists: " . $tour->tour_slug . "\n";
        echo "   URL should be: /h3panos/" . $tour->tour_slug . "\n";
    }
} else {
    echo "❌ Tour not found in database\n";
}

echo "\n";
