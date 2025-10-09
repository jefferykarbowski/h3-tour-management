# S3 Operations: Metadata-Driven Paths and Deletion/Archive Rules

This note documents how all S3 operations within the H3 Tour Management plugin must use tour metadata as the single source of truth for S3 paths, along with logging guidance and test scenarios.

## Source of Truth

- Use `H3TM_Tour_Metadata.s3_folder` as the authoritative S3 key prefix for a tour.
- The `s3_folder` preserves spaces exactly as created by Lambda (e.g., `tours/Jeffs Test/`).
- Never derive S3 paths by sanitizing `display_name` or converting spaces to dashes.
- When a method needs the folder name without the leading `tours/` and trailing `/`, compute it with:
  - `str_replace('tours/', '', rtrim($tour->s3_folder, '/'))`.

## Archive (Delete/Move) Behavior

- Method: `H3TM_S3_Simple::archive_tour($tour_name)`
  - Looks up the tour by `display_name` via `H3TM_Tour_Metadata`.
  - Extracts the actual S3 folder from `metadata.s3_folder` and uses it to list and move files.
  - Copies all files from `tours/<Folder With Spaces>/` to `archive/<folder-with-spaces>_<timestamp>/`.
  - Deletes originals only after successful copy.
  - Adds extensive logging to trace the exact S3 prefixes used and outcomes.

## Logging Guidance

- Prefix all messages with `H3TM S3` context and operation type, e.g., `H3TM S3 Archive:`.
- Log at the start of operations the exact `source_prefix` and `archive_prefix` being used.
- When using metadata: log the resolved S3 folder (without `tours/`) after extracting from `metadata.s3_folder`.
- For each copy/delete failure, log the full S3 key and response status/body if available.
- Summarize results: counts of files moved and errors.

## Edge Cases to Test

Validate operations (archive/delete flows) with tours whose names include:

- Single spaces: `"Jeffs Test"` → `tours/Jeffs Test/`
- Multiple spaces: `"Big   Open   House"` → `tours/Big   Open   House/`
- Hyphens: `"Downtown-East"` → `tours/Downtown-East/`
- Mixed spaces and hyphens: `"Downtown East-West"` → `tours/Downtown East-West/`
- Special characters (supported by S3 and your upload process): `"R&D Suite #5"` → `tours/R&D Suite #5/`

Expected behavior:

- The plugin archives the tour without attempting to replace spaces with dashes.
- Logging shows the resolved folder from `metadata.s3_folder`.
- If a tour is not found (empty list), the method returns `{ success: false, message: 'Tour not found in S3' }` and logs the attempt.

## Verification Commands

- PHP lint:
  - `php -l includes/class-h3tm-s3-simple.php`

## Notes

- This document complements the Critical Bug #1 fix: all S3 operations must anchor on `metadata.s3_folder` to avoid mismatches caused by sanitizing names.

