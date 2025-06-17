# Google Analytics API Setup Guide

## Quick Fix (Without Google API)

The plugin now works without the Google API by sending sample analytics data. You'll receive formatted emails with example data to verify your email system is working.

## Full Setup (With Real Analytics Data)

To get real analytics data from Google Analytics, follow these steps:

### 1. Install Google API Client Library

#### Option A: Using Composer (Recommended)
```bash
# Navigate to your WordPress root directory
cd /path/to/your/wordpress/site

# Install dependencies
composer install
```

#### Option B: Manual Installation
If you don't have Composer, install it first:
```bash
# Download Composer
curl -sS https://getcomposer.org/installer | php

# Install dependencies
php composer.phar install
```

### 2. Set Up Google Analytics Service Account

1. **Create a Google Cloud Project**
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create a new project or select an existing one
   - Enable the Google Analytics Data API

2. **Create Service Account**
   - Go to "IAM & Admin" > "Service Accounts"
   - Click "Create Service Account"
   - Give it a name like "H3VT Analytics Reader"
   - Grant it "Viewer" role
   - Create and download the JSON key file

3. **Save Credentials**
   - Rename the downloaded file to `service-account-credentials.json`
   - Place it in your WordPress root directory (same level as wp-config.php)
   - Ensure it's not publicly accessible (add to .htaccess if needed)

4. **Grant Analytics Access**
   - Copy the service account email (looks like: your-service-account@project-id.iam.gserviceaccount.com)
   - Go to Google Analytics
   - Admin > Property > Property Access Management
   - Add the service account email with "Viewer" access

### 3. Verify Installation

1. Go to WordPress Admin > 3D Tours > Analytics
2. Check the "Email Configuration Status" section
3. Both indicators should show green checkmarks:
   - ✓ Google API Library: Found
   - ✓ Service Account Credentials: Found

### 4. Test Email

1. Go to 3D Tours > Manage Tours
2. Click "Send Test Email"
3. You should now receive real analytics data

## Troubleshooting

### "Google API client library not found"
- Ensure composer.json is in WordPress root
- Run `composer install` from WordPress root directory
- Check that `/vendor/autoload.php` exists

### "Service account credentials file not found"
- Verify `service-account-credentials.json` is in WordPress root
- Check file permissions (should be readable by PHP)

### "Failed to send email"
- Check WordPress email settings
- Consider using an SMTP plugin
- Check error logs for details

## Security Notes

1. **Never commit credentials to version control**
   - Add to .gitignore: `service-account-credentials.json`

2. **Protect credentials file**
   Add to .htaccess in WordPress root:
   ```apache
   <Files "service-account-credentials.json">
       Order Allow,Deny
       Deny from all
   </Files>
   ```

3. **Use environment variables (optional)**
   Instead of a file, you can set:
   ```php
   putenv('GOOGLE_APPLICATION_CREDENTIALS=/path/to/credentials.json');
   ```

## Sample Data Mode

If you don't set up Google API, the plugin will:
- Send nicely formatted emails with sample data
- Show a notice that it's using sample data
- Allow you to test email functionality
- Work for all email scheduling features

This ensures the plugin is functional even without the Google API setup.