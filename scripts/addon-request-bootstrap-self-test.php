<?php
/**
 * Disposable database checks for enabled add-on request bootstrap.
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
require_once $projectRoot . '/includes/addon_runtime_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_request)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on request bootstrap self-test refused non-disposable database: ' .
        DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$basePackageId = 'redcms.request-base';
$targetPackageId = 'redcms.request-target';
$packageIds = [$basePackageId, $targetPackageId];
$temporaryRoot = sys_get_temp_dir() .
    '/redcms-addon-request-' . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$executionMarker = $temporaryRoot . '/execution-order';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_request_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_request_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_request_test_remove_tree($path)
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

function red_addon_request_test_cleanup(
    $connection,
    array $packageIds,
    $temporaryRoot
) {
    try {
        foreach (
            [
                'RED_Addon_Activity_Log',
                'RED_Addon_Migrations',
                'RED_Addon_Installations',
            ]
            as $table
        ) {
            foreach ($packageIds as $packageId) {
                $stmt = mysqli_prepare(
                    $connection,
                    'DELETE FROM ' . $table . ' WHERE PackageID=?'
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 's', $packageId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
    } catch (Throwable $throwable) {
        error_log(
            'Add-on request bootstrap cleanup failed: ' .
            $throwable->getMessage()
        );
    }
    red_addon_request_test_remove_tree($temporaryRoot);
}

function red_addon_request_test_package(
    $project,
    $packageId,
    $serviceId,
    $executionMarker,
    array $requiredDependencies
) {
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create request package fixture.');
    }

    $entrypoint = '<?php' . "\n" .
        'return static function (RED_Addon_Runtime_Registry $runtime): void {' .
        "\n    file_put_contents(" . var_export($executionMarker, true) . ', ' .
        var_export($packageId . "\n", true) . ', FILE_APPEND | LOCK_EX);' .
        "\n    \$runtime->registerService(" . var_export($serviceId, true) . ', ' .
        'static function (): string { return ' .
        var_export($packageId, true) . '; });' .
        "\n};\n";
    file_put_contents($directory . '/addon.php', $entrypoint);

    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Request Bootstrap Fixture',
        'description' => 'Disposable enabled request bootstrap fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [$serviceId],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => [
            'required' => $requiredDependencies,
            'optional' => [],
        ],
        'permissions' => [$packageId . '.settings.manage'],
        'settings' => [],
        'migrations' => [],
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
    return $directory;
}

function red_addon_request_test_insert_installation(
    $connection,
    array $snapshot,
    $state
) {
    $stmt = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState,
            InstalledByAdminRecordID, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?, 2147000955, 2147000955)'
    );
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
}

function red_addon_request_test_fingerprint($connection)
{
    return red_addon_request_test_scalar(
        $connection,
        "SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID IN ('redcms.request-base', 'redcms.request-target')),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS(
                '#', PackageID, PackageVersion, PackageType,
                ManifestSHA256, InventorySHA256, LifecycleState,
                InstalledByAdminRecordID, UpdatedByAdminRecordID
             ))), 0)
             FROM RED_Addon_Installations
             WHERE PackageID IN ('redcms.request-base', 'redcms.request-target')),
            (SELECT COUNT(*) FROM RED_Addon_Migrations
             WHERE PackageID IN ('redcms.request-base', 'redcms.request-target')),
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID IN ('redcms.request-base', 'redcms.request-target'))
        )"
    );
}

try {
    red_addon_request_test_cleanup(
        $connection,
        $packageIds,
        $temporaryRoot
    );
    $baseDirectory = red_addon_request_test_package(
        $fixtureProject,
        $basePackageId,
        'request.base',
        $executionMarker,
        []
    );
    $targetDirectory = red_addon_request_test_package(
        $fixtureProject,
        $targetPackageId,
        'request.target',
        $executionMarker,
        [[
            'id' => $basePackageId,
            'version' => '>=1.0 <2.0',
        ]]
    );
    $catalog = red_addon_discover(
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $baseSnapshot = red_addon_registry_snapshot(
        $catalog['packages'][$basePackageId] ?? []
    );
    $targetSnapshot = red_addon_registry_snapshot(
        $catalog['packages'][$targetPackageId] ?? []
    );
    red_addon_request_test_assert(
        !empty($catalog['valid'])
            && is_array($baseSnapshot)
            && is_array($targetSnapshot)
            && !file_exists($executionMarker),
        'validated request fixtures remain unexecuted before registry state is read'
    );

    $uninstalledRuntime = red_addon_runtime_bootstrap(
        $connection,
        $fixtureProject
    );
    red_addon_request_test_assert(
        $uninstalledRuntime['context']->isEmpty()
            && $uninstalledRuntime['order'] === []
            && !file_exists($executionMarker),
        'discovered but uninstalled packages are not loaded'
    );

    red_addon_request_test_insert_installation(
        $connection,
        $baseSnapshot,
        'installed_disabled'
    );
    red_addon_request_test_insert_installation(
        $connection,
        $targetSnapshot,
        'installed_disabled'
    );
    $disabledFingerprint = red_addon_request_test_fingerprint($connection);
    $disabledRuntime = red_addon_runtime_bootstrap(
        $connection,
        $fixtureProject
    );
    red_addon_request_test_assert(
        $disabledRuntime['context']->isEmpty()
            && !file_exists($executionMarker)
            && hash_equals(
                $disabledFingerprint,
                red_addon_request_test_fingerprint($connection)
            ),
        'installed-disabled packages remain unexecuted and registry-read-only'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='enabled'
         WHERE PackageID IN ('redcms.request-base', 'redcms.request-target')"
    );
    $enabledFingerprint = red_addon_request_test_fingerprint($connection);
    $context = red_addon_runtime_request_bootstrap(
        $connection,
        $fixtureProject
    );
    $repeatContext = red_addon_runtime_request_bootstrap(
        $connection,
        $fixtureProject
    );
    $executionOrder = file_exists($executionMarker)
        ? file($executionMarker, FILE_IGNORE_NEW_LINES)
        : [];
    $baseHandler = red_addon_runtime_handler('services', 'request.base');
    $targetHandler = red_addon_runtime_handler('services', 'request.target');
    red_addon_request_test_assert(
        $context->order() === [$basePackageId, $targetPackageId]
            && $executionOrder === [$basePackageId, $targetPackageId]
            && $repeatContext === $context
            && red_addon_runtime_current_context() === $context,
        'enabled packages register once per request in required dependency order'
    );
    red_addon_request_test_assert(
        is_callable($baseHandler)
            && is_callable($targetHandler)
            && $baseHandler() === $basePackageId
            && $targetHandler() === $targetPackageId
            && red_addon_runtime_owner('services', 'request.target')
                === $targetPackageId,
        'core can resolve exact enabled handlers and owners after bootstrap'
    );
    red_addon_request_test_assert(
        hash_equals(
            $enabledFingerprint,
            red_addon_request_test_fingerprint($connection)
        ),
        'successful request bootstrap does not mutate registry or audit state'
    );
    $indexSource = (string) file_get_contents($projectRoot . '/index.php');
    $configSource = (string) file_get_contents(
        $projectRoot . '/includes/config.php'
    );
    $installCliSource = (string) file_get_contents(
        $projectRoot . '/scripts/admin-addon-install.php'
    );
    $preflightCliSource = (string) file_get_contents(
        $projectRoot . '/scripts/admin-addon-enable-preflight.php'
    );
    red_addon_request_test_assert(
        str_contains($indexSource, 'red_addon_runtime_request_bootstrap')
            && str_contains(
                $indexSource,
                'Site extensions are temporarily unavailable.'
            )
            && !str_contains(
                $configSource . $installCliSource . $preflightCliSource,
                'red_addon_runtime_request_bootstrap'
            ),
        'page requests load enabled packages while lifecycle CLIs stay non-executing'
    );

    @unlink($executionMarker);
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET ManifestSHA256='" . str_repeat('0', 64) . "'
         WHERE PackageID='redcms.request-target'"
    );
    $driftFingerprint = red_addon_request_test_fingerprint($connection);
    try {
        red_addon_runtime_bootstrap($connection, $fixtureProject);
        red_addon_request_test_assert(
            false,
            'registry identity drift must stop request bootstrap'
        );
    } catch (RuntimeException $exception) {
        red_addon_request_test_assert(
            str_contains($exception->getMessage(), 'evidence is not current')
                && !file_exists($executionMarker)
                && hash_equals(
                    $driftFingerprint,
                    red_addon_request_test_fingerprint($connection)
                ),
            'registry drift fails before any enabled entry point executes'
        );
    }
    $stmt = mysqli_prepare(
        $connection,
        'UPDATE RED_Addon_Installations
         SET ManifestSHA256=?
         WHERE PackageID=?'
    );
    mysqli_stmt_bind_param(
        $stmt,
        'ss',
        $targetSnapshot['manifestSha256'],
        $targetPackageId
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='installed_disabled'
         WHERE PackageID='redcms.request-base'"
    );
    try {
        red_addon_runtime_bootstrap($connection, $fixtureProject);
        red_addon_request_test_assert(
            false,
            'a disabled required dependency must stop request bootstrap'
        );
    } catch (RuntimeException $exception) {
        red_addon_request_test_assert(
            str_contains($exception->getMessage(), 'dependency check failed')
                && !file_exists($executionMarker),
            'a disabled dependency fails before the dependent executes'
        );
    }
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='enabled'
         WHERE PackageID='redcms.request-base'"
    );

    $offlineDirectory = $temporaryRoot . '/request-target-offline';
    rename($targetDirectory, $offlineDirectory);
    try {
        red_addon_runtime_bootstrap($connection, $fixtureProject);
        red_addon_request_test_assert(
            false,
            'missing enabled package code must stop request bootstrap'
        );
    } catch (RuntimeException $exception) {
        red_addon_request_test_assert(
            (
                str_contains($exception->getMessage(), 'dependency check failed')
                || str_contains($exception->getMessage(), 'catalog is invalid')
            )
                && !file_exists($executionMarker),
            'missing enabled code fails before any remaining package executes'
        );
    }
    rename($offlineDirectory, $targetDirectory);

    red_addon_request_test_cleanup(
        $connection,
        $packageIds,
        $temporaryRoot
    );
    red_addon_request_test_assert(
        red_addon_request_test_fingerprint($connection) === '0:0:0:0'
            && !file_exists($executionMarker)
            && !file_exists($temporaryRoot),
        'request bootstrap database and filesystem fixtures clean up exactly'
    );

    printf(
        "Add-on request bootstrap self-test passed: %d assertions.\n",
        $assertions
    );
} catch (Throwable $throwable) {
    red_addon_request_test_cleanup(
        $connection,
        $packageIds,
        $temporaryRoot
    );
    fwrite(
        STDERR,
        $throwable->getMessage() . ' (after ' . $assertions .
        " assertions)\n"
    );
    $db->close();
    exit(1);
}

$db->close();
