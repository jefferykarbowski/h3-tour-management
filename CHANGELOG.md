# Changelog

All notable changes to H3 Tour Management will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.6.8] - 2025-10-28

### Added
- **Diagnostics Page**: New admin page under 3D Tours → Diagnostics
  - Real-time slug conflict checker with visual feedback
  - Database overview showing tour counts by status
  - Orphaned tours finder and cleanup tool
  - Recent database changes viewer
  - AJAX-powered interface for instant results
  - File added: `includes/class-h3tm-diagnostics.php`

### Improved
- **Admin UI**: Easier debugging without needing to check logs
- **Troubleshooting**: All diagnostic tools accessible from WordPress admin
- **User Experience**: Visual status indicators and formatted output

## [2.6.7] - 2025-10-28

### Added
- **Debug Tools**: Added comprehensive debugging and cleanup tools for slug conflicts
  - `tools/debug-slug-conflict.php` - Check database for conflicting slugs
  - `tools/cleanup-orphaned-slugs.php` - Remove orphaned database entries
  - `tools/fix-arden-pikesville-slug.sql` - SQL script for manual cleanup
  - Enhanced logging in slug_exists() to identify conflicting tours

### Fixed
- **URL Slug Debugging**: Enhanced slug_exists() with detailed error logging
  - Logs conflicting tours when slug validation fails
  - Shows tour ID, display name, and status of blocking tours
  - File modified: `includes/class-h3tm-tour-metadata.php`

## [2.6.6] - 2025-10-28

### Fixed
- **URL Slug Validation**: Fixed slug_exists() to exclude deleted/archived tours from validation
  - Slug validation now only checks active tours (excludes deleted, archived, failed status)
  - Resolves issue where deleted tours still blocked URL slug reuse
  - File modified: `includes/class-h3tm-tour-metadata.php`

## [2.6.5] - 2025-10-28

### Fixed
- **URL Slug Reuse**: Removed historical slug validation that prevented reusing URL slugs
  - Tours can now be assigned any URL slug that isn't currently in use by another active tour
  - Previously used slugs can now be reused freely
  - Maintains validation for format and active slug uniqueness
  - Files modified: `includes/class-h3tm-new-handlers.php`, `includes/class-h3tm-admin.php`

## [2.0.0] - 2025-09-27

### 🎉 **MAJOR RELEASE: Complete AWS Serverless Architecture**

#### **Revolutionary Changes:**
- **Complete AWS Lambda + S3 system** replacing all server-based uploads
- **Serverless processing** eliminates all Pantheon/server limitations
- **Unlimited file sizes** supported through S3 direct upload
- **Professional user experience** with real-time processing status

#### **Core Features:**
- ✅ **S3 Direct Upload**: Browser → S3 (no server involvement)
- ✅ **Lambda Auto-Processing**: Extracts ZIPs, handles nested Web.zip structure
- ✅ **Analytics Integration**: Hybrid system with dynamic script injection
- ✅ **Tour Management**: Rename, delete (archive), auto-sync with S3
- ✅ **Local URLs**: Tours accessible via `/h3panos/TourName/` while served from S3
- ✅ **Processing Status**: Real-time monitoring with 5-second polling
- ✅ **Archive System**: Deleted tours moved to S3 archive/ folder

#### **Technical Infrastructure:**
- **AWS Lambda** (Node.js 20, 3GB memory, 15min timeout)
- **S3 Bucket** (uploads/, tours/, archive/ folders)
- **IAM Policies** (least-privilege security)
- **Function URLs** (secure HTTP endpoints)
- **WordPress Proxy** (S3 content via local URLs)

#### **Performance:**
- **Upload Speed**: Direct S3 (2-3x faster than chunked)
- **Processing**: 458 files in ~24 seconds
- **No Server Limits**: Memory, disk space, execution time
- **Scalability**: 1000+ concurrent uploads possible

#### **Security:**
- ✅ No AWS CLI dependencies
- ✅ No server-side file storage
- ✅ Redacted sensitive data in logs
- ✅ Proper IAM policies and roles
- ✅ Production-ready security practices

#### **Developer Experience:**
- Professional admin interface
- Real-time processing feedback
- Comprehensive error handling
- Extensive debugging and logging

## [1.6.0] - 2025-09-25

### 🚀 **MAJOR RELEASE: S3-Only Architecture**
- **Complete removal** of chunked upload system - S3 required for all uploads
- **Simplified architecture**: Browser → S3 → WordPress processing
- **No file size limits**: Supports unlimited file sizes through S3
- **Eliminated server constraints**: No more Pantheon disk space or memory limits
- **Clean codebase**: Removed 500+ lines of chunked upload complexity

### Added
- **S3-only upload workflow** with XMLHttpRequest (no AWS SDK dependency)
- **Enhanced error handling** with clear S3 configuration guidance
- **Comprehensive validation suite** for S3-only functionality
- **Professional UI** with S3 direct upload progress tracking

### Removed
- **All chunked upload functionality** (handle_upload_chunk, handle_process_upload)
- **Server-side chunk processing** and temporary file management
- **Dual upload mode complexity** and fallback mechanisms
- **Pantheon-specific workarounds** and disk space checking

## [1.5.0] - 2025-09-21

### Added
- **🚀 AWS S3 Integration**: Complete S3 direct upload system for large files
- S3 configuration and management in WordPress admin (3D Tours → S3 Settings)
- Automatic upload method selection (S3 for >100MB, chunked for smaller)
- Presigned URL generation for secure direct browser-to-S3 uploads
- AWS credential management with environment variable support
- S3 connection testing and validation tools

### Fixed
- **Major**: Eliminates Pantheon disk space limitations for large tour uploads
- Supports files up to 1GB+ with direct S3 upload
- Maintains backward compatibility with existing chunked upload system

## [1.4.21] - 2025-09-21

### Fixed
- Plugin version synchronization and internal management

## [1.4.20] - 2025-09-21

### Added
- **Plugin Update Metadata**: Updated plugin.json with proper H3 Tour Management data
- Complete plugin information for update checker system
- Proper version tracking and changelog integration

### Fixed
- Plugin update checker configuration and metadata

## [1.4.19] - 2025-09-21

### Fixed
- **Plugin Activation**: Made plugin update checker inclusion optional
- Resolves fatal error when PUC files are missing during activation
- Plugin now activates successfully even without update checker

## [1.4.18] - 2025-09-21

### Fixed
- Internal version management

## [1.4.17] - 2025-09-21

### Fixed
- **Current Version**: Ready for testing with enhanced debugging
- All upload improvements and Pantheon fixes included
- Debug mode enabled for troubleshooting large file uploads

## [1.4.16] - 2025-09-21

### Fixed
- **Pantheon Fix**: Disabled unreliable disk_free_space() check on Pantheon
- Enhanced chunk write error detection (partial writes, write failures)
- Better memory management with immediate chunk cleanup and garbage collection
- More detailed error logging for chunk combination process
- Resolves false "Insufficient disk space" errors on Pantheon Live

## [1.4.15] - 2025-09-21

### Added
- **Disk Space Management**: Pre-flight disk space checking before chunk combination
- Automatic cleanup of old temporary files (>1 hour old) to free space
- Clear error messages showing space requirements vs available space
- Better space utilization with 10% safety buffer

### Fixed
- **Critical Fix**: Resolves "Failed to write chunk data" errors for large files
- Addresses Cedar Park (300MB) upload failures due to insufficient disk space
- Automatic space recovery through cleanup of abandoned temp files

## [1.4.14] - 2025-09-21

### Added
- **Enhanced Chunk Debugging**: Detailed logging for chunk combination process
- Progress tracking for large file chunk assembly (every 50 chunks)
- Better error reporting for chunk read/write operations
- Comprehensive logging of file creation and directory operations

### Fixed
- Improved error handling in chunk combination process for large files
- Better diagnosis capabilities for files that fail at the combination stage

## [1.4.13] - 2025-09-21

### Added
- **Debug Mode**: Enhanced JavaScript console debugging for upload process
- Detailed logging of file processing steps and responses
- Better error tracking and diagnosis capabilities
- Disabled automatic page refresh to preserve debug information

### Fixed
- Extended AJAX timeout to 3 minutes for large file processing
- Added comprehensive error logging for troubleshooting Pantheon issues

## [1.4.12] - 2025-09-21

### Fixed
- **Timeout Fix**: Increased execution timeouts for large file processing
- General uploads: 5 minutes → 15 minutes (900 seconds)
- Pantheon uploads: 1 minute → 10 minutes (600 seconds)
- Large tour operations: 5 minutes → 15 minutes (900 seconds)
- Resolves timeout issues with large tours like Cedar Park (315MB)

## [1.4.11] - 2025-09-21

### Fixed
- **Complete Fix**: Increased file size limits for Web/ directory contents to 500MB
- Resolves issue with large video files (e.g., 155MB MP4s) being skipped during extraction
- Now supports: Web.zip (1GB), Web/ content files (500MB), other files (100MB)
- Ensures complete extraction of high-resolution tour media including videos

## [1.4.10] - 2025-09-21

### Fixed
- **Enhanced Fix**: Increased Web.zip file size limit from 500MB to 1GB (1,073,741,824 bytes)
- Accommodates larger tour packages with high-resolution media and complex panoramas
- Maintains memory-efficient streaming extraction for large Web.zip files

## [1.4.9] - 2025-09-21

### Fixed
- **Critical Fix**: Increased file size limit for Web.zip files from 100MB to 500MB
- Resolves issue where large Web.zip files were being skipped during extraction
- Allows nested tour structures with Web.zip files up to 500MB

## [1.4.8] - 2025-09-21

### Added
- Enhanced debugging for nested zip extraction process
- Detailed logging to track directory contents and file movement
- Better error reporting for troubleshooting extraction issues

### Fixed
- Improved nested zip extraction diagnostics and error tracking

## [1.4.7] - 2025-09-21

### Fixed
- Fixed nested zip detection logic that was too restrictive (count files === 0)
- Now properly detects Web.zip in any directory regardless of other files present
- Resolves issue where hidden files (e.g., .DS_Store) prevented nested extraction

## [1.4.6] - 2025-09-21

### Added
- Support for new nested tour zip structure: TOURNAME.zip/TOURNAME/Web.zip/Web/ → tour files
- Enhanced tour extraction logic to detect and handle Web.zip nested archives
- Automatic detection and extraction of nested zip files within tour packages
- Fallback support for existing tour structures to maintain backward compatibility

### Fixed
- Tour software packaging changes now properly handled during upload process
- Nested zip extraction uses same memory-efficient streaming as main extraction

## [1.0.8] - 2025-01-18

### Changed
- Simplified analytics display with clean, modern design
- Removed header bar and complex charts that weren't rendering
- Combined tour selector and date range in single control panel
- Added "All Tours" option to view combined analytics

### Fixed
- Country distribution bars now display correctly
- Removed non-functional SVG circles and complex charts
- Improved layout with better visual hierarchy

### Added
- Horizontal bar charts for country distribution
- Percentage display for each country
- Clean white card-based design
- Better responsive layout for mobile

## [1.0.7] - 2025-01-18

### Changed
- Complete redesign of analytics display to match 3dVista Cloud interface
- Dark theme with cyan accent colors matching 3dVista branding
- New layout with Global Analytics section showing total metrics and top countries
- Individual tour analytics with progress circles and country distribution charts

### Added
- Time range selector (All Time, Last Year, Last Month, Last Week, Custom)
- Progress circle visualizations for tour percentages
- Country distribution bar charts for each tour
- Responsive design optimized for all screen sizes
- 3dVista Cloud branding in header

## [1.0.6] - 2025-01-18

### Changed
- Replaced Source/Medium tracking with Country data in both email analytics and shortcode
- Updated email analytics to show "Visitors by Country" instead of "Referring Traffic"
- Updated analytics display shortcode to show "Visitors by Country" instead of "Traffic Sources"

### Removed
- Source/Medium tracking (not properly captured by 3dVista)
- Referrer tracking methods

## [1.0.5] - 2025-01-18

### Added
- Hero image banner with tour thumbnail at top of analytics page
- "View Tour" button in controls bar
- Professional styling matching the original design

### Changed
- Improved layout with side-by-side tables for Top Pages and Traffic Sources
- Better responsive design for mobile devices
- Enhanced visual hierarchy with proper typography and spacing
- Updated duration formatting for better readability

## [1.0.4] - 2025-01-18

### Fixed
- Fixed 502 errors in `[tour_analytics_display]` shortcode caused by deprecated Google Analytics v3 API
- Completely rewrote shortcode to use GA4 API server-side
- Removed dependency on deprecated Google Analytics Embed API
- Removed Vue.js dependency for better performance

### Changed
- Analytics display now renders server-side for better performance
- Simplified user interface with native HTML form controls
- Improved error handling with clear user messages

### Added
- New GA4-compatible shortcode implementation (class-h3tm-shortcodes-v2.php)
- Date range selector (7 days, 30 days, 90 days, 1 year)
- Top pages report showing page paths and views
- Traffic sources report with source/medium breakdown

## [1.0.3] - 2025-01-18

### Fixed
- Analytics referrer tracking now properly displays traffic sources
- Changed from `pageReferrer` to `sessionSource/sessionMedium` dimensions for better GA4 compatibility
- Added proper handling of empty referrer values (shown as "direct / none")
- Added fallback message when no referrer data is available

### Added
- Diagnostic tools for testing referrer tracking
- Better error handling for missing referrer data

## [1.0.2] - 2024-01-16

### Added
- Automatic directory structure fix for uploaded tours
- Tours with nested folders are now properly extracted with index.html at root level

### Fixed
- Tour uploads now handle ZIP files that contain a parent folder
- Improved tour structure validation to ensure index.html is accessible

## [1.0.1] - 2024-01-16

### Fixed
- Disabled PHP index file creation to prevent permission errors
- Removed Analytics Settings page (no longer needed without PHP index files)
- Fixed SSL certificate issues on localhost development
- Improved error handling for chunked uploads
- Added retry logic for failed chunks

### Changed
- Tours now keep their original index.html files
- Improved upload error messages with specific details
- Enhanced memory and execution time limits for large uploads

### Removed
- Analytics Settings admin page
- PHP index file conversion functionality
- Analytics code injection into tours

## [1.0.0] - 2024-01-16

### Added
- Initial release
- Extracted tour management functionality from theme
- User tour assignment with Select2 (no ACF dependency)
- Email analytics with scheduling (Daily/Weekly/Monthly/Never)
- Chunked file uploads for large tour files
- Google Analytics 4 integration
- Test email functionality
- Comprehensive admin interface
- Tour upload, rename, and delete functionality
- Fallback analytics system for when Google API is not available