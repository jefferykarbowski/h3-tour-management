# AWS S3 Configuration Files

This directory contains all the configuration files referenced in the AWS S3 Infrastructure Setup Guide.

## Files Overview

| File | Purpose | Usage |
|------|---------|-------|
| `bucket-policy.json` | S3 bucket policy for public read access | Applied via AWS Console or CLI |
| `cors-config.json` | CORS configuration for browser uploads | Applied via S3 bucket permissions |
| `lifecycle-policy.json` | Automatic cleanup and archiving rules | Applied via S3 bucket management |
| `iam-policy.json` | IAM permissions for upload user | Attached to IAM user |
| `cloudwatch-alarms.json` | Monitoring and alerting configuration | CloudWatch alarm setup |
| `setup-commands.sh` | Complete automated setup script | Run after AWS CLI configuration |

## Quick Setup

1. **Install AWS CLI**
   ```bash
   # macOS
   brew install awscli

   # Windows
   pip install awscli

   # Linux
   sudo apt-get install awscli
   ```

2. **Configure AWS CLI**
   ```bash
   aws configure
   # Enter your AWS Access Key ID
   # Enter your AWS Secret Access Key
   # Default region: us-east-1
   # Default output format: json
   ```

3. **Run Setup Script**
   ```bash
   chmod +x setup-commands.sh
   ./setup-commands.sh
   ```

## Manual Configuration

If you prefer to configure manually, use each JSON file with the corresponding AWS CLI commands:

### Bucket Policy
```bash
aws s3api put-bucket-policy \
  --bucket h3-tour-files-h3vt \
  --policy file://bucket-policy.json
```

### CORS Configuration
```bash
aws s3api put-bucket-cors \
  --bucket h3-tour-files-h3vt \
  --cors-configuration file://cors-config.json
```

### Lifecycle Policy
```bash
aws s3api put-bucket-lifecycle-configuration \
  --bucket h3-tour-files-h3vt \
  --lifecycle-configuration file://lifecycle-policy.json
```

### IAM Policy
```bash
aws iam create-policy \
  --policy-name H3TourUploaderPolicy \
  --policy-document file://iam-policy.json
```

### CloudWatch Alarm
```bash
aws cloudwatch put-metric-alarm \
  --cli-input-json file://cloudwatch-alarms.json
```

## Customization

Before running the setup:

1. **Update bucket name** in all JSON files (replace `h3-tour-files-h3vt`)
2. **Update domains** in `cors-config.json`
3. **Adjust lifecycle rules** in `lifecycle-policy.json` based on your needs
4. **Modify alarm thresholds** in `cloudwatch-alarms.json`

## Verification

After setup, verify the configuration:

```bash
# Test bucket access
aws s3 ls s3://h3-tour-files-h3vt/

# Test upload
echo "test" > test.txt
aws s3 cp test.txt s3://h3-tour-files-h3vt/tours/test/
rm test.txt

# Verify public access
curl -I https://h3-tour-files-h3vt.s3.amazonaws.com/tours/test/test.txt
```

## Security Notes

- All configurations follow AWS security best practices
- IAM permissions are minimal and scoped to necessary actions only
- Bucket policy allows public read only for tour files
- CORS is configured for specific domains only
- Server-side encryption is enabled by default

## Cost Management

The lifecycle policy automatically:
- Deletes temporary files after 7 days
- Moves older files to cheaper storage classes
- Cleans up incomplete multipart uploads

Expected monthly costs:
- **Small business (50GB)**: $1-3/month
- **Growing business (200GB)**: $5-10/month
- **Large operation (1TB)**: $20-40/month

## Support

For issues with these configurations:
1. Check AWS CloudTrail logs for API errors
2. Verify IAM permissions in AWS Console
3. Test CORS with browser developer tools
4. Monitor CloudWatch metrics for unusual activity