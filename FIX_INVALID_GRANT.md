# Fix for "invalid_grant" Error

## What This Error Means

The "invalid_grant: account not found" error indicates that:
- The service account credentials are properly formatted JSON
- But Google cannot authenticate the service account
- Usually means the private key is invalid or the account was deleted

## Quick Fix Steps

### 1. Run Debug Tool First
```bash
cd "C:\Users\Jeff\Local Sites\h3vt\app\public\wp-content\plugins\h3-tour-management\tools"
php debug-credentials.php
```

This will show you:
- Your current service account email
- Whether authentication is working
- Specific error details

### 2. Create New Service Account Key

Since the current key is invalid, you need to create a new one:

1. **Go to Google Cloud Console**
   - https://console.cloud.google.com/
   - Make sure you're in the correct project

2. **Check if Service Account Still Exists**
   - Menu (☰) > IAM & Admin > Service Accounts
   - Look for your service account email (shown in debug output)
   
   **If account EXISTS:**
   - Click on the service account
   - Go to "Keys" tab
   - Delete any old keys (optional but recommended)
   - Click "Add Key" > "Create new key" > JSON
   - Download the new key file

   **If account DOESN'T EXIST:**
   - Create a new service account:
     - Click "Create Service Account"
     - Name: "H3VT Analytics Reader"
     - Click "Create and Continue"
     - Skip permissions (click "Continue")
     - Click "Done"
   - Then create a key as described above

3. **Replace Credentials File**
   - Delete old file: `C:\Users\Jeff\Local Sites\h3vt\app\public\service-account-credentials.json`
   - Rename downloaded file to: `service-account-credentials.json`
   - Move to: `C:\Users\Jeff\Local Sites\h3vt\app\public\`

### 3. Enable Required API

Make sure the Google Analytics Data API is enabled:

1. In Google Cloud Console
2. Go to "APIs & Services" > "Library"
3. Search for "Google Analytics Data API"
4. Click on it
5. Click "Enable" (if not already enabled)

### 4. Grant Analytics Access

**IMPORTANT**: If you created a NEW service account, you must grant it access:

1. **Get the Service Account Email**
   - Open your new `service-account-credentials.json`
   - Find the "client_email" field
   - Copy the email

2. **Add to Google Analytics**
   - Go to https://analytics.google.com/
   - Admin > Property > Property Access Management
   - Click "+" to add user
   - Paste the service account email
   - Select "Viewer" role
   - Click "Add"

### 5. Verify Everything Works

Run the debug tool again:
```bash
php debug-credentials.php
```

You should see:
- ✓ Authentication successful!
- ✓ Successfully connected to Google Analytics!

Then test in WordPress:
- Go to 3D Tours > Manage Tours
- Click "Send Test Email"
- Should work without errors

## Common Issues

### Still Getting "invalid_grant"
- Make sure you're using the NEW key file, not the old one
- Verify the file is valid JSON (open in text editor)
- Check that you downloaded JSON format, not P12

### "Permission Denied" After Fixing invalid_grant
- The new service account needs Analytics access
- Follow step 4 above to grant access
- Make sure you're using the correct property

### Wrong Google Account/Project
- Verify you're logged into the correct Google account
- Check you're in the right project in Cloud Console
- Project name should match what's in your credentials file

## Prevention

To avoid this in the future:
1. Don't delete service accounts in Google Cloud Console
2. If you need to revoke access, delete the key instead
3. Keep a backup of working credentials (securely stored)
4. Document which project and account you're using

## Quick Command Reference

```bash
# Debug current setup
cd "C:\Users\Jeff\Local Sites\h3vt\app\public\wp-content\plugins\h3-tour-management\tools"
php debug-credentials.php

# Verify setup after fixing
php setup-analytics.php

# File location
# C:\Users\Jeff\Local Sites\h3vt\app\public\service-account-credentials.json
```