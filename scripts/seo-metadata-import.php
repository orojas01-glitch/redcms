<?php
/**
 * Dry-run or atomically apply an explicit per-route SEO migration manifest.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/bootstrap.php';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/seo_metadata_migration_helpers.php';

function red_seo_import_usage()
{
    return "Usage:\n" .
        "  php scripts/seo-metadata-import.php --manifest=/absolute/manifest.json " .
        "--database=NAME --report=/absolute/report.json [--apply --confirm-database=NAME]\n";
}

function red_seo_import_cli_fail($message, $exitCode = 1)
{
    fwrite(STDERR, (string) $message . PHP_EOL);
    exit((int) $exitCode);
}

function red_seo_import_owner_contract($ownerType)
{
    $contracts = [
        'article' => ['table' => 'RED_Articles', 'alias' => 'Alias'],
        'section' => ['table' => 'RED_Sections', 'alias' => 'Sections'],
        'category' => ['table' => 'RED_Categories', 'alias' => 'Categories'],
        'subcategory' => ['table' => 'RED_SubCategories', 'alias' => 'SubCategories'],
    ];
    return $contracts[$ownerType] ?? null;
}

function red_seo_import_resolve_owner($connection, array $owner)
{
    $contract = red_seo_import_owner_contract($owner['type']);
    if (!is_array($contract)) {
        return null;
    }
    $sql = 'SELECT RecordID FROM `' . $contract['table'] . '` WHERE `' . $contract['alias'] .
        '`=? AND Language=?';
    $types = 'ss';
    $values = [$owner['alias'], $owner['language']];
    if ((int) $owner['recordId'] > 0) {
        $sql .= ' AND RecordID=?';
        $types .= 'i';
        $values[] = (int) $owner['recordId'];
    }
    $sql .= ' ORDER BY RecordID LIMIT 2';
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        return null;
    }
    $references = [$types];
    foreach ($values as $key => &$value) {
        $references[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $references);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return count($rows) === 1 ? (int) $rows[0]['RecordID'] : null;
}

function red_seo_import_write_report($path, array $report)
{
    $directory = dirname($path);
    if (!is_dir($directory)
        || !is_writable($directory)
        || is_link($directory)
        || is_link($path)
        || (file_exists($path) && !is_file($path))
    ) {
        red_seo_import_cli_fail('The report directory is unavailable or unsafe.', 66);
    }
    $json = json_encode(
        $report,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    if (file_put_contents($path, $json, LOCK_EX) === false) {
        red_seo_import_cli_fail('Could not write the migration report.', 74);
    }
}

$options = getopt('', [
    'manifest:',
    'database:',
    'report:',
    'apply',
    'confirm-database:',
    'help',
]);
if (isset($options['help'])) {
    echo red_seo_import_usage();
    exit(0);
}

$manifestPath = (string) ($options['manifest'] ?? '');
$targetDatabase = trim((string) ($options['database'] ?? ''));
$reportPath = (string) ($options['report'] ?? '');
$apply = isset($options['apply']);
$confirmedDatabase = trim((string) ($options['confirm-database'] ?? ''));
if ($manifestPath === ''
    || $manifestPath[0] !== '/'
    || !is_file($manifestPath)
    || is_link($manifestPath)
    || $reportPath === ''
    || $reportPath[0] !== '/'
    || preg_match('/\A[A-Za-z0-9_]+\z/', $targetDatabase) !== 1
    || strlen($targetDatabase) > 64
) {
    red_seo_import_cli_fail(red_seo_import_usage(), 64);
}
if ($targetDatabase === DBNAME) {
    red_seo_import_cli_fail('Refusing the configured primary database.', 65);
}
if ($apply && $confirmedDatabase !== $targetDatabase) {
    red_seo_import_cli_fail('Apply requires --confirm-database to match the exact target.', 65);
}
if (!$apply && $confirmedDatabase !== '') {
    red_seo_import_cli_fail('--confirm-database is valid only with --apply.', 64);
}

try {
    $manifestJson = file_get_contents($manifestPath);
    if (!is_string($manifestJson) || $manifestJson === '') {
        red_seo_import_cli_fail('The migration manifest is unreadable.', 66);
    }
    $manifest = red_seo_import_manifest($manifestJson);
    $db = new connection(DBHOST, DBUSER, DBPASS, $targetDatabase);
    $connection = $db->connection;
    if (!red_seo_table_available($connection)) {
        red_seo_import_cli_fail('The target database does not contain RED_Page_SEO.', 65);
    }

    $routes = [];
    $ready = true;
    foreach ($manifest['entries'] as $entry) {
        $recordId = red_seo_import_resolve_owner($connection, $entry['owner']);
        $existing = $recordId
            ? red_seo_metadata_row($connection, $entry['owner']['type'], $recordId)
            : null;
        $action = 'missing-owner';
        if ($recordId) {
            if ($existing === null) {
                $action = 'create';
            } elseif (red_seo_import_values_equal($existing, $entry['metadata'])) {
                $action = 'unchanged';
            } else {
                $action = 'conflict';
            }
        }
        if (in_array($action, ['missing-owner', 'conflict'], true)) {
            $ready = false;
        }
        $routes[] = [
            'source' => $entry['source'],
            'routePath' => $entry['routePath'],
            'owner' => $entry['owner'],
            'resolvedRecordId' => $recordId,
            'action' => $action,
            'importedFields' => array_values(array_keys(array_filter(
                $entry['metadata'],
                static function ($value) {
                    return trim(red_seo_scalar($value)) !== '';
                }
            ))),
            'decisions' => $entry['decisions'],
        ];
    }

    $applied = false;
    if ($apply) {
        if (!$ready) {
            red_seo_import_cli_fail('Apply refused because the dry-run found missing owners or conflicts.', 65);
        }
        if (!mysqli_begin_transaction($connection)) {
            red_seo_import_cli_fail('Could not begin the SEO import transaction.', 1);
        }
        try {
            foreach ($manifest['entries'] as $index => $entry) {
                $recordId = (int) $routes[$index]['resolvedRecordId'];
                if (!red_seo_save_metadata(
                    $connection,
                    $entry['owner']['type'],
                    $recordId,
                    $entry['metadata'],
                    0
                )) {
                    throw new RuntimeException('Could not save route ' . $entry['routePath'] . '.');
                }
            }
            if (!mysqli_commit($connection)) {
                throw new RuntimeException('Could not commit the SEO import transaction.');
            }
            $applied = true;
        } catch (Throwable $exception) {
            mysqli_rollback($connection);
            throw $exception;
        }
        foreach ($manifest['entries'] as $index => $entry) {
            $stored = red_seo_metadata_row(
                $connection,
                $entry['owner']['type'],
                (int) $routes[$index]['resolvedRecordId']
            );
            if (!is_array($stored) || !red_seo_import_values_equal($stored, $entry['metadata'])) {
                throw new RuntimeException('Post-apply verification failed for ' . $entry['routePath'] . '.');
            }
            $routes[$index]['action'] = 'applied';
        }
    }

    $counts = red_seo_import_decision_counts($manifest['entries']);
    $summary = array_merge([
        'routes' => count($routes),
        'ready' => count(array_filter($routes, static function ($route) {
            return in_array($route['action'], ['create', 'unchanged', 'applied'], true);
        })),
        'missingOwners' => count(array_filter($routes, static function ($route) {
            return $route['action'] === 'missing-owner';
        })),
        'conflicts' => count(array_filter($routes, static function ($route) {
            return $route['action'] === 'conflict';
        })),
    ], $counts);
    $report = [
        'schemaVersion' => 1,
        'migrationId' => $manifest['migrationId'],
        'manifestSha256' => hash('sha256', $manifestJson),
        'targetDatabase' => $targetDatabase,
        'mode' => $apply ? 'apply' : 'dry-run',
        'status' => $ready ? ($applied ? 'applied' : 'ready') : 'blocked',
        'summary' => $summary,
        'routes' => $routes,
    ];
    red_seo_import_write_report($reportPath, $report);
    $db->close();

    echo json_encode(
        [
            'status' => $report['status'],
            'mode' => $report['mode'],
            'summary' => $summary,
            'report' => $reportPath,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) . PHP_EOL;
    exit($ready ? 0 : 1);
} catch (Throwable $exception) {
    red_seo_import_cli_fail('SEO metadata import failed: ' . $exception->getMessage(), 1);
}
?>
