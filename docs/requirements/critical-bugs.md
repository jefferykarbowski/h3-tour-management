# H3 Tour Management Plugin — Critical Bugs & Fix Plan

## Overview
- Current: WP plugin manages 3D tours on AWS S3/CloudFront with Lambda processing.
- Goals: Fix critical defects, align S3 path handling with metadata, strengthen logging, and stabilize admin features.
- Stack: WordPress 5.8+, PHP 7.4+, MySQL, AWS S3/CloudFront/Lambda, jQuery.

## Critical Bugs

### 1) Delete Tour Broken
- Symptom: Deleting a tour shows “Error: Tour not found in S3”.
- Root cause: Lambda creates S3 folders preserving spaces (e.g., `tours/Jeffs Test/`), but PHP sanitizes name to slug/dashes.
- Impact: S3 operations target wrong prefix; deletion/archival fails.
- Files: `includes/class-h3tm-s3-simple.php` (archive_tour), `includes/class-h3tm-tour-metadata.php`, `includes/class-h3tm-activator.php` (migration preserves spaces).
- Fix:
  - Use metadata `s3_folder` as canonical source of truth for all S3 ops.
  - In `archive_tour()`, resolve metadata via display name with slug fallback, require `s3_folder`, and abort when not present (no sanitized fallback).
  - Add structured logging for S3 actions (bucket, `s3_folder`, counts, op result) so QA can confirm canonical prefix usage.
  - If metadata lookup fails, return actionable error ("metadata not found") so UI surfaces a meaningful message instead of falsely sanitizing.
- Tests:
  - Delete tours named with spaces, hyphens, multiple spaces, special chars.
  - Verify PHP logs include resolved metadata id/slug and canonical `s3_folder` before copying, plus final moved/error counts.
  - Confirm archive destination contains timestamped folder that matches canonical name and that missing metadata surfaces an error immediately.

### 2) Change URL - FIXED ✅
- **Status**: Handler registered and functional (class-h3tm-admin.php:2051)
- **Implementation**:
  - ✅ `wp_ajax_h3tm_change_tour_url` registered in constructor (line 32)
  - ✅ `H3TM_Tour_Metadata::change_slug()` properly updates slug and url_history (lines 204-240)
  - ✅ `H3TM_URL_Redirector` hooks on `init` (line 14) and `template_redirect` (line 19)
  - ✅ 301 redirects implemented via `check_tour_redirect()` and `handle_tour_request()`
  - ✅ JavaScript modal and AJAX implemented (admin-tour-features.js:140-282)
- **Remaining**: UI refresh hook (I2.T4) to update table/embed without reload
- Tests:
  - DevTools: verify AJAX request/response, JS console clean.
  - Visit old URL → 301 to new URL; table and embed reflect new slug.

### 3) Rename Tour - FIXED ✅
- **Status**: Handler registered and functional (class-h3tm-admin.php:1359)
- **Implementation**:
  - ✅ `wp_ajax_h3tm_rename_tour` registered in constructor (line 25)
  - ✅ Backend handler exists with proper security checks (lines 1359-1417)
  - ✅ `H3TM_Tour_Metadata::rename_tour()` updates display_name only (lines 184-195)
  - ✅ JavaScript delegated event handler exists (assets/js/admin.js)
- **Note**: Optimized version also available (class-h3tm-admin-optimized.php)
- Tests:
  - Rename tours with spaces; table updates display_name; slug unchanged.

### 4) Update Tour & Get Script - FIXED ✅
- **Status**: Handlers registered and functional
- **Update Tour** (class-h3tm-admin.php:1920):
  - ✅ `wp_ajax_h3tm_update_tour` registered (line 30)
  - ✅ Full upload flow with presigned URLs and Lambda processing
  - ✅ JavaScript implementation (admin-tour-features.js:96-424)
- **Get Script** (class-h3tm-admin.php:1990):
  - ✅ `wp_ajax_h3tm_get_embed_script` registered (line 31)
  - ✅ Returns both standard and responsive embed codes
  - ✅ JavaScript modal with clipboard copy (admin-tour-features.js:97-509)
- **Note**: No hoisting issues - all functions properly declared before use
- Tests:
  - Confirm Update triggers flow (upload/polling) and Get Script opens/copies embed.

### 5) Rebuild Metadata - FIXED ✅
- **Status**: Handler registered and functional (class-h3tm-admin.php:2125)
- **Implementation**:
  - ✅ `wp_ajax_h3tm_rebuild_metadata` registered in constructor (line 33)
  - ✅ Clears and rebuilds metadata table from S3 tours (lines 2125-2165)
  - ✅ Proper security checks (nonce + capabilities)
  - ✅ Success/error logging implemented
- **JavaScript**: Button click handler needs to be verified in Settings page
- Tests:
  - Verify success JSON; check PHP logs for handler execution.

## Refactoring Plan (Traits)
- Problem: `class-h3tm-admin.php` >2100 lines; hard to maintain.
- Solution: Split into traits under `includes/traits/`:
  - `trait-h3tm-tour-handlers.php` (update/get script/change URL/rebuild)
  - `trait-h3tm-delete-rename.php` (delete/rename)
  - `trait-h3tm-s3-operations.php` (presign/upload/cleanup)
  - `trait-h3tm-migration.php` (migrate/upload dir/delete dir)
  - `trait-h3tm-page-renderers.php` (render pages)
- Success: Main class <400 lines; each trait <300; syntax valid; functionality preserved.

## UX Improvements (Phase 3)
- Rework tour table: search/filter/sort, bulk actions, inline status, better button hierarchy.
- Real-time progress: poll `status.json` with percentage/ETA; auto-refresh on complete.
- Confirmations: Delete/Archive confirmations; warnings for destructive actions.
- Modals: Improved design, loading/error states, success feedback.

## Technical Details
- DB: `wp_h3tm_tour_metadata` with fields `tour_slug`, `display_name`, `s3_folder` (preserve spaces), `url_history` (JSON), timestamps, indexes on slug and s3_folder.
- AWS: S3 for storage, CloudFront for delivery, Lambda for ZIP processing; WordPress must align with Lambda’s space-preserving folder names.

## Debugging Checklist
- Console: no JS errors; functions defined before use (or declarations).
- Network: XHR requests present, correct payloads, successful responses.
- PHP error logs: no warnings/fatals during AJAX handlers.
- File loading: admin.js and admin-tour-features.js enqueued and cache-busted.
- DB: table exists; `s3_folder` values preserve spaces; sample queries OK.

## Testing Checklist
- Syntax: `php -l` on PHP files; `node --check` on JS files.
- Delete: create → delete tour; verify S3 + DB removal.
- Change URL: change slug; verify table/embed updates; old→new 301.
- Rename: rename display name; slug stable; UI refresh.
- Update: upload new ZIP; confirm S3 updates; CloudFront invalidation if applicable.
- Get Script: modal shows correct embed; copy actions work.
- Rebuild Metadata: AJAX success; logs recorded; metadata corrected.

## Logging
- Add structured logs for S3 ops and DB changes: include tour id/slug, bucket, `s3_folder`, operation, result.
- Ensure redirector and slug changes log old→new mappings for traceability.
