<?php
/**
 * Disposable end-to-end check of the core settings editor for a package whose
 * settings have no defaults and have never been saved: the editor opens with
 * empty fields, refuses incomplete or stale submissions, saves a complete
 * submission once, and reports an unchanged resubmission.
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

if (!preg_match(
    '/\Aredcms_(?:acceptance|addon_setting_editor)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on setting editor first-save self-test refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000950;
$packageId = 'redcms.editor-first-save';
$permission = 'firstsave.settings.manage';
$temporaryRoot = sys_get_temp_dir() . '/redcms-setting-editor-'
    . bin2hex(random_bytes(8));
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_editor_first_save_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_editor_first_save_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) $row[0] : '';
}

function red_addon_editor_first_save_sorted(array $values)
{
    ksort($values, SORT_STRING);
    return $values;
}

function red_addon_editor_first_save_remove_tree($path)
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            red_addon_editor_first_save_remove_tree($path . '/' . $entry);
        }
    }
    rmdir($path);
}

function red_addon_editor_first_save_cleanup($connection, $packageId, $actorId)
{
    $escaped = mysqli_real_escape_string($connection, $packageId);
    $id = (int) $actorId;
    foreach ([
        "DELETE FROM RED_Addon_Settings WHERE PackageID='$escaped'",
        "DELETE FROM RED_Addon_Activity_Log WHERE PackageID='$escaped'",
        "DELETE FROM RED_Addon_Installations WHERE PackageID='$escaped'",
        "DELETE FROM RED_Admin_Capabilities WHERE AdminRecordID=$id",
        "DELETE FROM RED_Admin_Roles WHERE AdminRecordID=$id",
        "DELETE FROM RED_Admin WHERE RecordID=$id",
    ] as $sql) {
        try {
            mysqli_query($connection, $sql);
        } catch (Throwable $throwable) {
            error_log('Add-on setting editor first-save cleanup failed.');
        }
    }
}

function red_addon_editor_first_save_manifest($packageId, $permission)
{
    $definition = static function ($key, $label, $type) use ($permission) {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'secret' => false,
            'permission' => $permission,
            'default' => null,
        ];
    };
    return [
        '$schema' => 'https://red-sphere.com/schemas/addon-manifest-v1.json',
        'schemaVersion' => 1,
        'id' => $packageId,
        'name' => 'Editor first-save fixture',
        'description' => 'Settings without defaults, never saved.',
        'version' => '1.0.0',
        'type' => 'service',
        'compatibility' => ['cms' => '>=5.1 <6.0', 'php' => '>=8.2 <9.0'],
        'provides' => [
            'components' => [],
            'services' => [$packageId . '/service'],
            'adminTools' => [],
            'adapters' => [],
        ],
        'dependencies' => ['required' => [], 'optional' => []],
        'permissions' => [$permission],
        'settings' => [
            $definition('catalog.currency', 'Catalog currency', 'text'),
            $definition('checkout.pickup-enabled', 'Enable pickup', 'boolean'),
            $definition('checkout.fee-minor', 'Fee in minor units', 'integer'),
        ],
        'migrations' => [],
        'routes' => [],
        'jobs' => [],
        'outboundHosts' => [],
        'assets' => ['public' => [], 'admin' => []],
        'integrity' => ['entrypoint' => 'addon.php', 'files' => []],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
}

red_addon_editor_first_save_cleanup($connection, $packageId, $actorId);
try {
    $manifest = red_addon_editor_first_save_manifest($packageId, $permission);
    $packageDirectory = $temporaryRoot . '/addons/redcms/editor-first-save';
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
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
    $package = red_addon_validate_manifest(
        $packageId,
        $temporaryRoot,
        ['cmsVersion' => '5.1.0', 'phpVersion' => PHP_VERSION]
    );
    $snapshot = red_addon_registry_snapshot($package);
    red_addon_editor_first_save_assert(
        !empty($package['valid']) && is_array($snapshot),
        'the fixture package passes data-only trust validation'
    );

    $password = password_hash('EditorFirstSave-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_editor_first', ?, 'Admin', 'EditorFirst',
                   'guest', '', '', 'editor-first-save@example.test',
                   'N', 'to', 'N', 'to')"
    );
    mysqli_stmt_bind_param($statement, 'is', $actorId, $password);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $statement = mysqli_prepare(
        $connection,
        'INSERT INTO RED_Admin_Capabilities (
            AdminRecordID, Capability, GrantedByAdminRecordID
         ) VALUES (?, ?, ?)'
    );
    mysqli_stmt_bind_param($statement, 'isi', $actorId, $permission, $actorId);
    mysqli_stmt_execute($statement);
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
        $actorId,
        $actorId
    );
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    $storedCount = static function () use ($connection, $packageId): string {
        $escaped = mysqli_real_escape_string($connection, $packageId);
        return red_addon_editor_first_save_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Settings WHERE PackageID='$escaped'"
        );
    };

    // The editor opens for first-time setup.
    $context = red_addon_setting_editor_context($connection, $package, $actorId);
    red_addon_editor_first_save_assert(
        !empty($context['ready'])
            && $context['lifecycleState'] === 'installed_disabled'
            && red_addon_editor_first_save_sorted($context['ordinaryValues']) === [
                'catalog.currency' => null,
                'checkout.fee-minor' => null,
                'checkout.pickup-enabled' => null,
            ]
            && red_addon_valid_sha256($context['planSha256']),
        'the editor opens with empty values when nothing has been saved yet'
    );
    $csrf = str_repeat('c', 64);
    $html = red_addon_setting_editor_render($context, $csrf);
    red_addon_editor_first_save_assert(
        str_contains($html, 'name="Settings[catalog.currency]"')
            && str_contains($html, 'name="Settings[checkout.fee-minor]"')
            && str_contains($html, 'name="Settings[checkout.pickup-enabled]"')
            && str_contains($html, 'value="' . $context['planSha256'] . '"')
            && !str_contains($html, 'temporarily unavailable'),
        'the first-time editor renders every field and its plan'
    );

    $complete = [
        'catalog.currency' => 'COP',
        'checkout.fee-minor' => '0',
        'checkout.pickup-enabled' => '1',
    ];
    $incomplete = $complete;
    $incomplete['checkout.fee-minor'] = '';
    $refused = red_addon_setting_editor_update(
        $connection,
        $package,
        $actorId,
        $incomplete,
        $context['planSha256']
    );
    red_addon_editor_first_save_assert(
        $refused['ok'] === false
            && $refused['reason'] === 'invalid_values'
            && $storedCount() === '0',
        'an empty required field is refused and nothing is stored'
    );

    $missing = $complete;
    unset($missing['catalog.currency']);
    $refused = red_addon_setting_editor_update(
        $connection,
        $package,
        $actorId,
        $missing,
        $context['planSha256']
    );
    red_addon_editor_first_save_assert(
        $refused['ok'] === false
            && $refused['reason'] === 'invalid_values'
            && $storedCount() === '0',
        'a submission that omits a required setting is refused'
    );

    $refused = red_addon_setting_editor_update(
        $connection,
        $package,
        $actorId,
        $complete,
        str_repeat('d', 64)
    );
    red_addon_editor_first_save_assert(
        $refused['ok'] === false
            && $refused['reason'] === 'stale_plan'
            && $storedCount() === '0',
        'a submission rendered from another plan is reported as stale'
    );

    $saved = red_addon_setting_editor_update(
        $connection,
        $package,
        $actorId,
        $complete,
        $context['planSha256']
    );
    red_addon_editor_first_save_assert(
        $saved['ok'] === true
            && $saved['status'] === 'updated'
            && $storedCount() === '3',
        'a complete first-time submission saves every setting'
    );

    $after = red_addon_setting_editor_context($connection, $package, $actorId);
    red_addon_editor_first_save_assert(
        !empty($after['ready'])
            && red_addon_editor_first_save_sorted($after['ordinaryValues']) === [
                'catalog.currency' => 'COP',
                'checkout.fee-minor' => 0,
                'checkout.pickup-enabled' => true,
            ]
            && !hash_equals($context['planSha256'], $after['planSha256']),
        'the reopened editor shows the saved values under a new plan'
    );

    $changed = $complete;
    $changed['checkout.fee-minor'] = '500000';
    $stale = red_addon_setting_editor_update(
        $connection,
        $package,
        $actorId,
        $changed,
        $context['planSha256']
    );
    red_addon_editor_first_save_assert(
        $stale['ok'] === false
            && $stale['reason'] === 'stale_plan'
            && red_addon_setting_editor_context(
                $connection,
                $package,
                $actorId
            )['ordinaryValues']['checkout.fee-minor'] === 0,
        'a form opened before the save cannot overwrite it'
    );

    $updated = red_addon_setting_editor_update(
        $connection,
        $package,
        $actorId,
        $changed,
        $after['planSha256']
    );
    $final = red_addon_setting_editor_context($connection, $package, $actorId);
    red_addon_editor_first_save_assert(
        $updated['ok'] === true
            && $updated['status'] === 'updated'
            && $final['ordinaryValues']['checkout.fee-minor'] === 500000,
        'a current form changes a saved value'
    );

    $unchanged = red_addon_setting_editor_update(
        $connection,
        $package,
        $actorId,
        $changed,
        $final['planSha256']
    );
    red_addon_editor_first_save_assert(
        $unchanged['ok'] === true && $unchanged['status'] === 'unchanged',
        'resubmitting the same values reports unchanged'
    );
} finally {
    red_addon_editor_first_save_cleanup($connection, $packageId, $actorId);
    red_addon_editor_first_save_remove_tree($temporaryRoot);
}

$escapedPackage = mysqli_real_escape_string($connection, $packageId);
red_addon_editor_first_save_assert(
    red_addon_editor_first_save_scalar(
        $connection,
        "SELECT CONCAT_WS(
            ':',
            (SELECT COUNT(*) FROM RED_Addon_Settings
             WHERE PackageID='$escapedPackage'),
            (SELECT COUNT(*) FROM RED_Addon_Installations
             WHERE PackageID='$escapedPackage'),
            (SELECT COUNT(*) FROM RED_Admin WHERE RecordID=$actorId)
         )"
    ) === '0:0:0'
        && !file_exists($temporaryRoot),
    'all fixtures clean up'
);
$db->close();

fwrite(
    STDOUT,
    'Add-on setting editor first-save self-test passed (' . $assertions
        . " assertions).\n"
);
