# H3 Tour Management - New Features Implementation v2.5.0

## 🎯 Overview

This document summarizes the implementation of four major new features for the H3 Tour Management plugin:

1. **Name/URL Decoupling** - Rename tours without changing URLs
2. **Update Tour Functionality** - Overwrite existing tour files with new uploads
3. **URL Redirect System** - Preserve old tour URLs with automatic 301 redirects
4. **Embed Script Generation** - One-click copy embed code for client distribution

---

## 📋 Features Implemented

### 1. Name/URL Decoupling ✅

**Problem**: Previously, renaming a tour would change its S3 folder name and URL, breaking all distributed links.

**Solution**: New database table `h3tm_tour_metadata` decouples display names from URL slugs and S3 storage paths.

**How It Works:**
- Display name (shown in admin) can be changed without affecting URLs
- URL slug remains constant, preserving all links
- S3 folder location stays the same, avoiding file moves

**Database Schema:**
```sql
CREATE TABLE wp_h3tm_tour_metadata (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    tour_slug varchar(255) NOT NULL,        -- URL path (unchanging)
    display_name varchar(255) NOT NULL,     -- Admin display name (changeable)
    s3_folder varchar(255) NOT NULL,        -- S3 storage path (unchanging)
    url_history text,                       -- JSON array of old slugs
    created_date datetime DEFAULT CURRENT_TIMESTAMP,
    updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY tour_slug (tour_slug),
    KEY idx_tour_slug (tour_slug),
    KEY idx_s3_folder (s3_folder)
);
```

**Files Modified:**
- `includes/class-h3tm-activator.php` - Database table creation + migration
- `includes/class-h3tm-tour-metadata.php` - NEW - Metadata CRUD operations

---

### 2. Update Tour Functionality ✅

**Problem**: No way to update/overwrite existing tour files without deleting and re-uploading.

**Solution**: New "Update" button that overwrites tour files and invalidates CloudFront cache.

**How It Works:**
1. User clicks "Update" button next to tour in admin table
2. Modal opens prompting for new ZIP file
3. File uploads to S3 using existing presigned URL system
4. All existing tour files in S3 are deleted
5. New tour files are uploaded to same S3 location
6. CloudFront cache is invalidated for instant delivery
7. Metadata `updated_date` is updated

**CloudFront Cache Invalidation:**
```php
// Invalidates: /tours/{tour-slug}/*
$invalidation_path = '/tours/' . $tour_s3_name . '/*';
```

**Files Modified:**
- `includes/class-h3tm-s3-simple.php` - Added `invalidate_tour_cache()` and `update_tour()` methods
- `includes/class-h3tm-admin.php` - Added `handle_update_tour()` AJAX handler
- `assets/js/admin-tour-features.js` - NEW - Update tour UI and logic
- `assets/js/admin.js` - Added "Update" button to tour listing (line 1148)
- `assets/css/admin.css` - Styling for update modal and warnings

---

### 3. URL Redirect System ✅

**Problem**: When tour URLs change (through future slug changes), old links become 404s.

**Solution**: `url_history` JSON field tracks all previous slugs, with WordPress rewrite rules handling 301 redirects.

**How It Works:**
1. When a tour's slug changes, old slug is saved to `url_history` array
2. WordPress rewrite rules intercept `/h3panos/{slug}/` requests
3. If slug matches an entry in any tour's `url_history`, redirect to current slug (301)
4. Maximum 10 historical slugs per tour (oldest removed when limit reached)

**url_history Structure:**
```json
["old-slug-1", "old-slug-2", "old-slug-3"]
```

**Rewrite Rules:**
```php
// Matches: /h3panos/{tour-slug}/
add_rewrite_rule('^h3panos/([^/]+)/?$', 'index.php?tour_slug=$matches[1]', 'top');

// Matches: /h3panos/{tour-slug}/{path}
add_rewrite_rule('^h3panos/([^/]+)/(.+)$', 'index.php?tour_slug=$matches[1]&tour_path=$matches[2]', 'top');
```

**Files Created:**
- `includes/class-h3tm-url-redirector.php` - NEW - URL redirect handling

**Files Modified:**
- `includes/class-h3tm-tour-metadata.php` - Added `change_slug()` method
- `h3-tour-management.php` - Initialize URL redirector

---

### 4. Embed Script Generation ✅

**Problem**: Clients need easy way to embed tours on their websites.

**Solution**: "Get Script" button generates ready-to-use iframe embed code with copy-to-clipboard.

**How It Works:**
1. User clicks "Get Script" button next to tour
2. Modal opens with two embed code options:
   - **Standard**: Fixed height (600px) iframe
   - **Responsive**: 16:9 aspect ratio container
3. One-click copy to clipboard using Clipboard API
4. Visual confirmation when code is copied

**Embed Code Templates:**

**Standard:**
```html
<iframe
  src="https://h3vt.com/h3panos/tour-slug/"
  width="100%"
  height="600"
  style="border: 0; border-radius: 8px; max-width: 100%;"
  allow="fullscreen; gyroscope; accelerometer"
  loading="lazy"
  title="Tour Name - 3D Tour">
</iframe>
```

**Responsive:**
```html
<!-- Responsive 3D Tour Embed -->
<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">
  <iframe
    src="https://h3vt.com/h3panos/tour-slug/"
    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
    allow="fullscreen; gyroscope; accelerometer"
    loading="lazy"
    title="Tour Name - 3D Tour">
  </iframe>
</div>
```

**Files Modified:**
- `includes/class-h3tm-admin.php` - Added `handle_get_embed_script()` AJAX handler
- `assets/js/admin-tour-features.js` - Embed modal UI and copy-to-clipboard
- `assets/js/admin.js` - Added "Get Script" button to tour listing (line 1150)
- `assets/css/admin.css` - Modal styling for embed code display

---

## 📁 Files Created

1. **`includes/class-h3tm-tour-metadata.php`** - Tour metadata CRUD operations
2. **`includes/class-h3tm-url-redirector.php`** - URL redirect and rewrite rules
3. **`assets/js/admin-tour-features.js`** - Update Tour and Get Script UI functionality
4. **`includes/class-h3tm-admin-handlers.php`** - Reference implementation (not used)

## 📝 Files Modified

1. **`includes/class-h3tm-activator.php`**
   - Added `h3tm_tour_metadata` table creation
   - Added `migrate_existing_tours()` method for backward compatibility

2. **`includes/class-h3tm-admin.php`**
   - Added `handle_update_tour()` AJAX handler
   - Added `handle_get_embed_script()` AJAX handler
   - Registered AJAX action hooks (lines 27-28)
   - Enqueued new JavaScript file (line 128)

3. **`includes/class-h3tm-s3-simple.php`**
   - Added `invalidate_tour_cache()` method (CloudFront invalidation)
   - Added `update_tour()` method (overwrite existing tour files)

4. **`h3-tour-management.php`**
   - Added requires for new classes (lines 48-49)
   - Initialized `H3TM_URL_Redirector()` in `h3tm_init()` (line 80)

5. **`assets/js/admin.js`**
   - Added "Update" button to tour listing (line 1148)
   - Added "Get Script" button to tour listing (line 1150)

6. **`assets/css/admin.css`**
   - Update modal warning styles
   - Embed modal container styles
   - Embed code textarea styles
   - Copy success button styles
   - Button color schemes for new actions

---

## 🔄 Migration Process

**Automatic Migration** runs on plugin activation:

1. Checks if migration already completed (`h3tm_metadata_migrated` option)
2. Lists all existing tours from S3
3. Creates metadata entry for each tour:
   - `tour_slug` = sanitized tour name (e.g., "bee-cave")
   - `display_name` = current display name (e.g., "Bee Cave")
   - `s3_folder` = current S3 path (e.g., "tours/Bee-Cave")
   - `url_history` = empty array `[]`
4. Sets `h3tm_metadata_migrated` option to `true`

**Backward Compatibility**: Tours without metadata entries will have metadata auto-created on first access using `get_or_create()` method.

---

## 🚀 User Workflow

### Updating a Tour:
1. Navigate to "3D Tours" → "Manage Tours"
2. Click "Update" button next to the tour
3. Select new ZIP file in modal
4. Click "Update Tour"
5. Progress indicator shows upload and processing
6. Page automatically refreshes when complete

### Generating Embed Script:
1. Navigate to "3D Tours" → "Manage Tours"
2. Click "Get Script" button next to the tour
3. Choose between Standard or Responsive embed
4. Click "Copy to Clipboard"
5. Paste into client's website HTML

### Renaming a Tour (New Behavior):
1. Click "Rename" button
2. Enter new display name
3. URL remains unchanged (no broken links!)
4. Old tour name is only changed in admin display

---

## 🔐 Security Features

- **Nonce Validation**: All AJAX requests validate WordPress nonce
- **Capability Checks**: Only users with `manage_options` can update/rename tours
- **Input Sanitization**: All user inputs sanitized before database storage
- **SQL Injection Prevention**: Using `$wpdb->prepare()` for all queries
- **XSS Prevention**: All output escaped with `esc_attr()`, `esc_url()`, `esc_html()`

---

## ⚡ Performance Optimizations

- **Database Indexes**: tour_slug and s3_folder columns indexed for fast lookups
- **CloudFront Invalidation**: Async operation, doesn't block user response
- **Tour List Cache**: 2-hour transient cache for S3 tour listings
- **Metadata Caching**: Minimize database queries with intelligent get_or_create()

---

## 🧪 Testing Checklist

Before release, test these scenarios:

- [ ] **Name/URL Decoupling**
  - [ ] Rename a tour, verify URL doesn't change
  - [ ] Access tour by old display name (should still work)
  - [ ] Rename multiple times, verify URL remains stable

- [ ] **Update Tour**
  - [ ] Upload new files for existing tour
  - [ ] Verify old files are deleted
  - [ ] Verify CloudFront cache is invalidated
  - [ ] Check updated_date timestamp changes

- [ ] **URL Redirects**
  - [ ] Change tour slug (when implemented)
  - [ ] Access old URL, verify 301 redirect
  - [ ] Multiple slug changes, verify redirect chain works

- [ ] **Embed Script**
  - [ ] Generate embed code for multiple tours
  - [ ] Copy standard embed to clipboard
  - [ ] Copy responsive embed to clipboard
  - [ ] Paste code in test HTML file, verify iframe loads

- [ ] **Edge Cases**
  - [ ] Special characters in tour names
  - [ ] Very long tour names
  - [ ] Concurrent update operations
  - [ ] Network interruption during update
  - [ ] CloudFront not configured (graceful degradation)

---

## 🐛 Known Limitations

1. **Rename** currently only updates display name, not URL slug
   - Future enhancement: Add "Change URL" feature to modify slug
   - This would use the url_history redirect system

2. **CloudFront invalidation** requires distribution ID in settings
   - If not configured, updates work but cache takes longer to clear
   - Consider async background invalidation for large tours

3. **Update operation** can take 30-60 seconds for large tours
   - UI shows progress but user must wait
   - Consider background processing for very large tours

---

## 💡 Future Enhancements

1. **Bulk Update**: Update multiple tours at once
2. **Tour Versioning**: Keep historical versions of tours
3. **Scheduled Updates**: Auto-update tours at specific times
4. **Advanced Embed Options**: Customize iframe parameters in UI
5. **URL Slug Editor**: Allow manual slug changes through admin UI
6. **Preview Mode**: Preview tour before updating live version

---

## 📚 Technical Implementation Details

### Class Relationships:
```
H3TM_Admin
  ├─ handle_update_tour() → H3TM_S3_Simple::update_tour()
  ├─ handle_get_embed_script() → H3TM_URL_Redirector::get_tour_url()
  └─ handle_rename_tour() → H3TM_Tour_Metadata::rename_tour()

H3TM_S3_Simple
  ├─ update_tour() → invalidate_tour_cache()
  ├─ invalidate_tour_cache() → AWS CloudFront API
  └─ upload_tour_to_s3_public() → uploads files

H3TM_URL_Redirector
  ├─ check_tour_redirect() → WordPress init hook
  ├─ add_rewrite_rules() → WordPress rewrite system
  └─ find_by_old_slug() → H3TM_Tour_Metadata::find_by_old_slug()

H3TM_Tour_Metadata
  ├─ create(), update(), delete() → CRUD operations
  ├─ get_by_slug(), get_by_display_name() → Lookups
  └─ find_by_old_slug() → url_history search
```

### AJAX Endpoints:
- `h3tm_update_tour` → Update existing tour files
- `h3tm_get_embed_script` → Generate iframe embed code

### JavaScript Components:
- `admin-tour-features.js` - Update and Get Script functionality
- Event delegation for dynamically loaded tour lists
- Modal system with ESC key support
- Clipboard API with fallback to execCommand

---

## 🔧 Deployment Steps

1. **Deactivate and Reactivate Plugin**
   - Go to Plugins → Deactivate "H3 Tour Management"
   - Reactivate the plugin
   - This runs `H3TM_Activator::activate()` which creates the new table and migrates data

2. **Verify Migration**
   - Check database for `wp_h3tm_tour_metadata` table
   - Verify all existing tours have metadata entries
   - Check `wp_options` for `h3tm_metadata_migrated = true`

3. **Test Features**
   - Try updating a tour
   - Generate an embed script
   - Test that all buttons appear and function correctly

4. **Clear Caches**
   - Clear WordPress object cache if using persistent caching
   - Clear CloudFront cache if configured
   - Flush rewrite rules: Settings → Permalinks → Save Changes

---

## 📊 Implementation Statistics

- **New Classes**: 2 (Tour_Metadata, URL_Redirector)
- **Modified Classes**: 4 (Activator, Admin, S3_Simple, main plugin file)
- **New JavaScript**: 1 file (~230 lines)
- **CSS Added**: ~115 lines for modals and buttons
- **Database Tables**: 1 new table with 7 columns
- **AJAX Endpoints**: 2 new handlers
- **Lines of Code**: ~800 total (PHP + JS + CSS)

---

## 🎉 Benefits

1. **Client-Friendly**: Tours can be renamed without breaking distributed links
2. **Efficient Updates**: Overwrite functionality avoids duplicate uploads and saves storage
3. **SEO-Preserved**: Old URLs automatically redirect (301) to new locations
4. **Easy Distribution**: One-click embed code generation for clients
5. **Professional UX**: Polished modals, progress indicators, and copy feedback
6. **Backward Compatible**: Existing tours work seamlessly via automatic migration

---

## 🆘 Support & Troubleshooting

### Issue: Tours don't show Update/Get Script buttons
**Solution**: Clear browser cache and hard refresh (Ctrl+F5)

### Issue: CloudFront invalidation fails
**Solution**: Check CloudFront Distribution ID in S3 Settings page

### Issue: Embed script doesn't copy
**Solution**: Use HTTPS site (Clipboard API requires secure context)

### Issue: Migration didn't run
**Solution**: Deactivate and reactivate plugin to trigger activation hook

### Issue: URL redirects not working
**Solution**: Go to Settings → Permalinks → Save Changes (flushes rewrite rules)

---

**Implementation Date**: 2025-10-08
**Version**: 2.5.0
**Developer**: Jeff Karbowski / H3 Photography

