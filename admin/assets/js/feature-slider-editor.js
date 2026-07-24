(function (window, document, $) {
    'use strict';

    function toArray(nodes) {
        return Array.prototype.slice.call(nodes || []);
    }

    function sliderWorkspace(element) {
        return element && element.closest ? element.closest('[data-slider-workspace]') : null;
    }

    function selectedCards(workspace) {
        return toArray(workspace.querySelectorAll('[data-slider-select]')).filter(function (input) {
            return input.checked;
        });
    }

    function setMessage(workspace, message, tone) {
        var status = workspace.querySelector('.red-admin-slider-message');
        if (!status) {
            return;
        }

        status.textContent = message || '';
        status.classList.toggle('is-success', tone === 'success');
        status.classList.toggle('is-error', tone === 'error');
        status.hidden = !message;
    }

    function syncCard(input) {
        var card = input.closest('[data-slider-card]');
        if (!card) {
            return;
        }

        var selected = input.checked;
        var status = card.querySelector('[data-slider-status]');
        var label = card.querySelector('[data-slider-choice-label]');

        card.classList.toggle('is-selected', selected);
        if (status) {
            status.textContent = selected ? 'Selected' : 'Available';
        }
        if (label) {
            label.textContent = selected ? 'Included in slider' : 'Add to slider';
        }
    }

    function syncWorkspace(workspace) {
        if (!workspace) {
            return;
        }

        toArray(workspace.querySelectorAll('[data-slider-select]')).forEach(syncCard);

        var count = selectedCards(workspace).length;
        toArray(workspace.querySelectorAll('[data-slider-selected-count]')).forEach(function (target) {
            target.textContent = String(count);
        });

        var selectionLabel = workspace.querySelector('[data-slider-selection-label]');
        var actionsLabel = workspace.querySelector('[data-slider-actions-label]');
        if (selectionLabel) {
            selectionLabel.textContent = count === 1 ? '1 selected slide' : count + ' selected slides';
        }
        if (actionsLabel) {
            actionsLabel.textContent = count === 1 ? 'slide selected' : 'slides selected';
        }
    }

    function applyFilter(input) {
        var workspace = sliderWorkspace(input);
        if (!workspace) {
            return;
        }

        var query = String(input.value || '').trim().toLowerCase();
        var visibleCount = 0;
        toArray(workspace.querySelectorAll('[data-slider-card]')).forEach(function (card) {
            var searchText = String(card.getAttribute('data-slider-search') || '').toLowerCase();
            var visible = query === '' || searchText.indexOf(query) !== -1;
            card.hidden = !visible;
            if (visible) {
                visibleCount++;
            }
        });

        var empty = workspace.querySelector('[data-slider-filter-empty]');
        if (empty) {
            empty.hidden = visibleCount !== 0;
        }
    }

    function setBusy(form, busy) {
        var button = form.querySelector('.red-admin-slider-save');
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
        if (button) {
            button.disabled = busy;
            button.classList.toggle('is-busy', busy);
        }
    }

    function showPageContent() {
        var editor = document.getElementById('msggbox_edit_content');
        var grid = document.getElementById('edit_content_grid');
        if (editor) {
            editor.hidden = true;
            editor.style.display = 'none';
            editor.innerHTML = '';
        }
        if (grid) {
            grid.hidden = false;
            grid.style.display = '';
        }
        toArray(document.querySelectorAll('.red-admin-slider-launcher__button:disabled')).forEach(function (button) {
            button.disabled = false;
        });
    }

    function replaceEditor(data) {
        var editor = $('#msggbox_edit_content');
        var content = String(data || '').trim();
        if (!editor.length || content === '' || content === 'no') {
            return false;
        }

        $('#edit_content_grid').hide();
        editor.stop(true, true).html(data).show();
        if (editor[0] && typeof editor[0].scrollIntoView === 'function') {
            var reducedMotion = window.matchMedia
                && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            editor[0].scrollIntoView({ block: 'start', behavior: reducedMotion ? 'auto' : 'smooth' });
        }
        return true;
    }

    window.redAdminOpenSliderEditor = function (form) {
        if (!$ || !form) {
            return false;
        }

        var button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
        }

        $.ajax({
            type: 'POST',
            url: '/admin/bin/edit_feature_slider.php',
            cache: false,
            data: $(form).serialize()
        }).done(function (data) {
            if (!replaceEditor(data) && button) {
                button.disabled = false;
            }
        }).fail(function () {
            if (button) {
                button.disabled = false;
            }
        });

        return false;
    };

    window.run_update_slider = function (form) {
        if (!$ || !form) {
            return false;
        }

        var workspace = form.matches('[data-slider-workspace]') ? form : sliderWorkspace(form);
        if (!workspace) {
            return false;
        }

        setBusy(workspace, true);
        setMessage(workspace, '', '');

        $.ajax({
            type: 'POST',
            url: '/admin/bin/update_feature_slider.php',
            data: $(workspace).serialize()
        }).done(function (data) {
            if (String(data || '').trim() === 'yes') {
                setMessage(workspace, 'Slider updated. Refreshing the page…', 'success');
                window.setTimeout(function () {
                    window.location.reload();
                }, 350);
                return;
            }

            setBusy(workspace, false);
            setMessage(workspace, 'The slider could not be updated. Please review your selections and try again.', 'error');
        }).fail(function () {
            setBusy(workspace, false);
            setMessage(workspace, 'The slider could not be updated. Please try again.', 'error');
        });

        return false;
    };

    window.redAdminSliderInit = function (root) {
        var workspace = root && root.matches && root.matches('[data-slider-workspace]')
            ? root
            : sliderWorkspace(root);
        syncWorkspace(workspace);
    };

    document.addEventListener('change', function (event) {
        if (!event.target.matches('[data-slider-select]')) {
            return;
        }
        syncWorkspace(sliderWorkspace(event.target));
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-slider-filter]')) {
            applyFilter(event.target);
        }
    });

    document.addEventListener('click', function (event) {
        var returnButton = event.target.closest('[data-slider-return]');
        if (returnButton) {
            event.preventDefault();
            showPageContent();
            return;
        }

        var editButton = event.target.closest('[data-slider-edit-article]');
        if (!editButton || !$) {
            return;
        }

        event.preventDefault();
        editButton.disabled = true;
        $.ajax({
            type: 'POST',
            url: '/admin/bin/edit_article.php',
            cache: false,
            data: {
                RecordID: editButton.getAttribute('data-record-id'),
                VarPosition: editButton.getAttribute('data-position-column')
            }
        }).done(function (data) {
            if (!replaceEditor(data)) {
                editButton.disabled = false;
            }
        }).fail(function () {
            editButton.disabled = false;
        });
    });
})(window, document, window.jQuery);
