#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const ADRIANA_PACKAGE_ID = 'adriana-granobles-v4';
const ADRIANA_PACKAGE_MEDIA_PREFIX = '/images/articles/adriana-granobles-v4/';
const ADRIANA_PACKAGE_APPROVED_SHA256 = '018fb1a336a7635c85fe883ec94feaad5ff447153d819d4497bd30b9c498937c';

function adriana_package_assert(bool $condition, string $message): void
{
    static $assertions = 0;
    $assertions++;
    $GLOBALS['adrianaPackageAssertions'] = $assertions;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function adriana_package_fragment(string $html): array
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $document->loadHTML(
            '<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    adriana_package_assert($loaded === true, 'sanitized HTML parses');
    return [$document, new DOMXPath($document)];
}

function adriana_package_normalized_text(string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

$projectRoot = dirname(__DIR__);
$packageRoot = $projectRoot . '/content-migrations/' . ADRIANA_PACKAGE_ID;
$manifestPath = $packageRoot . '/routes.json';
adriana_package_assert(is_file($manifestPath), 'manifest exists');
adriana_package_assert(
    hash_equals(ADRIANA_PACKAGE_APPROVED_SHA256, hash_file('sha256', $manifestPath)),
    'manifest matches the approved reviewed package digest'
);

try {
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    throw new RuntimeException('Manifest JSON is invalid: ' . $exception->getMessage(), 0, $exception);
}
adriana_package_assert(is_array($manifest), 'manifest is an object');
adriana_package_assert(($manifest['schemaVersion'] ?? null) === 1, 'schema version is 1');
adriana_package_assert(($manifest['migrationId'] ?? '') === ADRIANA_PACKAGE_ID, 'migration id is exact');

$expectedGroups = [
    'home-editorial' => ['index.html'],
    'directory-hub' => ['clases-de-musica.html', 'instrumentos.html', 'canto.html', 'estudio-de-grabacion.html', 'testimonios.html'],
    'service-detail' => [
        'escuela-canto.html', 'escuela-piano.html', 'escuela-guitarra.html', 'escuela-bateria.html',
        'escuela-percusion.html', 'escuela-bajo.html', 'escuela-flauta.html', 'escuela-clarinete.html',
        'escuela-teoria-musical.html', 'escuela-composicion-produccion.html', 'escuela-violin.html',
        'coaching-ontologico.html', 'canto-terapeutico.html', 'composicion.html', 'produccion-musical.html',
    ],
    'campaign-story' => [
        'clases-de-musica-online-para-ninos.html', 'programa-cuda.html', 'el-cantautor.html',
        'bodas-y-eventos.html', 'la-voz-que-sana.html', 'sobre-adriana.html',
    ],
    'contact-conversion' => ['contacto.html'],
];
$expectedRoutes = [];
foreach ($expectedGroups as $layout => $files) {
    foreach ($files as $file) {
        $alias = $file === 'index.html' ? '' : substr($file, 0, -5);
        $expectedRoutes[] = [
            'source' => $file,
            'path' => $file === 'index.html' ? '/' : '/' . $file,
            'alias' => $alias,
            'layout' => $layout,
        ];
    }
}

$routes = is_array($manifest['routes'] ?? null) ? $manifest['routes'] : [];
$media = is_array($manifest['media'] ?? null) ? $manifest['media'] : [];
$counts = is_array($manifest['counts'] ?? null) ? $manifest['counts'] : [];
adriana_package_assert(count($routes) === 28, 'exactly 28 routes are staged');
adriana_package_assert(count($media) === 42, 'exactly 42 media files are staged');
adriana_package_assert(($counts['routes'] ?? null) === 28, 'declared route count is exact');
adriana_package_assert(($counts['nonHomeAliases'] ?? null) === 27, 'declared alias count is exact');
adriana_package_assert(($counts['sourceSections'] ?? null) === 153, 'declared section count is exact');
adriana_package_assert(($counts['mediaFiles'] ?? null) === 42, 'declared media count is exact');

$expectedPathSet = array_fill_keys(array_column($expectedRoutes, 'path'), true);
$seenPaths = [];
$seenAliases = [];
$referencedMedia = [];
$sectionCount = 0;
$convertedFrames = 0;
$nativeAnchors = 0;
$replacedForms = 0;
$layoutCounts = [];

foreach ($routes as $index => $route) {
    adriana_package_assert(is_array($route), 'route row is an object');
    $expected = $expectedRoutes[$index];
    foreach (['source', 'path', 'alias', 'layout'] as $key) {
        adriana_package_assert(($route[$key] ?? null) === $expected[$key], "route $index has the expected $key");
    }

    $path = (string) $route['path'];
    $alias = (string) $route['alias'];
    adriana_package_assert(!isset($seenPaths[$path]), "route path $path is unique");
    $seenPaths[$path] = true;
    if ($alias !== '') {
        adriana_package_assert(!isset($seenAliases[$alias]), "route alias $alias is unique");
        adriana_package_assert(preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]*\z/', $alias) === 1, "route alias $alias is editor-safe");
        $seenAliases[$alias] = true;
    }

    foreach (['title', 'description', 'h1', 'sourceMarker', 'bodyHtml', 'bodySha256', 'sourceSha256'] as $key) {
        adriana_package_assert(is_string($route[$key] ?? null) && trim((string) $route[$key]) !== '', "$path has non-empty $key");
    }
    adriana_package_assert(
        hash_equals((string) $route['bodySha256'], hash('sha256', (string) $route['bodyHtml'])),
        "$path body checksum matches"
    );
    adriana_package_assert(
        ($route['canonical'] ?? '') === 'https://adrianagranobles.com' . $path,
        "$path preserves its canonical URL"
    );

    [$document, $xpath] = adriana_package_fragment((string) $route['bodyHtml']);
    $pageNodes = $xpath->query('//*[@data-redcms-source-page]');
    adriana_package_assert($pageNodes instanceof DOMNodeList && $pageNodes->length === 1, "$path has exactly one source-page marker");
    $marker = $pageNodes?->item(0);
    $expectedMarker = $alias === '' ? 'home' : $alias;
    adriana_package_assert($marker instanceof DOMElement && $marker->getAttribute('data-redcms-source-page') === $expectedMarker, "$path source-page marker is exact");

    $sectionNodes = $xpath->query('//section[@data-redcms-source-section]');
    $routeSectionCount = $sectionNodes instanceof DOMNodeList ? $sectionNodes->length : 0;
    adriana_package_assert($routeSectionCount === (int) ($route['sectionCount'] ?? 0), "$path section count matches");
    $sectionCount += $routeSectionCount;

    $h1Nodes = $xpath->query('//h1');
    adriana_package_assert($h1Nodes instanceof DOMNodeList && $h1Nodes->length === 1, "$path has exactly one h1");
    adriana_package_assert(
        adriana_package_normalized_text((string) $h1Nodes?->item(0)?->textContent) === adriana_package_normalized_text((string) $route['h1']),
        "$path h1 matches the manifest"
    );

    foreach (['script', 'style', 'noscript', 'form', 'iframe'] as $tag) {
        $nodes = $xpath->query('//' . $tag);
        adriana_package_assert($nodes instanceof DOMNodeList && $nodes->length === 0, "$path contains no $tag element");
    }
    $elements = $xpath->query('//*');
    if ($elements instanceof DOMNodeList) {
        foreach ($elements as $element) {
            if (!($element instanceof DOMElement)) {
                continue;
            }
            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                adriana_package_assert(!str_starts_with($name, 'on'), "$path contains no inline event handler");
                adriana_package_assert($name !== 'style', "$path contains no inline style");
                if (in_array($name, ['href', 'src', 'action'], true)) {
                    adriana_package_assert(!str_starts_with(strtolower($value), 'javascript:'), "$path contains no javascript URL");
                }
            }
        }
    }

    $links = $xpath->query('//a[@href]');
    if ($links instanceof DOMNodeList) {
        foreach ($links as $link) {
            if (!($link instanceof DOMElement)) {
                continue;
            }
            $href = trim($link->getAttribute('href'));
            $parts = parse_url($href);
            adriana_package_assert(
                $parts === false
                    || !isset($parts['host'])
                    || !in_array(strtolower((string) $parts['host']), ['adrianagranobles.com', 'www.adrianagranobles.com'], true),
                "$path contains no absolute source-domain content link"
            );
            if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || $href === '' || $href[0] === '#') {
                continue;
            }
            $linkPath = (string) ($parts['path'] ?? '');
            if (str_starts_with($linkPath, '/')) {
                adriana_package_assert(isset($expectedPathSet[$linkPath]), "$path internal link $linkPath targets a staged route");
            }
        }
    }

    $images = $xpath->query('//img[@src]');
    if ($images instanceof DOMNodeList) {
        foreach ($images as $image) {
            if (!($image instanceof DOMElement)) {
                continue;
            }
            $src = trim($image->getAttribute('src'));
            if (str_starts_with($src, ADRIANA_PACKAGE_MEDIA_PREFIX)) {
                $referencedMedia[$src] = true;
            }
        }
    }

    $decisions = is_array($route['decisions'] ?? null) ? $route['decisions'] : [];
    $convertedFrames += count(is_array($decisions['convertedFrames'] ?? null) ? $decisions['convertedFrames'] : []);
    $nativeAnchors += (int) ($decisions['nativeFormAnchors'] ?? 0);
    $replacedForms += (int) ($decisions['replacedStaticForms'] ?? 0);
    $layoutCounts[$route['layout']] = ($layoutCounts[$route['layout']] ?? 0) + 1;
}

adriana_package_assert(count($seenPaths) === 28, '28 unique paths are staged');
adriana_package_assert(count($seenAliases) === 27, '27 unique aliases are staged');
adriana_package_assert($sectionCount === 153, '153 marked source sections are staged');
adriana_package_assert($convertedFrames === 4, 'four source frames became explicit external links');
adriana_package_assert($nativeAnchors === 1, 'one native contact form anchor is staged');
adriana_package_assert($replacedForms === 1, 'one non-operational homepage form was replaced');
adriana_package_assert($layoutCounts === [
    'home-editorial' => 1,
    'directory-hub' => 5,
    'service-detail' => 15,
    'campaign-story' => 6,
    'contact-conversion' => 1,
], 'layout distribution is exact');

$footerHtml = (string) ($manifest['shell']['footerHtml'] ?? '');
$footerHash = (string) ($manifest['shell']['footerSha256'] ?? '');
adriana_package_assert($footerHtml !== '' && str_contains($footerHtml, 'data-redcms-source-footer="' . ADRIANA_PACKAGE_ID . '"'), 'normalized source footer is marked');
adriana_package_assert(hash_equals($footerHash, hash('sha256', $footerHtml)), 'footer checksum matches');
[$footerDocument, $footerXpath] = adriana_package_fragment($footerHtml);
$footerImages = $footerXpath->query('//img[@src]');
if ($footerImages instanceof DOMNodeList) {
    foreach ($footerImages as $image) {
        if ($image instanceof DOMElement && str_starts_with($image->getAttribute('src'), ADRIANA_PACKAGE_MEDIA_PREFIX)) {
            $referencedMedia[$image->getAttribute('src')] = true;
        }
    }
}

$mediaPaths = [];
foreach ($media as $item) {
    adriana_package_assert(is_array($item), 'media row is an object');
    $target = (string) ($item['target'] ?? '');
    $publicPath = (string) ($item['publicPath'] ?? '');
    adriana_package_assert(preg_match('/\Amedia\/[A-Za-z0-9][A-Za-z0-9._-]*\z/', $target) === 1, "media target $target is safe");
    adriana_package_assert(str_starts_with($publicPath, ADRIANA_PACKAGE_MEDIA_PREFIX), "media public path $publicPath is scoped");
    adriana_package_assert(!isset($mediaPaths[$publicPath]), "media public path $publicPath is unique");
    $mediaPaths[$publicPath] = true;
    $file = $packageRoot . '/' . $target;
    adriana_package_assert(is_file($file), "staged media $target exists");
    adriana_package_assert(filesize($file) === (int) ($item['bytes'] ?? -1), "staged media $target size matches");
    adriana_package_assert(hash_equals((string) ($item['sha256'] ?? ''), hash_file('sha256', $file)), "staged media $target checksum matches");
}
ksort($mediaPaths);
ksort($referencedMedia);
adriana_package_assert(array_keys($mediaPaths) === array_keys($referencedMedia), 'all and only ledgered media are referenced');

$summary = [
    'status' => 'ok',
    'migrationId' => ADRIANA_PACKAGE_ID,
    'manifestSha256' => hash_file('sha256', $manifestPath),
    'routes' => count($routes),
    'aliases' => count($seenAliases),
    'sections' => $sectionCount,
    'mediaFiles' => count($media),
    'convertedFrames' => $convertedFrames,
    'assertions' => (int) ($GLOBALS['adrianaPackageAssertions'] ?? 0),
];
fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
