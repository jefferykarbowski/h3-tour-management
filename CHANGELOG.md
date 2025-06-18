# Changelog

All notable changes to H3 Tour Management will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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