#!/usr/bin/env php
<?php

declare(strict_types=1);

const ADRIANA_SOURCE_DOMAIN = 'adrianagranobles.com';
const ADRIANA_MEDIA_PUBLIC_PREFIX = '/images/articles/adriana-granobles-v4/';

function adriana_stage_fail(string $message, int $status = 1): never
{
    fwrite(STDERR, "ERROR: $message\n");
    exit($status);
}

function adriana_stage_usage(): void
{
    fwrite(STDOUT, "Usage: scripts/adriana-content-stage.php /absolute/path/to/source-site\n");
}

function adriana_stage_routes(): array
{
    $groups = [
        'home-editorial' => ['index.html'],
        'directory-hub' => [
            'clases-de-musica.html',
            'instrumentos.html',
            'canto.html',
            'estudio-de-grabacion.html',
            'testimonios.html',
        ],
        'service-detail' => [
            'escuela-canto.html',
            'escuela-piano.html',
            'escuela-guitarra.html',
            'escuela-bateria.html',
            'escuela-percusion.html',
            'escuela-bajo.html',
            'escuela-flauta.html',
            'escuela-clarinete.html',
            'escuela-teoria-musical.html',
            'escuela-composicion-produccion.html',
            'escuela-violin.html',
            'coaching-ontologico.html',
            'canto-terapeutico.html',
            'composicion.html',
            'produccion-musical.html',
        ],
        'campaign-story' => [
            'clases-de-musica-online-para-ninos.html',
            'programa-cuda.html',
            'el-cantautor.html',
            'bodas-y-eventos.html',
            'la-voz-que-sana.html',
            'sobre-adriana.html',
        ],
        'contact-conversion' => ['contacto.html'],
    ];

    $routes = [];
    foreach ($groups as $layout => $files) {
        foreach ($files as $file) {
            $alias = $file === 'index.html' ? '' : substr($file, 0, -5);
            $routes[] = [
                'source' => $file,
                'path' => $file === 'index.html' ? '/' : '/' . $file,
                'alias' => $alias,
                'layout' => $layout,
            ];
        }
    }

    return $routes;
}

function adriana_stage_xpath_string(DOMXPath $xpath, string $query, ?DOMNode $context = null): string
{
    $nodes = $xpath->query($query, $context);
    if (!($nodes instanceof DOMNodeList) || $nodes->length < 1) {
        return '';
    }
    return trim(html_entity_decode((string) $nodes->item(0)?->nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function adriana_stage_nodes(DOMNodeList|false $nodes): array
{
    if ($nodes === false) {
        return [];
    }
    $copy = [];
    foreach ($nodes as $node) {
        $copy[] = $node;
    }
    return $copy;
}

function adriana_stage_inner_html(DOMNode $node): string
{
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument?->saveHTML($child) ?? '';
    }
    return $html;
}

function adriana_stage_internal_href(string $value, array $routeByFile): string
{
    $value = trim($value);
    if ($value === '' || $value[0] === '#') {
        return $value;
    }

    $parts = parse_url(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($parts === false) {
        return $value;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host !== '' && $host !== ADRIANA_SOURCE_DOMAIN && $host !== 'www.' . ADRIANA_SOURCE_DOMAIN) {
        return $value;
    }

    $path = (string) ($parts['path'] ?? $value);
    $file = basename($path);
    if (!isset($routeByFile[$file])) {
        $flatAlias = trim($path, '/');
        if ($flatAlias === '') {
            $file = 'index.html';
        } elseif (!str_contains($flatAlias, '/')) {
            $file = $flatAlias . '.html';
        }
    }
    if (!isset($routeByFile[$file])) {
        return $value;
    }

    $rewritten = (string) $routeByFile[$file]['path'];
    if (isset($parts['query']) && $parts['query'] !== '') {
        $rewritten .= '?' . $parts['query'];
    }
    if (isset($parts['fragment']) && $parts['fragment'] !== '') {
        $rewritten .= '#' . $parts['fragment'];
    }
    return $rewritten;
}

function adriana_stage_local_media(
    string $value,
    string $sourceRoot,
    array &$mediaBySource
): ?string {
    $decoded = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $path = parse_url($decoded, PHP_URL_PATH);
    if (!is_string($path)) {
        return null;
    }
    $path = ltrim($path, '/');
    if (!str_starts_with($path, 'assets/img/source/')) {
        return null;
    }
    if (preg_match('/\Aassets\/img\/source\/[A-Za-z0-9][A-Za-z0-9._-]*\z/', $path) !== 1) {
        adriana_stage_fail("Unsafe source media path: $value");
    }

    $absolute = $sourceRoot . '/' . $path;
    $resolved = realpath($absolute);
    $mediaRoot = realpath($sourceRoot . '/assets/img/source');
    if ($resolved === false || $mediaRoot === false || !is_file($resolved)
        || !str_starts_with($resolved, $mediaRoot . DIRECTORY_SEPARATOR)
    ) {
        adriana_stage_fail("Referenced source media is missing or escapes its root: $path");
    }

    $targetFile = basename($path);
    $mediaBySource[$path] = [
        'source' => $path,
        'target' => 'media/' . $targetFile,
        'publicPath' => ADRIANA_MEDIA_PUBLIC_PREFIX . rawurlencode($targetFile),
        'bytes' => filesize($resolved),
        'sha256' => hash_file('sha256', $resolved),
    ];

    return ADRIANA_MEDIA_PUBLIC_PREFIX . rawurlencode($targetFile);
}

function adriana_stage_sanitize_node(
    DOMElement $root,
    DOMXPath $xpath,
    string $routeAlias,
    array $routeByFile,
    string $sourceRoot,
    array &$mediaBySource,
    array &$decisions,
    bool $footer = false
): void {
    foreach (['.//script', './/style', './/noscript'] as $query) {
        foreach (array_reverse(adriana_stage_nodes($xpath->query($query, $root))) as $node) {
            $node->parentNode?->removeChild($node);
            $decisions['removedExecutableNodes']++;
        }
    }

    foreach (array_reverse(adriana_stage_nodes($xpath->query('.//iframe', $root))) as $iframe) {
        if (!($iframe instanceof DOMElement)) {
            continue;
        }
        $src = trim($iframe->getAttribute('src'));
        $host = strtolower((string) parse_url(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_HOST));
        $label = trim($iframe->getAttribute('title'));
        if ($label === '') {
            $label = $host !== '' ? 'Abrir recurso externo de ' . $host : 'Abrir recurso externo';
        }
        $replacement = $root->ownerDocument->createElement('div');
        $replacement->setAttribute('class', 'external-embed-fallback');
        if ($host !== '') {
            $replacement->setAttribute('data-redcms-external-host', $host);
        }
        $copy = $root->ownerDocument->createElement('p', 'Este recurso se abre fuera de RED-CMS.');
        $link = $root->ownerDocument->createElement('a', $label);
        $link->setAttribute('class', 'button button--outline');
        $link->setAttribute('href', $src);
        $link->setAttribute('target', '_blank');
        $link->setAttribute('rel', 'noopener noreferrer');
        $replacement->appendChild($copy);
        $replacement->appendChild($link);
        $iframe->parentNode?->replaceChild($replacement, $iframe);
        $decisions['convertedFrames'][] = ['host' => $host, 'url' => $src];
    }

    foreach (array_reverse(adriana_stage_nodes($xpath->query('.//form', $root))) as $form) {
        if (!($form instanceof DOMElement)) {
            continue;
        }
        $replacement = $root->ownerDocument->createElement('div');
        if ($routeAlias === 'contacto' && !$footer) {
            $replacement->setAttribute('data-redcms-native-form-anchor', '');
            $decisions['nativeFormAnchors']++;
        } else {
            $replacement->setAttribute('class', 'redcms-source-form-replacement');
            $copy = $root->ownerDocument->createElement('p', 'El formulario operativo está disponible en la página de contacto.');
            $link = $root->ownerDocument->createElement('a', 'Ir a contacto');
            $link->setAttribute('class', 'button button--primary');
            $link->setAttribute('href', '/contacto.html');
            $replacement->appendChild($copy);
            $replacement->appendChild($link);
            $decisions['replacedStaticForms']++;
        }
        $form->parentNode?->replaceChild($replacement, $form);
    }

    $elements = [$root];
    foreach (adriana_stage_nodes($xpath->query('.//*', $root)) as $element) {
        if ($element instanceof DOMElement) {
            $elements[] = $element;
        }
    }

    foreach ($elements as $element) {
        $attributeNames = [];
        foreach ($element->attributes ?? [] as $attribute) {
            $attributeNames[] = $attribute->nodeName;
        }
        foreach ($attributeNames as $name) {
            $lowerName = strtolower($name);
            $value = $element->getAttribute($name);
            if ($lowerName === 'style' || str_starts_with($lowerName, 'on')) {
                $element->removeAttribute($name);
                $decisions['removedUnsafeAttributes']++;
                continue;
            }
            if (in_array($lowerName, ['href', 'src', 'poster'], true)) {
                if (preg_match('/\Ajavascript:/i', trim($value)) === 1) {
                    $element->removeAttribute($name);
                    $decisions['removedUnsafeAttributes']++;
                    continue;
                }
                $mediaPath = adriana_stage_local_media($value, $sourceRoot, $mediaBySource);
                if ($mediaPath !== null) {
                    $element->setAttribute($name, $mediaPath);
                    continue;
                }
                if ($lowerName === 'href') {
                    $element->setAttribute($name, adriana_stage_internal_href($value, $routeByFile));
                }
                if ($lowerName === 'src') {
                    $srcHost = strtolower((string) parse_url(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_HOST));
                    if ($srcHost !== '') {
                        $decisions['externalImageHosts'][$srcHost] = true;
                    }
                }
            }
        }
    }
}

function adriana_stage_load_html(string $path): array
{
    $source = file_get_contents($path);
    if (!is_string($source) || $source === '') {
        adriana_stage_fail("Could not read source page: $path");
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $source, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        adriana_stage_fail("Could not parse source page: $path");
    }
    return [$source, $document, new DOMXPath($document)];
}

if ($argc === 2 && $argv[1] === '--help') {
    adriana_stage_usage();
    exit(0);
}
if ($argc !== 2 || !str_starts_with((string) $argv[1], '/')) {
    adriana_stage_usage();
    exit(64);
}

$sourceRoot = realpath((string) $argv[1]);
if ($sourceRoot === false || !is_dir($sourceRoot)) {
    adriana_stage_fail('Source root does not exist.', 66);
}
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    adriana_stage_fail('Project root could not be resolved.', 66);
}
$outputRoot = $projectRoot . '/content-migrations/adriana-granobles-v4';
$mediaOutput = $outputRoot . '/media';
if (!is_dir($mediaOutput) && !mkdir($mediaOutput, 0775, true) && !is_dir($mediaOutput)) {
    adriana_stage_fail("Could not create staging directory: $mediaOutput");
}

$routeDefinitions = adriana_stage_routes();
if (count($routeDefinitions) !== 28) {
    adriana_stage_fail('Internal route inventory is not exactly 28 pages.');
}
$routeByFile = [];
foreach ($routeDefinitions as $route) {
    if (isset($routeByFile[$route['source']])) {
        adriana_stage_fail('Internal route inventory contains a duplicate source file.');
    }
    $routeByFile[$route['source']] = $route;
}

$actualRootPages = glob($sourceRoot . '/*.html') ?: [];
sort($actualRootPages, SORT_STRING);
$expectedRootPages = array_map(static fn(array $route): string => $sourceRoot . '/' . $route['source'], $routeDefinitions);
sort($expectedRootPages, SORT_STRING);
if ($actualRootPages !== $expectedRootPages) {
    adriana_stage_fail('Source root HTML inventory does not match the reviewed 28-route contract.');
}

$sitemapPath = $sourceRoot . '/sitemap.xml';
$sitemapXml = is_file($sitemapPath) ? file_get_contents($sitemapPath) : false;
if (!is_string($sitemapXml) || $sitemapXml === '') {
    adriana_stage_fail('Source sitemap.xml is missing or empty.');
}
$sitemapDocument = new DOMDocument('1.0', 'UTF-8');
$previous = libxml_use_internal_errors(true);
$sitemapLoaded = $sitemapDocument->loadXML($sitemapXml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
libxml_clear_errors();
libxml_use_internal_errors($previous);
if (!$sitemapLoaded) {
    adriana_stage_fail('Source sitemap.xml could not be parsed.');
}
$sitemapXPath = new DOMXPath($sitemapDocument);
$sitemapUrls = [];
foreach (adriana_stage_nodes($sitemapXPath->query('//*[local-name()="loc"]')) as $location) {
    $url = trim((string) $location->textContent);
    if ($url === '' || isset($sitemapUrls[$url])) {
        adriana_stage_fail('Source sitemap.xml contains an empty or duplicate location.');
    }
    $sitemapUrls[$url] = true;
}
$expectedSitemapUrls = [];
foreach ($routeDefinitions as $route) {
    $expectedSitemapUrls['https://' . ADRIANA_SOURCE_DOMAIN . $route['path']] = true;
}
ksort($sitemapUrls, SORT_STRING);
ksort($expectedSitemapUrls, SORT_STRING);
if (array_keys($sitemapUrls) !== array_keys($expectedSitemapUrls)) {
    adriana_stage_fail('Source sitemap.xml does not match the reviewed 28-route contract.');
}

$mediaBySource = [];
$manifestRoutes = [];
$canonicalSeen = [];
$aliasSeen = [];
$footerHtml = '';
$footerSha256 = '';

foreach ($routeDefinitions as $routeIndex => $route) {
    $path = $sourceRoot . '/' . $route['source'];
    [$sourceHtml, $document, $xpath] = adriana_stage_load_html($path);

    $title = adriana_stage_xpath_string($xpath, '//title');
    $description = adriana_stage_xpath_string(
        $xpath,
        '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]/@content'
    );
    $canonical = adriana_stage_xpath_string($xpath, '//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="canonical"]/@href');
    $h1 = adriana_stage_xpath_string($xpath, '//body//h1[1]');
    if ($title === '' || $description === '' || $canonical === '' || $h1 === '') {
        adriana_stage_fail("Required title, description, canonical, or H1 is missing in {$route['source']}.");
    }
    $expectedCanonical = 'https://' . ADRIANA_SOURCE_DOMAIN . $route['path'];
    if ($canonical !== $expectedCanonical) {
        adriana_stage_fail("Canonical mismatch in {$route['source']}: expected $expectedCanonical, got $canonical");
    }
    if (isset($canonicalSeen[$canonical])) {
        adriana_stage_fail("Duplicate canonical: $canonical");
    }
    $canonicalSeen[$canonical] = true;
    if ($route['alias'] !== '') {
        if (isset($aliasSeen[$route['alias']])) {
            adriana_stage_fail("Duplicate route alias: {$route['alias']}");
        }
        $aliasSeen[$route['alias']] = true;
    }

    $sections = adriana_stage_nodes($xpath->query('/html/body/section'));
    if ($sections === []) {
        adriana_stage_fail("No direct content sections were found in {$route['source']}.");
    }
    $decisions = [
        'removedExecutableNodes' => 0,
        'removedUnsafeAttributes' => 0,
        'replacedStaticForms' => 0,
        'nativeFormAnchors' => 0,
        'convertedFrames' => [],
        'externalImageHosts' => [],
    ];
    $pageHtml = '';
    foreach ($sections as $sectionIndex => $section) {
        if (!($section instanceof DOMElement)) {
            continue;
        }
        $section->setAttribute('data-redcms-source-section', (string) ($sectionIndex + 1));
        if ($sectionIndex === 0) {
            $section->setAttribute('data-redcms-source-page', $route['alias'] !== '' ? $route['alias'] : 'home');
        }
        adriana_stage_sanitize_node(
            $section,
            $xpath,
            (string) $route['alias'],
            $routeByFile,
            $sourceRoot,
            $mediaBySource,
            $decisions
        );
        $pageHtml .= trim((string) $document->saveHTML($section)) . "\n";
    }

    if (preg_match('/<(?:script|form|iframe)\b|\son[a-z]+\s*=|javascript:/i', $pageHtml) === 1) {
        adriana_stage_fail("Sanitized page still contains executable or operational markup: {$route['source']}");
    }
    $expectedNativeAnchors = $route['alias'] === 'contacto' ? 1 : 0;
    if ($decisions['nativeFormAnchors'] !== $expectedNativeAnchors) {
        adriana_stage_fail("Unexpected native Form anchor count in {$route['source']}.");
    }
    if ($route['source'] === 'index.html' && $decisions['replacedStaticForms'] !== 1) {
        adriana_stage_fail('The homepage static form was not replaced exactly once.');
    }

    ksort($decisions['externalImageHosts'], SORT_STRING);
    $decisions['externalImageHosts'] = array_keys($decisions['externalImageHosts']);
    $sourceMarker = trim(preg_replace('/\s+/', ' ', strip_tags($h1)) ?? $h1);
    $manifestRoutes[] = array_merge($route, [
        'canonical' => $canonical,
        'title' => $title,
        'description' => $description,
        'h1' => $h1,
        'sourceMarker' => $sourceMarker,
        'sourceSha256' => hash('sha256', $sourceHtml),
        'sectionCount' => count($sections),
        'bodyHtml' => trim($pageHtml),
        'bodySha256' => hash('sha256', trim($pageHtml)),
        'decisions' => $decisions,
    ]);

    if ($routeIndex === 0) {
        $footer = $xpath->query('/html/body/footer')->item(0);
        if (!($footer instanceof DOMElement)) {
            adriana_stage_fail('Homepage source footer was not found.');
        }
        $footerDecision = [
            'removedExecutableNodes' => 0,
            'removedUnsafeAttributes' => 0,
            'replacedStaticForms' => 0,
            'nativeFormAnchors' => 0,
            'convertedFrames' => [],
            'externalImageHosts' => [],
        ];
        adriana_stage_sanitize_node(
            $footer,
            $xpath,
            '',
            $routeByFile,
            $sourceRoot,
            $mediaBySource,
            $footerDecision,
            true
        );
        $firstElement = null;
        foreach ($footer->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $firstElement = $child;
                break;
            }
        }
        if (!($firstElement instanceof DOMElement)) {
            adriana_stage_fail('Homepage footer has no renderable content.');
        }
        $firstElement->setAttribute('data-redcms-source-footer', 'adriana-granobles-v4');
        $footerHtml = trim(adriana_stage_inner_html($footer));
        $footerSha256 = hash('sha256', $footerHtml);
    }
}

if (count($manifestRoutes) !== 28 || count($canonicalSeen) !== 28 || count($aliasSeen) !== 27) {
    adriana_stage_fail('Final route/canonical/alias counts do not match 28/28/27.');
}
if ($footerHtml === '' || strpos($footerHtml, 'data-redcms-source-footer=') === false) {
    adriana_stage_fail('Normalized source footer was not staged.');
}

ksort($mediaBySource, SORT_STRING);
$media = array_values($mediaBySource);
$targetHashes = [];
foreach ($media as $entry) {
    $target = (string) $entry['target'];
    if (isset($targetHashes[$target]) && $targetHashes[$target] !== $entry['sha256']) {
        adriana_stage_fail("Two source files collide at $target with different hashes.");
    }
    $targetHashes[$target] = $entry['sha256'];
    $sourceFile = $sourceRoot . '/' . $entry['source'];
    $targetFile = $outputRoot . '/' . $target;
    if (is_file($targetFile) && hash_file('sha256', $targetFile) !== $entry['sha256']) {
        adriana_stage_fail("Staged media exists with different bytes: $targetFile");
    }
    if (!is_file($targetFile) && !copy($sourceFile, $targetFile)) {
        adriana_stage_fail("Could not stage media: {$entry['source']}");
    }
}

$existingMedia = glob($mediaOutput . '/*') ?: [];
$expectedMedia = array_map(static fn(array $entry): string => $outputRoot . '/' . $entry['target'], $media);
sort($existingMedia, SORT_STRING);
sort($expectedMedia, SORT_STRING);
if ($existingMedia !== $expectedMedia) {
    adriana_stage_fail('Staging media directory contains files outside the reviewed manifest.');
}

$manifest = [
    'schemaVersion' => 1,
    'migrationId' => 'adriana-granobles-v4',
    'source' => [
        'directoryName' => basename($sourceRoot),
        'sitemapSha256' => hash_file('sha256', $sourceRoot . '/sitemap.xml'),
    ],
    'policy' => [
        'canonicalUrls' => 'preserve-root-html-through-compatibility-adapter',
        'tracking' => 'excluded',
        'jotform' => 'excluded',
        'staticForms' => 'replaced',
        'contactForm' => 'native-redcms-form',
        'contentIframes' => 'converted-to-explicit-external-links',
    ],
    'shell' => [
        'websiteTitle' => 'Adriana Granobles',
        'websiteSlogan' => 'Voz, música y transformación',
        'footerHtml' => $footerHtml,
        'footerSha256' => $footerSha256,
    ],
    'counts' => [
        'routes' => count($manifestRoutes),
        'nonHomeAliases' => count($aliasSeen),
        'sourceSections' => array_sum(array_column($manifestRoutes, 'sectionCount')),
        'mediaFiles' => count($media),
    ],
    'routes' => $manifestRoutes,
    'media' => $media,
];

$manifestJson = json_encode(
    $manifest,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
) . "\n";
$manifestPath = $outputRoot . '/routes.json';
$tempManifest = tempnam($outputRoot, '.routes-json-');
if ($tempManifest === false || file_put_contents($tempManifest, $manifestJson) === false
    || !rename($tempManifest, $manifestPath)
) {
    if (is_string($tempManifest) && is_file($tempManifest)) {
        unlink($tempManifest);
    }
    adriana_stage_fail('Could not write the staged route manifest.');
}

fwrite(STDOUT, sprintf(
    "Staged %d routes, %d source sections, and %d media files.\nManifest: %s\nSHA-256: %s\n",
    $manifest['counts']['routes'],
    $manifest['counts']['sourceSections'],
    $manifest['counts']['mediaFiles'],
    $manifestPath,
    hash_file('sha256', $manifestPath)
));
