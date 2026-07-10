<?php
/**
 * Helpers for lightweight admin section/category/subcategory write endpoints.
 */

require_once __DIR__ . '/admin_transaction_helpers.php';

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

if (!function_exists('red_admin_feature_list')) {
    function red_admin_feature_list($value)
    {
        if (!is_array($value)) {
            return red_admin_text($value);
        }

        $features = [];
        foreach ($value as $feature) {
            $feature = red_admin_text($feature);
            if ($feature !== '') {
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
        return red_admin_area_fetch_column(
            $connection,
            'SELECT UniqueName FROM RED_Layouts ORDER BY UniqueName ASC',
            'UniqueName',
            'RED_Layouts lookup failed'
        );
    }
}

if (!function_exists('red_admin_area_features')) {
    function red_admin_area_features($connection)
    {
        return red_admin_area_fetch_column(
            $connection,
            'SELECT UniqueName FROM RED_Features ORDER BY UniqueName ASC',
            'UniqueName',
            'RED_Features lookup failed'
        );
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

        return red_admin_area_fetch_all(
            $connection,
            'SELECT RecordID, Title, `' . $aliasColumn . '`, Layout, Active FROM `' . $table . '` WHERE Language=? ORDER BY RecordID ASC',
            's',
            [$language],
            $table . ' admin list lookup failed'
        );
    }
}

if (!function_exists('red_admin_area_related_article_count')) {
    function red_admin_area_related_article_count($connection, $aliasColumn, $alias)
    {
        if (!in_array($aliasColumn, red_admin_area_tables(), true)) {
            return 0;
        }

        $alias = red_admin_text($alias);
        if ($alias === '') {
            return 0;
        }

        try {
            $stmt = mysqli_prepare($connection, "SELECT COUNT(*) AS related_count FROM RED_Articles WHERE `$aliasColumn`=?");
            if (!$stmt) {
                return 0;
            }

            mysqli_stmt_bind_param($stmt, 's', $alias);
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

if (!function_exists('red_admin_area_update_columns')) {
    function red_admin_area_update_columns($aliasColumn)
    {
        return [
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
    }
}

if (!function_exists('red_admin_area_update_payload')) {
    function red_admin_area_update_payload($post, $aliasColumn)
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

        return $data;
    }
}

if (!function_exists('red_admin_update_area')) {
    function red_admin_update_area($connection, $table, $aliasColumn, $recordId, $data)
    {
        $allowedTables = red_admin_area_tables();
        if (!isset($allowedTables[$table]) || $allowedTables[$table] !== $aliasColumn) {
            return false;
        }

        $recordId = (int) red_admin_scalar($recordId);
        if ($recordId <= 0 || empty($data)) {
            return false;
        }

        $allowedColumns = red_admin_area_update_columns($aliasColumn);
        $sets = [];
        $types = '';
        $values = [];

        foreach ($data as $fieldName => $value) {
            if (!isset($allowedColumns[$fieldName])) {
                continue;
            }

            $sets[] = "`$fieldName`=?";
            $types .= 's';
            $values[] = red_admin_scalar($value);
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
        $success = red_admin_write_transaction(
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

if (!function_exists('red_admin_insert_area')) {
    function red_admin_insert_area($connection, $table, $aliasColumn, $title, $alias, $layout, $queryLimit, $accessLevel, $features, $active, $description, $tags, $language)
    {
        $allowed = [
            'RED_Sections' => 'Sections',
            'RED_Categories' => 'Categories',
            'RED_SubCategories' => 'SubCategories',
        ];

        if (!isset($allowed[$table]) || $allowed[$table] !== $aliasColumn) {
            return false;
        }

        $stmt = mysqli_prepare(
            $connection,
            "INSERT INTO `$table` (Title, `$aliasColumn`, Layout, QueryLimit, AccessLevel, Features, Active, Description, Tags, Language) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ssssssssss', $title, $alias, $layout, $queryLimit, $accessLevel, $features, $active, $description, $tags, $language);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $success;
    }
}

?>
