(function () {
    'use strict';

    var itemSelector = '[data-red-layout-item="true"]';
    var positionSelector = '[data-red-editor-position]';

    function csrfToken() {
        return typeof window.RED_CSRF_TOKEN === 'string' ? window.RED_CSRF_TOKEN : '';
    }

    function cloneItems(items) {
        return items.map(function (item) {
            return {
                recordId: Number(item.recordId),
                position: Number(item.position),
                order: Number(item.order)
            };
        });
    }

    function sameItems(left, right) {
        return JSON.stringify(left) === JSON.stringify(right);
    }

    function slotContent(position) {
        return position.querySelector('[data-red-editor-slot-content="true"]');
    }

    function cardsIn(position) {
        var content = slotContent(position);
        return content ? Array.prototype.slice.call(content.querySelectorAll(':scope > ' + itemSelector)) : [];
    }

    function positionsFor(root) {
        return Array.prototype.slice.call(root.querySelectorAll(positionSelector)).map(function (position) {
            return {
                element: position,
                value: Number(position.dataset.redEditorPosition),
                label: position.dataset.redPositionLabel || ('Position ' + position.dataset.redEditorPosition)
            };
        });
    }

    function visualItems(root) {
        var items = [];
        positionsFor(root).forEach(function (position) {
            cardsIn(position.element).forEach(function (card, index) {
                items.push({
                    recordId: Number(card.dataset.recordId),
                    position: position.value,
                    order: index + 1
                });
            });
        });
        return items.sort(function (left, right) {
            return left.recordId - right.recordId;
        });
    }

    function storedItems(root) {
        return Array.prototype.slice.call(root.querySelectorAll(itemSelector)).map(function (card) {
            return {
                recordId: Number(card.dataset.recordId),
                position: Number(card.dataset.position),
                order: Number(card.dataset.order)
            };
        }).sort(function (left, right) {
            return left.recordId - right.recordId;
        });
    }

    function positionByValue(root, value) {
        return positionsFor(root).find(function (position) {
            return position.value === Number(value);
        }) || null;
    }

    function ordinal(value) {
        var number = Number(value);
        var remainder = number % 100;
        if (remainder >= 11 && remainder <= 13) {
            return number + 'th';
        }
        if (number % 10 === 1) {
            return number + 'st';
        }
        if (number % 10 === 2) {
            return number + 'nd';
        }
        if (number % 10 === 3) {
            return number + 'rd';
        }
        return number + 'th';
    }

    function setStatus(root, message, state) {
        var status = root.querySelector('[data-red-layout-status="true"]');
        if (!status) {
            return;
        }
        status.textContent = message || '';
        status.dataset.state = state || '';
    }

    function refresh(root) {
        var options = positionsFor(root);
        options.forEach(function (position) {
            var cards = cardsIn(position.element);
            var empty = position.element.querySelector('.red-admin-position__empty');
            position.element.classList.toggle('is-empty', cards.length === 0);
            if (empty) {
                empty.hidden = true;
            }
            cards.forEach(function (card, index) {
                card.dataset.position = String(position.value);
                card.dataset.order = String(index + 1);
                var placement = card.querySelector('[data-red-layout-placement="true"]');
                if (placement) {
                    placement.textContent = position.label + ' · ' + ordinal(index + 1);
                }
                var select = card.querySelector('[data-red-layout-position-select="true"]');
                if (select) {
                    select.textContent = '';
                    options.forEach(function (option) {
                        var node = document.createElement('option');
                        node.value = String(option.value);
                        node.textContent = option.value === 0 ? 'Hidden content' : option.label;
                        node.selected = option.value === position.value;
                        select.appendChild(node);
                    });
                }
                var up = card.querySelector('[data-red-layout-action="up"]');
                var down = card.querySelector('[data-red-layout-action="down"]');
                if (up) {
                    up.disabled = index === 0;
                }
                if (down) {
                    down.disabled = index === cards.length - 1;
                }
            });
        });
    }

    function arrangeToState(root, state) {
        var byPosition = {};
        state.forEach(function (item) {
            var key = String(item.position);
            if (!byPosition[key]) {
                byPosition[key] = [];
            }
            byPosition[key].push(item);
        });
        Object.keys(byPosition).forEach(function (key) {
            byPosition[key].sort(function (left, right) {
                return left.order - right.order || left.recordId - right.recordId;
            });
            var position = positionByValue(root, Number(key));
            var content = position ? slotContent(position.element) : null;
            if (!content) {
                return;
            }
            byPosition[key].forEach(function (item) {
                var card = root.querySelector(itemSelector + '[data-record-id="' + item.recordId + '"]');
                if (card) {
                    content.appendChild(card);
                }
            });
            if (Number(key) === 0 && position.element.tagName === 'DETAILS') {
                position.element.open = true;
            }
        });
        refresh(root);
    }

    function postLayout(root, expected, target) {
        var body = new URLSearchParams();
        body.append('Layout', root.dataset.redLayoutId || '');
        body.append('VarPosition', root.dataset.redPositionColumn || '');
        body.append('ExpectedItems', JSON.stringify(expected));
        body.append('Items', JSON.stringify(target));
        return fetch(root.dataset.redLayoutEndpoint || '/admin/bin/update_layout_distribution.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': csrfToken()
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

    function showUndo(root, message) {
        var undo = root.querySelector('[data-red-layout-undo="true"]');
        var copy = root.querySelector('[data-red-layout-undo-message="true"]');
        if (copy) {
            copy.textContent = message;
        }
        if (undo) {
            undo.hidden = false;
        }
    }

    function hideUndo(root) {
        var undo = root.querySelector('[data-red-layout-undo="true"]');
        if (undo) {
            undo.hidden = true;
        }
    }

    function save(root, state, rollbackState, options) {
        var target = visualItems(root);
        if (sameItems(target, state.expected)) {
            refresh(root);
            return Promise.resolve(false);
        }
        root.dataset.saving = 'true';
        root.setAttribute('aria-busy', 'true');
        setStatus(root, options && options.undo ? 'Undoing…' : 'Saving arrangement…', 'loading');
        return postLayout(root, state.expected, target).then(function (data) {
            state.expected = cloneItems(data.items || target);
            refresh(root);
            setStatus(
                root,
                data.changed === 1 ? '1 component updated' : data.changed + ' components updated',
                'success'
            );
            if (options && options.undo) {
                state.undo = null;
                hideUndo(root);
            } else {
                state.undo = cloneItems(rollbackState);
                showUndo(root, 'Page structure updated.');
            }
            return true;
        }).catch(function (error) {
            arrangeToState(root, rollbackState);
            if (error.reason === 'conflict') {
                root.dataset.conflict = 'true';
                setStatus(root, 'This page changed in another window. Reload before arranging it again.', 'error');
            } else {
                setStatus(root, 'The arrangement could not be saved. No content was changed.', 'error');
            }
            throw error;
        }).finally(function () {
            delete root.dataset.saving;
            root.removeAttribute('aria-busy');
        });
    }

    function init(root) {
        if (!root || root.dataset.redLayoutReady === 'true') {
            return;
        }
        root.dataset.redLayoutReady = 'true';
        var state = {
            expected: storedItems(root),
            undo: null,
            armed: null,
            dragging: null,
            dropped: false,
            rollback: null
        };
        var restricted = !!root.querySelector('[data-red-layout-restricted="true"]');
        if (restricted) {
            root.dataset.restricted = 'true';
            root.querySelectorAll('[data-red-layout-drag-handle="true"], [data-red-layout-position-select="true"], [data-red-layout-action]').forEach(function (control) {
                control.disabled = true;
            });
            setStatus(root, 'Arrangement is unavailable because this page contains content outside your permissions.', 'notice');
        }

        refresh(root);

        root.addEventListener('pointerdown', function (event) {
            var handle = event.target.closest('[data-red-layout-drag-handle="true"]');
            state.armed = handle ? handle.closest(itemSelector) : null;
        });
        root.addEventListener('pointerup', function () {
            if (!state.dragging) {
                state.armed = null;
            }
        });

        root.addEventListener('dragstart', function (event) {
            var card = event.target.closest(itemSelector);
            if (!card || card !== state.armed || restricted || root.dataset.saving === 'true' || root.dataset.conflict === 'true') {
                event.preventDefault();
                return;
            }
            state.dragging = card;
            state.dropped = false;
            state.rollback = visualItems(root);
            card.classList.add('is-dragging');
            root.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.recordId || '');
        });

        root.querySelectorAll(positionSelector).forEach(function (position) {
            var content = slotContent(position);
            if (!content) {
                return;
            }
            content.addEventListener('dragover', function (event) {
                if (!state.dragging) {
                    return;
                }
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                position.classList.add('is-drop-target');
                var siblings = cardsIn(position).filter(function (card) {
                    return card !== state.dragging;
                });
                var next = siblings.find(function (card) {
                    var box = card.getBoundingClientRect();
                    return event.clientY < box.top + box.height / 2;
                });
                if (next) {
                    content.insertBefore(state.dragging, next);
                } else {
                    content.appendChild(state.dragging);
                }
                if (position.tagName === 'DETAILS') {
                    position.open = true;
                }
                refresh(root);
            });
            content.addEventListener('dragleave', function (event) {
                if (!position.contains(event.relatedTarget)) {
                    position.classList.remove('is-drop-target');
                }
            });
            content.addEventListener('drop', function (event) {
                if (!state.dragging) {
                    return;
                }
                event.preventDefault();
                state.dropped = true;
                position.classList.remove('is-drop-target');
                save(root, state, state.rollback).catch(function () {});
            });
        });

        root.addEventListener('dragend', function () {
            root.querySelectorAll('.is-drop-target').forEach(function (position) {
                position.classList.remove('is-drop-target');
            });
            if (state.dragging) {
                state.dragging.classList.remove('is-dragging');
            }
            if (!state.dropped && state.rollback) {
                arrangeToState(root, state.rollback);
            }
            root.classList.remove('is-dragging');
            state.armed = null;
            state.dragging = null;
            state.rollback = null;
        });

        root.addEventListener('click', function (event) {
            var action = event.target.closest('[data-red-layout-action]');
            if (!action || restricted || root.dataset.saving === 'true' || root.dataset.conflict === 'true') {
                return;
            }
            var card = action.closest(itemSelector);
            var position = card ? card.closest(positionSelector) : null;
            if (!card || !position) {
                return;
            }
            var cards = cardsIn(position);
            var index = cards.indexOf(card);
            var direction = action.dataset.redLayoutAction;
            var rollback = visualItems(root);
            if (direction === 'up' && index > 0) {
                slotContent(position).insertBefore(card, cards[index - 1]);
            } else if (direction === 'down' && index >= 0 && index < cards.length - 1) {
                slotContent(position).insertBefore(cards[index + 1], card);
            } else {
                return;
            }
            refresh(root);
            save(root, state, rollback).then(function () {
                var menu = card.querySelector('.red-admin-layout-item__menu');
                if (menu) {
                    menu.open = false;
                }
                card.querySelector('[data-red-layout-drag-handle="true"]').focus();
            }).catch(function () {});
        });

        root.addEventListener('change', function (event) {
            var select = event.target.closest('[data-red-layout-position-select="true"]');
            if (!select || restricted || root.dataset.saving === 'true' || root.dataset.conflict === 'true') {
                return;
            }
            var card = select.closest(itemSelector);
            var targetPosition = positionByValue(root, Number(select.value));
            var targetContent = targetPosition ? slotContent(targetPosition.element) : null;
            if (!card || !targetContent) {
                refresh(root);
                return;
            }
            var rollback = visualItems(root);
            targetContent.appendChild(card);
            if (targetPosition.element.tagName === 'DETAILS') {
                targetPosition.element.open = true;
            }
            refresh(root);
            save(root, state, rollback).then(function () {
                var menu = card.querySelector('.red-admin-layout-item__menu');
                if (menu) {
                    menu.open = false;
                }
                card.querySelector('[data-red-layout-drag-handle="true"]').focus();
            }).catch(function () {});
        });

        var undoButton = root.querySelector('[data-red-layout-undo-button="true"]');
        if (undoButton) {
            undoButton.addEventListener('click', function () {
                if (!state.undo || root.dataset.saving === 'true' || root.dataset.conflict === 'true') {
                    return;
                }
                var rollback = visualItems(root);
                arrangeToState(root, state.undo);
                save(root, state, rollback, {undo: true}).catch(function () {});
            });
        }
    }

    function scan() {
        document.querySelectorAll('[data-red-editor-workspace="page-layout"]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
}());
