(function () {
    'use strict';

    function all(root, selector) {
        return Array.prototype.slice.call(root.querySelectorAll(selector));
    }

    function text(value) {
        return value === null || typeof value === 'undefined' ? '' : String(value);
    }

    function fold(value) {
        return text(value).trim().toLocaleLowerCase();
    }

    function same(left, right) {
        return fold(left) === fold(right);
    }

    function selectedValue(select) {
        return select && !select.disabled ? text(select.value).trim() : '';
    }

    function clearChildren(element) {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
    }

    function option(value, label, data) {
        var element = document.createElement('option');
        element.value = text(value);
        element.textContent = text(label);
        Object.keys(data || {}).forEach(function (key) {
            element.dataset[key] = text(data[key]);
        });
        return element;
    }

    function itemLabel(item) {
        var title = text(item && item.title).trim();
        var value = text(item && item.value).trim();
        if (title === '' || same(title, value)) {
            return value;
        }
        return title + ' — ' + value;
    }

    function populateAreaSelect(select, rows, placeholder, enabled) {
        clearChildren(select);
        select.appendChild(option('', placeholder));
        (rows || []).forEach(function (row) {
            select.appendChild(option(row.value, itemLabel(row), {
                recordId: row.recordId,
                parentRecordId: row.parentRecordId,
                section: row.section,
                category: row.category,
                layout: row.layout
            }));
        });
        select.disabled = !enabled;
    }

    function uniqueMatch(rows, value) {
        if (text(value).trim() === '') {
            return null;
        }
        var matches = (rows || []).filter(function (row) {
            return same(row.value, value);
        });
        return matches.length === 1 ? matches[0] : null;
    }

    function routeMatches(row, section, category, subcategory) {
        return same(row.section, section) &&
            same(row.category, category) &&
            same(row.subcategory, subcategory);
    }

    function routePath(section, category, subcategory, article) {
        var segments = [];
        if (section && (!same(section, 'home') || category || subcategory)) {
            segments.push(section);
        }
        if (category) {
            segments.push(category);
        }
        if (subcategory) {
            segments.push(subcategory);
        }
        if (article) {
            segments.push(article);
        }
        if (!segments.length) {
            return '/';
        }
        var path = '/' + segments.map(function (segment) {
            return encodeURIComponent(segment);
        }).join('/');
        return article ? path : path + '/';
    }

    function parseCatalog(form) {
        var id = form.getAttribute('data-red-move-catalog');
        var node = id ? document.getElementById(id) : null;
        if (!node) {
            return null;
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (error) {
            return null;
        }
    }

    function sourceRows(form) {
        return all(form, '[data-red-move-source-row]');
    }

    function visibleSourceRows(form) {
        return sourceRows(form).filter(function (row) {
            return !row.hidden;
        });
    }

    function selectedInputs(form) {
        return all(form, '[data-red-move-item]:checked');
    }

    function updateSelectedState(form) {
        var selected = selectedInputs(form);
        var count = form.querySelector('[data-red-move-selected-count]');
        var selectAll = form.querySelector('[data-red-move-select-all]');
        var visible = visibleSourceRows(form);
        var checkedVisible = visible.filter(function (row) {
            var input = row.querySelector('[data-red-move-item]');
            return input && input.checked;
        });

        sourceRows(form).forEach(function (row) {
            var input = row.querySelector('[data-red-move-item]');
            row.classList.toggle('is-selected', Boolean(input && input.checked));
        });
        if (count) {
            count.textContent = selected.length + (selected.length === 1 ? ' selected' : ' selected');
        }
        if (selectAll) {
            selectAll.checked = visible.length > 0 && checkedVisible.length === visible.length;
            selectAll.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visible.length;
            selectAll.disabled = visible.length === 0;
        }
        updateReadyState(form);
    }

    function compareRows(sortValue, left, right) {
        var leftTitle = fold(left.dataset.title);
        var rightTitle = fold(right.dataset.title);
        var leftComponent = fold(left.dataset.component);
        var rightComponent = fold(right.dataset.component);
        var leftPosition = parseInt(left.dataset.position || '0', 10);
        var rightPosition = parseInt(right.dataset.position || '0', 10);
        var leftUpdated = text(left.dataset.updated);
        var rightUpdated = text(right.dataset.updated);

        if (sortValue === 'title-asc') {
            return leftTitle.localeCompare(rightTitle);
        }
        if (sortValue === 'title-desc') {
            return rightTitle.localeCompare(leftTitle);
        }
        if (sortValue === 'position-asc') {
            return leftPosition - rightPosition || leftTitle.localeCompare(rightTitle);
        }
        if (sortValue === 'position-desc') {
            return rightPosition - leftPosition || leftTitle.localeCompare(rightTitle);
        }
        if (sortValue === 'component-asc') {
            return leftComponent.localeCompare(rightComponent) || leftTitle.localeCompare(rightTitle);
        }
        return rightUpdated.localeCompare(leftUpdated) || leftTitle.localeCompare(rightTitle);
    }

    function filterAndSortSources(form) {
        var search = form.querySelector('[data-red-move-search]');
        var position = form.querySelector('[data-red-move-source-position]');
        var sort = form.querySelector('[data-red-move-sort]');
        var list = form.querySelector('[data-red-move-source-list]');
        var noResults = form.querySelector('[data-red-move-no-results]');
        if (!list) {
            updateSelectedState(form);
            return;
        }

        var query = fold(search ? search.value : '');
        var positionValue = selectedValue(position) || 'all';
        var rows = sourceRows(form);
        rows.forEach(function (row) {
            var haystack = fold(row.dataset.title) + ' ' + fold(row.dataset.component);
            var searchMatch = query === '' || haystack.indexOf(query) !== -1;
            var positionMatch = positionValue === 'all' || text(row.dataset.position) === positionValue;
            row.hidden = !(searchMatch && positionMatch);
        });
        rows.sort(function (left, right) {
            return compareRows(selectedValue(sort) || 'updated', left, right);
        }).forEach(function (row) {
            list.appendChild(row);
        });
        if (noResults) {
            noResults.hidden = visibleSourceRows(form).length !== 0;
        }
        updateSelectedState(form);
    }

    function populateArticleSelect(form, preserveValue) {
        var state = form._redMoveState;
        var section = selectedValue(state.section);
        var category = selectedValue(state.category);
        var subcategory = selectedValue(state.subcategory);
        var matches = [];
        if (section) {
            matches = (state.catalog.articles || []).filter(function (row) {
                return routeMatches(row, section, category, subcategory);
            });
        }

        clearChildren(state.article);
        state.article.appendChild(option('', matches.length ? 'No article page' : 'No pages at this path'));
        matches.forEach(function (row) {
            var path = routePath(row.section, row.category, row.subcategory, row.value);
            state.article.appendChild(option(row.value, itemLabel(row) + ' · ' + path, {
                recordId: row.recordId,
                layout: row.layout
            }));
        });
        state.article.disabled = !section || matches.length === 0;
        if (preserveValue && matches.some(function (row) { return same(row.value, preserveValue); })) {
            state.article.value = preserveValue;
        }
        syncArticleRecordId(form);
    }

    function syncArticleRecordId(form) {
        var state = form._redMoveState;
        var selected = state.article.options[state.article.selectedIndex];
        state.articleId.value = selectedValue(state.article) && selected
            ? text(selected.dataset.recordId)
            : '';
    }

    function positionEntries(definition) {
        return Object.keys((definition && definition.positions) || {}).map(function (key) {
            return {
                id: parseInt(key, 10),
                label: text(definition.positions[key])
            };
        }).filter(function (entry) {
            return entry.id > 0 && entry.id <= 99 && entry.label !== '';
        }).sort(function (left, right) {
            return left.id - right.id;
        });
    }

    function setPositionSelection(form) {
        var state = form._redMoveState;
        var selected = selectedValue(state.position);
        all(form, '[data-red-move-position-shortcut]').forEach(function (button) {
            button.setAttribute(
                'aria-pressed',
                text(button.getAttribute('data-red-move-position-shortcut')) === selected ? 'true' : 'false'
            );
        });
        updateReadyState(form);
    }

    function buildMapCell(form, cell, knownPositions) {
        var positionId = parseInt(cell && cell.position, 10);
        if (!knownPositions[positionId]) {
            return null;
        }
        var button = document.createElement('button');
        var number = document.createElement('span');
        var label = document.createElement('span');
        var weight = Math.max(1, Math.min(12, parseInt(cell.weight, 10) || 1));

        button.type = 'button';
        button.className = 'red-admin-move-map__cell';
        button.style.setProperty('--red-admin-layout-weight', weight);
        button.setAttribute('data-red-move-position-shortcut', positionId);
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', 'Choose position ' + positionId + ', ' + knownPositions[positionId]);
        number.className = 'red-admin-move-map__number';
        number.textContent = positionId;
        label.className = 'red-admin-move-map__cell-label';
        label.textContent = knownPositions[positionId];
        button.appendChild(number);
        button.appendChild(label);
        button.addEventListener('click', function () {
            form._redMoveState.position.value = text(positionId);
            setPositionSelection(form);
            form._redMoveState.position.focus();
        });
        return button;
    }

    function drawDestination(form) {
        var state = form._redMoveState;
        var sectionValue = selectedValue(state.section);
        var categoryValue = selectedValue(state.category);
        var subcategoryValue = selectedValue(state.subcategory);
        var articleValue = selectedValue(state.article);
        var sectionRow = uniqueMatch(state.catalog.sections, sectionValue);
        var categoryMatches = (state.catalog.categories || []).filter(function (row) {
            return same(row.value, categoryValue) && same(row.section, sectionValue);
        });
        var categoryRow = categoryValue && categoryMatches.length === 1 ? categoryMatches[0] : null;
        var subcategoryMatches = (state.catalog.subcategories || []).filter(function (row) {
            return same(row.value, subcategoryValue) &&
                same(row.section, sectionValue) &&
                same(row.category, categoryValue);
        });
        var subcategoryRow = subcategoryValue && subcategoryMatches.length === 1
            ? subcategoryMatches[0]
            : null;
        var articleRows = (state.catalog.articles || []).filter(function (row) {
            return same(row.value, articleValue) &&
                routeMatches(row, sectionValue, categoryValue, subcategoryValue);
        });
        var articleId = parseInt(state.articleId.value || '0', 10);
        if (articleId > 0) {
            articleRows = articleRows.filter(function (row) {
                return parseInt(row.recordId, 10) === articleId;
            });
        }
        var articleRow = articleValue && articleRows.length === 1 ? articleRows[0] : null;
        var target = sectionRow;
        var level = sectionRow && same(sectionRow.value, 'home') ? 'Home' : 'Section';

        if (categoryValue) {
            target = categoryRow;
            level = 'Category';
        }
        if (subcategoryValue) {
            target = subcategoryRow;
            level = 'Subcategory';
        }
        if (articleValue) {
            target = articleRow;
            level = 'Article page';
        }

        var definition = target && state.catalog.layouts
            ? state.catalog.layouts[target.layout]
            : null;
        var entries = positionEntries(definition);
        var valid = Boolean(target && definition && entries.length);
        state.destination = valid ? {
            target: target,
            level: level,
            definition: definition,
            articleRecordId: articleRow ? parseInt(articleRow.recordId, 10) : 0
        } : null;

        clearChildren(state.position);
        if (!sectionValue) {
            state.position.appendChild(option('', 'Choose a destination first'));
        } else if (!valid) {
            state.position.appendChild(option('', 'Layout positions unavailable'));
        } else {
            state.position.appendChild(option('', 'Choose a position…'));
            if (parseInt(definition.hiddenPosition, 10) === 0) {
                state.position.appendChild(option('0', 'Hidden (0)'));
            }
            entries.forEach(function (entry) {
                state.position.appendChild(option(entry.id, entry.label + ' (' + entry.id + ')'));
            });
        }
        state.position.disabled = !valid;

        clearChildren(state.mapDiagram);
        if (valid) {
            var knownPositions = {};
            entries.forEach(function (entry) {
                knownPositions[entry.id] = entry.label;
            });
            (definition.previewRows || []).forEach(function (row) {
                var rowElement = document.createElement('div');
                rowElement.className = 'red-admin-move-map__row';
                (row || []).forEach(function (cell) {
                    var cellElement = buildMapCell(form, cell, knownPositions);
                    if (cellElement) {
                        rowElement.appendChild(cellElement);
                    }
                });
                if (rowElement.children.length) {
                    state.mapDiagram.appendChild(rowElement);
                }
            });
            state.mapDiagram.hidden = state.mapDiagram.children.length === 0;
            state.mapEmpty.hidden = true;
            state.mapTitle.textContent = text(definition.label || target.layout);
            state.mapCount.textContent = entries.length + (entries.length === 1 ? ' position' : ' positions');
            state.mapNote.hidden = false;
            state.mapNote.textContent = definition.previewIsFallback
                ? 'Exact geometry is not declared; positions follow the template order.'
                : 'Click a position in the desktop map, or use the Position menu.';
            state.hiddenShortcut.hidden = parseInt(definition.hiddenPosition, 10) !== 0;
            state.hiddenShortcut.disabled = parseInt(definition.hiddenPosition, 10) !== 0;
            state.badge.textContent = level + ' layout';
            state.liveStatus.textContent = text(definition.label || target.layout) + ', ' +
                entries.length + (entries.length === 1 ? ' position available.' : ' positions available.');
        } else {
            state.mapDiagram.hidden = true;
            state.mapEmpty.hidden = false;
            state.mapTitle.textContent = sectionValue ? 'Layout unavailable' : 'No destination selected';
            state.mapCount.textContent = '0 positions';
            state.mapNote.hidden = true;
            state.hiddenShortcut.hidden = true;
            state.hiddenShortcut.disabled = true;
            state.badge.textContent = sectionValue ? 'Review destination' : 'Choose a section';
            state.liveStatus.textContent = sectionValue
                ? 'The selected destination does not have an available layout.'
                : 'Choose a section to load its layout.';
        }

        state.path.textContent = sectionValue
            ? routePath(sectionValue, categoryValue, subcategoryValue, articleValue)
            : 'Choose a section to begin';
        setPositionSelection(form);
    }

    function destinationSelfTargeted(form) {
        var destination = form._redMoveState.destination;
        if (!destination || !destination.articleRecordId) {
            return false;
        }
        return selectedInputs(form).some(function (input) {
            return parseInt(input.value, 10) === destination.articleRecordId;
        });
    }

    function setMessage(form, message, kind) {
        var element = form.querySelector('#msggbox_tool_content');
        if (!element) {
            return;
        }
        element.textContent = message || '';
        element.classList.toggle('is-success', kind === 'success');
        element.classList.toggle('has-error', kind === 'error');
    }

    function updateReadyState(form) {
        var state = form._redMoveState;
        if (!state) {
            return false;
        }
        var hasItems = selectedInputs(form).length > 0;
        var hasDestination = Boolean(state.destination);
        var hasPosition = selectedValue(state.position) !== '';
        var selfTarget = destinationSelfTargeted(form);
        var ready = hasItems && hasDestination && hasPosition && !selfTarget && !state.submitting;
        state.submit.disabled = !ready;

        if (selfTarget) {
            state.selfTargetMessage = true;
            setMessage(form, 'A destination Article page cannot be moved into itself.', 'error');
        } else if (state.selfTargetMessage) {
            state.selfTargetMessage = false;
            setMessage(form, '', '');
        }
        return ready;
    }

    function resetDestination(form) {
        var state = form._redMoveState;
        state.section.value = '';
        populateAreaSelect(state.category, [], 'No category', false);
        populateAreaSelect(state.subcategory, [], 'No subcategory', false);
        populateAreaSelect(state.article, [], 'No article page', false);
        state.articleId.value = '';
        drawDestination(form);
        state.section.focus();
        setMessage(form, '', '');
    }

    function editContent(form, button) {
        if (!window.jQuery) {
            return;
        }
        var articleId = parseInt(button.dataset.articleId || '0', 10);
        var contentId = parseInt(button.dataset.contentId || '0', 10);
        var isGroup = text(button.dataset.componentGroup).toUpperCase() === 'Y';
        var data = {
            Layout: form.getAttribute('data-red-move-layout') || '',
            VarPosition: form.getAttribute('data-red-move-position-column') || ''
        };
        if (isGroup) {
            data.RecordID = contentId;
            data.ArtRecordID = articleId;
        } else {
            data.RecordID = articleId;
        }

        button.disabled = true;
        window.jQuery.ajax({
            type: 'POST',
            url: button.dataset.endpoint,
            data: data,
            success: function (response) {
                if (typeof window.showdiv === 'function') {
                    window.showdiv('editcontent');
                }
                window.jQuery('#msggbox_edit_content').html(response).fadeIn(200);
            },
            error: function () {
                setMessage(form, 'The editor could not be opened. Please try again.', 'error');
            },
            complete: function () {
                button.disabled = false;
            }
        });
    }

    function bindDestination(form) {
        var state = form._redMoveState;
        state.section.addEventListener('change', function () {
            var sectionValue = selectedValue(state.section);
            var categories = (state.catalog.categories || []).filter(function (row) {
                return same(row.section, sectionValue);
            });
            populateAreaSelect(
                state.category,
                categories,
                'No category',
                sectionValue !== '' && categories.length > 0
            );
            populateAreaSelect(state.subcategory, [], 'No subcategory', false);
            populateArticleSelect(form, '');
            drawDestination(form);
        });
        state.category.addEventListener('change', function () {
            var sectionValue = selectedValue(state.section);
            var categoryValue = selectedValue(state.category);
            var subcategories = (state.catalog.subcategories || []).filter(function (row) {
                return same(row.section, sectionValue) && same(row.category, categoryValue);
            });
            populateAreaSelect(
                state.subcategory,
                subcategories,
                'No subcategory',
                categoryValue !== '' && subcategories.length > 0
            );
            populateArticleSelect(form, '');
            drawDestination(form);
        });
        state.subcategory.addEventListener('change', function () {
            populateArticleSelect(form, '');
            drawDestination(form);
        });
        state.article.addEventListener('change', function () {
            syncArticleRecordId(form);
            drawDestination(form);
        });
        state.position.addEventListener('change', function () {
            setPositionSelection(form);
        });
        state.hiddenShortcut.addEventListener('click', function () {
            state.position.value = '0';
            setPositionSelection(form);
            state.position.focus();
        });
        state.clear.addEventListener('click', function () {
            resetDestination(form);
        });
    }

    function bindSources(form) {
        var search = form.querySelector('[data-red-move-search]');
        var position = form.querySelector('[data-red-move-source-position]');
        var sort = form.querySelector('[data-red-move-sort]');
        var selectAll = form.querySelector('[data-red-move-select-all]');

        [search, position, sort].forEach(function (control) {
            if (!control) {
                return;
            }
            control.addEventListener(control === search ? 'input' : 'change', function () {
                filterAndSortSources(form);
            });
        });
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                visibleSourceRows(form).forEach(function (row) {
                    var input = row.querySelector('[data-red-move-item]');
                    if (input) {
                        input.checked = selectAll.checked;
                    }
                });
                updateSelectedState(form);
            });
        }
        all(form, '[data-red-move-item]').forEach(function (input) {
            input.addEventListener('change', function () {
                updateSelectedState(form);
            });
        });
        all(form, '[data-red-move-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                editContent(form, button);
            });
        });
    }

    function init(form) {
        if (!form || form.getAttribute('data-red-move-initialized') === 'true') {
            return;
        }
        var catalog = parseCatalog(form);
        if (!catalog) {
            return;
        }

        var state = {
            catalog: catalog,
            section: form.querySelector('[data-red-move-section]'),
            category: form.querySelector('[data-red-move-category]'),
            subcategory: form.querySelector('[data-red-move-subcategory]'),
            article: form.querySelector('[data-red-move-article]'),
            articleId: form.querySelector('[data-red-move-article-id]'),
            position: form.querySelector('[data-red-move-position]'),
            path: form.querySelector('[data-red-move-path]'),
            badge: form.querySelector('[data-red-move-destination-badge]'),
            mapTitle: form.querySelector('[data-red-move-map-title]'),
            mapCount: form.querySelector('[data-red-move-map-count]'),
            mapEmpty: form.querySelector('[data-red-move-map-empty]'),
            mapDiagram: form.querySelector('[data-red-move-map-diagram]'),
            mapNote: form.querySelector('[data-red-move-map-note]'),
            hiddenShortcut: form.querySelector('[data-red-move-position-shortcut="0"]'),
            liveStatus: form.querySelector('[data-red-move-live-status]'),
            clear: form.querySelector('[data-red-move-clear]'),
            submit: form.querySelector('[data-red-move-submit]'),
            destination: null,
            submitting: false,
            selfTargetMessage: false
        };
        if (Object.keys(state).some(function (key) {
            return key !== 'catalog' &&
                key !== 'destination' &&
                key !== 'submitting' &&
                key !== 'selfTargetMessage' &&
                !state[key];
        })) {
            return;
        }

        form._redMoveState = state;
        form.setAttribute('data-red-move-initialized', 'true');
        populateAreaSelect(state.section, catalog.sections || [], 'Choose a section…', true);
        populateAreaSelect(state.category, [], 'No category', false);
        populateAreaSelect(state.subcategory, [], 'No subcategory', false);
        populateAreaSelect(state.article, [], 'No article page', false);
        bindSources(form);
        bindDestination(form);
        filterAndSortSources(form);
        drawDestination(form);
    }

    function submit(form) {
        var state = form && form._redMoveState;
        if (!state || !window.jQuery || !updateReadyState(form)) {
            if (form) {
                setMessage(form, 'Select at least one item, a valid destination, and a layout position.', 'error');
            }
            return false;
        }

        state.submitting = true;
        state.submit.disabled = true;
        state.submit.setAttribute('aria-busy', 'true');
        setMessage(form, 'Moving selected content…', '');
        window.jQuery.ajax({
            type: 'POST',
            url: '/admin/bin/run_tool_movecontent.php',
            data: window.jQuery(form).serialize(),
            success: function (response) {
                if (window.jQuery.trim(response) === 'yes') {
                    setMessage(form, 'Content moved. Refreshing the page…', 'success');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 450);
                    return;
                }
                state.submitting = false;
                state.submit.removeAttribute('aria-busy');
                setMessage(
                    form,
                    'The move could not be completed. Review the destination; its layout or content may have changed.',
                    'error'
                );
                updateReadyState(form);
            },
            error: function () {
                state.submitting = false;
                state.submit.removeAttribute('aria-busy');
                setMessage(form, 'The move request could not be sent. Please try again.', 'error');
                updateReadyState(form);
            }
        });
        return false;
    }

    function scan(root) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return;
        }
        if (root.matches && root.matches('[data-red-move-content]')) {
            init(root);
        }
        all(root, '[data-red-move-content]').forEach(init);
    }

    window.REDMoveContent = {
        init: init,
        submit: submit
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            scan(document);
        });
    } else {
        scan(document);
    }

    if (typeof window.MutationObserver === 'function') {
        new window.MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                    if (node.nodeType === 1) {
                        scan(node);
                    }
                });
            });
        }).observe(document.documentElement, {childList: true, subtree: true});
    }
}());
