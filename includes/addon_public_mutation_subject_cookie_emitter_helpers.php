<?php
/**
 * Core-owned emitter for fixed public-mutation subject-cookie lifecycle state.
 *
 * It accepts only the existing lifecycle result vocabulary and can emit only
 * the exact host-only Set-Cookie lines already validated by core. Package and
 * theme code never supply a header name, cookie attribute, or token value.
 */

require_once __DIR__ .
    '/addon_public_mutation_subject_cookie_lifecycle_helpers.php';

if (!function_exists('red_addon_public_mutation_subject_cookie_emitter_values')) {
    function red_addon_public_mutation_subject_cookie_emitter_values(
        $lifecycle
    ) {
        if (!red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
            $lifecycle
        ) || empty($lifecycle['valid'])) {
            return null;
        }
        if ($lifecycle['state'] === 'issued') {
            return [$lifecycle['setCookieValue']];
        }
        if ($lifecycle['state'] === 'resolved') {
            return [];
        }
        if ($lifecycle['state'] === 'cleared') {
            return [$lifecycle['clearCookieValue']];
        }
        if ($lifecycle['state'] === 'rotated') {
            return [
                $lifecycle['clearCookieValue'],
                $lifecycle['setCookieValue'],
            ];
        }
        return null;
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_emit')) {
    function red_addon_public_mutation_subject_cookie_emit($lifecycle)
    {
        $values =
            red_addon_public_mutation_subject_cookie_emitter_values($lifecycle);
        if ($values === null) {
            throw new InvalidArgumentException(
                'Public-mutation subject-cookie lifecycle is invalid.'
            );
        }
        if ($values !== [] && headers_sent()) {
            throw new RuntimeException(
                'Public-mutation subject-cookie headers were sent prematurely.'
            );
        }
        foreach ($values as $value) {
            header('Set-Cookie: ' . $value, false);
        }
    }
}

?>
