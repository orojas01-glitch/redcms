<?php
/**
 * Validate one or every installed RED-CMS theme manifest.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/theme_helpers.php';

function red_theme_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  theme-validate.php --all [--json]\n" .
        "  theme-validate.php THEME_ID [--json]\n" .
        "  theme-validate.php --resolve THEME_ID [--json]\n"
    );
}

function red_theme_cli_label(array $result)
{
    $manifest = isset($result['manifest']) && is_array($result['manifest']) ? $result['manifest'] : [];
    $name = isset($manifest['name']) && is_string($manifest['name']) ? $manifest['name'] : $result['theme'];
    $version = isset($manifest['version']) && is_string($manifest['version']) ? $manifest['version'] : '';

    return $version !== '' ? $name . ' ' . $version : $name;
}

function red_theme_cli_print_result(array $result)
{
    $status = !empty($result['valid']) ? 'PASS' : 'FAIL';
    $suffix = !empty($result['usedFallback']) ? ' (fallback)' : '';
    echo $status . ' ' . $result['theme'] . ' — ' . red_theme_cli_label($result) . $suffix . PHP_EOL;

    foreach ($result['errors'] ?? [] as $error) {
        echo '  ERROR: ' . $error . PHP_EOL;
    }
    foreach ($result['warnings'] ?? [] as $warning) {
        echo '  WARNING: ' . $warning . PHP_EOL;
    }
    foreach ($result['requestedErrors'] ?? [] as $error) {
        echo '  REQUESTED THEME ERROR: ' . $error . PHP_EOL;
    }
}

function red_theme_cli_executable_files(array $result, $projectRoot)
{
    if (empty($result['valid']) || empty($result['manifest']) || !is_array($result['manifest'])) {
        return [];
    }

    $manifest = $result['manifest'];
    $themeType = $manifest['type'] ?? '';
    $themeDirectory = $result['path'];
    $files = [];

    if ($themeType === 'legacy-adapter' && !empty($manifest['adapter'])) {
        $adapter = red_theme_existing_path($themeDirectory, $manifest['adapter']);
        if ($adapter !== null && strtolower(pathinfo($adapter, PATHINFO_EXTENSION)) === 'php') {
            $files[$adapter] = $adapter;
        }
    }

    $groups = ['regions', 'layouts', 'components', 'features'];
    $pathField = $themeType === 'legacy-adapter' ? 'legacySource' : 'template';
    $baseDirectory = $themeType === 'legacy-adapter' ? $projectRoot : $themeDirectory;
    foreach ($groups as $group) {
        if (empty($manifest[$group]) || !is_array($manifest[$group])) {
            continue;
        }
        foreach ($manifest[$group] as $definition) {
            if (!is_array($definition) || empty($definition[$pathField])) {
                continue;
            }
            $file = red_theme_existing_path($baseDirectory, $definition[$pathField]);
            if ($file !== null && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
                $files[$file] = $file;
            }
        }
    }

    ksort($files, SORT_STRING);
    return array_values($files);
}

function red_theme_cli_check_php_syntax(array &$result, $projectRoot)
{
    foreach (red_theme_cli_executable_files($result, $projectRoot) as $file) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-l', $file],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            red_theme_add_error($result, 'PHP syntax check could not start for ' . $file . '.');
            $result['valid'] = false;
            continue;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $detail = trim($stderr !== '' ? $stderr : $stdout);
            red_theme_add_error(
                $result,
                'PHP syntax check failed for ' . $file . ($detail !== '' ? ': ' . $detail : '.')
            );
            $result['valid'] = false;
        }
    }
}

$arguments = array_slice($argv, 1);
$jsonOutput = false;
$filteredArguments = [];
foreach ($arguments as $argument) {
    if ($argument === '--json') {
        $jsonOutput = true;
        continue;
    }
    $filteredArguments[] = $argument;
}

$mode = $filteredArguments[0] ?? '--all';
$results = [];

if ($mode === '--all') {
    if (count($filteredArguments) !== 1) {
        red_theme_cli_usage();
        exit(2);
    }
    $results = red_theme_discover($projectRoot);
    if ($results === []) {
        fwrite(STDERR, "No installed theme directories were found.\n");
        exit(1);
    }
} elseif ($mode === '--resolve') {
    if (count($filteredArguments) !== 2) {
        red_theme_cli_usage();
        exit(2);
    }
    $result = red_theme_resolve($filteredArguments[1], $projectRoot);
    $results[$result['theme']] = $result;
} else {
    if (count($filteredArguments) !== 1) {
        red_theme_cli_usage();
        exit(2);
    }
    $results[$mode] = red_theme_validate_manifest($mode, $projectRoot);
}

foreach ($results as &$result) {
    red_theme_cli_check_php_syntax($result, $projectRoot);
}
unset($result);

$validCount = 0;
foreach ($results as $result) {
    if (!empty($result['valid'])) {
        $validCount++;
    }
}
$summary = [
    'total' => count($results),
    'valid' => $validCount,
    'invalid' => count($results) - $validCount,
];

if ($jsonOutput) {
    echo json_encode(
        ['themes' => array_values($results), 'summary' => $summary],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} else {
    foreach ($results as $result) {
        red_theme_cli_print_result($result);
    }
    echo 'Validated ' . $summary['total'] . ' theme(s): ' . $summary['valid'] . ' valid, ' .
        $summary['invalid'] . ' invalid.' . PHP_EOL;
}

exit($summary['invalid'] === 0 ? 0 : 1);
