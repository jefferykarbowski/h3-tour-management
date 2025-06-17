# How to Update Service Account Credentials

## When to Update Credentials

You need to update your service account credentials when:
- Your current credentials have expired
- You've revoked the old key for security reasons
- You're switching to a different Google Cloud project
- You're changing to a different service account
- The credentials file is corrupted or invalid

## Step-by-Step Update Process

### 1. Remove Old Credentials (Optional)
If you have existing credentials that you want to replace:
```bash
cd "C:\Users\Jeff\Local Sites\h3vt\app\public"
del service-account-credentials.json
```

### 2. Generate New Credentials

1. **Go to Google Cloud Console**
   - Visit: https://console.cloud.google.com/
   - Make sure you're in the correct project

2. **Navigate to Service Accounts**
   - Menu (☰) > IAM & Admin > Service Accounts
   - Find your existing service account (e.g., "H3VT Analytics Reader")
   - Click on the service account name

3. **Create New Key**
   - Go to the "Keys" tab
   - Click "Add Key" > "Create new key"
   - Choose "JSON" format
   - Click "Create"
   - The file will download automatically

4. **Optional: Delete Old Key**
   - In the same "Keys" tab, you'll see all active keys
   - Find the old key (check the creation date)
   - Click the trash icon to delete it
   - Confirm deletion

### 3. Install New Credentials

1. **Rename the Downloaded File**
   - The downloaded file will have a long name like `project-name-abc123def456.json`
   - Rename it to exactly: `service-account-credentials.json`

2. **Move to Correct Location**
   - Move the file to: `C:\Users\Jeff\Local Sites\h3vt\app\public\`
   - This should replace any existing file with the same name

3. **Verify File Permissions**
   - Right-click the file > Properties
   - Make sure it's readable by the web server
   - Generally, default permissions are fine

### 4. Verify the Update

Run the setup verification script:
```bash
cd "C:\Users\Jeff\Local Sites\h3vt\app\public\wp-content\plugins\h3-tour-management\tools"
php setup-analytics.php
```

You should see:
- ✓ Service account credentials found
- ✓ Successfully connected to Google Analytics!

### 5. Test Analytics Email

1. Go to WordPress Admin > 3D Tours > Manage Tours
2. Click "Send Test Email" for any user
3. Verify the email sends successfully

## Important Notes

### Service Account Email Remains the Same
If you're just updating the key for the same service account:
- The service account email stays the same
- No need to update Google Analytics permissions
- The transition should be seamless

### Service Account Email Changes
If you're using a NEW service account:
1. Get the new service account email from the JSON file
2. Add it to Google Analytics:
   - Analytics Admin > Property > Property Access Management
   - Add the new email with "Viewer" access
3. Remove the old service account email (optional)

## Troubleshooting

### "Invalid credentials" Error
- Make sure you downloaded the JSON format (not P12)
- Verify the file is named exactly: `service-account-credentials.json`
- Check that the file is valid JSON (open in text editor)

### "Permission denied" Error
- The service account needs "Viewer" access in Google Analytics
- Make sure you're using the correct Analytics property ID
- Verify the Analytics Data API is enabled in Google Cloud

### Can't Find Service Account
If you can't find your existing service account:
1. You might be in the wrong Google Cloud project
2. Create a new service account following the original setup guide
3. Make sure to grant it Analytics access

## Security Best Practices

1. **Never commit credentials to Git**
   - Add to .gitignore: `service-account-credentials.json`

2. **Rotate keys regularly**
   - Google recommends rotating keys every 90 days
   - Delete old keys after confirming new ones work

3. **Limit permissions**
   - Service account only needs "Viewer" access to Analytics
   - Don't grant unnecessary permissions

4. **Protect the file**
   - Consider adding to .htaccess:
   ```apache
   <Files "service-account-credentials.json">
       Order Allow,Deny
       Deny from all
   </Files>
   ```

## Quick Reference

**File Location**: `C:\Users\Jeff\Local Sites\h3vt\app\public\service-account-credentials.json`

**Required Format**: JSON (not P12)

**Required Permissions**: Google Analytics Viewer

**Required API**: Google Analytics Data API (must be enabled)

**Verification Script**: `/wp-content/plugins/h3-tour-management/tools/setup-analytics.php`