<?php
/**
 * Test Script for Alternative URL Handlers
 * Run this script to test all URL handling approaches
 *
 * Usage: php test-url-handlers.php [site-url] [test-tour-name]
 */

// Configuration
$site_url = $argv[1] ?? 'http://localhost';
$test_tour = $argv[2] ?? 'Test-Tour';

// Remove trailing slash
$site_url = rtrim($site_url, '/');

echo "Testing H3TM URL Handlers\n";
echo "=========================\n";
echo "Site URL: " . $site_url . "\n";
echo "Test Tour: " . $test_tour . "\n\n";

// Test URLs to check
$test_urls = array(
    'h3panos_dir' => $site_url . '/h3panos/' . $test_tour . '/',
    'h3panos_file' => $site_url . '/h3panos/' . $test_tour . '/app.js',
    'h3panos_index' => $site_url . '/h3panos/' . $test_tour . '/index.htm',
    'endpoint_dir' => $site_url . '/h3tours/' . $test_tour . '/',
    'endpoint_file' => $site_url . '/h3tours/' . $test_tour . '/app.js',
    'rest_api' => $site_url . '/wp-json/h3tm/v1/tour/' . $test_tour,
    'direct_handler' => $site_url . '/h3panos-direct.php'
);

// Test special characters
$special_tour = 'Tour%20With%20Spaces';
$test_urls['h3panos_special'] = $site_url . '/h3panos/' . $special_tour . '/';

/**
 * Test URL and return detailed information
 */
function test_url($url, $name) {
    echo "Testing: " . $name . "\n";
    echo "URL: " . $url . "\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'H3TM-URL-Handler-Test/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        echo "❌ ERROR: " . $error . "\n";
        return false;
    }

    // Analyze response
    $status = $http_code == 200 ? '✅ SUCCESS' : '❌ FAILED';
    echo "Status: " . $status . " (HTTP " . $http_code . ")\n";
    echo "Content-Type: " . $content_type . "\n";
    echo "Response Time: " . round($total_time * 1000, 2) . "ms\n";

    // Check for handler signatures in response
    $headers = substr($response, 0, strpos($response, "\r\n\r\n"));
    $body = substr($response, strpos($response, "\r\n\r\n") + 4);

    // Detect which handler served the request
    $handler_detected = 'Unknown';
    if (strpos($body, 'H3TM 404 Handler') !== false) {
        $handler_detected = '404 Handler';
    } elseif (strpos($body, 'H3TM Direct Handler') !== false) {
        $handler_detected = 'Direct Handler';
    } elseif (strpos($body, 'H3TM Action Hook') !== false) {
        $handler_detected = 'Action Hook';
    } elseif (strpos($body, 'H3TM Endpoint Handler') !== false) {
        $handler_detected = 'Endpoint Handler';
    } elseif (strpos($headers, 'wp-json') !== false) {
        $handler_detected = 'REST API';
    }

    echo "Handler: " . $handler_detected . "\n";

    // Check for analytics injection
    if (strpos($body, 'gtag') !== false) {
        echo "Analytics: ✅ Injected\n";
    } elseif ($http_code == 200 && strpos($content_type, 'text/html') !== false) {
        echo "Analytics: ❌ Missing\n";
    }

    // Check cache headers
    if (strpos($headers, 'Cache-Control') !== false) {
        echo "Caching: ✅ Headers present\n";
    } else {
        echo "Caching: ⚠️ No cache headers\n";
    }

    echo "---\n\n";

    return $http_code == 200;
}

/**
 * Test WordPress REST API availability
 */
function test_wordpress_api($site_url) {
    echo "Testing WordPress REST API availability...\n";
    $api_url = $site_url . '/wp-json/wp/v2/';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        echo "✅ WordPress REST API is available\n";
        return true;
    } else {
        echo "❌ WordPress REST API is not available (HTTP " . $http_code . ")\n";
        return false;
    }
}

/**
 * Check for direct handler file
 */
function check_direct_handler($site_url) {
    echo "Checking for direct handler file...\n";
    $handler_url = $site_url . '/h3panos-direct.php';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $handler_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 || $http_code == 404) {
        echo "✅ Direct handler file is accessible\n";
        return true;
    } else {
        echo "❌ Direct handler file not found or not accessible\n";
        return false;
    }
}

// Run tests
echo "Preliminary Checks:\n";
echo "==================\n";

$wp_api_available = test_wordpress_api($site_url);
echo "\n";

$direct_handler_available = check_direct_handler($site_url);
echo "\n";

echo "URL Handler Tests:\n";
echo "==================\n";

$results = array();
foreach ($test_urls as $type => $url) {
    $results[$type] = test_url($url, $type);
}

// Summary
echo "Test Summary:\n";
echo "=============\n";

$total_tests = count($results);
$successful_tests = array_sum($results);

echo "Total Tests: " . $total_tests . "\n";
echo "Successful: " . $successful_tests . "\n";
echo "Failed: " . ($total_tests - $successful_tests) . "\n\n";

if ($successful_tests > 0) {
    echo "✅ At least one URL handler is working!\n";

    // Recommend best handler based on results
    if ($results['h3panos_dir'] && $results['h3panos_file']) {
        echo "🎯 Recommendation: h3panos URLs are working (likely 404 Handler or Action Hook)\n";
    } elseif ($results['endpoint_dir'] && $results['endpoint_file']) {
        echo "🎯 Recommendation: Endpoint handler is working\n";
    } elseif ($results['direct_handler']) {
        echo "🎯 Recommendation: Direct handler is working\n";
    }
} else {
    echo "❌ No URL handlers are working. Check configuration:\n";
    echo "   - Verify S3 bucket and credentials\n";
    echo "   - Check WordPress plugin activation\n";
    echo "   - Review server error logs\n";
    echo "   - Test with a known existing tour\n";
}

echo "\nConfiguration Checklist:\n";
echo "========================\n";
echo "□ S3 bucket configured and accessible\n";
echo "□ AWS credentials valid\n";
echo "□ H3TM plugin activated\n";
echo "□ Test tour exists in S3 bucket\n";
echo "□ WordPress permalinks enabled\n";
echo "□ .htaccess file writable (for Direct Handler)\n";

if (!$wp_api_available) {
    echo "⚠️  WordPress REST API is disabled - Endpoint Handler won't work\n";
}

if (!$direct_handler_available) {
    echo "⚠️  Direct Handler file not accessible - check file permissions\n";
}

echo "\nTo fix issues:\n";
echo "==============\n";
echo "1. Check WordPress Admin → 3D Tours → URL Handlers\n";
echo "2. Test S3 connection in S3 Settings page\n";
echo "3. Verify tour exists: " . $site_url . "/wp-admin/admin.php?page=h3-tour-management\n";
echo "4. Check server error logs for detailed error messages\n\n";

// Exit with appropriate code
exit($successful_tests > 0 ? 0 : 1);
?>