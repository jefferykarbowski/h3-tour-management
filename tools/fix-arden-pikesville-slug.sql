-- SQL Script to fix arden-pikesville slug conflict
-- Run this directly in your WordPress database (phpMyAdmin, MySQL CLI, etc.)

-- STEP 1: Find all tours with slug 'arden-pikesville'
SELECT
    id,
    tour_id,
    tour_slug,
    display_name,
    status,
    created_date,
    updated_date
FROM wp_h3tm_tour_metadata
WHERE tour_slug = 'arden-pikesville';

-- STEP 2: If any tours are found with status other than 'deleted', delete them
-- Uncomment the line below to execute the deletion:
-- DELETE FROM wp_h3tm_tour_metadata WHERE tour_slug = 'arden-pikesville';

-- STEP 3: Verify the slug is now free
SELECT COUNT(*) as active_count
FROM wp_h3tm_tour_metadata
WHERE tour_slug = 'arden-pikesville'
AND status NOT IN ('deleted', 'archived', 'failed');

-- Should return 0 if slug is free

-- STEP 4: Show all tours (for reference)
SELECT
    id,
    tour_slug,
    display_name,
    status
FROM wp_h3tm_tour_metadata
ORDER BY updated_date DESC
LIMIT 20;
