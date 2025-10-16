<?php
/**
 * Check Tour Processing Status
 *
 * Usage: wp eval-file tools/check-tour-status.php
 */

global $wpdb;

$tour_id = '20251016_163115_y2dtamcj';

// Check tour_processing table
$table_name = $wpdb->prefix . 'h3tm_tour_processing';
$tour = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $table_name WHERE tour_id = %s", $tour_id)
);

if ($tour) {
    echo "Tour Processing Record Found:\n";
    echo "==============================\n";
    echo "Tour ID: " . $tour->tour_id . "\n";
    echo "Tour Name: " . $tour->tour_name . "\n";
    echo "Status: " . $tour->status . "\n";
    echo "S3 Key: " . $tour->s3_key . "\n";
    echo "Created: " . $tour->created_at . "\n";
    echo "Updated: " . $tour->updated_at . "\n";

    if (!empty($tour->webhook_payload)) {
        echo "\nWebhook Payload:\n";
        echo $tour->webhook_payload . "\n";
    }

    if (!empty($tour->error_message)) {
        echo "\nError Message:\n";
        echo $tour->error_message . "\n";
    }
} else {
    echo "No tour processing record found for tour_id: $tour_id\n";
}

// Check tour_metadata table
$metadata_table = $wpdb->prefix . 'h3tm_tour_metadata';
$metadata = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $metadata_table WHERE tour_id = %s", $tour_id)
);

if ($metadata) {
    echo "\n\nTour Metadata Record Found:\n";
    echo "===========================\n";
    echo "Tour ID: " . $metadata->tour_id . "\n";
    echo "Display Name: " . $metadata->display_name . "\n";
    echo "Slug: " . $metadata->tour_slug . "\n";
    echo "S3 Folder: " . $metadata->s3_folder . "\n";
    echo "Thumbnail: " . $metadata->thumbnail_url . "\n";
} else {
    echo "\n\nNo metadata record found for tour_id: $tour_id\n";
}
