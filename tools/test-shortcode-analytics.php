<?php
/**
 * Test Shortcode Analytics
 * Test what happens when the shortcode is called
 */

// Include WordPress
if (!defined('ABSPATH')) {
    // Adjust the path as needed to point to wp-config.php
    require_once realpath(dirname(__FILE__) . '/../../../../wp-config.php');
}

echo "=== H3TM Shortcode Analytics Test ===\n\n";

// Test 1: Check if classes exist
echo "1. Checking class availability:\n";
echo "   - H3TM_Shortcodes_V4: " . (class_exists('H3TM_Shortcodes_V4') ? "✓ Available" : "✗ Missing") . "\n";
echo "   - H3TM_Config: " . (class_exists('H3TM_Config') ? "✓ Available" : "✗ Missing") . "\n";
echo "   - H3TM_Tour_Manager: " . (class_exists('H3TM_Tour_Manager') ? "✓ Available" : "✗ Missing") . "\n\n";

// Test 2: Check configuration paths
echo "2. Checking configuration paths:\n";
try {
    $credentials_path = H3TM_Config::get_credentials_path();
    echo "   - Credentials Path: " . $credentials_path . "\n";
    echo "   - Credentials Exist: " . (file_exists($credentials_path) ? "✓ Yes" : "✗ No") . "\n";
    
    $autoload_path = H3TM_Config::get_autoload_path();
    echo "   - Autoload Path: " . $autoload_path . "\n";
    echo "   - Autoload Exist: " . (file_exists($autoload_path) ? "✓ Yes" : "✗ No") . "\n";
    
    echo "   - SSL Verification: " . (H3TM_Config::should_verify_ssl() ? "✓ Enabled" : "✗ Disabled") . "\n";
    echo "   - Development Mode: " . (H3TM_Config::is_development() ? "✓ Yes" : "✗ No") . "\n";
    
} catch (Exception $e) {
    echo "   - Configuration Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Test shortcode initialization
echo "3. Testing shortcode initialization:\n";
try {
    $shortcode = new H3TM_Shortcodes_V4();
    echo "   - Shortcode Creation: ✓ Success\n";
    
    // Check if shortcode is registered
    global $shortcode_tags;
    if (isset($shortcode_tags['tour_analytics_display'])) {
        echo "   - Shortcode Registration: ✓ Success\n";
    } else {
        echo "   - Shortcode Registration: ✗ Failed\n";
    }
} catch (Exception $e) {
    echo "   - Shortcode Initialization Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Test with a fake user simulation
echo "4. Testing shortcode execution (simulation):\n";

// Create a temporary user for testing
$test_user_id = wp_create_user('test_analytics_' . time(), wp_generate_password(), 'test@example.com');
if (is_wp_error($test_user_id)) {
    echo "   - User Creation: ✗ Failed - " . $test_user_id->get_error_message() . "\n";
} else {
    echo "   - Test User Created: ✓ ID $test_user_id\n";
    
    // Set user meta with fake tours
    update_user_meta($test_user_id, 'h3tm_tours', array('sample-tour'));
    echo "   - Test Tours Assigned: ✓ sample-tour\n";
    
    // Simulate logged in user
    wp_set_current_user($test_user_id);
    echo "   - User Login Simulated: ✓ Success\n";
    
    try {
        // Test shortcode execution
        $shortcode = new H3TM_Shortcodes_V4();
        ob_start();
        $output = $shortcode->tour_analytics_display_shortcode(array());
        ob_end_clean();
        
        if (empty($output)) {
            echo "   - Shortcode Output: ✗ Empty\n";
        } else if (strpos($output, 'error') !== false || strpos($output, 'Error') !== false) {
            echo "   - Shortcode Output: ✗ Contains Error\n";
            // Extract error message
            if (preg_match('/<div class="analytics-error"[^>]*>(.*?)<\/div>/s', $output, $matches)) {
                echo "   - Error Details: " . strip_tags($matches[1]) . "\n";
            }
        } else if (strpos($output, 'analytics-container') !== false) {
            echo "   - Shortcode Output: ✓ Generated HTML\n";
        } else {
            echo "   - Shortcode Output: ? Unknown format\n";
        }
        
        // Check for specific analytics data
        if (strpos($output, 'number_format') !== false || strpos($output, '<div class="metric-value">') !== false) {
            echo "   - Analytics Data: ✓ Present\n";
        } else {
            echo "   - Analytics Data: ✗ Missing\n";
        }
        
    } catch (Exception $e) {
        echo "   - Shortcode Execution Error: " . $e->getMessage() . "\n";
    }
    
    // Clean up
    wp_delete_user($test_user_id);
    echo "   - Test User Cleanup: ✓ Deleted\n";
}
echo "\n";

// Test 5: Test Google Analytics initialization directly
echo "5. Testing Google Analytics initialization:\n";
try {
    $shortcode = new H3TM_Shortcodes_V4();
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($shortcode);
    $method = $reflection->getMethod('initialize_analytics');
    $method->setAccessible(true);
    
    $analytics_service = $method->invoke($shortcode);
    
    if ($analytics_service && is_object($analytics_service)) {
        echo "   - Analytics Service: ✓ Initialized\n";
        echo "   - Service Class: " . get_class($analytics_service) . "\n";
        
        // Test a simple API call
        $PROPERTY_ID = "properties/491286260";
        $response = $analytics_service->properties->runReport($PROPERTY_ID, new Google_Service_AnalyticsData_RunReportRequest());
        echo "   - API Test Call: ✓ Success\n";
        
    } else {
        echo "   - Analytics Service: ✗ Failed to initialize\n";
    }
    
} catch (Exception $e) {
    echo "   - Analytics Initialization Error: " . $e->getMessage() . "\n";
    
    // Check for specific error types
    if (strpos($e->getMessage(), 'not found') !== false) {
        echo "   - Error Type: Missing file/library\n";
    } else if (strpos($e->getMessage(), '403') !== false || strpos($e->getMessage(), 'Forbidden') !== false) {
        echo "   - Error Type: Permission denied\n";
    } else if (strpos($e->getMessage(), '401') !== false || strpos($e->getMessage(), 'Unauthorized') !== false) {
        echo "   - Error Type: Authentication failed\n";
    }
}

echo "\n=== Test Complete ===\n";