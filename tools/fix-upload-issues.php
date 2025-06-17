<?php
/**
 * Fix upload issues for H3 Tour Management
 * Run: php fix-upload-issues.php
 */

echo "H3 Tour Management - Upload Issues Diagnostics\n";
echo "==============================================\n\n";

// Check PHP settings
echo "PHP Configuration:\n";
echo "==================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . " seconds\n";
echo "Max input time: " . ini_get('max_input_time') . " seconds\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Post max size: " . ini_get('post_max_size') . "\n";
echo "Upload max filesize: " . ini_get('upload_max_filesize') . "\n";
echo "Max file uploads: " . ini_get('max_file_uploads') . "\n\n";

// Check WordPress upload directory
$wp_root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
require_once $wp_root . '/wp-load.php';

$upload_dir = wp_upload_dir();
echo "WordPress Upload Directory:\n";
echo "===========================\n";
echo "Base dir: " . $upload_dir['basedir'] . "\n";
echo "Base URL: " . $upload_dir['baseurl'] . "\n";

// Check temp directories
$temp_dir = $upload_dir['basedir'] . '/h3-tours-temp';
$tours_dir = $upload_dir['basedir'] . '/h3-tours';

echo "\nDirectory Status:\n";
echo "=================\n";
echo "Temp dir: " . $temp_dir . " - " . (is_dir($temp_dir) ? "EXISTS" : "NOT FOUND") . "\n";
echo "Tours dir: " . $tours_dir . " - " . (is_dir($tours_dir) ? "EXISTS" : "NOT FOUND") . "\n";

// Check permissions
if (is_dir($temp_dir)) {
    echo "Temp dir writable: " . (is_writable($temp_dir) ? "YES" : "NO") . "\n";
}
if (is_dir($tours_dir)) {
    echo "Tours dir writable: " . (is_writable($tours_dir) ? "YES" : "NO") . "\n";
}

// Create directories if missing
if (!is_dir($temp_dir)) {
    if (wp_mkdir_p($temp_dir)) {
        echo "\n✓ Created temp directory\n";
    } else {
        echo "\n✗ Failed to create temp directory\n";
    }
}

if (!is_dir($tours_dir)) {
    if (wp_mkdir_p($tours_dir)) {
        echo "\n✓ Created tours directory\n";
    } else {
        echo "\n✗ Failed to create tours directory\n";
    }
}

// Check for orphaned temp files
echo "\nChecking for orphaned temp files:\n";
echo "=================================\n";
if (is_dir($temp_dir)) {
    $temp_subdirs = glob($temp_dir . '/*', GLOB_ONLYDIR);
    if ($temp_subdirs) {
        echo "Found " . count($temp_subdirs) . " temp upload directories\n";
        foreach ($temp_subdirs as $subdir) {
            $chunks = glob($subdir . '/chunk_*');
            $dir_time = filemtime($subdir);
            $age_hours = (time() - $dir_time) / 3600;
            echo "- " . basename($subdir) . " (" . count($chunks) . " chunks, " . round($age_hours, 1) . " hours old)\n";
            
            // Clean up if older than 24 hours
            if ($age_hours > 24) {
                array_map('unlink', $chunks);
                rmdir($subdir);
                echo "  ✓ Cleaned up old temp directory\n";
            }
        }
    } else {
        echo "No temp upload directories found\n";
    }
}

// Chunk size calculation
echo "\nChunk Upload Info:\n";
echo "==================\n";
$chunk_size = 1024 * 1024; // 1MB
echo "Chunk size: " . ($chunk_size / 1024 / 1024) . " MB\n";
echo "For a 1GB file: ~" . ceil(1024 * 1024 * 1024 / $chunk_size) . " chunks\n";
echo "Chunk 938 suggests file size: ~" . round(938 * $chunk_size / 1024 / 1024) . " MB\n";

// Recommendations
echo "\nRecommendations:\n";
echo "================\n";

if (ini_get('max_execution_time') < 300) {
    echo "⚠ Increase max_execution_time to at least 300 seconds\n";
}

if (ini_get('memory_limit') < '256M') {
    echo "⚠ Increase memory_limit to at least 256M\n";
}

echo "\nTo fix chunk 938 error:\n";
echo "=======================\n";
echo "1. The error suggests the upload is failing around 938MB\n";
echo "2. This could be due to:\n";
echo "   - Server timeout (execution time limit)\n";
echo "   - Disk space issues\n";
echo "   - Memory exhaustion\n";
echo "   - Network timeout\n";
echo "\n";
echo "3. Quick fixes to try:\n";
echo "   a. Add to wp-config.php:\n";
echo "      set_time_limit(0);\n";
echo "      ini_set('memory_limit', '512M');\n";
echo "\n";
echo "   b. Check available disk space:\n";
echo "      Free space: " . round(disk_free_space($upload_dir['basedir']) / 1024 / 1024 / 1024, 2) . " GB\n";
echo "\n";
echo "   c. Try uploading a smaller test file first\n";

// Create .htaccess for upload directories
$htaccess_content = "# Increase timeout for large uploads
<IfModule mod_php7.c>
    php_value max_execution_time 600
    php_value max_input_time 600
    php_value memory_limit 512M
</IfModule>
<IfModule mod_php8.c>
    php_value max_execution_time 600
    php_value max_input_time 600
    php_value memory_limit 512M
</IfModule>";

$htaccess_file = $upload_dir['basedir'] . '/.htaccess';
if (!file_exists($htaccess_file)) {
    file_put_contents($htaccess_file, $htaccess_content);
    echo "\n✓ Created .htaccess file with extended limits\n";
}

echo "\nDiagnostics complete.\n";