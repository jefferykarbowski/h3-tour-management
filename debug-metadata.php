<?php
/**
 * Debug script to check metadata for "Jeffs Test"
 * Run this from WordPress root or via browser
 */

// Load WordPress
require_once('wp-load.php');

// Load classes
require_once('wp-content/plugins/h3-tour-management/includes/class-h3tm-tour-metadata.php');

echo "<h2>Metadata Debug for 'Jeffs Test'</h2>";

$metadata = new H3TM_Tour_Metadata();

// Try all lookup methods
echo "<h3>1. By display name 'Jeffs Test':</h3>";
$by_display = $metadata->get_by_display_name('Jeffs Test');
echo "<pre>";
print_r($by_display);
echo "</pre>";

echo "<h3>2. By slug 'jeffs-test':</h3>";
$by_slug = $metadata->get_by_slug('jeffs-test');
echo "<pre>";
print_r($by_slug);
echo "</pre>";

echo "<h3>3. Resolve by display name:</h3>";
if (method_exists($metadata, 'resolve_by_display_name')) {
    $resolved = $metadata->resolve_by_display_name('Jeffs Test');
    echo "<pre>";
    print_r($resolved);
    echo "</pre>";
} else {
    echo "<p>Method resolve_by_display_name() doesn't exist</p>";
}

echo "<h3>4. All tours in metadata:</h3>";
$all = $metadata->get_all();
echo "<pre>";
foreach ($all as $tour) {
    echo "ID: {$tour->id} | Name: {$tour->display_name} | Slug: {$tour->tour_slug} | S3: {$tour->s3_folder}\n";
}
echo "</pre>";

echo "<h3>5. What archive_tour() would use:</h3>";
echo "<p>Looking up 'Jeffs Test' via resolve_by_display_name()...</p>";
if ($resolved) {
    echo "<p><strong>Found tour:</strong></p>";
    echo "<ul>";
    echo "<li><strong>ID:</strong> {$resolved->id}</li>";
    echo "<li><strong>Display Name:</strong> {$resolved->display_name}</li>";
    echo "<li><strong>Slug:</strong> {$resolved->tour_slug}</li>";
    echo "<li><strong>S3 Folder:</strong> {$resolved->s3_folder}</li>";
    echo "</ul>";

    // Extract canonical folder
    $canonical_folder = rtrim($resolved->s3_folder, '/');
    if (strpos($canonical_folder, 'tours/') === 0) {
        $canonical_folder = substr($canonical_folder, strlen('tours/'));
    }
    echo "<p><strong>Canonical folder for S3 operations:</strong> <code>{$canonical_folder}</code></p>";
    echo "<p><strong>Source prefix for list:</strong> <code>tours/{$canonical_folder}/</code></p>";
} else {
    echo "<p style='color: red;'><strong>ERROR: Tour not found in metadata!</strong></p>";
    echo "<p>This is why delete fails - archive_tour() returns 'Tour metadata not found'</p>";
}
?>
