<?php
/**
 * Fix metadata for "Jeffs Test" tour
 * This ensures the s3_folder matches Lambda's actual S3 structure
 */

// Load WordPress
require_once('wp-load.php');

// Load required classes
require_once('wp-content/plugins/h3-tour-management/includes/class-h3tm-tour-metadata.php');

echo "<h2>Fixing Metadata for 'Jeffs Test'</h2>\n";

$metadata = new H3TM_Tour_Metadata();

// Check if metadata exists
$existing = $metadata->get_by_display_name('Jeffs Test');

if ($existing) {
    echo "<p>✅ Metadata exists for 'Jeffs Test'</p>\n";
    echo "<pre>";
    echo "Current values:\n";
    echo "  ID: {$existing->id}\n";
    echo "  Display Name: {$existing->display_name}\n";
    echo "  Slug: {$existing->tour_slug}\n";
    echo "  S3 Folder: {$existing->s3_folder}\n";
    echo "</pre>";

    // Check if s3_folder needs fixing
    $correct_s3_folder = 'tours/Jeffs Test';  // Lambda preserves spaces!

    if ($existing->s3_folder !== $correct_s3_folder) {
        echo "<p>⚠️ S3 Folder is incorrect. Fixing...</p>\n";
        echo "<p>Current: <code>{$existing->s3_folder}</code></p>\n";
        echo "<p>Correct: <code>{$correct_s3_folder}</code></p>\n";

        // Update metadata
        $result = $metadata->update($existing->id, array(
            's3_folder' => $correct_s3_folder
        ));

        if ($result) {
            echo "<p style='color: green;'><strong>✅ FIXED!</strong> S3 folder updated successfully.</p>\n";
            echo "<p>Try deleting 'Jeffs Test' again.</p>\n";
        } else {
            echo "<p style='color: red;'><strong>❌ ERROR:</strong> Failed to update metadata.</p>\n";
        }
    } else {
        echo "<p style='color: green;'>✅ S3 Folder is correct!</p>\n";
        echo "<p>The issue might be that the tour doesn't exist in S3, or the folder name in S3 is different.</p>\n";
        echo "<p><strong>Expected S3 location:</strong> <code>tours/Jeffs Test/</code></p>\n";
        echo "<p><strong>Try these:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Check S3 bucket to verify folder exists</li>\n";
        echo "<li>Check if Lambda created the folder with dashes: <code>tours/Jeffs-Test/</code></li>\n";
        echo "<li>If using dashes in S3, update metadata s3_folder to: <code>tours/Jeffs-Test</code></li>\n";
        echo "</ul>\n";
    }
} else {
    echo "<p style='color: red;'><strong>❌ ERROR: Metadata NOT FOUND for 'Jeffs Test'</strong></p>\n";
    echo "<p>This is the root cause! Creating metadata now...</p>\n";

    // Create metadata
    $tour_slug = sanitize_title('Jeffs Test');  // "jeffs-test"
    $s3_folder = 'tours/Jeffs Test';  // Lambda preserves spaces

    $id = $metadata->create(array(
        'tour_slug' => $tour_slug,
        'display_name' => 'Jeffs Test',
        's3_folder' => $s3_folder,
        'url_history' => json_encode(array())
    ));

    if ($id) {
        echo "<p style='color: green;'><strong>✅ CREATED!</strong> Metadata created successfully.</p>\n";
        echo "<pre>";
        echo "New metadata:\n";
        echo "  ID: {$id}\n";
        echo "  Display Name: Jeffs Test\n";
        echo "  Slug: {$tour_slug}\n";
        echo "  S3 Folder: {$s3_folder}\n";
        echo "</pre>";
        echo "<p><strong>Try deleting 'Jeffs Test' again!</strong></p>\n";
    } else {
        echo "<p style='color: red;'><strong>❌ ERROR:</strong> Failed to create metadata.</p>\n";
    }
}

echo "\n<hr>\n";
echo "<p><a href='/wp-admin/admin.php?page=h3-tour-management'>&larr; Back to Tours Management</a></p>\n";
?>
