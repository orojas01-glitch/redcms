(function () {
    'use strict';

    var formSelector = '[data-red-addon-public-mutation-form]';
    var statusSelector = '[data-red-addon-public-mutation-status]';
    var tokenPattern = /^[a-f0-9]{64}$/;
    var pathSegmentPattern = /^[A-Za-z0-9_{}.-]+$/;
    var states = new WeakMap();

    function routeUrl(form) {
        var action = form.getAttribute('action');
        if (typeof action !== 'string' || action.length < 2 || action.length > 240) {
            return null;
        }
        var url;
        try {
            url = new URL(action, window.location.origin);
        } catch (error) {
            return null;
        }
        if (url.origin !== window.location.origin
            || url.search !== ''
            || url.hash !== ''
            || url.pathname !== action
        ) {
            return null;
        }
        var segments = url.pathname.slice(1).split('/');
        if (segments.length < 1
            || segments.some(function (segment) {
                return !pathSegmentPattern.test(segment)
                    || segment === '.'
                    || segment === '..';
            })
        ) {
            return null;
        }
        return url;
    }

    function formBody(form) {
        var pairs = [];
        var seen = Object.create(null);
        var valid = true;
        new FormData(form).forEach(function (value, key) {
            if (typeof value !== 'string'
                || !/^[a-z][a-z0-9-]{0,63}$/.test(key)
                || value.length > 2000
                || Object.prototype.hasOwnProperty.call(seen, key)
            ) {
                valid = false;
                return;
            }
            seen[key] = true;
            pairs.push([key, value]);
        });
        if (!valid || pairs.length < 1 || pairs.length > 16) {
            return '';
        }
        var body = new URLSearchParams(pairs).toString();
        if (body.length < 1 || body.length > 8192) {
            return '';
        }
        return body;
    }

    function condition(form, wrapper, prefix) {
        var field = wrapper.getAttribute('data-red-' + prefix + '-field');
        var equals = wrapper.getAttribute('data-red-' + prefix + '-equals');
        if (field === null && equals === null) {
            return {valid: true, active: false, matches: false};
        }
        if (typeof field !== 'string'
            || typeof equals !== 'string'
            || !/^[a-z][a-z0-9-]{0,63}$/.test(field)
            || !/^[A-Za-z0-9][A-Za-z0-9._~-]*$/.test(equals)
        ) {
            return {valid: false, active: false, matches: false};
        }
        var controller = form.elements.namedItem(field);
        if (!(controller instanceof HTMLSelectElement)) {
            return {valid: false, active: false, matches: false};
        }
        var allowed = Array.prototype.some.call(
            controller.options,
            function (option) {
                return option.value === equals;
            }
        );
        return {
            valid: allowed,
            active: true,
            matches: allowed && controller.value === equals
        };
    }

    function updateConditions(form) {
        var valid = true;
        form.querySelectorAll('[data-red-addon-public-mutation-field]').forEach(
            function (wrapper) {
                var controls = wrapper.querySelectorAll('input, textarea');
                if (controls.length !== 1) {
                    valid = false;
                    return;
                }
                var requiredWhen = condition(form, wrapper, 'required-when');
                var visibleWhen = condition(form, wrapper, 'visible-when');
                if (!requiredWhen.valid || !visibleWhen.valid) {
                    valid = false;
                    return;
                }
                if (requiredWhen.active) {
                    controls[0].required = requiredWhen.matches;
                }
                if (visibleWhen.active) {
                    if (!visibleWhen.matches) {
                        controls[0].value = '';
                    }
                    wrapper.hidden = !visibleWhen.matches;
                }
            }
        );
        return valid;
    }

    function statusElement(form) {
        var statuses = form.querySelectorAll(statusSelector);
        return statuses.length === 1 ? statuses[0] : null;
    }

    function submitButton(form) {
        var buttons = form.querySelectorAll('button[type="submit"]');
        return buttons.length === 1 ? buttons[0] : null;
    }

    function setStatus(state, message) {
        state.status.textContent = message;
    }

    function setBusy(state, busy) {
        state.busy = busy;
        state.form.setAttribute('aria-busy', busy ? 'true' : 'false');
        state.submit.disabled = busy || state.finished;
    }

    function freezeCommand(state) {
        state.form.querySelectorAll('input, select, textarea').forEach(
            function (control) {
                control.disabled = true;
            }
        );
        state.form.setAttribute('data-red-addon-public-mutation-frozen', 'true');
    }

    function unavailable(state) {
        state.finished = true;
        freezeCommand(state);
        setBusy(state, false);
        setStatus(state, 'This action is unavailable. Refresh the page.');
    }

    function responseResult(response, body, expectedUrl) {
        if (typeof body !== 'string' || body.length < 1 || body.length > 128) {
            return null;
        }
        var contentType = response.headers.get('Content-Type');
        if (response.redirected
            || response.url !== expectedUrl
            || contentType !== 'application/json; charset=UTF-8'
            || response.headers.get('Cache-Control') !== 'no-store'
            || response.headers.get('X-Content-Type-Options') !== 'nosniff'
        ) {
            return null;
        }
        var payload;
        try {
            payload = JSON.parse(body);
        } catch (error) {
            return null;
        }
        if (!payload || Array.isArray(payload) || typeof payload !== 'object') {
            return null;
        }
        var keys = Object.keys(payload).sort();
        if (response.status === 200
            && keys.join(',') === 'ok,outcome'
            && payload.ok === true
            && (payload.outcome === 'accepted' || payload.outcome === 'unchanged')
        ) {
            return {
                complete: true,
                refresh: true,
                message: payload.outcome === 'accepted'
                    ? 'Update completed.'
                    : 'No changes were needed.'
            };
        }
        var refusalStatuses = {
            invalid_request: 400,
            method_not_allowed: 405,
            request_conflict: 409,
            rate_limited: 429,
            temporarily_unavailable: 503
        };
        if (keys.join(',') !== 'ok,reason'
            || payload.ok !== false
            || refusalStatuses[payload.reason] !== response.status
        ) {
            return null;
        }
        if (payload.reason === 'rate_limited') {
            return {
                complete: false,
                refresh: false,
                message: 'Too many attempts. Try again shortly.'
            };
        }
        if (payload.reason === 'temporarily_unavailable') {
            return {
                complete: false,
                refresh: false,
                message: 'This update is temporarily unavailable. Try again.'
            };
        }
        return {
            complete: true,
            refresh: false,
            message: 'Could not complete this update. Refresh the page.'
        };
    }

    async function send(state) {
        var refresh = false;
        setBusy(state, true);
        setStatus(state, 'Updating…');
        try {
            var response = await window.fetch(state.url.href, {
                method: 'POST',
                mode: 'same-origin',
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'error',
                referrerPolicy: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-RED-CMS-CSRF': state.csrfToken,
                    'Idempotency-Key': state.idempotencyKey
                },
                body: state.body
            });
            var result = responseResult(
                response,
                await response.text(),
                state.url.href
            );
            if (result === null) {
                state.finished = true;
                setStatus(
                    state,
                    'Could not complete this update. Refresh the page.'
                );
            } else {
                state.finished = result.complete;
                refresh = result.refresh;
                setStatus(state, result.message);
            }
        } catch (error) {
            state.finished = false;
            setStatus(state, 'Could not complete this update. Try again.');
        }
        setBusy(state, false);
        if (refresh) {
            window.setTimeout(function () {
                window.location.reload();
            }, 750);
        }
    }

    function configure(form) {
        if (states.has(form)) {
            return;
        }
        var state = {
            form: form,
            status: statusElement(form),
            submit: submitButton(form),
            url: null,
            csrfToken: form.getAttribute('data-red-csrf-token') || '',
            idempotencyKey:
                form.getAttribute('data-red-idempotency-key') || '',
            body: '',
            busy: false,
            finished: false,
            configured: false
        };
        form.removeAttribute('data-red-csrf-token');
        form.removeAttribute('data-red-idempotency-key');
        states.set(form, state);
        if (!state.status || !state.submit) {
            return;
        }
        state.url = routeUrl(form);
        state.configured = state.url !== null
            && form.getAttribute('method') === 'post'
            && form.getAttribute('enctype')
                === 'application/x-www-form-urlencoded'
            && form.getAttribute('data-red-csrf-header')
                === 'X-RED-CMS-CSRF'
            && form.getAttribute('data-red-idempotency-header')
                === 'Idempotency-Key'
            && tokenPattern.test(state.csrfToken)
            && tokenPattern.test(state.idempotencyKey)
            && updateConditions(form);
        form.setAttribute(
            'data-red-addon-public-mutation-controller',
            state.configured ? 'ready' : 'unavailable'
        );
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (state.busy || state.finished) {
                return;
            }
            if (!state.configured) {
                unavailable(state);
                return;
            }
            if (!updateConditions(form)) {
                unavailable(state);
                return;
            }
            if (state.body === '') {
                state.body = formBody(form);
                if (state.body === '') {
                    unavailable(state);
                    return;
                }
                freezeCommand(state);
            }
            send(state);
        });
        form.addEventListener('change', function () {
            if (!state.busy && !state.finished && !updateConditions(form)) {
                unavailable(state);
            }
        });
    }

    function initialize() {
        document.querySelectorAll(formSelector).forEach(configure);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, {once: true});
    } else {
        initialize();
    }
}());
