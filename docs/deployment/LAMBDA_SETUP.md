# Lambda Deployment & Setup Guide
**Complete Guide for H3 Tour Management Lambda Functions**

**Last Updated**: 2025-10-16
**Lambda Runtime**: Node.js 18.x
**Function Name**: `h3tm-tour-processor`

---

## Table of Contents

1. [Overview](#overview)
2. [Lambda Functions](#lambda-functions)
3. [Prerequisites](#prerequisites)
4. [Deployment](#deployment)
5. [Configuration](#configuration)
6. [IAM Permissions](#iam-permissions)
7. [Testing](#testing)
8. [Monitoring](#monitoring)
9. [Troubleshooting](#troubleshooting)
10. [Known Issues](#known-issues)

---

## Overview

The H3 Tour Management system uses AWS Lambda for serverless tour processing and migration tasks. The Lambda function handles:

1. **Tour Processing** - Automatic ZIP extraction and S3 upload when new tours are uploaded
2. **Tour Migration** - One-time migration of legacy tours to ID-based system
3. **Tour Deletion** - Cleanup of tour files from S3

**Key Benefits:**
- Serverless architecture (no EC2 management)
- Automatic scaling
- Pay-per-use pricing
- Event-driven processing
- Integration with S3, RDS Data API, and WordPress

---

## Lambda Functions

### Primary Handler (`index.js`)

Routes requests based on event type:

| Event Type | Trigger | Handler Function | Purpose |
|------------|---------|------------------|---------|
| S3 Event | ObjectCreated | Tour Processing | Extract uploaded ZIP, process files |
| Direct Invocation | `{action: "delete_tour"}` | Tour Deletion | Delete tour from S3 |
| Direct Invocation | `{action: "migrate_tours"}` | Tour Migration | Migrate legacy tours to ID-based system |

### Migration Handler (`migrate-tours.js`)

Standalone migration function for ID-based tour system:
- Lists all tours from S3
- Checks database for existing tour_ids
- Generates UUIDs for legacy tours
- Inserts metadata entries in database
- Returns detailed migration report

---

## Prerequisites

### 1. AWS Account Setup

- ✅ AWS account with Lambda access
- ✅ S3 bucket created (e.g., `h3-panos` or `h3-tour-files-h3vt`)
- ✅ RDS Serverless Aurora MySQL cluster (optional, for migration)
- ✅ Secrets Manager secret with database credentials (optional, for migration)

### 2. IAM Permissions

Your AWS user needs:
- `lambda:CreateFunction`
- `lambda:UpdateFunctionCode`
- `lambda:UpdateFunctionConfiguration`
- `iam:PassRole` (to assign execution role to Lambda)

### 3. Local Development Tools

```bash
# Node.js 18+
node --version  # Should be v18.x or higher

# AWS CLI configured
aws --version
aws configure  # Set up credentials

# Development dependencies
cd lambda
npm install
```

---

## Deployment

### Quick Deploy (Recommended)

Use the provided deployment script:

```bash
cd lambda
chmod +x deploy.sh
./deploy.sh h3tm-tour-processor
```

The script will:
1. Install dependencies
2. Create deployment package
3. Deploy to Lambda
4. Show next steps

### Manual Deployment

#### Step 1: Install Dependencies

```bash
cd lambda
npm install
```

**Installed Packages:**
- `@aws-sdk/client-s3` - S3 operations
- `@aws-sdk/client-rds-data` - Database operations (migration)
- `jszip` - ZIP file extraction
- `cheerio` - HTML parsing for analytics injection

#### Step 2: Create Deployment Package

```bash
# Create ZIP with all required files
zip -r function.zip index.js migrate-tours.js node_modules/

# Or on Windows PowerShell:
Compress-Archive -Path index.js, migrate-tours.js, node_modules -DestinationPath function.zip -Force
```

**Package Contents:**
- `index.js` (main handler)
- `migrate-tours.js` (migration handler)
- `node_modules/` (dependencies)
- `package.json` (dependency list)

#### Step 3: Deploy to AWS

**Option A: Update Existing Function**
```bash
aws lambda update-function-code \
  --function-name h3tm-tour-processor \
  --zip-file fileb://function.zip \
  --region us-east-1
```

**Option B: Create New Function**
```bash
aws lambda create-function \
  --function-name h3tm-tour-processor \
  --runtime nodejs18.x \
  --handler index.handler \
  --zip-file fileb://function.zip \
  --role arn:aws:iam::ACCOUNT_ID:role/lambda-execution-role \
  --timeout 300 \
  --memory-size 512 \
  --region us-east-1
```

**Option C: AWS Console**
1. Navigate to [Lambda Console](https://console.aws.amazon.com/lambda/)
2. Select region: **us-east-1**
3. Click function: **h3tm-tour-processor**
4. Click **"Upload from"** → **".zip file"**
5. Choose `function.zip` (typically ~5-50MB depending on dependencies)
6. Click **"Save"**

#### Step 4: Verify Deployment

```bash
# Check function configuration
aws lambda get-function --function-name h3tm-tour-processor

# Test invoke
aws lambda invoke \
  --function-name h3tm-tour-processor \
  --payload '{"test": true}' \
  response.json

cat response.json
```

---

## Configuration

### Environment Variables

Set these in Lambda configuration:

| Variable | Description | Required For | Example |
|----------|-------------|--------------|---------|
| `BUCKET_NAME` | S3 bucket name | All operations | `h3-panos` |
| `WORDPRESS_WEBHOOK_URL` | WordPress webhook endpoint | Tour Processing | `https://h3vt.com/wp-json/h3tm/v1/webhook` |
| `WORDPRESS_SITE` | WordPress site URL | Tour Processing | `https://h3vt.com` |
| `DB_CLUSTER_ARN` | RDS cluster ARN | Migration only | `arn:aws:rds:us-east-1:123:cluster:h3tm` |
| `DB_SECRET_ARN` | Secrets Manager ARN | Migration only | `arn:aws:secretsmanager:us-east-1:123:secret:h3tm` |
| `DB_NAME` | Database name | Migration only | `h3tm` |

**Set via AWS CLI:**
```bash
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --environment Variables="{
    BUCKET_NAME=h3-panos,
    WORDPRESS_WEBHOOK_URL=https://h3vt.com/wp-json/h3tm/v1/webhook,
    WORDPRESS_SITE=https://h3vt.com,
    DB_CLUSTER_ARN=arn:aws:rds:us-east-1:ACCOUNT:cluster:h3tm-cluster,
    DB_SECRET_ARN=arn:aws:secretsmanager:us-east-1:ACCOUNT:secret:h3tm-db,
    DB_NAME=h3tm
  }" \
  --region us-east-1
```

**Set via AWS Console:**
1. Go to Lambda function
2. Click **Configuration** tab
3. Click **Environment variables** → **Edit**
4. Add each variable
5. Click **Save**

### Function Settings

**Recommended Settings:**
- **Timeout**: 300 seconds (5 minutes)
  - Needed for large tour ZIP files
  - Processing can take 2-4 minutes for 100+ file tours
- **Memory**: 512 MB minimum
  - 1024 MB recommended for large tours
  - Affects CPU allocation (more memory = more CPU)
- **Runtime**: Node.js 18.x
- **Handler**: `index.handler`

**Set via AWS CLI:**
```bash
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --timeout 300 \
  --memory-size 1024 \
  --region us-east-1
```

### S3 Trigger Configuration

Set up S3 to automatically trigger Lambda on uploads:

**Via AWS Console:**
1. Go to S3 bucket (e.g., `h3-panos`)
2. Click **Properties** tab
3. Scroll to **Event notifications**
4. Click **Create event notification**
5. Configure:
   - **Event name**: `TourUploadTrigger`
   - **Prefix**: `uploads/`
   - **Event types**: `PUT` (All object create events)
   - **Destination**: Lambda function `h3tm-tour-processor`
6. Save

**Via AWS CLI:**
```bash
aws s3api put-bucket-notification-configuration \
  --bucket h3-panos \
  --notification-configuration '{
    "LambdaFunctionConfigurations": [{
      "LambdaFunctionArn": "arn:aws:lambda:us-east-1:ACCOUNT:function:h3tm-tour-processor",
      "Events": ["s3:ObjectCreated:*"],
      "Filter": {
        "Key": {
          "FilterRules": [{
            "Name": "prefix",
            "Value": "uploads/"
          }]
        }
      }
    }]
  }'
```

---

## IAM Permissions

### Lambda Execution Role

Create or update the Lambda execution role with these permissions:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "S3Access",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:ListBucket",
        "s3:CopyObject"
      ],
      "Resource": [
        "arn:aws:s3:::h3-panos",
        "arn:aws:s3:::h3-panos/*",
        "arn:aws:s3:::h3-tour-files-h3vt",
        "arn:aws:s3:::h3-tour-files-h3vt/*"
      ]
    },
    {
      "Sid": "RDSDataAPI",
      "Effect": "Allow",
      "Action": [
        "rds-data:ExecuteStatement",
        "rds-data:BatchExecuteStatement"
      ],
      "Resource": "arn:aws:rds:*:*:cluster:*"
    },
    {
      "Sid": "SecretsManager",
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue"
      ],
      "Resource": "arn:aws:secretsmanager:*:*:secret:*"
    },
    {
      "Sid": "CloudWatchLogs",
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:CreateLogStream",
        "logs:PutLogEvents"
      ],
      "Resource": "arn:aws:logs:*:*:*"
    }
  ]
}
```

**Attach to Role:**
```bash
# Create policy
aws iam create-policy \
  --policy-name H3TM-Lambda-Policy \
  --policy-document file://lambda-policy.json

# Attach to role
aws iam attach-role-policy \
  --role-name lambda-execution-role \
  --policy-arn arn:aws:iam::ACCOUNT:policy/H3TM-Lambda-Policy
```

### S3 Bucket Policy

Ensure bucket allows public read for tour files:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowPublicReadTourFiles",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::h3-panos/tours/*"
    }
  ]
}
```

**⚠️ Important Note - ACLs Disabled:**

AWS best practice since 2018 is to disable object ACLs and use bucket policies instead.

**DO NOT** include `ACL: 'public-read'` in `PutObjectCommand` - this will cause:
```
AccessControlListNotSupported: The bucket does not allow ACLs
```

The Lambda function is already configured correctly:
```javascript
// ✅ CORRECT - No ACL parameter
await s3.send(new PutObjectCommand({
    Bucket: bucket,
    Key: s3Key,
    Body: fileData,
    ContentType: getContentType(file.path)
    // Bucket policy handles public access
}));

// ❌ WRONG - Will crash Lambda
await s3.send(new PutObjectCommand({
    Bucket: bucket,
    Key: s3Key,
    Body: fileData,
    ContentType: getContentType(file.path),
    ACL: 'public-read'  // DO NOT USE
}));
```

---

## Testing

### Test Tour Processing

#### Method 1: Upload via WordPress
1. Go to WordPress admin: H3 Tours → Upload Tour
2. Enter tour name: "Test Lambda Deploy"
3. Select any ZIP file
4. Click Upload
5. Monitor progress in admin interface
6. Check CloudWatch logs for processing details

#### Method 2: Direct S3 Upload
```bash
# Upload test ZIP
aws s3 cp test-tour.zip s3://h3-panos/uploads/test-20251016/test-20251016.zip

# Watch CloudWatch logs
aws logs tail /aws/lambda/h3tm-tour-processor --follow

# Check if tour was extracted
aws s3 ls s3://h3-panos/tours/test-20251016/
```

### Test Migration

#### Method 1: Via WordPress Admin
1. Go to **H3 Tours → Tour Migration**
2. Review migration status
3. Click **Execute Migration**
4. Verify results

#### Method 2: Direct Lambda Invocation
```bash
aws lambda invoke \
  --function-name h3tm-tour-processor \
  --payload '{"action":"migrate_tours"}' \
  response.json \
  --region us-east-1

cat response.json | jq '.'
```

### Test Deletion

```bash
aws lambda invoke \
  --function-name h3tm-tour-processor \
  --payload '{
    "action": "delete_tour",
    "bucket": "h3-panos",
    "tourName": "Test Tour"
  }' \
  response.json

cat response.json
```

---

## Monitoring

### CloudWatch Logs

**View logs in real-time:**
```bash
# Follow all logs
aws logs tail /aws/lambda/h3tm-tour-processor --follow

# Filter by time
aws logs tail /aws/lambda/h3tm-tour-processor --since 1h

# Search for errors
aws logs tail /aws/lambda/h3tm-tour-processor --filter-pattern "ERROR"
```

**Via AWS Console:**
1. Go to [CloudWatch Console](https://console.aws.amazon.com/cloudwatch/)
2. Click **Logs** → **Log groups**
3. Find `/aws/lambda/h3tm-tour-processor`
4. Click to view log streams
5. Click most recent stream to see logs

### Key Log Indicators

**Successful Processing:**
```
[H3 Lambda] Processing tour upload from: uploads/20251016_001234_abc12345/...
[H3 Lambda] Extracted 87 files from ZIP
[H3 Lambda] All 87 files uploaded successfully
[H3 Lambda] Tour processing completed successfully
```

**Migration Success:**
```
Migration Summary:
  Total tours: 50
  Migrated: 45
  Skipped: 5
  Errors: 0
```

**Errors to Watch:**
```
AccessControlListNotSupported  # ACL parameter issue
Timeout                        # Increase timeout/memory
Memory limit exceeded          # Increase memory allocation
Access Denied                  # IAM permissions issue
```

### CloudWatch Metrics

Monitor these metrics in AWS Console:
- **Invocations**: Number of times Lambda ran
- **Duration**: How long each invocation took
- **Errors**: Failed invocations
- **Throttles**: Rate-limited requests
- **Concurrent Executions**: How many ran simultaneously

---

## Troubleshooting

### Issue: Lambda Timeout

**Symptoms:**
- Upload shows "Processing..." forever
- CloudWatch shows "Task timed out after 300 seconds"
- Tour never appears as "completed"

**Solution:**
```bash
# Increase timeout to 5 minutes
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --timeout 300 \
  --region us-east-1
```

### Issue: Out of Memory

**Symptoms:**
- CloudWatch shows "Runtime.OutOfMemory"
- Large ZIP files fail to process

**Solution:**
```bash
# Increase memory to 1024 MB
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --memory-size 1024 \
  --region us-east-1
```

### Issue: Access Denied Errors

**Symptoms:**
- "Access Denied" in CloudWatch logs
- Cannot read from S3 or write to RDS

**Solution:**
1. Verify Lambda execution role has required permissions (see IAM section)
2. Check S3 bucket policy allows Lambda access
3. Verify RDS Data API is enabled
4. Check Secrets Manager permissions

### Issue: Database Connection Fails

**Symptoms:**
- Migration fails with database errors
- "Unable to connect to cluster" in logs

**Solution:**
```bash
# Verify RDS Data API is enabled
aws rds describe-db-clusters \
  --db-cluster-identifier h3tm-cluster \
  --query 'DBClusters[0].HttpEndpointEnabled'
# Should return: true

# Enable if needed
aws rds modify-db-cluster \
  --db-cluster-identifier h3tm-cluster \
  --enable-http-endpoint
```

### Issue: WordPress Webhook Not Called

**Symptoms:**
- Tour status stays "uploading"
- No webhook errors in CloudWatch
- Files extracted to S3 but WordPress not notified

**Solution:**
1. Verify `WORDPRESS_WEBHOOK_URL` environment variable
2. Check WordPress endpoint is accessible: `curl https://your-site.com/wp-json/h3tm/v1/webhook`
3. Verify webhook authentication (if required)
4. Check WordPress error logs

### Issue: S3 Trigger Not Working

**Symptoms:**
- Upload to S3 but Lambda never triggers
- No CloudWatch logs for recent uploads

**Solution:**
1. Verify S3 event notification is configured (see Configuration section)
2. Check Lambda has permission to be invoked by S3:
   ```bash
   aws lambda get-policy --function-name h3tm-tour-processor
   ```
3. Re-create S3 trigger if needed

---

## Known Issues

### ACL Parameter Issue (RESOLVED)

**Problem**: Lambda crashed with `AccessControlListNotSupported` error when bucket had ACLs disabled.

**Cause**: Code attempted to set `ACL: 'public-read'` on uploaded files.

**Resolution**: ACL parameter removed from all `PutObjectCommand` calls. Bucket policy handles public access.

**Verification**: Check `index.js` lines 100-106 - should NOT have ACL parameter.

### Large File Processing

**Issue**: Very large tours (500+ files, 500+ MB) may timeout or run out of memory.

**Workarounds**:
- Increase Lambda timeout to maximum (900 seconds / 15 minutes)
- Increase memory to maximum (10240 MB)
- Split large tours into smaller chunks
- Use multipart upload for very large ZIPs

### RDS Data API Latency

**Issue**: Database operations via RDS Data API can be slower than direct connections.

**Impact**: Migration may take 30-60 seconds for 100+ tours.

**Mitigation**: This is expected behavior. RDS Data API trades latency for serverless convenience.

---

## Deployment Checklist

### Pre-Deployment
- [ ] Backup current Lambda function (download existing code)
- [ ] Review changes in `index.js` and `migrate-tours.js`
- [ ] Test locally if possible
- [ ] Create test environment (staging Lambda)

### Deployment
- [ ] Install dependencies: `npm install`
- [ ] Create deployment package: `zip -r function.zip ...`
- [ ] Upload to Lambda (CLI, Console, or script)
- [ ] Verify environment variables are set
- [ ] Verify function timeout is 300 seconds
- [ ] Verify memory is at least 512 MB

### Post-Deployment
- [ ] Test tour upload end-to-end
- [ ] Check CloudWatch logs for errors
- [ ] Verify files appear in S3 `tours/` directory
- [ ] Test migration if deploying migration handler
- [ ] Monitor for 24 hours
- [ ] Update documentation if settings changed

---

## Quick Reference

### Common Commands

```bash
# Deploy Lambda
cd lambda && npm install && zip -r function.zip * && \
aws lambda update-function-code \
  --function-name h3tm-tour-processor \
  --zip-file fileb://function.zip

# View logs
aws logs tail /aws/lambda/h3tm-tour-processor --follow

# Test invoke
aws lambda invoke \
  --function-name h3tm-tour-processor \
  --payload '{"test": true}' \
  response.json

# Update timeout
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --timeout 300

# Update memory
aws lambda update-function-configuration \
  --function-name h3tm-tour-processor \
  --memory-size 1024
```

### Support Resources

- **AWS Lambda Documentation**: https://docs.aws.amazon.com/lambda/
- **CloudWatch Logs**: https://console.aws.amazon.com/cloudwatch/
- **Lambda Console**: https://console.aws.amazon.com/lambda/
- **Project Documentation**: See `docs/migration/` and `docs/architecture/`

---

**Last Updated**: 2025-10-16
**Maintained By**: H3 Tour Management Team
**Lambda Function**: `h3tm-tour-processor`
**Region**: us-east-1
