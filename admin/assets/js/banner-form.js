(function (window, document) {
    'use strict';

    if (window.RedAdminBannerForm) {
        window.RedAdminBannerForm.activate();
        return;
    }

    var MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    var ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    var ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    function find(form, selector) {
        return form ? form.querySelector(selector) : null;
    }

    function formMode(form) {
        return form && form.getAttribute('data-banner-mode') === 'edit' ? 'edit' : 'create';
    }

    function setMessage(form, message, state) {
        var messageBox = find(form, '[data-banner-message]');

        if (!messageBox) {
            return;
        }

        messageBox.textContent = message || '';
        messageBox.hidden = !message;
        messageBox.setAttribute('data-state', state || 'info');
    }

    function setSaving(form, saving) {
        var saveButton = find(form, '[data-banner-save]');
        var label;
        var defaultLabel;

        if (!saveButton) {
            return;
        }

        label = saveButton.querySelector('[data-save-label]');
        defaultLabel = saveButton.getAttribute('data-default-label')
            || (formMode(form) === 'edit' ? 'Save changes' : 'Save banner');
        saveButton.disabled = saving;
        saveButton.setAttribute('aria-busy', saving ? 'true' : 'false');
        if (label) {
            label.textContent = saving ? 'Saving…' : defaultLabel;
        }
    }

    function fileExtension(fileName) {
        var parts = String(fileName || '').toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    }

    function validateImage(file) {
        if (!file) {
            return 'Choose an image to upload.';
        }
        if (file.size <= 0) {
            return 'The selected image is empty.';
        }
        if (file.size > MAX_IMAGE_BYTES) {
            return 'That image is larger than 2 MB.';
        }
        if (ALLOWED_IMAGE_TYPES.indexOf(file.type) === -1 && ALLOWED_IMAGE_EXTENSIONS.indexOf(fileExtension(file.name)) === -1) {
            return 'Choose a JPG, PNG, or GIF image.';
        }

        return '';
    }

    function parseUploadResponse(xhr) {
        try {
            return xhr.responseText ? JSON.parse(xhr.responseText) : {};
        } catch (error) {
            return {status: xhr.responseText || 'The upload server returned an invalid response.'};
        }
    }

    function updateUploadPreview(uploader, file) {
        var preview = uploader.querySelector('[data-upload-preview]');
        var image = uploader.querySelector('[data-upload-preview-image]');
        var fileName = uploader.querySelector('[data-upload-file-name]');
        var reader;

        if (!preview || !image || !fileName) {
            return;
        }

        fileName.textContent = file.name;
        preview.hidden = false;
        reader = new FileReader();
        reader.onload = function (event) {
            image.src = event.target.result;
            image.alt = 'Preview of ' + file.name;
        };
        reader.readAsDataURL(file);
    }

    function synchronizeEditedImage(uploader, response) {
        var field = uploader.getAttribute('data-upload-field');
        var storedName = response && typeof response.stored_name === 'string' ? response.stored_name : '';
        var valueInput = uploader.querySelector('[data-upload-value]');
        var removeInput = uploader.querySelector('[data-remove-image]');
        var currentMedia = uploader.querySelector('[data-current-media]');
        var currentImage = uploader.querySelector('[data-current-image]');
        var currentName = uploader.querySelector('[data-current-name]');
        var previewImage = uploader.querySelector('[data-upload-preview-image]');

        if (!field || !storedName || !valueInput || valueInput.name !== field) {
            return false;
        }

        valueInput.value = storedName;
        valueInput.disabled = false;
        if (field === 'Photo0') {
            Array.prototype.forEach.call(uploader.querySelectorAll('[data-banner-photo-value]'), function (photoInput) {
                if (photoInput !== valueInput) {
                    photoInput.disabled = true;
                }
            });
            Array.prototype.forEach.call(uploader.querySelectorAll('[data-banner-photo-delete]'), function (deleteInput) {
                deleteInput.checked = false;
                deleteInput.disabled = deleteInput !== removeInput;
            });
            Array.prototype.forEach.call(uploader.querySelectorAll('[data-banner-photo-index]'), function (photoCard, index) {
                if (index > 0) {
                    photoCard.hidden = true;
                }
            });
        }

        if (removeInput) {
            removeInput.checked = false;
            removeInput.disabled = false;
            removeInput.dispatchEvent(new window.Event('change'));
        }
        if (currentName) {
            currentName.textContent = storedName;
        }
        if (currentImage && previewImage && previewImage.src) {
            currentImage.src = previewImage.src;
            currentImage.alt = 'Current image ' + storedName;
        }
        if (currentMedia) {
            currentMedia.hidden = false;
            currentMedia.classList.remove('is-marked-for-removal');
        }

        return true;
    }

    function initializeUploader(form, uploader) {
        var input = uploader.querySelector('[data-upload-input]');
        var browseButton = uploader.querySelector('[data-upload-browse]');
        var dropzone = uploader.querySelector('[data-upload-dropzone]');
        var status = uploader.querySelector('[data-upload-status]');
        var progress = uploader.querySelector('[data-upload-progress]');
        var uploadUrl = uploader.getAttribute('data-upload-url');

        if (!input || !browseButton || !dropzone || !status || !progress || !uploadUrl) {
            return;
        }

        function setUploadStatus(message, state) {
            var dropzoneStatus = uploader.querySelector('.red-admin-banner-queue-message');
            status.textContent = message;
            status.setAttribute('data-state', state || 'info');
            if (dropzoneStatus) {
                dropzoneStatus.textContent = message;
                dropzoneStatus.setAttribute('data-state', state || 'info');
            }
        }

        function finishUpload() {
            uploader.setAttribute('aria-busy', 'false');
            input.disabled = false;
            browseButton.disabled = false;
            form._redBannerUploadsInFlight = Math.max(0, (form._redBannerUploadsInFlight || 1) - 1);
        }

        function upload(file) {
            var validationMessage = validateImage(file);
            var xhr;
            var payload;
            var csrfInput;

            if (validationMessage) {
                uploader.classList.remove('is-complete');
                uploader.classList.add('has-error');
                setUploadStatus(validationMessage, 'error');
                setMessage(form, validationMessage, 'error');
                return;
            }

            uploader.classList.remove('has-error', 'is-complete');
            uploader.setAttribute('aria-busy', 'true');
            input.disabled = true;
            browseButton.disabled = true;
            progress.style.width = '0%';
            setUploadStatus('Uploading ' + file.name + '…', 'progress');
            updateUploadPreview(uploader, file);
            form._redBannerUploadsInFlight = (form._redBannerUploadsInFlight || 0) + 1;

            payload = new FormData();
            payload.append('pic', file, file.name);
            xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl, true);
            csrfInput = find(form, 'input[name="csrf_token"]');
            if (csrfInput && csrfInput.value) {
                xhr.setRequestHeader('X-CSRF-Token', csrfInput.value);
            } else if (window.RED_CSRF_TOKEN) {
                xhr.setRequestHeader('X-CSRF-Token', window.RED_CSRF_TOKEN);
            }

            xhr.upload.addEventListener('progress', function (event) {
                var percentage;
                if (!event.lengthComputable) {
                    return;
                }
                percentage = Math.round((event.loaded / event.total) * 100);
                progress.style.width = percentage + '%';
            });

            xhr.addEventListener('load', function () {
                var response = parseUploadResponse(xhr);
                var successful = xhr.status >= 200 && xhr.status < 300;

                if (successful && !synchronizeEditedImage(uploader, response)) {
                    successful = false;
                    response.status = 'The server did not confirm the stored image name.';
                }

                if (successful) {
                    progress.style.width = '100%';
                    uploader.classList.add('is-complete');
                    setUploadStatus('Uploaded successfully', 'success');
                    setMessage(form, 'Image replaced successfully. The saved filename is synchronized with this form.', 'success');
                } else {
                    progress.style.width = '0%';
                    uploader.classList.add('has-error');
                    setUploadStatus(response.message || response.status || 'The image could not be uploaded.', 'error');
                    setMessage(form, 'The image could not be uploaded. Review the message and try again.', 'error');
                }

                input.value = '';
                finishUpload();
            });

            xhr.addEventListener('error', function () {
                progress.style.width = '0%';
                uploader.classList.add('has-error');
                setUploadStatus('The upload could not reach the server.', 'error');
                setMessage(form, 'The image upload could not reach the server.', 'error');
                input.value = '';
                finishUpload();
            });

            xhr.send(payload);
        }

        browseButton.addEventListener('click', function () {
            input.click();
        });
        input.addEventListener('change', function () {
            if (input.files && input.files.length) {
                upload(input.files[0]);
            }
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('is-dragging');
            });
        });
        ['dragleave', 'dragend'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('is-dragging');
            });
        });
        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.remove('is-dragging');
            if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                upload(event.dataTransfer.files[0]);
            }
        });
    }

    function initializeAdvancedPanel(form) {
        var details = find(form, '[data-banner-advanced]');
        var storageKey = 'red-admin-' + formMode(form) + '-banner-advanced-open';

        if (!details) {
            return;
        }

        try {
            details.open = window.sessionStorage.getItem(storageKey) === 'true';
            details.addEventListener('toggle', function () {
                window.sessionStorage.setItem(storageKey, details.open ? 'true' : 'false');
            });
        } catch (error) {
            // The native disclosure remains functional without session storage.
        }
    }

    function initializeLinkNavigator(form) {
        var navigator = find(form, '#LinkNavigator');
        var link = find(form, '#Link');

        if (!navigator || !link) {
            return;
        }

        navigator.addEventListener('change', function () {
            if (navigator.value) {
                link.value = navigator.value;
            }
        });
    }

    function initializeDateControls(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-banner-date]'), function (dateInput) {
            var field = dateInput.closest('.red-admin-field');
            var payload = field ? field.querySelector('[data-date-payload]') : null;
            var originalDate = dateInput.getAttribute('data-original-date');

            if (!payload || originalDate === null) {
                return;
            }

            function synchronizeDatePayload() {
                var changed = dateInput.value !== originalDate;
                payload.disabled = !changed;
                payload.value = changed ? dateInput.value : '';
            }

            dateInput.addEventListener('input', synchronizeDatePayload);
            dateInput.addEventListener('change', synchronizeDatePayload);
            synchronizeDatePayload();
        });
    }

    function initializeRemovalChoices(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-remove-image]'), function (removeInput) {
            var currentMedia = removeInput.closest('[data-current-media]');

            function updateRemovalState() {
                if (currentMedia) {
                    currentMedia.classList.toggle('is-marked-for-removal', removeInput.checked);
                }
            }

            removeInput.addEventListener('change', updateRemovalState);
            updateRemovalState();
        });
    }

    function bannerDateControl(form, kind, fallbackName) {
        return find(form, '[data-banner-date="' + kind + '"]') || find(form, '[name="' + fallbackName + '"]');
    }

    function validateForm(form) {
        var title = find(form, '[name="Title"]');
        var titleError = find(form, '[data-title-error]');
        var startDate = bannerDateControl(form, 'start', 'StartDate');
        var expirationDate = bannerDateControl(form, 'expiration', 'ExpDate');
        var advanced = find(form, '[data-banner-advanced]');

        if (!title || !String(title.value || '').trim()) {
            if (title) {
                title.setAttribute('aria-invalid', 'true');
                title.focus();
            }
            if (titleError) {
                titleError.hidden = false;
            }
            setMessage(form, 'Add a title before saving the banner.', 'error');
            return false;
        }

        title.removeAttribute('aria-invalid');
        if (titleError) {
            titleError.hidden = true;
        }

        if (startDate && expirationDate && startDate.value && expirationDate.value && expirationDate.value < startDate.value) {
            if (advanced) {
                advanced.open = true;
            }
            expirationDate.setAttribute('aria-invalid', 'true');
            expirationDate.focus();
            setMessage(form, 'The expiration date must be on or after the start date.', 'error');
            return false;
        }
        if (expirationDate) {
            expirationDate.removeAttribute('aria-invalid');
        }

        if ((form._redBannerUploadsInFlight || 0) > 0) {
            setMessage(form, 'Wait for the image upload to finish before saving.', 'warning');
            return false;
        }

        return true;
    }

    function submitForm(form) {
        var mode = formMode(form);
        var submitUrl = form.getAttribute('data-submit-url')
            || (mode === 'edit' ? '/admin/bin/update_gallery.php' : '/admin/bin/insert_gallery.php');

        if (!validateForm(form)) {
            return false;
        }

        setSaving(form, true);
        setMessage(form, mode === 'edit' ? 'Saving banner changes…' : 'Saving banner details…', 'progress');

        if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
            setSaving(form, false);
            setMessage(form, 'The banner could not be saved because the administrator request tools are unavailable.', 'error');
            return false;
        }

        window.jQuery.ajax({
            type: 'POST',
            url: submitUrl,
            data: window.jQuery(form).serialize(),
            success: function (data) {
                var response = String(data).trim();
                var accepted = response === 'yes' || response === 'yesyes' || response === 'noyes' || response === 'yesno';
                var uploadQueued;

                if (!accepted) {
                    setSaving(form, false);
                    setMessage(form, mode === 'edit' ? 'The changes could not be saved. Review the fields and try again.' : 'The banner could not be added. Review the fields and try again.', 'error');
                    return;
                }

                if (mode === 'edit') {
                    setMessage(form, 'Changes saved. Refreshing the editor…', 'success');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 650);
                    return;
                }

                setMessage(form, 'Banner details saved. Uploading selected images…', 'progress');
                uploadQueued = typeof window.redGalleryCreateUploadQueued === 'function'
                    ? window.redGalleryCreateUploadQueued()
                    : Promise.resolve();
                uploadQueued.then(function () {
                    setMessage(form, 'Banner and selected images saved. Refreshing the editor…', 'success');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 650);
                }).catch(function (error) {
                    setSaving(form, false);
                    setMessage(form, 'The banner details were saved, but an image failed to upload: ' + error.message, 'error');
                });
            },
            error: function () {
                setSaving(form, false);
                setMessage(form, 'The save request could not reach the server. Try again.', 'error');
            }
        });

        return false;
    }

    function deleteBanner(form) {
        var deleteButton = find(form, '[data-banner-delete]');
        var recordId = find(form, 'input[name="RecordID"]');
        var artRecordId = find(form, 'input[name="ArtRecordID"]');
        var csrfInput = find(form, 'input[name="csrf_token"]');
        var deleteUrl = form.getAttribute('data-delete-url') || '/admin/bin/delete_label.php';

        if (!deleteButton || !recordId || !artRecordId || !csrfInput) {
            return false;
        }
        if (!window.confirm('Delete this banner permanently? This action cannot be undone.')) {
            return false;
        }
        if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
            setMessage(form, 'The banner could not be deleted because the administrator request tools are unavailable.', 'error');
            return false;
        }

        deleteButton.disabled = true;
        deleteButton.setAttribute('aria-busy', 'true');
        setMessage(form, 'Deleting banner…', 'progress');

        window.jQuery.ajax({
            type: 'POST',
            url: deleteUrl,
            data: {
                RecordID: recordId.value,
                ArtRecordID: artRecordId.value,
                T: 'gal',
                csrf_token: csrfInput.value
            },
            success: function (data) {
                if (String(data).trim() === 'yesyes') {
                    setMessage(form, 'Banner deleted. Refreshing the content list…', 'success');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 650);
                    return;
                }

                deleteButton.disabled = false;
                deleteButton.setAttribute('aria-busy', 'false');
                setMessage(form, 'The banner could not be deleted. Try again.', 'error');
            },
            error: function () {
                deleteButton.disabled = false;
                deleteButton.setAttribute('aria-busy', 'false');
                setMessage(form, 'The delete request could not reach the server. Try again.', 'error');
            }
        });

        return false;
    }

    function initForm(form) {
        var deleteButton;

        if (!form || form.getAttribute('data-red-banner-ready') === 'true') {
            return;
        }

        form.setAttribute('data-red-banner-ready', 'true');
        form._redBannerUploadsInFlight = 0;
        initializeAdvancedPanel(form);
        initializeLinkNavigator(form);
        initializeDateControls(form);
        initializeRemovalChoices(form);
        Array.prototype.forEach.call(form.querySelectorAll('[data-banner-upload]'), function (uploader) {
            initializeUploader(form, uploader);
        });

        deleteButton = find(form, '[data-banner-delete]');
        if (deleteButton) {
            deleteButton.addEventListener('click', function () {
                deleteBanner(form);
            });
        }
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('form.red-admin-banner-form'), initForm);
    }

    function activate() {
        window.run_insert_gallery = function (form) {
            return submitForm(form);
        };
        window.run_update_gallery = function (form) {
            return submitForm(form);
        };
        init();
    }

    window.RedAdminBannerForm = {
        activate: activate,
        init: init,
        submit: submitForm,
        remove: deleteBanner
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        window.setTimeout(activate, 0);
    }
}(window, document));
