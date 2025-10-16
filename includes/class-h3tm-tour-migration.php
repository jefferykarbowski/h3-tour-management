<?php
/**
 * Tour Migration Class
 *
 * Handles one-time migration of legacy tours to ID-based system via Lambda
 *
 * @package    H3_Tour_Management
 * @subpackage H3_Tour_Management/includes
 */

class H3TM_Tour_Migration {

    /**
     * Execute the migration via Lambda
     */
    public static function execute_migration() {
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            return new WP_Error('unauthorized', 'You do not have permission to run migrations.');
        }

        // Get Lambda webhook URL
        $lambda_url = get_option('h3tm_lambda_webhook_url');
        if (empty($lambda_url)) {
            return new WP_Error('no_lambda_url', 'Lambda webhook URL not configured.');
        }

        error_log('H3TM Migration: Calling Lambda at ' . $lambda_url);

        // Call Lambda migration endpoint
        $response = wp_remote_post($lambda_url, array(
            'timeout' => 300, // 5 minutes - migration might take time
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'action' => 'migrate_tours',
                'timestamp' => current_time('mysql')
            ))
        ));

        if (is_wp_error($response)) {
            error_log('H3TM Migration Error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log('H3TM Migration Response: Status ' . $status_code);
        error_log('H3TM Migration Response Body: ' . $body);

        if ($status_code !== 200) {
            return new WP_Error('lambda_error', 'Lambda returned status ' . $status_code . ': ' . $body);
        }

        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', 'Invalid JSON response from Lambda');
        }

        return $result;
    }

    /**
     * Get migration status/history
     */
    public static function get_migration_status() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_tour_metadata';

        // Count tours with and without IDs
        $total_tours = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $tours_with_ids = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE tour_id IS NOT NULL AND tour_id != ''");
        $legacy_tours = $total_tours - $tours_with_ids;

        return array(
            'total_tours' => (int) $total_tours,
            'tours_with_ids' => (int) $tours_with_ids,
            'legacy_tours' => (int) $legacy_tours,
            'migration_needed' => $legacy_tours > 0
        );
    }

    /**
     * Render migration admin page
     */
    public static function render_migration_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle migration execution
        $migration_result = null;
        if (isset($_POST['execute_migration']) && check_admin_referer('h3tm_execute_migration')) {
            $migration_result = self::execute_migration();
        }

        $status = self::get_migration_status();

        ?>
        <div class="wrap">
            <h1>H3 Tour Migration</h1>
            <p>Convert legacy tours to the new ID-based system.</p>

            <div class="card">
                <h2>Migration Status</h2>
                <table class="widefat">
                    <tr>
                        <th>Total Tours:</th>
                        <td><?php echo esc_html($status['total_tours']); ?></td>
                    </tr>
                    <tr>
                        <th>Tours with IDs:</th>
                        <td><?php echo esc_html($status['tours_with_ids']); ?></td>
                    </tr>
                    <tr>
                        <th>Legacy Tours:</th>
                        <td><?php echo esc_html($status['legacy_tours']); ?></td>
                    </tr>
                    <tr>
                        <th>Migration Needed:</th>
                        <td><?php echo $status['migration_needed'] ? '<strong>Yes</strong>' : 'No'; ?></td>
                    </tr>
                </table>
            </div>

            <?php if ($migration_result): ?>
                <div class="notice <?php echo is_wp_error($migration_result) ? 'notice-error' : 'notice-success'; ?>">
                    <h3>Migration Results</h3>
                    <?php if (is_wp_error($migration_result)): ?>
                        <p><strong>Error:</strong> <?php echo esc_html($migration_result->get_error_message()); ?></p>
                    <?php else: ?>
                        <table class="widefat">
                            <tr>
                                <th>Total Tours:</th>
                                <td><?php echo esc_html($migration_result['total_tours']); ?></td>
                            </tr>
                            <tr>
                                <th>Migrated:</th>
                                <td><?php echo esc_html($migration_result['migrated']); ?></td>
                            </tr>
                            <tr>
                                <th>Skipped (already had IDs):</th>
                                <td><?php echo esc_html($migration_result['skipped']); ?></td>
                            </tr>
                            <tr>
                                <th>Errors:</th>
                                <td><?php echo esc_html($migration_result['errors']); ?></td>
                            </tr>
                        </table>

                        <?php if (!empty($migration_result['details'])): ?>
                            <h4>Details:</h4>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>S3 Folder</th>
                                        <th>Action</th>
                                        <th>Tour ID</th>
                                        <th>Tour Slug</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($migration_result['details'] as $detail): ?>
                                        <tr>
                                            <td><?php echo esc_html($detail['s3_folder']); ?></td>
                                            <td><strong><?php echo esc_html($detail['action']); ?></strong></td>
                                            <td><?php echo esc_html($detail['tour_id'] ?? '-'); ?></td>
                                            <td><?php echo esc_html($detail['tour_slug'] ?? '-'); ?></td>
                                            <td><?php echo esc_html($detail['reason']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($status['migration_needed']): ?>
                <div class="card">
                    <h2>⚠️ Migration Required</h2>
                    <p>You have <strong><?php echo esc_html($status['legacy_tours']); ?> legacy tour(s)</strong> that need to be migrated to the new ID-based system.</p>
                    <p>This is a <strong>one-time operation</strong> that will:</p>
                    <ul>
                        <li>Generate unique IDs for all tours without IDs</li>
                        <li>Create metadata entries with tour_id, tour_slug, and s3_folder</li>
                        <li>Preserve all existing tour data</li>
                    </ul>
                    <p><strong>Note:</strong> This process is safe and can be run multiple times. Tours with IDs will be skipped.</p>

                    <form method="post">
                        <?php wp_nonce_field('h3tm_execute_migration'); ?>
                        <button type="submit" name="execute_migration" class="button button-primary button-hero" onclick="return confirm('Are you sure you want to migrate all legacy tours? This will assign IDs to tours that don\'t have them.');">
                            🚀 Execute Migration
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>✅ All tours are using the ID-based system!</strong></p>
                    <p>No migration needed.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add migration page to admin menu
     */
    public static function add_migration_menu() {
        add_submenu_page(
            'h3tm-settings',
            'Tour Migration',
            'Tour Migration',
            'manage_options',
            'h3tm-migration',
            array(__CLASS__, 'render_migration_page')
        );
    }
}

// Register admin menu
add_action('admin_menu', array('H3TM_Tour_Migration', 'add_migration_menu'), 20);
