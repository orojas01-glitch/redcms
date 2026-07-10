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

if (!function_exists('red_admin_main_menu_link_options')) {
    function red_admin_main_menu_link_options($connection)
    {
        $options = '<option value="">Select a link from available pages of the website...</option>';
        $sections = red_admin_menu_fetch_all(
            $connection,
            "SELECT Sections FROM RED_Sections WHERE Active='Y' ORDER BY Sections ASC",
            '',
            [],
            'RED_Sections menu link lookup failed'
        );
        $categories = red_admin_menu_fetch_all(
            $connection,
            "SELECT Categories FROM RED_Categories WHERE Active='Y' ORDER BY Categories ASC",
            '',
            [],
            'RED_Categories menu link lookup failed'
        );
        $subCategories = red_admin_menu_fetch_all(
            $connection,
            "SELECT SubCategories FROM RED_SubCategories WHERE Active='Y' ORDER BY SubCategories ASC",
            '',
            [],
            'RED_SubCategories menu link lookup failed'
        );

        foreach ($sections as $sectionRow) {
            $section = red_admin_text($sectionRow['Sections'] ?? '');
            if ($section === '') {
                continue;
            }

            $sectionVal = $section === 'home' ? '' : '/' . $section;
            $options .= red_admin_menu_option($sectionVal . '/');

            foreach (red_admin_menu_fetch_all(
                $connection,
                "SELECT Alias FROM RED_Articles WHERE Sections=? AND Categories='' AND SubCategories='' ORDER BY Updated DESC",
                's',
                [$section],
                'RED_Articles section menu link lookup failed'
            ) as $articleRow) {
                $alias = red_admin_text($articleRow['Alias'] ?? '');
                if ($alias !== '') {
                    $options .= red_admin_menu_option($sectionVal . '/' . $alias);
                }
            }

            foreach ($categories as $categoryRow) {
                $category = red_admin_text($categoryRow['Categories'] ?? '');
                if ($category === '') {
                    continue;
                }

                $options .= red_admin_menu_option($sectionVal . '/' . $category . '/');

                foreach (red_admin_menu_fetch_all(
                    $connection,
                    "SELECT Alias FROM RED_Articles WHERE Sections=? AND Categories=? AND SubCategories='' ORDER BY Updated DESC",
                    'ss',
                    [$section, $category],
                    'RED_Articles category menu link lookup failed'
                ) as $articleRow) {
                    $alias = red_admin_text($articleRow['Alias'] ?? '');
                    if ($alias !== '') {
                        $options .= red_admin_menu_option($sectionVal . '/' . $category . '/' . $alias);
                    }
                }

                foreach ($subCategories as $subCategoryRow) {
                    $subCategory = red_admin_text($subCategoryRow['SubCategories'] ?? '');
                    if ($subCategory === '') {
                        continue;
                    }

                    $options .= red_admin_menu_option($sectionVal . '/' . $category . '/' . $subCategory . '/');

                    foreach (red_admin_menu_fetch_all(
                        $connection,
                        'SELECT Alias FROM RED_Articles WHERE Sections=? AND Categories=? AND SubCategories=? ORDER BY Updated DESC',
                        'sss',
                        [$section, $category, $subCategory],
                        'RED_Articles subcategory menu link lookup failed'
                    ) as $articleRow) {
                        $alias = red_admin_text($articleRow['Alias'] ?? '');
                        if ($alias !== '') {
                            $options .= red_admin_menu_option($sectionVal . '/' . $category . '/' . $subCategory . '/' . $alias);
                        }
                    }
                }
            }
        }

        return $options;
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
