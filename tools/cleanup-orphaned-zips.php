<?php
/**
 * Cleanup orphaned ZIP files in h3panos directory
 * Run this occasionally to remove leftover ZIP files
 */

$h3panos_path = ABSPATH . 'h3panos';
if (!defined('ABSPATH')) {
    $h3panos_path = '/path/to/h3panos'; // Update for your environment
}

echo "Scanning for orphaned ZIP files in: $h3panos_path\n\n";

$zip_files = glob($h3panos_path . '/*.zip');
$removed_count = 0;
$removed_size = 0;

foreach ($zip_files as $zip_file) {
    $size = filesize($zip_file);
    $name = basename($zip_file);
    
    echo "Found: $name (" . round($size / 1024 / 1024, 1) . " MB)";
    
    if (unlink($zip_file)) {
        echo " - REMOVED ✅\n";
        $removed_count++;
        $removed_size += $size;
    } else {
        echo " - FAILED ❌\n";
    }
}

echo "\nCleanup complete:\n";
echo "Removed: $removed_count files\n";
echo "Space freed: " . round($removed_size / 1024 / 1024, 1) . " MB\n";
?>