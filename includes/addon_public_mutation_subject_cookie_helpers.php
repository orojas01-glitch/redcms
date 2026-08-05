<?php
/**
 * Pure core-only serialization for a future public-mutation subject cookie.
 *
 * This helper accepts only the exact descriptor shape returned by the internal
 * subject issuer and converts it into one fixed host-only Set-Cookie value for
 * a later dispatcher. It does not read request/cookie/session globals, access
 * a database, issue a subject, emit a header or cookie, bootstrap runtime
 * state, load package code, select or claim a route, or change lifecycle,
 * enablement, Store Lite, or client state. It is not wired into index.php.
 */

require_once __DIR__ . '/addon_public_mutation_subject_helpers.php';

if (!function_exists('red_addon_public_mutation_subject_cookie_serialization_result')) {
    function red_addon_public_mutation_subject_cookie_serialization_result()
    {
        return [
            'valid' => false,
            'setCookieValue' => '',
            'reason' => 'subject_invalid',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_issued_valid')) {
    /**
     * Restricts serialization to the exact descriptor shape from the core issuer.
     */
    function red_addon_public_mutation_subject_cookie_issued_valid($subject)
    {
        if (!is_array($subject)
            || array_keys($subject) !== [
                'valid', 'issued', 'subjectRecordId', 'cookie', 'reason',
            ]
            || ($subject['valid'] ?? null) !== true
            || ($subject['issued'] ?? null) !== true
            || !is_int($subject['subjectRecordId'] ?? null)
            || $subject['subjectRecordId'] < 1
            || ($subject['reason'] ?? null) !== 'subject_issued'
            || !is_array($subject['cookie'] ?? null)
        ) {
            return false;
        }

        $token = $subject['cookie']['value'] ?? null;
        $expectedCookie = red_addon_public_mutation_subject_cookie_options();
        $expectedCookie['value'] = $token;
        return red_addon_public_mutation_valid_opaque_token($token)
            && $subject['cookie'] === $expectedCookie;
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_serialize')) {
    /**
     * Creates one fixed, host-only cookie value without emitting it.
     */
    function red_addon_public_mutation_subject_cookie_serialize($subject)
    {
        $result = red_addon_public_mutation_subject_cookie_serialization_result();
        if (!red_addon_public_mutation_subject_cookie_issued_valid($subject)) {
            return $result;
        }

        $cookie = $subject['cookie'];
        $result['valid'] = true;
        $result['setCookieValue'] = $cookie['name'] . '=' . $cookie['value']
            . '; Max-Age=' . $cookie['maxAgeSeconds']
            . '; Path=' . $cookie['path']
            . '; Secure; HttpOnly; SameSite=' . $cookie['sameSite'];
        $result['reason'] = 'subject_cookie_serialized';
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_serialization_valid')) {
    function red_addon_public_mutation_subject_cookie_serialization_valid(
        $serialization
    ) {
        if (!is_array($serialization)
            || array_keys($serialization) !== [
                'valid', 'setCookieValue', 'reason',
            ]
            || !is_bool($serialization['valid'])
            || !is_string($serialization['setCookieValue'])
            || !is_string($serialization['reason'])
        ) {
            return false;
        }
        if ($serialization['valid'] === false) {
            return $serialization ===
                red_addon_public_mutation_subject_cookie_serialization_result();
        }
        if ($serialization['reason'] !== 'subject_cookie_serialized') {
            return false;
        }

        $prefix = red_addon_public_mutation_subject_cookie_name() . '=';
        $suffix = '; Max-Age='
            . red_addon_public_mutation_subject_lifetime_seconds()
            . '; Path=/; Secure; HttpOnly; SameSite=Strict';
        $value = $serialization['setCookieValue'];
        if (!str_starts_with($value, $prefix)
            || !str_ends_with($value, $suffix)
        ) {
            return false;
        }
        $tokenLength = strlen($value) - strlen($prefix) - strlen($suffix);
        if ($tokenLength !== 64) {
            return false;
        }
        $token = substr($value, strlen($prefix), $tokenLength);
        return red_addon_public_mutation_valid_opaque_token($token);
    }
}

?>
