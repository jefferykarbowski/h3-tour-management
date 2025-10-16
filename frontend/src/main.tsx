import React from 'react';
import { createRoot } from 'react-dom/client';
import { TourUpload } from './components/TourUpload';
import './index.css';

console.log('Tour Uploader script loaded');

// Mount the component to the DOM
const container = document.getElementById('h3tm-tour-uploader-root');
console.log('Looking for uploader container:', container);
if (container) {
  console.log('Mounting Tour Uploader component');
  const root = createRoot(container);
  root.render(
    <React.StrictMode>
      <TourUpload
        onUploadComplete={(tourName, file) => {
          console.log('Tour uploaded successfully:', tourName, file);
          // Tour already uploaded to S3 and processed by Lambda
          // Reload page to show updated tours table
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        }}
      />
    </React.StrictMode>
  );
  console.log('Tour Uploader mounted successfully');
} else {
  console.error('Could not find h3tm-tour-uploader-root container!');
}
