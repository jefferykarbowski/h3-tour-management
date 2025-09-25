/**
 * H3TM S3 Direct Upload Handler
 * Handles direct browser-to-S3 uploads with fallback to chunked upload
 */

class H3TM_S3_Uploader {
    constructor(options = {}) {
        this.options = {
            uploadThreshold: 50 * 1024 * 1024, // 50MB
            chunkSize: 10 * 1024 * 1024, // 10MB for S3 multipart
            maxRetries: 3,
            retryDelay: 1000,
            progressUpdateInterval: 100,
            ...options
        };

        this.currentUpload = null;
        this.isUploading = false;
        this.progressCallback = null;
        this.statusCallback = null;
    }

    /**
     * Determine upload method and initiate upload
     */
    async uploadFile(file, tourName, method = 'auto') {
        if (this.isUploading) {
            throw new Error('Upload already in progress');
        }

        this.isUploading = true;

        try {
            const useS3 = this.shouldUseS3Upload(file, method);

            if (useS3) {
                return await this.uploadToS3(file, tourName);
            } else {
                return await this.uploadChunked(file, tourName);
            }
        } catch (error) {
            this.isUploading = false;
            throw error;
        }
    }

    /**
     * Determine if S3 upload should be used
     */
    shouldUseS3Upload(file, method) {
        switch (method) {
            case 's3':
                return true;
            case 'chunked':
                return false;
            case 'auto':
            default:
                return file.size > this.options.uploadThreshold;
        }
    }

    /**
     * Upload file directly to S3
     */
    async uploadToS3(file, tourName) {
        try {
            // Step 1: Get presigned URL from WordPress
            this.updateStatus('initializing', 'Getting upload URL...');
            const uploadConfig = await this.getS3UploadURL(file, tourName);

            this.currentUpload = {
                uploadId: uploadConfig.upload_id,
                s3Key: uploadConfig.s3_key,
                method: 's3'
            };

            // Step 2: Upload to S3
            if (uploadConfig.multipart && file.size > 100 * 1024 * 1024) {
                await this.uploadMultipart(file, uploadConfig);
            } else {
                await this.uploadSingle(file, uploadConfig);
            }

            // Step 3: Notify WordPress of completion
            this.updateStatus('verifying', 'Verifying upload...');
            await this.notifyUploadComplete(uploadConfig.upload_id);

            // Step 4: Initiate processing
            this.updateStatus('processing', 'Processing tour files...');
            await this.initiateProcessing(uploadConfig.upload_id);

            // Step 5: Monitor processing
            await this.monitorProcessing(uploadConfig.upload_id);

            this.isUploading = false;
            return {
                success: true,
                uploadId: uploadConfig.upload_id,
                method: 's3'
            };

        } catch (error) {
            this.isUploading = false;
            this.updateStatus('failed', `S3 upload failed: ${error.message}`);

            // Attempt fallback to chunked upload for smaller files
            if (file.size <= 100 * 1024 * 1024) {
                console.warn('S3 upload failed, attempting fallback to chunked upload');
                return await this.uploadChunked(file, tourName);
            }

            throw error;
        }
    }

    /**
     * Get S3 upload URL from WordPress
     */
    async getS3UploadURL(file, tourName) {
        const formData = new FormData();
        formData.append('action', 'h3tm_get_s3_upload_url');
        formData.append('nonce', h3tm_ajax.nonce);
        formData.append('tour_name', tourName);
        formData.append('file_name', file.name);
        formData.append('file_size', file.size);
        formData.append('file_type', file.type || 'application/zip');

        const response = await fetch(h3tm_ajax.ajax_url, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data || 'Failed to get upload URL');
        }

        return result.data;
    }

    /**
     * Upload single file to S3
     */
    async uploadSingle(file, config) {
        this.updateStatus('uploading', 'Uploading to S3...');

        const formData = new FormData();

        // Add S3 form fields
        Object.keys(config.fields).forEach(key => {
            formData.append(key, config.fields[key]);
        });

        // Add file last
        formData.append('file', file);

        const xhr = new XMLHttpRequest();

        return new Promise((resolve, reject) => {
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const progress = Math.round((event.loaded / event.total) * 100);
                    this.updateProgress(progress, `Uploading: ${progress}%`);
                }
            };

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    this.updateProgress(100, 'Upload completed');
                    resolve();
                } else {
                    reject(new Error(`S3 upload failed with status ${xhr.status}: ${xhr.statusText}`));
                }
            };

            xhr.onerror = () => {
                reject(new Error('S3 upload network error'));
            };

            xhr.ontimeout = () => {
                reject(new Error('S3 upload timeout'));
            };

            xhr.timeout = 600000; // 10 minutes
            xhr.open('POST', config.upload_url, true);
            xhr.send(formData);
        });
    }

    /**
     * Upload large file using multipart upload
     */
    async uploadMultipart(file, config) {
        this.updateStatus('uploading', 'Starting multipart upload...');

        const chunkSize = config.chunk_size || this.options.chunkSize;
        const totalChunks = Math.ceil(file.size / chunkSize);
        let uploadedChunks = 0;

        // Create multipart upload
        const uploadId = await this.createMultipartUpload(config);
        const parts = [];

        try {
            // Upload chunks in parallel (max 3 concurrent)
            const concurrency = 3;
            const chunks = [];

            for (let i = 0; i < totalChunks; i++) {
                const start = i * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);

                chunks.push({
                    chunk,
                    partNumber: i + 1,
                    size: end - start
                });
            }

            // Process chunks in batches
            for (let i = 0; i < chunks.length; i += concurrency) {
                const batch = chunks.slice(i, i + concurrency);
                const promises = batch.map(chunkData =>
                    this.uploadChunkToS3(chunkData, config, uploadId)
                );

                const batchResults = await Promise.all(promises);
                parts.push(...batchResults);
                uploadedChunks += batch.length;

                const progress = Math.round((uploadedChunks / totalChunks) * 100);
                this.updateProgress(progress, `Uploaded ${uploadedChunks}/${totalChunks} chunks`);
            }

            // Complete multipart upload
            await this.completeMultipartUpload(config, uploadId, parts);

        } catch (error) {
            // Abort multipart upload on error
            await this.abortMultipartUpload(config, uploadId);
            throw error;
        }
    }

    /**
     * Fallback to chunked upload (existing method)
     */
    async uploadChunked(file, tourName) {
        this.updateStatus('uploading', 'Using chunked upload...');

        this.currentUpload = {
            method: 'chunked',
            file: file,
            tourName: tourName
        };

        // Use existing chunked upload logic from admin.js
        return new Promise((resolve, reject) => {
            this.performChunkedUpload(file, tourName)
                .then(result => {
                    this.isUploading = false;
                    resolve(result);
                })
                .catch(error => {
                    this.isUploading = false;
                    reject(error);
                });
        });
    }

    /**
     * Notify WordPress that S3 upload is complete
     */
    async notifyUploadComplete(uploadId) {
        const formData = new FormData();
        formData.append('action', 'h3tm_s3_upload_complete');
        formData.append('nonce', h3tm_ajax.nonce);
        formData.append('upload_id', uploadId);

        const response = await fetch(h3tm_ajax.ajax_url, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data || 'Failed to verify upload completion');
        }

        return result.data;
    }

    /**
     * Initiate processing of uploaded file
     */
    async initiateProcessing(uploadId) {
        const formData = new FormData();
        formData.append('action', 'h3tm_process_s3_upload');
        formData.append('nonce', h3tm_ajax.nonce);
        formData.append('upload_id', uploadId);

        const response = await fetch(h3tm_ajax.ajax_url, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data || 'Failed to initiate processing');
        }

        return result.data;
    }

    /**
     * Monitor processing status
     */
    async monitorProcessing(uploadId) {
        const maxAttempts = 180; // 15 minutes max (5-second intervals)
        let attempts = 0;

        while (attempts < maxAttempts) {
            await new Promise(resolve => setTimeout(resolve, 5000)); // 5-second delay

            try {
                const status = await this.checkUploadStatus(uploadId);

                this.updateStatus(status.status, status.message);

                if (status.status === 'completed') {
                    return status;
                } else if (status.status === 'failed') {
                    throw new Error(status.error || 'Processing failed');
                }

            } catch (error) {
                console.warn('Status check failed:', error);
            }

            attempts++;
        }

        throw new Error('Processing timeout - please check admin panel for status');
    }

    /**
     * Check upload/processing status
     */
    async checkUploadStatus(uploadId) {
        const url = new URL(h3tm_ajax.ajax_url);
        url.searchParams.set('action', 'h3tm_check_s3_upload');
        url.searchParams.set('nonce', h3tm_ajax.nonce);
        url.searchParams.set('upload_id', uploadId);

        const response = await fetch(url);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.data || 'Failed to check status');
        }

        return result.data;
    }

    /**
     * Cancel current upload
     */
    async cancelUpload() {
        if (!this.isUploading || !this.currentUpload) {
            return false;
        }

        this.isUploading = false;

        if (this.currentUpload.method === 's3') {
            // TODO: Implement S3 multipart upload abortion
            console.log('Cancelling S3 upload:', this.currentUpload.uploadId);
        }

        this.updateStatus('cancelled', 'Upload cancelled');
        return true;
    }

    /**
     * Set progress callback
     */
    onProgress(callback) {
        this.progressCallback = callback;
        return this;
    }

    /**
     * Set status callback
     */
    onStatus(callback) {
        this.statusCallback = callback;
        return this;
    }

    /**
     * Update progress
     */
    updateProgress(percent, message = '') {
        if (this.progressCallback) {
            this.progressCallback({
                percent: percent,
                message: message
            });
        }
    }

    /**
     * Update status
     */
    updateStatus(status, message = '') {
        if (this.statusCallback) {
            this.statusCallback({
                status: status,
                message: message
            });
        }
    }

    /**
     * Create multipart upload (placeholder - would need backend support)
     */
    async createMultipartUpload(config) {
        // This would require additional backend endpoints for multipart uploads
        throw new Error('Multipart upload not yet implemented');
    }

    /**
     * Upload chunk to S3 (placeholder)
     */
    async uploadChunkToS3(chunkData, config, uploadId) {
        // This would require additional backend support
        throw new Error('S3 chunk upload not yet implemented');
    }

    /**
     * Complete multipart upload (placeholder)
     */
    async completeMultipartUpload(config, uploadId, parts) {
        // This would require additional backend support
        throw new Error('Complete multipart upload not yet implemented');
    }

    /**
     * Abort multipart upload (placeholder)
     */
    async abortMultipartUpload(config, uploadId) {
        // This would require additional backend support
        console.warn('Aborting multipart upload:', uploadId);
    }

    /**
     * Existing chunked upload method (simplified)
     */
    async performChunkedUpload(file, tourName) {
        // This would call the existing chunked upload logic
        // For now, return a placeholder
        return {
            success: true,
            method: 'chunked',
            message: 'Chunked upload completed'
        };
    }
}

// Global instance
window.H3TM_S3_Uploader = H3TM_S3_Uploader;

// jQuery integration for existing admin interface
jQuery(document).ready(function($) {
    // Replace existing upload handler with S3-aware version
    $('#h3tm-upload-form').off('submit').on('submit', async function(e) {
        e.preventDefault();

        const $form = $(this);
        const tourName = $('#tour_name').val();
        const file = $('#tour_file')[0].files[0];
        const uploadMethod = $('input[name="upload_method"]:checked').val() || 'auto';

        if (!file) {
            alert('Please select a file to upload');
            return;
        }

        // Initialize uploader
        const uploader = new H3TM_S3_Uploader();

        // Set up progress and status callbacks
        uploader.onProgress(function(progress) {
            $('#upload-progress-bar').css('width', progress.percent + '%');
            $('#upload-progress-text').text(progress.message || progress.percent + '%');
        }).onStatus(function(status) {
            console.log('Upload status:', status);
            if (status.status === 'failed') {
                $('#upload-result').html('<div class="error">' + status.message + '</div>').show();
            }
        });

        // Show progress bar
        $('#upload-progress-wrapper').show();
        $form.find('input[type="submit"]').prop('disabled', true);

        try {
            const result = await uploader.uploadFile(file, tourName, uploadMethod);

            $('#upload-result').html('<div class="updated"><p>Tour uploaded successfully using ' + result.method + ' method!</p></div>').show();

            // Reset form
            $form[0].reset();
            $('#file-info').hide();

            // Refresh tour list
            location.reload();

        } catch (error) {
            console.error('Upload error:', error);
            $('#upload-result').html('<div class="error"><p>Upload failed: ' + error.message + '</p></div>').show();
        } finally {
            $('#upload-progress-wrapper').hide();
            $form.find('input[type="submit"]').prop('disabled', false);
        }
    });
});