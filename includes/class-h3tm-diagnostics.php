<?php
/**
 * H3TM Diagnostics Page
 * Provides tools for debugging tour metadata and slug conflicts
 */

class H3TM_Diagnostics {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_diagnostics_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_ajax_h3tm_run_diagnostic', array($this, 'handle_diagnostic_ajax'));
        add_action('wp_ajax_h3tm_cleanup_orphaned', array($this, 'handle_cleanup_ajax'));
    }

    public function add_diagnostics_page() {
        add_submenu_page(
            'h3-tour-management',
            'Diagnostics',
            'Diagnostics',
            'manage_options',
            'h3tm-diagnostics',
            array($this, 'render_diagnostics_page')
        );
    }

    public function enqueue_styles($hook) {
        if ($hook !== '3d-tours_page_h3tm-diagnostics') {
            return;
        }
        ?>
        <style>
            .h3tm-diagnostics-container {
                max-width: 1200px;
                margin: 20px 0;
            }
            .h3tm-diag-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin-bottom: 20px;
                padding: 20px;
            }
            .h3tm-diag-section h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .h3tm-diag-output {
                background: #f6f7f7;
                border: 1px solid #ddd;
                padding: 15px;
                margin: 15px 0;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                max-height: 500px;
                overflow-y: auto;
                white-space: pre-wrap;
            }
            .h3tm-diag-output.empty {
                color: #999;
                font-style: italic;
            }
            .h3tm-tool-button {
                margin-right: 10px;
                margin-bottom: 10px;
            }
            .h3tm-loading {
                display: none;
                margin-left: 10px;
            }
            .h3tm-loading.active {
                display: inline-block;
            }
            .h3tm-result-success { color: #46b450; }
            .h3tm-result-warning { color: #ffb900; }
            .h3tm-result-error { color: #dc3232; }
            .h3tm-result-info { color: #00a0d2; }
        </style>
        <?php
    }

    public function render_diagnostics_page() {
        ?>
        <div class="wrap">
            <h1>Tour Diagnostics</h1>
            <p>Use these tools to diagnose and fix tour metadata issues.</p>

            <div class="h3tm-diagnostics-container">

                <!-- Slug Conflict Checker -->
                <div class="h3tm-diag-section">
                    <h2>🔍 Slug Conflict Checker</h2>
                    <p>Check if a URL slug is in use and identify which tours are blocking it.</p>

                    <div>
                        <input type="text" id="slug-to-check" placeholder="Enter slug (e.g., arden-pikesville)"
                               style="width: 300px;" value="">
                        <button type="button" class="button button-primary h3tm-tool-button"
                                onclick="runSlugCheck()">Check Slug</button>
                        <span class="h3tm-loading spinner"></span>
                    </div>

                    <div id="slug-check-output" class="h3tm-diag-output empty">
                        Results will appear here...
                    </div>
                </div>

                <!-- Database Overview -->
                <div class="h3tm-diag-section">
                    <h2>📊 Database Overview</h2>
                    <p>View summary of all tours in the database and their statuses.</p>

                    <button type="button" class="button button-primary h3tm-tool-button"
                            onclick="runDatabaseOverview()">View Overview</button>
                    <span class="h3tm-loading spinner"></span>

                    <div id="db-overview-output" class="h3tm-diag-output empty">
                        Results will appear here...
                    </div>
                </div>

                <!-- Orphaned Tours -->
                <div class="h3tm-diag-section">
                    <h2>🗑️ Orphaned Tours Cleanup</h2>
                    <p>Find and remove database entries for tours that no longer exist in S3.</p>

                    <button type="button" class="button button-primary h3tm-tool-button"
                            onclick="findOrphaned()">Find Orphaned Tours</button>
                    <button type="button" class="button button-secondary h3tm-tool-button"
                            onclick="cleanupOrphaned()" id="cleanup-orphaned-btn" disabled>
                        Clean Up Orphaned Tours
                    </button>
                    <span class="h3tm-loading spinner"></span>

                    <div id="orphaned-output" class="h3tm-diag-output empty">
                        Results will appear here...
                    </div>
                </div>

                <!-- Recent Changes -->
                <div class="h3tm-diag-section">
                    <h2>📝 Recent Database Changes</h2>
                    <p>View the most recently modified tours.</p>

                    <button type="button" class="button button-primary h3tm-tool-button"
                            onclick="viewRecentChanges()">View Recent Changes</button>
                    <span class="h3tm-loading spinner"></span>

                    <div id="recent-changes-output" class="h3tm-diag-output empty">
                        Results will appear here...
                    </div>
                </div>

            </div>
        </div>

        <script type="text/javascript">
        let orphanedTourIds = [];

        function showLoading(button) {
            jQuery(button).prop('disabled', true);
            jQuery(button).siblings('.h3tm-loading').addClass('active');
        }

        function hideLoading(button) {
            jQuery(button).prop('disabled', false);
            jQuery(button).siblings('.h3tm-loading').removeClass('active');
        }

        function runSlugCheck() {
            const button = event.target;
            const slug = jQuery('#slug-to-check').val().trim();

            if (!slug) {
                alert('Please enter a slug to check');
                return;
            }

            showLoading(button);

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'h3tm_run_diagnostic',
                    tool: 'check_slug',
                    slug: slug,
                    nonce: '<?php echo wp_create_nonce('h3tm_diagnostics'); ?>'
                },
                success: function(response) {
                    hideLoading(button);
                    if (response.success) {
                        jQuery('#slug-check-output').removeClass('empty').html(response.data.output);
                    } else {
                        jQuery('#slug-check-output').removeClass('empty')
                            .html('<span class="h3tm-result-error">Error: ' + response.data + '</span>');
                    }
                },
                error: function() {
                    hideLoading(button);
                    jQuery('#slug-check-output').removeClass('empty')
                        .html('<span class="h3tm-result-error">AJAX request failed</span>');
                }
            });
        }

        function runDatabaseOverview() {
            const button = event.target;
            showLoading(button);

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'h3tm_run_diagnostic',
                    tool: 'db_overview',
                    nonce: '<?php echo wp_create_nonce('h3tm_diagnostics'); ?>'
                },
                success: function(response) {
                    hideLoading(button);
                    if (response.success) {
                        jQuery('#db-overview-output').removeClass('empty').html(response.data.output);
                    } else {
                        jQuery('#db-overview-output').removeClass('empty')
                            .html('<span class="h3tm-result-error">Error: ' + response.data + '</span>');
                    }
                },
                error: function() {
                    hideLoading(button);
                    jQuery('#db-overview-output').removeClass('empty')
                        .html('<span class="h3tm-result-error">AJAX request failed</span>');
                }
            });
        }

        function findOrphaned() {
            const button = event.target;
            showLoading(button);

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'h3tm_run_diagnostic',
                    tool: 'find_orphaned',
                    nonce: '<?php echo wp_create_nonce('h3tm_diagnostics'); ?>'
                },
                success: function(response) {
                    hideLoading(button);
                    if (response.success) {
                        orphanedTourIds = response.data.orphaned_ids || [];
                        jQuery('#orphaned-output').removeClass('empty').html(response.data.output);
                        jQuery('#cleanup-orphaned-btn').prop('disabled', orphanedTourIds.length === 0);
                    } else {
                        jQuery('#orphaned-output').removeClass('empty')
                            .html('<span class="h3tm-result-error">Error: ' + response.data + '</span>');
                    }
                },
                error: function() {
                    hideLoading(button);
                    jQuery('#orphaned-output').removeClass('empty')
                        .html('<span class="h3tm-result-error">AJAX request failed</span>');
                }
            });
        }

        function cleanupOrphaned() {
            if (orphanedTourIds.length === 0) {
                alert('No orphaned tours to clean up');
                return;
            }

            if (!confirm('Are you sure you want to delete ' + orphanedTourIds.length + ' orphaned tour(s) from the database?')) {
                return;
            }

            const button = event.target;
            showLoading(button);

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'h3tm_cleanup_orphaned',
                    tour_ids: orphanedTourIds,
                    nonce: '<?php echo wp_create_nonce('h3tm_diagnostics'); ?>'
                },
                success: function(response) {
                    hideLoading(button);
                    if (response.success) {
                        jQuery('#orphaned-output').html(response.data.output);
                        jQuery('#cleanup-orphaned-btn').prop('disabled', true);
                        orphanedTourIds = [];
                    } else {
                        alert('Cleanup failed: ' + response.data);
                    }
                },
                error: function() {
                    hideLoading(button);
                    alert('AJAX request failed');
                }
            });
        }

        function viewRecentChanges() {
            const button = event.target;
            showLoading(button);

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'h3tm_run_diagnostic',
                    tool: 'recent_changes',
                    nonce: '<?php echo wp_create_nonce('h3tm_diagnostics'); ?>'
                },
                success: function(response) {
                    hideLoading(button);
                    if (response.success) {
                        jQuery('#recent-changes-output').removeClass('empty').html(response.data.output);
                    } else {
                        jQuery('#recent-changes-output').removeClass('empty')
                            .html('<span class="h3tm-result-error">Error: ' + response.data + '</span>');
                    }
                },
                error: function() {
                    hideLoading(button);
                    jQuery('#recent-changes-output').removeClass('empty')
                        .html('<span class="h3tm-result-error">AJAX request failed</span>');
                }
            });
        }
        </script>
        <?php
    }

    public function handle_diagnostic_ajax() {
        check_ajax_referer('h3tm_diagnostics', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $tool = sanitize_text_field($_POST['tool']);

        switch ($tool) {
            case 'check_slug':
                $this->check_slug_conflict();
                break;
            case 'db_overview':
                $this->database_overview();
                break;
            case 'find_orphaned':
                $this->find_orphaned_tours();
                break;
            case 'recent_changes':
                $this->recent_changes();
                break;
            default:
                wp_send_json_error('Invalid tool');
        }
    }

    private function check_slug_conflict() {
        global $wpdb;
        $slug = sanitize_title($_POST['slug']);
        $table_name = $wpdb->prefix . 'h3tm_tour_metadata';

        $output = "=== SLUG CONFLICT CHECK: '{$slug}' ===\n\n";

        // Check all tours with this slug
        $all_tours = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE tour_slug = %s",
            $slug
        ));

        if (empty($all_tours)) {
            $output .= "<span class='h3tm-result-success'>✅ Slug is AVAILABLE - No tours found with this slug</span>\n";
        } else {
            $output .= "<span class='h3tm-result-info'>Found " . count($all_tours) . " tour(s) with this slug:</span>\n\n";

            foreach ($all_tours as $tour) {
                $is_active = !in_array($tour->status, ['deleted', 'archived', 'failed']);
                $status_class = $is_active ? 'h3tm-result-error' : 'h3tm-result-warning';
                $status_icon = $is_active ? '🚫' : '⚠️';

                $output .= "{$status_icon} Tour #{$tour->id}\n";
                $output .= "   Tour ID: {$tour->tour_id}\n";
                $output .= "   Display Name: {$tour->display_name}\n";
                $output .= "   Status: <span class='{$status_class}'>{$tour->status}</span>\n";
                $output .= "   S3 Folder: {$tour->s3_folder}\n";
                $output .= "   Created: {$tour->created_date}\n";
                $output .= "   Updated: {$tour->updated_date}\n\n";
            }
        }

        // Check active blocking tours
        $active_tours = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE tour_slug = %s AND status NOT IN ('deleted', 'archived', 'failed')",
            $slug
        ));

        if (!empty($active_tours)) {
            $output .= "\n<span class='h3tm-result-error'>❌ SLUG IS BLOCKED by " . count($active_tours) . " active tour(s)</span>\n";
        } else if (!empty($all_tours)) {
            $output .= "\n<span class='h3tm-result-success'>✅ Slug exists but NOT blocked (all tours are deleted/archived)</span>\n";
            $output .= "\nRecommendation: Safe to manually delete these entries\n";
        }

        wp_send_json_success(array('output' => $output));
    }

    private function database_overview() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_tour_metadata';

        $output = "=== DATABASE OVERVIEW ===\n\n";

        // Total tours
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $output .= "Total Tours: {$total}\n\n";

        // By status
        $by_status = $wpdb->get_results("SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status ORDER BY count DESC");
        $output .= "Tours by Status:\n";
        foreach ($by_status as $row) {
            $output .= "  {$row->status}: {$row->count}\n";
        }

        // Recent additions
        $output .= "\nRecently Added (Last 10):\n";
        $recent = $wpdb->get_results("SELECT tour_slug, display_name, status, created_date FROM {$table_name} ORDER BY created_date DESC LIMIT 10");
        foreach ($recent as $tour) {
            $output .= "  • {$tour->display_name} ({$tour->tour_slug}) - {$tour->status} - {$tour->created_date}\n";
        }

        wp_send_json_success(array('output' => $output));
    }

    private function find_orphaned_tours() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_tour_metadata';

        $output = "=== ORPHANED TOURS CHECK ===\n\n";

        // Get all tours from database
        $db_tours = $wpdb->get_results("SELECT * FROM {$table_name}");
        $output .= "Database tours: " . count($db_tours) . "\n";

        // Get tours from S3
        if (!class_exists('H3TM_S3_Simple')) {
            $output .= "<span class='h3tm-result-error'>❌ ERROR: H3TM_S3_Simple class not found</span>\n";
            $output .= "Make sure the S3 integration is properly loaded.\n";
            wp_send_json_success(array('output' => $output, 'orphaned_ids' => array()));
            return;
        }

        try {
            $s3 = new H3TM_S3_Simple();
            $s3_result = $s3->list_tours();

            if (!$s3_result['success']) {
                $output .= "<span class='h3tm-result-error'>❌ ERROR: Could not list S3 tours</span>\n";
                $output .= "Message: " . esc_html($s3_result['message']) . "\n";
                wp_send_json_success(array('output' => $output, 'orphaned_ids' => array()));
                return;
            }
        } catch (Exception $e) {
            $output .= "<span class='h3tm-result-error'>❌ EXCEPTION: " . esc_html($e->getMessage()) . "</span>\n";
            wp_send_json_success(array('output' => $output, 'orphaned_ids' => array()));
            return;
        }

        $s3_tour_ids = array();
        foreach ($s3_result['tours'] as $tour) {
            $s3_tour_ids[] = $tour['name'];
        }

        $output .= "S3 tours: " . count($s3_tour_ids) . "\n\n";

        // Find orphaned
        $orphaned = array();
        $orphaned_ids = array();

        foreach ($db_tours as $tour) {
            if (!in_array($tour->tour_id, $s3_tour_ids)) {
                $orphaned[] = $tour;
                $orphaned_ids[] = $tour->id;
            }
        }

        if (empty($orphaned)) {
            $output .= "<span class='h3tm-result-success'>✅ No orphaned tours found!</span>\n";
        } else {
            $output .= "<span class='h3tm-result-warning'>⚠️  Found " . count($orphaned) . " orphaned tour(s):</span>\n\n";

            foreach ($orphaned as $tour) {
                $output .= "🗑️  #{$tour->id}: {$tour->tour_slug} ({$tour->display_name})\n";
                $output .= "   Tour ID: {$tour->tour_id} - Status: {$tour->status}\n";
                $output .= "   S3 Folder: {$tour->s3_folder}\n\n";
            }

            $output .= "\n<span class='h3tm-result-info'>Click 'Clean Up Orphaned Tours' to remove these entries</span>\n";
        }

        wp_send_json_success(array('output' => $output, 'orphaned_ids' => $orphaned_ids));
    }

    private function recent_changes() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_tour_metadata';

        $output = "=== RECENT DATABASE CHANGES ===\n\n";

        $recent = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY updated_date DESC LIMIT 20"
        );

        $output .= "Last 20 Modified Tours:\n\n";

        foreach ($recent as $tour) {
            $output .= "• {$tour->display_name}\n";
            $output .= "  Slug: {$tour->tour_slug}\n";
            $output .= "  Status: {$tour->status}\n";
            $output .= "  Updated: {$tour->updated_date}\n\n";
        }

        wp_send_json_success(array('output' => $output));
    }

    public function handle_cleanup_ajax() {
        check_ajax_referer('h3tm_diagnostics', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'h3tm_tour_metadata';
        $tour_ids = array_map('intval', $_POST['tour_ids']);

        $output = "=== CLEANUP RESULTS ===\n\n";
        $deleted_count = 0;

        foreach ($tour_ids as $id) {
            $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));

            if ($result) {
                $deleted_count++;
                $output .= "✅ Deleted tour ID #{$id}\n";
            } else {
                $output .= "❌ Failed to delete tour ID #{$id}\n";
            }
        }

        $output .= "\n<span class='h3tm-result-success'>✅ Cleanup complete! Deleted {$deleted_count} orphaned tour(s)</span>\n";

        wp_send_json_success(array('output' => $output));
    }
}
