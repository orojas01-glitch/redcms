<?php

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/theme_helpers.php';
require_once $repositoryRoot . '/includes/theme_compatibility_helpers.php';
require_once $repositoryRoot . '/includes/theme_standard_adapter.php';
require_once $repositoryRoot . '/includes/admin_article_helpers.php';
require_once $repositoryRoot . '/includes/admin_tool_helpers.php';

if (!class_exists('content')) {
    class content
    {
        public static $publicCalls = [];
        public static $controlPanelCalls = [];

        public function articles($query, $features, $positionColumn, $position, $layout, $limit)
        {
            self::$publicCalls[] = ['position' => (int) $position, 'layout' => (string) $layout];
            echo '<public-slot data-position="' . (int) $position . '"></public-slot>';
        }

        public function cp_articles($query, $features, $positionColumn, $position, $layout, $limit, $table)
        {
            self::$controlPanelCalls[] = ['position' => (int) $position, 'layout' => (string) $layout];
            echo '<admin-slot data-position="' . (int) $position . '"></admin-slot>';
        }
    }
}

$assertions = 0;
$assert = function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$fixtureRoot = sys_get_temp_dir() . '/redcms-layout-contract-' . bin2hex(random_bytes(8));

$removeFixture = function () use ($fixtureRoot) {
    $tempRoot = rtrim(realpath(sys_get_temp_dir()) ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $resolved = realpath($fixtureRoot);
    if ($resolved === false || strpos($resolved . DIRECTORY_SEPARATOR, $tempRoot . 'redcms-layout-contract-') !== 0) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($resolved);
};
register_shutdown_function($removeFixture);

$writeFile = function ($path, $contents) {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create fixture directory: ' . $directory);
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Could not write fixture file: ' . $path);
    }
};

$buildTheme = function (
    $themeId,
    array $layoutDefinitions,
    array $aliases = [],
    array $layoutPreviews = []
) use ($fixtureRoot, $writeFile) {
    $themeRoot = $fixtureRoot . '/themes/' . $themeId;
    $writeFile($fixtureRoot . '/includes/public_render_helpers.php', "<?php\n");
    $regions = [];
    $productionRegions = [];
    foreach (['document', 'header', 'navigation', 'hero', 'footer'] as $regionId) {
        $path = 'templates/' . $regionId . '.php';
        $writeFile($themeRoot . '/' . $path, "<?php /* fixture region */ ?>\n");
        $regions[$regionId] = [
            'label' => ucfirst($regionId),
            'required' => $regionId !== 'hero',
            'template' => $path,
        ];
        $productionRegions[$regionId] = ['template' => $path];
    }

    $layouts = [];
    $productionLayouts = [];
    foreach ($layoutDefinitions as $layoutId => $positions) {
        $path = 'layouts/' . $layoutId . '.php';
        $writeFile(
            $themeRoot . '/' . $path,
            '<?php foreach (($redThemeLayoutContext["slots"] ?? []) as $slotId => $slotHtml) {' .
            ' echo "<fixture-slot data-position=\"" . (int) $slotId . "\">" . $slotHtml . "</fixture-slot>";' .
            ' } ?>' . "\n"
        );
        $declaredPositions = [];
        foreach ($positions as $positionId => $label) {
            $declaredPositions[] = ['id' => (int) $positionId, 'label' => (string) $label];
        }
        $layouts[$layoutId] = [
            'label' => ucwords(str_replace('-', ' ', $layoutId)),
            'template' => $path,
            'positions' => $declaredPositions,
            'hiddenPosition' => 0,
        ];
        if (isset($layoutPreviews[$layoutId])) {
            $layouts[$layoutId]['adminPreview'] = $layoutPreviews[$layoutId];
        }
        $productionLayouts[$layoutId] = ['template' => $path];
    }

    $components = [];
    $productionComponents = [];
    foreach (['Article', 'Form', 'Gallery', 'Other'] as $componentId) {
        $path = 'components/' . strtolower($componentId) . '.php';
        $writeFile($themeRoot . '/' . $path, "<?php /* fixture component */ ?>\n");
        $components[$componentId] = ['label' => $componentId, 'template' => $path];
        $productionComponents[$componentId] = ['template' => $path];

        $writeFile(
            $fixtureRoot . '/themes/legacy-bootstrap/components/' . strtolower($componentId) . '.php',
            "<?php /* fixture compatibility component */ ?>\n"
        );
    }

    $writeFile($themeRoot . '/preview.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $manifest = [
        'schemaVersion' => 1,
        'id' => $themeId,
        'name' => ucwords(str_replace('-', ' ', $themeId)),
        'version' => '1.0.0',
        'type' => 'standard',
        'description' => 'Synthetic layout extensibility fixture.',
        'preview' => 'preview.svg',
        'compatibility' => [
            'cms' => '>=4.0',
            'php' => '>=8.2',
            'layoutAliases' => $aliases,
        ],
        'assets' => ['styles' => [], 'scripts' => []],
        'production' => [
            'assets' => ['styles' => [], 'scripts' => []],
            'regions' => $productionRegions,
            'layouts' => $productionLayouts,
            'components' => $productionComponents,
        ],
        'regions' => $regions,
        'layouts' => $layouts,
        'components' => $components,
        'settings' => [],
    ];
    $writeFile(
        $themeRoot . '/theme.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );

    return $manifest;
};

try {
    $oneLayout = [
        'landing' => [1 => 'Primary', 7 => 'Proof rail', 12 => 'Conversion footer'],
    ];
    $legacyAliases = [
        'Full-Width' => 'landing',
        'index' => 'landing',
        'index-1' => 'landing',
        'index-2' => 'landing',
        'index-3' => 'landing',
    ];
    $buildTheme('one-layout', $oneLayout, $legacyAliases);
    $oneValidation = red_theme_validate_manifest('one-layout', $fixtureRoot);
    $assert($oneValidation['valid'], 'one-layout theme validates');
    $oneManifest = $oneValidation['manifest'];
    $oneCatalog = red_theme_layout_manifest_catalog($oneManifest);
    $assert(array_keys($oneCatalog) === ['landing'], 'one canonical layout is preserved');
    $assert(array_keys($oneCatalog['landing']['positions']) === [1, 7, 12], 'non-consecutive meaningful positions are preserved');
    $assert(
        array_map(
            static function ($row) {
                return array_column($row, 'position');
            },
            $oneCatalog['landing']['previewRows']
        ) === [[1], [7], [12]],
        'layouts without preview metadata receive a truthful stacked fallback using exact position ids'
    );
    $assert(
        $oneCatalog['landing']['previewIsFallback'] === true,
        'catalog marks automatic stacked rows as order-only fallback geometry'
    );
    $assert(red_theme_layout_resolve_id($oneManifest, 'index-3') === 'landing', 'explicit legacy id resolves to canonical layout');
    $assert(red_theme_layout_resolve_id($oneManifest, 'unknown') === null, 'undeclared layout does not resolve');
    $assert(count(red_theme_layout_accepted_ids($oneManifest)) === 6, 'canonical id plus five aliases are accepted');
    $assert(red_theme_layout_resolve_id($oneManifest, 'Full-Width') === 'landing', 'bounded legacy mixed-case assignment resolves explicitly');
    $assert(
        red_theme_layout_definition($oneManifest, 'Full-Width')['previewRows']
            === $oneCatalog['landing']['previewRows'],
        'compatibility aliases inherit the resolved canonical layout preview'
    );

    $previewRows = [
        'rows' => [
            [
                ['position' => 1, 'weight' => 2],
                ['position' => 7, 'weight' => 1],
            ],
            [
                ['position' => 12, 'weight' => 1],
            ],
        ],
    ];
    $previewManifest = $buildTheme('layout-preview', $oneLayout, [], ['landing' => $previewRows]);
    $previewValidation = red_theme_validate_manifest('layout-preview', $fixtureRoot);
    $assert($previewValidation['valid'], 'valid explicit layout preview geometry passes manifest validation');
    $previewCatalog = red_theme_layout_manifest_catalog($previewManifest);
    $assert(
        $previewCatalog['landing']['previewRows'][0][0]['weight'] === 2
            && array_column($previewCatalog['landing']['previewRows'][0], 'position') === [1, 7],
        'catalog normalizes explicit rows, relative weights, labels, and non-consecutive position ids'
    );
    $assert(
        $previewCatalog['landing']['previewIsFallback'] === false,
        'catalog distinguishes declared desktop geometry from its automatic fallback'
    );

    $buildTheme('preview-duplicate', $oneLayout, [], ['landing' => [
        'rows' => [[
            ['position' => 1, 'weight' => 1],
            ['position' => 1, 'weight' => 1],
        ]],
    ]]);
    $assert(
        !red_theme_validate_manifest('preview-duplicate', $fixtureRoot)['valid'],
        'layout preview validation rejects duplicate and omitted positions'
    );

    $buildTheme('preview-unknown', $oneLayout, [], ['landing' => [
        'rows' => [
            [['position' => 1, 'weight' => 1]],
            [['position' => 7, 'weight' => 1]],
            [['position' => 99, 'weight' => 1]],
        ],
    ]]);
    $assert(
        !red_theme_validate_manifest('preview-unknown', $fixtureRoot)['valid'],
        'layout preview validation rejects undeclared positions'
    );

    $buildTheme('preview-weight', $oneLayout, [], ['landing' => [
        'rows' => [[
            ['position' => 1, 'weight' => 7],
            ['position' => 7, 'weight' => 6],
            ['position' => 12, 'weight' => 1],
        ]],
    ]]);
    $assert(
        !red_theme_validate_manifest('preview-weight', $fixtureRoot)['valid'],
        'layout preview validation rejects row weights beyond the bounded twelve-unit grid'
    );

    $positionCandidate = [
        'Layout' => 'Full-Width',
        'HomePosition' => 1,
        'SectionPosition' => 7,
        'CategoryPosition' => 12,
        'SubCategoryPosition' => 0,
        'PagePosition' => 12,
    ];
    $positionAreaLayouts = [
        'home' => 'index',
        'section' => 'index-1',
        'category' => 'index-2',
        'subcategory' => 'index-3',
    ];
    $assert(
        red_admin_article_position_contract_valid(
            $oneManifest,
            $positionCandidate,
            $positionAreaLayouts,
            array_keys(red_admin_article_position_columns())
        ),
        'article writes accept exact active-theme positions and core hidden position zero'
    );
    $invalidPositionCandidate = $positionCandidate;
    $invalidPositionCandidate['SectionPosition'] = 4;
    $assert(
        !red_admin_article_position_contract_valid(
            $oneManifest,
            $invalidPositionCandidate,
            $positionAreaLayouts,
            ['SectionPosition']
        ),
        'article writes reject an undeclared direct-post position'
    );
    $assert(
        red_admin_article_layout_default_position(['positions' => [5 => 'Primary', 12 => 'Secondary']]) === 5,
        'new content defaults to the first declared slot when a layout has no position one'
    );
    $assert(red_admin_tool_valid_route_alias('legacy_page') === true, 'legacy underscore route aliases remain bounded');
    $assert(red_admin_tool_valid_route_alias('%') === false, 'forged SQL LIKE wildcard route aliases are rejected');

    $oneProduction = red_theme_standard_production_validation(
        $oneManifest,
        $oneValidation['path']
    );
    $assert($oneProduction['valid'], 'one-layout production contract validates');
    $assert(count($oneProduction['files']) === 10, 'one-layout production file inventory is dynamic');
    $oneAdapter = new RedStandardThemeAdapter($fixtureRoot, $oneManifest);
    $assert($oneAdapter->publicLayoutIds() === ['landing'], 'adapter registers one canonical layout');
    $assert($oneAdapter->supportsPublicLayout('landing'), 'adapter supports canonical one-layout id');
    $assert($oneAdapter->supportsPublicLayout('index-2'), 'adapter supports explicit compatibility alias');
    $assert(!$oneAdapter->supportsPublicLayout('index-9'), 'adapter rejects undeclared id');
    content::$publicCalls = [];
    ob_start();
    $oneAdapter->renderPublicLayout('Full-Width', '', '', 'PagePosition', 20);
    $onePublicHtml = ob_get_clean();
    $assert(count(content::$publicCalls) === 3, 'one-layout public renderer dispatches all declared positions');
    $assert(
        array_column(content::$publicCalls, 'position') === [1, 7, 12],
        'public renderer dispatches exact non-consecutive position ids'
    );
    $assert(
        array_unique(array_column(content::$publicCalls, 'layout')) === ['Full-Width'],
        'public component bridge retains the stored compatibility id'
    );
    $assert(substr_count($onePublicHtml, '<fixture-slot ') === 3, 'one-layout template receives all rendered slots');

    $oneLayoutRows = [
        ['source_table' => 'RED_Sections', 'layout_id' => 'index', 'assignments' => 2],
        ['source_table' => 'RED_Categories', 'layout_id' => 'index-1', 'assignments' => 1],
        ['source_table' => 'RED_SubCategories', 'layout_id' => 'index-2', 'assignments' => 1],
        ['source_table' => 'RED_Articles', 'layout_id' => 'index-3', 'assignments' => 3],
        ['source_table' => 'RED_Articles', 'layout_id' => 'Full-Width', 'assignments' => 1],
    ];
    $oneComponentRows = array_map(
        function ($componentId) {
            return ['source_table' => 'RED_Articles', 'component_id' => $componentId, 'assignments' => 1];
        },
        ['Article', 'Form', 'Gallery', 'Other']
    );
    $onePositionRows = [
        ['source_table' => 'RED_Sections.HomePosition', 'layout_id' => 'index', 'position_id' => 1, 'assignments' => 1],
        ['source_table' => 'RED_Categories.CategoryPosition', 'layout_id' => 'index-1', 'position_id' => 7, 'assignments' => 1],
        ['source_table' => 'RED_SubCategories.SubCategoryPosition', 'layout_id' => 'index-2', 'position_id' => 12, 'assignments' => 1],
        ['source_table' => 'RED_Articles.PagePosition', 'layout_id' => 'index-3', 'position_id' => 1, 'assignments' => 1],
        ['source_table' => 'RED_Articles.PagePosition', 'layout_id' => 'Full-Width', 'position_id' => 12, 'assignments' => 1],
    ];
    $inventory = red_theme_compatibility_inventory_from_rows(
        $oneLayoutRows,
        $oneComponentRows,
        [],
        $onePositionRows
    );
    $oneReport = red_theme_compatibility_report_from_validation($oneValidation, $inventory);
    $assert($oneReport['compatible'], 'one-layout aliases cover four stored legacy ids');
    $assert($oneReport['coverage']['missingLayouts'] === [], 'alias-aware preflight reports no missing layouts');
    $assert(count($oneReport['coverage']['layoutAliases']) === 5, 'preflight exposes explicit alias map');
    $assert(
        array_values(array_unique(array_values($oneReport['coverage']['resolvedRequiredLayouts']))) === ['landing'],
        'all stored ids resolve to the declared canonical layout'
    );
    $assert($oneReport['coverage']['missingLayoutPositions'] === [], 'alias-aware preflight covers exact live positions');

    $caseSensitiveLayoutRows = $oneLayoutRows;
    $caseSensitiveLayoutRows[] = [
        'source_table' => 'RED_Articles',
        'layout_id' => 'INDEX',
        'assignments' => 1,
    ];
    $caseSensitiveInventory = red_theme_compatibility_inventory_from_rows(
        $caseSensitiveLayoutRows,
        $oneComponentRows,
        [],
        $onePositionRows
    );
    $caseSensitiveReport = red_theme_compatibility_report_from_validation(
        $oneValidation,
        $caseSensitiveInventory
    );
    $assert(
        $caseSensitiveReport['coverage']['missingLayouts'] === ['INDEX'],
        'preflight keeps differently cased stored layout ids as exact independent requirements'
    );

    $unsafePositionRows = $onePositionRows;
    $unsafePositionRows[] = [
        'source_table' => 'RED_Articles.PagePosition',
        'layout_id' => 'index-2',
        'position_id' => 4,
        'assignments' => 1,
    ];
    $unsafePositionInventory = red_theme_compatibility_inventory_from_rows(
        $oneLayoutRows,
        $oneComponentRows,
        [],
        $unsafePositionRows
    );
    $unsafePositionReport = red_theme_compatibility_report_from_validation(
        $oneValidation,
        $unsafePositionInventory
    );
    $assert(!$unsafePositionReport['compatible'], 'alias coverage cannot hide an undeclared live position');
    $assert(
        $unsafePositionReport['coverage']['missingLayoutPositions'] === [[
            'layoutId' => 'index-2',
            'resolvedLayoutId' => 'landing',
            'positionId' => 4,
        ]],
        'blocked alias reports the exact stored layout, resolved layout, and missing position'
    );

    $buildTheme('case-collision', ['full-width' => [1 => 'Primary']], [
        'Full-Width' => 'full-width',
    ]);
    $caseCollisionValidation = red_theme_validate_manifest('case-collision', $fixtureRoot);
    $assert(!$caseCollisionValidation['valid'], 'alias cannot collide case-insensitively with a canonical id');

    $buildTheme('alias-collision', ['landing' => [1 => 'Primary']], [
        'Legacy-Layout' => 'landing',
        'legacy-layout' => 'landing',
    ]);
    $aliasCollisionValidation = red_theme_validate_manifest('alias-collision', $fixtureRoot);
    $assert(!$aliasCollisionValidation['valid'], 'two aliases cannot collide under database collation');

    $buildTheme('numeric-layout', ['123' => [1 => 'Primary']]);
    $numericLayoutValidation = red_theme_validate_manifest('numeric-layout', $fixtureRoot);
    $assert(!$numericLayoutValidation['valid'], 'purely numeric canonical layout ids fail validation before runtime');

    $extraComponentManifest = $buildTheme('extra-component', ['landing' => [1 => 'Primary']]);
    $writeFile(
        $fixtureRoot . '/themes/extra-component/components/video.php',
        "<?php /* unsupported fixture component */ ?>\n"
    );
    $extraComponentManifest['components']['Video'] = [
        'label' => 'Video',
        'template' => 'components/video.php',
    ];
    $extraComponentManifest['production']['components']['Video'] = [
        'template' => 'components/video.php',
    ];
    $writeFile(
        $fixtureRoot . '/themes/extra-component/theme.json',
        json_encode($extraComponentManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    );
    $extraComponentValidation = red_theme_validate_manifest('extra-component', $fixtureRoot);
    $assert(
        !$extraComponentValidation['valid'],
        'portable manifests cannot claim component ids outside the fixed four-component runtime registry'
    );

    $longLayoutId = 'layout-' . str_repeat('a', 57);
    $twelveLayoutIds = [
        'home',
        'landing-page',
        'article-detail',
        'catalog-grid',
        'catalog-list',
        'service-overview',
        'service-detail',
        'team-directory',
        'case-study',
        'contact-split',
        'campaign-focus',
        $longLayoutId,
    ];
    $twelveLayouts = [];
    foreach ($twelveLayoutIds as $index => $layoutId) {
        $twelveLayouts[$layoutId] = [
            1 => 'Primary',
            5 => 'Secondary ' . ($index + 1),
            12 => 'Footer rail',
            99 => 'Extended slot',
        ];
    }
    $buildTheme('twelve-layouts', $twelveLayouts);
    $twelveValidation = red_theme_validate_manifest('twelve-layouts', $fixtureRoot);
    $assert($twelveValidation['valid'], 'twelve-layout theme validates');
    $twelveManifest = $twelveValidation['manifest'];
    $twelveCatalog = red_theme_layout_manifest_catalog($twelveManifest);
    $assert(count($twelveCatalog) === 12, 'all twelve layouts are discovered');
    $assert(strlen($longLayoutId) === 64 && isset($twelveCatalog[$longLayoutId]), '64-character meaningful layout id is supported');
    $assert($twelveCatalog['contact-split']['positions'][12] === 'Footer rail', 'layout-specific position labels are preserved');
    $assert(isset($twelveCatalog['home']['positions'][99]), 'position ids above the legacy four-slot limit are supported');

    $twelveProduction = red_theme_standard_production_validation(
        $twelveManifest,
        $twelveValidation['path']
    );
    $assert($twelveProduction['valid'], 'twelve-layout production contract validates');
    $assert(count($twelveProduction['files']) === 21, 'twelve-layout production file inventory expands dynamically');
    $twelveAdapter = new RedStandardThemeAdapter($fixtureRoot, $twelveManifest);
    $assert(count($twelveAdapter->publicLayoutIds()) === 12, 'adapter registers all twelve layouts');
    foreach ($twelveLayoutIds as $layoutId) {
        $assert($twelveAdapter->supportsPublicLayout($layoutId), 'adapter supports ' . $layoutId);
    }
    $assert(!$twelveAdapter->supportsPublicLayout('layout-thirteen'), 'adapter has no implicit thirteenth layout');
    content::$controlPanelCalls = [];
    ob_start();
    $twelveAdapter->renderControlPanelLayout(
        'contact-split',
        '',
        '',
        'PagePosition',
        20,
        'Articles'
    );
    $controlPanelHtml = ob_get_clean();
    $assert(
        array_column(content::$controlPanelCalls, 'position') === [1, 5, 12, 99, 0],
        'control panel dispatches exact declared positions plus hidden position zero'
    );
    $assert(
        substr_count($controlPanelHtml, 'data-red-editor-position="') === 5,
        'core-owned control panel exposes all four declared positions plus its hidden tray'
    );
    $assert(
        strpos($controlPanelHtml, 'data-red-editor-position="0"') !== false
            && strpos($controlPanelHtml, 'Hidden content') !== false,
        'control panel preserves the hidden-content tray'
    );

    echo 'Theme layout extensibility self-test passed: ' . $assertions . " assertions.\n";
} finally {
    $removeFixture();
}
