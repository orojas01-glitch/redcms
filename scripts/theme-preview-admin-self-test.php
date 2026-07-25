<?php
require_once dirname(__DIR__) . '/includes/theme_preview_admin_helpers.php';
require_once dirname(__DIR__) . '/includes/theme_preview_helpers.php';

$assertions = 0;
function red_preview_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$projectRoot = dirname(__DIR__);
$inventory = red_theme_admin_preview_inventory($projectRoot);
$inventoryById = [];
foreach ($inventory as $theme) {
    $inventoryById[$theme['themeId']] = $theme;
}

red_preview_test_assert(
    array_keys($inventoryById) === ['legacy-bootstrap', 'starter-reference'],
    'only the recovery renderer and portable starter are discoverable'
);
red_preview_test_assert(
    $inventoryById['legacy-bootstrap']['isLiveCompatibility'] === true
        && $inventoryById['legacy-bootstrap']['previewAvailable'] === false,
    'the legacy package remains a non-preview recovery renderer'
);
red_preview_test_assert(
    $inventoryById['starter-reference']['previewAvailable'] === true
        && $inventoryById['starter-reference']['previewModes'] === ['Contact canary', 'Home route']
        && $inventoryById['starter-reference']['productionSupported'] === true,
    'the starter package exposes its fixed production previews'
);
red_preview_test_assert(
    red_theme_admin_preview_can_launch($inventory, 'contact')
        && red_theme_admin_preview_can_launch($inventory, 'home'),
    'both starter preview modes are launchable'
);
red_preview_test_assert(
    red_theme_admin_preview_request_action(['action' => 'start']) === 'start'
        && red_theme_admin_preview_request_action(['action' => 'start-home']) === 'start-home'
        && red_theme_admin_preview_request_action(['action' => 'exit']) === 'exit',
    'the preview action allowlist accepts only the starter lifecycle'
);
red_preview_test_assert(
    red_theme_admin_preview_query([]) === ['view' => 'shell', 'status' => '']
        && red_theme_admin_preview_query(['view' => 'contact']) === ['view' => 'contact', 'status' => '']
        && red_theme_admin_preview_query(['view' => 'home']) === ['view' => 'home', 'status' => ''],
    'the preview query allowlist exposes the shell and starter views'
);
$rejected = false;
try {
    red_theme_admin_preview_mode('client-home');
} catch (InvalidArgumentException $exception) {
    $rejected = true;
}
red_preview_test_assert($rejected, 'unknown preview modes fail closed');

echo 'Theme preview admin self-test passed: ' . $assertions . " assertions.\n";
