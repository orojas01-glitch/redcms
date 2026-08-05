<?php
/**
 * Dependency-free checks for the non-routable public-mutation server adapter.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_server_request_helpers.php';

$assertions = 0;

function red_addon_public_mutation_server_request_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_server_request_test_result($reason)
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

function red_addon_public_mutation_server_request_test_headers(
    $subjectToken,
    $csrfToken,
    $idempotencyKey
) {
    return [
        ['name' => 'Origin', 'value' => 'https://store.example.test'],
        [
            'name' => 'Content-Type',
            'value' => 'application/x-www-form-urlencoded',
        ],
        [
            'name' => 'Cookie',
            'value' => red_addon_public_mutation_subject_cookie_name()
                . '=' . $subjectToken,
        ],
        [
            'name' => red_addon_public_mutation_http_request_csrf_header_name(),
            'value' => $csrfToken,
        ],
        [
            'name' => red_addon_public_mutation_http_request_idempotency_header_name(),
            'value' => $idempotencyKey,
        ],
    ];
}

$originEnvironmentKey = 'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN';
$originalOriginEnvironment = getenv($originEnvironmentKey);
$originalServer = $_SERVER;
$exitCode = 0;

try {
    $origin = 'https://store.example.test';
    putenv($originEnvironmentKey . '=' . $origin);
    $_SERVER['HTTP_HOST'] = 'attacker.example.test';
    $_SERVER[$originEnvironmentKey] = 'https://attacker.example.test';

    $subjectToken = str_repeat('a', 64);
    $csrfToken = str_repeat('b', 64);
    $idempotencyKey = str_repeat('c', 64);
    $body = 'product=SKU-42&quantity=2';
    $target = '/addons/redcms/server-request-fixture/cart-intent?retry=1';
    $headers = red_addon_public_mutation_server_request_test_headers(
        $subjectToken,
        $csrfToken,
        $idempotencyKey
    );
    $headerCapture = [
        'complete' => true,
        'headers' => $headers,
    ];

    red_addon_public_mutation_server_request_test_assert(
        red_server_config_value(
            'PUBLIC_MUTATION_TRUSTED_ORIGIN',
            [$originEnvironmentKey],
            ''
        ) === $origin
            && red_addon_public_mutation_server_trusted_origin() === $origin,
        'the future origin comes only from operating-system or local configuration, not Host or a server request value'
    );

    red_addon_public_mutation_server_request_test_assert(
        red_addon_public_mutation_server_request_headers($headerCapture)
            === $headers,
        'an upstream-attested complete header-line list remains ordered and unmodified'
    );

    $captured = red_addon_public_mutation_server_request_capture(
        'POST',
        $target,
        $headerCapture,
        $body
    );
    red_addon_public_mutation_server_request_test_assert(
        $captured === [
            'available' => true,
            'trustedOrigin' => $origin,
            'method' => 'POST',
            'requestTarget' => $target,
            'headers' => $headers,
            'body' => $body,
            'reason' => 'captured',
        ],
        'the adapter captures only bounded raw transport facts for a later envelope normalizer'
    );

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = $target;
    red_addon_public_mutation_server_request_test_assert(
        red_addon_public_mutation_server_request_capture_current(
            $headerCapture,
            $body
        ) === $captured,
        'the current-server bridge reads only method and raw target while retaining the upstream line capture'
    );

    foreach ([
        [],
        ['complete' => false, 'headers' => $headers],
        ['headers' => $headers, 'complete' => true],
        ['complete' => true, 'headers' => ['Origin' => $origin]],
    ] as $incompleteCapture) {
        red_addon_public_mutation_server_request_test_assert(
            red_addon_public_mutation_server_request_capture(
                'POST',
                $target,
                $incompleteCapture,
                $body
            ) === red_addon_public_mutation_server_request_test_result(
                'transport_unavailable'
            ),
            'missing, ambiguous, reordered, or associative header capture cannot enter the adapter'
        );
    }

    $duplicateOrigin = $headers;
    $duplicateOrigin[] = ['name' => 'Origin', 'value' => $origin];
    red_addon_public_mutation_server_request_test_assert(
        red_addon_public_mutation_server_request_capture(
            'POST',
            $target,
            ['complete' => true, 'headers' => $duplicateOrigin],
            $body
        ) === red_addon_public_mutation_server_request_test_result(
            'transport_unavailable'
        ),
        'a complete capture with duplicate critical header evidence fails before transport facts are released'
    );

    foreach ([
        ['', $target, $body],
        ['POST', 'https://store.example.test/forged', $body],
        ['POST', '/forged#fragment', $body],
        ['POST', $target, str_repeat('x', 8193)],
    ] as [$method, $requestTarget, $candidateBody]) {
        red_addon_public_mutation_server_request_test_assert(
            red_addon_public_mutation_server_request_capture(
                $method,
                $requestTarget,
                $headerCapture,
                $candidateBody
            ) === red_addon_public_mutation_server_request_test_result(
                'transport_invalid'
            ),
            'malformed method, target, or globally oversized body returns no partial transport evidence'
        );
    }

    putenv($originEnvironmentKey . '=http://store.example.test');
    red_addon_public_mutation_server_request_test_assert(
        red_addon_public_mutation_server_request_capture(
            'POST',
            $target,
            $headerCapture,
            $body
        ) === red_addon_public_mutation_server_request_test_result(
            'origin_unavailable'
        ),
        'an absent or noncanonical server origin configuration prevents every transport capture'
    );
    putenv($originEnvironmentKey . '=' . $origin);

    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_server_request_helpers.php'
    );
    $runtimeConfigSource = file_get_contents(
        $projectRoot . '/includes/runtime_config_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    $serverConfigStart = is_string($runtimeConfigSource)
        ? strpos($runtimeConfigSource, 'function red_server_config_value')
        : false;
    $serverConfigSource = $serverConfigStart === false
        ? ''
        : substr($runtimeConfigSource, $serverConfigStart);
    red_addon_public_mutation_server_request_test_assert(
        is_string($source)
            && is_string($runtimeConfigSource)
            && is_string($frontController)
            && strpos($source, 'getallheaders') === false
            && preg_match(
                '/\$_(?:GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|header|http_response_code|setcookie|session_start|session_id|ob_start|ob_end_clean|ob_get_clean|file_get_contents|file_put_contents)\s*\(/',
                $source
            ) !== 1
            && strpos($source, 'addon.php') === false
            && preg_match('/\$_(?:ENV|SERVER)\s*\[/', $serverConfigSource)
                !== 1
            && strpos(
                $frontController,
                'addon_public_mutation_server_request_helpers.php'
            ) === false,
        'the adapter has no associative-header fallback, parsed request data, emission, package, database, or front-controller path'
    );

    $serverBefore = $_SERVER;
    $getBefore = $_GET;
    $postBefore = $_POST;
    $cookieBefore = $_COOKIE;
    $requestBefore = $_REQUEST;
    $headersBefore = headers_list();
    $statusBefore = http_response_code();
    $bufferBefore = ob_get_level();
    $sessionBefore = session_status();
    red_addon_public_mutation_server_request_capture_current(
        $headerCapture,
        $body
    );
    red_addon_public_mutation_server_request_test_assert(
        $_SERVER === $serverBefore
            && $_GET === $getBefore
            && $_POST === $postBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && http_response_code() === $statusBefore
            && ob_get_level() === $bufferBefore
            && session_status() === $sessionBefore,
        'capturing transport facts changes no request global, session, buffer, or response state'
    );

    fwrite(
        STDOUT,
        'Public-mutation server request self-test passed ('
            . $assertions . " assertions).\n"
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    $exitCode = 1;
}

if ($originalOriginEnvironment === false) {
    putenv($originEnvironmentKey);
} else {
    putenv($originEnvironmentKey . '=' . $originalOriginEnvironment);
}
$_SERVER = $originalServer;
exit($exitCode);

?>
