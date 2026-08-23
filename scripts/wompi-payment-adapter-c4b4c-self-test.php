<?php
/** Disposable C4B4C Wompi sealed transport-double start/result acceptance. */

define('RED_WOMPI_C4B4B_FIXTURE_ONLY', true);
require_once __DIR__ . '/wompi-payment-adapter-c4b4b-self-test.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_wompi_transport_double_helpers.php';

$assertions = 0;

function red_wompi_c4b4c_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c4b4c_chain(
    $connection,
    $fixtureProject,
    array $package,
    array $catalog,
    $actorId,
    $now,
    $authorizationDigit,
    $claimDigit,
    $recordClaim = true
) {
    [$authorization, $claim] = red_wompi_c4b4b_evidence(
        $connection,
        $actorId,
        $now,
        $authorizationDigit,
        $claimDigit
    );
    $claimPlan = red_addon_wompi_claim_plan(
        $connection,
        $package,
        $catalog,
        $actorId,
        $authorization,
        $claim,
        $now
    );
    $claimed = null;
    if ($recordClaim && !empty($claimPlan['ready'])) {
        $claimed = red_addon_wompi_claim_record(
            $connection,
            $fixtureProject,
            $actorId,
            $authorization,
            $claim,
            $claimPlan['authorizationStateSha256'],
            $claimPlan['claimStateSha256'],
            $now
        );
    }
    $transportPlan = red_addon_wompi_transport_plan(
        $connection,
        $package,
        $catalog,
        $actorId,
        $authorization,
        $claim,
        $now
    );
    return [
        'authorization' => $authorization,
        'claim' => $claim,
        'claimPlan' => $claimPlan,
        'claimed' => $claimed,
        'transportPlan' => $transportPlan,
    ];
}

try {
    $password = password_hash('WompiC4B4C-Disposable-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_wompi_c4b4c', ?, 'Admin', 'WompiC4B4C',
                   'webmaster', '', '', 'wompi-c4b4c@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    $inserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_wompi_c4b4c_assert($inserted, 'disposable Owner fixture is recorded');
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    foreach (['addons.install', 'addons.enable', 'store.orders.manage']
        as $capability
    ) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Admin_Capabilities
             (AdminRecordID, Capability, GrantedByAdminRecordID)
             VALUES (?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $statement,
            'isi',
            $actorId,
            $capability,
            $actorId
        );
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }
    red_wompi_c4b4c_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT RoleName FROM RED_Admin_Roles
                 WHERE AdminRecordID=$actorId),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$actorId
                   AND Capability IN (
                     'addons.install','addons.enable','store.orders.manage'
                   )))"
        ) === 'owner:3',
        'Owner has exact lifecycle and order authority'
    );

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $wompiPackage = $catalog['packages'][$wompiPackageId] ?? [];
    red_wompi_c4b4c_assert(
        !empty($catalog['valid'])
            && ($storePackage['manifest']['version'] ?? null) === '0.1.35'
            && ($wompiPackage['manifest']['version'] ?? null) === '0.1.4',
        'exact packages discover without execution'
    );
    red_wompi_c4b4c_assert(
        red_wompi_c3b_record_enabled_store(
            $connection,
            $storePackage,
            $actorId
        ),
        'Store Lite enabled dependency baseline is recorded'
    );
    $installPlan = red_addon_install_plan(
        $connection,
        $wompiPackage,
        $actorId,
        false,
        $catalog
    );
    $installed = red_addon_install_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $installPlan['planSha256'] ?? ''
    );
    red_wompi_c4b4c_assert(
        !empty($installPlan['valid'])
            && ($installed['status'] ?? null) === 'installed_disabled',
        'Wompi installs disabled before separate enablement'
    );
    $references = [
        'wompi.private-key' => 'config:c4b4c-wompi-private',
        'wompi.integrity-key' => 'config:c4b4c-wompi-integrity',
        'wompi.event-secret' => 'config:c4b4c-wompi-event',
    ];
    red_wompi_c4b4c_assert(
        red_wompi_c3c1_store_settings(
            $connection,
            $wompiPackageId,
            $actorId,
            'sandbox-public-reference-c4b4c',
            $references
        ),
        'client-local setting and three opaque references are stored'
    );
    $declarations = red_addon_secret_reference_declarations(
        array_values($references),
        ''
    );
    $enablePlan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $wompiPackage,
        $actorId,
        $catalog,
        $declarations
    );
    $enabled = red_addon_payment_adapter_enable_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $enablePlan['planSha256'] ?? '',
        $declarations
    );
    red_wompi_c4b4c_assert(
        !empty($enablePlan['valid'])
            && ($enabled['status'] ?? null) === 'enabled',
        'Wompi enables with value-free declarations'
    );
    require_once $fixtureProject
        . '/addons/redcms/store-lite-wompi/addon.php';
    $now = 1787443200;

    $missing = red_wompi_c4b4c_chain(
        $connection,
        $fixtureProject,
        $wompiPackage,
        $catalog,
        $actorId,
        $now,
        '5',
        '6',
        false
    );
    red_wompi_c4b4c_assert(
        ($missing['transportPlan']['errors'] ?? null)
            === ['durable_claim_refused'],
        'transport planning refuses pure evidence without durable claim rows'
    );

    $success = red_wompi_c4b4c_chain(
        $connection,
        $fixtureProject,
        $wompiPackage,
        $catalog,
        $actorId,
        $now,
        '6',
        '7'
    );
    $plan = $success['transportPlan'];
    red_wompi_c4b4c_assert(
        ($success['claimed']['status'] ?? null) === 'claimed'
            && !empty($plan['ready'])
            && $plan['authorizationRecorded']
            && $plan['claimRecorded']
            && $plan['replayProtectionActive']
            && $plan['executionStartAvailable']
            && !$plan['executionStarted'],
        'dry plan requires exact durable claim and writes no start'
    );
    red_wompi_c4b4c_assert(
        red_addon_wompi_transport_request_valid(
            red_addon_wompi_transport_request($plan) ?? []
        )
            && red_addon_wompi_claim_sha256($plan['requestSha256'])
            && red_addon_wompi_claim_sha256(
                $plan['executionStartStateSha256']
            ),
        'plan fixes a bounded hash-only sealed-double request and start state'
    );
    $double = new RED_Addon_Wompi_No_Contact_Transport_Double();
    $executed = red_addon_wompi_transport_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $success['authorization'],
        $success['claim'],
        $plan['executionStartStateSha256'],
        $double,
        $now
    );
    red_wompi_c4b4c_assert(
        $executed['status'] === 'sealed_double_completed'
            && $double->callCount() === 1
            && $executed['executionStarted']
            && $executed['startAuditRecorded']
            && $executed['transportDoubleInvoked']
            && $executed['executionPerformed']
            && $executed['outcomeRecorded']
            && $executed['outcomeAuditRecorded'],
        'start commits before one double invocation and bounded result commit'
    );
    $outcome = $executed['boundedOutcome'];
    red_wompi_c4b4c_assert(
        $outcome['simulationObserved']
            && !$outcome['responseBodyIncluded']
            && !$outcome['responseHeadersIncluded']
            && !$outcome['credentialIncluded']
            && !$outcome['personalDataIncluded']
            && !$outcome['networkAccess']
            && !$outcome['providerContact']
            && !$outcome['providerMutation']
            && !$outcome['transactionCreation']
            && !$outcome['paymentVerified']
            && !$outcome['eventAgreement']
            && !$outcome['paymentApplied']
            && !$outcome['orderMutation']
            && !$outcome['retryAuthorized'],
        'bounded double outcome proves every real effect false'
    );
    red_wompi_c4b4c_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND ActionID LIKE 'wompi-no-contact-%."
                    . $success['authorization']['authorizationNonceSha256']
                    . "'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND DetailCode IN (
                     'wompi_no_contact_authorized',
                     'wompi_no_contact_claimed',
                     'wompi_no_contact_execution_started',
                     'wompi_no_contact_double_completed'
                   )))"
        ) === '4:4',
        'authorization, claim, start, result, and four audits are exact'
    );
    $replayDouble = new RED_Addon_Wompi_No_Contact_Transport_Double();
    $replay = red_addon_wompi_transport_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $success['authorization'],
        $success['claim'],
        $plan['executionStartStateSha256'],
        $replayDouble,
        $now
    );
    red_wompi_c4b4c_assert(
        $replay['status'] === 'execution_already_started'
            && $replayDouble->callCount() === 0,
        'replay refuses before a second double invocation'
    );

    $startRollback = red_wompi_c4b4c_chain(
        $connection,
        $fixtureProject,
        $wompiPackage,
        $catalog,
        $actorId,
        $now,
        '8',
        '9'
    );
    $startDouble = new RED_Addon_Wompi_No_Contact_Transport_Double();
    $startFailure = red_addon_wompi_transport_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $startRollback['authorization'],
        $startRollback['claim'],
        $startRollback['transportPlan']['executionStartStateSha256'],
        $startDouble,
        $now,
        static fn () => false
    );
    red_wompi_c4b4c_assert(
        $startFailure['status'] === 'execution_start_audit_failed'
            && $startDouble->callCount() === 0,
        'start-audit failure rolls back before double invocation'
    );
    $startRecovery = red_addon_wompi_transport_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $startRollback['authorization'],
        $startRollback['claim'],
        $startRollback['transportPlan']['executionStartStateSha256'],
        $startDouble,
        $now
    );
    red_wompi_c4b4c_assert(
        $startRecovery['status'] === 'sealed_double_completed'
            && $startDouble->callCount() === 1,
        'rolled-back start permits one clean recovery'
    );

    $outcomeFailureChain = red_wompi_c4b4c_chain(
        $connection,
        $fixtureProject,
        $wompiPackage,
        $catalog,
        $actorId,
        $now,
        'a',
        'b'
    );
    $outcomeDouble = new RED_Addon_Wompi_No_Contact_Transport_Double();
    $outcomeFailure = red_addon_wompi_transport_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $outcomeFailureChain['authorization'],
        $outcomeFailureChain['claim'],
        $outcomeFailureChain['transportPlan']['executionStartStateSha256'],
        $outcomeDouble,
        $now,
        null,
        static fn () => false
    );
    red_wompi_c4b4c_assert(
        $outcomeFailure['status'] === 'outcome_audit_failed'
            && $outcomeDouble->callCount() === 1
            && $outcomeFailure['executionStarted']
            && !$outcomeFailure['outcomeRecorded'],
        'result-audit failure preserves spent start without result'
    );
    $noRetryDouble = new RED_Addon_Wompi_No_Contact_Transport_Double();
    $noRetry = red_addon_wompi_transport_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $outcomeFailureChain['authorization'],
        $outcomeFailureChain['claim'],
        $outcomeFailureChain['transportPlan']['executionStartStateSha256'],
        $noRetryDouble,
        $now
    );
    red_wompi_c4b4c_assert(
        $noRetry['status'] === 'execution_already_started'
            && $noRetryDouble->callCount() === 0,
        'post-start result failure is permanently no-retry'
    );

    $faultChain = red_wompi_c4b4c_chain(
        $connection,
        $fixtureProject,
        $wompiPackage,
        $catalog,
        $actorId,
        $now,
        'c',
        'd'
    );
    $faultDouble = new RED_Addon_Wompi_No_Contact_Transport_Double('fault');
    $fault = red_addon_wompi_transport_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $faultChain['authorization'],
        $faultChain['claim'],
        $faultChain['transportPlan']['executionStartStateSha256'],
        $faultDouble,
        $now
    );
    red_wompi_c4b4c_assert(
        $fault['status'] === 'sealed_double_indeterminate'
            && $faultDouble->callCount() === 1
            && $fault['outcomeRecorded']
            && !$fault['networkAccess']
            && !$fault['providerContact']
            && !$fault['retryAuthorized'],
        'double fault records bounded indeterminate no-network no-retry result'
    );

    $malformedChain = red_wompi_c4b4c_chain(
        $connection,
        $fixtureProject,
        $wompiPackage,
        $catalog,
        $actorId,
        $now,
        'e',
        'f'
    );
    $malformedDouble =
        new RED_Addon_Wompi_No_Contact_Transport_Double('malformed');
    $malformed = red_addon_wompi_transport_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $malformedChain['authorization'],
        $malformedChain['claim'],
        $malformedChain['transportPlan']['executionStartStateSha256'],
        $malformedDouble,
        $now
    );
    red_wompi_c4b4c_assert(
        $malformed['status'] === 'sealed_double_indeterminate'
            && $malformedDouble->callCount() === 1
            && $malformed['outcomeRecorded'],
        'malformed double output is contained as spent indeterminate result'
    );

    $changedChain = red_wompi_c4b4c_chain(
        $connection,
        $fixtureProject,
        $wompiPackage,
        $catalog,
        $actorId,
        $now,
        '1',
        '2'
    );
    $changedDouble = new RED_Addon_Wompi_No_Contact_Transport_Double();
    $changed = red_addon_wompi_transport_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $changedChain['authorization'],
        $changedChain['claim'],
        str_repeat('3', 64),
        $changedDouble,
        $now
    );
    red_wompi_c4b4c_assert(
        $changed['status'] === 'execution_changed'
            && $changedDouble->callCount() === 0,
        'changed expected start hash refuses before invocation'
    );

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_payment_adapter_wompi_transport_double_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_create(',
        'sandbox.wompi.co', 'Authorization:', 'php://input', '$_POST',
        '$_SERVER', 'getenv(', 'putenv(', 'shell_exec(', 'sleep(', 'usleep(',
        'red_addon_runtime_secret', 'red_addon_runtime_register_package',
        'red_addon_adapter_invoke_registered',
    ] as $forbidden) {
        red_wompi_c4b4c_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from transport-double runner source'
        );
    }

    echo 'Wompi C4B4C transport-double self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
