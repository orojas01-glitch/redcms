<?php
/**
 * Disposable check of first-time secret-reference setup for a package whose
 * ordinary and secret settings have never been saved (for example a payment
 * adapter's public key plus its secret references). The settings editor cannot
 * save the ordinary value while a secret is unbound, so the reference binding
 * accepts the never-stored ordinary values in the same atomic write.
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
    '/\Aredcms_(?:acceptance|addon_secret_setup)_[A-Za-z0-9_]+\z/',
    (string) DBNAME
)) {
    fwrite(
        STDERR,
        'Add-on secret first-setup self-test refused non-disposable database: '
            . DBNAME . "\n"
    );
    exit(65);
}

$assertions = 0;
$actorId = 2147000951;
$packageId = 'redcms.secret-first-setup';
$permission = 'secretsetup.settings.manage';
$temporaryRoot = sys_get_temp_dir() . '/redcms-secret-setup-'
    . bin2hex(random_bytes(8));
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

function red_addon_secret_first_setup_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_addon_secret_first_setup_scalar($connection, $sql)
{
    $query = mysqli_query($connection, $sql);
    $row = $query ? mysqli_fetch_row($query) : null;
    if ($query) {
        mysqli_free_result($query);
    }
    return is_array($row) ? (string) $row[0] : '';
}

function red_addon_secret_first_setup_sorted(array $values)
{
    ksort($values, SORT_STRING);
    return $values;
}

function red_addon_secret_first_setup_remove_tree($path)
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
            red_addon_secret_first_setup_remove_tree($path . '/' . $entry);
        }
    }
    rmdir($path);
}

function red_addon_secret_first_setup_cleanup($connection, $packageId, $actorId)
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
            error_log('Add-on secret first-setup cleanup failed.');
        }
    }
}

function red_addon_secret_first_setup_manifest($packageId, $permission)
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
        'name' => 'Secret first-setup fixture',
        'description' => 'Ordinary and secret settings, never saved.',
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
            $definition('provider.public-key', 'Public key', 'text'),
            $definition('provider.sandbox', 'Sandbox mode', 'boolean'),
            [
                'key' => 'provider.private-key',
                'label' => 'Private key',
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
        'integrity' => ['entrypoint' => 'addon.php', 'files' => []],
        'uninstall' => [
            'defaultDataAction' => 'retain',
            'allowExplicitPurge' => false,
        ],
    ];
}

red_addon_secret_first_setup_cleanup($connection, $packageId, $actorId);
try {
    $manifest = red_addon_secret_first_setup_manifest($packageId, $permission);
    $packageDirectory = $temporaryRoot . '/addons/redcms/secret-first-setup';
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
    red_addon_secret_first_setup_assert(
        !empty($package['valid']) && is_array($snapshot),
        'the fixture package passes data-only trust validation'
    );

    $password = password_hash('SecretFirstSetup-2026!', PASSWORD_DEFAULT);
    $statement = mysqli_prepare(
        $connection,
        "INSERT INTO RED_Admin (
            RecordID, Username, Password, Administrator, Alias, AdminType,
            AdminComponents, AdminTools, Email, Contact_Form,
            Contact_Form_Pref, Donation_Form, Donation_Form_Pref
         ) VALUES (?, 'codex_secret_setup', ?, 'Admin', 'SecretSetup',
                   'guest', '', '', 'secret-first-setup@example.test',
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
        return red_addon_secret_first_setup_scalar(
            $connection,
            "SELECT COUNT(*) FROM RED_Addon_Settings WHERE PackageID='$escaped'"
        );
    };

    $reference = 'config:redcms.secret-first-setup.private-key';
    $fixtureSecret = 'fixture-' . str_repeat('s', 24);
    putenv('RED_ADDON_SECRET_REFERENCES=' . $reference);
    putenv(
        'RED_ADDON_SECRET_VALUES_JSON=' . json_encode(
            [$reference => $fixtureSecret],
            JSON_UNESCAPED_SLASHES
        )
    );
    $references = ['provider.private-key' => $reference];
    $ordinary = [
        'provider.public-key' => 'public-fixture-key',
        'provider.sandbox' => '1',
    ];

    // The deadlock this fixes: the editor needs the secret bound first ...
    $context = red_addon_setting_editor_context($connection, $package, $actorId);
    $editorRefused = red_addon_setting_editor_update(
        $connection,
        $package,
        $actorId,
        $ordinary,
        (string) ($context['planSha256'] ?? str_repeat('a', 64))
    );
    red_addon_secret_first_setup_assert(
        !empty($context['ready'])
            && $editorRefused['ok'] === false
            && $editorRefused['reason'] === 'secret_unconfigured'
            && $storedCount() === '0',
        'the settings editor alone cannot save while the secret is unbound'
    );
    // ... and binding references alone needs the ordinary values first.
    $referencesOnly = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        $references
    );
    red_addon_secret_first_setup_assert(
        empty($referencesOnly['valid'])
            && $referencesOnly['reason'] === 'setting_required',
        'binding references without the never-stored ordinary values is refused'
    );

    $partial = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        $references,
        ['provider.public-key' => 'public-fixture-key']
    );
    red_addon_secret_first_setup_assert(
        empty($partial['valid']) && $partial['reason'] === 'setting_required',
        'every never-stored ordinary setting must be supplied'
    );

    foreach ([
        'a secret setting as an ordinary value' => [
            'provider.private-key' => 'not-allowed',
        ] + $ordinary,
        'an undeclared setting' => ['provider.unknown' => 'x'] + $ordinary,
        'a malformed boolean' => [
            'provider.public-key' => 'public-fixture-key',
            'provider.sandbox' => 'yes',
        ],
    ] as $label => $invalidOrdinary) {
        $invalid = red_addon_secret_replacement_target(
            $connection,
            $package,
            $actorId,
            $references,
            $invalidOrdinary
        );
        red_addon_secret_first_setup_assert(
            empty($invalid['valid']) && $invalid['reason'] === 'invalid_values',
            'first-time setup refuses ' . $label
        );
    }

    $unavailable = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        ['provider.private-key' => 'config:redcms.secret-first-setup.missing'],
        $ordinary
    );
    red_addon_secret_first_setup_assert(
        empty($unavailable['valid'])
            && $unavailable['reason'] === 'secret_unavailable'
            && $storedCount() === '0',
        'a reference without a provisioned server-local value is refused'
    );

    $target = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        $references,
        $ordinary
    );
    red_addon_secret_first_setup_assert(
        !empty($target['valid'])
            && red_addon_valid_sha256($target['plan']['planSha256'] ?? ''),
        'references plus never-stored ordinary values produce one write plan'
    );
    $stale = red_addon_secret_replacement_update(
        $connection,
        $package,
        $actorId,
        $references,
        str_repeat('d', 64),
        $ordinary
    );
    red_addon_secret_first_setup_assert(
        $stale['ok'] === false
            && $stale['reason'] === 'stale_plan'
            && $storedCount() === '0',
        'a stale first-time plan is refused without writing'
    );
    $saved = red_addon_secret_replacement_update(
        $connection,
        $package,
        $actorId,
        $references,
        $target['plan']['planSha256'],
        $ordinary
    );
    $escapedPackage = mysqli_real_escape_string($connection, $packageId);
    red_addon_secret_first_setup_assert(
        $saved['ok'] === true
            && $saved['status'] === 'updated'
            && $storedCount() === '3'
            && red_addon_secret_first_setup_scalar(
                $connection,
                "SELECT SecretReference FROM RED_Addon_Settings
                 WHERE PackageID='$escapedPackage'
                   AND SettingKey='provider.private-key'"
            ) === $reference
            && red_addon_secret_first_setup_scalar(
                $connection,
                "SELECT COUNT(*) FROM RED_Addon_Settings
                 WHERE PackageID='$escapedPackage'
                   AND (ValueJSON LIKE '%" . $fixtureSecret . "%'
                        OR SecretReference LIKE '%" . $fixtureSecret . "%')"
            ) === '0',
        'one atomic write stores the ordinary values and the reference, never the secret'
    );

    $after = red_addon_setting_editor_context($connection, $package, $actorId);
    red_addon_secret_first_setup_assert(
        !empty($after['ready'])
            && $after['ordinaryValues']['provider.public-key'] === 'public-fixture-key'
            && $after['ordinaryValues']['provider.sandbox'] === true,
        'the settings editor opens normally after first-time setup'
    );

    $again = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        $references,
        ['provider.public-key' => 'another-key']
    );
    red_addon_secret_first_setup_assert(
        empty($again['valid']) && $again['reason'] === 'invalid_values',
        'stored ordinary values cannot be changed through reference binding'
    );
    $rebind = red_addon_secret_replacement_target(
        $connection,
        $package,
        $actorId,
        $references
    );
    red_addon_secret_first_setup_assert(
        !empty($rebind['valid']),
        'reference replacement without ordinary values keeps working after setup'
    );
} finally {
    putenv('RED_ADDON_SECRET_REFERENCES');
    putenv('RED_ADDON_SECRET_VALUES_JSON');
    red_addon_secret_first_setup_cleanup($connection, $packageId, $actorId);
    red_addon_secret_first_setup_remove_tree($temporaryRoot);
}

$escapedPackage = mysqli_real_escape_string($connection, $packageId);
red_addon_secret_first_setup_assert(
    red_addon_secret_first_setup_scalar(
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
    'Add-on secret first-setup self-test passed (' . $assertions
        . " assertions).\n"
);
