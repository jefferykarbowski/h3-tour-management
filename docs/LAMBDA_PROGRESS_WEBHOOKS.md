# Lambda Function Progress Webhook Implementation Guide

## Overview

This document describes the required changes to the AWS Lambda function to support **real-time progress notifications** during tour update processing. This eliminates 504 timeout errors and provides users with visibility into the processing stages.

## Problem Solved

- **504 Gateway Timeouts**: Large tour files (>100MB) were causing WordPress to timeout waiting for synchronous processing
- **No User Feedback**: Users had no visibility into what was happening during the long processing time
- **Poor UX**: Users would see error messages even when processing was successful

## Solution Architecture

The Lambda function now sends **progress webhooks** at key stages of processing, while WordPress polls for progress updates to display in the UI:

```
User → WordPress → Lambda (async)
                    ↓
        Progress Updates (webhooks)
                    ↓
WordPress ← Lambda ← Processing Stages
```

## Required Lambda Function Changes

### 1. Accept New Request Parameters

The `invoke_lambda_update()` function in WordPress now sends these parameters:

```json
{
  "action": "update_tour",
  "bucket": "h3-tour-files-h3vt",
  "tourId": "20251021_205548_a5lufh63",
  "s3Key": "uploads/20251021_205548_a5lufh63/Archive.zip",
  "webhookUrl": "https://yoursite.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook",
  "webhookSecret": "your_webhook_secret_here",
  "enableProgressUpdates": true
}
```

### 2. Send Progress Webhooks

At each major processing stage, send an HTTP POST webhook to `webhookUrl` with this payload:

```javascript
const sendProgressUpdate = async (stage, progress, message, additionalData = {}) => {
  if (!enableProgressUpdates) return;

  const payload = {
    type: 'progress',
    tourId: tourId,
    status: 'processing',
    stage: stage,
    progress: progress,
    message: message,
    timestamp: new Date().toISOString(),
    ...additionalData
  };

  const signature = 'sha256=' + crypto
    .createHmac('sha256', webhookSecret)
    .update(JSON.stringify(payload))
    .digest('hex');

  try {
    await axios.post(webhookUrl, payload, {
      headers: {
        'Content-Type': 'application/json',
        'X-Webhook-Signature': signature
      },
      timeout: 10000 // 10 second timeout
    });
  } catch (error) {
    console.error('Progress webhook failed:', error.message);
    // Don't fail the whole process if progress updates fail
  }
};
```

### 3. Processing Stages

Send progress webhooks at these stages:

#### Stage 1: Initialization (0-10%)
```javascript
await sendProgressUpdate('initializing', 5, 'Starting tour update process...');
```

#### Stage 2: Download from S3 (10-30%)
```javascript
await sendProgressUpdate('downloading', 15, 'Downloading tour file from S3...');
// ... perform download ...
await sendProgressUpdate('downloading', 30, 'Download complete', {
  filesProcessed: 1,
  totalFiles: 1
});
```

#### Stage 3: Extract ZIP (30-50%)
```javascript
await sendProgressUpdate('extracting', 35, 'Extracting tour files...');
// ... perform extraction ...
await sendProgressUpdate('extracting', 50, `Extracted ${filesExtracted} files`, {
  filesProcessed: filesExtracted,
  totalFiles: filesExtracted
});
```

#### Stage 4: Upload to S3 (50-85%)
```javascript
await sendProgressUpdate('uploading', 55, 'Uploading files to S3...');

// Send progress for each file or batch
let uploadedCount = 0;
for (const file of filesToUpload) {
  await uploadFileToS3(file);
  uploadedCount++;

  // Update every 10% or 10 files
  if (uploadedCount % 10 === 0 || uploadedCount === filesToUpload.length) {
    const uploadProgress = 55 + Math.floor((uploadedCount / filesToUpload.length) * 30);
    await sendProgressUpdate('uploading', uploadProgress,
      `Uploaded ${uploadedCount}/${filesToUpload.length} files`, {
      filesProcessed: uploadedCount,
      totalFiles: filesToUpload.length
    });
  }
}
```

#### Stage 5: Cache Invalidation (85-95%)
```javascript
await sendProgressUpdate('invalidating', 88, 'Invalidating CloudFront cache...');
// ... perform cache invalidation ...
await sendProgressUpdate('invalidating', 95, 'Cache invalidated successfully');
```

#### Stage 6: Cleanup (95-100%)
```javascript
await sendProgressUpdate('cleanup', 97, 'Cleaning up temporary files...');
// ... cleanup ...
await sendProgressUpdate('completing', 100, 'Tour update complete!');
```

### 4. Send Final Completion Webhook

After all stages complete, send the **existing completion webhook** (not a progress update):

```javascript
const completionPayload = {
  success: true,
  tourName: tourName,
  tourId: tourId,
  s3Key: s3Key,
  message: 'Tour updated successfully',
  filesExtracted: filesExtracted,
  totalSize: totalSize,
  processingTime: Date.now() - startTime,
  timestamp: new Date().toISOString()
};

const signature = 'sha256=' + crypto
  .createHmac('sha256', webhookSecret)
  .update(JSON.stringify(completionPayload))
  .digest('hex');

await axios.post(webhookUrl, completionPayload, {
  headers: {
    'Content-Type': 'application/json',
    'X-Webhook-Signature': signature
  }
});
```

## WordPress Integration (Already Implemented)

### Backend Changes ✅

1. **`handle_update_tour()`** (class-h3tm-admin.php:1895)
   - Now invokes Lambda asynchronously instead of WordPress processing
   - Returns immediately with progress tracking key
   - Sets up progress transient for polling

2. **`invoke_lambda_update()`** (class-h3tm-admin.php:1984)
   - Sends Lambda request with `enableProgressUpdates: true`
   - Passes webhook URL and secret for callbacks

3. **`handle_get_update_progress()`** (class-h3tm-admin.php:2040)
   - AJAX endpoint for frontend to poll progress status
   - Returns current progress data from transient

4. **`handle_progress_update()`** (class-h3tm-lambda-webhook.php:285)
   - Webhook handler for progress updates from Lambda
   - Updates transient with real-time progress data

5. **`validate_webhook_payload()`** (class-h3tm-lambda-webhook.php:125)
   - Enhanced to validate both progress and completion payloads

### Frontend Changes (Pending)

The `UpdateTourModal.tsx` needs to be updated to:
1. Start polling when update is initiated
2. Display progress bar and status messages
3. Stop polling when complete or failed

## Payload Examples

### Progress Update Payload
```json
{
  "type": "progress",
  "tourId": "20251021_205548_a5lufh63",
  "status": "processing",
  "stage": "uploading",
  "progress": 65,
  "message": "Uploaded 150/200 files",
  "filesProcessed": 150,
  "totalFiles": 200,
  "timestamp": "2025-10-26T15:30:45.123Z"
}
```

### Completion Webhook Payload (Unchanged)
```json
{
  "success": true,
  "tourName": "Arden-SilverSprings",
  "tourId": "20251021_205548_a5lufh63",
  "s3Key": "uploads/20251021_205548_a5lufh63/Archive.zip",
  "message": "Tour updated successfully",
  "filesExtracted": 1247,
  "totalSize": 176092120,
  "processingTime": 42350,
  "timestamp": "2025-10-26T15:31:30.456Z"
}
```

## Error Handling

### Progress Update Failures
- **Don't fail the entire process** if a progress webhook fails
- Log the error but continue processing
- The completion webhook is the critical one

### Network Timeouts
- Set a 10-second timeout for progress webhooks
- Use async/fire-and-forget pattern if your language supports it
- Queue webhooks if network is temporarily unavailable

### Retry Strategy
- **Don't retry progress updates** - they're ephemeral
- **DO retry the final completion webhook** - this is critical
- Use exponential backoff for completion webhook retries

## Testing

### Test Locally
```javascript
// Simulate progress updates
const testProgressWebhook = async () => {
  const stages = [
    { stage: 'initializing', progress: 5, message: 'Starting...' },
    { stage: 'downloading', progress: 20, message: 'Downloading from S3...' },
    { stage: 'extracting', progress: 50, message: 'Extracting files...' },
    { stage: 'uploading', progress: 80, message: 'Uploading to S3...' },
    { stage: 'completing', progress: 100, message: 'Complete!' }
  ];

  for (const update of stages) {
    await sendProgressUpdate(update.stage, update.progress, update.message);
    await new Promise(resolve => setTimeout(resolve, 2000)); // 2 sec delay
  }
};
```

### Monitor in WordPress
1. Enable WordPress debug logging (`WP_DEBUG_LOG`)
2. Watch `/wp-content/debug.log` for progress updates:
   ```
   H3TM Lambda Progress: Updated progress for tour_id 20251021_205548_a5lufh63 - 65% - Uploaded 150/200 files
   ```

## Migration Path

### Version 2.6.2 (Current - Broken)
- Synchronous WordPress processing
- 504 timeouts on large files
- No progress visibility

### Version 2.6.3 (This Update)
- Asynchronous Lambda processing
- Real-time progress webhooks
- No timeouts, better UX

### Backward Compatibility
The changes are **backward compatible**:
- If Lambda doesn't send progress updates, the old completion webhook still works
- Frontend gracefully handles missing progress data
- Existing tours and processes unaffected

## Security Considerations

1. **Webhook Signature Verification**
   - All webhooks MUST be signed with HMAC-SHA256
   - Use the `webhookSecret` provided in the request
   - Include signature in `X-Webhook-Signature` header

2. **Timestamp Validation**
   - WordPress validates timestamps within 24 hours
   - Prevents replay attacks

3. **SSL/TLS Required**
   - All webhook URLs use HTTPS
   - Ensure Lambda can make HTTPS requests

## Performance Impact

- **Minimal**: Each progress webhook is ~500 bytes
- **Async**: Doesn't block Lambda processing
- **Throttled**: Only sent at major milestones (10-15 total per update)
- **Timeout**: 10 second timeout prevents hanging

## Version Compatibility

- **WordPress**: 2.6.3+
- **Lambda**: Update required to support progress webhooks
- **PHP**: 7.4+ (unchanged)
- **Node.js**: 18+ recommended for Lambda

## Next Steps

1. ✅ Update WordPress backend (COMPLETE)
2. ⏳ Update Lambda function with progress webhooks (YOUR TASK)
3. ⏳ Update frontend React component to display progress
4. ⏳ Test with large tour file (>100MB)
5. ⏳ Deploy to production

---

**Questions?** Check the implementation in:
- `includes/class-h3tm-admin.php` lines 1895-2065
- `includes/class-h3tm-lambda-webhook.php` lines 285-333
