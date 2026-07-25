<?php
/**
 * Transitional adapter for the current RED-CMS Bootstrap renderer.
 *
 * The adapter deliberately prepares current CMS data for theme-owned
 * compatibility views while legacy layout and component classes are moved
 * behind portable boundaries in later verified batches.
 */
final class RedLegacyBootstrapThemeAdapter
{
    private $projectRoot;
    private $manifest;
    private $documentSource;
    private $headerSource;
    private $navigationSource;
    private $heroSource;
    private $footerSource;
    private $articleComponentSource;
    private $formComponentSource;
    private $galleryComponentSource;
    private $otherComponentSource;
    private $layoutSources = [];

    public function __construct($projectRoot, array $manifest)
    {
        $resolved = realpath((string) $projectRoot);
        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('A valid RED-CMS project root is required.');
        }

        $this->projectRoot = $resolved;
        $this->manifest = $manifest;
        $this->documentSource = $this->regionSource($manifest, 'document');
        $this->headerSource = $this->regionSource($manifest, 'header');
        $this->navigationSource = $this->regionSource($manifest, 'navigation');
        $this->heroSource = $this->regionSource($manifest, 'hero');
        $this->footerSource = $this->regionSource($manifest, 'footer');
        $this->articleComponentSource = $this->componentSource($manifest, 'Article');
        $this->formComponentSource = $this->componentSource($manifest, 'Form');
        $this->galleryComponentSource = $this->componentSource($manifest, 'Gallery');
        $this->otherComponentSource = $this->componentSource($manifest, 'Other');
        foreach (array_keys(red_theme_layout_manifest_catalog($manifest)) as $layoutId) {
            $this->layoutSources[$layoutId] = $this->layoutSource($manifest, $layoutId);
        }
    }

    private function sourcePath($relativePath)
    {
        if (!function_exists('red_theme_existing_path')) {
            throw new RuntimeException('Theme helpers must be loaded before the legacy adapter.');
        }

        $source = red_theme_existing_path($this->projectRoot, $relativePath);
        if ($source === null || !is_file($source)) {
            throw new RuntimeException('Legacy theme source is missing: ' . $relativePath);
        }

        return $source;
    }

    private function regionSource(array $manifest, $regionId)
    {
        $region = $manifest['regions'][$regionId] ?? null;
        $relativePath = is_array($region) ? ($region['legacySource'] ?? '') : '';

        return $this->sourcePath($relativePath);
    }

    private function layoutSource(array $manifest, $layoutId)
    {
        $layout = $manifest['layouts'][$layoutId] ?? null;
        $relativePath = is_array($layout) ? ($layout['legacySource'] ?? '') : '';

        return $this->sourcePath($relativePath);
    }

    private function componentSource(array $manifest, $componentId)
    {
        $component = $manifest['components'][$componentId] ?? null;
        $relativePath = is_array($component) ? ($component['legacySource'] ?? '') : '';

        return $this->sourcePath($relativePath);
    }

    private function renderDocumentPhase($phase)
    {
        if (!in_array($phase, ['start', 'end'], true)) {
            throw new InvalidArgumentException('Unsupported legacy document render phase.');
        }

        $redThemeDocumentPhase = $phase;
        require $this->documentSource;
    }

    public function renderDocumentStart()
    {
        $this->renderDocumentPhase('start');
    }

    /**
     * The current header partial includes the manifest-declared navigation and
     * hero partials in their original compatibility positions.
     */
    public function renderHeaderBundle()
    {
        require_once $this->sourcePath('includes/public_theme_helpers.php');
        $redThemeHeaderContext = red_public_legacy_header_context();
        $redThemeNavigationContext = red_public_legacy_navigation_context();
        $redThemeHeroContext = red_public_legacy_hero_context();
        $redThemeNavigationSource = $this->navigationSource;
        $redThemeHeroSource = $this->heroSource;
        require $this->headerSource;
        // Preserve the historical blank lines between the legacy header bundle and page content.
        echo "\n\n";
    }

    public function renderFooter()
    {
        require_once $this->sourcePath('includes/public_theme_helpers.php');
        $redThemeFooterContext = red_public_legacy_footer_context();
        require $this->footerSource;
    }

    public function renderDocumentEnd()
    {
        $this->renderDocumentPhase('end');
    }

    public function resolvePublicLayoutId($layoutId)
    {
        $resolvedLayoutId = red_theme_layout_resolve_id(
            $this->manifest,
            is_string($layoutId) ? trim($layoutId) : ''
        );
        return $resolvedLayoutId !== null && isset($this->layoutSources[$resolvedLayoutId])
            ? $resolvedLayoutId
            : null;
    }

    public function supportsPublicLayout($layoutId)
    {
        return $this->resolvePublicLayoutId($layoutId) !== null;
    }

    public function publicLayoutIds()
    {
        return array_keys(red_theme_layout_manifest_catalog($this->manifest));
    }

    public function renderPublicLayout(
        $layoutId,
        $articleQuery,
        $varFeatures,
        $varPosition,
        $limit
    ) {
        $layoutId = $this->resolvePublicLayoutId($layoutId);
        if ($layoutId === null) {
            throw new InvalidArgumentException('Unsupported legacy public layout id: ' . $layoutId);
        }

        require_once $this->sourcePath('includes/legacy_layout_helpers.php');

        $redThemeRenderBreadcrumb = function () {
            if (!class_exists('Build_Breadcrumb')) {
                throw new RuntimeException('The legacy breadcrumb renderer is unavailable.');
            }

            $breadcrumb = new Build_Breadcrumb();
            $breadcrumb->get_breadcrumb();
        };
        $redThemeRenderSlot = function ($position) use (
            $articleQuery,
            $varFeatures,
            $varPosition,
            $layoutId,
            $limit
        ) {
            $context = red_legacy_layout_slot_context(
                $articleQuery,
                $varFeatures,
                $varPosition,
                $position,
                $layoutId,
                $limit,
                'public'
            );
            return red_legacy_render_layout_slot($context);
        };

        require $this->layoutSources[$layoutId];
    }

    public function renderPublicArticleComponent(array $inputs)
    {
        global $URL;

        require_once $this->sourcePath('includes/public_render_helpers.php');
        require_once $this->sourcePath('includes/legacy_component_helpers.php');

        $inventory = red_legacy_public_component_input_inventory();
        if (array_keys($inputs) !== $inventory['Article']) {
            throw new InvalidArgumentException('Invalid legacy public Article component inputs.');
        }

        $redThemeArticleContext = red_legacy_public_article_view_context(
            $inputs['recordId'],
            $inputs['layout'],
            $inputs['article'],
            $inputs['position'],
            isset($URL) ? (string) $URL : ''
        );
        require $this->articleComponentSource;
    }

    public function renderPublicFormComponent(array $inputs)
    {
        require_once $this->sourcePath('includes/public_render_helpers.php');
        require_once $this->sourcePath('includes/legacy_component_helpers.php');

        $inventory = red_legacy_public_component_input_inventory();
        if (array_keys($inputs) !== $inventory['Form']) {
            throw new InvalidArgumentException('Invalid legacy public Form component inputs.');
        }

        $redThemeFormContext = red_legacy_public_form_context($inputs['recordId']);
        require $this->formComponentSource;
    }

    public function renderPublicGalleryComponent(array $inputs)
    {
        require_once $this->sourcePath('includes/public_render_helpers.php');
        require_once $this->sourcePath('includes/legacy_component_helpers.php');

        $inventory = red_legacy_public_component_input_inventory();
        if (array_keys($inputs) !== $inventory['Gallery']) {
            throw new InvalidArgumentException('Invalid legacy public Gallery component inputs.');
        }

        $redThemeGalleryContext = red_legacy_public_gallery_context(
            $inputs['recordId'],
            $inputs['layout'],
            $inputs['position']
        );
        require $this->galleryComponentSource;
    }

    public function renderPublicOtherComponent(array $inputs)
    {
        require_once $this->sourcePath('includes/public_render_helpers.php');
        require_once $this->sourcePath('includes/legacy_component_helpers.php');

        $inventory = red_legacy_public_component_input_inventory();
        if (array_keys($inputs) !== $inventory['Other']) {
            throw new InvalidArgumentException('Invalid legacy public Other component inputs.');
        }

        $redThemeOtherContext = red_legacy_public_other_view_context(
            $inputs['recordId'],
            $inputs['layout'],
            $inputs['article'],
            $inputs['position']
        );
        require $this->otherComponentSource;
    }

    public function renderLayout($articleQuery, $varFeatures, $varPosition, $adminMode = false, $table = null)
    {
        if (!class_exists('page_layout')) {
            throw new RuntimeException('The legacy page_layout class is not loaded.');
        }

        $layout = new page_layout();
        if ($adminMode) {
            $layout->cp_layout($articleQuery, $varFeatures, $varPosition, $table);
            return;
        }

        if (!class_exists('limit') || !class_exists('layout')) {
            throw new RuntimeException('The legacy layout resolvers are not loaded.');
        }

        $limitResolver = new limit();
        $layoutResolver = new layout();
        $this->renderPublicLayout(
            $layoutResolver->get_layout(),
            $articleQuery,
            $varFeatures,
            $varPosition,
            $limitResolver->get_limit()
        );
    }
}
