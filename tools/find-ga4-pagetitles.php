<?php
/**
 * Find GA4 PageTitle Values
 * This shows what pageTitle values exist in GA4 vs WordPress tour titles
 */

require_once dirname(dirname(__FILE__)) . '/h3-tour-management.php';

echo "=== GA4 PageTitle Discovery Tool ===\n\n";

try {
    // Initialize analytics
    $shortcode = new H3TM_Shortcodes_V4();
    $reflection = new ReflectionClass($shortcode);
    $initMethod = $reflection->getMethod('initialize_analytics');
    $initMethod->setAccessible(true);
    $analytics_service = $initMethod->invoke($shortcode);
    
    $PROPERTY_ID = "properties/491286260";
    
    // Get all pageTitle values from last 90 days
    $dateRange = new Google_Service_AnalyticsData_DateRange();
    $dateRange->setStartDate(date('Y-m-d', strtotime('-90 days')));
    $dateRange->setEndDate('today');
    
    $pageTitle = new Google_Service_AnalyticsData_Dimension();
    $pageTitle->setName('pageTitle');
    
    $sessions = new Google_Service_AnalyticsData_Metric();
    $sessions->setName('sessions');
    
    $users = new Google_Service_AnalyticsData_Metric();
    $users->setName('totalUsers');
    
    // Order by sessions descending
    $ordering = new Google_Service_AnalyticsData_OrderBy();
    $metricOrdering = new Google_Service_AnalyticsData_MetricOrderBy();
    $metricOrdering->setMetricName('sessions');
    $ordering->setMetric($metricOrdering);
    $ordering->setDesc(true);
    
    $request = new Google_Service_AnalyticsData_RunReportRequest();
    $request->setProperty($PROPERTY_ID);
    $request->setDateRanges([$dateRange]);
    $request->setDimensions([$pageTitle]);
    $request->setMetrics([$sessions, $users]);
    $request->setOrderBys([$ordering]);
    $request->setLimit(100);
    
    $response = $analytics_service->properties->runReport($PROPERTY_ID, $request);
    $rows = $response->getRows();
    
    echo "Found " . count($rows) . " unique page titles in GA4 (last 90 days):\n\n";
    
    // Expected tour titles
    $expected_tours = [
        'Arden Courts of Elk Grove',
        'Arden Courts of Farmington', 
        'Arden Courts of Geneva'
    ];
    
    $found_matches = [];
    
    foreach ($rows as $index => $row) {
        $dimensionValues = $row->getDimensionValues();
        $metricValues = $row->getMetricValues();
        $pageTitle = $dimensionValues[0]->getValue();
        $sessions = $metricValues[0]->getValue();
        $users = $metricValues[1]->getValue();
        
        // Check for matches with expected tours
        $match_info = [];
        foreach ($expected_tours as $expected) {
            if ($pageTitle === $expected) {
                $match_info[] = "EXACT: $expected";
                $found_matches[$expected] = 'exact';
            } elseif (stripos($pageTitle, $expected) !== false) {
                $match_info[] = "CONTAINS: $expected";
                $found_matches[$expected] = $found_matches[$expected] ?? 'contains';
            } elseif (stripos($expected, $pageTitle) !== false) {
                $match_info[] = "REVERSE: $expected";
                $found_matches[$expected] = $found_matches[$expected] ?? 'reverse';
            }
            
            // Check for partial word matches
            $expected_words = explode(' ', strtolower($expected));
            $page_words = explode(' ', strtolower($pageTitle));
            $common_words = array_intersect($expected_words, $page_words);
            if (count($common_words) >= 2 && !isset($found_matches[$expected])) {
                $match_info[] = "PARTIAL: $expected (" . implode(', ', $common_words) . ")";
                $found_matches[$expected] = 'partial';
            }
        }
        
        $match_display = empty($match_info) ? '' : ' <- [' . implode('; ', $match_info) . ']';
        echo sprintf("%3d. '%s' (%d sessions, %d users)%s\n", 
            $index + 1, $pageTitle, $sessions, $users, $match_display);
    }
    
    echo "\n=== MATCH SUMMARY ===\n";
    foreach ($expected_tours as $expected) {
        $match_type = $found_matches[$expected] ?? 'NONE';
        echo "❯ '$expected': $match_type\n";
    }
    
    if (empty($found_matches)) {
        echo "\n⚠️  NO MATCHES FOUND!\n";
        echo "This means the WordPress tour titles don't match any GA4 pageTitle values.\n\n";
        echo "POSSIBLE SOLUTIONS:\n";
        echo "1. Check if tours are being tracked in GA4 with different names\n";
        echo "2. Look for URL-based tracking instead of pageTitle\n";
        echo "3. Verify the GA4 property ID is correct\n";
        echo "4. Check if tracking is working at all\n\n";
        
        // Show top 10 pages that might be related
        echo "TOP 10 PAGES (might contain tour pages):\n";
        for ($i = 0; $i < min(10, count($rows)); $i++) {
            $row = $rows[$i];
            $dimensionValues = $row->getDimensionValues();
            $metricValues = $row->getMetricValues();
            $pageTitle = $dimensionValues[0]->getValue();
            $sessions = $metricValues[0]->getValue();
            echo "  $i. '$pageTitle' ($sessions sessions)\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nThis could be:\n";
    echo "- Missing Google API client library\n";
    echo "- Invalid credentials\n";
    echo "- No permission to access GA4 property\n";
    echo "- Wrong property ID\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "If no matches found:\n";
echo "1. Run this on production to see actual GA4 data\n";
echo "2. Check if tours use different tracking method\n";
echo "3. Verify pageTitle vs pagePath in GA4\n";
echo "4. Consider using URL-based filtering instead\n";