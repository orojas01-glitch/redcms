<?php
/**
 * Read-only activation-readiness inventory for the portable starter.
 *
 * This boundary inventories current public route/content dependencies. It does
 * not render a public response, create preview state, select a theme, persist a
 * setting, or modify live routing/content.
 */

require_once __DIR__ . '/theme_compatibility_helpers.php';
require_once __DIR__ . '/theme_runtime.php';
require_once __DIR__ . '/theme_activation_helpers.php';

if (!function_exists('red_theme_readiness_query_inventory')) {
    function red_theme_readiness_query_inventory()
    {
        return [
            'active-areas' =>
                "SELECT AreaType, RecordID, Slug, Title, Layout, QueryLimit, Features, Description, Tags, Language, Active\n" .
                "FROM (\n" .
                "    SELECT 'section' AS AreaType, RecordID, Sections AS Slug, Title, Layout, QueryLimit,\n" .
                "        Features, Description, Tags, Language, Active\n" .
                "    FROM RED_Sections WHERE Active='Y' AND Language='sp'\n" .
                "    UNION ALL\n" .
                "    SELECT 'category', RecordID, Categories, Title, Layout, QueryLimit,\n" .
                "        Features, Description, Tags, Language, Active\n" .
                "    FROM RED_Categories WHERE Active='Y' AND Language='sp'\n" .
                "    UNION ALL\n" .
                "    SELECT 'subcategory', RecordID, SubCategories, Title, Layout, QueryLimit,\n" .
                "        Features, Description, Tags, Language, Active\n" .
                "    FROM RED_SubCategories WHERE Active='Y' AND Language='sp'\n" .
                ") AS active_areas\n" .
                'ORDER BY AreaType, Slug, RecordID',
            'active-articles' =>
                "SELECT RecordID, Title, Component, Alias, Sections, Categories, SubCategories, Layout, Article,\n" .
                "HomePosition, HomePositionOrder, SectionPosition, SectionPositionOrder,\n" .
                "CategoryPosition, CategoryPositionOrder, SubCategoryPosition, SubCategoryPositionOrder,\n" .
                "PagePosition, PagePositionOrder, HomeFeature, HomeFeatures, HomeFeatures_Order,\n" .
                "SectionFeatures, SectionFeatures_Order, CategoryFeatures, CategoryFeatures_Order,\n" .
                "SubCategoryFeatures, SubCategoryFeatures_Order, ArticleFeatures, StartDate, ExpDate,\n" .
                "ShortDesc, LongDesc, Link, NewWindow, BigPict, SmallPict, SmallPict2, Language, Active,\n" .
                "CASE WHEN StartDate<=NOW() AND (YEAR(ExpDate)=0 OR ExpDate>NOW()) THEN 1 ELSE 0 END AS RenderableNow\n" .
                "FROM RED_Articles\n" .
                "WHERE Active='Y' AND Language='sp'\n" .
                'ORDER BY Sections, Categories, SubCategories, Alias, RecordID',
            'active-navigation' =>
                "SELECT RecordID, Parent, RootOrder, Title, Label, Link, NewWindow, MenuOrder, Language, Active\n" .
                "FROM RED_Menu\n" .
                "WHERE Active='Y' AND Language='sp'\n" .
                'ORDER BY MenuOrder, RecordID',
            'form-components' =>
                "SELECT a.RecordID AS ArticleRecordID, f.RecordID AS ComponentRecordID, f.RefID, f.Title, f.Alias,\n" .
                "f.FormType, f.LongDesc AS Definition, f.TableName\n" .
                "FROM RED_Articles AS a\n" .
                "INNER JOIN RED_C_Form AS f ON CAST(f.RefID AS UNSIGNED)=a.RecordID\n" .
                "WHERE a.Active='Y' AND a.Language='sp' AND a.Component='Form'\n" .
                'ORDER BY a.RecordID, f.RecordID',
            'gallery-components' =>
                "SELECT a.RecordID AS ArticleRecordID, g.RecordID AS ComponentRecordID, g.RefID, g.Title, g.Alias,\n" .
                "g.GalleryType, g.ShortDesc, g.Link, g.LongDesc, g.NewWindow\n" .
                "FROM RED_Articles AS a\n" .
                "INNER JOIN RED_C_Gallery AS g ON CAST(g.RefID AS UNSIGNED)=a.RecordID\n" .
                "WHERE a.Active='Y' AND a.Language='sp' AND a.Component='Gallery'\n" .
                'ORDER BY a.RecordID, g.RecordID',
            'layout-catalog' =>
                "SELECT UniqueName, Positions, w_Pos1, vw_Pos1, vh_Pos1, w_Pos2, vw_Pos2, vh_Pos2,\n" .
                "w_Pos3, vw_Pos3, vh_Pos3, w_Pos4, vw_Pos4, vh_Pos4\n" .
                "FROM RED_Layouts\n" .
                'ORDER BY UniqueName',
            'custom-layout-catalog' =>
                "SELECT LayoutID, PublishedLabel, PublishedDefinition, PublishedHash\n" .
                "FROM RED_Custom_Layouts\n" .
                "WHERE Archived='N' AND PublishedDefinition IS NOT NULL\n" .
                'ORDER BY LayoutID',
            'region-settings' =>
                "SELECT RecordID, Item, Content, Language\n" .
                "FROM RED_Advanced\n" .
                "WHERE (Language='sp' AND Item IN ('Website_Footer', 'Website_Header', 'Website_Logo', 'Website_Title'))\n" .
                "   OR (Language='' AND Item IN ('System_Active_Theme', 'System_Previous_Theme'))\n" .
                'ORDER BY Language, Item, RecordID',
        ];
    }
}

if (!function_exists('red_theme_readiness_assert_query_inventory')) {
    function red_theme_readiness_assert_query_inventory(array $queries)
    {
        $expectedIds = [
            'active-areas',
            'active-articles',
            'active-navigation',
            'form-components',
            'gallery-components',
            'layout-catalog',
            'custom-layout-catalog',
            'region-settings',
        ];
        if (array_keys($queries) !== $expectedIds) {
            throw new InvalidArgumentException('Theme readiness requires exactly eight fixed query ids.');
        }

        $allowedTables = [
            'RED_Sections' => true,
            'RED_Categories' => true,
            'RED_SubCategories' => true,
            'RED_Articles' => true,
            'RED_Menu' => true,
            'RED_C_Form' => true,
            'RED_C_Gallery' => true,
            'RED_Layouts' => true,
            'RED_Custom_Layouts' => true,
            'RED_Advanced' => true,
        ];
        foreach ($queries as $id => $query) {
            if (!is_string($query)
                || preg_match('/\ASELECT\s/i', ltrim($query)) !== 1
                || strpos($query, ';') !== false
                || preg_match(
                    '/\b(?:ALTER|CALL|CREATE|DELETE|DROP|GRANT|INSERT|LOAD|LOCK|RENAME|REPLACE|REVOKE|TRUNCATE|UPDATE)\b/i',
                    $query
                ) === 1
                || preg_match('/(?:--|#|\/\*)/', $query) === 1
            ) {
                throw new InvalidArgumentException('Theme readiness query "' . $id . '" is not one fixed SELECT.');
            }
            preg_match_all('/\b(?:FROM|JOIN)\s+(RED_[A-Za-z0-9_]+)/i', $query, $matches);
            if ($matches[1] === []) {
                throw new InvalidArgumentException('Theme readiness query "' . $id . '" has no allowed source table.');
            }
            foreach ($matches[1] as $table) {
                if (!isset($allowedTables[$table])) {
                    throw new InvalidArgumentException(
                        'Theme readiness query "' . $id . '" uses unexpected table "' . $table . '".'
                    );
                }
            }
        }

        return true;
    }
}

if (!function_exists('red_theme_readiness_scope')) {
    function red_theme_readiness_scope($databaseReads, $filesystemReads)
    {
        if (!in_array($databaseReads, [0, 8], true)
            || !is_int($filesystemReads)
            || $filesystemReads < 0
            || $filesystemReads > 100
        ) {
            throw new InvalidArgumentException('Theme readiness side-effect scope is invalid.');
        }

        return [
            'databaseReads' => $databaseReads,
            'databaseWrites' => 0,
            'filesystemReads' => $filesystemReads,
            'filesystemWrites' => 0,
            'sessionReads' => 0,
            'sessionWrites' => 0,
            'themeSelectionWrites' => 0,
            'settingWrites' => 0,
            'liveRuntimeChanges' => 0,
            'standardRuntimeExecution' => false,
        ];
    }
}

if (!function_exists('red_theme_readiness_read_rows')) {
    function red_theme_readiness_read_rows($connection)
    {
        if (!($connection instanceof mysqli)) {
            throw new InvalidArgumentException('Theme readiness requires a live mysqli connection.');
        }
        $queries = red_theme_readiness_query_inventory();
        red_theme_readiness_assert_query_inventory($queries);
        $started = false;
        try {
            if (!mysqli_query($connection, 'START TRANSACTION READ ONLY')) {
                throw new RuntimeException('Theme readiness could not start a read-only transaction.');
            }
            $started = true;
            $rows = [];
            foreach ($queries as $id => $query) {
                $result = mysqli_query($connection, $query);
                if ($result === false) {
                    throw new RuntimeException(
                        'Theme readiness fixed read "' . $id . '" failed: ' . mysqli_error($connection)
                    );
                }
                $rows[$id] = mysqli_fetch_all($result, MYSQLI_ASSOC);
                mysqli_free_result($result);
                if (count($rows[$id]) > 500) {
                    throw new RuntimeException('Theme readiness query "' . $id . '" exceeded its row boundary.');
                }
            }
            if (!mysqli_commit($connection)) {
                throw new RuntimeException('Theme readiness could not close its read-only transaction.');
            }
            $started = false;
        } catch (Throwable $exception) {
            if ($started) {
                mysqli_rollback($connection);
            }
            throw $exception;
        }

        return ['rows' => $rows, 'scope' => red_theme_readiness_scope(8, 0)];
    }
}

if (!function_exists('red_theme_readiness_exact_row')) {
    function red_theme_readiness_exact_row($row, array $keys, $label)
    {
        if (!is_array($row) || array_keys($row) !== $keys) {
            throw new InvalidArgumentException($label . ' row does not match its fixed selected columns.');
        }
        foreach ($row as $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException($label . ' row contains a non-scalar value.');
            }
        }
        return $row;
    }
}

if (!function_exists('red_theme_readiness_text')) {
    function red_theme_readiness_text($value, $label, $allowEmpty = true, $maximum = 200000)
    {
        if (!is_string($value) && !is_numeric($value) && $value !== null) {
            throw new InvalidArgumentException($label . ' must be scalar text.');
        }
        $text = trim((string) $value);
        if ((!$allowEmpty && $text === '') || strlen($text) > $maximum || preg_match('//u', $text) !== 1) {
            throw new InvalidArgumentException($label . ' is empty, invalid, or outside its length boundary.');
        }
        return $text;
    }
}

if (!function_exists('red_theme_readiness_integer')) {
    function red_theme_readiness_integer($value, $label, $minimum = 0, $maximum = 4294967295)
    {
        if ((is_int($value) || (is_string($value) && preg_match('/\A-?[0-9]+\z/', $value) === 1))) {
            $integer = (int) $value;
            if ($integer >= $minimum && $integer <= $maximum) {
                return $integer;
            }
        }
        throw new InvalidArgumentException($label . ' is outside its integer boundary.');
    }
}

if (!function_exists('red_theme_readiness_list')) {
    function red_theme_readiness_list($value)
    {
        $items = [];
        foreach (preg_split('/\s*,\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) as $item) {
            if (!in_array($item, $items, true)) {
                $items[] = $item;
            }
        }
        return $items;
    }
}

if (!function_exists('red_theme_readiness_article_url')) {
    function red_theme_readiness_article_url(array $article)
    {
        $segments = [];
        if (strtolower($article['sections']) !== 'home' && $article['sections'] !== '') {
            $segments[] = $article['sections'];
        }
        foreach (['categories', 'subcategories', 'alias'] as $key) {
            if ($article[$key] !== '') {
                $segments[] = $article[$key];
            }
        }
        return '/' . implode('/', array_map('rawurlencode', $segments));
    }
}

if (!function_exists('red_theme_readiness_resource_references')) {
    function red_theme_readiness_resource_references($html)
    {
        $references = [];
        if (preg_match_all('/\b(?:src|href)\s*=\s*["\']([^"\']+)["\']/i', (string) $html, $matches)) {
            foreach ($matches[1] as $reference) {
                $reference = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($reference !== '' && !in_array($reference, $references, true)) {
                    $references[] = $reference;
                }
            }
        }
        sort($references, SORT_STRING);
        return $references;
    }
}

if (!function_exists('red_theme_readiness_local_image_fact')) {
    function red_theme_readiness_local_image_fact($projectRoot, $publicDirectory, $filename, &$filesystemReads)
    {
        $filename = trim((string) $filename);
        $publicDirectory = trim((string) $publicDirectory, '/');
        $fact = [
            'publicPath' => $filename === '' ? '' : '/' . $publicDirectory . '/' . $filename,
            'safe' => false,
            'exists' => false,
            'bytes' => 0,
            'sha256' => '',
            'width' => 0,
            'height' => 0,
            'mime' => '',
        ];
        if ($filename === ''
            || basename($filename) !== $filename
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,199}\z/', $filename) !== 1
        ) {
            return $fact;
        }
        $root = realpath(rtrim((string) $projectRoot, '/') . '/' . $publicDirectory);
        if ($root === false || !is_dir($root)) {
            return $fact;
        }
        $path = realpath($root . '/' . $filename);
        if ($path === false || !is_file($path) || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0) {
            $fact['safe'] = true;
            return $fact;
        }
        $filesystemReads++;
        $size = filesize($path);
        $hash = hash_file('sha256', $path);
        $image = @getimagesize($path);
        $fact['safe'] = true;
        $fact['exists'] = $size !== false && $hash !== false && is_array($image);
        if ($fact['exists']) {
            $fact['bytes'] = (int) $size;
            $fact['sha256'] = (string) $hash;
            $fact['width'] = (int) ($image[0] ?? 0);
            $fact['height'] = (int) ($image[1] ?? 0);
            $fact['mime'] = (string) ($image['mime'] ?? '');
        }
        return $fact;
    }
}

if (!function_exists('red_theme_readiness_form_fields')) {
    function red_theme_readiness_form_fields($definition)
    {
        $fields = [];
        if (preg_match_all('/\|name=([^|;\r\n]+)\|type=([^|;\r\n]+)/', (string) $definition, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim($match[1]);
                $type = trim($match[2]);
                if ($type !== 'button') {
                    $fields[] = ['name' => $name, 'type' => $type];
                }
            }
        }
        return $fields;
    }
}

if (!function_exists('red_theme_readiness_body_media_inventory')) {
    function red_theme_readiness_body_media_inventory(array $references, $projectRoot, &$filesystemReads)
    {
        $root = realpath((string) $projectRoot);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Theme readiness project root is unavailable.');
        }
        $files = [];
        $manifest = [];
        foreach ($references as $reference) {
            $path = parse_url($reference, PHP_URL_PATH);
            if (!is_string($path)
                || preg_match('/\.(?:gif|jpe?g|png|webp)\z/i', $path) !== 1
                || preg_match('~\A(?:https?:)?//~i', $reference) === 1
            ) {
                continue;
            }
            while (strpos($path, '../') === 0) {
                $path = substr($path, 3);
            }
            $path = ltrim($path, '/');
            if ($path === '' || preg_match('~(?:\A|/)\.\.(?:/|\z)~', $path) === 1) {
                $files[] = ['publicPath' => '/' . $path, 'safe' => false, 'exists' => false];
                $manifest[] = '/' . $path . ':UNSAFE';
                continue;
            }
            $resolved = realpath($root . '/' . $path);
            $safe = $resolved !== false
                && is_file($resolved)
                && strpos($resolved, $root . DIRECTORY_SEPARATOR) === 0;
            $fact = ['publicPath' => '/' . $path, 'safe' => $safe, 'exists' => false];
            if ($safe) {
                $filesystemReads++;
                $hash = hash_file('sha256', $resolved);
                $size = filesize($resolved);
                $fact['exists'] = $hash !== false && $size !== false;
                if ($fact['exists']) {
                    $fact['bytes'] = (int) $size;
                    $fact['sha256'] = (string) $hash;
                }
            }
            $files[] = $fact;
            $manifest[] = $fact['publicPath'] . ':' . ($fact['sha256'] ?? ($safe ? 'UNREADABLE' : 'MISSING'));
        }
        usort($files, static function (array $left, array $right) {
            return strcmp($left['publicPath'], $right['publicPath']);
        });
        sort($manifest, SORT_STRING);
        $existing = count(array_filter($files, static function (array $file) {
            return !empty($file['exists']);
        }));

        return [
            'count' => count($files),
            'existing' => $existing,
            'missingOrUnsafe' => count($files) - $existing,
            'manifestSha256' => hash('sha256', implode("\n", $manifest)),
            'files' => $files,
        ];
    }
}

if (!function_exists('red_theme_readiness_form_endpoint')) {
    function red_theme_readiness_form_endpoint($formType)
    {
        $map = [
            'Contact' => '/bin/contact.php',
            'Login' => '/bin/login.php',
            'Register' => '/bin/register.php',
            'Response' => '/bin/response.php',
        ];
        return $map[$formType] ?? '';
    }
}

if (!function_exists('red_theme_readiness_video_fact')) {
    function red_theme_readiness_video_fact($source)
    {
        $source = trim((string) $source);
        $provider = '';
        $id = '';
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $source, $matches)) {
            $provider = 'youtube';
            $id = $matches[1];
        } elseif (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~i', $source, $matches)) {
            $provider = 'vimeo';
            $id = $matches[1];
        }
        return ['source' => $source, 'provider' => $provider, 'id' => $id, 'recognized' => $id !== ''];
    }
}

if (!function_exists('red_theme_readiness_template_capabilities')) {
    function red_theme_readiness_template_capabilities($projectRoot, &$filesystemReads)
    {
        $paths = [
            'document' => 'themes/starter-reference/templates/page.php',
            'article' => 'themes/starter-reference/components/article.php',
            'form' => 'themes/starter-reference/components/form.php',
            'gallery' => 'themes/starter-reference/components/gallery.php',
            'previewCore' => 'includes/theme_preview_helpers.php',
            'instructionsProvider' => 'includes/theme_preview_instructions_helpers.php',
            'loginProvider' => 'includes/theme_preview_login_helpers.php',
            'selectedContactProvider' => 'includes/theme_preview_selected_contact_helpers.php',
            'operationBoundary' => 'includes/public_form_operation_helpers.php',
            'contactEndpoint' => 'bin/contact.php',
            'loginEndpoint' => 'bin/login.php',
            'fallbackBoundary' => 'includes/public_route_fallback_helpers.php',
            'publicPageLayout' => 'class/class_page_layout.php',
            'regionContextProvider' => 'includes/theme_region_context_helpers.php',
        ];
        $sources = [];
        foreach ($paths as $id => $relativePath) {
            $path = rtrim((string) $projectRoot, '/') . '/' . $relativePath;
            if (!is_file($path)) {
                throw new RuntimeException('Theme readiness cannot inspect fixed starter file ' . $relativePath . '.');
            }
            $source = file_get_contents($path);
            if ($source === false) {
                throw new RuntimeException('Theme readiness cannot read fixed starter file ' . $relativePath . '.');
            }
            $filesystemReads++;
            $sources[$id] = $source;
        }

        return [
            'document' => [
                'contactNoticeExact' => strpos($sources['document'], 'read-only-contact-preview') !== false,
                'homeNoticeExact' => strpos($sources['document'], 'read-only-home-preview') !== false,
                'administrationNoticeExact' => strpos(
                    $sources['document'],
                    'read-only-administration-preview'
                ) !== false,
                'instructionsNoticeExact' => strpos(
                    $sources['document'],
                    'read-only-instructions-preview'
                ) !== false,
                'loginNoticeExact' => strpos(
                    $sources['document'],
                    'read-only-login-preview'
                ) !== false,
                'selectedContactNoticeExact' => strpos(
                    $sources['document'],
                    'read-only-selected-contact-preview'
                ) !== false,
            ],
            'routeFallback' => [
                'coreContract' => strpos(
                    $sources['fallbackBoundary'],
                    'red_public_route_fallback_contracts'
                ) !== false
                    && strpos($sources['fallbackBoundary'], "'empty-layout-shell'") !== false
                    && strpos($sources['fallbackBoundary'], "'unmatched-theme-404'") !== false
                    && strpos(
                        $sources['fallbackBoundary'],
                        'red_public_route_fallback_render'
                    ) !== false,
                'livePublicConnection' => strpos(
                    $sources['publicPageLayout'],
                    'includes/public_route_fallback_helpers.php'
                ) !== false
                    && strpos(
                        $sources['publicPageLayout'],
                        'red_public_route_fallback_render($this->layout)'
                    ) !== false,
            ],
            'regionContext' => [
                'coreProvider' => strpos(
                    $sources['regionContextProvider'],
                    'red_theme_region_context_query_inventory'
                ) !== false
                    && strpos(
                        $sources['regionContextProvider'],
                        'red_theme_region_context_report_from_rows'
                    ) !== false
                    && strpos(
                        $sources['regionContextProvider'],
                        'red_theme_region_context_live_report'
                    ) !== false
                    && strpos(
                        $sources['regionContextProvider'],
                        'START TRANSACTION READ ONLY'
                    ) !== false,
                'allCurrentRoutes' => strpos(
                    $sources['regionContextProvider'],
                    'red_theme_region_context_route_inventory'
                ) !== false
                    && strpos(
                        $sources['regionContextProvider'],
                        "'routeScope' => 'all-current-public-routes'"
                    ) !== false,
                'inputFree' => strpos(
                    $sources['regionContextProvider'],
                    "'acceptedCallerInputs' => []"
                ) !== false,
                'productionConnected' => strpos(
                    $sources['regionContextProvider'],
                    "'productionConnection' => true"
                ) !== false,
            ],
            'article' => [
                'escapedSummary' => strpos($sources['article'], 'htmlspecialchars($article[\'summary\']') !== false,
                'trustedHtml' => strpos($sources['article'], 'isset($article[\'bodyHtml\'])') !== false
                    && strpos($sources['article'], '$article[\'bodyHtml\']') !== false
                    && strpos($sources['previewCore'], 'red_theme_preview_trusted_article_html') !== false
                    && strpos($sources['previewCore'], 'read-only-instructions-preview') !== false,
                'selectedInstructionsProvider' => strpos(
                    $sources['instructionsProvider'],
                    'red_theme_instructions_preview_render'
                ) !== false
                    && strpos($sources['instructionsProvider'], "'articleRecordId' => 89196971") !== false
                    && strpos(
                        $sources['instructionsProvider'],
                        'red_theme_instructions_preview_sanitize_body'
                    ) !== false,
            ],
            'form' => [
                'displayFields' => strpos($sources['form'], '<form') !== false,
                'submits' => strpos($sources['form'], 'type="button"') === false,
                'endpointInput' => strpos($sources['form'], 'action=') !== false,
                'selectedLoginProvider' => strpos(
                    $sources['loginProvider'],
                    'red_theme_login_preview_render'
                ) !== false
                    && strpos($sources['loginProvider'], "'articleRecordId' => 966111194") !== false
                    && strpos($sources['loginProvider'], "'formRecordId' => 884542279") !== false
                    && strpos(
                        $sources['loginProvider'],
                        'red_theme_administration_preview_login_template'
                    ) !== false,
                'selectedContactProvider' => strpos(
                    $sources['selectedContactProvider'],
                    'red_theme_selected_contact_preview_render'
                ) !== false
                    && strpos(
                        $sources['selectedContactProvider'],
                        "'articleRecordId' => 459269660"
                    ) !== false
                    && strpos(
                        $sources['selectedContactProvider'],
                        "'formRecordId' => 93039112"
                    ) !== false
                    && strpos(
                        $sources['selectedContactProvider'],
                        'red_theme_contact_preview_form_template'
                    ) !== false,
                'coreOperationBoundary' => strpos(
                    $sources['operationBoundary'],
                    'red_public_form_operation_contracts'
                ) !== false
                    && strpos(
                        $sources['operationBoundary'],
                        'red_public_form_operation_submission'
                    ) !== false
                    && strpos(
                        $sources['operationBoundary'],
                        'red_public_form_operation_execute'
                    ) !== false
                    && strpos(
                        $sources['operationBoundary'],
                        'live-operational-form-boundary'
                    ) !== false,
                'contactOperationAdapter' => strpos(
                    $sources['contactEndpoint'],
                    'includes/public_form_operation_helpers.php'
                ) !== false
                    && strpos(
                        $sources['contactEndpoint'],
                        "red_public_form_operation_execute(\n        'contact'"
                    ) !== false
                    && strpos(
                        $sources['contactEndpoint'],
                        "'consumeContactSession'"
                    ) !== false
                    && strpos(
                        $sources['contactEndpoint'],
                        'return (bool) $mail->send();'
                    ) !== false
                    && strpos(
                        $sources['contactEndpoint'],
                        'return mail('
                    ) !== false,
                'loginOperationAdapter' => strpos(
                    $sources['loginEndpoint'],
                    'includes/public_form_operation_helpers.php'
                ) !== false
                    && strpos(
                        $sources['loginEndpoint'],
                        "red_public_form_operation_execute(\n\t\t'login'"
                    ) !== false
                    && strpos(
                        $sources['loginEndpoint'],
                        "'authenticate' => static function"
                    ) !== false
                    && strpos(
                        $sources['loginEndpoint'],
                        "return 'success';"
                    ) !== false
                    && strpos(
                        $sources['loginEndpoint'],
                        "\$_SESSION['AdminPasswordFingerprint']"
                    ) !== false,
            ],
            'gallery' => [
                'imageItems' => strpos($sources['gallery'], '<img') !== false,
                'videoContract' => strpos($sources['gallery'], 'starter-video-contract') !== false
                    && strpos($sources['gallery'], 'data-video-provider') !== false
                    && strpos($sources['gallery'], 'data-video-id') !== false
                    && strpos($sources['gallery'], 'External playback is intentionally disabled') !== false,
                'videoEmbed' => strpos($sources['gallery'], '<iframe') !== false,
                'bannerLink' => strpos($sources['gallery'], '<a ') !== false,
            ],
        ];
    }
}

if (!function_exists('red_theme_readiness_activation_capabilities')) {
    function red_theme_readiness_activation_capabilities($projectRoot, &$filesystemReads)
    {
        $paths = [
            'helper' => 'includes/theme_activation_helpers.php',
            'endpoint' => 'admin/bin/theme_preview.php',
            'entrypoint' => 'index.php',
            'migration' => 'database/migrations/2026-07-17-active-theme-state.sql',
        ];
        $sources = [];
        foreach ($paths as $id => $relativePath) {
            $path = rtrim((string) $projectRoot, '/') . '/' . $relativePath;
            if (!is_file($path)) {
                $sources[$id] = '';
                continue;
            }
            $source = file_get_contents($path);
            if (!is_string($source)) {
                $sources[$id] = '';
                continue;
            }
            $filesystemReads++;
            $sources[$id] = $source;
        }

        return [
            'persistedState' => strpos($sources['helper'], 'red_theme_activation_read_state') !== false
                && strpos($sources['helper'], 'red_theme_activation_apply') !== false
                && strpos($sources['migration'], 'System_Active_Theme') !== false
                && strpos($sources['migration'], 'System_Previous_Theme') !== false
                && strpos($sources['migration'], 'START TRANSACTION') !== false
                && strpos($sources['migration'], 'COMMIT') !== false,
            'adminControls' => strpos(
                $sources['endpoint'],
                "red_require_admin_site_manager(\$method === 'POST')"
            ) !== false
                && strpos($sources['endpoint'], 'value="activate"') !== false
                && strpos($sources['endpoint'], 'value="rollback"') !== false
                && strpos($sources['endpoint'], 'red_theme_activation_apply') !== false,
            'publicSelection' => strpos(
                $sources['entrypoint'],
                'red_theme_activation_active_id_from_project'
            ) !== false
                && strpos($sources['entrypoint'], "'legacy-bootstrap',\n        true") !== false,
            'legacyRecovery' => strpos(
                $sources['entrypoint'],
                'RED-CMS active theme render failed; using legacy-bootstrap'
            ) !== false
                && strpos(
                    $sources['entrypoint'],
                    "red_theme_runtime_bootstrap('legacy-bootstrap', __DIR__)"
                ) !== false
                && strpos($sources['entrypoint'], 'RED-CMS legacy theme recovery failed') !== false,
        ];
    }
}

if (!function_exists('red_theme_readiness_report_from_rows')) {
    function red_theme_readiness_report_from_rows(array $rows, $projectRoot = null, $databaseReads = 0)
    {
        $queryIds = array_keys(red_theme_readiness_query_inventory());
        if (array_keys($rows) !== $queryIds) {
            throw new InvalidArgumentException('Theme readiness row groups must match the eight fixed query ids.');
        }
        if (!in_array($databaseReads, [0, 8], true)) {
            throw new InvalidArgumentException('Theme readiness database-read count must be zero or eight.');
        }
        $projectRoot = $projectRoot ?: dirname(__DIR__);
        $filesystemReads = 0;

        $areaKeys = [
            'AreaType', 'RecordID', 'Slug', 'Title', 'Layout', 'QueryLimit', 'Features',
            'Description', 'Tags', 'Language', 'Active',
        ];
        $areas = [];
        foreach ($rows['active-areas'] as $index => $row) {
            $row = red_theme_readiness_exact_row($row, $areaKeys, 'Active area ' . $index);
            $type = red_theme_readiness_text($row['AreaType'], 'Area type', false, 20);
            if (!in_array($type, ['section', 'category', 'subcategory'], true)) {
                throw new InvalidArgumentException('Active area type is outside the fixed allowlist.');
            }
            $slug = red_theme_readiness_text($row['Slug'], 'Area slug', false, 100);
            $areas[] = [
                'type' => $type,
                'recordId' => red_theme_readiness_integer($row['RecordID'], 'Area RecordID', 1),
                'slug' => $slug,
                'title' => red_theme_readiness_text($row['Title'], 'Area title', true, 120),
                'layout' => red_theme_readiness_text($row['Layout'], 'Area layout', true, 64),
                'queryLimit' => red_theme_readiness_integer($row['QueryLimit'], 'Area query limit', 1, 999),
                'features' => red_theme_readiness_list($row['Features']),
                'descriptionBytes' => strlen((string) $row['Description']),
                'tagsBytes' => strlen((string) $row['Tags']),
                'language' => red_theme_readiness_text($row['Language'], 'Area language', false, 2),
                'active' => $row['Active'] === 'Y',
            ];
        }

        $articleKeys = [
            'RecordID', 'Title', 'Component', 'Alias', 'Sections', 'Categories', 'SubCategories', 'Layout',
            'Article', 'HomePosition', 'HomePositionOrder', 'SectionPosition', 'SectionPositionOrder',
            'CategoryPosition', 'CategoryPositionOrder', 'SubCategoryPosition', 'SubCategoryPositionOrder',
            'PagePosition', 'PagePositionOrder', 'HomeFeature', 'HomeFeatures', 'HomeFeatures_Order',
            'SectionFeatures', 'SectionFeatures_Order', 'CategoryFeatures', 'CategoryFeatures_Order',
            'SubCategoryFeatures', 'SubCategoryFeatures_Order', 'ArticleFeatures', 'StartDate', 'ExpDate',
            'ShortDesc', 'LongDesc', 'Link', 'NewWindow', 'BigPict', 'SmallPict', 'SmallPict2',
            'Language', 'Active', 'RenderableNow',
        ];
        $articles = [];
        foreach ($rows['active-articles'] as $index => $row) {
            $row = red_theme_readiness_exact_row($row, $articleKeys, 'Active article ' . $index);
            $bodyResources = red_theme_readiness_resource_references($row['LongDesc']);
            $article = [
                'recordId' => red_theme_readiness_integer($row['RecordID'], 'Article RecordID', 1),
                'title' => red_theme_readiness_text($row['Title'], 'Article title', true),
                'component' => red_theme_readiness_text($row['Component'], 'Article component', false, 50),
                'alias' => red_theme_readiness_text($row['Alias'], 'Article alias', false, 200),
                'sections' => red_theme_readiness_text($row['Sections'], 'Article section', true, 100),
                'categories' => red_theme_readiness_text($row['Categories'], 'Article category', true, 100),
                'subcategories' => red_theme_readiness_text($row['SubCategories'], 'Article subcategory', true, 100),
                'layout' => red_theme_readiness_text($row['Layout'], 'Article layout', true, 64),
                'articleMatch' => red_theme_readiness_text($row['Article'], 'Article match value', true, 255),
                'positions' => [
                    'home' => red_theme_readiness_integer($row['HomePosition'], 'Home position'),
                    'homeOrder' => red_theme_readiness_integer($row['HomePositionOrder'], 'Home position order'),
                    'section' => red_theme_readiness_integer($row['SectionPosition'], 'Section position'),
                    'sectionOrder' => red_theme_readiness_integer($row['SectionPositionOrder'], 'Section position order'),
                    'category' => red_theme_readiness_integer($row['CategoryPosition'], 'Category position'),
                    'categoryOrder' => red_theme_readiness_integer($row['CategoryPositionOrder'], 'Category position order'),
                    'subcategory' => red_theme_readiness_integer($row['SubCategoryPosition'], 'Subcategory position'),
                    'subcategoryOrder' => red_theme_readiness_integer($row['SubCategoryPositionOrder'], 'Subcategory order'),
                    'page' => red_theme_readiness_integer($row['PagePosition'], 'Page position'),
                    'pageOrder' => red_theme_readiness_integer($row['PagePositionOrder'], 'Page position order'),
                ],
                'features' => [
                    'homeFeature' => $row['HomeFeature'] === 'Y',
                    'home' => red_theme_readiness_list($row['HomeFeatures']),
                    'section' => red_theme_readiness_list($row['SectionFeatures']),
                    'category' => red_theme_readiness_list($row['CategoryFeatures']),
                    'subcategory' => red_theme_readiness_list($row['SubCategoryFeatures']),
                    'article' => red_theme_readiness_list($row['ArticleFeatures']),
                ],
                'startDate' => red_theme_readiness_text($row['StartDate'], 'Article start date', false, 30),
                'expiryDate' => red_theme_readiness_text($row['ExpDate'], 'Article expiry date', false, 30),
                'renderableNow' => (string) $row['RenderableNow'] === '1',
                'summaryBytes' => strlen((string) $row['ShortDesc']),
                'bodyBytes' => strlen((string) $row['LongDesc']),
                'bodySha256' => hash('sha256', (string) $row['LongDesc']),
                'bodyResources' => $bodyResources,
                'bodyMedia' => red_theme_readiness_body_media_inventory(
                    $bodyResources,
                    $projectRoot,
                    $filesystemReads
                ),
                'link' => red_theme_readiness_text($row['Link'], 'Article link', true, 2000),
                'newWindow' => red_theme_readiness_text($row['NewWindow'], 'Article target', true, 10),
                'images' => array_values(array_filter([
                    red_theme_readiness_text($row['BigPict'], 'Article big image', true, 200),
                    red_theme_readiness_text($row['SmallPict'], 'Article small image', true, 200),
                    red_theme_readiness_text($row['SmallPict2'], 'Article second image', true, 200),
                ], static function ($value) { return $value !== ''; })),
                'language' => red_theme_readiness_text($row['Language'], 'Article language', false, 2),
                'active' => $row['Active'] === 'Y',
            ];
            $article['canonicalUrl'] = red_theme_readiness_article_url($article);
            $articles[$article['recordId']] = $article;
        }

        $navigationKeys = [
            'RecordID', 'Parent', 'RootOrder', 'Title', 'Label', 'Link',
            'NewWindow', 'MenuOrder', 'Language', 'Active',
        ];
        $navigation = [];
        foreach ($rows['active-navigation'] as $index => $row) {
            $row = red_theme_readiness_exact_row($row, $navigationKeys, 'Navigation ' . $index);
            $navigation[] = [
                'recordId' => red_theme_readiness_integer($row['RecordID'], 'Navigation RecordID', 1),
                'parent' => red_theme_readiness_integer($row['Parent'], 'Navigation parent'),
                'rootOrder' => red_theme_readiness_text($row['RootOrder'], 'Navigation root order', false, 2),
                'title' => red_theme_readiness_text($row['Title'], 'Navigation title', true, 50),
                'label' => red_theme_readiness_text($row['Label'], 'Navigation label', false, 50),
                'link' => red_theme_readiness_text($row['Link'], 'Navigation link', false, 2000),
                'newWindow' => red_theme_readiness_text($row['NewWindow'], 'Navigation target', true, 10),
                'order' => red_theme_readiness_integer($row['MenuOrder'], 'Navigation order'),
                'language' => red_theme_readiness_text($row['Language'], 'Navigation language', false, 2),
                'active' => $row['Active'] === 'Y',
            ];
        }

        $formKeys = [
            'ArticleRecordID', 'ComponentRecordID', 'RefID', 'Title', 'Alias',
            'FormType', 'Definition', 'TableName',
        ];
        $forms = [];
        foreach ($rows['form-components'] as $index => $row) {
            $row = red_theme_readiness_exact_row($row, $formKeys, 'Form component ' . $index);
            $articleId = red_theme_readiness_integer($row['ArticleRecordID'], 'Form Article RecordID', 1);
            $formType = red_theme_readiness_text($row['FormType'], 'Form type', false, 20);
            $definition = (string) $row['Definition'];
            $refId = red_theme_readiness_integer($row['RefID'], 'Form RefID', 1);
            if ($refId !== $articleId
                || !isset($articles[$articleId])
                || $articles[$articleId]['component'] !== 'Form'
                || isset($forms[$articleId])
            ) {
                throw new InvalidArgumentException('Form component relationship is missing, duplicated, or invalid.');
            }
            $forms[$articleId] = [
                'recordId' => red_theme_readiness_integer($row['ComponentRecordID'], 'Form RecordID', 1),
                'refId' => $refId,
                'title' => red_theme_readiness_text($row['Title'], 'Form title', true),
                'alias' => red_theme_readiness_text($row['Alias'], 'Form alias', false),
                'type' => $formType,
                'fields' => red_theme_readiness_form_fields($definition),
                'definitionBytes' => strlen($definition),
                'definitionSha256' => hash('sha256', $definition),
                'tableNameDeclared' => trim((string) $row['TableName']) !== '',
                'operationalEndpoint' => red_theme_readiness_form_endpoint($formType),
            ];
        }

        $galleryKeys = [
            'ArticleRecordID', 'ComponentRecordID', 'RefID', 'Title', 'Alias',
            'GalleryType', 'ShortDesc', 'Link', 'LongDesc', 'NewWindow',
        ];
        $galleries = [];
        foreach ($rows['gallery-components'] as $index => $row) {
            $row = red_theme_readiness_exact_row($row, $galleryKeys, 'Gallery component ' . $index);
            $articleId = red_theme_readiness_integer($row['ArticleRecordID'], 'Gallery Article RecordID', 1);
            $type = red_theme_readiness_text($row['GalleryType'], 'Gallery type', false, 20);
            $longDescription = red_theme_readiness_text($row['LongDesc'], 'Gallery source', true, 200000);
            $media = ['kind' => 'none'];
            if ($type === 'Banner') {
                $media = [
                    'kind' => 'local-image',
                    'fact' => red_theme_readiness_local_image_fact(
                        $projectRoot,
                        'images/gallery',
                        $longDescription,
                        $filesystemReads
                    ),
                ];
            } elseif ($type === 'Video') {
                $media = ['kind' => 'external-video', 'fact' => red_theme_readiness_video_fact($longDescription)];
            } elseif ($type === 'Gallery') {
                $media = ['kind' => 'legacy-gallery-list', 'sourceBytes' => strlen($longDescription)];
            }
            $refId = red_theme_readiness_integer($row['RefID'], 'Gallery RefID', 1);
            if ($refId !== $articleId
                || !isset($articles[$articleId])
                || $articles[$articleId]['component'] !== 'Gallery'
                || isset($galleries[$articleId])
            ) {
                throw new InvalidArgumentException('Gallery component relationship is missing, duplicated, or invalid.');
            }
            $galleries[$articleId] = [
                'recordId' => red_theme_readiness_integer($row['ComponentRecordID'], 'Gallery RecordID', 1),
                'refId' => $refId,
                'title' => red_theme_readiness_text($row['Title'], 'Gallery title', true),
                'alias' => red_theme_readiness_text($row['Alias'], 'Gallery alias', false),
                'type' => $type,
                'captionBytes' => strlen((string) $row['ShortDesc']),
                'link' => red_theme_readiness_text($row['Link'], 'Gallery link', true, 2000),
                'newWindow' => red_theme_readiness_text($row['NewWindow'], 'Gallery target', true, 10),
                'media' => $media,
            ];
        }

        $layoutKeys = [
            'UniqueName', 'Positions', 'w_Pos1', 'vw_Pos1', 'vh_Pos1', 'w_Pos2', 'vw_Pos2', 'vh_Pos2',
            'w_Pos3', 'vw_Pos3', 'vh_Pos3', 'w_Pos4', 'vw_Pos4', 'vh_Pos4',
        ];
        $layouts = [];
        foreach ($rows['layout-catalog'] as $index => $row) {
            $row = red_theme_readiness_exact_row($row, $layoutKeys, 'Layout catalog ' . $index);
            $layoutId = red_theme_readiness_text($row['UniqueName'], 'Layout id', false, 64);
            $layouts[$layoutId] = [
                'positions' => red_theme_readiness_integer($row['Positions'], 'Layout positions', 1, 20),
                'dimensions' => [
                    '1' => [$row['w_Pos1'], $row['vw_Pos1'], $row['vh_Pos1']],
                    '2' => [$row['w_Pos2'], $row['vw_Pos2'], $row['vh_Pos2']],
                    '3' => [$row['w_Pos3'], $row['vw_Pos3'], $row['vh_Pos3']],
                    '4' => [$row['w_Pos4'], $row['vw_Pos4'], $row['vh_Pos4']],
                ],
            ];
        }
        $customLayoutKeys = ['LayoutID', 'PublishedLabel', 'PublishedDefinition', 'PublishedHash'];
        $customLayouts = [];
        foreach ($rows['custom-layout-catalog'] as $index => $row) {
            $row = red_theme_readiness_exact_row(
                $row,
                $customLayoutKeys,
                'Custom layout catalog ' . $index
            );
            $layoutId = red_theme_readiness_text($row['LayoutID'], 'Custom layout id', false, 64);
            $label = red_theme_readiness_text($row['PublishedLabel'], 'Custom layout label', false, 120);
            if (!red_custom_layout_valid_id($layoutId) || isset($customLayouts[$layoutId])) {
                throw new InvalidArgumentException('Custom layout catalog contains an invalid or duplicate id.');
            }
            $definition = red_custom_layout_normalize_definition((string) $row['PublishedDefinition']);
            $hash = red_custom_layout_definition_hash($definition);
            if (!hash_equals((string) $row['PublishedHash'], $hash)) {
                throw new InvalidArgumentException('Custom layout catalog contains a hash mismatch.');
            }
            $customLayouts[$layoutId] = red_custom_layout_catalog_definition(
                $layoutId,
                $label,
                $definition
            );
        }
        ksort($customLayouts, SORT_STRING);

        $settingKeys = ['RecordID', 'Item', 'Content', 'Language'];
        $settings = [];
        $activationRows = [];
        foreach ($rows['region-settings'] as $index => $row) {
            $row = red_theme_readiness_exact_row($row, $settingKeys, 'Region setting ' . $index);
            if ((string) $row['Language'] === '') {
                $activationRows[] = $row;
                continue;
            }
            $item = red_theme_readiness_text($row['Item'], 'Region setting item', false, 50);
            if ((string) $row['Language'] !== 'sp'
                || !in_array(
                    $item,
                    ['Website_Footer', 'Website_Header', 'Website_Logo', 'Website_Title'],
                    true
                )
            ) {
                throw new InvalidArgumentException('Region setting inventory contains an unexpected item.');
            }
            if (isset($settings[$item])) {
                throw new InvalidArgumentException('Region setting inventory contains a duplicate item.');
            }
            $content = (string) $row['Content'];
            $settings[$item] = [
                'recordId' => red_theme_readiness_integer($row['RecordID'], 'Region setting RecordID', 1),
                'configured' => trim($content) !== '',
                'contentBytes' => strlen($content),
                'language' => red_theme_readiness_text($row['Language'], 'Region setting language', false, 2),
            ];
            if ($item === 'Website_Logo') {
                $isSupportedRaster = false;
                if (trim($content) !== '') {
                    $settings[$item]['media'] = red_theme_readiness_local_image_fact(
                        $projectRoot,
                        'images',
                        trim($content),
                        $filesystemReads
                    );
                    $isSupportedRaster = !empty($settings[$item]['media']['safe'])
                        && !empty($settings[$item]['media']['exists'])
                        && in_array($settings[$item]['media']['mime'], ['image/png', 'image/jpeg'], true);
                }
                $settings[$item]['policy'] = [
                    'id' => 'core-managed-raster-override',
                    'managedRoot' => '/images',
                    'publicRendering' => $isSupportedRaster,
                    'runtimeConnected' => true,
                    'templateFallback' => !$isSupportedRaster,
                ];
            }
        }
        $activationState = red_theme_activation_state_from_rows($activationRows, true);
        $operationalAssets = [];
        if ($forms !== []) {
            $operationalAssets = [
                'formSuccessIcon' => red_theme_readiness_local_image_fact(
                    $projectRoot,
                    'images',
                    'check.png',
                    $filesystemReads
                ),
                'formErrorIcon' => red_theme_readiness_local_image_fact(
                    $projectRoot,
                    'images',
                    'icon-error.png',
                    $filesystemReads
                ),
            ];
        }

        foreach ($articles as $articleId => &$article) {
            if ($article['component'] === 'Form') {
                if (!isset($forms[$articleId])) {
                    throw new InvalidArgumentException('Active Form Article is missing its exact child row.');
                }
                $article['componentDetail'] = $forms[$articleId];
            } elseif ($article['component'] === 'Gallery') {
                if (!isset($galleries[$articleId])) {
                    throw new InvalidArgumentException('Active Gallery Article is missing its exact child row.');
                }
                $article['componentDetail'] = $galleries[$articleId];
            } elseif ($article['component'] === 'Article') {
                $article['componentDetail'] = [
                    'type' => 'Article',
                    'trustedHtmlRequired' => $article['bodyBytes'] > 0,
                    'resourceCount' => count($article['bodyResources']),
                ];
            } else {
                $article['componentDetail'] = ['type' => $article['component']];
            }
        }
        unset($article);

        $capabilities = red_theme_readiness_template_capabilities($projectRoot, $filesystemReads);

        $incomingLinks = [];
        foreach ($navigation as $item) {
            $incomingLinks[$item['link']][] = 'menu:' . $item['recordId'];
        }
        foreach ($articles as $article) {
            if ($article['component'] === 'Article') {
                $target = $article['link'] !== '' ? $article['link'] : $article['canonicalUrl'];
                $incomingLinks[$target][] = 'article-listing:' . $article['recordId'];
            }
            if ($article['component'] === 'Gallery'
                && is_array($article['componentDetail'])
                && ($article['componentDetail']['link'] ?? '') !== ''
            ) {
                $incomingLinks[$article['componentDetail']['link']][] = 'gallery-link:' . $article['recordId'];
            }
        }

        $routes = [];
        foreach ($areas as $area) {
            if ($area['type'] !== 'section') {
                continue;
            }
            $url = strtolower($area['slug']) === 'home' ? '/' : '/' . rawurlencode($area['slug']) . '/';
            $positionKey = strtolower($area['slug']) === 'home' ? 'home' : 'section';
            $orderKey = $positionKey . 'Order';
            $components = [];
            foreach ($articles as $article) {
                $matchesArea = strcasecmp($article['sections'], $area['slug']) === 0;
                if (strtolower($area['slug']) === 'home' && $article['features']['homeFeature']) {
                    $matchesArea = true;
                }
                if (!$matchesArea
                    || $article['positions'][$positionKey] < 1
                    || !$article['renderableNow']
                ) {
                    continue;
                }
                $components[] = [
                    'position' => $article['positions'][$positionKey],
                    'order' => $article['positions'][$orderKey],
                    'articleRecordId' => $article['recordId'],
                    'alias' => $article['alias'],
                    'component' => $article['component'],
                    'subtype' => is_array($article['componentDetail'])
                        ? (string) ($article['componentDetail']['type'] ?? '')
                        : '',
                ];
            }
            usort($components, static function (array $left, array $right) {
                return [$left['position'], $left['order'], $left['articleRecordId']]
                    <=> [$right['position'], $right['order'], $right['articleRecordId']];
            });
            $previewMode = '';
            if (strtolower($area['slug']) === 'home') {
                $previewMode = 'home';
            } elseif (strtolower($area['slug']) === 'contacto') {
                $previewMode = 'contact';
            } elseif (strtolower($area['slug']) === 'administracion') {
                $previewMode = 'administration';
            }
            $routeLinks = array_values(array_unique($incomingLinks[$url] ?? []));
            $routes[] = [
                'url' => $url,
                'kind' => 'section',
                'source' => ['table' => 'RED_Sections', 'recordId' => $area['recordId'], 'slug' => $area['slug']],
                'language' => $area['language'],
                'layout' => $area['layout'],
                'queryLimit' => $area['queryLimit'],
                'features' => $area['features'],
                'components' => $components,
                'incomingLinks' => $routeLinks,
                'menuExposed' => count(array_filter($routeLinks, static function ($source) {
                    return strpos($source, 'menu:') === 0;
                })) > 0,
                'discoverable' => $routeLinks !== [],
                'fallback' => $area['layout'] === '' ? 'empty-layout-shell' : 'none',
                'previewCoverage' => $previewMode === ''
                    ? ['status' => 'missing', 'mode' => '']
                    : ['status' => 'exact', 'mode' => $previewMode],
            ];
        }

        foreach ($articles as $article) {
            $fallback = 'none';
            if ($article['layout'] === '') {
                $fallback = 'empty-layout-shell';
            } elseif (!$article['renderableNow'] || $article['positions']['page'] < 1) {
                $fallback = 'empty-selected-layout';
            }
            $routeLinks = array_values(array_unique($incomingLinks[$article['canonicalUrl']] ?? []));
            $articlePreviewMode = '';
            if ($fallback === 'none'
                && $article['recordId'] === 89196971
                && !empty($capabilities['document']['instructionsNoticeExact'])
                && !empty($capabilities['article']['trustedHtml'])
                && !empty($capabilities['article']['selectedInstructionsProvider'])
            ) {
                $articlePreviewMode = 'instructions';
            } elseif ($fallback === 'none'
                && $article['recordId'] === 966111194
                && !empty($capabilities['document']['loginNoticeExact'])
                && !empty($capabilities['form']['displayFields'])
                && empty($capabilities['form']['submits'])
                && empty($capabilities['form']['endpointInput'])
                && !empty($capabilities['form']['selectedLoginProvider'])
            ) {
                $articlePreviewMode = 'login';
            } elseif ($fallback === 'none'
                && $article['recordId'] === 459269660
                && !empty($capabilities['document']['selectedContactNoticeExact'])
                && !empty($capabilities['form']['displayFields'])
                && empty($capabilities['form']['submits'])
                && empty($capabilities['form']['endpointInput'])
                && !empty($capabilities['form']['selectedContactProvider'])
            ) {
                $articlePreviewMode = 'selected-contact';
            }
            $routes[] = [
                'url' => $article['canonicalUrl'],
                'kind' => 'article',
                'source' => ['table' => 'RED_Articles', 'recordId' => $article['recordId'], 'slug' => $article['alias']],
                'language' => $article['language'],
                'layout' => $article['layout'],
                'queryLimit' => 100,
                'features' => $article['features']['article'],
                'components' => $fallback === 'none' ? [[
                    'position' => $article['positions']['page'],
                    'order' => $article['positions']['pageOrder'],
                    'articleRecordId' => $article['recordId'],
                    'alias' => $article['alias'],
                    'component' => $article['component'],
                    'subtype' => is_array($article['componentDetail'])
                        ? (string) ($article['componentDetail']['type'] ?? '')
                        : '',
                ]] : [],
                'incomingLinks' => $routeLinks,
                'menuExposed' => count(array_filter($routeLinks, static function ($source) {
                    return strpos($source, 'menu:') === 0;
                })) > 0,
                'discoverable' => $routeLinks !== [],
                'fallback' => $fallback,
                'previewCoverage' => $articlePreviewMode === ''
                    ? ['status' => 'missing', 'mode' => '']
                    : ['status' => 'exact', 'mode' => $articlePreviewMode],
            ];
        }

        usort($routes, static function (array $left, array $right) {
            $leftPriority = $left['url'] === '/' ? 0 : ($left['kind'] === 'section' ? 1 : 2);
            $rightPriority = $right['url'] === '/' ? 0 : ($right['kind'] === 'section' ? 1 : 2);
            return [$leftPriority, $left['url']] <=> [$rightPriority, $right['url']];
        });

        $knownFallbackUrl = '/administracion/test-vimeo';
        $matchedKnownFallback = false;
        foreach ($routes as $route) {
            if ($route['url'] === $knownFallbackUrl) {
                $matchedKnownFallback = true;
                break;
            }
        }
        if (!$matchedKnownFallback) {
            $routes[] = [
                'url' => $knownFallbackUrl,
                'kind' => 'known-unmatched-canary',
                'source' => ['table' => '', 'recordId' => 0, 'slug' => 'test-vimeo'],
                'language' => 'sp',
                'layout' => '',
                'queryLimit' => 0,
                'features' => [],
                'components' => [],
                'incomingLinks' => [],
                'menuExposed' => false,
                'discoverable' => false,
                'fallback' => 'unmatched-theme-404',
                'previewCoverage' => ['status' => 'missing', 'mode' => ''],
            ];
        }

        $routeByUrl = [];
        foreach ($routes as $route) {
            if (isset($routeByUrl[$route['url']])) {
                throw new InvalidArgumentException('Theme readiness found duplicate canonical public route ' . $route['url'] . '.');
            }
            $routeByUrl[$route['url']] = $route;
        }
        $canaryExpectations = [
            'home' => ['url' => '/', 'recordId' => 13, 'layout' => 'index-1', 'fallback' => 'none'],
            'contact' => ['url' => '/contacto/', 'recordId' => 24, 'layout' => 'index-1', 'fallback' => 'none'],
            'administration' => ['url' => '/administracion/', 'recordId' => 25, 'layout' => 'index-3', 'fallback' => 'none'],
            'instructions' => [
                'url' => '/administracion/instructions',
                'recordId' => 89196971,
                'layout' => 'index-2',
                'fallback' => 'none',
            ],
            'test-vimeo' => [
                'url' => '/administracion/test-vimeo',
                'recordId' => 0,
                'layout' => '',
                'fallback' => 'unmatched-theme-404',
            ],
        ];
        $canaries = [];
        foreach ($canaryExpectations as $id => $expected) {
            $route = $routeByUrl[$expected['url']] ?? null;
            $observed = is_array($route) ? [
                'recordId' => (int) ($route['source']['recordId'] ?? 0),
                'layout' => (string) ($route['layout'] ?? ''),
                'fallback' => (string) ($route['fallback'] ?? ''),
            ] : ['recordId' => 0, 'layout' => '', 'fallback' => 'missing'];
            $canaries[] = [
                'id' => $id,
                'url' => $expected['url'],
                'valid' => $observed === [
                    'recordId' => $expected['recordId'],
                    'layout' => $expected['layout'],
                    'fallback' => $expected['fallback'],
                ],
                'observed' => $observed,
            ];
        }
        $canariesValid = count(array_filter($canaries, static function (array $canary) {
            return $canary['valid'];
        })) === count($canaries);

        $validation = red_theme_validate_manifest('starter-reference', $projectRoot);
        $manifest = is_array($validation['manifest'] ?? null) ? $validation['manifest'] : [];
        $providedLayouts = array_keys(is_array($manifest['layouts'] ?? null) ? $manifest['layouts'] : []);
        $acceptedLayouts = $providedLayouts;
        try {
            $acceptedLayouts = red_theme_layout_accepted_ids($manifest);
        } catch (Throwable $exception) {
            $acceptedLayouts = $providedLayouts;
        }
        $acceptedLayouts = array_values(array_unique(array_merge(
            $acceptedLayouts,
            array_keys($customLayouts)
        )));
        $providedComponents = array_keys(is_array($manifest['components'] ?? null) ? $manifest['components'] : []);
        sort($providedLayouts, SORT_STRING);
        sort($providedComponents, SORT_STRING);
        $assignedLayouts = [];
        $assignedComponents = [];
        foreach ($areas as $area) {
            if ($area['layout'] !== '') {
                $assignedLayouts[] = $area['layout'];
            }
        }
        foreach ($articles as $article) {
            if ($article['layout'] !== '') {
                $assignedLayouts[] = $article['layout'];
            }
            $assignedComponents[] = $article['component'];
        }
        $assignedLayouts = array_values(array_unique($assignedLayouts));
        $assignedComponents = array_values(array_unique($assignedComponents));
        sort($assignedLayouts, SORT_STRING);
        sort($assignedComponents, SORT_STRING);
        $missingLayouts = array_values(array_diff($assignedLayouts, $acceptedLayouts));
        $missingComponents = array_values(array_diff($assignedComponents, $providedComponents));
        $providedLayoutPositions = [];
        try {
            foreach (red_theme_layout_manifest_catalog($manifest) as $canonicalLayoutId => $definition) {
                $positionIds = array_values(array_map('intval', array_keys($definition['positions'] ?? [])));
                sort($positionIds, SORT_NUMERIC);
                $providedLayoutPositions[$canonicalLayoutId] = $positionIds;
            }
            ksort($providedLayoutPositions, SORT_STRING);
        } catch (Throwable $exception) {
            $providedLayoutPositions = [];
        }
        foreach ($customLayouts as $customLayoutId => $definition) {
            $positionIds = array_values(array_map('intval', array_keys($definition['positions'] ?? [])));
            sort($positionIds, SORT_NUMERIC);
            $providedLayoutPositions[$customLayoutId] = $positionIds;
        }
        ksort($providedLayoutPositions, SORT_STRING);
        $requiredLayoutPositions = [];
        $missingLayoutPositionsByKey = [];
        $missingLayoutPositionRoutes = [];
        foreach ($routes as $route) {
            $assignedLayoutId = (string) ($route['layout'] ?? '');
            if ($assignedLayoutId === '' || (string) ($route['fallback'] ?? '') !== 'none') {
                continue;
            }
            $resolvedLayoutId = null;
            if (isset($customLayouts[$assignedLayoutId])) {
                $resolvedLayoutId = $assignedLayoutId;
            } else {
                try {
                $resolvedLayoutId = red_theme_layout_resolve_id($manifest, $assignedLayoutId);
                } catch (Throwable $exception) {
                    $resolvedLayoutId = null;
                }
            }
            foreach (($route['components'] ?? []) as $component) {
                $positionId = is_array($component) ? (int) ($component['position'] ?? 0) : 0;
                if ($positionId < 1) {
                    continue;
                }
                if (!isset($requiredLayoutPositions[$assignedLayoutId])) {
                    $requiredLayoutPositions[$assignedLayoutId] = [];
                }
                $requiredLayoutPositions[$assignedLayoutId][$positionId] = true;
                if (is_string($resolvedLayoutId)
                    && $resolvedLayoutId !== ''
                    && !in_array($positionId, $providedLayoutPositions[$resolvedLayoutId] ?? [], true)
                ) {
                    $key = $assignedLayoutId . "\0" . $resolvedLayoutId . "\0" . $positionId;
                    $missingLayoutPositionsByKey[$key] = [
                        'layoutId' => $assignedLayoutId,
                        'resolvedLayoutId' => $resolvedLayoutId,
                        'positionId' => $positionId,
                    ];
                    $missingLayoutPositionRoutes[(string) ($route['url'] ?? '')] = true;
                }
            }
        }
        foreach ($requiredLayoutPositions as &$positionIds) {
            $positionIds = array_values(array_map('intval', array_keys($positionIds)));
            sort($positionIds, SORT_NUMERIC);
        }
        unset($positionIds);
        ksort($requiredLayoutPositions, SORT_STRING);
        ksort($missingLayoutPositionsByKey, SORT_STRING);
        $missingLayoutPositions = array_values($missingLayoutPositionsByKey);
        $manifestCoverageCompatible = $missingLayouts === []
            && $missingLayoutPositions === []
            && $missingComponents === [];

        $previewCovered = 0;
        $renderableMissing = 0;
        $shellOnly = 0;
        foreach ($routes as $route) {
            if ($route['previewCoverage']['status'] === 'exact') {
                $previewCovered++;
            } elseif ($route['fallback'] === 'none') {
                $renderableMissing++;
            } else {
                $shellOnly++;
            }
        }

        $productionValidation = isset($validation['path']) && is_string($validation['path'])
            ? red_theme_standard_production_validation(
                $manifest,
                $validation['path']
            )
            : ['valid' => false, 'errors' => ['Theme path is invalid.'], 'files' => []];
        $runtime = red_theme_runtime_bootstrap(
            'starter-reference',
            $projectRoot,
            'legacy-bootstrap',
            true
        );
        $standardRuntimeReady = ($runtime['themeId'] ?? '') === 'starter-reference'
            && ($runtime['themeType'] ?? '') === 'standard'
            && !empty($runtime['standardExecutionEnabled'])
            && empty($runtime['resolution']['usedFallback'])
            && is_object($runtime['adapter'] ?? null)
            && get_class($runtime['adapter']) === 'RedStandardThemeAdapter';
        $activationCapabilities = red_theme_readiness_activation_capabilities(
            $projectRoot,
            $filesystemReads
        );
        $activationStatePackagesValid = false;
        if (!empty($activationState['persisted'])) {
            try {
                red_theme_activation_validate_candidate($activationState['activeThemeId'], $projectRoot);
                red_theme_activation_validate_candidate($activationState['previousThemeId'], $projectRoot);
                $activationStatePackagesValid = true;
            } catch (Throwable $exception) {
                $activationStatePackagesValid = false;
            }
        }
        $operationBoundaryReady = !empty($capabilities['form']['coreOperationBoundary']);
        $contactOperationReady = !empty($capabilities['form']['contactOperationAdapter']);
        $loginOperationReady = !empty($capabilities['form']['loginOperationAdapter']);
        $fallbackContractReady = !empty($capabilities['routeFallback']['coreContract'])
            && !empty($capabilities['routeFallback']['livePublicConnection']);
        $regionContextReady = !empty($capabilities['regionContext']['coreProvider'])
            && !empty($capabilities['regionContext']['allCurrentRoutes'])
            && !empty($capabilities['regionContext']['inputFree'])
            && empty($capabilities['regionContext']['productionConnected']);
        $operationGap = null;
        if (!$operationBoundaryReady) {
            $operationGap = [
                'id' => 'operational-form-boundary',
                'severity' => 'blocking',
                'routes' => ['/contacto/', '/contacto/contact', '/administracion/', '/administracion/login'],
                'reason' => 'The portable Form view is display-only and no CMS-owned Contact/Login operation boundary exists.',
            ];
        } elseif (!$contactOperationReady) {
            $operationGap = [
                'id' => 'operational-contact-integration',
                'severity' => 'blocking',
                'routes' => ['/contacto/', '/contacto/contact'],
                'reason' => 'The CMS-owned operation contract is dependency-tested, but the live Contact endpoint does not consume it.',
            ];
        } elseif (!$loginOperationReady) {
            $operationGap = [
                'id' => 'operational-login-integration',
                'severity' => 'blocking',
                'routes' => ['/administracion/', '/administracion/login'],
                'reason' => 'The live Contact endpoint consumes the CMS-owned operation seam, but Login and the portable Form view remain unconnected.',
            ];
        }
        $gaps = [];
        if ($operationGap !== null) {
            $gaps[] = $operationGap;
        }
        if (!$fallbackContractReady) {
            $gaps[] = [
                'id' => 'empty-layout-route-policy',
                'severity' => 'blocking',
                'routes' => ['/administracion/admin-video', '/banner-test'],
                'reason' => 'Two active article aliases have no Layout and currently resolve to an HTTP 200 shell-only fallback.',
            ];
            $gaps[] = [
                'id' => 'unmatched-route-policy',
                'severity' => 'blocking',
                'routes' => [$knownFallbackUrl],
                'reason' => 'The documented unmatched test-vimeo canary currently returns the legacy shell and has no portable fallback contract.',
            ];
        }
        if (!$regionContextReady) {
            $gaps[] = [
                'id' => 'generic-region-settings-provider',
                'severity' => 'blocking',
                'routes' => array_map(static function (array $route) { return $route['url']; }, $routes),
                'reason' => 'Contact, Home, Administration, Instructions, Login, and selected Contact reconstruct region settings independently; there is no generic production document/navigation/region provider.',
            ];
        }
        if (empty($capabilities['document']['homeNoticeExact'])) {
            $gaps[] = [
                'id' => 'home-preview-notice-copy',
                'severity' => 'nonblocking-preview-debt',
                'routes' => ['/'],
                'reason' => 'The starter document template has an exact Contact notice but no exact Home-mode notice, so Home falls through to fixture copy.',
            ];
        }
        if (empty($capabilities['gallery']['videoContract'])) {
            array_splice($gaps, $operationGap === null ? 0 : 1, 0, [[
                'id' => 'gallery-video-contract',
                'severity' => 'blocking',
                'routes' => ['/administracion/'],
                'reason' => 'The portable Gallery view cannot represent the live YouTube Video subtype safely.',
            ]]);
        }
        if (!$canariesValid) {
            $gaps[] = [
                'id' => 'live-canary-drift',
                'severity' => 'blocking',
                'routes' => array_values(array_map(static function (array $canary) {
                    return $canary['url'];
                }, array_filter($canaries, static function (array $canary) {
                    return !$canary['valid'];
                }))),
                'reason' => 'One or more fixed live route canaries drifted from the audited readiness contract.',
            ];
        }
        if ($missingLayoutPositions !== []) {
            $gaps[] = [
                'id' => 'manifest-layout-position-coverage',
                'severity' => 'blocking-activation',
                'routes' => array_values(array_filter(array_keys($missingLayoutPositionRoutes))),
                'reason' => 'The theme omits one or more numbered layout positions currently used by live content.',
            ];
        }
        $logoMedia = $settings['Website_Logo']['media'] ?? null;
        if (is_array($logoMedia)
            && (empty($logoMedia['safe']) || empty($logoMedia['exists']))
        ) {
            $gaps[] = [
                'id' => 'configured-logo-media-missing',
                'severity' => 'blocking-data-decision',
                'routes' => array_map(static function (array $route) { return $route['url']; }, $routes),
                'reason' => 'Website_Logo is configured, but its CMS-managed /images asset is missing or unsafe.',
            ];
        }
        $missingArticleMediaRoutes = [];
        foreach ($articles as $article) {
            if (($article['bodyMedia']['missingOrUnsafe'] ?? 0) > 0) {
                $missingArticleMediaRoutes[] = $article['canonicalUrl'];
            }
        }
        if ($missingArticleMediaRoutes !== []) {
            $gaps[] = [
                'id' => 'article-body-media-missing',
                'severity' => 'blocking-data-decision',
                'routes' => $missingArticleMediaRoutes,
                'reason' => 'One or more trusted Article body media references are missing or unsafe.',
            ];
        }

        $allRouteUrls = array_values(array_map(static function (array $route) {
            return $route['url'];
        }, $routes));
        if (empty($activationState['persisted'])) {
            $gaps[] = [
                'id' => 'active-theme-state',
                'severity' => 'blocking-activation',
                'routes' => [],
                'reason' => 'The two global active/previous theme rows have not been persisted.',
            ];
        } elseif (!$activationStatePackagesValid) {
            $gaps[] = [
                'id' => 'active-theme-package-reference',
                'severity' => 'blocking-activation',
                'routes' => [],
                'reason' => 'The persisted active or previous theme id does not resolve to a production-supported package.',
            ];
        }
        if (empty($productionValidation['valid'])) {
            $gaps[] = [
                'id' => 'standard-production-contract',
                'severity' => 'blocking-activation',
                'routes' => $allRouteUrls,
                'reason' => 'The standard package does not pass its explicit production template and asset contract.',
            ];
        }
        if (!$standardRuntimeReady) {
            $gaps[] = [
                'id' => 'standard-runtime-execution',
                'severity' => 'blocking-activation',
                'routes' => $allRouteUrls,
                'reason' => 'The guarded production runtime cannot initialize the standard package without fallback.',
            ];
        }
        if (in_array(false, $activationCapabilities, true)) {
            $gaps[] = [
                'id' => 'theme-activation-boundary',
                'severity' => 'blocking-activation',
                'routes' => [],
                'reason' => 'Persisted state, Webmaster controls, public selection, and legacy recovery are not all connected.',
            ];
        }

        $activationReady = $gaps === []
            && !empty($validation['valid'])
            && $manifestCoverageCompatible
            && !empty($productionValidation['valid'])
            && $standardRuntimeReady
            && !empty($activationState['persisted'])
            && $activationStatePackagesValid
            && !in_array(false, $activationCapabilities, true);

        return [
            'schemaVersion' => 2,
            'mode' => 'read-only-activation-readiness',
            'theme' => [
                'id' => 'starter-reference',
                'version' => (string) ($manifest['version'] ?? ''),
                'manifestValid' => !empty($validation['valid']),
            ],
            'activationReady' => $activationReady,
            'manifestIdCoverage' => [
                'compatible' => $manifestCoverageCompatible,
                'assignedLayouts' => $assignedLayouts,
                'assignedComponents' => $assignedComponents,
                'providedLayouts' => $providedLayouts,
                'coreCustomLayouts' => array_keys($customLayouts),
                'acceptedLayouts' => $acceptedLayouts,
                'requiredLayoutPositions' => $requiredLayoutPositions,
                'providedLayoutPositions' => $providedLayoutPositions,
                'providedComponents' => $providedComponents,
                'missingLayouts' => $missingLayouts,
                'missingLayoutPositions' => $missingLayoutPositions,
                'missingComponents' => $missingComponents,
            ],
            'runtime' => [
                'requestedThemeId' => 'starter-reference',
                'resolvedThemeId' => (string) ($runtime['themeId'] ?? ''),
                'usedFallback' => !empty($runtime['resolution']['usedFallback']),
                'standardRuntimeExecution' => $standardRuntimeReady,
                'legacyRecovery' => !empty($activationCapabilities['legacyRecovery']),
            ],
            'activation' => [
                'state' => [
                    'activeThemeId' => (string) $activationState['activeThemeId'],
                    'previousThemeId' => (string) $activationState['previousThemeId'],
                    'persisted' => !empty($activationState['persisted']),
                ],
                'statePackagesValid' => $activationStatePackagesValid,
                'productionContractValid' => !empty($productionValidation['valid']),
                'productionFileCount' => count((array) ($productionValidation['files'] ?? [])),
                'capabilities' => $activationCapabilities,
            ],
            'source' => [
                'queryIds' => $queryIds,
                'rowCounts' => array_map('count', $rows),
                'areas' => $areas,
                'navigation' => $navigation,
                'articles' => array_values($articles),
                'forms' => array_values($forms),
                'galleries' => array_values($galleries),
                'layoutCatalog' => $layouts,
                'customLayoutCatalog' => $customLayouts,
                'regionSettings' => $settings,
                'themeState' => [
                    'activeThemeId' => (string) $activationState['activeThemeId'],
                    'previousThemeId' => (string) $activationState['previousThemeId'],
                    'persisted' => !empty($activationState['persisted']),
                ],
                'operationalAssets' => $operationalAssets,
            ],
            'routes' => $routes,
            'canaries' => $canaries,
            'canariesValid' => $canariesValid,
            'routeSummary' => [
                'total' => count($routes),
                'exactPreviewCovered' => $previewCovered,
                'renderableWithoutPreview' => $renderableMissing,
                'shellOnlyOrUnmatched' => $shellOnly,
                'menuExposed' => count(array_filter($routes, static function (array $route) {
                    return $route['menuExposed'];
                })),
                'discoverable' => count(array_filter($routes, static function (array $route) {
                    return $route['discoverable'];
                })),
            ],
            'portableCapabilities' => $capabilities,
            'gaps' => $gaps,
            'recommendedSequence' => [
                'Use the Webmaster Themes controls for activation and rollback while preserving the template-fallback logo policy and hard legacy recovery path.',
            ],
            'scope' => red_theme_readiness_scope($databaseReads, $filesystemReads),
        ];
    }
}

if (!function_exists('red_theme_readiness_live_report')) {
    function red_theme_readiness_live_report($connection, $projectRoot = null)
    {
        $read = red_theme_readiness_read_rows($connection);
        $report = red_theme_readiness_report_from_rows($read['rows'], $projectRoot, 8);
        if ($report['scope']['databaseReads'] !== $read['scope']['databaseReads']) {
            throw new RuntimeException('Theme readiness database-read scope changed during report preparation.');
        }
        return $report;
    }
}
