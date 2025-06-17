<?php
/**
 * Enhanced Tour Manager for H3 Tour Management
 * 
 * Uses WordPress Filesystem API and improved security
 * 
 * @package H3_Tour_Management
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class H3TM_Tour_Manager_V2 {
    
    /**
     * WordPress Filesystem instance
     * 
     * @var WP_Filesystem_Base
     */
    private $filesystem;
    
    /**
     * Tour directory path
     * 
     * @var string
     */
    private $tour_dir;
    
    /**
     * Tour directory URL
     * 
     * @var string
     */
    private $tour_url;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->tour_dir = ABSPATH . H3TM_TOUR_DIR;
        $this->tour_url = site_url('/' . H3TM_TOUR_DIR);
        
        // Initialize filesystem
        $this->init_filesystem();
        
        // Ensure tour directory exists
        $this->ensure_tour_directory();
    }
    
    /**
     * Initialize WordPress Filesystem
     * 
     * @return bool Success status
     */
    private function init_filesystem() {
        global $wp_filesystem;
        
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        // Initialize the WP filesystem
        if (!WP_Filesystem()) {
            H3TM_Logger::error('tour', 'Failed to initialize WordPress Filesystem');
            return false;
        }
        
        $this->filesystem = $wp_filesystem;
        return true;
    }
    
    /**
     * Ensure tour directory exists with proper permissions
     */
    private function ensure_tour_directory() {
        if (!$this->filesystem->exists($this->tour_dir)) {
            $this->filesystem->mkdir($this->tour_dir, 0755);
            
            // Add .htaccess for security
            $htaccess_content = "# H3 Tour Management Directory Protection\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</Files>\n";
            
            $this->filesystem->put_contents(
                $this->tour_dir . '/.htaccess',
                $htaccess_content,
                0644
            );
        }
    }
    
    /**
     * Get all tours with metadata
     * 
     * @param array $args Optional arguments for filtering
     * @return array Tours with metadata
     */
    public function get_all_tours($args = array()) {
        $defaults = array(
            'orderby' => 'name',
            'order' => 'ASC',
            'include_meta' => true,
            'page' => 1,
            'per_page' => -1
        );
        
        $args = wp_parse_args($args, $defaults);
        $tours = array();
        
        if (!$this->filesystem->is_dir($this->tour_dir)) {
            return $tours;
        }
        
        $files = $this->filesystem->dirlist($this->tour_dir);
        
        if (!$files) {
            return $tours;
        }
        
        foreach ($files as $file) {
            if ($file['type'] === 'd' && $file['name'] !== '.' && $file['name'] !== '..') {
                $tour_data = array(
                    'name' => $file['name'],
                    'path' => $this->tour_dir . '/' . $file['name'],
                    'url' => $this->tour_url . '/' . rawurlencode($file['name']),
                    'created' => date('Y-m-d H:i:s', $file['time']),
                    'size' => $this->get_directory_size($this->tour_dir . '/' . $file['name'])
                );
                
                // Include metadata if requested
                if ($args['include_meta']) {
                    $tour_data['title'] = $this->get_tour_title($file['name']);
                    $tour_data['meta'] = H3TM_Database::get_tour_meta($file['name']);
                    $tour_data['thumbnail'] = $this->get_tour_thumbnail($file['name']);
                }
                
                $tours[] = $tour_data;
            }
        }
        
        // Sort tours
        if ($args['orderby'] === 'name') {
            usort($tours, function($a, $b) use ($args) {
                $result = strcasecmp($a['name'], $b['name']);
                return ($args['order'] === 'DESC') ? -$result : $result;
            });
        } elseif ($args['orderby'] === 'created') {
            usort($tours, function($a, $b) use ($args) {
                $result = strtotime($a['created']) - strtotime($b['created']);
                return ($args['order'] === 'DESC') ? -$result : $result;
            });
        }
        
        // Handle pagination
        if ($args['per_page'] > 0) {
            $offset = ($args['page'] - 1) * $args['per_page'];
            $tours = array_slice($tours, $offset, $args['per_page']);
        }
        
        return $tours;
    }
    
    /**
     * Upload a new tour with enhanced security
     * 
     * @param string $tour_name Tour name
     * @param array $file File data
     * @param bool $is_chunked Whether this is a chunked upload
     * @return array Result with success status and message
     */
    public function upload_tour($tour_name, $file, $is_chunked = false) {
        $result = array('success' => false, 'message' => '');
        
        try {
            // Validate upload
            $validation = H3TM_Security::validate_upload($file);
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }
            
            // Check rate limiting
            if (!H3TM_Security::check_rate_limit('upload', get_current_user_id())) {
                throw new Exception(__('Upload rate limit exceeded. Please try again later.', 'h3-tour-management'));
            }
            
            // Sanitize tour name
            $tour_name = sanitize_file_name($tour_name);
            $tour_path = $this->tour_dir . '/' . $tour_name;
            
            // Check if tour already exists
            if ($this->filesystem->exists($tour_path)) {
                throw new Exception(__('A tour with this name already exists.', 'h3-tour-management'));
            }
            
            // Create tour directory
            if (!$this->filesystem->mkdir($tour_path, 0755)) {
                throw new Exception(__('Failed to create tour directory.', 'h3-tour-management'));
            }
            
            // Extract ZIP file
            $extracted = $this->extract_tour_zip($file['tmp_name'], $tour_path);
            if (!$extracted) {
                $this->filesystem->rmdir($tour_path, true);
                throw new Exception(__('Failed to extract tour files.', 'h3-tour-management'));
            }
            
            // Create PHP index file
            if (!$this->create_php_index($tour_path, $tour_name)) {
                $this->filesystem->rmdir($tour_path, true);
                throw new Exception(__('Failed to create tour index file.', 'h3-tour-management'));
            }
            
            // Store tour metadata
            H3TM_Database::update_tour_meta($tour_name, 'upload_date', current_time('mysql'));
            H3TM_Database::update_tour_meta($tour_name, 'uploaded_by', get_current_user_id());
            H3TM_Database::update_tour_meta($tour_name, 'file_size', $file['size']);
            
            // Log activity
            H3TM_Database::log_activity('tour_uploaded', 'tour', $tour_name, array(
                'file_size' => $file['size'],
                'user_id' => get_current_user_id()
            ));
            
            // Clean up temp file if needed
            if ($is_chunked && file_exists($file['tmp_name'])) {
                unlink($file['tmp_name']);
            }
            
            $result['success'] = true;
            $result['message'] = __('Tour uploaded successfully.', 'h3-tour-management');
            $result['tour_url'] = $this->tour_url . '/' . rawurlencode($tour_name);
            
            H3TM_Logger::info('tour', 'Tour uploaded successfully', array(
                'tour_name' => $tour_name,
                'size' => $file['size']
            ));
            
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            
            H3TM_Logger::error('tour', 'Tour upload failed', array(
                'tour_name' => $tour_name,
                'error' => $e->getMessage()
            ));
        }
        
        return $result;
    }
    
    /**
     * Extract tour ZIP file
     * 
     * @param string $zip_path Path to ZIP file
     * @param string $extract_to Extraction destination
     * @return bool Success status
     */
    private function extract_tour_zip($zip_path, $extract_to) {
        $zip = new ZipArchive();
        
        if ($zip->open($zip_path) !== true) {
            return false;
        }
        
        // Extract files one by one for better control
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $file_info = pathinfo($filename);
            
            // Skip unsafe files
            if ($this->is_unsafe_file($filename)) {
                continue;
            }
            
            // Extract file
            $zip->extractTo($extract_to, $filename);
        }
        
        $zip->close();
        return true;
    }
    
    /**
     * Check if a file is unsafe
     * 
     * @param string $filename Filename to check
     * @return bool True if unsafe
     */
    private function is_unsafe_file($filename) {
        $unsafe_extensions = array('php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'exe', 'sh', 'bat');
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        return in_array($ext, $unsafe_extensions, true);
    }
    
    /**
     * Create PHP index file with analytics
     * 
     * @param string $tour_path Tour directory path
     * @param string $tour_name Tour name
     * @return bool Success status
     */
    private function create_php_index($tour_path, $tour_name) {
        // Find existing index file
        $index_files = array('index.html', 'index.htm');
        $source_file = null;
        
        foreach ($index_files as $index) {
            if ($this->filesystem->exists($tour_path . '/' . $index)) {
                $source_file = $tour_path . '/' . $index;
                break;
            }
        }
        
        if (!$source_file) {
            return false;
        }
        
        // Read original HTML
        $html_content = $this->filesystem->get_contents($source_file);
        if (!$html_content) {
            return false;
        }
        
        // Extract title
        $title = $tour_name;
        if (preg_match('/<title>(.*?)<\/title>/i', $html_content, $matches)) {
            $title = trim($matches[1]);
        }
        
        // Store title as metadata
        H3TM_Database::update_tour_meta($tour_name, 'title', $title);
        
        // Generate PHP content
        $php_content = $this->generate_php_index_content($html_content, $tour_name, $title);
        
        // Write PHP file
        $php_file = $tour_path . '/index.php';
        if (!$this->filesystem->put_contents($php_file, $php_content, 0644)) {
            return false;
        }
        
        // Delete original HTML file
        $this->filesystem->delete($source_file);
        
        // Create security .htaccess
        $htaccess_content = $this->generate_tour_htaccess();
        $this->filesystem->put_contents($tour_path . '/.htaccess', $htaccess_content, 0644);
        
        return true;
    }
    
    /**
     * Generate PHP index content
     * 
     * @param string $html_content Original HTML content
     * @param string $tour_name Tour name
     * @param string $title Tour title
     * @return string PHP content
     */
    private function generate_php_index_content($html_content, $tour_name, $title) {
        $ga_measurement_id = get_option('h3tm_ga_measurement_id', 'G-08Q1M637NJ');
        $analytics_enabled = get_option('h3tm_analytics_enabled', '1');
        $track_interactions = get_option('h3tm_track_interactions', '1');
        $track_time_spent = get_option('h3tm_track_time_spent', '1');
        $custom_code = get_option('h3tm_custom_analytics_code', '');
        
        // Analytics code template
        $analytics_template = file_get_contents(H3TM_PLUGIN_DIR . 'templates/analytics-code.php');
        
        // Replace placeholders
        $analytics_code = str_replace(
            array(
                '{{GA_MEASUREMENT_ID}}',
                '{{ANALYTICS_ENABLED}}',
                '{{TRACK_INTERACTIONS}}',
                '{{TRACK_TIME_SPENT}}',
                '{{TOUR_NAME}}',
                '{{TOUR_TITLE}}',
                '{{TOUR_DIR}}',
                '{{CUSTOM_CODE}}'
            ),
            array(
                $ga_measurement_id,
                $analytics_enabled ? 'true' : 'false',
                $track_interactions ? 'true' : 'false',
                $track_time_spent ? 'true' : 'false',
                addslashes($tour_name),
                addslashes($title),
                H3TM_TOUR_DIR,
                $custom_code
            ),
            $analytics_template
        );
        
        // PHP header
        $php_header = "<?php\n";
        $php_header .= "/**\n";
        $php_header .= " * H3 Tour: " . esc_html($tour_name) . "\n";
        $php_header .= " * Generated by H3 Tour Management Plugin v" . H3TM_VERSION . "\n";
        $php_header .= " * Date: " . current_time('Y-m-d H:i:s') . "\n";
        $php_header .= " */\n\n";
        $php_header .= "// Security check\n";
        $php_header .= "if (!defined('ABSPATH')) {\n";
        $php_header .= "    define('ABSPATH', dirname(__FILE__) . '/');\n";
        $php_header .= "}\n\n";
        $php_header .= "// Tour configuration\n";
        $php_header .= "\$tour_name = '" . esc_attr($tour_name) . "';\n";
        $php_header .= "\$tour_title = '" . esc_attr($title) . "';\n";
        $php_header .= "?>\n";
        
        // Combine PHP header with HTML
        $php_content = $php_header;
        $php_content .= "<!DOCTYPE html>\n";
        $php_content .= substr($html_content, strpos($html_content, '<html'));
        
        // Insert analytics before </head>
        $php_content = str_replace('</head>', $analytics_code . "\n</head>", $php_content);
        
        return $php_content;
    }
    
    /**
     * Generate tour-specific .htaccess
     * 
     * @return string htaccess content
     */
    private function generate_tour_htaccess() {
        $content = "# H3 Tour Directory Protection\n";
        $content .= "Options -Indexes\n\n";
        $content .= "# Use PHP index file\n";
        $content .= "DirectoryIndex index.php index.html index.htm\n\n";
        $content .= "# Cache static assets\n";
        $content .= "<IfModule mod_expires.c>\n";
        $content .= "    ExpiresActive On\n";
        $content .= "    ExpiresByType image/jpg \"access plus 1 month\"\n";
        $content .= "    ExpiresByType image/jpeg \"access plus 1 month\"\n";
        $content .= "    ExpiresByType image/png \"access plus 1 month\"\n";
        $content .= "    ExpiresByType image/webp \"access plus 1 month\"\n";
        $content .= "    ExpiresByType video/mp4 \"access plus 1 month\"\n";
        $content .= "    ExpiresByType application/javascript \"access plus 1 week\"\n";
        $content .= "    ExpiresByType text/css \"access plus 1 week\"\n";
        $content .= "</IfModule>\n";
        
        return $content;
    }
    
    /**
     * Delete a tour
     * 
     * @param string $tour_name Tour name
     * @return array Result
     */
    public function delete_tour($tour_name) {
        $result = array('success' => false, 'message' => '');
        
        try {
            $tour_path = $this->tour_dir . '/' . $tour_name;
            
            // Verify tour exists
            if (!$this->filesystem->exists($tour_path)) {
                throw new Exception(__('Tour not found.', 'h3-tour-management'));
            }
            
            // Remove tour directory
            if (!$this->filesystem->rmdir($tour_path, true)) {
                throw new Exception(__('Failed to delete tour files.', 'h3-tour-management'));
            }
            
            // Remove tour from users
            $this->remove_tour_from_users($tour_name);
            
            // Delete tour metadata
            H3TM_Database::delete_tour_meta($tour_name);
            
            // Log activity
            H3TM_Database::log_activity('tour_deleted', 'tour', $tour_name);
            
            $result['success'] = true;
            $result['message'] = __('Tour deleted successfully.', 'h3-tour-management');
            
            H3TM_Logger::info('tour', 'Tour deleted', array('tour_name' => $tour_name));
            
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            H3TM_Logger::error('tour', 'Failed to delete tour', array(
                'tour_name' => $tour_name,
                'error' => $e->getMessage()
            ));
        }
        
        return $result;
    }
    
    /**
     * Rename a tour
     * 
     * @param string $old_name Current tour name
     * @param string $new_name New tour name
     * @return array Result
     */
    public function rename_tour($old_name, $new_name) {
        $result = array('success' => false, 'message' => '');
        
        try {
            $new_name = sanitize_file_name($new_name);
            $old_path = $this->tour_dir . '/' . $old_name;
            $new_path = $this->tour_dir . '/' . $new_name;
            
            // Verify old tour exists
            if (!$this->filesystem->exists($old_path)) {
                throw new Exception(__('Tour not found.', 'h3-tour-management'));
            }
            
            // Check new name doesn't exist
            if ($this->filesystem->exists($new_path)) {
                throw new Exception(__('A tour with the new name already exists.', 'h3-tour-management'));
            }
            
            // Move directory
            if (!$this->filesystem->move($old_path, $new_path)) {
                throw new Exception(__('Failed to rename tour.', 'h3-tour-management'));
            }
            
            // Update tour name in PHP file
            $this->update_tour_php_file($new_path, $old_name, $new_name);
            
            // Update user assignments
            $this->update_tour_name_for_users($old_name, $new_name);
            
            // Update metadata
            global $wpdb;
            $table = $wpdb->prefix . 'h3tm_tour_meta';
            $wpdb->update(
                $table,
                array('tour_name' => $new_name),
                array('tour_name' => $old_name),
                array('%s'),
                array('%s')
            );
            
            // Log activity
            H3TM_Database::log_activity('tour_renamed', 'tour', $new_name, array(
                'old_name' => $old_name
            ));
            
            $result['success'] = true;
            $result['message'] = __('Tour renamed successfully.', 'h3-tour-management');
            
            H3TM_Logger::info('tour', 'Tour renamed', array(
                'old_name' => $old_name,
                'new_name' => $new_name
            ));
            
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            H3TM_Logger::error('tour', 'Failed to rename tour', array(
                'old_name' => $old_name,
                'new_name' => $new_name,
                'error' => $e->getMessage()
            ));
        }
        
        return $result;
    }
    
    /**
     * Get tour title
     * 
     * @param string $tour_name Tour name
     * @return string Tour title
     */
    public function get_tour_title($tour_name) {
        // Check metadata first
        $title = H3TM_Database::get_tour_meta($tour_name, 'title');
        if ($title) {
            return $title;
        }
        
        // Try to extract from index file
        $tour_path = $this->tour_dir . '/' . $tour_name;
        $index_files = array('/index.php', '/index.html', '/index.htm');
        
        foreach ($index_files as $index) {
            $file_path = $tour_path . $index;
            if ($this->filesystem->exists($file_path)) {
                $content = $this->filesystem->get_contents($file_path);
                if (preg_match('/<title>(.*?)<\/title>/i', $content, $matches)) {
                    $title = trim($matches[1]);
                    // Cache in metadata
                    H3TM_Database::update_tour_meta($tour_name, 'title', $title);
                    return $title;
                }
            }
        }
        
        return $tour_name;
    }
    
    /**
     * Get tour thumbnail URL
     * 
     * @param string $tour_name Tour name
     * @return string|false Thumbnail URL or false
     */
    public function get_tour_thumbnail($tour_name) {
        $tour_path = $this->tour_dir . '/' . $tour_name;
        $thumbnail_files = array('thumbnail.png', 'thumbnail.jpg', 'thumb.png', 'thumb.jpg');
        
        foreach ($thumbnail_files as $thumb) {
            if ($this->filesystem->exists($tour_path . '/' . $thumb)) {
                return $this->tour_url . '/' . rawurlencode($tour_name) . '/' . $thumb;
            }
        }
        
        return false;
    }
    
    /**
     * Get directory size
     * 
     * @param string $dir Directory path
     * @return int Size in bytes
     */
    private function get_directory_size($dir) {
        $size = 0;
        $files = $this->filesystem->dirlist($dir, true, true);
        
        if ($files) {
            foreach ($files as $file) {
                if ($file['type'] === 'f') {
                    $size += $file['size'];
                }
            }
        }
        
        return $size;
    }
    
    /**
     * Update tour PHP file after rename
     * 
     * @param string $tour_path Tour directory path
     * @param string $old_name Old tour name
     * @param string $new_name New tour name
     */
    private function update_tour_php_file($tour_path, $old_name, $new_name) {
        $php_file = $tour_path . '/index.php';
        
        if ($this->filesystem->exists($php_file)) {
            $content = $this->filesystem->get_contents($php_file);
            
            // Update tour name references
            $content = str_replace(
                "\$tour_name = '" . esc_attr($old_name) . "'",
                "\$tour_name = '" . esc_attr($new_name) . "'",
                $content
            );
            
            $this->filesystem->put_contents($php_file, $content);
        }
    }
    
    /**
     * Remove tour from all users
     * 
     * @param string $tour_name Tour name
     */
    private function remove_tour_from_users($tour_name) {
        $users = get_users(array(
            'meta_key' => 'h3tm_tours',
            'meta_compare' => 'EXISTS'
        ));
        
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
     * 
     * @param string $old_name Old tour name
     * @param string $new_name New tour name
     */
    private function update_tour_name_for_users($old_name, $new_name) {
        $users = get_users(array(
            'meta_key' => 'h3tm_tours',
            'meta_compare' => 'EXISTS'
        ));
        
        foreach ($users as $user) {
            $user_tours = get_user_meta($user->ID, 'h3tm_tours', true);
            
            if (is_array($user_tours) && in_array($old_name, $user_tours)) {
                $key = array_search($old_name, $user_tours);
                $user_tours[$key] = $new_name;
                update_user_meta($user->ID, 'h3tm_tours', array_values($user_tours));
            }
        }
    }
}