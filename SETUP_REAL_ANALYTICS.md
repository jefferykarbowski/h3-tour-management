# Setting Up Real Google Analytics for H3 Tour Management

## Current Issue
The plugin is looking for credentials at:
`C:\Users\Jeff\Local Sites\h3vt\app\public\service-account-credentials.json`

## Quick Setup Steps

### 1. First, check if Composer is installed
Open a terminal/command prompt and run:
```bash
cd "C:\Users\Jeff\Local Sites\h3vt\app\public"
composer install
```

If you get an error that composer is not found, you'll need to install it first:
- Download from: https://getcomposer.org/download/
- Run the Windows installer

### 2. Get Google Analytics Credentials

1. **Go to Google Cloud Console**
   - Visit: https://console.cloud.google.com/
   - Sign in with your Google account

2. **Create or Select a Project**
   - Click the project dropdown at the top
   - Click "New Project" or select existing

3. **Enable Google Analytics Data API**
   - In the search bar, type "Google Analytics Data API"
   - Click on it and press "Enable"

4. **Create Service Account**
   - Go to Menu (☰) > IAM & Admin > Service Accounts
   - Click "Create Service Account"
   - Name: "H3VT Analytics Reader"
   - Click "Create and Continue"
   - Skip optional permissions (click "Continue")
   - Click "Done"

5. **Download Credentials**
   - Click on your new service account
   - Go to "Keys" tab
   - Click "Add Key" > "Create new key"
   - Choose "JSON"
   - Save the file

6. **Install the Credentials**
   - Rename the downloaded file to: `service-account-credentials.json`
   - Move it to: `C:\Users\Jeff\Local Sites\h3vt\app\public\`

### 3. Grant Analytics Access

1. **Copy the Service Account Email**
   - Open your `service-account-credentials.json` file
   - Find the `client_email` field
   - Copy the email (looks like: something@project-id.iam.gserviceaccount.com)

2. **Add to Google Analytics**
   - Go to Google Analytics (analytics.google.com)
   - Admin > Property > Property Access Management
   - Click the "+" button
   - Paste the service account email
   - Give it "Viewer" role
   - Click "Add"

### 4. Test the Setup

Run the setup script I created:
```bash
cd "C:\Users\Jeff\Local Sites\h3vt\app\public\wp-content\plugins\h3-tour-management\tools"
php setup-analytics.php
```

This will verify:
- ✓ Composer is installed
- ✓ Google API client library is installed
- ✓ Credentials file exists and is valid
- ✓ Connection to Google Analytics works

## Troubleshooting

### "composer: command not found"
- Install Composer from https://getcomposer.org/download/
- Use the Windows installer
- Restart your terminal after installation

### "Google API client library not found"
```bash
cd "C:\Users\Jeff\Local Sites\h3vt\app\public"
composer install
```

### "Failed to authenticate"
- Make sure the service account email has access to your Analytics property
- Verify the Analytics Data API is enabled in Google Cloud Console

## Expected Result

Once everything is set up correctly:
1. The error message will disappear
2. "Send Analytics Email" will send real data from Google Analytics
3. Scheduled emails will include actual visitor statistics

## File Locations Summary

- **WordPress Root**: `C:\Users\Jeff\Local Sites\h3vt\app\public\`
- **Credentials File**: `C:\Users\Jeff\Local Sites\h3vt\app\public\service-account-credentials.json`
- **Composer File**: `C:\Users\Jeff\Local Sites\h3vt\app\public\composer.json`
- **Vendor Directory**: `C:\Users\Jeff\Local Sites\h3vt\app\public\vendor\`