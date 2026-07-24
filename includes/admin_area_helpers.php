<?php
/**
 * Helpers for lightweight admin section/category/subcategory write endpoints.
 */

require_once __DIR__ . '/admin_transaction_helpers.php';
require_once __DIR__ . '/theme_helpers.php';

if (!function_exists('red_admin_scalar')) {
    function red_admin_scalar($value)
    {
        return is_array($value) ? '' : (string) $value;
    }
}

if (!function_exists('red_admin_text')) {
    function red_admin_text($value)
    {
        return trim(preg_replace("'<[^>]+>'U", '', red_admin_scalar($value)));
    }
}

if (!function_exists('red_admin_post_text')) {
    function red_admin_post_text($fieldName, $default = '')
    {
        return red_admin_text($_POST[$fieldName] ?? $default);
    }
}

if (!function_exists('red_admin_active_value')) {
    function red_admin_active_value($value)
    {
        return $value === 'N' ? 'N' : 'Y';
    }
}

if (!function_exists('red_admin_slug')) {
    function red_admin_slug($value, $replaceAmpersand = false)
    {
        $slug = red_admin_text($value);
        $slug = preg_replace('/\%/', ' percentage', $slug);
        $slug = preg_replace('/\@/', ' at ', $slug);
        if ($replaceAmpersand) {
            $slug = preg_replace('/\&/', ' and ', $slug);
        }
        $slug = preg_replace('/\s[\s]+/', '-', $slug);
        $slug = preg_replace('/[\s\W]+/', '-', $slug);
        $slug = preg_replace('/^[\-]+/', '', $slug);
        $slug = preg_replace('/[\-]+$/', '', $slug);
        return strtolower($slug);
    }
}

if (!function_exists('red_admin_tag_list')) {
    function red_admin_tag_list($value)
    {
        $tags = red_admin_text($value);
        $tags = preg_replace('/\%/', '', $tags);
        $tags = preg_replace('/\@/', '', $tags);
        $tags = preg_replace('/\&/', '', $tags);
        $tags = preg_replace('/\s[\s]+/', ',', $tags);
        $tags = preg_replace('/[\s\W]+/', ',', $tags);
        $tags = preg_replace('/^[,\-]+/', '', $tags);
        $tags = preg_replace('/[,\-]+$/', '', $tags);
        return strtolower($tags);
    }
}

if (!function_exists('red_admin_area_feature_catalog')) {
    function red_admin_area_feature_catalog()
    {
        return [
            'slider' => [
                'label' => 'Hero photo slider',
                'description' => 'Uses the main image and Slider summary from Articles selected in the Slider editor.',
            ],
        ];
    }
}

if (!function_exists('red_admin_area_feature_label')) {
    function red_admin_area_feature_label($featureName)
    {
        $featureName = red_admin_text($featureName);
        $catalog = red_admin_area_feature_catalog();

        return (string) ($catalog[$featureName]['label'] ?? $featureName);
    }
}

if (!function_exists('red_admin_area_feature_description')) {
    function red_admin_area_feature_description($featureName)
    {
        $featureName = red_admin_text($featureName);
        $catalog = red_admin_area_feature_catalog();

        return (string) ($catalog[$featureName]['description'] ?? '');
    }
}

if (!function_exists('red_admin_feature_list')) {
    function red_admin_feature_list($value)
    {
        $allowedFeatures = array_keys(red_admin_area_feature_catalog());
        $submittedFeatures = is_array($value)
            ? $value
            : explode(',', red_admin_text($value));
        $features = [];
        foreach ($submittedFeatures as $feature) {
            $feature = red_admin_text($feature);
            if (
                in_array($feature, $allowedFeatures, true)
                && !in_array($feature, $features, true)
            ) {
                $features[] = $feature;
            }
        }

        return implode(',', $features);
    }
}

if (!function_exists('red_admin_area_tables')) {
    function red_admin_area_tables()
    {
        return [
            'RED_Sections' => 'Sections',
            'RED_Categories' => 'Categories',
            'RED_SubCategories' => 'SubCategories',
        ];
    }
}

if (!function_exists('red_admin_area_language')) {
    function red_admin_area_language($default = 'sp')
    {
        return defined('language') ? (string) constant('language') : $default;
    }
}

if (!function_exists('red_admin_area_alias_count')) {
    function red_admin_area_alias_count($connection, $table, $column, $language, $alias)
    {
        $allowed = red_admin_area_tables();

        if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
            return 0;
        }

        $stmt = mysqli_prepare($connection, "SELECT `$column` FROM `$table` WHERE Language=? AND `$column`=? LIMIT 1");
        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 'ss', $language, $alias);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $count = mysqli_stmt_num_rows($stmt);
        mysqli_stmt_close($stmt);

        return $count;
    }
}

if (!function_exists('red_admin_area_alias_conflict')) {
    function red_admin_area_alias_conflict($connection, $language, $alias)
    {
        if (red_admin_area_alias_count($connection, 'RED_Sections', 'Sections', $language, $alias) > 0) {
            return 'error';
        }
        if (red_admin_area_alias_count($connection, 'RED_Categories', 'Categories', $language, $alias) > 0) {
            return 'error2';
        }
        if (red_admin_area_alias_count($connection, 'RED_SubCategories', 'SubCategories', $language, $alias) > 0) {
            return 'error3';
        }

        return '';
    }
}

if (!function_exists('red_admin_area_bind_values')) {
    function red_admin_area_bind_values($stmt, $types, &$values)
    {
        $refs = [];
        foreach ($values as $key => &$value) {
            $refs[$key] = &$value;
        }

        return mysqli_stmt_bind_param($stmt, $types, ...$refs);
    }
}

if (!function_exists('red_admin_area_execute_update')) {
    function red_admin_area_execute_update($connection, $sql, $types, $values, $logMessage)
    {
        try {
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                return false;
            }

            if ($types !== '' && !red_admin_area_bind_values($stmt, $types, $values)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            $affectedRows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            return $affectedRows;
        } catch (mysqli_sql_exception $e) {
            error_log($logMessage . ': ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_area_fetch_one')) {
    function red_admin_area_fetch_one($connection, $sql, $types, $values, $logMessage)
    {
        try {
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                return null;
            }

            if ($types !== '' && !red_admin_area_bind_values($stmt, $types, $values)) {
                mysqli_stmt_close($stmt);
                return null;
            }

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            return $row ?: null;
        } catch (mysqli_sql_exception $e) {
            error_log($logMessage . ': ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_area_fetch_all')) {
    function red_admin_area_fetch_all($connection, $sql, $types, $values, $logMessage)
    {
        try {
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                return [];
            }

            if ($types !== '' && !red_admin_area_bind_values($stmt, $types, $values)) {
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

if (!function_exists('red_admin_area_fetch_column')) {
    function red_admin_area_fetch_column($connection, $sql, $column, $logMessage)
    {
        try {
            $result = mysqli_query($connection, $sql);
            if (!$result) {
                return [];
            }

            $values = [];
            while ($row = mysqli_fetch_assoc($result)) {
                if (isset($row[$column])) {
                    $values[] = $row[$column];
                }
            }

            mysqli_free_result($result);
            return $values;
        } catch (mysqli_sql_exception $e) {
            error_log($logMessage . ': ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('red_admin_area_html')) {
    function red_admin_area_html($value)
    {
        return htmlspecialchars(red_admin_scalar($value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('red_admin_area_parent_column')) {
    function red_admin_area_parent_column($table)
    {
        $columns = [
            'RED_Categories' => 'SectionRecordID',
            'RED_SubCategories' => 'CategoryRecordID',
        ];

        return $columns[$table] ?? '';
    }
}

if (!function_exists('red_admin_area_public_path')) {
    function red_admin_area_public_path($section, $category = '', $subcategory = '')
    {
        $section = red_admin_text($section);
        $category = red_admin_text($category);
        $subcategory = red_admin_text($subcategory);
        $segments = [];

        if ($section !== '' && (strcasecmp($section, 'home') !== 0 || $category !== '' || $subcategory !== '')) {
            $segments[] = $section;
        }
        if ($category !== '') {
            $segments[] = $category;
        }
        if ($subcategory !== '') {
            $segments[] = $subcategory;
        }

        return $segments === []
            ? '/'
            : '/' . implode('/', array_map('rawurlencode', $segments)) . '/';
    }
}

if (!function_exists('red_admin_area_parent_options')) {
    function red_admin_area_parent_options($connection, $table, $language)
    {
        $language = substr(red_admin_text($language), 0, 2);
        if ($language === '') {
            return [];
        }

        if ($table === 'RED_Categories') {
            return red_admin_area_fetch_all(
                $connection,
                'SELECT RecordID AS ParentRecordID, Sections AS ParentAlias, Title AS ParentTitle, ' .
                    'Active AS ParentActive, Sections AS SectionAlias, Title AS SectionTitle ' .
                    'FROM RED_Sections WHERE Language=? ORDER BY Title ASC, Sections ASC, RecordID ASC',
                's',
                [$language],
                'RED_Categories parent Section lookup failed'
            );
        }

        if ($table === 'RED_SubCategories') {
            return red_admin_area_fetch_all(
                $connection,
                'SELECT category_area.RecordID AS ParentRecordID, ' .
                    'category_area.Categories AS ParentAlias, category_area.Title AS ParentTitle, ' .
                    'category_area.Active AS ParentActive, section_area.RecordID AS SectionRecordID, ' .
                    'section_area.Sections AS SectionAlias, section_area.Title AS SectionTitle ' .
                    'FROM RED_Categories AS category_area ' .
                    'JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                    'AND section_area.Language=category_area.Language ' .
                    'WHERE category_area.Language=? ' .
                    'ORDER BY section_area.Title ASC, category_area.Title ASC, category_area.RecordID ASC',
                's',
                [$language],
                'RED_SubCategories parent Category lookup failed'
            );
        }

        return [];
    }
}

if (!function_exists('red_admin_area_parent_context')) {
    function red_admin_area_parent_context($connection, $table, $parentRecordId, $language)
    {
        $parentRecordId = (int) red_admin_scalar($parentRecordId);
        $language = substr(red_admin_text($language), 0, 2);
        if ($parentRecordId <= 0 || $language === '') {
            return null;
        }

        if ($table === 'RED_Categories') {
            $row = red_admin_area_fetch_one(
                $connection,
                'SELECT RecordID AS ParentRecordID, Sections AS ParentAlias, Title AS ParentTitle, ' .
                    'Sections AS SectionAlias, Title AS SectionTitle, Active AS ParentActive ' .
                    'FROM RED_Sections WHERE RecordID=? AND Language=? LIMIT 1',
                'is',
                [$parentRecordId, $language],
                'RED_Categories parent Section validation failed'
            );
            if (!$row) {
                return null;
            }
            $row['path'] = red_admin_area_public_path($row['SectionAlias'] ?? '');
            return $row;
        }

        if ($table === 'RED_SubCategories') {
            $row = red_admin_area_fetch_one(
                $connection,
                'SELECT category_area.RecordID AS ParentRecordID, ' .
                    'category_area.Categories AS ParentAlias, category_area.Title AS ParentTitle, ' .
                    'category_area.Active AS ParentActive, section_area.RecordID AS SectionRecordID, ' .
                    'section_area.Sections AS SectionAlias, section_area.Title AS SectionTitle ' .
                    'FROM RED_Categories AS category_area ' .
                    'JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                    'AND section_area.Language=category_area.Language ' .
                    'WHERE category_area.RecordID=? AND category_area.Language=? LIMIT 1',
                'is',
                [$parentRecordId, $language],
                'RED_SubCategories parent Category validation failed'
            );
            if (!$row) {
                return null;
            }
            $row['path'] = red_admin_area_public_path(
                $row['SectionAlias'] ?? '',
                $row['ParentAlias'] ?? ''
            );
            return $row;
        }

        return null;
    }
}

if (!function_exists('red_admin_area_record_route_context')) {
    function red_admin_area_record_route_context($connection, $table, $recordId)
    {
        $recordId = (int) red_admin_scalar($recordId);
        if ($recordId <= 0) {
            return null;
        }

        if ($table === 'RED_Categories') {
            $row = red_admin_area_fetch_one(
                $connection,
                'SELECT category_area.RecordID, category_area.SectionRecordID AS ParentRecordID, ' .
                    'category_area.Categories AS Alias, category_area.Language, ' .
                    'section_area.Sections AS SectionAlias ' .
                    'FROM RED_Categories AS category_area ' .
                    'LEFT JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                    'AND section_area.Language=category_area.Language ' .
                    'WHERE category_area.RecordID=? LIMIT 1',
                'i',
                [$recordId],
                'RED_Categories route context lookup failed'
            );
            if (!$row) {
                return null;
            }
            $row['CategoryAlias'] = red_admin_text($row['Alias'] ?? '');
            $row['SubCategoryAlias'] = '';
            $row['path'] = ($row['SectionAlias'] ?? '') !== ''
                ? red_admin_area_public_path($row['SectionAlias'], $row['CategoryAlias'])
                : '';
            return $row;
        }

        if ($table === 'RED_SubCategories') {
            $row = red_admin_area_fetch_one(
                $connection,
                'SELECT subcategory_area.RecordID, subcategory_area.CategoryRecordID AS ParentRecordID, ' .
                    'subcategory_area.SubCategories AS Alias, subcategory_area.Language, ' .
                    'category_area.Categories AS CategoryAlias, section_area.Sections AS SectionAlias ' .
                    'FROM RED_SubCategories AS subcategory_area ' .
                    'LEFT JOIN RED_Categories AS category_area ON category_area.RecordID=subcategory_area.CategoryRecordID ' .
                    'AND category_area.Language=subcategory_area.Language ' .
                    'LEFT JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                    'AND section_area.Language=category_area.Language ' .
                    'WHERE subcategory_area.RecordID=? LIMIT 1',
                'i',
                [$recordId],
                'RED_SubCategories route context lookup failed'
            );
            if (!$row) {
                return null;
            }
            $row['SubCategoryAlias'] = red_admin_text($row['Alias'] ?? '');
            $row['path'] = ($row['SectionAlias'] ?? '') !== '' && ($row['CategoryAlias'] ?? '') !== ''
                ? red_admin_area_public_path(
                    $row['SectionAlias'],
                    $row['CategoryAlias'],
                    $row['SubCategoryAlias']
                )
                : '';
            return $row;
        }

        return null;
    }
}

if (!function_exists('red_admin_area_record')) {
    function red_admin_area_record($connection, $table, $recordId)
    {
        $allowedTables = red_admin_area_tables();
        if (!isset($allowedTables[$table])) {
            return null;
        }

        $recordId = (int) red_admin_scalar($recordId);
        if ($recordId <= 0) {
            return null;
        }

        return red_admin_area_fetch_one(
            $connection,
            'SELECT * FROM `' . $table . '` WHERE RecordID=? LIMIT 1',
            'i',
            [$recordId],
            $table . ' area record lookup failed'
        );
    }
}

if (!function_exists('red_admin_area_layouts')) {
    function red_admin_area_layouts($connection)
    {
        try {
            $contract = red_theme_active_layout_contract($connection);
            return array_keys($contract['catalog']);
        } catch (Throwable $exception) {
            error_log('Active theme layout catalog unavailable; using legacy registry: ' . $exception->getMessage());
            return red_admin_area_fetch_column(
                $connection,
                'SELECT UniqueName FROM RED_Layouts ORDER BY UniqueName ASC',
                'UniqueName',
                'RED_Layouts lookup failed'
            );
        }
    }
}

if (!function_exists('red_admin_area_layout_options')) {
    function red_admin_area_layout_options($connection, $currentLayout = '')
    {
        $currentLayout = red_admin_text($currentLayout);
        try {
            $contract = red_theme_active_layout_contract($connection);
            $options = [];
            foreach ($contract['catalog'] as $layoutId => $definition) {
                $options[$layoutId] = $definition['label'];
            }

            if ($currentLayout !== '' && !isset($options[$currentLayout])) {
                $currentDefinition = red_theme_layout_definition($contract['manifest'], $currentLayout);
                if ($currentDefinition !== null) {
                    $options = [$currentLayout => $currentDefinition['label'] . ' (compatibility id)'] + $options;
                } else {
                    $options = [$currentLayout => $currentLayout . ' (unavailable; preserved)'] + $options;
                }
            }

            return $options;
        } catch (Throwable $exception) {
            $options = [];
            foreach (red_admin_area_layouts($connection) as $layoutId) {
                $options[$layoutId] = $layoutId;
            }
            if ($currentLayout !== '' && !isset($options[$currentLayout])) {
                $options = [$currentLayout => $currentLayout . ' (unavailable; preserved)'] + $options;
            }
            return $options;
        }
    }
}

if (!function_exists('red_admin_area_layout_definition')) {
    function red_admin_area_layout_definition($connection, $layout)
    {
        try {
            return red_theme_active_layout_definition($connection, red_admin_text($layout));
        } catch (Throwable $exception) {
            return null;
        }
    }
}

if (!function_exists('red_admin_area_layout_position_options')) {
    function red_admin_area_layout_position_options($connection, $layout, $includeHidden = true)
    {
        $definition = red_admin_area_layout_definition($connection, $layout);
        if ($definition === null) {
            return [];
        }

        $positions = $definition['positions'];
        if ($includeHidden && $definition['hiddenPosition'] === 0) {
            $positions = [0 => 'Hidden'] + $positions;
        }
        return $positions;
    }
}

if (!function_exists('red_admin_area_layout_supports_positions')) {
    function red_admin_area_layout_supports_positions($connection, $layout, array $positionIds)
    {
        $definition = red_admin_area_layout_definition($connection, $layout);
        if ($definition === null) {
            return false;
        }

        foreach ($positionIds as $positionId) {
            $positionId = (int) $positionId;
            if ($positionId === 0) {
                continue;
            }
            if ($positionId < 1 || $positionId > 99 || !isset($definition['positions'][$positionId])) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_admin_area_used_positions')) {
    function red_admin_area_used_positions($connection, $table, $aliasColumn, $alias, $language)
    {
        $allowedTables = red_admin_area_tables();
        if (!isset($allowedTables[$table]) || $allowedTables[$table] !== $aliasColumn) {
            return null;
        }

        $alias = red_admin_text($alias);
        $language = substr(red_admin_text($language), 0, 2);
        if ($alias === '' || $language === '') {
            return null;
        }

        $positionColumn = [
            'RED_Sections' => strtolower($alias) === 'home' ? 'HomePosition' : 'SectionPosition',
            'RED_Categories' => 'CategoryPosition',
            'RED_SubCategories' => 'SubCategoryPosition',
        ][$table];
        $where = 'Language=? AND `' . $positionColumn . '`>0';
        $types = 's';
        $values = [$language];
        if (!($table === 'RED_Sections' && strtolower($alias) === 'home')) {
            $where .= ' AND `' . $aliasColumn . '`=?';
            $types .= 's';
            $values[] = $alias;
        }

        $rows = red_admin_area_fetch_all(
            $connection,
            'SELECT DISTINCT `' . $positionColumn . '` AS position_id FROM RED_Articles WHERE ' .
                $where . ' ORDER BY `' . $positionColumn . '` ASC',
            $types,
            $values,
            $table . ' used-position lookup failed'
        );

        return array_values(array_map(static function (array $row) {
            return (int) ($row['position_id'] ?? 0);
        }, $rows));
    }
}

if (!function_exists('red_admin_area_features')) {
    function red_admin_area_features($connection)
    {
        $registeredFeatures = red_admin_area_fetch_column(
            $connection,
            'SELECT UniqueName FROM RED_Features ORDER BY UniqueName ASC',
            'UniqueName',
            'RED_Features lookup failed'
        );
        $registeredLookup = array_fill_keys($registeredFeatures, true);

        return array_values(array_filter(
            array_keys(red_admin_area_feature_catalog()),
            static function ($featureName) use ($registeredLookup) {
                return isset($registeredLookup[$featureName]);
            }
        ));
    }
}

if (!function_exists('red_admin_area_list_rows')) {
    function red_admin_area_list_rows($connection, $table, $language)
    {
        $allowedTables = red_admin_area_tables();
        if (!isset($allowedTables[$table])) {
            return [];
        }

        $aliasColumn = $allowedTables[$table];
        $language = red_admin_text($language);
        if ($language === '') {
            return [];
        }

        if ($table === 'RED_Categories') {
            return red_admin_area_fetch_all(
                $connection,
                'SELECT category_area.RecordID, category_area.Title, category_area.Categories, ' .
                    'category_area.Layout, category_area.Active, category_area.SectionRecordID, ' .
                    'section_area.Title AS ParentTitle, section_area.Sections AS ParentAlias ' .
                    'FROM RED_Categories AS category_area ' .
                    'LEFT JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                    'AND section_area.Language=category_area.Language ' .
                    'WHERE category_area.Language=? ORDER BY category_area.RecordID ASC',
                's',
                [$language],
                $table . ' admin list lookup failed'
            );
        }

        if ($table === 'RED_SubCategories') {
            return red_admin_area_fetch_all(
                $connection,
                'SELECT subcategory_area.RecordID, subcategory_area.Title, subcategory_area.SubCategories, ' .
                    'subcategory_area.Layout, subcategory_area.Active, subcategory_area.CategoryRecordID, ' .
                    'category_area.Title AS ParentTitle, category_area.Categories AS ParentAlias, ' .
                    'section_area.Title AS SectionTitle, section_area.Sections AS SectionAlias ' .
                    'FROM RED_SubCategories AS subcategory_area ' .
                    'LEFT JOIN RED_Categories AS category_area ON category_area.RecordID=subcategory_area.CategoryRecordID ' .
                    'AND category_area.Language=subcategory_area.Language ' .
                    'LEFT JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                    'AND section_area.Language=category_area.Language ' .
                    'WHERE subcategory_area.Language=? ORDER BY subcategory_area.RecordID ASC',
                's',
                [$language],
                $table . ' admin list lookup failed'
            );
        }

        return red_admin_area_fetch_all(
            $connection,
            'SELECT RecordID, Title, `' . $aliasColumn . '`, Layout, Active FROM `' . $table . '` WHERE Language=? ORDER BY RecordID ASC',
            's',
            [$language],
            $table . ' admin list lookup failed'
        );
    }
}

if (!function_exists('red_admin_area_child_count')) {
    function red_admin_area_child_count($connection, $table, $recordId)
    {
        $recordId = (int) red_admin_scalar($recordId);
        $config = [
            'RED_Sections' => ['table' => 'RED_Categories', 'column' => 'SectionRecordID'],
            'RED_Categories' => ['table' => 'RED_SubCategories', 'column' => 'CategoryRecordID'],
        ][$table] ?? null;
        if ($recordId <= 0 || !$config) {
            return 0;
        }

        $row = red_admin_area_fetch_one(
            $connection,
            'SELECT COUNT(*) AS child_count FROM `' . $config['table'] . '` WHERE `' . $config['column'] . '`=?',
            'i',
            [$recordId],
            $table . ' child-area count failed'
        );

        return $row ? (int) ($row['child_count'] ?? 0) : 0;
    }
}

if (!function_exists('red_admin_area_related_article_count')) {
    function red_admin_area_related_article_count($connection, $aliasColumn, $alias, $language = '')
    {
        if (!in_array($aliasColumn, red_admin_area_tables(), true)) {
            return 0;
        }

        $alias = red_admin_text($alias);
        $language = substr(red_admin_text($language), 0, 2);
        if ($alias === '') {
            return 0;
        }

        try {
            $sql = "SELECT COUNT(*) AS related_count FROM RED_Articles WHERE `$aliasColumn`=?";
            if ($language !== '') {
                $sql .= ' AND Language=?';
            }
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                return 0;
            }

            if ($language === '') {
                mysqli_stmt_bind_param($stmt, 's', $alias);
            } else {
                mysqli_stmt_bind_param($stmt, 'ss', $alias, $language);
            }
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return 0;
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            return $row ? (int) $row['related_count'] : 0;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Articles related area count failed: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('red_admin_section_archive_and_delete')) {
    function red_admin_section_archive_and_delete($connection, $recordId)
    {
        $recordId = (int) red_admin_scalar($recordId);
        if ($recordId <= 0 || red_admin_area_child_count($connection, 'RED_Sections', $recordId) > 0) {
            return false;
        }

        $archivedArticleCount = 0;
        $success = red_admin_theme_contract_write_transaction(
            $connection,
            function () use ($connection, $recordId, &$archivedArticleCount) {
                $sectionStmt = mysqli_prepare(
                    $connection,
                    'SELECT Sections, Language FROM RED_Sections WHERE RecordID=? LIMIT 1 FOR UPDATE'
                );
                if (!$sectionStmt) {
                    return false;
                }

                mysqli_stmt_bind_param($sectionStmt, 'i', $recordId);
                if (!mysqli_stmt_execute($sectionStmt)) {
                    mysqli_stmt_close($sectionStmt);
                    return false;
                }
                $sectionResult = mysqli_stmt_get_result($sectionStmt);
                $section = $sectionResult ? mysqli_fetch_assoc($sectionResult) : null;
                mysqli_stmt_close($sectionStmt);
                if (!$section) {
                    return false;
                }

                $sectionAlias = red_admin_text($section['Sections'] ?? '');
                $language = red_admin_text($section['Language'] ?? '');
                if ($sectionAlias === '' || $language === '') {
                    return false;
                }

                $countStmt = mysqli_prepare(
                    $connection,
                    'SELECT COUNT(*) AS related_count FROM RED_Articles WHERE Sections=? AND Language=?'
                );
                if (!$countStmt) {
                    return false;
                }
                mysqli_stmt_bind_param($countStmt, 'ss', $sectionAlias, $language);
                if (!mysqli_stmt_execute($countStmt)) {
                    mysqli_stmt_close($countStmt);
                    return false;
                }
                $countResult = mysqli_stmt_get_result($countStmt);
                $countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
                mysqli_stmt_close($countStmt);
                if (!$countRow) {
                    return false;
                }
                $archivedArticleCount = (int) $countRow['related_count'];

                $archiveStmt = mysqli_prepare(
                    $connection,
                    "UPDATE RED_Articles SET Active='N' WHERE Sections=? AND Language=? AND Active<>'N'"
                );
                if (!$archiveStmt) {
                    return false;
                }
                mysqli_stmt_bind_param($archiveStmt, 'ss', $sectionAlias, $language);
                $archiveSuccess = mysqli_stmt_execute($archiveStmt);
                mysqli_stmt_close($archiveStmt);
                if (!$archiveSuccess) {
                    return false;
                }

                $deleteStmt = mysqli_prepare(
                    $connection,
                    'DELETE FROM RED_Sections WHERE RecordID=? AND Sections=? AND Language=?'
                );
                if (!$deleteStmt) {
                    return false;
                }
                mysqli_stmt_bind_param($deleteStmt, 'iss', $recordId, $sectionAlias, $language);
                $deleteSuccess = mysqli_stmt_execute($deleteStmt)
                    && mysqli_stmt_affected_rows($deleteStmt) === 1;
                mysqli_stmt_close($deleteStmt);

                return $deleteSuccess;
            },
            ['RED_Sections', 'RED_Articles']
        );

        return $success
            ? ['archivedArticles' => $archivedArticleCount]
            : false;
    }
}

if (!function_exists('red_admin_area_update_columns')) {
    function red_admin_area_update_columns($table, $aliasColumn)
    {
        $columns = [
            'Title' => true,
            $aliasColumn => true,
            'Active' => true,
            'Layout' => true,
            'QueryLimit' => true,
            'AccessLevel' => true,
            'Description' => true,
            'Tags' => true,
            'Features' => true,
        ];
        $parentColumn = red_admin_area_parent_column($table);
        if ($parentColumn !== '') {
            $columns[$parentColumn] = true;
        }

        return $columns;
    }
}

if (!function_exists('red_admin_area_update_payload')) {
    function red_admin_area_update_payload($post, $aliasColumn, $table = '')
    {
        $data = [];

        if (array_key_exists('Title', $post)) {
            $data['Title'] = red_admin_text($post['Title']);
        }
        if (array_key_exists($aliasColumn, $post)) {
            $data[$aliasColumn] = red_admin_slug($post[$aliasColumn]);
        }
        if (array_key_exists('Active', $post)) {
            $data['Active'] = red_admin_active_value(red_admin_text($post['Active']));
        }
        if (array_key_exists('Layout', $post)) {
            $data['Layout'] = red_admin_text($post['Layout']);
        }
        if (array_key_exists('QueryLimit', $post)) {
            $data['QueryLimit'] = (string) max(0, (int) red_admin_text($post['QueryLimit']));
        }
        if (array_key_exists('AccessLevel', $post)) {
            $data['AccessLevel'] = red_admin_text($post['AccessLevel']);
        }
        if (array_key_exists('Description', $post)) {
            $data['Description'] = red_admin_text($post['Description']);
        }
        if (array_key_exists('Tags', $post)) {
            $data['Tags'] = red_admin_tag_list($post['Tags']);
        }

        $data['Features'] = red_admin_feature_list($post['Features'] ?? []);
        $parentColumn = red_admin_area_parent_column($table);
        if ($parentColumn !== '' && array_key_exists($parentColumn, $post)) {
            $data[$parentColumn] = (int) red_admin_scalar($post[$parentColumn]);
        }

        return $data;
    }
}

if (!function_exists('red_admin_update_area_unlocked')) {
    function red_admin_update_area_unlocked($connection, $table, $aliasColumn, $recordId, $data)
    {
        $allowedTables = red_admin_area_tables();
        if (!isset($allowedTables[$table]) || $allowedTables[$table] !== $aliasColumn) {
            return false;
        }

        $recordId = (int) red_admin_scalar($recordId);
        if ($recordId <= 0 || empty($data)) {
            return false;
        }

        if (array_key_exists('Layout', $data)) {
            $existing = red_admin_area_record($connection, $table, $recordId);
            if (!$existing) {
                return false;
            }
            $currentLayout = red_admin_text($existing['Layout'] ?? '');
            $targetLayout = red_admin_text($data['Layout']);
            if ($targetLayout !== $currentLayout) {
                if (red_admin_area_layout_definition($connection, $targetLayout) === null) {
                    return false;
                }
                $targetAlias = red_admin_text($data[$aliasColumn] ?? ($existing[$aliasColumn] ?? ''));
                $usedPositions = red_admin_area_used_positions(
                    $connection,
                    $table,
                    $aliasColumn,
                    $targetAlias,
                    $existing['Language'] ?? ''
                );
                if (!is_array($usedPositions)
                    || !red_admin_area_layout_supports_positions($connection, $targetLayout, $usedPositions)
                ) {
                    return false;
                }
            }
        }

        $allowedColumns = red_admin_area_update_columns($table, $aliasColumn);
        $sets = [];
        $types = '';
        $values = [];

        foreach ($data as $fieldName => $value) {
            if (!isset($allowedColumns[$fieldName])) {
                continue;
            }

            $sets[] = "`$fieldName`=?";
            $isParentColumn = $fieldName === red_admin_area_parent_column($table);
            $types .= $isParentColumn ? 'i' : 's';
            $values[] = $isParentColumn ? (int) $value : red_admin_scalar($value);
        }

        if (empty($sets)) {
            return false;
        }

        $types .= 'i';
        $values[] = $recordId;

        return red_admin_area_execute_update(
            $connection,
            'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE RecordID=?',
            $types,
            $values,
            $table . ' area update failed'
        );
    }
}

if (!function_exists('red_admin_update_area')) {
    function red_admin_update_area($connection, $table, $aliasColumn, $recordId, $data)
    {
        return red_admin_with_theme_contract_lock(
            $connection,
            function () use ($connection, $table, $aliasColumn, $recordId, $data) {
                return red_admin_update_area_unlocked(
                    $connection,
                    $table,
                    $aliasColumn,
                    $recordId,
                    $data
                );
            }
        );
    }
}

if (!function_exists('red_admin_area_update_articles')) {
    function red_admin_area_update_articles($connection, $aliasColumn, $newAlias, $currentAlias, $language)
    {
        if (!in_array($aliasColumn, red_admin_area_tables(), true)) {
            return false;
        }

        $newAlias = red_admin_text($newAlias);
        $currentAlias = red_admin_text($currentAlias);
        $language = red_admin_text($language);
        if ($newAlias === '' || $currentAlias === '' || $language === '') {
            return false;
        }

        return red_admin_area_execute_update(
            $connection,
            "UPDATE RED_Articles SET `$aliasColumn`=? WHERE Language=? AND `$aliasColumn`=?",
            'sss',
            [$newAlias, $language, $currentAlias],
            'RED_Articles area alias update failed'
        );
    }
}

if (!function_exists('red_admin_area_update_menu_links')) {
    function red_admin_area_update_menu_links($connection, $table, $newAlias, $currentAlias)
    {
        if (!in_array($table, ['RED_Menu', 'RED_C_Menu'], true)) {
            return false;
        }

        $newAlias = red_admin_text($newAlias);
        $currentAlias = red_admin_text($currentAlias);
        if ($newAlias === '' || $currentAlias === '') {
            return false;
        }

        return red_admin_area_execute_update(
            $connection,
            'UPDATE `' . $table . '` SET Link=replace(Link, ?, ?)',
            'ss',
            ['/' . $currentAlias . '/', '/' . $newAlias . '/'],
            $table . ' menu link update failed'
        );
    }
}

if (!function_exists('red_admin_area_rename')) {
    function red_admin_area_rename($connection, $table, $aliasColumn, $recordId, $data, $currentAlias, $newAlias, $language)
    {
        $allowedTables = red_admin_area_tables();
        if (!isset($allowedTables[$table]) || $allowedTables[$table] !== $aliasColumn) {
            return false;
        }

        $response = '';
        $success = red_admin_theme_contract_write_transaction(
            $connection,
            function () use ($connection, $table, $aliasColumn, $recordId, $data, $currentAlias, $newAlias, $language, &$response) {
                $articleRows = red_admin_area_update_articles($connection, $aliasColumn, $newAlias, $currentAlias, $language);
                if ($articleRows === false) {
                    return false;
                }
                if ($articleRows > 0) {
                    $response .= 'update';
                }

                $menuRows = red_admin_area_update_menu_links($connection, 'RED_Menu', $newAlias, $currentAlias);
                if ($menuRows === false) {
                    return false;
                }
                if ($menuRows > 0) {
                    $response .= 'update';
                }

                $componentMenuRows = red_admin_area_update_menu_links($connection, 'RED_C_Menu', $newAlias, $currentAlias);
                if ($componentMenuRows === false) {
                    return false;
                }
                if ($componentMenuRows > 0) {
                    $response .= 'update';
                }

                $areaRows = red_admin_update_area($connection, $table, $aliasColumn, $recordId, $data);
                return $areaRows !== false && $areaRows > 0;
            },
            [$table, 'RED_Articles', 'RED_Menu', 'RED_C_Menu']
        );

        return $success ? $response . 'yes' : false;
    }
}

if (!function_exists('red_admin_area_update_menu_path')) {
    function red_admin_area_update_menu_path($connection, $table, $oldPath, $newPath)
    {
        if (!in_array($table, ['RED_Menu', 'RED_C_Menu'], true)) {
            return false;
        }

        $oldPath = red_admin_text($oldPath);
        $newPath = red_admin_text($newPath);
        if ($oldPath === '' || $newPath === '' || $oldPath === $newPath) {
            return 0;
        }

        return red_admin_area_execute_update(
            $connection,
            'UPDATE `' . $table . '` SET Link=replace(Link, ?, ?)',
            'ss',
            [$oldPath, $newPath],
            $table . ' parent route update failed'
        );
    }
}

if (!function_exists('red_admin_area_update_owned_route')) {
    function red_admin_area_update_owned_route(
        $connection,
        $table,
        $currentAlias,
        $newAlias,
        $language,
        array $parentContext
    ) {
        $currentAlias = red_admin_text($currentAlias);
        $newAlias = red_admin_text($newAlias);
        $language = substr(red_admin_text($language), 0, 2);
        $sectionAlias = red_admin_text($parentContext['SectionAlias'] ?? '');
        if ($currentAlias === '' || $newAlias === '' || $language === '' || $sectionAlias === '') {
            return false;
        }

        if ($table === 'RED_Categories') {
            return red_admin_area_execute_update(
                $connection,
                'UPDATE RED_Articles SET Categories=?, Sections=? WHERE Language=? AND Categories=?',
                'ssss',
                [$newAlias, $sectionAlias, $language, $currentAlias],
                'RED_Articles Category parent route update failed'
            );
        }

        if ($table === 'RED_SubCategories') {
            $categoryAlias = red_admin_text($parentContext['ParentAlias'] ?? '');
            if ($categoryAlias === '') {
                return false;
            }
            return red_admin_area_execute_update(
                $connection,
                'UPDATE RED_Articles SET SubCategories=?, Categories=?, Sections=? ' .
                    'WHERE Language=? AND SubCategories=?',
                'sssss',
                [$newAlias, $categoryAlias, $sectionAlias, $language, $currentAlias],
                'RED_Articles Subcategory parent route update failed'
            );
        }

        return false;
    }
}

if (!function_exists('red_admin_area_save_existing')) {
    function red_admin_area_save_existing($connection, $table, $aliasColumn, $recordId, array $data)
    {
        $allowedTables = red_admin_area_tables();
        if (!isset($allowedTables[$table]) || $allowedTables[$table] !== $aliasColumn) {
            return false;
        }

        $recordId = (int) red_admin_scalar($recordId);
        $existing = red_admin_area_record($connection, $table, $recordId);
        if (!$existing) {
            return false;
        }

        $currentAlias = red_admin_text($existing[$aliasColumn] ?? '');
        $newAlias = red_admin_text($data[$aliasColumn] ?? $currentAlias);
        $language = substr(red_admin_text($existing['Language'] ?? ''), 0, 2);
        $parentColumn = red_admin_area_parent_column($table);
        if ($recordId <= 0 || $currentAlias === '' || $newAlias === '' || $language === '') {
            return false;
        }

        $parentContext = null;
        $currentContext = null;
        $routeChanged = $newAlias !== $currentAlias;
        $newPath = '';
        $oldPath = '';
        if ($parentColumn !== '') {
            $parentRecordId = (int) ($data[$parentColumn] ?? 0);
            $parentContext = red_admin_area_parent_context(
                $connection,
                $table,
                $parentRecordId,
                $language
            );
            if (!$parentContext) {
                return false;
            }

            $currentContext = red_admin_area_record_route_context($connection, $table, $recordId);
            if (!$currentContext) {
                return false;
            }
            $routeChanged = $routeChanged
                || (int) ($currentContext['ParentRecordID'] ?? 0) !== $parentRecordId;
            $oldPath = red_admin_text($currentContext['path'] ?? '');
            if ($table === 'RED_Categories') {
                $newPath = red_admin_area_public_path(
                    $parentContext['SectionAlias'] ?? '',
                    $newAlias
                );
            } else {
                $newPath = red_admin_area_public_path(
                    $parentContext['SectionAlias'] ?? '',
                    $parentContext['ParentAlias'] ?? '',
                    $newAlias
                );
            }
        }

        $response = '';
        $success = red_admin_theme_contract_write_transaction(
            $connection,
            function () use (
                $connection,
                $table,
                $aliasColumn,
                $recordId,
                $data,
                $currentAlias,
                $newAlias,
                $language,
                $parentContext,
                $routeChanged,
                $oldPath,
                $newPath,
                &$response
            ) {
                if ($routeChanged && is_array($parentContext)) {
                    $articleRows = red_admin_area_update_owned_route(
                        $connection,
                        $table,
                        $currentAlias,
                        $newAlias,
                        $language,
                        $parentContext
                    );
                    if ($articleRows === false) {
                        return false;
                    }
                    if ($articleRows > 0) {
                        $response .= 'update';
                    }

                    if ($oldPath !== '' && $newPath !== '') {
                        foreach (['RED_Menu', 'RED_C_Menu'] as $menuTable) {
                            $menuRows = red_admin_area_update_menu_path(
                                $connection,
                                $menuTable,
                                $oldPath,
                                $newPath
                            );
                            if ($menuRows === false) {
                                return false;
                            }
                            if ($menuRows > 0) {
                                $response .= 'update';
                            }
                        }
                    } elseif ($newAlias !== $currentAlias) {
                        foreach (['RED_Menu', 'RED_C_Menu'] as $menuTable) {
                            $menuRows = red_admin_area_update_menu_links(
                                $connection,
                                $menuTable,
                                $newAlias,
                                $currentAlias
                            );
                            if ($menuRows === false) {
                                return false;
                            }
                            if ($menuRows > 0) {
                                $response .= 'update';
                            }
                        }
                    }
                } elseif ($newAlias !== $currentAlias) {
                    $articleRows = red_admin_area_update_articles(
                        $connection,
                        $aliasColumn,
                        $newAlias,
                        $currentAlias,
                        $language
                    );
                    if ($articleRows === false) {
                        return false;
                    }
                    if ($articleRows > 0) {
                        $response .= 'update';
                    }
                }

                $areaRows = red_admin_update_area_unlocked(
                    $connection,
                    $table,
                    $aliasColumn,
                    $recordId,
                    $data
                );

                return $areaRows !== false;
            },
            [$table, 'RED_Articles', 'RED_Menu', 'RED_C_Menu']
        );

        if (!$success) {
            return false;
        }

        return [
            'response' => $response . 'yes',
            'alias' => $newAlias,
            'path' => $newPath,
            'routeChanged' => $routeChanged,
        ];
    }
}

if (!function_exists('red_admin_area_delete_record')) {
    function red_admin_area_delete_record($connection, $table, $recordId)
    {
        $recordId = (int) red_admin_scalar($recordId);
        if (!in_array($table, ['RED_Categories', 'RED_SubCategories'], true) || $recordId <= 0) {
            return false;
        }
        if ($table === 'RED_Categories'
            && red_admin_area_child_count($connection, 'RED_Categories', $recordId) > 0
        ) {
            return false;
        }

        return red_admin_theme_contract_write_transaction(
            $connection,
            function () use ($connection, $table, $recordId) {
                try {
                    $stmt = mysqli_prepare(
                        $connection,
                        'DELETE FROM `' . $table . '` WHERE RecordID=?'
                    );
                    if (!$stmt) {
                        return false;
                    }
                    mysqli_stmt_bind_param($stmt, 'i', $recordId);
                    $deleted = mysqli_stmt_execute($stmt)
                        && mysqli_stmt_affected_rows($stmt) === 1;
                    mysqli_stmt_close($stmt);
                    return $deleted;
                } catch (mysqli_sql_exception $exception) {
                    error_log($table . ' protected area delete failed: ' . $exception->getMessage());
                    return false;
                }
            },
            [$table]
        );
    }
}

if (!function_exists('red_admin_insert_area_unlocked')) {
    function red_admin_insert_area_unlocked(
        $connection,
        $table,
        $aliasColumn,
        $title,
        $alias,
        $layout,
        $queryLimit,
        $accessLevel,
        $features,
        $active,
        $description,
        $tags,
        $language,
        $parentRecordId = 0
    )
    {
        $allowed = [
            'RED_Sections' => 'Sections',
            'RED_Categories' => 'Categories',
            'RED_SubCategories' => 'SubCategories',
        ];

        if (!isset($allowed[$table]) || $allowed[$table] !== $aliasColumn) {
            return false;
        }
        if (red_admin_area_layout_definition($connection, $layout) === null) {
            return false;
        }

        $parentColumn = red_admin_area_parent_column($table);
        $parentRecordId = (int) $parentRecordId;
        if ($parentColumn !== '' && !red_admin_area_parent_context($connection, $table, $parentRecordId, $language)) {
            return false;
        }

        $columns = 'Title, `' . $aliasColumn . '`, Layout, QueryLimit, AccessLevel, Features, Active, Description, Tags, Language';
        $placeholders = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
        if ($parentColumn !== '') {
            $columns = '`' . $parentColumn . '`, ' . $columns;
            $placeholders = '?, ' . $placeholders;
        }

        $stmt = mysqli_prepare(
            $connection,
            "INSERT INTO `$table` ($columns) VALUES ($placeholders)"
        );
        if (!$stmt) {
            return false;
        }

        if ($parentColumn !== '') {
            mysqli_stmt_bind_param(
                $stmt,
                'issssssssss',
                $parentRecordId,
                $title,
                $alias,
                $layout,
                $queryLimit,
                $accessLevel,
                $features,
                $active,
                $description,
                $tags,
                $language
            );
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                'ssssssssss',
                $title,
                $alias,
                $layout,
                $queryLimit,
                $accessLevel,
                $features,
                $active,
                $description,
                $tags,
                $language
            );
        }
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $success;
    }
}

if (!function_exists('red_admin_insert_area')) {
    function red_admin_insert_area(
        $connection,
        $table,
        $aliasColumn,
        $title,
        $alias,
        $layout,
        $queryLimit,
        $accessLevel,
        $features,
        $active,
        $description,
        $tags,
        $language,
        $parentRecordId = 0
    )
    {
        return red_admin_with_theme_contract_lock(
            $connection,
            function () use ($connection, $table, $aliasColumn, $title, $alias, $layout, $queryLimit, $accessLevel, $features, $active, $description, $tags, $language, $parentRecordId) {
                return red_admin_insert_area_unlocked(
                    $connection,
                    $table,
                    $aliasColumn,
                    $title,
                    $alias,
                    $layout,
                    $queryLimit,
                    $accessLevel,
                    $features,
                    $active,
                    $description,
                    $tags,
                    $language,
                    $parentRecordId
                );
            }
        );
    }
}

?>
