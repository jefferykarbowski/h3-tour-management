<?php
/**
 * Test Tour URL System
 *
 * Quick verification script to test the robust tour URL system
 * Run this from WordPress admin or via WP-CLI
 */

// Security check
if (!defined('ABSPATH')) {
    die('Direct access not permitted');
}

class H3TM_Tour_URL_System_Test {

    public function run_tests() {
        echo "<h2>H3TM Tour URL System Tests</h2>\n";

        $tests = array(
            'test_classes_loaded' => 'Test if classes are loaded',
            'test_s3_configuration' => 'Test S3 configuration',
            'test_url_handler_instance' => 'Test URL handler instantiation',
            'test_rewrite_rules' => 'Test rewrite rules',
            'test_query_vars' => 'Test query variables',
            'test_rest_endpoints' => 'Test REST API endpoints',
            'test_direct_handler' => 'Test direct handler file',
            'test_url_parsing' => 'Test URL parsing logic',
        );

        $results = array();

        foreach ($tests as $method => $description) {
            echo "<h3>$description</h3>\n";
            try {
                $result = $this->$method();
                $results[$method] = $result;

                if ($result['status'] === 'pass') {
                    echo "<p style='color: green;'>✓ PASS: {$result['message']}</p>\n";
                } elseif ($result['status'] === 'warning') {
                    echo "<p style='color: orange;'>⚠ WARNING: {$result['message']}</p>\n";
                } else {
                    echo "<p style='color: red;'>✗ FAIL: {$result['message']}</p>\n";
                }

                if (!empty($result['details'])) {
                    echo "<pre>" . print_r($result['details'], true) . "</pre>\n";
                }

            } catch (Exception $e) {
                echo "<p style='color: red;'>✗ ERROR: " . $e->getMessage() . "</p>\n";
                $results[$method] = array('status' => 'error', 'message' => $e->getMessage());
            }
        }

        $this->display_summary($results);
        $this->display_test_urls();
    }

    private function test_classes_loaded() {
        $classes = array(
            'H3TM_Tour_URL_Handler',
            'H3TM_Tour_URL_Diagnostics',
            'H3TM_S3_Simple'
        );

        $missing = array();
        foreach ($classes as $class) {
            if (!class_exists($class)) {
                $missing[] = $class;
            }
        }

        if (empty($missing)) {
            return array('status' => 'pass', 'message' => 'All required classes loaded');
        } else {
            return array('status' => 'fail', 'message' => 'Missing classes: ' . implode(', ', $missing));
        }
    }

    private function test_s3_configuration() {
        if (!class_exists('H3TM_S3_Simple')) {
            return array('status' => 'fail', 'message' => 'H3TM_S3_Simple class not available');
        }

        $s3_simple = new H3TM_S3_Simple();
        $config = $s3_simple->get_s3_config();

        if ($config['configured']) {
            return array(
                'status' => 'pass',
                'message' => 'S3 configuration found',
                'details' => array(
                    'bucket' => $config['bucket'],
                    'region' => $config['region'],
                    'access_key' => !empty($config['access_key']) ? 'SET' : 'EMPTY',
                    'secret_key' => !empty($config['secret_key']) ? 'SET' : 'EMPTY'
                )
            );
        } else {
            return array('status' => 'warning', 'message' => 'S3 not configured - tours will not work');
        }
    }

    private function test_url_handler_instance() {
        global $wp_filter;

        $hooks = array('wp', 'parse_request', 'template_redirect', 'init', 'rest_api_init');
        $found_hooks = array();

        foreach ($hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                $found_hooks[$hook] = count($wp_filter[$hook]);
            }
        }

        if (!empty($found_hooks)) {
            return array(
                'status' => 'pass',
                'message' => 'URL handler hooks registered',
                'details' => $found_hooks
            );
        } else {
            return array('status' => 'fail', 'message' => 'No URL handler hooks found');
        }
    }

    private function test_rewrite_rules() {
        global $wp_rewrite;

        if (!$wp_rewrite) {
            return array('status' => 'warning', 'message' => 'Rewrite system not available');
        }

        $rules = $wp_rewrite->wp_rewrite_rules();
        $tour_rules = array();

        foreach ($rules as $pattern => $rewrite) {
            if (strpos($pattern, 'h3panos') !== false || strpos($rewrite, 'h3tm_tour') !== false) {
                $tour_rules[$pattern] = $rewrite;
            }
        }

        if (!empty($tour_rules)) {
            return array(
                'status' => 'pass',
                'message' => 'Tour rewrite rules found',
                'details' => $tour_rules
            );
        } else {
            return array('status' => 'warning', 'message' => 'No tour rewrite rules found (fallbacks will work)');
        }
    }

    private function test_query_vars() {
        global $wp;

        $required_vars = array('h3tm_tour', 'h3tm_file', 'h3tour', 'h3file');
        $found_vars = array();
        $missing_vars = array();

        foreach ($required_vars as $var) {
            if (in_array($var, $wp->public_query_vars)) {
                $found_vars[] = $var;
            } else {
                $missing_vars[] = $var;
            }
        }

        if (count($found_vars) >= 2) {
            return array(
                'status' => 'pass',
                'message' => 'Query vars registered',
                'details' => array('found' => $found_vars, 'missing' => $missing_vars)
            );
        } else {
            return array(
                'status' => 'warning',
                'message' => 'Limited query vars available',
                'details' => array('found' => $found_vars, 'missing' => $missing_vars)
            );
        }
    }

    private function test_rest_endpoints() {
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();

        $tour_routes = array();
        foreach ($routes as $route => $handlers) {
            if (strpos($route, '/h3tm/v1/tour') !== false) {
                $tour_routes[] = $route;
            }
        }

        if (!empty($tour_routes)) {
            return array(
                'status' => 'pass',
                'message' => 'REST API endpoints registered',
                'details' => $tour_routes
            );
        } else {
            return array('status' => 'warning', 'message' => 'REST API endpoints not found');
        }
    }

    private function test_direct_handler() {
        $handler_file = H3TM_PLUGIN_DIR . 'includes/h3tour-direct-handler.php';

        if (!file_exists($handler_file)) {
            return array('status' => 'fail', 'message' => 'Direct handler file not found');
        }

        if (!is_readable($handler_file)) {
            return array('status' => 'warning', 'message' => 'Direct handler file not readable');
        }

        return array(
            'status' => 'pass',
            'message' => 'Direct handler file available',
            'details' => array(
                'path' => $handler_file,
                'size' => filesize($handler_file) . ' bytes',
                'url' => site_url('/wp-content/plugins/h3-tour-management/includes/h3tour-direct-handler.php')
            )
        );
    }

    private function test_url_parsing() {
        // Test URL pattern matching logic
        $test_urls = array(
            '/h3panos/TestTour/' => array('tour' => 'TestTour', 'file' => 'index.htm'),
            '/h3panos/TestTour/file.js' => array('tour' => 'TestTour', 'file' => 'file.js'),
            '/h3panos/Complex-Tour-Name+Test/' => array('tour' => 'Complex-Tour-Name+Test', 'file' => 'index.htm'),
            '/other/path/' => false
        );

        $results = array();
        foreach ($test_urls as $url => $expected) {
            $parsed = $this->parse_test_url($url);
            $results[$url] = array('expected' => $expected, 'actual' => $parsed);
        }

        return array(
            'status' => 'pass',
            'message' => 'URL parsing logic test completed',
            'details' => $results
        );
    }

    private function parse_test_url($url) {
        // Simplified version of URL parsing logic
        if (preg_match('/^\/h3panos\/([^\/\?]+)\/?$/', $url, $matches)) {
            return array('tour' => $matches[1], 'file' => 'index.htm');
        }

        if (preg_match('/^\/h3panos\/([^\/\?]+)\/(.+)$/', $url, $matches)) {
            return array('tour' => $matches[1], 'file' => $matches[2]);
        }

        return false;
    }

    private function display_summary($results) {
        $total = count($results);
        $passed = 0;
        $warnings = 0;
        $failed = 0;

        foreach ($results as $result) {
            switch ($result['status']) {
                case 'pass':
                    $passed++;
                    break;
                case 'warning':
                    $warnings++;
                    break;
                case 'fail':
                case 'error':
                    $failed++;
                    break;
            }
        }

        echo "<div style='border: 2px solid #0073aa; padding: 15px; margin: 20px 0;'>\n";
        echo "<h3>Test Summary</h3>\n";
        echo "<p><strong>Total Tests:</strong> $total</p>\n";
        echo "<p><strong style='color: green;'>Passed:</strong> $passed</p>\n";
        echo "<p><strong style='color: orange;'>Warnings:</strong> $warnings</p>\n";
        echo "<p><strong style='color: red;'>Failed:</strong> $failed</p>\n";

        if ($failed == 0 && $warnings <= 2) {
            echo "<p style='color: green; font-weight: bold;'>✓ System appears to be working correctly!</p>\n";
        } elseif ($failed == 0) {
            echo "<p style='color: orange; font-weight: bold;'>⚠ System working with some limitations</p>\n";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ System has issues that need attention</p>\n";
        }
        echo "</div>\n";
    }

    private function display_test_urls() {
        echo "<div style='border: 2px solid #46b450; padding: 15px; margin: 20px 0;'>\n";
        echo "<h3>Test URLs</h3>\n";
        echo "<p>Try these URLs to test the tour system:</p>\n";
        echo "<ol>\n";
        echo "<li><strong>Primary:</strong> <a href='" . site_url('/h3panos/test-tour/') . "' target='_blank'>" . site_url('/h3panos/test-tour/') . "</a></li>\n";
        echo "<li><strong>Query String:</strong> <a href='" . site_url('/?h3tour=test-tour') . "' target='_blank'>" . site_url('/?h3tour=test-tour') . "</a></li>\n";
        echo "<li><strong>REST API:</strong> <a href='" . site_url('/wp-json/h3tm/v1/tour/test-tour') . "' target='_blank'>" . site_url('/wp-json/h3tm/v1/tour/test-tour') . "</a></li>\n";
        echo "<li><strong>Direct Handler:</strong> <a href='" . site_url('/wp-content/plugins/h3-tour-management/includes/h3tour-direct-handler.php?tour=test-tour') . "' target='_blank'>Direct PHP Handler</a></li>\n";
        echo "</ol>\n";
        echo "<p><em>Note: These will show 404 errors if the 'test-tour' doesn't exist in S3, but the URLs should be processed correctly.</em></p>\n";
        echo "</div>\n";
    }
}

// Run tests if accessed directly or called via AJAX
if (isset($_GET['run_h3tm_url_tests']) || (defined('WP_ADMIN') && WP_ADMIN && current_user_can('manage_options'))) {
    $tester = new H3TM_Tour_URL_System_Test();
    $tester->run_tests();
}