# Lambda Deployment Fix - Remove ACL Parameter

## Problem Summary

Lambda was crashing with `AccessControlListNotSupported` error because the S3 bucket `h3-tour-files-h3vt` has ACLs disabled (AWS best practice). The code was attempting to set `ACL: 'public-read'` on uploaded files, causing S3 to reject the request with a 400 error.

## Root Cause

```javascript
// BROKEN CODE (causes Lambda to crash):
await s3.send(new PutObjectCommand({
    Bucket: bucket,
    Key: s3Key,
    Body: fileData,
    ContentType: getContentType(file.path),
    ACL: 'public-read'  // ❌ This breaks Lambda!
}));
```

**Error in CloudWatch Logs:**
```json
{
    "errorType": "AccessControlListNotSupported",
    "errorMessage": "The bucket does not allow ACLs",
    "$fault": "client",
    "$metadata": {
        "httpStatusCode": 400
    }
}
```

## Solution

Remove the ACL parameter entirely. The bucket policy handles public access:

```javascript
// CORRECTED CODE (works with bucket policy):
await s3.send(new PutObjectCommand({
    Bucket: bucket,
    Key: s3Key,
    Body: fileData,
    ContentType: getContentType(file.path)
    // Bucket policy handles public access - ACLs are disabled on this bucket
}));
```

## Bucket Policy Confirmation

The S3 bucket policy already grants public read access:

```json
{
    "Sid": "AllowPublicReadTourFiles",
    "Effect": "Allow",
    "Principal": "*",
    "Action": "s3:GetObject",
    "Resource": "arn:aws:s3:::h3-tour-files-h3vt/tours/*"
}
```

This makes object-level ACLs unnecessary.

## Deployment Instructions

### Option 1: AWS Console (Recommended)

1. **Navigate to Lambda Console:**
   - Go to https://console.aws.amazon.com/lambda/
   - Select region: **us-east-1**
   - Click on function: **h3tm-tour-processor**

2. **Upload Deployment Package:**
   - Click **"Upload from"** → **".zip file"**
   - Choose file: `C:\Users\Jeff\Documents\GitHub\h3-tour-management\lambda\function.zip` (46KB)
   - Click **"Save"**

3. **Verify Deployment:**
   - Go to **"Code"** tab
   - Open `index.js` in the code editor
   - Scroll to lines 100-106
   - Confirm the `PutObjectCommand` does NOT have `ACL` parameter
   - You should see the comment: `// Bucket policy handles public access - ACLs are disabled on this bucket`

4. **Test the Fix:**
   - Go to WordPress admin: https://h3vt.local/wp-admin/admin.php?page=h3-tour-management
   - Upload a new test tour
   - Monitor the upload progress - should complete successfully without timeout
   - Verify tour appears in the table with status "completed"

### Option 2: AWS CLI (Requires IAM Permissions)

```bash
cd /c/Users/Jeff/Documents/GitHub/h3-tour-management/lambda

aws lambda update-function-code \
  --function-name h3tm-tour-processor \
  --zip-file fileb://function.zip \
  --region us-east-1
```

**Note:** This requires the IAM user to have `lambda:UpdateFunctionCode` permission, which `h3-tour-uploader` currently lacks.

## Expected Behavior After Fix

1. **Upload Phase:**
   - User uploads ZIP to WordPress
   - File uploaded to `s3://h3-tour-files-h3vt/uploads/{tour_id}/{tour_id}.zip`
   - Tour status: "uploading"

2. **Lambda Processing:**
   - S3 ObjectCreated event triggers Lambda
   - Lambda downloads ZIP, extracts files
   - Lambda uploads files to `s3://h3-tour-files-h3vt/tours/{tour_id}/` **without ACL parameter**
   - S3 accepts uploads (no more AccessControlListNotSupported error)
   - Bucket policy provides public read access

3. **Completion Phase:**
   - Lambda notifies WordPress webhook
   - Tour status updated to "completed"
   - Frontend polling succeeds (200 response from CloudFront/S3)
   - User sees tour in table with working "View" link

## Timeline Reference

**Before Fix:**
- 02:17:03 - Upload starts
- 02:18:30 - Lambda triggered
- 02:21:48 - Lambda crashes trying to use ACL
- 02:23:04 - Frontend timeout after 60 failed polls
- Result: NO files in `tours/` directory, tour stuck in "uploading" status

**After Fix:**
- Upload starts
- Lambda triggered
- Lambda successfully extracts and uploads files (no ACL error)
- Files appear in `tours/` directory with bucket policy public access
- Frontend polling succeeds
- Tour status changes to "completed"

## Files Modified

- `lambda/index.js` lines 100-106 - Removed ACL parameter from PutObjectCommand
- Deployment package: `lambda/function.zip` (46KB)

## Verification Checklist

- [ ] Deployed `function.zip` to Lambda function `h3tm-tour-processor`
- [ ] Confirmed code in AWS Console does not have ACL parameter
- [ ] Uploaded test tour
- [ ] Upload completed without timeout
- [ ] Tour status changed to "completed"
- [ ] Files exist in S3 `tours/` directory
- [ ] CloudFront returns 200 for tour URL
- [ ] "View" button works in admin table

## Additional Notes

- The bucket has ACLs disabled globally - this is AWS best practice as of 2018
- All public access is controlled via bucket policy, not object ACLs
- CloudFront uses OAI (Origin Access Identity) for secure access
- Direct S3 URLs also work due to bucket policy allowing public `s3:GetObject`
