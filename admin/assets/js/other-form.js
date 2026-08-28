(function (window, document) {
    'use strict';

    if (window.RedAdminOtherForm) {
        window.RedAdminOtherForm.init();
        return;
    }

    var MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    var ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    var ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    function find(form, selector) {
        return form ? form.querySelector(selector) : null;
    }

    function formMode(form) {
        return form && form.getAttribute('data-other-mode') === 'edit' ? 'edit' : 'create';
    }

    function currentEditorMode(form) {
        var workspace = find(form, '[data-other-editor-workspace]');
        return workspace && workspace.getAttribute('data-editor-mode') === 'html' ? 'html' : 'visual';
    }

    function setMessage(form, message, state) {
        var box = find(form, '[data-other-message]');

        if (!box) {
            return;
        }

        box.textContent = message || '';
        box.hidden = !message;
        box.setAttribute('data-state', state || 'info');
    }

    function setSaving(form, saving) {
        var button = find(form, '[data-other-save]');
        var label;
        var defaultLabel;

        if (!button) {
            return;
        }

        label = button.querySelector('[data-save-label]');
        defaultLabel = button.getAttribute('data-default-label')
            || (formMode(form) === 'edit' ? 'Save changes' : 'Save HTML block');
        button.disabled = saving;
        button.setAttribute('aria-busy', saving ? 'true' : 'false');
        if (label) {
            label.textContent = saving ? 'Saving…' : defaultLabel;
        }
    }

    function setEditorStatus(form, message, state) {
        var status = find(form, '[data-other-editor-status]');

        if (!status) {
            return;
        }

        status.textContent = message;
        status.setAttribute('data-state', state || 'loading');
    }

    function sourceField(form) {
        return find(form, '[data-other-html-source]');
    }

    function utf8Base64(value) {
        var bytes;
        var binary = '';
        var index;

        value = String(value || '');
        if (window.TextEncoder) {
            bytes = new window.TextEncoder().encode(value);
            for (index = 0; index < bytes.length; index += 1) {
                binary += String.fromCharCode(bytes[index]);
            }
            return window.btoa(binary);
        }

        return window.btoa(unescape(encodeURIComponent(value)));
    }

    function visualField(form) {
        return find(form, '[data-other-visual-editor]');
    }

    function visualEditor(form) {
        var field = visualField(form);

        if (!field || !field.id || !window.tinymce || typeof window.tinymce.get !== 'function') {
            return null;
        }

        return window.tinymce.get(field.id);
    }

    function updateSourceStats(form) {
        var source = sourceField(form);
        var stats = find(form, '[data-other-source-stats]');
        var value;
        var lineCount;

        if (!source || !stats) {
            return;
        }

        value = String(source.value || '');
        lineCount = value === '' ? 1 : value.split(/\r\n|\r|\n/).length;
        stats.textContent = lineCount + (lineCount === 1 ? ' line' : ' lines')
            + ' · ' + value.length + (value.length === 1 ? ' character' : ' characters');
    }

    function unsafeVisualUrl(value) {
        var normalized = String(value || '').replace(/[\u0000-\u0020]+/g, '').toLowerCase();

        if (/^(?:javascript|vbscript):/.test(normalized)) {
            return true;
        }
        if (normalized.indexOf('data:') === 0) {
            return !/^data:image\/(?:gif|jpe?g|png|webp);base64,/.test(normalized);
        }

        return normalized.indexOf('blob:') === 0;
    }

    function sanitizeVisualHtml(value) {
        var parser;
        var parsed;
        var blocked;

        try {
            parser = new window.DOMParser();
            parsed = parser.parseFromString(String(value || ''), 'text/html');
        } catch (error) {
            parsed = document.implementation.createHTMLDocument('Visual HTML working copy');
            parsed.body.textContent = String(value || '');
        }

        blocked = parsed.body.querySelectorAll('script,noscript,iframe,frame,frameset,object,embed,applet,form,input,button,select,option,textarea,meta,base,link,style,template,svg,math');
        Array.prototype.forEach.call(blocked, function (element) {
            element.remove();
        });

        Array.prototype.forEach.call(parsed.body.querySelectorAll('*'), function (element) {
            Array.prototype.slice.call(element.attributes).forEach(function (attribute) {
                var name = attribute.name.toLowerCase();

                if (/^on/.test(name)
                        || ['srcdoc', 'formaction', 'action', 'http-equiv', 'style', 'ping', 'background', 'srcset'].indexOf(name) !== -1
                        || (['href', 'src', 'xlink:href', 'poster', 'cite'].indexOf(name) !== -1 && unsafeVisualUrl(attribute.value))) {
                    element.removeAttribute(attribute.name);
                }
            });
        });

        return parsed.body.innerHTML;
    }

    function replaceIdReferences(form, originalId, scopedId) {
        var attributes = ['for', 'aria-controls', 'aria-labelledby', 'aria-describedby'];

        Array.prototype.forEach.call(form.querySelectorAll('[for],[aria-controls],[aria-labelledby],[aria-describedby]'), function (element) {
            attributes.forEach(function (attribute) {
                var value = element.getAttribute(attribute);
                var tokens;

                if (!value) {
                    return;
                }
                tokens = value.split(/\s+/).map(function (token) {
                    return token === originalId ? scopedId : token;
                });
                element.setAttribute(attribute, tokens.join(' '));
            });
        });
    }

    function scopeElementIdentity(form, element, suffix) {
        var originalId;
        var scopedId;

        if (!element || !element.id || element.getAttribute('data-other-scoped') === 'true') {
            return;
        }

        originalId = element.id;
        scopedId = suffix + '-' + originalId;
        replaceIdReferences(form, originalId, scopedId);
        element.id = scopedId;
        element.setAttribute('data-other-scoped', 'true');
    }

    function scopeEditorIdentity(form) {
        var recordId = find(form, 'input[name="RecordID"]');
        var suffix = (form.id + '-' + (recordId ? recordId.value : 'new')).replace(/[^a-zA-Z0-9_-]+/g, '-');
        var referencedIds = {};

        Array.prototype.forEach.call(form.querySelectorAll('[for],[aria-controls],[aria-labelledby],[aria-describedby]'), function (element) {
            ['for', 'aria-controls', 'aria-labelledby', 'aria-describedby'].forEach(function (attribute) {
                var value = element.getAttribute(attribute);

                if (value) {
                    value.split(/\s+/).forEach(function (id) {
                        referencedIds[id] = true;
                    });
                }
            });
        });
        Array.prototype.forEach.call(form.querySelectorAll('[id]'), function (element) {
            if (referencedIds[element.id]) {
                scopeElementIdentity(form, element, suffix);
            }
        });
    }

    function editorConfig(form, field) {
        return {
            selector: '#' + field.id,
            height: 390,
            menubar: false,
            statusbar: true,
            resize: true,
            browser_spellcheck: true,
            convert_urls: false,
            verify_html: true,
            plugins: [
                'advlist autolink lists link image charmap anchor',
                'searchreplace visualblocks fullscreen table paste wordcount'
            ],
            toolbar: 'undo redo | styleselect | bold italic underline | bullist numlist | link image table | removeformat fullscreen',
            style_formats: [
                {title: 'Heading 1', format: 'h1'},
                {title: 'Heading 2', format: 'h2'},
                {title: 'Heading 3', format: 'h3'},
                {title: 'Heading 4', format: 'h4'},
                {title: 'Paragraph', format: 'p'},
                {title: 'Blockquote', format: 'blockquote'}
            ],
            paste_preprocess: function (plugin, args) {
                args.content = sanitizeVisualHtml(args.content);
            },
            setup: function (editor) {
                editor.on('BeforeSetContent', function (event) {
                    event.content = sanitizeVisualHtml(event.content);
                });
                editor.on('init', function () {
                    var stagedContent = form._redOtherPendingVisualContent || '';
                    var stagedDirty = form._redOtherVisualDirty === true;

                    form._redOtherEditor = editor;
                    form._redOtherEditorStarting = false;
                    form._redOtherEditorSyncing = true;
                    editor.setContent(stagedContent);
                    if (typeof editor.setDirty === 'function') {
                        editor.setDirty(stagedDirty);
                    }
                    form._redOtherVisualDirty = stagedDirty;
                    form._redOtherEditorSyncing = false;
                    setEditorStatus(form, stagedDirty ? 'Unsaved visual changes' : 'Visual editor ready', stagedDirty ? 'changed' : 'ready');
                });

                editor.on('change input undo redo', function () {
                    if (!form._redOtherEditorSyncing) {
                        form._redOtherVisualDirty = true;
                        setEditorStatus(form, 'Unsaved visual changes', 'changed');
                    }
                });
            }
        };
    }

    function showHtmlFallback(form, message) {
        form._redOtherEditorStarting = false;
        setEditorMode(form, 'html', false);
        setEditorStatus(form, message, 'warning');
        setMessage(form, 'The visual tools did not load. Your HTML remains available and unchanged.', 'warning');
    }

    function ensureVisualEditor(form) {
        var field = visualField(form);

        if (!field || form.getAttribute('data-advanced-markup') === 'true' || form._redOtherEditor || form._redOtherEditorStarting) {
            return;
        }

        if (!form._redOtherVisualDirty) {
            field.value = sanitizeVisualHtml(form._redOtherSourceSnapshot || '');
        }
        form._redOtherPendingVisualContent = field.value;
        form._redOtherEditorStarting = true;
        form._redOtherEditorAttempts = 0;

        function start() {
            form._redOtherEditorAttempts += 1;
            if (!window.tinymce || typeof window.tinymce.init !== 'function') {
                if (form._redOtherEditorAttempts < 30) {
                    window.setTimeout(start, 100);
                    return;
                }

                showHtmlFallback(form, 'HTML mode — visual tools did not load');
                return;
            }

            try {
                if (typeof window.tinymce.get === 'function') {
                    var existing = window.tinymce.get(field.id);
                    if (existing && typeof existing.remove === 'function') {
                        existing.remove();
                    }
                }
                window.tinymce.init(editorConfig(form, field));
            } catch (error) {
                showHtmlFallback(form, 'HTML mode — visual tools could not start');
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('Other visual editor initialization failed.', error);
                }
            }
        }

        start();
    }

    function syncVisualToSource(form) {
        var source = sourceField(form);
        var stage = visualField(form);
        var editor = visualEditor(form) || form._redOtherEditor;

        if (!source || !form._redOtherVisualDirty) {
            return;
        }

        source.value = sanitizeVisualHtml(editor && typeof editor.getContent === 'function'
            ? editor.getContent()
            : (stage ? stage.value : source.value));
        form._redOtherSourceSnapshot = source.value;
        form._redOtherSourceDirty = true;
        form._redOtherVisualDirty = false;
        if (editor && typeof editor.setDirty === 'function') {
            editor.setDirty(false);
        }
        updateSourceStats(form);
    }

    function loadSourceIntoVisual(form) {
        var source = sourceField(form);
        var stage = visualField(form);
        var editor = visualEditor(form) || form._redOtherEditor;

        if (!source || !stage) {
            return;
        }

        form._redOtherSourceSnapshot = source.value;
        stage.value = sanitizeVisualHtml(source.value);
        form._redOtherPendingVisualContent = stage.value;
        form._redOtherVisualDirty = false;

        if (editor && typeof editor.setContent === 'function') {
            form._redOtherEditorSyncing = true;
            editor.setContent(stage.value);
            if (typeof editor.setDirty === 'function') {
                editor.setDirty(false);
            }
            form._redOtherEditorSyncing = false;
            setEditorStatus(form, 'Visual editor ready', 'ready');
            return;
        }

        ensureVisualEditor(form);
    }

    function updateModeUi(form, mode) {
        var buttons = form.querySelectorAll('[data-other-editor-mode]');
        var visualPanel = find(form, '[data-other-visual-panel]');
        var htmlPanel = find(form, '[data-other-html-panel]');
        var workspace = find(form, '[data-other-editor-workspace]');
        var description = find(form, '[data-other-mode-description]');

        Array.prototype.forEach.call(buttons, function (button) {
            var active = button.getAttribute('data-other-editor-mode') === mode;
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.setAttribute('tabindex', active ? '0' : '-1');
        });
        if (workspace) {
            workspace.setAttribute('data-editor-mode', mode);
        }
        if (visualPanel) {
            visualPanel.hidden = mode !== 'visual';
        }
        if (htmlPanel) {
            htmlPanel.hidden = mode !== 'html';
        }
        if (description) {
            description.textContent = mode === 'html'
                ? 'Edit the exact stored HTML. Only paste code you trust.'
                : 'Format text, headings, lists, links, and tables without writing HTML.';
        }
    }

    function setEditorMode(form, mode, focusPanel) {
        var source;

        mode = mode === 'html' ? 'html' : 'visual';
        if (mode === 'visual' && form.getAttribute('data-advanced-markup') === 'true') {
            updateModeUi(form, 'html');
            setEditorStatus(form, 'HTML source ready', 'ready');
            setMessage(form, 'Visual editing is unavailable for structured template HTML so its code remains exact.', 'warning');
            return;
        }
        if (mode === currentEditorMode(form)) {
            if (mode === 'visual') {
                ensureVisualEditor(form);
            }
            return;
        }

        if (mode === 'html') {
            syncVisualToSource(form);
            setEditorStatus(form, 'HTML source ready', 'ready');
        } else {
            loadSourceIntoVisual(form);
        }

        updateModeUi(form, mode);
        if (focusPanel) {
            source = mode === 'html' ? sourceField(form) : visualField(form);
            if (source && typeof source.focus === 'function') {
                window.setTimeout(function () {
                    source.focus();
                }, 0);
            }
        }
    }

    function initializeModeTabs(form) {
        var buttons = form.querySelectorAll('[data-other-editor-mode]');

        Array.prototype.forEach.call(buttons, function (button, index) {
            button.addEventListener('click', function () {
                setEditorMode(form, button.getAttribute('data-other-editor-mode'), true);
            });
            button.addEventListener('keydown', function (event) {
                var targetIndex = index;

                if (event.key === 'ArrowRight') {
                    targetIndex = (index + 1) % buttons.length;
                } else if (event.key === 'ArrowLeft') {
                    targetIndex = (index - 1 + buttons.length) % buttons.length;
                } else if (event.key === 'Home') {
                    targetIndex = 0;
                } else if (event.key === 'End') {
                    targetIndex = buttons.length - 1;
                } else {
                    return;
                }

                event.preventDefault();
                buttons[targetIndex].focus();
                setEditorMode(form, buttons[targetIndex].getAttribute('data-other-editor-mode'), false);
            });
        });
    }

    function initializeSourceEditor(form) {
        var source = sourceField(form);
        var stage = visualField(form);

        if (!source) {
            return;
        }

        form._redOtherSourceSnapshot = source.value;
        form._redOtherSourceDirty = false;
        updateSourceStats(form);

        source.addEventListener('input', function () {
            form._redOtherSourceSnapshot = source.value;
            form._redOtherSourceDirty = true;
            updateSourceStats(form);
            setEditorStatus(form, 'Unsaved HTML changes', 'changed');
        });

        if (stage) {
            stage.value = sanitizeVisualHtml(source.value);
            form._redOtherPendingVisualContent = stage.value;
            stage.addEventListener('input', function () {
                if (!form._redOtherEditor) {
                    form._redOtherPendingVisualContent = stage.value;
                    form._redOtherVisualDirty = true;
                    setEditorStatus(form, 'Unsaved visual changes', 'changed');
                }
            });
        }
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

    function copyText(value, done) {
        if (window.navigator.clipboard && typeof window.navigator.clipboard.writeText === 'function') {
            try {
                window.navigator.clipboard.writeText(value).then(function () {
                    done(true);
                }, function () {
                    done(legacyCopyText(value));
                });
                return;
            } catch (error) {
                done(legacyCopyText(value));
                return;
            }
        }

        done(legacyCopyText(value));
    }

    function showCopyFeedback(control, copied, successLabel, defaultLabel) {
        var label = control.querySelector('[data-copy-label]');

        window.clearTimeout(control._redOtherCopyTimer);
        control.classList.toggle('is-copied', copied);
        control.classList.toggle('has-copy-error', !copied);
        if (label) {
            label.textContent = copied ? successLabel : 'Try again';
        }
        control._redOtherCopyTimer = window.setTimeout(function () {
            control.classList.remove('is-copied', 'has-copy-error');
            if (label) {
                label.textContent = defaultLabel;
            }
        }, 1800);
    }

    function initializeCopyControls(form) {
        var copyHtml = find(form, '[data-other-copy-html]');
        var copyPage = find(form, '[data-other-copy-page]');
        var copyStatus = find(form, '[data-other-copy-status]');

        if (copyHtml) {
            copyHtml.addEventListener('click', function () {
                var source = sourceField(form);
                copyText(source ? source.value : '', function (copied) {
                    showCopyFeedback(copyHtml, copied, 'Copied!', 'Copy HTML');
                    if (copyStatus) {
                        copyStatus.textContent = copied ? 'HTML copied to the clipboard.' : 'HTML could not be copied.';
                    }
                });
            });
        }

        if (copyPage) {
            copyPage.addEventListener('click', function () {
                copyText(copyPage.getAttribute('data-copy-value') || '', function (copied) {
                    showCopyFeedback(copyPage, copied, 'Copied!', 'Copy page link');
                    if (copyStatus) {
                        copyStatus.textContent = copied ? 'Page address copied to the clipboard.' : 'Page address could not be copied.';
                    }
                });
            });
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

    function synchronizeImage(form, uploader, response) {
        var field = uploader.getAttribute('data-upload-field');
        var storedName = response && typeof response.stored_name === 'string' ? response.stored_name : '';
        var valueInput = uploader.querySelector('[data-upload-value]');
        var removeInput = uploader.querySelector('[data-other-remove-image]');
        var currentMedia = uploader.querySelector('[data-current-media]');
        var currentImage = uploader.querySelector('[data-current-image]');
        var currentName = uploader.querySelector('[data-current-name]');
        var previewImage = uploader.querySelector('[data-upload-preview-image]');

        if (!field || !storedName || !valueInput || valueInput.name !== field) {
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
        var browse = uploader.querySelector('[data-upload-browse]');
        var dropzone = uploader.querySelector('[data-upload-dropzone]');
        var status = uploader.querySelector('[data-upload-status]');
        var progress = uploader.querySelector('[data-upload-progress]');
        var uploadUrl = uploader.getAttribute('data-upload-url');

        if (!input || !browse || !dropzone || !status || !progress || !uploadUrl) {
            return;
        }

        function finish() {
            uploader.setAttribute('aria-busy', 'false');
            input.disabled = false;
            browse.disabled = false;
            form._redOtherUploadsInFlight = Math.max(0, (form._redOtherUploadsInFlight || 1) - 1);
        }

        function upload(file) {
            var error = validateImage(file);
            var xhr;
            var payload;
            var csrf;

            if (error) {
                uploader.classList.add('has-error');
                status.textContent = error;
                status.setAttribute('data-state', 'error');
                return;
            }

            uploader.classList.remove('has-error', 'is-complete');
            uploader.setAttribute('aria-busy', 'true');
            input.disabled = true;
            browse.disabled = true;
            progress.style.width = '0%';
            status.textContent = 'Uploading ' + file.name + '…';
            status.setAttribute('data-state', 'progress');
            updateUploadPreview(uploader, file);
            form._redOtherUploadsInFlight = (form._redOtherUploadsInFlight || 0) + 1;

            payload = new FormData();
            payload.append('pic', file, file.name);
            xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl, true);
            csrf = find(form, 'input[name="csrf_token"]');
            if (csrf && csrf.value) {
                xhr.setRequestHeader('X-CSRF-Token', csrf.value);
            }

            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable) {
                    progress.style.width = Math.round((event.loaded / event.total) * 100) + '%';
                }
            });
            xhr.addEventListener('load', function () {
                var response = parseUploadResponse(xhr);
                var successful = xhr.status >= 200 && xhr.status < 300 && synchronizeImage(form, uploader, response);

                if (successful) {
                    var revisionHash = xhr.getResponseHeader('X-RED-Revision-Hash');
                    var currentHash = find(form, '[data-other-current-hash]');

                    if (currentHash && /^[a-f0-9]{64}$/.test(String(revisionHash || ''))) {
                        currentHash.value = revisionHash;
                    }
                    progress.style.width = '100%';
                    uploader.classList.add('is-complete');
                    status.textContent = 'Uploaded successfully';
                    status.setAttribute('data-state', 'success');
                    setMessage(form, 'Image uploaded and synchronized with this HTML block.', 'success');
                } else {
                    progress.style.width = '0%';
                    uploader.classList.add('has-error');
                    status.textContent = response.status || 'The image could not be uploaded.';
                    status.setAttribute('data-state', 'error');
                    setMessage(form, 'The image could not be uploaded. Review the message and try again.', 'error');
                }
                input.value = '';
                finish();
            });
            xhr.addEventListener('error', function () {
                progress.style.width = '0%';
                uploader.classList.add('has-error');
                status.textContent = 'The upload could not reach the server.';
                status.setAttribute('data-state', 'error');
                setMessage(form, 'The image upload could not reach the server.', 'error');
                input.value = '';
                finish();
            });
            xhr.send(payload);
        }

        browse.addEventListener('click', function () {
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
        var details = find(form, '[data-other-advanced]');
        var key = 'red-admin-' + formMode(form) + '-other-advanced-open';

        if (!details) {
            return;
        }

        try {
            details.open = window.sessionStorage.getItem(key) === 'true';
            details.addEventListener('toggle', function () {
                window.sessionStorage.setItem(key, details.open ? 'true' : 'false');
            });
        } catch (error) {
            // The native disclosure remains functional when storage is unavailable.
        }
    }

    function initializeDateControls(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-other-date]'), function (dateInput) {
            var field = dateInput.closest('.red-admin-field');
            var payload = field ? field.querySelector('[data-date-payload]') : null;
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

    function initializeRemovalChoices(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-other-remove-image]'), function (input) {
            var current = input.closest('[data-current-media]');
            function update() {
                if (current) {
                    current.classList.toggle('is-marked-for-removal', input.checked);
                }
            }
            input.addEventListener('change', update);
            update();
        });
    }

    function validateForm(form) {
        var title = find(form, '[name="Title"]');
        var titleError = find(form, '[data-other-title-error]');
        var startDate = find(form, '[data-other-date="start"]');
        var expirationDate = find(form, '[data-other-date="expiration"]');
        var advanced = find(form, '[data-other-advanced]');

        if (!title || !String(title.value || '').trim()) {
            if (title) {
                title.setAttribute('aria-invalid', 'true');
                title.focus();
            }
            if (titleError) {
                titleError.hidden = false;
            }
            setMessage(form, 'Add a title before saving the HTML block.', 'error');
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
        if ((form._redOtherUploadsInFlight || 0) > 0) {
            setMessage(form, 'Wait for the image upload to finish before saving.', 'warning');
            return false;
        }

        return true;
    }

    function prepareContentPayload(form) {
        var action = find(form, '[data-other-content-action]');
        var encoded = find(form, '[data-other-content-base64]');
        var selectedSource = find(form, '[data-other-reconcile-source]:checked');
        var source = sourceField(form);
        var mode = formMode(form);

        if (!action || !encoded) {
            return false;
        }

        encoded.value = '';
        if (mode === 'create') {
            action.value = 'create';
            encoded.value = utf8Base64(source ? source.value : '');
            return true;
        }
        if (form.getAttribute('data-legacy-mismatch') === 'true') {
            if (selectedSource) {
                action.value = 'reconcile';
            } else {
                action.value = 'preserve';
            }
            return true;
        }
        if (form._redOtherSourceDirty) {
            action.value = 'update';
            encoded.value = utf8Base64(source ? source.value : '');
            return true;
        }

        action.value = 'preserve';
        return true;
    }

    function initializeReconciliation(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-other-reconcile-source]'), function (choice) {
            choice.addEventListener('change', function () {
                setEditorStatus(
                    form,
                    choice.value === 'long'
                        ? 'Dedicated-page version selected'
                        : 'Editor/listing version selected',
                    'changed'
                );
                setMessage(form, 'Your selected legacy version will become the one canonical Other content when saved.', 'warning');
            });
        });
    }

    function submitForm(form) {
        var mode = formMode(form);
        var submitUrl = form.getAttribute('data-submit-url')
            || (mode === 'edit' ? '/admin/bin/update_content.php' : '/admin/bin/insert_content.php');

        if (!validateForm(form)) {
            return false;
        }

        if (currentEditorMode(form) === 'visual') {
            syncVisualToSource(form);
        }
        if (!prepareContentPayload(form)) {
            setMessage(form, 'The HTML block content could not be prepared safely.', 'error');
            return false;
        }
        setSaving(form, true);
        setMessage(form, 'Saving HTML block…', 'progress');

        if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
            setSaving(form, false);
            setMessage(form, 'The HTML block could not be saved because the administrator request tools are unavailable.', 'error');
            return false;
        }

        window.jQuery.ajax({
            type: 'POST',
            url: submitUrl,
            data: window.jQuery(form).serialize(),
            success: function (data) {
                if (String(data).trim() === 'yes') {
                    setMessage(form, mode === 'edit' ? 'Changes saved. Refreshing the editor…' : 'HTML block added. Refreshing the editor…', 'success');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 650);
                    return;
                }

                setSaving(form, false);
                if (String(data).trim() === 'stale') {
                    setMessage(form, 'This block changed after you opened it. Reload the editor before saving.', 'error');
                } else if (String(data).trim() === 'reconcile') {
                    setMessage(form, 'Choose which saved legacy version should become canonical before changing its content.', 'error');
                } else {
                    setMessage(form, 'The HTML block could not be saved. Review the fields and try again.', 'error');
                }
            },
            error: function () {
                setSaving(form, false);
                setMessage(form, 'The save request could not reach the server. Try again.', 'error');
            }
        });

        return false;
    }

    function deleteOther(form) {
        var button = find(form, '[data-other-delete]');
        var recordId = find(form, 'input[name="RecordID"]');
        var csrf = find(form, 'input[name="csrf_token"]');
        var deleteUrl = form.getAttribute('data-delete-url') || '/admin/bin/delete_label.php';

        if (!button || !recordId || !csrf) {
            return false;
        }
        if (!window.confirm('Delete this HTML block permanently? This action cannot be undone.')) {
            return false;
        }
        if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
            setMessage(form, 'The HTML block could not be deleted because the administrator request tools are unavailable.', 'error');
            return false;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setMessage(form, 'Deleting HTML block…', 'progress');
        window.jQuery.ajax({
            type: 'POST',
            url: deleteUrl,
            data: {RecordID: recordId.value, csrf_token: csrf.value},
            success: function (data) {
                if (String(data).trim() === 'yes') {
                    setMessage(form, 'HTML block deleted. Refreshing the content list…', 'success');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 650);
                    return;
                }
                button.disabled = false;
                button.setAttribute('aria-busy', 'false');
                setMessage(form, 'The HTML block could not be deleted. Try again.', 'error');
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
        var preferred;
        var deleteButton;

        if (!form || form.getAttribute('data-red-other-ready') === 'true') {
            return;
        }

        form.setAttribute('data-red-other-ready', 'true');
        form._redOtherUploadsInFlight = 0;
        form._redOtherVisualDirty = false;
        form._redOtherSourceDirty = false;
        scopeEditorIdentity(form);
        initializeSourceEditor(form);
        initializeModeTabs(form);
        initializeCopyControls(form);
        initializeAdvancedPanel(form);
        initializeDateControls(form);
        initializeRemovalChoices(form);
        initializeReconciliation(form);
        Array.prototype.forEach.call(form.querySelectorAll('[data-other-upload]'), function (uploader) {
            initializeUploader(form, uploader);
        });

        preferred = form.getAttribute('data-preferred-editor-mode') === 'html' ? 'html' : 'visual';
        updateModeUi(form, preferred);
        if (preferred === 'visual') {
            ensureVisualEditor(form);
        } else {
            setEditorStatus(form, 'HTML source ready', 'ready');
        }

        deleteButton = find(form, '[data-other-delete]');
        if (deleteButton) {
            deleteButton.addEventListener('click', function () {
                deleteOther(form);
            });
        }
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('form.red-admin-other-form'), initForm);
    }

    window.run_insert_content = function (form) {
        return submitForm(form);
    };
    window.run_update_content = function (form) {
        return submitForm(form);
    };
    window.run_deleterecord = function () {
        return deleteOther(document.getElementById('update_content'));
    };

    window.RedAdminOtherForm = {
        init: init,
        submit: submitForm,
        remove: deleteOther
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        window.setTimeout(init, 0);
    }
}(window, document));
