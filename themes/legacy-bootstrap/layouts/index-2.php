<?php
/**
 * Legacy public layout: four stacked full-width rows.
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
echo('<div class="col-lg-12 pt-3">');
$redThemeRenderSlot('1');
echo('</div>');
echo('<div class="col-lg-12 pt-3">');
$redThemeRenderSlot('2');
echo('</div>');
echo('<div class="col-lg-12 pt-3">');
$redThemeRenderSlot('3');
echo('</div>');
echo('<div class="col-lg-12 pt-3">');
$redThemeRenderSlot('4');
echo('</div>');
echo('</div>');
echo('</div>');
