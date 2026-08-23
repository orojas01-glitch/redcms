<?php
/** Disposable C4B4B durable Wompi authorization/claim acceptance. */

define('RED_WOMPI_C3C1_FIXTURE_ONLY', true);
require_once __DIR__ . '/wompi-payment-adapter-c3c1-self-test.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_wompi_no_contact_claim_helpers.php';

$assertions = 0;

function red_wompi_c4b4b_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c4b4b_evidence(
    $connection,
    $actorId,
    $now,
    $authorizationDigit,
    $claimDigit,
    $databaseOverride = null
) {
    $transient = [
        'customer_email' => 'synthetic-buyer@example.test',
        'phone_number' => '3105550123',
        'acceptance_token' =>
            'synthetic.end.user.' . str_repeat('a', 32),
        'accept_personal_auth' =>
            'synthetic.personal.auth.' . str_repeat('b', 32),
        'private_key' => 'prv_' . 'test_' . str_repeat('c', 32),
        'integrity_secret' =>
            'test_' . 'integrity_' . str_repeat('d', 32),
    ];
    $merchantPlan =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Request_Planner::plan([
            'publicKeySettingPresent' => true,
            'publicKeySha256' => str_repeat('1', 64),
        ]);
    $projection =
        RED_CMS_Store_Lite_Wompi_Merchant_Contract_Response_Gate::project(
            $merchantPlan,
            [
                'data' => [
                    'presigned_acceptance' => [
                        'acceptance_token' =>
                            $transient['acceptance_token'],
                        'permalink' =>
                            'https://contracts.wompi.co/synthetic-end-user.pdf',
                        'type' => 'END_USER_POLICY',
                    ],
                    'presigned_personal_data_auth' => [
                        'acceptance_token' =>
                            $transient['accept_personal_auth'],
                        'permalink' =>
                            'https://contracts.wompi.com/synthetic-personal.pdf',
                        'type' => 'PERSONAL_DATA_AUTH',
                    ],
                ],
            ]
        );
    $presentation =
        RED_CMS_Store_Lite_Wompi_Contract_Consent_Presentation::present(
            $projection
        );
    $consent = RED_CMS_Store_Lite_Wompi_Contract_Consent_Evidence::record(
        $presentation,
        [
            'orderId' => 'ord_0123456789abcdef0123456789abcdef',
            'subjectSha256' => str_repeat('4', 64),
            'presentationSha256' => $presentation['presentationSha256'],
            'contractsSha256' => $presentation['contractsSha256'],
            'acceptanceTokenSha256' =>
                $presentation['acceptanceTokenSha256'],
            'personalAuthTokenSha256' =>
                $presentation['personalAuthTokenSha256'],
            'consentNonceSha256' => str_repeat('5', 64),
            'endUserPolicyPresented' => true,
            'personalDataAuthPresented' => true,
            'endUserPolicyAccepted' => true,
            'personalDataAuthAccepted' => true,
            'acceptedAtEpoch' => $now - 10,
        ],
        $now
    );
    $order = [
        'orderId' => 'ord_0123456789abcdef0123456789abcdef',
        'orderSnapshotSha256' => str_repeat('2', 64),
        'amountMinor' => 12500000,
        'currency' => 'COP',
        'idempotencySha256' => str_repeat('3', 64),
        'customerEmailSha256' => hash(
            'sha256',
            $transient['customer_email']
        ),
        'customerPhoneSha256' => hash(
            'sha256',
            $transient['phone_number']
        ),
    ];
    $plan = RED_CMS_Store_Lite_Wompi_Nequi_Request_Planner::plan(
        $order,
        [
            'privacyAccepted' => true,
            'personalDataAccepted' => true,
            'acceptanceTokenSha256' =>
                $consent['acceptanceTokenSha256'],
            'personalAuthTokenSha256' =>
                $consent['personalAuthTokenSha256'],
            'contractsSha256' => $consent['contractsSha256'],
        ],
        [
            'publicKeySettingPresent' => true,
            'privateKeyReferenceAvailable' => true,
            'integrityKeyReferenceAvailable' => true,
            'eventSecretReferenceAvailable' => true,
        ]
    );
    $wire =
        RED_CMS_Store_Lite_Wompi_Nequi_Transient_Wire_Request_Builder::build(
            $plan,
            $order,
            $consent,
            $transient,
            $now
        );
    $scope = [
        'clientScopeSha256' =>
            red_addon_wompi_claim_client_scope_sha256($connection),
        'databaseSha256' => $databaseOverride
            ?? red_addon_wompi_claim_database_sha256($connection),
        'actorSubjectSha256' =>
            red_addon_wompi_claim_actor_subject_sha256(
                $connection,
                $actorId
            ),
        'secretAvailabilitySha256' =>
            red_addon_wompi_claim_secret_availability_sha256($connection),
        'authorizationNonceSha256' =>
            str_repeat($authorizationDigit, 64),
        'issuedAtEpoch' => $now - 5,
        'expiresAtEpoch' => $now + 895,
        'ownerAuthorityRevalidated' => true,
        'orderAuthorityRevalidated' => true,
        'packageEnabled' => true,
        'storeEnabled' => true,
        'oneAttemptConfirmed' => true,
        'noRetryConfirmed' => true,
        'networkDisabledConfirmed' => true,
        'providerContactDenied' => true,
        'providerMutationDenied' => true,
        'orderMutationDenied' => true,
    ];
    $authorization =
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::authorize(
            $plan,
            $wire,
            $scope,
            $now
        );
    $claim =
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::prepareClaim(
            $authorization,
            [
                'authorizationSha256' =>
                    $authorization['authorizationSha256'],
                'claimNonceSha256' => str_repeat($claimDigit, 64),
                'claimedAtEpoch' => $now,
                'attemptNumber' => 1,
                'priorClaimEvidenceSha256' => [],
                'oneAttemptConfirmed' => true,
                'noRetryConfirmed' => true,
                'durableClaimRequired' => true,
            ],
            $now
        );
    return [$authorization, $claim];
}

if (!defined('RED_WOMPI_C4B4B_FIXTURE_ONLY')) {
try {
    $password = password_hash('WompiC4B4B-Disposable-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_wompi_c4b4b', ?, 'Admin', 'WompiC4B4B',
                   'webmaster', '', '', 'wompi-c4b4b@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    $inserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_wompi_c4b4b_assert($inserted, 'disposable Owner fixture is recorded');
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
    red_wompi_c4b4b_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT RoleName FROM RED_Admin_Roles
                 WHERE AdminRecordID=$actorId),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$actorId
                   AND Capability IN (
                     'addons.install','addons.enable','store.orders.manage'
                   ))
             )"
        ) === 'owner:3',
        'Owner has exact install, enable, and order authority'
    );

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $wompiPackage = $catalog['packages'][$wompiPackageId] ?? [];
    red_wompi_c4b4b_assert(
        !empty($catalog['valid'])
            && ($storePackage['manifest']['version'] ?? null) === '0.1.35'
            && ($wompiPackage['manifest']['version'] ?? null) === '0.1.4',
        'exact Store Lite and Wompi packages discover without execution'
    );
    red_wompi_c4b4b_assert(
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
    red_wompi_c4b4b_assert(
        !empty($installPlan['valid'])
            && ($installed['status'] ?? null) === 'installed_disabled',
        'Wompi installs disabled before separate enablement'
    );
    $references = [
        'wompi.private-key' => 'config:c4b4b-wompi-private',
        'wompi.integrity-key' => 'config:c4b4b-wompi-integrity',
        'wompi.event-secret' => 'config:c4b4b-wompi-event',
    ];
    red_wompi_c4b4b_assert(
        red_wompi_c3c1_store_settings(
            $connection,
            $wompiPackageId,
            $actorId,
            'sandbox-public-reference-c4b4b',
            $references
        ),
        'client-local public setting and three opaque references are stored'
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
    red_wompi_c4b4b_assert(
        !empty($enablePlan['valid'])
            && ($enabled['status'] ?? null) === 'enabled'
            && ($enabled['version'] ?? null) === '0.1.4',
        'Wompi enables atomically with value-free secret declarations'
    );

    require_once $fixtureProject
        . '/addons/redcms/store-lite-wompi/addon.php';
    $now = 1787443200;
    [$authorization, $claim] = red_wompi_c4b4b_evidence(
        $connection,
        $actorId,
        $now,
        '6',
        '7'
    );
    red_wompi_c4b4b_assert(
        RED_CMS_Store_Lite_Wompi_No_Contact_Attempt_Contract::validClaim(
            $claim,
            $authorization
        ),
        'external C4B4A source produces exact pure claim evidence'
    );
    $plan = red_addon_wompi_claim_plan(
        $connection,
        $wompiPackage,
        $catalog,
        $actorId,
        $authorization,
        $claim,
        $now
    );
    red_wompi_c4b4b_assert(
        !empty($plan['valid'])
            && !empty($plan['ready'])
            && $plan['authorizationAvailable']
            && $plan['claimAvailable']
            && !$plan['authorizationRecorded']
            && !$plan['claimRecorded']
            && !$plan['replayProtectionActive'],
        'dry plan revalidates exact evidence and writes nothing'
    );
    red_wompi_c4b4b_assert(
        $plan['ownerAuthorityRevalidated']
            && $plan['orderAuthorityRevalidated']
            && $plan['packageStateRevalidated']
            && $plan['settingAvailabilityRevalidated']
            && red_addon_wompi_claim_sha256(
                $plan['authorizationStateSha256']
            )
            && red_addon_wompi_claim_sha256($plan['claimStateSha256']),
        'plan binds current authority, package, settings, and state hashes'
    );
    red_wompi_c4b4b_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='redcms.store-lite-wompi'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND DetailCode IN (
                     'wompi_no_contact_authorized',
                     'wompi_no_contact_claimed'
                   )))"
        ) === '0:0',
        'planning creates no action or audit row'
    );

    $stale = red_addon_wompi_claim_record(
        $connection,
        $fixtureProject,
        $actorId,
        $authorization,
        $claim,
        str_repeat('8', 64),
        $plan['claimStateSha256'],
        $now
    );
    red_wompi_c4b4b_assert(
        $stale['status'] === 'claim_changed'
            && !$stale['authorizationRecorded']
            && !$stale['claimRecorded'],
        'changed expected state refuses before transaction writes'
    );

    $claimed = red_addon_wompi_claim_record(
        $connection,
        $fixtureProject,
        $actorId,
        $authorization,
        $claim,
        $plan['authorizationStateSha256'],
        $plan['claimStateSha256'],
        $now
    );
    red_wompi_c4b4b_assert(
        $claimed['status'] === 'claimed'
            && $claimed['authorizationRecorded']
            && $claimed['claimRecorded']
            && $claimed['replayProtectionActive']
            && $claimed['auditRecorded'],
        'one transaction commits authorization, claim, and both audits'
    );
    red_wompi_c4b4b_assert(
        !$claimed['executionAuthorized']
            && !$claimed['executionStarted']
            && !$claimed['executionPerformed']
            && !$claimed['secretResolution']
            && !$claimed['networkAccess']
            && !$claimed['providerContact']
            && !$claimed['providerMutation']
            && !$claimed['orderMutation']
            && !$claimed['retryAuthorized'],
        'durable claim still grants no execution or external effect'
    );
    red_wompi_c4b4b_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND ActionID LIKE 'wompi-no-contact-%'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND DetailCode IN (
                     'wompi_no_contact_authorized',
                     'wompi_no_contact_claimed'
                   )))"
        ) === '2:2',
        'ledger contains exactly two immutable actions and two audits'
    );
    $replay = red_addon_wompi_claim_record(
        $connection,
        $fixtureProject,
        $actorId,
        $authorization,
        $claim,
        $plan['authorizationStateSha256'],
        $plan['claimStateSha256'],
        $now
    );
    red_wompi_c4b4b_assert(
        $replay['status'] === 'attempt_already_claimed'
            && !$replay['authorizationRecorded']
            && !$replay['claimRecorded'],
        'replay refuses before any additional row or audit'
    );

    [$rollbackAuthorization, $rollbackClaim] = red_wompi_c4b4b_evidence(
        $connection,
        $actorId,
        $now,
        '8',
        '9'
    );
    $rollbackPlan = red_addon_wompi_claim_plan(
        $connection,
        $wompiPackage,
        $catalog,
        $actorId,
        $rollbackAuthorization,
        $rollbackClaim,
        $now
    );
    $beforeRollbackAuditCount = red_wompi_c3b_scalar(
        $connection,
        "SELECT COUNT(*) FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.store-lite-wompi'
           AND DetailCode IN (
             'wompi_no_contact_authorized',
             'wompi_no_contact_claimed'
           )"
    );
    $auditFailure = red_addon_wompi_claim_record(
        $connection,
        $fixtureProject,
        $actorId,
        $rollbackAuthorization,
        $rollbackClaim,
        $rollbackPlan['authorizationStateSha256'],
        $rollbackPlan['claimStateSha256'],
        $now,
        static function ($db, array $candidate, $purpose) {
            if ($purpose === 'claim') {
                return false;
            }
            return red_addon_wompi_claim_audit_record(
                $db,
                $candidate,
                $purpose
            );
        }
    );
    red_wompi_c4b4b_assert(
        $auditFailure['status'] === 'claim_audit_failed'
            && red_wompi_c3b_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Admin_Action_Executions
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND ActionID LIKE 'wompi-no-contact-%."
                    . $rollbackAuthorization['authorizationNonceSha256']
                    . "'"
            ) === '0'
            && red_wompi_c3b_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND DetailCode IN (
                     'wompi_no_contact_authorized',
                     'wompi_no_contact_claimed'
                   )"
            ) === $beforeRollbackAuditCount,
        'claim-audit failure rolls back both new actions and the first audit'
    );
    $recovered = red_addon_wompi_claim_record(
        $connection,
        $fixtureProject,
        $actorId,
        $rollbackAuthorization,
        $rollbackClaim,
        $rollbackPlan['authorizationStateSha256'],
        $rollbackPlan['claimStateSha256'],
        $now
    );
    red_wompi_c4b4b_assert(
        $recovered['status'] === 'claimed'
            && $recovered['replayProtectionActive'],
        'complete rollback permits one clean durable recovery'
    );

    [$authorityAuthorization, $authorityClaim] = red_wompi_c4b4b_evidence(
        $connection,
        $actorId,
        $now,
        'c',
        'd'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$actorId
           AND Capability='store.orders.manage'"
    );
    $revoked = red_addon_wompi_claim_plan(
        $connection,
        $wompiPackage,
        $catalog,
        $actorId,
        $authorityAuthorization,
        $authorityClaim,
        $now
    );
    red_wompi_c4b4b_assert(
        !$revoked['valid']
            && $revoked['errors'] === ['order_authority_refused'],
        'revoked order authority fails closed before persistence'
    );
    $capability = 'store.orders.manage';
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

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='installed_disabled'
         WHERE PackageID='redcms.store-lite-wompi'"
    );
    $disabled = red_addon_wompi_claim_plan(
        $connection,
        $wompiPackage,
        $catalog,
        $actorId,
        $authorityAuthorization,
        $authorityClaim,
        $now
    );
    red_wompi_c4b4b_assert(
        !$disabled['valid']
            && $disabled['errors'] === ['package_state_refused'],
        'disabled Wompi package cannot obtain durable claim state'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='enabled'
         WHERE PackageID='redcms.store-lite-wompi'"
    );

    [$wrongDatabaseAuthorization, $wrongDatabaseClaim] =
        red_wompi_c4b4b_evidence(
            $connection,
            $actorId,
            $now,
            'e',
            'f',
            str_repeat('e', 64)
        );
    $wrongDatabase = red_addon_wompi_claim_plan(
        $connection,
        $wompiPackage,
        $catalog,
        $actorId,
        $wrongDatabaseAuthorization,
        $wrongDatabaseClaim,
        $now
    );
    red_wompi_c4b4b_assert(
        !$wrongDatabase['valid']
            && $wrongDatabase['errors'] === ['client_scope_refused'],
        'evidence for a different database cannot be adopted'
    );

    $tamperedClaim = $authorityClaim;
    $tamperedClaim['claimSha256'] = str_repeat('f', 64);
    $tampered = red_addon_wompi_claim_plan(
        $connection,
        $wompiPackage,
        $catalog,
        $actorId,
        $authorityAuthorization,
        $tamperedClaim,
        $now
    );
    red_wompi_c4b4b_assert(
        !$tampered['valid']
            && $tampered['errors'] === ['claim_evidence_refused'],
        'tampered package claim fingerprint is refused'
    );

    mysqli_begin_transaction($connection);
    $nested = red_addon_wompi_claim_record(
        $connection,
        $fixtureProject,
        $actorId,
        $authorityAuthorization,
        $authorityClaim,
        str_repeat('1', 64),
        str_repeat('2', 64),
        $now
    );
    mysqli_rollback($connection);
    red_wompi_c4b4b_assert(
        $nested['status'] === 'invalid',
        'caller-owned active transaction is refused'
    );

    $source = (string) file_get_contents(
        $projectRoot
            . '/includes/addon_payment_adapter_wompi_no_contact_claim_helpers.php'
    );
    red_wompi_c4b4b_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\$_GET|\$_POST|\$_REQUEST|\$_COOKIE|'
                . '\$_SESSION|\bgetenv\s*\(|\bcurl_|\bfsockopen\s*\(|'
                . '\bstream_socket_client\s*\(|red_addon_runtime_secret|'
                . 'red_addon_adapter_invoke|\bheader\s*\(|'
                . '\bhttp_response_code\s*\()/i',
            $source
        ) !== 1,
        'C4B4B helper has no request, environment, secret, package invocation, network, or response path'
    );

    echo 'Wompi C4B4B durable claim self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
}

?>
