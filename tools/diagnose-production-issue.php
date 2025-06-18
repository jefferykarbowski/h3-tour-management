<?php
/**
 * Diagnostic tool for production analytics issues
 * 
 * Run this on your production server to diagnose the invalid_grant error
 */

// Load WordPress
require_once($_SERVER["DOCUMENT_ROOT"] . "/wp-load.php");

echo "=== H3 Tour Management - Production Diagnostics ===\n\n";

// 1. Check environment
echo "1. ENVIRONMENT CHECK:\n";
echo "   - Document Root: " . $_SERVER["DOCUMENT_ROOT"] . "\n";
echo "   - Host: " . ($_SERVER['HTTP_HOST'] ?? 'CLI') . "\n";
echo "   - WordPress URL: " . site_url() . "\n";
echo "   - Plugin Path: " . plugin_dir_path(dirname(__FILE__)) . "\n\n";

// 2. Check credentials file
$root = realpath($_SERVER["DOCUMENT_ROOT"]);
$credentials_path = $root . '/service-account-credentials.json';

echo "2. CREDENTIALS FILE CHECK:\n";
echo "   - Expected Path: " . $credentials_path . "\n";
echo "   - File Exists: " . (file_exists($credentials_path) ? 'YES' : 'NO') . "\n";

if (file_exists($credentials_path)) {
    $creds = json_decode(file_get_contents($credentials_path), true);
    if ($creds) {
        echo "   - Type: " . ($creds['type'] ?? 'Unknown') . "\n";
        echo "   - Project ID: " . ($creds['project_id'] ?? 'Unknown') . "\n";
        echo "   - Client Email: " . ($creds['client_email'] ?? 'Unknown') . "\n";
        echo "   - Private Key ID: " . substr($creds['private_key_id'] ?? '', 0, 10) . "...\n";
    } else {
        echo "   - ERROR: Invalid JSON in credentials file\n";
    }
}
echo "\n";

// 3. Check Google API Client
$autoload_path = $root . '/vendor/autoload.php';
echo "3. GOOGLE API CLIENT CHECK:\n";
echo "   - Autoload Path: " . $autoload_path . "\n";
echo "   - Autoload Exists: " . (file_exists($autoload_path) ? 'YES' : 'NO') . "\n";

if (file_exists($autoload_path)) {
    require_once $autoload_path;
    echo "   - Google Client Class: " . (class_exists('Google_Client') ? 'LOADED' : 'NOT FOUND') . "\n";
}
echo "\n";

// 4. Test authentication
echo "4. AUTHENTICATION TEST:\n";
if (file_exists($credentials_path) && file_exists($autoload_path)) {
    try {
        $client = new Google_Client();
        $client->setApplicationName("H3VT Analytics Test");
        $client->setAuthConfig($credentials_path);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        
        // Try to get access token
        $token = $client->fetchAccessTokenWithAssertion();
        
        if (isset($token['error'])) {
            echo "   - Token Error: " . $token['error'] . "\n";
            echo "   - Description: " . ($token['error_description'] ?? 'No description') . "\n";
        } else {
            echo "   - Access Token: OBTAINED SUCCESSFULLY\n";
            echo "   - Token Type: " . ($token['token_type'] ?? 'Unknown') . "\n";
            echo "   - Expires In: " . ($token['expires_in'] ?? 'Unknown') . " seconds\n";
        }
    } catch (Exception $e) {
        echo "   - Exception: " . $e->getMessage() . "\n";
    }
} else {
    echo "   - SKIPPED: Missing credentials or autoload file\n";
}
echo "\n";

// 5. Check GA4 property access
echo "5. GA4 PROPERTY ACCESS TEST:\n";
$property_id = get_option('h3tm_ga_property_id', '491286260');
echo "   - Property ID: " . $property_id . "\n";

if (isset($client) && !isset($token['error'])) {
    try {
        $analytics = new Google_Service_AnalyticsData($client);
        
        // Try a simple metadata request
        $response = $analytics->properties->getMetadata(
            'properties/' . $property_id . '/metadata',
            []
        );
        
        echo "   - Metadata Access: SUCCESS\n";
        echo "   - Dimensions Count: " . count($response->getDimensions()) . "\n";
        echo "   - Metrics Count: " . count($response->getMetrics()) . "\n";
    } catch (Exception $e) {
        echo "   - Access Error: " . $e->getMessage() . "\n";
        
        // Check if it's a permissions error
        if (strpos($e->getMessage(), '403') !== false) {
            echo "   - SOLUTION: Grant the service account 'Viewer' access to GA4 property\n";
            echo "   - Service Account Email: " . ($creds['client_email'] ?? 'Unknown') . "\n";
        }
    }
}
echo "\n";

// 6. Alternative credentials locations
echo "6. ALTERNATIVE CREDENTIAL LOCATIONS:\n";
$alt_paths = [
    plugin_dir_path(dirname(__FILE__)) . 'service-account-credentials.json',
    WP_CONTENT_DIR . '/service-account-credentials.json',
    ABSPATH . 'service-account-credentials.json',
    dirname(ABSPATH) . '/service-account-credentials.json',
];

foreach ($alt_paths as $path) {
    echo "   - " . $path . ": " . (file_exists($path) ? 'EXISTS' : 'Not found') . "\n";
}

echo "\n=== END DIAGNOSTICS ===\n";

// Recommendations
echo "\nRECOMMENDATIONS:\n";
echo "1. Ensure the service account has 'Viewer' access to GA4 property $property_id\n";
echo "2. Verify the credentials file is the same one that works on local\n";
echo "3. Check that the service account is active in Google Cloud Console\n";
echo "4. Consider using environment-specific credentials files\n";