(function () {
    'use strict';

    function initializeLayoutPreview(root) {
        var trigger = root.querySelector('[data-layout-preview-trigger]');
        var panel = root.querySelector('[data-layout-preview-panel]');
        var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
        var pinned = false;

        if (!trigger || !panel) {
            return;
        }

        function setOpen(open) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.hidden = !open;
            root.classList.toggle('is-open', open);
        }

        function closePreview() {
            pinned = false;
            setOpen(false);
        }

        root.addEventListener('mouseenter', function () {
            if (finePointer.matches) {
                setOpen(true);
            }
        });

        root.addEventListener('mouseleave', function () {
            if (!pinned && !root.contains(document.activeElement)) {
                setOpen(false);
            }
        });

        root.addEventListener('focusin', function (event) {
            if (event.target === trigger || finePointer.matches) {
                setOpen(true);
            }
        });

        root.addEventListener('focusout', function () {
            window.setTimeout(function () {
                if (!pinned && !root.contains(document.activeElement)) {
                    setOpen(false);
                }
            }, 0);
        });

        trigger.addEventListener('click', function () {
            if (pinned) {
                closePreview();
                return;
            }

            pinned = true;
            setOpen(true);
        });

        root.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || panel.hidden) {
                return;
            }

            closePreview();
            if (event.target === trigger || panel.contains(event.target)) {
                event.preventDefault();
                trigger.focus();
            }
        });

        document.addEventListener('pointerdown', function (event) {
            if (!root.contains(event.target)) {
                closePreview();
            }
        });
    }

    function initializeAllLayoutPreviews() {
        document.querySelectorAll('[data-layout-preview]').forEach(initializeLayoutPreview);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAllLayoutPreviews);
    } else {
        initializeAllLayoutPreviews();
    }
}());
