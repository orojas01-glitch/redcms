(function (window, document, $) {
    'use strict';

    if (!$) {
        return;
    }

    function menuForm() {
        return document.querySelector('[data-red-menu-editor]');
    }

    function setStatus(form, message, tone) {
        var status = form ? form.querySelector('[data-menu-status]') : null;
        if (!status) {
            return;
        }

        status.textContent = message;
        status.dataset.tone = tone || 'neutral';
    }

    function setBusy(form, busy) {
        if (!form) {
            return;
        }

        form.setAttribute('aria-busy', busy ? 'true' : 'false');
        Array.prototype.forEach.call(
            form.querySelectorAll('.red-admin-menu-save, [data-menu-delete]'),
            function (button) {
                button.disabled = busy;
            }
        );
    }

    function updateRoutePreview(item, value) {
        var preview = item ? item.querySelector('[data-menu-link-preview]') : null;
        if (preview) {
            preview.textContent = String(value || '').trim() || 'No destination selected';
        }
    }

    function markDirty(form) {
        if (form) {
            form.dataset.menuDirty = 'true';
            setStatus(form, 'Unsaved navigation changes.', 'pending');
        }
    }

    function replaceEditor(content, message, tone) {
        var editor = $('#msggbox_edit_content');
        if (!editor.length || String(content || '').trim() === '') {
            return false;
        }

        editor.stop(true, true).html(content).show();
        $('#edit_content_grid').hide();
        var form = menuForm();
        if (form && message) {
            setStatus(form, message, tone);
        }
        return true;
    }

    function refreshEditor(message, tone) {
        $.ajax({
            type: 'POST',
            url: '/admin/bin/edit_main_menu.php',
            cache: false,
            data: { RecordID: 0 }
        }).done(function (content) {
            if (!replaceEditor(content, message, tone)) {
                var form = menuForm();
                setBusy(form, false);
                setStatus(form, 'The menu editor could not be refreshed. Reload the page and try again.', 'error');
            }
        }).fail(function () {
            var form = menuForm();
            setBusy(form, false);
            setStatus(form, 'The menu editor could not be refreshed. Reload the page and try again.', 'error');
        });
    }

    function showPageContent() {
        var form = menuForm();
        if (form && form.dataset.menuDirty === 'true'
            && !window.confirm('Leave the menu editor without saving your changes?')) {
            return;
        }

        if (document.body.dataset.menuNeedsRefresh === 'true') {
            window.location.reload();
            return;
        }

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
    }

    window.run_update_main_menu = function (form) {
        form = form && form.nodeType === 1 ? form : menuForm();
        if (!form || form.getAttribute('aria-busy') === 'true') {
            return false;
        }

        var payload = $(form).serialize();
        setBusy(form, true);
        setStatus(form, 'Saving navigation…', 'pending');

        $.ajax({
            type: 'POST',
            url: '/admin/bin/update_main_menu.php',
            data: payload
        }).done(function (response) {
            if (String(response || '').trim() !== 'yes') {
                setBusy(form, false);
                setStatus(form, 'Navigation was not saved. Review the fields and try again.', 'error');
                return;
            }

            document.body.dataset.menuNeedsRefresh = 'true';
            refreshEditor('Navigation saved. You can keep editing or return to the page.', 'success');
        }).fail(function () {
            setBusy(form, false);
            setStatus(form, 'Navigation was not saved. Check the connection and try again.', 'error');
        });

        return false;
    };

    document.addEventListener('change', function (event) {
        var picker = event.target.closest('[data-menu-link-picker]');
        if (picker) {
            var item = picker.closest('[data-menu-item]');
            var linkInput = item ? item.querySelector('[data-menu-link-input]') : null;
            if (linkInput && picker.value !== '') {
                linkInput.value = picker.value;
                updateRoutePreview(item, picker.value);
                markDirty(picker.form);
            }
            return;
        }

        if (event.target.closest('[data-red-menu-editor]')) {
            markDirty(event.target.form || menuForm());
        }
    });

    document.addEventListener('input', function (event) {
        var linkInput = event.target.closest('[data-menu-link-input]');
        if (linkInput) {
            updateRoutePreview(linkInput.closest('[data-menu-item]'), linkInput.value);
        }

        if (event.target.closest('[data-red-menu-editor]')) {
            markDirty(event.target.form || menuForm());
        }
    });

    document.addEventListener('click', function (event) {
        var returnButton = event.target.closest('[data-menu-return]');
        if (returnButton) {
            event.preventDefault();
            showPageContent();
            return;
        }

        var deleteButton = event.target.closest('[data-menu-delete]');
        if (!deleteButton) {
            return;
        }

        event.preventDefault();
        var form = deleteButton.closest('[data-red-menu-editor]');
        var recordId = parseInt(deleteButton.dataset.menuDelete || '0', 10);
        var label = String(deleteButton.dataset.menuDeleteLabel || 'this button').trim();
        if (!form || recordId <= 0
            || !window.confirm('Delete “' + label + '” from the navigation? Nested buttons may also be removed.')) {
            return;
        }

        var csrfInput = form.querySelector('input[name="csrf_token"]');
        setBusy(form, true);
        setStatus(form, 'Deleting ' + label + '…', 'pending');

        $.ajax({
            type: 'POST',
            url: '/admin/bin/delete_label.php',
            data: {
                RecordID: recordId,
                T: 'main',
                csrf_token: csrfInput ? csrfInput.value : ''
            }
        }).done(function (response) {
            if (String(response || '').trim() !== 'yes') {
                setBusy(form, false);
                setStatus(form, 'The button could not be deleted. Please try again.', 'error');
                return;
            }

            document.body.dataset.menuNeedsRefresh = 'true';
            refreshEditor('Navigation button deleted.', 'success');
        }).fail(function () {
            setBusy(form, false);
            setStatus(form, 'The button could not be deleted. Check the connection and try again.', 'error');
        });
    });
})(window, document, window.jQuery);
