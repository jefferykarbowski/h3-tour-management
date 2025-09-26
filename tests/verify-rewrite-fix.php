<?php
/**
 * Verify Rewrite Rules Fix - Test script for WordPress admin
 * Copy this URL and run in browser: http://h3vt.local/wp-admin/admin.php?page=h3tm-admin&verify_rewrite=1
 */

// Add verification to existing admin page
add_action('admin_init', 'h3tm_verify_rewrite_rules_fix');

function h3tm_verify_rewrite_rules_fix() {
    if (!isset($_GET['verify_rewrite']) || !current_user_can('manage_options')) {
        return;
    }

    echo "<div style='margin: 20px; font-family: monospace;'>";
    echo "<h1>🔧 H3 Tour Management Rewrite Rules Verification</h1>";

    // Test 1: Class exists and is loaded
    echo "<h2>1. Class Loading Test</h2>";
    if (class_exists('H3TM_S3_Proxy')) {
        echo "✅ <strong>SUCCESS:</strong> H3TM_S3_Proxy class is loaded<br>";
    } else {
        echo "❌ <strong>FAILED:</strong> H3TM_S3_Proxy class not found<br>";
        echo "   → Check that class-h3tm-s3-proxy.php is included<br>";
    }

    // Test 2: WordPress rewrite rules registration
    echo "<h2>2. Rewrite Rules Registration Test</h2>";
    $rewrite_rules = get_option('rewrite_rules');
    $h3panos_found = false;

    if (is_array($rewrite_rules)) {
        foreach ($rewrite_rules as $pattern => $replacement) {
            if (strpos($pattern, 'h3panos') !== false) {
                echo "✅ <strong>FOUND RULE:</strong> $pattern → $replacement<br>";
                $h3panos_found = true;
            }
        }
    }

    if (!$h3panos_found) {
        echo "❌ <strong>FAILED:</strong> No h3panos rewrite rules found<br>";
        echo "   → <strong>ACTION REQUIRED:</strong> Go to Settings → Permalinks → Save Changes<br>";
    }

    // Test 3: Query variables registration
    echo "<h2>3. Query Variables Test</h2>";
    global $wp;
    $h3tm_vars = array_filter($wp->public_query_vars, function($var) {
        return strpos($var, 'h3tm_') === 0;
    });

    if (!empty($h3tm_vars)) {
        echo "✅ <strong>SUCCESS:</strong> H3TM query vars registered: " . implode(', ', $h3tm_vars) . "<br>";
    } else {
        echo "❌ <strong>FAILED:</strong> No H3TM query variables found<br>";
    }

    // Test 4: Hook registration verification
    echo "<h2>4. WordPress Hook Registration</h2>";
    global $wp_filter;

    $init_hooks = $wp_filter['init'] ?? new stdClass();
    $template_hooks = $wp_filter['template_redirect'] ?? new stdClass();

    $init_found = false;
    $template_found = false;

    // Check init hooks
    if (isset($init_hooks->callbacks)) {
        foreach ($init_hooks->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) &&
                    is_object($callback['function'][0]) &&
                    get_class($callback['function'][0]) === 'H3TM_S3_Proxy') {
                    echo "✅ <strong>SUCCESS:</strong> H3TM_S3_Proxy::add_rewrite_rules hooked to init<br>";
                    $init_found = true;
                }
            }
        }
    }

    // Check template_redirect hooks
    if (isset($template_hooks->callbacks)) {
        foreach ($template_hooks->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) &&
                    is_object($callback['function'][0]) &&
                    get_class($callback['function'][0]) === 'H3TM_S3_Proxy') {
                    echo "✅ <strong>SUCCESS:</strong> H3TM_S3_Proxy::handle_tour_requests hooked to template_redirect<br>";
                    $template_found = true;
                }
            }
        }
    }

    if (!$init_found) {
        echo "❌ <strong>FAILED:</strong> H3TM_S3_Proxy not hooked to init action<br>";
    }
    if (!$template_found) {
        echo "❌ <strong>FAILED:</strong> H3TM_S3_Proxy not hooked to template_redirect action<br>";
    }

    // Test 5: Manual URL pattern testing
    echo "<h2>5. URL Pattern Testing</h2>";
    $test_patterns = array(
        '^h3panos/([^/]+)/?$',
        '^h3panos/([^/]+)/(.+)$'
    );

    $test_urls = array(
        'h3panos/Sugar-Land',
        'h3panos/Sugar-Land/',
        'h3panos/Sugar-Land/index.htm',
        'h3panos/Sugar Land',
    );

    foreach ($test_urls as $url) {
        echo "<strong>Testing:</strong> /$url<br>";
        foreach ($test_patterns as $pattern) {
            if (preg_match('#' . $pattern . '#', $url, $matches)) {
                echo "  ✅ Matches pattern: $pattern<br>";
                echo "  🎯 Variables: ";
                for ($i = 1; $i < count($matches); $i++) {
                    echo "h3tm_tour=" . ($i === 1 ? $matches[$i] : '') . " ";
                    echo "h3tm_file=" . ($i === 2 ? $matches[$i] : 'index.htm') . " ";
                }
                echo "<br>";
                break;
            }
        }
        echo "<br>";
    }

    // Test 6: WordPress flush status
    echo "<h2>6. Flush Status Check</h2>";
    $flush_version = get_option('h3tm_s3_rewrite_rules_flushed');
    $current_version = H3TM_VERSION;

    if ($flush_version === $current_version) {
        echo "✅ <strong>SUCCESS:</strong> Rewrite rules flushed for current version ($current_version)<br>";
    } else {
        echo "⚠️ <strong>WARNING:</strong> Flush version ($flush_version) ≠ current version ($current_version)<br>";
        echo "   → Rewrite rules may need refreshing<br>";
    }

    // Action buttons
    echo "<h2>7. Quick Actions</h2>";
    echo "<a href='" . admin_url('options-permalink.php') . "' class='button button-primary'>Flush Rewrite Rules (Settings → Permalinks)</a> ";
    echo "<a href='https://h3vt.local/h3panos/Sugar-Land/' target='_blank' class='button'>Test Sugar-Land URL</a> ";
    echo "<a href='https://h3vt.local/h3panos/Sugar Land/' target='_blank' class='button'>Test Sugar Land URL (with space)</a>";

    echo "</div>";
    exit;
}