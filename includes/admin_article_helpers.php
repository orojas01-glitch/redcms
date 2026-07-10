<?php
/**
 * Helpers for admin RED_Articles write endpoints.
 */

require_once __DIR__ . '/admin_area_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';

if (!function_exists('red_admin_article_batch_transaction')) {
    function red_admin_article_batch_transaction($connection, $callback)
    {
        return red_admin_write_transaction($connection, $callback, ['RED_Articles']);
    }
}

if (!function_exists('red_admin_article_columns')) {
    function red_admin_article_columns()
    {
        return [
            'RecordID' => true,
            'Title' => true,
            'Component' => true,
            'Alias' => true,
            'Sections' => true,
            'HomePosition' => true,
            'HomePositionOrder' => true,
            'SectionPosition' => true,
            'SectionPositionOrder' => true,
            'Categories' => true,
            'CategoryPosition' => true,
            'CategoryPositionOrder' => true,
            'SubCategories' => true,
            'SubCategoryPosition' => true,
            'SubCategoryPositionOrder' => true,
            'Layout' => true,
            'Article' => true,
            'PagePosition' => true,
            'PagePositionOrder' => true,
            'Tags' => true,
            'Active' => true,
            'HomeFeature' => true,
            'HomeFeatures' => true,
            'HomeFeatures_Order' => true,
            'SectionFeatures' => true,
            'SectionFeatures_Order' => true,
            'CategoryFeatures' => true,
            'CategoryFeatures_Order' => true,
            'SubCategoryFeatures' => true,
            'SubCategoryFeatures_Order' => true,
            'ArticleFeatures' => true,
            'StartDate' => true,
            'EventDate' => true,
            'ExpDate' => true,
            'ShortDesc' => true,
            'LongDesc' => true,
            'SliderDesc' => true,
            'Link' => true,
            'NewWindow' => true,
            'VideoSrc' => true,
            'AlbumSrc' => true,
            'BigPict' => true,
            'SmallPict' => true,
            'SmallPictAlign' => true,
            'SmallPict2' => true,
            'SmallPictAlign2' => true,
            'EditedBy' => true,
            'Language' => true,
        ];
    }
}

if (!function_exists('red_admin_article_integer_columns')) {
    function red_admin_article_integer_columns()
    {
        return [
            'RecordID' => true,
            'HomePosition' => true,
            'HomePositionOrder' => true,
            'SectionPosition' => true,
            'SectionPositionOrder' => true,
            'CategoryPosition' => true,
            'CategoryPositionOrder' => true,
            'SubCategoryPosition' => true,
            'SubCategoryPositionOrder' => true,
            'PagePosition' => true,
            'PagePositionOrder' => true,
            'HomeFeatures_Order' => true,
            'SectionFeatures_Order' => true,
            'CategoryFeatures_Order' => true,
            'SubCategoryFeatures_Order' => true,
        ];
    }
}

if (!function_exists('red_admin_article_scalar')) {
    function red_admin_article_scalar($value)
    {
        return is_array($value) ? '' : (string) $value;
    }
}

if (!function_exists('red_admin_article_datetime')) {
    function red_admin_article_datetime($value, $emptyValue)
    {
        $value = trim(red_admin_text(red_admin_article_scalar($value)));
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return $emptyValue;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $emptyValue;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

if (!function_exists('red_admin_article_clean_value')) {
    function red_admin_article_clean_value($fieldName, $value)
    {
        $value = red_admin_article_scalar($value);

        if (isset(red_admin_article_integer_columns()[$fieldName])) {
            return max(0, (int) $value);
        }

        switch ($fieldName) {
            case 'Active':
                return $value === 'Y' || $value === 'N' ? $value : '';

            case 'HomeFeature':
            case 'NewWindow':
                return $value === 'Y' ? 'Y' : '';

            case 'Component':
                return in_array($value, ['Article', 'Other', 'Gallery', 'Form', 'MainMenu', 'SubMenu'], true) ? $value : '';

            case 'SmallPictAlign':
            case 'SmallPictAlign2':
                return in_array($value, ['Top', 'Left', 'Right'], true) ? $value : '';

            case 'Language':
                return substr(red_admin_text($value), 0, 2);

            case 'StartDate':
            case 'EventDate':
                return red_admin_article_datetime($value, '1970-01-01 00:00:00');

            case 'ExpDate':
                return red_admin_article_datetime($value, '9999-12-31 23:59:59');

            default:
                return $value;
        }
    }
}

if (!function_exists('red_admin_article_has_payload')) {
    function red_admin_article_has_payload($post)
    {
        $controls = [
            'csrf_token' => true,
            'RecordID' => true,
            'submit' => true,
            'Order' => true,
            'LinkNavigator' => true,
        ];
        $columns = red_admin_article_columns();

        foreach ($post as $name => $value) {
            if (!is_string($name) || isset($controls[$name])) {
                continue;
            }
            if (isset($columns[$name])) {
                return true;
            }
            if (strpos($name, 'Delete_') === 0 && red_admin_article_scalar($value) === 'Y') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('red_admin_article_collect_values')) {
    function red_admin_article_collect_values($post, $mode)
    {
        $columns = red_admin_article_columns();
        $data = [];

        foreach ($post as $name => $value) {
            if (!is_string($name) || !isset($columns[$name]) || $name === 'RecordID') {
                continue;
            }

            switch ($name) {
                case 'Title':
                    $data['Title'] = red_admin_article_clean_value('Title', $value);
                    break;

                case 'Alias':
                    $data['Alias'] = red_admin_slug($value, true);
                    break;

                case 'Tags':
                    $data['Tags'] = red_admin_tag_list($value);
                    break;

                case 'BigPict':
                case 'SmallPict':
                case 'SmallPict2':
                    $deleteField = 'Delete_' . $name;
                    $data[$name] = isset($post[$deleteField]) && red_admin_article_scalar($post[$deleteField]) === 'Y'
                        ? ''
                        : red_admin_article_clean_value($name, $value);
                    break;

                case 'HomeFeature':
                case 'NewWindow':
                    break;

                default:
                    $data[$name] = red_admin_article_clean_value($name, $value);
                    break;
            }
        }

        if ($mode === 'insert' && isset($data['Title'])) {
            if (!isset($post['Alias'])) {
                $data['Alias'] = red_admin_slug($data['Title'], true);
            }
            if (!isset($post['Tags'])) {
                $data['Tags'] = red_admin_tag_list($data['Title']);
            }
        }

        if (red_admin_article_has_payload($post)) {
            $data['NewWindow'] = isset($post['NewWindow']) && red_admin_article_scalar($post['NewWindow']) === 'Y' ? 'Y' : '';
            $data['HomeFeature'] = isset($post['HomeFeature']) && red_admin_article_scalar($post['HomeFeature']) === 'Y' ? 'Y' : '';
        }

        return $data;
    }
}

if (!function_exists('red_admin_article_apply_home_position')) {
    function red_admin_article_apply_home_position(&$data, $existingRow)
    {
        if (($data['HomeFeature'] ?? '') !== 'Y' || isset($data['HomePosition']) || !$existingRow) {
            return;
        }

        if ((int) ($existingRow['HomePosition'] ?? 0) === 0) {
            $data['HomePosition'] = 1;
        }
    }
}

if (!function_exists('red_admin_article_default_insert_data')) {
    function red_admin_article_default_insert_data($recordId)
    {
        return [
            'RecordID' => $recordId,
            'Title' => '',
            'Component' => '',
            'Alias' => '',
            'Sections' => '',
            'HomePosition' => 0,
            'HomePositionOrder' => 0,
            'SectionPosition' => 0,
            'SectionPositionOrder' => 0,
            'Categories' => '',
            'CategoryPosition' => 0,
            'CategoryPositionOrder' => 0,
            'SubCategories' => '',
            'SubCategoryPosition' => 0,
            'SubCategoryPositionOrder' => 0,
            'Layout' => 'Full-Width',
            'Article' => '',
            'PagePosition' => 1,
            'PagePositionOrder' => 0,
            'Tags' => '',
            'Active' => 'Y',
            'HomeFeature' => '',
            'HomeFeatures' => '',
            'HomeFeatures_Order' => 0,
            'SectionFeatures' => '',
            'SectionFeatures_Order' => 0,
            'CategoryFeatures' => '',
            'CategoryFeatures_Order' => 0,
            'SubCategoryFeatures' => '',
            'SubCategoryFeatures_Order' => 0,
            'ArticleFeatures' => '',
            'StartDate' => '1970-01-01 00:00:00',
            'EventDate' => '1970-01-01 00:00:00',
            'ExpDate' => '9999-12-31 23:59:59',
            'ShortDesc' => '',
            'LongDesc' => '',
            'SliderDesc' => '',
            'Link' => '',
            'NewWindow' => '',
            'VideoSrc' => '',
            'AlbumSrc' => '',
            'BigPict' => '',
            'SmallPict' => '',
            'SmallPictAlign' => '',
            'SmallPict2' => '',
            'SmallPictAlign2' => '',
            'EditedBy' => '',
            'Language' => '',
        ];
    }
}

if (!function_exists('red_admin_article_bind_values')) {
    function red_admin_article_bind_values($stmt, $types, &$values)
    {
        $refs = [];
        foreach ($values as $key => &$value) {
            $refs[$key] = &$value;
        }

        return mysqli_stmt_bind_param($stmt, $types, ...$refs);
    }
}

if (!function_exists('red_admin_article_param_type')) {
    function red_admin_article_param_type($fieldName)
    {
        return isset(red_admin_article_integer_columns()[$fieldName]) ? 'i' : 's';
    }
}

if (!function_exists('red_admin_article_position_columns')) {
    function red_admin_article_position_columns()
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

if (!function_exists('red_admin_article_position_column')) {
    function red_admin_article_position_column($value, $default = 'PagePosition')
    {
        $value = red_admin_text($value);
        if ($value === '') {
            return $default;
        }

        return isset(red_admin_article_position_columns()[$value]) ? $value : null;
    }
}

if (!function_exists('red_admin_article_fetch_all')) {
    function red_admin_article_fetch_all($connection, $sql, $types, $values, $logMessage)
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

if (!function_exists('red_admin_article_option')) {
    function red_admin_article_option($value, $selected)
    {
        $value = red_admin_text($value);
        if ($value === '') {
            return '';
        }

        $escapedValue = red_admin_area_html($value);
        $selectedAttribute = strtolower($value) === strtolower(red_admin_text($selected)) ? ' selected="selected"' : '';
        return '<option value="' . $escapedValue . '"' . $selectedAttribute . '>' . $escapedValue . '</option>';
    }
}

if (!function_exists('red_admin_article_area_options')) {
    function red_admin_article_area_options($connection, $table, $column, $selected, $activeOnly = true)
    {
        $allowedTables = red_admin_area_tables();
        if (!isset($allowedTables[$table]) || $allowedTables[$table] !== $column) {
            return '';
        }

        $where = $activeOnly ? " WHERE Active='Y'" : '';
        $rows = red_admin_article_fetch_all(
            $connection,
            'SELECT `' . $column . '` FROM `' . $table . '`' . $where . ' ORDER BY `' . $column . '` ASC',
            '',
            [],
            $table . ' option lookup failed'
        );

        $options = '';
        foreach ($rows as $row) {
            $options .= red_admin_article_option($row[$column] ?? '', $selected);
        }

        return $options;
    }
}

if (!function_exists('red_admin_article_page_options')) {
    function red_admin_article_page_options($connection, $selected)
    {
        $rows = red_admin_article_fetch_all(
            $connection,
            "SELECT Alias FROM RED_Articles WHERE Active='Y' AND Component='Article' ORDER BY Updated DESC",
            '',
            [],
            'RED_Articles page option lookup failed'
        );

        $options = '';
        foreach ($rows as $row) {
            $options .= red_admin_article_option($row['Alias'] ?? '', $selected);
        }

        return $options;
    }
}

if (!function_exists('red_admin_article_gallery_type_options')) {
    function red_admin_article_gallery_type_options($connection, $selected)
    {
        $rows = red_admin_article_fetch_all(
            $connection,
            "SELECT Layout FROM RED_Components WHERE UniqueName='Gallery' ORDER BY Layout ASC",
            '',
            [],
            'RED_Components gallery option lookup failed'
        );

        $options = '';
        foreach ($rows as $row) {
            $options .= red_admin_article_option($row['Layout'] ?? '', $selected);
        }

        return $options;
    }
}

if (!function_exists('red_admin_article_form_component_template')) {
    function red_admin_article_form_component_template($connection, $type)
    {
        $type = red_admin_text($type);
        if ($type === '') {
            return ['Template' => '', 'ResponseTemplate' => ''];
        }

        $row = red_admin_area_fetch_one(
            $connection,
            "SELECT Template, ResponseTemplate FROM RED_Components WHERE UniqueName='Form' AND Layout=? LIMIT 1",
            's',
            [$type],
            'RED_Components form template lookup failed'
        );

        return $row ?: ['Template' => '', 'ResponseTemplate' => ''];
    }
}

if (!function_exists('red_admin_article_record')) {
    function red_admin_article_record($connection, $recordId)
    {
        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID, HomePosition FROM RED_Articles WHERE RecordID=? LIMIT 1');
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
            error_log('RED_Articles lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_article_full_record')) {
    function red_admin_article_full_record($connection, $recordId)
    {
        $recordId = (int) red_admin_scalar($recordId);
        if ($recordId <= 0) {
            return null;
        }

        return red_admin_area_fetch_one(
            $connection,
            'SELECT * FROM RED_Articles WHERE RecordID=? LIMIT 1',
            'i',
            [$recordId],
            'RED_Articles full lookup failed'
        );
    }
}

if (!function_exists('red_admin_article_layout_positions')) {
    function red_admin_article_layout_positions($connection, $layout)
    {
        $layout = red_admin_text($layout);
        if ($layout === '') {
            return 0;
        }

        $row = red_admin_area_fetch_one(
            $connection,
            'SELECT Positions FROM RED_Layouts WHERE UniqueName=? LIMIT 1',
            's',
            [$layout],
            'RED_Layouts positions lookup failed'
        );

        return $row ? max(0, (int) $row['Positions']) : 0;
    }
}

if (!function_exists('red_admin_article_update')) {
    function red_admin_article_update($connection, $recordId, $data)
    {
        unset($data['RecordID']);
        if ($recordId <= 0 || empty($data)) {
            return false;
        }

        $sets = [];
        $types = '';
        $values = [];
        foreach ($data as $fieldName => $value) {
            if (!isset(red_admin_article_columns()[$fieldName]) || $fieldName === 'RecordID') {
                continue;
            }
            $sets[] = "`$fieldName`=?";
            $types .= red_admin_article_param_type($fieldName);
            $values[] = $value;
        }

        if (empty($sets)) {
            return false;
        }

        $types .= 'i';
        $values[] = $recordId;
        try {
            $stmt = mysqli_prepare($connection, 'UPDATE RED_Articles SET ' . implode(', ', $sets) . ' WHERE RecordID=?');
            if (!$stmt) {
                return false;
            }

            if (!red_admin_article_bind_values($stmt, $types, $values)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Articles update failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_article_update_order_batch')) {
    function red_admin_article_update_order_batch($connection, $recordIds, $positionColumns, $positionOrders)
    {
        if (!is_array($recordIds) || empty($recordIds) || !is_array($positionColumns) || !is_array($positionOrders)) {
            return false;
        }

        return red_admin_article_batch_transaction($connection, function () use ($connection, $recordIds, $positionColumns, $positionOrders) {
            $attempted = false;
            foreach ($recordIds as $index => $recordId) {
                $recordId = (int) $recordId;
                $positionColumn = red_admin_text($positionColumns[$index] ?? '');
                if ($recordId <= 0 || !isset(red_admin_article_position_columns()[$positionColumn])) {
                    return false;
                }

                if (!red_admin_article_record($connection, $recordId)) {
                    return false;
                }

                $attempted = true;
                $orderColumn = $positionColumn . 'Order';
                $positionOrder = (int) ($positionOrders[$index] ?? 0);
                if (!red_admin_article_update($connection, $recordId, [$orderColumn => $positionOrder])) {
                    return false;
                }
            }

            return $attempted;
        });
    }
}

if (!function_exists('red_admin_article_insert')) {
    function red_admin_article_insert($connection, $recordId, $data)
    {
        if ($recordId <= 0) {
            return false;
        }

        $data = array_merge(red_admin_article_default_insert_data($recordId), $data);
        $data['RecordID'] = $recordId;

        $columns = [];
        $placeholders = [];
        $types = '';
        $values = [];

        foreach ($data as $fieldName => $value) {
            if (!isset(red_admin_article_columns()[$fieldName])) {
                continue;
            }
            $columns[] = "`$fieldName`";
            $placeholders[] = '?';
            $types .= red_admin_article_param_type($fieldName);
            $values[] = $value;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Articles (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            if (!$stmt) {
                return false;
            }

            if (!red_admin_article_bind_values($stmt, $types, $values)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Articles insert failed: ' . $e->getMessage());
            return false;
        }
    }
}

?>
