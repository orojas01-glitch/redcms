<?php
/**
 * Server-local Owner workflow for manifest-declared package permissions.
 *
 * Discovery and dry runs never execute package PHP. Mutations require exact
 * database, actor, target, and plan confirmations and are atomic with audit.
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
require_once $projectRoot . '/includes/addon_package_permission_helpers.php';

function red_admin_addon_permission_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-addon-permission.php --status --package=ID --target-admin=ID\n" .
        "  php scripts/admin-addon-permission.php --package=ID --target-admin=ID \\\n" .
        "    --actor-owner=ID --permission=PERMISSION (--grant|--revoke) \\\n" .
        "    --confirm-database=NAME --confirm-target-username=USERNAME \\\n" .
        "    --confirm-actor-username=USERNAME\n" .
        "  Repeat the mutation command with --apply --expected-plan=SHA256.\n"
    );
}

$options = [
    'status' => false,
    'package' => '',
    'targetAdmin' => 0,
    'actorOwner' => 0,
    'permission' => '',
    'action' => '',
    'confirmDatabase' => '',
    'confirmTargetUsername' => '',
    'confirmActorUsername' => '',
    'expectedPlan' => '',
    'apply' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--status') {
        $options['status'] = true;
    } elseif ($argument === '--grant') {
        $options['action'] = $options['action'] === '' ? 'grant' : 'invalid';
    } elseif ($argument === '--revoke') {
        $options['action'] = $options['action'] === '' ? 'revoke' : 'invalid';
    } elseif ($argument === '--apply') {
        $options['apply'] = true;
    } elseif (str_starts_with($argument, '--package=')) {
        $options['package'] = substr($argument, strlen('--package='));
    } elseif (str_starts_with($argument, '--target-admin=')) {
        $options['targetAdmin'] = (int) substr($argument, strlen('--target-admin='));
    } elseif (str_starts_with($argument, '--actor-owner=')) {
        $options['actorOwner'] = (int) substr($argument, strlen('--actor-owner='));
    } elseif (str_starts_with($argument, '--permission=')) {
        $options['permission'] = substr($argument, strlen('--permission='));
    } elseif (str_starts_with($argument, '--confirm-database=')) {
        $options['confirmDatabase'] = substr($argument, strlen('--confirm-database='));
    } elseif (str_starts_with($argument, '--confirm-target-username=')) {
        $options['confirmTargetUsername'] = substr(
            $argument,
            strlen('--confirm-target-username=')
        );
    } elseif (str_starts_with($argument, '--confirm-actor-username=')) {
        $options['confirmActorUsername'] = substr(
            $argument,
            strlen('--confirm-actor-username=')
        );
    } elseif (str_starts_with($argument, '--expected-plan=')) {
        $options['expectedPlan'] = substr($argument, strlen('--expected-plan='));
    } else {
        red_admin_addon_permission_cli_usage();
        exit(64);
    }
}

if (!red_addon_valid_package_id($options['package'])
    || $options['targetAdmin'] <= 0
    || ($options['status'] && ($options['apply'] || $options['action'] !== ''))
    || (!$options['status'] && !in_array($options['action'], ['grant', 'revoke'], true))
) {
    red_admin_addon_permission_cli_usage();
    exit(64);
}

$catalog = red_addon_discover($projectRoot);
$package = $catalog['packages'][$options['package']] ?? null;
if (empty($catalog['valid']) || !is_array($package) || empty($package['valid'])) {
    fwrite(STDERR, "Validated package discovery failed. No package code was executed.\n");
    exit(65);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
if (!red_addon_package_permission_storage_available($connection)) {
    fwrite(STDERR, "Package permission storage is unavailable. Apply pending core migrations first.\n");
    $db->close();
    exit(65);
}

$target = red_addon_package_permission_target(
    $connection,
    $options['targetAdmin']
);
if ($target === null) {
    fwrite(STDERR, "Target administrator is unavailable.\n");
    $db->close();
    exit(65);
}
$manifest = $package['manifest'];
$declaredPermissions = $manifest['permissions'] ?? [];

if ($options['status']) {
    printf(
        "Database: %s\nPackage: %s %s\nTarget: #%d %s\n",
        (string) DBNAME,
        (string) $manifest['id'],
        (string) $manifest['version'],
        (int) $target['RecordID'],
        (string) $target['Username']
    );
    foreach ($declaredPermissions as $permission) {
        printf(
            "%s: %s\n",
            (string) $permission,
            red_addon_package_permission_has_exact_grant(
                $connection,
                (int) $target['RecordID'],
                (string) $permission
            ) ? 'granted' : 'not granted'
        );
    }
    $db->close();
    exit(0);
}

$actor = red_addon_package_permission_target(
    $connection,
    $options['actorOwner']
);
if ($actor === null
    || $options['confirmDatabase'] !== (string) DBNAME
    || !hash_equals(
        (string) $target['Username'],
        $options['confirmTargetUsername']
    )
    || !hash_equals(
        (string) $actor['Username'],
        $options['confirmActorUsername']
    )
) {
    fwrite(STDERR, "Exact database, actor, and target confirmations are required.\n");
    $db->close();
    exit(65);
}

$plan = red_addon_package_permission_plan(
    $connection,
    $package,
    $options['actorOwner'],
    $options['targetAdmin'],
    $options['permission'],
    $options['action']
);
if (empty($plan['changeReady'])) {
    fwrite(
        STDERR,
        "Permission change refused: " .
        implode(', ', $plan['errors'] ?? ['unknown']) . "\n"
    );
    $db->close();
    exit(65);
}

printf(
    "%s %s for target #%d %s in database %s.\nPlan SHA-256: %s\n",
    $options['apply'] ? strtoupper($options['action']) : 'DRY RUN: would ' . $options['action'],
    (string) $options['permission'],
    (int) $target['RecordID'],
    (string) $target['Username'],
    (string) DBNAME,
    (string) $plan['planSha256']
);
if (!$options['apply']) {
    echo "No database changes were made. Repeat with --apply and the exact --expected-plan value.\n";
    $db->close();
    exit(0);
}
if (!hash_equals((string) $plan['planSha256'], $options['expectedPlan'])) {
    fwrite(STDERR, "The expected plan confirmation did not match current state.\n");
    $db->close();
    exit(65);
}

$result = red_addon_package_permission_execute(
    $connection,
    $package,
    $options['actorOwner'],
    $options['targetAdmin'],
    $options['permission'],
    $options['action'],
    $options['expectedPlan']
);
$db->close();
if (empty($result['changed'])) {
    fwrite(STDERR, "Permission change failed: " . (string) $result['reason'] . "\n");
    exit(1);
}

printf(
    "Permission %s. Existing administrator sessions are rechecked at each package decision.\n",
    (string) $result['status']
);
exit(0);
