<?php
/**
 * Tour management functionality
 */
class H3TM_Tour_Manager {
    
    private $tour_dir;
    
    public function __construct() {
        $this->tour_dir = ABSPATH . H3TM_TOUR_DIR;
        
        // Create tour directory if it doesn't exist
        if (!file_exists($this->tour_dir)) {
            wp_mkdir_p($this->tour_dir);
        }
    }
    
    /**
     * Get all tours
     */
    public function get_all_tours() {
        $tours = array();
        
        if (!is_dir($this->tour_dir)) {
            return $tours;
        }
        
        $dir = new DirectoryIterator($this->tour_dir);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                $tours[] = $fileinfo->getFilename();
            }
        }
        
        sort($tours);
        return $tours;
    }
    
    /**
     * Upload a new tour
     */
    public function upload_tour($tour_name, $file, $is_pre_uploaded = false) {
        $result = array('success' => false, 'message' => '');
        
        // Sanitize tour name
        $tour_name = sanitize_file_name($tour_name);
        $tour_path = $this->tour_dir . '/' . $tour_name;
        
        // Check if tour already exists
        if (file_exists($tour_path)) {
            $result['message'] = __('A tour with this name already exists.', 'h3-tour-management');
            return $result;
        }
        
        // Check file type
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'zip') {
            $result['message'] = __('Please upload a ZIP file.', 'h3-tour-management');
            return $result;
        }
        
        // Create tour directory
        if (!wp_mkdir_p($tour_path)) {
            $result['message'] = __('Failed to create tour directory.', 'h3-tour-management');
            return $result;
        }
        
        // Handle file movement
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/h3-tours/' . basename($file['name']);
        
        if ($is_pre_uploaded) {
            // File is already uploaded via chunks, just rename it
            if (!rename($file['tmp_name'], $temp_file)) {
                rmdir($tour_path);
                $result['message'] = __('Failed to move uploaded file.', 'h3-tour-management');
                return $result;
            }
        } else {
            // Traditional upload
            if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
                rmdir($tour_path);
                $result['message'] = __('Failed to move uploaded file.', 'h3-tour-management');
                return $result;
            }
        }
        
        // Extract ZIP file
        $zip = new ZipArchive();
        if ($zip->open($temp_file) === TRUE) {
            $zip->extractTo($tour_path);
            $zip->close();
            unlink($temp_file);
            
            // Skip PHP index file creation - just use original index.html
            // if ($this->create_php_index($tour_path, $tour_name)) {
            //     $result['success'] = true;
            //     $result['message'] = __('Tour uploaded successfully.', 'h3-tour-management');
            // } else {
            //     $this->delete_directory($tour_path);
            //     $result['message'] = __('Failed to create PHP index file.', 'h3-tour-management');
            // }
            
            // Success without PHP index creation
            $result['success'] = true;
            $result['message'] = __('Tour uploaded successfully.', 'h3-tour-management');
        } else {
            unlink($temp_file);
            $this->delete_directory($tour_path);
            $result['message'] = __('Failed to extract ZIP file.', 'h3-tour-management');
        }
        
        return $result;
    }
    
    /**
     * Delete a tour
     */
    public function delete_tour($tour_name) {
        $result = array('success' => false, 'message' => '');
        
        $tour_path = $this->tour_dir . '/' . $tour_name;
        
        if (!file_exists($tour_path)) {
            $result['message'] = __('Tour not found.', 'h3-tour-management');
            return $result;
        }
        
        if ($this->delete_directory($tour_path)) {
            // Remove tour from all users
            $this->remove_tour_from_users($tour_name);
            
            $result['success'] = true;
            $result['message'] = __('Tour deleted successfully.', 'h3-tour-management');
        } else {
            $result['message'] = __('Failed to delete tour.', 'h3-tour-management');
        }
        
        return $result;
    }
    
    /**
     * Rename a tour
     */
    public function rename_tour($old_name, $new_name) {
        $result = array('success' => false, 'message' => '');
        
        $new_name = sanitize_file_name($new_name);
        $old_path = $this->tour_dir . '/' . $old_name;
        $new_path = $this->tour_dir . '/' . $new_name;
        
        if (!file_exists($old_path)) {
            $result['message'] = __('Tour not found.', 'h3-tour-management');
            return $result;
        }
        
        if (file_exists($new_path)) {
            $result['message'] = __('A tour with the new name already exists.', 'h3-tour-management');
            return $result;
        }
        
        if (rename($old_path, $new_path)) {
            // Update tour name for all users
            $this->update_tour_name_for_users($old_name, $new_name);
            
            $result['success'] = true;
            $result['message'] = __('Tour renamed successfully.', 'h3-tour-management');
        } else {
            $result['message'] = __('Failed to rename tour.', 'h3-tour-management');
        }
        
        return $result;
    }
    
    /**
     * Create PHP index file
     */
    private function create_php_index($tour_path, $tour_name) {
        $html_file = $tour_path . '/index.html';
        $htm_file = $tour_path . '/index.htm';
        $php_file = $tour_path . '/index.php';
        
        // Determine which HTML file exists
        $source_file = '';
        if (file_exists($html_file)) {
            $source_file = $html_file;
        } elseif (file_exists($htm_file)) {
            $source_file = $htm_file;
        } else {
            return false;
        }
        
        // Read the original HTML content
        $html_content = file_get_contents($source_file);
        if ($html_content === false) {
            return false;
        }
        
        // Extract the title
        $title = $tour_name;
        if (preg_match('/<title>(.*?)<\/title>/i', $html_content, $matches)) {
            $title = trim($matches[1]);
        }
        
        // Create PHP index file with analytics code
        $php_content = '<?php
/**
 * H3 Tour Index File
 * Tour: ' . esc_html($tour_name) . '
 * Generated by H3 Tour Management Plugin
 */

// WordPress environment (optional - remove if not needed)
// require_once($_SERVER["DOCUMENT_ROOT"] . "/wp-load.php");

// Get tour name from directory
$tour_name = basename(__DIR__);

// Analytics configuration from plugin settings
$ga_measurement_id = "' . get_option('h3tm_ga_measurement_id', 'G-08Q1M637NJ') . '";
$analytics_enabled = ' . (get_option('h3tm_analytics_enabled', '1') ? 'true' : 'false') . ';
$track_interactions = ' . (get_option('h3tm_track_interactions', '1') ? 'true' : 'false') . ';
$track_time_spent = ' . (get_option('h3tm_track_time_spent', '1') ? 'true' : 'false') . ';

// Custom analytics parameters (modify as needed)
$custom_dimensions = array();
$custom_events = array();

// Start output
?>
<!DOCTYPE html>
' . substr($html_content, strpos($html_content, '<html'));
        
        // Insert Google Analytics code before </head>
        $analytics_code = '
<!-- Google Analytics -->
<?php if ($analytics_enabled && !empty($ga_measurement_id)) : ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $ga_measurement_id; ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag(\'js\', new Date());
  
  gtag(\'config\', \'<?php echo $ga_measurement_id; ?>\', {
    \'page_title\': \'' . addslashes($title) . '\',
    \'page_path\': \'/\' + \'<?php echo H3TM_TOUR_DIR; ?>\' + \'/\' + \'<?php echo $tour_name; ?>\' + \'/\'
  });
  
  // Track tour view event
  gtag(\'event\', \'tour_view\', {
    \'tour_name\': \'<?php echo $tour_name; ?>\',
    \'tour_title\': \'' . addslashes($title) . '\'
  });
</script>

<!-- Custom Analytics Tracking -->
<script>
  // Track tour engagement
  document.addEventListener(\'DOMContentLoaded\', function() {
    <?php if ($track_interactions) : ?>
    // Track panorama interactions
    if (typeof tour !== \'undefined\' && tour.on) {
      tour.on(\'view-change\', function(e) {
        if (typeof gtag !== \'undefined\') {
          gtag(\'event\', \'panorama_interaction\', {
            \'event_category\': \'Tour Engagement\',
            \'event_label\': e.view || \'unknown\',
            \'tour_name\': \'<?php echo $tour_name; ?>\'
          });
        }
      });
    }
    <?php endif; ?>
    
    <?php if ($track_time_spent) : ?>
    // Track time on page
    let startTime = Date.now();
    window.addEventListener(\'beforeunload\', function() {
      let timeSpent = Math.round((Date.now() - startTime) / 1000);
      if (typeof gtag !== \'undefined\') {
        gtag(\'event\', \'timing_complete\', {
          \'name\': \'tour_session\',
          \'value\': timeSpent,
          \'event_category\': \'Tour Engagement\',
          \'event_label\': \'<?php echo $tour_name; ?>\'
        });
      }
    });
    <?php endif; ?>
  });
</script>

<?php 
// Insert custom analytics code if provided
$custom_code = get_option(\'h3tm_custom_analytics_code\', \'\');
if (!empty($custom_code)) {
    echo "<!-- Custom Analytics Code -->\n";
    echo "<script>\n" . $custom_code . "\n</script>\n";
    echo "<!-- End Custom Analytics Code -->\n";
}
?>

<?php endif; ?>
<!-- End Google Analytics -->

</head>';
        
        // Replace </head> with analytics code + </head>
        $php_content = str_replace('</head>', $analytics_code, $php_content);
        
        // Add custom PHP tracking at the end of body
        $tracking_code = '
<!-- Additional PHP Tracking -->
<?php
// You can add custom PHP tracking code here
// For example, log visits to a database, send notifications, etc.

// Example: Custom event tracking
if ($analytics_enabled) {
    // Add any server-side tracking logic here
}
?>
</body>';
        
        // Replace </body> with tracking code
        $php_content = str_replace('</body>', $tracking_code, $php_content);
        
        // Write the PHP file
        if (file_put_contents($php_file, $php_content) === false) {
            return false;
        }
        
        // Delete the original HTML file
        unlink($source_file);
        
        // Create .htaccess to ensure index.php is used
        $htaccess_content = '# H3 Tour Management - Use PHP index
DirectoryIndex index.php index.html index.htm

# Optional: Prevent direct access to media files from outside
# <FilesMatch "\.(jpg|jpeg|png|gif|mp4|webm)$">
#     Order Allow,Deny
#     Allow from all
# </FilesMatch>';
        
        file_put_contents($tour_path . '/.htaccess', $htaccess_content);
        
        return true;
    }
    
    /**
     * Get tour title
     */
    public function get_tour_title($tour_name) {
        // Try PHP file first, then HTML files
        $files = array(
            '/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour_name) . '/index.php',
            '/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour_name) . '/index.html',
            '/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour_name) . '/index.htm'
        );
        
        foreach ($files as $file) {
            $url = site_url($file);
            $response = wp_remote_get($url);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                if (preg_match('/<title>(.*?)<\/title>/i', $body, $matches)) {
                    return trim($matches[1]);
                }
            }
        }
        
        return $tour_name;
    }
    
    /**
     * Get tour media
     */
    public function get_tour_media($tour_name) {
        $url = site_url('/' . H3TM_TOUR_DIR . '/' . rawurlencode($tour_name) . '/locale/en.txt');
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $media = array();
        $lines = explode("\n", $body);
        
        foreach ($lines as $i => $line) {
            $parts = explode(' = ', $line);
            if (count($parts) == 2) {
                $media[$i] = $parts;
            }
        }
        
        return $media;
    }
    
    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            if (!$this->delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Remove tour from all users
     */
    private function remove_tour_from_users($tour_name) {
        $users = get_users();
        
        foreach ($users as $user) {
            $user_tours = get_user_meta($user->ID, 'h3tm_tours', true);
            
            if (is_array($user_tours) && in_array($tour_name, $user_tours)) {
                $user_tours = array_diff($user_tours, array($tour_name));
                
                if (empty($user_tours)) {
                    delete_user_meta($user->ID, 'h3tm_tours');
                } else {
                    update_user_meta($user->ID, 'h3tm_tours', array_values($user_tours));
                }
            }
        }
    }
    
    /**
     * Update tour name for all users
     */
    private function update_tour_name_for_users($old_name, $new_name) {
        $users = get_users();
        
        foreach ($users as $user) {
            $user_tours = get_user_meta($user->ID, 'h3tm_tours', true);
            
            if (is_array($user_tours) && in_array($old_name, $user_tours)) {
                $key = array_search($old_name, $user_tours);
                $user_tours[$key] = $new_name;
                update_user_meta($user->ID, 'h3tm_tours', array_values($user_tours));
            }
        }
    }
    
    /**
     * Update analytics code in existing tour
     */
    public function update_tour_analytics($tour_name) {
        // Skip analytics update since we're not using PHP index files
        return true;
        
        /* Disabled PHP index creation
        $tour_path = $this->tour_dir . '/' . $tour_name;
        $php_file = $tour_path . '/index.php';
        
        // Check if PHP file exists
        if (!file_exists($php_file)) {
            // If no PHP file, check for HTML file and convert it
            $html_file = $tour_path . '/index.html';
            $htm_file = $tour_path . '/index.htm';
            
            if (file_exists($html_file) || file_exists($htm_file)) {
                return $this->create_php_index($tour_path, $tour_name);
            }
            return false;
        }
        */
        
        // Read existing PHP file
        $content = file_get_contents($php_file);
        if ($content === false) {
            return false;
        }
        
        // Update GA measurement ID
        $ga_measurement_id = get_option('h3tm_ga_measurement_id', 'G-08Q1M637NJ');
        $content = preg_replace(
            '/\$ga_measurement_id = "[^"]*";/',
            '$ga_measurement_id = "' . $ga_measurement_id . '";',
            $content
        );
        
        // Update analytics settings
        $analytics_enabled = get_option('h3tm_analytics_enabled', '1') ? 'true' : 'false';
        $content = preg_replace(
            '/\$analytics_enabled = (true|false);/',
            '$analytics_enabled = ' . $analytics_enabled . ';',
            $content
        );
        
        // Update tracking options
        $track_interactions = get_option('h3tm_track_interactions', '1') ? 'true' : 'false';
        $track_time_spent = get_option('h3tm_track_time_spent', '1') ? 'true' : 'false';
        
        // Add tracking variables if they don't exist
        if (strpos($content, '$track_interactions') === false) {
            $content = str_replace(
                '$analytics_enabled = ' . $analytics_enabled . ';',
                '$analytics_enabled = ' . $analytics_enabled . ';' . "\n" .
                '$track_interactions = ' . $track_interactions . ';' . "\n" .
                '$track_time_spent = ' . $track_time_spent . ';',
                $content
            );
        } else {
            $content = preg_replace(
                '/\$track_interactions = (true|false);/',
                '$track_interactions = ' . $track_interactions . ';',
                $content
            );
            $content = preg_replace(
                '/\$track_time_spent = (true|false);/',
                '$track_time_spent = ' . $track_time_spent . ';',
                $content
            );
        }
        
        // Write updated content back to file
        return file_put_contents($php_file, $content) !== false;
    }
}