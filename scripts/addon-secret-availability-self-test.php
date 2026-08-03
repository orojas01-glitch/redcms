<?php
/**
 * Dependency-free secret-reference availability acceptance fixture.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/addon_secret_availability_helpers.php';

$assertions = 0;

function red_addon_secret_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$manifest = [
    'id' => 'redcms.store-lite',
    'permissions' => ['store.settings.manage'],
    'settings' => [
        [
            'key' => 'store.currency',
            'label' => 'Currency',
            'type' => 'select',
            'secret' => false,
            'permission' => 'store.settings.manage',
            'default' => 'USD',
            'options' => ['USD', 'EUR'],
        ],
        [
            'key' => 'payment.api-key',
            'label' => 'Payment API key',
            'type' => 'secret-reference',
            'secret' => true,
            'permission' => 'store.settings.manage',
        ],
        [
            'key' => 'payment.webhook-key',
            'label' => 'Payment webhook key',
            'type' => 'secret-reference',
            'secret' => true,
            'permission' => 'store.settings.manage',
        ],
    ],
];
$configured = [
    'payment.api-key' => 'config:redcms.store-lite.payment-api-key',
    'payment.webhook-key' => 'config:redcms.store-lite.webhook-key',
];

try {
    $empty = red_addon_secret_reference_declarations([], '');
    red_addon_secret_test_assert(
        !empty($empty['valid'])
            && $empty['references'] === []
            && preg_match('/\A[a-f0-9]{64}\z/', $empty['declarationSha256']) === 1,
        'an empty server-local declaration is valid and deterministic'
    );

    $declarations = red_addon_secret_reference_declarations(
        ['config:redcms.store-lite.webhook-key'],
        'config:redcms.store-lite.payment-api-key'
    );
    red_addon_secret_test_assert(
        !empty($declarations['valid'])
            && $declarations['references'] === [
                'config:redcms.store-lite.payment-api-key',
                'config:redcms.store-lite.webhook-key',
            ],
        'local and environment declarations merge into one sorted inventory'
    );

    $duplicate = red_addon_secret_reference_declarations(
        ['config:redcms.store-lite.payment-api-key'],
        'config:redcms.store-lite.payment-api-key'
    );
    red_addon_secret_test_assert(
        !empty($duplicate['valid'])
            && count($duplicate['references']) === 1,
        'duplicate declarations collapse deterministically'
    );

    foreach ([
        [['CONFIG:UPPER'], ''],
        [['../secret'], ''],
        [[], 'config:valid, config:space'],
        [['config:valid', ['nested']], ''],
    ] as $invalidInput) {
        $invalid = red_addon_secret_reference_declarations(
            $invalidInput[0],
            $invalidInput[1]
        );
        red_addon_secret_test_assert(
            empty($invalid['valid'])
                && $invalid['references'] === []
                && $invalid['declarationSha256'] === '',
            'invalid declarations fail closed without a partial inventory'
        );
    }

    $available = red_addon_secret_availability_evidence(
        $manifest,
        $configured,
        'redcms.store-lite',
        $declarations
    );
    red_addon_secret_test_assert(
        !empty($available['valid'])
            && !empty($available['available'])
            && $available['secretSettingCount'] === 2
            && $available['availableCount'] === 2
            && $available['missing'] === [],
        'complete opaque declarations produce available evidence'
    );
    red_addon_secret_test_assert(
        preg_match('/\A[a-f0-9]{64}\z/', $available['configurationSha256']) === 1
            && preg_match('/\A[a-f0-9]{64}\z/', $available['evidenceSha256']) === 1
            && json_encode($available) !== false
            && strpos(json_encode($available), 'config:') === false,
        'evidence exposes hashes and counts but no secret reference identifier'
    );

    $availableAgain = red_addon_secret_availability_evidence(
        $manifest,
        $configured,
        'redcms.store-lite',
        $declarations
    );
    red_addon_secret_test_assert(
        $availableAgain === $available,
        'unchanged configuration and declarations produce exact evidence'
    );

    $partialDeclarations = red_addon_secret_reference_declarations(
        ['config:redcms.store-lite.payment-api-key'],
        ''
    );
    $partial = red_addon_secret_availability_evidence(
        $manifest,
        $configured,
        'redcms.store-lite',
        $partialDeclarations
    );
    red_addon_secret_test_assert(
        !empty($partial['valid'])
            && empty($partial['available'])
            && $partial['availableCount'] === 1
            && $partial['missing'] === ['payment.webhook-key']
            && $partial['evidenceSha256'] !== $available['evidenceSha256'],
        'missing declarations identify only setting keys and change evidence'
    );

    $changedConfigured = $configured;
    $changedConfigured['payment.webhook-key'] =
        'config:redcms.store-lite.changed-webhook-key';
    $changed = red_addon_secret_availability_evidence(
        $manifest,
        $changedConfigured,
        'redcms.store-lite',
        $declarations
    );
    red_addon_secret_test_assert(
        !empty($changed['valid'])
            && empty($changed['available'])
            && $changed['configurationSha256'] !== $available['configurationSha256']
            && $changed['evidenceSha256'] !== $available['evidenceSha256'],
        'changed opaque references invalidate prior availability evidence'
    );

    $invalidPackage = red_addon_secret_availability_evidence(
        $manifest,
        $configured,
        'redcms.other',
        $declarations
    );
    red_addon_secret_test_assert(
        empty($invalidPackage['valid'])
            && $invalidPackage['errors'] === ['package_invalid'],
        'evidence binds the exact manifest package identity'
    );

    $invalidConfiguration = red_addon_secret_availability_evidence(
        $manifest,
        ['payment.api-key' => 'actual-secret'],
        'redcms.store-lite',
        $declarations
    );
    red_addon_secret_test_assert(
        empty($invalidConfiguration['valid'])
            && $invalidConfiguration['errors'] === ['configuration_invalid'],
        'raw or incomplete secret configuration fails closed'
    );

    $invalidDeclarations = red_addon_secret_availability_evidence(
        $manifest,
        $configured,
        'redcms.store-lite',
        ['valid' => false]
    );
    red_addon_secret_test_assert(
        empty($invalidDeclarations['valid'])
            && $invalidDeclarations['errors'] === ['declaration_invalid'],
        'malformed availability input fails closed'
    );

    $forgedDeclarations = $declarations;
    $forgedDeclarations['references'][] =
        'config:redcms.store-lite.forged-key';
    $forged = red_addon_secret_availability_evidence(
        $manifest,
        $configured,
        'redcms.store-lite',
        $forgedDeclarations
    );
    red_addon_secret_test_assert(
        empty($forged['valid'])
            && $forged['errors'] === ['declaration_invalid'],
        'changed declarations cannot reuse a stale declaration hash'
    );

    $ordinaryManifest = [
        'id' => 'redcms.events',
        'permissions' => ['events.settings.manage'],
        'settings' => [[
            'key' => 'events.timezone',
            'label' => 'Timezone',
            'type' => 'text',
            'secret' => false,
            'permission' => 'events.settings.manage',
            'default' => 'America/New_York',
        ]],
    ];
    $ordinary = red_addon_secret_availability_evidence(
        $ordinaryManifest,
        [],
        'redcms.events',
        $empty
    );
    red_addon_secret_test_assert(
        !empty($ordinary['valid'])
            && !empty($ordinary['available'])
            && $ordinary['secretSettingCount'] === 0
            && $ordinary['availableCount'] === 0,
        'a valid package with no secret settings needs no declarations'
    );

    $encodedSources = file_get_contents(
        dirname(__DIR__) . '/includes/addon_secret_availability_helpers.php'
    );
    red_addon_secret_test_assert(
        is_string($encodedSources)
            && strpos($encodedSources, 'RED_Addon_Settings') === false
            && strpos($encodedSources, 'mysqli_') === false
            && strpos($encodedSources, 'addon.php') === false,
        'the availability boundary has no database or package execution path'
    );

    fwrite(
        STDOUT,
        'Add-on secret-reference availability self-test passed ('
            . $assertions . " assertions).\n"
    );
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
