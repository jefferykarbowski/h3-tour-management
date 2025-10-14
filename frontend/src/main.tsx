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
          console.log('Tour uploaded:', tourName, file);
          // This will be replaced with actual WordPress AJAX call
          const formData = new FormData();
          formData.append('action', 'h3tm_upload_tour');
          formData.append('tour_name', tourName);
          formData.append('tour_file', file);
          formData.append('nonce', (window as any).h3tmData?.nonce || '');

          fetch((window as any).h3tmData?.ajaxUrl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                alert('Tour uploaded successfully!');
              } else {
                alert('Upload failed: ' + (data.message || 'Unknown error'));
              }
            })
            .catch((error) => {
              console.error('Upload error:', error);
              alert('Upload failed. Please try again.');
            });
        }}
      />
    </React.StrictMode>
  );
  console.log('Tour Uploader mounted successfully');
} else {
  console.error('Could not find h3tm-tour-uploader-root container!');
}
