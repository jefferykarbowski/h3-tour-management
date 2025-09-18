# H3 Tour Management - Project Overview

## Purpose
WordPress plugin for managing 3D virtual tours with analytics integration and email notifications.

## Tech Stack
- **Platform**: WordPress plugin
- **Language**: PHP 7.4+
- **Dependencies**: 
  - Google Analytics 4 API
  - Google API Client Library (via Composer)
  - Select2 for enhanced user interface
- **Structure**: Object-oriented PHP with WordPress hooks

## Key Features
- 3D tour upload and management with chunked uploads
- User assignment and access control
- Google Analytics 4 integration for tour analytics
- Automated email analytics (Daily/Weekly/Monthly)
- Shortcode-based analytics display for frontend
- Tour renaming and deletion capabilities

## Current Analytics Implementation
- **Email Analytics**: Sends regular reports with specific metrics
- **Frontend Display**: `[tour_analytics_display]` shortcode shows analytics to logged-in users
- **GA4 Integration**: Real-time data from Google Analytics 4 API
- **User-specific**: Only shows analytics for tours assigned to the logged-in user