<?php
/**
 * Legacy public layout: main content with a right sidebar.
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
echo('<div class="col-lg-8 col-md-8 col-sm-8">');
$redThemeRenderSlot('1');
echo('</div>');
echo('<div class="col-lg-4 col-md-4 col-sm-4">');
$redThemeRenderSlot('2');
echo('</div>');
echo('</div>');
echo('</div>');
