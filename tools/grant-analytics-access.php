<?php
/**
 * Guide to grant Google Analytics access to service account
 * Run: php grant-analytics-access.php
 */

echo "H3 Tour Management - Grant Analytics Access\n";
echo "===========================================\n\n";

$wp_root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
$credentials_file = $wp_root . '/service-account-credentials.json';

// Get service account email
if (!file_exists($credentials_file)) {
    echo "ERROR: No credentials file found!\n";
    exit(1);
}

$credentials = json_decode(file_get_contents($credentials_file), true);
$service_account_email = $credentials['client_email'] ?? 'unknown';

echo "SUCCESS: Authentication is working! ✓\n\n";
echo "Now we need to grant Analytics access to your service account.\n\n";

echo "Service Account Email:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "➜ " . $service_account_email . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Step-by-Step Instructions:\n";
echo "==========================\n\n";

echo "1. Open Google Analytics:\n";
echo "   https://analytics.google.com/\n\n";

echo "2. Make sure you're viewing the correct property:\n";
echo "   - Look at the top of the page\n";
echo "   - You should see your property name\n";
echo "   - The property ID should be: 491286260\n\n";

echo "3. Go to Admin (gear icon at bottom left)\n\n";

echo "4. In the PROPERTY column (middle column):\n";
echo "   Click on 'Property Access Management'\n\n";

echo "5. Click the blue '+' button (top right)\n\n";

echo "6. In the 'Add users and groups' dialog:\n";
echo "   a. Email addresses: PASTE this email:\n";
echo "      " . $service_account_email . "\n";
echo "   b. Under 'Assign roles', check: Viewer\n";
echo "   c. Click 'Add' button\n\n";

echo "7. You should see the service account in the list now\n\n";

echo "Alternative Method (if above doesn't work):\n";
echo "===========================================\n";
echo "Sometimes the UI is different. Try this:\n\n";
echo "1. In Admin, look for 'Account Access Management' or 'User Management'\n";
echo "2. Add the service account email there\n";
echo "3. Make sure it has at least 'Read & Analyze' permissions\n\n";

echo "Verify the Property ID:\n";
echo "=======================\n";
echo "While in Admin, verify your property details:\n";
echo "- Property name: (your property)\n";
echo "- Property ID: Should show something like: 491286260\n";
echo "- If the ID is different, we need to update it in the plugin\n\n";

// Test if we can connect
$autoload = $wp_root . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    
    echo "Current Configuration:\n";
    echo "=====================\n";
    echo "Property ID in plugin: properties/491286260\n";
    echo "Service account: " . $service_account_email . "\n\n";
    
    echo "After Adding Access:\n";
    echo "===================\n";
    echo "1. Wait 1-2 minutes for permissions to propagate\n";
    echo "2. Try sending a test email again\n";
    echo "3. If it still fails, run this script again\n\n";
    
    // Quick test
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
        
        $client->setApplicationName("H3VT Access Test");
        $client->setAuthConfig($credentials_file);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        
        $analytics = new Google_Service_AnalyticsData($client);
        
        echo "Testing connection...\n";
        try {
            $response = $analytics->properties->getMetadata("properties/491286260");
            echo "✓ SUCCESS! Analytics access is working!\n";
            echo "You can now send analytics emails.\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '403') !== false) {
                echo "✗ Still no access. Please follow the steps above.\n";
                echo "\nMake sure:\n";
                echo "- You added: " . $service_account_email . "\n";
                echo "- With 'Viewer' role\n";
                echo "- To the correct Analytics property\n";
            } else {
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
    } catch (Exception $e) {
        echo "Setup error: " . $e->getMessage() . "\n";
    }
}

echo "\nNeed Different Property ID?\n";
echo "============================\n";
echo "If your Analytics property ID is different from 491286260,\n";
echo "let me know and I'll update the plugin configuration.\n";