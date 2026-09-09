<?php
/** Server-local, counts-only readiness audit; conversion requires explicit confirmations. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/runtime_config_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_password_helpers.php';
$apply = false; $confirmed = ''; $plaintextConfirmed = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') $apply = true;
    elseif ($arg === '--confirm-legacy-plaintext') $plaintextConfirmed = true;
    elseif (str_starts_with($arg, '--confirm-database=')) $confirmed = substr($arg, 19);
    elseif ($arg === '--help') { echo "Audit (default): admin-password-migration.php\nConvert: --apply --confirm-database=EXACT_NAME --confirm-legacy-plaintext\nPrints counts only. Unrecognized hash-like or empty values require a password reset.\n"; exit; }
    else { fwrite(STDERR, "Unknown argument.\n"); exit(64); }
}
$db = null; $transaction = false; $stage = 'configuration';
try {
    $name = (string) red_server_config_value('DBNAME', ['RED_DB_NAME', 'DBNAME'], '');
    if ($apply && (!$plaintextConfirmed || $confirmed === '' || $confirmed !== $name)) throw new RuntimeException('confirmation');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $stage = 'connect';
    $db = mysqli_connect(
        red_server_config_value('DBHOST', ['RED_DB_HOST', 'DBHOST'], 'localhost'),
        red_server_config_value('DBUSER', ['RED_DB_USER', 'DBUSER'], ''),
        red_server_config_value('DBPASS', ['RED_DB_PASS', 'DBPASS'], ''), $name
    );
    mysqli_set_charset($db, 'utf8mb4');
    $stage = 'schema';
    $columnReady = red_admin_password_column_supports_hash($db);
    $engine = mysqli_fetch_row(mysqli_query($db, "SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin'"))[0] ?? '';
    if ($apply && (!$columnReady || strtoupper($engine) !== 'INNODB')) throw new RuntimeException('schema');
    if ($apply) { mysqli_begin_transaction($db); $transaction = true; }
    $stage = 'rows';
    $rows = mysqli_fetch_all(mysqli_query($db, 'SELECT RecordID, Password FROM RED_Admin' . ($apply ? ' FOR UPDATE' : '')), MYSQLI_ASSOC);
    $counts = ['hashed'=>0, 'legacy'=>0, 'resetRequired'=>0];
    foreach ($rows as $row) $counts[red_admin_password_migration_class((string) $row['Password'])]++;
    $converted = 0;
    if ($apply) {
        // All-or-nothing: ambiguous rows need an explicit owner reset, not double hashing.
        if ($counts['resetRequired'] !== 0) throw new RuntimeException('reset');
        $stmt = mysqli_prepare($db, 'UPDATE RED_Admin SET Password=? WHERE RecordID=? AND CAST(Password AS BINARY)=CAST(? AS BINARY)');
        foreach ($rows as $row) {
            if (red_admin_password_migration_class((string) $row['Password']) !== 'legacy') continue;
            $hash = password_hash($row['Password'], PASSWORD_DEFAULT); $id = (int) $row['RecordID']; $old = (string) $row['Password'];
            mysqli_stmt_bind_param($stmt, 'sis', $hash, $id, $old);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) !== 1) throw new RuntimeException('conflict');
            $converted++;
        }
        mysqli_stmt_close($stmt); mysqli_commit($db); $transaction = false;
        $counts['hashed'] += $converted; $counts['legacy'] -= $converted;
    }
    mysqli_close($db); $db = null;
    $ready = $columnReady && $counts['legacy'] === 0 && $counts['resetRequired'] === 0;
    echo json_encode(['mode'=>$apply?'apply':'audit','columnReady'=>$columnReady,'counts'=>$counts,'converted'=>$converted,'ready'=>$ready], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($ready ? 0 : 2);
} catch (Throwable $error) {
    if ($transaction && $db) mysqli_rollback($db);
    if ($db) mysqli_close($db);
    fwrite(STDERR, "Password operation refused or unavailable; verify confirmations, readiness counts, schema and private database access. No partial conversion committed.\n");
    fwrite(STDERR, "Stage: " . $stage . "; category: " . get_class($error) . "; code: " . (int) $error->getCode() . PHP_EOL);
    exit(1);
}
