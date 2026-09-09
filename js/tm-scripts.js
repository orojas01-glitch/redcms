/* RED-CMS legacy-theme initialization, 1.0.0. SPDX-License-Identifier: MIT.
 * Preserve the existing year and mobile navigation hooks. The document owns
 * its viewport; Superfish owns keyboard/touch interaction.
 */
var currentYear = new Date().getFullYear();
jQuery(function ($) {
    $('#copyright-year').text(new Date().getFullYear());
    $('.sf-menu').mobileMenu();
});
