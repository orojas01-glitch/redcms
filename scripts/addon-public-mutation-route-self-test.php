<?php
/**
 * Dependency-free checks for static public-mutation route selection.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/addon_public_mutation_route_helpers.php';

$assertions = 0;

function red_addon_public_mutation_route_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_route_test_manifest($id, $path)
{
    $routeId = $id . '/cart-intent';
    $mutationId = $id . '/add-to-cart';
    return [
        'id' => $id,
        'routes' => [[
            'id' => $routeId,
            'scope' => 'public',
            'path' => $path,
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => $routeId,
            'mutation' => $mutationId,
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 128,
            'requestFields' => [[
                'key' => 'product',
                'type' => 'identifier',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 120,
            ]],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => ['RED_Addon_Route_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_public_mutation_route_test_context(
    array $manifests,
    array $missingBindings = [],
    array &$invocations = []
) {
    $order = [];
    $registries = [];
    foreach ($manifests as $manifest) {
        $packageId = $manifest['id'];
        $registry = new RED_Addon_Runtime_Registry($packageId, $manifest);
        foreach ($manifest['routes'] as $route) {
            $routeId = $route['id'];
            if (in_array('route:' . $packageId, $missingBindings, true)) {
                continue;
            }
            $registry->registerRoute(
                $routeId,
                static function () use (&$invocations, $packageId) {
                    $invocations['route:' . $packageId] =
                        ($invocations['route:' . $packageId] ?? 0) + 1;
                }
            );
        }
        foreach ($manifest['publicMutationContracts'] as $contract) {
            $mutationId = $contract['mutation'];
            if (!in_array('mutation:' . $packageId, $missingBindings, true)) {
            $registry->registerPublicMutation(
                $mutationId,
                static function () use (&$invocations, $packageId) {
                    $invocations['mutation:' . $packageId] =
                        ($invocations['mutation:' . $packageId] ?? 0) + 1;
                },
                ['RED_Addon_Route_Fixture_Carts']
            );
            }
            if (in_array('state-loader:' . $packageId, $missingBindings, true)) {
                continue;
            }
            $registry->registerPublicMutationStateLoader(
                $mutationId,
                static function () use (&$invocations, $packageId) {
                    $invocations['state-loader:' . $packageId] =
                        ($invocations['state-loader:' . $packageId] ?? 0) + 1;
                    return [];
                }
            );
        }
        $order[] = $packageId;
        $registries[$packageId] = $registry;
    }
    return new RED_Addon_Runtime_Context($order, $registries);
}

function red_addon_public_mutation_route_test_result(
    $claimed,
    $ready,
    $reason
) {
    return [
        'claimed' => $claimed,
        'ready' => $ready,
        'packageId' => '',
        'route' => '',
        'mutation' => '',
        'path' => '',
        'reason' => $reason,
    ];
}

$contextKey = 'RED_ADDON_RUNTIME_CONTEXT';
$contextWasSet = array_key_exists($contextKey, $GLOBALS);
$contextBefore = $GLOBALS[$contextKey] ?? null;

try {
    $packageId = 'redcms.route-fixture';
    $path = '/addons/redcms/route-fixture/cart-intent';
    $routeId = $packageId . '/cart-intent';
    $mutationId = $packageId . '/add-to-cart';
    $manifest = red_addon_public_mutation_route_test_manifest($packageId, $path);

    red_addon_public_mutation_route_test_assert(
        red_addon_public_mutation_route_selection_valid(
            red_addon_public_mutation_route_selection_result('not_matched')
        )
            && !red_addon_public_mutation_route_selection_valid([
                'claimed' => false,
                'ready' => true,
                'packageId' => '',
                'route' => '',
                'mutation' => '',
                'path' => '',
                'reason' => 'route_selected',
            ]),
        'the selector exposes one closed private result shape'
    );
    red_addon_public_mutation_route_test_assert(
        red_addon_public_mutation_route_target_path($path) === $path
            && red_addon_public_mutation_route_target_path($path . '?retry=1')
                === $path,
        'only the raw un-decoded path portion selects a static declaration'
    );
    foreach ([
        '',
        'https://store.example.test' . $path,
        $path . '%2F',
        $path . '#fragment',
        $path . "\n",
    ] as $invalidTarget) {
        red_addon_public_mutation_route_test_assert(
            red_addon_public_mutation_route_target_path($invalidTarget) === '',
            'noncanonical targets cannot participate in route selection'
        );
    }

    unset($GLOBALS[$contextKey]);
    red_addon_public_mutation_route_test_assert(
        red_addon_public_mutation_route_select($path)
            === red_addon_public_mutation_route_test_result(
                false,
                false,
                'runtime_unavailable'
            ),
        'a request without one initialized runtime context cannot be selected'
    );

    $invocations = [];
    $GLOBALS[$contextKey] = red_addon_public_mutation_route_test_context(
        [$manifest],
        [],
        $invocations
    );
    $selected = red_addon_public_mutation_route_select($path);
    red_addon_public_mutation_route_test_assert(
        $selected === [
            'claimed' => true,
            'ready' => true,
            'packageId' => $packageId,
            'route' => $routeId,
            'mutation' => $mutationId,
            'path' => $path,
            'reason' => 'route_selected',
        ]
            && red_addon_public_mutation_route_selection_valid($selected),
        'one exact declared and registrar-bound mutation route is selected'
    );
    red_addon_public_mutation_route_test_assert(
        red_addon_public_mutation_route_select($path . '?retry=1') === $selected,
        'the selector reserves a known path even when later envelope validation must refuse the target'
    );
    red_addon_public_mutation_route_test_assert(
        $invocations === [],
        'selection does not invoke route, mutation, or state-loader callbacks'
    );

    red_addon_public_mutation_route_test_assert(
        red_addon_public_mutation_route_select(
            '/addons/redcms/route-fixture/other'
        ) === red_addon_public_mutation_route_test_result(
            false,
            false,
            'not_matched'
        ),
        'only one exact static declaration path is selected'
    );
    foreach ([$path . '/', $path . '%2F'] as $noncanonicalPath) {
        red_addon_public_mutation_route_test_assert(
            red_addon_public_mutation_route_select($noncanonicalPath)
                === red_addon_public_mutation_route_test_result(
                    false,
                    false,
                    'request_invalid'
                ),
            'trailing or encoded static paths are refused before manifest selection'
        );
    }

    foreach (['route', 'mutation', 'state-loader'] as $missingBinding) {
        $invocations = [];
        $GLOBALS[$contextKey] = red_addon_public_mutation_route_test_context(
            [$manifest],
            [$missingBinding . ':' . $packageId],
            $invocations
        );
        red_addon_public_mutation_route_test_assert(
            red_addon_public_mutation_route_select($path)
                === red_addon_public_mutation_route_test_result(
                    true,
                    false,
                    'route_unavailable'
                )
                && $invocations === [],
            'every route, mutation, and state-loader binding is mandatory before selection is ready'
        );
    }

    $ambiguousManifest = $manifest;
    $ambiguousRouteId = $packageId . '/cart-intent-second';
    $ambiguousMutationId = $packageId . '/add-to-cart-second';
    $ambiguousManifest['routes'][] = [
        'id' => $ambiguousRouteId,
        'scope' => 'public',
        'path' => $path,
        'methods' => ['POST'],
        'authentication' => 'public',
        'csrf' => 'required',
    ];
    $ambiguousContract = $manifest['publicMutationContracts'][0];
    $ambiguousContract['route'] = $ambiguousRouteId;
    $ambiguousContract['mutation'] = $ambiguousMutationId;
    $ambiguousManifest['publicMutationContracts'][] = $ambiguousContract;
    $invocations = [];
    $GLOBALS[$contextKey] = red_addon_public_mutation_route_test_context(
        [$ambiguousManifest],
        [],
        $invocations
    );
    red_addon_public_mutation_route_test_assert(
        red_addon_public_mutation_route_select($path)
            === red_addon_public_mutation_route_test_result(
                true,
                false,
                'route_ambiguous'
            )
            && $invocations === [],
        'ambiguous static mutation paths are claimed and refused without leaking an owner'
    );

    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_route_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_route_test_assert(
        is_string($source)
            && is_string($frontController)
            && preg_match(
                '/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|header|http_response_code|setcookie|session_start|session_id|ob_start|ob_end_clean|ob_get_clean|file_get_contents|file_put_contents)\s*\(/',
                $source
            ) !== 1
            && strpos($source, 'php://') === false
            && strpos(
                $frontController,
                'addon_public_mutation_route_helpers.php'
            ) === false,
        'the selector has no request-global, database, execution, emission, filesystem, or front-controller path'
    );

    fwrite(
        STDOUT,
        'Public-mutation route selector self-test passed ('
            . $assertions . " assertions).\n"
    );
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
} finally {
    if ($contextWasSet) {
        $GLOBALS[$contextKey] = $contextBefore;
    } else {
        unset($GLOBALS[$contextKey]);
    }
}

?>
