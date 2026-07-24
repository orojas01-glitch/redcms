<?php
/**
 * Dependency-free compatibility-report tests for portable themes.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_compatibility_helpers.php';
require_once $repositoryRoot . '/includes/theme_runtime.php';

$assertions = 0;

function red_theme_preflight_test_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_preflight_test_remove_tree($path)
{
    if (is_link($path) || is_file($path)) {
        unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entryPath = $entry->getPathname();
        if ($entry->isLink() || $entry->isFile()) {
            unlink($entryPath);
        } else {
            rmdir($entryPath);
        }
    }
    rmdir($path);
}

function red_theme_preflight_test_write_manifest($themeDirectory, array $manifest)
{
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($themeDirectory . '/theme.json', $json . PHP_EOL) === false) {
        throw new RuntimeException('Could not write a preflight fixture manifest.');
    }
}

function red_theme_preflight_test_manifest($themeId)
{
    $view = ['label' => 'Fixture view', 'template' => 'view.php'];
    $layout = static function ($label, array $positionIds) {
        return [
            'label' => $label,
            'template' => 'view.php',
            'positions' => array_map(
                static function ($positionId) {
                    return ['id' => $positionId, 'label' => 'Position ' . $positionId];
                },
                $positionIds
            ),
            'hiddenPosition' => 0,
        ];
    };

    return [
        'schemaVersion' => 1,
        'id' => $themeId,
        'name' => 'Preflight Fixture',
        'version' => '1.0.0',
        'type' => 'standard',
        'description' => 'Temporary portable-theme compatibility fixture.',
        'preview' => 'preview.svg',
        'compatibility' => ['cms' => '>=4.0', 'php' => '>=8.2'],
        'assets' => [
            'styles' => [
                ['id' => 'fixture', 'path' => 'assets/theme.css', 'location' => 'head'],
            ],
            'scripts' => [],
        ],
        'regions' => [
            'document' => $view,
            'header' => $view,
            'navigation' => $view,
            'hero' => $view,
            'footer' => $view,
        ],
        'layouts' => [
            'index' => $layout('Index', [1, 2, 3]),
            'index-1' => $layout('Index one', [1, 2, 3, 4]),
            'index-2' => $layout('Index two', [1, 2, 3, 4]),
            'index-3' => $layout('Index three', [1, 2]),
        ],
        'components' => [
            'Article' => $view,
            'Form' => $view,
            'Gallery' => $view,
            'Other' => $view,
        ],
        'settings' => [],
    ];
}

$token = bin2hex(random_bytes(8));
$fixtureRoot = sys_get_temp_dir() . '/redcms-theme-preflight-' . $token;
$outsideRoot = sys_get_temp_dir() . '/redcms-theme-preflight-outside-' . $token;
$themeId = 'complete-theme';
$themeDirectory = $fixtureRoot . '/themes/' . $themeId;

try {
    if (!mkdir($themeDirectory . '/assets', 0700, true) || !mkdir($outsideRoot, 0700, true)) {
        throw new RuntimeException('Could not create temporary preflight fixture directories.');
    }
    file_put_contents($themeDirectory . '/view.php', '<?php echo "fixture";' . PHP_EOL);
    file_put_contents($themeDirectory . '/preview.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    file_put_contents($themeDirectory . '/assets/theme.css', 'body { color: #111; }' . PHP_EOL);
    file_put_contents($outsideRoot . '/outside.php', '<?php echo "outside";' . PHP_EOL);

    $layoutRows = [
        ['source_table' => 'RED_Sections', 'layout_id' => 'index', 'assignments' => 1],
        ['source_table' => 'RED_Sections', 'layout_id' => 'index-1', 'assignments' => 1],
        ['source_table' => 'RED_Articles', 'layout_id' => 'index-1', 'assignments' => 1],
        ['source_table' => 'RED_Articles', 'layout_id' => 'index-2', 'assignments' => 2],
        ['source_table' => 'RED_Categories', 'layout_id' => 'index-3', 'assignments' => 1],
        ['source_table' => 'RED_SubCategories', 'layout_id' => '', 'assignments' => 1],
    ];
    $componentRows = [
        ['source_table' => 'RED_Articles', 'component_id' => 'Article', 'assignments' => 1],
        ['source_table' => 'RED_Articles', 'component_id' => 'Form', 'assignments' => 2],
        ['source_table' => 'RED_Articles', 'component_id' => 'Gallery', 'assignments' => 2],
    ];
    $catalogRows = [
        ['layout_id' => 'index', 'positions' => 3],
        ['layout_id' => 'index-1', 'positions' => 3],
        ['layout_id' => 'index-2', 'positions' => 4],
        ['layout_id' => 'index-3', 'positions' => 2],
    ];
    $positionRows = [
        ['source_table' => 'RED_Sections.HomePosition', 'layout_id' => 'index', 'position_id' => 3, 'assignments' => 1],
        ['source_table' => 'RED_Sections.SectionPosition', 'layout_id' => 'index-1', 'position_id' => 4, 'assignments' => 1],
        ['source_table' => 'RED_Articles.PagePosition', 'layout_id' => 'index-2', 'position_id' => 2, 'assignments' => 2],
        ['source_table' => 'RED_Categories.CategoryPosition', 'layout_id' => 'index-3', 'position_id' => 2, 'assignments' => 1],
    ];
    $inventory = red_theme_compatibility_inventory_from_rows(
        $layoutRows,
        $componentRows,
        $catalogRows,
        $positionRows
    );

    red_theme_preflight_test_assert(
        $inventory['layouts']['assigned'] === [
            'index' => 1,
            'index-1' => 2,
            'index-2' => 2,
            'index-3' => 1,
        ],
        'layout assignments aggregate across all current content sources'
    );
    red_theme_preflight_test_assert(
        $inventory['components']['assigned'] === [
            'Article' => 1,
            'Form' => 2,
            'Gallery' => 2,
        ],
        'component assignments preserve exact case-sensitive ids and counts'
    );
    red_theme_preflight_test_assert(
        $inventory['layouts']['catalog']['index-1']['positions'] === 3,
        'legacy layout catalog stays visible without becoming a compatibility write'
    );
    red_theme_preflight_test_assert(
        $inventory['layouts']['requiredPositions']['index-2'] === [2 => 2],
        'live numbered positions are inventoried separately from the legacy position count'
    );

    $starterValidation = red_theme_validate_manifest('starter-reference', $repositoryRoot);
    red_theme_preflight_test_assert($starterValidation['valid'], 'installed starter reference validates');
    $starterReport = red_theme_compatibility_report_from_validation($starterValidation, $inventory);
    red_theme_preflight_test_assert($starterReport['compatible'], 'installed starter covers the complete fixture');
    red_theme_preflight_test_assert(
        $starterReport['coverage']['missingLayouts'] === []
            && $starterReport['coverage']['missingComponents'] === [],
        'complete starter report has no missing assigned ids'
    );
    red_theme_preflight_test_assert(
        array_sum($starterReport['changes']) === 0 && $starterReport['mode'] === 'read-only',
        'preflight report explicitly records zero write operations'
    );

    $runtime = red_theme_runtime_bootstrap('starter-reference', $repositoryRoot);
    red_theme_preflight_test_assert(
        $runtime['themeId'] === 'legacy-bootstrap' && !empty($runtime['resolution']['usedFallback']),
        'current runtime refuses the standard starter and keeps legacy-bootstrap active'
    );

    $manifest = red_theme_preflight_test_manifest($themeId);
    red_theme_preflight_test_write_manifest($themeDirectory, $manifest);
    $completeReport = red_theme_compatibility_report($themeId, $inventory, $fixtureRoot);
    red_theme_preflight_test_assert($completeReport['compatible'], 'complete portable fixture passes preflight');
    red_theme_preflight_test_assert(
        $completeReport['checks'] === [
            'manifestValid' => true,
            'standardTheme' => true,
            'assignedLayoutsCovered' => true,
            'assignedLayoutPositionsCovered' => true,
            'assignedComponentsCovered' => true,
        ],
        'complete report passes every independent compatibility check'
    );

    $incomplete = $manifest;
    unset($incomplete['layouts']['index-3']);
    red_theme_preflight_test_write_manifest($themeDirectory, $incomplete);
    $incompleteReport = red_theme_compatibility_report($themeId, $inventory, $fixtureRoot);
    red_theme_preflight_test_assert(
        $incompleteReport['validation']['valid'],
        'fixture missing a live layout remains structurally valid for a readable coverage report'
    );
    red_theme_preflight_test_assert(!$incompleteReport['compatible'], 'incomplete fixture is blocked');
    red_theme_preflight_test_assert(
        $incompleteReport['coverage']['missingLayouts'] === ['index-3'],
        'incomplete report names the exact missing assigned layout'
    );

    $missingPosition = $manifest;
    $missingPosition['layouts']['index-2']['positions'] = array_values(array_filter(
        $missingPosition['layouts']['index-2']['positions'],
        static function (array $position) {
            return ($position['id'] ?? 0) !== 2;
        }
    ));
    red_theme_preflight_test_write_manifest($themeDirectory, $missingPosition);
    $missingPositionReport = red_theme_compatibility_report($themeId, $inventory, $fixtureRoot);
    red_theme_preflight_test_assert(
        $missingPositionReport['validation']['valid'] && !$missingPositionReport['compatible'],
        'structurally valid theme missing a live numbered position is blocked'
    );
    red_theme_preflight_test_assert(
        $missingPositionReport['coverage']['missingLayoutPositions'] === [[
            'layoutId' => 'index-2',
            'resolvedLayoutId' => 'index-2',
            'positionId' => 2,
        ]],
        'position coverage names the exact assigned layout, resolved layout, and missing position'
    );

    $missingComponent = $manifest;
    unset($missingComponent['components']['Gallery']);
    red_theme_preflight_test_write_manifest($themeDirectory, $missingComponent);
    $missingComponentReport = red_theme_compatibility_report($themeId, $inventory, $fixtureRoot);
    red_theme_preflight_test_assert(
        !$missingComponentReport['validation']['valid']
            && $missingComponentReport['coverage']['missingComponents'] === ['Gallery'],
        'missing required component is reported by validation and live coverage'
    );

    $unsafe = $manifest;
    $unsafe['regions']['header']['template'] = '../outside.php';
    red_theme_preflight_test_write_manifest($themeDirectory, $unsafe);
    $unsafeReport = red_theme_compatibility_report($themeId, $inventory, $fixtureRoot);
    red_theme_preflight_test_assert(
        !$unsafeReport['validation']['valid'] && !$unsafeReport['compatible'],
        'parent traversal is rejected before compatibility can pass'
    );

    $linkedView = $themeDirectory . '/linked-view.php';
    if (function_exists('symlink') && @symlink($outsideRoot . '/outside.php', $linkedView)) {
        $unsafe = $manifest;
        $unsafe['regions']['header']['template'] = 'linked-view.php';
        red_theme_preflight_test_write_manifest($themeDirectory, $unsafe);
        $linkedReport = red_theme_compatibility_report($themeId, $inventory, $fixtureRoot);
        red_theme_preflight_test_assert(
            !$linkedReport['validation']['valid'] && !$linkedReport['compatible'],
            'symbolic-link file escape is rejected before compatibility can pass'
        );
    }

    $legacyReport = red_theme_compatibility_report('legacy-bootstrap', $inventory, $repositoryRoot);
    red_theme_preflight_test_assert(
        !$legacyReport['compatible'] && !$legacyReport['checks']['standardTheme'],
        'legacy adapter inventory coverage never masquerades as a portable standard theme'
    );

    echo 'Theme compatibility preflight self-test passed: ' . $assertions . ' assertions.' . PHP_EOL;
} finally {
    red_theme_preflight_test_remove_tree($fixtureRoot);
    red_theme_preflight_test_remove_tree($outsideRoot);
}
