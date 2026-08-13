<?php
/**
 * Server-local Owner-authorized add-on upgrade and failure resume.
 *
 * Dry-run is the default. Apply requires a separately verified backup and
 * exact current/target evidence. Upgrade never loads package runtime code and
 * always finishes installed-disabled.
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
require_once $projectRoot . '/includes/addon_upgrade_helpers.php';

function red_addon_upgrade_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-addon-upgrade.php --package=vendor.package --actor-admin=ID [--resume-failed]\n" .
        "  php scripts/admin-addon-upgrade.php --package=vendor.package --actor-admin=ID \\\n" .
        "    --confirm-database=NAME --confirm-package=vendor.package \\\n" .
        "    --confirm-current-version=X.Y.Z --confirm-target-version=X.Y.Z \\\n" .
        "    --confirm-plan-sha256=SHA256 --confirm-backup-sha256=SHA256 \\\n" .
        "    --confirm-state=installed_disabled [--resume-failed] --apply\n"
    );
}

$options = [
    'package' => '',
    'actorAdmin' => 0,
    'confirmDatabase' => '',
    'confirmPackage' => '',
    'confirmCurrentVersion' => '',
    'confirmTargetVersion' => '',
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
        $options['actorAdmin'] = (int) substr($argument, strlen('--actor-admin='));
    } elseif (str_starts_with($argument, '--confirm-database=')) {
        $options['confirmDatabase'] = substr($argument, strlen('--confirm-database='));
    } elseif (str_starts_with($argument, '--confirm-package=')) {
        $options['confirmPackage'] = substr($argument, strlen('--confirm-package='));
    } elseif (str_starts_with($argument, '--confirm-current-version=')) {
        $options['confirmCurrentVersion'] = substr($argument, strlen('--confirm-current-version='));
    } elseif (str_starts_with($argument, '--confirm-target-version=')) {
        $options['confirmTargetVersion'] = substr($argument, strlen('--confirm-target-version='));
    } elseif (str_starts_with($argument, '--confirm-plan-sha256=')) {
        $options['confirmPlanSha256'] = substr($argument, strlen('--confirm-plan-sha256='));
    } elseif (str_starts_with($argument, '--confirm-backup-sha256=')) {
        $options['confirmBackupSha256'] = substr($argument, strlen('--confirm-backup-sha256='));
    } elseif (str_starts_with($argument, '--confirm-state=')) {
        $options['confirmState'] = substr($argument, strlen('--confirm-state='));
    } else {
        red_addon_upgrade_cli_usage();
        exit(64);
    }
}

if (!red_addon_valid_package_id($options['package'])
    || $options['actorAdmin'] <= 0
) {
    red_addon_upgrade_cli_usage();
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
if (empty($catalog['valid']) || !isset($catalog['packages'][$options['package']])) {
    fwrite(STDERR, "Package discovery, trust, or dependency validation failed.\n");
    $db->close();
    exit(65);
}
$plan = red_addon_upgrade_plan(
    $connection,
    $catalog['packages'][$options['package']],
    $options['actorAdmin'],
    $options['resume'],
    $catalog
);
if (empty($plan['valid'])) {
    fwrite(STDERR, 'Upgrade plan refused: ' . implode(', ', $plan['errors']) . PHP_EOL);
    $db->close();
    exit(65);
}

printf("Database: %s\n", $plan['database']);
printf("Package: %s\n", $plan['packageId']);
printf("Current version/state: %s / %s\n", $plan['currentVersion'], $plan['currentState']);
printf("Target version: %s\n", $plan['targetVersion']);
printf("Resume: %s\n", $plan['resume'] ? 'yes' : 'no');
printf("Preserved migrations: %d\n", count($plan['appliedMigrations']));
printf("Pending migrations: %d\n", count($plan['pendingMigrations']));
foreach ($plan['pendingMigrations'] as $migrationId) {
    echo '  ' . $migrationId . PHP_EOL;
}
printf("Stored settings checked: %d\n", (int) ($plan['settingEvidence']['storedCount'] ?? 0));
echo "Completion state: installed_disabled\n";
echo "Runtime load: no\n";
echo 'Plan SHA-256: ' . $plan['planSha256'] . PHP_EOL;

if (!$options['apply']) {
    echo "DRY RUN: no database changes or package code execution occurred.\n";
    echo "Rehearse the upgrade against a disposable restored database and retain its evidence.\n";
    $db->close();
    exit(0);
}

$acceptedState = $plan['resume']
    ? in_array($options['confirmState'], ['upgrading', 'upgrade_failed'], true)
    : $options['confirmState'] === 'installed_disabled';
if ($options['confirmDatabase'] !== $plan['database']
    || $options['confirmPackage'] !== $plan['packageId']
    || $options['confirmCurrentVersion'] !== $plan['currentVersion']
    || $options['confirmTargetVersion'] !== $plan['targetVersion']
    || !hash_equals($plan['planSha256'], $options['confirmPlanSha256'])
    || !red_addon_valid_sha256($options['confirmBackupSha256'])
    || hash_equals(str_repeat('0', 64), $options['confirmBackupSha256'])
    || !$acceptedState
) {
    fwrite(STDERR, "Apply requires exact database, package, current/target versions, state, plan, and nonzero backup confirmations.\n");
    $db->close();
    exit(64);
}

$result = red_addon_upgrade_package(
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
        'Upgrade failed closed: ' . $result['status'] .
        ($result['failedMigration'] !== ''
            ? '; migration=' . $result['failedMigration']
            : '') . PHP_EOL
    );
    exit(1);
}
printf(
    "Upgraded %s from %s to %s with %d newly applied migration(s); package remains disabled and unloaded.\n",
    $result['packageId'],
    $result['currentVersion'],
    $result['targetVersion'],
    count($result['appliedMigrations'])
);
exit(0);

?>
