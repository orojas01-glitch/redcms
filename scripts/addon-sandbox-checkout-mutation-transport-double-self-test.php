<?php
/** Disposable P3E-9C3A transport-double start/result fixture. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_CHECKOUT_MUTATION_CLAIM_FIXTURE_ONLY', true);
require_once __DIR__
    . '/addon-sandbox-checkout-mutation-claim-self-test.php';
require_once $projectRoot
    . '/includes/addon_sandbox_checkout_mutation_transport_double_helpers.php';

$assertions = 0;
$actorId = 2147000991;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_Checkout_Transport_Double_Fixture';
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-checkout-transport-double-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$packageIds = [$adapterPackageId, $storePackageId];

function red_addon_checkout_transport_test_prepare_chain(
    $connection,
    $fixtureProject,
    array $adapterPackage,
    array $catalog,
    $actorId,
    array $input,
    $ownerSubject,
    $nonceCharacter
) {
    $syntheticPlan = red_addon_checkout_synthetic_plan($adapterPackage, $input);
    $prepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat($nonceCharacter, 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $authorizationPlan = red_addon_checkout_mutation_authorization_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $prepared,
        '2026-08-20T12:05:00Z'
    );
    $authorized = red_addon_checkout_mutation_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $prepared,
        $authorizationPlan['authorizationSha256'],
        '2026-08-20T12:05:00Z'
    );
    $claimPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $prepared,
        '2026-08-20T12:06:00Z'
    );
    $claimed = red_addon_checkout_mutation_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $prepared,
        $claimPlan['authorizationSha256'],
        $claimPlan['authorizationStateSha256'],
        $claimPlan['claimStateSha256'],
        '2026-08-20T12:06:00Z'
    );
    return [
        'prepared' => $prepared,
        'authorized' => $authorized,
        'claimed' => $claimed,
    ];
}

function red_addon_checkout_transport_test_plan(
    $connection,
    array $adapterPackage,
    array $catalog,
    $actorId,
    array $input,
    array $prepared
) {
    return red_addon_checkout_mutation_transport_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $prepared,
        '2026-08-20T12:07:00Z'
    );
}

function red_addon_checkout_transport_test_execute(
    $connection,
    $fixtureProject,
    $actorId,
    array $input,
    array $prepared,
    array $plan,
    RED_Addon_Checkout_Mutation_Transport_Double $double,
    $startAudit = null,
    $outcomeAudit = null
) {
    return red_addon_checkout_mutation_execute_transport_double(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $prepared,
        $plan['authorizationSha256'],
        $plan['authorizationStateSha256'],
        $plan['claimStateSha256'],
        $plan['executionStartStateSha256'],
        $double,
        '2026-08-20T12:07:00Z',
        $startAudit,
        $outcomeAudit
    );
}

if (defined('RED_ADDON_CHECKOUT_TRANSPORT_DOUBLE_FIXTURE_ONLY')
    && RED_ADDON_CHECKOUT_TRANSPORT_DOUBLE_FIXTURE_ONLY
) {
    return;
}

red_addon_checkout_mutation_test_cleanup(
    $connection,
    $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
);

try {
    $password = password_hash('CheckoutTransportDouble-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_checkout_transport_double', ?, 'Admin',
                   'CheckoutDouble', 'webmaster', '', '',
                   'checkout-double@example.test', 'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    foreach (['addons.enable', 'store.orders.manage'] as $capability) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Admin_Capabilities
             (AdminRecordID, Capability, GrantedByAdminRecordID)
             VALUES (?, ?, ?)'
        );
        mysqli_stmt_bind_param($statement, 'isi', $actorId, $capability, $actorId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $storePackageId,
        'content-package',
        '0.1.35',
        $executionMarker
    );
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $adapterPackageId,
        'adapter',
        '0.1.5',
        $executionMarker,
        $tableName
    );
    $storeManifestPath = $fixtureProject
        . '/addons/redcms/store-lite/addon.json';
    $storeManifest = json_decode(
        (string) file_get_contents($storeManifestPath),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $storeManifest['permissions'] = ['store.orders.manage'];
    file_put_contents(
        $storeManifestPath,
        json_encode($storeManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            . "\n"
    );
    $adapterManifestPath = $fixtureProject
        . '/addons/redcms/store-lite-stripe-checkout/addon.json';
    $adapterManifest = json_decode(
        (string) file_get_contents($adapterManifestPath),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $adapterManifest['dependencies']['required'][0]['version'] =
        '>=0.1.35 <1.0';
    $adapterManifest['routes'][0]['path'] =
        '/addons/redcms/store-lite-stripe-checkout/provider-events';
    file_put_contents(
        $adapterManifestPath,
        json_encode($adapterManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            . "\n"
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? [];
    red_addon_checkout_mutation_test_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($adapterPackage['valid'])
            && !file_exists($executionMarker),
        'exact packages discover without executing package PHP'
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_payment_adapter_db_test_record_installation(
            $connection,
            $storePackage,
            $actorId,
            'enabled'
        ) && red_addon_payment_adapter_db_test_record_installation(
            $connection,
            $adapterPackage,
            $actorId,
            'enabled'
        ),
        'exact packages are enabled in one disposable client database'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$tableName` (
            RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );

    $input = red_addon_checkout_mutation_test_input();
    $ownerSubject = red_addon_provider_contact_owner_subject_sha256(
        $connection,
        $actorId
    );
    $chain = red_addon_checkout_transport_test_prepare_chain(
        $connection,
        $fixtureProject,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $ownerSubject,
        'a'
    );
    red_addon_checkout_mutation_test_assert(
        ($chain['authorized']['status'] ?? '') === 'authorized'
            && ($chain['claimed']['status'] ?? '') === 'claimed',
        'exact P3E-9C1 authorization and P3E-9C2 claim exist first'
    );
    $plan = red_addon_checkout_transport_test_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $chain['prepared']
    );
    red_addon_checkout_mutation_test_assert(
        !empty($plan['ready'])
            && !empty($plan['authorizationRecorded'])
            && !empty($plan['attemptClaimed'])
            && !empty($plan['executionStartAvailable'])
            && empty($plan['executionStarted'])
            && red_addon_provider_contact_sha256(
                $plan['executionStartStateSha256'] ?? ''
            ),
        'dry plan accepts exact authorization and claim without writing start'
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'))"
        ) === '2:2',
        'transport plan writes no start, result, or audit'
    );

    $double = new RED_Addon_Checkout_Mutation_Transport_Double('completed');
    $executed = red_addon_checkout_transport_test_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $chain['prepared'],
        $plan,
        $double
    );
    red_addon_checkout_mutation_test_assert(
        ($executed['status'] ?? '') === 'transport_double_completed'
            && $double->callCount() === 1
            && !empty($executed['executionStarted'])
            && !empty($executed['startAuditRecorded'])
            && !empty($executed['transportDoubleInvoked'])
            && !empty($executed['executionPerformed'])
            && !empty($executed['outcomeRecorded'])
            && !empty($executed['outcomeAuditRecorded']),
        'start commits before one double invocation and bounded result commit'
    );
    $outcome = $executed['boundedOutcome'] ?? [];
    red_addon_checkout_mutation_test_assert(
        ($outcome['simulationObserved'] ?? null) === true
            && empty($outcome['networkAccess'])
            && empty($outcome['providerContact'])
            && empty($outcome['providerMutation'])
            && empty($outcome['checkoutCreation'])
            && empty($outcome['payment'])
            && empty($outcome['webhook'])
            && empty($outcome['storeLiteMutation'])
            && empty($outcome['retryAuthorized'])
            && empty($outcome['clientDeployment']),
        'bounded double result proves every real provider/business effect false'
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'))"
        ) === '4:4',
        'authorization, claim, start, result and four audits are exact'
    );
    $startAction = mysqli_real_escape_string(
        $connection,
        red_addon_checkout_mutation_start_action_id(str_repeat('a', 64))
    );
    $resultAction = mysqli_real_escape_string(
        $connection,
        red_addon_checkout_mutation_outcome_action_id(str_repeat('a', 64))
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId' AND ActionID='$startAction'
                   AND PreviousStateSHA256='{$plan['claimStateSha256']}'
                   AND StateSHA256='{$plan['executionStartStateSha256']}'),
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId' AND ActionID='$resultAction'
                   AND PreviousStateSHA256=
                       '{$plan['executionStartStateSha256']}'
                   AND StateSHA256='{$executed['outcomeStateSha256']}'))"
        ) === '1:1',
        'immutable start and result rows bind the exact preceding states'
    );
    red_addon_checkout_mutation_test_assert(
        !file_exists($executionMarker)
            && !file_exists($executionMarker . '-adapter-handler')
            && !file_exists($executionMarker . '-route-handler')
            && red_addon_checkout_mutation_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM `$tableName`"
            ) === '0',
        'runner invokes no package registrar, handler, migration, or data row'
    );

    $replayDouble = new RED_Addon_Checkout_Mutation_Transport_Double();
    $replay = red_addon_checkout_transport_test_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $chain['prepared'],
        $plan,
        $replayDouble
    );
    red_addon_checkout_mutation_test_assert(
        ($replay['status'] ?? '') === 'execution_already_started'
            && $replayDouble->callCount() === 0,
        'replay is refused before a second double invocation'
    );

    $missingSynthetic = red_addon_checkout_synthetic_plan($adapterPackage, $input);
    $missingPrepared = red_addon_checkout_mutation_prepare(
        $missingSynthetic,
        $ownerSubject,
        str_repeat('b', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $missingPlan = red_addon_checkout_transport_test_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $missingPrepared
    );
    red_addon_checkout_mutation_test_assert(
        ($missingPlan['status'] ?? '') === 'authorization_record_refused',
        'execution refuses a nonce without authorization and claim'
    );

    $rollbackChain = red_addon_checkout_transport_test_prepare_chain(
        $connection,
        $fixtureProject,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $ownerSubject,
        'c'
    );
    $rollbackPlan = red_addon_checkout_transport_test_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $rollbackChain['prepared']
    );
    $rollbackDouble = new RED_Addon_Checkout_Mutation_Transport_Double();
    $startFailure = red_addon_checkout_transport_test_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $rollbackChain['prepared'],
        $rollbackPlan,
        $rollbackDouble,
        static fn () => false
    );
    red_addon_checkout_mutation_test_assert(
        ($startFailure['status'] ?? '') === 'execution_start_audit_failed'
            && $rollbackDouble->callCount() === 0,
        'start-audit failure rolls back before double invocation'
    );
    $rollbackRecovered = red_addon_checkout_transport_test_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $rollbackChain['prepared'],
        $rollbackPlan,
        $rollbackDouble
    );
    red_addon_checkout_mutation_test_assert(
        ($rollbackRecovered['status'] ?? '') === 'transport_double_completed'
            && $rollbackDouble->callCount() === 1,
        'rolled-back start remains available for one clean execution'
    );

    $outcomeChain = red_addon_checkout_transport_test_prepare_chain(
        $connection,
        $fixtureProject,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $ownerSubject,
        'd'
    );
    $outcomePlan = red_addon_checkout_transport_test_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $outcomeChain['prepared']
    );
    $outcomeDouble = new RED_Addon_Checkout_Mutation_Transport_Double();
    $outcomeFailure = red_addon_checkout_transport_test_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $outcomeChain['prepared'],
        $outcomePlan,
        $outcomeDouble,
        null,
        static fn () => false
    );
    red_addon_checkout_mutation_test_assert(
        ($outcomeFailure['status'] ?? '') === 'outcome_audit_failed'
            && $outcomeDouble->callCount() === 1
            && !empty($outcomeFailure['executionStarted'])
            && empty($outcomeFailure['outcomeRecorded']),
        'outcome-audit failure preserves committed spent start without result'
    );
    $noRetryDouble = new RED_Addon_Checkout_Mutation_Transport_Double();
    $noRetry = red_addon_checkout_transport_test_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $outcomeChain['prepared'],
        $outcomePlan,
        $noRetryDouble
    );
    red_addon_checkout_mutation_test_assert(
        ($noRetry['status'] ?? '') === 'execution_already_started'
            && $noRetryDouble->callCount() === 0,
        'post-start result failure remains permanently no-retry'
    );

    $faultChain = red_addon_checkout_transport_test_prepare_chain(
        $connection,
        $fixtureProject,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $ownerSubject,
        'e'
    );
    $faultPlan = red_addon_checkout_transport_test_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $faultChain['prepared']
    );
    $faultDouble = new RED_Addon_Checkout_Mutation_Transport_Double('fault');
    $fault = red_addon_checkout_transport_test_execute(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $faultChain['prepared'],
        $faultPlan,
        $faultDouble
    );
    red_addon_checkout_mutation_test_assert(
        ($fault['status'] ?? '') === 'transport_double_indeterminate'
            && $faultDouble->callCount() === 1
            && !empty($fault['outcomeRecorded'])
            && empty($fault['networkAccess'])
            && empty($fault['providerContact']),
        'double fault records bounded indeterminate no-network no-retry result'
    );

    $changedChain = red_addon_checkout_transport_test_prepare_chain(
        $connection,
        $fixtureProject,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $ownerSubject,
        'f'
    );
    $changedPlan = red_addon_checkout_transport_test_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $changedChain['prepared']
    );
    $changedDouble = new RED_Addon_Checkout_Mutation_Transport_Double();
    $changedResult = red_addon_checkout_mutation_execute_transport_double(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $changedChain['prepared'],
        $changedPlan['authorizationSha256'],
        $changedPlan['authorizationStateSha256'],
        $changedPlan['claimStateSha256'],
        str_repeat('0', 64),
        $changedDouble,
        '2026-08-20T12:07:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($changedResult['status'] ?? '') === 'execution_changed'
            && $changedDouble->callCount() === 0,
        'changed expected start hash is refused before invocation'
    );

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_sandbox_checkout_mutation_transport_double_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'api.stripe.com', 'Authorization:', 'php://input', '$_POST',
        '$_SERVER', 'getenv(', 'putenv(', 'shell_exec(', 'sleep(', 'usleep(',
        'red_addon_secret_resolve', 'red_addon_runtime_register_package',
        'red_addon_adapter_invoke_registered',
    ] as $forbidden) {
        red_addon_checkout_mutation_test_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from transport-double runner source'
        );
    }

    red_addon_checkout_mutation_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID IN ('$adapterPackageId','$storePackageId')),
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tableName'))"
        ) === '0:0:0:0:0'
            && !file_exists($temporaryRoot),
        'cleanup removes all start/result, package, actor, table, and file fixtures'
    );

    echo 'Sandbox Checkout P3E-9C3A transport-double self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_addon_checkout_mutation_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
