# GA4 Shortcode Migration

## Issue
The `[tour_analytics_display]` shortcode was using the deprecated Google Analytics v3 API (Universal Analytics), which has been shut down. This was causing 502 errors when trying to access endpoints like:
- `https://content.googleapis.com/analytics/v3/data/ga?...`

## Solution
Created a new GA4-compatible version of the shortcode (`class-h3tm-shortcodes-v2.php`) that:

1. **Uses GA4 API server-side** - No more client-side JavaScript API calls
2. **Proper authentication** - Uses the same service account as email analytics
3. **Better performance** - Data is fetched server-side and cached
4. **Simplified interface** - Clean HTML/CSS without Vue.js dependency

## Changes Made

### Old Implementation (v3 API)
- Used deprecated `ga:` prefixed metrics
- Client-side JavaScript with Google Analytics Embed API
- Required complex authentication flow
- Depended on Vue.js for rendering

### New Implementation (GA4 API)
- Uses modern GA4 dimensions and metrics
- Server-side PHP implementation
- Simple HTML output with native form controls
- Shows:
  - Total Views (Sessions)
  - Total Visitors
  - Total Photos Viewed (Events)
  - Average Session Duration
  - Top Pages
  - Traffic Sources

## Usage
The shortcode usage remains the same:
```
[tour_analytics_display]
```

## Features
- Tour selection dropdown (for users with multiple tours)
- Date range selection (7 days, 30 days, 90 days, 1 year)
- Responsive design
- Error handling with clear messages
- No external JavaScript dependencies

## Migration Notes
- The new implementation is backward compatible
- No changes needed to existing pages using the shortcode
- Data is fetched in real-time from GA4
- Performance is improved due to server-side rendering