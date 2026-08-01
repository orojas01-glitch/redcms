<?php
/**
 * Disposable checks for component-editor package-permission decisions.
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
require_once $projectRoot
    . '/includes/addon_component_editor_authorization_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_editor_auth)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Component editor authorization self-test refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$permittedAdminId = 2147000980;
$ownerAdminId = 2147000981;
$otherAdminId = 2147000982;
$fixtureIds = [$permittedAdminId, $ownerAdminId, $otherAdminId];
$component = 'redcms.authorization-fixture/item';
$longPermission = 'fixture.'
    . str_repeat('publish.', 18)
    . 'manage';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_editor_auth_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_editor_auth_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_editor_auth_test_cleanup($connection, array $fixtureIds)
{
    $ids = implode(',', array_map('intval', $fixtureIds));
    try {
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID IN ('
                . $ids . ')'
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID IN ('
                . $ids . ')'
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID IN (' . $ids . ')'
        );
    } catch (Throwable $throwable) {
        error_log(
            'Component editor authorization cleanup failed: '
                . $throwable->getMessage()
        );
    }
}

function red_addon_editor_auth_test_manifest($component, $longPermission)
{
    $permissions = [
        'create' => 'fixture.items.create',
        'view' => 'fixture.items.view',
        'edit' => 'fixture.items.edit',
        'delete' => 'fixture.items.delete',
        'publish' => $longPermission,
        'restore' => 'fixture.items.restore',
    ];
    return [
        'provides' => ['components' => [$component]],
        'permissions' => array_values($permissions),
        'componentEditors' => [[
            'component' => $component,
            'label' => 'Authorization fixture',
            'description' => 'Exact package-permission decision fixture.',
            'icon' => 'package',
            'permissions' => $permissions,
            'fields' => [[
                'key' => 'title',
                'label' => 'Title',
                'type' => 'text',
                'required' => true,
                'maxLength' => 120,
            ]],
        ]],
    ];
}

function red_addon_editor_auth_test_fingerprint(
    $connection,
    array $fixtureIds
) {
    $ids = implode(',', array_map('intval', $fixtureIds));
    return red_addon_editor_auth_test_scalar(
        $connection,
        "SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID IN ($ids)),
            (SELECT COUNT(*) FROM RED_Admin_Roles
             WHERE AdminRecordID IN ($ids)),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities
             WHERE AdminRecordID IN ($ids)),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS(
                '#',
                AdminRecordID,
                Capability,
                GrantedByAdminRecordID,
                GrantedAt
             ))), 0)
             FROM RED_Admin_Capabilities
             WHERE AdminRecordID IN ($ids)),
            (SELECT COUNT(*) FROM RED_Addon_Installations),
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log)
         )"
    );
}

try {
    red_addon_editor_auth_test_cleanup($connection, $fixtureIds);
    $manifest = red_addon_editor_auth_test_manifest(
        $component,
        $longPermission
    );

    red_addon_editor_auth_test_assert(
        strlen($longPermission) > 64
            && strlen($longPermission) <= 160
            && red_addon_valid_permission($longPermission),
        'the fixture proves the published permission length above the legacy limit'
    );
    red_addon_editor_auth_test_assert(
        red_addon_component_editor_permission_storage_available($connection)
            && red_addon_editor_auth_test_scalar(
                $connection,
                "SELECT CHARACTER_MAXIMUM_LENGTH
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Admin_Capabilities'
                   AND COLUMN_NAME='Capability'"
            ) === '160',
        'per-client capability storage matches the manifest permission limit'
    );

    $expectedPermissions = [
        'create' => 'fixture.items.create',
        'view' => 'fixture.items.view',
        'edit' => 'fixture.items.edit',
        'delete' => 'fixture.items.delete',
        'publish' => $longPermission,
        'restore' => 'fixture.items.restore',
    ];
    $resolvedPermissions = [];
    foreach (red_addon_component_editor_operations() as $operation) {
        $plan = red_addon_component_editor_permission_plan(
            $manifest,
            $component,
            $operation
        );
        $resolvedPermissions[$operation] = $plan['permission'] ?? null;
    }
    red_addon_editor_auth_test_assert(
        $resolvedPermissions === $expectedPermissions,
        'all six fixed operations resolve only their exact manifest permission'
    );

    $passwordHash = password_hash(
        'ComponentEditorAuthorization-2026!',
        PASSWORD_DEFAULT
    );
    if (!is_string($passwordHash)) {
        throw new RuntimeException('Could not create fixture password hash.');
    }
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, ?, ?, 'Admin', ?, ?, '', '', ?, 'N', 'to', 'N', 'to')"
    );
    if (!$statement) {
        throw new RuntimeException(
            'Could not prepare component editor authorization administrators.'
        );
    }
    $fixtures = [
        [
            $permittedAdminId,
            'codex_editor_permission',
            'EditPerm',
            'guest',
            'editor-permission@example.test',
        ],
        [
            $ownerAdminId,
            'codex_editor_owner',
            'EditOwner',
            'webmaster',
            'editor-owner@example.test',
        ],
        [
            $otherAdminId,
            'codex_editor_other',
            'EditOther',
            'guest',
            'editor-other@example.test',
        ],
    ];
    foreach ($fixtures as $fixture) {
        [$recordId, $username, $alias, $adminType, $email] = $fixture;
        mysqli_stmt_bind_param(
            $statement,
            'isssss',
            $recordId,
            $username,
            $passwordHash,
            $alias,
            $adminType,
            $email
        );
        mysqli_stmt_execute($statement);
    }
    mysqli_stmt_close($statement);

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)'
    );
    if (!$statement) {
        throw new RuntimeException(
            'Could not prepare component editor authorization grants.'
        );
    }
    $grants = [
        [$permittedAdminId, 'fixture.items.create', $ownerAdminId],
        [$permittedAdminId, $longPermission, $ownerAdminId],
        [$permittedAdminId, 'FIXTURE.ITEMS.EDIT', $ownerAdminId],
        [$ownerAdminId, 'addons.install', $ownerAdminId],
        [$ownerAdminId, 'addons.enable', $ownerAdminId],
        [$otherAdminId, 'fixture.other.create', $ownerAdminId],
    ];
    foreach ($grants as $grant) {
        [$adminRecordId, $permission, $grantedByAdminRecordId] = $grant;
        mysqli_stmt_bind_param(
            $statement,
            'isi',
            $adminRecordId,
            $permission,
            $grantedByAdminRecordId
        );
        mysqli_stmt_execute($statement);
    }
    mysqli_stmt_close($statement);
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles (
            AdminRecordID, RoleName, AssignedByAdminRecordID
         ) VALUES ($ownerAdminId, 'owner', $ownerAdminId)"
    );

    red_addon_editor_auth_test_assert(
        red_addon_editor_auth_test_scalar(
            $connection,
            "SELECT CONCAT_WS(
                ':',
                (SELECT COUNT(*) FROM RED_Admin
                 WHERE RecordID IN (
                   $permittedAdminId,
                   $ownerAdminId,
                   $otherAdminId
                 )),
                (SELECT COUNT(*) FROM RED_Admin_Roles
                 WHERE AdminRecordID=$ownerAdminId AND RoleName='owner'),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID IN (
                   $permittedAdminId,
                   $ownerAdminId,
                   $otherAdminId
                 ))
             )"
        ) === '3:1:6',
        'the disposable actors and exact test-only grants are isolated'
    );

    $before = red_addon_editor_auth_test_fingerprint(
        $connection,
        $fixtureIds
    );
    $createDecision = red_addon_component_editor_permission_decision(
        $connection,
        $manifest,
        $component,
        'create',
        $permittedAdminId
    );
    $publishDecision = red_addon_component_editor_permission_decision(
        $connection,
        $manifest,
        $component,
        'publish',
        $permittedAdminId
    );
    red_addon_editor_auth_test_assert(
        $createDecision === [
            'authorized' => true,
            'actorRecordId' => $permittedAdminId,
            'component' => $component,
            'operation' => 'create',
            'permission' => 'fixture.items.create',
            'reason' => 'authorized',
        ]
            && !empty($publishDecision['authorized'])
            && $publishDecision['permission'] === $longPermission,
        'a non-Owner receives only operations backed by exact fresh package grants'
    );

    $ownerDecision = red_addon_component_editor_permission_decision(
        $connection,
        $manifest,
        $component,
        'create',
        $ownerAdminId
    );
    red_addon_editor_auth_test_assert(
        empty($ownerDecision['authorized'])
            && $ownerDecision['reason'] === 'permission_denied'
            && $ownerDecision['permission'] === 'fixture.items.create',
        'Owner role and lifecycle grants do not imply package permission'
    );

    $caseDecision = red_addon_component_editor_permission_decision(
        $connection,
        $manifest,
        $component,
        'edit',
        $permittedAdminId
    );
    $otherDecision = red_addon_component_editor_permission_decision(
        $connection,
        $manifest,
        $component,
        'create',
        $otherAdminId
    );
    red_addon_editor_auth_test_assert(
        empty($caseDecision['authorized'])
            && $caseDecision['reason'] === 'permission_denied'
            && empty($otherDecision['authorized'])
            && $otherDecision['reason'] === 'permission_denied',
        'case drift and unrelated package grants do not satisfy an exact permission'
    );

    $invalidActor = red_addon_component_editor_permission_decision(
        $connection,
        $manifest,
        $component,
        'create',
        0
    );
    $invalidOperation = red_addon_component_editor_permission_decision(
        $connection,
        $manifest,
        $component,
        'execute',
        $permittedAdminId
    );
    $missingSchema = red_addon_component_editor_permission_decision(
        $connection,
        ['provides' => ['components' => [$component]]],
        $component,
        'create',
        $permittedAdminId
    );
    red_addon_editor_auth_test_assert(
        $invalidActor['reason'] === 'invalid_actor'
            && $invalidActor['permission'] === ''
            && $invalidOperation['reason'] === 'invalid_operation'
            && $invalidOperation['operation'] === ''
            && $missingSchema['reason'] === 'schema_unavailable'
            && $missingSchema['permission'] === '',
        'invalid actors, operations, and schemas fail closed without inferred grants'
    );

    red_addon_editor_auth_test_assert(
        red_addon_editor_auth_test_fingerprint(
            $connection,
            $fixtureIds
        ) === $before,
        'permission planning and decisions write no role, grant, package, or audit state'
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$permittedAdminId
           AND Capability='fixture.items.create'"
    );
    $revoked = red_addon_component_editor_permission_decision(
        $connection,
        $manifest,
        $component,
        'create',
        $permittedAdminId
    );
    red_addon_editor_auth_test_assert(
        empty($revoked['authorized'])
            && $revoked['reason'] === 'permission_denied',
        'revocation takes effect on the next fresh database decision'
    );

    red_addon_editor_auth_test_cleanup($connection, $fixtureIds);
    red_addon_editor_auth_test_assert(
        red_addon_editor_auth_test_scalar(
            $connection,
            "SELECT CONCAT_WS(
                ':',
                (SELECT COUNT(*) FROM RED_Admin
                 WHERE RecordID IN (
                   $permittedAdminId,
                   $ownerAdminId,
                   $otherAdminId
                 )),
                (SELECT COUNT(*) FROM RED_Admin_Roles
                 WHERE AdminRecordID IN (
                   $permittedAdminId,
                   $ownerAdminId,
                   $otherAdminId
                 )),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID IN (
                   $permittedAdminId,
                   $ownerAdminId,
                   $otherAdminId
                 ))
             )"
        ) === '0:0:0',
        'all disposable authorization actors, roles, and grants are removed'
    );

    printf(
        "Add-on component editor authorization self-test passed: %d assertions.\n",
        $assertions
    );
} catch (Throwable $throwable) {
    red_addon_editor_auth_test_cleanup($connection, $fixtureIds);
    fwrite(
        STDERR,
        'Add-on component editor authorization self-test failed: '
            . $throwable->getMessage()
            . ' (after ' . $assertions . " assertions)\n"
    );
    mysqli_close($connection);
    exit(1);
}

mysqli_close($connection);
