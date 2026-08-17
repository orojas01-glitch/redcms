<?php
/**
 * Disposable database checks for the P3E-8A one-attempt claim boundary.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_PROVIDER_CONTACT_AUTHORIZATION_FIXTURE_ONLY', true);
require_once __DIR__ . '/addon-provider-contact-authorization-self-test.php';
require_once $projectRoot .
    '/includes/addon_provider_contact_claim_helpers.php';

$assertions = 0;
$actorId = 2147000992;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_Contact_Claim_Fixture';
$temporaryRoot = sys_get_temp_dir() . '/redcms-provider-contact-claim-' .
    bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$packageIds = [$adapterPackageId, $storePackageId];

function red_addon_provider_contact_claim_test_authorize(
    $connection,
    $fixtureProject,
    $actorId,
    array $readiness,
    array $prepared,
    $evaluatedAtUtc
) {
    return red_addon_provider_contact_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $prepared,
        $prepared['authorizationSha256'],
        $evaluatedAtUtc
    );
}

red_addon_provider_contact_test_cleanup(
    $connection,
    $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
);

try {
    $password = password_hash('ProviderContactClaim-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_provider_contact_claim', ?, 'Admin',
                   'ContactClaim', 'webmaster', '', '',
                   'provider-claim@example.test', 'N', 'to', 'N', 'to')"
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
    $capability = 'addons.enable';
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param($statement, 'isi', $actorId, $capability, $actorId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

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
        '0.1.1',
        $executionMarker,
        $tableName
    );
    $adapterManifestPath = $fixtureProject .
        '/addons/redcms/store-lite-stripe-checkout/addon.json';
    $adapterManifest = json_decode(
        (string) file_get_contents($adapterManifestPath),
        true,
        32,
        JSON_THROW_ON_ERROR
    );
    $adapterManifest['routes'][0]['path'] =
        '/addons/redcms/store-lite-stripe-checkout/provider-events';
    file_put_contents(
        $adapterManifestPath,
        json_encode(
            $adapterManifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? [];
    red_addon_provider_contact_test_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($adapterPackage['valid'])
            && !file_exists($executionMarker),
        'exact packages discover without executing package PHP'
    );
    red_addon_provider_contact_test_assert(
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
        'same disposable client records enabled Store Lite and adapter'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$tableName` (
            RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );

    $ownerSubject = red_addon_provider_contact_owner_subject_sha256(
        $connection,
        $actorId
    );
    $readiness = red_addon_provider_contact_test_readiness();
    $prepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('1', 64)
    );
    $authorized = red_addon_provider_contact_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $prepared,
        '2026-08-17T12:05:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($authorized['status'] ?? '') === 'authorized'
            && !empty($authorized['contactAuthorized'])
            && empty($authorized['executionPerformed']),
        'P3E-7 authorization exists before claim planning'
    );

    $plan = red_addon_provider_contact_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $prepared,
        '2026-08-17T12:06:00Z'
    );
    red_addon_provider_contact_test_assert(
        !empty($plan['valid'])
            && !empty($plan['ready'])
            && ($plan['status'] ?? '') === 'ready'
            && !empty($plan['authorizationRecorded'])
            && !empty($plan['claimAvailable'])
            && empty($plan['attemptClaimed'])
            && empty($plan['executionPerformed']),
        'dry-run plan accepts one exact unclaimed authorization'
    );
    red_addon_provider_contact_test_assert(
        red_addon_provider_contact_sha256($plan['claimStateSha256'] ?? '')
            && ($plan['claimActionId'] ?? '') ===
                red_addon_provider_contact_claim_action_id(str_repeat('1', 64))
            && hash_equals(
                $authorized['authorizationStateSha256'],
                $plan['authorizationStateSha256']
            ),
        'claim binds nonce and exact persisted authorization state'
    );
    red_addon_provider_contact_test_assert(
        red_addon_provider_contact_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId')
             )"
        ) === '1:1',
        'claim planning writes no execution or audit row'
    );

    $claimed = red_addon_provider_contact_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $prepared,
        $plan['authorizationSha256'],
        $plan['authorizationStateSha256'],
        $plan['claimStateSha256'],
        '2026-08-17T12:06:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($claimed['status'] ?? '') === 'claimed'
            && !empty($claimed['attemptClaimed'])
            && !empty($claimed['auditRecorded'])
            && empty($claimed['executionPerformed'])
            && empty($claimed['secretResolution'])
            && empty($claimed['networkAccess'])
            && empty($claimed['providerContact']),
        'apply commits only the attempt claim and bounded audit fact'
    );
    $claimActionId = red_addon_provider_contact_claim_action_id(
        str_repeat('1', 64)
    );
    $claimActionEscaped = mysqli_real_escape_string(
        $connection,
        $claimActionId
    );
    red_addon_provider_contact_test_assert(
        red_addon_provider_contact_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
             WHERE PackageID='$adapterPackageId'
               AND ActionID='$claimActionEscaped'
               AND TargetRecordID=1
               AND PlanSHA256='{$plan['planSha256']}'
               AND ContractSHA256='{$plan['authorizationSha256']}'
               AND PreviousStateSHA256='{$plan['authorizationStateSha256']}'
               AND StateSHA256='{$plan['claimStateSha256']}'
               AND ActorAdminRecordID=$actorId"
        ) === '1',
        'immutable claim row stores only exact hashes and numeric actor'
    );
    red_addon_provider_contact_test_assert(
        red_addon_provider_contact_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID='$adapterPackageId'
               AND DetailCode='provider_contact_attempt_claimed'"
        ) === '1',
        'one value-free claim audit fact commits atomically'
    );

    $replay = red_addon_provider_contact_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $prepared,
        $plan['authorizationSha256'],
        $plan['authorizationStateSha256'],
        $plan['claimStateSha256'],
        '2026-08-17T12:07:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($replay['status'] ?? '') === 'attempt_already_claimed'
            && empty($replay['attemptClaimed']),
        'the authorized attempt cannot be claimed twice'
    );

    $changedPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('1', 64),
        '2026-08-17T12:01:00Z',
        '2026-08-17T12:14:00Z'
    );
    $changedPlan = red_addon_provider_contact_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $changedPrepared,
        '2026-08-17T12:07:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($changedPlan['status'] ?? '') === 'authorization_record_refused',
        'changed envelope cannot borrow the persisted nonce authorization'
    );

    $missingPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('2', 64)
    );
    $missingPlan = red_addon_provider_contact_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $missingPrepared,
        '2026-08-17T12:06:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($missingPlan['status'] ?? '') === 'authorization_record_refused',
        'claim refuses an envelope without a committed P3E-7 authorization'
    );

    $revokedPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('3', 64)
    );
    $revokedAuthorization = red_addon_provider_contact_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $revokedPrepared,
        '2026-08-17T12:05:00Z'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=$actorId"
    );
    $revokedPlan = red_addon_provider_contact_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $revokedPrepared,
        '2026-08-17T12:06:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($revokedAuthorization['status'] ?? '') === 'authorized'
            && ($revokedPlan['status'] ?? '') === 'authorization_refused',
        'current Owner authority is revalidated after authorization'
    );
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param($statement, 'isi', $actorId, $capability, $actorId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $disabledPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('4', 64)
    );
    $disabledAuthorization = red_addon_provider_contact_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $disabledPrepared,
        '2026-08-17T12:05:00Z'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='disabled'
         WHERE PackageID='$storePackageId'"
    );
    $disabledPlan = red_addon_provider_contact_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $disabledPrepared,
        '2026-08-17T12:06:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($disabledAuthorization['status'] ?? '') === 'authorized'
            && ($disabledPlan['status'] ?? '') === 'authorization_refused',
        'disabled same-database Store Lite invalidates the future claim'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='$storePackageId'"
    );

    $expiredPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('5', 64)
    );
    $expiredAuthorization = red_addon_provider_contact_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $expiredPrepared,
        '2026-08-17T12:05:00Z'
    );
    $expiredPlan = red_addon_provider_contact_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $expiredPrepared,
        '2026-08-17T12:15:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($expiredAuthorization['status'] ?? '') === 'authorized'
            && ($expiredPlan['status'] ?? '') === 'authorization_refused',
        'authorization expiry is rechecked before claim'
    );

    $rollbackPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('6', 64)
    );
    $rollbackAuthorization = red_addon_provider_contact_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $rollbackPrepared,
        '2026-08-17T12:05:00Z'
    );
    $rollbackPlan = red_addon_provider_contact_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $rollbackPrepared,
        '2026-08-17T12:06:00Z'
    );
    $auditFailure = red_addon_provider_contact_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $rollbackPrepared,
        $rollbackPlan['authorizationSha256'],
        $rollbackPlan['authorizationStateSha256'],
        $rollbackPlan['claimStateSha256'],
        '2026-08-17T12:06:00Z',
        static function () {
            return false;
        }
    );
    $rollbackAction = mysqli_real_escape_string(
        $connection,
        red_addon_provider_contact_claim_action_id(str_repeat('6', 64))
    );
    red_addon_provider_contact_test_assert(
        ($rollbackAuthorization['status'] ?? '') === 'authorized'
            && ($auditFailure['status'] ?? '') === 'audit_failed'
            && red_addon_provider_contact_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'
                   AND ActionID='$rollbackAction'"
            ) === '0',
        'audit failure rolls back the attempt claim'
    );
    $afterRollback = red_addon_provider_contact_claim(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $rollbackPrepared,
        $rollbackPlan['authorizationSha256'],
        $rollbackPlan['authorizationStateSha256'],
        $rollbackPlan['claimStateSha256'],
        '2026-08-17T12:06:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($afterRollback['status'] ?? '') === 'claimed',
        'rolled-back claim remains available for its one real attempt'
    );

    $tamperedPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('7', 64)
    );
    $tamperedAuthorization = red_addon_provider_contact_claim_test_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $tamperedPrepared,
        '2026-08-17T12:05:00Z'
    );
    $tamperedAction = mysqli_real_escape_string(
        $connection,
        red_addon_provider_contact_action_id(str_repeat('7', 64))
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Admin_Action_Executions
         SET StateSHA256='" . str_repeat('0', 64) . "'
         WHERE PackageID='$adapterPackageId'
           AND ActionID='$tamperedAction' AND TargetRecordID=1"
    );
    $tamperedPlan = red_addon_provider_contact_claim_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $tamperedPrepared,
        '2026-08-17T12:06:00Z'
    );
    red_addon_provider_contact_test_assert(
        ($tamperedAuthorization['status'] ?? '') === 'authorized'
            && ($tamperedPlan['status'] ?? '')
                === 'authorization_record_refused',
        'altered authorization ledger evidence fails closed'
    );

    red_addon_provider_contact_test_assert(
        !file_exists($executionMarker)
            && !file_exists($executionMarker . '-adapter-handler')
            && !file_exists($executionMarker . '-route-handler'),
        'P3E-8A never executes package registrar or registered handlers'
    );
    $source = (string) file_get_contents(
        $projectRoot . '/includes/addon_provider_contact_claim_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'getenv(', 'putenv(',
        '$_SERVER', '$_POST', 'php://input', 'Authorization:', 'sk_test_',
        'rk_test_', 'sk_live_', 'api.stripe.com', 'red_addon_secret_resolve',
    ] as $forbidden) {
        red_addon_provider_contact_test_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from core claim source'
        );
    }

    red_addon_provider_contact_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
    red_addon_provider_contact_test_assert(
        red_addon_provider_contact_test_scalar(
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
        ) === '0:0:0:0:0',
        'cleanup removes every claim and fixture artifact exactly'
    );

    echo 'Provider contact P3E-8A claim self-test passed: ' .
        $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_addon_provider_contact_test_cleanup(
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
