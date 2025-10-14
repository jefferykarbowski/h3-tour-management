# React Uploader Debugging Guide

## Issue Summary
User reported that the React uploader component isn't showing on the admin page.

## Fixes Applied

### 1. Nonce Mismatch Fixed
**Problem**: React component was using nonce `'h3tm_upload_tour'` but AJAX handler expected `'h3tm_ajax_nonce'`

**Fix Applied**: Updated `includes/class-h3tm-react-uploader.php:65` to use `'h3tm_ajax_nonce'`

### 2. CSS Positioning Fixed
**Added styles** to `assets/css/admin.css`:
- `.h3tm-admin-container` with `position: relative`
- `#h3tm-tour-uploader-root` with proper z-index
- `.h3tm-section` with `overflow: visible`

## Debugging Steps

### Step 1: Check if React Container Exists
Open browser console and run:
```javascript
console.log(document.getElementById('h3tm-tour-uploader-root'));
```
**Expected**: Should show the div element
**If null**: The PHP isn't rendering the container

### Step 2: Check if Scripts are Loaded
```javascript
console.log(typeof window.h3tmData);
```
**Expected**: Should show "object"
**If undefined**: Scripts aren't being enqueued properly

### Step 3: Check for JavaScript Errors
Open browser console (F12) and look for any red error messages.

Common errors:
- `Cannot read property 'ajaxUrl' of undefined` → h3tmData not loaded
- `React is not defined` → React bundle didn't load
- CORS errors → File path issues

### Step 4: Verify Built Files Exist
Check that these files exist:
- `assets/dist/tour-uploader.js` (376KB)
- `assets/dist/tour-uploader.css` (20KB)

### Step 5: Check Network Tab
1. Open DevTools → Network tab
2. Refresh the page
3. Filter by "JS" and "CSS"
4. Look for:
   - `tour-uploader.js` - Should be 200 status
   - `tour-uploader.css` - Should be 200 status

**If 404**: File paths are wrong or files don't exist
**If loaded**: Check console for execution errors

### Step 6: Verify WordPress Hook
Check page source (Ctrl+U) and search for:
```html
<div id="h3tm-tour-uploader-root"></div>
```
**If not found**: PHP isn't rendering the container

Also search for:
```html
<script id='h3tm-tour-uploader-js'
```
**If not found**: Scripts aren't being enqueued

## Manual Testing

### Test 1: Component Mounting
Open console and run:
```javascript
// Force mount if React loaded
if (window.React) {
  console.log('React is available');
  console.log('h3tmData:', window.h3tmData);
}
```

### Test 2: Check AJAX Endpoint
```javascript
// Test if AJAX endpoint works
fetch(window.h3tmData.ajaxUrl, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: `action=h3tm_upload_tour&nonce=${window.h3tmData.nonce}`
}).then(r => r.json()).then(console.log);
```
**Expected**: Should get error about missing tour_name (that's good!)
**If error**: Network or AJAX handler issue

## Common Issues and Solutions

### Issue: Component Container Not Visible
**Symptom**: Container exists but nothing renders

**Solution**:
```css
/* Add to admin.css if missing */
#h3tm-tour-uploader-root {
    min-height: 200px;
    margin-bottom: 30px;
    display: block !important;
}
```

### Issue: Scripts Not Loading
**Symptom**: 404 errors in Network tab

**Check**:
1. Build files exist in `assets/dist/`
2. Plugin URL constant is correct
3. File permissions allow reading

**Rebuild**:
```bash
cd frontend
npm run build
```

### Issue: White Screen / React Error
**Symptom**: Console shows React errors

**Common causes**:
- React version mismatch
- Missing dependencies
- Build configuration issue

**Solution**: Rebuild frontend
```bash
cd frontend
rm -rf node_modules
npm install
npm run build
```

### Issue: Table Positioning Problems
**Symptom**: Available Tours table goes off screen

**Check CSS**:
```css
.h3tm-admin-container {
    position: relative;
    max-width: 1200px;
    overflow: visible;
}

.h3tm-section {
    position: relative;
    overflow: visible;
}
```

## Quick Checklist

- [ ] Built files exist in `assets/dist/`
- [ ] React container div is in page source
- [ ] Scripts enqueued (check page source)
- [ ] No JavaScript console errors
- [ ] Network tab shows 200 for JS/CSS
- [ ] `window.h3tmData` is defined
- [ ] Nonce matches between frontend and backend

## Next Steps

If component still doesn't show after checking all above:

1. **Clear all caches**: Browser, WordPress, CDN
2. **Disable other plugins**: Check for conflicts
3. **Check PHP error logs**: Look for PHP warnings/errors
4. **Test in different browser**: Rule out browser-specific issues
5. **Check file permissions**: Ensure web server can read dist files

## File Locations Reference

- React Component: `frontend/src/components/TourUpload.tsx`
- Entry Point: `frontend/src/main.tsx`
- Build Config: `frontend/vite.config.ts`
- WP Integration: `includes/class-h3tm-react-uploader.php`
- Page Renderer: `includes/traits/trait-h3tm-page-renderers.php:30`
- CSS Styles: `assets/css/admin.css`
- Built Assets: `assets/dist/tour-uploader.{js,css}`

## Support Commands

```bash
# Rebuild React app
cd frontend && npm run build

# Check if files exist
ls -lh assets/dist/

# Check file permissions
stat assets/dist/tour-uploader.js

# Watch for errors while building
cd frontend && npm run build 2>&1 | tee build.log
```
