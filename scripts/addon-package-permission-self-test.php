<?php
/**
 * Disposable checks for Owner-authorized package permission changes.
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

if (!preg_match('/\Aredcms_(?:acceptance|addon_permission)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(STDERR, "Package permission self-test refused non-disposable database: " . DBNAME . "\n");
    exit(65);
}

$assertions = 0;
$ownerId = 2146999810;
$targetId = 2146999811;
$otherId = 2146999812;
$fixtureIds = [$ownerId, $targetId, $otherId];
$packageId = 'redcms.permission-fixture';
$permission = 'fixture.inventory.settings.manage';
$temporaryRoot = sys_get_temp_dir() . '/redcms-addon-permission-' . bin2hex(random_bytes(6));
$executionMarker = $temporaryRoot . '/package-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_permission_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_permission_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_permission_test_remove_tree($path)
{
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child) && !is_link($child)) {
            red_addon_permission_test_remove_tree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}

function red_addon_permission_test_cleanup(
    $connection,
    array $fixtureIds,
    $packageId,
    $temporaryRoot
) {
    $ids = implode(',', array_map('intval', $fixtureIds));
    try {
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Permission_Activity_Log
             WHERE PackageID='" . mysqli_real_escape_string($connection, $packageId) . "'"
        );
        mysqli_query($connection, "DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID IN ($ids)");
        mysqli_query($connection, "DELETE FROM RED_Admin_Roles WHERE AdminRecordID IN ($ids)");
        mysqli_query($connection, "DELETE FROM RED_Admin WHERE RecordID IN ($ids)");
    } catch (Throwable $throwable) {
        error_log('Package permission self-test cleanup failed: ' . $throwable->getMessage());
    }
    red_addon_permission_test_remove_tree($temporaryRoot);
}

function red_addon_permission_test_package($temporaryRoot, $executionMarker)
{
    $project = $temporaryRoot . '/project';
    $directory = $project . '/addons/redcms/permission-fixture';
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create package permission fixture.');
    }
    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) .
        ", 'executed');\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.permission-fixture',
        'name' => 'Permission Fixture',
        'description' => 'Non-executing package permission fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => ['redcms.permission-fixture/service'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [
            'fixture.inventory.settings.manage',
            'fixture.inventory.orders.view',
        ],
        'settings' => [],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => hash('sha256', $entrypoint),
            ]],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => true,
        ],
    ];
    file_put_contents(
        $directory . '/addon.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    return $project;
}

try {
    red_addon_permission_test_cleanup(
        $connection,
        $fixtureIds,
        $packageId,
        $temporaryRoot
    );
    red_addon_permission_test_assert(
        red_addon_package_permission_storage_available($connection),
        'capability and immutable package-permission audit storage are available'
    );
    red_addon_permission_test_assert(
        red_addon_permission_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Permission_Activity_Log'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Permission_Activity_Log'
                   AND INDEX_NAME IN (
                     'PRIMARY',
                     'idx_red_addon_permission_package',
                     'idx_red_addon_permission_target',
                     'idx_red_addon_permission_actor'
                   ))
             )"
        ) === '9:10',
        'permission audit columns and four exact index definitions exist'
    );

    $passwordHash = password_hash('PackagePermissionFixture-2026!', PASSWORD_DEFAULT);
    if (!is_string($passwordHash)) {
        throw new RuntimeException('Could not create package permission fixture password.');
    }
    $stmt = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, ?, ?, 'Admin', ?, ?, '', '', ?, 'N', 'to', 'N', 'to')"
    );
    $fixtures = [
        [$ownerId, 'codex_permission_owner', 'PermOwner', 'webmaster', 'permission-owner@example.test'],
        [$targetId, 'codex_permission_target', 'PermTarget', 'guest', 'permission-target@example.test'],
        [$otherId, 'codex_permission_other', 'PermOther', 'webmaster', 'permission-other@example.test'],
    ];
    foreach ($fixtures as $fixture) {
        [$recordId, $username, $alias, $adminType, $email] = $fixture;
        mysqli_stmt_bind_param(
            $stmt,
            'isssss',
            $recordId,
            $username,
            $passwordHash,
            $alias,
            $adminType,
            $email
        );
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($ownerId, 'owner', $ownerId)"
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($ownerId, 'addons.install', $ownerId)"
    );

    $fixtureProject = red_addon_permission_test_package(
        $temporaryRoot,
        $executionMarker
    );
    $catalog = red_addon_discover($fixtureProject);
    $package = $catalog['packages'][$packageId] ?? null;
    red_addon_permission_test_assert(
        !empty($catalog['valid'])
            && is_array($package)
            && !empty($package['valid'])
            && !file_exists($executionMarker),
        'manifest discovery validates the package without executing its entrypoint'
    );

    $before = red_addon_permission_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':',
            (SELECT COUNT(*) FROM RED_Admin_Capabilities
             WHERE AdminRecordID IN ($ownerId,$targetId,$otherId)),
            (SELECT COUNT(*) FROM RED_Addon_Permission_Activity_Log
             WHERE PackageID='$packageId')
         )"
    );
    $plan = red_addon_package_permission_plan(
        $connection,
        $package,
        $ownerId,
        $targetId,
        $permission,
        'grant'
    );
    $repeatPlan = red_addon_package_permission_plan(
        $connection,
        $package,
        $ownerId,
        $targetId,
        $permission,
        'grant'
    );
    red_addon_permission_test_assert(
        !empty($plan['valid'])
            && !empty($plan['changeReady'])
            && $plan['currentState'] === 'not_granted'
            && $plan['targetState'] === 'granted'
            && red_addon_valid_sha256($plan['planSha256'])
            && $plan['planSha256'] === $repeatPlan['planSha256'],
        'grant planning is deterministic and binds exact current and target state'
    );
    red_addon_permission_test_assert(
        red_addon_permission_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID IN ($ownerId,$targetId,$otherId)),
                (SELECT COUNT(*) FROM RED_Addon_Permission_Activity_Log
                 WHERE PackageID='$packageId')
             )"
        ) === $before
            && !file_exists($executionMarker),
        'planning writes no capability or audit row and executes no package code'
    );

    $nonOwner = red_addon_package_permission_plan(
        $connection,
        $package,
        $otherId,
        $targetId,
        $permission,
        'grant'
    );
    $undeclared = red_addon_package_permission_plan(
        $connection,
        $package,
        $ownerId,
        $targetId,
        'fixture.inventory.products.manage',
        'grant'
    );
    $missingTarget = red_addon_package_permission_plan(
        $connection,
        $package,
        $ownerId,
        2146999899,
        $permission,
        'grant'
    );
    red_addon_permission_test_assert(
        in_array('owner_required', $nonOwner['errors'], true)
            && in_array('permission_not_declared', $undeclared['errors'], true)
            && in_array('target_unavailable', $missingTarget['errors'], true),
        'non-Owners, undeclared permissions, and missing targets fail closed'
    );

    $wrongPlan = red_addon_package_permission_execute(
        $connection,
        $package,
        $ownerId,
        $targetId,
        $permission,
        'grant',
        str_repeat('0', 64)
    );
    red_addon_permission_test_assert(
        empty($wrongPlan['changed'])
            && $wrongPlan['reason'] === 'stale_plan'
            && red_addon_permission_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$targetId"
            ) === '0',
        'a mismatched plan is refused without a partial grant'
    );

    $grant = red_addon_package_permission_execute(
        $connection,
        $package,
        $ownerId,
        $targetId,
        $permission,
        'grant',
        $plan['planSha256']
    );
    red_addon_permission_test_assert(
        !empty($grant['changed'])
            && $grant['status'] === 'granted'
            && red_addon_package_permission_has_exact_grant(
                $connection,
                $targetId,
                $permission
            ),
        'the exact current plan atomically grants the declared permission'
    );
    red_addon_permission_test_assert(
        red_addon_permission_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':', EventName, PackageID, PackageVersion,
                    Permission, TargetAdminRecordID, ActorAdminRecordID, Result)
             FROM RED_Addon_Permission_Activity_Log
             WHERE PackageID='$packageId' ORDER BY RecordID DESC LIMIT 1"
        ) === "addon.permission.granted:$packageId:1.0.0:$permission:$targetId:$ownerId:succeeded",
        'the grant commits one exact bounded audit fact with actor and target'
    );

    $replay = red_addon_package_permission_execute(
        $connection,
        $package,
        $ownerId,
        $targetId,
        $permission,
        'grant',
        $plan['planSha256']
    );
    red_addon_permission_test_assert(
        empty($replay['changed'])
            && $replay['reason'] === 'already_granted'
            && red_addon_permission_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Permission_Activity_Log
                 WHERE PackageID='$packageId'"
            ) === '1',
        'replay is refused without duplicating grant or audit state'
    );

    $revokePlan = red_addon_package_permission_plan(
        $connection,
        $package,
        $ownerId,
        $targetId,
        $permission,
        'revoke'
    );
    red_addon_permission_test_assert(
        !empty($revokePlan['changeReady'])
            && $revokePlan['currentState'] === 'granted'
            && $revokePlan['targetState'] === 'not_granted',
        'revocation planning binds the fresh granted state'
    );
    mysqli_begin_transaction($connection);
    $nested = red_addon_package_permission_execute(
        $connection,
        $package,
        $ownerId,
        $targetId,
        $permission,
        'revoke',
        $revokePlan['planSha256']
    );
    mysqli_rollback($connection);
    red_addon_permission_test_assert(
        empty($nested['changed'])
            && $nested['reason'] === 'transaction_already_active'
            && red_addon_package_permission_has_exact_grant(
                $connection,
                $targetId,
                $permission
            ),
        'caller-owned transactions are refused without changing the grant'
    );

    $revoke = red_addon_package_permission_execute(
        $connection,
        $package,
        $ownerId,
        $targetId,
        $permission,
        'revoke',
        $revokePlan['planSha256']
    );
    red_addon_permission_test_assert(
        !empty($revoke['changed'])
            && $revoke['status'] === 'revoked'
            && !red_addon_package_permission_has_exact_grant(
                $connection,
                $targetId,
                $permission
            ),
        'the exact current plan atomically revokes the permission'
    );
    red_addon_permission_test_assert(
        red_addon_permission_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$targetId),
                (SELECT COUNT(*) FROM RED_Addon_Permission_Activity_Log
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Permission_Activity_Log
                 WHERE PackageID='$packageId'
                   AND EventName='addon.permission.revoked'
                   AND Permission='$permission'
                   AND TargetAdminRecordID=$targetId
                   AND ActorAdminRecordID=$ownerId
                   AND Result='succeeded')
             )"
        ) === '0:2:1'
            && !file_exists($executionMarker),
        'revocation takes effect immediately and adds one exact audit fact only'
    );

    red_addon_permission_test_cleanup(
        $connection,
        $fixtureIds,
        $packageId,
        $temporaryRoot
    );
    red_addon_permission_test_assert(
        red_addon_permission_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Admin
                 WHERE RecordID IN ($ownerId,$targetId,$otherId)),
                (SELECT COUNT(*) FROM RED_Admin_Roles
                 WHERE AdminRecordID IN ($ownerId,$targetId,$otherId)),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID IN ($ownerId,$targetId,$otherId)),
                (SELECT COUNT(*) FROM RED_Addon_Permission_Activity_Log
                 WHERE PackageID='$packageId')
             )"
        ) === '0:0:0:0'
            && !file_exists($temporaryRoot),
        'administrator, grant, audit, and filesystem fixtures clean to zero'
    );

    $db->close();
    printf("Package permission checks passed: %d assertions.\n", $assertions);
    exit(0);
} catch (Throwable $throwable) {
    red_addon_permission_test_cleanup(
        $connection,
        $fixtureIds,
        $packageId,
        $temporaryRoot
    );
    $db->close();
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
