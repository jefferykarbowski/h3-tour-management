# Deprecated Classes Archive
**Date Archived**: 2025-10-16
**Reason**: Phase 2 cleanup - removing unused/experimental implementations

## ⚠️ Important Notice

**These classes are NOT loaded by the plugin and should NOT be used in new development.**

This directory contains archived class files that were:
- Experimental/incomplete implementations
- Superseded by newer versions
- Alternative implementations that were never activated
- Dead code with no references in the active codebase

## 📋 Archived Classes

### AWS Security Stack (6 files) - Incomplete Feature
These were part of an incomplete AWS security enhancement feature:
- `class-h3tm-aws-audit.php` - AWS audit logging (never used)
- `class-h3tm-aws-security.php` - Enhanced AWS security layer (references missing classes)
- `class-h3tm-config-adapter.php` - Config adapter for bulletproof system (incomplete)
- `class-h3tm-config-ajax-handlers.php` - AJAX handlers for config (unused)
- `class-h3tm-s3-config-manager.php` - S3 config manager (references missing classes)
- `class-h3tm-s3-integration.php` - S3 integration wrapper (unused)

**Missing Dependencies**: References `H3TM_Bulletproof_Config` and `H3TM_Environment_Config` which don't exist

### Alternative S3 Implementations (3 files)
Different approaches to S3 integration that were superseded:
- `class-h3tm-s3-proxy-enhanced.php` - Enhanced version of S3_Proxy (never activated)
- `class-h3tm-s3-tour-registry.php` - Tour registry system (contains TODO comment, unused)
- `class-h3tm-s3-uploader.php` - Direct S3 uploader (superseded by React uploader)

### URL Handler Experiments (8 files)
Various experimental URL routing implementations:
- `class-h3tm-404-handler.php` - Custom 404 handler for tours
- `class-h3tm-action-hook.php` - Action hook-based routing
- `class-h3tm-direct-handler.php` - Direct file handler
- `class-h3tm-endpoint-handler.php` - Endpoint-based routing
- `class-h3tm-tour-url-diagnostics.php` - URL debugging tools
- `class-h3tm-tour-url-handler.php` - Tour URL handler
- `class-h3tm-url-manager.php` - URL management system
- `h3tour-direct-handler.php` - Standalone direct handler

**Note**: Main `H3TM_URL_Redirector` class is currently disabled (see h3-tour-management.php:84)
due to conflicts with S3 Proxy rewrite rules. These are alternative implementations that were never activated.

### Analytics/Utility Alternatives (4 files)
Alternative implementations that were superseded or incomplete:
- `class-h3tm-analytics-service.php` - Analytics service layer (never instantiated)
- `class-h3tm-analytics-simple.php` - Simple analytics without Google (unused)
- `class-h3tm-cleanup.php` - Cleanup utilities (references missing H3TM_Tour_Manager_V2)
- `class-h3tm-shortcodes.php` - OLD VERSION (superseded by class-h3tm-shortcodes-v4.php)

## 🔄 Restoration Instructions

If you need to restore any of these classes:

```bash
# Copy the file back to includes/
cp includes/deprecated/class-h3tm-CLASSNAME.php includes/

# Add require_once to h3-tour-management.php
# Add instantiation if needed
# Test thoroughly before deploying
```

## 📊 Impact of Archiving

- **Files moved**: 21 PHP classes
- **Main includes/ directory**: Reduced from 50 to 29 active classes (42% reduction)
- **Breaking changes**: None - these classes were not referenced in active code
- **Rollback**: Simply move files back and add require_once statements

## 🔍 Verification

To verify none of these classes are used:

```bash
# From project root
cd includes/
grep -r "new H3TM_AWS_Audit" ../
grep -r "H3TM_Cleanup::" ../
grep -r "new H3TM_URL_Manager" ../
# etc... (all should return no results in active code)
```

## 📝 Related Documentation

- **Phase 2 Analysis**: `docs/phase2-class-dependency-analysis.md`
- **Overall Cleanup Report**: `docs/cleanup-analysis-report.md`
- **Commit**: Search git log for "Phase 2 cleanup"

---

**Archived by**: Phase 2 Cleanup Process
**Can be deleted after**: 6 months (2025-04-16) if no restoration needed
**Restore contact**: Review git history and Phase 2 analysis document
