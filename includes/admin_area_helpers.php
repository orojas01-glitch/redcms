<?php
/**
 * Helpers for lightweight admin section/category/subcategory write endpoints.
 */

if (!function_exists('red_admin_text')) {
    function red_admin_text($value)
    {
        return trim(preg_replace("'<[^>]+>'U", '', (string) $value));
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

if (!function_exists('red_admin_area_alias_count')) {
    function red_admin_area_alias_count($connection, $table, $column, $language, $alias)
    {
        $allowed = [
            'RED_Sections' => 'Sections',
            'RED_Categories' => 'Categories',
            'RED_SubCategories' => 'SubCategories',
        ];

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
