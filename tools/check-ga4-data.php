<?php
/**
 * Check GA4 Data - Simple Version
 */

echo "GA4 Data Check\n";
echo "==============\n\n";

// Test WordPress tour titles we're expecting
$expected_tours = [
    'Arden Courts of Elk Grove',
    'Arden Courts of Farmington', 
    'Arden Courts of Geneva'
];

echo "Expected tour titles from WordPress:\n";
foreach ($expected_tours as $i => $tour) {
    echo ($i + 1) . ". '$tour'\n";
}

echo "\nThe issue: These titles are not found in GA4 as pageTitle values.\n\n";

echo "LIKELY CAUSES:\n";
echo "1. Tours are tracked in GA4 with different names\n";
echo "2. GA4 uses pagePath instead of pageTitle for tour tracking\n";
echo "3. Tours have different titles in the actual HTML <title> tags\n";
echo "4. GA4 property might be tracking different pages\n\n";

echo "IMMEDIATE SOLUTIONS:\n";
echo "1. Check the actual tour pages' HTML <title> tags\n";
echo "2. Look at GA4 dashboard to see what pageTitle values exist\n";
echo "3. Consider switching from pageTitle to pagePath filtering\n";
echo "4. Add logging to see what GA4 API returns\n\n";

echo "EMAIL ANALYTICS WORK BECAUSE:\n";
echo "- They might use different filtering logic\n";
echo "- They might have been tested with actual GA4 data\n";
echo "- They might use pagePath instead of pageTitle\n\n";

echo "NEXT STEPS:\n";
echo "1. Check GA4 dashboard manually for these tour names\n";
echo "2. Look at actual tour page HTML titles\n";
echo "3. Test the email analytics to see what data they show\n";
echo "4. Consider modifying the shortcode to use pagePath filtering\n";