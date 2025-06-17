# H3 Tour Management

A comprehensive WordPress plugin for managing 3D virtual tours with analytics integration and email notifications.

## Features

- **3D Tour Management**
  - Upload tours via WordPress admin with chunked file uploads
  - Rename and delete existing tours
  - Automatic thumbnail detection
  - Support for large tour files

- **User Management**
  - Assign tours to specific users
  - Custom user fields using Select2 (no ACF dependency)
  - User-specific tour access control

- **Email Analytics**
  - Automated analytics emails (Daily/Weekly/Monthly)
  - Google Analytics 4 integration
  - Customizable email templates
  - Test email functionality

- **Admin Interface**
  - Intuitive management dashboard
  - Tour overview with thumbnails
  - Email settings configuration
  - Analytics overview page

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Google Analytics 4 property (for analytics features)
- Google API Client Library (optional, for real analytics data)

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to '3D Tours' in the admin menu to start managing tours

## Configuration

### Google Analytics Setup (Optional)

To enable real analytics data:

1. Install Google API Client via Composer:
   ```bash
   composer install
   ```

2. Create a Google Cloud service account with Analytics API access

3. Place the credentials file at your WordPress root as `service-account-credentials.json`

4. Grant the service account email "Viewer" access to your GA4 property

## Usage

### Uploading Tours

1. Go to **3D Tours > Manage Tours**
2. Click "Upload New Tour"
3. Select your tour ZIP file
4. Enter a tour name
5. Click Upload (supports large files via chunked upload)

### Assigning Tours to Users

1. Edit any WordPress user profile
2. Find the "3D Tour Access" section
3. Select tours from the dropdown (uses Select2 for better UX)
4. Save the user profile

### Email Configuration

1. Go to **3D Tours > Email Settings**
2. Configure sender name and email
3. Users can set their email frequency preference in their profile

## Changelog

### 1.0.0
- Initial release
- Extracted tour management from theme functions
- Added chunked file uploads
- Implemented email scheduling system
- Created admin interface
- Added Google Analytics 4 integration

## Support

For support and bug reports, please use the GitHub issues page.

## License

GPL v2 or later