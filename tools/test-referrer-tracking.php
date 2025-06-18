<?php
/**
 * Test referrer tracking in Google Analytics
 * 
 * Usage: php test-referrer-tracking.php [tour_title]
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Ensure we're running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Get tour title from command line
$tour_title = isset($argv[1]) ? $argv[1] : null;

if (!$tour_title) {
    echo "Usage: php test-referrer-tracking.php [tour_title]\n";
    echo "Example: php test-referrer-tracking.php \"Arden Farmington\"\n";
    exit(1);
}

echo "Testing referrer tracking for tour: $tour_title\n";
echo str_repeat('=', 50) . "\n\n";

try {
    // Load required files
    require_once dirname(__FILE__) . '/../includes/class-h3tm-config.php';
    $autoload_path = H3TM_Config::get_autoload_path();
    
    if (!file_exists($autoload_path)) {
        throw new Exception('Google API client library not found at: ' . $autoload_path);
    }
    
    require_once $autoload_path;
    
    $KEY_FILE_LOCATION = H3TM_Config::get_credentials_path();
    if (!file_exists($KEY_FILE_LOCATION)) {
        throw new Exception('Service account credentials not found at: ' . $KEY_FILE_LOCATION);
    }
    
    // Initialize Google Analytics client
    $client = new Google_Client();
    
    // SSL verification based on environment
    if (!H3TM_Config::should_verify_ssl()) {
        $httpClient = new \GuzzleHttp\Client([
            'verify' => false
        ]);
        $client->setHttpClient($httpClient);
    }
    
    $client->setApplicationName("H3VT Analytics Debug");
    $client->setAuthConfig($KEY_FILE_LOCATION);
    $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
    
    $analytics = new Google_Service_AnalyticsData($client);
    
    $PROPERTY_ID = "properties/491286260";
    
    // Test 1: Get raw referrer data without filters
    echo "Test 1: Getting all referrer data (last 30 days)\n";
    echo str_repeat('-', 30) . "\n";
    
    $dateRange = new Google_Service_AnalyticsData_DateRange();
    $dateRange->setStartDate('30daysAgo');
    $dateRange->setEndDate('today');
    
    $users = new Google_Service_AnalyticsData_Metric();
    $users->setName('totalUsers');
    
    $sessions = new Google_Service_AnalyticsData_Metric();
    $sessions->setName('sessions');
    
    $referrer = new Google_Service_AnalyticsData_Dimension();
    $referrer->setName('pageReferrer');
    
    $pageTitle = new Google_Service_AnalyticsData_Dimension();
    $pageTitle->setName('pageTitle');
    
    $request = new Google_Service_AnalyticsData_RunReportRequest();
    $request->setProperty($PROPERTY_ID);
    $request->setDateRanges([$dateRange]);
    $request->setDimensions([$referrer, $pageTitle]);
    $request->setMetrics([$users, $sessions]);
    $request->setLimit(10);
    
    $response = $analytics->properties->runReport($PROPERTY_ID, $request);
    $rows = $response->getRows();
    
    if (empty($rows)) {
        echo "No referrer data found!\n";
    } else {
        echo "Found " . count($rows) . " referrer entries:\n\n";
        foreach ($rows as $row) {
            $dims = $row->getDimensionValues();
            $mets = $row->getMetricValues();
            echo "Referrer: " . $dims[0]->getValue() . "\n";
            echo "Page Title: " . $dims[1]->getValue() . "\n";
            echo "Users: " . $mets[0]->getValue() . ", Sessions: " . $mets[1]->getValue() . "\n\n";
        }
    }
    
    // Test 2: Get referrer data for specific tour
    echo "\nTest 2: Getting referrer data for tour '$tour_title'\n";
    echo str_repeat('-', 30) . "\n";
    
    $filter = new Google_Service_AnalyticsData_Filter();
    $stringFilter = new Google_Service_AnalyticsData_StringFilter();
    $stringFilter->setMatchType('EXACT');
    $stringFilter->setValue($tour_title);
    $filter->setStringFilter($stringFilter);
    $filter->setFieldName('pageTitle');
    
    $filterExpression = new Google_Service_AnalyticsData_FilterExpression();
    $filterExpression->setFilter($filter);
    
    $request = new Google_Service_AnalyticsData_RunReportRequest();
    $request->setProperty($PROPERTY_ID);
    $request->setDateRanges([$dateRange]);
    $request->setDimensions([$referrer]);
    $request->setMetrics([$users, $sessions]);
    $request->setDimensionFilter($filterExpression);
    $request->setLimit(20);
    
    $response = $analytics->properties->runReport($PROPERTY_ID, $request);
    $rows = $response->getRows();
    
    if (empty($rows)) {
        echo "No referrer data found for this tour!\n";
        
        // Let's check what page titles exist
        echo "\nChecking what page titles contain '$tour_title'...\n";
        
        $stringFilter->setMatchType('CONTAINS');
        $filter->setStringFilter($stringFilter);
        $filterExpression->setFilter($filter);
        
        $request = new Google_Service_AnalyticsData_RunReportRequest();
        $request->setProperty($PROPERTY_ID);
        $request->setDateRanges([$dateRange]);
        $request->setDimensions([$pageTitle]);
        $request->setMetrics([$users]);
        $request->setDimensionFilter($filterExpression);
        $request->setLimit(10);
        
        $response = $analytics->properties->runReport($PROPERTY_ID, $request);
        $rows = $response->getRows();
        
        if (!empty($rows)) {
            echo "Found these page titles:\n";
            foreach ($rows as $row) {
                $dims = $row->getDimensionValues();
                echo "- " . $dims[0]->getValue() . "\n";
            }
        }
    } else {
        echo "Found " . count($rows) . " referrers for this tour:\n\n";
        foreach ($rows as $row) {
            $dims = $row->getDimensionValues();
            $mets = $row->getMetricValues();
            $referrerValue = $dims[0]->getValue();
            
            // Handle empty referrer
            if (empty($referrerValue) || $referrerValue === '(not set)') {
                $referrerValue = '(direct)';
            }
            
            echo "Referrer: " . $referrerValue . "\n";
            echo "Users: " . $mets[0]->getValue() . ", Sessions: " . $mets[1]->getValue() . "\n\n";
        }
    }
    
    // Test 3: Check available dimensions
    echo "\nTest 3: Alternative referrer dimensions\n";
    echo str_repeat('-', 30) . "\n";
    
    // Try sessionSource dimension
    $source = new Google_Service_AnalyticsData_Dimension();
    $source->setName('sessionSource');
    
    $medium = new Google_Service_AnalyticsData_Dimension();
    $medium->setName('sessionMedium');
    
    $request = new Google_Service_AnalyticsData_RunReportRequest();
    $request->setProperty($PROPERTY_ID);
    $request->setDateRanges([$dateRange]);
    $request->setDimensions([$source, $medium]);
    $request->setMetrics([$users]);
    $request->setDimensionFilter($filterExpression);
    $request->setLimit(20);
    
    $response = $analytics->properties->runReport($PROPERTY_ID, $request);
    $rows = $response->getRows();
    
    if (!empty($rows)) {
        echo "Found " . count($rows) . " traffic sources:\n\n";
        foreach ($rows as $row) {
            $dims = $row->getDimensionValues();
            $mets = $row->getMetricValues();
            echo "Source: " . $dims[0]->getValue() . " / Medium: " . $dims[1]->getValue() . "\n";
            echo "Users: " . $mets[0]->getValue() . "\n\n";
        }
    } else {
        echo "No traffic source data found for this tour.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getErrors')) {
        $errors = $e->getErrors();
        if (!empty($errors)) {
            echo "\nDetailed errors:\n";
            foreach ($errors as $error) {
                echo "- " . json_encode($error) . "\n";
            }
        }
    }
    exit(1);
}

echo "\nDone!\n";