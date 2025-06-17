# H3 Tour Management Plugin Migration Guide

## Version 2.0.0 Upgrade Instructions

This guide provides step-by-step instructions for migrating from the original H3 Tour Management plugin to the enhanced version 2.0.0.

## Overview of Changes

### Security Enhancements
- Added comprehensive file validation beyond extension checks
- Implemented rate limiting for all AJAX endpoints
- Added path sanitization to prevent directory traversal
- Removed exposed file system paths from error messages
- Added proper nonce verification with additional security checks

### Performance Improvements
- Implemented caching for Google Analytics API calls
- Added pagination for tour listings
- Lazy loading of Select2 library only where needed
- Optimized database queries with proper indexes
- Added background processing for large operations

### Code Organization
- Split analytics functionality into separate service class
- Created dedicated security, logging, and database classes
- Implemented WordPress Filesystem API for file operations
- Added proper error handling throughout
- Created cleanup service for orphaned files

### Database Changes
- Added indexes to improve query performance
- New tables for caching and activity logging
- Improved schema with foreign key constraints
- Added email queue for reliable delivery

## Pre-Migration Checklist

1. **Backup Your Site**
   - Create a full backup of your WordPress database
   - Backup the entire `wp-content/plugins/h3-tour-management` directory
   - Backup the `h3panos` directory containing your tours

2. **Check Requirements**
   - PHP 7.4 or higher
   - WordPress 5.8 or higher
   - MySQL 5.7 or higher (or MariaDB 10.2+)

3. **Test Environment**
   - Perform the migration on a staging site first
   - Verify all functionality before migrating production

## Migration Steps

### Step 1: Prepare for Migration

1. **Deactivate the Current Plugin**
   ```
   WordPress Admin > Plugins > Deactivate "H3 Tour Management"
   ```

2. **Export Current Settings**
   ```sql
   -- Run this SQL to export current settings
   SELECT * FROM wp_options WHERE option_name LIKE 'h3tm_%';
   SELECT * FROM wp_usermeta WHERE meta_key LIKE 'h3tm_%';
   ```

### Step 2: Install Enhanced Version

1. **Update Plugin Files**
   - Copy all new files from the enhanced version
   - The following files are new and must be added:
     - `includes/class-h3tm-security.php`
     - `includes/class-h3tm-logger.php`
     - `includes/class-h3tm-database.php`
     - `includes/class-h3tm-analytics-service.php`
     - `includes/class-h3tm-tour-manager-v2.php`
     - `includes/class-h3tm-admin-v2.php`
     - `includes/class-h3tm-cleanup.php`
     - `templates/analytics-code.php`
     - `h3-tour-management-v2.php`

2. **Update Main Plugin File**
   - Rename `h3-tour-management.php` to `h3-tour-management-old.php`
   - Rename `h3-tour-management-v2.php` to `h3-tour-management.php`

### Step 3: Activate and Migrate

1. **Activate the Plugin**
   ```
   WordPress Admin > Plugins > Activate "H3 Tour Management"
   ```

2. **Run Database Migration**
   The plugin will automatically:
   - Create new database tables
   - Add indexes to existing tables
   - Migrate existing data

3. **Verify Migration**
   - Check System Status page: `3D Tours > System Status`
   - Review migration logs in: `wp-content/uploads/h3tm-logs/`

### Step 4: Update Configuration

1. **Analytics Settings**
   - Navigate to `3D Tours > Settings`
   - Verify Google Analytics Measurement ID
   - Enable desired tracking options

2. **Email Settings**
   - Navigate to `3D Tours > Email Settings`
   - Configure email templates
   - Test email delivery

3. **Security Settings**
   - Review rate limiting settings
   - Configure cleanup schedules
   - Set log retention periods

### Step 5: Update Tour Files

The plugin will automatically update tour files, but you can manually trigger updates:

1. **Update Analytics Code**
   ```
   3D Tours > Settings > Update Analytics Code in All Tours
   ```

2. **Verify Tours**
   - Check each tour URL to ensure proper functionality
   - Verify analytics tracking is working

### Step 6: Update Custom Code

If you have custom code that interacts with the plugin, update it to use the new classes:

#### Old Code:
```php
$tour_manager = new H3TM_Tour_Manager();
$tours = $tour_manager->get_all_tours();
```

#### New Code:
```php
$tour_manager = new H3TM_Tour_Manager_V2();
$tours = $tour_manager->get_all_tours(array(
    'page' => 1,
    'per_page' => 20,
    'include_meta' => true
));
```

#### Analytics Service:
```php
// Old way
$analytics = new H3TM_Analytics();
$analytics->get_report($tour_title, $start_date);

// New way
$data = H3TM_Analytics_Service::get_tour_analytics($tour_title, $start_date);
```

## Post-Migration Tasks

1. **Clear Caches**
   - Clear WordPress object cache
   - Clear any page caching plugins
   - Clear CDN cache if applicable

2. **Test Functionality**
   - Upload a test tour
   - Send test analytics email
   - Verify user tour assignments
   - Check analytics data collection

3. **Monitor Logs**
   - Review error logs for any issues
   - Check activity logs for unexpected behavior
   - Monitor performance metrics

4. **Schedule Cleanup**
   - Configure automatic cleanup settings
   - Set retention periods for logs and cache
   - Enable orphaned file cleanup

## Rollback Instructions

If you need to rollback to the previous version:

1. **Deactivate Plugin**
2. **Restore Original Files**
   - Delete new files
   - Rename `h3-tour-management-old.php` back to `h3-tour-management.php`
3. **Remove New Tables** (optional)
   ```sql
   DROP TABLE IF EXISTS wp_h3tm_analytics_cache;
   DROP TABLE IF EXISTS wp_h3tm_activity_log;
   DROP TABLE IF EXISTS wp_h3tm_tour_meta;
   DROP TABLE IF EXISTS wp_h3tm_email_queue;
   ```
4. **Reactivate Plugin**

## Troubleshooting

### Common Issues

1. **"Google API client library not found" Error**
   - Install Google API client: `composer require google/apiclient`

2. **Tours Not Loading**
   - Check file permissions on `h3panos` directory
   - Verify .htaccess rules are correct
   - Clear permalink cache

3. **Analytics Not Working**
   - Verify service account credentials file exists
   - Check GA4 property ID is correct
   - Test connection in System Status

4. **Email Delivery Issues**
   - Check WordPress email configuration
   - Verify SMTP settings if using external service
   - Review email queue in database

### Getting Help

1. **Check Logs**
   - Plugin logs: `wp-content/uploads/h3tm-logs/`
   - WordPress debug log: `wp-content/debug.log`

2. **Enable Debug Mode**
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. **Contact Support**
   - Include system status information
   - Provide relevant log entries
   - Describe steps to reproduce issue

## New Features to Explore

After successful migration, explore these new features:

1. **Enhanced Security**
   - Rate limiting dashboard
   - Security event logging
   - File validation reports

2. **Performance Monitoring**
   - Analytics caching statistics
   - Database query performance
   - Resource usage tracking

3. **Advanced Analytics**
   - Real-time data caching
   - Batch processing for reports
   - Custom event tracking

4. **Automation**
   - Scheduled cleanup tasks
   - Email queue processing
   - Automatic tour optimization

## Best Practices

1. **Regular Maintenance**
   - Run cleanup weekly
   - Monitor log file sizes
   - Review security events

2. **Performance Optimization**
   - Enable analytics caching
   - Use pagination for large lists
   - Optimize image sizes in tours

3. **Security Hardening**
   - Keep rate limits enabled
   - Review user permissions regularly
   - Monitor failed login attempts

## Version History

- **2.0.0** - Major security and performance overhaul
- **1.0.0** - Initial release

## Additional Resources

- [WordPress Filesystem API Documentation](https://developer.wordpress.org/reference/functions/wp_filesystem/)
- [Google Analytics 4 Documentation](https://developers.google.com/analytics/devguides/reporting/data/v1)
- [WordPress Security Best Practices](https://wordpress.org/support/article/hardening-wordpress/)