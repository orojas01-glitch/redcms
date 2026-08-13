<?php
/**
 * Dependency-free checks for the supported-server mutation endpoint bridge.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_public_mutation_endpoint_helpers.php';

$assertions = 0;

function red_addon_public_mutation_endpoint_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_endpoint_test_manifest()
{
    return [
        'id' => 'redcms.endpoint-fixture',
        'routes' => [[
            'id' => 'redcms.endpoint-fixture/cart-intent',
            'scope' => 'public',
            'path' => '/addons/redcms/endpoint-fixture/cart-intent',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => 'redcms.endpoint-fixture/cart-intent',
            'mutation' => 'redcms.endpoint-fixture/add-to-cart',
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
                'maxLength' => 64,
            ]],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => ['RED_Addon_Endpoint_Fixture_Carts'],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
    ];
}

function red_addon_public_mutation_endpoint_test_context(array $manifest)
{
    $registry = new RED_Addon_Runtime_Registry($manifest['id'], $manifest);
    $registry->registerRoute(
        $manifest['routes'][0]['id'],
        static function () {
            throw new RuntimeException('Route callback must not run.');
        }
    );
    $mutation = $manifest['publicMutationContracts'][0]['mutation'];
    $registry->registerPublicMutation(
        $mutation,
        static function () {
            throw new RuntimeException('Mutation callback must not run.');
        },
        ['RED_Addon_Endpoint_Fixture_Carts']
    );
    $registry->registerPublicMutationStateLoader(
        $mutation,
        static function () {
            throw new RuntimeException('State callback must not run.');
        }
    );
    return new RED_Addon_Runtime_Context(
        [$manifest['id']],
        [$manifest['id'] => $registry]
    );
}

function red_addon_public_mutation_endpoint_test_capture()
{
    return red_addon_public_mutation_server_request_result(
        'transport_unavailable'
    );
}

$contextKey = 'RED_ADDON_RUNTIME_CONTEXT';
$contextWasSet = array_key_exists($contextKey, $GLOBALS);
$contextBefore = $GLOBALS[$contextKey] ?? null;
$serverBefore = $_SERVER;
$environmentKeys = [
    'RED_PUBLIC_MUTATION_ENDPOINT_ENABLED',
    'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN',
    'RED_PUBLIC_MUTATION_INGRESS_PROFILE',
    'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY',
];
$environmentBefore = [];
foreach ($environmentKeys as $key) {
    $environmentBefore[$key] = getenv($key);
}

try {
    $path = '/addons/redcms/endpoint-fixture/cart-intent';
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_candidate('POST', $path)
            && red_addon_public_mutation_endpoint_candidate(
                'POST',
                $path . '?forged=1'
            )
            && red_addon_public_mutation_endpoint_candidate('GET', $path)
            && !red_addon_public_mutation_endpoint_candidate(
                'POST',
                '/not-addons/cart-intent'
            )
            && !red_addon_public_mutation_endpoint_candidate(
                'POST',
                '/addons/%65ncoded'
            ),
        'only bounded methods in the unencoded reserved namespace qualify for selection'
    );

    foreach ($environmentKeys as $key) {
        putenv($key);
    }
    red_addon_public_mutation_endpoint_test_assert(
        !red_addon_public_mutation_endpoint_enabled(),
        'the endpoint is dormant without its operator-owned deployment facts'
    );
    putenv('RED_PUBLIC_MUTATION_ENDPOINT_ENABLED=1');
    putenv('RED_PUBLIC_MUTATION_TRUSTED_ORIGIN=https://demo.example.test');
    putenv(
        'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY=' . str_repeat('a', 64)
    );
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_enabled(),
        'the default attested profile requires the exact flag, origin, and HMAC key'
    );
    putenv('RED_PUBLIC_MUTATION_INGRESS_PROFILE=direct_php');
    putenv('RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY');
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_ingress_profile() === 'direct_php'
            && red_addon_public_mutation_endpoint_enabled(),
        'the explicitly selected direct-PHP profile requires no attestation key'
    );
    $_SERVER['HTTPS'] = 'off';
    red_addon_public_mutation_endpoint_test_assert(
        !red_addon_public_mutation_endpoint_page_enabled_current(),
        'the direct-PHP profile exposes no page forms over HTTP'
    );
    $_SERVER['HTTPS'] = 'on';
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_page_enabled_current(),
        'the direct-PHP page gate accepts a server-owned HTTPS request'
    );
    putenv('RED_PUBLIC_MUTATION_INGRESS_PROFILE=invalid');
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_ingress_profile() === ''
            && !red_addon_public_mutation_endpoint_enabled(),
        'an unknown ingress profile disables the endpoint'
    );
    putenv('RED_PUBLIC_MUTATION_INGRESS_PROFILE=frankenphp_attested');
    putenv(
        'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY=' . str_repeat('a', 64)
    );
    putenv('RED_PUBLIC_MUTATION_TRUSTED_ORIGIN=http://demo.example.test');
    red_addon_public_mutation_endpoint_test_assert(
        !red_addon_public_mutation_endpoint_enabled(),
        'non-HTTPS trusted-origin drift disables the endpoint'
    );

    $manifest = red_addon_public_mutation_endpoint_test_manifest();
    $GLOBALS[$contextKey] =
        red_addon_public_mutation_endpoint_test_context($manifest);
    $disabled = red_addon_public_mutation_endpoint_dispatch(
        null,
        'POST',
        $path,
        red_addon_public_mutation_endpoint_test_capture(),
        false
    );
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_result_valid($disabled)
            && $disabled['claimed'] === true
            && $disabled['reason'] === 'endpoint_disabled'
            && $disabled['response']['httpStatus'] === 503,
        'an explicitly disabled reserved endpoint closes with the fixed response'
    );

    $unattested = red_addon_public_mutation_endpoint_dispatch(
        null,
        'POST',
        $path,
        red_addon_public_mutation_endpoint_test_capture(),
        true
    );
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_result_valid($unattested)
            && $unattested['claimed'] === true
            && $unattested['response']['httpStatus'] === 503
            && $unattested['response']['body']
                === '{"ok":false,"reason":"temporarily_unavailable"}',
        'a declared path without attested transport closes before database or callback use'
    );

    $unknown = red_addon_public_mutation_endpoint_dispatch(
        null,
        'POST',
        '/addons/redcms/unknown/cart-intent',
        red_addon_public_mutation_endpoint_test_capture(),
        true
    );
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_result_valid($unknown)
            && $unknown['claimed'] === true
            && $unknown['reason'] === 'endpoint_invalid'
            && $unknown['response']['httpStatus'] === 400,
        'an unknown reserved path receives only the generic invalid-request envelope'
    );

    $methodRefusal = red_addon_public_mutation_endpoint_dispatch(
        null,
        'GET',
        $path,
        red_addon_public_mutation_endpoint_test_capture(),
        true
    );
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_result_valid($methodRefusal)
            && $methodRefusal['claimed'] === true
            && $methodRefusal['response']['httpStatus'] === 405
            && $methodRefusal['response']['body']
                === '{"ok":false,"reason":"method_not_allowed"}',
        'a selected non-POST mutation returns the fixed method refusal'
    );
    $notClaimed = red_addon_public_mutation_endpoint_dispatch(
        null,
        'GET',
        '/addons/redcms/unknown/cart-intent',
        red_addon_public_mutation_endpoint_test_capture(),
        true
    );
    red_addon_public_mutation_endpoint_test_assert(
        red_addon_public_mutation_endpoint_result_valid($notClaimed)
            && $notClaimed ===
                red_addon_public_mutation_endpoint_result(),
        'an unknown non-POST add-on path remains available to ordinary routing'
    );

    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_endpoint_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    $endpointPosition = strpos(
        $frontController,
        'addon_public_mutation_endpoint_helpers.php'
    );
    $themePosition = strpos($frontController, "'/includes/bootstrap.php'");
    $sessionPosition = strpos($frontController, 'red_start_session()');
    red_addon_public_mutation_endpoint_test_assert(
        is_string($source)
            && is_string($frontController)
            && $endpointPosition !== false
            && $themePosition !== false
            && $sessionPosition !== false
            && $endpointPosition < $themePosition
            && $endpointPosition < $sessionPosition
            && str_contains(
                $frontController,
                'red_addon_public_mutation_endpoint_dispatch_current('
            )
            && str_contains(
                $frontController,
                "'transport_unavailable'\n            ),\n            false"
            )
            && str_contains(
                $frontController,
                'red_addon_public_mutation_endpoint_emit('
            )
            && !str_contains($source, '$_POST')
            && !str_contains($source, '$_COOKIE')
            && !str_contains($source, '$_SESSION'),
        'the core bridge refuses disabled candidates and emits before theme or session bootstrap'
    );

    echo 'Public-mutation endpoint self-test passed (' .
        $assertions . " assertions).\n";
} finally {
    if ($contextWasSet) {
        $GLOBALS[$contextKey] = $contextBefore;
    } else {
        unset($GLOBALS[$contextKey]);
    }
    foreach ($environmentBefore as $key => $value) {
        if (is_string($value)) {
            putenv($key . '=' . $value);
        } else {
            putenv($key);
        }
    }
    $_SERVER = $serverBefore;
}

?>
