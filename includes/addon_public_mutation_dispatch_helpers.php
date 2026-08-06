<?php
/**
 * Core-owned public-mutation dispatcher for one explicitly captured request.
 *
 * This helper is the first composition point for the already reviewed
 * public-mutation foundations. It accepts only explicit server facts supplied
 * by a supported integration; it never reads PHP request globals, starts a
 * session, emits a response, or claims a front-controller path by itself.
 * A later front controller may link it only after the supported server fixture
 * and client deployment boundary are separately accepted.
 */

require_once __DIR__ . '/addon_public_mutation_route_helpers.php';
require_once __DIR__ . '/addon_public_mutation_form_helpers.php';
require_once __DIR__ . '/addon_public_mutation_execution_helpers.php';
require_once __DIR__ . '/addon_public_mutation_response_helpers.php';

if (!function_exists('red_addon_public_mutation_dispatch_result')) {
    function red_addon_public_mutation_dispatch_result($reason = 'not_matched')
    {
        $allowed = [
            'not_matched',
            'runtime_unavailable',
            'route_unavailable',
            'route_ambiguous',
            'method_not_allowed',
            'transport_unavailable',
            'transport_invalid',
            'request_invalid',
            'subject_invalid',
            'subject_storage_unavailable',
            'csrf_invalid',
            'csrf_storage_unavailable',
            'fields_invalid',
            'contract_unavailable',
            'body_too_large',
            'execution_failed',
        ];
        $reason = is_string($reason) && in_array($reason, $allowed, true)
            ? $reason
            : 'runtime_unavailable';
        return [
            'claimed' => false,
            'response' => null,
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_dispatch_refusal')) {
    function red_addon_public_mutation_dispatch_refusal($reason)
    {
        $result = red_addon_public_mutation_dispatch_result($reason);
        $result['claimed'] = true;
        $result['response'] = red_addon_public_mutation_response_refusal(
            $reason
        );
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_dispatch_capture_valid')) {
    /**
     * Keeps the composition boundary closed to partial or forged transport.
     */
    function red_addon_public_mutation_dispatch_capture_valid($capture)
    {
        return is_array($capture)
            && array_keys($capture) === [
                'available',
                'trustedOrigin',
                'method',
                'requestTarget',
                'headers',
                'body',
                'reason',
            ]
            && is_bool($capture['available'])
            && is_string($capture['trustedOrigin'])
            && is_string($capture['method'])
            && is_string($capture['requestTarget'])
            && is_array($capture['headers'])
            && is_string($capture['body'])
            && is_string($capture['reason']);
    }
}

if (!function_exists('red_addon_public_mutation_dispatch_connection_valid')) {
    function red_addon_public_mutation_dispatch_connection_valid($connection)
    {
        return $connection instanceof mysqli;
    }
}

if (!function_exists('red_addon_public_mutation_dispatch')) {
    /**
     * Composes the reviewed core request, route, subject, CSRF, form, runner,
     * and response contracts without emitting or claiming HTTP state.
     */
    function red_addon_public_mutation_dispatch(
        $connection,
        $method,
        $requestTarget,
        $capture
    ) {
        $initial = red_addon_public_mutation_dispatch_result();
        if (!is_string($method)
            || !is_string($requestTarget)
            || !red_addon_public_mutation_dispatch_capture_valid($capture)
        ) {
            return $initial;
        }

        $selection = red_addon_public_mutation_route_select($requestTarget);
        if (!red_addon_public_mutation_route_selection_valid($selection)) {
            return red_addon_public_mutation_dispatch_refusal(
                'runtime_unavailable'
            );
        }
        if (empty($selection['claimed'])) {
            $result = red_addon_public_mutation_dispatch_result(
                $selection['reason'] ?? 'not_matched'
            );
            return $result;
        }

        if (empty($selection['ready'])) {
            return red_addon_public_mutation_dispatch_refusal(
                ($selection['reason'] ?? '') === 'route_ambiguous'
                    ? 'runtime_unavailable'
                    : 'route_unavailable'
            );
        }
        if ($method !== 'POST') {
            return red_addon_public_mutation_dispatch_refusal(
                'method_not_allowed'
            );
        }
        if (empty($capture['available'])
            || $capture['method'] !== $method
            || $capture['requestTarget'] !== $requestTarget
            || !in_array(
                $capture['reason'],
                ['captured'],
                true
            )
        ) {
            return red_addon_public_mutation_dispatch_refusal(
                in_array(
                    $capture['reason'],
                    ['transport_invalid', 'request_invalid'],
                    true
                )
                    ? 'transport_invalid'
                : 'transport_unavailable'
            );
        }
        if (!red_addon_public_mutation_dispatch_connection_valid($connection)) {
            return red_addon_public_mutation_dispatch_refusal(
                'runtime_unavailable'
            );
        }

        $manifest = red_addon_runtime_manifest($selection['packageId']);
        if (!is_array($manifest)) {
            return red_addon_public_mutation_dispatch_refusal(
                'runtime_unavailable'
            );
        }

        $request = red_addon_public_mutation_http_request_normalize(
            $manifest,
            $selection['route'],
            $selection['mutation'],
            $capture['trustedOrigin'],
            $capture['method'],
            $capture['requestTarget'],
            $capture['headers'],
            $capture['body']
        );
        if (!is_array($request)
            || empty($request['valid'])
        ) {
            $reason = is_array($request)
                && is_string($request['reason'] ?? null)
                    ? $request['reason']
                    : 'runtime_unavailable';
            return red_addon_public_mutation_dispatch_refusal($reason);
        }

        $declarationPlan = red_addon_public_mutation_declaration_preflight(
            $manifest,
            $selection['route'],
            $selection['mutation']
        );
        if (!red_addon_public_mutation_declaration_preflight_is_valid(
            $declarationPlan
        )) {
            return red_addon_public_mutation_dispatch_refusal(
                'runtime_unavailable'
            );
        }

        $subject = red_addon_public_mutation_subject_resolve(
            $connection,
            $request['subjectToken']
        );
        if (!is_array($subject) || empty($subject['valid'])) {
            $reason = is_array($subject)
                && is_string($subject['reason'] ?? null)
                    ? $subject['reason']
                    : 'subject_invalid';
            return red_addon_public_mutation_dispatch_refusal($reason);
        }

        $csrf = red_addon_public_mutation_csrf_verify(
            $connection,
            $subject,
            $declarationPlan,
            $request['csrfToken']
        );
        if (!is_array($csrf) || empty($csrf['valid'])) {
            $reason = is_array($csrf)
                && is_string($csrf['reason'] ?? null)
                    ? $csrf['reason']
                    : 'csrf_invalid';
            return red_addon_public_mutation_dispatch_refusal($reason);
        }

        $fields = red_addon_public_mutation_form_decode(
            $manifest,
            $selection['route'],
            $selection['mutation'],
            $request['body']
        );
        if (!is_array($fields) || empty($fields['valid'])) {
            $reason = is_array($fields)
                && is_string($fields['reason'] ?? null)
                    ? $fields['reason']
                    : 'fields_invalid';
            return red_addon_public_mutation_dispatch_refusal($reason);
        }

        $execution = red_addon_public_mutation_execute(
            $connection,
            $manifest,
            $selection['route'],
            $selection['mutation'],
            $subject,
            $request['csrfToken'],
            $request['idempotencyKey'],
            $fields['fields']
        );
        $response = red_addon_public_mutation_response_from_execution(
            $execution
        );
        $result = red_addon_public_mutation_dispatch_result(
            is_array($execution) && is_string($execution['reason'] ?? null)
                ? $execution['reason']
                : 'execution_failed'
        );
        $result['claimed'] = true;
        $result['response'] = $response;
        return $result;
    }
}

?>
