-- Test 301 Redirect for Old Slugs
-- This script changes the tour slug to test redirect functionality

-- Step 1: Check current state
SELECT id, tour_id, display_name, tour_slug, url_history, status
FROM wp_h3tm_tour_metadata
WHERE tour_id = '20251014_204411_mhy3v057';

-- Step 2: Update slug and add old slug to url_history
UPDATE wp_h3tm_tour_metadata
SET
    tour_slug = 'jeffs-test-tour',
    url_history = JSON_ARRAY('my-tour-jeff')
WHERE tour_id = '20251014_204411_mhy3v057';

-- Step 3: Verify the update
SELECT id, tour_id, display_name, tour_slug, url_history, status
FROM wp_h3tm_tour_metadata
WHERE tour_id = '20251014_204411_mhy3v057';

-- Step 4: Clear tours cache
DELETE FROM wp_options WHERE option_name = '_transient_h3tm_s3_tours_cache';

-- Test URLs after running this script:
-- New URL (should work): https://h3vt.local/h3panos/jeffs-test-tour/
-- Old URL (should 301 redirect): https://h3vt.local/h3panos/my-tour-jeff/
