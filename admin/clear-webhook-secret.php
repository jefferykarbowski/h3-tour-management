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
        delete_option('h3tm_lambda_webhook_secret');
        echo '<div class="notice notice-success"><p><strong>Success!</strong> Webhook secret cleared. Signature verification disabled.</p></div>';
        echo '<div class="notice notice-info"><p>Webhook URL: <code>' . admin_url('admin-ajax.php?action=h3tm_lambda_webhook') . '</code></p></div>';
    }

    // Get current secret status
    $current_secret = get_option('h3tm_lambda_webhook_secret', '');
    $has_secret = !empty($current_secret);

    ?>
    <div class="wrap">
        <h1>Clear Webhook Secret</h1>

        <div class="card" style="max-width: 600px;">
            <h2>Current Status</h2>
            <p>
                <strong>Webhook Secret:</strong>
                <?php if ($has_secret): ?>
                    <span style="color: green;">✓ Set</span>
                    (<?php echo substr($current_secret, 0, 8); ?>...)
                <?php else: ?>
                    <span style="color: orange;">⚠ Not Set</span>
                <?php endif; ?>
            </p>
            <p>
                <strong>Signature Verification:</strong>
                <?php echo $has_secret ? '<span style="color: green;">Enabled</span>' : '<span style="color: orange;">Disabled</span>'; ?>
            </p>
        </div>

        <div class="card" style="max-width: 600px; margin-top: 20px;">
            <h2>Fix Lambda Webhook 401 Errors</h2>
            <p>If you're getting <strong>401 Unauthorized</strong> errors from Lambda webhooks, clear the webhook secret to disable signature verification.</p>

            <?php if ($has_secret): ?>
                <form method="post" onsubmit="return confirm('Are you sure you want to clear the webhook secret?');">
                    <?php wp_nonce_field('h3tm_clear_webhook_secret'); ?>
                    <p>
                        <button type="submit" name="clear_webhook_secret" class="button button-primary">
                            Clear Webhook Secret
                        </button>
                    </p>
                </form>
                <p class="description">
                    ⚠️ This will disable webhook signature verification, allowing Lambda to send webhooks without authentication.
                </p>
            <?php else: ?>
                <p><strong>✓ Webhook secret is already cleared.</strong></p>
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
