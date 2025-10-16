# 403 Forbidden Errors - Solution Summary

**Date**: 2025-10-16
**Status**: ✅ Code Fixed | ⏳ AWS Configuration Pending
**Commit**: 8108e06

---

## Problem Summary

All tours (new and legacy) were returning **403 Forbidden** from CloudFront/S3.

**Example URLs:**
- New tour: `https://h3vt.local/h3panos/asdfasdf/` → 403
- Legacy tour: `https://h3vt.local/h3panos/Arden%20Pikesville/` → 403

---

## Root Causes Found

### 1. CDN Helper Path Bug (FIXED ✅)
**Location**: `includes/class-h3tm-cdn-helper.php`

**Problem**: Code incorrectly converted spaces to dashes
- Legacy tours stored as `Arden Pikesville/` (with space) in S3
- Code generated URLs with `Arden-Pikesville` (dash)
- Result: Wrong path, 404/403 errors

**Fix Applied**: Changed to URL encoding
- Primary: `Arden%20Pikesville` (URL encoded)
- Fallback: `Arden-Pikesville` (for edge cases)

### 2. S3 Bucket Policy Missing (PENDING ⏳)
**Location**: AWS S3 bucket `h3-tour-files-h3vt`

**Problem**: Bucket doesn't allow CloudFront or public access
- CloudFront gets `403 Forbidden` from S3
- Header shows `X-Cache: Error from cloudfront`

**Fix Required**: Apply bucket policy (see below)

---

## What Was Fixed (Code)

✅ **files Changed:**
1. `includes/class-h3tm-cdn-helper.php` - Fixed URL generation
2. `docs/403-error-diagnosis.md` - Complete root cause analysis
3. `docs/fix-403-permissions-guide.md` - Step-by-step AWS setup
4. `docs/s3-bucket-policy-cloudfront-oai.json` - CloudFront OAI policy template
5. `docs/s3-bucket-policy-public-read.json` - Public read policy template
6. `tools/diagnose-s3-tours.php` - WordPress diagnostic page
7. `diagnose-403.sh` - AWS CLI diagnostic script

✅ **Committed**: Commit `8108e06`

---

## What Still Needs to Be Done (AWS)

⏳ **Apply S3 Bucket Policy**

You need to choose ONE option:

### Option A: CloudFront OAI (Recommended - More Secure)

**Steps:**
1. Create CloudFront Origin Access Identity in AWS Console
2. Update CloudFront distribution to use the OAI
3. Apply bucket policy: `docs/s3-bucket-policy-cloudfront-oai.json`

**Detailed Guide**: `docs/fix-403-permissions-guide.md` (Option A section)

**Result**: Only CloudFront can access S3 files

### Option B: Public Read (Alternative - Simpler)

**Steps:**
1. Disable public access block on bucket (if enabled)
2. Apply bucket policy: `docs/s3-bucket-policy-public-read.json`

**Detailed Guide**: `docs/fix-403-permissions-guide.md` (Option B section)

**Result**: Anyone can access tours via S3 or CloudFront

---

## IAM Permissions Issue

**Current User**: `h3-tour-uploader` (IAM user)
**Problem**: Cannot view or modify bucket policies

**Error Received:**
```
User: arn:aws:iam::991497151548:user/h3-tour-uploader is not authorized
to perform: s3:GetBucketPolicy
```

**Options:**
1. Contact AWS admin to apply bucket policy
2. OR grant `s3:PutBucketPolicy` permission to `h3-tour-uploader`

---

## Verification Steps

After applying the bucket policy:

```bash
# Test new tour
curl -I "https://d14z8re58oj029.cloudfront.net/tours/20251016_050212_enj0cply/index.htm"

# Test legacy tour (URL encoded)
curl -I "https://d14z8re58oj029.cloudfront.net/tours/Arden%20Pikesville/index.htm"

# Expected result:
# HTTP/1.1 200 OK
# X-Cache: Miss from cloudfront (or Hit from cloudfront)
```

Then test in WordPress:
- `https://h3vt.local/h3panos/asdfasdf/` → Should show tour
- `https://h3vt.local/h3panos/Arden%20Pikesville/` → Should show tour

---

## Impact Analysis

### Before Fix
| Tour Type | Path Generated | S3 Actual Path | Result |
|-----------|---------------|----------------|--------|
| New (asdfasdf) | `20251016_050212_enj0cply` | `20251016_050212_enj0cply/` | 403 (permissions) |
| Legacy (Arden Pikesville) | `Arden-Pikesville` ❌ | `Arden Pikesville/` | 403 (wrong path + permissions) |

### After Code Fix (Current State)
| Tour Type | Path Generated | S3 Actual Path | Result |
|-----------|---------------|----------------|--------|
| New (asdfasdf) | `20251016_050212_enj0cply` ✅ | `20251016_050212_enj0cply/` | 403 (permissions only) |
| Legacy (Arden Pikesville) | `Arden%20Pikesville` ✅ | `Arden Pikesville/` | 403 (permissions only) |

### After Bucket Policy Applied (Target State)
| Tour Type | Path Generated | S3 Actual Path | Result |
|-----------|---------------|----------------|--------|
| New (asdfasdf) | `20251016_050212_enj0cply` ✅ | `20251016_050212_enj0cply/` | ✅ 200 OK |
| Legacy (Arden Pikesville) | `Arden%20Pikesville` ✅ | `Arden Pikesville/` | ✅ 200 OK |

---

## Quick Reference

**Complete Documentation**:
- **Root Cause Analysis**: `docs/403-error-diagnosis.md`
- **Fix Permissions**: `docs/fix-403-permissions-guide.md`
- **S3 Architecture**: `docs/architecture/S3_ARCHITECTURE.md`

**Diagnostic Tools**:
- **WordPress**: `tools/diagnose-s3-tours.php` (requires admin access)
- **AWS CLI**: `diagnose-403.sh`

**Bucket Policies**:
- **CloudFront OAI**: `docs/s3-bucket-policy-cloudfront-oai.json`
- **Public Read**: `docs/s3-bucket-policy-public-read.json`

---

## Next Steps

1. ✅ **Code fix committed** - No further code changes needed
2. ⏳ **Apply bucket policy** - Contact AWS admin OR use elevated IAM user
3. ⏳ **Wait for propagation** - CloudFront changes take 5-15 minutes
4. ⏳ **Test tours** - Verify both new and legacy tours work
5. ⏳ **Monitor logs** - Check CloudFront access logs for 200 OK responses

---

## Timeline

**Code Fix**: Completed 2025-10-16
**AWS Configuration**: Pending (requires admin access or IAM permissions)
**Estimated Time to Fix**: 15-30 minutes after AWS access obtained
**Estimated Propagation**: 5-15 minutes after bucket policy applied

---

**Created**: 2025-10-16
**Last Updated**: 2025-10-16
**Commit**: 8108e06
