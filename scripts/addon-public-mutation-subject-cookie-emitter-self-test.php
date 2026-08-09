<?php
/**
 * Dependency-free checks for core subject-cookie response ownership.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot .
    '/includes/addon_public_mutation_subject_cookie_emitter_helpers.php';

$assertions = 0;

function red_addon_public_mutation_cookie_emitter_test_assert(
    $condition,
    $message
) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_cookie_emitter_test_value($token)
{
    return red_addon_public_mutation_subject_cookie_name() . '=' . $token
        . '; Max-Age=1800; Path=/; Secure; HttpOnly; SameSite=Strict';
}

$issuedValue = red_addon_public_mutation_cookie_emitter_test_value(
    str_repeat('a', 64)
);
$replacementValue = red_addon_public_mutation_cookie_emitter_test_value(
    str_repeat('b', 64)
);
$clearValue = red_addon_public_mutation_subject_cookie_clear_serialize();
$issued = red_addon_public_mutation_subject_cookie_lifecycle_set_result(
    'issued',
    10,
    0,
    $issuedValue,
    '',
    'subject_cookie_issued'
);
$resolved = red_addon_public_mutation_subject_cookie_lifecycle_set_result(
    'resolved',
    10,
    10,
    '',
    '',
    'subject_cookie_resolved'
);
$cleared = red_addon_public_mutation_subject_cookie_lifecycle_set_result(
    'cleared',
    0,
    10,
    '',
    $clearValue,
    'subject_cookie_cleared'
);
$rotated = red_addon_public_mutation_subject_cookie_lifecycle_set_result(
    'rotated',
    11,
    10,
    $replacementValue,
    $clearValue,
    'subject_cookie_rotated'
);

red_addon_public_mutation_cookie_emitter_test_assert(
    red_addon_public_mutation_subject_cookie_emitter_values($issued)
        === [$issuedValue]
        && red_addon_public_mutation_subject_cookie_emitter_values($resolved)
            === [],
    'issuance emits one fixed value while resolution emits none'
);
red_addon_public_mutation_cookie_emitter_test_assert(
    red_addon_public_mutation_subject_cookie_emitter_values($cleared)
        === [$clearValue]
        && red_addon_public_mutation_subject_cookie_emitter_values($rotated)
            === [$clearValue, $replacementValue],
    'clear and rotation preserve the fixed clear-before-set ordering'
);
$forged = $issued;
$forged['setCookieValue'] .= '; Domain=.example.test';
red_addon_public_mutation_cookie_emitter_test_assert(
    red_addon_public_mutation_subject_cookie_emitter_values($forged) === null
        && red_addon_public_mutation_subject_cookie_emitter_values([]) === null,
    'forged attributes and malformed lifecycle state fail before emission'
);

$source = file_get_contents(
    $projectRoot .
        '/includes/addon_public_mutation_subject_cookie_emitter_helpers.php'
);
red_addon_public_mutation_cookie_emitter_test_assert(
    is_string($source)
        && str_contains(
            $source,
            "header('Set-Cookie: ' . \$value, false);"
        )
        && !preg_match('/\$_(?:SERVER|GET|POST|COOKIE|SESSION|REQUEST)\b/', $source)
        && !str_contains($source, 'setcookie(')
        && !str_contains($source, 'Domain=')
        && !str_contains($source, 'Expires='),
    'only core lifecycle values can reach one fixed Set-Cookie emission site'
);

echo 'Public-mutation subject-cookie emitter self-test passed (' .
    $assertions . " assertions).\n";

?>
