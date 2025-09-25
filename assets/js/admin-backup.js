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

    // S3 Upload Configuration
    var S3_CONFIGURED = (h3tm_ajax.s3_configured === '1' || h3tm_ajax.s3_configured === true);

    // Debug S3 configuration
    console.log('=== H3TM S3 Config Debug ===');
    console.log('S3_CONFIGURED:', S3_CONFIGURED);

    // Handle tour upload - S3 ONLY
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

        if (!S3_CONFIGURED) {
            $result.removeClass('notice-success').addClass('notice-error');
            $result.html('<p><strong>Error:</strong> S3 upload is required but not configured. Please configure AWS S3 settings in the admin panel.</p>');
            $result.show();
            $('#upload-progress-wrapper').hide();
            $spinner.removeClass('is-active');
            return;
        }

        console.log('Starting S3 upload (' + formatFileSize(file.size) + ')...');
        performS3Upload(file, tourName, $form, $spinner, $result, $progressBar, $progressText);
    });

    // Perform S3 upload
    function performS3Upload(file, tourName, $form, $spinner, $result, $progressBar, $progressText) {
        $spinner.addClass('is-active');
        $result.hide();
        showProgressBar($progressBar, $progressText);

        // Get presigned URL from server
        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_get_s3_presigned_url',
                nonce: h3tm_ajax.nonce,
                file_name: file.name,
                file_size: file.size,
                tour_name: tourName
            },
            success: function(response) {
                if (response.success) {
                    console.log('Got presigned URL, uploading to S3...');
                    uploadToS3(file, response.data, tourName, $form, $spinner, $result, $progressBar, $progressText);
                } else {
                    $progressText.text('Error: ' + response.data);
                    setTimeout(function() {
                        showUploadError(response.data);
                        hideProgressBar($progressBar, $progressText);
                        $spinner.removeClass('is-active');
                    }, 1000);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to get presigned URL:', error);
                showUploadError('Failed to get S3 upload URL: ' + error);
                hideProgressBar($progressBar, $progressText);
                $spinner.removeClass('is-active');
            }
        });
    }

    // Upload file to S3 using presigned URL
    function uploadToS3(file, s3Data, tourName, $form, $spinner, $result, $progressBar, $progressText) {
        console.log('=== S3 Direct Upload Starting ===');
        console.log('File size:', formatFileSize(file.size));

        $progressText.text('Uploading to S3...');

        var xhr = new XMLHttpRequest();

        // Track upload progress
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var progress = Math.round((e.loaded / e.total) * 100);
                $('#upload-progress-bar').css('width', progress + '%');
                $progressText.text('Uploading to S3: ' + progress + '%');
            }
        });

        // Handle upload completion
        xhr.addEventListener('load', function() {
            console.log('S3 Upload completed. Status:', xhr.status);

            if (xhr.status === 200) {
                console.log('✅ S3 upload successful! Processing tour...');
                $progressText.text('Upload complete, processing tour...');

                // Notify server to download from S3 and process
                $.ajax({
                    url: h3tm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'h3tm_process_s3_upload',
                        nonce: h3tm_ajax.nonce,
                        tour_name: tourName,
                        s3_key: s3Data.s3_key,
                        unique_id: s3Data.unique_id,
                        file_name: file.name
                    },
                    success: function(response) {
                        if (response.success) {
                            showUploadSuccess(response);
                        } else {
                            showUploadError(response.data || 'Tour processing failed');
                        }
                        hideProgressBar($progressBar, $progressText);
                        $spinner.removeClass('is-active');
                    },
                    error: function(xhr, status, error) {
                        console.error('Tour processing failed:', error);
                        showUploadError('Tour processing failed: ' + error);
                        hideProgressBar($progressBar, $progressText);
                        $spinner.removeClass('is-active');
                    }
                });
            } else {
                console.error('❌ S3 upload failed with status:', xhr.status);
                showUploadError('S3 upload failed (status: ' + xhr.status + ')');
                hideProgressBar($progressBar, $progressText);
                $spinner.removeClass('is-active');
            }
        });

        // Handle upload errors
        xhr.addEventListener('error', function() {
            console.error('❌ S3 upload error');
            showUploadError('S3 upload failed due to network error');
            hideProgressBar($progressBar, $progressText);
            $spinner.removeClass('is-active');
        });

        // Start the upload
        xhr.open('PUT', s3Data.upload_url);
        xhr.send(file);
    }

    // Progress bar utilities
    function showProgressBar($progressBar, $progressText) {
        $('#upload-progress-wrapper').show();
        $('#upload-progress-bar').css('width', '0%');
        $progressText.text('Preparing upload...');
    }

    function hideProgressBar($progressBar, $progressText) {
        $('#upload-progress-wrapper').hide();
        $('#upload-progress-bar').css('width', '0%');
        $progressText.text('');
    }

    // Result display functions
    function showUploadSuccess(response) {
        var $result = $('#upload-result');
        $result.removeClass('notice-error').addClass('notice-success');
        $result.html('<p><strong>Success:</strong> ' + (response.data || response.message || 'Tour uploaded successfully') + '</p>');
        $result.show();

        // Reset form
        $('#h3tm-upload-form')[0].reset();
        $('#file-info').hide();

        // Refresh tour list
        setTimeout(function() {
            location.reload();
        }, 2000);
    }

    function showUploadError(message) {
        var $result = $('#upload-result');
        $result.removeClass('notice-success').addClass('notice-error');
        $result.html('<p><strong>Error:</strong> ' + message + '</p>');
        $result.show();
    }

    // Tour deletion
    $(document).on('click', '.delete-tour', function(e) {
        e.preventDefault();

        var tourName = $(this).data('tour-name');

        if (confirm('Are you sure you want to delete the tour "' + tourName + '"? This action cannot be undone.')) {
            var $button = $(this);
            var originalText = $button.text();

            $button.text('Deleting...');
            $button.prop('disabled', true);

            $.ajax({
                url: h3tm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'h3tm_delete_tour',
                    nonce: h3tm_ajax.nonce,
                    tour_name: tourName
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error: ' + response.data);
                        $button.text(originalText);
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred while deleting the tour.');
                    $button.text(originalText);
                    $button.prop('disabled', false);
                }
            });
        }
    });

    // Tour renaming
    $(document).on('click', '.rename-tour', function(e) {
        e.preventDefault();

        var tourName = $(this).data('tour-name');
        var newName = prompt('Enter new name for tour "' + tourName + '":');

        if (newName && newName !== tourName) {
            var $button = $(this);
            var originalText = $button.text();

            $button.text('Renaming...');
            $button.prop('disabled', true);

            $.ajax({
                url: h3tm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'h3tm_rename_tour',
                    nonce: h3tm_ajax.nonce,
                    old_name: tourName,
                    new_name: newName
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.text(originalText);
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred while renaming the tour.');
                    $button.text(originalText);
                    $button.prop('disabled', false);
                }
            });
        }
    });

    // Email test functionality
    $('#test-email').on('click', function() {
        var $button = $(this);
        var $result = $('#email-test-result');

        $button.text('Sending...').prop('disabled', true);

        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_test_email',
                nonce: h3tm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('notice-error').addClass('notice-success');
                    $result.html('<p>Test email sent successfully!</p>');
                } else {
                    $result.removeClass('notice-success').addClass('notice-error');
                    $result.html('<p>Error: ' + response.data + '</p>');
                }
                $result.show();
            },
            error: function() {
                $result.removeClass('notice-success').addClass('notice-error');
                $result.html('<p>An error occurred while sending the test email.</p>');
                $result.show();
            },
            complete: function() {
                $button.text('Send Test Email').prop('disabled', false);
            }
        });
    });

    // S3 test functionality
    $('#test-s3-connection').on('click', function() {
        var $button = $(this);
        var $result = $('#s3-test-result');

        $button.text('Testing...').prop('disabled', true);

        $.ajax({
            url: h3tm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'h3tm_test_s3_connection',
                nonce: h3tm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('notice-error').addClass('notice-success');
                    $result.html('<p>' + response.data + '</p>');
                } else {
                    $result.removeClass('notice-success').addClass('notice-error');
                    $result.html('<p>Error: ' + response.data + '</p>');
                }
                $result.show();
            },
            error: function() {
                $result.removeClass('notice-success').addClass('notice-error');
                $result.html('<p>An error occurred while testing S3 connection.</p>');
                $result.show();
            },
            complete: function() {
                $button.text('Test S3 Connection').prop('disabled', false);
            }
        });
    });
});