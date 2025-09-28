# CloudFront CDN Setup Guide for H3 Tour Management

## Version 2.3.0 Features
The H3 Tour Management plugin now includes full CloudFront CDN integration for faster global tour delivery and reduced S3 costs.

## Benefits of CloudFront Integration
- **60-80% faster** tour loading through edge caching
- **Lower S3 costs** - CloudFront bandwidth is cheaper than S3 direct access
- **Global performance** - 400+ edge locations worldwide
- **Automatic compression** - Gzip/Brotli compression enabled by default
- **HTTPS included** - CloudFront SSL certificate at no extra cost
- **Smart caching** - Different cache times for HTML (1 hour) vs assets (7 days)

## AWS CloudFront Setup Steps

### 1. Create CloudFront Distribution

1. Log into AWS Console → CloudFront
2. Click "Create Distribution"
3. Choose "Web" distribution type

### 2. Configure Origin Settings

**Origin Domain**: Select your S3 bucket (e.g., `h3-tour-files-h3vt.s3.us-east-1.amazonaws.com`)

**Origin Path**: Leave empty

**Origin Access**:
- Choose "Origin access control settings (recommended)"
- Create new OAC if needed
- CloudFront will provide a bucket policy to add

**Additional Settings**:
- Enable Origin Shield: No (unless you have high traffic)
- Connection attempts: 3
- Connection timeout: 10 seconds

### 3. Configure Default Cache Behavior

**Viewer Protocol Policy**: Redirect HTTP to HTTPS

**Allowed HTTP Methods**: GET, HEAD (tours are read-only)

**Cache Policy**:
- Create custom policy or use "Managed-CachingOptimized"
- Set Default TTL: 3600 (1 hour)
- Maximum TTL: 86400 (24 hours)

**Compress Objects Automatically**: Yes

### 4. Configure Distribution Settings

**Price Class**:
- Use all edge locations (best performance)
- OR Use only US, Canada, Europe (lower cost)

**Alternate Domain Names (CNAMEs)**: Optional - add custom domain if desired

**SSL Certificate**: Default CloudFront certificate

**Supported HTTP Versions**: HTTP/2, HTTP/1.1, HTTP/1.0

**Standard Logging**: Optional but recommended

### 5. Update S3 Bucket Policy

CloudFront will provide a bucket policy. Add it to your S3 bucket:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "AllowCloudFrontServicePrincipalReadOnly",
            "Effect": "Allow",
            "Principal": {
                "Service": "cloudfront.amazonaws.com"
            },
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::h3-tour-files-h3vt/*",
            "Condition": {
                "StringEquals": {
                    "AWS:SourceArn": "arn:aws:cloudfront::YOUR_ACCOUNT_ID:distribution/YOUR_DISTRIBUTION_ID"
                }
            }
        }
    ]
}
```

### 6. Wait for Deployment
- Distribution deployment takes 15-20 minutes
- Status will change from "In Progress" to "Deployed"

## Plugin Configuration

### 1. Access Plugin Settings
WordPress Admin → H3 Tours → S3 Settings → CloudFront CDN Settings

### 2. Enter CloudFront Details

**Enable CloudFront**: Check this box to activate CDN

**CloudFront Domain**: Enter your distribution domain
- Example: `d1234abcd.cloudfront.net`
- Do NOT include `https://`

**Distribution ID**: Enter for cache invalidation support
- Example: `E1234ABCD5678`
- Found in CloudFront console
- Optional but recommended

### 3. Save Settings
Click "Save Changes" to activate CloudFront integration

## Testing CloudFront Integration

### 1. Check Debug Logs
```php
// Enable WordPress debug logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Look for logs like:
```
H3TM CDN Helper: CloudFront=enabled, Domain=d1234abcd.cloudfront.net
H3TM S3 Proxy: Using CDN helper, trying URLs: https://d1234abcd.cloudfront.net/tours/...
```

### 2. Verify Tour URLs
- Navigate to a tour: `/h3panos/Cedar Park/`
- Check browser DevTools → Network tab
- URLs should show CloudFront domain, not S3

### 3. Check Response Headers
Look for CloudFront headers:
- `x-cache: Hit from cloudfront` (cached)
- `x-cache: Miss from cloudfront` (first request)
- `via: 1.1 abc123.cloudfront.net`

## Cache Invalidation

When updating tours, you may need to invalidate CloudFront cache:

### Manual Invalidation (AWS Console)
1. CloudFront → Distributions → Your distribution
2. Invalidations tab → Create invalidation
3. Add paths: `/tours/Tour-Name/*`

### Automatic Invalidation (Coming Soon)
The plugin includes hooks for automatic invalidation:
```php
do_action('h3tm_cloudfront_invalidate', $distribution_id, $paths);
```

## Performance Optimization

### Cache Times by File Type
The plugin automatically sets optimal cache times:
- HTML/HTM: 1 hour (dynamic content)
- CSS/JS: 24 hours (versioned assets)
- Images: 7 days (static content)
- Videos: 7 days (large static files)

### CloudFront Behaviors
For maximum performance, create additional behaviors:

1. **Path Pattern**: `/tours/*/media/*`
   - Cache: 7 days
   - Compress: Yes

2. **Path Pattern**: `/tours/*.htm`
   - Cache: 1 hour
   - Compress: Yes

## Troubleshooting

### Tours Not Loading
1. Check CloudFront distribution status (must be "Deployed")
2. Verify S3 bucket policy includes CloudFront access
3. Check WordPress debug logs for errors
4. Ensure CloudFront domain is entered correctly (no https://)

### 403 Forbidden Errors
- Update S3 bucket policy with CloudFront OAC
- Verify Origin Access Control is configured
- Check S3 object permissions

### Slow Performance
- Check cache hit ratio in CloudFront metrics
- Verify compression is enabled
- Consider using Origin Shield for high traffic

### Cache Not Updating
- Create invalidation for specific paths
- Wait 10-15 minutes for invalidation to complete
- Check TTL settings in cache behavior

## Cost Considerations

### CloudFront Pricing
- Data transfer OUT: ~$0.085/GB (varies by region)
- HTTP/HTTPS requests: ~$0.01 per 10,000 requests
- Invalidation: First 1,000 paths/month free

### Compared to S3 Direct
- S3 data transfer: ~$0.09/GB
- S3 requests: ~$0.0004 per GET request
- CloudFront typically 20-40% cheaper for high traffic

### Cost Optimization Tips
1. Use appropriate cache TTLs to reduce origin requests
2. Enable compression to reduce bandwidth
3. Consider regional edge caches for localized traffic
4. Monitor AWS Cost Explorer for usage patterns

## Security Best Practices

1. **Use Origin Access Control (OAC)** - Prevents direct S3 access
2. **Enable AWS WAF** - Optional web application firewall
3. **Configure Security Headers** - Add via CloudFront functions
4. **Monitor Access Logs** - Enable standard logging
5. **Use HTTPS Only** - Redirect all HTTP to HTTPS

## Support

For issues or questions:
1. Check WordPress debug logs: `/wp-content/debug.log`
2. Review CloudFront metrics in AWS Console
3. Verify all settings match this guide
4. Contact support with distribution ID and error messages