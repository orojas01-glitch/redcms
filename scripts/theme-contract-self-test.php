<?php
/**
 * Dependency-free contract checks for RED-CMS theme validation and fallback.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/theme_helpers.php';
require_once dirname(__DIR__) . '/includes/theme_runtime.php';
require_once dirname(__DIR__) . '/includes/public_theme_helpers.php';
require_once dirname(__DIR__) . '/includes/legacy_layout_helpers.php';
require_once dirname(__DIR__) . '/includes/legacy_component_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_ftp_ui_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_gallery_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_gallery_ui_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_other_ui_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_user_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_area_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_tool_helpers.php';

$assertionCount = 0;

function red_theme_test_assert($condition, $message)
{
    global $assertionCount;
    $assertionCount++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function red_theme_test_write_manifest($themeDirectory, array $manifest)
{
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($themeDirectory . '/theme.json', $json . PHP_EOL) === false) {
        throw new RuntimeException('Could not write the temporary theme manifest.');
    }
}

function red_theme_test_remove_tree($path)
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

function red_theme_test_render_legacy_layout($layoutId)
{
    $calls = [];
    $redThemeRenderBreadcrumb = function () use (&$calls) {
        $calls[] = 'breadcrumb';
        echo '<breadcrumb />';
    };
    $redThemeRenderSlot = function ($position) use (&$calls) {
        $position = (string) $position;
        $calls[] = 'slot:' . $position;
        echo '<slot-' . $position . ' />';
    };

    ob_start();
    try {
        require dirname(__DIR__) . '/themes/legacy-bootstrap/layouts/' . $layoutId . '.php';
        $html = ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }

    return [$html, $calls];
}

function red_theme_test_render_legacy_other(array $context)
{
    $redThemeOtherContext = $context;

    ob_start();
    try {
        require dirname(__DIR__) . '/themes/legacy-bootstrap/components/other.php';
        return ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
}

function red_theme_test_render_legacy_gallery(array $context)
{
    $redThemeGalleryContext = $context;

    ob_start();
    try {
        require dirname(__DIR__) . '/themes/legacy-bootstrap/components/gallery.php';
        return ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
}

function red_theme_test_render_legacy_article(array $context)
{
    $redThemeArticleContext = $context;

    ob_start();
    try {
        require dirname(__DIR__) . '/themes/legacy-bootstrap/components/article.php';
        return ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
}

function red_theme_test_render_legacy_form(array $context)
{
    if (!defined('language')) {
        define('language', 'sp');
    }

    $sessionWasSet = isset($_SESSION);
    $previousSession = $sessionWasSet ? $_SESSION : null;
    $requestUriWasSet = array_key_exists('REQUEST_URI', $_SERVER);
    $previousRequestUri = $requestUriWasSet ? $_SERVER['REQUEST_URI'] : null;
    $_SESSION = [];
    $_SERVER['REQUEST_URI'] = '/administracion/';
    $redThemeFormContext = $context;

    ob_start();
    try {
        require dirname(__DIR__) . '/themes/legacy-bootstrap/components/form.php';
        return ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    } finally {
        if ($sessionWasSet) {
            $_SESSION = $previousSession;
        } else {
            unset($_SESSION);
        }
        if ($requestUriWasSet) {
            $_SERVER['REQUEST_URI'] = $previousRequestUri;
        } else {
            unset($_SERVER['REQUEST_URI']);
        }
    }
}

function red_theme_test_render_standard_document_start($relativeTemplate, array $context)
{
    $redThemeDocumentContext = $context;

    ob_start();
    try {
        require dirname(__DIR__) . '/' . ltrim((string) $relativeTemplate, '/');
        return ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
}

$token = bin2hex(random_bytes(8));
$projectRoot = sys_get_temp_dir() . '/redcms-theme-contract-' . $token;
$outsideRoot = sys_get_temp_dir() . '/redcms-theme-outside-' . $token;
$themeDirectory = $projectRoot . '/themes/example-theme';

try {
    if (!mkdir($themeDirectory . '/assets', 0700, true) || !mkdir($outsideRoot, 0700, true)) {
        throw new RuntimeException('Could not create temporary theme-test directories.');
    }
    file_put_contents($themeDirectory . '/preview.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    file_put_contents($themeDirectory . '/view.php', '<?php echo "theme-test";' . PHP_EOL);
    file_put_contents($themeDirectory . '/assets/theme.css', 'body { color: #111; }' . PHP_EOL);

    $view = [
        'label' => 'Test view',
        'template' => 'view.php',
    ];
    $manifest = [
        'schemaVersion' => 1,
        'id' => 'example-theme',
        'name' => 'Example Theme',
        'version' => '1.0.0',
        'type' => 'standard',
        'description' => 'Temporary contract fixture.',
        'preview' => 'preview.svg',
        'compatibility' => [
            'cms' => '>=4.0',
            'php' => '>=8.2',
        ],
        'assets' => [
            'styles' => [
                [
                    'id' => 'theme-style',
                    'path' => 'assets/theme.css',
                    'location' => 'head',
                ],
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
            'full-width' => [
                'label' => 'Full width',
                'template' => 'view.php',
                'positions' => [
                    ['id' => 1, 'label' => 'Main content'],
                ],
                'hiddenPosition' => 0,
            ],
        ],
        'components' => [
            'Article' => $view,
            'Form' => $view,
            'Gallery' => $view,
            'Other' => $view,
        ],
        'settings' => [
            [
                'key' => 'branding.accent',
                'label' => 'Accent color',
                'type' => 'color',
            ],
        ],
    ];

    red_theme_test_assert(
        red_theme_standard_breadcrumbs_enabled($manifest),
        'standard themes retain breadcrumbs when the reserved setting is absent'
    );
    $breadcrumbsDisabledManifest = $manifest;
    $breadcrumbsDisabledManifest['settings'][] = [
        'key' => 'navigation.breadcrumbs',
        'label' => 'Show breadcrumbs',
        'type' => 'checkbox',
        'default' => false,
    ];
    red_theme_test_assert(
        !red_theme_standard_breadcrumbs_enabled($breadcrumbsDisabledManifest),
        'standard themes may disable breadcrumbs once for the whole package'
    );
    $breadcrumbsEnabledManifest = $breadcrumbsDisabledManifest;
    $breadcrumbsEnabledManifest['settings'][1]['default'] = true;
    red_theme_test_assert(
        red_theme_standard_breadcrumbs_enabled($breadcrumbsEnabledManifest),
        'standard themes may explicitly retain breadcrumbs for the whole package'
    );

    red_theme_test_write_manifest($themeDirectory, $manifest);

    red_theme_test_assert(red_theme_valid_relative_path('partials/header.php'), 'safe relative path accepted');
    red_theme_test_assert(!red_theme_valid_relative_path('../index.php'), 'parent traversal rejected');
    red_theme_test_assert(!red_theme_valid_relative_path('/etc/passwd'), 'absolute path rejected');
    red_theme_test_assert(!red_theme_valid_relative_path('assets//theme.css'), 'empty path segment rejected');
    red_theme_test_assert(
        red_theme_valid_php_class_name('RedExampleThemeAdapter'),
        'safe adapter class name accepted'
    );
    red_theme_test_assert(
        !red_theme_valid_php_class_name('RedExampleThemeAdapter::render'),
        'executable adapter expression rejected'
    );
    $unsupportedRegionRejected = false;
    try {
        red_public_legacy_region_context('Website_Title');
    } catch (InvalidArgumentException $exception) {
        $unsupportedRegionRejected = true;
    }
    red_theme_test_assert(
        $unsupportedRegionRejected,
        'legacy theme data helper rejects an undeclared database item'
    );

    $layoutSlotInventory = red_legacy_layout_slot_inventory();
    red_theme_test_assert(
        array_keys($layoutSlotInventory) === ['index', 'index-1', 'index-2', 'index-3'],
        'legacy layout slot inventory contains only the four live layout ids'
    );
    red_theme_test_assert(
        $layoutSlotInventory['index']['public']['positions'] === ['1', '2', '3']
            && $layoutSlotInventory['index']['control-panel']['positions'] === ['1', '2', '3', '0'],
        'index slot inventory preserves three public positions and hidden control-panel position zero'
    );
    red_theme_test_assert(
        $layoutSlotInventory['index-1']['public']['positions'] === ['1', '2', '3', '4']
            && $layoutSlotInventory['index-1']['control-panel']['positions'] === ['1', '2', '3', '4', '0'],
        'index-1 slot inventory preserves all four rendered positions and hidden position zero'
    );
    red_theme_test_assert(
        $layoutSlotInventory['index-2']['public']['positions'] === ['1', '2', '3', '4']
            && $layoutSlotInventory['index-2']['control-panel']['positions'] === ['1', '2', '3', '4', '0'],
        'index-2 slot inventory preserves four stacked positions and hidden position zero'
    );
    red_theme_test_assert(
        $layoutSlotInventory['index-3']['public']['positions'] === ['1', '2']
            && $layoutSlotInventory['index-3']['control-panel']['positions'] === ['1', '2', '0'],
        'index-3 slot inventory preserves main, sidebar, and hidden control-panel positions'
    );

    $expectedLegacyLayouts = [
        'index' => [
            'html' =>
                '<div class="container px-4 pb-0 pt-3"><div class="row"><breadcrumb />' .
                '<div class="col-lg-6"><slot-1 /></div><div class="col-lg-6"><slot-2 /></div>' .
                '</div></div><div class="container px-4 pb-0 pt-3"><div class="row">' .
                '<div class="col-lg-12"><slot-3 /></div></div></div>',
            'calls' => ['breadcrumb', 'slot:1', 'slot:2', 'slot:3'],
        ],
        'index-1' => [
            'html' =>
                '<div class="container px-4 pb-0 pt-3"><div class="row"><breadcrumb />' .
                '<div class="col-lg-12"><slot-1 /></div></div></div>' .
                '<div class="container px-4 pb-0 pt-3"><div class="row">' .
                '<div class="col-lg-4"><slot-2 /></div><div class="col-lg-4"><slot-3 /></div>' .
                '<div class="col-lg-4"><slot-4 /></div></div></div>',
            'calls' => ['breadcrumb', 'slot:1', 'slot:2', 'slot:3', 'slot:4'],
        ],
        'index-2' => [
            'html' =>
                '<div class="container px-4 pb-0 pt-3"><div class="row"><breadcrumb />' .
                '<div class="col-lg-12 pt-3"><slot-1 /></div>' .
                '<div class="col-lg-12 pt-3"><slot-2 /></div>' .
                '<div class="col-lg-12 pt-3"><slot-3 /></div>' .
                '<div class="col-lg-12 pt-3"><slot-4 /></div></div></div>',
            'calls' => ['breadcrumb', 'slot:1', 'slot:2', 'slot:3', 'slot:4'],
        ],
        'index-3' => [
            'html' =>
                '<div class="container px-4 pb-0 pt-3"><div class="row"><breadcrumb />' .
                '<div class="col-lg-8 col-md-8 col-sm-8"><slot-1 /></div>' .
                '<div class="col-lg-4 col-md-4 col-sm-4"><slot-2 /></div></div></div>',
            'calls' => ['breadcrumb', 'slot:1', 'slot:2'],
        ],
    ];
    foreach ($expectedLegacyLayouts as $layoutId => $expectedLayout) {
        [$layoutHtml, $layoutCalls] = red_theme_test_render_legacy_layout($layoutId);
        red_theme_test_assert(
            $layoutHtml === $expectedLayout['html'],
            $layoutId . ' legacy public layout preserves its exact compatibility markup'
        );
        red_theme_test_assert(
            $layoutCalls === $expectedLayout['calls'],
            $layoutId . ' legacy public layout preserves breadcrumb and slot order'
        );
    }

    $unsupportedLayoutRejected = false;
    try {
        red_legacy_layout_slot_context('query', 'features', 'positions', '1', 'index-4', 10);
    } catch (InvalidArgumentException $exception) {
        $unsupportedLayoutRejected = true;
    }
    red_theme_test_assert(
        $unsupportedLayoutRejected,
        'legacy layout slot preparation rejects a dormant layout id'
    );

    $publicHiddenPositionRejected = false;
    try {
        red_legacy_layout_slot_context('query', 'features', 'positions', '0', 'index', 10);
    } catch (InvalidArgumentException $exception) {
        $publicHiddenPositionRejected = true;
    }
    red_theme_test_assert(
        $publicHiddenPositionRejected,
        'legacy public slot preparation rejects control-panel-only position zero'
    );

    $publicSlotContext = red_legacy_layout_slot_context(
        ['prepared-query'],
        'SectionFeatured',
        'SectionPosition',
        2,
        'index',
        25,
        'public',
        'ignored-table'
    );
    red_theme_test_assert(
        $publicSlotContext === [
            'mode' => 'public',
            'method' => 'articles',
            'articleQuery' => ['prepared-query'],
            'varFeatures' => 'SectionFeatured',
            'varPosition' => 'SectionPosition',
            'position' => '2',
            'layout' => 'index',
            'limit' => 25,
            'table' => null,
        ],
        'legacy public slot context preserves the exact existing renderer inputs'
    );
    red_theme_test_assert(
        red_legacy_render_layout_slot(
            $publicSlotContext,
            function ($context) {
                return $context;
            }
        ) === $publicSlotContext,
        'legacy shared slot renderer accepts an injectable dependency-free renderer'
    );

    $controlPanelSlotContext = red_legacy_layout_slot_context(
        'prepared-admin-query',
        'PageFeatured',
        'PagePosition',
        0,
        'index-1',
        50,
        'control-panel',
        'RED_Articles'
    );
    red_theme_test_assert(
        $controlPanelSlotContext['method'] === 'cp_articles'
            && $controlPanelSlotContext['position'] === '0'
            && $controlPanelSlotContext['table'] === 'RED_Articles',
        'legacy control-panel slot context preserves its method, hidden position, and table input'
    );

    $expectedVisibleWrapperContext = [
        'layout' => 'index-3',
        'position' => '2',
        'hidden' => false,
        'titles' => [
            'enabled' => true,
            'className' => 'cp_titles',
        ],
        'item' => [
            'hiddenStyle' => 'float:left; padding-right:5px; margin-right:5px;',
        ],
        'order' => [
            'enabled' => true,
            'endpoint' => '/admin/bin/update_order.php',
            'formId' => 'update_order_2',
            'functionName' => 'run_update_order_2',
            'alertId' => 'msggbox_alert_2',
            'csrfRequired' => true,
            'successMessage' => 'Order Updated',
            'failureMessage' => 'Nothing to Update. Please try again.',
        ],
    ];
    red_theme_test_assert(
        red_legacy_control_panel_slot_wrapper_context_from_data('index-3', 2)
            === $expectedVisibleWrapperContext,
        'legacy visible control-panel wrapper context preserves exact title and order inputs'
    );
    $preparedControlPanelWrappers = [];
    foreach ($layoutSlotInventory as $layoutId => $layoutContract) {
        foreach ($layoutContract['control-panel']['positions'] as $slotPosition) {
            $wrapperContext = red_legacy_control_panel_slot_wrapper_context_from_data(
                $layoutId,
                $slotPosition
            );
            $preparedControlPanelWrappers[] = $wrapperContext;
            red_theme_test_assert(
                red_legacy_control_panel_slot_wrapper_context_validate(
                    $wrapperContext,
                    $layoutId,
                    $slotPosition
                ) === $wrapperContext,
                $layoutId . ' position ' . $slotPosition . ' control-panel wrapper context validates'
            );
        }
    }
    $hiddenWrapperCount = count(array_filter(
        $preparedControlPanelWrappers,
        function ($wrapperContext) {
            return $wrapperContext['hidden'];
        }
    ));
    red_theme_test_assert(
        count($preparedControlPanelWrappers) === 17
            && $hiddenWrapperCount === 4,
        'legacy control-panel wrapper preparation covers all seventeen live slots and four hidden slots'
    );
    $wrapperContextsAreDataOnly = true;
    foreach ($preparedControlPanelWrappers as $wrapperContext) {
        if (array_intersect(
            ['class', 'method', 'renderer', 'callback', 'callable'],
            array_keys($wrapperContext)
        )) {
            $wrapperContextsAreDataOnly = false;
        }
        if (array_keys($wrapperContext['titles']) !== ['enabled', 'className']
            || array_keys($wrapperContext['item']) !== ['hiddenStyle']
            || array_keys($wrapperContext['order']) !== [
                'enabled',
                'endpoint',
                'formId',
                'functionName',
                'alertId',
                'csrfRequired',
                'successMessage',
                'failureMessage',
            ]
        ) {
            $wrapperContextsAreDataOnly = false;
        }
    }
    red_theme_test_assert(
        $wrapperContextsAreDataOnly,
        'legacy control-panel wrapper contexts contain only fixed data and no executable mapping'
    );
    $tamperedWrapperRejected = false;
    $tamperedWrapper = $expectedVisibleWrapperContext;
    $tamperedWrapper['order']['endpoint'] = '/admin/bin/tampered.php';
    try {
        red_legacy_control_panel_slot_wrapper_context_validate(
            $tamperedWrapper,
            'index-3',
            '2'
        );
    } catch (InvalidArgumentException $exception) {
        $tamperedWrapperRejected = true;
    }
    red_theme_test_assert(
        $tamperedWrapperRejected,
        'legacy control-panel wrapper validation rejects a changed endpoint'
    );
    $unsupportedControlPanelWrapperRejected = false;
    try {
        red_legacy_control_panel_slot_wrapper_context_from_data('index-4', '1');
    } catch (InvalidArgumentException $exception) {
        $unsupportedControlPanelWrapperRejected = true;
    }
    red_theme_test_assert(
        $unsupportedControlPanelWrapperRejected,
        'legacy control-panel wrapper preparation rejects a dormant layout id'
    );

    $invalidSlotMethodRejected = false;
    $invalidSlotContext = $publicSlotContext;
    $invalidSlotContext['method'] = 'cp_articles';
    try {
        red_legacy_render_layout_slot($invalidSlotContext, function () {
            return null;
        });
    } catch (InvalidArgumentException $exception) {
        $invalidSlotMethodRejected = true;
    }
    red_theme_test_assert(
        $invalidSlotMethodRejected,
        'legacy shared slot renderer rejects a mode and method mismatch'
    );

    $componentInputInventory = red_legacy_public_component_input_inventory();
    red_theme_test_assert(
        $componentInputInventory === [
            'Article' => ['recordId', 'layout', 'article', 'position'],
            'Form' => ['recordId'],
            'Gallery' => ['position', 'recordId', 'layout', 'smallPicture'],
            'Other' => ['recordId', 'layout', 'article', 'position'],
        ],
        'legacy public component inventory contains only the four existing input sets'
    );
    $componentRow = [
        'Component' => 'Article',
        'RecordID' => '123',
        'SmallPict' => 'small.jpg',
        'Unrelated' => 'ignored',
    ];
    $componentRowBefore = $componentRow;
    red_theme_test_assert(
        red_legacy_public_component_context($componentRow, 'index-2', 'example-article', '3', true) === [
            'component' => 'Article',
            'active' => true,
            'inputs' => [
                'recordId' => '123',
                'layout' => 'index-2',
                'article' => 'example-article',
                'position' => '3',
            ],
        ],
        'legacy Article preparation preserves its exact public inputs'
    );
    $componentRow['Component'] = 'Form';
    red_theme_test_assert(
        red_legacy_public_component_context($componentRow, 'index-2', 'ignored', '3', false) === [
            'component' => 'Form',
            'active' => false,
            'inputs' => ['recordId' => '123'],
        ],
        'legacy Form preparation preserves only its record id and active state'
    );
    $componentRow['Component'] = 'Gallery';
    red_theme_test_assert(
        red_legacy_public_component_context($componentRow, 'index-2', 'ignored', '3', true) === [
            'component' => 'Gallery',
            'active' => true,
            'inputs' => [
                'position' => '3',
                'recordId' => '123',
                'layout' => 'index-2',
                'smallPicture' => 'small.jpg',
            ],
        ],
        'legacy Gallery preparation preserves its exact public inputs'
    );
    $componentRow['Component'] = 'Other';
    $otherComponentContext = red_legacy_public_component_context(
        $componentRow,
        'index-2',
        'example-article',
        '3',
        true
    );
    red_theme_test_assert(
        $otherComponentContext === [
            'component' => 'Other',
            'active' => true,
            'inputs' => [
                'recordId' => '123',
                'layout' => 'index-2',
                'article' => 'example-article',
                'position' => '3',
            ],
        ],
        'legacy Other preparation preserves its exact public inputs'
    );
    red_theme_test_assert(
        !array_key_exists('class', $otherComponentContext)
            && !array_key_exists('method', $otherComponentContext)
            && !array_key_exists('renderer', $otherComponentContext),
        'legacy public component context contains no executable mapping'
    );
    $componentRow['Component'] = 'Unsupported';
    red_theme_test_assert(
        red_legacy_public_component_context($componentRow, 'index-2', 'example-article', '3', true) === null,
        'unsupported legacy public components remain silently unprepared'
    );
    $pureComponentRow = $componentRowBefore;
    red_legacy_public_component_context($pureComponentRow, 'index-2', 'example-article', '3', true);
    red_theme_test_assert(
        $pureComponentRow === $componentRowBefore,
        'legacy public component preparation does not mutate its source row'
    );

    $controlPanelComponentInputInventory = red_legacy_control_panel_component_input_inventory();
    red_theme_test_assert(
        $controlPanelComponentInputInventory === [
            'Article' => ['position', 'recordId', 'varPosition', 'layout'],
            'Other' => ['position', 'recordId', 'varPosition', 'layout'],
            'Form' => ['recordId', 'varFeatures', 'varPosition', 'table', 'position', 'layout'],
            'Gallery' => ['position', 'recordId', 'layout', 'varFeatures', 'varPosition', 'table'],
        ],
        'legacy control-panel component inventory preserves the four exact editor call signatures'
    );
    $controlPanelComponentRow = [
        'Component' => 'Article',
        'RecordID' => '123',
        'Alias' => 'cp-example',
        'SectionPositionOrder' => '7',
        'Unrelated' => 'ignored',
    ];
    $controlPanelComponentRowBefore = $controlPanelComponentRow;
    $articleControlPanelContext = red_legacy_control_panel_component_context_from_data(
        $controlPanelComponentRow,
        'SectionFeatures',
        'SectionPosition',
        '3',
        'index-2',
        'RED_Articles',
        true,
        2
    );
    red_theme_test_assert(
        $articleControlPanelContext === [
            'authorized' => true,
            'component' => 'Article',
            'supported' => true,
            'alias' => 'cp_example',
            'order' => [
                'index' => 2,
                'value' => '7',
                'varPosition' => 'SectionPosition',
                'recordId' => '123',
            ],
            'inputs' => [
                'position' => '3',
                'recordId' => '123',
                'varPosition' => 'SectionPosition',
                'layout' => 'index-2',
            ],
        ],
        'legacy Article control-panel context preserves wrapper, order, and editor inputs'
    );
    $expectedControlPanelInputs = [
        'Other' => [
            'position' => '3',
            'recordId' => '123',
            'varPosition' => 'SectionPosition',
            'layout' => 'index-2',
        ],
        'Form' => [
            'recordId' => '123',
            'varFeatures' => 'SectionFeatures',
            'varPosition' => 'SectionPosition',
            'table' => 'RED_Articles',
            'position' => '3',
            'layout' => 'index-2',
        ],
        'Gallery' => [
            'position' => '3',
            'recordId' => '123',
            'layout' => 'index-2',
            'varFeatures' => 'SectionFeatures',
            'varPosition' => 'SectionPosition',
            'table' => 'RED_Articles',
        ],
    ];
    foreach ($expectedControlPanelInputs as $component => $expectedInputs) {
        $componentFixture = $controlPanelComponentRow;
        $componentFixture['Component'] = $component;
        $preparedComponent = red_legacy_control_panel_component_context_from_data(
            $componentFixture,
            'SectionFeatures',
            'SectionPosition',
            '3',
            'index-2',
            'RED_Articles',
            true,
            2
        );
        red_theme_test_assert(
            $preparedComponent['component'] === $component
                && $preparedComponent['supported']
                && $preparedComponent['inputs'] === $expectedInputs,
            'legacy ' . $component . ' control-panel context preserves its exact editor inputs'
        );
    }
    $unauthorizedControlPanelContext = red_legacy_control_panel_component_context_from_data(
        ['RecordID' => '999'],
        'ignored',
        'SectionPosition',
        '1',
        'index',
        'RED_Articles',
        false,
        0
    );
    red_theme_test_assert(
        $unauthorizedControlPanelContext === [
            'authorized' => false,
            'component' => null,
            'supported' => false,
            'alias' => null,
            'order' => null,
            'inputs' => [],
        ],
        'unauthorized control-panel preparation stops before component and wrapper data access'
    );
    $unsupportedControlPanelRow = $controlPanelComponentRow;
    $unsupportedControlPanelRow['Component'] = 'Unsupported';
    $unsupportedControlPanelContext = red_legacy_control_panel_component_context_from_data(
        $unsupportedControlPanelRow,
        'SectionFeatures',
        'SectionPosition',
        '3',
        'index-2',
        'RED_Articles',
        true,
        2
    );
    red_theme_test_assert(
        !$unsupportedControlPanelContext['supported']
            && $unsupportedControlPanelContext['inputs'] === []
            && $unsupportedControlPanelContext['order']['recordId'] === '123',
        'unsupported authorized control-panel components preserve wrapper order and remain undispatched'
    );
    red_theme_test_assert(
        !array_intersect(
            ['class', 'method', 'renderer', 'callback', 'callable'],
            array_keys($articleControlPanelContext)
        ),
        'legacy control-panel component context contains no executable mapping'
    );
    red_theme_test_assert(
        $controlPanelComponentRow === $controlPanelComponentRowBefore,
        'legacy control-panel component preparation does not mutate its source row'
    );
    $incompleteControlPanelRowRejected = false;
    try {
        red_legacy_control_panel_component_context_from_data(
            [
                'Component' => 'Article',
                'RecordID' => '123',
                'Alias' => 'cp-example',
            ],
            'SectionFeatures',
            'SectionPosition',
            '3',
            'index-2',
            'RED_Articles',
            true,
            2
        );
    } catch (InvalidArgumentException $exception) {
        $incompleteControlPanelRowRejected = true;
    }
    red_theme_test_assert(
        $incompleteControlPanelRowRejected,
        'legacy control-panel component preparation rejects a missing dynamic order field'
    );
    $invalidControlPanelAuthorizationRejected = false;
    try {
        red_legacy_control_panel_component_context_from_data(
            $controlPanelComponentRow,
            'SectionFeatures',
            'SectionPosition',
            '3',
            'index-2',
            'RED_Articles',
            'yes',
            2
        );
    } catch (InvalidArgumentException $exception) {
        $invalidControlPanelAuthorizationRejected = true;
    }
    red_theme_test_assert(
        $invalidControlPanelAuthorizationRejected,
        'legacy control-panel component preparation rejects a non-boolean authorization result'
    );
    $controlPanelContentSource = file_get_contents(dirname(__DIR__) . '/class/class_content.php');
    red_theme_test_assert(
        is_string($controlPanelContentSource)
            && strpos($controlPanelContentSource, 'red_legacy_control_panel_component_context(') !== false
            && strpos($controlPanelContentSource, 'red_legacy_control_panel_slot_wrapper_context_validate(') !== false,
        'legacy content dispatcher consumes both prepared control-panel contexts'
    );
    red_theme_test_assert(
        strpos($controlPanelContentSource, 'new cp_Article()') !== false
            && strpos($controlPanelContentSource, 'new cp_other()') !== false
            && strpos($controlPanelContentSource, 'new forms()') !== false
            && strpos($controlPanelContentSource, 'new gallery()') !== false
            && strpos($controlPanelContentSource, '->cp_Article(') !== false
            && strpos($controlPanelContentSource, '->cp_other(') !== false
            && strpos($controlPanelContentSource, '->cp_form(') !== false
            && strpos($controlPanelContentSource, '->cp_album(') !== false,
        'legacy control-panel dispatcher retains its four fixed core class and method calls'
    );
    red_theme_test_assert(
        strpos($controlPanelContentSource, '/admin/bin/update_order.php') !== false
            && strpos($controlPanelContentSource, 'red_csrf_input()') !== false,
        'legacy control-panel dispatcher retains the protected order endpoint and CSRF field'
    );

    $componentRendererRegistry = red_legacy_public_component_renderer_registry();
    red_theme_test_assert(
        array_keys($componentRendererRegistry) === ['Article', 'Form', 'Gallery', 'Other']
            && count(array_filter($componentRendererRegistry, 'is_callable')) === 4,
        'legacy public component registry contains only four fixed core renderers'
    );
    $redThemeAdapter = new class {
        public $formInputs;
        public $galleryInputs;

        public function renderPublicFormComponent(array $inputs)
        {
            $this->formInputs = $inputs;
            echo '<form-view />';
        }

        public function renderPublicGalleryComponent(array $inputs)
        {
            $this->galleryInputs = $inputs;
            echo '<gallery-view />';
        }
    };
    ob_start();
    $componentRendererRegistry['Form'](['recordId' => '123']);
    $formRegistryHtml = ob_get_clean();
    red_theme_test_assert(
        $redThemeAdapter->formInputs === ['recordId' => '123']
            && $formRegistryHtml === '<form-view /><div class="clear-1"></div>',
        'fixed Form renderer preserves adapter input and clear-spacer order'
    );
    $galleryRegistryInputs = [
        'position' => '3',
        'recordId' => '123',
        'layout' => 'index-2',
        'smallPicture' => 'small.jpg',
    ];
    ob_start();
    $componentRendererRegistry['Gallery']($galleryRegistryInputs);
    $galleryRegistryHtml = ob_get_clean();
    red_theme_test_assert(
        $redThemeAdapter->galleryInputs === $galleryRegistryInputs
            && $galleryRegistryHtml === '<gallery-view /><div class="clear-1"></div>',
        'fixed Gallery renderer preserves adapter input and clear-spacer order'
    );
    unset($redThemeAdapter);
    $componentDispatchCalls = [];
    $componentDispatchContexts = [
        'Article' => red_legacy_public_component_context(
            $componentRowBefore,
            'index-2',
            'example-article',
            '3',
            true
        ),
    ];
    foreach (['Form', 'Gallery', 'Other'] as $componentName) {
        $dispatchRow = $componentRowBefore;
        $dispatchRow['Component'] = $componentName;
        $componentDispatchContexts[$componentName] = red_legacy_public_component_context(
            $dispatchRow,
            'index-2',
            'example-article',
            '3',
            true
        );
    }
    foreach ($componentDispatchContexts as $componentName => $dispatchContext) {
        red_theme_test_assert(
            red_legacy_render_public_component(
                $dispatchContext,
                function (array $inputs) use (&$componentDispatchCalls, $componentName) {
                    $componentDispatchCalls[$componentName] = $inputs;
                }
            ),
            'legacy ' . $componentName . ' public component dispatch reports a rendered component'
        );
    }
    red_theme_test_assert(
        $componentDispatchCalls === [
            'Article' => [
                'recordId' => '123',
                'layout' => 'index-2',
                'article' => 'example-article',
                'position' => '3',
            ],
            'Form' => ['recordId' => '123'],
            'Gallery' => [
                'position' => '3',
                'recordId' => '123',
                'layout' => 'index-2',
                'smallPicture' => 'small.jpg',
            ],
            'Other' => [
                'recordId' => '123',
                'layout' => 'index-2',
                'article' => 'example-article',
                'position' => '3',
            ],
        ],
        'legacy public component dispatcher preserves every prepared input and dispatch order'
    );
    $inactiveComponentCalls = 0;
    $inactiveComponentContext = $componentDispatchContexts['Form'];
    $inactiveComponentContext['active'] = false;
    red_theme_test_assert(
        red_legacy_render_public_component(
            $inactiveComponentContext,
            function () use (&$inactiveComponentCalls) {
                $inactiveComponentCalls++;
            }
        ) === false && $inactiveComponentCalls === 0,
        'inactive legacy public components do not invoke their renderer'
    );
    red_theme_test_assert(
        red_legacy_render_public_component(null, function () {
            throw new RuntimeException('Unknown component renderer must not run.');
        }) === false,
        'unknown legacy public components remain silent at the dispatch boundary'
    );
    $malformedComponentContextRejected = false;
    $malformedComponentContext = $componentDispatchContexts['Gallery'];
    unset($malformedComponentContext['inputs']['smallPicture']);
    try {
        red_legacy_render_public_component($malformedComponentContext, function () {
            return null;
        });
    } catch (InvalidArgumentException $exception) {
        $malformedComponentContextRejected = true;
    }
    red_theme_test_assert(
        $malformedComponentContextRejected,
        'legacy public component dispatcher rejects malformed prepared input keys'
    );

    $formTemplate =
        '#|question=|name=email|type=textfield|required=true|displayname=Email:|initialvalue=a=b;' . "\r\n" .
        '#|question=|name=Submit|type=button|displayname=submit';
    $formRow = [
        'RecordID' => '93039112',
        'RefID' => '459269660',
        'Alias' => '9 contact-form',
        'Title' => 'Contact',
        'FormType' => 'Contact',
        'LongDesc' => $formTemplate,
    ];
    red_theme_test_assert(
        red_legacy_public_form_row_inventory() === array_keys($formRow),
        'legacy Form row inventory matches the exact public RED_C_Form projection'
    );
    $formFields = red_legacy_public_form_template_fields($formTemplate);
    red_theme_test_assert(
        $formFields === [
            [
                '#' => '',
                'question' => '',
                'name' => 'email',
                'type' => 'textfield',
                'required' => 'true',
                'displayname' => 'Email:',
                'initialvalue' => 'a=b',
            ],
            [
                "\r\n#" => '',
                'question' => '',
                'name' => 'Submit',
                'type' => 'button',
                'displayname' => 'submit',
            ],
        ],
        'legacy Form parser preserves semicolon rows, pipe fields, line prefixes, and equals signs inside values'
    );
    red_theme_test_assert(
        red_legacy_public_form_template_fields('alpha|beta=two;') === [
            ['alpha' => '', 'beta' => 'two'],
            ['' => ''],
        ],
        'legacy Form parser preserves missing equals values and a trailing empty field row'
    );
    $formRowsBefore = [$formRow];
    $formContext = red_legacy_public_form_context_from_data($formRowsBefore);
    red_theme_test_assert(
        $formContext === [
            'rows' => [
                [
                    'record' => $formRow,
                    'fields' => $formFields,
                    'alias' => [
                        'raw' => '9 contact-form',
                        'javascript' => 'form_9_contact_form',
                    ],
                    'action' => [
                        'formType' => 'Contact',
                        'endpoint' => '/bin/contact.php',
                        'payloadMode' => 'serialized-form',
                    ],
                ],
            ],
        ],
        'legacy Form context preserves exact record, parsed fields, alias inputs, and Contact action inputs'
    );
    red_theme_test_assert(
        $formRowsBefore === [$formRow],
        'legacy Form preparation does not mutate its source rows'
    );
    red_theme_test_assert(
        red_legacy_public_form_alias_inputs(' -- ') === ['raw' => ' -- ', 'javascript' => 'form']
            && red_legacy_public_form_alias_inputs('login_form') === [
                'raw' => 'login_form',
                'javascript' => 'login_form',
            ],
        'legacy Form alias preparation preserves the raw value and exact JavaScript fallback rules'
    );
    $formActions = [];
    foreach (['Contact', 'Login', 'Response', 'Register', 'Custom'] as $formType) {
        $formActions[$formType] = red_legacy_public_form_action_inputs($formType);
    }
    red_theme_test_assert(
        $formActions === [
            'Contact' => [
                'formType' => 'Contact',
                'endpoint' => '/bin/contact.php',
                'payloadMode' => 'serialized-form',
            ],
            'Login' => [
                'formType' => 'Login',
                'endpoint' => '/bin/login.php',
                'payloadMode' => 'data-string',
            ],
            'Response' => [
                'formType' => 'Response',
                'endpoint' => '/bin/response.php',
                'payloadMode' => 'serialized-form',
            ],
            'Register' => [
                'formType' => 'Register',
                'endpoint' => '/bin/register.php',
                'payloadMode' => 'serialized-form',
            ],
            'Custom' => [
                'formType' => 'Custom',
                'endpoint' => '',
                'payloadMode' => 'native-submit',
            ],
        ],
        'legacy Form action preparation preserves every fixed endpoint, payload mode, and native fallback'
    );
    $formStructureKeys = array_merge(
        array_keys($formContext),
        array_keys($formContext['rows'][0]),
        array_keys($formContext['rows'][0]['alias']),
        array_keys($formContext['rows'][0]['action'])
    );
    red_theme_test_assert(
        !array_intersect(['class', 'method', 'renderer', 'callback', 'callable'], $formStructureKeys),
        'legacy Form context contains no executable mapping'
    );
    $invalidFormRowRejected = false;
    $invalidFormRow = $formRow;
    unset($invalidFormRow['LongDesc']);
    try {
        red_legacy_public_form_context_from_data([$invalidFormRow]);
    } catch (InvalidArgumentException $exception) {
        $invalidFormRowRejected = true;
    }
    red_theme_test_assert(
        $invalidFormRowRejected,
        'legacy Form preparation rejects incomplete projection rows'
    );
    $invalidFormTemplateRejected = false;
    $invalidFormTemplateRow = $formRow;
    $invalidFormTemplateRow['LongDesc'] = [];
    try {
        red_legacy_public_form_context_from_data([$invalidFormTemplateRow]);
    } catch (InvalidArgumentException $exception) {
        $invalidFormTemplateRejected = true;
    }
    red_theme_test_assert(
        $invalidFormTemplateRejected,
        'legacy Form preparation rejects a non-string template'
    );
    red_theme_test_assert(
        red_legacy_public_form_context_validate($formContext) === $formContext,
        'legacy Form view validation reconstructs the exact prepared context from source rows'
    );
    $tamperedFormContextRejected = false;
    $tamperedFormContext = $formContext;
    $tamperedFormContext['rows'][0]['action']['endpoint'] = '/bin/changed.php';
    try {
        red_legacy_public_form_context_validate($tamperedFormContext);
    } catch (InvalidArgumentException $exception) {
        $tamperedFormContextRejected = true;
    }
    red_theme_test_assert(
        $tamperedFormContextRejected,
        'legacy Form view validation rejects action data that does not match its source row'
    );

    $contactFormHtml = red_theme_test_render_legacy_form($formContext);
    red_theme_test_assert(
        str_contains($contactFormHtml, 'function checkform_form_9_contact_form (formElement)')
            && str_contains($contactFormHtml, 'alert("Campo obligatorio -\\u003E Email:.");')
            && str_contains($contactFormHtml, 'url: "/bin/contact.php",')
            && str_contains($contactFormHtml, 'data: $("#form_9_contact_form").serialize(),')
            && str_contains(
                $contactFormHtml,
                '<form id="form_9_contact_form" class="form_9_contact_form" name="form_9_contact_form" method="post" onSubmit="return checkform_form_9_contact_form(this);">'
            )
            && str_contains($contactFormHtml, '<input type="hidden" name="alias" value="9 contact-form" />')
            && str_contains($contactFormHtml, '<input type="hidden" name="RecordID" value="93039112" />')
            && str_contains($contactFormHtml, '<textarea id="MySpamTrap" name="MySpamTrap" rows="3" cols="4"></textarea>'),
        'legacy Form view preserves Contact validation, endpoint, serialized payload, form, and hidden-field markup'
    );

    $enhancedContactRow = $formRow;
    $enhancedContactRow['LongDesc'] =
        '#|question=|name=name|type=textfield|required=true|displayname=Nombre|initialvalue=|autocomplete=name|placeholder=Tu nombre;' .
        '#|question=|name=email|type=textfield|required=true|displayname=Email|initialvalue=|inputtype=email|autocomplete=email|placeholder=tu@email.com;' .
        '#|question=|name=message|type=textarea|required=false|displayname=Mensaje|initialvalue=|cols=1|rows=5|readonly=false|placeholder=Cuéntame brevemente en qué puedo ayudarte;' .
        '#|type=paragraph|paragraph=Formulario local de respaldo.;' .
        '#|question=|name=Submit|type=button|displayname=Enviar mensaje';
    $enhancedContactHtml = red_theme_test_render_legacy_form(
        red_legacy_public_form_context_from_data([$enhancedContactRow])
    );
    red_theme_test_assert(
        str_contains(
            $enhancedContactHtml,
            '<input type="text" name="name" class="text-input" id="name" value="" autocomplete="name" placeholder="Tu nombre" />'
        )
            && str_contains(
                $enhancedContactHtml,
                '<input type="email" name="email" class="text-input" id="email" value="" autocomplete="email" placeholder="tu@email.com" />'
            )
            && str_contains(
                $enhancedContactHtml,
                '<textarea name="message" class="text-input" id="message" cols="1" rows="5" placeholder="Cuéntame brevemente en qué puedo ayudarte"></textarea>'
            )
            && str_contains(
                $enhancedContactHtml,
                '<p class="form-note">Formulario local de respaldo.</p>'
            )
            && !str_contains($enhancedContactHtml, 'form_9_contact_form..value'),
        'legacy Form view supports optional safe field attributes and display-only notes without serializing note rows'
    );

    $loginFormRow = [
        'RecordID' => '884542279',
        'RefID' => '966111194',
        'Alias' => 'login',
        'Title' => 'Login',
        'FormType' => 'Login',
        'LongDesc' =>
            '#|question=|name=username|type=textfield|required=true|displayname=Username:|initialvalue=;' .
            '#|question=|name=password|type=password|required=true|displayname=Password:|initialvalue=;' .
            '#|question=|name=Submit|type=button|displayname=login',
    ];
    $loginFormContext = red_legacy_public_form_context_from_data([$loginFormRow]);
    $loginFormHtml = red_theme_test_render_legacy_form($loginFormContext);
    red_theme_test_assert(
        red_legacy_public_form_context_validate($loginFormContext) === $loginFormContext
            && str_contains($loginFormHtml, 'function checkform_login (formElement)')
            && str_contains($loginFormHtml, 'alert("Campo obligatorio -\\u003E Username:.");')
            && str_contains($loginFormHtml, 'alert("Campo obligatorio -\\u003E Password:.");')
            && str_contains($loginFormHtml, 'url: "/bin/login.php",')
            && str_contains($loginFormHtml, 'data: dataString,')
            && substr_count($loginFormHtml, 'document.location="/administracion/";') === 2
            && str_contains($loginFormHtml, '<input type="password" name="password"')
            && str_contains($loginFormHtml, '<input type="hidden" name="alias" value="login" />'),
        'legacy Form view preserves Login validation, data-string payload, endpoint, response redirect, and hidden alias'
    );

    $serializedFormBranches = [];
    foreach (['Response' => '/bin/response.php', 'Register' => '/bin/register.php'] as $formType => $endpoint) {
        $serializedRow = $loginFormRow;
        $serializedRow['RecordID'] = $formType === 'Response' ? '10' : '11';
        $serializedRow['Alias'] = strtolower($formType);
        $serializedRow['Title'] = $formType;
        $serializedRow['FormType'] = $formType;
        $serializedRow['LongDesc'] = '#|question=|name=Submit|type=button|displayname=submit';
        $serializedHtml = red_theme_test_render_legacy_form(
            red_legacy_public_form_context_from_data([$serializedRow])
        );
        $serializedFormBranches[$formType] = str_contains($serializedHtml, 'url: "' . $endpoint . '",')
            && str_contains($serializedHtml, 'data: $("#' . strtolower($formType) . '").serialize(),');
    }
    red_theme_test_assert(
        $serializedFormBranches === ['Response' => true, 'Register' => true],
        'legacy Form view preserves Response and Register endpoints with serialized-form payloads'
    );

    $formViewSource = file_get_contents(
        dirname(__DIR__) . '/themes/legacy-bootstrap/components/form.php'
    );
    red_theme_test_assert(
        is_string($formViewSource)
            && str_contains($formViewSource, 'red_legacy_public_form_context_validate')
            && !preg_match('/new\\s+connection|mysqli_|red_public_form_rows|new\\s+forms|->form\\s*\\(/', $formViewSource),
        'legacy Form view validates prepared context without querying or dispatching components'
    );

    $galleryStackInsert = red_admin_gallery_collect_values(
        ['Title' => 'Stack', 'GalleryType' => 'Gallery'],
        'insert',
        501,
        601
    );
    $galleryCarouselInsert = red_admin_gallery_collect_values(
        ['Title' => 'Carousel', 'GalleryType' => 'Gallery', 'GalleryPresentation' => 'carousel'],
        'insert',
        502,
        602
    );
    $galleryPresentationPreserved = red_admin_gallery_collect_values(
        ['Title' => 'Partial update', 'GalleryType' => 'Gallery'],
        'update',
        503,
        603
    );
    $videoCraftedPresentation = red_admin_gallery_collect_values(
        ['Title' => 'Video', 'GalleryType' => 'Video', 'GalleryPresentation' => 'carousel'],
        'update',
        504,
        604
    );
    $bannerNewWindow = red_admin_gallery_collect_values(
        ['Title' => 'Banner', 'GalleryType' => 'Banner', 'NewWindow' => 'Y'],
        'update',
        505,
        605
    );
    red_theme_test_assert(
        ($galleryStackInsert['NewWindow'] ?? null) === ''
            && ($galleryCarouselInsert['NewWindow'] ?? null) === 'Y'
            && !array_key_exists('NewWindow', $galleryPresentationPreserved)
            && ($videoCraftedPresentation['NewWindow'] ?? null) === ''
            && ($bannerNewWindow['NewWindow'] ?? null) === 'Y'
            && red_admin_gallery_clean_presentation('carousel') === 'carousel'
            && red_admin_gallery_clean_presentation('anything-else') === 'stack',
        'Gallery presentation defaults to stack, maps carousel only for Gallery, preserves partial Gallery updates, and leaves Video and Banner NewWindow semantics independent'
    );
    red_theme_test_assert(
        red_admin_gallery_insert_reuse_allowed('', 'Gallery')
            && red_admin_gallery_insert_reuse_allowed('Gallery', 'Gallery')
            && !red_admin_gallery_insert_reuse_allowed('Banner', 'Gallery')
            && !red_admin_gallery_insert_reuse_allowed('Video', 'Gallery')
            && !red_admin_gallery_insert_reuse_allowed('Carrousel', 'Gallery')
            && !red_admin_gallery_insert_reuse_allowed('Gallery', 'Banner'),
        'Gallery insert retries accept only a blank upload placeholder or the exact existing subtype'
    );
    $galleryUploadPlaceholder = red_admin_article_default_insert_data(701);
    $galleryUploadPlaceholder['Component'] = 'Gallery';
    $galleryUploadPlaceholder['Layout'] = 'index';
    $galleryUploadPlaceholder['PagePosition'] = 0;
    $galleryUploadPlaceholder['Active'] = 'N';
    $galleryUploadPlaceholder['BigPict'] = 'queued.jpg';
    $otherComponentPlaceholder = $galleryUploadPlaceholder;
    $otherComponentPlaceholder['Component'] = 'Article';
    $savedGalleryArticle = red_admin_article_default_insert_data(702);
    $savedGalleryArticle['Component'] = 'Gallery';
    $savedOtherArticle = $savedGalleryArticle;
    $savedOtherArticle['Component'] = 'Article';
    $blankGalleryChild = ['GalleryType' => ''];
    $savedGalleryChild = ['GalleryType' => 'Gallery'];
    $savedBannerChild = ['GalleryType' => 'Banner'];
    red_theme_test_assert(
        red_admin_gallery_insert_target_allowed(null, null, 'Gallery')
            && red_admin_gallery_insert_target_allowed(null, $blankGalleryChild, 'Gallery')
            && !red_admin_gallery_insert_target_allowed(null, $savedGalleryChild, 'Gallery')
            && red_admin_gallery_insert_target_allowed($galleryUploadPlaceholder, null, 'Gallery')
            && red_admin_gallery_insert_target_allowed($galleryUploadPlaceholder, $blankGalleryChild, 'Gallery')
            && !red_admin_gallery_insert_target_allowed($galleryUploadPlaceholder, $savedGalleryChild, 'Gallery')
            && !red_admin_gallery_insert_target_allowed($otherComponentPlaceholder, $blankGalleryChild, 'Gallery')
            && red_admin_gallery_insert_target_allowed($savedGalleryArticle, $savedGalleryChild, 'Gallery')
            && !red_admin_gallery_insert_target_allowed($savedGalleryArticle, null, 'Gallery')
            && !red_admin_gallery_insert_target_allowed($savedGalleryArticle, $blankGalleryChild, 'Gallery')
            && !red_admin_gallery_insert_target_allowed($savedGalleryArticle, $savedBannerChild, 'Gallery')
            && !red_admin_gallery_insert_target_allowed($savedOtherArticle, $savedGalleryChild, 'Gallery'),
        'Gallery insert targets permit only fresh pairs, verified upload placeholders, or authorized exact-subtype retries without component mutation'
    );
    red_theme_test_assert(
        red_legacy_public_gallery_link_url('/gallery/details') === '/gallery/details'
            && red_legacy_public_gallery_link_url('https://example.com/gallery') === 'https://example.com/gallery'
            && red_legacy_public_gallery_link_url('javascript:alert(1)') === ''
            && red_legacy_public_gallery_link_url('//example.com/unsafe') === '',
        'Gallery photo links accept safe internal or HTTPS destinations and discard executable or protocol-relative values'
    );

    $galleryDimensions = [
        'Width' => 196.0,
        'WidthDivisor' => 3.0,
        'Height' => 0,
        'vWidth' => 390.0,
        'vHeight' => 219.0,
    ];
    $galleryRow = [
        'RecordID' => '201',
        'RefID' => '123',
        'Alias' => 'example-gallery',
        'Title' => 'Gallery title',
        'GalleryType' => 'Gallery',
        'ShortDesc' =>
            'First caption;https://example.test/one?x=1&y=2,Second caption',
        'LongDesc' => 'one.jpg,two.jpg,three.jpg',
        'Link' => '',
        'NewWindow' => '',
    ];
    red_theme_test_assert(
        red_legacy_public_gallery_row_inventory() === array_keys($galleryRow),
        'legacy Gallery row inventory matches the exact public RED_C_Gallery projection'
    );
    $secondGalleryRow = $galleryRow;
    $secondGalleryRow['RecordID'] = '202';
    $secondGalleryRow['Alias'] = 'second-gallery';
    $secondGalleryRow['ShortDesc'] = 'Nested caption';
    $secondGalleryRow['LongDesc'] = 'nested.jpg';
    $secondGalleryRow['NewWindow'] = 'Y';
    $youtubeRow = $galleryRow;
    $youtubeRow['RecordID'] = '203';
    $youtubeRow['Alias'] = 'long-youtube';
    $youtubeRow['Title'] = 'Video title';
    $youtubeRow['GalleryType'] = 'Video';
    $youtubeRow['ShortDesc'] = '<p>Video copy</p>';
    $youtubeRow['LongDesc'] = 'https://www.youtube.com/watch?v=pP8VJwjSnqA&feature=youtu.be';
    $youtubeRow['Link'] = 'https://example.test/video?x=1&y=2';
    $youtubeRow['NewWindow'] = '';
    $bannerRow = $galleryRow;
    $bannerRow['RecordID'] = '204';
    $bannerRow['Alias'] = 'example-banner';
    $bannerRow['Title'] = 'Banner title';
    $bannerRow['GalleryType'] = 'Banner';
    $bannerRow['ShortDesc'] = '';
    $bannerRow['LongDesc'] = 'banner&image.png';
    $bannerRow['Link'] = '/administracion/?x=1&y=2';
    $bannerRow['NewWindow'] = '';
    $gallerySourceRows = [$galleryRow, $secondGalleryRow, $youtubeRow, $bannerRow];
    $gallerySourceRowsBefore = $gallerySourceRows;
    $galleryContext = red_legacy_public_gallery_context_from_data(
        $galleryDimensions,
        $gallerySourceRows
    );
    red_theme_test_assert(
        $gallerySourceRows === $gallerySourceRowsBefore,
        'legacy Gallery preparation does not mutate its ordered source rows'
    );
    red_theme_test_assert(
        $galleryContext['dimensions'] === $galleryDimensions
            && $galleryContext['rows'][0]['gallery'] === [
                'presentation' => 'stack',
                'width' => 196.0 / 3.0,
                'photos' => [
                    [
                        'file' => 'one.jpg',
                        'title' => 'First caption',
                        'url' => 'https://example.test/one?x=1&y=2',
                    ],
                    [
                        'file' => 'two.jpg',
                        'title' => 'Second caption',
                        'url' => '',
                    ],
                    [
                        'file' => 'three.jpg',
                        'title' => '',
                        'url' => '',
                    ],
                ],
            ]
            && $galleryContext['rows'][0]['link'] === [
                'href' => '',
                'target' => '_self',
            ],
        'legacy Gallery context preserves width division plus comma and semicolon photo metadata parsing'
    );
    red_theme_test_assert(
        $galleryContext['rows'][1]['gallery'] === [
            'presentation' => 'carousel',
            'width' => (196.0 / 3.0) / 3.0,
            'photos' => [
                [
                    'file' => 'nested.jpg',
                    'title' => 'Nested caption',
                    'url' => '',
                ],
            ],
        ]
            && $galleryContext['rows'][1]['link']['target'] === '_self'
            && $galleryContext['rows'][2]['link']['target'] === '_self'
            && $galleryContext['rows'][3]['link']['target'] === '_self',
        'legacy Gallery context preserves accumulated widths while isolating carousel presentation from Video and Banner link targets'
    );
    red_theme_test_assert(
        $galleryContext['rows'][2]['video'] === [
            'provider' => 'youtube',
            'id' => 'pP8VJwjSnqA',
            'canonical_url' => 'https://www.youtube.com/watch?v=pP8VJwjSnqA',
            'embed_url' => 'https://www.youtube.com/embed/pP8VJwjSnqA?wmode=transparent',
            'privacy_embed_url' => 'https://www.youtube-nocookie.com/embed/pP8VJwjSnqA?rel=0&playsinline=1',
            'thumbnail_url' => 'https://i.ytimg.com/vi/pP8VJwjSnqA/hqdefault.jpg',
            'provider_label' => 'YouTube',
        ]
            && $galleryContext['rows'][2]['link']['href'] ===
                'https://example.test/video?x=1&y=2'
            && $galleryContext['rows'][3]['banner'] === ['image' => 'banner&image.png']
            && $galleryContext['rows'][3]['link']['href'] === '/administracion/?x=1&y=2',
        'legacy Gallery context recognizes long YouTube links while preserving exact Video and Banner link inputs'
    );
    $videoProviders = [];
    foreach ([
        'youtube' => 'https://youtu.be/M7lc1UVf-VE',
        'vimeo' => 'https://vimeo.com/987654321',
        'external' => 'https://videos.example.test/watch/presentation',
    ] as $expectedProvider => $videoUrl) {
        $providerRow = $youtubeRow;
        $providerRow['LongDesc'] = $videoUrl;
        $providerContext = red_legacy_public_gallery_context_from_data(
            $galleryDimensions,
            [$providerRow]
        );
        $videoProviders[$expectedProvider] = $providerContext['rows'][0]['video'];
    }
    red_theme_test_assert(
        $videoProviders === [
            'youtube' => [
                'provider' => 'youtube',
                'id' => 'M7lc1UVf-VE',
                'canonical_url' => 'https://www.youtube.com/watch?v=M7lc1UVf-VE',
                'embed_url' => 'https://www.youtube.com/embed/M7lc1UVf-VE?wmode=transparent',
                'privacy_embed_url' => 'https://www.youtube-nocookie.com/embed/M7lc1UVf-VE?rel=0&playsinline=1',
                'thumbnail_url' => 'https://i.ytimg.com/vi/M7lc1UVf-VE/hqdefault.jpg',
                'provider_label' => 'YouTube',
            ],
            'vimeo' => [
                'provider' => 'vimeo',
                'id' => '987654321',
                'canonical_url' => 'https://vimeo.com/987654321',
                'embed_url' => 'https://player.vimeo.com/video/987654321',
                'privacy_embed_url' => 'https://player.vimeo.com/video/987654321',
                'thumbnail_url' => '',
                'provider_label' => 'Vimeo',
            ],
            'external' => [
                'provider' => 'external',
                'id' => '',
                'canonical_url' => 'https://videos.example.test/watch/presentation',
                'embed_url' => '',
                'privacy_embed_url' => '',
                'thumbnail_url' => '',
                'provider_label' => 'videos.example.test',
            ],
        ],
        'legacy Gallery Video preparation recognizes YouTube, Vimeo, and safe external video links'
    );
    $unlistedVimeoData = red_video_url_data('https://vimeo.com/987654321/abcDEF123456');
    red_theme_test_assert(
        red_video_url_normalize('https://m.youtube.com/shorts/M7lc1UVf-VE?feature=share') ===
            'https://www.youtube.com/watch?v=M7lc1UVf-VE'
            && red_video_url_normalize('https://www.youtube-nocookie.com/embed/M7lc1UVf-VE') ===
                'https://www.youtube.com/watch?v=M7lc1UVf-VE'
            && red_video_url_normalize('https://www.youtube.com/live/M7lc1UVf-VE') ===
                'https://www.youtube.com/watch?v=M7lc1UVf-VE'
            && red_video_url_normalize('https://player.vimeo.com/video/987654321') ===
                'https://vimeo.com/987654321'
            && is_array($unlistedVimeoData)
            && $unlistedVimeoData['canonical_url'] === 'https://vimeo.com/987654321/abcDEF123456'
            && $unlistedVimeoData['embed_url'] ===
                'https://player.vimeo.com/video/987654321?h=abcDEF123456'
            && red_video_url_data('http://www.youtube.com/watch?v=M7lc1UVf-VE') === null
            && red_video_url_data('https://youtube.com.evil.test/watch?v=M7lc1UVf-VE') === null
            && red_video_url_data('https://user:pass@youtube.com/watch?v=M7lc1UVf-VE') === null
            && red_video_url_data('https://www.youtube.com/watch?v=too-short') === null
            && red_video_url_data('https://www.youtube.com/watch?v=M7lc1UVf-VE&v=too-short') === null
            && red_video_url_data('https://player.vimeo.com/video/987654321?h=abcDEF123456&h=different789') === null
            && red_video_url_data('<iframe src="https://www.youtube.com/embed/M7lc1UVf-VE"></iframe>') === null
            && red_video_url_data(['https://youtu.be/M7lc1UVf-VE']) === null,
        'video URL recognition accepts current provider and unlisted Vimeo shapes while rejecting duplicate identifiers, insecure, credentialed, malformed, embed-code, and non-scalar input'
    );
    $galleryStructureKeys = array_merge(
        array_keys($galleryContext),
        array_keys($galleryContext['rows'][0]),
        array_keys($galleryContext['rows'][0]['link']),
        array_keys($galleryContext['rows'][0]['gallery']),
        array_keys($galleryContext['rows'][0]['video']),
        array_keys($galleryContext['rows'][0]['banner'])
    );
    red_theme_test_assert(
        array_keys($galleryContext) === ['dimensions', 'rows']
            && array_keys($galleryContext['rows'][0]) ===
                ['record', 'link', 'gallery', 'video', 'banner']
            && !array_intersect(
                ['class', 'method', 'renderer', 'callback', 'callable'],
                $galleryStructureKeys
            ),
        'legacy Gallery context contains only data and no executable mapping'
    );
    red_theme_test_assert(
        red_legacy_public_gallery_context_validate($galleryContext) === $galleryContext,
        'legacy Gallery validation reconstructs the exact prepared context from source data'
    );
    $galleryHtml = red_theme_test_render_legacy_gallery($galleryContext);
    red_theme_test_assert(
        substr_count($galleryHtml, 'class="red-public-gallery__figure"') === 4
            && substr_count($galleryHtml, 'w=65.333333333333&amp;img=/images/gallery/') === 3
            && substr_count($galleryHtml, 'w=196&amp;img=/images/gallery/') === 1
            && str_contains($galleryHtml, 'data-red-gallery="stack"')
            && str_contains($galleryHtml, 'data-red-gallery="carousel"')
            && str_contains($galleryHtml, '<figcaption class="red-public-gallery__caption" aria-hidden="true">First caption</figcaption>')
            && str_contains($galleryHtml, '<figcaption class="red-public-gallery__caption" aria-hidden="true">Second caption</figcaption>')
            && str_contains($galleryHtml, '<figcaption class="red-public-gallery__caption" aria-hidden="true">Nested caption</figcaption>')
            && str_contains($galleryHtml, '<a class="red-public-gallery__link" href="https://example.test/one?x=1&amp;y=2">')
            && str_contains($galleryHtml, 'alt="Gallery title, photo 3 of 3"')
            && !str_contains($galleryHtml, 'data-red-gallery-controls'),
        'legacy Gallery view renders ordered stack and single-photo carousel collections with exact captions and no unnecessary controls'
    );
    $multiCarouselRow = $galleryRow;
    $multiCarouselRow['NewWindow'] = 'Y';
    $multiCarouselHtml = red_theme_test_render_legacy_gallery(
        red_legacy_public_gallery_context_from_data($galleryDimensions, [$multiCarouselRow])
    );
    red_theme_test_assert(
        str_contains($multiCarouselHtml, 'data-red-gallery="carousel"')
            && str_contains($multiCarouselHtml, 'data-red-gallery-carousel')
            && substr_count($multiCarouselHtml, 'data-red-gallery-slide') === 3
            && substr_count($multiCarouselHtml, 'data-red-gallery-dot=') === 3
            && str_contains($multiCarouselHtml, 'data-red-gallery-previous')
            && str_contains($multiCarouselHtml, 'data-red-gallery-next')
            && str_contains($multiCarouselHtml, 'data-red-gallery-status aria-live="polite"')
            && !str_contains($multiCarouselHtml, 'data-red-gallery-slide hidden'),
        'multi-photo carousel renders unique controls, dots, live status, keyboard target, and a complete no-JavaScript photo fallback'
    );
    red_theme_test_assert(
        str_contains(
            $galleryHtml,
            '<h3>Video title</h3><div class="js-video widescreen">' .
                '<iframe width="390" height="219" src="https://www.youtube.com/embed/' .
                'pP8VJwjSnqA?wmode=transparent" title="Video title" loading="lazy" frameborder="0" ' .
                'allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" ' .
                'allowfullscreen></iframe></div>' .
                '<p>Video copy</p><a href="https://example.test/video?x=1&amp;y=2" ' .
                'target="_self" class="link-1">Read More</a><div class="clear-1"></div>'
        )
            && str_ends_with(
                $galleryHtml,
                '<figure class="img-indent"><a href="/administracion/?x=1&amp;y=2" target="_self" ' .
                    'title=""><img class="red-gallery-image" src="/images/gallery/banner&amp;image.png" ' .
                    'alt=""></a></figure>'
            ),
        'legacy Gallery view preserves Video player, raw summary, read-more, isolated target, and linked Banner markup'
    );
    foreach ([
        'https://youtu.be/M7lc1UVf-VE' =>
            '<div class="js-video widescreen"><iframe width="390" height="219" ' .
                'src="https://www.youtube.com/embed/M7lc1UVf-VE?wmode=transparent" title="Embedded video" ' .
                'loading="lazy" frameborder="0" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; ' .
                'picture-in-picture; web-share" allowfullscreen></iframe></div>',
        'https://vimeo.com/987654321' =>
            '<div class="js-video vimeo"><iframe src="https://player.vimeo.com/video/987654321" ' .
                'width="390" height="219" title="Embedded video" loading="lazy" frameborder="0" ' .
                'allow="fullscreen; picture-in-picture" allowfullscreen></iframe></div>',
    ] as $providerUrl => $expectedPlayer) {
        $providerRow = $youtubeRow;
        $providerRow['Title'] = '';
        $providerRow['ShortDesc'] = '';
        $providerRow['LongDesc'] = $providerUrl;
        $providerRow['Link'] = '';
        red_theme_test_assert(
            red_theme_test_render_legacy_gallery(
                red_legacy_public_gallery_context_from_data($galleryDimensions, [$providerRow])
            ) === $expectedPlayer,
            'legacy Gallery view preserves exact alternate Video provider markup for ' . $providerUrl
        );
    }
    $externalVideoRow = $youtubeRow;
    $externalVideoRow['Title'] = '';
    $externalVideoRow['ShortDesc'] = '';
    $externalVideoRow['LongDesc'] = 'https://videos.example.test/watch/presentation';
    $externalVideoRow['Link'] = '';
    red_theme_test_assert(
        red_theme_test_render_legacy_gallery(
            red_legacy_public_gallery_context_from_data($galleryDimensions, [$externalVideoRow])
        ) === '<p class="red-public-video-external"><a class="link-1" href="https://videos.example.test/watch/presentation" target="_blank" rel="noopener noreferrer">Watch video on videos.example.test</a></p>',
        'legacy Gallery view gives unrecognized HTTPS providers a safe external-link fallback instead of executing supplied embed markup'
    );
    $unlinkedBannerRow = $bannerRow;
    $unlinkedBannerRow['Link'] = '';
    red_theme_test_assert(
        red_theme_test_render_legacy_gallery(
            red_legacy_public_gallery_context_from_data($galleryDimensions, [$unlinkedBannerRow])
        ) ===
            '<figure class="img-indent"><img class="red-gallery-image" ' .
            'src="/images/gallery/banner&amp;image.png" alt=""></figure>',
        'legacy Gallery view preserves the unlinked Banner branch exactly'
    );
    $tamperedGalleryContextRejected = false;
    $tamperedGalleryContext = $galleryContext;
    $tamperedGalleryContext['rows'][2]['video']['id'] = 'changed';
    try {
        red_theme_test_render_legacy_gallery($tamperedGalleryContext);
    } catch (InvalidArgumentException $exception) {
        $tamperedGalleryContextRejected = true;
    }
    red_theme_test_assert(
        $tamperedGalleryContextRejected,
        'legacy Gallery view rejects derived media data that does not match its source row'
    );
    $invalidGalleryDimensionsRejected = false;
    try {
        red_legacy_public_gallery_context_from_data(['Width' => 196.0], [$galleryRow]);
    } catch (InvalidArgumentException $exception) {
        $invalidGalleryDimensionsRejected = true;
    }
    red_theme_test_assert(
        $invalidGalleryDimensionsRejected,
        'legacy Gallery preparation rejects incomplete layout dimensions'
    );
    $invalidGalleryRowRejected = false;
    $invalidGalleryRow = $galleryRow;
    unset($invalidGalleryRow['NewWindow']);
    try {
        red_legacy_public_gallery_context_from_data($galleryDimensions, [$invalidGalleryRow]);
    } catch (InvalidArgumentException $exception) {
        $invalidGalleryRowRejected = true;
    }
    red_theme_test_assert(
        $invalidGalleryRowRejected,
        'legacy Gallery preparation rejects incomplete projection rows'
    );
    $invalidGalleryValueRejected = false;
    $invalidGalleryValueRow = $galleryRow;
    $invalidGalleryValueRow['LongDesc'] = [];
    try {
        red_legacy_public_gallery_context_from_data($galleryDimensions, [$invalidGalleryValueRow]);
    } catch (InvalidArgumentException $exception) {
        $invalidGalleryValueRejected = true;
    }
    red_theme_test_assert(
        $invalidGalleryValueRejected,
        'legacy Gallery preparation rejects non-scalar projection values'
    );
    $legacyComponentHelperSource = file_get_contents(
        dirname(__DIR__) . '/includes/legacy_component_helpers.php'
    );
    red_theme_test_assert(
        is_string($legacyComponentHelperSource)
            && str_contains($legacyComponentHelperSource, 'renderPublicGalleryComponent')
            && str_contains($legacyComponentHelperSource, "echo '<div class=\"clear-1\"></div>';")
            && !str_contains($legacyComponentHelperSource, '$component = new gallery();')
            && !str_contains($legacyComponentHelperSource, '$component->album(')
            && !str_contains($legacyComponentHelperSource, "'Gallery' => ['class'")
            && !str_contains($legacyComponentHelperSource, "'Gallery' => ['method'"),
        'fixed Gallery registry reaches the adapter view boundary and preserves its clear spacer without executable data mapping'
    );
    $galleryViewSource = file_get_contents(
        dirname(__DIR__) . '/themes/legacy-bootstrap/components/gallery.php'
    );
    red_theme_test_assert(
        is_string($galleryViewSource)
            && str_contains($galleryViewSource, 'red_legacy_public_gallery_context_validate')
            && !str_contains($galleryViewSource, 'new connection(')
            && !str_contains($galleryViewSource, 'mysqli_')
            && !str_contains($galleryViewSource, 'red_public_gallery_rows(')
            && !str_contains($galleryViewSource, 'new gallery')
            && !str_contains($galleryViewSource, '->album('),
        'legacy Gallery component view consumes validated prepared data without database or class dispatch'
    );
    $publicGalleryCss = file_get_contents(dirname(__DIR__) . '/css/public-gallery.css');
    $publicGalleryScript = file_get_contents(dirname(__DIR__) . '/js/public-gallery.js');
    $publicGalleryStandardAdapterSource = file_get_contents(
        dirname(__DIR__) . '/includes/theme_standard_adapter.php'
    );
    $publicGalleryLegacyDocumentSource = file_get_contents(
        dirname(__DIR__) . '/themes/legacy-bootstrap/document.php'
    );
    red_theme_test_assert(
        is_string($publicGalleryCss)
            && str_contains($publicGalleryCss, '.red-public-gallery--carousel.is-ready .red-public-gallery__item')
            && str_contains($publicGalleryCss, '@media (prefers-reduced-motion: reduce)')
            && is_string($publicGalleryScript)
            && str_contains($publicGalleryScript, "event.key === 'ArrowLeft'")
            && str_contains($publicGalleryScript, "event.key === 'Home'")
            && str_contains($publicGalleryScript, "event.key === 'End'")
            && !str_contains(strtolower($publicGalleryScript), 'autoplay')
            && is_string($publicGalleryStandardAdapterSource)
            && substr_count($publicGalleryStandardAdapterSource, '/css/public-gallery.css') === 1
            && substr_count($publicGalleryStandardAdapterSource, '/js/public-gallery.js') === 1
            && is_string($publicGalleryLegacyDocumentSource)
            && substr_count($publicGalleryLegacyDocumentSource, '/css/public-gallery.css') === 1
            && substr_count($publicGalleryLegacyDocumentSource, '/js/public-gallery.js') === 1,
        'public Gallery assets load once per theme shell and provide progressive enhancement, keyboard controls, no autoplay, and reduced-motion behavior'
    );
    $galleryClassSource = file_get_contents(dirname(__DIR__) . '/class/class_gallery.php');
    $galleryPublicSource = is_string($galleryClassSource)
        ? strstr($galleryClassSource, 'CONTROL PANEL', true)
        : false;
    red_theme_test_assert(
        is_string($galleryPublicSource)
            && str_contains($galleryPublicSource, 'red_legacy_public_gallery_context_validate')
            && str_contains($galleryPublicSource, 'red_legacy_public_gallery_context')
            && !str_contains($galleryPublicSource, 'new connection(')
            && !str_contains($galleryPublicSource, 'red_public_gallery_rows('),
        'legacy Gallery public renderer consumes validated prepared data without direct database access'
    );

    $articleDimensions = [
        'Width' => 196.0,
        'WidthDivisor' => 1.0,
        'Height' => 0,
        'vWidth' => 0.0,
        'vHeight' => 0.0,
    ];
    $articleRow = [
        'RecordID' => '123',
        'Alias' => 'example-article',
        'Title' => 'Article &amp; "Title"',
        'ShortDesc' => '<p id="article-short">Short branch</p>',
        'LongDesc' => '<p id="article-long">Long branch</p>',
        'Link' => '',
        'NewWindow' => '',
        'Component' => 'Article',
        'Sections' => 'administracion',
        'Categories' => 'news',
        'SubCategories' => 'updates',
        'SmallPict' => 'small&.jpg',
        'SmallPictAlign' => 'Left',
        'SmallPict2' => 'detail&.jpg',
        'SmallPictAlign2' => 'Right',
    ];
    red_theme_test_assert(
        red_legacy_public_article_row_inventory() === array_keys($articleRow),
        'legacy Article row inventory matches the exact public RED_Articles projection'
    );
    $articleListContext = red_legacy_public_article_view_context_from_data(
        '/administracion/news/updates/example-article',
        '',
        $articleDimensions,
        [$articleRow]
    );
    red_theme_test_assert(
        $articleListContext['url'] === '/administracion/news/updates/example-article'
            && $articleListContext['article'] === ''
            && $articleListContext['dimensions'] === $articleDimensions
            && $articleListContext['rows'][0]['selected'] === false
            && $articleListContext['rows'][0]['closeLine'] === [
                'linked' => true,
                'href' => '/administracion/news/updates/example-article',
                'target' => '_self',
            ],
        'legacy Article view context preserves URL, dimensions, internal link, target, and close-line inputs'
    );
    red_theme_test_assert(
        array_keys($articleListContext) === ['url', 'article', 'dimensions', 'rows']
            && array_keys($articleListContext['rows'][0]) === ['record', 'selected', 'closeLine']
            && !array_intersect(
                ['class', 'method', 'renderer', 'callback', 'callable'],
                array_keys($articleListContext)
            ),
        'legacy Article view context contains no executable mapping'
    );
    red_theme_test_assert(
        red_theme_test_render_legacy_article($articleListContext) ===
            '<div class="thumb-pad6 clearfix"><div class="thumbnail"><div class="badgeBox">' .
            '<div class="caption <!--maxheight-->"><h2><a href="/administracion/news/updates/example-article" ' .
            'target="_self" class="link-article">Article &amp; &quot;Title&quot;</a></h2>' .
            '<figure><a href="/administracion/news/updates/example-article" target="_self">' .
            '<img src="/images/articles/small&amp;.jpg" align="Left" title="Article &amp; &quot;Title&quot;" ' .
            'class="SmallPict_Left" border="0" style="margin-bottom:20px;"></a></figure>' .
            '<p id="article-short">Short branch</p></div>' .
            '<a href="/administracion/news/updates/example-article" target="_self" ' .
            'class="btn-default btn5">Leer m&aacute;s</a><div class="clear-1"></div>' .
            '</div></div></div>',
        'legacy Article listing branch preserves internal-link, image, description, and close-line markup exactly'
    );
    $articleSelectedContext = red_legacy_public_article_view_context_from_data(
        '/administracion/news/updates/example-article',
        'example-article',
        $articleDimensions,
        [$articleRow]
    );
    red_theme_test_assert(
        red_theme_test_render_legacy_article($articleSelectedContext) ===
            '<div class="thumb-pad3 clearfix"><div class="thumbnail"><div class="badgeBox">' .
            '<h2>Article &amp; &quot;Title&quot;</h2>' .
            '<figure><img src="/images/articles/detail&amp;.jpg" align="Right" ' .
            'title="Article &amp; &quot;Title&quot;" class="SmallPict_Right"></figure>' .
            '<p id="article-long">Long branch</p><div class="clear-1"></div>' .
            '<div class="fb-like" data-href="/administracion/news/updates/example-article" ' .
            'data-width="500" data-layout="" data-action="" data-size="" data-share="true"></div>' .
            '</div></div></div>',
        'legacy Article selected branch preserves detail image, LongDesc, and Facebook URL markup exactly'
    );
    $articleExternalRow = $articleRow;
    $articleExternalRow['Link'] = 'https://example.test/article?x=1&y=2';
    $articleExternalRow['NewWindow'] = 'Y';
    $articleExternalContext = red_legacy_public_article_view_context_from_data(
        '/administracion/news/updates/example-article',
        '',
        $articleDimensions,
        [$articleExternalRow]
    );
    $articleExternalHtml = red_theme_test_render_legacy_article($articleExternalContext);
    red_theme_test_assert(
        $articleExternalContext['rows'][0]['closeLine'] === [
            'linked' => true,
            'href' => 'https://example.test/article?x=1&y=2',
            'target' => '_blank',
        ]
            && substr_count($articleExternalHtml, 'href="https://example.test/article?x=1&amp;y=2"') === 3
            && substr_count($articleExternalHtml, 'target="_blank"') === 3,
        'legacy Article listing preserves stored-link override and new-window target in all three anchors'
    );
    $articlePlainRow = $articleRow;
    $articlePlainRow['Component'] = 'Other';
    $articlePlainRow['LongDesc'] = '';
    $articlePlainRow['Link'] = '';
    $articlePlainContext = red_legacy_public_article_view_context_from_data(
        '/administracion/news/updates/example-article',
        '',
        $articleDimensions,
        [$articlePlainRow]
    );
    red_theme_test_assert(
        $articlePlainContext['rows'][0]['closeLine']['linked'] === false
            && red_theme_test_render_legacy_article($articlePlainContext) ===
                '<div class="thumb-pad6 clearfix"><div class="thumbnail"><div class="badgeBox">' .
                '<div class="caption <!--maxheight-->"><h2>Article &amp; &quot;Title&quot;</h2>' .
                '<figure><img src="/images/resize.php?w=196&amp;img=/images/articles/small&amp;.jpg" ' .
                'align="Left" title="Article &amp; &quot;Title&quot;" class="SmallPict_Left" border="0"></figure>' .
                '<p id="article-short">Short branch</p></div><div class="clear-1"></div>' .
                '</div></div></div>',
        'legacy Article unlinked listing preserves resized image and clear-only close line'
    );
    $tamperedArticleContextRejected = false;
    $tamperedArticleContext = $articleListContext;
    $tamperedArticleContext['rows'][0]['closeLine']['target'] = '_top';
    try {
        red_theme_test_render_legacy_article($tamperedArticleContext);
    } catch (InvalidArgumentException $exception) {
        $tamperedArticleContextRejected = true;
    }
    red_theme_test_assert(
        $tamperedArticleContextRejected,
        'legacy Article view rejects derived link state that does not match its source row'
    );
    $invalidArticleDimensionsRejected = false;
    try {
        red_legacy_public_article_view_context_from_data('', '', ['Width' => 196.0], [$articleRow]);
    } catch (InvalidArgumentException $exception) {
        $invalidArticleDimensionsRejected = true;
    }
    red_theme_test_assert(
        $invalidArticleDimensionsRejected,
        'legacy Article view preparation rejects incomplete layout dimensions'
    );
    $invalidArticleRowRejected = false;
    $invalidArticleRow = $articleRow;
    unset($invalidArticleRow['SmallPictAlign2']);
    try {
        red_legacy_public_article_view_context_from_data('', '', $articleDimensions, [$invalidArticleRow]);
    } catch (InvalidArgumentException $exception) {
        $invalidArticleRowRejected = true;
    }
    red_theme_test_assert(
        $invalidArticleRowRejected,
        'legacy Article view preparation rejects incomplete article rows'
    );

    $otherDimensions = $articleDimensions;
    $otherRow = [
        'RecordID' => '123',
        'Alias' => 'example-article',
        'Title' => 'Other Title',
        'ShortDesc' => '<p id="other-short">Short branch</p>',
        'LongDesc' => '<p id="other-long">Long branch</p>',
        'Link' => '',
        'NewWindow' => '',
        'Component' => 'Other',
        'Sections' => 'administracion',
        'Categories' => '',
        'SubCategories' => '',
        'SmallPict' => 'small.jpg',
        'SmallPictAlign' => 'Left',
        'SmallPict2' => 'detail.jpg',
        'SmallPictAlign2' => 'Right',
    ];
    red_theme_test_assert(
        red_legacy_public_other_row_inventory() === array_keys($otherRow),
        'legacy Other row inventory matches the exact public RED_Articles projection'
    );
    $otherListContext = red_legacy_public_other_view_context_from_data('', $otherDimensions, [$otherRow]);
    red_theme_test_assert(
        $otherListContext === [
            'article' => '',
            'dimensions' => $otherDimensions,
            'rows' => [$otherRow],
        ],
        'legacy Other view context preserves exact dimensions and ordered article rows'
    );
    red_theme_test_assert(
        array_keys($otherListContext) === ['article', 'dimensions', 'rows'],
        'legacy Other view context contains no executable mapping'
    );
    red_theme_test_assert(
        red_theme_test_render_legacy_other($otherListContext) ===
            '<img src="/images/resize.php?w=196&amp;img=/images/articles/small.jpg" ' .
            'align="Left" title="Other Title" class="SmallPict_Left">' .
            '<p id="other-short">Short branch</p>',
        'legacy Other list branch preserves resized-image and ShortDesc markup exactly'
    );
    $otherSelectedContext = red_legacy_public_other_view_context_from_data(
        'example-article',
        $otherDimensions,
        [$otherRow]
    );
    red_theme_test_assert(
        red_theme_test_render_legacy_other($otherSelectedContext) ===
            '<img src="/images/articles/detail.jpg" align="Right" title="Other Title" ' .
            'class="SmallPict_Right"><p id="other-long">Long branch</p>',
        'legacy Other selected-article branch preserves direct-image and LongDesc markup exactly'
    );
    $otherMismatchContext = red_legacy_public_other_view_context_from_data(
        'different-article',
        $otherDimensions,
        [$otherRow]
    );
    red_theme_test_assert(
        red_theme_test_render_legacy_other($otherMismatchContext) ===
            '<img src="/images/resize.php?w=196&amp;img=/images/articles/small.jpg" ' .
            'align="Left" title="Other Title" class="SmallPict_Left">' .
            '<p id="other-short">Short branch</p>',
        'legacy Other nonmatching selected-article branch preserves ShortDesc markup exactly'
    );
    $otherTopRow = $otherRow;
    $otherTopRow['SmallPictAlign'] = 'Top';
    red_theme_test_assert(
        red_theme_test_render_legacy_other(
            red_legacy_public_other_view_context_from_data('', $otherDimensions, [$otherTopRow])
        ) === '<p id="other-short">Short branch</p>',
        'legacy Other list branch preserves Top-image suppression'
    );
    $invalidOtherDimensionsRejected = false;
    try {
        red_legacy_public_other_view_context_from_data('', ['Width' => 196.0], [$otherRow]);
    } catch (InvalidArgumentException $exception) {
        $invalidOtherDimensionsRejected = true;
    }
    red_theme_test_assert(
        $invalidOtherDimensionsRejected,
        'legacy Other view preparation rejects incomplete layout dimensions'
    );
    $invalidOtherRowRejected = false;
    $invalidOtherRow = $otherRow;
    unset($invalidOtherRow['SmallPictAlign2']);
    try {
        red_legacy_public_other_view_context_from_data('', $otherDimensions, [$invalidOtherRow]);
    } catch (InvalidArgumentException $exception) {
        $invalidOtherRowRejected = true;
    }
    red_theme_test_assert(
        $invalidOtherRowRejected,
        'legacy Other view preparation rejects incomplete article rows'
    );

    $navigationContext = red_public_legacy_navigation_context_from_rows(
        [
            ['RecordID' => 1, 'Parent' => 0, 'RootOrder' => 1, 'Label' => 'Inicio', 'Link' => '/', 'NewWindow' => ''],
            ['RecordID' => 2, 'Parent' => 0, 'RootOrder' => 1, 'Label' => 'Contacto', 'Link' => '/contacto/', 'NewWindow' => ''],
            ['RecordID' => 3, 'Parent' => 2, 'RootOrder' => 2, 'Label' => 'About', 'Link' => '/contacto/about', 'NewWindow' => ''],
            ['RecordID' => 4, 'Parent' => 3, 'RootOrder' => 3, 'Label' => 'Details', 'Link' => '/contacto/about/details', 'NewWindow' => ''],
        ],
        ['section' => 'contacto', 'article' => '', 'countpage' => 3]
    );
    red_theme_test_assert(
        count($navigationContext['items']) === 2,
        'legacy navigation context contains only root menu items'
    );
    red_theme_test_assert(
        ($navigationContext['items'][1]['itemClass'] ?? '') === 'sub-menu active',
        'legacy navigation context preserves nested active classes'
    );
    red_theme_test_assert(
        ($navigationContext['items'][1]['children'][0]['children'][0]['label'] ?? '') === 'Details',
        'legacy navigation context preserves three menu levels'
    );
    $redThemeNavigationContext = $navigationContext;
    ob_start();
    require dirname(__DIR__) . '/themes/legacy-bootstrap/partials/navigation.php';
    $navigationHtml = ob_get_clean();
    red_theme_test_assert(
        $navigationHtml ===
            '<nav class="navbar navbar-default navbar-static-top tm_navbar clearfix" role="navigation">' .
            '<ul class="nav sf-menu clearfix"><li class=""><a href="/" target=""><i>Home</i><em></em></a></li>' .
            '<li class="sub-menu active"><a href="/contacto/" target="">Contacto<span></span></a>' .
            '<ul class="submenu"><li><a href="/contacto/about" target="">About<span></span></a>' .
            '<ul class="submenu"><li><a href="/contacto/about/details" target="">Details</a></li>' .
            '<li class="tr"></li></ul></li></ul></li></ul></nav>',
        'legacy navigation partial preserves the three-level compatibility markup'
    );

    $redThemeNavigationContext = [
        'mode' => 'production',
        'items' => $navigationContext['items'],
    ];
    ob_start();
    require dirname(__DIR__) . '/themes/starter-reference/partials/production-navigation.php';
    $starterNavigationHtml = ob_get_clean();
    red_theme_test_assert(
        strpos($starterNavigationHtml, 'data-starter-navigation') !== false
            && strpos($starterNavigationHtml, 'href="/contacto/about"') !== false
            && strpos($starterNavigationHtml, 'href="/contacto/about/details"') !== false,
        'starter-reference production navigation renders category and subcategory links'
    );
    red_theme_test_assert(
        substr_count($starterNavigationHtml, 'data-starter-navigation-toggle') === 2
            && strpos($starterNavigationHtml, 'starter-navigation__submenu--level-2') !== false
            && strpos($starterNavigationHtml, 'starter-navigation__submenu--level-3') !== false,
        'starter-reference production navigation exposes accessible controls for both nested levels'
    );

    $starterNavigationManifest = json_decode(
        file_get_contents(dirname(__DIR__) . '/themes/starter-reference/theme.json'),
        true
    );
    $starterProductionScripts = $starterNavigationManifest['production']['assets']['scripts'] ?? [];
    red_theme_test_assert(
        in_array(
            'assets/js/navigation.js',
            array_column($starterProductionScripts, 'path'),
            true
        ),
        'starter-reference production manifest loads the nested navigation controller'
    );
    $starterNavigationCss = file_get_contents(
        dirname(__DIR__) . '/themes/starter-reference/assets/css/production.css'
    );
    $starterNavigationScript = file_get_contents(
        dirname(__DIR__) . '/themes/starter-reference/assets/js/navigation.js'
    );
    red_theme_test_assert(
        strpos($starterNavigationCss, '[data-navigation-ready]') !== false
            && strpos($starterNavigationCss, '@media (max-width: 47.99rem)') !== false
            && strpos($starterNavigationScript, 'aria-expanded') !== false
            && strpos($starterNavigationScript, 'event.key !== "Escape"') !== false,
        'starter-reference nested navigation has responsive and keyboard interaction contracts'
    );

    $heroContext = red_public_legacy_hero_context_from_rows(
        true,
        [
            [
                'Title' => '<b>First</b> &amp; Co',
                'Alias' => 'first-story',
                'Sections' => 'home',
                'Categories' => '',
                'SubCategories' => '',
                'LongDesc' => '<p>Body</p>',
                'SliderDesc' => 'Hello <em>world</em>',
                'Link' => '',
                'NewWindow' => '',
                'BigPict' => 'first.jpg',
                'ExpDate' => '2026-07-15 12:00:00',
            ],
            [
                'Title' => 'Second "Slide"',
                'Alias' => 'second-story',
                'Sections' => 'contacto',
                'Categories' => 'news',
                'SubCategories' => '',
                'LongDesc' => '<p>Body</p>',
                'SliderDesc' => 'Stored &amp; external',
                'Link' => 'https://example.test/story?x=1&y=2',
                'NewWindow' => 'Y',
                'BigPict' => 'second&.jpg',
                'ExpDate' => '0000-00-00 00:00:00',
            ],
            [
                'Title' => 'Expired',
                'Alias' => 'expired',
                'Sections' => 'home',
                'LongDesc' => '<p>Body</p>',
                'SliderDesc' => 'Expired slide',
                'Link' => '',
                'NewWindow' => '',
                'BigPict' => 'expired.jpg',
                'ExpDate' => '2026-07-13 12:00:00',
            ],
        ],
        strtotime('2026-07-14 12:00:00 America/New_York')
    );
    red_theme_test_assert($heroContext['enabled'], 'legacy hero context preserves the enabled state');
    red_theme_test_assert(
        count($heroContext['slides']) === 2,
        'legacy hero context filters expired slides'
    );
    red_theme_test_assert(
        ($heroContext['slides'][0]['link'] ?? '') === '/first-story',
        'legacy hero context preserves home article links'
    );
    red_theme_test_assert(
        ($heroContext['slides'][1]['link'] ?? '') === 'https://example.test/story?x=1&y=2'
            && ($heroContext['slides'][1]['target'] ?? '') === '_blank',
        'legacy hero context preserves stored link overrides and new-window targets'
    );
    $redThemeHeroContext = $heroContext;
    ob_start();
    require dirname(__DIR__) . '/themes/legacy-bootstrap/partials/hero.php';
    $heroHtml = ob_get_clean();
    red_theme_test_assert(
        strpos($heroHtml, 'id="red-hero-slider" class="carousel slide red-hero__carousel"') !== false
            && substr_count($heroHtml, 'class="carousel-item red-hero__slide') === 2
            && strpos($heroHtml, 'src="/images/articles/first.jpg"') !== false
            && strpos($heroHtml, 'src="/images/articles/second%26.jpg"') !== false
            && strpos($heroHtml, 'First &amp; Co') !== false
            && strpos($heroHtml, 'Second &quot;Slide&quot;') !== false
            && strpos($heroHtml, 'href="https://example.test/story?x=1&amp;y=2"') !== false
            && strpos($heroHtml, 'target="_blank"') !== false
            && strpos($heroHtml, 'rel="noopener"') !== false
            && strpos($heroHtml, 'data-bs-slide="prev"') !== false
            && strpos($heroHtml, 'data-bs-slide="next"') !== false,
        'legacy hero partial renders every escaped slide through the Bootstrap carousel'
    );
    $redThemeHeroContext = ['enabled' => true, 'slides' => [$heroContext['slides'][0]]];
    ob_start();
    require dirname(__DIR__) . '/themes/legacy-bootstrap/partials/hero.php';
    $singleHeroHtml = ob_get_clean();
    red_theme_test_assert(
        substr_count($singleHeroHtml, 'class="carousel-item red-hero__slide') === 1
            && strpos($singleHeroHtml, 'data-bs-slide="prev"') === false
            && strpos($singleHeroHtml, 'data-bs-slide="next"') === false
            && strpos($singleHeroHtml, 'carousel-indicators') === false,
        'single-slide legacy hero omits redundant carousel controls'
    );
    $redThemeHeroContext = red_public_legacy_hero_context_from_rows(true, []);
    ob_start();
    require dirname(__DIR__) . '/themes/legacy-bootstrap/partials/hero.php';
    red_theme_test_assert(ob_get_clean() === '', 'enabled legacy hero with no slides renders no empty wrapper');
    $redThemeHeroContext = red_public_legacy_hero_context_from_rows(false, []);
    ob_start();
    require dirname(__DIR__) . '/themes/legacy-bootstrap/partials/hero.php';
    red_theme_test_assert(ob_get_clean() === '', 'disabled legacy hero renders no markup');

    $redThemeHeroContext = [
        'mode' => 'production',
        'enabled' => true,
        'slides' => $heroContext['slides'],
    ];
    ob_start();
    require dirname(__DIR__) . '/themes/starter-reference/partials/production-hero.php';
    $starterHeroHtml = ob_get_clean();
    red_theme_test_assert(
        strpos($starterHeroHtml, 'data-starter-hero-slider') !== false
            && substr_count($starterHeroHtml, 'class="starter-hero__slide') === 2
            && strpos($starterHeroHtml, 'src="/images/articles/first.jpg"') !== false
            && strpos($starterHeroHtml, 'src="/images/articles/second%26.jpg"') !== false
            && strpos($starterHeroHtml, 'Second &quot;Slide&quot;') !== false
            && strpos($starterHeroHtml, 'data-starter-hero-previous') !== false
            && strpos($starterHeroHtml, 'data-starter-hero-next') !== false
            && strpos($starterHeroHtml, 'data-starter-hero-go-to="1"') !== false
            && strpos($starterHeroHtml, 'rel="noopener"') !== false,
        'standard production hero renders every escaped slide with accessible controls'
    );
    red_theme_test_assert(
        substr_count($starterHeroHtml, ' hidden') === 1
            && substr_count($starterHeroHtml, 'aria-roledescription="diapositiva"') === 2,
        'standard production hero exposes one active slide and a no-script first-slide fallback'
    );
    $starterSliderSource = file_get_contents(
        dirname(__DIR__) . '/themes/starter-reference/assets/js/hero-slider.js'
    );
    red_theme_test_assert(
        is_string($starterSliderSource)
            && strpos($starterSliderSource, '[data-starter-hero-slide]') !== false
            && strpos($starterSliderSource, 'ArrowLeft') !== false
            && strpos($starterSliderSource, 'ArrowRight') !== false
            && strpos($starterSliderSource, 'data-slider-ready') !== false,
        'starter hero controller supports buttons, direct selection, and keyboard navigation'
    );
    $redThemeHeroContext = ['mode' => 'production', 'enabled' => true, 'slides' => []];
    ob_start();
    require dirname(__DIR__) . '/themes/starter-reference/partials/production-hero.php';
    red_theme_test_assert(ob_get_clean() === '', 'standard production hero renders no empty wrapper');

    $valid = red_theme_validate_manifest('example-theme', $projectRoot);
    red_theme_test_assert($valid['valid'], 'valid standard theme accepted');
    red_theme_test_assert(count(red_theme_discover($projectRoot)) === 1, 'theme discovery returns one theme');

    $fallback = red_theme_resolve('missing-theme', $projectRoot, 'example-theme');
    red_theme_test_assert($fallback['valid'], 'valid fallback resolves');
    red_theme_test_assert($fallback['usedFallback'], 'fallback use is reported');
    red_theme_test_assert(!empty($fallback['requestedErrors']), 'requested-theme errors are retained');

    $invalid = $manifest;
    $invalid['regions']['header']['template'] = '../view.php';
    red_theme_test_write_manifest($themeDirectory, $invalid);
    red_theme_test_assert(
        !red_theme_validate_manifest('example-theme', $projectRoot)['valid'],
        'template traversal fails validation'
    );

    $invalid = $manifest;
    $invalid['layouts']['full-width']['positions'][] = ['id' => 1, 'label' => 'Duplicate'];
    red_theme_test_write_manifest($themeDirectory, $invalid);
    red_theme_test_assert(
        !red_theme_validate_manifest('example-theme', $projectRoot)['valid'],
        'duplicate layout position fails validation'
    );

    $invalid = $manifest;
    unset($invalid['components']['Gallery']);
    red_theme_test_write_manifest($themeDirectory, $invalid);
    red_theme_test_assert(
        !red_theme_validate_manifest('example-theme', $projectRoot)['valid'],
        'missing required component fails validation'
    );

    $invalid = $manifest;
    $invalid['assets']['scripts'][] = [
        'id' => 'insecure-script',
        'url' => 'http://example.com/theme.js',
        'location' => 'head',
    ];
    red_theme_test_write_manifest($themeDirectory, $invalid);
    red_theme_test_assert(
        !red_theme_validate_manifest('example-theme', $projectRoot)['valid'],
        'non-HTTPS external script fails validation'
    );

    $invalid = $manifest;
    $invalid['settings'][0]['type'] = 'php';
    red_theme_test_write_manifest($themeDirectory, $invalid);
    red_theme_test_assert(
        !red_theme_validate_manifest('example-theme', $projectRoot)['valid'],
        'unsupported setting type fails validation'
    );

    $invalid = $manifest;
    $invalid['settings'][] = [
        'key' => 'navigation.breadcrumbs',
        'label' => 'Show breadcrumbs',
        'type' => 'checkbox',
        'default' => 'false',
    ];
    red_theme_test_write_manifest($themeDirectory, $invalid);
    red_theme_test_assert(
        !red_theme_validate_manifest('example-theme', $projectRoot)['valid'],
        'reserved breadcrumb setting rejects a non-boolean default'
    );

    $invalid = $manifest;
    $invalid['id'] = 'wrong-theme';
    red_theme_test_write_manifest($themeDirectory, $invalid);
    red_theme_test_assert(
        !red_theme_validate_manifest('example-theme', $projectRoot)['valid'],
        'manifest and directory id mismatch fails validation'
    );

    red_theme_test_write_manifest($themeDirectory, $manifest);
    file_put_contents($outsideRoot . '/theme.json', json_encode($manifest));
    $linkedTheme = $projectRoot . '/themes/escaped-theme';
    if (function_exists('symlink') && @symlink($outsideRoot, $linkedTheme)) {
        red_theme_test_assert(
            !red_theme_validate_manifest('escaped-theme', $projectRoot)['valid'],
            'theme-directory symbolic-link escape fails validation'
        );
    }

    $standardThemeRejected = false;
    try {
        red_theme_runtime_bootstrap('example-theme', $projectRoot, 'example-theme');
    } catch (RuntimeException $exception) {
        $standardThemeRejected = true;
    }
    red_theme_test_assert(
        $standardThemeRejected,
        'compatibility runtime refuses to execute a standard theme'
    );

    $runtime = red_theme_runtime_bootstrap('missing-theme', dirname(__DIR__));
    red_theme_test_assert($runtime['themeId'] === 'legacy-bootstrap', 'runtime selects the hard fallback theme');
    red_theme_test_assert(
        !empty($runtime['resolution']['usedFallback']),
        'runtime reports use of the hard fallback'
    );
    red_theme_test_assert(
        $runtime['adapter'] instanceof RedLegacyBootstrapThemeAdapter,
        'runtime constructs the declared compatibility adapter'
    );
    red_theme_test_assert(
        is_callable([$runtime['adapter'], 'renderDocumentStart']),
        'runtime adapter exposes the document-start boundary'
    );
    red_theme_test_assert(
        is_callable([$runtime['adapter'], 'renderDocumentEnd']),
        'runtime adapter exposes the document-end boundary'
    );
    red_theme_test_assert(
        is_callable([$runtime['adapter'], 'renderPublicLayout']),
        'runtime adapter exposes the validated public-layout boundary'
    );
    red_theme_test_assert(
        is_callable([$runtime['adapter'], 'renderPublicArticleComponent']),
        'runtime adapter exposes the validated public Article component-view boundary'
    );
    red_theme_test_assert(
        is_callable([$runtime['adapter'], 'renderPublicFormComponent']),
        'runtime adapter exposes the validated public Form component-view boundary'
    );
    red_theme_test_assert(
        is_callable([$runtime['adapter'], 'renderPublicGalleryComponent']),
        'runtime adapter exposes the validated public Gallery component-view boundary'
    );
    red_theme_test_assert(
        is_callable([$runtime['adapter'], 'renderPublicOtherComponent']),
        'runtime adapter exposes the validated public Other component-view boundary'
    );
    red_theme_test_assert(
        ($runtime['manifest']['regions']['header']['legacySource'] ?? '') ===
            'themes/legacy-bootstrap/partials/header.php',
        'runtime manifest selects the theme-owned header partial'
    );
    red_theme_test_assert(
        ($runtime['manifest']['regions']['footer']['legacySource'] ?? '') ===
            'themes/legacy-bootstrap/partials/footer.php',
        'runtime manifest selects the theme-owned footer partial'
    );
    red_theme_test_assert(
        ($runtime['manifest']['regions']['navigation']['legacySource'] ?? '') ===
            'themes/legacy-bootstrap/partials/navigation.php',
        'runtime manifest selects the theme-owned navigation partial'
    );
    red_theme_test_assert(
        ($runtime['manifest']['regions']['hero']['legacySource'] ?? '') ===
            'themes/legacy-bootstrap/partials/hero.php',
        'runtime manifest selects the theme-owned hero partial'
    );
    red_theme_test_assert(
        ($runtime['manifest']['version'] ?? '') === '2.5.0',
        'runtime selects the layout-alias-aware compatibility package version'
    );
    red_theme_test_assert(
        is_callable([$runtime['adapter'], 'supportsPublicLayout'])
            && $runtime['adapter']->supportsPublicLayout('Full-Width'),
        'legacy runtime accepts the explicitly declared stored Full-Width id'
    );
    red_theme_test_assert(
        is_callable([$runtime['adapter'], 'resolvePublicLayoutId'])
            && $runtime['adapter']->resolvePublicLayoutId('Full-Width') === 'index-2',
        'legacy runtime resolves Full-Width only to its declared canonical layout'
    );
    $runtimeComponentContractsAreNonExecutable = true;
    foreach (($runtime['manifest']['components'] ?? []) as $componentContract) {
        if (array_intersect(
            ['class', 'method', 'renderer', 'callback', 'callable'],
            array_keys($componentContract)
        )) {
            $runtimeComponentContractsAreNonExecutable = false;
        }
    }
    red_theme_test_assert(
        $runtimeComponentContractsAreNonExecutable,
        'runtime manifest contains no executable public component mapping'
    );
    $runtimeComponentSources = [];
    foreach (($runtime['manifest']['components'] ?? []) as $componentId => $componentContract) {
        $runtimeComponentSources[$componentId] = $componentContract['legacySource'] ?? '';
    }
    red_theme_test_assert(
        $runtimeComponentSources === [
            'Article' => 'themes/legacy-bootstrap/components/article.php',
            'Form' => 'themes/legacy-bootstrap/components/form.php',
            'Gallery' => 'themes/legacy-bootstrap/components/gallery.php',
            'Other' => 'themes/legacy-bootstrap/components/other.php',
        ],
        'runtime manifest selects all four theme-owned public component views'
    );
    $malformedArticleInputsRejected = false;
    try {
        $runtime['adapter']->renderPublicArticleComponent(['recordId' => '123']);
    } catch (InvalidArgumentException $exception) {
        $malformedArticleInputsRejected = true;
    }
    red_theme_test_assert(
        $malformedArticleInputsRejected,
        'runtime adapter rejects malformed public Article component inputs before data access'
    );
    $malformedFormInputsRejected = false;
    try {
        $runtime['adapter']->renderPublicFormComponent(['recordId' => '123', 'extra' => true]);
    } catch (InvalidArgumentException $exception) {
        $malformedFormInputsRejected = true;
    }
    red_theme_test_assert(
        $malformedFormInputsRejected,
        'runtime adapter rejects malformed public Form component inputs before data access'
    );
    $malformedGalleryInputsRejected = false;
    try {
        $runtime['adapter']->renderPublicGalleryComponent(['recordId' => '123']);
    } catch (InvalidArgumentException $exception) {
        $malformedGalleryInputsRejected = true;
    }
    red_theme_test_assert(
        $malformedGalleryInputsRejected,
        'runtime adapter rejects malformed public Gallery component inputs before data access'
    );
    $malformedOtherInputsRejected = false;
    try {
        $runtime['adapter']->renderPublicOtherComponent(['recordId' => '123']);
    } catch (InvalidArgumentException $exception) {
        $malformedOtherInputsRejected = true;
    }
    red_theme_test_assert(
        $malformedOtherInputsRejected,
        'runtime adapter rejects malformed public Other component inputs before data access'
    );
    $runtimeLayoutSources = [];
    foreach (($runtime['manifest']['layouts'] ?? []) as $layoutId => $layoutContract) {
        $runtimeLayoutSources[$layoutId] = $layoutContract['legacySource'] ?? '';
    }
    red_theme_test_assert(
        $runtimeLayoutSources === [
            'index' => 'themes/legacy-bootstrap/layouts/index.php',
            'index-1' => 'themes/legacy-bootstrap/layouts/index-1.php',
            'index-2' => 'themes/legacy-bootstrap/layouts/index-2.php',
            'index-3' => 'themes/legacy-bootstrap/layouts/index-3.php',
        ],
        'runtime manifest selects the four theme-owned public layout views'
    );
    $unsupportedPublicLayoutRejected = false;
    try {
        $runtime['adapter']->renderPublicLayout('index-4', null, null, null, null);
    } catch (InvalidArgumentException $exception) {
        $unsupportedPublicLayoutRejected = true;
    }
    red_theme_test_assert(
        $unsupportedPublicLayoutRejected,
        'runtime adapter rejects an undeclared public layout view'
    );
    $runtimeLayoutSlots = [];
    foreach (($runtime['manifest']['layouts'] ?? []) as $layoutId => $layoutContract) {
        $publicPositions = array_map(
            function ($position) {
                return (string) ($position['id'] ?? '');
            },
            $layoutContract['positions'] ?? []
        );
        $runtimeLayoutSlots[$layoutId] = [
            'public' => $publicPositions,
            'control-panel' => array_merge(
                $publicPositions,
                [(string) ($layoutContract['hiddenPosition'] ?? '')]
            ),
        ];
    }
    $preparedLayoutSlots = [];
    foreach ($layoutSlotInventory as $layoutId => $layoutContract) {
        $preparedLayoutSlots[$layoutId] = [
            'public' => $layoutContract['public']['positions'],
            'control-panel' => $layoutContract['control-panel']['positions'],
        ];
    }
    red_theme_test_assert(
        $runtimeLayoutSlots === $preparedLayoutSlots,
        'runtime manifest layout positions match the prepared public and control-panel slot inventory'
    );

    $signedInDocumentContext = [
        'mode' => 'production',
        'phase' => 'start',
        'language' => 'sp',
        'titleHtml' => 'Theme shell test',
        'metaHtml' => '<meta name="red-test-meta" content="present">',
        'headAssetsHtml' => '<link rel="stylesheet" href="/red-test-head.css">',
        'adminOverlayHtml' => '<div id="advanced">Signed-in control panel</div>',
        'themeId' => 'starter-reference',
    ];
    $signedInStarterContext = $signedInDocumentContext;
    $signedInStarterDocument = red_theme_test_render_standard_document_start(
        'themes/starter-reference/templates/production-page.php',
        $signedInStarterContext
    );
    $signedInStarterHeadEnd = strpos($signedInStarterDocument, '</head>');
    $signedInStarterBody = strpos($signedInStarterDocument, '<body');
    $signedInStarterAdmin = strpos($signedInStarterDocument, 'id="advanced"');
    red_theme_test_assert(
        $signedInStarterHeadEnd !== false
            && $signedInStarterBody !== false
            && $signedInStarterAdmin !== false
            && $signedInStarterHeadEnd < $signedInStarterBody
            && $signedInStarterBody < $signedInStarterAdmin,
        'signed-in starter document places the control panel after body start'
    );
    red_theme_test_assert(
        strpos($signedInStarterDocument, 'red-standard-theme--with-admin') !== false,
        'signed-in starter document exposes the authenticated theme-state class'
    );
    red_theme_test_assert(
        strpos(substr($signedInStarterDocument, 0, $signedInStarterHeadEnd), 'id="advanced"') === false,
        'signed-in starter document keeps control-panel markup out of the document head'
    );

    $controlPanelCss = file_get_contents(dirname(__DIR__) . '/admin/assets/css/cp.css');
    red_theme_test_assert(
        is_string($controlPanelCss)
            && preg_match('/(^|})\s*\.wrapper\s*\{/m', $controlPanelCss) !== 1,
        'control-panel CSS does not override every theme wrapper globally'
    );
    red_theme_test_assert(
        is_string($controlPanelCss)
            && preg_match('/#advanced\s+\.wrapper\s*,\s*\.cp\s+\.wrapper\s*\{[^}]*width\s*:\s*100%/s', $controlPanelCss) === 1,
        'control-panel wrapper reset is limited to the admin panel and admin forms'
    );

    $starterManifest = json_decode(
        (string) file_get_contents(dirname(__DIR__) . '/themes/starter-reference/theme.json'),
        true
    );
    $legacyManifest = json_decode(
        (string) file_get_contents(dirname(__DIR__) . '/themes/legacy-bootstrap/theme.json'),
        true
    );
    red_theme_test_assert(
        is_array($starterManifest) && red_theme_standard_breadcrumbs_enabled($starterManifest),
        'starter theme preserves the backwards-compatible breadcrumb default'
    );
    $layoutPreviewCatalogs = [
        'legacy-bootstrap' => red_theme_layout_manifest_catalog($legacyManifest),
        'starter-reference' => red_theme_layout_manifest_catalog($starterManifest),
    ];
    $layoutPreviewCoverageIsExact = true;
    $installedLayoutGeometryIsDeclared = true;
    foreach ($layoutPreviewCatalogs as $layoutPreviewCatalog) {
        foreach ($layoutPreviewCatalog as $layoutDefinition) {
            $previewPositionIds = [];
            foreach ($layoutDefinition['previewRows'] as $previewRow) {
                $previewPositionIds = array_merge($previewPositionIds, array_column($previewRow, 'position'));
            }
            if ($previewPositionIds !== array_keys($layoutDefinition['positions'])) {
                $layoutPreviewCoverageIsExact = false;
            }
            if (!empty($layoutDefinition['previewIsFallback'])) {
                $installedLayoutGeometryIsDeclared = false;
            }
        }
    }
    red_theme_test_assert(
        $layoutPreviewCoverageIsExact
            && $installedLayoutGeometryIsDeclared
            && array_map(
                static fn ($row) => array_column($row, 'position'),
                $layoutPreviewCatalogs['legacy-bootstrap']['index']['previewRows']
            ) === [[1, 2], [3]]
            && array_map(
                static fn ($row) => array_column($row, 'position'),
                $layoutPreviewCatalogs['starter-reference']['feature-grid']['previewRows']
            ) === [[1], [2, 3, 4], [5]],
        'all installed themes expose complete automatic layout maps with exact column and row groupings'
    );
    $standardAdapterSource = file_get_contents(
        dirname(__DIR__) . '/includes/theme_standard_adapter.php'
    );
    $publicLayoutOffset = is_string($standardAdapterSource)
        ? strpos($standardAdapterSource, 'public function renderPublicLayout')
        : false;
    $controlPanelLayoutOffset = is_string($standardAdapterSource)
        ? strpos($standardAdapterSource, 'public function renderControlPanelLayout')
        : false;
    $controlPanelWorkspaceOffset = is_string($standardAdapterSource)
        ? strpos($standardAdapterSource, 'private function renderControlPanelWorkspace')
        : false;
    $publicLayoutSource = $publicLayoutOffset !== false && $controlPanelLayoutOffset !== false
        ? substr($standardAdapterSource, $publicLayoutOffset, $controlPanelLayoutOffset - $publicLayoutOffset)
        : '';
    $controlPanelLayoutSource = $controlPanelLayoutOffset !== false
        ? substr($standardAdapterSource, $controlPanelLayoutOffset)
        : '';
    $controlPanelWorkspaceSource = $controlPanelWorkspaceOffset !== false && $controlPanelLayoutOffset !== false
        ? substr(
            $standardAdapterSource,
            $controlPanelWorkspaceOffset,
            $controlPanelLayoutOffset - $controlPanelWorkspaceOffset
        )
        : '';
    red_theme_test_assert(
        str_contains($publicLayoutSource, "'breadcrumb' => " . '$this->breadcrumbContext(),')
            && str_contains($publicLayoutSource, 'require $this->layoutSources[$resolvedLayoutId]')
            && str_contains($controlPanelWorkspaceSource, 'red-admin-workspace')
            && str_contains($controlPanelWorkspaceSource, 'data-red-editor-position')
            && str_contains($controlPanelLayoutSource, 'renderControlPanelWorkspace(')
            && !str_contains($controlPanelLayoutSource, '$redThemeLayoutContext')
            && !str_contains($controlPanelLayoutSource, 'require $this->layoutSources[$resolvedLayoutId]'),
        'standard themes preserve public layouts while every control panel uses the core-owned compact workspace'
    );
    $controlPanelCss = file_get_contents(dirname(__DIR__) . '/admin/assets/css/cp.css');
    $controlPanelNavigationSource = file_get_contents(dirname(__DIR__) . '/admin/mainnav.php');
    red_theme_test_assert(
        is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced #edit_content_grid .red-admin-workspace')
            && str_contains($controlPanelCss, '.red-admin-position__controls--hidden.cp_admin')
            && str_contains($controlPanelCss, '@media only screen and (max-width: 767px)')
            && is_string($controlPanelNavigationSource)
            && str_contains(
                $controlPanelNavigationSource,
                "filemtime(__DIR__ . '/assets/css/cp.css')"
            ),
        'core control-panel CSS keeps the compact editor scoped, responsive, and cache-safe'
    );
    $layoutEditorSource = file_get_contents(dirname(__DIR__) . '/admin/class/class_edit_layout.php');
    $layoutPreviewScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/layout-preview.js');
    $themeManifestSchema = file_get_contents(dirname(__DIR__) . '/docs/theme-manifest.schema.json');
    red_theme_test_assert(
        is_string($layoutEditorSource)
            && str_contains($layoutEditorSource, 'id="update_layout" name="update_layout"')
            && str_contains($layoutEditorSource, 'name="Layout" id="layout"')
            && str_contains($layoutEditorSource, 'onChange="return run_update_layout(update_layout);"')
            && str_contains($layoutEditorSource, 'url: "/admin/bin/update_layout.php"')
            && str_contains($layoutEditorSource, 'data: $("#update_layout").serialize()')
            && str_contains($layoutEditorSource, 'window.location.reload()'),
        'layout-map enhancement preserves the native selector and exact authenticated update contract'
    );
    red_theme_test_assert(
        is_string($layoutEditorSource)
            && str_contains($layoutEditorSource, 'data-layout-preview-trigger')
            && str_contains($layoutEditorSource, 'aria-expanded="false"')
            && str_contains($layoutEditorSource, 'data-layout-preview-panel role="region"')
            && str_contains($layoutEditorSource, 'Desktop structure; columns stack on smaller screens.')
            && str_contains($layoutEditorSource, 'Exact desktop geometry is not declared; positions follow manifest order.')
            && str_contains($layoutEditorSource, "'Position order' : 'Desktop layout'")
            && str_contains($layoutEditorSource, 'Visual map unavailable. The saved layout assignment is preserved.')
            && !str_contains($layoutEditorSource, '<iframe')
            && !str_contains($layoutEditorSource, '<img')
            && is_string($layoutPreviewScript)
            && str_contains($layoutPreviewScript, "root.addEventListener('mouseenter'")
            && str_contains($layoutPreviewScript, "root.addEventListener('focusin'")
            && str_contains($layoutPreviewScript, "trigger.addEventListener('click'")
            && str_contains($layoutPreviewScript, "event.key !== 'Escape'")
            && str_contains($layoutPreviewScript, "document.addEventListener('pointerdown'")
            && is_string($controlPanelNavigationSource)
            && str_contains($controlPanelNavigationSource, "filemtime(__DIR__ . '/assets/js/layout-preview.js')")
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-layout-preview__panel[hidden]')
            && str_contains($controlPanelCss, '#advanced .red-admin-layout-preview__trigger:focus-visible')
            && str_contains($controlPanelCss, 'width: min(288px, calc(100vw - 32px))')
            && is_string($themeManifestSchema)
            && str_contains($themeManifestSchema, '"adminPreview"')
            && str_contains($themeManifestSchema, '"layoutAdminPreviewCell"'),
        'layout selector exposes an accessible responsive blueprint popover with schema-backed geometry and no theme iframe'
    );
    $adminBreadcrumbHelperSource = file_get_contents(
        dirname(__DIR__) . '/includes/admin_breadcrumb_helpers.php'
    );
    red_theme_test_assert(
        is_string($controlPanelNavigationSource)
            && str_contains($controlPanelNavigationSource, 'red_admin_breadcrumb_items()')
            && str_contains($controlPanelNavigationSource, 'class="red-admin-breadcrumb"')
            && str_contains($controlPanelNavigationSource, 'aria-current="page"')
            && is_string($adminBreadcrumbHelperSource)
            && str_contains($adminBreadcrumbHelperSource, 'red_public_breadcrumb_title')
            && !str_contains($adminBreadcrumbHelperSource, 'red_theme_standard_breadcrumbs_enabled')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-pagebar'),
        'administrator breadcrumb stays compact and independent from public theme breadcrumb settings'
    );
    red_theme_test_assert(
        is_string($controlPanelNavigationSource)
            && str_contains($controlPanelNavigationSource, 'class="red-admin-logout"')
            && str_contains($controlPanelNavigationSource, 'href="/bin/logout.php?logout"')
            && str_contains($controlPanelNavigationSource, 'aria-label="Log out of RED-CMS"')
            && !str_contains($controlPanelNavigationSource, '/admin/images/logout.png')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-logout:focus-visible')
            && preg_match('/red-admin-workspace__title strong\s*\{[^}]*color\s*:\s*#fff/s', $controlPanelCss) === 1
            && preg_match('/red-admin-workspace__eyebrow\s*\{[^}]*color\s*:\s*#d7e0e8/s', $controlPanelCss) === 1,
        'administrator session controls and workspace heading text retain compact high-contrast styling'
    );
    red_theme_test_assert(
        is_string($controlPanelNavigationSource)
            && str_contains($controlPanelNavigationSource, 'class="red-admin-session-identity"')
            && str_contains($controlPanelNavigationSource, "\$_SESSION['AdminUsername']")
            && str_contains($controlPanelNavigationSource, "\$_SESSION['AdminType']")
            && str_contains($controlPanelNavigationSource, 'function redAdminOpenCurrentUser(recordId)')
            && str_contains($controlPanelNavigationSource, 'data: {view: "user", RecordID: recordId}')
            && str_contains($controlPanelNavigationSource, 'aria-label="Edit the signed-in administrator account"')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-session-identity__avatar')
            && str_contains($controlPanelCss, '#advanced .red-admin-session-identity__edit:focus-visible'),
        'signed-in identity exposes display name, username, role, and a protected current-account editor'
    );
    red_theme_test_assert(
        is_string($controlPanelNavigationSource)
            && substr_count($controlPanelNavigationSource, 'class="red-admin-disclosure"') === 5
            && substr_count($controlPanelNavigationSource, 'aria-expanded="false"') === 5
            && str_contains($controlPanelNavigationSource, 'class="nav red-admin-local-tabs"')
            && str_contains($controlPanelNavigationSource, '$(".cp1_slideDown .red-admin-disclosure")')
            && !str_contains($controlPanelNavigationSource, '$(".cp1_slideDown dx").click')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-disclosure__copy')
            && str_contains($controlPanelCss, '#advanced .red-admin-local-tabs > li.selected > a')
            && preg_match('/@media \(max-width: 700px\)\s*\{[^}]*#advanced \.red-admin-sessionbar\.wrapper/s', $controlPanelCss) === 1
            && str_contains($controlPanelCss, '@media (prefers-reduced-motion: reduce)'),
        'main administrator sections use accessible disclosures and responsive local action tabs'
    );
    red_theme_test_assert(
        is_string($controlPanelNavigationSource)
            && !str_contains($controlPanelNavigationSource, 'href="#addadvanced"')
            && !str_contains($controlPanelNavigationSource, 'class_new_advanced.php')
            && !str_contains($controlPanelNavigationSource, 'new newadvanced()')
            && str_contains($controlPanelNavigationSource, 'href="#editadvanced"'),
        'Advanced settings no longer expose the incomplete language-row seeder while preserving the existing editor'
    );
    $addContentSource = file_get_contents(dirname(__DIR__) . '/admin/class/class_add_menu.php');
    red_theme_test_assert(
        is_string($addContentSource)
            && str_contains($addContentSource, 'usort($components')
            && str_contains($addContentSource, 'strnatcasecmp(')
            && str_contains($addContentSource, 'class="wrapper red-admin-add-content__grid" role="list"')
            && str_contains($addContentSource, 'class="cp_addcontent_button red-admin-add-card__link"')
            && str_contains($addContentSource, 'aria-label="Add ')
            && str_contains($addContentSource, 'url: "/admin/bin/new_')
            && str_contains($addContentSource, 'data: {Type: contenttype, CountPage: ')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, 'grid-template-columns: repeat(auto-fit, minmax(250px, 1fr))')
            && str_contains($controlPanelCss, '.red-admin-add-card__link:focus-visible')
            && str_contains($controlPanelCss, '@media (prefers-reduced-motion: reduce)'),
        'Add Content stays permission-driven and AJAX-compatible while exposing an alphabetized responsive icon-card chooser'
    );
    $articleComponentSource = file_get_contents(dirname(__DIR__) . '/class/class_article.php');
    $otherComponentSource = file_get_contents(dirname(__DIR__) . '/class/class_other.php');
    $formComponentSource = file_get_contents(dirname(__DIR__) . '/class/class_forms.php');
    $galleryComponentSource = file_get_contents(dirname(__DIR__) . '/class/class_gallery.php');
    red_theme_test_assert(
        is_string($articleComponentSource)
            && substr_count($articleComponentSource, 'red-admin-component-action--article') === 2
            && is_string($otherComponentSource)
            && substr_count($otherComponentSource, 'red-admin-component-action--other') === 2
            && is_string($formComponentSource)
            && str_contains($formComponentSource, "? 'form-login'")
            && str_contains($formComponentSource, ": 'form-builder'")
            && substr_count($formComponentSource, 'red-admin-component-action--') === 2
            && is_string($galleryComponentSource)
            && str_contains($galleryComponentSource, "['banner', 'gallery', 'video']")
            && substr_count($galleryComponentSource, 'red-admin-component-action--') === 2
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, 'input.red-admin-component-action--article')
            && str_contains($controlPanelCss, 'input.red-admin-component-action--banner')
            && str_contains($controlPanelCss, 'input.red-admin-component-action--form-builder')
            && str_contains($controlPanelCss, 'input.red-admin-component-action--form-login')
            && str_contains($controlPanelCss, 'input.red-admin-component-action--gallery')
            && str_contains($controlPanelCss, 'input.red-admin-component-action--other')
            && str_contains($controlPanelCss, 'input.red-admin-component-action--video'),
        'Edit Content component actions reuse the exact Add Content palette, including Gallery subtypes and Admin Login'
    );
    $contentToolsSource = file_get_contents(dirname(__DIR__) . '/admin/class/class_add_tools.php');
    red_theme_test_assert(
        is_string($contentToolsSource)
            && str_contains($contentToolsSource, 'usort($tools')
            && str_contains($contentToolsSource, 'class="red-admin-card-chooser"')
            && str_contains($contentToolsSource, 'class="wrapper red-admin-add-content__grid" role="list" aria-label="Available tools"')
            && str_contains($contentToolsSource, 'class="cp_addcontent_button red-admin-add-card__link"')
            && str_contains($contentToolsSource, 'aria-label="Open ')
            && str_contains($contentToolsSource, 'href="#cp_')
            && str_contains($contentToolsSource, 'url: "/admin/bin/tool_')
            && str_contains($contentToolsSource, 'cparea: ')
            && str_contains($contentToolsSource, 'compgroup: ')
            && !str_contains($contentToolsSource, 'id="cp_tools"')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-card-chooser')
            && str_contains($controlPanelCss, '.red-admin-add-card--move')
            && str_contains($controlPanelCss, '.red-admin-tools-content .red-admin-add-content__grid.wrapper'),
        'Tools reuses the responsive action-card chooser while preserving its permission-driven AJAX contract'
    );
    $moveToolSource = file_get_contents(dirname(__DIR__) . '/admin/bin/tool_movecontent.php');
    $moveRunSource = file_get_contents(dirname(__DIR__) . '/admin/bin/run_tool_movecontent.php');
    $moveScriptSource = file_get_contents(dirname(__DIR__) . '/admin/assets/js/move-content.js');
    $areaHierarchyScriptSource = file_get_contents(dirname(__DIR__) . '/admin/assets/js/area-hierarchy.js');
    $adminAreaHelperSource = file_get_contents(dirname(__DIR__) . '/includes/admin_area_helpers.php');
    $adminArticleHelperSource = file_get_contents(dirname(__DIR__) . '/includes/admin_article_helpers.php');
    $publicRenderHelperSource = file_get_contents(dirname(__DIR__) . '/includes/public_render_helpers.php');
    $areaParentMigrationSource = file_get_contents(
        dirname(__DIR__) . '/database/migrations/2026-07-23-area-parent-relationships.sql'
    );
    $installerSource = file_get_contents(dirname(__DIR__) . '/db-structure.sql');
    red_theme_test_assert(
        is_string($moveToolSource)
            && str_contains($moveToolSource, 'data-red-move-content')
            && str_contains($moveToolSource, 'red_admin_tool_move_destination_catalog')
            && str_contains($moveToolSource, 'name="SourcePositionColumn"')
            && str_contains($moveToolSource, 'data-red-move-map')
            && str_contains($moveToolSource, 'name="Position"')
            && !str_contains($moveToolSource, 'red_admin_tool_all_layout_position_options')
            && is_string($moveRunSource)
            && str_contains($moveRunSource, 'red_admin_tool_move_articles_update')
            && is_string($moveScriptSource)
            && str_contains($moveScriptSource, 'populateArticleSelect')
            && str_contains($moveScriptSource, 'data-red-move-position-shortcut')
            && str_contains($moveScriptSource, 'MutationObserver')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '.red-admin-move-destination-grid')
            && str_contains($controlPanelCss, '.red-admin-move-map__cell[aria-pressed="true"]'),
        'Move Content exposes a progressive, AJAX-safe destination workflow with exact layout positions and an interactive responsive map'
    );
    red_theme_test_assert(
        is_string($areaHierarchyScriptSource)
            && str_contains($areaHierarchyScriptSource, 'dataset.parentSection')
            && str_contains($areaHierarchyScriptSource, 'dataset.parentCategory')
            && str_contains($areaHierarchyScriptSource, "form.matches('[data-red-move-content]')")
            && is_string($moveScriptSource)
            && str_contains($moveScriptSource, 'same(row.section, sectionValue)')
            && str_contains($moveScriptSource, 'same(row.category, categoryValue)')
            && is_string($adminAreaHelperSource)
            && str_contains($adminAreaHelperSource, "'RED_Categories' => 'SectionRecordID'")
            && str_contains($adminAreaHelperSource, "'RED_SubCategories' => 'CategoryRecordID'")
            && str_contains($adminAreaHelperSource, 'red_admin_area_child_count')
            && is_string($adminArticleHelperSource)
            && str_contains($adminArticleHelperSource, 'red_admin_article_hierarchy_valid')
            && str_contains($adminArticleHelperSource, 'section_area.RecordID=category_area.SectionRecordID')
            && is_string($publicRenderHelperSource)
            && str_contains($publicRenderHelperSource, 'area_row.SectionRecordID IS NULL')
            && str_contains($publicRenderHelperSource, 'area_row.CategoryRecordID IS NULL')
            && is_string($areaParentMigrationSource)
            && str_contains($areaParentMigrationSource, 'fk_red_categories_section')
            && str_contains($areaParentMigrationSource, 'fk_red_subcategories_category')
            && is_string($installerSource)
            && str_contains($installerSource, '`SectionRecordID` int unsigned DEFAULT NULL')
            && str_contains($installerSource, '`CategoryRecordID` int unsigned DEFAULT NULL'),
        'Category and Subcategory parents remain enforced across schema, CRUD, Article selection, Move Content, and public routing'
    );
    red_theme_test_assert(
        red_admin_area_public_path('home', 'test-category') === '/home/test-category/'
            && red_admin_area_public_path('about', 'test-category', 'test-subcategory')
                === '/about/test-category/test-subcategory/'
            && red_admin_tool_public_article_link('home', '', '', 'welcome') === '/welcome'
            && red_admin_tool_public_article_link(
                'home',
                'test-category',
                'test-subcategory',
                'welcome'
            ) === '/home/test-category/test-subcategory/welcome',
        'Hierarchy path helpers distinguish area routes from Article routes and retain Home when a deeper parent exists'
    );
    red_theme_test_assert(
        red_admin_tool_move_destination_position_column([
            'Sections' => 'about',
            'Categories' => '',
            'SubCategories' => '',
            'Article' => '',
        ]) === 'SectionPosition'
            && red_admin_tool_move_destination_position_column([
                'Sections' => 'about',
                'Categories' => 'stories',
                'SubCategories' => '',
                'Article' => '',
            ]) === 'CategoryPosition'
            && red_admin_tool_move_destination_position_column([
                'Sections' => 'about',
                'Categories' => 'stories',
                'SubCategories' => 'profiles',
                'Article' => '',
            ]) === 'SubCategoryPosition'
            && red_admin_tool_move_destination_position_column([
                'Sections' => 'home',
                'Categories' => '',
                'SubCategories' => '',
                'Article' => '',
            ]) === 'HomePosition'
            && red_admin_tool_move_destination_position_column([
                'Sections' => 'about',
                'Categories' => '',
                'SubCategories' => '',
                'Article' => 'team',
            ]) === 'PagePosition',
        'Move Content retains deepest-target placement precedence across Home, Section, Category, Subcategory, and Article destinations'
    );
    $moveSourceContext = red_admin_tool_move_source_context([
        'SourceCountPage' => '3',
        'SourceSection' => 'about',
        'SourceCategory' => '',
        'SourceSubCategory' => '',
        'SourceArticle' => '',
        'SourceLanguage' => 'sp',
        'SourcePositionColumn' => 'SectionPosition',
        'VarPosition' => 'SectionPosition',
    ]);
    red_theme_test_assert(
        is_array($moveSourceContext)
            && $moveSourceContext['positionColumn'] === 'SectionPosition'
            && red_admin_tool_move_source_context([
                'SourceCountPage' => '3',
                'SourceSection' => 'about',
                'SourceCategory' => '',
                'SourceSubCategory' => '',
                'SourceArticle' => '',
                'SourceLanguage' => 'sp',
                'SourcePositionColumn' => 'PagePosition',
                'VarPosition' => 'PagePosition',
            ]) === null
            && red_admin_tool_move_position_id('0') === 0
            && red_admin_tool_move_position_id('99') === 99
            && red_admin_tool_move_position_id('100') === null
            && red_admin_tool_move_position_id('abc') === null,
        'Move Content verifies its source route placement and accepts only strict position ids from 0 through 99'
    );
    $moveRecordUpdates = red_admin_tool_move_record_updates(
        [
            'HomePosition' => 0,
            'SectionPosition' => 2,
            'CategoryPosition' => 0,
            'SubCategoryPosition' => 0,
            'PagePosition' => 1,
        ],
        [
            'Sections' => 'about',
            'Categories' => '',
            'SubCategories' => '',
            'Article' => '',
        ],
        'PagePosition',
        'SectionPosition',
        1
    );
    red_theme_test_assert(
        $moveRecordUpdates['Sections'] === 'about'
            && $moveRecordUpdates['SectionPosition'] === 1
            && $moveRecordUpdates['PagePosition'] === 0
            && $moveRecordUpdates['Categories'] === ''
            && $moveRecordUpdates['SubCategories'] === ''
            && $moveRecordUpdates['Article'] === '',
        'Move Content clears the source placement and obsolete descendant route metadata when no preserved placement still depends on it'
    );
    $adminListHelperSource = file_get_contents(dirname(__DIR__) . '/includes/admin_list_ui_helpers.php');
    $adminSectionListSource = file_get_contents(dirname(__DIR__) . '/admin/class/class_edit_section.php');
    $adminCategoryListSource = file_get_contents(dirname(__DIR__) . '/admin/class/class_edit_category.php');
    $adminSubcategoryListSource = file_get_contents(dirname(__DIR__) . '/admin/class/class_edit_subcategory.php');
    $adminAdvancedListSource = file_get_contents(dirname(__DIR__) . '/admin/class/class_edit_advanced.php');
    $adminAdvancedEditorEndpointSource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_advanced.php');
    $adminAdvancedEditorUiSource = file_get_contents(dirname(__DIR__) . '/includes/admin_advanced_ui_helpers.php');
    $adminAdvancedEditorScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/advanced-editor.js');
    $adminAdvancedIdentityScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/advanced-identity.js');
    $adminAdvancedLogoScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/advanced-logo.js');
    $adminAdvancedCreditScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/advanced-credit.js');
    $siteLogoHelperSource = file_get_contents(dirname(__DIR__) . '/includes/site_logo_helpers.php');
    $logoUploadSource = file_get_contents(dirname(__DIR__) . '/admin/bin/post_file.php');
    $starterProductionHeaderSource = file_get_contents(dirname(__DIR__) . '/themes/starter-reference/partials/production-header.php');
    $adminInactiveListSource = file_get_contents(dirname(__DIR__) . '/admin/class/class_edit_hiddenarticles.php');
    $adminAreaListSourceBundle = implode('', [
        $adminSectionListSource,
        $adminCategoryListSource,
        $adminSubcategoryListSource,
        $adminAdvancedListSource,
        $adminInactiveListSource,
    ]);
    red_theme_test_assert(
        is_string($adminListHelperSource)
            && str_contains($adminListHelperSource, 'red_admin_list_ui_action_button')
            && str_contains($adminListHelperSource, '<button type="button" class="red-admin-area-list__action"')
            && str_contains($adminListHelperSource, 'red_admin_list_ui_status')
            && is_string($adminSectionListSource)
            && str_contains($adminSectionListSource, 'role="table" aria-label="Sections"')
            && str_contains($adminSectionListSource, 'editsections(')
            && str_contains($adminSectionListSource, 'url: "/admin/bin/edit_section.php"')
            && is_string($adminCategoryListSource)
            && str_contains($adminCategoryListSource, 'editcategories(')
            && str_contains($adminCategoryListSource, 'url: "/admin/bin/edit_category.php"')
            && is_string($adminSubcategoryListSource)
            && str_contains($adminSubcategoryListSource, 'editsubcategories(')
            && str_contains($adminSubcategoryListSource, 'url: "/admin/bin/edit_subcategory.php"')
            && is_string($adminAdvancedListSource)
            && str_contains($adminAdvancedListSource, 'edit_admin_users();')
            && str_contains($adminAdvancedListSource, 'edit_advanced(')
            && str_contains($adminAdvancedListSource, "red_admin_list_ui_action_link('/admin/bin/theme_preview.php'")
            && is_string($adminInactiveListSource)
            && str_contains($adminInactiveListSource, 'role="table" aria-label="Inactive articles"')
            && str_contains($adminInactiveListSource, 'data-red-admin-list="inactive-articles"')
            && str_contains($adminInactiveListSource, 'red_admin_list_ui_action_button($visibleArticle')
            && str_contains($adminInactiveListSource, "'subtype' => ''")
            && !str_contains($adminAreaListSourceBundle, '/admin/images/ico_edit.png')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-area-list__row')
            && str_contains($controlPanelCss, '.red-admin-area-list__row--form-login')
            && str_contains($controlPanelCss, '.red-admin-area-list__component')
            && str_contains($controlPanelCss, '.red-admin-area-list__status--active')
            && str_contains($controlPanelCss, '.red-admin-area-list__action:focus-visible')
            && str_contains($controlPanelCss, '@media only screen and (max-width: 1000px)'),
        'administrator area lists share aligned responsive rows and accessible Edit controls without changing their AJAX contracts'
    );
    red_theme_test_assert(
        is_string($adminAdvancedEditorEndpointSource)
            && str_contains($adminAdvancedEditorEndpointSource, "['Website_CSS', 'Website_Footer', 'Website_Header']")
            && str_contains($adminAdvancedEditorEndpointSource, 'red_admin_render_advanced_source_editor')
            && is_string($adminAdvancedEditorUiSource)
            && str_contains($adminAdvancedEditorUiSource, "red_admin_advanced_editor_definition")
            && str_contains($adminAdvancedEditorUiSource, "'Website_CSS' =>")
            && str_contains($adminAdvancedEditorUiSource, "'Website_Header' =>")
            && str_contains($adminAdvancedEditorUiSource, "'Website_Footer' =>")
            && str_contains($adminAdvancedEditorUiSource, 'data-red-advanced-editor')
            && str_contains($adminAdvancedEditorUiSource, 'name="css_target_token"')
            && str_contains($adminAdvancedEditorUiSource, 'name="RecordID"')
            && str_contains($adminAdvancedEditorUiSource, 'name="Item"')
            && str_contains($adminAdvancedEditorUiSource, 'red_csrf_input()')
            && is_string($adminAdvancedEditorScript)
            && str_contains($adminAdvancedEditorScript, 'window.run_update_advanced')
            && str_contains($adminAdvancedEditorScript, 'window.jQuery(form).serialize()')
            && str_contains($adminAdvancedEditorScript, 'navigator.clipboard.writeText')
            && str_contains($adminAdvancedEditorScript, 'event.key !== "Tab"')
            && str_contains($controlPanelCss, '.red-admin-advanced-editor-shell--css')
            && str_contains($controlPanelCss, '.red-admin-advanced-source-field textarea')
            && str_contains($controlPanelCss, '@media (max-width: 700px)'),
        'Website CSS, Header, and Footer share a responsive source workspace while preserving their write contracts'
    );
    red_theme_test_assert(
        is_string($adminAdvancedEditorEndpointSource)
            && str_contains($adminAdvancedEditorEndpointSource, "['Website_Title', 'Website_Slogan']")
            && str_contains($adminAdvancedEditorEndpointSource, 'red_admin_render_advanced_identity_editor')
            && is_string($adminAdvancedEditorUiSource)
            && str_contains($adminAdvancedEditorUiSource, 'red_admin_advanced_identity_definition')
            && str_contains($adminAdvancedEditorUiSource, "'Website_Title' =>")
            && str_contains($adminAdvancedEditorUiSource, "'Website_Slogan' =>")
            && str_contains($adminAdvancedEditorUiSource, 'data-red-advanced-identity')
            && str_contains($adminAdvancedEditorUiSource, 'name="ShortLine"')
            && str_contains($adminAdvancedEditorUiSource, 'data-advanced-identity-preview')
            && str_contains($adminAdvancedEditorUiSource, 'data-advanced-identity-restore')
            && str_contains($adminAdvancedEditorUiSource, 'red_csrf_input()')
            && is_string($adminAdvancedIdentityScript)
            && str_contains($adminAdvancedIdentityScript, 'window.run_update_advanced')
            && str_contains($adminAdvancedIdentityScript, 'window.jQuery(form).serialize()')
            && str_contains($adminAdvancedIdentityScript, 'navigator.clipboard.writeText')
            && str_contains($adminAdvancedIdentityScript, 'textContent = trimmed || emptyPreview')
            && str_contains($controlPanelCss, '.red-admin-advanced-identity-shell--title')
            && str_contains($controlPanelCss, '.red-admin-advanced-identity-shell--slogan')
            && str_contains($controlPanelCss, '.red-admin-advanced-identity-grid')
            && str_contains($controlPanelCss, '.red-admin-advanced-identity-preview__canvas'),
        'Website Title and Website Slogan share a responsive identity workspace while preserving the ShortLine write contract'
    );
    red_theme_test_assert(
        is_string($adminAdvancedEditorEndpointSource)
            && str_contains($adminAdvancedEditorEndpointSource, "=== 'Website_Logo'")
            && str_contains($adminAdvancedEditorEndpointSource, 'red_admin_render_advanced_logo_editor')
            && is_string($adminAdvancedEditorUiSource)
            && str_contains($adminAdvancedEditorUiSource, 'data-red-advanced-logo')
            && str_contains($adminAdvancedEditorUiSource, 'data-logo-dropzone')
            && str_contains($adminAdvancedEditorUiSource, 'Browse computer')
            && str_contains($adminAdvancedEditorUiSource, 'Copy image URL')
            && str_contains($adminAdvancedEditorUiSource, 'No custom logo uploaded')
            && str_contains($adminAdvancedEditorUiSource, 'Template fallback in use')
            && !str_contains($adminAdvancedEditorUiSource, 'Legacy placeholder preserved')
            && is_string($adminAdvancedLogoScript)
            && str_contains($adminAdvancedLogoScript, 'new XMLHttpRequest()')
            && str_contains($adminAdvancedLogoScript, '["png", "jpg", "jpeg"]')
            && str_contains($adminAdvancedLogoScript, 'navigator.clipboard.writeText')
            && is_string($siteLogoHelperSource)
            && str_contains($siteLogoHelperSource, 'red_site_logo_public_context')
            && !str_contains($siteLogoHelperSource, 'legacyPlaceholder')
            && is_string($logoUploadSource)
            && str_contains($logoUploadSource, "red_upload_validate_file(\$file, ['jpg', 'jpeg', 'png']")
            && is_string($standardAdapterSource)
            && str_contains($standardAdapterSource, "'Website_Title'")
            && str_contains($standardAdapterSource, "'Website_Logo'")
            && str_contains($standardAdapterSource, "'Website_Header'")
            && str_contains($standardAdapterSource, "'Website_Footer'")
            && str_contains($standardAdapterSource, "'logo' => red_site_logo_public_context")
            && is_string($starterProductionHeaderSource)
            && str_contains($starterProductionHeaderSource, "is_array(\$header['logo'] ?? null)")
            && str_contains($controlPanelCss, '.red-admin-advanced-logo-dropzone')
            && str_contains($controlPanelCss, '@media (max-width: 700px)'),
        'Website Logo uses a polished PNG/JPG upload workspace and a safe shared template-fallback contract'
    );
    red_theme_test_assert(
        is_string($adminAdvancedEditorEndpointSource)
            && str_contains($adminAdvancedEditorEndpointSource, "=== 'Website_Red_Sphere_Credit'")
            && str_contains($adminAdvancedEditorEndpointSource, 'red_admin_render_advanced_credit_editor')
            && is_string($adminAdvancedEditorUiSource)
            && str_contains($adminAdvancedEditorUiSource, 'data-red-advanced-credit')
            && str_contains($adminAdvancedEditorUiSource, 'name="ShortLine" value="Y"')
            && str_contains($adminAdvancedEditorUiSource, 'name="ShortLine" value="N"')
            && str_contains($adminAdvancedEditorUiSource, '/admin/images/red-tm.png')
            && is_string($adminAdvancedCreditScript)
            && str_contains($adminAdvancedCreditScript, 'window.run_update_advanced')
            && str_contains($adminAdvancedCreditScript, 'window.jQuery(form).serialize()')
            && str_contains($controlPanelCss, '.red-admin-advanced-credit-grid')
            && str_contains($controlPanelCss, '.red-admin-signature')
            && is_string($controlPanelNavigationSource)
            && str_contains($controlPanelNavigationSource, 'class="red-admin-signature"')
            && !str_contains($controlPanelNavigationSource, 'redcms-bonsai.png'),
        'the Webmaster website-credit control uses a polished opt-out workspace and the admin shell uses a text-only RED-CMS 5.0 signature'
    );
    $publicThemeHelperSource = file_get_contents(dirname(__DIR__) . '/includes/public_theme_helpers.php');
    $starterFooterSource = file_get_contents(dirname(__DIR__) . '/themes/starter-reference/partials/production-footer.php');
    $legacyFooterSource = file_get_contents(dirname(__DIR__) . '/themes/legacy-bootstrap/partials/footer.php');
    $creditMigrationSource = file_get_contents(
        dirname(__DIR__) . '/database/migrations/2026-07-24-red-sphere-credit.sql'
    );
    red_theme_test_assert(
        is_string($publicThemeHelperSource)
            && str_contains($publicThemeHelperSource, 'red_public_red_sphere_credit_enabled')
            && str_contains($publicThemeHelperSource, '/admin/images/red-tm.png')
            && str_contains($publicThemeHelperSource, 'https://www.red-sphere.com')
            && is_string($starterFooterSource)
            && str_contains($starterFooterSource, 'red_public_render_red_sphere_credit($footer)')
            && is_string($legacyFooterSource)
            && str_contains($legacyFooterSource, 'red_public_render_red_sphere_credit($redThemeFooterContext)')
            && is_string($creditMigrationSource)
            && str_contains($creditMigrationSource, "'Website_Red_Sphere_Credit', 'Y'")
            && is_file(dirname(__DIR__) . '/favicon.svg')
            && is_file(dirname(__DIR__) . '/logoico.ico')
            && is_file(dirname(__DIR__) . '/apple-touch-icon.png')
            && is_file(dirname(__DIR__) . '/icon-192.png')
            && is_file(dirname(__DIR__) . '/icon-512.png'),
        'the default-on Red Sphere credit stays inside every production footer and the Bonsai favicon package is complete'
    );
    $newSectionSource = file_get_contents(dirname(__DIR__) . '/admin/class/class_new_section.php');
    $editSectionSource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_section.php');
    $insertSectionSource = file_get_contents(dirname(__DIR__) . '/admin/bin/insert_section.php');
    red_theme_test_assert(
        is_string($newSectionSource)
            && str_contains($newSectionSource, 'class="cp red-admin-section-form"')
            && str_contains($newSectionSource, 'data-red-section-url-preview')
            && str_contains($newSectionSource, 'name="AccessLevel" value="Public" checked')
            && str_contains($newSectionSource, 'Members only <em>Planned</em>')
            && str_contains($newSectionSource, 'name="Features[]"')
            && str_contains($newSectionSource, 'red_csrf_input()')
            && str_contains($newSectionSource, 'url: "/admin/bin/insert_section.php"')
            && !str_contains($newSectionSource, 'name="QueryLimit"')
            && is_string($editSectionSource)
            && str_contains($editSectionSource, 'red-admin-section-legacy-access')
            && !str_contains($editSectionSource, 'name="QueryLimit"')
            && !str_contains($editSectionSource, '<select name="AccessLevel">')
            && is_string($insertSectionSource)
            && str_contains($insertSectionSource, "\$queryLimit = '100';")
            && str_contains($insertSectionSource, "\$accessLevel = 'Public';")
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-section-form')
            && str_contains($controlPanelCss, '#advanced .red-admin-section-submit'),
        'Add Section uses the polished responsive workspace while retaining an internal render cap and withholding unenforced private access'
    );
    $newCategorySource = file_get_contents(dirname(__DIR__) . '/admin/class/class_new_category.php');
    $newSubcategorySource = file_get_contents(dirname(__DIR__) . '/admin/class/class_new_subcategory.php');
    $editCategorySource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_category.php');
    $editSubcategorySource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_subcategory.php');
    $insertCategorySource = file_get_contents(dirname(__DIR__) . '/admin/bin/insert_category.php');
    $insertSubcategorySource = file_get_contents(dirname(__DIR__) . '/admin/bin/insert_subcategory.php');
    $areaFormSources = [
        $newSectionSource,
        $editSectionSource,
        $newCategorySource,
        $editCategorySource,
        $newSubcategorySource,
        $editSubcategorySource,
    ];
    red_theme_test_assert(
        !in_array(false, $areaFormSources, true)
            && array_reduce(
                $areaFormSources,
                static fn ($valid, $source) => $valid
                    && str_contains($source, 'red-admin-section-form')
                    && str_contains($source, 'data-red-area-form=')
                    && str_contains($source, 'name="Features[]"')
                    && str_contains($source, 'red_csrf_input()')
                    && !str_contains($source, 'name="QueryLimit"')
                    && !str_contains($source, '<select name="AccessLevel">'),
                true
            )
            && str_contains($newCategorySource, 'url: "/admin/bin/insert_category.php"')
            && str_contains($newCategorySource, 'data-red-category-url-preview')
            && str_contains($newSubcategorySource, 'url: "/admin/bin/insert_subcategory.php"')
            && str_contains($newSubcategorySource, 'data-red-subcategory-url-preview')
            && str_contains($editSectionSource, 'url: "/admin/bin/update_section.php"')
            && str_contains($editSectionSource, 'name="CurrentSection"')
            && str_contains($editSectionSource, 'RED_ADMIN_AREA_RENAME.redirect(canonicalAlias, 1)')
            && str_contains($editCategorySource, 'url: "/admin/bin/update_category.php"')
            && str_contains($editCategorySource, 'name="CurrentCategory"')
            && str_contains($editCategorySource, 'RED_ADMIN_AREA_RENAME.redirect(canonicalAlias, 2)')
            && str_contains($editSubcategorySource, 'url: "/admin/bin/update_subcategory.php"')
            && str_contains($editSubcategorySource, 'name="CurrentSubCategory"')
            && str_contains($editSubcategorySource, 'RED_ADMIN_AREA_RENAME.redirect(canonicalAlias, 3)')
            && str_contains($editSectionSource, 'url: "/admin/bin/delete_label.php"')
            && str_contains($editSectionSource, 'function run_delete_section_record')
            && str_contains($editCategorySource, 'function run_delete_category_record')
            && str_contains($editCategorySource, 'T: "categories", csrf_token:')
            && str_contains($editSubcategorySource, 'function run_delete_subcategory_record')
            && str_contains($editSubcategorySource, 'T: "subcategories", csrf_token:')
            && is_string($insertCategorySource)
            && str_contains($insertCategorySource, "\$queryLimit = '100';")
            && str_contains($insertCategorySource, "\$accessLevel = 'Public';")
            && is_string($insertSubcategorySource)
            && str_contains($insertSubcategorySource, "\$queryLimit = '100';")
            && str_contains($insertSubcategorySource, "\$accessLevel = 'Public';")
            && str_contains($controlPanelCss, '.red-admin-section-grid--identity')
            && str_contains($controlPanelCss, '.red-admin-section-management')
            && str_contains($controlPanelCss, '.red-admin-section-delete')
            && str_contains($controlPanelCss, '.red-admin-area-form--category')
            && str_contains($controlPanelCss, '.red-admin-area-form--subcategory'),
        'Section, Category, and Subcategory Add/Edit forms share one responsive workspace while preserving AJAX, rename, delete, feature, and field contracts'
    );
    $areaFeatureCatalog = red_admin_area_feature_catalog();
    red_theme_test_assert(
        array_keys($areaFeatureCatalog) === ['slider']
            && red_admin_area_feature_label('slider') === 'Hero photo slider'
            && str_contains(red_admin_area_feature_description('slider'), 'Slider summary')
            && red_admin_feature_list(['kwicks', 'slider', 'slider', '']) === 'slider'
            && red_admin_feature_list('kwicks') === ''
            && str_contains($newSectionSource, 'red_admin_area_feature_label($featureOption)')
            && str_contains($newSectionSource, 'red_admin_area_feature_description($featureOption)'),
        'administrator area feature choices expose only the implemented Hero photo slider and reject dormant kwicks submissions'
    );
    $adminUserEditorSource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_admin_users.php');
    $adminUserPermissions = red_admin_user_component_permissions([
        ['RecordID' => 100, 'UniqueName' => 'Article', 'Layout' => '', 'ButtonTag' => 'New Article'],
        ['RecordID' => 102, 'UniqueName' => 'Form', 'Layout' => 'Contact', 'ButtonTag' => 'Form Contact'],
        ['RecordID' => 103, 'UniqueName' => 'Form', 'Layout' => 'Login', 'ButtonTag' => 'Form Login'],
        ['RecordID' => 104, 'UniqueName' => 'Form', 'Layout' => 'Response', 'ButtonTag' => 'Form Response'],
        ['RecordID' => 105, 'UniqueName' => 'Form', 'Layout' => 'Other', 'ButtonTag' => 'Form Other'],
        ['RecordID' => 117, 'UniqueName' => 'Form', 'Layout' => 'Register', 'ButtonTag' => 'Form Register'],
    ]);
    $adminUserPermissionMap = [];
    foreach ($adminUserPermissions as $permission) {
        $adminUserPermissionMap[$permission['PermissionKey'] ?? ''] = $permission;
    }
    red_theme_test_assert(
        count($adminUserPermissions) === 3
            && isset($adminUserPermissionMap['form-builder'], $adminUserPermissionMap['admin-login'])
            && ($adminUserPermissionMap['form-builder']['ButtonTag'] ?? '') === 'Form Builder'
            && ($adminUserPermissionMap['form-builder']['PermissionIDs'] ?? []) === [102, 104, 105, 117]
            && ($adminUserPermissionMap['admin-login']['ButtonTag'] ?? '') === 'Admin Login'
            && ($adminUserPermissionMap['admin-login']['PermissionIDs'] ?? []) === [103]
            && is_string($adminUserEditorSource)
            && str_contains($adminUserEditorSource, 'red_admin_user_component_permissions(red_admin_user_components')
            && str_contains($adminUserEditorSource, 'redAdminSyncPermissionGroup')
            && str_contains($adminUserEditorSource, 'data-red-permission-member')
            && str_contains($adminUserEditorSource, 'linked content types'),
        'Administrator Users presents one Form Builder permission and a separate Admin Login while submitting the original component ids'
    );
    $newArticleSource = file_get_contents(dirname(__DIR__) . '/admin/bin/new_article.php');
    $newArticleScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/new-article-form.js');
    red_theme_test_assert(
        is_string($newArticleSource)
            && str_contains($newArticleSource, 'class="cp red-admin-article-form"')
            && str_contains($newArticleSource, 'data-article-advanced')
            && str_contains($newArticleSource, 'name="StartDate" type="date" id="article-start-date"')
            && str_contains($newArticleSource, 'name="ExpDate" type="date" id="article-expiration-date"')
            && substr_count($newArticleSource, 'data-article-upload data-upload-field=') === 3
            && substr_count($newArticleSource, 'type="file"') === 3
            && str_contains($newArticleSource, "'AuthComponent' => 'Article'")
            && str_contains($newArticleSource, "'Insert' => 'true'")
            && str_contains($newArticleSource, 'name="RecordID"')
            && str_contains($newArticleSource, 'name="Language"')
            && str_contains($newArticleSource, 'name="Layout"')
            && str_contains($newArticleSource, 'name="EditedBy"')
            && str_contains($newArticleSource, 'name="Component"')
            && str_contains($newArticleSource, 'red_csrf_input()'),
        'New Article groups its fields, uses calendar controls, and adds three accessible computer pickers without changing hidden or upload targets'
    );
    $tinyMcePlugins = [
        'advlist',
        'autolink',
        'lists',
        'link',
        'image',
        'charmap',
        'preview',
        'anchor',
        'searchreplace',
        'visualblocks',
        'code',
        'fullscreen',
        'insertdatetime',
        'media',
        'table',
        'paste',
        'textcolor',
        'wordcount',
    ];
    $tinyMcePluginAssetsPresent = true;
    foreach ($tinyMcePlugins as $tinyMcePlugin) {
        if (!is_file(dirname(__DIR__) . '/admin/assets/js/tiny_mce/plugins/' . $tinyMcePlugin . '/plugin.min.js')) {
            $tinyMcePluginAssetsPresent = false;
            break;
        }
    }
    red_theme_test_assert(
        is_string($newArticleScript)
            && $tinyMcePluginAssetsPresent
            && !str_contains($newArticleScript, 'moxiemanager')
            && str_contains($newArticleScript, 'window.tinymce.triggerSave()')
            && str_contains($newArticleSource, 'data-submit-url="/admin/bin/insert_content.php"')
            && str_contains($newArticleScript, 'url: submitUrl')
            && str_contains($newArticleScript, "payload.append('pic', file, file.name)")
            && str_contains($newArticleScript, "xhr.setRequestHeader('X-CSRF-Token'")
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-article-form')
            && str_contains($controlPanelCss, '#advanced .red-admin-upload-grid'),
        'New Article uses only bundled TinyMCE plugins, synchronizes before save, and preserves protected multipart image uploads'
    );
    $editArticleSource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_article.php');
    red_theme_test_assert(
        is_string($editArticleSource)
            && str_contains($editArticleSource, 'id="update_content"')
            && str_contains($editArticleSource, 'class="cp red-admin-article-form"')
            && str_contains($editArticleSource, 'data-article-mode="edit"')
            && str_contains($editArticleSource, 'data-submit-url="/admin/bin/update_content.php"')
            && str_contains($editArticleSource, 'data-delete-url="/admin/bin/delete_label.php"')
            && str_contains($editArticleSource, 'red_admin_require_article_access($db->connection, $RecordID)')
            && str_contains($editArticleSource, 'Unavailable; preserved')
            && str_contains($editArticleSource, 'name="Title"')
            && str_contains($editArticleSource, 'name="Alias"')
            && str_contains($editArticleSource, 'name="Tags"')
            && str_contains($editArticleSource, 'name="Active"')
            && str_contains($editArticleSource, 'name="HomeFeature"')
            && str_contains($editArticleSource, 'name="SliderDesc"')
            && str_contains($editArticleSource, 'name="ShortDesc"')
            && str_contains($editArticleSource, 'name="LongDesc"')
            && str_contains($editArticleSource, 'name="Link"')
            && str_contains($editArticleSource, 'name="NewWindow"')
            && str_contains($editArticleSource, 'name="Sections"')
            && str_contains($editArticleSource, 'name="Categories"')
            && str_contains($editArticleSource, 'name="SubCategories"')
            && str_contains($editArticleSource, 'name="Article"')
            && str_contains($editArticleSource, "'alignmentName' => 'SmallPictAlign'")
            && str_contains($editArticleSource, "'alignmentName' => 'SmallPictAlign2'")
            && str_contains($editArticleSource, 'name="RecordID"')
            && str_contains($editArticleSource, 'name="EditedBy"')
            && str_contains($editArticleSource, 'red_csrf_input()'),
        'Edit Article retains its protected update/delete field contract inside the shared compact Article workspace'
    );
    red_theme_test_assert(
        is_string($editArticleSource)
            && str_contains($editArticleSource, 'type="date" id="article-start-date"')
            && str_contains($editArticleSource, 'type="date" id="article-expiration-date"')
            && str_contains($editArticleSource, 'name="StartDate" value="" disabled data-date-payload')
            && str_contains($editArticleSource, 'name="ExpDate" value="" disabled data-date-payload')
            && str_contains($editArticleSource, "foreach (['BigPict', 'SmallPict', 'SmallPict2'] as \$uploadCase)")
            && str_contains($editArticleSource, "'field' => 'BigPict'")
            && str_contains($editArticleSource, "'field' => 'SmallPict'")
            && str_contains($editArticleSource, "'field' => 'SmallPict2'")
            && str_contains($editArticleSource, 'data-upload-value')
            && str_contains($editArticleSource, 'data-remove-image')
            && !str_contains($editArticleSource, "'Insert' => 'true'")
            && is_string($newArticleScript)
            && str_contains($newArticleScript, 'window.run_update_content')
            && str_contains($newArticleScript, 'scopeEditorIdentity')
            && str_contains($newArticleScript, "form.id + '-' + originalId")
            && str_contains($newArticleScript, 'response.stored_name')
            && str_contains($newArticleScript, 'valueInput.value = storedName')
            && str_contains($newArticleScript, 'removeInput.checked = false')
            && str_contains($newArticleScript, 'data: window.jQuery(form).serialize()')
            && str_contains($newArticleScript, "csrf_token: csrfInput.value")
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-upload-current')
            && str_contains($controlPanelCss, '#advanced .red-admin-article-delete'),
        'Edit Article preserves untouched timestamps and safely synchronizes existing-image replace/remove controls through the shared module'
    );
    red_theme_test_assert(
        is_string($editArticleSource)
            && substr_count($editArticleSource, 'data-copy-page-link') === 2
            && substr_count($editArticleSource, 'data-copy-value="<?php echo red_admin_area_html($publicUrl); ?>"') === 2
            && str_contains($editArticleSource, 'data-copy-status')
            && str_contains($editArticleSource, 'Copy page link')
            && str_contains($editArticleSource, 'red-admin-article-permalink-copy')
            && is_string($newArticleScript)
            && str_contains($newArticleScript, 'initializePageCopyControls')
            && str_contains($newArticleScript, 'window.navigator.clipboard.writeText(value)')
            && str_contains($newArticleScript, "document.execCommand('copy')")
            && str_contains($newArticleScript, 'Page address copied to the clipboard.')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-article-view-link.is-copied')
            && str_contains($controlPanelCss, '#advanced .red-admin-article-permalink-copy.is-copied'),
        'Edit Article copies the current public address from both compact controls with accessible feedback and a legacy fallback'
    );
    $newOtherSource = file_get_contents(dirname(__DIR__) . '/admin/bin/new_other.php');
    $editOtherSource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_other.php');
    $otherUiSource = file_get_contents(dirname(__DIR__) . '/includes/admin_other_ui_helpers.php');
    $otherFormScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/other-form.js');
    red_theme_test_assert(
        red_admin_other_preferred_editor_mode('', 'create') === 'visual'
            && red_admin_other_preferred_editor_mode('<section data-redcms-source-section="1"></section>', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<main data-redcms-source-page="home"></main>', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<div class="feature">Structured content</div>', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<img src="x" onerror="alert(1)">', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<iframe src="https://example.com"></iframe>', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<button type="button">CTA</button>', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<input type="text">', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<noscript>Fallback</noscript>', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<meta http-equiv="refresh" content="1">', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<link rel="stylesheet" href="theme.css">', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<img src="a.jpg" srcset="a.jpg 1x, b.jpg 2x">', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<brand-card>Custom content</brand-card>', 'edit') === 'html'
            && red_admin_other_preferred_editor_mode('<p>Simple content</p>', 'edit') === 'visual'
            && red_admin_other_preferred_editor_mode('<h2>Simple heading</h2><ul><li>List item</li></ul>', 'edit') === 'visual'
            && is_string($otherUiSource)
            && str_contains($otherUiSource, 'name="ShortDesc" id="other-html-source"')
            && str_contains($otherUiSource, 'id="other-visual-editor" rows="14" data-other-visual-editor')
            && !str_contains($otherUiSource, 'name="VisualShortDesc"')
            && str_contains($otherUiSource, 'role="tablist" aria-label="Content editing mode"')
            && str_contains($otherUiSource, 'data-other-editor-mode="visual"')
            && str_contains($otherUiSource, 'data-other-editor-mode="html"')
            && str_contains($otherUiSource, 'Advanced layout markup detected.')
            && str_contains($otherUiSource, 'This block stays in HTML mode')
            && str_contains($otherUiSource, 'Visual editing is unavailable for structured template HTML')
            && str_contains($otherUiSource, 'Simple blocks only')
            && str_contains($otherUiSource, 'Tab moves to the next control'),
        'Other chooses approachable Visual mode for simple content and lossless HTML mode for structured source while retaining one canonical ShortDesc field'
    );
    red_theme_test_assert(
        is_string($newOtherSource)
            && is_string($editOtherSource)
            && str_contains($newOtherSource, 'red_admin_render_other_form([')
            && str_contains($editOtherSource, 'red_admin_render_other_form([')
            && str_contains($newOtherSource, "red_admin_require_component_selection(\$db->connection, 'Other')")
            && str_contains($editOtherSource, "red_admin_text(\$row['Component'] ?? '') !== 'Other'")
            && str_contains($newOtherSource, "'submitUrl' => '/admin/bin/insert_content.php'")
            && str_contains($editOtherSource, "'submitUrl' => '/admin/bin/update_content.php'")
            && str_contains($editOtherSource, "'deleteUrl' => '/admin/bin/delete_label.php'")
            && str_contains($newOtherSource, "'AuthComponent' => 'Other'")
            && str_contains($otherUiSource, 'id="insert_content"') === false
            && str_contains($otherUiSource, "\$formId = \$isEdit ? 'update_content' : 'insert_content'")
            && str_contains($otherUiSource, 'name="Component" id="Component" value="Other"')
            && str_contains($otherUiSource, 'name="RecordID" id="RecordID"')
            && str_contains($otherUiSource, 'name="EditedBy" id="EditedBy"')
            && str_contains($otherUiSource, 'name="csrf_token"')
            && !str_contains($newOtherSource, 'jquery.filedrop')
            && !str_contains($editOtherSource, 'jquery.filedrop'),
        'Other Add/Edit use the shared polished workspace while preserving authorization, exact generic endpoints, record fields, component identity, CSRF, and upload authorization'
    );
    red_theme_test_assert(
        is_string($otherFormScript)
            && str_contains($otherFormScript, 'form._redOtherSourceSnapshot')
            && str_contains($otherFormScript, 'form._redOtherVisualDirty')
            && str_contains($otherFormScript, 'syncVisualToSource(form)')
            && str_contains($otherFormScript, 'window.tinymce.init(editorConfig(form, field))')
            && str_contains($otherFormScript, 'sanitizeVisualHtml')
            && str_contains($otherFormScript, "blocked = parsed.body.querySelectorAll('script,noscript,iframe")
            && str_contains($otherFormScript, "editor.on('BeforeSetContent'")
            && str_contains($otherFormScript, 'paste_preprocess: function')
            && str_contains($otherFormScript, 'verify_html: true')
            && !str_contains($otherFormScript, "valid_elements: '*[*]'")
            && !str_contains($otherFormScript, 'alignleft aligncenter alignright')
            && str_contains($otherFormScript, 'replaceIdReferences')
            && str_contains($otherFormScript, 'scopeElementIdentity')
            && str_contains($otherFormScript, 'referencedIds')
            && str_contains($otherFormScript, 'form._redOtherPendingVisualContent')
            && str_contains($otherFormScript, "mode === 'visual' && form.getAttribute('data-advanced-markup') === 'true'")
            && !str_contains($otherFormScript, "source.addEventListener('keydown'")
            && !str_contains($otherFormScript, 'tinymce.triggerSave')
            && !str_contains($otherFormScript, 'tinyMCE.triggerSave')
            && !str_contains($otherFormScript, 'moxiemanager')
            && str_contains($otherFormScript, "event.key === 'ArrowRight'")
            && str_contains($otherFormScript, "event.key === 'ArrowLeft'")
            && str_contains($otherFormScript, "payload.append('pic', file, file.name)")
            && str_contains($otherFormScript, "xhr.setRequestHeader('X-CSRF-Token'")
            && str_contains($otherFormScript, 'data: window.jQuery(form).serialize()')
            && str_contains($otherFormScript, 'document.execCommand(\'copy\')')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-other-mode-tabs')
            && str_contains($controlPanelCss, '#advanced .red-admin-other-editor-pane--html textarea')
            && str_contains($controlPanelCss, '#advanced .red-admin-other-supporting-grid')
            && preg_match('/@media \(max-width: 560px\)\s*\{\s*#advanced \.red-admin-other-mode-tabs/s', $controlPanelCss) === 1,
        'Other keeps untouched raw HTML outside TinyMCE, exposes keyboard-accessible dual modes, protected uploads, clipboard feedback, and responsive source editing without third-party services'
    );
    $ftpLibraryRoot = $projectRoot . '/ftp-library-fixture';
    $ftpLibraryDirectory = $ftpLibraryRoot . '/images/articles';
    if (!mkdir($ftpLibraryDirectory . '/nested', 0700, true)) {
        throw new RuntimeException('Could not create the temporary FTP-library fixture.');
    }
    file_put_contents($ftpLibraryDirectory . '/latest & notes.txt', 'latest');
    file_put_contents($ftpLibraryDirectory . '/file10.pdf', 'ten');
    file_put_contents($ftpLibraryDirectory . '/file2.pdf', 'two');
    file_put_contents($ftpLibraryDirectory . '/blocked.php', '<?php echo "blocked";');
    file_put_contents($ftpLibraryDirectory . '/.hidden.pdf', 'hidden');
    file_put_contents($ftpLibraryDirectory . '/nested/inside.pdf', 'nested');
    $ftpFixtureTime = time();
    if (
        !touch($ftpLibraryDirectory . '/latest & notes.txt', $ftpFixtureTime)
        || !touch($ftpLibraryDirectory . '/file10.pdf', $ftpFixtureTime - 10)
        || !touch($ftpLibraryDirectory . '/file2.pdf', $ftpFixtureTime - 10)
    ) {
        throw new RuntimeException('Could not timestamp the FTP-library fixtures.');
    }
    if (
        function_exists('symlink')
        && !symlink($ftpLibraryDirectory . '/file2.pdf', $ftpLibraryDirectory . '/linked.pdf')
    ) {
        throw new RuntimeException('Could not create the FTP-library symlink fixture.');
    }
    $ftpLibraryFiles = red_admin_ftp_file_library($ftpLibraryRoot);
    red_theme_test_assert(
        array_column($ftpLibraryFiles, 'name') === ['latest & notes.txt', 'file2.pdf', 'file10.pdf']
            && ($ftpLibraryFiles[0]['publicPath'] ?? '') === '/images/articles/latest%20%26%20notes.txt'
            && ($ftpLibraryFiles[0]['typeLabel'] ?? '') === 'Text file'
            && ($ftpLibraryFiles[1]['typeLabel'] ?? '') === 'Document'
            && ($ftpLibraryFiles[0]['sizeLabel'] ?? '') === '6 B',
        'FTP file library lists only allowlisted top-level regular files with encoded paths, metadata, and newest-then-natural ordering'
    );
    $newFtpSource = file_get_contents(dirname(__DIR__) . '/admin/bin/new_ftp.php');
    $postFtpSource = file_get_contents(dirname(__DIR__) . '/admin/bin/post_ftp.php');
    $uploadHelperSource = file_get_contents(dirname(__DIR__) . '/includes/upload_helpers.php');
    $ftpHelperSource = file_get_contents(dirname(__DIR__) . '/includes/admin_ftp_ui_helpers.php');
    $ftpFormScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/ftp-form.js');
    red_theme_test_assert(
        is_string($newFtpSource)
            && str_contains($newFtpSource, "red_admin_require_component_selection(\$authorizationDb->connection, 'FTP')")
            && str_contains($newFtpSource, 'class="cp red-admin-article-form red-admin-ftp-form"')
            && str_contains($newFtpSource, 'id="dropbox"')
            && str_contains($newFtpSource, 'data-ftp-library')
            && str_contains($newFtpSource, 'data-ftp-copy-path=')
            && str_contains($newFtpSource, 'data-upload-url=')
            && str_contains($newFtpSource, 'action="<?php echo red_admin_area_html($uploadUrl); ?>"')
            && str_contains($newFtpSource, 'data-allowed-extensions=')
            && str_contains($newFtpSource, 'data-max-file-bytes=')
            && str_contains($newFtpSource, "\$ftpScript = '/admin/assets/js/ftp-form.js'")
            && str_contains($newFtpSource, '<script src="<?php echo red_admin_area_html($ftpScript); ?>')
            && str_contains($newFtpSource, 'Browse computer')
            && str_contains($newFtpSource, 'class="red-admin-ftp-file-input"')
            && str_contains($newFtpSource, 'tabindex="-1"')
            && str_contains($newFtpSource, 'aria-hidden="true"')
            && str_contains($newFtpSource, "'UC' => 'FTP'")
            && str_contains($newFtpSource, "'Language' => \$Language")
            && !str_contains($newFtpSource, "'csrf_token' => \$csrfToken")
            && !str_contains($newFtpSource, 'jquery.filedrop.js')
            && !str_contains($newFtpSource, '/admin/assets/css/styles.css')
            && !str_contains($newFtpSource, 'data-ftp-delete'),
        'FTP renders a polished upload-only workspace and collapsed shared-folder library while retaining its Add Content and legacy dropbox contracts'
    );
    red_theme_test_assert(
        is_string($postFtpSource)
            && str_contains($postFtpSource, 'red_require_admin(true)')
            && str_contains($postFtpSource, '$allowedExtensions = red_upload_ftp_allowed_extensions()')
            && str_contains($postFtpSource, '$maxBytes = red_upload_ftp_max_bytes()')
            && str_contains($postFtpSource, "array_key_exists('pic', \$_FILES)")
            && str_contains($postFtpSource, "red_admin_require_component_selection(\$db->connection, 'FTP')")
            && str_contains($postFtpSource, "red_upload_move(\$file, 'images/articles', \$fileInfo['safe_name'])")
            && str_contains($postFtpSource, "['stored_name' => \$storedName]")
            && is_string($uploadHelperSource)
            && str_contains($uploadHelperSource, 'function red_upload_ftp_allowed_extensions()')
            && str_contains($uploadHelperSource, 'function red_upload_ftp_max_bytes()')
            && red_upload_ftp_allowed_extensions() === [
                'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'pdf', 'xls', 'xlsx', 'pptx', 'ppt', 'pps', 'txt', 'zip',
            ]
            && red_admin_ftp_allowed_extensions() === red_upload_ftp_allowed_extensions()
            && red_upload_ftp_max_bytes() === 10 * 1024 * 1024
            && is_string($ftpHelperSource)
            && str_contains($ftpHelperSource, 'FilesystemIterator::SKIP_DOTS')
            && str_contains($ftpHelperSource, '$fileInfo->isLink()')
            && str_contains($ftpHelperSource, "'/images/articles/' . rawurlencode(\$name)"),
        'FTP server authorization, CSRF upload path, exact extension allowlist, multipart field, safe shared directory, and stored-name response remain protected'
    );
    red_theme_test_assert(
        is_string($ftpFormScript)
            && str_contains($ftpFormScript, "payload.append('pic', file, file.name)")
            && str_contains($ftpFormScript, "xhr.setRequestHeader('X-CSRF-Token'")
            && str_contains($ftpFormScript, "form.getAttribute('data-allowed-extensions')")
            && str_contains($ftpFormScript, "form.getAttribute('data-max-file-bytes')")
            && str_contains($ftpFormScript, 'new window.URL(publicPath, window.location.origin).href')
            && str_contains($ftpFormScript, "'/images/articles/' + window.encodeURIComponent")
            && str_contains($ftpFormScript, 'window.navigator.clipboard.writeText(value)')
            && str_contains($ftpFormScript, "document.execCommand('copy')")
            && str_contains($ftpFormScript, 'list.insertBefore(item, list.firstChild)')
            && str_contains($ftpFormScript, 'showUploadResult(form, file, storedName)')
            && str_contains($ftpFormScript, 'resetUploadResult(form)')
            && substr_count($ftpFormScript, 'resetUploadResult(form)') >= 3
            && str_contains($ftpFormScript, 'uploadInFlight = true;')
            && strpos($ftpFormScript, 'resetUploadResult(form);') < strpos($ftpFormScript, 'if (validationMessage)')
            && strpos($ftpFormScript, 'resetUploadResult(form);') < strpos($ftpFormScript, 'uploadInFlight = true;')
            && str_contains($ftpFormScript, 'showSelection(file)')
            && str_contains($ftpFormScript, 'if (uploadInFlight)')
            && str_contains($ftpFormScript, "fail(validationMessage, true)")
            && str_contains($ftpFormScript, "search.addEventListener('input'")
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-ftp-result')
            && str_contains($controlPanelCss, '#advanced .red-admin-ftp-file-input')
            && str_contains($controlPanelCss, '#advanced .red-admin-ftp-file-list')
            && str_contains($controlPanelCss, '#advanced .red-admin-ftp-file__copy.is-copied')
            && str_contains($controlPanelCss, '#advanced .red-admin-ftp-library .red-admin-article-advanced__copy small')
            && preg_match('/@media \(max-width: 430px\)\s*\{\s*#advanced \.red-admin-ftp-selection/s', $controlPanelCss) === 1,
        'FTP uploads, current-origin public URLs, clipboard fallback, live library insertion, search, feedback, and responsive presentation stay wired together'
    );
    $newGallerySource = file_get_contents(dirname(__DIR__) . '/admin/bin/new_gallery.php');
    $editGallerySource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_gallery.php');
    $insertGallerySource = file_get_contents(dirname(__DIR__) . '/admin/bin/insert_gallery.php');
    $updateGallerySource = file_get_contents(dirname(__DIR__) . '/admin/bin/update_gallery.php');
    $galleryHelperSource = file_get_contents(dirname(__DIR__) . '/includes/admin_gallery_helpers.php');
    $galleryUiSource = file_get_contents(dirname(__DIR__) . '/includes/admin_gallery_ui_helpers.php');
    $galleryFormScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/gallery-form.js');
    $bannerUiSource = file_get_contents(dirname(__DIR__) . '/includes/admin_banner_ui_helpers.php');
    $bannerFormScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/banner-form.js');
    $videoUiSource = file_get_contents(dirname(__DIR__) . '/includes/admin_video_ui_helpers.php');
    $videoFormScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/video-form.js');
    $videoUrlHelperSource = file_get_contents(dirname(__DIR__) . '/includes/video_url_helpers.php');
    $galleryQueueScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/gallery-create-uploads.js');
    red_theme_test_assert(
        is_string($newGallerySource)
            && is_string($editGallerySource)
            && strpos($newGallerySource, "if (\$Type === 'Gallery')") < strpos($newGallerySource, '<!-- TinyMCE -->')
            && strpos($newGallerySource, "if (\$Type === 'Video')") < strpos($newGallerySource, '<!-- TinyMCE -->')
            && strpos($newGallerySource, "if (\$Type === 'Banner')") < strpos($newGallerySource, '<!-- TinyMCE -->')
            && strpos($editGallerySource, "if ((\$row['GalleryType'] ?? '') === 'Gallery')") < strpos($editGallerySource, '<!-- TinyMCE -->')
            && strpos($editGallerySource, "if ((\$row['GalleryType'] ?? '') === 'Video')") < strpos($editGallerySource, '<!-- TinyMCE -->')
            && strpos($editGallerySource, "if ((\$row['GalleryType'] ?? '') === 'Banner')") < strpos($editGallerySource, '<!-- TinyMCE -->')
            && str_contains($newGallerySource, "red_admin_require_component_selection(\$authorizationDb->connection, 'Gallery', \$Type)")
            && str_contains($editGallerySource, 'red_admin_require_article_access($db->connection, $ArtRecordID)')
            && str_contains($newGallerySource, "'submitUrl' => '/admin/bin/insert_gallery.php'")
            && str_contains($editGallerySource, "'submitUrl' => '/admin/bin/update_gallery.php'")
            && str_contains($editGallerySource, "'deleteUrl' => '/admin/bin/delete_label.php'")
            && str_contains($newGallerySource, 'red_admin_render_video_form([')
            && str_contains($editGallerySource, 'red_admin_render_video_form([')
            && str_contains($newGallerySource, 'switch ($Type)')
            && str_contains($editGallerySource, "switch (\$row['GalleryType'])"),
        'Gallery, Video, and Banner create/edit use their modern workspaces before legacy assets while retaining exact authorization and fallback contracts'
    );
    $galleryEntries = red_admin_gallery_ui_photo_entries(
        'first image.jpg,second.png',
        'First caption;/first,Second caption'
    );
    $galleryUploadUrl = red_admin_gallery_ui_upload_url([
        'RecordID' => 700,
        'UC' => 'Gallery',
        'csrf_token' => 'must-not-appear',
    ]);
    red_theme_test_assert(
        $galleryEntries === [
            ['name' => 'first image.jpg', 'caption' => 'First caption', 'link' => '/first'],
            ['name' => 'second.png', 'caption' => 'Second caption', 'link' => ''],
        ]
            && str_contains($galleryUploadUrl, 'RecordID=700')
            && str_contains($galleryUploadUrl, 'UC=Gallery')
            && !str_contains($galleryUploadUrl, 'csrf')
            && is_string($galleryUiSource)
            && str_contains($galleryUiSource, 'class="cp red-admin-article-form red-admin-gallery-form"')
            && str_contains($galleryUiSource, 'data-gallery-mode=')
            && str_contains($galleryUiSource, 'name="GalleryPresentation" type="radio" value="stack"')
            && str_contains($galleryUiSource, 'name="GalleryPresentation" type="radio" value="carousel"')
            && str_contains($galleryUiSource, 'data-gallery-photo-card')
            && str_contains($galleryUiSource, 'data-gallery-caption')
            && str_contains($galleryUiSource, 'data-gallery-caption-link')
            && str_contains($galleryUiSource, 'data-gallery-move="earlier"')
            && str_contains($galleryUiSource, 'data-gallery-move="later"')
            && str_contains($galleryUiSource, 'data-gallery-remove')
            && str_contains($galleryUiSource, 'name="ShortDesc"')
            && str_contains($galleryUiSource, 'name="GalleryType" value="Gallery"')
            && str_contains($galleryUiSource, 'name="ArtRecordID"')
            && str_contains($galleryUiSource, 'name="RecordID"')
            && str_contains($galleryUiSource, 'name="Component" id="Component" value="Gallery"')
            && str_contains($galleryUiSource, 'data-gallery-advanced')
            && str_contains($galleryUiSource, 'Browse images')
            && str_contains($galleryUiSource, 'up to 10 images per batch')
            && !str_contains($galleryUiSource, 'tinymce.init'),
        'Gallery workspace preserves paired fields and ordered caption metadata while exposing accessible presentation, browse, reorder, removal, and Advanced controls'
    );
    red_theme_test_assert(
        is_string($galleryFormScript)
            && str_contains($galleryFormScript, "payload.append('pic', file, file.name)")
            && str_contains($galleryFormScript, "xhr.setRequestHeader('X-CSRF-Token'")
            && str_contains($galleryFormScript, 'createUploadQueue(form)')
            && str_contains($galleryFormScript, 'markPhotoStored(form, card, result.storedName)')
            && str_contains($galleryFormScript, "photo.name = 'Photo' + index")
            && str_contains($galleryFormScript, "remove.name = 'Delete' + index")
            && str_contains($galleryFormScript, "descriptions.join(',')")
            && str_contains($galleryFormScript, 'isAllowedCaptionLink(linkValue)')
            && str_contains($galleryFormScript, "parsed.protocol === 'https:'")
            && str_contains($galleryFormScript, 'requestSave(form).then(function ()')
            && strpos($galleryFormScript, 'requestSave(form).then(function ()') < strpos($galleryFormScript, 'queue = createUploadQueue(form);')
            && substr_count($galleryFormScript, 'return requestSave(form)') >= 1
            && str_contains($galleryFormScript, "T: 'gal'")
            && str_contains($galleryFormScript, "String(data).trim() === 'yesyes'")
            && str_contains($galleryFormScript, 'form._redGalleryUploadsInFlight')
            && is_string($galleryQueueScript)
            && !str_contains($galleryQueueScript, 'csrf_token: config.csrfToken')
            && is_string($galleryHelperSource)
            && str_contains($galleryHelperSource, "'GalleryPresentation' => true")
            && is_string($insertGallerySource)
            && str_contains($insertGallerySource, '$existingGalleryType')
            && str_contains($insertGallerySource, 'red_admin_article_full_record($db->connection, $artRecordId)')
            && str_contains($insertGallerySource, 'red_admin_gallery_insert_target_allowed($existingArticle, $existingGallery, $postedGalleryType)')
            && str_contains($insertGallerySource, 'red_admin_require_article_access($db->connection, $artRecordId)')
            && str_contains($insertGallerySource, 'red_admin_gallery_insert_reuse_allowed($existingGalleryType, $postedGalleryType)')
            && is_string($updateGallerySource)
            && str_contains($updateGallerySource, '$existingGalleryType')
            && str_contains($updateGallerySource, "unset(\$galleryData['NewWindow'])")
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-gallery-presentation-card')
            && str_contains($controlPanelCss, '#advanced .red-admin-gallery-collection')
            && str_contains($controlPanelCss, '#advanced .red-admin-gallery-photo-card')
            && preg_match('/@media \(max-width: 560px\)\s*\{\s*#advanced \.red-admin-gallery-count/s', $controlPanelCss) === 1,
        'Gallery save, paired delete, post-save create queue, edit synchronization, CSRF header, caption alignment, partial-update preservation, and mobile presentation stay wired together'
    );
    red_theme_test_assert(
        is_string($bannerUiSource)
            && str_contains($bannerUiSource, 'class="cp red-admin-article-form red-admin-banner-form"')
            && str_contains($bannerUiSource, 'data-banner-advanced')
            && str_contains($bannerUiSource, 'data-red-banner-queue')
            && str_contains($bannerUiSource, 'data-banner-upload data-upload-field="Photo0"')
            && str_contains($bannerUiSource, 'name="GalleryType" value="Banner"')
            && str_contains($bannerUiSource, 'name="ArtRecordID"')
            && str_contains($bannerUiSource, 'name="RecordID"')
            && str_contains($bannerUiSource, 'name="Language"')
            && str_contains($bannerUiSource, 'name="EditedBy"')
            && str_contains($bannerUiSource, 'name="Component" id="Component" value="Gallery"')
            && str_contains($bannerUiSource, 'name="Layout"')
            && str_contains($bannerUiSource, 'name="Link"')
            && str_contains($bannerUiSource, 'name="NewWindow"')
            && str_contains($bannerUiSource, 'name="StartDate" type="hidden" value="" data-date-payload disabled')
            && str_contains($bannerUiSource, 'name="ExpDate" type="hidden" value="" data-date-payload disabled')
            && str_contains($bannerUiSource, 'Browse computer')
            && !str_contains($bannerUiSource, 'tinymce.init'),
        'Banner workspace preserves paired-record fields, untouched edit dates, fixed subtype, and accessible computer image pickers without unused TinyMCE'
    );
    red_theme_test_assert(
        is_string($bannerFormScript)
            && str_contains($bannerFormScript, "payload.append('pic', file, file.name)")
            && str_contains($bannerFormScript, "xhr.setRequestHeader('X-CSRF-Token'")
            && str_contains($bannerFormScript, "valueInput.value = storedName")
            && str_contains($bannerFormScript, "T: 'gal'")
            && str_contains($bannerFormScript, "String(data).trim() === 'yesyes'")
            && str_contains($bannerFormScript, 'window.redGalleryCreateUploadQueued')
            && str_contains($bannerFormScript, 'data: window.jQuery(form).serialize()')
            && is_string($galleryQueueScript)
            && str_contains($galleryQueueScript, "Insert: 'false'")
            && str_contains($galleryQueueScript, "AuthComponent: 'Gallery'")
            && str_contains($galleryQueueScript, 'data-red-banner-queue')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-banner-media-grid')
            && str_contains($controlPanelCss, '#advanced .red-admin-banner-supporting-grid'),
        'Banner save, paired delete, post-save create queue, replacement synchronization, CSRF uploads, and responsive image-first styling retain their protected contracts'
    );
    red_theme_test_assert(
        is_string($videoUiSource)
            && str_contains($videoUiSource, 'class="cp red-admin-article-form red-admin-video-form"')
            && str_contains($videoUiSource, 'name="LongDesc" type="url" id="gal_video"')
            && str_contains($videoUiSource, 'data-video-thumbnail')
            && str_contains($videoUiSource, 'data-video-load-label')
            && str_contains($videoUiSource, 'data-video-copy-label')
            && str_contains($videoUiSource, 'data-video-open')
            && str_contains($videoUiSource, 'name="ShortDesc" id="video-description"')
            && str_contains($videoUiSource, 'name="Link" type="text" id="video-link"')
            && str_contains($videoUiSource, 'name="NewWindow" type="checkbox"')
            && str_contains($videoUiSource, 'data-video-advanced')
            && str_contains($videoUiSource, 'name="StartDate" type="hidden" value="" data-date-payload disabled')
            && str_contains($videoUiSource, 'name="ExpDate" type="hidden" value="" data-date-payload disabled')
            && str_contains($videoUiSource, 'data-video-upload data-upload-field=')
            && !str_contains($videoUiSource, 'data-upload-field="Gallery"')
            && str_contains($videoUiSource, 'name="GalleryType" value="Video"')
            && str_contains($videoUiSource, 'name="ArtRecordID"')
            && str_contains($videoUiSource, 'name="RecordID"')
            && str_contains($videoUiSource, 'name="Component" id="Component" value="Gallery"')
            && str_contains($videoUiSource, 'Browse computer'),
        'Video workspace keeps exact paired-record fields while adding automatic source preview, copy/open tools, rich description, CTA, schedule, and supporting images'
    );
    red_theme_test_assert(
        is_string($videoFormScript)
            && str_contains($videoFormScript, "provider: 'youtube'")
            && str_contains($videoFormScript, "provider: 'vimeo'")
            && str_contains($videoFormScript, "provider: 'external'")
            && str_contains($videoFormScript, "'https://www.youtube-nocookie.com/embed/'")
            && str_contains($videoFormScript, "'https://i.ytimg.com/vi/'")
            && str_contains($videoFormScript, "parsed.protocol !== 'https:'")
            && str_contains($videoFormScript, "parsed.searchParams.getAll('v')")
            && str_contains($videoFormScript, "parsed.searchParams.getAll('h')")
            && str_contains($videoFormScript, 'document.execCommand(\'copy\')')
            && str_contains($videoFormScript, 'window.tinymce.init(editorConfig')
            && !str_contains($videoFormScript, 'moxiemanager')
            && str_contains($videoFormScript, "payload.append('pic', file, file.name)")
            && str_contains($videoFormScript, "xhr.setRequestHeader('X-CSRF-Token'")
            && str_contains($videoFormScript, 'return requestSave(form);')
            && str_contains($videoFormScript, 'pendingEditUploads.promise.then(function ()')
            && str_contains($videoFormScript, "'Retrying supporting image uploads…'")
            && str_contains($videoFormScript, "T: 'gal'")
            && str_contains($videoFormScript, "String(data).trim() === 'yesyes'")
            && str_contains($videoFormScript, 'window.run_insert_gallery')
            && str_contains($videoFormScript, 'window.run_update_gallery')
            && is_string($videoUrlHelperSource)
            && str_contains($videoUrlHelperSource, "'provider' => 'youtube'")
            && str_contains($videoUrlHelperSource, "'provider' => 'vimeo'")
            && str_contains($videoUrlHelperSource, "'provider' => 'external'")
            && str_contains($insertGallerySource, "if (\$postedGalleryType === 'Video')")
            && str_contains($insertGallerySource, 'red_video_url_data($galleryPost[\'LongDesc\'] ?? \'\')')
            && str_contains($updateGallerySource, "if (\$existingGalleryType === 'Video'")
            && str_contains($updateGallerySource, "red_video_url_data(\$galleryPost['LongDesc'])")
            && str_contains($updateGallerySource, 'red_admin_article_full_record($db->connection, $artRecordId)')
            && str_contains($updateGallerySource, "!== 'Gallery'")
            && !str_contains($insertGallerySource, "\$galleryPost['LongDesc'] = red_video_url_normalize")
            && !str_contains($updateGallerySource, "\$galleryPost['LongDesc'] = red_video_url_normalize")
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-video-source-grid')
            && str_contains($controlPanelCss, '#advanced .red-admin-video-preview')
            && str_contains($controlPanelCss, '#advanced .red-admin-video-supporting-grid'),
        'Video recognition, privacy preview, thumbnail, clipboard fallback, free local editor, protected uploads, raw URL preservation, save/delete, and responsive styling stay wired together'
    );
    $mainMenuSource = file_get_contents(dirname(__DIR__) . '/class/class_main_menu.php');
    red_theme_test_assert(
        is_string($mainMenuSource)
            && str_contains($mainMenuSource, 'red_public_legacy_navigation_context_from_rows($menuRows)')
            && str_contains($mainMenuSource, '<details class="red-admin-menu-quicknav__disclosure">')
            && str_contains($mainMenuSource, 'aria-label="Quick site navigation"')
            && str_contains($mainMenuSource, 'url: "/admin/bin/edit_main_menu.php"')
            && str_contains($mainMenuSource, 'name="RecordID"')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '.red-admin-menu-quicknav__panel')
            && str_contains($controlPanelCss, '.red-admin-menu-quicknav__list--depth-1'),
        'Edit Content exposes compact hierarchical page navigation without changing the main-menu edit contract'
    );
    $mainMenuEditorSource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_main_menu.php');
    $mainMenuUpdateSource = file_get_contents(dirname(__DIR__) . '/admin/bin/update_main_menu.php');
    $mainMenuHelperSource = file_get_contents(dirname(__DIR__) . '/includes/admin_menu_helpers.php');
    $mainMenuEditorScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/main-menu-editor.js');
    red_theme_test_assert(
        is_string($mainMenuEditorSource)
            && str_contains($mainMenuEditorSource, 'class="cp red-admin-menu-editor"')
            && str_contains($mainMenuEditorSource, 'data-red-menu-editor')
            && str_contains($mainMenuEditorSource, "'linkName' => 'NewLabelLink'")
            && str_contains($mainMenuEditorSource, "'windowName' => 'NewLabelNewWindow'")
            && str_contains($mainMenuEditorSource, "'linkName' => 'NewSubLabelLink[")
            && str_contains($mainMenuEditorSource, "'linkName' => 'NewSubSubLabelLink[")
            && str_contains($mainMenuEditorSource, 'data-menu-link-picker')
            && str_contains($mainMenuEditorSource, 'data-menu-link-input')
            && str_contains($mainMenuEditorSource, 'data-menu-delete=')
            && str_contains($mainMenuEditorSource, 'red_csrf_input()')
            && !str_contains($mainMenuEditorSource, 'ico_trashcan.png')
            && !str_contains($mainMenuEditorSource, 'id="cp_accordion"'),
        'Edit Top Menu exposes polished first-save destination controls at all three levels while preserving record, hierarchy, title, language, and CSRF fields'
    );
    red_theme_test_assert(
        is_string($mainMenuHelperSource)
            && str_contains($mainMenuHelperSource, 'function red_admin_main_menu_link_choices')
            && str_contains($mainMenuHelperSource, 'section_area.RecordID=category_area.SectionRecordID')
            && str_contains($mainMenuHelperSource, 'category_area.RecordID=subcategory_area.CategoryRecordID')
            && str_contains($mainMenuHelperSource, 'red_admin_area_public_path($section, $category, $subCategory)')
            && str_contains($mainMenuHelperSource, 'function red_admin_main_menu_article_path')
            && is_string($mainMenuUpdateSource)
            && str_contains($mainMenuUpdateSource, "red_admin_menu_value(\$post, 'NewLabelLink')")
            && str_contains($mainMenuUpdateSource, "red_admin_menu_value(\$post, 'NewLabelNewWindow')"),
        'Menu destinations derive only from stored Section, Category, and Subcategory parents and new top-level records retain their chosen link and window behavior'
    );
    red_theme_test_assert(
        is_string($mainMenuEditorScript)
            && str_contains($mainMenuEditorScript, "url: '/admin/bin/update_main_menu.php'")
            && str_contains($mainMenuEditorScript, "url: '/admin/bin/edit_main_menu.php'")
            && str_contains($mainMenuEditorScript, "url: '/admin/bin/delete_label.php'")
            && str_contains($mainMenuEditorScript, 'window.run_update_main_menu')
            && str_contains($mainMenuEditorScript, '[data-menu-link-picker]')
            && str_contains($mainMenuEditorScript, 'document.body.dataset.menuNeedsRefresh')
            && is_string($controlPanelNavigationSource)
            && str_contains($controlPanelNavigationSource, '/admin/assets/js/main-menu-editor.js?v=')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-menu-editor__hero')
            && str_contains($controlPanelCss, '#advanced .red-admin-menu-item__fields')
            && str_contains($controlPanelCss, '#advanced .red-admin-menu-actions')
            && preg_match('/@media \(max-width: 560px\)\s*\{[^}]*#advanced \.red-admin-menu-editor-shell/s', $controlPanelCss) === 1
            && str_contains($controlPanelCss, '@media (prefers-reduced-motion: reduce)'),
        'Delegated destination selection, in-place refresh, delete and save endpoints, responsive hierarchy styling, and reduced-motion behavior remain wired together'
    );
    $featureSliderClassSource = file_get_contents(dirname(__DIR__) . '/class/class_feature_slider.php');
    $featureSliderEditorSource = file_get_contents(dirname(__DIR__) . '/admin/bin/edit_feature_slider.php');
    $featureSliderScript = file_get_contents(dirname(__DIR__) . '/admin/assets/js/feature-slider-editor.js');
    $featureHelperSource = file_get_contents(dirname(__DIR__) . '/includes/admin_feature_helpers.php');
    red_theme_test_assert(
        is_string($featureSliderClassSource)
            && str_contains($featureSliderClassSource, 'class="red-admin-slider-launcher"')
            && str_contains($featureSliderClassSource, 'window.redAdminOpenSliderEditor')
            && str_contains($featureSliderClassSource, '<span>Manage slides</span>')
            && str_contains($featureSliderClassSource, 'name="VarFeatures"')
            && str_contains($featureSliderClassSource, 'name="Query"')
            && str_contains($featureSliderClassSource, 'name="Language"')
            && is_string($featureSliderEditorSource)
            && str_contains($featureSliderEditorSource, 'class="cp red-admin-slider-form"')
            && str_contains($featureSliderEditorSource, 'data-slider-workspace')
            && str_contains($featureSliderEditorSource, 'name="sliderSelect[')
            && str_contains($featureSliderEditorSource, 'name="FeatureOrder[')
            && str_contains($featureSliderEditorSource, 'name="RecordID[')
            && str_contains($featureSliderEditorSource, 'data-slider-edit-article')
            && str_contains($featureSliderEditorSource, 'data-slider-filter')
            && str_contains($featureSliderEditorSource, 'red_csrf_input()')
            && !str_contains($featureSliderEditorSource, 'ico_edit.png')
            && !str_contains($featureSliderEditorSource, 'cp_viewall'),
        'Edit Slider uses the polished launcher and searchable Article-card workspace while preserving exact selection, order, record, scope, language, query, and CSRF fields'
    );
    red_theme_test_assert(
        is_string($featureSliderScript)
            && str_contains($featureSliderScript, "url: '/admin/bin/edit_feature_slider.php'")
            && str_contains($featureSliderScript, "url: '/admin/bin/update_feature_slider.php'")
            && str_contains($featureSliderScript, "url: '/admin/bin/edit_article.php'")
            && str_contains($featureSliderScript, 'VarPosition:')
            && str_contains($featureSliderScript, 'window.redAdminOpenSliderEditor')
            && str_contains($featureSliderScript, 'window.run_update_slider')
            && str_contains($featureSliderScript, 'data-slider-selected-count')
            && str_contains($featureSliderScript, ".red-admin-slider-launcher__button:disabled")
            && str_contains($featureSliderScript, "matchMedia('(prefers-reduced-motion: reduce)')")
            && is_string($featureHelperSource)
            && str_contains($featureHelperSource, 'function red_admin_feature_position_column')
            && str_contains($featureHelperSource, 'function red_admin_feature_scope_label')
            && str_contains($featureHelperSource, 'function red_admin_feature_list_contains')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-slider-launcher')
            && str_contains($controlPanelCss, '#advanced .red-admin-slider-card-grid')
            && str_contains($controlPanelCss, '#advanced .red-admin-slider-actions')
            && preg_match('/@media \(max-width: 767px\)\s*\{[^}]*#advanced \.red-admin-slider-launcher/s', $controlPanelCss) === 1,
        'Slider filtering, live selection feedback, corrected Article editing context, save endpoint, reduced motion, responsive controls, and scope helpers stay wired together'
    );
    $revisionMigrationSource = file_get_contents(
        dirname(__DIR__) . '/database/migrations/2026-07-23-content-revisions.sql'
    );
    $revisionHelperSource = file_get_contents(
        dirname(__DIR__) . '/includes/admin_content_revision_helpers.php'
    );
    $revisionEndpointSource = file_get_contents(
        dirname(__DIR__) . '/admin/bin/content_revisions.php'
    );
    $revisionScriptSource = file_get_contents(
        dirname(__DIR__) . '/admin/assets/js/content-revisions.js'
    );
    red_theme_test_assert(
        is_string($revisionMigrationSource)
            && str_contains($revisionMigrationSource, 'CREATE TABLE IF NOT EXISTS `RED_Content_Revisions`')
            && str_contains($revisionMigrationSource, 'UNIQUE KEY `uniq_red_content_revision_number`')
            && str_contains($revisionMigrationSource, '`Snapshot` mediumtext')
            && str_contains($revisionMigrationSource, '`SnapshotHash` char(64)')
            && is_string($revisionHelperSource)
            && str_contains($revisionHelperSource, 'function red_admin_content_revision_capture')
            && str_contains($revisionHelperSource, 'function red_admin_content_revision_transaction')
            && str_contains($revisionHelperSource, 'function red_admin_content_revision_restore')
            && str_contains($revisionHelperSource, 'red_admin_content_revision_same_shape')
            && str_contains($revisionHelperSource, 'red_admin_require_article_access') === false,
        'Content revisions use one immutable aggregate snapshot table and keep authorization at the protected endpoint boundary'
    );
    red_theme_test_assert(
        is_string($revisionEndpointSource)
            && str_contains($revisionEndpointSource, 'red_require_admin(true)')
            && str_contains($revisionEndpointSource, 'red_admin_require_article_access')
            && str_contains($revisionEndpointSource, "['list', 'restore']")
            && str_contains($revisionEndpointSource, 'CurrentHash')
            && is_string($revisionScriptSource)
            && str_contains($revisionScriptSource, "action: 'restore'")
            && str_contains($revisionScriptSource, 'Your current version will remain in history.')
            && str_contains($revisionScriptSource, 'window.location.reload()')
            && is_string($controlPanelNavigationSource)
            && str_contains($controlPanelNavigationSource, '/admin/assets/js/content-revisions.js?v=')
            && is_string($controlPanelCss)
            && str_contains($controlPanelCss, '#advanced .red-admin-revisions')
            && str_contains($controlPanelCss, '#advanced .red-admin-revision__restore'),
        'Authorized version listing, conflict-bound restore, non-destructive confirmation, responsive history UI, and shell loading stay connected'
    );
    $revisionedWriteSources = [
        file_get_contents(dirname(__DIR__) . '/admin/bin/update_content.php'),
        file_get_contents(dirname(__DIR__) . '/admin/bin/update_gallery.php'),
        file_get_contents(dirname(__DIR__) . '/admin/bin/update_form.php'),
    ];
    red_theme_test_assert(
        array_reduce(
            $revisionedWriteSources,
            static function ($carry, $source) {
                return $carry
                    && is_string($source)
                    && str_contains($source, 'red_admin_content_revision_transaction')
                    && str_contains($source, 'red_admin_content_revision_response_headers');
            },
            true
        )
            && str_contains(
                (string) file_get_contents(dirname(__DIR__) . '/admin/bin/post_file.php'),
                "'upload'"
            )
            && str_contains(
                (string) file_get_contents(dirname(__DIR__) . '/includes/admin_tool_helpers.php'),
                "'move'"
            )
            && str_contains(
                (string) file_get_contents(dirname(__DIR__) . '/includes/admin_article_helpers.php'),
                "'order'"
            ),
        'Article, paired Gallery/Form, media, Move Content, and order writes all enter the shared revision lifecycle'
    );
    $layoutDistributionEndpoint = (string) file_get_contents(
        dirname(__DIR__) . '/admin/bin/update_layout_distribution.php'
    );
    $layoutDistributionScript = (string) file_get_contents(
        dirname(__DIR__) . '/admin/assets/js/layout-distribution.js'
    );
    $articleHelperSource = (string) file_get_contents(
        dirname(__DIR__) . '/includes/admin_article_helpers.php'
    );
    $standardAdapterSource = (string) file_get_contents(
        dirname(__DIR__) . '/includes/theme_standard_adapter.php'
    );
    red_theme_test_assert(
        str_contains($articleHelperSource, 'function red_admin_article_distribution_expected_items')
            && str_contains($articleHelperSource, 'function red_admin_article_update_distribution_batch')
            && str_contains($articleHelperSource, "\$failureReason = 'conflict'")
            && str_contains($articleHelperSource, "\$positionChanged ? 'move' : 'order'")
            && str_contains($layoutDistributionEndpoint, 'red_require_admin(true)')
            && str_contains($layoutDistributionEndpoint, 'red_admin_require_article_ids_access')
            && str_contains($layoutDistributionEndpoint, 'ExpectedItems')
            && str_contains($layoutDistributionEndpoint, 'Items'),
        'Page distribution writes are atomic, stale-state checked, permission bounded, and revision aware'
    );
    red_theme_test_assert(
        str_contains($standardAdapterSource, 'data-red-editor-workspace="page-layout"')
            && str_contains($standardAdapterSource, 'data-red-position-column="')
            && str_contains($standardAdapterSource, 'data-red-layout-endpoint="/admin/bin/update_layout_distribution.php"')
            && str_contains($standardAdapterSource, 'Arrange the page directly.')
            && str_contains($controlPanelContentSource, 'data-red-layout-item="true"')
            && str_contains($controlPanelContentSource, 'class="red-admin-layout-item__handle" draggable="true"')
            && str_contains($controlPanelContentSource, 'data-red-layout-drag-handle="true"')
            && str_contains($controlPanelContentSource, 'data-red-layout-position-select="true"'),
        'Standard themes receive one core-owned draggable card workspace with visible placement and accessible arrangement controls'
    );
    red_theme_test_assert(
        str_contains($layoutDistributionScript, "root.addEventListener('dragstart'")
            && str_contains($layoutDistributionScript, "root.addEventListener('change'")
            && str_contains($layoutDistributionScript, 'data-red-layout-undo-button')
            && str_contains($controlPanelContentSource, 'Move up')
            && str_contains($layoutDistributionScript, 'This page changed in another window.')
            && str_contains($controlPanelNavigationSource, '/admin/assets/js/layout-distribution.js?v=')
            && str_contains($controlPanelCss, 'Page structure arrangement: core-owned and isolated from public themes.')
            && str_contains($controlPanelCss, '.red-admin-layout-item__menu-panel select'),
        'Drag, keyboard/touch fallback, undo, conflict feedback, responsive styling, and shell loading stay connected'
    );
    red_theme_test_assert(
        preg_match(
            '/#advanced \[data-red-editor-workspace="page-layout"\] \.red-admin-layout-item__menu\s*\{[^}]*margin:\s*0;[^}]*padding:\s*0;[^}]*border:\s*0;[^}]*background:\s*transparent;[^}]*\}/s',
            $controlPanelCss
        ) === 1
            && preg_match(
                '/#advanced \[data-red-editor-workspace="page-layout"\] \.red-admin-layout-item__menu > summary\s*\{[^}]*margin:\s*0;[^}]*min-height:\s*0;[^}]*width:\s*32px;[^}]*height:\s*30px;[^}]*\}/s',
                $controlPanelCss
            ) === 1,
        'Page-layout details and summary controls reset active-theme element styles while retaining the desktop 32 by 30 pixel menu contract'
    );
    red_theme_test_assert(
        str_contains(
            $controlPanelContentSource,
            "->cp_Article(\$componentInputs['position'], \$this->recordid, \$componentInputs['varPosition'], \$componentInputs['layout'], true)"
        )
            && str_contains(
                $controlPanelContentSource,
                "->cp_other(\$componentInputs['position'], \$this->recordid, \$componentInputs['varPosition'], \$componentInputs['layout'], true)"
            )
            && is_string($articleComponentSource)
            && str_contains($articleComponentSource, '$layout, $structuredEditor = false)')
            && substr_count($articleComponentSource, "\$position==='0' && !\$structuredEditor") === 2
            && str_contains($articleComponentSource, 'float:left; padding-right:5px; margin-right:5px;')
            && is_string($otherComponentSource)
            && str_contains($otherComponentSource, '$layout, $structuredEditor = false)')
            && substr_count($otherComponentSource, "\$position==='0' && !\$structuredEditor") === 2
            && str_contains($otherComponentSource, 'float:left; padding-right:5px; margin-right:5px;')
            && str_contains($controlPanelContentSource, "'float:left; padding-right:5px; margin-right:5px;'")
            && preg_match(
                '/#advanced \[data-red-editor-workspace="page-layout"\] \.red-admin-layout-item__editor\s*\{[^}]*display:\s*flow-root;[^}]*min-width:\s*0;[^}]*\}/s',
                $controlPanelCss
            ) === 1
            && str_contains(
                $controlPanelCss,
                '.red-admin-position__controls.cp_admin:not(.red-admin-position__controls--hidden)'
            ),
        'Structured hidden Article and Other editors stay in normal flow while legacy non-structured position 0 wrappers remain available'
    );
    echo 'Theme contract self-test passed: ' . $assertionCount . ' assertions.' . PHP_EOL;
} finally {
    red_theme_test_remove_tree($projectRoot);
    red_theme_test_remove_tree($outsideRoot);
}
