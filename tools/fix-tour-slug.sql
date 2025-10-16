-- Check current tour_slug value
SELECT id, tour_id, display_name, tour_slug, status
FROM wp_h3tm_tour_metadata
WHERE tour_id = '20251014_204411_mhy3v057';

-- If tour_slug is NULL, update it with sanitized display name
-- The slug for "My Tour Jeff" should be "my-tour-jeff"
UPDATE wp_h3tm_tour_metadata
SET tour_slug = 'my-tour-jeff'
WHERE tour_id = '20251014_204411_mhy3v057'
AND (tour_slug IS NULL OR tour_slug = '');

-- Verify the update
SELECT id, tour_id, display_name, tour_slug, status
FROM wp_h3tm_tour_metadata
WHERE tour_id = '20251014_204411_mhy3v057';

-- Clear the tours cache
DELETE FROM wp_options WHERE option_name = '_transient_h3tm_s3_tours_cache';
