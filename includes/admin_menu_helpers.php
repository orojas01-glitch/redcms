<?php
/**
 * Helpers for admin menu write endpoints.
 */

require_once __DIR__ . '/admin_area_helpers.php';

if (!function_exists('red_admin_menu_scalar')) {
    function red_admin_menu_scalar($value)
    {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    $items[] = red_admin_text($item);
                }
            }

            return implode(',', $items);
        }

        return red_admin_text($value);
    }
}

if (!function_exists('red_admin_menu_order')) {
    function red_admin_menu_order($value)
    {
        return max(0, (int) red_admin_menu_scalar($value));
    }
}

if (!function_exists('red_admin_menu_new_window')) {
    function red_admin_menu_new_window($value)
    {
        return red_admin_menu_scalar($value) === '_blank' ? '_blank' : '';
    }
}

if (!function_exists('red_admin_menu_value')) {
    function red_admin_menu_value($post, $field, $firstKey = null, $secondKey = null, $default = '')
    {
        if (!isset($post[$field])) {
            return $default;
        }

        $value = $post[$field];
        if ($firstKey !== null) {
            if (!is_array($value) || !array_key_exists($firstKey, $value)) {
                return $default;
            }
            $value = $value[$firstKey];
        }

        if ($secondKey !== null) {
            if (!is_array($value) || !array_key_exists($secondKey, $value)) {
                return $default;
            }
            $value = $value[$secondKey];
        }

        return $value;
    }
}

if (!function_exists('red_admin_menu_language')) {
    function red_admin_menu_language($default = 'sp')
    {
        return defined('language') ? substr(red_admin_text(constant('language')), 0, 2) : $default;
    }
}

if (!function_exists('red_admin_menu_html')) {
    function red_admin_menu_html($value)
    {
        return red_admin_area_html($value);
    }
}

if (!function_exists('red_admin_menu_fetch_all')) {
    function red_admin_menu_fetch_all($connection, $sql, $types, $values, $logMessage)
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

if (!function_exists('red_admin_main_menu_title')) {
    function red_admin_main_menu_title($connection, $language)
    {
        $language = substr(red_admin_menu_scalar($language), 0, 2);
        $row = red_admin_area_fetch_one(
            $connection,
            'SELECT Title FROM RED_Menu WHERE RootOrder=? AND Language=? ORDER BY RootOrder ASC LIMIT 1',
            'ss',
            ['1', $language],
            'RED_Menu title lookup failed'
        );

        return $row ? red_admin_menu_scalar($row['Title'] ?? '') : '';
    }
}

if (!function_exists('red_admin_main_menu_items')) {
    function red_admin_main_menu_items($connection, $language)
    {
        $language = substr(red_admin_menu_scalar($language), 0, 2);
        return red_admin_menu_fetch_all(
            $connection,
            'SELECT RecordID, Label, MenuOrder, Link, NewWindow FROM RED_Menu WHERE RootOrder=? AND Language=? ORDER BY MenuOrder ASC',
            'ss',
            ['1', $language],
            'RED_Menu root lookup failed'
        );
    }
}

if (!function_exists('red_admin_main_menu_children')) {
    function red_admin_main_menu_children($connection, $parentRecordId, $language, $depth)
    {
        $parentRecordId = (int) $parentRecordId;
        $language = substr(red_admin_menu_scalar($language), 0, 2);
        if ($parentRecordId <= 0) {
            return [];
        }

        $excludeSecondLevel = (int) $depth === 3 ? " AND RootOrder <> '2'" : '';
        return red_admin_menu_fetch_all(
            $connection,
            "SELECT RecordID, Label, MenuOrder, Link, NewWindow FROM RED_Menu WHERE Parent=? AND RootOrder <> '1' AND Language=?" . $excludeSecondLevel . ' ORDER BY MenuOrder ASC',
            'is',
            [$parentRecordId, $language],
            'RED_Menu child lookup failed'
        );
    }
}

if (!function_exists('red_admin_menu_option')) {
    function red_admin_menu_option($path)
    {
        $path = red_admin_menu_html($path);
        return '<option value="' . $path . '">' . $path . '</option>';
    }
}

if (!function_exists('red_admin_main_menu_article_path')) {
    function red_admin_main_menu_article_path($section, $category, $subcategory, $alias)
    {
        $alias = red_admin_menu_scalar($alias);
        if ($alias === '') {
            return '';
        }

        return rtrim(
            red_admin_area_public_path($section, $category, $subcategory),
            '/'
        ) . '/' . rawurlencode($alias);
    }
}

if (!function_exists('red_admin_main_menu_choice_label')) {
    function red_admin_main_menu_choice_label($type, $title, $alias, $path)
    {
        $title = red_admin_menu_scalar($title);
        $alias = red_admin_menu_scalar($alias);
        $display = $title !== '' ? $title : $alias;
        if ($display === '') {
            $display = 'Untitled';
        }

        return red_admin_menu_scalar($type) . ' — ' . $display . ' · ' . red_admin_menu_scalar($path);
    }
}

if (!function_exists('red_admin_main_menu_add_link_choice')) {
    function red_admin_main_menu_add_link_choice(&$choices, &$seen, $group, $path, $label, $kind, $depth)
    {
        $path = red_admin_menu_scalar($path);
        if ($path === '' || isset($seen[$path])) {
            return;
        }

        $seen[$path] = true;
        $choices[] = [
            'group' => red_admin_menu_scalar($group),
            'value' => $path,
            'label' => red_admin_menu_scalar($label),
            'kind' => red_admin_menu_scalar($kind),
            'depth' => max(0, (int) $depth),
        ];
    }
}

if (!function_exists('red_admin_main_menu_link_choices')) {
    function red_admin_main_menu_link_choices($connection, $language = '')
    {
        $language = substr(
            red_admin_menu_scalar($language !== '' ? $language : red_admin_menu_language()),
            0,
            2
        );
        $sections = red_admin_menu_fetch_all(
            $connection,
            "SELECT RecordID, Sections, Title FROM RED_Sections WHERE Active='Y' AND Language=? " .
                "ORDER BY CASE WHEN LOWER(Sections)='home' THEN 0 ELSE 1 END, " .
                'Title ASC, Sections ASC, RecordID ASC',
            's',
            [$language],
            'RED_Sections menu link lookup failed'
        );
        $categories = red_admin_menu_fetch_all(
            $connection,
            "SELECT category_area.RecordID, category_area.Categories, category_area.Title, " .
                'category_area.SectionRecordID, section_area.Sections AS SectionAlias ' .
                'FROM RED_Categories AS category_area ' .
                'JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                'AND section_area.Language=category_area.Language ' .
                "WHERE category_area.Active='Y' AND section_area.Active='Y' " .
                'AND category_area.Language=? ' .
                'ORDER BY section_area.Title ASC, category_area.Title ASC, ' .
                'category_area.Categories ASC, category_area.RecordID ASC',
            's',
            [$language],
            'RED_Categories parent-aware menu link lookup failed'
        );
        $subCategories = red_admin_menu_fetch_all(
            $connection,
            "SELECT subcategory_area.RecordID, subcategory_area.SubCategories, subcategory_area.Title, " .
                'subcategory_area.CategoryRecordID, category_area.Categories AS CategoryAlias, ' .
                'section_area.Sections AS SectionAlias ' .
                'FROM RED_SubCategories AS subcategory_area ' .
                'JOIN RED_Categories AS category_area ON category_area.RecordID=subcategory_area.CategoryRecordID ' .
                'AND category_area.Language=subcategory_area.Language ' .
                'JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                'AND section_area.Language=category_area.Language ' .
                "WHERE subcategory_area.Active='Y' AND category_area.Active='Y' " .
                "AND section_area.Active='Y' AND subcategory_area.Language=? " .
                'ORDER BY section_area.Title ASC, category_area.Title ASC, ' .
                'subcategory_area.Title ASC, subcategory_area.SubCategories ASC, ' .
                'subcategory_area.RecordID ASC',
            's',
            [$language],
            'RED_SubCategories parent-aware menu link lookup failed'
        );
        $articles = red_admin_menu_fetch_all(
            $connection,
            'SELECT RecordID, Title, Alias, Sections, Categories, SubCategories ' .
                'FROM RED_Articles WHERE Language=? ' .
                'ORDER BY Sections ASC, Categories ASC, SubCategories ASC, ' .
                'Title ASC, Updated DESC, RecordID ASC',
            's',
            [$language],
            'RED_Articles menu link lookup failed'
        );

        $categoriesBySection = [];
        foreach ($categories as $categoryRow) {
            $categoriesBySection[(int) ($categoryRow['SectionRecordID'] ?? 0)][] = $categoryRow;
        }

        $subCategoriesByCategory = [];
        foreach ($subCategories as $subCategoryRow) {
            $subCategoriesByCategory[(int) ($subCategoryRow['CategoryRecordID'] ?? 0)][] = $subCategoryRow;
        }

        $articlesByRoute = [];
        foreach ($articles as $articleRow) {
            $routeKey = strtolower(implode('|', [
                red_admin_menu_scalar($articleRow['Sections'] ?? ''),
                red_admin_menu_scalar($articleRow['Categories'] ?? ''),
                red_admin_menu_scalar($articleRow['SubCategories'] ?? ''),
            ]));
            $articlesByRoute[$routeKey][] = $articleRow;
        }

        $choices = [];
        $seen = [];
        foreach ($sections as $sectionRow) {
            $sectionId = (int) ($sectionRow['RecordID'] ?? 0);
            $section = red_admin_menu_scalar($sectionRow['Sections'] ?? '');
            if ($sectionId <= 0 || $section === '') {
                continue;
            }

            $sectionTitle = red_admin_menu_scalar($sectionRow['Title'] ?? '');
            $groupTitle = 'Section · ' . ($sectionTitle !== '' ? $sectionTitle : $section);
            $sectionPath = red_admin_area_public_path($section);
            red_admin_main_menu_add_link_choice(
                $choices,
                $seen,
                $groupTitle,
                $sectionPath,
                red_admin_main_menu_choice_label('Section', $sectionTitle, $section, $sectionPath),
                'section',
                0
            );

            $sectionArticleKey = strtolower($section . '||');
            foreach (($articlesByRoute[$sectionArticleKey] ?? []) as $articleRow) {
                $articlePath = red_admin_main_menu_article_path($section, '', '', $articleRow['Alias'] ?? '');
                red_admin_main_menu_add_link_choice(
                    $choices,
                    $seen,
                    $groupTitle,
                    $articlePath,
                    red_admin_main_menu_choice_label(
                        'Article',
                        $articleRow['Title'] ?? '',
                        $articleRow['Alias'] ?? '',
                        $articlePath
                    ),
                    'article',
                    1
                );
            }

            foreach (($categoriesBySection[$sectionId] ?? []) as $categoryRow) {
                $categoryId = (int) ($categoryRow['RecordID'] ?? 0);
                $category = red_admin_menu_scalar($categoryRow['Categories'] ?? '');
                if ($categoryId <= 0 || $category === '') {
                    continue;
                }

                $categoryPath = red_admin_area_public_path($section, $category);
                red_admin_main_menu_add_link_choice(
                    $choices,
                    $seen,
                    $groupTitle,
                    $categoryPath,
                    red_admin_main_menu_choice_label(
                        'Category',
                        $categoryRow['Title'] ?? '',
                        $category,
                        $categoryPath
                    ),
                    'category',
                    1
                );

                $categoryArticleKey = strtolower($section . '|' . $category . '|');
                foreach (($articlesByRoute[$categoryArticleKey] ?? []) as $articleRow) {
                    $articlePath = red_admin_main_menu_article_path(
                        $section,
                        $category,
                        '',
                        $articleRow['Alias'] ?? ''
                    );
                    red_admin_main_menu_add_link_choice(
                        $choices,
                        $seen,
                        $groupTitle,
                        $articlePath,
                        red_admin_main_menu_choice_label(
                            'Article in ' . ($categoryRow['Title'] ?? $category),
                            $articleRow['Title'] ?? '',
                            $articleRow['Alias'] ?? '',
                            $articlePath
                        ),
                        'article',
                        2
                    );
                }

                foreach (($subCategoriesByCategory[$categoryId] ?? []) as $subCategoryRow) {
                    $subCategory = red_admin_menu_scalar($subCategoryRow['SubCategories'] ?? '');
                    if ($subCategory === '') {
                        continue;
                    }

                    $subCategoryPath = red_admin_area_public_path($section, $category, $subCategory);
                    red_admin_main_menu_add_link_choice(
                        $choices,
                        $seen,
                        $groupTitle,
                        $subCategoryPath,
                        red_admin_main_menu_choice_label(
                            'Subcategory',
                            $subCategoryRow['Title'] ?? '',
                            $subCategory,
                            $subCategoryPath
                        ),
                        'subcategory',
                        2
                    );

                    $subCategoryArticleKey = strtolower(
                        $section . '|' . $category . '|' . $subCategory
                    );
                    foreach (($articlesByRoute[$subCategoryArticleKey] ?? []) as $articleRow) {
                        $articlePath = red_admin_main_menu_article_path(
                            $section,
                            $category,
                            $subCategory,
                            $articleRow['Alias'] ?? ''
                        );
                        red_admin_main_menu_add_link_choice(
                            $choices,
                            $seen,
                            $groupTitle,
                            $articlePath,
                            red_admin_main_menu_choice_label(
                                'Article in ' . ($subCategoryRow['Title'] ?? $subCategory),
                                $articleRow['Title'] ?? '',
                                $articleRow['Alias'] ?? '',
                                $articlePath
                            ),
                            'article',
                            3
                        );
                    }
                }
            }
        }

        return $choices;
    }
}

if (!function_exists('red_admin_main_menu_link_options_from_choices')) {
    function red_admin_main_menu_link_options_from_choices($choices, $selected = '')
    {
        $selected = red_admin_menu_scalar($selected);
        $choiceValues = [];
        foreach ((array) $choices as $choice) {
            if (is_array($choice)) {
                $choiceValues[] = red_admin_menu_scalar($choice['value'] ?? '');
            }
        }
        $hasSelectedChoice = $selected !== '' && in_array($selected, $choiceValues, true);
        $options = '<option value=""' . (!$hasSelectedChoice ? ' selected="selected"' : '') .
            '>Choose a page or keep a custom destination…</option>';
        $openGroup = '';

        foreach ((array) $choices as $choice) {
            if (!is_array($choice)) {
                continue;
            }

            $group = red_admin_menu_scalar($choice['group'] ?? 'Website pages');
            $value = red_admin_menu_scalar($choice['value'] ?? '');
            if ($value === '') {
                continue;
            }

            if ($group !== $openGroup) {
                if ($openGroup !== '') {
                    $options .= '</optgroup>';
                }
                $options .= '<optgroup label="' . red_admin_menu_html($group) . '">';
                $openGroup = $group;
            }

            $options .= '<option value="' . red_admin_menu_html($value) . '" data-kind="' .
                red_admin_menu_html($choice['kind'] ?? '') . '" data-depth="' .
                red_admin_menu_html($choice['depth'] ?? 0) . '"' .
                ($selected === $value ? ' selected="selected"' : '') . '>' .
                red_admin_menu_html($choice['label'] ?? $value) . '</option>';
        }

        if ($openGroup !== '') {
            $options .= '</optgroup>';
        }

        return $options;
    }
}

if (!function_exists('red_admin_main_menu_link_options')) {
    function red_admin_main_menu_link_options($connection, $language = '', $selected = '')
    {
        return red_admin_main_menu_link_options_from_choices(
            red_admin_main_menu_link_choices($connection, $language),
            $selected
        );
    }
}

if (!function_exists('red_admin_menu_bind_values')) {
    function red_admin_menu_bind_values($stmt, $types, &$values)
    {
        $refs = [];
        foreach ($values as $key => &$value) {
            $refs[$key] = &$value;
        }

        return mysqli_stmt_bind_param($stmt, $types, ...$refs);
    }
}

if (!function_exists('red_admin_menu_execute')) {
    function red_admin_menu_execute($connection, $sql, $types, $values, $logMessage)
    {
        try {
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                return false;
            }

            if ($types !== '' && !red_admin_menu_bind_values($stmt, $types, $values)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log($logMessage . ': ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_main_menu_rename')) {
    function red_admin_main_menu_rename($connection, $title, $currentTitle)
    {
        $title = red_admin_menu_scalar($title);
        $currentTitle = red_admin_menu_scalar($currentTitle);
        if ($currentTitle === '') {
            return false;
        }

        $values = [$title, $currentTitle];
        return red_admin_menu_execute(
            $connection,
            'UPDATE RED_Menu SET Title=? WHERE Title=?',
            'ss',
            $values,
            'RED_Menu title update failed'
        );
    }
}

if (!function_exists('red_admin_main_menu_update_item')) {
    function red_admin_main_menu_update_item($connection, $recordId, $label, $menuOrder, $link, $newWindow)
    {
        $recordId = (int) $recordId;
        if ($recordId <= 0) {
            return false;
        }

        $values = [
            red_admin_menu_scalar($label),
            red_admin_menu_order($menuOrder),
            red_admin_menu_scalar($link),
            red_admin_menu_new_window($newWindow),
            $recordId,
        ];

        return red_admin_menu_execute(
            $connection,
            'UPDATE RED_Menu SET Label=?, MenuOrder=?, Link=?, NewWindow=? WHERE RecordID=?',
            'sissi',
            $values,
            'RED_Menu item update failed'
        );
    }
}

if (!function_exists('red_admin_main_menu_insert_item')) {
    function red_admin_main_menu_insert_item($connection, $rootOrder, $title, $label, $parent, $link, $newWindow, $menuOrder, $language)
    {
        $label = red_admin_menu_scalar($label);
        if ($label === '') {
            return false;
        }

        $values = [
            (string) max(1, (int) $rootOrder),
            red_admin_menu_scalar($title),
            $label,
            max(0, (int) $parent),
            red_admin_menu_scalar($link),
            red_admin_menu_new_window($newWindow),
            red_admin_menu_order($menuOrder),
            substr(red_admin_menu_scalar($language), 0, 2),
        ];

        return red_admin_menu_execute(
            $connection,
            "INSERT INTO RED_Menu (RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, Active, Language) VALUES (?, ?, ?, ?, ?, ?, ?, 'Y', ?)",
            'sssissis',
            $values,
            'RED_Menu item insert failed'
        );
    }
}

if (!function_exists('red_admin_component_menu_record_matches')) {
    function red_admin_component_menu_record_matches($connection, $recordId, $artRecordId)
    {
        $recordId = (int) $recordId;
        $artRecordId = (int) $artRecordId;
        if ($recordId <= 0 || $artRecordId <= 0) {
            return false;
        }

        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID FROM RED_C_Menu WHERE RecordID=? AND RefID=? LIMIT 1');
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, 'ii', $recordId, $artRecordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            mysqli_stmt_store_result($stmt);
            $matches = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);

            return $matches;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_C_Menu pairing lookup failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_component_menu_update_title')) {
    function red_admin_component_menu_update_title($connection, $artRecordId, $title)
    {
        $artRecordId = (int) $artRecordId;
        if ($artRecordId <= 0) {
            return false;
        }

        $values = [red_admin_menu_scalar($title), $artRecordId];
        return red_admin_menu_execute(
            $connection,
            'UPDATE RED_C_Menu SET Title=? WHERE RefID=?',
            'si',
            $values,
            'RED_C_Menu title update failed'
        );
    }
}

if (!function_exists('red_admin_component_menu_update_type')) {
    function red_admin_component_menu_update_type($connection, $artRecordId, $menuType)
    {
        $artRecordId = (int) $artRecordId;
        if ($artRecordId <= 0) {
            return false;
        }

        $values = [red_admin_menu_scalar($menuType), $artRecordId];
        return red_admin_menu_execute(
            $connection,
            'UPDATE RED_C_Menu SET MenuType=? WHERE RefID=?',
            'si',
            $values,
            'RED_C_Menu type update failed'
        );
    }
}

if (!function_exists('red_admin_component_menu_update_item')) {
    function red_admin_component_menu_update_item($connection, $recordId, $label, $menuOrder, $link, $newWindow)
    {
        $recordId = (int) $recordId;
        if ($recordId <= 0) {
            return false;
        }

        $values = [
            red_admin_menu_scalar($label),
            red_admin_menu_order($menuOrder),
            red_admin_menu_scalar($link),
            red_admin_menu_new_window($newWindow),
            $recordId,
        ];

        return red_admin_menu_execute(
            $connection,
            'UPDATE RED_C_Menu SET Label=?, MenuOrder=?, Link=?, NewWindow=? WHERE RecordID=?',
            'sissi',
            $values,
            'RED_C_Menu item update failed'
        );
    }
}

if (!function_exists('red_admin_component_menu_insert_item')) {
    function red_admin_component_menu_insert_item($connection, $artRecordId, $rootOrder, $title, $label, $parent, $link, $newWindow, $menuOrder, $menuType)
    {
        $artRecordId = (int) $artRecordId;
        $label = red_admin_menu_scalar($label);
        if ($artRecordId <= 0 || $label === '') {
            return false;
        }

        $values = [
            $artRecordId,
            (string) max(1, (int) $rootOrder),
            red_admin_menu_scalar($title),
            $label,
            max(0, (int) $parent),
            red_admin_menu_scalar($link),
            red_admin_menu_new_window($newWindow),
            red_admin_menu_order($menuOrder),
            red_admin_menu_scalar($menuType),
        ];

        return red_admin_menu_execute(
            $connection,
            'INSERT INTO RED_C_Menu (RefID, RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, MenuType) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'isssissis',
            $values,
            'RED_C_Menu item insert failed'
        );
    }
}

if (!function_exists('red_admin_menu_article_record')) {
    function red_admin_menu_article_record($connection, $recordId)
    {
        $recordId = (int) $recordId;
        if ($recordId <= 0) {
            return null;
        }

        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID, HomePosition, PagePosition FROM RED_Articles WHERE RecordID=? LIMIT 1');
            if (!$stmt) {
                return null;
            }

            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            return $row ?: null;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Articles menu lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_menu_article_list')) {
    function red_admin_menu_article_list($value)
    {
        if (!is_array($value)) {
            return red_admin_menu_scalar($value);
        }

        $articles = [];
        foreach ($value as $item) {
            $item = red_admin_menu_scalar($item);
            if ($item !== '') {
                $articles[] = $item;
            }
        }

        return implode(',', $articles);
    }
}

?>
