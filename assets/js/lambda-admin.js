/**
 * H3 Tour Management Lambda Admin JavaScript
 *
 * Handles Lambda-specific admin functionality and UI interactions
 *
 * @package H3_Tour_Management
 * @since 2.2.0
 */

(function($) {
    'use strict';

    /**
     * Lambda Admin Handler
     */
    class H3TM_Lambda_Admin {
        constructor() {
            this.init();
        }

        /**
         * Initialize Lambda admin functionality
         */
        init() {
            this.bindEvents();
            this.initProcessingMethodToggle();
            this.initDeploymentStatus();
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Test Lambda webhook
            $(document).on('click', '#test-lambda-webhook', this.testWebhook.bind(this));

            // Regenerate webhook secret
            $(document).on('click', '#regenerate-webhook-secret', this.regenerateSecret.bind(this));

            // View Lambda statistics
            $(document).on('click', '#view-lambda-stats', this.viewStats.bind(this));

            // Copy deployment configuration
            $(document).on('click', '.copy-terraform-config', this.copyTerraformConfig.bind(this));

            // Processing method selection
            $(document).on('change', 'input[name="processing_method"]', this.onProcessingMethodChange.bind(this));

            // Lambda deployment guide toggle
            $(document).on('click', '.lambda-deployment-toggle', this.toggleDeploymentGuide.bind(this));
        }

        /**
         * Initialize processing method toggle functionality
         */
        initProcessingMethodToggle() {
            const $methodInputs = $('input[name="processing_method"]');

            if ($methodInputs.length > 0) {
                // Show/hide additional options based on method
                this.updateProcessingMethodUI($methodInputs.filter(':checked').val());
            }
        }

        /**
         * Initialize deployment status display
         */
        initDeploymentStatus() {
            if ($('.lambda-deployment-status').length > 0) {
                this.checkDeploymentStatus();
            }
        }

        /**
         * Test Lambda webhook endpoint
         */
        testWebhook(event) {
            event.preventDefault();

            const $button = $(event.target);
            const $result = $('#lambda-test-result');

            $button.prop('disabled', true).text('Testing...');
            $result.hide();

            $.post(h3tm_lambda.ajax_url, {
                action: 'h3tm_test_lambda_webhook',
                nonce: h3tm_lambda.nonce
            })
            .done((response) => {
                $result.removeClass('success error warning')
                       .addClass(response.success ? 'success' : 'error')
                       .text(response.data)
                       .show();
            })
            .fail(() => {
                $result.removeClass('success error warning')
                       .addClass('error')
                       .text('Request failed - please check your connection')
                       .show();
            })
            .always(() => {
                $button.prop('disabled', false).text('Test Webhook');
            });
        }

        /**
         * Regenerate webhook secret
         */
        regenerateSecret(event) {
            event.preventDefault();

            if (!confirm('Are you sure? This will invalidate the current secret and require Lambda reconfiguration.')) {
                return;
            }

            const $button = $(event.target);
            $button.prop('disabled', true).text('Regenerating...');

            $.post(h3tm_lambda.ajax_url, {
                action: 'h3tm_regenerate_webhook_secret',
                nonce: h3tm_lambda.nonce
            })
            .done((response) => {
                if (response.success) {
                    // Show success message and new configuration
                    this.showNotification('Webhook secret regenerated successfully!', 'success');

                    // Update the displayed secret preview
                    if (response.data.secret_preview) {
                        $('.webhook-secret-preview').text(response.data.secret_preview);
                    }

                    // Show deployment update notice
                    this.showDeploymentUpdateNotice();
                } else {
                    this.showNotification('Failed to regenerate secret: ' + response.data, 'error');
                }
            })
            .fail(() => {
                this.showNotification('Request failed - please check your connection', 'error');
            })
            .always(() => {
                $button.prop('disabled', false).text('Regenerate Secret');
            });
        }

        /**
         * View Lambda processing statistics
         */
        viewStats(event) {
            event.preventDefault();

            const $button = $(event.target);
            const $result = $('#lambda-stats-result');

            $button.prop('disabled', true).text('Loading...');
            $result.hide();

            $.post(h3tm_lambda.ajax_url, {
                action: 'h3tm_get_lambda_stats',
                nonce: h3tm_lambda.nonce
            })
            .done((response) => {
                if (response.success) {
                    const stats = response.data;
                    const html = this.buildStatsHTML(stats);

                    $result.removeClass('success error warning')
                           .addClass('success')
                           .html(html)
                           .show();
                } else {
                    $result.removeClass('success error warning')
                           .addClass('error')
                           .text(response.data)
                           .show();
                }
            })
            .fail(() => {
                $result.removeClass('success error warning')
                       .addClass('error')
                       .text('Failed to load statistics')
                       .show();
            })
            .always(() => {
                $button.prop('disabled', false).text('View Processing Stats');
            });
        }

        /**
         * Build HTML for Lambda statistics display
         */
        buildStatsHTML(stats) {
            let html = '<div class="lambda-stats-container">';
            html += '<h4>Lambda Processing Statistics (Last 30 Days)</h4>';

            // Main statistics
            html += '<div class="stats-grid">';
            html += `<div class="stat-item"><div class="stat-number">${stats.total_webhooks}</div><div class="stat-label">Total Processed</div></div>`;
            html += `<div class="stat-item success"><div class="stat-number">${stats.successful}</div><div class="stat-label">Successful</div></div>`;
            html += `<div class="stat-item ${stats.failed > 0 ? 'failed' : 'success'}"><div class="stat-number">${stats.failed}</div><div class="stat-label">Failed</div></div>`;
            html += `<div class="stat-item"><div class="stat-number">${stats.success_rate}%</div><div class="stat-label">Success Rate</div></div>`;
            html += '</div>';

            // Performance metrics
            if (stats.avg_processing_time > 0) {
                html += '<div class="performance-metrics">';
                html += '<h5>Performance Metrics</h5>';
                html += `<p><strong>Average Processing Time:</strong> ${stats.avg_processing_time}s</p>`;

                if (stats.avg_processing_time < 60) {
                    html += '<p class="performance-indicator good">🟢 Excellent performance</p>';
                } else if (stats.avg_processing_time < 300) {
                    html += '<p class="performance-indicator ok">🟡 Good performance</p>';
                } else {
                    html += '<p class="performance-indicator slow">🔴 Consider optimization</p>';
                }
                html += '</div>';
            }

            // Recommendations
            if (stats.total_webhooks > 0) {
                html += '<div class="recommendations">';
                html += '<h5>Recommendations</h5>';

                if (stats.success_rate >= 95) {
                    html += '<p>✅ Excellent reliability - system is performing optimally</p>';
                } else if (stats.success_rate >= 85) {
                    html += '<p>⚠️ Good reliability - monitor for recurring errors</p>';
                } else {
                    html += '<p>🔴 Low reliability - check CloudWatch logs for error patterns</p>';
                }

                if (stats.failed > 5) {
                    html += '<p>📊 Consider reviewing failed processing patterns in CloudWatch logs</p>';
                }

                html += '</div>';
            }

            html += '</div>';

            return html;
        }

        /**
         * Copy Terraform configuration to clipboard
         */
        copyTerraformConfig(event) {
            event.preventDefault();

            const $button = $(event.target);
            const configText = $button.siblings('.terraform-config').text();

            if (navigator.clipboard) {
                navigator.clipboard.writeText(configText).then(() => {
                    $button.text('Copied!');
                    setTimeout(() => $button.text('Copy Configuration'), 2000);
                });
            } else {
                // Fallback for older browsers
                const $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(configText).select();
                document.execCommand('copy');
                $temp.remove();

                $button.text('Copied!');
                setTimeout(() => $button.text('Copy Configuration'), 2000);
            }
        }

        /**
         * Handle processing method change
         */
        onProcessingMethodChange(event) {
            const selectedMethod = $(event.target).val();
            this.updateProcessingMethodUI(selectedMethod);
        }

        /**
         * Update UI based on selected processing method
         */
        updateProcessingMethodUI(method) {
            const $lambdaOptions = $('.lambda-specific-options');
            const $wordpressOptions = $('.wordpress-specific-options');

            if (method === 'lambda') {
                $lambdaOptions.show();
                $wordpressOptions.hide();
                this.showLambdaBenefits();
            } else {
                $lambdaOptions.hide();
                $wordpressOptions.show();
                this.hideLambdaBenefits();
            }
        }

        /**
         * Show Lambda benefits information
         */
        showLambdaBenefits() {
            if ($('.lambda-benefits').length === 0) {
                const benefits = `
                    <div class="lambda-benefits notice notice-info inline">
                        <h4>Lambda Processing Benefits:</h4>
                        <ul>
                            <li><strong>No Memory Limits:</strong> Process files up to 5GB</li>
                            <li><strong>Faster Processing:</strong> Dedicated compute resources</li>
                            <li><strong>Automatic:</strong> Processing starts immediately on upload</li>
                            <li><strong>Reliable:</strong> No server resource conflicts</li>
                        </ul>
                    </div>
                `;

                $('.lambda-processing-option').append(benefits);
            }
        }

        /**
         * Hide Lambda benefits information
         */
        hideLambdaBenefits() {
            $('.lambda-benefits').remove();
        }

        /**
         * Toggle deployment guide visibility
         */
        toggleDeploymentGuide(event) {
            event.preventDefault();

            const $toggle = $(event.target);
            const $guide = $('.lambda-deployment-guide');

            if ($guide.is(':visible')) {
                $guide.slideUp();
                $toggle.text('Show Deployment Guide');
            } else {
                $guide.slideDown();
                $toggle.text('Hide Deployment Guide');
            }
        }

        /**
         * Check deployment status
         */
        checkDeploymentStatus() {
            // This would check if Lambda is actually deployed and working
            // For now, we check webhook configuration
            const $status = $('.lambda-deployment-status');

            if (h3tm_lambda.webhook_url) {
                $status.addClass('configured').text('✅ Webhook configured');
            } else {
                $status.addClass('not-configured').text('❌ Webhook not configured');
            }
        }

        /**
         * Show notification message
         */
        showNotification(message, type = 'info') {
            const $notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);

            $('.wrap > h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut();
            }, 5000);

            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut();
            });
        }

        /**
         * Show deployment update notice
         */
        showDeploymentUpdateNotice() {
            const notice = `
                <div class="notice notice-warning">
                    <p><strong>Important:</strong> You need to update your Lambda deployment with the new webhook secret.</p>
                    <p>Run the following commands:</p>
                    <code>
                        cd terraform<br>
                        terraform apply
                    </code>
                </div>
            `;

            $('.lambda-webhook-secret').after(notice);
        }
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if (typeof h3tm_lambda !== 'undefined') {
            new H3TM_Lambda_Admin();
        }
    });

})(jQuery);