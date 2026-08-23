<?php
/**
 * Exact Wompi C3C1 body-signed ingress and atomic-enablement rehearsal.
 */

define('RED_WOMPI_C3B_FIXTURE_ONLY', true);
require_once __DIR__ . '/wompi-payment-adapter-c3b-self-test.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_enable_helpers.php';

$assertions = 0;

function red_wompi_c3c1_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c3c1_store_settings(
    $connection,
    $packageId,
    $actorId,
    $publicKey,
    array $secretReferences
) {
    if (array_keys($secretReferences) !== [
        'wompi.private-key',
        'wompi.integrity-key',
        'wompi.event-secret',
    ]) {
        return false;
    }
    $rows = [[
        'wompi.public-key',
        'text',
        json_encode($publicKey),
        null,
    ]];
    foreach ($secretReferences as $key => $reference) {
        $rows[] = [$key, 'secret-reference', null, $reference];
    }
    foreach ($rows as $row) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Settings (
                PackageID, SettingKey, ValueType, ValueJSON,
                SecretReference, UpdatedByAdminRecordID
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$statement) {
            return false;
        }
        mysqli_stmt_bind_param(
            $statement,
            'sssssi',
            $packageId,
            $row[0],
            $row[1],
            $row[2],
            $row[3],
            $actorId
        );
        $stored = mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        if (!$stored) {
            return false;
        }
    }
    return true;
}

function red_wompi_c3c1_state_fingerprint($connection)
{
    $queries = [
        "SELECT PackageID, LifecycleState, UpdatedByAdminRecordID
         FROM RED_Addon_Installations
         WHERE PackageID IN ('redcms.store-lite','redcms.store-lite-wompi')
         ORDER BY PackageID",
        "SELECT PackageID, SettingKey, ValueType, ValueJSON,
                SecretReference, UpdatedByAdminRecordID
         FROM RED_Addon_Settings
         WHERE PackageID='redcms.store-lite-wompi'
         ORDER BY SettingKey",
        "SELECT EventName, PackageID, PackageVersion, ActorAdminRecordID,
                Result, DetailCode
         FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.store-lite-wompi'
         ORDER BY RecordID",
        'SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Payment_Attempts',
        'SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Event_Receipts',
    ];
    $material = [];
    foreach ($queries as $query) {
        $result = mysqli_query($connection, $query);
        if (!$result) {
            throw new RuntimeException('Could not fingerprint C3C1 state.');
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        $material[] = $rows;
    }
    return hash(
        'sha256',
        json_encode($material, JSON_UNESCAPED_SLASHES)
    );
}

if (!defined('RED_WOMPI_C3C1_FIXTURE_ONLY')) {
try {
    $password = password_hash('WompiC3C1-Disposable-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_wompi_c3c1', ?, 'Admin', 'WompiC3C1',
                   'webmaster', '', '', 'wompi-c3c1@example.test',
                   'N', 'to', 'N', 'to')"
    );
    if (!$statement) {
        throw new RuntimeException('Could not prepare C3C1 Owner fixture.');
    }
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    $inserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_wompi_c3c1_assert($inserted, 'disposable Owner fixture is recorded');
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    foreach (['addons.install', 'addons.enable'] as $capability) {
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
    red_wompi_c3c1_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT RoleName FROM RED_Admin_Roles
                 WHERE AdminRecordID=$actorId),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$actorId
                   AND Capability IN ('addons.install','addons.enable'))
             )"
        ) === 'owner:2',
        'persisted Owner has the exact lifecycle authority used by C3C1'
    );

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $wompiPackage = $catalog['packages'][$wompiPackageId] ?? [];
    red_wompi_c3c1_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($wompiPackage['valid'])
            && ($storePackage['manifest']['version'] ?? null) === '0.1.35'
            && ($wompiPackage['manifest']['version'] ?? null) === '0.1.4',
        'exact Store Lite and Wompi packages discover together'
    );
    red_wompi_c3c1_assert(
        red_wompi_c3b_record_enabled_store(
            $connection,
            $storePackage,
            $actorId
        ),
        'exact Store Lite identity supplies the enabled dependency baseline'
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
    red_wompi_c3c1_assert(
        !empty($installPlan['valid'])
            && $installed['status'] === 'installed_disabled'
            && $installed['appliedMigrations'] === [
                '2026-08-23-wompi-payment-attempts',
                '2026-08-23-wompi-event-receipts',
            ],
        'guarded Wompi install completes disabled with exact migrations'
    );

    $publicKey = 'sandbox-public-reference-c3c1';
    $secretReferences = [
        'wompi.private-key' => 'config:c3c1-wompi-private',
        'wompi.integrity-key' => 'config:c3c1-wompi-integrity',
        'wompi.event-secret' => 'config:c3c1-wompi-event',
    ];
    red_wompi_c3c1_assert(
        red_wompi_c3c1_store_settings(
            $connection,
            $wompiPackageId,
            $actorId,
            $publicKey,
            $secretReferences
        ),
        'client-local public setting and three opaque references are stored'
    );
    $declarations = red_addon_secret_reference_declarations(
        array_values($secretReferences),
        ''
    );
    red_wompi_c3c1_assert(
        !empty($declarations['valid'])
            && count($declarations['references']) === 3,
        'all three references are declared available without secret values'
    );

    $beforePlan = red_wompi_c3c1_state_fingerprint($connection);
    $plan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $wompiPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_wompi_c3c1_assert(
        red_addon_payment_adapter_enablement_plan_is_valid($plan)
            && $plan['profileId'] === 'store_lite_wompi_adapter_v1'
            && $plan['enableReady']
            && $plan['activationSupported']
            && !$plan['stateMutation']
            && !$plan['runtimePublication']
            && !$plan['handlerInvocation']
            && !$plan['secretResolution']
            && !$plan['networkAccess']
            && !$plan['routeExposure'],
        'exact Wompi evidence yields one value-free enable-ready plan'
    );
    red_wompi_c3c1_assert(
        $plan['settingCount'] === 4
            && $plan['configuredSettingCount'] === 4
            && $plan['secretSettingCount'] === 3
            && $plan['availableSecretCount'] === 3
            && $plan['blockers'] === []
            && red_addon_valid_sha256($plan['ingressContractSha256'])
            && red_addon_valid_sha256($plan['settingsStateSha256'])
            && red_addon_valid_sha256(
                $plan['secretAvailabilitySha256']
            ),
        'plan signs exact Wompi setting, secret, and body-ingress evidence'
    );
    $encodedPlan = json_encode(
        $plan,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    red_wompi_c3c1_assert(
        is_string($encodedPlan)
            && !str_contains($encodedPlan, $publicKey)
            && !str_contains(
                $encodedPlan,
                implode('', array_values($secretReferences))
            )
            && !str_contains($encodedPlan, 'config:c3c1-wompi-'),
        'enable plan contains no public value or opaque secret reference'
    );
    red_wompi_c3c1_assert(
        hash_equals(
            $beforePlan,
            red_wompi_c3c1_state_fingerprint($connection)
        ),
        'planning changes no lifecycle, setting, audit, or payment fact'
    );

    $partialDeclarations = red_addon_secret_reference_declarations(
        array_slice(array_values($secretReferences), 0, 2),
        ''
    );
    $missingSecret = red_addon_payment_adapter_enablement_plan(
        $connection,
        $wompiPackage,
        $actorId,
        $catalog,
        $partialDeclarations
    );
    red_wompi_c3c1_assert(
        empty($missingSecret['valid'])
            && in_array(
                'payment_adapter_configuration_incomplete',
                $missingSecret['errors'],
                true
            ),
        'one missing Wompi secret declaration fails without resolution'
    );
    $stale = red_addon_payment_adapter_enable_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        hash('sha256', 'stale-wompi-c3c1-plan'),
        $declarations
    );
    red_wompi_c3c1_assert(
        $stale['status'] === 'plan_changed'
            && red_wompi_c3b_scalar(
                $connection,
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.store-lite-wompi'"
            ) === 'installed_disabled',
        'stale Wompi plan fails before lifecycle mutation'
    );
    $forcedFailure = red_addon_payment_adapter_enable_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations,
        null,
        static function (): void {
            throw new RuntimeException('forced_c3c1_after_state_failure');
        }
    );
    red_wompi_c3c1_assert(
        $forcedFailure['status'] === 'enable_transaction_failed'
            && red_wompi_c3b_scalar(
                $connection,
                "SELECT CONCAT_WS(':', LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.store-lite-wompi'
                       AND EventName='addon.enable.completed'))
                 FROM RED_Addon_Installations
                 WHERE PackageID='redcms.store-lite-wompi'"
            ) === 'installed_disabled:0',
        'failure after compare-and-swap rolls back state and audit atomically'
    );

    $enabled = red_addon_payment_adapter_enable_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_wompi_c3c1_assert(
        $enabled['status'] === 'enabled'
            && $enabled['packageId'] === $wompiPackageId
            && $enabled['version'] === '0.1.4'
            && hash_equals(
                $enabled['registrationSha256'],
                $plan['registrationSha256']
            )
            && hash_equals(
                $enabled['ingressContractSha256'],
                $plan['ingressContractSha256']
            ),
        'exact locked revalidation atomically enables Wompi'
    );
    red_wompi_c3c1_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':', LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND EventName='addon.enable.completed'
                   AND Result='succeeded'
                   AND DetailCode='payment_adapter_enabled'),
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='redcms.store-lite-wompi'),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Payment_Attempts),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Event_Receipts))
             FROM RED_Addon_Installations
             WHERE PackageID='redcms.store-lite-wompi'"
        ) === 'enabled:1:4:0:0',
        'enabled state commits once while settings remain references and tables empty'
    );
    $repeat = red_addon_payment_adapter_enable_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_wompi_c3c1_assert(
        $repeat['status'] === 'database_payment_adapter_evidence_invalid'
            && red_wompi_c3b_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND EventName='addon.enable.completed'"
            ) === '1',
        'enabled Wompi cannot consume the confirmed transition again'
    );

    echo 'Wompi payment-adapter C3C1 self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
}

?>
