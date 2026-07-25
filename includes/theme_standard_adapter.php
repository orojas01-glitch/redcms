<?php
/**
 * Production adapter for validated file-based standard themes.
 *
 * The standard package owns document, region, layout, component, and asset
 * markup. Core continues to prepare routes and live content. During this first
 * activation phase, component operations are preserved through the audited
 * legacy component contexts/views before their HTML enters standard wrappers.
 */

final class RedStandardThemeAdapter
{
    private $projectRoot;
    private $themeRoot;
    private $themeId;
    private $manifest;
    private $documentSource;
    private $regionSources = [];
    private $layoutSources = [];
    private $componentSources = [];
    private $legacyComponentSources = [];
    private $advancedItems = null;
    private $breadcrumbsEnabled = true;
    private $customLayoutCache = [];

    public function __construct($projectRoot, array $manifest)
    {
        $resolvedRoot = realpath((string) $projectRoot);
        $themeId = isset($manifest['id']) && is_string($manifest['id']) ? $manifest['id'] : '';
        if ($resolvedRoot === false
            || !is_dir($resolvedRoot)
            || !red_theme_valid_id($themeId)
            || ($manifest['type'] ?? '') !== 'standard'
        ) {
            throw new InvalidArgumentException('A validated standard theme manifest is required.');
        }

        $themeRoot = realpath(red_theme_root($resolvedRoot) . DIRECTORY_SEPARATOR . $themeId);
        if ($themeRoot === false || !is_dir($themeRoot)) {
            throw new RuntimeException('The standard theme directory is unavailable.');
        }

        $this->projectRoot = $resolvedRoot;
        $this->themeRoot = $themeRoot;
        $this->themeId = $themeId;
        $this->manifest = $manifest;
        $this->breadcrumbsEnabled = red_theme_standard_breadcrumbs_enabled($manifest);
        $productionValidation = red_theme_standard_production_validation($manifest, $themeRoot);
        if (empty($productionValidation['valid'])) {
            throw new RuntimeException(
                'The standard theme production contract is invalid: ' .
                implode(' ', $productionValidation['errors'])
            );
        }
        $production = $manifest['production'];
        $this->documentSource = $this->declaredView($production['regions']['document'] ?? null, 'document');
        foreach (['header', 'navigation', 'hero', 'footer'] as $regionId) {
            $this->regionSources[$regionId] = $this->declaredView(
                $production['regions'][$regionId] ?? null,
                'region ' . $regionId
            );
        }
        foreach ($production['layouts'] as $layoutId => $definition) {
            $this->layoutSources[$layoutId] = $this->declaredView(
                $definition,
                'layout ' . $layoutId
            );
        }
        foreach (['Article', 'Form', 'Gallery', 'Other'] as $componentId) {
            $this->componentSources[$componentId] = $this->declaredView(
                $production['components'][$componentId] ?? null,
                'component ' . $componentId
            );
            $legacySource = red_theme_existing_path(
                $this->projectRoot,
                'themes/legacy-bootstrap/components/' . strtolower($componentId) . '.php'
            );
            if ($legacySource === null || !is_file($legacySource)) {
                throw new RuntimeException('The compatibility component bridge is unavailable.');
            }
            $this->legacyComponentSources[$componentId] = $legacySource;
        }
    }

    private function declaredView($definition, $label)
    {
        $relativePath = is_array($definition) ? ($definition['template'] ?? '') : '';
        $source = red_theme_existing_path($this->themeRoot, $relativePath);
        if ($source === null
            || !is_file($source)
            || strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'php'
        ) {
            throw new RuntimeException('Standard theme ' . $label . ' template is unavailable.');
        }

        return $source;
    }

    private function resolvedLayoutId($layoutId)
    {
        return red_theme_layout_resolve_id($this->manifest, is_string($layoutId) ? trim($layoutId) : '');
    }

    private function customLayoutDefinition($layoutId)
    {
        $layoutId = is_string($layoutId) ? trim($layoutId) : '';
        if (!red_custom_layout_valid_id($layoutId)
            || !class_exists('connection')
            || !defined('DBHOST')
            || !defined('DBUSER')
            || !defined('DBPASS')
            || !defined('DBNAME')
        ) {
            return null;
        }
        if (array_key_exists($layoutId, $this->customLayoutCache)) {
            return $this->customLayoutCache[$layoutId];
        }

        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $definition = red_custom_layout_published_definition($db->connection, $layoutId);
        } finally {
            $db->close();
        }
        $this->customLayoutCache[$layoutId] = $definition;

        return $definition;
    }

    public function supportsPublicLayout($layoutId)
    {
        $resolvedLayoutId = $this->resolvedLayoutId($layoutId);
        if ($resolvedLayoutId !== null && isset($this->layoutSources[$resolvedLayoutId])) {
            return true;
        }

        return $this->customLayoutDefinition($layoutId) !== null;
    }

    public function publicLayoutIds()
    {
        $ids = array_keys(red_theme_layout_manifest_catalog($this->manifest));
        if (class_exists('connection')
            && defined('DBHOST')
            && defined('DBUSER')
            && defined('DBPASS')
            && defined('DBNAME')
        ) {
            $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
            try {
                $ids = array_merge($ids, array_keys(red_custom_layout_published_catalog($db->connection)));
            } finally {
                $db->close();
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);

        return $ids;
    }

    private function capture(callable $renderer)
    {
        $level = ob_get_level();
        ob_start();
        try {
            $renderer();
            $html = ob_get_clean();
            if (!is_string($html)) {
                throw new RuntimeException('Standard theme capture failed.');
            }
            return $html;
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $exception;
        }
    }

    private function themeAssetUrl($path)
    {
        if (!red_theme_valid_relative_path($path)
            || red_theme_existing_path($this->themeRoot, $path) === null
        ) {
            throw new RuntimeException('Standard theme asset path is unsafe or missing.');
        }

        $segments = array_map('rawurlencode', explode('/', str_replace('\\', '/', $path)));
        return '/themes/' . rawurlencode($this->themeId) . '/' . implode('/', $segments);
    }

    private function assetHtml($location)
    {
        if (!in_array($location, ['head', 'body-end'], true)) {
            throw new InvalidArgumentException('Standard theme asset location is invalid.');
        }

        $html = '';
        if ($location === 'head') {
            $html .= '<link rel="stylesheet" href="/css/forms.css?v=' . time() . '" type="text/css" media="screen">' . "\n";
            $customLayoutCss = $this->projectRoot . '/css/public-custom-layout.css';
            if (!is_file($customLayoutCss)) {
                throw new RuntimeException('The core custom-layout stylesheet is unavailable.');
            }
            $html .= '<link rel="stylesheet" href="/css/public-custom-layout.css?v=' .
                rawurlencode((string) filemtime($customLayoutCss)) . '">' . "\n";
        }

        foreach (['styles', 'scripts'] as $group) {
            $assets = array_merge(
                (array) ($this->manifest['assets'][$group] ?? []),
                (array) ($this->manifest['production']['assets'][$group] ?? [])
            );
            foreach ($assets as $asset) {
                if (!is_array($asset) || ($asset['location'] ?? '') !== $location) {
                    continue;
                }
                $url = isset($asset['url']) && is_string($asset['url'])
                    ? $asset['url']
                    : $this->themeAssetUrl((string) ($asset['path'] ?? ''));
                $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                if ($group === 'styles') {
                    $html .= '<link rel="stylesheet" href="' . $escapedUrl . '?v=' . time() . '">' . "\n";
                    continue;
                }

                $attributes = '';
                if (!empty($asset['integrity']) && is_string($asset['integrity'])) {
                    $attributes .= ' integrity="' . htmlspecialchars($asset['integrity'], ENT_QUOTES, 'UTF-8') . '"';
                    $attributes .= ' crossorigin="anonymous"';
                }
                $html .= '<script src="' . $escapedUrl . '"' . $attributes . '></script>' . "\n";
            }
        }

        if ($location === 'head') {
            $html .= '<link rel="stylesheet" href="/css/public-gallery.css?v=' . time() . '">' . "\n";
        } else {
            $html .= '<script src="/js/public-gallery.js?v=' . time() . '"></script>' . "\n";
        }

        if ($location === 'head') {
            $html .= '<script src="/js/jquery-3.7.1.min.js"></script>' . "\n";
        }

        return $html;
    }

    private function advancedItems()
    {
        if (is_array($this->advancedItems)) {
            return $this->advancedItems;
        }

        require_once $this->projectRoot . '/includes/public_render_helpers.php';
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $items = red_public_advanced_items(
                $db->connection,
                [
                    'Website_Title',
                    'Website_Logo',
                    'Website_Header',
                    'Website_Footer',
                    'Website_Red_Sphere_Credit',
                ]
            );
        } finally {
            $db->close();
        }
        $this->advancedItems = [
            'Website_Title' => trim((string) ($items['Website_Title'] ?? '')),
            'Website_Logo' => trim((string) ($items['Website_Logo'] ?? '')),
            'Website_Header' => (string) ($items['Website_Header'] ?? ''),
            'Website_Footer' => (string) ($items['Website_Footer'] ?? ''),
            'Website_Red_Sphere_Credit' => (string) ($items['Website_Red_Sphere_Credit'] ?? 'Y'),
        ];

        return $this->advancedItems;
    }

    private function pageTitleHtml()
    {
        if (!class_exists('Page_Title')) {
            throw new RuntimeException('The public page-title provider is unavailable.');
        }
        return $this->capture(function () {
            $page = new Page_Title();
            $page->Title();
        });
    }

    private function metaHtml()
    {
        if (!class_exists('Page_Metatags')) {
            throw new RuntimeException('The public metadata provider is unavailable.');
        }
        return $this->capture(function () {
            $page = new Page_Metatags();
            $page->Metatags();
        });
    }

    private function adminOverlayHtml()
    {
        $source = $this->projectRoot . '/admin/mainnav.php';
        return $this->capture(function () use ($source) {
            include $source;
        });
    }

    private function renderDocumentPhase($phase)
    {
        if (!in_array($phase, ['start', 'end'], true)) {
            throw new InvalidArgumentException('Standard document phase is invalid.');
        }

        $redThemeDocumentContext = [
            'mode' => 'production',
            'phase' => $phase,
            'language' => defined('language') ? (string) language : 'sp',
            'titleHtml' => $phase === 'start' ? $this->pageTitleHtml() : '',
            'metaHtml' => $phase === 'start' ? $this->metaHtml() : '',
            'headAssetsHtml' => $phase === 'start' ? $this->assetHtml('head') : '',
            'bodyAssetsHtml' => $phase === 'end' ? $this->assetHtml('body-end') : '',
            'adminOverlayHtml' => $phase === 'start' ? $this->adminOverlayHtml() : '',
            'themeId' => $this->themeId,
        ];
        require $this->documentSource;
    }

    public function renderDocumentStart()
    {
        $this->renderDocumentPhase('start');
    }

    public function renderHeaderBundle()
    {
        require_once $this->projectRoot . '/includes/public_theme_helpers.php';
        require_once $this->projectRoot . '/includes/site_logo_helpers.php';
        $advanced = $this->advancedItems();
        $redThemeHeaderContext = [
            'mode' => 'production',
            'homeUrl' => '/',
            'siteTitle' => $advanced['Website_Title'] !== '' ? $advanced['Website_Title'] : 'RED-CMS',
            'logo' => red_site_logo_public_context($this->projectRoot, $advanced['Website_Logo']),
            'customHtml' => $advanced['Website_Header'],
        ];
        require $this->regionSources['header'];

        $navigation = red_public_legacy_navigation_context();
        $redThemeNavigationContext = [
            'mode' => 'production',
            'items' => $navigation['items'],
            'breadcrumbsEnabled' => $this->breadcrumbsEnabled,
        ];
        require $this->regionSources['navigation'];

        $hero = red_public_legacy_hero_context();
        $redThemeHeroContext = [
            'mode' => 'production',
            'enabled' => !empty($hero['enabled']),
            'slides' => is_array($hero['slides'] ?? null) ? $hero['slides'] : [],
        ];
        require $this->regionSources['hero'];
    }

    private function breadcrumbLabel($connection, $table, $alias)
    {
        $label = red_public_plain_text(red_public_breadcrumb_title($connection, $table, $alias));
        return $label !== '' ? $label : ucwords(str_replace(['-', '_'], ' ', (string) $alias));
    }

    private function breadcrumbContext()
    {
        if (!$this->breadcrumbsEnabled) {
            return [];
        }

        require_once $this->projectRoot . '/includes/public_render_helpers.php';
        $section = defined('section') ? (string) section : 'home';
        $category = defined('category') ? (string) category : '';
        $subcategory = defined('subcategory') ? (string) subcategory : '';
        $article = defined('article') ? (string) article : '';
        if ($section === 'home' && $article === '') {
            return [];
        }

        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $items = [[
                'label' => $this->breadcrumbLabel($db->connection, 'Sections', 'Home'),
                'url' => '/',
            ]];
            if ($section !== 'home') {
                $items[] = [
                    'label' => $this->breadcrumbLabel($db->connection, 'Sections', $section),
                    'url' => ($category !== '' || $subcategory !== '' || $article !== '')
                        ? '/' . rawurlencode($section) . '/'
                        : '',
                ];
            }
            if ($category !== '') {
                $items[] = [
                    'label' => $this->breadcrumbLabel($db->connection, 'Categories', $category),
                    'url' => ($subcategory !== '' || $article !== '')
                        ? '/' . rawurlencode($section) . '/' . rawurlencode($category) . '/'
                        : '',
                ];
            }
            if ($subcategory !== '') {
                $items[] = [
                    'label' => $this->breadcrumbLabel($db->connection, 'SubCategories', $subcategory),
                    'url' => $article !== ''
                        ? '/' . rawurlencode($section) . '/' . rawurlencode($category) . '/' . rawurlencode($subcategory) . '/'
                        : '',
                ];
            }
            if ($article !== '') {
                $items[] = [
                    'label' => $this->breadcrumbLabel($db->connection, 'Articles', $article),
                    'url' => '',
                ];
            }
        } finally {
            $db->close();
        }

        $deduplicated = [];
        foreach ($items as $item) {
            $lastIndex = count($deduplicated) - 1;
            if ($lastIndex >= 0
                && strtolower(trim((string) $deduplicated[$lastIndex]['label'])) ===
                    strtolower(trim((string) $item['label']))
            ) {
                $deduplicated[$lastIndex] = $item;
                continue;
            }
            $deduplicated[] = $item;
        }

        return $deduplicated;
    }

    public function renderPublicLayout($layoutId, $articleQuery, $varFeatures, $varPosition, $limit)
    {
        $assignedLayoutId = is_string($layoutId) ? trim($layoutId) : '';
        $resolvedLayoutId = $this->resolvedLayoutId($assignedLayoutId);
        $customDefinition = null;
        if ($resolvedLayoutId === null || !isset($this->layoutSources[$resolvedLayoutId])) {
            $customDefinition = $this->customLayoutDefinition($assignedLayoutId);
        }
        if (($resolvedLayoutId === null || !isset($this->layoutSources[$resolvedLayoutId]))
            && $customDefinition === null
        ) {
            throw new InvalidArgumentException('Standard public layout id is unsupported.');
        }

        $definition = $customDefinition !== null
            ? $customDefinition
            : red_theme_layout_definition($this->manifest, $assignedLayoutId);
        $positionIds = array_keys($definition['positions']);
        $slots = [];
        foreach ($positionIds as $position) {
            $slots[(int) $position] = $this->capture(function () use (
                $articleQuery,
                $varFeatures,
                $varPosition,
                $position,
                $assignedLayoutId,
                $limit
            ) {
                if (!class_exists('content')) {
                    throw new RuntimeException('The CMS content renderer is unavailable.');
                }
                $content = new content();
                $content->articles(
                    $articleQuery,
                    $varFeatures,
                    $varPosition,
                    (string) $position,
                    $assignedLayoutId,
                    $limit
                );
            });
        }

        $redThemeLayoutContext = [
            'mode' => 'production',
            'layout' => $customDefinition !== null ? $assignedLayoutId : $resolvedLayoutId,
            'assignedLayout' => $assignedLayoutId,
            'label' => $definition['label'],
            'positions' => $definition['positions'],
            'breadcrumb' => $this->breadcrumbContext(),
            'slots' => $slots,
        ];
        if ($customDefinition !== null) {
            $this->renderPublicCustomLayoutMarkup($customDefinition, $redThemeLayoutContext);
            return;
        }
        require $this->layoutSources[$resolvedLayoutId];
    }

    private function renderPublicCustomLayoutMarkup(array $definition, array $context)
    {
        $layoutId = htmlspecialchars((string) ($definition['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $layoutLabel = htmlspecialchars((string) ($definition['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $grid = red_custom_layout_normalize_definition($definition['grid'] ?? []);
        echo '<main id="main-content" class="red-custom-layout" tabindex="-1"';
        echo ' data-red-custom-layout="' . $layoutId . '" aria-label="' . $layoutLabel . '">';

        if (($context['breadcrumb'] ?? []) !== []) {
            echo '<nav class="red-custom-layout__breadcrumb" aria-label="Breadcrumb"><ol>';
            foreach ($context['breadcrumb'] as $item) {
                $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                $url = htmlspecialchars((string) ($item['url'] ?? ''), ENT_QUOTES, 'UTF-8');
                echo '<li>';
                if ($url !== '') {
                    echo '<a href="' . $url . '">' . $label . '</a>';
                } else {
                    echo '<span aria-current="page">' . $label . '</span>';
                }
                echo '</li>';
            }
            echo '</ol></nav>';
        }

        echo '<div class="red-custom-layout__container">';
        foreach ($grid['rows'] as $rowIndex => $row) {
            echo '<div class="red-custom-layout__row" data-red-custom-layout-row="' .
                ((int) $rowIndex + 1) . '">';
            foreach ($row['columns'] as $column) {
                $position = (int) $column['position'];
                $span = (int) $column['span'];
                $label = htmlspecialchars((string) $column['label'], ENT_QUOTES, 'UTF-8');
                echo '<section class="red-custom-layout__slot" style="--red-custom-layout-span:' .
                    $span . '" data-red-custom-layout-position="' . $position . '" aria-label="' .
                    $label . '">';
                echo (string) ($context['slots'][$position] ?? '');
                echo '</section>';
            }
            echo '</div>';
        }
        echo '</div></main>';
    }

    private function controlPanelSlotHtml(
        $articleQuery,
        $varFeatures,
        $varPosition,
        $position,
        $assignedLayoutId,
        $limit,
        $table,
        $hidden = false
    ) {
        return $this->capture(function () use (
            $articleQuery,
            $varFeatures,
            $varPosition,
            $position,
            $assignedLayoutId,
            $limit,
            $table,
            $hidden
        ) {
            if (!class_exists('content')) {
                throw new RuntimeException('The CMS control-panel content renderer is unavailable.');
            }

            $position = (string) $position;
            $escapedPosition = htmlspecialchars($position, ENT_QUOTES, 'UTF-8');
            $slotHtml = $this->capture(function () use (
                $articleQuery,
                $varFeatures,
                $varPosition,
                $position,
                $assignedLayoutId,
                $limit,
                $table
            ) {
                $content = new content();
                $content->cp_articles(
                    $articleQuery,
                    $varFeatures,
                    $varPosition,
                    $position,
                    $assignedLayoutId,
                    $limit,
                    $table,
                    null,
                    true
                );
            });
            $empty = trim($slotHtml) === '';
            $classes = ['red-admin-position__controls', 'cp_admin'];
            if ($hidden) {
                $classes[] = 'red-admin-position__controls--hidden';
            }
            if ($empty) {
                $classes[] = 'red-admin-position__controls--empty';
            }

            echo '<div class="' . implode(' ', $classes) . '" data-red-editor-slot-content="true">';
            if ($empty) {
                echo '<p class="red-admin-position__empty">No content is assigned to this position.</p>';
            } else {
                echo $slotHtml;
            }
            echo '<span id="msggbox_alert_' . $escapedPosition . '" class="red-admin-position__message" aria-live="polite" style="display:none;"></span>';
            echo '</div>';
        });
    }

    private function renderControlPanelWorkspace(
        $assignedLayoutId,
        array $definition,
        array $slots,
        $hiddenSlot,
        $varPosition
    ) {
        $layoutLabel = (string) ($definition['label'] ?? $assignedLayoutId);
        $positionCount = count($definition['positions']);
        $escapedLayoutId = htmlspecialchars((string) $assignedLayoutId, ENT_QUOTES, 'UTF-8');
        $escapedLayoutLabel = htmlspecialchars($layoutLabel, ENT_QUOTES, 'UTF-8');
        $escapedVarPosition = htmlspecialchars((string) $varPosition, ENT_QUOTES, 'UTF-8');
        $workspaceLabel = htmlspecialchars(
            'Edit content positions for ' . $layoutLabel,
            ENT_QUOTES,
            'UTF-8'
        );
        $safeLayoutId = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $assignedLayoutId);
        if (!is_string($safeLayoutId) || $safeLayoutId === '') {
            $safeLayoutId = 'layout';
        }

        echo '<div class="red-admin-workspace" data-red-editor-workspace="page-layout" data-red-layout-id="' . $escapedLayoutId . '" data-red-position-column="' . $escapedVarPosition . '" data-red-layout-endpoint="/admin/bin/update_layout_distribution.php" role="region" aria-label="' . $workspaceLabel . '">';
        echo '<div class="red-admin-workspace__header">';
        echo '<div class="red-admin-workspace__title">';
        echo '<span class="red-admin-workspace__eyebrow">Page structure</span>';
        echo '<strong>' . $escapedLayoutLabel . '</strong>';
        echo '</div>';
        echo '<span class="red-admin-workspace__count">' . $positionCount . ' editable ' . ($positionCount === 1 ? 'position' : 'positions') . '</span>';
        echo '</div>';
        echo '<div class="red-admin-workspace__arrange-guide">';
        echo '<span class="red-admin-workspace__arrange-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 5h11M9 12h11M9 19h11"></path><circle cx="4" cy="5" r="1.5"></circle><circle cx="4" cy="12" r="1.5"></circle><circle cx="4" cy="19" r="1.5"></circle></svg></span>';
        echo '<p><strong>Arrange the page directly.</strong> Drag cards between positions or use each card&rsquo;s Arrange menu on touch and keyboard devices.</p>';
        echo '<span class="red-admin-workspace__save-status" data-red-layout-status="true" role="status" aria-live="polite"></span>';
        echo '</div>';
        echo '<div class="red-admin-workspace__undo" data-red-layout-undo="true" hidden>';
        echo '<span data-red-layout-undo-message="true">Layout updated.</span>';
        echo '<button type="button" data-red-layout-undo-button="true">Undo</button>';
        echo '</div>';
        echo '<div class="red-admin-workspace__positions">';

        foreach ($definition['positions'] as $position => $label) {
            $position = (string) $position;
            $escapedPosition = htmlspecialchars($position, ENT_QUOTES, 'UTF-8');
            $escapedLabel = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
            $headingId = 'red-admin-position-' . $safeLayoutId . '-' . $position;
            $escapedHeadingId = htmlspecialchars($headingId, ENT_QUOTES, 'UTF-8');

            echo '<div class="red-admin-position" data-red-editor-position="' . $escapedPosition . '" data-red-position-label="' . $escapedLabel . '" role="group" aria-labelledby="' . $escapedHeadingId . '">';
            echo '<div class="red-admin-position__header" id="' . $escapedHeadingId . '">';
            echo '<span class="red-admin-position__number" aria-hidden="true">' . $escapedPosition . '</span>';
            echo '<span class="red-admin-position__label">' . $escapedLabel . '</span>';
            echo '</div>';
            echo $slots[(int) $position] ?? '';
            echo '</div>';
        }

        echo '</div>';
        if (is_string($hiddenSlot) && $hiddenSlot !== '') {
            echo '<details class="red-admin-hidden" data-red-editor-position="0" data-red-position-label="Hidden">';
            echo '<summary>';
            echo '<span class="red-admin-hidden__number" aria-hidden="true">0</span>';
            echo '<span class="red-admin-hidden__title">Hidden content</span>';
            echo '<span class="red-admin-hidden__hint">Not shown on the public page</span>';
            echo '</summary>';
            echo '<div class="red-admin-hidden__body">' . $hiddenSlot . '</div>';
            echo '</details>';
        }
        echo '</div>';
    }

    public function renderControlPanelLayout($layoutId, $articleQuery, $varFeatures, $varPosition, $limit, $table)
    {
        $assignedLayoutId = is_string($layoutId) ? trim($layoutId) : '';
        $resolvedLayoutId = $this->resolvedLayoutId($assignedLayoutId);
        $customDefinition = null;
        if ($resolvedLayoutId === null || !isset($this->layoutSources[$resolvedLayoutId])) {
            $customDefinition = $this->customLayoutDefinition($assignedLayoutId);
        }
        if (($resolvedLayoutId === null || !isset($this->layoutSources[$resolvedLayoutId]))
            && $customDefinition === null
        ) {
            throw new InvalidArgumentException('Standard control-panel layout id is unsupported.');
        }

        $definition = $customDefinition !== null
            ? $customDefinition
            : red_theme_layout_definition($this->manifest, $assignedLayoutId);
        $slots = [];
        foreach ($definition['positions'] as $position => $label) {
            $slots[(int) $position] = $this->controlPanelSlotHtml(
                $articleQuery,
                $varFeatures,
                $varPosition,
                $position,
                $assignedLayoutId,
                $limit,
                $table
            );
        }

        $hiddenSlot = null;
        if ($definition['hiddenPosition'] === 0) {
            $hiddenSlot = $this->controlPanelSlotHtml(
                $articleQuery,
                $varFeatures,
                $varPosition,
                0,
                $assignedLayoutId,
                $limit,
                $table,
                true
            );
        }

        // The editor is CMS-owned and intentionally independent from public
        // theme markup. This prevents any installed theme's spacing, surfaces,
        // or layout selectors from leaking into the Edit Content workspace.
        $this->renderControlPanelWorkspace(
            $assignedLayoutId,
            $definition,
            $slots,
            $hiddenSlot,
            $varPosition
        );
    }

    private function renderStandardComponent($componentId, $html)
    {
        $context = ['mode' => 'production', 'html' => $html];
        switch ($componentId) {
            case 'Article':
                $redThemeArticleContext = $context;
                require $this->componentSources[$componentId];
                return;
            case 'Form':
                $redThemeFormContext = $context;
                require $this->componentSources[$componentId];
                return;
            case 'Gallery':
                $redThemeGalleryContext = $context;
                require $this->componentSources[$componentId];
                return;
            case 'Other':
                $redThemeOtherContext = $context;
                require $this->componentSources[$componentId];
                return;
        }
        throw new InvalidArgumentException('Standard component id is unsupported.');
    }

    public function renderPublicArticleComponent(array $inputs)
    {
        global $URL;
        require_once $this->projectRoot . '/includes/public_render_helpers.php';
        require_once $this->projectRoot . '/includes/legacy_component_helpers.php';
        $inventory = red_legacy_public_component_input_inventory();
        if (array_keys($inputs) !== $inventory['Article']) {
            throw new InvalidArgumentException('Invalid standard Article inputs.');
        }
        $legacyContext = red_legacy_public_article_view_context(
            $inputs['recordId'],
            $inputs['layout'],
            $inputs['article'],
            $inputs['position'],
            isset($URL) ? (string) $URL : ''
        );
        $source = $this->legacyComponentSources['Article'];
        $html = $this->capture(function () use ($legacyContext, $source) {
            $redThemeArticleContext = $legacyContext;
            require $source;
        });
        $this->renderStandardComponent('Article', $html);
    }

    public function renderPublicFormComponent(array $inputs)
    {
        require_once $this->projectRoot . '/includes/public_render_helpers.php';
        require_once $this->projectRoot . '/includes/legacy_component_helpers.php';
        $inventory = red_legacy_public_component_input_inventory();
        if (array_keys($inputs) !== $inventory['Form']) {
            throw new InvalidArgumentException('Invalid standard Form inputs.');
        }
        $legacyContext = red_legacy_public_form_context($inputs['recordId']);
        $source = $this->legacyComponentSources['Form'];
        $html = $this->capture(function () use ($legacyContext, $source) {
            $redThemeFormContext = $legacyContext;
            require $source;
        });
        $this->renderStandardComponent('Form', $html);
    }

    public function renderPublicGalleryComponent(array $inputs)
    {
        require_once $this->projectRoot . '/includes/public_render_helpers.php';
        require_once $this->projectRoot . '/includes/legacy_component_helpers.php';
        $inventory = red_legacy_public_component_input_inventory();
        if (array_keys($inputs) !== $inventory['Gallery']) {
            throw new InvalidArgumentException('Invalid standard Gallery inputs.');
        }
        $legacyContext = red_legacy_public_gallery_context(
            $inputs['recordId'],
            $inputs['layout'],
            $inputs['position']
        );
        $source = $this->legacyComponentSources['Gallery'];
        $html = $this->capture(function () use ($legacyContext, $source) {
            $redThemeGalleryContext = $legacyContext;
            require $source;
        });
        $this->renderStandardComponent('Gallery', $html);
    }

    public function renderPublicOtherComponent(array $inputs)
    {
        require_once $this->projectRoot . '/includes/public_render_helpers.php';
        require_once $this->projectRoot . '/includes/legacy_component_helpers.php';
        $inventory = red_legacy_public_component_input_inventory();
        if (array_keys($inputs) !== $inventory['Other']) {
            throw new InvalidArgumentException('Invalid standard Other inputs.');
        }
        $legacyContext = red_legacy_public_other_view_context(
            $inputs['recordId'],
            $inputs['layout'],
            $inputs['article'],
            $inputs['position']
        );
        $source = $this->legacyComponentSources['Other'];
        $html = $this->capture(function () use ($legacyContext, $source) {
            $redThemeOtherContext = $legacyContext;
            require $source;
        });
        $this->renderStandardComponent('Other', $html);
    }

    public function renderFooter()
    {
        $advanced = $this->advancedItems();
        $siteTitle = $advanced['Website_Title'] !== '' ? $advanced['Website_Title'] : 'RED-CMS';
        $redThemeFooterContext = [
            'mode' => 'production',
            'copyright' => '© ' . date('Y') . ' ' . $siteTitle,
            'customHtml' => $advanced['Website_Footer'],
            'redSphereCreditEnabled' => red_public_red_sphere_credit_enabled(
                $advanced['Website_Red_Sphere_Credit']
            ),
        ];
        require $this->regionSources['footer'];
    }

    public function renderDocumentEnd()
    {
        $this->renderDocumentPhase('end');
    }
}
