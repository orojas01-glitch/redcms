<?php
/**
 * Dependency-free checks for the non-emitting public-mutation response model.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_public_mutation_response_helpers.php';

$assertions = 0;

function red_addon_public_mutation_response_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

try {
    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_response_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_response_test_assert(
        is_string($source)
            && is_string($frontController)
            && preg_match(
                '/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|header|http_response_code|setcookie|session_start|session_id|ob_start|ob_end_clean|ob_get_clean|fwrite|file_get_contents|file_put_contents)\s*\(/',
                $source
            ) !== 1
            && preg_match('/\b(?:echo|print|printf)\b/', $source) !== 1
            && strpos(
                $frontController,
                'addon_public_mutation_response_helpers.php'
            ) === false,
        'the response model has no request-global, database, session, emission, or front-controller path'
    );

    red_addon_public_mutation_response_test_assert(
        red_addon_public_mutation_response_outcome_valid('accepted')
            && red_addon_public_mutation_response_outcome_valid('unchanged')
            && !red_addon_public_mutation_response_outcome_valid('replayed')
            && !red_addon_public_mutation_response_outcome_valid('accepted '),
        'only the fixed accepted and unchanged outcome vocabulary is permitted'
    );

    $accepted = red_addon_public_mutation_response_success('accepted');
    red_addon_public_mutation_response_test_assert(
        $accepted === [
            'httpStatus' => 200,
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Length' => '32',
            ],
            'body' => '{"ok":true,"outcome":"accepted"}',
            'ok' => true,
            'outcome' => 'accepted',
            'reason' => '',
        ],
        'accepted creates one fixed no-store JSON envelope'
    );

    $unchanged = red_addon_public_mutation_response_success('unchanged');
    red_addon_public_mutation_response_test_assert(
        $unchanged['httpStatus'] === 200
            && $unchanged['body'] === '{"ok":true,"outcome":"unchanged"}'
            && ($unchanged['headers']['Content-Length'] ?? '') === '33'
            && !array_key_exists('replayed', json_decode($unchanged['body'], true)),
        'unchanged succeeds without exposing a replay signal or state value'
    );

    $invalids = [
        'invalid_request', 'subject_invalid', 'csrf_invalid',
        'idempotency_invalid', 'command_invalid', 'origin_invalid',
        'content_type_invalid', 'content_length_invalid', 'body_too_large',
        'fields_invalid',
    ];
    $invalidResponses = array_map(
        'red_addon_public_mutation_response_refusal',
        $invalids
    );
    red_addon_public_mutation_response_test_assert(
        count(array_unique(array_map('serialize', $invalidResponses))) === 1
            && $invalidResponses[0]['httpStatus'] === 400
            && $invalidResponses[0]['body']
                === '{"ok":false,"reason":"invalid_request"}',
        'request and CSRF failures collapse to one generic invalid-request response'
    );

    $method = red_addon_public_mutation_response_refusal('method_not_allowed');
    red_addon_public_mutation_response_test_assert(
        $method['httpStatus'] === 405
            && ($method['headers']['Allow'] ?? '') === 'POST'
            && $method['body']
                === '{"ok":false,"reason":"method_not_allowed"}',
        'the future static mutation path exposes only POST in a method refusal'
    );

    $conflict = red_addon_public_mutation_response_refusal('idempotency_conflict');
    red_addon_public_mutation_response_test_assert(
        $conflict['httpStatus'] === 409
            && $conflict['body']
                === '{"ok":false,"reason":"request_conflict"}',
        'a conflicting idempotency key exposes no command or state detail'
    );

    $rate = red_addon_public_mutation_response_refusal('rate_limited');
    red_addon_public_mutation_response_test_assert(
        $rate['httpStatus'] === 429
            && $rate['body'] === '{"ok":false,"reason":"rate_limited"}',
        'the fixed core rate decision has one bounded public response'
    );

    $unavailable = red_addon_public_mutation_response_refusal('runtime_unavailable');
    red_addon_public_mutation_response_test_assert(
        $unavailable['httpStatus'] === 503
            && $unavailable['body']
                === '{"ok":false,"reason":"temporarily_unavailable"}',
        'internal runner availability failures disclose no package identity or state'
    );

    $unknown = red_addon_public_mutation_response_refusal('package_not_enabled');
    red_addon_public_mutation_response_test_assert(
        $unknown === $unavailable,
        'unmapped internal failures fail closed as temporary unavailability'
    );

    red_addon_public_mutation_response_test_assert(
        red_addon_public_mutation_response_valid($accepted)
            && red_addon_public_mutation_response_valid($unchanged)
            && red_addon_public_mutation_response_valid($method)
            && red_addon_public_mutation_response_valid($conflict)
            && red_addon_public_mutation_response_valid($rate)
            && red_addon_public_mutation_response_valid($unavailable),
        'every response produced by the closed maps has an exact valid envelope'
    );

    $forgedHeader = $accepted;
    $forgedHeader['headers']['X-Injected'] = 'forbidden';
    red_addon_public_mutation_response_test_assert(
        !red_addon_public_mutation_response_valid($forgedHeader),
        'additional response headers fail validation'
    );

    $forgedBody = $rate;
    $forgedBody['body'] = '{"ok":false,"reason":"rate_limited","data":1}';
    red_addon_public_mutation_response_test_assert(
        !red_addon_public_mutation_response_valid($forgedBody),
        'value-bearing response bodies fail validation'
    );

    $completed = red_addon_public_mutation_response_from_execution([
        'completed' => true,
        'replayed' => false,
        'outcome' => 'accepted',
        'route' => 'redcms.fixture/cart',
        'mutation' => 'redcms.fixture/add',
        'reason' => 'completed',
    ]);
    red_addon_public_mutation_response_test_assert(
        $completed === $accepted,
        'a completed runner result is redacted to its fixed success outcome'
    );

    $replayed = red_addon_public_mutation_response_from_execution([
        'completed' => false,
        'replayed' => true,
        'outcome' => 'unchanged',
        'route' => 'redcms.fixture/cart',
        'mutation' => 'redcms.fixture/add',
        'reason' => 'replayed',
    ]);
    red_addon_public_mutation_response_test_assert(
        $replayed === $unchanged,
        'a replay retains only the prior bounded outcome'
    );

    $runnerRate = red_addon_public_mutation_response_from_execution([
        'completed' => false,
        'replayed' => false,
        'outcome' => '',
        'reason' => 'rate_limited',
    ]);
    red_addon_public_mutation_response_test_assert(
        $runnerRate === $rate,
        'a runner rate refusal reaches only the bounded rate response'
    );

    $malformed = red_addon_public_mutation_response_from_execution([
        'completed' => true,
        'replayed' => true,
        'outcome' => 'accepted',
        'reason' => 'completed',
    ]);
    red_addon_public_mutation_response_test_assert(
        $malformed === $unavailable
            && red_addon_public_mutation_response_build(
                201,
                true,
                'accepted',
                ''
            ) === $unavailable,
        'inconsistent runner results and forged status/outcome pairs fail closed'
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
    red_addon_public_mutation_response_from_execution([
        'completed' => false,
        'replayed' => false,
        'outcome' => '',
        'reason' => 'csrf_invalid',
    ]);
    red_addon_public_mutation_response_test_assert(
        $_SERVER === $serverBefore
            && $_GET === $getBefore
            && $_POST === $postBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && http_response_code() === $statusBefore
            && ob_get_level() === $bufferBefore
            && session_status() === $sessionBefore,
        'response construction changes no browser, output, header, or session state'
    );
} finally {
    // This dependency-free fixture owns no database, package, or browser state.
}

fwrite(
    STDOUT,
    'Public-mutation response self-test passed ('
        . $assertions . " assertions).\n"
);

?>
