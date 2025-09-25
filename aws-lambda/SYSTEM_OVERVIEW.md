# AWS Lambda Tour Processing System - Technical Overview

## 🎯 Problem & Solution

### Current WordPress Limitations
- **Memory Exhaustion**: Large ZIP files (>500MB) crash WordPress with memory limits
- **403 Download Errors**: WordPress can't download files from S3 due to permission issues
- **PHP Timeouts**: Processing large tours exceeds PHP execution time limits
- **Resource Blocking**: Heavy processing blocks other site operations

### Lambda Solution Architecture
- **Serverless Processing**: No memory limits (up to 10GB), 15-minute timeout
- **Native S3 Access**: Full S3 permissions eliminate download restrictions
- **Event-Driven**: Automatic processing triggered by S3 uploads
- **Cost Efficient**: Pay per execution (~$0.02/tour vs. continuous server costs)

## 🏗️ System Architecture

```
┌─────────────────┐    ┌──────────────┐    ┌─────────────────────┐    ┌──────────────┐
│   Browser       │    │      S3      │    │   Lambda Function   │    │  WordPress   │
│                 │    │              │    │                     │    │              │
│ 1. Upload ZIP ──┼───▶│ 2. Store in  │    │ 4. Process ZIP:     │    │ 7. Webhook   │
│    via WordPress│    │    uploads/  │    │    • Download       │    │    Handler   │
│                 │    │              │    │    • Extract nested │    │              │
│                 │    │ 3. Trigger   │    │    • Upload to      │    │ 8. Update    │
│                 │    │    Lambda ───┼───▶│      tours/         │───▶│    Database  │
│                 │    │              │    │    • Send webhook   │    │              │
│                 │    │ 5. Get       │    │                     │    │              │
│                 │    │    extracted │    │ 6. Move original    │    │              │
│                 │    │    files     │    │    to processed/   │    │              │
└─────────────────┘    └──────────────┘    └─────────────────────┘    └──────────────┘
```

## 📂 S3 Directory Structure

```
s3://h3-tour-files-h3vt/
├── uploads/           # Incoming ZIP files (triggers Lambda)
├── tours/            # Extracted tour files (public access)
├── temp/             # Temporary processing files
├── processed/        # Successfully processed original files
└── failed/          # Failed processing original files
```

## ⚙️ Lambda Function Architecture

### Core Processing Flow

1. **Event Parsing**: Validates S3 event, filters for uploads/*.zip files
2. **File Validation**: Checks file size, type, and accessibility
3. **Nested Extraction**: Handles TourName.zip → TourName/Web.zip → Web/ structure
4. **S3 Upload**: Places extracted files in tours/TourName/ directory
5. **File Management**: Moves originals to processed/ or failed/ directories
6. **Webhook Notification**: Sends completion/failure status to WordPress
7. **Error Handling**: SNS alerts for failures, detailed CloudWatch logging

### Key Features

- **Memory Efficient**: Streams large files without loading entirely into memory
- **Content Type Detection**: Sets proper MIME types for web files
- **Metadata Generation**: Creates tour-metadata.json for tracking
- **Retry Logic**: Automatic retries for transient failures
- **Security**: Input validation, signature verification for webhooks

## 🔒 Security Architecture

### IAM Permissions (Least Privilege)
```json
{
  "S3": ["GetObject", "PutObject", "DeleteObject", "CopyObject"] - Limited to specific prefixes,
  "CloudWatch": ["PutLogEvents", "CreateLogStream"] - Function logs only,
  "SNS": ["Publish"] - Error alerts only
}
```

### S3 Security
- **Bucket Policy**: Public read only for tours/, Lambda-only access for processing directories
- **Encryption**: Server-side encryption for all objects
- **HTTPS Only**: Deny non-SSL requests

### Webhook Security
- **HMAC-SHA256**: Signature verification using shared secret
- **Timestamp Validation**: Reject old requests (24-hour window)
- **Input Sanitization**: Validate all webhook payload fields

## 📊 Monitoring & Observability

### CloudWatch Integration
- **Function Logs**: Detailed processing logs with structured JSON
- **Metrics**: Duration, errors, invocations, concurrent executions
- **Alarms**: Error rate and duration thresholds with SNS notifications

### Performance Tracking
- **Processing Time**: Tracks extraction speed for optimization
- **File Count**: Monitors files extracted per tour
- **Memory Usage**: Tracks RAM consumption for sizing
- **Error Patterns**: Categorizes failures for debugging

### WordPress Dashboard
- **Processing Statistics**: Success/failure rates, average times
- **Webhook Monitoring**: Test connectivity, view recent activity
- **Configuration Status**: Deployment validation, secret management

## 🔧 Configuration Management

### Terraform Infrastructure as Code
```hcl
# Environment-specific configurations
variable "environment" { default = "prod" }
variable "lambda_memory_size" { default = 1024 }
variable "lambda_timeout" { default = 900 }
variable "max_file_size" { default = 1073741824 }

# Auto-scaling and monitoring
resource "aws_cloudwatch_metric_alarm" "lambda_errors"
resource "aws_lambda_function" "tour_processor"
resource "aws_s3_bucket_notification" "tour_upload_notification"
```

### WordPress Integration
```php
// Webhook handler automatically processes Lambda notifications
class H3TM_Lambda_Webhook {
    - Validates webhook signatures
    - Updates tour database records
    - Sends admin notifications
    - Provides admin UI for testing
}
```

## 🚀 Performance Specifications

| Metric | Specification | Notes |
|--------|---------------|--------|
| **Max File Size** | 5GB | Configurable, limited by Lambda /tmp space |
| **Processing Speed** | 1GB in ~2-3 minutes | Depends on nested structure complexity |
| **Memory Usage** | 512MB - 10GB | Auto-scales based on file size |
| **Timeout** | 15 minutes max | AWS Lambda hard limit |
| **Concurrency** | 1000 simultaneous | AWS account default limit |
| **Cold Start** | <5 seconds | Node.js runtime optimization |

## 💰 Cost Analysis

### Per-Tour Processing Cost (1GB file)
```
Lambda Execution: 120 seconds × 1024MB = $0.0167
S3 Operations: ~50 requests = $0.0002
CloudWatch Logs: ~1MB = $0.0005
SNS (if failure): 1 notification = $0.0001
Total: ~$0.0175 per tour
```

### Monthly Cost Examples
- **10 tours/month**: ~$0.18
- **100 tours/month**: ~$1.75
- **1000 tours/month**: ~$17.50

### Cost Optimization
- **Lifecycle Policies**: Auto-archive old processed files to Glacier
- **Log Retention**: 14-day default, configurable
- **Memory Optimization**: Right-size Lambda memory for best price/performance

## 🔄 Deployment Pipeline

### Automated Deployment
```bash
./deploy.sh deploy
```

**Pipeline Steps:**
1. **Prerequisites Check**: AWS CLI, Terraform, Node.js, permissions
2. **Configuration Validation**: Terraform vars, S3 bucket access
3. **Lambda Packaging**: npm install, zip creation with exclusions
4. **Infrastructure Deployment**: Terraform plan/apply with state management
5. **Deployment Verification**: Function test, S3 notifications, webhook test
6. **Summary Report**: URLs, ARNs, next steps

### Validation & Testing
```bash
./validate-deployment.sh
```

**Test Suite:**
- AWS connectivity and credentials
- Lambda function configuration and invocation
- S3 bucket setup and event notifications
- CloudWatch log group creation
- SNS topic configuration
- Webhook endpoint accessibility
- End-to-end processing with test file

## 🛠️ Maintenance & Operations

### Routine Maintenance
- **Weekly**: Review CloudWatch logs, check error rates
- **Monthly**: Analyze costs, optimize resource allocation
- **Quarterly**: Update dependencies, security patches

### Troubleshooting Guide
- **Processing Failures**: Check CloudWatch logs for specific error messages
- **Webhook Issues**: Verify WordPress accessibility, regenerate secrets
- **Performance Issues**: Monitor memory usage, consider increasing allocation
- **Cost Spikes**: Review S3 lifecycle policies, check for stuck processes

### Upgrade Path
- **Lambda Updates**: Package and deploy new function code
- **Infrastructure Changes**: Terraform plan/apply for resource updates
- **WordPress Integration**: Plugin updates through standard WP mechanisms

## 🔮 Future Enhancements

### Planned Features
- **Multi-Region Deployment**: Global processing for reduced latency
- **Enhanced Monitoring**: Custom metrics dashboard in WordPress
- **Batch Processing**: Handle multiple tours in single invocation
- **Advanced Retry Logic**: Exponential backoff for transient failures
- **Pre-processing Validation**: Scan for malware, validate tour structure

### Integration Opportunities
- **CDN Integration**: Automatic CloudFront distribution setup
- **Database Optimization**: Direct RDS integration for tour metadata
- **API Gateway**: RESTful API for external tour submission
- **Machine Learning**: Automatic tour categorization and optimization

---

**This serverless architecture eliminates all WordPress processing limitations while providing enterprise-grade scalability, monitoring, and cost efficiency.**