<?php
/**
 * Read-only comparison of validated package files and per-client registry.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_registry_helpers.php';

$packageId = '';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--package=')) {
        $packageId = substr($argument, strlen('--package='));
    } elseif ($argument !== '--all') {
        fwrite(
            STDERR,
            "Usage: php scripts/addon-registry-status.php --all|--package=vendor.package\n"
        );
        exit(64);
    }
}
if ($packageId !== '' && !red_addon_valid_package_id($packageId)) {
    fwrite(STDERR, "Package id is invalid.\n");
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
if (!red_addon_registry_storage_available($connection)) {
    fwrite(STDERR, "Add-on registry storage is unavailable. Apply pending migrations first.\n");
    $db->close();
    exit(65);
}

$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$report = red_addon_registry_catalog_report($connection, $catalog);
printf("Database: %s\n", DBNAME);
foreach ($report['errors'] as $error) {
    echo 'ERROR: ' . $error . PHP_EOL;
}
foreach ($report['warnings'] as $warning) {
    echo 'WARNING: ' . $warning . PHP_EOL;
}

$shown = 0;
foreach ($report['packages'] as $id => $packageReport) {
    if ($packageId !== '' && $id !== $packageId) {
        continue;
    }
    $shown++;
    printf(
        "%s: %s; lifecycle=%s; loadable=no\n",
        $id,
        $packageReport['status'],
        $packageReport['lifecycleState'] !== ''
            ? $packageReport['lifecycleState']
            : 'not-installed'
    );
    foreach ($packageReport['pendingMigrations'] as $migrationId) {
        echo '  pending migration: ' . $migrationId . PHP_EOL;
    }
    foreach ($packageReport['errors'] as $error) {
        echo '  ERROR: ' . $error . PHP_EOL;
    }
    foreach ($packageReport['warnings'] as $warning) {
        echo '  WARNING: ' . $warning . PHP_EOL;
    }
}

if ($packageId !== '' && $shown === 0) {
    fwrite(STDERR, "Package was not discovered or recorded: " . $packageId . "\n");
    $db->close();
    exit(66);
}
if ($packageId === '' && $shown === 0) {
    echo "No discovered or recorded add-on packages.\n";
}

$db->close();
exit(!empty($report['valid']) ? 0 : 1);
