<?php
/**
 * Disposable atomic enable, disable, and re-enable proof for one operational
 * content package. No retained database or package is accepted.
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
require_once $projectRoot . '/includes/addon_enable_helpers.php';
require_once $projectRoot . '/includes/addon_disable_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_operational)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Operational lifecycle self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000979;
$packageId = 'redcms.operational-lifecycle';
$componentId = $packageId . '/item';
$serviceId = $packageId . '/catalog';
$toolId = $packageId . '/items';
$formId = $packageId . '/item-editor';
$routeId = $packageId . '/create-item';
$mutationId = $packageId . '/create-item-command';
$permission = 'operational.items.manage';
$itemsTable = 'RED_Addon_Operational_Lifecycle_Items';
$commandsTable = 'RED_Addon_Operational_Lifecycle_Commands';
$temporaryRoot = sys_get_temp_dir() . '/redcms-operational-lifecycle-' .
    bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/registrar-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_operational_lifecycle_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_operational_lifecycle_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_operational_lifecycle_remove_tree($path)
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

function red_addon_operational_lifecycle_cleanup(
    $connection,
    $packageId,
    $actorId,
    $itemsTable,
    $commandsTable,
    $temporaryRoot
) {
    mysqli_query($connection, 'DROP TABLE IF EXISTS `' . $commandsTable . '`');
    mysqli_query($connection, 'DROP TABLE IF EXISTS `' . $itemsTable . '`');
    $escaped = mysqli_real_escape_string($connection, $packageId);
    foreach (
        [
            'RED_Addon_Settings',
            'RED_Addon_Activity_Log',
            'RED_Addon_Migrations',
            'RED_Addon_Installations',
        ] as $table
    ) {
        mysqli_query(
            $connection,
            "DELETE FROM $table WHERE PackageID='$escaped'"
        );
    }
    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=' .
            (int) $actorId
    );
    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=' . (int) $actorId
    );
    mysqli_query(
        $connection,
        'DELETE FROM RED_Admin WHERE RecordID=' . (int) $actorId
    );
    red_addon_operational_lifecycle_remove_tree($temporaryRoot);
}

function red_addon_operational_lifecycle_package(
    $project,
    $packageId,
    $componentId,
    $serviceId,
    $toolId,
    $formId,
    $routeId,
    $mutationId,
    $permission,
    $itemsTable,
    $commandsTable,
    $executionMarker
) {
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    $migrationDirectory = $directory . '/migrations';
    if (!mkdir($migrationDirectory, 0700, true)
        && !is_dir($migrationDirectory)
    ) {
        throw new RuntimeException('Could not create lifecycle fixture.');
    }
    $entrypoint = "<?php\n" .
        "file_put_contents(" . var_export($executionMarker, true) .
        ", '1', FILE_APPEND);\n" .
        "return static function (RED_Addon_Runtime_Registry \$runtime): void {\n" .
        "    \$handler = static function (): array { return []; };\n" .
        "    \$runtime->registerComponent(" . var_export($componentId, true) .
        ", \$handler);\n" .
        "    \$runtime->registerComponentDataLoader(" .
        var_export($componentId, true) . ", \$handler);\n" .
        "    \$runtime->registerComponentDataCreator(" .
        var_export($componentId, true) . ", \$handler, [" .
        var_export($itemsTable, true) . "]);\n" .
        "    \$runtime->registerComponentDataWriter(" .
        var_export($componentId, true) . ", \$handler, [" .
        var_export($itemsTable, true) . "]);\n" .
        "    \$runtime->registerComponentDataDeleter(" .
        var_export($componentId, true) . ", \$handler, [" .
        var_export($itemsTable, true) . "]);\n" .
        "    \$runtime->registerService(" . var_export($serviceId, true) .
        ", \$handler);\n" .
        "    \$runtime->registerAdminTool(" . var_export($toolId, true) .
        ", \$handler);\n" .
        "    \$runtime->registerAdminToolFormTargetLoader(" .
        var_export($formId, true) . ", \$handler);\n" .
        "    \$runtime->registerAdminToolFormValueLoader(" .
        var_export($formId, true) . ", \$handler);\n" .
        "    \$runtime->registerAdminToolFormInitialValueLoader(" .
        var_export($formId, true) . ", \$handler);\n" .
        "    \$runtime->registerAdminToolFormCreator(" .
        var_export($formId, true) . ", \$handler, [" .
        var_export($itemsTable, true) . "]);\n" .
        "    \$runtime->registerAdminToolFormWriter(" .
        var_export($formId, true) . ", \$handler, [" .
        var_export($itemsTable, true) . "]);\n" .
        "    \$runtime->registerRoute(" . var_export($routeId, true) .
        ", \$handler);\n" .
        "    \$runtime->registerPublicMutation(" .
        var_export($mutationId, true) . ", \$handler, [" .
        var_export($commandsTable, true) . ', ' .
        var_export($itemsTable, true) . "]);\n" .
        "    \$runtime->registerPublicMutationStateLoader(" .
        var_export($mutationId, true) . ", \$handler);\n" .
        "};\n";
    $migrationPath = 'migrations/2026-08-12-operational-lifecycle.sql';
    $migration = "CREATE TABLE $itemsTable (RecordID int unsigned NOT NULL);\n" .
        "CREATE TABLE $commandsTable (RecordID int unsigned NOT NULL);\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    file_put_contents($directory . '/' . $migrationPath, $migration);
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Operational lifecycle fixture',
        'description' => 'Atomic operational lifecycle fixture.',
        'version' => '1.0.0',
        'type' => 'content-package',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [$componentId],
            'services' => [$serviceId],
            'adminTools' => [$toolId],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$permission],
        'componentEditors' => [[
            'component' => $componentId,
            'label' => 'Item',
            'description' => 'Select one item.',
            'icon' => 'products',
            'permissions' => array_fill_keys(
                ['create', 'view', 'edit', 'delete', 'publish', 'restore'],
                $permission
            ),
            'fields' => [[
                'key' => 'item-id',
                'label' => 'Item ID',
                'type' => 'text',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 64,
            ]],
        ]],
        'adminToolContracts' => [[
            'tool' => $toolId,
            'label' => 'Items',
            'description' => 'Create or review items.',
            'icon' => 'products',
            'permission' => $permission,
            'mode' => 'read-only',
        ]],
        'adminToolFormContracts' => [[
            'tool' => $toolId,
            'form' => $formId,
            'label' => 'Item details',
            'description' => 'Create or edit one item.',
            'create' => [
                'label' => 'Add item',
                'description' => 'Create one draft item.',
            ],
            'permission' => $permission,
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/json',
            'maxBodyBytes' => 4096,
            'runtimeSettings' => ['catalog.currency'],
            'fields' => [[
                'key' => 'id',
                'label' => 'Item ID',
                'type' => 'text',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 64,
            ]],
        ]],
        'settings' => [[
            'key' => 'catalog.currency',
            'label' => 'Catalog currency',
            'type' => 'text',
            'secret' => false,
            'permission' => $permission,
            'default' => null,
        ]],
        'migrations' => [[
            'id' => '2026-08-12-operational-lifecycle',
            'path' => $migrationPath,
            'sha256' => hash('sha256', $migration),
        ]],
        'routes' => [[
            'id' => $routeId,
            'scope' => 'public',
            'path' => '/addons/redcms/operational-lifecycle/create-item',
            'methods' => ['POST'],
            'authentication' => 'public',
            'csrf' => 'required',
        ]],
        'publicMutationContracts' => [[
            'route' => $routeId,
            'mutation' => $mutationId,
            'scope' => 'public',
            'authentication' => 'public',
            'method' => 'POST',
            'csrf' => 'required',
            'encoding' => 'application/x-www-form-urlencoded',
            'maxBodyBytes' => 512,
            'requestFields' => [[
                'key' => 'item',
                'type' => 'identifier',
                'required' => true,
                'minLength' => 1,
                'maxLength' => 64,
            ]],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => [$commandsTable, $itemsTable],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.item.created',
            'outcomes' => ['accepted', 'unchanged'],
            'runtimeSettings' => ['catalog.currency'],
        ]],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [
                ['path' => 'addon.php', 'sha256' => hash('sha256', $entrypoint)],
                [
                    'path' => $migrationPath,
                    'sha256' => hash('sha256', $migration),
                ],
            ],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
    file_put_contents(
        $directory . '/addon.json',
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n"
    );
}

function red_addon_operational_lifecycle_record_installation(
    $connection,
    array $package,
    $actorId
) {
    $snapshot = red_addon_registry_snapshot($package);
    if (!is_array($snapshot)) {
        return false;
    }
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState, InstalledByAdminRecordID,
            UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, \'installed_disabled\', ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'sssssii',
        $snapshot['id'],
        $snapshot['version'],
        $snapshot['type'],
        $snapshot['manifestSha256'],
        $snapshot['inventorySha256'],
        $actorId,
        $actorId
    );
    $recorded = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    if (!$recorded) {
        return false;
    }
    foreach ($snapshot['migrations'] as $migration) {
        $statement = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Migrations (
                PackageID, MigrationID, MigrationPath, Checksum,
                AppliedByAdminRecordID, ExecutionMs
             ) VALUES (?, ?, ?, ?, ?, 0)'
        );
        mysqli_stmt_bind_param(
            $statement,
            'ssssi',
            $snapshot['id'],
            $migration['id'],
            $migration['path'],
            $migration['sha256'],
            $actorId
        );
        $recorded = mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        if (!$recorded) {
            return false;
        }
    }
    return true;
}

function red_addon_operational_lifecycle_data_fingerprint(
    $connection,
    $packageId,
    $itemsTable,
    $commandsTable
) {
    $escaped = mysqli_real_escape_string($connection, $packageId);
    return red_addon_operational_lifecycle_scalar(
        $connection,
        "SELECT SHA2(GROUP_CONCAT(ValueText ORDER BY ValueText SEPARATOR '|'), 256)
         FROM (
            SELECT CONCAT_WS('#', PackageID, MigrationID, MigrationPath, Checksum)
                AS ValueText
            FROM RED_Addon_Migrations WHERE PackageID='$escaped'
            UNION ALL
            SELECT CONCAT_WS('#', PackageID, SettingKey, ValueType, ValueJSON)
            FROM RED_Addon_Settings WHERE PackageID='$escaped'
            UNION ALL
            SELECT CONCAT_WS('#', 'item', RecordID, ItemCode) FROM `$itemsTable`
            UNION ALL
            SELECT CONCAT_WS('#', 'command', RecordID, CommandCode)
            FROM `$commandsTable`
         ) AS red_operational_data"
    );
}

red_addon_operational_lifecycle_cleanup(
    $connection,
    $packageId,
    $actorId,
    $itemsTable,
    $commandsTable,
    $temporaryRoot
);

try {
    $password = password_hash('OperationalLifecycle-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_operational_lifecycle', ?, 'Admin', 'OpsLife',
                   'webmaster', '', '', 'lifecycle@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)'
    );
    foreach (['addons.enable', 'addons.disable', $permission] as $capability) {
        mysqli_stmt_bind_param($statement, 'isi', $actorId, $capability, $actorId);
        mysqli_stmt_execute($statement);
    }
    mysqli_stmt_close($statement);

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_operational_lifecycle_package(
        $fixtureProject,
        $packageId,
        $componentId,
        $serviceId,
        $toolId,
        $formId,
        $routeId,
        $mutationId,
        $permission,
        $itemsTable,
        $commandsTable,
        $executionMarker
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][$packageId] ?? [];
    red_addon_operational_lifecycle_assert(
        !empty($catalog['valid'])
            && !empty($package['valid'])
            && !file_exists($executionMarker),
        'trusted operational package discovery remains non-executing'
    );
    red_addon_operational_lifecycle_assert(
        red_addon_operational_lifecycle_record_installation(
            $connection,
            $package,
            $actorId
        ),
        'fixture starts installed-disabled with exact migration evidence'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$itemsTable` (
            RecordID int unsigned NOT NULL AUTO_INCREMENT,
            ItemCode varchar(64) NOT NULL,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$commandsTable` (
            RecordID int unsigned NOT NULL AUTO_INCREMENT,
            CommandCode varchar(64) NOT NULL,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    mysqli_query(
        $connection,
        "INSERT INTO `$itemsTable` (ItemCode) VALUES ('preserved-item')"
    );
    mysqli_query(
        $connection,
        "INSERT INTO `$commandsTable` (CommandCode)
         VALUES ('preserved-command')"
    );
    $valueJson = json_encode('USD');
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings
         (PackageID, SettingKey, ValueType, ValueJSON,
          SecretReference, UpdatedByAdminRecordID)
         VALUES (?, \'catalog.currency\', \'text\', ?, NULL, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'ssi',
        $packageId,
        $valueJson,
        $actorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    $dataFingerprint = red_addon_operational_lifecycle_data_fingerprint(
        $connection,
        $packageId,
        $itemsTable,
        $commandsTable
    );
    $codeFingerprint = hash_file(
        'sha256',
        $package['path'] . '/addon.php'
    );
    red_addon_operational_lifecycle_assert(
        red_addon_valid_sha256($dataFingerprint)
            && red_addon_valid_sha256($codeFingerprint),
        'package business data, settings, migrations, and code are fingerprinted'
    );

    $plan = red_addon_enable_transition_plan(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    $repeatPlan = red_addon_enable_transition_plan(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    red_addon_operational_lifecycle_assert(
        !empty($plan['valid'])
            && !empty($plan['transitionReady'])
            && ($plan['activationProfile']['id'] ?? '')
                === 'operational_content_package'
            && red_addon_valid_sha256(
                $plan['operationalEvidenceSha256']
            )
            && hash_equals($plan['planSha256'], $repeatPlan['planSha256'])
            && !file_exists($executionMarker),
        'Owner receives one deterministic non-executing operational transition plan'
    );
    $stale = red_addon_enable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        str_repeat('f', 64)
    );
    red_addon_operational_lifecycle_assert(
        $stale['status'] === 'plan_changed'
            && !file_exists($executionMarker),
        'stale operational plan is rejected before registrar or state change'
    );
    $registrarFailure = red_addon_enable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        null,
        static function () {
            throw new RuntimeException('forced_operational_registrar_failure');
        }
    );
    red_addon_operational_lifecycle_assert(
        $registrarFailure['status'] === 'registrar_validation_failed'
            && !file_exists($executionMarker),
        'registrar failure leaves operational package installed-disabled'
    );
    $incompleteRegistrar = red_addon_enable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        null,
        static function (array $package) {
            return new RED_Addon_Runtime_Registry(
                $package['id'],
                $package['manifest']
            );
        }
    );
    red_addon_operational_lifecycle_assert(
        $incompleteRegistrar['status'] === 'registrar_validation_failed'
            && !file_exists($executionMarker),
        'incomplete runtime registration shape cannot enable the package'
    );
    $engineDrift = red_addon_enable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        null,
        static function (array $package) use ($connection, $itemsTable) {
            $registry = red_addon_runtime_register_package($package);
            mysqli_query(
                $connection,
                "ALTER TABLE `$itemsTable` ENGINE=MyISAM"
            );
            return $registry;
        }
    );
    red_addon_operational_lifecycle_assert(
        $engineDrift['status'] === 'registrar_validation_failed'
            && red_addon_operational_lifecycle_scalar(
                $connection,
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='$packageId'"
            ) === 'installed_disabled',
        'registrar-time non-InnoDB drift blocks enable before state mutation'
    );
    mysqli_query($connection, "ALTER TABLE `$itemsTable` ENGINE=InnoDB");
    $auditFailure = red_addon_enable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256'],
        static function () {
            return false;
        }
    );
    red_addon_operational_lifecycle_assert(
        $auditFailure['status'] === 'enable_transaction_failed'
            && red_addon_operational_lifecycle_scalar(
                $connection,
                "SELECT CONCAT_WS(':', LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='$packageId'))
                 FROM RED_Addon_Installations
                 WHERE PackageID='$packageId'"
            ) === 'installed_disabled:0'
            && file_get_contents($executionMarker) === '11',
        'audit failure rolls back operational state and audit after registrar validation'
    );

    $enabled = red_addon_enable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $plan['planSha256']
    );
    red_addon_operational_lifecycle_assert(
        $enabled['status'] === 'enabled'
            && red_addon_valid_sha256($enabled['registrarEvidenceSha256'])
            && count($enabled['runtimeRegistrations']['registrations']) === 18
            && file_get_contents($executionMarker) === '111',
        'exact operational registrar evidence precedes atomic enable completion'
    );
    red_addon_operational_lifecycle_assert(
        red_addon_operational_lifecycle_scalar(
            $connection,
            "SELECT CONCAT_WS(':', LifecycleState,
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$packageId'
                   AND EventName='addon.enable.completed'))
             FROM RED_Addon_Installations WHERE PackageID='$packageId'"
        ) === 'enabled:1',
        'enabled state and one bounded audit fact commit together'
    );
    $firstRegistrarEvidence = $enabled['registrarEvidenceSha256'];
    $runtimeEnabled = red_addon_runtime_bootstrap(
        $connection,
        $fixtureProject
    );
    red_addon_operational_lifecycle_assert(
        $runtimeEnabled['context']->handler('components', $componentId) !== null
            && $runtimeEnabled['context']->handler('services', $serviceId) !== null
            && $runtimeEnabled['context']->handler(
                'publicMutationHandlers',
                $mutationId
            ) !== null
            && file_get_contents($executionMarker) === '1111',
        'later request bootstrap loads the enabled operational package'
    );

    $disablePlan = red_addon_disable_transition_plan(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    red_addon_operational_lifecycle_assert(
        !empty($disablePlan['valid'])
            && !empty($disablePlan['transitionReady'])
            && !file_exists($temporaryRoot . '/disable-executed'),
        'existing non-executing disable plan accepts the operational package'
    );
    $markerBeforeDisable = file_get_contents($executionMarker);
    $disableAuditFailure = red_addon_disable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $disablePlan['planSha256'],
        static function () {
            return false;
        }
    );
    red_addon_operational_lifecycle_assert(
        $disableAuditFailure['status'] === 'disable_transaction_failed'
            && file_get_contents($executionMarker) === $markerBeforeDisable
            && red_addon_operational_lifecycle_scalar(
                $connection,
                "SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='$packageId'"
            ) === 'enabled',
        'disable audit failure rolls back state without package execution'
    );
    $disabled = red_addon_disable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $disablePlan['planSha256']
    );
    red_addon_operational_lifecycle_assert(
        $disabled['status'] === 'installed_disabled'
            && file_get_contents($executionMarker) === $markerBeforeDisable,
        'operational disable completes without executing package code'
    );
    red_addon_operational_lifecycle_assert(
        hash_equals(
            $dataFingerprint,
            red_addon_operational_lifecycle_data_fingerprint(
                $connection,
                $packageId,
                $itemsTable,
                $commandsTable
            )
        )
            && hash_equals(
                $codeFingerprint,
                hash_file('sha256', $package['path'] . '/addon.php')
            ),
        'disable preserves exact settings, migrations, business rows, and code'
    );
    $runtimeDisabled = red_addon_runtime_bootstrap(
        $connection,
        $fixtureProject
    );
    red_addon_operational_lifecycle_assert(
        $runtimeDisabled['context']->handler('components', $componentId) === null
            && $runtimeDisabled['context']->handler('services', $serviceId) === null
            && file_get_contents($executionMarker) === $markerBeforeDisable,
        'later request bootstrap excludes disabled operational registrations'
    );

    $reEnablePlan = red_addon_enable_transition_plan(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    $reEnabled = red_addon_enable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $reEnablePlan['planSha256']
    );
    red_addon_operational_lifecycle_assert(
        !empty($reEnablePlan['valid'])
            && ($reEnablePlan['activationProfile']['id'] ?? '')
                === 'operational_content_package'
            && $reEnabled['status'] === 'enabled'
            && hash_equals(
                $firstRegistrarEvidence,
                $reEnabled['registrarEvidenceSha256']
            ),
        'disable-to-re-enable repeats the same exact registrar validation'
    );
    red_addon_operational_lifecycle_assert(
        hash_equals(
            $dataFingerprint,
            red_addon_operational_lifecycle_data_fingerprint(
                $connection,
                $packageId,
                $itemsTable,
                $commandsTable
            )
        )
            && red_addon_operational_lifecycle_scalar(
                $connection,
                "SELECT CONCAT_WS(':', LifecycleState,
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='$packageId'
                       AND EventName='addon.enable.completed'),
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='$packageId'
                       AND EventName='addon.disable.completed'))
                 FROM RED_Addon_Installations WHERE PackageID='$packageId'"
            ) === 'enabled:2:1',
        're-enable preserves data and records exactly two enables and one disable'
    );

    $enableCli = file_get_contents(
        $projectRoot . '/scripts/admin-addon-enable.php'
    );
    $disableCli = file_get_contents(
        $projectRoot . '/scripts/admin-addon-disable.php'
    );
    red_addon_operational_lifecycle_assert(
        is_string($enableCli)
            && is_string($disableCli)
            && str_contains($enableCli, '--confirm-backup-sha256=')
            && str_contains($disableCli, '--confirm-backup-sha256=')
            && !file_exists($projectRoot . '/admin/bin/addon_enable.php')
            && !file_exists($projectRoot . '/admin/bin/addon_disable.php'),
        'operational lifecycle remains CLI-only and backup-confirmed'
    );
} finally {
    red_addon_operational_lifecycle_cleanup(
        $connection,
        $packageId,
        $actorId,
        $itemsTable,
        $commandsTable,
        $temporaryRoot
    );
    red_addon_operational_lifecycle_assert(
        red_addon_operational_lifecycle_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId),
                (SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME IN ('$itemsTable', '$commandsTable')))"
        ) === '0:0:0:0:0:0'
            && !is_dir($temporaryRoot),
        'operational lifecycle fixtures clean up exactly'
    );
}

printf(
    "Operational add-on lifecycle self-test passed: %d assertions.\n",
    $assertions
);
