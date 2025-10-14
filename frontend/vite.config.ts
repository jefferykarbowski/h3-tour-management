import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': resolve(__dirname, './src'),
    },
  },
  build: {
    outDir: '../assets/dist',
    emptyOutDir: true,
    cssCodeSplit: true,
    rollupOptions: {
      input: {
        'tour-uploader': resolve(__dirname, 'src/main.tsx'),
        'tours-table': resolve(__dirname, 'src/tours-table.tsx'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          // Map CSS files to their corresponding component names
          const name = assetInfo.name || '';
          if (name.endsWith('.css')) {
            // Check which entry point this CSS belongs to
            if (name.includes('tour-uploader') || name.startsWith('tour-uploader')) {
              return 'tour-uploader.css';
            }
            if (name.includes('tours-table') || name.startsWith('tours-table')) {
              return 'tours-table.css';
            }
            // For any other CSS from main entries, use the base name without hash
            return '[name].css';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
