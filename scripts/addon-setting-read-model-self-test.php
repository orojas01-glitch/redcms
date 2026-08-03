<?php
/**
 * Disposable checks for permission-scoped add-on setting read models.
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
require_once $projectRoot . '/includes/addon_setting_read_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_setting_read)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on setting read-model self-test refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$catalogActorId = 2147000942;
$secretActorId = 2147000943;
$deniedActorId = 2147000944;
$packageId = 'redcms.setting-read-fixture';
$catalogPermission = 'fixture.settings.catalog';
$secretPermission = 'fixture.settings.payment';
$temporaryRoot = sys_get_temp_dir() . '/redcms-setting-read-'
    . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/execution-marker';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_setting_read_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_setting_read_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_setting_read_test_remove_tree($path)
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

function red_addon_setting_read_test_cleanup(
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
            "DELETE FROM RED_Addon_Activity_Log WHERE PackageID='$escaped'"
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
            'Add-on setting read-model cleanup failed: '
                . $throwable->getMessage()
        );
    }
}

function red_addon_setting_read_test_manifest(
    $catalogPermission,
    $secretPermission
) {
    return [
        '$schema' =>
            'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => 'redcms.setting-read-fixture',
        'name' => 'Setting read fixture',
        'description' => 'Permission-scoped settings read-model fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => ['redcms.setting-read-fixture/service'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$catalogPermission, $secretPermission],
        'settings' => [
            [
                'key' => 'store.name',
                'label' => 'Store name',
                'type' => 'text',
                'secret' => false,
                'permission' => $catalogPermission,
                'default' => 'Fixture Store',
            ],
            [
                'key' => 'store.enabled',
                'label' => 'Store enabled',
                'type' => 'boolean',
                'secret' => false,
                'permission' => $catalogPermission,
                'default' => false,
            ],
            [
                'key' => 'store.currency',
                'label' => 'Store currency',
                'type' => 'select',
                'secret' => false,
                'permission' => $catalogPermission,
                'options' => ['USD', 'CAD'],
                'default' => 'USD',
            ],
            [
                'key' => 'support.email',
                'label' => 'Support email',
                'type' => 'email',
                'secret' => false,
                'permission' => $catalogPermission,
            ],
            [
                'key' => 'payment.api-key',
                'label' => 'Payment API key',
                'type' => 'secret-reference',
                'secret' => true,
                'permission' => $secretPermission,
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

function red_addon_setting_read_test_fingerprint(
    $connection,
    $packageId,
    array $adminIds
) {
    $escaped = mysqli_real_escape_string($connection, $packageId);
    $ids = implode(',', array_map('intval', $adminIds));
    return red_addon_setting_read_test_scalar(
        $connection,
        "SELECT SHA2(CONCAT_WS(
            '|',
            (SELECT COALESCE(GROUP_CONCAT(CONCAT_WS(
                ':', SettingKey, ValueType, COALESCE(ValueJSON, '<null>'),
                COALESCE(SecretReference, '<null>'), UpdatedByAdminRecordID,
                DATE_FORMAT(UpdatedAt, '%Y-%m-%d %H:%i:%s')
             ) ORDER BY SettingKey SEPARATOR '|'), '')
             FROM RED_Addon_Settings WHERE PackageID='$escaped'),
            (SELECT COALESCE(GROUP_CONCAT(CONCAT_WS(
                ':', PackageVersion, PackageType, ManifestSHA256,
                InventorySHA256, LifecycleState,
                DATE_FORMAT(UpdatedAt, '%Y-%m-%d %H:%i:%s')
             ) ORDER BY PackageID SEPARATOR '|'), '')
             FROM RED_Addon_Installations WHERE PackageID='$escaped'),
            (SELECT COALESCE(GROUP_CONCAT(CONCAT_WS(
                ':', AdminRecordID, Capability
             ) ORDER BY AdminRecordID, Capability SEPARATOR '|'), '')
             FROM RED_Admin_Capabilities WHERE AdminRecordID IN ($ids)),
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID='$escaped')
         ), 256)"
    );
}

function red_addon_setting_read_test_insert_setting(
    $connection,
    $packageId,
    $settingKey,
    $valueType,
    $valueJson,
    $secretReference,
    $adminRecordId
) {
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON, SecretReference,
            UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$statement) {
        throw new RuntimeException('Could not prepare setting fixture row.');
    }
    mysqli_stmt_bind_param(
        $statement,
        'sssssi',
        $packageId,
        $settingKey,
        $valueType,
        $valueJson,
        $secretReference,
        $adminRecordId
    );
    if (!mysqli_stmt_execute($statement)) {
        mysqli_stmt_close($statement);
        throw new RuntimeException('Could not create setting fixture row.');
    }
    mysqli_stmt_close($statement);
}

red_addon_setting_read_test_cleanup(
    $connection,
    $packageId,
    [$catalogActorId, $secretActorId, $deniedActorId]
);

try {
    $packageDirectory = $temporaryRoot
        . '/addons/redcms/setting-read-fixture';
    if (!mkdir($packageDirectory, 0700, true)) {
        throw new RuntimeException('Could not create package fixture.');
    }
    $entrypoint = "<?php\nfile_put_contents("
        . var_export($executionMarker, true)
        . ", \"executed\\n\", FILE_APPEND | LOCK_EX);\n"
        . "throw new RuntimeException('must not execute');\n";
    file_put_contents($packageDirectory . '/addon.php', $entrypoint);
    $manifest = red_addon_setting_read_test_manifest(
        $catalogPermission,
        $secretPermission
    );
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
    red_addon_setting_read_test_assert(
        !empty($package['valid'])
            && is_array($snapshot)
            && !file_exists($executionMarker),
        'trusted package discovery remains non-executing'
    );

    $password = password_hash('SettingRead-2026!', PASSWORD_DEFAULT);
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
        [$catalogActorId, 'codex_setting_catalog', 'Catalog Reader',
            'setting-catalog@example.test'],
        [$secretActorId, 'codex_setting_secret', 'Secret Reader',
            'setting-secret@example.test'],
        [$deniedActorId, 'codex_setting_denied', 'Denied Reader',
            'setting-denied@example.test'],
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

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)'
    );
    foreach ([
        [$catalogActorId, $catalogPermission],
        [$secretActorId, $secretPermission],
    ] as $grant) {
        $actorId = $grant[0];
        $capability = $grant[1];
        mysqli_stmt_bind_param(
            $statement,
            'isi',
            $actorId,
            $capability,
            $actorId
        );
        if (!mysqli_stmt_execute($statement)) {
            throw new RuntimeException('Could not create permission fixture.');
        }
    }
    mysqli_stmt_close($statement);

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState, InstalledByAdminRecordID,
            UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $state = 'installed_disabled';
    mysqli_stmt_bind_param(
        $statement,
        'ssssssii',
        $snapshot['id'],
        $snapshot['version'],
        $snapshot['type'],
        $snapshot['manifestSha256'],
        $snapshot['inventorySha256'],
        $state,
        $catalogActorId,
        $catalogActorId
    );
    if (!mysqli_stmt_execute($statement)) {
        throw new RuntimeException('Could not create installation fixture.');
    }
    mysqli_stmt_close($statement);

    red_addon_setting_read_test_insert_setting(
        $connection,
        $packageId,
        'store.name',
        'text',
        '"Configured Store"',
        null,
        $catalogActorId
    );
    red_addon_setting_read_test_insert_setting(
        $connection,
        $packageId,
        'store.enabled',
        'boolean',
        'true',
        null,
        $catalogActorId
    );
    red_addon_setting_read_test_insert_setting(
        $connection,
        $packageId,
        'payment.api-key',
        'secret-reference',
        null,
        'config:redcms.setting-read-fixture.api-key',
        $secretActorId
    );

    $adminIds = [$catalogActorId, $secretActorId, $deniedActorId];
    $before = red_addon_setting_read_test_fingerprint(
        $connection,
        $packageId,
        $adminIds
    );
    $catalog = red_addon_setting_read_model(
        $connection,
        $package,
        $catalogActorId
    );
    red_addon_setting_read_test_assert(
        !empty($catalog['readable'])
            && $catalog['actorRecordId'] === $catalogActorId
            && $catalog['packageId'] === $packageId
            && $catalog['version'] === '1.0.0'
            && $catalog['lifecycleState'] === 'installed_disabled'
            && red_addon_valid_sha256($catalog['modelSha256'])
            && $catalog['reason'] === 'readable'
            && $catalog['settings'] === [
                [
                    'key' => 'store.name',
                    'type' => 'text',
                    'configured' => true,
                    'source' => 'stored',
                    'value' => 'Configured Store',
                ],
                [
                    'key' => 'store.enabled',
                    'type' => 'boolean',
                    'configured' => true,
                    'source' => 'stored',
                    'value' => true,
                ],
                [
                    'key' => 'store.currency',
                    'type' => 'select',
                    'configured' => false,
                    'source' => 'default',
                    'value' => 'USD',
                ],
                [
                    'key' => 'support.email',
                    'type' => 'email',
                    'configured' => false,
                    'source' => 'unset',
                    'value' => null,
                ],
            ],
        'only exact catalog grants receive typed stored, default, and unset values'
    );
    red_addon_setting_read_test_assert(
        !str_contains(json_encode($catalog), 'payment.api-key')
            && !str_contains(json_encode($catalog), 'config:'),
        'a catalog-only model reveals neither secret metadata nor references'
    );
    red_addon_setting_read_test_assert(
        red_addon_setting_read_test_fingerprint(
            $connection,
            $packageId,
            $adminIds
        ) === $before
            && !file_exists($executionMarker),
        'successful settings reads write no database state or package code'
    );
    $catalogRepeat = red_addon_setting_read_model(
        $connection,
        $package,
        $catalogActorId
    );
    red_addon_setting_read_test_assert(
        $catalogRepeat === $catalog,
        'unchanged authorized settings produce one deterministic read model'
    );

    $secret = red_addon_setting_read_model(
        $connection,
        $package,
        $secretActorId
    );
    red_addon_setting_read_test_assert(
        !empty($secret['readable'])
            && $secret['settings'] === [[
                'key' => 'payment.api-key',
                'type' => 'secret-reference',
                'configured' => true,
            ]]
            && !str_contains(json_encode($secret), 'config:'),
        'a secret-setting grant receives only masked configured state'
    );

    $denied = red_addon_setting_read_model(
        $connection,
        $package,
        $deniedActorId
    );
    red_addon_setting_read_test_assert(
        empty($denied['readable'])
            && $denied['settings'] === []
            && $denied['modelSha256'] === ''
            && $denied['reason'] === 'permission_denied',
        'an administrator without an exact grant receives no settings model'
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$catalogActorId
           AND Capability='$catalogPermission'"
    );
    $revoked = red_addon_setting_read_model(
        $connection,
        $package,
        $catalogActorId
    );
    red_addon_setting_read_test_assert(
        empty($revoked['readable'])
            && $revoked['reason'] === 'permission_denied',
        'grant revocation is effective on the next settings read decision'
    );

    $upperCatalogPermission = strtoupper($catalogPermission);
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'isi',
        $catalogActorId,
        $upperCatalogPermission,
        $catalogActorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    $caseDenied = red_addon_setting_read_model(
        $connection,
        $package,
        $catalogActorId
    );
    red_addon_setting_read_test_assert(
        empty($caseDenied['readable'])
            && $caseDenied['reason'] === 'permission_denied',
        'setting read grants remain binary and case-sensitive'
    );
    mysqli_query(
        $connection,
        "DELETE FROM RED_Admin_Capabilities
         WHERE AdminRecordID=$catalogActorId
           AND Capability='$upperCatalogPermission'"
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
        $catalogActorId,
        $catalogPermission,
        $catalogActorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Settings
         SET ValueJSON='[]'
         WHERE PackageID='$packageId' AND SettingKey='store.name'"
    );
    $invalidStored = red_addon_setting_read_model(
        $connection,
        $package,
        $catalogActorId
    );
    red_addon_setting_read_test_assert(
        empty($invalidStored['readable'])
            && $invalidStored['settings'] === []
            && $invalidStored['reason'] === 'stored_value_invalid',
        'malformed stored values fail closed without a partial model'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Settings
         SET ValueJSON='\"Configured Store\"'
         WHERE PackageID='$packageId' AND SettingKey='store.name'"
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET PackageVersion='1.0.1'
         WHERE PackageID='$packageId'"
    );
    $identityDrift = red_addon_setting_read_model(
        $connection,
        $package,
        $catalogActorId
    );
    red_addon_setting_read_test_assert(
        empty($identityDrift['readable'])
            && $identityDrift['settings'] === []
            && $identityDrift['reason'] === 'installation_identity_mismatch',
        'installation identity drift fails before any setting value is returned'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET PackageVersion='1.0.0',
            LifecycleState='enabled' WHERE PackageID='$packageId'"
    );
    $enabled = red_addon_setting_read_model(
        $connection,
        $package,
        $catalogActorId
    );
    red_addon_setting_read_test_assert(
        !empty($enabled['readable'])
            && $enabled['lifecycleState'] === 'enabled'
            && !file_exists($executionMarker),
        'a current enabled installation is readable without runtime execution'
    );
} finally {
    red_addon_setting_read_test_cleanup(
        $connection,
        $packageId,
        [$catalogActorId, $secretActorId, $deniedActorId]
    );
    red_addon_setting_read_test_remove_tree($temporaryRoot);
}

red_addon_setting_read_test_assert(
    red_addon_setting_read_test_scalar(
        $connection,
        "SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Addon_Settings
             WHERE PackageID='redcms.setting-read-fixture'),
            (SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID='redcms.setting-read-fixture'),
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID='redcms.setting-read-fixture'),
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID IN ($catalogActorId,$secretActorId,$deniedActorId)),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities
             WHERE AdminRecordID IN (
                $catalogActorId,$secretActorId,$deniedActorId
             ))
         )"
    ) === '0:0:0:0:0'
        && !file_exists($temporaryRoot),
    'all setting, package, administrator, grant, and filesystem fixtures clean up'
);

fwrite(
    STDOUT,
    'Add-on setting read-model self-test passed (' . $assertions
        . " assertions).\n"
);

?>
