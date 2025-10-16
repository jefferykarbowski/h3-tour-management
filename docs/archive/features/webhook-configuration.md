# Lambda Webhook Configuration Guide

## Problem

Lambda successfully processes tours but cannot notify WordPress of completion because `WORDPRESS_WEBHOOK_URL` environment variable is not configured. This causes:

1. ✅ Lambda extracts and uploads tour files (3 minutes)
2. ❌ Lambda cannot notify WordPress
3. ❌ Tour status stuck at "uploading" in database
4. ❌ Frontend keeps polling, times out at 95% after 5 minutes
5. ✅ Tour IS available in S3, but WordPress doesn't know

## Solution: Configure Lambda Environment Variable

### Step 1: Determine Your Webhook URL

**For Production (Public Site):**
```
https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook
```

**For Local Development (via LocalWP tunnel):**

Since `h3vt.local` is only accessible from your local machine, AWS Lambda cannot reach it. You have two options:

**Option A: Use LocalWP Live Link (Temporary URL)**
1. Open Local by Flywheel
2. Right-click on "h3vt" site
3. Click "Enable Live Link"
4. Copy the temporary URL (e.g., `https://abc123.localwp.dev`)
5. Webhook URL: `https://abc123.localwp.dev/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook`

**Option B: Use ngrok or similar tunnel**
```bash
ngrok http https://h3vt.local
# Copy HTTPS URL: https://xyz.ngrok.io
# Webhook URL: https://xyz.ngrok.io/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook
```

### Step 2: Configure Lambda Environment Variable

#### Via AWS Console (Recommended)

1. **Navigate to Lambda:**
   - Go to https://console.aws.amazon.com/lambda/
   - Region: **us-east-1**
   - Click function: **h3tm-tour-processor**

2. **Configuration Tab:**
   - Click **"Configuration"** tab
   - Click **"Environment variables"** in left sidebar
   - Click **"Edit"**

3. **Add Environment Variable:**
   - Click **"Add environment variable"**
   - Key: `WORDPRESS_WEBHOOK_URL`
   - Value: Your webhook URL from Step 1
   - Example for production: `https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook`
   - Click **"Save"**

4. **Verify Configuration:**
   - Return to **"Configuration"** → **"Environment variables"**
   - Confirm `WORDPRESS_WEBHOOK_URL` is listed

#### Via AWS CLI (Alternative)

```bash
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --environment "Variables={WORDPRESS_WEBHOOK_URL=https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook}" \
  --region us-east-1
```

### Step 3: Optional - Configure Webhook Secret (Enhanced Security)

The webhook handler supports HMAC signature verification for added security.

1. **Generate Webhook Secret in WordPress:**
   - Go to WordPress admin: Settings → H3 Tour Management
   - Find "Lambda Webhook Settings" section
   - Click **"Regenerate Webhook Secret"**
   - Copy the generated secret (64 characters)

2. **Add to Lambda Environment:**
   - Add another environment variable:
     - Key: `WORDPRESS_WEBHOOK_SECRET`
     - Value: The secret from WordPress

3. **Update Lambda Code to Send Signature:**

Edit `lambda/index.js` around line 277 to add signature header:

```javascript
const webhookSecret = process.env.WORDPRESS_WEBHOOK_SECRET;
const headers = {
    'Content-Type': 'application/json',
    'User-Agent': 'H3-Lambda-Processor/1.0'
};

// Add HMAC signature if secret is configured
if (webhookSecret) {
    const crypto = require('crypto');
    const payloadString = JSON.stringify(payload);
    const signature = 'sha256=' + crypto.createHmac('sha256', webhookSecret)
        .update(payloadString)
        .digest('hex');
    headers['X-Webhook-Signature'] = signature;
}

const response = await fetch(webhookUrl, {
    method: 'POST',
    headers: headers,
    body: JSON.stringify(payload)
});
```

## Lambda Payload Structure

Lambda sends this payload to WordPress (see `lambda/index.js` lines 259-272):

```json
{
    "success": true,
    "tourName": "Tour Display Name",
    "tourId": "20251016_024353_60u4d82e",
    "s3Key": "uploads/20251016_024353_60u4d82e/20251016_024353_60u4d82e.zip",
    "s3FolderName": "20251016_024353_60u4d82e",
    "s3Bucket": "h3-tour-files-h3vt",
    "filesExtracted": 450,
    "processingTime": 176995,
    "totalSize": 45000000,
    "message": "Tour processed successfully",
    "timestamp": "2025-10-16T02:48:08.477Z",
    "s3Url": "https://h3-tour-files-h3vt.s3.us-east-1.amazonaws.com/tours/20251016_024353_60u4d82e/"
}
```

## WordPress Webhook Handler

The webhook handler (`includes/class-h3tm-lambda-webhook.php`) performs these actions:

1. **Validates Payload** (lines 121-159):
   - Checks required fields: success, tourName, s3Key, message, timestamp
   - Validates tour name format
   - Validates S3 key format
   - Checks timestamp is recent (within 24 hours)

2. **Verifies Signature** (lines 164-171, optional):
   - If webhook secret configured, verifies HMAC-SHA256 signature
   - Rejects unauthorized requests

3. **Updates Tour Status** (lines 187-211):
   - Queries tour metadata by `tour_id`
   - Updates status from 'uploading' to 'completed'
   - Logs success/failure

4. **Clears Caches** (lines 214-220):
   - Deletes tour list cache transient
   - Cleans up processing transients
   - Removes temporary files

5. **Returns Response** (lines 228-231):
   - Sends JSON success response
   - Logs to WordPress debug.log

## Testing the Webhook

### Option 1: Test from WordPress Admin

1. Go to: https://h3vt.local/wp-admin/admin.php?page=h3-tour-management
2. Find "Webhook Test" button
3. Click to send test webhook
4. Check response message

### Option 2: Test with curl

```bash
curl -X POST \
  https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook \
  -H "Content-Type: application/json" \
  -d '{
    "success": true,
    "tourName": "test-tour",
    "tourId": "20251016_120000_test123",
    "s3Key": "uploads/test/test.zip",
    "s3FolderName": "20251016_120000_test123",
    "s3Bucket": "h3-tour-files-h3vt",
    "filesExtracted": 10,
    "processingTime": 5000,
    "totalSize": 1000000,
    "message": "Test webhook",
    "timestamp": "'$(date -u +%Y-%m-%dT%H:%M:%S.000Z)'"
  }'
```

### Option 3: Upload Real Tour

After configuring the webhook URL:

1. Go to: https://h3vt.local/wp-admin/admin.php?page=h3-tour-management
2. Upload a tour ZIP file
3. Watch the progress bar
4. Should complete at 100% (not timeout at 95%)
5. Tour status should change to "completed"

## Expected Log Output

**WordPress `debug.log`:**
```
H3TM Lambda Webhook: Webhook request received
H3TM Lambda Webhook: Payload received: {JSON payload}
H3TM Lambda Webhook: Updated metadata status to 'completed' for tour_id: 20251016_024353_60u4d82e
H3TM Lambda Webhook: Cleared S3 tour cache
H3TM Lambda Webhook: Webhook processed successfully
```

**Lambda CloudWatch Logs:**
```
📞 Step 5: Notifying WordPress...
📞 Sending webhook to: https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook
📊 Payload: {JSON payload}
✅ WordPress notified successfully
Response: {"success":true,"data":"Tour processed successfully"}
```

## Troubleshooting

### Issue: "WORDPRESS_WEBHOOK_URL not configured"

**Solution:** Add environment variable to Lambda (see Step 2 above)

### Issue: "Webhook signature verification failed"

**Cause:** Signature mismatch or webhook secret not configured in Lambda

**Solution:**
1. Either remove signature verification (set `WORDPRESS_WEBHOOK_SECRET=""`)
2. Or update Lambda code to send correct signature (see Step 3)

### Issue: "Tour not found for tour_id"

**Cause:** WordPress created metadata with different tour_id than Lambda received

**Solution:** Check S3 Simple class generates tour_id correctly (lines 539-566)

### Issue: Webhook returns 404

**Cause:** WordPress permalinks not configured or .htaccess issue

**Solution:**
1. Go to Settings → Permalinks
2. Click "Save Changes" to flush rewrite rules
3. Test webhook again

### Issue: Webhook times out

**Cause:** Site not accessible from AWS Lambda (local site, firewall, etc.)

**Solution:** Use LocalWP Live Link or ngrok tunnel (see Option A/B in Step 1)

## Production Deployment Checklist

- [ ] Configure `WORDPRESS_WEBHOOK_URL` in Lambda environment
- [ ] Optional: Generate and configure webhook secret
- [ ] Optional: Update Lambda code to send HMAC signature
- [ ] Test webhook with curl or admin test button
- [ ] Upload test tour and verify completion
- [ ] Monitor WordPress debug.log for webhook logs
- [ ] Monitor Lambda CloudWatch logs for webhook success
- [ ] Verify tour status changes from 'uploading' to 'completed'

## Security Considerations

1. **HTTPS Required:** Webhook URL must use HTTPS in production
2. **Signature Verification:** Enable webhook secret for production environments
3. **Timestamp Validation:** Webhook rejects payloads older than 24 hours
4. **Input Sanitization:** All payload fields are sanitized before database updates
5. **Access Control:** Webhook endpoint is publicly accessible but validates all inputs

## Architecture Notes

This webhook replaces the old polling-based system:

**Old Flow (Polling):**
1. Frontend polls CloudFront/S3 every 5 seconds
2. Continues for 5 minutes (60 polls)
3. Times out if files not found
4. No confirmation from Lambda

**New Flow (Webhook):**
1. Lambda uploads files
2. Lambda notifies WordPress immediately
3. WordPress updates status instantly
4. Frontend refreshes tour list
5. Tour appears with "completed" status

The webhook provides:
- **Instant feedback** - No waiting for polling
- **Reliable status updates** - Direct communication
- **Better error handling** - Lambda can report failures
- **Reduced load** - No continuous polling
