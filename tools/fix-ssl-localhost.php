<?php
/**
 * Fix SSL certificate issues on localhost
 * Run: php fix-ssl-localhost.php
 */

echo "H3 Tour Management - SSL Fix for Localhost\n";
echo "==========================================\n\n";

$wp_root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));

// Option 1: Download CA certificates
echo "Option 1: Download CA Certificate Bundle\n";
echo "========================================\n";

$cacert_url = 'https://curl.se/ca/cacert.pem';
$cacert_path = $wp_root . '/cacert.pem';

echo "Downloading CA certificates...\n";
$ch = curl_init($cacert_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Temporarily disable to download
$cacert_content = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && $cacert_content) {
    file_put_contents($cacert_path, $cacert_content);
    echo "✓ Downloaded CA certificate bundle to: " . $cacert_path . "\n\n";
    
    echo "Now you need to configure PHP to use this certificate:\n";
    echo "1. Find your php.ini file (create phpinfo.php with <?php phpinfo(); ?> to find it)\n";
    echo "2. Look for: curl.cainfo\n";
    echo "3. Set it to: curl.cainfo = \"" . str_replace('/', '\\', $cacert_path) . "\"\n";
    echo "4. Also set: openssl.cafile = \"" . str_replace('/', '\\', $cacert_path) . "\"\n";
    echo "5. Restart your web server\n\n";
} else {
    echo "✗ Failed to download CA certificates\n\n";
}

// Option 2: Create a local fix
echo "Option 2: Quick Fix for Development (Less Secure)\n";
echo "=================================================\n";

$fix_file_content = '<?php
/**
 * SSL Certificate Fix for Local Development
 * Add this to your wp-config.php or at the start of the plugin
 */

// Only for local development - DO NOT use in production!
if (isset($_SERVER[\'HTTP_HOST\']) && (
    strpos($_SERVER[\'HTTP_HOST\'], \'localhost\') !== false ||
    strpos($_SERVER[\'HTTP_HOST\'], \'.local\') !== false ||
    strpos($_SERVER[\'HTTP_HOST\'], \'127.0.0.1\') !== false
)) {
    // Disable SSL verification for Google API Client
    add_action(\'init\', function() {
        if (class_exists(\'Google_Client\')) {
            // This will be applied to all Google_Client instances
            Google_Client::$httpHandler = function($request, $options = []) {
                $options[\'verify\'] = false;
                $httpHandler = \\GuzzleHttp\\HandlerStack::create();
                $client = new \\GuzzleHttp\\Client([\'handler\' => $httpHandler]);
                return $client->send($request, $options);
            };
        }
    }, 1);
    
    // Alternative: Set stream context options
    stream_context_set_default([
        \'ssl\' => [
            \'verify_peer\' => false,
            \'verify_peer_name\' => false,
        ],
    ]);
}
';

$fix_file_path = $wp_root . '/wp-content/plugins/h3-tour-management/includes/ssl-localhost-fix.php';
file_put_contents($fix_file_path, $fix_file_content);

echo "Created SSL fix file at:\n";
echo $fix_file_path . "\n\n";

// Option 3: Update the analytics class directly
echo "Option 3: Patching Analytics Class\n";
echo "==================================\n";

$analytics_file = $wp_root . '/wp-content/plugins/h3-tour-management/includes/class-h3tm-analytics.php';
$analytics_content = file_get_contents($analytics_file);

// Check if fix is already applied
if (strpos($analytics_content, 'verify_peer') === false) {
    // Add SSL fix after creating the client
    $search = '$client = new Google_Client();';
    $replace = '$client = new Google_Client();
        
        // SSL fix for localhost development
        if (strpos($_SERVER[\'HTTP_HOST\'] ?? \'\', \'localhost\') !== false || 
            strpos($_SERVER[\'HTTP_HOST\'] ?? \'\', \'.local\') !== false) {
            $httpClient = new \\GuzzleHttp\\Client([
                \'verify\' => false
            ]);
            $client->setHttpClient($httpClient);
        }';
    
    $analytics_content = str_replace($search, $replace, $analytics_content);
    file_put_contents($analytics_file, $analytics_content);
    
    echo "✓ Patched analytics class with SSL fix\n";
} else {
    echo "✓ Analytics class already has SSL fix\n";
}

echo "\n";
echo "Option 4: Local Sites SSL Fix\n";
echo "=============================\n";
echo "Since you're using Local Sites (by Flywheel), try these:\n\n";
echo "1. Trust the Local Sites certificate:\n";
echo "   - Open Local Sites\n";
echo "   - Right-click on your site\n";
echo "   - Select 'SSL' > 'Trust'\n\n";
echo "2. Use the built-in PHP settings:\n";
echo "   - In Local Sites, go to your site\n";
echo "   - Click on 'Open Site Shell'\n";
echo "   - Run: php -i | grep -i ssl\n";
echo "   - Check the SSL settings\n\n";

echo "Testing the fix...\n";
echo "==================\n";

// Test if we can connect now
$autoload = $wp_root . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    
    $credentials_file = $wp_root . '/service-account-credentials.json';
    if (file_exists($credentials_file)) {
        try {
            $client = new Google_Client();
            $client->setApplicationName("SSL Test");
            $client->setAuthConfig($credentials_file);
            $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
            
            // Apply the localhost fix
            if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false) {
                $httpClient = new \GuzzleHttp\Client([
                    'verify' => false
                ]);
                $client->setHttpClient($httpClient);
            }
            
            $client->fetchAccessTokenWithAssertion();
            echo "✓ SSL fix successful! Authentication works.\n";
        } catch (Exception $e) {
            echo "✗ Still having issues: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nFix complete. Try sending a test email now.\n";