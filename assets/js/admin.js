jQuery(document).ready(function($) {
    // Initialize Select2 on user select
    $('.h3tm-user-select').select2({
        placeholder: 'Select a user...',
        allowClear: true
    });
    
    // File info display
    $('#tour_file').on('change', function() {
        var file = this.files[0];
        if (file) {
            $('#file-name').text(file.name);
            $('#file-size').text(formatFileSize(file.size));
            $('#file-info').show();
        } else {
            $('#file-info').hide();
        }
    });
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Handle tour upload with chunking
    $('#h3tm-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $result = $('#upload-result');
        var $progressBar = $('#upload-progress');
        var $progressText = $('#upload-progress-text');
        
        var tourName = $('#tour_name').val();
        var file = $('#tour_file')[0].files[0];
        
        if (!file) {
            alert('Please select a file to upload');
            return;
        }
        
        // Chunk size: 1MB
        var chunkSize = 1024 * 1024;
        var chunks = Math.ceil(file.size / chunkSize);
        var currentChunk = 0;
        var uniqueId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);

        // Time tracking for remaining time calculation
        var startTime = Date.now();
        var lastProgressTime = startTime;
        
        $spinner.addClass('is-active');
        $result.hide();
        
        // Show progress bar - now uses CSS classes for gradient styling
        if ($progressBar.length === 0) {
            $form.after('<div id="upload-progress-wrapper" style="display: none;">' +
                '<div id="upload-progress">' +
                '<div id="upload-progress-bar"></div>' +
                '</div>' +
                '<div id="upload-progress-text">0%</div>' +
                '</div>');
            $progressBar = $('#upload-progress');
            $progressText = $('#upload-progress-text');
        }
        
        $('#upload-progress-wrapper').show();
        
        function uploadChunk(start, retryCount) {
            retryCount = retryCount || 0;
            var maxRetries = 3;
            var end = Math.min(start + chunkSize, file.size);
            var chunk = file.slice(start, end);
            
            var chunkData = new FormData();
            chunkData.append('action', 'h3tm_upload_chunk');
            chunkData.append('nonce', h3tm_ajax.nonce);
            chunkData.append('tour_name', tourName);
            chunkData.append('chunk', chunk);
            chunkData.append('chunk_number', currentChunk);
            chunkData.append('total_chunks', chunks);
            chunkData.append('unique_id', uniqueId);
            chunkData.append('file_name', file.name);
            
            $.ajax({
                url: h3tm_ajax.ajax_url,
                type: 'POST',
                data: chunkData,
                processData: false,
                contentType: false,
                timeout: 60000, // 60 second timeout
                success: function(response) {
                    if (response.success) {
                        currentChunk++;
                        var progress = Math.round((currentChunk / chunks) * 100);
                        var currentTime = Date.now();

                        // Update progress bar width and progressive gradient
                        $('#upload-progress-bar').css('width', progress + '%');
                        updateProgressiveGradient(progress);

                        // Calculate time remaining
                        var timeElapsed = (currentTime - startTime) / 1000; // seconds
                        var timeRemaining = '';

                        if (progress > 5 && timeElapsed > 1) { // Only show after 5% and 1 second
                            var estimatedTotal = (timeElapsed / progress) * 100;
                            var remaining = Math.max(0, estimatedTotal - timeElapsed);

                            if (remaining > 60) {
                                var minutes = Math.ceil(remaining / 60);
                                timeRemaining = ' • ~' + minutes + 'm remaining';
                            } else if (remaining > 10) {
                                timeRemaining = ' • ~' + Math.ceil(remaining) + 's remaining';
                            } else if (remaining > 0) {
                                timeRemaining = ' • finishing up...';
                            }
                        }

                        // Update progress text with percentage and time remaining
                        if (timeRemaining) {
                            $progressText.html(progress + '%<span class="time-remaining">' + timeRemaining + '</span>');
                        } else {
                            $progressText.text(progress + '%');
                        }

                        lastProgressTime = currentTime;
                        
                        if (currentChunk < chunks) {
                            // Small delay between chunks to prevent overload
                            setTimeout(function() {
                                uploadChunk(end);
                            }, 10);
                        } else {
                            // All chunks uploaded, now process the file
                            processUploadedFile();
                        }
                    } else {
                        $result.removeClass('notice-success').addClass('notice-error');
                        
                        // Check if response has debug info
                        if (response.data && typeof response.data === 'object' && response.data.debug) {
                            var debugInfo = response.data.debug;
                            var debugHtml = '<p><strong>Error at chunk ' + currentChunk + ':</strong> ' + (response.data.message || response.data) + '</p>';
                            debugHtml += '<div style="background:#f0f0f0;padding:10px;margin:10px 0;border-radius:5px;font-family:monospace;font-size:12px;">';
                            debugHtml += '<strong>Debug Information:</strong><br>';
                            debugHtml += 'Check Path: ' + debugInfo.check_path + '<br>';
                            debugHtml += 'H3panos Exists: ' + (debugInfo.h3panos_exists ? 'YES' : 'NO') + '<br>';
                            debugHtml += 'Check Path Exists: ' + (debugInfo.check_path_exists ? 'YES' : 'NO') + '<br>';
                            debugHtml += 'Free Space: ' + debugInfo.free_space_mb + ' MB<br>';
                            debugHtml += 'Required: ' + debugInfo.required_mb + ' MB<br>';
                            debugHtml += 'ABSPATH: ' + debugInfo.abspath + '<br>';
                            debugHtml += 'Upload Dir: ' + debugInfo.upload_basedir + '<br>';
                            if (debugInfo.handler) {
                                debugHtml += 'Handler: ' + debugInfo.handler + '<br>';
                            }
                            debugHtml += '</div>';
                            $result.html(debugHtml);
                        } else {
                            $result.html('<p>Error at chunk ' + currentChunk + ': ' + response.data + '</p>');
                        }
                        
                        $result.show();
                        $('#upload-progress-wrapper').hide();
                        $spinner.removeClass('is-active');
                    }
                },
                error: function(xhr, status, error) {
                    if (retryCount < maxRetries) {
                        // Retry the chunk
                        console.log('Retrying chunk ' + currentChunk + ' (attempt ' + (retryCount + 1) + ')');
                        $progressText.text('Retrying chunk ' + currentChunk + '...');
                        setTimeout(function() {
                            uploadChunk(start, retryCount + 1);
                        }, 2000); // Wait 2 seconds before retry
                    } else {
                        var errorMsg = 'Failed to upload chunk ' + currentChunk + ' after ' + maxRetries + ' attempts.';
                        if (status === 'timeout') {
                            errorMsg += ' The request timed out.';
                        } else if (error) {
                            errorMsg += ' Error: ' + error;
                        }
                        
                        $result.removeClass('notice-success').addClass('notice-error');
                        $result.html('<p>' + errorMsg + '</p><p>Please check your server logs for more details.</p>');
                        $result.show();
                        $('#upload-progress-wrapper').hide();
                        $spinner.removeClass('is-active');
                    }
                }
            });
        }
        
        function processUploadedFile() {
            $progressText.text('Processing uploaded file...');

            var processStartTime = Date.now();
            var maxWaitTime = 90000; // 90 seconds max wait for Pantheon

            $.ajax({
                url: h3tm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'h3tm_process_upload',
                    nonce: h3tm_ajax.nonce,
                    tour_name: tourName,
                    unique_id: uniqueId,
                    file_name: file.name
                },
                timeout: maxWaitTime,
                success: function(response) {
                    if (response && response.success) {
                        // Success - show message and refresh
                        $result.removeClass('notice-error').addClass('notice-success');
                        $result.html('<p>' + (response.data || response.message || 'Tour uploaded successfully!') + '</p>');
                        $form[0].reset();
                        $('#upload-progress-wrapper').hide();
                        $result.show();

                        // Show countdown and reload
                        showSuccessAndReload('Tour uploaded successfully! Refreshing page...');
                    } else {
                        // Error response
                        handleProcessError(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Process upload error:', status, error, xhr);

                    if (status === 'timeout') {
                        // On timeout, assume success since tour appears after manual refresh
                        showTimeoutSuccessMessage();
                    } else {
                        // Other errors
                        $result.removeClass('notice-success').addClass('notice-error');
                        $result.html('<p>An error occurred while processing the tour. Error: ' + status + '</p>');
                        $result.show();
                        $('#upload-progress-wrapper').hide();
                    }
                },
                complete: function() {
                    $spinner.removeClass('is-active');
                }
            });
        }

        function handleProcessError(response) {
            $result.removeClass('notice-success').addClass('notice-error');

            var errorMessage = 'Upload processing failed.';
            if (response && response.data) {
                if (typeof response.data === 'string') {
                    errorMessage = response.data;
                } else if (response.data.message) {
                    errorMessage = response.data.message;
                }
            }

            $result.html('<p>' + errorMessage + '</p>');
            $('#upload-progress-wrapper').hide();
            $result.show();
        }

        function showSuccessAndReload(message) {
            var countdown = 3;
            var countdownInterval = setInterval(function() {
                $progressText.text(message + ' (' + countdown + 's)');
                countdown--;

                if (countdown < 0) {
                    clearInterval(countdownInterval);
                    location.reload();
                }
            }, 1000);
        }

        function showTimeoutSuccessMessage() {
            $result.removeClass('notice-error').addClass('notice-warning');
            $result.html('<p><strong>Upload Status:</strong> The upload process timed out, but the tour may have been uploaded successfully. <button type="button" class="button button-secondary" onclick="location.reload();">Refresh Page to Check</button></p>');
            $('#upload-progress-wrapper').hide();
            $result.show();
        }
        
        // Start upload
        uploadChunk(0);
    });

    /**
     * Update progress bar with progressive gradient based on percentage
     */
    function updateProgressiveGradient(progress) {
        var $progressBar = $('#upload-progress-bar');

        // Don't update gradient in IE11
        if (document.documentElement.className.indexOf('ie11') !== -1) {
            return;
        }

        // Calculate gradient based on progress
        var gradient = '';

        if (progress <= 0) {
            gradient = '#c1272d'; // Pure red at start
        } else if (progress >= 100) {
            gradient = 'linear-gradient(90deg, #c1272d 0%, #d73527 15%, #e67e22 35%, #f1c40f 60%, #27ae60 85%, #2ecc71 100%)';
        } else {
            // Progressive gradient - only show colors up to current progress
            var colors = [
                {percent: 0, color: '#c1272d'},   // WordPress red
                {percent: 15, color: '#d73527'},  // Deep red
                {percent: 35, color: '#e67e22'},  // Orange
                {percent: 60, color: '#f1c40f'},  // Yellow
                {percent: 85, color: '#27ae60'},  // Green transition
                {percent: 100, color: '#2ecc71'} // Success green
            ];

            var stops = [];
            var lastColor = '#c1272d';

            for (var i = 0; i < colors.length; i++) {
                if (colors[i].percent <= progress) {
                    // This color should be included
                    var adjustedPercent = (colors[i].percent / progress) * 100;
                    stops.push(colors[i].color + ' ' + Math.round(adjustedPercent) + '%');
                    lastColor = colors[i].color;
                } else {
                    // Map the remaining progress to this color range
                    var prevColor = i > 0 ? colors[i-1] : colors[0];
                    var progressInRange = (progress - prevColor.percent) / (colors[i].percent - prevColor.percent);
                    var interpolatedColor = interpolateColor(prevColor.color, colors[i].color, progressInRange);
                    stops.push(interpolatedColor + ' 100%');
                    break;
                }
            }

            if (stops.length > 1) {
                gradient = 'linear-gradient(90deg, ' + stops.join(', ') + ')';
            } else {
                gradient = lastColor;
            }
        }

        $progressBar.css('background', gradient);
    }

    /**
     * Interpolate between two hex colors
     */
    function interpolateColor(color1, color2, factor) {
        if (factor <= 0) return color1;
        if (factor >= 1) return color2;

        var c1 = hexToRgb(color1);
        var c2 = hexToRgb(color2);

        var r = Math.round(c1.r + (c2.r - c1.r) * factor);
        var g = Math.round(c1.g + (c2.g - c1.g) * factor);
        var b = Math.round(c1.b + (c2.b - c1.b) * factor);

        return rgbToHex(r, g, b);
    }

    /**
     * Convert hex color to RGB object
     */
    function hexToRgb(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    /**
     * Convert RGB values to hex color
     */
    function rgbToHex(r, g, b) {
        return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }

    // Handle tour deletion
    $('.delete-tour').on('click', function() {
        var tourName = $(this).data('tour');
        
        if (!confirm('Are you sure you want to delete the tour "' + tourName + '"? This action cannot be undone.')) {
            return;
        }
        
        var $button = $(this);
        var $row = $button.closest('tr');
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_delete_tour',
                tour_name: tourName,
                nonce: h3tm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $row.remove();
                    });
                } else {
                    alert('Error: ' + H3TM_TourRename.extractErrorMessage(response));
                }
            },
            error: function() {
                alert('An error occurred while deleting the tour.');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Handle tour rename with professional modal dialog
    $('.rename-tour').on('click', function() {
        var oldName = $(this).data('tour');
        var $button = $(this);
        var $row = $button.closest('tr');

        // Create and show rename modal
        H3TM_TourRename.showModal(oldName, function(newName) {
            H3TM_TourRename.performRename(oldName, newName, $button, $row);
        });
    });

    // Initialize tour rename functionality
    var H3TM_TourRename = {
        modal: null,

        /**
         * Show professional rename modal dialog
         */
        showModal: function(oldName, onConfirm) {
            var modal = this.createModal(oldName);
            var $modal = $(modal);

            // Add to page and show
            $('body').append($modal);
            $modal.addClass('h3tm-modal-show');

            // Focus on input after animation
            setTimeout(function() {
                $modal.find('#h3tm-new-name').focus().select();
            }, 200);

            // Handle form submission
            $modal.find('#h3tm-rename-form').on('submit', function(e) {
                e.preventDefault();
                var newName = $modal.find('#h3tm-new-name').val().trim();

                if (H3TM_TourRename.validateInput(newName, oldName, $modal)) {
                    H3TM_TourRename.closeModal($modal);
                    onConfirm(newName);
                }
            });

            // Handle cancel/close buttons
            $modal.find('.h3tm-modal-cancel, .h3tm-modal-close').on('click', function() {
                H3TM_TourRename.closeModal($modal);
            });

            // Handle escape key
            $(document).on('keydown.h3tm-rename', function(e) {
                if (e.keyCode === 27) { // Escape key
                    H3TM_TourRename.closeModal($modal);
                }
            });
        },

        /**
         * Create modal HTML structure
         */
        createModal: function(oldName) {
            return '<div class="h3tm-modal-overlay" role="dialog" aria-labelledby="h3tm-rename-title" aria-describedby="h3tm-rename-desc">\n' +
                   '  <div class="h3tm-modal-container">\n' +
                   '    <div class="h3tm-modal-header">\n' +
                   '      <h3 id="h3tm-rename-title">Rename Tour</h3>\n' +
                   '      <button type="button" class="h3tm-modal-close" aria-label="Close dialog">&times;</button>\n' +
                   '    </div>\n' +
                   '    <div class="h3tm-modal-body">\n' +
                   '      <p id="h3tm-rename-desc">Enter a new name for the tour "<strong>' + this.escapeHtml(oldName) + '</strong>"</p>\n' +
                   '      <form id="h3tm-rename-form">\n' +
                   '        <div class="h3tm-form-field">\n' +
                   '          <label for="h3tm-new-name">New Tour Name:</label>\n' +
                   '          <input type="text" id="h3tm-new-name" name="new_name" value="' + this.escapeHtml(oldName) + '" required maxlength="255" autocomplete="off" />\n' +
                   '          <div class="h3tm-field-error" id="h3tm-name-error" role="alert" aria-live="polite"></div>\n' +
                   '          <div class="h3tm-field-hint">Only letters, numbers, spaces, hyphens, and underscores are allowed.</div>\n' +
                   '        </div>\n' +
                   '      </form>\n' +
                   '    </div>\n' +
                   '    <div class="h3tm-modal-footer">\n' +
                   '      <button type="button" class="button button-secondary h3tm-modal-cancel">Cancel</button>\n' +
                   '      <button type="submit" form="h3tm-rename-form" class="button button-primary h3tm-rename-confirm">Rename Tour</button>\n' +
                   '    </div>\n' +
                   '  </div>\n' +
                   '</div>';
        },

        /**
         * Validate user input
         */
        validateInput: function(newName, oldName, $modal) {
            var $error = $modal.find('#h3tm-name-error');
            var $input = $modal.find('#h3tm-new-name');

            // Clear previous errors
            $error.text('').hide();
            $input.removeClass('h3tm-field-error-input');

            // Validation rules
            if (!newName) {
                this.showFieldError($error, $input, 'Tour name is required.');
                return false;
            }

            if (newName === oldName) {
                this.showFieldError($error, $input, 'Please enter a different name.');
                return false;
            }

            if (newName.length < 2) {
                this.showFieldError($error, $input, 'Tour name must be at least 2 characters long.');
                return false;
            }

            if (newName.length > 255) {
                this.showFieldError($error, $input, 'Tour name is too long (maximum 255 characters).');
                return false;
            }

            // Check for invalid characters
            var invalidChars = /[^a-zA-Z0-9\s\-_]/;
            if (invalidChars.test(newName)) {
                this.showFieldError($error, $input, 'Tour name contains invalid characters.');
                return false;
            }

            // Check for reserved names
            var reservedNames = ['con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'lpt1', 'lpt2'];
            if (reservedNames.indexOf(newName.toLowerCase()) !== -1) {
                this.showFieldError($error, $input, 'This name is reserved and cannot be used.');
                return false;
            }

            return true;
        },

        /**
         * Show field error message
         */
        showFieldError: function($error, $input, message) {
            $error.text(message).show();
            $input.addClass('h3tm-field-error-input').focus();
        },

        /**
         * Perform the actual rename operation with retry logic and debug handling (like upload)
         */
        performRename: function(oldName, newName, $button, $row, retryCount) {
            retryCount = retryCount || 0;
            var maxRetries = 3; // Same as upload function

            // Show progress overlay with retry info if applicable
            var progressMessage = retryCount > 0
                ? 'Retrying rename operation (attempt ' + (retryCount + 1) + ')...'
                : 'Renaming tour...';
            this.showProgressOverlay(progressMessage);

            // Disable button
            $button.prop('disabled', true);

            $.ajax({
                url: h3tm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'h3tm_rename_tour',
                    old_name: oldName,
                    new_name: newName,
                    nonce: h3tm_ajax.nonce
                },
                timeout: 60000, // 60 second timeout per attempt (like upload chunks)
                success: function(response) {
                    if (response && response.success) {
                        // Success - update UI
                        H3TM_TourRename.updateTourRow($row, $button, oldName, newName);

                        // Show success message with debug info if available
                        var successMsg = 'Tour renamed successfully!';
                        if (response.data && response.data.debug && response.data.debug.using_optimized) {
                            successMsg += ' (Using optimized backend)';
                        }
                        H3TM_TourRename.showSuccessMessage(successMsg);

                        // Optional: reload page after delay to refresh everything
                        setTimeout(function() {
                            if (confirm('Tour renamed successfully! Would you like to refresh the page to see all updates?')) {
                                location.reload();
                            }
                        }, 2000);
                    } else {
                        // Error - handle debug info like upload function
                        H3TM_TourRename.handleRenameError(response, oldName, newName, $button, $row, retryCount, maxRetries);
                    }
                },
                error: function(xhr, status, error) {
                    if (retryCount < maxRetries && (status === 'timeout' || xhr.status === 0 || xhr.status >= 500)) {
                        // Retry on timeout, network errors, or server errors (like upload function)
                        console.log('Retrying rename operation (attempt ' + (retryCount + 1) + ')');
                        setTimeout(function() {
                            H3TM_TourRename.performRename(oldName, newName, $button, $row, retryCount + 1);
                        }, 2000); // Wait 2 seconds before retry
                    } else {
                        // Final error - give up after retries
                        var errorMessage = 'Failed to rename tour after ' + maxRetries + ' attempts.';

                        if (status === 'timeout') {
                            errorMessage += ' The request timed out.';
                        } else if (xhr.status === 0) {
                            errorMessage += ' Network error.';
                        } else if (xhr.status >= 500) {
                            errorMessage += ' Server error.';
                        } else if (xhr.responseJSON && xhr.responseJSON.data) {
                            errorMessage = xhr.responseJSON.data;
                        }

                        H3TM_TourRename.showErrorMessage(errorMessage + ' Please check your server logs for more details.');
                        H3TM_TourRename.hideProgressOverlay();
                        $button.prop('disabled', false);
                    }
                }
            });
        },

        /**
         * Handle rename errors with debug information display (like upload function)
         */
        handleRenameError: function(response, oldName, newName, $button, $row, retryCount, maxRetries) {
            // Check if response has debug info like upload function
            if (response.data && typeof response.data === 'object' && response.data.debug) {
                var debugInfo = response.data.debug;
                var errorHtml = '<p><strong>Rename Error:</strong> ' + (response.data.message || response.data) + '</p>';
                errorHtml += '<div style="background:#f0f0f0;padding:10px;margin:10px 0;border-radius:5px;font-family:monospace;font-size:12px;">';
                errorHtml += '<strong>Debug Information:</strong><br>';
                errorHtml += 'Operation: ' + debugInfo.operation + '<br>';
                errorHtml += 'Old Name: ' + debugInfo.old_name + '<br>';
                errorHtml += 'New Name: ' + debugInfo.new_name + '<br>';
                errorHtml += 'Is Pantheon: ' + (debugInfo.is_pantheon ? 'YES' : 'NO') + '<br>';
                errorHtml += 'H3panos Path: ' + debugInfo.h3panos_path + '<br>';
                errorHtml += 'H3panos Exists: ' + (debugInfo.h3panos_exists ? 'YES' : 'NO') + '<br>';
                errorHtml += 'H3panos Writeable: ' + (debugInfo.h3panos_writeable ? 'YES' : 'NO') + '<br>';
                errorHtml += 'Old Tour Exists: ' + (debugInfo.old_tour_exists ? 'YES' : 'NO') + '<br>';
                errorHtml += 'New Tour Exists: ' + (debugInfo.new_tour_exists ? 'YES' : 'NO') + '<br>';
                errorHtml += 'Using Optimized: ' + (debugInfo.using_optimized ? 'YES' : 'NO') + '<br>';
                errorHtml += 'ABSPATH: ' + debugInfo.abspath + '<br>';
                errorHtml += 'Handler: ' + debugInfo.handler + '<br>';
                errorHtml += '</div>';

                // Create a detailed error notice
                var notice = '<div class="notice notice-error is-dismissible h3tm-notice h3tm-debug-notice">' + errorHtml +
                           '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';

                // Insert detailed debug notice
                var $target = $('.wp-header-end').length ? $('.wp-header-end') : $('#wpbody-content h1').first();
                if ($target.length) {
                    $target.after(notice);
                } else {
                    $('#wpbody-content').prepend(notice);
                }

                // Handle dismiss button
                $('.h3tm-debug-notice .notice-dismiss').on('click', function() {
                    $(this).closest('.h3tm-debug-notice').fadeOut(300, function() {
                        $(this).remove();
                    });
                });
            } else {
                // Simple error message
                var errorMessage = this.extractErrorMessage(response);
                this.showErrorMessage('Rename failed: ' + errorMessage);
            }

            this.hideProgressOverlay();
            $button.prop('disabled', false);
        },

        /**
         * Extract error message from WordPress AJAX response
         */
        extractErrorMessage: function(response) {
            if (!response) {
                return 'Unknown error occurred.';
            }

            // WordPress wp_send_json_error() can send data in different formats
            if (typeof response.data === 'string') {
                return response.data;
            } else if (response.data && response.data.message) {
                return response.data.message;
            } else if (response.data && typeof response.data === 'object') {
                return JSON.stringify(response.data);
            } else if (response.message) {
                return response.message;
            }

            return 'An unknown error occurred.';
        },

        /**
         * Update tour row with new name
         */
        updateTourRow: function($row, $button, oldName, newName) {
            // Update tour name in first column
            $row.find('td:first').text(newName);

            // Update data attributes
            $row.data('tour', newName);
            $button.data('tour', newName);
            $row.find('.delete-tour').data('tour', newName);

            // Update URL if present
            var $link = $row.find('a');
            if ($link.length) {
                var currentHref = $link.attr('href');
                if (currentHref) {
                    var newHref = currentHref.replace(
                        encodeURIComponent(oldName),
                        encodeURIComponent(newName)
                    );
                    $link.attr('href', newHref).text(newHref);
                }
            }

            // Add visual feedback
            $row.addClass('h3tm-row-updated');
            setTimeout(function() {
                $row.removeClass('h3tm-row-updated');
            }, 3000);
        },

        /**
         * Show progress overlay
         */
        showProgressOverlay: function(message) {
            var overlay = '<div id="h3tm-progress-overlay" class="h3tm-progress-overlay">\n' +
                         '  <div class="h3tm-progress-container">\n' +
                         '    <div class="h3tm-spinner"></div>\n' +
                         '    <p class="h3tm-progress-message">' + this.escapeHtml(message) + '</p>\n' +
                         '    <div class="h3tm-progress-details">This may take a moment for large tours...</div>\n' +
                         '  </div>\n' +
                         '</div>';

            $('body').append(overlay);
            $('#h3tm-progress-overlay').addClass('h3tm-show');
        },

        /**
         * Hide progress overlay
         */
        hideProgressOverlay: function() {
            $('#h3tm-progress-overlay').removeClass('h3tm-show');
            setTimeout(function() {
                $('#h3tm-progress-overlay').remove();
            }, 300);
        },

        /**
         * Show success message
         */
        showSuccessMessage: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showErrorMessage: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show WordPress-style notice
         */
        showNotice: function(message, type) {
            var notice = '<div class="notice notice-' + type + ' is-dismissible h3tm-notice">\n' +
                        '  <p>' + this.escapeHtml(message) + '</p>\n' +
                        '  <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>\n' +
                        '</div>';

            // Insert after page title or at top of content
            var $target = $('.wp-header-end').length ? $('.wp-header-end') : $('#wpbody-content h1').first();
            if ($target.length) {
                $target.after(notice);
            } else {
                $('#wpbody-content').prepend(notice);
            }

            // Handle dismiss button
            $('.h3tm-notice .notice-dismiss').on('click', function() {
                $(this).closest('.h3tm-notice').fadeOut(300, function() {
                    $(this).remove();
                });
            });

            // Auto-remove success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $('.h3tm-notice.notice-success').fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },

        /**
         * Close modal dialog
         */
        closeModal: function($modal) {
            $modal.removeClass('h3tm-modal-show');
            $(document).off('keydown.h3tm-rename');

            setTimeout(function() {
                $modal.remove();
            }, 300);
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
    
    // Handle test email
    $('#h3tm-test-email-form').on('submit', function(e) {
        e.preventDefault();
        
        var userId = $('#test_user_id').val();
        
        if (!userId) {
            alert('Please select a user');
            return;
        }
        
        var $form = $(this);
        var $spinner = $form.find('.spinner');
        var $result = $('#test-email-result');
        
        $spinner.addClass('is-active');
        $result.hide();
        
        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_test_email',
                user_id: userId,
                nonce: h3tm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('notice-error').addClass('notice-success');
                    $result.html('<p>' + response.data + '</p>');
                } else {
                    $result.removeClass('notice-success').addClass('notice-error');
                    $result.html('<p>' + response.data + '</p>');
                }
                $result.show();
            },
            error: function() {
                $result.removeClass('notice-success').addClass('notice-error');
                $result.html('<p>An error occurred while sending the email.</p>');
                $result.show();
            },
            complete: function() {
                $spinner.removeClass('is-active');
            }
        });
    });
});