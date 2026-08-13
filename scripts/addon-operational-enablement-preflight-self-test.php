<?php
/**
 * Disposable checks for read-only operational add-on readiness evidence.
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
require_once $projectRoot .
    '/includes/addon_operational_enablement_preflight_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_operational)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Operational enablement preflight self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000978;
$packageId = 'redcms.operational-readiness';
$routeId = 'redcms.operational-readiness/create-item';
$mutationId = 'redcms.operational-readiness/create-item-command';
$permission = 'operational.items.manage';
$itemsTable = 'RED_Addon_Operational_Readiness_Items';
$commandsTable = 'RED_Addon_Operational_Readiness_Commands';
$temporaryRoot = sys_get_temp_dir() . '/redcms-operational-readiness-' .
    bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_operational_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_operational_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_operational_test_remove_tree($path)
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

function red_addon_operational_test_cleanup(
    $connection,
    $packageId,
    $actorId,
    $itemsTable,
    $commandsTable,
    $temporaryRoot
) {
    try {
        mysqli_query($connection, 'DROP TABLE IF EXISTS `' . $commandsTable . '`');
        mysqli_query($connection, 'DROP TABLE IF EXISTS `' . $itemsTable . '`');
        $escaped = mysqli_real_escape_string($connection, $packageId);
        foreach ([
            'RED_Addon_Settings',
            'RED_Addon_Activity_Log',
            'RED_Addon_Migrations',
            'RED_Addon_Installations',
        ] as $table) {
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
    } catch (Throwable $throwable) {
        error_log(
            'Operational enablement preflight cleanup failed: ' .
                $throwable->getMessage()
        );
    }
    red_addon_operational_test_remove_tree($temporaryRoot);
}

function red_addon_operational_test_create_package(
    $project,
    $packageId,
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
    if (!mkdir($migrationDirectory, 0700, true) && !is_dir($migrationDirectory)) {
        throw new RuntimeException('Could not create operational fixture.');
    }
    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) . ", 'executed');\n" .
        "return static function (RED_Addon_Runtime_Registry \$runtime): void {};\n";
    $migrationPath = 'migrations/2026-08-12-operational-readiness.sql';
    $migration = "CREATE TABLE $itemsTable (RecordID int unsigned NOT NULL);\n" .
        "CREATE TABLE $commandsTable (RecordID int unsigned NOT NULL);\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    file_put_contents($directory . '/' . $migrationPath, $migration);
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Operational readiness fixture',
        'description' => 'Non-executing operational enablement fixture.',
        'version' => '1.0.0',
        'type' => 'content-package',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [$packageId . '/item'],
            'services' => [$packageId . '/catalog'],
            'adminTools' => [$packageId . '/items'],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$permission],
        'componentEditors' => [[
            'component' => $packageId . '/item',
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
            'tool' => $packageId . '/items',
            'label' => 'Items',
            'description' => 'Create or review items.',
            'icon' => 'products',
            'permission' => $permission,
            'mode' => 'read-only',
        ]],
        'adminToolFormContracts' => [[
            'tool' => $packageId . '/items',
            'form' => $packageId . '/item-editor',
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
            'id' => '2026-08-12-operational-readiness',
            'path' => $migrationPath,
            'sha256' => hash('sha256', $migration),
        ]],
        'routes' => [[
            'id' => $routeId,
            'scope' => 'public',
            'path' => '/addons/redcms/operational-readiness/create-item',
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
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) .
            "\n"
    );
}

function red_addon_operational_test_record_installation(
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

red_addon_operational_test_cleanup(
    $connection,
    $packageId,
    $actorId,
    $itemsTable,
    $commandsTable,
    $temporaryRoot
);

try {
    $password = password_hash('OperationalReadiness-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_operational_readiness', ?, 'Admin',
                   'Operational', 'webmaster', '', '',
                   'operational@example.test', 'N', 'to', 'N', 'to')"
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
    foreach (['addons.enable', $permission] as $capability) {
        mysqli_stmt_bind_param($statement, 'isi', $actorId, $capability, $actorId);
        mysqli_stmt_execute($statement);
    }
    mysqli_stmt_close($statement);

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_operational_test_create_package(
        $fixtureProject,
        $packageId,
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
    if (empty($catalog['valid']) || empty($package['valid'])) {
        fwrite(
            STDERR,
            'Operational fixture discovery errors: ' .
                json_encode([
                    'catalog' => $catalog['errors'] ?? [],
                    'package' => $package['errors'] ?? [],
                ], JSON_UNESCAPED_SLASHES) . "\n"
        );
    }
    red_addon_operational_test_assert(
        !empty($catalog['valid'])
            && !empty($package['valid'])
            && !file_exists($executionMarker),
        'trusted operational package discovery remains non-executing'
    );
    $contract = red_addon_operational_contract($package['manifest']);
    red_addon_operational_test_assert(
        !empty($contract['valid'])
            && $contract['componentCount'] === 1
            && $contract['adminToolCount'] === 1
            && $contract['adminFormCount'] === 1
            && $contract['migrationCount'] === 1
            && $contract['settingCount'] === 1
            && $contract['publicMutationCount'] === 1,
        'closed operational content-package surface is accepted as data only'
    );
    $secretManifest = $package['manifest'];
    $secretManifest['settings'][0]['type'] = 'secret-reference';
    $secretManifest['settings'][0]['secret'] = true;
    unset($secretManifest['settings'][0]['default']);
    red_addon_operational_test_assert(
        empty(red_addon_operational_contract($secretManifest)['valid']),
        'secret-bearing operational mutation packages remain out of profile'
    );
    $networkManifest = $package['manifest'];
    $networkManifest['outboundHosts'] = ['api.example.test'];
    red_addon_operational_test_assert(
        empty(red_addon_operational_contract($networkManifest)['valid']),
        'network-bearing operational packages remain out of profile'
    );

    red_addon_operational_test_assert(
        red_addon_operational_test_record_installation(
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
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$commandsTable` (
            RecordID int unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    $withoutSetting = red_addon_operational_enablement_preflight(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    red_addon_operational_test_assert(
        !empty($withoutSetting['valid'])
            && empty($withoutSetting['operationalEvidenceReady'])
            && $withoutSetting['gates']['settingsConfiguration'] === 'blocked'
            && $withoutSetting['gates']['publicMutations'] === 'blocked'
            && !$withoutSetting['packageExecution'],
        'missing per-client settings block operational evidence without execution'
    );

    $settingKey = 'catalog.currency';
    $valueType = 'text';
    $valueJson = json_encode('USD');
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings
         (PackageID, SettingKey, ValueType, ValueJSON,
          SecretReference, UpdatedByAdminRecordID)
         VALUES (?, ?, ?, ?, NULL, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'ssssi',
        $packageId,
        $settingKey,
        $valueType,
        $valueJson,
        $actorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    $before = red_addon_operational_test_scalar(
        $connection,
        "SELECT CONCAT(
            (SELECT LifecycleState FROM RED_Addon_Installations
             WHERE PackageID='$packageId'), ':',
            (SELECT COUNT(*) FROM RED_Addon_Migrations
             WHERE PackageID='$packageId'), ':',
            (SELECT COUNT(*) FROM RED_Addon_Settings
             WHERE PackageID='$packageId'), ':',
            (SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID='$packageId'))"
    );
    $ready = red_addon_operational_enablement_preflight(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    red_addon_operational_test_assert(
        red_addon_operational_enablement_preflight_is_valid($ready)
            && !empty($ready['operationalEvidenceReady'])
            && !$ready['enableReady']
            && !$ready['activationSupported']
            && !$ready['stateMutation']
            && !$ready['runtimeLoad']
            && !$ready['packageExecution']
            && $ready['migrationCount'] === 1
            && $ready['settingCount'] === 1
            && $ready['configuredSettingCount'] === 1
            && $ready['publicMutationCount'] === 1
            && $ready['readyPublicMutationCount'] === 1
            && array_column($ready['blockers'], 'code') === [
                'atomic_operational_enablement_required',
                'registrar_validation_required',
            ],
        'complete evidence is deterministic but cannot enable the package'
    );
    red_addon_operational_test_assert(
        $before === 'installed_disabled:1:1:0'
            && $before === red_addon_operational_test_scalar(
                $connection,
                "SELECT CONCAT(
                    (SELECT LifecycleState FROM RED_Addon_Installations
                     WHERE PackageID='$packageId'), ':',
                    (SELECT COUNT(*) FROM RED_Addon_Migrations
                     WHERE PackageID='$packageId'), ':',
                    (SELECT COUNT(*) FROM RED_Addon_Settings
                     WHERE PackageID='$packageId'), ':',
                    (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                     WHERE PackageID='$packageId'))"
            )
            && !file_exists($executionMarker),
        'readiness leaves registry, settings, audit, and package execution unchanged'
    );
    $again = red_addon_operational_enablement_preflight(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    red_addon_operational_test_assert(
        hash_equals($ready['planSha256'], $again['planSha256']),
        'unchanged client evidence produces one operational plan digest'
    );
    $forged = $ready;
    $forged['readyPublicMutationCount'] = 0;
    $forged['planSha256'] =
        red_addon_operational_enablement_fingerprint($forged);
    red_addon_operational_test_assert(
        !red_addon_operational_enablement_preflight_is_valid($forged),
        'forged readiness counts cannot validate after hash recomputation'
    );

    mysqli_query($connection, "ALTER TABLE `$commandsTable` ENGINE=MyISAM");
    $unsupportedEngine = red_addon_operational_enablement_preflight(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    red_addon_operational_test_assert(
        !empty($unsupportedEngine['valid'])
            && empty($unsupportedEngine['operationalEvidenceReady'])
            && $unsupportedEngine['gates']['publicMutations'] === 'blocked',
        'a non-transactional declared package table blocks operational evidence'
    );
    mysqli_query($connection, "ALTER TABLE `$commandsTable` ENGINE=InnoDB");

    $source = file_get_contents(
        $projectRoot .
            '/includes/addon_operational_enablement_preflight_helpers.php'
    );
    red_addon_operational_test_assert(
        is_string($source)
            && strpos($source, 'addon.php') === false
            && strpos($source, 'mysqli_begin_transaction') === false
            && strpos($source, 'registerComponent(') === false
            && strpos($source, '$_POST') === false
            && strpos($source, '$_GET') === false,
        'operational preflight has no package include, transaction, registrar, or request path'
    );

} finally {
    red_addon_operational_test_cleanup(
        $connection,
        $packageId,
        $actorId,
        $itemsTable,
        $commandsTable,
        $temporaryRoot
    );
    red_addon_operational_test_assert(
        red_addon_operational_test_scalar(
            $connection,
            "SELECT CONCAT(
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='$packageId'), ':',
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='$packageId'), ':',
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='$packageId'), ':',
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId), ':',
                (SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME IN ('$itemsTable', '$commandsTable')))"
        ) === '0:0:0:0:0'
            && !is_dir($temporaryRoot),
        'operational preflight fixture cleanup is exact'
    );
}

echo 'Operational add-on enablement preflight passed ' . $assertions .
    " assertions.\n";
