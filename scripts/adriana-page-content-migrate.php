<?php
/**
 * Convert the approved Adriana 28-route prototype into native RED-CMS areas
 * and editable pagewise content. Visible markup lives only in Other.ShortDesc.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/theme_activation_helpers.php';
require_once $projectRoot . '/includes/admin_article_helpers.php';
require_once $projectRoot . '/includes/public_form_helpers.php';

const RED_ADRIANA_PAGEWISE_MANIFEST_SHA256 = '018fb1a336a7635c85fe883ec94feaad5ff447153d819d4497bd30b9c498937c';
const RED_ADRIANA_PAGEWISE_EDITOR = 'adrianaPW';
const RED_ADRIANA_PROTOTYPE_EDITOR = 'adriana28';
const RED_ADRIANA_PAGEWISE_ARTICLE_BASE_ID = 3400000000;
const RED_ADRIANA_PAGEWISE_CHILD_BASE_ID = 3500000000;
const RED_ADRIANA_PAGEWISE_MENU_BASE_ID = 1800000000;
const RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID = 3400000100;
const RED_ADRIANA_PAGEWISE_CONTACT_FORM_ID = 3400000200;
const RED_ADRIANA_PAGEWISE_PREVIOUS_CONTACT_DEFINITION_SHA256 = '5f84ca1244b3c9a66884783469ef6ee2bed4d469f2a75d73a337acc72c43d1a1';

function red_adriana_pagewise_output(array $payload, $stream = null)
{
    fwrite(
        $stream ?: STDOUT,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );
}

function red_adriana_pagewise_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException((string) $message);
    }
}

function red_adriana_pagewise_contact_form_contract(array $nativeForm)
{
    $definition = (string) ($nativeForm['fieldDefinition'] ?? '');
    red_adriana_pagewise_assert(
        (int) ($nativeForm['articleRecordId'] ?? 0) === RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID
            && (int) ($nativeForm['formRecordId'] ?? 0) === RED_ADRIANA_PAGEWISE_CONTACT_FORM_ID
            && (int) ($nativeForm['position'] ?? 0) === 2
            && (int) ($nativeForm['positionOrder'] ?? 0) === 1
            && $definition !== '',
        'The native Contact Form mapping is incomplete.'
    );

    $fields = red_public_form_parse_definition($definition);
    $expected = [
        [
            'name' => 'reason',
            'type' => 'select',
            'required' => 'true',
            'displayname' => 'Motivo',
            'value' => 'Por favor seleccione^selected,--------^disabled,Clases de música,Canto,Programa CUDA,El Cantautor,Canto terapéutico,Coaching ontológico,La voz que sana,Eventos,Composición,Producción musical',
        ],
        [
            'name' => 'name',
            'type' => 'textfield',
            'required' => 'true',
            'displayname' => 'Nombre',
            'autocomplete' => 'name',
            'placeholder' => 'Tu nombre',
        ],
        [
            'name' => 'email',
            'type' => 'textfield',
            'required' => 'true',
            'displayname' => 'Email',
            'inputtype' => 'email',
            'autocomplete' => 'email',
            'placeholder' => 'tu@email.com',
        ],
        [
            'name' => 'message',
            'type' => 'textarea',
            'required' => 'false',
            'displayname' => 'Mensaje',
            'readonly' => 'false',
            'cols' => '45',
            'rows' => '5',
            'placeholder' => 'Cuéntame en qué momento estás y qué te gustaría trabajar',
        ],
        [
            'name' => 'Submit',
            'type' => 'button',
            'displayname' => 'Enviar mensaje',
        ],
        [
            'type' => 'paragraph',
            'paragraph' => 'Formulario local de respaldo. También puedes usar el asistente Jotform, email o WhatsApp.',
        ],
    ];
    red_adriana_pagewise_assert(count($fields) === count($expected), 'The Contact field inventory drifted.');
    foreach ($expected as $index => $expectedFields) {
        $actual = $fields[$index] ?? [];
        foreach ($expectedFields as $key => $value) {
            red_adriana_pagewise_assert(
                array_key_exists($key, $actual) && (string) $actual[$key] === $value,
                'The Contact field definition drifted at row ' . ($index + 1) . ', key ' . $key . '.'
            );
        }
    }
    red_adriana_pagewise_assert(
        stripos($definition, 'Plor favor') === false
            && stripos($definition, 'L-Youth') === false
            && stripos($definition, 'Adult') === false,
        'Example-only Contact options leaked into the production definition.'
    );

    return [
        'articleRecordId' => RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID,
        'formRecordId' => RED_ADRIANA_PAGEWISE_CONTACT_FORM_ID,
        'position' => 2,
        'positionOrder' => 1,
        'fieldDefinition' => $definition,
        'definitionSha256' => hash('sha256', $definition),
        'inputFields' => 4,
        'rows' => count($fields),
    ];
}

function red_adriana_pagewise_bind($statement, $types, array &$values)
{
    if ($types === '') {
        return true;
    }
    $references = [];
    foreach ($values as $index => &$value) {
        $references[$index] = &$value;
    }
    return mysqli_stmt_bind_param($statement, $types, ...$references);
}

function red_adriana_pagewise_statement($connection, $sql, $types = '', array $values = [])
{
    $statement = mysqli_prepare($connection, $sql);
    red_adriana_pagewise_assert($statement instanceof mysqli_stmt, 'Could not prepare the pagewise statement.');
    red_adriana_pagewise_assert(
        red_adriana_pagewise_bind($statement, $types, $values),
        'Could not bind the pagewise statement.'
    );
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        throw new RuntimeException('Could not execute the pagewise statement: ' . $error);
    }
    return $statement;
}

function red_adriana_pagewise_execute($connection, $sql, $types = '', array $values = [])
{
    $statement = red_adriana_pagewise_statement($connection, $sql, $types, $values);
    $affected = mysqli_stmt_affected_rows($statement);
    mysqli_stmt_close($statement);
    return $affected;
}

function red_adriana_pagewise_fetch_all($connection, $sql, $types = '', array $values = [])
{
    $statement = red_adriana_pagewise_statement($connection, $sql, $types, $values);
    $result = mysqli_stmt_get_result($statement);
    red_adriana_pagewise_assert($result instanceof mysqli_result, 'Could not read the pagewise result set.');
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);
    return $rows;
}

function red_adriana_pagewise_fetch_one($connection, $sql, $types = '', array $values = [])
{
    $rows = red_adriana_pagewise_fetch_all($connection, $sql, $types, $values);
    return $rows[0] ?? null;
}

function red_adriana_pagewise_scalar($connection, $sql, $types = '', array $values = [])
{
    $row = red_adriana_pagewise_fetch_one($connection, $sql, $types, $values);
    return is_array($row) && $row !== [] ? reset($row) : null;
}

function red_adriana_pagewise_article_select_list()
{
    return implode(', ', array_map(static function ($column) {
        return '`' . $column . '`';
    }, array_keys(red_admin_article_columns())));
}

function red_adriana_pagewise_normalize_article(array $row)
{
    $normalized = [];
    $integerColumns = red_admin_article_integer_columns();
    foreach (array_keys(red_admin_article_columns()) as $column) {
        red_adriana_pagewise_assert(array_key_exists($column, $row), 'Article row is missing ' . $column . '.');
        $normalized[$column] = isset($integerColumns[$column]) ? (int) $row[$column] : (string) $row[$column];
    }
    return $normalized;
}

function red_adriana_pagewise_expected_article($recordId, array $data)
{
    $data['RecordID'] = (int) $recordId;
    return red_adriana_pagewise_normalize_article($data);
}

function red_adriana_pagewise_prototype_owner_data(array $route, $isHome)
{
    $bodyHtml = (string) $route['bodyHtml'];
    return [
        'Title' => (string) $route['title'],
        'Component' => 'Other',
        'Alias' => (string) $route['alias'],
        'Sections' => 'Home',
        'HomePosition' => $isHome ? 1 : 0,
        'HomePositionOrder' => $isHome ? 1 : 0,
        'SectionPosition' => 0,
        'SectionPositionOrder' => 0,
        'Categories' => '',
        'CategoryPosition' => 0,
        'CategoryPositionOrder' => 0,
        'SubCategories' => '',
        'SubCategoryPosition' => 0,
        'SubCategoryPositionOrder' => 0,
        'Layout' => (string) $route['layout'],
        'Article' => '',
        'PagePosition' => 1,
        'PagePositionOrder' => 1,
        'Tags' => red_admin_tag_list((string) $route['title']),
        'Active' => 'Y',
        'HomeFeature' => '',
        'HomeFeatures' => '',
        'HomeFeatures_Order' => 0,
        'SectionFeatures' => '',
        'SectionFeatures_Order' => 0,
        'CategoryFeatures' => '',
        'CategoryFeatures_Order' => 0,
        'SubCategoryFeatures' => '',
        'SubCategoryFeatures_Order' => 0,
        'ArticleFeatures' => '',
        'StartDate' => '1970-01-01 00:00:00',
        'EventDate' => '1970-01-01 00:00:00',
        'ExpDate' => '9999-12-31 23:59:59',
        'ShortDesc' => $isHome ? $bodyHtml : (string) $route['description'],
        'LongDesc' => $bodyHtml,
        'SliderDesc' => '',
        'Link' => '',
        'NewWindow' => '',
        'VideoSrc' => '',
        'AlbumSrc' => '',
        'BigPict' => '',
        'SmallPict' => '',
        'SmallPictAlign' => '',
        'SmallPict2' => '',
        'SmallPictAlign2' => '',
        'EditedBy' => RED_ADRIANA_PROTOTYPE_EDITOR,
        'Language' => 'sp',
    ];
}

function red_adriana_pagewise_contact_article_data($editor)
{
    return [
        'Title' => 'Formulario de contacto',
        'Component' => 'Form',
        'Alias' => 'contacto-form',
        'Sections' => 'Home',
        'HomePosition' => 0,
        'HomePositionOrder' => 0,
        'SectionPosition' => 0,
        'SectionPositionOrder' => 0,
        'Categories' => '',
        'CategoryPosition' => 0,
        'CategoryPositionOrder' => 0,
        'SubCategories' => '',
        'SubCategoryPosition' => 0,
        'SubCategoryPositionOrder' => 0,
        'Layout' => 'contact-conversion',
        'Article' => 'contacto',
        'PagePosition' => 2,
        'PagePositionOrder' => 1,
        'Tags' => 'contacto',
        'Active' => 'Y',
        'HomeFeature' => '',
        'HomeFeatures' => '',
        'HomeFeatures_Order' => 0,
        'SectionFeatures' => '',
        'SectionFeatures_Order' => 0,
        'CategoryFeatures' => '',
        'CategoryFeatures_Order' => 0,
        'SubCategoryFeatures' => '',
        'SubCategoryFeatures_Order' => 0,
        'ArticleFeatures' => '',
        'StartDate' => '1970-01-01 00:00:00',
        'EventDate' => '1970-01-01 00:00:00',
        'ExpDate' => '9999-12-31 23:59:59',
        'ShortDesc' => '',
        'LongDesc' => '',
        'SliderDesc' => '',
        'Link' => '',
        'NewWindow' => '',
        'VideoSrc' => '',
        'AlbumSrc' => '',
        'BigPict' => '',
        'SmallPict' => '',
        'SmallPictAlign' => '',
        'SmallPict2' => '',
        'SmallPictAlign2' => '',
        'EditedBy' => (string) $editor,
        'Language' => 'sp',
    ];
}

function red_adriana_pagewise_extract_sections(array $route)
{
    $sections = preg_split(
        '/(?=<section\\b[^>]*\\bdata-redcms-source-section="\\d+")/i',
        (string) $route['bodyHtml'],
        -1,
        PREG_SPLIT_NO_EMPTY
    );
    red_adriana_pagewise_assert(is_array($sections), 'Could not extract source sections.');
    red_adriana_pagewise_assert(
        count($sections) === (int) $route['sectionCount']
            && implode('', $sections) === (string) $route['bodyHtml'],
        'Source section extraction changed the approved page bytes.'
    );
    foreach ($sections as $index => $html) {
        $sectionNumber = $index + 1;
        red_adriana_pagewise_assert(
            substr_count($html, 'data-redcms-source-section="' . $sectionNumber . '"') === 1,
            'Source section marker drifted at section ' . $sectionNumber . '.'
        );
        red_adriana_pagewise_assert(
            preg_match('/<(?:script|style|noscript|iframe|form)\\b/i', $html) !== 1
                && preg_match('/\\son[a-z]+\\s*=/i', $html) !== 1
                && stripos($html, 'javascript:') === false,
            'Executable markup appeared in source section ' . $sectionNumber . '.'
        );
    }
    return $sections;
}

function red_adriana_pagewise_rewrite_internal_links($html, array $redirects)
{
    $rewritten = preg_replace_callback(
        '/\\bhref="([^"]*)"/i',
        static function (array $matches) use ($redirects) {
            $href = (string) $matches[1];
            if ($href === '' || $href[0] !== '/') {
                return $matches[0];
            }
            if (preg_match('/\\A([^?#]*)(\\?[^#]*)?(#.*)?\\z/', $href, $parts) !== 1) {
                return $matches[0];
            }
            $path = (string) ($parts[1] ?? '');
            if (!isset($redirects[$path])) {
                return $matches[0];
            }
            $suffix = (string) ($parts[2] ?? '') . (string) ($parts[3] ?? '');
            return 'href="' . $redirects[$path] . $suffix . '"';
        },
        (string) $html
    );
    red_adriana_pagewise_assert(is_string($rewritten), 'Could not rewrite approved internal links.');
    red_adriana_pagewise_assert(
        preg_match('/\\bhref="\\/[^"]+\\.html(?:[?#][^"]*)?"/i', $rewritten) !== 1,
        'A legacy root HTML link remains after canonical rewriting.'
    );
    return $rewritten;
}

function red_adriana_pagewise_section_title($html, array $route, $sectionNumber)
{
    $title = '';
    if (preg_match('/<h[1-3]\\b[^>]*>(.*?)<\\/h[1-3]>/is', (string) $html, $matches) === 1) {
        $title = html_entity_decode(strip_tags((string) $matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/\\s+/u', ' ', trim($title));
    }
    if (!is_string($title) || $title === '') {
        $title = (string) $route['h1'] . ' — sección ' . (int) $sectionNumber;
    }
    return mb_substr($title, 0, 240, 'UTF-8');
}

function red_adriana_pagewise_menu_rows(array $navigation)
{
    $rows = [];
    $nextId = RED_ADRIANA_PAGEWISE_MENU_BASE_ID;
    foreach ($navigation as $rootIndex => $root) {
        $rootId = $nextId++;
        $rows[] = [
            'RecordID' => $rootId,
            'RootOrder' => '1',
            'Title' => 'Top Navigation',
            'Label' => (string) $root['label'],
            'Parent' => 0,
            'Link' => (string) $root['link'],
            'NewWindow' => '',
            'MenuOrder' => $rootIndex + 1,
            'Active' => 'Y',
            'Language' => 'sp',
        ];
        foreach ((array) $root['children'] as $childIndex => $child) {
            $rows[] = [
                'RecordID' => $nextId++,
                'RootOrder' => '2',
                'Title' => 'Top Navigation',
                'Label' => (string) $child['label'],
                'Parent' => $rootId,
                'Link' => (string) $child['link'],
                'NewWindow' => '',
                'MenuOrder' => $childIndex + 1,
                'Active' => 'Y',
                'Language' => 'sp',
            ];
        }
    }
    return $rows;
}

function red_adriana_pagewise_package($projectRoot)
{
    $packageRoot = $projectRoot . '/content-migrations/adriana-granobles-v4';
    $manifestFile = $packageRoot . '/routes.json';
    $mappingFile = $packageRoot . '/pages/site.json';
    $cudaFile = $packageRoot . '/pages/programa-cuda.json';
    foreach ([$manifestFile, $mappingFile, $cudaFile] as $file) {
        red_adriana_pagewise_assert(is_file($file), 'A required pagewise package file is missing.');
    }

    $manifestJson = file_get_contents($manifestFile);
    $mappingJson = file_get_contents($mappingFile);
    $cudaJson = file_get_contents($cudaFile);
    red_adriana_pagewise_assert(
        is_string($manifestJson) && is_string($mappingJson) && is_string($cudaJson),
        'A required pagewise package file is unreadable.'
    );
    red_adriana_pagewise_assert(
        hash_equals(RED_ADRIANA_PAGEWISE_MANIFEST_SHA256, hash('sha256', $manifestJson)),
        'The approved 28-route manifest digest drifted.'
    );
    $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
    $mapping = json_decode($mappingJson, true, 512, JSON_THROW_ON_ERROR);
    $cudaMapping = json_decode($cudaJson, true, 512, JSON_THROW_ON_ERROR);
    red_adriana_pagewise_assert(
        ($mapping['schemaVersion'] ?? null) === 2
            && ($mapping['migrationId'] ?? '') === 'adriana-granobles-v4-pagewise-site'
            && ($mapping['manifestSha256'] ?? '') === RED_ADRIANA_PAGEWISE_MANIFEST_SHA256,
        'The site pagewise mapping header drifted.'
    );
    $componentModel = $mapping['componentModel'] ?? [];
    red_adriana_pagewise_assert(
        ($componentModel['articleRouteOwner'] ?? '') === 'Article'
            && (int) ($componentModel['articleRouteOwnerPosition'] ?? -1) === 0
            && ($componentModel['areaRouteOwner'] ?? '') === 'Section'
            && ($componentModel['visibleComponent'] ?? '') === 'Other'
            && ($componentModel['editableHtmlField'] ?? '') === 'ShortDesc'
            && ($componentModel['visibleEditor'] ?? '') === 'plain-html',
        'The site editable component model drifted.'
    );

    $manifestRoutes = $manifest['routes'] ?? null;
    $mappedRoutes = $mapping['routes'] ?? null;
    red_adriana_pagewise_assert(
        is_array($manifestRoutes) && is_array($mappedRoutes)
            && count($manifestRoutes) === 28 && count($mappedRoutes) === 28,
        'The pagewise site package must map exactly 28 routes.'
    );
    $redirects = ['/index.html' => '/'];
    $canonicalPaths = [];
    foreach ($mappedRoutes as $index => $definition) {
        red_adriana_pagewise_assert(is_array($definition), 'A site route mapping is invalid.');
        $sourcePath = (string) ($definition['sourcePath'] ?? '');
        $canonicalPath = (string) ($definition['canonicalPath'] ?? '');
        red_adriana_pagewise_assert(
            $sourcePath === (string) ($manifestRoutes[$index]['path'] ?? '')
                && preg_match('/\\A\\/(?:[a-z0-9-]+(?:\\/[a-z0-9-]+)?\\/?)?\\z/', $canonicalPath) === 1
                && !isset($canonicalPaths[$canonicalPath]),
            'A mapped source or canonical route drifted at index ' . $index . '.'
        );
        $canonicalPaths[$canonicalPath] = true;
        if ($sourcePath !== '/') {
            $redirects[$sourcePath] = $canonicalPath;
        }
    }

    $layoutMaximums = [
        'home-editorial' => 5,
        'directory-hub' => 5,
        'service-detail' => 5,
        'campaign-story' => 5,
        'contact-conversion' => 3,
    ];
    $preparedRoutes = [];
    $totalSections = 0;
    $articleRoutes = 0;
    $areaRoutes = 0;
    $sectionIds = [];
    $contactForm = null;
    foreach ($mappedRoutes as $index => $definition) {
        $route = $manifestRoutes[$index];
        $kind = (string) ($definition['kind'] ?? '');
        $layout = (string) ($definition['layout'] ?? '');
        $section = (string) ($definition['section'] ?? '');
        $alias = (string) ($definition['alias'] ?? '');
        $canonicalPath = (string) $definition['canonicalPath'];
        red_adriana_pagewise_assert(
            in_array($kind, ['home', 'section', 'article'], true)
                && isset($layoutMaximums[$layout])
                && $layout === (string) ($route['layout'] ?? '')
                && hash_equals((string) $route['bodySha256'], hash('sha256', (string) $route['bodyHtml']))
                && preg_match('/\\A(?:Home|[a-z0-9-]+)\\z/', $section) === 1,
            'A route kind, layout, body, or section drifted at ' . $canonicalPath . '.'
        );
        if ($kind === 'home') {
            red_adriana_pagewise_assert(
                $index === 0 && $canonicalPath === '/' && $section === 'Home' && $alias === '',
                'The Home area mapping drifted.'
            );
            $areaRoutes++;
        } elseif ($kind === 'section') {
            $sectionId = (int) ($definition['sectionRecordId'] ?? 0);
            red_adriana_pagewise_assert(
                substr($canonicalPath, -1) === '/'
                    && $alias === ''
                    && $sectionId === 3600000000 + $index
                    && !isset($sectionIds[$sectionId])
                    && trim((string) ($definition['sectionTitle'] ?? '')) !== '',
                'A RED-CMS Section mapping drifted at ' . $canonicalPath . '.'
            );
            $sectionIds[$sectionId] = true;
            $areaRoutes++;
        } else {
            $ownerId = (int) ($definition['ownerRecordId'] ?? 0);
            red_adriana_pagewise_assert(
                substr($canonicalPath, -1) !== '/'
                    && preg_match('/\\A[a-z0-9][a-z0-9-]*\\z/', $alias) === 1
                    && $ownerId === RED_ADRIANA_PAGEWISE_ARTICLE_BASE_ID + $index,
                'An Article route-owner mapping drifted at ' . $canonicalPath . '.'
            );
            $articleRoutes++;
        }

        if ($canonicalPath === '/contacto') {
            red_adriana_pagewise_assert(
                $kind === 'article'
                    && $layout === 'contact-conversion'
                    && $alias === 'contacto'
                    && $contactForm === null
                    && is_array($definition['nativeForm'] ?? null),
                'The Contact route/native Form mapping drifted.'
            );
            $contactForm = red_adriana_pagewise_contact_form_contract($definition['nativeForm']);
        } else {
            red_adriana_pagewise_assert(
                !array_key_exists('nativeForm', $definition),
                'A native Form mapping leaked outside Contact.'
            );
        }

        $sourceSections = red_adriana_pagewise_extract_sections($route);
        $positions = $definition['positions'] ?? null;
        red_adriana_pagewise_assert(
            is_array($positions) && array_is_list($positions) && count($positions) === count($sourceSections),
            'The section-position list drifted at ' . $canonicalPath . '.'
        );
        $previousPosition = 0;
        $positionOrders = [];
        $positionCounts = [];
        foreach ($positions as $positionIndex => $positionValue) {
            red_adriana_pagewise_assert(
                is_int($positionValue)
                    && $positionValue >= 1
                    && $positionValue <= $layoutMaximums[$layout]
                    && $positionValue >= $previousPosition,
                'Section positions must be valid, contiguous groups at ' . $canonicalPath . '.'
            );
            $previousPosition = $positionValue;
            $positionCounts[$positionValue] = ($positionCounts[$positionValue] ?? 0) + 1;
            $positionOrders[$positionIndex] = $positionCounts[$positionValue];
        }
        red_adriana_pagewise_assert(($positions[0] ?? 0) === 1, 'Every source page must begin in position 1.');

        $renderedSections = [];
        $titles = [];
        foreach ($sourceSections as $sectionIndex => $html) {
            $renderedSections[$sectionIndex] = red_adriana_pagewise_rewrite_internal_links($html, $redirects);
            $titles[$sectionIndex] = red_adriana_pagewise_section_title($html, $route, $sectionIndex + 1);
        }
        $totalSections += count($sourceSections);
        $preparedRoutes[] = [
            'index' => $index,
            'definition' => $definition,
            'route' => $route,
            'sourceSections' => $sourceSections,
            'renderedSections' => $renderedSections,
            'titles' => $titles,
            'positionOrders' => $positionOrders,
            'distribution' => $positionCounts,
        ];
    }
    red_adriana_pagewise_assert(
        $totalSections === 153 && $articleRoutes === 24 && $areaRoutes === 4 && is_array($contactForm),
        'The pagewise route/section ownership inventory drifted.'
    );

    $cudaSite = $preparedRoutes[22];
    $cudaPositions = array_map(static function (array $section) {
        return (int) $section['position'];
    }, (array) ($cudaMapping['sections'] ?? []));
    red_adriana_pagewise_assert(
        ($cudaMapping['page']['path'] ?? '') === '/programa-cuda.html'
            && (int) ($cudaMapping['page']['ownerRecordId'] ?? 0) === 3400000022
            && $cudaPositions === $cudaSite['definition']['positions']
            && hash_equals(
                (string) ($cudaMapping['page']['bodySha256'] ?? ''),
                (string) $cudaSite['route']['bodySha256']
            ),
        'The locked CUDA pilot no longer matches the site pagewise map.'
    );

    $navigation = $mapping['navigation'] ?? null;
    red_adriana_pagewise_assert(is_array($navigation) && array_is_list($navigation), 'Canonical navigation is missing.');
    $menuRows = red_adriana_pagewise_menu_rows($navigation);
    red_adriana_pagewise_assert(count($navigation) === 9 && count($menuRows) === 28, 'Canonical navigation count drifted.');
    $menuLinks = [];
    foreach ($menuRows as $row) {
        $link = (string) $row['Link'];
        red_adriana_pagewise_assert(isset($canonicalPaths[$link]) && !isset($menuLinks[$link]), 'Navigation link drifted: ' . $link);
        $menuLinks[$link] = true;
    }
    red_adriana_pagewise_assert(
        count($menuLinks) === count($canonicalPaths)
            && array_diff_key($menuLinks, $canonicalPaths) === []
            && array_diff_key($canonicalPaths, $menuLinks) === [],
        'Canonical navigation must expose every migrated page once.'
    );

    $footerSource = (string) ($manifest['shell']['footerHtml'] ?? '');
    $footerCanonical = red_adriana_pagewise_rewrite_internal_links($footerSource, $redirects);
    red_adriana_pagewise_assert($footerCanonical !== $footerSource, 'Canonical footer link rewriting made no change.');

    return [
        'manifest' => $manifest,
        'mapping' => $mapping,
        'mappingFile' => $mappingFile,
        'mappingSha256' => hash('sha256', $mappingJson),
        'routes' => $preparedRoutes,
        'redirects' => $redirects,
        'menuRows' => $menuRows,
        'contactForm' => $contactForm,
        'footerSource' => $footerSource,
        'footerCanonical' => $footerCanonical,
        'summary' => [
            'routes' => 28,
            'articleRoutes' => $articleRoutes,
            'areaRoutes' => $areaRoutes,
            'sections' => $totalSections,
            'menuRows' => count($menuRows),
            'legacyRedirects' => count($redirects),
            'contactFormInputFields' => (int) $contactForm['inputFields'],
            'contactFormDefinitionSha256' => (string) $contactForm['definitionSha256'],
        ],
    ];
}

function red_adriana_pagewise_expected_rows(array $package)
{
    $prototypeOwners = [];
    $owners = [];
    $children = [];
    $sections = [];
    foreach ($package['routes'] as $prepared) {
        $index = (int) $prepared['index'];
        $definition = $prepared['definition'];
        $route = $prepared['route'];
        $kind = (string) $definition['kind'];
        $prototypeId = RED_ADRIANA_PAGEWISE_ARTICLE_BASE_ID + $index;
        $prototype = red_adriana_pagewise_expected_article(
            $prototypeId,
            red_adriana_pagewise_prototype_owner_data($route, $kind === 'home')
        );
        $prototypeOwners[$prototypeId] = $prototype;

        if ($kind === 'article') {
            $ownerData = red_adriana_pagewise_prototype_owner_data($route, false);
            $ownerData['Component'] = 'Article';
            $ownerData['Alias'] = (string) $definition['alias'];
            $ownerData['Sections'] = (string) $definition['section'];
            $ownerData['PagePosition'] = 0;
            $ownerData['PagePositionOrder'] = 0;
            $ownerData['LongDesc'] = '';
            $ownerData['EditedBy'] = RED_ADRIANA_PAGEWISE_EDITOR;
            $owners[$prototypeId] = red_adriana_pagewise_expected_article($prototypeId, $ownerData);
        } elseif ($kind === 'section') {
            $sectionId = (int) $definition['sectionRecordId'];
            $sections[$sectionId] = [
                'RecordID' => $sectionId,
                'Sections' => (string) $definition['section'],
                'Title' => (string) $definition['sectionTitle'],
                'Layout' => (string) $definition['layout'],
                'QueryLimit' => '100',
                'AccessLevel' => 'Public',
                'Features' => '',
                'Active' => 'Y',
                'Description' => (string) $route['description'],
                'Tags' => red_admin_tag_list((string) $route['title']),
                'Language' => 'sp',
            ];
        }

        foreach ($prepared['renderedSections'] as $sectionIndex => $html) {
            $sectionNumber = $sectionIndex + 1;
            $recordId = RED_ADRIANA_PAGEWISE_CHILD_BASE_ID + ($index * 100) + $sectionNumber;
            $data = red_adriana_pagewise_prototype_owner_data($route, false);
            $data['Title'] = (string) $prepared['titles'][$sectionIndex];
            $data['Component'] = 'Other';
            $data['Alias'] = '';
            $data['Sections'] = (string) $definition['section'];
            $data['Article'] = $kind === 'article' ? (string) $definition['alias'] : '';
            $data['HomePosition'] = 0;
            $data['HomePositionOrder'] = 0;
            $data['SectionPosition'] = 0;
            $data['SectionPositionOrder'] = 0;
            $data['PagePosition'] = 0;
            $data['PagePositionOrder'] = 0;
            $position = (int) $definition['positions'][$sectionIndex];
            $positionOrder = (int) $prepared['positionOrders'][$sectionIndex];
            if ($kind === 'home') {
                $data['HomePosition'] = $position;
                $data['HomePositionOrder'] = $positionOrder;
            } elseif ($kind === 'section') {
                $data['SectionPosition'] = $position;
                $data['SectionPositionOrder'] = $positionOrder;
            } else {
                $data['PagePosition'] = $position;
                $data['PagePositionOrder'] = $positionOrder;
            }
            $data['Tags'] = red_admin_tag_list($data['Title']);
            $data['ShortDesc'] = (string) $html;
            $data['LongDesc'] = '';
            $data['EditedBy'] = RED_ADRIANA_PAGEWISE_EDITOR;
            $children[$recordId] = red_adriana_pagewise_expected_article($recordId, $data);
        }
    }

    return [
        'prototypeOwners' => $prototypeOwners,
        'owners' => $owners,
        'children' => $children,
        'sections' => $sections,
        'prototypeContact' => red_adriana_pagewise_expected_article(
            RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID,
            red_adriana_pagewise_contact_article_data(RED_ADRIANA_PROTOTYPE_EDITOR)
        ),
        'contact' => red_adriana_pagewise_expected_article(
            RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID,
            red_adriana_pagewise_contact_article_data(RED_ADRIANA_PAGEWISE_EDITOR)
        ),
        'contactFormDefinition' => (string) $package['contactForm']['fieldDefinition'],
    ];
}

function red_adriana_pagewise_fetch_article($connection, $recordId, $lock = false)
{
    $row = red_adriana_pagewise_fetch_one(
        $connection,
        'SELECT ' . red_adriana_pagewise_article_select_list() . ' FROM RED_Articles WHERE RecordID=? LIMIT 1' .
            ($lock ? ' FOR UPDATE' : ''),
        'i',
        [(int) $recordId]
    );
    return is_array($row) ? red_adriana_pagewise_normalize_article($row) : null;
}

function red_adriana_pagewise_fetch_contact_form($connection, $lock = false)
{
    $row = red_adriana_pagewise_fetch_one(
        $connection,
        "SELECT RecordID, CAST(RefID AS UNSIGNED) AS RefID, Title, Alias, FormType, ShortDesc, LongDesc,\n" .
            "Subject, Submitter, Destinatary, CC, BCC, Response, TableName\n" .
            'FROM RED_C_Form WHERE RecordID=? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''),
        'i',
        [RED_ADRIANA_PAGEWISE_CONTACT_FORM_ID]
    );
    if (!is_array($row)) {
        return null;
    }
    $normalized = [];
    foreach (['RecordID', 'RefID', 'Title', 'Alias', 'FormType', 'ShortDesc', 'LongDesc', 'Subject', 'Submitter', 'Destinatary', 'CC', 'BCC', 'Response', 'TableName'] as $column) {
        red_adriana_pagewise_assert(array_key_exists($column, $row), 'Contact Form row is missing ' . $column . '.');
        $normalized[$column] = in_array($column, ['RecordID', 'RefID'], true)
            ? (int) $row[$column]
            : (string) $row[$column];
    }
    return $normalized;
}

function red_adriana_pagewise_contact_operational_sha256(array $row)
{
    unset($row['LongDesc']);
    return hash(
        'sha256',
        json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );
}

function red_adriana_pagewise_assert_contact_form_row($row)
{
    red_adriana_pagewise_assert(
        is_array($row)
            && (int) $row['RecordID'] === RED_ADRIANA_PAGEWISE_CONTACT_FORM_ID
            && (int) $row['RefID'] === RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID
            && strcasecmp((string) $row['FormType'], 'Contact') === 0
            && (string) $row['LongDesc'] !== '',
        'The native Contact Form relationship/type is invalid.'
    );
}

function red_adriana_pagewise_update_contact_definition($connection, $expectedDefinition, $requireKnownPredecessor)
{
    $current = red_adriana_pagewise_fetch_contact_form($connection, true);
    red_adriana_pagewise_assert_contact_form_row($current);
    if (hash_equals((string) $expectedDefinition, (string) $current['LongDesc'])) {
        return false;
    }
    if ($requireKnownPredecessor) {
        red_adriana_pagewise_assert(
            hash_equals(
                RED_ADRIANA_PAGEWISE_PREVIOUS_CONTACT_DEFINITION_SHA256,
                hash('sha256', (string) $current['LongDesc'])
            ),
            'The managed Contact definition was edited; refusing to overwrite it.'
        );
    }

    $operationalBefore = red_adriana_pagewise_contact_operational_sha256($current);
    red_adriana_pagewise_assert(
        red_adriana_pagewise_execute(
            $connection,
            "UPDATE RED_C_Form SET LongDesc=?\n" .
                'WHERE RecordID=? AND CAST(RefID AS UNSIGNED)=? AND LongDesc=?',
            'siis',
            [
                (string) $expectedDefinition,
                RED_ADRIANA_PAGEWISE_CONTACT_FORM_ID,
                RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID,
                (string) $current['LongDesc'],
            ]
        ) === 1,
        'The native Contact definition changed concurrently or could not be updated.'
    );
    $updated = red_adriana_pagewise_fetch_contact_form($connection, true);
    red_adriana_pagewise_assert_contact_form_row($updated);
    red_adriana_pagewise_assert(
        hash_equals((string) $expectedDefinition, (string) $updated['LongDesc'])
            && hash_equals($operationalBefore, red_adriana_pagewise_contact_operational_sha256($updated)),
        'The native Contact update changed operational form metadata.'
    );
    return true;
}

function red_adriana_pagewise_section_select_list()
{
    return 'RecordID, Sections, Title, Layout, QueryLimit, AccessLevel, Features, Active, Description, Tags, Language';
}

function red_adriana_pagewise_normalize_section(array $row)
{
    $normalized = [];
    foreach (['RecordID', 'Sections', 'Title', 'Layout', 'QueryLimit', 'AccessLevel', 'Features', 'Active', 'Description', 'Tags', 'Language'] as $column) {
        red_adriana_pagewise_assert(array_key_exists($column, $row), 'Section row is missing ' . $column . '.');
        $normalized[$column] = $column === 'RecordID' ? (int) $row[$column] : (string) $row[$column];
    }
    return $normalized;
}

function red_adriana_pagewise_fetch_section($connection, $recordId, $lock = false)
{
    $row = red_adriana_pagewise_fetch_one(
        $connection,
        'SELECT ' . red_adriana_pagewise_section_select_list() . ' FROM RED_Sections WHERE RecordID=? LIMIT 1' .
            ($lock ? ' FOR UPDATE' : ''),
        'i',
        [(int) $recordId]
    );
    return is_array($row) ? red_adriana_pagewise_normalize_section($row) : null;
}

function red_adriana_pagewise_fetch_menu_rows($connection, $lock = false)
{
    $rows = red_adriana_pagewise_fetch_all(
        $connection,
        "SELECT RecordID, RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, Active, Language\n" .
            "FROM RED_Menu WHERE Language='sp' ORDER BY RecordID" . ($lock ? ' FOR UPDATE' : '')
    );
    foreach ($rows as &$row) {
        foreach (['RecordID', 'Parent', 'MenuOrder'] as $column) {
            $row[$column] = (int) $row[$column];
        }
        foreach (['RootOrder', 'Title', 'Label', 'Link', 'NewWindow', 'Active', 'Language'] as $column) {
            $row[$column] = (string) $row[$column];
        }
    }
    unset($row);
    return $rows;
}

function red_adriana_pagewise_expected_menu_rows(array $rows)
{
    usort($rows, static function (array $left, array $right) {
        return $left['RecordID'] <=> $right['RecordID'];
    });
    return $rows;
}

function red_adriana_pagewise_verify_final($connection, array $package, array $expected)
{
    foreach ($expected['owners'] as $recordId => $row) {
        red_adriana_pagewise_assert(
            red_adriana_pagewise_fetch_article($connection, $recordId) === $row,
            'Metadata-only Article owner drifted: ' . $recordId . '.'
        );
    }
    foreach ($expected['prototypeOwners'] as $recordId => $prototype) {
        $kind = (string) $package['routes'][$recordId - RED_ADRIANA_PAGEWISE_ARTICLE_BASE_ID]['definition']['kind'];
        if ($kind !== 'article') {
            red_adriana_pagewise_assert(
                red_adriana_pagewise_fetch_article($connection, $recordId) === null,
                'An area route retained its prototype Article owner: ' . $recordId . '.'
            );
        }
    }
    foreach ($expected['children'] as $recordId => $row) {
        red_adriana_pagewise_assert(
            red_adriana_pagewise_fetch_article($connection, $recordId) === $row,
            'Editable Other source section drifted: ' . $recordId . '.'
        );
    }
    foreach ($expected['sections'] as $recordId => $row) {
        red_adriana_pagewise_assert(
            red_adriana_pagewise_fetch_section($connection, $recordId) === $row,
            'Canonical RED-CMS Section drifted: ' . $recordId . '.'
        );
    }
    red_adriana_pagewise_assert(
        red_adriana_pagewise_fetch_article($connection, RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID) === $expected['contact'],
        'Native Contact Form article drifted.'
    );
    $contactForm = red_adriana_pagewise_fetch_contact_form($connection);
    red_adriana_pagewise_assert_contact_form_row($contactForm);
    red_adriana_pagewise_assert(
        hash_equals((string) $expected['contactFormDefinition'], (string) $contactForm['LongDesc']),
        'Native Contact Form field definition drifted.'
    );
    $managedCount = (int) red_adriana_pagewise_scalar(
        $connection,
        'SELECT COUNT(*) FROM RED_Articles WHERE EditedBy=?',
        's',
        [RED_ADRIANA_PAGEWISE_EDITOR]
    );
    red_adriana_pagewise_assert(
        $managedCount === 178
            && (int) red_adriana_pagewise_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Articles WHERE EditedBy=? AND Component=\'Other\' AND LongDesc=\'\'',
                's',
                [RED_ADRIANA_PAGEWISE_EDITOR]
            ) === 153
            && (int) red_adriana_pagewise_scalar(
                $connection,
                'SELECT COUNT(*) FROM RED_Articles WHERE EditedBy=? AND Component=\'Article\' AND PagePosition=0 AND LongDesc=\'\'',
                's',
                [RED_ADRIANA_PAGEWISE_EDITOR]
            ) === 24,
        'Final pagewise component inventory drifted.'
    );

    $menuRows = red_adriana_pagewise_fetch_menu_rows($connection);
    red_adriana_pagewise_assert(
        $menuRows === red_adriana_pagewise_expected_menu_rows($package['menuRows']),
        'Canonical top navigation drifted.'
    );
    $footerRows = red_adriana_pagewise_fetch_all(
        $connection,
        "SELECT Content FROM RED_Advanced WHERE Item='Website_Footer' AND Language='sp' ORDER BY RecordID"
    );
    red_adriana_pagewise_assert(
        count($footerRows) === 1 && (string) $footerRows[0]['Content'] === $package['footerCanonical'],
        'Canonical footer navigation drifted.'
    );
    $home = red_adriana_pagewise_fetch_one(
        $connection,
        "SELECT Sections, Layout, Active, Language FROM RED_Sections WHERE LOWER(Sections)='home' AND Language='sp'"
    );
    red_adriana_pagewise_assert(
        is_array($home)
            && strtolower((string) $home['Sections']) === 'home'
            && (string) $home['Layout'] === 'home-editorial'
            && (string) $home['Active'] === 'Y'
            && (string) $home['Language'] === 'sp',
        'Home Section route drifted.'
    );

    $state = red_theme_activation_read_state($connection);
    red_adriana_pagewise_assert(
        ($state['activeThemeId'] ?? '') === 'adriana-granobles',
        'Adriana theme is not active in the disposable clone.'
    );
    $compatibility = red_theme_compatibility_live_preflight('adriana-granobles', $connection, dirname(__DIR__));
    red_adriana_pagewise_assert(!empty($compatibility['compatible']), 'The pagewise site is not theme-compatible.');

    $distributions = [];
    foreach ($package['routes'] as $prepared) {
        $distributions[$prepared['definition']['canonicalPath']] = $prepared['distribution'];
    }
    return [
        'routes' => 28,
        'articleRoutes' => 24,
        'areaRoutes' => 4,
        'editableOtherRecords' => 153,
        'metadataArticleOwners' => 24,
        'nativeForms' => 1,
        'contactFormInputFields' => (int) $package['contactForm']['inputFields'],
        'contactFormDefinitionSha256' => hash('sha256', (string) $contactForm['LongDesc']),
        'navigationRows' => 28,
        'visibleHtmlField' => 'ShortDesc',
        'longDescBytes' => 0,
        'themeCompatible' => true,
        'distributions' => $distributions,
    ];
}

function red_adriana_pagewise_verify_initial($connection, array $package, array $expected)
{
    foreach ($expected['prototypeOwners'] as $recordId => $row) {
        red_adriana_pagewise_assert(
            red_adriana_pagewise_fetch_article($connection, $recordId) === $row,
            'A route is not in the approved bulk-prototype state: ' . $recordId . '.'
        );
    }
    foreach (array_keys($expected['children']) as $recordId) {
        red_adriana_pagewise_assert(
            red_adriana_pagewise_fetch_article($connection, $recordId) === null,
            'A deterministic pagewise child RecordID is already occupied: ' . $recordId . '.'
        );
    }
    foreach (array_keys($expected['sections']) as $recordId) {
        red_adriana_pagewise_assert(
            red_adriana_pagewise_fetch_section($connection, $recordId) === null,
            'A deterministic pagewise Section RecordID is already occupied: ' . $recordId . '.'
        );
    }
    red_adriana_pagewise_assert(
        red_adriana_pagewise_fetch_article($connection, RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID) === $expected['prototypeContact'],
        'The native Contact Form article is not in the approved prototype state.'
    );
    red_adriana_pagewise_assert_contact_form_row(red_adriana_pagewise_fetch_contact_form($connection));
    red_adriana_pagewise_assert(
        red_adriana_pagewise_fetch_menu_rows($connection) === red_adriana_pagewise_expected_menu_rows($package['menuRows']),
        'The canonical top navigation was not installed by the base migration.'
    );
    $footerRows = red_adriana_pagewise_fetch_all(
        $connection,
        "SELECT Content FROM RED_Advanced WHERE Item='Website_Footer' AND Language='sp' ORDER BY RecordID"
    );
    red_adriana_pagewise_assert(
        count($footerRows) === 1 && (string) $footerRows[0]['Content'] === $package['footerSource'],
        'The source footer is not in the approved prototype state.'
    );
}

function red_adriana_pagewise_insert_section($connection, array $row)
{
    return red_adriana_pagewise_execute(
        $connection,
        "INSERT INTO RED_Sections\n" .
            '(RecordID, Sections, Title, Layout, QueryLimit, AccessLevel, Features, Active, Description, Tags, Language) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'issssssssss',
        [
            $row['RecordID'],
            $row['Sections'],
            $row['Title'],
            $row['Layout'],
            $row['QueryLimit'],
            $row['AccessLevel'],
            $row['Features'],
            $row['Active'],
            $row['Description'],
            $row['Tags'],
            $row['Language'],
        ]
    ) === 1;
}

function red_adriana_pagewise_database_guard()
{
    $target = trim((string) getenv('RED_DB_NAME'));
    $primary = trim((string) getenv('RED_PRIMARY_DB_NAME'));
    red_adriana_pagewise_assert($target !== '' && $primary !== '', 'RED_DB_NAME and RED_PRIMARY_DB_NAME are required.');
    red_adriana_pagewise_assert(
        preg_match('/\\A[A-Za-z0-9_]+\\z/', $target) === 1
            && preg_match('/\\A[A-Za-z0-9_]+\\z/', $primary) === 1
            && strpos($target, 'redcms_adriana_28_') === 0
            && !hash_equals($target, $primary),
        'Pagewise migration target is not a guarded Adriana disposable clone.'
    );
    return [$target, $primary];
}

$connection = null;
try {
    foreach ($argv as $index => $argument) {
        if ($index > 0 && $argument !== '--package-only') {
            throw new InvalidArgumentException('Unsupported argument: ' . $argument);
        }
    }
    $package = red_adriana_pagewise_package($projectRoot);
    if (in_array('--package-only', $argv, true)) {
        red_adriana_pagewise_output([
            'ok' => true,
            'operation' => 'validate-adriana-pagewise-site-package',
            'summary' => $package['summary'],
            'mappingSha256' => $package['mappingSha256'],
        ]);
        return;
    }

    [$targetDatabase, $primaryDatabase] = red_adriana_pagewise_database_guard();
    $expected = red_adriana_pagewise_expected_rows($package);
    $connection = red_theme_activation_open_connection($projectRoot);
    red_adriana_pagewise_assert($connection instanceof mysqli, 'Could not connect to the guarded disposable database.');
    red_adriana_pagewise_assert(
        hash_equals($targetDatabase, (string) red_adriana_pagewise_scalar($connection, 'SELECT DATABASE()')),
        'Connected database does not match RED_DB_NAME.'
    );

    $managedCount = (int) red_adriana_pagewise_scalar(
        $connection,
        'SELECT COUNT(*) FROM RED_Articles WHERE EditedBy=?',
        's',
        [RED_ADRIANA_PAGEWISE_EDITOR]
    );
    if ($managedCount > 0) {
        $status = 'unchanged';
        $currentContact = red_adriana_pagewise_fetch_contact_form($connection);
        red_adriana_pagewise_assert_contact_form_row($currentContact);
        if (!hash_equals((string) $expected['contactFormDefinition'], (string) $currentContact['LongDesc'])) {
            $predecessorExpected = $expected;
            $predecessorExpected['contactFormDefinition'] = (string) $currentContact['LongDesc'];
            red_adriana_pagewise_verify_final($connection, $package, $predecessorExpected);

            $upgradeError = '';
            $upgraded = red_admin_theme_contract_write_transaction(
                $connection,
                function () use ($connection, $package, $expected, &$upgradeError) {
                    try {
                        red_adriana_pagewise_update_contact_definition(
                            $connection,
                            $expected['contactFormDefinition'],
                            true
                        );
                        red_adriana_pagewise_verify_final($connection, $package, $expected);
                        return true;
                    } catch (Throwable $exception) {
                        $upgradeError = $exception->getMessage();
                        return false;
                    }
                },
                ['RED_C_Form']
            );
            red_adriana_pagewise_assert(
                $upgraded,
                $upgradeError !== '' ? $upgradeError : 'The Contact definition upgrade failed.'
            );
            $status = 'contact-form-upgraded';
        }
        $verification = red_adriana_pagewise_verify_final($connection, $package, $expected);
        red_adriana_pagewise_output([
            'ok' => true,
            'operation' => 'migrate-adriana-pagewise-site-content',
            'status' => $status,
            'targetDatabase' => $targetDatabase,
            'primaryDatabaseGuard' => $primaryDatabase,
            'mappingSha256' => $package['mappingSha256'],
            'verification' => $verification,
        ]);
        return;
    }
    red_adriana_pagewise_verify_initial($connection, $package, $expected);

    $transactionError = '';
    $committed = red_admin_theme_contract_write_transaction(
        $connection,
        function () use ($connection, $package, $expected, &$transactionError) {
            try {
                red_adriana_pagewise_verify_initial($connection, $package, $expected);
                foreach ($package['routes'] as $prepared) {
                    $index = (int) $prepared['index'];
                    $definition = $prepared['definition'];
                    $prototypeId = RED_ADRIANA_PAGEWISE_ARTICLE_BASE_ID + $index;
                    if ((string) $definition['kind'] === 'article') {
                        red_adriana_pagewise_assert(
                            red_admin_article_update_unlocked($connection, $prototypeId, $expected['owners'][$prototypeId]),
                            'Could not convert Article route owner ' . $prototypeId . '.'
                        );
                    } else {
                        red_adriana_pagewise_assert(
                            red_adriana_pagewise_execute(
                                $connection,
                                'DELETE FROM RED_Articles WHERE RecordID=?',
                                'i',
                                [$prototypeId]
                            ) === 1,
                            'Could not remove area prototype owner ' . $prototypeId . '.'
                        );
                        if ((string) $definition['kind'] === 'section') {
                            $sectionId = (int) $definition['sectionRecordId'];
                            red_adriana_pagewise_assert(
                                red_adriana_pagewise_insert_section($connection, $expected['sections'][$sectionId]),
                                'Could not insert canonical Section ' . $sectionId . '.'
                            );
                        }
                    }
                }
                foreach ($expected['children'] as $recordId => $row) {
                    red_adriana_pagewise_assert(
                        red_admin_article_insert_unlocked($connection, $recordId, $row),
                        'Could not insert editable Other source section ' . $recordId . '.'
                    );
                }
                red_adriana_pagewise_assert(
                    red_admin_article_update_unlocked(
                        $connection,
                        RED_ADRIANA_PAGEWISE_CONTACT_ARTICLE_ID,
                        $expected['contact']
                    ),
                    'Could not finalize the native Contact Form placement.'
                );
                red_adriana_pagewise_update_contact_definition(
                    $connection,
                    $expected['contactFormDefinition'],
                    false
                );
                red_adriana_pagewise_assert(
                    red_adriana_pagewise_execute(
                        $connection,
                        "UPDATE RED_Advanced SET Content=? WHERE Item='Website_Footer' AND Language='sp' AND Content=?",
                        'ss',
                        [$package['footerCanonical'], $package['footerSource']]
                    ) === 1,
                    'Could not install the canonical footer links.'
                );
                red_adriana_pagewise_verify_final($connection, $package, $expected);
                return true;
            } catch (Throwable $exception) {
                $transactionError = $exception->getMessage();
                return false;
            }
        },
        ['RED_Advanced', 'RED_Articles', 'RED_C_Form', 'RED_Menu', 'RED_Sections']
    );
    red_adriana_pagewise_assert(
        $committed,
        $transactionError !== '' ? $transactionError : 'The site pagewise transaction failed.'
    );

    $verification = red_adriana_pagewise_verify_final($connection, $package, $expected);
    red_adriana_pagewise_output([
        'ok' => true,
        'operation' => 'migrate-adriana-pagewise-site-content',
        'status' => 'applied',
        'targetDatabase' => $targetDatabase,
        'primaryDatabaseGuard' => $primaryDatabase,
        'mappingSha256' => $package['mappingSha256'],
        'verification' => $verification,
    ]);
} catch (Throwable $exception) {
    red_adriana_pagewise_output([
        'ok' => false,
        'operation' => 'migrate-adriana-pagewise-site-content',
        'error' => $exception->getMessage(),
    ], STDERR);
    exit(1);
} finally {
    if ($connection instanceof mysqli) {
        mysqli_close($connection);
    }
}
