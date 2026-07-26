<?php
/**
 * Disposable database checks for guarded add-on installation and resume.
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
require_once $projectRoot . '/includes/addon_install_helpers.php';

if (!preg_match('/\Aredcms_(?:acceptance|addon_install)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(STDERR, "Add-on install self-test refused non-disposable database: " . DBNAME . "\n");
    exit(65);
}

$assertions = 0;
$packageId = 'redcms.install-fixture';
$actorId = 2147000950;
$temporaryRoot = sys_get_temp_dir() . '/redcms-addon-install-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_install_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_install_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_install_test_remove_tree($path)
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

function red_addon_install_test_cleanup(
    $connection,
    $packageId,
    $actorId,
    $temporaryRoot
) {
    try {
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Activity_Log
             WHERE PackageID='redcms.install-fixture'"
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Migrations
             WHERE PackageID='redcms.install-fixture'"
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Installations
             WHERE PackageID IN (
               'redcms.install-fixture',
               'redcms.install-base'
             )"
        );
        mysqli_query($connection, 'DROP TABLE IF EXISTS RED_Addon_Install_Fixture');
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=' . (int) $actorId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=' . (int) $actorId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=' . (int) $actorId
        );
    } catch (Throwable $throwable) {
        error_log('Add-on install self-test cleanup failed: ' . $throwable->getMessage());
    }
    red_addon_install_test_remove_tree($temporaryRoot);
}

function red_addon_install_test_package($temporaryRoot, $executionMarker)
{
    $project = $temporaryRoot . '/project';
    $directory = $project . '/addons/redcms/install-fixture';
    $migrationDirectory = $directory . '/migrations';
    if (!mkdir($migrationDirectory, 0700, true) && !is_dir($migrationDirectory)) {
        throw new RuntimeException('Could not create install package fixture.');
    }

    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) .
        ", 'executed');\n";
    $firstMigration = "CREATE TABLE IF NOT EXISTS RED_Addon_Install_Fixture (\n" .
        "  RecordID int unsigned NOT NULL,\n" .
        "  Label varchar(64) NOT NULL,\n" .
        "  PRIMARY KEY (RecordID)\n" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
    $secondMigration = "INSERT INTO RED_Addon_Install_Fixture (RecordID, Label)\n" .
        "VALUES (1, 'installed')\n" .
        "ON DUPLICATE KEY UPDATE Label=VALUES(Label);\n";

    $files = [
        'addon.php' => $entrypoint,
        'migrations/2026-07-26-create-install-fixture.sql' => $firstMigration,
        'migrations/2026-07-26-seed-install-fixture.sql' => $secondMigration,
    ];
    foreach ($files as $path => $content) {
        file_put_contents($directory . '/' . $path, $content);
    }

    $migrations = [[
        'id' => '2026-07-26-create-install-fixture',
        'path' => 'migrations/2026-07-26-create-install-fixture.sql',
        'sha256' => hash('sha256', $firstMigration),
    ], [
        'id' => '2026-07-26-seed-install-fixture',
        'path' => 'migrations/2026-07-26-seed-install-fixture.sql',
        'sha256' => hash('sha256', $secondMigration),
    ]];
    $integrityFiles = [];
    foreach ($files as $path => $content) {
        $integrityFiles[] = [
            'path' => $path,
            'sha256' => hash('sha256', $content),
        ];
    }
    usort(
        $integrityFiles,
        static function ($left, $right) {
            return strcmp($left['path'], $right['path']);
        }
    );

    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.install-fixture',
        'name' => 'Install Fixture',
        'description' => 'Guarded installation and recovery fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => ['redcms.install-fixture/service'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => [
            'required' => [],
            'optional' => [],
        ],
        'permissions' => ['redcms.install-fixture.settings.manage'],
        'settings' => [],
        'migrations' => $migrations,
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => [
            'public' => [],
            'admin' => [],
        ],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => $integrityFiles,
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => true,
        ],
    ];
    file_put_contents(
        $directory . '/addon.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    return $project;
}

try {
    red_addon_install_test_cleanup(
        $connection,
        $packageId,
        $actorId,
        $temporaryRoot
    );

    red_addon_install_test_assert(
        red_addon_install_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Activity_Log'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Activity_Log'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log)
             )"
        ) === '1:8:0',
        'the bounded add-on lifecycle audit table exists and starts empty'
    );

    $passwordHash = password_hash('AddonInstallFixture-2026!', PASSWORD_DEFAULT);
    $stmt = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_installer', ?, 'Admin', 'Installer',
                   'webmaster', '100', '1', 'addon-installer@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($stmt, 'is', $actorId, $passwordHash);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
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
         VALUES ($actorId, 'addons.install', $actorId)"
    );

    $fixtureProject = red_addon_install_test_package(
        $temporaryRoot,
        $executionMarker
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][$packageId] ?? [];
    red_addon_install_test_assert(
        !empty($catalog['valid'])
            && !empty($package['valid'])
            && !file_exists($executionMarker),
        'trusted package discovery succeeds without executing addon.php'
    );

    $targetManifestPath = $fixtureProject .
        '/addons/redcms/install-fixture/addon.json';
    $targetManifest = json_decode(
        (string) file_get_contents($targetManifestPath),
        true
    );
    $baseDirectory = $fixtureProject . '/addons/redcms/install-base';
    if (!is_array($targetManifest)
        || (!mkdir($baseDirectory, 0700, true) && !is_dir($baseDirectory))
    ) {
        throw new RuntimeException('Could not prepare dependency fixture.');
    }
    $baseEntrypoint = "<?php\n";
    file_put_contents($baseDirectory . '/addon.php', $baseEntrypoint);
    $baseManifest = $targetManifest;
    $baseManifest['id'] = 'redcms.install-base';
    $baseManifest['name'] = 'Install Base';
    $baseManifest['description'] = 'Required dependency state fixture.';
    $baseManifest['provides']['services'] = ['redcms.install-base/service'];
    $baseManifest['permissions'] = ['redcms.install-base.settings.manage'];
    $baseManifest['migrations'] = [];
    $baseManifest['integrity']['files'] = [[
        'path' => 'addon.php',
        'sha256' => hash('sha256', $baseEntrypoint),
    ]];
    file_put_contents(
        $baseDirectory . '/addon.json',
        json_encode(
            $baseManifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
    );
    $targetManifest['dependencies']['required'] = [[
        'id' => 'redcms.install-base',
        'version' => '>=1.0 <2.0',
    ]];
    file_put_contents(
        $targetManifestPath,
        json_encode(
            $targetManifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
    );
    $dependencyCatalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $dependencyPackage = $dependencyCatalog['packages'][$packageId] ?? [];
    $dependencyDenied = red_addon_install_plan(
        $connection,
        $dependencyPackage,
        $actorId,
        false,
        $dependencyCatalog
    );
    red_addon_install_test_assert(
        !empty($dependencyCatalog['valid'])
            && empty($dependencyDenied['valid'])
            && $dependencyDenied['errors'] === [
                'required_dependency_not_enabled',
            ],
        'a required package present only on disk cannot authorize installation'
    );

    $basePackage = $dependencyCatalog['packages']['redcms.install-base'] ?? [];
    $baseSnapshot = red_addon_registry_snapshot($basePackage);
    if ($baseSnapshot === null
        || !red_addon_install_insert_installation(
            $connection,
            $baseSnapshot,
            $actorId
        )
        || !red_addon_install_update_state(
            $connection,
            'redcms.install-base',
            'enabled',
            $actorId
        )
    ) {
        throw new RuntimeException('Could not record enabled dependency fixture.');
    }
    $dependencyAllowed = red_addon_install_plan(
        $connection,
        $dependencyPackage,
        $actorId,
        false,
        $dependencyCatalog
    );
    red_addon_install_test_assert(
        !empty($dependencyAllowed['valid'])
            && count($dependencyAllowed['requiredDependencies']) === 1
            && $dependencyAllowed['requiredDependencies'][0]['id']
                === 'redcms.install-base'
            && $dependencyAllowed['requiredDependencies'][0]['lifecycleState']
                === 'enabled',
        'an exact current enabled dependency becomes immutable plan evidence'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Addon_Installations
         WHERE PackageID='redcms.install-base'"
    );
    $targetManifest['dependencies']['required'] = [];
    file_put_contents(
        $targetManifestPath,
        json_encode(
            $targetManifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
    );
    red_addon_install_test_remove_tree($baseDirectory);
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][$packageId] ?? [];

    $deniedPlan = red_addon_install_plan($connection, $package, 1);
    red_addon_install_test_assert(
        empty($deniedPlan['valid'])
            && $deniedPlan['errors'] === ['owner_install_capability_required'],
        'a legacy manager without persisted Owner install capability is denied'
    );

    $plan = red_addon_install_plan($connection, $package, $actorId);
    red_addon_install_test_assert(
        !empty($plan['valid'])
            && !$plan['resume']
            && count($plan['pendingMigrations']) === 2
            && red_addon_valid_sha256($plan['planSha256'])
            && !file_exists($executionMarker),
        'Owner dry-run produces a deterministic two-migration disabled install plan'
    );

    $stalePlanResult = red_addon_install_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        str_repeat('f', 64)
    );
    red_addon_install_test_assert(
        $stalePlanResult['status'] === 'plan_changed'
            && red_addon_install_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Addon_Installations),
                    (SELECT COUNT(*) FROM RED_Addon_Migrations),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Install_Fixture')
                 )"
            ) === '0:0:0:0',
        'a stale plan hash is refused before any registry, audit, or SQL change'
    );

    $auditFailureResult = red_addon_install_package(
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
    red_addon_install_test_assert(
        $auditFailureResult['status'] === 'audit_start_failed'
            && red_addon_install_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM RED_Addon_Installations),
                    (SELECT COUNT(*) FROM RED_Addon_Migrations),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Install_Fixture')
                 )"
            ) === '0:0:0:0',
        'failure to persist the start audit rolls back before package SQL'
    );

    $executionCount = 0;
    $forcedFailureExecutor = static function ($dbConnection, $sql) use (&$executionCount) {
        $executionCount++;
        if ($executionCount === 2) {
            throw new RuntimeException('forced_second_migration_failure');
        }
        return red_addon_install_execute_sql($dbConnection, $sql);
    };
    $failedResult = red_addon_install_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        false,
        null,
        $forcedFailureExecutor
    );
    red_addon_install_test_assert(
        $failedResult['status'] === 'migration_execution_failed'
            && $failedResult['failedMigration'] === '2026-07-26-seed-install-fixture'
            && $failedResult['appliedMigrations'] === [
                '2026-07-26-create-install-fixture',
            ],
        'a forced second-migration failure reports the exact resumable boundary'
    );
    red_addon_install_test_assert(
        red_addon_install_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.install-fixture'),
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='redcms.install-fixture'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.install-fixture'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Install_Fixture'),
                (SELECT COUNT(*) FROM RED_Addon_Install_Fixture)
             )"
        ) === 'installation_failed:1:2:1:0',
        'partial DDL remains visible, failed, audited, non-loadable, and resumable'
    );

    $resumeDenied = red_addon_install_plan(
        $connection,
        $package,
        $actorId,
        false
    );
    red_addon_install_test_assert(
        empty($resumeDenied['valid'])
            && $resumeDenied['errors'] === ['resume_confirmation_required'],
        'failed installation cannot resume without an explicit resume confirmation'
    );

    $resumePlan = red_addon_install_plan(
        $connection,
        $package,
        $actorId,
        true
    );
    red_addon_install_test_assert(
        !empty($resumePlan['valid'])
            && $resumePlan['resume']
            && $resumePlan['appliedMigrations'] === [
                '2026-07-26-create-install-fixture',
            ]
            && $resumePlan['pendingMigrations'] === [
                '2026-07-26-seed-install-fixture',
            ],
        'resume plan reuses only exact ledger evidence and schedules the remainder'
    );

    $completedResult = red_addon_install_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $resumePlan['planSha256'],
        true
    );
    red_addon_install_test_assert(
        $completedResult['status'] === 'installed_disabled'
            && $completedResult['appliedMigrations'] === [
                '2026-07-26-seed-install-fixture',
            ]
            && !file_exists($executionMarker),
        'reviewed resume applies only the pending migration and remains unloaded'
    );

    $completedReport = red_addon_registry_package_report($connection, $package);
    red_addon_install_test_assert(
        $completedReport['status'] === 'installed_disabled_current'
            && !$completedReport['loadable']
            && red_addon_install_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT LifecycleState FROM RED_Addon_Installations
                     WHERE PackageID='redcms.install-fixture'),
                    (SELECT COUNT(*) FROM RED_Addon_Migrations
                     WHERE PackageID='redcms.install-fixture'),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.install-fixture'),
                    (SELECT Label FROM RED_Addon_Install_Fixture
                     WHERE RecordID=1)
                 )"
            ) === 'installed_disabled:2:4:installed',
        'completed install has exact ledger, bounded audit, disabled state, and data'
    );

    $repeatPlan = red_addon_install_plan($connection, $package, $actorId);
    red_addon_install_test_assert(
        empty($repeatPlan['valid'])
            && $repeatPlan['errors'] === ['package_already_recorded'],
        'an installed package cannot be installed a second time'
    );

    red_addon_install_test_assert(
        red_addon_install_sql_guard(
            'ALTER TABLE RED_Articles ADD COLUMN Unsafe int;'
        ) === 'migration_table_scope'
            && red_addon_install_sql_guard(
                'DELETE FROM RED_Addon_Migrations;'
            ) === 'migration_table_scope'
            && red_addon_install_sql_guard(
                "GRANT ALL PRIVILEGES ON *.* TO 'x'@'%';"
            ) === 'migration_forbidden_sql'
            && red_addon_install_sql_guard(
                'CREATE TABLE Unnamespaced_Order (RecordID int);'
            ) === 'migration_table_scope'
            && red_addon_install_sql_guard(
                'CREATE TABLE RED_Addon_Unsafe (RecordID int); COMMIT;'
            ) === 'migration_forbidden_sql',
        'migration guard rejects core, registry, privilege, transaction, and unnamespaced table SQL'
    );

    $auditPayload = red_addon_install_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':',
            SUM(EventName='addon.install.started' AND Result='started'),
            SUM(EventName='addon.install.failed' AND Result='failed'
                AND DetailCode='migration_execution_failed'),
            SUM(EventName='addon.install.completed' AND Result='succeeded'
                AND DetailCode='installed_disabled'),
            SUM(PackageID='redcms.install-fixture'
                AND PackageVersion='1.0.0'
                AND ActorAdminRecordID=$actorId)
         )
         FROM RED_Addon_Activity_Log
         WHERE PackageID='redcms.install-fixture'"
    );
    red_addon_install_test_assert(
        $auditPayload === '2:1:1:4',
        'audit history contains only bounded start, failure, resume, and completion facts'
    );

    $cliSource = (string) file_get_contents(
        $projectRoot . '/scripts/admin-addon-install.php'
    );
    red_addon_install_test_assert(
        str_contains($cliSource, "PHP_SAPI !== 'cli'")
            && str_contains($cliSource, '--confirm-database=')
            && str_contains($cliSource, '--confirm-package=')
            && str_contains($cliSource, '--confirm-version=')
            && str_contains($cliSource, '--confirm-plan-sha256=')
            && str_contains($cliSource, '--confirm-backup-sha256=')
            && str_contains($cliSource, '--confirm-state=')
            && str_contains($cliSource, '--resume-failed')
            && str_contains($cliSource, '--apply')
            && !file_exists($projectRoot . '/admin/bin/addon_install.php'),
        'installer is local-only with plan, backup, target, state, and resume confirmations'
    );

    red_addon_install_test_cleanup(
        $connection,
        $packageId,
        $actorId,
        $temporaryRoot
    );
    red_addon_install_test_assert(
        red_addon_install_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='redcms.install-fixture'),
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='redcms.install-fixture'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.install-fixture'),
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Install_Fixture')
             )"
        ) === '0:0:0:0:0' && !file_exists($executionMarker),
        'installer fixtures, package table, audit, authorization, and code marker clean up exactly'
    );

    printf("Add-on install self-test passed: %d assertions.\n", $assertions);
} catch (Throwable $throwable) {
    red_addon_install_test_cleanup(
        $connection,
        $packageId,
        $actorId,
        $temporaryRoot
    );
    fwrite(
        STDERR,
        $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n"
    );
    $db->close();
    exit(1);
}

$db->close();
