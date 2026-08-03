<?php
/**
 * Disposable checks and runtime fixture controls for core-owned add-on asset
 * document injection. The fixture is never part of the clean starter.
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
require_once $projectRoot . '/includes/addon_asset_injection_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_asset_injection)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on asset injection self-test refused non-disposable database: ' .
        DBNAME . "\n"
    );
    exit(65);
}

$mode = $argv[1] ?? '--self-test';
$fixturePackageId = 'redcms.asset-injection-fixture';
$fixtureMarkerContents = "redcms-acceptance-asset-injection-fixture-v1\n";

function red_addon_asset_injection_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_asset_injection_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_asset_injection_test_remove_tree($path)
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_link($path)) {
        throw new RuntimeException('Fixture cleanup refused a symbolic link.');
    }
    if (!is_dir($path)) {
        if (!unlink($path)) {
            throw new RuntimeException('Could not remove fixture file.');
        }
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entryPath = $entry->getPathname();
        if ($entry->isLink()) {
            throw new RuntimeException(
                'Fixture cleanup refused a nested symbolic link.'
            );
        }
        if ($entry->isDir()) {
            if (!rmdir($entryPath)) {
                throw new RuntimeException('Could not remove fixture directory.');
            }
        } elseif (!unlink($entryPath)) {
            throw new RuntimeException('Could not remove fixture file.');
        }
    }
    if (!rmdir($path)) {
        throw new RuntimeException('Could not remove fixture root.');
    }
}

function red_addon_asset_injection_fixture_assets($invalidLocation = false)
{
    $assets = [
        'publicStyle' => [
            'path' => 'assets/public/injection.css',
            'location' => 'head',
            'contents' => ".redcms-asset-injection-public{display:block;}\n",
        ],
        'publicScript' => [
            'path' => 'assets/public/injection.js',
            'location' => $invalidLocation ? 'head' : 'body-end',
            'contents' => "window.redcmsAssetInjectionPublic=true;\n",
        ],
        'adminStyle' => [
            'path' => 'assets/admin/injection.css',
            'location' => 'head',
            'contents' => ".redcms-asset-injection-admin{display:block;}\n",
        ],
        'adminScript' => [
            'path' => 'assets/admin/injection.js',
            'location' => 'body-end',
            'contents' => "window.redcmsAssetInjectionAdmin=true;\n",
        ],
    ];
    foreach ($assets as $key => $asset) {
        $assets[$key]['sha256'] = hash('sha256', $asset['contents']);
    }
    return $assets;
}

function red_addon_asset_injection_fixture_package(
    $project,
    $packageId,
    $executionMarker,
    $invalidLocation = false
) {
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    foreach (['assets/public', 'assets/admin'] as $relativeDirectory) {
        $target = $directory . '/' . $relativeDirectory;
        if (!mkdir($target, 0700, true) && !is_dir($target)) {
            throw new RuntimeException('Could not create asset injection fixture.');
        }
    }

    $serviceId = $packageId . '/service';
    $entrypoint = "<?php\nreturn static function " .
        '(RED_Addon_Runtime_Registry $runtime): void {' . "\n    " .
        'file_put_contents(' . var_export($executionMarker, true) . ', ' .
        var_export('runtime:' . $packageId . "\n", true) .
        ', FILE_APPEND | LOCK_EX);' . "\n    " .
        '$runtime->registerService(' . var_export($serviceId, true) .
        ', static function (): string { return ' .
        var_export($packageId, true) . '; });' . "\n};\n";
    if (file_put_contents($directory . '/addon.php', $entrypoint) === false) {
        throw new RuntimeException('Could not create fixture entry point.');
    }

    $assets = red_addon_asset_injection_fixture_assets($invalidLocation);
    $publicAssets = [];
    $adminAssets = [];
    $integrityFiles = [[
        'path' => 'addon.php',
        'sha256' => hash('sha256', $entrypoint),
    ]];
    foreach ($assets as $asset) {
        $path = $directory . '/' . $asset['path'];
        if (file_put_contents($path, $asset['contents']) === false) {
            throw new RuntimeException('Could not write fixture asset.');
        }
        $declaration = [
            'path' => $asset['path'],
            'sha256' => $asset['sha256'],
            'location' => $asset['location'],
        ];
        if (str_starts_with($asset['path'], 'assets/public/')) {
            $publicAssets[] = $declaration;
        } else {
            $adminAssets[] = $declaration;
        }
        $integrityFiles[] = [
            'path' => $asset['path'],
            'sha256' => $asset['sha256'],
        ];
    }

    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Asset Injection Fixture',
        'description' => 'Disposable core-owned asset injection fixture.',
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
            'required' => [],
            'optional' => [],
        ],
        'permissions' => [$packageId . '.manage'],
        'settings' => [],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => [
            'public' => $publicAssets,
            'admin' => $adminAssets,
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
    $encoded = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($encoded)
        || file_put_contents($directory . '/addon.json', $encoded) === false
    ) {
        throw new RuntimeException('Could not write fixture manifest.');
    }

    foreach ($assets as $key => $asset) {
        $assets[$key]['filePath'] = $directory . '/' . $asset['path'];
    }
    return [
        'path' => $directory,
        'assets' => $assets,
    ];
}

function red_addon_asset_injection_fixture_register(
    $connection,
    $project,
    $packageId
) {
    $package = red_addon_validate_manifest(
        $packageId,
        $project,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $snapshot = red_addon_registry_snapshot($package);
    if (empty($package['valid']) || !is_array($snapshot)) {
        throw new RuntimeException('Asset injection fixture validation failed.');
    }
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState,
            InstalledByAdminRecordID, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?, 2147000964, 2147000964)'
    );
    if (!$statement) {
        throw new RuntimeException('Could not prepare fixture registry insert.');
    }
    $state = 'enabled';
    mysqli_stmt_bind_param(
        $statement,
        'ssssss',
        $snapshot['id'],
        $snapshot['version'],
        $snapshot['type'],
        $snapshot['manifestSha256'],
        $snapshot['inventorySha256'],
        $state
    );
    if (!mysqli_stmt_execute($statement)) {
        mysqli_stmt_close($statement);
        throw new RuntimeException('Could not register asset injection fixture.');
    }
    mysqli_stmt_close($statement);
    return $package;
}

function red_addon_asset_injection_fixture_cleanup(
    $connection,
    array $packageIds,
    $path
) {
    foreach (
        [
            'RED_Addon_Settings',
            'RED_Addon_Migrations',
            'RED_Addon_Activity_Log',
            'RED_Addon_Installations',
        ]
        as $table
    ) {
        foreach ($packageIds as $packageId) {
            $statement = mysqli_prepare(
                $connection,
                'DELETE FROM ' . $table . ' WHERE PackageID=?'
            );
            if (!$statement) {
                throw new RuntimeException('Could not prepare fixture cleanup.');
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                throw new RuntimeException('Could not remove fixture rows.');
            }
            mysqli_stmt_close($statement);
        }
    }
    red_addon_asset_injection_test_remove_tree($path);
}

function red_addon_asset_injection_fixture_fingerprint(
    $connection,
    array $packageIds
) {
    $escaped = array_map(
        static function ($packageId) use ($connection) {
            return "'" . mysqli_real_escape_string($connection, $packageId) . "'";
        },
        $packageIds
    );
    $list = implode(',', $escaped);
    return red_addon_asset_injection_test_scalar(
        $connection,
        "SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID IN ($list)),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS(
                '#', PackageID, PackageVersion, PackageType,
                ManifestSHA256, InventorySHA256, LifecycleState,
                InstalledByAdminRecordID, UpdatedByAdminRecordID
             ))), 0) FROM RED_Addon_Installations WHERE PackageID IN ($list)),
            (SELECT COUNT(*) FROM RED_Addon_Migrations
             WHERE PackageID IN ($list)),
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID IN ($list))
        )"
    );
}

function red_addon_asset_injection_runtime_paths($projectRoot)
{
    $token = substr(hash('sha256', (string) realpath($projectRoot)), 0, 24);
    $temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') .
        '/redcms-asset-injection-runtime-' . $token;
    return [
        'addonRoot' => $projectRoot . '/addons',
        'packagePath' => $projectRoot . '/addons/redcms/asset-injection-fixture',
        'markerPath' => $temporaryRoot . '/fixture-marker',
        'executionMarker' => $temporaryRoot . '/runtime-executed',
        'temporaryRoot' => $temporaryRoot,
    ];
}

function red_addon_asset_injection_runtime_marker_valid(array $paths, $contents)
{
    $marker = !is_link($paths['markerPath']) && is_file($paths['markerPath'])
        ? file_get_contents($paths['markerPath'])
        : false;
    return is_string($marker) && hash_equals($contents, $marker);
}

function red_addon_asset_injection_runtime_remove_empty_parents(array $paths)
{
    foreach (
        [
            dirname($paths['packagePath']),
            $paths['addonRoot'],
        ]
        as $directory
    ) {
        if (!is_dir($directory) || is_link($directory)) {
            continue;
        }
        $entries = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
        if ($entries === [] && !rmdir($directory)) {
            throw new RuntimeException('Could not remove empty fixture parent.');
        }
    }
}

function red_addon_asset_injection_runtime_cleanup(
    $connection,
    $projectRoot,
    $packageId,
    $markerContents
) {
    $paths = red_addon_asset_injection_runtime_paths($projectRoot);
    if (!red_addon_asset_injection_runtime_marker_valid($paths, $markerContents)) {
        return false;
    }
    red_addon_asset_injection_fixture_cleanup(
        $connection,
        [$packageId],
        $paths['packagePath']
    );
    red_addon_asset_injection_runtime_remove_empty_parents($paths);
    red_addon_asset_injection_test_remove_tree($paths['temporaryRoot']);
    return true;
}

function red_addon_asset_injection_runtime_assert_fixture(
    $connection,
    $projectRoot,
    $packageId,
    $markerContents
) {
    $paths = red_addon_asset_injection_runtime_paths($projectRoot);
    if (!red_addon_asset_injection_runtime_marker_valid($paths, $markerContents)
        || !is_dir($paths['packagePath'])
        || is_link($paths['packagePath'])
        || red_addon_asset_injection_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Installations WHERE PackageID='" .
                mysqli_real_escape_string($connection, $packageId) . "'"
        ) !== '1'
    ) {
        throw new RuntimeException('Runtime asset injection fixture is unavailable.');
    }
    return $paths;
}

function red_addon_asset_injection_runtime_setup(
    $connection,
    $projectRoot,
    $packageId,
    $markerContents
) {
    $paths = red_addon_asset_injection_runtime_paths($projectRoot);
    if (red_addon_asset_injection_runtime_marker_valid($paths, $markerContents)) {
        red_addon_asset_injection_runtime_cleanup(
            $connection,
            $projectRoot,
            $packageId,
            $markerContents
        );
    }
    if (file_exists($paths['packagePath'])
        || is_link($paths['packagePath'])
        || file_exists($paths['temporaryRoot'])
        || red_addon_asset_injection_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Installations WHERE PackageID='" .
                mysqli_real_escape_string($connection, $packageId) . "'"
        ) !== '0'
    ) {
        throw new RuntimeException('Runtime fixture target is not empty.');
    }
    if (!mkdir($paths['temporaryRoot'], 0700, true)
        || file_put_contents($paths['markerPath'], $markerContents) === false
    ) {
        throw new RuntimeException('Could not create runtime fixture marker.');
    }
    try {
        $fixture = red_addon_asset_injection_fixture_package(
            $projectRoot,
            $packageId,
            $paths['executionMarker']
        );
        red_addon_asset_injection_fixture_register(
            $connection,
            $projectRoot,
            $packageId
        );
    } catch (Throwable $throwable) {
        red_addon_asset_injection_runtime_cleanup(
            $connection,
            $projectRoot,
            $packageId,
            $markerContents
        );
        throw $throwable;
    }

    $assets = $fixture['assets'];
    return [
        'publicCssUrl' => red_addon_asset_url(
            $packageId,
            $assets['publicStyle']['path'],
            $assets['publicStyle']['sha256']
        ),
        'publicScriptUrl' => red_addon_asset_url(
            $packageId,
            $assets['publicScript']['path'],
            $assets['publicScript']['sha256']
        ),
        'adminCssUrl' => red_addon_asset_url(
            $packageId,
            $assets['adminStyle']['path'],
            $assets['adminStyle']['sha256']
        ),
        'adminScriptUrl' => red_addon_asset_url(
            $packageId,
            $assets['adminScript']['path'],
            $assets['adminScript']['sha256']
        ),
        'executionMarker' => $paths['executionMarker'],
    ];
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

try {
    if ($mode === '--runtime-setup') {
        $metadata = red_addon_asset_injection_runtime_setup(
            $connection,
            $projectRoot,
            $fixturePackageId,
            $fixtureMarkerContents
        );
        foreach ($metadata as $key => $value) {
            printf('%s=%s' . "\n", $key, $value);
        }
        exit(0);
    }
    if ($mode === '--runtime-cleanup') {
        red_addon_asset_injection_runtime_cleanup(
            $connection,
            $projectRoot,
            $fixturePackageId,
            $fixtureMarkerContents
        );
        exit(0);
    }
    if ($mode !== '--self-test') {
        throw new InvalidArgumentException('Unknown add-on asset injection mode.');
    }

    $assertions = 0;
    $validPackageId = $fixturePackageId;
    $invalidPackageId = 'redcms.asset-injection-invalid';
    $packageIds = [$validPackageId, $invalidPackageId];
    $temporaryRoot = rtrim(sys_get_temp_dir(), '/\\') .
        '/redcms-addon-asset-injection-' . bin2hex(random_bytes(8));
    $fixtureProject = $temporaryRoot . '/project';
    $executionMarker = $temporaryRoot . '/executed';
    try {
        red_addon_asset_injection_fixture_cleanup(
            $connection,
            $packageIds,
            $temporaryRoot
        );
        $fixture = red_addon_asset_injection_fixture_package(
            $fixtureProject,
            $validPackageId,
            $executionMarker
        );
        red_addon_asset_injection_fixture_register(
            $connection,
            $fixtureProject,
            $validPackageId
        );
        $assets = $fixture['assets'];
        $publicCssUrl = red_addon_asset_url(
            $validPackageId,
            $assets['publicStyle']['path'],
            $assets['publicStyle']['sha256']
        );
        $publicScriptUrl = red_addon_asset_url(
            $validPackageId,
            $assets['publicScript']['path'],
            $assets['publicScript']['sha256']
        );
        $adminCssUrl = red_addon_asset_url(
            $validPackageId,
            $assets['adminStyle']['path'],
            $assets['adminStyle']['sha256']
        );
        $adminScriptUrl = red_addon_asset_url(
            $validPackageId,
            $assets['adminScript']['path'],
            $assets['adminScript']['sha256']
        );
        $fingerprintBefore = red_addon_asset_injection_fixture_fingerprint(
            $connection,
            [$validPackageId]
        );

        $invalidContext = red_addon_asset_injection_plan(
            $connection,
            $fixtureProject,
            'yes'
        );
        red_addon_asset_injection_test_assert(
            empty($invalidContext['valid'])
                && $invalidContext['assets'] === []
                && red_addon_asset_injection_plan_html($invalidContext, 'head') === '',
            'a non-boolean administrator context produces no document markup'
        );

        $publicPlan = red_addon_asset_injection_plan(
            $connection,
            $fixtureProject,
            false
        );
        $publicHead = red_addon_asset_injection_plan_html($publicPlan, 'head');
        $publicBodyEnd = red_addon_asset_injection_plan_html(
            $publicPlan,
            'body-end'
        );
        red_addon_asset_injection_test_assert(
            red_addon_asset_injection_plan_is_valid($publicPlan)
                && count($publicPlan['packages']) === 1
                && count($publicPlan['assets']) === 2
                && $publicHead === '<link rel="stylesheet" href="' .
                    $publicCssUrl . '">' . "\n"
                && $publicBodyEnd === '<script src="' . $publicScriptUrl .
                    '" defer></script>' . "\n"
                && !str_contains($publicHead . $publicBodyEnd, $adminCssUrl)
                && !str_contains($publicHead . $publicBodyEnd, $adminScriptUrl),
            'current enabled public assets form one canonical public-only plan'
        );

        $adminPlan = red_addon_asset_injection_plan(
            $connection,
            $fixtureProject,
            true
        );
        $adminHead = red_addon_asset_injection_plan_html($adminPlan, 'head');
        $adminBodyEnd = red_addon_asset_injection_plan_html(
            $adminPlan,
            'body-end'
        );
        red_addon_asset_injection_test_assert(
            red_addon_asset_injection_plan_is_valid($adminPlan)
                && count($adminPlan['assets']) === 4
                && $adminHead === '<link rel="stylesheet" href="' .
                    $publicCssUrl . '">' . "\n" .
                    '<link rel="stylesheet" href="' . $adminCssUrl . '">' . "\n"
                && $adminBodyEnd === '<script src="' . $publicScriptUrl .
                    '" defer></script>' . "\n" .
                    '<script src="' . $adminScriptUrl .
                    '" defer></script>' . "\n",
            'authenticated plans keep public and administrator assets deterministic'
        );

        $headDocument = '<!doctype html><html><head><title>Fixture</title></head><body>';
        $bodyDocument = '<main>Fixture</main></body></html>';
        red_addon_asset_injection_test_assert(
            red_addon_asset_injection_insert_document(
                $headDocument,
                $publicPlan,
                'head'
            ) === '<!doctype html><html><head><title>Fixture</title>' .
                $publicHead . '</head><body>'
                && red_addon_asset_injection_insert_document(
                    $bodyDocument,
                    $adminPlan,
                    'body-end'
                ) === '<main>Fixture</main>' . $adminBodyEnd . '</body></html>',
            'core inserts escaped tags only immediately before unambiguous document boundaries'
        );

        $forgedPlan = $adminPlan;
        $forgedPlan['planSha256'] = str_repeat('0', 64);
        red_addon_asset_injection_test_assert(
            red_addon_asset_injection_plan_html($forgedPlan, 'head') === ''
                && red_addon_asset_injection_insert_document(
                    $headDocument,
                    $adminPlan,
                    'body-end'
                ) === $headDocument
                && red_addon_asset_injection_insert_document(
                    '<html><head></head><head></head></html>',
                    $adminPlan,
                    'head'
                ) === '<html><head></head><head></head></html>',
            'forged plans or ambiguous document boundaries leave markup unchanged'
        );

        red_addon_asset_injection_test_assert(
            !file_exists($executionMarker)
                && $fingerprintBefore === red_addon_asset_injection_fixture_fingerprint(
                    $connection,
                    [$validPackageId]
                ),
            'injection planning reads current evidence without package execution or registry mutation'
        );

        mysqli_query(
            $connection,
            "UPDATE RED_Addon_Installations SET LifecycleState='installed_disabled' " .
                "WHERE PackageID='" .
                mysqli_real_escape_string($connection, $validPackageId) . "'"
        );
        $disabledPlan = red_addon_asset_injection_plan(
            $connection,
            $fixtureProject,
            false
        );
        mysqli_query(
            $connection,
            "UPDATE RED_Addon_Installations SET LifecycleState='enabled' " .
                "WHERE PackageID='" .
                mysqli_real_escape_string($connection, $validPackageId) . "'"
        );
        red_addon_asset_injection_test_assert(
            red_addon_asset_injection_plan_is_valid($disabledPlan)
                && $disabledPlan['assets'] === []
                && red_addon_asset_injection_plan_html($disabledPlan, 'head') === '',
            'disabled packages cannot contribute an injected asset tag'
        );

        file_put_contents(
            $assets['publicStyle']['filePath'],
            $assets['publicStyle']['contents'] . "/* tampered */\n"
        );
        $tamperedPlan = red_addon_asset_injection_plan(
            $connection,
            $fixtureProject,
            false
        );
        file_put_contents(
            $assets['publicStyle']['filePath'],
            $assets['publicStyle']['contents']
        );
        red_addon_asset_injection_test_assert(
            empty($tamperedPlan['valid'])
                && $tamperedPlan['assets'] === []
                && red_addon_asset_injection_plan_html($tamperedPlan, 'head') === '',
            'integrity drift fails closed before document markup is generated'
        );

        red_addon_asset_injection_fixture_package(
            $fixtureProject,
            $invalidPackageId,
            $executionMarker,
            true
        );
        red_addon_asset_injection_fixture_register(
            $connection,
            $fixtureProject,
            $invalidPackageId
        );
        $mixedPlan = red_addon_asset_injection_plan(
            $connection,
            $fixtureProject,
            false
        );
        red_addon_asset_injection_test_assert(
            empty($mixedPlan['valid'])
                && $mixedPlan['assets'] === []
                && red_addon_asset_injection_plan_html($mixedPlan, 'head') === '',
            'one enabled package with an invalid surface prevents every partial injection'
        );

        $injectionSource = file_get_contents(
            $projectRoot . '/includes/addon_asset_injection_helpers.php'
        );
        $indexSource = file_get_contents($projectRoot . '/index.php');
        $runtimeAt = is_string($indexSource)
            ? strpos($indexSource, 'red_addon_runtime_request_bootstrap(')
            : false;
        $injectionAt = is_string($indexSource)
            ? strpos($indexSource, 'red_addon_asset_injection_plan(')
            : false;
        red_addon_asset_injection_test_assert(
            is_string($injectionSource)
                && is_string($indexSource)
                && !str_contains(
                    $injectionSource,
                    'red_addon_runtime_request_bootstrap'
                )
                && !str_contains(
                    $injectionSource,
                    'red_addon_runtime_register_package'
                )
                && $runtimeAt !== false
                && $injectionAt !== false
                && $runtimeAt < $injectionAt
                && str_contains(
                    $indexSource,
                    "red_addon_asset_injection_insert_document("
                )
                && !file_exists($executionMarker),
            'the core document path plans after normal runtime bootstrap without invoking package code itself'
        );

        red_addon_asset_injection_fixture_cleanup(
            $connection,
            $packageIds,
            $temporaryRoot
        );
        red_addon_asset_injection_test_assert(
            red_addon_asset_injection_fixture_fingerprint(
                $connection,
                $packageIds
            ) === '0:0:0:0'
                && !file_exists($executionMarker)
                && !file_exists($temporaryRoot),
            'injection fixture database and filesystem state clean up exactly'
        );

        printf(
            "Add-on asset injection self-test passed: %d assertions.\n",
            $assertions
        );
    } finally {
        if (file_exists($temporaryRoot) || is_link($temporaryRoot)) {
            red_addon_asset_injection_fixture_cleanup(
                $connection,
                $packageIds,
                $temporaryRoot
            );
        }
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
} finally {
    $db->close();
}

?>
