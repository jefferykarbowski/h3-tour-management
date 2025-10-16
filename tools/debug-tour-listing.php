#!/usr/bin/env php
<?php
/**
 * Debug Tour Listing - Check S3 folders and metadata
 *
 * Run this to diagnose why a tour isn't showing up in the Available Tours list
 */

// WordPress environment
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../../../h3vt/wp-load.php');

echo "=== Tour Listing Debug Tool ===\n\n";

$tour_id = '20251014_204411_mhy3v057';

// 1. Check metadata
echo "1. Checking metadata for tour_id: $tour_id\n";
echo str_repeat('-', 60) . "\n";

if (class_exists('H3TM_Tour_Metadata')) {
    $metadata = new H3TM_Tour_Metadata();
    $tour = $metadata->get_by_tour_id($tour_id);

    if ($tour) {
        echo "✅ Metadata found:\n";
        echo "   ID: {$tour->id}\n";
        echo "   Tour ID: {$tour->tour_id}\n";
        echo "   Display Name: {$tour->display_name}\n";
        echo "   Tour Slug: {$tour->tour_slug}\n";
        echo "   S3 Folder: {$tour->s3_folder}\n";
        echo "   Status: {$tour->status}\n";
        echo "   Created: {$tour->created_date}\n";
    } else {
        echo "❌ No metadata found for tour_id: $tour_id\n";
    }
} else {
    echo "❌ H3TM_Tour_Metadata class not found\n";
}

echo "\n";

// 2. Check S3 folder exists
echo "2. Checking if S3 folder exists\n";
echo str_repeat('-', 60) . "\n";

if (class_exists('H3TM_S3_Simple')) {
    $s3 = new H3TM_S3_Simple();
    $config = $s3->get_s3_config();

    if ($config['configured']) {
        echo "S3 Bucket: {$config['bucket']}\n";
        echo "S3 Region: {$config['region']}\n\n";

        // Try to list files in the tour folder
        $test_url = "https://{$config['bucket']}.s3.{$config['region']}.amazonaws.com/tours/$tour_id/index.htm";
        echo "Testing URL: $test_url\n";

        $response = wp_remote_head($test_url, array('timeout' => 10));

        if (is_wp_error($response)) {
            echo "❌ Error checking S3: " . $response->get_error_message() . "\n";
        } else {
            $status = wp_remote_retrieve_response_code($response);
            if ($status === 200) {
                echo "✅ S3 folder EXISTS - index.htm found (HTTP $status)\n";
            } elseif ($status === 403) {
                echo "⚠️  S3 folder may exist but access denied (HTTP $status)\n";
            } elseif ($status === 404) {
                echo "❌ S3 folder DOES NOT EXIST (HTTP $status)\n";
                echo "   Lambda may not have finished processing!\n";
            } else {
                echo "⚠️  Unexpected status: HTTP $status\n";
            }
        }
    } else {
        echo "❌ S3 not configured\n";
    }
} else {
    echo "❌ H3TM_S3_Simple class not found\n";
}

echo "\n";

// 3. Check what list_s3_tours returns
echo "3. Running list_s3_tours() to see what it finds\n";
echo str_repeat('-', 60) . "\n";

if (class_exists('H3TM_S3_Simple')) {
    $s3 = new H3TM_S3_Simple();
    $tours = $s3->list_s3_tours();

    echo "Total tours found: " . count($tours) . "\n\n";

    if (!empty($tours)) {
        $found_our_tour = false;
        foreach ($tours as $tour) {
            if (is_array($tour)) {
                if (isset($tour['tour_id']) && $tour['tour_id'] === $tour_id) {
                    echo "✅ FOUND our tour in list:\n";
                    echo "   Name: {$tour['name']}\n";
                    echo "   Tour ID: {$tour['tour_id']}\n";
                    echo "   Status: {$tour['status']}\n";
                    $found_our_tour = true;
                    break;
                }
            }
        }

        if (!$found_our_tour) {
            echo "❌ Our tour NOT in list\n\n";
            echo "Tours currently in list:\n";
            foreach ($tours as $idx => $tour) {
                if (is_array($tour)) {
                    echo sprintf("   %d. %s (ID: %s, Status: %s)\n",
                        $idx + 1,
                        $tour['name'] ?? 'N/A',
                        $tour['tour_id'] ?? 'N/A',
                        $tour['status'] ?? 'N/A'
                    );
                } else {
                    echo sprintf("   %d. %s (legacy)\n", $idx + 1, $tour);
                }
            }
        }
    } else {
        echo "❌ No tours found at all\n";
    }
}

echo "\n";

// 4. Check cache
echo "4. Checking S3 tours cache\n";
echo str_repeat('-', 60) . "\n";

$cache = get_transient('h3tm_s3_tours_cache');
if ($cache !== false) {
    echo "⚠️  Cache exists with " . count($cache) . " tours\n";
    echo "   Recommendation: Clear cache with DELETE FROM wp_options WHERE option_name LIKE '%h3tm_s3_tours_cache%'\n";
} else {
    echo "✅ No cache found - fresh queries\n";
}

echo "\n";

// 5. Recommendations
echo "5. Recommendations\n";
echo str_repeat('=', 60) . "\n";

if ($tour && $tour->status === 'completed') {
    echo "Metadata shows 'completed' but tour not in list.\n\n";
    echo "Possible causes:\n";
    echo "1. Lambda didn't finish - Check Lambda logs in AWS Console\n";
    echo "2. S3 folder doesn't exist - Check S3 bucket manually\n";
    echo "3. Cache issue - Clear cache:\n";
    echo "   DELETE FROM wp_options WHERE option_name = 'h3tm_s3_tours_cache';\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Go to AWS Lambda console and check logs for tour_id: $tour_id\n";
    echo "2. Go to S3 bucket and check if folder 'tours/$tour_id/' exists\n";
    echo "3. If Lambda failed, re-deploy updated Lambda function\n";
    echo "4. If Lambda succeeded but webhook failed, manually update metadata\n";
}

echo "\n=== Debug Complete ===\n";
