<?php
/**
 * Disposable checks for public-mutation live-data readiness evidence.
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
    '/includes/addon_public_mutation_live_data_helpers.php';
require_once $projectRoot . '/includes/addon_setting_write_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_public_mutation_live_data)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Public-mutation live-data preflight self-test refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000970;
$packageId = 'redcms.mutation-live-data';
$routeId = 'redcms.mutation-live-data/cart-intent';
$mutationId = 'redcms.mutation-live-data/add-to-cart';
$settingPermission = 'mutation.live-data.settings.manage';
$cartTable = 'RED_Addon_Mutation_Live_Data_Carts';
$itemTable = 'RED_Addon_Mutation_Live_Data_Items';
$temporaryRoot = sys_get_temp_dir() . '/redcms-public-mutation-live-data-' .
    bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_public_mutation_live_data_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_public_mutation_live_data_test_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_public_mutation_live_data_test_remove_tree($path)
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

function red_addon_public_mutation_live_data_test_cleanup(
    $connection,
    $packageId,
    $actorId,
    $cartTable,
    $itemTable,
    $temporaryRoot
) {
    try {
        mysqli_query($connection, 'DROP TABLE IF EXISTS `' . $itemTable . '`');
        mysqli_query($connection, 'DROP TABLE IF EXISTS `' . $cartTable . '`');
        $escapedPackageId = mysqli_real_escape_string($connection, $packageId);
        foreach ([
            'RED_Addon_Settings',
            'RED_Addon_Activity_Log',
            'RED_Addon_Migrations',
            'RED_Addon_Installations',
        ] as $table) {
            mysqli_query(
                $connection,
                "DELETE FROM $table WHERE PackageID='$escapedPackageId'"
            );
        }
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=' .
                (int) $actorId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=' .
                (int) $actorId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=' . (int) $actorId
        );
    } catch (Throwable $throwable) {
        error_log(
            'Public-mutation live-data preflight cleanup failed: ' .
                $throwable->getMessage()
        );
    }
    red_addon_public_mutation_live_data_test_remove_tree($temporaryRoot);
}

function red_addon_public_mutation_live_data_test_create_package(
    $project,
    $packageId,
    $routeId,
    $mutationId,
    $settingPermission,
    $cartTable,
    $itemTable,
    $executionMarker
) {
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    $migrationDirectory = $directory . '/migrations';
    if (!mkdir($migrationDirectory, 0700, true) && !is_dir($migrationDirectory)) {
        throw new RuntimeException('Could not create public-mutation fixture.');
    }
    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) . ", 'executed');\n" .
        "return static function (RED_Addon_Runtime_Registry \$runtime): void {};\n";
    $migrationPath = 'migrations/2026-08-03-mutation-live-data.sql';
    $migration = "CREATE TABLE $cartTable (RecordID int unsigned NOT NULL);\n" .
        "CREATE TABLE $itemTable (RecordID int unsigned NOT NULL);\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    file_put_contents($directory . '/' . $migrationPath, $migration);
    $files = [
        ['path' => 'addon.php', 'sha256' => hash('sha256', $entrypoint)],
        ['path' => $migrationPath, 'sha256' => hash('sha256', $migration)],
    ];
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Public mutation live-data fixture',
        'description' => 'Non-executing public-mutation readiness fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => ['redcms.mutation-live-data/cart'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$settingPermission],
        'settings' => [
            [
                'key' => 'store.name',
                'label' => 'Store name',
                'type' => 'text',
                'secret' => false,
                'permission' => $settingPermission,
                'default' => 'Mutation Fixture Store',
            ],
            [
                'key' => 'gateway.api-key',
                'label' => 'Gateway API key',
                'type' => 'secret-reference',
                'secret' => true,
                'permission' => $settingPermission,
            ],
        ],
        'migrations' => [[
            'id' => '2026-08-03-mutation-live-data',
            'path' => $migrationPath,
            'sha256' => hash('sha256', $migration),
        ]],
        'routes' => [[
            'id' => $routeId,
            'scope' => 'public',
            'path' => '/addons/redcms/mutation-live-data/cart-intent',
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
            'maxBodyBytes' => 1024,
            'requestFields' => [
                [
                    'key' => 'product',
                    'type' => 'identifier',
                    'required' => true,
                    'minLength' => 1,
                    'maxLength' => 120,
                ],
                [
                    'key' => 'quantity',
                    'type' => 'positive-integer',
                    'required' => true,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
            'subject' => 'anonymous',
            'idempotency' => 'core-issued-key',
            'privacy' => 'no-store',
            'rateLimit' => 'required',
            'tables' => [$cartTable, $itemTable],
            'postcondition' => 'server-derived-state',
            'audit' => 'commerce.cart.item-added',
            'outcomes' => ['accepted', 'unchanged'],
        ]],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => $files,
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

function red_addon_public_mutation_live_data_test_record_installation(
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
    if (!$statement) {
        return false;
    }
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
        if (!$statement) {
            return false;
        }
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

function red_addon_public_mutation_live_data_test_fingerprint(
    $connection,
    $packageId,
    $actorId,
    $cartTable,
    $itemTable
) {
    $escapedPackageId = mysqli_real_escape_string($connection, $packageId);
    $rows = [];
    foreach ([
        "SELECT PackageID, PackageVersion, PackageType, ManifestSHA256,
                InventorySHA256, LifecycleState, InstalledByAdminRecordID,
                UpdatedByAdminRecordID
         FROM RED_Addon_Installations
         WHERE PackageID='$escapedPackageId'",
        "SELECT PackageID, MigrationID, MigrationPath, Checksum,
                AppliedByAdminRecordID, ExecutionMs
         FROM RED_Addon_Migrations
         WHERE PackageID='$escapedPackageId'
         ORDER BY MigrationID",
        "SELECT PackageID, SettingKey, ValueType, ValueJSON, SecretReference,
                UpdatedByAdminRecordID
         FROM RED_Addon_Settings
         WHERE PackageID='$escapedPackageId'
         ORDER BY SettingKey",
        'SELECT AdminRecordID, RoleName, AssignedByAdminRecordID
         FROM RED_Admin_Roles
         WHERE AdminRecordID=' . (int) $actorId,
        'SELECT AdminRecordID, Capability, GrantedByAdminRecordID
         FROM RED_Admin_Capabilities
         WHERE AdminRecordID=' . (int) $actorId . '
         ORDER BY Capability',
        "SELECT TABLE_NAME, ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME IN ('$cartTable', '$itemTable')
         ORDER BY TABLE_NAME",
    ] as $sql) {
        $query = mysqli_query($connection, $sql);
        $resultRows = [];
        while ($query && ($row = mysqli_fetch_assoc($query))) {
            $resultRows[] = $row;
        }
        if ($query) {
            mysqli_free_result($query);
        }
        $rows[] = $resultRows;
    }
    return hash(
        'sha256',
        (string) json_encode(
            $rows,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )
    );
}

red_addon_public_mutation_live_data_test_cleanup(
    $connection,
    $packageId,
    $actorId,
    $cartTable,
    $itemTable,
    $temporaryRoot
);

try {
    red_addon_public_mutation_live_data_test_assert(
        red_addon_registry_storage_available($connection)
            && red_addon_setting_storage_available($connection)
            && red_addon_public_mutation_subject_storage_available($connection),
        'the disposable client has registry, settings, anonymous-subject, and CSRF storage'
    );

    $password = password_hash('MutationLiveData-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_mutation_live_data', ?, 'Admin',
                   'MutationData', 'webmaster', '', '',
                   'mutation-live-data@example.test', 'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    if (!mysqli_stmt_execute($statement)) {
        throw new RuntimeException('Could not create owner fixture.');
    }
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
    foreach (['addons.enable', $settingPermission] as $capability) {
        mysqli_stmt_bind_param($statement, 'isi', $actorId, $capability, $actorId);
        if (!mysqli_stmt_execute($statement)) {
            throw new RuntimeException('Could not grant fixture capability.');
        }
    }
    mysqli_stmt_close($statement);

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_public_mutation_live_data_test_create_package(
        $fixtureProject,
        $packageId,
        $routeId,
        $mutationId,
        $settingPermission,
        $cartTable,
        $itemTable,
        $executionMarker
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $package = $catalog['packages'][$packageId] ?? [];
    red_addon_public_mutation_live_data_test_assert(
        !empty($catalog['valid'])
            && !empty($package['valid'])
            && !file_exists($executionMarker),
        'trusted package discovery remains non-executing'
    );

    $uninstalled = red_addon_public_mutation_live_data_preflight(
        $connection,
        $package,
        $actorId,
        $catalog,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_live_data_test_assert(
        empty($uninstalled['valid'])
            && $uninstalled['errors'] === [
                'package_not_installed_disabled_current',
            ],
        'a trusted but uninstalled mutation package has no live-data plan'
    );

    if (!red_addon_public_mutation_live_data_test_record_installation(
        $connection,
        $package,
        $actorId
    )) {
        throw new RuntimeException('Could not record package installation evidence.');
    }
    $withoutTablesFingerprint = red_addon_public_mutation_live_data_test_fingerprint(
        $connection,
        $packageId,
        $actorId,
        $cartTable,
        $itemTable
    );
    $withoutTables = red_addon_public_mutation_live_data_preflight(
        $connection,
        $package,
        $actorId,
        $catalog,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_live_data_test_assert(
        !empty($withoutTables['valid'])
            && empty($withoutTables['dataEvidenceReady'])
            && $withoutTables['gates']['declaration'] === 'passed'
            && $withoutTables['gates']['migrations'] === 'passed'
            && $withoutTables['gates']['packageTables'] === 'blocked'
            && $withoutTables['tableCount'] === 2
            && $withoutTables['innoDbTableCount'] === 0
            && !$withoutTables['requestDispatch']
            && !$withoutTables['packageExecution'],
        'missing declared package tables are a value-free live-data blocker'
    );
    red_addon_public_mutation_live_data_test_assert(
        hash_equals(
            $withoutTablesFingerprint,
            red_addon_public_mutation_live_data_test_fingerprint(
                $connection,
                $packageId,
                $actorId,
                $cartTable,
                $itemTable
            )
        ) && !file_exists($executionMarker),
        'missing-table inspection never writes state or executes addon.php'
    );

    mysqli_query(
        $connection,
        "CREATE TABLE `$cartTable` (
            RecordID int unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$itemTable` (
            RecordID int unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    $withoutSettings = red_addon_public_mutation_live_data_preflight(
        $connection,
        $package,
        $actorId,
        $catalog,
        $routeId,
        $mutationId
    );
    red_addon_public_mutation_live_data_test_assert(
        !empty($withoutSettings['valid'])
            && empty($withoutSettings['dataEvidenceReady'])
            && $withoutSettings['gates']['packageTables'] === 'passed'
            && $withoutSettings['gates']['settingsConfiguration'] === 'blocked'
            && $withoutSettings['settingCount'] === 2
            && $withoutSettings['configuredSettingCount'] === 0,
        'declared settings remain blocked until complete client-scoped values exist'
    );

    $configuredValues = [
        'gateway.api-key' => 'config:redcms.mutation-live-data.gateway-key',
    ];
    $writePlan = red_addon_setting_write_preflight(
        $connection,
        $package,
        $actorId,
        $configuredValues
    );
    $write = red_addon_setting_write(
        $connection,
        $package,
        $actorId,
        $configuredValues,
        $writePlan['planSha256'] ?? ''
    );
    red_addon_public_mutation_live_data_test_assert(
        !empty($writePlan['valid']) && $write['status'] === 'updated',
        'the fixture persists a complete typed setting state through the existing atomic writer'
    );

    $declarations = red_addon_secret_reference_declarations([
        'config:redcms.mutation-live-data.gateway-key',
    ], '');
    $beforeReady = red_addon_public_mutation_live_data_test_fingerprint(
        $connection,
        $packageId,
        $actorId,
        $cartTable,
        $itemTable
    );
    $ready = red_addon_public_mutation_live_data_preflight(
        $connection,
        $package,
        $actorId,
        $catalog,
        $routeId,
        $mutationId,
        $declarations
    );
    $readyJson = json_encode($ready, JSON_UNESCAPED_SLASHES);
    red_addon_public_mutation_live_data_test_assert(
        !empty($ready['valid'])
            && !empty($ready['dataEvidenceReady'])
            && !$ready['enableReady']
            && !$ready['activationSupported']
            && !$ready['requestDispatch']
            && !$ready['invoked']
            && !$ready['stateMutation']
            && !$ready['runtimeLoad']
            && !$ready['packageExecution']
            && !$ready['secretResolution']
            && $ready['migrationCount'] === 1
            && $ready['tableCount'] === 2
            && $ready['innoDbTableCount'] === 2
            && $ready['settingCount'] === 2
            && $ready['configuredSettingCount'] === 2
            && $ready['secretSettingCount'] === 1
            && $ready['availableSecretCount'] === 1
            && $ready['missingSecretSettingCount'] === 0
            && $ready['gates']['settingsConfiguration'] === 'passed'
            && $ready['gates']['secretAvailability'] === 'passed'
            && $ready['gates']['settingsEndpoint'] === 'not_implemented'
            && $ready['gates']['secretLookup'] === 'not_implemented'
            && $ready['gates']['anonymousSubject'] === 'passed'
            && $ready['gates']['csrf'] === 'passed'
            && $ready['gates']['idempotency'] === 'not_implemented'
            && $ready['gates']['rateLimit'] === 'passed'
            && red_addon_public_mutation_live_data_preflight_is_valid($ready),
        'complete current package, table, setting, and secret-availability evidence is deterministic but non-activating'
    );
    red_addon_public_mutation_live_data_test_assert(
        is_string($readyJson)
            && strpos($readyJson, $cartTable) === false
            && strpos($readyJson, $itemTable) === false
            && strpos(
                $readyJson,
                'config:redcms.mutation-live-data.gateway-key'
            ) === false,
        'the public-mutation plan exposes no table or opaque secret-reference value'
    );
    red_addon_public_mutation_live_data_test_assert(
        hash_equals(
            $beforeReady,
            red_addon_public_mutation_live_data_test_fingerprint(
                $connection,
                $packageId,
                $actorId,
                $cartTable,
                $itemTable
            )
        ) && !file_exists($executionMarker),
        'live-data readiness remains database-read-only and non-executing'
    );
    $readyAgain = red_addon_public_mutation_live_data_preflight(
        $connection,
        $package,
        $actorId,
        $catalog,
        $routeId,
        $mutationId,
        $declarations
    );
    red_addon_public_mutation_live_data_test_assert(
        hash_equals($ready['planSha256'], $readyAgain['planSha256']),
        'unchanged package and client evidence produces one deterministic plan digest'
    );
    $forged = $ready;
    $forged['innoDbTableCount'] = 1;
    red_addon_public_mutation_live_data_test_assert(
        !red_addon_public_mutation_live_data_preflight_is_valid($forged),
        'forged live-data counts cannot validate'
    );

    $missingSecret = red_addon_public_mutation_live_data_preflight(
        $connection,
        $package,
        $actorId,
        $catalog,
        $routeId,
        $mutationId,
        red_addon_secret_reference_declarations([], '')
    );
    red_addon_public_mutation_live_data_test_assert(
        !empty($missingSecret['valid'])
            && empty($missingSecret['dataEvidenceReady'])
            && $missingSecret['gates']['secretAvailability'] === 'blocked'
            && $missingSecret['missingSecretSettingCount'] === 1
            && strpos(
                (string) json_encode($missingSecret),
                'config:redcms.mutation-live-data.gateway-key'
            ) === false,
        'unavailable configured secret references block readiness without disclosure'
    );

    mysqli_query($connection, "ALTER TABLE `$itemTable` ENGINE=MyISAM");
    $unsupportedEngine = red_addon_public_mutation_live_data_preflight(
        $connection,
        $package,
        $actorId,
        $catalog,
        $routeId,
        $mutationId,
        $declarations
    );
    red_addon_public_mutation_live_data_test_assert(
        !empty($unsupportedEngine['valid'])
            && empty($unsupportedEngine['dataEvidenceReady'])
            && $unsupportedEngine['gates']['packageTables'] === 'blocked'
            && $unsupportedEngine['tableCount'] === 2
            && $unsupportedEngine['innoDbTableCount'] === 1,
        'non-InnoDB declared tables block live-data readiness before any mutation runner exists'
    );
    mysqli_query($connection, "ALTER TABLE `$itemTable` ENGINE=InnoDB");

    $source = file_get_contents(
        $projectRoot . '/includes/addon_public_mutation_live_data_helpers.php'
    );
    red_addon_public_mutation_live_data_test_assert(
        is_string($source)
            && strpos($source, '$_') === false
            && strpos($source, 'mysqli_begin_transaction') === false
            && strpos($source, 'register') === false,
        'the live-data helper has no request-global, transaction, or runtime-registration path'
    );
    red_addon_public_mutation_live_data_test_assert(
        red_addon_public_mutation_live_data_test_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Activity_Log
             WHERE PackageID='$packageId'
               AND EventName<>'addon.settings.updated'"
        ) === '0',
        'the preflight creates no mutation or lifecycle audit evidence'
    );

    red_addon_public_mutation_live_data_test_cleanup(
        $connection,
        $packageId,
        $actorId,
        $cartTable,
        $itemTable,
        $temporaryRoot
    );
    red_addon_public_mutation_live_data_test_assert(
        red_addon_public_mutation_live_data_test_scalar(
            $connection,
            "SELECT CONCAT_WS(
                ':',
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='$packageId'),
                (SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME IN ('$cartTable', '$itemTable')),
                (SELECT COUNT(*) FROM RED_Admin
                 WHERE RecordID=$actorId)
             )"
        ) === '0:0:0:0:0' && !is_dir($temporaryRoot),
        'exact package, table, authorization, setting, and filesystem fixtures are removed'
    );

    fwrite(
        STDOUT,
        'Add-on public-mutation live-data preflight self-test passed (' .
            $assertions . " assertions).\n"
    );
} catch (Throwable $throwable) {
    red_addon_public_mutation_live_data_test_cleanup(
        $connection,
        $packageId,
        $actorId,
        $cartTable,
        $itemTable,
        $temporaryRoot
    );
    fwrite(
        STDERR,
        'Add-on public-mutation live-data preflight self-test failed: ' .
            $throwable->getMessage() . "\n"
    );
    exit(1);
}

?>
