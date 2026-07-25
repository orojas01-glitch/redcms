(function (window, document) {
    'use strict';

    if (window.RedAdminFtpForm) {
        window.RedAdminFtpForm.activate();
        return;
    }

    function find(scope, selector) {
        return scope ? scope.querySelector(selector) : null;
    }

    function allowedExtensions(form) {
        var configured = form ? form.getAttribute('data-allowed-extensions') : '';
        var parsed;

        try {
            parsed = configured ? JSON.parse(configured) : [];
        } catch (error) {
            parsed = [];
        }

        return Array.isArray(parsed) ? parsed.map(function (extension) {
            return String(extension || '').toLowerCase();
        }).filter(Boolean) : [];
    }

    function maxFileBytes(form) {
        var configured = form ? Number(form.getAttribute('data-max-file-bytes')) : 0;
        return isFinite(configured) && configured > 0 ? configured : 0;
    }

    function fileExtension(fileName) {
        var parts = String(fileName || '').toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    }

    function fileKind(extension) {
        if (['jpg', 'jpeg', 'png', 'gif'].indexOf(extension) !== -1) {
            return {key: 'image', label: 'Image'};
        }
        if (['doc', 'docx', 'pdf'].indexOf(extension) !== -1) {
            return {key: 'document', label: 'Document'};
        }
        if (['xls', 'xlsx'].indexOf(extension) !== -1) {
            return {key: 'spreadsheet', label: 'Spreadsheet'};
        }
        if (['ppt', 'pptx', 'pps'].indexOf(extension) !== -1) {
            return {key: 'presentation', label: 'Presentation'};
        }
        if (extension === 'zip') {
            return {key: 'archive', label: 'Archive'};
        }

        return {key: 'text', label: 'Text file'};
    }

    function formatBytes(bytes) {
        var units = ['B', 'KB', 'MB', 'GB'];
        var unitIndex = 0;
        var value = Math.max(0, Number(bytes) || 0);

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex += 1;
        }

        if (unitIndex === 0) {
            return Math.round(value) + ' ' + units[unitIndex];
        }

        return (value >= 10 ? value.toFixed(0) : value.toFixed(1)) + ' ' + units[unitIndex];
    }

    function publicUrl(publicPath) {
        try {
            return new window.URL(publicPath, window.location.origin).href;
        } catch (error) {
            return '';
        }
    }

    function publicPathForName(fileName) {
        return '/images/articles/' + window.encodeURIComponent(String(fileName || ''));
    }

    function announce(form, message) {
        var liveRegion = find(form, '[data-ftp-live]');

        if (!liveRegion) {
            return;
        }

        liveRegion.textContent = '';
        window.setTimeout(function () {
            liveRegion.textContent = message || '';
        }, 0);
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
        var label = find(control, '[data-copy-label]');
        var defaultLabel = control.getAttribute('data-copy-default-label') || 'Copy link';
        var message = copied
            ? 'Public file link copied to the clipboard.'
            : 'The public file link could not be copied. Try again.';

        window.clearTimeout(control._redFtpCopyTimer);
        control.classList.toggle('is-copied', copied);
        control.classList.toggle('has-copy-error', !copied);
        if (label) {
            label.textContent = copied ? 'Copied!' : 'Try again';
        }
        announce(form, message);

        control._redFtpCopyTimer = window.setTimeout(function () {
            control.classList.remove('is-copied', 'has-copy-error');
            if (label) {
                label.textContent = defaultLabel;
            }
        }, 1800);
    }

    function copyLink(form, control) {
        var path = control.getAttribute('data-ftp-copy-path') || '';
        var value = publicUrl(path);

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

    function validateFile(form, file) {
        var extension;
        var allowed = allowedExtensions(form);
        var maxBytes = maxFileBytes(form);

        if (!file) {
            return 'Choose a file to upload.';
        }
        if (file.size <= 0) {
            return 'The selected file is empty.';
        }
        if (!maxBytes || file.size > maxBytes) {
            return 'That file is larger than the allowed upload size.';
        }

        extension = fileExtension(file.name);
        if (allowed.indexOf(extension) === -1) {
            return 'Choose an image, PDF, Office, text, or ZIP file.';
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

    function updateLibraryCount(form) {
        var list = find(form, '[data-ftp-file-list]');
        var countBadge = find(form, '[data-ftp-library-count]');
        var emptyState = find(form, '[data-ftp-empty]');
        var count = list ? list.querySelectorAll('[data-ftp-file]').length : 0;

        if (countBadge) {
            countBadge.textContent = count + (count === 1 ? ' file' : ' files');
        }
        if (emptyState) {
            emptyState.hidden = count > 0;
        }
    }

    function filterLibrary(form) {
        var search = find(form, '[data-ftp-search]');
        var list = find(form, '[data-ftp-file-list]');
        var noResults = find(form, '[data-ftp-no-results]');
        var query = search ? search.value.trim().toLowerCase() : '';
        var visible = 0;
        var items;

        if (!list) {
            return;
        }

        items = list.querySelectorAll('[data-ftp-file]');
        Array.prototype.forEach.call(items, function (item) {
            var haystack = item.getAttribute('data-search-value') || '';
            var matches = query === '' || haystack.indexOf(query) !== -1;

            item.hidden = !matches;
            if (matches) {
                visible += 1;
            }
        });

        if (noResults) {
            noResults.hidden = query === '' || visible > 0 || items.length === 0;
        }
    }

    function createFileIcon(extension, kind) {
        var icon = document.createElement('span');
        var label = document.createElement('small');

        icon.className = 'red-admin-ftp-file__icon red-admin-ftp-file__icon--' + kind;
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = '<svg viewBox="0 0 24 24"><path d="M6 3.75h9.25L19 7.5v12.75H6z"></path><path d="M15 3.75V7.5h4"></path></svg>';
        label.textContent = extension.toUpperCase();
        icon.appendChild(label);

        return icon;
    }

    function createLibraryFile(form, file, storedName, publicPath) {
        var list = find(form, '[data-ftp-file-list]');
        var extension = fileExtension(storedName);
        var kind = fileKind(extension);
        var item;
        var details;
        var fileName;
        var meta;
        var actions;
        var openLink;
        var copyButton;
        var copyLabel;
        var fullUrl = publicUrl(publicPath);
        var modifiedLabel;

        if (!list) {
            return;
        }

        try {
            modifiedLabel = new window.Intl.DateTimeFormat(undefined, {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            }).format(new Date());
        } catch (error) {
            modifiedLabel = new Date().toLocaleString();
        }

        item = document.createElement('li');
        item.className = 'red-admin-ftp-file is-new';
        item.setAttribute('data-ftp-file', '');
        item.setAttribute('data-search-value', (storedName + ' ' + extension + ' ' + kind.label).toLowerCase());
        item.appendChild(createFileIcon(extension, kind.key));

        details = document.createElement('div');
        details.className = 'red-admin-ftp-file__details';
        fileName = document.createElement('strong');
        fileName.textContent = storedName;
        fileName.title = storedName;
        meta = document.createElement('span');
        meta.textContent = kind.label + ' · ' + formatBytes(file.size) + ' · ' + modifiedLabel;
        details.appendChild(fileName);
        details.appendChild(meta);
        item.appendChild(details);

        actions = document.createElement('div');
        actions.className = 'red-admin-ftp-file__actions';
        openLink = document.createElement('a');
        openLink.className = 'red-admin-ftp-file__open';
        openLink.href = fullUrl;
        openLink.target = '_blank';
        openLink.rel = 'noopener';
        openLink.setAttribute('aria-label', 'Open ' + storedName + ' in a new tab');
        openLink.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M11 13l8-8M19 13v6H5V5h6"></path></svg><span>Open</span>';
        actions.appendChild(openLink);

        copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.className = 'red-admin-ftp-file__copy';
        copyButton.setAttribute('data-ftp-copy-path', publicPath);
        copyButton.setAttribute('data-copy-default-label', 'Copy link');
        copyButton.setAttribute('aria-label', 'Copy public link for ' + storedName);
        copyButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg>';
        copyLabel = document.createElement('span');
        copyLabel.setAttribute('data-copy-label', '');
        copyLabel.textContent = 'Copy link';
        copyButton.appendChild(copyLabel);
        actions.appendChild(copyButton);
        item.appendChild(actions);

        list.insertBefore(item, list.firstChild);
        updateLibraryCount(form);
        filterLibrary(form);
    }

    function showUploadResult(form, file, storedName) {
        var result = find(form, '[data-ftp-result]');
        var resultName = find(form, '[data-ftp-result-name]');
        var resultUrl = find(form, '[data-ftp-result-url]');
        var resultOpen = find(form, '[data-ftp-result-open]');
        var resultCopy = find(form, '[data-ftp-result] [data-ftp-copy-path]');
        var path = publicPathForName(storedName);
        var url = publicUrl(path);

        if (!result || !url) {
            return;
        }

        if (resultName) {
            resultName.textContent = storedName;
        }
        if (resultUrl) {
            resultUrl.href = url;
            resultUrl.textContent = url;
            resultUrl.title = url;
        }
        if (resultOpen) {
            resultOpen.href = url;
        }
        if (resultCopy) {
            resultCopy.setAttribute('data-ftp-copy-path', path);
        }
        result.hidden = false;
        createLibraryFile(form, file, storedName, path);
    }

    function resetUploadResult(form) {
        var result = find(form, '[data-ftp-result]');
        var resultName = find(form, '[data-ftp-result-name]');
        var resultUrl = find(form, '[data-ftp-result-url]');
        var resultOpen = find(form, '[data-ftp-result-open]');
        var resultCopy = find(form, '[data-ftp-result] [data-ftp-copy-path]');
        var copyLabel;

        if (!result) {
            return;
        }

        result.hidden = true;
        if (resultName) {
            resultName.textContent = '';
        }
        if (resultUrl) {
            resultUrl.href = '#';
            resultUrl.textContent = '';
            resultUrl.removeAttribute('title');
        }
        if (resultOpen) {
            resultOpen.href = '#';
        }
        if (resultCopy) {
            window.clearTimeout(resultCopy._redFtpCopyTimer);
            resultCopy.setAttribute('data-ftp-copy-path', '');
            resultCopy.classList.remove('is-copied', 'has-copy-error');
            copyLabel = find(resultCopy, '[data-copy-label]');
            if (copyLabel) {
                copyLabel.textContent = resultCopy.getAttribute('data-copy-default-label') || 'Copy link';
            }
        }
    }

    function initializeUploader(form) {
        var uploader = find(form, '[data-ftp-uploader]');
        var input = find(form, '[data-ftp-input]');
        var browse = find(form, '[data-ftp-browse]');
        var dropzone = find(form, '[data-ftp-dropzone]');
        var selection = find(form, '[data-ftp-selection]');
        var selectionName = find(form, '[data-ftp-selection-name]');
        var selectionMeta = find(form, '[data-ftp-selection-meta]');
        var uploadStatus = find(form, '[data-ftp-upload-status]');
        var progress = find(form, '[data-ftp-progress]');
        var uploadUrl = form.getAttribute('data-upload-url') || form.action;
        var uploadInFlight = false;

        if (!uploader || !input || !browse || !dropzone || !uploadUrl) {
            return;
        }

        function setStatus(message, state) {
            if (uploadStatus) {
                uploadStatus.textContent = message || '';
                uploadStatus.setAttribute('data-state', state || 'info');
            }
        }

        function finishUpload() {
            uploadInFlight = false;
            uploader.setAttribute('aria-busy', 'false');
            input.disabled = false;
            browse.disabled = false;
            input.value = '';
        }

        function showSelection(file) {
            if (selection) {
                selection.hidden = false;
            }
            if (selectionName) {
                selectionName.textContent = file ? file.name : '';
            }
            if (selectionMeta) {
                selectionMeta.textContent = file
                    ? formatBytes(file.size) + ' · ' + fileExtension(file.name).toUpperCase()
                    : '';
            }
        }

        function fail(message, clearInput) {
            uploader.classList.remove('is-complete');
            uploader.classList.add('has-error');
            setStatus(message, 'error');
            announce(form, message);
            if (clearInput) {
                input.value = '';
            }
        }

        function upload(file) {
            var validationMessage = validateFile(form, file);
            var xhr;
            var payload;
            var csrfInput;

            showSelection(file);
            resetUploadResult(form);
            if (validationMessage) {
                fail(validationMessage, true);
                return;
            }

            uploadInFlight = true;
            uploader.classList.remove('has-error', 'is-complete');
            uploader.setAttribute('aria-busy', 'true');
            input.disabled = true;
            browse.disabled = true;
            if (progress) {
                progress.style.width = '0%';
            }
            setStatus('Uploading…', 'progress');
            announce(form, 'Uploading ' + file.name + '.');

            payload = new window.FormData();
            payload.append('pic', file, file.name);
            xhr = new window.XMLHttpRequest();
            xhr.open('POST', uploadUrl, true);
            csrfInput = find(form, 'input[name="csrf_token"]');
            if (csrfInput && csrfInput.value) {
                xhr.setRequestHeader('X-CSRF-Token', csrfInput.value);
            } else if (window.RED_CSRF_TOKEN) {
                xhr.setRequestHeader('X-CSRF-Token', window.RED_CSRF_TOKEN);
            }

            xhr.upload.addEventListener('progress', function (event) {
                var percentage;

                if (!event.lengthComputable || !progress) {
                    return;
                }
                percentage = Math.max(0, Math.min(100, Math.round((event.loaded / event.total) * 100)));
                progress.style.width = percentage + '%';
                setStatus('Uploading ' + percentage + '%', 'progress');
            });

            xhr.addEventListener('load', function () {
                var response = parseUploadResponse(xhr);
                var storedName = response && typeof response.stored_name === 'string'
                    ? response.stored_name
                    : '';

                if (xhr.status < 200 || xhr.status >= 300 || !storedName) {
                    fail(response.status || 'The file could not be uploaded. Try again.');
                    finishUpload();
                    return;
                }

                if (progress) {
                    progress.style.width = '100%';
                }
                uploader.classList.remove('has-error');
                uploader.classList.add('is-complete');
                setStatus('Uploaded', 'success');
                showUploadResult(form, file, storedName);
                announce(form, storedName + ' uploaded. Its public link is ready to copy.');
                finishUpload();
            });

            xhr.addEventListener('error', function () {
                fail('The upload request could not reach the server. Try again.');
                finishUpload();
            });

            xhr.addEventListener('abort', function () {
                fail('The upload was canceled.');
                finishUpload();
            });

            xhr.send(payload);
        }

        function useFiles(files) {
            if (uploadInFlight) {
                setStatus('Upload already in progress', 'progress');
                announce(form, 'Wait for the current upload to finish before choosing another file.');
                return;
            }
            if (!files || !files.length) {
                return;
            }
            if (files.length > 1) {
                resetUploadResult(form);
                if (selection) {
                    selection.hidden = false;
                }
                if (selectionName) {
                    selectionName.textContent = 'Multiple files selected';
                }
                if (selectionMeta) {
                    selectionMeta.textContent = files.length + ' files';
                }
                fail('Upload one file at a time.', true);
                return;
            }

            upload(files[0]);
        }

        browse.addEventListener('click', function () {
            input.click();
        });
        input.addEventListener('change', function () {
            useFiles(input.files);
        });
        dropzone.addEventListener('dragenter', function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });
        dropzone.addEventListener('dragover', function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }
        });
        dropzone.addEventListener('dragleave', function (event) {
            if (!dropzone.contains(event.relatedTarget)) {
                dropzone.classList.remove('is-dragging');
            }
        });
        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
            useFiles(event.dataTransfer ? event.dataTransfer.files : null);
        });
    }

    function initForm(form) {
        var search;

        if (!form || form.getAttribute('data-red-ftp-ready') === 'true') {
            return;
        }

        form.setAttribute('data-red-ftp-ready', 'true');
        initializeUploader(form);
        updateLibraryCount(form);

        search = find(form, '[data-ftp-search]');
        if (search) {
            search.addEventListener('input', function () {
                filterLibrary(form);
            });
        }

        form.addEventListener('click', function (event) {
            var control = event.target.closest('[data-ftp-copy-path]');

            if (!control || !form.contains(control)) {
                return;
            }
            event.preventDefault();
            copyLink(form, control);
        });
    }

    function activate() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-red-ftp-form]'), initForm);
    }

    window.RedAdminFtpForm = {
        activate: activate,
        init: activate
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activate);
    } else {
        window.setTimeout(activate, 0);
    }
}(window, document));
