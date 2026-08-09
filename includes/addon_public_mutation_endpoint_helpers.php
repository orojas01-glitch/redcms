<?php
/**
 * Core-owned operational bridge for the supported public-mutation endpoint.
 *
 * The bridge remains dormant unless an operator explicitly enables it in
 * server-local configuration and supplies both the canonical HTTPS origin and
 * the Caddy/FrankenPHP ingress HMAC key. It accepts only POST requests in the
 * reserved /addons/ namespace, composes the already reviewed dispatcher, and
 * emits only the closed core response envelope.
 */

require_once __DIR__ . '/addon_public_mutation_dispatch_helpers.php';
require_once __DIR__ . '/addon_public_mutation_frankenphp_ingress_helpers.php';
require_once __DIR__ . '/addon_public_mutation_response_emitter_helpers.php';

if (!function_exists('red_addon_public_mutation_endpoint_result')) {
    function red_addon_public_mutation_endpoint_result(
        $reason = 'endpoint_not_claimed'
    ) {
        $allowed = [
            'endpoint_not_claimed',
            'endpoint_disabled',
            'endpoint_invalid',
            'endpoint_unavailable',
            'endpoint_ready',
        ];
        $reason = is_string($reason) && in_array($reason, $allowed, true)
            ? $reason
            : 'endpoint_invalid';
        return [
            'claimed' => false,
            'response' => null,
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_endpoint_candidate')) {
    function red_addon_public_mutation_endpoint_candidate(
        $method,
        $requestTarget
    ) {
        if (!is_string($method)
            || strlen($method) < 1
            || strlen($method) > 16
            || !red_addon_public_mutation_http_request_header_name_valid(
                $method
            )
            || !is_string($requestTarget)
            || strlen($requestTarget) < 10
            || strlen($requestTarget) > 2048
            || preg_match('/[\x00-\x20\x7F]/', $requestTarget) === 1
            || str_contains($requestTarget, '#')
        ) {
            return false;
        }
        $queryAt = strpos($requestTarget, '?');
        $path = $queryAt === false
            ? $requestTarget
            : substr($requestTarget, 0, $queryAt);
        return is_string($path)
            && str_starts_with($path, '/addons/')
            && red_addon_valid_route_path($path);
    }
}

if (!function_exists('red_addon_public_mutation_endpoint_enabled')) {
    /**
     * Requires three independent operator-owned deployment facts.
     */
    function red_addon_public_mutation_endpoint_enabled()
    {
        $enabled = red_server_config_value(
            'PUBLIC_MUTATION_ENDPOINT_ENABLED',
            ['RED_PUBLIC_MUTATION_ENDPOINT_ENABLED'],
            false
        );
        return ($enabled === true || $enabled === '1')
            && red_addon_public_mutation_server_trusted_origin() !== ''
            && red_addon_public_mutation_frankenphp_ingress_key() !== '';
    }
}

if (!function_exists('red_addon_public_mutation_endpoint_dispatch')) {
    /**
     * Composes explicit, already-captured facts without reading globals.
     */
    function red_addon_public_mutation_endpoint_dispatch(
        $connection,
        $method,
        $requestTarget,
        $capture,
        $enabled
    ) {
        if (!red_addon_public_mutation_endpoint_candidate(
            $method,
            $requestTarget
        )) {
            return red_addon_public_mutation_endpoint_result();
        }
        if ($enabled !== true) {
            $result = red_addon_public_mutation_endpoint_result(
                'endpoint_disabled'
            );
            $result['claimed'] = true;
            $result['response'] =
                red_addon_public_mutation_response_refusal(
                    'runtime_unavailable'
                );
            return $result;
        }

        $dispatch = red_addon_public_mutation_dispatch(
            $connection,
            $method,
            $requestTarget,
            $capture
        );
        if (!is_array($dispatch)
            || array_keys($dispatch) !== ['claimed', 'response', 'reason']
            || !is_bool($dispatch['claimed'])
            || !is_string($dispatch['reason'])
        ) {
            $dispatch = null;
        }
        if ($dispatch !== null
            && empty($dispatch['claimed'])
            && $method !== 'POST'
        ) {
            return red_addon_public_mutation_endpoint_result();
        }
        if ($dispatch === null || empty($dispatch['claimed'])) {
            $result = red_addon_public_mutation_endpoint_result(
                $dispatch === null
                    ? 'endpoint_unavailable'
                    : 'endpoint_invalid'
            );
            $result['claimed'] = true;
            $result['response'] = red_addon_public_mutation_response_refusal(
                $dispatch === null ? 'runtime_unavailable' : 'invalid_request'
            );
            return $result;
        }
        if (!red_addon_public_mutation_response_emitter_valid(
            $dispatch['response']
        )) {
            $result = red_addon_public_mutation_endpoint_result(
                'endpoint_unavailable'
            );
            $result['claimed'] = true;
            $result['response'] = red_addon_public_mutation_response_refusal(
                'runtime_unavailable'
            );
            return $result;
        }
        return [
            'claimed' => true,
            'response' => $dispatch['response'],
            'reason' => 'endpoint_ready',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_endpoint_dispatch_current')) {
    /**
     * Reads the signed current request only after the explicit endpoint gate.
     */
    function red_addon_public_mutation_endpoint_dispatch_current($connection)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        $requestTarget = $_SERVER['REQUEST_URI'] ?? null;
        if (!red_addon_public_mutation_endpoint_candidate(
            $method,
            $requestTarget
        )) {
            return red_addon_public_mutation_endpoint_result();
        }
        $enabled = red_addon_public_mutation_endpoint_enabled();
        $capture = $enabled && $method === 'POST'
            ? red_addon_public_mutation_frankenphp_ingress_capture_current()
            : red_addon_public_mutation_server_request_result(
                'transport_unavailable'
            );
        return red_addon_public_mutation_endpoint_dispatch(
            $connection,
            $method,
            $requestTarget,
            $capture,
            $enabled
        );
    }
}

if (!function_exists('red_addon_public_mutation_endpoint_result_valid')) {
    function red_addon_public_mutation_endpoint_result_valid($result)
    {
        if (!is_array($result)
            || array_keys($result) !== ['claimed', 'response', 'reason']
            || !is_bool($result['claimed'])
            || (!is_array($result['response'])
                && $result['response'] !== null)
            || !is_string($result['reason'])
        ) {
            return false;
        }
        if (!$result['claimed']) {
            return $result === red_addon_public_mutation_endpoint_result(
                $result['reason']
            );
        }
        return in_array(
            $result['reason'],
            [
                'endpoint_disabled',
                'endpoint_invalid',
                'endpoint_unavailable',
                'endpoint_ready',
            ],
            true
        )
            && red_addon_public_mutation_response_emitter_valid(
                $result['response']
            );
    }
}

if (!function_exists('red_addon_public_mutation_endpoint_emit')) {
    function red_addon_public_mutation_endpoint_emit($result)
    {
        if (!red_addon_public_mutation_endpoint_result_valid($result)
            || empty($result['claimed'])
        ) {
            throw new InvalidArgumentException(
                'Public-mutation endpoint result is invalid.'
            );
        }
        red_addon_public_mutation_response_emit($result['response']);
    }
}

?>
