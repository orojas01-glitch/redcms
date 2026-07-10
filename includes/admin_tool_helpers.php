<?php
/**
 * Helpers for admin content maintenance tool endpoints.
 */

require_once __DIR__ . '/admin_area_helpers.php';
require_once __DIR__ . '/admin_article_helpers.php';

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
        $layout = red_admin_tool_text($layout);
        if ($layout === '') {
            return 0;
        }

        $row = red_admin_tool_fetch_one(
            $connection,
            'SELECT Positions FROM RED_Layouts WHERE UniqueName=? LIMIT 1',
            's',
            [$layout],
            'RED_Layouts position lookup failed'
        );

        return $row ? max(0, (int) $row['Positions']) : 0;
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
    function red_admin_tool_move_articles($connection, $countPage, $section, $category, $subcategory, $article, $positionColumn, $filterPosition, $sortBy, $sortPositionColumn)
    {
        [$scopeSql, $types, $values] = red_admin_tool_article_scope($countPage, $section, $category, $subcategory, $article);
        $positionColumn = red_admin_tool_position_column($positionColumn, $sortPositionColumn);
        $sortPositionColumn = red_admin_tool_position_column($sortPositionColumn, $positionColumn);
        if ($positionColumn === '' || $sortPositionColumn === '') {
            return [];
        }

        $where = ['Active=\'Y\'', $scopeSql];
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
    function red_admin_tool_rows_by_group($connection, $compgroup)
    {
        $compgroup = red_admin_tool_text($compgroup);
        if (!in_array($compgroup, ['Content', 'Areas'], true)) {
            return [];
        }

        $rows = red_admin_tool_fetch_all(
            $connection,
            'SELECT UniqueName, ButtonTag, AltContent FROM RED_Tools WHERE CompGroup=? ORDER BY RecordID ASC',
            's',
            [$compgroup],
            'RED_Tools admin list lookup failed'
        );

        $tools = [];
        foreach ($rows as $row) {
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
        ];

        if ($component === '' || empty($adminComponentIds)) {
            return $access;
        }

        try {
            $stmt = mysqli_prepare($connection, 'SELECT CompGroup FROM RED_Components WHERE RecordID=? AND UniqueName=? LIMIT 1');
            if (!$stmt) {
                return $access;
            }

            $recordId = 0;
            mysqli_stmt_bind_param($stmt, 'is', $recordId, $component);
            foreach ($adminComponentIds as $adminComponentId) {
                $recordId = (int) $adminComponentId;
                if ($recordId <= 0 || !mysqli_stmt_execute($stmt)) {
                    continue;
                }

                $result = mysqli_stmt_get_result($stmt);
                $row = $result ? mysqli_fetch_assoc($result) : null;
                if ($row) {
                    $access['authorized'] = true;
                    $access['comp_group'] = red_admin_tool_text($row['CompGroup'] ?? '');
                    break;
                }
            }

            mysqli_stmt_close($stmt);

            if ($access['authorized'] && $access['comp_group'] === 'Y') {
                $tables = red_admin_tool_component_tables_by_name();
                if (isset($tables[$component]) && $articleRecordId > 0) {
                    $row = red_admin_tool_fetch_one(
                        $connection,
                        'SELECT RecordID FROM `' . $tables[$component] . '` WHERE RefID=? LIMIT 1',
                        's',
                        [(string) $articleRecordId],
                        'RED component record lookup failed'
                    );
                    $access['component_record_id'] = $row ? (int) $row['RecordID'] : 0;
                }
            }

            return $access;
        } catch (mysqli_sql_exception $e) {
            error_log('RED component authorization lookup failed: ' . $e->getMessage());
            return $access;
        }
    }
}

if (!function_exists('red_admin_tool_public_article_link')) {
    function red_admin_tool_public_article_link($section, $category, $subcategory, $article)
    {
        $segments = [];
        $section = red_admin_tool_text($section);
        $category = red_admin_tool_text($category);
        $subcategory = red_admin_tool_text($subcategory);
        $article = red_admin_tool_text($article);

        if ($section !== '' && $section !== 'home') {
            $segments[] = $section;
        }
        if ($category !== '') {
            $segments[] = $category;
        }
        if ($subcategory !== '') {
            $segments[] = $subcategory;
        }
        if ($article !== '') {
            $segments[] = $article;
        }

        if (empty($segments)) {
            return '/';
        }

        return '/' . implode('/', array_map('rawurlencode', $segments));
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

        $position = red_admin_tool_text($post['Position'] ?? '');
        if ($position !== '') {
            $positionColumn = red_admin_tool_text($post['VarPosition'] ?? '');
            if (!isset(red_admin_tool_article_position_columns()[$positionColumn])) {
                return false;
            }
            $updates[$positionColumn] = max(0, (int) red_admin_tool_scalar($position));
        }

        if (empty($updates)) {
            return false;
        }

        return red_admin_article_batch_transaction($connection, function () use ($connection, $recordIds, $updates) {
            foreach ($recordIds as $recordId) {
                if (!red_admin_article_record($connection, $recordId)) {
                    return false;
                }
                if (!red_admin_article_update($connection, $recordId, $updates)) {
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

if (!function_exists('red_admin_tool_update_layout')) {
    function red_admin_tool_update_layout($connection, $countpage, $section, $category, $subcategory, $article, $layout)
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
            switch ($countpage) {
                case 2:
                    $where = "((Sections='Home' AND Alias=?) OR (Article=?))";
                    $types .= 'ss';
                    array_push($values, $article, $article);
                    break;
                case 3:
                    if ($section === '') {
                        return false;
                    }
                    $where = '((Sections=? AND Alias=?) OR (Sections=? AND Article=?))';
                    $types .= 'ssss';
                    array_push($values, $section, $article, $section, $article);
                    break;
                case 4:
                    if ($section === '' || $category === '') {
                        return false;
                    }
                    $where = '((Sections=? AND Categories=? AND Alias=?) OR (Sections=? AND Categories=? AND Article=?))';
                    $types .= 'ssssss';
                    array_push($values, $section, $category, $article, $section, $category, $article);
                    break;
                case 5:
                case 6:
                    if ($section === '' || $category === '' || $subcategory === '') {
                        return false;
                    }
                    $where = '((Sections=? AND Categories=? AND SubCategories=? AND Alias=?) OR (Sections=? AND Categories=? AND SubCategories=? AND Article=?))';
                    $types .= 'ssssssss';
                    array_push($values, $section, $category, $subcategory, $article, $section, $category, $subcategory, $article);
                    break;
                default:
                    return false;
            }
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

        return red_admin_write_transaction($connection, function () use ($connection, $table, $recordId, $articleId) {
            $componentDeleted = red_admin_tool_delete_by_id($connection, $table, $recordId);
            $articleDeleted = red_admin_tool_delete_by_id($connection, 'RED_Articles', $articleId);

            return $componentDeleted && $articleDeleted;
        }, [$table, 'RED_Articles']);
    }
}

?>
