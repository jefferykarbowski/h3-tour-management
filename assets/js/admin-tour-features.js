// Additional Tour Management Features
// Update Tour and Get Script functionality

jQuery(document).ready(function($) {

    // ===== Update Tour Functionality =====

    // Handle Update Tour button click
    $(document).on('click', '.update-tour', function() {
        var tourName = $(this).data('tour');
        var $button = $(this);
        var $row = $button.closest('tr');

        // Show file upload modal for update
        var modal = createUpdateTourModal(tourName);
        $('body').append(modal);
        var $modal = $('#h3tm-update-modal');
        $modal.addClass('h3tm-modal-show');

        // Handle file selection
        $modal.find('#h3tm-update-file').on('change', function() {
            var file = this.files[0];
            if (file) {
                $modal.find('#h3tm-update-filename').text(file.name);
                $modal.find('#h3tm-update-filesize').text(formatFileSize(file.size));
                $modal.find('#h3tm-update-file-info').show();
                $modal.find('.h3tm-update-confirm').prop('disabled', false);
            }
        });

        // Handle update confirmation
        $modal.find('.h3tm-update-confirm').on('click', function() {
            var file = $modal.find('#h3tm-update-file')[0].files[0];
            if (!file) {
                alert('Please select a file');
                return;
            }

            performTourUpdate(tourName, file, $modal, $button, $row);
        });

        // Handle cancel
        $modal.find('.h3tm-modal-cancel, .h3tm-modal-close').on('click', function() {
            closeModal($modal);
        });
    });

    // ===== Change URL Functionality =====

    // Handle Change URL button click
    $(document).on('click', '.change-url', function() {
        var tourName = $(this).data('tour');
        var currentUrl = $(this).data('url');
        var $button = $(this);
        var $row = $button.closest('tr');

        // Get current slug from URL
        var currentSlug = currentUrl ? currentUrl.split('/h3panos/')[1].replace(/\/$/, '') : sanitize_title(tourName);

        // Show Change URL modal
        var modal = createChangeUrlModal(tourName, currentSlug);
        $('body').append(modal);
        var $modal = $('#h3tm-change-url-modal');
        $modal.addClass('h3tm-modal-show');

        // Focus on input
        setTimeout(function() {
            $modal.find('#h3tm-new-slug').focus().select();
        }, 200);

        // Handle form submission
        $modal.find('#h3tm-change-url-form').on('submit', function(e) {
            e.preventDefault();
            var newSlug = $modal.find('#h3tm-new-slug').val().trim();

            if (validateSlug(newSlug, currentSlug, $modal)) {
                performUrlChange(tourName, currentSlug, newSlug, $modal, $button, $row);
            }
        });

        // Handle cancel
        $modal.find('.h3tm-modal-cancel, .h3tm-modal-close').on('click', function() {
            closeModal($modal);
        });

        // Escape key
        $(document).on('keydown.h3tm-change-url', function(e) {
            if (e.keyCode === 27) {
                closeModal($modal);
            }
        });
    });

    // ===== Get Script Functionality =====

    // Handle Get Script button click
    $(document).on('click', '.get-script', function() {
        var tourName = $(this).data('tour');

        // Show loading
        if (typeof H3TM_TourRename !== 'undefined') {
            H3TM_TourRename.showProgressOverlay('Generating embed script...');
        }

        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_get_embed_script',
                nonce: h3tm_ajax.nonce,
                tour_name: tourName
            },
            success: function(response) {
                if (typeof H3TM_TourRename !== 'undefined') {
                    H3TM_TourRename.hideProgressOverlay();
                }

                if (response.success) {
                    showEmbedScriptModal(response.data);
                } else {
                    if (typeof H3TM_TourRename !== 'undefined') {
                        H3TM_TourRename.showErrorMessage('Failed to generate embed script: ' + (response.data || 'Unknown error'));
                    } else {
                        alert('Failed to generate embed script');
                    }
                }
            },
            error: function() {
                if (typeof H3TM_TourRename !== 'undefined') {
                    H3TM_TourRename.hideProgressOverlay();
                    H3TM_TourRename.showErrorMessage('Failed to get embed script');
                } else {
                    alert('Failed to get embed script');
                }
            }
        });
    });

    // Create Change URL modal
    function createChangeUrlModal(tourName, currentSlug) {
        var currentUrl = window.location.origin + "/h3panos/" + currentSlug + "/";

        return "<div id=\"h3tm-change-url-modal\" class=\"h3tm-modal-overlay\" role=\"dialog\">" +
               "  <div class=\"h3tm-modal-container\">" +
               "    <div class=\"h3tm-modal-header\">" +
               "      <h3>Change Tour URL</h3>" +
               "      <button type=\"button\" class=\"h3tm-modal-close\">&times;</button>" +
               "    </div>" +
               "    <div class=\"h3tm-modal-body\">" +
               "      <p><strong>Tour:</strong> " + escapeHtml(tourName) + "</p>" +
               "      <p><strong>Current URL:</strong> <code>" + escapeHtml(currentUrl) + "</code></p>" +
               "      <div class=\"h3tm-url-info\">" +
               "        <p><strong>ℹ️ Important:</strong></p>" +
               "        <ul>" +
               "          <li>Old URL will automatically redirect to the new URL (301)</li>" +
               "          <li>Display name and S3 files remain unchanged</li>" +
               "          <li>Clients using old links will be redirected automatically</li>" +
               "        </ul>" +
               "      </div>" +
               "      <form id=\"h3tm-change-url-form\">" +
               "        <div class=\"h3tm-form-field\">" +
               "          <label for=\"h3tm-new-slug\">New URL Slug:</label>" +
               "          <input type=\"text\" id=\"h3tm-new-slug\" name=\"new_slug\" value=\"" + escapeHtml(currentSlug) + "\" required />" +
               "          <div class=\"h3tm-field-error\" id=\"h3tm-slug-error\" role=\"alert\"></div>" +
               "          <div class=\"h3tm-field-hint\">Only lowercase letters, numbers, and hyphens. Example: bee-cave-tour</div>" +
               "          <div class=\"h3tm-url-preview\">" +
               "            <strong>New URL:</strong> <span id=\"h3tm-url-preview-text\">" + window.location.origin + "/h3panos/<span class=\"h3tm-slug-preview\">" + escapeHtml(currentSlug) + "</span>/</span>" +
               "          </div>" +
               "        </div>" +
               "      </form>" +
               "    </div>" +
               "    <div class=\"h3tm-modal-footer\">" +
               "      <button type=\"button\" class=\"button button-secondary h3tm-modal-cancel\">Cancel</button>" +
               "      <button type=\"submit\" form=\"h3tm-change-url-form\" class=\"button button-primary\">Change URL</button>" +
               "    </div>" +
               "  </div>" +
               "</div>";
    }

    // Validate slug input
    function validateSlug(newSlug, currentSlug, $modal) {
        var $error = $modal.find("#h3tm-slug-error");
        var $input = $modal.find("#h3tm-new-slug");

        $error.text("").hide();
        $input.removeClass("h3tm-field-error-input");

        if (!newSlug) {
            showFieldError($error, $input, "URL slug is required");
            return false;
        }

        if (newSlug === currentSlug) {
            showFieldError($error, $input, "Please enter a different URL slug");
            return false;
        }

        if (!/^[a-z0-9-]+$/.test(newSlug)) {
            showFieldError($error, $input, "URL slug can only contain lowercase letters, numbers, and hyphens");
            return false;
        }

        if (newSlug.length < 2) {
            showFieldError($error, $input, "URL slug must be at least 2 characters");
            return false;
        }

        if (newSlug.length > 200) {
            showFieldError($error, $input, "URL slug is too long (max 200 characters)");
            return false;
        }

        return true;
    }

    // Show field error
    function showFieldError($error, $input, message) {
        $error.text(message).show();
        $input.addClass("h3tm-field-error-input").focus();
    }

    // Perform URL change
    function performUrlChange(tourName, oldSlug, newSlug, $modal, $button, $row) {
        $button.prop("disabled", true);
        closeModal($modal);

        if (typeof H3TM_TourRename !== "undefined") {
            H3TM_TourRename.showProgressOverlay("Changing tour URL...");
        }

        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: "POST",
            data: {
                action: "h3tm_change_tour_url",
                nonce: h3tm_ajax.nonce,
                tour_name: tourName,
                new_slug: newSlug
            },
            success: function(response) {
                if (typeof H3TM_TourRename !== "undefined") {
                    H3TM_TourRename.hideProgressOverlay();
                }

                if (response.success) {
                    // Update URL in the row
                    var $link = $row.find("td:eq(2) a");
                    if ($link.length) {
                        $link.attr("href", response.data.new_url).text(response.data.new_url);
                    }

                    // Update change-url button data
                    $row.find(".change-url").data("url", response.data.new_url);

                    if (typeof H3TM_TourRename !== "undefined") {
                        H3TM_TourRename.showSuccessMessage("URL changed successfully! Old URL will redirect to new URL.");
                    }

                    $button.prop("disabled", false);
                } else {
                    if (typeof H3TM_TourRename !== "undefined") {
                        H3TM_TourRename.showErrorMessage("Failed to change URL: " + (response.data || "Unknown error"));
                    }
                    $button.prop("disabled", false);
                }
            },
            error: function() {
                if (typeof H3TM_TourRename !== "undefined") {
                    H3TM_TourRename.hideProgressOverlay();
                    H3TM_TourRename.showErrorMessage("Failed to change tour URL");
                }
                $button.prop("disabled", false);
            }
        });
    }

    // Live URL preview update
    $(document).on("input", "#h3tm-new-slug", function() {
        var newSlug = $(this).val().trim().toLowerCase().replace(/[^a-z0-9-]/g, "");
        $(this).val(newSlug);
        $("#h3tm-change-url-modal .h3tm-slug-preview").text(newSlug || "your-slug");
    });


    // ===== Helper Functions =====

    // Create Update Tour modal
    function createUpdateTourModal(tourName) {
        return '<div id="h3tm-update-modal" class="h3tm-modal-overlay" role="dialog">' +
               '  <div class="h3tm-modal-container">' +
               '    <div class="h3tm-modal-header">' +
               '      <h3>Update Tour</h3>' +
               '      <button type="button" class="h3tm-modal-close">&times;</button>' +
               '    </div>' +
               '    <div class="h3tm-modal-body">' +
               '      <p><strong>Tour:</strong> ' + escapeHtml(tourName) + '</p>' +
               '      <div class="h3tm-update-warning">' +
               '        <p style="color: #d63384;"><strong>⚠️ Warning:</strong> This will overwrite all existing tour files.</p>' +
               '      </div>' +
               '      <div class="h3tm-form-field">' +
               '        <label for="h3tm-update-file">Select New Tour ZIP File:</label>' +
               '        <input type="file" id="h3tm-update-file" accept=".zip" />' +
               '        <div id="h3tm-update-file-info" style="display: none; margin-top: 10px;">' +
               '          <p><strong>File:</strong> <span id="h3tm-update-filename"></span></p>' +
               '          <p><strong>Size:</strong> <span id="h3tm-update-filesize"></span></p>' +
               '        </div>' +
               '      </div>' +
               '    </div>' +
               '    <div class="h3tm-modal-footer">' +
               '      <button type="button" class="button button-secondary h3tm-modal-cancel">Cancel</button>' +
               '      <button type="button" class="button button-primary h3tm-update-confirm" disabled>Update Tour</button>' +
               '    </div>' +
               '  </div>' +
               '</div>';
    }

    // Perform tour update
    function performTourUpdate(tourName, file, $modal, $button, $row) {
        $button.prop('disabled', true);
        closeModal($modal);

        // Show progress overlay
        if (typeof H3TM_TourRename !== 'undefined') {
            H3TM_TourRename.showProgressOverlay('Uploading new tour files...');
        }

        // Get presigned URL
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
            success: function(response) {
                if (response.success && response.data && response.data.upload_url) {
                    // Upload to S3
                    var xhr = new XMLHttpRequest();

                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var progress = Math.round((e.loaded / e.total) * 100);
                            if (typeof H3TM_TourRename !== 'undefined') {
                                $('.h3tm-progress-message').text('Uploading... ' + progress + '%');
                            }
                        }
                    });

                    xhr.addEventListener('load', function() {
                        if (xhr.status === 200) {
                            if (typeof H3TM_TourRename !== 'undefined') {
                                $('.h3tm-progress-message').text('Processing updated tour files...');
                            }

                            // Trigger update processing
                            $.ajax({
                                url: h3tm_ajax.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'h3tm_update_tour',
                                    nonce: h3tm_ajax.nonce,
                                    tour_name: tourName,
                                    s3_key: response.data.s3_key
                                },
                                success: function(updateResponse) {
                                    if (typeof H3TM_TourRename !== 'undefined') {
                                        H3TM_TourRename.hideProgressOverlay();
                                    }

                                    if (updateResponse.success) {
                                        if (typeof H3TM_TourRename !== 'undefined') {
                                            H3TM_TourRename.showSuccessMessage('Tour updated successfully! Page will refresh in 3 seconds...');
                                        }
                                        setTimeout(function() {
                                            location.reload();
                                        }, 3000);
                                    } else {
                                        if (typeof H3TM_TourRename !== 'undefined') {
                                            H3TM_TourRename.showErrorMessage('Update processing failed: ' + (updateResponse.data || 'Unknown error'));
                                        }
                                        $button.prop('disabled', false);
                                    }
                                },
                                error: function() {
                                    if (typeof H3TM_TourRename !== 'undefined') {
                                        H3TM_TourRename.hideProgressOverlay();
                                        H3TM_TourRename.showErrorMessage('Failed to process tour update');
                                    }
                                    $button.prop('disabled', false);
                                }
                            });
                        } else {
                            if (typeof H3TM_TourRename !== 'undefined') {
                                H3TM_TourRename.hideProgressOverlay();
                                H3TM_TourRename.showErrorMessage('Upload failed with status: ' + xhr.status);
                            }
                            $button.prop('disabled', false);
                        }
                    });

                    xhr.open('PUT', response.data.upload_url);
                    xhr.setRequestHeader('Content-Type', file.type || 'application/zip');
                    xhr.send(file);
                } else {
                    if (typeof H3TM_TourRename !== 'undefined') {
                        H3TM_TourRename.hideProgressOverlay();
                        H3TM_TourRename.showErrorMessage('Failed to get upload URL');
                    }
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                if (typeof H3TM_TourRename !== 'undefined') {
                    H3TM_TourRename.hideProgressOverlay();
                    H3TM_TourRename.showErrorMessage('Failed to initiate update');
                }
                $button.prop('disabled', false);
            }
        });
    }

    // Show embed script modal
    function showEmbedScriptModal(data) {
        var modal = '<div id="h3tm-embed-modal" class="h3tm-modal-overlay h3tm-embed-modal" role="dialog">' +
                    '  <div class="h3tm-modal-container h3tm-embed-container">' +
                    '    <div class="h3tm-modal-header">' +
                    '      <h3>Embed Code for: ' + escapeHtml(data.tour_name) + '</h3>' +
                    '      <button type="button" class="h3tm-modal-close">&times;</button>' +
                    '    </div>' +
                    '    <div class="h3tm-modal-body">' +
                    '      <p><strong>Tour URL:</strong> <a href="' + data.tour_url + '" target="_blank">' + data.tour_url + '</a></p>' +
                    '' +
                    '      <div class="h3tm-embed-option">' +
                    '        <h4>Standard Embed (Fixed Height)</h4>' +
                    '        <textarea id="h3tm-embed-standard" class="h3tm-embed-code" readonly>' + escapeHtml(data.embed_script) + '</textarea>' +
                    '        <button type="button" class="button button-primary h3tm-copy-embed" data-target="h3tm-embed-standard">Copy to Clipboard</button>' +
                    '      </div>' +
                    '' +
                    '      <div class="h3tm-embed-option">' +
                    '        <h4>Responsive Embed (16:9 Aspect Ratio)</h4>' +
                    '        <textarea id="h3tm-embed-responsive" class="h3tm-embed-code" readonly>' + escapeHtml(data.embed_script_responsive) + '</textarea>' +
                    '        <button type="button" class="button button-primary h3tm-copy-embed" data-target="h3tm-embed-responsive">Copy to Clipboard</button>' +
                    '      </div>' +
                    '' +
                    '      <div class="h3tm-embed-instructions">' +
                    '        <h4>How to Use:</h4>' +
                    '        <ol>' +
                    '          <li>Click "Copy to Clipboard" on your preferred embed option</li>' +
                    '          <li>Paste the code into your website HTML</li>' +
                    '          <li>The tour will display in an iframe</li>' +
                    '        </ol>' +
                    '        <p><strong>Note:</strong> The responsive embed maintains a 16:9 aspect ratio and works well on all devices.</p>' +
                    '      </div>' +
                    '    </div>' +
                    '    <div class="h3tm-modal-footer">' +
                    '      <button type="button" class="button button-secondary h3tm-modal-close">Close</button>' +
                    '    </div>' +
                    '  </div>' +
                    '</div>';

        var $modal = $(modal);
        $('body').append($modal);
        $modal.addClass('h3tm-modal-show');

        // Handle copy to clipboard
        $modal.find('.h3tm-copy-embed').on('click', function() {
            var targetId = $(this).data('target');
            var $textarea = $('#' + targetId);
            var $button = $(this);

            $textarea[0].select();
            $textarea[0].setSelectionRange(0, 99999); // For mobile devices

            try {
                // Try modern clipboard API first
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText($textarea.val()).then(function() {
                        showCopySuccess($button);
                    }).catch(function() {
                        // Fallback to execCommand
                        document.execCommand('copy');
                        showCopySuccess($button);
                    });
                } else {
                    // Fallback for older browsers
                    document.execCommand('copy');
                    showCopySuccess($button);
                }
            } catch (err) {
                alert('Failed to copy to clipboard. Please select and copy manually.');
            }
        });

        // Handle close
        $modal.find('.h3tm-modal-close').on('click', function() {
            closeModal($modal);
        });

        // Escape key to close
        $(document).on('keydown.h3tm-embed', function(e) {
            if (e.keyCode === 27) {
                closeModal($modal);
            }
        });
    }

    // Show copy success feedback
    function showCopySuccess($button) {
        var originalText = $button.text();
        $button.text('✓ Copied!').addClass('h3tm-copy-success');

        setTimeout(function() {
            $button.text(originalText).removeClass('h3tm-copy-success');
        }, 2000);
    }

    // Close modal helper
    function closeModal($modal) {
        $modal.removeClass('h3tm-modal-show');
        $(document).off('keydown.h3tm-embed');
        setTimeout(function() {
            $modal.remove();
        }, 300);
    }

    // Helper to format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Helper to escape HTML
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
