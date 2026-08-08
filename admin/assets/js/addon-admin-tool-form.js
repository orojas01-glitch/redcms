(function () {
    'use strict';

    function csrfToken() {
        return typeof window.RED_CSRF_TOKEN === 'string'
            ? window.RED_CSRF_TOKEN
            : '';
    }

    function directChildren(parent, selector) {
        return Array.prototype.filter.call(parent.children, function (child) {
            return child.matches(selector);
        });
    }

    function collectionItems(collection) {
        var container = directChildren(
            collection,
            '[data-red-addon-admin-form-items]'
        )[0];
        return container
            ? directChildren(container, '[data-red-addon-admin-form-item]')
            : [];
    }

    function updateCollection(collection) {
        var count = collectionItems(collection).length;
        var minimum = Number(collection.dataset.minItems || '0');
        var maximum = Number(collection.dataset.maxItems || '0');
        var add = directChildren(
            collection,
            '[data-red-addon-admin-form-add]'
        )[0];
        if (add) {
            add.disabled = count >= maximum;
        }
        collectionItems(collection).forEach(function (item) {
            var remove = directChildren(
                item,
                '[data-red-addon-admin-form-remove]'
            )[0];
            if (remove) {
                remove.disabled = count <= minimum;
            }
        });
    }

    function addCollectionItem(collection) {
        var items = directChildren(
            collection,
            '[data-red-addon-admin-form-items]'
        )[0];
        var template = directChildren(
            collection,
            '[data-red-addon-admin-form-template]'
        )[0];
        var maximum = Number(collection.dataset.maxItems || '0');
        if (!items || !template || collectionItems(collection).length >= maximum) {
            return null;
        }
        var item = template.content.firstElementChild
            ? template.content.firstElementChild.cloneNode(true)
            : null;
        if (!item) {
            return null;
        }
        items.appendChild(item);
        ensureMinimumCollections(item);
        updateCollection(collection);
        return item;
    }

    function ensureMinimumCollections(root) {
        root.querySelectorAll('[data-red-addon-admin-form-collection]').forEach(
            function (collection) {
                var minimum = Number(collection.dataset.minItems || '0');
                while (collectionItems(collection).length < minimum) {
                    if (!addCollectionItem(collection)) {
                        break;
                    }
                }
                updateCollection(collection);
            }
        );
    }

    function scalarValue(field) {
        var control = field.querySelector('[data-red-addon-admin-form-control]');
        var type = field.dataset.fieldType || '';
        if (!control) {
            throw new Error('invalid_form');
        }
        if (type === 'integer') {
            if (control.value === '') {
                return null;
            }
            var integer = Number(control.value);
            if (!Number.isSafeInteger(integer)) {
                throw new Error('invalid_values');
            }
            return integer;
        }
        if (type === 'boolean') {
            if (control.value === '') {
                return null;
            }
            if (control.value !== 'true' && control.value !== 'false') {
                throw new Error('invalid_values');
            }
            return control.value === 'true';
        }
        if (control.value === '' && !control.required) {
            return null;
        }
        if (control.value === '' && [
            'select',
            'url',
            'email',
            'date',
            'datetime',
            'media-reference'
        ].indexOf(type) !== -1) {
            return null;
        }
        return control.value;
    }

    function objectValues(object) {
        var values = {};
        directChildren(
            object,
            '[data-red-addon-admin-form-field],'
                + '[data-red-addon-admin-form-collection]'
        ).forEach(function (field) {
            var key = field.dataset.fieldKey || '';
            if (!key || Object.prototype.hasOwnProperty.call(values, key)) {
                throw new Error('invalid_form');
            }
            if (field.hasAttribute('data-red-addon-admin-form-collection')) {
                values[key] = collectionItems(field).map(function (item) {
                    var itemObject = directChildren(
                        item,
                        '[data-red-addon-admin-form-object]'
                    )[0];
                    if (!itemObject) {
                        throw new Error('invalid_form');
                    }
                    return objectValues(itemObject);
                });
            } else {
                values[key] = scalarValue(field);
            }
        });
        return values;
    }

    function responseJson(response) {
        return response.json().catch(function () {
            return {ok: false, reason: 'invalid_response'};
        }).then(function (data) {
            if (!response.ok || !data.ok) {
                var error = new Error(data.reason || 'save_failed');
                error.reason = data.reason || 'save_failed';
                throw error;
            }
            return data;
        });
    }

    function reloadEditor(form, targetRecordId) {
        var body = new URLSearchParams();
        body.append('tool', form.dataset.tool || '');
        body.append('form', form.dataset.form || '');
        body.append(
            'targetRecordId',
            String(targetRecordId || form.dataset.targetRecordId || '')
        );
        return fetch(form.dataset.editAction || '', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'X-CSRF-Token': csrfToken()},
            body: body
        }).then(function (response) {
            return response.text().then(function (html) {
                if (!response.ok) {
                    throw new Error('reload_failed');
                }
                var workspace = form.closest(
                    '[data-red-addon-admin-form-workspace]'
                );
                if (!workspace) {
                    throw new Error('reload_failed');
                }
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                var nextWorkspace = wrapper.firstElementChild;
                if (!nextWorkspace
                    || !nextWorkspace.hasAttribute(
                        'data-red-addon-admin-form-workspace'
                    )
                ) {
                    throw new Error('reload_failed');
                }
                workspace.replaceWith(nextWorkspace);
                return nextWorkspace.querySelector(
                    '[data-red-addon-admin-form-status]'
                );
            });
        });
    }

    function failureMessage(reason, creating) {
        if (reason === 'state_conflict') {
            return creating
                ? 'This draft changed after it was opened. Start again before creating.'
                : 'This form changed after it was opened. Reopen it before saving.';
        }
        if (reason === 'invalid_values') {
            return 'Review the highlighted values and try again.';
        }
        if (reason === 'permission_denied') {
            return creating
                ? 'You no longer have permission to create this record.'
                : 'You no longer have permission to save this form.';
        }
        return creating
            ? 'The record could not be created. No changes were applied.'
            : 'The form could not be saved. No changes were applied.';
    }

    function openTarget(button) {
        var body = new URLSearchParams();
        body.append('tool', button.dataset.tool || '');
        body.append('form', button.dataset.form || '');
        body.append('targetRecordId', button.dataset.targetRecordId || '');
        button.disabled = true;
        return fetch(button.dataset.editAction || '', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'X-CSRF-Token': csrfToken()},
            body: body
        }).then(function (response) {
            return response.text().then(function (html) {
                if (!response.ok) {
                    throw new Error('open_failed');
                }
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                var workspace = wrapper.firstElementChild;
                var tool = button.closest('.red-admin-addon-tool');
                if (!workspace
                    || !workspace.hasAttribute(
                        'data-red-addon-admin-form-workspace'
                    )
                    || !tool
                ) {
                    throw new Error('open_failed');
                }
                tool.replaceWith(workspace);
                ensureMinimumCollections(workspace);
            });
        }).catch(function () {
            button.disabled = false;
        });
    }

    function openCreate(button) {
        var body = new URLSearchParams();
        body.append('tool', button.dataset.tool || '');
        body.append('form', button.dataset.form || '');
        button.disabled = true;
        return fetch(button.dataset.createAction || '', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'X-CSRF-Token': csrfToken()},
            body: body
        }).then(function (response) {
            return response.text().then(function (html) {
                if (!response.ok) {
                    throw new Error('open_failed');
                }
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html;
                var workspace = wrapper.firstElementChild;
                var tool = button.closest('.red-admin-addon-tool');
                if (!workspace
                    || !workspace.hasAttribute(
                        'data-red-addon-admin-form-workspace'
                    )
                    || !tool
                ) {
                    throw new Error('open_failed');
                }
                tool.replaceWith(workspace);
                ensureMinimumCollections(workspace);
                var first = workspace.querySelector(
                    '[data-red-addon-admin-form-control]'
                );
                if (first) {
                    first.focus();
                }
            });
        }).catch(function () {
            button.disabled = false;
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target.closest(
            '[data-red-addon-admin-form-target]'
        );
        if (target) {
            event.preventDefault();
            openTarget(target);
            return;
        }
        var create = event.target.closest(
            '[data-red-addon-admin-form-create-target]'
        );
        if (create) {
            event.preventDefault();
            openCreate(create);
            return;
        }
        var add = event.target.closest('[data-red-addon-admin-form-add]');
        if (add) {
            event.preventDefault();
            var collection = add.closest(
                '[data-red-addon-admin-form-collection]'
            );
            var item = collection ? addCollectionItem(collection) : null;
            var control = item
                ? item.querySelector('[data-red-addon-admin-form-control]')
                : null;
            if (control) {
                control.focus();
            }
            return;
        }
        var remove = event.target.closest(
            '[data-red-addon-admin-form-remove]'
        );
        if (!remove) {
            return;
        }
        event.preventDefault();
        var item = remove.closest('[data-red-addon-admin-form-item]');
        var parentCollection = remove.closest(
            '[data-red-addon-admin-form-collection]'
        );
        if (!item || !parentCollection) {
            return;
        }
        var minimum = Number(parentCollection.dataset.minItems || '0');
        if (collectionItems(parentCollection).length <= minimum) {
            updateCollection(parentCollection);
            return;
        }
        item.remove();
        updateCollection(parentCollection);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest(
            '[data-red-addon-admin-form-edit],'
                + '[data-red-addon-admin-form-create]'
        );
        if (!form) {
            return;
        }
        var creating = form.hasAttribute('data-red-addon-admin-form-create');
        event.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        var submit = form.querySelector('button[type="submit"]');
        var status = form.querySelector(
            '[data-red-addon-admin-form-status]'
        );
        if (submit) {
            submit.disabled = true;
        }
        if (status) {
            status.hidden = false;
            status.textContent = creating
                ? 'Creating record…'
                : 'Saving changes…';
        }
        var root = directChildren(
            form,
            '[data-red-addon-admin-form-object]'
        )[0];
        var payload;
        try {
            payload = creating ? {
                tool: form.dataset.tool || '',
                form: form.dataset.form || '',
                initialStateSha256: form.dataset.initialStateSha256 || '',
                values: objectValues(root)
            } : {
                tool: form.dataset.tool || '',
                form: form.dataset.form || '',
                targetRecordId: Number(form.dataset.targetRecordId || '0'),
                currentStateSha256: form.dataset.currentStateSha256 || '',
                values: objectValues(root)
            };
        } catch (error) {
            if (status) {
                status.textContent = failureMessage(error.message, creating);
            }
            if (submit) {
                submit.disabled = false;
            }
            return;
        }
        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken()
            },
            body: JSON.stringify(payload)
        }).then(responseJson).then(function (data) {
            if (creating) {
                if (data.status !== 'created'
                    || !Number.isSafeInteger(data.targetRecordId)
                    || data.targetRecordId < 1
                ) {
                    throw new Error('create_failed');
                }
                return reloadEditor(form, data.targetRecordId).then(
                    function (nextStatus) {
                        if (nextStatus) {
                            nextStatus.hidden = false;
                            nextStatus.textContent = 'Record created.';
                        }
                    }
                );
            }
            if (data.status === 'unchanged') {
                if (status) {
                    status.textContent = 'No changes to save.';
                }
                if (submit) {
                    submit.disabled = false;
                }
                return;
            }
            return reloadEditor(form).then(function (nextStatus) {
                if (nextStatus) {
                    nextStatus.hidden = false;
                    nextStatus.textContent = 'Changes saved.';
                }
            });
        }).catch(function (error) {
            if (status) {
                status.textContent = failureMessage(
                    error.reason || error.message,
                    creating
                );
            }
            if (submit) {
                submit.disabled = false;
            }
        });
    });

    document.querySelectorAll(
        '[data-red-addon-admin-form-edit],'
            + '[data-red-addon-admin-form-create]'
    ).forEach(
        function (form) {
            ensureMinimumCollections(form);
        }
    );
}());
