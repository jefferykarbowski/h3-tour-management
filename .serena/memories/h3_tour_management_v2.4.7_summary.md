# H3 Tour Management Plugin - v2.4.7 Summary

## Plugin Overview
WordPress plugin for managing virtual tours with S3/CloudFront integration, analytics, and user management.

## Recent Updates (v2.4.1 - v2.4.7)

### v2.4.7 - Analytics Fix
- Added missing `get_tour_title()` method to H3TM_Tour_Manager class
- Fixed "Call to undefined method" error in analytics reporting
- Method returns tour name as title for now

### v2.4.6 - Parsedown Vendor Files Fix
- Fixed .gitignore to track plugin-update-checker/vendor files
- Added missing Parsedown vendor files to repository
- Resolved "Class 'Parsedown' not found" error definitively

### v2.4.5 - Parsedown Loading Improvements
- Added file existence checks before requiring Parsedown
- Added fallback loading mechanisms
- Handles different plugin directory names

### v2.4.4 - Parsedown Autoloader Fix
- Fixed autoloader to use require_once for Parsedown
- Added explicit Parsedown loading in load-v5p6.php

### v2.4.3 - UI and Archive Improvements
- Fixed double percentage sign in upload progress bar
- Removed emoji icons from Processing Tour box
- Fixed archive operation to maintain proper folder structure
- Professional left-aligned layout for processing status

### v2.4.2 - Major Cleanup
- Removed 52 obsolete files including:
  - Backup JavaScript files
  - Test directories
  - Lambda functionality
  - Unused tools and scripts
- Reduced repository size significantly

### v2.4.1 - S3 Authentication Fixes
- Fixed undefined `$is_configured` property error
- Properly initialized S3 configuration properties
- Fixed AWS Signature V4 for copy operations with x-amz-copy-source header

## Key Files and Components

### Core Plugin Files
- `h3-tour-management.php` - Main plugin file
- `includes/class-h3tm-s3-simple.php` - S3 integration
- `includes/class-h3tm-tour-manager.php` - Tour management
- `includes/class-h3tm-analytics.php` - Analytics and email
- `includes/class-h3tm-shortcodes-v4.php` - Shortcode implementations

### Important Methods
- `H3TM_S3_Simple::init_s3_config()` - Initializes S3 configuration
- `H3TM_S3_Simple::create_auth_headers()` - AWS Signature V4 implementation
- `H3TM_Tour_Manager::get_tour_title()` - Returns tour title from name
- `H3TM_S3_Simple::archive_tour()` - Archives tours with proper structure

### Configuration
- S3 credentials stored as WordPress options
- CloudFront distribution for content delivery
- Google Analytics integration for tracking
- Email notifications for analytics

## Technical Details

### AWS Integration
- Uses AWS Signature Version 4 for authentication
- Supports S3 bucket operations (upload, copy, delete)
- CloudFront CDN for tour delivery
- Handles large file uploads with chunking

### Tour Structure
- Tours stored in `h3panos/` directory
- Archive functionality maintains folder structure
- Analytics tracked per tour and user

### Dependencies
- plugin-update-checker for automatic updates
- Parsedown for markdown processing
- WordPress 5.0+ required

## Known Issues Resolved
- ✅ S3 authentication errors
- ✅ Parsedown class not found
- ✅ Analytics reporting errors
- ✅ Archive folder structure
- ✅ UI display issues

## Testing Notes
- Test S3 operations after configuration
- Verify analytics tracking is working
- Check email notifications for assigned tours
- Ensure plugin auto-updates work correctly

## Repository
GitHub: https://github.com/jefferykarbowski/h3-tour-management

## Support Contact
H3 Photography: https://h3vt.com/