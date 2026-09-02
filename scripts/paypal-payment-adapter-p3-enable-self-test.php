<?php
/** Exact PayPal P3 non-routable ingress and atomic-enablement rehearsal. */

define('RED_PAYPAL_P2_FIXTURE_ONLY', true);
require_once __DIR__ . '/paypal-payment-adapter-p2-disposable-self-test.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_enable_helpers.php';

$assertions = 0;

function red_paypal_p3_assert($condition, $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_paypal_p3_store_settings(
    $connection,
    string $packageId,
    int $actorId,
    string $returnOrigin,
    string $webhookId,
    array $secretReferences
): bool {
    if (array_keys($secretReferences) !== [
        'paypal.client-id',
        'paypal.client-secret',
    ]) {
        return false;
    }
    $rows = [[
        'checkout.return-origin',
        'url',
        json_encode($returnOrigin),
        null,
    ], [
        'paypal.webhook-id',
        'text',
        json_encode($webhookId),
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

function red_paypal_p3_state_fingerprint($connection): string
{
    $queries = [
        "SELECT PackageID, LifecycleState, UpdatedByAdminRecordID
         FROM RED_Addon_Installations
         WHERE PackageID IN ('redcms.store-lite','redcms.store-lite-paypal')
         ORDER BY PackageID",
        "SELECT PackageID, SettingKey, ValueType, ValueJSON,
                SecretReference, UpdatedByAdminRecordID
         FROM RED_Addon_Settings
         WHERE PackageID='redcms.store-lite-paypal'
         ORDER BY SettingKey",
        "SELECT EventName, PackageID, PackageVersion, ActorAdminRecordID,
                Result, DetailCode
         FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.store-lite-paypal'
         ORDER BY RecordID",
        'SELECT COUNT(*) FROM RED_Addon_StoreLite_PayPal_Order_Attempts',
        'SELECT COUNT(*) FROM RED_Addon_StoreLite_PayPal_Event_Receipts',
    ];
    $material = [];
    foreach ($queries as $query) {
        $result = mysqli_query($connection, $query);
        if (!$result) {
            throw new RuntimeException('Could not fingerprint PayPal P3 state.');
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        $material[] = $rows;
    }
    return hash('sha256', json_encode($material, JSON_UNESCAPED_SLASHES));
}

if (!defined('RED_PAYPAL_P3_FIXTURE_ONLY')) {
try {
    $password = password_hash('PayPalP3-Disposable-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_paypal_p3', ?, 'Admin', 'PayPalP3',
                   'webmaster', '', '', 'paypal-p3@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    $inserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_paypal_p3_assert($inserted, 'disposable Owner fixture is recorded');
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
    red_paypal_p3_assert(
        red_paypal_p2_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT RoleName FROM RED_Admin_Roles
                 WHERE AdminRecordID=$actorId),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$actorId
                   AND Capability IN ('addons.install','addons.enable'))
             )"
        ) === 'owner:2',
        'persisted Owner has the exact lifecycle authority used by P3'
    );

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $paypalPackage = $catalog['packages'][$paypalPackageId] ?? [];
    red_paypal_p3_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($paypalPackage['valid'])
            && ($storePackage['manifest']['version'] ?? null) === '0.1.50'
            && ($paypalPackage['manifest']['version'] ?? null) === '0.2.0',
        'exact Store Lite and PayPal packages discover together'
    );
    red_paypal_p3_assert(
        red_paypal_p2_record_enabled_store(
            $connection,
            $storePackage,
            $actorId
        ),
        'Store Lite supplies the enabled dependency baseline'
    );
    $installPlan = red_addon_install_plan(
        $connection,
        $paypalPackage,
        $actorId,
        false,
        $catalog
    );
    $installed = red_addon_install_package(
        $connection,
        $paypalPackageId,
        $fixtureProject,
        $actorId,
        $installPlan['planSha256'] ?? ''
    );
    red_paypal_p3_assert(
        !empty($installPlan['valid'])
            && $installed['status'] === 'installed_disabled'
            && $installed['appliedMigrations'] === [
                '2026-09-01-paypal-order-attempts',
                '2026-09-01-paypal-event-receipts',
            ],
        'guarded PayPal install completes disabled with exact migrations'
    );

    $returnOrigin = 'https://demo.red-sphere.com';
    $webhookId = 'WH-TEST-PAYPAL-P3-0001';
    $secretReferences = [
        'paypal.client-id' => 'config:p3-paypal-client-id',
        'paypal.client-secret' => 'config:p3-paypal-client-secret',
    ];
    red_paypal_p3_assert(
        red_paypal_p3_store_settings(
            $connection,
            $paypalPackageId,
            $actorId,
            $returnOrigin,
            $webhookId,
            $secretReferences
        ),
        'two client-local settings and two opaque references are stored'
    );
    $declarations = red_addon_secret_reference_declarations(
        array_values($secretReferences),
        ''
    );
    red_paypal_p3_assert(
        !empty($declarations['valid'])
            && count($declarations['references']) === 2,
        'both references are declared available without secret values'
    );

    $beforePlan = red_paypal_p3_state_fingerprint($connection);
    $plan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $paypalPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_paypal_p3_assert(
        red_addon_payment_adapter_enablement_plan_is_valid($plan)
            && $plan['profileId'] === 'store_lite_paypal_adapter_v1'
            && $plan['enableReady']
            && $plan['activationSupported']
            && !$plan['stateMutation']
            && !$plan['runtimePublication']
            && !$plan['handlerInvocation']
            && !$plan['secretResolution']
            && !$plan['networkAccess']
            && !$plan['routeExposure'],
        'exact PayPal evidence yields one value-free enable-ready plan'
    );
    red_paypal_p3_assert(
        $plan['settingCount'] === 4
            && $plan['configuredSettingCount'] === 4
            && $plan['secretSettingCount'] === 2
            && $plan['availableSecretCount'] === 2
            && $plan['blockers'] === []
            && red_addon_valid_sha256($plan['ingressContractSha256'])
            && red_addon_valid_sha256($plan['settingsStateSha256'])
            && red_addon_valid_sha256($plan['secretAvailabilitySha256']),
        'plan signs exact setting, secret, and PayPal ingress evidence'
    );
    $encodedPlan = json_encode(
        $plan,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    red_paypal_p3_assert(
        is_string($encodedPlan)
            && !str_contains($encodedPlan, $returnOrigin)
            && !str_contains($encodedPlan, $webhookId)
            && !str_contains($encodedPlan, 'config:p3-paypal-'),
        'enable plan contains no setting value or opaque reference identifier'
    );
    red_paypal_p3_assert(
        hash_equals($beforePlan, red_paypal_p3_state_fingerprint($connection)),
        'planning changes no lifecycle, setting, audit, or payment fact'
    );

    $partialDeclarations = red_addon_secret_reference_declarations(
        [reset($secretReferences)],
        ''
    );
    $missingSecret = red_addon_payment_adapter_enablement_plan(
        $connection,
        $paypalPackage,
        $actorId,
        $catalog,
        $partialDeclarations
    );
    red_paypal_p3_assert(
        empty($missingSecret['valid'])
            && in_array(
                'payment_adapter_configuration_incomplete',
                $missingSecret['errors'],
                true
            ),
        'one missing PayPal secret declaration fails without resolution'
    );
    $stale = red_addon_payment_adapter_enable_package(
        $connection,
        $paypalPackageId,
        $fixtureProject,
        $actorId,
        hash('sha256', 'stale-paypal-p3-plan'),
        $declarations
    );
    red_paypal_p3_assert(
        $stale['status'] === 'plan_changed'
            && red_paypal_p2_scalar(
                $connection,
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.store-lite-paypal'"
            ) === 'installed_disabled',
        'stale PayPal plan fails before lifecycle mutation'
    );
    $forcedFailure = red_addon_payment_adapter_enable_package(
        $connection,
        $paypalPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations,
        null,
        static function (): void {
            throw new RuntimeException('forced_paypal_p3_after_state_failure');
        }
    );
    red_paypal_p3_assert(
        $forcedFailure['status'] === 'enable_transaction_failed'
            && red_paypal_p2_scalar(
                $connection,
                "SELECT CONCAT_WS(':', LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.store-lite-paypal'
                       AND EventName='addon.enable.completed'))
                 FROM RED_Addon_Installations
                 WHERE PackageID='redcms.store-lite-paypal'"
            ) === 'installed_disabled:0',
        'failure after compare-and-swap rolls back state and audit atomically'
    );

    $enabled = red_addon_payment_adapter_enable_package(
        $connection,
        $paypalPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_paypal_p3_assert(
        $enabled['status'] === 'enabled'
            && $enabled['packageId'] === $paypalPackageId
            && $enabled['version'] === '0.2.0'
            && hash_equals(
                $enabled['registrationSha256'],
                $plan['registrationSha256']
            )
            && hash_equals(
                $enabled['ingressContractSha256'],
                $plan['ingressContractSha256']
            ),
        'exact locked revalidation atomically enables PayPal'
    );
    red_paypal_p3_assert(
        red_paypal_p2_scalar(
            $connection,
            "SELECT CONCAT_WS(':', LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-paypal'
                   AND EventName='addon.enable.completed'
                   AND Result='succeeded'
                   AND DetailCode='payment_adapter_enabled'),
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='redcms.store-lite-paypal'),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_PayPal_Order_Attempts),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_PayPal_Event_Receipts))
             FROM RED_Addon_Installations
             WHERE PackageID='redcms.store-lite-paypal'"
        ) === 'enabled:1:4:0:0',
        'enabled state commits once while settings remain and tables stay empty'
    );
    $repeat = red_addon_payment_adapter_enable_package(
        $connection,
        $paypalPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_paypal_p3_assert(
        $repeat['status'] === 'database_payment_adapter_evidence_invalid'
            && red_paypal_p2_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-paypal'
                   AND EventName='addon.enable.completed'"
            ) === '1',
        'enabled PayPal cannot consume the confirmed transition twice'
    );

    echo 'PayPal P3 atomic-enablement self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
}

?>
