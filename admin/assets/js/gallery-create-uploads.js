(function (window, document) {
    'use strict';

    var config = window.RED_GALLERY_CREATE_CONFIG || {};
    var queues = [];

    function uploadErrorMessage(response, payload) {
        if (payload && (payload.message || payload.status)) {
            return payload.message || payload.status;
        }

        return 'Upload failed with server status ' + response.status + '.';
    }

    function parseResponse(response) {
        return response.text().then(function (body) {
            var payload = null;

            if (body) {
                try {
                    payload = JSON.parse(body);
                } catch (error) {
                    payload = null;
                }
            }

            if (!response.ok) {
                throw new Error(uploadErrorMessage(response, payload));
            }

            if (!payload) {
                throw new Error('The upload server returned an invalid response.');
            }

            return payload;
        });
    }

    function buildUploadUrl(queue) {
        var params = new URLSearchParams({
            RecordID: String(queue.recordId),
            UC: queue.uploadCase,
            Insert: 'false',
            AuthComponent: 'Gallery',
            AuthSubtype: config.galleryType || '',
            Language: config.language || ''
        });

        if (queue.articleRecordId) {
            params.set('ArtRecordID', String(queue.articleRecordId));
        }

        return '/admin/bin/post_file.php?' + params.toString();
    }

    function uploadFile(queue, file) {
        var formData = new FormData();
        formData.append('pic', file, file.name);

        setQueueStatus(queue, 'Uploading ' + file.name + '…', 'progress');

        return window.fetch(buildUploadUrl(queue), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': config.csrfToken || ''
            }
        }).then(parseResponse);
    }

    function setQueueStatus(queue, message, state) {
        var status = queue.element.querySelector('[data-upload-status]');

        if (status) {
            status.textContent = message;
            status.setAttribute('data-state', state || 'info');
        }
        if (queue.message) {
            queue.message.textContent = message;
            queue.message.style.display = message ? '' : 'none';
        }
    }

    function renderQueue(queue) {
        var previews = queue.element.querySelectorAll('.red-gallery-queued-preview');
        var modernPreview = queue.element.querySelector('[data-upload-preview]');
        var modernImage = queue.element.querySelector('[data-upload-preview-image]');
        var modernName = queue.element.querySelector('[data-upload-file-name]');
        Array.prototype.forEach.call(previews, function (preview) {
            preview.parentNode.removeChild(preview);
        });

        if (queue.modern && modernPreview && modernImage && modernName) {
            if (!queue.files.length) {
                modernPreview.hidden = true;
                modernImage.removeAttribute('src');
                modernImage.alt = '';
                modernName.textContent = '';
                setQueueStatus(queue, 'No image selected yet.', 'info');
                return;
            }

            modernPreview.hidden = false;
            modernName.textContent = queue.files[0].name;
            (function (file) {
                var reader = new FileReader();
                reader.onload = function (event) {
                    modernImage.src = event.target.result;
                    modernImage.alt = 'Preview of ' + file.name;
                };
                reader.readAsDataURL(file);
            }(queue.files[0]));
            setQueueStatus(queue, 'Ready to upload after the banner is saved.', 'ready');
            return;
        }

        queue.files.forEach(function (file) {
            var preview = document.createElement('div');
            var image = document.createElement('img');
            var reader = new FileReader();

            preview.className = 'preview red-gallery-queued-preview';
            preview.setAttribute('data-file-name', file.name);
            image.alt = file.name;
            image.width = 100;
            image.height = 100;
            reader.onload = function (event) {
                image.src = event.target.result;
            };
            reader.readAsDataURL(file);
            preview.appendChild(image);
            queue.element.appendChild(preview);
        });

        if (queue.message) {
            queue.message.style.display = queue.files.length ? 'none' : '';
        }
    }

    function showQueueError(queue, message) {
        var status = document.getElementById('msggbox_insert_gallery');

        setQueueStatus(queue, message, 'error');
        if (status) {
            status.textContent = ' ' + message;
            status.style.display = 'inline';
        } else if (queue.message) {
            queue.message.textContent = message;
            queue.message.style.display = '';
        }
    }

    function validExtension(file) {
        var parts = file.name.toLowerCase().split('.');
        var extension = parts.length > 1 ? parts.pop() : '';
        return (config.allowedExtensions || []).indexOf(extension) !== -1;
    }

    function addFiles(queue, fileList) {
        var incoming = Array.prototype.slice.call(fileList || []);

        incoming.forEach(function (file) {
            if (!validExtension(file) || !/^image\//i.test(file.type || '')) {
                showQueueError(queue, file.name + ' is not a supported image. Use JPG, PNG, or GIF.');
                return;
            }

            if (file.size > config.maxImageBytes) {
                showQueueError(queue, file.name + ' is too large. The maximum image size is 2 MB.');
                return;
            }

            if (queue.modern && queue.maxFiles === 1) {
                queue.files = [file];
                return;
            }

            if (queue.files.length >= queue.maxFiles) {
                showQueueError(queue, 'Too many files. Select no more than ' + queue.maxFiles + '.');
                return;
            }

            queue.files.push(file);
        });

        renderQueue(queue);
    }

    function createQueue(options) {
        var element = document.getElementById(options.elementId);
        var input;
        var message;
        var queue;
        var modern;
        var dropTarget;
        var browseButton;

        if (!element || element.getAttribute('data-red-gallery-queue') === 'ready') {
            return;
        }

        element.setAttribute('data-red-gallery-queue', 'ready');
        modern = element.hasAttribute('data-red-banner-queue');
        message = element.querySelector(options.messageSelector);
        dropTarget = modern ? element.querySelector('[data-upload-dropzone]') : element;
        browseButton = modern ? element.querySelector('[data-upload-browse]') : null;
        input = document.createElement('input');
        input.type = 'file';
        input.accept = '.jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif';
        input.multiple = options.maxFiles > 1;
        input.className = 'red-gallery-file-input';
        input.setAttribute('aria-label', options.label);
        input.style.position = 'absolute';
        input.style.left = '-9999px';
        if (modern) {
            input.tabIndex = -1;
            input.setAttribute('aria-hidden', 'true');
        }
        element.appendChild(input);

        queue = {
            element: element,
            input: input,
            message: message,
            files: [],
            maxFiles: options.maxFiles,
            recordId: options.recordId,
            articleRecordId: options.articleRecordId,
            uploadCase: options.uploadCase,
            modern: modern
        };

        input.addEventListener('change', function () {
            addFiles(queue, input.files);
            input.value = '';
        });
        if (browseButton) {
            browseButton.addEventListener('click', function () {
                input.click();
            });
        } else {
            element.addEventListener('click', function (event) {
                if (event.target !== input && !event.target.closest('.red-gallery-queued-preview')) {
                    input.click();
                }
            });
        }
        (dropTarget || element).addEventListener('dragover', function (event) {
            event.preventDefault();
            if (modern) {
                (dropTarget || element).classList.add('is-dragging');
            }
        });
        (dropTarget || element).addEventListener('dragleave', function () {
            if (modern) {
                (dropTarget || element).classList.remove('is-dragging');
            }
        });
        (dropTarget || element).addEventListener('drop', function (event) {
            event.preventDefault();
            if (modern) {
                (dropTarget || element).classList.remove('is-dragging');
            }
            addFiles(queue, event.dataTransfer.files);
        });

        queues.push(queue);
    }

    function initialize() {
        if (!document.getElementById('insert_gallery')) {
            return;
        }

        createQueue({
            elementId: 'dropbox',
            messageSelector: '.message',
            label: config.galleryType === 'Banner' ? 'Choose banner image' : 'Choose gallery images',
            maxFiles: config.galleryType === 'Banner' || config.galleryType === 'Video' ? 1 : 10,
            recordId: config.recordId,
            articleRecordId: config.articleRecordId,
            uploadCase: 'Gallery'
        });
        createQueue({
            elementId: 'dropbox2',
            messageSelector: '.message2',
            label: 'Choose feature image',
            maxFiles: 1,
            recordId: config.articleRecordId,
            articleRecordId: 0,
            uploadCase: 'BigPict'
        });
        createQueue({
            elementId: 'dropbox3',
            messageSelector: '.message3',
            label: 'Choose small image',
            maxFiles: 1,
            recordId: config.articleRecordId,
            articleRecordId: 0,
            uploadCase: 'SmallPict'
        });
    }

    window.redGalleryCreateUploadQueued = function () {
        var chain = Promise.resolve();

        queues.forEach(function (queue) {
            queue.files.slice().forEach(function (file) {
                chain = chain.then(function () {
                    return uploadFile(queue, file).then(function () {
                        var index = queue.files.indexOf(file);
                        if (index !== -1) {
                            queue.files.splice(index, 1);
                        }
                        renderQueue(queue);
                        setQueueStatus(queue, 'Uploaded successfully.', 'success');
                    });
                });
            });
        });

        return chain;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        window.setTimeout(initialize, 0);
    }
}(window, document));
