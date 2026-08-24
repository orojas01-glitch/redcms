<?php
/** Disposable C4C3A durable merchant-read provider-double acceptance. */

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
    . '/includes/addon_payment_adapter_wompi_merchant_read_durable_helpers.php';

if (preg_match(
    '/\Aredcms_payment_adapter_db_c4c3a_[A-Za-z0-9_]+\z/D',
    (string) DBNAME
) !== 1
    || realpath((string) getenv('RED_WOMPI_C3B_PROJECT_ROOT'))
        !== realpath($projectRoot)
) {
    fwrite(STDERR, "C4C3A refused non-disposable fixture.\n");
    exit(65);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$actorId = 2147000995;
$assertions = 0;

function red_wompi_c4c3a_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c4c3a_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_wompi_c4c3a_chain(
    $connection,
    $projectRoot,
    $actorId,
    $now,
    $digit
) {
    $authorization = red_addon_wompi_merchant_durable_authorize(
        $connection,
        $projectRoot,
        $actorId,
        str_repeat($digit, 64),
        $now
    );
    $plan = red_addon_wompi_merchant_durable_plan(
        $connection,
        $projectRoot,
        $actorId,
        $authorization,
        $now
    );
    return [$authorization, $plan];
}

try {
    $now = time();
    [$authorization, $plan] = red_wompi_c4c3a_chain(
        $connection,
        $projectRoot,
        $actorId,
        $now,
        '1'
    );
    red_wompi_c4c3a_assert(
        red_addon_wompi_merchant_durable_authorization_valid(
            $authorization,
            $now
        )
            && $authorization['providerDoubleOnly']
            && !$authorization['realProviderContactAuthorized']
            && !$authorization['retryAuthorized'],
        'exact current-client evidence authorizes only one provider double'
    );
    red_wompi_c4c3a_assert(
        !empty($plan['ready'])
            && $plan['executionStartAvailable']
            && !$plan['executionStarted']
            && !$plan['replayProtectionActive']
            && red_addon_wompi_merchant_durable_request_valid(
                red_addon_wompi_merchant_durable_request($plan) ?? []
            ),
        'zero-write plan produces exact hash-only request and start state'
    );
    red_wompi_c4c3a_assert(
        red_wompi_c4c3a_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
             WHERE PackageID='redcms.store-lite-wompi'
               AND ActionID LIKE 'wompi-merchant-read-provider-%'"
        ) === '0',
        'planning writes no provider-double action row'
    );
    $double = new RED_Addon_Wompi_Merchant_Read_Provider_Double();
    $executed = red_addon_wompi_merchant_durable_execute(
        $connection,
        $projectRoot,
        $actorId,
        $authorization,
        $plan['executionStartStateSha256'],
        $double,
        $now
    );
    red_wompi_c4c3a_assert(
        $executed['status'] === 'merchant_provider_double_completed'
            && $double->callCount() === 1
            && $executed['executionStarted']
            && $executed['startAuditRecorded']
            && $executed['providerDoubleInvoked']
            && $executed['outcomeRecorded']
            && $executed['outcomeAuditRecorded']
            && $executed['replayProtectionActive'],
        'start commits before one provider double and bounded result commits'
    );
    $outcome = $executed['boundedOutcome'];
    red_wompi_c4c3a_assert(
        $outcome['simulationObserved']
            && !$outcome['responseBodyIncluded']
            && !$outcome['responseHeadersIncluded']
            && !$outcome['publicKeyIncluded']
            && !$outcome['rawTokensReturned']
            && !$outcome['networkAccess']
            && !$outcome['providerContact']
            && !$outcome['providerMutation']
            && !$outcome['transactionCreation']
            && !$outcome['payment']
            && !$outcome['eventRegistration']
            && !$outcome['orderMutation']
            && !$outcome['retryAuthorized'],
        'bounded outcome proves every real provider/business effect false'
    );
    red_wompi_c4c3a_assert(
        red_wompi_c4c3a_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND ActionID LIKE 'wompi-merchant-read-provider-%'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND DetailCode LIKE 'wompi_merchant_read_provider_double_%'),
                (SELECT COUNT(*)
                 FROM RED_Addon_StoreLite_Wompi_Payment_Attempts),
                (SELECT COUNT(*)
                 FROM RED_Addon_StoreLite_Wompi_Event_Receipts))"
        ) === '2:2:0:0',
        'exact start/result and audits persist with business tables empty'
    );
    $replayDouble = new RED_Addon_Wompi_Merchant_Read_Provider_Double();
    $replay = red_addon_wompi_merchant_durable_execute(
        $connection,
        $projectRoot,
        $actorId,
        $authorization,
        $plan['executionStartStateSha256'],
        $replayDouble,
        $now
    );
    red_wompi_c4c3a_assert(
        $replay['status'] === 'execution_already_started'
            && $replayDouble->callCount() === 0
            && $replay['replayProtectionActive'],
        'replay refuses before a second provider-double invocation'
    );

    [$changedAuthorization, $changedPlan] = red_wompi_c4c3a_chain(
        $connection,
        $projectRoot,
        $actorId,
        $now,
        '2'
    );
    $changedDouble = new RED_Addon_Wompi_Merchant_Read_Provider_Double();
    $changed = red_addon_wompi_merchant_durable_execute(
        $connection,
        $projectRoot,
        $actorId,
        $changedAuthorization,
        str_repeat('f', 64),
        $changedDouble,
        $now
    );
    red_wompi_c4c3a_assert(
        !$changed['executionStarted'] && $changedDouble->callCount() === 0,
        'changed expected start refuses before durability or invocation'
    );

    [$rollbackAuthorization, $rollbackPlan] = red_wompi_c4c3a_chain(
        $connection,
        $projectRoot,
        $actorId,
        $now,
        '3'
    );
    $rollbackDouble = new RED_Addon_Wompi_Merchant_Read_Provider_Double();
    $startFailed = red_addon_wompi_merchant_durable_execute(
        $connection,
        $projectRoot,
        $actorId,
        $rollbackAuthorization,
        $rollbackPlan['executionStartStateSha256'],
        $rollbackDouble,
        $now,
        static function (): bool { return false; }
    );
    red_wompi_c4c3a_assert(
        $startFailed['status'] === 'execution_start_audit_failed'
            && !$startFailed['executionStarted']
            && $rollbackDouble->callCount() === 0,
        'start-audit failure rolls back before provider-double invocation'
    );
    $recoveryDouble = new RED_Addon_Wompi_Merchant_Read_Provider_Double();
    $recovered = red_addon_wompi_merchant_durable_execute(
        $connection,
        $projectRoot,
        $actorId,
        $rollbackAuthorization,
        $rollbackPlan['executionStartStateSha256'],
        $recoveryDouble,
        $now
    );
    red_wompi_c4c3a_assert(
        $recovered['status'] === 'merchant_provider_double_completed'
            && $recoveryDouble->callCount() === 1,
        'rolled-back start permits one clean recovery'
    );

    [$spentAuthorization, $spentPlan] = red_wompi_c4c3a_chain(
        $connection,
        $projectRoot,
        $actorId,
        $now,
        '4'
    );
    $spentDouble = new RED_Addon_Wompi_Merchant_Read_Provider_Double();
    $outcomeFailed = red_addon_wompi_merchant_durable_execute(
        $connection,
        $projectRoot,
        $actorId,
        $spentAuthorization,
        $spentPlan['executionStartStateSha256'],
        $spentDouble,
        $now,
        null,
        static function (): bool { return false; }
    );
    red_wompi_c4c3a_assert(
        $outcomeFailed['status'] === 'outcome_audit_failed'
            && $outcomeFailed['executionStarted']
            && !$outcomeFailed['outcomeRecorded']
            && $spentDouble->callCount() === 1,
        'result-audit failure preserves spent start without result'
    );
    $spentReplayDouble = new RED_Addon_Wompi_Merchant_Read_Provider_Double();
    $spentReplay = red_addon_wompi_merchant_durable_execute(
        $connection,
        $projectRoot,
        $actorId,
        $spentAuthorization,
        $spentPlan['executionStartStateSha256'],
        $spentReplayDouble,
        $now
    );
    red_wompi_c4c3a_assert(
        $spentReplay['status'] === 'execution_already_started'
            && $spentReplayDouble->callCount() === 0,
        'post-start failure remains permanently no-retry'
    );

    foreach (['fault', 'malformed'] as $modeIndex => $mode) {
        $digit = (string) (5 + $modeIndex);
        [$failureAuthorization, $failurePlan] = red_wompi_c4c3a_chain(
            $connection,
            $projectRoot,
            $actorId,
            $now,
            $digit
        );
        $failureDouble =
            new RED_Addon_Wompi_Merchant_Read_Provider_Double($mode);
        $failure = red_addon_wompi_merchant_durable_execute(
            $connection,
            $projectRoot,
            $actorId,
            $failureAuthorization,
            $failurePlan['executionStartStateSha256'],
            $failureDouble,
            $now
        );
        red_wompi_c4c3a_assert(
            $failure['status'] === 'merchant_provider_double_indeterminate'
                && $failureDouble->callCount() === 1
                && $failure['outcomeRecorded']
                && !$failure['boundedOutcome']['networkAccess']
                && !$failure['boundedOutcome']['retryAuthorized'],
            $mode . ' double records one bounded indeterminate no-retry result'
        );
    }

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_payment_adapter_wompi_merchant_read_durable_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_create(',
        'socket_connect(', 'WompiNequiOfflineAdapter.php',
        'Merchant_Contract_Curl_Transport',
        'red_addon_runtime_register_package(',
        'red_addon_adapter_invoke_registered(', '->secret(',
        'Authorization:', 'production.wompi.co', 'shell_exec(', 'passthru(',
        'sleep(', 'usleep(', 'php://input', '$_POST',
    ] as $forbidden) {
        red_wompi_c4c3a_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from the durable provider-double runner'
        );
    }

    $db->close();
    echo 'Wompi C4C3A durable provider-double self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    $db->close();
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
