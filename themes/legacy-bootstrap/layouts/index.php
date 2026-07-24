<?php
/**
 * Legacy public layout: two columns followed by one full-width row.
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
echo('<div class="col-lg-6">');
$redThemeRenderSlot('1');
echo('</div>');
echo('<div class="col-lg-6">');
$redThemeRenderSlot('2');
echo('</div>');
echo('</div>');
echo('</div>');
echo('<div class="container px-4 pb-0 pt-3">');
echo('<div class="row">');
echo('<div class="col-lg-12">');
$redThemeRenderSlot('3');
echo('</div>');
echo('</div>');
echo('</div>');
