# How to Add New AJAX Handlers to class-h3tm-admin.php

## Overview
This guide shows you how to manually add the 4 new AJAX handlers for Update Tour, Get Script, Change URL, and Rebuild Metadata features.

---

## STEP 1: Register AJAX Actions

**Location:** `includes/class-h3tm-admin.php` - In the `__construct()` method

**Find this code (around line 26):**
```php
add_action('wp_ajax_h3tm_migrate_tour_to_s3', array($this, 'handle_migrate_tour_to_s3'));
// h3tm_list_s3_tours is handled by H3TM_S3_Simple class
```

**Add these 4 lines AFTER it:**
```php
add_action('wp_ajax_h3tm_update_tour', array($this, 'handle_update_tour'));
add_action('wp_ajax_h3tm_get_embed_script', array($this, 'handle_get_embed_script'));
add_action('wp_ajax_h3tm_change_tour_url', array($this, 'handle_change_tour_url'));
add_action('wp_ajax_h3tm_rebuild_metadata', array($this, 'handle_rebuild_metadata'));
```

---

## STEP 2: Add Handler Methods

**Location:** `includes/class-h3tm-admin.php` - At the END of the class

**Find the LAST closing brace:**
```php
        rmdir($dir);
    }
}  ← This is the class closing brace
```

**Paste the 4 methods from `docs/HANDLERS_TO_ADD.php` BEFORE that final `}`**

The methods to add are:
1. `handle_update_tour()` - Updates existing tour files
2. `handle_get_embed_script()` - Generates iframe embed code
3. `handle_change_tour_url()` - Modifies URL slug with redirects
4. `handle_rebuild_metadata()` - Rebuilds metadata table

---

## STEP 3: Enqueue JavaScript File

**Location:** `includes/class-h3tm-admin.php` - In the `enqueue_admin_scripts()` method

**Find this code (around line 129):**
```php
wp_enqueue_script('h3tm-admin', H3TM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'select2'), H3TM_VERSION, true);
wp_enqueue_style('h3tm-admin', H3TM_PLUGIN_URL . 'assets/css/admin.css', array(), H3TM_VERSION);
```

**Add this line AFTER the first enqueue_script:**
```php
wp_enqueue_script('h3tm-tour-features', H3TM_PLUGIN_URL . 'assets/js/admin-tour-features.js', array('jquery', 'h3tm-admin'), H3TM_VERSION, true);
```

---

## STEP 4: Update Settings Page (Optional)

**To rename "S3 Settings" to "Settings":**

**Find (around line 88):**
```php
__('S3 Upload Settings', 'h3-tour-management'),
__('S3 Settings', 'h3-tour-management'),
```

**Replace with:**
```php
__('Plugin Settings', 'h3-tour-management'),
__('Settings', 'h3-tour-management'),
```

**Find (around line 740):**
```php
<h1><?php _e('S3 & CloudFront Settings', 'h3-tour-management'); ?></h1>
```

**Replace with:**
```php
<h1><?php _e('Plugin Settings', 'h3-tour-management'); ?></h1>
```

---

## Verification

After adding all code:

1. **Check PHP syntax:**
   ```bash
   php -l includes/class-h3tm-admin.php
   ```

2. **Hard refresh browser:**
   Press `Ctrl+Shift+R`

3. **Test features:**
   - Click "Update" on a tour
   - Click "Get Script" on a tour
   - Click "Change URL" on a tour
   - Go to Settings → Click "Rebuild Tour Metadata"

---

## Quick Reference

**Files to edit:**
- `includes/class-h3tm-admin.php` (3 locations)

**Files created (already done):**
- `assets/js/admin-tour-features.js` ✅
- `includes/class-h3tm-tour-metadata.php` ✅
- `includes/class-h3tm-url-redirector.php` ✅

**No other files need editing!**

---

## If You Get Stuck

Run the SQL command to fix delete immediately:
```sql
DELETE FROM wp_options WHERE option_name = 'h3tm_metadata_migrated';
```

Then refresh Tours page - delete will work even without the new features!
