# Pantheon .htaccess Deployment Instructions

## Overview
The h3panos analytics wrapper is ready for deployment to Pantheon.io. The .htaccess redirect should work properly on Pantheon's production environment.

## Deployment Steps

### 1. Upload Files to Pantheon
Upload these files to your Pantheon site:
- `/h3panos-wrapper.php` (in site root)
- Updated `/wp-content/.htaccess` (with h3panos rules)

### 2. Updated .htaccess Content
Add this to the TOP of your WordPress `.htaccess` file (BEFORE the WordPress rules):

```apache
# H3Panos Analytics Wrapper Rules - MUST be before WordPress rules
<IfModule mod_rewrite.c>
RewriteEngine On

# Redirect h3panos tour index.htm files to PHP wrapper
RewriteRule ^h3panos/([^/]+)/index\.htm$ /h3panos-wrapper.php?tour=$1 [L,QSA]

# Handle h3panos direct folder access
RewriteRule ^h3panos/([^/]+)/?$ /h3panos-wrapper.php?tour=$1 [L,QSA]
</IfModule>
```

### 3. Test on Pantheon
After deployment, test these URLs:
- `https://yoursite.com/h3panos/Arden-Farmington/`
- `https://yoursite.com/h3panos/Arden-Farmington/index.htm`

Both should redirect to the wrapper and inject GA4 analytics.

### 4. Verify Analytics
Check the page source - you should see:
```html
<!-- Google Analytics 4 - Injected by h3panos-wrapper.php -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-08Q1M637NJ"></script>
<script>
  gtag('config', 'G-08Q1M637NJ', {
    page_title: 'Arden-Farmington',
    // ... more tracking code
  });
</script>
```

## Why This Works on Pantheon but Not Locally

- **Pantheon**: Full Apache mod_rewrite support with proper .htaccess processing
- **Local (Flywheel)**: Often has simplified .htaccess handling that doesn't process subdirectory rules properly

## Troubleshooting on Pantheon

If redirects don't work:

1. **Check file permissions**: Ensure .htaccess is readable (644)
2. **Clear caches**: Clear all Pantheon caches (Redis, Varnish, etc.)
3. **Test direct wrapper**: Try `/h3panos-wrapper.php?tour=Arden-Farmington` directly
4. **Check Apache logs**: Review error logs in Pantheon dashboard

## Expected Results

Once working, all h3panos tours will automatically:
- ✅ Inject GA4 tracking code with tour-specific page titles
- ✅ Track `tour_view` events with tour names
- ✅ Maintain full 3DVista functionality
- ✅ Work with existing tour URLs unchanged