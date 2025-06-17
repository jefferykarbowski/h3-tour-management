<?php
/**
 * H3 Tour Management - Convert HTML Tours to PHP
 * 
 * This script converts all existing HTML tour files to PHP files with analytics
 * 
 * Usage: 
 * 1. Run from command line: php convert-tours-to-php.php
 * 2. Or access via browser: http://yoursite.com/wp-content/plugins/h3-tour-management/tools/convert-tours-to-php.php
 */

// Load WordPress environment
$wp_load_path = dirname(__FILE__) . '/../../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: Cannot find wp-load.php. Please ensure this script is in the correct location.\n");
}
require_once($wp_load_path);

// Check if running from CLI or web
$is_cli = php_sapi_name() === 'cli';

// Security check for web access
if (!$is_cli && !current_user_can('manage_options')) {
    die("Error: You must be logged in as an administrator to run this script.\n");
}

// Output formatting
function output_message($message, $type = 'info') {
    global $is_cli;
    
    if ($is_cli) {
        $prefix = '';
        switch ($type) {
            case 'success':
                $prefix = "\033[32m✓\033[0m "; // Green checkmark
                break;
            case 'error':
                $prefix = "\033[31m✗\033[0m "; // Red X
                break;
            case 'warning':
                $prefix = "\033[33m!\033[0m "; // Yellow !
                break;
            case 'info':
                $prefix = "\033[34mℹ\033[0m "; // Blue i
                break;
        }
        echo $prefix . $message . "\n";
    } else {
        $color = '';
        switch ($type) {
            case 'success':
                $color = 'color: green;';
                break;
            case 'error':
                $color = 'color: red;';
                break;
            case 'warning':
                $color = 'color: orange;';
                break;
            case 'info':
                $color = 'color: blue;';
                break;
        }
        echo "<p style='$color'>" . esc_html($message) . "</p>";
    }
}

// HTML header for web access
if (!$is_cli) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>H3 Tour Management - Convert Tours to PHP</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                max-width: 800px;
                margin: 0 auto;
            }
            h1 {
                color: #333;
                border-bottom: 2px solid #c1272d;
                padding-bottom: 10px;
            }
            .stats {
                background: #f0f0f0;
                padding: 15px;
                border-radius: 3px;
                margin: 20px 0;
            }
            .button {
                background: #c1272d;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 3px;
                display: inline-block;
                margin-top: 20px;
            }
            .button:hover {
                background: #a01020;
            }
            pre {
                background: #f5f5f5;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 3px;
                overflow-x: auto;
            }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>H3 Tour Management - Convert Tours to PHP</h1>
    <?php
}

// Start conversion process
output_message("Starting tour conversion process...", "info");

// Initialize variables
$tour_dir = ABSPATH . H3TM_TOUR_DIR;
$tours_found = 0;
$tours_converted = 0;
$tours_already_php = 0;
$tours_failed = 0;
$conversion_log = array();

// Check if tour directory exists
if (!is_dir($tour_dir)) {
    output_message("Error: Tour directory not found at: $tour_dir", "error");
    exit;
}

// Get analytics settings
$ga_measurement_id = get_option('h3tm_ga_measurement_id', 'G-08Q1M637NJ');
$analytics_enabled = get_option('h3tm_analytics_enabled', '1');
$track_interactions = get_option('h3tm_track_interactions', '1');
$track_time_spent = get_option('h3tm_track_time_spent', '1');

output_message("Analytics Configuration:", "info");
output_message("  GA4 Measurement ID: $ga_measurement_id", "info");
output_message("  Analytics Enabled: " . ($analytics_enabled ? 'Yes' : 'No'), "info");
output_message("  Track Interactions: " . ($track_interactions ? 'Yes' : 'No'), "info");
output_message("  Track Time Spent: " . ($track_time_spent ? 'Yes' : 'No'), "info");
output_message("", "info");

// Load tour manager class
if (!class_exists('H3TM_Tour_Manager')) {
    require_once(dirname(__FILE__) . '/../includes/class-h3tm-tour-manager.php');
}

$tour_manager = new H3TM_Tour_Manager();

// Get all tours
$tours = $tour_manager->get_all_tours();
$tours_found = count($tours);

output_message("Found $tours_found tours in $tour_dir", "info");
output_message("", "info");

// Process each tour
foreach ($tours as $tour) {
    $tour_path = $tour_dir . '/' . $tour;
    $php_file = $tour_path . '/index.php';
    $html_file = $tour_path . '/index.html';
    $htm_file = $tour_path . '/index.htm';
    
    output_message("Processing: $tour", "info");
    
    // Check if already has PHP file
    if (file_exists($php_file)) {
        output_message("  → Already has index.php", "warning");
        
        // Ask if should update analytics code
        if (!$is_cli || (isset($argv[1]) && $argv[1] === '--update-existing')) {
            output_message("  → Updating analytics code...", "info");
            
            if ($tour_manager->update_tour_analytics($tour)) {
                output_message("  → Analytics code updated successfully", "success");
                $tours_already_php++;
                $conversion_log[] = array(
                    'tour' => $tour,
                    'action' => 'updated',
                    'status' => 'success'
                );
            } else {
                output_message("  → Failed to update analytics code", "error");
                $tours_failed++;
                $conversion_log[] = array(
                    'tour' => $tour,
                    'action' => 'update_failed',
                    'status' => 'error'
                );
            }
        } else {
            $tours_already_php++;
            $conversion_log[] = array(
                'tour' => $tour,
                'action' => 'skipped',
                'status' => 'info',
                'reason' => 'Already has PHP file'
            );
        }
        continue;
    }
    
    // Check for HTML files
    if (!file_exists($html_file) && !file_exists($htm_file)) {
        output_message("  → No index.html or index.htm found", "error");
        $tours_failed++;
        $conversion_log[] = array(
            'tour' => $tour,
            'action' => 'failed',
            'status' => 'error',
            'reason' => 'No index file found'
        );
        continue;
    }
    
    // Backup original file
    $source_file = file_exists($html_file) ? $html_file : $htm_file;
    $backup_file = $source_file . '.backup';
    
    if (!file_exists($backup_file)) {
        if (copy($source_file, $backup_file)) {
            output_message("  → Created backup: " . basename($backup_file), "info");
        } else {
            output_message("  → Warning: Could not create backup", "warning");
        }
    }
    
    // Convert to PHP
    output_message("  → Converting to PHP...", "info");
    
    // Use the tour manager's create_php_index method
    $reflection = new ReflectionClass($tour_manager);
    $method = $reflection->getMethod('create_php_index');
    $method->setAccessible(true);
    
    if ($method->invoke($tour_manager, $tour_path, $tour)) {
        output_message("  → Successfully converted to PHP", "success");
        $tours_converted++;
        $conversion_log[] = array(
            'tour' => $tour,
            'action' => 'converted',
            'status' => 'success'
        );
    } else {
        output_message("  → Failed to convert to PHP", "error");
        $tours_failed++;
        $conversion_log[] = array(
            'tour' => $tour,
            'action' => 'failed',
            'status' => 'error',
            'reason' => 'Conversion failed'
        );
    }
    
    output_message("", "info");
}

// Summary
output_message("Conversion Complete!", "success");
output_message("", "info");

$summary = "Summary:\n";
$summary .= "  Total tours found: $tours_found\n";
$summary .= "  Tours converted: $tours_converted\n";
$summary .= "  Tours already PHP: $tours_already_php\n";
$summary .= "  Tours failed: $tours_failed";

if (!$is_cli) {
    echo "<div class='stats'><pre>$summary</pre></div>";
} else {
    echo "\n" . $summary . "\n";
}

// Save conversion log
$log_file = dirname(__FILE__) . '/conversion-log-' . date('Y-m-d-His') . '.json';
file_put_contents($log_file, json_encode(array(
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => array(
        'total' => $tours_found,
        'converted' => $tours_converted,
        'already_php' => $tours_already_php,
        'failed' => $tours_failed
    ),
    'details' => $conversion_log
), JSON_PRETTY_PRINT));

output_message("", "info");
output_message("Conversion log saved to: " . basename($log_file), "info");

// Additional instructions
if (!$is_cli) {
    ?>
    <h2>Next Steps</h2>
    <ol>
        <li>Review the conversion log to ensure all tours were processed correctly</li>
        <li>Test a few tours to verify they're working properly</li>
        <li>The original HTML files have been backed up as .backup files</li>
        <li>To update analytics settings, go to: <a href="<?php echo admin_url('admin.php?page=h3tm-analytics-settings'); ?>">Analytics Settings</a></li>
    </ol>
    
    <a href="<?php echo admin_url('admin.php?page=h3-tour-management'); ?>" class="button">Return to Tour Management</a>
    
    </div>
    </body>
    </html>
    <?php
} else {
    echo "\nTo update existing PHP files with new analytics code, run:\n";
    echo "php " . basename(__FILE__) . " --update-existing\n";
}
?>