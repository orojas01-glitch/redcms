<?php
/**
 * Read-only generic document/navigation/hero/settings data boundary.
 *
 * This core-owned provider inventories the shared Spanish shell data needed by
 * every current public route. It accepts no route, record, query, table, path,
 * setting, theme, request, or session input; renders no theme; and performs no
 * writes. A later production-runtime batch may consume this data shape only
 * after activation and rollback policy are independently approved.
 */

if (!function_exists('red_theme_region_context_query_inventory')) {
    function red_theme_region_context_query_inventory()
    {
        return [
            'region-settings' =>
                "SELECT RecordID, Item, Content, Language\n" .
                "FROM RED_Advanced\n" .
                "WHERE Language='sp'\n" .
                "  AND Item IN ('Website_Footer', 'Website_Header', 'Website_Logo', 'Website_Title')\n" .
                'ORDER BY Item ASC, RecordID ASC LIMIT 5',
            'navigation' =>
                "SELECT RecordID, Parent, RootOrder, Title, Label, Link, NewWindow, MenuOrder, Language, Active\n" .
                "FROM RED_Menu\n" .
                "WHERE Language='sp' AND Active='Y'\n" .
                'ORDER BY MenuOrder ASC, RecordID ASC LIMIT 200',
            'hero-areas' =>
                "SELECT AreaType, RecordID, Slug, Title, Features, Language, Active\n" .
                "FROM (\n" .
                "    SELECT 'section' AS AreaType, RecordID, Sections AS Slug, Title, Features, Language, Active\n" .
                "    FROM RED_Sections WHERE Language='sp' AND Active='Y'\n" .
                "    UNION ALL\n" .
                "    SELECT 'category', RecordID, Categories, Title, Features, Language, Active\n" .
                "    FROM RED_Categories WHERE Language='sp' AND Active='Y'\n" .
                "    UNION ALL\n" .
                "    SELECT 'subcategory', RecordID, SubCategories, Title, Features, Language, Active\n" .
                "    FROM RED_SubCategories WHERE Language='sp' AND Active='Y'\n" .
                ") AS active_areas\n" .
                'ORDER BY AreaType ASC, Slug ASC, RecordID ASC LIMIT 200',
            'hero-articles' =>
                "SELECT RecordID, Title, Alias, Sections, Categories, SubCategories, LongDesc, SliderDesc,\n" .
                "Link, NewWindow, BigPict, ExpDate, HomeFeatures, HomeFeatures_Order,\n" .
                "SectionFeatures, SectionFeatures_Order, CategoryFeatures, CategoryFeatures_Order,\n" .
                "SubCategoryFeatures, SubCategoryFeatures_Order, Language, Active\n" .
                "FROM RED_Articles\n" .
                "WHERE Language='sp' AND Active='Y'\n" .
                "  AND (HomeFeatures LIKE '%slider%' OR SectionFeatures LIKE '%slider%'\n" .
                "    OR CategoryFeatures LIKE '%slider%' OR SubCategoryFeatures LIKE '%slider%')\n" .
                'ORDER BY RecordID ASC LIMIT 100',
        ];
    }
}

if (!function_exists('red_theme_region_context_assert_query_inventory')) {
    function red_theme_region_context_assert_query_inventory(array $queries)
    {
        $expectedIds = ['region-settings', 'navigation', 'hero-areas', 'hero-articles'];
        if (array_keys($queries) !== $expectedIds) {
            throw new InvalidArgumentException('Generic region context requires exactly four fixed query ids.');
        }

        $allowedTables = [
            'RED_Advanced' => true,
            'RED_Menu' => true,
            'RED_Sections' => true,
            'RED_Categories' => true,
            'RED_SubCategories' => true,
            'RED_Articles' => true,
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
                throw new InvalidArgumentException(
                    'Generic region context query "' . $id . '" is not one fixed SELECT.'
                );
            }
            preg_match_all('/\b(?:FROM|JOIN)\s+(RED_[A-Za-z0-9_]+)/i', $sql, $matches);
            if ($matches[1] === []) {
                throw new InvalidArgumentException(
                    'Generic region context query "' . $id . '" has no allowed source table.'
                );
            }
            foreach ($matches[1] as $table) {
                if (!isset($allowedTables[$table])) {
                    throw new InvalidArgumentException(
                        'Generic region context query "' . $id . '" uses an unexpected source table.'
                    );
                }
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_region_context_scope')) {
    function red_theme_region_context_scope($databaseReads)
    {
        if (!is_int($databaseReads) || !in_array($databaseReads, [0, 4], true)) {
            throw new InvalidArgumentException('Generic region context read count must be zero or four.');
        }

        return [
            'databaseReads' => $databaseReads,
            'databaseWrites' => 0,
            'filesystemReads' => 0,
            'filesystemWrites' => 0,
            'requestReads' => 0,
            'sessionReads' => 0,
            'sessionWrites' => 0,
            'themeSelectionWrites' => 0,
            'settingWrites' => 0,
            'liveRuntimeChanges' => 0,
            'standardThemeExecutions' => 0,
        ];
    }
}

if (!function_exists('red_theme_region_context_read_rows')) {
    function red_theme_region_context_read_rows($connection)
    {
        if (!is_object($connection)
            || !method_exists($connection, 'query')
            || !method_exists($connection, 'commit')
            || !method_exists($connection, 'rollback')
        ) {
            throw new InvalidArgumentException('Generic region context requires a valid mysqli connection.');
        }

        $queries = red_theme_region_context_query_inventory();
        red_theme_region_context_assert_query_inventory($queries);
        $limits = [
            'region-settings' => 5,
            'navigation' => 200,
            'hero-areas' => 200,
            'hero-articles' => 100,
        ];
        $started = false;
        try {
            if ($connection->query('START TRANSACTION READ ONLY') !== true) {
                throw new RuntimeException('Generic region context could not start a read-only transaction.');
            }
            $started = true;
            $rows = [];
            foreach ($queries as $id => $sql) {
                $result = $connection->query($sql);
                if (!is_object($result) || !method_exists($result, 'fetch_assoc')) {
                    throw new RuntimeException('Generic region context fixed read "' . $id . '" failed.');
                }
                $resultRows = [];
                while ($row = $result->fetch_assoc()) {
                    if (!is_array($row)) {
                        throw new RuntimeException('Generic region context received an invalid database row.');
                    }
                    $resultRows[] = $row;
                    if (count($resultRows) > $limits[$id]) {
                        throw new RuntimeException(
                            'Generic region context fixed read "' . $id . '" exceeded its row boundary.'
                        );
                    }
                }
                if (method_exists($result, 'free')) {
                    $result->free();
                }
                $rows[$id] = $resultRows;
            }
            if (!$connection->commit()) {
                throw new RuntimeException('Generic region context could not close its read-only transaction.');
            }
            $started = false;
        } catch (Throwable $exception) {
            if ($started) {
                $connection->rollback();
            }
            throw $exception;
        }

        return [
            'rows' => $rows,
            'scope' => red_theme_region_context_scope(count($queries)),
        ];
    }
}

if (!function_exists('red_theme_region_context_exact_row')) {
    function red_theme_region_context_exact_row($row, array $expectedKeys, $context)
    {
        if (!is_array($row) || array_keys($row) !== $expectedKeys) {
            throw new InvalidArgumentException($context . ' must contain the exact selected columns in order.');
        }

        return $row;
    }
}

if (!function_exists('red_theme_region_context_string')) {
    function red_theme_region_context_string($value, $context, $allowEmpty = false, $maximumLength = 500)
    {
        if (!is_string($value)
            || (!$allowEmpty && trim($value) === '')
            || strpos($value, "\0") !== false
            || strpos($value, '<?') !== false
            || preg_match('//u', $value) !== 1
            || strlen($value) > $maximumLength
        ) {
            throw new InvalidArgumentException($context . ' must be bounded non-executable UTF-8 text.');
        }

        return $value;
    }
}

if (!function_exists('red_theme_region_context_plain_text')) {
    function red_theme_region_context_plain_text(
        $value,
        $context,
        $allowEmpty = false,
        $maximumLength = 500
    ) {
        $source = red_theme_region_context_string(
            $value,
            $context,
            $allowEmpty,
            max($maximumLength * 4, $maximumLength)
        );
        $plain = html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', trim($plain));
        if (!is_string($plain)
            || (!$allowEmpty && $plain === '')
            || strlen($plain) > $maximumLength
        ) {
            throw new InvalidArgumentException($context . ' must reduce to bounded plain text.');
        }

        return $plain;
    }
}

if (!function_exists('red_theme_region_context_integer')) {
    function red_theme_region_context_integer($value, $context, $minimum = 0, $maximum = 2147483647)
    {
        if ((is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) === 1)
        ) {
            $integer = (int) $value;
            if (($integer >= $minimum && $integer <= $maximum)
                && (is_int($value) || (string) $integer === $value)
            ) {
                return $integer;
            }
        }

        throw new InvalidArgumentException($context . ' must be a bounded unsigned integer.');
    }
}

if (!function_exists('red_theme_region_context_internal_or_https_url')) {
    function red_theme_region_context_internal_or_https_url($value, $context, $allowEmpty = false)
    {
        $url = red_theme_region_context_string($value, $context, $allowEmpty, 1000);
        if ($url === '' && $allowEmpty) {
            return '';
        }
        if ($url[0] === '/') {
            if (strpos($url, '//') === 0 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
                throw new InvalidArgumentException($context . ' is not a safe internal URL.');
            }
            return $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException($context . ' must be an internal path or HTTPS URL.');
        }

        return $url;
    }
}

if (!function_exists('red_theme_region_context_feature_list')) {
    function red_theme_region_context_feature_list($value, $context)
    {
        $source = red_theme_region_context_string($value, $context, true, 500);
        if (trim($source) === '') {
            return [];
        }
        $features = [];
        foreach (explode(',', $source) as $feature) {
            $feature = strtolower(trim($feature));
            if ($feature === '' || preg_match('/\A[a-z][a-z0-9_-]{0,49}\z/', $feature) !== 1) {
                throw new InvalidArgumentException($context . ' contains an invalid feature id.');
            }
            if (!in_array($feature, $features, true)) {
                $features[] = $feature;
            }
        }

        return $features;
    }
}

if (!function_exists('red_theme_region_context_settings')) {
    function red_theme_region_context_settings($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 4) {
            throw new InvalidArgumentException('Generic region context requires exactly four Spanish setting rows.');
        }
        $expectedItems = ['Website_Footer', 'Website_Header', 'Website_Logo', 'Website_Title'];
        $metadata = [
            'Website_Title' => ['key' => 'site.title', 'kind' => 'plain-text'],
            'Website_Logo' => ['key' => 'branding.logo', 'kind' => 'image-reference'],
            'Website_Header' => ['key' => 'header.custom-html', 'kind' => 'trusted-html'],
            'Website_Footer' => ['key' => 'footer.custom-html', 'kind' => 'trusted-html'],
        ];
        $byItem = [];
        $seenRecordIds = [];
        foreach ($rows as $index => $sourceRow) {
            $row = red_theme_region_context_exact_row(
                $sourceRow,
                ['RecordID', 'Item', 'Content', 'Language'],
                'Generic region setting row ' . $index
            );
            $recordId = red_theme_region_context_integer(
                $row['RecordID'],
                'Generic region setting RecordID',
                1
            );
            $item = red_theme_region_context_string($row['Item'], 'Generic region setting item', false, 50);
            $language = red_theme_region_context_string(
                $row['Language'],
                'Generic region setting language',
                false,
                2
            );
            if ($item !== $expectedItems[$index]
                || !isset($metadata[$item])
                || isset($byItem[$item])
                || isset($seenRecordIds[$recordId])
                || $language !== 'sp'
            ) {
                throw new InvalidArgumentException(
                    'Generic region settings are duplicated, reordered, or outside the fixed Spanish allowlist.'
                );
            }
            $maximumLength = in_array($item, ['Website_Header', 'Website_Footer'], true) ? 262144 : 500;
            $value = red_theme_region_context_string(
                $row['Content'],
                'Generic region setting content',
                true,
                $maximumLength
            );
            if ($item === 'Website_Title') {
                $value = red_theme_region_context_plain_text(
                    $value,
                    'Generic region title',
                    true,
                    180
                );
            }
            if ($item === 'Website_Logo'
                && $value !== ''
                && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,254}\z/', $value) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Generic region logo must remain one bounded filename; media resolution is deferred.'
                );
            }
            $seenRecordIds[$recordId] = true;
            $byItem[$item] = [
                'key' => $metadata[$item]['key'],
                'legacyItem' => $item,
                'recordId' => $recordId,
                'valueKind' => $metadata[$item]['kind'],
                'value' => $value,
                'configured' => trim($value) !== '',
                'bytes' => strlen($value),
                'sha256' => hash('sha256', $value),
                'language' => $language,
                'execution' => 'data-only',
                'resolution' => $item === 'Website_Logo' ? 'deferred' : 'not-applicable',
            ];
        }

        $settings = [];
        foreach (['Website_Title', 'Website_Logo', 'Website_Header', 'Website_Footer'] as $item) {
            $settings[$byItem[$item]['key']] = $byItem[$item];
        }

        return $settings;
    }
}

if (!function_exists('red_theme_region_context_navigation_node')) {
    function red_theme_region_context_navigation_node(
        $recordId,
        array $byId,
        array $children,
        array &$stack,
        array &$visited,
        $depth
    ) {
        if ($depth > 3 || isset($stack[$recordId])) {
            throw new InvalidArgumentException('Generic region navigation contains a cycle or exceeds three levels.');
        }
        $stack[$recordId] = true;
        $visited[$recordId] = true;
        $item = $byId[$recordId];
        $childItems = [];
        foreach ($children[$recordId] ?? [] as $childId) {
            $childItems[] = red_theme_region_context_navigation_node(
                $childId,
                $byId,
                $children,
                $stack,
                $visited,
                $depth + 1
            );
        }
        unset($stack[$recordId]);
        $item['children'] = $childItems;

        return $item;
    }
}

if (!function_exists('red_theme_region_context_navigation')) {
    function red_theme_region_context_navigation($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) < 1 || count($rows) > 200) {
            throw new InvalidArgumentException('Generic region navigation requires one to 200 ordered rows.');
        }
        $expectedKeys = [
            'RecordID', 'Parent', 'RootOrder', 'Title', 'Label', 'Link',
            'NewWindow', 'MenuOrder', 'Language', 'Active',
        ];
        $byId = [];
        $children = [];
        $rootIds = [];
        $lastOrder = -1;
        $lastRecordId = -1;
        foreach ($rows as $index => $sourceRow) {
            $row = red_theme_region_context_exact_row(
                $sourceRow,
                $expectedKeys,
                'Generic region navigation row ' . $index
            );
            $recordId = red_theme_region_context_integer($row['RecordID'], 'Navigation RecordID', 1);
            $parent = red_theme_region_context_integer($row['Parent'], 'Navigation parent RecordID');
            $rootOrder = red_theme_region_context_integer($row['RootOrder'], 'Navigation root order', 1, 99);
            $menuOrder = red_theme_region_context_integer($row['MenuOrder'], 'Navigation menu order', 0, 999999);
            $language = red_theme_region_context_string($row['Language'], 'Navigation language', false, 2);
            $active = red_theme_region_context_string($row['Active'], 'Navigation active state', false, 1);
            $newWindow = red_theme_region_context_string(
                $row['NewWindow'],
                'Navigation new-window state',
                true,
                1
            );
            if (isset($byId[$recordId])
                || $language !== 'sp'
                || $active !== 'Y'
                || !in_array($newWindow, ['', 'N', 'Y'], true)
                || $menuOrder < $lastOrder
                || ($menuOrder === $lastOrder && $recordId < $lastRecordId)
                || ($parent === 0 && $rootOrder !== 1)
                || ($parent !== 0 && $rootOrder === 1)
            ) {
                throw new InvalidArgumentException(
                    'Generic region navigation row is duplicated, reordered, or outside the fixed active-Spanish shape.'
                );
            }
            $link = red_theme_region_context_internal_or_https_url($row['Link'], 'Navigation link');
            $path = parse_url($link, PHP_URL_PATH);
            $pathSegments = [];
            if (is_string($path)) {
                $pathSegments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
            }
            $byId[$recordId] = [
                'recordId' => $recordId,
                'parentRecordId' => $parent,
                'rootOrder' => $rootOrder,
                'title' => red_theme_region_context_plain_text(
                    $row['Title'],
                    'Navigation title',
                    true,
                    180
                ),
                'label' => red_theme_region_context_plain_text(
                    $row['Label'],
                    'Navigation label',
                    false,
                    180
                ),
                'url' => $link,
                'target' => $newWindow === 'Y' ? '_blank' : '_self',
                'menuOrder' => $menuOrder,
                'match' => [
                    'internal' => isset($link[0]) && $link[0] === '/',
                    'pathSegments' => $pathSegments,
                ],
            ];
            if ($parent === 0) {
                $rootIds[] = $recordId;
            } else {
                $children[$parent][] = $recordId;
            }
            $lastOrder = $menuOrder;
            $lastRecordId = $recordId;
        }
        foreach ($children as $parentId => $childIds) {
            if (!isset($byId[$parentId])) {
                throw new InvalidArgumentException('Generic region navigation contains an orphaned item.');
            }
        }
        if ($rootIds === []) {
            throw new InvalidArgumentException('Generic region navigation has no root item.');
        }
        $items = [];
        $stack = [];
        $visited = [];
        foreach ($rootIds as $rootId) {
            $items[] = red_theme_region_context_navigation_node(
                $rootId,
                $byId,
                $children,
                $stack,
                $visited,
                1
            );
        }
        if (count($visited) !== count($byId)) {
            throw new InvalidArgumentException('Generic region navigation contains an unreachable cycle.');
        }

        return ['items' => $items, 'byId' => $byId];
    }
}

if (!function_exists('red_theme_region_context_hero_areas')) {
    function red_theme_region_context_hero_areas($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 200) {
            throw new InvalidArgumentException('Generic region hero areas must be a bounded ordered list.');
        }
        $expectedKeys = ['AreaType', 'RecordID', 'Slug', 'Title', 'Features', 'Language', 'Active'];
        $areas = [];
        $lastTuple = null;
        foreach ($rows as $index => $sourceRow) {
            $row = red_theme_region_context_exact_row(
                $sourceRow,
                $expectedKeys,
                'Generic region hero area row ' . $index
            );
            $areaType = red_theme_region_context_string($row['AreaType'], 'Hero area type', false, 20);
            if (!in_array($areaType, ['section', 'category', 'subcategory'], true)) {
                throw new InvalidArgumentException('Generic region hero area has an unsupported type.');
            }
            $recordId = red_theme_region_context_integer($row['RecordID'], 'Hero area RecordID', 1);
            $slug = strtolower(red_theme_region_context_string($row['Slug'], 'Hero area slug', false, 180));
            if (preg_match('/\A[a-z0-9][a-z0-9_-]{0,179}\z/', $slug) !== 1) {
                throw new InvalidArgumentException('Generic region hero area slug is unsafe.');
            }
            $language = red_theme_region_context_string($row['Language'], 'Hero area language', false, 2);
            $active = red_theme_region_context_string($row['Active'], 'Hero area active state', false, 1);
            $tuple = [$areaType, $slug, $recordId];
            if (($lastTuple !== null && $tuple < $lastTuple) || $language !== 'sp' || $active !== 'Y') {
                throw new InvalidArgumentException(
                    'Generic region hero areas are reordered or outside the fixed active-Spanish shape.'
                );
            }
            $areaKey = $areaType . ':' . $slug;
            if (isset($areas[$areaKey])) {
                throw new InvalidArgumentException('Generic region hero area keys must be unique.');
            }
            $features = red_theme_region_context_feature_list($row['Features'], 'Hero area features');
            $areas[$areaKey] = [
                'areaType' => $areaType,
                'recordId' => $recordId,
                'slug' => $slug,
                'title' => red_theme_region_context_plain_text(
                    $row['Title'],
                    'Hero area title',
                    false,
                    180
                ),
                'features' => $features,
                'enabled' => in_array('slider', $features, true),
                'candidateScope' => $areaType === 'section' && $slug === 'home'
                    ? 'home'
                    : $areaType,
            ];
            $lastTuple = $tuple;
        }

        return $areas;
    }
}

if (!function_exists('red_theme_region_context_relative_media')) {
    function red_theme_region_context_relative_media($value, $context)
    {
        $path = red_theme_region_context_string($value, $context, true, 500);
        if ($path === '') {
            return '';
        }
        if ($path[0] === '/'
            || strpos($path, '..') !== false
            || strpos($path, '\\') !== false
            || strpos($path, ':') !== false
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]*\z/', $path) !== 1
        ) {
            throw new InvalidArgumentException($context . ' must be a confined relative media path.');
        }

        return $path;
    }
}

if (!function_exists('red_theme_region_context_hero_link')) {
    function red_theme_region_context_hero_link(array $row)
    {
        $storedLink = red_theme_region_context_internal_or_https_url(
            $row['Link'],
            'Hero Article stored link',
            true
        );
        if ($storedLink !== '') {
            return $storedLink;
        }
        $longDescription = red_theme_region_context_string(
            $row['LongDesc'],
            'Hero Article long-description marker',
            true,
            2097152
        );
        if ($longDescription === '') {
            return '';
        }
        $segments = [];
        foreach (['Sections', 'Categories', 'SubCategories'] as $column) {
            $segment = strtolower(red_theme_region_context_string(
                $row[$column],
                'Hero Article route segment',
                true,
                180
            ));
            if ($segment !== '' && !($column === 'Sections' && $segment === 'home')) {
                if (preg_match('/\A[a-z0-9][a-z0-9_-]{0,179}\z/', $segment) !== 1) {
                    throw new InvalidArgumentException('Hero Article route segment is unsafe.');
                }
                $segments[] = $segment;
            }
        }
        $alias = strtolower(red_theme_region_context_string($row['Alias'], 'Hero Article alias', false, 180));
        if (preg_match('/\A[a-z0-9][a-z0-9_-]{0,179}\z/', $alias) !== 1) {
            throw new InvalidArgumentException('Hero Article alias is unsafe.');
        }
        $segments[] = $alias;

        return '/' . implode('/', $segments);
    }
}

if (!function_exists('red_theme_region_context_hero_articles')) {
    function red_theme_region_context_hero_articles($rows)
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 100) {
            throw new InvalidArgumentException('Generic region hero Articles must be a bounded ordered list.');
        }
        $expectedKeys = [
            'RecordID', 'Title', 'Alias', 'Sections', 'Categories', 'SubCategories',
            'LongDesc', 'SliderDesc', 'Link', 'NewWindow', 'BigPict', 'ExpDate',
            'HomeFeatures', 'HomeFeatures_Order', 'SectionFeatures', 'SectionFeatures_Order',
            'CategoryFeatures', 'CategoryFeatures_Order', 'SubCategoryFeatures',
            'SubCategoryFeatures_Order', 'Language', 'Active',
        ];
        $scopeColumns = [
            'home' => ['HomeFeatures', 'HomeFeatures_Order'],
            'section' => ['SectionFeatures', 'SectionFeatures_Order'],
            'category' => ['CategoryFeatures', 'CategoryFeatures_Order'],
            'subcategory' => ['SubCategoryFeatures', 'SubCategoryFeatures_Order'],
        ];
        $groups = array_fill_keys(array_keys($scopeColumns), []);
        $recordIds = [];
        $lastRecordId = -1;
        foreach ($rows as $index => $sourceRow) {
            $row = red_theme_region_context_exact_row(
                $sourceRow,
                $expectedKeys,
                'Generic region hero Article row ' . $index
            );
            $recordId = red_theme_region_context_integer($row['RecordID'], 'Hero Article RecordID', 1);
            $language = red_theme_region_context_string($row['Language'], 'Hero Article language', false, 2);
            $active = red_theme_region_context_string($row['Active'], 'Hero Article active state', false, 1);
            $newWindow = red_theme_region_context_string(
                $row['NewWindow'],
                'Hero Article new-window state',
                true,
                1
            );
            if (isset($recordIds[$recordId])
                || $recordId < $lastRecordId
                || $language !== 'sp'
                || $active !== 'Y'
                || !in_array($newWindow, ['', 'N', 'Y'], true)
            ) {
                throw new InvalidArgumentException(
                    'Generic region hero Articles are duplicated, reordered, or outside the active-Spanish shape.'
                );
            }
            $expiration = red_theme_region_context_string(
                $row['ExpDate'],
                'Hero Article expiration',
                true,
                19
            );
            if ($expiration !== ''
                && $expiration !== '0000-00-00 00:00:00'
                && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/', $expiration) !== 1
            ) {
                throw new InvalidArgumentException('Hero Article expiration is malformed.');
            }
            $recordIds[$recordId] = true;
            $base = [
                'recordId' => $recordId,
                'title' => red_theme_region_context_plain_text(
                    $row['Title'],
                    'Hero Article title',
                    false,
                    180
                ),
                'summary' => red_theme_region_context_plain_text(
                    $row['SliderDesc'],
                    'Hero Article slider description',
                    true,
                    1000
                ),
                'image' => red_theme_region_context_relative_media($row['BigPict'], 'Hero Article image'),
                'url' => red_theme_region_context_hero_link($row),
                'target' => $newWindow === 'Y' ? '_blank' : '_self',
                'expiresAt' => $expiration,
            ];
            $matchedScope = false;
            foreach ($scopeColumns as $scope => [$featureColumn, $orderColumn]) {
                $features = red_theme_region_context_feature_list(
                    $row[$featureColumn],
                    'Hero Article ' . $scope . ' features'
                );
                if (!in_array('slider', $features, true)) {
                    continue;
                }
                $matchedScope = true;
                $candidate = $base;
                $candidate['featureOrder'] = red_theme_region_context_integer(
                    $row[$orderColumn],
                    'Hero Article ' . $scope . ' feature order',
                    0,
                    999999
                );
                $groups[$scope][] = $candidate;
            }
            if (!$matchedScope) {
                throw new InvalidArgumentException(
                    'Generic region hero Article query returned a row without the exact slider feature.'
                );
            }
            $lastRecordId = $recordId;
        }
        foreach ($groups as &$group) {
            usort($group, static function (array $left, array $right) {
                return [$left['featureOrder'], $left['recordId']]
                    <=> [$right['featureOrder'], $right['recordId']];
            });
            $group = array_slice($group, 0, 5);
        }
        unset($group);

        return $groups;
    }
}

if (!function_exists('red_theme_region_context_route_inventory')) {
    function red_theme_region_context_route_inventory()
    {
        return [
            '/' => 'section:home',
            '/contacto/' => 'section:contacto',
            '/administracion/' => 'section:administracion',
            '/administracion/instructions' => 'non-area',
            '/administracion/login' => 'non-area',
            '/contacto/contact' => 'non-area',
            '/administracion/admin-video' => 'non-area',
            '/banner-test' => 'non-area',
            '/administracion/test-vimeo' => 'non-area',
        ];
    }
}

if (!function_exists('red_theme_region_context_current_navigation_id')) {
    function red_theme_region_context_current_navigation_id(array $rootItems, $route)
    {
        $routePath = parse_url((string) $route, PHP_URL_PATH);
        $routeSegments = is_string($routePath)
            ? array_values(array_filter(explode('/', trim($routePath, '/')), 'strlen'))
            : [];
        foreach ($rootItems as $item) {
            if (empty($item['match']['internal'])) {
                continue;
            }
            $itemSegments = $item['match']['pathSegments'];
            if (($route === '/' && $item['url'] === '/')
                || ($routeSegments !== [] && $itemSegments !== [] && $routeSegments[0] === $itemSegments[0])
            ) {
                return $item['recordId'];
            }
        }

        return null;
    }
}

if (!function_exists('red_theme_region_context_report_from_rows')) {
    function red_theme_region_context_report_from_rows(array $rows, $databaseReads = 0)
    {
        $queryIds = array_keys(red_theme_region_context_query_inventory());
        if (array_keys($rows) !== $queryIds) {
            throw new InvalidArgumentException(
                'Generic region context row groups must match the four fixed query ids.'
            );
        }
        $settings = red_theme_region_context_settings($rows['region-settings']);
        $navigation = red_theme_region_context_navigation($rows['navigation']);
        $areas = red_theme_region_context_hero_areas($rows['hero-areas']);
        $candidateGroups = red_theme_region_context_hero_articles($rows['hero-articles']);
        $heroContexts = [];
        foreach ($areas as $areaKey => $area) {
            $heroContexts[$areaKey] = [
                'enabled' => $area['enabled'],
                'areaType' => $area['areaType'],
                'recordId' => $area['recordId'],
                'slug' => $area['slug'],
                'title' => $area['title'],
                'features' => $area['features'],
                'candidateScope' => $area['candidateScope'],
                'candidates' => $area['enabled'] ? $candidateGroups[$area['candidateScope']] : [],
            ];
        }
        $heroContexts['non-area'] = [
            'enabled' => false,
            'areaType' => null,
            'recordId' => null,
            'slug' => null,
            'title' => '',
            'features' => [],
            'candidateScope' => null,
            'candidates' => [],
        ];

        $routes = [];
        foreach (red_theme_region_context_route_inventory() as $url => $heroContextKey) {
            if (!isset($heroContexts[$heroContextKey])) {
                throw new InvalidArgumentException(
                    'Generic region context is missing a fixed current-route hero area.'
                );
            }
            $routes[] = [
                'url' => $url,
                'heroContextKey' => $heroContextKey,
                'navigationCurrentRecordId' => red_theme_region_context_current_navigation_id(
                    $navigation['items'],
                    $url
                ),
            ];
        }

        $settingCanary = [
            'site.title' => [1, 0, hash('sha256', '')],
            'branding.logo' => [3, 0, hash('sha256', '')],
            'header.custom-html' => [4, 0, hash('sha256', '')],
            'footer.custom-html' => [5, 0, hash('sha256', '')],
        ];
        $settingCanaryValid = true;
        foreach ($settingCanary as $key => [$recordId, $bytes, $sha256]) {
            $setting = $settings[$key];
            if ($setting['recordId'] !== $recordId
                || $setting['bytes'] !== $bytes
                || $setting['sha256'] !== $sha256
            ) {
                $settingCanaryValid = false;
            }
        }

        return [
            'schemaVersion' => 1,
            'mode' => 'read-only-generic-region-context',
            'contract' => [
                'legacyLanguage' => 'sp',
                'documentLanguage' => 'es',
                'acceptedCallerInputs' => [],
                'routeScope' => 'all-current-public-routes',
                'routeCount' => count($routes),
                'themeRendering' => false,
                'productionConnection' => false,
            ],
            'document' => [
                'settings' => $settings,
                'settingCanaryValid' => $settingCanaryValid,
            ],
            'navigation' => [
                'items' => $navigation['items'],
                'currentRecordIdByRoute' => array_column(
                    $routes,
                    'navigationCurrentRecordId',
                    'url'
                ),
            ],
            'hero' => [
                'feature' => 'slider',
                'contexts' => $heroContexts,
                'candidateGroups' => $candidateGroups,
                'expirationEvaluation' => 'deferred-to-core-clock',
            ],
            'routes' => $routes,
            'source' => [
                'queryIds' => $queryIds,
                'rowCounts' => array_map('count', $rows),
                'settingItems' => array_column($rows['region-settings'], 'Item'),
                'navigationRecordIds' => array_map(static function ($value) {
                    return (int) $value;
                }, array_column($rows['navigation'], 'RecordID')),
                'heroAreaKeys' => array_keys($areas),
                'heroArticleRecordIds' => array_map(static function ($value) {
                    return (int) $value;
                }, array_column($rows['hero-articles'], 'RecordID')),
            ],
            'scope' => red_theme_region_context_scope($databaseReads),
        ];
    }
}

if (!function_exists('red_theme_region_context_live_report')) {
    function red_theme_region_context_live_report($connection)
    {
        $read = red_theme_region_context_read_rows($connection);
        $report = red_theme_region_context_report_from_rows(
            $read['rows'],
            $read['scope']['databaseReads']
        );
        if ($report['scope'] !== $read['scope']) {
            throw new RuntimeException('Generic region context scope changed during report preparation.');
        }

        return $report;
    }
}
