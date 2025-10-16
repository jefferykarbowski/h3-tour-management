<?php
/**
 * S3 Tour Diagnostics
 *
 * Checks S3 bucket structure and tour file locations
 * Run from wp-admin or CLI to diagnose 403 errors
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h2>S3 Tour Diagnostics</h2>\n";

// Test tours
$test_tours = [
    'asdfasdf' => '20251016_050212_enj0cply',
    'Arden Pikesville' => null // Legacy tour
];

$cloudfront_domain = 'd14z8re58oj029.cloudfront.net';
$s3_bucket = get_option('h3tm_s3_bucket', '');
$s3_region = get_option('h3tm_aws_region', 'us-east-1');

echo "<h3>Configuration</h3>\n";
echo "CloudFront Domain: {$cloudfront_domain}<br>\n";
echo "S3 Bucket: {$s3_bucket}<br>\n";
echo "S3 Region: {$s3_region}<br>\n";

if (empty($s3_bucket)) {
    echo "<p style='color:red;'><strong>ERROR: S3 bucket not configured!</strong></p>\n";
}

echo "<h3>Testing Tour URLs</h3>\n";

foreach ($test_tours as $slug => $tour_id) {
    echo "<h4>Tour: {$slug}</h4>\n";

    // Determine the actual tour directory
    $tour_dir = $tour_id ? $tour_id : str_replace(' ', '-', $slug);

    echo "Slug: {$slug}<br>\n";
    echo "Tour ID: " . ($tour_id ?: 'Legacy') . "<br>\n";
    echo "Expected S3 Path: tours/{$tour_dir}/<br>\n";

    // Test URLs to try
    $test_urls = [
        "CloudFront" => "https://{$cloudfront_domain}/tours/{$tour_dir}/index.htm",
        "Direct S3" => "https://{$s3_bucket}.s3.{$s3_region}.amazonaws.com/tours/{$tour_dir}/index.htm",
    ];

    // If legacy with spaces, try alternate paths
    if (!$tour_id && strpos($slug, ' ') !== false) {
        $test_urls["CloudFront (URL encoded)"] = "https://{$cloudfront_domain}/tours/" . urlencode($slug) . "/index.htm";
        $test_urls["Direct S3 (URL encoded)"] = "https://{$s3_bucket}.s3.{$s3_region}.amazonaws.com/tours/" . urlencode($slug) . "/index.htm";
    }

    echo "<table border='1' cellpadding='5' style='margin: 10px 0;'>\n";
    echo "<tr><th>URL Type</th><th>URL</th><th>Status</th><th>Response</th></tr>\n";

    foreach ($test_urls as $type => $url) {
        $response = wp_remote_head($url, [
            'timeout' => 10,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            $status = 'ERROR';
            $message = $response->get_error_message();
            $color = 'red';
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $status = $status_code;

            if ($status_code == 200) {
                $message = '✅ File exists and is accessible';
                $color = 'green';
            } elseif ($status_code == 403) {
                $message = '❌ Forbidden - Check S3 bucket policy and CloudFront permissions';
                $color = 'red';
            } elseif ($status_code == 404) {
                $message = '❌ Not Found - File does not exist at this path';
                $color = 'orange';
            } else {
                $message = wp_remote_retrieve_response_message($response);
                $color = 'red';
            }
        }

        echo "<tr>";
        echo "<td>{$type}</td>";
        echo "<td style='font-family: monospace; font-size: 11px;'><a href='{$url}' target='_blank'>" . esc_html($url) . "</a></td>";
        echo "<td style='color: {$color}; font-weight: bold;'>{$status}</td>";
        echo "<td style='color: {$color};'>{$message}</td>";
        echo "</tr>\n";
    }

    echo "</table>\n";
}

echo "<h3>Possible S3 Bucket Structures to Check</h3>\n";
echo "<p>Your tours might be stored in one of these structures:</p>\n";
echo "<ol>\n";
echo "<li><code>s3://bucket/tours/TOUR_ID/index.htm</code> (New structure)</li>\n";
echo "<li><code>s3://bucket/tours/Tour-Name/index.htm</code> (Legacy with dashes)</li>\n";
echo "<li><code>s3://bucket/tours/Tour Name/index.htm</code> (Legacy with spaces)</li>\n";
echo "<li><code>s3://bucket/TOUR_ID/index.htm</code> (No 'tours' prefix)</li>\n";
echo "<li><code>s3://bucket/Tour-Name/index.htm</code> (No 'tours' prefix, legacy)</li>\n";
echo "</ol>\n";

echo "<h3>AWS CLI Commands to Check</h3>\n";
echo "<pre>\n";
echo "# List all tours in bucket\n";
echo "aws s3 ls s3://{$s3_bucket}/tours/ --recursive | grep index.htm\n\n";
echo "# Check specific tour\n";
echo "aws s3 ls s3://{$s3_bucket}/tours/20251016_050212_enj0cply/\n\n";
echo "# Check bucket policy\n";
echo "aws s3api get-bucket-policy --bucket {$s3_bucket}\n";
echo "</pre>\n";

echo "<h3>Next Steps</h3>\n";
echo "<ol>\n";
echo "<li>Run the AWS CLI commands above to verify the actual S3 structure</li>\n";
echo "<li>If files exist but return 403, check bucket policy allows public read</li>\n";
echo "<li>If using CloudFront, verify CloudFront has access to the S3 bucket</li>\n";
echo "<li>Check if tours are in 'tours/' subdirectory or root of bucket</li>\n";
echo "</ol>\n";

// Check database for tour metadata
echo "<h3>Database Tour Metadata</h3>\n";
global $wpdb;
$metadata_table = $wpdb->prefix . 'h3tm_tour_metadata';

if ($wpdb->get_var("SHOW TABLES LIKE '{$metadata_table}'") == $metadata_table) {
    $tours = $wpdb->get_results("SELECT tour_id, tour_slug, tour_name FROM {$metadata_table} LIMIT 10");

    if ($tours) {
        echo "<table border='1' cellpadding='5'>\n";
        echo "<tr><th>Tour ID</th><th>Slug</th><th>Name</th></tr>\n";
        foreach ($tours as $tour) {
            echo "<tr>";
            echo "<td>{$tour->tour_id}</td>";
            echo "<td>{$tour->tour_slug}</td>";
            echo "<td>{$tour->tour_name}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>No tours found in metadata table</p>\n";
    }
} else {
    echo "<p style='color: orange;'>Metadata table does not exist</p>\n";
}
