<?php
require_once __DIR__ . '/custom_layout_helpers.php';
require_once __DIR__ . '/seo_metadata_helpers.php';
require_once __DIR__ . '/site_logo_helpers.php';

if (!function_exists('red_public_language')) {
    function red_public_language($default = 'sp')
    {
        return defined('language') ? (string) constant('language') : $default;
    }

    function red_public_route_value($name, $default = '')
    {
        return defined($name) ? (string) constant($name) : $default;
    }

    function red_public_html($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function red_public_display_text($value)
    {
        return red_public_html(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    function red_public_plain_text($value)
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    function red_public_bind_values($stmt, $types, array &$values)
    {
        $refs = [$types];
        foreach ($values as $key => &$value) {
            $refs[] = &$value;
        }
        return call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    function red_public_fetch_all($connection, $sql, $types, array $values, $logMessage)
    {
        $stmt = mysqli_prepare($connection, $sql);
        if (!$stmt) {
            error_log($logMessage . ' prepare failed: ' . mysqli_error($connection));
            return [];
        }

        if ($types !== '') {
            red_public_bind_values($stmt, $types, $values);
        }

        if (!mysqli_stmt_execute($stmt)) {
            error_log($logMessage . ' execute failed: ' . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return [];
        }

        $result = mysqli_stmt_get_result($stmt);
        $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($stmt);
        return $rows;
    }

    function red_public_fetch_one($connection, $sql, $types, array $values, $logMessage)
    {
        $rows = red_public_fetch_all($connection, $sql, $types, $values, $logMessage);
        return $rows[0] ?? null;
    }

    function red_public_area_config($table)
    {
        $tables = [
            'Sections' => ['table' => 'RED_Sections', 'column' => 'Sections', 'constant' => 'section'],
            'Categories' => ['table' => 'RED_Categories', 'column' => 'Categories', 'constant' => 'category'],
            'SubCategories' => ['table' => 'RED_SubCategories', 'column' => 'SubCategories', 'constant' => 'subcategory'],
        ];
        return $tables[$table] ?? null;
    }

    function red_public_allowed_columns($table)
    {
        $columns = [
            'RED_Advanced' => ['Item', 'Content', 'Language'],
            'RED_Articles' => ['RecordID', 'Title', 'Layout', 'ShortDesc', 'Tags', 'Sections', 'Categories', 'SubCategories', 'Alias', 'Language', 'Active', 'BigPict', 'SmallPict', 'SmallPict2'],
            'RED_Sections' => ['RecordID', 'Sections', 'Title', 'Layout', 'QueryLimit', 'Description', 'Tags', 'Features', 'Language', 'Active'],
            'RED_Categories' => ['RecordID', 'Categories', 'Title', 'Layout', 'QueryLimit', 'Description', 'Tags', 'Features', 'Language', 'Active'],
            'RED_SubCategories' => ['RecordID', 'SubCategories', 'Title', 'Layout', 'QueryLimit', 'Description', 'Tags', 'Features', 'Language', 'Active'],
        ];
        return $columns[$table] ?? [];
    }

    function red_public_columns_sql($table, array $columns)
    {
        $allowed = red_public_allowed_columns($table);
        $selected = [];
        foreach ($columns as $column) {
            if (in_array($column, $allowed, true)) {
                $selected[] = '`' . $column . '`';
            }
        }
        return $selected ? implode(', ', $selected) : '';
    }

    function red_public_area_value($table)
    {
        $config = red_public_area_config($table);
        if (!$config) {
            return '';
        }

        if ($table === 'Sections' && (int) red_public_route_value('countpage', 0) === 2) {
            return 'Home';
        }

        return red_public_route_value($config['constant']);
    }

    function red_public_area_row($connection, $table, array $columns)
    {
        $config = red_public_area_config($table);
        if (!$config) {
            return null;
        }

        $columnSql = red_public_columns_sql($config['table'], $columns);
        if ($columnSql === '') {
            return null;
        }

        $value = red_public_area_value($table);
        if ($value === '') {
            return null;
        }

        $language = red_public_language();
        $types = 'ss';
        $values = [$language, $value];

        if ($table === 'Categories') {
            $qualifiedColumns = preg_replace('/`([^`]+)`/', 'area_row.`$1`', $columnSql);
            $sql = 'SELECT ' . $qualifiedColumns . ' FROM RED_Categories AS area_row ' .
                'LEFT JOIN RED_Sections AS parent_section ' .
                'ON parent_section.RecordID=area_row.SectionRecordID ' .
                'AND parent_section.Language=area_row.Language ' .
                'WHERE area_row.Active=\'Y\' AND area_row.Language=? AND area_row.Categories=? ' .
                'AND (area_row.SectionRecordID IS NULL OR (' .
                'parent_section.Active=\'Y\' AND parent_section.Sections=?)) LIMIT 1';
            $types .= 's';
            $values[] = red_public_route_value('section');
        } elseif ($table === 'SubCategories') {
            $qualifiedColumns = preg_replace('/`([^`]+)`/', 'area_row.`$1`', $columnSql);
            $sql = 'SELECT ' . $qualifiedColumns . ' FROM RED_SubCategories AS area_row ' .
                'LEFT JOIN RED_Categories AS parent_category ' .
                'ON parent_category.RecordID=area_row.CategoryRecordID ' .
                'AND parent_category.Language=area_row.Language ' .
                'LEFT JOIN RED_Sections AS parent_section ' .
                'ON parent_section.RecordID=parent_category.SectionRecordID ' .
                'AND parent_section.Language=parent_category.Language ' .
                'WHERE area_row.Active=\'Y\' AND area_row.Language=? AND area_row.SubCategories=? ' .
                'AND (area_row.CategoryRecordID IS NULL OR (' .
                'parent_category.Active=\'Y\' AND parent_category.Categories=? ' .
                'AND (parent_category.SectionRecordID IS NULL OR (' .
                'parent_section.Active=\'Y\' AND parent_section.Sections=?)))) LIMIT 1';
            $types .= 'ss';
            $values[] = red_public_route_value('category');
            $values[] = red_public_route_value('section');
        } else {
            $sql = 'SELECT ' . $columnSql . ' FROM `' . $config['table'] . '` ' .
                'WHERE Active=\'Y\' AND Language=? AND `' . $config['column'] . '`=? LIMIT 1';
        }

        return red_public_fetch_one(
            $connection,
            $sql,
            $types,
            $values,
            'Public area row lookup failed'
        );
    }

    function red_public_article_route_row($connection, array $columns)
    {
        $columnSql = red_public_columns_sql('RED_Articles', $columns);
        $article = red_public_route_value('article');
        if ($columnSql === '' || $article === '') {
            return null;
        }

        $countPage = (int) red_public_route_value('countpage', 0);
        $qualifiedColumns = preg_replace('/`([^`]+)`/', 'article_row.`$1`', $columnSql);
        $from = 'RED_Articles AS article_row';
        $joins = '';
        $where = ['article_row.Active=\'Y\'', 'article_row.Language=?'];
        $types = 's';
        $values = [red_public_language()];

        if ($countPage === 2) {
            $where[] = 'article_row.Sections=?';
            $types .= 's';
            $values[] = 'Home';
        } elseif ($countPage >= 3) {
            $where[] = 'article_row.Sections=?';
            $types .= 's';
            $values[] = red_public_route_value('section');
        }

        if ($countPage >= 4) {
            $joins .= ' LEFT JOIN RED_Categories AS route_category ' .
                'ON route_category.Categories=article_row.Categories ' .
                'AND route_category.Language=article_row.Language ' .
                'LEFT JOIN RED_Sections AS route_category_section ' .
                'ON route_category_section.RecordID=route_category.SectionRecordID ' .
                'AND route_category_section.Language=route_category.Language';
            $where[] = 'article_row.Categories=?';
            $where[] = '(route_category.RecordID IS NULL OR route_category.SectionRecordID IS NULL OR (' .
                'route_category.Active=\'Y\' AND route_category_section.Active=\'Y\' ' .
                'AND route_category_section.Sections=article_row.Sections))';
            $types .= 's';
            $values[] = red_public_route_value('category');
        }

        if ($countPage >= 5) {
            $joins .= ' LEFT JOIN RED_SubCategories AS route_subcategory ' .
                'ON route_subcategory.SubCategories=article_row.SubCategories ' .
                'AND route_subcategory.Language=article_row.Language ' .
                'LEFT JOIN RED_Categories AS route_subcategory_parent ' .
                'ON route_subcategory_parent.RecordID=route_subcategory.CategoryRecordID ' .
                'AND route_subcategory_parent.Language=route_subcategory.Language ' .
                'LEFT JOIN RED_Sections AS route_subcategory_section ' .
                'ON route_subcategory_section.RecordID=route_subcategory_parent.SectionRecordID ' .
                'AND route_subcategory_section.Language=route_subcategory_parent.Language';
            $where[] = 'article_row.SubCategories=?';
            $where[] = '(route_subcategory.RecordID IS NULL OR route_subcategory.CategoryRecordID IS NULL OR (' .
                'route_subcategory.Active=\'Y\' AND route_subcategory_parent.Active=\'Y\' ' .
                'AND route_subcategory_section.Active=\'Y\' ' .
                'AND route_subcategory_parent.Categories=article_row.Categories ' .
                'AND route_subcategory_section.Sections=article_row.Sections))';
            $types .= 's';
            $values[] = red_public_route_value('subcategory');
        }

        $where[] = 'article_row.Alias=?';
        $types .= 's';
        $values[] = $article;

        $sql = 'SELECT ' . $qualifiedColumns . ' FROM ' . $from . $joins .
            ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1';

        return red_public_fetch_one(
            $connection,
            $sql,
            $types,
            $values,
            'Public article route lookup failed'
        );
    }

    function red_public_advanced_item($connection, $item)
    {
        return red_public_fetch_one(
            $connection,
            'SELECT Content FROM RED_Advanced WHERE Language=? AND Item=? LIMIT 1',
            'ss',
            [red_public_language(), (string) $item],
            'Public advanced item lookup failed'
        );
    }

    function red_public_advanced_items($connection, array $items)
    {
        if (!$items) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($items), '?'));
        $types = str_repeat('s', count($items) + 1);
        $values = array_merge([red_public_language()], array_values($items));

        $rows = red_public_fetch_all(
            $connection,
            'SELECT Item, Content FROM RED_Advanced WHERE Language=? AND Item IN(' . $placeholders . ')',
            $types,
            $values,
            'Public advanced items lookup failed'
        );

        $byItem = [];
        foreach ($rows as $row) {
            $byItem[$row['Item']] = $row['Content'];
        }
        return $byItem;
    }

    function red_public_article_position_config($positionColumn)
    {
        $positions = [
            'HomePosition' => 'HomePositionOrder',
            'SectionPosition' => 'SectionPositionOrder',
            'CategoryPosition' => 'CategoryPositionOrder',
            'SubCategoryPosition' => 'SubCategoryPositionOrder',
            'PagePosition' => 'PagePositionOrder',
        ];

        if (!isset($positions[$positionColumn])) {
            return null;
        }

        return [
            'position' => $positionColumn,
            'order' => $positions[$positionColumn],
        ];
    }

    function red_public_add_article_route_filter(array &$where, &$types, array &$values)
    {
        $countPage = (int) red_public_route_value('countpage', 0);
        $section = red_public_route_value('section');
        $category = red_public_route_value('category');
        $subcategory = red_public_route_value('subcategory');
        $article = red_public_route_value('article');

        switch ($countPage) {
            case 2:
                if ($article === '') {
                    $where[] = "(Sections=? OR HomeFeature='Y')";
                    $types .= 's';
                    $values[] = 'Home';
                } else {
                    $where[] = '((Sections=? AND Alias=?) OR Article LIKE ?)';
                    $types .= 'sss';
                    $values[] = 'Home';
                    $values[] = $article;
                    $values[] = '%' . $article . '%';
                }
                break;

            case 3:
                if ($article === '') {
                    $where[] = 'Sections=?';
                    $types .= 's';
                    $values[] = $section;
                } else {
                    $where[] = '((Sections=? AND Alias=?) OR (Sections=? AND Article LIKE ?))';
                    $types .= 'ssss';
                    $values[] = $section;
                    $values[] = $article;
                    $values[] = $section;
                    $values[] = '%' . $article . '%';
                }
                break;

            case 4:
                if ($article === '') {
                    $where[] = 'Sections=? AND Categories=?';
                    $types .= 'ss';
                    $values[] = $section;
                    $values[] = $category;
                } else {
                    $where[] = '((Sections=? AND Categories=? AND Alias=?) OR (Sections=? AND Categories=? AND Article LIKE ?))';
                    $types .= 'ssssss';
                    $values[] = $section;
                    $values[] = $category;
                    $values[] = $article;
                    $values[] = $section;
                    $values[] = $category;
                    $values[] = '%' . $article . '%';
                }
                break;

            case 5:
            case 6:
                if ($article === '') {
                    $where[] = 'Sections=? AND Categories=? AND SubCategories=?';
                    $types .= 'sss';
                    $values[] = $section;
                    $values[] = $category;
                    $values[] = $subcategory;
                } else {
                    $where[] = '((Sections=? AND Categories=? AND SubCategories=? AND Alias=?) OR (Sections=? AND Categories=? AND SubCategories=? AND Article LIKE ?))';
                    $types .= 'ssssssss';
                    $values[] = $section;
                    $values[] = $category;
                    $values[] = $subcategory;
                    $values[] = $article;
                    $values[] = $section;
                    $values[] = $category;
                    $values[] = $subcategory;
                    $values[] = '%' . $article . '%';
                }
                break;
        }
    }

    function red_public_content_articles($connection, $positionColumn, $position, $limit, $requireStarted)
    {
        $config = red_public_article_position_config($positionColumn);
        $limit = (int) $limit;

        if (!$config || $limit < 1) {
            return [];
        }

        $where = ["Active='Y'", 'Language=?', '`' . $config['position'] . '`=?'];
        $types = 'ss';
        $values = [red_public_language(), (string) $position];

        if ($requireStarted) {
            $where[] = 'StartDate <= NOW()';
        }

        red_public_add_article_route_filter($where, $types, $values);

        $sql = 'SELECT RecordID, Alias, Title, Component, ExpDate, SmallPict, `' . $config['order'] . '` ' .
            'FROM RED_Articles WHERE ' . implode(' AND ', $where) . ' ' .
            'ORDER BY `' . $config['order'] . '` ASC, StartDate DESC LIMIT ?';
        $types .= 'i';
        $values[] = $limit;

        return red_public_fetch_all(
            $connection,
            $sql,
            $types,
            $values,
            'Public content article list failed'
        );
    }

    function red_public_menu_rows($connection)
    {
        return red_public_fetch_all(
            $connection,
            'SELECT RecordID, Parent, RootOrder, Title, Label, Link, NewWindow, MenuOrder FROM RED_Menu ' .
            "WHERE Language=? AND Active='Y' ORDER BY MenuOrder ASC",
            's',
            [red_public_language()],
            'Public main menu lookup failed'
        );
    }

    function red_public_main_menu_root($connection)
    {
        return red_public_fetch_one(
            $connection,
            'SELECT RecordID, Title FROM RED_Menu WHERE Language=? AND RootOrder=? AND Active=\'Y\' ORDER BY MenuOrder ASC LIMIT 1',
            'ss',
            [red_public_language(), '1'],
            'Public main menu root lookup failed'
        );
    }

    function red_public_breadcrumb_title($connection, $table, $alias)
    {
        $alias = (string) $alias;
        if ($alias === '') {
            return '';
        }

        if ($table === 'Articles') {
            $row = red_public_fetch_one(
                $connection,
                "SELECT Title FROM RED_Articles WHERE Active='Y' AND Language=? AND Alias=? LIMIT 1",
                'ss',
                [red_public_language(), $alias],
                'Public breadcrumb article title lookup failed'
            );
            return $row['Title'] ?? '';
        }

        $config = red_public_area_config($table);
        if (!$config) {
            return '';
        }

        $row = red_public_fetch_one(
            $connection,
            'SELECT Title FROM `' . $config['table'] . "` WHERE Active='Y' AND Language=? AND `" . $config['column'] . '`=? LIMIT 1',
            'ss',
            [red_public_language(), $alias],
            'Public breadcrumb area title lookup failed'
        );
        return $row['Title'] ?? '';
    }

    function red_public_feature_column_config($featureColumn)
    {
        $columns = [
            'HomeFeatures' => 'HomeFeatures_Order',
            'SectionFeatures' => 'SectionFeatures_Order',
            'CategoryFeatures' => 'CategoryFeatures_Order',
            'SubCategoryFeatures' => 'SubCategoryFeatures_Order',
            'ArticleFeatures' => null,
        ];

        if (!array_key_exists($featureColumn, $columns)) {
            return null;
        }

        return [
            'feature' => $featureColumn,
            'order' => $columns[$featureColumn],
        ];
    }

    function red_public_feature_enabled($connection, $table, $featureName)
    {
        $row = red_public_area_row($connection, $table, ['Features']);
        if (!$row || !isset($row['Features'])) {
            return false;
        }

        $features = array_map('trim', explode(',', (string) $row['Features']));
        return in_array((string) $featureName, $features, true);
    }

    function red_public_feature_articles($connection, $featureColumn, $featureName, $limit = 5)
    {
        $config = red_public_feature_column_config($featureColumn);
        $limit = (int) $limit;
        if (!$config || !$config['order'] || $limit < 1) {
            return [];
        }

        $sql = 'SELECT RecordID, Title, Alias, Sections, Categories, SubCategories, LongDesc, SliderDesc, ' .
            'Link, NewWindow, BigPict, ExpDate, `' . $config['feature'] . '`, `' . $config['order'] . '` ' .
            "FROM RED_Articles WHERE Active='Y' AND Language=? AND `" . $config['feature'] . '` LIKE ? ' .
            'ORDER BY `' . $config['order'] . '` ASC LIMIT ?';

        return red_public_fetch_all(
            $connection,
            $sql,
            'ssi',
            [red_public_language(), '%' . (string) $featureName . '%', $limit],
            'Public feature article list failed'
        );
    }

    function red_public_record_id($value)
    {
        return max(0, (int) $value);
    }

    function red_public_js_identifier($value, $fallback = 'content')
    {
        $identifier = preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $value);
        $identifier = trim($identifier, '_');
        if ($identifier === '') {
            $identifier = $fallback;
        }
        if (!preg_match('/^[A-Za-z_]/', $identifier)) {
            $identifier = $fallback . '_' . $identifier;
        }
        return $identifier;
    }

    function red_public_article_render_rows($connection, $recordId, $orderByUpdated = false)
    {
        $recordId = red_public_record_id($recordId);
        if ($recordId <= 0) {
            return [];
        }

        $sql = 'SELECT RecordID, Alias, Title, ShortDesc, LongDesc, Link, NewWindow, Component, ' .
            'Sections, Categories, SubCategories, SmallPict, SmallPictAlign, SmallPict2, SmallPictAlign2 ' .
            'FROM RED_Articles WHERE RecordID=?';
        if ($orderByUpdated) {
            $sql .= ' ORDER BY Updated ASC';
        }

        return red_public_fetch_all(
            $connection,
            $sql,
            'i',
            [$recordId],
            'Public article render lookup failed'
        );
    }

    function red_public_article_render_row($connection, $recordId)
    {
        $rows = red_public_article_render_rows($connection, $recordId);
        return $rows[0] ?? null;
    }

    function red_public_gallery_rows($connection, $articleRecordId)
    {
        $articleRecordId = red_public_record_id($articleRecordId);
        if ($articleRecordId <= 0) {
            return [];
        }

        return red_public_fetch_all(
            $connection,
            'SELECT RecordID, RefID, Alias, Title, GalleryType, ShortDesc, LongDesc, Link, NewWindow ' .
            'FROM RED_C_Gallery WHERE RefID=?',
            's',
            [(string) $articleRecordId],
            'Public gallery render lookup failed'
        );
    }

    function red_public_form_rows($connection, $articleRecordId)
    {
        $articleRecordId = red_public_record_id($articleRecordId);
        if ($articleRecordId <= 0) {
            return [];
        }

        return red_public_fetch_all(
            $connection,
            'SELECT RecordID, RefID, Alias, Title, FormType, LongDesc FROM RED_C_Form WHERE RefID=?',
            's',
            [(string) $articleRecordId],
            'Public form render lookup failed'
        );
    }

    function red_public_layout_dimensions($connection, $layout, $position)
    {
        global $redThemeRuntime;

        $standardLayout = null;
        $customLayout = red_custom_layout_published_definition($connection, (string) $layout);
        if ($customLayout !== null) {
            $span = 12;
            foreach (($customLayout['grid']['rows'] ?? []) as $row) {
                foreach (($row['columns'] ?? []) as $column) {
                    if ((int) ($column['position'] ?? 0) === (int) $position) {
                        $span = max(1, min(12, (int) ($column['span'] ?? 12)));
                        break 2;
                    }
                }
            }
            $width = max(1, (int) round(1200 * ($span / 12)));

            return [
                'Width' => $width,
                'WidthDivisor' => 1,
                'Height' => 0,
                'vWidth' => $width,
                'vHeight' => max(1, (int) round($width * (9 / 16))),
            ];
        }

        if (is_array($redThemeRuntime ?? null)
            && ($redThemeRuntime['themeType'] ?? '') === 'standard'
            && is_array($redThemeRuntime['manifest'] ?? null)
            && function_exists('red_theme_layout_definition')
        ) {
            try {
                $standardLayout = red_theme_layout_definition(
                    $redThemeRuntime['manifest'],
                    (string) $layout
                );
            } catch (Throwable $exception) {
                $standardLayout = null;
            }
        }

        $row = red_public_fetch_one(
            $connection,
            'SELECT w_Pos1, w_div_Pos1, vw_Pos1, vh_Pos1, ' .
            'w_Pos2, w_div_Pos2, vw_Pos2, vh_Pos2, ' .
            'w_Pos3, w_div_Pos3, vw_Pos3, vh_Pos3, ' .
            'w_Pos4, w_div_Pos4, vw_Pos4, vh_Pos4 ' .
            'FROM RED_Layouts WHERE UniqueName=? LIMIT 1',
            's',
            [(string) $layout],
            'Public layout dimension lookup failed'
        );

        $slot = in_array((string) $position, ['2', '3', '4'], true) ? (string) $position : '1';
        if ($standardLayout !== null && (!$row || (int) $position > 4)) {
            return [
                'Width' => 1200,
                'WidthDivisor' => 1,
                'Height' => 0,
                'vWidth' => 1200,
                'vHeight' => 675,
            ];
        }
        if (!$row) {
            return [
                'Width' => 0,
                'WidthDivisor' => 1,
                'Height' => 0,
                'vWidth' => 0,
                'vHeight' => 0,
            ];
        }

        $widthDivisor = (float) ($row['w_div_Pos' . $slot] ?? 1);
        if ($widthDivisor <= 0) {
            $widthDivisor = 1;
        }

        return [
            'Width' => (float) ($row['w_Pos' . $slot] ?? 0),
            'WidthDivisor' => $widthDivisor,
            'Height' => 0,
            'vWidth' => (float) ($row['vw_Pos' . $slot] ?? 0),
            'vHeight' => (float) ($row['vh_Pos' . $slot] ?? 0),
        ];
    }

    function red_public_admin_component_ids($value)
    {
        $ids = [];
        foreach (explode(',', (string) $value) as $id) {
            $id = (int) trim($id);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    function red_public_admin_component_authorized($connection, $component, array $adminComponentIds)
    {
        $component = (string) $component;
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $component) || empty($adminComponentIds)) {
            return false;
        }

        foreach ($adminComponentIds as $componentId) {
            $row = red_public_fetch_one(
                $connection,
                'SELECT RecordID FROM RED_Components WHERE RecordID=? AND UniqueName=? LIMIT 1',
                'is',
                [(int) $componentId, $component],
                'Public admin component authorization lookup failed'
            );
            if ($row) {
                return true;
            }
        }

        return false;
    }

    function red_public_seo_origin()
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === ''
            || preg_match('/\A(?:[A-Za-z0-9.-]+|\[[A-Fa-f0-9:]+\])(?::[0-9]{1,5})?\z/', $host) !== 1
        ) {
            return '';
        }
        $forwardedProtocol = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || $forwardedProtocol === 'https';

        return ($https ? 'https' : 'http') . '://' . $host;
    }

    function red_public_seo_current_url($origin)
    {
        $origin = rtrim((string) $origin, '/');
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if ($origin === '' || !is_string($path) || !red_seo_valid_root_path($path)) {
            return '';
        }

        return $origin . $path;
    }

    function red_public_seo_default_locale($language)
    {
        $language = strtolower(trim((string) $language));
        $known = [
            'sp' => 'es_ES',
            'es' => 'es_ES',
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'pt' => 'pt_BR',
        ];
        return $known[$language] ?? 'en_US';
    }

    function red_public_seo_local_image_fact($reference, $origin)
    {
        $url = red_seo_absolute_reference($reference, $origin);
        $fact = [
            'url' => $url,
            'mime' => '',
            'width' => 0,
            'height' => 0,
        ];
        if ($url === '') {
            return $fact;
        }

        $urlParts = parse_url($url);
        $originParts = parse_url((string) $origin);
        if (!is_array($urlParts)
            || !is_array($originParts)
            || strcasecmp((string) ($urlParts['host'] ?? ''), (string) ($originParts['host'] ?? '')) !== 0
            || (int) ($urlParts['port'] ?? 0) !== (int) ($originParts['port'] ?? 0)
            || strcasecmp((string) ($urlParts['scheme'] ?? ''), (string) ($originParts['scheme'] ?? '')) !== 0
        ) {
            return $fact;
        }

        $path = rawurldecode((string) ($urlParts['path'] ?? ''));
        if (!red_seo_valid_root_path($path)) {
            return $fact;
        }
        $projectRoot = realpath(dirname(__DIR__));
        $candidate = $projectRoot !== false ? realpath($projectRoot . $path) : false;
        if ($projectRoot === false
            || $candidate === false
            || !is_file($candidate)
            || strpos($candidate, $projectRoot . DIRECTORY_SEPARATOR) !== 0
        ) {
            return $fact;
        }

        $dimensions = @getimagesize($candidate);
        if (!is_array($dimensions) || empty($dimensions[0]) || empty($dimensions[1])) {
            return $fact;
        }
        $fact['mime'] = (string) ($dimensions['mime'] ?? '');
        $fact['width'] = (int) $dimensions[0];
        $fact['height'] = (int) $dimensions[1];
        return $fact;
    }

    function red_public_seo_article_image_reference(array $row)
    {
        foreach (['BigPict', 'SmallPict', 'SmallPict2'] as $field) {
            $filename = trim((string) ($row[$field] ?? ''));
            if ($filename !== ''
                && basename($filename) === $filename
                && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,254}\z/', $filename) === 1
            ) {
                return '/images/articles/' . rawurlencode($filename);
            }
        }
        return '';
    }

    function red_public_seo_route_context($connection, $table)
    {
        $table = (string) $table;
        $advanced = red_public_advanced_items(
            $connection,
            ['Website_Title', 'Website_Slogan', 'Website_Logo']
        );
        $websiteTitle = red_public_plain_text($advanced['Website_Title'] ?? '');
        $websiteSlogan = red_public_plain_text($advanced['Website_Slogan'] ?? '');
        $article = red_public_route_value('article');
        $ownerType = '';
        $ownerAlias = '';
        $row = null;
        $description = '';
        $tags = '';
        $legacyTitle = '';
        $pageImage = '';

        if ($article !== '') {
            $ownerType = 'article';
            $row = red_public_article_route_row(
                $connection,
                [
                    'RecordID',
                    'Title',
                    'ShortDesc',
                    'Tags',
                    'Alias',
                    'Language',
                    'BigPict',
                    'SmallPict',
                    'SmallPict2',
                ]
            );
            if ($row) {
                $ownerAlias = (string) ($row['Alias'] ?? '');
                $visibleTitle = red_public_plain_text($row['Title'] ?? '');
                $legacyArticleTitle = preg_replace('/-/', ' ', (string) ($row['Title'] ?? ''));
                $legacyTitle = $websiteTitle . ' | ' . ucwords($legacyArticleTitle);
                $description = red_public_plain_text($row['ShortDesc'] ?? '');
                $tags = (string) ($row['Tags'] ?? '');
                $pageImage = red_public_seo_article_image_reference($row);
            }
        } else {
            $map = [
                'Sections' => ['owner' => 'section', 'alias' => 'Sections'],
                'Categories' => ['owner' => 'category', 'alias' => 'Categories'],
                'SubCategories' => ['owner' => 'subcategory', 'alias' => 'SubCategories'],
            ];
            if (isset($map[$table])) {
                $ownerType = $map[$table]['owner'];
                $aliasColumn = $map[$table]['alias'];
                $row = red_public_area_row(
                    $connection,
                    $table,
                    ['RecordID', $aliasColumn, 'Title', 'Description', 'Tags', 'Language']
                );
                if ($row) {
                    $ownerAlias = (string) ($row[$aliasColumn] ?? '');
                    $visibleTitle = red_public_plain_text($row['Title'] ?? '');
                    $legacyTitle = strtolower($ownerAlias) === 'home'
                        ? $websiteTitle . ' | ' . $websiteSlogan
                        : $websiteTitle . ' | ' . ucwords($visibleTitle);
                    $description = (string) ($row['Description'] ?? '');
                    $tags = (string) ($row['Tags'] ?? '');
                }
            }
        }

        if (!$row) {
            $notFound = $websiteTitle !== '' ? $websiteTitle . ' | Page not found' : 'Page not found';
            return [
                'found' => false,
                'legacyTitle' => $table !== '' ? $notFound : 'Page not found',
                'rich' => false,
            ];
        }

        $seo = red_seo_metadata_row($connection, $ownerType, (int) ($row['RecordID'] ?? 0));
        $rich = is_array($seo) && red_seo_has_overrides($seo);
        $origin = red_public_seo_origin();
        $currentUrl = red_public_seo_current_url($origin);
        $canonical = $rich && red_seo_valid_absolute_url($seo['CanonicalURL'] ?? '')
            ? (string) $seo['CanonicalURL']
            : $currentUrl;
        $seoTitle = $rich ? trim((string) ($seo['SEO_Title'] ?? '')) : '';
        $effectiveTitle = $seoTitle !== '' ? $seoTitle : $legacyTitle;
        $metaDescription = $rich && trim((string) ($seo['MetaDescription'] ?? '')) !== ''
            ? trim((string) $seo['MetaDescription'])
            : red_public_plain_text($description);
        $ogTitle = $rich && trim((string) ($seo['OGTitle'] ?? '')) !== ''
            ? trim((string) $seo['OGTitle'])
            : $effectiveTitle;
        $ogDescription = $rich && trim((string) ($seo['OGDescription'] ?? '')) !== ''
            ? trim((string) $seo['OGDescription'])
            : $metaDescription;
        $ogImageReference = $rich && trim((string) ($seo['OGImage'] ?? '')) !== ''
            ? trim((string) $seo['OGImage'])
            : $pageImage;
        if ($ogImageReference === '' && trim((string) ($advanced['Website_Logo'] ?? '')) !== '') {
            $logo = red_site_logo_public_context(dirname(__DIR__), $advanced['Website_Logo']);
            $ogImageReference = is_array($logo) ? (string) ($logo['url'] ?? '') : '';
        }
        $ogImage = red_public_seo_local_image_fact($ogImageReference, $origin);
        $xImageReference = $rich && trim((string) ($seo['XImage'] ?? '')) !== ''
            ? trim((string) $seo['XImage'])
            : $ogImageReference;
        $xImage = red_public_seo_local_image_fact($xImageReference, $origin);
        $language = (string) ($row['Language'] ?? red_public_language());
        $isHome = $ownerType === 'section' && strtolower($ownerAlias) === 'home';
        $ogLocale = trim((string) ($seo['OGLocale'] ?? ''))
            ?: red_public_seo_default_locale($language);
        $schemaLanguage = preg_match('/\A[a-z]{2}/', $ogLocale, $languageMatch) === 1
            ? (string) $languageMatch[0]
            : '';

        return [
            'found' => true,
            'rich' => $rich,
            'ownerType' => $ownerType,
            'ownerRecordId' => (int) ($row['RecordID'] ?? 0),
            'ownerAlias' => $ownerAlias,
            'isHome' => $isHome,
            'legacyTitle' => $legacyTitle,
            'title' => $effectiveTitle,
            'visibleTitle' => $visibleTitle ?? '',
            'description' => $metaDescription,
            'tags' => $tags,
            'canonical' => $canonical,
            'robots' => [
                'index' => ($seo['RobotsIndex'] ?? '') === 'N' ? 'noindex' : 'index',
                'follow' => ($seo['RobotsFollow'] ?? '') === 'N' ? 'nofollow' : 'follow',
            ],
            'og' => [
                'locale' => $ogLocale,
                'type' => trim((string) ($seo['OGType'] ?? '')) ?: ($isHome ? 'website' : 'article'),
                'title' => $ogTitle,
                'description' => $ogDescription,
                'url' => $canonical,
                'image' => $ogImage,
                'imageAlt' => trim((string) ($seo['OGImageAlt'] ?? '')) ?: ($visibleTitle ?? ''),
            ],
            'x' => [
                'card' => trim((string) ($seo['XCard'] ?? '')) ?: ($xImage['url'] !== '' ? 'summary_large_image' : 'summary'),
                'title' => trim((string) ($seo['XTitle'] ?? '')) ?: $ogTitle,
                'description' => trim((string) ($seo['XDescription'] ?? '')) ?: $ogDescription,
                'image' => $xImage,
            ],
            'schemaType' => trim((string) ($seo['SchemaType'] ?? '')) ?: ($isHome ? 'WebSite' : 'WebPage'),
            'schemaLanguage' => $schemaLanguage,
            'schema' => [
                'identityType' => trim((string) ($seo['SchemaIdentityType'] ?? '')),
                'identityName' => trim((string) ($seo['SchemaIdentityName'] ?? '')),
                'identityUrl' => trim((string) ($seo['SchemaIdentityURL'] ?? '')),
                'mainEntityName' => trim((string) ($seo['SchemaMainEntityName'] ?? '')),
                'educationalLevel' => trim((string) ($seo['SchemaEducationalLevel'] ?? '')),
                'courseMode' => trim((string) ($seo['SchemaCourseMode'] ?? '')),
                'courseWorkload' => trim((string) ($seo['SchemaCourseWorkload'] ?? '')),
                'instructorName' => trim((string) ($seo['SchemaInstructorName'] ?? '')),
                'teaches' => trim((string) ($seo['SchemaTeaches'] ?? '')),
                'serviceType' => trim((string) ($seo['SchemaServiceType'] ?? '')),
            ],
            'websiteTitle' => $websiteTitle,
        ];
    }

    function red_public_seo_meta_tag($attribute, $name, $content)
    {
        $content = trim((string) $content);
        return $content === ''
            ? ''
            : '<meta ' . $attribute . '="' . red_public_html($name) . '" content="' .
                red_public_html($content) . '">' . "\n";
    }

    function red_public_seo_schema(array $context)
    {
        $type = (string) ($context['schemaType'] ?? 'WebPage');
        if (!in_array($type, ['WebPage', 'WebSite', 'Course', 'Service'], true)) {
            $type = 'WebPage';
        }
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => (string) ($context['title'] ?? ''),
            'url' => (string) ($context['canonical'] ?? ''),
        ];
        if (trim((string) ($context['description'] ?? '')) !== '') {
            $schema['description'] = (string) $context['description'];
        }
        $imageUrl = (string) ($context['og']['image']['url'] ?? '');
        if ($imageUrl !== '') {
            $schema['image'] = $imageUrl;
        }
        $language = trim((string) ($context['schemaLanguage'] ?? ''));
        if (in_array($type, ['WebPage', 'WebSite', 'Course'], true)
            && preg_match('/\A[a-z]{2}\z/', $language) === 1
        ) {
            $schema['inLanguage'] = $language;
        }
        $details = is_array($context['schema'] ?? null) ? $context['schema'] : [];
        $identityType = (string) ($details['identityType'] ?? '');
        $identityName = trim((string) ($details['identityName'] ?? ''));
        $identity = null;
        if (in_array($identityType, ['Person', 'Organization'], true) && $identityName !== '') {
            $identity = [
                '@type' => $identityType,
                'name' => $identityName,
            ];
            $identityUrl = trim((string) ($details['identityUrl'] ?? ''));
            if (red_seo_valid_absolute_url($identityUrl)) {
                $identity['url'] = $identityUrl;
            }
        }
        if ($type === 'WebPage' && trim((string) ($context['websiteTitle'] ?? '')) !== '') {
            $websiteUrl = red_public_seo_origin() . '/';
            $canonicalParts = parse_url((string) ($context['canonical'] ?? ''));
            $canonicalScheme = is_array($canonicalParts)
                ? strtolower((string) ($canonicalParts['scheme'] ?? ''))
                : '';
            if (is_array($canonicalParts)
                && in_array($canonicalScheme, ['http', 'https'], true)
                && trim((string) ($canonicalParts['host'] ?? '')) !== ''
            ) {
                $websiteUrl = $canonicalScheme . '://' .
                    (string) $canonicalParts['host'] .
                    (isset($canonicalParts['port']) ? ':' . (int) $canonicalParts['port'] : '') . '/';
            }
            $schema['isPartOf'] = [
                '@type' => 'WebSite',
                'name' => (string) $context['websiteTitle'],
                'url' => $websiteUrl,
            ];
        }
        if (in_array($type, ['WebPage', 'WebSite'], true) && is_array($identity)) {
            $schema['about'] = $identity;
        }
        if (in_array($type, ['Course', 'Service'], true) && is_array($identity)) {
            $schema['provider'] = $identity;
        }

        $mainEntityName = trim((string) ($details['mainEntityName'] ?? ''));
        if ($type === 'WebPage' && $mainEntityName !== '') {
            $schema['mainEntity'] = [
                '@type' => 'Course',
                'name' => $mainEntityName,
            ];
            if (is_array($identity)) {
                $schema['mainEntity']['provider'] = $identity;
            }
        }

        if ($type === 'Course') {
            $educationalLevel = trim((string) ($details['educationalLevel'] ?? ''));
            if ($educationalLevel !== '') {
                $schema['educationalLevel'] = $educationalLevel;
            }

            $teaches = preg_split('/\R/u', (string) ($details['teaches'] ?? ''));
            $teaches = array_values(array_unique(array_filter(array_map(
                static function ($value) {
                    return trim((string) $value);
                },
                is_array($teaches) ? $teaches : []
            ))));
            if ($teaches !== []) {
                $schema['teaches'] = $teaches;
            }

            $courseMode = trim((string) ($details['courseMode'] ?? ''));
            $courseWorkload = trim((string) ($details['courseWorkload'] ?? ''));
            $instructorName = trim((string) ($details['instructorName'] ?? ''));
            if ($courseMode !== '' || $courseWorkload !== '' || $instructorName !== '') {
                $instance = ['@type' => 'CourseInstance'];
                if ($courseMode !== '') {
                    $instance['courseMode'] = $courseMode;
                }
                if ($courseWorkload !== '') {
                    $instance['courseWorkload'] = $courseWorkload;
                }
                if ($instructorName !== '') {
                    $instance['instructor'] = [
                        '@type' => 'Person',
                        'name' => $instructorName,
                    ];
                }
                $schema['hasCourseInstance'] = $instance;
            }
        }

        if ($type === 'Service') {
            $serviceType = trim((string) ($details['serviceType'] ?? ''));
            if ($serviceType !== '') {
                $schema['serviceType'] = $serviceType;
            }
        }

        return $schema;
    }

    function red_public_seo_rich_meta_html(array $context)
    {
        $html = red_public_seo_meta_tag('name', 'description', $context['description'] ?? '');
        $html .= red_public_seo_meta_tag('name', 'keywords', $context['tags'] ?? '');
        $html .= red_public_seo_meta_tag(
            'name',
            'robots',
            ($context['robots']['index'] ?? 'index') . ', ' . ($context['robots']['follow'] ?? 'follow')
        );
        if (!empty($context['canonical'])) {
            $html .= '<link rel="canonical" href="' . red_public_html($context['canonical']) . '">' . "\n";
        }

        foreach ([
            'og:locale' => $context['og']['locale'] ?? '',
            'og:type' => $context['og']['type'] ?? '',
            'og:title' => $context['og']['title'] ?? '',
            'og:description' => $context['og']['description'] ?? '',
            'og:url' => $context['og']['url'] ?? '',
            'og:image' => $context['og']['image']['url'] ?? '',
            'og:image:alt' => $context['og']['imageAlt'] ?? '',
            'og:image:type' => $context['og']['image']['mime'] ?? '',
            'og:image:width' => $context['og']['image']['width'] ?? '',
            'og:image:height' => $context['og']['image']['height'] ?? '',
        ] as $property => $content) {
            $html .= red_public_seo_meta_tag('property', $property, $content);
        }
        foreach ([
            'twitter:card' => $context['x']['card'] ?? '',
            'twitter:title' => $context['x']['title'] ?? '',
            'twitter:description' => $context['x']['description'] ?? '',
            'twitter:image' => $context['x']['image']['url'] ?? '',
            'twitter:image:alt' => $context['og']['imageAlt'] ?? '',
        ] as $name => $content) {
            $html .= red_public_seo_meta_tag('name', $name, $content);
        }

        $schemaJson = json_encode(
            red_public_seo_schema($context),
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );
        if (is_string($schemaJson) && $schemaJson !== '') {
            $html .= '<script type="application/ld+json">' . $schemaJson . '</script>' . "\n";
        }

        return $html;
    }
}
?>
