<?php
/**
 * Setup script for Google Analytics integration
 * Run this from command line: php setup-analytics.php
 */

echo "H3 Tour Management - Google Analytics Setup\n";
echo "==========================================\n\n";

// Check current directory
$current_dir = getcwd();
$plugin_dir = dirname(dirname(__FILE__));
$wp_root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));

echo "Current directory: " . $current_dir . "\n";
echo "WordPress root: " . $wp_root . "\n\n";

// Step 1: Check for composer.json
$composer_file = $wp_root . '/composer.json';
if (!file_exists($composer_file)) {
    echo "ERROR: composer.json not found at: " . $composer_file . "\n";
    echo "The composer.json file should already exist in your WordPress root.\n";
    exit(1);
} else {
    echo "✓ Found composer.json\n";
}

// Step 2: Check for vendor directory
$vendor_dir = $wp_root . '/vendor';
$autoload_file = $vendor_dir . '/autoload.php';

if (!file_exists($autoload_file)) {
    echo "\n⚠ Google API Client Library not installed.\n";
    echo "Please run the following commands:\n\n";
    echo "cd \"" . $wp_root . "\"\n";
    echo "composer install\n\n";
    echo "After running composer install, run this script again.\n";
} else {
    echo "✓ Google API Client Library is installed\n";
}

// Step 3: Check for credentials file
$credentials_file = $wp_root . '/service-account-credentials.json';
$sample_credentials_file = $wp_root . '/service-account-credentials-sample.json';

if (!file_exists($credentials_file)) {
    echo "\n⚠ Service account credentials not found.\n";
    echo "Expected location: " . $credentials_file . "\n\n";
    
    // Create sample file if it doesn't exist
    if (!file_exists($sample_credentials_file)) {
        echo "Creating sample credentials file...\n";
        
        $sample_content = '{
  "type": "service_account",
  "project_id": "your-project-id",
  "private_key_id": "your-private-key-id",
  "private_key": "-----BEGIN PRIVATE KEY-----\\nYOUR-PRIVATE-KEY-HERE\\n-----END PRIVATE KEY-----\\n",
  "client_email": "your-service-account@your-project-id.iam.gserviceaccount.com",
  "client_id": "your-client-id",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "https://www.googleapis.com/robot/v1/metadata/x509/your-service-account%40your-project-id.iam.gserviceaccount.com"
}';
        
        file_put_contents($sample_credentials_file, $sample_content);
        echo "✓ Created sample file at: " . $sample_credentials_file . "\n\n";
    }
    
    echo "To set up Google Analytics:\n";
    echo "1. Go to Google Cloud Console: https://console.cloud.google.com/\n";
    echo "2. Create or select a project\n";
    echo "3. Enable the Google Analytics Data API\n";
    echo "4. Create a service account:\n";
    echo "   - Go to 'IAM & Admin' > 'Service Accounts'\n";
    echo "   - Click 'Create Service Account'\n";
    echo "   - Name it something like 'H3VT Analytics Reader'\n";
    echo "   - Click 'Create and Continue'\n";
    echo "   - Skip the optional steps and click 'Done'\n";
    echo "5. Create a key for the service account:\n";
    echo "   - Click on your new service account\n";
    echo "   - Go to the 'Keys' tab\n";
    echo "   - Click 'Add Key' > 'Create New Key'\n";
    echo "   - Choose 'JSON' format\n";
    echo "   - Download the file\n";
    echo "6. Rename the downloaded file to: service-account-credentials.json\n";
    echo "7. Place it at: " . $credentials_file . "\n";
    echo "8. In Google Analytics:\n";
    echo "   - Go to Admin > Property > Property Access Management\n";
    echo "   - Add the service account email (from the JSON file) with 'Viewer' access\n";
} else {
    echo "✓ Service account credentials found\n";
    
    // Validate the JSON
    $json_content = file_get_contents($credentials_file);
    $credentials = json_decode($json_content, true);
    
    if (!$credentials || !isset($credentials['client_email'])) {
        echo "⚠ WARNING: Credentials file may be invalid\n";
    } else {
        echo "  Service account: " . $credentials['client_email'] . "\n";
        echo "\nMake sure this email has 'Viewer' access in your Google Analytics property.\n";
    }
}

// Step 4: Test the connection
if (file_exists($autoload_file) && file_exists($credentials_file)) {
    echo "\n\nTesting Google Analytics connection...\n";
    
    require_once $autoload_file;
    
    try {
        $client = new Google_Client();
        $client->setApplicationName("H3VT Analytics Test");
        $client->setAuthConfig($credentials_file);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        
        $analytics_service = new Google_Service_AnalyticsData($client);
        
        // Try to get property metadata
        $property_id = "properties/491286260";
        $response = $analytics_service->properties->getMetadata($property_id);
        
        echo "✓ Successfully connected to Google Analytics!\n";
        echo "  Property: " . $property_id . "\n";
    } catch (Exception $e) {
        echo "✗ Connection failed: " . $e->getMessage() . "\n";
        echo "\nPossible issues:\n";
        echo "1. Service account doesn't have access to the Analytics property\n";
        echo "2. Analytics Data API is not enabled in Google Cloud Console\n";
        echo "3. Invalid credentials file\n";
    }
}

echo "\n\nSetup check complete.\n";