<?php
/**
 * Render the fixed Administration landing through the isolated audited starter.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/theme_preview_administration_helpers.php';

function red_theme_administration_preview_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  theme-preview-administration.php starter-reference\n" .
        "  theme-preview-administration.php starter-reference --json\n" .
        "  theme-preview-administration.php starter-reference --output=/temporary/path/preview.html [--json]\n"
    );
}

function red_theme_administration_preview_cli_config_value(
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

function red_theme_administration_preview_cli_database_config($projectRoot)
{
    $localConfig = [];
    $localConfigFile = $projectRoot . '/includes/config.local.php';
    if (is_file($localConfigFile)) {
        $loadedConfig = require $localConfigFile;
        if (is_array($loadedConfig)) {
            $localConfig = $loadedConfig;
        }
    }
    $host = (string) red_theme_administration_preview_cli_config_value(
        $localConfig,
        'DBHOST',
        ['RED_DB_HOST', 'DBHOST'],
        'localhost'
    );
    $port = (int) red_theme_administration_preview_cli_config_value(
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
        'user' => (string) red_theme_administration_preview_cli_config_value(
            $localConfig,
            'DBUSER',
            ['RED_DB_USER', 'DBUSER']
        ),
        'password' => (string) red_theme_administration_preview_cli_config_value(
            $localConfig,
            'DBPASS',
            ['RED_DB_PASS', 'DBPASS']
        ),
        'database' => (string) red_theme_administration_preview_cli_config_value(
            $localConfig,
            'DBNAME',
            ['RED_DB_NAME', 'DBNAME']
        ),
    ];
}

function red_theme_administration_preview_cli_open_connection(array $config)
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

$themeId = null;
$outputPath = null;
$jsonOutput = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--json') {
        $jsonOutput = true;
        continue;
    }
    if (strpos($argument, '--output=') === 0) {
        if ($outputPath !== null) {
            red_theme_administration_preview_cli_usage();
            exit(2);
        }
        $outputPath = substr($argument, strlen('--output='));
        continue;
    }
    if ($themeId !== null) {
        red_theme_administration_preview_cli_usage();
        exit(2);
    }
    $themeId = $argument;
}
if ($themeId === null || $themeId === '') {
    red_theme_administration_preview_cli_usage();
    exit(2);
}
if ($themeId !== 'starter-reference') {
    fwrite(STDERR, "Administration preview currently supports only starter-reference.\n");
    exit(2);
}

$connection = null;
try {
    $connection = red_theme_administration_preview_cli_open_connection(
        red_theme_administration_preview_cli_database_config($projectRoot)
    );
    $result = red_theme_administration_preview_render($connection, $projectRoot);
    $resolvedOutputPath = null;
    if ($outputPath !== null) {
        $resolvedOutputPath = red_theme_preview_temp_output_path($outputPath);
        if (file_put_contents($resolvedOutputPath, $result['html'], LOCK_EX) === false) {
            throw new RuntimeException('Administration preview output could not be written.');
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Administration theme preview failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($connection instanceof mysqli) {
        mysqli_close($connection);
    }
}

$report = [
    'mode' => 'read-only-administration',
    'theme' => $result['theme'],
    'layout' => $result['layout'],
    'bytes' => $result['bytes'],
    'sha256' => $result['sha256'],
    'output' => $resolvedOutputPath,
    'source' => $result['source'],
    'scope' => $result['scope'],
];
if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($resolvedOutputPath !== null) {
    echo 'Rendered real Administration data through ' . $result['theme'] . ' ' . $result['layout'] . ': ' .
        $result['bytes'] . ' bytes, SHA-256 ' . $result['sha256'] . PHP_EOL;
    echo 'Temporary output: ' . $resolvedOutputPath . PHP_EOL;
    echo "Scope: four database reads; zero writes, sessions, activations, or live-runtime changes.\n";
} else {
    echo $result['html'];
}

exit(0);
