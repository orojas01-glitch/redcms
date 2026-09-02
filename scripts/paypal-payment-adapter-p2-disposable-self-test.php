<?php
/** Exact PayPal install-disabled, database, and registrar rehearsal. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot
    . '/includes/addon_payment_adapter_registrar_helpers.php';

if (!defined('RED_PAYPAL_P2_FIXTURE_ONLY')
    && !preg_match(
        '/\Aredcms_paypal_p2_[A-Za-z0-9_]+\z/',
        (string) DBNAME
    )
) {
    fwrite(STDERR, "PayPal P2 refused non-disposable database.\n");
    exit(65);
}

$fixtureProject = getenv('RED_PAYPAL_P2_PROJECT_ROOT');
if (!is_string($fixtureProject)
    || realpath($fixtureProject) !== realpath($projectRoot)
) {
    fwrite(STDERR, "PayPal P2 staged project root is invalid.\n");
    exit(65);
}

$assertions = 0;
$actorId = 2147000993;
$storePackageId = 'redcms.store-lite';
$paypalPackageId = 'redcms.store-lite-paypal';
$attemptsTable = 'RED_Addon_StoreLite_PayPal_Order_Attempts';
$eventsTable = 'RED_Addon_StoreLite_PayPal_Event_Receipts';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_paypal_p2_assert($condition, $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_paypal_p2_scalar($connection, string $sql): string
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_paypal_p2_database_fingerprint($connection): string
{
    $queries = [
        "SELECT PackageID, PackageVersion, PackageType, ManifestSHA256,
                InventorySHA256, LifecycleState, InstalledByAdminRecordID,
                UpdatedByAdminRecordID
         FROM RED_Addon_Installations
         WHERE PackageID IN ('redcms.store-lite','redcms.store-lite-paypal')
         ORDER BY PackageID",
        "SELECT PackageID, MigrationID, MigrationPath, Checksum,
                AppliedByAdminRecordID, ExecutionMs
         FROM RED_Addon_Migrations
         WHERE PackageID IN ('redcms.store-lite','redcms.store-lite-paypal')
         ORDER BY PackageID, MigrationID",
        "SELECT EventName, PackageID, PackageVersion, ActorAdminRecordID,
                Result, DetailCode
         FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.store-lite-paypal'
         ORDER BY RecordID",
        "SELECT TABLE_NAME, ENGINE
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN (
             'RED_Addon_StoreLite_PayPal_Order_Attempts',
             'RED_Addon_StoreLite_PayPal_Event_Receipts'
           )
         ORDER BY TABLE_NAME",
        "SELECT PackageID, SettingKey
         FROM RED_Addon_Settings
         WHERE PackageID='redcms.store-lite-paypal'
         ORDER BY SettingKey",
        'SELECT COUNT(*) FROM RED_Addon_StoreLite_PayPal_Order_Attempts',
        'SELECT COUNT(*) FROM RED_Addon_StoreLite_PayPal_Event_Receipts',
    ];
    $material = [];
    foreach ($queries as $query) {
        $result = mysqli_query($connection, $query);
        if (!$result) {
            throw new RuntimeException('Could not fingerprint PayPal database.');
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

function red_paypal_p2_record_enabled_store(
    $connection,
    array $package,
    int $actorId
): bool {
    $snapshot = red_addon_registry_snapshot($package);
    if (!is_array($snapshot)
        || !red_addon_install_insert_installation(
            $connection,
            $snapshot,
            $actorId
        )
    ) {
        return false;
    }
    foreach ($snapshot['migrations'] as $migration) {
        if (!red_addon_install_record_migration(
            $connection,
            $snapshot['id'],
            $migration,
            $actorId,
            0
        )) {
            return false;
        }
    }
    return red_addon_install_update_state(
        $connection,
        $snapshot['id'],
        'enabled',
        $actorId
    );
}

if (!defined('RED_PAYPAL_P2_FIXTURE_ONLY')) {
try {
    $password = password_hash('PayPalP2-Disposable-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_paypal_p2', ?, 'Admin', 'PayPalP2',
                   'webmaster', '', '', 'paypal-p2@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    $inserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_paypal_p2_assert($inserted, 'disposable Owner fixture is recorded');
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
    red_paypal_p2_assert(
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
        'persisted Owner has only the lifecycle capabilities used here'
    );

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $paypalPackage = $catalog['packages'][$paypalPackageId] ?? [];
    red_paypal_p2_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($paypalPackage['valid'])
            && ($storePackage['manifest']['version'] ?? null) === '0.1.50'
            && ($paypalPackage['manifest']['version'] ?? null) === '0.1.0'
            && count(
                $paypalPackage['manifest']['integrity']['files'] ?? []
            ) === 10,
        'exact Store Lite and ten-file PayPal package discover together'
    );
    red_paypal_p2_assert(
        !class_exists(RED_CMS_Store_Lite_PayPal_Offline_Adapter::class, false),
        'discovery executes no PayPal package PHP'
    );

    red_paypal_p2_assert(
        red_paypal_p2_record_enabled_store(
            $connection,
            $storePackage,
            $actorId
        ),
        'Store Lite identity is recorded as the enabled dependency baseline'
    );
    $storeMigrationCount = count($storePackage['manifest']['migrations']);
    red_paypal_p2_assert(
        red_paypal_p2_scalar(
            $connection,
            "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='redcms.store-lite'))
             FROM RED_Addon_Installations
             WHERE PackageID='redcms.store-lite'"
        ) === '0.1.50:enabled:' . $storeMigrationCount,
        'Store Lite baseline is current without replaying package PHP'
    );

    $installPlan = red_addon_install_plan(
        $connection,
        $paypalPackage,
        $actorId,
        false,
        $catalog
    );
    red_paypal_p2_assert(
        !empty($installPlan['valid'])
            && !$installPlan['resume']
            && array_column(
                $installPlan['requiredDependencies'],
                'id'
            ) === [$storePackageId]
            && $installPlan['pendingMigrations'] === [
                '2026-09-01-paypal-order-attempts',
                '2026-09-01-paypal-event-receipts',
            ]
            && red_addon_valid_sha256($installPlan['planSha256']),
        'Owner receives the exact two-migration install-disabled plan'
    );
    $installed = red_addon_install_package(
        $connection,
        $paypalPackageId,
        $fixtureProject,
        $actorId,
        $installPlan['planSha256']
    );
    red_paypal_p2_assert(
        $installed['status'] === 'installed_disabled'
            && $installed['version'] === '0.1.0'
            && $installed['appliedMigrations'] === [
                '2026-09-01-paypal-order-attempts',
                '2026-09-01-paypal-event-receipts',
            ]
            && !class_exists(
                RED_CMS_Store_Lite_PayPal_Offline_Adapter::class,
                false
            ),
        'guarded install applies both migrations and leaves package PHP unloaded'
    );
    red_paypal_p2_assert(
        red_paypal_p2_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.store-lite-paypal'),
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='redcms.store-lite-paypal'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME IN ('$attemptsTable','$eventsTable')),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME IN ('$attemptsTable','$eventsTable')
                   AND ENGINE='InnoDB'),
                (SELECT COUNT(*) FROM `$attemptsTable`),
                (SELECT COUNT(*) FROM `$eventsTable`),
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='redcms.store-lite-paypal'))"
        ) === 'installed_disabled:2:2:2:0:0:0',
        'PayPal is disabled with two empty InnoDB tables and no settings'
    );

    $databasePlan = red_addon_payment_adapter_database_preflight(
        $connection,
        $paypalPackage,
        $actorId,
        $catalog
    );
    red_paypal_p2_assert(
        red_addon_payment_adapter_database_preflight_is_valid($databasePlan)
            && $databasePlan['databaseEvidenceReady']
            && !$databasePlan['enableReady']
            && !$databasePlan['activationSupported']
            && !$databasePlan['stateMutation']
            && !$databasePlan['runtimeLoad']
            && !$databasePlan['packageExecution']
            && !$databasePlan['secretResolution']
            && !$databasePlan['networkAccess']
            && !$databasePlan['routeExposure'],
        'database evidence is ready while runtime effects remain blocked'
    );
    red_paypal_p2_assert(
        $databasePlan['dependencyCount'] === 1
            && $databasePlan['migrationCount'] === 2
            && $databasePlan['tableCount'] === 2
            && $databasePlan['innoDbTableCount'] === 2
            && array_column($databasePlan['blockers'], 'code') === [
                'atomic_payment_adapter_enablement_required',
                'registrar_validation_required',
                'server_event_ingress_required',
            ],
        'database plan clears exact dependency, migration, and table gates'
    );

    $beforeRegistrar = red_paypal_p2_database_fingerprint($connection);
    $registrarPlan = red_addon_payment_adapter_validate_registrar(
        $paypalPackage,
        $databasePlan
    );
    red_paypal_p2_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid($registrarPlan)
            && $registrarPlan['profileId']
                === 'store_lite_paypal_adapter_v1'
            && $registrarPlan['registrarEvidenceReady']
            && $registrarPlan['packageExecutionAttempted']
            && $registrarPlan['registrarExecutionCompleted']
            && !$registrarPlan['handlerInvocation']
            && !$registrarPlan['runtimePublication']
            && !$registrarPlan['networkAccess']
            && !$registrarPlan['routeExposure'],
        'registration-only execution validates PayPal without handlers'
    );
    red_paypal_p2_assert(
        $registrarPlan['adapter']
            === 'redcms.store-lite-paypal/checkout'
            && $registrarPlan['serverEventRoute']
                === 'redcms.store-lite-paypal/provider-events'
            && $registrarPlan['registrationCount'] === 2
            && array_column($registrarPlan['blockers'], 'code') === [
                'atomic_payment_adapter_enablement_required',
                'server_event_ingress_required',
            ],
        'only exact PayPal registration evidence is retained'
    );
    red_paypal_p2_assert(
        hash_equals(
            $beforeRegistrar,
            red_paypal_p2_database_fingerprint($connection)
        ),
        'registrar validation changes no database, setting, or payment fact'
    );
    $repeatInstall = red_addon_install_plan(
        $connection,
        $paypalPackage,
        $actorId,
        false,
        $catalog
    );
    red_paypal_p2_assert(
        empty($repeatInstall['valid'])
            && $repeatInstall['errors'] === ['package_already_recorded'],
        'installed-disabled PayPal cannot be installed twice'
    );
    red_paypal_p2_assert(
        red_paypal_p2_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-paypal'
                   AND EventName='addon.install.started'
                   AND Result='started'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-paypal'
                   AND EventName='addon.install.completed'
                   AND Result='succeeded'
                   AND DetailCode='installed_disabled'))"
        ) === '1:1',
        'audit records one start and one disabled completion'
    );

    echo 'PayPal P2 disposable self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
}

?>
