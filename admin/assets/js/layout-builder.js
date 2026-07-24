(function () {
    'use strict';

    var activeDrag = null;
    var pendingMessage = '';

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function element(tagName, className, text) {
        var node = document.createElement(tagName);
        if (className) {
            node.className = className;
        }
        if (typeof text === 'string') {
            node.textContent = text;
        }
        return node;
    }

    function icon(path) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        var paths = Array.isArray(path) ? path : [path];
        paths.forEach(function (definition) {
            var part = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            part.setAttribute('d', definition);
            svg.appendChild(part);
        });
        return svg;
    }

    function actionButton(label, title, paths, className) {
        var button = element('button', className || 'red-layout-builder__icon-button');
        button.type = 'button';
        button.title = title;
        button.setAttribute('aria-label', title);
        button.appendChild(icon(paths));
        if (label) {
            button.appendChild(element('span', '', label));
        }
        return button;
    }

    function slugPart(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 56)
            .replace(/-+$/g, '');
    }

    function defaultDefinition() {
        return {
            schemaVersion: 1,
            rows: [{
                columns: [{
                    position: 1,
                    span: 12,
                    label: 'Full-width row'
                }]
            }],
            mobile: 'stack'
        };
    }

    function parseBootstrap(builder) {
        var script = builder.querySelector('[data-red-layout-builder-bootstrap]');
        if (!script) {
            return null;
        }
        try {
            return JSON.parse(script.textContent || '{}');
        } catch (error) {
            return null;
        }
    }

    function initialize(scope) {
        var searchRoot = scope && scope.querySelector ? scope : document;
        var builders = searchRoot.matches && searchRoot.matches('[data-red-layout-builder]')
            ? [searchRoot]
            : Array.prototype.slice.call(searchRoot.querySelectorAll('[data-red-layout-builder]'));
        builders.forEach(function (builder) {
            if (builder.getAttribute('data-red-layout-initialized') === 'true') {
                return;
            }
            var config = parseBootstrap(builder);
            if (!config || !builder.querySelector('[data-red-layout-canvas]')) {
                return;
            }
            builder.setAttribute('data-red-layout-initialized', 'true');
            createEditor(builder, config);
        });
    }

    function createEditor(builder, config) {
        var host = builder.parentElement;
        var labelInput = builder.querySelector('[data-red-layout-label]');
        var idInput = builder.querySelector('[data-red-layout-id]');
        var canvas = builder.querySelector('[data-red-layout-canvas]');
        var presetsHost = builder.querySelector('[data-red-layout-presets]');
        var preview = builder.querySelector('[data-red-layout-preview]');
        var previewMap = builder.querySelector('[data-red-layout-preview-map]');
        var validationHost = builder.querySelector('[data-red-layout-validation]');
        var messageHost = builder.querySelector('[data-red-layout-message]');
        var titleHost = builder.querySelector('[data-red-layout-working-title]');
        var stateLabel = builder.querySelector('[data-red-layout-state-label]');
        var saveButton = builder.querySelector('[data-red-layout-save]');
        var publishButton = builder.querySelector('[data-red-layout-publish]');
        var archiveButton = builder.querySelector('[data-red-layout-archive]');
        var selected = config.selected || null;
        var definition = clone(config.initialDefinition || defaultDefinition());
        var dirty = false;
        var busy = false;
        var idTouched = !!selected;
        var lastValidation = null;

        function isArchived() {
            return !!(selected && selected.archived);
        }

        function isEditable() {
            return !busy && !isArchived();
        }

        function status(message, isError) {
            if (!messageHost) {
                return;
            }
            messageHost.textContent = message || '';
            messageHost.classList.toggle('is-error', !!isError);
            messageHost.classList.toggle('is-success', !!message && !isError);
        }

        function setBusy(nextBusy) {
            busy = !!nextBusy;
            builder.classList.toggle('is-busy', busy);
            builder.setAttribute('aria-busy', busy ? 'true' : 'false');
            updateControls();
        }

        function currentLayoutId() {
            var suffix = slugPart(idInput ? idInput.value : '');
            return suffix ? 'custom-' + suffix : '';
        }

        function nextPosition() {
            var positions = availablePositions(1);
            return positions.length ? positions[0] : 0;
        }

        function availablePositions(count) {
            var used = {};
            var highest = 0;
            definition.rows.forEach(function (row) {
                row.columns.forEach(function (column) {
                    var position = Number(column.position) || 0;
                    if (position > 0) {
                        used[position] = true;
                        highest = Math.max(highest, position);
                    }
                });
            });
            var positions = [];
            var candidate;
            for (candidate = highest + 1; candidate <= 99 && positions.length < count; candidate += 1) {
                if (!used[candidate]) {
                    positions.push(candidate);
                }
            }
            for (candidate = 1; candidate <= highest && positions.length < count; candidate += 1) {
                if (!used[candidate]) {
                    positions.push(candidate);
                }
            }
            return positions;
        }

        function positionCount() {
            return definition.rows.reduce(function (total, row) {
                return total + row.columns.length;
            }, 0);
        }

        function validate() {
            var errors = [];
            var label = String(labelInput ? labelInput.value : '').trim();
            var layoutId = currentLayoutId();
            var seen = {};

            if (!label) {
                errors.push('Enter a layout name.');
            }
            if (!/^custom-[a-z0-9](?:[a-z0-9-]{0,54}[a-z0-9])?$/.test(layoutId)) {
                errors.push('Use a stable ID with lowercase letters, numbers, and hyphens.');
            }
            if (!Array.isArray(definition.rows) || definition.rows.length < 1 || definition.rows.length > 48) {
                errors.push('A layout needs between 1 and 48 rows.');
            } else {
                definition.rows.forEach(function (row, rowIndex) {
                    var total = 0;
                    if (!row || !Array.isArray(row.columns) || row.columns.length < 1 || row.columns.length > 12) {
                        errors.push('Row ' + (rowIndex + 1) + ' needs between 1 and 12 columns.');
                        return;
                    }
                    row.columns.forEach(function (column, columnIndex) {
                        var span = Number(column.span);
                        var position = Number(column.position);
                        total += span;
                        if (!Number.isInteger(span) || span < 1 || span > 12) {
                            errors.push('Row ' + (rowIndex + 1) + ', column ' + (columnIndex + 1) + ' has an invalid span.');
                        }
                        if (!Number.isInteger(position) || position < 1 || position > 99 || seen[position]) {
                            errors.push('Every content position must have a unique number from 1 to 99.');
                        }
                        seen[position] = true;
                        if (!String(column.label || '').trim()) {
                            errors.push('Name every content position.');
                        }
                    });
                    if (total !== 12) {
                        errors.push('Row ' + (rowIndex + 1) + ' totals ' + total + ' units; it must total exactly 12.');
                    }
                });
            }

            lastValidation = {
                valid: errors.length === 0,
                errors: errors,
                label: label,
                layoutId: layoutId
            };
            return lastValidation;
        }

        function renderValidation() {
            var result = validate();
            validationHost.textContent = '';
            validationHost.setAttribute('data-state', result.valid ? 'valid' : 'invalid');
            if (result.valid) {
                validationHost.appendChild(icon('M5 12.5 9.2 17 19 7'));
                validationHost.appendChild(element(
                    'span',
                    '',
                    'Ready: ' + definition.rows.length + ' row' +
                        (definition.rows.length === 1 ? '' : 's') + ', ' +
                        positionCount() + ' editable position' +
                        (positionCount() === 1 ? '' : 's') + '.'
                ));
                return;
            }
            validationHost.appendChild(icon(['M12 8v5', 'M12 17h.01', 'M10.3 3.7 2.2 18h19.6L13.7 3.7a2 2 0 0 0-3.4 0Z']));
            var copy = element('span');
            copy.textContent = result.errors[0];
            if (result.errors.length > 1) {
                copy.appendChild(element('small', '', ' +' + (result.errors.length - 1) + ' more'));
            }
            validationHost.appendChild(copy);
        }

        function markDirty() {
            if (!isArchived()) {
                dirty = true;
                status('', false);
            }
            renderAll();
        }

        function updateControls() {
            var valid = (lastValidation || validate()).valid;
            var archived = isArchived();
            if (labelInput) {
                labelInput.disabled = busy || archived;
            }
            if (idInput) {
                idInput.disabled = busy || archived;
            }
            builder.querySelectorAll('[data-red-layout-editor-action]').forEach(function (control) {
                control.disabled = busy || archived || control.hasAttribute('data-layout-action-disabled');
            });
            if (saveButton) {
                saveButton.disabled = busy || archived || !valid || (!!selected && !dirty);
            }
            if (publishButton) {
                publishButton.disabled = busy || archived || !selected || dirty ||
                    !valid || !config.standardThemeActive;
                publishButton.title = dirty
                    ? 'Save the current draft before publishing.'
                    : (!config.standardThemeActive
                        ? 'Publishing requires an active standard template.'
                        : '');
            }
            if (archiveButton) {
                archiveButton.disabled = busy;
            }
            if (stateLabel) {
                if (archived) {
                    stateLabel.textContent = 'Archived';
                } else if (dirty) {
                    stateLabel.textContent = 'Unsaved changes';
                } else if (selected && selected.published && selected.hasUnpublishedChanges) {
                    stateLabel.textContent = 'Published + draft changes';
                } else if (selected && selected.published) {
                    stateLabel.textContent = 'Published';
                } else if (selected) {
                    stateLabel.textContent = 'Draft';
                } else {
                    stateLabel.textContent = 'New draft';
                }
            }
        }

        function createPositionLabel(spans, index) {
            if (spans.length === 1) {
                return 'Full-width row';
            }
            if (spans.length === 2) {
                return index === 0 ? 'Left column' : 'Right column';
            }
            return 'Column ' + (index + 1);
        }

        function addPreset(spans, insertAt) {
            if (!isEditable() || definition.rows.length >= 48 || positionCount() + spans.length > 99) {
                status('This layout has reached its row or position limit.', true);
                return;
            }
            var available = availablePositions(spans.length);
            if (available.length !== spans.length) {
                status('No additional content positions are available.', true);
                return;
            }
            var columns = spans.map(function (span, index) {
                return {
                    position: available[index],
                    span: Number(span),
                    label: createPositionLabel(spans, index)
                };
            });
            var row = {columns: columns};
            if (typeof insertAt === 'number' && insertAt >= 0 && insertAt <= definition.rows.length) {
                definition.rows.splice(insertAt, 0, row);
            } else {
                definition.rows.push(row);
            }
            markDirty();
        }

        function renderPresets() {
            presetsHost.textContent = '';
            (config.presets || []).forEach(function (preset) {
                var button = element('button', 'red-layout-builder__preset');
                button.type = 'button';
                button.draggable = true;
                button.setAttribute('data-red-layout-editor-action', '');
                button.setAttribute('aria-label', 'Add ' + preset.label + ' row');
                var map = element('span', 'red-layout-builder__preset-map');
                preset.spans.forEach(function (span) {
                    var cell = element('span');
                    cell.style.flex = String(span) + ' 1 0';
                    map.appendChild(cell);
                });
                button.appendChild(map);
                button.appendChild(element('strong', '', preset.label));
                button.appendChild(element('small', '', preset.spans.join(' / ')));
                button.addEventListener('click', function () {
                    addPreset(preset.spans);
                });
                button.addEventListener('dragstart', function (event) {
                    activeDrag = {type: 'preset', spans: clone(preset.spans)};
                    button.classList.add('is-dragging');
                    if (event.dataTransfer) {
                        event.dataTransfer.effectAllowed = 'copy';
                        event.dataTransfer.setData('text/plain', 'red-layout-preset:' + preset.id);
                    }
                });
                button.addEventListener('dragend', function () {
                    activeDrag = null;
                    button.classList.remove('is-dragging');
                    clearDropStates();
                });
                presetsHost.appendChild(button);
            });
        }

        function clearDropStates() {
            builder.querySelectorAll('.is-drop-target').forEach(function (target) {
                target.classList.remove('is-drop-target');
            });
        }

        function moveItem(list, from, to) {
            if (from === to || from < 0 || to < 0 || from >= list.length || to >= list.length) {
                return;
            }
            var item = list.splice(from, 1)[0];
            list.splice(to, 0, item);
        }

        function rowDrop(event, rowIndex, rowElement) {
            if (!activeDrag) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            var bounds = rowElement.getBoundingClientRect();
            var after = event.clientY > bounds.top + (bounds.height / 2);
            if (activeDrag.type === 'preset') {
                addPreset(activeDrag.spans, rowIndex + (after ? 1 : 0));
            } else if (activeDrag.type === 'row') {
                var from = activeDrag.rowIndex;
                var to = rowIndex + (after ? 1 : 0);
                if (from < to) {
                    to -= 1;
                }
                if (from !== to) {
                    var row = definition.rows.splice(from, 1)[0];
                    definition.rows.splice(Math.max(0, Math.min(to, definition.rows.length)), 0, row);
                    markDirty();
                }
            }
            activeDrag = null;
            clearDropStates();
        }

        function columnDrop(event, rowIndex, columnIndex, columnElement) {
            if (!activeDrag || activeDrag.type !== 'column' || activeDrag.rowIndex !== rowIndex) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            var bounds = columnElement.getBoundingClientRect();
            var after = event.clientX > bounds.left + (bounds.width / 2);
            var from = activeDrag.columnIndex;
            var to = columnIndex + (after ? 1 : 0);
            if (from < to) {
                to -= 1;
            }
            if (from !== to) {
                var columns = definition.rows[rowIndex].columns;
                var column = columns.splice(from, 1)[0];
                columns.splice(Math.max(0, Math.min(to, columns.length)), 0, column);
                markDirty();
            }
            activeDrag = null;
            clearDropStates();
        }

        function addColumn(rowIndex) {
            var row = definition.rows[rowIndex];
            if (!row || row.columns.length >= 12 || positionCount() >= 99) {
                status('This row cannot accept another column.', true);
                return;
            }
            var donorIndex = -1;
            var donorSpan = 1;
            row.columns.forEach(function (column, index) {
                if (Number(column.span) > donorSpan) {
                    donorSpan = Number(column.span);
                    donorIndex = index;
                }
            });
            if (donorIndex < 0) {
                status('Reduce or rebalance this row before adding another column.', true);
                return;
            }
            row.columns[donorIndex].span -= 1;
            var position = nextPosition();
            row.columns.push({
                position: position,
                span: 1,
                label: 'Column ' + (row.columns.length + 1)
            });
            markDirty();
        }

        function removeColumn(rowIndex, columnIndex) {
            var row = definition.rows[rowIndex];
            if (!row || row.columns.length <= 1) {
                status('A row must keep at least one column. Remove the row instead.', true);
                return;
            }
            var removed = row.columns[columnIndex];
            var recipientIndex = columnIndex > 0 ? columnIndex - 1 : 1;
            row.columns[recipientIndex].span += Number(removed.span);
            row.columns.splice(columnIndex, 1);
            markDirty();
        }

        function duplicateRow(rowIndex) {
            if (definition.rows.length >= 48) {
                status('A layout can contain up to 48 rows.', true);
                return;
            }
            var source = definition.rows[rowIndex];
            if (!source || positionCount() + source.columns.length > 99) {
                status('There are not enough available content positions to duplicate this row.', true);
                return;
            }
            var positions = availablePositions(source.columns.length);
            if (positions.length !== source.columns.length) {
                status('There are not enough available content positions to duplicate this row.', true);
                return;
            }
            var copied = {
                columns: source.columns.map(function (column, index) {
                    return {
                        position: positions[index],
                        span: Number(column.span),
                        label: String(column.label || '') + ' copy'
                    };
                })
            };
            definition.rows.splice(rowIndex + 1, 0, copied);
            markDirty();
        }

        function renderColumn(row, rowIndex, column, columnIndex) {
            var card = element('article', 'red-layout-builder__column');
            card.style.setProperty('--red-layout-column-span', String(column.span));
            card.setAttribute('data-red-layout-column', String(columnIndex));

            card.addEventListener('dragover', function (event) {
                if (activeDrag && activeDrag.type === 'column' && activeDrag.rowIndex === rowIndex) {
                    event.preventDefault();
                    event.stopPropagation();
                    card.classList.add('is-drop-target');
                }
            });
            card.addEventListener('dragleave', function () {
                card.classList.remove('is-drop-target');
            });
            card.addEventListener('drop', function (event) {
                columnDrop(event, rowIndex, columnIndex, card);
            });

            var top = element('div', 'red-layout-builder__column-top');
            var drag = actionButton('', 'Drag content position', ['M9 5h.01', 'M15 5h.01', 'M9 12h.01', 'M15 12h.01', 'M9 19h.01', 'M15 19h.01'], 'red-layout-builder__drag-handle');
            drag.draggable = true;
            drag.setAttribute('data-red-layout-editor-action', '');
            drag.addEventListener('dragstart', function (event) {
                activeDrag = {type: 'column', rowIndex: rowIndex, columnIndex: columnIndex};
                card.classList.add('is-dragging');
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', 'red-layout-column:' + rowIndex + ':' + columnIndex);
                }
            });
            drag.addEventListener('dragend', function () {
                activeDrag = null;
                card.classList.remove('is-dragging');
                clearDropStates();
            });
            top.appendChild(drag);
            top.appendChild(element('span', 'red-layout-builder__position', 'Position ' + column.position));

            var columnActions = element('div', 'red-layout-builder__column-actions');
            var left = actionButton('', 'Move position left', 'm15 18-6-6 6-6');
            left.setAttribute('data-red-layout-editor-action', '');
            if (columnIndex === 0) {
                left.setAttribute('data-layout-action-disabled', '');
            }
            left.addEventListener('click', function () {
                moveItem(row.columns, columnIndex, columnIndex - 1);
                markDirty();
            });
            var right = actionButton('', 'Move position right', 'm9 18 6-6-6-6');
            right.setAttribute('data-red-layout-editor-action', '');
            if (columnIndex === row.columns.length - 1) {
                right.setAttribute('data-layout-action-disabled', '');
            }
            right.addEventListener('click', function () {
                moveItem(row.columns, columnIndex, columnIndex + 1);
                markDirty();
            });
            var remove = actionButton('', 'Remove position', ['M5 12h14', 'M8 5h8', 'M9 5V3h6v2', 'M7 8l1 12h8l1-12']);
            remove.setAttribute('data-red-layout-editor-action', '');
            if (row.columns.length === 1) {
                remove.setAttribute('data-layout-action-disabled', '');
            }
            remove.addEventListener('click', function () {
                removeColumn(rowIndex, columnIndex);
            });
            columnActions.appendChild(left);
            columnActions.appendChild(right);
            columnActions.appendChild(remove);
            top.appendChild(columnActions);
            card.appendChild(top);

            var label = element('label', 'red-layout-builder__column-name');
            label.appendChild(element('span', '', 'Position name'));
            var input = document.createElement('input');
            input.type = 'text';
            input.maxLength = 80;
            input.value = String(column.label || '');
            input.setAttribute('data-red-layout-editor-action', '');
            input.addEventListener('input', function () {
                column.label = input.value;
                dirty = true;
                renderPreview();
                renderValidation();
                updateControls();
            });
            label.appendChild(input);
            card.appendChild(label);

            var spanLabel = element('label', 'red-layout-builder__span');
            spanLabel.appendChild(element('span', '', 'Width'));
            var select = document.createElement('select');
            select.setAttribute('data-red-layout-editor-action', '');
            for (var span = 1; span <= 12; span += 1) {
                var option = document.createElement('option');
                option.value = String(span);
                option.textContent = span + (span === 1 ? ' unit' : ' units');
                option.selected = span === Number(column.span);
                select.appendChild(option);
            }
            select.addEventListener('change', function () {
                column.span = Number(select.value);
                markDirty();
            });
            spanLabel.appendChild(select);
            card.appendChild(spanLabel);
            return card;
        }

        function renderRow(row, rowIndex) {
            var card = element('article', 'red-layout-builder__row');
            card.setAttribute('data-red-layout-row', String(rowIndex));
            card.addEventListener('dragover', function (event) {
                if (activeDrag && (activeDrag.type === 'preset' || activeDrag.type === 'row')) {
                    event.preventDefault();
                    card.classList.add('is-drop-target');
                }
            });
            card.addEventListener('dragleave', function (event) {
                if (!card.contains(event.relatedTarget)) {
                    card.classList.remove('is-drop-target');
                }
            });
            card.addEventListener('drop', function (event) {
                rowDrop(event, rowIndex, card);
            });

            var header = element('header', 'red-layout-builder__row-header');
            var rowIdentity = element('div', 'red-layout-builder__row-identity');
            var drag = actionButton('', 'Drag row', ['M9 5h.01', 'M15 5h.01', 'M9 12h.01', 'M15 12h.01', 'M9 19h.01', 'M15 19h.01'], 'red-layout-builder__drag-handle red-layout-builder__drag-handle--row');
            drag.draggable = true;
            drag.setAttribute('data-red-layout-editor-action', '');
            drag.addEventListener('dragstart', function (event) {
                activeDrag = {type: 'row', rowIndex: rowIndex};
                card.classList.add('is-dragging');
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', 'red-layout-row:' + rowIndex);
                }
            });
            drag.addEventListener('dragend', function () {
                activeDrag = null;
                card.classList.remove('is-dragging');
                clearDropStates();
            });
            rowIdentity.appendChild(drag);
            rowIdentity.appendChild(element('span', 'red-layout-builder__row-number', String(rowIndex + 1)));
            var rowCopy = element('span', 'red-layout-builder__row-copy');
            rowCopy.appendChild(element('strong', '', 'Row ' + (rowIndex + 1)));
            rowCopy.appendChild(element('small', '', row.columns.length + ' content position' + (row.columns.length === 1 ? '' : 's')));
            rowIdentity.appendChild(rowCopy);
            header.appendChild(rowIdentity);

            var total = row.columns.reduce(function (sum, column) {
                return sum + Number(column.span);
            }, 0);
            var rowActions = element('div', 'red-layout-builder__row-actions');
            rowActions.appendChild(element(
                'span',
                'red-layout-builder__row-total' + (total === 12 ? '' : ' is-invalid'),
                total + ' / 12'
            ));
            var add = actionButton('Column', 'Add a column', 'M12 5v14M5 12h14', 'red-layout-builder__row-action red-layout-builder__row-action--add');
            add.setAttribute('data-red-layout-editor-action', '');
            if (row.columns.length >= 12) {
                add.setAttribute('data-layout-action-disabled', '');
            }
            add.addEventListener('click', function () {
                addColumn(rowIndex);
            });
            rowActions.appendChild(add);
            var up = actionButton('', 'Move row up', 'm18 15-6-6-6 6');
            up.setAttribute('data-red-layout-editor-action', '');
            if (rowIndex === 0) {
                up.setAttribute('data-layout-action-disabled', '');
            }
            up.addEventListener('click', function () {
                moveItem(definition.rows, rowIndex, rowIndex - 1);
                markDirty();
            });
            rowActions.appendChild(up);
            var down = actionButton('', 'Move row down', 'm6 9 6 6 6-6');
            down.setAttribute('data-red-layout-editor-action', '');
            if (rowIndex === definition.rows.length - 1) {
                down.setAttribute('data-layout-action-disabled', '');
            }
            down.addEventListener('click', function () {
                moveItem(definition.rows, rowIndex, rowIndex + 1);
                markDirty();
            });
            rowActions.appendChild(down);
            var duplicate = actionButton('', 'Duplicate row', ['M8 8h11v11H8z', 'M5 16H4V4h12v1']);
            duplicate.setAttribute('data-red-layout-editor-action', '');
            duplicate.addEventListener('click', function () {
                duplicateRow(rowIndex);
            });
            rowActions.appendChild(duplicate);
            var remove = actionButton('', 'Remove row', ['M5 12h14', 'M8 5h8', 'M9 5V3h6v2', 'M7 8l1 12h8l1-12']);
            remove.setAttribute('data-red-layout-editor-action', '');
            if (definition.rows.length === 1) {
                remove.setAttribute('data-layout-action-disabled', '');
            }
            remove.addEventListener('click', function () {
                if (definition.rows.length <= 1) {
                    status('A layout must keep at least one row.', true);
                    return;
                }
                definition.rows.splice(rowIndex, 1);
                markDirty();
            });
            rowActions.appendChild(remove);
            header.appendChild(rowActions);
            card.appendChild(header);

            var columns = element('div', 'red-layout-builder__columns');
            row.columns.forEach(function (column, columnIndex) {
                columns.appendChild(renderColumn(row, rowIndex, column, columnIndex));
            });
            card.appendChild(columns);
            return card;
        }

        function renderCanvas() {
            canvas.textContent = '';
            definition.rows.forEach(function (row, rowIndex) {
                canvas.appendChild(renderRow(row, rowIndex));
            });
            if (definition.rows.length === 0) {
                canvas.appendChild(element('p', 'red-layout-builder__canvas-empty', 'Drop a row pattern here to begin.'));
            }
        }

        function renderPreview() {
            previewMap.textContent = '';
            definition.rows.forEach(function (row, rowIndex) {
                var previewRow = element('div', 'red-layout-builder__preview-row');
                previewRow.setAttribute('aria-label', 'Row ' + (rowIndex + 1));
                row.columns.forEach(function (column) {
                    var cell = element('div', 'red-layout-builder__preview-cell');
                    cell.style.setProperty('--red-layout-preview-span', String(column.span));
                    cell.appendChild(element('span', '', String(column.position)));
                    cell.appendChild(element('strong', '', String(column.label || 'Unnamed position')));
                    cell.appendChild(element('small', '', String(column.span) + '/12'));
                    previewRow.appendChild(cell);
                });
                previewMap.appendChild(previewRow);
            });
        }

        function renderAll() {
            if (titleHost) {
                titleHost.textContent = String(labelInput && labelInput.value ? labelInput.value : 'Untitled layout');
            }
            renderCanvas();
            renderPreview();
            renderValidation();
            updateControls();
        }

        function confirmDiscard() {
            return !dirty || window.confirm('Discard the unsaved changes in this layout?');
        }

        function refresh(layoutId, message) {
            if (!host) {
                window.location.reload();
                return;
            }
            var body = new URLSearchParams();
            body.set('LayoutID', layoutId || '');
            setBusy(true);
            fetch(config.refreshEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: body
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('The Layout Builder could not be refreshed.');
                }
                return response.text();
            }).then(function (html) {
                pendingMessage = message || '';
                host.innerHTML = html;
                initialize(host);
            }).catch(function (error) {
                setBusy(false);
                status(error.message, true);
            });
        }

        function request(operation, additional) {
            var body = new URLSearchParams();
            body.set('operation', operation);
            body.set('layoutId', currentLayoutId());
            body.set('stateHash', selected ? String(selected.stateHash || '') : '');
            body.set('csrf_token', String(config.csrfToken || ''));
            Object.keys(additional || {}).forEach(function (key) {
                body.set(key, String(additional[key]));
            });

            setBusy(true);
            status('Working…', false);
            return fetch(config.actionEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': String(config.csrfToken || '')
                },
                body: body
            }).then(function (response) {
                return response.json().catch(function () {
                    return {
                        ok: false,
                        message: 'The server returned an unreadable Layout Builder response.'
                    };
                }).then(function (payload) {
                    if (!response.ok || !payload.ok) {
                        var error = new Error(payload.message || 'The Layout Builder action failed.');
                        error.payload = payload;
                        throw error;
                    }
                    return payload;
                });
            }).catch(function (error) {
                setBusy(false);
                status(error.message, true);
                throw error;
            });
        }

        function resetNew() {
            if (!confirmDiscard()) {
                return;
            }
            selected = null;
            definition = defaultDefinition();
            dirty = true;
            idTouched = false;
            labelInput.value = '';
            idInput.value = '';
            idInput.readOnly = false;
            status('New layout started. Name it, arrange the grid, then save the draft.', false);
            renderAll();
            labelInput.focus();
        }

        function copyTemplate(layoutId) {
            if (!confirmDiscard()) {
                return;
            }
            var template = (config.templateLayouts || []).find(function (candidate) {
                return candidate.layoutId === layoutId;
            });
            if (!template) {
                status('That template layout is no longer available.', true);
                return;
            }
            selected = null;
            definition = clone(template.definition);
            dirty = true;
            idTouched = false;
            var name = template.label + ' copy';
            var suffix = slugPart(name);
            var reserved = {};
            (config.customLayouts || []).forEach(function (layout) {
                reserved[layout.layoutId] = true;
            });
            var candidate = 'custom-' + suffix;
            var number = 2;
            while (reserved[candidate]) {
                candidate = 'custom-' + slugPart(suffix + '-' + number);
                number += 1;
            }
            labelInput.value = name;
            idInput.value = candidate.replace(/^custom-/, '');
            idInput.readOnly = false;
            status('Template copied into a new draft. The packaged layout remains unchanged.', false);
            renderAll();
            labelInput.focus();
        }

        function bindStaticControls() {
            builder.querySelectorAll('[data-red-layout-open]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (confirmDiscard()) {
                        refresh(button.getAttribute('data-red-layout-open'), '');
                    }
                });
            });
            builder.querySelectorAll('[data-red-layout-copy]').forEach(function (button) {
                button.addEventListener('click', function () {
                    copyTemplate(button.getAttribute('data-red-layout-copy'));
                });
            });
            builder.querySelectorAll('[data-red-layout-new]').forEach(function (button) {
                button.addEventListener('click', resetNew);
            });
            builder.querySelectorAll('[data-red-layout-preview-mode]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var mode = button.getAttribute('data-red-layout-preview-mode') === 'mobile'
                        ? 'mobile'
                        : 'desktop';
                    preview.setAttribute('data-preview-mode', mode);
                    builder.querySelectorAll('[data-red-layout-preview-mode]').forEach(function (candidate) {
                        var active = candidate === button;
                        candidate.classList.toggle('is-active', active);
                        candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                });
            });
            builder.querySelectorAll('[data-red-layout-restore-version]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!selected || !window.confirm('Restore this version as the current draft? The published layout will stay unchanged.')) {
                        return;
                    }
                    request('restore', {
                        revisionId: button.getAttribute('data-red-layout-restore-version')
                    }).then(function (payload) {
                        refresh(payload.layout.layoutId, payload.message);
                    }).catch(function () {});
                });
            });

            var close = builder.querySelector('[data-red-layout-builder-close]');
            if (close) {
                close.addEventListener('click', function () {
                    if (!confirmDiscard()) {
                        return;
                    }
                    if (host) {
                        host.style.display = 'none';
                        host.innerHTML = '';
                    }
                    var grid = document.getElementById('edit_advanced_grid');
                    if (grid) {
                        grid.style.display = '';
                    }
                });
            }

            labelInput.addEventListener('input', function () {
                if (!selected && !idTouched) {
                    idInput.value = slugPart(labelInput.value);
                }
                dirty = true;
                titleHost.textContent = labelInput.value || 'Untitled layout';
                renderValidation();
                updateControls();
            });
            idInput.addEventListener('input', function () {
                idTouched = true;
                var caret = idInput.selectionStart;
                idInput.value = slugPart(idInput.value);
                if (typeof caret === 'number' && idInput.setSelectionRange) {
                    idInput.setSelectionRange(Math.min(caret, idInput.value.length), Math.min(caret, idInput.value.length));
                }
                dirty = true;
                renderValidation();
                updateControls();
            });

            canvas.addEventListener('dragover', function (event) {
                if (activeDrag && activeDrag.type === 'preset') {
                    event.preventDefault();
                    canvas.classList.add('is-drop-target');
                }
            });
            canvas.addEventListener('dragleave', function (event) {
                if (!canvas.contains(event.relatedTarget)) {
                    canvas.classList.remove('is-drop-target');
                }
            });
            canvas.addEventListener('drop', function (event) {
                if (activeDrag && activeDrag.type === 'preset' && event.target === canvas) {
                    event.preventDefault();
                    addPreset(activeDrag.spans);
                    activeDrag = null;
                    clearDropStates();
                }
            });

            saveButton.addEventListener('click', function () {
                var result = validate();
                renderValidation();
                if (!result.valid) {
                    status(result.errors[0], true);
                    return;
                }
                request('save', {
                    label: result.label,
                    definition: JSON.stringify(definition)
                }).then(function (payload) {
                    refresh(payload.layout.layoutId, payload.message);
                }).catch(function () {});
            });

            publishButton.addEventListener('click', function () {
                if (!selected || dirty) {
                    status('Save the draft before publishing it.', true);
                    return;
                }
                request('publish', {}).then(function (payload) {
                    refresh(payload.layout.layoutId, payload.message);
                }).catch(function () {});
            });

            if (archiveButton) {
                archiveButton.addEventListener('click', function () {
                    var shouldArchive = archiveButton.getAttribute('data-red-layout-archive') === '1';
                    if (shouldArchive && !window.confirm('Archive this layout? Assigned layouts must be moved first, and public pages will never be changed automatically.')) {
                        return;
                    }
                    request(shouldArchive ? 'archive' : 'unarchive', {}).then(function (payload) {
                        refresh(payload.layout.layoutId, payload.message);
                    }).catch(function () {});
                });
            }
        }

        renderPresets();
        bindStaticControls();
        renderAll();
        if (pendingMessage) {
            status(pendingMessage, false);
            pendingMessage = '';
        }
    }

    window.RedLayoutBuilder = {
        init: initialize
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initialize(document);
        });
    } else {
        initialize(document);
    }
}());
