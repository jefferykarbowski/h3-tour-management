<?php
/**
 * H3TM CRON Analytics Integration
 * WordPress integration for automated analytics injection
 */

class H3TM_CRON_Analytics {
    
    private $cron_hook = 'h3tm_inject_analytics';
    
    public function __construct() {
        // WordPress CRON integration
        add_action('init', array($this, 'schedule_cron'));
        add_action($this->cron_hook, array($this, 'run_analytics_injection'));
        
        // Admin integration
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_notices', array($this, 'show_new_tour_notices'));
        add_action('wp_ajax_h3tm_manual_inject', array($this, 'manual_injection_ajax'));
        
        // Webhook endpoint for external monitoring
        add_action('wp_ajax_nopriv_h3tm_cron_status', array($this, 'cron_status_endpoint'));
        add_action('wp_ajax_h3tm_cron_status', array($this, 'cron_status_endpoint'));
    }
    
    /**
     * Schedule CRON job if not already scheduled
     */
    public function schedule_cron() {
        if (!wp_next_scheduled($this->cron_hook)) {
            // Run every hour
            wp_schedule_event(time(), 'hourly', $this->cron_hook);
        }
    }
    
    /**
     * Execute analytics injection via CRON
     */
    public function run_analytics_injection() {
        $script_path = H3TM_PLUGIN_DIR . 'tools/cron-inject-analytics.php';
        
        if (!file_exists($script_path)) {
            error_log('H3TM: Analytics injection script not found');
            return;
        }
        
        // Include and execute
        $config = include($script_path);
        
        if (class_exists('H3TM_CRON_Analytics_Injector')) {
            $injector = new H3TM_CRON_Analytics_Injector($config);
            $results = $injector->run();
            
            // Store results for admin display
            update_option('h3tm_last_cron_results', array(
                'timestamp' => time(),
                'results' => $results
            ));
            
            // Log if there were new tours
            if ($results['new_tours'] > 0) {
                error_log("H3TM: Processed {$results['new_tours']} new tours with analytics injection");
            }
        }
    }
    
    /**
     * Add admin menu for CRON management
     */
    public function add_admin_menu() {
        add_submenu_page(
            'h3-tour-management',
            'Analytics CRON',
            'Analytics CRON',
            'manage_options',
            'h3tm-analytics-cron',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page for CRON management
     */
    public function admin_page() {
        $last_run = get_option('h3tm_last_cron_results');
        $next_scheduled = wp_next_scheduled($this->cron_hook);
        
        ?>
        <div class="wrap">
            <h1>H3TM Analytics CRON</h1>
            
            <div class="card">
                <h2>CRON Status</h2>
                <table class="form-table">
                    <tr>
                        <th>Next Scheduled Run:</th>
                        <td>
                            <?php 
                            if ($next_scheduled) {
                                echo date('Y-m-d H:i:s', $next_scheduled) . ' (' . human_time_diff($next_scheduled) . ')';
                            } else {
                                echo '<span style="color: red;">Not scheduled</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Last Run:</th>
                        <td>
                            <?php 
                            if ($last_run && isset($last_run['timestamp'])) {
                                echo date('Y-m-d H:i:s', $last_run['timestamp']) . ' (' . human_time_diff($last_run['timestamp']) . ' ago)';
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php if ($last_run && isset($last_run['results'])): ?>
            <div class="card">
                <h2>Last Execution Results</h2>
                <table class="form-table">
                    <tr>
                        <th>New Tours Found:</th>
                        <td><?php echo $last_run['results']['new_tours']; ?></td>
                    </tr>
                    <tr>
                        <th>Successfully Processed:</th>
                        <td><?php echo $last_run['results']['processed']; ?></td>
                    </tr>
                    <tr>
                        <th>Skipped (Already Has Analytics):</th>
                        <td><?php echo $last_run['results']['skipped']; ?></td>
                    </tr>
                    <tr>
                        <th>Errors:</th>
                        <td><?php echo $last_run['results']['errors']; ?></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Manual Controls</h2>
                <p>
                    <button type="button" class="button button-primary" id="run-manual-injection">
                        Run Analytics Injection Now
                    </button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </p>
                <div id="manual-injection-results" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#run-manual-injection').click(function() {
                var button = $(this);
                var spinner = $('.spinner');
                var results = $('#manual-injection-results');
                
                button.prop('disabled', true);
                spinner.addClass('is-active');
                results.html('');
                
                $.post(ajaxurl, {
                    action: 'h3tm_manual_inject',
                    nonce: '<?php echo wp_create_nonce('h3tm_manual_inject'); ?>'
                }, function(response) {
                    if (response.success) {
                        results.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        results.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                    }
                }).always(function() {
                    button.prop('disabled', false);
                    spinner.removeClass('is-active');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle manual injection AJAX
     */
    public function manual_injection_ajax() {
        check_ajax_referer('h3tm_manual_inject', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Run the injection
        $this->run_analytics_injection();
        
        $last_run = get_option('h3tm_last_cron_results');
        if ($last_run && isset($last_run['results'])) {
            $results = $last_run['results'];
            $message = sprintf(
                'Analytics injection completed! New tours: %d, Processed: %d, Errors: %d',
                $results['new_tours'],
                $results['processed'],
                $results['errors']
            );
            wp_send_json_success($message);
        } else {
            wp_send_json_error('Failed to get execution results');
        }
    }
    
    /**
     * Show admin notices for new tours
     */
    public function show_new_tour_notices() {
        $notices = get_option('h3tm_new_tours', array());
        $recent_notices = array_filter($notices, function($notice) {
            return (time() - $notice['timestamp']) < (24 * 60 * 60); // Last 24 hours
        });
        
        if (!empty($recent_notices)) {
            $count = count($recent_notices);
            $tour_names = array_column($recent_notices, 'tour_name');
            
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>H3TM Analytics:</strong> ';
            echo sprintf('Analytics injected for %d new tour%s: %s', 
                $count, 
                $count > 1 ? 's' : '',
                implode(', ', array_slice($tour_names, 0, 5))
            );
            if (count($tour_names) > 5) {
                echo ' and ' . (count($tour_names) - 5) . ' more';
            }
            echo '</p></div>';
        }
    }
    
    /**
     * CRON status endpoint for monitoring
     */
    public function cron_status_endpoint() {
        $last_run = get_option('h3tm_last_cron_results');
        $next_scheduled = wp_next_scheduled($this->cron_hook);
        
        $status = array(
            'status' => 'running',
            'next_scheduled' => $next_scheduled,
            'last_run' => $last_run,
            'cron_active' => (bool) $next_scheduled
        );
        
        wp_send_json($status);
    }
}

// Initialize if WordPress is loaded
if (defined('ABSPATH')) {
    new H3TM_CRON_Analytics();
}
?>