# Apply S3 Bucket Policy - Quick Guide

**CloudFront Distribution**: E2NXJFS5WWGCEO
**Origin Access Control**: E13T04WFZC3OSE (already configured)
**S3 Bucket**: h3-tour-files-h3vt
**AWS Account**: 991497151548

---

## ✅ Good News!

CloudFront already has an **Origin Access Control (OAC)** configured:
- **ID**: E13T04WFZC3OSE
- **Name**: oac-h3-tour-files-h3vt.s3.amazonaws.com-mg3psvcbb21
- **Status**: ✅ Active on distribution E2NXJFS5WWGCEO

**This is the modern replacement for Origin Access Identities (OAI).**

---

## 🚀 One-Step Fix

All you need to do is **apply the bucket policy** to allow this OAC to access your S3 bucket.

### Option 1: AWS Console (Easiest)

1. Go to **S3 Console**: https://s3.console.aws.amazon.com/s3/buckets/h3-tour-files-h3vt
2. Click **Permissions** tab
3. Scroll to **Bucket policy**
4. Click **Edit**
5. **Paste this policy**:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowCloudFrontOAC",
      "Effect": "Allow",
      "Principal": {
        "Service": "cloudfront.amazonaws.com"
      },
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::h3-tour-files-h3vt/tours/*",
      "Condition": {
        "StringEquals": {
          "AWS:SourceArn": "arn:aws:cloudfront::991497151548:distribution/E2NXJFS5WWGCEO"
        }
      }
    }
  ]
}
```

6. Click **Save changes**

### Option 2: AWS CLI (Fast)

```bash
cd "C:\Users\Jeff\Documents\GitHub\h3-tour-management"

aws s3api put-bucket-policy \
  --bucket h3-tour-files-h3vt \
  --policy file://docs/s3-bucket-policy-cloudfront-oac.json
```

**Note**: If you get permission error, you'll need to use AWS Console or contact admin.

---

## 🧪 Test Immediately

After applying the policy, test right away:

```bash
# Test new tour
curl -I "https://d14z8re58oj029.cloudfront.net/tours/20251016_050212_enj0cply/index.htm"

# Test legacy tour
curl -I "https://d14z8re58oj029.cloudfront.net/tours/Arden%20Pikesville/index.htm"
```

**Expected**:
```
HTTP/1.1 200 OK
Content-Type: text/html
X-Cache: Miss from cloudfront  (or Hit from cloudfront)
```

---

## 📋 What This Policy Does

- **Allows**: CloudFront distribution `E2NXJFS5WWGCEO` to read files
- **From**: S3 bucket `h3-tour-files-h3vt`
- **Path**: Only files under `tours/*`
- **Method**: Using Origin Access Control `E13T04WFZC3OSE`

**Security**: Direct S3 URLs will NOT work - only CloudFront URLs will be accessible.

---

## ⚠️ Important Notes

### CloudFront OAC vs OAI
- **OAC (Origin Access Control)**: ✅ Modern, recommended (what you have)
- **OAI (Origin Access Identity)**: ❌ Legacy, being deprecated

**You're already using the modern approach!** CloudFront automatically created the OAC when you set up the distribution.

### Why You're Getting 403 Now
The OAC is configured in CloudFront, but the **S3 bucket doesn't allow it yet**. That's why you're getting:
```
HTTP/1.1 403 Forbidden
X-Cache: Error from cloudfront
```

Once you apply the bucket policy, CloudFront will be allowed to fetch files from S3.

---

## 🔍 Verify Policy Applied

```bash
# Check bucket policy (requires s3:GetBucketPolicy permission)
aws s3api get-bucket-policy --bucket h3-tour-files-h3vt

# Should show the policy we just applied
```

---

## ✅ After Applying Policy

Your tours will immediately work:
- `https://h3vt.local/h3panos/asdfasdf/` → ✅ Works
- `https://h3vt.local/h3panos/Arden%20Pikesville/` → ✅ Works

CloudFront will cache the files, and subsequent requests will be fast.

---

## 🆘 Troubleshooting

### Still Getting 403 After Applying Policy?

1. **Wait 30 seconds** - S3 policy changes are nearly instant but give it a moment
2. **Invalidate CloudFront cache**:
   ```bash
   aws cloudfront create-invalidation \
     --distribution-id E2NXJFS5WWGCEO \
     --paths "/tours/*"
   ```
3. **Verify distribution uses OAC**: Go to CloudFront → Distribution → Origins → Check origin settings

### Permission Error When Applying Policy?

**Error**: `User is not authorized to perform: s3:PutBucketPolicy`

**Solution**:
- Use AWS Console instead (usually has broader permissions)
- OR contact AWS admin to apply the policy
- OR add this permission to your IAM user

---

## 📊 Summary

| Component | Status | Action |
|-----------|--------|--------|
| CloudFront Distribution | ✅ Active | None - already configured |
| Origin Access Control | ✅ Created | None - already exists |
| S3 Bucket Policy | ⏳ Missing | **Apply policy above** |
| CDN Helper Code | ✅ Fixed | None - already committed |

**ONE STEP LEFT**: Apply the bucket policy above! 🚀

---

**Policy File**: `docs/s3-bucket-policy-cloudfront-oac.json`
**Distribution ID**: E2NXJFS5WWGCEO
**OAC ID**: E13T04WFZC3OSE
**AWS Account**: 991497151548
