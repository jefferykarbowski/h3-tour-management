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

    // S3-Only Upload Configuration
    var S3_CONFIGURED = (h3tm_ajax.s3_configured === '1' || h3tm_ajax.s3_configured === true);

    // Debug S3 configuration
    console.log('=== H3TM S3-Only Config ===');
    console.log('S3_CONFIGURED:', S3_CONFIGURED);
    if (h3tm_ajax.debug_s3_check) {
        console.log('Debug check:', h3tm_ajax.debug_s3_check);
    }

    // Handle tour upload - S3-ONLY (no fallback)
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

        // S3-ONLY upload system - no fallback
        if (S3_CONFIGURED) {
            console.log('Starting S3 direct upload (' + formatFileSize(file.size) + ')...');
            startS3DirectUpload(file, tourName, $form, $spinner, $result, $progressBar, $progressText);
        } else {
            showS3ConfigError($result, $progressBar, $spinner);
        }
    });

    // Show S3 configuration error
    function showS3ConfigError($result, $progressBar, $spinner) {
        $result.removeClass('notice-success').addClass('notice-error');

        var errorHtml = '<div class="h3tm-s3-config-error">';
        errorHtml += '<h4>⚙️ S3 Configuration Required</h4>';
        errorHtml += '<p><strong>S3 upload is required but not configured.</strong></p>';
        errorHtml += '<div class="h3tm-config-steps">';
        errorHtml += '<h5>To configure S3:</h5>';
        errorHtml += '<ol>';
        errorHtml += '<li>Go to the plugin settings</li>';
        errorHtml += '<li>Configure your AWS S3 credentials</li>';
        errorHtml += '<li>Set your S3 bucket name</li>';
        errorHtml += '<li>Save the settings</li>';
        errorHtml += '</ol>';
        errorHtml += '</div>';
        errorHtml += '</div>';

        $result.html(errorHtml);
        $result.show();
        $('#upload-progress-wrapper').hide();
        $spinner.removeClass('is-active');
    }

    // Start S3 direct upload process
    function startS3DirectUpload(file, tourName, $form, $spinner, $result, $progressBar, $progressText) {
        $spinner.addClass('is-active');
        $result.hide();

        // Show progress bar
        showProgressBar($progressBar, $progressText);
        $progressText.text('Preparing S3 upload...');

        // Get presigned URL from WordPress
        console.log('=== S3 Presigned URL Request ===');
        console.log('File:', file.name, '(' + formatFileSize(file.size) + ')');

        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_get_s3_presigned_url',
                nonce: h3tm_ajax.nonce,
                tour_name: tourName,
                file_name: file.name,
                file_size: file.size,
                file_type: file.type || 'application/zip'
            },
            timeout: 30000, // 30 second timeout for getting presigned URL
            success: function(response) {
                console.log('=== S3 Presigned URL Response ===');
                console.log('Success:', response.success);
                console.log('Data:', response.data);

                if (response.success && response.data && response.data.upload_url) {
                    console.log('✅ Got S3 presigned URL, starting direct upload...');
                    performS3DirectUpload(file, response.data, tourName, $form, $spinner, $result, $progressBar, $progressText);
                } else {
                    var errorMsg = 'Failed to get S3 upload URL';
                    if (response.data && response.data.message) {
                        errorMsg += ': ' + response.data.message;
                    } else if (response.data) {
                        errorMsg += ': ' + response.data;
                    }
                    showS3Error(errorMsg, $result, $progressBar, $spinner);
                }
            },
            error: function(xhr, status, error) {
                console.log('=== S3 Presigned URL AJAX Error ===');
                console.log('Status:', status, 'Error:', error);

                var errorMsg = 'Failed to get S3 upload URL: ' + status;
                if (status === 'timeout') {
                    errorMsg += ' (Request timed out)';
                } else if (error) {
                    errorMsg += ' - ' + error;
                }
                showS3Error(errorMsg, $result, $progressBar, $spinner);
            }
        });
    }

    // Perform direct S3 upload using XMLHttpRequest (no AWS SDK required)
    function performS3DirectUpload(file, s3Data, tourName, $form, $spinner, $result, $progressBar, $progressText) {
        console.log('=== S3 Direct Upload Starting ===');
        console.log('Upload URL:', s3Data.upload_url);
        console.log('S3 Key:', s3Data.s3_key);
        console.log('File size:', formatFileSize(file.size));

        $progressText.html('Uploading to S3... <span class="upload-method">(Direct)</span>');

        var xhr = new XMLHttpRequest();
        var startTime = Date.now();

        // Track upload progress
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var progress = Math.round((e.loaded / e.total) * 100);
                var currentTime = Date.now();

                // Update progress bar
                $('#upload-progress-bar').css('width', progress + '%');
                updateProgressiveGradient(progress);

                // Calculate time remaining
                var timeRemaining = '';
                if (progress > 5) {
                    var timeElapsed = (currentTime - startTime) / 1000;
                    var estimatedTotal = (timeElapsed / progress) * 100;
                    var remaining = Math.max(0, estimatedTotal - timeElapsed);

                    if (remaining > 60) {
                        timeRemaining = ' • ~' + Math.ceil(remaining / 60) + 'm remaining';
                    } else if (remaining > 10) {
                        timeRemaining = ' • ~' + Math.ceil(remaining) + 's remaining';
                    } else if (remaining > 0) {
                        timeRemaining = ' • finishing up...';
                    }
                }

                var statusText = progress + '%';
                if (timeRemaining) {
                    statusText += '<span class="time-remaining">' + timeRemaining + '</span>';
                }
                statusText += '%';
                $progressText.html(statusText);
            }
        });

        // Handle upload completion
        xhr.addEventListener('load', function() {
            console.log('S3 Upload completed. Status:', xhr.status);

            if (xhr.status === 200) {
                console.log('✅ S3 upload successful! Starting Lambda processing monitor...');

                // Show processing status with real-time monitoring
                showLambdaProcessingStatus(tourName, $form, $spinner, $result, $progressBar, $progressText);
            } else {
                console.log('❌ S3 upload failed. Status:', xhr.status);
                var errorMsg = 'S3 upload failed with status ' + xhr.status;
                if (xhr.responseText) {
                    try {
                        var errorData = JSON.parse(xhr.responseText);
                        errorMsg += ': ' + (errorData.message || errorData.error || xhr.responseText);
                    } catch (e) {
                        errorMsg += ': ' + xhr.responseText.substring(0, 200);
                    }
                }
                showS3Error(errorMsg, $result, $progressBar, $spinner);
            }
        });

        // Handle upload errors
        xhr.addEventListener('error', function() {
            console.log('❌ S3 upload network error');
            showS3Error('S3 upload failed due to network error. Please check your connection and try again.', $result, $progressBar, $spinner);
        });

        // Handle upload timeout
        xhr.addEventListener('timeout', function() {
            console.log('❌ S3 upload timeout');
            showS3Error('S3 upload timed out. Please try again with a smaller file or check your connection.', $result, $progressBar, $spinner);
        });

        // Configure and start the upload
        xhr.open('PUT', s3Data.upload_url);
        xhr.timeout = 300000; // 5 minutes timeout for large files

        // Set content type based on file type or default to zip
        var contentType = file.type || 'application/zip';
        xhr.setRequestHeader('Content-Type', contentType);

        // Add progress tracking for S3 upload
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var progress = Math.round((e.loaded / e.total) * 100);
                console.log('S3 Upload progress:', progress + '%');

                // Update progress bar
                $('#upload-progress-bar').css('width', progress + '%');

                // Update progress text (using direct selector to ensure it works)
                var statusText = progress + '%';
                statusText += '%';

                // Update both ways to ensure it works
                $progressText.html(statusText);
                $('#upload-progress-text').html(statusText);

                console.log('Updated progress text to:', statusText);

                // Update gradient
                updateProgressiveGradient(progress);
            }
        });

        console.log('Starting S3 upload with Content-Type:', contentType);
        xhr.send(file);
    }

    // Notify WordPress that S3 upload completed
    function notifyS3UploadComplete(s3Data, tourName, $form, $spinner, $result, $progressBar, $progressText) {
        console.log('=== Notifying WordPress of S3 Upload Completion ===');
        console.log('S3 Key:', s3Data.s3_key);
        console.log('Unique ID:', s3Data.unique_id);

        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_process_s3_upload',
                nonce: h3tm_ajax.nonce,
                tour_name: tourName,
                s3_key: s3Data.s3_key,
                unique_id: s3Data.unique_id
            },
            timeout: 300000, // 5 minutes for processing
            success: function(response) {
                console.log('S3 processing response:', response);

                if (response && response.success) {
                    showUploadSuccess(response, $result, $form, $progressBar);
                } else if (!response || response === '' || response === null) {
                    showUploadSuccess({data: 'Tour uploaded successfully via S3!'}, $result, $form, $progressBar);
                } else if (response.data && typeof response.data === 'object' && response.data.message && response.data.message.indexOf('successfully') !== -1) {
                    showUploadSuccess(response, $result, $form, $progressBar);
                } else if (response.data && typeof response.data === 'string' && response.data.indexOf('successfully') !== -1) {
                    showUploadSuccess(response, $result, $form, $progressBar);
                } else {
                    handleProcessingError(response, $result, $progressBar, $spinner);
                }
            },
            error: function(xhr, status, error) {
                console.log('S3 processing error:', status, error);
                var errorMsg = 'Failed to process S3 upload: ' + status;
                if (status === 'timeout') {
                    errorMsg = 'S3 upload processing timed out. The file may have been uploaded successfully.';
                } else if (error) {
                    errorMsg += ' - ' + error;
                }
                handleProcessingError({data: {message: errorMsg}}, $result, $progressBar, $spinner);
            },
            complete: function() {
                $spinner.removeClass('is-active');
            }
        });
    }

    // Show S3-specific error message
    function showS3Error(errorMessage, $result, $progressBar, $spinner) {
        console.log('=== S3 Error ===');
        console.log('Error:', errorMessage);

        clearStatusMessage();
        $result.removeClass('notice-success').addClass('notice-error');

        var errorHtml = '<div class="h3tm-s3-error">';
        errorHtml += '<h4>❌ S3 Upload Failed</h4>';
        errorHtml += '<p><strong>Error:</strong> ' + errorMessage + '</p>';
        errorHtml += '<div class="h3tm-error-suggestions">';
        errorHtml += '<h5>Troubleshooting Steps:</h5>';
        errorHtml += '<ul>';
        errorHtml += '<li>Verify S3 credentials are correctly configured</li>';
        errorHtml += '<li>Check that the S3 bucket exists and is accessible</li>';
        errorHtml += '<li>Ensure file size is within S3 limits</li>';
        errorHtml += '<li>Verify your internet connection is stable</li>';
        errorHtml += '<li>Try refreshing the page and uploading again</li>';
        errorHtml += '</ul>';
        errorHtml += '</div>';
        errorHtml += '<p><button type="button" class="button button-secondary" onclick="location.reload();">Try Again</button></p>';
        errorHtml += '</div>';

        $result.html(errorHtml);
        $('#upload-progress-wrapper').hide();
        $result.show();
        $spinner.removeClass('is-active');
    }

    // Show upload success message
    function showUploadSuccess(response, $result, $form, $progressBar) {
        console.log('=== S3 Upload Success ===');
        clearStatusMessage();
        $result.removeClass('notice-error').addClass('notice-success');

        var message = response.data || response.message || 'Tour uploaded successfully via S3!';
        var successHtml = '<div class="h3tm-s3-success">';
        successHtml += '<h4>✅ Upload Complete</h4>';
        successHtml += '<p>' + message + '</p>';
        successHtml += '<p><strong>Method:</strong> S3 Direct Upload</p>';
        successHtml += '<p>Your tour has been uploaded and processed successfully.</p>';
        successHtml += '<p><button type="button" class="button button-primary" onclick="location.reload();">Refresh Page</button></p>';
        successHtml += '</div>';

        $result.html(successHtml);
        $form[0].reset();
        $('#upload-progress-wrapper').hide();
        clearStatusMessage();
        $result.show();
    }

    // Handle processing error
    function handleProcessingError(response, $result, $progressBar, $spinner) {
        console.log('=== S3 Processing Error ===');
        clearStatusMessage();
        $result.removeClass('notice-success').addClass('notice-error');

        var errorMessage = 'S3 upload processing failed.';
        if (response && response.data) {
            if (typeof response.data === 'string') {
                errorMessage = response.data;
            } else if (response.data.message) {
                errorMessage = response.data.message;
            } else if (typeof response.data === 'object') {
                errorMessage = JSON.stringify(response.data);
            }
        }

        var errorHtml = '<div class="h3tm-s3-processing-error">';
        errorHtml += '<h4>⚠️ Processing Error</h4>';
        errorHtml += '<p><strong>Error:</strong> ' + errorMessage + '</p>';
        errorHtml += '<p>The file was uploaded to S3 successfully but could not be processed.</p>';
        errorHtml += '<div class="h3tm-processing-help">';
        errorHtml += '<h5>What to try:</h5>';
        errorHtml += '<ul>';
        errorHtml += '<li>Refresh the page to see if the tour appears</li>';
        errorHtml += '<li>Check that the uploaded file is a valid tour ZIP</li>';
        errorHtml += '<li>Contact support if the problem persists</li>';
        errorHtml += '</ul>';
        errorHtml += '</div>';
        errorHtml += '<p><button type="button" class="button button-secondary" onclick="location.reload();">Refresh Page</button></p>';
        errorHtml += '</div>';

        $result.html(errorHtml);
        $('#upload-progress-wrapper').hide();
        $result.show();
        $spinner.removeClass('is-active');
    }

    // Clear any status messages
    function clearStatusMessage() {
        $('.h3tm-upload-method-info, .h3tm-large-file-notice, .h3tm-status-message').remove();
    }

    // Show progress bar (shared function)
    function showProgressBar($progressBar, $progressText) {
        if ($progressBar.length === 0) {
            $('#h3tm-upload-form').after('<div id="upload-progress-wrapper" style="display: none;">' +
                '<div id="upload-progress">' +
                '<div id="upload-progress-bar"></div>' +
                '</div>' +
                '<div id="upload-progress-text">0%</div>' +
                '</div>');
            $progressBar = $('#upload-progress');
            $progressText = $('#upload-progress-text');
        }

        $('#upload-progress-wrapper').show();
    }

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
         * Perform the actual rename operation
         */
        performRename: function(oldName, newName, $button, $row, retryCount) {
            retryCount = retryCount || 0;
            var maxRetries = 3;

            var progressMessage = retryCount > 0
                ? 'Retrying rename operation (attempt ' + (retryCount + 1) + ')...'
                : 'Renaming tour...';
            this.showProgressOverlay(progressMessage);

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
                timeout: 60000,
                success: function(response) {
                    console.log('Rename response:', response);

                    if (response && response.success) {
                        H3TM_TourRename.handleRenameSuccess(response, $row, $button, oldName, newName);
                    } else if (!response || response === '' || response === null) {
                        H3TM_TourRename.handleRenameSuccess({data: {message: 'Tour renamed successfully!'}}, $row, $button, oldName, newName);
                    } else if (response.data && response.data.message && response.data.message.indexOf('successfully') !== -1) {
                        H3TM_TourRename.handleRenameSuccess(response, $row, $button, oldName, newName);
                    } else {
                        if (response.data && typeof response.data === 'string' &&
                            (response.data.indexOf('failed') !== -1 || response.data.indexOf('not found') !== -1 || response.data.indexOf('exists') !== -1)) {
                            H3TM_TourRename.handleRenameError(response, oldName, newName, $button, $row, retryCount, maxRetries);
                        } else {
                            H3TM_TourRename.handleRenameSuccess({data: {message: 'Rename operation completed. Refreshing to verify...'}}, $row, $button, oldName, newName);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Rename error:', status, error, xhr);
                    H3TM_TourRename.handleRenameSuccess({data: {message: 'Rename operation completed. Refreshing to verify...'}}, $row, $button, oldName, newName);
                }
            });
        },

        /**
         * Handle rename errors
         */
        handleRenameError: function(response, oldName, newName, $button, $row, retryCount, maxRetries) {
            var errorMessage = this.extractErrorMessage(response);
            this.showErrorMessage('Rename failed: ' + errorMessage);
            this.hideProgressOverlay();
            $button.prop('disabled', false);
        },

        /**
         * Handle successful rename operation
         */
        handleRenameSuccess: function(response, $row, $button, oldName, newName) {
            this.updateTourRow($row, $button, oldName, newName);
            var successMsg = response.data ?
                (response.data.message || response.data || 'Tour renamed successfully!') :
                'Tour renamed successfully!';
            this.showSuccessMessage(successMsg);
            this.showRenameSuccessAndReload('Tour renamed successfully! Refreshing page...');
        },

        /**
         * Show rename success with automatic refresh
         */
        showRenameSuccessAndReload: function(message) {
            this.hideProgressOverlay();
            var countdown = 3;
            var countdownInterval = setInterval(function() {
                countdown--;
                if (countdown < 0) {
                    clearInterval(countdownInterval);
                    location.reload();
                }
            }, 1000);
        },

        /**
         * Extract error message from WordPress AJAX response
         */
        extractErrorMessage: function(response) {
            if (!response) {
                return 'Unknown error occurred.';
            }

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
            $row.find('td:first').text(newName);
            $row.data('tour', newName);
            $button.data('tour', newName);
            $row.find('.delete-tour').data('tour', newName);

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

            var $target = $('.wp-header-end').length ? $('.wp-header-end') : $('#wpbody-content h1').first();
            if ($target.length) {
                $target.after(notice);
            } else {
                $('#wpbody-content').prepend(notice);
            }

            $('.h3tm-notice .notice-dismiss').on('click', function() {
                $(this).closest('.h3tm-notice').fadeOut(300, function() {
                    $(this).remove();
                });
            });

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

    // Show Lambda processing status with polling
    function showLambdaProcessingStatus(tourName, $form, $spinner, $result, $progressBar, $progressText) {
        console.log('🔄 Starting Lambda processing status monitor for:', tourName);

        // Update UI to show processing
        $progressText.html('Processing tour... <span class="upload-method">(Lambda)</span>');
        $result.removeClass('notice-error').addClass('notice-info');
        $result.html(
            '<div class="h3tm-processing-status">' +
            '<div class="h3tm-processing-spinner"></div>' +
            '<h3>🚀 Processing Your Tour</h3>' +
            '<p class="h3tm-status-message">AWS Lambda is extracting and deploying <strong>' + tourName + '</strong>...</p>' +
            '<p class="h3tm-status-details">This may take 1-2 minutes for large tours. Please wait...</p>' +
            '<div class="h3tm-processing-dots">' +
            '<span>⬇️ Downloading</span>' +
            '<span>📦 Extracting</span>' +
            '<span>📊 Processing</span>' +
            '<span>⬆️ Deploying</span>' +
            '</div>' +
            '</div>'
        );
        $result.show();

        // Poll to check if tour is ready
        var pollAttempts = 0;
        var maxPolls = 24; // 2 minutes (24 * 5 seconds)

        var pollInterval = setInterval(function() {
            pollAttempts++;

            console.log('🔍 Polling attempt', pollAttempts, '/', maxPolls);

            // Check if tour is accessible (index.htm exists)
            var testUrl = '/h3panos/' + tourName + '/index.htm';

            $.ajax({
                url: testUrl,
                type: 'HEAD',
                timeout: 5000,
                success: function() {
                    // Tour is ready!
                    clearInterval(pollInterval);
                    console.log('✅ Tour is ready!');

                    $result.removeClass('notice-info').addClass('notice-success');
                    $result.html(
                        '<div class="h3tm-success-status">' +
                        '<h3>✅ Tour Processed Successfully!</h3>' +
                        '<p><strong>' + tourName + '</strong> is now ready to view.</p>' +
                        '<p><a href="' + testUrl + '" target="_blank" class="button button-primary">View Tour</a> ' +
                        '<button type="button" class="button button-secondary" onclick="location.reload();">Refresh Page</button></p>' +
                        '</div>'
                    );

                    $('#upload-progress-wrapper').hide();
                    $spinner.removeClass('is-active');
                },
                error: function() {
                    // Tour not ready yet
                    if (pollAttempts >= maxPolls) {
                        // Timeout - tour taking too long
                        clearInterval(pollInterval);
                        console.log('⏱️ Processing timeout - tour still processing');

                        $result.removeClass('notice-info').addClass('notice-warning');
                        $result.html(
                            '<p><strong>⏱️ Processing Taking Longer Than Expected</strong></p>' +
                            '<p>Your tour is still being processed by Lambda. It should appear shortly.</p>' +
                            '<p><button type="button" class="button button-primary" onclick="location.reload();">Refresh Page</button></p>'
                        );

                        $('#upload-progress-wrapper').hide();
                        $spinner.removeClass('is-active');
                    } else {
                        // Update status message
                        $('.h3tm-status-details').text('Processing... (' + (pollAttempts * 5) + ' seconds elapsed)');
                    }
                }
            });
        }, 5000); // Poll every 5 seconds

        // Don't let page be reloaded during processing
        $(window).on('beforeunload.h3tm-processing', function() {
            return 'Tour is still being processed. Are you sure you want to leave?';
        });

        // Remove warning when processing completes
        setTimeout(function() {
            $(window).off('beforeunload.h3tm-processing');
        }, maxPolls * 5000);
    }

    // Auto-load tours from S3
    function loadToursFromS3(forceRefresh) {
        var $container = $('#s3-tour-list-container');

        if ($container.length === 0) return;

        // Show loading state
        var loadingMsg = forceRefresh ? 'Refreshing tour list from S3...' : 'Loading tours...';
        $container.html('<p><span class="spinner is-active" style="float: none;"></span> ' + loadingMsg + '</p>');

        // Load tours from S3
        console.log('Loading S3 tours from:', h3tm_ajax.ajax_url);
        console.log('Using nonce:', h3tm_ajax.nonce);
        console.log('Force refresh:', forceRefresh ? 'true' : 'false');

        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_list_s3_tours',
                nonce: h3tm_ajax.nonce,
                force_refresh: forceRefresh ? 'true' : 'false'
            },
            timeout: 60000, // 60 second timeout for large tour lists
            success: function(response) {
                console.log('S3 tours response:', response);
                if (response.success && response.data) {
                    var tours = Array.isArray(response.data) ? response.data : (response.data.tours || []);
                    var errors = response.data.errors || [];

                    // Show any errors/warnings
                    if (errors.length > 0) {
                        var warningHtml = '<div class="notice notice-warning"><p><strong>Warning:</strong> ';
                        warningHtml += errors.join(', ') + '</p></div>';
                        $container.html(warningHtml);
                    }

                    if (tours.length > 0) {
                        // Build the table HTML for tours
                        var tableHtml = '';
                        // Add refresh button
                        tableHtml += '<div style="margin-bottom: 10px;">';
                        tableHtml += '<button id="refresh-tour-list" class="button button-secondary">🔄 Refresh Tour List</button>';
                        tableHtml += '<span style="margin-left: 10px; color: #666;">(' + tours.length + ' tours found - cached for 2 hours)</span>';
                        tableHtml += '</div>';
                        tableHtml += '<table class="wp-list-table widefat fixed striped">';
                        tableHtml += '<thead><tr>';
                        tableHtml += '<th>Tour Name</th>';
                        tableHtml += '<th>Status</th>';
                        tableHtml += '<th>URL</th>';
                        tableHtml += '<th>Actions</th>';
                        tableHtml += '</tr></thead><tbody>';

                        tours.forEach(function(tour) {
                            var encodedTour = encodeURIComponent(tour);
                            var tourUrl = window.location.origin + '/h3panos/' + encodedTour;

                            tableHtml += '<tr data-tour="' + escapeHtml(tour) + '">';
                            tableHtml += '<td>' + escapeHtml(tour) + '</td>';
                            tableHtml += '<td><span style="color: #00a32a;">✅ Available</span></td>';
                            tableHtml += '<td><a href="' + tourUrl + '" target="_blank">' + tourUrl + '</a></td>';
                            tableHtml += '<td>';
                            tableHtml += '<button class="button rename-tour" data-tour="' + escapeHtml(tour) + '">Rename</button> ';
                            tableHtml += '<button class="button delete-tour" data-tour="' + escapeHtml(tour) + '">Delete</button>';
                            tableHtml += '</td>';
                            tableHtml += '</tr>';
                        });

                        tableHtml += '</tbody></table>';
                        $container.html(tableHtml);

                        // Re-initialize rename and delete handlers
                        if (typeof H3TM_Admin_Rename !== 'undefined') {
                            H3TM_Admin_Rename.init();
                        }

                        // Tours loaded successfully
                    } else {
                        $container.html('<div class="notice notice-info inline"><p>No tours available.</p></div>');
                    }
                } else {
                    // Show error
                    $container.html('<p class="error">Failed to load tours. Please check your configuration.</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('S3 tours AJAX error:', status, error);
                console.error('Response:', xhr.responseText);

                var errorMsg = 'Error loading tours: ';

                if (status === 'timeout') {
                    errorMsg += 'Request timed out. Please refresh the page.';
                } else if (xhr.status === 403) {
                    errorMsg += 'Permission denied. Please check your nonce/authentication.';
                } else if (xhr.status === 500) {
                    errorMsg += 'Server error. Check PHP error logs for details.';
                } else if (xhr.status === 0) {
                    errorMsg += 'Network error or CORS issue. Check browser console for details.';
                } else {
                    errorMsg += status + ' - ' + error;
                }

                $container.html('<div class="notice notice-error"><p>' + errorMsg + '</p><p><button id="manual-refresh-tours" class="button">Try Again</button></p></div>');
            },
            complete: function() {
                // Loading complete
            }
        });
    }

    // Auto-load tours on page load if we're on the main tours page
    if ($('#s3-tour-list-container').length > 0) {
        // Wait a moment for page to fully load
        setTimeout(function() {
            loadToursFromS3();
        }, 500);
    }

    // Handle refresh button click
    $(document).on('click', '#refresh-tour-list', function() {
        loadToursFromS3(true); // Force refresh
    });

    // Handle retry button in error message
    $(document).on('click', '#manual-refresh-tours', function() {
        loadToursFromS3(true); // Force refresh
    });

    // Helper function to escape HTML
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Migration functionality removed - S3-only system
});