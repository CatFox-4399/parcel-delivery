/**
 * upload.js — Delivery Photo Capture & Upload Module
 *
 * Handles:
 *  - File input / camera capture on mobile
 *  - Client-side image compression via Canvas API
 *  - AJAX upload to /api/upload_photo.php with progress feedback
 */

'use strict';

const PhotoUpload = (() => {

    const UPLOAD_URL     = App.baseUrl + '/api/upload_photo.php';
    const MAX_DIMENSION  = 1280;     // px — resize if image exceeds this
    const JPEG_QUALITY   = 0.75;     // 75% JPEG quality after compression

    let fileInput    = null;
    let previewWrap  = null;
    let previewImg   = null;
    let progressWrap = null;
    let progressBar  = null;
    let progressLbl  = null;
    let compressedBlob = null;  // Compressed image ready for upload

    /**
     * Initialise the upload module.
     *
     * @param {object} config
     * @param {string} config.inputId      ID of <input type="file">
     * @param {string} config.previewId    ID of the preview wrapper element
     * @param {string} config.progressId   ID of the progress bar container
     */
    function init({ inputId, previewId, progressId } = {}) {
        fileInput    = document.getElementById(inputId   ?? 'photoInput');
        previewWrap  = document.getElementById(previewId ?? 'photoPreview');
        progressWrap = document.getElementById(progressId ?? 'uploadProgress');

        if (previewWrap) {
            previewImg = previewWrap.querySelector('img');
        }

        if (progressWrap) {
            progressBar = progressWrap.querySelector('.progress-bar');
            progressLbl = progressWrap.querySelector('.progress-label');
        }

        if (fileInput) {
            fileInput.addEventListener('change', handleFileSelect);
        }

        // Wire "remove photo" button if present
        const removeBtn = document.getElementById('removePhotoBtn');
        if (removeBtn) {
            removeBtn.addEventListener('click', clearPreview);
        }
    }

    /**
     * Called when the user selects a file.
     */
    function handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowed.includes(file.type)) {
            showToast('Only JPEG, PNG, and WebP images are allowed.', 'error');
            fileInput.value = '';
            return;
        }

        if (file.size > 20 * 1024 * 1024) {
            showToast('Image is too large (max 20 MB before compression).', 'error');
            fileInput.value = '';
            return;
        }

        compressImage(file).then(blob => {
            compressedBlob = blob;
            showPreview(blob);
        }).catch(err => {
            console.error('[PhotoUpload] Compression error:', err);
            // Fall back to original file
            compressedBlob = file;
            showPreview(file);
        });
    }

    /**
     * Compress an image file using Canvas.
     * Resizes to MAX_DIMENSION on the longest side, then encodes as JPEG.
     *
     * @param {File|Blob} file
     * @returns {Promise<Blob>}
     */
    function compressImage(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);

            img.onload = function () {
                URL.revokeObjectURL(url);

                let { width, height } = img;

                // Scale down if needed
                if (width > MAX_DIMENSION || height > MAX_DIMENSION) {
                    if (width >= height) {
                        height = Math.round((height / width) * MAX_DIMENSION);
                        width  = MAX_DIMENSION;
                    } else {
                        width  = Math.round((width / height) * MAX_DIMENSION);
                        height = MAX_DIMENSION;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width  = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(blob => {
                    if (!blob) {
                        reject(new Error('Canvas toBlob returned null'));
                        return;
                    }
                    console.log(`[PhotoUpload] Compressed: ${(file.size / 1024).toFixed(1)}KB → ${(blob.size / 1024).toFixed(1)}KB`);
                    resolve(blob);
                }, 'image/jpeg', JPEG_QUALITY);
            };

            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Failed to load image for compression'));
            };

            img.src = url;
        });
    }

    /**
     * Show the compressed image preview.
     * @param {Blob} blob
     */
    function showPreview(blob) {
        if (!previewWrap || !previewImg) return;

        const url = URL.createObjectURL(blob);
        previewImg.src = url;
        previewImg.onload = () => URL.revokeObjectURL(url);

        // Show size in preview badge
        const badge = previewWrap.querySelector('.photo-preview-badge');
        if (badge) {
            badge.textContent = `${(blob.size / 1024).toFixed(0)} KB`;
        }

        previewWrap.style.display = 'block';
    }

    /**
     * Clear the selected file and hide the preview.
     */
    function clearPreview() {
        compressedBlob = null;
        if (fileInput) fileInput.value = '';
        if (previewWrap) previewWrap.style.display = 'none';
        if (previewImg)  previewImg.src = '';
    }

    /**
     * Upload the compressed photo for a given parcel.
     *
     * @param {number|string} parcelId
     * @returns {Promise<{success: boolean, message: string, filename?: string}>}
     */
    function uploadPhoto(parcelId) {
        if (!compressedBlob) {
            return Promise.resolve({ success: false, message: 'No photo selected.' });
        }

        const formData = new FormData();
        formData.append('photo',      compressedBlob, 'proof_' + Date.now() + '.jpg');
        formData.append('parcel_id',  parcelId);
        formData.append('csrf_token', App.csrfToken);

        // Show progress bar
        if (progressWrap) progressWrap.style.display = 'block';
        setProgress(0);

        return uploadWithProgress(UPLOAD_URL, formData)
            .then(res => {
                if (res.success) {
                    setProgress(100);
                    showToast('Photo uploaded successfully.', 'success');
                    clearPreview();
                } else {
                    setProgress(0);
                    showToast(res.message || 'Upload failed.', 'error');
                }
                return res;
            })
            .catch(err => {
                setProgress(0);
                showToast('Upload failed. Please try again.', 'error');
                console.error('[PhotoUpload] Upload error:', err);
                return { success: false, message: err.message };
            });
    }

    /**
     * Upload using XMLHttpRequest to support upload progress events.
     * @param {string}   url
     * @param {FormData} formData
     * @returns {Promise<object>}
     */
    function uploadWithProgress(url, formData) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();

            xhr.open('POST', url, true);
            xhr.setRequestHeader('X-CSRF-Token', App.csrfToken);

            xhr.upload.addEventListener('progress', e => {
                if (e.lengthComputable) {
                    setProgress(Math.round((e.loaded / e.total) * 90)); // cap at 90% until response
                }
            });

            xhr.onload = function () {
                try {
                    resolve(JSON.parse(xhr.responseText));
                } catch {
                    reject(new Error('Invalid server response'));
                }
            };

            xhr.onerror = () => reject(new Error('Network error during upload'));
            xhr.send(formData);
        });
    }

    function setProgress(pct) {
        if (progressBar) progressBar.style.width = `${pct}%`;
        if (progressLbl) progressLbl.textContent  = `${pct}%`;
    }

    /**
     * Returns true if a photo is selected and ready.
     */
    function hasPhoto() {
        return compressedBlob !== null;
    }

    return { init, uploadPhoto, clearPreview, hasPhoto };

})();
