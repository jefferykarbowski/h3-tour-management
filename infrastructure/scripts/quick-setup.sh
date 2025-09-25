#!/bin/bash

# H3 Tour Management - Quick AWS Setup Script
# Rapid deployment for immediate use

set -e

# Configuration
ENVIRONMENT="${ENVIRONMENT:-prod}"
AWS_REGION="${AWS_REGION:-us-east-1}"
WORDPRESS_WEBHOOK="${WORDPRESS_WEBHOOK:-https://your-site.com/wp-json/h3/v1/tour-processed}"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${BLUE}[SETUP]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[INFO]${NC} $1"
}

log "🚀 H3 Tour Management - Quick AWS Setup"
log "======================================"

# Check AWS CLI
if ! command -v aws &> /dev/null; then
    log "Installing AWS CLI..."
    curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
    unzip -q awscliv2.zip
    sudo ./aws/install
    rm -rf awscliv2.zip aws/
fi

# Check AWS credentials
if ! aws sts get-caller-identity &> /dev/null; then
    warn "AWS credentials not configured. Please run:"
    warn "  aws configure"
    warn "  # Enter your AWS Access Key ID, Secret, Region, and Output format"
    exit 1
fi

log "AWS Configuration:"
aws sts get-caller-identity --query 'Account' --output text | xargs -I {} echo "  Account: {}"
echo "  Region: $AWS_REGION"
echo "  Environment: $ENVIRONMENT"

# Quick deploy using AWS CLI commands (no CloudFormation/Terraform)
log "Creating S3 buckets..."

UPLOADS_BUCKET="h3-tour-uploads-${ENVIRONMENT}-$(date +%s | tail -c 6)"
TOURS_BUCKET="h3-tour-files-${ENVIRONMENT}-$(date +%s | tail -c 6)"

# Create S3 buckets
aws s3 mb "s3://$UPLOADS_BUCKET" --region "$AWS_REGION"
aws s3 mb "s3://$TOURS_BUCKET" --region "$AWS_REGION"

# Configure CORS for uploads bucket
cat > /tmp/cors-config.json << EOF
{
    "CORSRules": [
        {
            "AllowedHeaders": ["*"],
            "AllowedMethods": ["PUT", "POST"],
            "AllowedOrigins": ["*"],
            "MaxAgeSeconds": 3000
        }
    ]
}
EOF

aws s3api put-bucket-cors --bucket "$UPLOADS_BUCKET" --cors-configuration file:///tmp/cors-config.json

# Configure CORS for tours bucket
cat > /tmp/cors-config-tours.json << EOF
{
    "CORSRules": [
        {
            "AllowedHeaders": ["*"],
            "AllowedMethods": ["GET"],
            "AllowedOrigins": ["*"],
            "MaxAgeSeconds": 3000
        }
    ]
}
EOF

aws s3api put-bucket-cors --bucket "$TOURS_BUCKET" --cors-configuration file:///tmp/cors-config-tours.json

success "S3 buckets created:"
success "  Uploads: $UPLOADS_BUCKET"
success "  Tours: $TOURS_BUCKET"

# Create SNS topic
log "Creating SNS notification topic..."
SNS_TOPIC_ARN=$(aws sns create-topic --name "h3-tour-processing-${ENVIRONMENT}" --region "$AWS_REGION" --query 'TopicArn' --output text)
success "SNS topic created: $SNS_TOPIC_ARN"

# Create IAM role for Lambda
log "Creating IAM role for Lambda..."

# Trust policy
cat > /tmp/trust-policy.json << EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "Service": "lambda.amazonaws.com"
            },
            "Action": "sts:AssumeRole"
        }
    ]
}
EOF

ROLE_NAME="h3-tour-processor-role-${ENVIRONMENT}"
aws iam create-role --role-name "$ROLE_NAME" --assume-role-policy-document file:///tmp/trust-policy.json

# Attach basic execution policy
aws iam attach-role-policy --role-name "$ROLE_NAME" --policy-arn "arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole"

# Create custom policy
cat > /tmp/lambda-policy.json << EOF
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::$UPLOADS_BUCKET/*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:PutObjectAcl"
            ],
            "Resource": "arn:aws:s3:::$TOURS_BUCKET/*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::$UPLOADS_BUCKET",
                "arn:aws:s3:::$TOURS_BUCKET"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "sns:Publish"
            ],
            "Resource": "$SNS_TOPIC_ARN"
        }
    ]
}
EOF

aws iam put-role-policy --role-name "$ROLE_NAME" --policy-name "S3SNSAccess" --policy-document file:///tmp/lambda-policy.json

success "IAM role created: $ROLE_NAME"

# Wait for role to be available
log "Waiting for IAM role to propagate..."
sleep 10

# Package Lambda function
log "Packaging Lambda function..."

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LAMBDA_DIR="$(dirname "$SCRIPT_DIR")/lambda"

cd "$LAMBDA_DIR"
zip -r /tmp/tour-processor.zip . -x "__pycache__/*" "*.pyc"

success "Lambda function packaged"

# Create Lambda function
log "Creating Lambda function..."

ROLE_ARN="arn:aws:iam::$(aws sts get-caller-identity --query 'Account' --output text):role/$ROLE_NAME"
FUNCTION_NAME="h3-tour-processor-${ENVIRONMENT}"

aws lambda create-function \
    --function-name "$FUNCTION_NAME" \
    --runtime python3.9 \
    --role "$ROLE_ARN" \
    --handler tour_processor.lambda_handler \
    --zip-file fileb:///tmp/tour-processor.zip \
    --memory-size 1024 \
    --timeout 900 \
    --environment Variables="{
        UPLOADS_BUCKET=$UPLOADS_BUCKET,
        TOURS_BUCKET=$TOURS_BUCKET,
        NOTIFICATION_TOPIC_ARN=$SNS_TOPIC_ARN,
        WORDPRESS_WEBHOOK_URL=$WORDPRESS_WEBHOOK,
        ENVIRONMENT=$ENVIRONMENT
    }" \
    --region "$AWS_REGION"

success "Lambda function created: $FUNCTION_NAME"

# Add S3 trigger permission
log "Configuring S3 trigger..."

aws lambda add-permission \
    --function-name "$FUNCTION_NAME" \
    --principal s3.amazonaws.com \
    --action lambda:InvokeFunction \
    --statement-id s3-trigger \
    --source-arn "arn:aws:s3:::$UPLOADS_BUCKET" \
    --region "$AWS_REGION"

# Configure S3 event notification
cat > /tmp/notification-config.json << EOF
{
    "LambdaConfigurations": [
        {
            "Id": "tour-zip-processor",
            "LambdaFunctionArn": "arn:aws:lambda:$AWS_REGION:$(aws sts get-caller-identity --query 'Account' --output text):function:$FUNCTION_NAME",
            "Events": ["s3:ObjectCreated:*"],
            "Filter": {
                "Key": {
                    "FilterRules": [
                        {
                            "Name": "suffix",
                            "Value": ".zip"
                        }
                    ]
                }
            }
        }
    ]
}
EOF

aws s3api put-bucket-notification-configuration \
    --bucket "$UPLOADS_BUCKET" \
    --notification-configuration file:///tmp/notification-config.json

success "S3 trigger configured"

# Create CloudWatch log group
log "Creating CloudWatch log group..."
aws logs create-log-group --log-group-name "/aws/lambda/$FUNCTION_NAME" --region "$AWS_REGION" 2>/dev/null || true

# Cleanup temp files
rm -f /tmp/cors-config*.json /tmp/trust-policy.json /tmp/lambda-policy.json /tmp/notification-config.json /tmp/tour-processor.zip

success "🎉 Quick setup completed successfully!"

log ""
log "📋 Deployment Summary:"
log "=================================="
log "Environment: $ENVIRONMENT"
log "AWS Region: $AWS_REGION"
log ""
log "📦 Resources Created:"
log "  Uploads S3 Bucket: $UPLOADS_BUCKET"
log "  Tours S3 Bucket: $TOURS_BUCKET"
log "  Lambda Function: $FUNCTION_NAME"
log "  SNS Topic: $SNS_TOPIC_ARN"
log "  IAM Role: $ROLE_NAME"
log ""
log "🔗 Integration URLs:"
log "  Uploads Bucket URL: https://$UPLOADS_BUCKET.s3.$AWS_REGION.amazonaws.com"
log "  Tours Bucket URL: https://$TOURS_BUCKET.s3.$AWS_REGION.amazonaws.com"
log ""
log "⚙️ WordPress Configuration:"
log "Add these to your WordPress wp-config.php or environment:"
log ""
log "define('H3_AWS_UPLOADS_BUCKET', '$UPLOADS_BUCKET');"
log "define('H3_AWS_TOURS_BUCKET', '$TOURS_BUCKET');"
log "define('H3_AWS_REGION', '$AWS_REGION');"
log "define('H3_SNS_TOPIC_ARN', '$SNS_TOPIC_ARN');"
log ""
log "🧪 Test Your Setup:"
log "1. Upload a ZIP file to: s3://$UPLOADS_BUCKET/test.zip"
log "2. Check processing in CloudWatch logs: /aws/lambda/$FUNCTION_NAME"
log "3. Verify extracted files in: s3://$TOURS_BUCKET/tours/"
log ""
log "🔍 Monitoring:"
log "  CloudWatch Logs: https://console.aws.amazon.com/cloudwatch/home?region=$AWS_REGION#logsV2:log-groups/log-group/\$252Faws\$252Flambda\$252F$FUNCTION_NAME"
log "  Lambda Console: https://console.aws.amazon.com/lambda/home?region=$AWS_REGION#/functions/$FUNCTION_NAME"
log ""

# Save configuration to file
cat > deployment-config.env << EOF
# H3 Tour Management - Deployment Configuration
# Generated: $(date)

export AWS_REGION="$AWS_REGION"
export ENVIRONMENT="$ENVIRONMENT"
export H3_UPLOADS_BUCKET="$UPLOADS_BUCKET"
export H3_TOURS_BUCKET="$TOURS_BUCKET"
export H3_LAMBDA_FUNCTION="$FUNCTION_NAME"
export H3_SNS_TOPIC_ARN="$SNS_TOPIC_ARN"
export H3_IAM_ROLE="$ROLE_NAME"

# WordPress configuration
export WP_H3_AWS_UPLOADS_BUCKET="$UPLOADS_BUCKET"
export WP_H3_AWS_TOURS_BUCKET="$TOURS_BUCKET"
export WP_H3_AWS_REGION="$AWS_REGION"
export WP_H3_SNS_TOPIC_ARN="$SNS_TOPIC_ARN"
EOF

success "Configuration saved to: deployment-config.env"
success "Source this file to use these values: source deployment-config.env"

log ""
warn "🚨 Next Steps:"
warn "1. Update your WordPress site with the bucket names above"
warn "2. Configure AWS credentials in WordPress (IAM user with S3 access)"
warn "3. Test by uploading a tour ZIP file"
warn "4. Monitor CloudWatch logs for any issues"
warn ""
warn "Need help? Check the logs:"
warn "  aws logs tail /aws/lambda/$FUNCTION_NAME --follow"