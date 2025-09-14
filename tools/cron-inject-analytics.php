<?php
/**
 * CRON-Ready Analytics Injection for H3Panos Tours
 * Automatically processes new tours and injects GA4 tracking
 * Designed to run on CRON schedule for Pantheon hosting
 */

// Configuration
$config = array(
    'h3panos_path' => ABSPATH . 'h3panos',
    'ga4_measurement_id' => 'G-08Q1M637NJ',
    'log_file' => ABSPATH . 'logs/h3tm-analytics-cron.log',
    'max_log_size' => 5 * 1024 * 1024, // 5MB
    'backup_enabled' => true,
    'dry_run' => false
);

// WordPress environment check
if (!defined('ABSPATH')) {
    // If running standalone, define WordPress paths
    $wp_root = dirname(dirname(dirname(__FILE__)));
    if (file_exists($wp_root . '/wp-config.php')) {
        require_once($wp_root . '/wp-config.php');
    } else {
        die("WordPress environment not found. Run from WordPress root or WP-CLI.\n");
    }
}

class H3TM_CRON_Analytics_Injector {
    
    private $config;
    private $log_handle;
    private $stats;
    
    public function __construct($config) {
        $this->config = $config;
        $this->stats = array(
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'new_tours' => 0
        );
        
        $this->init_logging();
    }
    
    /**
     * Initialize logging
     */
    private function init_logging() {
        // Create logs directory if it doesn't exist
        $log_dir = dirname($this->config['log_file']);
        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        // Rotate log if too large
        if (file_exists($this->config['log_file']) && 
            filesize($this->config['log_file']) > $this->config['max_log_size']) {
            rename($this->config['log_file'], $this->config['log_file'] . '.old');
        }
        
        $this->log_handle = fopen($this->config['log_file'], 'a');
        $this->log("=== H3TM Analytics CRON Started ===");
    }
    
    /**
     * Log message with timestamp
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_line = "[{$timestamp}] {$message}\n";
        
        if ($this->log_handle) {
            fwrite($this->log_handle, $log_line);
        }
        
        // Also echo if running from command line
        if (php_sapi_name() === 'cli') {
            echo $log_line;
        }
    }
    
    /**
     * Main execution function
     */
    public function run() {
        try {
            $this->log("Target directory: {$this->config['h3panos_path']}");
            $this->log("GA4 Tracking ID: {$this->config['ga4_measurement_id']}");
            
            if (!is_dir($this->config['h3panos_path'])) {
                throw new Exception("h3panos directory not found: {$this->config['h3panos_path']}");
            }
            
            $tour_dirs = glob($this->config['h3panos_path'] . '/*', GLOB_ONLYDIR);
            
            if (empty($tour_dirs)) {
                $this->log("No tour directories found");
                return $this->stats;
            }
            
            $this->log("Found " . count($tour_dirs) . " tour directories");
            
            foreach ($tour_dirs as $tour_dir) {
                $this->process_tour($tour_dir);
            }
            
            $this->log_summary();
            
        } catch (Exception $e) {
            $this->log("FATAL ERROR: " . $e->getMessage());
            $this->stats['errors']++;
        }
        
        $this->log("=== H3TM Analytics CRON Completed ===\n");
        
        if ($this->log_handle) {
            fclose($this->log_handle);
        }
        
        return $this->stats;
    }
    
    /**
     * Process individual tour directory
     */
    private function process_tour($tour_dir) {
        $tour_name = basename($tour_dir);
        $index_file = $tour_dir . '/index.htm';
        
        // Skip if no index.htm
        if (!file_exists($index_file)) {
            $this->log("SKIP: {$tour_name} - No index.htm found");
            $this->stats['skipped']++;
            return;
        }
        
        // Read current content
        $content = file_get_contents($index_file);
        if ($content === false) {
            $this->log("ERROR: {$tour_name} - Failed to read index.htm");
            $this->stats['errors']++;
            return;
        }
        
        // Skip if already has analytics
        if (strpos($content, $this->config['ga4_measurement_id']) !== false) {
            $this->log("SKIP: {$tour_name} - Already has analytics");
            $this->stats['skipped']++;
            return;
        }
        
        // This is a new tour - needs analytics injection
        $this->stats['new_tours']++;
        
        if ($this->config['dry_run']) {
            $this->log("DRY-RUN: {$tour_name} - Would inject analytics");
            $this->stats['processed']++;
            return;
        }
        
        try {
            // Create backup if enabled
            if ($this->config['backup_enabled']) {
                $backup_file = $index_file . '.backup-' . date('Y-m-d-H-i-s');
                if (!file_put_contents($backup_file, $content)) {
                    throw new Exception("Failed to create backup");
                }
            }
            
            // Inject analytics
            $modified_content = $this->inject_analytics($content, $tour_name);
            
            // Verify injection worked
            if ($modified_content === $content) {
                throw new Exception("Analytics injection failed - no </head> tag found");
            }
            
            // Write modified file
            if (!file_put_contents($index_file, $modified_content)) {
                throw new Exception("Failed to write modified file");
            }
            
            $this->log("SUCCESS: {$tour_name} - Analytics injected");
            $this->stats['processed']++;
            
            // Send notification for new tour (optional)
            $this->notify_new_tour($tour_name);
            
        } catch (Exception $e) {
            $this->log("ERROR: {$tour_name} - " . $e->getMessage());
            $this->stats['errors']++;
        }
    }
    
    /**
     * Inject GA4 analytics code
     */
    private function inject_analytics($content, $tour_name) {
        $ga4_code = '
<!-- Google Analytics 4 - Auto-injected by CRON -->
<script async src="https://www.googletagmanager.com/gtag/js?id=' . $this->config['ga4_measurement_id'] . '"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag(\'js\', new Date());
  
  // Configure GA4 with tour-specific page title
  gtag(\'config\', \'' . $this->config['ga4_measurement_id'] . '\', {
    page_title: \'' . esc_js($tour_name) . '\'
  });
  
  // Track tour view event
  gtag(\'event\', \'tour_view\', {
    \'tour_name\': \'' . esc_js($tour_name) . '\',
    \'event_category\': \'3dvista_tour\',
    \'event_label\': \'tour_started\',
    \'auto_injected\': true
  });
  
  console.log(\'GA4 Analytics auto-loaded for tour: ' . esc_js($tour_name) . '\');
</script>
<!-- End GA4 Analytics -->';
        
        return str_replace('</head>', $ga4_code . '</head>', $content);
    }
    
    /**
     * Send notification for new tour (can integrate with email, Slack, etc.)
     */
    private function notify_new_tour($tour_name) {
        // Optional: Send email notification
        // wp_mail('admin@example.com', 'New Tour Added', "Analytics injected for: {$tour_name}");
        
        // Optional: Add to WordPress admin notices
        if (function_exists('update_option')) {
            $notices = get_option('h3tm_new_tours', array());
            $notices[] = array(
                'tour_name' => $tour_name,
                'timestamp' => time(),
                'status' => 'analytics_injected'
            );
            update_option('h3tm_new_tours', $notices);
        }
    }
    
    /**
     * Log execution summary
     */
    private function log_summary() {
        $this->log("=== EXECUTION SUMMARY ===");
        $this->log("New tours found: {$this->stats['new_tours']}");
        $this->log("Successfully processed: {$this->stats['processed']}");
        $this->log("Skipped (already has analytics): {$this->stats['skipped']}");
        $this->log("Errors: {$this->stats['errors']}");
        
        if ($this->stats['new_tours'] > 0) {
            $this->log("🎉 {$this->stats['new_tours']} new tours now have GA4 tracking!");
        }
    }
}

// Helper function for JavaScript escaping
if (!function_exists('esc_js')) {
    function esc_js($text) {
        return addslashes($text);
    }
}

// Execute if running directly (CRON or command line)
if (php_sapi_name() === 'cli' || (isset($_GET['h3tm_cron']) && $_GET['h3tm_cron'] === 'analytics')) {
    $injector = new H3TM_CRON_Analytics_Injector($config);
    $results = $injector->run();
    
    // Return JSON for webhook monitoring
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode($results);
    }
}

return $config; // For WordPress integration
?>