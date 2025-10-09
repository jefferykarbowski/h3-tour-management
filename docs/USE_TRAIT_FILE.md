# Simple Solution: Use Trait File

Instead of editing the huge class-h3tm-admin.php file, I've created a small separate trait file with just the 4 new handlers.

## Files Created
- `includes/class-h3tm-new-handlers.php` - Contains all 4 handlers (258 lines)

## How to Use

### Step 1: Include the trait file

**Edit:** `includes/class-h3tm-admin.php`

**At the top of the file (after the opening `<?php` and before `class H3TM_Admin {`):**

Add this line:
```php
require_once __DIR__ . '/class-h3tm-new-handlers.php';
```

### Step 2: Use the trait in the class

**Find:** `class H3TM_Admin {` (around line 5)

**Change to:**
```php
class H3TM_Admin {
    use H3TM_New_Handlers;
```

### Step 3: Register the AJAX actions

**In the `__construct()` method, after line 26, add:**
```php
add_action('wp_ajax_h3tm_update_tour', array($this, 'handle_update_tour'));
add_action('wp_ajax_h3tm_get_embed_script', array($this, 'handle_get_embed_script'));
add_action('wp_ajax_h3tm_change_tour_url', array($this, 'handle_change_tour_url'));
add_action('wp_ajax_h3tm_rebuild_metadata', array($this, 'handle_rebuild_metadata'));
```

### Step 4: Enqueue the JavaScript

**Find (around line 129):**
```php
wp_enqueue_script('h3tm-admin', H3TM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'select2'), H3TM_VERSION, true);
```

**Add this line after it:**
```php
wp_enqueue_script('h3tm-tour-features', H3TM_PLUGIN_URL . 'assets/js/admin-tour-features.js', array('jquery', 'h3tm-admin'), H3TM_VERSION, true);
```

## That's It!

Only **4 simple edits** to one file (class-h3tm-admin.php):
1. Include trait file (1 line at top)
2. Use trait (1 word in class declaration)
3. Register actions (4 lines in constructor)
4. Enqueue script (1 line in enqueue method)

All the complex handler code is in the separate trait file!

## Verify

After editing, check syntax:
```bash
php -l includes/class-h3tm-admin.php
php -l includes/class-h3tm-new-handlers.php
```

Then hard refresh browser and test the features!
