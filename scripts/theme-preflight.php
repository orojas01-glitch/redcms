<?php
/**
 * Read-only portable-theme compatibility preflight.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/theme_compatibility_helpers.php';

function red_theme_preflight_usage()
{
    fwrite(STDERR, "Usage: theme-preflight.php THEME_ID [--json]\n");
}

function red_theme_preflight_config_value(array $localConfig, $localKey, array $environmentKeys, $default = '')
{
    foreach ($environmentKeys as $environmentKey) {
        $value = getenv($environmentKey);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    return array_key_exists($localKey, $localConfig) ? $localConfig[$localKey] : $default;
}

function red_theme_preflight_database_config($projectRoot)
{
    $localConfig = [];
    $localConfigFile = $projectRoot . '/includes/config.local.php';
    if (is_file($localConfigFile)) {
        $loadedConfig = require $localConfigFile;
        if (is_array($loadedConfig)) {
            $localConfig = $loadedConfig;
        }
    }

    $host = (string) red_theme_preflight_config_value(
        $localConfig,
        'DBHOST',
        ['RED_DB_HOST', 'DBHOST'],
        'localhost'
    );
    $port = (int) red_theme_preflight_config_value(
        $localConfig,
        'DBPORT',
        ['RED_DB_PORT', 'DBPORT'],
        3306
    );
    if (substr_count($host, ':') === 1 && preg_match('/\A(.+):(\d+)\z/', $host, $matches) === 1) {
        $host = $matches[1];
        $port = (int) $matches[2];
    }

    return [
        'host' => $host,
        'port' => $port,
        'user' => (string) red_theme_preflight_config_value(
            $localConfig,
            'DBUSER',
            ['RED_DB_USER', 'DBUSER']
        ),
        'password' => (string) red_theme_preflight_config_value(
            $localConfig,
            'DBPASS',
            ['RED_DB_PASS', 'DBPASS']
        ),
        'database' => (string) red_theme_preflight_config_value(
            $localConfig,
            'DBNAME',
            ['RED_DB_NAME', 'DBNAME']
        ),
    ];
}

function red_theme_preflight_open_connection(array $config)
{
    if (!extension_loaded('mysqli')) {
        throw new RuntimeException(
            'The selected PHP CLI does not provide mysqli; use the documented FrankenPHP CLI runtime.'
        );
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = mysqli_init();
    if ($connection === false
        || !@mysqli_real_connect(
            $connection,
            $config['host'],
            $config['user'],
            $config['password'],
            $config['database'],
            $config['port']
        )
    ) {
        throw new RuntimeException('Could not connect to the configured RED-CMS database.');
    }
    if (!mysqli_set_charset($connection, 'utf8mb4')) {
        mysqli_close($connection);
        throw new RuntimeException('Could not initialize the RED-CMS database charset.');
    }

    return $connection;
}

function red_theme_preflight_list(array $values)
{
    return $values === [] ? 'none' : implode(', ', $values);
}

function red_theme_preflight_position_list(array $missingPositions)
{
    $labels = [];
    foreach ($missingPositions as $missingPosition) {
        if (!is_array($missingPosition)) {
            continue;
        }
        $layoutId = (string) ($missingPosition['layoutId'] ?? '');
        $resolvedLayoutId = (string) ($missingPosition['resolvedLayoutId'] ?? '');
        $positionId = (int) ($missingPosition['positionId'] ?? 0);
        if ($layoutId === '' || $resolvedLayoutId === '' || $positionId < 1) {
            continue;
        }
        $labels[] = $layoutId . ':' . $positionId .
            ($layoutId === $resolvedLayoutId ? '' : '->' . $resolvedLayoutId);
    }

    return red_theme_preflight_list($labels);
}

function red_theme_preflight_print_report(array $report)
{
    $status = !empty($report['compatible']) ? 'PASS' : 'FAIL';
    $theme = $report['theme'];
    $label = $theme['name'] !== '' ? $theme['name'] : $theme['id'];
    if ($theme['version'] !== '') {
        $label .= ' ' . $theme['version'];
    }
    echo $status . ' ' . $theme['id'] . ' — ' . $label . PHP_EOL;
    echo '  Mode: read-only (no selection, setting, preview-session, or database writes)' . PHP_EOL;
    echo '  Theme type: ' . ($theme['type'] !== '' ? $theme['type'] : 'unknown') . PHP_EOL;
    echo '  Required layouts: ' . red_theme_preflight_list($report['requirements']['layouts']) . PHP_EOL;
    echo '  Required components: ' . red_theme_preflight_list($report['requirements']['components']) . PHP_EOL;
    echo '  Missing layouts: ' . red_theme_preflight_list($report['coverage']['missingLayouts']) . PHP_EOL;
    echo '  Missing layout positions: ' . red_theme_preflight_position_list(
        $report['coverage']['missingLayoutPositions'] ?? []
    ) . PHP_EOL;
    echo '  Missing components: ' . red_theme_preflight_list($report['coverage']['missingComponents']) . PHP_EOL;
    foreach ($report['validation']['errors'] as $error) {
        echo '  ERROR: ' . $error . PHP_EOL;
    }
    foreach ($report['validation']['warnings'] as $warning) {
        echo '  WARNING: ' . $warning . PHP_EOL;
    }
    foreach ($report['blockingReasons'] as $reason) {
        echo '  BLOCKED: ' . $reason . PHP_EOL;
    }
}

$arguments = array_slice($argv, 1);
$jsonOutput = false;
$themeId = null;
foreach ($arguments as $argument) {
    if ($argument === '--json') {
        $jsonOutput = true;
        continue;
    }
    if ($themeId !== null) {
        red_theme_preflight_usage();
        exit(2);
    }
    $themeId = $argument;
}

if ($themeId === null || $themeId === '') {
    red_theme_preflight_usage();
    exit(2);
}

$connection = null;
try {
    $connection = red_theme_preflight_open_connection(
        red_theme_preflight_database_config($projectRoot)
    );
    $report = red_theme_compatibility_live_preflight($themeId, $connection, $projectRoot);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Theme preflight failed: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
} finally {
    if ($connection instanceof mysqli) {
        mysqli_close($connection);
    }
}

if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    red_theme_preflight_print_report($report);
}

exit(!empty($report['compatible']) ? 0 : 1);
