# Lambda Deployment Instructions

## Overview

The H3 Tour Management system uses AWS Lambda for:
1. **Tour Processing** (`index.js`) - Handles ZIP uploads and extraction
2. **Tour Migration** (`migrate-tours.js`) - One-time migration to ID-based system

## Deployment Steps

### 1. Install Dependencies

```bash
cd lambda
npm install
```

### 2. Create Deployment Package

```bash
# Create ZIP with all required files
zip -r function.zip index.js migrate-tours.js node_modules/
```

### 3. Deploy to AWS Lambda

#### Option A: Update Existing Function

```bash
aws lambda update-function-code \
  --function-name h3tm-tour-processor \
  --zip-file fileb://function.zip
```

#### Option B: Create New Function (if needed)

```bash
aws lambda create-function \
  --function-name h3tm-tour-processor \
  --runtime nodejs18.x \
  --handler index.handler \
  --zip-file fileb://function.zip \
  --role arn:aws:iam::YOUR_ACCOUNT:role/lambda-execution-role \
  --environment Variables="{
    BUCKET_NAME=your-bucket-name,
    WORDPRESS_WEBHOOK_URL=https://your-site.com/wp-json/h3tm/v1/webhook,
    WORDPRESS_SITE=https://your-site.com,
    DB_CLUSTER_ARN=arn:aws:rds:region:account:cluster:your-cluster,
    DB_SECRET_ARN=arn:aws:secretsmanager:region:account:secret:your-secret,
    DB_NAME=h3tm
  }" \
  --timeout 300 \
  --memory-size 512
```

### 4. Update Environment Variables

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
  }"
```

## Environment Variables

| Variable | Description | Required For |
|----------|-------------|--------------|
| `BUCKET_NAME` | S3 bucket name | Both |
| `WORDPRESS_WEBHOOK_URL` | WordPress webhook endpoint | Tour Processing |
| `WORDPRESS_SITE` | WordPress site URL | Tour Processing |
| `DB_CLUSTER_ARN` | RDS cluster ARN | Migration |
| `DB_SECRET_ARN` | Secrets Manager ARN | Migration |
| `DB_NAME` | Database name | Migration |

## Lambda Handler Routing

The Lambda function routes requests based on the event structure:

### Tour Processing (S3 Event)
```json
{
  "Records": [{
    "eventName": "ObjectCreated:Put",
    "s3": {
      "bucket": { "name": "h3-panos" },
      "object": { "key": "uploads/tour-id/tour-id.zip" }
    }
  }]
}
```

### Tour Deletion (Direct Invocation)
```json
{
  "action": "delete_tour",
  "bucket": "h3-panos",
  "tourName": "Tour Name"
}
```

### Tour Migration (Function URL)
```json
{
  "action": "migrate_tours"
}
```

## Required IAM Permissions

The Lambda execution role needs:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
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
        "arn:aws:s3:::h3-panos/*"
      ]
    },
    {
      "Effect": "Allow",
      "Action": [
        "rds-data:ExecuteStatement",
        "rds-data:BatchExecuteStatement"
      ],
      "Resource": "arn:aws:rds:region:account:cluster:*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue"
      ],
      "Resource": "arn:aws:secretsmanager:region:account:secret:*"
    },
    {
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

## Testing

### Test Tour Processing
Upload a ZIP file to trigger the Lambda:
```bash
aws s3 cp test-tour.zip s3://h3-panos/uploads/test-id/test-id.zip
```

### Test Migration
Invoke via WordPress admin:
1. Go to **H3 Tours → Tour Migration**
2. Click **Execute Migration**

Or directly via Lambda:
```bash
aws lambda invoke \
  --function-name h3tm-tour-processor \
  --payload '{"action":"migrate_tours"}' \
  response.json
```

## Monitoring

View logs:
```bash
aws logs tail /aws/lambda/h3tm-tour-processor --follow
```

## Troubleshooting

### Common Issues

1. **Timeout**: Increase timeout to 300 seconds for large tours
2. **Memory**: Increase memory to 512MB or higher for ZIP processing
3. **Permissions**: Verify IAM role has all required permissions
4. **Database**: Ensure RDS Data API is enabled on cluster

### Debug Checklist

- [ ] Lambda has correct environment variables
- [ ] IAM role has all required permissions
- [ ] S3 bucket trigger is configured correctly
- [ ] RDS Data API is enabled
- [ ] Database table `wp_h3tm_tour_metadata` exists
- [ ] WordPress webhook URL is accessible from Lambda

## Quick Deploy Script

```bash
#!/bin/bash
# deploy-lambda.sh

cd lambda
npm install
zip -r function.zip index.js migrate-tours.js node_modules/
aws lambda update-function-code \
  --function-name h3tm-tour-processor \
  --zip-file fileb://function.zip
echo "Lambda deployed successfully!"
```

Make executable and run:
```bash
chmod +x deploy-lambda.sh
./deploy-lambda.sh
```
