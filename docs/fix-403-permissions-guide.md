# Fix 403 Forbidden - S3/CloudFront Permissions Setup

**Issue**: Tours returning 403 Forbidden from CloudFront/S3
**Cause**: Bucket policy doesn't allow CloudFront or public access to tour files
**Bucket**: `h3-tour-files-h3vt`
**Region**: `us-east-1`
**CloudFront**: `d14z8re58oj029.cloudfront.net`

---

## Quick Fix Summary

**Choose ONE option:**
- **Option A (Recommended)**: CloudFront Origin Access Identity (OAI) - More secure
- **Option B (Alternative)**: Public Read Access - Simpler but less secure

---

## Option A: CloudFront OAI (Recommended)

### Step 1: Create CloudFront Origin Access Identity

1. Go to **CloudFront Console**: https://console.aws.amazon.com/cloudfront/
2. Click **Origin Access Identities** in left sidebar
3. Click **Create Origin Access Identity**
4. Name: `h3-tour-files-oai`
5. Click **Create**
6. **Copy the OAI ID** (format: `EXXXXXXXXXXX`)

### Step 2: Update CloudFront Distribution

1. Go to **CloudFront Distributions**
2. Select your distribution (domain: `d14z8re58oj029.cloudfront.net`)
3. Click **Origins** tab
4. Select S3 origin (`h3-tour-files-h3vt.s3.us-east-1.amazonaws.com`)
5. Click **Edit**
6. Under **Origin Access**:
   - Select **Legacy access identities**
   - Choose the OAI you just created (`h3-tour-files-oai`)
   - Check **Yes, update the bucket policy**
7. Click **Save changes**

**NOTE**: CloudFront may automatically add the bucket policy. If not, proceed to Step 3.

### Step 3: Apply Bucket Policy (Manual)

If CloudFront didn't auto-update the policy:

1. Get your OAI ARN:
   ```bash
   # Format: arn:aws:iam::cloudfront:user/CloudFront Origin Access Identity EXXXXXXXXXXX
   # Replace EXXXXXXXXXXX with your OAI ID from Step 1
   ```

2. Edit `docs/s3-bucket-policy-cloudfront-oai.json`:
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       {
         "Sid": "AllowCloudFrontOAI",
         "Effect": "Allow",
         "Principal": {
           "AWS": "arn:aws:iam::cloudfront:user/CloudFront Origin Access Identity EXXXXXXXXXXX"
         },
         "Action": "s3:GetObject",
         "Resource": "arn:aws:s3:::h3-tour-files-h3vt/tours/*"
       }
     ]
   }
   ```

3. Apply the policy:
   ```bash
   aws s3api put-bucket-policy \
     --bucket h3-tour-files-h3vt \
     --policy file://docs/s3-bucket-policy-cloudfront-oai.json
   ```

### Step 4: Verify

Test CloudFront access:
```bash
# Should return 200 OK
curl -I "https://d14z8re58oj029.cloudfront.net/tours/20251016_050212_enj0cply/index.htm"

# Check for success indicators:
# HTTP/1.1 200 OK
# X-Cache: Miss from cloudfront (first request) or Hit from cloudfront (cached)
```

---

## Option B: Public Read Access (Simpler, Less Secure)

### Step 1: Disable Public Access Block (if enabled)

```bash
# Check current settings
aws s3api get-public-access-block --bucket h3-tour-files-h3vt

# If blocked, disable (requires appropriate IAM permissions)
aws s3api delete-public-access-block --bucket h3-tour-files-h3vt
```

**WARNING**: This makes the bucket open to public access configuration. Only disable if you're comfortable with public reads.

### Step 2: Apply Public Read Policy

```bash
aws s3api put-bucket-policy \
  --bucket h3-tour-files-h3vt \
  --policy file://docs/s3-bucket-policy-public-read.json
```

**Policy Contents** (`docs/s3-bucket-policy-public-read.json`):
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

### Step 3: Verify

Test both S3 and CloudFront access:
```bash
# Direct S3 (should work)
curl -I "https://h3-tour-files-h3vt.s3.us-east-1.amazonaws.com/tours/20251016_050212_enj0cply/index.htm"

# CloudFront (should also work)
curl -I "https://d14z8re58oj029.cloudfront.net/tours/20251016_050212_enj0cply/index.htm"
```

---

## IAM Permissions Required

To apply bucket policies, your AWS user needs these permissions:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutBucketPolicy",
        "s3:GetBucketPolicy",
        "s3:GetPublicAccessBlock",
        "s3:DeletePublicAccessBlock"
      ],
      "Resource": "arn:aws:s3:::h3-tour-files-h3vt"
    }
  ]
}
```

**Current User**: `arn:aws:iam::991497151548:user/h3-tour-uploader`
**Current Permissions**: Very restricted (can't view or modify bucket policies)

**Action Required**: Contact AWS admin to either:
1. Grant the above permissions to `h3-tour-uploader` user
2. OR have admin apply the bucket policy directly

---

## Verification Checklist

After applying the fix:

- [ ] CloudFront returns 200 OK for new tours
- [ ] CloudFront returns 200 OK for legacy tours (with spaces)
- [ ] `X-Cache` header shows `Hit from cloudfront` or `Miss from cloudfront`
- [ ] No `Error from cloudfront` in headers
- [ ] WordPress proxy URLs work: `https://h3vt.local/h3panos/asdfasdf/`
- [ ] Legacy URLs work: `https://h3vt.local/h3panos/Arden%20Pikesville/`

---

## Troubleshooting

### Still Getting 403 After Applying Policy

1. **Wait for CloudFront propagation** (5-15 minutes)
2. **Invalidate CloudFront cache**:
   ```bash
   aws cloudfront create-invalidation \
     --distribution-id YOUR_DISTRIBUTION_ID \
     --paths "/tours/*"
   ```

3. **Verify bucket policy applied**:
   ```bash
   aws s3api get-bucket-policy --bucket h3-tour-files-h3vt
   ```

### AccessDenied When Applying Policy

- Your IAM user lacks `s3:PutBucketPolicy` permission
- Contact AWS admin or use a user with appropriate permissions

### Mixed Content Warnings

- Ensure CloudFront uses HTTPS
- Update WordPress site URL to use HTTPS

---

## Security Considerations

### CloudFront OAI (Option A)
✅ **Pros**: Only CloudFront can access S3, blocks direct S3 access
✅ **Pros**: Better control over caching and security
✅ **Pros**: Can restrict by CloudFront signed URLs if needed later
⚠️ **Cons**: Slightly more complex setup

### Public Read (Option B)
✅ **Pros**: Simple to set up
✅ **Pros**: Works for both CloudFront and direct S3 URLs
⚠️ **Cons**: Anyone can access tours directly via S3 URLs
⚠️ **Cons**: Bypasses CloudFront caching and security features

---

## Next Steps

1. **Choose option** (A recommended for production)
2. **Apply bucket policy** following steps above
3. **Test tours** in local WordPress environment
4. **Monitor CloudFront logs** for successful requests
5. **Document** which option was chosen in deployment notes

---

**Related Documentation**:
- **Root Cause Analysis**: `docs/403-error-diagnosis.md`
- **S3 Architecture**: `docs/architecture/S3_ARCHITECTURE.md`
- **CloudFront Setup**: `docs/cloudfront-setup.md`
