<?php
/** Read-only C4C3A disposable state check for one authorization nonce. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (count($argv) !== 2 || preg_match('/\A[a-f0-9]{64}\z/D', $argv[1]) !== 1) {
    fwrite(STDERR, "Usage: state-check NONCE_SHA256\n");
    exit(64);
}
$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
if (preg_match('/\Aredcms_payment_adapter_db_c4c3a_[A-Za-z0-9_]+\z/D', (string) DBNAME) !== 1) {
    fwrite(STDERR, "C4C3A state check refused non-disposable database.\n");
    exit(65);
}
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$nonce = mysqli_real_escape_string($connection, $argv[1]);
$queries = [
    "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
     WHERE PackageID='redcms.store-lite-wompi'
       AND ActionID IN (
         'wompi-merchant-read-provider-start.{$nonce}',
         'wompi-merchant-read-provider-result.{$nonce}'
       )",
    "SELECT COUNT(*) FROM RED_Addon_Activity_Log
     WHERE PackageID='redcms.store-lite-wompi'
       AND DetailCode LIKE 'wompi_merchant_read_provider_double_%'",
    'SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Payment_Attempts',
    'SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Event_Receipts',
];
$counts = [];
foreach ($queries as $sql) {
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    if (!is_array($row)) {
        $db->close();
        fwrite(STDERR, "Could not read C4C3A state.\n");
        exit(1);
    }
    $counts[] = (string) $row[0];
}
$db->close();
echo implode(':', $counts) . "\n";

?>
