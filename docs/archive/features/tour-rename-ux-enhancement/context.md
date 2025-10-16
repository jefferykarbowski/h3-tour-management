# Tour Rename UX Enhancement - Fix Undefined Error & Add Progress

## Task
Fix the tour rename functionality that shows "undefined error popup" and add progress bar/better UI/UX since the process takes a long time.

## Project Context
**Repository**: WordPress plugin "H3 Tour Management"
**Working directory**: C:\Users\Jeff\Documents\GitHub\h3-tour-management
**Target files**:
- `assets/js/admin.js` (lines 257-301) - Current rename tour JavaScript
- `assets/css/admin.css` - CSS styling (may need new styles for modal/progress)
- `includes/class-h3tm-admin-v2.php` (lines 576-595) - AJAX handler
- `includes/class-h3tm-tour-manager-v2.php` (lines 507-560) - Backend rename logic

## Current Issues Identified

### 1. "Undefined Error Popup"
**Location**: `admin.js` line 291
```javascript
alert('Error: ' + response.data);
```
**Problem**: WordPress `wp_send_json_error()` structure inconsistency - sometimes `response.data` is undefined, should check `response.data.message` or handle properly.

### 2. No Progress Indication
**Problem**: Rename operation involves multiple time-consuming steps:
- Moving large tour directories (potentially hundreds of files)
- Updating PHP files with `update_tour_php_file()`
- Database updates for user assignments
- Database metadata updates
- Activity logging

**Current UX**: Only button disable, no visual feedback during operation.

### 3. Poor User Interface
**Problem**: Uses basic `prompt()` dialog instead of proper modal with validation.

## Technical Analysis

### Current Rename Process Flow
1. **Frontend** (`admin.js`):
   - `prompt()` for new name input
   - AJAX call to `h3tm_rename_tour`
   - Button disable/enable
   - Basic error handling

2. **Backend** (`class-h3tm-admin-v2.php`):
   - Security verification
   - Input sanitization
   - Delegate to `H3TM_Tour_Manager_V2::rename_tour()`

3. **Core Logic** (`class-h3tm-tour-manager-v2.php`):
   - Filesystem operations (directory move)
   - PHP file updates
   - Database operations (user assignments, metadata)
   - Activity logging

### Performance Considerations
- Large tour directories can take 10-30+ seconds to move
- Database operations add additional time
- No intermediate progress feedback to user
- Risk of timeout on very large tours

## Requirements

### 1. Fix Undefined Error
- **Robust error handling** in JavaScript
- **Consistent error message format** from backend
- **User-friendly error messages** instead of technical details

### 2. Add Progress Indication
- **Visual progress bar** or loading spinner
- **Progress updates** during long operations (if possible)
- **Clear status messages** ("Moving files...", "Updating database...")
- **Estimated time** or completion percentage

### 3. Improve User Interface
- **Replace prompt()** with proper modal dialog
- **Input validation** before submission
- **Confirmation step** with old/new name display
- **Cancel capability** during operation (if feasible)

### 4. Enhanced User Experience
- **Responsive design** for mobile devices
- **Accessibility compliance** (screen readers, keyboard navigation)
- **WordPress admin styling** consistency
- **Success feedback** with clear completion message

## Implementation Approaches

### Option A: Simple Loading Indicator
- Replace prompt() with modal dialog
- Add spinner/progress bar during AJAX call
- Fix error handling for proper message display
- **Pros**: Quick implementation, minimal backend changes
- **Cons**: No real progress tracking, just visual feedback

### Option B: Progress Tracking with Intermediate Updates
- Implement chunked progress reporting from backend
- Use WordPress heartbeat API or polling for progress updates
- Display specific progress stages ("Moving files 45%", "Updating database...")
- **Pros**: Real progress tracking, better UX
- **Cons**: More complex, requires backend modifications

### Option C: Background Processing with Notifications
- Move operation to background job
- Implement job queue system
- Use admin notifications for completion
- **Pros**: No timeout issues, best for large operations
- **Cons**: Significant architecture change

## Success Criteria
- [ ] No more "undefined" error popups
- [ ] Clear, user-friendly error messages
- [ ] Visual progress indication during rename operation
- [ ] Professional modal dialog replacing prompt()
- [ ] Input validation and user confirmation
- [ ] Mobile-responsive design
- [ ] Accessibility compliance
- [ ] No breaking changes to existing functionality
- [ ] Performance improvement or maintained performance

## Testing Requirements
- Test with small tours (under 50 files)
- Test with large tours (500+ files)
- Test with very large tours (1000+ files)
- Test error scenarios (duplicate names, permission issues)
- Test on different browsers (Chrome, Firefox, Safari, Edge)
- Test mobile responsiveness
- Test accessibility with screen readers
- Verify existing tour management functionality remains intact

## Constraints
- Must maintain WordPress coding standards
- No external dependencies beyond existing jQuery
- Cross-browser compatibility required
- Performance impact should be minimal
- No breaking changes to existing upload/delete workflows
- Must handle server timeout scenarios gracefully