# Refactoring Plan: Split class-h3tm-admin.php

## Current Problem
- 1872 lines in one file
- Hard to edit without syntax errors
- Mixing concerns (menus, AJAX, rendering, S3 operations)

## Proposed Structure

### Keep Main File: `class-h3tm-admin.php`
- Constructor (registers menus and AJAX)
- Menu registration
- Script enqueuing
- Delegates to trait files

### Split into Trait Files:

**1. `traits/trait-h3tm-tour-handlers.php`**
- handle_update_tour()
- handle_get_embed_script()
- handle_change_tour_url()
- handle_rebuild_metadata()

**2. `traits/trait-h3tm-delete-rename.php`**
- handle_delete_tour()
- handle_rename_tour()

**3. `traits/trait-h3tm-s3-operations.php`**
- handle_get_s3_presigned_url()
- handle_process_s3_upload()
- download_from_s3()
- cleanup_s3_file()

**4. `traits/trait-h3tm-migration.php`**
- handle_migrate_tour_to_s3()
- upload_directory_to_s3()
- delete_directory()

**5. `traits/trait-h3tm-page-renderers.php`**
- render_upload_page()
- render_analytics_page()
- render_s3_settings_page()
- render_url_handlers_page()

## Benefits
- ✅ Easy to edit (each file ~200-300 lines)
- ✅ Clear separation of concerns
- ✅ No more syntax errors from complex edits
- ✅ Can edit traits without touching main file
- ✅ Better code organization

## Implementation
1. Create `/includes/traits/` directory
2. Extract methods into trait files
3. Update main class to use traits
4. Test all functionality

Want me to create this structure?
