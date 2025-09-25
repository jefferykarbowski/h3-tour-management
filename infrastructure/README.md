# H3 Tour Management - AWS Lambda Infrastructure

Complete AWS Lambda infrastructure setup for automated tour ZIP extraction, replacing WordPress-based processing to eliminate download permission issues and server limitations.

## 🎯 Overview

This infrastructure automatically processes uploaded tour ZIP files using AWS Lambda, extracting contents to S3 and notifying WordPress of completion. It provides a scalable, serverless solution that eliminates WordPress processing constraints.

### Current Working Flow
```
1. Browser uploads ZIP to S3 uploads/ ✅ (working perfectly)
2. Lambda processes ZIP automatically ⚡ (this infrastructure)
3. Extracted tour files in S3 tours/ directory 📁
4. WordPress receives completion notification 🔔
```

## 📂 Infrastructure Components

```
infrastructure/
├── lambda/                      # Lambda function code
│   ├── tour_processor.py       # Main ZIP processing logic
│   ├── wordpress_integration.py # WordPress webhook integration
│   └── requirements.txt        # Python dependencies
├── cloudformation/             # CloudFormation templates
│   └── tour-processor-stack.yaml # Complete infrastructure stack
├── terraform/                 # Terraform configuration
│   ├── main.tf               # Main infrastructure resources
│   └── outputs.tf            # Output values
└── scripts/                   # Deployment and testing scripts
    ├── deploy.sh             # Full deployment script
    ├── test-deployment.sh    # Testing and validation
    └── quick-setup.sh        # Rapid deployment for immediate use
```

## ⚡ Quick Start (5 minutes)

For immediate deployment with minimal configuration:

```bash
# Navigate to scripts directory
cd infrastructure/scripts

# Quick setup (creates all resources automatically)
./quick-setup.sh

# Test the deployment
./test-deployment.sh
```

This creates all AWS resources with auto-generated names and provides integration URLs for WordPress.

## 🚀 Full Deployment Options

### Option 1: CloudFormation Deployment
```bash
cd infrastructure/scripts

# Deploy to production
./deploy.sh deploy-cf

# Deploy to staging
./deploy.sh -e staging deploy-cf

# Update Lambda code only
./deploy.sh update-lambda
```

### Option 2: Terraform Deployment
```bash
cd infrastructure/scripts

# Deploy infrastructure
./deploy.sh deploy-tf

# Custom configuration
./deploy.sh -e prod -r us-west-2 --uploads-bucket my-uploads deploy-tf
```

### Option 3: Manual AWS CLI Setup
See the `quick-setup.sh` script for step-by-step AWS CLI commands.

## 🔧 Configuration

### Environment Variables
```bash
# Deployment configuration
export ENVIRONMENT="prod"                    # dev, staging, prod
export AWS_REGION="us-east-1"               # AWS region
export UPLOADS_BUCKET="h3-tour-uploads"     # ZIP uploads bucket
export TOURS_BUCKET="h3-tour-files"         # Extracted files bucket

# WordPress integration
export WORDPRESS_WEBHOOK="https://yoursite.com/wp-json/h3/v1/tour-processed"
export WORDPRESS_API_KEY="your-api-key"     # Optional authentication
export WORDPRESS_SECRET="webhook-secret"    # Optional signature validation
```

### WordPress Configuration
Add to your `wp-config.php`:
```php
// AWS S3 Configuration
define('H3_AWS_UPLOADS_BUCKET', 'your-uploads-bucket');
define('H3_AWS_TOURS_BUCKET', 'your-tours-bucket');
define('H3_AWS_REGION', 'us-east-1');

// Optional: SNS notifications
define('H3_SNS_TOPIC_ARN', 'arn:aws:sns:region:account:topic-name');

// Optional: Webhook security
define('H3_WEBHOOK_SECRET', 'your-webhook-secret');
```

## 🏗️ AWS Resources Created

### Core Infrastructure
- **S3 Buckets**: `uploads/` (ZIP files) and `tours/` (extracted files)
- **Lambda Function**: ZIP processing with 1GB memory, 15-minute timeout
- **IAM Role**: Lambda execution role with S3 and SNS permissions
- **SNS Topic**: Notification system for WordPress integration

### Monitoring & Security
- **CloudWatch Log Groups**: Lambda execution logs (30-day retention)
- **CloudWatch Alarms**: Error detection and duration monitoring
- **S3 CORS**: Configured for browser uploads and tour access
- **S3 Event Triggers**: Automatic Lambda invocation on ZIP uploads

### Security Features
- Public access blocked on all S3 buckets
- IAM least-privilege access policies
- Optional webhook signature validation
- CloudWatch monitoring and alerting

## 🔄 Processing Workflow

1. **Upload Detection**: S3 event triggers Lambda when ZIP uploaded
2. **ZIP Extraction**: Lambda downloads, extracts, and uploads individual files
3. **File Organization**: Extracted files saved to `tours/{tour-name}/`
4. **Cleanup**: Original ZIP file deleted after successful extraction
5. **Notification**: WordPress receives webhook with tour details
6. **Logging**: All operations logged to CloudWatch for monitoring

## 🧪 Testing & Validation

### Automated Testing
```bash
# Run all tests
./test-deployment.sh

# Test specific components
./test-deployment.sh upload lambda-direct end-to-end

# Test in different environment
./test-deployment.sh -e staging
```

### Manual Testing
```bash
# Upload test file
aws s3 cp test-tour.zip s3://your-uploads-bucket/

# Monitor Lambda logs
aws logs tail /aws/lambda/h3-tour-processor-prod --follow

# Check extracted files
aws s3 ls s3://your-tours-bucket/tours/ --recursive
```

## 📊 Monitoring & Troubleshooting

### CloudWatch Dashboards
- **Lambda Metrics**: Invocations, errors, duration, memory usage
- **S3 Metrics**: Upload/download counts, storage usage
- **SNS Metrics**: Message delivery success/failure

### Common Issues & Solutions

**Lambda Timeout (15 minutes exceeded)**
```bash
# Check file size and processing time
aws logs filter-log-events --log-group-name /aws/lambda/function-name \
  --filter-pattern "Duration:"
```

**S3 Permission Errors**
```bash
# Verify IAM role permissions
aws iam get-role-policy --role-name h3-tour-processor-role-prod \
  --policy-name S3Access
```

**WordPress Webhook Failures**
```bash
# Check webhook URL and authentication
aws logs filter-log-events --log-group-name /aws/lambda/function-name \
  --filter-pattern "WordPress notification"
```

## 💡 Performance Optimization

### Lambda Configuration
- **Memory**: 1024MB (adjustable based on ZIP sizes)
- **Timeout**: 900 seconds (15 minutes)
- **Concurrent Executions**: AWS default limits apply
- **Runtime**: Python 3.9 with optimized dependencies

### S3 Optimization
- **Transfer Acceleration**: Enable for faster uploads
- **Multipart Upload**: Automatic for files >100MB
- **Storage Classes**: Standard for active files
- **Lifecycle Policies**: Optional archival of old ZIPs

## 🔐 Security Best Practices

### IAM Security
- Least-privilege access policies
- No hardcoded credentials
- Environment-specific roles
- Regular access reviews

### S3 Security
- Public access blocked
- Bucket policies for specific access patterns
- CORS configured for legitimate origins only
- Optional encryption at rest

### Network Security
- VPC configuration available (commented in templates)
- Security groups for restricted access
- Optional WAF for webhook endpoints

## 📈 Scaling Considerations

### Current Limits
- **Lambda**: 1000 concurrent executions (default)
- **S3**: No practical limits
- **File Size**: Up to 10GB per ZIP (Lambda /tmp limit)
- **Processing Time**: 15 minutes maximum per ZIP

### Scaling Options
- **Increase Lambda Memory**: For faster processing
- **SQS Integration**: For batch processing and retry logic
- **Step Functions**: For complex multi-stage workflows
- **ECS Tasks**: For very large files requiring more resources

## 🛠️ Customization

### Lambda Function Modifications
The Lambda function can be extended for:
- Custom file validation rules
- Image optimization and resizing
- Content transformation
- Virus scanning integration
- Database direct updates

### Workflow Enhancements
- **Preview Generation**: Create tour thumbnails
- **Content Validation**: Verify tour structure
- **Analytics Integration**: Track usage patterns
- **CDN Integration**: CloudFront distribution
- **Backup Strategy**: Cross-region replication

## 📚 Additional Resources

### AWS Documentation
- [Lambda Best Practices](https://docs.aws.amazon.com/lambda/latest/dg/best-practices.html)
- [S3 Event Notifications](https://docs.aws.amazon.com/AmazonS3/latest/userguide/EventNotifications.html)
- [CloudWatch Monitoring](https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/)

### WordPress Integration
- Custom endpoint handlers in `/wp-json/h3/v1/`
- Database integration for tour metadata
- Admin dashboard for monitoring
- User interface for tour management

## 🤝 Contributing

1. Test changes locally with `./test-deployment.sh`
2. Validate CloudFormation templates: `./deploy.sh validate`
3. Use environment-specific deployments for testing
4. Update documentation for new features
5. Monitor CloudWatch logs for any issues

---

**Production Ready**: This infrastructure is designed for production use with proper error handling, monitoring, and security configurations.

**Cost Effective**: Serverless architecture means you only pay for actual processing time and storage used.

**Scalable**: Automatically handles varying loads without manual intervention.