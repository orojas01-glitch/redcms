<?php
/**
 * Disposable Owner-authorized add-on upgrade and recovery checks.
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
require_once $projectRoot . '/includes/addon_upgrade_helpers.php';
require_once $projectRoot . '/includes/addon_runtime_helpers.php';

if (!preg_match('/\Aredcms_(?:acceptance|addon_upgrade)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(STDERR, 'Add-on upgrade self-test refused non-disposable database: ' . DBNAME . "\n");
    exit(65);
}

$assertions = 0;
$packageId = 'redcms.upgrade-fixture';
$actorId = 2147000962;
$temporaryRoot = sys_get_temp_dir() . '/redcms-addon-upgrade-' . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$packageDirectory = $fixtureProject . '/addons/redcms/upgrade-fixture';
$executionMarker = $temporaryRoot . '/addon-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_upgrade_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_upgrade_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_upgrade_test_remove_tree($path)
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($path);
}

function red_addon_upgrade_test_cleanup($connection, $actorId, $temporaryRoot)
{
    try {
        mysqli_query($connection, "DELETE FROM RED_Addon_Activity_Log WHERE PackageID='redcms.upgrade-fixture'");
        mysqli_query($connection, "DELETE FROM RED_Addon_Settings WHERE PackageID='redcms.upgrade-fixture'");
        mysqli_query($connection, "DELETE FROM RED_Addon_Migrations WHERE PackageID='redcms.upgrade-fixture'");
        mysqli_query($connection, "DELETE FROM RED_Addon_Installations WHERE PackageID='redcms.upgrade-fixture'");
        mysqli_query($connection, 'DROP TABLE IF EXISTS RED_Addon_Upgrade_Fixture');
        mysqli_query($connection, 'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=' . (int) $actorId);
        mysqli_query($connection, 'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=' . (int) $actorId);
        mysqli_query($connection, 'DELETE FROM RED_Admin WHERE RecordID=' . (int) $actorId);
    } catch (Throwable $throwable) {
        error_log('Add-on upgrade self-test cleanup failed: ' . $throwable->getMessage());
    }
    red_addon_upgrade_test_remove_tree($temporaryRoot);
}

function red_addon_upgrade_test_write_package(
    $packageDirectory,
    $executionMarker,
    $version,
    array $migrationContents,
    $includeSetting = true
) {
    $migrationDirectory = $packageDirectory . '/migrations';
    if (!is_dir($migrationDirectory)
        && !mkdir($migrationDirectory, 0700, true)
        && !is_dir($migrationDirectory)
    ) {
        throw new RuntimeException('Could not create upgrade package fixture.');
    }
    foreach (glob($migrationDirectory . '/*.sql') ?: [] as $existing) {
        unlink($existing);
    }
    $entrypoint = "<?php\nfile_put_contents(" . var_export($executionMarker, true) . ", 'executed');\n";
    file_put_contents($packageDirectory . '/addon.php', $entrypoint);
    $files = ['addon.php' => $entrypoint];
    $migrations = [];
    foreach ($migrationContents as $id => $sql) {
        $path = 'migrations/' . $id . '.sql';
        file_put_contents($packageDirectory . '/' . $path, $sql);
        $files[$path] = $sql;
        $migrations[] = [
            'id' => $id,
            'path' => $path,
            'sha256' => hash('sha256', $sql),
        ];
    }
    $integrityFiles = [];
    foreach ($files as $path => $contents) {
        $integrityFiles[] = ['path' => $path, 'sha256' => hash('sha256', $contents)];
    }
    usort($integrityFiles, static function ($left, $right) {
        return strcmp($left['path'], $right['path']);
    });
    $settings = $includeSetting ? [[
        'key' => 'fixture.label',
        'label' => 'Fixture label',
        'type' => 'text',
        'secret' => false,
        'permission' => 'redcms.upgrade-fixture.settings.manage',
        'default' => null,
    ]] : [];
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.upgrade-fixture',
        'name' => 'Upgrade Fixture',
        'description' => 'Disposable disabled-package upgrade fixture.',
        'version' => $version,
        'type' => 'service',
        'compatibility' => ['cms' => '>=5.1 <6.0', 'php' => '>=8.2 <9.0'],
        'provides' => [
            'components' => [],
            'services' => ['redcms.upgrade-fixture/service'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => ['redcms.upgrade-fixture.settings.manage'],
        'settings' => $settings,
        'migrations' => $migrations,
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => ['entrypoint' => 'addon.php', 'files' => $integrityFiles],
        'uninstall' => ['defaultDataAction' => 'retain', 'allowExplicitPurge' => true],
    ];
    file_put_contents(
        $packageDirectory . '/addon.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

$baselineMigration = "CREATE TABLE IF NOT EXISTS RED_Addon_Upgrade_Fixture (\n" .
    "  RecordID int unsigned NOT NULL,\n" .
    "  Label varchar(64) NOT NULL,\n" .
    "  PRIMARY KEY (RecordID)\n" .
    ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
$columnMigration = "ALTER TABLE RED_Addon_Upgrade_Fixture\n" .
    "ADD COLUMN UpgradeLabel varchar(64) DEFAULT NULL;\n";
$dataMigration = "UPDATE RED_Addon_Upgrade_Fixture\n" .
    "SET UpgradeLabel='migrated' WHERE RecordID=1;\n";
$baselineId = '2026-08-12-create-upgrade-fixture';
$columnId = '2026-08-13-add-upgrade-label';
$dataId = '2026-08-13-populate-upgrade-label';
$baselineMigrations = [$baselineId => $baselineMigration];
$targetMigrations = [
    $baselineId => $baselineMigration,
    $columnId => $columnMigration,
    $dataId => $dataMigration,
];

try {
    red_addon_upgrade_test_cleanup($connection, $actorId, $temporaryRoot);
    red_addon_upgrade_test_write_package(
        $packageDirectory,
        $executionMarker,
        '1.0.0',
        $baselineMigrations
    );
    $oldCatalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $oldPackage = $oldCatalog['packages'][$packageId] ?? [];
    $oldSnapshot = red_addon_registry_snapshot($oldPackage);
    red_addon_upgrade_test_assert(
        !empty($oldCatalog['valid']) && is_array($oldSnapshot) && !file_exists($executionMarker),
        'old package snapshot is trusted without executing addon.php'
    );

    $passwordHash = password_hash('AddonUpgradeFixture-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_upgrader', ?, 'Admin', 'Upgrader',
                   'webmaster', '100', '1', 'addon-upgrader@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $passwordHash);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($actorId, 'addons.upgrade', $actorId)"
    );
    if (!is_array($oldSnapshot)
        || !red_addon_install_insert_installation($connection, $oldSnapshot, $actorId)
        || !red_addon_install_update_state($connection, $packageId, 'installed_disabled', $actorId)
    ) {
        throw new RuntimeException('Could not create old installation fixture.');
    }
    red_addon_install_execute_sql($connection, $baselineMigration);
    red_addon_install_record_migration(
        $connection,
        $packageId,
        $oldSnapshot['migrations'][$baselineId],
        $actorId,
        0
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Addon_Upgrade_Fixture (RecordID, Label)
         VALUES (1, 'preserved')"
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON,
            SecretReference, UpdatedByAdminRecordID
         ) VALUES (
            'redcms.upgrade-fixture', 'fixture.label', 'text',
            '\"kept\"', NULL, $actorId
         )"
    );

    red_addon_upgrade_test_write_package(
        $packageDirectory,
        $executionMarker,
        '1.1.0',
        $targetMigrations
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][$packageId] ?? [];
    red_addon_upgrade_test_assert(
        !empty($catalog['valid']) && !empty($package['valid']) && !file_exists($executionMarker),
        'higher target package is trusted and remains unexecuted'
    );

    $denied = red_addon_upgrade_plan($connection, $package, 1, false, $catalog);
    red_addon_upgrade_test_assert(
        empty($denied['valid'])
            && $denied['errors'] === ['owner_upgrade_capability_required'],
        'upgrade requires the persisted Owner capability'
    );
    $plan = red_addon_upgrade_plan($connection, $package, $actorId, false, $catalog);
    $repeatPlan = red_addon_upgrade_plan($connection, $package, $actorId, false, $catalog);
    red_addon_upgrade_test_assert(
        !empty($plan['valid'])
            && !$plan['resume']
            && $plan['currentVersion'] === '1.0.0'
            && $plan['targetVersion'] === '1.1.0'
            && $plan['currentState'] === 'installed_disabled'
            && $plan['appliedMigrations'] === [$baselineId]
            && $plan['pendingMigrations'] === [$columnId, $dataId]
            && ($plan['settingEvidence']['storedCount'] ?? 0) === 1
            && red_addon_valid_sha256($plan['planSha256']),
        'dry run binds higher version, old identity, settings, and append-only migrations'
    );
    red_addon_upgrade_test_assert(
        $repeatPlan === $plan && !file_exists($executionMarker),
        'upgrade plan is deterministic, read-only, and non-executing'
    );

    red_addon_upgrade_test_write_package(
        $packageDirectory,
        $executionMarker,
        '1.1.0',
        $targetMigrations,
        false
    );
    $missingSettingCatalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION,
    ]);
    $missingSettingPlan = red_addon_upgrade_plan(
        $connection,
        $missingSettingCatalog['packages'][$packageId] ?? [],
        $actorId,
        false,
        $missingSettingCatalog
    );
    red_addon_upgrade_test_assert(
        empty($missingSettingPlan['valid'])
            && $missingSettingPlan['errors'] === ['stored_setting_removed'],
        'target cannot silently remove a stored setting definition'
    );
    $driftedMigrations = $targetMigrations;
    $driftedMigrations[$baselineId] .= "-- forbidden historical rewrite\n";
    red_addon_upgrade_test_write_package(
        $packageDirectory,
        $executionMarker,
        '1.1.0',
        $driftedMigrations
    );
    $driftCatalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION,
    ]);
    $driftPlan = red_addon_upgrade_plan(
        $connection,
        $driftCatalog['packages'][$packageId] ?? [],
        $actorId,
        false,
        $driftCatalog
    );
    red_addon_upgrade_test_assert(
        empty($driftPlan['valid'])
            && $driftPlan['errors'] === ['applied_migration_drift'],
        'target cannot rewrite an already recorded migration path or checksum'
    );
    red_addon_upgrade_test_write_package(
        $packageDirectory,
        $executionMarker,
        '1.1.0',
        $targetMigrations
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][$packageId] ?? [];
    $plan = red_addon_upgrade_plan($connection, $package, $actorId, false, $catalog);

    mysqli_query($connection, "UPDATE RED_Addon_Installations SET LifecycleState='enabled' WHERE PackageID='$packageId'");
    $enabledPlan = red_addon_upgrade_plan($connection, $package, $actorId, false, $catalog);
    red_addon_upgrade_test_assert(
        empty($enabledPlan['valid'])
            && $enabledPlan['errors'] === ['package_must_be_disabled'],
        'enabled packages must be explicitly disabled before upgrade'
    );
    mysqli_query($connection, "UPDATE RED_Addon_Installations SET LifecycleState='installed_disabled' WHERE PackageID='$packageId'");

    $stale = red_addon_upgrade_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        str_repeat('f', 64)
    );
    red_addon_upgrade_test_assert(
        $stale['status'] === 'plan_changed'
            && red_addon_upgrade_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Migrations WHERE PackageID='$packageId'),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Upgrade_Fixture'
                       AND COLUMN_NAME='UpgradeLabel'))
                 FROM RED_Addon_Installations WHERE PackageID='$packageId'"
            ) === '1.0.0:installed_disabled:1:0',
        'stale plan is refused before lifecycle, ledger, or schema change'
    );

    $auditFailure = red_addon_upgrade_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        false,
        static function () {
            return false;
        }
    );
    red_addon_upgrade_test_assert(
        $auditFailure['status'] === 'upgrade_start_failed'
            && red_addon_upgrade_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log WHERE PackageID='$packageId'))
                 FROM RED_Addon_Installations WHERE PackageID='$packageId'"
            ) === 'installed_disabled:0',
        'start-audit failure rolls back before migration execution'
    );

    $executionCount = 0;
    $forcedFailure = static function ($dbConnection, $sql) use (&$executionCount) {
        $executionCount++;
        if ($executionCount === 2) {
            throw new RuntimeException('forced_second_upgrade_migration_failure');
        }
        return red_addon_install_execute_sql($dbConnection, $sql);
    };
    $failed = red_addon_upgrade_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        false,
        null,
        $forcedFailure
    );
    red_addon_upgrade_test_assert(
        $failed['status'] === 'migration_execution_failed'
            && $failed['failedMigration'] === $dataId
            && $failed['appliedMigrations'] === [$columnId],
        'forced second migration failure reports the exact resumable boundary'
    );
    red_addon_upgrade_test_assert(
        red_addon_upgrade_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Migrations WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Upgrade_Fixture'
                   AND COLUMN_NAME='UpgradeLabel'),
                (SELECT Label FROM RED_Addon_Upgrade_Fixture WHERE RecordID=1),
                (SELECT JSON_UNQUOTE(ValueJSON) FROM RED_Addon_Settings
                 WHERE PackageID='$packageId' AND SettingKey='fixture.label'))
             FROM RED_Addon_Installations WHERE PackageID='$packageId'"
        ) === '1.0.0:upgrade_failed:2:1:preserved:kept'
            && !file_exists($executionMarker),
        'failed upgrade preserves old identity, prior data/settings, and applied DDL evidence'
    );

    $failedReport = red_addon_registry_package_report($connection, $package);
    red_addon_upgrade_test_assert(
        ($failedReport['status'] ?? '') === 'upgrade_failed'
            && !empty($failedReport['errors'])
            && empty($failedReport['loadable']),
        'registry exposes the recoverable failed state while retaining drift evidence'
    );

    $runtime = red_addon_runtime_bootstrap($connection, $fixtureProject);
    red_addon_upgrade_test_assert(
        $runtime['context']->handler('services', 'redcms.upgrade-fixture/service') === null
            && !file_exists($executionMarker),
        'upgrade_failed package is non-loadable and addon.php remains unexecuted'
    );
    $resumeDenied = red_addon_upgrade_plan($connection, $package, $actorId, false, $catalog);
    red_addon_upgrade_test_assert(
        empty($resumeDenied['valid'])
            && $resumeDenied['errors'] === ['resume_confirmation_required'],
        'failed upgrade cannot resume without explicit confirmation'
    );
    $resumePlan = red_addon_upgrade_plan($connection, $package, $actorId, true, $catalog);
    red_addon_upgrade_test_assert(
        !empty($resumePlan['valid'])
            && $resumePlan['resume']
            && $resumePlan['appliedMigrations'] === [$baselineId, $columnId]
            && $resumePlan['pendingMigrations'] === [$dataId],
        'reviewed resume schedules only the exact remaining migration'
    );

    $completionAuditFailure = static function (
        $dbConnection,
        $eventName,
        $eventPackageId,
        $version,
        $eventActorId,
        $result,
        $detailCode
    ) {
        if ($eventName === 'addon.upgrade.completed') {
            return false;
        }
        return red_addon_install_audit_record(
            $dbConnection,
            $eventName,
            $eventPackageId,
            $version,
            $eventActorId,
            $result,
            $detailCode
        );
    };
    $completionFailed = red_addon_upgrade_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $resumePlan['planSha256'],
        true,
        $completionAuditFailure
    );
    red_addon_upgrade_test_assert(
        $completionFailed['status'] === 'completion_transaction_failed'
            && red_addon_upgrade_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Migrations WHERE PackageID='$packageId'),
                    (SELECT UpgradeLabel FROM RED_Addon_Upgrade_Fixture WHERE RecordID=1))
                 FROM RED_Addon_Installations WHERE PackageID='$packageId'"
            ) === '1.0.0:upgrade_failed:3:migrated',
        'completion-audit failure rolls back target identity and remains recoverable'
    );

    $finalPlan = red_addon_upgrade_plan($connection, $package, $actorId, true, $catalog);
    red_addon_upgrade_test_assert(
        !empty($finalPlan['valid'])
            && $finalPlan['resume']
            && $finalPlan['pendingMigrations'] === [],
        'final recovery recognizes all exact migrations without rerunning them'
    );
    $completed = red_addon_upgrade_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $finalPlan['planSha256'],
        true
    );
    red_addon_upgrade_test_assert(
        $completed['status'] === 'installed_disabled'
            && $completed['currentVersion'] === '1.0.0'
            && $completed['targetVersion'] === '1.1.0'
            && $completed['appliedMigrations'] === [],
        'exact recovery commits the target identity while keeping runtime disabled'
    );
    $finalReport = red_addon_registry_package_report($connection, $package);
    red_addon_upgrade_test_assert(
        ($finalReport['status'] ?? '') === 'installed_disabled_current'
            && empty($finalReport['loadable'])
            && red_addon_upgrade_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', PackageVersion, LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Migrations WHERE PackageID='$packageId'),
                    (SELECT Label FROM RED_Addon_Upgrade_Fixture WHERE RecordID=1),
                    (SELECT UpgradeLabel FROM RED_Addon_Upgrade_Fixture WHERE RecordID=1),
                    (SELECT JSON_UNQUOTE(ValueJSON) FROM RED_Addon_Settings
                     WHERE PackageID='$packageId' AND SettingKey='fixture.label'))
                 FROM RED_Addon_Installations WHERE PackageID='$packageId'"
            ) === '1.1.0:installed_disabled:3:preserved:migrated:kept'
            && !file_exists($executionMarker),
        'completed upgrade has exact current registry, ledger, data, settings, and no runtime execution'
    );
    red_addon_upgrade_test_assert(
        red_addon_upgrade_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                SUM(EventName='addon.upgrade.started' AND Result='started'),
                SUM(EventName='addon.upgrade.failed' AND Result='failed'),
                SUM(EventName='addon.upgrade.completed' AND Result='succeeded'),
                SUM(PackageVersion='1.1.0' AND ActorAdminRecordID=$actorId))
             FROM RED_Addon_Activity_Log WHERE PackageID='$packageId'"
        ) === '3:2:1:6',
        'audit history contains only bounded target-version start, failure, and completion facts'
    );

    $repeat = red_addon_upgrade_plan($connection, $package, $actorId, false, $catalog);
    red_addon_upgrade_test_assert(
        empty($repeat['valid'])
            && $repeat['errors'] === ['target_version_not_newer'],
        'the same target version cannot be applied twice'
    );
    $cliSource = file_get_contents($projectRoot . '/scripts/admin-addon-upgrade.php');
    red_addon_upgrade_test_assert(
        is_string($cliSource)
            && str_contains($cliSource, '--confirm-current-version=')
            && str_contains($cliSource, '--confirm-target-version=')
            && str_contains($cliSource, '--confirm-backup-sha256=')
            && str_contains($cliSource, '--confirm-state=')
            && str_contains($cliSource, '--resume-failed')
            && str_contains($cliSource, "PHP_SAPI !== 'cli'"),
        'upgrade command is CLI-only with target, backup, state, plan, and resume confirmations'
    );

    red_addon_upgrade_test_cleanup($connection, $actorId, $temporaryRoot);
    red_addon_upgrade_test_assert(
        red_addon_upgrade_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Installations WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Migrations WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Settings WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Addon_Upgrade_Fixture'),
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId))"
        ) === '0:0:0:0:0:0'
            && !is_dir($temporaryRoot),
        'upgrade registry, ledger, settings, data, audit, actor, and package fixtures clean up exactly'
    );

    echo 'Add-on upgrade/recovery self-test passed: ' . $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    red_addon_upgrade_test_cleanup($connection, $actorId, $temporaryRoot);
    $db->close();
    exit(1);
}

$db->close();
exit(0);

?>
