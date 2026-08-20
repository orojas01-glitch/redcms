<?php
/**
 * Disposable-database P3E-9C1 mutation-authorization acceptance fixture.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_PROVIDER_CONTACT_AUTHORIZATION_FIXTURE_ONLY', true);
require_once __DIR__ . '/addon-provider-contact-authorization-self-test.php';
require_once $projectRoot
    . '/includes/addon_sandbox_checkout_mutation_authorization_helpers.php';

$assertions = 0;
$actorId = 2147000989;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_Checkout_Mutation_Auth_Fixture';
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-checkout-mutation-auth-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$packageIds = [$adapterPackageId, $storePackageId];

function red_addon_checkout_mutation_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_checkout_mutation_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_addon_checkout_mutation_test_cleanup(
    $connection,
    array $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
) {
    foreach ($packageIds as $packageId) {
        $escaped = mysqli_real_escape_string($connection, $packageId);
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Admin_Action_Executions
             WHERE PackageID='$escaped'"
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Activity_Log
             WHERE PackageID='$escaped'"
        );
    }
    red_addon_payment_adapter_db_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
}

function red_addon_checkout_mutation_test_input()
{
    return [
        'contactTarget' => 'synthetic-checkout-package',
        'checkout' => [
            'orderId' => 'ord_0123456789abcdef0123456789abcdef',
            'orderSnapshotSha256' => str_repeat('a', 64),
            'paymentMethod' => 'stripe_checkout',
            'amountMinor' => 5897,
            'currency' => 'USD',
            'idempotencySha256' => str_repeat('b', 64),
            'lineItems' => [[
                'name' => 'Dog scarf - Small / Red',
                'quantity' => 2,
                'unitAmountMinor' => 1999,
                'lineTotalMinor' => 3998,
            ], [
                'name' => 'Delivery fee',
                'quantity' => 1,
                'unitAmountMinor' => 1899,
                'lineTotalMinor' => 1899,
            ]],
        ],
        'policy' => [
            'apiVersion' => '2024-09-30.acacia',
            'successUrl' =>
                'https://shop.example.test/checkout/stripe-complete',
            'cancelUrl' => 'https://shop.example.test/checkout',
            'createdAtEpoch' => 1787025600,
            'expiresAtEpoch' => 1787027400,
        ],
        'profile' => [
            'packageId' => 'redcms.store-lite-stripe-checkout',
            'contractVersion' => 'p3e9a-v1',
            'operation' => 'checkout.create-sandbox',
            'contactTarget' => 'stripe-sandbox',
            'credentialMode' => 'restricted_test_write',
            'providerContact' => true,
            'providerMutation' => true,
            'checkoutCreation' => true,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'orderMutation' => false,
            'clientDeployment' => false,
            'oneAttempt' => true,
            'automaticRetry' => false,
        ],
        'contractSha256' => str_repeat('c', 64),
    ];
}

red_addon_checkout_mutation_test_cleanup(
    $connection,
    $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
);

try {
    $password = password_hash(
        'CheckoutMutationAuthority-2026!',
        PASSWORD_DEFAULT
    );
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_checkout_mutation_authority', ?, 'Admin',
                   'CheckoutAuth', 'webmaster', '', '',
                   'checkout-authority@example.test', 'N', 'to', 'N', 'to')"
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
        json_encode(
            $storeManifest,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
        ) . "\n"
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
        json_encode(
            $adapterManifest,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
        ) . "\n"
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
        'same disposable client records enabled Store Lite and adapter 0.1.5'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$tableName` (
            RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );

    $input = red_addon_checkout_mutation_test_input();
    $syntheticPlan = red_addon_checkout_synthetic_plan(
        $adapterPackage,
        $input
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_synthetic_plan_valid($syntheticPlan),
        'exact P3E-9B plan is accepted without package invocation'
    );
    $ownerSubject = red_addon_provider_contact_owner_subject_sha256(
        $connection,
        $actorId
    );
    $prepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('d', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        !empty($prepared['prepared'])
            && red_addon_provider_contact_sha256(
                $prepared['authorizationSha256'] ?? null
            )
            && ($prepared['authorization']['providerMutationAuthorized']
                ?? null) === true
            && ($prepared['authorization']['checkoutCreationAuthorized']
                ?? null) === true
            && ($prepared['authorization']['paymentAuthorized'] ?? null)
                === false
            && ($prepared['authorization']['executionPerformed'] ?? null)
                === false,
        'pure envelope binds mutation authority while executing nothing'
    );

    $plan = red_addon_checkout_mutation_authorization_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $prepared,
        '2026-08-20T12:07:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        !empty($plan['valid'])
            && !empty($plan['ready'])
            && ($plan['status'] ?? null) === 'ready'
            && !empty($plan['ownerAuthorityRevalidated'])
            && !empty($plan['orderAuthorityRevalidated'])
            && ($plan['packageVersion'] ?? null) === '0.1.5'
            && ($plan['storePackageVersion'] ?? null) === '0.1.35'
            && red_addon_provider_contact_sha256(
                $plan['authorizationStateSha256'] ?? null
            ),
        'fresh Owner and order authority produce one non-writing plan'
    );
    red_addon_checkout_mutation_test_assert(
        empty($plan['mutationAuthorityRecorded'])
            && empty($plan['claimRecorded'])
            && empty($plan['executionStarted'])
            && empty($plan['executionPerformed'])
            && empty($plan['secretResolution'])
            && empty($plan['networkAccess'])
            && empty($plan['providerContact'])
            && empty($plan['providerMutation'])
            && empty($plan['checkoutCreation'])
            && empty($plan['payment'])
            && empty($plan['retryAuthorized']),
        'dry plan grants no effect and records no authority'
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'))"
        ) === '0:0'
            && !file_exists($executionMarker),
        'dry plan writes no ledger/audit row and executes no package'
    );

    $authorized = red_addon_checkout_mutation_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $prepared,
        $plan['authorizationSha256'],
        '2026-08-20T12:07:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($authorized['status'] ?? null) === 'authorized'
            && !empty($authorized['nonceConsumed'])
            && !empty($authorized['auditRecorded'])
            && !empty($authorized['mutationAuthorityRecorded'])
            && empty($authorized['claimRecorded'])
            && empty($authorized['executionStarted'])
            && empty($authorized['executionPerformed'])
            && empty($authorized['secretResolution'])
            && empty($authorized['networkAccess'])
            && empty($authorized['providerContact'])
            && empty($authorized['checkoutCreation']),
        'apply records only mutation authority plus one audit fact'
    );
    $actionId = mysqli_real_escape_string(
        $connection,
        red_addon_checkout_mutation_action_id(str_repeat('d', 64))
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'
                   AND ActionID='$actionId'
                   AND PlanSHA256='{$plan['syntheticPlanSha256']}'
                   AND ContractSHA256='{$plan['authorizationSha256']}'
                   AND PreviousStateSHA256='{$plan['ownerSubjectSha256']}'
                   AND StateSHA256='{$plan['authorizationStateSha256']}'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'
                   AND DetailCode='sandbox_checkout_mutation_authorized'))"
        ) === '1:1',
        'immutable authorization and value-free audit bind exact hashes'
    );
    red_addon_checkout_mutation_test_assert(
        !file_exists($executionMarker)
            && getenv('RED_ADDON_SECRET_REFERENCES') === false
            && getenv('RED_ADDON_SECRET_VALUES_JSON') === false
            && red_addon_checkout_mutation_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM `$tableName`),
                    (SELECT COUNT(*) FROM RED_Addon_Public_Mutation_Executions))"
            ) === '0:0',
        'authorization invokes no package, secret, public mutation, or data row'
    );

    $replay = red_addon_checkout_mutation_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $prepared,
        $plan['authorizationSha256'],
        '2026-08-20T12:08:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($replay['status'] ?? null) === 'nonce_already_consumed'
            && red_addon_checkout_mutation_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'"
            ) === '1',
        'nonce replay is refused without a second authorization row'
    );
    $changedPrepared = $prepared;
    $changedPrepared['authorization']['inputSha256'] = str_repeat('e', 64);
    $changedPrepared['authorizationSha256'] = hash(
        'sha256',
        json_encode(
            $changedPrepared['authorization'],
            JSON_UNESCAPED_SLASHES
        )
    );
    $changed = red_addon_checkout_mutation_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $changedPrepared,
        $changedPrepared['authorizationSha256'],
        '2026-08-20T12:08:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        empty($changed['mutationAuthorityRecorded'])
            && in_array($changed['status'] ?? null, [
                'invalid', 'package_invalid',
            ], true),
        'changed envelope cannot reuse the consumed nonce'
    );

    $expired = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('e', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $expiredPlan = red_addon_checkout_mutation_authorization_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $expired,
        '2026-08-20T12:15:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        empty($expiredPlan['ready'])
            && ($expiredPlan['errors'] ?? [])
                === ['authorization_evidence_refused'],
        'expired authority is refused at its exact boundary'
    );
    $tooLong = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('f', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:01Z'
    );
    red_addon_checkout_mutation_test_assert(
        empty($tooLong['prepared']),
        'authority longer than fifteen minutes is refused before database work'
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$actorId
           AND Capability='store.orders.manage'"
    );
    $missingOrder = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('1', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $missingOrderPlan = red_addon_checkout_mutation_authorization_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $missingOrder,
        '2026-08-20T12:07:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        empty($missingOrderPlan['ready'])
            && ($missingOrderPlan['errors'] ?? [])
                === ['order_authority_refused'],
        'Owner without store.orders.manage cannot authorize mutation'
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($actorId, 'store.orders.manage', $actorId)"
    );

    $auditPrepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('2', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $auditPlan = red_addon_checkout_mutation_authorization_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $auditPrepared,
        '2026-08-20T12:07:00Z'
    );
    $auditFailure = red_addon_checkout_mutation_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $auditPrepared,
        $auditPlan['authorizationSha256'],
        '2026-08-20T12:07:00Z',
        static fn () => false
    );
    red_addon_checkout_mutation_test_assert(
        ($auditFailure['status'] ?? null) === 'audit_failed'
            && empty($auditFailure['mutationAuthorityRecorded'])
            && red_addon_checkout_mutation_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'"
            ) === '1',
        'audit failure rolls back nonce reservation and authority row'
    );
    $auditRecovery = red_addon_checkout_mutation_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $auditPrepared,
        $auditPlan['authorizationSha256'],
        '2026-08-20T12:07:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($auditRecovery['status'] ?? null) === 'authorized'
            && !empty($auditRecovery['mutationAuthorityRecorded']),
        'rolled-back nonce remains available for one clean authorization'
    );

    $wrongProfileInput = $input;
    $wrongProfileInput['profile']['credentialMode'] =
        'restricted_test_read';
    red_addon_checkout_mutation_test_assert(
        empty(
            red_addon_checkout_synthetic_plan(
                $adapterPackage,
                $wrongProfileInput
            )['ready']
        ),
        'read-only credential profile cannot enter mutation authorization'
    );

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_sandbox_checkout_mutation_authorization_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'api.stripe.com', 'Authorization:', 'php://input', '$_POST',
        '$_SERVER', 'getenv(', 'putenv(', 'shell_exec(', 'sleep(', 'usleep(',
    ] as $forbidden) {
        red_addon_checkout_mutation_test_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from mutation-authorization source'
        );
    }
    red_addon_checkout_mutation_test_assert(
        !str_contains($source, 'red_addon_runtime_register_package')
            && !str_contains($source, 'red_addon_adapter_invoke_registered')
            && !str_contains($source, 'RED_Addon_Runtime_Secret_Access'),
        'authorization source has no package execution or secret access path'
    );

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
        'cleanup removes every authority, package, actor, table, and file fixture'
    );

    echo 'Sandbox Checkout P3E-9C1 mutation authorization self-test passed: '
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
