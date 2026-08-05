<?php
/**
 * Core-only emitter for the closed public-mutation response contract.
 *
 * This helper can emit only an already-valid fixed core response envelope for
 * a future dispatcher. It does not accept an HTTP request, read request,
 * cookie, or session globals, access a database, bootstrap runtime state,
 * load package code, issue browser evidence, select or claim a route, or
 * change lifecycle, enablement, Store Lite, or client state. It is not wired
 * into index.php. A later core-owned dispatcher must return immediately after
 * it calls this helper.
 */

require_once __DIR__ . '/addon_public_mutation_response_helpers.php';

if (!function_exists('red_addon_public_mutation_response_emitter_valid')) {
    /**
     * Keeps the emitter closed even if the pure response contract expands.
     */
    function red_addon_public_mutation_response_emitter_valid($response)
    {
        if (!red_addon_public_mutation_response_valid($response)
            || !in_array(
                $response['httpStatus'],
                [200, 400, 405, 409, 429, 503],
                true
            )
        ) {
            return false;
        }

        $expectedHeaders = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Length' => (string) strlen($response['body']),
        ];
        if ($response['httpStatus'] === 405) {
            $expectedHeaders['Allow'] = 'POST';
        }

        return $response['headers'] === $expectedHeaders;
    }
}

if (!function_exists('red_addon_public_mutation_response_emit')) {
    /**
     * Emits one exact closed core response after a future dispatcher finishes.
     */
    function red_addon_public_mutation_response_emit($response)
    {
        if (!red_addon_public_mutation_response_emitter_valid($response)) {
            throw new InvalidArgumentException(
                'Public-mutation response envelope is invalid.'
            );
        }
        if (headers_sent()) {
            throw new RuntimeException(
                'Public-mutation response headers were sent prematurely.'
            );
        }

        header_remove();
        http_response_code($response['httpStatus']);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $response['body'];
    }
}

?>
