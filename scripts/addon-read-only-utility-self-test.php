<?php
/**
 * Dependency-free checks for the read-only public utility profile.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_read_only_utility_registrar_helpers.php';

$assertions = 0;
function red_addon_read_only_utility_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$sha = str_repeat('a', 64);
$manifest = [
    'id' => 'redcms.search-fixture',
    'type' => 'cross-cutting',
    'provides' => [
        'components' => [],
        'services' => ['content.search'],
        'adminTools' => [],
        'adapters' => [],
    ],
    'permissions' => [],
    'componentEditors' => [],
    'adminToolContracts' => [],
    'adminToolActionContracts' => [],
    'adminToolFormContracts' => [],
    'settings' => [],
    'migrations' => [[
        'id' => '2026-08-22-create-search-index',
        'path' => 'migrations/2026-08-22-create-search-index.sql',
        'sha256' => $sha,
    ]],
    'routes' => [[
        'id' => 'redcms.search-fixture/query',
        'scope' => 'public',
        'path' => '/addons/redcms/search-fixture/query',
        'methods' => ['GET'],
        'authentication' => 'public',
        'csrf' => 'not-applicable',
    ]],
    'publicMutationContracts' => [],
    'jobs' => [],
    'outboundHosts' => [],
    'assets' => [
        'public' => [
            [
                'path' => 'assets/search.css',
                'location' => 'head',
                'sha256' => $sha,
            ],
            [
                'path' => 'assets/search.js',
                'location' => 'body-end',
                'sha256' => $sha,
            ],
        ],
        'admin' => [],
    ],
];

$contract = red_addon_read_only_utility_contract($manifest);
red_addon_read_only_utility_test_assert(
    !empty($contract['valid'])
        && $contract['profileId'] === 'read_only_public_utility'
        && $contract['serviceCount'] === 1
        && $contract['migrationCount'] === 1
        && $contract['routeCount'] === 1
        && $contract['publicAssetCount'] === 2
        && red_addon_valid_sha256($contract['contractSha256']),
    'the exact cross-cutting read-only surface is accepted'
);

$wrongType = $manifest;
$wrongType['type'] = 'content-package';
red_addon_read_only_utility_test_assert(
    empty(red_addon_read_only_utility_contract($wrongType)['valid']),
    'content packages cannot enter the read-only utility profile'
);

$unsafeRoute = $manifest;
$unsafeRoute['routes'][0]['methods'] = ['POST'];
$unsafeRoute['routes'][0]['csrf'] = 'required';
red_addon_read_only_utility_test_assert(
    empty(red_addon_read_only_utility_contract($unsafeRoute)['valid']),
    'unsafe public methods are refused'
);

$placeholderRoute = $manifest;
$placeholderRoute['routes'][0]['path'] =
    '/addons/redcms/search-fixture/{query}';
red_addon_read_only_utility_test_assert(
    empty(red_addon_read_only_utility_contract($placeholderRoute)['valid']),
    'placeholder routes are refused'
);

$adminAsset = $manifest;
$adminAsset['assets']['admin'][] = [
    'path' => 'assets/admin.css',
    'location' => 'head',
    'sha256' => $sha,
];
red_addon_read_only_utility_test_assert(
    empty(red_addon_read_only_utility_contract($adminAsset)['valid']),
    'administrator assets are refused'
);

$mutation = $manifest;
$mutation['publicMutationContracts'][] = ['unexpected' => true];
red_addon_read_only_utility_test_assert(
    empty(red_addon_read_only_utility_contract($mutation)['valid']),
    'public mutations are refused'
);

$registry = new RED_Addon_Runtime_Registry(
    $manifest['id'],
    $manifest
);
$registry->registerService(
    'content.search',
    static fn () => null
);
$registry->registerRoute(
    'redcms.search-fixture/query',
    static fn () => null
);
$registry->assertComplete();
$evidence = red_addon_read_only_utility_registrar_evidence(
    $registry,
    $manifest
);
red_addon_read_only_utility_test_assert(
    !empty($evidence['valid'])
        && $evidence['registrationCount'] === 2
        && red_addon_valid_sha256($evidence['registrationSha256']),
    'the exact service and route registrar shape is accepted'
);

$missing = new RED_Addon_Runtime_Registry(
    $manifest['id'],
    $manifest
);
$missing->registerService('content.search', static fn () => null);
red_addon_read_only_utility_test_assert(
    empty(red_addon_read_only_utility_registrar_evidence(
        $missing,
        $manifest
    )['valid']),
    'an incomplete registrar is refused'
);

$extraService = $manifest;
$extraService['provides']['services'][] = 'content.search.admin';
$extraRegistry = new RED_Addon_Runtime_Registry(
    $extraService['id'],
    $extraService
);
$extraRegistry->registerService('content.search', static fn () => null);
$extraRegistry->registerService('content.search.admin', static fn () => null);
$extraRegistry->registerRoute(
    'redcms.search-fixture/query',
    static fn () => null
);
$extraEvidence = red_addon_read_only_utility_registrar_evidence(
    $extraRegistry,
    $extraService
);
red_addon_read_only_utility_test_assert(
    !empty($extraEvidence['valid'])
        && $extraEvidence['registrationCount'] === 3,
    'bounded multiple declared services remain exact'
);

$source = file_get_contents(
    $projectRoot . '/includes/addon_enable_helpers.php'
);
red_addon_read_only_utility_test_assert(
    is_string($source)
        && str_contains($source, 'read_only_public_utility')
        && str_contains(
            $source,
            'red_addon_read_only_utility_registrar_evidence'
        ),
    'the atomic enablement path requires profile-specific registrar evidence'
);

$frontController = file_get_contents($projectRoot . '/index.php');
$readRoutePosition = is_string($frontController)
    ? strpos($frontController, '$redPublicReadMethod')
    : false;
$mutationPosition = is_string($frontController)
    ? strpos($frontController, '$redPublicMutationMethod')
    : false;
red_addon_read_only_utility_test_assert(
    is_int($readRoutePosition)
        && is_int($mutationPosition)
        && $readRoutePosition < $mutationPosition
        && str_contains(
            $frontController,
            'red_addon_public_route_declaration($redPublicReadPath)'
        )
        && str_contains(
            $frontController,
            "in_array('GET', \$redPublicReadContract['methods'] ?? [], true)"
        ),
    'exact public GET routes are classified before mutation dispatch'
);

printf(
    "Read-only public utility self-test passed (%d assertions).\n",
    $assertions
);

?>
