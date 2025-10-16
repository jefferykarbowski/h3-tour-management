# Phase 2: Class Dependency Analysis
**Date**: 2025-10-16
**Project**: H3 Tour Management v2.5.0

---

## Executive Summary

**Analysis Complete**: Full dependency map of 50 PHP classes
**Classification**:
- ✅ **Active** (14): Directly instantiated in main plugin file
- 🔧 **Utility/Required** (4): Core dependencies used by multiple classes
- 🎯 **Feature-Specific** (8): Self-initializing or trait-loaded classes
- ⚠️ **Alternative/Optimized** (3): Variant implementations (V2, Optimized)
- ❌ **Unused/Orphaned** (21): Not referenced anywhere

---

## 1. Core Utility Classes (Required - Do NOT Remove)

These are foundational classes used by many other components:

### H3TM_Logger
**File**: `includes/class-h3tm-logger.php`
**Purpose**: Centralized logging system with levels (debug, info, warning, error, critical)
**Used By**: 15+ classes (Admin, Analytics, AWS, Cleanup, Security, etc.)
**Status**: ✅ **CRITICAL** - Required by entire system
**Usage Pattern**:
```php
H3TM_Logger::error('context', 'message', $data);
H3TM_Logger::info('admin', 'Operation complete');
```

### H3TM_Security
**File**: `includes/class-h3tm-security.php`
**Purpose**: Security validation, rate limiting, file checks, AJAX verification
**Used By**: Admin, AWS Security, Optimized Admin
**Status**: ✅ **CRITICAL** - Security layer
**Usage Pattern**:
```php
H3TM_Security::verify_ajax_request($nonce, $action);
H3TM_Security::check_rate_limit('upload', $user_id);
H3TM_Security::validate_upload($file);
```

### H3TM_Config
**File**: `includes/class-h3tm-config.php`
**Purpose**: Environment-aware configuration (credentials paths, SSL verification, dev detection)
**Used By**: Analytics, AWS Security, Shortcodes V4
**Status**: ✅ **CRITICAL** - Configuration management
**Usage Pattern**:
```php
H3TM_Config::get_credentials_path();
H3TM_Config::should_verify_ssl();
H3TM_Config::is_development();
```

### H3TM_Database
**File**: `includes/class-h3tm-database.php`
**Purpose**: Database schema, migrations, activity logging, stats
**Used By**: Cleanup, Tour Manager Optimized
**Status**: ✅ **REQUIRED** - Data persistence layer
**Usage Pattern**:
```php
H3TM_Database::log_activity('tour_renamed', 'tour', $name, $data);
H3TM_Database::get_tour_meta($tour_name, 'key');
H3TM_Database::get_stats();
```

---

## 2. Active Classes (Instantiated in Main Plugin)

From `h3-tour-management.php:62-85`:

| Class | Line | Purpose | Dependencies |
|-------|------|---------|--------------|
| **H3TM_Admin** | 64 | Admin UI, AJAX handlers | S3_Simple, Tour_Metadata, Analytics, Activator |
| **H3TM_User_Fields** | 65 | User custom fields management | - |
| **H3TM_Analytics** | 66 | Google Analytics integration | H3TM_Config, Tour_Manager |
| **H3TM_Email** | 67 | Email notifications | - |
| **H3TM_Tour_Manager** | 68 | Core tour operations | - |
| **H3TM_S3_Simple** | 69 | AWS S3 integration | - |
| **H3TM_S3_Proxy** | 70 | S3 URL proxying & rewrite rules | S3_Simple, CDN_Helper, Tour_Metadata |
| **H3TM_S3_Settings** | 71 | Admin settings page for S3 | S3_Simple, S3_Processor |
| **H3TM_Analytics_Endpoint** | 72 | Analytics AJAX endpoint | - |
| **H3TM_Shortcodes_V4** | 73 | WordPress shortcodes | H3TM_Config |
| **H3TM_Tour_Processing** | 76 | Lambda webhook processing | - |
| **H3TM_Lambda_Webhook** | 79 | Webhook handler for Lambda | Tour_Processing, Tour_Manager, Tour_Metadata |
| **H3TM_Lambda_Integration** | 80 | Lambda integration layer | Lambda_Webhook |

---

## 3. Loaded But Not Instantiated (Required Files)

These are `require_once`'d but **not** instantiated directly in main file:

### Self-Initializing Classes
```php
// H3TM_React_Uploader (line 50)
// File ends with: H3TM_React_Uploader::init();
✅ Auto-initializes via static call

// H3TM_React_Tours_Table (line 51)
// File ends with: H3TM_React_Tours_Table::init();
✅ Auto-initializes via static call

// H3TM_CDN_Helper (line 37)
// File ends with: H3TM_CDN_Helper::get_instance();
✅ Singleton auto-initialized
```

### Lazy-Loaded/Conditional Classes
```php
// H3TM_Tour_Metadata (line 48)
✅ Used by: Admin (multiple places), Lambda_Webhook, S3_Proxy
// Instantiated on-demand: new H3TM_Tour_Metadata()

// H3TM_Tour_Migration (line 52)
✅ Used by: migration tools in tools/ directory
// Not used in regular plugin operation, only migrations

// H3TM_URL_Redirector (line 49)
⏸️ DISABLED (see line 84): // new H3TM_URL_Redirector();
// Comment: "TEMPORARILY DISABLED: This was conflicting with S3 Proxy rewrite rules"

// H3TM_Pantheon_Helper (line 34)
⚠️ Loaded but never instantiated
// Used via static calls?: H3TM_Pantheon_Helper::get_h3panos_path()

// H3TM_Cron_Analytics (line 35)
⚠️ Loaded but never instantiated
// File auto-instantiates?: new H3TM_CRON_Analytics(); (at end of file)
```

---

## 4. Optimized/Alternative Implementations

### Version 2 (V2) Classes
These likely serve as base classes for optimized versions:

```
❓ H3TM_Admin_V2 (not found in files list)
   ↳ Extended by: H3TM_Admin_Optimized

❓ H3TM_Tour_Manager_V2 (not found in files list)
   ↳ Extended by: H3TM_Tour_Manager_Optimized, H3TM_Cleanup
```

**Investigation needed**: Are V2 classes in different files or same file as base classes?

### Optimized Implementations
```
⚠️ H3TM_Admin_Optimized
   Purpose: Enhanced admin with progress tracking, timeout handling
   Status: NOT instantiated in main file
   Used: Conditionally loaded by H3TM_Admin (line 57)

⚠️ H3TM_Tour_Manager_Optimized
   Purpose: Performance-optimized tour operations
   Status: NOT instantiated in main file
   Used: Conditionally created by Admin_Optimized (line 111, 165)
```

**Recommendation**:
- If V2 is current standard, update main plugin to use V2/Optimized
- Or deprecate Optimized classes if not production-ready

---

## 5. Unused/Orphaned Classes (21 files)

### AWS/Security Stack (6 files) - Possibly Incomplete Feature
```
❌ class-h3tm-aws-audit.php
❌ class-h3tm-aws-security.php
❌ class-h3tm-s3-config-manager.php
❌ class-h3tm-config-adapter.php
❌ class-h3tm-config-ajax-handlers.php
❌ class-h3tm-s3-integration.php
```
**Analysis**: References to H3TM_Bulletproof_Config, H3TM_Environment_Config not found
**Recommendation**: Archive - Incomplete AWS security feature implementation

### S3 Handlers (4 files) - Alternative Implementations?
```
❌ class-h3tm-s3-processor.php
   Used by: S3_Settings (line 730) - Admin settings only

❌ class-h3tm-s3-proxy-enhanced.php
   Enhanced version of S3_Proxy? Not used anywhere

❌ class-h3tm-s3-tour-registry.php
   Has TODO comment (flagged in Phase 1 analysis)

❌ class-h3tm-s3-uploader.php
   Not referenced - superseded by React uploader?
```
**Recommendation**: Keep S3_Processor (used), archive others

### URL/Tour Handlers (7 files) - Experimental/Deprecated?
```
❌ class-h3tm-404-handler.php
❌ class-h3tm-action-hook.php
❌ class-h3tm-direct-handler.php
❌ class-h3tm-endpoint-handler.php
❌ class-h3tm-tour-url-diagnostics.php
❌ class-h3tm-tour-url-handler.php
❌ class-h3tm-url-manager.php
❌ h3tour-direct-handler.php (non-class file)
```
**Analysis**: Alternative URL routing implementations, all unused
**Recommendation**: Archive all - URL redirector is disabled, these appear experimental

### Analytics/Utilities (4 files)
```
❌ class-h3tm-analytics-service.php
   Uses H3TM_Logger but not instantiated anywhere

❌ class-h3tm-analytics-simple.php
   Simple analytics without Google - unused

❌ class-h3tm-cleanup.php
   References H3TM_Tour_Manager_V2 (not found) - incomplete?

❌ class-h3tm-shortcodes.php
   Superseded by class-h3tm-shortcodes-v4.php
```
**Recommendation**:
- Archive analytics-service, analytics-simple, cleanup
- Delete shortcodes.php (confirmed superseded by v4)

---

## 6. Dependency Graph

### High-Level Architecture
```
Main Plugin File
├─ Active Classes (14)
│  ├─ Admin → S3_Simple, Tour_Metadata, Analytics
│  ├─ Analytics → Config, Tour_Manager
│  ├─ Lambda_Webhook → Tour_Processing, Tour_Manager, Tour_Metadata
│  ├─ Lambda_Integration → Lambda_Webhook
│  ├─ S3_Proxy → S3_Simple, CDN_Helper, Tour_Metadata
│  ├─ S3_Settings → S3_Simple, S3_Processor
│  └─ Shortcodes_V4 → Config
│
├─ Auto-Init Classes (3)
│  ├─ React_Uploader::init()
│  ├─ React_Tours_Table::init()
│  └─ CDN_Helper::get_instance()
│
├─ On-Demand Classes (5)
│  ├─ Tour_Metadata (instantiated in Admin, Lambda_Webhook, S3_Proxy)
│  ├─ Pantheon_Helper (static calls)
│  ├─ Cron_Analytics (auto-init at file end?)
│  ├─ Tour_Migration (tools only)
│  └─ URL_Redirector (DISABLED)
│
└─ Utility Classes (4) - Used Everywhere
   ├─ Logger
   ├─ Security
   ├─ Config
   └─ Database
```

### Detailed Dependency Matrix

| Class | Depends On | Used By |
|-------|-----------|---------|
| **Logger** | None | 15+ classes |
| **Security** | Logger (conditional) | Admin, AWS, Optimized |
| **Config** | None | Analytics, AWS, Shortcodes |
| **Database** | Logger | Cleanup, Tour_Manager_Optimized |
| **Tour_Metadata** | None | Admin (10+ places), Lambda_Webhook, S3_Proxy, Activator, Tools |
| **S3_Simple** | None | Admin (6+ places), S3_Proxy, S3_Settings, Tools |
| **CDN_Helper** | None | S3_Proxy |
| **Tour_Manager** | None | Analytics (2 places), Lambda_Webhook, Base for Optimized |

---

## 7. Categorized Action Plan

### ✅ Keep (27 classes)

**Core Active** (14):
- H3TM_Activator
- H3TM_Admin
- H3TM_Analytics
- H3TM_Analytics_Endpoint
- H3TM_Email
- H3TM_Lambda_Integration
- H3TM_Lambda_Webhook
- H3TM_S3_Proxy
- H3TM_S3_Settings
- H3TM_S3_Simple
- H3TM_Shortcodes_V4
- H3TM_Tour_Manager
- H3TM_Tour_Processing
- H3TM_User_Fields

**Utility/Required** (4):
- H3TM_Config
- H3TM_Database
- H3TM_Logger
- H3TM_Security

**Auto-Initialized** (3):
- H3TM_CDN_Helper
- H3TM_React_Tours_Table
- H3TM_React_Uploader

**On-Demand/Conditional** (5):
- H3TM_Cron_Analytics (verify auto-init)
- H3TM_Pantheon_Helper
- H3TM_Tour_Metadata
- H3TM_Tour_Migration (tools only)
- H3TM_URL_Redirector (currently disabled)

**Feature-Specific** (1):
- H3TM_S3_Processor (used by S3_Settings)

---

### ⚠️ Investigate Before Action (3 classes)

**Optimized Implementations**:
```
1. H3TM_Admin_Optimized
   Question: Is this production-ready? Should main file use this?
   Action: Test thoroughly, then either:
      a) Update main file to instantiate Optimized version
      b) Move to deprecated/ if not ready

2. H3TM_Tour_Manager_Optimized
   Question: Same as above
   Depends on: H3TM_Tour_Manager_V2 (not found - needs investigation)

3. Base V2 Classes
   Question: Where are H3TM_Admin_V2 and H3TM_Tour_Manager_V2?
   Action: Search for class definitions, understand inheritance
```

---

### ❌ Archive to `includes/deprecated/` (21 classes)

**AWS Security Stack** (6) - Incomplete feature:
- class-h3tm-aws-audit.php
- class-h3tm-aws-security.php
- class-h3tm-config-adapter.php
- class-h3tm-config-ajax-handlers.php
- class-h3tm-s3-config-manager.php
- class-h3tm-s3-integration.php

**Alternative S3 Implementations** (3):
- class-h3tm-s3-proxy-enhanced.php
- class-h3tm-s3-tour-registry.php
- class-h3tm-s3-uploader.php

**URL Handler Experiments** (8):
- class-h3tm-404-handler.php
- class-h3tm-action-hook.php
- class-h3tm-direct-handler.php
- class-h3tm-endpoint-handler.php
- class-h3tm-tour-url-diagnostics.php
- class-h3tm-tour-url-handler.php
- class-h3tm-url-manager.php
- h3tour-direct-handler.php

**Analytics/Utils Alternatives** (4):
- class-h3tm-analytics-service.php
- class-h3tm-analytics-simple.php
- class-h3tm-cleanup.php
- class-h3tm-shortcodes.php (superseded by V4)

---

## 8. Implementation Steps

### Step 1: Verify Base Classes (30 min)
```bash
# Search for V2 base classes
grep -r "class H3TM_Admin_V2" includes/
grep -r "class H3TM_Tour_Manager_V2" includes/

# Check if they're in same files as Optimized
# Or if Optimized extends the main classes directly
```

### Step 2: Test Optimized Classes (1-2 hours)
```
1. Review H3TM_Admin_Optimized features:
   - Progress tracking
   - Timeout handling
   - Enhanced error responses

2. Check if option 'h3tm_use_optimized_operations' is set
3. Test tour operations with Optimized classes
4. Decision: Promote to main or deprecate
```

### Step 3: Create Deprecated Directory (15 min)
```bash
mkdir -p includes/deprecated
echo "# Deprecated Classes - Archived $(date)" > includes/deprecated/README.md
```

### Step 4: Archive Unused Classes (30 min)
```bash
# Move AWS security stack
mv includes/class-h3tm-aws-*.php includes/deprecated/
mv includes/class-h3tm-config-adapter.php includes/deprecated/
mv includes/class-h3tm-config-ajax-handlers.php includes/deprecated/
mv includes/class-h3tm-s3-config-manager.php includes/deprecated/
mv includes/class-h3tm-s3-integration.php includes/deprecated/

# Move alternative S3 implementations
mv includes/class-h3tm-s3-proxy-enhanced.php includes/deprecated/
mv includes/class-h3tm-s3-tour-registry.php includes/deprecated/
mv includes/class-h3tm-s3-uploader.php includes/deprecated/

# Move URL handler experiments
mv includes/class-h3tm-*handler*.php includes/deprecated/
mv includes/class-h3tm-url-*.php includes/deprecated/
mv includes/h3tour-direct-handler.php includes/deprecated/

# Move analytics alternatives
mv includes/class-h3tm-analytics-service.php includes/deprecated/
mv includes/class-h3tm-analytics-simple.php includes/deprecated/
mv includes/class-h3tm-cleanup.php includes/deprecated/

# Delete superseded shortcodes
rm includes/class-h3tm-shortcodes.php
```

### Step 5: Document Changes (30 min)
```
1. Update docs/cleanup-analysis-report.md with Phase 2 findings
2. Create includes/deprecated/README.md explaining archived classes
3. Add comments in main plugin file explaining Optimized class strategy
```

### Step 6: Test Plugin Functionality (1 hour)
```
1. Deactivate and reactivate plugin
2. Test core features:
   - Upload tour
   - Rename tour
   - Delete tour
   - View analytics
   - S3 integration
3. Check for PHP errors in debug.log
4. Verify no broken dependencies
```

---

## 9. Risk Assessment

### Low Risk ✅
- Archiving unused AWS security classes (never referenced)
- Archiving URL handler experiments (all unused)
- Archiving analytics alternatives (unused)
- Deleting old shortcodes.php (superseded by v4)

### Medium Risk ⚠️
- Archiving S3 alternatives (verify no conditional loading)
- Moving Optimized classes (need to understand V2 relationship first)

### High Risk 🔴
- None identified (all operations are archive, not delete)
- Can restore from includes/deprecated/ if issues arise

---

## 10. Expected Outcomes

### Code Organization
```
Before: 50 PHP class files in includes/
After:  29 active class files + deprecated/ directory

Reduction: 42% fewer files in main includes/ directory
```

### Developer Experience
```
Before: Unclear which classes are active vs experimental
After:  Clear separation of active, utility, and deprecated code

Onboarding: "Which classes should I learn first?"
Answer: "The 27 in includes/ - ignore deprecated/"
```

### Maintainability
```
Before: Risk of accidentally using deprecated/experimental code
After:  Clear signal - anything in deprecated/ is archived

IDE autocomplete: Fewer irrelevant class suggestions
```

---

## 11. Next Actions

**Immediate** (This Session):
- [ ] Search for V2 base classes
- [ ] Decision on Optimized classes (promote or deprecate)
- [ ] Create includes/deprecated/ directory
- [ ] Move 21 unused classes to deprecated/
- [ ] Delete class-h3tm-shortcodes.php

**Follow-up** (Next Session):
- [ ] Create deprecated/README.md with archive details
- [ ] Update main cleanup report
- [ ] Test plugin functionality after archiving
- [ ] Document Optimized classes strategy

---

**Analysis Completed**: 2025-10-16
**Analyst**: Phase 2 Cleanup Process
**Status**: Ready for implementation
