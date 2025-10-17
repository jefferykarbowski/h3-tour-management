<?php
/**
 * Clear Webhook Secret - Admin Page
 *
 * Pantheon-compatible alternative to wp eval-file
 * Access via: WP Admin → 3D Tours → Clear Webhook Secret
 */

// Add admin menu item
add_action('admin_menu', function() {
    add_submenu_page(
        'h3-tour-management',
        'Clear Webhook Secret',
        'Clear Webhook Secret',
        'manage_options',
        'h3tm-clear-webhook-secret',
        'h3tm_render_clear_webhook_page'
    );
}, 100);

function h3tm_render_clear_webhook_page() {
    // Handle form submission
    if (isset($_POST['clear_webhook_secret']) && check_admin_referer('h3tm_clear_webhook_secret')) {
        // Set flag to disable signature verification instead of deleting secret
        update_option('h3tm_webhook_signature_disabled', '1');
        echo '<div class="notice notice-success"><p><strong>Success!</strong> Signature verification disabled.</p></div>';
        echo '<div class="notice notice-info"><p>Webhook URL: <code>' . admin_url('admin-ajax.php?action=h3tm_lambda_webhook') . '</code></p></div>';
    }

    // Get current status
    $signature_disabled = get_option('h3tm_webhook_signature_disabled', '0');
    $verification_enabled = ($signature_disabled !== '1');

    ?>
    <div class="wrap">
        <h1>Clear Webhook Secret</h1>

        <div class="card" style="max-width: 600px;">
            <h2>Current Status</h2>
            <p>
                <strong>Signature Verification:</strong>
                <?php if ($verification_enabled): ?>
                    <span style="color: green;">✓ Enabled</span>
                <?php else: ?>
                    <span style="color: orange;">⚠ Disabled</span>
                <?php endif; ?>
            </p>
            <p class="description">
                <?php if ($verification_enabled): ?>
                    Webhooks from Lambda are required to include a valid signature.
                <?php else: ?>
                    Webhooks from Lambda will be accepted without signature verification.
                <?php endif; ?>
            </p>
        </div>

        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>Fix Lambda Webhook 401 Errors</h2>
            <p>If you're getting <strong>401 Unauthorized</strong> errors from Lambda webhooks, disable signature verification.</p>

            <?php if ($verification_enabled): ?>
                <form method="post" onsubmit="return confirm('Are you sure you want to disable signature verification?');">
                    <?php wp_nonce_field('h3tm_clear_webhook_secret'); ?>
                    <p>
                        <button type="submit" name="clear_webhook_secret" class="button button-primary">
                            Disable Signature Verification
                        </button>
                    </p>
                </form>
                <p class="description">
                    ⚠️ This will allow Lambda to send webhooks without authentication. Only use this if you trust your Lambda function source.
                </p>
            <?php else: ?>
                <p><strong>✓ Signature verification is already disabled.</strong></p>
                <p class="description">Lambda webhooks will work without signature verification.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>Webhook URL for Lambda</h2>
            <p>Configure Lambda with this webhook URL:</p>
            <code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0;">
                <?php echo admin_url('admin-ajax.php?action=h3tm_lambda_webhook'); ?>
            </code>
        </div>
    </div>
    <?php
}
