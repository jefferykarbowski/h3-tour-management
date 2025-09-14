<?php
/**
 * Debug Tour Analytics Shortcode
 * This tool helps debug why the shortcode isn't showing data
 */

// Setup minimal WordPress environment
$wp_root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
require_once $wp_root . '/wp-config.php';

echo "H3TM Shortcode Analytics Debug Tool\n";
echo "====================================\n\n";

// Step 1: Check if classes are available
echo "1. Class Availability:\n";
echo "   H3TM_Shortcodes_V4: " . (class_exists('H3TM_Shortcodes_V4') ? "✓" : "✗") . "\n";
echo "   H3TM_Config: " . (class_exists('H3TM_Config') ? "✓" : "✗") . "\n";
echo "   H3TM_Tour_Manager: " . (class_exists('H3TM_Tour_Manager') ? "✓" : "✗") . "\n\n";

// Step 2: Get some test user data
echo "2. User Setup:\n";
$users_with_tours = get_users(array(
    'meta_key' => 'h3tm_tours',
    'meta_compare' => 'EXISTS',
    'number' => 1
));

if (empty($users_with_tours)) {
    echo "   No users with tours found.\n\n";
    exit(1);
}

$test_user = $users_with_tours[0];
$tours = get_user_meta($test_user->ID, 'h3tm_tours', true);
echo "   Test User: {$test_user->display_name} (ID: {$test_user->ID})\n";
echo "   Tours: " . implode(', ', $tours) . "\n\n";

// Step 3: Test tour title retrieval
echo "3. Tour Title Analysis:\n";
$tour_manager = new H3TM_Tour_Manager();
foreach ($tours as $tour) {
    $tour_title = trim($tour_manager->get_tour_title($tour));
    echo "   Tour Directory: $tour\n";
    echo "   Tour Title: '$tour_title'\n";
    echo "   Title Length: " . strlen($tour_title) . " chars\n\n";
}

// Step 4: Check what pageTitle values exist in GA4
echo "4. Google Analytics PageTitle Discovery:\n";
try {
    // Initialize the shortcode to access analytics
    $shortcode = new H3TM_Shortcodes_V4();
    
    // Use reflection to access the private analytics service
    $reflection = new ReflectionClass($shortcode);
    $initMethod = $reflection->getMethod('initialize_analytics');
    $initMethod->setAccessible(true);
    $analytics_service = $initMethod->invoke($shortcode);
    
    $PROPERTY_ID = "properties/491286260";
    
    // Get all unique page titles from the last 30 days
    $dateRange = new Google_Service_AnalyticsData_DateRange();
    $dateRange->setStartDate(date('Y-m-d', strtotime('-30 days')));
    $dateRange->setEndDate('today');
    
    $pageTitle = new Google_Service_AnalyticsData_Dimension();
    $pageTitle->setName('pageTitle');
    
    $sessions = new Google_Service_AnalyticsData_Metric();
    $sessions->setName('sessions');
    
    $request = new Google_Service_AnalyticsData_RunReportRequest();
    $request->setProperty($PROPERTY_ID);
    $request->setDateRanges([$dateRange]);
    $request->setDimensions([$pageTitle]);
    $request->setMetrics([$sessions]);
    $request->setLimit(50);
    
    $response = $analytics_service->properties->runReport($PROPERTY_ID, $request);
    $rows = $response->getRows();
    
    echo "   Found " . count($rows) . " unique page titles in GA4:\n";
    
    $found_matches = array();
    foreach ($rows as $row) {
        $dimensionValues = $row->getDimensionValues();
        $metricValues = $row->getMetricValues();
        $pageTitle = $dimensionValues[0]->getValue();
        $sessions = $metricValues[0]->getValue();
        
        // Check if this matches any of our tour titles
        $matches = array();
        foreach ($tours as $tour) {
            $tour_title = trim($tour_manager->get_tour_title($tour));
            if ($pageTitle === $tour_title) {
                $matches[] = "EXACT: $tour";
                $found_matches[$tour] = 'exact';
            } elseif (stripos($pageTitle, $tour_title) !== false) {
                $matches[] = "CONTAINS: $tour";
                if (!isset($found_matches[$tour])) {
                    $found_matches[$tour] = 'partial';
                }
            } elseif (stripos($tour_title, $pageTitle) !== false) {
                $matches[] = "REVERSE: $tour";
                if (!isset($found_matches[$tour])) {
                    $found_matches[$tour] = 'reverse';
                }
            }
        }
        
        $match_info = empty($matches) ? '' : ' [' . implode(', ', $matches) . ']';
        echo "   - '$pageTitle' ($sessions sessions)$match_info\n";
    }
    
    echo "\n5. Match Summary:\n";
    foreach ($tours as $tour) {
        $tour_title = trim($tour_manager->get_tour_title($tour));
        $match_type = isset($found_matches[$tour]) ? $found_matches[$tour] : 'none';
        echo "   Tour '$tour' ('$tour_title'): $match_type match\n";
    }
    
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'not found') !== false) {
        echo "   Issue: Missing files or library\n";
    } elseif (strpos($e->getMessage(), '403') !== false) {
        echo "   Issue: Permission denied - check service account access\n";
    } elseif (strpos($e->getMessage(), '401') !== false) {
        echo "   Issue: Authentication failed - check credentials\n";
    }
}

echo "\n6. Recommendations:\n";
echo "   - Add ?debug=1 to the analytics page URL to see debug info\n";
echo "   - Check if tour titles in WordPress match GA4 pageTitle exactly\n";
echo "   - The updated shortcode now includes fallback partial matching\n";
echo "   - Email analytics work because they might use different filtering\n\n";

echo "Debug complete!\n";