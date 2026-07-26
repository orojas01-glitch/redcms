<?php
/**
 * Disposable database checks for read-only add-on registry reconciliation.
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
require_once $projectRoot . '/includes/addon_registry_helpers.php';

if (!preg_match('/\Aredcms_(?:acceptance|addon_registry)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(STDERR, "Add-on registry self-test refused non-disposable database: " . DBNAME . "\n");
    exit(65);
}

$assertions = 0;
$packageId = 'redcms.registry-fixture';
$temporaryRoot = sys_get_temp_dir() . '/redcms-addon-registry-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_registry_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_registry_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_registry_test_remove_tree($path)
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

function red_addon_registry_test_cleanup($connection, $packageId, $temporaryRoot)
{
    try {
        $stmt = mysqli_prepare(
            $connection,
            'DELETE FROM RED_Addon_Migrations WHERE PackageID=?'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $packageId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $stmt = mysqli_prepare(
            $connection,
            'DELETE FROM RED_Addon_Installations WHERE PackageID=?'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $packageId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } catch (Throwable $throwable) {
        error_log('Add-on registry self-test database cleanup failed: ' . $throwable->getMessage());
    }
    red_addon_registry_test_remove_tree($temporaryRoot);
}

function red_addon_registry_test_package($temporaryRoot, $executionMarker)
{
    $project = $temporaryRoot . '/project';
    $directory = $project . '/addons/redcms/registry-fixture';
    $migrationDirectory = $directory . '/migrations';
    if (!mkdir($migrationDirectory, 0700, true) && !is_dir($migrationDirectory)) {
        throw new RuntimeException('Could not create registry package fixture.');
    }

    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) .
        ", 'executed');\n";
    $migration = "CREATE TABLE RED_Addon_Registry_Fixture (RecordID int unsigned NOT NULL);\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    file_put_contents(
        $migrationDirectory . '/2026-07-26-create-fixture.sql',
        $migration
    );

    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.registry-fixture',
        'name' => 'Registry Fixture',
        'description' => 'Read-only registry reconciliation fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => ['redcms.registry-fixture/service'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => [
            'required' => [],
            'optional' => [],
        ],
        'permissions' => ['redcms.registry-fixture.settings.manage'],
        'settings' => [],
        'migrations' => [[
            'id' => '2026-07-26-create-fixture',
            'path' => 'migrations/2026-07-26-create-fixture.sql',
            'sha256' => hash('sha256', $migration),
        ]],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => [
            'public' => [],
            'admin' => [],
        ],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => hash('sha256', $entrypoint),
            ], [
                'path' => 'migrations/2026-07-26-create-fixture.sql',
                'sha256' => hash('sha256', $migration),
            ]],
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
    red_addon_registry_test_cleanup($connection, $packageId, $temporaryRoot);

    red_addon_registry_test_assert(
        red_addon_registry_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME IN (
                     'RED_Addon_Installations',
                     'RED_Addon_Migrations'
                   )),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Installations'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Migrations'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA=DATABASE()
                   AND CONSTRAINT_NAME='fk_red_addon_migrations_installation')
             )"
        ) === '2:10:7:1',
        'registry tables, exact columns, and migration ownership foreign key exist'
    );
    red_addon_registry_test_assert(
        red_addon_registry_test_scalar(
            $connection,
            'SELECT CONCAT_WS(":", COUNT(*), (SELECT COUNT(*) FROM RED_Addon_Migrations))
             FROM RED_Addon_Installations'
        ) === '0:0',
        'the client registry starts empty'
    );

    $ownerActor = [
        'role' => 'owner',
        'capabilities' => red_admin_addon_lifecycle_capabilities(),
    ];
    red_addon_registry_test_assert(
        red_addon_registry_actor_can_transition($ownerActor, 'install')
            && red_addon_registry_actor_can_transition($ownerActor, 'enable')
            && red_addon_registry_actor_can_transition($ownerActor, 'purge')
            && !red_addon_registry_actor_can_transition($ownerActor, 'root')
            && !red_addon_registry_actor_can_transition(
                ['role' => 'webmaster', 'capabilities' => ['addons.install']],
                'install'
            ),
        'registry lifecycle mappings require both Owner and the exact capability'
    );

    $fixtureProject = red_addon_registry_test_package(
        $temporaryRoot,
        $executionMarker
    );
    $package = red_addon_validate_manifest(
        $packageId,
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $snapshot = red_addon_registry_snapshot($package);
    red_addon_registry_test_assert(
        !empty($package['valid'])
            && is_array($snapshot)
            && red_addon_valid_sha256($snapshot['manifestSha256'])
            && red_addon_valid_sha256($snapshot['inventorySha256'])
            && !file_exists($executionMarker)
            && red_addon_registry_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Registry_Fixture'"
            ) === '0',
        'validated files produce deterministic hashes without executing addon.php or package SQL'
    );

    $discoveredReport = red_addon_registry_package_report($connection, $package);
    red_addon_registry_test_assert(
        $discoveredReport['status'] === 'discovered_valid'
            && !$discoveredReport['installed']
            && !$discoveredReport['loadable'],
        'a valid filesystem package remains discovered but not installed or loadable'
    );

    $stmt = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState,
            InstalledByAdminRecordID, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?, 2147000960, 2147000960)'
    );
    $state = 'installed_disabled';
    mysqli_stmt_bind_param(
        $stmt,
        'ssssss',
        $snapshot['id'],
        $snapshot['version'],
        $snapshot['type'],
        $snapshot['manifestSha256'],
        $snapshot['inventorySha256'],
        $state
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $pendingReport = red_addon_registry_package_report($connection, $package);
    red_addon_registry_test_assert(
        $pendingReport['status'] === 'migration_pending'
            && $pendingReport['lifecycleState'] === 'installed_disabled'
            && $pendingReport['pendingMigrations'] === ['2026-07-26-create-fixture']
            && !$pendingReport['loadable'],
        'an installation remains fail-closed while a declared migration is unrecorded'
    );

    $migration = $snapshot['migrations']['2026-07-26-create-fixture'];
    $stmt = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Migrations (
            PackageID, MigrationID, MigrationPath, Checksum,
            AppliedByAdminRecordID, ExecutionMs
         ) VALUES (?, ?, ?, ?, 2147000960, 7)'
    );
    mysqli_stmt_bind_param(
        $stmt,
        'ssss',
        $snapshot['id'],
        $migration['id'],
        $migration['path'],
        $migration['sha256']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $currentReport = red_addon_registry_package_report($connection, $package);
    red_addon_registry_test_assert(
        $currentReport['status'] === 'installed_disabled_current'
            && $currentReport['errors'] === []
            && $currentReport['pendingMigrations'] === []
            && !$currentReport['loadable'],
        'matching installation and immutable ledger reconcile as disabled and current'
    );

    $duplicateIdRejected = false;
    try {
        mysqli_query(
            $connection,
            "INSERT INTO RED_Addon_Migrations (
                PackageID, MigrationID, MigrationPath, Checksum,
                AppliedByAdminRecordID
             ) VALUES (
                'redcms.registry-fixture',
                '2026-07-26-create-fixture',
                'migrations/duplicate.sql',
                '" . str_repeat('a', 64) . "',
                2147000960
             )"
        );
    } catch (Throwable $throwable) {
        $duplicateIdRejected = true;
    }
    red_addon_registry_test_assert(
        $duplicateIdRejected,
        'the database rejects reuse of an applied package migration id'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Migrations
         SET Checksum='" . str_repeat('b', 64) . "'
         WHERE PackageID='redcms.registry-fixture'"
    );
    $driftReport = red_addon_registry_package_report($connection, $package);
    red_addon_registry_test_assert(
        $driftReport['status'] === 'registry_drift'
            && str_contains(implode(' ', $driftReport['errors']), 'Applied migration drift'),
        'an applied migration checksum mismatch fails reconciliation closed'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Migrations
         SET Checksum='" . $migration['sha256'] . "'
         WHERE PackageID='redcms.registry-fixture'"
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET PackageVersion='1.0.1'
         WHERE PackageID='redcms.registry-fixture'"
    );
    $identityDriftReport = red_addon_registry_package_report($connection, $package);
    red_addon_registry_test_assert(
        $identityDriftReport['status'] === 'registry_drift'
            && str_contains(
                implode(' ', $identityDriftReport['errors']),
                'PackageVersion'
            ),
        'deployed version drift fails reconciliation closed'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET PackageVersion='1.0.0', LifecycleState='enabled'
         WHERE PackageID='redcms.registry-fixture'"
    );

    $enabledReport = red_addon_registry_package_report($connection, $package);
    red_addon_registry_test_assert(
        $enabledReport['status'] === 'enabled_runtime_unavailable'
            && !$enabledReport['loadable']
            && count($enabledReport['warnings']) === 1,
        'recorded enabled state still cannot load code before a runtime exists'
    );

    red_addon_registry_test_remove_tree($temporaryRoot . '/project');
    $missingCatalog = red_addon_discover(
        $temporaryRoot . '/project',
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $missingReport = red_addon_registry_catalog_report($connection, $missingCatalog);
    red_addon_registry_test_assert(
        empty($missingReport['valid'])
            && $missingReport['packages'][$packageId]['status'] === 'package_code_missing'
            && !$missingReport['packages'][$packageId]['loadable'],
        'an installed package with missing code fails the catalog closed'
    );

    $deleteRestricted = false;
    try {
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Installations
             WHERE PackageID='redcms.registry-fixture'"
        );
    } catch (Throwable $throwable) {
        $deleteRestricted = true;
    }
    red_addon_registry_test_assert(
        $deleteRestricted
            && red_addon_registry_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='redcms.registry-fixture'"
            ) === '1',
        'migration history prevents silent deletion of its installation record'
    );

    red_addon_registry_test_cleanup($connection, $packageId, $temporaryRoot);
    red_addon_registry_test_assert(
        red_addon_registry_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='redcms.registry-fixture'),
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='redcms.registry-fixture')
             )"
        ) === '0:0' && !file_exists($executionMarker),
        'registry database and filesystem fixtures clean up without package execution'
    );

    printf("Add-on registry self-test passed: %d assertions.\n", $assertions);
} catch (Throwable $throwable) {
    red_addon_registry_test_cleanup($connection, $packageId, $temporaryRoot);
    fwrite(
        STDERR,
        $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n"
    );
    $db->close();
    exit(1);
}

$db->close();
