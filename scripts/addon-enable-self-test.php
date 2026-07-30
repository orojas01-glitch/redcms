<?php
/**
 * Disposable database checks for atomic Owner-authorized add-on enablement.
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
require_once $projectRoot . '/includes/addon_component_render_helpers.php';

if (!preg_match('/\Aredcms_(?:acceptance|addon_enable)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(STDERR, 'Add-on enable self-test refused non-disposable database: ' . DBNAME . "\n");
    exit(65);
}

$assertions = 0;
$actorId = 2147000931;
$readyPackageId = 'redcms.atomic-ready';
$componentPackageId = 'redcms.atomic-component';
$combinedPackageId = 'redcms.atomic-combined';
$richPackageId = 'redcms.atomic-rich';
$packageIds = [
    $readyPackageId,
    $componentPackageId,
    $combinedPackageId,
    $richPackageId,
];
$temporaryRoot = sys_get_temp_dir() . '/redcms-addon-atomic-enable-' . bin2hex(random_bytes(8));
$executionMarker = $temporaryRoot . '/registrar-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_atomic_enable_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_atomic_enable_scalar($connection, $sql)
{
    $result = mysqli_query($connection, $sql);
    $row = $result ? mysqli_fetch_row($result) : null;
    if ($result) {
        mysqli_free_result($result);
    }
    return $row ? (string) $row[0] : '';
}

function red_addon_atomic_enable_remove_tree($path)
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

function red_addon_atomic_enable_cleanup($connection, array $packageIds, $actorId, $temporaryRoot)
{
    try {
        foreach (['RED_Addon_Activity_Log', 'RED_Addon_Migrations', 'RED_Addon_Installations'] as $table) {
            foreach ($packageIds as $packageId) {
                $stmt = mysqli_prepare($connection, 'DELETE FROM ' . $table . ' WHERE PackageID=?');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 's', $packageId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
        }
        mysqli_query($connection, 'DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=' . (int) $actorId);
        mysqli_query($connection, 'DELETE FROM RED_Admin_Roles WHERE AdminRecordID=' . (int) $actorId);
        mysqli_query($connection, 'DELETE FROM RED_Admin WHERE RecordID=' . (int) $actorId);
    } catch (Throwable $throwable) {
        error_log('Add-on atomic enable cleanup failed: ' . $throwable->getMessage());
    }
    red_addon_atomic_enable_remove_tree($temporaryRoot);
}

function red_addon_atomic_enable_package($project, $packageId, $serviceId, $marker, $rich = false)
{
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create atomic enable fixture.');
    }
    $entrypoint = "<?php\nreturn static function (RED_Addon_Runtime_Registry \$runtime): void {\n" .
        '    file_put_contents(' . var_export($marker, true) . ", 'executed\\n', FILE_APPEND | LOCK_EX);\n" .
        '    $runtime->registerService(' . var_export($serviceId, true) . ", static function (): string { return 'ok'; });\n" .
        "};\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Atomic Enable Fixture',
        'description' => 'Disposable atomic enablement fixture.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => ['cms' => '>=5.1 <6.0', 'php' => '>=8.2 <9.0'],
        'provides' => [
            'components' => $rich ? ['redcms.atomic-rich/component'] : [],
            'services' => [$serviceId],
            'adminTools' => $rich ? ['redcms.atomic-rich/tool'] : [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$packageId . '.settings.manage'],
        'settings' => [],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [['path' => 'addon.php', 'sha256' => hash('sha256', $entrypoint)]],
        ],
        'uninstall' => ['defaultDataAction' => 'retain', 'allowExplicitPurge' => true],
    ];
    file_put_contents($directory . '/addon.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function red_addon_atomic_enable_component_package(
    $project,
    $packageId,
    $componentId,
    $marker,
    $serviceId = ''
) {
    $parts = explode('.', $packageId, 2);
    $directory = $project . '/addons/' . $parts[0] . '/' . $parts[1];
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create atomic component fixture.');
    }
    $viewTitle = $serviceId === ''
        ? 'Lifecycle component'
        : 'Combined lifecycle component';
    $viewSummary = $serviceId === ''
        ? 'Enabled through the supported Owner lifecycle.'
        : 'A default component and registration-only service share one package.';
    $serviceRegistration = $serviceId === ''
        ? ''
        : '    $runtime->registerService(' .
            var_export($serviceId, true) .
            ", static function (): string { return 'combined-service'; });\n";
    $entrypoint = "<?php\nreturn static function (RED_Addon_Runtime_Registry \$runtime): void {\n" .
        '    file_put_contents(' . var_export($marker, true) . ", 'component-executed\\n', FILE_APPEND | LOCK_EX);\n" .
        '    $runtime->registerComponent(' . var_export($componentId, true) .
        ", static function (array \$context): array {\n" .
        '        return [' .
        "'title' => " . var_export($viewTitle, true) . ', ' .
        "'summary' => " . var_export($viewSummary, true) . "];\n" .
        "    });\n" .
        $serviceRegistration .
        "};\n";
    file_put_contents($directory . '/addon.php', $entrypoint);
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => $serviceId === ''
            ? 'Atomic Component Enable Fixture'
            : 'Atomic Combined Enable Fixture',
        'description' => $serviceId === ''
            ? 'Disposable default public component fixture.'
            : 'Disposable default component with registration-only service.',
        'version' => '1.0.0',
        'type' => $serviceId === '' ? 'component' : 'content-package',
        'compatibility' => ['cms' => '>=5.1 <6.0', 'php' => '>=8.2 <9.0'],
        'provides' => [
            'components' => [$componentId],
            'services' => $serviceId === '' ? [] : [$serviceId],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$packageId . '.manage'],
        'settings' => [],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [['path' => 'addon.php', 'sha256' => hash('sha256', $entrypoint)]],
        ],
        'uninstall' => ['defaultDataAction' => 'retain', 'allowExplicitPurge' => true],
    ];
    file_put_contents(
        $directory . '/addon.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function red_addon_atomic_enable_record_installation($connection, array $package, $actorId)
{
    $snapshot = red_addon_registry_snapshot($package);
    if ($snapshot === null) {
        return false;
    }
    $state = 'installed_disabled';
    $stmt = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (PackageID, PackageVersion, PackageType, ManifestSHA256, InventorySHA256, LifecycleState, InstalledByAdminRecordID, UpdatedByAdminRecordID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ssssssii', $snapshot['id'], $snapshot['version'], $snapshot['type'], $snapshot['manifestSha256'], $snapshot['inventorySha256'], $state, $actorId, $actorId);
    $recorded = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $recorded;
}

function red_addon_atomic_enable_fingerprint($connection, array $packageIds)
{
    $quoted = array_map(static function ($id) use ($connection) {
        return "'" . mysqli_real_escape_string($connection, $id) . "'";
    }, $packageIds);
    return red_addon_atomic_enable_scalar(
        $connection,
        'SELECT SHA2(GROUP_CONCAT(ValueText ORDER BY ValueText SEPARATOR "|") , 256) FROM (' .
        'SELECT CONCAT_WS("#", PackageID, PackageVersion, LifecycleState, UpdatedByAdminRecordID) AS ValueText FROM RED_Addon_Installations WHERE PackageID IN (' . implode(',', $quoted) . ') ' .
        'UNION ALL SELECT CONCAT_WS("#", EventName, PackageID, PackageVersion, ActorAdminRecordID, Result, DetailCode) AS ValueText FROM RED_Addon_Activity_Log WHERE PackageID IN (' . implode(',', $quoted) . ')' .
        ') AS red_atomic_enable_fingerprint'
    );
}

try {
    red_addon_atomic_enable_cleanup($connection, $packageIds, $actorId, $temporaryRoot);
    $password = password_hash('AtomicEnableFixture-2026!', PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($connection, "INSERT INTO RED_Admin (RecordID, Username, Password, Administrator, Alias, AdminType, AdminComponents, AdminTools, Email, Contact_Form, Contact_Form_Pref, Donation_Form, Donation_Form_Pref) VALUES (?, 'codex_atomic_enabler', ?, 'Admin', 'Atomic', 'webmaster', '100', '1', 'atomic-enable@example.test', 'N', 'to', 'N', 'to')");
    mysqli_stmt_bind_param($stmt, 'is', $actorId, $password);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_query($connection, "INSERT INTO RED_Admin_Roles (AdminRecordID, RoleName, AssignedByAdminRecordID) VALUES ($actorId, 'owner', $actorId)");
    mysqli_query($connection, "INSERT INTO RED_Admin_Capabilities (AdminRecordID, Capability, GrantedByAdminRecordID) VALUES ($actorId, 'addons.enable', $actorId)");

    $fixtureProject = $temporaryRoot . '/project';
    red_addon_atomic_enable_package($fixtureProject, $readyPackageId, 'redcms.atomic-ready/service', $executionMarker);
    red_addon_atomic_enable_component_package(
        $fixtureProject,
        $componentPackageId,
        'redcms.atomic-component/card',
        $executionMarker
    );
    red_addon_atomic_enable_component_package(
        $fixtureProject,
        $combinedPackageId,
        'redcms.atomic-combined/card',
        $executionMarker,
        'redcms.atomic-combined/catalog'
    );
    red_addon_atomic_enable_package($fixtureProject, $richPackageId, 'redcms.atomic-rich/service', $executionMarker, true);
    $catalog = red_addon_discover($fixtureProject, ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]);
    $readyPackage = $catalog['packages'][$readyPackageId] ?? [];
    $componentPackage = $catalog['packages'][$componentPackageId] ?? [];
    $combinedPackage = $catalog['packages'][$combinedPackageId] ?? [];
    $richPackage = $catalog['packages'][$richPackageId] ?? [];
    red_addon_atomic_enable_assert(!empty($catalog['valid']) && !empty($readyPackage['valid']) && !empty($componentPackage['valid']) && !empty($combinedPackage['valid']) && !empty($richPackage['valid']) && !file_exists($executionMarker), 'fixture discovery is trusted and non-executing');
    red_addon_atomic_enable_assert(red_addon_atomic_enable_record_installation($connection, $readyPackage, $actorId) && red_addon_atomic_enable_record_installation($connection, $componentPackage, $actorId) && red_addon_atomic_enable_record_installation($connection, $combinedPackage, $actorId) && red_addon_atomic_enable_record_installation($connection, $richPackage, $actorId), 'fixtures start installed and disabled');

    $deniedPlan = red_addon_enable_transition_plan($connection, $readyPackage, 1, $catalog);
    red_addon_atomic_enable_assert(empty($deniedPlan['valid']) && $deniedPlan['errors'] === ['owner_enable_capability_required'], 'legacy administrator cannot plan an enable transition');
    $richPlan = red_addon_enable_transition_plan($connection, $richPackage, $actorId, $catalog);
    red_addon_atomic_enable_assert(empty($richPlan['valid']) && $richPlan['errors'] === ['supported_activation_profile_required'] && !file_exists($executionMarker), 'richer package surfaces remain non-executing and blocked');

    $plan = red_addon_enable_transition_plan($connection, $readyPackage, $actorId, $catalog);
    red_addon_atomic_enable_assert(!empty($plan['valid']) && $plan['transitionReady'] && ($plan['activationProfile']['id'] ?? '') === 'registration_only_service' && red_addon_valid_sha256($plan['planSha256']) && !file_exists($executionMarker), 'Owner dry run yields an exact non-executing registration-only transition plan');
    $before = red_addon_atomic_enable_fingerprint($connection, $packageIds);
    $stale = red_addon_enable_package($connection, $readyPackageId, $fixtureProject, $actorId, str_repeat('f', 64));
    red_addon_atomic_enable_assert($stale['status'] === 'plan_changed' && $before === red_addon_atomic_enable_fingerprint($connection, $packageIds) && !file_exists($executionMarker), 'stale plan is rejected before registrar execution or database mutation');

    $registrarFailure = red_addon_enable_package($connection, $readyPackageId, $fixtureProject, $actorId, $plan['planSha256'], null, static function () { throw new RuntimeException('forced_registrar_failure'); });
    red_addon_atomic_enable_assert($registrarFailure['status'] === 'registrar_validation_failed' && $before === red_addon_atomic_enable_fingerprint($connection, $packageIds) && !file_exists($executionMarker), 'registrar validation failure leaves state and audit unchanged');

    $auditFailure = red_addon_enable_package($connection, $readyPackageId, $fixtureProject, $actorId, $plan['planSha256'], static function () { return false; });
    red_addon_atomic_enable_assert($auditFailure['status'] === 'enable_transaction_failed' && $before === red_addon_atomic_enable_fingerprint($connection, $packageIds) && file_exists($executionMarker), 'audit failure rolls back the state transition atomically after registrar validation');
    unlink($executionMarker);

    $injectedFailure = red_addon_enable_package($connection, $readyPackageId, $fixtureProject, $actorId, $plan['planSha256'], null, null, static function () { throw new RuntimeException('forced_after_state_update_failure'); });
    red_addon_atomic_enable_assert($injectedFailure['status'] === 'enable_transaction_failed' && $before === red_addon_atomic_enable_fingerprint($connection, $packageIds) && file_exists($executionMarker), 'injected failure after compare-and-swap rolls back state and audit together');
    unlink($executionMarker);

    $enabled = red_addon_enable_package($connection, $readyPackageId, $fixtureProject, $actorId, $plan['planSha256']);
    red_addon_atomic_enable_assert($enabled['status'] === 'enabled' && ($enabled['runtimeRegistrations']['packageId'] ?? '') === $readyPackageId && file_exists($executionMarker), 'validated registrar and atomic state/audit transition enable the package');
    red_addon_atomic_enable_assert(red_addon_atomic_enable_scalar($connection, "SELECT CONCAT_WS(':', (SELECT LifecycleState FROM RED_Addon_Installations WHERE PackageID='redcms.atomic-ready'), (SELECT COUNT(*) FROM RED_Addon_Activity_Log WHERE PackageID='redcms.atomic-ready' AND EventName='addon.enable.completed' AND Result='succeeded' AND DetailCode='enabled'))") === 'enabled:1', 'enabled state and bounded audit fact commit together');

    $beforeComponentPlan = (string) file_get_contents($executionMarker);
    $componentPlan = red_addon_enable_transition_plan(
        $connection,
        $componentPackage,
        $actorId,
        $catalog
    );
    red_addon_atomic_enable_assert(
        !empty($componentPlan['valid'])
            && $componentPlan['transitionReady']
            && ($componentPlan['activationProfile']['id'] ?? '')
                === 'default_public_component'
            && red_addon_valid_sha256($componentPlan['planSha256'])
            && file_get_contents($executionMarker) === $beforeComponentPlan,
        'Owner dry run accepts the default public component without executing it'
    );
    $componentEnabled = red_addon_enable_package(
        $connection,
        $componentPackageId,
        $fixtureProject,
        $actorId,
        $componentPlan['planSha256']
    );
    red_addon_atomic_enable_assert(
        $componentEnabled['status'] === 'enabled'
            && ($componentEnabled['runtimeRegistrations']['registrations']['components'][0]
                ?? '') === 'redcms.atomic-component/card',
        'registrar validation and atomic state/audit transition enable the default public component'
    );
    red_addon_atomic_enable_assert(
        red_addon_atomic_enable_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.atomic-component'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.atomic-component'
                   AND EventName='addon.enable.completed'
                   AND Result='succeeded'
                   AND DetailCode='enabled'))"
        ) === 'enabled:1',
        'component enabled state and bounded audit fact commit together'
    );

    $combinedPlan = red_addon_enable_transition_plan(
        $connection,
        $combinedPackage,
        $actorId,
        $catalog
    );
    red_addon_atomic_enable_assert(
        !empty($combinedPlan['valid'])
            && $combinedPlan['transitionReady']
            && ($combinedPlan['activationProfile']['id'] ?? '')
                === 'default_public_component_with_services'
            && red_addon_valid_sha256($combinedPlan['planSha256']),
        'Owner dry run accepts a default component with registration-only services'
    );
    $combinedEnabled = red_addon_enable_package(
        $connection,
        $combinedPackageId,
        $fixtureProject,
        $actorId,
        $combinedPlan['planSha256']
    );
    red_addon_atomic_enable_assert(
        $combinedEnabled['status'] === 'enabled'
            && ($combinedEnabled['runtimeRegistrations']['registrations']['components'][0]
                ?? '') === 'redcms.atomic-combined/card'
            && ($combinedEnabled['runtimeRegistrations']['registrations']['services'][0]
                ?? '') === 'redcms.atomic-combined/catalog',
        'registrar validation atomically enables both declared combined-package registrations'
    );
    red_addon_atomic_enable_assert(
        red_addon_atomic_enable_scalar(
            $connection,
            "SELECT CONCAT_WS(':',
                (SELECT LifecycleState FROM RED_Addon_Installations
                 WHERE PackageID='redcms.atomic-combined'),
                (SELECT COUNT(*) FROM RED_Addon_Activity_Log
                 WHERE PackageID='redcms.atomic-combined'
                   AND EventName='addon.enable.completed'
                   AND Result='succeeded'
                   AND DetailCode='enabled'))"
        ) === 'enabled:1',
        'combined-package enabled state and bounded audit fact commit together'
    );

    $runtime = red_addon_runtime_bootstrap($connection, $fixtureProject);
    red_addon_atomic_enable_assert(
        $runtime['context']->handler('services', 'redcms.atomic-ready/service') !== null
            && $runtime['context']->handler(
                'components',
                'redcms.atomic-component/card'
            ) !== null
            && $runtime['context']->handler(
                'components',
                'redcms.atomic-combined/card'
            ) !== null
            && $runtime['context']->handler(
                'services',
                'redcms.atomic-combined/catalog'
            ) !== null,
        'a later runtime bootstrap loads all supported enabled profiles'
    );
    red_addon_runtime_set_request_context($runtime['context']);
    $publicContext = red_addon_public_component_context(
        'redcms.atomic-component/card',
        42,
        'default',
        'lifecycle-component',
        2,
        true
    );
    ob_start();
    $publicRendered = red_addon_public_component_render($publicContext);
    $publicOutput = (string) ob_get_clean();
    red_addon_atomic_enable_assert(
        $publicRendered
            && str_contains($publicOutput, '<h2>Lifecycle component</h2>')
            && str_contains(
                $publicOutput,
                'Enabled through the supported Owner lifecycle.'
            ),
        'the lifecycle-enabled component reaches the core-owned default public renderer'
    );
    $combinedPublicContext = red_addon_public_component_context(
        'redcms.atomic-combined/card',
        43,
        'default',
        'combined-lifecycle-component',
        3,
        true
    );
    ob_start();
    $combinedPublicRendered =
        red_addon_public_component_render($combinedPublicContext);
    $combinedPublicOutput = (string) ob_get_clean();
    red_addon_atomic_enable_assert(
        $combinedPublicRendered
            && str_contains(
                $combinedPublicOutput,
                '<h2>Combined lifecycle component</h2>'
            )
            && str_contains(
                $combinedPublicOutput,
                'A default component and registration-only service share one package.'
            ),
        'the combined package reaches the same core-owned default component renderer'
    );
    $repeat = red_addon_enable_package($connection, $readyPackageId, $fixtureProject, $actorId, $plan['planSha256']);
    red_addon_atomic_enable_assert($repeat['status'] === 'package_not_installed_disabled_current', 'an enabled package cannot be enabled a second time');

    $cli = (string) file_get_contents($projectRoot . '/scripts/admin-addon-enable.php');
    red_addon_atomic_enable_assert(str_contains($cli, "PHP_SAPI !== 'cli'") && str_contains($cli, '--confirm-database=') && str_contains($cli, '--confirm-package=') && str_contains($cli, '--confirm-version=') && str_contains($cli, '--confirm-plan-sha256=') && str_contains($cli, '--confirm-backup-sha256=') && str_contains($cli, '--confirm-state=') && str_contains($cli, '--apply') && !file_exists($projectRoot . '/admin/bin/addon_enable.php') && !file_exists($projectRoot . '/bin/addon_enable.php'), 'enable command is server-local with exact confirmation guards and no web endpoint');

    red_addon_atomic_enable_cleanup($connection, $packageIds, $actorId, $temporaryRoot);
    red_addon_atomic_enable_assert(red_addon_atomic_enable_scalar($connection, "SELECT CONCAT_WS(':', (SELECT COUNT(*) FROM RED_Addon_Installations WHERE PackageID IN ('redcms.atomic-ready','redcms.atomic-component','redcms.atomic-combined','redcms.atomic-rich')), (SELECT COUNT(*) FROM RED_Addon_Activity_Log WHERE PackageID IN ('redcms.atomic-ready','redcms.atomic-component','redcms.atomic-combined','redcms.atomic-rich')), (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId))") === '0:0:0' && !file_exists($executionMarker), 'all package, audit, authorization, and code fixtures clean up exactly');
    printf("Add-on atomic enable self-test passed: %d assertions.\n", $assertions);
} catch (Throwable $throwable) {
    red_addon_atomic_enable_cleanup($connection, $packageIds, $actorId, $temporaryRoot);
    fwrite(STDERR, $throwable->getMessage() . ' (after ' . $assertions . " assertions)\n");
    $db->close();
    exit(1);
}

$db->close();
