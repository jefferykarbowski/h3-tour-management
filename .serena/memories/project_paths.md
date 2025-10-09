# H3 Tour Management Project Paths

## Local WordPress Site
- **Site Root**: C:/Users/Jeff/Local Sites/h3vt/app/public/
- **Plugin Location**: Symbolic link from wp-content/plugins/h3-tour-management to GitHub directory
- **GitHub Directory**: C:/Users/Jeff/Documents/GitHub/h3-tour-management/
- **h3panos Directory**: C:/Users/Jeff/Local Sites/h3vt/app/public/wp-content/uploads/h3panos/

## AWS S3 Configuration
- **Bucket**: h3-tour-files-h3vt
- **Region**: us-east-1
- **Tours Path**: s3://h3-tour-files-h3vt/tours/
- **Known Tours in S3**: Bee-Cave, Onion Creek, Sugar-Land

## Lambda Function
- **Purpose**: Processes uploaded tours, injects analytics
- **Location**: lambda/index.js in GitHub directory

## Analytics
- **Endpoint**: /h3-tour-analytics.js
- **GA4 ID**: G-6P29YLK8Q9 (configured in WordPress)