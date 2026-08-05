<?php
/**
 * Dependency-free checks for the isolated public-mutation response emitter.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_response_emitter_helpers.php';

$assertions = 0;

function red_addon_public_mutation_response_emitter_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    $source = file_get_contents(
        $projectRoot
            . '/includes/addon_public_mutation_response_emitter_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_response_emitter_test_assert(
        is_string($source)
            && is_string($frontController)
            && strpos(
                $source,
                "'/addon_public_mutation_response_helpers.php'"
            ) !== false
            && preg_match(
                '/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|setcookie|session_start|session_id|getenv|'
                    . 'file_get_contents|file_put_contents|fopen|ob_start|'
                    . 'ob_end_clean|ob_get_clean|error_log)\s*\(/',
                $source
            ) !== 1
            && preg_match('/\b(?:exit|die)\b/', $source) !== 1
            && strpos(
                $frontController,
                'addon_public_mutation_response_emitter_helpers.php'
            ) === false,
        'the emitter has no request, database, package, browser, or front-controller path'
    );

    red_addon_public_mutation_response_emitter_test_assert(
        preg_match('/\bheaders_sent\s*\(\s*\)/', $source) === 1
            && preg_match('/\bheader_remove\s*\(\s*\)/', $source) === 1
            && preg_match('/\bhttp_response_code\s*\(/', $source) === 1
            && preg_match('/\bheader\s*\(/', $source) === 1
            && preg_match('/\becho\s+\$response\[\'body\'\]/', $source) === 1,
        'the emitter checks for premature output and owns the bounded HTTP emission sequence'
    );

    $accepted = red_addon_public_mutation_response_success('accepted');
    $method = red_addon_public_mutation_response_refusal('method_not_allowed');
    $unavailable = red_addon_public_mutation_response_refusal(
        'runtime_unavailable'
    );
    red_addon_public_mutation_response_emitter_test_assert(
        red_addon_public_mutation_response_emitter_valid($accepted)
            && red_addon_public_mutation_response_emitter_valid($method)
            && red_addon_public_mutation_response_emitter_valid($unavailable),
        'only closed response-contract envelopes are eligible for emission'
    );

    red_addon_public_mutation_response_emitter_test_assert(
        $accepted['headers'] === [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => '32',
        ]
            && $method['headers'] === [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Length' => '42',
                'Allow' => 'POST',
            ],
        'the emitter preserves only the exact no-store response header vocabulary'
    );

    $forgedHeader = $accepted;
    $forgedHeader['headers']['X-Injected'] = 'forbidden';
    $forgedLength = $accepted;
    $forgedLength['headers']['Content-Length'] = '31';
    $forgedStatus = $accepted;
    $forgedStatus['httpStatus'] = 201;
    red_addon_public_mutation_response_emitter_test_assert(
        !red_addon_public_mutation_response_emitter_valid($forgedHeader)
            && !red_addon_public_mutation_response_emitter_valid($forgedLength)
            && !red_addon_public_mutation_response_emitter_valid($forgedStatus),
        'injected headers, body-length drift, and unreviewed statuses fail closed'
    );

    $serverBefore = $_SERVER;
    $getBefore = $_GET;
    $postBefore = $_POST;
    $cookieBefore = $_COOKIE;
    $requestBefore = $_REQUEST;
    $sessionBefore = session_status();
    $bufferBefore = ob_get_level();
    ob_start();
    red_addon_public_mutation_response_emit($accepted);
    $acceptedBody = ob_get_clean();
    red_addon_public_mutation_response_emitter_test_assert(
        $acceptedBody === $accepted['body']
            && http_response_code() === 200
            && $_SERVER === $serverBefore
            && $_GET === $getBefore
            && $_POST === $postBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && session_status() === $sessionBefore
            && ob_get_level() === $bufferBefore,
        'an accepted envelope emits only its fixed bytes and changes no request or browser state'
    );

    ob_start();
    red_addon_public_mutation_response_emit($method);
    $methodBody = ob_get_clean();
    red_addon_public_mutation_response_emitter_test_assert(
        $methodBody === $method['body']
            && http_response_code() === 405
            && ob_get_level() === $bufferBefore,
        'a method refusal emits its fixed bounded body with the matching status'
    );

    $headersBefore = headers_list();
    $statusBefore = http_response_code();
    ob_start();
    $invalidThrown = false;
    try {
        red_addon_public_mutation_response_emit($forgedHeader);
    } catch (InvalidArgumentException $throwable) {
        $invalidThrown = true;
    }
    $invalidBody = ob_get_clean();
    red_addon_public_mutation_response_emitter_test_assert(
        $invalidThrown
            && $invalidBody === ''
            && headers_list() === $headersBefore
            && http_response_code() === $statusBefore
            && ob_get_level() === $bufferBefore,
        'an invalid envelope changes no HTTP state and emits no bytes'
    );
} finally {
    // This dependency-free fixture owns no database, package, or client state.
}

fwrite(
    STDOUT,
    'Public-mutation response emitter self-test passed ('
        . $assertions . " assertions).\n"
);

?>
