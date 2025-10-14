# React Tour Uploader - Integration Guide

## Overview

A modern, animated React-based tour uploader component has been created to replace the existing PHP uploader. The component features:

- ✨ Clean minimalist design with subtle blue accents
- 📁 Drag-and-drop file upload with .zip validation
- 📊 Animated progress bar during upload
- ✅ Success animation on completion
- 🎯 Built with TypeScript, Tailwind CSS, and shadcn/ui
- 🌙 Dark mode support

## Quick Start

### 1. Install Dependencies

```bash
cd frontend
npm install
```

### 2. Build the Component

```bash
npm run build
```

This creates optimized production files:
- `assets/dist/tour-uploader.js` - Bundled JavaScript
- `assets/dist/tour-uploader.css` - Compiled CSS

### 3. Integration is Complete!

The React uploader is already integrated into the WordPress plugin:

- ✅ `class-h3tm-react-uploader.php` - Handles script enqueuing
- ✅ Plugin file updated to load the React uploader class
- ✅ Ready to use in any admin page

## Using in Admin Pages

To add the uploader to an admin page, use the renderer method:

```php
// In your page rendering trait or method
public function render_upload_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Upload 3D Tour', 'h3-tour-management'); ?></h1>
        <?php H3TM_React_Uploader::render_uploader(); ?>
    </div>
    <?php
}
```

The React component will mount to the `#h3tm-tour-uploader-root` div.

## How It Works

### Frontend Flow

1. **User inputs tour name** - Text field for naming the tour
2. **User uploads .zip file** - Drag-and-drop or click to browse
3. **Upload button activates** - Only when both name and file are provided
4. **Progress dialog shows** - Animated progress bar from 0-100%
5. **Success animation plays** - Green checkmark with tour name
6. **Data sent to WordPress** - FormData POST to admin-ajax.php

### Backend Integration

The React component sends upload data via AJAX:

```javascript
// From main.tsx
const formData = new FormData();
formData.append('action', 'h3tm_upload_tour');
formData.append('tour_name', tourName);
formData.append('tour_file', file);
formData.append('nonce', h3tmData.nonce);

fetch(h3tmData.ajaxUrl, {
  method: 'POST',
  body: formData,
})
```

Your existing `handle_upload_tour` method in `class-h3tm-admin.php` receives this data:

```php
public function handle_upload_tour() {
    check_ajax_referer('h3tm_upload_tour', 'nonce');

    $tour_name = sanitize_text_field($_POST['tour_name']);
    $file = $_FILES['tour_file'];

    // Your existing upload logic...
}
```

## WordPress Data Passed to React

The `H3TM_React_Uploader` class passes WordPress data to JavaScript via `wp_localize_script`:

```php
wp_localize_script('h3tm-tour-uploader', 'h3tmData', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('h3tm_upload_tour'),
    'maxFileSize' => wp_max_upload_size(),
    'uploadDir' => wp_upload_dir()['baseurl'] . '/h3-tours/',
));
```

Access in JavaScript: `window.h3tmData`

## Development Workflow

### Development Mode

Run Vite dev server for hot-reload during development:

```bash
cd frontend
npm run dev
```

Opens at http://localhost:5173 with live preview.

### Production Build

Build optimized bundle for WordPress:

```bash
cd frontend
npm run build
```

Outputs to `assets/dist/` for WordPress to load.

### Type Checking

Verify TypeScript types:

```bash
npm run type-check
```

## Customization

### Change Colors

Edit `frontend/tailwind.config.js`:

```javascript
theme: {
  extend: {
    colors: {
      primary: '...',  // Main button color
      accent: '...',   // Hover states
      // ... more colors
    }
  }
}
```

### Modify Upload Behavior

Edit `frontend/src/main.tsx` - the `onUploadComplete` callback:

```typescript
onUploadComplete={(tourName, file) => {
  // Your custom upload logic
  // Current implementation: FormData POST to admin-ajax.php
}}
```

### Add Validation

Edit `frontend/src/components/TourUpload.tsx`:

```typescript
const handleFileChange = (newFiles: File[]) => {
  const selectedFile = newFiles[0];

  // Add custom validation
  if (selectedFile.size > MAX_SIZE) {
    alert('File too large!');
    return;
  }

  if (selectedFile.name.endsWith('.zip')) {
    setFile(selectedFile);
  }
};
```

## Optional: Use WordPress's Built-in React

To reduce bundle size, you can use WordPress's bundled React (from Gutenberg):

### 1. Update Vite Config

Edit `frontend/vite.config.ts`:

```typescript
build: {
  rollupOptions: {
    external: ['react', 'react-dom'],
    output: {
      globals: {
        react: 'wp.element',
        'react-dom': 'wp.element'
      }
    }
  }
}
```

### 2. Switch to WordPress React

In `includes/class-h3tm-react-uploader.php`, comment out `enqueue_scripts()` and uncomment the alternative `enqueue_scripts_wp_react()` method.

**Trade-offs:**
- ✅ Smaller bundle size (~150KB reduction)
- ❌ Requires WordPress 5.0+ (Gutenberg)
- ❌ Limited to WordPress's React version
- ❌ May have compatibility issues with newer React features

## File Structure

```
h3-tour-management/
├── frontend/                   # React source code
│   ├── src/
│   │   ├── components/
│   │   │   ├── ui/            # shadcn/ui components
│   │   │   └── TourUpload.tsx # Main component
│   │   ├── lib/
│   │   │   └── utils.ts       # Utilities
│   │   ├── index.css          # Tailwind styles
│   │   └── main.tsx           # Entry point
│   ├── package.json
│   ├── vite.config.ts
│   ├── tailwind.config.js
│   └── tsconfig.json
├── assets/
│   └── dist/                  # Build output (generated)
│       ├── tour-uploader.js
│       └── tour-uploader.css
└── includes/
    └── class-h3tm-react-uploader.php  # WordPress integration
```

## Troubleshooting

### Build Files Missing

**Error:** "React uploader assets not built" admin notice

**Solution:**
```bash
cd frontend
npm install
npm run build
```

Verify files exist:
- `assets/dist/tour-uploader.js`
- `assets/dist/tour-uploader.css`

### Styles Not Loading

**Issue:** Component appears unstyled

**Checklist:**
- [ ] Run `npm run build` to compile Tailwind CSS
- [ ] Check `tour-uploader.css` is in `assets/dist/`
- [ ] Verify CSS is enqueued before JS in `class-h3tm-react-uploader.php`
- [ ] Clear browser cache

### Upload Not Working

**Issue:** Upload button does nothing or errors

**Checklist:**
- [ ] Open browser console - check for JavaScript errors
- [ ] Verify `h3tmData` exists: `console.log(window.h3tmData)`
- [ ] Check AJAX handler is registered: `wp_ajax_h3tm_upload_tour`
- [ ] Verify nonce: `wp_create_nonce('h3tm_upload_tour')`
- [ ] Check file size limits: `wp_max_upload_size()`

### TypeScript Errors

**Issue:** Build fails with type errors

**Solution:**
```bash
cd frontend
npm run type-check  # See all type errors
```

Fix types in source files, then rebuild.

## Next Steps

1. **Replace Old Uploader** - Update your admin page to use `H3TM_React_Uploader::render_uploader()`
2. **Test Upload Flow** - Verify file uploads work end-to-end
3. **Customize Design** - Adjust colors/branding to match your theme
4. **Add Features** - Extend component with additional functionality

## Support

For detailed component documentation, see `frontend/README.md`.

For questions or issues, check:
- React component source: `frontend/src/components/TourUpload.tsx`
- WordPress integration: `includes/class-h3tm-react-uploader.php`
- Build configuration: `frontend/vite.config.ts`
