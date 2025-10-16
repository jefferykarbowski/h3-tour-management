<?php
/**
 * Clear Webhook Secret (Temporarily disable signature verification)
 *
 * Usage: wp eval-file tools/clear-webhook-secret.php
 */

delete_option('h3tm_lambda_webhook_secret');
echo "Webhook secret cleared. Signature verification disabled.\n";
echo "Webhook URL: " . admin_url('admin-ajax.php?action=h3tm_lambda_webhook') . "\n";
