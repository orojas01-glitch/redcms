<?php
/**
 * Core data preparation for public theme regions.
 *
 * Legacy header/footer HTML remains intentionally unescaped to preserve the
 * existing trusted RED_Advanced behavior. Only fixed CMS item names are
 * accepted here; theme views never choose a database key. Navigation data is
 * normalized here so the theme partial only owns compatibility markup.
 */

require_once __DIR__ . '/public_render_helpers.php';

if (!function_exists('red_public_legacy_region_context')) {
    function red_public_legacy_region_context($item)
    {
        $allowedItems = ['Website_Header', 'Website_Footer'];
        if (!is_string($item) || !in_array($item, $allowedItems, true)) {
            throw new InvalidArgumentException('Unsupported legacy public theme region.');
        }

        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $info = red_public_advanced_item($db->connection, $item);
            return [
                'customHtml' => $info && array_key_exists('Content', $info)
                    ? (string) $info['Content']
                    : '',
            ];
        } finally {
            $db->close();
        }
    }
}

if (!function_exists('red_public_legacy_header_context')) {
    function red_public_legacy_header_context()
    {
        return red_public_legacy_region_context('Website_Header');
    }
}

if (!function_exists('red_public_legacy_footer_context')) {
    function red_public_legacy_footer_context()
    {
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $items = red_public_advanced_items(
                $db->connection,
                ['Website_Footer', 'Website_Red_Sphere_Credit']
            );
            return [
                'customHtml' => (string) ($items['Website_Footer'] ?? ''),
                'redSphereCreditEnabled' => red_public_red_sphere_credit_enabled(
                    $items['Website_Red_Sphere_Credit'] ?? 'Y'
                ),
            ];
        } finally {
            $db->close();
        }
    }
}

if (!function_exists('red_public_red_sphere_credit_enabled')) {
    function red_public_red_sphere_credit_enabled($value)
    {
        return strtoupper(trim((string) $value)) !== 'N';
    }
}

if (!function_exists('red_public_red_sphere_credit_html')) {
    function red_public_red_sphere_credit_html(array $footerContext)
    {
        if (empty($footerContext['redSphereCreditEnabled'])) {
            return '';
        }

        return '<aside class="red-sphere-signature" aria-label="Website credit" ' .
            'style="display:flex;flex:0 0 100%;align-self:stretch;justify-content:center;clear:both;' .
            'width:100%;box-sizing:border-box;padding:18px 12px 22px;text-align:center;">' .
            '<a id="referral" href="https://www.red-sphere.com" target="_blank" rel="noopener noreferrer" ' .
            'aria-label="Website by Red Sphere" ' .
            'style="display:inline-flex;flex-direction:column;align-items:center;gap:2px;color:#9b9b9b;' .
            'font:500 9px/1.15 Arial,Helvetica,sans-serif;letter-spacing:.08em;text-decoration:none;' .
            'opacity:.38;transition:opacity .18s ease;">' .
            '<span>WEB BY</span>' .
            '<img src="/admin/images/red-tm.png" alt="" width="46" height="22" ' .
            'loading="lazy" decoding="async" style="display:block;width:46px;height:22px;object-fit:contain;">' .
            '<span><strong style="color:#c81918;font-weight:700;">RED</strong> SPHERE</span>' .
            '</a></aside>';
    }
}

if (!function_exists('red_public_render_red_sphere_credit')) {
    function red_public_render_red_sphere_credit(array $footerContext)
    {
        echo red_public_red_sphere_credit_html($footerContext);
    }
}

if (!function_exists('red_public_legacy_navigation_context_from_rows')) {
    function red_public_legacy_navigation_context_from_rows(array $rows, $routeContext = null)
    {
        if (!is_array($routeContext)) {
            $routeContext = [
                'section' => red_public_route_value('section'),
                'category' => red_public_route_value('category'),
                'subcategory' => red_public_route_value('subcategory'),
                'article' => red_public_route_value('article'),
                'countpage' => (int) red_public_route_value('countpage', 0),
            ];
        }

        $currentSection = strtolower((string) ($routeContext['section'] ?? ''));
        $currentCategory = strtolower((string) ($routeContext['category'] ?? ''));
        $currentSubcategory = strtolower((string) ($routeContext['subcategory'] ?? ''));
        $currentArticle = strtolower((string) ($routeContext['article'] ?? ''));
        $currentSegments = [];
        if ($currentSection !== '' && $currentSection !== 'home') {
            $currentSegments[] = $currentSection;
        }
        if ($currentCategory !== '') {
            $currentSegments[] = $currentCategory;
        }
        if ($currentSubcategory !== '') {
            $currentSegments[] = $currentSubcategory;
        }
        if ($currentArticle !== '') {
            $currentSegments[] = $currentArticle;
        }
        $currentPath = $currentSegments === [] ? '/' : '/' . implode('/', $currentSegments);
        if ($currentArticle === '' && $currentPath !== '/') {
            $currentPath .= '/';
        }
        $isActiveLink = static function ($link, $allowDescendants = false) use ($currentPath) {
            $path = parse_url((string) $link, PHP_URL_PATH);
            if (!is_string($path) || $path === '' || $path[0] !== '/') {
                return false;
            }
            $path = strtolower($path);
            if ($path === $currentPath) {
                return true;
            }
            return $allowDescendants
                && $path !== '/'
                && substr($path, -1) === '/'
                && strpos($currentPath, $path) === 0;
        };
        $childrenByParent = [];
        $rootRows = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ((string) ($row['RootOrder'] ?? '') === '1') {
                $rootRows[] = $row;
            }

            $parentId = (string) ($row['Parent'] ?? '');
            $childrenByParent[$parentId][] = $row;
        }

        $items = [];
        foreach ($rootRows as $index => $row) {
            $recordId = (string) ($row['RecordID'] ?? '');
            $label = (string) ($row['Label'] ?? '');
            $link = (string) ($row['Link'] ?? '');
            $activeClass = $isActiveLink($link, true) ? 'active' : '';

            $secondLevelItems = [];
            foreach ($childrenByParent[$recordId] ?? [] as $child) {
                if ((string) ($child['RootOrder'] ?? '') === '1') {
                    continue;
                }

                $childRecordId = (string) ($child['RecordID'] ?? '');
                $thirdLevelItems = [];
                foreach ($childrenByParent[$childRecordId] ?? [] as $grandchild) {
                    $rootOrder = (string) ($grandchild['RootOrder'] ?? '');
                    if ($rootOrder === '1' || $rootOrder === '2') {
                        continue;
                    }

                    $thirdLevelItems[] = [
                        'label' => (string) ($grandchild['Label'] ?? ''),
                        'link' => (string) ($grandchild['Link'] ?? ''),
                        'newWindow' => (string) ($grandchild['NewWindow'] ?? ''),
                        'itemClass' => $isActiveLink((string) ($grandchild['Link'] ?? '')) ? 'active' : '',
                    ];
                }

                $secondLevelItems[] = [
                    'label' => (string) ($child['Label'] ?? ''),
                    'link' => (string) ($child['Link'] ?? ''),
                    'newWindow' => (string) ($child['NewWindow'] ?? ''),
                    'itemClass' => $isActiveLink((string) ($child['Link'] ?? ''), true) ? 'active' : '',
                    'children' => $thirdLevelItems,
                ];
            }

            $items[] = [
                'label' => $label,
                'link' => $link,
                'newWindow' => (string) ($row['NewWindow'] ?? ''),
                'isHome' => strtolower($label) === 'inicio',
                'itemClass' => $secondLevelItems ? 'sub-menu ' . $activeClass : $activeClass,
                'children' => $secondLevelItems,
            ];
        }

        return ['items' => $items];
    }
}

if (!function_exists('red_public_legacy_navigation_context')) {
    function red_public_legacy_navigation_context()
    {
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            return red_public_legacy_navigation_context_from_rows(
                red_public_menu_rows($db->connection)
            );
        } finally {
            $db->close();
        }
    }
}

if (!function_exists('red_public_legacy_hero_article_link')) {
    function red_public_legacy_hero_article_link(array $row)
    {
        $link = (string) ($row['Link'] ?? '');

        if (!empty($row['LongDesc'])) {
            $link = (string) ($row['Alias'] ?? '');

            if (!empty($row['SubCategories'])) {
                $link = (string) $row['SubCategories'] . '/' . $link;
            }
            if (!empty($row['Categories'])) {
                $link = (string) $row['Categories'] . '/' . $link;
            }
            if (!empty($row['Sections']) && $row['Sections'] !== 'home') {
                $link = (string) $row['Sections'] . '/' . $link;
            } else {
                $link = '/' . $link;
            }
        }

        if (!empty($row['Link'])) {
            $link = (string) $row['Link'];
        }

        return $link;
    }
}

if (!function_exists('red_public_legacy_hero_context_from_rows')) {
    function red_public_legacy_hero_context_from_rows($enabled, array $rows, $nowTimestamp = null)
    {
        if (!$enabled) {
            return ['enabled' => false, 'slides' => []];
        }

        $slides = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $expiration = (string) ($row['ExpDate'] ?? '');
            if ($expiration !== '' && $expiration !== '0000-00-00 00:00:00') {
                date_default_timezone_set('America/New_York');
                $currentTime = date(
                    'Y-m-d H:i:s',
                    $nowTimestamp === null ? time() : (int) $nowTimestamp
                );
                if ($expiration < $currentTime) {
                    continue;
                }
            }

            $slides[] = [
                'image' => (string) ($row['BigPict'] ?? ''),
                'title' => red_public_plain_text($row['Title'] ?? ''),
                'description' => red_public_plain_text($row['SliderDesc'] ?? ''),
                'link' => red_public_legacy_hero_article_link($row),
                'target' => (($row['NewWindow'] ?? '') === 'Y') ? '_blank' : '_self',
            ];
        }

        return ['enabled' => true, 'slides' => $slides];
    }
}

if (!function_exists('red_public_legacy_hero_context')) {
    function red_public_legacy_hero_context()
    {
        $queryBuilder = new Build_Query();
        $queryContext = $queryBuilder->get_query();
        $featureColumn = (string) ($queryContext[2] ?? '');
        $table = (string) ($queryContext[4] ?? '');

        if ($table === '' || $table === 'Articles') {
            return ['enabled' => false, 'slides' => []];
        }

        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        try {
            $enabled = red_public_feature_enabled($db->connection, $table, 'slider');
            $rows = $enabled
                ? red_public_feature_articles($db->connection, $featureColumn, 'slider', 5)
                : [];

            return red_public_legacy_hero_context_from_rows($enabled, $rows);
        } finally {
            $db->close();
        }
    }
}
