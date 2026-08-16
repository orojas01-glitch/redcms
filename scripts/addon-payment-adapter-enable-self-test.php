<?php
/**
 * Disposable database checks for P3A-5 atomic adapter enablement.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('RED_ADDON_PAYMENT_ADAPTER_DATABASE_FIXTURE_ONLY', true);
require_once __DIR__ .
    '/addon-payment-adapter-database-preflight-self-test.php';
require_once $projectRoot .
    '/includes/addon_payment_adapter_enable_helpers.php';

$assertions = 0;

function red_addon_payment_adapter_enable_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_payment_adapter_enable_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) ($row[0] ?? '') : '';
}

function red_addon_payment_adapter_enable_test_cleanup(
    $connection,
    array $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
) {
    foreach ($packageIds as $packageId) {
        $statement = mysqli_prepare(
            $connection,
            'DELETE FROM RED_Addon_Settings WHERE PackageID=?'
        );
        if ($statement) {
            mysqli_stmt_bind_param($statement, 's', $packageId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }
    }
    red_addon_payment_adapter_db_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
}

function red_addon_payment_adapter_enable_test_fingerprint(
    $connection,
    array $packageIds,
    $actorId,
    $tableName
) {
    $base = red_addon_payment_adapter_db_test_fingerprint(
        $connection,
        $packageIds,
        $actorId,
        $tableName
    );
    $quoted = array_map(
        static fn($packageId) => "'" .
            mysqli_real_escape_string($connection, $packageId) . "'",
        $packageIds
    );
    $material = [$base];
    foreach ([
        'SELECT PackageID, SettingKey, ValueType, ValueJSON,
                SecretReference, UpdatedByAdminRecordID
         FROM RED_Addon_Settings
         WHERE PackageID IN (' . implode(',', $quoted) . ')
         ORDER BY PackageID, SettingKey',
        'SELECT EventName, PackageID, PackageVersion,
                ActorAdminRecordID, Result, DetailCode
         FROM RED_Addon_Activity_Log
         WHERE PackageID IN (' . implode(',', $quoted) . ')
         ORDER BY RecordID',
    ] as $sql) {
        $query = mysqli_query($connection, $sql);
        $rows = [];
        while ($query && ($row = mysqli_fetch_assoc($query))) {
            $rows[] = $row;
        }
        if ($query) {
            mysqli_free_result($query);
        }
        $material[] = $rows;
    }
    return hash('sha256', json_encode($material));
}

function red_addon_payment_adapter_enable_test_store_settings(
    $connection,
    $packageId,
    $actorId,
    $apiReference,
    $webhookReference
) {
    $rows = [[
        'checkout.return-origin',
        'url',
        json_encode('https://checkout.example.test'),
        null,
    ], [
        'stripe.secret-key',
        'secret-reference',
        null,
        $apiReference,
    ], [
        'stripe.webhook-secret',
        'secret-reference',
        null,
        $webhookReference,
    ]];
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

$packageIds = [$adapterPackageId, $storePackageId];
red_addon_payment_adapter_enable_test_cleanup(
    $connection,
    $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
);

try {
    $password = password_hash('PaymentAdapterEnable-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_payment_adapter_enable', ?, 'Admin',
                   'PayAdEnable', 'webmaster', '', '',
                   'payment-adapter-enable@example.test',
                   'N', 'to', 'N', 'to')"
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
    mysqli_stmt_bind_param(
        $statement,
        'isi',
        $actorId,
        $capability,
        $actorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $storePackageId,
        'content-package',
        '0.1.31',
        $executionMarker
    );
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $adapterPackageId,
        'adapter',
        '0.1.0',
        $executionMarker,
        $tableName
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? [];
    red_addon_payment_adapter_enable_test_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($adapterPackage['valid'])
            && !file_exists($executionMarker),
        'trusted dependency and adapter discovery remains non-executing'
    );
    red_addon_payment_adapter_enable_test_assert(
        red_addon_payment_adapter_db_test_record_installation(
            $connection,
            $storePackage,
            $actorId,
            'enabled'
        ) && red_addon_payment_adapter_db_test_record_installation(
            $connection,
            $adapterPackage,
            $actorId,
            'installed_disabled'
        ),
        'fixture records enabled Store Lite and disabled adapter in one database'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$tableName` (
            RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    red_addon_payment_adapter_enable_test_assert(
        mysqli_errno($connection) === 0,
        'fixture creates the adapter-owned InnoDB table'
    );

    $apiReference = 'config:test-payment-adapter-api';
    $webhookReference = 'config:test-payment-adapter-webhook';
    red_addon_payment_adapter_enable_test_assert(
        red_addon_payment_adapter_enable_test_store_settings(
            $connection,
            $adapterPackageId,
            $actorId,
            $apiReference,
            $webhookReference
        ),
        'fixture stores exact ordinary and opaque secret-reference settings'
    );
    $declarations = red_addon_secret_reference_declarations(
        [$apiReference, $webhookReference],
        ''
    );
    red_addon_payment_adapter_enable_test_assert(
        !empty($declarations['valid']),
        'fixture declares both opaque references without secret values'
    );

    $before = red_addon_payment_adapter_enable_test_fingerprint(
        $connection,
        $packageIds,
        $actorId,
        $tableName
    );
    $plan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_addon_payment_adapter_enable_test_assert(
        red_addon_payment_adapter_enablement_plan_is_valid($plan)
            && $plan['enableReady']
            && $plan['activationSupported']
            && !$plan['stateMutation']
            && !$plan['runtimePublication']
            && !$plan['handlerInvocation']
            && !$plan['secretResolution']
            && !$plan['networkAccess']
            && !$plan['routeExposure']
            && $plan['packageExecutionAttempted']
            && $plan['registrarExecutionCompleted'],
        'complete P3A evidence yields one non-mutating enable-ready plan'
    );
    red_addon_payment_adapter_enable_test_assert(
        $plan['settingCount'] === 3
            && $plan['configuredSettingCount'] === 3
            && $plan['secretSettingCount'] === 2
            && $plan['availableSecretCount'] === 2
            && $plan['blockers'] === []
            && red_addon_valid_sha256($plan['registrationSha256'])
            && red_addon_valid_sha256($plan['ingressContractSha256'])
            && red_addon_valid_sha256($plan['settingsStateSha256'])
            && red_addon_valid_sha256(
                $plan['secretAvailabilitySha256']
            ),
        'plan exposes only exact counts and value-free hashes'
    );
    $encodedPlan = json_encode(
        $plan,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    red_addon_payment_adapter_enable_test_assert(
        is_string($encodedPlan)
            && !str_contains($encodedPlan, $apiReference)
            && !str_contains($encodedPlan, $webhookReference)
            && !file_exists($executionMarker . '-adapter-handler')
            && !file_exists($executionMarker . '-route-handler'),
        'plan contains no references and invokes neither registered handler'
    );
    red_addon_payment_adapter_enable_test_assert(
        hash_equals(
            $before,
            red_addon_payment_adapter_enable_test_fingerprint(
                $connection,
                $packageIds,
                $actorId,
                $tableName
            )
        ),
        'planning changes no lifecycle, setting, authority, table, or audit fact'
    );
    $repeatPlan = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $declarations
    );
    red_addon_payment_adapter_enable_test_assert(
        red_addon_payment_adapter_enablement_plan_is_valid($repeatPlan)
            && hash_equals($plan['planSha256'], $repeatPlan['planSha256']),
        'unchanged complete evidence is deterministic'
    );

    $partialDeclarations = red_addon_secret_reference_declarations(
        [$apiReference],
        ''
    );
    $missingSecret = red_addon_payment_adapter_enablement_plan(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog,
        $partialDeclarations
    );
    red_addon_payment_adapter_enable_test_assert(
        empty($missingSecret['valid'])
            && in_array(
                'payment_adapter_configuration_incomplete',
                $missingSecret['errors'],
                true
            ),
        'missing secret-reference availability fails without resolving a value'
    );
    $tampered = $plan;
    $tampered['availableSecretCount'] = 1;
    red_addon_payment_adapter_enable_test_assert(
        !red_addon_payment_adapter_enablement_plan_is_valid($tampered),
        'tampered enablement evidence fails its deterministic contract'
    );

    $staleHash = hash('sha256', 'stale-payment-adapter-plan');
    $stale = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $fixtureProject,
        $actorId,
        $staleHash,
        $declarations
    );
    red_addon_payment_adapter_enable_test_assert(
        $stale['status'] === 'plan_changed'
            && hash_equals(
                $before,
                red_addon_payment_adapter_enable_test_fingerprint(
                    $connection,
                    $packageIds,
                    $actorId,
                    $tableName
                )
            ),
        'stale plan fails before lifecycle or audit mutation'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Settings
         SET ValueJSON='\"https://changed.example.test\"'
         WHERE PackageID='redcms.stripe-db-fixture'
           AND SettingKey='checkout.return-origin'"
    );
    $configurationChanged = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_addon_payment_adapter_enable_test_assert(
        $configurationChanged['status'] === 'plan_changed'
            && red_addon_payment_adapter_enable_test_scalar(
                $connection,
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.stripe-db-fixture'"
            ) === 'installed_disabled',
        'changed configuration invalidates the confirmed plan'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Settings
         SET ValueJSON='\"https://checkout.example.test\"'
         WHERE PackageID='redcms.stripe-db-fixture'
           AND SettingKey='checkout.return-origin'"
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$actorId AND Capability='addons.enable'"
    );
    $revoked = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_addon_payment_adapter_enable_test_assert(
        $revoked['status'] === 'database_payment_adapter_evidence_invalid'
            && red_addon_payment_adapter_enable_test_scalar(
                $connection,
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.stripe-db-fixture'"
            ) === 'installed_disabled',
        'revoked Owner enable authority fails under the lifecycle lock'
    );
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
        "UPDATE RED_Addon_Installations SET LifecycleState='installed_disabled'
         WHERE PackageID='redcms.store-lite'"
    );
    $dependencyChanged = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_addon_payment_adapter_enable_test_assert(
        $dependencyChanged['status']
            === 'database_payment_adapter_evidence_invalid'
            && red_addon_payment_adapter_enable_test_scalar(
                $connection,
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.stripe-db-fixture'"
            ) === 'installed_disabled',
        'disabled Store Lite dependency fails under the lifecycle lock'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='redcms.store-lite'"
    );

    $auditFailure = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations,
        static fn(): bool => false
    );
    red_addon_payment_adapter_enable_test_assert(
        $auditFailure['status'] === 'enable_transaction_failed'
            && red_addon_payment_adapter_enable_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.stripe-db-fixture'))
                 FROM RED_Addon_Installations
                 WHERE PackageID='redcms.stripe-db-fixture'"
            ) === 'installed_disabled:0',
        'audit failure rolls back lifecycle state and audit together'
    );
    $injectedFailure = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations,
        null,
        static function (): void {
            throw new RuntimeException('forced_after_state_failure');
        }
    );
    red_addon_payment_adapter_enable_test_assert(
        $injectedFailure['status'] === 'enable_transaction_failed'
            && red_addon_payment_adapter_enable_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.stripe-db-fixture'))
                 FROM RED_Addon_Installations
                 WHERE PackageID='redcms.stripe-db-fixture'"
            ) === 'installed_disabled:0',
        'failure after compare-and-swap rolls back every database fact'
    );

    $enabled = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_addon_payment_adapter_enable_test_assert(
        $enabled['status'] === 'enabled'
            && $enabled['packageId'] === $adapterPackageId
            && $enabled['version'] === '0.1.0'
            && hash_equals(
                $enabled['registrationSha256'],
                $plan['registrationSha256']
            )
            && hash_equals(
                $enabled['ingressContractSha256'],
                $plan['ingressContractSha256']
            )
            && !file_exists($executionMarker . '-adapter-handler')
            && !file_exists($executionMarker . '-route-handler'),
        'exact revalidation enables the adapter without handler invocation'
    );
    red_addon_payment_adapter_enable_test_assert(
        red_addon_payment_adapter_enable_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':', LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.stripe-db-fixture'
                   AND EventName='addon.enable.completed'
                   AND Result='succeeded'
                   AND DetailCode='payment_adapter_enabled'))
             FROM RED_Addon_Installations
             WHERE PackageID='redcms.stripe-db-fixture'"
        ) === 'enabled:1',
        'enabled state and one bounded payment-adapter audit fact commit atomically'
    );
    $repeat = red_addon_payment_adapter_enable_package(
        $connection,
        $adapterPackageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        $declarations
    );
    red_addon_payment_adapter_enable_test_assert(
        $repeat['status'] === 'database_payment_adapter_evidence_invalid'
            && red_addon_payment_adapter_enable_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.stripe-db-fixture'
                   AND EventName='addon.enable.completed'"
            ) === '1',
        'an enabled adapter cannot consume the confirmed transition again'
    );

    $helperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_payment_adapter_enable_helpers.php'
    );
    red_addon_payment_adapter_enable_test_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|php:\/\/input|\bcurl_|\bfsockopen\s*\(|\bstream_socket_client\s*\(|red_addon_(?:runtime_)?secret_(?:resolve|access)|->handler\s*\(|\bheader\s*\(|\bhttp_response_code\s*\()/i',
            $helperSource
        ) !== 1,
        'runner has no request, secret-resolution, network, handler, or response path'
    );
    $cliSource = (string) file_get_contents(
        $projectRoot . '/scripts/admin-payment-adapter-enable.php'
    );
    red_addon_payment_adapter_enable_test_assert(
        str_contains($cliSource, "PHP_SAPI !== 'cli'")
            && str_contains($cliSource, '--confirm-database=')
            && str_contains($cliSource, '--confirm-package=')
            && str_contains($cliSource, '--confirm-version=')
            && str_contains($cliSource, '--confirm-plan-sha256=')
            && str_contains($cliSource, '--confirm-backup-sha256=')
            && str_contains($cliSource, '--confirm-state=')
            && str_contains($cliSource, '--apply')
            && !file_exists(
                $projectRoot . '/admin/bin/payment_adapter_enable.php'
            )
            && !file_exists(
                $projectRoot . '/bin/payment_adapter_enable.php'
            ),
        'enable command is CLI-only, backup-bound, exact, and has no web endpoint'
    );

    red_addon_payment_adapter_enable_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
    red_addon_payment_adapter_enable_test_assert(
        red_addon_payment_adapter_enable_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID IN (
                    'redcms.stripe-db-fixture','redcms.store-lite')),
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID IN (
                    'redcms.stripe-db-fixture','redcms.store-lite')),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID IN (
                    'redcms.stripe-db-fixture','redcms.store-lite')),
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Stripe_DB_Fixture_Attempts'))"
        ) === '0:0:0:0:0'
            && !file_exists($executionMarker)
            && !file_exists($executionMarker . '-adapter-handler')
            && !file_exists($executionMarker . '-route-handler'),
        'package, settings, audit, authority, table, code, and markers clean exactly'
    );
    echo 'Payment adapter P3A-5 atomic enable self-test passed: ' .
        $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_addon_payment_adapter_enable_test_cleanup(
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
