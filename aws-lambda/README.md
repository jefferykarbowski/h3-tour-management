# AWS Lambda Tour Processing System

**Serverless tour extraction system that eliminates WordPress download and processing limitations.**

## 🎯 Problem Solved

The current H3 Tour Management plugin faces critical limitations:

- ❌ **WordPress Memory Limits**: Large ZIP files (>500MB) cause memory exhaustion
- ❌ **403 Download Errors**: WordPress can't download files from S3 due to permission issues
- ❌ **Processing Timeouts**: PHP execution limits prevent large file processing
- ❌ **Server Resource Consumption**: Processing blocks other site operations

## ✅ Lambda Solution Benefits

- **🚀 No Memory Limits**: Lambda handles up to 10GB RAM for large files
- **🔒 Full S3 Access**: Lambda has native S3 permissions, no download restrictions
- **⏱️ Extended Processing**: 15-minute timeout allows complex extractions
- **🔄 Automatic Processing**: S3 events trigger processing without manual intervention
- **💰 Cost Effective**: Pay only when processing tours, not continuous server costs
- **📊 Built-in Monitoring**: CloudWatch logs and metrics included

## 🏗️ Architecture Overview

```
Browser → S3 Upload → S3 Event → Lambda Function → Extract & Upload → S3 Tours → WordPress Webhook
```

### Processing Flow

1. **S3 Upload**: User uploads ZIP file to `s3://bucket/uploads/TourName.zip`
2. **Event Trigger**: S3 automatically invokes Lambda function
3. **Nested Extraction**: Lambda handles `TourName.zip → TourName/Web.zip → Web/` structure
4. **Tour Publishing**: Extracted files uploaded to `s3://bucket/tours/TourName/`
5. **WordPress Notification**: Webhook notifies WordPress of completion/failure

## 🚀 Quick Start

### Prerequisites

- AWS CLI configured with appropriate credentials
- Terraform installed (>= 1.0)
- Node.js (>= 18.x)
- Existing S3 bucket for tour files

### 1. Configure Variables

```bash
cd aws-lambda/terraform
cp terraform.tfvars.example terraform.tfvars
# Edit terraform.tfvars with your values
```

Required variables:
```hcl
bucket_name = "h3-tour-files-h3vt"
webhook_url = "https://yoursite.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook"
```

### 2. Deploy Infrastructure

```bash
cd aws-lambda/deployment
chmod +x deploy.sh
./deploy.sh deploy
```

### 3. WordPress Configuration

The deployment automatically configures the webhook handler in WordPress. Access **H3 Tour Management > S3 Settings** to:

- View webhook URL and secret
- Test webhook functionality
- Monitor processing statistics

## 📁 Project Structure

```
aws-lambda/
├── lambda-tour-processor/     # Lambda function code
│   ├── index.js              # Main Lambda handler
│   ├── package.json          # Dependencies
│   └── tests/                # Unit tests
├── terraform/                # Infrastructure as Code
│   ├── main.tf               # Main Terraform configuration
│   ├── variables.tf          # Variable definitions
│   └── terraform.tfvars.example
├── deployment/               # Deployment automation
│   └── deploy.sh            # Deployment script
└── README.md                # This file
```

## 🔧 Configuration Options

### Environment Variables

The Lambda function is configured via Terraform variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `bucket_name` | S3 bucket for tour files | Required |
| `webhook_url` | WordPress webhook endpoint | Required |
| `lambda_memory_size` | Memory allocation (MB) | 1024 |
| `lambda_timeout` | Function timeout (seconds) | 900 |
| `max_file_size` | Maximum ZIP file size | 1GB |
| `alert_email` | Email for error alerts | Optional |

### WordPress Settings

Configure in **WordPress Admin > H3 Tour Management > S3 Settings**:

- **Lambda Processing**: Enable/disable Lambda processing
- **Webhook Security**: Regenerate webhook secrets
- **Notifications**: Configure admin email alerts
- **Monitoring**: View processing statistics

## 🔍 Monitoring & Debugging

### CloudWatch Logs

All Lambda execution logs are available in CloudWatch:

```bash
aws logs tail /aws/lambda/h3-tour-processor-dev --follow
```

### Processing Statistics

View statistics in WordPress admin:
- Total tours processed
- Success/failure rates
- Average processing times
- Error summaries

### Common Issues

**Lambda timeout errors:**
- Increase `lambda_timeout` in terraform.tfvars
- Increase `lambda_memory_size` for faster processing

**Webhook failures:**
- Verify webhook URL is accessible
- Check WordPress error logs
- Regenerate webhook secret if needed

## 🧪 Testing

### Test Lambda Function

```bash
cd lambda-tour-processor
npm test
```

### Test Full Pipeline

1. Upload test ZIP to `s3://your-bucket/uploads/test-tour.zip`
2. Monitor CloudWatch logs for processing
3. Check `s3://your-bucket/tours/test-tour/` for extracted files
4. Verify WordPress receives webhook notification

### Webhook Testing

Use WordPress admin to test webhook endpoint:
1. Go to **H3 Tour Management > S3 Settings**
2. Click **Test Webhook**
3. Check results in admin interface

## 📊 Performance Specifications

| Metric | Specification | Notes |
|--------|---------------|--------|
| **Max File Size** | 1GB (configurable to 5GB) | Limited by Lambda tmp space |
| **Processing Time** | 15 minutes maximum | AWS Lambda limit |
| **Memory Usage** | 1GB default (up to 10GB) | Configurable via Terraform |
| **Concurrent Processing** | 1000 simultaneous | AWS account limits |
| **Supported Formats** | ZIP with nested ZIP | Handles TourName/Web.zip structure |

## 🔒 Security Features

- **Webhook Authentication**: HMAC-SHA256 signature verification
- **IAM Least Privilege**: Lambda has minimal required S3 permissions
- **VPC Support**: Optional VPC deployment for network isolation
- **Encryption**: All S3 operations use server-side encryption
- **Input Validation**: Comprehensive payload and file validation

## 💰 Cost Optimization

Typical processing costs:
- **Lambda Execution**: ~$0.02 per tour (500MB file)
- **S3 Operations**: ~$0.001 per tour
- **CloudWatch Logs**: ~$0.005 per GB of logs
- **SNS Notifications**: ~$0.001 per error alert

Monthly costs for 100 tours: **~$3-5**

## 🔄 Deployment Commands

### Full Deployment
```bash
./deploy.sh deploy
```

### Package Only
```bash
./deploy.sh package
```

### Rollback
```bash
./deploy.sh rollback
```

### Verify Deployment
```bash
./deploy.sh verify
```

## 🆘 Troubleshooting

### Common Deployment Issues

**Terraform authentication errors:**
```bash
aws sts get-caller-identity  # Verify AWS credentials
```

**S3 bucket not found:**
- Verify bucket exists and name is correct in terraform.tfvars
- Check AWS region matches bucket region

**Lambda packaging failures:**
```bash
cd lambda-tour-processor
npm install  # Reinstall dependencies
```

### Runtime Issues

**Processing failures:**
1. Check CloudWatch logs: `/aws/lambda/h3-tour-processor-{env}`
2. Verify S3 permissions for uploads/ and tours/ prefixes
3. Check file format (must be ZIP)

**Webhook not receiving notifications:**
1. Test webhook URL manually
2. Check WordPress error logs
3. Verify webhook secret configuration

## 📞 Support

For issues with this Lambda implementation:

1. **Check CloudWatch Logs** - Most issues are logged with detailed error messages
2. **Review Terraform State** - Use `terraform show` to verify infrastructure
3. **Test Components Individually** - Use provided testing tools
4. **Monitor AWS Costs** - Set up billing alerts for unexpected charges

## 🔗 Related Documentation

- [H3 Tour Management Plugin](../README.md)
- [S3 Configuration Guide](../docs/s3-implementation-guide.md)
- [WordPress Integration](../includes/class-h3tm-lambda-webhook.php)
- [AWS Lambda Best Practices](https://docs.aws.amazon.com/lambda/latest/dg/best-practices.html)

---

**This serverless solution completely eliminates WordPress processing limitations while providing enterprise-grade scalability and monitoring.**