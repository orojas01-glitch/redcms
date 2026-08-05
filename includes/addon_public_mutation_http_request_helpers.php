<?php
/**
 * Pure HTTP request-envelope normalization for a future public add-on mutation
 * dispatcher.
 *
 * The later core-owned dispatcher must pass only explicit transport values to
 * this helper. It validates one already-closed static declaration, a
 * server-configured canonical HTTPS origin, a raw request target/method, a
 * complete header list, and raw body bytes. It returns opaque subject, CSRF,
 * and idempotency evidence only after every transport fact is valid. It does
 * not read PHP request/cookie/session globals, access a database, load package
 * code, issue or resolve browser evidence, decode package fields, claim a
 * route, emit a response, or change lifecycle, package, Store Lite, or client
 * state.
 */

require_once __DIR__ . '/addon_public_mutation_subject_helpers.php';

if (!function_exists('red_addon_public_mutation_http_request_result')) {
    function red_addon_public_mutation_http_request_result(
        $reason = 'runtime_unavailable'
    ) {
        $allowed = [
            'runtime_unavailable',
            'method_not_allowed',
            'invalid_request',
            'origin_invalid',
            'content_type_invalid',
            'content_length_invalid',
            'body_too_large',
            'subject_invalid',
            'csrf_invalid',
            'idempotency_invalid',
        ];
        $reason = is_string($reason) && in_array($reason, $allowed, true)
            ? $reason
            : 'runtime_unavailable';
        return [
            'valid' => false,
            'method' => '',
            'path' => '',
            'origin' => '',
            'body' => '',
            'csrfToken' => '',
            'idempotencyKey' => '',
            'subjectToken' => '',
            'reason' => $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_http_request_csrf_header_name')) {
    function red_addon_public_mutation_http_request_csrf_header_name()
    {
        return 'X-RED-CMS-CSRF';
    }
}

if (!function_exists('red_addon_public_mutation_http_request_idempotency_header_name')) {
    function red_addon_public_mutation_http_request_idempotency_header_name()
    {
        return 'Idempotency-Key';
    }
}

if (!function_exists('red_addon_public_mutation_http_request_contract')) {
    function red_addon_public_mutation_http_request_contract(
        $manifest,
        $routeId,
        $mutationId
    ) {
        if (!is_array($manifest)
            || !is_string($routeId)
            || !is_string($mutationId)
        ) {
            return null;
        }
        $contract = red_addon_public_mutation_contract(
            $manifest,
            $routeId,
            $mutationId
        );
        $path = is_array($contract) && is_string($contract['path'] ?? null)
            ? $contract['path']
            : '';
        if (!is_array($contract)
            || ($contract['route'] ?? null) !== $routeId
            || ($contract['mutation'] ?? null) !== $mutationId
            || ($contract['scope'] ?? null) !== 'public'
            || ($contract['authentication'] ?? null) !== 'public'
            || ($contract['method'] ?? null) !== 'POST'
            || ($contract['csrf'] ?? null) !== 'required'
            || ($contract['encoding'] ?? null)
                !== 'application/x-www-form-urlencoded'
            || !is_int($contract['maxBodyBytes'] ?? null)
            || $contract['maxBodyBytes'] < 128
            || $contract['maxBodyBytes'] > 8192
            || !red_addon_valid_route_path($path)
            || str_contains($path, '%')
            || str_contains($path, '{')
            || str_contains($path, '}')
        ) {
            return null;
        }
        return $contract;
    }
}

if (!function_exists('red_addon_public_mutation_http_request_header_name_valid')) {
    function red_addon_public_mutation_http_request_header_name_valid($name)
    {
        return is_string($name)
            && strlen($name) >= 1
            && strlen($name) <= 80
            && preg_match(
                '/\A[!#$%&\'*+\-.^_`|~0-9A-Za-z]+\z/D',
                $name
            ) === 1;
    }
}

if (!function_exists('red_addon_public_mutation_http_request_headers')) {
    /**
     * Normalizes only security-relevant headers from an explicit complete list.
     */
    function red_addon_public_mutation_http_request_headers($headers)
    {
        if (!is_array($headers)
            || !array_is_list($headers)
            || count($headers) > 64
        ) {
            return null;
        }
        $critical = array_fill_keys([
            'origin',
            'content-type',
            'content-length',
            'cookie',
            strtolower(red_addon_public_mutation_http_request_csrf_header_name()),
            strtolower(
                red_addon_public_mutation_http_request_idempotency_header_name()
            ),
        ], true);
        $normalized = [];
        $totalBytes = 0;
        foreach ($headers as $header) {
            if (!is_array($header)
                || array_keys($header) !== ['name', 'value']
                || !is_string($header['name'])
                || !is_string($header['value'])
                || !red_addon_public_mutation_http_request_header_name_valid(
                    $header['name']
                )
                || strlen($header['value']) > 8192
                || preg_match('/[\x00-\x1F\x7F]/', $header['value']) === 1
            ) {
                return null;
            }
            $totalBytes += strlen($header['name']) + strlen($header['value']) + 2;
            if ($totalBytes > 16384) {
                return null;
            }
            $name = strtolower($header['name']);
            if ($name === 'transfer-encoding' || $name === 'content-encoding') {
                return null;
            }
            if (isset($critical[$name])) {
                if (array_key_exists($name, $normalized)) {
                    return null;
                }
                $normalized[$name] = $header['value'];
            }
        }
        return $normalized;
    }
}

if (!function_exists('red_addon_public_mutation_http_request_trusted_origin')) {
    /**
     * Accepts one canonical server-configured HTTPS origin, never Host input.
     */
    function red_addon_public_mutation_http_request_trusted_origin($origin)
    {
        if (!is_string($origin)
            || $origin === ''
            || strlen($origin) > 255
            || preg_match('/[\x00-\x20\x7F]/', $origin) === 1
        ) {
            return '';
        }
        $parts = parse_url($origin);
        if (!is_array($parts)) {
            return '';
        }
        foreach (array_keys($parts) as $key) {
            if (!in_array($key, ['scheme', 'host', 'port'], true)) {
                return '';
            }
        }
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : '';
        if (($parts['scheme'] ?? null) !== 'https'
            || $host === ''
            || $host !== strtolower($host)
        ) {
            return '';
        }
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $literal = substr($host, 1, -1);
            if ($literal === ''
                || filter_var(
                    $literal,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV6
                ) === false
            ) {
                return '';
            }
        } elseif (filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4
        ) === false) {
            $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
            if (strlen($host) > 253
                || preg_match('/\A(?:' . $label . '\.)*' . $label . '\z/D', $host)
                    !== 1
            ) {
                return '';
            }
        }
        $port = $parts['port'] ?? null;
        if ($port !== null
            && (!is_int($port) || $port < 1 || $port > 65535 || $port === 443)
        ) {
            return '';
        }
        $canonical = 'https://' . $host
            . ($port === null ? '' : ':' . (string) $port);
        return hash_equals($canonical, $origin) ? $canonical : '';
    }
}

if (!function_exists('red_addon_public_mutation_http_request_content_type_valid')) {
    function red_addon_public_mutation_http_request_content_type_valid($value)
    {
        return is_string($value)
            && in_array(
                $value,
                [
                    'application/x-www-form-urlencoded',
                    'application/x-www-form-urlencoded;charset=UTF-8',
                ],
                true
            );
    }
}

if (!function_exists('red_addon_public_mutation_http_request_subject_token')) {
    /**
     * Extracts only one exact opaque core subject cookie from raw header bytes.
     */
    function red_addon_public_mutation_http_request_subject_token($cookieHeader)
    {
        if (!is_string($cookieHeader)
            || $cookieHeader === ''
            || strlen($cookieHeader) > 16384
            || preg_match('/[\x00-\x1F\x7F]/', $cookieHeader) === 1
        ) {
            return null;
        }
        $cookieName = red_addon_public_mutation_subject_cookie_name();
        if (!is_string($cookieName)
            || !red_addon_public_mutation_http_request_header_name_valid(
                $cookieName
            )
        ) {
            return null;
        }
        $subjectToken = '';
        $subjectSeen = false;
        foreach (explode(';', $cookieHeader) as $pair) {
            $pair = trim($pair, " \t");
            if ($pair === '' || !str_contains($pair, '=')) {
                return null;
            }
            [$name, $value] = explode('=', $pair, 2);
            if ($name === ''
                || $name !== trim($name, " \t")
                || !red_addon_public_mutation_http_request_header_name_valid($name)
            ) {
                return null;
            }
            if (!hash_equals($cookieName, $name)) {
                continue;
            }
            if ($subjectSeen
                || !red_addon_public_mutation_valid_opaque_token($value)
            ) {
                return null;
            }
            $subjectSeen = true;
            $subjectToken = $value;
        }
        return $subjectToken;
    }
}

if (!function_exists('red_addon_public_mutation_http_request_normalize')) {
    /**
     * Returns raw evidence only after complete static transport validation.
     */
    function red_addon_public_mutation_http_request_normalize(
        $manifest,
        $routeId,
        $mutationId,
        $trustedOrigin,
        $method,
        $requestUri,
        $headers,
        $body
    ) {
        $contract = red_addon_public_mutation_http_request_contract(
            $manifest,
            $routeId,
            $mutationId
        );
        if (!is_array($contract)
            || red_addon_public_mutation_http_request_trusted_origin(
                $trustedOrigin
            ) === ''
        ) {
            return red_addon_public_mutation_http_request_result(
                'runtime_unavailable'
            );
        }
        if (!is_string($requestUri) || $requestUri !== $contract['path']) {
            return red_addon_public_mutation_http_request_result('invalid_request');
        }
        if (!is_string($method) || $method !== 'POST') {
            return red_addon_public_mutation_http_request_result(
                'method_not_allowed'
            );
        }
        if (!is_string($body)) {
            return red_addon_public_mutation_http_request_result('invalid_request');
        }
        if (strlen($body) > $contract['maxBodyBytes']) {
            return red_addon_public_mutation_http_request_result('body_too_large');
        }
        $headerValues = red_addon_public_mutation_http_request_headers($headers);
        if (!is_array($headerValues)) {
            return red_addon_public_mutation_http_request_result('invalid_request');
        }
        $origin = $headerValues['origin'] ?? null;
        if (!is_string($origin)
            || !hash_equals($trustedOrigin, $origin)
        ) {
            return red_addon_public_mutation_http_request_result('origin_invalid');
        }
        $contentType = $headerValues['content-type'] ?? null;
        if (!red_addon_public_mutation_http_request_content_type_valid(
            $contentType
        )) {
            return red_addon_public_mutation_http_request_result(
                'content_type_invalid'
            );
        }
        if (array_key_exists('content-length', $headerValues)) {
            $contentLength = $headerValues['content-length'];
            if (!preg_match('/\A(?:0|[1-9][0-9]{0,4})\z/D', $contentLength)
                || (int) $contentLength !== strlen($body)
            ) {
                return red_addon_public_mutation_http_request_result(
                    'content_length_invalid'
                );
            }
        }
        $subjectToken = red_addon_public_mutation_http_request_subject_token(
            $headerValues['cookie'] ?? null
        );
        if (!is_string($subjectToken)
            || !red_addon_public_mutation_valid_opaque_token($subjectToken)
        ) {
            return red_addon_public_mutation_http_request_result('subject_invalid');
        }
        $csrfHeader = strtolower(
            red_addon_public_mutation_http_request_csrf_header_name()
        );
        $csrfToken = $headerValues[$csrfHeader] ?? null;
        if (!red_addon_public_mutation_valid_opaque_token($csrfToken)) {
            return red_addon_public_mutation_http_request_result('csrf_invalid');
        }
        $idempotencyHeader = strtolower(
            red_addon_public_mutation_http_request_idempotency_header_name()
        );
        $idempotencyKey = $headerValues[$idempotencyHeader] ?? null;
        if (!red_addon_public_mutation_valid_opaque_token($idempotencyKey)) {
            return red_addon_public_mutation_http_request_result(
                'idempotency_invalid'
            );
        }
        return [
            'valid' => true,
            'method' => 'POST',
            'path' => $contract['path'],
            'origin' => $trustedOrigin,
            'body' => $body,
            'csrfToken' => $csrfToken,
            'idempotencyKey' => $idempotencyKey,
            'subjectToken' => $subjectToken,
            'reason' => 'normalized',
        ];
    }
}

?>
