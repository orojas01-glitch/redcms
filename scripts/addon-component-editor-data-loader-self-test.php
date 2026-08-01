<?php
/**
 * Disposable checks for bounded add-on component editor data loading.
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
require_once $projectRoot
    . '/includes/addon_component_editor_data_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_editor_data)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Component editor data-loader self-test refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$adminRecordId = 2147000983;
$contentRecordId = 2147000984;
$packageId = 'redcms.editor-data-fixture';
$componentId = 'redcms.editor-data-fixture/item';
$permission = 'fixture.editor-data.view';
$packageTable = 'RED_Addon_Component_Editor_Data_Fixture';
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-addon-editor-data-'
    . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$executionMarker = $temporaryRoot . '/execution-marker';
$loaderMarker = $temporaryRoot . '/loader-marker';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_editor_data_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_editor_data_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_editor_data_test_remove_tree($path)
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

function red_addon_editor_data_test_cleanup(
    $connection,
    $adminRecordId,
    $contentRecordId,
    $packageId,
    $packageTable,
    $temporaryRoot
) {
    try {
        if (preg_match('/\ARED_Addon_[A-Za-z0-9_]+\z/', $packageTable) === 1) {
            mysqli_query(
                $connection,
                'DROP TABLE IF EXISTS `' . $packageTable . '`'
            );
        }
        $statement = mysqli_prepare(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID=?'
        );
        if ($statement) {
            mysqli_stmt_bind_param($statement, 'i', $contentRecordId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
        }
        foreach (
            [
                'RED_Addon_Activity_Log',
                'RED_Addon_Migrations',
                'RED_Addon_Installations',
            ] as $table
        ) {
            $statement = mysqli_prepare(
                $connection,
                'DELETE FROM ' . $table . ' WHERE PackageID=?'
            );
            if ($statement) {
                mysqli_stmt_bind_param($statement, 's', $packageId);
                mysqli_stmt_execute($statement);
                mysqli_stmt_close($statement);
            }
        }
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
                . (int) $adminRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID='
                . (int) $adminRecordId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID='
                . (int) $adminRecordId
        );
    } catch (Throwable $throwable) {
        error_log(
            'Component editor data-loader cleanup failed: '
                . $throwable->getMessage()
        );
    }
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_editor_data_test_remove_tree($temporaryRoot);
}

function red_addon_editor_data_test_insert_parent(
    $connection,
    $contentRecordId,
    $componentId
) {
    $result = mysqli_query(
        $connection,
        'SELECT * FROM RED_Articles ORDER BY RecordID LIMIT 1'
    );
    $row = $result ? mysqli_fetch_assoc($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    if (!is_array($row) || $row === []) {
        throw new RuntimeException('Could not read the disposable article seed.');
    }
    $row['RecordID'] = (string) $contentRecordId;
    $row['Component'] = $componentId;
    $row['Alias'] = 'codex-addon-editor-data-fixture';
    $row['Title'] = 'Disposable component editor data fixture';
    $row['StartDate'] = '1970-01-01 00:00:01';
    $row['EventDate'] = '1970-01-01 00:00:01';
    $row['ExpDate'] = '2099-12-31 23:59:59';
    $columns = array_keys($row);
    $values = [];
    foreach (array_values($row) as $value) {
        $values[] = $value === null
            ? 'NULL'
            : "'" . mysqli_real_escape_string($connection, (string) $value) . "'";
    }
    $sql = 'INSERT INTO RED_Articles (`'
        . implode('`,`', $columns)
        . '`) VALUES ('
        . implode(',', $values)
        . ')';
    if (!mysqli_query($connection, $sql)) {
        throw new RuntimeException('Could not create the disposable parent.');
    }
}

function red_addon_editor_data_test_write_fixture(
    $project,
    $packageId,
    $componentId,
    $permission,
    $packageTable,
    $executionMarker,
    $loaderMarker
) {
    [$vendor, $package] = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $vendor . '/' . $package;
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create data-loader fixture.');
    }
    $entrypoint = '<?php' . "\n"
        . 'return static function (RED_Addon_Runtime_Registry $runtime): void {'
        . "\n    file_put_contents(" . var_export($executionMarker, true)
        . ', ' . var_export("registered\n", true)
        . ', FILE_APPEND | LOCK_EX);'
        . "\n    \$runtime->registerComponent(" . var_export($componentId, true)
        . ', static function (array $context): array {'
        . "\n        return ['title' => 'Fixture', 'summary' => 'Fixture'];"
        . "\n    });"
        . "\n    \$runtime->registerComponentDataLoader("
        . var_export($componentId, true)
        . ', static function ($connection, array $context): array {'
        . "\n        if (array_keys(\$context) !== ['component', 'contentRecordId']"
        . "\n            || \$context['component'] !== "
        . var_export($componentId, true)
        . "\n            || !is_int(\$context['contentRecordId'])) {"
        . "\n            throw new RuntimeException('unexpected loader context');"
        . "\n        }"
        . "\n        file_put_contents(" . var_export($loaderMarker, true)
        . ', ' . var_export("loaded\n", true)
        . ', FILE_APPEND | LOCK_EX);'
        . "\n        \$statement = mysqli_prepare(\$connection, "
        . var_export(
            'SELECT Title, Quantity, Mode FROM `' . $packageTable
                . '` WHERE ContentRecordID=? LIMIT 1',
            true
        )
        . ');'
        . "\n        mysqli_stmt_bind_param(\$statement, 'i', \$context['contentRecordId']);"
        . "\n        mysqli_stmt_execute(\$statement);"
        . "\n        \$result = mysqli_stmt_get_result(\$statement);"
        . "\n        \$row = \$result ? mysqli_fetch_assoc(\$result) : null;"
        . "\n        mysqli_stmt_close(\$statement);"
        . "\n        if (!is_array(\$row)) { throw new RuntimeException('missing data'); }"
        . "\n        if (\$row['Mode'] === 'emit') { echo 'unsafe-loader-output'; }"
        . "\n        if (\$row['Mode'] === 'throw') { throw new RuntimeException('private loader failure'); }"
        . "\n        if (\$row['Mode'] === 'nested') { ob_start(); }"
        . "\n        if (\$row['Mode'] === 'invalid') { return ['title' => \$row['Title'], 'quantity' => (int) \$row['Quantity'], 'extra' => 'no']; }"
        . "\n        return ['title' => \$row['Title'], 'quantity' => (int) \$row['Quantity']];"
        . "\n    });"
        . "\n};\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Component Editor Data Fixture',
        'description' => 'Disposable bounded package data-loader fixture.',
        'version' => '1.0.0',
        'type' => 'component',
        'compatibility' => ['cms' => '>=5.1 <6.0', 'php' => '>=8.2 <9.0'],
        'provides' => [
            'components' => [$componentId],
            'services' => [],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$permission],
        'componentEditors' => [[
            'component' => $componentId,
            'label' => 'Data fixture',
            'description' => 'Load bounded package-owned values.',
            'icon' => 'package',
            'permissions' => [
                'create' => $permission,
                'view' => $permission,
                'edit' => $permission,
                'delete' => $permission,
                'publish' => $permission,
                'restore' => $permission,
            ],
            'fields' => [
                [
                    'key' => 'title',
                    'label' => 'Title',
                    'type' => 'text',
                    'required' => true,
                    'maxLength' => 120,
                ],
                [
                    'key' => 'quantity',
                    'label' => 'Quantity',
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 0,
                    'maximum' => 100,
                ],
            ],
        ]],
        'settings' => [],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
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
}

function red_addon_editor_data_test_fingerprint(
    $connection,
    $adminRecordId,
    $contentRecordId,
    $packageId,
    $packageTable
) {
    $escapedPackage = mysqli_real_escape_string($connection, $packageId);
    return red_addon_editor_data_test_scalar(
        $connection,
        "SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID=" . (int) $adminRecordId . "),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities
             WHERE AdminRecordID=" . (int) $adminRecordId . "),
            (SELECT COUNT(*) FROM RED_Articles
             WHERE RecordID=" . (int) $contentRecordId . "),
            (SELECT COALESCE(SUM(CRC32(CONCAT_WS('#', Title, Quantity, Mode))), 0)
             FROM `" . $packageTable . "`
             WHERE ContentRecordID=" . (int) $contentRecordId . "),
            (SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID='" . $escapedPackage . "'),
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID='" . $escapedPackage . "')
         )"
    );
}

function red_addon_editor_data_test_loader_count($loaderMarker)
{
    if (!is_file($loaderMarker)) {
        return 0;
    }
    $lines = file($loaderMarker, FILE_IGNORE_NEW_LINES);
    return is_array($lines) ? count($lines) : 0;
}

try {
    red_addon_editor_data_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        $packageTable,
        $temporaryRoot
    );
    red_addon_editor_data_test_write_fixture(
        $fixtureProject,
        $packageId,
        $componentId,
        $permission,
        $packageTable,
        $executionMarker,
        $loaderMarker
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][$packageId] ?? null;
    $snapshot = is_array($package)
        ? red_addon_registry_snapshot($package)
        : null;
    red_addon_editor_data_test_assert(
        !empty($catalog['valid'])
            && is_array($package)
            && is_array($snapshot)
            && !file_exists($executionMarker)
            && !file_exists($loaderMarker),
        'trust discovery remains non-executing for a declared data loader'
    );

    $missingRegistry = new RED_Addon_Runtime_Registry(
        $packageId,
        $package['manifest']
    );
    $missingRegistry->registerComponent(
        $componentId,
        static function (): array {
            return ['title' => '', 'summary' => ''];
        }
    );
    try {
        $missingRegistry->assertComplete();
        red_addon_editor_data_test_assert(
            false,
            'a declared editor must require one data loader'
        );
    } catch (LogicException $exception) {
        red_addon_editor_data_test_assert(
            str_contains($exception->getMessage(), 'componentDataLoaders'),
            'a declared editor requires exactly one data-loader registration'
        );
    }

    $renderOnlyManifest = $package['manifest'];
    unset($renderOnlyManifest['componentEditors']);
    $undeclaredRegistry = new RED_Addon_Runtime_Registry(
        $packageId,
        $renderOnlyManifest
    );
    try {
        $undeclaredRegistry->registerComponentDataLoader(
            $componentId,
            static function (): array {
                return [];
            }
        );
        red_addon_editor_data_test_assert(
            false,
            'a render-only component cannot add a data loader'
        );
    } catch (LogicException $exception) {
        red_addon_editor_data_test_assert(
            str_contains($exception->getMessage(), 'undeclared'),
            'a component without editor metadata cannot register a data loader'
        );
    }

    $duplicateRegistry = new RED_Addon_Runtime_Registry(
        $packageId,
        $package['manifest']
    );
    $duplicateRegistry->registerComponentDataLoader(
        $componentId,
        static function (): array {
            return [];
        }
    );
    try {
        $duplicateRegistry->registerComponentDataLoader(
            $componentId,
            static function (): array {
                return [];
            }
        );
        red_addon_editor_data_test_assert(
            false,
            'a duplicated data loader must fail'
        );
    } catch (LogicException $exception) {
        red_addon_editor_data_test_assert(
            str_contains($exception->getMessage(), 'duplicated'),
            'each declared component editor has one data-loader owner'
        );
    }

    $packageMigration = 'CREATE TABLE `' . $packageTable . '` ('
        . '`ContentRecordID` int unsigned NOT NULL,'
        . '`Title` varchar(120) NOT NULL,'
        . '`Quantity` int NOT NULL,'
        . '`Mode` varchar(20) NOT NULL,'
        . 'PRIMARY KEY (`ContentRecordID`),'
        . 'CONSTRAINT `fk_red_addon_editor_data_parent` '
        . 'FOREIGN KEY (`ContentRecordID`) REFERENCES `RED_Articles` (`RecordID`) '
        . 'ON DELETE RESTRICT ON UPDATE RESTRICT'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    red_addon_editor_data_test_assert(
        red_addon_install_sql_guard($packageMigration) === '',
        'the disposable package table uses only the approved numeric parent relationship'
    );
    red_addon_install_execute_sql($connection, $packageMigration);
    red_addon_editor_data_test_insert_parent(
        $connection,
        $contentRecordId,
        $componentId
    );
    mysqli_query(
        $connection,
        "INSERT INTO `$packageTable` (ContentRecordID, Title, Quantity, Mode)
         VALUES ($contentRecordId, 'Loaded fixture', 7, 'valid')"
    );

    $passwordHash = password_hash('EditorDataLoader-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_editor_data', ?, 'Admin', 'EditData', 'guest',
            '', '', 'editor-data@example.test', 'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $adminRecordId, $passwordHash);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'isi',
        $adminRecordId,
        $permission,
        $adminRecordId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState, InstalledByAdminRecordID,
            UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, \'enabled\', ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'sssssii',
        $snapshot['id'],
        $snapshot['version'],
        $snapshot['type'],
        $snapshot['manifestSha256'],
        $snapshot['inventorySha256'],
        $adminRecordId,
        $adminRecordId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $runtime = red_addon_runtime_request_bootstrap(
        $connection,
        $fixtureProject
    );
    red_addon_editor_data_test_assert(
        $runtime->owner('components', $componentId) === $packageId
            && $runtime->owner('componentDataLoaders', $componentId)
                === $packageId
            && file_get_contents($executionMarker) === "registered\n"
            && !file_exists($loaderMarker),
        'enabled registration binds one exact data loader without invoking it'
    );

    $before = red_addon_editor_data_test_fingerprint(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        $packageTable
    );
    $loaded = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_data_test_assert(
        $loaded['loaded'] === true
            && $loaded['actorRecordId'] === $adminRecordId
            && $loaded['contentRecordId'] === $contentRecordId
            && $loaded['component'] === $componentId
            && $loaded['package'] === $packageId
            && $loaded['permission'] === $permission
            && $loaded['values'] === [
                'title' => 'Loaded fixture',
                'quantity' => 7,
            ]
            && preg_match('/\A[a-f0-9]{64}\z/', $loaded['stateHash']) === 1
            && $loaded['reason'] === 'loaded',
        'an exact view grant loads only schema-valid normalized values and a state hash'
    );
    red_addon_editor_data_test_assert(
        red_addon_editor_data_test_fingerprint(
            $connection,
            $adminRecordId,
            $contentRecordId,
            $packageId,
            $packageTable
        ) === $before,
        'successful loading writes no core, package, authorization, or audit state'
    );

    $invocations = red_addon_editor_data_test_loader_count($loaderMarker);
    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
            . $adminRecordId
    );
    $denied = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_data_test_assert(
        empty($denied['loaded'])
            && $denied['reason'] === 'permission_denied'
            && red_addon_editor_data_test_loader_count($loaderMarker)
                === $invocations,
        'revoked view permission fails before package data-loader execution'
    );
    $upperPermission = strtoupper($permission);
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'isi',
        $adminRecordId,
        $upperPermission,
        $adminRecordId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    $caseDenied = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_data_test_assert(
        empty($caseDenied['loaded'])
            && $caseDenied['reason'] === 'permission_denied'
            && red_addon_editor_data_test_loader_count($loaderMarker)
                === $invocations,
        'case-drifted package permission cannot invoke the data loader'
    );
    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID='
            . $adminRecordId
    );
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'isi',
        $adminRecordId,
        $permission,
        $adminRecordId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Component='redcms.editor-data-fixture/other'
         WHERE RecordID=$contentRecordId"
    );
    $drifted = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_data_test_assert(
        empty($drifted['loaded'])
            && $drifted['reason'] === 'binding_unavailable'
            && red_addon_editor_data_test_loader_count($loaderMarker)
                === $invocations,
        'parent component drift fails before package data loading'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Component='"
            . mysqli_real_escape_string($connection, $componentId)
            . "' WHERE RecordID=$contentRecordId"
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='installed_disabled'
         WHERE PackageID='"
            . mysqli_real_escape_string($connection, $packageId)
            . "'"
    );
    $disabled = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_data_test_assert(
        empty($disabled['loaded'])
            && $disabled['reason'] === 'binding_unavailable'
            && red_addon_editor_data_test_loader_count($loaderMarker)
                === $invocations,
        'disabled persisted package state fails even with an earlier request context'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='"
            . mysqli_real_escape_string($connection, $packageId)
            . "'"
    );

    $foreignManifest = $package['manifest'];
    $foreignManifest['id'] = 'redcms.foreign-fixture';
    $foreign = red_addon_component_editor_load_values(
        $connection,
        $foreignManifest,
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_data_test_assert(
        empty($foreign['loaded'])
            && $foreign['reason'] === 'binding_unavailable'
            && red_addon_editor_data_test_loader_count($loaderMarker)
                === $invocations,
        'a foreign manifest cannot claim the enabled runtime owner and loader'
    );

    $forgedManifest = $package['manifest'];
    $forgedManifest['componentEditors'][0]['label'] = 'Forged editor';
    $forged = red_addon_component_editor_load_values(
        $connection,
        $forgedManifest,
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_data_test_assert(
        empty($forged['loaded'])
            && $forged['reason'] === 'manifest_mismatch'
            && red_addon_editor_data_test_loader_count($loaderMarker)
                === $invocations,
        'same-id manifest drift fails before registered data-loader execution'
    );

    mysqli_query(
        $connection,
        "UPDATE `$packageTable` SET Mode='invalid'
         WHERE ContentRecordID=$contentRecordId"
    );
    $invalidValues = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_data_test_assert(
        empty($invalidValues['loaded'])
            && $invalidValues['values'] === []
            && $invalidValues['stateHash'] === ''
            && $invalidValues['reason'] === 'invalid_values',
        'unknown package-returned values fail the closed manifest schema'
    );

    foreach (['emit', 'throw', 'nested'] as $mode) {
        mysqli_query(
            $connection,
            "UPDATE `$packageTable` SET Mode='"
                . mysqli_real_escape_string($connection, $mode)
                . "' WHERE ContentRecordID=$contentRecordId"
        );
        ob_start();
        $failed = red_addon_component_editor_load_values(
            $connection,
            $package['manifest'],
            $componentId,
            $contentRecordId,
            $adminRecordId
        );
        $output = (string) ob_get_clean();
        red_addon_editor_data_test_assert(
            empty($failed['loaded'])
                && $failed['reason'] === 'loader_failed'
                && $failed['values'] === []
                && $output === '',
            'loader ' . $mode . ' failure is contained without output or values'
        );
    }

    $invalidActor = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        0
    );
    $invalidRecord = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        0,
        $adminRecordId
    );
    $missingSchema = red_addon_component_editor_load_values(
        $connection,
        ['id' => $packageId],
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_data_test_assert(
        $invalidActor['reason'] === 'invalid_actor'
            && $invalidRecord['reason'] === 'invalid_content_record'
            && $missingSchema['reason'] === 'schema_unavailable',
        'invalid actor, parent, and manifest evidence fail closed'
    );

    red_addon_editor_data_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        $packageTable,
        $temporaryRoot
    );
    red_addon_editor_data_test_assert(
        red_addon_editor_data_test_scalar(
            $connection,
            "SELECT CONCAT_WS(
                ':',
                (SELECT COUNT(*) FROM RED_Admin
                 WHERE RecordID=$adminRecordId),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$adminRecordId),
                (SELECT COUNT(*) FROM RED_Articles
                 WHERE RecordID=$contentRecordId),
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='"
                    . mysqli_real_escape_string($connection, $packageId)
                    . "'),
                (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='$packageTable')
             )"
        ) === '0:0:0:0:0'
            && !file_exists($temporaryRoot),
        'all disposable loader database and filesystem fixtures are removed'
    );

    printf(
        "Add-on component editor data-loader self-test passed: %d assertions.\n",
        $assertions
    );
} catch (Throwable $throwable) {
    red_addon_editor_data_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        $packageTable,
        $temporaryRoot
    );
    fwrite(
        STDERR,
        'Add-on component editor data-loader self-test failed: '
            . $throwable->getMessage()
            . ' (after '
            . $assertions
            . " assertions)\n"
    );
    mysqli_close($connection);
    exit(1);
}

mysqli_close($connection);
