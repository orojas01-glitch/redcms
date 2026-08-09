<?php
/**
 * Dependency-free checks for the unlinked public-mutation dispatcher.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_dispatch_helpers.php';

$assertions = 0;

function red_addon_public_mutation_dispatch_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_dispatch_test_manifest($id, $path)
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
            'tables' => ['RED_Addon_Dispatch_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_public_mutation_dispatch_test_context(
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
            if (in_array('route:' . $packageId, $missingBindings, true)) {
                continue;
            }
            $registry->registerRoute(
                $route['id'],
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
                    ['RED_Addon_Dispatch_Fixture_Carts']
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

function red_addon_public_mutation_dispatch_test_capture($reason)
{
    return [
        'available' => false,
        'trustedOrigin' => '',
        'method' => '',
        'requestTarget' => '',
        'headers' => [],
        'body' => '',
        'reason' => $reason,
    ];
}

function red_addon_public_mutation_dispatch_test_response($result, $status)
{
    return is_array($result)
        && ($result['claimed'] ?? null) === true
        && is_array($result['response'] ?? null)
        && $result['response']['httpStatus'] === $status
        && red_addon_public_mutation_response_valid($result['response']);
}

$contextKey = 'RED_ADDON_RUNTIME_CONTEXT';
$contextWasSet = array_key_exists($contextKey, $GLOBALS);
$contextBefore = $GLOBALS[$contextKey] ?? null;

try {
    $packageId = 'redcms.dispatch-fixture';
    $path = '/addons/redcms/dispatch-fixture/cart-intent';
    $manifest = red_addon_public_mutation_dispatch_test_manifest(
        $packageId,
        $path
    );
    $method = 'POST';
    $capture = red_addon_public_mutation_dispatch_test_capture(
        'transport_unavailable'
    );

    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_dispatch_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_dispatch_test_assert(
        is_string($source)
            && is_string($frontController)
            && preg_match('/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/', $source)
                !== 1
            && preg_match(
                '/\b(?:mysqli_connect|mysqli_query|header|http_response_code|'
                    . 'setcookie|session_start|session_id|file_get_contents|'
                    . 'ob_start|ob_end_clean|ob_get_clean|echo|exit|die)\s*\(/',
                $source
            ) !== 1
            && strpos(
                $frontController,
                'addon_public_mutation_dispatch_helpers.php'
            ) === false,
        'the dispatcher remains explicit-input, non-emitting, and unlinked'
    );

    red_addon_public_mutation_dispatch_test_assert(
        red_addon_public_mutation_dispatch_capture_valid($capture)
            && red_addon_public_mutation_dispatch_result('not_matched') === [
                'claimed' => false,
                'response' => null,
                'reason' => 'not_matched',
            ],
        'the dispatcher exposes one closed result and capture shape'
    );

    unset($GLOBALS[$contextKey]);
    $notSelected = red_addon_public_mutation_dispatch(
        null,
        $method,
        $path,
        $capture
    );
    red_addon_public_mutation_dispatch_test_assert(
        $notSelected === [
            'claimed' => false,
            'response' => null,
            'reason' => 'runtime_unavailable',
        ],
        'a request without a trusted runtime context remains unclaimed'
    );

    $invocations = [];
    $GLOBALS[$contextKey] = red_addon_public_mutation_dispatch_test_context(
        [$manifest],
        [],
        $invocations
    );
    $methodRefusal = red_addon_public_mutation_dispatch(
        null,
        'GET',
        $path,
        $capture
    );
    red_addon_public_mutation_dispatch_test_assert(
        red_addon_public_mutation_dispatch_test_response(
            $methodRefusal,
            405
        )
            && $methodRefusal['response']['body']
                === '{"ok":false,"reason":"method_not_allowed"}'
            && $invocations === [],
        'a selected mutation path refuses non-POST methods before package callbacks or database access'
    );

    $transportRefusal = red_addon_public_mutation_dispatch(
        null,
        $method,
        $path,
        $capture
    );
    red_addon_public_mutation_dispatch_test_assert(
        red_addon_public_mutation_dispatch_test_response(
            $transportRefusal,
            503
        )
            && $transportRefusal['response']['body']
                === '{"ok":false,"reason":"temporarily_unavailable"}'
            && $invocations === [],
        'a POST mutation path refuses without attested transport before package callbacks or database access'
    );

    $missingBindings = [];
    $GLOBALS[$contextKey] = red_addon_public_mutation_dispatch_test_context(
        [$manifest],
        ['mutation:' . $packageId],
        $missingBindings
    );
    $unavailable = red_addon_public_mutation_dispatch(
        null,
        'GET',
        $path,
        $capture
    );
    red_addon_public_mutation_dispatch_test_assert(
        red_addon_public_mutation_dispatch_test_response($unavailable, 503)
            && $missingBindings === [],
        'an incomplete registrar binding is claimed and refused without leaking an owner or invoking a callback'
    );
} finally {
    if ($contextWasSet) {
        $GLOBALS[$contextKey] = $contextBefore;
    } else {
        unset($GLOBALS[$contextKey]);
    }
}

fwrite(
    STDOUT,
    'Public-mutation dispatch self-test passed ('
        . $assertions . " assertions).\n"
);

?>
