import React from 'react';
import { createRoot } from 'react-dom/client';
import { ToursTable } from './components/ToursTable';
import './index.css';

console.log('Tours Table script loaded');

// Mount the tours table component to the DOM
const container = document.getElementById('h3tm-tours-table-root');
console.log('Looking for tours table container:', container);
if (container) {
  console.log('Mounting Tours Table component');
  const root = createRoot(container);
  root.render(
    <React.StrictMode>
      <ToursTable
        onRefresh={() => {
          console.log('Tours table refreshed');
          // Trigger any WordPress-specific refresh logic if needed
        }}
      />
    </React.StrictMode>
  );
  console.log('Tours Table mounted successfully');
} else {
  console.error('Could not find h3tm-tours-table-root container!');
}
