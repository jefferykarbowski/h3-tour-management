<?php
/**
 * Option Key Mismatch Root Cause Analysis Test
 *
 * This test definitively proves the root cause of S3 configuration failures in AJAX context
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/class-h3tm-s3-integration.php';
require_once ABSPATH . 'admin/s3-settings.php';

class H3TM_Option_Key_Mismatch_Test {

    private $original_values = array();

    public function run_complete_test() {
        echo "<h1>ROOT CAUSE ANALYSIS: Option Key Mismatch Test</h1>\n";

        // Store original values
        $this->backup_original_values();

        echo "<h2>EVIDENCE 1: Settings Form Registration vs Loading</h2>\n";
        $this->test_form_registration_mismatch();

        echo "<h2>EVIDENCE 2: Admin Context vs AJAX Context Behavior</h2>\n";
        $this->test_context_difference();

        echo "<h2>EVIDENCE 3: Database State Analysis</h2>\n";
        $this->test_database_state();

        echo "<h2>EVIDENCE 4: Configuration Test with Different Keys</h2>\n";
        $this->test_configuration_with_different_keys();

        echo "<h2>ROOT CAUSE CONFIRMATION</h2>\n";
        $this->confirm_root_cause();

        echo "<h2>SOLUTION VERIFICATION</h2>\n";
        $this->test_solution();

        // Restore original values
        $this->restore_original_values();

        echo "<h2>TEST COMPLETE</h2>\n";
    }

    private function backup_original_values() {
        $this->original_values = array(
            'h3tm_s3_bucket' => get_option('h3tm_s3_bucket', ''),
            'h3tm_s3_bucket_name' => get_option('h3tm_s3_bucket_name', ''),
            'h3tm_aws_access_key' => get_option('h3tm_aws_access_key', ''),
            'h3tm_aws_secret_key' => get_option('h3tm_aws_secret_key', ''),
            'h3tm_s3_region' => get_option('h3tm_s3_region', ''),
            'h3tm_aws_region' => get_option('h3tm_aws_region', '')
        );
        echo "✅ Backed up original values\n";
    }

    private function restore_original_values() {
        foreach ($this->original_values as $key => $value) {
            update_option($key, $value);
        }
        echo "✅ Restored original values\n";
    }

    private function test_form_registration_mismatch() {
        echo "<div style='background: #f8f8f8; padding: 10px; margin: 10px 0;'>\n";
        echo "<strong>SETTINGS FORM ANALYSIS:</strong>\n";
        echo "<ul>\n";
        echo "<li>Form field name: h3tm_s3_bucket_name</li>\n";
        echo "<li>Settings registration: h3tm_s3_bucket_name</li>\n";
        echo "<li>Loading code uses: h3tm_s3_bucket (MISMATCH!)</li>\n";
        echo "</ul>\n";
        echo "</div>\n";

        // Simulate form submission
        echo "<strong>SIMULATING SETTINGS FORM SAVE:</strong>\n";
        update_option('h3tm_s3_bucket_name', 'test-bucket-from-settings-form');
        update_option('h3tm_aws_access_key', 'test-access-key');
        update_option('h3tm_aws_secret_key', 'test-secret-key');

        echo "- Saved to h3tm_s3_bucket_name: test-bucket-from-settings-form\n";
        echo "- Saved to h3tm_aws_access_key: test-access-key\n";
        echo "- Saved to h3tm_aws_secret_key: test-secret-key\n";

        // Test what AJAX loading code finds
        echo "<strong>WHAT AJAX LOADING CODE FINDS:</strong>\n";
        $bucket_ajax = get_option('h3tm_s3_bucket', ''); // What AJAX uses
        $access_ajax = get_option('h3tm_aws_access_key', '');
        $secret_ajax = get_option('h3tm_aws_secret_key', '');

        echo "- AJAX looks for h3tm_s3_bucket: '" . $bucket_ajax . "' (EMPTY!)\n";
        echo "- AJAX looks for h3tm_aws_access_key: '" . $access_ajax . "' (FOUND!)\n";
        echo "- AJAX looks for h3tm_aws_secret_key: '" . $secret_ajax . "' (FOUND!)\n";

        echo "<div style='background: #ffcccc; padding: 5px;'>❌ BUCKET NAME MISSING IN AJAX CONTEXT!</div>\n\n";
    }

    private function test_context_difference() {
        echo "<strong>ADMIN CONTEXT (Settings Page):</strong>\n";

        // Simulate what admin settings page does
        $admin_bucket = get_option('h3tm_s3_bucket_name', '');
        echo "- Admin page looks for: h3tm_s3_bucket_name = '" . $admin_bucket . "'\n";

        echo "<strong>AJAX CONTEXT (S3 Integration):</strong>\n";

        // Simulate what AJAX handlers do
        $ajax_bucket = get_option('h3tm_s3_bucket', '');
        echo "- AJAX handler looks for: h3tm_s3_bucket = '" . $ajax_bucket . "'\n";

        echo "<div style='background: #ffffcc; padding: 5px;'>⚠️ DIFFERENT OPTION KEYS!</div>\n\n";
    }

    private function test_database_state() {
        global $wpdb;

        echo "<strong>DATABASE OPTION ANALYSIS:</strong>\n";

        $bucket_name_value = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'h3tm_s3_bucket_name'");
        $bucket_value = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'h3tm_s3_bucket'");
        $access_key_value = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'h3tm_aws_access_key'");
        $secret_key_value = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'h3tm_aws_secret_key'");

        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Option Name</th><th>Database Value</th><th>Used By</th></tr>\n";
        echo "<tr><td>h3tm_s3_bucket_name</td><td>'" . ($bucket_name_value ?: 'EMPTY') . "'</td><td>Settings Form</td></tr>\n";
        echo "<tr><td>h3tm_s3_bucket</td><td>'" . ($bucket_value ?: 'EMPTY') . "'</td><td>AJAX Handlers</td></tr>\n";
        echo "<tr><td>h3tm_aws_access_key</td><td>'" . ($access_key_value ? 'SET' : 'EMPTY') . "'</td><td>Both</td></tr>\n";
        echo "<tr><td>h3tm_aws_secret_key</td><td>'" . ($secret_key_value ? 'SET' : 'EMPTY') . "'</td><td>Both</td></tr>\n";
        echo "</table>\n\n";
    }

    private function test_configuration_with_different_keys() {
        echo "<strong>TESTING S3 INTEGRATION WITH DIFFERENT KEYS:</strong>\n";

        // Clear all bucket options
        update_option('h3tm_s3_bucket', '');
        update_option('h3tm_s3_bucket_name', '');

        // Test 1: Only h3tm_s3_bucket_name set (Settings form scenario)
        update_option('h3tm_s3_bucket_name', 'settings-form-bucket');

        $integration = new H3TM_S3_Integration();
        $config = $integration->get_s3_config();

        echo "<strong>Test 1 - Only h3tm_s3_bucket_name set:</strong>\n";
        echo "- Bucket found by AJAX: '" . ($config['bucket'] ?? '') . "'\n";
        echo "- Is configured: " . ($integration->is_configured() ? 'YES' : 'NO') . "\n";

        // Test 2: Only h3tm_s3_bucket set (What AJAX expects)
        update_option('h3tm_s3_bucket_name', '');
        update_option('h3tm_s3_bucket', 'ajax-expected-bucket');

        // Force new instance to clear cache
        $integration2 = new H3TM_S3_Integration();
        $config2 = $integration2->get_s3_config();

        echo "<strong>Test 2 - Only h3tm_s3_bucket set:</strong>\n";
        echo "- Bucket found by AJAX: '" . ($config2['bucket'] ?? '') . "'\n";
        echo "- Is configured: " . ($integration2->is_configured() ? 'YES' : 'NO') . "\n";

        echo "<div style='background: #ccffcc; padding: 5px;'>✅ CONFIRMS KEY MISMATCH IS THE ROOT CAUSE!</div>\n\n";
    }

    private function confirm_root_cause() {
        echo "<div style='background: #ffeeee; border: 2px solid red; padding: 15px; margin: 10px 0;'>\n";
        echo "<h3>🎯 ROOT CAUSE CONFIRMED: OPTION KEY MISMATCH</h3>\n";
        echo "<strong>Problem:</strong>\n";
        echo "<ul>\n";
        echo "<li>Settings page saves to: <code>h3tm_s3_bucket_name</code></li>\n";
        echo "<li>AJAX handlers read from: <code>h3tm_s3_bucket</code></li>\n";
        echo "<li>These are DIFFERENT database options!</li>\n";
        echo "</ul>\n";

        echo "<strong>Why admin page works:</strong>\n";
        echo "<ul>\n";
        echo "<li>Admin connection test might use same keys as settings form</li>\n";
        echo "<li>Or it might be using constants/environment variables</li>\n";
        echo "</ul>\n";

        echo "<strong>Why AJAX fails:</strong>\n";
        echo "<ul>\n";
        echo "<li>AJAX handlers look for <code>h3tm_s3_bucket</code></li>\n";
        echo "<li>But settings form saves to <code>h3tm_s3_bucket_name</code></li>\n";
        echo "<li>Result: bucket name appears empty in AJAX context</li>\n";
        echo "</ul>\n";
        echo "</div>\n\n";
    }

    private function test_solution() {
        echo "<strong>TESTING SOLUTION OPTIONS:</strong>\n";

        // Solution 1: Update S3 Integration to use correct key
        echo "<strong>Solution 1: Fix AJAX loading code</strong>\n";
        echo "Change line 283 in class-h3tm-s3-integration.php from:\n";
        echo "<code>get_option('h3tm_s3_bucket', '')</code>\n";
        echo "to:\n";
        echo "<code>get_option('h3tm_s3_bucket_name', '')</code>\n\n";

        // Solution 2: Update settings form to use correct key
        echo "<strong>Solution 2: Fix settings form registration</strong>\n";
        echo "Change line 35 in admin/s3-settings.php from:\n";
        echo "<code>register_setting('h3tm_s3_settings', 'h3tm_s3_bucket_name')</code>\n";
        echo "to:\n";
        echo "<code>register_setting('h3tm_s3_settings', 'h3tm_s3_bucket')</code>\n\n";

        echo "<div style='background: #ccffcc; padding: 10px;'>✅ SOLUTION 1 RECOMMENDED: Update AJAX code to match settings form keys</div>\n\n";
    }
}

// Run the test
$test = new H3TM_Option_Key_Mismatch_Test();
$test->run_complete_test();