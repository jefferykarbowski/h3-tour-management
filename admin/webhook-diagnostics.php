<?php
/**
 * Webhook Diagnostics - Debug Page
 *
 * Shows current webhook configuration and tests the signature disable flag
 * Access via: WP Admin → 3D Tours → Webhook Diagnostics
 */

// Add admin menu item
add_action('admin_menu', function() {
    add_submenu_page(
        'h3-tour-management',
        'Webhook Diagnostics',
        'Webhook Diagnostics',
        'manage_options',
        'h3tm-webhook-diagnostics',
        'h3tm_render_webhook_diagnostics_page'
    );
}, 101);

function h3tm_render_webhook_diagnostics_page() {
    ?>
    <div class="wrap">
        <h1>Webhook Diagnostics</h1>

        <div class="card" style="max-width: 800px;">
            <h2>Plugin Version</h2>
            <p><strong>Deployed Version:</strong> <?php echo defined('H3TM_VERSION') ? H3TM_VERSION : 'Unknown'; ?></p>
            <p><strong>Expected Version:</strong> 2.5.6</p>
            <p style="color: <?php echo (defined('H3TM_VERSION') && H3TM_VERSION === '2.5.6') ? 'green' : 'red'; ?>">
                <?php echo (defined('H3TM_VERSION') && H3TM_VERSION === '2.5.6') ? '✓ Correct version deployed' : '✗ Version mismatch - update needed'; ?>
            </p>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Signature Verification Status</h2>
            <?php
            $signature_disabled = get_option('h3tm_webhook_signature_disabled', '0');
            $webhook_secret = get_option('h3tm_lambda_webhook_secret', '');

            // Check if webhook handler code exists
            $webhook_file = H3TM_PLUGIN_DIR . 'includes/class-h3tm-lambda-webhook.php';
            $webhook_code = file_get_contents($webhook_file);
            $has_disable_check = strpos($webhook_code, 'h3tm_webhook_signature_disabled') !== false;
            ?>

            <table class="widefat" style="margin-top: 10px;">
                <tr>
                    <th style="width: 300px;">Setting</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td><strong>Signature Disabled Flag</strong></td>
                    <td><code>h3tm_webhook_signature_disabled = '<?php echo esc_html($signature_disabled); ?>'</code></td>
                    <td style="color: <?php echo ($signature_disabled === '1') ? 'green' : 'red'; ?>">
                        <?php echo ($signature_disabled === '1') ? '✓ Disabled' : '✗ Enabled'; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Webhook Secret Exists</strong></td>
                    <td><?php echo !empty($webhook_secret) ? 'Yes (' . substr($webhook_secret, 0, 8) . '...)' : 'No'; ?></td>
                    <td>ℹ️ Info only</td>
                </tr>
                <tr>
                    <td><strong>Code Has Disable Check</strong></td>
                    <td><?php echo $has_disable_check ? 'Yes' : 'No'; ?></td>
                    <td style="color: <?php echo $has_disable_check ? 'green' : 'red'; ?>">
                        <?php echo $has_disable_check ? '✓ v2.5.6+ code deployed' : '✗ Old code still deployed'; ?>
                    </td>
                </tr>
            </table>

            <?php if ($signature_disabled !== '1'): ?>
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-top: 15px;">
                    <strong>⚠️ Issue Detected:</strong> Signature verification is still ENABLED.
                    <br><br>
                    Go to <a href="<?php echo admin_url('admin.php?page=h3tm-clear-webhook-secret'); ?>">Clear Webhook Secret</a>
                    and click "Disable Signature Verification" to fix this.
                </div>
            <?php elseif (!$has_disable_check): ?>
                <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 10px; margin-top: 15px;">
                    <strong>🚨 Critical Issue:</strong> Old code is still deployed!
                    <br><br>
                    The v2.5.6 code with the disable check is not on the server. Please pull latest code from GitHub.
                </div>
            <?php else: ?>
                <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 10px; margin-top: 15px;">
                    <strong>✓ All Good:</strong> Signature verification is properly disabled. Webhooks should work now.
                </div>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Webhook URL</h2>
            <p>Configure Lambda with this URL:</p>
            <code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0;">
                <?php echo admin_url('admin-ajax.php?action=h3tm_lambda_webhook'); ?>
            </code>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Test Webhook Logic</h2>
            <p>This simulates what happens when Lambda sends a webhook:</p>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;">
<?php
// Simulate webhook handler logic
$sig_disabled = get_option('h3tm_webhook_signature_disabled', '0');
$webhook_secret = get_option('h3tm_lambda_webhook_secret', '');

echo "Step 1: Check disable flag\n";
echo "  \$signature_disabled = get_option('h3tm_webhook_signature_disabled', '0')\n";
echo "  Result: '{$sig_disabled}'\n\n";

echo "Step 2: Check webhook secret\n";
echo "  \$webhook_secret = get_option('h3tm_lambda_webhook_secret', '')\n";
echo "  Result: " . (!empty($webhook_secret) ? "'<secret exists>'" : "'<empty>'") . "\n\n";

echo "Step 3: Evaluate condition\n";
echo "  if (\$signature_disabled !== '1' && !empty(\$webhook_secret)) {\n";
echo "    // REQUIRE signature verification → 401 if missing\n";
echo "  } else if (\$signature_disabled === '1') {\n";
echo "    // SKIP signature verification → Accept webhook\n";
echo "  }\n\n";

echo "Step 4: Result\n";
if ($sig_disabled === '1') {
    echo "  ✅ Condition TRUE: Signature verification DISABLED\n";
    echo "  → Webhook will be accepted without signature\n";
    echo "  → No 401 error\n";
} else if (!empty($webhook_secret)) {
    echo "  ❌ Condition FALSE: Signature verification ENABLED\n";
    echo "  → Webhook requires X-Webhook-Signature header\n";
    echo "  → Lambda doesn't send this → 401 Unauthorized\n";
} else {
    echo "  ℹ️  No webhook secret exists\n";
    echo "  → Signature verification skipped\n";
}
?>
            </pre>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>All Relevant Options</h2>
            <table class="widefat" style="margin-top: 10px;">
                <tr>
                    <th>Option Name</th>
                    <th>Value</th>
                </tr>
                <?php
                $options = array(
                    'h3tm_webhook_signature_disabled',
                    'h3tm_lambda_webhook_secret',
                    'h3tm_lambda_enabled'
                );
                foreach ($options as $option) {
                    $value = get_option($option, '<not set>');
                    if ($option === 'h3tm_lambda_webhook_secret' && !empty($value)) {
                        $value = substr($value, 0, 8) . '... (truncated)';
                    }
                    echo "<tr>";
                    echo "<td><code>{$option}</code></td>";
                    echo "<td>" . esc_html($value) . "</td>";
                    echo "</tr>";
                }
                ?>
            </table>
        </div>
    </div>
    <?php
}
