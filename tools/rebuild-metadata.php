<?php
/**
 * Standalone metadata rebuild script
 * Place in WordPress root and access via browser: https://h3vt.local/rebuild-metadata.php
 */

// Find wp-load.php in parent directories
$levels_up = 0;
$wp_load_path = '';
while ($levels_up < 5) {
    $test_path = str_repeat('../', $levels_up) . 'wp-load.php';
    if (file_exists($test_path)) {
        $wp_load_path = $test_path;
        break;
    }
    $levels_up++;
}

if ($wp_load_path) {
    require_once($wp_load_path);
} else {
    die('Could not find WordPress. Place this file in your WordPress root directory.');
}

// Load required class
$metadata_class = dirname(__FILE__) . '/includes/class-h3tm-tour-metadata.php';
if (file_exists($metadata_class)) {
    require_once($metadata_class);
} else {
    // Try alternate path
    $metadata_class = 'wp-content/plugins/h3-tour-management/includes/class-h3tm-tour-metadata.php';
    require_once($metadata_class);
}

echo "<h1>Rebuild Tour Metadata</h1>\n";
echo "<p>This will ensure all tours have correct metadata...</p>\n";

// Create metadata instance
$metadata = new H3TM_Tour_Metadata();

// Define tours that should exist (with spaces as they are in S3)
$expected_tours = array(
    'Jeffs Test' => 'tours/Jeffs Test',  // S3 folder with space
    // Add other tours here if needed
);

echo "<h2>Processing Tours:</h2>\n";

foreach ($expected_tours as $tour_name => $s3_folder) {
    echo "<h3>Tour: {$tour_name}</h3>\n";

    // Check if exists
    $existing = $metadata->get_by_display_name($tour_name);

    if ($existing) {
        echo "<p>✓ Metadata exists (ID: {$existing->id})</p>\n";
        echo "<ul>\n";
        echo "<li>Slug: {$existing->tour_slug}</li>\n";
        echo "<li>Current S3 Folder: <code>{$existing->s3_folder}</code></li>\n";
        echo "<li>Expected S3 Folder: <code>{$s3_folder}</code></li>\n";
        echo "</ul>\n";

        if ($existing->s3_folder !== $s3_folder) {
            echo "<p>⚠️ S3 folder mismatch! Updating...</p>\n";
            $result = $metadata->update($existing->id, array('s3_folder' => $s3_folder));
            if ($result) {
                echo "<p style='color: green;'><strong>✅ FIXED!</strong> Updated s3_folder</p>\n";
            } else {
                echo "<p style='color: red;'><strong>❌ ERROR:</strong> Failed to update</p>\n";
            }
        } else {
            echo "<p style='color: green;'>✅ S3 folder is correct!</p>\n";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Metadata NOT FOUND. Creating...</p>\n";

        $tour_slug = sanitize_title($tour_name);
        $id = $metadata->create(array(
            'tour_slug' => $tour_slug,
            'display_name' => $tour_name,
            's3_folder' => $s3_folder,
            'url_history' => json_encode(array())
        ));

        if ($id) {
            echo "<p style='color: green;'><strong>✅ CREATED!</strong> New metadata ID: {$id}</p>\n";
            echo "<ul>\n";
            echo "<li>Slug: {$tour_slug}</li>\n";
            echo "<li>S3 Folder: <code>{$s3_folder}</code></li>\n";
            echo "</ul>\n";
        } else {
            echo "<p style='color: red;'><strong>❌ ERROR:</strong> Failed to create metadata</p>\n";
        }
    }

    echo "<hr>\n";
}

echo "<h2>Final Check:</h2>\n";
$all_tours = $metadata->get_all();
echo "<p>Total tours in metadata: " . count($all_tours) . "</p>\n";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
echo "<tr><th>ID</th><th>Display Name</th><th>Slug</th><th>S3 Folder</th></tr>\n";
foreach ($all_tours as $tour) {
    echo "<tr>";
    echo "<td>{$tour->id}</td>";
    echo "<td>{$tour->display_name}</td>";
    echo "<td>{$tour->tour_slug}</td>";
    echo "<td><code>{$tour->s3_folder}</code></td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "\n<p><strong>✅ Done! Now try deleting 'Jeffs Test' again.</strong></p>\n";
echo "<p><a href='/wp-admin/admin.php?page=h3-tour-management'>&larr; Back to Tours Management</a></p>\n";
?>
