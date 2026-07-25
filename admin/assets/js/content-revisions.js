(function () {
    'use strict';

    var endpoint = '/admin/bin/content_revisions.php';

    function token() {
        return typeof window.RED_CSRF_TOKEN === 'string' ? window.RED_CSRF_TOKEN : '';
    }

    function request(payload) {
        var body = new URLSearchParams();
        Object.keys(payload).forEach(function (key) {
            body.append(key, String(payload[key]));
        });
        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': token()
            },
            body: body.toString()
        }).then(function (response) {
            return response.json().catch(function () {
                return {ok: false, reason: 'invalid-response'};
            }).then(function (data) {
                if (!response.ok || !data.ok) {
                    var error = new Error(data.reason || 'request-failed');
                    error.reason = data.reason || 'request-failed';
                    throw error;
                }
                return data;
            });
        });
    }

    function icon(path) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        var shape = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        shape.setAttribute('d', path);
        svg.appendChild(shape);
        return svg;
    }

    function setStatus(panel, message, state) {
        var status = panel.querySelector('[data-red-revision-status]');
        if (!status) {
            return;
        }
        status.textContent = message;
        status.dataset.state = state || '';
        status.hidden = message === '';
    }

    function confirmation(card, panel, revision) {
        var existing = card.querySelector('.red-admin-revision__confirm');
        if (existing) {
            existing.remove();
            return;
        }

        panel.querySelectorAll('.red-admin-revision__confirm').forEach(function (node) {
            node.remove();
        });

        var confirm = document.createElement('div');
        confirm.className = 'red-admin-revision__confirm';

        var copy = document.createElement('p');
        copy.textContent = 'Restore version ' + revision.revisionNumber + '? Your current version will remain in history.';
        confirm.appendChild(copy);

        var actions = document.createElement('div');
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'red-admin-revision__cancel';
        cancel.textContent = 'Cancel';
        cancel.addEventListener('click', function () {
            confirm.remove();
        });

        var restore = document.createElement('button');
        restore.type = 'button';
        restore.className = 'red-admin-revision__confirm-restore';
        restore.appendChild(icon('M4 12a8 8 0 1 0 2.3-5.7L4 8.6M4 4v4.6h4.6'));
        var restoreLabel = document.createElement('span');
        restoreLabel.textContent = 'Restore version ' + revision.revisionNumber;
        restore.appendChild(restoreLabel);
        restore.addEventListener('click', function () {
            restore.disabled = true;
            cancel.disabled = true;
            panel.setAttribute('aria-busy', 'true');
            setStatus(panel, 'Restoring version ' + revision.revisionNumber + '…', 'loading');
            request({
                action: 'restore',
                ContentRecordID: panel.dataset.contentRecordId,
                RevisionID: revision.revisionId,
                CurrentHash: panel.dataset.currentHash
            }).then(function () {
                setStatus(panel, 'Version restored. Reloading the editor…', 'success');
                window.setTimeout(function () {
                    window.location.reload();
                }, 550);
            }).catch(function (error) {
                var message = error.reason === 'conflict'
                    ? 'This content changed after the history was loaded. Reload the history before restoring.'
                    : 'The version could not be restored. No content was changed.';
                setStatus(panel, message, 'error');
                restore.disabled = false;
                cancel.disabled = false;
                panel.removeAttribute('aria-busy');
            });
        });

        actions.appendChild(cancel);
        actions.appendChild(restore);
        confirm.appendChild(actions);
        card.appendChild(confirm);
        cancel.focus();
    }

    function revisionCard(panel, revision) {
        var item = document.createElement('li');
        item.className = 'red-admin-revision';
        if (revision.isCurrent) {
            item.classList.add('red-admin-revision--current');
        }

        var marker = document.createElement('span');
        marker.className = 'red-admin-revision__marker';
        marker.textContent = String(revision.revisionNumber);
        item.appendChild(marker);

        var content = document.createElement('div');
        content.className = 'red-admin-revision__content';

        var heading = document.createElement('div');
        heading.className = 'red-admin-revision__heading';
        var title = document.createElement('strong');
        title.textContent = 'Version ' + revision.revisionNumber;
        heading.appendChild(title);
        if (revision.isCurrent) {
            var current = document.createElement('span');
            current.className = 'red-admin-revision__current';
            current.textContent = 'Current';
            heading.appendChild(current);
        }
        content.appendChild(heading);

        var summary = document.createElement('p');
        summary.className = 'red-admin-revision__summary';
        summary.textContent = revision.summary || 'Saved version';
        content.appendChild(summary);

        var meta = document.createElement('p');
        meta.className = 'red-admin-revision__meta';
        meta.textContent = 'Saved by ' + revision.actorAlias + ' · ' + revision.createdLabel;
        content.appendChild(meta);

        if (Array.isArray(revision.changes) && revision.changes.length) {
            var changes = document.createElement('ul');
            changes.className = 'red-admin-revision__changes';
            revision.changes.forEach(function (change) {
                var changeItem = document.createElement('li');
                changeItem.textContent = change;
                changes.appendChild(changeItem);
            });
            content.appendChild(changes);
        }
        item.appendChild(content);

        if (!revision.isCurrent && revision.operation !== 'delete') {
            var restore = document.createElement('button');
            restore.type = 'button';
            restore.className = 'red-admin-revision__restore';
            restore.appendChild(icon('M4 12a8 8 0 1 0 2.3-5.7L4 8.6M4 4v4.6h4.6'));
            var label = document.createElement('span');
            label.textContent = 'Restore';
            restore.appendChild(label);
            restore.setAttribute('aria-label', 'Restore version ' + revision.revisionNumber);
            restore.addEventListener('click', function () {
                confirmation(item, panel, revision);
            });
            item.appendChild(restore);
        }
        return item;
    }

    function render(panel, data) {
        var list = panel.querySelector('[data-red-revision-list]');
        var count = panel.querySelector('[data-red-revision-count]');
        list.textContent = '';
        panel.dataset.currentHash = data.currentHash || '';

        if (!data.available) {
            count.textContent = 'Unavailable';
            setStatus(panel, 'Version history will become available after the database migration is applied.', 'error');
            return;
        }
        var revisions = Array.isArray(data.revisions) ? data.revisions : [];
        var total = Number.isFinite(Number(data.total)) ? Number(data.total) : revisions.length;
        count.textContent = total > revisions.length
            ? 'Latest ' + revisions.length + ' of ' + total
            : total + (total === 1 ? ' version' : ' versions');
        if (!revisions.length) {
            setStatus(panel, 'The first version will be created automatically the next time this content is saved.', 'empty');
            return;
        }
        setStatus(panel, '', '');
        revisions.forEach(function (revision) {
            list.appendChild(revisionCard(panel, revision));
        });
    }

    function load(panel, force) {
        if (!panel || panel.dataset.loading === 'true' || (!force && panel.dataset.loaded === 'true')) {
            return;
        }
        panel.dataset.loading = 'true';
        panel.setAttribute('aria-busy', 'true');
        setStatus(panel, 'Loading version history…', 'loading');
        request({
            action: 'list',
            ContentRecordID: panel.dataset.contentRecordId
        }).then(function (data) {
            render(panel, data);
            panel.dataset.loaded = 'true';
        }).catch(function () {
            setStatus(panel, 'Version history could not be loaded. Please try again.', 'error');
        }).finally(function () {
            panel.dataset.loading = 'false';
            panel.removeAttribute('aria-busy');
        });
    }

    document.addEventListener('click', function (event) {
        var summary = event.target.closest('[data-red-revision-summary]');
        if (!summary) {
            return;
        }
        var panel = summary.closest('[data-red-revision-panel]');
        window.setTimeout(function () {
            if (panel.open) {
                load(panel, false);
            }
        }, 0);
    });

    if (window.jQuery) {
        window.jQuery(document).ajaxComplete(function (_event, xhr) {
            var contentRecordId = xhr.getResponseHeader('X-RED-Content-Record');
            var revisionHash = xhr.getResponseHeader('X-RED-Revision-Hash');
            if (!contentRecordId || !revisionHash) {
                return;
            }
            document.querySelectorAll('[data-red-revision-panel][data-content-record-id="' + contentRecordId + '"]').forEach(function (panel) {
                panel.dataset.currentHash = revisionHash;
                panel.dataset.loaded = 'false';
                if (panel.open) {
                    load(panel, true);
                }
            });
        });
    }
}());
