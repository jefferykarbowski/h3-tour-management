<?php
class H3TM_Tour_Manager {
    
    /**
     * Constructor - Simplified for S3-only operation
     */
    public function __construct() {
        // No local tour directory needed
    }
    
    /**
     * Get all tours - Now only returns S3 tours
     * @deprecated Use H3TM_S3_Simple::list_s3_tours() directly
     */
    public function get_all_tours() {
        $s3_simple = new H3TM_S3_Simple();
        $s3_tours = $s3_simple->list_s3_tours();
        
        if (is_array($s3_tours) && !empty($s3_tours)) {
            return $s3_tours;
        }
        
        return array();
    }
    
    /**
     * Upload tour - Redirects to S3 upload
     * @deprecated Tours are managed directly in S3
     */
    public function upload_tour($tour_name, $file) {
        return array(
            'success' => false,
            'message' => __('Direct uploads are no longer supported. Tours are managed through S3.', 'h3-tour-management')
        );
    }
    
    /**
     * Delete tour - Removes from S3
     * @deprecated Use S3 management directly
     */
    public function delete_tour($tour_name) {
        return array(
            'success' => false,
            'message' => __('Tour deletion should be managed through S3 directly.', 'h3-tour-management')
        );
    }
    
    /**
     * Rename tour - Not supported for S3 tours
     * @deprecated S3 tours cannot be renamed directly
     */
    public function rename_tour($old_name, $new_name) {
        return array(
            'success' => false,
            'message' => __('Tour renaming is not supported for S3 tours.', 'h3-tour-management')
        );
    }
    
    /**
     * Remove tour from users - Still needed for user management
     */
    private function remove_tour_from_users($tour_name) {
        $users = get_users();
        foreach ($users as $user) {
            $user_tours = get_user_meta($user->ID, 'h3tm_tours', true);
            if (!empty($user_tours) && is_array($user_tours)) {
                $key = array_search($tour_name, $user_tours);
                if ($key !== false) {
                    unset($user_tours[$key]);
                    update_user_meta($user->ID, 'h3tm_tours', array_values($user_tours));
                }
            }
        }
    }
    
    /**
     * Update tour name for users
     */
    private function update_tour_name_for_users($old_name, $new_name) {
        $users = get_users();
        foreach ($users as $user) {
            $user_tours = get_user_meta($user->ID, 'h3tm_tours', true);
            if (!empty($user_tours) && is_array($user_tours)) {
                $key = array_search($old_name, $user_tours);
                if ($key !== false) {
                    $user_tours[$key] = $new_name;
                    update_user_meta($user->ID, 'h3tm_tours', $user_tours);
                }
            }
        }
    }
    
    /**
     * Get tour title from tour name
     */
    public function get_tour_title($tour_name) {
        // For now, just return the tour name as the title
        // This can be enhanced later to read from a metadata file if needed
        return $tour_name;
    }

    /**
     * Update tour analytics
     */
    public function update_tour_analytics($tour_name, $analytics_data) {
        if (!is_array($analytics_data)) {
            return false;
        }
        
        // Store analytics in database
        $option_key = 'h3tm_analytics_' . md5($tour_name);
        $existing_data = get_option($option_key, array());
        
        if (!is_array($existing_data)) {
            $existing_data = array();
        }
        
        // Merge new analytics with existing
        $merged_data = array_merge($existing_data, $analytics_data);
        
        // Keep only last 30 days of data
        $cutoff_time = strtotime('-30 days');
        foreach ($merged_data as $timestamp => $data) {
            if (is_numeric($timestamp) && $timestamp < $cutoff_time) {
                unset($merged_data[$timestamp]);
            }
        }
        
        update_option($option_key, $merged_data);
        return true;
    }
}