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
require_once $projectRoot . '/includes/addon_asset_delivery_helpers.php';

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

function red_addon_registry_test_registry_fingerprint($connection, $packageId)
{
    $statement = mysqli_prepare(
        $connection,
        'SELECT CONCAT_WS(\':\', PackageVersion, PackageType, ManifestSHA256,
                InventorySHA256, LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Migrations WHERE PackageID=?))
         FROM RED_Addon_Installations
         WHERE PackageID=?'
    );
    if (!$statement) {
        return '';
    }
    mysqli_stmt_bind_param($statement, 'ss', $packageId, $packageId);
    if (!mysqli_stmt_execute($statement)) {
        mysqli_stmt_close($statement);
        return '';
    }
    $result = mysqli_stmt_get_result($statement);
    $row = $result ? mysqli_fetch_row($result) : null;
    mysqli_stmt_close($statement);
    return $row ? (string) $row[0] : '';
}

function red_addon_registry_test_asset_matches(array $result, array $asset, $surface)
{
    return !empty($result['claimed'])
        && !empty($result['resolved'])
        && $result['packageId'] === 'redcms.registry-fixture'
        && $result['surfaces'] === [$surface]
        && $result['path'] === $asset['path']
        && $result['type'] === $asset['type']
        && $result['location'] === $asset['location']
        && $result['sha256'] === $asset['sha256']
        && $result['contentType'] === $asset['contentType']
        && $result['byteLength'] === strlen($asset['contents'])
        && $result['filePath'] === $asset['filePath']
        && $result['reason'] === 'resolved';
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
    $publicAssetDirectory = $directory . '/assets/public';
    $adminAssetDirectory = $directory . '/assets/admin';
    if (!mkdir($migrationDirectory, 0700, true) && !is_dir($migrationDirectory)) {
        throw new RuntimeException('Could not create registry package fixture.');
    }
    if (!mkdir($publicAssetDirectory, 0700, true)
        && !is_dir($publicAssetDirectory)
    ) {
        throw new RuntimeException('Could not create public asset fixture.');
    }
    if (!mkdir($adminAssetDirectory, 0700, true)
        && !is_dir($adminAssetDirectory)
    ) {
        throw new RuntimeException('Could not create administrator asset fixture.');
    }

    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) .
        ", 'executed');\n";
    $migration = "CREATE TABLE RED_Addon_Registry_Fixture (RecordID int unsigned NOT NULL);\n";
    $publicStyle = ".redcms-registry-fixture{display:block;}\n";
    $publicScript = "window.redcmsRegistryFixture=true;\n";
    $adminStyle = ".redcms-registry-fixture-admin{display:block;}\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    file_put_contents(
        $migrationDirectory . '/2026-07-26-create-fixture.sql',
        $migration
    );
    file_put_contents($publicAssetDirectory . '/registry.css', $publicStyle);
    file_put_contents($publicAssetDirectory . '/registry.js', $publicScript);
    file_put_contents($adminAssetDirectory . '/registry.css', $adminStyle);

    $assets = [
        'publicStyle' => [
            'path' => 'assets/public/registry.css',
            'sha256' => hash('sha256', $publicStyle),
            'location' => 'head',
            'type' => 'style',
            'contentType' => 'text/css; charset=UTF-8',
            'contents' => $publicStyle,
            'filePath' => (string) realpath(
                $publicAssetDirectory . '/registry.css'
            ),
        ],
        'publicScript' => [
            'path' => 'assets/public/registry.js',
            'sha256' => hash('sha256', $publicScript),
            'location' => 'body-end',
            'type' => 'script',
            'contentType' => 'text/javascript; charset=UTF-8',
            'contents' => $publicScript,
            'filePath' => (string) realpath(
                $publicAssetDirectory . '/registry.js'
            ),
        ],
        'adminStyle' => [
            'path' => 'assets/admin/registry.css',
            'sha256' => hash('sha256', $adminStyle),
            'location' => 'head',
            'type' => 'style',
            'contentType' => 'text/css; charset=UTF-8',
            'contents' => $adminStyle,
            'filePath' => (string) realpath(
                $adminAssetDirectory . '/registry.css'
            ),
        ],
    ];

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
            'public' => [[
                'path' => $assets['publicStyle']['path'],
                'sha256' => $assets['publicStyle']['sha256'],
                'location' => $assets['publicStyle']['location'],
            ], [
                'path' => $assets['publicScript']['path'],
                'sha256' => $assets['publicScript']['sha256'],
                'location' => $assets['publicScript']['location'],
            ]],
            'admin' => [[
                'path' => $assets['adminStyle']['path'],
                'sha256' => $assets['adminStyle']['sha256'],
                'location' => $assets['adminStyle']['location'],
            ]],
        ],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => hash('sha256', $entrypoint),
            ], [
                'path' => 'migrations/2026-07-26-create-fixture.sql',
                'sha256' => hash('sha256', $migration),
            ], [
                'path' => $assets['publicStyle']['path'],
                'sha256' => $assets['publicStyle']['sha256'],
            ], [
                'path' => $assets['publicScript']['path'],
                'sha256' => $assets['publicScript']['sha256'],
            ], [
                'path' => $assets['adminStyle']['path'],
                'sha256' => $assets['adminStyle']['sha256'],
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
    return [
        'project' => $project,
        'assets' => $assets,
    ];
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

    $fixture = red_addon_registry_test_package(
        $temporaryRoot,
        $executionMarker
    );
    $fixtureProject = $fixture['project'];
    $fixtureAssets = $fixture['assets'];
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
        $enabledReport['status'] === 'enabled_current'
            && $enabledReport['loadable']
            && $enabledReport['warnings'] === [],
        'recorded enabled state with current evidence is eligible for runtime loading'
    );

    $publicStyleUrl = red_addon_asset_url(
        $packageId,
        $fixtureAssets['publicStyle']['path'],
        $fixtureAssets['publicStyle']['sha256']
    );
    $publicScriptUrl = red_addon_asset_url(
        $packageId,
        $fixtureAssets['publicScript']['path'],
        $fixtureAssets['publicScript']['sha256']
    );
    $adminStyleUrl = red_addon_asset_url(
        $packageId,
        $fixtureAssets['adminStyle']['path'],
        $fixtureAssets['adminStyle']['sha256']
    );
    $notMatched = red_addon_asset_delivery_preflight(
        $connection,
        $fixtureProject,
        '/media/registry.css'
    );
    red_addon_registry_test_assert(
        empty($notMatched['claimed'])
            && empty($notMatched['resolved'])
            && $notMatched['reason'] === 'not_matched'
            && $notMatched['filePath'] === ''
            && !file_exists($executionMarker),
        'non-reserved paths are not claimed and cannot trigger package loading'
    );

    $registryFingerprintBefore = red_addon_registry_test_registry_fingerprint(
        $connection,
        $packageId
    );
    ob_start();
    $publicStyleDelivery = red_addon_asset_delivery_preflight(
        $connection,
        $fixtureProject,
        $publicStyleUrl
    );
    $deliveryOutput = ob_get_clean();
    $registryFingerprintAfter = red_addon_registry_test_registry_fingerprint(
        $connection,
        $packageId
    );
    red_addon_registry_test_assert(
        is_string($publicStyleUrl)
            && red_addon_registry_test_asset_matches(
                $publicStyleDelivery,
                $fixtureAssets['publicStyle'],
                'public'
            )
            && $deliveryOutput === ''
            && $registryFingerprintBefore !== ''
            && $registryFingerprintBefore === $registryFingerprintAfter
            && !file_exists($executionMarker),
        'enabled current public CSS resolves as internal evidence without output, execution, or registry writes'
    );

    $publicScriptDelivery = red_addon_asset_delivery_preflight(
        $connection,
        $fixtureProject,
        $publicScriptUrl
    );
    red_addon_registry_test_assert(
        is_string($publicScriptUrl)
            && red_addon_registry_test_asset_matches(
                $publicScriptDelivery,
                $fixtureAssets['publicScript'],
                'public'
            )
            && !file_exists($executionMarker),
        'enabled current public JavaScript resolves only at body end with the exact content type'
    );

    $adminStyleDelivery = red_addon_asset_delivery_preflight(
        $connection,
        $fixtureProject,
        $adminStyleUrl
    );
    red_addon_registry_test_assert(
        is_string($adminStyleUrl)
            && red_addon_registry_test_asset_matches(
                $adminStyleDelivery,
                $fixtureAssets['adminStyle'],
                'admin'
            )
            && !file_exists($executionMarker),
        'public and administrator assets retain exact separate surface evidence'
    );

    $staleSha256 = $fixtureAssets['publicStyle']['sha256'];
    $staleSha256[0] = $staleSha256[0] === '0' ? '1' : '0';
    $versionMismatch = red_addon_asset_delivery_preflight(
        $connection,
        $fixtureProject,
        red_addon_asset_url(
            $packageId,
            $fixtureAssets['publicStyle']['path'],
            $staleSha256
        )
    );
    red_addon_registry_test_assert(
        !empty($versionMismatch['claimed'])
            && empty($versionMismatch['resolved'])
            && $versionMismatch['reason'] === 'asset_version_mismatch'
            && $versionMismatch['filePath'] === ''
            && !file_exists($executionMarker),
        'a stale or substituted checksum URL returns no asset evidence'
    );

    $malformedDelivery = red_addon_asset_delivery_preflight(
        $connection,
        $fixtureProject,
        $publicStyleUrl . '&unexpected=1'
    );
    $traversalDelivery = red_addon_asset_delivery_preflight(
        $connection,
        $fixtureProject,
        '/_red/addons/redcms/registry-fixture/assets/../registry.css?v=' .
            $fixtureAssets['publicStyle']['sha256']
    );
    red_addon_registry_test_assert(
        !empty($malformedDelivery['claimed'])
            && empty($malformedDelivery['resolved'])
            && $malformedDelivery['reason'] === 'request_invalid'
            && $malformedDelivery['filePath'] === ''
            && !empty($traversalDelivery['claimed'])
            && empty($traversalDelivery['resolved'])
            && $traversalDelivery['reason'] === 'request_invalid'
            && $traversalDelivery['filePath'] === ''
            && !file_exists($executionMarker),
        'reserved delivery URLs reject noncanonical query data and traversal before package validation'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='installed_disabled'
         WHERE PackageID='redcms.registry-fixture'"
    );
    $disabledDelivery = red_addon_asset_delivery_preflight(
        $connection,
        $fixtureProject,
        $publicStyleUrl
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='enabled'
         WHERE PackageID='redcms.registry-fixture'"
    );
    red_addon_registry_test_assert(
        !empty($disabledDelivery['claimed'])
            && empty($disabledDelivery['resolved'])
            && $disabledDelivery['reason'] === 'package_not_enabled'
            && $disabledDelivery['filePath'] === ''
            && !file_exists($executionMarker),
        'installed-disabled packages cannot resolve immutable delivery evidence'
    );

    file_put_contents(
        $fixtureAssets['publicStyle']['filePath'],
        $fixtureAssets['publicStyle']['contents'] . '/* tampered */' . "\n"
    );
    $tamperedDelivery = red_addon_asset_delivery_preflight(
        $connection,
        $fixtureProject,
        $publicStyleUrl
    );
    file_put_contents(
        $fixtureAssets['publicStyle']['filePath'],
        $fixtureAssets['publicStyle']['contents']
    );
    red_addon_registry_test_assert(
        !empty($tamperedDelivery['claimed'])
            && empty($tamperedDelivery['resolved'])
            && $tamperedDelivery['reason'] === 'package_invalid'
            && $tamperedDelivery['filePath'] === ''
            && !file_exists($executionMarker),
        'changed package files fail complete integrity validation before delivery evidence'
    );

    $deliverySource = file_get_contents(
        $projectRoot . '/includes/addon_asset_delivery_helpers.php'
    );
    $forbiddenDeliveryCalls = [
        'header(', 'http_response_code', 'echo ', 'print ', 'include ',
        'require ', 'mysqli_query', 'mysqli_stmt_', 'INSERT ', 'UPDATE ',
        'DELETE ', 'file_put_contents', 'unlink(', 'mkdir(',
    ];
    red_addon_registry_test_assert(
        is_string($deliverySource)
            && array_filter(
                $forbiddenDeliveryCalls,
                static fn ($needle) => str_contains($deliverySource, $needle)
            ) === []
            && !file_exists($executionMarker),
        'delivery preflight has no HTTP response, package execution, or mutation call path'
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
