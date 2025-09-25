# H3 Tour Management Lambda Deployment Guide

**Complete step-by-step guide to deploy the serverless tour processing system.**

## 📋 Prerequisites

### Required Software
- **AWS CLI** (v2.x recommended) - [Install Guide](https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html)
- **Terraform** (≥1.0) - [Install Guide](https://developer.hashicorp.com/terraform/downloads)
- **Node.js** (≥18.x) - [Install Guide](https://nodejs.org/)
- **Git** - For accessing the repository

### AWS Account Requirements
- AWS account with appropriate permissions
- S3 bucket for tour files (can be existing bucket from WordPress integration)
- IAM permissions to create Lambda functions, IAM roles, SNS topics, CloudWatch resources

## 🔧 Step 1: AWS Credential Configuration

### Configure AWS CLI

```bash
aws configure
```

Provide:
- **AWS Access Key ID**: Your access key
- **AWS Secret Access Key**: Your secret key
- **Default region**: e.g., `us-west-2`
- **Default output format**: `json`

### Verify AWS Configuration

```bash
aws sts get-caller-identity
```

Should return your account ID and user ARN.

## 📁 Step 2: Project Setup

### Clone and Navigate to Lambda Directory

```bash
cd h3-tour-management/aws-lambda
```

### Install Lambda Dependencies

```bash
cd lambda-tour-processor
npm install
cd ..
```

## ⚙️ Step 3: Configuration

### Create Terraform Variables File

```bash
cd terraform
cp terraform.tfvars.example terraform.tfvars
```

### Edit terraform.tfvars

**Required Variables:**

```hcl
# Your existing S3 bucket name
bucket_name = "h3-tour-files-h3vt"

# WordPress webhook URL (replace with your domain)
webhook_url = "https://yourdomain.com/wp-admin/admin-ajax.php?action=h3tm_lambda_webhook"

# Environment (dev, staging, prod)
environment = "prod"

# AWS region (should match your bucket region)
aws_region = "us-west-2"
```

**Optional Variables:**

```hcl
# Email for error notifications
alert_email = "admin@yourdomain.com"

# Lambda configuration
lambda_memory_size = 1024  # MB
lambda_timeout     = 900   # seconds (15 min max)

# File size limits
max_file_size = 1073741824  # 1GB in bytes

# Monitoring
enable_monitoring           = true
error_threshold            = 0
duration_threshold_seconds = 600
log_retention_days        = 14
```

### Validate Configuration

Ensure your S3 bucket exists and is accessible:

```bash
aws s3 ls s3://h3-tour-files-h3vt/
```

## 🚀 Step 4: Deployment

### Automated Deployment (Recommended)

```bash
cd ../deployment
chmod +x deploy.sh
./deploy.sh deploy
```

This script will:
1. ✅ Check prerequisites
2. ✅ Validate configuration
3. ✅ Install Lambda dependencies
4. ✅ Package Lambda function
5. ✅ Deploy infrastructure with Terraform
6. ✅ Verify deployment
7. ✅ Show deployment summary

### Manual Deployment (Advanced)

If you prefer manual control:

```bash
# 1. Package Lambda function
cd lambda-tour-processor
npm install --production
cd ..
zip -r h3-tour-processor.zip lambda-tour-processor/ -x "*.git*" "node_modules/.cache/*" "coverage/*" "*.log"

# 2. Deploy infrastructure
cd terraform
terraform init
terraform plan
terraform apply
```

## ✅ Step 5: Verification

### Automated Validation

```bash
cd scripts
chmod +x validate-deployment.sh
./validate-deployment.sh
```

The validation script tests:
- AWS connectivity and permissions
- Lambda function deployment and configuration
- S3 bucket setup and notifications
- CloudWatch logging
- SNS error notifications
- Webhook endpoint accessibility
- End-to-end processing with test file

### Manual Verification

1. **Check Lambda Function:**
   ```bash
   aws lambda list-functions | grep h3-tour-processor
   ```

2. **Test S3 Event Trigger:**
   ```bash
   echo "test" > test.zip
   aws s3 cp test.zip s3://h3-tour-files-h3vt/uploads/
   ```

3. **Monitor CloudWatch Logs:**
   ```bash
   aws logs tail /aws/lambda/h3-tour-processor-prod --follow
   ```

## 🔗 Step 6: WordPress Integration

The Lambda system automatically integrates with your WordPress installation through the webhook handler.

### WordPress Configuration

1. **Access WordPress Admin:**
   - Go to **H3 Tour Management > S3 Settings**

2. **Verify Webhook Configuration:**
   - Webhook URL should be displayed
   - Webhook secret should be generated
   - Test webhook functionality

3. **Enable Lambda Processing:**
   - Check "Enable Lambda Processing" if available
   - Save settings

### Test WordPress Integration

1. Upload a tour ZIP file through WordPress admin
2. File should be uploaded to S3 uploads/ directory
3. Lambda function processes automatically
4. WordPress receives completion webhook
5. Tour appears in WordPress admin

## 📊 Step 7: Monitoring and Maintenance

### CloudWatch Monitoring

**View Lambda Logs:**
```bash
aws logs tail /aws/lambda/h3-tour-processor-prod --follow
```

**Check Lambda Metrics:**
- Duration, Errors, Invocations, Throttles
- Available in AWS Console > CloudWatch > Metrics

**Set Up Alarms:**
- Error rate monitoring
- Duration threshold alerts
- Invocation count tracking

### Cost Monitoring

**Typical Monthly Costs (100 tours/month):**
- Lambda execution: ~$3
- S3 operations: ~$1
- CloudWatch logs: ~$1
- SNS notifications: <$1
- **Total: ~$5/month**

**Set Budget Alerts:**
```bash
aws budgets create-budget --account-id YOUR_ACCOUNT_ID --budget '{
  "BudgetName": "H3TourLambdaBudget",
  "BudgetLimit": {"Amount": "10", "Unit": "USD"},
  "TimeUnit": "MONTHLY",
  "BudgetType": "COST"
}'
```

### Maintenance Tasks

**Weekly:**
- Review CloudWatch logs for errors
- Check S3 bucket size and cleanup old files
- Monitor processing success rates

**Monthly:**
- Review AWS costs and optimize if needed
- Update Lambda function if new features available
- Check S3 lifecycle policies are working

## 🔧 Common Configuration Scenarios

### High-Volume Processing
For sites processing >100 tours/month:

```hcl
lambda_memory_size = 2048  # Faster processing
lambda_timeout     = 900   # Full 15 minutes
max_file_size     = 2147483648  # 2GB limit
```

### Development Environment
For testing and development:

```hcl
environment = "dev"
lambda_memory_size = 512   # Lower costs
log_retention_days = 7     # Shorter retention
enable_monitoring  = false # Disable alarms
```

### Multi-Region Setup
For global installations:

```hcl
aws_region = "eu-west-1"  # Europe
# or
aws_region = "ap-southeast-1"  # Asia Pacific
```

## 🆘 Troubleshooting

### Deployment Failures

**Terraform Permission Errors:**
```bash
# Check your AWS permissions
aws iam get-user
aws iam list-attached-user-policies --user-name YOUR_USERNAME
```

**Lambda Package Too Large:**
```bash
# Check package size
ls -lh h3-tour-processor.zip
# Should be <50MB
```

**S3 Bucket Not Found:**
```bash
# Verify bucket exists and region
aws s3api head-bucket --bucket h3-tour-files-h3vt
aws s3api get-bucket-location --bucket h3-tour-files-h3vt
```

### Runtime Issues

**Lambda Timeout Errors:**
- Increase `lambda_timeout` in terraform.tfvars
- Increase `lambda_memory_size` for faster processing

**S3 Permission Errors:**
- Verify IAM policy includes required S3 actions
- Check bucket policy allows Lambda role access

**Webhook Not Receiving Notifications:**
- Test webhook URL manually: `curl -X POST your-webhook-url`
- Check WordPress error logs
- Verify webhook secret configuration

### Performance Issues

**Slow Processing:**
- Increase Lambda memory allocation
- Check if files are very large or complex
- Review CloudWatch metrics for bottlenecks

**High Costs:**
- Review Lambda duration and memory usage
- Implement S3 lifecycle policies
- Optimize file sizes before upload

## 🔄 Updates and Rollbacks

### Update Lambda Function

```bash
cd deployment
./deploy.sh package  # Package new version
terraform apply      # Deploy update
```

### Rollback Deployment

```bash
cd deployment
./deploy.sh rollback
```

### Update Configuration Only

```bash
cd terraform
# Edit terraform.tfvars
terraform apply
```

## 📞 Support and Next Steps

### After Successful Deployment

1. **Upload Test Tour:**
   - Upload a sample tour ZIP to test processing
   - Monitor logs for successful extraction
   - Verify files appear in tours/ directory

2. **Configure WordPress:**
   - Update WordPress S3 settings
   - Test upload workflow end-to-end
   - Train users on new system

3. **Set Up Monitoring:**
   - Configure email alerts for errors
   - Set up cost budgets and alerts
   - Create operational runbooks

### Getting Help

1. **Check CloudWatch Logs** - Most issues are logged with details
2. **Run Validation Script** - Identifies common problems
3. **Review Terraform State** - Use `terraform show` for infrastructure details
4. **Test Components Individually** - Isolate issues to specific components

---

**🎉 Congratulations! Your serverless tour processing system is now deployed and ready to eliminate WordPress processing limitations.**