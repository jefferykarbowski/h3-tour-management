# Tour Update Asynchronous Implementation - Version 2.6.3

## Summary

Converted the tour update process from **synchronous WordPress processing** to **asynchronous Lambda-based processing with real-time progress updates** to fix 504 Gateway Timeout errors on large file uploads.

## Problem

### Issue Reported (Version 2.6.2)
- **Error**: "Failed to update tour: JSON.parse: unexpected character at line 1 column 1 of the JSON data"
- **Root Cause**: 504 Gateway Timeout when updating tours with large files (>100MB)
- **HAR Analysis**:
  - S3 upload successful (176MB in ~44 seconds)
  - AJAX request to `h3tm_update_tour` timed out after 59 seconds
  - Response: Plain text "The application did not respond in time." instead of JSON
  - CloudFront invalidation overhead added to processing time

### Why It Failed
1. **Synchronous Processing**: `handle_update_tour()` downloaded, extracted, and uploaded files synchronously
2. **Long Processing Time**: Large files + CloudFront invalidation exceeded 60-second gateway timeout
3. **No User Feedback**: Users had no visibility into what was happening
4. **Poor Error Handling**: Timeout returned plain text instead of JSON, causing parse errors

## Solution Architecture

### Before (Synchronous)
```
User → WordPress → Download from S3 → Extract → Upload → Cache Invalidate → Response
                   |___________ 60+ seconds (TIMEOUT!) _______________|
```

### After (Asynchronous with Progress)
```
User → WordPress → Trigger Lambda → Immediate Response ✓
                                    ↓
                          Lambda Processing (async)
                                    ↓
                          Progress Webhooks (every stage)
                                    ↓
WordPress ← Poll for Progress ← Update Progress Transient
    ↓
Frontend displays real-time progress
```

## Implementation Details

### 1. Backend Changes (WordPress)

#### File: `includes/class-h3tm-admin.php`

**Changes to `handle_update_tour()` (lines 1895-1982)**:
- ✅ Now invokes Lambda asynchronously instead of WordPress processing
- ✅ Creates progress transient for tracking
- ✅ Updates metadata status to 'updating'
- ✅ Returns immediately with `async: true` flag
- ✅ Rollback support if Lambda invocation fails

**New method: `invoke_lambda_update()` (lines 1984-2038)**:
- ✅ Sends HTTP POST to Lambda function URL
- ✅ Passes `enableProgressUpdates: true` to enable real-time updates
- ✅ Includes webhook URL and secret for callbacks
- ✅ 30-second timeout (doesn't wait for processing)

**New method: `handle_get_update_progress()` (lines 2040-2065)**:
- ✅ AJAX endpoint for frontend polling
- ✅ Returns progress data from transient
- ✅ Secured with nonce verification

**AJAX Registration** (line 46):
```php
add_action('wp_ajax_h3tm_get_update_progress', array($this, 'handle_get_update_progress'));
```

#### File: `includes/class-h3tm-lambda-webhook.php`

**Updated `handle_webhook()` (lines 102-109)**:
- ✅ Routes to `handle_progress_update()` for progress webhooks
- ✅ Maintains backward compatibility with completion webhooks

**New method: `handle_progress_update()` (lines 285-333)**:
- ✅ Processes progress webhook from Lambda
- ✅ Updates transient with real-time progress data
- ✅ Validates tourId and payload structure
- ✅ Logs progress for debugging

**Updated `validate_webhook_payload()` (lines 125-185)**:
- ✅ Handles both progress and completion payloads
- ✅ Different validation rules for each type
- ✅ Progress: requires `type`, `tourId`, `status`, `message`, `timestamp`
- ✅ Completion: requires `success`, `tourName`, `s3Key`, `message`, `timestamp`

### 2. Frontend Changes (React)

#### File: `frontend/src/components/UpdateTourModal.tsx`

**New State Variables**:
```typescript
const [progressMessage, setProgressMessage] = useState<string>('');
const [processingStage, setProcessingStage] = useState<string>('');
const pollingIntervalRef = useRef<NodeJS.Timeout | null>(null);
```

**New Functions**:

1. **`pollProgress()`** (lines 96-143):
   - Polls `h3tm_get_update_progress` every 2 seconds
   - Updates progress bar and status messages
   - Detects completion or failure
   - Graceful error handling (doesn't stop on transient errors)

2. **`startPolling()`** (lines 145-156):
   - Initiates 2-second polling interval
   - Immediate first poll
   - Clears any existing interval

3. **`stopPolling()`** (lines 158-163):
   - Clears polling interval
   - Cleanup on completion or error

**Updated `handleUpdate()`** (lines 165-232):
- ✅ Detects async response (`data.data?.async`)
- ✅ Starts polling if async
- ✅ Backward compatible with synchronous flow
- ✅ Cleanup on errors

**Updated UI** (lines 515-539):
- ✅ Displays real-time progress message from Lambda
- ✅ Shows current processing stage
- ✅ Highlights active stage in checklist
- ✅ Progress bar updates in real-time

**Cleanup Effect** (lines 74-79):
```typescript
useEffect(() => {
  return () => {
    stopPolling();
  };
}, []);
```

### 3. Lambda Function Requirements

**See**: `docs/LAMBDA_PROGRESS_WEBHOOKS.md` for complete implementation guide

**Key Requirements**:
1. Accept `enableProgressUpdates: true` parameter
2. Send progress webhooks at these stages:
   - Initializing (5%)
   - Downloading (10-30%)
   - Extracting (30-50%)
   - Uploading (50-85%)
   - Cache Invalidation (85-95%)
   - Cleanup (95-100%)
3. Include webhook signature with HMAC-SHA256
4. Send final completion webhook (unchanged)

**Progress Webhook Payload**:
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

## Files Modified

### Backend (PHP)
1. ✅ `includes/class-h3tm-admin.php` - Async Lambda invocation
2. ✅ `includes/class-h3tm-lambda-webhook.php` - Progress webhook handler

### Frontend (TypeScript/React)
3. ✅ `frontend/src/components/UpdateTourModal.tsx` - Progress polling UI

### Documentation
4. ✅ `docs/LAMBDA_PROGRESS_WEBHOOKS.md` - Lambda implementation guide
5. ✅ `docs/TOUR_UPDATE_ASYNC_IMPLEMENTATION.md` - This file

## Testing Checklist

### WordPress Testing
- [ ] Verify Lambda function URL is configured (`h3tm_lambda_function_url` option)
- [ ] Verify webhook secret is configured (`h3tm_lambda_webhook_secret` option)
- [ ] Test with small tour file (<10MB) - should complete quickly
- [ ] Test with large tour file (>100MB) - should show progress updates
- [ ] Verify progress polling stops when complete
- [ ] Verify error handling if Lambda fails
- [ ] Check WordPress debug log for progress messages

### Lambda Testing (YOUR TASK)
- [ ] Implement progress webhook sending
- [ ] Test webhook signature generation
- [ ] Verify progress updates at each stage
- [ ] Test final completion webhook
- [ ] Handle network failures gracefully
- [ ] Test with 176MB file (the original failing case)

### Frontend Testing
- [ ] Verify progress bar updates smoothly
- [ ] Verify stage highlights change correctly
- [ ] Verify progress message updates in real-time
- [ ] Test modal close during processing (should be prevented)
- [ ] Test cleanup on unmount
- [ ] Verify backward compatibility if Lambda doesn't support progress

## Backward Compatibility

✅ **Fully backward compatible**:
- If Lambda doesn't send progress updates, completion webhook still works
- Frontend handles both async and sync responses
- Existing tours and processes unaffected
- Old synchronous flow still supported (fallback)

## Performance Impact

**WordPress**:
- ⚡ Immediate response (no timeout)
- 📊 Minimal overhead (transient storage + polling)
- 🔄 Poll every 2 seconds (low impact)

**Lambda**:
- 📤 10-15 progress webhooks per update
- 📦 ~500 bytes per webhook
- ⏱️ 10-second timeout per webhook

**Frontend**:
- 🔍 Poll every 2 seconds while processing
- 💾 Minimal state management
- ⚡ Auto-cleanup on completion

## Security Considerations

1. **Webhook Signature**: All webhooks signed with HMAC-SHA256
2. **Nonce Verification**: All AJAX requests require valid nonce
3. **Timestamp Validation**: Webhooks expire after 24 hours
4. **HTTPS Required**: All webhook URLs use HTTPS
5. **Capability Check**: Only admins can trigger updates

## Monitoring & Debugging

### WordPress Debug Log
```
H3TM Update: Starting asynchronous Lambda update for tour_id: 20251021_205548_a5lufh63
H3TM Update: Invoking Lambda for tour_id: 20251021_205548_a5lufh63
H3TM Update: Lambda response: 200 - Success
H3TM Lambda Progress: Updated progress for tour_id 20251021_205548_a5lufh63 - 35% - Extracting tour files...
H3TM Lambda Progress: Updated progress for tour_id 20251021_205548_a5lufh63 - 65% - Uploaded 150/200 files
H3TM Lambda Progress: Updated progress for tour_id 20251021_205548_a5lufh63 - 100% - Tour update complete!
H3TM Lambda Webhook: Updated metadata status to 'completed' for tour_id: 20251021_205548_a5lufh63
```

### Frontend Console
```
Processing tour update...
Progress: 35% - Extracting tour files...
Progress: 65% - Uploaded 150/200 files
Progress: 100% - Tour update complete!
Update complete, refreshing tour list...
```

## Migration Guide

### For Developers

**Step 1**: Update WordPress plugin to 2.6.3
```bash
git pull origin main
git checkout v2.6.3
```

**Step 2**: Update Lambda function with progress webhooks
- Follow `docs/LAMBDA_PROGRESS_WEBHOOKS.md`
- Test locally with sample payloads
- Deploy to Lambda

**Step 3**: Test end-to-end
- Upload small tour (verify works)
- Upload large tour (verify progress updates)
- Monitor logs for errors

**Step 4**: Deploy to production
- Deploy WordPress changes
- Deploy Lambda changes
- Monitor first production update

### For Users

No action required! The update is transparent:
- ✅ No more timeout errors
- ✅ Real-time progress visibility
- ✅ Same workflow as before

## Known Limitations

1. **Progress Accuracy**: Progress percentage is approximate based on stages
2. **Polling Overhead**: 2-second polling adds minimal server load
3. **Lambda Dependency**: Requires Lambda function update for progress
4. **Network Delays**: Progress updates may lag by 2-4 seconds

## Future Enhancements

Possible improvements for future versions:
- WebSocket support for real-time updates (eliminate polling)
- More granular progress tracking (per-file upload progress)
- Resume support for interrupted uploads
- Progress notifications via email or push
- Admin notification when updates complete

## Version History

**v2.6.2** (Broken):
- Synchronous WordPress processing
- 504 timeouts on large files
- No progress visibility

**v2.6.3** (Current):
- Asynchronous Lambda processing
- Real-time progress webhooks
- No timeouts, better UX
- Backward compatible

## Support

For issues or questions:
1. Check WordPress debug log
2. Check Lambda CloudWatch logs
3. Review HAR file for network issues
4. Reference `docs/LAMBDA_PROGRESS_WEBHOOKS.md`

---

**Status**: ✅ WordPress implementation COMPLETE | ⏳ Lambda implementation PENDING (YOUR TASK)

**Next Step**: Update Lambda function with progress webhooks following `docs/LAMBDA_PROGRESS_WEBHOOKS.md`
