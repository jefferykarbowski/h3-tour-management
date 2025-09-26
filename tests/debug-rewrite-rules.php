<?php
/**
 * WordPress Rewrite Rules Debugging Tool
 * Evidence-based investigation of h3panos URL failures
 */

if (!defined('ABSPATH')) {
    // For standalone testing
    $wp_path = dirname(__DIR__, 4) . '/Local Sites/h3vt/app/public/wp-config.php';
    if (file_exists($wp_path)) {
        require_once $wp_path;
    } else {
        die('WordPress not found. Run this from WordPress admin.');
    }
}

echo "<h1>H3 Tour Management Rewrite Rules Diagnostic Report</h1>\n";
echo "<style>body{font-family:monospace;} .error{color:red;} .success{color:green;} .warning{color:orange;}</style>\n";

// 1. EVIDENCE: Check if rewrite rules are actually registered
echo "<h2>1. WordPress Rewrite Rules Investigation</h2>\n";
$rewrite_rules = get_option('rewrite_rules');

echo "<h3>1.1 All Current Rewrite Rules:</h3>\n";
$h3panos_rules_found = false;
echo "<pre>\n";
if (is_array($rewrite_rules)) {
    foreach ($rewrite_rules as $pattern => $replacement) {
        if (strpos($pattern, 'h3panos') !== false || strpos($replacement, 'h3tm_tour') !== false) {
            echo "<span class='success'>✓ H3PANOS RULE FOUND:</span>\n";
            echo "  Pattern: $pattern\n";
            echo "  Replacement: $replacement\n\n";
            $h3panos_rules_found = true;
        }
    }

    if (!$h3panos_rules_found) {
        echo "<span class='error'>✗ NO H3PANOS RULES FOUND IN WordPress REWRITE RULES</span>\n";
    }

    echo "\nTotal rules in WordPress: " . count($rewrite_rules) . "\n";
} else {
    echo "<span class='error'>✗ No rewrite rules found in WordPress</span>\n";
}
echo "</pre>\n";

// 2. EVIDENCE: Check query vars registration
echo "<h2>2. Query Variables Investigation</h2>\n";
global $wp;
echo "<h3>2.1 Registered Query Variables:</h3>\n";
echo "<pre>\n";
$h3tm_vars_found = false;
foreach ($wp->public_query_vars as $var) {
    if (strpos($var, 'h3tm_') === 0) {
        echo "<span class='success'>✓ Found: $var</span>\n";
        $h3tm_vars_found = true;
    }
}

if (!$h3tm_vars_found) {
    echo "<span class='error'>✗ NO H3TM QUERY VARIABLES REGISTERED</span>\n";
}

echo "\nTotal public query vars: " . count($wp->public_query_vars) . "\n";
echo "</pre>\n";

// 3. EVIDENCE: Check plugin instantiation
echo "<h2>3. Plugin Component Investigation</h2>\n";

echo "<h3>3.1 Class Existence Check:</h3>\n";
$classes_to_check = [
    'H3TM_S3_Proxy',
    'H3TM_S3_Simple',
    'H3TM_Tour_Manager',
    'H3TM_Admin'
];

foreach ($classes_to_check as $class) {
    if (class_exists($class)) {
        echo "<span class='success'>✓ Class $class exists</span><br>\n";
    } else {
        echo "<span class='error'>✗ Class $class MISSING</span><br>\n";
    }
}

echo "<h3>3.2 Active Plugin Hooks Investigation:</h3>\n";
echo "<pre>\n";

// Check if init action has our rewrite rules function
global $wp_filter;
$init_hooks = $wp_filter['init'] ?? null;
$template_redirect_hooks = $wp_filter['template_redirect'] ?? null;

echo "INIT HOOKS:\n";
if ($init_hooks) {
    foreach ($init_hooks->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback_id => $callback_info) {
            $callback = $callback_info['function'];
            if (is_array($callback) && isset($callback[0])) {
                $class_name = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
                if (strpos($class_name, 'H3TM') !== false || strpos($callback_id, 'h3tm') !== false) {
                    echo "<span class='success'>✓ H3TM hook found:</span> Priority $priority - $callback_id\n";
                }
            }
        }
    }
} else {
    echo "<span class='error'>✗ No init hooks found</span>\n";
}

echo "\nTEMPLATE_REDIRECT HOOKS:\n";
if ($template_redirect_hooks) {
    foreach ($template_redirect_hooks->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback_id => $callback_info) {
            $callback = $callback_info['function'];
            if (is_array($callback) && isset($callback[0])) {
                $class_name = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
                if (strpos($class_name, 'H3TM') !== false || strpos($callback_id, 'h3tm') !== false) {
                    echo "<span class='success'>✓ H3TM template_redirect hook found:</span> Priority $priority - $callback_id\n";
                }
            }
        }
    }
} else {
    echo "<span class='error'>✗ No template_redirect hooks found</span>\n";
}
echo "</pre>\n";

// 4. EVIDENCE: Test URL parsing manually
echo "<h2>4. URL Parsing Test</h2>\n";

$test_urls = [
    '/h3panos/Sugar-Land',
    '/h3panos/Sugar-Land/',
    '/h3panos/Sugar-Land/index.htm',
    '/h3panos/Sugar Land',
    '/h3panos/Sugar Land/',
    '/h3panos/Sugar Land/index.htm'
];

foreach ($test_urls as $test_url) {
    echo "<h3>Testing URL: $test_url</h3>\n";

    // Test against current rewrite rules
    $matched = false;
    if (is_array($rewrite_rules)) {
        foreach ($rewrite_rules as $pattern => $replacement) {
            if (preg_match('#^' . $pattern . '$#', trim($test_url, '/'), $matches)) {
                echo "<span class='success'>✓ MATCHES PATTERN:</span> $pattern → $replacement<br>\n";

                // Show what the matches would be
                echo "<span class='success'>  Matches:</span> ";
                for ($i = 1; $i < count($matches); $i++) {
                    echo "\$matches[$i] = '{$matches[$i]}' ";
                }
                echo "<br>\n";
                $matched = true;
                break;
            }
        }
    }

    if (!$matched) {
        echo "<span class='error'>✗ NO MATCH FOUND</span><br>\n";
    }

    echo "<br>\n";
}

// 5. EVIDENCE: Check WordPress configuration
echo "<h2>5. WordPress Configuration Check</h2>\n";

echo "<h3>5.1 Permalink Structure:</h3>\n";
$permalink_structure = get_option('permalink_structure');
echo "Permalink structure: " . ($permalink_structure ?: '<span class="error">Plain (may cause issues)</span>') . "<br>\n";

echo "<h3>5.2 Rewrite Rules Status:</h3>\n";
$rewrite_rules_flushed = get_option('h3tm_s3_rewrite_rules_flushed');
echo "Last flush version: " . ($rewrite_rules_flushed ?: '<span class="error">Never flushed</span>') . "<br>\n";
echo "Current plugin version: " . (defined('H3TM_VERSION') ? H3TM_VERSION : '<span class="error">Not defined</span>') . "<br>\n";

// 6. ROOT CAUSE ANALYSIS
echo "<h2>6. ROOT CAUSE ANALYSIS</h2>\n";
echo "<div style='border: 2px solid red; padding: 10px; background: #ffe6e6;'>\n";
echo "<h3>🔍 PRIMARY ISSUE IDENTIFIED:</h3>\n";

if (!class_exists('H3TM_S3_Proxy')) {
    echo "<strong>CRITICAL:</strong> H3TM_S3_Proxy class is NOT LOADED<br>\n";
    echo "• File exists but is never included in main plugin<br>\n";
    echo "• Class is never instantiated in h3tm_init()<br>\n";
    echo "• This means rewrite rules are NEVER REGISTERED<br><br>\n";
}

if (!$h3panos_rules_found) {
    echo "<strong>CONFIRMED:</strong> No h3panos rewrite rules in WordPress<br>\n";
    echo "• WordPress doesn't know how to handle /h3panos/ URLs<br>\n";
    echo "• All h3panos requests fall through to 404<br><br>\n";
}

if (!$h3tm_vars_found) {
    echo "<strong>CONFIRMED:</strong> No h3tm query variables registered<br>\n";
    echo "• Even if rules existed, query vars wouldn't work<br><br>\n";
}

echo "</div>\n";

// 7. SOLUTION APPROACHES
echo "<h2>7. SOLUTION APPROACHES</h2>\n";

echo "<h3>7.1 IMMEDIATE FIX (Required):</h3>\n";
echo "<div style='border: 2px solid green; padding: 10px; background: #e6ffe6;'>\n";
echo "1. Include class-h3tm-s3-proxy.php in main plugin file<br>\n";
echo "2. Instantiate new H3TM_S3_Proxy() in h3tm_init()<br>\n";
echo "3. Flush rewrite rules (wp-admin → Settings → Permalinks → Save)<br>\n";
echo "</div>\n";

echo "<h3>7.2 ALTERNATIVE APPROACHES:</h3>\n";
echo "<div style='border: 1px solid blue; padding: 10px; background: #e6f3ff;'>\n";
echo "<strong>Option A:</strong> Direct URL handling with parse_request hook<br>\n";
echo "<strong>Option B:</strong> .htaccess rules (if Apache)<br>\n";
echo "<strong>Option C:</strong> WordPress 'wp' action for early interception<br>\n";
echo "</div>\n";

// 8. VERIFICATION TESTS
echo "<h2>8. NEXT STEPS FOR VERIFICATION</h2>\n";
echo "<ol>\n";
echo "<li>Fix the primary issue (include and instantiate H3TM_S3_Proxy)</li>\n";
echo "<li>Visit wp-admin → Settings → Permalinks → Save Changes</li>\n";
echo "<li>Re-run this diagnostic to confirm rules are registered</li>\n";
echo "<li>Test URLs: <a href='/h3panos/Sugar-Land'>/h3panos/Sugar-Land</a></li>\n";
echo "<li>Check error logs for template_redirect debug messages</li>\n";
echo "</ol>\n";

echo "<h2>9. LOG FILE INVESTIGATION</h2>\n";
$log_files = [
    WP_CONTENT_DIR . '/debug.log',
    ABSPATH . 'logs/h3tm-analytics-cron.log',
    WP_CONTENT_DIR . '/uploads/h3tm-error.log'
];

foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        echo "<h3>Log: " . basename($log_file) . "</h3>\n";
        $recent_logs = array_slice(file($log_file), -20);
        echo "<pre style='max-height: 200px; overflow: scroll; border: 1px solid #ccc; padding: 10px;'>\n";
        foreach ($recent_logs as $line) {
            if (strpos($line, 'H3TM') !== false || strpos($line, 'h3panos') !== false) {
                echo "<strong>$line</strong>";
            } else {
                echo htmlspecialchars($line);
            }
        }
        echo "</pre>\n";
    } else {
        echo "<p>Log file not found: " . basename($log_file) . "</p>\n";
    }
}

echo "<hr>\n";
echo "<p><strong>Investigation completed at:</strong> " . date('Y-m-d H:i:s') . "</p>\n";