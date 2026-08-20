<?php
/** Disposable-database P3E-9C2 mutation-attempt claim acceptance fixture. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_CHECKOUT_MUTATION_AUTHORIZATION_FIXTURE_ONLY', true);
require_once __DIR__
    . '/addon-sandbox-checkout-mutation-authorization-self-test.php';
require_once $projectRoot
    . '/includes/addon_sandbox_checkout_mutation_claim_helpers.php';

$assertions = 0;
$actorId = 2147000990;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_Checkout_Mutation_Claim_Fixture';
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-checkout-mutation-claim-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$packageIds = [$adapterPackageId, $storePackageId];

function red_addon_checkout_mutation_claim_test_authorize(
    $connection,
    $fixtureProject,
    $actorId,
    array $input,
    array $prepared,
    $evaluatedAtUtc
) {
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][
        'redcms.store-lite-stripe-checkout'
    ] ?? [];
    $plan = red_addon_checkout_mutation_authorization_plan(
        $connection,
        $package,
        $catalog,
        $actorId,
        $input,
        $prepared,
        $evaluatedAtUtc
    );
    if (empty($plan['ready'])) {
        return $plan;
    }
    return red_addon_checkout_mutation_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $prepared,
        $plan['authorizationSha256'],
        $evaluatedAtUtc
    );
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
        'CheckoutMutationClaim-2026!',
        PASSWORD_DEFAULT
    );
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_checkout_mutation_claim', ?, 'Admin',
                   'CheckoutClaim', 'webmaster', '', '',
                   'checkout-claim@example.test', 'N', 'to', 'N', 'to')"
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
        'same disposable client enables exact Store Lite and adapter packages'
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
    $ownerSubject = red_addon_provider_contact_owner_subject_sha256(
        $connection,
        $actorId
    );
    $prepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('a', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $authorized = red_addon_checkout_mutation_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $prepared,
        '2026-08-20T12:05:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($authorized['status'] ?? null) === 'authorized'
            && !empty($authorized['mutationAuthorityRecorded'])
            && empty($authorized['claimRecorded'])
            && empty($authorized['executionPerformed']),
        'exact P3E-9C1 authorization exists before claim planning'
    );

    $plan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $prepared,
        '2026-08-20T12:06:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        !empty($plan['valid'])
            && !empty($plan['ready'])
            && ($plan['status'] ?? null) === 'ready'
            && !empty($plan['ownerAuthorityRevalidated'])
            && !empty($plan['orderAuthorityRevalidated'])
            && !empty($plan['authorizationRecorded'])
            && !empty($plan['claimAvailable'])
            && empty($plan['attemptClaimed'])
            && empty($plan['executionStarted'])
            && empty($plan['executionPerformed']),
        'dry plan accepts one exact unclaimed mutation authorization'
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_provider_contact_sha256($plan['claimStateSha256'] ?? '')
            && ($plan['authorizationActionId'] ?? '')
                === red_addon_checkout_mutation_action_id(
                    str_repeat('a', 64)
                )
            && ($plan['claimActionId'] ?? '')
                === red_addon_checkout_mutation_claim_action_id(
                    str_repeat('a', 64)
                ),
        'claim binds the nonce and exact P3E-9C1 authorization state'
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'))"
        ) === '1:1',
        'claim planning writes no claim or audit row'
    );

    $claimed = red_addon_checkout_mutation_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $prepared,
        $plan['authorizationSha256'],
        $plan['authorizationStateSha256'],
        $plan['claimStateSha256'],
        '2026-08-20T12:06:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($claimed['status'] ?? null) === 'claimed'
            && !empty($claimed['attemptClaimed'])
            && !empty($claimed['auditRecorded'])
            && empty($claimed['executionStarted'])
            && empty($claimed['executionPerformed'])
            && empty($claimed['secretResolution'])
            && empty($claimed['networkAccess'])
            && empty($claimed['providerContact'])
            && empty($claimed['providerMutation'])
            && empty($claimed['checkoutCreation']),
        'apply commits only one attempt claim and one audit fact'
    );
    $claimAction = mysqli_real_escape_string(
        $connection,
        red_addon_checkout_mutation_claim_action_id(str_repeat('a', 64))
    );
    red_addon_checkout_mutation_test_assert(
        red_addon_checkout_mutation_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'
                   AND ActionID='$claimAction'
                   AND PlanSHA256='{$plan['syntheticPlanSha256']}'
                   AND ContractSHA256='{$plan['authorizationSha256']}'
                   AND PreviousStateSHA256=
                       '{$plan['authorizationStateSha256']}'
                   AND StateSHA256='{$plan['claimStateSha256']}'
                   AND ActorAdminRecordID=$actorId),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'
                   AND DetailCode=
                       'sandbox_checkout_mutation_attempt_claimed'))"
        ) === '1:1',
        'immutable claim and value-free audit bind exact authorization hashes'
    );
    red_addon_checkout_mutation_test_assert(
        !file_exists($executionMarker)
            && !file_exists($executionMarker . '-adapter-handler')
            && !file_exists($executionMarker . '-route-handler')
            && red_addon_checkout_mutation_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM `$tableName`),
                    (SELECT COUNT(*)
                     FROM RED_Addon_Public_Mutation_Executions))"
            ) === '0:0',
        'claim invokes no package, provider, public mutation, or data write'
    );

    $replay = red_addon_checkout_mutation_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $prepared,
        $plan['authorizationSha256'],
        $plan['authorizationStateSha256'],
        $plan['claimStateSha256'],
        '2026-08-20T12:07:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($replay['status'] ?? null) === 'attempt_already_claimed'
            && empty($replay['attemptClaimed']),
        'authorized mutation attempt cannot be claimed twice'
    );

    $missingPrepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('b', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $missingPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $missingPrepared,
        '2026-08-20T12:06:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($missingPlan['status'] ?? null) === 'authorization_record_refused',
        'claim refuses an envelope without committed P3E-9C1 authorization'
    );

    $changedPrepared = $prepared;
    $changedPrepared['authorization']['expiresAtUtc'] =
        '2026-08-20T12:14:00Z';
    $changedPrepared['authorizationSha256'] = hash(
        'sha256',
        red_addon_provider_contact_encode($changedPrepared['authorization'])
    );
    $changedPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $changedPrepared,
        '2026-08-20T12:07:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($changedPlan['status'] ?? null) === 'authorization_record_refused',
        'changed envelope cannot borrow the persisted authorization nonce'
    );

    $revokedPrepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('c', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $revokedAuthorization = red_addon_checkout_mutation_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $revokedPrepared,
        '2026-08-20T12:05:00Z'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$actorId AND Capability='addons.enable'"
    );
    $revokedPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $revokedPrepared,
        '2026-08-20T12:06:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($revokedAuthorization['status'] ?? null) === 'authorized'
            && ($revokedPlan['status'] ?? null) === 'authorization_refused'
            && ($revokedPlan['errors'] ?? []) === ['owner_authority_refused'],
        'fresh addons.enable authority is required after authorization'
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($actorId, 'addons.enable', $actorId)"
    );

    $orderPrepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('d', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $orderAuthorization = red_addon_checkout_mutation_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $orderPrepared,
        '2026-08-20T12:05:00Z'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$actorId
           AND Capability='store.orders.manage'"
    );
    $orderPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $orderPrepared,
        '2026-08-20T12:06:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($orderAuthorization['status'] ?? null) === 'authorized'
            && ($orderPlan['status'] ?? null) === 'authorization_refused'
            && ($orderPlan['errors'] ?? []) === ['order_authority_refused'],
        'fresh store.orders.manage authority is required after authorization'
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($actorId, 'store.orders.manage', $actorId)"
    );

    $disabledPrepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('e', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $disabledAuthorization = red_addon_checkout_mutation_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $disabledPrepared,
        '2026-08-20T12:05:00Z'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='disabled'
         WHERE PackageID='$storePackageId'"
    );
    $disabledPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $disabledPrepared,
        '2026-08-20T12:06:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($disabledAuthorization['status'] ?? null) === 'authorized'
            && ($disabledPlan['status'] ?? null) === 'authorization_refused'
            && ($disabledPlan['errors'] ?? []) === ['package_state_refused'],
        'disabled same-database Store Lite invalidates the future claim'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='$storePackageId'"
    );

    $expiredPrepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('f', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $expiredAuthorization = red_addon_checkout_mutation_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $expiredPrepared,
        '2026-08-20T12:05:00Z'
    );
    $expiredPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $expiredPrepared,
        '2026-08-20T12:15:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($expiredAuthorization['status'] ?? null) === 'authorized'
            && ($expiredPlan['status'] ?? null) === 'authorization_refused',
        'authorization expiry is rechecked before claim'
    );

    $rollbackPrepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('1', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $rollbackAuthorization = red_addon_checkout_mutation_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $rollbackPrepared,
        '2026-08-20T12:05:00Z'
    );
    $rollbackPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $rollbackPrepared,
        '2026-08-20T12:06:00Z'
    );
    $auditFailure = red_addon_checkout_mutation_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $rollbackPrepared,
        $rollbackPlan['authorizationSha256'],
        $rollbackPlan['authorizationStateSha256'],
        $rollbackPlan['claimStateSha256'],
        '2026-08-20T12:06:00Z',
        static fn () => false
    );
    $rollbackAction = mysqli_real_escape_string(
        $connection,
        red_addon_checkout_mutation_claim_action_id(str_repeat('1', 64))
    );
    red_addon_checkout_mutation_test_assert(
        ($rollbackAuthorization['status'] ?? null) === 'authorized'
            && ($auditFailure['status'] ?? null) === 'audit_failed'
            && red_addon_checkout_mutation_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'
                   AND ActionID='$rollbackAction'"
            ) === '0',
        'audit failure rolls back the claim reservation'
    );
    $afterRollback = red_addon_checkout_mutation_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $rollbackPrepared,
        $rollbackPlan['authorizationSha256'],
        $rollbackPlan['authorizationStateSha256'],
        $rollbackPlan['claimStateSha256'],
        '2026-08-20T12:06:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($afterRollback['status'] ?? null) === 'claimed',
        'rolled-back claim remains available for one clean claim'
    );

    $hashPrepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('2', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    red_addon_checkout_mutation_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $hashPrepared,
        '2026-08-20T12:05:00Z'
    );
    $hashPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $hashPrepared,
        '2026-08-20T12:06:00Z'
    );
    $hashChanged = red_addon_checkout_mutation_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $hashPrepared,
        str_repeat('0', 64),
        $hashPlan['authorizationStateSha256'],
        $hashPlan['claimStateSha256'],
        '2026-08-20T12:06:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($hashChanged['status'] ?? null) === 'claim_changed'
            && empty($hashChanged['attemptClaimed']),
        'changed expected authorization hash cannot claim the attempt'
    );

    $tamperedPrepared = red_addon_checkout_mutation_prepare(
        $syntheticPlan,
        $ownerSubject,
        str_repeat('3', 64),
        '2026-08-20T12:00:00Z',
        '2026-08-20T12:15:00Z'
    );
    $tamperedAuthorization = red_addon_checkout_mutation_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $input,
        $tamperedPrepared,
        '2026-08-20T12:05:00Z'
    );
    $tamperedAction = mysqli_real_escape_string(
        $connection,
        red_addon_checkout_mutation_action_id(str_repeat('3', 64))
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Admin_Action_Executions
         SET StateSHA256='" . str_repeat('0', 64) . "'
         WHERE PackageID='$adapterPackageId'
           AND ActionID='$tamperedAction'"
    );
    $tamperedPlan = red_addon_checkout_mutation_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $input,
        $tamperedPrepared,
        '2026-08-20T12:06:00Z'
    );
    red_addon_checkout_mutation_test_assert(
        ($tamperedAuthorization['status'] ?? null) === 'authorized'
            && ($tamperedPlan['status'] ?? null)
                === 'authorization_record_refused',
        'altered P3E-9C1 ledger evidence fails closed'
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
        'read-only credential profile cannot enter mutation claim'
    );

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_sandbox_checkout_mutation_claim_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'socket_',
        'api.stripe.com', 'Authorization:', 'php://input', '$_POST',
        '$_SERVER', 'getenv(', 'putenv(', 'shell_exec(', 'sleep(', 'usleep(',
    ] as $forbidden) {
        red_addon_checkout_mutation_test_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from mutation-claim source'
        );
    }
    red_addon_checkout_mutation_test_assert(
        !str_contains($source, 'red_addon_runtime_register_package')
            && !str_contains($source, 'red_addon_adapter_invoke_registered')
            && !str_contains($source, 'RED_Addon_Runtime_Secret_Access'),
        'claim source has no package execution or secret-access path'
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
        'cleanup removes every claim, package, actor, table, and file fixture'
    );

    echo 'Sandbox Checkout P3E-9C2 mutation claim self-test passed: '
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
