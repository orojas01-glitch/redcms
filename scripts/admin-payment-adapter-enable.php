<?php
/**
 * Server-local Owner-authorized atomic P3A payment-adapter enablement.
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
require_once $projectRoot .
    '/includes/addon_payment_adapter_enable_helpers.php';

function red_addon_payment_adapter_enable_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-payment-adapter-enable.php --package=vendor.package --actor-admin=ID\n" .
        "  php scripts/admin-payment-adapter-enable.php --package=vendor.package --actor-admin=ID \\\n" .
        "    --confirm-database=NAME --confirm-package=vendor.package \\\n" .
        "    --confirm-version=X.Y.Z --confirm-plan-sha256=SHA256 \\\n" .
        "    --confirm-backup-sha256=SHA256 --confirm-state=installed_disabled \\\n" .
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
        red_addon_payment_adapter_enable_cli_usage();
        exit(64);
    }
}
if (!red_addon_valid_package_id($options['package'])
    || $options['actorAdmin'] <= 0
) {
    red_addon_payment_adapter_enable_cli_usage();
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
if (!red_addon_install_storage_available($connection)) {
    fwrite(
        STDERR,
        "Payment-adapter enablement storage is unavailable. " .
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
        "Payment-adapter discovery, trust, or dependency validation failed.\n"
    );
    $db->close();
    exit(65);
}
$plan = red_addon_payment_adapter_enablement_plan(
    $connection,
    $package,
    $options['actorAdmin'],
    $catalog
);
if (!red_addon_payment_adapter_enablement_plan_is_valid($plan)) {
    fwrite(
        STDERR,
        'Payment-adapter enablement plan refused: ' .
            implode(', ', $plan['errors'] ?? ['invalid']) . PHP_EOL
    );
    $db->close();
    exit(65);
}

$database = red_addon_enable_preflight_database_name($connection);
printf("Database: %s\n", $database);
printf("Package: %s %s\n", $plan['packageId'], $plan['version']);
printf("Actor administrator: %d\n", $options['actorAdmin']);
printf("Current state: %s\n", $plan['currentState']);
printf("Proposed target state: %s\n", $plan['targetState']);
printf(
    "Registration/settings readiness: %d registrations; %d/%d secret references available\n",
    2,
    $plan['availableSecretCount'],
    $plan['secretSettingCount']
);
echo 'Server-event ingress: contract ready, endpoint not linked' . PHP_EOL;
echo 'Plan SHA-256: ' . $plan['planSha256'] . PHP_EOL;

if (!$options['apply']) {
    echo "DRY RUN: the fixed registration-only registrar executed; no handler, secret resolution, route, network, or database mutation occurred.\n";
    echo "Retain a verified backup and provide exact target, plan, and installed-disabled confirmations with --apply.\n";
    $db->close();
    exit(0);
}

if ($options['confirmDatabase'] !== $database
    || $options['confirmPackage'] !== $plan['packageId']
    || $options['confirmVersion'] !== $plan['version']
    || !hash_equals($plan['planSha256'], $options['confirmPlanSha256'])
    || !red_addon_valid_sha256($options['confirmBackupSha256'])
    || hash_equals(str_repeat('0', 64), $options['confirmBackupSha256'])
    || $options['confirmState'] !== 'installed_disabled'
) {
    fwrite(
        STDERR,
        "Apply requires exact database, package, version, plan, nonzero " .
            "backup checksum, and installed-disabled-state confirmations.\n"
    );
    $db->close();
    exit(64);
}

$result = red_addon_payment_adapter_enable_package(
    $connection,
    $options['package'],
    $projectRoot,
    $options['actorAdmin'],
    $options['confirmPlanSha256']
);
$db->close();
if ($result['status'] !== 'enabled') {
    fwrite(
        STDERR,
        'Payment-adapter enablement failed closed: ' .
            $result['status'] . PHP_EOL
    );
    exit(1);
}
printf(
    "Enabled %s %s after exact P3A revalidation; no provider endpoint or network client was activated.\n",
    $result['packageId'],
    $result['version']
);
exit(0);

?>
