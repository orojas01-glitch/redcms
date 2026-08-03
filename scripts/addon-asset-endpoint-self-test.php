<?php
/**
 * Disposable checks and runtime fixture controls for immutable add-on assets.
 *
 * The default mode validates the endpoint response contract against a temporary
 * first-party package outside the clean starter. Runtime modes create only one
 * attested fixture in a disposable acceptance database so the shell acceptance
 * suite can verify the real index.php HTTP boundary.
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
require_once $projectRoot . '/includes/addon_asset_endpoint_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_asset_endpoint)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on asset endpoint self-test refused non-disposable database: ' .
        DBNAME . "\n"
    );
    exit(65);
}

$mode = $argv[1] ?? '--self-test';
$fixturePackageId = 'redcms.asset-endpoint-fixture';
$fixtureMarkerContents = "redcms-acceptance-asset-endpoint-fixture-v1\n";

function red_addon_asset_endpoint_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_asset_endpoint_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_asset_endpoint_test_remove_tree($path)
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
            throw new RuntimeException('Fixture cleanup refused a nested symbolic link.');
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

function red_addon_asset_endpoint_fixture_assets($includeOversized = false)
{
    $assets = [
        'publicStyle' => [
            'path' => 'assets/public/endpoint.css',
            'location' => 'head',
            'type' => 'style',
            'contentType' => 'text/css; charset=UTF-8',
            'contents' => ".redcms-asset-endpoint-fixture{display:block;}\n",
        ],
        'publicScript' => [
            'path' => 'assets/public/endpoint.js',
            'location' => 'body-end',
            'type' => 'script',
            'contentType' => 'text/javascript; charset=UTF-8',
            'contents' => "window.redcmsAssetEndpointFixture=true;\n",
        ],
        'adminStyle' => [
            'path' => 'assets/admin/endpoint.css',
            'location' => 'head',
            'type' => 'style',
            'contentType' => 'text/css; charset=UTF-8',
            'contents' => ".redcms-asset-endpoint-admin-fixture{display:block;}\n",
        ],
    ];
    if ($includeOversized) {
        $assets['oversizedStyle'] = [
            'path' => 'assets/public/oversized.css',
            'location' => 'head',
            'type' => 'style',
            'contentType' => 'text/css; charset=UTF-8',
            'contents' => str_repeat(
                'x',
                red_addon_asset_delivery_max_bytes() + 1
            ),
        ];
    }
    foreach ($assets as $key => $asset) {
        $assets[$key]['sha256'] = hash('sha256', $asset['contents']);
    }
    return $assets;
}

function red_addon_asset_endpoint_fixture_package(
    $project,
    $packageId,
    $executionMarker,
    $includeOversized = false
) {
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    $publicAssetDirectory = $directory . '/assets/public';
    $adminAssetDirectory = $directory . '/assets/admin';
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

    $assets = red_addon_asset_endpoint_fixture_assets($includeOversized);
    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) .
        ", 'executed');\nthrow new RuntimeException('asset endpoint fixture executed');\n";
    if (file_put_contents($directory . '/addon.php', $entrypoint) === false) {
        throw new RuntimeException('Could not create asset endpoint entrypoint fixture.');
    }
    foreach ($assets as $asset) {
        $path = $directory . '/' . $asset['path'];
        if (file_put_contents($path, $asset['contents']) === false) {
            throw new RuntimeException('Could not create asset endpoint fixture asset.');
        }
    }

    $publicAssets = [];
    $adminAssets = [];
    $integrityFiles = [[
        'path' => 'addon.php',
        'sha256' => hash('sha256', $entrypoint),
    ]];
    foreach ($assets as $asset) {
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
        'name' => 'Asset Endpoint Fixture',
        'description' => 'Disposable static immutable asset endpoint fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => ['redcms.asset-endpoint-fixture/service'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => [
            'required' => [],
            'optional' => [],
        ],
        'permissions' => ['redcms.asset-endpoint-fixture.manage'],
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
    $encodedManifest = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($encodedManifest)
        || file_put_contents($directory . '/addon.json', $encodedManifest) === false
    ) {
        throw new RuntimeException('Could not create asset endpoint manifest fixture.');
    }

    foreach ($assets as $key => $asset) {
        $assets[$key]['filePath'] = (string) realpath(
            $directory . '/' . $asset['path']
        );
    }
    return [
        'directory' => $directory,
        'assets' => $assets,
    ];
}

function red_addon_asset_endpoint_fixture_register(
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
        throw new RuntimeException('Asset endpoint fixture did not validate.');
    }
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState,
            InstalledByAdminRecordID, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?, 2147000963, 2147000963)'
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
        throw new RuntimeException('Could not register asset endpoint fixture.');
    }
    mysqli_stmt_close($statement);
    return $package;
}

function red_addon_asset_endpoint_fixture_cleanup($connection, $packageId, $path)
{
    foreach (
        [
            'RED_Addon_Settings',
            'RED_Addon_Migrations',
            'RED_Addon_Activity_Log',
            'RED_Addon_Installations',
        ]
        as $table
    ) {
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
            throw new RuntimeException('Could not clean fixture registry rows.');
        }
        mysqli_stmt_close($statement);
    }
    red_addon_asset_endpoint_test_remove_tree($path);
}

function red_addon_asset_endpoint_runtime_paths($projectRoot)
{
    return [
        'addonRoot' => $projectRoot . '/addons',
        'packagePath' => $projectRoot . '/addons/redcms/asset-endpoint-fixture',
        'markerPath' => $projectRoot .
            '/addons/.redcms-acceptance-asset-endpoint-fixture',
        'executionMarker' => $projectRoot .
            '/addons/redcms/asset-endpoint-fixture/.asset-endpoint-executed',
    ];
}

function red_addon_asset_endpoint_runtime_marker_valid(array $paths, $contents)
{
    $marker = !is_link($paths['markerPath']) && is_file($paths['markerPath'])
        ? file_get_contents($paths['markerPath'])
        : false;
    return is_string($marker) && hash_equals($contents, $marker);
}

function red_addon_asset_endpoint_runtime_cleanup(
    $connection,
    $projectRoot,
    $packageId,
    $markerContents
) {
    $paths = red_addon_asset_endpoint_runtime_paths($projectRoot);
    if (!red_addon_asset_endpoint_runtime_marker_valid($paths, $markerContents)) {
        return false;
    }
    red_addon_asset_endpoint_fixture_cleanup(
        $connection,
        $packageId,
        $paths['packagePath']
    );
    if (!unlink($paths['markerPath'])) {
        throw new RuntimeException('Could not remove runtime fixture marker.');
    }
    foreach (
        [
            $projectRoot . '/addons/redcms',
            $paths['addonRoot'],
        ] as $directory
    ) {
        if (!is_dir($directory) || is_link($directory)) {
            continue;
        }
        $entries = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
        if ($entries === [] && !rmdir($directory)) {
            throw new RuntimeException('Could not remove empty fixture parent directory.');
        }
    }
    return true;
}

function red_addon_asset_endpoint_runtime_assert_fixture(
    $connection,
    $projectRoot,
    $packageId,
    $markerContents
) {
    $paths = red_addon_asset_endpoint_runtime_paths($projectRoot);
    if (!red_addon_asset_endpoint_runtime_marker_valid($paths, $markerContents)
        || !is_dir($paths['packagePath'])
        || is_link($paths['packagePath'])
        || red_addon_asset_endpoint_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID='" . mysqli_real_escape_string(
                $connection,
                $packageId
            ) . "'"
        ) !== '1'
    ) {
        throw new RuntimeException('Runtime asset endpoint fixture is unavailable.');
    }
    return $paths;
}

function red_addon_asset_endpoint_runtime_setup(
    $connection,
    $projectRoot,
    $packageId,
    $markerContents
) {
    $paths = red_addon_asset_endpoint_runtime_paths($projectRoot);
    if (red_addon_asset_endpoint_runtime_marker_valid($paths, $markerContents)) {
        red_addon_asset_endpoint_runtime_cleanup(
            $connection,
            $projectRoot,
            $packageId,
            $markerContents
        );
    }
    if (file_exists($paths['packagePath'])
        || is_link($paths['packagePath'])
        || red_addon_asset_endpoint_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID='" . mysqli_real_escape_string(
                $connection,
                $packageId
            ) . "'"
        ) !== '0'
    ) {
        throw new RuntimeException('Runtime fixture target is not empty.');
    }
    if (!mkdir($paths['addonRoot'], 0700, true) && !is_dir($paths['addonRoot'])) {
        throw new RuntimeException('Could not create runtime add-on root.');
    }
    if (file_put_contents($paths['markerPath'], $markerContents) === false) {
        throw new RuntimeException('Could not create runtime fixture marker.');
    }
    try {
        $fixture = red_addon_asset_endpoint_fixture_package(
            $projectRoot,
            $packageId,
            $paths['executionMarker']
        );
        red_addon_asset_endpoint_fixture_register(
            $connection,
            $projectRoot,
            $packageId
        );
    } catch (Throwable $throwable) {
        red_addon_asset_endpoint_runtime_cleanup(
            $connection,
            $projectRoot,
            $packageId,
            $markerContents
        );
        throw $throwable;
    }

    $assets = $fixture['assets'];
    return [
        'cssUrl' => red_addon_asset_url(
            $packageId,
            $assets['publicStyle']['path'],
            $assets['publicStyle']['sha256']
        ),
        'scriptUrl' => red_addon_asset_url(
            $packageId,
            $assets['publicScript']['path'],
            $assets['publicScript']['sha256']
        ),
        'adminUrl' => red_addon_asset_url(
            $packageId,
            $assets['adminStyle']['path'],
            $assets['adminStyle']['sha256']
        ),
        'cssSha256' => $assets['publicStyle']['sha256'],
        'cssLength' => strlen($assets['publicStyle']['contents']),
        'executionMarker' => $paths['executionMarker'],
    ];
}

function red_addon_asset_endpoint_runtime_set_state(
    $connection,
    $projectRoot,
    $packageId,
    $markerContents,
    $state
) {
    if (!in_array($state, ['enabled', 'installed_disabled'], true)) {
        throw new InvalidArgumentException('Runtime fixture lifecycle state is invalid.');
    }
    red_addon_asset_endpoint_runtime_assert_fixture(
        $connection,
        $projectRoot,
        $packageId,
        $markerContents
    );
    $statement = mysqli_prepare(
        $connection,
        'UPDATE RED_Addon_Installations SET LifecycleState=? WHERE PackageID=?'
    );
    if (!$statement) {
        throw new RuntimeException('Could not prepare runtime fixture state update.');
    }
    mysqli_stmt_bind_param($statement, 'ss', $state, $packageId);
    if (!mysqli_stmt_execute($statement)
        || mysqli_stmt_affected_rows($statement) !== 1
    ) {
        mysqli_stmt_close($statement);
        throw new RuntimeException('Could not update runtime fixture state.');
    }
    mysqli_stmt_close($statement);
}

function red_addon_asset_endpoint_runtime_file(
    $connection,
    $projectRoot,
    $packageId,
    $markerContents,
    $operation
) {
    $paths = red_addon_asset_endpoint_runtime_assert_fixture(
        $connection,
        $projectRoot,
        $packageId,
        $markerContents
    );
    $assetPath = $paths['packagePath'] . '/assets/public/endpoint.css';
    $assets = red_addon_asset_endpoint_fixture_assets();
    $contents = $operation === 'tamper'
        ? $assets['publicStyle']['contents'] . "/* tampered */\n"
        : $assets['publicStyle']['contents'];
    if (!in_array($operation, ['tamper', 'restore'], true)
        || file_put_contents($assetPath, $contents) === false
    ) {
        throw new RuntimeException('Could not update runtime fixture asset.');
    }
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

try {
    if ($mode === '--runtime-setup') {
        $metadata = red_addon_asset_endpoint_runtime_setup(
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
        red_addon_asset_endpoint_runtime_cleanup(
            $connection,
            $projectRoot,
            $fixturePackageId,
            $fixtureMarkerContents
        );
        exit(0);
    }
    if ($mode === '--runtime-disable') {
        red_addon_asset_endpoint_runtime_set_state(
            $connection,
            $projectRoot,
            $fixturePackageId,
            $fixtureMarkerContents,
            'installed_disabled'
        );
        exit(0);
    }
    if ($mode === '--runtime-enable') {
        red_addon_asset_endpoint_runtime_set_state(
            $connection,
            $projectRoot,
            $fixturePackageId,
            $fixtureMarkerContents,
            'enabled'
        );
        exit(0);
    }
    if ($mode === '--runtime-tamper' || $mode === '--runtime-restore') {
        red_addon_asset_endpoint_runtime_file(
            $connection,
            $projectRoot,
            $fixturePackageId,
            $fixtureMarkerContents,
            $mode === '--runtime-tamper' ? 'tamper' : 'restore'
        );
        exit(0);
    }
    if ($mode !== '--self-test') {
        throw new InvalidArgumentException('Unknown add-on asset endpoint test mode.');
    }

    $assertions = 0;
    $temporaryRoot = sys_get_temp_dir() .
        '/redcms-addon-asset-endpoint-' . bin2hex(random_bytes(8));
    $fixtureProject = $temporaryRoot . '/project';
    $executionMarker = $temporaryRoot . '/executed';
    $packageId = 'redcms.asset-endpoint-fixture';
    try {
        red_addon_asset_endpoint_fixture_cleanup(
            $connection,
            $packageId,
            $temporaryRoot
        );
        $fixture = red_addon_asset_endpoint_fixture_package(
            $fixtureProject,
            $packageId,
            $executionMarker,
            true
        );
        $package = red_addon_asset_endpoint_fixture_register(
            $connection,
            $fixtureProject,
            $packageId
        );
        $assets = $fixture['assets'];
        $publicCssUrl = red_addon_asset_url(
            $packageId,
            $assets['publicStyle']['path'],
            $assets['publicStyle']['sha256']
        );
        $publicScriptUrl = red_addon_asset_url(
            $packageId,
            $assets['publicScript']['path'],
            $assets['publicScript']['sha256']
        );
        $adminCssUrl = red_addon_asset_url(
            $packageId,
            $assets['adminStyle']['path'],
            $assets['adminStyle']['sha256']
        );
        $oversizedCssUrl = red_addon_asset_url(
            $packageId,
            $assets['oversizedStyle']['path'],
            $assets['oversizedStyle']['sha256']
        );

        red_addon_asset_endpoint_test_assert(
            !empty($package['valid'])
                && red_addon_registry_package_report(
                    $connection,
                    $package
                )['status'] === 'enabled_current'
                && !file_exists($executionMarker),
            'the enabled disposable fixture is current without package execution'
        );
        $indexSource = file_get_contents($projectRoot . '/index.php');
        $endpointSource = file_get_contents(
            $projectRoot . '/includes/addon_asset_endpoint_helpers.php'
        );
        $assetEntrypoint = is_string($indexSource)
            ? strpos($indexSource, '$redAddonAssetRequestUri')
            : false;
        $bootstrapEntrypoint = is_string($indexSource)
            ? strpos($indexSource, "require_once __DIR__ . '/includes/bootstrap.php'")
            : false;
        red_addon_asset_endpoint_test_assert(
            is_string($indexSource)
                && is_string($endpointSource)
                && $assetEntrypoint !== false
                && $bootstrapEntrypoint !== false
                && $assetEntrypoint < $bootstrapEntrypoint
                && !str_contains($endpointSource, 'red_addon_runtime_request_bootstrap')
                && !str_contains($endpointSource, 'red_start_session('),
            'the reserved asset endpoint precedes bootstrap and cannot request-load add-ons or sessions'
        );

        $fingerprintBefore = red_addon_asset_endpoint_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':', PackageVersion, ManifestSHA256, InventorySHA256, LifecycleState)
             FROM RED_Addon_Installations
             WHERE PackageID='redcms.asset-endpoint-fixture'"
        );
        ob_start();
        $publicCss = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            $publicCssUrl
        );
        $dispatchOutput = ob_get_clean();
        $fingerprintAfter = red_addon_asset_endpoint_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':', PackageVersion, ManifestSHA256, InventorySHA256, LifecycleState)
             FROM RED_Addon_Installations
             WHERE PackageID='redcms.asset-endpoint-fixture'"
        );
        red_addon_asset_endpoint_test_assert(
            $publicCss['claimed']
                && $publicCss['delivered']
                && $publicCss['status'] === 200
                && $publicCss['headers'] === [
                    'Content-Type' => 'text/css; charset=UTF-8',
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                    'X-Content-Type-Options' => 'nosniff',
                    'Accept-Ranges' => 'none',
                    'Content-Length' => (string) strlen(
                        $assets['publicStyle']['contents']
                    ),
                ]
                && $publicCss['body'] === $assets['publicStyle']['contents']
                && !$publicCss['headOnly']
                && $publicCss['reason'] === 'delivered'
                && $dispatchOutput === ''
                && $fingerprintBefore !== ''
                && $fingerprintBefore === $fingerprintAfter
                && !file_exists($executionMarker),
            'GET serves exact immutable CSS without output before emission, package execution, or registry writes'
        );

        $headCss = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'HEAD',
            $publicCssUrl
        );
        $publicScript = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            $publicScriptUrl
        );
        $adminCss = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            $adminCssUrl
        );
        red_addon_asset_endpoint_test_assert(
            $headCss['claimed']
                && $headCss['delivered']
                && $headCss['status'] === 200
                && $headCss['body'] === ''
                && $headCss['headOnly']
                && ($headCss['headers']['Content-Length'] ?? '') ===
                    (string) strlen($assets['publicStyle']['contents'])
                && $publicScript['delivered']
                && ($publicScript['headers']['Content-Type'] ?? '') ===
                    'text/javascript; charset=UTF-8'
                && $publicScript['body'] === $assets['publicScript']['contents']
                && $adminCss['delivered']
                && ($adminCss['headers']['Content-Type'] ?? '') ===
                    'text/css; charset=UTF-8'
                && $adminCss['body'] === $assets['adminStyle']['contents']
                && !file_exists($executionMarker),
            'HEAD, JavaScript, and administrator-surface CSS preserve exact byte and type boundaries without execution'
        );

        $notMatched = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            '/images/endpoint.css'
        );
        $staleSha256 = $assets['publicStyle']['sha256'];
        $staleSha256[0] = $staleSha256[0] === '0' ? '1' : '0';
        $stale = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            red_addon_asset_url(
                $packageId,
                $assets['publicStyle']['path'],
                $staleSha256
            )
        );
        $extraQuery = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            $publicCssUrl . '&unexpected=1'
        );
        $traversal = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            '/_red/addons/redcms/asset-endpoint-fixture/assets/public/../endpoint.css?v=' .
                $assets['publicStyle']['sha256']
        );
        red_addon_asset_endpoint_test_assert(
            !$notMatched['claimed']
                && $notMatched['status'] === 0
                && !$stale['delivered']
                && $stale['status'] === 404
                && $stale['body'] === "Not found.\n"
                && !$extraQuery['delivered']
                && $extraQuery['status'] === 404
                && !$traversal['delivered']
                && $traversal['status'] === 404
                && !file_exists($executionMarker),
            'nonreserved, stale, noncanonical, and traversal requests disclose no asset bytes'
        );

        $unsupportedMethod = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'POST',
            $publicCssUrl
        );
        $unavailable = red_addon_asset_delivery_dispatch(
            null,
            $fixtureProject,
            'GET',
            $publicCssUrl
        );
        $oversized = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            $oversizedCssUrl
        );
        red_addon_asset_endpoint_test_assert(
            !$unsupportedMethod['delivered']
                && $unsupportedMethod['status'] === 405
                && ($unsupportedMethod['headers']['Allow'] ?? '') === 'GET, HEAD'
                && $unsupportedMethod['body'] === "Method not allowed.\n"
                && !$unavailable['delivered']
                && $unavailable['status'] === 503
                && $unavailable['body'] === "Service temporarily unavailable.\n"
                && !$oversized['delivered']
                && $oversized['status'] === 404
                && $oversized['body'] === "Not found.\n"
                && !file_exists($executionMarker),
            'only GET and HEAD are accepted; registry loss and bounded asset reads fail closed'
        );

        mysqli_query(
            $connection,
            "UPDATE RED_Addon_Installations
             SET LifecycleState='installed_disabled'
             WHERE PackageID='redcms.asset-endpoint-fixture'"
        );
        $disabled = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            $publicCssUrl
        );
        mysqli_query(
            $connection,
            "UPDATE RED_Addon_Installations
             SET LifecycleState='enabled'
             WHERE PackageID='redcms.asset-endpoint-fixture'"
        );
        file_put_contents(
            $assets['publicStyle']['filePath'],
            $assets['publicStyle']['contents'] . "/* tampered */\n"
        );
        $tampered = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'GET',
            $publicCssUrl
        );
        $tamperedHead = red_addon_asset_delivery_dispatch(
            $connection,
            $fixtureProject,
            'HEAD',
            $publicCssUrl
        );
        file_put_contents(
            $assets['publicStyle']['filePath'],
            $assets['publicStyle']['contents']
        );
        red_addon_asset_endpoint_test_assert(
            !$disabled['delivered']
                && $disabled['status'] === 404
                && !$tampered['delivered']
                && $tampered['status'] === 404
                && !$tamperedHead['delivered']
                && $tamperedHead['status'] === 404
                && $tamperedHead['body'] === ''
                && !file_exists($executionMarker),
            'disabled or whole-package-integrity-drifted GET and HEAD assets fail closed without package execution'
        );

        ob_start();
        red_addon_asset_delivery_emit($publicCss);
        $emitted = ob_get_clean();
        $forged = $publicCss;
        $forged['headers']['Content-Length'] = '1';
        $forgedRejected = false;
        try {
            red_addon_asset_delivery_emit($forged);
        } catch (Throwable $throwable) {
            $forgedRejected = true;
        }
        red_addon_asset_endpoint_test_assert(
            $emitted === $assets['publicStyle']['contents']
                && $forgedRejected
                && !file_exists($executionMarker),
            'core emission accepts only self-consistent response evidence'
        );

        printf(
            "Add-on asset endpoint self-test passed: %d assertions.\n",
            $assertions
        );
    } finally {
        red_addon_asset_endpoint_fixture_cleanup(
            $connection,
            $packageId,
            $temporaryRoot
        );
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
} finally {
    mysqli_close($connection);
}

?>
