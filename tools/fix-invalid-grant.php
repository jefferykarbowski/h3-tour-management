<?php
/**
 * Fix invalid_grant error - Step by step guide
 * Run: php fix-invalid-grant.php
 */

echo "H3 Tour Management - Fix Invalid Grant Error\n";
echo "============================================\n\n";

$wp_root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
$credentials_file = $wp_root . '/service-account-credentials.json';

// Step 1: Check current credentials
echo "Step 1: Checking Current Credentials\n";
echo "====================================\n";

if (!file_exists($credentials_file)) {
    echo "✗ No credentials file found at:\n";
    echo "  " . $credentials_file . "\n\n";
    echo "You need to create one. Skip to Step 2.\n\n";
} else {
    $json_content = file_get_contents($credentials_file);
    $credentials = json_decode($json_content, true);
    
    if (!$credentials || !isset($credentials['client_email'])) {
        echo "✗ Invalid credentials file\n\n";
    } else {
        echo "Current service account email:\n";
        echo "➜ " . $credentials['client_email'] . "\n";
        echo "Project ID: " . ($credentials['project_id'] ?? 'unknown') . "\n\n";
        
        echo "IMPORTANT: Write down this email, you'll need it later!\n\n";
    }
}

echo "Step 2: Create New Service Account Credentials\n";
echo "==============================================\n\n";

echo "Since you're getting 'invalid_grant', we need NEW credentials.\n\n";

echo "1. Open Google Cloud Console:\n";
echo "   https://console.cloud.google.com/\n\n";

echo "2. SELECT THE CORRECT PROJECT:\n";
echo "   - Look at the top of the page for the project selector\n";
echo "   - Make sure you're in the right project\n";
echo "   - If unsure, create a new project\n\n";

echo "3. Enable the Google Analytics Data API:\n";
echo "   a. Go to: APIs & Services > Library\n";
echo "   b. Search for: Google Analytics Data API\n";
echo "   c. Click on it\n";
echo "   d. Click 'Enable' (if not already enabled)\n\n";

echo "4. Create Service Account:\n";
echo "   a. Go to: IAM & Admin > Service Accounts\n";
echo "   b. Click 'Create Service Account'\n";
echo "   c. Service account name: H3VT Analytics\n";
echo "   d. Service account ID: (auto-fills)\n";
echo "   e. Description: Analytics access for H3VT tours\n";
echo "   f. Click 'Create and Continue'\n";
echo "   g. Skip the optional steps (click 'Continue' then 'Done')\n\n";

echo "5. Create the Key:\n";
echo "   a. Click on your new service account (H3VT Analytics)\n";
echo "   b. Go to the 'Keys' tab\n";
echo "   c. Click 'Add Key' > 'Create new key'\n";
echo "   d. Choose 'JSON' format\n";
echo "   e. Click 'Create'\n";
echo "   f. The file will download automatically\n\n";

echo "6. Install the Key:\n";
echo "   a. Find the downloaded file (usually in Downloads folder)\n";
echo "   b. It will have a long name like: project-name-abc123.json\n";
echo "   c. Rename it to: service-account-credentials.json\n";
echo "   d. Move it to: " . $wp_root . "\\\n";
echo "   e. Replace the existing file if asked\n\n";

echo "Step 3: Grant Google Analytics Access\n";
echo "=====================================\n\n";

echo "The new service account needs access to your Analytics property:\n\n";

echo "1. Get the service account email:\n";
echo "   - Open your new service-account-credentials.json file\n";
echo "   - Find the 'client_email' field\n";
echo "   - Copy the email address\n\n";

echo "2. Add to Google Analytics:\n";
echo "   a. Go to: https://analytics.google.com/\n";
echo "   b. Make sure you're viewing the correct property\n";
echo "   c. Click the gear icon (Admin) at bottom left\n";
echo "   d. In the Property column, click 'Property Access Management'\n";
echo "   e. Click the '+' button (top right)\n";
echo "   f. Add users: paste the service account email\n";
echo "   g. Role: Select 'Viewer'\n";
echo "   h. Click 'Add'\n\n";

echo "Step 4: Verify Everything Works\n";
echo "================================\n\n";

// Try to load and test if autoload exists
$autoload = $wp_root . '/vendor/autoload.php';
if (file_exists($autoload) && file_exists($credentials_file)) {
    echo "Testing authentication...\n";
    require_once $autoload;
    
    try {
        $client = new Google_Client();
        
        // Apply localhost SSL fix
        if (strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false || 
            strpos($_SERVER['HTTP_HOST'] ?? 'localhost', '.local') !== false) {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false
            ]);
            $client->setHttpClient($httpClient);
        }
        
        $client->setApplicationName("H3VT Fix Test");
        $client->setAuthConfig($credentials_file);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        
        // Try to get a token
        $token = $client->fetchAccessTokenWithAssertion();
        
        if (isset($token['access_token'])) {
            echo "✓ Authentication successful!\n\n";
            
            // Try Analytics
            try {
                $analytics = new Google_Service_AnalyticsData($client);
                $property_id = "properties/491286260";
                $metadata = $analytics->properties->getMetadata($property_id);
                echo "✓ Google Analytics connection successful!\n";
                echo "  Property: " . $metadata->getName() . "\n\n";
                echo "Everything is working! Try sending a test email now.\n";
            } catch (Exception $e) {
                echo "✗ Analytics connection failed\n";
                echo "Error: " . $e->getMessage() . "\n\n";
                echo "Make sure the service account has 'Viewer' access to the Analytics property.\n";
            }
        } else {
            echo "✗ Authentication failed - no token received\n";
        }
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n\n";
        echo "Follow the steps above to create new credentials.\n";
    }
} else {
    echo "Run this script again after completing the steps above.\n";
}

echo "\nCommon Issues:\n";
echo "==============\n";
echo "1. Wrong Google account - make sure you're using the account that owns the Analytics\n";
echo "2. Wrong project - credentials must be from the correct Google Cloud project\n";
echo "3. API not enabled - Google Analytics Data API must be enabled\n";
echo "4. No Analytics access - service account email must have Viewer role\n";
echo "\n";