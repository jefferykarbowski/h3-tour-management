# 403 Forbidden Error - Root Cause Analysis

**Date**: 2025-10-16
**Status**: 🔴 CRITICAL - Tours inaccessible
**Impact**: All tours (new and legacy) returning 403 Forbidden

---

## Executive Summary

Tours are returning **403 Forbidden** errors from CloudFront/S3. Investigation reveals **two critical issues**:

1. **🐛 CDN Helper Bug**: Incorrectly converts spaces to dashes (legacy tours stored WITH spaces)
2. **🔒 S3/CloudFront Permissions**: Bucket policy doesn't allow public/CloudFront access

---

## Evidence

### ✅ Files Exist in S3

```bash
# New tour (ID-based)
aws s3 ls s3://h3-tour-files-h3vt/tours/20251016_050212_enj0cply/
# OUTPUT: Files present including thumbnail.png, script.js, etc.

# Legacy tour (name-based with SPACES)
aws s3 ls "s3://h3-tour-files-h3vt/tours/Arden Pikesville/"
# OUTPUT: index.htm (3882 bytes), thumbnail.png, etc.
```

**Key Finding**: Legacy tours stored as `Arden Pikesville/` (with space), NOT `Arden-Pikesville/`

### ❌ CloudFront Returns 403

```bash
curl -I "https://d14z8re58oj029.cloudfront.net/tours/20251016_050212_enj0cply/index.htm"
# HTTP/1.1 403 Forbidden
# X-Cache: Error from cloudfront
```

**Key Finding**: CloudFront can access S3, but S3 denies the request

### ⚠️ Bucket Policy Restricted

```bash
aws s3api get-bucket-policy --bucket h3-tour-files-h3vt
# ERROR: User not authorized to perform s3:GetBucketPolicy
```

**Key Finding**: Current IAM user has very restricted permissions

---

## Root Cause #1: CDN Helper Path Conversion Bug

### Location
`includes/class-h3tm-cdn-helper.php` lines 70-72

### Current Code (WRONG)
```php
// Convert spaces to dashes for S3/CloudFront compatibility
// S3 stores tours with dashes instead of spaces
$tour_name_with_dashes = str_replace(' ', '-', $tour_name);
```

### The Problem
- Comment says "S3 stores tours with dashes"
- **Reality**: S3 stores legacy tours WITH SPACES (`Arden Pikesville/`)
- Code converts `"Arden Pikesville"` → `"Arden-Pikesville"`
- Tries to fetch: `tours/Arden-Pikesville/index.htm`
- Actual S3 path: `tours/Arden Pikesville/index.htm`
- **Result**: 404 or 403 (path doesn't match)

### URL Generation Flow (BROKEN)
```
User requests: /h3panos/Arden%20Pikesville/
    ↓
Slug resolved: "Arden Pikesville"
    ↓
CDN Helper converts spaces → dashes: "Arden-Pikesville"
    ↓
CloudFront URL: https://d14z8re58oj029.cloudfront.net/tours/Arden-Pikesville/index.htm
    ↓
S3 lookup: tours/Arden-Pikesville/ ❌ DOESN'T EXIST
    ↓
Actual path: tours/Arden Pikesville/ ✅ EXISTS
```

### Solution
Use **URL encoding** instead of dash replacement:
```php
// Use rawurlencode for spaces (Arden%20Pikesville)
$tour_s3_path = rawurlencode($tour_name);
```

---

## Root Cause #2: S3 Bucket Policy Permissions

### Symptoms
- CloudFront returns `X-Cache: Error from cloudfront`
- S3 denies CloudFront's request
- Both new and legacy tours affected equally

### Required Bucket Policy

The S3 bucket `h3-tour-files-h3vt` needs a policy allowing:
1. **CloudFront OAI (Origin Access Identity)** read access
2. **OR Public read access** for tour files

### Option A: CloudFront OAI (Recommended)
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowCloudFrontOAI",
      "Effect": "Allow",
      "Principal": {
        "AWS": "arn:aws:iam::cloudfront:user/CloudFront Origin Access Identity E***********"
      },
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::h3-tour-files-h3vt/tours/*"
    }
  ]
}
```

**Steps to implement**:
1. Create CloudFront Origin Access Identity (OAI)
2. Update CloudFront distribution to use OAI
3. Add bucket policy allowing OAI to read `tours/*`

### Option B: Public Read Access (Less Secure)
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadGetObject",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::h3-tour-files-h3vt/tours/*"
    }
  ]
}
```

**Security consideration**: Makes ALL tours publicly accessible via direct S3 URLs

### Check Public Access Block
```bash
aws s3api get-public-access-block --bucket h3-tour-files-h3vt
```

If public access is blocked, it must be disabled for Option B, or use Option A (OAI).

---

## Impact Analysis

### Current State
| Tour Type | Example | S3 Path | Generated URL | Result |
|-----------|---------|---------|---------------|--------|
| New (ID-based) | asdfasdf | `tours/20251016_050212_enj0cply/` | ✅ Correct | 🔒 403 (permissions) |
| Legacy (spaces) | Arden Pikesville | `tours/Arden Pikesville/` | ❌ Wrong (`Arden-Pikesville`) | 🔒 403 (wrong path + permissions) |

### After CDN Helper Fix
| Tour Type | Example | S3 Path | Generated URL | Result |
|-----------|---------|---------|---------------|--------|
| New (ID-based) | asdfasdf | `tours/20251016_050212_enj0cply/` | ✅ Correct | 🔒 403 (permissions) |
| Legacy (spaces) | Arden Pikesville | `tours/Arden Pikesville/` | ✅ Correct (`Arden%20Pikesville`) | 🔒 403 (permissions) |

### After Both Fixes
| Tour Type | Example | S3 Path | Generated URL | Result |
|-----------|---------|---------|---------------|--------|
| New (ID-based) | asdfasdf | `tours/20251016_050212_enj0cply/` | ✅ Correct | ✅ 200 OK |
| Legacy (spaces) | Arden Pikesville | `tours/Arden Pikesville/` | ✅ Correct | ✅ 200 OK |

---

## Recommended Fix Order

### Phase 1: Fix CDN Helper (Code Fix)
1. Update `class-h3tm-cdn-helper.php` to use URL encoding
2. Remove incorrect space-to-dash conversion
3. Test URL generation with both tour types

### Phase 2: Fix S3/CloudFront Permissions (AWS Configuration)
1. **Option A (Recommended)**: Set up CloudFront OAI
   - Create OAI in CloudFront console
   - Update distribution origin settings
   - Add bucket policy allowing OAI
2. **Option B (Alternative)**: Enable public read
   - Update bucket policy
   - Disable public access blocks if needed

### Phase 3: Verification
1. Test new tour: `https://h3vt.local/h3panos/asdfasdf/`
2. Test legacy tour: `https://h3vt.local/h3panos/Arden%20Pikesville/`
3. Verify CloudFront headers show `X-Cache: Hit from cloudfront`

---

## Verification Commands

### Check S3 Structure
```bash
# List all tours
aws s3 ls s3://h3-tour-files-h3vt/tours/

# Check specific tour with spaces
aws s3 ls "s3://h3-tour-files-h3vt/tours/Arden Pikesville/"

# Check new tour
aws s3 ls s3://h3-tour-files-h3vt/tours/20251016_050212_enj0cply/
```

### Test CloudFront Access
```bash
# Test new tour
curl -I "https://d14z8re58oj029.cloudfront.net/tours/20251016_050212_enj0cply/index.htm"

# Test legacy tour (URL encoded)
curl -I "https://d14z8re58oj029.cloudfront.net/tours/Arden%20Pikesville/index.htm"
```

### Expected Results After Fix
```
HTTP/1.1 200 OK
Content-Type: text/html
X-Cache: Hit from cloudfront  (or Miss from cloudfront on first request)
```

---

## Files to Modify

1. **includes/class-h3tm-cdn-helper.php** - Fix space conversion
2. **AWS S3 Bucket Policy** - Add CloudFront OAI or public read permissions
3. **CloudFront Distribution** (if using OAI) - Update origin settings

---

## References

- **S3 Architecture**: `docs/architecture/S3_ARCHITECTURE.md`
- **CloudFront Setup**: `docs/cloudfront-setup.md`
- **Debug Logs**: Tour metadata resolving correctly, URLs being generated incorrectly
- **AWS IAM User**: `h3-tour-uploader` (restricted permissions)
- **CloudFront Domain**: `d14z8re58oj029.cloudfront.net`
- **S3 Bucket**: `h3-tour-files-h3vt` (us-east-1)

---

**Next Steps**: Implement CDN Helper fix, then coordinate with AWS admin to update bucket policy.
