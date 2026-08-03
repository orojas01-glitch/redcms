<?php
/**
 * Disposable checks for per-client setting storage and read-only preflight.
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
require_once $projectRoot . '/includes/addon_setting_storage_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_setting_storage)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on setting storage self-test refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000940;
$otherId = 2147000941;
$packageId = 'redcms.storage-fixture';
$permission = 'fixture.settings.manage';
$temporaryRoot = sys_get_temp_dir() . '/redcms-setting-storage-'
    . bin2hex(random_bytes(8));
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_setting_storage_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_setting_storage_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_setting_storage_test_remove_tree($path)
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

function red_addon_setting_storage_test_cleanup(
    $connection,
    $packageId,
    array $adminIds
) {
    $escaped = mysqli_real_escape_string($connection, $packageId);
    $ids = implode(',', array_map('intval', $adminIds));
    try {
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Settings WHERE PackageID='$escaped'"
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Addon_Installations WHERE PackageID='$escaped'"
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID IN ($ids)"
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Admin_Roles WHERE AdminRecordID IN ($ids)"
        );
        mysqli_query(
            $connection,
            "DELETE FROM RED_Admin WHERE RecordID IN ($ids)"
        );
    } catch (Throwable $throwable) {
        error_log(
            'Add-on setting storage cleanup failed: '
                . $throwable->getMessage()
        );
    }
}

function red_addon_setting_storage_test_manifest($permission)
{
    return [
        '$schema' =>
            'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.storage-fixture',
        'name' => 'Setting storage fixture',
        'description' => 'Read-only setting storage preflight fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => ['redcms.storage-fixture/service'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$permission],
        'settings' => [
            [
                'key' => 'store.name',
                'label' => 'Store name',
                'type' => 'text',
                'secret' => false,
                'permission' => $permission,
                'default' => 'Fixture Store',
            ],
            [
                'key' => 'store.enabled',
                'label' => 'Store enabled',
                'type' => 'boolean',
                'secret' => false,
                'permission' => $permission,
                'default' => false,
            ],
            [
                'key' => 'payment.api-key',
                'label' => 'Payment API key',
                'type' => 'secret-reference',
                'secret' => true,
                'permission' => $permission,
            ],
        ],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
}

red_addon_setting_storage_test_cleanup(
    $connection,
    $packageId,
    [$actorId, $otherId]
);

try {
    red_addon_setting_storage_test_assert(
        red_addon_setting_storage_available($connection)
            && red_addon_setting_storage_test_scalar(
                $connection,
                "SELECT CONCAT_WS(
                    ':',
                    (SELECT COUNT(*) FROM RED_Addon_Settings),
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA=DATABASE()
                       AND CONSTRAINT_NAME=
                         'fk_red_addon_settings_installation')
                 )"
            ) === '0:1',
        'the current client starts with exact empty generic setting storage'
    );

    $manifest = red_addon_setting_storage_test_manifest($permission);
    $schema = red_addon_settings_schema($manifest);
    red_addon_setting_storage_test_assert(
        is_array($schema)
            && ($schema[0]['permission'] ?? '') === $permission
            && ($schema[2]['permission'] ?? '') === $permission,
        'setting definitions normalize only an explicit package permission'
    );

    $undeclared = $manifest;
    $undeclared['permissions'] = ['fixture.other.manage'];
    red_addon_setting_storage_test_assert(
        red_addon_settings_schema($undeclared) === null,
        'a setting permission not declared by its package fails trust validation'
    );

    $noPermission = $manifest;
    unset($noPermission['settings'][0]['permission']);
    red_addon_setting_storage_test_assert(
        red_addon_setting_permission_plan(
            $manifest,
            'store.name'
        ) === [
            'setting' => 'store.name',
            'operation' => 'manage',
            'permission' => $permission,
        ]
            && red_addon_setting_permission_plan(
                $noPermission,
                'store.name'
            ) === null,
        'operational permission planning requires the exact setting binding'
    );

    $packageDirectory = $temporaryRoot
        . '/addons/redcms/storage-fixture';
    if (!mkdir($packageDirectory, 0700, true)) {
        throw new RuntimeException('Could not create package fixture.');
    }
    $entrypoint = "<?php\nthrow new RuntimeException('must not execute');\n";
    file_put_contents($packageDirectory . '/addon.php', $entrypoint);
    $manifest['integrity']['files'] = [[
        'path' => 'addon.php',
        'sha256' => hash('sha256', $entrypoint),
    ]];
    file_put_contents(
        $packageDirectory . '/addon.json',
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n"
    );
    $package = red_addon_validate_manifest(
        $packageId,
        $temporaryRoot,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $snapshot = red_addon_registry_snapshot($package);
    red_addon_setting_storage_test_assert(
        !empty($package['valid']) && is_array($snapshot),
        'the fixture package passes complete data-only trust validation'
    );

    $password = password_hash('SettingStorage-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, ?, ?, 'Admin', ?, 'guest', '', '', ?,
                   'N', 'to', 'N', 'to')"
    );
    foreach ([
        [$actorId, 'codex_setting_actor', 'SettingActor',
            'setting-actor@example.test'],
        [$otherId, 'codex_setting_other', 'SettingOther',
            'setting-other@example.test'],
    ] as $admin) {
        $recordId = $admin[0];
        $username = $admin[1];
        $alias = $admin[2];
        $email = $admin[3];
        mysqli_stmt_bind_param(
            $statement,
            'issss',
            $recordId,
            $username,
            $password,
            $alias,
            $email
        );
        if (!mysqli_stmt_execute($statement)) {
            throw new RuntimeException('Could not create administrator fixture.');
        }
    }
    mysqli_stmt_close($statement);

    red_addon_setting_storage_test_assert(
        empty(red_addon_setting_permission_decision(
            $connection,
            $manifest,
            'store.name',
            $actorId
        )['authorized']),
        'a declared permission without a fresh grant is denied'
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
        $actorId,
        $permission,
        $actorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    red_addon_setting_storage_test_assert(
        !empty(red_addon_setting_permission_decision(
            $connection,
            $manifest,
            'store.name',
            $actorId
        )['authorized'])
            && empty(red_addon_setting_permission_decision(
                $connection,
                $manifest,
                'store.name',
                $otherId
            )['authorized']),
        'only the current client administrator with the exact grant is authorized'
    );

    $uppercaseManifest = $manifest;
    foreach ($uppercaseManifest['settings'] as &$definition) {
        $definition['permission'] = strtoupper($permission);
    }
    unset($definition);
    $uppercaseManifest['permissions'] = [strtoupper($permission)];
    red_addon_setting_storage_test_assert(
        empty(red_addon_setting_permission_decision(
            $connection,
            $uppercaseManifest,
            'store.name',
            $actorId
        )['authorized']),
        'permission matching remains binary and case-sensitive'
    );

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState, InstalledByAdminRecordID,
            UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $state = 'installed_disabled';
    $snapshotId = $snapshot['id'];
    $snapshotVersion = $snapshot['version'];
    $snapshotType = $snapshot['type'];
    $manifestSha256 = $snapshot['manifestSha256'];
    $inventorySha256 = $snapshot['inventorySha256'];
    mysqli_stmt_bind_param(
        $statement,
        'ssssssii',
        $snapshotId,
        $snapshotVersion,
        $snapshotType,
        $manifestSha256,
        $inventorySha256,
        $state,
        $actorId,
        $actorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $values = [
        'payment.api-key' => 'config:redcms.storage-fixture.api-key',
    ];
    $fingerprint = red_addon_setting_storage_test_scalar(
        $connection,
        "SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Addon_Settings),
            (SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID='redcms.storage-fixture'),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities
             WHERE AdminRecordID=$actorId)
         )"
    );
    $preflight = red_addon_setting_write_preflight(
        $connection,
        $package,
        $actorId,
        $values
    );
    red_addon_setting_storage_test_assert(
        !empty($preflight['valid'])
            && !empty($preflight['writeReady'])
            && $preflight['permissions'] === [$permission]
            && red_addon_valid_sha256($preflight['currentStateSha256'])
            && red_addon_valid_sha256($preflight['targetStateSha256'])
            && red_addon_valid_sha256($preflight['planSha256'])
            && empty($preflight['stateMutation'])
            && empty($preflight['packageExecution'])
            && empty($preflight['secretResolution']),
        'exact package, values, state, and grants produce a value-free write plan'
    );
    red_addon_setting_storage_test_assert(
        red_addon_setting_storage_test_scalar(
            $connection,
            "SELECT CONCAT_WS(
                ':',
                (SELECT COUNT(*) FROM RED_Addon_Settings),
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='redcms.storage-fixture'),
                (SELECT COUNT(*) FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=$actorId)
             )"
        ) === $fingerprint,
        'preflight performs no database mutation'
    );
    red_addon_setting_storage_test_assert(
        empty(red_addon_setting_write_preflight(
            $connection,
            $package,
            $actorId,
            []
        )['valid']),
        'missing required setting values fail before planning'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='installation_failed'
         WHERE PackageID='redcms.storage-fixture'"
    );
    red_addon_setting_storage_test_assert(
        (red_addon_setting_write_preflight(
            $connection,
            $package,
            $actorId,
            $values
        )['errors'][0] ?? '') === 'lifecycle_state_unsupported',
        'unsupported lifecycle state fails closed'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='installed_disabled'
         WHERE PackageID='redcms.storage-fixture'"
    );

    mysqli_query(
        $connection,
        "INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON,
            SecretReference, UpdatedByAdminRecordID
         ) VALUES (
            'redcms.storage-fixture', 'unknown.setting', 'text',
            '\"unsafe\"', NULL, $actorId
         )"
    );
    red_addon_setting_storage_test_assert(
        (red_addon_setting_write_preflight(
            $connection,
            $package,
            $actorId,
            $values
        )['errors'][0] ?? '') === 'stored_schema_drift',
        'unknown persisted keys fail closed before any future write'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Addon_Settings
         WHERE PackageID='redcms.storage-fixture'"
    );

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON,
            SecretReference, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, NULL, ?, ?)'
    );
    $secretKey = 'payment.api-key';
    $secretType = 'secret-reference';
    $reference = 'config:redcms.storage-fixture.api-key';
    mysqli_stmt_bind_param(
        $statement,
        'ssssi',
        $packageId,
        $secretKey,
        $secretType,
        $reference,
        $actorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    $withStoredState = red_addon_setting_write_preflight(
        $connection,
        $package,
        $actorId,
        $values
    );
    red_addon_setting_storage_test_assert(
        !empty($withStoredState['valid'])
            && !hash_equals(
                $preflight['currentStateSha256'],
                $withStoredState['currentStateSha256']
            ),
        'valid persisted opaque references change only the current-state fingerprint'
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$actorId AND Capability='fixture.settings.manage'"
    );
    red_addon_setting_storage_test_assert(
        (red_addon_setting_write_preflight(
            $connection,
            $package,
            $actorId,
            $values
        )['errors'][0] ?? '') === 'permission_denied',
        'grant revocation is effective on the next preflight decision'
    );
} finally {
    red_addon_setting_storage_test_cleanup(
        $connection,
        $packageId,
        [$actorId, $otherId]
    );
    red_addon_setting_storage_test_remove_tree($temporaryRoot);
}

red_addon_setting_storage_test_assert(
    red_addon_setting_storage_test_scalar(
        $connection,
        "SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Addon_Settings
             WHERE PackageID='redcms.storage-fixture'),
            (SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID='redcms.storage-fixture'),
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID IN ($actorId,$otherId)),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities
             WHERE AdminRecordID IN ($actorId,$otherId))
         )"
    ) === '0:0:0:0'
        && !file_exists($temporaryRoot),
    'all storage, package, administrator, grant, and filesystem fixtures clean up'
);

fwrite(
    STDOUT,
    'Add-on setting storage preflight self-test passed (' . $assertions
        . " assertions).\n"
);

?>
