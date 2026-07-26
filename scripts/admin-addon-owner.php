<?php
/**
 * Server-local first-Owner bootstrap and read-only status report.
 *
 * This command never installs, enables, loads, migrates, or removes an add-on.
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
require_once $projectRoot . '/includes/admin_addon_authorization_helpers.php';

function red_admin_addon_owner_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-addon-owner.php --status\n" .
        "  php scripts/admin-addon-owner.php --bootstrap-owner=ID --actor-admin=ID \\\n" .
        "    --confirm-database=NAME --confirm-username=USERNAME [--apply]\n"
    );
}

function red_admin_addon_owner_cli_account($connection, $recordId)
{
    $recordId = (int) $recordId;
    if ($recordId <= 0) {
        return null;
    }
    $stmt = mysqli_prepare(
        $connection,
        'SELECT RecordID, Username, AdminType FROM RED_Admin WHERE RecordID=? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $recordId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }
    $result = mysqli_stmt_get_result($stmt);
    $account = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $account ?: null;
}

$options = [
    'status' => false,
    'bootstrapOwner' => 0,
    'actorAdmin' => 0,
    'confirmDatabase' => '',
    'confirmUsername' => '',
    'apply' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--status') {
        $options['status'] = true;
    } elseif ($argument === '--apply') {
        $options['apply'] = true;
    } elseif (str_starts_with($argument, '--bootstrap-owner=')) {
        $options['bootstrapOwner'] = (int) substr($argument, strlen('--bootstrap-owner='));
    } elseif (str_starts_with($argument, '--actor-admin=')) {
        $options['actorAdmin'] = (int) substr($argument, strlen('--actor-admin='));
    } elseif (str_starts_with($argument, '--confirm-database=')) {
        $options['confirmDatabase'] = substr($argument, strlen('--confirm-database='));
    } elseif (str_starts_with($argument, '--confirm-username=')) {
        $options['confirmUsername'] = substr($argument, strlen('--confirm-username='));
    } else {
        red_admin_addon_owner_cli_usage();
        exit(64);
    }
}

$isBootstrap = $options['bootstrapOwner'] > 0;
if ($options['status'] === $isBootstrap
    || ($options['status'] && $options['apply'])
) {
    red_admin_addon_owner_cli_usage();
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

if (!red_admin_addon_storage_available($connection)) {
    fwrite(STDERR, "Owner authorization storage is unavailable. Apply pending migrations first.\n");
    $db->close();
    exit(65);
}

if ($options['status']) {
    $result = mysqli_query(
        $connection,
        "SELECT a.RecordID, a.Username, r.RoleName
         FROM RED_Admin_Roles r
         INNER JOIN RED_Admin a ON a.RecordID=r.AdminRecordID
         WHERE LOWER(r.RoleName)='owner'
         ORDER BY a.RecordID ASC"
    );
    $owners = [];
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $owners[] = $row;
    }
    if ($result) {
        mysqli_free_result($result);
    }

    printf("Database: %s\n", DBNAME);
    if ($owners === []) {
        echo "No Owner is assigned.\n";
    }
    foreach ($owners as $owner) {
        $actor = red_admin_addon_database_actor($connection, (int) $owner['RecordID']);
        printf(
            "Owner #%d %s: %s\n",
            (int) $owner['RecordID'],
            (string) $owner['Username'],
            implode(', ', $actor['capabilities'])
        );
    }
    $db->close();
    exit(0);
}

if ($options['actorAdmin'] <= 0
    || $options['confirmDatabase'] !== (string) DBNAME
    || $options['confirmUsername'] === ''
) {
    fwrite(STDERR, "Bootstrap requires exact database, username, and actor confirmations.\n");
    $db->close();
    exit(64);
}

$target = red_admin_addon_owner_cli_account($connection, $options['bootstrapOwner']);
$actor = red_admin_addon_owner_cli_account($connection, $options['actorAdmin']);
if (!$target
    || !hash_equals((string) $target['Username'], $options['confirmUsername'])
) {
    fwrite(STDERR, "Target administrator confirmation did not match.\n");
    $db->close();
    exit(65);
}
if (!red_admin_addon_manager_account($connection, $options['bootstrapOwner'])) {
    fwrite(STDERR, "The target administrator must be a Webmaster or legacy Superadmin.\n");
    $db->close();
    exit(65);
}
if (!$actor || !red_admin_addon_manager_account($connection, $options['actorAdmin'])) {
    fwrite(STDERR, "The recorded actor must be a Webmaster or legacy Superadmin.\n");
    $db->close();
    exit(65);
}
if (red_admin_addon_owner_count($connection) > 0) {
    fwrite(STDERR, "An Owner already exists; first-Owner bootstrap is permanently closed.\n");
    $db->close();
    exit(65);
}

printf(
    "%s Owner #%d %s in database %s with %d bounded lifecycle capabilities; actor #%d %s.\n",
    $options['apply'] ? 'Assigning' : 'DRY RUN: would assign',
    (int) $target['RecordID'],
    (string) $target['Username'],
    (string) DBNAME,
    count(red_admin_addon_lifecycle_capabilities()),
    (int) $actor['RecordID'],
    (string) $actor['Username']
);
if (!$options['apply']) {
    echo "No database changes were made. Add --apply to perform this one-time bootstrap.\n";
    $db->close();
    exit(0);
}

$status = red_admin_addon_bootstrap_owner(
    $connection,
    $options['bootstrapOwner'],
    $options['actorAdmin']
);
$db->close();

if ($status !== 'yes') {
    fwrite(STDERR, "Owner bootstrap failed: " . $status . "\n");
    exit($status === 'owner_exists' ? 65 : 1);
}

echo "Owner bootstrap completed. Existing sessions refresh authorization on their next protected request.\n";
exit(0);
