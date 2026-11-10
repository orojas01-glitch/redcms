<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/addon_setting_editor_helpers.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$projectRoot = dirname(__DIR__);
$manifest = static fn (string $id, string $name, array $settings): array => [
    'id' => $id,
    'name' => $name,
    'version' => '1.2.3',
    'permissions' => ['store.settings.manage'],
    'settings' => $settings,
];
$setting = [
    'key' => 'checkout.wompi-enabled',
    'label' => 'Offer Wompi Web Checkout (COP only)',
    'type' => 'boolean',
    'secret' => false,
    'permission' => 'store.settings.manage',
    'default' => null,
];
$catalog = [
    'vendor.with-settings' => [
        'valid' => true,
        'manifest' => $manifest('vendor.with-settings', 'With Settings', [$setting]),
    ],
    'vendor.no-settings' => [
        'valid' => true,
        'manifest' => $manifest('vendor.no-settings', 'No Settings', []),
    ],
    'vendor.invalid' => [
        'valid' => false,
        'manifest' => $manifest('vendor.invalid', 'Invalid', [$setting]),
    ],
    'vendor.disabled' => [
        'valid' => true,
        'manifest' => $manifest('vendor.disabled', '', [$setting]),
    ],
];
$installations = [
    'vendor.with-settings' => [
        'LifecycleState' => 'enabled',
        'PackageVersion' => '1.2.2',
    ],
    'vendor.no-settings' => ['LifecycleState' => 'enabled', 'PackageVersion' => '1.2.3'],
    'vendor.invalid' => ['LifecycleState' => 'enabled', 'PackageVersion' => '1.2.3'],
    'vendor.disabled' => [
        'LifecycleState' => 'installed_disabled',
        'PackageVersion' => '1.2.3',
    ],
    'vendor.failed' => ['LifecycleState' => 'failed', 'PackageVersion' => '1.2.3'],
    'vendor.missing-files' => ['LifecycleState' => 'enabled', 'PackageVersion' => '1.2.3'],
];

$listed = red_addon_settings_page_packages($installations, $catalog);
$assert(
    array_keys($listed) === ['vendor.disabled', 'vendor.with-settings'],
    'only installed, valid add-ons that declare settings are listed, sorted'
);
$assert(
    $listed['vendor.with-settings'] === [
        'name' => 'With Settings',
        'version' => '1.2.3',
        'installedVersion' => '1.2.2',
        'state' => 'enabled',
        'settingCount' => 1,
    ],
    'a listed add-on shows its name, file and installed versions, and state'
);
$assert(
    $listed['vendor.disabled']['name'] === 'vendor.disabled'
        && $listed['vendor.disabled']['state'] === 'installed_disabled',
    'an add-on without a name falls back to its id and installed-disabled is listed'
);
$assert(
    red_addon_settings_page_packages(null, $catalog) === []
        && red_addon_settings_page_packages($installations, null) === [],
    'unavailable registry or catalog lists nothing'
);

$page = (string) file_get_contents($projectRoot . '/admin/bin/addon_settings.php');
$assert(
    str_contains($page, "red_require_admin_site_manager(\$method === 'POST')")
        && str_contains($page, "red_addon_setting_editor_request(\$_POST, 'edit')")
        && str_contains($page, 'array_key_exists($request[\'packageId\'], $packages)')
        && str_contains($page, 'red_addon_setting_editor_context(')
        && str_contains($page, 'red_addon_setting_editor_render($context, $csrfToken)'),
    'the page requires a site manager, CSRF on POST, a listed package, and the core editor'
);
$assert(
    str_contains($page, "script-src 'self'")
        && str_contains($page, "default-src 'none'")
        && str_contains($page, 'X-Frame-Options: SAMEORIGIN')
        && !preg_match('/<script(?![^>]*\bsrc=)/', $page)
        && !str_contains($page, 'ADDON_SECRET_VALUES')
        && !str_contains($page, 'resolve('),
    'the page has a strict CSP, no inline script, and never resolves secrets'
);
$script = (string) file_get_contents(
    $projectRoot . '/admin/assets/js/addon-settings-page.js'
);
$assert(
    str_contains($script, "credentials: 'same-origin'")
        && str_contains($script, "'X-CSRF-Token'")
        && str_contains($script, 'form[data-red-addon-settings-form]')
        && !str_contains($script, 'innerHTML'),
    'the save script posts the core form same-origin with CSRF and writes text only'
);
$advanced = (string) file_get_contents(
    $projectRoot . '/admin/class/class_edit_advanced.php'
);
$assert(
    str_contains(
        $advanced,
        "red_admin_list_ui_action_link('/admin/bin/addon_settings.php', 'Open Add-on settings')"
    )
        && str_contains($advanced, '($canManageSite ? 3 : 0)'),
    'Advanced lists Add-on settings for site managers'
);

echo 'Admin add-on settings page self-test passed: ' . $assertions
    . " assertions.\n";
