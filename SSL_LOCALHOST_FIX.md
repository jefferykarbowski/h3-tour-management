# Fix SSL Certificate Error on Localhost

## Quick Fix (Already Applied)

I've already patched the analytics class to disable SSL verification on localhost. Try sending a test email now - it should work!

## If Still Having Issues

### Option 1: Add to wp-config.php

Add this to your `wp-config.php` file (near the top, after `<?php`):

```php
// SSL Fix for Local Development Only
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
    strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false) {
    // Disable SSL verification for curl
    add_action('http_api_curl', function($handle) {
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
    });
}
```

### Option 2: Fix PHP Configuration

1. **Find your php.ini file**:
   - Create a file called `phpinfo.php` with: `<?php phpinfo(); ?>`
   - Open it in browser, search for "php.ini"
   - Note the location

2. **Download CA certificates**:
   ```bash
   cd "C:\Users\Jeff\Local Sites\h3vt\app\public"
   curl -o cacert.pem https://curl.se/ca/cacert.pem
   ```

3. **Update php.ini**:
   ```ini
   curl.cainfo = "C:\Users\Jeff\Local Sites\h3vt\app\public\cacert.pem"
   openssl.cafile = "C:\Users\Jeff\Local Sites\h3vt\app\public\cacert.pem"
   ```

4. **Restart Local Sites**

### Option 3: Local Sites Specific Fix

Since you're using Local Sites:

1. **Trust the SSL Certificate**:
   - Open Local Sites
   - Right-click on "h3vt" site
   - Go to SSL → Trust

2. **Use Site Shell**:
   - Right-click your site in Local Sites
   - Open Site Shell
   - Run the fix script:
   ```bash
   cd /app/public/wp-content/plugins/h3-tour-management/tools
   php fix-ssl-localhost.php
   ```

## What Was Done

The analytics class has been updated to automatically detect localhost and disable SSL verification for Google API calls. This is safe for development but would never run on a production server.

The fix adds this code when on localhost:
```php
$httpClient = new \GuzzleHttp\Client([
    'verify' => false
]);
$client->setHttpClient($httpClient);
```

## Test It Now

1. Go to WordPress Admin → 3D Tours → Manage Tours
2. Click "Send Test Email"
3. It should work without SSL errors

## Important Security Note

This fix ONLY applies when running on localhost or .local domains. On production servers, SSL verification remains enabled for security.