(function () {
    'use strict';

    function all(root, selector) {
        return Array.prototype.slice.call(root.querySelectorAll(selector));
    }

    function text(value) {
        return value === null || typeof value === 'undefined' ? '' : String(value).trim();
    }

    function same(left, right) {
        return text(left).toLocaleLowerCase() === text(right).toLocaleLowerCase();
    }

    function replaceOptions(select, options, isAllowed, preserveUnavailable) {
        var current = text(select.value);
        var currentKept = false;
        while (select.firstChild) {
            select.removeChild(select.firstChild);
        }

        options.forEach(function (item) {
            var value = text(item.value);
            var unavailable = item.getAttribute('data-red-hierarchy-unavailable') === 'true';
            var keep = value === '' || isAllowed(item) ||
                (preserveUnavailable && unavailable && same(value, current));
            if (!keep) {
                return;
            }
            select.appendChild(item);
            if (same(value, current)) {
                currentKept = true;
            }
        });
        select.value = currentKept ? current : '';
    }

    function init(form) {
        if (!form || form.getAttribute('data-red-area-hierarchy') === 'true' ||
            form.matches('[data-red-move-content]')) {
            return;
        }
        var section = form.querySelector('select[name="Sections"]');
        var category = form.querySelector('select[name="Categories"]');
        var subcategory = form.querySelector('select[name="SubCategories"]');
        if (!section || !category || !subcategory) {
            return;
        }

        var categoryOptions = all(category, 'option');
        var subcategoryOptions = all(subcategory, 'option');

        function refreshCategory(preserveUnavailable) {
            var sectionValue = text(section.value);
            replaceOptions(category, categoryOptions, function (item) {
                return sectionValue !== '' && same(item.dataset.parentSection, sectionValue);
            }, preserveUnavailable);
            category.disabled = sectionValue === '';
        }

        function refreshSubcategory(preserveUnavailable) {
            var sectionValue = text(section.value);
            var categoryValue = text(category.value);
            replaceOptions(subcategory, subcategoryOptions, function (item) {
                return sectionValue !== '' && categoryValue !== '' &&
                    same(item.dataset.parentSection, sectionValue) &&
                    same(item.dataset.parentCategory, categoryValue);
            }, preserveUnavailable);
            subcategory.disabled = sectionValue === '' || categoryValue === '';
        }

        form.setAttribute('data-red-area-hierarchy', 'true');
        refreshCategory(true);
        refreshSubcategory(true);
        section.addEventListener('change', function () {
            refreshCategory(false);
            refreshSubcategory(false);
        });
        category.addEventListener('change', function () {
            refreshSubcategory(false);
        });
    }

    function scan(root) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return;
        }
        if (root.matches && root.matches('form')) {
            init(root);
        }
        all(root, 'form').forEach(init);
    }

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
