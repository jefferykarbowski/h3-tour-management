<?php
/**
 * User fields management (replaces ACF functionality)
 */
class H3TM_User_Fields {
    
    public function __construct() {
        // Add fields to user profile
        add_action('show_user_profile', array($this, 'add_user_fields'));
        add_action('edit_user_profile', array($this, 'add_user_fields'));
        
        // Save user fields
        add_action('personal_options_update', array($this, 'save_user_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_fields'));
        
        // Enqueue scripts on user profile page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_profile_scripts'));
        
        // Add analytics button to user profile
        add_action('show_user_profile', array($this, 'add_analytics_button'));
        add_action('edit_user_profile', array($this, 'add_analytics_button'));
        
        // AJAX handler for analytics button
        add_action('wp_ajax_h3tm_send_user_analytics', array($this, 'handle_send_user_analytics'));
    }
    
    /**
     * Add custom fields to user profile
     */
    public function add_user_fields($user) {
        $tour_manager = new H3TM_Tour_Manager();
        $available_tours = $tour_manager->get_all_tours();
        $user_tours = get_user_meta($user->ID, 'h3tm_tours', true);
        $email_frequency = $this->get_user_email_frequency($user->ID);
        
        if (!is_array($user_tours)) {
            $user_tours = array();
        }
        ?>
        <h3><?php _e('3D Tour Settings', 'h3-tour-management'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th><label for="h3tm_tours"><?php _e('Assigned Tours', 'h3-tour-management'); ?></label></th>
                <td>
                    <select name="h3tm_tours[]" id="h3tm_tours" class="h3tm-tours-select" multiple="multiple" style="width: 350px;">
                        <?php foreach ($available_tours as $tour) :
                            // Handle both array (new ID-based tours) and string (legacy tours) formats
                            $tour_name = is_array($tour) ? $tour['name'] : $tour;
                            $tour_value = $tour_name; // Use display name for both value and display
                        ?>
                            <option value="<?php echo esc_attr($tour_value); ?>" <?php selected(in_array($tour_value, $user_tours)); ?>>
                                <?php echo esc_html($tour_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select the tours this user should have access to.', 'h3-tour-management'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="h3tm_email_frequency"><?php _e('Email Frequency', 'h3-tour-management'); ?></label></th>
                <td>
                    <select name="h3tm_email_frequency" id="h3tm_email_frequency">
                        <option value="daily" <?php selected($email_frequency, 'daily'); ?>><?php _e('Daily', 'h3-tour-management'); ?></option>
                        <option value="weekly" <?php selected($email_frequency, 'weekly'); ?>><?php _e('Weekly', 'h3-tour-management'); ?></option>
                        <option value="monthly" <?php selected($email_frequency, 'monthly'); ?>><?php _e('Monthly (1st of month)', 'h3-tour-management'); ?></option>
                        <option value="never" <?php selected($email_frequency, 'never'); ?>><?php _e('Never', 'h3-tour-management'); ?></option>
                    </select>
                    <p class="description"><?php _e('How often should this user receive analytics emails?', 'h3-tour-management'); ?></p>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#h3tm_tours').select2({
                placeholder: '<?php _e('Select tours...', 'h3-tour-management'); ?>',
                allowClear: true
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add analytics button to user profile
     */
    public function add_analytics_button($user) {
        $user_tours = get_user_meta($user->ID, 'h3tm_tours', true);
        if (empty($user_tours)) {
            return;
        }
        ?>
        <h3><?php _e('Analytics', 'h3-tour-management'); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php _e('Send Analytics Email', 'h3-tour-management'); ?></th>
                <td>
                    <button type="button" onclick="h3tmSendUserAnalytics(<?php echo $user->ID; ?>)" class="button button-primary">
                        <?php _e('Send Analytics Email', 'h3-tour-management'); ?>
                    </button>
                    <span class="spinner" id="h3tm-analytics-spinner-<?php echo $user->ID; ?>" style="float: none;"></span>
                    <div id="h3tm-analytics-result-<?php echo $user->ID; ?>" style="margin-top: 10px;"></div>
                </td>
            </tr>
        </table>
        
        <script>
        function h3tmSendUserAnalytics(userID) {
            var spinner = jQuery('#h3tm-analytics-spinner-' + userID);
            var result = jQuery('#h3tm-analytics-result-' + userID);
            
            spinner.addClass('is-active');
            result.html('');
            
            jQuery.ajax({
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                type: "POST",
                data: {
                    action: "h3tm_send_user_analytics",
                    user_id: userID,
                    nonce: "<?php echo wp_create_nonce('h3tm_user_analytics'); ?>"
                },
                success: function(response) {
                    if (response.success) {
                        result.html('<div style="color: green;">' + response.data + '</div>');
                    } else {
                        result.html('<div style="color: red;">' + response.data + '</div>');
                    }
                },
                error: function() {
                    result.html('<div style="color: red;">Error sending analytics email</div>');
                },
                complete: function() {
                    spinner.removeClass('is-active');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Save user fields
     */
    public function save_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        // Save tours
        if (isset($_POST['h3tm_tours'])) {
            $tours = array_map('sanitize_text_field', $_POST['h3tm_tours']);
            update_user_meta($user_id, 'h3tm_tours', $tours);
        } else {
            delete_user_meta($user_id, 'h3tm_tours');
        }
        
        // Save email frequency
        if (isset($_POST['h3tm_email_frequency'])) {
            $this->update_user_email_frequency($user_id, sanitize_text_field($_POST['h3tm_email_frequency']));
        }
    }
    
    /**
     * Enqueue scripts on user profile page
     */
    public function enqueue_profile_scripts($hook) {
        if (!in_array($hook, array('profile.php', 'user-edit.php'))) {
            return;
        }
        
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
    }
    
    /**
     * Get user email frequency
     */
    private function get_user_email_frequency($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_user_settings';
        
        $frequency = $wpdb->get_var($wpdb->prepare(
            "SELECT email_frequency FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        return $frequency ? $frequency : 'monthly';
    }
    
    /**
     * Update user email frequency
     */
    private function update_user_email_frequency($user_id, $frequency) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_user_settings';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        if ($exists) {
            $wpdb->update(
                $table_name,
                array('email_frequency' => $frequency),
                array('user_id' => $user_id),
                array('%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'email_frequency' => $frequency
                ),
                array('%d', '%s')
            );
        }
    }
    
    /**
     * Handle send analytics AJAX request
     */
    public function handle_send_user_analytics() {
        check_ajax_referer('h3tm_user_analytics', 'nonce');
        
        $user_id = intval($_POST['user_id']);
        
        if (!current_user_can('edit_user', $user_id)) {
            wp_send_json_error(__('Unauthorized', 'h3-tour-management'));
        }
        
        try {
            $analytics = new H3TM_Analytics();
            $analytics->send_analytics_for_user($user_id);
            wp_send_json_success(__('Analytics email sent successfully', 'h3-tour-management'));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}