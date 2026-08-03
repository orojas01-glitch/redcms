<?php
/**
 * Dependency-free add-on asset-plan acceptance fixture.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/addon_asset_helpers.php';

$assertions = 0;

function red_addon_asset_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_asset_test_manifest(array $public = [], array $admin = [])
{
    return [
        'id' => 'redcms.store-lite',
        'assets' => [
            'public' => $public,
            'admin' => $admin,
        ],
    ];
}

$publicStyle = [
    'path' => 'assets/public/store-lite.css',
    'sha256' => hash('sha256', 'public-style'),
    'location' => 'head',
];
$publicScript = [
    'path' => 'assets/public/store-lite.js',
    'sha256' => hash('sha256', 'public-script'),
    'location' => 'body-end',
];
$adminStyle = [
    'path' => 'assets/admin/store-lite.css',
    'sha256' => hash('sha256', 'admin-style'),
    'location' => 'head',
];

try {
    $publicPlan = red_addon_asset_plan(
        red_addon_asset_test_manifest([$publicScript, $publicStyle], [$adminStyle]),
        'public'
    );
    red_addon_asset_test_assert(
        !empty($publicPlan['valid'])
            && $publicPlan['packageId'] === 'redcms.store-lite'
            && $publicPlan['surface'] === 'public'
            && count($publicPlan['assets']) === 2
            && $publicPlan['assets'][0]['path'] === 'assets/public/store-lite.js'
            && $publicPlan['assets'][1]['path'] === 'assets/public/store-lite.css',
        'a valid public manifest produces a deterministic namespaced asset plan'
    );
    red_addon_asset_test_assert(
        $publicPlan['assets'][0]['url'] ===
            '/_red/addons/redcms/store-lite/assets/public/store-lite.js?v=' .
                hash('sha256', 'public-script')
            && $publicPlan['assets'][1]['url'] ===
                '/_red/addons/redcms/store-lite/assets/public/store-lite.css?v=' .
                hash('sha256', 'public-style'),
        'canonical URLs include the package namespace, encoded path, and immutable checksum'
    );
    red_addon_asset_test_assert(
        preg_match('/\A[a-f0-9]{64}\z/', $publicPlan['planSha256']) === 1
            && red_addon_asset_plan_is_valid($publicPlan),
        'a canonical asset plan carries verifiable deterministic evidence'
    );

    $head = red_addon_asset_plan_html($publicPlan, 'head');
    $bodyEnd = red_addon_asset_plan_html($publicPlan, 'body-end');
    red_addon_asset_test_assert(
        $head === '<link rel="stylesheet" href="' .
            $publicPlan['assets'][1]['url'] . '">' . "\n"
            && $bodyEnd === '<script src="' . $publicPlan['assets'][0]['url'] .
                '" defer></script>' . "\n",
        'core-owned escaped tags preserve the declared location and safe type'
    );
    red_addon_asset_test_assert(
        red_addon_asset_plan_html($publicPlan, 'invalid') === '',
        'unknown render locations return no markup'
    );

    $adminPlan = red_addon_asset_plan(
        red_addon_asset_test_manifest([$publicStyle], [$adminStyle]),
        'admin'
    );
    red_addon_asset_test_assert(
        !empty($adminPlan['valid'])
            && count($adminPlan['assets']) === 1
            && $adminPlan['assets'][0]['path'] === 'assets/admin/store-lite.css'
            && strpos($adminPlan['assets'][0]['url'], '/_red/addons/redcms/store-lite/') === 0,
        'public and administrator surfaces remain independently namespaced'
    );

    $reordered = red_addon_asset_plan(
        red_addon_asset_test_manifest([$publicStyle, $publicScript], [$adminStyle]),
        'public'
    );
    red_addon_asset_test_assert(
        $reordered === $publicPlan,
        'manifest ordering cannot change the asset plan or its evidence'
    );

    $surfaceReordered = red_addon_asset_plan(
        [
            'id' => 'redcms.store-lite',
            'assets' => [
                'admin' => [$adminStyle],
                'public' => [$publicStyle, $publicScript],
            ],
        ],
        'public'
    );
    red_addon_asset_test_assert(
        $surfaceReordered === $publicPlan,
        'logical manifest surface ordering cannot change the public asset plan'
    );

    foreach ([
        ['invalid-surface', red_addon_asset_test_manifest([$publicStyle]), 'other'],
        ['bad-package', ['id' => 'not-a-package', 'assets' => ['public' => [], 'admin' => []]], 'public'],
        ['bad-assets', ['id' => 'redcms.store-lite', 'assets' => ['public' => []]], 'public'],
        ['outside-assets', red_addon_asset_test_manifest([[
            'path' => 'public/store-lite.css',
            'sha256' => hash('sha256', 'x'),
            'location' => 'head',
        ]]), 'public'],
        ['unsupported-type', red_addon_asset_test_manifest([[
            'path' => 'assets/public/store-lite.svg',
            'sha256' => hash('sha256', 'x'),
            'location' => 'head',
        ]]), 'public'],
        ['style-location', red_addon_asset_test_manifest([[
            'path' => 'assets/public/store-lite.css',
            'sha256' => hash('sha256', 'x'),
            'location' => 'body-end',
        ]]), 'public'],
        ['script-location', red_addon_asset_test_manifest([[
            'path' => 'assets/public/store-lite.js',
            'sha256' => hash('sha256', 'x'),
            'location' => 'head',
        ]]), 'public'],
        ['checksum', red_addon_asset_test_manifest([[
            'path' => 'assets/public/store-lite.css',
            'sha256' => strtoupper(hash('sha256', 'x')),
            'location' => 'head',
        ]]), 'public'],
        ['extra-key', red_addon_asset_test_manifest([[
            'path' => 'assets/public/store-lite.css',
            'sha256' => hash('sha256', 'x'),
            'location' => 'head',
            'onload' => 'forbidden',
        ]]), 'public'],
    ] as $case) {
        $invalid = red_addon_asset_plan($case[1], $case[2]);
        red_addon_asset_test_assert(
            empty($invalid['valid'])
                && $invalid['assets'] === []
                && $invalid['planSha256'] === '',
            'invalid ' . $case[0] . ' declarations fail closed with no partial plan'
        );
    }

    $duplicate = red_addon_asset_plan(
        red_addon_asset_test_manifest([$publicStyle, $publicStyle]),
        'public'
    );
    red_addon_asset_test_assert(
        empty($duplicate['valid'])
            && in_array('asset_duplicate', $duplicate['errors'], true),
        'one surface cannot declare an asset path twice'
    );

    $forged = $publicPlan;
    $forged['assets'][0]['url'] = 'javascript:alert(1)';
    red_addon_asset_test_assert(
        !red_addon_asset_plan_is_valid($forged)
            && red_addon_asset_plan_html($forged, 'body-end') === '',
        'forged asset URLs cannot produce markup'
    );

    $forgedHash = $publicPlan;
    $forgedHash['planSha256'] = str_repeat('0', 64);
    red_addon_asset_test_assert(
        !red_addon_asset_plan_is_valid($forgedHash)
            && red_addon_asset_plan_html($forgedHash, 'head') === '',
        'stale or substituted plan evidence cannot produce markup'
    );

    $source = file_get_contents(
        dirname(__DIR__) . '/includes/addon_asset_helpers.php'
    );
    red_addon_asset_test_assert(
        is_string($source)
            && strpos($source, 'mysqli_') === false
            && strpos($source, 'include ') === false
            && strpos($source, 'require ') === false
            && strpos($source, 'file_get_contents') === false,
        'asset planning has no database, package execution, or filesystem-read path'
    );

    fwrite(
        STDOUT,
        'Add-on asset-plan self-test passed (' . $assertions . " assertions).\n"
    );
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
