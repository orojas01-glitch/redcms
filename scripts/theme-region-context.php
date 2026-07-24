<?php
/**
 * Print the fixed generic document/navigation/hero/settings context report.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/theme_region_context_helpers.php';

function red_theme_region_context_cli_usage()
{
    fwrite(STDERR, "Usage: theme-region-context.php [--json]\n");
}

function red_theme_region_context_cli_config_value(
    array $localConfig,
    $localKey,
    array $environmentKeys,
    $default = ''
) {
    foreach ($environmentKeys as $environmentKey) {
        $value = getenv($environmentKey);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    return array_key_exists($localKey, $localConfig) ? $localConfig[$localKey] : $default;
}

function red_theme_region_context_cli_database_config($projectRoot)
{
    $localConfig = [];
    $localConfigFile = rtrim((string) $projectRoot, '/') . '/includes/config.local.php';
    if (is_file($localConfigFile)) {
        $loaded = require $localConfigFile;
        if (is_array($loaded)) {
            $localConfig = $loaded;
        }
    }
    $host = (string) red_theme_region_context_cli_config_value(
        $localConfig,
        'DBHOST',
        ['RED_DB_HOST', 'DBHOST'],
        'localhost'
    );
    $port = (int) red_theme_region_context_cli_config_value(
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
        'user' => (string) red_theme_region_context_cli_config_value(
            $localConfig,
            'DBUSER',
            ['RED_DB_USER', 'DBUSER']
        ),
        'password' => (string) red_theme_region_context_cli_config_value(
            $localConfig,
            'DBPASS',
            ['RED_DB_PASS', 'DBPASS']
        ),
        'database' => (string) red_theme_region_context_cli_config_value(
            $localConfig,
            'DBNAME',
            ['RED_DB_NAME', 'DBNAME']
        ),
    ];
}

function red_theme_region_context_cli_open_connection(array $config)
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

$json = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--json' && !$json) {
        $json = true;
        continue;
    }
    red_theme_region_context_cli_usage();
    exit(2);
}

$connection = null;
try {
    $connection = red_theme_region_context_cli_open_connection(
        red_theme_region_context_cli_database_config($projectRoot)
    );
    $report = red_theme_region_context_live_report($connection);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Generic region context failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($connection instanceof mysqli) {
        mysqli_close($connection);
    }
}

if ($json) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo 'READ ONLY generic region context' . PHP_EOL;
    echo '  Routes covered: ' . $report['contract']['routeCount'] . PHP_EOL;
    echo '  Settings: ' . $report['source']['rowCounts']['region-settings'] . PHP_EOL;
    echo '  Navigation rows: ' . $report['source']['rowCounts']['navigation'] . PHP_EOL;
    echo '  Hero areas/candidates: ' . $report['source']['rowCounts']['hero-areas'] . '/' .
        $report['source']['rowCounts']['hero-articles'] . PHP_EOL;
    echo '  Fixed database reads: ' . $report['scope']['databaseReads'] . '; writes: 0' . PHP_EOL;
    echo "  Theme rendering/production connection: no/no\n";
}

exit(0);
