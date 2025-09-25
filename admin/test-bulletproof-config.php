<?php
/**
 * Bulletproof Configuration Test Interface
 *
 * Admin interface for testing and debugging the bulletproof configuration system.
 *
 * @package H3_Tour_Management
 * @since 1.5.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if user has proper permissions
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this page.');
}

?>
<div class="wrap">
    <h1>H3TM Bulletproof Configuration Test Interface</h1>

    <div class="notice notice-info">
        <p><strong>Info:</strong> This interface allows you to test the bulletproof configuration system across different WordPress contexts.</p>
    </div>

    <div id="test-results" style="margin-top: 20px;"></div>

    <div class="card">
        <h2>Configuration Tests</h2>
        <p>Test the bulletproof configuration system functionality:</p>

        <div class="test-buttons" style="margin: 20px 0;">
            <button type="button" id="test-basic-config" class="button button-primary">
                Test Basic Configuration
            </button>
            <button type="button" id="test-ajax-context" class="button button-secondary">
                Test AJAX Context
            </button>
            <button type="button" id="validate-config" class="button button-secondary">
                Validate Configuration
            </button>
            <button type="button" id="debug-config" class="button button-secondary">
                Debug Configuration
            </button>
            <button type="button" id="clear-cache" class="button">
                Clear Cache
            </button>
        </div>

        <div class="test-options" style="margin: 20px 0; border: 1px solid #ccc; padding: 15px; background: #f9f9f9;">
            <h3>Validation Options</h3>
            <label>
                <input type="radio" name="validation_type" value="quick" checked> Quick Validation (3 essential tests)
            </label><br>
            <label>
                <input type="radio" name="validation_type" value="comprehensive"> Comprehensive Validation (10 detailed tests)
            </label>
        </div>

        <button type="button" id="export-report" class="button">
            Export Full Report
        </button>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h2>Current Configuration Status</h2>
        <div id="config-status">
            <p>Click "Test Basic Configuration" to see current status...</p>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h2>AJAX Context Test</h2>
        <div id="ajax-test-results">
            <p>Click "Test AJAX Context" to verify configuration works in AJAX requests...</p>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h2>Debug Information</h2>
        <div id="debug-info">
            <p>Click "Debug Configuration" to see detailed debug information...</p>
        </div>
    </div>
</div>

<style>
    .test-result {
        margin: 10px 0;
        padding: 15px;
        border-radius: 4px;
        border-left: 4px solid #ddd;
    }

    .test-result.success {
        background: #d4edda;
        border-left-color: #28a745;
        color: #155724;
    }

    .test-result.error {
        background: #f8d7da;
        border-left-color: #dc3545;
        color: #721c24;
    }

    .test-result.info {
        background: #d1ecf1;
        border-left-color: #17a2b8;
        color: #0c5460;
    }

    .test-details {
        font-family: monospace;
        font-size: 12px;
        white-space: pre-wrap;
        background: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        margin-top: 10px;
        max-height: 400px;
        overflow-y: auto;
    }

    .loading {
        opacity: 0.6;
        pointer-events: none;
    }

    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .test-buttons button {
        margin-right: 10px;
        margin-bottom: 10px;
    }

    .test-options label {
        display: block;
        margin: 5px 0;
    }
</style>

<script>
jQuery(document).ready(function($) {
    const ajaxUrl = ajaxurl;
    const nonce = '<?php echo wp_create_nonce('h3tm_ajax_nonce'); ?>';

    // Utility function to display results
    function displayResult(containerId, result, type = 'info') {
        const container = $('#' + containerId);
        const resultHtml = `
            <div class="test-result ${type}">
                <strong>${type.toUpperCase()}:</strong> ${result.message || result}
                ${result.details ? '<div class="test-details">' + JSON.stringify(result.details, null, 2) + '</div>' : ''}
            </div>
        `;
        container.html(resultHtml);
    }

    // Test basic configuration
    $('#test-basic-config').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('Testing...');

        $.post(ajaxUrl, {
            action: 'h3tm_test_bulletproof_config',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                displayResult('config-status', response.data, 'success');
                $('#test-results').prepend(`
                    <div class="test-result success">
                        <strong>Basic Configuration Test: PASSED</strong>
                        <div class="test-details">
                            Configuration loaded: ${response.data.configuration_loaded ? 'YES' : 'NO'}
                            S3 Configured: ${response.data.is_configured ? 'YES' : 'NO'}
                            Bucket: ${response.data.bucket_name || 'NOT SET'}
                            Region: ${response.data.region || 'NOT SET'}
                            Source: ${response.data.source || 'unknown'}
                            Context: ${response.data.context || 'unknown'}
                        </div>
                    </div>
                `);
            } else {
                displayResult('config-status', response.data || 'Test failed', 'error');
                $('#test-results').prepend(`
                    <div class="test-result error">
                        <strong>Basic Configuration Test: FAILED</strong>
                        <div class="test-details">${response.data || 'Unknown error'}</div>
                    </div>
                `);
            }
        })
        .fail(function(xhr, status, error) {
            displayResult('config-status', 'AJAX request failed: ' + error, 'error');
        })
        .always(function() {
            $button.prop('disabled', false).text('Test Basic Configuration');
        });
    });

    // Test AJAX context
    $('#test-ajax-context').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('Testing...');

        $.post(ajaxUrl, {
            action: 'h3tm_test_ajax_context_config',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                displayResult('ajax-test-results', response.data, 'success');
                $('#test-results').prepend(`
                    <div class="test-result success">
                        <strong>AJAX Context Test: PASSED</strong>
                        <div class="test-details">
                            AJAX Context: ${response.data.ajax_context ? 'YES' : 'NO'}
                            Configuration Loaded: ${response.data.configuration_loaded ? 'YES' : 'NO'}
                            S3 Configured: ${response.data.s3_configured ? 'YES' : 'NO'}
                            Bucket: ${response.data.bucket_name || 'NOT_SET'}
                        </div>
                    </div>
                `);
            } else {
                displayResult('ajax-test-results', response.data || 'AJAX test failed', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            displayResult('ajax-test-results', 'AJAX context test failed: ' + error, 'error');
        })
        .always(function() {
            $button.prop('disabled', false).text('Test AJAX Context');
        });
    });

    // Validate configuration
    $('#validate-config').on('click', function() {
        const $button = $(this);
        const validationType = $('input[name="validation_type"]:checked').val();

        $button.prop('disabled', true).text('Validating...');

        $.post(ajaxUrl, {
            action: 'h3tm_validate_bulletproof_config',
            validation_type: validationType,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                const report = response.data;
                const summary = report.summary;
                const type = summary.overall_status === 'PASS' ? 'success' : 'error';

                $('#test-results').prepend(`
                    <div class="test-result ${type}">
                        <strong>Configuration Validation: ${summary.overall_status}</strong>
                        <div class="test-details">
                            Tests Run: ${summary.total_tests}
                            Passed: ${summary.passed}
                            Failed: ${summary.failed}
                            Success Rate: ${summary.success_rate}%

                            Test Results:
                            ${report.test_results.map(test =>
                                `${test.test}: ${test.status} - ${test.message}`
                            ).join('\n')}
                        </div>
                    </div>
                `);
            } else {
                $('#test-results').prepend(`
                    <div class="test-result error">
                        <strong>Validation Failed</strong>
                        <div class="test-details">${response.data || 'Unknown error'}</div>
                    </div>
                `);
            }
        })
        .fail(function(xhr, status, error) {
            $('#test-results').prepend(`
                <div class="test-result error">
                    <strong>Validation Request Failed</strong>
                    <div class="test-details">${error}</div>
                </div>
            `);
        })
        .always(function() {
            $button.prop('disabled', false).text('Validate Configuration');
        });
    });

    // Debug configuration
    $('#debug-config').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('Debugging...');

        $.post(ajaxUrl, {
            action: 'h3tm_debug_bulletproof_config',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                displayResult('debug-info', response.data, 'info');
            } else {
                displayResult('debug-info', response.data || 'Debug failed', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            displayResult('debug-info', 'Debug request failed: ' + error, 'error');
        })
        .always(function() {
            $button.prop('disabled', false).text('Debug Configuration');
        });
    });

    // Clear cache
    $('#clear-cache').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('Clearing...');

        $.post(ajaxUrl, {
            action: 'h3tm_clear_config_cache',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                $('#test-results').prepend(`
                    <div class="test-result success">
                        <strong>Cache Cleared Successfully</strong>
                        <div class="test-details">
                            Cache cleared: ${response.data.cache_cleared ? 'YES' : 'NO'}
                            Config reloaded: ${response.data.config_reloaded ? 'YES' : 'NO'}
                            New loaded time: ${response.data.loaded_at || 'unknown'}
                        </div>
                    </div>
                `);
            } else {
                $('#test-results').prepend(`
                    <div class="test-result error">
                        <strong>Cache Clear Failed</strong>
                        <div class="test-details">${response.data || 'Unknown error'}</div>
                    </div>
                `);
            }
        })
        .fail(function(xhr, status, error) {
            $('#test-results').prepend(`
                <div class="test-result error">
                    <strong>Cache Clear Request Failed</strong>
                    <div class="test-details">${error}</div>
                </div>
            `);
        })
        .always(function() {
            $button.prop('disabled', false).text('Clear Cache');
        });
    });

    // Export report
    $('#export-report').on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).text('Exporting...');

        $.post(ajaxUrl, {
            action: 'h3tm_export_config_report',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                $('#test-results').prepend(`
                    <div class="test-result success">
                        <strong>Report Exported Successfully</strong>
                        <div class="test-details">
                            Filename: ${response.data.filename}
                            Location: ${response.data.filepath}
                            Tests: ${response.data.report_summary.total_tests}
                            Status: ${response.data.report_summary.overall_status}
                        </div>
                    </div>
                `);
            } else {
                $('#test-results').prepend(`
                    <div class="test-result error">
                        <strong>Report Export Failed</strong>
                        <div class="test-details">${response.data || 'Unknown error'}</div>
                    </div>
                `);
            }
        })
        .fail(function(xhr, status, error) {
            $('#test-results').prepend(`
                <div class="test-result error">
                    <strong>Export Request Failed</strong>
                    <div class="test-details">${error}</div>
                </div>
            `);
        })
        .always(function() {
            $button.prop('disabled', false).text('Export Full Report');
        });
    });

    // Auto-run basic test on page load
    $('#test-basic-config').trigger('click');
});
</script>