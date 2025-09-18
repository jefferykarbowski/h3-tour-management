/**
 * Optimized Admin JavaScript for H3 Tour Management
 *
 * Enhanced with progress tracking and timeout handling
 *
 * @package H3_Tour_Management
 * @since 2.1.0
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * Progress tracking manager
     */
    const ProgressTracker = {
        activeOperations: new Map(),

        /**
         * Start tracking an operation
         */
        startTracking: function(operationId, callback) {
            if (this.activeOperations.has(operationId)) {
                return; // Already tracking
            }

            const tracker = {
                id: operationId,
                callback: callback,
                startTime: Date.now(),
                checkCount: 0,
                interval: null
            };

            this.activeOperations.set(operationId, tracker);
            this.startPolling(tracker);
        },

        /**
         * Start polling for progress updates
         */
        startPolling: function(tracker) {
            tracker.interval = setInterval(() => {
                tracker.checkCount++;

                // Stop tracking after maximum checks
                if (tracker.checkCount > h3tm_optimized.max_progress_checks) {
                    this.stopTracking(tracker.id, 'timeout');
                    return;
                }

                this.checkProgress(tracker);
            }, h3tm_optimized.progress_interval);
        },

        /**
         * Check operation progress
         */
        checkProgress: function(tracker) {
            $.ajax({
                url: h3tm_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'h3tm_get_operation_progress',
                    operation_id: tracker.id,
                    nonce: h3tm_admin.nonce
                },
                success: (response) => {
                    if (response.success && response.data.progress) {
                        const progressData = response.data.progress;

                        // Update callback with progress
                        tracker.callback('progress', progressData);

                        // Check if operation is completed
                        if (progressData.status === 'completed') {
                            this.stopTracking(tracker.id, 'completed', progressData);
                        } else if (progressData.status === 'failed') {
                            this.stopTracking(tracker.id, 'failed', progressData);
                        }
                    } else {
                        // Operation not found or error
                        this.stopTracking(tracker.id, 'not_found');
                    }
                },
                error: () => {
                    // Network error - continue trying for a few more attempts
                    if (tracker.checkCount < 5) {
                        return; // Keep trying
                    }
                    this.stopTracking(tracker.id, 'error');
                }
            });
        },

        /**
         * Stop tracking an operation
         */
        stopTracking: function(operationId, reason, data = null) {
            const tracker = this.activeOperations.get(operationId);
            if (!tracker) return;

            if (tracker.interval) {
                clearInterval(tracker.interval);
            }

            tracker.callback('stopped', { reason: reason, data: data });
            this.activeOperations.delete(operationId);
        },

        /**
         * Stop all tracking
         */
        stopAll: function() {
            for (const [operationId] of this.activeOperations) {
                this.stopTracking(operationId, 'manual');
            }
        }
    };

    /**
     * Enhanced progress modal
     */
    const ProgressModal = {
        /**
         * Show progress modal
         */
        show: function(title, message) {
            if ($('#h3tm-progress-modal').length === 0) {
                this.create();
            }

            $('#h3tm-progress-modal .modal-title').text(title);
            $('#h3tm-progress-modal .progress-message').text(message);
            $('#h3tm-progress-modal .progress-bar').css('width', '0%');
            $('#h3tm-progress-modal .progress-percentage').text('0%');
            $('#h3tm-progress-modal').show();
        },

        /**
         * Update progress
         */
        update: function(progress, message) {
            $('#h3tm-progress-modal .progress-bar').css('width', progress + '%');
            $('#h3tm-progress-modal .progress-percentage').text(progress + '%');
            if (message) {
                $('#h3tm-progress-modal .progress-message').text(message);
            }
        },

        /**
         * Hide progress modal
         */
        hide: function() {
            $('#h3tm-progress-modal').fadeOut();
        },

        /**
         * Show error in modal
         */
        showError: function(message) {
            $('#h3tm-progress-modal .progress-container').hide();
            $('#h3tm-progress-modal .error-message')
                .text(message)
                .show();

            $('#h3tm-progress-modal .close-button').show();
        },

        /**
         * Create modal HTML
         */
        create: function() {
            const modalHtml = `
                <div id="h3tm-progress-modal" class="h3tm-modal" style="display: none;">
                    <div class="h3tm-modal-content">
                        <div class="h3tm-modal-header">
                            <h3 class="modal-title">Processing...</h3>
                        </div>
                        <div class="h3tm-modal-body">
                            <div class="progress-container">
                                <div class="progress-message">Starting operation...</div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar"></div>
                                </div>
                                <div class="progress-percentage">0%</div>
                            </div>
                            <div class="error-message" style="display: none;"></div>
                        </div>
                        <div class="h3tm-modal-footer">
                            <button type="button" class="button close-button" style="display: none;">Close</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            // Handle close button
            $(document).on('click', '#h3tm-progress-modal .close-button', function() {
                ProgressModal.hide();
            });
        }
    };

    /**
     * Enhanced tour rename handler with progress tracking
     */
    function enhancedRename($button, oldName, newName) {
        // Disable button and show loading state
        $button.prop('disabled', true).addClass('loading');

        // Show progress modal
        ProgressModal.show(
            'Renaming Tour',
            h3tm_optimized.strings.operation_starting
        );

        // Start the rename operation
        $.ajax({
            url: h3tm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_rename_tour_optimized',
                old_name: oldName,
                new_name: newName,
                nonce: h3tm_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data || {};

                    // Check if operation has an ID for tracking
                    if (data.operation_id) {
                        // Start progress tracking
                        ProgressTracker.startTracking(data.operation_id, function(event, eventData) {
                            handleProgressEvent(event, eventData, $button, oldName, newName);
                        });
                    } else {
                        // Operation completed immediately
                        handleImmediateSuccess(response, $button, oldName, newName);
                    }
                } else {
                    handleError(response, $button);
                }
            },
            error: function(xhr) {
                let errorMessage = h3tm_admin.strings.error;

                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                } catch (e) {
                    // Use default error message
                }

                handleError({ data: { message: errorMessage } }, $button);
            }
        });
    }

    /**
     * Handle progress tracking events
     */
    function handleProgressEvent(event, eventData, $button, oldName, newName) {
        switch (event) {
            case 'progress':
                const progress = eventData.progress || 0;
                const message = eventData.message || h3tm_optimized.strings.operation_progress.replace('{progress}', progress);

                ProgressModal.update(progress, message);
                break;

            case 'stopped':
                const reason = eventData.reason;
                const data = eventData.data;

                if (reason === 'completed' && data) {
                    handleSuccess(data, $button, oldName, newName);
                } else if (reason === 'failed' && data) {
                    handleError({ data: { message: data.message || 'Operation failed' } }, $button);
                } else if (reason === 'timeout') {
                    handleTimeout($button);
                } else {
                    handleError({ data: { message: 'Operation status unknown' } }, $button);
                }
                break;
        }
    }

    /**
     * Handle immediate success (no progress tracking needed)
     */
    function handleImmediateSuccess(response, $button, oldName, newName) {
        updateUIAfterRename($button, oldName, newName);
        ProgressModal.hide();

        // Show success notice
        showNotice('success', response.data.message || response.message);
    }

    /**
     * Handle tracked operation success
     */
    function handleSuccess(progressData, $button, oldName, newName) {
        updateUIAfterRename($button, oldName, newName);
        ProgressModal.hide();

        // Show success notice
        showNotice('success', progressData.message || h3tm_optimized.strings.operation_completed);
    }

    /**
     * Handle operation errors
     */
    function handleError(response, $button) {
        $button.prop('disabled', false).removeClass('loading');

        const errorMessage = (response.data && response.data.message) ||
                             h3tm_admin.strings.error;

        ProgressModal.showError(errorMessage);
        showNotice('error', errorMessage);
    }

    /**
     * Handle operation timeout
     */
    function handleTimeout($button) {
        $button.prop('disabled', false).removeClass('loading');

        ProgressModal.showError(h3tm_optimized.strings.operation_timeout);
        showNotice('warning', h3tm_optimized.strings.operation_timeout);
    }

    /**
     * Update UI after successful rename
     */
    function updateUIAfterRename($button, oldName, newName) {
        const $row = $button.closest('tr');

        // Update table row
        $row.find('td:first').text(newName);
        $row.data('tour', newName);
        $button.data('tour', newName);
        $row.find('.delete-tour').data('tour', newName);

        // Update URL
        const newUrl = $row.find('a').attr('href').replace(encodeURIComponent(oldName), encodeURIComponent(newName));
        $row.find('a').attr('href', newUrl);

        // Re-enable button
        $button.prop('disabled', false).removeClass('loading');
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        const noticeHtml = `
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `;

        $('.wrap h1').after(noticeHtml);

        // Auto-dismiss after 5 seconds for success notices
        if (type === 'success') {
            setTimeout(() => {
                $('.notice-success').fadeOut();
            }, 5000);
        }
    }

    /**
     * Estimate if operation will be large
     */
    function estimateOperationSize($button) {
        const $row = $button.closest('tr');
        const sizeText = $row.find('.tour-size').text();

        // Simple heuristic: if size > 100MB or contains "GB", warn user
        if (sizeText.includes('GB') ||
            (sizeText.includes('MB') && parseInt(sizeText) > 100)) {
            return 'large';
        }

        return 'normal';
    }

    /**
     * Override the existing rename handler
     */
    $(document).off('click', '.rename-tour');
    $(document).on('click', '.rename-tour', function() {
        const $button = $(this);
        const oldName = $button.data('tour');
        const newName = prompt('Enter new name for tour "' + oldName + '":', oldName);

        if (!newName || newName === oldName) {
            return;
        }

        // Check if this might be a large operation
        const operationSize = estimateOperationSize($button);
        if (operationSize === 'large') {
            const confirmed = confirm(
                h3tm_optimized.strings.large_tour_warning + '\n\n' +
                'Do you want to continue?'
            );

            if (!confirmed) {
                return;
            }
        }

        // Use enhanced rename function
        enhancedRename($button, oldName, newName);
    });

    /**
     * Clean up on page unload
     */
    $(window).on('beforeunload', function() {
        ProgressTracker.stopAll();
    });

    /**
     * Add modal styles
     */
    const modalStyles = `
        <style>
        .h3tm-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .h3tm-modal-content {
            background: white;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            max-height: 90%;
            overflow: auto;
        }

        .h3tm-modal-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid #ddd;
        }

        .h3tm-modal-header .modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .h3tm-modal-body {
            padding: 20px 24px;
        }

        .progress-container {
            text-align: center;
        }

        .progress-message {
            margin-bottom: 16px;
            color: #666;
            font-size: 14px;
        }

        .progress-bar-container {
            background-color: #f0f0f0;
            border-radius: 12px;
            height: 24px;
            overflow: hidden;
            margin-bottom: 12px;
            position: relative;
        }

        .progress-bar {
            background: linear-gradient(90deg, #0073aa 0%, #00a0d2 100%);
            height: 100%;
            border-radius: 12px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .progress-percentage {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        .error-message {
            color: #d63638;
            background-color: #fcf0f1;
            border: 1px solid #d63638;
            border-radius: 4px;
            padding: 12px;
            text-align: center;
        }

        .h3tm-modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #ddd;
            text-align: right;
        }

        .h3tm-modal-footer .close-button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .h3tm-modal-footer .close-button:hover {
            background: #005a87;
        }

        .rename-tour.loading {
            opacity: 0.6;
            position: relative;
        }

        .rename-tour.loading::after {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: button-loading-spinner 1s ease infinite;
        }

        @keyframes button-loading-spinner {
            from {
                transform: rotate(0turn);
            }
            to {
                transform: rotate(1turn);
            }
        }
        </style>
    `;

    $('head').append(modalStyles);
});