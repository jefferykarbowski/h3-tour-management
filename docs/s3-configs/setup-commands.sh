#!/bin/bash

# AWS S3 Infrastructure Setup Script for H3 Tour Management
# Run this script after installing AWS CLI and configuring credentials

set -e

# Configuration variables
BUCKET_NAME="h3-tour-files-h3vt"
REGION="us-east-1"
IAM_USER="h3-tour-uploader"
IAM_POLICY="H3TourUploaderPolicy"
SNS_TOPIC="h3-s3-alerts"

echo "🚀 Starting AWS S3 infrastructure setup for H3 Tour Management..."

# 1. Create S3 bucket
echo "📦 Creating S3 bucket: $BUCKET_NAME"
aws s3api create-bucket \
  --bucket "$BUCKET_NAME" \
  --region "$REGION" \
  --create-bucket-configuration LocationConstraint="$REGION" 2>/dev/null || echo "Bucket already exists"

# 2. Apply bucket policy
echo "🔒 Applying bucket policy..."
aws s3api put-bucket-policy \
  --bucket "$BUCKET_NAME" \
  --policy file://bucket-policy.json

# 3. Apply CORS configuration
echo "🌐 Applying CORS configuration..."
aws s3api put-bucket-cors \
  --bucket "$BUCKET_NAME" \
  --cors-configuration file://cors-config.json

# 4. Apply lifecycle policy
echo "♻️ Applying lifecycle policy..."
aws s3api put-bucket-lifecycle-configuration \
  --bucket "$BUCKET_NAME" \
  --lifecycle-configuration file://lifecycle-policy.json

# 5. Enable server-side encryption
echo "🔐 Enabling server-side encryption..."
aws s3api put-bucket-encryption \
  --bucket "$BUCKET_NAME" \
  --server-side-encryption-configuration '{
    "Rules": [
      {
        "ApplyServerSideEncryptionByDefault": {
          "SSEAlgorithm": "AES256"
        }
      }
    ]
  }'

# 6. Create IAM policy
echo "👤 Creating IAM policy: $IAM_POLICY"
POLICY_ARN=$(aws iam create-policy \
  --policy-name "$IAM_POLICY" \
  --policy-document file://iam-policy.json \
  --query 'Policy.Arn' \
  --output text 2>/dev/null || \
  aws iam list-policies --query "Policies[?PolicyName=='$IAM_POLICY'].Arn" --output text)

# 7. Create IAM user
echo "👤 Creating IAM user: $IAM_USER"
aws iam create-user --user-name "$IAM_USER" 2>/dev/null || echo "User already exists"

# 8. Attach policy to user
echo "🔗 Attaching policy to user..."
aws iam attach-user-policy \
  --user-name "$IAM_USER" \
  --policy-arn "$POLICY_ARN"

# 9. Create access keys
echo "🔑 Creating access keys..."
ACCESS_KEYS=$(aws iam create-access-key --user-name "$IAM_USER" --output json 2>/dev/null || echo "Keys may already exist")

if [ "$ACCESS_KEYS" != "Keys may already exist" ]; then
  echo "📝 Access Key ID: $(echo "$ACCESS_KEYS" | jq -r '.AccessKey.AccessKeyId')"
  echo "🔐 Secret Access Key: $(echo "$ACCESS_KEYS" | jq -r '.AccessKey.SecretAccessKey')"
  echo ""
  echo "⚠️  IMPORTANT: Save these keys securely and add them to your WordPress configuration:"
  echo "   - AWS_ACCESS_KEY_ID: $(echo "$ACCESS_KEYS" | jq -r '.AccessKey.AccessKeyId')"
  echo "   - AWS_SECRET_ACCESS_KEY: $(echo "$ACCESS_KEYS" | jq -r '.AccessKey.SecretAccessKey')"
fi

# 10. Create SNS topic for alerts
echo "📧 Creating SNS topic for alerts..."
SNS_TOPIC_ARN=$(aws sns create-topic --name "$SNS_TOPIC" --query 'TopicArn' --output text)
echo "📧 SNS Topic ARN: $SNS_TOPIC_ARN"

# 11. Create folder structure
echo "📁 Creating folder structure..."
aws s3api put-object --bucket "$BUCKET_NAME" --key "tours/" --content-length 0
aws s3api put-object --bucket "$BUCKET_NAME" --key "temp-uploads/" --content-length 0
aws s3api put-object --bucket "$BUCKET_NAME" --key "logs/" --content-length 0

# 12. Test upload
echo "🧪 Testing upload functionality..."
echo "Test file for H3 Tour Management S3 setup" > test-upload.txt
aws s3 cp test-upload.txt "s3://$BUCKET_NAME/tours/test/test-upload.txt" --acl public-read
rm test-upload.txt

# 13. Verify public access
echo "🔍 Verifying public access..."
curl -I "https://$BUCKET_NAME.s3.amazonaws.com/tours/test/test-upload.txt" && echo "✅ Public access working"

# 14. Setup CloudWatch alarm (optional)
if command -v jq &> /dev/null; then
  echo "📊 Creating CloudWatch alarm..."
  aws cloudwatch put-metric-alarm --cli-input-json file://cloudwatch-alarms.json
fi

echo ""
echo "🎉 AWS S3 infrastructure setup complete!"
echo ""
echo "📋 Next steps:"
echo "1. Add AWS credentials to your WordPress wp-config.php:"
echo "   define('AWS_ACCESS_KEY_ID', 'your-access-key-id');"
echo "   define('AWS_SECRET_ACCESS_KEY', 'your-secret-access-key');"
echo "   define('AWS_DEFAULT_REGION', '$REGION');"
echo "   define('H3_S3_BUCKET', '$BUCKET_NAME');"
echo ""
echo "2. Subscribe to SNS alerts:"
echo "   aws sns subscribe --topic-arn '$SNS_TOPIC_ARN' --protocol email --notification-endpoint your-email@domain.com"
echo ""
echo "3. Test the integration with your WordPress plugin"
echo ""
echo "💰 Estimated monthly cost for 100GB: $3-5"
echo "📊 Monitor costs in AWS Cost Explorer"