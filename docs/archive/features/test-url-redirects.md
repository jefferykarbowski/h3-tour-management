# Testing 301 Redirects for Renamed Tours

## Overview
When you change a tour's URL slug, the old slug should automatically redirect to the new slug with a 301 (Permanent Redirect) status. This preserves SEO and ensures old links continue to work.

## Code Verification ✅

**1. URL History Tracking** - `class-h3tm-tour-metadata.php:233-269`
- ✅ `change_slug()` method properly adds old slug to `url_history` array
- ✅ Keeps last 10 slugs to prevent unlimited growth
- ✅ Updates database with new slug and history

**2. Redirect Detection** - `class-h3tm-s3-proxy.php:517-551`
- ✅ `resolve_tour_identifier()` checks `url_history` for old slugs
- ✅ Returns redirect info when old slug detected
- ✅ Uses `find_by_old_slug()` method from Tour Metadata class

**3. Redirect Execution** - `class-h3tm-s3-proxy.php:103-116, 214-229`
- ✅ Both `handle_tour_requests()` and `pantheon_early_tour_handler()` check for redirect
- ✅ Perform `wp_redirect($redirect_url, 301)` when old slug accessed
- ✅ Preserve file paths during redirect (e.g., `/old-slug/file.js` → `/new-slug/file.js`)

## Test Scenarios

### Test 1: Change URL via WordPress Admin

**Steps:**
1. Go to WordPress Admin → H3 Tours page
2. Find tour "My Tour Jeff" (currently at `/h3panos/my-tour-jeff/`)
3. Click the "Change URL" (chain link) button
4. Enter new slug: `jeffs-test-tour`
5. Confirm the change

**Expected Results:**
- ✅ Success message: "URL changed successfully"
- ✅ Tour now appears with new URL: `/h3panos/jeffs-test-tour/`
- ✅ New URL loads the tour correctly
- ✅ Old URL redirects to new URL with 301 status

**Verification:**
```bash
# Test new URL
curl -I https://h3vt.local/h3panos/jeffs-test-tour/
# Should show: HTTP/1.1 200 OK

# Test old URL redirect
curl -I https://h3vt.local/h3panos/my-tour-jeff/
# Should show: HTTP/1.1 301 Moved Permanently
# Location: https://h3vt.local/h3panos/jeffs-test-tour/
```

### Test 2: Change URL via SQL Script

If you prefer to test via SQL, run the provided script:

**File:** `tools/test-slug-redirect.sql`

**Steps:**
1. Open your database tool (e.g., phpMyAdmin, MySQL Workbench, Adminer)
2. Select the WordPress database
3. Run the SQL script
4. Test both URLs in browser

### Test 3: Multiple Renames (URL History Chain)

**Steps:**
1. Change tour slug from `jeffs-test-tour` to `my-final-tour`
2. Verify all old slugs redirect:
   - `/h3panos/my-tour-jeff/` → 301 → `/h3panos/my-final-tour/`
   - `/h3panos/jeffs-test-tour/` → 301 → `/h3panos/my-final-tour/`
3. Check database to see url_history contains both old slugs

**SQL to Check History:**
```sql
SELECT tour_id, tour_slug, url_history
FROM wp_h3tm_tour_metadata
WHERE tour_id = '20251014_204411_mhy3v057';
```

Expected `url_history`: `["my-tour-jeff", "jeffs-test-tour"]`

## Browser Testing

### Using Browser DevTools
1. Open browser DevTools (F12)
2. Go to Network tab
3. Navigate to old URL: `https://h3vt.local/h3panos/my-tour-jeff/`
4. Check the first request shows:
   - Status: `301 Moved Permanently`
   - Location header: `https://h3vt.local/h3panos/jeffs-test-tour/`
5. Browser should automatically follow redirect to new URL

### Using Playwright
```javascript
// Test redirect
await page.goto('https://h3vt.local/h3panos/my-tour-jeff/');
console.log('Final URL:', page.url());
// Should be: https://h3vt.local/h3panos/jeffs-test-tour/
```

## Error Log Verification

Check WordPress error logs for redirect activity:

**Expected Log Entries:**
```
H3TM S3 Proxy: Old slug "my-tour-jeff" found, should redirect to: jeffs-test-tour
H3TM S3 Proxy: 301 redirect from old slug to: https://h3vt.local/h3panos/jeffs-test-tour/
```

## Edge Cases to Test

1. **Deep Links with File Paths**
   - Old: `/h3panos/my-tour-jeff/app-files/123456/file.js`
   - Should redirect to: `/h3panos/jeffs-test-tour/app-files/123456/file.js`

2. **Multiple Redirects**
   - Rename tour multiple times
   - Verify all historical slugs redirect to current slug
   - Check that url_history is limited to 10 entries

3. **Invalid Slugs**
   - Try accessing non-existent slug
   - Should show "Tour file not found" (not redirect)

4. **Tour ID URLs Still Work**
   - `/h3panos/20251014_204411_mhy3v057/` should still work
   - Should NOT redirect (tour_id is permanent identifier)

## Success Criteria

- [x] Code review confirms proper implementation
- [ ] Change URL via admin interface works
- [ ] Old slug performs 301 redirect to new slug
- [ ] New slug loads tour successfully
- [ ] Multiple renames create proper redirect chain
- [ ] url_history is properly populated in database
- [ ] Deep links (with file paths) redirect correctly
- [ ] Tour ID URLs continue to work without redirect

## Troubleshooting

**If redirects don't work:**
1. Check if url_history is populated in database
2. Verify rewrite rules are flushed (Settings → Permalinks → Save)
3. Check error logs for redirect detection messages
4. Clear WordPress transient cache: `DELETE FROM wp_options WHERE option_name LIKE '%transient%h3tm%';`

**If getting redirect loops:**
1. Verify H3TM_URL_Redirector is disabled in `h3-tour-management.php`
2. Check that only S3 Proxy is handling `/h3panos/` URLs
3. Flush rewrite rules again

## Current Status

**Implementation:** ✅ Complete
**Testing:** ⏳ Ready for user testing

All code is in place and verified. Ready for real-world testing!
