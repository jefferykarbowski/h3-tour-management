# AWS CLI Commands for S3 Tour Verification

## Prerequisites
Make sure your AWS CLI is configured with the same credentials used in WordPress:
```bash
aws configure list
```

## 1. Verify S3 Bucket Access
Check if you can access the bucket:
```bash
aws s3 ls s3://h3-tour-files-h3vt/
```

## 2. List Tours in the S3 Bucket
List all folders under the tours/ prefix:
```bash
aws s3 ls s3://h3-tour-files-h3vt/tours/ --delimiter / --prefix tours/
```

Or get more detail:
```bash
aws s3api list-objects-v2 --bucket h3-tour-files-h3vt --prefix tours/ --delimiter /
```

## 3. Check Specific Tour Contents
Replace "Bee-Cave" with your tour name:
```bash
aws s3 ls s3://h3-tour-files-h3vt/tours/Bee-Cave/ --recursive
```

## 4. Verify CORS Configuration
Check the CORS configuration on your bucket:
```bash
aws s3api get-bucket-cors --bucket h3-tour-files-h3vt
```

If CORS is not set, you can set it with:
```bash
aws s3api put-bucket-cors --bucket h3-tour-files-h3vt --cors-configuration file://cors.json
```

Where cors.json contains:
```json
{
  "CORSRules": [
    {
      "AllowedHeaders": ["*"],
      "AllowedMethods": ["GET", "PUT", "POST", "DELETE", "HEAD"],
      "AllowedOrigins": ["*"],
      "ExposeHeaders": ["ETag"],
      "MaxAgeSeconds": 3000
    }
  ]
}
```

## 5. Test Public Access to a Tour
Test if a tour index.html is publicly accessible:
```bash
curl -I https://h3-tour-files-h3vt.s3.us-east-1.amazonaws.com/tours/Bee-Cave/index.html
```

## 6. Check Bucket Policy
View current bucket policy:
```bash
aws s3api get-bucket-policy --bucket h3-tour-files-h3vt --query Policy --output text | python -m json.tool
```

## 7. List All Upload Files
Check for any ZIP files in the uploads folder:
```bash
aws s3 ls s3://h3-tour-files-h3vt/uploads/
```

## 8. Test IAM Permissions
Verify your IAM user has the necessary permissions:
```bash
aws iam get-user
aws iam list-attached-user-policies --user-name $(aws iam get-user --query 'User.UserName' --output text)
```

## 9. Test Creating a Test File
Test if you can upload to the bucket:
```bash
echo "test" > test.txt
aws s3 cp test.txt s3://h3-tour-files-h3vt/test.txt
aws s3 rm s3://h3-tour-files-h3vt/test.txt
rm test.txt
```

## Debugging WordPress Integration

If the refresh button still shows errors, run these commands and share the output:

1. Basic bucket listing:
```bash
aws s3 ls s3://h3-tour-files-h3vt/tours/
```

2. Detailed API call with debug info:
```bash
aws s3api list-objects-v2 --bucket h3-tour-files-h3vt --prefix tours/ --delimiter / --debug 2>&1 | head -50
```

3. Check your AWS credentials:
```bash
aws sts get-caller-identity
```

This will show which AWS identity is being used.