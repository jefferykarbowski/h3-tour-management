# Fix Local Sites Configuration Issues

## Issue 1: PHP Upload Limits Too Low

Your current limits:
- **Upload max filesize: 2M** ← This is why uploads fail!
- **Post max size: 8M** ← Too small for chunks
- **Memory limit: 128M** ← Should be higher

### Fix in Local Sites:

1. **Open Local Sites**
2. **Right-click on your "h3vt" site**
3. **Go to "Open Site Shell"**
4. **Find PHP config:**
   ```bash
   php --ini
   ```
   This will show you where php.ini is located

5. **Edit php.ini** (the path will be something like):
   ```bash
   nano /etc/php/8.3/conf.d/php.ini
   ```

6. **Add/Update these values:**
   ```ini
   ; Increase upload limits
   upload_max_filesize = 1024M
   post_max_size = 1024M
   memory_limit = 512M
   max_execution_time = 0
   max_input_time = 600
   
   ; For chunked uploads
   max_file_uploads = 100
   ```

7. **Alternative Method - Through Local Sites UI:**
   - Stop your site
   - Go to site settings
   - Look for PHP settings or Advanced
   - Adjust the values there
   - Restart the site

## Issue 2: MySQL Extension Missing

The error "missing the MySQL extension" means PHP can't connect to MySQL.

### Fix in Local Sites:

1. **Stop the site** in Local Sites
2. **Check PHP version** - you're using 8.3.10
3. **Go to site settings**
4. **Try switching to:**
   - PHP 8.1.x or 8.2.x (more stable)
   - Or reinstall PHP 8.3

5. **If that doesn't work:**
   - Open Site Shell
   - Run: `php -m | grep mysql`
   - Should show: mysqli, mysqlnd, pdo_mysql

## Quick Fix Using .htaccess

While Local Sites is being fixed, add this to your `.htaccess` file in WordPress root:

```apache
# Increase PHP Limits
<IfModule mod_php.c>
    php_value upload_max_filesize 1024M
    php_value post_max_size 1024M
    php_value memory_limit 512M
    php_value max_execution_time 600
    php_value max_input_time 600
</IfModule>
```

## Or Use wp-config.php

Add to the top of `wp-config.php`:

```php
// Increase memory limit
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Increase upload size
@ini_set('upload_max_filesize', '1024M');
@ini_set('post_max_size', '1024M');
@ini_set('max_execution_time', '600');
```

## The Real Problem

**Your 2MB upload limit** is causing the chunk upload to fail. Each chunk is 1MB, but with overhead, it exceeds 2MB, causing failure at chunk 938.

## Immediate Solution

1. **Fix the MySQL error first** (change PHP version in Local Sites)
2. **Then increase upload limits** as shown above
3. **Restart Local Sites**
4. **Try upload again**

## If Local Sites Won't Cooperate

Sometimes Local Sites has issues with custom PHP settings. You can:

1. **Export your site** from Local Sites
2. **Use XAMPP or WAMP** instead (they're easier to configure)
3. **Or use Local Sites "Custom" environment** which gives more control

## Test After Fixing

Run the diagnostic again:
```bash
cd "C:\Users\Jeff\Local Sites\h3vt\app\public\wp-content\plugins\h3-tour-management\tools"
php fix-upload-issues.php
```

You should see:
- Upload max filesize: 1024M
- Post max size: 1024M
- Memory limit: 512M

Then uploads will work!