# H3 Tour Management - React Frontend

Modern React-based tour uploader component with TypeScript, Tailwind CSS, and shadcn/ui.

## Features

- 🎨 Clean, minimalist design with subtle blue accents
- 📁 Drag-and-drop file upload with .zip validation
- 📊 Animated progress bar and processing dialog
- ✅ Upload completion animation
- 🎯 TypeScript for type safety
- 🌙 Dark mode support
- ♿ Accessible UI components

## Setup

### 1. Install Dependencies

```bash
cd frontend
npm install
```

### 2. Development

Run the development server:

```bash
npm run dev
```

This will start Vite dev server at http://localhost:5173

### 3. Build for Production

Build the optimized bundle for WordPress:

```bash
npm run build
```

This creates production-ready files in `../assets/dist/`:
- `tour-uploader.js` - Compiled JavaScript bundle
- `tour-uploader.css` - Compiled CSS with Tailwind

### 4. Type Checking

Check TypeScript types without building:

```bash
npm run type-check
```

## WordPress Integration

The component is automatically integrated when you build it. The build output goes to:
```
assets/dist/tour-uploader.js
assets/dist/tour-uploader.css
```

The `H3TM_React_Uploader` class (in `includes/class-h3tm-react-uploader.php`) handles:
- Enqueuing scripts and styles
- Passing WordPress data to React via `wp_localize_script`
- Rendering the uploader container

### Using in Admin Pages

In your admin page rendering method:

```php
// In your page renderer trait or method
public function render_upload_page() {
    ?>
    <div class="wrap">
        <h1>Upload 3D Tour</h1>
        <?php H3TM_React_Uploader::render_uploader(); ?>
    </div>
    <?php
}
```

## Component Structure

```
src/
├── components/
│   ├── ui/              # shadcn/ui base components
│   │   ├── button.tsx
│   │   ├── input.tsx
│   │   ├── label.tsx
│   │   ├── dialog.tsx
│   │   └── progress.tsx
│   └── TourUpload.tsx   # Main uploader component
├── lib/
│   └── utils.ts         # Utility functions
├── index.css            # Tailwind styles
└── main.tsx             # Entry point
```

## Customization

### Colors

Edit `tailwind.config.js` to change theme colors. The component uses:
- Primary color for buttons and progress
- Blue accent (#3B82F6) for highlights
- Muted colors for secondary elements

### Upload Handling

The upload completion callback sends data to WordPress via AJAX:

```typescript
// In main.tsx
onUploadComplete={(tourName, file) => {
  const formData = new FormData();
  formData.append('action', 'h3tm_upload_tour');
  formData.append('tour_name', tourName);
  formData.append('tour_file', file);
  formData.append('nonce', h3tmData.nonce);

  fetch(h3tmData.ajaxUrl, {
    method: 'POST',
    body: formData,
  })
  // Handle response...
}
```

Make sure your WordPress AJAX handler (`handle_upload_tour` in `class-h3tm-admin.php`) processes this correctly.

## Using WordPress's Built-in React (Optional)

To use WordPress's bundled React instead of including your own:

1. Update `vite.config.ts` to externalize React:

```typescript
build: {
  rollupOptions: {
    external: ['react', 'react-dom'],
    output: {
      globals: {
        react: 'wp.element.createElement',
        'react-dom': 'wp.element.render'
      }
    }
  }
}
```

2. In `class-h3tm-react-uploader.php`, uncomment the `enqueue_scripts_wp_react` method

This reduces bundle size but requires WordPress 5.0+ (Gutenberg).

## Dependencies

### Core
- React 18.3
- TypeScript 5.6
- Vite 5.4
- Tailwind CSS 3.4

### UI Components
- @radix-ui/react-* - Accessible component primitives
- framer-motion - Smooth animations
- lucide-react - Icon library
- react-dropzone - File upload handling
- shadcn/ui - Component system

### Utilities
- clsx & tailwind-merge - Class name utilities
- class-variance-authority - Component variants

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+

## Development Notes

- The component uses TypeScript for type safety
- All UI components are from shadcn/ui for consistency
- Animations use framer-motion for smooth transitions
- The grid pattern background is purely decorative
- Progress simulation can be replaced with real upload progress

## Troubleshooting

**Build files not found:**
- Run `npm install && npm run build` in the frontend directory
- Check that `assets/dist/` contains `tour-uploader.js` and `tour-uploader.css`

**Styles not working:**
- Ensure Tailwind CSS is properly configured
- Check that `tour-uploader.css` is enqueued before the JS
- Clear browser cache

**Upload not working:**
- Check browser console for JavaScript errors
- Verify `h3tmData` is properly localized
- Ensure AJAX handler is registered in WordPress
- Check nonce verification in backend
