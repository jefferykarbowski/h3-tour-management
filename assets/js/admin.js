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
        
        $spinner.addClass('is-active');
        $result.hide();
        
        // Show progress bar
        if ($progressBar.length === 0) {
            $form.after('<div id="upload-progress-wrapper" style="margin-top: 10px; display: none;">' +
                '<div id="upload-progress" style="width: 100%; height: 20px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 3px; overflow: hidden;">' +
                '<div id="upload-progress-bar" style="width: 0%; height: 100%; background: #c1272d; transition: width 0.3s;"></div>' +
                '</div>' +
                '<div id="upload-progress-text" style="text-align: center; margin-top: 5px;">0%</div>' +
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
                        
                        $('#upload-progress-bar').css('width', progress + '%');
                        $progressText.text(progress + '%');
                        
                        // Show free space if available
                        if (response.data.free_space) {
                            $progressText.text(progress + '% (Free space: ' + response.data.free_space + ')');
                        }
                        
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
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('notice-error').addClass('notice-success');
                        $result.html('<p>' + response.data + '</p>');
                        $form[0].reset();
                        $('#upload-progress-wrapper').hide();
                        
                        // Reload page after 2 seconds to show new tour
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.removeClass('notice-success').addClass('notice-error');
                        $result.html('<p>' + response.data + '</p>');
                        $('#upload-progress-wrapper').hide();
                    }
                    $result.show();
                },
                error: function() {
                    $result.removeClass('notice-success').addClass('notice-error');
                    $result.html('<p>An error occurred while processing the tour.</p>');
                    $result.show();
                    $('#upload-progress-wrapper').hide();
                },
                complete: function() {
                    $spinner.removeClass('is-active');
                }
            });
        }
        
        // Start upload
        uploadChunk(0);
    });
    
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
                    alert('Error: ' + response.data);
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
    
    // Handle tour rename
    $('.rename-tour').on('click', function() {
        var oldName = $(this).data('tour');
        var newName = prompt('Enter new name for tour "' + oldName + '":', oldName);
        
        if (!newName || newName === oldName) {
            return;
        }
        
        var $button = $(this);
        var $row = $button.closest('tr');
        
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
            success: function(response) {
                if (response.success) {
                    // Update the row with new name
                    $row.find('td:first').text(newName);
                    $row.data('tour', newName);
                    $button.data('tour', newName);
                    $row.find('.delete-tour').data('tour', newName);
                    
                    // Update URL
                    var newUrl = $row.find('a').attr('href').replace(encodeURIComponent(oldName), encodeURIComponent(newName));
                    $row.find('a').attr('href', newUrl).text(newUrl);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while renaming the tour.');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
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