<?php
/**
 * Pure core-owned composition for the future public-mutation response.
 *
 * This helper accepts only a validated non-executing deployment profile, one
 * closed core response envelope, and (optionally) one validated subject-cookie
 * lifecycle descriptor. It returns the exact response plus the only permitted
 * Set-Cookie lines without emitting headers/body bytes, reading request or
 * session state, resolving secrets, accessing a database, loading package
 * code, or linking the front controller.
 */

require_once __DIR__ . '/addon_public_mutation_deployment_profile_helpers.php';
require_once __DIR__ . '/addon_public_mutation_response_emitter_helpers.php';
require_once __DIR__ .
    '/addon_public_mutation_subject_cookie_emitter_helpers.php';

if (!function_exists('red_addon_public_mutation_response_owner_result')) {
    function red_addon_public_mutation_response_owner_result(
        $reason = 'response_owner_invalid'
    ) {
        $allowedReasons = [
            'response_owner_invalid',
            'deployment_profile_invalid',
            'response_invalid',
            'lifecycle_invalid',
            'cookie_body_leak',
        ];
        $reason = is_string($reason) && in_array(
            $reason,
            $allowedReasons,
            true
        ) ? $reason : 'response_owner_invalid';
        return [
            'valid' => false,
            'profileHash' => '',
            'response' => [],
            'setCookieValues' => [],
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_response_owner_cookie_values')) {
    /**
     * Maps one valid lifecycle state to its fixed response cookie lines.
     */
    function red_addon_public_mutation_response_owner_cookie_values(
        $lifecycle
    ) {
        if ($lifecycle === null) {
            return [];
        }
        return red_addon_public_mutation_subject_cookie_emitter_values(
            $lifecycle
        );
    }
}

if (!function_exists('red_addon_public_mutation_response_owner_compose')) {
    /**
     * Composes only the closed response and fixed lifecycle cookie lines.
     */
    function red_addon_public_mutation_response_owner_compose(
        $deploymentProfileResult,
        $response,
        $lifecycle = null
    ) {
        if (!red_addon_public_mutation_deployment_profile_valid(
            $deploymentProfileResult
        )) {
            return red_addon_public_mutation_response_owner_result(
                'deployment_profile_invalid'
            );
        }

        $profile = $deploymentProfileResult['profile'];
        if ($profile['response']['owner'] !== 'core'
            || $profile['response']['emitter']
                !== 'core_public_mutation_response_emitter'
            || $profile['response']['browserCookieOwner'] !== 'core'
            || $profile['response']['packageMayEmitHeaders'] !== false
            || $profile['response']['frontControllerLinked'] !== false
            || $profile['activation']['dispatcherLinked'] !== false
        ) {
            return red_addon_public_mutation_response_owner_result(
                'deployment_profile_invalid'
            );
        }

        if (!red_addon_public_mutation_response_emitter_valid($response)) {
            return red_addon_public_mutation_response_owner_result(
                'response_invalid'
            );
        }

        $cookieValues =
            red_addon_public_mutation_response_owner_cookie_values($lifecycle);
        if ($cookieValues === null) {
            return red_addon_public_mutation_response_owner_result(
                'lifecycle_invalid'
            );
        }

        foreach ($cookieValues as $cookieValue) {
            $cookieParts = explode('=', $cookieValue, 2);
            $cookieTokenAndAttributes = $cookieParts[1] ?? '';
            $cookieToken = explode(';', $cookieTokenAndAttributes, 2)[0];
            if ($cookieToken !== ''
                && red_addon_public_mutation_valid_opaque_token($cookieToken)
                && strpos($response['body'], $cookieToken) !== false
            ) {
                return red_addon_public_mutation_response_owner_result(
                    'cookie_body_leak'
                );
            }
        }

        return [
            'valid' => true,
            'profileHash' => $deploymentProfileResult['profileHash'],
            'response' => $response,
            'setCookieValues' => $cookieValues,
            'reason' => 'response_owner_valid',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_response_owner_result_valid')) {
    function red_addon_public_mutation_response_owner_result_valid($result)
    {
        if (!is_array($result)
            || array_keys($result) !== [
                'valid',
                'profileHash',
                'response',
                'setCookieValues',
                'reason',
            ]
            || !is_bool($result['valid'])
            || !is_string($result['profileHash'])
            || !is_array($result['response'])
            || !is_array($result['setCookieValues'])
            || !is_string($result['reason'])
        ) {
            return false;
        }
        if (!$result['valid']) {
            return $result ===
                red_addon_public_mutation_response_owner_result(
                    $result['reason']
                );
        }
        $cookieValueKeys = array_keys($result['setCookieValues']);
        $cookieValueKeysValid = $cookieValueKeys === []
            || $cookieValueKeys === range(
                0,
                count($result['setCookieValues']) - 1
            );
        if ($result['reason'] !== 'response_owner_valid'
            || !red_addon_public_mutation_response_emitter_valid(
                $result['response']
            )
            || !red_addon_valid_sha256($result['profileHash'])
            || count($result['setCookieValues']) > 2
            || !$cookieValueKeysValid
        ) {
            return false;
        }
        $hasClear = false;
        $hasSet = false;
        foreach ($result['setCookieValues'] as $index => $cookieValue) {
            if (!is_string($cookieValue)) {
                return false;
            }
            $cookieParts = explode('=', $cookieValue, 2);
            if (count($cookieParts) !== 2) {
                return false;
            }
            $cookieName = $cookieParts[0];
            $cookieTokenAndAttributes = $cookieParts[1];
            $cookieToken = explode(';', $cookieTokenAndAttributes, 2)[0];
            $setSerialization =
                red_addon_public_mutation_subject_cookie_serialization_valid([
                    'valid' => true,
                    'setCookieValue' => $cookieValue,
                    'reason' => 'subject_cookie_serialized',
                ]);
            $clearSerialization =
                red_addon_public_mutation_subject_cookie_clear_serialized_valid(
                    $cookieValue
                );
            if ($cookieName !== red_addon_public_mutation_subject_cookie_name()
                || (!$setSerialization && !$clearSerialization)
                || ($setSerialization && $hasSet)
                || ($clearSerialization && $hasClear)
                || ($index === 0 && $clearSerialization === false
                    && count($result['setCookieValues']) === 2)
                || ($index === 1 && $setSerialization === false
                    && count($result['setCookieValues']) === 2)
            ) {
                return false;
            }
            $hasClear = $hasClear || $clearSerialization;
            $hasSet = $hasSet || $setSerialization;
            if ($setSerialization) {
                $setToken = $cookieToken;
                if (strpos($result['response']['body'], $setToken) !== false) {
                    return false;
                }
            }
        }
        return true;
    }
}

?>
