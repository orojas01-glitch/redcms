<?php
/**
 * Optional Caddy/FrankenPHP ingress verifier for a future public-mutation
 * dispatcher.
 *
 * A separately built operator-owned Caddy handler can replace untrusted
 * request headers with one signed, bounded capture. This helper verifies that
 * capture before it ever reads php://input, then hands only explicit facts to
 * the existing non-routable server request-facts adapter. It is not included
 * by index.php and does not select a route, emit a response, issue a cookie,
 * access a database/runtime/package, or change client state.
 */

require_once __DIR__ . '/addon_public_mutation_server_request_helpers.php';

if (!function_exists('red_addon_public_mutation_frankenphp_ingress_key')) {
    /**
     * Resolves only a process environment value shared with the Caddy handler.
     * config.local.php is deliberately not a fallback: Caddy must never read a
     * PHP-local file to acquire this attestation key.
     */
    function red_addon_public_mutation_frankenphp_ingress_key()
    {
        $configuredKey = getenv('RED_PUBLIC_MUTATION_INGRESS_HMAC_KEY');
        if (!is_string($configuredKey)
            || preg_match('/\A[a-f0-9]{64}\z/D', $configuredKey) !== 1) {
            return '';
        }
        $key = hex2bin($configuredKey);
        return is_string($key) && strlen($key) === 32 ? $key : '';
    }
}

if (!function_exists('red_addon_public_mutation_frankenphp_ingress_result')) {
    function red_addon_public_mutation_frankenphp_ingress_result($reason)
    {
        return red_addon_public_mutation_server_request_result($reason);
    }
}

if (!function_exists('red_addon_public_mutation_frankenphp_ingress_names')) {
    function red_addon_public_mutation_frankenphp_ingress_names()
    {
        return [
            'Origin',
            'Content-Type',
            'Cookie',
            red_addon_public_mutation_http_request_csrf_header_name(),
            red_addon_public_mutation_http_request_idempotency_header_name(),
        ];
    }
}

if (!function_exists('red_addon_public_mutation_frankenphp_ingress_base64url_decode')) {
    function red_addon_public_mutation_frankenphp_ingress_base64url_decode(
        $encoded
    ) {
        if (!is_string($encoded)
            || $encoded === ''
            || strlen($encoded) > 12288
            || preg_match('/\A[A-Za-z0-9_-]+\z/D', $encoded) !== 1
            || strlen($encoded) % 4 === 1) {
            return null;
        }
        $padded = strtr($encoded, '-_', '+/')
            . str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $decoded = base64_decode($padded, true);
        if (!is_string($decoded)
            || rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=')
                !== $encoded) {
            return null;
        }
        return $decoded;
    }
}

if (!function_exists('red_addon_public_mutation_frankenphp_ingress_payload')) {
    /**
     * Decodes a valid signed Caddy capture into fixed server facts only.
     */
    function red_addon_public_mutation_frankenphp_ingress_payload(
        $captureHeader,
        $signatureHeader,
        $binaryKey
    ) {
        if (!is_string($captureHeader)
            || strlen($captureHeader) < 4
            || strlen($captureHeader) > 12291
            || !str_starts_with($captureHeader, 'v1.')
            || !is_string($signatureHeader)
            || preg_match('/\Asha256=[a-f0-9]{64}\z/D', $signatureHeader)
                !== 1
            || !is_string($binaryKey)
            || strlen($binaryKey) !== 32) {
            return null;
        }
        $expectedSignature = 'sha256=' . hash_hmac(
            'sha256',
            $captureHeader,
            $binaryKey
        );
        if (!hash_equals($expectedSignature, $signatureHeader)) {
            return null;
        }
        $json = red_addon_public_mutation_frankenphp_ingress_base64url_decode(
            substr($captureHeader, 3)
        );
        if (!is_string($json)) {
            return null;
        }
        try {
            $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            return null;
        }
        if (!is_array($payload)
            || array_keys($payload) !== [
                'v',
                'method',
                'target',
                'bodyBytes',
                'bodySha256',
                'headers',
            ]
            || $payload['v'] !== 1
            || $payload['method'] !== 'POST'
            || !is_string($payload['target'])
            || strlen($payload['target']) < 8
            || strlen($payload['target']) > 2048
            || !str_starts_with($payload['target'], '/addons/')
            || str_contains($payload['target'], '#')
            || preg_match('/[\x00-\x20\x7F]/', $payload['target']) === 1
            || !is_int($payload['bodyBytes'])
            || $payload['bodyBytes'] < 0
            || $payload['bodyBytes'] > 8192
            || !is_string($payload['bodySha256'])
            || preg_match('/\A[a-f0-9]{64}\z/D', $payload['bodySha256'])
                !== 1
            || !is_array($payload['headers'])
            || !array_is_list($payload['headers'])
            || count($payload['headers']) > 5) {
            return null;
        }
        $expectedNames = red_addon_public_mutation_frankenphp_ingress_names();
        $previousPosition = -1;
        $totalHeaderBytes = 0;
        foreach ($payload['headers'] as $header) {
            if (!is_array($header)
                || array_keys($header) !== ['name', 'value']
                || !is_string($header['name'])
                || !is_string($header['value'])) {
                return null;
            }
            $position = array_search($header['name'], $expectedNames, true);
            if ($position === false
                || $position <= $previousPosition
                || strlen($header['value']) > 2048
                || preg_match('/[\x00-\x1F\x7F]/', $header['value']) === 1) {
                return null;
            }
            $totalHeaderBytes += strlen($header['name'])
                + strlen($header['value']) + 2;
            if ($totalHeaderBytes > 6144) {
                return null;
            }
            $previousPosition = $position;
        }
        return $payload;
    }
}

if (!function_exists('red_addon_public_mutation_frankenphp_ingress_capture')) {
    /**
     * Validates signed explicit facts without reading PHP request globals.
     */
    function red_addon_public_mutation_frankenphp_ingress_capture(
        $server,
        $body,
        $captureHeader,
        $signatureHeader,
        $binaryKey
    ) {
        $payload = red_addon_public_mutation_frankenphp_ingress_payload(
            $captureHeader,
            $signatureHeader,
            $binaryKey
        );
        if (!is_array($payload)) {
            return red_addon_public_mutation_frankenphp_ingress_result(
                'transport_unavailable'
            );
        }
        $method = is_array($server) ? ($server['REQUEST_METHOD'] ?? null) : null;
        $requestTarget = is_array($server)
            ? ($server['REQUEST_URI'] ?? null)
            : null;
        if (!is_string($method)
            || !is_string($requestTarget)
            || $method !== $payload['method']
            || $requestTarget !== $payload['target']
            || !is_string($body)
            || strlen($body) !== $payload['bodyBytes']
            || !hash_equals(
                $payload['bodySha256'],
                hash('sha256', $body)
            )) {
            return red_addon_public_mutation_frankenphp_ingress_result(
                'transport_invalid'
            );
        }
        return red_addon_public_mutation_server_request_capture(
            $method,
            $requestTarget,
            [
                'complete' => true,
                'headers' => $payload['headers'],
            ],
            $body
        );
    }
}

if (!function_exists('red_addon_public_mutation_frankenphp_ingress_capture_current')) {
    /**
     * The unlinked future bridge reads php://input only after a valid HMAC.
     */
    function red_addon_public_mutation_frankenphp_ingress_capture_current()
    {
        $binaryKey = red_addon_public_mutation_frankenphp_ingress_key();
        $captureHeader = $_SERVER['HTTP_X_RED_PUBLIC_MUTATION_CAPTURE'] ?? null;
        $signatureHeader = $_SERVER['HTTP_X_RED_PUBLIC_MUTATION_SIGNATURE']
            ?? null;
        $payload = red_addon_public_mutation_frankenphp_ingress_payload(
            $captureHeader,
            $signatureHeader,
            $binaryKey
        );
        if (!is_array($payload)) {
            return red_addon_public_mutation_frankenphp_ingress_result(
                'transport_unavailable'
            );
        }
        $server = [
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        ];
        if ($server['REQUEST_METHOD'] !== $payload['method']
            || $server['REQUEST_URI'] !== $payload['target']) {
            return red_addon_public_mutation_frankenphp_ingress_result(
                'transport_invalid'
            );
        }
        $body = file_get_contents('php://input');
        if (!is_string($body)) {
            return red_addon_public_mutation_frankenphp_ingress_result(
                'transport_unavailable'
            );
        }
        return red_addon_public_mutation_frankenphp_ingress_capture(
            $server,
            $body,
            $captureHeader,
            $signatureHeader,
            $binaryKey
        );
    }
}

?>
