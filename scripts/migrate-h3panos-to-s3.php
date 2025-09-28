<?php
/**
 * Migration Script: H3panos Tours to S3
 *
 * This script migrates existing tours from the h3panos directory to S3 bucket
 * Handles both individual tour directories and ZIP files
 *
 * Usage: php migrate-h3panos-to-s3.php [--dry-run] [--tour=name]
 */

// Load WordPress environment
$wp_load_path = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: WordPress environment not found. Please run this script from the plugin directory.\n");
}
require_once($wp_load_path);

// Command line arguments
$options = getopt('', ['dry-run', 'tour::', 'help']);
$dry_run = isset($options['dry-run']);
$specific_tour = $options['tour'] ?? null;

if (isset($options['help'])) {
    echo "H3 Tours Migration Script to S3\n";
    echo "================================\n\n";
    echo "Usage: php migrate-h3panos-to-s3.php [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run       Run without actually uploading (test mode)\n";
    echo "  --tour=name     Migrate specific tour only\n";
    echo "  --help          Show this help message\n\n";
    exit(0);
}

// Configuration
$h3panos_path = 'C:/Users/Jeff/Local Sites/h3vt/app/public/h3panos';
$s3_bucket = get_option('h3tm_s3_bucket');
$s3_region = get_option('h3tm_s3_region', 'us-east-1');

if (!$s3_bucket) {
    die("Error: S3 bucket not configured in plugin settings.\n");
}

// Load S3 class
if (!class_exists('H3TM_S3_Simple')) {
    require_once(dirname(dirname(__FILE__)) . '/includes/class-h3tm-s3-simple.php');
}

$s3 = new H3TM_S3_Simple();

echo "H3 Tours Migration to S3\n";
echo "========================\n";
echo "Source: $h3panos_path\n";
echo "Destination: s3://$s3_bucket/tours/\n";
echo "Mode: " . ($dry_run ? "DRY RUN (no actual uploads)" : "LIVE") . "\n\n";

// Get list of tours to migrate
$tours_to_migrate = [];

if ($specific_tour) {
    // Migrate specific tour
    $tour_path = $h3panos_path . '/' . $specific_tour;
    if (is_dir($tour_path)) {
        $tours_to_migrate[] = $specific_tour;
    } elseif (file_exists($tour_path . '.zip')) {
        $tours_to_migrate[] = $specific_tour . '.zip';
    } else {
        die("Error: Tour '$specific_tour' not found.\n");
    }
} else {
    // Get all tours
    if (!is_dir($h3panos_path)) {
        die("Error: H3panos directory not found at: $h3panos_path\n");
    }

    $items = scandir($h3panos_path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'index.php') {
            continue;
        }

        $item_path = $h3panos_path . '/' . $item;
        if (is_dir($item_path) || (is_file($item_path) && preg_match('/\.zip$/i', $item))) {
            $tours_to_migrate[] = $item;
        }
    }
}

if (empty($tours_to_migrate)) {
    echo "No tours found to migrate.\n";
    exit(0);
}

echo "Found " . count($tours_to_migrate) . " tours to migrate:\n";
foreach ($tours_to_migrate as $tour) {
    echo "  - $tour\n";
}
echo "\n";

// Migration statistics
$stats = [
    'total' => count($tours_to_migrate),
    'success' => 0,
    'skipped' => 0,
    'failed' => 0,
    'files_uploaded' => 0,
    'bytes_uploaded' => 0
];

// Process each tour
foreach ($tours_to_migrate as $index => $tour_item) {
    $tour_num = $index + 1;
    echo "[$tour_num/{$stats['total']}] Processing: $tour_item\n";

    // Determine tour name and type
    $is_zip = preg_match('/\.zip$/i', $tour_item);
    $tour_name = $is_zip ? preg_replace('/\.zip$/i', '', $tour_item) : $tour_item;

    // Convert spaces to dashes for S3 (matching Lambda behavior)
    $s3_tour_name = str_replace(' ', '-', $tour_name);

    echo "  Tour name: $tour_name\n";
    echo "  S3 name: $s3_tour_name\n";

    // Check if tour already exists in S3
    if (!$dry_run) {
        $existing_tours = $s3->list_tours();
        $tour_exists = false;

        foreach ($existing_tours as $existing) {
            if (strcasecmp($existing['name'], $tour_name) === 0 ||
                strcasecmp($existing['name'], $s3_tour_name) === 0) {
                $tour_exists = true;
                break;
            }
        }

        if ($tour_exists) {
            echo "  ⚠️  Tour already exists in S3, skipping...\n\n";
            $stats['skipped']++;
            continue;
        }
    }

    // Handle ZIP files
    if ($is_zip) {
        $zip_path = $h3panos_path . '/' . $tour_item;
        echo "  Type: ZIP file\n";
        echo "  Size: " . number_format(filesize($zip_path) / 1024 / 1024, 2) . " MB\n";

        if (!$dry_run) {
            echo "  Extracting ZIP file...\n";

            // Create temporary extraction directory
            $temp_dir = sys_get_temp_dir() . '/h3_migration_' . uniqid();
            mkdir($temp_dir, 0777, true);

            $zip = new ZipArchive();
            if ($zip->open($zip_path) === TRUE) {
                $zip->extractTo($temp_dir);
                $zip->close();

                // Upload extracted files
                $upload_result = upload_directory_to_s3($s3, $temp_dir, $s3_tour_name, $stats, $dry_run);

                // Clean up temp directory
                delete_directory($temp_dir);

                if ($upload_result) {
                    echo "  ✅ Successfully migrated\n\n";
                    $stats['success']++;
                } else {
                    echo "  ❌ Migration failed\n\n";
                    $stats['failed']++;
                }
            } else {
                echo "  ❌ Failed to extract ZIP file\n\n";
                $stats['failed']++;
            }
        } else {
            echo "  [DRY RUN] Would extract and upload ZIP contents\n\n";
            $stats['success']++;
        }
    } else {
        // Handle directory
        $dir_path = $h3panos_path . '/' . $tour_item;
        echo "  Type: Directory\n";

        if (!$dry_run) {
            $upload_result = upload_directory_to_s3($s3, $dir_path, $s3_tour_name, $stats, $dry_run);

            if ($upload_result) {
                echo "  ✅ Successfully migrated\n\n";
                $stats['success']++;
            } else {
                echo "  ❌ Migration failed\n\n";
                $stats['failed']++;
            }
        } else {
            $file_count = count_files_in_directory($dir_path);
            echo "  [DRY RUN] Would upload $file_count files\n\n";
            $stats['success']++;
        }
    }
}

// Print summary
echo "\nMigration Summary\n";
echo "=================\n";
echo "Total tours:     {$stats['total']}\n";
echo "Successful:      {$stats['success']}\n";
echo "Skipped:         {$stats['skipped']}\n";
echo "Failed:          {$stats['failed']}\n";
echo "Files uploaded:  {$stats['files_uploaded']}\n";
echo "Data uploaded:   " . format_bytes($stats['bytes_uploaded']) . "\n";

if ($dry_run) {
    echo "\nThis was a DRY RUN. No files were actually uploaded.\n";
    echo "Run without --dry-run flag to perform actual migration.\n";
}

// Helper functions

function upload_directory_to_s3($s3, $local_path, $s3_tour_name, &$stats, $dry_run = false) {
    $success = true;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($local_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $local_file = $file->getPathname();
            $relative_path = str_replace($local_path . DIRECTORY_SEPARATOR, '', $local_file);
            $relative_path = str_replace('\\', '/', $relative_path);
            $s3_key = "tours/{$s3_tour_name}/" . $relative_path;

            echo "    Uploading: $relative_path";

            if (!$dry_run) {
                // Determine content type
                $content_type = mime_content_type($local_file);
                if (!$content_type) {
                    $ext = strtolower(pathinfo($local_file, PATHINFO_EXTENSION));
                    $content_types = [
                        'html' => 'text/html',
                        'css' => 'text/css',
                        'js' => 'application/javascript',
                        'json' => 'application/json',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'svg' => 'image/svg+xml',
                        'xml' => 'application/xml'
                    ];
                    $content_type = $content_types[$ext] ?? 'application/octet-stream';
                }

                $result = $s3->upload_file($local_file, $s3_key, $content_type);

                if ($result) {
                    echo " ✓\n";
                    $stats['files_uploaded']++;
                    $stats['bytes_uploaded'] += filesize($local_file);
                } else {
                    echo " ✗\n";
                    $success = false;
                }
            } else {
                echo " [dry-run]\n";
            }
        }
    }

    return $success;
}

function count_files_in_directory($dir) {
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $count++;
        }
    }

    return $count;
}

function delete_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? delete_directory($path) : unlink($path);
    }

    rmdir($dir);
}

function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return number_format($bytes, 2) . ' ' . $units[$i];
}