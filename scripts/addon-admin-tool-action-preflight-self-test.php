<?php
/**
 * Disposable database checks for non-executing administrator tool actions.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/admin/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_admin_tool_action_preflight_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_admin_tool_action|rev_base)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on administrator tool action preflight refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$calls = ['tool' => 0, 'action' => 0];
$actorId = 2147000967;
$toolId = 'redcms.tool-action-fixture/orders';
$actionId = 'redcms.tool-action-fixture/mark-paid';
$permission = 'fixture.orders.transition';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_admin_tool_action_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_admin_tool_action_test_execute(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare administrator action fixture SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Administrator action fixture SQL failed: ' . $error);
    }
    mysqli_stmt_close($statement);
}

function red_addon_admin_tool_action_test_manifest(
    $toolId,
    $actionId,
    $permission,
    $description = 'Record a manual payment for one order.'
) {
    return [
        'id' => 'redcms.tool-action-fixture',
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [$toolId],
            'adapters' => [],
        ],
        'permissions' => [$permission],
        'componentEditors' => [],
        'adminToolActionContracts' => [[
            'tool' => $toolId,
            'action' => $actionId,
            'label' => 'Mark paid',
            'description' => $description,
            'permission' => $permission,
            'method' => 'POST',
            'csrf' => 'required',
        ]],
        'routes' => [],
    ];
}

function red_addon_admin_tool_action_test_context(
    $toolId,
    $actionId,
    $permission,
    callable $toolHandler,
    callable $actionHandler,
    $description = 'Record a manual payment for one order.'
) {
    $manifest = red_addon_admin_tool_action_test_manifest(
        $toolId,
        $actionId,
        $permission,
        $description
    );
    $registry = new RED_Addon_Runtime_Registry(
        'redcms.tool-action-fixture',
        $manifest
    );
    $registry->registerAdminTool($toolId, $toolHandler);
    $registry->registerAdminToolAction($actionId, $actionHandler);
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        ['redcms.tool-action-fixture'],
        ['redcms.tool-action-fixture' => $registry]
    );
}

function red_addon_admin_tool_action_test_counts($connection, $actorId)
{
    $result = mysqli_query(
        $connection,
        'SELECT CONCAT_WS(\':\',
            (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=' . (int) $actorId . '),
            (SELECT COUNT(*) FROM RED_Admin_Roles WHERE AdminRecordID=' . (int) $actorId . '),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities WHERE AdminRecordID=' . (int) $actorId . ')
        ) AS Counts'
    );
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return (string) ($row['Counts'] ?? '');
}

function red_addon_admin_tool_action_test_cleanup($connection, $actorId)
{
    try {
        red_addon_admin_tool_action_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_tool_action_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_tool_action_test_execute(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=?',
            'i',
            [$actorId]
        );
    } catch (Throwable $throwable) {
        error_log(
            'Add-on administrator action cleanup failed: ' .
            $throwable->getMessage()
        );
    }
}

try {
    red_addon_admin_tool_action_test_cleanup($connection, $actorId);
    red_addon_admin_tool_action_test_assert(
        red_addon_component_editor_permission_storage_available($connection),
        'fresh package-permission storage is available in the disposable database'
    );

    $password = password_hash('AddonToolAction-2026!', PASSWORD_DEFAULT);
    red_addon_admin_tool_action_test_execute(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_tool_action', ?, 'Admin',
            'ToolAction', 'webmaster', '', '', 'addon-tool-action@example.test',
            'N', 'to', 'N', 'to')",
        'is',
        [$actorId, $password]
    );

    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $missing = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7001
    );
    red_addon_admin_tool_action_test_assert(
        empty($missing['authorized'])
            && empty($missing['ready'])
            && empty($missing['invoked'])
            && $missing['reason'] === 'action_unavailable',
        'an action cannot preflight without request-local enabled ownership'
    );

    $toolHandler = static function () use (&$calls) {
        $calls['tool']++;
        throw new RuntimeException('tool handlers stay display-only and uninvoked');
    };
    $actionHandler = static function () use (&$calls) {
        $calls['action']++;
        throw new RuntimeException('action handlers must not run during preflight');
    };
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_tool_action_test_context(
            $toolId,
            $actionId,
            $permission,
            $toolHandler,
            $actionHandler
        );

    $denied = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7001
    );
    red_addon_admin_tool_action_test_assert(
        empty($denied['authorized'])
            && empty($denied['ready'])
            && empty($denied['invoked'])
            && $denied['reason'] === 'permission_denied'
            && $calls === ['tool' => 0, 'action' => 0],
        'a missing exact action permission refuses before either package callback'
    );

    red_addon_admin_tool_action_test_execute(
        $connection,
        "INSERT INTO RED_Admin_Roles (
            AdminRecordID, RoleName, AssignedByAdminRecordID
         ) VALUES (?, 'owner', ?)",
        'ii',
        [$actorId, $actorId]
    );
    red_addon_admin_tool_action_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, 'addons.enable', $actorId]
    );
    $ownerDenied = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7001
    );
    red_addon_admin_tool_action_test_assert(
        empty($ownerDenied['authorized'])
            && empty($ownerDenied['ready'])
            && $calls === ['tool' => 0, 'action' => 0],
        'Owner and lifecycle authority do not imply administrator action access'
    );

    red_addon_admin_tool_action_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    red_addon_admin_tool_action_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, 'Fixture.orders.transition', $actorId]
    );
    $caseDenied = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7001
    );
    red_addon_admin_tool_action_test_assert(
        empty($caseDenied['authorized'])
            && empty($caseDenied['ready'])
            && $calls === ['tool' => 0, 'action' => 0],
        'case-drifted package permission cannot authorize an action preflight'
    );

    red_addon_admin_tool_action_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    red_addon_admin_tool_action_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );
    $countsBefore = red_addon_admin_tool_action_test_counts($connection, $actorId);
    $ready = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7001
    );
    $repeat = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7001
    );
    $countsAfter = red_addon_admin_tool_action_test_counts($connection, $actorId);
    red_addon_admin_tool_action_test_assert(
        $ready['authorized'] === true
            && $ready['ready'] === true
            && $ready['invoked'] === false
            && $ready['package'] === 'redcms.tool-action-fixture'
            && $ready['permission'] === $permission
            && $ready['method'] === 'POST'
            && $ready['csrf'] === 'required'
            && $ready['actorRecordId'] === $actorId
            && $ready['targetRecordId'] === 7001
            && red_addon_valid_sha256($ready['contractSha256'])
            && red_addon_valid_sha256($ready['planSha256'])
            && $ready['reason'] === 'preflight_ready'
            && $ready['planSha256'] === $repeat['planSha256']
            && $countsBefore === '1:1:1'
            && $countsAfter === $countsBefore
            && $calls === ['tool' => 0, 'action' => 0],
        'the exact grant produces stable value-free action evidence without callbacks or database mutation'
    );

    $otherTarget = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7002
    );
    red_addon_admin_tool_action_test_assert(
        $otherTarget['ready'] === true
            && $otherTarget['planSha256'] !== $ready['planSha256']
            && $calls === ['tool' => 0, 'action' => 0],
        'the deterministic plan binds the exact numeric target record'
    );

    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_tool_action_test_context(
            $toolId,
            $actionId,
            $permission,
            $toolHandler,
            $actionHandler,
            'Record a verified manual payment for one order.'
        );
    $contractDrift = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7001
    );
    red_addon_admin_tool_action_test_assert(
        $contractDrift['ready'] === true
            && $contractDrift['contractSha256'] !== $ready['contractSha256']
            && $contractDrift['planSha256'] !== $ready['planSha256']
            && $calls === ['tool' => 0, 'action' => 0],
        'the deterministic plan changes when current action-contract evidence drifts'
    );

    foreach (
        [
            [$toolId, $actionId, $actorId, 0],
            [$toolId, $actionId, $actorId, '7001'],
            ['../tool', $actionId, $actorId, 7001],
            [$toolId, '../action', 0, 7001],
        ]
        as [$candidateTool, $candidateAction, $candidateActor, $candidateTarget]
    ) {
        $invalid = red_addon_admin_tool_action_preflight(
            $connection,
            $candidateTool,
            $candidateAction,
            $candidateActor,
            $candidateTarget
        );
        red_addon_admin_tool_action_test_assert(
            empty($invalid['authorized'])
                && empty($invalid['ready'])
                && empty($invalid['invoked'])
                && $invalid['reason'] === 'invalid_request'
                && $calls === ['tool' => 0, 'action' => 0],
            'invalid action identities and target input fail before callback invocation'
        );
    }

    $badManifest = red_addon_admin_tool_action_test_manifest(
        $toolId,
        $actionId,
        $permission
    );
    $badManifest['adminToolActionContracts'][0]['method'] = 'GET';
    red_addon_admin_tool_action_test_assert(
        red_addon_admin_tool_action_contract(
            $badManifest,
            $toolId,
            $actionId
        ) === null,
        'a forged non-POST action contract cannot enter preflight binding'
    );

    red_addon_admin_tool_action_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    $revoked = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7001
    );
    red_addon_admin_tool_action_test_assert(
        empty($revoked['authorized'])
            && empty($revoked['ready'])
            && $revoked['reason'] === 'permission_denied'
            && $calls === ['tool' => 0, 'action' => 0],
        'permission revocation applies on the next action preflight'
    );

    $helperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_admin_tool_action_preflight_helpers.php'
    );
    $endpointSource = (string) file_get_contents(
        $projectRoot . '/admin/bin/view_addon_tool.php'
    );
    red_addon_admin_tool_action_test_assert(
        !str_contains($helperSource, '$_POST')
            && !str_contains($helperSource, '$_SESSION')
            && !str_contains($helperSource, 'red_addon_runtime_request_bootstrap(')
            && preg_match(
                '/\b(?:START TRANSACTION|COMMIT|ROLLBACK|INSERT|UPDATE|DELETE)\b/i',
                $helperSource
            ) !== 1
            && !str_contains($endpointSource, 'addon_admin_tool_action_preflight')
            && !str_contains($endpointSource, 'adminToolActions'),
        'the preflight has no request, transaction, action endpoint, or operational write surface'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_admin_tool_action_test_cleanup($connection, $actorId);
    red_addon_admin_tool_action_test_assert(
        red_addon_admin_tool_action_test_counts($connection, $actorId) === '0:0:0',
        'administrator, role, and grant fixtures are removed exactly'
    );
    $db->close();
}

fwrite(
    STDOUT,
    'Add-on administrator tool action preflight self-test passed (' .
        $assertions . " assertions).\n"
);

?>
