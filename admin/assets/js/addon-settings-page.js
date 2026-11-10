/* Saves the core add-on settings editor form without leaving the page. */
(function () {
    'use strict';

    var messages = {
        stale_plan: 'These settings changed in another window. Reloading the latest values…',
        invalid_values: 'One of the values is not valid. Check the fields and try again.',
        secret_submission: 'Secrets cannot be entered here.',
        secret_unconfigured: 'A required key is not configured on the server yet.',
        permission_denied: 'Your account cannot change these settings.',
        settings_unavailable: 'Settings could not be saved. Try again.',
        invalid_request: 'Settings could not be saved. Reload the page and try again.'
    };

    function reopen(packageId) {
        var forms = document.querySelectorAll('form[data-red-addon-settings-open]');
        for (var index = 0; index < forms.length; index++) {
            if (forms[index].getAttribute('data-red-addon-settings-open') === packageId) {
                forms[index].submit();
                return;
            }
        }
        window.location.assign('/admin/bin/addon_settings.php');
    }

    function setStatus(node, text) {
        if (!node) {
            return;
        }
        node.textContent = text;
        node.hidden = false;
    }

    function bind(form) {
        var button = form.querySelector('button[type="submit"]');
        var status = form.querySelector('[data-red-addon-settings-status]');
        var packageField = form.querySelector('input[name="PackageID"]');
        var csrfField = form.querySelector('input[name="csrf_token"]');
        var busy = false;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (busy || !packageField || !csrfField) {
                return;
            }
            busy = true;
            if (button) {
                button.disabled = true;
            }
            setStatus(status, 'Saving…');
            window.fetch(form.action, {
                method: 'POST',
                body: new window.FormData(form),
                credentials: 'same-origin',
                redirect: 'error',
                headers: {'X-CSRF-Token': csrfField.value}
            }).then(function (response) {
                return response.json().then(function (payload) {
                    return {status: response.status, payload: payload};
                });
            }).then(function (result) {
                var payload = result.payload || {};
                if (result.status === 200 && payload.ok === true) {
                    setStatus(
                        status,
                        payload.status === 'unchanged'
                            ? 'No changes to save.'
                            : 'Saved.'
                    );
                    window.setTimeout(function () {
                        reopen(packageField.value);
                    }, 700);
                    return;
                }
                var reason = typeof payload.reason === 'string' ? payload.reason : '';
                setStatus(status, messages[reason] || messages.settings_unavailable);
                if (reason === 'stale_plan') {
                    window.setTimeout(function () {
                        reopen(packageField.value);
                    }, 1200);
                    return;
                }
                busy = false;
                if (button) {
                    button.disabled = false;
                }
            }).catch(function () {
                setStatus(status, messages.settings_unavailable);
                busy = false;
                if (button) {
                    button.disabled = false;
                }
            });
        });
    }

    function init() {
        var forms = document.querySelectorAll('form[data-red-addon-settings-form]');
        for (var index = 0; index < forms.length; index++) {
            bind(forms[index]);
        }
        var editor = document.getElementById('editor');
        if (editor && editor.scrollIntoView) {
            editor.scrollIntoView({block: 'start'});
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
