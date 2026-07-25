<?php
/**
 * Read-only Home-route input provider for the isolated starter preview.
 *
 * The provider owns five fixed SELECT reads and one confined local Gallery
 * media root. It reads no request or session state and never participates in
 * live rendering, theme selection, settings persistence, or activation.
 */

require_once __DIR__ . '/theme_preview_helpers.php';

if (!function_exists('red_theme_home_preview_canary')) {
    function red_theme_home_preview_canary()
    {
        return [
            'sectionRecordId' => 13,
            'section' => 'home',
            'legacyLanguage' => 'sp',
            'documentLanguage' => 'es',
            'route' => '/',
            'layout' => 'index-1',
            'queryLimit' => 100,
            'features' => ['slider'],
            'articleRecordId' => 1154326271,
            'articleAlias' => 'banner-test',
            'component' => 'Gallery',
            'homePosition' => 1,
            'homePositionOrder' => 1,
            'galleryRecordId' => 2030445666,
            'galleryAlias' => 'banner-test',
            'galleryType' => 'Banner',
        ];
    }
}

if (!function_exists('red_theme_home_preview_query_inventory')) {
    function red_theme_home_preview_query_inventory()
    {
        return [
            'home-section' =>
                "SELECT RecordID, Sections, Title, Layout, QueryLimit, Description, Tags, Features, Language, Active\n" .
                "FROM RED_Sections\n" .
                "WHERE RecordID=13 AND Sections='home' AND Language='sp' AND Active='Y'\n" .
                'LIMIT 2',
            'home-navigation' =>
                "SELECT RecordID, RootOrder, Label, Link, NewWindow, MenuOrder, Active, Language\n" .
                "FROM RED_Menu\n" .
                "WHERE RootOrder='1' AND Parent=0 AND Language='sp' AND Active='Y'\n" .
                'ORDER BY MenuOrder ASC, RecordID ASC LIMIT 20',
            'home-hero' =>
                "SELECT RecordID, Title, Alias, Sections, Categories, SubCategories, LongDesc, SliderDesc,\n" .
                "Link, NewWindow, BigPict, ExpDate, HomeFeatures, HomeFeatures_Order\n" .
                "FROM RED_Articles\n" .
                "WHERE Active='Y' AND Language='sp' AND HomeFeatures LIKE '%slider%'\n" .
                "AND (YEAR(ExpDate)=0 OR ExpDate>NOW())\n" .
                'ORDER BY HomeFeatures_Order ASC, RecordID ASC LIMIT 6',
            'home-gallery' =>
                "SELECT a.RecordID AS ArticleRecordID, a.Alias AS ArticleAlias, a.Title AS ArticleTitle,\n" .
                "a.Component, a.Sections, a.HomePosition, a.HomePositionOrder, a.HomeFeature, a.HomeFeatures,\n" .
                "a.StartDate, a.ExpDate, a.Language, a.Active, g.RecordID AS GalleryRecordID, g.RefID,\n" .
                "g.Alias AS GalleryAlias, g.Title AS GalleryTitle, g.GalleryType, g.ShortDesc, g.LongDesc,\n" .
                "g.Link, g.NewWindow\n" .
                "FROM RED_Articles AS a\n" .
                "INNER JOIN RED_C_Gallery AS g ON CAST(g.RefID AS UNSIGNED)=a.RecordID\n" .
                "WHERE a.RecordID=1154326271 AND a.Active='Y' AND a.Language='sp'\n" .
                "AND a.Sections='home' AND a.Component='Gallery' AND a.HomePosition=1\n" .
                "AND a.StartDate<=NOW() AND (YEAR(a.ExpDate)=0 OR a.ExpDate>NOW())\n" .
                'ORDER BY a.HomePositionOrder ASC, a.RecordID ASC, g.RecordID ASC LIMIT 2',
            'home-settings' =>
                "SELECT RecordID, Item, Content, Language\n" .
                "FROM RED_Advanced\n" .
                "WHERE Language='sp' AND Item IN ('Website_Footer', 'Website_Title')\n" .
                'ORDER BY Item ASC, RecordID ASC LIMIT 3',
        ];
    }
}

if (!function_exists('red_theme_home_preview_assert_query_inventory')) {
    function red_theme_home_preview_assert_query_inventory(array $queries)
    {
        if (array_keys($queries) !== [
            'home-section',
            'home-navigation',
            'home-hero',
            'home-gallery',
            'home-settings',
        ]) {
            throw new RuntimeException('Home preview query inventory must contain exactly five fixed reads.');
        }

        $allowedTables = [
            'RED_Sections' => true,
            'RED_Menu' => true,
            'RED_Articles' => true,
            'RED_C_Gallery' => true,
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
                throw new RuntimeException('Home preview query "' . $id . '" is not a single fixed SELECT.');
            }
            if (preg_match_all('/\b(?:FROM|JOIN)\s+([A-Za-z0-9_]+)/i', $sql, $matches) < 1) {
                throw new RuntimeException('Home preview query "' . $id . '" has no declared source table.');
            }
            foreach ($matches[1] as $table) {
                if (!isset($allowedTables[$table])) {
                    throw new RuntimeException('Home preview query "' . $id . '" uses an unexpected table.');
                }
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_home_preview_scope')) {
    function red_theme_home_preview_scope($databaseReads)
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

if (!function_exists('red_theme_home_preview_read_rows')) {
    function red_theme_home_preview_read_rows($connection)
    {
        if (!is_object($connection)
            || !method_exists($connection, 'query')
            || !method_exists($connection, 'commit')
            || !method_exists($connection, 'rollback')
        ) {
            throw new InvalidArgumentException('Home preview requires a valid mysqli connection.');
        }

        $queries = red_theme_home_preview_query_inventory();
        red_theme_home_preview_assert_query_inventory($queries);
        $started = false;
        try {
            if ($connection->query('START TRANSACTION READ ONLY') !== true) {
                throw new RuntimeException('Home preview could not start a read-only transaction.');
            }
            $started = true;
            $rows = [];
            foreach ($queries as $id => $sql) {
                $result = $connection->query($sql);
                if (!is_object($result) || !method_exists($result, 'fetch_assoc')) {
                    throw new RuntimeException('Home preview fixed read "' . $id . '" failed.');
                }
                $resultRows = [];
                while ($row = $result->fetch_assoc()) {
                    if (!is_array($row)) {
                        throw new RuntimeException('Home preview received an invalid database row.');
                    }
                    $resultRows[] = $row;
                    if (count($resultRows) > 20) {
                        throw new RuntimeException('Home preview query exceeded its fixed row boundary.');
                    }
                }
                if (method_exists($result, 'free')) {
                    $result->free();
                }
                $rows[$id] = $resultRows;
            }
            if (!$connection->commit()) {
                throw new RuntimeException('Home preview could not close its read-only transaction.');
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
                'section' => $rows['home-section'],
                'navigation' => $rows['home-navigation'],
                'hero' => $rows['home-hero'],
                'gallery' => $rows['home-gallery'],
                'settings' => $rows['home-settings'],
            ],
            'scope' => red_theme_home_preview_scope(count($queries)),
        ];
    }
}

if (!function_exists('red_theme_home_preview_require_row_keys')) {
    function red_theme_home_preview_require_row_keys($row, array $expectedKeys, $context)
    {
        if (!is_array($row) || array_keys($row) !== $expectedKeys) {
            throw new InvalidArgumentException($context . ' must contain the exact selected columns in order.');
        }

        return $row;
    }
}

if (!function_exists('red_theme_home_preview_source_string')) {
    function red_theme_home_preview_source_string($value, $context, $allowEmpty = false, $maximumLength = 500)
    {
        $value = red_theme_preview_string($value, $context, $allowEmpty, $maximumLength);
        if (strpos($value, '<?') !== false || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException($context . ' contains unsafe or invalid text.');
        }

        return $value;
    }
}

if (!function_exists('red_theme_home_preview_integer')) {
    function red_theme_home_preview_integer($value, $context, $minimum = 0, $maximum = 2147483647)
    {
        if ((is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) === 1)
        ) {
            $integer = (int) $value;
            if ((is_int($value) || (string) $integer === $value)
                && $integer >= $minimum
                && $integer <= $maximum
            ) {
                return $integer;
            }
        }

        throw new InvalidArgumentException($context . ' must be a bounded unsigned integer.');
    }
}

if (!function_exists('red_theme_home_preview_plain_text')) {
    function red_theme_home_preview_plain_text($value, $context, $allowEmpty = false, $maximumLength = 500)
    {
        $source = red_theme_home_preview_source_string(
            $value,
            $context,
            $allowEmpty,
            max($maximumLength * 4, $maximumLength)
        );
        $plain = trim(preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ));
        if ((!$allowEmpty && $plain === '') || strlen($plain) > $maximumLength) {
            throw new InvalidArgumentException($context . ' is empty or exceeds its plain-text boundary.');
        }

        return $plain;
    }
}

if (!function_exists('red_theme_home_preview_local_url')) {
    function red_theme_home_preview_local_url($value, $context, $allowEmpty = false)
    {
        $value = red_theme_home_preview_source_string($value, $context, $allowEmpty, 240);
        if ($value === '' && $allowEmpty) {
            return '';
        }
        if ($value[0] !== '/' || str_starts_with($value, '//')) {
            throw new InvalidArgumentException($context . ' must be a local absolute-path URL.');
        }

        return red_theme_preview_url($value, $context);
    }
}

if (!function_exists('red_theme_home_preview_section')) {
    function red_theme_home_preview_section($rows)
    {
        if (!is_array($rows) || count($rows) !== 1 || !isset($rows[0])) {
            throw new InvalidArgumentException('Home preview requires exactly one fixed section row.');
        }
        $row = red_theme_home_preview_require_row_keys(
            $rows[0],
            ['RecordID', 'Sections', 'Title', 'Layout', 'QueryLimit', 'Description', 'Tags', 'Features', 'Language', 'Active'],
            'Home section row'
        );
        $canary = red_theme_home_preview_canary();
        $features = array_values(array_filter(array_map('trim', explode(',', (string) $row['Features']))));
        $recordId = red_theme_home_preview_integer($row['RecordID'], 'Home section RecordID', 1);
        $section = red_theme_home_preview_source_string($row['Sections'], 'Home section alias', false, 100);
        $layout = red_theme_home_preview_source_string($row['Layout'], 'Home section layout', false, 64);
        $queryLimit = red_theme_home_preview_integer($row['QueryLimit'], 'Home section query limit', 1, 999);
        $language = red_theme_home_preview_source_string($row['Language'], 'Home section language', false, 12);
        $active = red_theme_home_preview_source_string($row['Active'], 'Home section active state', false, 1);
        if ($recordId !== $canary['sectionRecordId']
            || strtolower($section) !== $canary['section']
            || $layout !== $canary['layout']
            || $queryLimit !== $canary['queryLimit']
            || $features !== $canary['features']
            || $language !== $canary['legacyLanguage']
            || $active !== 'Y'
        ) {
            throw new InvalidArgumentException('Home section row does not match the fixed route/layout canary.');
        }

        return [
            'recordId' => $recordId,
            'section' => strtolower($section),
            'title' => red_theme_home_preview_plain_text($row['Title'], 'Home section title', false, 120),
            'layout' => $layout,
            'queryLimit' => $queryLimit,
            'description' => red_theme_home_preview_plain_text(
                $row['Description'],
                'Home section description',
                true,
                500
            ),
            'tags' => red_theme_home_preview_plain_text($row['Tags'], 'Home section tags', true, 300),
            'features' => $features,
            'language' => $language,
            'active' => $active,
        ];
    }
}

if (!function_exists('red_theme_home_preview_navigation')) {
    function red_theme_home_preview_navigation($rows)
    {
        if (!is_array($rows) || count($rows) !== 2 || !array_is_list($rows)) {
            throw new InvalidArgumentException('Home preview requires exactly two active root navigation rows.');
        }
        $expected = [
            1 => ['url' => '/', 'order' => 1, 'current' => true],
            67 => ['url' => '/contacto/', 'order' => 5, 'current' => false],
        ];
        $items = [];
        foreach ($rows as $row) {
            $row = red_theme_home_preview_require_row_keys(
                $row,
                ['RecordID', 'RootOrder', 'Label', 'Link', 'NewWindow', 'MenuOrder', 'Active', 'Language'],
                'Home navigation row'
            );
            $recordId = red_theme_home_preview_integer($row['RecordID'], 'Home navigation RecordID', 1);
            if (!isset($expected[$recordId])) {
                throw new InvalidArgumentException('Home navigation contains an unexpected root record.');
            }
            $rootOrder = red_theme_home_preview_source_string($row['RootOrder'], 'Home navigation root order', false, 2);
            $url = red_theme_home_preview_local_url($row['Link'], 'Home navigation URL');
            $newWindow = red_theme_home_preview_source_string(
                $row['NewWindow'],
                'Home navigation window state',
                true,
                6
            );
            $order = red_theme_home_preview_integer($row['MenuOrder'], 'Home navigation order', 0, 999);
            $active = red_theme_home_preview_source_string($row['Active'], 'Home navigation active state', false, 1);
            $language = red_theme_home_preview_source_string($row['Language'], 'Home navigation language', false, 12);
            if ($rootOrder !== '1'
                || $url !== $expected[$recordId]['url']
                || $order !== $expected[$recordId]['order']
                || !in_array($newWindow, ['', 'N'], true)
                || $active !== 'Y'
                || $language !== 'sp'
            ) {
                throw new InvalidArgumentException('Home navigation row does not match the fixed root-menu canary.');
            }
            $items[] = [
                'recordId' => $recordId,
                'label' => red_theme_home_preview_plain_text($row['Label'], 'Home navigation label', false, 80),
                'url' => $url,
                'current' => $expected[$recordId]['current'],
                'order' => $order,
            ];
        }
        if (array_column($items, 'recordId') !== [1, 67]) {
            throw new InvalidArgumentException('Home navigation rows are not in the fixed menu order.');
        }

        return $items;
    }
}

if (!function_exists('red_theme_home_preview_hero')) {
    function red_theme_home_preview_hero($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 5) {
            throw new InvalidArgumentException('Home preview hero rows exceed the fixed slider boundary.');
        }
        $items = [];
        $lastOrder = -1;
        foreach ($rows as $row) {
            $row = red_theme_home_preview_require_row_keys(
                $row,
                ['RecordID', 'Title', 'Alias', 'Sections', 'Categories', 'SubCategories', 'LongDesc', 'SliderDesc', 'Link', 'NewWindow', 'BigPict', 'ExpDate', 'HomeFeatures', 'HomeFeatures_Order'],
                'Home hero row'
            );
            $features = array_values(array_filter(array_map('trim', explode(',', (string) $row['HomeFeatures']))));
            $order = red_theme_home_preview_integer($row['HomeFeatures_Order'], 'Home hero order', 0, 999);
            if (!in_array('slider', $features, true) || $order < $lastOrder) {
                throw new InvalidArgumentException('Home hero row is outside the fixed slider order.');
            }
            $lastOrder = $order;
            $link = red_theme_home_preview_local_url($row['Link'], 'Home hero link', true);
            $newWindow = red_theme_home_preview_source_string($row['NewWindow'], 'Home hero window state', true, 6);
            if (!in_array($newWindow, ['', 'N'], true)) {
                throw new InvalidArgumentException('Home hero cannot request a new browsing context.');
            }
            $items[] = [
                'recordId' => red_theme_home_preview_integer($row['RecordID'], 'Home hero RecordID', 1),
                'title' => red_theme_home_preview_plain_text($row['Title'], 'Home hero title', false, 180),
                'summary' => red_theme_home_preview_plain_text(
                    $row['SliderDesc'] !== '' ? $row['SliderDesc'] : $row['LongDesc'],
                    'Home hero summary',
                    true,
                    500
                ),
                'link' => $link,
                'order' => $order,
            ];
        }

        return $items;
    }
}

if (!function_exists('red_theme_home_preview_gallery')) {
    function red_theme_home_preview_gallery($rows)
    {
        if (!is_array($rows) || count($rows) !== 1 || !isset($rows[0])) {
            throw new InvalidArgumentException('Home preview requires exactly one fixed Gallery component row.');
        }
        $row = red_theme_home_preview_require_row_keys(
            $rows[0],
            [
                'ArticleRecordID', 'ArticleAlias', 'ArticleTitle', 'Component', 'Sections',
                'HomePosition', 'HomePositionOrder', 'HomeFeature', 'HomeFeatures', 'StartDate',
                'ExpDate', 'Language', 'Active', 'GalleryRecordID', 'RefID', 'GalleryAlias',
                'GalleryTitle', 'GalleryType', 'ShortDesc', 'LongDesc', 'Link', 'NewWindow',
            ],
            'Home Gallery row'
        );
        $canary = red_theme_home_preview_canary();
        $articleRecordId = red_theme_home_preview_integer($row['ArticleRecordID'], 'Home Gallery article RecordID', 1);
        $galleryRecordId = red_theme_home_preview_integer($row['GalleryRecordID'], 'Home Gallery RecordID', 1);
        $refId = red_theme_home_preview_integer($row['RefID'], 'Home Gallery RefID', 1);
        $articleAlias = red_theme_home_preview_source_string($row['ArticleAlias'], 'Home Gallery article alias', false, 100);
        $component = red_theme_home_preview_source_string($row['Component'], 'Home Gallery component', false, 50);
        $section = red_theme_home_preview_source_string($row['Sections'], 'Home Gallery section', false, 100);
        $position = red_theme_home_preview_integer($row['HomePosition'], 'Home Gallery position', 1, 4);
        $positionOrder = red_theme_home_preview_integer($row['HomePositionOrder'], 'Home Gallery order', 0, 999);
        $language = red_theme_home_preview_source_string($row['Language'], 'Home Gallery language', false, 12);
        $active = red_theme_home_preview_source_string($row['Active'], 'Home Gallery active state', false, 1);
        $galleryAlias = red_theme_home_preview_source_string($row['GalleryAlias'], 'Home Gallery alias', false, 100);
        $galleryType = red_theme_home_preview_source_string($row['GalleryType'], 'Home Gallery type', false, 20);
        if ($articleRecordId !== $canary['articleRecordId']
            || $articleAlias !== $canary['articleAlias']
            || $component !== $canary['component']
            || strtolower($section) !== $canary['section']
            || $position !== $canary['homePosition']
            || $positionOrder !== $canary['homePositionOrder']
            || $language !== $canary['legacyLanguage']
            || $active !== 'Y'
            || $galleryRecordId !== $canary['galleryRecordId']
            || $refId !== $articleRecordId
            || $galleryAlias !== $canary['galleryAlias']
            || $galleryType !== $canary['galleryType']
        ) {
            throw new InvalidArgumentException('Home Gallery row does not match the fixed component relationship canary.');
        }
        foreach (['HomeFeature', 'HomeFeatures'] as $featureField) {
            red_theme_home_preview_source_string($row[$featureField], 'Home Gallery ' . $featureField, true, 100);
        }
        foreach (['StartDate', 'ExpDate'] as $dateField) {
            $date = red_theme_home_preview_source_string($row[$dateField], 'Home Gallery ' . $dateField, false, 32);
            if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $date) !== 1) {
                throw new InvalidArgumentException('Home Gallery dates must retain the legacy datetime shape.');
            }
        }
        $image = red_theme_home_preview_source_string($row['LongDesc'], 'Home Gallery media file', false, 240);
        if (!red_theme_valid_relative_path($image)
            || basename($image) !== $image
            || preg_match('/\.(?:gif|jpe?g|png|webp)\z/i', $image) !== 1
        ) {
            throw new InvalidArgumentException('Home Gallery media must be one safe local raster filename.');
        }
        $link = red_theme_home_preview_local_url($row['Link'], 'Home Gallery link', true);
        $newWindow = red_theme_home_preview_source_string($row['NewWindow'], 'Home Gallery window state', true, 6);
        if (!in_array($newWindow, ['', 'N'], true)) {
            throw new InvalidArgumentException('Home Gallery cannot request a new browsing context.');
        }

        return [
            'articleRecordId' => $articleRecordId,
            'articleAlias' => $articleAlias,
            'articleTitle' => red_theme_home_preview_plain_text($row['ArticleTitle'], 'Home Gallery article title', false, 180),
            'component' => $component,
            'homePosition' => $position,
            'homePositionOrder' => $positionOrder,
            'galleryRecordId' => $galleryRecordId,
            'galleryAlias' => $galleryAlias,
            'galleryTitle' => red_theme_home_preview_plain_text($row['GalleryTitle'], 'Home Gallery title', false, 180),
            'galleryType' => $galleryType,
            'caption' => red_theme_home_preview_plain_text(
                $row['ShortDesc'] !== '' ? $row['ShortDesc'] : $row['GalleryTitle'],
                'Home Gallery caption',
                false,
                180
            ),
            'image' => $image,
            'link' => $link,
        ];
    }
}

if (!function_exists('red_theme_home_preview_settings')) {
    function red_theme_home_preview_settings($rows)
    {
        if (!is_array($rows) || count($rows) !== 2 || !array_is_list($rows)) {
            throw new InvalidArgumentException('Home preview requires exactly two allowlisted setting rows.');
        }
        $settings = [];
        foreach ($rows as $row) {
            $row = red_theme_home_preview_require_row_keys(
                $row,
                ['RecordID', 'Item', 'Content', 'Language'],
                'Home setting row'
            );
            red_theme_home_preview_integer($row['RecordID'], 'Home setting RecordID', 1);
            $item = red_theme_home_preview_source_string($row['Item'], 'Home setting item', false, 50);
            $language = red_theme_home_preview_source_string($row['Language'], 'Home setting language', false, 12);
            if (!in_array($item, ['Website_Footer', 'Website_Title'], true)
                || isset($settings[$item])
                || $language !== 'sp'
            ) {
                throw new InvalidArgumentException('Home settings contain an unexpected or duplicate row.');
            }
            $settings[$item] = red_theme_home_preview_plain_text(
                $row['Content'],
                'Home setting ' . $item,
                true,
                180
            );
        }
        ksort($settings);
        if (array_keys($settings) !== ['Website_Footer', 'Website_Title']) {
            throw new InvalidArgumentException('Home settings do not match the fixed allowlist.');
        }

        return $settings;
    }
}

if (!function_exists('red_theme_home_preview_media')) {
    function red_theme_home_preview_media($projectRoot, $image)
    {
        $projectRoot = $projectRoot === null ? dirname(__DIR__) : $projectRoot;
        if (!is_string($projectRoot) || $projectRoot === '' || realpath($projectRoot) === false) {
            throw new InvalidArgumentException('Home preview project root is unavailable.');
        }
        $mediaRoot = realpath(rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/images/gallery');
        if ($mediaRoot === false || !is_dir($mediaRoot)) {
            throw new RuntimeException('Home preview Gallery media root is unavailable.');
        }
        $file = red_theme_existing_path($mediaRoot, $image);
        if ($file === null || !is_file($file) || filesize($file) <= 0 || filesize($file) > 1048576) {
            throw new RuntimeException('Home preview Gallery media is missing, unsafe, empty, or too large.');
        }
        $sha256 = hash_file('sha256', $file);
        if (!is_string($sha256) || preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1) {
            throw new RuntimeException('Home preview Gallery media could not be fingerprinted.');
        }

        return [
            'root' => $mediaRoot,
            'file' => $image,
            'bytes' => filesize($file),
            'sha256' => $sha256,
        ];
    }
}

if (!function_exists('red_theme_home_preview_prepare_rows')) {
    function red_theme_home_preview_prepare_rows(array $rows)
    {
        red_theme_preview_require_exact_keys(
            $rows,
            ['section', 'navigation', 'hero', 'gallery', 'settings'],
            [],
            'Home preview source rows'
        );
        $section = red_theme_home_preview_section($rows['section']);
        $navigation = red_theme_home_preview_navigation($rows['navigation']);
        $heroItems = red_theme_home_preview_hero($rows['hero']);
        $gallery = red_theme_home_preview_gallery($rows['gallery']);
        $settings = red_theme_home_preview_settings($rows['settings']);
        $canary = red_theme_home_preview_canary();

        $siteTitleSource = $settings['Website_Title'] !== '' ? 'advanced.Website_Title' : 'section.Title';
        $footerSource = $settings['Website_Footer'] !== '' ? 'advanced.Website_Footer' : 'section.Title';
        $descriptionSource = $section['description'] !== '' ? 'section.Description' : 'gallery.Title';
        $heroSource = $heroItems !== [] ? 'home.hero.first' : 'section/gallery fallback';
        $siteTitle = $siteTitleSource === 'advanced.Website_Title' ? $settings['Website_Title'] : $section['title'];
        $footer = $footerSource === 'advanced.Website_Footer' ? $settings['Website_Footer'] : $section['title'];
        $description = $descriptionSource === 'section.Description'
            ? $section['description']
            : $gallery['galleryTitle'];
        $heroTitle = $heroItems !== [] ? $heroItems[0]['title'] : $section['title'];
        $heroSummary = $heroItems !== [] && $heroItems[0]['summary'] !== ''
            ? $heroItems[0]['summary']
            : $description;

        $navigationItems = [];
        foreach ($navigation as $item) {
            $navigationItems[] = [
                'label' => $item['label'],
                'url' => $item['url'],
                'current' => $item['current'],
            ];
        }

        $fixture = [
            'schemaVersion' => 1,
            'theme' => 'starter-reference',
            'document' => [
                'language' => $canary['documentLanguage'],
                'title' => $section['title'] . ' — ' . $gallery['galleryTitle'],
                'description' => $description,
            ],
            'regions' => [
                'header' => [
                    'siteTitle' => $siteTitle,
                    'homeUrl' => '/',
                ],
                'navigation' => [
                    'items' => $navigationItems,
                ],
                'hero' => [
                    'title' => $heroTitle,
                    'summary' => $heroSummary,
                    'action' => [
                        'label' => 'View ' . $gallery['galleryTitle'],
                        'url' => '#preview-gallery',
                    ],
                ],
                'footer' => [
                    'copyright' => $footer,
                ],
            ],
            'page' => [
                'layout' => $section['layout'],
                'breadcrumb' => [
                    [
                        'label' => $navigation[0]['label'],
                        'url' => '',
                    ],
                ],
                'slots' => [
                    '1' => [
                        [
                            'component' => 'Gallery',
                            'data' => [
                                'title' => $gallery['galleryTitle'],
                                'items' => [
                                    [
                                        'image' => $gallery['image'],
                                        'caption' => $gallery['caption'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '2' => [],
                    '3' => [],
                    '4' => [],
                ],
            ],
        ];
        $source = [
            'mode' => 'read-only-home-preview',
            'canary' => [
                'sectionRecordId' => $section['recordId'],
                'section' => $section['section'],
                'legacyLanguage' => $section['language'],
                'route' => $canary['route'],
                'layout' => $section['layout'],
                'queryLimit' => $section['queryLimit'],
                'features' => $section['features'],
            ],
            'component' => [
                'articleRecordId' => $gallery['articleRecordId'],
                'alias' => $gallery['articleAlias'],
                'type' => $gallery['component'],
                'homePosition' => $gallery['homePosition'],
                'homePositionOrder' => $gallery['homePositionOrder'],
            ],
            'gallery' => [
                'recordId' => $gallery['galleryRecordId'],
                'alias' => $gallery['galleryAlias'],
                'type' => $gallery['galleryType'],
                'image' => $gallery['image'],
                'link' => $gallery['link'],
            ],
            'hero' => [
                'enabled' => in_array('slider', $section['features'], true),
                'rowCount' => count($heroItems),
                'source' => $heroSource,
            ],
            'queryIds' => array_keys(red_theme_home_preview_query_inventory()),
            'rowCounts' => [
                'section' => count($rows['section']),
                'navigation' => count($rows['navigation']),
                'hero' => count($rows['hero']),
                'gallery' => count($rows['gallery']),
                'settings' => count($rows['settings']),
            ],
            'fallbacks' => [
                'siteTitle' => $siteTitleSource,
                'description' => $descriptionSource,
                'footer' => $footerSource,
            ],
        ];
        red_theme_preview_assert_non_executable($fixture, 'Home prepared preview input');
        red_theme_preview_assert_non_executable($source, 'Home preview source metadata');

        return [
            'fixture' => $fixture,
            'source' => $source,
        ];
    }
}

if (!function_exists('red_theme_home_preview_render_rows')) {
    function red_theme_home_preview_render_rows(array $rows, $projectRoot = null, $databaseReads = 0)
    {
        if (!in_array($databaseReads, [0, 5], true)) {
            throw new InvalidArgumentException('Home preview database-read count must be zero or five.');
        }
        $validation = red_theme_preview_validate_reference_theme('starter-reference', $projectRoot);
        $prepared = red_theme_home_preview_prepare_rows($rows);
        $media = red_theme_home_preview_media($projectRoot, $prepared['source']['gallery']['image']);
        $contract = red_theme_preview_contract($prepared['fixture'], $validation);
        $result = red_theme_preview_render_prepared_contract(
            $validation,
            $contract,
            'read-only-home-preview',
            red_theme_home_preview_scope($databaseReads),
            $media['root']
        );
        $prepared['source']['gallery']['mediaBytes'] = $media['bytes'];
        $prepared['source']['gallery']['mediaSha256'] = $media['sha256'];
        $result['source'] = $prepared['source'];

        return $result;
    }
}

if (!function_exists('red_theme_home_preview_render')) {
    function red_theme_home_preview_render($connection, $projectRoot = null)
    {
        $read = red_theme_home_preview_read_rows($connection);
        $result = red_theme_home_preview_render_rows(
            $read['rows'],
            $projectRoot,
            $read['scope']['databaseReads']
        );
        if ($result['scope'] !== $read['scope']) {
            throw new RuntimeException('Home preview side-effect scope changed during rendering.');
        }

        return $result;
    }
}
