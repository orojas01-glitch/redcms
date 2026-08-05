<?php
/**
 * Core-only server request-facts adapter for a future public add-on mutation
 * dispatcher.
 *
 * This helper owns the narrow translation boundary between server-local
 * configuration/current method-target facts and the already pure HTTP request
 * envelope. It accepts header lines only through an explicit complete capture
 * supplied by a later server integration. It deliberately does not use an
 * associative header API or an HTTP_* server projection, because either shape
 * can lose duplicate wire-header evidence needed by the envelope.
 *
 * It does not select or claim a route, bootstrap a runtime, access a database,
 * read cookies or parsed fields, issue browser evidence, call a package,
 * decode a form, run a transaction, emit a response, or change lifecycle,
 * enablement, Store Lite, or client state. It is not wired into index.php.
 */

require_once __DIR__ . '/runtime_config_helpers.php';
require_once __DIR__ . '/addon_public_mutation_http_request_helpers.php';

if (!function_exists('red_addon_public_mutation_server_request_result')) {
    function red_addon_public_mutation_server_request_result(
        $reason = 'server_unavailable'
    ) {
        $allowed = [
            'server_unavailable',
            'origin_unavailable',
            'transport_unavailable',
            'transport_invalid',
            'captured',
        ];
        $reason = is_string($reason) && in_array($reason, $allowed, true)
            ? $reason
            : 'server_unavailable';
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
}

if (!function_exists('red_addon_public_mutation_server_trusted_origin')) {
    /**
     * Resolves the future mutation origin from server-local configuration only.
     */
    function red_addon_public_mutation_server_trusted_origin()
    {
        $configuredOrigin = red_server_config_value(
            'PUBLIC_MUTATION_TRUSTED_ORIGIN',
            ['RED_PUBLIC_MUTATION_TRUSTED_ORIGIN'],
            ''
        );
        return red_addon_public_mutation_http_request_trusted_origin(
            $configuredOrigin
        );
    }
}

if (!function_exists('red_addon_public_mutation_server_request_headers')) {
    /**
     * Accepts only an upstream-attested, line-preserving complete header list.
     */
    function red_addon_public_mutation_server_request_headers($capture)
    {
        if (!is_array($capture)
            || array_keys($capture) !== ['complete', 'headers']
            || $capture['complete'] !== true
            || !is_array($capture['headers'])
        ) {
            return null;
        }
        $headers = $capture['headers'];
        if (red_addon_public_mutation_http_request_headers($headers) === null) {
            return null;
        }
        return $headers;
    }
}

if (!function_exists('red_addon_public_mutation_server_request_transport_valid')) {
    /**
     * Bounds raw transport facts without parsing or normalizing the request.
     */
    function red_addon_public_mutation_server_request_transport_valid(
        $method,
        $requestTarget,
        $body
    ) {
        return is_string($method)
            && strlen($method) >= 1
            && strlen($method) <= 16
            && red_addon_public_mutation_http_request_header_name_valid($method)
            && is_string($requestTarget)
            && strlen($requestTarget) >= 1
            && strlen($requestTarget) <= 2048
            && str_starts_with($requestTarget, '/')
            && !str_contains($requestTarget, '#')
            && preg_match('/[\x00-\x20\x7F]/', $requestTarget) !== 1
            && is_string($body)
            && strlen($body) <= 8192;
    }
}

if (!function_exists('red_addon_public_mutation_server_request_capture')) {
    /**
     * Captures bounded raw facts for a later dispatcher without dispatching.
     */
    function red_addon_public_mutation_server_request_capture(
        $method,
        $requestTarget,
        $headerCapture,
        $body
    ) {
        $trustedOrigin = red_addon_public_mutation_server_trusted_origin();
        if ($trustedOrigin === '') {
            return red_addon_public_mutation_server_request_result(
                'origin_unavailable'
            );
        }
        $headers = red_addon_public_mutation_server_request_headers(
            $headerCapture
        );
        if (!is_array($headers)) {
            return red_addon_public_mutation_server_request_result(
                'transport_unavailable'
            );
        }
        if (!red_addon_public_mutation_server_request_transport_valid(
            $method,
            $requestTarget,
            $body
        )) {
            return red_addon_public_mutation_server_request_result(
                'transport_invalid'
            );
        }
        return [
            'available' => true,
            'trustedOrigin' => $trustedOrigin,
            'method' => $method,
            'requestTarget' => $requestTarget,
            'headers' => $headers,
            'body' => $body,
            'reason' => 'captured',
        ];
    }
}

if (!function_exists('red_addon_public_mutation_server_request_capture_current')) {
    /**
     * Reads only the current server method and raw target; a later web-server
     * integration must supply the complete line-preserving headers and body.
     */
    function red_addon_public_mutation_server_request_capture_current(
        $headerCapture,
        $body
    ) {
        return red_addon_public_mutation_server_request_capture(
            $_SERVER['REQUEST_METHOD'] ?? null,
            $_SERVER['REQUEST_URI'] ?? null,
            $headerCapture,
            $body
        );
    }
}

?>
