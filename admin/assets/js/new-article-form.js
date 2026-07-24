(function (window, document) {
    'use strict';

    if (window.RedAdminArticleForm) {
        window.RedAdminArticleForm.init();
        return;
    }

    var MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    var ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    var ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    function find(form, selector) {
        return form ? form.querySelector(selector) : null;
    }

    function formMode(form) {
        return form && form.getAttribute('data-article-mode') === 'edit' ? 'edit' : 'create';
    }

    function setMessage(form, message, state) {
        var messageBox = find(form, '[data-article-message]')
            || find(form, '#msggbox_insert_content')
            || find(form, '#msggbox_update_content');

        if (!messageBox) {
            return;
        }

        messageBox.textContent = message || '';
        messageBox.hidden = !message;
        messageBox.setAttribute('data-state', state || 'info');
    }

    function setSaving(form, saving) {
        var saveButton = find(form, '[data-article-save]') || find(form, '#save');
        var label;
        var defaultLabel;

        if (!saveButton) {
            return;
        }

        label = saveButton.querySelector('[data-save-label]');
        defaultLabel = saveButton.getAttribute('data-default-label')
            || (formMode(form) === 'edit' ? 'Save changes' : 'Save article');
        saveButton.disabled = saving;
        saveButton.setAttribute('aria-busy', saving ? 'true' : 'false');
        if (label) {
            label.textContent = saving ? 'Saving…' : defaultLabel;
        }
    }

    function syncEditors() {
        if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
            window.tinymce.triggerSave();
        }
    }

    function removeExistingEditor(id) {
        var existing;

        if (!window.tinymce || typeof window.tinymce.get !== 'function') {
            return;
        }

        existing = window.tinymce.get(id);
        if (existing && typeof existing.remove === 'function') {
            existing.remove();
        }
    }

    function editorConfig(selector, height, onReady) {
        return {
            selector: selector,
            height: height,
            menubar: false,
            statusbar: true,
            resize: true,
            browser_spellcheck: true,
            convert_urls: false,
            image_advtab: true,
            plugins: [
                'advlist autolink lists link image charmap preview anchor',
                'searchreplace visualblocks code fullscreen insertdatetime media',
                'table paste textcolor wordcount'
            ],
            toolbar: 'undo redo | styleselect | bold italic underline | bullist numlist | alignleft aligncenter alignright | link image | removeformat code fullscreen',
            style_formats: [
                {title: 'Heading 1', format: 'h1'},
                {title: 'Heading 2', format: 'h2'},
                {title: 'Heading 3', format: 'h3'},
                {title: 'Heading 4', format: 'h4'},
                {title: 'Paragraph', format: 'p'},
                {title: 'Blockquote', format: 'blockquote'}
            ],
            setup: function (editor) {
                editor.on('init', function () {
                    onReady(editor.id);
                });
            }
        };
    }

    function editorFields(form) {
        var fields = form.querySelectorAll('[data-article-editor]');

        if (fields.length) {
            return fields;
        }

        return form.querySelectorAll('#ShortDesc, #LongDesc');
    }

    function scopeEditorIdentity(form, field, index) {
        var originalId = field.getAttribute('data-editor-key') || field.id || ('editor-' + index);
        var scopedId = form.id + '-' + originalId;
        var label;

        field.setAttribute('data-editor-key', originalId);
        if (field.id === scopedId) {
            return;
        }

        label = field.id ? find(form, 'label[for="' + field.id + '"]') : null;
        field.id = scopedId;
        if (label) {
            label.setAttribute('for', scopedId);
        }
    }

    function initializeEditors(form) {
        var status = find(form, '[data-editor-status]');
        var fields = editorFields(form);
        var readyEditors = {};
        var attempts = 0;

        Array.prototype.forEach.call(fields, function (field, index) {
            scopeEditorIdentity(form, field, index);
        });

        function showFallback(message) {
            if (!status) {
                return;
            }

            status.textContent = message;
            status.setAttribute('data-state', 'warning');
        }

        function markReady(id) {
            var allReady = true;

            readyEditors[id] = true;
            Array.prototype.forEach.call(fields, function (field) {
                if (!readyEditors[field.id]) {
                    allReady = false;
                }
            });

            if (status && allReady) {
                status.textContent = 'Rich text tools ready';
                status.setAttribute('data-state', 'ready');
            }
        }

        function start() {
            attempts += 1;

            if (!window.tinymce || typeof window.tinymce.init !== 'function') {
                if (attempts < 30) {
                    window.setTimeout(start, 100);
                    return;
                }

                showFallback('Plain text mode — rich text tools did not load');
                return;
            }

            try {
                Array.prototype.forEach.call(fields, function (field) {
                    var height = parseInt(field.getAttribute('data-editor-height'), 10);

                    if (!field.id) {
                        return;
                    }

                    removeExistingEditor(field.id);
                    if (!height || height < 120) {
                        height = field.id === 'LongDesc' ? 320 : 180;
                    }
                    window.tinymce.init(editorConfig('#' + field.id, height, markReady));
                });
            } catch (error) {
                showFallback('Plain text mode — rich text tools could not start');
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('Article TinyMCE initialization failed.', error);
                }
            }
        }

        if (!fields.length) {
            showFallback('No rich text fields are available');
            return;
        }

        start();
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

    function synchronizeEditedImage(form, uploader, response) {
        var field = uploader.getAttribute('data-upload-field');
        var storedName = response && typeof response.stored_name === 'string' ? response.stored_name : '';
        var valueInput;
        var removeInput;
        var currentMedia;
        var currentImage;
        var currentName;
        var previewImage;

        if (formMode(form) !== 'edit' || !field || !storedName) {
            return formMode(form) !== 'edit';
        }

        valueInput = uploader.querySelector('[data-upload-value]');
        removeInput = uploader.querySelector('[data-remove-image]');
        currentMedia = uploader.querySelector('[data-current-media]');
        currentImage = uploader.querySelector('[data-current-image]');
        currentName = uploader.querySelector('[data-current-name]');
        previewImage = uploader.querySelector('[data-upload-preview-image]');

        if (!valueInput || valueInput.name !== field) {
            return false;
        }

        valueInput.value = storedName;
        if (removeInput) {
            removeInput.checked = false;
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
            status.textContent = message;
            status.setAttribute('data-state', state || 'info');
        }

        function finishUpload() {
            uploader.setAttribute('aria-busy', 'false');
            input.disabled = false;
            browseButton.disabled = false;
            form._redArticleUploadsInFlight = Math.max(0, (form._redArticleUploadsInFlight || 1) - 1);
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
                return;
            }

            uploader.classList.remove('has-error', 'is-complete');
            uploader.setAttribute('aria-busy', 'true');
            input.disabled = true;
            browseButton.disabled = true;
            progress.style.width = '0%';
            setUploadStatus('Uploading ' + file.name + '…', 'progress');
            updateUploadPreview(uploader, file);
            form._redArticleUploadsInFlight = (form._redArticleUploadsInFlight || 0) + 1;

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

                if (successful && !synchronizeEditedImage(form, uploader, response)) {
                    successful = false;
                    response.status = 'The server did not confirm the stored image name.';
                }

                if (successful) {
                    progress.style.width = '100%';
                    uploader.classList.add('is-complete');
                    setUploadStatus('Uploaded successfully', 'success');
                    setMessage(
                        form,
                        formMode(form) === 'edit'
                            ? 'Image replaced successfully. The saved filename is synchronized with this form.'
                            : 'Image uploaded. Save the article when you are ready.',
                        'success'
                    );
                } else {
                    progress.style.width = '0%';
                    uploader.classList.add('has-error');
                    setUploadStatus(response.status || 'The image could not be uploaded.', 'error');
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
        var details = find(form, '[data-article-advanced]');
        var storageKey = 'red-admin-' + formMode(form) + '-article-advanced-open';

        if (!details) {
            return;
        }

        try {
            details.open = window.sessionStorage.getItem(storageKey) === 'true';
            details.addEventListener('toggle', function () {
                window.sessionStorage.setItem(storageKey, details.open ? 'true' : 'false');
            });
        } catch (error) {
            // The disclosure remains fully functional when storage is unavailable.
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

    function legacyCopyText(value) {
        var copyField = document.createElement('textarea');
        var copied = false;

        copyField.value = value;
        copyField.setAttribute('readonly', 'readonly');
        copyField.style.position = 'fixed';
        copyField.style.top = '-9999px';
        copyField.style.left = '-9999px';
        document.body.appendChild(copyField);
        copyField.focus();
        copyField.select();

        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(copyField);
        return copied;
    }

    function setCopyFeedback(form, control, copied) {
        var label = control.querySelector('[data-copy-label]');
        var status = find(form, '[data-copy-status]');
        var defaultLabel = control.getAttribute('data-copy-default-label') || 'Copy';
        var message = copied
            ? 'Page address copied to the clipboard.'
            : 'The page address could not be copied. Try again.';

        window.clearTimeout(control._redCopyFeedbackTimer);
        control.classList.toggle('is-copied', copied);
        control.classList.toggle('has-copy-error', !copied);
        if (label) {
            label.textContent = copied ? 'Copied!' : 'Try again';
        }
        if (status) {
            status.textContent = '';
            window.setTimeout(function () {
                status.textContent = message;
            }, 0);
        }

        control._redCopyFeedbackTimer = window.setTimeout(function () {
            control.classList.remove('is-copied', 'has-copy-error');
            if (label) {
                label.textContent = defaultLabel;
            }
        }, 1800);
    }

    function copyPageLink(form, control) {
        var value = control.getAttribute('data-copy-value') || '';

        if (!value) {
            setCopyFeedback(form, control, false);
            return;
        }

        if (window.navigator.clipboard && typeof window.navigator.clipboard.writeText === 'function') {
            try {
                window.navigator.clipboard.writeText(value).then(function () {
                    setCopyFeedback(form, control, true);
                }, function () {
                    setCopyFeedback(form, control, legacyCopyText(value));
                });
                return;
            } catch (error) {
                setCopyFeedback(form, control, legacyCopyText(value));
                return;
            }
        }

        setCopyFeedback(form, control, legacyCopyText(value));
    }

    function initializePageCopyControls(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-copy-page-link]'), function (control) {
            control.addEventListener('click', function () {
                copyPageLink(form, control);
            });
        });
    }

    function initializeDateControls(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-article-date]'), function (dateInput) {
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

    function articleDateControl(form, kind, fallbackName) {
        return find(form, '[data-article-date="' + kind + '"]') || find(form, '[name="' + fallbackName + '"]');
    }

    function validateForm(form) {
        var title = find(form, '[name="Title"]');
        var titleError = find(form, '[data-title-error]');
        var startDate = articleDateControl(form, 'start', 'StartDate');
        var expirationDate = articleDateControl(form, 'expiration', 'ExpDate');
        var advanced = find(form, '[data-article-advanced]');

        if (!title || !String(title.value || '').trim()) {
            if (title) {
                title.setAttribute('aria-invalid', 'true');
                title.focus();
            }
            if (titleError) {
                titleError.hidden = false;
            }
            setMessage(form, 'Add a title before saving the article.', 'error');
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

        if ((form._redArticleUploadsInFlight || 0) > 0) {
            setMessage(form, 'Wait for the image upload to finish before saving.', 'warning');
            return false;
        }

        return true;
    }

    function submitForm(form) {
        var mode = formMode(form);
        var submitUrl = form.getAttribute('data-submit-url')
            || (mode === 'edit' ? '/admin/bin/update_content.php' : '/admin/bin/insert_content.php');

        if (!validateForm(form)) {
            return false;
        }

        syncEditors();
        setSaving(form, true);
        setMessage(form, 'Saving article…', 'progress');

        if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
            setSaving(form, false);
            setMessage(form, 'The article could not be saved because the administrator request tools are unavailable.', 'error');
            return false;
        }

        window.jQuery.ajax({
            type: 'POST',
            url: submitUrl,
            data: window.jQuery(form).serialize(),
            success: function (data) {
                if (String(data).trim() === 'yes') {
                    setMessage(form, mode === 'edit' ? 'Changes saved. Refreshing the editor…' : 'Article added. Refreshing the editor…', 'success');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 650);
                    return;
                }

                setSaving(form, false);
                setMessage(form, mode === 'edit' ? 'The changes could not be saved. Review the fields and try again.' : 'The article could not be added. Review the fields and try again.', 'error');
            },
            error: function () {
                setSaving(form, false);
                setMessage(form, 'The save request could not reach the server. Try again.', 'error');
            }
        });

        return false;
    }

    function deleteArticle(form) {
        var deleteButton = find(form, '[data-article-delete]');
        var recordId = find(form, 'input[name="RecordID"]');
        var csrfInput = find(form, 'input[name="csrf_token"]');
        var deleteUrl = form.getAttribute('data-delete-url') || '/admin/bin/delete_label.php';

        if (!deleteButton || !recordId || !csrfInput) {
            return false;
        }

        if (!window.confirm('Delete this article permanently? This action cannot be undone.')) {
            return false;
        }

        if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
            setMessage(form, 'The article could not be deleted because the administrator request tools are unavailable.', 'error');
            return false;
        }

        deleteButton.disabled = true;
        deleteButton.setAttribute('aria-busy', 'true');
        setMessage(form, 'Deleting article…', 'progress');

        window.jQuery.ajax({
            type: 'POST',
            url: deleteUrl,
            data: {
                RecordID: recordId.value,
                csrf_token: csrfInput.value
            },
            success: function (data) {
                if (String(data).trim() === 'yes') {
                    setMessage(form, 'Article deleted. Refreshing the content list…', 'success');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 650);
                    return;
                }

                deleteButton.disabled = false;
                deleteButton.setAttribute('aria-busy', 'false');
                setMessage(form, 'The article could not be deleted. Try again.', 'error');
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

        if (!form || form.getAttribute('data-red-article-ready') === 'true') {
            return;
        }

        form.setAttribute('data-red-article-ready', 'true');
        form._redArticleUploadsInFlight = 0;
        initializeAdvancedPanel(form);
        initializeLinkNavigator(form);
        initializePageCopyControls(form);
        initializeDateControls(form);
        initializeRemovalChoices(form);
        Array.prototype.forEach.call(form.querySelectorAll('[data-article-upload]'), function (uploader) {
            initializeUploader(form, uploader);
        });
        initializeEditors(form);

        deleteButton = find(form, '[data-article-delete]');
        if (deleteButton) {
            deleteButton.addEventListener('click', function () {
                deleteArticle(form);
            });
        }
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('form.red-admin-article-form'), initForm);
    }

    window.run_insert_content = function (form) {
        return submitForm(form);
    };

    window.run_update_content = function (form) {
        return submitForm(form);
    };

    window.run_deleterecord = function () {
        return deleteArticle(document.getElementById('update_content'));
    };

    window.RedAdminArticleForm = {
        init: init,
        submit: submitForm,
        remove: deleteArticle
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        window.setTimeout(init, 0);
    }
}(window, document));
