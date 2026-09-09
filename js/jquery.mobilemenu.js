/* RED-CMS mobile menu compatibility implementation, 1.0.0.
 * SPDX-License-Identifier: MIT
 * Original implementation for RED-CMS; retains the existing mobileMenu interface.
 */
(function ($) {
    'use strict';
    $.fn.mobileMenu = function (options) {
        var settings = $.extend({defaultText: 'Navigate to...', className: 'select-menu', subMenuClass: 'sub-menu', subMenuDash: '\u2013'}, options);
        return this.each(function () {
            var menu = this;
            if (menu._redMobileSelect) menu._redMobileSelect.remove();
            var select = document.createElement('select');
            select.className = settings.className + ' ' + menu.className;
            select.setAttribute('aria-label', menu.getAttribute('aria-label') || settings.defaultText);
            var placeholder = new Option(settings.defaultText, '#');
            select.add(placeholder);
            var dash = String(settings.subMenuDash).replace(/&ndash;/g, '\u2013').replace(/&mdash;/g, '\u2014').replace(/&nbsp;/g, '\u00a0');
            menu.querySelectorAll('ul').forEach(function (list) { list.classList.add(settings.subMenuClass); });
            menu.querySelectorAll('a[href]').forEach(function (anchor) {
                var url;
                try { url = new URL(anchor.getAttribute('href'), document.baseURI); } catch (_) { return; }
                if (!['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol)) return;
                var depth = 0;
                for (var node = anchor.parentElement; node && node !== menu; node = node.parentElement) {
                    if (node.tagName === 'UL') depth++;
                }
                // Menu labels remain text even when they contain literal markup.
                var option = new Option(dash.repeat(depth) + '\u00a0' + anchor.textContent, url.href);
                option.selected = url.href === window.location.href;
                select.add(option);
            });
            select.addEventListener('change', function () {
                if (select.value === '#') return;
                var target;
                try { target = new URL(select.value, document.baseURI); } catch (_) { return; }
                if (['http:', 'https:', 'mailto:', 'tel:'].includes(target.protocol)) window.location.assign(target.href);
            });
            menu.insertAdjacentElement('afterend', select);
            menu._redMobileSelect = select;
        });
    };
})(jQuery);
