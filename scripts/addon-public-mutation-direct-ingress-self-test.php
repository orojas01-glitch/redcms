<?php
/**
 * Dependency-free checks for shared-host direct-PHP mutation ingress.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_direct_ingress_helpers.php';

$assertions = 0;

function red_addon_public_mutation_direct_ingress_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_direct_ingress_test_server(
    $body,
    $origin = 'https://store.example.test'
) {
    return [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/addons/redcms/direct-fixture/cart-intent',
        'HTTPS' => 'on',
        'HTTP_HOST' => 'attacker.example.test',
        'HTTP_X_FORWARDED_PROTO' => 'http',
        'HTTP_ORIGIN' => $origin,
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'CONTENT_LENGTH' => (string) strlen($body),
        'HTTP_COOKIE' => red_addon_public_mutation_subject_cookie_name()
            . '=' . str_repeat('a', 64),
        'HTTP_X_RED_CMS_CSRF' => str_repeat('b', 64),
        'HTTP_IDEMPOTENCY_KEY' => str_repeat('c', 64),
    ];
}

$originKey = 'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN';
$originBefore = getenv($originKey);
$serverBefore = $_SERVER;
$getBefore = $_GET;
$postBefore = $_POST;
$cookieBefore = $_COOKIE;
$requestBefore = $_REQUEST;
$exitCode = 0;

try {
    $origin = 'https://store.example.test';
    $body = 'product=SKU-42&quantity=2';
    putenv($originKey . '=' . $origin);
    $server = red_addon_public_mutation_direct_ingress_test_server($body);

    red_addon_public_mutation_direct_ingress_test_assert(
        red_addon_public_mutation_direct_ingress_https($server)
            && !red_addon_public_mutation_direct_ingress_https(
                array_replace($server, ['HTTPS' => 'off'])
            )
            && !red_addon_public_mutation_direct_ingress_https(
                array_replace($server, [
                    'HTTPS' => '',
                    'HTTP_X_FORWARDED_PROTO' => 'https',
                ])
            ),
        'only the direct server-owned HTTPS fact is accepted'
    );

    $headers =
        red_addon_public_mutation_direct_ingress_projected_headers($server);
    red_addon_public_mutation_direct_ingress_test_assert(
        is_array($headers)
            && array_column($headers, 'name') === [
                'Origin',
                'Content-Type',
                'Content-Length',
                'Cookie',
                'X-RED-CMS-CSRF',
                'Idempotency-Key',
            ]
            && !str_contains(json_encode($headers), 'attacker.example.test')
            && !str_contains(json_encode($headers), 'X-Forwarded'),
        'the fixed projection excludes Host and every forwarding value'
    );

    $captured = red_addon_public_mutation_direct_ingress_capture(
        $server,
        $body
    );
    red_addon_public_mutation_direct_ingress_test_assert(
        $captured === [
            'available' => true,
            'trustedOrigin' => $origin,
            'method' => 'POST',
            'requestTarget' => $server['REQUEST_URI'],
            'headers' => $headers,
            'body' => $body,
            'reason' => 'captured',
        ],
        'a bounded direct HTTPS request becomes the existing explicit capture'
    );

    foreach ([
        ['HTTP_ORIGIN', 'https://attacker.example.test'],
        ['CONTENT_TYPE', 'application/json'],
        ['CONTENT_LENGTH', '0001'],
        ['CONTENT_LENGTH', '8193'],
        ['HTTP_COOKIE', red_addon_public_mutation_subject_cookie_name()
            . '=' . str_repeat('a', 64) . '; '
            . red_addon_public_mutation_subject_cookie_name() . '='
            . str_repeat('a', 64)],
        ['HTTP_X_RED_CMS_CSRF', str_repeat('b', 64) . ', forged'],
        ['HTTP_IDEMPOTENCY_KEY', str_repeat('c', 64) . ',forged'],
    ] as [$key, $value]) {
        $candidate = array_replace($server, [$key => $value]);
        red_addon_public_mutation_direct_ingress_test_assert(
            red_addon_public_mutation_direct_ingress_preflight($candidate)
                === null,
            'ambiguous or malformed projected security value ' . $key
                . ' fails before body I/O'
        );
    }

    foreach ([
        'HTTP_CONTENT_TYPE',
        'HTTP_CONTENT_LENGTH',
        'HTTP_TRANSFER_ENCODING',
        'TRANSFER_ENCODING',
        'HTTP_CONTENT_ENCODING',
        'CONTENT_ENCODING',
    ] as $forbiddenKey) {
        $candidate = array_replace($server, [$forbiddenKey => 'forged']);
        red_addon_public_mutation_direct_ingress_test_assert(
            red_addon_public_mutation_direct_ingress_preflight($candidate)
                === null,
            'alternate or encoded transport projections are rejected'
        );
    }

    foreach ([
        array_replace($server, ['REQUEST_METHOD' => 'GET']),
        array_replace($server, ['REQUEST_URI' => '/not-addons/cart-intent']),
        array_replace($server, ['HTTPS' => 'off']),
    ] as $candidate) {
        red_addon_public_mutation_direct_ingress_test_assert(
            red_addon_public_mutation_direct_ingress_capture($candidate, $body)
                === red_addon_public_mutation_direct_ingress_result(
                    'transport_unavailable'
                ),
            'non-POST, foreign-path, and non-HTTPS requests release no facts'
        );
    }

    red_addon_public_mutation_direct_ingress_test_assert(
        red_addon_public_mutation_direct_ingress_capture(
            $server,
            $body . 'x'
        ) === red_addon_public_mutation_direct_ingress_result(
            'transport_invalid'
        ),
        'declared and actual body length drift fails closed'
    );

    $source = file_get_contents(
        $projectRoot
            . '/includes/addon_public_mutation_direct_ingress_helpers.php'
    );
    red_addon_public_mutation_direct_ingress_test_assert(
        is_string($source)
            && !str_contains($source, 'getallheaders')
            && !str_contains($source, 'apache_request_headers')
            && preg_match('/\$_(?:GET|POST|COOKIE|SESSION|REQUEST)\b/', $source)
                !== 1
            && !str_contains($source, 'HTTP_HOST')
            && !str_contains($source, 'HTTP_X_FORWARDED')
            && !str_contains($source, 'addon.php')
            && !preg_match(
                '/\b(?:mysqli|header|http_response_code|setcookie|session_start)\s*\(/',
                $source
            ),
        'the adapter has no parsed-field, Host, forwarding, package, database, session, or response path'
    );

    $_SERVER = $server;
    $headersBefore = headers_list();
    $statusBefore = http_response_code();
    $bufferBefore = ob_get_level();
    $sessionBefore = session_status();
    red_addon_public_mutation_direct_ingress_preflight($_SERVER);
    red_addon_public_mutation_direct_ingress_test_assert(
        $_SERVER === $server
            && $_GET === $getBefore
            && $_POST === $postBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && http_response_code() === $statusBefore
            && ob_get_level() === $bufferBefore
            && session_status() === $sessionBefore,
        'preflight changes no request, session, buffer, or response state'
    );

    fwrite(
        STDOUT,
        'Public-mutation direct ingress self-test passed ('
            . $assertions . " assertions).\n"
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    $exitCode = 1;
}

if ($originBefore === false) {
    putenv($originKey);
} else {
    putenv($originKey . '=' . $originBefore);
}
$_SERVER = $serverBefore;
exit($exitCode);

?>
