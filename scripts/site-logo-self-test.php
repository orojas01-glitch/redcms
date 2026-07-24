<?php
require_once dirname(__DIR__) . '/includes/site_logo_helpers.php';

$assertions = 0;
function red_site_logo_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$projectRoot = dirname(__DIR__);
$unconfigured = red_site_logo_fact($projectRoot, '');
red_site_logo_test_assert(
    $unconfigured['configured'] === false
        && $unconfigured['valid'] === false
        && $unconfigured['active'] === false
        && $unconfigured['reason'] === 'not-configured',
    'an empty logo setting cleanly selects the template fallback'
);
red_site_logo_test_assert(
    red_site_logo_public_context($projectRoot, '') === null,
    'an empty logo setting exposes no public override'
);
red_site_logo_test_assert(
    !is_file($projectRoot . '/images/logo.png'),
    'the confusing generic logo placeholder is absent from the repository'
);

$temporaryRoot = sys_get_temp_dir() . '/redcms-logo-self-test-' . bin2hex(random_bytes(6));
$temporaryImages = $temporaryRoot . '/images';
if (!mkdir($temporaryImages, 0700, true)) {
    throw new RuntimeException('Unable to create temporary logo fixture directory.');
}
$temporaryLogo = $temporaryImages . '/custom-logo.png';
if (!copy($projectRoot . '/themes/adriana-granobles/assets/images/logo.png', $temporaryLogo)) {
    throw new RuntimeException('Unable to copy temporary logo fixture.');
}

try {
    $custom = red_site_logo_fact($temporaryRoot, 'custom-logo.png');
    red_site_logo_test_assert(
        $custom['valid'] === true
            && $custom['active'] === true
            && $custom['reason'] === 'custom-raster',
        'a managed PNG becomes the active shared override'
    );
    red_site_logo_test_assert(
        red_site_logo_public_context($temporaryRoot, 'custom-logo.png') === [
            'url' => '/images/custom-logo.png',
            'filename' => 'custom-logo.png',
            'mime' => 'image/png',
            'width' => 264,
            'height' => 104,
            'source' => 'advanced.Website_Logo',
        ],
        'the public context exposes only bounded template-ready logo metadata'
    );
    $redThemeHeaderContext = [
        'mode' => 'production',
        'homeUrl' => '/',
        'siteTitle' => 'Logo Contract Test',
        'logo' => red_site_logo_public_context($temporaryRoot, 'custom-logo.png'),
        'customHtml' => '',
    ];
    ob_start();
    require $projectRoot . '/themes/starter-reference/partials/production-header.php';
    $starterHeader = ob_get_clean();
    red_site_logo_test_assert(
        is_string($starterHeader)
            && strpos($starterHeader, 'class="starter-brand__logo"') !== false
            && strpos($starterHeader, '/images/custom-logo.png') !== false
            && strpos($starterHeader, 'starter-brand__mark') === false,
        'the starter header consumes the shared logo override instead of its fallback mark'
    );
    ob_start();
    require $projectRoot . '/themes/adriana-granobles/partials/production-header.php';
    $adrianaHeader = ob_get_clean();
    red_site_logo_test_assert(
        is_string($adrianaHeader)
            && strpos($adrianaHeader, '/images/custom-logo.png') !== false
            && strpos($adrianaHeader, '/themes/adriana-granobles/assets/images/logo.png') === false,
        'the Adriana header consumes the shared override instead of its bundled logo'
    );
    red_site_logo_test_assert(
        red_site_logo_fact($temporaryRoot, '../custom-logo.png')['reason'] === 'unsafe-filename',
        'parent traversal is rejected'
    );
    red_site_logo_test_assert(
        red_site_logo_fact($temporaryRoot, 'custom-logo.svg')['reason'] === 'unsupported-type',
        'SVG input is rejected by the shared resolver'
    );
    red_site_logo_test_assert(
        red_site_logo_fact($temporaryRoot, 'missing.png')['reason'] === 'missing',
        'a missing managed file fails closed'
    );
} finally {
    if (is_file($temporaryLogo)) {
        unlink($temporaryLogo);
    }
    if (is_dir($temporaryImages)) {
        rmdir($temporaryImages);
    }
    if (is_dir($temporaryRoot)) {
        rmdir($temporaryRoot);
    }
}

echo 'Site logo self-test passed: ' . $assertions . " assertions.\n";
