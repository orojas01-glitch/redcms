<?php
/**
 * Disposable database checks for read-only add-on enablement preflight.
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
require_once $projectRoot . '/includes/addon_enable_preflight_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_enable)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on enablement preflight self-test refused non-disposable database: ' .
        DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000930;
$targetPackageId = 'redcms.enable-target';
$basePackageId = 'redcms.enable-base';
$readyPackageId = 'redcms.enable-ready';
$fixturePackageIds = [
    $targetPackageId,
    $basePackageId,
    $readyPackageId,
];
$temporaryRoot = sys_get_temp_dir() .
    '/redcms-addon-enable-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/addon-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_enable_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_enable_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_enable_test_remove_tree($path)
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

function red_addon_enable_test_cleanup(
    $connection,
    array $packageIds,
    $actorId,
    $temporaryRoot
) {
    try {
        foreach ([
            'RED_Addon_Activity_Log',
            'RED_Addon_Migrations',
            'RED_Addon_Installations',
        ] as $table) {
            foreach ($packageIds as $packageId) {
                $stmt = mysqli_prepare(
                    $connection,
                    'DELETE FROM ' . $table . ' WHERE PackageID=?'
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 's', $packageId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
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
            'Add-on enablement preflight cleanup failed: ' .
            $throwable->getMessage()
        );
    }
    red_addon_enable_test_remove_tree($temporaryRoot);
}

function red_addon_enable_test_package(
    $project,
    $packageId,
    $executionMarker,
    array $requiredDependencies,
    $sharedCapability,
    $routeId,
    $withMigration = false,
    $withRoute = true
) {
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create enablement package fixture.');
    }

    $registrations =
        "\n    \$runtime->registerService(" .
        var_export($sharedCapability, true) .
        ", static function (): string { return 'ok'; });";
    if ($withRoute) {
        $registrations .=
            "\n    \$runtime->registerRoute(" .
            var_export($routeId, true) .
            ", static function (): string { return 'ok'; });";
    }
    $entrypoint = "<?php\nfile_put_contents(" .
        var_export($executionMarker, true) .
        ", 'executed');\nreturn static function (" .
        "RED_Addon_Runtime_Registry \$runtime): void {" .
        $registrations . "\n};\n";
    $files = [
        'addon.php' => $entrypoint,
    ];
    $migrations = [];
    if ($withMigration) {
        $migrationDirectory = $directory . '/migrations';
        if (!mkdir($migrationDirectory, 0700, true)
            && !is_dir($migrationDirectory)
        ) {
            throw new RuntimeException(
                'Could not create enablement migration fixture.'
            );
        }
        $migrationPath =
            'migrations/2026-07-26-enable-target-fixture.sql';
        $migrationSql =
            "-- Ledger evidence fixture; preflight must not execute this SQL.\n" .
            "CREATE TABLE RED_Addon_Enable_Target_Fixture " .
            "(RecordID int unsigned NOT NULL);\n";
        $files[$migrationPath] = $migrationSql;
        $migrations[] = [
            'id' => '2026-07-26-enable-target-fixture',
            'path' => $migrationPath,
            'sha256' => hash('sha256', $migrationSql),
        ];
    }
    foreach ($files as $path => $content) {
        file_put_contents($directory . '/' . $path, $content);
    }

    $routeSlug = $parts[1];
    $integrityFiles = [];
    foreach ($files as $path => $content) {
        $integrityFiles[] = [
            'path' => $path,
            'sha256' => hash('sha256', $content),
        ];
    }
    usort(
        $integrityFiles,
        static function ($left, $right) {
            return strcmp($left['path'], $right['path']);
        }
    );
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Enablement ' . $routeSlug,
        'description' => 'Read-only enablement preflight fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [$sharedCapability],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => [
            'required' => $requiredDependencies,
            'optional' => [],
        ],
        'permissions' => [$packageId . '.settings.manage'],
        'settings' => [],
        'migrations' => $migrations,
        'routes' => $withRoute ? [[
                'id' => $routeId,
                'scope' => 'admin',
                'path' => '/admin/addons/' . $parts[0] . '/' .
                    $parts[1] . '/manage',
                'methods' => ['GET'],
                'authentication' => 'admin',
                'csrf' => 'not-applicable',
            ]] : [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => [
            'public' => [],
            'admin' => [],
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
    file_put_contents(
        $directory . '/addon.json',
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
    );
}

function red_addon_enable_test_record_installation(
    $connection,
    array $package,
    $actorId,
    $state
) {
    $snapshot = red_addon_registry_snapshot($package);
    if ($snapshot === null
        || !red_addon_registry_valid_lifecycle_state($state)
    ) {
        return false;
    }
    $stmt = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType,
            ManifestSHA256, InventorySHA256, LifecycleState,
            InstalledByAdminRecordID, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param(
        $stmt,
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
    $inserted = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$inserted) {
        return false;
    }
    foreach ($snapshot['migrations'] as $migration) {
        $stmt = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Migrations (
                PackageID, MigrationID, MigrationPath, Checksum,
                AppliedByAdminRecordID, ExecutionMs
             ) VALUES (?, ?, ?, ?, ?, 0)'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'ssssi',
            $snapshot['id'],
            $migration['id'],
            $migration['path'],
            $migration['sha256'],
            $actorId
        );
        $recorded = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$recorded) {
            return false;
        }
    }
    return true;
}

function red_addon_enable_test_fingerprint(
    $connection,
    array $packageIds,
    $actorId
) {
    $quotedIds = array_map(
        static function ($packageId) use ($connection) {
            return "'" . mysqli_real_escape_string($connection, $packageId) . "'";
        },
        $packageIds
    );
    $queries = [
        'SELECT PackageID, PackageVersion, PackageType, ManifestSHA256,
                InventorySHA256, LifecycleState, InstalledByAdminRecordID,
                InstalledAt, UpdatedByAdminRecordID, UpdatedAt
         FROM RED_Addon_Installations
         WHERE PackageID IN (' . implode(',', $quotedIds) . ')
         ORDER BY PackageID',
        'SELECT PackageID, MigrationID, MigrationPath, Checksum,
                AppliedByAdminRecordID, AppliedAt, ExecutionMs
         FROM RED_Addon_Migrations
         WHERE PackageID IN (' . implode(',', $quotedIds) . ')
         ORDER BY PackageID, MigrationID',
        'SELECT EventName, PackageID, PackageVersion, ActorAdminRecordID,
                Result, DetailCode, OccurredAt
         FROM RED_Addon_Activity_Log
         WHERE PackageID IN (' . implode(',', $quotedIds) . ')
         ORDER BY RecordID',
        'SELECT AdminRecordID, RoleName, AssignedByAdminRecordID, AssignedAt
         FROM RED_Admin_Roles
         WHERE AdminRecordID=' . (int) $actorId,
        'SELECT AdminRecordID, Capability, GrantedByAdminRecordID, GrantedAt
         FROM RED_Admin_Capabilities
         WHERE AdminRecordID=' . (int) $actorId . '
         ORDER BY Capability',
    ];
    $rows = [];
    foreach ($queries as $query) {
        $result = mysqli_query($connection, $query);
        $queryRows = [];
        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $queryRows[] = $row;
        }
        if ($result) {
            mysqli_free_result($result);
        }
        $rows[] = $queryRows;
    }
    return hash(
        'sha256',
        (string) json_encode(
            $rows,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )
    );
}

try {
    red_addon_enable_test_cleanup(
        $connection,
        $fixturePackageIds,
        $actorId,
        $temporaryRoot
    );

    red_addon_enable_test_assert(
        red_addon_registry_storage_available($connection)
            && red_admin_addon_storage_available($connection),
        'per-client registry and Owner authorization storage are available'
    );

    $passwordHash = password_hash(
        'AddonEnableFixture-2026!',
        PASSWORD_DEFAULT
    );
    $stmt = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_addon_enabler', ?, 'Admin', 'Enabler',
                   'webmaster', '100', '1', 'addon-enabler@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($stmt, 'is', $actorId, $passwordHash);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles
         (AdminRecordID, RoleName, AssignedByAdminRecordID)
         VALUES ($actorId, 'owner', $actorId)"
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities
         (AdminRecordID, Capability, GrantedByAdminRecordID)
         VALUES ($actorId, 'addons.enable', $actorId)"
    );

    $fixtureProject = $temporaryRoot . '/project';
    $sharedCapability = 'redcms.enable-shared/service';
    $sharedRouteId = 'redcms.enable-shared/route';
    red_addon_enable_test_package(
        $fixtureProject,
        $basePackageId,
        $executionMarker,
        [],
        $sharedCapability,
        $sharedRouteId,
        false
    );
    red_addon_enable_test_package(
        $fixtureProject,
        $targetPackageId,
        $executionMarker,
        [[
            'id' => $basePackageId,
            'version' => '>=1.0 <2.0',
        ]],
        $sharedCapability,
        $sharedRouteId,
        true
    );
    red_addon_enable_test_package(
        $fixtureProject,
        $readyPackageId,
        $executionMarker,
        [],
        'redcms.enable-ready/service',
        'redcms.enable-ready/unused-route',
        false,
        false
    );

    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $targetPackage = $catalog['packages'][$targetPackageId] ?? [];
    $basePackage = $catalog['packages'][$basePackageId] ?? [];
    $readyPackage = $catalog['packages'][$readyPackageId] ?? [];
    red_addon_enable_test_assert(
        !empty($catalog['valid'])
            && !empty($targetPackage['valid'])
            && !empty($basePackage['valid'])
            && !empty($readyPackage['valid'])
            && !file_exists($executionMarker),
        'trusted fixture discovery validates every package without executing addon.php'
    );

    $deniedPlan = red_addon_enable_preflight_plan(
        $connection,
        $targetPackage,
        1,
        $catalog
    );
    red_addon_enable_test_assert(
        empty($deniedPlan['valid'])
            && $deniedPlan['errors'] === [
                'owner_enable_capability_required',
            ],
        'a legacy administrator without persisted Owner enable authority is denied'
    );

    $uninstalledPlan = red_addon_enable_preflight_plan(
        $connection,
        $targetPackage,
        $actorId,
        $catalog
    );
    red_addon_enable_test_assert(
        empty($uninstalledPlan['valid'])
            && $uninstalledPlan['errors'] === [
                'package_not_installed_disabled_current',
            ],
        'a trusted but uninstalled package cannot receive an enablement plan'
    );

    if (!red_addon_enable_test_record_installation(
        $connection,
        $targetPackage,
        $actorId,
        'installed_disabled'
    ) || !red_addon_enable_test_record_installation(
        $connection,
        $basePackage,
        $actorId,
        'installed_disabled'
    ) || !red_addon_enable_test_record_installation(
        $connection,
        $readyPackage,
        $actorId,
        'installed_disabled'
    )) {
        throw new RuntimeException('Could not record enablement fixtures.');
    }

    $readyFingerprint = red_addon_enable_test_fingerprint(
        $connection,
        $fixturePackageIds,
        $actorId
    );
    $readyPlan = red_addon_enable_preflight_plan(
        $connection,
        $readyPackage,
        $actorId,
        $catalog
    );
    red_addon_enable_test_assert(
        !empty($readyPlan['valid'])
            && $readyPlan['declarativeGatesReady']
            && !$readyPlan['enableReady']
            && !$readyPlan['activationSupported']
            && $readyPlan['activationProfile']['id']
                === 'registration_only_service'
            && $readyPlan['gates']['themeCompatibility']
                === 'not_applicable'
            && $readyPlan['gates']['settings'] === 'passed'
            && $readyPlan['gates']['liveData'] === 'not_applicable'
            && array_column($readyPlan['blockers'], 'code') === [
                'registrar_validation_required',
            ],
        'a registration-only service clears every declarative activation gate'
    );
    red_addon_enable_test_assert(
        hash_equals(
            $readyFingerprint,
            red_addon_enable_test_fingerprint(
                $connection,
                $fixturePackageIds,
                $actorId
            )
        ) && !file_exists($executionMarker),
        'registration-only readiness remains database-read-only and non-executing'
    );

    $beforeDisabledDependencyPlan = red_addon_enable_test_fingerprint(
        $connection,
        $fixturePackageIds,
        $actorId
    );
    $disabledDependencyPlan = red_addon_enable_preflight_plan(
        $connection,
        $targetPackage,
        $actorId,
        $catalog
    );
    $disabledBlockerCodes = array_column(
        $disabledDependencyPlan['blockers'],
        'code'
    );
    red_addon_enable_test_assert(
        !empty($disabledDependencyPlan['valid'])
            && !$disabledDependencyPlan['enableReady']
            && $disabledDependencyPlan['currentState'] === 'installed_disabled'
            && $disabledDependencyPlan['targetState'] === 'enabled'
            && in_array(
                'required_dependency_not_enabled',
                $disabledBlockerCodes,
                true
            )
            && in_array(
                'registrar_validation_required',
                $disabledBlockerCodes,
                true
            )
            && in_array(
                'live_data_contract_required',
                $disabledBlockerCodes,
                true
            )
            && $disabledDependencyPlan['gates']['themeCompatibility']
                === 'not_applicable'
            && $disabledDependencyPlan['gates']['settings'] === 'passed'
            && $disabledDependencyPlan['gates']['liveData'] === 'blocked'
            && !$disabledDependencyPlan['declarativeGatesReady']
            && $disabledDependencyPlan['gates']['runtimeRegistration']
                === 'available'
            && $disabledDependencyPlan['gates']['dependencies'] === 'blocked',
        'disabled dependency and exposed live-data surface stay explicit blockers'
    );

    $repeatDisabledDependencyPlan = red_addon_enable_preflight_plan(
        $connection,
        $targetPackage,
        $actorId,
        $catalog
    );
    red_addon_enable_test_assert(
        red_addon_valid_sha256($disabledDependencyPlan['planSha256'])
            && hash_equals(
                $disabledDependencyPlan['planSha256'],
                $repeatDisabledDependencyPlan['planSha256']
            ),
        'unchanged database and package evidence produce one deterministic plan digest'
    );
    red_addon_enable_test_assert(
        hash_equals(
            $beforeDisabledDependencyPlan,
            red_addon_enable_test_fingerprint(
                $connection,
                $fixturePackageIds,
                $actorId
            )
        ) && !file_exists($executionMarker),
        'repeated preflight leaves registry, audit, authorization, and package code untouched'
    );

    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='enabled', UpdatedByAdminRecordID=$actorId
         WHERE PackageID='redcms.enable-base'"
    );
    $beforeEnabledDependencyPlan = red_addon_enable_test_fingerprint(
        $connection,
        $fixturePackageIds,
        $actorId
    );
    $enabledDependencyPlan = red_addon_enable_preflight_plan(
        $connection,
        $targetPackage,
        $actorId,
        $catalog
    );
    red_addon_enable_test_assert(
        !empty($enabledDependencyPlan['valid'])
            && count($enabledDependencyPlan['requiredDependencies']) === 1
            && $enabledDependencyPlan['requiredDependencies'][0]['id']
                === $basePackageId
            && $enabledDependencyPlan['requiredDependencies'][0]['lifecycleState']
                === 'enabled'
            && $enabledDependencyPlan['gates']['dependencies'] === 'passed',
        'an exact current enabled dependency becomes immutable plan evidence'
    );
    red_addon_enable_test_assert(
        $enabledDependencyPlan['capabilityConflicts'] === [[
            'code' => 'provided_capability_conflict',
            'type' => 'services',
            'capability' => $sharedCapability,
            'packageId' => $basePackageId,
        ]]
            && $enabledDependencyPlan['gates']['capabilityNamespace']
                === 'blocked',
        'a provided capability already owned by an enabled package blocks readiness'
    );
    red_addon_enable_test_assert(
        $enabledDependencyPlan['routeConflicts'] === [[
            'code' => 'route_id_conflict',
            'routeId' => $sharedRouteId,
            'packageId' => $basePackageId,
        ]]
            && $enabledDependencyPlan['gates']['routeNamespace'] === 'blocked',
        'a route identifier already owned by an enabled package blocks readiness'
    );
    red_addon_enable_test_assert(
        count($enabledDependencyPlan['enabledPackages']) === 1
            && $enabledDependencyPlan['enabledPackages'][0]['id']
                === $basePackageId
            && !isset($enabledDependencyPlan['runtimeInventory']['services']),
        'enabled package evidence is bound separately from the target runtime inventory'
    );
    red_addon_enable_test_assert(
        ($enabledDependencyPlan['runtimeInventory']['provides']['services']
            ?? 0) === 1
            && ($enabledDependencyPlan['runtimeInventory']['routes'] ?? 0) === 1
            && count($enabledDependencyPlan['appliedMigrations']) === 1
            && $enabledDependencyPlan['appliedMigrations'][0]['id']
                === '2026-07-26-enable-target-fixture'
            && !$enabledDependencyPlan['stateMutation']
            && !$enabledDependencyPlan['runtimeLoad']
            && !$enabledDependencyPlan['activationSupported'],
        'runtime declarations are inventoried without state mutation or activation'
    );
    red_addon_enable_test_assert(
        hash_equals(
            $beforeEnabledDependencyPlan,
            red_addon_enable_test_fingerprint(
                $connection,
                $fixturePackageIds,
                $actorId
            )
        ) && !file_exists($executionMarker),
        'dependency and namespace analysis is database-read-only and non-executing'
    );

    $syntheticTarget = [
        'routes' => [[
            'id' => 'redcms.synthetic/target',
            'scope' => 'public',
            'path' => '/addons/redcms/synthetic/items',
            'methods' => ['GET', 'POST'],
        ]],
    ];
    $syntheticEnabled = [
        'redcms.synthetic-base' => [
            'manifest' => [
                'routes' => [[
                    'id' => 'redcms.synthetic/enabled',
                    'scope' => 'public',
                    'path' => '/addons/redcms/synthetic/items',
                    'methods' => ['POST', 'DELETE'],
                ]],
            ],
        ],
    ];
    red_addon_enable_test_assert(
        red_addon_enable_preflight_route_conflicts(
            $syntheticTarget,
            $syntheticEnabled
        ) === [[
            'code' => 'route_path_method_conflict',
            'scope' => 'public',
            'path' => '/addons/redcms/synthetic/items',
            'method' => 'POST',
            'packageId' => 'redcms.synthetic-base',
        ]],
        'same-scope path registration detects only overlapping HTTP methods'
    );

    $defaultComponentProfile =
        red_addon_enable_preflight_activation_profile([
            'provides' => [
                'components' => ['redcms.synthetic/component'],
                'services' => [],
                'adminTools' => [],
                'adapters' => [],
            ],
            'settings' => [],
            'migrations' => [],
            'routes' => [],
            'jobs' => [],
            'outboundHosts' => [],
            'assets' => [
                'public' => [],
                'admin' => [],
            ],
        ]);
    red_addon_enable_test_assert(
        !empty($defaultComponentProfile['eligible'])
            && $defaultComponentProfile['id']
                === 'default_public_component'
            && $defaultComponentProfile['gates'] === [
                'themeCompatibility' => 'passed',
                'settings' => 'passed',
                'liveData' => 'not_applicable',
            ]
            && $defaultComponentProfile['blockers'] === [],
        'a component with only the core default renderer clears declarative gates'
    );

    $defaultComponentWithServicesProfile =
        red_addon_enable_preflight_activation_profile([
            'provides' => [
                'components' => ['redcms.synthetic/component'],
                'services' => [
                    'redcms.synthetic/catalog',
                    'redcms.synthetic/cart',
                ],
                'adminTools' => [],
                'adapters' => [],
            ],
            'settings' => [],
            'migrations' => [],
            'routes' => [],
            'jobs' => [],
            'outboundHosts' => [],
            'assets' => [
                'public' => [],
                'admin' => [],
            ],
        ]);
    red_addon_enable_test_assert(
        !empty($defaultComponentWithServicesProfile['eligible'])
            && $defaultComponentWithServicesProfile['id']
                === 'default_public_component_with_services'
            && $defaultComponentWithServicesProfile['gates'] === [
                'themeCompatibility' => 'passed',
                'settings' => 'passed',
                'liveData' => 'not_applicable',
            ]
            && $defaultComponentWithServicesProfile['blockers'] === [],
        'a default public component plus registration-only services clears declarative gates'
    );

    $expandedProfile = red_addon_enable_preflight_activation_profile([
        'provides' => [
            'components' => ['redcms.synthetic/component'],
            'services' => [],
            'adminTools' => ['redcms.synthetic/tool'],
            'adapters' => ['redcms.synthetic/adapter'],
        ],
        'settings' => [[
            'key' => 'synthetic.setting',
        ]],
        'routes' => [['id' => 'redcms.synthetic/route']],
        'jobs' => [['id' => 'redcms.synthetic/job']],
        'outboundHosts' => ['api.example.test'],
        'assets' => [
            'public' => [['path' => 'public.css']],
            'admin' => [['path' => 'admin.css']],
        ],
    ]);
    red_addon_enable_test_assert(
        empty($expandedProfile['eligible'])
            && $expandedProfile['id'] === 'expanded_contract_required'
            && $expandedProfile['gates'] === [
                'themeCompatibility' => 'blocked',
                'settings' => 'blocked',
                'liveData' => 'blocked',
            ]
            && array_column($expandedProfile['blockers'], 'code') === [
                'live_data_contract_required',
                'settings_configuration_required',
                'supported_activation_profile_required',
                'theme_contract_required',
            ],
        'components, configuration, and operational surfaces fail closed with exact gate evidence'
    );

    $targetSnapshot = red_addon_registry_snapshot($targetPackage);
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET ManifestSHA256='" . str_repeat('0', 64) . "'
         WHERE PackageID='redcms.enable-target'"
    );
    $driftPlan = red_addon_enable_preflight_plan(
        $connection,
        $targetPackage,
        $actorId,
        $catalog
    );
    red_addon_enable_test_assert(
        empty($driftPlan['valid'])
            && $driftPlan['errors'] === ['registry_catalog_invalid'],
        'recorded package identity drift fails closed before readiness analysis'
    );
    if ($targetSnapshot === null) {
        throw new RuntimeException('Could not restore target snapshot.');
    }
    $stmt = mysqli_prepare(
        $connection,
        'UPDATE RED_Addon_Installations
         SET ManifestSHA256=?, UpdatedByAdminRecordID=?
         WHERE PackageID=?'
    );
    mysqli_stmt_bind_param(
        $stmt,
        'sis',
        $targetSnapshot['manifestSha256'],
        $actorId,
        $targetPackageId
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $cliSource = (string) file_get_contents(
        $projectRoot . '/scripts/admin-addon-enable-preflight.php'
    );
    $helperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_enable_preflight_helpers.php'
    );
    red_addon_enable_test_assert(
        str_contains($cliSource, "PHP_SAPI !== 'cli'")
            && str_contains($cliSource, 'State mutation: no')
            && str_contains($cliSource, 'Runtime load: no')
            && str_contains($cliSource, 'Declarative gates ready:')
            && !str_contains($cliSource, '--apply')
            && preg_match(
                '/\\b(?:require|include)(?:_once)?\\b\\s*\\(?[^;\\n]*addon\\.php/',
                $cliSource . "\n" . $helperSource
            ) !== 1
            && !file_exists(
                $projectRoot . '/admin/bin/addon_enable_preflight.php'
            )
            && !file_exists($projectRoot . '/bin/addon_enable_preflight.php'),
        'preflight is CLI-only, exposes no apply path, and cannot include package PHP'
    );

    red_addon_enable_test_cleanup(
        $connection,
        $fixturePackageIds,
        $actorId,
        $temporaryRoot
    );
    red_addon_enable_test_assert(
        red_addon_enable_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*) FROM RED_Addon_Installations
                 WHERE PackageID IN (
                    'redcms.enable-target','redcms.enable-base',
                    'redcms.enable-ready'
                 )),
                (SELECT COUNT(*) FROM RED_Addon_Migrations
                 WHERE PackageID IN (
                    'redcms.enable-target','redcms.enable-base',
                    'redcms.enable-ready'
                 )),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID IN (
                    'redcms.enable-target','redcms.enable-base',
                    'redcms.enable-ready'
                 )),
                (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId)
             )"
        ) === '0:0:0:0' && !file_exists($executionMarker),
        'enablement fixtures, authorization, audit, and code marker clean up exactly'
    );

    printf(
        "Add-on enablement preflight self-test passed: %d assertions.\n",
        $assertions
    );
} catch (Throwable $throwable) {
    red_addon_enable_test_cleanup(
        $connection,
        $fixturePackageIds,
        $actorId,
        $temporaryRoot
    );
    fwrite(
        STDERR,
        $throwable->getMessage() .
        ' (after ' . $assertions . " assertions)\n"
    );
    $db->close();
    exit(1);
}

$db->close();
