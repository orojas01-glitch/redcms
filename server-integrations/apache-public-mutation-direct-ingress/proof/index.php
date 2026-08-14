<?php
/**
 * Disposable Apache/FastCGI direct-ingress proof endpoint.
 *
 * This file is staged only into the temporary server root. It is not linked
 * from RED-CMS, does not open a database, and never invokes package code.
 */

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$requestTarget = $_SERVER['REQUEST_URI'] ?? '';
if ($requestTarget === '/healthz') {
    http_response_code(204);
    exit;
}

if ($requestTarget === '/favicon.ico'
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
) {
    http_response_code(204);
    exit;
}

if (($requestTarget === '/' || $requestTarget === '/index.php')
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>RED-CMS Apache ingress proof</title>';
    echo '<main><h1>Apache direct ingress proof</h1>';
    echo '<p>HTTPS page evidence only. The dispatcher is not linked.</p></main>';
    exit;
}

require_once __DIR__
    . '/includes/addon_public_mutation_direct_ingress_helpers.php';

$capture = red_addon_public_mutation_direct_ingress_capture_current();
header('Content-Type: text/plain; charset=utf-8');

if (!is_array($capture) || ($capture['available'] ?? false) !== true) {
    http_response_code(400);
    echo 'refused:'
        . (is_array($capture) ? ($capture['reason'] ?? 'unknown') : 'unknown')
        . "\n";
    exit;
}

echo "captured\n";
echo 'method=' . $capture['method'] . "\n";
echo 'target=' . $capture['requestTarget'] . "\n";
echo 'body_sha256=' . hash('sha256', $capture['body']) . "\n";
echo 'header_count=' . count($capture['headers']) . "\n";
echo 'https=' . (string) ($_SERVER['HTTPS'] ?? '') . "\n";
echo 'sapi=' . PHP_SAPI . "\n";

?>
