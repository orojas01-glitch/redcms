<?php
/**
 * Disposable checks for the atomic administrator action runner and its
 * protected endpoint bridge.
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
require_once $projectRoot . '/includes/addon_admin_tool_action_endpoint_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_admin_tool_action_exec|rev_base)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on administrator action runner refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000966;
$packageId = 'redcms.tool-action-fixture';
$toolId = 'redcms.tool-action-fixture/orders';
$actionId = 'redcms.tool-action-fixture/mark-paid';
$permission = 'fixture.orders.transition';
$calls = ['tool' => 0, 'action' => 0, 'loader' => 0];
$handlerMode = 'complete';
$loaderMode = 'normal';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_admin_tool_action_execution_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_admin_tool_action_execution_test_execute(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare administrator action runner SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Administrator action runner SQL failed: ' . $error);
    }
    mysqli_stmt_close($statement);
}

function red_addon_admin_tool_action_execution_test_scalar(
    $connection,
    $sql,
    $types = '',
    array $values = []
) {
    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        throw new RuntimeException('Could not prepare administrator action runner scalar SQL.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Administrator action runner scalar SQL failed: ' . $error);
    }
    $result = mysqli_stmt_get_result($statement);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    mysqli_stmt_close($statement);
    return $row[0] ?? null;
}

function red_addon_admin_tool_action_execution_test_status($connection, $targetRecordId)
{
    $status = red_addon_admin_tool_action_execution_test_scalar(
        $connection,
        'SELECT Status FROM RED_Addon_Admin_Action_Fixture
         WHERE TargetRecordID=? LIMIT 1',
        'i',
        [$targetRecordId]
    );
    return is_string($status) ? $status : '';
}

function red_addon_admin_tool_action_execution_test_manifest(
    $toolId,
    $actionId,
    $permission
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
            'description' => 'Record one verified manual payment.',
            'permission' => $permission,
            'method' => 'POST',
            'csrf' => 'required',
            'idempotency' => 'once-per-target',
        ]],
        'routes' => [],
    ];
}

function red_addon_admin_tool_action_execution_test_context(
    $toolId,
    $actionId,
    $permission,
    callable $toolHandler,
    callable $actionHandler,
    callable $stateLoader
) {
    $registry = new RED_Addon_Runtime_Registry(
        'redcms.tool-action-fixture',
        red_addon_admin_tool_action_execution_test_manifest(
            $toolId,
            $actionId,
            $permission
        )
    );
    $registry->registerAdminTool($toolId, $toolHandler);
    $registry->registerAdminToolAction(
        $actionId,
        $actionHandler,
        ['RED_Addon_Admin_Action_Fixture']
    );
    $registry->registerAdminToolActionStateLoader($actionId, $stateLoader);
    $registry->assertComplete();
    return new RED_Addon_Runtime_Context(
        ['redcms.tool-action-fixture'],
        ['redcms.tool-action-fixture' => $registry]
    );
}

function red_addon_admin_tool_action_execution_test_ledger_count(
    $connection,
    $packageId,
    $actionId = null,
    $targetRecordId = null
) {
    $where = ['PackageID=?'];
    $types = 's';
    $values = [$packageId];
    if (is_string($actionId)) {
        $where[] = 'ActionID=?';
        $types .= 's';
        $values[] = $actionId;
    }
    if (is_int($targetRecordId)) {
        $where[] = 'TargetRecordID=?';
        $types .= 'i';
        $values[] = $targetRecordId;
    }
    return (int) red_addon_admin_tool_action_execution_test_scalar(
        $connection,
        'SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions WHERE ' .
            implode(' AND ', $where),
        $types,
        $values
    );
}

function red_addon_admin_tool_action_execution_test_audit_count(
    $connection,
    $packageId
) {
    return (int) red_addon_admin_tool_action_execution_test_scalar(
        $connection,
        "SELECT COUNT(*) FROM RED_Addon_Activity_Log
         WHERE PackageID=? AND EventName='addon.action.completed'
           AND Result='succeeded' AND DetailCode='action_completed'",
        's',
        [$packageId]
    );
}

function red_addon_admin_tool_action_execution_test_cleanup(
    $connection,
    $packageId,
    $actorId
) {
    try {
        red_addon_admin_tool_action_execution_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Admin_Action_Executions WHERE PackageID=?',
            's',
            [$packageId]
        );
        red_addon_admin_tool_action_execution_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Activity_Log WHERE PackageID=?',
            's',
            [$packageId]
        );
        red_addon_admin_tool_action_execution_test_execute(
            $connection,
            'DELETE FROM RED_Addon_Installations WHERE PackageID=?',
            's',
            [$packageId]
        );
        red_addon_admin_tool_action_execution_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_tool_action_execution_test_execute(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=?',
            'i',
            [$actorId]
        );
        red_addon_admin_tool_action_execution_test_execute(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=?',
            'i',
            [$actorId]
        );
        mysqli_query(
            $connection,
            'DROP TABLE IF EXISTS RED_Addon_Admin_Action_Fixture'
        );
    } catch (Throwable $throwable) {
        error_log(
            'Add-on administrator action runner cleanup failed: ' .
                $throwable->getMessage()
        );
    }
}

try {
    red_addon_admin_tool_action_execution_test_cleanup(
        $connection,
        $packageId,
        $actorId
    );
    red_addon_admin_tool_action_execution_test_assert(
        red_addon_admin_tool_action_execution_storage_available($connection),
        'the fresh disposable database exposes the exact administrator action ledger'
    );
    if (!mysqli_query(
        $connection,
        'CREATE TABLE RED_Addon_Admin_Action_Fixture (
            TargetRecordID int unsigned NOT NULL,
            Status varchar(32) NOT NULL,
            PRIMARY KEY (TargetRecordID)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    )) {
        throw new RuntimeException('Could not create action package fixture table.');
    }

    $password = password_hash('AddonToolActionRunner-2026!', PASSWORD_DEFAULT);
    red_addon_admin_tool_action_execution_test_execute(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_tool_action_runner', ?, 'Admin',
            'ActionRun', 'webmaster', '', '',
            'addon-tool-action-runner@example.test', 'N', 'to', 'N', 'to')",
        'is',
        [$actorId, $password]
    );
    red_addon_admin_tool_action_execution_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );
    $packageVersion = '0.1.0';
    $manifestHash = str_repeat('a', 64);
    $inventoryHash = str_repeat('b', 64);
    red_addon_admin_tool_action_execution_test_execute(
        $connection,
        "INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState, InstalledByAdminRecordID,
            UpdatedByAdminRecordID
         ) VALUES (?, ?, 'content-package', ?, ?, 'enabled', ?, ?)",
        'ssssii',
        [
            $packageId,
            $packageVersion,
            $manifestHash,
            $inventoryHash,
            $actorId,
            $actorId,
        ]
    );
    foreach (
        [7101, 7102, 7103, 7104, 7105, 7106, 7107, 7108, 7109, 7110, 7111, 7112]
        as $targetId
    ) {
        red_addon_admin_tool_action_execution_test_execute(
            $connection,
            "INSERT INTO RED_Addon_Admin_Action_Fixture (TargetRecordID, Status)
             VALUES (?, 'pending')",
            'i',
            [$targetId]
        );
    }

    $toolHandler = static function () use (&$calls) {
        $calls['tool']++;
        throw new RuntimeException('display tool callback must remain uninvoked');
    };
    $stateLoader = static function ($connection, $request) use (&$calls, &$loaderMode) {
        $calls['loader']++;
        if ($loaderMode === 'output') {
            echo 'state-loader-output';
        }
        $status = red_addon_admin_tool_action_execution_test_status(
            $connection,
            $request->targetRecordId()
        );
        return new RED_Addon_Admin_Tool_Action_Target_State(
            $request->targetRecordId(),
            ['status' => $status]
        );
    };
    $actionHandler = static function ($connection, $request) use (&$calls, &$handlerMode) {
        $calls['action']++;
        $targetId = $request->targetRecordId();
        if ($handlerMode === 'throw') {
            red_addon_admin_tool_action_execution_test_execute(
                $connection,
                "UPDATE RED_Addon_Admin_Action_Fixture SET Status='completed'
                 WHERE TargetRecordID=?",
                'i',
                [$targetId]
            );
            throw new RuntimeException('fixture action failure');
        }
        if ($handlerMode === 'output') {
            red_addon_admin_tool_action_execution_test_execute(
                $connection,
                "UPDATE RED_Addon_Admin_Action_Fixture SET Status='completed'
                 WHERE TargetRecordID=?",
                'i',
                [$targetId]
            );
            echo 'handler-output';
            return RED_Addon_Admin_Tool_Action_Execution_Result::changed(
                new RED_Addon_Admin_Tool_Action_Target_State(
                    $targetId,
                    ['status' => 'completed']
                )
            );
        }
        if ($handlerMode === 'bad-result') {
            red_addon_admin_tool_action_execution_test_execute(
                $connection,
                "UPDATE RED_Addon_Admin_Action_Fixture SET Status='completed'
                 WHERE TargetRecordID=?",
                'i',
                [$targetId]
            );
            return true;
        }
        if ($handlerMode === 'wrong-state') {
            red_addon_admin_tool_action_execution_test_execute(
                $connection,
                "UPDATE RED_Addon_Admin_Action_Fixture SET Status='completed'
                 WHERE TargetRecordID=?",
                'i',
                [$targetId]
            );
            return RED_Addon_Admin_Tool_Action_Execution_Result::changed(
                new RED_Addon_Admin_Tool_Action_Target_State(
                    $targetId,
                    ['status' => 'different']
                )
            );
        }
        if ($handlerMode === 'unchanged') {
            return RED_Addon_Admin_Tool_Action_Execution_Result::unchanged(
                new RED_Addon_Admin_Tool_Action_Target_State(
                    $targetId,
                    ['status' => red_addon_admin_tool_action_execution_test_status(
                        $connection,
                        $targetId
                    )]
                )
            );
        }
        if ($handlerMode === 'changed-but-unchanged') {
            red_addon_admin_tool_action_execution_test_execute(
                $connection,
                "UPDATE RED_Addon_Admin_Action_Fixture SET Status='completed'
                 WHERE TargetRecordID=?",
                'i',
                [$targetId]
            );
            return RED_Addon_Admin_Tool_Action_Execution_Result::unchanged(
                new RED_Addon_Admin_Tool_Action_Target_State(
                    $targetId,
                    ['status' => 'completed']
                )
            );
        }
        red_addon_admin_tool_action_execution_test_execute(
            $connection,
            "UPDATE RED_Addon_Admin_Action_Fixture SET Status='completed'
             WHERE TargetRecordID=?",
            'i',
            [$targetId]
        );
        return RED_Addon_Admin_Tool_Action_Execution_Result::changed(
            new RED_Addon_Admin_Tool_Action_Target_State(
                $targetId,
                ['status' => 'completed']
            )
        );
    };
    $GLOBALS['RED_ADDON_RUNTIME_CONTEXT'] =
        red_addon_admin_tool_action_execution_test_context(
            $toolId,
            $actionId,
            $permission,
            $toolHandler,
            $actionHandler,
            $stateLoader
        );

    $oldLoaderCalls = $calls['loader'];
    $metadataOnly = red_addon_admin_tool_action_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7101
    );
    red_addon_admin_tool_action_execution_test_assert(
        !empty($metadataOnly['ready'])
            && $metadataOnly['idempotency'] === 'once-per-target'
            && $calls['loader'] === $oldLoaderCalls
            && $calls['action'] === 0,
        'the former metadata preflight remains non-executing after the runner contract expands'
    );

    $loaderCallsBeforeDirectPlan = $calls['loader'];
    $directPlan = red_addon_admin_tool_action_execution_plan(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7101
    );
    red_addon_admin_tool_action_execution_test_assert(
        empty($directPlan['ready'])
            && $directPlan['reason'] === 'transaction_required'
            && $calls['loader'] === $loaderCallsBeforeDirectPlan,
        'the state-aware internal plan refuses to run a loader outside a core-owned transaction'
    );

    $plan = red_addon_admin_tool_action_execution_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7101
    );
    red_addon_admin_tool_action_execution_test_assert(
        $plan['authorized'] === true
            && $plan['ready'] === true
            && $plan['stateLoaderInvoked'] === true
            && $plan['executed'] === false
            && $plan['package'] === $packageId
            && $plan['packageVersion'] === $packageVersion
            && $plan['idempotency'] === 'once-per-target'
            && red_addon_valid_sha256($plan['metadataPlanSha256'])
            && red_addon_valid_sha256($plan['previousStateSha256'])
            && red_addon_valid_sha256($plan['planSha256'])
            && !array_key_exists('state', $plan)
            && red_addon_admin_tool_action_execution_test_status($connection, 7101)
                === 'pending'
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId
            ) === 0
            && red_addon_admin_tool_action_execution_test_audit_count(
                $connection,
                $packageId
            ) === 0,
        'state preflight returns only exact value-free evidence and rolls back its loader transaction'
    );

    $stalePlan = red_addon_admin_tool_action_execution_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7102
    );
    red_addon_admin_tool_action_execution_test_execute(
        $connection,
        "UPDATE RED_Addon_Admin_Action_Fixture SET Status='externally-changed'
         WHERE TargetRecordID=7102"
    );
    $beforeStaleActionCalls = $calls['action'];
    $stale = red_addon_admin_tool_action_execute(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7102,
        $stalePlan['planSha256']
    );
    red_addon_admin_tool_action_execution_test_assert(
        empty($stale['executed'])
            && $stale['reason'] === 'plan_mismatch'
            && $calls['action'] === $beforeStaleActionCalls
            && red_addon_admin_tool_action_execution_test_status($connection, 7102)
                === 'externally-changed'
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId,
                $actionId,
                7102
            ) === 0,
        'a target-state change after preflight refuses before the package action callback'
    );

    $executed = red_addon_admin_tool_action_execute(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7101,
        $plan['planSha256']
    );
    red_addon_admin_tool_action_execution_test_assert(
        $executed['executed'] === true
            && empty($executed['unchanged'])
            && $executed['reason'] === 'executed'
            && $executed['previousStateSha256'] === $plan['previousStateSha256']
            && red_addon_valid_sha256($executed['stateSha256'])
            && $executed['stateSha256'] !== $executed['previousStateSha256']
            && !array_key_exists('state', $executed)
            && red_addon_admin_tool_action_execution_test_status($connection, 7101)
                === 'completed'
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId,
                $actionId,
                7101
            ) === 1
            && red_addon_admin_tool_action_execution_test_audit_count(
                $connection,
                $packageId
            ) === 1,
        'the matching exact plan performs one package mutation, ledger record, and value-free audit fact atomically'
    );

    $loaderCallsBeforeReplay = $calls['loader'];
    $actionCallsBeforeReplay = $calls['action'];
    $replay = red_addon_admin_tool_action_execution_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7101
    );
    $replayRun = red_addon_admin_tool_action_execute(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7101,
        $plan['planSha256']
    );
    red_addon_admin_tool_action_execution_test_assert(
        empty($replay['ready'])
            && $replay['reason'] === 'already_executed'
            && empty($replayRun['executed'])
            && $replayRun['reason'] === 'already_executed'
            && $calls['loader'] === $loaderCallsBeforeReplay
            && $calls['action'] === $actionCallsBeforeReplay
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId,
                $actionId,
                7101
            ) === 1
            && red_addon_admin_tool_action_execution_test_audit_count(
                $connection,
                $packageId
            ) === 1,
        'the per-client ledger refuses an action replay before state loading or package execution'
    );

    $revokedPlan = red_addon_admin_tool_action_execution_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7103
    );
    red_addon_admin_tool_action_execution_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    $actionCallsBeforeRevocation = $calls['action'];
    $revoked = red_addon_admin_tool_action_execute(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7103,
        $revokedPlan['planSha256']
    );
    red_addon_admin_tool_action_execution_test_assert(
        empty($revoked['executed'])
            && $revoked['reason'] === 'permission_denied'
            && $calls['action'] === $actionCallsBeforeRevocation
            && red_addon_admin_tool_action_execution_test_status($connection, 7103)
                === 'pending'
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId,
                $actionId,
                7103
            ) === 0,
        'fresh exact grant revocation refuses the runner before the action callback'
    );
    red_addon_admin_tool_action_execution_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );

    foreach (
        [
            ['output', 7104, 'action_failed'],
            ['throw', 7105, 'action_failed'],
            ['bad-result', 7106, 'action_failed'],
            ['wrong-state', 7107, 'postcondition_failed'],
            ['changed-but-unchanged', 7108, 'action_result_invalid'],
        ] as [$mode, $targetId, $expectedReason]
    ) {
        $handlerMode = $mode;
        $failurePlan = red_addon_admin_tool_action_execution_preflight(
            $connection,
            $toolId,
            $actionId,
            $actorId,
            $targetId
        );
        $failure = red_addon_admin_tool_action_execute(
            $connection,
            $toolId,
            $actionId,
            $actorId,
            $targetId,
            $failurePlan['planSha256']
        );
        red_addon_admin_tool_action_execution_test_assert(
            empty($failure['executed'])
                && $failure['reason'] === $expectedReason
                && red_addon_admin_tool_action_execution_test_status(
                    $connection,
                    $targetId
                ) === 'pending'
                && red_addon_admin_tool_action_execution_test_ledger_count(
                    $connection,
                    $packageId,
                    $actionId,
                    $targetId
                ) === 0
                && red_addon_admin_tool_action_execution_test_audit_count(
                    $connection,
                    $packageId
                ) === 1,
            'output, exceptions, malformed results, and failed postconditions roll package state, reservation, and audit evidence back'
        );
    }
    $handlerMode = 'unchanged';
    $unchangedPlan = red_addon_admin_tool_action_execution_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7103
    );
    $unchanged = red_addon_admin_tool_action_execute(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7103,
        $unchangedPlan['planSha256']
    );
    red_addon_admin_tool_action_execution_test_assert(
        empty($unchanged['executed'])
            && $unchanged['unchanged'] === true
            && $unchanged['reason'] === 'unchanged'
            && red_addon_admin_tool_action_execution_test_status($connection, 7103)
                === 'pending'
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId,
                $actionId,
                7103
            ) === 0
            && red_addon_admin_tool_action_execution_test_audit_count(
                $connection,
                $packageId
            ) === 1,
        'an explicitly unchanged action releases its reservation without consuming the target idempotency slot'
    );
    $handlerMode = 'complete';

    $loaderMode = 'output';
    $actionCallsBeforeLoaderFailure = $calls['action'];
    $loaderFailure = red_addon_admin_tool_action_execution_preflight(
        $connection,
        $toolId,
        $actionId,
        $actorId,
        7103
    );
    $loaderMode = 'normal';
    red_addon_admin_tool_action_execution_test_assert(
        empty($loaderFailure['ready'])
            && $loaderFailure['reason'] === 'state_loader_failed'
            && $calls['action'] === $actionCallsBeforeLoaderFailure
            && red_addon_admin_tool_action_execution_test_status($connection, 7103)
                === 'pending'
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId,
                $actionId,
                7103
            ) === 0,
        'state-loader output is contained and refuses before package action execution'
    );

    foreach (
        [
            [$toolId, $actionId, '1', 7103, str_repeat('c', 64)],
            [$toolId, $actionId, $actorId, '7103', str_repeat('c', 64)],
            [$toolId, $actionId, $actorId, 7103, 'not-a-hash'],
            ['../tool', $actionId, $actorId, 7103, str_repeat('c', 64)],
        ] as [$candidateTool, $candidateAction, $candidateActor, $candidateTarget, $candidatePlan]
    ) {
        $invalid = red_addon_admin_tool_action_execute(
            $connection,
            $candidateTool,
            $candidateAction,
            $candidateActor,
            $candidateTarget,
            $candidatePlan
        );
        red_addon_admin_tool_action_execution_test_assert(
            empty($invalid['executed'])
                && $calls['tool'] === 0,
            'invalid internal runner input never invokes a display tool or action callback'
        );
    }

    $callsBeforeRequestValidation = $calls;
    foreach (
        [
            [
                'tool' => $toolId,
                'action' => $actionId,
                'targetRecordId' => '07109',
                'csrf_token' => str_repeat('c', 64),
            ],
            [
                'tool' => $toolId,
                'action' => $actionId,
                'targetRecordId' => '7109',
                'csrf_token' => '',
            ],
            [
                'tool' => $toolId,
                'action' => $actionId,
                'targetRecordId' => '7109',
                'csrf_token' => str_repeat('c', 64),
                'package' => $packageId,
            ],
            [
                'tool' => '../tool',
                'action' => $actionId,
                'targetRecordId' => '7109',
                'csrf_token' => str_repeat('c', 64),
            ],
        ] as $candidate
    ) {
        red_addon_admin_tool_action_execution_test_assert(
            red_addon_admin_tool_action_endpoint_request($candidate) === null,
            'the endpoint accepts no loose, extra, or caller-owned action fields'
        );
    }
    $endpointRequest = red_addon_admin_tool_action_endpoint_request([
        'tool' => $toolId,
        'action' => $actionId,
        'targetRecordId' => '7109',
        'csrf_token' => str_repeat('c', 64),
    ]);
    red_addon_admin_tool_action_execution_test_assert(
        is_array($endpointRequest)
            && $endpointRequest === [
                'tool' => $toolId,
                'action' => $actionId,
                'targetRecordId' => 7109,
            ]
            && $calls === $callsBeforeRequestValidation,
        'the endpoint parser retains only exact server-safe action identities after outer CSRF validation'
    );

    $invalidEndpointActor = red_addon_admin_tool_action_endpoint_dispatch(
        $connection,
        $endpointRequest,
        '2147000966'
    );
    red_addon_admin_tool_action_execution_test_assert(
        $invalidEndpointActor === [
            'httpStatus' => 400,
            'ok' => false,
            'status' => '',
            'reason' => 'invalid_request',
        ]
            && $calls === $callsBeforeRequestValidation,
        'the endpoint bridge accepts the actor only from a current integer session identity'
    );

    $callsBeforeEndpointExecution = $calls;
    $auditBeforeEndpointExecution = red_addon_admin_tool_action_execution_test_audit_count(
        $connection,
        $packageId
    );
    $endpointExecuted = red_addon_admin_tool_action_endpoint_dispatch(
        $connection,
        $endpointRequest,
        $actorId
    );
    $endpointExecutedBody = red_addon_admin_tool_action_endpoint_public_body(
        $endpointExecuted
    );
    red_addon_admin_tool_action_execution_test_assert(
        $endpointExecuted === [
            'httpStatus' => 200,
            'ok' => true,
            'status' => 'executed',
            'reason' => '',
        ]
            && $endpointExecutedBody === [
                'ok' => true,
                'status' => 'executed',
            ]
            && $calls['tool'] === $callsBeforeEndpointExecution['tool']
            && $calls['action'] === $callsBeforeEndpointExecution['action'] + 1
            && $calls['loader'] > $callsBeforeEndpointExecution['loader']
            && red_addon_admin_tool_action_execution_test_status($connection, 7109)
                === 'completed'
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId,
                $actionId,
                7109
            ) === 1
            && red_addon_admin_tool_action_execution_test_audit_count(
                $connection,
                $packageId
            ) === $auditBeforeEndpointExecution + 1,
        'the endpoint derives the current plan server-side and returns only a bounded executed outcome'
    );

    $callsBeforeEndpointReplay = $calls;
    $endpointReplay = red_addon_admin_tool_action_endpoint_dispatch(
        $connection,
        $endpointRequest,
        $actorId
    );
    red_addon_admin_tool_action_execution_test_assert(
        $endpointReplay === [
            'httpStatus' => 409,
            'ok' => false,
            'status' => '',
            'reason' => 'already_executed',
        ]
            && red_addon_admin_tool_action_endpoint_public_body($endpointReplay)
                === ['ok' => false, 'reason' => 'already_executed']
            && $calls === $callsBeforeEndpointReplay,
        'the endpoint reports a bounded replay conflict before package state loading or action execution'
    );

    $handlerMode = 'unchanged';
    $endpointUnchanged = red_addon_admin_tool_action_endpoint_dispatch(
        $connection,
        red_addon_admin_tool_action_endpoint_request([
            'tool' => $toolId,
            'action' => $actionId,
            'targetRecordId' => '7110',
            'csrf_token' => str_repeat('c', 64),
        ]),
        $actorId
    );
    $handlerMode = 'complete';
    red_addon_admin_tool_action_execution_test_assert(
        $endpointUnchanged === [
            'httpStatus' => 200,
            'ok' => true,
            'status' => 'unchanged',
            'reason' => '',
        ]
            && red_addon_admin_tool_action_execution_test_status($connection, 7110)
                === 'pending'
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId,
                $actionId,
                7110
            ) === 0
            && red_addon_admin_tool_action_execution_test_audit_count(
                $connection,
                $packageId
            ) === $auditBeforeEndpointExecution + 1,
        'the endpoint retains an unchanged action as a safe success without consuming idempotency evidence'
    );

    red_addon_admin_tool_action_execution_test_execute(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
        'i',
        [$actorId]
    );
    $callsBeforeEndpointRevocation = $calls;
    $endpointRevoked = red_addon_admin_tool_action_endpoint_dispatch(
        $connection,
        red_addon_admin_tool_action_endpoint_request([
            'tool' => $toolId,
            'action' => $actionId,
            'targetRecordId' => '7111',
            'csrf_token' => str_repeat('c', 64),
        ]),
        $actorId
    );
    red_addon_admin_tool_action_execution_test_assert(
        $endpointRevoked === [
            'httpStatus' => 403,
            'ok' => false,
            'status' => '',
            'reason' => 'permission_denied',
        ]
            && $calls === $callsBeforeEndpointRevocation
            && red_addon_admin_tool_action_execution_test_status($connection, 7111)
                === 'pending',
        'the endpoint rechecks the exact current grant before package state loading or action execution'
    );
    red_addon_admin_tool_action_execution_test_execute(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)',
        'isi',
        [$actorId, $permission, $actorId]
    );

    $handlerMode = 'output';
    $endpointFailure = red_addon_admin_tool_action_endpoint_dispatch(
        $connection,
        red_addon_admin_tool_action_endpoint_request([
            'tool' => $toolId,
            'action' => $actionId,
            'targetRecordId' => '7112',
            'csrf_token' => str_repeat('c', 64),
        ]),
        $actorId
    );
    $handlerMode = 'complete';
    red_addon_admin_tool_action_execution_test_assert(
        $endpointFailure === [
            'httpStatus' => 422,
            'ok' => false,
            'status' => '',
            'reason' => 'action_failed',
        ]
            && red_addon_admin_tool_action_execution_test_status($connection, 7112)
                === 'pending'
            && red_addon_admin_tool_action_execution_test_ledger_count(
                $connection,
                $packageId,
                $actionId,
                7112
            ) === 0
            && red_addon_admin_tool_action_execution_test_audit_count(
                $connection,
                $packageId
            ) === $auditBeforeEndpointExecution + 1,
        'contained package output fails closed and exposes no package details through the endpoint'
    );

    $helperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_admin_tool_action_execution_helpers.php'
    );
    $endpointSource = (string) file_get_contents(
        $projectRoot . '/admin/bin/view_addon_tool.php'
    );
    $actionEndpointSource = (string) file_get_contents(
        $projectRoot . '/admin/bin/run_addon_tool_action.php'
    );
    $endpointHelperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_admin_tool_action_endpoint_helpers.php'
    );
    $chooserSource = (string) file_get_contents(
        $projectRoot . '/admin/class/class_add_tools.php'
    );
    red_addon_admin_tool_action_execution_test_assert(
        !str_contains($helperSource, '$_POST')
            && !str_contains($helperSource, '$_SESSION')
            && !str_contains($helperSource, 'REQUEST_URI')
            && !str_contains($endpointSource, 'addon_admin_tool_action_execute')
            && !str_contains($endpointSource, 'adminToolActions')
            && !str_contains($endpointHelperSource, '$_POST')
            && !str_contains($endpointHelperSource, '$_SESSION')
            && !str_contains($endpointHelperSource, 'REQUEST_URI')
            && str_contains($actionEndpointSource, 'red_require_admin(true)')
            && str_contains(
                $actionEndpointSource,
                'red_addon_runtime_request_bootstrap('
            )
            && str_contains(
                $actionEndpointSource,
                'red_addon_admin_tool_action_endpoint_dispatch('
            )
            && !str_contains(
                $actionEndpointSource,
                'red_addon_admin_tool_action_execute('
            )
            && !str_contains($chooserSource, 'run_addon_tool_action.php'),
        'the runner stays request-free while the protected endpoint is server-derived and unlinked from the administrator UI'
    );
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_admin_tool_action_execution_test_cleanup(
        $connection,
        $packageId,
        $actorId
    );
    $remainingRows = (int) red_addon_admin_tool_action_execution_test_scalar(
        $connection,
        "SELECT COUNT(*) FROM (
            SELECT PackageID AS Value FROM RED_Addon_Installations
              WHERE PackageID='redcms.tool-action-fixture'
            UNION ALL
            SELECT PackageID AS Value FROM RED_Addon_Admin_Action_Executions
              WHERE PackageID='redcms.tool-action-fixture'
            UNION ALL
            SELECT PackageID AS Value FROM RED_Addon_Activity_Log
              WHERE PackageID='redcms.tool-action-fixture'
         ) AS Remaining"
    );
    $fixtureTableCount = (int) red_addon_admin_tool_action_execution_test_scalar(
        $connection,
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME='RED_Addon_Admin_Action_Fixture'"
    );
    red_addon_admin_tool_action_execution_test_assert(
        $remainingRows === 0
            && $fixtureTableCount === 0
            && (int) red_addon_admin_tool_action_execution_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Admin WHERE RecordID=?',
                'i',
                [$actorId]
            ) === 0
            && (int) red_addon_admin_tool_action_execution_test_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Admin_Capabilities WHERE AdminRecordID=?',
                'i',
                [$actorId]
            ) === 0,
        'administrator, grant, installation, ledger, audit, and package table fixtures are removed exactly'
    );
    $db->close();
}

fwrite(
    STDOUT,
    'Add-on administrator tool action runner and endpoint helper self-test passed (' .
        $assertions . " assertions).\n"
);

?>
