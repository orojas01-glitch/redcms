<?php
/** Disposable database proof for package-runtime secret consumption. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/class/class_connection.php';
require_once $projectRoot . '/includes/addon_service_helpers.php';
require_once $projectRoot . '/includes/addon_enable_helpers.php';

if (!preg_match(
    '/\Aredcms_(?:acceptance|runtime_secret)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Runtime secret self-test refused non-disposable database: ' .
        DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$packageId = 'redcms.runtime-secret-db';
$serviceId = 'runtime.secret-db';
$settingKey = 'payment.api-key';
$reference = 'config:redcms.runtime-secret-db.api-key';
$actorId = 2147000957;
$temporaryRoot = sys_get_temp_dir() . '/redcms-runtime-secret-'
    . bin2hex(random_bytes(8));
$fixtureProject = $temporaryRoot . '/project';
$executionMarker = $temporaryRoot . '/registrar-executed';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_runtime_secret_db_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_runtime_secret_db_remove_tree($path)
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

function red_addon_runtime_secret_db_cleanup(
    $connection,
    $packageId,
    $temporaryRoot,
    $actorId
) {
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
    foreach (['RED_Admin_Capabilities', 'RED_Admin_Roles', 'RED_Admin'] as $table) {
        $column = $table === 'RED_Admin' ? 'RecordID' : 'AdminRecordID';
        mysqli_query(
            $connection,
            'DELETE FROM ' . $table . ' WHERE ' . $column . '=' . (int) $actorId
        );
    }
    red_addon_runtime_secret_db_remove_tree($temporaryRoot);
}

function red_addon_runtime_secret_db_package(
    $fixtureProject,
    $packageId,
    $serviceId,
    $settingKey,
    $executionMarker
) {
    $parts = explode('.', $packageId, 2);
    $directory = $fixtureProject . '/addons/' . $parts[0] . '/' . $parts[1];
    if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create secret package fixture.');
    }
    $entrypoint = <<<'PHP'
<?php
return static function (RED_Addon_Runtime_Registry $runtime): void {
    file_put_contents('__MARKER__', "registrar\n", FILE_APPEND | LOCK_EX);
    $runtime->registerService('__SERVICE__', static function (
        RED_Addon_Service_Request $request
    ): RED_Addon_Service_Result {
        $secret = null;
        $resolved = $request->secret('__SETTING__', $secret);
        if (empty($resolved['resolved']) || !is_string($secret)) {
            return RED_Addon_Service_Result::failure('secret_unavailable');
        }
        return RED_Addon_Service_Result::success([
            'configured' => true,
            'secretLength' => strlen($secret),
        ]);
    });
};
PHP;
    $entrypoint = str_replace(
        ['__MARKER__', '__SERVICE__', '__SETTING__'],
        [
            addcslashes($executionMarker, "\\'") ,
            addcslashes($serviceId, "\\'"),
            addcslashes($settingKey, "\\'"),
        ],
        $entrypoint
    );
    file_put_contents($directory . '/addon.php', $entrypoint);
    $manifest = [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Runtime secret fixture',
        'description' => 'Disposable secret-capable service fixture.',
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
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$packageId . '.settings.manage'],
        'settings' => [[
            'key' => $settingKey,
            'label' => 'Payment API key',
            'type' => 'secret-reference',
            'secret' => true,
            'permission' => $packageId . '.settings.manage',
        ]],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => [
            'entrypoint' => 'addon.php',
            'files' => [[
                'path' => 'addon.php',
                'sha256' => hash_file('sha256', $directory . '/addon.php'),
            ]],
        ],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
    file_put_contents(
        $directory . '/addon.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    return $directory;
}

function red_addon_runtime_secret_db_insert_installation(
    $connection,
    array $snapshot
) {
    $state = 'enabled';
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Installations (
            PackageID, PackageVersion, PackageType, ManifestSHA256,
            InventorySHA256, LifecycleState,
            InstalledByAdminRecordID, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, ?, ?, ?, 2147000956, 2147000956)'
    );
    mysqli_stmt_bind_param(
        $statement,
        'ssssss',
        $snapshot['id'],
        $snapshot['version'],
        $snapshot['type'],
        $snapshot['manifestSha256'],
        $snapshot['inventorySha256'],
        $state
    );
    if (!mysqli_stmt_execute($statement)) {
        mysqli_stmt_close($statement);
        throw new RuntimeException('Could not insert secret installation.');
    }
    mysqli_stmt_close($statement);
}

function red_addon_runtime_secret_db_insert_owner($connection, $actorId)
{
    $password = password_hash('RuntimeSecretFixture-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, ?, ?, \'Admin\', \'RuntimeSecret\', \'webmaster\',
                   \'\', \'\', \'runtime-secret@example.test\',
                   \'N\', \'to\', \'N\', \'to\')'
    );
    $username = 'codex_runtime_secret_owner';
    mysqli_stmt_bind_param(
        $statement,
        'iss',
        $actorId,
        $username,
        $password
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
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
}

try {
    red_addon_runtime_secret_db_cleanup(
        $connection,
        $packageId,
        $temporaryRoot,
        $actorId
    );
    red_addon_runtime_secret_db_package(
        $fixtureProject,
        $packageId,
        $serviceId,
        $settingKey,
        $executionMarker
    );
    $catalog = red_addon_discover(
        $fixtureProject,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $package = $catalog['packages'][$packageId] ?? null;
    $snapshot = is_array($package)
        ? red_addon_registry_snapshot($package)
        : null;
    red_addon_runtime_secret_db_assert(
        !empty($catalog['valid'])
            && is_array($package)
            && is_array($snapshot),
        'the secret-capable service fixture validates without executing PHP'
    );
    red_addon_runtime_secret_db_insert_installation(
        $connection,
        $snapshot
    );
    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Addon_Settings (
            PackageID, SettingKey, ValueType, ValueJSON,
            SecretReference, UpdatedByAdminRecordID
         ) VALUES (?, ?, ?, NULL, ?, 2147000956)'
    );
    $type = 'secret-reference';
    mysqli_stmt_bind_param(
        $statement,
        'ssss',
        $packageId,
        $settingKey,
        $type,
        $reference
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    putenv('RED_ADDON_SECRET_REFERENCES=' . $reference);
    putenv('RED_ADDON_SECRET_VALUES_JSON=' . json_encode([
        $reference => 'runtime-fixture-secret',
    ], JSON_UNESCAPED_SLASHES));

    $runtime = red_addon_runtime_bootstrap($connection, $fixtureProject);
    $context = red_addon_runtime_set_request_context($runtime['context']);
    $access = red_addon_runtime_secret_access($packageId);
    $response = red_addon_service_invoke(
        $serviceId,
        'health.check',
        []
    );
    red_addon_runtime_secret_db_assert(
        $context->order() === [$packageId]
            && $access instanceof RED_Addon_Runtime_Secret_Access
            && $access->settingCount() === 1
            && !empty($response['success'])
            && $response['reason'] === 'completed'
            && ($response['data']['secretLength'] ?? 0) === strlen(
                'runtime-fixture-secret'
            )
            && !str_contains(json_encode($response), 'runtime-fixture-secret')
            && file_exists($executionMarker),
        'enabled runtime resolves one local secret for its own service without disclosure'
    );

    red_addon_runtime_secret_db_insert_owner($connection, $actorId);
    mysqli_query(
        $connection,
        "UPDATE RED_Addon_Installations
         SET LifecycleState='installed_disabled'
         WHERE PackageID='" . mysqli_real_escape_string(
            $connection,
            $packageId
        ) . "'"
    );
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    $preflight = red_addon_enable_preflight_plan(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    red_addon_runtime_secret_db_assert(
        !empty($preflight['valid'])
            && !empty($preflight['declarativeGatesReady'])
            && ($preflight['activationProfile']['id'] ?? '') ===
                'registration_only_service_with_secrets'
            && ($preflight['gates']['secretRuntime'] ?? '') === 'passed'
            && ($preflight['gates']['settings'] ?? '') === 'passed'
            && in_array(
                'registrar_validation_required',
                array_column($preflight['blockers'], 'code'),
                true
            )
            && !str_contains(
                json_encode($preflight),
                'runtime-fixture-secret'
            ),
        'Owner preflight clears only the secret-capable service profile with value-free evidence'
    );
    $transition = red_addon_enable_transition_plan(
        $connection,
        $package,
        $actorId,
        $catalog
    );
    $enabled = red_addon_enable_package(
        $connection,
        $packageId,
        $fixtureProject,
        $actorId,
        $transition['planSha256']
    );
    red_addon_runtime_secret_db_assert(
        !empty($transition['transitionReady'])
            && ($enabled['status'] ?? '') === 'enabled'
            && file_exists($executionMarker),
        'the reviewed secret-capable profile enables atomically after registrar validation'
    );

    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    @unlink($executionMarker);
    putenv('RED_ADDON_SECRET_VALUES_JSON');
    try {
        red_addon_runtime_bootstrap($connection, $fixtureProject);
        red_addon_runtime_secret_db_assert(
            false,
            'missing server-local value must block package runtime bootstrap'
        );
    } catch (RuntimeException $exception) {
        red_addon_runtime_secret_db_assert(
            str_contains($exception->getMessage(), 'secret configuration')
                && !str_contains(
                    $exception->getMessage(),
                    'runtime-fixture-secret'
                )
                && !file_exists($executionMarker),
            'unavailable runtime secret fails before package PHP executes'
        );
    }
} finally {
    unset($GLOBALS['RED_ADDON_RUNTIME_CONTEXT']);
    putenv('RED_ADDON_SECRET_REFERENCES');
    putenv('RED_ADDON_SECRET_VALUES_JSON');
    red_addon_runtime_secret_db_cleanup(
        $connection,
        $packageId,
        $temporaryRoot,
        $actorId
    );
}

fwrite(
    STDOUT,
    'Add-on runtime secret bootstrap self-test passed (' . $assertions
        . " assertions).\n"
);

?>
