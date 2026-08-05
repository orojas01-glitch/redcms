<?php
/**
 * Dependency-free checks for subject-cookie serialization only.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot
    . '/includes/addon_public_mutation_subject_cookie_helpers.php';

$assertions = 0;

function red_addon_public_mutation_subject_cookie_test_assert(
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
            . '/includes/addon_public_mutation_subject_cookie_helpers.php'
    );
    $frontController = file_get_contents($projectRoot . '/index.php');
    red_addon_public_mutation_subject_cookie_test_assert(
        is_string($source)
            && is_string($frontController)
            && strpos(
                $source,
                "'/addon_public_mutation_subject_helpers.php'"
            ) !== false
            && preg_match(
                '/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/',
                $source
            ) !== 1
            && preg_match(
                '/\b(?:mysqli|header|setcookie|session_start|session_id|'
                    . 'getenv|file_get_contents|file_put_contents|fopen|'
                    . 'ob_start|ob_end_clean|ob_get_clean|error_log)\s*\(/',
                $source
            ) !== 1
            && preg_match('/\b(?:echo|print|printf|exit|die)\b/', $source)
                !== 1
            && strpos(
                $frontController,
                'addon_public_mutation_subject_cookie_helpers.php'
            ) === false,
        'the serializer has no request, database, emission, package, or front-controller path'
    );

    $token = str_repeat('a', 64);
    $issuedSubject = [
        'valid' => true,
        'issued' => true,
        'subjectRecordId' => 1,
        'cookie' => [
            'name' => 'redcms_public_mutation_subject',
            'path' => '/',
            'secure' => true,
            'httpOnly' => true,
            'sameSite' => 'Strict',
            'maxAgeSeconds' => 1800,
            'value' => $token,
        ],
        'reason' => 'subject_issued',
    ];
    red_addon_public_mutation_subject_cookie_test_assert(
        red_addon_public_mutation_subject_cookie_issued_valid($issuedSubject),
        'only the exact core-issued subject descriptor shape is serializable'
    );

    $serialized = red_addon_public_mutation_subject_cookie_serialize(
        $issuedSubject
    );
    $expectedValue = 'redcms_public_mutation_subject=' . $token
        . '; Max-Age=1800; Path=/; Secure; HttpOnly; SameSite=Strict';
    red_addon_public_mutation_subject_cookie_test_assert(
        $serialized === [
            'valid' => true,
            'setCookieValue' => $expectedValue,
            'reason' => 'subject_cookie_serialized',
        ]
            && red_addon_public_mutation_subject_cookie_serialization_valid(
                $serialized
            ),
        'the exact issued descriptor becomes one fixed host-only cookie value'
    );

    red_addon_public_mutation_subject_cookie_test_assert(
        !str_contains($serialized['setCookieValue'], 'Domain=')
            && !str_contains($serialized['setCookieValue'], 'Expires=')
            && str_contains($serialized['setCookieValue'], 'Path=/')
            && str_contains($serialized['setCookieValue'], 'Secure')
            && str_contains($serialized['setCookieValue'], 'HttpOnly')
            && str_contains($serialized['setCookieValue'], 'SameSite=Strict'),
        'the serialized cookie is host-only and retains its fixed security attributes'
    );

    $forgedDescriptor = $issuedSubject;
    $forgedDescriptor['cookie']['domain'] = 'example.test';
    $forgedSameSite = $issuedSubject;
    $forgedSameSite['cookie']['sameSite'] = 'Lax';
    $forgedToken = $issuedSubject;
    $forgedToken['cookie']['value'] = 'not-an-opaque-token';
    $forgedIssue = $issuedSubject;
    $forgedIssue['issued'] = false;
    red_addon_public_mutation_subject_cookie_test_assert(
        !red_addon_public_mutation_subject_cookie_issued_valid(
            $forgedDescriptor
        )
            && !red_addon_public_mutation_subject_cookie_issued_valid(
                $forgedSameSite
            )
            && !red_addon_public_mutation_subject_cookie_issued_valid(
                $forgedToken
            )
            && !red_addon_public_mutation_subject_cookie_issued_valid(
                $forgedIssue
            )
            && red_addon_public_mutation_subject_cookie_serialize(
                $forgedDescriptor
            ) === red_addon_public_mutation_subject_cookie_serialization_result(),
        'forged descriptor shape, policy, token, or issuance evidence fails closed'
    );

    $forgedSerialization = $serialized;
    $forgedSerialization['setCookieValue'] .= '; Domain=example.test';
    $forgedMaxAge = $serialized;
    $forgedMaxAge['setCookieValue'] = str_replace(
        'Max-Age=1800',
        'Max-Age=1',
        $forgedMaxAge['setCookieValue']
    );
    red_addon_public_mutation_subject_cookie_test_assert(
        !red_addon_public_mutation_subject_cookie_serialization_valid(
            $forgedSerialization
        )
            && !red_addon_public_mutation_subject_cookie_serialization_valid(
                $forgedMaxAge
            ),
        'domain injection and lifetime drift cannot become valid cookie serialization'
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
    red_addon_public_mutation_subject_cookie_serialize($issuedSubject);
    red_addon_public_mutation_subject_cookie_test_assert(
        $_SERVER === $serverBefore
            && $_GET === $getBefore
            && $_POST === $postBefore
            && $_COOKIE === $cookieBefore
            && $_REQUEST === $requestBefore
            && headers_list() === $headersBefore
            && http_response_code() === $statusBefore
            && ob_get_level() === $bufferBefore
            && session_status() === $sessionBefore,
        'serialization changes no request, browser, response, session, or buffer state'
    );
} finally {
    // This dependency-free fixture owns no database, package, or client state.
}

fwrite(
    STDOUT,
    'Public-mutation subject-cookie self-test passed ('
        . $assertions . " assertions).\n"
);

?>
