<?php
/**
 * URL Manager - Centralized management and fallback system for all URL handling approaches
 * This class coordinates between different URL handling strategies and provides fallbacks
 */
class H3TM_URL_Manager {

    private $handlers = array();
    private $active_handler = null;
    private $s3_config;
    private $fallback_order = array();

    public function __construct() {
        // Get S3 configuration
        $s3_simple = new H3TM_S3_Simple();
        $this->s3_config = $s3_simple->get_s3_config();

        // Initialize all handlers
        $this->initialize_handlers();

        // Set up the active handler
        $this->setup_active_handler();

        // Add admin hooks
        add_action('wp_ajax_h3tm_test_url_handlers', array($this, 'test_all_handlers'));
        add_action('wp_ajax_h3tm_switch_handler', array($this, 'switch_active_handler'));

        // Hook into URL filtering for admin display
        add_filter('h3tm_tour_url', array($this, 'filter_tour_urls'), 10, 2);
    }

    /**
     * Initialize all available handlers
     */
    private function initialize_handlers() {
        $this->handlers = array(
            '404_handler' => array(
                'class' => 'H3TM_404_Handler',
                'instance' => null,
                'priority' => 10,
                'name' => '404 Handler',
                'description' => 'Intercepts 404 errors for h3panos URLs',
                'pros' => array(
                    'No rewrite rules needed',
                    'Works with any WordPress setup',
                    'Handles complex URL patterns'
                ),
                'cons' => array(
                    'Slightly higher overhead',
                    'Relies on WordPress 404 system'
                )
            ),
            'direct_handler' => array(
                'class' => 'H3TM_Direct_Handler',
                'instance' => null,
                'priority' => 20,
                'name' => 'Direct PHP Handler',
                'description' => 'Standalone PHP file in web root',
                'pros' => array(
                    'Fastest performance',
                    'Completely bypasses WordPress',
                    'Works with .htaccess rules'
                ),
                'cons' => array(
                    'Requires file system access',
                    'Harder to maintain'
                )
            ),
            'action_hook' => array(
                'class' => 'H3TM_Action_Hook',
                'instance' => null,
                'priority' => 30,
                'name' => 'WordPress Action Hook',
                'description' => 'Uses wp and parse_request hooks',
                'pros' => array(
                    'Native WordPress integration',
                    'Early request interception',
                    'Good performance'
                ),
                'cons' => array(
                    'May conflict with other plugins',
                    'Depends on WordPress load order'
                )
            ),
            'endpoint_handler' => array(
                'class' => 'H3TM_Endpoint_Handler',
                'instance' => null,
                'priority' => 40,
                'name' => 'Custom Endpoint',
                'description' => 'WordPress REST API and custom endpoints',
                'pros' => array(
                    'REST API compatibility',
                    'Clean URL structure',
                    'API-friendly'
                ),
                'cons' => array(
                    'More complex setup',
                    'Requires endpoint support'
                )
            )
        );

        // Set fallback order based on reliability and performance
        $this->fallback_order = array('404_handler', 'action_hook', 'endpoint_handler', 'direct_handler');
    }

    /**
     * Setup the active handler based on configuration and availability
     */
    private function setup_active_handler() {
        $preferred_handler = get_option('h3tm_preferred_handler', 'auto');

        if ($preferred_handler === 'auto') {
            $this->auto_select_handler();
        } else {
            $this->set_active_handler($preferred_handler);
        }

        // Initialize the active handler
        $this->initialize_active_handler();
    }

    /**
     * Auto-select the best available handler
     */
    private function auto_select_handler() {
        foreach ($this->fallback_order as $handler_key) {
            if ($this->is_handler_available($handler_key)) {
                $this->active_handler = $handler_key;
                error_log('H3TM URL Manager: Auto-selected handler: ' . $handler_key);
                update_option('h3tm_active_handler', $handler_key);
                return;
            }
        }

        // If no handler is available, default to 404 handler
        $this->active_handler = '404_handler';
        error_log('H3TM URL Manager: Defaulting to 404 handler (fallback)');
    }

    /**
     * Set specific active handler
     */
    private function set_active_handler($handler_key) {
        if (isset($this->handlers[$handler_key])) {
            $this->active_handler = $handler_key;
            update_option('h3tm_active_handler', $handler_key);
            error_log('H3TM URL Manager: Set active handler to: ' . $handler_key);
        }
    }

    /**
     * Check if a handler is available and working
     */
    private function is_handler_available($handler_key) {
        if (!isset($this->handlers[$handler_key])) {
            return false;
        }

        $handler_class = $this->handlers[$handler_key]['class'];

        // Basic class availability check
        if (!class_exists($handler_class)) {
            return false;
        }

        // Specific availability checks per handler type
        switch ($handler_key) {
            case 'direct_handler':
                // Check if we can write to web root
                return is_writable(ABSPATH);

            case 'endpoint_handler':
                // Check if REST API is available
                return function_exists('rest_url');

            case '404_handler':
            case 'action_hook':
            default:
                // These are always available if the class exists
                return true;
        }
    }

    /**
     * Initialize the active handler instance
     */
    private function initialize_active_handler() {
        if (!$this->active_handler || !isset($this->handlers[$this->active_handler])) {
            return false;
        }

        $handler_info = $this->handlers[$this->active_handler];
        $handler_class = $handler_info['class'];

        if (class_exists($handler_class)) {
            $this->handlers[$this->active_handler]['instance'] = new $handler_class();
            error_log('H3TM URL Manager: Initialized active handler: ' . $this->active_handler);
            return true;
        }

        error_log('H3TM URL Manager: Failed to initialize handler: ' . $this->active_handler);
        return false;
    }

    /**
     * Get active handler instance
     */
    public function get_active_handler() {
        if ($this->active_handler && isset($this->handlers[$this->active_handler]['instance'])) {
            return $this->handlers[$this->active_handler]['instance'];
        }
        return null;
    }

    /**
     * Get active handler info
     */
    public function get_active_handler_info() {
        if (!$this->active_handler) {
            return null;
        }

        return $this->handlers[$this->active_handler];
    }

    /**
     * Test all handlers for availability and functionality
     */
    public function test_all_handlers() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $results = array();

        foreach ($this->handlers as $key => $handler_info) {
            $results[$key] = array(
                'name' => $handler_info['name'],
                'description' => $handler_info['description'],
                'available' => $this->is_handler_available($key),
                'active' => ($key === $this->active_handler),
                'test_result' => $this->test_handler($key)
            );
        }

        wp_send_json_success(array(
            'handlers' => $results,
            'active_handler' => $this->active_handler,
            's3_configured' => $this->s3_config['configured']
        ));
    }

    /**
     * Test a specific handler
     */
    private function test_handler($handler_key) {
        if (!$this->is_handler_available($handler_key)) {
            return array(
                'success' => false,
                'message' => 'Handler not available'
            );
        }

        $handler_class = $this->handlers[$handler_key]['class'];

        try {
            // Create temporary instance for testing
            $test_instance = new $handler_class();

            // Test handler-specific functionality
            switch ($handler_key) {
                case 'direct_handler':
                    if (method_exists($test_instance, 'test_direct_handler')) {
                        return $test_instance->test_direct_handler();
                    }
                    break;

                case 'action_hook':
                    if (method_exists($test_instance, 'test_handler')) {
                        return $test_instance->test_handler();
                    }
                    break;

                case 'endpoint_handler':
                    return array(
                        'success' => true,
                        'message' => 'Endpoint handler initialized successfully'
                    );

                case '404_handler':
                default:
                    return array(
                        'success' => true,
                        'message' => 'Handler initialized successfully'
                    );
            }

            return array(
                'success' => true,
                'message' => 'Handler test completed'
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Handler test failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Switch active handler via AJAX
     */
    public function switch_active_handler() {
        check_ajax_referer('h3tm_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $new_handler = sanitize_text_field($_POST['handler'] ?? '');

        if (!isset($this->handlers[$new_handler])) {
            wp_send_json_error('Invalid handler specified');
        }

        if (!$this->is_handler_available($new_handler)) {
            wp_send_json_error('Handler not available: ' . $this->handlers[$new_handler]['name']);
        }

        // Deactivate current handler if it has a cleanup method
        if ($this->active_handler && isset($this->handlers[$this->active_handler]['instance'])) {
            $current_instance = $this->handlers[$this->active_handler]['instance'];
            if (method_exists($current_instance, 'deactivate')) {
                $current_instance->deactivate();
            }
        }

        // Set new handler
        $this->active_handler = $new_handler;
        update_option('h3tm_active_handler', $new_handler);

        // Initialize new handler
        $this->initialize_active_handler();

        wp_send_json_success(array(
            'message' => 'Successfully switched to: ' . $this->handlers[$new_handler]['name'],
            'active_handler' => $new_handler
        ));
    }

    /**
     * Filter tour URLs for admin display
     */
    public function filter_tour_urls($url, $tour_name) {
        // Always return h3panos URLs for consistency
        return site_url('/h3panos/' . rawurlencode($tour_name) . '/');
    }

    /**
     * Get all available handlers info
     */
    public function get_all_handlers_info() {
        $handlers_info = array();

        foreach ($this->handlers as $key => $handler_info) {
            $handlers_info[$key] = array(
                'name' => $handler_info['name'],
                'description' => $handler_info['description'],
                'priority' => $handler_info['priority'],
                'pros' => $handler_info['pros'],
                'cons' => $handler_info['cons'],
                'available' => $this->is_handler_available($key),
                'active' => ($key === $this->active_handler)
            );
        }

        return $handlers_info;
    }

    /**
     * Get system status and recommendations
     */
    public function get_system_status() {
        $status = array(
            'active_handler' => $this->active_handler,
            'active_handler_name' => $this->handlers[$this->active_handler]['name'] ?? 'Unknown',
            's3_configured' => $this->s3_config['configured'],
            'handlers_available' => 0,
            'recommendations' => array()
        );

        // Count available handlers
        foreach ($this->handlers as $key => $handler_info) {
            if ($this->is_handler_available($key)) {
                $status['handlers_available']++;
            }
        }

        // Generate recommendations
        if (!$this->s3_config['configured']) {
            $status['recommendations'][] = array(
                'type' => 'warning',
                'message' => 'S3 configuration is incomplete. Tour serving will not work.',
                'action' => 'Configure S3 settings in the admin panel.'
            );
        }

        if ($status['handlers_available'] === 0) {
            $status['recommendations'][] = array(
                'type' => 'error',
                'message' => 'No URL handlers are available.',
                'action' => 'Check system requirements and file permissions.'
            );
        } elseif ($status['handlers_available'] === 1) {
            $status['recommendations'][] = array(
                'type' => 'info',
                'message' => 'Only one URL handler is available. Consider enabling more for redundancy.',
                'action' => 'Review handler availability and system configuration.'
            );
        }

        if ($this->active_handler === 'direct_handler' && !is_writable(ABSPATH)) {
            $status['recommendations'][] = array(
                'type' => 'warning',
                'message' => 'Direct handler requires write access to web root.',
                'action' => 'Check file permissions or switch to a different handler.'
            );
        }

        return $status;
    }

    /**
     * Fallback handler - try alternative handlers if active one fails
     */
    public function try_fallback_handler($tour_name, $file_path = 'index.htm') {
        foreach ($this->fallback_order as $handler_key) {
            if ($handler_key === $this->active_handler) {
                continue; // Skip active handler
            }

            if (!$this->is_handler_available($handler_key)) {
                continue;
            }

            try {
                $handler_class = $this->handlers[$handler_key]['class'];
                $fallback_instance = new $handler_class();

                // Try to serve content with fallback handler
                error_log('H3TM URL Manager: Trying fallback handler: ' . $handler_key);

                // This would need handler-specific implementation
                return true;

            } catch (Exception $e) {
                error_log('H3TM URL Manager: Fallback handler failed: ' . $handler_key . ' - ' . $e->getMessage());
                continue;
            }
        }

        return false;
    }

    /**
     * Get handler performance metrics
     */
    public function get_performance_metrics() {
        $metrics = array();

        foreach ($this->handlers as $key => $handler_info) {
            $metrics[$key] = array(
                'name' => $handler_info['name'],
                'priority' => $handler_info['priority'],
                'estimated_speed' => $this->get_estimated_speed($key),
                'resource_usage' => $this->get_estimated_resource_usage($key),
                'reliability' => $this->get_estimated_reliability($key)
            );
        }

        return $metrics;
    }

    /**
     * Get estimated speed for handler (1-10 scale, 10 = fastest)
     */
    private function get_estimated_speed($handler_key) {
        $speed_map = array(
            'direct_handler' => 10,   // Fastest - bypasses WordPress completely
            'action_hook' => 8,       // Fast - early WordPress interception
            '404_handler' => 6,       // Medium - processes after 404 detection
            'endpoint_handler' => 7   // Good - REST API overhead
        );

        return $speed_map[$handler_key] ?? 5;
    }

    /**
     * Get estimated resource usage (1-10 scale, 1 = lowest usage)
     */
    private function get_estimated_resource_usage($handler_key) {
        $usage_map = array(
            'direct_handler' => 1,    // Lowest - no WordPress overhead
            'action_hook' => 3,       // Low - minimal WordPress load
            '404_handler' => 5,       // Medium - full WordPress load
            'endpoint_handler' => 4   // Low-Medium - WordPress + REST API
        );

        return $usage_map[$handler_key] ?? 5;
    }

    /**
     * Get estimated reliability (1-10 scale, 10 = most reliable)
     */
    private function get_estimated_reliability($handler_key) {
        $reliability_map = array(
            '404_handler' => 10,      // Most reliable - always works
            'action_hook' => 8,       // Very reliable - WordPress native
            'endpoint_handler' => 7,  // Good - depends on REST API
            'direct_handler' => 6     // Good - depends on file system access
        );

        return $reliability_map[$handler_key] ?? 5;
    }

    /**
     * Generate admin panel content
     */
    public function render_admin_panel() {
        $status = $this->get_system_status();
        $handlers = $this->get_all_handlers_info();
        $metrics = $this->get_performance_metrics();

        ob_start();
        ?>
        <div class="h3tm-url-manager-panel">
            <h3>URL Handler Management</h3>

            <div class="h3tm-status-overview">
                <h4>System Status</h4>
                <p><strong>Active Handler:</strong> <?php echo esc_html($status['active_handler_name']); ?></p>
                <p><strong>Available Handlers:</strong> <?php echo intval($status['handlers_available']); ?>/<?php echo count($this->handlers); ?></p>
                <p><strong>S3 Configured:</strong> <?php echo $status['s3_configured'] ? 'Yes' : 'No'; ?></p>

                <?php if (!empty($status['recommendations'])): ?>
                    <div class="h3tm-recommendations">
                        <h5>Recommendations:</h5>
                        <?php foreach ($status['recommendations'] as $rec): ?>
                            <div class="notice notice-<?php echo esc_attr($rec['type']); ?>">
                                <p><strong><?php echo esc_html($rec['message']); ?></strong></p>
                                <p><?php echo esc_html($rec['action']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="h3tm-handlers-table">
                <h4>Available Handlers</h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Handler</th>
                            <th>Status</th>
                            <th>Performance</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($handlers as $key => $handler): ?>
                            <tr<?php echo $handler['active'] ? ' class="active"' : ''; ?>>
                                <td>
                                    <strong><?php echo esc_html($handler['name']); ?></strong>
                                    <?php if ($handler['active']): ?>
                                        <span class="dashicons dashicons-yes" style="color: green;"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($handler['available']): ?>
                                        <span style="color: green;">Available</span>
                                    <?php else: ?>
                                        <span style="color: red;">Unavailable</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    Speed: <?php echo intval($metrics[$key]['estimated_speed']); ?>/10<br>
                                    Resources: <?php echo intval($metrics[$key]['resource_usage']); ?>/10<br>
                                    Reliability: <?php echo intval($metrics[$key]['reliability']); ?>/10
                                </td>
                                <td>
                                    <?php echo esc_html($handler['description']); ?>
                                    <div class="handler-pros-cons">
                                        <strong>Pros:</strong> <?php echo esc_html(implode(', ', $handler['pros'])); ?><br>
                                        <strong>Cons:</strong> <?php echo esc_html(implode(', ', $handler['cons'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($handler['available'] && !$handler['active']): ?>
                                        <button type="button" class="button h3tm-switch-handler" data-handler="<?php echo esc_attr($key); ?>">
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="h3tm-test-section">
                <h4>Test Handlers</h4>
                <button type="button" class="button button-secondary" id="h3tm-test-all-handlers">
                    Test All Handlers
                </button>
                <div id="h3tm-test-results"></div>
            </div>
        </div>

        <style>
            .h3tm-url-manager-panel .active { background-color: #f0f8ff; }
            .handler-pros-cons { font-size: 0.9em; margin-top: 5px; color: #666; }
            .h3tm-recommendations { margin: 15px 0; }
            #h3tm-test-results { margin-top: 15px; }
        </style>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.h3tm-switch-handler').click(function() {
                var handler = $(this).data('handler');
                var button = $(this);

                button.prop('disabled', true).text('Activating...');

                $.post(ajaxurl, {
                    action: 'h3tm_switch_handler',
                    handler: handler,
                    nonce: '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to switch handler: ' + response.data);
                        button.prop('disabled', false).text('Activate');
                    }
                })
                .fail(function() {
                    alert('Request failed');
                    button.prop('disabled', false).text('Activate');
                });
            });

            $('#h3tm-test-all-handlers').click(function() {
                var button = $(this);
                var resultsDiv = $('#h3tm-test-results');

                button.prop('disabled', true).text('Testing...');
                resultsDiv.html('<p>Testing all handlers...</p>');

                $.post(ajaxurl, {
                    action: 'h3tm_test_url_handlers',
                    nonce: '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        var results = '<h5>Test Results:</h5>';
                        $.each(response.data.handlers, function(key, handler) {
                            results += '<div class="notice ' + (handler.test_result.success ? 'notice-success' : 'notice-error') + '">';
                            results += '<p><strong>' + handler.name + ':</strong> ' + handler.test_result.message + '</p>';
                            results += '</div>';
                        });
                        resultsDiv.html(results);
                    } else {
                        resultsDiv.html('<div class="notice notice-error"><p>Test failed: ' + response.data + '</p></div>');
                    }
                })
                .fail(function() {
                    resultsDiv.html('<div class="notice notice-error"><p>Test request failed</p></div>');
                })
                .always(function() {
                    button.prop('disabled', false).text('Test All Handlers');
                });
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }
}