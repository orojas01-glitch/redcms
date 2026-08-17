<?php
/**
 * Disposable database checks for P3E-7 owner revalidation and nonce use.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_PAYMENT_ADAPTER_DATABASE_FIXTURE_ONLY', true);
require_once __DIR__ . '/addon-payment-adapter-database-preflight-self-test.php';
require_once $projectRoot .
    '/includes/addon_provider_contact_authorization_helpers.php';

$assertions = 0;
$actorId = 2147000993;
$adapterPackageId = 'redcms.store-lite-stripe-checkout';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_Contact_Authorization_Fixture';
$temporaryRoot = sys_get_temp_dir() . '/redcms-provider-contact-auth-' .
    bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$packageIds = [$adapterPackageId, $storePackageId];

function red_addon_provider_contact_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_provider_contact_test_cleanup(
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

function red_addon_provider_contact_test_readiness()
{
    $plan = [
        'operation' => 'stripe.sandbox.read-only-resource-miss-probe',
        'packageId' => 'redcms.store-lite-stripe-checkout',
        'packageVersion' => '0.1.1',
        'packageArtifactSha256' => str_repeat('a', 64),
        'runtimeProviderTransport' => 'disabled',
        'method' => 'GET',
        'url' => 'https://api.stripe.com/v1/checkout/sessions/' .
            'cs_test_redcms_readiness_probe',
        'expectedEffect' => 'read-only-resource-miss',
        'responseBodyProjection' => 'none',
        'credentialSettingKey' => 'stripe.secret-key',
        'credentialMode' => 'restricted_test',
        'credentialSource' => 'process_environment',
        'credentialValueIncluded' => false,
        'credentialValueSha256Included' => false,
        'credentialEvidenceSha256' => str_repeat('b', 64),
        'minimumTlsVersion' => '1.2',
        'verifyPeer' => true,
        'verifyHost' => true,
        'proxyMode' => 'disabled',
        'followRedirects' => false,
        'maximumRedirects' => 0,
        'connectTimeoutMilliseconds' => 5000,
        'totalTimeoutMilliseconds' => 15000,
        'maximumResponseBytes' => 65536,
        'maximumAttempts' => 1,
        'oneTimeAuthorizationRequired' => true,
        'retryAuthorized' => false,
        'mutationAuthorized' => false,
        'checkoutCreationAuthorized' => false,
        'paymentAuthorized' => false,
        'webhookAuthorized' => false,
        'liveModeAuthorized' => false,
        'clientDeploymentAuthorized' => false,
        'executionPerformed' => false,
    ];
    return [
        'ready' => true,
        'contactPlan' => $plan,
        'planSha256' => hash(
            'sha256',
            json_encode($plan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ),
        'executionPerformed' => false,
        'errors' => [],
    ];
}

function red_addon_provider_contact_test_prepared(
    array $readiness,
    $ownerSubjectSha256,
    $nonceSha256,
    $issuedAt = '2026-08-17T12:00:00Z',
    $expiresAt = '2026-08-17T12:15:00Z'
) {
    $authorization = [
        'action' => 'authorize-stripe-sandbox-read-only-probe',
        'planSha256' => $readiness['planSha256'],
        'operatorSubjectSha256' => $ownerSubjectSha256,
        'authorizationNonceSha256' => $nonceSha256,
        'issuedAtUtc' => $issuedAt,
        'expiresAtUtc' => $expiresAt,
        'maximumAttempts' => 1,
        'oneTimeConsumptionRequired' => true,
        'ownerAuthorityRevalidationRequired' => true,
        'restrictedTestKeyRequired' => true,
        'readOnlyGetAuthorized' => true,
        'retryAuthorized' => false,
        'mutationAuthorized' => false,
        'checkoutCreationAuthorized' => false,
        'paymentAuthorized' => false,
        'webhookAuthorized' => false,
        'liveModeAuthorized' => false,
        'clientDeploymentAuthorized' => false,
        'credentialValueIncluded' => false,
        'contactAuthorized' => false,
        'executionPerformed' => false,
    ];
    return [
        'prepared' => true,
        'authorization' => $authorization,
        'authorizationSha256' => hash(
            'sha256',
            json_encode(
                $authorization,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        ),
        'ownerAuthorityRevalidationRequired' => true,
        'nonceConsumptionRequired' => true,
        'contactAuthorized' => false,
        'executionPerformed' => false,
        'errors' => [],
    ];
}

red_addon_provider_contact_test_cleanup(
    $connection,
    $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
);

try {
    $password = password_hash('ProviderContactAuth-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_provider_contact_auth', ?, 'Admin',
                   'ContactAuth', 'webmaster', '', '',
                   'provider-contact@example.test', 'N', 'to', 'N', 'to')"
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
        'exact packages discover without executing package PHP: ' .
            json_encode([
                'errors' => $catalog['errors'] ?? [],
                'dependency' => $catalog['dependency']['errors'] ?? [],
                'valid' => $catalog['valid'] ?? null,
                'packages' => array_keys($catalog['packages'] ?? []),
                'adapterValid' => $adapterPackage['valid'] ?? null,
                'storeValid' => $storePackage['valid'] ?? null,
                'marker' => file_exists($executionMarker),
                'adapter' => $adapterPackage['errors'] ?? [],
                'store' => $storePackage['errors'] ?? [],
            ])
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
        str_repeat('d', 64)
    );
    $plan = red_addon_provider_contact_authorization_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $prepared,
        '2026-08-17T12:07:30Z'
    );
    red_addon_provider_contact_test_assert(
        $plan['valid'] === true
            && $plan['ready'] === true
            && $plan['status'] === 'ready'
            && $plan['ownerAuthorityRevalidated'] === true
            && $plan['contactAuthorized'] === false
            && $plan['nonceConsumed'] === false
            && $plan['executionPerformed'] === false,
        'fresh owner and exact P3E-6 evidence produce a non-writing plan'
    );
    red_addon_provider_contact_test_assert(
        $plan['packageId'] === $adapterPackageId
            && $plan['packageVersion'] === '0.1.1'
            && $plan['lifecycleState'] === 'enabled'
            && $plan['ownerSubjectSha256'] === $ownerSubject
            && $plan['planSha256'] === $readiness['planSha256']
            && $plan['authorizationSha256']
                === $prepared['authorizationSha256']
            && $plan['authorizationNonceSha256'] === str_repeat('d', 64)
            && red_addon_provider_contact_sha256(
                $plan['authorizationStateSha256']
            ),
        'plan binds package, owner, nonce, envelope, and state evidence'
    );
    red_addon_provider_contact_test_assert(
        $plan['secretResolution'] === false
            && $plan['networkAccess'] === false
            && $plan['providerContact'] === false
            && $plan['checkoutCreation'] === false
            && $plan['payment'] === false
            && $plan['webhook'] === false
            && $plan['storeLiteMutation'] === false
            && $plan['clientDeployment'] === false,
        'planning retains every credential, provider, payment, and client stop'
    );
    red_addon_provider_contact_test_assert(
        red_addon_provider_contact_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='$adapterPackageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$adapterPackageId'))"
        ) === '0:0',
        'planning writes no nonce or audit evidence'
    );

    $authorized = red_addon_provider_contact_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $prepared,
        $prepared['authorizationSha256'],
        '2026-08-17T12:07:30Z'
    );
    red_addon_provider_contact_test_assert(
        $authorized['status'] === 'authorized'
            && $authorized['nonceConsumed'] === true
            && $authorized['auditRecorded'] === true
            && $authorized['contactAuthorized'] === true
            && $authorized['executionPerformed'] === false
            && $authorized['providerContact'] === false
            && $authorized['networkAccess'] === false,
        'atomic apply authorizes one future contact without executing it: ' .
            json_encode($authorized)
    );
    $actionId = red_addon_provider_contact_action_id(str_repeat('d', 64));
    $stored = mysqli_query(
        $connection,
        "SELECT PlanSHA256, ContractSHA256, PreviousStateSHA256,
                StateSHA256, ActorAdminRecordID
         FROM RED_Addon_Admin_Action_Executions
         WHERE PackageID='$adapterPackageId'
           AND ActionID='$actionId'
           AND TargetRecordID=1"
    );
    $storedRow = $stored ? mysqli_fetch_assoc($stored) : null;
    if ($stored) {
        mysqli_free_result($stored);
    }
    red_addon_provider_contact_test_assert(
        is_array($storedRow)
            && $storedRow['PlanSHA256'] === $readiness['planSha256']
            && $storedRow['ContractSHA256']
                === $prepared['authorizationSha256']
            && $storedRow['PreviousStateSHA256'] === $ownerSubject
            && $storedRow['StateSHA256']
                === $authorized['authorizationStateSha256']
            && (int) $storedRow['ActorAdminRecordID'] === $actorId,
        'immutable ledger stores only exact value-free authorization hashes'
    );
    red_addon_provider_contact_test_assert(
        red_addon_provider_contact_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE EventName='addon.action.completed'
               AND PackageID='$adapterPackageId'
               AND PackageVersion='0.1.1'
               AND ActorAdminRecordID=$actorId
               AND Result='succeeded'
               AND DetailCode='provider_contact_authorized'"
        ) === '1',
        'one value-free audit fact commits with nonce consumption'
    );

    $replay = red_addon_provider_contact_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $prepared,
        $prepared['authorizationSha256'],
        '2026-08-17T12:08:00Z'
    );
    red_addon_provider_contact_test_assert(
        $replay['status'] === 'nonce_already_consumed'
            && $replay['nonceConsumed'] === false
            && $replay['contactAuthorized'] === false,
        'the exact authorization cannot consume its nonce twice: ' .
            json_encode($replay)
    );
    $changedEnvelope = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('d', 64),
        '2026-08-17T12:01:00Z',
        '2026-08-17T12:14:00Z'
    );
    $changedReplay = red_addon_provider_contact_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $changedEnvelope,
        $changedEnvelope['authorizationSha256'],
        '2026-08-17T12:08:00Z'
    );
    red_addon_provider_contact_test_assert(
        $changedReplay['status'] === 'nonce_already_consumed',
        'the database key refuses reuse even under a newly hashed envelope'
    );

    $secondPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('e', 64)
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$actorId AND Capability='addons.enable'"
    );
    $revoked = red_addon_provider_contact_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $secondPrepared,
        $secondPrepared['authorizationSha256'],
        '2026-08-17T12:08:00Z'
    );
    red_addon_provider_contact_test_assert(
        $revoked['status'] === 'invalid'
            && $revoked['errors'] === ['owner_authority_refused']
            && $revoked['contactAuthorized'] === false,
        'revoked exact Owner capability fails on the current decision'
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

    $expired = red_addon_provider_contact_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $secondPrepared,
        $secondPrepared['authorizationSha256'],
        '2026-08-17T12:15:00Z'
    );
    red_addon_provider_contact_test_assert(
        $expired['status'] === 'invalid'
            && $expired['errors'] === ['authorization_evidence_refused'],
        'authorization expires at the exact upper UTC bound'
    );

    $wrongOwnerPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        str_repeat('f', 64),
        str_repeat('e', 64)
    );
    $wrongOwner = red_addon_provider_contact_authorization_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $wrongOwnerPrepared,
        '2026-08-17T12:08:00Z'
    );
    red_addon_provider_contact_test_assert(
        $wrongOwner['errors'] === ['owner_subject_refused'],
        'opaque operator hash must equal the core-derived client Owner subject'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='installed_disabled'
         WHERE PackageID='$storePackageId'"
    );
    $disabledStore = red_addon_provider_contact_authorization_plan(
        $connection,
        $adapterPackage,
        $catalog,
        $actorId,
        $readiness,
        $secondPrepared,
        '2026-08-17T12:08:00Z'
    );
    red_addon_provider_contact_test_assert(
        $disabledStore['errors'] === ['package_state_refused'],
        'disabled same-database Store Lite dependency blocks authorization'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='$storePackageId'"
    );

    $thirdPrepared = red_addon_provider_contact_test_prepared(
        $readiness,
        $ownerSubject,
        str_repeat('1', 64)
    );
    $auditFailure = red_addon_provider_contact_authorize(
        $connection,
        $fixtureProject,
        $actorId,
        $readiness,
        $thirdPrepared,
        $thirdPrepared['authorizationSha256'],
        '2026-08-17T12:08:00Z',
        static function () {
            return false;
        }
    );
    red_addon_provider_contact_test_assert(
        $auditFailure['status'] === 'audit_failed'
            && $auditFailure['nonceConsumed'] === false
            && $auditFailure['contactAuthorized'] === false,
        'audit failure rolls back the nonce reservation'
    );
    red_addon_provider_contact_test_assert(
        red_addon_provider_contact_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
             WHERE PackageID='$adapterPackageId'
               AND ActionID='" . red_addon_provider_contact_action_id(
                   str_repeat('1', 64)
               ) . "'"
        ) === '0',
        'failed transaction leaves the new nonce reusable and absent'
    );

    $tamperedReadiness = $readiness;
    $tamperedReadiness['contactPlan']['method'] = 'POST';
    red_addon_provider_contact_test_assert(
        !red_addon_provider_contact_readiness_valid($tamperedReadiness),
        'tampered provider plan is refused before owner or database mutation'
    );
    red_addon_provider_contact_test_assert(
        !file_exists($executionMarker)
            && !file_exists($executionMarker . '-adapter-handler')
            && !file_exists($executionMarker . '-route-handler'),
        'P3E-7 never executes package registrar or registered handlers'
    );
    $source = (string) file_get_contents(
        $projectRoot .
            '/includes/addon_provider_contact_authorization_helpers.php'
    );
    foreach ([
        'curl_', 'fsockopen(', 'stream_socket_client(', 'getenv(', 'putenv(',
        '$_SERVER', '$_POST', 'php://input', 'Authorization:', 'sk_test_',
        'rk_test_', 'sk_live_', 'api.stripe.com:443',
    ] as $forbidden) {
        red_addon_provider_contact_test_assert(
            !str_contains($source, $forbidden),
            $forbidden . ' is absent from core authorization source'
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
        'cleanup removes every authorization and fixture artifact exactly'
    );

    echo 'Provider contact P3E-7 authorization self-test passed: ' .
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

function red_addon_provider_contact_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

?>
