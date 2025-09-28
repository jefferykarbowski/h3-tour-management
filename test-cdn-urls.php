<?php
/**
 * Test CDN URL generation for tours with spaces
 * Run: php test-cdn-urls.php
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../../../wp-load.php');

// Load our plugin files
require_once(__DIR__ . '/includes/class-h3tm-cdn-helper.php');

// Test tours with different naming patterns
$test_tours = array(
    'Arden Anderson',
    'Tour-With-Dashes',
    'Simple',
    'Tour With Multiple Spaces'
);

// Initialize CDN helper
$cdn_helper = H3TM_CDN_Helper::get_instance();

echo "CDN URL Test Results\n";
echo "====================\n\n";

// Check if CloudFront is enabled
$is_cloudfront = $cdn_helper->is_cloudfront_enabled();
echo "CloudFront Status: " . ($is_cloudfront ? "ENABLED" : "DISABLED") . "\n\n";

foreach ($test_tours as $tour) {
    echo "Tour: '$tour'\n";
    echo "-------------------------------\n";

    // Get URLs for index.htm
    $urls = $cdn_helper->get_tour_urls($tour, 'index.htm');
    echo "URLs for index.htm:\n";
    foreach ($urls as $i => $url) {
        echo "  [" . ($i + 1) . "] $url\n";
    }

    // Get base URL
    $base_url = $cdn_helper->get_tour_base_url($tour);
    echo "Base URL: $base_url\n";

    echo "\n";
}

// Expected URLs for Arden Anderson
echo "Expected URLs for 'Arden Anderson' with CloudFront:\n";
echo "-----------------------------------------------------\n";
echo "Working URL (per user): https://d14z8re58oj029.cloudfront.net/Arden-Anderson/index.htm\n";
echo "Our Generated URL:     ";
$arden_urls = $cdn_helper->get_tour_urls('Arden Anderson', 'index.htm');
echo $arden_urls[0] . "\n";

if ($is_cloudfront) {
    $expected = 'https://d14z8re58oj029.cloudfront.net/Arden-Anderson/index.htm';
    $generated = $arden_urls[0];

    // Compare structure (we don't know actual domain)
    if (strpos($generated, '/tours/') !== false) {
        echo "\n⚠️  WARNING: Generated URL contains '/tours/' but shouldn't (origin already has /tours)\n";
    } else {
        echo "\n✅ URL structure looks correct (no /tours/ prefix)\n";
    }

    if (strpos($generated, 'Arden-Anderson') !== false) {
        echo "✅ Spaces converted to dashes correctly\n";
    } else {
        echo "⚠️  WARNING: Space-to-dash conversion may have issues\n";
    }
}