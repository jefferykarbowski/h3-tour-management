#!/bin/bash
# Deploy H3 Tour Management Lambda
# This script packages and deploys the Lambda function with migration support

set -e  # Exit on error

echo "🚀 H3 Tour Management Lambda Deployment"
echo "========================================"

# Check if we're in the lambda directory
if [ ! -f "index.js" ]; then
    echo "❌ Error: Must run from lambda/ directory"
    exit 1
fi

# Install dependencies
echo "📦 Installing dependencies..."
npm install

# Create deployment package
echo "📦 Creating deployment package..."
if [ -f "function.zip" ]; then
    rm function.zip
fi

zip -r function.zip index.js migrate-tours.js node_modules/ > /dev/null
echo "✅ Created function.zip ($(du -h function.zip | cut -f1))"

# Check if AWS CLI is available
if ! command -v aws &> /dev/null; then
    echo "⚠️  AWS CLI not found. Please deploy manually:"
    echo "   aws lambda update-function-code --function-name h3tm-tour-processor --zip-file fileb://function.zip"
    exit 0
fi

# Check if function name is provided
FUNCTION_NAME="${1:-h3tm-tour-processor}"

echo "🚀 Deploying to Lambda function: $FUNCTION_NAME"

# Deploy to Lambda
aws lambda update-function-code \
    --function-name "$FUNCTION_NAME" \
    --zip-file fileb://function.zip \
    --no-cli-pager

if [ $? -eq 0 ]; then
    echo "✅ Lambda deployed successfully!"
    echo ""
    echo "📋 Next steps:"
    echo "   1. Verify environment variables are set:"
    echo "      - BUCKET_NAME"
    echo "      - WORDPRESS_WEBHOOK_URL"
    echo "      - WORDPRESS_SITE"
    echo "      - DB_CLUSTER_ARN (for migration)"
    echo "      - DB_SECRET_ARN (for migration)"
    echo "      - DB_NAME (for migration)"
    echo ""
    echo "   2. Test tour upload:"
    echo "      aws s3 cp test.zip s3://BUCKET/uploads/test-id/test-id.zip"
    echo ""
    echo "   3. Run migration via WordPress:"
    echo "      H3 Tours → Tour Migration → Execute Migration"
else
    echo "❌ Deployment failed"
    exit 1
fi
