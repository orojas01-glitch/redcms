<?php
/**
 * Dependency-free checks for the optional Caddy/FrankenPHP ingress verifier.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_frankenphp_ingress_helpers.php';

$assertions = 0;

function red_addon_public_mutation_frankenphp_ingress_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_frankenphp_ingress_test_result($reason)
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

function red_addon_public_mutation_frankenphp_ingress_test_token(
    $binaryKey,
    $body,
    $headers,
    $target = '/addons/redcms/ingress-fixture/cart-intent'
) {
    $payload = [
        'v' => 1,
        'method' => 'POST',
        'target' => $target,
        'bodyBytes' => strlen($body),
        'bodySha256' => hash('sha256', $body),
        'headers' => $headers,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Could not encode test Caddy payload.');
    }
    $token = 'v1.' . rtrim(
        strtr(base64_encode($json), '+/', '-_'),
        '='
    );
    return [
        $token,
        'sha256=' . hash_hmac('sha256', $token, $binaryKey),
        $payload,
    ];
}

$originEnvironmentKey = 'RED_PUBLIC_MUTATION_TRUSTED_ORIGIN';
$keyEnvironmentKey = 'RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY';
$originalOriginEnvironment = getenv($originEnvironmentKey);
$originalKeyEnvironment = getenv($keyEnvironmentKey);
$originalServer = $_SERVER;
$exitCode = 0;

try {
    $origin = 'https://store.example.test';
    $keyHex = str_repeat('a', 64);
    putenv($originEnvironmentKey . '=' . $origin);
    putenv($keyEnvironmentKey . '=' . $keyHex);
    $binaryKey = red_addon_public_mutation_frankenphp_ingress_key();
    $subjectToken = str_repeat('b', 64);
    $csrfToken = str_repeat('c', 64);
    $idempotencyKey = str_repeat('d', 64);
    $body = 'product=SKU-42&quantity=2';
    $target = '/addons/redcms/ingress-fixture/cart-intent';
    $headers = [
        ['name' => 'Origin', 'value' => $origin],
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
    [$captureHeader, $signatureHeader, $expectedPayload]
        = red_addon_public_mutation_frankenphp_ingress_test_token(
            $binaryKey,
            $body,
            $headers,
            $target
        );

    red_addon_public_mutation_frankenphp_ingress_test_assert(
        $binaryKey === hex2bin($keyHex),
        'only the shared process environment can supply one exact binary HMAC key'
    );

    red_addon_public_mutation_frankenphp_ingress_test_assert(
        red_addon_public_mutation_frankenphp_ingress_payload(
            $captureHeader,
            $signatureHeader,
            $binaryKey
        ) === $expectedPayload,
        'one exact Caddy-signed bounded capture verifies before any body read'
    );

    $goCaptureHeader = 'v1.eyJ2IjoxLCJtZXRob2QiOiJQT1NUIiwidGFyZ2V0IjoiL2FkZG9ucy9yZWRjbXMvZml4dHVyZSIsImJvZHlCeXRlcyI6MywiYm9keVNoYTI1NiI6IjFmMjA2YjExYzIzZTI4Y2MyNTBkZWQ3ZmMwMDk4ZDM4MjNhODQ2N2E1NDM0MGYxYWM0ZTUzNWNiODU0NDQ5M2YiLCJoZWFkZXJzIjpbeyJuYW1lIjoiT3JpZ2luIiwidmFsdWUiOiJodHRwczovL3gudGVzdCJ9LHsibmFtZSI6IkNvbnRlbnQtVHlwZSIsInZhbHVlIjoidGV4dC9wbGFpbiJ9LHsibmFtZSI6IkNvb2tpZSIsInZhbHVlIjoieCJ9LHsibmFtZSI6IlgtUkVELUNNUy1DU1JGIiwidmFsdWUiOiJ4In0seyJuYW1lIjoiSWRlbXBvdGVuY3ktS2V5IiwidmFsdWUiOiJ4In1dfQ';
    $goSignatureHeader = 'sha256=443c5357f7da41db33d8d6a1d6b915800c977adab522b50cbb945143bcb0272f';
    $goExpectedPayload = [
        'v' => 1,
        'method' => 'POST',
        'target' => '/addons/redcms/fixture',
        'bodyBytes' => 3,
        'bodySha256' => '1f206b11c23e28cc250ded7fc0098d3823a8467a54340f1ac4e535cb8544493f',
        'headers' => [
            ['name' => 'Origin', 'value' => 'https://x.test'],
            ['name' => 'Content-Type', 'value' => 'text/plain'],
            ['name' => 'Cookie', 'value' => 'x'],
            [
                'name' => red_addon_public_mutation_http_request_csrf_header_name(),
                'value' => 'x',
            ],
            [
                'name' => red_addon_public_mutation_http_request_idempotency_header_name(),
                'value' => 'x',
            ],
        ],
    ];
    red_addon_public_mutation_frankenphp_ingress_test_assert(
        red_addon_public_mutation_frankenphp_ingress_payload(
            $goCaptureHeader,
            $goSignatureHeader,
            $binaryKey
        ) === $goExpectedPayload,
        'the PHP verifier accepts one fixed Go/Caddy JSON and HMAC fixture'
    );

    $captured = red_addon_public_mutation_frankenphp_ingress_capture(
        [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => $target,
        ],
        $body,
        $captureHeader,
        $signatureHeader,
        $binaryKey
    );
    red_addon_public_mutation_frankenphp_ingress_test_assert(
        $captured === [
            'available' => true,
            'trustedOrigin' => $origin,
            'method' => 'POST',
            'requestTarget' => $target,
            'headers' => $headers,
            'body' => $body,
            'reason' => 'captured',
        ],
        'verified ingress facts enter only the existing explicit server adapter'
    );

    foreach ([
        [$captureHeader, $signatureHeader . '0', $body, $target, 'transport_unavailable'],
        [$captureHeader . 'x', $signatureHeader, $body, $target, 'transport_unavailable'],
        [$captureHeader, $signatureHeader, $body . '&forged=1', $target, 'transport_invalid'],
        [$captureHeader, $signatureHeader, $body, $target . '?forged=1', 'transport_invalid'],
    ] as [$candidateCapture, $candidateSignature, $candidateBody, $candidateTarget, $reason]) {
        red_addon_public_mutation_frankenphp_ingress_test_assert(
            red_addon_public_mutation_frankenphp_ingress_capture(
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => $candidateTarget,
                ],
                $candidateBody,
                $candidateCapture,
                $candidateSignature,
                $binaryKey
            ) === red_addon_public_mutation_frankenphp_ingress_test_result(
                $reason
            ),
            'forged signature, token, body, or current target cannot release partial facts'
        );
    }

    $duplicateHeaders = $headers;
    $duplicateHeaders[1] = ['name' => 'Origin', 'value' => $origin];
    [$duplicateCapture, $duplicateSignature]
        = red_addon_public_mutation_frankenphp_ingress_test_token(
            $binaryKey,
            $body,
            $duplicateHeaders,
            $target
        );
    $forbiddenHeaders = $headers;
    $forbiddenHeaders[1] = [
        'name' => 'Content-Encoding',
        'value' => 'gzip',
    ];
    [$forbiddenCapture, $forbiddenSignature]
        = red_addon_public_mutation_frankenphp_ingress_test_token(
            $binaryKey,
            $body,
            $forbiddenHeaders,
            $target
        );
    red_addon_public_mutation_frankenphp_ingress_test_assert(
        red_addon_public_mutation_frankenphp_ingress_payload(
            $duplicateCapture,
            $duplicateSignature,
            $binaryKey
        ) === null
            && red_addon_public_mutation_frankenphp_ingress_payload(
                $forbiddenCapture,
                $forbiddenSignature,
                $binaryKey
            ) === null,
        'even a correctly signed malformed payload cannot introduce duplicate or forbidden header evidence'
    );

    putenv($keyEnvironmentKey . '=' . str_repeat('A', 64));
    red_addon_public_mutation_frankenphp_ingress_test_assert(
        red_addon_public_mutation_frankenphp_ingress_key() === '',
        'uppercase, short, or request-projected values cannot become the ingress HMAC key'
    );
    putenv($keyEnvironmentKey . '=' . $keyHex);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = $target;
    $_SERVER['HTTP_X_RED_PUBLIC_MUTATION_CAPTURE'] = $captureHeader;
    $_SERVER['HTTP_X_RED_PUBLIC_MUTATION_SIGNATURE'] = $signatureHeader . '0';
    $serverBefore = $_SERVER;
    $getBefore = $_GET;
    $postBefore = $_POST;
    $cookieBefore = $_COOKIE;
    $requestBefore = $_REQUEST;
    $headersBefore = headers_list();
    $statusBefore = http_response_code();
    $bufferBefore = ob_get_level();
    $sessionBefore = session_status();
    red_addon_public_mutation_frankenphp_ingress_test_assert(
        red_addon_public_mutation_frankenphp_ingress_capture_current()
            === red_addon_public_mutation_frankenphp_ingress_test_result(
                'transport_unavailable'
            )
            && $_SERVER === $serverBefore
            && $_GET === $getBefore
            && $_POST === $postBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && http_response_code() === $statusBefore
            && ob_get_level() === $bufferBefore
            && session_status() === $sessionBefore,
        'an invalid current capture returns before body access and changes no request, response, session, or buffer state'
    );

    $source = file_get_contents(
        $projectRoot
            . '/includes/addon_public_mutation_frankenphp_ingress_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_frankenphp_ingress_test_assert(
        is_string($source)
            && is_string($frontController)
            && str_contains(
                $source,
                "getenv('RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY')"
            )
            && strpos($source, 'red_server_config_value(') === false
            && preg_match(
                '/\$_(?:GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|header|http_response_code|setcookie|session_start|'
                    . 'session_id|file_put_contents|fopen|ob_start|ob_end_clean|'
                    . 'ob_get_clean|error_log)\s*\(/',
                $source
            ) !== 1
            && substr_count($source, "file_get_contents('php://input')") === 1
            && strpos($source, 'addon.php') === false
            && strpos(
                $frontController,
                'addon_public_mutation_frankenphp_ingress_helpers.php'
            ) === false,
        'the unlinked bridge has no parsed request, database, emission, package, or front-controller path'
    );

    fwrite(
        STDOUT,
        'Public-mutation FrankenPHP ingress self-test passed ('
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
if ($originalKeyEnvironment === false) {
    putenv($keyEnvironmentKey);
} else {
    putenv($keyEnvironmentKey . '=' . $originalKeyEnvironment);
}
$_SERVER = $originalServer;
exit($exitCode);

?>
