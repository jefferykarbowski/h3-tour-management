-- Fix metadata for "Jeffs Test" tour
-- Run this in your WordPress database (via phpMyAdmin or MySQL client)

-- First, check if metadata exists
SELECT * FROM wp_h3tm_tour_metadata WHERE display_name = 'Jeffs Test';

-- If no results, create the metadata:
INSERT INTO wp_h3tm_tour_metadata (tour_slug, display_name, s3_folder, url_history, created_date, updated_date)
VALUES (
    'jeffs-test',
    'Jeffs Test',
    'tours/Jeffs Test',  -- Matches S3 folder with spaces
    '[]',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    s3_folder = 'tours/Jeffs Test',
    updated_date = NOW();

-- Verify the fix
SELECT id, tour_slug, display_name, s3_folder FROM wp_h3tm_tour_metadata WHERE display_name = 'Jeffs Test';

-- Expected result:
-- tour_slug: jeffs-test
-- display_name: Jeffs Test
-- s3_folder: tours/Jeffs Test  (with space, NOT dash!)
