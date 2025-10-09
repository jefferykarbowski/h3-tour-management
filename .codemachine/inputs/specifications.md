# H3 Tour Management WordPress Plugin - Fix & Revamp Specifications

## Project Overview

**Current State**: WordPress plugin for managing 3D tours stored in AWS S3/CloudFront with Lambda processing
**Goal**: Fix critical bugs, refactor code for maintainability, and modernize the admin UX
**Stack**: WordPress 5.8+, PHP 7.4+, MySQL, AWS S3/CloudFront/Lambda, jQuery

---

## 🔴 CRITICAL BUGS TO FIX

### Bug #1: Delete Tour Completely Broken
**Symptom**: Clicking Delete shows alert "Error: Tour not found in S3"
**Test Case**: Delete tour named "Jeffs Test"

**Root Cause**:
Lambda creates S3 folders WITH SPACES: `s3://bucket/tours/Jeffs Test/`
But code expects DASHES: `s3://bucket/tours/Jeffs-Test/`

**Files Involved**:
- `includes/class-h3tm-s3-simple.php` - `archive_tour()` method (lines ~1212-1224)
- `includes/class-h3tm-tour-metadata.php` - Metadata storage
- `includes/class-h3tm-activator.php` - Migration logic (lines 78-107)

**Fix Requirements**:
1. ✅ Migration in `class-h3tm-activator.php` already preserves spaces (line 98)
2. ❌ `archive_tour()` must look up metadata.s3_folder instead of sanitizing display_name
3. ❌ All S3 operations must use metadata.s3_folder as source of truth
4. Add extensive logging to track S3 operations
5. Test with tours containing: spaces, hyphens, multiple spaces, special chars

**Example Fix**:
```php
// In archive_tour() method
// WRONG (current):
$tour_s3_name = sanitize_title($tour_name); // Converts spaces to dashes!

// RIGHT (needed):
$metadata = new H3TM_Tour_Metadata();
$tour = $metadata->get_by_display_name($tour_name);
$tour_s3_name = str_replace('tours/', '', rtrim($tour->s3_folder, '/'));
```

---

### Bug #2: Change URL Feature Non-Functional
**Symptom**: Modal opens, user enters new slug, AJAX completes, but nothing happens
**Issues**:
- URL doesn't change in database
- No redirect occurs when accessing old URL
- Tour table doesn't refresh to show new URL
- Embed code still has old URL

**Files Involved**:
- `includes/class-h3tm-admin.php` - Handler at line 2051-2115
- `includes/class-h3tm-new-handlers.php` - Duplicate handler (trait never included)
- `includes/class-h3tm-tour-metadata.php` - `change_slug()` method
- `includes/class-h3tm-url-redirector.php` - Redirect logic
- `assets/js/admin-tour-features.js` - Frontend modal

**Debug Steps**:
1. Open browser DevTools → Console tab
2. Click "Change URL" button
3. Check for JavaScript errors
4. Switch to Network tab
5. Submit new URL
6. Check if AJAX request to `admin-ajax.php?action=h3tm_change_tour_url` fires
7. Check response data
8. Check PHP error log for server-side failures

**Possible Issues**:
- ❌ AJAX handler not actually registered (verify line 32 in constructor)
- ❌ JavaScript function called before defined (hoisting issue)
- ❌ Database update succeeds but UI doesn't refresh
- ❌ Redirect class not initialized on `template_redirect` hook

**Fix Requirements**:
1. Verify AJAX action registration in constructor
2. Verify handler method exists and is callable
3. Add success logging to `change_slug()` method
4. After successful change, trigger tour list refresh
5. Test redirect by visiting old URL
6. Verify embed code updates with new URL

---

### Bug #3: Rename Tour Does Nothing
**Symptom**: Clicking "Rename" button has zero effect - no modal, no error, nothing

**Files Involved**:
- `assets/js/admin.js` - Handler at line 575
- `includes/class-h3tm-admin.php` - AJAX handler `handle_rename_tour()`

**Debug Steps**:
1. Open DevTools → Console
2. Click "Rename" button
3. Check console for errors
4. Check Network tab for AJAX requests
5. Verify button has correct class: `class="button rename-tour"`

**Possible Issues**:
- ❌ Event handler not attaching (selector issue?)
- ❌ JavaScript error preventing execution
- ❌ Button created dynamically but handler registered before DOM ready
- ❌ AJAX handler exists but has runtime error

**Fix Requirements**:
1. Verify `.rename-tour` event delegation works
2. Add console.log to confirm click handler fires
3. Check if modal HTML is generated correctly
4. Test rename with tour containing spaces
5. Ensure tour list refreshes after successful rename

---

### Bug #4: Update Tour & Get Script Features Broken
**Symptom**: Buttons exist but clicking does nothing

**Files Involved**:
- `assets/js/admin-tour-features.js` - All handlers
- `includes/class-h3tm-admin.php` - Line 132 (JS enqueue), Lines 1920-2044 (handlers)

**Possible Issues**:
- ❌ JavaScript file not loading (check Network tab)
- ❌ Functions called before defined (hoisting issue in admin-tour-features.js)
  - Line 15: calls `createUpdateTourModal()`
  - Line 288: `createUpdateTourModal()` defined (273 lines later!)
  - Line 61: calls `createChangeUrlModal()`
  - Line 140: `createChangeUrlModal()` defined (125 lines later!)
- ❌ Browser cache serving old JavaScript
- ❌ WordPress cache preventing new code from loading

**Fix Requirements**:
1. Hard refresh browser cache: Ctrl+Shift+R
2. Move function definitions BEFORE they're called
3. Or use function declarations (hoisted) instead of expressions:
   ```javascript
   // Current (NOT hoisted):
   var createModal = function() { ... }

   // Fix (IS hoisted):
   function createModal() { ... }
   ```
4. Add console.log statements to verify handlers fire
5. Check browser console for "undefined function" errors

---

### Bug #5: Rebuild Metadata Button Not Working
**Symptom**: Button shows on Settings page but clicking does nothing

**Files Involved**:
- `includes/class-h3tm-admin.php` - Line 763 (button HTML), Line 929 (JS handler)
- Handler at line 2116+

**Possible Issues**:
- ❌ AJAX action `wp_ajax_h3tm_rebuild_metadata` not registered
- ❌ JavaScript handler not executing
- ❌ Browser cache issue

**Fix Requirements**:
1. Verify action registration in constructor (should be line 33)
2. Test AJAX call in browser console:
   ```javascript
   jQuery.post(ajaxurl, {
     action: 'h3tm_rebuild_metadata',
     nonce: '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>'
   }, function(response) { console.log(response); });
   ```
3. Check PHP error log for handler failures
4. Add logging to handler method

---

## 🟡 CODE REFACTORING NEEDED

### Problem: Unmaintainable File Size
**Current**: `class-h3tm-admin.php` is 2100+ lines, impossible to edit safely
**Impact**: Multiple failed edit attempts, syntax errors, file corruption

**Solution**: Split into trait files

### Proposed Structure:
```
includes/
├── class-h3tm-admin.php (300 lines - core functionality only)
├── traits/
│   ├── trait-h3tm-tour-handlers.php (~250 lines)
│   │   ├── handle_update_tour()
│   │   ├── handle_get_embed_script()
│   │   ├── handle_change_tour_url()
│   │   └── handle_rebuild_metadata()
│   ├── trait-h3tm-delete-rename.php (~200 lines)
│   │   ├── handle_delete_tour()
│   │   └── handle_rename_tour()
│   ├── trait-h3tm-s3-operations.php (~300 lines)
│   │   ├── handle_get_s3_presigned_url()
│   │   ├── handle_process_s3_upload()
│   │   ├── download_from_s3()
│   │   └── cleanup_s3_file()
│   ├── trait-h3tm-migration.php (~250 lines)
│   │   ├── handle_migrate_tour_to_s3()
│   │   ├── upload_directory_to_s3()
│   │   └── delete_directory()
│   └── trait-h3tm-page-renderers.php (~900 lines)
│       ├── render_main_page()
│       ├── render_email_settings_page()
│       ├── render_analytics_page()
│       └── render_s3_settings_page()
```

**Implementation Steps**:
1. Create `includes/traits/` directory
2. Extract methods into trait files (one trait at a time)
3. Add `use TraitName;` in main class
4. Test each trait after extraction
5. Validate PHP syntax on all files
6. Test all functionality still works

**Benefits**:
- ✅ Each file ~200-300 lines (easy to edit)
- ✅ Clear separation of concerns
- ✅ Can edit traits without touching main file
- ✅ No more "file modified" errors
- ✅ Future maintenance much easier

---

## 🟢 UX IMPROVEMENTS

### Current UX Problems

1. **Button Overload**
   - Too many buttons per row: Delete, Rename, Archive, Update, Get Script, Change URL
   - No visual hierarchy - all equal importance
   - Actions scattered, no logical grouping

2. **No Search/Filter**
   - With 10+ tours, impossible to find specific tour
   - No way to filter by status or date
   - No sorting options

3. **No Bulk Operations**
   - Can't select multiple tours to delete
   - Can't bulk archive old tours
   - Must click each tour individually

4. **Status Column Removed**
   - Status should appear inline with name when NOT "Available"
   - Currently no visual indicator during processing

5. **Upload Progress Broken**
   - Shows "Processing..." with no % complete
   - Lambda finishes but UI times out
   - No real-time feedback

6. **No Confirmation Dialogs**
   - Delete has no "Are you sure?"
   - Easy to accidentally delete tours
   - No undo functionality

### Proposed UX Improvements

**Tour Table Layout**:
```
┌────────────────────────────────────────────────────────┐
│ Search: [____________]  Filter: [All ▾]  Status: [▾]  │
├────────────────────────────────────────────────────────┤
│ ☐ Select All                              Bulk: [▾]   │
├────────────────────────────────────────────────────────┤
│ ☐ Jeffs Test                    Updated: 2 hours ago  │
│   /h3panos/jeffs-test/                                │
│   👁️ Preview | 📋 Embed | ⋮ More (Rename, Update,      │
│                            Change URL, Archive, Delete)│
├────────────────────────────────────────────────────────┤
│ ☐ Downtown Office       🔄 Processing... 45% (2m left) │
│   /h3panos/downtown-office/                           │
│   ⏳ Processing (no actions available)                 │
└────────────────────────────────────────────────────────┘
```

**Button Hierarchy**:
- **Primary**: Preview (eye icon) - most common action
- **Secondary**: Embed, Change URL - frequent but not primary
- **Tertiary**: Everything else in "⋮ More" dropdown - rare/destructive

**Real-Time Progress**:
- WebSocket or long-polling for Lambda status
- Show percentage complete
- Show estimated time remaining
- Auto-refresh when complete

**Confirmation Dialogs**:
- Delete: "Are you sure? This cannot be undone."
- Archive: "Tour will no longer be accessible."
- Change URL: "Old URL will redirect to new URL."

**Search & Filter**:
- Instant search by tour name
- Filter by status: All, Available, Processing, Archived
- Sort by: Name, Date Created, Date Updated

---

## 📋 IMPLEMENTATION PRIORITY

### Phase 1: Critical Bug Fixes (Week 1)
**Goal**: Make existing features actually work

1. ✅ **Fix Delete** - Most critical, users can't remove tours
   - Update `archive_tour()` to use metadata.s3_folder
   - Add comprehensive logging
   - Test with "Jeffs Test" tour

2. ✅ **Fix Change URL** - Important for SEO and branding
   - Debug AJAX handler registration
   - Verify database updates
   - Test redirect functionality
   - Ensure UI refreshes

3. ✅ **Fix Rename** - Basic functionality users expect
   - Debug event handler attachment
   - Test modal display
   - Verify database updates
   - Ensure UI refreshes

4. ✅ **Fix Update & Get Script** - New features that should work
   - Reorder JavaScript functions
   - Fix hoisting issues
   - Clear browser/WordPress cache
   - Test end-to-end

5. ✅ **Fix Rebuild Metadata** - Critical for fixing data issues
   - Verify AJAX registration
   - Add success/error logging
   - Test metadata rebuild

**Success Criteria**:
- Can delete "Jeffs Test" without errors
- Can change URL and old URL redirects
- Can rename tour and see new name in table
- Update and Get Script buttons work
- Rebuild Metadata fixes incorrect data

---

### Phase 2: Code Refactoring (Week 2)
**Goal**: Make code maintainable for future development

1. ✅ **Create trait structure**
   - Create `includes/traits/` directory
   - Plan trait organization

2. ✅ **Extract tour handlers trait**
   - Move Update, Get Script, Change URL, Rebuild to trait
   - Test functionality after extraction

3. ✅ **Extract delete/rename trait**
   - Move Delete and Rename handlers
   - Test functionality

4. ✅ **Extract S3 operations trait**
   - Move all S3-related methods
   - Test S3 operations

5. ✅ **Extract page renderers trait**
   - Move all render_*_page methods
   - Test admin pages display correctly

6. ✅ **Update main class**
   - Add `use` statements for all traits
   - Verify all functionality works
   - Validate PHP syntax

**Success Criteria**:
- Main class under 400 lines
- Each trait under 300 lines
- All tests pass
- No functionality broken

---

### Phase 3: UX Improvements (Week 3)
**Goal**: Modern, intuitive admin interface

1. ✅ **Redesign tour table**
   - Add checkboxes for selection
   - Reorganize buttons (primary/secondary/tertiary)
   - Add search box
   - Add filter dropdown

2. ✅ **Implement bulk actions**
   - Bulk delete with confirmation
   - Bulk archive
   - Bulk export URLs

3. ✅ **Add real-time progress**
   - Implement polling or WebSocket
   - Show % complete during processing
   - Show estimated time remaining
   - Auto-refresh on completion

4. ✅ **Add confirmation dialogs**
   - Delete confirmation
   - Archive confirmation
   - Destructive action warnings

5. ✅ **Improve modals**
   - Better visual design
   - Loading states
   - Error handling
   - Success animations

**Success Criteria**:
- Can search tours by name
- Can filter by status
- Can bulk delete multiple tours
- Upload shows real progress
- Modals look modern and professional

---

## 🔧 TECHNICAL DETAILS

### Database Schema
```sql
-- Current schema (already created)
CREATE TABLE wp_h3tm_tour_metadata (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    tour_slug varchar(255) NOT NULL,
    display_name varchar(255) NOT NULL,
    s3_folder varchar(255) NOT NULL,  -- MUST preserve spaces!
    url_history text,                  -- JSON array of old slugs
    created_date datetime DEFAULT CURRENT_TIMESTAMP,
    updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY tour_slug (tour_slug),
    KEY idx_tour_slug (tour_slug),
    KEY idx_s3_folder (s3_folder)
);
```

### Lambda Integration Notes
**Important**: Lambda can be updated if needed. Current Lambda behavior:
- Creates folders with EXACT tour name (preserves spaces)
- Example: Tour "Jeffs Test" → `s3://bucket/tours/Jeffs Test/`
- Uploads all tour files to that folder
- Sets folder path in metadata

**If Lambda Changes Needed**:
- Lambda script can be modified to match WordPress expectations
- Or WordPress can be modified to match Lambda behavior
- Current approach: WordPress matches Lambda (preserve spaces)

### AWS Services Used
- **S3**: Tour file storage
- **CloudFront**: CDN for tour delivery (cache invalidation needed after updates)
- **Lambda**: Processes uploaded tours, extracts ZIP, uploads to S3

### File Structure
```
h3-tour-management/
├── h3-tour-management.php          # Main plugin file
├── includes/
│   ├── class-h3tm-activator.php    # Plugin activation (DB setup)
│   ├── class-h3tm-admin.php        # Admin UI and handlers (2100 lines - NEEDS REFACTOR)
│   ├── class-h3tm-s3-simple.php    # S3 operations
│   ├── class-h3tm-tour-metadata.php # Metadata CRUD
│   ├── class-h3tm-url-redirector.php # 301 redirects
│   └── traits/ (TO CREATE)
│       ├── trait-h3tm-tour-handlers.php
│       ├── trait-h3tm-delete-rename.php
│       ├── trait-h3tm-s3-operations.php
│       ├── trait-h3tm-migration.php
│       └── trait-h3tm-page-renderers.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       ├── admin.js                  # Main admin JS
│       └── admin-tour-features.js    # Update/Get Script/Change URL
└── docs/                             # Documentation files
```

---

## 🐛 DEBUGGING CHECKLIST

### Before Starting Any Fix

1. **Check Browser Console**
   - F12 → Console tab
   - Look for JavaScript errors
   - Check for "undefined function" errors

2. **Check Network Tab**
   - F12 → Network tab
   - Filter by XHR
   - Verify AJAX requests fire
   - Check request/response data

3. **Check PHP Error Log**
   - Check WordPress debug.log
   - Check server error logs
   - Look for fatal errors, warnings

4. **Verify File Loading**
   - Network tab → JS filter
   - Confirm admin.js loads
   - Confirm admin-tour-features.js loads
   - Check file timestamps (cache issue?)

5. **Test Database**
   - Verify metadata table exists
   - Check sample data: `SELECT * FROM wp_h3tm_tour_metadata LIMIT 5;`
   - Verify s3_folder has spaces preserved

### Common Issues

1. **Browser Cache**
   - Solution: Ctrl+Shift+R (hard refresh)
   - Or: Disable cache in DevTools

2. **WordPress Cache**
   - Solution: Deactivate/reactivate plugin
   - Or: Clear WordPress cache plugin

3. **Function Hoisting**
   - Problem: Function called before defined
   - Solution: Move definitions to top OR use function declarations

4. **Event Delegation**
   - Problem: Handler attached before DOM element exists
   - Solution: Use `$(document).on('click', '.selector', handler)`

5. **AJAX Action Not Registered**
   - Problem: Handler exists but not registered in constructor
   - Solution: Add `add_action('wp_ajax_ACTION', array($this, 'handler'))`

---

## 📝 TESTING CHECKLIST

### After Each Fix

1. ✅ **PHP Syntax Valid**
   ```bash
   php -l includes/class-h3tm-admin.php
   php -l includes/class-h3tm-s3-simple.php
   php -l includes/class-h3tm-tour-metadata.php
   ```

2. ✅ **JavaScript Syntax Valid**
   ```bash
   node -c assets/js/admin.js
   node -c assets/js/admin-tour-features.js
   ```

3. ✅ **Browser Console Clean**
   - No JavaScript errors
   - No 404s on resource loading
   - AJAX requests succeed

4. ✅ **Functionality Works**
   - Test with real tour data
   - Test edge cases (spaces, hyphens, special chars)
   - Test error handling
   - Test success messaging

5. ✅ **Database State Correct**
   - Query metadata after operation
   - Verify data updated correctly
   - Check url_history JSON valid

### End-to-End Tests

**Test Delete:**
1. Create test tour "Test Tour 123"
2. Upload successfully
3. Verify appears in tour table
4. Click Delete
5. Confirm deletion
6. Verify removed from table
7. Verify removed from S3
8. Verify metadata deleted

**Test Change URL:**
1. Use existing tour
2. Click "Change URL"
3. Enter new slug "new-test-tour"
4. Confirm change
5. Verify table shows new URL
6. Visit old URL → should 301 redirect
7. Check embed code has new URL
8. Verify metadata updated

**Test Rename:**
1. Click "Rename" on tour
2. Enter new name "Updated Tour Name"
3. Confirm rename
4. Verify table shows new name
5. Verify URL unchanged
6. Verify metadata display_name updated

**Test Update:**
1. Click "Update" on tour
2. Select new ZIP file
3. Confirm upload
4. Wait for processing
5. Verify tour files updated in S3
6. Verify CloudFront cache invalidated

**Test Get Script:**
1. Click "Get Script"
2. Verify modal shows correct URL
3. Click "Copy" buttons
4. Verify copied to clipboard
5. Paste in test page
6. Verify iframe loads tour

---

## 🎯 SUCCESS METRICS

### Phase 1 (Bug Fixes)
- ✅ Zero errors on Delete operation
- ✅ Change URL works with redirect
- ✅ Rename updates UI immediately
- ✅ Update and Get Script functional
- ✅ Rebuild Metadata fixes data issues

### Phase 2 (Refactor)
- ✅ Main class under 400 lines
- ✅ Each trait under 300 lines
- ✅ All functionality preserved
- ✅ PHP syntax valid on all files

### Phase 3 (UX)
- ✅ Can search tours instantly
- ✅ Can filter by status
- ✅ Bulk actions work
- ✅ Real-time upload progress
- ✅ Modern, professional interface

---

## 📞 SUPPORT & RESOURCES

### Documentation
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [AWS S3 PHP SDK](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-examples.html)
- [WordPress AJAX](https://codex.wordpress.org/AJAX_in_Plugins)

### Key Classes
- `H3TM_Admin` - Main admin functionality (TO REFACTOR)
- `H3TM_S3_Simple` - S3 operations
- `H3TM_Tour_Metadata` - Database CRUD
- `H3TM_URL_Redirector` - 301 redirects
- `H3TM_Activator` - Plugin activation/deactivation

### Important Files
- `h3-tour-management.php` - Main plugin file (loads classes)
- `includes/class-h3tm-admin.php` - Admin UI (2100 lines - needs refactor!)
- `assets/js/admin.js` - Main admin JavaScript
- `assets/js/admin-tour-features.js` - New feature handlers

---

## 🚀 GETTING STARTED

1. **Start with Delete Bug** - Most critical, easiest to fix
2. **Test thoroughly** - Use "Jeffs Test" tour as test case
3. **Move to other bugs** - One at a time, test each
4. **Then refactor** - Only after all bugs fixed
5. **Finally UX** - Polish after stability achieved

**Remember**: Lambda script can be updated if needed. Current behavior is to preserve spaces in folder names, which WordPress should match.

---

**Good luck! Focus on getting Delete working first - it's the most critical issue and will validate the approach for other bugs.** 🎯
