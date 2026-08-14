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
require_once $projectRoot . '/includes/addon_setting_editor_helpers.php';
require_once $projectRoot . '/includes/addon_setting_write_helpers.php';
require_once $projectRoot . '/includes/addon_secret_replacement_helpers.php';

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

$unsignedMetadataSources = '';
foreach ([
    'includes/addon_setting_storage_helpers.php',
    'includes/addon_component_persistence_helpers.php',
    'includes/addon_admin_tool_action_execution_helpers.php',
    'includes/addon_public_mutation_rate_limit_helpers.php',
    'includes/addon_public_mutation_subject_helpers.php',
    'includes/addon_public_mutation_execution_helpers.php',
    'includes/addon_public_mutation_idempotency_helpers.php',
] as $relativePath) {
    $source = file_get_contents($projectRoot . '/' . $relativePath);
    red_addon_setting_storage_test_assert(
        is_string($source),
        'unsigned metadata contract source is readable: ' . $relativePath
    );
    $unsignedMetadataSources .= (string) $source;
}
red_addon_setting_storage_test_assert(
    preg_match_all(
        "/DATA_TYPE='int'\\s+AND COLUMN_TYPE LIKE 'int% unsigned'/",
        $unsignedMetadataSources
    ) === 12
        && preg_match_all(
            "/DATA_TYPE='smallint'\\s+AND COLUMN_TYPE LIKE 'smallint% unsigned'/",
            $unsignedMetadataSources
        ) === 1
        && preg_match_all(
            "/DATA_TYPE='bigint'\\s+AND COLUMN_TYPE LIKE 'bigint% unsigned'/",
            $unsignedMetadataSources
        ) === 1
        && preg_match(
            "/COLUMN_TYPE='(?:smallint|int|bigint) unsigned'/",
            $unsignedMetadataSources
        ) === 0,
    'unsigned schema guards accept legacy display widths without weakening type checks'
);

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

    $auditFailure = red_addon_setting_write(
        $connection,
        $package,
        $actorId,
        $values,
        $preflight['planSha256'],
        static function () {
            return false;
        }
    );
    red_addon_setting_storage_test_assert(
        $auditFailure['status'] === 'audit_failed'
            && red_addon_setting_storage_test_scalar(
                $connection,
                "SELECT CONCAT_WS(
                    ':',
                    (SELECT COUNT(*) FROM RED_Addon_Settings
                     WHERE PackageID='redcms.storage-fixture'),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.storage-fixture')
                 )"
            ) === '0:0',
        'audit failure rolls back the complete replacement atomically'
    );

    $injectedFailure = red_addon_setting_write(
        $connection,
        $package,
        $actorId,
        $values,
        $preflight['planSha256'],
        null,
        static function () {
            return false;
        }
    );
    red_addon_setting_storage_test_assert(
        $injectedFailure['status'] === 'injected_failure'
            && red_addon_setting_storage_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='redcms.storage-fixture'"
            ) === '0',
        'injected late failure rolls back every setting row'
    );

    $updated = red_addon_setting_write(
        $connection,
        $package,
        $actorId,
        $values,
        $preflight['planSha256']
    );
    red_addon_setting_storage_test_assert(
        $updated['status'] === 'updated'
            && hash_equals(
                $preflight['targetStateSha256'],
                $updated['stateSha256']
            )
            && red_addon_setting_storage_test_scalar(
                $connection,
                "SELECT CONCAT_WS(
                    ':',
                    (SELECT COUNT(*) FROM RED_Addon_Settings
                     WHERE PackageID='redcms.storage-fixture'),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.storage-fixture'
                       AND EventName='addon.settings.updated'
                       AND Result='succeeded'
                       AND DetailCode='settings_updated')
                 )"
            ) === '3:1',
        'exact plan atomically stores the complete target plus one bounded audit fact'
    );
    red_addon_setting_storage_test_assert(
        red_addon_setting_storage_test_scalar(
            $connection,
            "SELECT CONCAT_WS(
                ':',
                (SELECT ValueJSON FROM RED_Addon_Settings
                 WHERE PackageID='redcms.storage-fixture'
                   AND SettingKey='store.name'),
                (SELECT ValueJSON FROM RED_Addon_Settings
                 WHERE PackageID='redcms.storage-fixture'
                   AND SettingKey='store.enabled'),
                (SELECT ValueJSON IS NULL FROM RED_Addon_Settings
                 WHERE PackageID='redcms.storage-fixture'
                   AND SettingKey='payment.api-key'),
                (SELECT SecretReference FROM RED_Addon_Settings
                 WHERE PackageID='redcms.storage-fixture'
                   AND SettingKey='payment.api-key')
             )"
        ) === '"Fixture Store":false:1:config:redcms.storage-fixture.api-key',
        'ordinary defaults and opaque secret references persist in separate columns'
    );

    $editorContext = red_addon_setting_editor_context(
        $connection,
        $package,
        $actorId
    );
    $editorHtml = red_addon_setting_editor_render(
        $editorContext,
        str_repeat('b', 64)
    );
    red_addon_setting_storage_test_assert(
        !empty($editorContext['ready'])
            && red_addon_valid_sha256($editorContext['planSha256'])
            && str_contains($editorHtml, 'Settings[store.name]')
            && str_contains($editorHtml, 'data-red-addon-secret-state="configured"')
            && !str_contains($editorHtml, 'config:redcms.storage-fixture.api-key'),
        'authorized settings context renders ordinary controls and masks the stored secret reference'
    );
    $editorNoop = red_addon_setting_editor_update(
        $connection,
        $package,
        $actorId,
        [
            'store.name' => 'Fixture Store',
            'store.enabled' => '0',
        ],
        $editorContext['planSha256']
    );
    red_addon_setting_storage_test_assert(
        !empty($editorNoop['ok'])
            && $editorNoop['status'] === 'unchanged',
        'the core settings editor preserves the secret row on an exact no-op'
    );

    $replacementReference = 'config:redcms.storage-fixture.rotated-key';
    putenv(
        'RED_ADDON_SECRET_REFERENCES='
            . $values['payment.api-key'] . ',' . $replacementReference
    );
    putenv(
        'RED_ADDON_SECRET_VALUES_JSON=' . json_encode([
            $values['payment.api-key'] => 'fixture-original-secret',
            $replacementReference => 'fixture-rotated-secret',
        ], JSON_UNESCAPED_SLASHES)
    );
    $replacementTarget = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        ['payment.api-key' => $replacementReference]
    );
    red_addon_setting_storage_test_assert(
        !empty($replacementTarget['valid'])
            && red_addon_valid_sha256(
                $replacementTarget['plan']['planSha256'] ?? ''
            ),
        'a provisioned server-local reference produces a complete replacement plan'
    );
    $replacement = red_addon_secret_replacement_update(
        $connection,
        $package,
        $actorId,
        ['payment.api-key' => $replacementReference],
        $replacementTarget['plan']['planSha256']
    );
    red_addon_setting_storage_test_assert(
        !empty($replacement['ok'])
            && $replacement['status'] === 'updated'
            && red_addon_setting_storage_test_scalar(
                $connection,
                "SELECT CONCAT_WS(
                    ':',
                    (SELECT SecretReference FROM RED_Addon_Settings
                     WHERE PackageID='redcms.storage-fixture'
                       AND SettingKey='payment.api-key'),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='redcms.storage-fixture'
                       AND EventName='addon.settings.updated'
                       AND DetailCode='secret_reference_replaced')
                 )"
            ) === $replacementReference . ':1',
        'the replacement delegates to the atomic writer and records no secret value'
    );
    $replacementNoopTarget = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        ['payment.api-key' => $replacementReference]
    );
    $replacementNoop = red_addon_secret_replacement_update(
        $connection,
        $package,
        $actorId,
        ['payment.api-key' => $replacementReference],
        $replacementNoopTarget['plan']['planSha256']
    );
    red_addon_setting_storage_test_assert(
        !empty($replacementNoop['ok'])
            && $replacementNoop['status'] === 'unchanged'
            && red_addon_setting_storage_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.storage-fixture'
                   AND EventName='addon.settings.updated'
                   AND DetailCode='secret_reference_replaced'"
            ) === '1',
        'an exact secret-reference replacement no-op adds no audit fact'
    );

    mysqli_query(
        $connection,
        "DELETE FROM RED_Addon_Settings
         WHERE PackageID='redcms.storage-fixture'
           AND SettingKey='payment.api-key'"
    );
    $initialReplacementTarget = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        ['payment.api-key' => $replacementReference]
    );
    $initialReplacement = red_addon_secret_replacement_update(
        $connection,
        $package,
        $actorId,
        ['payment.api-key' => $replacementReference],
        $initialReplacementTarget['plan']['planSha256']
    );
    red_addon_setting_storage_test_assert(
        !empty($initialReplacement['ok'])
            && $initialReplacement['status'] === 'updated'
            && red_addon_setting_storage_test_scalar(
                $connection,
                "SELECT SecretReference FROM RED_Addon_Settings
                 WHERE PackageID='redcms.storage-fixture'
                   AND SettingKey='payment.api-key'"
            ) === $replacementReference,
        'the replacement can bind an initially missing secret row without accepting a secret value'
    );
    $values['payment.api-key'] = $replacementReference;

    $unavailableTarget = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        ['payment.api-key' => 'config:redcms.storage-fixture.missing']
    );
    red_addon_setting_storage_test_assert(
        empty($unavailableTarget['valid'])
            && $unavailableTarget['reason'] === 'secret_unavailable',
        'a reference without an allowlisted provisioned value fails before writing'
    );
    $staleReplacement = red_addon_secret_replacement_update(
        $connection,
        $package,
        $actorId,
        ['payment.api-key' => $replacementReference],
        str_repeat('c', 64)
    );
    red_addon_setting_storage_test_assert(
        empty($staleReplacement['ok'])
            && $staleReplacement['reason'] === 'stale_plan',
        'a stale secret replacement plan is refused without mutation'
    );
    putenv('RED_ADDON_SECRET_REFERENCES');
    putenv('RED_ADDON_SECRET_VALUES_JSON');

    $repeatPlan = red_addon_setting_write_preflight(
        $connection,
        $package,
        $actorId,
        $values
    );
    $unchanged = red_addon_setting_write(
        $connection,
        $package,
        $actorId,
        $values,
        $repeatPlan['planSha256']
    );
    red_addon_setting_storage_test_assert(
        $unchanged['status'] === 'unchanged'
            && red_addon_setting_storage_test_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.storage-fixture'
                   AND EventName='addon.settings.updated'"
            ) === '3',
        'an exact no-op commits no replacement and no duplicate audit fact'
    );

    mysqli_begin_transaction($connection);
    $nested = red_addon_setting_write(
        $connection,
        $package,
        $actorId,
        $values,
        $repeatPlan['planSha256']
    );
    mysqli_rollback($connection);
    red_addon_setting_storage_test_assert(
        $nested['status'] === 'invalid',
        'the writer refuses a caller-owned transaction before locking or mutation'
    );

    $changedValues = $values + ['store.name' => 'Changed Store'];
    $planChanged = red_addon_setting_write(
        $connection,
        $package,
        $actorId,
        $changedValues,
        $repeatPlan['planSha256']
    );
    red_addon_setting_storage_test_assert(
        $planChanged['status'] === 'plan_changed'
            && red_addon_setting_storage_test_scalar(
                $connection,
                "SELECT ValueJSON FROM RED_Addon_Settings
                 WHERE PackageID='redcms.storage-fixture'
                   AND SettingKey='store.name'"
            ) === '"Fixture Store"',
        'changed target values invalidate the caller plan without mutation'
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
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID='redcms.storage-fixture'),
            (SELECT COUNT(*) FROM RED_Admin
             WHERE RecordID IN ($actorId,$otherId)),
            (SELECT COUNT(*) FROM RED_Admin_Capabilities
             WHERE AdminRecordID IN ($actorId,$otherId))
         )"
    ) === '0:0:0:0:0'
        && !file_exists($temporaryRoot),
    'all storage, package, administrator, grant, and filesystem fixtures clean up'
);

fwrite(
    STDOUT,
    'Add-on setting storage preflight self-test passed (' . $assertions
        . " assertions).\n"
);

?>
