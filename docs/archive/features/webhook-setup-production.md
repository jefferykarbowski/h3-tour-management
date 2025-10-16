# Production Webhook Setup Guide

## Overview

Lambda requires the `WORDPRESS_WEBHOOK_URL` environment variable to notify WordPress when tour processing completes. Without this, tours will remain stuck at "uploading" status.

## Quick Setup (3 Steps)

### Step 1: Deploy Plugin to Production

Ensure the H3 Tour Management plugin is installed and activated on your production WordPress site:
- `https://h3vt.com/wp-admin/plugins.php`

### Step 2: Configure Lambda Environment Variable

**Via AWS Console (Recommended):**

1. Navigate to Lambda:
   - https://console.aws.amazon.com/lambda/ (us-east-1)
   - Click function: **h3tm-tour-processor**

2. Add Environment Variable:
   - Click **Configuration** tab
   - Click **Environment variables** → **Edit**
   - Click **Add environment variable**
   - Key: `WORDPRESS_WEBHOOK_URL`
   - Value: `https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook`
   - Click **Save**

**Via AWS CLI:**
```bash
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --environment "Variables={WORDPRESS_WEBHOOK_URL=https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook}" \
  --region us-east-1
```

### Step 3: Deploy Updated Lambda Code

1. Navigate to Lambda function: **h3tm-tour-processor**
2. Click **Upload from** → **.zip file**
3. Select: `lambda/function.zip`
4. Click **Save**

## How It Works

**Upload Flow:**
1. User uploads tour ZIP via WordPress admin
2. File uploaded to S3: `uploads/{tour_id}/{tour_id}.zip`
3. S3 triggers Lambda function
4. Lambda extracts ZIP and uploads files to `tours/{tour_id}/`
5. **Lambda sends webhook to WordPress**
6. **WordPress updates tour status to "completed"**
7. Tour appears in admin table with "completed" status

**Without Webhook:**
- Steps 1-4 work fine
- Step 5 fails (no webhook URL configured)
- Tour stuck at "uploading" status
- Files ARE in S3 but WordPress doesn't know

## Webhook Payload

Lambda sends this JSON payload to WordPress:

```json
{
    "success": true,
    "tourName": "Tour Display Name",
    "tourId": "20251016_030721_hg7nau01",
    "s3Key": "uploads/20251016_030721_hg7nau01/20251016_030721_hg7nau01.zip",
    "s3FolderName": "20251016_030721_hg7nau01",
    "s3Bucket": "h3-tour-files-h3vt",
    "filesExtracted": 3811,
    "processingTime": 0,
    "totalSize": 0,
    "message": "Tour processed successfully",
    "timestamp": "2025-10-16T03:11:42.164Z",
    "s3Url": "https://h3-tour-files-h3vt.s3.us-east-1.amazonaws.com/tours/20251016_030721_hg7nau01/"
}
```

## WordPress Webhook Handler

The webhook endpoint is registered in `includes/class-h3tm-lambda-webhook.php`:

**Endpoint:** `/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook`

**What it does:**
1. Validates payload structure
2. Updates tour metadata status: "uploading" → "completed"
3. Clears tour cache
4. Returns success response

## Expected Logs

**Lambda CloudWatch:**
```
📞 Sending webhook to: https://h3vt.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook
📊 Payload: {JSON payload}
✅ WordPress notified successfully
Response: {"success":true,"data":"Tour processed successfully"}
```

**WordPress debug.log:**
```
H3TM Lambda Webhook: Webhook request received
H3TM Lambda Webhook: Payload received: {JSON}
H3TM Lambda Webhook: Updated metadata status to 'completed' for tour_id: 20251016_030721_hg7nau01
H3TM Lambda Webhook: Cleared S3 tour cache
H3TM Lambda Webhook: Webhook processed successfully
```

## Testing

### Test 1: Upload a Tour

1. Go to: https://h3vt.com/wp-admin/admin.php?page=h3-tour-management
2. Upload a tour ZIP file
3. **Should complete at 100%** (not timeout at 95%)
4. Tour appears with status "completed"
5. "View" button works

### Test 2: Check CloudWatch Logs

```bash
aws logs tail /aws/lambda/h3tm-tour-processor --follow --region us-east-1
```

Look for:
- `📞 Sending webhook to: https://h3vt.com/...`
- `✅ WordPress notified successfully`

### Test 3: Check WordPress Logs

Production WordPress debug.log should show:
- `H3TM Lambda Webhook: Webhook request received`
- `H3TM Lambda Webhook: Updated metadata status to 'completed'`

## Troubleshooting

### Issue: "WORDPRESS_WEBHOOK_URL not configured"

**Cause:** Environment variable not set in Lambda
**Solution:** Follow Step 2 to configure environment variable

### Issue: Webhook returns 400 Bad Request

**Cause:** Payload validation failed
**Solution:** Check WordPress debug.log for validation error details

Possible validation errors:
- Missing required field (success, tourName, s3Key, message, timestamp)
- Invalid tour name format (must be alphanumeric, dashes, underscores only)
- Invalid S3 key format (must be `uploads/*.zip`)
- Expired timestamp (older than 24 hours)

### Issue: Webhook returns 404 Not Found

**Cause:** Plugin not activated or webhook handler not registered
**Solution:**
1. Verify plugin is activated on production
2. Check `h3-tour-management.php` line 79 initializes webhook handler
3. Try deactivating and reactivating plugin

### Issue: Webhook returns 401 Unauthorized

**Cause:** Webhook secret configured but Lambda not sending signature
**Solution:**
1. Check if `h3tm_lambda_webhook_secret` option exists in WordPress
2. Either remove it or configure Lambda to send HMAC signature
3. For now, delete the secret: `DELETE FROM wp_options WHERE option_name = 'h3tm_lambda_webhook_secret'`

### Issue: No webhook logs in WordPress

**Possible causes:**
1. **Firewall blocking requests** - Check security plugin logs
2. **Wrong URL** - Verify webhook URL is correct
3. **Plugin not active** - Activate plugin on production
4. **Server error** - Check PHP error logs

### Issue: Tour stuck at "uploading" but files in S3

**Cause:** Webhook failed to notify WordPress
**Solution:**
1. Check CloudWatch logs for webhook error
2. Check WordPress debug.log for webhook logs
3. Manually update status in database as temporary fix:
   ```sql
   UPDATE wp_h3tm_tour_metadata
   SET status = 'completed'
   WHERE tour_id = '20251016_030721_hg7nau01';
   ```

## Local Development Without Production

If you want to test locally without deploying to production:

**Option 1: No Webhook (Simplest)**
- Don't configure `WORDPRESS_WEBHOOK_URL` in Lambda
- Lambda will skip webhook notification
- Files will upload to S3 successfully
- Tours will be stuck at "uploading" status
- Manually update status in database when needed

**Option 2: Expose Local Site**
- Use ngrok: `ngrok http https://h3vt.local`
- Set `WORDPRESS_WEBHOOK_URL` to ngrok URL
- Lambda can reach your local site
- Works for testing but not recommended for regular development

## Security Considerations

1. **HTTPS Required:** Webhook URL must use HTTPS in production
2. **No Authentication:** Webhook endpoint is publicly accessible
3. **Validation:** All payload fields are validated and sanitized
4. **Timestamp Check:** Rejects payloads older than 24 hours
5. **Optional Signature:** Can enable HMAC-SHA256 signature verification

### Enabling Signature Verification (Optional)

For enhanced security, enable webhook signature verification:

**1. Generate Secret in WordPress:**
- Go to: https://h3vt.com/wp-admin/admin.php?page=h3-tour-management
- Find "Webhook Settings" section
- Click "Generate Webhook Secret"
- Copy the 64-character secret

**2. Add Secret to Lambda:**
- Environment variable: `WORDPRESS_WEBHOOK_SECRET`
- Value: The secret from WordPress

**3. Update Lambda Code:**
```javascript
// In notifyWordPress function, add signature header
const webhookSecret = process.env.WORDPRESS_WEBHOOK_SECRET;
const headers = {
    'Content-Type': 'application/json',
    'User-Agent': 'H3-Lambda-Processor/1.0'
};

if (webhookSecret) {
    const crypto = require('crypto');
    const payloadString = JSON.stringify(payload);
    const signature = 'sha256=' + crypto.createHmac('sha256', webhookSecret)
        .update(payloadString)
        .digest('hex');
    headers['X-Webhook-Signature'] = signature;
}
```

## Production Checklist

- [ ] Plugin deployed to production: https://h3vt.com
- [ ] Plugin activated in WordPress admin
- [ ] `WORDPRESS_WEBHOOK_URL` configured in Lambda
- [ ] Updated Lambda code deployed (function.zip)
- [ ] Test upload completed successfully
- [ ] Tour status shows "completed"
- [ ] CloudWatch shows webhook success
- [ ] WordPress logs show webhook received
- [ ] "View" button works for uploaded tour

## Files Modified

- `lambda/index.js` - Webhook notification function
- `lambda/function.zip` - Deployment package (46KB)
- `includes/class-h3tm-lambda-webhook.php` - WordPress webhook handler
- `h3-tour-management.php` - Plugin initialization (line 79)

## Additional Documentation

- **Lambda ACL Fix:** `docs/lambda-deployment-fix.md`
- **Original Webhook Guide:** `docs/webhook-configuration.md`
- **Test Tool:** `tools/test-webhook.php`
