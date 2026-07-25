<?php
/**
 * Fixed read-only selected Instructions Article provider for the isolated starter.
 *
 * Three fixed SELECT reads reconstruct only Article 89196971 with its parent
 * Section, the two root navigation rows, and two bounded text settings. The
 * current rich body is validated against an exact canary, reduced to a strict
 * HTML/attribute policy, and its 21 confined local images are embedded for an
 * offline preview. The provider accepts no caller route, record, query, HTML,
 * path, media, setting, or mode and never participates in live rendering.
 */

require_once __DIR__ . '/theme_preview_administration_helpers.php';

if (!function_exists('red_theme_instructions_preview_canary')) {
    function red_theme_instructions_preview_canary()
    {
        return [
            'articleRecordId' => 89196971,
            'articleTitle' => 'Instructions',
            'articleAlias' => 'instructions',
            'component' => 'Article',
            'sectionRecordId' => 25,
            'section' => 'administracion',
            'sectionTitle' => 'administracion',
            'legacyLanguage' => 'sp',
            'documentLanguage' => 'es',
            'route' => '/administracion/instructions',
            'sectionRoute' => '/administracion/',
            'layout' => 'index-2',
            'sectionLayout' => 'index-3',
            'queryLimit' => 100,
            'sectionPosition' => 2,
            'sectionPositionOrder' => 1,
            'pagePosition' => 1,
            'pagePositionOrder' => 0,
            'startDate' => '1970-01-01 00:00:00',
            'expiryDate' => '9999-12-31 23:59:59',
            'summaryBytes' => 958,
            'summarySha256' => 'bfbc0f2f4d53a5d5564ec10eea0753cd6019a86cea71271b18cb25f4ad246114',
            'bodyBytes' => 18907,
            'bodySha256' => '8dc4cd54cf74d74f9d0d41be81acba2921d70044043e0f5d6520e443f61f66ad',
            'mediaCount' => 21,
            'mediaBytes' => 1264187,
            'mediaManifestSha256' => 'b692747db87cfc99da551e0f7b3bd82d828eaf032facb67b1820ace3becdace4',
            'mediaFiles' => [
                'image005.png',
                'image010.png',
                'image012.png',
                'image015.png',
                'image016.png',
                'image017.jpg',
                'image018.png',
                'image019.png',
                'image021.png',
                'image023.jpg',
                'image025.png',
                'image027.png',
                'image029.png',
                'image031.png',
                'image033.png',
                'image034.png',
                'image038.png',
                'image041.jpg',
                'image042.jpg',
                'image043.jpg',
                'image044.jpg',
            ],
        ];
    }
}

if (!function_exists('red_theme_instructions_preview_query_inventory')) {
    function red_theme_instructions_preview_query_inventory()
    {
        return [
            'instructions-article-section' =>
                "SELECT a.RecordID AS ArticleRecordID, a.Title AS ArticleTitle, a.Alias AS ArticleAlias,\n" .
                "a.Component, a.Sections AS ArticleSection, a.Categories, a.SubCategories,\n" .
                "a.Layout AS ArticleLayout, a.SectionPosition, a.SectionPositionOrder,\n" .
                "a.PagePosition, a.PagePositionOrder, a.ShortDesc, a.LongDesc, a.Link, a.NewWindow,\n" .
                "a.Language AS ArticleLanguage, a.Active AS ArticleActive, a.StartDate, a.ExpDate,\n" .
                "s.RecordID AS SectionRecordID, s.Sections AS SectionAlias, s.Title AS SectionTitle,\n" .
                "s.Layout AS SectionLayout, s.QueryLimit, s.Description AS SectionDescription,\n" .
                "s.Tags AS SectionTags, s.Features AS SectionFeatures,\n" .
                "s.Language AS SectionLanguage, s.Active AS SectionActive\n" .
                "FROM RED_Articles AS a\n" .
                "INNER JOIN RED_Sections AS s ON s.RecordID=25 AND s.Sections=a.Sections AND s.Language=a.Language\n" .
                "WHERE a.RecordID=89196971 AND a.Alias='instructions' AND a.Language='sp' AND a.Active='Y'\n" .
                'ORDER BY a.RecordID ASC LIMIT 2',
            'instructions-navigation' =>
                "SELECT RecordID, RootOrder, Label, Link, NewWindow, MenuOrder, Active, Language\n" .
                "FROM RED_Menu\n" .
                "WHERE RootOrder='1' AND Parent=0 AND Language='sp' AND Active='Y'\n" .
                'ORDER BY MenuOrder ASC, RecordID ASC LIMIT 20',
            'instructions-settings' =>
                "SELECT RecordID, Item, Content, Language\n" .
                "FROM RED_Advanced\n" .
                "WHERE Language='sp' AND Item IN ('Website_Footer', 'Website_Title')\n" .
                'ORDER BY Item ASC, RecordID ASC LIMIT 3',
        ];
    }
}

if (!function_exists('red_theme_instructions_preview_normalize_query')) {
    function red_theme_instructions_preview_normalize_query($sql)
    {
        return preg_replace('/\s+/', ' ', trim((string) $sql));
    }
}

if (!function_exists('red_theme_instructions_preview_assert_query_inventory')) {
    function red_theme_instructions_preview_assert_query_inventory(array $queries)
    {
        $expected = red_theme_instructions_preview_query_inventory();
        if (array_keys($queries) !== array_keys($expected)) {
            throw new RuntimeException(
                'Instructions preview query inventory must contain exactly three fixed reads.'
            );
        }
        $allowedTables = [
            'RED_Articles' => true,
            'RED_Sections' => true,
            'RED_Menu' => true,
            'RED_Advanced' => true,
        ];
        foreach ($queries as $id => $sql) {
            if (!is_string($sql)
                || preg_match('/\ASELECT\s/i', ltrim($sql)) !== 1
                || strpos($sql, ';') !== false
                || preg_match(
                    '/\b(?:ALTER|CALL|CREATE|DELETE|DROP|GRANT|INSERT|LOAD|LOCK|RENAME|REPLACE|REVOKE|TRUNCATE|UPDATE)\b/i',
                    $sql
                ) === 1
                || preg_match('/(?:--|#|\/\*)/', $sql) === 1
            ) {
                throw new RuntimeException(
                    'Instructions preview query "' . $id . '" is not a single fixed SELECT.'
                );
            }
            if (red_theme_instructions_preview_normalize_query($sql) !==
                red_theme_instructions_preview_normalize_query($expected[$id])
            ) {
                throw new RuntimeException(
                    'Instructions preview query "' . $id . '" does not match the fixed inventory.'
                );
            }
            if (preg_match_all('/\b(?:FROM|JOIN)\s+([A-Za-z0-9_]+)/i', $sql, $matches) < 1) {
                throw new RuntimeException(
                    'Instructions preview query "' . $id . '" has no declared source table.'
                );
            }
            foreach ($matches[1] as $table) {
                if (!isset($allowedTables[$table])) {
                    throw new RuntimeException(
                        'Instructions preview query "' . $id . '" uses an unexpected table.'
                    );
                }
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_instructions_preview_scope')) {
    function red_theme_instructions_preview_scope($databaseReads)
    {
        return red_theme_preview_scope([
            'databaseReads' => $databaseReads,
            'databaseWrites' => 0,
            'sessionReads' => 0,
            'sessionWrites' => 0,
            'liveRuntimeChanges' => 0,
        ]);
    }
}

if (!function_exists('red_theme_instructions_preview_read_rows')) {
    function red_theme_instructions_preview_read_rows($connection)
    {
        if (!is_object($connection)
            || !method_exists($connection, 'query')
            || !method_exists($connection, 'commit')
            || !method_exists($connection, 'rollback')
        ) {
            throw new InvalidArgumentException('Instructions preview requires a valid mysqli connection.');
        }
        $queries = red_theme_instructions_preview_query_inventory();
        red_theme_instructions_preview_assert_query_inventory($queries);
        $started = false;
        try {
            if ($connection->query('START TRANSACTION READ ONLY') !== true) {
                throw new RuntimeException('Instructions preview could not start a read-only transaction.');
            }
            $started = true;
            $rows = [];
            foreach ($queries as $id => $sql) {
                $result = $connection->query($sql);
                if (!is_object($result) || !method_exists($result, 'fetch_assoc')) {
                    throw new RuntimeException('Instructions preview fixed read "' . $id . '" failed.');
                }
                $resultRows = [];
                while ($row = $result->fetch_assoc()) {
                    if (!is_array($row)) {
                        throw new RuntimeException('Instructions preview received an invalid database row.');
                    }
                    $resultRows[] = $row;
                    if (count($resultRows) > 20) {
                        throw new RuntimeException(
                            'Instructions preview query exceeded its fixed row boundary.'
                        );
                    }
                }
                if (method_exists($result, 'free')) {
                    $result->free();
                }
                $rows[$id] = $resultRows;
            }
            if (!$connection->commit()) {
                throw new RuntimeException('Instructions preview could not close its read-only transaction.');
            }
            $started = false;
        } catch (Throwable $exception) {
            if ($started) {
                $connection->rollback();
            }
            throw $exception;
        }

        return [
            'rows' => [
                'articleSection' => $rows['instructions-article-section'],
                'navigation' => $rows['instructions-navigation'],
                'settings' => $rows['instructions-settings'],
            ],
            'scope' => red_theme_instructions_preview_scope(count($queries)),
        ];
    }
}

if (!function_exists('red_theme_instructions_preview_body_string')) {
    function red_theme_instructions_preview_body_string($value, $context, $maximumLength = 25000)
    {
        if (!is_string($value)
            || $value === ''
            || strlen($value) > $maximumLength
            || strpos($value, "\0") !== false
            || strpos($value, '<?') !== false
            || preg_match('//u', $value) !== 1
        ) {
            throw new InvalidArgumentException($context . ' must be bounded valid UTF-8 HTML text.');
        }

        return $value;
    }
}

if (!function_exists('red_theme_instructions_preview_dom_fragment')) {
    function red_theme_instructions_preview_dom_fragment($html)
    {
        if (!class_exists('DOMDocument')) {
            throw new RuntimeException('Instructions preview requires the DOM extension.');
        }
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<html><body><div id="red-theme-instructions-source-root">' . $html . '</div></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $root = $loaded ? $document->getElementById('red-theme-instructions-source-root') : null;
        if (!$root instanceof DOMElement) {
            throw new InvalidArgumentException('Instructions rich body could not be parsed safely.');
        }

        return [$document, $root];
    }
}

if (!function_exists('red_theme_instructions_preview_sanitize_body')) {
    function red_theme_instructions_preview_sanitize_body($body, $projectRoot)
    {
        $canary = red_theme_instructions_preview_canary();
        $body = red_theme_instructions_preview_body_string($body, 'Instructions rich body');
        if (preg_match(
            '/<(?:script|style|iframe|object|embed|form|input|button|link|meta|base)\b|\son[a-z]+\s*=|javascript\s*:|data\s*:|\b(?:src|href)\s*=\s*["\'](?:https?:)?\/\//i',
            $body
        ) === 1) {
            throw new InvalidArgumentException(
                'Instructions rich body contains executable or external-resource markup.'
            );
        }
        if (strlen($body) !== $canary['bodyBytes']
            || hash('sha256', $body) !== $canary['bodySha256']
        ) {
            throw new InvalidArgumentException(
                'Instructions rich body does not match the fixed reviewed content canary.'
            );
        }
        [$document, $root] = red_theme_instructions_preview_dom_fragment($body);
        $allowedTags = array_fill_keys(
            ['h1', 'h2', 'h3', 'h4', 'p', 'ul', 'ol', 'li', 'a', 'hr', 'strong', 'img', 'br', 'em', 'blockquote'],
            true
        );
        $headingMap = ['h1' => 'h3', 'h2' => 'h4', 'h3' => 'h5', 'h4' => 'h6'];
        $allowedAttributes = [
            'h1' => ['id'],
            'h2' => ['id'],
            'h3' => ['id'],
            'h4' => ['id'],
            'p' => ['id'],
            'ul' => ['id'],
            'ol' => ['id', 'type'],
            'li' => ['id'],
            'a' => ['href', 'id', 'name'],
            'hr' => ['id'],
            'strong' => ['id'],
            'img' => ['src', 'alt', 'width', 'height', 'border', 'name'],
            'br' => ['id'],
            'em' => ['id'],
            'blockquote' => ['id'],
        ];
        $targetPattern = '/\A[A-Za-z0-9][A-Za-z0-9_-]{0,79}\z/';
        $elements = [];
        foreach ($root->getElementsByTagName('*') as $element) {
            $elements[] = $element;
        }
        $sourceIds = [];
        foreach ($elements as $element) {
            if ($element->hasAttribute('id')) {
                $id = $element->getAttribute('id');
                if (preg_match($targetPattern, $id) !== 1) {
                    throw new InvalidArgumentException('Instructions rich body contains an unsafe id.');
                }
                $sourceIds[$id] = true;
            }
        }
        $targets = [];
        $hrefTargets = [];
        $mediaFiles = [];
        $mediaNames = [];
        $manifest = [];
        $totalBytes = 0;
        $duplicateTargetsRemoved = 0;
        $dimensionCorrections = 0;
        $rootPath = realpath((string) $projectRoot);
        $mediaRoot = $rootPath === false
            ? false
            : realpath($rootPath . '/admin/images/red-cms-instructions-manual_files');
        if ($rootPath === false
            || $mediaRoot === false
            || !is_dir($mediaRoot)
            || strpos($mediaRoot, $rootPath . DIRECTORY_SEPARATOR) !== 0
        ) {
            throw new RuntimeException('Instructions fixed local media root is unavailable or unsafe.');
        }

        foreach ($elements as $element) {
            $tag = strtolower($element->tagName);
            if (!isset($allowedTags[$tag])) {
                throw new InvalidArgumentException(
                    'Instructions rich body contains unsupported element ' . $tag . '.'
                );
            }
            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->name);
                if (!in_array($name, $allowedAttributes[$tag], true)) {
                    throw new InvalidArgumentException(
                        'Instructions rich body contains unsupported ' . $tag . ' attribute ' . $name . '.'
                    );
                }
            }
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMText
                    && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $child->nodeValue) === 1
                ) {
                    throw new InvalidArgumentException(
                        'Instructions rich body contains unsafe control text.'
                    );
                }
            }
            if ($element->hasAttribute('id')) {
                $id = $element->getAttribute('id');
                if (isset($targets[$id])) {
                    $element->removeAttribute('id');
                    $duplicateTargetsRemoved++;
                } else {
                    $targets[$id] = true;
                }
            }
            if ($tag === 'a' && $element->hasAttribute('name')) {
                $name = $element->getAttribute('name');
                if (preg_match($targetPattern, $name) !== 1) {
                    throw new InvalidArgumentException(
                        'Instructions rich body contains an unsafe named target.'
                    );
                }
                if (isset($sourceIds[$name]) || isset($targets[$name])) {
                    $element->removeAttribute('name');
                    $duplicateTargetsRemoved++;
                } else {
                    $targets[$name] = true;
                }
            }
            if ($tag === 'a' && $element->hasAttribute('href')) {
                $href = $element->getAttribute('href');
                if (preg_match(
                    '/\A(?:instructions)?#([A-Za-z0-9][A-Za-z0-9_-]{0,79})\z/',
                    $href,
                    $match
                ) !== 1) {
                    throw new InvalidArgumentException(
                        'Instructions rich body links must target the fixed local manual.'
                    );
                }
                $element->setAttribute('href', '#' . $match[1]);
                $hrefTargets[] = $match[1];
            }
            if ($tag === 'ol' && $element->hasAttribute('type') && $element->getAttribute('type') !== 'a') {
                throw new InvalidArgumentException('Instructions ordered-list type is unsupported.');
            }
            if ($tag === 'img') {
                foreach (['src', 'alt', 'width', 'height', 'border'] as $requiredAttribute) {
                    if (!$element->hasAttribute($requiredAttribute)) {
                        throw new InvalidArgumentException(
                            'Instructions image is missing its fixed source metadata.'
                        );
                    }
                }
                if ($element->getAttribute('border') !== '0'
                    || preg_match('/\A[1-9][0-9]{0,4}\z/', $element->getAttribute('width')) !== 1
                    || preg_match('/\A[1-9][0-9]{0,4}\z/', $element->getAttribute('height')) !== 1
                ) {
                    throw new InvalidArgumentException(
                        'Instructions image source dimensions or border are outside the fixed contract.'
                    );
                }
                $source = $element->getAttribute('src');
                if (preg_match(
                    '~\A\.\./admin/images/red-cms-instructions-manual_files/' .
                    '([A-Za-z0-9][A-Za-z0-9._-]{0,99})\z~',
                    $source,
                    $match
                ) !== 1) {
                    throw new InvalidArgumentException(
                        'Instructions image source is outside the fixed local manual directory.'
                    );
                }
                $filename = $match[1];
                if (!in_array($filename, $canary['mediaFiles'], true)
                    || in_array($filename, $mediaNames, true)
                ) {
                    throw new InvalidArgumentException(
                        'Instructions image filename is duplicated or outside the fixed allowlist.'
                    );
                }
                $path = realpath($mediaRoot . '/' . $filename);
                if ($path === false
                    || !is_file($path)
                    || strpos($path, $mediaRoot . DIRECTORY_SEPARATOR) !== 0
                ) {
                    throw new InvalidArgumentException(
                        'Instructions image does not resolve beneath the fixed local media root.'
                    );
                }
                $bytes = file_get_contents($path);
                $image = $bytes === false ? false : @getimagesizefromstring($bytes);
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $expectedMime = $extension === 'png' ? 'image/png' : 'image/jpeg';
                if ($bytes === false
                    || $bytes === ''
                    || strlen($bytes) > 1000000
                    || !is_array($image)
                    || ($image['mime'] ?? '') !== $expectedMime
                ) {
                    throw new InvalidArgumentException(
                        'Instructions image type, size, or dimensions are invalid.'
                    );
                }
                $hash = hash('sha256', $bytes);
                $publicPath = '/admin/images/red-cms-instructions-manual_files/' . $filename;
                $mediaNames[] = $filename;
                $totalBytes += strlen($bytes);
                $manifest[] = $publicPath . ':' . $hash;
                $mediaFiles[] = [
                    'publicPath' => $publicPath,
                    'bytes' => strlen($bytes),
                    'sha256' => $hash,
                    'width' => (int) ($image[0] ?? 0),
                    'height' => (int) ($image[1] ?? 0),
                    'mime' => (string) ($image['mime'] ?? ''),
                ];
                if ((int) $element->getAttribute('width') !== (int) ($image[0] ?? 0)
                    || (int) $element->getAttribute('height') !== (int) ($image[1] ?? 0)
                ) {
                    $dimensionCorrections++;
                }
                while ($element->attributes->length > 0) {
                    $element->removeAttributeNode($element->attributes->item(0));
                }
                $element->setAttribute(
                    'src',
                    'data:' . $expectedMime . ';base64,' . base64_encode($bytes)
                );
                $element->setAttribute('alt', '');
                $element->setAttribute('width', (string) ($image[0] ?? 0));
                $element->setAttribute('height', (string) ($image[1] ?? 0));
                $element->setAttribute('loading', 'lazy');
                $element->setAttribute('decoding', 'async');
                $element->setAttribute(
                    'style',
                    'display:block;max-width:100%;height:auto;margin:1rem auto;'
                );
            }
            if (isset($headingMap[$tag])) {
                $replacement = $document->createElement($headingMap[$tag]);
                foreach ($element->attributes as $attribute) {
                    $replacement->setAttribute($attribute->name, $attribute->value);
                }
                while ($element->firstChild !== null) {
                    $replacement->appendChild($element->firstChild);
                }
                $element->parentNode->replaceChild($replacement, $element);
            }
        }
        foreach ($hrefTargets as $target) {
            if (!isset($targets[$target])) {
                throw new InvalidArgumentException(
                    'Instructions rich body contains a link to a missing local target.'
                );
            }
        }
        if ($mediaNames !== $canary['mediaFiles']) {
            throw new InvalidArgumentException(
                'Instructions media order does not match the fixed reviewed inventory.'
            );
        }
        sort($manifest, SORT_STRING);
        $manifestSha256 = hash('sha256', implode("\n", $manifest));
        if (count($mediaFiles) !== $canary['mediaCount']
            || $totalBytes !== $canary['mediaBytes']
            || $manifestSha256 !== $canary['mediaManifestSha256']
        ) {
            throw new InvalidArgumentException(
                'Instructions local media facts do not match the fixed reviewed manifest.'
            );
        }
        $sanitized = '';
        foreach ($root->childNodes as $child) {
            $sanitized .= $document->saveHTML($child);
        }
        $sanitized = red_theme_preview_trusted_article_html($sanitized);

        return [
            'html' => $sanitized,
            'htmlBytes' => strlen($sanitized),
            'htmlSha256' => hash('sha256', $sanitized),
            'linkCount' => count($hrefTargets),
            'mediaCount' => count($mediaFiles),
            'mediaBytes' => $totalBytes,
            'mediaManifestSha256' => $manifestSha256,
            'dimensionCorrections' => $dimensionCorrections,
            'duplicateTargetsRemoved' => $duplicateTargetsRemoved,
            'externalResources' => 0,
        ];
    }
}

if (!function_exists('red_theme_instructions_preview_article')) {
    function red_theme_instructions_preview_article($rows, $projectRoot)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1) {
            throw new InvalidArgumentException(
                'Instructions preview requires exactly one joined Article and Section row.'
            );
        }
        $row = red_theme_administration_preview_require_row_keys(
            $rows[0],
            [
                'ArticleRecordID', 'ArticleTitle', 'ArticleAlias', 'Component', 'ArticleSection',
                'Categories', 'SubCategories', 'ArticleLayout', 'SectionPosition',
                'SectionPositionOrder', 'PagePosition', 'PagePositionOrder', 'ShortDesc',
                'LongDesc', 'Link', 'NewWindow', 'ArticleLanguage', 'ArticleActive',
                'StartDate', 'ExpDate', 'SectionRecordID', 'SectionAlias', 'SectionTitle',
                'SectionLayout', 'QueryLimit', 'SectionDescription', 'SectionTags',
                'SectionFeatures', 'SectionLanguage', 'SectionActive',
            ],
            'Instructions joined Article/Section row'
        );
        $canary = red_theme_instructions_preview_canary();
        $articleRecordId = red_theme_administration_preview_integer(
            $row['ArticleRecordID'],
            'Instructions Article RecordID',
            1
        );
        $sectionRecordId = red_theme_administration_preview_integer(
            $row['SectionRecordID'],
            'Instructions Section RecordID',
            1
        );
        $articleTitle = red_theme_administration_preview_plain_text(
            $row['ArticleTitle'],
            'Instructions Article title',
            false,
            180
        );
        $articleAlias = red_theme_administration_preview_source_string(
            $row['ArticleAlias'],
            'Instructions Article alias',
            false,
            100
        );
        $component = red_theme_administration_preview_source_string(
            $row['Component'],
            'Instructions Article component',
            false,
            50
        );
        $articleSection = red_theme_administration_preview_source_string(
            $row['ArticleSection'],
            'Instructions Article section',
            false,
            100
        );
        $articleLayout = red_theme_administration_preview_source_string(
            $row['ArticleLayout'],
            'Instructions Article layout',
            false,
            64
        );
        $articleLanguage = red_theme_administration_preview_source_string(
            $row['ArticleLanguage'],
            'Instructions Article language',
            false,
            2
        );
        $articleActive = red_theme_administration_preview_source_string(
            $row['ArticleActive'],
            'Instructions Article active state',
            false,
            1
        );
        $sectionAlias = red_theme_administration_preview_source_string(
            $row['SectionAlias'],
            'Instructions Section alias',
            false,
            100
        );
        $sectionTitle = red_theme_administration_preview_plain_text(
            $row['SectionTitle'],
            'Instructions Section title',
            false,
            120
        );
        $sectionLayout = red_theme_administration_preview_source_string(
            $row['SectionLayout'],
            'Instructions Section layout',
            false,
            64
        );
        $sectionLanguage = red_theme_administration_preview_source_string(
            $row['SectionLanguage'],
            'Instructions Section language',
            false,
            2
        );
        $sectionActive = red_theme_administration_preview_source_string(
            $row['SectionActive'],
            'Instructions Section active state',
            false,
            1
        );
        $summary = red_theme_instructions_preview_body_string(
            $row['ShortDesc'],
            'Instructions listing summary',
            2000
        );
        $body = red_theme_instructions_preview_body_string(
            $row['LongDesc'],
            'Instructions rich body'
        );
        if ($articleRecordId !== $canary['articleRecordId']
            || $articleTitle !== $canary['articleTitle']
            || $articleAlias !== $canary['articleAlias']
            || $component !== $canary['component']
            || $articleSection !== $canary['section']
            || red_theme_administration_preview_source_string(
                $row['Categories'],
                'Instructions category alias',
                true,
                100
            ) !== ''
            || red_theme_administration_preview_source_string(
                $row['SubCategories'],
                'Instructions subcategory alias',
                true,
                100
            ) !== ''
            || $articleLayout !== $canary['layout']
            || red_theme_administration_preview_integer(
                $row['SectionPosition'],
                'Instructions section position',
                0,
                20
            ) !== $canary['sectionPosition']
            || red_theme_administration_preview_integer(
                $row['SectionPositionOrder'],
                'Instructions section order',
                0,
                1000
            ) !== $canary['sectionPositionOrder']
            || red_theme_administration_preview_integer(
                $row['PagePosition'],
                'Instructions page position',
                0,
                20
            ) !== $canary['pagePosition']
            || red_theme_administration_preview_integer(
                $row['PagePositionOrder'],
                'Instructions page order',
                0,
                1000
            ) !== $canary['pagePositionOrder']
            || strlen($summary) !== $canary['summaryBytes']
            || hash('sha256', $summary) !== $canary['summarySha256']
            || strlen($body) !== $canary['bodyBytes']
            || hash('sha256', $body) !== $canary['bodySha256']
            || red_theme_administration_preview_source_string(
                $row['Link'],
                'Instructions Article link',
                true,
                500
            ) !== ''
            || red_theme_administration_preview_source_string(
                $row['NewWindow'],
                'Instructions Article target',
                true,
                10
            ) !== ''
            || $articleLanguage !== $canary['legacyLanguage']
            || $articleActive !== 'Y'
            || (string) $row['StartDate'] !== $canary['startDate']
            || (string) $row['ExpDate'] !== $canary['expiryDate']
            || $sectionRecordId !== $canary['sectionRecordId']
            || $sectionAlias !== $canary['section']
            || $sectionTitle !== $canary['sectionTitle']
            || $sectionLayout !== $canary['sectionLayout']
            || red_theme_administration_preview_integer(
                $row['QueryLimit'],
                'Instructions Section query limit',
                1,
                1000
            ) !== $canary['queryLimit']
            || red_theme_administration_preview_source_string(
                $row['SectionDescription'],
                'Instructions Section description',
                true,
                500
            ) !== ''
            || red_theme_administration_preview_source_string(
                $row['SectionTags'],
                'Instructions Section tags',
                true,
                500
            ) !== ''
            || red_theme_administration_preview_source_string(
                $row['SectionFeatures'],
                'Instructions Section features',
                true,
                500
            ) !== ''
            || $sectionLanguage !== $canary['legacyLanguage']
            || $sectionActive !== 'Y'
        ) {
            throw new InvalidArgumentException(
                'Instructions joined Article/Section row does not match the fixed selected-route canary.'
            );
        }
        $sanitized = red_theme_instructions_preview_sanitize_body($body, $projectRoot);

        return [
            'articleRecordId' => $articleRecordId,
            'articleTitle' => $articleTitle,
            'articleAlias' => $articleAlias,
            'sectionRecordId' => $sectionRecordId,
            'sectionAlias' => $sectionAlias,
            'sectionTitle' => $sectionTitle,
            'articleLayout' => $articleLayout,
            'sectionLayout' => $sectionLayout,
            'summary' => red_theme_administration_preview_plain_text(
                $summary,
                'Instructions listing summary',
                false,
                1000
            ),
            'body' => $sanitized,
        ];
    }
}

if (!function_exists('red_theme_instructions_preview_prepare_rows')) {
    function red_theme_instructions_preview_prepare_rows(array $rows, $projectRoot = null)
    {
        red_theme_preview_require_exact_keys(
            $rows,
            ['articleSection', 'navigation', 'settings'],
            [],
            'Instructions preview rows'
        );
        $projectRoot = $projectRoot === null ? dirname(__DIR__) : $projectRoot;
        $article = red_theme_instructions_preview_article($rows['articleSection'], $projectRoot);
        $navigation = red_theme_administration_preview_navigation($rows['navigation']);
        $settings = red_theme_administration_preview_settings($rows['settings']);
        $canary = red_theme_instructions_preview_canary();
        $siteTitle = trim((string) ($settings['Website_Title'] ?? ''));
        $siteTitleSource = 'advanced.Website_Title';
        if ($siteTitle === '') {
            $siteTitle = $article['sectionTitle'];
            $siteTitleSource = 'section.Title';
        }
        $footer = trim((string) ($settings['Website_Footer'] ?? ''));
        $footerSource = 'advanced.Website_Footer';
        if ($footer === '') {
            $footer = $article['sectionTitle'];
            $footerSource = 'section.Title';
        }
        $fixture = [
            'schemaVersion' => 1,
            'theme' => 'starter-reference',
            'document' => [
                'language' => $canary['documentLanguage'],
                'title' => $article['articleTitle'] . ' — ' . $article['sectionTitle'],
                'description' => 'Read-only selected Instructions Article preview.',
            ],
            'regions' => [
                'header' => [
                    'siteTitle' => $siteTitle,
                    'homeUrl' => '/',
                ],
                'navigation' => [
                    'items' => array_map(static function (array $item) {
                        return [
                            'label' => $item['label'],
                            'url' => $item['url'],
                            'current' => false,
                        ];
                    }, $navigation),
                ],
                'hero' => [
                    'title' => $article['articleTitle'],
                    'summary' => $article['summary'],
                    'action' => [
                        'label' => 'Back to Administration',
                        'url' => $canary['sectionRoute'],
                    ],
                ],
                'footer' => [
                    'copyright' => $footer,
                ],
            ],
            'page' => [
                'layout' => $article['articleLayout'],
                'breadcrumb' => [
                    ['label' => $navigation[0]['label'], 'url' => $navigation[0]['url']],
                    ['label' => $article['sectionTitle'], 'url' => $canary['sectionRoute']],
                    ['label' => $article['articleTitle'], 'url' => ''],
                ],
                'slots' => [
                    '1' => [[
                        'component' => 'Article',
                        'data' => [
                            'title' => $article['articleTitle'],
                            'bodyHtml' => $article['body']['html'],
                        ],
                    ]],
                    '2' => [],
                    '3' => [],
                    '4' => [],
                ],
            ],
        ];
        $source = [
            'mode' => 'read-only-instructions-preview',
            'canary' => [
                'articleRecordId' => $article['articleRecordId'],
                'articleAlias' => $article['articleAlias'],
                'sectionRecordId' => $article['sectionRecordId'],
                'section' => $article['sectionAlias'],
                'legacyLanguage' => $canary['legacyLanguage'],
                'route' => $canary['route'],
                'layout' => $article['articleLayout'],
                'pagePosition' => $canary['pagePosition'],
                'pagePositionOrder' => $canary['pagePositionOrder'],
            ],
            'content' => [
                'sourceBytes' => $canary['bodyBytes'],
                'sourceSha256' => $canary['bodySha256'],
                'sanitizedBytes' => $article['body']['htmlBytes'],
                'sanitizedSha256' => $article['body']['htmlSha256'],
                'localLinkCount' => $article['body']['linkCount'],
                'duplicateTargetsRemoved' => $article['body']['duplicateTargetsRemoved'],
            ],
            'media' => [
                'count' => $article['body']['mediaCount'],
                'bytes' => $article['body']['mediaBytes'],
                'manifestSha256' => $article['body']['mediaManifestSha256'],
                'embedded' => $article['body']['mediaCount'],
                'dimensionCorrections' => $article['body']['dimensionCorrections'],
                'externalResources' => $article['body']['externalResources'],
            ],
            'queryIds' => array_keys(red_theme_instructions_preview_query_inventory()),
            'rowCounts' => [
                'articleSection' => count($rows['articleSection']),
                'navigation' => count($rows['navigation']),
                'settings' => count($rows['settings']),
            ],
            'fallbacks' => [
                'siteTitle' => $siteTitleSource,
                'footer' => $footerSource,
            ],
        ];
        red_theme_preview_assert_non_executable($fixture, 'Instructions prepared preview input');
        red_theme_preview_assert_non_executable($source, 'Instructions preview source metadata');

        return ['fixture' => $fixture, 'source' => $source];
    }
}

if (!function_exists('red_theme_instructions_preview_render_rows')) {
    function red_theme_instructions_preview_render_rows(
        array $rows,
        $projectRoot = null,
        $databaseReads = 0
    ) {
        if (!in_array($databaseReads, [0, 3], true)) {
            throw new InvalidArgumentException(
                'Instructions preview database-read count must be zero or three.'
            );
        }
        $validation = red_theme_preview_validate_reference_theme('starter-reference', $projectRoot);
        $prepared = red_theme_instructions_preview_prepare_rows($rows, $projectRoot);
        $contract = red_theme_preview_contract($prepared['fixture'], $validation);
        $result = red_theme_preview_render_prepared_contract(
            $validation,
            $contract,
            'read-only-instructions-preview',
            red_theme_instructions_preview_scope($databaseReads)
        );
        $result['source'] = $prepared['source'];

        return $result;
    }
}

if (!function_exists('red_theme_instructions_preview_render')) {
    function red_theme_instructions_preview_render($connection, $projectRoot = null)
    {
        $read = red_theme_instructions_preview_read_rows($connection);
        $result = red_theme_instructions_preview_render_rows(
            $read['rows'],
            $projectRoot,
            $read['scope']['databaseReads']
        );
        if ($result['scope'] !== $read['scope']) {
            throw new RuntimeException(
                'Instructions preview side-effect scope changed during rendering.'
            );
        }

        return $result;
    }
}
