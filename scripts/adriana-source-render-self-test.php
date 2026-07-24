#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
$themeRoot = $projectRoot . '/themes/adriana-granobles';
$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$capture = static function (string $file, array $variables = []): string {
    extract($variables, EXTR_SKIP);
    ob_start();
    try {
        require $file;
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        throw $exception;
    }
};

$navigationContext = [
    'mode' => 'production',
    'items' => [],
    'breadcrumbsEnabled' => false,
];
$navigationHtml = $capture($themeRoot . '/partials/production-navigation.php', [
    'redThemeNavigationContext' => $navigationContext,
]);
$assert(substr_count($navigationHtml, 'id="site-nav"') === 1, 'production navigation renders exactly once');
$assert(
    substr_count($navigationHtml, '<nav class="spacer-like-breadcrumb" aria-label="Miga de pan"></nav>') === 1,
    'breadcrumb-disabled Adriana navigation restores the original 92px spacer'
);
$assert(
    strpos($navigationHtml, 'id="site-nav"') < strpos($navigationHtml, 'spacer-like-breadcrumb'),
    'the visual spacer follows the fixed production navigation'
);
$navigationContext['breadcrumbsEnabled'] = true;
$navigationWithBreadcrumbsHtml = $capture($themeRoot . '/partials/production-navigation.php', [
    'redThemeNavigationContext' => $navigationContext,
]);
$assert(
    !str_contains($navigationWithBreadcrumbsHtml, 'spacer-like-breadcrumb'),
    'breadcrumb-enabled templates do not receive the no-breadcrumb spacer'
);

$themeCss = (string) file_get_contents($themeRoot . '/assets/css/theme.css');
$assert(
    preg_match('/\.spacer-like-breadcrumb\s*\{\s*height\s*:\s*92px\s*;?\s*\}/s', $themeCss) === 1,
    'the imported visual spacer remains exactly 92px high'
);
$manifest = json_decode(
    (string) file_get_contents($themeRoot . '/theme.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$productionStyles = array_column((array) ($manifest['production']['assets']['styles'] ?? []), 'path');
$assert(
    $productionStyles === ['assets/css/production.css', 'assets/css/production-forms.css'],
    'the scoped form bridge loads after the general production bridge'
);
$productionCss = (string) file_get_contents($themeRoot . '/assets/css/production.css');
$productionFormsCss = (string) file_get_contents($themeRoot . '/assets/css/production-forms.css');
$assert(!str_contains($productionCss, '.redcms-component--form'), 'general production CSS owns no Form rules');
$assert(
    str_contains($productionFormsCss, '.redcms-component--form .wrapper')
        && str_contains($productionFormsCss, 'min-width: 0;'),
    'the Form bridge contains the legacy wrapper shrink reset'
);
$assert(
    str_contains($productionFormsCss, '.redcms-component--form #contact fieldset')
        && str_contains($productionFormsCss, 'grid-template-columns: minmax(0, 1fr);'),
    'the Contact fieldset owns the source-like one-column grid'
);
$assert(
    str_contains($productionFormsCss, '#contact input[type="submit"].button')
        && str_contains($productionFormsCss, '#contact .form-note'),
    'the Contact submit and follow-up note have scoped source-parity rules'
);

$legacyFormFixture = '<form id="contact"><fieldset><label for="reason">Motivo</label>&nbsp;<select id="reason"></select></fieldset></form>';
$productionFormHtml = $capture($themeRoot . '/components/production-form.php', [
    'redThemeFormContext' => ['mode' => 'production', 'html' => $legacyFormFixture],
]);
$assert(
    str_contains($productionFormHtml, '</label><select id="reason">')
        && !str_contains($productionFormHtml, '</label>&nbsp;<select'),
    'the Adriana Form wrapper removes the legacy select spacer that creates an extra grid row'
);

$sourceHtml = '<section data-redcms-source-page="fixture"><h1>Fixture</h1></section>';
$otherHtml = $capture($themeRoot . '/components/production-other.php', [
    'redThemeOtherContext' => ['mode' => 'production', 'html' => $sourceHtml],
]);
$assert(substr_count($otherHtml, $sourceHtml) === 1, 'source Other HTML renders exactly once');
$assert(!str_contains($otherHtml, 'redcms-legacy-content'), 'source Other HTML does not receive a legacy wrapper');

$legacyOtherHtml = $capture($themeRoot . '/components/production-other.php', [
    'redThemeOtherContext' => ['mode' => 'production', 'html' => '<p>Legacy fixture</p>'],
]);
$assert(str_contains($legacyOtherHtml, 'redcms-legacy-content'), 'ordinary Other HTML preserves the compatibility wrapper');

$layoutFiles = [
    'home-editorial',
    'directory-hub',
    'service-detail',
    'campaign-story',
];
foreach ($layoutFiles as $layoutId) {
    $html = $capture($themeRoot . '/layouts/production-' . $layoutId . '.php', [
        'redThemeLayoutContext' => [
            'mode' => 'production',
            'breadcrumb' => [['label' => 'Fixture breadcrumb', 'url' => '']],
            'slots' => [1 => $sourceHtml, 2 => '', 3 => '', 4 => '', 5 => ''],
        ],
    ]);
    $assert(substr_count($html, $sourceHtml) === 1, "$layoutId renders source-page HTML exactly once");
    $assert(!str_contains($html, 'adriana-layout__slot'), "$layoutId suppresses semantic slot wrappers for a source page");
    $assert(!str_contains($html, 'class="breadcrumb wrapper"'), "$layoutId suppresses visible breadcrumbs for a source page");

    $distributedOne = '<section data-redcms-source-page="fixture" data-redcms-source-section="1"><h1>Fixture</h1></section>';
    $distributedTwo = '<section data-redcms-source-page="fixture" data-redcms-source-section="2"><p>Second section</p></section>';
    $distributedFive = '<section data-redcms-source-page="fixture" data-redcms-source-section="3"><p>Final section</p></section>';
    $distributedHtml = $capture($themeRoot . '/layouts/production-' . $layoutId . '.php', [
        'redThemeLayoutContext' => [
            'mode' => 'production',
            'breadcrumb' => [['label' => 'Fixture breadcrumb', 'url' => '']],
            'slots' => [1 => $distributedOne, 2 => $distributedTwo, 3 => '', 4 => '', 5 => $distributedFive],
        ],
    ]);
    $assert(substr_count($distributedHtml, $distributedOne) === 1, "$layoutId renders distributed position 1 exactly once");
    $assert(substr_count($distributedHtml, $distributedTwo) === 1, "$layoutId renders distributed position 2 exactly once");
    $assert(substr_count($distributedHtml, $distributedFive) === 1, "$layoutId renders distributed position 5 exactly once");
    $assert(!str_contains($distributedHtml, 'adriana-layout__slot'), "$layoutId suppresses semantic wrappers for distributed source sections");
    $assert(!str_contains($distributedHtml, 'class="breadcrumb wrapper"'), "$layoutId suppresses visible breadcrumbs for distributed source sections");
}

$nativeForm = '<div class="redcms-component--form" data-red-component="Form">Native form fixture $1 \\1</div>';
$contactSource = '<section data-redcms-source-page="contacto"><h1>Contact</h1><div data-redcms-native-form-anchor=""></div></section>';
$contactHtml = $capture($themeRoot . '/layouts/production-contact-conversion.php', [
    'redThemeLayoutContext' => [
        'mode' => 'production',
        'breadcrumb' => [],
        'slots' => [1 => $contactSource, 2 => $nativeForm, 3 => ''],
    ],
]);
$assert(substr_count($contactHtml, $nativeForm) === 1, 'Contact injects the native Form exactly once');
$assert(!str_contains($contactHtml, 'data-redcms-native-form-anchor'), 'Contact removes the migration anchor');

$contactDistributedOne = '<section data-redcms-source-page="contacto" data-redcms-source-section="1"><h1>Contact</h1></section>';
$contactDistributedTwo = '<section data-redcms-source-page="contacto" data-redcms-source-section="2"><div data-redcms-native-form-anchor=""></div></section>';
$contactDistributedThree = '<section data-redcms-source-page="contacto" data-redcms-source-section="3"><p>Contact details</p></section>';
$contactDistributedHtml = $capture($themeRoot . '/layouts/production-contact-conversion.php', [
    'redThemeLayoutContext' => [
        'mode' => 'production',
        'breadcrumb' => [],
        'slots' => [
            1 => $contactDistributedOne . $contactDistributedTwo,
            2 => $nativeForm,
            3 => $contactDistributedThree,
            4 => '',
            5 => '',
        ],
    ],
]);
$assert(substr_count($contactDistributedHtml, $contactDistributedOne) === 1, 'Contact renders distributed section 1 exactly once');
$assert(substr_count($contactDistributedHtml, 'data-redcms-source-section="2"') === 1, 'Contact renders distributed section 2 exactly once');
$assert(substr_count($contactDistributedHtml, $contactDistributedThree) === 1, 'Contact renders distributed section 3 exactly once');
$assert(substr_count($contactDistributedHtml, $nativeForm) === 1, 'Contact injects its native Form into distributed source sections exactly once');
$assert(!str_contains($contactDistributedHtml, 'data-redcms-native-form-anchor'), 'Contact removes the distributed migration anchor');

$contactFailedClosed = false;
try {
    $capture($themeRoot . '/layouts/production-contact-conversion.php', [
        'redThemeLayoutContext' => [
            'mode' => 'production',
            'breadcrumb' => [],
            'slots' => [1 => $sourceHtml, 2 => $nativeForm, 3 => ''],
        ],
    ]);
} catch (RuntimeException $exception) {
    $contactFailedClosed = str_contains($exception->getMessage(), 'exactly one native Form anchor');
}
$assert($contactFailedClosed, 'Contact fails closed when its native Form anchor is absent');

$sourceFooter = '<div data-redcms-source-footer="adriana-granobles-v4">Source footer</div>';
$footerHtml = $capture($themeRoot . '/partials/production-footer.php', [
    'redThemeFooterContext' => [
        'mode' => 'production',
        'customHtml' => $sourceFooter,
        'copyright' => '',
    ],
]);
$assert(substr_count($footerHtml, $sourceFooter) === 1, 'normalized source footer renders exactly once');
$assert(!str_contains($footerHtml, 'Powered by RED-CMS'), 'source footer suppresses the template fallback footer');

fwrite(STDOUT, sprintf("All %d Adriana source-render assertions passed.\n", $assertions));
