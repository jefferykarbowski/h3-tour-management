#!/bin/bash
# Quick S3 403 diagnostic script

BUCKET="h3-tour-files-h3vt"
REGION="us-east-1"

echo "=== S3 Tour 403 Diagnostic ==="
echo ""

echo "1. Checking bucket exists and is accessible..."
aws s3 ls s3://$BUCKET/ 2>&1 | head -5

echo ""
echo "2. Listing tours directory..."
aws s3 ls s3://$BUCKET/tours/ 2>&1 | head -10

echo ""
echo "3. Checking for specific tour (20251016_050212_enj0cply)..."
aws s3 ls s3://$BUCKET/tours/20251016_050212_enj0cply/ 2>&1

echo ""
echo "4. Checking bucket policy..."
aws s3api get-bucket-policy --bucket $BUCKET 2>&1 | head -20

echo ""
echo "5. Checking bucket public access block..."
aws s3api get-public-access-block --bucket $BUCKET 2>&1

echo ""
echo "6. Testing direct S3 URL (should return 200 or 403)..."
curl -I "https://$BUCKET.s3.$REGION.amazonaws.com/tours/20251016_050212_enj0cply/index.htm" 2>&1 | head -10
