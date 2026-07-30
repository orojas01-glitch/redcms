<?php
/**
 * Disposable database checks for atomic Owner-authorized add-on disablement.
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
require_once $projectRoot . '/includes/addon_disable_helpers.php';
require_once $projectRoot . '/includes/addon_runtime_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_disable)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on disable self-test refused non-disposable database: ' .
        DBNAME .
        "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000932;
$targetPackageId = 'redcms.disable-target';
$basePackageId = 'redcms.disable-base';
$dependentPackageId = 'redcms.disable-dependent';
$packageIds = [
    $targetPackageId,
    $basePackageId,
    $dependentPackageId,
];
$temporaryRoot = sys_get_temp_dir() .
    '/redcms-addon-disable-' .
    bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/registrar-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_disable_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_disable_test_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_disable_test_remove_tree($path)
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS
        ),
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

function red_addon_disable_test_cleanup(
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
            'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=' .
            (int) $actorId
        );
        mysqli_query(
            $connection,
            'DELETE FROM RED_Admin WHERE RecordID=' . (int) $actorId
        );
    } catch (Throwable $throwable) {
        error_log(
            'Add-on disable cleanup failed: ' . $throwable->getMessage()
        );
    }
    red_addon_disable_test_remove_tree($temporaryRoot);
}

function red_addon_disable_test_package(
    $project,
    $packageId,
    $marker,
    $requiredPackageId = ''
) {
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create disable fixture.');
    }
    $serviceId = $packageId . '/service';
    $entrypoint = "<?php\n" .
        "return static function (RED_Addon_Runtime_Registry \$runtime): void {\n" .
        '    file_put_contents(' .
        var_export($marker, true) .
        ', ' .
        var_export($packageId . "\n", true) .
        ", FILE_APPEND | LOCK_EX);\n" .
        '    $runtime->registerService(' .
        var_export($serviceId, true) .
        ", static function (): string { return 'ok'; });\n" .
        "};\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    $required = $requiredPackageId === ''
        ? []
        : [[
            'id' => $requiredPackageId,
            'version' => '>=1.0 <2.0',
        ]];
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Atomic Disable Fixture',
        'description' => 'Disposable atomic disablement fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => [
            'cms' => '>=5.1 <6.0',
            'php' => '>=8.2 <9.0',
        ],
        'provides' => [
            'components' => [],
            'services' => [$serviceId],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => [
            'required' => $required,
            'optional' => [],
        ],
        'permissions' => [$packageId . '.manage'],
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
        json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        )
    );
}

function red_addon_disable_test_record_enabled(
    $connection,
    array $package,
    $actorId
) {
    $snapshot = red_addon_registry_snapshot($package);
    if ($snapshot === null) {
        return false;
    }
    $state = 'enabled';
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
    $recorded = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $recorded;
}

function red_addon_disable_test_fingerprint($connection, array $packageIds)
{
    $quoted = array_map(
        static function ($id) use ($connection) {
            return "'" .
                mysqli_real_escape_string($connection, $id) .
                "'";
        },
        $packageIds
    );
    $ids = implode(',', $quoted);
    return red_addon_disable_test_scalar(
        $connection,
        'SELECT SHA2(
            GROUP_CONCAT(ValueText ORDER BY ValueText SEPARATOR "|"),
            256
         ) FROM (
            SELECT CONCAT_WS(
                "#", PackageID, PackageVersion,
                LifecycleState, UpdatedByAdminRecordID
            ) AS ValueText
            FROM RED_Addon_Installations
            WHERE PackageID IN (' . $ids . ')
            UNION ALL
            SELECT CONCAT_WS(
                "#", EventName, PackageID, PackageVersion,
                ActorAdminRecordID, Result, DetailCode
            ) AS ValueText
            FROM RED_Addon_Activity_Log
            WHERE PackageID IN (' . $ids . ')
            UNION ALL
            SELECT CONCAT_WS(
                "#", PackageID, MigrationID, MigrationPath, Checksum
            ) AS ValueText
            FROM RED_Addon_Migrations
            WHERE PackageID IN (' . $ids . ')
         ) AS red_atomic_disable_fingerprint'
    );
}

try {
    red_addon_disable_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $temporaryRoot
    );
    $password = password_hash(
        'AtomicDisableFixture-2026!',
        PASSWORD_DEFAULT
    );
    $stmt = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias,
            AdminType, AdminComponents, AdminTools, Email,
            Contact_Form, Contact_Form_Pref,
            Donation_Form, Donation_Form_Pref
         ) VALUES (
            ?, 'codex_atomic_disabler', ?, 'Admin', 'Disable',
            'webmaster', '100', '1', 'atomic-disable@example.test',
            'N', 'to', 'N', 'to'
         )"
    );
    mysqli_stmt_bind_param($stmt, 'is', $actorId, $password);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Roles (
            AdminRecordID, RoleName, AssignedByAdminRecordID
         ) VALUES ($actorId, 'owner', $actorId)"
    );
    mysqli_query(
        $connection,
        "INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES ($actorId, 'addons.disable', $actorId)"
    );

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_disable_test_package(
        $fixtureProject,
        $targetPackageId,
        $executionMarker
    );
    red_addon_disable_test_package(
        $fixtureProject,
        $basePackageId,
        $executionMarker
    );
    red_addon_disable_test_package(
        $fixtureProject,
        $dependentPackageId,
        $executionMarker,
        $basePackageId
    );
    $catalog = red_addon_discover($fixtureProject, [
        'cmsVersion' => '5.1.0',
        'phpVersion' => PHP_VERSION,
    ]);
    $targetPackage = $catalog['packages'][$targetPackageId] ?? [];
    $basePackage = $catalog['packages'][$basePackageId] ?? [];
    $dependentPackage = $catalog['packages'][$dependentPackageId] ?? [];
    red_addon_disable_test_assert(
        !empty($catalog['valid'])
            && !empty($targetPackage['valid'])
            && !empty($basePackage['valid'])
            && !empty($dependentPackage['valid'])
            && !file_exists($executionMarker),
        'fixture discovery is trusted and non-executing'
    );
    red_addon_disable_test_assert(
        red_addon_disable_test_record_enabled(
            $connection,
            $targetPackage,
            $actorId
        )
            && red_addon_disable_test_record_enabled(
                $connection,
                $basePackage,
                $actorId
            )
            && red_addon_disable_test_record_enabled(
                $connection,
                $dependentPackage,
                $actorId
            ),
        'fixtures start enabled with isolated registry evidence'
    );

    $runtimeBefore = red_addon_runtime_bootstrap(
        $connection,
        $fixtureProject
    );
    $beforeExecution = file_exists($executionMarker)
        ? (string) file_get_contents($executionMarker)
        : '';
    red_addon_disable_test_assert(
        $runtimeBefore['context']->handler(
            'services',
            $targetPackageId . '/service'
        ) !== null
            && $runtimeBefore['context']->handler(
                'services',
                $dependentPackageId . '/service'
            ) !== null
            && str_contains($beforeExecution, $targetPackageId)
            && str_contains($beforeExecution, $basePackageId)
            && str_contains($beforeExecution, $dependentPackageId),
        'request bootstrap loads all current enabled fixtures before disable'
    );
    unlink($executionMarker);

    $deniedPlan = red_addon_disable_transition_plan(
        $connection,
        $targetPackage,
        1,
        $catalog
    );
    red_addon_disable_test_assert(
        empty($deniedPlan['valid'])
            && $deniedPlan['errors']
                === ['owner_disable_capability_required'],
        'legacy administrator cannot plan a disable transition'
    );

    $targetPlan = red_addon_disable_transition_plan(
        $connection,
        $targetPackage,
        $actorId,
        $catalog
    );
    $targetPlanRepeat = red_addon_disable_transition_plan(
        $connection,
        $targetPackage,
        $actorId,
        $catalog
    );
    red_addon_disable_test_assert(
        !empty($targetPlan['valid'])
            && !empty($targetPlan['transitionReady'])
            && $targetPlan['currentState'] === 'enabled'
            && $targetPlan['targetState'] === 'installed_disabled'
            && red_addon_valid_sha256($targetPlan['planSha256'])
            && hash_equals(
                $targetPlan['planSha256'],
                $targetPlanRepeat['planSha256']
            )
            && !file_exists($executionMarker),
        'Owner dry run is deterministic, ready, and non-executing'
    );

    $before = red_addon_disable_test_fingerprint(
        $connection,
        $packageIds
    );
    $basePlan = red_addon_disable_transition_plan(
        $connection,
        $basePackage,
        $actorId,
        $catalog
    );
    red_addon_disable_test_assert(
        !empty($basePlan['valid'])
            && empty($basePlan['transitionReady'])
            && ($basePlan['enabledDependents'][0]['id'] ?? '')
                === $dependentPackageId
            && ($basePlan['blockers'][0]['code'] ?? '')
                === 'enabled_dependent_requires_package'
            && $before === red_addon_disable_test_fingerprint(
                $connection,
                $packageIds
            )
            && !file_exists($executionMarker),
        'enabled dependent evidence blocks disable without mutation or execution'
    );
    $blocked = red_addon_disable_package(
        $connection,
        $basePackageId,
        $fixtureProject,
        $actorId,
        $basePlan['planSha256']
    );
    red_addon_disable_test_assert(
        $blocked['status'] === 'enabled_dependent_requires_package'
            && ($blocked['enabledDependents'][0]['id'] ?? '')
                === $dependentPackageId
            && $before === red_addon_disable_test_fingerprint(
                $connection,
                $packageIds
            )
            && !file_exists($executionMarker),
        'atomic apply rechecks and refuses an enabled dependent'
    );

    $secondDb = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
    $secondConnection = $secondDb->connection;
    $firstLock = red_addon_lifecycle_lock($connection, 0);
    $secondBlocked = $firstLock
        && !red_addon_lifecycle_lock($secondConnection, 0);
    $firstReleased = red_addon_lifecycle_unlock($connection);
    $secondLock = $firstReleased
        && red_addon_lifecycle_lock($secondConnection, 0);
    $secondReleased = $secondLock
        && red_addon_lifecycle_unlock($secondConnection);
    $secondDb->close();
    red_addon_disable_test_assert(
        $firstLock
            && $secondBlocked
            && $firstReleased
            && $secondLock
            && $secondReleased,
        'database-wide lifecycle lock serializes enable and disable plans'
    );

    $stale = red_addon_disable_package(
        $connection,
        $targetPackageId,
        $fixtureProject,
        $actorId,
        str_repeat('f', 64)
    );
    red_addon_disable_test_assert(
        $stale['status'] === 'plan_changed'
            && $before === red_addon_disable_test_fingerprint(
                $connection,
                $packageIds
            )
            && !file_exists($executionMarker),
        'stale plan is rejected before state or audit mutation'
    );

    $auditFailure = red_addon_disable_package(
        $connection,
        $targetPackageId,
        $fixtureProject,
        $actorId,
        $targetPlan['planSha256'],
        static function () {
            return false;
        }
    );
    red_addon_disable_test_assert(
        $auditFailure['status'] === 'disable_transaction_failed'
            && $before === red_addon_disable_test_fingerprint(
                $connection,
                $packageIds
            )
            && !file_exists($executionMarker),
        'audit failure rolls back state and never executes package code'
    );

    $injectedFailure = red_addon_disable_package(
        $connection,
        $targetPackageId,
        $fixtureProject,
        $actorId,
        $targetPlan['planSha256'],
        null,
        static function () {
            throw new RuntimeException(
                'forced_after_state_update_failure'
            );
        }
    );
    red_addon_disable_test_assert(
        $injectedFailure['status'] === 'disable_transaction_failed'
            && $before === red_addon_disable_test_fingerprint(
                $connection,
                $packageIds
            )
            && !file_exists($executionMarker),
        'injected post-state failure rolls back state and audit together'
    );

    $disabled = red_addon_disable_package(
        $connection,
        $targetPackageId,
        $fixtureProject,
        $actorId,
        $targetPlan['planSha256']
    );
    red_addon_disable_test_assert(
        $disabled['status'] === 'installed_disabled'
            && !file_exists($executionMarker),
        'atomic transition disables the package without executing its registrar'
    );
    red_addon_disable_test_assert(
        red_addon_disable_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT LifecycleState
                 FROM RED_Addon_Installations
                 WHERE PackageID='redcms.disable-target'),
                (SELECT COUNT(*)
                 FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.disable-target'
                   AND EventName='addon.disable.completed'
                   AND Result='succeeded'
                   AND DetailCode='installed_disabled'),
                (SELECT COUNT(*)
                 FROM RED_Addon_Migrations
                 WHERE PackageID='redcms.disable-target'))"
        ) === 'installed_disabled:1:0',
        'disabled state and bounded audit commit without migration changes'
    );

    $runtimeAfter = red_addon_runtime_bootstrap(
        $connection,
        $fixtureProject
    );
    $afterExecution = file_exists($executionMarker)
        ? (string) file_get_contents($executionMarker)
        : '';
    red_addon_disable_test_assert(
        $runtimeAfter['context']->handler(
            'services',
            $targetPackageId . '/service'
        ) === null
            && $runtimeAfter['context']->handler(
                'services',
                $dependentPackageId . '/service'
            ) !== null
            && !str_contains($afterExecution, $targetPackageId)
            && str_contains($afterExecution, $basePackageId)
            && str_contains($afterExecution, $dependentPackageId),
        'later request bootstrap excludes the disabled package'
    );
    unlink($executionMarker);

    $repeat = red_addon_disable_package(
        $connection,
        $targetPackageId,
        $fixtureProject,
        $actorId,
        $targetPlan['planSha256']
    );
    red_addon_disable_test_assert(
        $repeat['status'] === 'package_not_enabled_current'
            && !file_exists($executionMarker),
        'an installed-disabled package cannot be disabled twice'
    );

    $dependentPlan = red_addon_disable_transition_plan(
        $connection,
        $dependentPackage,
        $actorId,
        $catalog
    );
    $dependentDisabled = red_addon_disable_package(
        $connection,
        $dependentPackageId,
        $fixtureProject,
        $actorId,
        $dependentPlan['planSha256']
    );
    $basePlanAfterDependent = red_addon_disable_transition_plan(
        $connection,
        $basePackage,
        $actorId,
        $catalog
    );
    red_addon_disable_test_assert(
        !empty($dependentPlan['transitionReady'])
            && $dependentDisabled['status'] === 'installed_disabled'
            && !empty($basePlanAfterDependent['transitionReady'])
            && $basePlanAfterDependent['enabledDependents'] === []
            && !file_exists($executionMarker),
        'disabling the dependent first safely unblocks its requirement'
    );

    $helperSource = (string) file_get_contents(
        $projectRoot . '/includes/addon_disable_helpers.php'
    );
    $cli = (string) file_get_contents(
        $projectRoot . '/scripts/admin-addon-disable.php'
    );
    red_addon_disable_test_assert(
        str_contains($cli, "PHP_SAPI !== 'cli'")
            && str_contains($cli, '--confirm-database=')
            && str_contains($cli, '--confirm-package=')
            && str_contains($cli, '--confirm-version=')
            && str_contains($cli, '--confirm-plan-sha256=')
            && str_contains($cli, '--confirm-backup-sha256=')
            && str_contains($cli, '--confirm-state=')
            && str_contains($cli, '--apply')
            && !str_contains(
                $helperSource,
                'red_addon_runtime_register_package'
            )
            && !file_exists(
                $projectRoot . '/admin/bin/addon_disable.php'
            )
            && !file_exists($projectRoot . '/bin/addon_disable.php'),
        'disablement is CLI-only, exact-confirmation, and non-executing'
    );

    red_addon_disable_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $temporaryRoot
    );
    red_addon_disable_test_assert(
        red_addon_disable_test_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT COUNT(*)
                 FROM RED_Addon_Installations
                 WHERE PackageID IN (
                    'redcms.disable-target',
                    'redcms.disable-base',
                    'redcms.disable-dependent'
                 )),
                (SELECT COUNT(*)
                 FROM RED_Addon_Activity_Log
                 WHERE PackageID IN (
                    'redcms.disable-target',
                    'redcms.disable-base',
                    'redcms.disable-dependent'
                 )),
                (SELECT COUNT(*)
                 FROM RED_Admin
                 WHERE RecordID=$actorId))"
        ) === '0:0:0'
            && !file_exists($executionMarker),
        'all package, audit, authorization, and code fixtures clean up exactly'
    );
    printf(
        "Add-on atomic disable self-test passed: %d assertions.\n",
        $assertions
    );
} catch (Throwable $throwable) {
    red_addon_disable_test_cleanup(
        $connection,
        $packageIds,
        $actorId,
        $temporaryRoot
    );
    fwrite(
        STDERR,
        $throwable->getMessage() .
        ' (after ' .
        $assertions .
        " assertions)\n"
    );
    $db->close();
    exit(1);
}

$db->close();
