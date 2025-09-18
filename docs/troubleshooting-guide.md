# H3 Tour Management - Troubleshooting Guide

## Common Issues and Solutions

### Google Analytics API Issues

#### Invalid Grant Error
**Problem**: `invalid_grant` error when accessing Google Analytics API

**Solution**:
1. Check system clock synchronization
2. Regenerate service account credentials
3. Verify JSON key file format
4. Ensure proper OAuth2 scope configuration

#### SSL/TLS Issues on Localhost
**Problem**: SSL verification failures during development

**Solution**:
1. Disable SSL verification in development environment
2. Use proper SSL certificates for production
3. Configure `CURLOPT_SSL_VERIFYPEER` appropriately

### File Upload Errors
**Problem**: File upload failures with permission errors

**Solution**:
1. Check directory permissions (755 for directories, 644 for files)
2. Verify PHP upload limits (`upload_max_filesize`, `post_max_size`)
3. Ensure proper WordPress file handling

### Configuration Issues

#### Local Sites Configuration
**Problem**: Plugin not working in local development

**Solution**:
1. Configure proper local paths in `wp-config.php`
2. Set up local database connections
3. Adjust file permissions for local environment

#### PHP Index Disabled
**Problem**: PHP directory indexing security concerns

**Solution**:
- Add `Options -Indexes` to `.htaccess`
- Implement proper directory protection
- Use `index.php` files to prevent directory browsing

## Setup Requirements

### Google API Setup
1. Create Google Cloud Project
2. Enable Analytics Reporting API
3. Create Service Account with Analytics Read permissions
4. Download JSON credentials file
5. Configure credentials path in plugin settings

### Analytics Configuration
1. Set up GA4 property
2. Configure data streams
3. Set up proper tracking codes
4. Verify data collection

## Migration Notes

### From Universal Analytics to GA4
- Update tracking codes
- Modify API calls to use GA4 Data API
- Adjust metrics and dimensions
- Test data collection

### Plugin Updates
- Back up current configuration
- Test in staging environment
- Update credentials if needed
- Verify functionality post-update

## Support

For additional help:
1. Check plugin logs for detailed error messages
2. Verify Google Cloud Console API quotas
3. Test with minimal configuration first
4. Contact support with specific error messages