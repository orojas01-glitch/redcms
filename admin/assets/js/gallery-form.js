(function (window, document) {
    'use strict';

    var allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    function find(root, selector) {
        return root ? root.querySelector(selector) : null;
    }

    function findAll(root, selector) {
        return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : [];
    }

    function formMode(form) {
        return form.getAttribute('data-gallery-mode') === 'edit' ? 'edit' : 'create';
    }

    function setMessage(form, message, state) {
        var box = find(form, '[data-gallery-message]');

        if (!box) {
            return;
        }
        box.textContent = message || '';
        box.setAttribute('data-state', state || 'info');
        box.hidden = !message;
    }

    function setSaving(form, saving) {
        var button = find(form, '[data-gallery-save]');
        var label = button ? find(button, '[data-save-label]') : null;

        if (!button) {
            return;
        }
        button.disabled = Boolean(saving);
        button.setAttribute('aria-busy', saving ? 'true' : 'false');
        if (label) {
            label.textContent = saving
                ? 'Saving…'
                : (button.getAttribute('data-default-label') || 'Save gallery');
        }
    }

    function csrfToken(form) {
        var input = find(form, 'input[name="csrf_token"]');
        return input && input.value ? input.value : (window.RED_CSRF_TOKEN || '');
    }

    function parseUploadResponse(xhr) {
        var payload;

        try {
            payload = JSON.parse(xhr.responseText || '{}');
        } catch (error) {
            payload = {status: xhr.responseText || 'The upload server returned an invalid response.'};
        }
        return payload;
    }

    function validateImage(form, file) {
        var parts;
        var extension;
        var maxBytes = Number(form.getAttribute('data-max-image-bytes')) || (2 * 1024 * 1024);

        if (!file) {
            return 'Choose an image to continue.';
        }
        parts = String(file.name || '').toLowerCase().split('.');
        extension = parts.length > 1 ? parts.pop() : '';
        if (allowedExtensions.indexOf(extension) === -1 || !/^image\//i.test(file.type || '')) {
            return file.name + ' is not a supported image. Use JPG, PNG, or GIF.';
        }
        if (file.size > maxBytes) {
            return file.name + ' is too large. The maximum image size is 2 MB.';
        }
        return '';
    }

    function uploadFile(form, uploadUrl, file, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            var payload = new FormData();

            if (!uploadUrl) {
                reject(new Error('The upload destination is unavailable.'));
                return;
            }

            payload.append('pic', file, file.name);
            xhr.open('POST', uploadUrl, true);
            if (csrfToken(form)) {
                xhr.setRequestHeader('X-CSRF-Token', csrfToken(form));
            }
            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable && typeof onProgress === 'function') {
                    onProgress(Math.round((event.loaded / event.total) * 100));
                }
            });
            xhr.addEventListener('load', function () {
                var response = parseUploadResponse(xhr);
                var storedName = response && typeof response.stored_name === 'string'
                    ? response.stored_name
                    : '';

                if (xhr.status >= 200 && xhr.status < 300 && storedName) {
                    resolve({storedName: storedName, response: response});
                    return;
                }
                reject(new Error(response.message || response.status || 'The image could not be uploaded.'));
            });
            xhr.addEventListener('error', function () {
                reject(new Error('The upload could not reach the server.'));
            });
            xhr.send(payload);
        });
    }

    function updateImagePreview(image, file) {
        var reader;

        if (!image || !file) {
            return;
        }
        reader = new FileReader();
        reader.onload = function (event) {
            image.src = event.target.result;
            image.alt = 'Preview of ' + file.name;
        };
        reader.readAsDataURL(file);
    }

    function photoCards(form) {
        return findAll(find(form, '[data-gallery-collection]'), '[data-gallery-photo-card]');
    }

    function isCardRemoved(card) {
        var remove = find(card, '[data-gallery-remove]');
        return Boolean(remove && remove.checked);
    }

    function updateCollection(form) {
        var cards = photoCards(form);
        var retained = cards.filter(function (card) { return !isCardRemoved(card); }).length;
        var marked = cards.length - retained;
        var count = find(form, '[data-gallery-count]');
        var empty = find(form, '[data-gallery-empty]');

        cards.forEach(function (card, index) {
            var order = find(card, '[data-gallery-order]');
            var name = find(card, '[data-gallery-file-name]');
            var earlier = find(card, '[data-gallery-move="earlier"]');
            var later = find(card, '[data-gallery-move="later"]');
            var label = name ? name.textContent : 'image';

            if (order) {
                order.textContent = String(index + 1);
            }
            if (earlier) {
                earlier.disabled = index === 0;
                earlier.setAttribute('aria-label', 'Move ' + label + ' earlier');
            }
            if (later) {
                later.disabled = index === cards.length - 1;
                later.setAttribute('aria-label', 'Move ' + label + ' later');
            }
            card.classList.toggle('is-marked-for-removal', isCardRemoved(card));
        });

        if (count) {
            count.textContent = retained + (retained === 1 ? ' image' : ' images')
                + (marked ? ' · ' + marked + ' marked for removal' : '');
        }
        if (empty) {
            empty.hidden = cards.length > 0;
        }
    }

    function containsReservedDelimiter(value) {
        return /[,;]/.test(String(value || ''));
    }

    function isAllowedCaptionLink(value) {
        var link = String(value || '').trim();
        var parsed;

        if (!link) {
            return true;
        }
        if (link.charAt(0) === '/') {
            return link.indexOf('//') !== 0 && !/[\u0000-\u001f\u007f]/.test(link);
        }
        try {
            parsed = new window.URL(link);
        } catch (error) {
            return false;
        }

        return parsed.protocol === 'https:' && !!parsed.hostname && !parsed.username && !parsed.password;
    }

    function serializePhotos(form, focusInvalid) {
        var descriptions = [];
        var invalid = null;
        var invalidMessage = '';

        photoCards(form).forEach(function (card, index) {
            var photo = find(card, '[data-gallery-photo-value]');
            var remove = find(card, '[data-gallery-remove]');
            var caption = find(card, '[data-gallery-caption]');
            var link = find(card, '[data-gallery-caption-link]');
            var captionValue = caption ? String(caption.value || '').trim() : '';
            var linkValue = link ? String(link.value || '').trim() : '';

            if (photo) {
                if (photo.value) {
                    photo.name = 'Photo' + index;
                    photo.disabled = false;
                } else {
                    photo.removeAttribute('name');
                    photo.disabled = true;
                }
            }
            if (remove) {
                if (photo && photo.value) {
                    remove.name = 'Delete' + index;
                    remove.disabled = false;
                } else {
                    remove.removeAttribute('name');
                    remove.disabled = true;
                }
            }

            [caption, link].forEach(function (field) {
                if (!field) {
                    return;
                }
                if (containsReservedDelimiter(field.value)) {
                    field.setAttribute('aria-invalid', 'true');
                    invalid = invalid || field;
                    invalidMessage = invalidMessage || 'Gallery captions and caption links cannot contain commas or semicolons.';
                } else {
                    field.removeAttribute('aria-invalid');
                }
            });
            if (link && !containsReservedDelimiter(link.value) && !isAllowedCaptionLink(linkValue)) {
                link.setAttribute('aria-invalid', 'true');
                invalid = invalid || link;
                invalidMessage = invalidMessage || 'Caption links must begin with / or use a secure HTTPS address.';
            }

            if (!isCardRemoved(card)) {
                descriptions.push(linkValue ? captionValue + ';' + linkValue : captionValue);
            }
        });

        if (invalid) {
            if (focusInvalid) {
                invalid.focus();
                setMessage(form, invalidMessage, 'error');
            }
            return false;
        }

        if (find(form, '[data-gallery-short-desc]')) {
            find(form, '[data-gallery-short-desc]').value = descriptions.join(',');
        }
        return true;
    }

    function createPhotoCard(form, file) {
        var template = find(form, '[data-gallery-photo-template]');
        var collection = find(form, '[data-gallery-collection]');
        var fragment;
        var card;
        var image;
        var fileName;

        if (!template || !collection) {
            return null;
        }
        fragment = template.content.cloneNode(true);
        card = find(fragment, '[data-gallery-photo-card]');
        image = find(card, '[data-gallery-photo-preview]');
        fileName = find(card, '[data-gallery-file-name]');
        card._redGalleryFile = file;
        if (fileName) {
            fileName.textContent = file.name;
            fileName.title = file.name;
        }
        updateImagePreview(image, file);
        collection.appendChild(fragment);
        updateCollection(form);
        return collection.lastElementChild;
    }

    function markPhotoStored(form, card, storedName) {
        var photo = find(card, '[data-gallery-photo-value]');
        var fileName = find(card, '[data-gallery-file-name]');
        var state = find(card, '[data-gallery-state-label]');
        var status = find(card, '[data-gallery-card-status]');
        var removeChoice = find(card, '[data-gallery-remove-choice]');
        var remove = find(card, '[data-gallery-remove]');
        var discard = find(card, '[data-gallery-discard]');

        card._redGalleryFile = null;
        card.setAttribute('data-gallery-photo-state', 'stored');
        if (photo) {
            photo.value = storedName;
            photo.disabled = false;
        }
        if (fileName) {
            fileName.textContent = storedName;
            fileName.title = storedName;
        }
        if (state) {
            state.textContent = 'Saved';
        }
        if (status) {
            status.textContent = 'Uploaded successfully.';
        }
        if (removeChoice) {
            removeChoice.hidden = false;
        }
        if (remove) {
            remove.disabled = false;
        }
        if (discard) {
            discard.hidden = true;
        }
        updateCollection(form);
        serializePhotos(form, false);
    }

    function initializePhotoCollection(form) {
        var collection = find(form, '[data-gallery-collection]');

        if (!collection) {
            return;
        }
        collection.addEventListener('click', function (event) {
            var moveButton = event.target.closest('[data-gallery-move]');
            var discardButton = event.target.closest('[data-gallery-discard]');
            var card = event.target.closest('[data-gallery-photo-card]');
            var direction;
            var sibling;

            if (!card || !collection.contains(card)) {
                return;
            }
            if (discardButton) {
                card.remove();
                updateCollection(form);
                serializePhotos(form, false);
                return;
            }
            if (!moveButton) {
                return;
            }
            direction = moveButton.getAttribute('data-gallery-move');
            sibling = direction === 'earlier' ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling) {
                return;
            }
            if (direction === 'earlier') {
                collection.insertBefore(card, sibling);
            } else {
                collection.insertBefore(sibling, card);
            }
            updateCollection(form);
            serializePhotos(form, false);
            moveButton.focus();
        });
        collection.addEventListener('change', function (event) {
            if (event.target.matches('[data-gallery-remove]')) {
                updateCollection(form);
                serializePhotos(form, false);
            }
        });
        collection.addEventListener('input', function (event) {
            if (event.target.matches('[data-gallery-caption], [data-gallery-caption-link]')) {
                if (!containsReservedDelimiter(event.target.value)) {
                    event.target.removeAttribute('aria-invalid');
                }
                serializePhotos(form, false);
            }
        });
        updateCollection(form);
        serializePhotos(form, false);
    }

    function setPrimaryUploadState(uploader, message, state, percentage) {
        var status = find(uploader, '[data-gallery-upload-status]');
        var progress = find(uploader, '[data-upload-progress]');

        if (status) {
            status.textContent = message;
            status.setAttribute('data-state', state || 'info');
        }
        if (progress) {
            progress.style.width = Math.max(0, Math.min(100, Number(percentage) || 0)) + '%';
        }
        uploader.classList.toggle('has-error', state === 'error');
        uploader.classList.toggle('is-complete', state === 'success');
    }

    function addCreateFiles(form, uploader, fileList) {
        var files = Array.prototype.slice.call(fileList || []);
        var maxBatch = Number(form.getAttribute('data-max-gallery-batch')) || 10;
        var pendingCount = photoCards(form).filter(function (card) { return Boolean(card._redGalleryFile); }).length;
        var errors = [];

        if (files.length > maxBatch || pendingCount + files.length > maxBatch) {
            setPrimaryUploadState(uploader, 'Choose no more than ' + maxBatch + ' images in one queued batch.', 'error', 0);
            setMessage(form, 'Choose no more than ' + maxBatch + ' gallery images before saving.', 'error');
            return;
        }
        files.forEach(function (file) {
            var error = validateImage(form, file);
            if (error) {
                errors.push(error);
                return;
            }
            createPhotoCard(form, file);
        });
        if (errors.length) {
            setPrimaryUploadState(uploader, errors[0], 'error', 0);
            setMessage(form, errors[0], 'error');
        } else if (files.length) {
            setPrimaryUploadState(uploader, files.length + (files.length === 1 ? ' image queued.' : ' images queued.'), 'ready', 0);
            setMessage(form, 'Images are queued and will upload after the gallery details are saved.', 'info');
        }
    }

    function uploadEditFiles(form, uploader, fileList) {
        var files = Array.prototype.slice.call(fileList || []);
        var maxBatch = Number(form.getAttribute('data-max-gallery-batch')) || 10;
        var uploadUrl = uploader.getAttribute('data-upload-url');
        var input = find(uploader, '[data-upload-input]');
        var browse = find(uploader, '[data-upload-browse]');
        var chain = Promise.resolve();
        var completed = 0;
        var validationError = '';

        if (!files.length) {
            return;
        }
        if (files.length > maxBatch) {
            setPrimaryUploadState(uploader, 'Choose no more than ' + maxBatch + ' images per batch.', 'error', 0);
            return;
        }
        files.some(function (file) {
            validationError = validateImage(form, file);
            return Boolean(validationError);
        });
        if (validationError) {
            setPrimaryUploadState(uploader, validationError, 'error', 0);
            setMessage(form, validationError, 'error');
            return;
        }

        form._redGalleryUploadsInFlight += 1;
        uploader.setAttribute('aria-busy', 'true');
        input.disabled = true;
        browse.disabled = true;
        setPrimaryUploadState(uploader, 'Uploading 1 of ' + files.length + '…', 'progress', 0);

        files.forEach(function (file, index) {
            chain = chain.then(function () {
                setPrimaryUploadState(uploader, 'Uploading ' + (index + 1) + ' of ' + files.length + ': ' + file.name, 'progress', 0);
                return uploadFile(form, uploadUrl, file, function (percentage) {
                    setPrimaryUploadState(uploader, 'Uploading ' + (index + 1) + ' of ' + files.length + ': ' + file.name, 'progress', percentage);
                }).then(function (result) {
                    var card = createPhotoCard(form, file);
                    completed += 1;
                    if (!card) {
                        throw new Error('The uploaded image could not be added to the editor. Reload before saving.');
                    }
                    markPhotoStored(form, card, result.storedName);
                });
            });
        });

        chain.then(function () {
            setPrimaryUploadState(uploader, completed + (completed === 1 ? ' image uploaded.' : ' images uploaded.'), 'success', 100);
            setMessage(form, 'The new gallery images were uploaded and added to this ordered collection.', 'success');
        }).catch(function (error) {
            setPrimaryUploadState(uploader, error.message, 'error', 0);
            setMessage(form, completed
                ? completed + ' image(s) uploaded before an error. The successful images are preserved below.'
                : error.message, 'error');
        }).then(function () {
            form._redGalleryUploadsInFlight = Math.max(0, form._redGalleryUploadsInFlight - 1);
            uploader.setAttribute('aria-busy', 'false');
            input.disabled = false;
            browse.disabled = false;
            input.value = '';
        });
    }

    function initializePrimaryUploader(form) {
        var uploader = find(form, '[data-gallery-primary-upload]');
        var input = find(uploader, '[data-upload-input]');
        var browse = find(uploader, '[data-upload-browse]');
        var dropzone = find(uploader, '[data-upload-dropzone]');

        if (!uploader || !input || !browse || !dropzone) {
            return;
        }

        function useFiles(files) {
            if (formMode(form) === 'edit') {
                uploadEditFiles(form, uploader, files);
            } else {
                addCreateFiles(form, uploader, files);
            }
            input.value = '';
        }

        browse.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () { useFiles(input.files); });
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
            if (event.dataTransfer && event.dataTransfer.files) {
                useFiles(event.dataTransfer.files);
            }
        });
    }

    function updateSupportingPreview(uploader, file) {
        var preview = find(uploader, '[data-upload-preview]');
        var image = find(uploader, '[data-upload-preview-image]');
        var name = find(uploader, '[data-upload-file-name]');

        if (!preview || !image || !name) {
            return;
        }
        name.textContent = file.name;
        preview.hidden = false;
        updateImagePreview(image, file);
    }

    function synchronizeSupportingImage(uploader, storedName) {
        var value = find(uploader, '[data-upload-value]');
        var current = find(uploader, '[data-current-media]');
        var currentImage = find(uploader, '[data-current-image]');
        var currentName = find(uploader, '[data-current-name]');
        var previewImage = find(uploader, '[data-upload-preview-image]');
        var remove = find(uploader, '[data-gallery-remove-image]');

        if (value) {
            value.value = storedName;
        }
        if (current) {
            current.hidden = false;
            current.classList.remove('is-marked-for-removal');
        }
        if (currentName) {
            currentName.textContent = storedName;
        }
        if (currentImage && previewImage && previewImage.src) {
            currentImage.src = previewImage.src;
            currentImage.alt = 'Current image ' + storedName;
        }
        if (remove) {
            remove.checked = false;
        }
        uploader._redGalleryFile = null;
    }

    function uploadSupportingNow(form, uploader, file) {
        var input = find(uploader, '[data-upload-input]');
        var browse = find(uploader, '[data-upload-browse]');
        var status = find(uploader, '[data-upload-status]');
        var progress = find(uploader, '[data-upload-progress]');
        var validationError = validateImage(form, file);

        if (validationError) {
            uploader.classList.add('has-error');
            if (status) {
                status.textContent = validationError;
                status.setAttribute('data-state', 'error');
            }
            setMessage(form, validationError, 'error');
            return;
        }

        form._redGalleryUploadsInFlight += 1;
        uploader.setAttribute('aria-busy', 'true');
        input.disabled = true;
        browse.disabled = true;
        uploader.classList.remove('has-error', 'is-complete');
        updateSupportingPreview(uploader, file);
        if (status) {
            status.textContent = 'Uploading ' + file.name + '…';
            status.setAttribute('data-state', 'progress');
        }

        uploadFile(form, uploader.getAttribute('data-upload-url'), file, function (percentage) {
            if (progress) {
                progress.style.width = percentage + '%';
            }
        }).then(function (result) {
            synchronizeSupportingImage(uploader, result.storedName);
            uploader.classList.add('is-complete');
            if (status) {
                status.textContent = 'Uploaded successfully';
                status.setAttribute('data-state', 'success');
            }
            setMessage(form, 'Supporting image uploaded successfully.', 'success');
        }).catch(function (error) {
            uploader.classList.add('has-error');
            if (status) {
                status.textContent = error.message;
                status.setAttribute('data-state', 'error');
            }
            setMessage(form, error.message, 'error');
        }).then(function () {
            form._redGalleryUploadsInFlight = Math.max(0, form._redGalleryUploadsInFlight - 1);
            uploader.setAttribute('aria-busy', 'false');
            input.disabled = false;
            browse.disabled = false;
            input.value = '';
        });
    }

    function initializeSupportingUploader(form, uploader) {
        var input = find(uploader, '[data-upload-input]');
        var browse = find(uploader, '[data-upload-browse]');
        var dropzone = find(uploader, '[data-upload-dropzone]');
        var remove = find(uploader, '[data-gallery-remove-image]');

        if (!input || !browse || !dropzone) {
            return;
        }

        function useFile(file) {
            var validationError = validateImage(form, file);
            var status = find(uploader, '[data-upload-status]');

            if (validationError) {
                uploader.classList.add('has-error');
                if (status) {
                    status.textContent = validationError;
                    status.setAttribute('data-state', 'error');
                }
                setMessage(form, validationError, 'error');
                return;
            }
            if (formMode(form) === 'edit') {
                uploadSupportingNow(form, uploader, file);
                return;
            }
            uploader._redGalleryFile = file;
            uploader.classList.remove('has-error');
            updateSupportingPreview(uploader, file);
            if (status) {
                status.textContent = 'Ready to upload after the gallery is saved.';
                status.setAttribute('data-state', 'ready');
            }
            setMessage(form, 'Supporting image queued with the gallery.', 'info');
        }

        browse.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            if (input.files && input.files.length) {
                useFile(input.files[0]);
            }
            input.value = '';
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
                useFile(event.dataTransfer.files[0]);
            }
        });
        if (remove) {
            remove.addEventListener('change', function () {
                var current = find(uploader, '[data-current-media]');
                if (current) {
                    current.classList.toggle('is-marked-for-removal', remove.checked);
                }
            });
        }
    }

    function initializeAdvancedPanel(form) {
        var details = find(form, '[data-gallery-advanced]');
        var storageKey = 'red-admin-' + formMode(form) + '-gallery-advanced-open';

        if (!details) {
            return;
        }
        try {
            details.open = window.sessionStorage.getItem(storageKey) === 'true';
            details.addEventListener('toggle', function () {
                window.sessionStorage.setItem(storageKey, details.open ? 'true' : 'false');
            });
        } catch (error) {
            // Native disclosure remains functional when storage is unavailable.
        }
    }

    function initializeDateControls(form) {
        findAll(form, '[data-gallery-date]').forEach(function (dateInput) {
            var field = dateInput.closest('.red-admin-field');
            var payload = field ? find(field, '[data-date-payload]') : null;
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

    function validateForm(form) {
        var title = find(form, '[name="Title"]');
        var titleError = find(form, '[data-title-error]');
        var startDate = find(form, '[data-gallery-date="start"]') || find(form, '[name="StartDate"]');
        var expirationDate = find(form, '[data-gallery-date="expiration"]') || find(form, '[name="ExpDate"]');
        var advanced = find(form, '[data-gallery-advanced]');

        if (!title || !String(title.value || '').trim()) {
            if (title) {
                title.setAttribute('aria-invalid', 'true');
                title.focus();
            }
            if (titleError) {
                titleError.hidden = false;
            }
            setMessage(form, 'Add a title before saving the gallery.', 'error');
            return false;
        }
        title.removeAttribute('aria-invalid');
        if (titleError) {
            titleError.hidden = true;
        }
        if (!serializePhotos(form, true)) {
            return false;
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
        if ((form._redGalleryUploadsInFlight || 0) > 0) {
            setMessage(form, 'Wait for the current image upload to finish before saving.', 'warning');
            return false;
        }
        return true;
    }

    function requestSave(form) {
        var submitUrl = form.getAttribute('data-submit-url')
            || (formMode(form) === 'edit' ? '/admin/bin/update_gallery.php' : '/admin/bin/insert_gallery.php');

        return new Promise(function (resolve, reject) {
            if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
                reject(new Error('The administrator request tools are unavailable.'));
                return;
            }
            window.jQuery.ajax({
                type: 'POST',
                url: submitUrl,
                data: window.jQuery(form).serialize(),
                success: function (data) {
                    var response = String(data).trim();
                    if (response === 'yes' || response === 'yesyes' || response === 'noyes' || response === 'yesno') {
                        resolve(response);
                        return;
                    }
                    reject(new Error('The gallery could not be saved. Review the fields and try again.'));
                },
                error: function () {
                    reject(new Error('The save request could not reach the server. Try again.'));
                }
            });
        });
    }

    function createUploadQueue(form) {
        var mainUploader = find(form, '[data-gallery-primary-upload]');
        var mainUrl = mainUploader ? mainUploader.getAttribute('data-upload-url') : '';
        var cards = photoCards(form).filter(function (card) { return Boolean(card._redGalleryFile); });
        var supports = findAll(form, '[data-gallery-support-upload]').filter(function (uploader) { return Boolean(uploader._redGalleryFile); });
        var total = cards.length + supports.length;
        var completed = 0;
        var chain = Promise.resolve();

        function progressMessage(fileName) {
            setMessage(form, 'Uploading image ' + (completed + 1) + ' of ' + total + ': ' + fileName, 'progress');
        }

        cards.forEach(function (card) {
            chain = chain.then(function () {
                var file = card._redGalleryFile;
                var status = find(card, '[data-gallery-card-status]');
                progressMessage(file.name);
                if (status) {
                    status.textContent = 'Uploading…';
                }
                return uploadFile(form, mainUrl, file, function (percentage) {
                    if (status) {
                        status.textContent = 'Uploading… ' + percentage + '%';
                    }
                }).then(function (result) {
                    completed += 1;
                    markPhotoStored(form, card, result.storedName);
                });
            });
        });

        supports.forEach(function (uploader) {
            chain = chain.then(function () {
                var file = uploader._redGalleryFile;
                var status = find(uploader, '[data-upload-status]');
                progressMessage(file.name);
                if (status) {
                    status.textContent = 'Uploading…';
                    status.setAttribute('data-state', 'progress');
                }
                return uploadFile(form, uploader.getAttribute('data-upload-url'), file, function (percentage) {
                    var progress = find(uploader, '[data-upload-progress]');
                    if (progress) {
                        progress.style.width = percentage + '%';
                    }
                }).then(function (result) {
                    completed += 1;
                    synchronizeSupportingImage(uploader, result.storedName);
                    uploader.classList.add('is-complete');
                    if (status) {
                        status.textContent = 'Uploaded successfully';
                        status.setAttribute('data-state', 'success');
                    }
                });
            });
        });

        return {
            total: total,
            promise: chain
        };
    }

    function submitForm(form) {
        var mode = formMode(form);
        var queue;

        if (!validateForm(form)) {
            return false;
        }
        setSaving(form, true);
        setMessage(form, mode === 'edit' ? 'Saving gallery changes…' : 'Saving gallery details…', 'progress');

        requestSave(form).then(function () {
            if (mode === 'edit') {
                setMessage(form, 'Changes saved. Refreshing the editor…', 'success');
                window.setTimeout(function () { window.location.reload(); }, 650);
                return null;
            }

            queue = createUploadQueue(form);
            if (!queue.total) {
                setMessage(form, 'Gallery saved. Refreshing the editor…', 'success');
                window.setTimeout(function () { window.location.reload(); }, 650);
                return null;
            }

            return queue.promise.then(function () {
                // Uploads persist filenames independently. Save once more so
                // reordered filenames and their index-matched captions become
                // authoritative even after a partial-upload retry.
                serializePhotos(form, false);
                setMessage(form, 'Images uploaded. Synchronizing their final order and captions…', 'progress');
                return requestSave(form).then(function () {
                    setMessage(form, 'Gallery and images saved. Refreshing the editor…', 'success');
                    window.setTimeout(function () { window.location.reload(); }, 650);
                });
            });
        }).catch(function (error) {
            setSaving(form, false);
            setMessage(form, error.message, 'error');
        });
        return false;
    }

    function deleteGallery(form) {
        var button = find(form, '[data-gallery-delete]');
        var recordId = find(form, 'input[name="RecordID"]');
        var artRecordId = find(form, 'input[name="ArtRecordID"]');
        var deleteUrl = form.getAttribute('data-delete-url') || '/admin/bin/delete_label.php';

        if (!button || !recordId || !artRecordId) {
            return false;
        }
        if (!window.confirm('Delete this gallery permanently? This action cannot be undone.')) {
            return false;
        }
        if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
            setMessage(form, 'The gallery could not be deleted because the administrator request tools are unavailable.', 'error');
            return false;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setMessage(form, 'Deleting gallery…', 'progress');
        window.jQuery.ajax({
            type: 'POST',
            url: deleteUrl,
            data: {
                RecordID: recordId.value,
                ArtRecordID: artRecordId.value,
                T: 'gal',
                csrf_token: csrfToken(form)
            },
            success: function (data) {
                if (String(data).trim() === 'yesyes') {
                    setMessage(form, 'Gallery deleted. Refreshing the content list…', 'success');
                    window.setTimeout(function () { window.location.reload(); }, 650);
                    return;
                }
                button.disabled = false;
                button.setAttribute('aria-busy', 'false');
                setMessage(form, 'The gallery could not be deleted. Try again.', 'error');
            },
            error: function () {
                button.disabled = false;
                button.setAttribute('aria-busy', 'false');
                setMessage(form, 'The delete request could not reach the server. Try again.', 'error');
            }
        });
        return false;
    }

    function initForm(form) {
        var deleteButton;

        if (!form || form.getAttribute('data-red-gallery-ready') === 'true') {
            return;
        }
        form.setAttribute('data-red-gallery-ready', 'true');
        form._redGalleryUploadsInFlight = 0;
        initializePhotoCollection(form);
        initializePrimaryUploader(form);
        initializeAdvancedPanel(form);
        initializeDateControls(form);
        findAll(form, '[data-gallery-support-upload]').forEach(function (uploader) {
            initializeSupportingUploader(form, uploader);
        });
        deleteButton = find(form, '[data-gallery-delete]');
        if (deleteButton) {
            deleteButton.addEventListener('click', function () { deleteGallery(form); });
        }
    }

    function init() {
        findAll(document, 'form.red-admin-gallery-form').forEach(initForm);
    }

    function activate() {
        window.run_insert_gallery = function (form) { return submitForm(form); };
        window.run_update_gallery = function (form) { return submitForm(form); };
        init();
    }

    window.RedAdminGalleryForm = {
        activate: activate,
        init: init,
        submit: submitForm,
        remove: deleteGallery
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        window.setTimeout(activate, 0);
    }
}(window, document));
