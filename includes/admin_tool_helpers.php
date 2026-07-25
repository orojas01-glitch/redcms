<?php
/**
 * Helpers for admin content maintenance tool endpoints.
 */

require_once __DIR__ . '/admin_area_helpers.php';
require_once __DIR__ . '/admin_article_helpers.php';
require_once __DIR__ . '/admin_authorization_helpers.php';
require_once __DIR__ . '/admin_content_revision_helpers.php';

if (!function_exists('red_admin_tool_scalar')) {
    function red_admin_tool_scalar($value)
    {
        return is_array($value) ? '' : (string) $value;
    }
}

if (!function_exists('red_admin_tool_text')) {
    function red_admin_tool_text($value)
    {
        return red_admin_text(red_admin_tool_scalar($value));
    }
}

if (!function_exists('red_admin_tool_html')) {
    function red_admin_tool_html($value)
    {
        return htmlspecialchars(red_admin_tool_scalar($value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('red_admin_tool_js_suffix')) {
    function red_admin_tool_js_suffix($alias, $recordId)
    {
        $alias = preg_replace('/[^A-Za-z0-9_]+/', '_', red_admin_tool_scalar($alias));
        $alias = trim($alias, '_');
        if ($alias === '') {
            $alias = 'content';
        }

        return $alias . '_' . (int) $recordId;
    }
}

if (!function_exists('red_admin_tool_area_config')) {
    function red_admin_tool_area_config($cparea)
    {
        $areas = [
            'Sections' => [
                'table' => 'RED_Sections',
                'column' => 'Sections',
                'position' => 'SectionPosition',
                'singular' => 'Section',
                'style' => 'sections',
            ],
            'Categories' => [
                'table' => 'RED_Categories',
                'column' => 'Categories',
                'position' => 'CategoryPosition',
                'singular' => 'Category',
                'style' => 'categories',
            ],
            'SubCategories' => [
                'table' => 'RED_SubCategories',
                'column' => 'SubCategories',
                'position' => 'SubCategoryPosition',
                'singular' => 'SubCategory',
                'style' => 'subcategories',
            ],
        ];

        return $areas[$cparea] ?? null;
    }
}

if (!function_exists('red_admin_tool_position_column')) {
    function red_admin_tool_position_column($value, $fallback = '')
    {
        $value = red_admin_tool_text($value);
        $fallback = red_admin_tool_text($fallback);
        $allowed = red_admin_tool_article_position_columns();

        if (isset($allowed[$value])) {
            return $value;
        }
        if (isset($allowed[$fallback])) {
            return $fallback;
        }

        return '';
    }
}

if (!function_exists('red_admin_tool_post_text')) {
    function red_admin_tool_post_text($fieldName, $default = '')
    {
        return red_admin_tool_text($_POST[$fieldName] ?? $default);
    }
}

if (!function_exists('red_admin_tool_count_page')) {
    function red_admin_tool_count_page($value)
    {
        $countPage = (int) red_admin_tool_scalar($value);
        return ($countPage >= 2 && $countPage <= 6) ? $countPage : 0;
    }
}

if (!function_exists('red_admin_tool_fetch_all')) {
    function red_admin_tool_fetch_all($connection, $sql, $types, array $values, $logMessage)
    {
        try {
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                return [];
            }

            if ($types !== '' && !red_admin_tool_bind_params($stmt, $types, $values)) {
                mysqli_stmt_close($stmt);
                return [];
            }

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return [];
            }

            $result = mysqli_stmt_get_result($stmt);
            $rows = [];
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }

            mysqli_stmt_close($stmt);
            return $rows;
        } catch (mysqli_sql_exception $e) {
            error_log($logMessage . ': ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('red_admin_tool_fetch_one')) {
    function red_admin_tool_fetch_one($connection, $sql, $types, array $values, $logMessage)
    {
        $rows = red_admin_tool_fetch_all($connection, $sql, $types, $values, $logMessage);
        return $rows[0] ?? null;
    }
}

if (!function_exists('red_admin_tool_layout_positions')) {
    function red_admin_tool_layout_positions($connection, $layout)
    {
        return count(red_admin_area_layout_position_options($connection, $layout, false));
    }
}

if (!function_exists('red_admin_tool_layout_position_options')) {
    function red_admin_tool_layout_position_options($connection, $layout, $includeHidden = true)
    {
        return red_admin_area_layout_position_options($connection, $layout, $includeHidden);
    }
}

if (!function_exists('red_admin_tool_all_layout_position_options')) {
    function red_admin_tool_all_layout_position_options($connection, $includeHidden = true)
    {
        $options = $includeHidden ? [0 => 'Hidden'] : [];
        foreach (red_admin_area_layouts($connection) as $layoutId) {
            foreach (red_admin_tool_layout_position_options($connection, $layoutId, false) as $positionId => $label) {
                $positionId = (int) $positionId;
                if (!isset($options[$positionId])) {
                    $options[$positionId] = (string) $label;
                } elseif ($options[$positionId] !== (string) $label) {
                    $options[$positionId] = 'Position ' . $positionId;
                }
            }
        }
        ksort($options, SORT_NUMERIC);

        return $options;
    }
}

if (!function_exists('red_admin_tool_layout_has_position')) {
    function red_admin_tool_layout_has_position($connection, $layout, $position, $includeHidden = true)
    {
        $position = (int) $position;
        return array_key_exists(
            $position,
            red_admin_tool_layout_position_options($connection, $layout, $includeHidden)
        );
    }
}

if (!function_exists('red_admin_tool_sort_sql')) {
    function red_admin_tool_sort_sql($sortBy, $positionColumn, $useHomePosition = false, $includeArticle = false)
    {
        $positionColumn = red_admin_tool_position_column($positionColumn, 'PagePosition');
        if ($useHomePosition) {
            $positionColumn = 'HomePosition';
        }

        switch (red_admin_tool_text($sortBy)) {
            case 'Article Title ▲':
            case 'Article Title':
                return 'ORDER BY Title ASC';
            case 'Article Title ▼':
                return 'ORDER BY Title DESC';
            case 'Pos ▲':
            case 'Pos':
                return 'ORDER BY `' . $positionColumn . '` ASC';
            case 'Pos ▼':
                return 'ORDER BY `' . $positionColumn . '` DESC';
            case 'Comp ▲':
            case 'Comp':
                return 'ORDER BY Component ASC';
            case 'Comp ▼':
                return 'ORDER BY Component DESC';
            case 'Article ▲':
            case 'Article':
                return $includeArticle ? 'ORDER BY Article ASC' : 'ORDER BY Updated DESC';
            case 'Article ▼':
                return $includeArticle ? 'ORDER BY Article DESC' : 'ORDER BY Updated DESC';
            default:
                return 'ORDER BY Updated DESC';
        }
    }
}

if (!function_exists('red_admin_tool_article_scope')) {
    function red_admin_tool_article_scope($countPage, $section, $category, $subcategory, $article)
    {
        $countPage = red_admin_tool_count_page($countPage);
        $section = red_admin_tool_text($section);
        $category = red_admin_tool_text($category);
        $subcategory = red_admin_tool_text($subcategory);
        $article = red_admin_tool_text($article);

        $types = '';
        $values = [];

        switch ($countPage) {
            case 2:
                if ($article === '') {
                    return ['(Sections=\'Home\' OR HomeFeature=\'Y\')', '', []];
                }
                return ['((Sections=\'Home\' AND Alias=?) OR (Article=?))', 'ss', [$article, $article]];
            case 3:
                if ($article === '') {
                    return ['Sections=?', 's', [$section]];
                }
                return ['((Sections=? AND Alias=?) OR (Sections=? AND Article=?))', 'ssss', [$section, $article, $section, $article]];
            case 4:
                if ($article === '') {
                    return ['Sections=? AND Categories=?', 'ss', [$section, $category]];
                }
                return [
                    '((Sections=? AND Categories=? AND Alias=?) OR (Sections=? AND Categories=? AND Article=?))',
                    'ssssss',
                    [$section, $category, $article, $section, $category, $article],
                ];
            case 5:
                if ($article === '') {
                    return ['Sections=? AND Categories=? AND SubCategories=?', 'sss', [$section, $category, $subcategory]];
                }
                $types = 'ssssssss';
                $values = [$section, $category, $subcategory, $article, $section, $category, $subcategory, $article];
                return ['((Sections=? AND Categories=? AND SubCategories=? AND Alias=?) OR (Sections=? AND Categories=? AND SubCategories=? AND Article=?))', $types, $values];
            case 6:
                $types = 'ssssssss';
                $values = [$section, $category, $subcategory, $article, $section, $category, $subcategory, $article];
                return ['((Sections=? AND Categories=? AND SubCategories=? AND Alias=?) OR (Sections=? AND Categories=? AND SubCategories=? AND Article=?))', $types, $values];
            default:
                return ['1=0', '', []];
        }
    }
}

if (!function_exists('red_admin_tool_move_articles')) {
    function red_admin_tool_move_articles(
        $connection,
        $countPage,
        $section,
        $category,
        $subcategory,
        $article,
        $positionColumn,
        $filterPosition,
        $sortBy,
        $sortPositionColumn,
        $language = ''
    )
    {
        [$scopeSql, $types, $values] = red_admin_tool_article_scope($countPage, $section, $category, $subcategory, $article);
        $positionColumn = red_admin_tool_position_column($positionColumn, $sortPositionColumn);
        $sortPositionColumn = red_admin_tool_position_column($sortPositionColumn, $positionColumn);
        if ($positionColumn === '' || $sortPositionColumn === '') {
            return [];
        }

        $where = ['Active=\'Y\'', $scopeSql];
        $language = substr(red_admin_tool_text($language), 0, 2);
        if ($language !== '') {
            $where[] = 'Language=?';
            $types .= 's';
            $values[] = $language;
        }
        if ($filterPosition !== 'all') {
            $where[] = '`' . $positionColumn . '`=?';
            $types .= 'i';
            $values[] = max(0, (int) red_admin_tool_scalar($filterPosition));
        }

        $sql = 'SELECT * FROM RED_Articles WHERE ' . implode(' AND ', $where) . ' ' .
            red_admin_tool_sort_sql($sortBy, $sortPositionColumn, red_admin_tool_text($section) === 'home', false);

        return red_admin_tool_fetch_all($connection, $sql, $types, $values, 'RED_Articles move-tool lookup failed');
    }
}

if (!function_exists('red_admin_tool_filter_articles')) {
    function red_admin_tool_filter_articles($connection, $cparea, $filterValue, $sortBy)
    {
        $config = red_admin_tool_area_config($cparea);
        if (!$config) {
            return [];
        }

        $filterValue = red_admin_tool_text($filterValue);
        if ($filterValue === '') {
            return [];
        }

        $sql = 'SELECT * FROM RED_Articles WHERE Active=\'Y\' AND `' . $config['column'] . '`=? ' .
            red_admin_tool_sort_sql($sortBy, $config['position'], false, true);

        return red_admin_tool_fetch_all($connection, $sql, 's', [$filterValue], 'RED_Articles filter-tool lookup failed');
    }
}

if (!function_exists('red_admin_tool_area_layout')) {
    function red_admin_tool_area_layout($connection, $cparea, $areaValue)
    {
        $config = red_admin_tool_area_config($cparea);
        $areaValue = red_admin_tool_text($areaValue);
        if (!$config || $areaValue === '') {
            return '';
        }

        $row = red_admin_tool_fetch_one(
            $connection,
            'SELECT Layout FROM `' . $config['table'] . '` WHERE `' . $config['column'] . '`=? LIMIT 1',
            's',
            [$areaValue],
            'RED area layout lookup failed'
        );

        return $row ? red_admin_tool_text($row['Layout'] ?? '') : '';
    }
}

if (!function_exists('red_admin_tool_area_options')) {
    function red_admin_tool_area_options($connection, $cparea)
    {
        $config = red_admin_tool_area_config($cparea);
        if (!$config) {
            return [];
        }

        return red_admin_tool_fetch_all(
            $connection,
            'SELECT `' . $config['column'] . '` AS AreaName, Layout FROM `' . $config['table'] . '` ORDER BY CreationDate DESC',
            '',
            [],
            'RED area option lookup failed'
        );
    }
}

if (!function_exists('red_admin_tool_active_area_values')) {
    function red_admin_tool_active_area_values($connection, $cparea)
    {
        $config = red_admin_tool_area_config($cparea);
        if (!$config) {
            return [];
        }

        return red_admin_tool_fetch_all(
            $connection,
            'SELECT `' . $config['column'] . '` AS AreaName, Features FROM `' . $config['table'] . '` WHERE Active=\'Y\' ORDER BY AreaName ASC',
            '',
            [],
            'RED active area lookup failed'
        );
    }
}

if (!function_exists('red_admin_tool_active_articles')) {
    function red_admin_tool_active_articles($connection, $articlesOnly = false)
    {
        $sql = 'SELECT Title, Alias FROM RED_Articles WHERE Active=\'Y\'';
        if ($articlesOnly) {
            $sql .= ' AND Component=\'Article\'';
        }
        $sql .= ' ORDER BY Updated DESC';

        return red_admin_tool_fetch_all($connection, $sql, '', [], 'RED active article lookup failed');
    }
}

if (!function_exists('red_admin_tool_destination_area_rows')) {
    function red_admin_tool_destination_area_rows($connection, $cparea, $language = '')
    {
        $config = red_admin_tool_area_config($cparea);
        if (!$config) {
            return [];
        }

        $language = substr(red_admin_tool_text($language), 0, 2);
        if ($cparea === 'Categories') {
            $sql = 'SELECT category_area.RecordID, category_area.Categories AS AreaName, ' .
                'category_area.Title, category_area.Layout, category_area.Language, ' .
                'category_area.SectionRecordID AS ParentRecordID, ' .
                'section_area.Sections AS SectionAlias, \'\' AS CategoryAlias ' .
                'FROM RED_Categories AS category_area ' .
                'JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                'AND section_area.Language=category_area.Language ' .
                'WHERE category_area.Active=\'Y\' AND section_area.Active=\'Y\'';
            $languageColumn = 'category_area.Language';
        } elseif ($cparea === 'SubCategories') {
            $sql = 'SELECT subcategory_area.RecordID, subcategory_area.SubCategories AS AreaName, ' .
                'subcategory_area.Title, subcategory_area.Layout, subcategory_area.Language, ' .
                'subcategory_area.CategoryRecordID AS ParentRecordID, ' .
                'section_area.Sections AS SectionAlias, category_area.Categories AS CategoryAlias ' .
                'FROM RED_SubCategories AS subcategory_area ' .
                'JOIN RED_Categories AS category_area ON category_area.RecordID=subcategory_area.CategoryRecordID ' .
                'AND category_area.Language=subcategory_area.Language ' .
                'JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                'AND section_area.Language=category_area.Language ' .
                'WHERE subcategory_area.Active=\'Y\' AND category_area.Active=\'Y\' AND section_area.Active=\'Y\'';
            $languageColumn = 'subcategory_area.Language';
        } else {
            $sql = 'SELECT RecordID, `' . $config['column'] . '` AS AreaName, Title, Layout, Language' .
                ' FROM `' . $config['table'] . '` WHERE Active=\'Y\'';
            $languageColumn = 'Language';
        }
        $types = '';
        $values = [];
        if ($language !== '') {
            $sql .= ' AND ' . $languageColumn . '=?';
            $types = 's';
            $values[] = $language;
        }
        $sql .= ' ORDER BY Title ASC, AreaName ASC, RecordID ASC';

        return red_admin_tool_fetch_all(
            $connection,
            $sql,
            $types,
            $values,
            'RED move destination area lookup failed'
        );
    }
}

if (!function_exists('red_admin_tool_destination_article_rows')) {
    function red_admin_tool_destination_article_rows($connection, $language = '')
    {
        $language = substr(red_admin_tool_text($language), 0, 2);
        $sql = 'SELECT RecordID, Title, Alias, Sections, Categories, SubCategories, Layout, Language' .
            ' FROM RED_Articles WHERE Active=\'Y\' AND Component=\'Article\'';
        $types = '';
        $values = [];
        if ($language !== '') {
            $sql .= ' AND Language=?';
            $types = 's';
            $values[] = $language;
        }
        $sql .= ' ORDER BY Sections ASC, Categories ASC, SubCategories ASC, Title ASC, RecordID ASC';

        return red_admin_tool_fetch_all(
            $connection,
            $sql,
            $types,
            $values,
            'RED move destination article lookup failed'
        );
    }
}

if (!function_exists('red_admin_tool_move_layout_definition')) {
    function red_admin_tool_move_layout_definition($connection, $layout)
    {
        $layout = red_admin_tool_text($layout);
        if ($layout === '') {
            return null;
        }

        $definition = red_admin_area_layout_definition($connection, $layout);
        if (!is_array($definition)) {
            return null;
        }

        return [
            'id' => red_admin_tool_text($definition['id'] ?? $layout),
            'assignedId' => $layout,
            'label' => red_admin_tool_text($definition['label'] ?? $layout),
            'positions' => is_array($definition['positions'] ?? null) ? $definition['positions'] : [],
            'previewRows' => is_array($definition['previewRows'] ?? null) ? $definition['previewRows'] : [],
            'previewIsFallback' => !empty($definition['previewIsFallback']),
            'hiddenPosition' => isset($definition['hiddenPosition']) ? (int) $definition['hiddenPosition'] : null,
        ];
    }
}

if (!function_exists('red_admin_tool_move_destination_catalog')) {
    function red_admin_tool_move_destination_catalog($connection, $language = '')
    {
        $language = substr(red_admin_tool_text($language), 0, 2);
        $catalog = [
            'language' => $language,
            'sections' => [],
            'categories' => [],
            'subcategories' => [],
            'articles' => [],
            'layouts' => [],
        ];

        $areaCatalogs = [
            'sections' => 'Sections',
            'categories' => 'Categories',
            'subcategories' => 'SubCategories',
        ];
        foreach ($areaCatalogs as $catalogKey => $cparea) {
            foreach (red_admin_tool_destination_area_rows($connection, $cparea, $language) as $row) {
                $layout = red_admin_tool_text($row['Layout'] ?? '');
                $catalog[$catalogKey][] = [
                    'recordId' => (int) ($row['RecordID'] ?? 0),
                    'parentRecordId' => (int) ($row['ParentRecordID'] ?? 0),
                    'value' => red_admin_tool_text($row['AreaName'] ?? ''),
                    'title' => red_admin_tool_text($row['Title'] ?? ''),
                    'section' => red_admin_tool_text($row['SectionAlias'] ?? ''),
                    'category' => red_admin_tool_text($row['CategoryAlias'] ?? ''),
                    'layout' => $layout,
                    'language' => substr(red_admin_tool_text($row['Language'] ?? ''), 0, 2),
                ];
                if ($layout !== '' && !array_key_exists($layout, $catalog['layouts'])) {
                    $catalog['layouts'][$layout] = red_admin_tool_move_layout_definition($connection, $layout);
                }
            }
        }

        foreach (red_admin_tool_destination_article_rows($connection, $language) as $row) {
            $layout = red_admin_tool_text($row['Layout'] ?? '');
            $catalog['articles'][] = [
                'recordId' => (int) ($row['RecordID'] ?? 0),
                'value' => red_admin_tool_text($row['Alias'] ?? ''),
                'title' => red_admin_tool_text($row['Title'] ?? ''),
                'section' => red_admin_tool_text($row['Sections'] ?? ''),
                'category' => red_admin_tool_text($row['Categories'] ?? ''),
                'subcategory' => red_admin_tool_text($row['SubCategories'] ?? ''),
                'layout' => $layout,
                'language' => substr(red_admin_tool_text($row['Language'] ?? ''), 0, 2),
            ];
            if ($layout !== '' && !array_key_exists($layout, $catalog['layouts'])) {
                $catalog['layouts'][$layout] = red_admin_tool_move_layout_definition($connection, $layout);
            }
        }

        return $catalog;
    }
}

if (!function_exists('red_admin_tool_admin_component_ids')) {
    function red_admin_tool_admin_component_ids($value)
    {
        $ids = [];
        foreach (explode(',', red_admin_tool_scalar($value)) as $id) {
            $id = (int) red_admin_tool_text($id);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('red_admin_tool_identifier')) {
    function red_admin_tool_identifier($value)
    {
        $value = red_admin_tool_text($value);
        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value) ? $value : '';
    }
}

if (!function_exists('red_admin_tool_components_for_admin')) {
    function red_admin_tool_components_for_admin($connection, array $adminComponentIds)
    {
        if (empty($adminComponentIds)) {
            return [];
        }

        try {
            $stmt = mysqli_prepare($connection, 'SELECT UniqueName, Layout, ButtonTag FROM RED_Components WHERE RecordID=? ORDER BY RecordID ASC');
            if (!$stmt) {
                return [];
            }

            $recordId = 0;
            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            $components = [];

            foreach ($adminComponentIds as $adminComponentId) {
                $recordId = (int) $adminComponentId;
                if ($recordId <= 0 || !mysqli_stmt_execute($stmt)) {
                    continue;
                }

                $result = mysqli_stmt_get_result($stmt);
                while ($result && ($row = mysqli_fetch_assoc($result))) {
                    $uniqueName = red_admin_tool_identifier($row['UniqueName'] ?? '');
                    if ($uniqueName !== '') {
                        $row['UniqueName'] = $uniqueName;
                        $components[] = $row;
                    }
                }
            }

            mysqli_stmt_close($stmt);
            return $components;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Components admin list lookup failed: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('red_admin_tool_rows_by_group')) {
    function red_admin_tool_rows_by_group($connection, $compgroup, $allowedToolIds = null)
    {
        $compgroup = red_admin_tool_text($compgroup);
        if (!in_array($compgroup, ['Content', 'Areas'], true)) {
            return [];
        }

        $rows = red_admin_tool_fetch_all(
            $connection,
            'SELECT RecordID, UniqueName, ButtonTag, AltContent FROM RED_Tools WHERE CompGroup=? ORDER BY RecordID ASC',
            's',
            [$compgroup],
            'RED_Tools admin list lookup failed'
        );

        $tools = [];
        foreach ($rows as $row) {
            $recordId = (int) ($row['RecordID'] ?? 0);
            if (is_array($allowedToolIds) && !in_array($recordId, $allowedToolIds, true)) {
                continue;
            }
            $uniqueName = red_admin_tool_identifier($row['UniqueName'] ?? '');
            if ($uniqueName !== '') {
                $row['UniqueName'] = $uniqueName;
                $tools[] = $row;
            }
        }

        return $tools;
    }
}

if (!function_exists('red_admin_tool_component_tables_by_name')) {
    function red_admin_tool_component_tables_by_name()
    {
        return [
            'Form' => 'RED_C_Form',
            'Gallery' => 'RED_C_Gallery',
            'SubMenu' => 'RED_C_Menu',
        ];
    }
}

if (!function_exists('red_admin_tool_component_access')) {
    function red_admin_tool_component_access($connection, $component, array $adminComponentIds, $articleRecordId)
    {
        $component = red_admin_tool_text($component);
        $articleRecordId = (int) $articleRecordId;
        $access = [
            'authorized' => false,
            'comp_group' => '',
            'component_record_id' => 0,
            'subtype' => '',
        ];

        if ($component === '' || empty($adminComponentIds) || $articleRecordId <= 0) {
            return $access;
        }

        $row = red_admin_article_authorization_row($connection, $articleRecordId);
        if (!$row || strcasecmp((string) ($row['component'] ?? ''), $component) !== 0) {
            return $access;
        }

        $componentRecordId = (int) ($row['component_record_id'] ?? 0);
        $access['authorized'] = $componentRecordId > 0 && in_array($componentRecordId, $adminComponentIds, true);
        $access['comp_group'] = red_admin_tool_text($row['comp_group'] ?? '');
        $access['component_record_id'] = (int) ($row['content_record_id'] ?? 0);
        $access['subtype'] = red_admin_tool_text($row['subtype'] ?? '');
        return $access;
    }
}

if (!function_exists('red_admin_tool_public_article_link')) {
    function red_admin_tool_public_article_link($section, $category, $subcategory, $article)
    {
        $section = red_admin_tool_text($section);
        $category = red_admin_tool_text($category);
        $subcategory = red_admin_tool_text($subcategory);
        $article = red_admin_tool_text($article);
        $areaPath = red_admin_area_public_path($section, $category, $subcategory);
        if ($article === '') {
            return $areaPath;
        }

        return rtrim($areaPath, '/') . '/' . rawurlencode($article);
    }
}

if (!function_exists('red_admin_tool_selected_article_ids')) {
    function red_admin_tool_selected_article_ids($post)
    {
        $selected = [];
        if (empty($post['Articles_Sel']) || !is_array($post['Articles_Sel'])) {
            return $selected;
        }

        foreach ($post['Articles_Sel'] as $value) {
            $recordId = (int) red_admin_tool_scalar($value);
            if ($recordId > 0) {
                $selected[$recordId] = $recordId;
            }
        }

        return array_values($selected);
    }
}

if (!function_exists('red_admin_tool_article_position_columns')) {
    function red_admin_tool_article_position_columns()
    {
        return [
            'HomePosition' => true,
            'SectionPosition' => true,
            'CategoryPosition' => true,
            'SubCategoryPosition' => true,
            'PagePosition' => true,
        ];
    }
}

if (!function_exists('red_admin_tool_move_destination_position_column')) {
    function red_admin_tool_move_destination_position_column($post, $fallback = '')
    {
        if (!is_array($post)) {
            return '';
        }

        if (red_admin_tool_text($post['Article'] ?? '') !== '') {
            return 'PagePosition';
        }
        if (red_admin_tool_text($post['SubCategories'] ?? '') !== '') {
            return 'SubCategoryPosition';
        }
        if (red_admin_tool_text($post['Categories'] ?? '') !== '') {
            return 'CategoryPosition';
        }

        $section = red_admin_tool_text($post['Sections'] ?? '');
        if ($section !== '') {
            return strcasecmp($section, 'home') === 0
                ? 'HomePosition'
                : 'SectionPosition';
        }

        return red_admin_tool_position_column($post['VarPosition'] ?? '', $fallback);
    }
}

if (!function_exists('red_admin_tool_values_match')) {
    function red_admin_tool_values_match($left, $right)
    {
        return strcasecmp(red_admin_tool_text($left), red_admin_tool_text($right)) === 0;
    }
}

if (!function_exists('red_admin_tool_move_catalog_match')) {
    function red_admin_tool_move_catalog_match(array $rows, $value, $recordId = 0)
    {
        $value = red_admin_tool_text($value);
        $recordId = (int) $recordId;
        $matches = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !red_admin_tool_values_match($row['value'] ?? '', $value)) {
                continue;
            }
            if ($recordId > 0 && (int) ($row['recordId'] ?? 0) !== $recordId) {
                continue;
            }
            $matches[] = $row;
        }

        return count($matches) === 1 ? $matches[0] : null;
    }
}

if (!function_exists('red_admin_tool_move_destination_context')) {
    function red_admin_tool_move_destination_context($connection, $post, $language = '')
    {
        $invalid = static function ($reason) {
            return [
                'valid' => false,
                'reason' => $reason,
                'level' => '',
                'label' => '',
                'path' => '',
                'layout' => '',
                'layoutDefinition' => null,
                'positionColumn' => '',
                'positionOptions' => [],
                'articleRecordId' => 0,
                'values' => [
                    'Sections' => '',
                    'Categories' => '',
                    'SubCategories' => '',
                    'Article' => '',
                ],
            ];
        };

        if (!is_array($post)) {
            return $invalid('invalid-request');
        }

        $section = red_admin_tool_text($post['Sections'] ?? '');
        $category = red_admin_tool_text($post['Categories'] ?? '');
        $subcategory = red_admin_tool_text($post['SubCategories'] ?? '');
        $article = red_admin_tool_text($post['Article'] ?? '');
        if ($section === '') {
            return $invalid('section-required');
        }
        if ($subcategory !== '' && $category === '') {
            return $invalid('category-required');
        }

        $catalog = red_admin_tool_move_destination_catalog($connection, $language);
        $sectionRow = red_admin_tool_move_catalog_match($catalog['sections'], $section);
        if (!is_array($sectionRow)) {
            return $invalid('section-unavailable');
        }

        $categoryRow = null;
        if ($category !== '') {
            $categoryRow = red_admin_tool_move_catalog_match($catalog['categories'], $category);
            if (!is_array($categoryRow)
                || !red_admin_tool_values_match($categoryRow['section'] ?? '', $sectionRow['value'] ?? '')
            ) {
                return $invalid('category-unavailable');
            }
        }

        $subcategoryRow = null;
        if ($subcategory !== '') {
            $subcategoryRow = red_admin_tool_move_catalog_match($catalog['subcategories'], $subcategory);
            if (!is_array($subcategoryRow)
                || !red_admin_tool_values_match($subcategoryRow['section'] ?? '', $sectionRow['value'] ?? '')
                || !red_admin_tool_values_match($subcategoryRow['category'] ?? '', $categoryRow['value'] ?? '')
            ) {
                return $invalid('subcategory-unavailable');
            }
        }

        $articleRow = null;
        $articleRecordId = (int) red_admin_tool_scalar($post['DestinationArticleRecordID'] ?? 0);
        if ($article !== '') {
            $articleMatches = [];
            foreach ($catalog['articles'] as $candidate) {
                if (!is_array($candidate)
                    || !red_admin_tool_values_match($candidate['value'] ?? '', $article)
                    || !red_admin_tool_values_match($candidate['section'] ?? '', $sectionRow['value'] ?? '')
                    || !red_admin_tool_values_match($candidate['category'] ?? '', $categoryRow['value'] ?? '')
                    || !red_admin_tool_values_match($candidate['subcategory'] ?? '', $subcategoryRow['value'] ?? '')
                ) {
                    continue;
                }
                if ($articleRecordId > 0 && (int) ($candidate['recordId'] ?? 0) !== $articleRecordId) {
                    continue;
                }
                $articleMatches[] = $candidate;
            }
            if (count($articleMatches) !== 1) {
                return $invalid('article-unavailable');
            }
            $articleRow = $articleMatches[0];
        } elseif ($articleRecordId > 0) {
            return $invalid('article-unavailable');
        }

        $targetRow = $sectionRow;
        $level = strcasecmp((string) ($sectionRow['value'] ?? ''), 'home') === 0 ? 'home' : 'section';
        $positionColumn = $level === 'home' ? 'HomePosition' : 'SectionPosition';
        if (is_array($categoryRow)) {
            $targetRow = $categoryRow;
            $level = 'category';
            $positionColumn = 'CategoryPosition';
        }
        if (is_array($subcategoryRow)) {
            $targetRow = $subcategoryRow;
            $level = 'subcategory';
            $positionColumn = 'SubCategoryPosition';
        }
        if (is_array($articleRow)) {
            $targetRow = $articleRow;
            $level = 'article';
            $positionColumn = 'PagePosition';
        }

        $layout = red_admin_tool_text($targetRow['layout'] ?? '');
        $layoutDefinition = $catalog['layouts'][$layout] ?? null;
        if (!is_array($layoutDefinition)) {
            return $invalid('layout-unavailable');
        }

        $positionOptions = [];
        if (($layoutDefinition['hiddenPosition'] ?? null) === 0) {
            $positionOptions[0] = 'Hidden';
        }
        foreach (($layoutDefinition['positions'] ?? []) as $positionId => $positionLabel) {
            $positionId = (int) $positionId;
            if ($positionId > 0) {
                $positionOptions[$positionId] = red_admin_tool_text($positionLabel);
            }
        }
        if ($positionOptions === []) {
            return $invalid('positions-unavailable');
        }

        $normalizedSection = red_admin_tool_text($sectionRow['value'] ?? '');
        $normalizedCategory = is_array($categoryRow) ? red_admin_tool_text($categoryRow['value'] ?? '') : '';
        $normalizedSubcategory = is_array($subcategoryRow) ? red_admin_tool_text($subcategoryRow['value'] ?? '') : '';
        $normalizedArticle = is_array($articleRow) ? red_admin_tool_text($articleRow['value'] ?? '') : '';
        $targetLabel = red_admin_tool_text($targetRow['title'] ?? '');
        if ($targetLabel === '') {
            $targetLabel = red_admin_tool_text($targetRow['value'] ?? '');
        }

        return [
            'valid' => true,
            'reason' => '',
            'level' => $level,
            'label' => $targetLabel,
            'path' => red_admin_tool_public_article_link(
                $normalizedSection,
                $normalizedCategory,
                $normalizedSubcategory,
                $normalizedArticle
            ),
            'layout' => $layout,
            'layoutDefinition' => $layoutDefinition,
            'positionColumn' => $positionColumn,
            'positionOptions' => $positionOptions,
            'articleRecordId' => is_array($articleRow) ? (int) ($articleRow['recordId'] ?? 0) : 0,
            'values' => [
                'Sections' => $normalizedSection,
                'Categories' => $normalizedCategory,
                'SubCategories' => $normalizedSubcategory,
                'Article' => $normalizedArticle,
            ],
        ];
    }
}

if (!function_exists('red_admin_tool_move_source_context')) {
    function red_admin_tool_move_source_context($post)
    {
        if (!is_array($post)) {
            return null;
        }

        $countPage = red_admin_tool_count_page($post['SourceCountPage'] ?? '');
        $section = red_admin_tool_text($post['SourceSection'] ?? '');
        $category = red_admin_tool_text($post['SourceCategory'] ?? '');
        $subcategory = red_admin_tool_text($post['SourceSubCategory'] ?? '');
        $article = red_admin_tool_text($post['SourceArticle'] ?? '');
        $language = substr(red_admin_tool_text($post['SourceLanguage'] ?? ''), 0, 2);
        $positionColumn = red_admin_tool_position_column($post['SourcePositionColumn'] ?? '');
        $legacyPositionColumn = red_admin_tool_position_column($post['VarPosition'] ?? '');
        if ($countPage === 0 || $section === '' || $language === '' || $positionColumn === '') {
            return null;
        }

        $expectedPositionColumn = 'SectionPosition';
        if ($article !== '') {
            $expectedPositionColumn = 'PagePosition';
        } elseif ($subcategory !== '') {
            $expectedPositionColumn = 'SubCategoryPosition';
        } elseif ($category !== '') {
            $expectedPositionColumn = 'CategoryPosition';
        } elseif (strcasecmp($section, 'home') === 0) {
            $expectedPositionColumn = 'HomePosition';
        }
        if ($positionColumn !== $expectedPositionColumn || $legacyPositionColumn !== $positionColumn) {
            return null;
        }

        return [
            'countPage' => $countPage,
            'section' => $section,
            'category' => $category,
            'subcategory' => $subcategory,
            'article' => $article,
            'language' => $language,
            'positionColumn' => $positionColumn,
        ];
    }
}

if (!function_exists('red_admin_tool_move_source_ids_valid')) {
    function red_admin_tool_move_source_ids_valid($connection, array $recordIds, array $sourceContext)
    {
        if ($recordIds === []) {
            return false;
        }

        [$scopeSql, $scopeTypes, $scopeValues] = red_admin_tool_article_scope(
            $sourceContext['countPage'] ?? 0,
            $sourceContext['section'] ?? '',
            $sourceContext['category'] ?? '',
            $sourceContext['subcategory'] ?? '',
            $sourceContext['article'] ?? ''
        );
        foreach ($recordIds as $recordId) {
            $values = [(int) $recordId];
            foreach ($scopeValues as $scopeValue) {
                $values[] = $scopeValue;
            }
            $values[] = $sourceContext['language'];
            $row = red_admin_tool_fetch_one(
                $connection,
                'SELECT RecordID FROM RED_Articles WHERE RecordID=? AND Active=\'Y\' AND (' .
                    $scopeSql . ') AND Language=? LIMIT 1',
                'i' . $scopeTypes . 's',
                $values,
                'RED move source-context validation failed'
            );
            if (!$row) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_admin_tool_move_position_id')) {
    function red_admin_tool_move_position_id($value)
    {
        $value = red_admin_tool_scalar($value);
        if (!preg_match('/^(?:0|[1-9][0-9]?)$/', $value)) {
            return null;
        }

        return (int) $value;
    }
}

if (!function_exists('red_admin_tool_move_record_updates')) {
    function red_admin_tool_move_record_updates(
        array $existing,
        array $destinationValues,
        $sourcePositionColumn,
        $destinationPositionColumn,
        $position
    ) {
        $updates = [
            'Sections' => red_admin_tool_text($destinationValues['Sections'] ?? ''),
        ];
        $finalPositions = [];
        foreach (array_keys(red_admin_tool_article_position_columns()) as $positionColumn) {
            $finalPositions[$positionColumn] = (int) ($existing[$positionColumn] ?? 0);
        }
        if ($sourcePositionColumn !== $destinationPositionColumn) {
            $finalPositions[$sourcePositionColumn] = 0;
            $updates[$sourcePositionColumn] = 0;
        }
        $finalPositions[$destinationPositionColumn] = (int) $position;
        $updates[$destinationPositionColumn] = (int) $position;

        $category = red_admin_tool_text($destinationValues['Categories'] ?? '');
        if ($category !== ''
            || ($finalPositions['CategoryPosition'] === 0
                && $finalPositions['SubCategoryPosition'] === 0
                && $finalPositions['PagePosition'] === 0)
        ) {
            $updates['Categories'] = $category;
        }

        $subcategory = red_admin_tool_text($destinationValues['SubCategories'] ?? '');
        if ($subcategory !== ''
            || ($finalPositions['SubCategoryPosition'] === 0 && $finalPositions['PagePosition'] === 0)
        ) {
            $updates['SubCategories'] = $subcategory;
        }

        $article = red_admin_tool_text($destinationValues['Article'] ?? '');
        if ($article !== '' || $finalPositions['PagePosition'] === 0) {
            $updates['Article'] = $article;
        }

        return $updates;
    }
}

if (!function_exists('red_admin_tool_move_articles_update')) {
    function red_admin_tool_move_articles_update($connection, $post)
    {
        if (!is_array($post) || empty($post['Articles_Sel']) || !is_array($post['Articles_Sel'])) {
            return false;
        }

        $recordIds = red_admin_tool_selected_article_ids($post);
        if ($recordIds === [] || count($recordIds) !== count($post['Articles_Sel'])) {
            return false;
        }

        $sourceContext = red_admin_tool_move_source_context($post);
        if (!is_array($sourceContext)
            || !red_admin_tool_move_source_ids_valid($connection, $recordIds, $sourceContext)
        ) {
            return false;
        }

        $destination = red_admin_tool_move_destination_context(
            $connection,
            $post,
            $sourceContext['language']
        );
        if (empty($destination['valid'])) {
            return false;
        }

        $position = red_admin_tool_move_position_id($post['Position'] ?? '');
        if ($position === null || !array_key_exists($position, $destination['positionOptions'])) {
            return false;
        }
        if ((int) ($destination['articleRecordId'] ?? 0) > 0
            && in_array((int) $destination['articleRecordId'], $recordIds, true)
        ) {
            return false;
        }

        $sourcePositionColumn = $sourceContext['positionColumn'];
        $destinationPositionColumn = red_admin_tool_position_column($destination['positionColumn'] ?? '');
        if ($destinationPositionColumn === '') {
            return false;
        }

        return red_admin_article_batch_transaction(
            $connection,
            function () use (
                $connection,
                $recordIds,
                $destination,
                $sourcePositionColumn,
                $destinationPositionColumn,
                $position
            ) {
                foreach ($recordIds as $recordId) {
                    $existing = red_admin_article_full_record($connection, $recordId);
                    if (!$existing) {
                        return false;
                    }
                    if (!red_admin_content_revision_checkpoint($connection, $recordId)) {
                        return false;
                    }
                    $updates = red_admin_tool_move_record_updates(
                        $existing,
                        $destination['values'],
                        $sourcePositionColumn,
                        $destinationPositionColumn,
                        $position
                    );
                    if (!red_admin_article_update($connection, $recordId, $updates)) {
                        return false;
                    }
                    if (!red_admin_content_revision_record_current($connection, $recordId, 'move')) {
                        return false;
                    }
                }

                return true;
            }
        );
    }
}

if (!function_exists('red_admin_tool_apply_article_updates')) {
    function red_admin_tool_apply_article_updates($connection, $post, $normalizeDashValues = false)
    {
        if (!is_array($post) || empty($post['Articles_Sel']) || !is_array($post['Articles_Sel'])) {
            return false;
        }

        $recordIds = red_admin_tool_selected_article_ids($post);
        foreach ($post['Articles_Sel'] as $selectedRecordId) {
            if ((int) red_admin_tool_scalar($selectedRecordId) <= 0) {
                return false;
            }
        }
        if (empty($recordIds)) {
            return false;
        }

        $updates = [];
        foreach (['Sections', 'Categories', 'SubCategories', 'Article'] as $column) {
            $value = red_admin_tool_text($post[$column] ?? '');
            if ($value === '') {
                continue;
            }

            if ($normalizeDashValues && $column !== 'Sections' && $value === '-') {
                $value = '';
            }
            $updates[$column] = $value;
        }

        $sourcePositionColumn = red_admin_tool_position_column($post['VarPosition'] ?? '');
        $destinationPositionColumn = $normalizeDashValues
            ? $sourcePositionColumn
            : red_admin_tool_move_destination_position_column($post, $sourcePositionColumn);
        $position = red_admin_tool_text($post['Position'] ?? '');
        if ($position !== '') {
            if (!isset(red_admin_tool_article_position_columns()[$destinationPositionColumn])) {
                return false;
            }
            $position = (int) red_admin_tool_scalar($position);
            if ($position < 0 || $position > 99) {
                return false;
            }
            if (!$normalizeDashValues
                && $sourcePositionColumn !== ''
                && $sourcePositionColumn !== $destinationPositionColumn
            ) {
                $updates[$sourcePositionColumn] = 0;
            }
            $updates[$destinationPositionColumn] = $position;
        } elseif (!$normalizeDashValues
            && $sourcePositionColumn !== ''
            && $destinationPositionColumn !== ''
            && $sourcePositionColumn !== $destinationPositionColumn
        ) {
            return false;
        }

        if (empty($updates)) {
            return false;
        }

        return red_admin_article_batch_transaction($connection, function () use ($connection, $recordIds, $updates) {
            foreach ($recordIds as $recordId) {
                if (!red_admin_article_record($connection, $recordId)) {
                    return false;
                }
                if (!red_admin_content_revision_checkpoint($connection, $recordId)) {
                    return false;
                }
                if (!red_admin_article_update($connection, $recordId, $updates)) {
                    return false;
                }
                if (!red_admin_content_revision_record_current($connection, $recordId, 'move')) {
                    return false;
                }
            }

            return true;
        });
    }
}

if (!function_exists('red_admin_tool_bind_params')) {
    function red_admin_tool_bind_params($stmt, $types, array &$values)
    {
        $refs = [];
        foreach ($values as $key => &$value) {
            $refs[$key] = &$value;
        }

        return mysqli_stmt_bind_param($stmt, $types, ...$refs);
    }
}

if (!function_exists('red_admin_tool_layout_target_positions_valid')) {
    function red_admin_tool_layout_target_positions_valid(
        $connection,
        $table,
        $where,
        $whereTypes,
        array $whereValues,
        $targetLayout
    ) {
        if ($table === 'RED_Articles') {
            $rows = red_admin_area_fetch_all(
                $connection,
                "SELECT PagePosition AS position_id FROM RED_Articles WHERE Active='Y' AND $where",
                $whereTypes,
                $whereValues,
                'RED layout tool page-position lookup failed'
            );
            if ($rows === []) {
                return false;
            }
            $positions = [];
            foreach ($rows as $row) {
                $positionId = (int) ($row['position_id'] ?? 0);
                if ($positionId > 0) {
                    $positions[$positionId] = true;
                }
            }

            return red_admin_area_layout_supports_positions(
                $connection,
                $targetLayout,
                array_keys($positions)
            );
        }

        $areaTables = red_admin_area_tables();
        if (!isset($areaTables[$table])) {
            return false;
        }
        $rows = red_admin_area_fetch_all(
            $connection,
            "SELECT * FROM `$table` WHERE Active='Y' AND $where",
            $whereTypes,
            $whereValues,
            'RED layout tool area-position lookup failed'
        );
        if ($rows === []) {
            return false;
        }
        $aliasColumn = $areaTables[$table];
        foreach ($rows as $row) {
            $positions = red_admin_area_used_positions(
                $connection,
                $table,
                $aliasColumn,
                $row[$aliasColumn] ?? '',
                $row['Language'] ?? ''
            );
            if (!is_array($positions)
                || !red_admin_area_layout_supports_positions($connection, $targetLayout, $positions)
            ) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_admin_tool_valid_route_alias')) {
    function red_admin_tool_valid_route_alias($article)
    {
        return is_string($article)
            && preg_match('/\A[A-Za-z0-9](?:[A-Za-z0-9_-]*[A-Za-z0-9])?\z/', $article) === 1;
    }
}

if (!function_exists('red_admin_tool_route_owner_exists')) {
    function red_admin_tool_route_owner_exists(
        $connection,
        $countpage,
        $section,
        $category,
        $subcategory,
        $article
    ) {
        if (!red_admin_tool_valid_route_alias($article)) {
            return false;
        }

        $where = '';
        $types = '';
        $values = [];
        switch ((int) $countpage) {
            case 2:
                $where = "Sections='Home' AND Alias=?";
                $types = 's';
                $values = [$article];
                break;
            case 3:
                $where = 'Sections=? AND Alias=?';
                $types = 'ss';
                $values = [$section, $article];
                break;
            case 4:
                $where = 'Sections=? AND Categories=? AND Alias=?';
                $types = 'sss';
                $values = [$section, $category, $article];
                break;
            case 5:
            case 6:
                $where = 'Sections=? AND Categories=? AND SubCategories=? AND Alias=?';
                $types = 'ssss';
                $values = [$section, $category, $subcategory, $article];
                break;
            default:
                return false;
        }

        return red_admin_tool_fetch_one(
            $connection,
            "SELECT RecordID FROM RED_Articles WHERE Active='Y' AND $where LIMIT 1",
            $types,
            $values,
            'RED layout tool route-owner lookup failed'
        ) !== null;
    }
}

if (!function_exists('red_admin_tool_update_layout_unlocked')) {
    function red_admin_tool_update_layout_unlocked($connection, $countpage, $section, $category, $subcategory, $article, $layout)
    {
        $countpage = (int) red_admin_tool_scalar($countpage);
        $section = red_admin_tool_text($section);
        $category = red_admin_tool_text($category);
        $subcategory = red_admin_tool_text($subcategory);
        $article = red_admin_tool_text($article);
        $layout = red_admin_tool_text($layout);

        if ($layout === '') {
            return false;
        }
        if (red_admin_area_layout_definition($connection, $layout) === null) {
            return false;
        }

        $table = '';
        $where = '';
        $types = 's';
        $values = [$layout];

        if ($article === '') {
            switch ($countpage) {
                case 2:
                    $table = 'RED_Sections';
                    $where = "Sections='Home'";
                    break;
                case 3:
                    if ($section === '') {
                        return false;
                    }
                    $table = 'RED_Sections';
                    $where = 'Sections=?';
                    $types .= 's';
                    $values[] = $section;
                    break;
                case 4:
                    if ($category === '') {
                        return false;
                    }
                    $table = 'RED_Categories';
                    $where = 'Categories=?';
                    $types .= 's';
                    $values[] = $category;
                    break;
                case 5:
                    if ($subcategory === '') {
                        return false;
                    }
                    $table = 'RED_SubCategories';
                    $where = 'SubCategories=?';
                    $types .= 's';
                    $values[] = $subcategory;
                    break;
                default:
                    return false;
            }
        } else {
            $table = 'RED_Articles';
            if (!red_admin_tool_route_owner_exists(
                $connection,
                $countpage,
                $section,
                $category,
                $subcategory,
                $article
            )) {
                return false;
            }
            switch ($countpage) {
                case 2:
                    $where = "((Sections='Home' AND Alias=?) OR (Article LIKE ?))";
                    $types .= 'ss';
                    array_push($values, $article, '%' . $article . '%');
                    break;
                case 3:
                    if ($section === '') {
                        return false;
                    }
                    $where = '((Sections=? AND Alias=?) OR (Sections=? AND Article LIKE ?))';
                    $types .= 'ssss';
                    array_push($values, $section, $article, $section, '%' . $article . '%');
                    break;
                case 4:
                    if ($section === '' || $category === '') {
                        return false;
                    }
                    $where = '((Sections=? AND Categories=? AND Alias=?) OR (Sections=? AND Categories=? AND Article LIKE ?))';
                    $types .= 'ssssss';
                    array_push($values, $section, $category, $article, $section, $category, '%' . $article . '%');
                    break;
                case 5:
                case 6:
                    if ($section === '' || $category === '' || $subcategory === '') {
                        return false;
                    }
                    $where = '((Sections=? AND Categories=? AND SubCategories=? AND Alias=?) OR (Sections=? AND Categories=? AND SubCategories=? AND Article LIKE ?))';
                    $types .= 'ssssssss';
                    array_push(
                        $values,
                        $section,
                        $category,
                        $subcategory,
                        $article,
                        $section,
                        $category,
                        $subcategory,
                        '%' . $article . '%'
                    );
                    break;
                default:
                    return false;
            }
        }

        $whereTypes = substr($types, 1);
        $whereValues = array_slice($values, 1);
        if (!red_admin_tool_layout_target_positions_valid(
            $connection,
            $table,
            $where,
            $whereTypes,
            $whereValues,
            $layout
        )) {
            return false;
        }

        try {
            $stmt = mysqli_prepare($connection, "UPDATE `$table` SET Layout=? WHERE Active='Y' AND $where");
            if (!$stmt) {
                return false;
            }

            red_admin_tool_bind_params($stmt, $types, $values);
            $success = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('RED layout update failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_tool_update_layout')) {
    function red_admin_tool_update_layout($connection, $countpage, $section, $category, $subcategory, $article, $layout)
    {
        return red_admin_with_theme_contract_lock(
            $connection,
            function () use ($connection, $countpage, $section, $category, $subcategory, $article, $layout) {
                return red_admin_tool_update_layout_unlocked(
                    $connection,
                    $countpage,
                    $section,
                    $category,
                    $subcategory,
                    $article,
                    $layout
                );
            }
        );
    }
}

if (!function_exists('red_admin_tool_delete_tables')) {
    function red_admin_tool_delete_tables()
    {
        return [
            'RED_SubCategories' => true,
            'RED_Categories' => true,
            'RED_Sections' => true,
            'RED_C_Menu' => true,
            'RED_Menu' => true,
            'RED_C_Gallery' => true,
            'RED_C_Form' => true,
            'RED_C_MonsterTemplate' => true,
            'RED_Articles' => true,
        ];
    }
}

if (!function_exists('red_admin_tool_delete_by_id')) {
    function red_admin_tool_delete_by_id($connection, $table, $recordId)
    {
        $recordId = (int) $recordId;
        if ($recordId <= 0 || !isset(red_admin_tool_delete_tables()[$table])) {
            return false;
        }

        try {
            $stmt = mysqli_prepare($connection, "DELETE FROM `$table` WHERE RecordID=?");
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            $success = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('RED delete failed for ' . $table . ': ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_tool_component_tables')) {
    function red_admin_tool_component_tables()
    {
        return [
            'RED_C_Gallery' => true,
            'RED_C_Form' => true,
            'RED_C_MonsterTemplate' => true,
            'RED_C_Menu' => true,
        ];
    }
}

if (!function_exists('red_admin_tool_component_pair_exists')) {
    function red_admin_tool_component_pair_exists($connection, $table, $recordId, $articleId)
    {
        $recordId = (int) $recordId;
        $articleId = (int) $articleId;
        if ($recordId <= 0 || $articleId <= 0 || !isset(red_admin_tool_component_tables()[$table])) {
            return false;
        }

        $refId = (string) $articleId;

        try {
            $stmt = mysqli_prepare($connection, "SELECT RecordID FROM `$table` WHERE RecordID=? AND RefID=? LIMIT 1");
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, 'is', $recordId, $refId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            return $exists;
        } catch (mysqli_sql_exception $e) {
            error_log('RED component pairing lookup failed for ' . $table . ': ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_tool_delete_component_article')) {
    function red_admin_tool_delete_component_article($connection, $table, $recordId, $articleId)
    {
        if (!red_admin_tool_component_pair_exists($connection, $table, $recordId, $articleId)) {
            return false;
        }

        return red_admin_content_revision_delete_transaction(
            $connection,
            $articleId,
            function () use ($connection, $table, $recordId, $articleId) {
                $componentDeleted = red_admin_tool_delete_by_id($connection, $table, $recordId);
                $articleDeleted = red_admin_tool_delete_by_id($connection, 'RED_Articles', $articleId);

                return $componentDeleted && $articleDeleted;
            },
            [$table, 'RED_Articles']
        );
    }
}

?>
