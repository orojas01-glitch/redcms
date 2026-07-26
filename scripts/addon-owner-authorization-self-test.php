<?php
/**
 * Disposable database checks for persisted Owner lifecycle authorization.
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
require_once $projectRoot . '/includes/admin_user_helpers.php';

if (!preg_match('/\Aredcms_(?:acceptance|addon_auth)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(STDERR, "Owner authorization self-test refused non-disposable database: " . DBNAME . "\n");
    exit(65);
}

$assertions = 0;
$managerId = 2147000970;
$secondManagerId = 2147000971;
$guestId = 2147000972;
$fixtureIds = [$managerId, $secondManagerId, $guestId];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_owner_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_owner_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_owner_test_cleanup($connection, array $fixtureIds)
{
    $ids = implode(',', array_map('intval', $fixtureIds));
    try {
        mysqli_query($connection, 'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID IN (' . $ids . ')');
        mysqli_query($connection, 'DELETE FROM RED_Admin_Roles WHERE AdminRecordID IN (' . $ids . ')');
        mysqli_query(
            $connection,
            "DELETE FROM RED_Admin_Activity_Log
             WHERE ActorAdminRecordID IN ($ids)
                OR (TargetType='administrator' AND TargetRecordID IN ($ids))"
        );
        mysqli_query($connection, 'DELETE FROM RED_Admin WHERE RecordID IN (' . $ids . ')');
    } catch (Throwable $throwable) {
        error_log('Owner authorization self-test cleanup failed: ' . $throwable->getMessage());
    }
}

try {
    red_addon_owner_test_cleanup($connection, $fixtureIds);

    red_addon_owner_test_assert(
        red_addon_owner_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME IN ('RED_Admin_Roles','RED_Admin_Capabilities')"
        ) === '2',
        'both normalized Owner authorization tables exist'
    );
    red_addon_owner_test_assert(
        red_addon_owner_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Roles'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Admin_Capabilities'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA=DATABASE()
                   AND CONSTRAINT_NAME IN (
                     'fk_red_admin_roles_admin',
                     'fk_red_admin_capabilities_admin'
                   ))
             )"
        ) === '4:4:2',
        'Owner authorization columns and protected account foreign keys match'
    );
    red_addon_owner_test_assert(
        red_addon_owner_test_scalar(
            $connection,
            'SELECT CONCAT_WS(":", COUNT(*), (SELECT COUNT(*) FROM RED_Admin_Capabilities)) FROM RED_Admin_Roles'
        ) === '0:0',
        'the migrated client starts with no automatic Owner or lifecycle grant'
    );

    $passwordHash = password_hash('OwnerAuthorizationFixture-2026!', PASSWORD_DEFAULT);
    if (!is_string($passwordHash)) {
        throw new RuntimeException('Could not create fixture password hash.');
    }
    $stmt = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
         ) VALUES (?, ?, ?, 'Admin', ?, ?, '100', '1', ?, 'N', 'to', 'N', 'to')"
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare Owner authorization administrators.');
    }
    $fixtures = [
        [$managerId, 'codex_owner_manager', 'OwnerOne', 'webmaster', 'owner-one@example.test'],
        [$secondManagerId, 'codex_owner_actor', 'OwnerActor', 'webmaster', 'owner-actor@example.test'],
        [$guestId, 'codex_owner_guest', 'OwnerGuest', 'guest', 'owner-guest@example.test'],
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

    red_addon_owner_test_assert(
        red_admin_addon_bootstrap_owner($connection, $guestId, $secondManagerId) === 'target_not_manager'
            && red_admin_addon_bootstrap_owner($connection, $managerId, $guestId) === 'actor_not_manager'
            && red_admin_addon_owner_count($connection) === 0,
        'Guest accounts cannot become or record the first Owner'
    );
    red_addon_owner_test_assert(
        red_admin_addon_bootstrap_owner($connection, $managerId, $secondManagerId) === 'yes',
        'an explicit manager-to-manager first-Owner bootstrap succeeds'
    );
    red_addon_owner_test_assert(
        red_addon_owner_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Admin_Roles
                 WHERE AdminRecordID=$managerId AND RoleName='owner'
                   AND AssignedByAdminRecordID=$secondManagerId),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$managerId
                   AND GrantedByAdminRecordID=$secondManagerId),
                (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                 WHERE EventName='administrator.owner_bootstrapped'
                   AND ActorAdminRecordID=$secondManagerId
                   AND TargetType='administrator'
                   AND TargetRecordID=$managerId)
             )"
        ) === '1:6:1',
        'Owner role, six bounded grants, and one audit event persist atomically'
    );
    red_addon_owner_test_assert(
        red_admin_addon_owner_count($connection) === 1
            && red_admin_addon_bootstrap_owner(
                $connection,
                $secondManagerId,
                $secondManagerId
            ) === 'owner_exists',
        'first-Owner bootstrap closes permanently once an Owner exists'
    );

    $databaseActor = red_admin_addon_database_actor($connection, $managerId);
    $expectedCapabilities = red_admin_addon_lifecycle_capabilities();
    sort($expectedCapabilities, SORT_STRING);
    red_addon_owner_test_assert(
        $databaseActor['role'] === 'owner'
            && $databaseActor['capabilities'] === $expectedCapabilities,
        'database authorization resolves only the exact bounded lifecycle grants'
    );

    $_SESSION = ['AdminRecordID' => $managerId];
    red_admin_addon_refresh_session_authorization($connection, $managerId);
    red_addon_owner_test_assert(
        red_admin_addon_current_actor_can('addons.install')
            && red_admin_addon_current_actor_can('addons.purge')
            && !red_admin_addon_current_actor_can('addons.root'),
        'refreshed Owner sessions require an exact recognized capability'
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$managerId AND Capability='addons.purge'"
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($managerId, 'addons.root', $secondManagerId)"
    );
    red_admin_addon_refresh_session_authorization($connection, $managerId);
    red_addon_owner_test_assert(
        !red_admin_addon_current_actor_can('addons.purge')
            && !in_array('addons.root', $_SESSION['AdminCapabilities'], true)
            && count($_SESSION['AdminCapabilities']) === 5,
        'session refresh removes revoked grants and ignores unknown database values'
    );

    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($secondManagerId, 'addons.install', $secondManagerId)"
    );
    $nonOwnerActor = red_admin_addon_database_actor($connection, $secondManagerId);
    red_addon_owner_test_assert(
        $nonOwnerActor['role'] === '' && $nonOwnerActor['capabilities'] === [],
        'capability rows never authorize an administrator without the Owner role'
    );

    $_SESSION = ['AdminRecordID' => $secondManagerId];
    $demotion = red_admin_user_update($connection, [
        'RecordID' => $managerId,
        'Alias' => 'OwnerOne',
        'Email' => 'owner-one@example.test',
        'Password' => '',
        'AdminType' => 'guest',
        'components' => ['100'],
        'tools' => ['1'],
    ]);
    red_addon_owner_test_assert(
        $demotion === 'owner_protected'
            && red_admin_user_delete($connection, ['RecordID' => $managerId]) === 'owner_protected'
            && red_addon_owner_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', AdminType, COUNT(*))
                 FROM RED_Admin
                 WHERE RecordID=$managerId
                 GROUP BY AdminType"
            ) === 'webmaster:1',
        'Administrator Users cannot demote or delete the protected Owner'
    );

    mysqli_query($connection, "DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID IN ($managerId,$secondManagerId)");
    mysqli_query($connection, "DELETE FROM RED_Admin_Roles WHERE AdminRecordID=$managerId");
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Activity_Log
         WHERE ActorAdminRecordID IN ($managerId,$secondManagerId)
            OR (TargetType='administrator' AND TargetRecordID IN ($managerId,$secondManagerId))"
    );
    red_addon_owner_test_assert(
        red_admin_addon_bootstrap_owner(
            $connection,
            $secondManagerId,
            $secondManagerId,
            static function () {
                return false;
            }
        ) === 'no'
            && red_addon_owner_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Admin_Roles
                     WHERE AdminRecordID=$secondManagerId),
                    (SELECT COUNT(*) FROM RED_Admin_Capabilities
                     WHERE AdminRecordID=$secondManagerId)
                 )"
            ) === '0:0',
        'a forced late audit failure rolls back the Owner role and every grant'
    );

    $cliSource = (string) file_get_contents($projectRoot . '/scripts/admin-addon-owner.php');
    $loginSource = (string) file_get_contents($projectRoot . '/bin/login.php');
    $bootstrapSource = (string) file_get_contents($projectRoot . '/includes/bootstrap.php');
    red_addon_owner_test_assert(
        str_contains($cliSource, "PHP_SAPI !== 'cli'")
            && str_contains($cliSource, '--confirm-database=')
            && str_contains($cliSource, '--confirm-username=')
            && str_contains($cliSource, '--apply')
            && !file_exists($projectRoot . '/admin/bin/addon_owner.php'),
        'first-Owner assignment is local-only and requires explicit target confirmations'
    );
    red_addon_owner_test_assert(
        str_contains($loginSource, 'red_admin_addon_refresh_session_authorization')
            && str_contains($bootstrapSource, 'red_admin_addon_refresh_session_authorization')
            && str_contains($bootstrapSource, "\$_SESSION['AdminRole']")
            && str_contains($bootstrapSource, "\$_SESSION['AdminCapabilities']"),
        'login and protected-request validation both refresh persisted Owner authorization'
    );

    red_addon_owner_test_cleanup($connection, $fixtureIds);
    red_addon_owner_test_assert(
        red_addon_owner_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID IN ($managerId,$secondManagerId,$guestId)),
                (SELECT COUNT(*) FROM RED_Admin_Roles
                 WHERE AdminRecordID IN ($managerId,$secondManagerId,$guestId)),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID IN ($managerId,$secondManagerId,$guestId)),
                (SELECT COUNT(*) FROM RED_Admin_Activity_Log
                 WHERE ActorAdminRecordID IN ($managerId,$secondManagerId,$guestId)
                    OR (TargetType='administrator'
                        AND TargetRecordID IN ($managerId,$secondManagerId,$guestId))),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS
                 WHERE TRIGGER_SCHEMA=DATABASE()
                   AND TRIGGER_NAME='red_addon_owner_force_audit_failure')
             )"
        ) === '0:0:0:0:0',
        'Owner authorization fixtures, audit rows, and forced trigger clean up exactly'
    );

    printf("Add-on Owner authorization self-test passed: %d assertions.\n", $assertions);
} catch (Throwable $throwable) {
    red_addon_owner_test_cleanup($connection, $fixtureIds);
    fwrite(
        STDERR,
        $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n"
    );
    $db->close();
    exit(1);
}

$db->close();
