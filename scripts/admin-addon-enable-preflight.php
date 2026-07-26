<?php
/**
 * Server-local, read-only preflight for a future add-on enable transition.
 *
 * This command cannot apply a state transition and never includes addon.php.
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

function red_addon_enable_preflight_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/admin-addon-enable-preflight.php " .
        "--package=vendor.package --actor-admin=ID\n"
    );
}

$options = [
    'package' => '',
    'actorAdmin' => 0,
];
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--package=')) {
        $options['package'] = substr($argument, strlen('--package='));
    } elseif (str_starts_with($argument, '--actor-admin=')) {
        $options['actorAdmin'] = (int) substr(
            $argument,
            strlen('--actor-admin=')
        );
    } else {
        red_addon_enable_preflight_cli_usage();
        exit(64);
    }
}

if (!red_addon_valid_package_id($options['package'])
    || $options['actorAdmin'] <= 0
) {
    red_addon_enable_preflight_cli_usage();
    exit(64);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
if (!red_addon_registry_storage_available($connection)) {
    fwrite(
        STDERR,
        "Add-on registry storage is unavailable. Apply pending core migrations first.\n"
    );
    $db->close();
    exit(65);
}

$catalog = red_addon_discover($projectRoot, [
    'cmsVersion' => '5.1.0',
    'phpVersion' => PHP_VERSION,
]);
if (empty($catalog['valid'])
    || !isset($catalog['packages'][$options['package']])
) {
    fwrite(
        STDERR,
        "Package discovery, trust, or dependency validation failed.\n"
    );
    foreach ($catalog['errors'] ?? [] as $error) {
        fwrite(STDERR, 'ERROR: ' . $error . PHP_EOL);
    }
    foreach ($catalog['dependency']['errors'] ?? [] as $error) {
        fwrite(STDERR, 'ERROR: ' . $error . PHP_EOL);
    }
    $db->close();
    exit(65);
}

$plan = red_addon_enable_preflight_plan(
    $connection,
    $catalog['packages'][$options['package']],
    $options['actorAdmin'],
    $catalog
);
if (empty($plan['valid'])) {
    fwrite(
        STDERR,
        'Enablement preflight refused: ' .
        implode(', ', $plan['errors']) .
        PHP_EOL
    );
    $db->close();
    exit(65);
}

printf("Database: %s\n", $plan['database']);
printf("Package: %s %s\n", $plan['packageId'], $plan['version']);
printf("Actor administrator: %d\n", $options['actorAdmin']);
printf("Current state: %s\n", $plan['currentState']);
printf("Proposed target state: %s\n", $plan['targetState']);
printf(
    "Required enabled dependencies: %d\n",
    count($plan['requiredDependencies'])
);
printf(
    "Current applied migrations verified: %d\n",
    count($plan['appliedMigrations'])
);
printf(
    "Currently enabled packages checked: %d\n",
    count($plan['enabledPackages'])
);
printf(
    "Provided-capability conflicts: %d\n",
    count($plan['capabilityConflicts'])
);
printf("Route conflicts: %d\n", count($plan['routeConflicts']));
echo 'Gates:' . PHP_EOL;
foreach ($plan['gates'] as $gate => $status) {
    printf("  %s: %s\n", $gate, $status);
}
echo 'Blockers:' . PHP_EOL;
foreach ($plan['blockers'] as $blocker) {
    echo '  ' . json_encode(
        $blocker,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) . PHP_EOL;
}
echo 'Enable ready: no' . PHP_EOL;
echo 'Activation supported: no' . PHP_EOL;
echo 'State mutation: no' . PHP_EOL;
echo 'Runtime load: no' . PHP_EOL;
echo 'Plan SHA-256: ' . $plan['planSha256'] . PHP_EOL;
echo "READ ONLY: no database changes or package code execution occurred.\n";
echo "Runtime registration, remaining enablement gates, and an atomic state transition require separate reviewed work.\n";
$db->close();
exit(0);
