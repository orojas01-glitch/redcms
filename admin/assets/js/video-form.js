(function (window, document) {
    'use strict';

    var MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    var ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    var ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    function find(root, selector) {
        return root ? root.querySelector(selector) : null;
    }

    function findAll(root, selector) {
        return root ? Array.prototype.slice.call(root.querySelectorAll(selector)) : [];
    }

    function formMode(form) {
        return form.getAttribute('data-video-mode') === 'edit' ? 'edit' : 'create';
    }

    function setMessage(form, message, state) {
        var box = find(form, '[data-video-message]');
        if (!box) {
            return;
        }
        box.textContent = message || '';
        box.hidden = !message;
        box.setAttribute('data-state', state || 'info');
    }

    function setSaving(form, saving) {
        var button = find(form, '[data-video-save]');
        var label = button ? find(button, '[data-save-label]') : null;
        if (!button) {
            return;
        }
        button.disabled = Boolean(saving);
        button.setAttribute('aria-busy', saving ? 'true' : 'false');
        if (label) {
            label.textContent = saving ? 'Saving…' : (button.getAttribute('data-default-label') || 'Save video');
        }
    }

    function parseVideoUrl(value) {
        var raw = String(value || '').trim();
        var parsed;
        var host;
        var segments;
        var videoId = '';
        var videoIds;
        var videoIndex = -1;
        var segmentHash = '';
        var queryHashes;
        var privacyHash = '';
        var youtubeHosts = [
            'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
            'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be', 'www.youtu.be'
        ];
        var vimeoHosts = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];

        if (!raw || raw.length > 2048 || /[\u0000-\u001f\u007f]/.test(raw)) {
            return null;
        }
        try {
            parsed = new window.URL(raw);
        } catch (error) {
            return null;
        }
        if (parsed.protocol !== 'https:' || !parsed.hostname || parsed.username || parsed.password) {
            return null;
        }

        host = parsed.hostname.toLowerCase().replace(/\.$/, '');
        segments = parsed.pathname.split('/').filter(Boolean);
        if (youtubeHosts.indexOf(host) !== -1) {
            if (host === 'youtu.be' || host === 'www.youtu.be') {
                videoId = segments[0] || '';
            } else if ((segments[0] || '') === 'watch') {
                videoIds = parsed.searchParams.getAll('v');
                videoId = videoIds.length === 1 ? videoIds[0] : '';
            } else if (['embed', 'shorts', 'live'].indexOf(segments[0] || '') !== -1) {
                videoId = segments[1] || '';
            }
            if (!/^[A-Za-z0-9_-]{11}$/.test(videoId)) {
                return null;
            }
            return {
                provider: 'youtube',
                providerLabel: 'YouTube',
                id: videoId,
                canonicalUrl: 'https://www.youtube.com/watch?v=' + videoId,
                previewUrl: 'https://www.youtube-nocookie.com/embed/' + videoId + '?rel=0&playsinline=1',
                thumbnailUrl: 'https://i.ytimg.com/vi/' + videoId + '/hqdefault.jpg'
            };
        }

        if (vimeoHosts.indexOf(host) !== -1) {
            queryHashes = parsed.searchParams.getAll('h');
            if (queryHashes.length > 1) {
                return null;
            }
            if (host === 'player.vimeo.com' && (segments[0] || '') === 'video') {
                videoId = segments[1] || '';
                videoIndex = 1;
            } else {
                segments.slice().reverse().some(function (segment, reverseIndex) {
                    if (/^[0-9]{1,12}$/.test(segment)) {
                        videoId = segment;
                        videoIndex = segments.length - reverseIndex - 1;
                        return true;
                    }
                    return false;
                });
            }
            if (!/^[0-9]{1,12}$/.test(videoId)) {
                return null;
            }
            segmentHash = videoIndex >= 0 ? (segments[videoIndex + 1] || '') : '';
            if (segmentHash && queryHashes[0] && segmentHash !== queryHashes[0]) {
                return null;
            }
            privacyHash = queryHashes[0] || segmentHash;
            if (privacyHash && !/^[A-Za-z0-9]{6,64}$/.test(privacyHash)) {
                return null;
            }
            return {
                provider: 'vimeo',
                providerLabel: 'Vimeo',
                id: videoId,
                canonicalUrl: 'https://vimeo.com/' + videoId + (privacyHash ? '/' + privacyHash : ''),
                previewUrl: 'https://player.vimeo.com/video/' + videoId + (privacyHash ? '?h=' + privacyHash : ''),
                thumbnailUrl: ''
            };
        }

        if (/(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be|vimeo\.com)(\.|$)/.test(host)) {
            return null;
        }

        return {
            provider: 'external',
            providerLabel: host,
            id: '',
            canonicalUrl: raw,
            previewUrl: '',
            thumbnailUrl: ''
        };
    }

    function copyText(value) {
        var field;
        var copied;
        if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
            return window.navigator.clipboard.writeText(value);
        }
        return new Promise(function (resolve, reject) {
            field = document.createElement('textarea');
            field.value = value;
            field.setAttribute('readonly', 'readonly');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();
            try {
                copied = document.execCommand('copy');
            } catch (error) {
                copied = false;
            }
            document.body.removeChild(field);
            if (copied) {
                resolve();
            } else {
                reject(new Error('Copy failed'));
            }
        });
    }

    function initializeVideoPreview(form) {
        var input = find(form, '[data-video-url]');
        var preview = find(form, '[data-video-preview]');
        var provider = find(preview, '[data-video-provider]');
        var identifier = find(preview, '[data-video-identifier]');
        var status = find(preview, '[data-video-preview-status]');
        var thumbnail = find(preview, '[data-video-thumbnail]');
        var placeholder = find(preview, '[data-video-placeholder]');
        var player = find(preview, '[data-video-player]');
        var loadButton = find(preview, '[data-video-load]');
        var loadLabel = find(preview, '[data-video-load-label]');
        var copyButton = find(preview, '[data-video-copy]');
        var copyLabel = find(preview, '[data-video-copy-label]');
        var openLink = find(preview, '[data-video-open]');
        var timer;

        if (!input || !preview) {
            return;
        }

        function clearPlayer() {
            if (player) {
                player.textContent = '';
                player.hidden = true;
            }
            preview.classList.remove('is-playing');
        }

        function render() {
            var data = parseVideoUrl(input.value);
            clearPlayer();
            form._redVideoSource = data;
            if (!data) {
                preview.setAttribute('data-state', input.value.trim() ? 'invalid' : 'empty');
                provider.textContent = input.value.trim() ? 'Link not recognized' : 'Waiting for a link';
                identifier.textContent = input.value.trim() ? 'Review the address' : 'Preview will appear here';
                status.textContent = input.value.trim()
                    ? 'Use a complete HTTPS YouTube, Vimeo, or video-page address.'
                    : 'Paste a video URL to check it before saving.';
                thumbnail.hidden = true;
                thumbnail.removeAttribute('src');
                thumbnail.alt = '';
                placeholder.hidden = false;
                loadButton.disabled = true;
                if (loadLabel) {
                    loadLabel.textContent = 'Load player';
                }
                copyButton.disabled = true;
                if (copyLabel) {
                    copyLabel.textContent = 'Copy link';
                }
                openLink.href = '#';
                openLink.setAttribute('aria-disabled', 'true');
                return;
            }

            preview.setAttribute('data-state', data.provider);
            provider.textContent = data.providerLabel;
            identifier.textContent = data.id ? 'Video ID · ' + data.id : 'Secure external video link';
            status.textContent = data.provider === 'external'
                ? 'This provider will open in a new tab instead of being embedded.'
                : 'Recognized and ready. You can load the player here before saving.';
            loadButton.disabled = !data.previewUrl;
            if (loadLabel) {
                loadLabel.textContent = data.previewUrl ? 'Load player' : 'External link';
            }
            copyButton.disabled = false;
            openLink.href = data.canonicalUrl;
            openLink.removeAttribute('aria-disabled');

            if (data.thumbnailUrl) {
                thumbnail.src = data.thumbnailUrl;
                thumbnail.alt = 'YouTube thumbnail preview';
                thumbnail.hidden = false;
                placeholder.hidden = true;
            } else {
                thumbnail.hidden = true;
                thumbnail.removeAttribute('src');
                thumbnail.alt = '';
                placeholder.hidden = false;
            }
        }

        input.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(render, 120);
            input.removeAttribute('aria-invalid');
            var error = find(form, '[data-video-url-error]');
            if (error) {
                error.hidden = true;
            }
        });
        input.addEventListener('change', render);
        thumbnail.addEventListener('error', function () {
            thumbnail.hidden = true;
            placeholder.hidden = false;
        });
        loadButton.addEventListener('click', function () {
            var data = form._redVideoSource || parseVideoUrl(input.value);
            var iframe;
            if (!data || !data.previewUrl || !player) {
                return;
            }
            iframe = document.createElement('iframe');
            iframe.src = data.previewUrl;
            iframe.title = (find(form, '[name="Title"]') || {}).value || data.providerLabel + ' video preview';
            iframe.loading = 'lazy';
            iframe.allow = 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen';
            iframe.allowFullscreen = true;
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            player.textContent = '';
            player.appendChild(iframe);
            player.hidden = false;
            preview.classList.add('is-playing');
        });
        copyButton.addEventListener('click', function () {
            var data = form._redVideoSource || parseVideoUrl(input.value);
            if (!data) {
                return;
            }
            copyText(data.canonicalUrl).then(function () {
                if (copyLabel) {
                    copyLabel.textContent = 'Copied!';
                    window.setTimeout(function () { copyLabel.textContent = 'Copy link'; }, 1200);
                }
            }).catch(function () {
                setMessage(form, 'Copy is unavailable in this browser. Select the video URL and copy it manually.', 'error');
                input.focus();
                input.select();
            });
        });
        openLink.addEventListener('click', function (event) {
            if (openLink.getAttribute('aria-disabled') === 'true') {
                event.preventDefault();
            }
        });
        render();
    }

    function editorConfig(selector, onReady) {
        return {
            selector: selector,
            height: 210,
            menubar: false,
            statusbar: true,
            resize: true,
            browser_spellcheck: true,
            convert_urls: false,
            plugins: [
                'advlist autolink lists link charmap preview anchor',
                'searchreplace visualblocks code fullscreen',
                'table paste textcolor wordcount'
            ],
            toolbar: 'undo redo | styleselect | bold italic underline | bullist numlist | alignleft aligncenter alignright | link | removeformat code fullscreen',
            setup: function (editor) {
                editor.on('init', onReady);
            }
        };
    }

    function initializeEditor(form) {
        var field = find(form, '[data-video-editor]');
        var status = find(form, '[data-video-editor-status]');
        var attempts = 0;

        if (!field) {
            return;
        }
        function start() {
            var existing;
            attempts += 1;
            if (!window.tinymce || typeof window.tinymce.init !== 'function') {
                if (attempts < 30) {
                    window.setTimeout(start, 100);
                    return;
                }
                if (status) {
                    status.textContent = 'Plain text mode';
                    status.setAttribute('data-state', 'warning');
                }
                return;
            }
            existing = typeof window.tinymce.get === 'function' ? window.tinymce.get(field.id) : null;
            if (existing && typeof existing.remove === 'function') {
                existing.remove();
            }
            window.tinymce.init(editorConfig('#' + field.id, function () {
                if (status) {
                    status.textContent = 'Rich text tools ready';
                    status.setAttribute('data-state', 'ready');
                }
            }));
        }
        start();
    }

    function validateImage(file) {
        var parts;
        var extension;
        if (!file) {
            return 'Choose an image to continue.';
        }
        parts = String(file.name || '').toLowerCase().split('.');
        extension = parts.length > 1 ? parts.pop() : '';
        if (file.size <= 0) {
            return 'The selected image is empty.';
        }
        if (file.size > MAX_IMAGE_BYTES) {
            return 'That image is larger than 2 MB.';
        }
        if (ALLOWED_IMAGE_TYPES.indexOf(file.type) === -1 && ALLOWED_IMAGE_EXTENSIONS.indexOf(extension) === -1) {
            return 'Choose a JPG, PNG, or GIF image.';
        }
        return '';
    }

    function csrfToken(form) {
        var input = find(form, 'input[name="csrf_token"]');
        return input && input.value ? input.value : (window.RED_CSRF_TOKEN || '');
    }

    function parseUploadResponse(xhr) {
        try {
            return JSON.parse(xhr.responseText || '{}');
        } catch (error) {
            return {status: xhr.responseText || 'The upload server returned an invalid response.'};
        }
    }

    function updateUploadPreview(uploader, file) {
        var preview = find(uploader, '[data-upload-preview]');
        var image = find(uploader, '[data-upload-preview-image]');
        var name = find(uploader, '[data-upload-file-name]');
        var reader;
        if (!preview || !image || !name) {
            return;
        }
        preview.hidden = false;
        name.textContent = file.name;
        reader = new FileReader();
        reader.onload = function (event) {
            image.src = event.target.result;
            image.alt = 'Preview of ' + file.name;
        };
        reader.readAsDataURL(file);
    }

    function synchronizeImage(uploader, storedName) {
        var value = find(uploader, '[data-upload-value]');
        var remove = find(uploader, '[data-video-remove-image]');
        var current = find(uploader, '[data-current-media]');
        var currentImage = find(uploader, '[data-current-image]');
        var currentName = find(uploader, '[data-current-name]');
        var previewImage = find(uploader, '[data-upload-preview-image]');
        if (value) {
            value.value = storedName;
        }
        if (remove) {
            remove.checked = false;
            remove.dispatchEvent(new window.Event('change'));
        }
        if (currentName) {
            currentName.textContent = storedName;
        }
        if (currentImage && previewImage && previewImage.src) {
            currentImage.src = previewImage.src;
            currentImage.alt = 'Current image ' + storedName;
        }
        if (current) {
            current.hidden = false;
            current.classList.remove('is-marked-for-removal');
        }
    }

    function uploadFile(form, uploader, file) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            var payload = new FormData();
            var status = find(uploader, '[data-upload-status]');
            var progress = find(uploader, '[data-upload-progress]');
            var uploadUrl = uploader.getAttribute('data-upload-url');
            if (!uploadUrl) {
                reject(new Error('The image upload destination is unavailable.'));
                return;
            }
            payload.append('pic', file, file.name);
            uploader.setAttribute('aria-busy', 'true');
            if (status) {
                status.textContent = 'Uploading…';
                status.setAttribute('data-state', 'progress');
            }
            xhr.open('POST', uploadUrl, true);
            if (csrfToken(form)) {
                xhr.setRequestHeader('X-CSRF-Token', csrfToken(form));
            }
            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable && progress) {
                    progress.style.width = Math.round((event.loaded / event.total) * 100) + '%';
                }
            });
            xhr.addEventListener('load', function () {
                var response = parseUploadResponse(xhr);
                var storedName = response && typeof response.stored_name === 'string' ? response.stored_name : '';
                uploader.setAttribute('aria-busy', 'false');
                if (xhr.status >= 200 && xhr.status < 300 && storedName) {
                    synchronizeImage(uploader, storedName);
                    uploader._redVideoFile = null;
                    uploader.classList.add('is-complete');
                    if (progress) {
                        progress.style.width = '100%';
                    }
                    if (status) {
                        status.textContent = 'Uploaded successfully';
                        status.setAttribute('data-state', 'success');
                    }
                    resolve(storedName);
                    return;
                }
                reject(new Error(response.message || response.status || 'The image could not be uploaded.'));
            });
            xhr.addEventListener('error', function () {
                uploader.setAttribute('aria-busy', 'false');
                reject(new Error('The image upload could not reach the server.'));
            });
            xhr.send(payload);
        });
    }

    function initializeUploader(form, uploader) {
        var input = find(uploader, '[data-upload-input]');
        var browse = find(uploader, '[data-upload-browse]');
        var dropzone = find(uploader, '[data-upload-dropzone]');
        var status = find(uploader, '[data-upload-status]');
        if (!input || !browse || !dropzone) {
            return;
        }
        function choose(file) {
            var error = validateImage(file);
            if (error) {
                uploader.classList.add('has-error');
                if (status) {
                    status.textContent = error;
                    status.setAttribute('data-state', 'error');
                }
                setMessage(form, error, 'error');
                return;
            }
            uploader.classList.remove('has-error', 'is-complete');
            uploader._redVideoFile = file;
            updateUploadPreview(uploader, file);
            if (formMode(form) === 'create') {
                if (status) {
                    status.textContent = 'Ready after the video is saved';
                    status.setAttribute('data-state', 'ready');
                }
                return;
            }
            form._redVideoUploadsInFlight += 1;
            input.disabled = true;
            browse.disabled = true;
            uploadFile(form, uploader, file).catch(function (uploadError) {
                uploader.classList.add('has-error');
                if (status) {
                    status.textContent = uploadError.message;
                    status.setAttribute('data-state', 'error');
                }
                setMessage(form, uploadError.message, 'error');
            }).then(function () {
                form._redVideoUploadsInFlight = Math.max(0, form._redVideoUploadsInFlight - 1);
                input.disabled = false;
                browse.disabled = false;
                input.value = '';
            });
        }
        browse.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            if (input.files && input.files.length) {
                choose(input.files[0]);
            }
        });
        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });
        ['dragleave', 'dragend'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
            });
        });
        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
            if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                choose(event.dataTransfer.files[0]);
            }
        });
    }

    function initializeAdvancedPanel(form) {
        var details = find(form, '[data-video-advanced]');
        var storageKey = 'red-admin-' + formMode(form) + '-video-advanced-open';
        if (!details) {
            return;
        }
        try {
            details.open = window.sessionStorage.getItem(storageKey) === 'true';
            details.addEventListener('toggle', function () {
                window.sessionStorage.setItem(storageKey, details.open ? 'true' : 'false');
            });
        } catch (error) {
            // Native details remains functional without session storage.
        }
    }

    function initializeDateControls(form) {
        findAll(form, '[data-video-date]').forEach(function (dateInput) {
            var field = dateInput.closest('.red-admin-field');
            var payload = field ? find(field, '[data-date-payload]') : null;
            var original = dateInput.getAttribute('data-original-date');
            if (!payload || original === null) {
                return;
            }
            function synchronize() {
                var changed = dateInput.value !== original;
                payload.disabled = !changed;
                payload.value = changed ? dateInput.value : '';
            }
            dateInput.addEventListener('input', synchronize);
            dateInput.addEventListener('change', synchronize);
            synchronize();
        });
    }

    function initializeChoices(form) {
        var navigator = find(form, '[data-video-link-navigator]');
        var link = find(form, '[data-video-link]');
        if (navigator && link) {
            navigator.addEventListener('change', function () {
                if (navigator.value) {
                    link.value = navigator.value;
                }
            });
        }
        findAll(form, '[data-video-remove-image]').forEach(function (choice) {
            var current = choice.closest('[data-current-media]');
            function synchronize() {
                if (current) {
                    current.classList.toggle('is-marked-for-removal', choice.checked);
                }
            }
            choice.addEventListener('change', synchronize);
            synchronize();
        });
    }

    function isAllowedLink(value) {
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
        return parsed.protocol === 'https:' && Boolean(parsed.hostname) && !parsed.username && !parsed.password;
    }

    function validateForm(form) {
        var title = find(form, '[name="Title"]');
        var titleError = find(form, '[data-video-title-error]');
        var videoInput = find(form, '[data-video-url]');
        var videoError = find(form, '[data-video-url-error]');
        var startDate = find(form, '[data-video-date="start"]');
        var expirationDate = find(form, '[data-video-date="expiration"]');
        var advanced = find(form, '[data-video-advanced]');
        var link = find(form, '[data-video-link]');
        var source;

        if (!title || !String(title.value || '').trim()) {
            if (title) {
                title.setAttribute('aria-invalid', 'true');
                title.focus();
            }
            if (titleError) {
                titleError.hidden = false;
            }
            setMessage(form, 'Add a title before saving the video.', 'error');
            return false;
        }
        title.removeAttribute('aria-invalid');
        if (titleError) {
            titleError.hidden = true;
        }

        source = parseVideoUrl(videoInput ? videoInput.value : '');
        if (!source) {
            if (videoInput) {
                videoInput.setAttribute('aria-invalid', 'true');
                videoInput.focus();
            }
            if (videoError) {
                videoError.hidden = false;
            }
            setMessage(form, 'Enter a complete secure video URL before saving.', 'error');
            return false;
        }
        videoInput.removeAttribute('aria-invalid');
        if (videoError) {
            videoError.hidden = true;
        }

        if (link && !isAllowedLink(link.value)) {
            link.setAttribute('aria-invalid', 'true');
            link.focus();
            setMessage(form, 'The follow-up link must be a site path or a complete HTTPS URL.', 'error');
            return false;
        }
        if (link) {
            link.removeAttribute('aria-invalid');
        }

        if (startDate && expirationDate && startDate.value && expirationDate.value && expirationDate.value < startDate.value) {
            if (advanced) {
                advanced.open = true;
            }
            expirationDate.setAttribute('aria-invalid', 'true');
            expirationDate.focus();
            setMessage(form, 'Expiration date must be on or after the start date.', 'error');
            return false;
        }
        if (expirationDate) {
            expirationDate.removeAttribute('aria-invalid');
        }

        if (form._redVideoUploadsInFlight > 0) {
            setMessage(form, 'Wait for the current image upload to finish before saving.', 'error');
            return false;
        }
        return true;
    }

    function requestSave(form) {
        return new Promise(function (resolve, reject) {
            if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
                reject(new Error('The administrator request tools are unavailable.'));
                return;
            }
            if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
                window.tinymce.triggerSave();
            }
            window.jQuery.ajax({
                type: 'POST',
                url: form.getAttribute('data-submit-url'),
                data: window.jQuery(form).serialize(),
                success: function (data) {
                    var response = String(data).trim();
                    if (response === 'yes' || response === 'yesyes' || response === 'noyes' || response === 'yesno') {
                        resolve(response);
                        return;
                    }
                    reject(new Error('The video could not be saved. Review the fields and try again.'));
                },
                error: function () {
                    reject(new Error('The save request could not reach the server. Try again.'));
                }
            });
        });
    }

    function uploadQueuedImages(form) {
        var uploaders = findAll(form, '[data-video-upload]').filter(function (uploader) {
            return Boolean(uploader._redVideoFile);
        });
        var chain = Promise.resolve();
        uploaders.forEach(function (uploader, index) {
            chain = chain.then(function () {
                setMessage(form, 'Uploading supporting image ' + (index + 1) + ' of ' + uploaders.length + '…', 'progress');
                return uploadFile(form, uploader, uploader._redVideoFile);
            });
        });
        return {total: uploaders.length, promise: chain};
    }

    function submitForm(form) {
        var mode = formMode(form);
        var pendingEditUploads;
        if (!validateForm(form)) {
            return false;
        }
        setSaving(form, true);
        pendingEditUploads = mode === 'edit'
            ? uploadQueuedImages(form)
            : {total: 0, promise: Promise.resolve()};
        setMessage(
            form,
            pendingEditUploads.total
                ? 'Retrying supporting image uploads…'
                : (mode === 'edit' ? 'Saving video changes…' : 'Saving video details…'),
            'progress'
        );
        pendingEditUploads.promise.then(function () {
            return requestSave(form);
        }).then(function () {
            var queue;
            if (mode === 'edit') {
                setMessage(form, 'Changes saved. Refreshing the editor…', 'success');
                window.setTimeout(function () { window.location.reload(); }, 650);
                return null;
            }
            queue = uploadQueuedImages(form);
            if (!queue.total) {
                return null;
            }
            return queue.promise;
        }).then(function () {
            if (mode === 'create') {
                setMessage(form, 'Video saved. Refreshing the content list…', 'success');
                window.setTimeout(function () { window.location.reload(); }, 650);
            }
        }).catch(function (error) {
            setSaving(form, false);
            setMessage(form, error.message, 'error');
        });
        return false;
    }

    function deleteVideo(form) {
        var button = find(form, '[data-video-delete]');
        var recordId = find(form, 'input[name="RecordID"]');
        var artRecordId = find(form, 'input[name="ArtRecordID"]');
        if (!button || !recordId || !artRecordId) {
            return false;
        }
        if (!window.confirm('Delete this video permanently? This action cannot be undone.')) {
            return false;
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setMessage(form, 'Deleting video…', 'progress');
        window.jQuery.ajax({
            type: 'POST',
            url: form.getAttribute('data-delete-url') || '/admin/bin/delete_label.php',
            data: {
                RecordID: recordId.value,
                ArtRecordID: artRecordId.value,
                T: 'gal',
                csrf_token: csrfToken(form)
            },
            success: function (data) {
                if (String(data).trim() === 'yesyes') {
                    setMessage(form, 'Video deleted. Refreshing the content list…', 'success');
                    window.setTimeout(function () { window.location.reload(); }, 650);
                    return;
                }
                button.disabled = false;
                button.setAttribute('aria-busy', 'false');
                setMessage(form, 'The video could not be deleted. Try again.', 'error');
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
        if (!form || form.getAttribute('data-red-video-ready') === 'true') {
            return;
        }
        form.setAttribute('data-red-video-ready', 'true');
        form._redVideoUploadsInFlight = 0;
        initializeVideoPreview(form);
        initializeEditor(form);
        initializeAdvancedPanel(form);
        initializeDateControls(form);
        initializeChoices(form);
        findAll(form, '[data-video-upload]').forEach(function (uploader) {
            initializeUploader(form, uploader);
        });
        deleteButton = find(form, '[data-video-delete]');
        if (deleteButton) {
            deleteButton.addEventListener('click', function () { deleteVideo(form); });
        }
    }

    function activate() {
        window.run_insert_gallery = function (form) { return submitForm(form); };
        window.run_update_gallery = function (form) { return submitForm(form); };
        findAll(document, 'form.red-admin-video-form').forEach(initForm);
    }

    window.RedAdminVideoForm = {
        activate: activate,
        parse: parseVideoUrl,
        submit: submitForm,
        remove: deleteVideo
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        window.setTimeout(activate, 0);
    }
}(window, document));
