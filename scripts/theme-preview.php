<?php
/**
 * Render the audited starter-reference fixture outside the live request path.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/theme_preview_helpers.php';

function red_theme_preview_cli_usage()
{
    fwrite(
        STDERR,
        "Usage:\n" .
        "  theme-preview.php starter-reference\n" .
        "  theme-preview.php starter-reference --json\n" .
        "  theme-preview.php starter-reference --output=/temporary/path/preview.html [--json]\n"
    );
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
            red_theme_preview_cli_usage();
            exit(2);
        }
        $outputPath = substr($argument, strlen('--output='));
        continue;
    }
    if ($themeId !== null) {
        red_theme_preview_cli_usage();
        exit(2);
    }
    $themeId = $argument;
}

if ($themeId === null || $themeId === '') {
    red_theme_preview_cli_usage();
    exit(2);
}

try {
    $result = red_theme_preview_render($themeId, $projectRoot);
    $resolvedOutputPath = null;
    if ($outputPath !== null) {
        $resolvedOutputPath = red_theme_preview_temp_output_path($outputPath);
        if (file_put_contents($resolvedOutputPath, $result['html'], LOCK_EX) === false) {
            throw new RuntimeException('Preview output could not be written.');
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Theme preview failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$report = [
    'mode' => 'fixture-only',
    'theme' => $result['theme'],
    'layout' => $result['layout'],
    'fixture' => 'themes/starter-reference/fixtures/preview.json',
    'bytes' => $result['bytes'],
    'sha256' => $result['sha256'],
    'output' => $resolvedOutputPath,
    'scope' => $result['scope'],
];

if ($jsonOutput) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($resolvedOutputPath !== null) {
    echo 'Rendered ' . $result['theme'] . ' ' . $result['layout'] . ' fixture preview: ' .
        $result['bytes'] . ' bytes, SHA-256 ' . $result['sha256'] . PHP_EOL;
    echo 'Temporary output: ' . $resolvedOutputPath . PHP_EOL;
} else {
    echo $result['html'];
}

exit(0);
