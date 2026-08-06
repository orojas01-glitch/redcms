<?php
/**
 * Disposable custom-binary proof endpoint.
 *
 * This file exists only inside the temporary Docker proof image. It proves
 * that the custom Caddy module's signed capture reaches the real unlinked PHP
 * verifier and preserves the body. It is not part of the CMS front controller
 * or a package route.
 */

if (($_SERVER['REQUEST_URI'] ?? '') === '/healthz') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/includes/addon_public_mutation_frankenphp_ingress_helpers.php';

$capture = red_addon_public_mutation_frankenphp_ingress_capture_current();
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (!is_array($capture) || ($capture['available'] ?? false) !== true) {
    http_response_code(400);
    echo 'refused:' . (is_array($capture) ? ($capture['reason'] ?? 'unknown') : 'unknown') . "\n";
    exit;
}

echo "captured\n";
echo 'method=' . $capture['method'] . "\n";
echo 'target=' . $capture['requestTarget'] . "\n";
echo 'body_sha256=' . hash('sha256', $capture['body']) . "\n";
echo 'header_count=' . count($capture['headers']) . "\n";
