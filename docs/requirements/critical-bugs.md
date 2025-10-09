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
  - In `archive_tour()`, look up tour by display name/slug, then use `s3_folder` from metadata; do NOT sanitize display name.
  - Add structured logging for S3 actions (bucket, prefix, op result).
- Tests:
  - Delete tours named with spaces, hyphens, multiple spaces, special chars.
  - Verify S3 folder removal/archive and DB metadata deletion.

### 2) Change URL Non-Functional
- Symptoms: Modal submits but URL doesn’t change, no redirect, table doesn’t refresh, embed code stale.
- Possible issues: AJAX action not registered, JS hoisting, DB update w/o UI refresh, redirector not hooked.
- Files: `includes/class-h3tm-admin.php`, `includes/class-h3tm-new-handlers.php` (unused), `includes/class-h3tm-tour-metadata.php`, `includes/class-h3tm-url-redirector.php`, `assets/js/admin-tour-features.js`.
- Fix:
  - Ensure `wp_ajax_h3tm_change_tour_url` is registered in constructor and handler callable.
  - Update `change_slug()` to persist new slug and append old slug to `url_history`; add success logs.
  - Initialize redirector on `template_redirect`.
  - On success, refresh tour list and update embed code.
- Tests:
  - DevTools: verify AJAX request/response, JS console clean.
  - Visit old URL → 301 to new URL; table and embed reflect new slug.

### 3) Rename Tour Does Nothing
- Symptoms: Clicking Rename has no effect; no modal or errors.
- Possible issues: Event delegation missing, JS error upstream, dynamically created button, PHP handler errors.
- Files: `assets/js/admin.js` (handler), `includes/class-h3tm-admin.php` (AJAX handler `handle_rename_tour`).
- Fix:
  - Use delegated click handler `$(document).on('click', '.rename-tour', ...)`.
  - Add console logging to confirm handler fires; ensure modal HTML renders.
  - Ensure backend handler returns success and UI refreshes on completion.
- Tests:
  - Rename tours with spaces; table updates display_name; slug unchanged.

### 4) Update Tour & Get Script Broken
- Symptoms: Buttons exist; clicking does nothing.
- Root cause likely: JS hoisting — functions called before defined as expressions.
- Files: `assets/js/admin-tour-features.js`, `includes/class-h3tm-admin.php` (enqueue + handlers).
- Fix:
  - Convert function expressions to declarations or move definitions before usage.
  - Add console logging to verify handlers.
  - Clear browser/WP cache; add cache-busting version to enqueued scripts.
- Tests:
  - Confirm Update triggers flow (upload/polling) and Get Script opens/copies embed.

### 5) Rebuild Metadata Button Not Working
- Symptom: Button on Settings page does nothing.
- Possible issues: AJAX `wp_ajax_h3tm_rebuild_metadata` not registered, JS handler not executed, caching.
- Files: `includes/class-h3tm-admin.php` (button + handler), admin JS.
- Fix:
  - Register AJAX action in constructor; validate nonce/capabilities; add logs.
  - Add/test JS call using `jQuery.post` to `admin-ajax.php` action.
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

