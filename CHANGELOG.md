# Changelog

All notable changes to H3 Tour Management will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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