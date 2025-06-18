<?php
/**
 * Test analytics referrer data retrieval
 * 
 * Usage: php test-analytics-referrers.php
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Ensure we're running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

echo "Testing Analytics Referrer Data Retrieval\n";
echo str_repeat('=', 50) . "\n\n";

try {
    // Load the analytics class
    require_once dirname(__FILE__) . '/../includes/class-h3tm-analytics.php';
    
    // Create instance
    $analytics = new H3TM_Analytics();
    
    // Get a sample tour to test with
    $tour_manager = new H3TM_Tour_Manager();
    $tours = $tour_manager->get_all_tours();
    
    if (empty($tours)) {
        throw new Exception('No tours found in the system');
    }
    
    // Use the first tour
    $test_tour = $tours[0];
    $tour_title = trim($tour_manager->get_tour_title($test_tour));
    
    echo "Testing with tour: $tour_title\n";
    echo "Tour directory: $test_tour\n\n";
    
    // Test the private method using reflection
    $reflection = new ReflectionClass($analytics);
    
    // Initialize analytics service
    $initMethod = $reflection->getMethod('initialize_analytics');
    $initMethod->setAccessible(true);
    $initMethod->invoke($analytics);
    
    // Test get_referring_sites
    echo "Testing get_referring_sites method...\n";
    echo str_repeat('-', 30) . "\n";
    
    $getReferringSitesMethod = $reflection->getMethod('get_referring_sites');
    $getReferringSitesMethod->setAccessible(true);
    
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $response = $getReferringSitesMethod->invoke($analytics, $tour_title, $start_date);
    
    $rows = $response->getRows();
    echo "Found " . count($rows) . " referrer entries\n\n";
    
    // Test referrer_results formatting
    echo "Testing referrer_results formatting...\n";
    echo str_repeat('-', 30) . "\n";
    
    $referrerResultsMethod = $reflection->getMethod('referrer_results');
    $referrerResultsMethod->setAccessible(true);
    
    $html = $referrerResultsMethod->invoke($analytics, $response);
    
    // Extract data from HTML for display
    if (count($rows) > 0) {
        echo "Referrer data found:\n\n";
        foreach ($rows as $row) {
            $dims = $row->getDimensionValues();
            $mets = $row->getMetricValues();
            
            $source = $dims[0]->getValue();
            $medium = isset($dims[1]) ? $dims[1]->getValue() : '';
            $users = $mets[0]->getValue();
            
            // Handle empty values
            if (empty($source) || $source === '(not set)') {
                $source = 'direct';
            }
            if (empty($medium) || $medium === '(not set)') {
                $medium = 'none';
            }
            
            echo sprintf("%-30s %s users\n", "$source / $medium", $users);
        }
    } else {
        echo "No referrer data available for this tour.\n";
    }
    
    echo "\nHTML Table Preview:\n";
    echo str_repeat('-', 30) . "\n";
    // Show a text representation of the HTML table
    if (strpos($html, 'No referrer data available') !== false) {
        echo "Table shows: 'No referrer data available'\n";
    } else {
        echo "Table contains referrer data formatted for email display\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone!\n";