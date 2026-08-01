<?php
/**
 * Disposable checks for transactional add-on component editor updates.
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
    . '/includes/addon_component_editor_write_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_editor_update)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Component editor update self-test refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$adminRecordId = 2147000973;
$contentRecordId = 2147000974;
$packageId = 'redcms.editor-update-fixture';
$componentId = 'redcms.editor-update-fixture/item';
$viewPermission = 'fixture.editor-update.view';
$editPermission = 'fixture.editor-update.edit';
$packageTable = 'RED_Addon_Component_Editor_Update_Fixture';
$transactionTable = 'RED_Addon_Component_Editor_Update_Transaction';
$temporaryRoot = sys_get_temp_dir()
    . '/redcms-addon-editor-update-'
    . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$executionMarker = $temporaryRoot . '/execution-marker';
$loaderMarker = $temporaryRoot . '/loader-marker';
$writerMarker = $temporaryRoot . '/writer-marker';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_editor_update_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_editor_update_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_editor_update_test_marker_count($path)
{
    if (!is_file($path)) {
        return 0;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    return is_array($lines) ? count($lines) : 0;
}

function red_addon_editor_update_test_remove_tree($path)
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

function red_addon_editor_update_test_cleanup(
    $connection,
    $adminRecordId,
    $contentRecordId,
    $packageId,
    array $tables,
    $temporaryRoot
) {
    try {
        foreach ($tables as $table) {
            if (preg_match('/\ARED_Addon_[A-Za-z0-9_]+\z/', $table) === 1) {
                mysqli_query($connection, 'DROP TABLE IF EXISTS `' . $table . '`');
            }
        }
        mysqli_query(
            $connection,
            'DELETE FROM RED_Articles WHERE RecordID=' . (int) $contentRecordId
        );
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
            'DELETE FROM RED_Admin WHERE RecordID=' . (int) $adminRecordId
        );
    } catch (Throwable $throwable) {
        error_log(
            'Component editor update cleanup failed: '
                . $throwable->getMessage()
        );
    }
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    red_addon_editor_update_test_remove_tree($temporaryRoot);
}

function red_addon_editor_update_test_insert_parent(
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
    $row['Alias'] = 'codex-addon-editor-update-fixture';
    $row['Title'] = 'Disposable component editor update fixture';
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

function red_addon_editor_update_test_write_fixture(
    $project,
    $packageId,
    $componentId,
    $viewPermission,
    $editPermission,
    $packageTable,
    $transactionTable,
    $executionMarker,
    $loaderMarker,
    $writerMarker
) {
    [$vendor, $package] = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $vendor . '/' . $package;
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create update fixture.');
    }
    $selectSql = 'SELECT Title, Quantity, Mode FROM `' . $packageTable
        . '` WHERE ContentRecordID=? LIMIT 1';
    $fullUpdateSql = 'UPDATE `' . $packageTable
        . '` SET Title=?, Quantity=? WHERE ContentRecordID=?';
    $partialUpdateSql = 'UPDATE `' . $packageTable
        . '` SET Title=? WHERE ContentRecordID=?';
    $entrypoint = <<<'PHP'
<?php
return static function (RED_Addon_Runtime_Registry $runtime): void {
    file_put_contents(__EXECUTION_MARKER__, "registered\n", FILE_APPEND | LOCK_EX);
    $runtime->registerComponent(
        __COMPONENT_ID__,
        static function (array $context): array {
            return ['title' => 'Fixture', 'summary' => 'Fixture'];
        }
    );
    $runtime->registerComponentDataLoader(
        __COMPONENT_ID__,
        static function ($connection, array $context): array {
            file_put_contents(__LOADER_MARKER__, "loaded\n", FILE_APPEND | LOCK_EX);
            $statement = mysqli_prepare($connection, __SELECT_SQL__);
            mysqli_stmt_bind_param($statement, 'i', $context['contentRecordId']);
            mysqli_stmt_execute($statement);
            $result = mysqli_stmt_get_result($statement);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($statement);
            if (!is_array($row)) {
                throw new RuntimeException('missing update fixture data');
            }
            return [
                'title' => $row['Title'],
                'quantity' => (int) $row['Quantity'],
            ];
        }
    );
    $runtime->registerComponentDataWriter(
        __COMPONENT_ID__,
        static function ($connection, array $context, array $values): bool {
            if (array_keys($context) !== [
                    'component',
                    'contentRecordId',
                    'actorRecordId',
                    'previousStateHash',
                ]
                || array_keys($values) !== ['title', 'quantity']
            ) {
                throw new RuntimeException('unexpected update context');
            }
            file_put_contents(__WRITER_MARKER__, "written\n", FILE_APPEND | LOCK_EX);
            $modeStatement = mysqli_prepare($connection, __SELECT_SQL__);
            mysqli_stmt_bind_param(
                $modeStatement,
                'i',
                $context['contentRecordId']
            );
            mysqli_stmt_execute($modeStatement);
            $modeResult = mysqli_stmt_get_result($modeStatement);
            $row = $modeResult ? mysqli_fetch_assoc($modeResult) : null;
            mysqli_stmt_close($modeStatement);
            if (!is_array($row)) {
                return false;
            }
            if ($row['Mode'] === 'mismatch') {
                $statement = mysqli_prepare($connection, __PARTIAL_UPDATE_SQL__);
                mysqli_stmt_bind_param(
                    $statement,
                    'si',
                    $values['title'],
                    $context['contentRecordId']
                );
            } else {
                $statement = mysqli_prepare($connection, __FULL_UPDATE_SQL__);
                mysqli_stmt_bind_param(
                    $statement,
                    'sii',
                    $values['title'],
                    $values['quantity'],
                    $context['contentRecordId']
                );
            }
            $updated = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            if (!$updated) {
                return false;
            }
            if ($row['Mode'] === 'emit') {
                echo 'unsafe-writer-output';
            }
            if ($row['Mode'] === 'throw') {
                throw new RuntimeException('private writer failure');
            }
            if ($row['Mode'] === 'nested') {
                ob_start();
            }
            if ($row['Mode'] === 'false') {
                return false;
            }
            return true;
        },
        [__PACKAGE_TABLE__, __TRANSACTION_TABLE__]
    );
};
PHP;
    $entrypoint = str_replace(
        [
            '__EXECUTION_MARKER__',
            '__LOADER_MARKER__',
            '__WRITER_MARKER__',
            '__COMPONENT_ID__',
            '__SELECT_SQL__',
            '__FULL_UPDATE_SQL__',
            '__PARTIAL_UPDATE_SQL__',
            '__PACKAGE_TABLE__',
            '__TRANSACTION_TABLE__',
        ],
        [
            var_export($executionMarker, true),
            var_export($loaderMarker, true),
            var_export($writerMarker, true),
            var_export($componentId, true),
            var_export($selectSql, true),
            var_export($fullUpdateSql, true),
            var_export($partialUpdateSql, true),
            var_export($packageTable, true),
            var_export($transactionTable, true),
        ],
        $entrypoint
    );
    file_put_contents($directory . '/addon.php', $entrypoint);
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Component Editor Update Fixture',
        'description' => 'Disposable transactional update fixture.',
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
        'permissions' => [$viewPermission, $editPermission],
        'componentEditors' => [[
            'component' => $componentId,
            'label' => 'Update fixture',
            'description' => 'Update package-owned values transactionally.',
            'icon' => 'package',
            'permissions' => [
                'create' => $editPermission,
                'view' => $viewPermission,
                'edit' => $editPermission,
                'delete' => $editPermission,
                'publish' => $editPermission,
                'restore' => $editPermission,
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

function red_addon_editor_update_test_grant(
    $connection,
    $adminRecordId,
    $permission
) {
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
}

function red_addon_editor_update_test_set_mode(
    $connection,
    $packageTable,
    $contentRecordId,
    $mode
) {
    $statement = mysqli_prepare(
        $connection,
        'UPDATE `' . $packageTable . '` SET Mode=? WHERE ContentRecordID=?'
    );
    mysqli_stmt_bind_param($statement, 'si', $mode, $contentRecordId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
}

function red_addon_editor_update_test_values(
    $connection,
    $packageTable,
    $contentRecordId
) {
    return red_addon_editor_update_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':', Title, Quantity) FROM `"
            . $packageTable . '` WHERE ContentRecordID=' . (int) $contentRecordId
    );
}

try {
    red_addon_editor_update_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        [$transactionTable, $packageTable],
        $temporaryRoot
    );
    red_addon_editor_update_test_write_fixture(
        $fixtureProject,
        $packageId,
        $componentId,
        $viewPermission,
        $editPermission,
        $packageTable,
        $transactionTable,
        $executionMarker,
        $loaderMarker,
        $writerMarker
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][$packageId] ?? null;
    $snapshot = is_array($package)
        ? red_addon_registry_snapshot($package)
        : null;
    red_addon_editor_update_test_assert(
        !empty($catalog['valid'])
            && is_array($package)
            && is_array($snapshot)
            && !file_exists($executionMarker)
            && !file_exists($loaderMarker)
            && !file_exists($writerMarker),
        'discovery remains non-executing for a declared component writer'
    );

    $renderOnly = $package['manifest'];
    unset($renderOnly['componentEditors']);
    $undeclaredRegistry = new RED_Addon_Runtime_Registry(
        $packageId,
        $renderOnly
    );
    try {
        $undeclaredRegistry->registerComponentDataWriter(
            $componentId,
            static function (): bool {
                return true;
            },
            [$packageTable]
        );
        red_addon_editor_update_test_assert(false, 'undeclared writer must fail');
    } catch (LogicException $exception) {
        red_addon_editor_update_test_assert(
            str_contains($exception->getMessage(), 'undeclared'),
            'only a declared component editor may bind a writer'
        );
    }

    $invalidTableRegistry = new RED_Addon_Runtime_Registry(
        $packageId,
        $package['manifest']
    );
    try {
        $invalidTableRegistry->registerComponentDataWriter(
            $componentId,
            static function (): bool {
                return true;
            },
            ['RED_Articles']
        );
        red_addon_editor_update_test_assert(false, 'core table metadata must fail');
    } catch (LogicException $exception) {
        red_addon_editor_update_test_assert(
            str_contains($exception->getMessage(), 'table'),
            'writer transaction metadata accepts only package tables'
        );
    }

    $duplicateRegistry = new RED_Addon_Runtime_Registry(
        $packageId,
        $package['manifest']
    );
    $duplicateRegistry->registerComponentDataWriter(
        $componentId,
        static function (): bool {
            return true;
        },
        [$packageTable]
    );
    try {
        $duplicateRegistry->registerComponentDataWriter(
            $componentId,
            static function (): bool {
                return true;
            },
            [$packageTable]
        );
        red_addon_editor_update_test_assert(false, 'duplicate writer must fail');
    } catch (LogicException $exception) {
        red_addon_editor_update_test_assert(
            str_contains($exception->getMessage(), 'duplicated'),
            'one editor may bind at most one component writer'
        );
    }

    $packageMigration = 'CREATE TABLE `' . $packageTable . '` ('
        . '`ContentRecordID` int unsigned NOT NULL,'
        . '`Title` varchar(120) NOT NULL,'
        . '`Quantity` int NOT NULL,'
        . '`Mode` varchar(20) NOT NULL,'
        . 'PRIMARY KEY (`ContentRecordID`),'
        . 'CONSTRAINT `fk_red_addon_editor_update_parent` '
        . 'FOREIGN KEY (`ContentRecordID`) REFERENCES `RED_Articles` (`RecordID`) '
        . 'ON DELETE RESTRICT ON UPDATE RESTRICT'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    red_addon_editor_update_test_assert(
        red_addon_install_sql_guard($packageMigration) === '',
        'the writer fixture uses only the approved numeric parent relationship'
    );
    red_addon_install_execute_sql($connection, $packageMigration);
    mysqli_query(
        $connection,
        'CREATE TABLE `' . $transactionTable . '` ('
            . '`RecordID` int unsigned NOT NULL PRIMARY KEY'
            . ') ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    red_addon_editor_update_test_insert_parent(
        $connection,
        $contentRecordId,
        $componentId
    );
    mysqli_query(
        $connection,
        "INSERT INTO `$packageTable` (ContentRecordID, Title, Quantity, Mode)
         VALUES ($contentRecordId, 'Initial fixture', 7, 'valid')"
    );

    $passwordHash = password_hash('EditorUpdate-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_editor_update', ?, 'Admin', 'EditWrite', 'guest',
            '', '', 'editor-update@example.test', 'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $adminRecordId, $passwordHash);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_addon_editor_update_test_grant(
        $connection,
        $adminRecordId,
        $viewPermission
    );
    red_addon_editor_update_test_grant(
        $connection,
        $adminRecordId,
        $editPermission
    );
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
    red_addon_editor_update_test_assert(
        $runtime->owner('componentDataLoaders', $componentId) === $packageId
            && $runtime->owner('componentDataWriters', $componentId)
                === $packageId
            && $runtime->metadata('componentDataWriters', $componentId)
                === ['tables' => [$packageTable, $transactionTable]]
            && red_addon_editor_update_test_marker_count($executionMarker) === 1
            && !file_exists($loaderMarker)
            && !file_exists($writerMarker),
        'enabled bootstrap binds exact loader, writer, and transaction tables without invocation'
    );

    $loaded = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    $writers = red_addon_editor_update_test_marker_count($writerMarker);
    $unsupported = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $loaded['stateHash'],
        ['title' => 'Blocked fixture', 'quantity' => '8']
    );
    red_addon_editor_update_test_assert(
        empty($unsupported['updated'])
            && $unsupported['reason'] === 'transaction_unsupported'
            && red_addon_editor_update_test_values(
                $connection,
                $packageTable,
                $contentRecordId
            ) === 'Initial fixture:7'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'a declared non-InnoDB transaction table refuses the writer before invocation'
    );
    mysqli_query(
        $connection,
        'ALTER TABLE `' . $transactionTable . '` ENGINE=InnoDB'
    );

    mysqli_begin_transaction($connection);
    $nestedTransaction = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $loaded['stateHash'],
        ['title' => 'Nested transaction', 'quantity' => '8']
    );
    mysqli_rollback($connection);
    red_addon_editor_update_test_assert(
        empty($nestedTransaction['updated'])
            && $nestedTransaction['reason'] === 'transaction_already_active'
            && red_addon_editor_update_test_values(
                $connection,
                $packageTable,
                $contentRecordId
            ) === 'Initial fixture:7'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'a caller-owned transaction is refused before writer invocation'
    );

    $coreBefore = red_addon_editor_update_test_scalar(
        $connection,
        "SELECT CONCAT_WS(':', Component, Alias) FROM RED_Articles
         WHERE RecordID=$contentRecordId"
    );
    $updated = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $loaded['stateHash'],
        ['title' => 'Updated fixture', 'quantity' => '11']
    );
    red_addon_editor_update_test_assert(
        $updated['updated'] === true
            && $updated['unchanged'] === false
            && $updated['permission'] === $editPermission
            && $updated['values'] === [
                'title' => 'Updated fixture',
                'quantity' => 11,
            ]
            && $updated['previousStateHash'] === $loaded['stateHash']
            && red_addon_component_editor_state_hash_valid(
                $updated['stateHash']
            )
            && !hash_equals($loaded['stateHash'], $updated['stateHash'])
            && $updated['reason'] === 'updated',
        'exact grants and current state commit normalized package values with a new state hash: '
            . json_encode($updated, JSON_UNESCAPED_SLASHES)
    );
    red_addon_editor_update_test_assert(
        red_addon_editor_update_test_values(
            $connection,
            $packageTable,
            $contentRecordId
        ) === 'Updated fixture:11'
            && red_addon_editor_update_test_scalar(
                $connection,
                "SELECT CONCAT_WS(':', Component, Alias) FROM RED_Articles
                 WHERE RecordID=$contentRecordId"
            ) === $coreBefore,
        'the package update preserves the locked core placement parent'
    );

    $writers = red_addon_editor_update_test_marker_count($writerMarker);
    $unchanged = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $updated['stateHash'],
        ['title' => 'Updated fixture', 'quantity' => '11']
    );
    red_addon_editor_update_test_assert(
        $unchanged['unchanged'] === true
            && empty($unchanged['updated'])
            && $unchanged['stateHash'] === $updated['stateHash']
            && $unchanged['reason'] === 'unchanged'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'an identical current submission succeeds without invoking the writer'
    );

    $stale = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $loaded['stateHash'],
        ['title' => 'Stale fixture', 'quantity' => '12']
    );
    red_addon_editor_update_test_assert(
        empty($stale['updated'])
            && $stale['reason'] === 'stale_state'
            && red_addon_editor_update_test_values(
                $connection,
                $packageTable,
                $contentRecordId
            ) === 'Updated fixture:11'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'a stale state hash refuses the callback and preserves current data'
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$adminRecordId
           AND BINARY Capability=BINARY '"
            . mysqli_real_escape_string($connection, $editPermission) . "'"
    );
    $denied = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $updated['stateHash'],
        ['title' => 'Denied fixture', 'quantity' => '12']
    );
    red_addon_editor_update_test_assert(
        empty($denied['updated'])
            && $denied['reason'] === 'permission_denied'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'revoked edit permission refuses the writer before invocation'
    );
    red_addon_editor_update_test_grant(
        $connection,
        $adminRecordId,
        $editPermission
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$adminRecordId
           AND BINARY Capability=BINARY '"
            . mysqli_real_escape_string($connection, $viewPermission) . "'"
    );
    $viewDenied = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $updated['stateHash'],
        ['title' => 'View denied fixture', 'quantity' => '12']
    );
    red_addon_editor_update_test_assert(
        empty($viewDenied['updated'])
            && $viewDenied['reason'] === 'view_permission_denied'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'an editor without the current view grant cannot validate stale state'
    );
    red_addon_editor_update_test_grant(
        $connection,
        $adminRecordId,
        $viewPermission
    );

    $invalid = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $updated['stateHash'],
        ['title' => 'Invalid fixture', 'quantity' => '101']
    );
    $badHash = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        strtoupper($updated['stateHash']),
        ['title' => 'Invalid fixture', 'quantity' => '12']
    );
    red_addon_editor_update_test_assert(
        $invalid['reason'] === 'invalid_values'
            && $badHash['reason'] === 'invalid_state_hash'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'invalid values and non-canonical state hashes fail before package execution'
    );

    $invalidActor = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        0,
        $updated['stateHash'],
        ['title' => 'Invalid actor', 'quantity' => '12']
    );
    $invalidRecord = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        0,
        $adminRecordId,
        $updated['stateHash'],
        ['title' => 'Invalid record', 'quantity' => '12']
    );
    $missingSchema = red_addon_component_editor_update_values(
        $connection,
        ['id' => $packageId],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $updated['stateHash'],
        ['title' => 'Missing schema', 'quantity' => '12']
    );
    red_addon_editor_update_test_assert(
        $invalidActor['reason'] === 'invalid_actor'
            && $invalidRecord['reason'] === 'invalid_content_record'
            && $missingSchema['reason'] === 'schema_unavailable'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'invalid actor, parent, and manifest evidence fail before package execution'
    );

    $forgedManifest = $package['manifest'];
    $forgedManifest['componentEditors'][0]['label'] = 'Forged update editor';
    $forged = red_addon_component_editor_update_values(
        $connection,
        $forgedManifest,
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $updated['stateHash'],
        ['title' => 'Forged fixture', 'quantity' => '12']
    );
    red_addon_editor_update_test_assert(
        $forged['reason'] === 'manifest_mismatch'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'same-id manifest drift cannot invoke the registered writer'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Component='redcms.editor-update-fixture/other'
         WHERE RecordID=$contentRecordId"
    );
    $drifted = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $updated['stateHash'],
        ['title' => 'Drifted fixture', 'quantity' => '12']
    );
    red_addon_editor_update_test_assert(
        $drifted['reason'] === 'binding_unavailable'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'component-parent drift fails after the transaction lock and before the writer'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Articles SET Component='"
            . mysqli_real_escape_string($connection, $componentId)
            . "' WHERE RecordID=$contentRecordId"
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='installed_disabled'
         WHERE PackageID='"
            . mysqli_real_escape_string($connection, $packageId) . "'"
    );
    $disabled = red_addon_component_editor_update_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId,
        $updated['stateHash'],
        ['title' => 'Disabled fixture', 'quantity' => '12']
    );
    red_addon_editor_update_test_assert(
        $disabled['reason'] === 'binding_unavailable'
            && red_addon_editor_update_test_marker_count($writerMarker)
                === $writers,
        'disabled persisted state fails even with an earlier runtime context'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='"
            . mysqli_real_escape_string($connection, $packageId) . "'"
    );

    foreach (
        [
            'emit' => 'writer_failed',
            'throw' => 'writer_failed',
            'nested' => 'writer_failed',
            'false' => 'writer_failed',
            'mismatch' => 'postcondition_failed',
        ] as $mode => $reason
    ) {
        red_addon_editor_update_test_set_mode(
            $connection,
            $packageTable,
            $contentRecordId,
            $mode
        );
        $beforeValues = red_addon_editor_update_test_values(
            $connection,
            $packageTable,
            $contentRecordId
        );
        ob_start();
        $failed = red_addon_component_editor_update_values(
            $connection,
            $package['manifest'],
            $componentId,
            $contentRecordId,
            $adminRecordId,
            $updated['stateHash'],
            ['title' => 'Rollback ' . $mode, 'quantity' => '13']
        );
        $output = (string) ob_get_clean();
        red_addon_editor_update_test_assert(
            empty($failed['updated'])
                && $failed['reason'] === $reason
                && $output === ''
                && red_addon_editor_update_test_values(
                    $connection,
                    $packageTable,
                    $contentRecordId
                ) === $beforeValues,
            'writer ' . $mode . ' failure rolls back package values without output'
        );
    }

    red_addon_editor_update_test_set_mode(
        $connection,
        $packageTable,
        $contentRecordId,
        'valid'
    );
    $reloaded = red_addon_component_editor_load_values(
        $connection,
        $package['manifest'],
        $componentId,
        $contentRecordId,
        $adminRecordId
    );
    red_addon_editor_update_test_assert(
        $reloaded['loaded'] === true
            && $reloaded['values'] === $updated['values']
            && $reloaded['stateHash'] === $updated['stateHash'],
        'all injected failures preserve the last committed package state'
    );

    red_addon_editor_update_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        [$transactionTable, $packageTable],
        $temporaryRoot
    );
    red_addon_editor_update_test_assert(
        red_addon_editor_update_test_scalar(
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
                   AND TABLE_NAME IN ('$packageTable', '$transactionTable'))
             )"
        ) === '0:0:0:0:0'
            && !file_exists($temporaryRoot),
        'all disposable update database and filesystem fixtures are removed'
    );

    printf(
        "Add-on component editor update self-test passed: %d assertions.\n",
        $assertions
    );
} catch (Throwable $throwable) {
    red_addon_editor_update_test_cleanup(
        $connection,
        $adminRecordId,
        $contentRecordId,
        $packageId,
        [$transactionTable, $packageTable],
        $temporaryRoot
    );
    fwrite(
        STDERR,
        'Add-on component editor update self-test failed: '
            . $throwable->getMessage()
            . ' (after '
            . $assertions
            . " assertions)\n"
    );
    mysqli_close($connection);
    exit(1);
}

mysqli_close($connection);
