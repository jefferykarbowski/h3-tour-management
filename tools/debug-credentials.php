<?php
/**
 * Debug script to check service account credentials and permissions
 * Run: php debug-credentials.php
 */

echo "H3 Tour Management - Credentials Debug Tool\n";
echo "==========================================\n\n";

$wp_root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
$credentials_file = $wp_root . '/service-account-credentials.json';

// Step 1: Check if credentials file exists
if (!file_exists($credentials_file)) {
    echo "ERROR: Credentials file not found at:\n";
    echo $credentials_file . "\n";
    exit(1);
}

echo "✓ Found credentials file\n\n";

// Step 2: Parse and display credentials info
$json_content = file_get_contents($credentials_file);
$credentials = json_decode($json_content, true);

if (!$credentials) {
    echo "ERROR: Invalid JSON in credentials file\n";
    exit(1);
}

echo "Service Account Details:\n";
echo "========================\n";
echo "Type: " . ($credentials['type'] ?? 'Not specified') . "\n";
echo "Project ID: " . ($credentials['project_id'] ?? 'Not specified') . "\n";
echo "Client Email: " . ($credentials['client_email'] ?? 'Not specified') . "\n";
echo "Client ID: " . ($credentials['client_id'] ?? 'Not specified') . "\n";
echo "Private Key ID: " . ($credentials['private_key_id'] ?? 'Not specified') . "\n";
echo "\n";

// Highlight the important email
if (isset($credentials['client_email'])) {
    echo "IMPORTANT: The following email needs access in Google Analytics:\n";
    echo "➜ " . $credentials['client_email'] . "\n\n";
}

// Step 3: Check Google API client
$autoload_file = $wp_root . '/vendor/autoload.php';
if (!file_exists($autoload_file)) {
    echo "⚠ Google API Client not installed\n";
    echo "Run: composer install\n";
    exit(1);
}

require_once $autoload_file;
echo "✓ Google API Client is installed\n\n";

// Step 4: Test authentication
echo "Testing Authentication...\n";
echo "========================\n";

try {
    $client = new Google_Client();
    $client->setApplicationName("H3VT Analytics Debug");
    $client->setAuthConfig($credentials_file);
    $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
    
    // Force a token refresh to test authentication
    $client->fetchAccessTokenWithAssertion();
    $token = $client->getAccessToken();
    
    if ($token && isset($token['access_token'])) {
        echo "✓ Authentication successful!\n";
        echo "  Token type: " . ($token['token_type'] ?? 'unknown') . "\n";
        echo "  Expires in: " . ($token['expires_in'] ?? 'unknown') . " seconds\n\n";
    } else {
        echo "✗ Authentication failed - no access token received\n\n";
    }
} catch (Exception $e) {
    echo "✗ Authentication failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), 'invalid_grant') !== false) {
        echo "SOLUTION: This error usually means one of the following:\n";
        echo "1. The service account doesn't exist anymore\n";
        echo "2. The private key in the JSON file is invalid\n";
        echo "3. The service account was deleted and recreated\n";
        echo "4. Clock sync issue (rare)\n\n";
        echo "TO FIX:\n";
        echo "1. Go to Google Cloud Console\n";
        echo "2. Create a NEW service account key\n";
        echo "3. Download and replace the credentials file\n";
    }
}

// Step 5: Test Analytics API access
echo "Testing Google Analytics Access...\n";
echo "=================================\n";

try {
    $analytics_service = new Google_Service_AnalyticsData($client);
    $property_id = "properties/491286260";
    
    echo "Attempting to access property: " . $property_id . "\n";
    
    // Try to get property metadata
    $response = $analytics_service->properties->getMetadata($property_id);
    
    echo "✓ Successfully connected to Google Analytics!\n";
    echo "  Property name: " . $response->getName() . "\n\n";
    
} catch (Exception $e) {
    echo "✗ Google Analytics access failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), '403') !== false || strpos($e->getMessage(), 'PERMISSION_DENIED') !== false) {
        echo "SOLUTION: The service account doesn't have access to the Analytics property.\n\n";
        echo "TO FIX:\n";
        echo "1. Go to Google Analytics (analytics.google.com)\n";
        echo "2. Select your property\n";
        echo "3. Go to Admin > Property > Property Access Management\n";
        echo "4. Click the '+' button to add a user\n";
        echo "5. Add this email: " . ($credentials['client_email'] ?? 'Check JSON file') . "\n";
        echo "6. Select 'Viewer' role\n";
        echo "7. Click 'Add'\n\n";
        echo "Make sure you're adding it to the correct property (GA4 property ID: 491286260)\n";
    } elseif (strpos($e->getMessage(), '404') !== false) {
        echo "SOLUTION: The Analytics property ID might be wrong.\n";
        echo "Current property ID: " . $property_id . "\n";
        echo "Verify this matches your GA4 property.\n";
    }
}

// Step 6: Additional checks
echo "Additional Checks:\n";
echo "==================\n";

// Check if Analytics Data API is enabled
echo "Required APIs in Google Cloud Console:\n";
echo "- Google Analytics Data API (MUST be enabled)\n\n";

echo "Checklist:\n";
echo "□ Google Analytics Data API is enabled in Google Cloud Console\n";
echo "□ Service account email has 'Viewer' access in GA4 property\n";
echo "□ Using the correct GA4 property ID (491286260)\n";
echo "□ Credentials file is from the correct Google Cloud project\n\n";

echo "Debug complete.\n";