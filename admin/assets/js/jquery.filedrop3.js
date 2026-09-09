/* jQuery filedrop compatibility fork for RED-CMS, 1.0.0.
 * Based on the MIT-licensed interface by Weixi Yen.
 * Copyright (c) 2010 Resopollution. SPDX-License-Identifier: MIT.
 * Upstream: https://github.com/weixiyen/jquery-filedrop
 * RED-CMS changes: isolated instances/batches, native FormData, same-origin
 * transport, strict JSON completion, deterministic callbacks and rebinding.
 * See docs/LEGACY-UTILITY-PROVENANCE.md for source lineage and license.
 */
(function ($) {
    'use strict';
    var pluginName = 'filedrop3';
    function noop() {}
    $.fn[pluginName] = function (options) {
        return this.each(function () {
            var element = this, $element = $(element);
            var stateKey = '_red_' + pluginName;
            if (element[stateKey]) element[stateKey]();
            $.redFiledropSequence = ($.redFiledropSequence || 0) + 1;
            var namespace = '.redFiledrop' + $.redFiledropSequence;
            var opts = $.extend({url:'', refresh:1000, paramname:'userfile', maxfiles:25, maxfilesize:1, data:{},
                drop:noop, dragEnter:noop, dragOver:noop, dragLeave:noop, docEnter:noop, docOver:noop, docLeave:noop,
                beforeEach:noop, afterAll:noop, rename:noop, error:function (error) { window.alert(error); },
                uploadStarted:noop, uploadFinished:noop, uploadFailed:function () { window.alert('Upload failed'); },
                progressUpdated:noop, speedUpdated:noop}, options);
            var busy = false, leaveTimer;
            element[stateKey] = function () {
                clearTimeout(leaveTimer);
                $element.off(namespace);
                $(document).off(namespace);
            };
            $element.on('drop' + namespace, function (event) {
                event.preventDefault(); event.stopPropagation(); opts.drop(event);
                var nativeEvent = event.originalEvent || event;
                if (!nativeEvent.dataTransfer || !nativeEvent.dataTransfer.files || !window.FormData) { opts.error('BrowserNotSupported'); return; }
                if (busy) { opts.error('UploadInProgress'); return; }
                var batch = Array.from(nativeEvent.dataTransfer.files);
                if (batch.length > opts.maxfiles) { opts.error('TooManyFiles'); return; }
                if (!batch.length) return;
                var target;
                try { target = new URL(opts.url, window.location.href); } catch (_) { opts.error('InvalidUploadUrl'); return; }
                if (target.origin !== window.location.origin || !['http:', 'https:'].includes(target.protocol) || target.username || target.password) { opts.error('InvalidUploadUrl'); return; }
                busy = true;
                var pending = batch.length;
                function complete() { pending--; if (!pending) { busy = false; opts.afterAll(); } }
                batch.forEach(function (file, index) {
                    if (opts.beforeEach(file) === false) { complete(); return; }
                    if (file.size > 1048576 * opts.maxfilesize) { opts.error('FileTooLarge', file, index); complete(); return; }
                    var xhr = new XMLHttpRequest(), started = Date.now(), settled = false, loaded = 0, tick = started;
                    function finish(success, response) {
                        if (settled) return;
                        settled = true;
                        try {
                            if (success) opts.uploadFinished(index, file, response, Date.now() - started);
                            else opts.uploadFailed(index, file, response, Date.now() - started, xhr);
                        } finally { complete(); }
                    }
                    xhr.onload = function () {
                        var response;
                        try { response = JSON.parse(xhr.responseText); }
                        catch (_) { finish(false, {status:'Invalid upload response'}); return; }
                        var valid = response !== null && typeof response === 'object';
                        finish(xhr.status >= 200 && xhr.status < 300 && valid, valid ? response : {status:'Invalid upload response'});
                    };
                    xhr.onerror = xhr.onabort = xhr.ontimeout = function () { finish(false, {status:'Upload transport failed'}); };
                    xhr.upload.onprogress = function (event) {
                        if (!event.lengthComputable) return;
                        opts.progressUpdated(index, file, Math.round(event.loaded * 100 / event.total));
                        var now = Date.now();
                        if (now - tick >= opts.refresh) { opts.speedUpdated(index, file, (event.loaded - loaded) / (now - tick)); tick = now; loaded = event.loaded; }
                    };
                    try {
                        var form = new FormData();
                        Object.keys(opts.data).forEach(function (key) { var value = opts.data[key]; form.append(key, typeof value === 'function' ? value() : value); });
                        var renamed = opts.rename(file.name);
                        form.append(opts.paramname, file, typeof renamed === 'string' ? renamed : file.name);
                        xhr.open('POST', target.href, true);
                        if (window.RED_CSRF_TOKEN) xhr.setRequestHeader('X-CSRF-Token', window.RED_CSRF_TOKEN);
                        opts.uploadStarted(index, file, batch.length);
                        xhr.send(form);
                    } catch (_) { finish(false, {status:'Upload could not start'}); }
                });
            });
            $element.on('dragenter' + namespace, function (event) { clearTimeout(leaveTimer); event.preventDefault(); opts.dragEnter(event); });
            $element.on('dragover' + namespace, function (event) { event.preventDefault(); opts.dragOver(event); });
            $element.on('dragleave' + namespace, function (event) { event.stopPropagation(); opts.dragLeave(event); });
            $(document).on('drop' + namespace, function (event) { event.preventDefault(); opts.docLeave(event); });
            $(document).on('dragenter' + namespace, function (event) { clearTimeout(leaveTimer); event.preventDefault(); opts.docEnter(event); });
            $(document).on('dragover' + namespace, function (event) { clearTimeout(leaveTimer); event.preventDefault(); opts.docOver(event); });
            $(document).on('dragleave' + namespace, function (event) { leaveTimer = setTimeout(function () { opts.docLeave(event); }, 200); });
        });
    };
})(jQuery);
