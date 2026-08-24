<?php
/** Emit one fresh C4C3A authorization plus a read-only baseline. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (count($argv) !== 3 || ($argv[1][0] ?? '') !== '/' || ($argv[2][0] ?? '') !== '/') {
    fwrite(STDERR, "Usage: fixture /absolute/evidence.json /absolute/meta.json\n");
    exit(64);
}
$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_wompi_merchant_read_durable_helpers.php';
if (preg_match('/\Aredcms_payment_adapter_db_c4c3a_[A-Za-z0-9_]+\z/D', (string) DBNAME) !== 1) {
    fwrite(STDERR, "C4C3A fixture refused non-disposable database.\n");
    exit(65);
}
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$actorId = 2147000995;
$now = time();
$authorization = red_addon_wompi_merchant_durable_authorize(
    $connection,
    $projectRoot,
    $actorId,
    str_repeat('8', 64),
    $now
);
$plan = red_addon_wompi_merchant_durable_plan(
    $connection,
    $projectRoot,
    $actorId,
    $authorization,
    $now
);
$auditQuery = mysqli_query(
    $connection,
    "SELECT COUNT(*) FROM RED_Addon_Activity_Log
     WHERE PackageID='redcms.store-lite-wompi'
       AND DetailCode LIKE 'wompi_merchant_read_provider_double_%'"
);
$auditRow = $auditQuery ? mysqli_fetch_row($auditQuery) : null;
if ($auditQuery) {
    mysqli_free_result($auditQuery);
}
$db->close();
if (empty($authorization['valid']) || empty($plan['ready']) || !is_array($auditRow)) {
    fwrite(STDERR, "C4C3A fixture could not produce fresh evidence.\n");
    exit(1);
}
$evidenceJson = json_encode($authorization, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$metaJson = json_encode([
    'authorizationNonceSha256' => $authorization['authorizationNonceSha256'],
    'baselineAuditCount' => (int) $auditRow[0],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($evidenceJson) || !is_string($metaJson)
    || file_put_contents($argv[1], $evidenceJson . "\n") === false
    || file_put_contents($argv[2], $metaJson . "\n") === false
) {
    fwrite(STDERR, "C4C3A fixture evidence write failed.\n");
    exit(1);
}
echo "C4C3A fresh operator evidence emitted.\n";

?>
