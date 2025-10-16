<?php
/**
 * Legacy Tour Migration Script
 *
 * Converts legacy tours (without tour_id) to the new ID-based system.
 * Creates metadata entries for all legacy tours without moving S3 files.
 *
 * Usage:
 * - Via WordPress: Load in admin and call migrate_legacy_tours()
 * - Via CLI: php migrate-legacy-tours.php
 *
 * @package H3_Tour_Management
 */

// Determine execution context
$is_cli = php_sapi_name() === 'cli';

if ($is_cli) {
    // CLI execution - load WordPress
    $wp_load_path = dirname(__DIR__, 4) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die("Error: Could not find WordPress installation. Please run from WordPress root or adjust path.\n");
    }
}

/**
 * Generate a unique tour ID in the format: YYYYMMDD_HHMMSS_8random
 *
 * @return string Generated tour ID
 */
function h3tm_generate_tour_id() {
    $timestamp = date('Ymd_His');
    $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
    return $timestamp . '_' . $random;
}

/**
 * Get canonical S3 folder name for a tour
 *
 * @param string $tour_name Tour name from S3
 * @return string S3 folder path
 */
function h3tm_get_s3_folder($tour_name) {
    // For legacy tours, S3 folder is tours/{TourName}/
    return 'tours/' . $tour_name;
}

/**
 * Migrate legacy tours to ID-based system
 *
 * @param bool $dry_run If true, only show what would be done without making changes
 * @return array Results of migration
 */
function migrate_legacy_tours($dry_run = true) {
    global $wpdb;

    $results = array(
        'success' => false,
        'dry_run' => $dry_run,
        'total_tours' => 0,
        'legacy_tours' => 0,
        'migrated' => 0,
        'skipped' => 0,
        'errors' => array(),
        'details' => array()
    );

    try {
        // Initialize S3 and Metadata classes
        if (!class_exists('H3TM_S3_Simple')) {
            throw new Exception('H3TM_S3_Simple class not found');
        }
        if (!class_exists('H3TM_Tour_Metadata')) {
            throw new Exception('H3TM_Tour_Metadata class not found');
        }

        $s3 = new H3TM_S3_Simple();
        $metadata = new H3TM_Tour_Metadata();

        // Get all tours from S3
        $s3_tours = $s3->get_s3_tours();
        if (empty($s3_tours)) {
            throw new Exception('No tours found in S3');
        }

        $results['total_tours'] = count($s3_tours);

        // Get existing metadata
        $table_name = $wpdb->prefix . 'h3tm_tour_metadata';
        $existing_metadata = $wpdb->get_results(
            "SELECT tour_id, display_name, tour_slug, s3_folder FROM {$table_name}",
            OBJECT_K
        );

        // Process each S3 tour
        foreach ($s3_tours as $tour_name) {
            $detail = array(
                'tour_name' => $tour_name,
                'action' => 'none',
                'reason' => ''
            );

            // Check if tour already has metadata with tour_id
            $has_metadata = false;
            $has_tour_id = false;

            foreach ($existing_metadata as $meta) {
                if ($meta->display_name === $tour_name || $meta->tour_slug === sanitize_title($tour_name)) {
                    $has_metadata = true;
                    if (!empty($meta->tour_id)) {
                        $has_tour_id = true;
                        break;
                    }
                }
            }

            // Skip if already has tour_id
            if ($has_tour_id) {
                $detail['action'] = 'skipped';
                $detail['reason'] = 'Already has tour_id';
                $results['details'][] = $detail;
                $results['skipped']++;
                continue;
            }

            // This is a legacy tour - needs migration
            $results['legacy_tours']++;

            // Generate new tour_id
            $tour_id = h3tm_generate_tour_id();
            $tour_slug = sanitize_title($tour_name);
            $s3_folder = h3tm_get_s3_folder($tour_name);

            $detail['action'] = 'migrate';
            $detail['tour_id'] = $tour_id;
            $detail['tour_slug'] = $tour_slug;
            $detail['s3_folder'] = $s3_folder;

            if (!$dry_run) {
                // Create or update metadata entry
                $insert_data = array(
                    'tour_id' => $tour_id,
                    'display_name' => $tour_name,
                    'tour_slug' => $tour_slug,
                    's3_folder' => $s3_folder,
                    'url_history' => json_encode(array()),
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                );

                $inserted = $wpdb->insert(
                    $table_name,
                    $insert_data,
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );

                if ($inserted === false) {
                    $detail['action'] = 'error';
                    $detail['reason'] = $wpdb->last_error;
                    $results['errors'][] = "Failed to migrate {$tour_name}: " . $wpdb->last_error;
                } else {
                    $detail['reason'] = 'Migrated successfully';
                    $results['migrated']++;
                }
            } else {
                $detail['reason'] = 'Would be migrated (dry-run)';
            }

            $results['details'][] = $detail;
        }

        // Clear tour cache if we actually migrated
        if (!$dry_run && $results['migrated'] > 0) {
            $s3->clear_tour_cache();
            delete_transient('h3tm_s3_tour_list');
        }

        $results['success'] = true;

    } catch (Exception $e) {
        $results['success'] = false;
        $results['errors'][] = $e->getMessage();
    }

    return $results;
}

/**
 * Format migration results for display
 *
 * @param array $results Results from migrate_legacy_tours()
 * @return string Formatted output
 */
function format_migration_results($results) {
    $output = "\n";
    $output .= "=== Legacy Tour Migration " . ($results['dry_run'] ? '(DRY RUN)' : '(LIVE)') . " ===\n\n";

    if (!$results['success']) {
        $output .= "❌ Migration failed!\n\n";
        $output .= "Errors:\n";
        foreach ($results['errors'] as $error) {
            $output .= "  - {$error}\n";
        }
        return $output;
    }

    $output .= "Summary:\n";
    $output .= "  Total tours in S3: {$results['total_tours']}\n";
    $output .= "  Legacy tours found: {$results['legacy_tours']}\n";
    $output .= "  Migrated: {$results['migrated']}\n";
    $output .= "  Skipped: {$results['skipped']}\n";

    if (!empty($results['errors'])) {
        $output .= "  Errors: " . count($results['errors']) . "\n";
    }

    $output .= "\nDetails:\n";
    foreach ($results['details'] as $detail) {
        $icon = $detail['action'] === 'migrate' ? '✅' : ($detail['action'] === 'skipped' ? '⏭️' : '❌');
        $output .= "  {$icon} {$detail['tour_name']}\n";

        if ($detail['action'] === 'migrate') {
            $output .= "      Tour ID: {$detail['tour_id']}\n";
            $output .= "      Slug: {$detail['tour_slug']}\n";
            $output .= "      S3 Folder: {$detail['s3_folder']}\n";
        }

        $output .= "      {$detail['reason']}\n\n";
    }

    if ($results['dry_run']) {
        $output .= "\n⚠️  This was a DRY RUN - no changes were made.\n";
        $output .= "Run with dry_run=false to perform actual migration.\n";
    } else {
        $output .= "\n✅ Migration complete!\n";
        $output .= "All legacy tours now have tour_id and can use rename/change URL features.\n";
        $output .= "S3 files remain in their original locations.\n";
    }

    return $output;
}

// CLI execution
if ($is_cli) {
    echo "Starting legacy tour migration...\n";

    // Check for --live flag
    $dry_run = !in_array('--live', $argv);

    if ($dry_run) {
        echo "Running in DRY RUN mode. Use --live flag to perform actual migration.\n";
    } else {
        echo "⚠️  LIVE MODE - This will modify the database!\n";
        echo "Press Ctrl+C within 5 seconds to cancel...\n";
        sleep(5);
    }

    $results = migrate_legacy_tours($dry_run);
    echo format_migration_results($results);

    exit($results['success'] ? 0 : 1);
}
