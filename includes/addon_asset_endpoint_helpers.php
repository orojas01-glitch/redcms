<?php
/**
 * Core-owned immutable add-on asset HTTP endpoint.
 *
 * This layer reruns the read-only delivery preflight for the current request,
 * then emits only exact checksum-verified CSS or JavaScript bytes. It never
 * loads add-on PHP, starts a session, injects document markup, or changes
 * package state.
 */

require_once __DIR__ . '/addon_asset_delivery_helpers.php';

if (!function_exists('red_addon_asset_delivery_http_result')) {
    function red_addon_asset_delivery_http_result($reason = 'not_matched')
    {
        return [
            'claimed' => false,
            'delivered' => false,
            'status' => 0,
            'headers' => [],
            'body' => '',
            'headOnly' => false,
            'reason' => is_string($reason) ? $reason : 'not_matched',
        ];
    }
}

if (!function_exists('red_addon_asset_delivery_max_bytes')) {
    function red_addon_asset_delivery_max_bytes()
    {
        return 4 * 1024 * 1024;
    }
}

if (!function_exists('red_addon_asset_delivery_http_error')) {
    function red_addon_asset_delivery_http_error($status, $method)
    {
        $status = in_array($status, [404, 405, 503], true) ? $status : 503;
        $body = $status === 404
            ? "Not found.\n"
            : ($status === 405
                ? "Method not allowed.\n"
                : "Service temporarily unavailable.\n");
        $headOnly = $method === 'HEAD';
        $headers = [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) strlen($body),
        ];
        if ($status === 405) {
            $headers['Allow'] = 'GET, HEAD';
        }

        return [
            'claimed' => true,
            'delivered' => false,
            'status' => $status,
            'headers' => $headers,
            'body' => $headOnly ? '' : $body,
            'headOnly' => $headOnly,
            'reason' => $status === 404
                ? 'not_found'
                : ($status === 405 ? 'method_not_allowed' : 'unavailable'),
        ];
    }
}

if (!function_exists('red_addon_asset_delivery_http_response')) {
    function red_addon_asset_delivery_http_response(array $preflight, $method)
    {
        $type = is_string($preflight['type'] ?? null)
            ? $preflight['type']
            : '';
        $contentType = is_string($preflight['contentType'] ?? null)
            ? $preflight['contentType']
            : '';
        $sha256 = is_string($preflight['sha256'] ?? null)
            ? $preflight['sha256']
            : '';
        $byteLength = $preflight['byteLength'] ?? null;
        $filePath = is_string($preflight['filePath'] ?? null)
            ? $preflight['filePath']
            : '';
        $expectedContentType = red_addon_asset_delivery_content_type($type);

        if (!red_addon_valid_sha256($sha256)
            || !is_int($byteLength)
            || $byteLength < 0
            || $byteLength > red_addon_asset_delivery_max_bytes()
            || $filePath === ''
            || $expectedContentType === ''
            || !hash_equals($expectedContentType, $contentType)
        ) {
            return red_addon_asset_delivery_http_error(404, $method);
        }

        $headOnly = $method === 'HEAD';
        $fileBytes = @file_get_contents($filePath);
        if (!is_string($fileBytes)
            || strlen($fileBytes) !== $byteLength
            || !hash_equals($sha256, hash('sha256', $fileBytes))
        ) {
            return red_addon_asset_delivery_http_error(404, $method);
        }

        return [
            'claimed' => true,
            'delivered' => true,
            'status' => 200,
            'headers' => [
                'Content-Type' => $contentType,
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'X-Content-Type-Options' => 'nosniff',
                'Accept-Ranges' => 'none',
                'Content-Length' => (string) $byteLength,
            ],
            'body' => $headOnly ? '' : $fileBytes,
            'headOnly' => $headOnly,
            'reason' => 'delivered',
        ];
    }
}

if (!function_exists('red_addon_asset_delivery_dispatch')) {
    function red_addon_asset_delivery_dispatch(
        $connection,
        $projectRoot,
        $method,
        $requestUri
    ) {
        $result = red_addon_asset_delivery_http_result();
        if (!red_addon_asset_delivery_claimed_uri($requestUri)) {
            return $result;
        }

        try {
            $preflight = red_addon_asset_delivery_preflight(
                $connection,
                $projectRoot,
                $requestUri
            );
        } catch (Throwable $throwable) {
            error_log('RED-CMS add-on asset delivery preflight failed.');
            return red_addon_asset_delivery_http_error(503, $method);
        }

        if (!is_array($preflight) || empty($preflight['claimed'])) {
            return $result;
        }
        if (empty($preflight['resolved'])) {
            return red_addon_asset_delivery_http_error(
                ($preflight['reason'] ?? '') === 'registry_unavailable'
                    ? 503
                    : 404,
                $method
            );
        }
        if (!is_string($method)
            || !in_array($method, ['GET', 'HEAD'], true)
        ) {
            return red_addon_asset_delivery_http_error(405, $method);
        }

        try {
            return red_addon_asset_delivery_http_response($preflight, $method);
        } catch (Throwable $throwable) {
            error_log('RED-CMS add-on asset delivery read failed.');
            return red_addon_asset_delivery_http_error(503, $method);
        }
    }
}

if (!function_exists('red_addon_asset_delivery_emit')) {
    function red_addon_asset_delivery_emit(array $result)
    {
        $allowedHeaders = [
            'Content-Type', 'Cache-Control', 'X-Content-Type-Options',
            'Accept-Ranges', 'Content-Length', 'Allow',
        ];
        $contentLength = $result['headers']['Content-Length'] ?? null;
        if (empty($result['claimed'])
            || !is_bool($result['delivered'] ?? null)
            || !is_int($result['status'] ?? null)
            || !in_array($result['status'], [200, 404, 405, 503], true)
            || !is_array($result['headers'] ?? null)
            || !is_string($result['body'] ?? null)
            || !is_bool($result['headOnly'] ?? null)
            || !is_string($contentLength)
            || preg_match('/\A(?:0|[1-9][0-9]{0,7})\z/D', $contentLength) !== 1
        ) {
            throw new InvalidArgumentException(
                'Add-on asset delivery response is invalid.'
            );
        }
        foreach ($result['headers'] as $name => $value) {
            if (!is_string($name)
                || !in_array($name, $allowedHeaders, true)
                || !is_string($value)
                || preg_match('/[\r\n]/', $value) === 1
            ) {
                throw new InvalidArgumentException(
                    'Add-on asset delivery headers are invalid.'
                );
            }
        }
        if (empty($result['headOnly'])
            && (int) $contentLength !== strlen($result['body'])
        ) {
            throw new InvalidArgumentException(
                'Add-on asset delivery body length is invalid.'
            );
        }
        if (!empty($result['headOnly']) && $result['body'] !== '') {
            throw new InvalidArgumentException(
                'Add-on asset delivery HEAD response has a body.'
            );
        }
        if (headers_sent()) {
            throw new RuntimeException(
                'Add-on asset delivery headers were sent prematurely.'
            );
        }

        header_remove();
        http_response_code($result['status']);
        foreach ($result['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }
        if (empty($result['headOnly'])) {
            echo $result['body'];
        }
    }
}

?>
