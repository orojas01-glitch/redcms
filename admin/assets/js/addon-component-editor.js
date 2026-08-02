(function () {
    'use strict';

    function token() {
        return typeof window.RED_CSRF_TOKEN === 'string' ? window.RED_CSRF_TOKEN : '';
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'X-CSRF-Token': token()},
            body: body
        });
    }

    function editorTarget() {
        return document.getElementById('msggbox_edit_content');
    }

    function loadEditor(recordId) {
        var body = new URLSearchParams();
        body.append('ContentRecordID', String(recordId));
        var target = editorTarget();
        if (!target) {
            return Promise.reject(new Error('editor-target-unavailable'));
        }
        target.setAttribute('aria-busy', 'true');
        return post('/admin/bin/edit_addon_component.php', body).then(function (response) {
            return response.text().then(function (html) {
                if (!response.ok) {
                    throw new Error('editor-unavailable');
                }
                var grid = document.getElementById('edit_content_grid');
                if (grid) {
                    grid.style.display = 'none';
                }
                target.innerHTML = html;
                target.style.display = '';
                target.removeAttribute('aria-busy');
            });
        }).catch(function (error) {
            target.removeAttribute('aria-busy');
            target.textContent = 'The component editor is unavailable.';
            target.style.display = '';
            throw error;
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-red-addon-component-edit]');
        if (!button) {
            return;
        }
        event.preventDefault();
        button.disabled = true;
        loadEditor(button.dataset.contentRecordId).catch(function () {
            button.disabled = false;
        });
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-red-addon-component-form]');
        if (!form) {
            return;
        }
        event.preventDefault();
        var submit = form.querySelector('button[type="submit"]');
        var status = form.querySelector('[data-red-addon-form-status]');
        if (submit) {
            submit.disabled = true;
        }
        if (status) {
            status.hidden = false;
            status.textContent = 'Saving changes…';
        }
        post(form.action, new FormData(form)).then(function (response) {
            return response.json().catch(function () {
                return {ok: false, reason: 'invalid_response'};
            }).then(function (data) {
                if (!response.ok || !data.ok) {
                    var error = new Error(data.reason || 'update_failed');
                    error.reason = data.reason || 'update_failed';
                    throw error;
                }
                if (status) {
                    status.textContent = data.reason === 'unchanged'
                        ? 'No changes to save.'
                        : 'Changes saved.';
                }
                return loadEditor(form.querySelector('[name="ContentRecordID"]').value);
            });
        }).catch(function (error) {
            if (status) {
                status.textContent = error.reason === 'stale_state'
                    ? 'This component changed after it was opened. Reopen it before saving.'
                    : 'The component could not be saved. No changes were applied.';
            }
            if (submit) {
                submit.disabled = false;
            }
        });
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-red-addon-placement-form]');
        if (!form) {
            return;
        }
        event.preventDefault();
        var submit = form.querySelector('button[type="submit"]');
        var status = form.querySelector('[data-red-addon-placement-status]');
        if (submit) {
            submit.disabled = true;
        }
        if (status) {
            status.hidden = false;
            status.textContent = 'Placing component…';
        }
        post(form.action, new FormData(form)).then(function (response) {
            return response.json().catch(function () {
                return {ok: false, reason: 'invalid_response'};
            }).then(function (data) {
                if (!response.ok || !data.ok) {
                    var error = new Error(data.reason || 'placement_failed');
                    error.reason = data.reason || 'placement_failed';
                    throw error;
                }
                return loadEditor(form.querySelector('[name="ContentRecordID"]').value);
            });
        }).catch(function (error) {
            if (status) {
                status.textContent = error.reason === 'stale_state'
                    ? 'This component or destination changed. Reopen it before placing.'
                    : 'The component could not be placed. No changes were applied.';
            }
            if (submit) {
                submit.disabled = false;
            }
        });
    });
}());
