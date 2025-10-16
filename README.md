# H3 Tour Management

**Version**: 2.5.1
**Architecture**: S3-Only Direct Upload System

A comprehensive WordPress plugin for managing 3D virtual tours with AWS S3 storage, Lambda processing, analytics integration, and automated migration from legacy name-based to ID-based tours.

## Features

### Core Tour Management
- **S3 Direct Upload**: Browser-to-S3 uploads bypassing server limitations
  - Handles files up to 2GB+ reliably
  - Eliminates Pantheon disk space constraints
  - Direct uploads to AWS S3 with presigned URLs
  - Automatic processing via Lambda webhooks

- **Tour Operations**
  - Upload, rename, and delete tours
  - Automatic thumbnail detection
  - ID-based tour system (`YYYYMMDD_HHMMSS_8random`)
  - Legacy name-based tour migration

### User Management
- Assign tours to specific users
- Custom user fields using Select2 (no ACF dependency)
- User-specific tour access control
- Tour metadata tracking

### Analytics & Email
- Automated analytics emails (Daily/Weekly/Monthly)
- Google Analytics 4 integration
- Customizable email templates
- Email scheduling system

### Admin Interface
- React-based upload interface with real-time progress
- Intuitive management dashboard
- Tour overview with thumbnails
- Email and S3 configuration
- Migration tools (WordPress admin + WP-CLI)

## Architecture Overview

### System Design

```
Browser → AWS S3 → WordPress downloads → Extract & Process → h3panos/TOURNAME/
                   ↓
              Lambda Processing
                   ↓
              Webhook Notification
```

**Key Components**:
- **Frontend**: React upload component with S3 presigned URLs
- **WordPress**: Tour management, user assignments, analytics
- **AWS S3**: Direct file storage and retrieval
- **AWS Lambda**: ZIP extraction and file processing
- **Database**: WordPress options + custom metadata tables

For complete architecture documentation, see:
- **[S3 Architecture](docs/architecture/S3_ARCHITECTURE.md)** - Complete system design
- **[Migration Guide](docs/migration/TOUR_MIGRATION_GUIDE.md)** - Legacy tour migration
- **[Lambda Setup](docs/deployment/LAMBDA_SETUP.md)** - Deployment procedures
- **[Documentation Index](docs/INDEX.md)** - All documentation

## Requirements

### Core Requirements
- **WordPress**: 5.0+
- **PHP**: 7.4+ with 512MB memory limit
- **Server**: Internet connectivity for S3 downloads

### AWS Requirements
- **AWS S3 Bucket**: For tour file storage
- **IAM User**: With S3 read/write permissions
- **AWS Lambda** (optional): For server-side processing
- **AWS Credentials**: Access key and secret key

### Optional
- **Google Analytics 4**: For analytics features
- **Google API Client**: For real analytics data (via Composer)

## Installation

### 1. Plugin Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to '3D Tours' in the admin menu

### 2. AWS S3 Setup

**Required for tour uploads**. See [S3 Architecture Guide](docs/architecture/S3_ARCHITECTURE.md#aws-infrastructure-setup) for complete setup.

**Quick Start**:
1. Create S3 bucket in AWS console
2. Create IAM user with S3 permissions
3. Configure WordPress S3 settings
4. Test S3 connection

**Configuration Options**:

**Option 1 - Environment Variables (Recommended)**:
```bash
export AWS_ACCESS_KEY_ID="your-access-key"
export AWS_SECRET_ACCESS_KEY="your-secret-key"
export H3_S3_BUCKET="your-bucket-name"
export H3_S3_REGION="us-east-1"
```

**Option 2 - WordPress Admin**:
1. Go to **3D Tours → S3 Settings**
2. Enter S3 bucket name and region
3. Enter AWS credentials
4. Click "Test S3 Connection"

### 3. AWS Lambda Setup (Optional)

For server-side tour processing, see [Lambda Setup Guide](docs/deployment/LAMBDA_SETUP.md).

### 4. Google Analytics Setup (Optional)

To enable real analytics data:

1. Install Google API Client:
   ```bash
   composer install
   ```

2. Create Google Cloud service account with Analytics API access
3. Place credentials at WordPress root: `service-account-credentials.json`
4. Grant service account "Viewer" access to GA4 property

## Quick Start Guide

See **[QUICK_START_GUIDE.md](docs/QUICK_START_GUIDE.md)** for detailed instructions.

### Uploading Tours

1. Go to **3D Tours → Manage Tours**
2. Click "Upload New Tour"
3. Select your tour ZIP file (up to 2GB)
4. Enter a tour name
5. Upload begins automatically (direct to S3)
6. Monitor real-time progress
7. Tour processes automatically after upload

**Note**: S3 configuration required. Files are uploaded directly from browser to AWS S3.

### Migrating Legacy Tours

For migrating existing name-based tours to ID-based system:

**Via WordPress Admin**:
1. Go to **3D Tours → Admin Tools**
2. Click "Run Migration" tab
3. Review tours to migrate
4. Click "Start Migration"

**Via WP-CLI**:
```bash
wp h3tm migrate-tours --dry-run
wp h3tm migrate-tours
```

See **[Migration Guide](docs/migration/TOUR_MIGRATION_GUIDE.md)** for complete procedures.

### Assigning Tours to Users

1. Edit any WordPress user profile
2. Find the "3D Tour Access" section
3. Select tours from dropdown (uses Select2)
4. Save the user profile

### Email Configuration

1. Go to **3D Tours → Email Settings**
2. Configure sender name and email
3. Users set email frequency in their profile

## Documentation

**Main Index**: **[docs/INDEX.md](docs/INDEX.md)**

### Core Guides
- **[Quick Start](docs/QUICK_START_GUIDE.md)** - Get up and running fast
- **[Features Overview](docs/TOUR_MANAGEMENT_FEATURES_v2.5.0.md)** - Complete feature list
- **[Troubleshooting](docs/troubleshooting-guide.md)** - Common issues and solutions

### Architecture & Deployment
- **[S3 Architecture](docs/architecture/S3_ARCHITECTURE.md)** - AWS infrastructure and system design
- **[Lambda Setup](docs/deployment/LAMBDA_SETUP.md)** - Lambda deployment and configuration
- **[Migration Guide](docs/migration/TOUR_MIGRATION_GUIDE.md)** - Legacy tour migration procedures

### Development
- **[How to Add Handlers](docs/HOW_TO_ADD_HANDLERS.md)** - Extending the plugin
- **[Use Trait File](docs/USE_TRAIT_FILE.md)** - Trait-based architecture
- **[Refactoring Plan](docs/REFACTORING_PLAN.md)** - Ongoing improvements

## Changelog

### 2.5.0 (Current)
- **Architecture**: Trait-based modular code organization
- **S3-Only System**: Removed chunked uploads, S3 direct upload only
- **Performance**: Reduced memory (1024M → 512M) and execution time (15min → 5min)
- **Migration**: Complete legacy-to-ID-based tour migration system
- **Lambda**: AWS Lambda integration for server-side processing
- **React**: Modern React upload interface with real-time progress
- **Documentation**: Phase 3 consolidation (71 docs → 3 authoritative guides)

### 1.6.0
- S3-only architecture
- Removed chunked upload backend (500+ lines)
- Simplified frontend JavaScript
- Breaking changes: S3 required for uploads

### 1.5.8
- Dual upload system (chunked + S3)
- S3 direct upload for large files (>100MB)
- Fallback to chunked upload

### 1.0.0
- Initial release
- Extracted tour management from theme
- Email scheduling system
- Google Analytics 4 integration

See **[CHANGELOG.md](CHANGELOG.md)** for complete version history.

## Contributing

See **[CONTRIBUTING.md](CONTRIBUTING.md)** for contribution guidelines.

## Support

- **Documentation**: [docs/INDEX.md](docs/INDEX.md)
- **Troubleshooting**: [docs/troubleshooting-guide.md](docs/troubleshooting-guide.md)
- **Bug Reports**: GitHub Issues
- **Feature Requests**: GitHub Issues

## License

GPL v2 or later

---

**Documentation Last Updated**: 2025-10-16 (Phase 3 Consolidation)