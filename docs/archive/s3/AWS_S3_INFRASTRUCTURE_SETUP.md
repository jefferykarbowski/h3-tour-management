# AWS S3 Infrastructure Setup Guide for H3 Tour Management

## Overview

This guide provides step-by-step instructions for setting up AWS S3 infrastructure to handle large 3D tour file uploads (up to 1GB) for the H3 Tour Management WordPress plugin. This solution addresses Pantheon hosting disk space limitations by enabling direct browser uploads to S3.

## Table of Contents

1. [AWS Account Setup](#aws-account-setup)
2. [S3 Bucket Configuration](#s3-bucket-configuration)
3. [IAM User and Permissions](#iam-user-and-permissions)
4. [CORS Configuration](#cors-configuration)
5. [Bucket Lifecycle Policies](#bucket-lifecycle-policies)
6. [Security Best Practices](#security-best-practices)
7. [Cost Optimization](#cost-optimization)
8. [Monitoring and Alerting](#monitoring-and-alerting)
9. [WordPress Integration](#wordpress-integration)
10. [Testing and Validation](#testing-and-validation)

---

## 1. AWS Account Setup

### Prerequisites
- Valid email address for AWS account
- Credit card for billing (free tier available)
- Phone number for verification

### Steps

1. **Create AWS Account**
   - Go to [aws.amazon.com](https://aws.amazon.com)
   - Click "Create an AWS Account"
   - Follow the registration process

2. **Enable Multi-Factor Authentication (MFA)**
   - Navigate to IAM → Users → Your root user
   - Add MFA device for enhanced security

3. **Set Up Billing Alerts**
   - Go to Billing & Cost Management
   - Create billing alerts for $5, $25, $50 thresholds

---

## 2. S3 Bucket Configuration

### Recommended Settings

**Bucket Name**: `h3-tour-files-[your-domain]` (e.g., `h3-tour-files-h3vt`)
**Region**: `us-east-1` (N. Virginia) - lowest cost, best performance for most users

### Step-by-Step Setup

1. **Create S3 Bucket**
   ```bash
   # AWS CLI command (optional - can use console)
   aws s3api create-bucket \
     --bucket h3-tour-files-h3vt \
     --region us-east-1
   ```

2. **Configure Bucket via AWS Console**
   - Navigate to S3 → Create Bucket
   - **Bucket name**: `h3-tour-files-h3vt`
   - **Region**: US East (N. Virginia) us-east-1
   - **Object Ownership**: ACLs disabled (recommended)
   - **Block Public Access**: Keep all blocks enabled initially
   - **Bucket Versioning**: Disabled (to save costs)
   - **Tags**: Add relevant tags
     - Environment: Production
     - Project: H3TourManagement
     - Owner: H3Photography

3. **Configure Public Read Access for Tour Files**
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

### Folder Structure
```
h3-tour-files-h3vt/
├── tours/
│   ├── [tour-id]/
│   │   ├── panos/
│   │   ├── tiles/
│   │   └── config.xml
├── temp-uploads/
└── logs/
```

---

## 3. IAM User and Permissions

### Create Dedicated IAM User

1. **Navigate to IAM → Users → Add User**
   - **User name**: `h3-tour-uploader`
   - **Access type**: Programmatic access only
   - **No console access needed**

2. **Create Custom Policy**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ListBucketInTours",
      "Effect": "Allow",
      "Action": [
        "s3:ListBucket"
      ],
      "Resource": "arn:aws:s3:::h3-tour-files-h3vt",
      "Condition": {
        "StringLike": {
          "s3:prefix": [
            "tours/*",
            "temp-uploads/*"
          ]
        }
      }
    },
    {
      "Sid": "AllowTourFileOperations",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:PutObjectAcl"
      ],
      "Resource": [
        "arn:aws:s3:::h3-tour-files-h3vt/tours/*",
        "arn:aws:s3:::h3-tour-files-h3vt/temp-uploads/*"
      ]
    },
    {
      "Sid": "AllowMultipartUpload",
      "Effect": "Allow",
      "Action": [
        "s3:AbortMultipartUpload",
        "s3:ListMultipartUploadParts",
        "s3:ListBucketMultipartUploads"
      ],
      "Resource": [
        "arn:aws:s3:::h3-tour-files-h3vt",
        "arn:aws:s3:::h3-tour-files-h3vt/*"
      ]
    }
  ]
}
```

3. **Attach Policy to User**
   - Policy name: `H3TourUploaderPolicy`
   - Attach to `h3-tour-uploader` user

4. **Generate Access Keys**
   - Save Access Key ID and Secret Access Key securely
   - Never commit these to version control

---

## 4. CORS Configuration

### CORS Rules for Direct Browser Uploads

```json
[
  {
    "AllowedHeaders": ["*"],
    "AllowedMethods": ["GET", "PUT", "POST", "DELETE", "HEAD"],
    "AllowedOrigins": [
      "https://yourdomain.com",
      "https://dev-yourdomain.pantheonsite.io",
      "https://test-yourdomain.pantheonsite.io",
      "https://live-yourdomain.pantheonsite.io"
    ],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

### Apply CORS Configuration

1. **Via AWS Console**
   - S3 → Your Bucket → Permissions → CORS
   - Paste the above JSON

2. **Via AWS CLI**
   ```bash
   aws s3api put-bucket-cors \
     --bucket h3-tour-files-h3vt \
     --cors-configuration file://cors-config.json
   ```

---

## 5. Bucket Lifecycle Policies

### Automatic Cleanup Rules

```json
{
  "Rules": [
    {
      "ID": "TempUploadsCleanup",
      "Status": "Enabled",
      "Filter": {
        "Prefix": "temp-uploads/"
      },
      "Expiration": {
        "Days": 7
      },
      "AbortIncompleteMultipartUpload": {
        "DaysAfterInitiation": 1
      }
    },
    {
      "ID": "LogsCleanup",
      "Status": "Enabled",
      "Filter": {
        "Prefix": "logs/"
      },
      "Expiration": {
        "Days": 30
      }
    },
    {
      "ID": "IntelligentTieringForTours",
      "Status": "Enabled",
      "Filter": {
        "Prefix": "tours/"
      },
      "Transitions": [
        {
          "Days": 30,
          "StorageClass": "STANDARD_IA"
        },
        {
          "Days": 90,
          "StorageClass": "GLACIER"
        }
      ]
    }
  ]
}
```

### Apply Lifecycle Policy

```bash
aws s3api put-bucket-lifecycle-configuration \
  --bucket h3-tour-files-h3vt \
  --lifecycle-configuration file://lifecycle-policy.json
```

---

## 6. Security Best Practices

### Access Key Management

1. **Store Keys Securely**
   ```php
   // In wp-config.php (never in theme/plugin files)
   define('AWS_ACCESS_KEY_ID', 'your-access-key-id');
   define('AWS_SECRET_ACCESS_KEY', 'your-secret-access-key');
   define('AWS_DEFAULT_REGION', 'us-east-1');
   define('H3_S3_BUCKET', 'h3-tour-files-h3vt');
   ```

2. **Use Environment Variables in Pantheon**
   ```bash
   # Set via Pantheon dashboard or Terminus
   terminus env:set site.env AWS_ACCESS_KEY_ID "your-key"
   terminus env:set site.env AWS_SECRET_ACCESS_KEY "your-secret"
   ```

### Bucket Security

1. **Enable Server-Side Encryption**
   ```json
   {
     "Rules": [
       {
         "ApplyServerSideEncryptionByDefault": {
           "SSEAlgorithm": "AES256"
         }
       }
     ]
   }
   ```

2. **Enable Access Logging**
   - Create separate bucket for logs: `h3-tour-logs-h3vt`
   - Configure access logging in main bucket

3. **CloudTrail Integration**
   - Enable CloudTrail for API call logging
   - Monitor unusual access patterns

---

## 7. Cost Optimization

### Storage Classes Strategy

| Data Type | Storage Class | Cost Optimization |
|-----------|---------------|-------------------|
| Active Tours | Standard | Immediate access |
| Older Tours (30+ days) | Standard-IA | 50% cost reduction |
| Archive Tours (90+ days) | Glacier | 80% cost reduction |
| Temp Files | Standard | Auto-delete after 7 days |

### Estimated Monthly Costs (100GB storage)

```
Standard Storage (50GB):     $1.15/month
Standard-IA (30GB):         $0.75/month
Glacier (20GB):             $0.20/month
Data Transfer Out (10GB):    $0.90/month
Total Estimated:            ~$3.00/month
```

### Cost Monitoring

```json
{
  "BudgetName": "H3-S3-Budget",
  "BudgetLimit": {
    "Amount": "25.0",
    "Unit": "USD"
  },
  "TimeUnit": "MONTHLY",
  "BudgetType": "COST"
}
```

---

## 8. Monitoring and Alerting

### CloudWatch Metrics

1. **Key Metrics to Monitor**
   - BucketSizeBytes
   - BucketObjectCount
   - NumberOfObjects (by storage class)
   - AllRequests
   - 4xxErrors and 5xxErrors

2. **Create Alarms**

```json
{
  "AlarmName": "H3-S3-HighErrorRate",
  "ComparisonOperator": "GreaterThanThreshold",
  "EvaluationPeriods": 2,
  "MetricName": "4xxErrors",
  "Namespace": "AWS/S3",
  "Period": 300,
  "Statistic": "Sum",
  "Threshold": 10.0,
  "ActionsEnabled": true,
  "AlarmDescription": "High error rate on S3 bucket"
}
```

### SNS Notifications

1. **Create SNS Topic**
   ```bash
   aws sns create-topic --name h3-s3-alerts
   ```

2. **Subscribe Email**
   ```bash
   aws sns subscribe \
     --topic-arn arn:aws:sns:us-east-1:123456789012:h3-s3-alerts \
     --protocol email \
     --notification-endpoint your-email@domain.com
   ```

---

## 9. WordPress Integration

### Plugin Configuration

Add to your WordPress plugin (`includes/class-h3tm-s3-uploader.php`):

```php
<?php
class H3TM_S3_Uploader {

    private $s3_client;
    private $bucket_name;

    public function __construct() {
        $this->bucket_name = defined('H3_S3_BUCKET') ? H3_S3_BUCKET : 'h3-tour-files-h3vt';
        $this->init_s3_client();
    }

    private function init_s3_client() {
        require_once 'aws-sdk/aws-autoloader.php';

        $this->s3_client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => defined('AWS_DEFAULT_REGION') ? AWS_DEFAULT_REGION : 'us-east-1',
            'credentials' => [
                'key'    => defined('AWS_ACCESS_KEY_ID') ? AWS_ACCESS_KEY_ID : '',
                'secret' => defined('AWS_SECRET_ACCESS_KEY') ? AWS_SECRET_ACCESS_KEY : '',
            ]
        ]);
    }

    public function generate_presigned_upload_url($key, $content_type = 'application/octet-stream') {
        $cmd = $this->s3_client->getCommand('PutObject', [
            'Bucket' => $this->bucket_name,
            'Key'    => $key,
            'ContentType' => $content_type,
            'ACL'    => 'public-read'
        ]);

        $request = $this->s3_client->createPresignedRequest($cmd, '+1 hour');

        return (string) $request->getUri();
    }

    public function initiate_multipart_upload($key, $content_type = 'application/octet-stream') {
        $result = $this->s3_client->createMultipartUpload([
            'Bucket' => $this->bucket_name,
            'Key'    => $key,
            'ContentType' => $content_type,
            'ACL'    => 'public-read'
        ]);

        return $result['UploadId'];
    }

    public function complete_multipart_upload($key, $upload_id, $parts) {
        return $this->s3_client->completeMultipartUpload([
            'Bucket'   => $this->bucket_name,
            'Key'      => $key,
            'UploadId' => $upload_id,
            'MultipartUpload' => [
                'Parts' => $parts
            ]
        ]);
    }
}
```

### JavaScript Direct Upload

```javascript
// Frontend JavaScript for direct S3 upload
class H3TourUploader {

    constructor(options) {
        this.bucket = options.bucket;
        this.region = options.region;
        this.apiEndpoint = options.apiEndpoint;
    }

    async uploadLargeFile(file, tourId, onProgress) {
        const key = `tours/${tourId}/${file.name}`;
        const chunkSize = 5 * 1024 * 1024; // 5MB chunks

        if (file.size <= chunkSize) {
            return this.uploadSmallFile(file, key, onProgress);
        }

        return this.uploadMultipartFile(file, key, chunkSize, onProgress);
    }

    async uploadSmallFile(file, key, onProgress) {
        // Get presigned URL from WordPress
        const response = await fetch(`${this.apiEndpoint}/get-upload-url`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ key, contentType: file.type })
        });

        const { uploadUrl } = await response.json();

        // Upload directly to S3
        return fetch(uploadUrl, {
            method: 'PUT',
            body: file,
            headers: {
                'Content-Type': file.type
            }
        });
    }

    async uploadMultipartFile(file, key, chunkSize, onProgress) {
        // Implementation for multipart upload
        // ... detailed implementation
    }
}
```

---

## 10. Testing and Validation

### Test Checklist

- [ ] **Bucket Access Test**
  ```bash
  aws s3 ls s3://h3-tour-files-h3vt/
  ```

- [ ] **Upload Test**
  ```bash
  aws s3 cp test-file.jpg s3://h3-tour-files-h3vt/tours/test/
  ```

- [ ] **CORS Test**
  - Use browser developer tools
  - Verify OPTIONS requests succeed

- [ ] **Lifecycle Test**
  - Upload to temp-uploads/
  - Verify deletion after 7 days

- [ ] **Multipart Upload Test**
  - Test with files > 100MB
  - Verify cleanup of incomplete uploads

### Performance Benchmarks

Expected performance for 1GB file:
- **Standard Upload**: 10-15 minutes (single part)
- **Multipart Upload**: 3-5 minutes (20 parts, 5MB each)
- **Download Speed**: 50-100 Mbps (depending on location)

---

## Quick Start Commands

### 1. Create Infrastructure
```bash
# Create S3 bucket
aws s3api create-bucket --bucket h3-tour-files-h3vt --region us-east-1

# Apply bucket policy
aws s3api put-bucket-policy --bucket h3-tour-files-h3vt --policy file://bucket-policy.json

# Apply CORS configuration
aws s3api put-bucket-cors --bucket h3-tour-files-h3vt --cors-configuration file://cors-config.json

# Apply lifecycle policy
aws s3api put-bucket-lifecycle-configuration --bucket h3-tour-files-h3vt --lifecycle-configuration file://lifecycle-policy.json
```

### 2. Test Upload
```bash
# Test file upload
aws s3 cp test.jpg s3://h3-tour-files-h3vt/tours/test-tour/test.jpg --acl public-read

# Verify file is accessible
curl -I https://h3-tour-files-h3vt.s3.amazonaws.com/tours/test-tour/test.jpg
```

### 3. Monitor Costs
```bash
# Check current month costs
aws ce get-cost-and-usage \
  --time-period Start=2024-01-01,End=2024-01-31 \
  --granularity MONTHLY \
  --metrics BlendedCost \
  --group-by Type=DIMENSION,Key=SERVICE
```

---

## Support and Troubleshooting

### Common Issues

1. **CORS Errors**
   - Verify domain in CORS configuration
   - Check browser developer tools for preflight requests

2. **Access Denied**
   - Verify IAM permissions
   - Check bucket policy

3. **High Costs**
   - Review lifecycle policies
   - Check data transfer patterns
   - Verify temp file cleanup

### Contact Information

- AWS Support: [AWS Support Center](https://console.aws.amazon.com/support/)
- Plugin Support: Create issue in GitHub repository
- Emergency: Contact H3 Photography team

---

## Conclusion

This infrastructure setup provides a robust, scalable, and cost-effective solution for handling large 3D tour files. The configuration supports files up to 1GB, implements security best practices, and includes comprehensive monitoring.

Total estimated monthly cost for typical usage: **$3-10/month**

Key benefits:
- ✅ Bypasses Pantheon disk space limitations
- ✅ Direct browser uploads (no server processing)
- ✅ Automatic file cleanup and archiving
- ✅ Comprehensive monitoring and alerting
- ✅ Scalable to handle business growth
- ✅ Industry-standard security practices