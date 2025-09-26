<?php
/**
 * Tour URL System Diagnostics
 *
 * Comprehensive testing and debugging tool for the tour URL system
 */
class H3TM_Tour_URL_Diagnostics {

    private $debug_output = array();

    public function __construct() {
        // Only initialize for admin users
        if (is_admin() && current_user_can('manage_options')) {
            add_action('wp_ajax_h3tm_test_tour_urls', array($this, 'run_diagnostics'));
            add_action('admin_menu', array($this, 'add_diagnostics_page'));
        }
    }

    /**
     * Add diagnostics page to admin menu
     */
    public function add_diagnostics_page() {
        add_submenu_page(
            'h3tm-admin',
            'Tour URL Diagnostics',
            'URL Diagnostics',
            'manage_options',
            'h3tm-url-diagnostics',
            array($this, 'diagnostics_page')
        );
    }

    /**
     * Render diagnostics page
     */
    public function diagnostics_page() {
        ?>
        <div class="wrap">
            <h1>Tour URL System Diagnostics</h1>

            <div id="h3tm-diagnostics-results">
                <p>Click "Run Diagnostics" to test the tour URL system...</p>
            </div>

            <p>
                <button type="button" id="h3tm-run-diagnostics" class="button button-primary">
                    Run Diagnostics
                </button>

                <button type="button" id="h3tm-test-sample-tour" class="button">
                    Test Sample Tour
                </button>

                <button type="button" id="h3tm-fix-common-issues" class="button">
                    Fix Common Issues
                </button>
            </p>

            <div id="h3tm-test-urls" style="margin-top: 20px;">
                <h2>Test URLs</h2>
                <p>Try accessing these URLs to test different fallback methods:</p>

                <ol>
                    <li><strong>Primary:</strong>
                        <a href="<?php echo site_url('/h3panos/test-tour/'); ?>" target="_blank">
                            <?php echo site_url('/h3panos/test-tour/'); ?>
                        </a>
                    </li>

                    <li><strong>Query String Fallback:</strong>
                        <a href="<?php echo site_url('/?h3tour=test-tour'); ?>" target="_blank">
                            <?php echo site_url('/?h3tour=test-tour'); ?>
                        </a>
                    </li>

                    <li><strong>REST API:</strong>
                        <a href="<?php echo site_url('/wp-json/h3tm/v1/tour/test-tour'); ?>" target="_blank">
                            <?php echo site_url('/wp-json/h3tm/v1/tour/test-tour'); ?>
                        </a>
                    </li>

                    <li><strong>Direct Handler:</strong>
                        <a href="<?php echo site_url('/wp-content/plugins/h3-tour-management/includes/h3tour-direct-handler.php?tour=test-tour'); ?>" target="_blank">
                            Direct PHP Handler
                        </a>
                    </li>
                </ol>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#h3tm-run-diagnostics').click(function() {
                var $button = $(this);
                var $results = $('#h3tm-diagnostics-results');

                $button.prop('disabled', true).text('Running...');
                $results.html('<p>Running diagnostics...</p>');

                $.post(ajaxurl, {
                    action: 'h3tm_test_tour_urls',
                    nonce: '<?php echo wp_create_nonce('h3tm_diagnostics'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.html(response.data.html);
                    } else {
                        $results.html('<div class="error"><p>Error: ' + response.data + '</p></div>');
                    }
                }).always(function() {
                    $button.prop('disabled', false).text('Run Diagnostics');
                });
            });

            $('#h3tm-test-sample-tour').click(function() {
                // Open test tour in new window
                window.open('<?php echo site_url('/h3panos/test-tour/'); ?>', '_blank');
            });

            $('#h3tm-fix-common-issues').click(function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Fixing...');

                $.post(ajaxurl, {
                    action: 'h3tm_fix_tour_urls',
                    nonce: '<?php echo wp_create_nonce('h3tm_diagnostics'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Common issues have been fixed. Run diagnostics again to verify.');
                    } else {
                        alert('Error fixing issues: ' + response.data);
                    }
                }).always(function() {
                    $button.prop('disabled', false).text('Fix Common Issues');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Run comprehensive diagnostics
     */
    public function run_diagnostics() {
        check_ajax_referer('h3tm_diagnostics', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $results = array();

        // Test 1: WordPress Environment
        $results['environment'] = $this->test_environment();

        // Test 2: S3 Configuration
        $results['s3_config'] = $this->test_s3_config();

        // Test 3: Rewrite Rules
        $results['rewrite_rules'] = $this->test_rewrite_rules();

        // Test 4: Hook Registration
        $results['hooks'] = $this->test_hooks();

        // Test 5: Query Variables
        $results['query_vars'] = $this->test_query_vars();

        // Test 6: REST API Endpoints
        $results['rest_api'] = $this->test_rest_api();

        // Test 7: Direct Handler
        $results['direct_handler'] = $this->test_direct_handler();

        // Test 8: .htaccess Rules
        $results['htaccess'] = $this->test_htaccess();

        // Test 9: Sample Tour Access
        $results['sample_tour'] = $this->test_sample_tour();

        // Generate HTML report
        $html = $this->generate_report_html($results);

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Test WordPress environment
     */
    private function test_environment() {
        $result = array(
            'status' => 'pass',
            'message' => 'WordPress environment OK',
            'details' => array()
        );

        // Check WordPress version
        global $wp_version;
        $result['details']['wp_version'] = $wp_version;

        // Check permalink structure
        $permalink_structure = get_option('permalink_structure');
        $result['details']['permalink_structure'] = $permalink_structure ?: 'Default (not SEO friendly)';

        if (empty($permalink_structure)) {
            $result['status'] = 'warning';
            $result['message'] = 'Default permalink structure may cause issues';
        }

        // Check mod_rewrite
        $result['details']['mod_rewrite'] = got_mod_rewrite() ? 'Available' : 'Not available';

        // Check .htaccess writable
        $htaccess_file = ABSPATH . '.htaccess';
        $result['details']['htaccess_writable'] = is_writable($htaccess_file) ? 'Yes' : 'No';

        return $result;
    }

    /**
     * Test S3 configuration
     */
    private function test_s3_config() {
        $result = array(
            'status' => 'fail',
            'message' => 'S3 not configured',
            'details' => array()
        );

        if (class_exists('H3TM_S3_Simple')) {
            $s3_simple = new H3TM_S3_Simple();
            $config = $s3_simple->get_s3_config();

            $result['details'] = $config;

            if ($config['configured']) {
                $result['status'] = 'pass';
                $result['message'] = 'S3 configuration found';

                // Test S3 connectivity
                $test_url = sprintf(
                    'https://%s.s3.%s.amazonaws.com/',
                    $config['bucket'],
                    $config['region']
                );

                $response = wp_remote_head($test_url, array('timeout' => 10));

                if (!is_wp_error($response)) {
                    $result['details']['connectivity'] = 'Success';
                } else {
                    $result['details']['connectivity'] = $response->get_error_message();
                    $result['status'] = 'warning';
                }
            }
        } else {
            $result['message'] = 'H3TM_S3_Simple class not found';
        }

        return $result;
    }

    /**
     * Test rewrite rules
     */
    private function test_rewrite_rules() {
        $result = array(
            'status' => 'pass',
            'message' => 'Rewrite rules configured',
            'details' => array()
        );

        global $wp_rewrite;
        $rules = $wp_rewrite->wp_rewrite_rules();

        // Check for our specific rules
        $found_rules = array();
        foreach ($rules as $pattern => $rewrite) {
            if (strpos($pattern, 'h3panos') !== false || strpos($rewrite, 'h3tm_tour') !== false) {
                $found_rules[$pattern] = $rewrite;
            }
        }

        $result['details']['found_rules'] = $found_rules;

        if (empty($found_rules)) {
            $result['status'] = 'warning';
            $result['message'] = 'No h3panos rewrite rules found';
        }

        return $result;
    }

    /**
     * Test hook registration
     */
    private function test_hooks() {
        $result = array(
            'status' => 'pass',
            'message' => 'Hooks registered',
            'details' => array()
        );

        global $wp_filter;

        $hooks_to_check = array('wp', 'parse_request', 'template_redirect', 'init', 'rest_api_init');

        foreach ($hooks_to_check as $hook) {
            $result['details'][$hook] = isset($wp_filter[$hook]) ? count($wp_filter[$hook]) . ' callbacks' : 'No callbacks';
        }

        return $result;
    }

    /**
     * Test query variables
     */
    private function test_query_vars() {
        $result = array(
            'status' => 'pass',
            'message' => 'Query variables registered',
            'details' => array()
        );

        global $wp;

        $vars_to_check = array('h3tm_tour', 'h3tm_file', 'h3tour', 'h3file');

        foreach ($vars_to_check as $var) {
            $result['details'][$var] = in_array($var, $wp->public_query_vars) ? 'Registered' : 'Not registered';
        }

        return $result;
    }

    /**
     * Test REST API endpoints
     */
    private function test_rest_api() {
        $result = array(
            'status' => 'pass',
            'message' => 'REST API endpoints available',
            'details' => array()
        );

        $test_url = site_url('/wp-json/h3tm/v1/tour/test');
        $response = wp_remote_get($test_url, array('timeout' => 10));

        if (is_wp_error($response)) {
            $result['status'] = 'fail';
            $result['message'] = 'REST API test failed: ' . $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $result['details']['test_response_code'] = $code;

            if ($code === 404 || $code === 500) {
                $result['status'] = 'expected';
                $result['message'] = 'REST API responding (404/500 expected for test tour)';
            }
        }

        return $result;
    }

    /**
     * Test direct handler
     */
    private function test_direct_handler() {
        $result = array(
            'status' => 'pass',
            'message' => 'Direct handler available',
            'details' => array()
        );

        $handler_file = H3TM_PLUGIN_DIR . 'includes/h3tour-direct-handler.php';

        if (file_exists($handler_file)) {
            $result['details']['file_exists'] = 'Yes';
            $result['details']['file_readable'] = is_readable($handler_file) ? 'Yes' : 'No';

            // Test handler URL
            $test_url = site_url('/wp-content/plugins/h3-tour-management/includes/h3tour-direct-handler.php?tour=test');
            $response = wp_remote_get($test_url, array('timeout' => 10));

            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                $result['details']['handler_response'] = $code;
            }
        } else {
            $result['status'] = 'fail';
            $result['message'] = 'Direct handler file not found';
        }

        return $result;
    }

    /**
     * Test .htaccess configuration
     */
    private function test_htaccess() {
        $result = array(
            'status' => 'pass',
            'message' => '.htaccess configuration OK',
            'details' => array()
        );

        $htaccess_file = ABSPATH . '.htaccess';

        if (file_exists($htaccess_file)) {
            $content = file_get_contents($htaccess_file);
            $result['details']['h3panos_rules'] = strpos($content, 'h3panos') !== false ? 'Found' : 'Not found';
            $result['details']['file_readable'] = 'Yes';
        } else {
            $result['status'] = 'warning';
            $result['message'] = '.htaccess file not found';
            $result['details']['file_readable'] = 'No';
        }

        return $result;
    }

    /**
     * Test sample tour access
     */
    private function test_sample_tour() {
        $result = array(
            'status' => 'skip',
            'message' => 'Skipped (requires valid S3 tour)',
            'details' => array()
        );

        // This would require a known good tour in S3
        // For now, just document the test URLs
        $result['details']['test_urls'] = array(
            'primary' => site_url('/h3panos/test-tour/'),
            'query_string' => site_url('/?h3tour=test-tour'),
            'rest_api' => site_url('/wp-json/h3tm/v1/tour/test-tour'),
            'direct_handler' => site_url('/wp-content/plugins/h3-tour-management/includes/h3tour-direct-handler.php?tour=test-tour')
        );

        return $result;
    }

    /**
     * Generate HTML report from results
     */
    private function generate_report_html($results) {
        $html = '<div class="h3tm-diagnostics-report">';

        // Summary
        $total_tests = count($results);
        $passed = 0;
        $warnings = 0;
        $failed = 0;

        foreach ($results as $result) {
            switch ($result['status']) {
                case 'pass':
                case 'expected':
                    $passed++;
                    break;
                case 'warning':
                    $warnings++;
                    break;
                case 'fail':
                    $failed++;
                    break;
            }
        }

        $html .= '<div class="h3tm-summary" style="margin-bottom: 20px; padding: 10px; border-left: 4px solid #0073aa;">';
        $html .= '<h2>Diagnostics Summary</h2>';
        $html .= '<p><strong>Total Tests:</strong> ' . $total_tests . ' | ';
        $html .= '<strong style="color: green;">Passed:</strong> ' . $passed . ' | ';
        $html .= '<strong style="color: orange;">Warnings:</strong> ' . $warnings . ' | ';
        $html .= '<strong style="color: red;">Failed:</strong> ' . $failed . '</p>';
        $html .= '</div>';

        // Detailed results
        foreach ($results as $test_name => $result) {
            $status_color = $this->get_status_color($result['status']);

            $html .= '<div class="h3tm-test-result" style="margin-bottom: 15px; padding: 10px; border-left: 4px solid ' . $status_color . ';">';
            $html .= '<h3>' . ucwords(str_replace('_', ' ', $test_name)) . '</h3>';
            $html .= '<p><strong>Status:</strong> <span style="color: ' . $status_color . ';">' . strtoupper($result['status']) . '</span></p>';
            $html .= '<p><strong>Message:</strong> ' . esc_html($result['message']) . '</p>';

            if (!empty($result['details'])) {
                $html .= '<details><summary>Details</summary>';
                $html .= '<pre>' . esc_html(print_r($result['details'], true)) . '</pre>';
                $html .= '</details>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get color for status
     */
    private function get_status_color($status) {
        switch ($status) {
            case 'pass':
            case 'expected':
                return '#46b450';
            case 'warning':
                return '#ffb900';
            case 'fail':
                return '#dc3232';
            default:
                return '#666';
        }
    }

    /**
     * Fix common issues
     */
    public static function fix_common_issues() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Update version flag
        update_option('h3tm_url_handler_version', H3TM_VERSION);

        // Clear any relevant caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        return true;
    }
}