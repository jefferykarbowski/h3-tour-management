# Alternative URL Handling Solutions for H3 Tour Management

## Overview

When WordPress rewrite rules fail completely (404 errors), this plugin provides **4 robust alternative approaches** to serve S3 tour content through local `/h3panos/TourName/` URLs without depending on rewrite rules.

## Quick Start

1. **Navigate to Admin Panel**: WordPress Admin → 3D Tours → URL Handlers
2. **Check System Status**: View which handlers are available
3. **Test All Handlers**: Click "Test All Handlers" to verify functionality
4. **Select Best Handler**: Activate the most suitable handler for your environment

## Available Solutions

### 1. 404 Handler Approach ⭐ **RECOMMENDED**

**File**: `class-h3tm-404-handler.php`

**How it works**: Intercepts 404 errors for h3panos URLs and serves S3 content directly.

**Advantages**:
- ✅ No rewrite rules needed
- ✅ Works with any WordPress setup
- ✅ Handles complex URL patterns and special characters
- ✅ Most reliable across different hosting environments
- ✅ Built-in analytics injection

**Disadvantages**:
- ❌ Slightly higher overhead (processes after 404 detection)
- ❌ Relies on WordPress 404 system

**Best for**: Most WordPress installations, shared hosting, complex tour names

---

### 2. Direct PHP Handler Approach ⚡ **FASTEST**

**File**: `class-h3tm-direct-handler.php`

**How it works**: Creates standalone PHP file (`h3panos-direct.php`) in web root with .htaccess rules.

**Advantages**:
- ✅ Fastest performance - bypasses WordPress completely
- ✅ Works with .htaccess redirect rules
- ✅ Minimal server resources
- ✅ No WordPress dependencies for serving content

**Disadvantages**:
- ❌ Requires file system write access to web root
- ❌ Harder to maintain (standalone file)
- ❌ May not work with all hosting configurations

**Best for**: VPS/dedicated servers, performance-critical installations

---

### 3. WordPress Action Hook Approach 🔧 **NATIVE**

**File**: `class-h3tm-action-hook.php`

**How it works**: Uses WordPress `wp` and `parse_request` hooks to intercept requests early.

**Advantages**:
- ✅ Native WordPress integration
- ✅ Early request interception
- ✅ Good performance
- ✅ No external files needed

**Disadvantages**:
- ❌ May conflict with other plugins
- ❌ Depends on WordPress load order
- ❌ Hook execution timing may vary

**Best for**: Clean WordPress installations, minimal plugin conflicts

---

### 4. Custom Endpoint Approach 🌐 **API-FRIENDLY**

**File**: `class-h3tm-endpoint-handler.php`

**How it works**: Creates WordPress REST API endpoints and custom URL endpoints.

**Advantages**:
- ✅ REST API compatibility
- ✅ Clean URL structure with `/h3tours/` alternative
- ✅ API-friendly for integrations
- ✅ WordPress native endpoint system

**Disadvantages**:
- ❌ More complex setup
- ❌ Requires REST API support
- ❌ Additional endpoint overhead

**Best for**: API integrations, developers, REST-API heavy sites

## URL Patterns Supported

All handlers support these URL patterns:

```
# Directory requests (loads index.htm)
/h3panos/TourName/
/h3panos/Tour%20With%20Spaces/
/h3panos/Tour-With-Hyphens/

# File requests
/h3panos/TourName/file.js
/h3panos/TourName/images/photo.jpg
/h3panos/TourName/videos/tour.mp4

# Special characters and spaces are handled properly
/h3panos/Property%20at%20123%20Main%20St/
/h3panos/Client_Name_Property/
```

## Features Included

### ✅ Analytics Integration
- Automatic Google Analytics 4 injection for HTML files
- Tour-specific tracking with custom dimensions
- Page view and interaction tracking
- Configurable via WordPress admin

### ✅ Performance Optimization
- Appropriate cache headers for different file types
- Long-term caching for static assets (CSS, JS, images)
- Shorter cache for HTML with revalidation
- Timeout optimization based on file types

### ✅ Security Headers
- X-Frame-Options: SAMEORIGIN
- X-Content-Type-Options: nosniff
- Referrer-Policy: strict-origin-when-cross-origin
- CORS headers for API usage

### ✅ Error Handling
- User-friendly error pages
- Proper HTTP status codes (404, 502, 503)
- Tour existence validation
- S3 connectivity error handling

### ✅ Content Type Detection
Supports all common file types:
- **HTML/HTM**: `text/html; charset=UTF-8`
- **JavaScript**: `application/javascript; charset=UTF-8`
- **CSS**: `text/css; charset=UTF-8`
- **Images**: PNG, JPG, GIF, SVG, ICO
- **Videos**: MP4, MOV, AVI
- **Fonts**: WOFF, WOFF2, TTF
- **Documents**: PDF, ZIP
- **Data**: JSON, XML, TXT

## Configuration

### S3 Requirements
```php
// Required S3 configuration (wp-config.php or admin panel)
define('H3_S3_BUCKET', 'your-bucket-name');
define('H3_S3_REGION', 'us-east-1');
define('AWS_ACCESS_KEY_ID', 'your-access-key');
define('AWS_SECRET_ACCESS_KEY', 'your-secret-key');
```

### Analytics Configuration
```php
// Optional analytics integration
update_option('h3tm_analytics_enabled', true);
update_option('h3tm_ga_tracking_id', 'G-XXXXXXXXXX');
```

## Testing & Diagnostics

### Admin Panel Tests
1. Navigate to **3D Tours → URL Handlers**
2. Click **"Test All Handlers"**
3. Review results for each handler
4. Switch to best performing handler

### Manual Testing
```bash
# Test tour access
curl -I "https://yoursite.com/h3panos/Test-Tour/"

# Test file access
curl -I "https://yoursite.com/h3panos/Test-Tour/app.js"

# Check response headers
curl -v "https://yoursite.com/h3panos/Test-Tour/"
```

### Debug Information
Enable WordPress debug logging to see handler activity:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for handler messages:
```
H3TM 404 Handler: Intercepting request: /h3panos/Tour-Name/
H3TM Action Hook: Serving from S3: https://bucket.s3.region.amazonaws.com/tours/Tour-Name/index.htm
```

## Troubleshooting

### Handler Not Working
1. **Check S3 Configuration**: Verify bucket, region, and credentials
2. **Test S3 Connectivity**: Use "Test S3 Connection" in admin panel
3. **Check File Permissions**: Ensure WordPress can write to required directories
4. **Review Error Logs**: Check WordPress debug.log and server error logs

### Performance Issues
1. **Switch to Direct Handler**: Fastest performance, bypasses WordPress
2. **Enable Caching**: Use WordPress caching plugins
3. **Optimize S3 Settings**: Use CloudFront CDN for better performance

### 404 Errors Persist
1. **Try Different Handler**: Switch to Action Hook or Endpoint handler
2. **Clear Rewrite Rules**: Flush permalinks in WordPress admin
3. **Check .htaccess**: Ensure no conflicting rules

### Special Characters in Tour Names
All handlers properly support:
- Spaces: `Tour Name` → `Tour%20Name`
- Special characters: Automatically URL encoded/decoded
- International characters: UTF-8 support

## Implementation Details

### URL Manager (`class-h3tm-url-manager.php`)
Central coordinator that:
- Auto-selects best available handler
- Provides fallback mechanisms
- Manages handler switching
- Offers performance metrics
- Generates admin interface

### Handler Selection Priority
1. **404 Handler** (most reliable)
2. **Action Hook** (WordPress native)
3. **Endpoint Handler** (API-friendly)
4. **Direct Handler** (requires file access)

### Fallback System
If active handler fails, URL Manager automatically tries alternatives in priority order.

## Best Practices

### Production Deployment
1. **Test Thoroughly**: Use staging environment first
2. **Monitor Performance**: Check server response times
3. **Enable Caching**: Use appropriate WordPress caching
4. **Monitor Logs**: Watch for error patterns
5. **Backup Configuration**: Save handler settings

### Security Considerations
- S3 credentials should use minimal required permissions
- Enable HTTPS for all tour URLs
- Regular security updates for WordPress
- Monitor access logs for unusual patterns

### Performance Optimization
- Use CloudFront CDN with S3
- Enable gzip compression
- Optimize image sizes in tours
- Monitor cache hit rates

## Migration from Rewrite Rules

### From Existing Rewrite System
1. **Backup Current Setup**: Save existing rewrite rules
2. **Install Alternative Handlers**: Upload new handler files
3. **Test in Staging**: Verify functionality before production
4. **Switch Handlers**: Use admin panel to activate new handler
5. **Monitor Traffic**: Ensure no broken links

### Rollback Plan
If issues arise:
1. Switch back to previous handler via admin panel
2. Restore original rewrite rules if needed
3. Check error logs for specific issues
4. Contact support with detailed error information

## Support & Maintenance

### Regular Maintenance
- **Monthly**: Test handler functionality
- **Quarterly**: Review performance metrics
- **Semi-annually**: Update S3 credentials if needed
- **Annually**: Review and optimize configuration

### Support Resources
- WordPress error logs: `/wp-content/debug.log`
- Server error logs: Check hosting control panel
- Plugin documentation: `/docs/` directory
- GitHub issues: For bug reports and feature requests

---

## Summary

The alternative URL handling system provides robust, rewrite-rule-independent solutions for serving S3 tour content. The **404 Handler** approach is recommended for most installations due to its reliability and broad compatibility. Use the admin panel to test and select the best handler for your specific environment.

All handlers include comprehensive analytics integration, performance optimization, and security features while maintaining compatibility with tour names containing spaces and special characters.