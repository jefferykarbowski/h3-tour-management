/**
 * S3 Direct Upload JavaScript for H3 Tour Management
 *
 * Handles secure client-side S3 direct uploads with presigned URLs
 * and comprehensive error handling. No AWS credentials exposed to frontend.
 *
 * @package H3_Tour_Management
 * @since 2.1.0
 */

(function($) {
    'use strict';

    /**
     * S3 Direct Upload Handler
     */
    class H3TM_S3_Upload {
        constructor(options = {}) {
            this.options = {
                fileInputId: 'tour-file-input',
                uploadButtonId: 'upload-button',
                progressBarId: 'upload-progress',
                statusId: 'upload-status',
                maxFileSize: 1073741824, // 1GB default
                allowedTypes: ['application/zip', 'application/x-zip-compressed'],
                chunkSize: 5242880, // 5MB chunks
                maxRetries: 3,
                retryDelay: 1000,
                ...options
            };

            this.currentUpload = null;
            this.isUploading = false;

            this.init();
        }

        /**
         * Initialize the upload handler
         */
        init() {
            this.bindEvents();
            this.setupProgressTracking();
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            const fileInput = document.getElementById(this.options.fileInputId);
            const uploadButton = document.getElementById(this.options.uploadButtonId);

            if (fileInput) {
                fileInput.addEventListener('change', (e) => this.handleFileSelection(e));
            }

            if (uploadButton) {
                uploadButton.addEventListener('click', (e) => this.handleUploadClick(e));
            }

            // Handle page unload during upload
            window.addEventListener('beforeunload', (e) => {
                if (this.isUploading) {
                    e.preventDefault();
                    e.returnValue = 'Upload in progress. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
        }

        /**
         * Setup progress tracking elements
         */
        setupProgressTracking() {
            const progressContainer = document.getElementById('upload-progress-container');
            if (progressContainer) {
                progressContainer.innerHTML = `
                    <div id="${this.options.progressBarId}" class="h3tm-progress-bar" style="display: none;">
                        <div class="h3tm-progress-fill"></div>
                        <div class="h3tm-progress-text">0%</div>
                    </div>
                    <div id="${this.options.statusId}" class="h3tm-upload-status"></div>
                `;
            }
        }

        /**
         * Handle file selection
         */
        handleFileSelection(event) {
            const file = event.target.files[0];
            if (!file) return;

            const validation = this.validateFile(file);
            if (!validation.valid) {
                this.showError(validation.error);
                return;
            }

            this.updateStatus(`Selected: ${file.name} (${this.formatFileSize(file.size)})`);
            this.enableUploadButton(true);
        }

        /**
         * Handle upload button click
         */
        handleUploadClick(event) {
            event.preventDefault();

            if (this.isUploading) {
                this.cancelUpload();
                return;
            }

            const fileInput = document.getElementById(this.options.fileInputId);
            const file = fileInput.files[0];

            if (!file) {
                this.showError('Please select a file to upload.');
                return;
            }

            this.startUpload(file);
        }

        /**
         * Validate selected file
         */
        validateFile(file) {
            // Check file size
            if (file.size > this.options.maxFileSize) {
                return {
                    valid: false,
                    error: `File size exceeds maximum allowed size of ${this.formatFileSize(this.options.maxFileSize)}`
                };
            }

            if (file.size < 1024) {
                return {
                    valid: false,
                    error: 'File is too small to be a valid tour.'
                };
            }

            // Check file type
            if (!this.options.allowedTypes.includes(file.type)) {
                return {
                    valid: false,
                    error: 'Invalid file type. Only ZIP files are allowed.'
                };
            }

            // Check file extension
            const extension = file.name.split('.').pop().toLowerCase();
            if (extension !== 'zip') {
                return {
                    valid: false,
                    error: 'Invalid file extension. Only .zip files are allowed.'
                };
            }

            return { valid: true };
        }

        /**
         * Start the S3 direct upload process
         */
        async startUpload(file) {
            try {
                this.isUploading = true;
                this.updateUploadButton('Cancel Upload');
                this.showProgress(0);
                this.updateStatus('Preparing upload...');

                // Request presigned URL from server
                const uploadData = await this.requestPresignedUrl(file);

                this.currentUpload = {
                    file: file,
                    sessionId: uploadData.session_id,
                    objectKey: uploadData.object_key,
                    uploadUrl: uploadData.upload_url,
                    fields: uploadData.fields,
                    expires: uploadData.expires
                };

                // Upload directly to S3
                await this.uploadToS3(file, uploadData);

                // Notify server of completion
                await this.notifyUploadComplete(true);

                this.handleUploadSuccess();

            } catch (error) {
                console.error('Upload failed:', error);
                this.handleUploadError(error);

                // Notify server of failure
                if (this.currentUpload) {
                    try {
                        await this.notifyUploadComplete(false, error.message);
                    } catch (notifyError) {
                        console.error('Failed to notify server of upload failure:', notifyError);
                    }
                }
            } finally {
                this.isUploading = false;
                this.updateUploadButton('Upload Tour');
                this.currentUpload = null;
            }
        }

        /**
         * Request presigned URL from WordPress backend
         */
        async requestPresignedUrl(file) {
            const formData = new FormData();
            formData.append('action', 'h3tm_get_s3_upload_url');
            formData.append('filename', file.name);
            formData.append('filesize', file.size);
            formData.append('nonce', h3tm_s3_upload.nonce);

            const response = await fetch(h3tm_s3_upload.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.data?.message || 'Failed to get upload URL');
            }

            return data.data;
        }

        /**
         * Upload file directly to S3 using presigned URL
         */
        async uploadToS3(file, uploadData) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();

                // Setup progress tracking
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const progress = Math.round((e.loaded / e.total) * 100);
                        this.showProgress(progress);
                        this.updateStatus(`Uploading: ${progress}%`);
                    }
                });

                // Handle completion
                xhr.addEventListener('load', () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        this.updateStatus('Upload completed, processing...');
                        resolve();
                    } else {
                        reject(new Error(`S3 upload failed: ${xhr.status} ${xhr.statusText}`));
                    }
                });

                // Handle errors
                xhr.addEventListener('error', () => {
                    reject(new Error('Network error during upload'));
                });

                xhr.addEventListener('abort', () => {
                    reject(new Error('Upload cancelled'));
                });

                // Prepare form data for S3
                const formData = new FormData();

                // Add S3 required fields
                Object.keys(uploadData.fields).forEach(key => {
                    formData.append(key, uploadData.fields[key]);
                });

                // Add the file (must be last)
                formData.append('file', file);

                // Start upload
                xhr.open('POST', uploadData.upload_url);
                xhr.send(formData);
            });
        }

        /**
         * Notify server of upload completion
         */
        async notifyUploadComplete(success, errorMessage = '') {
            const formData = new FormData();
            formData.append('action', 'h3tm_s3_upload_complete');
            formData.append('session_id', this.currentUpload.sessionId);
            formData.append('success', success ? '1' : '0');
            formData.append('error', errorMessage);
            formData.append('nonce', h3tm_s3_upload.nonce);

            const response = await fetch(h3tm_s3_upload.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.data?.message || 'Server notification failed');
            }

            return data.data;
        }

        /**
         * Cancel current upload
         */
        cancelUpload() {
            if (this.currentUpload && this.currentUpload.xhr) {
                this.currentUpload.xhr.abort();
            }

            this.isUploading = false;
            this.updateStatus('Upload cancelled');
            this.hideProgress();
            this.updateUploadButton('Upload Tour');
        }

        /**
         * Handle successful upload
         */
        handleUploadSuccess() {
            this.updateStatus('Upload completed successfully!', 'success');
            this.showProgress(100);

            // Auto-hide progress after 3 seconds
            setTimeout(() => {
                this.hideProgress();
            }, 3000);

            // Trigger custom event
            document.dispatchEvent(new CustomEvent('h3tm-upload-success', {
                detail: {
                    sessionId: this.currentUpload.sessionId,
                    objectKey: this.currentUpload.objectKey,
                    filename: this.currentUpload.file.name
                }
            }));

            // Refresh tour list or redirect as needed
            if (typeof h3tm_s3_upload.redirect_url !== 'undefined') {
                setTimeout(() => {
                    window.location.href = h3tm_s3_upload.redirect_url;
                }, 2000);
            }
        }

        /**
         * Handle upload error
         */
        handleUploadError(error) {
            console.error('Upload error:', error);

            const errorMessage = error.message || 'Upload failed due to an unknown error';
            this.showError(`Upload failed: ${errorMessage}`);
            this.hideProgress();

            // Trigger custom event
            document.dispatchEvent(new CustomEvent('h3tm-upload-error', {
                detail: {
                    error: errorMessage,
                    sessionId: this.currentUpload?.sessionId
                }
            }));
        }

        /**
         * Update upload status display
         */
        updateStatus(message, type = 'info') {
            const statusEl = document.getElementById(this.options.statusId);
            if (statusEl) {
                statusEl.textContent = message;
                statusEl.className = `h3tm-upload-status status-${type}`;
            }
        }

        /**
         * Show upload progress
         */
        showProgress(percentage) {
            const progressBar = document.getElementById(this.options.progressBarId);
            if (progressBar) {
                progressBar.style.display = 'block';

                const fill = progressBar.querySelector('.h3tm-progress-fill');
                const text = progressBar.querySelector('.h3tm-progress-text');

                if (fill) fill.style.width = `${percentage}%`;
                if (text) text.textContent = `${percentage}%`;
            }
        }

        /**
         * Hide upload progress
         */
        hideProgress() {
            const progressBar = document.getElementById(this.options.progressBarId);
            if (progressBar) {
                progressBar.style.display = 'none';
            }
        }

        /**
         * Show error message
         */
        showError(message) {
            this.updateStatus(message, 'error');

            // Also log to console for debugging
            console.error('H3TM Upload Error:', message);
        }

        /**
         * Update upload button state
         */
        updateUploadButton(text) {
            const button = document.getElementById(this.options.uploadButtonId);
            if (button) {
                button.textContent = text;
                button.classList.toggle('uploading', this.isUploading);
            }
        }

        /**
         * Enable/disable upload button
         */
        enableUploadButton(enabled) {
            const button = document.getElementById(this.options.uploadButtonId);
            if (button) {
                button.disabled = !enabled;
                button.classList.toggle('disabled', !enabled);
            }
        }

        /**
         * Format file size for display
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';

            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));

            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        /**
         * Get current upload status
         */
        getUploadStatus() {
            return {
                isUploading: this.isUploading,
                currentUpload: this.currentUpload,
                progress: this.currentProgress || 0
            };
        }
    }

    /**
     * Initialize S3 upload when document is ready
     */
    $(document).ready(function() {
        // Only initialize if the required elements exist
        if (document.getElementById('tour-file-input')) {
            window.h3tm_s3_uploader = new H3TM_S3_Upload(window.h3tm_upload_options || {});
        }
    });

    // Export for global access
    window.H3TM_S3_Upload = H3TM_S3_Upload;

})(jQuery);