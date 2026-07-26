<?php
/**
 * Read-only RED-CMS add-on manifest and trust-inventory validator.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/bootstrap.php';
require_once $projectRoot . '/includes/addon_manifest_helpers.php';

function red_addon_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  php scripts/addon-validate.php --all [--json]\n" .
        "  php scripts/addon-validate.php VENDOR.PACKAGE [--json]\n"
    );
}

function red_addon_cli_print_package(array $package)
{
    $status = !empty($package['valid']) ? 'PASS' : 'FAIL';
    $manifest = isset($package['manifest']) && is_array($package['manifest'])
        ? $package['manifest']
        : [];
    $label = isset($manifest['name']) && is_string($manifest['name'])
        ? $manifest['name']
        : (string) ($package['id'] ?? 'unknown');
    $version = isset($manifest['version']) && is_string($manifest['version'])
        ? ' ' . $manifest['version']
        : '';
    echo $status . ' ' . ($package['id'] ?? 'unknown') . ' — ' . $label . $version . PHP_EOL;
    foreach ($package['errors'] ?? [] as $error) {
        echo '  ERROR: ' . $error . PHP_EOL;
    }
    foreach ($package['warnings'] ?? [] as $warning) {
        echo '  WARNING: ' . $warning . PHP_EOL;
    }
    $integrity = isset($package['integrity']) && is_array($package['integrity'])
        ? $package['integrity']
        : [];
    echo '  Integrity: ' . (int) ($integrity['verifiedFiles'] ?? 0) . '/' .
        (int) ($integrity['declaredFiles'] ?? 0) . ' files verified.' . PHP_EOL;
}

$arguments = array_slice($argv, 1);
$jsonOutput = false;
$positionals = [];
foreach ($arguments as $argument) {
    if ($argument === '--json') {
        $jsonOutput = true;
        continue;
    }
    $positionals[] = $argument;
}

if (count($positionals) !== 1) {
    red_addon_cli_usage();
    exit(64);
}

$target = $positionals[0];
if ($target === '--all') {
    $catalog = red_addon_discover($projectRoot);
} else {
    if (!red_addon_valid_package_id($target)) {
        fwrite(STDERR, "Package id must use VENDOR.PACKAGE lowercase format.\n");
        exit(64);
    }
    $package = red_addon_validate_manifest($target, $projectRoot);
    $catalog = [
        'valid' => !empty($package['valid']),
        'root' => red_addon_root($projectRoot),
        'packages' => [$target => $package],
        'dependency' => red_addon_dependency_preflight([$target => $package]),
        'errors' => [],
        'warnings' => [],
    ];
    $catalog['valid'] = $catalog['valid'] && !empty($catalog['dependency']['valid']);
}

$summary = [
    'packages' => count($catalog['packages']),
    'validPackages' => count(array_filter(
        $catalog['packages'],
        static function ($package) {
            return !empty($package['valid']);
        }
    )),
    'invalidPackages' => count(array_filter(
        $catalog['packages'],
        static function ($package) {
            return empty($package['valid']);
        }
    )),
    'catalogErrors' => count($catalog['errors'] ?? []),
    'dependencyErrors' => count($catalog['dependency']['errors'] ?? []),
];

if ($jsonOutput) {
    echo json_encode(
        ['catalog' => $catalog, 'summary' => $summary],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} else {
    if ($catalog['packages'] === []) {
        echo "No filesystem-deployed add-on packages were found.\n";
    }
    foreach ($catalog['packages'] as $package) {
        red_addon_cli_print_package($package);
    }
    foreach ($catalog['errors'] ?? [] as $error) {
        echo 'CATALOG ERROR: ' . $error . PHP_EOL;
    }
    foreach ($catalog['dependency']['errors'] ?? [] as $error) {
        echo 'DEPENDENCY ERROR: ' . $error . PHP_EOL;
    }
    foreach ($catalog['dependency']['warnings'] ?? [] as $warning) {
        echo 'DEPENDENCY WARNING: ' . $warning . PHP_EOL;
    }
    echo 'Validated ' . $summary['packages'] . ' add-on package(s): ' .
        $summary['validPackages'] . ' valid, ' . $summary['invalidPackages'] .
        ' invalid; ' . $summary['dependencyErrors'] . ' dependency error(s).' . PHP_EOL;
}

exit(!empty($catalog['valid']) ? 0 : 1);
