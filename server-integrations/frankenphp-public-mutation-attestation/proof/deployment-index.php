<?php
/**
 * Disposable HTTPS deployment-rehearsal page.
 *
 * This file is staged only into a temporary Docker image. It deliberately
 * renders a static 200 page and a health probe; it is not RED-CMS index.php,
 * a dispatcher route, a package, or a client installation.
 */

ini_set('display_errors', '0');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/healthz') {
    http_response_code(204);
    exit;
}

if ($path !== '/') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo "not found\n";
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
http_response_code(200);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="data:,">
  <title>RED-CMS deployment rehearsal</title>
</head>
<body>
  <main>
    <h1>RED-CMS deployment rehearsal</h1>
    <p data-red-deployment-rehearsal="status">HTTPS boundary is active.</p>
    <p data-red-dispatcher="state">Dispatcher remains unlinked.</p>
  </main>
</body>
</html>
