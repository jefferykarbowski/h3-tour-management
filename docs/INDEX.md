# H3 Tour Management - Documentation Index

**Last Updated**: 2025-10-16
**Plugin Version**: 2.5.0

---

## Quick Start

- 📖 **[Quick Start Guide](QUICK_START_GUIDE.md)** - Get up and running fast
- 🏗️ **[Architecture Overview](architecture/S3_ARCHITECTURE.md)** - System architecture and AWS infrastructure
- 🔄 **[Migration Guide](migration/TOUR_MIGRATION_GUIDE.md)** - Legacy tour migration to ID-based system
- 🚀 **[Lambda Setup](deployment/LAMBDA_SETUP.md)** - AWS Lambda deployment and configuration

---

## Table of Contents

1. [Core Documentation](#core-documentation)
2. [Architecture & Design](#architecture--design)
3. [Deployment & Setup](#deployment--setup)
4. [Migration & Upgrades](#migration--upgrades)
5. [Development & Integration](#development--integration)
6. [Troubleshooting & Debugging](#troubleshooting--debugging)
7. [Security & Optimization](#security--optimization)
8. [UX & Frontend](#ux--frontend)
9. [Reference & ADRs](#reference--adrs)
10. [Archive](#archive)

---

## Core Documentation

### Primary Guides

| Document | Description | Status |
|----------|-------------|--------|
| **[Features Overview](TOUR_MANAGEMENT_FEATURES_v2.5.0.md)** | Complete feature list for v2.5.0 | ✅ Current |
| **[Quick Start Guide](QUICK_START_GUIDE.md)** | Getting started with plugin | ✅ Current |
| **[Troubleshooting Guide](troubleshooting-guide.md)** | Common issues and solutions | ✅ Current |
| **[Cleanup Analysis Report](cleanup-analysis-report.md)** | Phase 1-3 cleanup documentation | ✅ Current |

### Development Guides

| Document | Description | Status |
|----------|-------------|--------|
| **[How to Add Handlers](HOW_TO_ADD_HANDLERS.md)** | Adding new handler classes | ✅ Current |
| **[Use Trait File](USE_TRAIT_FILE.md)** | Using the trait-based architecture | ✅ Current |
| **[Refactoring Plan](REFACTORING_PLAN.md)** | Ongoing refactoring initiatives | 🔄 In Progress |
| **[Status Tracking Setup](STATUS_TRACKING_SETUP.md)** | Development status tracking | ✅ Current |

---

## Architecture & Design

### System Architecture

**Main Guide**: **[S3_ARCHITECTURE.md](architecture/S3_ARCHITECTURE.md)** (600+ lines)

Comprehensive AWS S3 architecture documentation covering:
- Executive summary and benefits
- High-level architecture diagrams
- System evolution (v1.5.8 → v1.6.0)
- AWS infrastructure setup (6 steps)
- Configuration management (3 priority levels)
- Configuration issues & solutions
- Upload features and data flow
- Implementation guide
- Validation & testing
- Security architecture
- Performance optimization
- Troubleshooting
- Cost analysis

### Architecture Decision Records (ADRs)

Located in `adr/`:

| ADR | Title | Status |
|-----|-------|--------|
| **[ADR-0001](adr/0001-admin-traits.md)** | Trait-Based Admin Architecture | ✅ Accepted |

### Design Documents

| Document | Description | Status |
|----------|-------------|--------|
| **[Alternative URL Handlers](alternative-url-handlers.md)** | URL handler implementation options | 📋 Reference |
| **[Bulletproof Configuration](bulletproof-configuration-system.md)** | Robust configuration system design | 📋 Design |
| **[WordPress Rewrite Rules](wordpress-rewrite-rules-investigation-report.md)** | Rewrite rules investigation | 📋 Analysis |

---

## Deployment & Setup

### Lambda Deployment

**Main Guide**: **[LAMBDA_SETUP.md](deployment/LAMBDA_SETUP.md)** (600+ lines)

Comprehensive Lambda deployment guide covering:
- Overview of Lambda functions
- Prerequisites
- Deployment methods (automated & manual)
- Configuration and environment variables
- IAM permissions
- Testing procedures
- Monitoring and logs
- Troubleshooting
- Known issues (ACL parameter fix)

### Infrastructure Setup

| Document | Description | Status |
|----------|-------------|--------|
| **[S3 Configs](s3-configs/README.md)** | AWS S3 configuration files and scripts | ✅ Active |
| **[CloudFront Setup](cloudfront-setup.md)** | CDN configuration | 📋 Reference |
| **[Pantheon Cron Setup](pantheon-cron-setup.md)** | Scheduled tasks on Pantheon | ✅ Current |
| **[Pantheon .htaccess](pantheon-htaccess-deployment.md)** | .htaccess deployment guide | ✅ Current |

### AWS Security

| Document | Description | Status |
|----------|-------------|--------|
| **[AWS Security Checklist](aws-security-checklist.md)** | Security best practices | ✅ Current |
| **[AWS Security Deployment](aws-security-deployment-guide.md)** | Security implementation guide | ✅ Current |

---

## Migration & Upgrades

### Tour Migration

**Main Guide**: **[TOUR_MIGRATION_GUIDE.md](migration/TOUR_MIGRATION_GUIDE.md)** (600+ lines)

Comprehensive migration guide covering:
- Overview and what migration does
- Prerequisites
- Execution methods (4 ways)
  - WordPress admin interface
  - WP-CLI command
  - Direct PHP execution
  - Admin tools page
- Testing and verification
- Troubleshooting
- Rollback procedures
- Technical details
- Database schema
- Code flow
- FAQs

### Version-Specific Guides

| Document | Description | Status |
|----------|-------------|--------|
| **[v1.7.0 URL Handler](v1.7.0-url-handler-implementation.md)** | URL handler implementation | 📋 Reference |
| **[Tour URL Deployment](tour-url-deployment-guide.md)** | Tour URL system deployment | 📋 Reference |

---

## Development & Integration

### Integration Guides

| Document | Description | Status |
|----------|-------------|--------|
| **[Integration Steps](integration-steps.md)** | General integration procedures | ✅ Current |
| **[React Uploader Integration](react-uploader-integration.md)** | React upload component | ✅ Current |
| **[Optimization Integration](optimization-integration-guide.md)** | Performance optimizations | ✅ Current |

### Development Workflows

| Document | Description | Status |
|----------|-------------|--------|
| **[Backend Optimization Analysis](backend-optimization-analysis.md)** | Performance analysis | 📋 Analysis |
| **[Performance Testing Protocol](performance-testing-protocol.md)** | Testing procedures | ✅ Current |
| **[Phase 2 Dependency Analysis](phase2-class-dependency-analysis.md)** | Class cleanup analysis | ✅ Complete |

### Working Notes

Located in `notes/`:

| Document | Description | Status |
|----------|-------------|--------|
| **[S3 Operations](notes/s3-operations.md)** | S3 operational notes | 📝 Notes |
| **[Trait Refactor Status](notes/trait-refactor-status.md)** | Trait refactoring progress | 📝 Notes |

---

## Troubleshooting & Debugging

### Main Guides

| Document | Description | Status |
|----------|-------------|--------|
| **[Troubleshooting Guide](troubleshooting-guide.md)** | Common issues and solutions | ✅ Current |
| **[Debug React Uploader](debug-react-uploader.md)** | React uploader debugging | ✅ Current |
| **[Critical Fixes Applied](critical-fixes-applied.md)** | Historical fixes log | 📋 Reference |

### Error Handling

| Document | Description | Status |
|----------|-------------|--------|
| **[Error Response Specification](error-response-specification.md)** | API error responses | 📋 Spec |

---

## Security & Optimization

### Security Documentation

| Document | Description | Status |
|----------|-------------|--------|
| **[AWS Security Checklist](aws-security-checklist.md)** | Security best practices | ✅ Current |
| **[AWS Security Deployment](aws-security-deployment-guide.md)** | Implementation guide | ✅ Current |

### Optimization

| Document | Description | Status |
|----------|-------------|--------|
| **[Backend Optimization Analysis](backend-optimization-analysis.md)** | Performance analysis | 📋 Analysis |
| **[Optimization Integration](optimization-integration-guide.md)** | Integration procedures | ✅ Current |
| **[Performance Testing Protocol](performance-testing-protocol.md)** | Testing procedures | ✅ Current |

---

## UX & Frontend

### UX Documentation

Located in `ux/`:

| Document | Description | Status |
|----------|-------------|--------|
| **[Admin Table Wireframe](ux/admin-table-wireframe.md)** | Tours table UI design | 📋 Design |

### Frontend Features

| Document | Description | Status |
|----------|-------------|--------|
| **[React Uploader Integration](react-uploader-integration.md)** | Upload component | ✅ Current |
| **[Debug React Uploader](debug-react-uploader.md)** | Debugging guide | ✅ Current |
| **[Add Rebuild Button UI](ADD_REBUILD_BUTTON_UI.md)** | UI enhancement spec | 📋 Spec |

---

## Reference & ADRs

### Requirements

Located in `requirements/`:

| Document | Description | Status |
|----------|-------------|--------|
| **[Critical Bugs](requirements/critical-bugs.md)** | Critical bug tracking | 🔴 Active |

### Architecture Decision Records

Located in `adr/`:

| ADR | Title | Date | Status |
|-----|-------|------|--------|
| **[ADR-0001](adr/0001-admin-traits.md)** | Trait-Based Admin Architecture | 2024 | ✅ Accepted |

### Diagrams

Located in `diagrams/`: (Visual architecture diagrams)

---

## Archive

**Location**: `docs/archive/`

Archived documentation for historical reference. Contains:

### Migration Archive
- **Location**: `archive/migration/`
- **Count**: 6 files (2659 total lines)
- **README**: [`archive/migration/README.md`](archive/migration/README.md)
- **Superseded By**: [`migration/TOUR_MIGRATION_GUIDE.md`](migration/TOUR_MIGRATION_GUIDE.md)

### Lambda Archive
- **Location**: `archive/lambda/`
- **Count**: 3 files
- **README**: [`archive/lambda/README.md`](archive/lambda/README.md)
- **Superseded By**: [`deployment/LAMBDA_SETUP.md`](deployment/LAMBDA_SETUP.md)

### S3 Archive
- **Location**: `archive/s3/`
- **Count**: 11 files (~101KB)
- **README**: [`archive/s3/README.md`](archive/s3/README.md)
- **Superseded By**: [`architecture/S3_ARCHITECTURE.md`](architecture/S3_ARCHITECTURE.md)

### Features Archive
- **Location**: `archive/features/`
- **Count**: 10 files + 2 directories
- **README**: [`archive/features/README.md`](archive/features/README.md)
- **Content**: Completed feature implementations

---

## Documentation Status Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Current and actively maintained |
| 🔄 | In progress / being updated |
| 📋 | Reference / specification |
| 📝 | Working notes |
| 🔴 | Requires attention |
| ⚠️ | Deprecated / outdated |

---

## Contributing to Documentation

### Adding New Documentation

1. **Create file** in appropriate directory:
   - `architecture/` - System design and architecture
   - `deployment/` - Deployment and setup guides
   - `migration/` - Migration procedures
   - `notes/` - Working notes and drafts
   - `adr/` - Architecture decision records
   - `ux/` - User experience documentation

2. **Follow naming conventions**:
   - Use kebab-case: `my-new-document.md`
   - Be descriptive: `s3-upload-optimization.md`
   - Include version if applicable: `v2.0.0-feature-guide.md`

3. **Update this INDEX.md** with new document entry

4. **Add frontmatter** (recommended):
   ```markdown
   # Document Title
   **Version**: 1.0.0
   **Last Updated**: 2025-10-16
   **Status**: Current
   ```

### Updating Existing Documentation

1. Update the **Last Updated** date
2. Update version numbers if applicable
3. Note changes in document changelog (if present)
4. Update INDEX.md if document status changes

### Archiving Documentation

1. Move to appropriate `archive/` subdirectory
2. Update or create `archive/[category]/README.md`
3. Update INDEX.md to remove from main sections
4. Add archive reference to INDEX.md

---

## Search Tips

**Find by keyword**:
```bash
# Search all documentation
grep -r "keyword" docs/

# Search active docs only (exclude archive)
grep -r "keyword" docs/ --exclude-dir=archive

# Case-insensitive search
grep -ri "keyword" docs/
```

**Find by file type**:
```bash
# All markdown files
find docs/ -name "*.md"

# Specific category
find docs/architecture/ -name "*.md"
```

---

## Related Resources

- **Plugin README**: [`../README.md`](../README.md)
- **Changelog**: [`../CHANGELOG.md`](../CHANGELOG.md)
- **Contributing**: [`../CONTRIBUTING.md`](../CONTRIBUTING.md)
- **License**: [`../LICENSE`](../LICENSE)

---

**For questions or documentation issues**, please create an issue on GitHub.

**Last INDEX Update**: 2025-10-16 (Phase 3 Documentation Consolidation)
