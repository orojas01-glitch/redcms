<?php
/**
 * Server-local Owner-authorized atomic add-on disable transition.
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
require_once $projectRoot . '/includes/addon_disable_helpers.php';

function red_addon_disable_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-addon-disable.php " .
        "--package=vendor.package --actor-admin=ID\n" .
        "  php scripts/admin-addon-disable.php " .
        "--package=vendor.package --actor-admin=ID \\\n" .
        "    --confirm-database=NAME --confirm-package=vendor.package \\\n" .
        "    --confirm-version=X.Y.Z --confirm-plan-sha256=SHA256 \\\n" .
        "    --confirm-backup-sha256=SHA256 --confirm-state=enabled \\\n" .
        "    --apply\n"
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
    'apply' => false,
];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
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
        red_addon_disable_cli_usage();
        exit(64);
    }
}
if (!red_addon_valid_package_id($options['package'])
    || $options['actorAdmin'] <= 0
) {
    red_addon_disable_cli_usage();
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
if (!red_addon_install_storage_available($connection)) {
    fwrite(
        STDERR,
        "Add-on disablement storage is unavailable. " .
        "Apply pending core migrations first.\n"
    );
    $db->close();
    exit(65);
}
$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$package = $catalog['packages'][$options['package']] ?? null;
if (empty($catalog['valid']) || !is_array($package)) {
    fwrite(
        STDERR,
        "Package discovery, trust, or dependency validation failed.\n"
    );
    $db->close();
    exit(65);
}
$plan = red_addon_disable_transition_plan(
    $connection,
    $package,
    $options['actorAdmin'],
    $catalog
);
if (empty($plan['valid'])) {
    fwrite(
        STDERR,
        'Disablement plan refused: ' .
        implode(', ', $plan['errors']) .
        PHP_EOL
    );
    $db->close();
    exit(65);
}

printf("Database: %s\n", $plan['database']);
printf("Package: %s %s\n", $plan['packageId'], $plan['version']);
printf("Actor administrator: %d\n", $options['actorAdmin']);
printf("Current state: %s\n", $plan['currentState']);
printf("Proposed target state: %s\n", $plan['targetState']);
printf(
    "Currently enabled packages checked: %d\n",
    count($plan['enabledPackages'])
);
printf(
    "Enabled dependents requiring this package: %d\n",
    count($plan['enabledDependents'])
);
echo 'Blockers:' . PHP_EOL;
foreach ($plan['blockers'] as $blocker) {
    echo '  ' . json_encode(
        $blocker,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) . PHP_EOL;
}
echo 'Disable ready: ' .
    ($plan['transitionReady'] ? 'yes' : 'no') .
    PHP_EOL;
echo 'Package execution: no' . PHP_EOL;
echo 'Migration execution: no' . PHP_EOL;
echo 'Data deletion: no' . PHP_EOL;
echo 'Plan SHA-256: ' . $plan['planSha256'] . PHP_EOL;

if (empty($plan['transitionReady'])) {
    fwrite(
        STDERR,
        "Disablement is blocked while an enabled package depends on it.\n"
    );
    $db->close();
    exit(65);
}
if (!$options['apply']) {
    echo "DRY RUN: no database changes or package code execution occurred.\n";
    echo "Retain a verified backup and provide the exact confirmations with --apply.\n";
    $db->close();
    exit(0);
}

if ($options['confirmDatabase'] !== $plan['database']
    || $options['confirmPackage'] !== $plan['packageId']
    || $options['confirmVersion'] !== $plan['version']
    || !hash_equals($plan['planSha256'], $options['confirmPlanSha256'])
    || !red_addon_valid_sha256($options['confirmBackupSha256'])
    || hash_equals(str_repeat('0', 64), $options['confirmBackupSha256'])
    || $options['confirmState'] !== 'enabled'
) {
    fwrite(
        STDERR,
        "Apply requires exact database, package, version, plan, nonzero " .
        "backup checksum, and enabled-state confirmations.\n"
    );
    $db->close();
    exit(64);
}

$result = red_addon_disable_package(
    $connection,
    $options['package'],
    $projectRoot,
    $options['actorAdmin'],
    $options['confirmPlanSha256']
);
$db->close();
if ($result['status'] !== 'installed_disabled') {
    fwrite(
        STDERR,
        'Disablement failed closed: ' . $result['status'] . PHP_EOL
    );
    exit(1);
}
printf(
    "Disabled %s %s without executing package code or deleting package data.\n",
    $result['packageId'],
    $result['version']
);
exit(0);
