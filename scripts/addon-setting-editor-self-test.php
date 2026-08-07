<?php
/** Dependency-free checks for the core-owned add-on settings editor boundary. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/addon_setting_editor_helpers.php';

$assertions = 0;

function red_addon_setting_editor_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_setting_editor_test_manifest()
{
    return [
        'id' => 'redcms.editor-fixture',
        'name' => 'Editor fixture',
        'description' => 'Core-owned settings editor fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'permissions' => ['fixture.settings.manage'],
        'settings' => [
            [
                'key' => 'store.name',
                'label' => 'Store name <unsafe>',
                'type' => 'text',
                'secret' => false,
                'permission' => 'fixture.settings.manage',
                'default' => 'Fixture Store',
            ],
            [
                'key' => 'checkout.enabled',
                'label' => 'Checkout enabled',
                'type' => 'boolean',
                'secret' => false,
                'permission' => 'fixture.settings.manage',
                'default' => false,
            ],
            [
                'key' => 'inventory.limit',
                'label' => 'Inventory limit',
                'type' => 'integer',
                'secret' => false,
                'permission' => 'fixture.settings.manage',
                'default' => 25,
            ],
            [
                'key' => 'store.currency',
                'label' => 'Currency',
                'type' => 'select',
                'secret' => false,
                'permission' => 'fixture.settings.manage',
                'default' => 'USD',
                'options' => ['USD', 'COP'],
            ],
            [
                'key' => 'payment.api-key',
                'label' => 'Payment API key',
                'type' => 'secret-reference',
                'secret' => true,
                'permission' => 'fixture.settings.manage',
            ],
        ],
    ];
}

try {
    $manifest = red_addon_setting_editor_test_manifest();
    $csrf = str_repeat('b', 64);
    $plan = str_repeat('a', 64);

    $edit = red_addon_setting_editor_request([
        'PackageID' => 'redcms.editor-fixture',
        'csrf_token' => $csrf,
    ], 'edit');
    red_addon_setting_editor_test_assert(
        !empty($edit['valid'])
            && $edit['packageId'] === 'redcms.editor-fixture'
            && $edit['expectedPlanSha256'] === '',
        'edit request accepts only the package selector and CSRF token'
    );

    $update = red_addon_setting_editor_request([
        'ExpectedPlanSha256' => $plan,
        'PackageID' => 'redcms.editor-fixture',
        'Settings' => [
            'checkout.enabled' => '1',
            'inventory.limit' => '25',
            'store.currency' => 'USD',
            'store.name' => 'Fixture Store',
        ],
        'csrf_token' => $csrf,
    ], 'update');
    red_addon_setting_editor_test_assert(
        !empty($update['valid'])
            && array_keys($update['settings']) === [
                'checkout.enabled',
                'inventory.limit',
                'store.currency',
                'store.name',
            ],
        'update request normalizes ordinary setting keys deterministically'
    );

    $invalidTopLevel = red_addon_setting_editor_request([
        'ExpectedPlanSha256' => $plan,
        'PackageID' => 'redcms.editor-fixture',
        'Settings' => ['store.name' => 'Fixture Store'],
        'csrf_token' => $csrf,
        'unexpected' => 'no',
    ], 'update');
    red_addon_setting_editor_test_assert(
        empty($invalidTopLevel['valid']),
        'unexpected top-level request keys fail closed'
    );

    $invalidNested = red_addon_setting_editor_request([
        'ExpectedPlanSha256' => $plan,
        'PackageID' => 'redcms.editor-fixture',
        'Settings' => ['store.name' => ['nested']],
        'csrf_token' => $csrf,
    ], 'update');
    red_addon_setting_editor_test_assert(
        empty($invalidNested['valid']),
        'nested setting values fail closed before validation'
    );

    $ordinary = [
        'checkout.enabled' => '1',
        'inventory.limit' => '25',
        'store.currency' => 'COP',
        'store.name' => 'Updated Store',
    ];
    $decoded = red_addon_setting_editor_decode_values(
        $manifest,
        $ordinary,
        ['payment.api-key' => 'config:redcms.editor.payment-key']
    );
    red_addon_setting_editor_test_assert(
        !empty($decoded['valid'])
            && $decoded['ordinaryValues']['checkout.enabled'] === true
            && $decoded['ordinaryValues']['inventory.limit'] === 25
            && $decoded['ordinaryValues']['store.currency'] === 'COP'
            && $decoded['configuredValues']['payment.api-key'] ===
                'config:redcms.editor.payment-key',
        'ordinary form scalars normalize to typed values while secrets stay internal'
    );

    $secretSubmission = red_addon_setting_editor_decode_values(
        $manifest,
        $ordinary + ['payment.api-key' => 'config:redcms.attacker'],
        ['payment.api-key' => 'config:redcms.editor.payment-key']
    );
    red_addon_setting_editor_test_assert(
        empty($secretSubmission['valid'])
            && in_array('secret_submission', $secretSubmission['errors'], true),
        'secret-reference submissions are rejected'
    );

    $invalidBoolean = red_addon_setting_editor_decode_values(
        $manifest,
        array_merge($ordinary, ['checkout.enabled' => 'yes']),
        ['payment.api-key' => 'config:redcms.editor.payment-key']
    );
    $invalidInteger = red_addon_setting_editor_decode_values(
        $manifest,
        array_merge($ordinary, ['inventory.limit' => '01']),
        ['payment.api-key' => 'config:redcms.editor.payment-key']
    );
    red_addon_setting_editor_test_assert(
        empty($invalidBoolean['valid'])
            && empty($invalidInteger['valid']),
        'boolean and integer controls reject loose or noncanonical scalars'
    );

    $settings = [];
    foreach (red_addon_settings_schema($manifest) as $definition) {
        $settings[] = [
            'key' => $definition['key'],
            'type' => $definition['type'],
            'configured' => true,
        ];
    }
    $context = [
        'ready' => true,
        'packageId' => 'redcms.editor-fixture',
        'version' => '1.0.0',
        'lifecycleState' => 'installed_disabled',
        'manifest' => $manifest,
        'settings' => $settings,
        'ordinaryValues' => [
            'store.name' => 'Fixture Store',
            'checkout.enabled' => false,
            'inventory.limit' => 25,
            'store.currency' => 'USD',
        ],
        'secretConfigured' => ['payment.api-key' => true],
        'modelSha256' => $plan,
        'planSha256' => $plan,
        'reason' => 'ready',
    ];
    $html = red_addon_setting_editor_render($context, $csrf);
    red_addon_setting_editor_test_assert(
        str_contains($html, 'action="/admin/bin/update_addon_settings.php"')
            && str_contains($html, 'name="Settings[store.name]"')
            && str_contains($html, 'data-red-addon-secret-state="configured"'),
        'renderer emits a core-owned form and masked secret state'
    );
    red_addon_setting_editor_test_assert(
        str_contains($html, 'Store name &lt;unsafe&gt;')
            && !str_contains($html, 'config:redcms.editor.payment-key')
            && !str_contains($html, 'config:redcms.attacker'),
        'renderer escapes labels and never discloses opaque secret references'
    );
    red_addon_setting_editor_test_assert(
        !str_contains($html, 'addon.php')
            && !str_contains($html, 'callback')
            && str_contains($html, 'name="ExpectedPlanSha256"'),
        'renderer contains no package markup and binds the stale-plan hash'
    );

    $forgedContext = $context;
    unset($forgedContext['planSha256']);
    red_addon_setting_editor_test_assert(
        red_addon_setting_editor_render($forgedContext, $csrf)
            === red_addon_setting_editor_ui_unavailable(),
        'forged or incomplete render context fails closed'
    );

    red_addon_setting_editor_test_assert(
        red_addon_setting_editor_package(
            dirname(__DIR__),
            'redcms.editor-fixture'
        ) === null,
        'package discovery returns no synthetic package without a trusted package directory'
    );

    echo 'Add-on settings editor self-test passed (' . $assertions
        . " assertions).\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
