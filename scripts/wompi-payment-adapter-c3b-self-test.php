<?php
/**
 * Exact Wompi C3B install-disabled, database, and registrar rehearsal.
 */

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

if (!preg_match(
    '/\Aredcms_(?:wompi_c3b|payment_adapter_db)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Wompi C3B refused non-disposable database: ' . DBNAME . "\n"
    );
    exit(65);
}

$fixtureProject = getenv('RED_WOMPI_C3B_PROJECT_ROOT');
if (!is_string($fixtureProject)
    || realpath($fixtureProject) !== realpath($projectRoot)
) {
    fwrite(STDERR, "Wompi C3B staged project root is invalid.\n");
    exit(65);
}

$assertions = 0;
$actorId = 2147000995;
$storePackageId = 'redcms.store-lite';
$wompiPackageId = 'redcms.store-lite-wompi';
$attemptsTable = 'RED_Addon_StoreLite_Wompi_Payment_Attempts';
$eventsTable = 'RED_Addon_StoreLite_Wompi_Event_Receipts';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_wompi_c3b_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_wompi_c3b_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_wompi_c3b_database_fingerprint($connection)
{
    $queries = [
        "SELECT PackageID, PackageVersion, PackageType, ManifestSHA256,
                InventorySHA256, LifecycleState, InstalledByAdminRecordID,
                UpdatedByAdminRecordID
         FROM RED_Addon_Installations
         WHERE PackageID IN ('redcms.store-lite','redcms.store-lite-wompi')
         ORDER BY PackageID",
        "SELECT PackageID, MigrationID, MigrationPath, Checksum,
                AppliedByAdminRecordID, ExecutionMs
         FROM RED_Addon_Migrations
         WHERE PackageID IN ('redcms.store-lite','redcms.store-lite-wompi')
         ORDER BY PackageID, MigrationID",
        "SELECT EventName, PackageID, PackageVersion, ActorAdminRecordID,
                Result, DetailCode
         FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.store-lite-wompi'
         ORDER BY RecordID",
        "SELECT TABLE_NAME, ENGINE
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN (
             'RED_Addon_StoreLite_Wompi_Payment_Attempts',
             'RED_Addon_StoreLite_Wompi_Event_Receipts'
           )
         ORDER BY TABLE_NAME",
        "SELECT PackageID, SettingKey
         FROM RED_Addon_Settings
         WHERE PackageID='redcms.store-lite-wompi'
         ORDER BY SettingKey",
        'SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Payment_Attempts',
        'SELECT COUNT(*) FROM RED_Addon_StoreLite_Wompi_Event_Receipts',
    ];
    $material = [];
    foreach ($queries as $query) {
        $result = mysqli_query($connection, $query);
        if (!$result) {
            throw new RuntimeException('Could not fingerprint C3B database.');
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

function red_wompi_c3b_record_enabled_store(
    $connection,
    array $package,
    $actorId
) {
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

if (!defined('RED_WOMPI_C3B_FIXTURE_ONLY')) {
try {
    $password = password_hash('WompiC3B-Disposable-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_wompi_c3b', ?, 'Admin', 'WompiC3B',
                   'webmaster', '', '', 'wompi-c3b@example.test',
                   'N', 'to', 'N', 'to')"
    );
    if (!$statement) {
        throw new RuntimeException('Could not prepare C3B Owner fixture.');
    }
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    $inserted = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_wompi_c3b_assert($inserted, 'disposable Owner fixture is recorded');
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
    red_wompi_c3b_assert(
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
        'persisted Owner has only the two lifecycle capabilities used here'
    );

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $wompiPackage = $catalog['packages'][$wompiPackageId] ?? [];
    red_wompi_c3b_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($wompiPackage['valid'])
            && ($storePackage['manifest']['version'] ?? null) === '0.1.35'
            && ($wompiPackage['manifest']['version'] ?? null) === '0.1.0'
            && count(
                $wompiPackage['manifest']['integrity']['files'] ?? []
            ) === 9,
        'exact Store Lite 0.1.35 and nine-file Wompi 0.1.0 discover together'
    );
    red_wompi_c3b_assert(
        !class_exists(
            'RED_CMS_Store_Lite_Wompi_Nequi_Offline_Adapter',
            false
        ),
        'discovery executes no Wompi package PHP'
    );

    red_wompi_c3b_assert(
        red_wompi_c3b_record_enabled_store(
            $connection,
            $storePackage,
            $actorId
        ),
        'exact Store Lite identity is recorded as the enabled dependency baseline'
    );
    red_wompi_c3b_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='redcms.store-lite'))
             FROM RED_Addon_Installations
             WHERE PackageID='redcms.store-lite'"
        ) === '0.1.35:enabled:11',
        'Store Lite baseline is current and complete without replaying its proven install'
    );

    $installPlan = red_addon_install_plan(
        $connection,
        $wompiPackage,
        $actorId,
        false,
        $catalog
    );
    red_wompi_c3b_assert(
        !empty($installPlan['valid'])
            && !$installPlan['resume']
            && array_column(
                $installPlan['requiredDependencies'],
                'id'
            ) === [$storePackageId]
            && $installPlan['pendingMigrations'] === [
                '2026-08-23-wompi-payment-attempts',
                '2026-08-23-wompi-event-receipts',
            ]
            && red_addon_valid_sha256($installPlan['planSha256']),
        'Owner receives the exact two-migration Wompi install-disabled plan'
    );
    $installed = red_addon_install_package(
        $connection,
        $wompiPackageId,
        $fixtureProject,
        $actorId,
        $installPlan['planSha256']
    );
    red_wompi_c3b_assert(
        $installed['status'] === 'installed_disabled'
            && $installed['version'] === '0.1.0'
            && $installed['appliedMigrations'] === [
                '2026-08-23-wompi-payment-attempts',
                '2026-08-23-wompi-event-receipts',
            ]
            && !class_exists(
                'RED_CMS_Store_Lite_Wompi_Nequi_Offline_Adapter',
                false
            ),
        'guarded install applies both migrations and leaves package PHP unloaded'
    );
    red_wompi_c3b_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.store-lite-wompi'),
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='redcms.store-lite-wompi'),
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
                 WHERE PackageID='redcms.store-lite-wompi')
             )"
        ) === 'installed_disabled:2:2:2:0:0:0',
        'Wompi remains disabled with two empty InnoDB tables and no settings'
    );

    $databasePlan = red_addon_payment_adapter_database_preflight(
        $connection,
        $wompiPackage,
        $actorId,
        $catalog
    );
    red_wompi_c3b_assert(
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
        'exact database evidence is ready while every runtime effect is blocked'
    );
    red_wompi_c3b_assert(
        $databasePlan['dependencyCount'] === 1
            && $databasePlan['migrationCount'] === 2
            && $databasePlan['tableCount'] === 2
            && $databasePlan['innoDbTableCount'] === 2
            && array_column($databasePlan['blockers'], 'code') === [
                'atomic_payment_adapter_enablement_required',
                'registrar_validation_required',
                'server_event_ingress_required',
            ],
        'database plan clears only its exact dependency, migration, and table gate'
    );

    $beforeRegistrar = red_wompi_c3b_database_fingerprint($connection);
    $registrarPlan = red_addon_payment_adapter_validate_registrar(
        $wompiPackage,
        $databasePlan
    );
    red_wompi_c3b_assert(
        red_addon_payment_adapter_registrar_preflight_is_valid(
            $registrarPlan
        )
            && $registrarPlan['profileId']
                === 'store_lite_wompi_adapter_v1'
            && $registrarPlan['registrarEvidenceReady']
            && $registrarPlan['packageExecutionAttempted']
            && $registrarPlan['registrarExecutionCompleted']
            && !$registrarPlan['handlerInvocation']
            && !$registrarPlan['runtimePublication']
            && !$registrarPlan['secretResolution']
            && !$registrarPlan['networkAccess']
            && !$registrarPlan['routeExposure'],
        'registration-only execution validates Wompi without invoking or publishing handlers'
    );
    red_wompi_c3b_assert(
        $registrarPlan['adapter']
            === 'redcms.store-lite-wompi/checkout'
            && $registrarPlan['serverEventRoute']
                === 'redcms.store-lite-wompi/provider-events'
            && $registrarPlan['registrationCount'] === 2
            && array_column($registrarPlan['blockers'], 'code') === [
                'atomic_payment_adapter_enablement_required',
                'server_event_ingress_required',
            ]
            && class_exists(
                'RED_CMS_Store_Lite_Wompi_Nequi_Offline_Adapter',
                false
            ),
        'only the exact adapter and refusing route are registration evidence'
    );
    red_wompi_c3b_assert(
        hash_equals(
            $beforeRegistrar,
            red_wompi_c3b_database_fingerprint($connection)
        ),
        'registrar validation changes no database, setting, or payment fact'
    );
    $repeatInstall = red_addon_install_plan(
        $connection,
        $wompiPackage,
        $actorId,
        false,
        $catalog
    );
    red_wompi_c3b_assert(
        empty($repeatInstall['valid'])
            && $repeatInstall['errors'] === ['package_already_recorded'],
        'installed-disabled Wompi cannot be installed a second time'
    );
    red_wompi_c3b_assert(
        red_wompi_c3b_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND EventName='addon.install.started'
                   AND Result='started'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.store-lite-wompi'
                   AND EventName='addon.install.completed'
                   AND Result='succeeded'
                   AND DetailCode='installed_disabled'))"
        ) === '1:1',
        'bounded install audit records one start and one disabled completion'
    );

    echo 'Wompi payment-adapter C3B disposable self-test passed: '
        . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}
}

?>
