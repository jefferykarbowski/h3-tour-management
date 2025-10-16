# Root Cause: S3 Public Access Block Enabled

**Date**: 2025-10-16
**Critical Finding**: Public Access Block (PAB) is blocking new tour access

---

## 🔍 Evidence Analysis

### Test Results Summary

| Tour Type | Direct S3 Access | CloudFront | Explanation |
|-----------|------------------|------------|-------------|
| **Legacy** (Arden Pikesville) | ✅ 200 OK | ❌ 403 | Has old object ACL from before PAB |
| **New** (20251016_050212_enj0cply) | ❌ 403 | ❌ 403 | Blocked by PAB despite bucket policy |

### Bucket Policy Analysis

Your bucket policy **correctly allows** public read:
```json
{
  "Sid": "AllowPublicReadTourFiles",
  "Effect": "Allow",
  "Principal": "*",
  "Action": "s3:GetObject",
  "Resource": "arn:aws:s3:::h3-tour-files-h3vt/tours/*"
}
```

**But new tours still get 403!** This means the bucket policy is being **overridden** by a higher-level setting.

---

## 🎯 Root Cause: Public Access Block (PAB)

### What is Public Access Block?

AWS introduced PAB as an extra security layer that **overrides bucket policies**:
- ✅ Existing object ACLs continue to work (legacy tours)
- ❌ New objects cannot use bucket policy for public access (new tours)
- ❌ Blocks all new public access attempts regardless of bucket policy

### Why This Explains Everything

**Legacy Tours Work**:
- Uploaded BEFORE PAB was enabled
- Have object-level `public-read` ACL
- PAB doesn't affect existing ACLs
- Result: Direct S3 access works ✅

**New Tours Fail**:
- Uploaded AFTER PAB was enabled
- Lambda doesn't set object ACL (we removed that due to ACL parameter bug)
- Rely on bucket policy for access
- PAB blocks bucket policy from granting public access
- Result: Direct S3 access fails ❌

**Presigned URL Works**:
- Presigned URLs use temporary credentials
- Not affected by PAB
- Result: Works for all tours ✅

---

## 🚀 Solution: Disable Public Access Block

### Option 1: AWS Console (Recommended)

1. Go to: https://s3.console.aws.amazon.com/s3/buckets/h3-tour-files-h3vt
2. Click **Permissions** tab
3. Scroll to **Block public access (bucket settings)**
4. Click **Edit**
5. **Uncheck all 4 boxes**:
   - ☐ Block all public access
   - ☐ Block public access to buckets and objects granted through new access control lists (ACLs)
   - ☐ Block public access to buckets and objects granted through any access control lists (ACLs)
   - ☐ Block public access to buckets and objects granted through new public bucket or access point policies
   - ☐ Block public and cross-account access to buckets and objects through any public bucket or access point policies
6. Click **Save changes**
7. Type `confirm` and click **Confirm**

### Option 2: AWS CLI

```bash
aws s3api delete-public-access-block --bucket h3-tour-files-h3vt
```

**Note**: Requires `s3:PutBucketPublicAccessBlock` permission

---

## 🔒 CloudFront OAC Issue (Separate Problem)

CloudFront is **also** getting 403 for ALL tours. This is a different issue.

### Incomplete CloudFront Statement

Your bucket policy shows:
```json
{
  "Sid": "AllowCloudFrontServicePrincipal",
  ...
```

But it's cut off. It likely needs to be:

```json
{
  "Sid": "AllowCloudFrontServicePrincipal",
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
```

**Action Required**: Verify this statement exists and has the correct distribution ARN.

---

## 📋 Complete Fix Checklist

### Step 1: Disable Public Access Block
- [ ] Go to S3 Console → h3-tour-files-h3vt → Permissions
- [ ] Edit Block public access settings
- [ ] Uncheck all 4 boxes
- [ ] Save and confirm

### Step 2: Verify Bucket Policy Has CloudFront OAC Statement
- [ ] Check bucket policy has complete CloudFront statement
- [ ] Verify distribution ARN: `arn:aws:cloudfront::991497151548:distribution/E2NXJFS5WWGCEO`
- [ ] Add if missing using `docs/s3-bucket-policy-cloudfront-oac.json`

### Step 3: Test Direct S3 Access
```bash
# Should now work for new tours
curl -I "https://h3-tour-files-h3vt.s3.us-east-1.amazonaws.com/tours/20251016_050212_enj0cply/index.htm"
# Expected: HTTP/1.1 200 OK ✅
```

### Step 4: Test CloudFront Access
```bash
# Should work after OAC statement is verified
curl -I "https://d14z8re58oj029.cloudfront.net/tours/20251016_050212_enj0cply/index.htm"
# Expected: HTTP/1.1 200 OK ✅
```

### Step 5: Restart Local WordPress
- [ ] Stop Local by Flywheel
- [ ] Start Local by Flywheel
- [ ] Clear PHP OPcache

### Step 6: Test WordPress Proxy
- [ ] Test: `https://h3vt.local/h3panos/asdfasdf/`
- [ ] Test: `https://h3vt.local/h3panos/Arden%20Pikesville/`
- [ ] Expected: Both tours load ✅

---

## 🎓 Understanding the Layers

### Access Control Hierarchy (Top to Bottom)

1. **Public Access Block** ← **BLOCKING YOU NOW**
   - Overrides everything below
   - Must be disabled for public bucket policies to work

2. **Bucket Policy**
   - Your policy is correct ✅
   - Being blocked by PAB ❌

3. **Object ACLs**
   - Legacy tours have these
   - New tours don't (we removed ACL setting from Lambda)

4. **Presigned URLs**
   - Bypass all of the above
   - Use temporary credentials

### Why Legacy Tours Work But New Ones Don't

```
Legacy Tour Upload (Before PAB):
1. Lambda uploads with ACL: public-read
2. Object gets public-read ACL
3. PAB enabled later (doesn't affect existing ACLs)
4. Direct S3 access: ✅ Works via object ACL

New Tour Upload (After PAB):
1. Lambda uploads without ACL (we fixed ACL parameter bug)
2. Relies on bucket policy for public access
3. PAB blocks bucket policy from granting public access
4. Direct S3 access: ❌ Blocked by PAB
```

---

## ⚡ Quick Summary

**Problem**: Public Access Block is enabled on the bucket

**Why Legacy Tours Work**: They have old object ACLs from before PAB was enabled

**Why New Tours Fail**: They rely on bucket policy, which PAB blocks

**Why CloudFront Fails**: Separate issue - OAC statement may be incomplete

**Solution**:
1. Disable Public Access Block (5-minute fix)
2. Verify CloudFront OAC statement in bucket policy
3. Restart Local WordPress
4. Test

---

## 🔐 Security Note

Disabling PAB is safe in your case because:
- ✅ You have explicit bucket policy controlling access
- ✅ Only `tours/*` and `uploads/*` are public
- ✅ Bucket root and other paths remain private
- ✅ RequireSSLRequestsOnly ensures HTTPS-only access

**Alternative (More Secure)**: Use CloudFront OAC exclusively and keep PAB enabled, but this requires:
- Updating all URLs to use CloudFront only (no direct S3)
- CloudFront OAC statement must be correctly configured
- More complex setup

---

**Next Step**: Disable Public Access Block in AWS Console, then test!
