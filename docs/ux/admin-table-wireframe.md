# Admin Tour Table UX Specification

## Purpose
- Provide a concrete reference for implementing the revamped tour management table in WordPress admin.
- Ensure the design supports the critical UX goals: quick discovery (search/filter), efficient multi-select workflows, clear processing feedback, and guarded destructive actions.

## Layout Overview
````markdown
+------------------------------------------------------------------------------+
| Search: [____________________]  Filter: [All Tours v]  Status: [All v]       |
|                                                          Actions: [Bulk v]   |
+------------------------------------------------------------------------------+
| [ ] Select All                                                   Updated 2h  |
+------------------------------------------------------------------------------+
| [ ] Jeffs Test Tour                                           [Preview] [More]|
|     /h3panos/jeffs-test/                                        [Embed]      |
|     Primary: Preview button    Secondary: Embed button          Menu: More   |
|     Tags: Available | Updated 2 hours ago                                    |
+------------------------------------------------------------------------------+
| [ ] Downtown Office (Processing)                               [Preview] [More]|
|     /h3panos/downtown-office/                                   [Embed]      |
|     Progress: [#####-----] 45% | ETA ~2m | Last update 30s ago                |
|     Inline copy: "Processing - actions enabled when complete"                |
+------------------------------------------------------------------------------+
| [ ] Archived Model                                             [Preview] [More]|
|     /h3panos/archived-model/                                    [Embed]      |
|     Status Pill: Archived (grey) | Updated 4 months ago                       |
|     More menu: Restore | Delete Permanently (confirmation required)          |
+------------------------------------------------------------------------------+

Notes:
- Primary action (Preview) stays visible; secondary (Embed) uses icon plus label in final build but text labels are shown here.
- Tertiary actions live in a "More" dropdown triggered by the [More] button to reduce visual noise.
- Status pills change colour: "Available" (green), "Processing" (amber with spinner icon), "Archived" (grey), "Error" (red).
````

## Controls and Interactions
- **Search Input**
  - Debounced (300 ms) text filter on tour name and slug.
  - Clears with dedicated "X" button; ESC also clears while focus is in the field.
  - Announces results count via live region (`aria-live="polite"`).
- **Filter Dropdowns**
  - `Filter` targets collections (for example "My Tours" or "Shared Tours").
  - `Status` filters by processing state (`Available`, `Processing`, `Archived`, `Error`).
  - Both trigger immediate refresh via AJAX; selection persists in query string (`h3tm_filter`, `h3tm_status`) for deep links.
- **Bulk Actions Menu**
  - Disabled until at least one row is selected; label switches to "Bulk (3)" when three items are picked.
  - Options: `Archive`, `Delete`, `Restore`, `Export URLs`. Each shows a confirmation before submission.
  - On submit, spins a mini progress indicator in the header and disables inline actions until the response arrives.
- **Row Selection**
  - Checkbox per row; `Select All` respects the current filtered results (only selects visible page).
  - Indeterminate state when a subset of rows is selected.
- **Row-Level Actions**
  - **Preview (Primary)**: opens CloudFront preview in a new tab (`target="_blank"` with constructed query string). Uses the existing `h3tm_preview` capability.
  - **Embed (Secondary)**: opens a modal anchored to the row; modal shows iframe code with copy buttons.
  - **More Menu (Tertiary)**: options depend on status:
    - `Available`: `Rename`, `Change URL`, `Update Tour`, `Archive`, `Delete`.
    - `Processing`: disabled with tooltip; only `View Logs` (future) enabled.
    - `Archived`: `Restore`, `Delete Permanently`.

## Progress Indicators
- Polls `status.json` (S3) every 10 s for tours marked `Processing`; polls stop after success or failure.
- Progress bar shows percent and estimated remaining time; provide descriptive text (`aria-valuenow`, `aria-valuemax`, `aria-live="polite"` summary on changes).
- When a tour finishes processing:
  - Row transitions to `Available` with a short fade animation.
  - Toast "Tour 'Downtown Office' is now Available" appears.
  - Associated embed snippet refreshed silently.
- On processing error:
  - Row shows red pill "Error - Retry Update" and includes "View Details" link.
  - Toast provides summary; log entry recorded (`error_log` plus CloudWatch).

## Confirmation and Notifications
- **Delete / Bulk Delete**
  - Modal copy: "Delete 3 tours? This cannot be undone. Files will be removed from S3."
  - Requires user to type `DELETE` for bulk operations to confirm destructive intent.
  - On success, toast with count removed; message clarifies that undo is not available.
- **Archive / Bulk Archive**
  - Modal copy: "Archive selected tours? Links will stop working until restored."
  - Progress spinner runs until AJAX completes; success toast references archive location.
- **Change URL / Rename / Update**
  - Inline modals reuse existing markup but add success toast plus automatic table refresh.
- **Toasts**
  - Implemented as dismissible alerts positioned top-right.
  - Include `role="status"` and keep focus until dismissed when triggered via keyboard (ESC closes).

## Empty and Error States
- **No Tours Found**
  - Display illustration placeholder, message "No tours match these filters", button "Clear filters".
  - Provide quick link to the upload workflow.
- **Network / Server Error**
  - Inline banner at top with retry button (`Retry Fetch`) and log details toggled for developers.
  - Maintain previously loaded rows when possible; do not wipe selection without confirmation.

## Accessibility and Keyboard Support
- Tab order moves left-to-right across toolbar, then into the table body.
- `Bulk` menu and row `More` menu use roving tabindex; support Arrow navigation.
- Progress bar exposes `aria-describedby` linking to textual ETA.
- Confirmation modals trap focus, enforce ESC close, and restore focus to the triggering control.
- Toast notifications include a "Dismiss" button; auto-dismiss after 6 s but pause on hover or focus.
- Colour palette meets WCAG AA contrast; status is conveyed with icons plus text, never colour alone.

## Backend Integration Map
- `h3tm_fetch_tours` (AJAX) - returns filtered list; accepts `search`, `status`, `collection`, `page`.
- `h3tm_bulk_archive`, `h3tm_bulk_delete`, `h3tm_bulk_restore` - process bulk actions; return array of updated IDs.
- `h3tm_change_tour_url`, `h3tm_rename_tour`, `h3tm_update_tour`, `h3tm_get_embed_script` - reused single-item handlers invoked from the row menu.
- `h3tm_poll_tour_status` - lightweight endpoint that proxies S3 `status.json` (returns percent, eta, message).
- Mount all AJAX calls with `wp_create_nonce('h3tm_ajax_nonce')` and include capability check `manage_options`.

## Responsive Behaviour
- Below 960 px: toolbar stacks into two rows (Search plus Filter on row 1, Status plus Bulk on row 2).
- Row actions collapse to icon-only buttons below 800 px; tooltips supply labels on focus or hover.
- Table converts to card layout under 640 px, retaining checkboxes, status pills, and progress bars.

## Implementation Checklist
- [ ] Update renderer to output the new toolbar markup and status pills.
- [ ] Implement delegated event handlers for search, filter, and bulk menu interactions.
- [ ] Ensure AJAX responses include updated timestamps and status values to populate pills.
- [ ] Wire toasts and confirmations per spec; add unit tests for command payloads.
- [ ] Verify assistive technology compatibility using keyboard-only walkthrough and screen reader spot checks.

---
- Last reviewed: 2025-10-09
- Owner: Admin UX Revamp (Iteration I4)
