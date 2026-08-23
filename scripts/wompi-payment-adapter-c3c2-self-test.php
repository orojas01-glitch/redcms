<?php
/**
 * Exact Wompi C3C2 two-client enable/disable isolation rehearsal.
 */

define('RED_WOMPI_C3C1_FIXTURE_ONLY', true);
require_once __DIR__ . '/wompi-payment-adapter-c3c1-self-test.php';
require_once $projectRoot . '/includes/addon_disable_helpers.php';

$clientBDatabase = getenv('RED_WOMPI_C3C2_CLIENT_B_DATABASE');
if (!is_string($clientBDatabase)
    || preg_match(
        '/\Aredcms_wompi_c3c2_b_[A-Za-z0-9_]+\z/',
        $clientBDatabase
    ) !== 1
    || $clientBDatabase === DBNAME
) {
    fwrite(STDERR, "Wompi C3C2 client B database is invalid.\n");
    exit(65);
}
if (preg_match(
    '/\Aredcms_payment_adapter_db_c2a_[A-Za-z0-9_]+\z/',
    (string) DBNAME
) !== 1) {
    fwrite(STDERR, "Wompi C3C2 client A database is invalid.\n");
    exit(65);
}

$assertions = 0;
$clientB = new connection(DBHOST, DBUSER, DBPASS, $clientBDatabase);
$connectionB = $clientB->connection;

function red_wompi_c3c2_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c3c2_create_owner(
    $connection,
    $actorId,
    $suffix
) {
    if (!is_string($suffix)
        || preg_match('/\A[ab]\z/D', $suffix) !== 1
    ) {
        return false;
    }
    $password = password_hash(
        'WompiC3C2-Disposable-' . strtoupper($suffix) . '-2026!',
        PASSWORD_DEFAULT
    );
    $username = 'codex_wompi_c3c2_' . $suffix;
    $alias = 'WompiC3C2' . strtoupper($suffix);
    $email = 'wompi-c3c2-' . $suffix . '@example.test';
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, ?, ?, 'Admin', ?, 'webmaster', '', '', ?,
                   'N', 'to', 'N', 'to')"
    );
    if (!$statement) {
        return false;
    }
    mysqli_stmt_bind_param(
        $statement,
        'issss',
        $actorId,
        $username,
        $password,
        $alias,
        $email
    );
    $inserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    if (!$inserted) {
        return false;
    }
    if (!mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    )) {
        return false;
    }
    foreach (['addons.install', 'addons.enable', 'addons.disable']
        as $capability
    ) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Admin_Capabilities
             (AdminRecordID, Capability, GrantedByAdminRecordID)
             VALUES (?, ?, ?)'
        );
        if (!$statement) {
            return false;
        }
        mysqli_stmt_bind_param(
            $statement,
            'isi',
            $actorId,
            $capability,
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

function red_wompi_c3c2_prepare_client(
    $connection,
    $label,
    $actorId,
    array $catalog,
    array $storePackage,
    array $wompiPackage,
    $fixtureProject
) {
    $result = [
        'valid' => false,
        'label' => $label,
        'database' => '',
        'plan' => [],
        'declarations' => [],
        'publicValue' => '',
        'secretReferences' => [],
        'errors' => [],
    ];
    if (!in_array($label, ['a', 'b'], true)
        || !red_wompi_c3c2_create_owner($connection, $actorId, $label)
        || !red_wompi_c3b_record_enabled_store(
            $connection,
            $storePackage,
            $actorId
        )
    ) {
        $result['errors'][] = 'client_baseline_failed';
        return $result;
    }
    $installPlan = red_addon_install_plan(
        $connection,
        $wompiPackage,
        $actorId,
        false,
        $catalog
    );
    if (empty($installPlan['valid'])) {
        $result['errors'][] = 'install_plan_failed';
        return $result;
    }
    $installed = red_addon_install_package(
        $connection,
        'redcms.store-lite-wompi',
        $fixtureProject,
        $actorId,
        $installPlan['planSha256']
    );
    if (($installed['status'] ?? '') !== 'installed_disabled') {
        $result['errors'][] = 'install_failed';
        return $result;
    }
    $result['publicValue'] = 'sandbox-public-reference-c3c2-' . $label;
    $result['secretReferences'] = [
        'wompi.private-key' =>
            'config:c3c2-' . $label . '-wompi-private',
        'wompi.integrity-key' =>
            'config:c3c2-' . $label . '-wompi-integrity',
        'wompi.event-secret' =>
            'config:c3c2-' . $label . '-wompi-event',
    ];
    if (!red_wompi_c3c1_store_settings(
        $connection,
        'redcms.store-lite-wompi',
        $actorId,
        $result['publicValue'],
        $result['secretReferences']
    )) {
        $result['errors'][] = 'settings_failed';
        return $result;
    }
    $result['declarations'] = red_addon_secret_reference_declarations(
        array_values($result['secretReferences']),
        ''
    );
    $result['plan'] = red_addon_payment_adapter_enablement_plan(
        $connection,
        $wompiPackage,
        $actorId,
        $catalog,
        $result['declarations']
    );
    if (!red_addon_payment_adapter_enablement_plan_is_valid(
        $result['plan']
    )) {
        $result['errors'][] = 'enable_plan_failed';
        return $result;
    }
    $enabled = red_addon_payment_adapter_enable_package(
        $connection,
        'redcms.store-lite-wompi',
        $fixtureProject,
        $actorId,
        $result['plan']['planSha256'],
        $result['declarations']
    );
    if (($enabled['status'] ?? '') !== 'enabled') {
        $result['errors'][] = 'enable_failed';
        return $result;
    }
    $result['database'] = red_addon_install_database_name($connection);
    $result['valid'] = true;
    return $result;
}

function red_wompi_c3c2_state_fingerprint($connection)
{
    $queries = [
        "SELECT PackageID, PackageVersion, ManifestSHA256, InventorySHA256,
                LifecycleState, InstalledByAdminRecordID,
                UpdatedByAdminRecordID
         FROM RED_Addon_Installations
         WHERE PackageID IN ('redcms.store-lite','redcms.store-lite-wompi')
         ORDER BY PackageID",
        "SELECT PackageID, MigrationID, MigrationPath, Checksum
         FROM RED_Addon_Migrations
         WHERE PackageID IN ('redcms.store-lite','redcms.store-lite-wompi')
         ORDER BY PackageID, MigrationID",
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
        $queryResult = mysqli_query($connection, $query);
        if (!$queryResult) {
            throw new RuntimeException('Could not fingerprint C3C2 client.');
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($queryResult)) {
            $rows[] = $row;
        }
        mysqli_free_result($queryResult);
        $material[] = $rows;
    }
    return hash(
        'sha256',
        json_encode($material, JSON_UNESCAPED_SLASHES)
    );
}

function red_wompi_c3c2_load_order($connection, array $catalog)
{
    $enabledIds = [];
    foreach (red_addon_registry_installations($connection)
        as $packageId => $installation
    ) {
        if (($installation['LifecycleState'] ?? '') === 'enabled') {
            $enabledIds[] = $packageId;
        }
    }
    $errors = [];
    $order = red_addon_runtime_load_order($catalog, $enabledIds, $errors);
    if (!is_array($order)
        || $errors !== []
        || red_addon_runtime_namespace_errors($catalog, $enabledIds) !== []
    ) {
        return null;
    }
    return $order;
}

try {
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $wompiPackage = $catalog['packages'][$wompiPackageId] ?? [];
    red_wompi_c3c2_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($wompiPackage['valid'])
            && ($storePackage['manifest']['version'] ?? null) === '0.1.35'
            && ($wompiPackage['manifest']['version'] ?? null) === '0.1.3',
        'both clients share only exact reviewed package code and identities'
    );

    $clientAState = red_wompi_c3c2_prepare_client(
        $connection,
        'a',
        $actorId,
        $catalog,
        $storePackage,
        $wompiPackage,
        $fixtureProject
    );
    $clientBState = red_wompi_c3c2_prepare_client(
        $connectionB,
        'b',
        $actorId,
        $catalog,
        $storePackage,
        $wompiPackage,
        $fixtureProject
    );
    red_wompi_c3c2_assert(
        !empty($clientAState['valid'])
            && !empty($clientBState['valid'])
            && $clientAState['database'] === DBNAME
            && $clientBState['database'] === $clientBDatabase,
        'each client independently installs, configures, and enables Wompi'
    );
    red_wompi_c3c2_assert(
        !hash_equals(
            $clientAState['plan']['databaseSha256'],
            $clientBState['plan']['databaseSha256']
        ),
        'client database identity hashes differ'
    );
    red_wompi_c3c2_assert(
        !hash_equals(
            $clientAState['plan']['settingsStateSha256'],
            $clientBState['plan']['settingsStateSha256']
        ),
        'client setting-state hashes differ'
    );
    red_wompi_c3c2_assert(
        !hash_equals(
            $clientAState['plan']['secretAvailabilitySha256'],
            $clientBState['plan']['secretAvailabilitySha256']
        ),
        'client secret-availability hashes differ'
    );
    red_wompi_c3c2_assert(
        !hash_equals(
            $clientAState['plan']['planSha256'],
            $clientBState['plan']['planSha256']
        ),
        'client enablement plan hashes differ'
    );
    red_wompi_c3c2_assert(
        !hash_equals(
            $clientAState['plan']['registrationSha256'],
            $clientBState['plan']['registrationSha256']
        ),
        'database-bound registration hashes differ across clients'
    );
    red_wompi_c3c2_assert(
        !hash_equals(
            $clientAState['plan']['ingressContractSha256'],
            $clientBState['plan']['ingressContractSha256']
        ),
        'database-bound ingress contract hashes differ across clients'
    );
    red_wompi_c3c2_assert(
        hash_equals(
            $clientAState['plan']['contractSha256'],
            $clientBState['plan']['contractSha256']
        )
            && hash_equals(
                $clientAState['plan']['manifestSha256'],
                $clientBState['plan']['manifestSha256']
            )
            && hash_equals(
                $clientAState['plan']['inventorySha256'],
                $clientBState['plan']['inventorySha256']
            ),
        'immutable package contract, manifest, and inventory hashes match'
    );
    foreach ([
        'client A' => $connection,
        'client B' => $connectionB,
    ] as $label => $clientConnection) {
        red_wompi_c3c2_assert(
            red_wompi_c3b_scalar(
                $clientConnection,
                "SELECT CONCAT_WS(':',
                    (SELECT LifecycleState FROM RED_Addon_Installations
                     WHERE PackageID='redcms.store-lite-wompi'),
                    (SELECT COUNT(*) FROM RED_Addon_Settings
                     WHERE PackageID='redcms.store-lite-wompi'),
                    (SELECT COUNT(*) FROM RED_Addon_Migrations
                     WHERE PackageID='redcms.store-lite-wompi'),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME IN (
                         'RED_Addon_StoreLite_Wompi_Payment_Attempts',
                         'RED_Addon_StoreLite_Wompi_Event_Receipts')),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Payment_Attempts),
                    (SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Event_Receipts))"
            ) === 'enabled:4:2:2:0:0',
            $label . ' has exact enabled state and empty isolated storage'
        );
    }
    red_wompi_c3c2_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Settings
             WHERE PackageID='redcms.store-lite-wompi'
               AND (ValueJSON LIKE '%c3c2-b%'
                    OR SecretReference LIKE '%c3c2-b%')"
        ) === '0'
            && red_wompi_c3b_scalar(
                $connectionB,
                "SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND (ValueJSON LIKE '%c3c2-a%'
                        OR SecretReference LIKE '%c3c2-a%')"
            ) === '0',
        'neither client database contains the other client setting or reference'
    );

    $orderA = red_wompi_c3c2_load_order($connection, $catalog);
    $orderB = red_wompi_c3c2_load_order($connectionB, $catalog);
    red_wompi_c3c2_assert(
        $orderA === ['redcms.store-lite', 'redcms.store-lite-wompi']
            && $orderB === $orderA,
        'both enabled registries have the same declarative dependency order'
    );

    $clientASecond = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
    $connectionASecond = $clientASecond->connection;
    $lockedA = red_addon_lifecycle_lock($connection, 0);
    $lockedB = red_addon_lifecycle_lock($connectionB, 0);
    $secondALocked = red_addon_lifecycle_lock($connectionASecond, 0);
    red_wompi_c3c2_assert(
        $lockedA && $lockedB && !$secondALocked,
        'lifecycle locks serialize within one client but not across clients'
    );
    if ($secondALocked) {
        red_addon_lifecycle_unlock($connectionASecond);
    }
    red_addon_lifecycle_unlock($connection);
    red_addon_lifecycle_unlock($connectionB);

    $disablePlanA = red_addon_disable_transition_plan(
        $connection,
        $wompiPackage,
        $actorId,
        $catalog
    );
    red_wompi_c3c2_assert(
        !empty($disablePlanA['valid'])
            && !empty($disablePlanA['transitionReady'])
            && $disablePlanA['currentState'] === 'enabled'
            && $disablePlanA['targetState'] === 'installed_disabled'
            && $disablePlanA['enabledDependents'] === [],
        'client A receives one exact non-executing Wompi disable plan'
    );
    $beforeRollbackA = red_wompi_c3c2_state_fingerprint($connection);
    $beforeRollbackB = red_wompi_c3c2_state_fingerprint($connectionB);
    $rollbackA = red_addon_disable_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $disablePlanA['planSha256'],
        null,
        static function (): void {
            throw new RuntimeException('forced_c3c2_disable_failure');
        }
    );
    red_wompi_c3c2_assert(
        $rollbackA['status'] === 'disable_transaction_failed'
            && hash_equals(
                $beforeRollbackA,
                red_wompi_c3c2_state_fingerprint($connection)
            )
            && hash_equals(
                $beforeRollbackB,
                red_wompi_c3c2_state_fingerprint($connectionB)
            ),
        'forced client A disable rolls back A and leaves B byte-for-byte unchanged'
    );

    $disabledA = red_addon_disable_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $disablePlanA['planSha256']
    );
    red_wompi_c3c2_assert(
        $disabledA['status'] === 'installed_disabled'
            && hash_equals(
                $beforeRollbackB,
                red_wompi_c3c2_state_fingerprint($connectionB)
            ),
        'client A disables atomically while every client B fact remains unchanged'
    );
    red_wompi_c3c2_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.store-lite-wompi'),
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='redcms.store-lite-wompi'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND EventName='addon.disable.completed'
                   AND Result='succeeded'),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Payment_Attempts),
                (SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Event_Receipts))"
        ) === 'installed_disabled:4:1:0:0'
            && red_wompi_c3b_scalar(
                $connectionB,
                "SELECT CONCAT_WS(':', LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Settings
                     WHERE PackageID='redcms.store-lite-wompi'))
                 FROM RED_Addon_Installations
                 WHERE PackageID='redcms.store-lite-wompi'"
            ) === 'enabled:4',
        'disable retains client A settings/tables while client B stays enabled'
    );
    red_wompi_c3c2_assert(
        red_wompi_c3c2_load_order($connection, $catalog)
            === ['redcms.store-lite']
            && red_wompi_c3c2_load_order($connectionB, $catalog)
                === ['redcms.store-lite', 'redcms.store-lite-wompi'],
        'later declarative runtime order excludes disabled A but retains enabled B'
    );
    $repeatDisableA = red_addon_disable_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $disablePlanA['planSha256']
    );
    red_wompi_c3c2_assert(
        $repeatDisableA['status'] === 'package_not_enabled_current'
            && hash_equals(
                $beforeRollbackB,
                red_wompi_c3c2_state_fingerprint($connectionB)
            ),
        'repeat client A disable refuses without affecting client B'
    );

    $encodedEvidence = json_encode([
        'clientA' => [
            'databaseSha256' => $clientAState['plan']['databaseSha256'],
            'planSha256' => $clientAState['plan']['planSha256'],
            'settingsStateSha256' =>
                $clientAState['plan']['settingsStateSha256'],
        ],
        'clientB' => [
            'databaseSha256' => $clientBState['plan']['databaseSha256'],
            'planSha256' => $clientBState['plan']['planSha256'],
            'settingsStateSha256' =>
                $clientBState['plan']['settingsStateSha256'],
        ],
    ], JSON_UNESCAPED_SLASHES);
    red_wompi_c3c2_assert(
        is_string($encodedEvidence)
            && !str_contains($encodedEvidence, 'sandbox-public-reference')
            && !str_contains($encodedEvidence, 'config:c3c2-'),
        'bounded two-client evidence contains hashes but no setting or reference'
    );

    echo 'Wompi payment-adapter C3C2 two-client self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
