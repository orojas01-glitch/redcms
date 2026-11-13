<?php
/**
 * Server-local first-time binding of add-on secret references.
 *
 * Binds opaque, operator-provisioned `config:` references to a package's
 * secret-reference settings, together with any ordinary settings that have
 * never been stored. Secret values are never accepted, printed, or stored by
 * this command; core only proves each reference resolves server-locally.
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
require_once $projectRoot . '/includes/addon_secret_replacement_helpers.php';

function red_addon_secret_setup_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-addon-secret-setup.php --package=vendor.package --actor-admin=ID \\\n" .
        "    --reference=setting.key=config:reference [--reference=...] \\\n" .
        "    [--setting=setting.key=value ...]\n" .
        "  ... same arguments ... --confirm-database=NAME --confirm-plan-sha256=SHA256 --apply\n" .
        "\n" .
        "--setting is accepted only for ordinary settings that have never been stored.\n" .
        "Secret values are never accepted here; provision them server-locally first.\n"
    );
}

$options = [
    'package' => '',
    'actorAdmin' => 0,
    'references' => [],
    'settings' => [],
    'confirmDatabase' => '',
    'confirmPlanSha256' => '',
    'apply' => false,
];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $options['apply'] = true;
    } elseif (str_starts_with($argument, '--package=')) {
        $options['package'] = substr($argument, strlen('--package='));
    } elseif (str_starts_with($argument, '--actor-admin=')) {
        $options['actorAdmin'] = (int) substr(
            $argument,
            strlen('--actor-admin=')
        );
    } elseif (str_starts_with($argument, '--reference=')
        || str_starts_with($argument, '--setting=')
    ) {
        $isReference = str_starts_with($argument, '--reference=');
        $pair = substr(
            $argument,
            strlen($isReference ? '--reference=' : '--setting=')
        );
        $separator = strpos($pair, '=');
        $bucket = $isReference ? 'references' : 'settings';
        if ($separator === false || $separator === 0) {
            red_addon_secret_setup_cli_usage();
            exit(64);
        }
        $key = substr($pair, 0, $separator);
        if (array_key_exists($key, $options[$bucket])) {
            red_addon_secret_setup_cli_usage();
            exit(64);
        }
        $options[$bucket][$key] = substr($pair, $separator + 1);
    } elseif (str_starts_with($argument, '--confirm-database=')) {
        $options['confirmDatabase'] = substr(
            $argument,
            strlen('--confirm-database=')
        );
    } elseif (str_starts_with($argument, '--confirm-plan-sha256=')) {
        $options['confirmPlanSha256'] = substr(
            $argument,
            strlen('--confirm-plan-sha256=')
        );
    } else {
        red_addon_secret_setup_cli_usage();
        exit(64);
    }
}
if (!red_addon_valid_package_id($options['package'])
    || $options['actorAdmin'] <= 0
    || $options['references'] === []
) {
    red_addon_secret_setup_cli_usage();
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
if (!red_addon_install_storage_available($connection)) {
    fwrite(STDERR, "Add-on storage is unavailable. Apply pending core migrations first.\n");
    $db->close();
    exit(65);
}
$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
$package = $catalog['packages'][$options['package']] ?? null;
if (empty($catalog['valid']) || !is_array($package)) {
    fwrite(STDERR, "Add-on discovery, trust, or dependency validation failed.\n");
    $db->close();
    exit(65);
}

$target = red_addon_secret_replacement_target(
    $connection,
    $package,
    $options['actorAdmin'],
    $options['references'],
    $options['settings']
);
if (empty($target['valid'])
    || !red_addon_valid_sha256($target['plan']['planSha256'] ?? '')
) {
    fwrite(
        STDERR,
        'Secret reference setup refused: ' .
            (string) ($target['reason'] ?? 'settings_unavailable') . PHP_EOL
    );
    $db->close();
    exit(65);
}

$database = red_addon_enable_preflight_database_name($connection);
$referenceKeys = array_keys($options['references']);
$settingKeys = array_keys($options['settings']);
sort($referenceKeys, SORT_STRING);
sort($settingKeys, SORT_STRING);
printf("Database: %s\n", $database);
printf("Package: %s\n", $options['package']);
printf("Actor administrator: %d\n", $options['actorAdmin']);
printf(
    "Secret references to bind (all resolve server-locally): %s\n",
    implode(', ', $referenceKeys)
);
printf(
    "First-time ordinary settings: %s\n",
    $settingKeys === [] ? 'none' : implode(', ', $settingKeys)
);
echo 'Plan SHA-256: ' . $target['plan']['planSha256'] . PHP_EOL;

if (!$options['apply']) {
    echo "DRY RUN: no setting was written and no secret value was read into output.\n";
    echo "Repeat with --confirm-database, --confirm-plan-sha256 and --apply.\n";
    $db->close();
    exit(0);
}

if ($options['confirmDatabase'] !== $database
    || !hash_equals(
        $target['plan']['planSha256'],
        $options['confirmPlanSha256']
    )
) {
    fwrite(STDERR, "Apply requires exact database and plan confirmations.\n");
    $db->close();
    exit(64);
}

$result = red_addon_secret_replacement_update(
    $connection,
    $package,
    $options['actorAdmin'],
    $options['references'],
    $options['confirmPlanSha256'],
    $options['settings']
);
$db->close();
if (empty($result['ok'])) {
    fwrite(
        STDERR,
        'Secret reference setup failed closed: ' .
            (string) ($result['reason'] ?? 'settings_unavailable') . PHP_EOL
    );
    exit(1);
}
printf(
    "Settings %s for %s; package lifecycle unchanged.\n",
    $result['status'],
    $options['package']
);
exit(0);

?>
