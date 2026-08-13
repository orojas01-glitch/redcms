<?php
/**
 * Shared-host-compatible direct PHP ingress for public add-on mutations.
 *
 * This adapter is an explicitly selected alternative to the optional
 * Caddy/FrankenPHP attestation profile. It accepts only the fixed Apache/PHP
 * server projection used by an HTTPS POST, validates the security-relevant
 * values before reading a bounded body, and hands explicit facts to the same
 * core request adapter and dispatcher. It never trusts Host, forwarded
 * identity, parsed request fields, or package input.
 *
 * Unlike the attested profile, this adapter cannot prove which raw duplicate
 * wire-header lines the web server received. It therefore accepts only values
 * whose closed syntax makes a combined or ambiguous projection invalid and is
 * supported only for a direct HTTPS web-server-to-PHP deployment.
 */

require_once __DIR__ . '/addon_public_mutation_server_request_helpers.php';

if (!function_exists('red_addon_public_mutation_direct_ingress_result')) {
    function red_addon_public_mutation_direct_ingress_result($reason)
    {
        return red_addon_public_mutation_server_request_result($reason);
    }
}

if (!function_exists('red_addon_public_mutation_direct_ingress_https')) {
    /**
     * Requires a direct server-owned HTTPS fact. Forwarded headers are never
     * accepted as a substitute.
     */
    function red_addon_public_mutation_direct_ingress_https($server)
    {
        if (!is_array($server)) {
            return false;
        }
        $https = $server['HTTPS'] ?? null;
        return $https === 'on' || $https === '1';
    }
}

if (!function_exists('red_addon_public_mutation_direct_ingress_projected_headers')) {
    /**
     * Builds the only supported fixed header projection after closed syntax
     * validation. Host and forwarding headers are deliberately ignored.
     */
    function red_addon_public_mutation_direct_ingress_projected_headers(
        $server
    ) {
        if (!is_array($server)
            || array_key_exists('HTTP_CONTENT_TYPE', $server)
            || array_key_exists('HTTP_CONTENT_LENGTH', $server)
            || array_key_exists('HTTP_TRANSFER_ENCODING', $server)
            || array_key_exists('TRANSFER_ENCODING', $server)
            || array_key_exists('HTTP_CONTENT_ENCODING', $server)
            || array_key_exists('CONTENT_ENCODING', $server)
        ) {
            return null;
        }

        $trustedOrigin = red_addon_public_mutation_server_trusted_origin();
        $origin = $server['HTTP_ORIGIN'] ?? null;
        $contentType = $server['CONTENT_TYPE'] ?? null;
        $contentLength = $server['CONTENT_LENGTH'] ?? null;
        $cookie = $server['HTTP_COOKIE'] ?? null;
        $csrf = $server['HTTP_X_RED_CMS_CSRF'] ?? null;
        $idempotency = $server['HTTP_IDEMPOTENCY_KEY'] ?? null;
        $subjectToken = is_string($cookie)
            ? red_addon_public_mutation_http_request_subject_token($cookie)
            : null;

        if ($trustedOrigin === ''
            || !is_string($origin)
            || !hash_equals($trustedOrigin, $origin)
            || !red_addon_public_mutation_http_request_content_type_valid(
                $contentType
            )
            || !is_string($contentLength)
            || preg_match('/\A(?:0|[1-9][0-9]{0,3})\z/D', $contentLength)
                !== 1
            || (int) $contentLength > 8192
            || !is_string($cookie)
            || !red_addon_public_mutation_valid_opaque_token($subjectToken)
            || !red_addon_public_mutation_valid_opaque_token($csrf)
            || !red_addon_public_mutation_valid_opaque_token($idempotency)
        ) {
            return null;
        }

        $headers = [
            ['name' => 'Origin', 'value' => $origin],
            ['name' => 'Content-Type', 'value' => $contentType],
            ['name' => 'Content-Length', 'value' => $contentLength],
            ['name' => 'Cookie', 'value' => $cookie],
            [
                'name' =>
                    red_addon_public_mutation_http_request_csrf_header_name(),
                'value' => $csrf,
            ],
            [
                'name' =>
                    red_addon_public_mutation_http_request_idempotency_header_name(),
                'value' => $idempotency,
            ],
        ];
        return red_addon_public_mutation_http_request_headers($headers) === null
            ? null
            : $headers;
    }
}

if (!function_exists('red_addon_public_mutation_direct_ingress_preflight')) {
    /**
     * Validates all current server facts that must precede body I/O.
     */
    function red_addon_public_mutation_direct_ingress_preflight($server)
    {
        if (!is_array($server)
            || !red_addon_public_mutation_direct_ingress_https($server)
        ) {
            return null;
        }
        $method = $server['REQUEST_METHOD'] ?? null;
        $requestTarget = $server['REQUEST_URI'] ?? null;
        $headers =
            red_addon_public_mutation_direct_ingress_projected_headers($server);
        if ($method !== 'POST'
            || !is_string($requestTarget)
            || !str_starts_with($requestTarget, '/addons/')
            || !is_array($headers)
        ) {
            return null;
        }
        return [
            'method' => 'POST',
            'requestTarget' => $requestTarget,
            'headers' => $headers,
            'bodyBytes' => (int) $server['CONTENT_LENGTH'],
        ];
    }
}

if (!function_exists('red_addon_public_mutation_direct_ingress_capture')) {
    /**
     * Validates an explicit body against the already bounded PHP projection.
     */
    function red_addon_public_mutation_direct_ingress_capture($server, $body)
    {
        $preflight =
            red_addon_public_mutation_direct_ingress_preflight($server);
        if (!is_array($preflight)) {
            return red_addon_public_mutation_direct_ingress_result(
                'transport_unavailable'
            );
        }
        if (!is_string($body)
            || strlen($body) !== $preflight['bodyBytes']
        ) {
            return red_addon_public_mutation_direct_ingress_result(
                'transport_invalid'
            );
        }
        return red_addon_public_mutation_server_request_capture(
            $preflight['method'],
            $preflight['requestTarget'],
            [
                'complete' => true,
                'headers' => $preflight['headers'],
            ],
            $body
        );
    }
}

if (!function_exists('red_addon_public_mutation_direct_ingress_capture_current')) {
    /**
     * Reads php://input only after the fixed direct-PHP preflight succeeds.
     */
    function red_addon_public_mutation_direct_ingress_capture_current()
    {
        $preflight =
            red_addon_public_mutation_direct_ingress_preflight($_SERVER);
        if (!is_array($preflight)) {
            return red_addon_public_mutation_direct_ingress_result(
                'transport_unavailable'
            );
        }
        $body = file_get_contents('php://input');
        if (!is_string($body)) {
            return red_addon_public_mutation_direct_ingress_result(
                'transport_unavailable'
            );
        }
        return red_addon_public_mutation_direct_ingress_capture(
            $_SERVER,
            $body
        );
    }
}

?>
