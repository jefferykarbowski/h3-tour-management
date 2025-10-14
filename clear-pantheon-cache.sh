#!/bin/bash
# Clear all Pantheon caches for h3vt site

echo "Clearing Dev environment cache..."
terminus env:clear-cache h3vt.dev

echo "Clearing Test environment cache..."
terminus env:clear-cache h3vt.test

echo "Cache cleared. Wait 30 seconds then test the admin page."
