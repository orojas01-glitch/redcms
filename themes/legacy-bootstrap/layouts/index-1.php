<?php
/**
 * Legacy public layout: one full-width row followed by three columns.
 */

if (!isset($redThemeRenderBreadcrumb, $redThemeRenderSlot)
    || !is_callable($redThemeRenderBreadcrumb)
    || !is_callable($redThemeRenderSlot)
) {
    throw new RuntimeException('Legacy public layout callbacks are unavailable.');
}

echo('<div class="container px-4 pb-0 pt-3">');
echo('<div class="row">');
$redThemeRenderBreadcrumb();
echo('<div class="col-lg-12">');
$redThemeRenderSlot('1');
echo('</div>');
echo('</div>');
echo('</div>');
echo('<div class="container px-4 pb-0 pt-3">');
echo('<div class="row">');
echo('<div class="col-lg-4">');
$redThemeRenderSlot('2');
echo('</div>');
echo('<div class="col-lg-4">');
$redThemeRenderSlot('3');
echo('</div>');
echo('<div class="col-lg-4">');
$redThemeRenderSlot('4');
echo('</div>');
echo('</div>');
echo('</div>');
