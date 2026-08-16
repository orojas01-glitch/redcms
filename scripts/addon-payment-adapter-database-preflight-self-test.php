<?php
/**
 * Disposable database checks for P3A-2 payment-adapter readiness evidence.
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
    '/includes/addon_payment_adapter_database_preflight_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|payment_adapter_db)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Payment-adapter database preflight refused non-disposable database: ' .
            DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000994;
$adapterPackageId = 'redcms.stripe-db-fixture';
$storePackageId = 'redcms.store-lite';
$tableName = 'RED_Addon_Stripe_DB_Fixture_Attempts';
$temporaryRoot = sys_get_temp_dir() . '/redcms-payment-adapter-db-' .
    bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_payment_adapter_db_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_payment_adapter_db_test_remove_tree($path)
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

function red_addon_payment_adapter_db_test_cleanup(
    $connection,
    array $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
) {
    try {
        mysqli_query(
            $connection,
            'DROP TABLE IF EXISTS `' . $tableName . '`'
        );
        foreach ([
            'RED_Addon_Activity_Log',
            'RED_Addon_Migrations',
            'RED_Addon_Installations',
        ] as $table) {
            foreach ($packageIds as $packageId) {
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
            'Payment-adapter database preflight cleanup failed: ' .
                $throwable->getMessage()
        );
    }
    red_addon_payment_adapter_db_test_remove_tree($temporaryRoot);
}

function red_addon_payment_adapter_db_test_write_package(
    $project,
    $packageId,
    $type,
    $version,
    $executionMarker,
    $tableName = ''
) {
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create payment adapter fixture.');
    }
    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) .
        ", 'executed');\nreturn static function (): void {};\n";
    file_put_contents($directory . '/addon.php', $entrypoint);

    $isAdapter = $type === 'adapter';
    $migrations = [];
    $integrityFiles = [[
        'path' => 'addon.php',
        'sha256' => hash('sha256', $entrypoint),
    ]];
    if ($isAdapter) {
        $migrationDirectory = $directory . '/migrations';
        if (!mkdir($migrationDirectory, 0700, true)
            && !is_dir($migrationDirectory)
        ) {
            throw new RuntimeException('Could not create adapter migrations.');
        }
        $migrationPath =
            'migrations/2026-08-15-stripe-db-fixture.sql';
        $migration = "CREATE TABLE $tableName (\n" .
            "  RecordID bigint unsigned NOT NULL AUTO_INCREMENT,\n" .
            "  PRIMARY KEY (RecordID)\n" .
            ") ENGINE=InnoDB;\n";
        file_put_contents($directory . '/' . $migrationPath, $migration);
        $migrations[] = [
            'id' => '2026-08-15-stripe-db-fixture',
            'path' => $migrationPath,
            'sha256' => hash('sha256', $migration),
        ];
        $integrityFiles[] = [
            'path' => $migrationPath,
            'sha256' => hash('sha256', $migration),
        ];
    }

    $manifest = [
        '$schema' =>
            'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => $isAdapter
            ? 'Stripe database readiness fixture'
            : 'Store Lite dependency fixture',
        'description' => 'Non-executing P3A-2 database fixture.',
        'version' => $version,
        'type' => $type,
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [],
            'adminTools' => [],
            'adapters' => $isAdapter ? [$packageId . '/checkout'] : [],
        ],
        'dependencies' => [
            'required' => $isAdapter ? [[
                'id' => 'redcms.store-lite',
                'version' => '>=0.1 <1.0',
            ]] : [],
            'optional' => [],
        ],
        'permissions' => [],
        'settings' => $isAdapter ? [[
            'key' => 'checkout.return-origin',
            'label' => 'Checkout return origin',
            'type' => 'url',
            'secret' => false,
            'default' => null,
        ], [
            'key' => 'stripe.secret-key',
            'label' => 'Stripe secret key reference',
            'type' => 'secret-reference',
            'secret' => true,
        ], [
            'key' => 'stripe.webhook-secret',
            'label' => 'Stripe webhook secret reference',
            'type' => 'secret-reference',
            'secret' => true,
        ]] : [],
        'migrations' => $migrations,
        'routes' => $isAdapter ? [[
            'id' => $packageId . '/provider-events',
            'scope' => 'public',
            'path' => '/addons/redcms/stripe-db-fixture/provider-events',
            'methods' => ['POST'],
            'authentication' => 'server-signature',
            'csrf' => 'not-applicable',
        ]] : [],
        'publicMutationContracts' => [],
        'jobs' => [],
        'outboundHosts' => $isAdapter ? ['api.stripe.com'] : [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => $integrityFiles,
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

function red_addon_payment_adapter_db_test_record_installation(
    $connection,
    array $package,
    $actorId,
    $state
) {
    $snapshot = red_addon_registry_snapshot($package);
    if (!is_array($snapshot)
        || !red_addon_registry_valid_lifecycle_state($state)
    ) {
        return false;
    }
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState, InstalledByAdminRecordID,
            UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$statement) {
        return false;
    }
    mysqli_stmt_bind_param(
        $statement,
        'ssssssii',
        $snapshot['id'],
        $snapshot['version'],
        $snapshot['type'],
        $snapshot['manifestSha256'],
        $snapshot['inventorySha256'],
        $state,
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

function red_addon_payment_adapter_db_test_fingerprint(
    $connection,
    array $packageIds,
    $actorId,
    $tableName
) {
    $escapedIds = array_map(
        static fn($packageId) => "'" .
            mysqli_real_escape_string($connection, $packageId) . "'",
        $packageIds
    );
    $queries = [
        'SELECT PackageID, PackageVersion, PackageType, ManifestSHA256,
                InventorySHA256, LifecycleState, InstalledByAdminRecordID,
                UpdatedByAdminRecordID
         FROM RED_Addon_Installations
         WHERE PackageID IN (' . implode(',', $escapedIds) . ')
         ORDER BY PackageID',
        'SELECT PackageID, MigrationID, MigrationPath, Checksum,
                AppliedByAdminRecordID, ExecutionMs
         FROM RED_Addon_Migrations
         WHERE PackageID IN (' . implode(',', $escapedIds) . ')
         ORDER BY PackageID, MigrationID',
        'SELECT AdminRecordID, RoleName, AssignedByAdminRecordID
         FROM RED_Admin_Roles WHERE AdminRecordID=' . (int) $actorId,
        'SELECT AdminRecordID, Capability, GrantedByAdminRecordID
         FROM RED_Admin_Capabilities WHERE AdminRecordID=' . (int) $actorId .
            ' ORDER BY Capability',
        "SELECT TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" .
            mysqli_real_escape_string($connection, $tableName) . "'",
    ];
    $material = [];
    foreach ($queries as $query) {
        $queryResult = mysqli_query($connection, $query);
        $rows = [];
        while ($queryResult && ($row = mysqli_fetch_assoc($queryResult))) {
            $rows[] = $row;
        }
        if ($queryResult) {
            mysqli_free_result($queryResult);
        }
        $material[] = $rows;
    }
    return hash('sha256', json_encode($material));
}

$packageIds = [$adapterPackageId, $storePackageId];
red_addon_payment_adapter_db_test_cleanup(
    $connection,
    $packageIds,
    $actorId,
    $tableName,
    $temporaryRoot
);

try {
    $password = password_hash('PaymentAdapterDB-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_payment_adapter_db', ?, 'Admin',
                   'PayAdapter', 'webmaster', '', '',
                   'payment-adapter@example.test', 'N', 'to', 'N', 'to')"
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
    $capability = 'addons.enable';
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'isi',
        $actorId,
        $capability,
        $actorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $storePackageId,
        'content-package',
        '0.1.31',
        $executionMarker
    );
    red_addon_payment_adapter_db_test_write_package(
        $fixtureProject,
        $adapterPackageId,
        'adapter',
        '0.1.0',
        $executionMarker,
        $tableName
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $storePackage = $catalog['packages'][$storePackageId] ?? [];
    $adapterPackage = $catalog['packages'][$adapterPackageId] ?? [];
    red_addon_payment_adapter_db_test_assert(
        !empty($catalog['valid'])
            && !empty($storePackage['valid'])
            && !empty($adapterPackage['valid'])
            && !file_exists($executionMarker),
        'trusted dependency and adapter discovery remains non-executing'
    );
    red_addon_payment_adapter_db_test_assert(
        red_addon_payment_adapter_db_test_record_installation(
            $connection,
            $storePackage,
            $actorId,
            'enabled'
        ) && red_addon_payment_adapter_db_test_record_installation(
            $connection,
            $adapterPackage,
            $actorId,
            'installed_disabled'
        ),
        'same client database records enabled Store Lite and disabled adapter identities'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$tableName` (
            RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );
    red_addon_payment_adapter_db_test_assert(
        mysqli_errno($connection) === 0,
        'fixture creates one adapter-owned InnoDB table'
    );

    $before = red_addon_payment_adapter_db_test_fingerprint(
        $connection,
        $packageIds,
        $actorId,
        $tableName
    );
    $plan = red_addon_payment_adapter_database_preflight(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog
    );
    red_addon_payment_adapter_db_test_assert(
        red_addon_payment_adapter_database_preflight_is_valid($plan)
            && !empty($plan['databaseEvidenceReady'])
            && !$plan['enableReady']
            && !$plan['activationSupported']
            && !$plan['stateMutation']
            && !$plan['runtimeLoad']
            && !$plan['packageExecution']
            && !$plan['secretResolution']
            && !$plan['networkAccess']
            && !$plan['routeExposure'],
        'Owner-bound exact database evidence is ready while every effect remains blocked'
    );
    red_addon_payment_adapter_db_test_assert(
        $plan['packageId'] === $adapterPackageId
            && $plan['version'] === '0.1.0'
            && $plan['currentState'] === 'installed_disabled'
            && $plan['dependencyCount'] === 1
            && $plan['migrationCount'] === 1
            && $plan['tableCount'] === 1
            && $plan['innoDbTableCount'] === 1
            && red_addon_valid_sha256($plan['databaseSha256'])
            && red_addon_valid_sha256($plan['dependencyEvidenceSha256'])
            && red_addon_valid_sha256($plan['migrationEvidenceSha256'])
            && red_addon_valid_sha256($plan['tableEvidenceSha256']),
        'plan exposes only bounded database, dependency, migration, and table hashes and counts'
    );
    red_addon_payment_adapter_db_test_assert(
        array_column($plan['blockers'], 'code') === [
            'atomic_payment_adapter_enablement_required',
            'registrar_validation_required',
            'server_event_ingress_required',
        ],
        'database readiness clears only its own blocker'
    );
    $repeat = red_addon_payment_adapter_database_preflight(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog
    );
    red_addon_payment_adapter_db_test_assert(
        hash_equals($plan['planSha256'], $repeat['planSha256'])
            && hash_equals(
                $before,
                red_addon_payment_adapter_db_test_fingerprint(
                    $connection,
                    $packageIds,
                    $actorId,
                    $tableName
                )
            ),
        'repeat preflight is deterministic and changes no database fact'
    );

    $denied = red_addon_payment_adapter_database_preflight(
        $connection,
        $adapterPackage,
        $actorId + 1,
        $catalog
    );
    red_addon_payment_adapter_db_test_assert(
        empty($denied['valid'])
            && in_array(
                'owner_enable_capability_required',
                $denied['errors'],
                true
            ),
        'database evidence requires persisted Owner enable authority'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='installed_disabled'
         WHERE PackageID='redcms.store-lite'"
    );
    $disabledDependency = red_addon_payment_adapter_database_preflight(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog
    );
    red_addon_payment_adapter_db_test_assert(
        red_addon_payment_adapter_database_preflight_is_valid(
            $disabledDependency
        )
            && empty($disabledDependency['databaseEvidenceReady'])
            && $disabledDependency['gates']['dependencies'] === 'blocked'
            && in_array(
                'database_payment_adapter_evidence_incomplete',
                array_column($disabledDependency['blockers'], 'code'),
                true
            ),
        'Store Lite must be enabled in the same client database'
    );
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations SET LifecycleState='enabled'
         WHERE PackageID='redcms.store-lite'"
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Migrations SET Checksum='" .
            str_repeat('a', 64) . "'
         WHERE PackageID='redcms.stripe-db-fixture'"
    );
    $migrationDrift = red_addon_payment_adapter_database_preflight(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog
    );
    red_addon_payment_adapter_db_test_assert(
        empty($migrationDrift['valid'])
            && in_array('registry_catalog_invalid', $migrationDrift['errors'], true),
        'immutable migration-ledger drift fails closed'
    );
    $adapterSnapshot = red_addon_registry_snapshot($adapterPackage);
    $migrationSha256 = $adapterSnapshot['migrations'][
        '2026-08-15-stripe-db-fixture'
    ]['sha256'];
    $statement = mysqli_prepare(
        $connection,
        'UPDATE RED_Addon_Migrations SET Checksum=?
         WHERE PackageID=? AND MigrationID=?'
    );
    $migrationId = '2026-08-15-stripe-db-fixture';
    mysqli_stmt_bind_param(
        $statement,
        'sss',
        $migrationSha256,
        $adapterPackageId,
        $migrationId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    mysqli_query($connection, "ALTER TABLE `$tableName` ENGINE=MyISAM");
    $wrongEngine = red_addon_payment_adapter_database_preflight(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog
    );
    red_addon_payment_adapter_db_test_assert(
        empty($wrongEngine['valid'])
            && in_array(
                'package_table_engine_unsupported',
                $wrongEngine['errors'],
                true
            ),
        'non-InnoDB adapter storage fails closed'
    );
    mysqli_query($connection, "ALTER TABLE `$tableName` ENGINE=InnoDB");

    mysqli_query($connection, "DROP TABLE `$tableName`");
    $missingTable = red_addon_payment_adapter_database_preflight(
        $connection,
        $adapterPackage,
        $actorId,
        $catalog
    );
    red_addon_payment_adapter_db_test_assert(
        empty($missingTable['valid'])
            && in_array(
                'package_table_unavailable',
                $missingTable['errors'],
                true
            ),
        'missing migration-declared adapter table fails closed'
    );
    mysqli_query(
        $connection,
        "CREATE TABLE `$tableName` (
            RecordID bigint unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (RecordID)
         ) ENGINE=InnoDB"
    );

    $tampered = $plan;
    $tampered['tableCount'] = 2;
    red_addon_payment_adapter_db_test_assert(
        !red_addon_payment_adapter_database_preflight_is_valid($tampered),
        'tampered database evidence fails its deterministic fingerprint'
    );
    $helperSource = (string) file_get_contents(
        $projectRoot .
            '/includes/addon_payment_adapter_database_preflight_helpers.php'
    );
    red_addon_payment_adapter_db_test_assert(
        preg_match(
            '/(?:\$_SERVER|\$_ENV|\bgetenv\s*\(|\bcurl_|\bfsockopen\s*\(|\bstream_socket_client\s*\(|\binclude\s*\(|\brequire\s*\(|addon\.php|RED_Addon_Settings|red_addon_(?:runtime_)?secret)/i',
            $helperSource
        ) !== 1,
        'database preflight has no request, secret, network, or package-entrypoint path'
    );
    red_addon_payment_adapter_db_test_assert(
        !file_exists($executionMarker),
        'all database and refusal checks leave package PHP unexecuted'
    );

    red_addon_payment_adapter_db_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
    echo 'Payment adapter P3A-2 database preflight self-test passed: ' .
        $assertions . " assertions.\n";
} catch (Throwable $throwable) {
    red_addon_payment_adapter_db_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $tableName,
        $temporaryRoot
    );
    fwrite(STDERR, $throwable->getMessage() . "\n");
    exit(1);
}

?>
