<?php
/**
 * Server-local Owner-authorized installation of one validated add-on package.
 *
 * Dry-run is the default. Successful installation always ends disabled and
 * this command never includes addon.php or enables runtime code.
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
require_once $projectRoot . '/includes/addon_install_helpers.php';

function red_addon_install_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-addon-install.php --package=vendor.package --actor-admin=ID [--resume-failed]\n" .
        "  php scripts/admin-addon-install.php --package=vendor.package --actor-admin=ID \\\n" .
        "    --confirm-database=NAME --confirm-package=vendor.package \\\n" .
        "    --confirm-version=X.Y.Z --confirm-plan-sha256=SHA256 \\\n" .
        "    --confirm-backup-sha256=SHA256 --confirm-state=installed_disabled \\\n" .
        "    [--resume-failed] --apply\n"
    );
}

$options = [
    'package' => '',
    'actorAdmin' => 0,
    'confirmDatabase' => '',
    'confirmPackage' => '',
    'confirmVersion' => '',
    'confirmPlanSha256' => '',
    'confirmBackupSha256' => '',
    'confirmState' => '',
    'resume' => false,
    'apply' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--resume-failed') {
        $options['resume'] = true;
    } elseif ($argument === '--apply') {
        $options['apply'] = true;
    } elseif (str_starts_with($argument, '--package=')) {
        $options['package'] = substr($argument, strlen('--package='));
    } elseif (str_starts_with($argument, '--actor-admin=')) {
        $options['actorAdmin'] = (int) substr(
            $argument,
            strlen('--actor-admin=')
        );
    } elseif (str_starts_with($argument, '--confirm-database=')) {
        $options['confirmDatabase'] = substr(
            $argument,
            strlen('--confirm-database=')
        );
    } elseif (str_starts_with($argument, '--confirm-package=')) {
        $options['confirmPackage'] = substr(
            $argument,
            strlen('--confirm-package=')
        );
    } elseif (str_starts_with($argument, '--confirm-version=')) {
        $options['confirmVersion'] = substr(
            $argument,
            strlen('--confirm-version=')
        );
    } elseif (str_starts_with($argument, '--confirm-plan-sha256=')) {
        $options['confirmPlanSha256'] = substr(
            $argument,
            strlen('--confirm-plan-sha256=')
        );
    } elseif (str_starts_with($argument, '--confirm-backup-sha256=')) {
        $options['confirmBackupSha256'] = substr(
            $argument,
            strlen('--confirm-backup-sha256=')
        );
    } elseif (str_starts_with($argument, '--confirm-state=')) {
        $options['confirmState'] = substr(
            $argument,
            strlen('--confirm-state=')
        );
    } else {
        red_addon_install_cli_usage();
        exit(64);
    }
}

if (!red_addon_valid_package_id($options['package'])
    || $options['actorAdmin'] <= 0
) {
    red_addon_install_cli_usage();
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
if (!red_addon_install_storage_available($connection)) {
    fwrite(STDERR, "Add-on installation storage is unavailable. Apply pending migrations first.\n");
    $db->close();
    exit(65);
}

$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
if (empty($catalog['valid'])
    || !isset($catalog['packages'][$options['package']])
) {
    fwrite(STDERR, "Package discovery, trust, or dependency validation failed.\n");
    foreach ($catalog['errors'] ?? [] as $error) {
        fwrite(STDERR, 'ERROR: ' . $error . PHP_EOL);
    }
    foreach ($catalog['dependency']['errors'] ?? [] as $error) {
        fwrite(STDERR, 'ERROR: ' . $error . PHP_EOL);
    }
    $db->close();
    exit(65);
}

$package = $catalog['packages'][$options['package']];
$plan = red_addon_install_plan(
    $connection,
    $package,
    $options['actorAdmin'],
    $options['resume'],
    $catalog
);
if (empty($plan['valid'])) {
    fwrite(
        STDERR,
        'Installation plan refused: ' .
        implode(', ', $plan['errors']) .
        PHP_EOL
    );
    $db->close();
    exit(65);
}

printf("Database: %s\n", $plan['database']);
printf("Package: %s %s\n", $plan['packageId'], $plan['version']);
printf("Actor administrator: %d\n", $options['actorAdmin']);
printf("Resume: %s\n", $plan['resume'] ? 'yes' : 'no');
printf(
    "Required enabled dependencies: %d\n",
    count($plan['requiredDependencies'])
);
printf(
    "Applied migrations already recorded: %d\n",
    count($plan['appliedMigrations'])
);
printf("Pending migrations: %d\n", count($plan['pendingMigrations']));
foreach ($plan['pendingMigrations'] as $migrationId) {
    echo '  ' . $migrationId . PHP_EOL;
}
echo 'Completion state: installed_disabled' . PHP_EOL;
echo 'Runtime load: no' . PHP_EOL;
echo 'Plan SHA-256: ' . $plan['planSha256'] . PHP_EOL;

if (!$options['apply']) {
    echo "DRY RUN: no database changes or package code execution occurred.\n";
    echo "Test the migrations on a disposable restored database and retain its evidence.\n";
    echo "Provide the exact confirmations, backup SHA-256, plan SHA-256, and --apply to install.\n";
    $db->close();
    exit(0);
}

if ($options['confirmDatabase'] !== $plan['database']
    || $options['confirmPackage'] !== $plan['packageId']
    || $options['confirmVersion'] !== $plan['version']
    || !hash_equals(
        $plan['planSha256'],
        $options['confirmPlanSha256']
    )
    || !red_addon_valid_sha256($options['confirmBackupSha256'])
    || hash_equals(str_repeat('0', 64), $options['confirmBackupSha256'])
    || $options['confirmState'] !== 'installed_disabled'
) {
    fwrite(
        STDERR,
        "Apply requires exact database, package, version, plan, nonzero backup checksum, and disabled-state confirmations.\n"
    );
    $db->close();
    exit(64);
}

$result = red_addon_install_package(
    $connection,
    $options['package'],
    $projectRoot,
    $options['actorAdmin'],
    $options['confirmPlanSha256'],
    $options['resume']
);
$db->close();

if ($result['status'] !== 'installed_disabled') {
    fwrite(
        STDERR,
        'Installation failed closed: ' . $result['status'] .
        ($result['failedMigration'] !== ''
            ? '; migration=' . $result['failedMigration']
            : '') .
        PHP_EOL
    );
    exit(1);
}

printf(
    "Installed %s %s with %d newly applied migration(s); package remains disabled and unloaded.\n",
    $result['packageId'],
    $result['version'],
    count($result['appliedMigrations'])
);
exit(0);
