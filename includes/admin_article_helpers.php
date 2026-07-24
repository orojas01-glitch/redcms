<?php
/**
 * Helpers for admin RED_Articles write endpoints.
 */

require_once __DIR__ . '/admin_area_helpers.php';
require_once __DIR__ . '/admin_transaction_helpers.php';

if (!function_exists('red_admin_article_batch_transaction')) {
    function red_admin_article_batch_transaction($connection, $callback)
    {
        $tables = ['RED_Articles'];
        if (function_exists('red_admin_content_revision_tables')) {
            $tables = red_admin_content_revision_tables($connection, $tables);
        }
        return red_admin_theme_contract_write_transaction($connection, $callback, $tables);
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
                return in_array($value, ['Article', 'Other', 'Gallery', 'Form', 'MainMenu'], true) ? $value : '';

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
    function red_admin_article_apply_home_position($connection, &$data, $existingRow)
    {
        if (($data['HomeFeature'] ?? '') !== 'Y' || isset($data['HomePosition']) || !$existingRow) {
            return true;
        }

        if ((int) ($existingRow['HomePosition'] ?? 0) === 0) {
            $language = substr(red_admin_text($data['Language'] ?? ''), 0, 2);
            if ($language === '') {
                $language = red_admin_area_language();
            }
            $homeLayout = red_admin_article_area_layout(
                $connection,
                'RED_Sections',
                'Sections',
                'home',
                $language
            );
            $definition = red_admin_area_layout_definition($connection, $homeLayout);
            if ($definition === null) {
                return false;
            }
            $position = red_admin_article_layout_default_position($definition);
            if ($position < 1) {
                return false;
            }
            $data['HomePosition'] = $position;
        }

        return true;
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

if (!function_exists('red_admin_article_upload_placeholder_contract')) {
    function red_admin_article_upload_placeholder_contract($connection, $language = '')
    {
        try {
            $contract = red_theme_active_layout_contract($connection);
            $layout = array_key_first($contract['catalog']);
        } catch (Throwable $exception) {
            return null;
        }
        if (!is_string($layout) || $layout === '') {
            return null;
        }

        return [
            'Layout' => $layout,
            'PagePosition' => 0,
            'Active' => 'N',
            'Language' => substr(red_admin_text($language), 0, 2),
        ];
    }
}

if (!function_exists('red_admin_article_upload_placeholder_data')) {
    function red_admin_article_upload_placeholder_data(
        $connection,
        $recordId,
        $field,
        $storedName,
        $language = '',
        $component = ''
    ) {
        $recordId = (int) $recordId;
        $allowedFields = ['BigPict' => true, 'SmallPict' => true, 'SmallPict2' => true];
        $component = red_admin_article_clean_value('Component', $component);
        $storedName = red_admin_text($storedName);
        if ($recordId <= 0 || !isset($allowedFields[$field]) || $component === '' || $storedName === '') {
            return null;
        }

        $contract = red_admin_article_upload_placeholder_contract($connection, $language);
        if ($contract === null) {
            return null;
        }

        $data = red_admin_article_default_insert_data($recordId);
        $data[$field] = $storedName;
        $data['Component'] = $component;
        $data['Layout'] = $contract['Layout'];
        $data['PagePosition'] = $contract['PagePosition'];
        $data['Active'] = $contract['Active'];
        $data['Language'] = $contract['Language'];
        return $data;
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

if (!function_exists('red_admin_article_insert_upload_placeholder')) {
    function red_admin_article_insert_upload_placeholder(
        $connection,
        $recordId,
        $field,
        $storedName,
        $language = '',
        $component = ''
    ) {
        $data = red_admin_article_upload_placeholder_data(
            $connection,
            $recordId,
            $field,
            $storedName,
            $language,
            $component
        );
        if ($data === null) {
            return false;
        }

        $columns = [];
        $placeholders = [];
        $types = '';
        $values = [];
        foreach ($data as $fieldName => $value) {
            if (!isset(red_admin_article_columns()[$fieldName])) {
                return false;
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
            if (!$stmt || !red_admin_article_bind_values($stmt, $types, $values)) {
                if ($stmt) {
                    mysqli_stmt_close($stmt);
                }
                return false;
            }

            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        } catch (mysqli_sql_exception $exception) {
            error_log('RED_Articles upload placeholder insert failed: ' . $exception->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_article_is_upload_placeholder')) {
    function red_admin_article_is_upload_placeholder($row)
    {
        if (!is_array($row) || (int) ($row['RecordID'] ?? 0) <= 0) {
            return false;
        }

        $component = red_admin_article_clean_value('Component', $row['Component'] ?? '');
        $layout = red_admin_text($row['Layout'] ?? '');
        $hasImage = red_admin_text($row['BigPict'] ?? '') !== ''
            || red_admin_text($row['SmallPict'] ?? '') !== ''
            || red_admin_text($row['SmallPict2'] ?? '') !== '';
        if ($component === ''
            || $layout === ''
            || !$hasImage
            || (int) ($row['PagePosition'] ?? -1) !== 0
            || red_admin_text($row['Active'] ?? '') !== 'N'
        ) {
            return false;
        }

        $defaults = red_admin_article_default_insert_data((int) $row['RecordID']);
        $allowedDifferences = [
            'Component' => true,
            'Layout' => true,
            'PagePosition' => true,
            'Active' => true,
            'Language' => true,
            'BigPict' => true,
            'SmallPict' => true,
            'SmallPict2' => true,
        ];
        foreach ($defaults as $fieldName => $defaultValue) {
            if (isset($allowedDifferences[$fieldName])) {
                continue;
            }
            if ((string) ($row[$fieldName] ?? '') !== (string) $defaultValue) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_admin_article_prepare_upload_placeholder_promotion')) {
    function red_admin_article_prepare_upload_placeholder_promotion($connection, $recordId, &$data)
    {
        $row = red_admin_article_full_record($connection, $recordId);
        if (!$row) {
            return false;
        }
        if (!red_admin_article_is_upload_placeholder($row)) {
            return true;
        }

        $layout = red_admin_text($data['Layout'] ?? ($row['Layout'] ?? ''));
        $definition = red_admin_area_layout_definition($connection, $layout);
        if ($definition === null) {
            return false;
        }
        $position = red_admin_article_layout_default_position($definition);
        if ($position < 1) {
            return false;
        }

        if (!array_key_exists('PagePosition', $data) || (int) $data['PagePosition'] < 1) {
            $data['PagePosition'] = $position;
        }
        return true;
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

        $language = red_admin_area_language();
        $activeClause = $activeOnly ? " AND area_row.Active='Y'" : '';
        if ($table === 'RED_Categories') {
            $sql = 'SELECT area_row.Categories AS AreaName, section_area.Sections AS ParentSection, ' .
                '\'\'' . ' AS ParentCategory FROM RED_Categories AS area_row ' .
                'JOIN RED_Sections AS section_area ON section_area.RecordID=area_row.SectionRecordID ' .
                'AND section_area.Language=area_row.Language ' .
                'WHERE area_row.Language=?' . $activeClause .
                ($activeOnly ? " AND section_area.Active='Y'" : '') .
                ' ORDER BY area_row.Categories ASC';
        } elseif ($table === 'RED_SubCategories') {
            $sql = 'SELECT area_row.SubCategories AS AreaName, section_area.Sections AS ParentSection, ' .
                'category_area.Categories AS ParentCategory FROM RED_SubCategories AS area_row ' .
                'JOIN RED_Categories AS category_area ON category_area.RecordID=area_row.CategoryRecordID ' .
                'AND category_area.Language=area_row.Language ' .
                'JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID ' .
                'AND section_area.Language=category_area.Language ' .
                'WHERE area_row.Language=?' . $activeClause .
                ($activeOnly ? " AND category_area.Active='Y' AND section_area.Active='Y'" : '') .
                ' ORDER BY area_row.SubCategories ASC';
        } else {
            $sql = 'SELECT area_row.`' . $column . '` AS AreaName, \'\'' .
                ' AS ParentSection, \'\'' . ' AS ParentCategory FROM `' . $table . '` AS area_row ' .
                'WHERE area_row.Language=?' . $activeClause . ' ORDER BY area_row.`' . $column . '` ASC';
        }
        $rows = red_admin_article_fetch_all(
            $connection,
            $sql,
            's',
            [$language],
            $table . ' option lookup failed'
        );

        $options = '';
        foreach ($rows as $row) {
            $value = red_admin_text($row['AreaName'] ?? '');
            if ($value === '') {
                continue;
            }
            $selectedAttribute = strcasecmp($value, red_admin_text($selected)) === 0
                ? ' selected="selected"'
                : '';
            $options .= '<option value="' . red_admin_area_html($value) . '"'
                . ' data-parent-section="' . red_admin_area_html($row['ParentSection'] ?? '') . '"'
                . ' data-parent-category="' . red_admin_area_html($row['ParentCategory'] ?? '') . '"'
                . $selectedAttribute . '>' . red_admin_area_html($value) . '</option>';
        }

        return $options;
    }
}

if (!function_exists('red_admin_article_hierarchy_valid')) {
    function red_admin_article_hierarchy_valid($connection, array $candidate)
    {
        $language = substr(red_admin_text($candidate['Language'] ?? ''), 0, 2);
        if ($language === '') {
            $language = red_admin_area_language();
        }
        $section = red_admin_text($candidate['Sections'] ?? '');
        $category = red_admin_text($candidate['Categories'] ?? '');
        $subcategory = red_admin_text($candidate['SubCategories'] ?? '');

        if (($category !== '' && $section === '')
            || ($subcategory !== '' && ($section === '' || $category === ''))
        ) {
            return false;
        }
        if ($section === '') {
            return true;
        }

        $sectionRow = red_admin_area_fetch_one(
            $connection,
            "SELECT RecordID FROM RED_Sections WHERE Sections=? AND Language=? AND Active='Y' LIMIT 1",
            'ss',
            [$section, $language],
            'RED_Articles Section hierarchy validation failed'
        );
        if (!$sectionRow) {
            return false;
        }
        if ($category === '') {
            return $subcategory === '';
        }

        $categoryRow = red_admin_area_fetch_one(
            $connection,
            "SELECT category_area.RecordID FROM RED_Categories AS category_area " .
                "JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID " .
                "AND section_area.Language=category_area.Language " .
                "WHERE category_area.Categories=? AND category_area.Language=? " .
                "AND category_area.Active='Y' AND section_area.Active='Y' AND section_area.Sections=? LIMIT 1",
            'sss',
            [$category, $language, $section],
            'RED_Articles Category hierarchy validation failed'
        );
        if (!$categoryRow) {
            return false;
        }
        if ($subcategory === '') {
            return true;
        }

        return (bool) red_admin_area_fetch_one(
            $connection,
            "SELECT subcategory_area.RecordID FROM RED_SubCategories AS subcategory_area " .
                "JOIN RED_Categories AS category_area ON category_area.RecordID=subcategory_area.CategoryRecordID " .
                "AND category_area.Language=subcategory_area.Language " .
                "JOIN RED_Sections AS section_area ON section_area.RecordID=category_area.SectionRecordID " .
                "AND section_area.Language=category_area.Language " .
                "WHERE subcategory_area.SubCategories=? AND subcategory_area.Language=? " .
                "AND subcategory_area.Active='Y' AND category_area.Active='Y' AND section_area.Active='Y' " .
                "AND category_area.Categories=? AND section_area.Sections=? LIMIT 1",
            'ssss',
            [$subcategory, $language, $category, $section],
            'RED_Articles Subcategory hierarchy validation failed'
        );
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
        return count(red_admin_area_layout_position_options($connection, $layout, false));
    }
}

if (!function_exists('red_admin_article_layout_position_options')) {
    function red_admin_article_layout_position_options($connection, $layout, $includeHidden = true)
    {
        return red_admin_area_layout_position_options($connection, $layout, $includeHidden);
    }
}

if (!function_exists('red_admin_article_layout_default_position')) {
    function red_admin_article_layout_default_position(array $definition)
    {
        foreach (($definition['positions'] ?? []) as $positionId => $label) {
            $positionId = (int) $positionId;
            if ($positionId >= 1 && $positionId <= 99) {
                return $positionId;
            }
        }

        return 0;
    }
}

if (!function_exists('red_admin_article_area_layout')) {
    function red_admin_article_area_layout($connection, $table, $aliasColumn, $alias, $language)
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

        $row = red_admin_area_fetch_one(
            $connection,
            'SELECT Layout FROM `' . $table . '` WHERE `' . $aliasColumn . '`=? AND Language=? ORDER BY RecordID ASC LIMIT 1',
            'ss',
            [$alias, $language],
            $table . ' layout context lookup failed'
        );

        return $row && isset($row['Layout']) ? red_admin_text($row['Layout']) : null;
    }
}

if (!function_exists('red_admin_article_position_dependencies')) {
    function red_admin_article_position_dependencies()
    {
        return [
            'HomePosition' => ['HomePosition', 'Language'],
            'SectionPosition' => ['SectionPosition', 'Sections', 'Language'],
            'CategoryPosition' => ['CategoryPosition', 'Categories', 'Language'],
            'SubCategoryPosition' => ['SubCategoryPosition', 'SubCategories', 'Language'],
            'PagePosition' => [
                'PagePosition',
                'Article',
                'Alias',
                'Sections',
                'Categories',
                'SubCategories',
                'Language',
                'Layout',
            ],
        ];
    }
}

if (!function_exists('red_admin_article_page_layouts')) {
    function red_admin_article_page_layouts($connection, array $candidate, $language)
    {
        $layouts = [];
        $ownLayout = red_admin_text($candidate['Layout'] ?? '');
        if ($ownLayout !== '') {
            $layouts[] = $ownLayout;
        }
        $pageAlias = red_admin_text($candidate['Article'] ?? '');
        $selfAlias = red_admin_text($candidate['Alias'] ?? '');
        if ($pageAlias === '' || ($selfAlias !== '' && strcasecmp($pageAlias, $selfAlias) === 0)) {
            return array_values(array_unique($layouts));
        }

        $sections = red_admin_text($candidate['Sections'] ?? '');
        $categories = red_admin_text($candidate['Categories'] ?? '');
        $subcategories = red_admin_text($candidate['SubCategories'] ?? '');
        $rows = red_admin_area_fetch_all(
            $connection,
            "SELECT DISTINCT Layout FROM RED_Articles\n" .
                "WHERE Language=? AND TRIM(Alias)<>'' AND ? LIKE CONCAT('%', Alias, '%')\n" .
                "AND (\n" .
                "    LOWER(Sections)='home'\n" .
                "    OR (TRIM(SubCategories)<>'' AND Sections=? AND Categories=? AND SubCategories=?)\n" .
                "    OR (TRIM(SubCategories)='' AND TRIM(Categories)<>'' AND Sections=? AND Categories=?)\n" .
                "    OR (TRIM(SubCategories)='' AND TRIM(Categories)='' AND LOWER(Sections)<>'home' AND Sections=?)\n" .
                ") ORDER BY Layout ASC",
            'ssssssss',
            [
                $language,
                $pageAlias,
                $sections,
                $categories,
                $subcategories,
                $sections,
                $categories,
                $sections,
            ],
            'RED_Articles page-owner layouts lookup failed'
        );

        foreach ($rows as $row) {
            $parentLayout = isset($row['Layout']) ? red_admin_text($row['Layout']) : '';
            if ($parentLayout !== '') {
                $layouts[] = $parentLayout;
            }
        }

        return array_values(array_unique($layouts));
    }
}

if (!function_exists('red_admin_article_route_page_positions')) {
    function red_admin_article_route_page_positions($connection, array $candidate)
    {
        $alias = red_admin_text($candidate['Alias'] ?? '');
        $language = substr(red_admin_text($candidate['Language'] ?? ''), 0, 2);
        if ($alias === '' || $language === '') {
            return [];
        }

        $sections = red_admin_text($candidate['Sections'] ?? '');
        $categories = red_admin_text($candidate['Categories'] ?? '');
        $subcategories = red_admin_text($candidate['SubCategories'] ?? '');
        $where = "Language=? AND PagePosition>0 AND ";
        $types = 's';
        $values = [$language];
        $articlePattern = '%' . $alias . '%';

        if (strtolower($sections) === 'home') {
            $where .= "((Sections='Home' AND Alias=?) OR Article LIKE ?)";
            $types .= 'ss';
            array_push($values, $alias, $articlePattern);
        } elseif ($subcategories !== '') {
            $where .= '((Sections=? AND Categories=? AND SubCategories=? AND Alias=?) ' .
                'OR (Sections=? AND Categories=? AND SubCategories=? AND Article LIKE ?))';
            $types .= 'ssssssss';
            array_push(
                $values,
                $sections,
                $categories,
                $subcategories,
                $alias,
                $sections,
                $categories,
                $subcategories,
                $articlePattern
            );
        } elseif ($categories !== '') {
            $where .= '((Sections=? AND Categories=? AND Alias=?) ' .
                'OR (Sections=? AND Categories=? AND Article LIKE ?))';
            $types .= 'ssssss';
            array_push(
                $values,
                $sections,
                $categories,
                $alias,
                $sections,
                $categories,
                $articlePattern
            );
        } elseif ($sections !== '') {
            $where .= '((Sections=? AND Alias=?) OR (Sections=? AND Article LIKE ?))';
            $types .= 'ssss';
            array_push($values, $sections, $alias, $sections, $articlePattern);
        } else {
            $where .= '(Alias=? OR Article LIKE ?)';
            $types .= 'ss';
            array_push($values, $alias, $articlePattern);
        }

        $rows = red_admin_article_fetch_all(
            $connection,
            'SELECT DISTINCT PagePosition AS position_id FROM RED_Articles WHERE ' . $where .
                ' ORDER BY PagePosition ASC',
            $types,
            $values,
            'RED_Articles route page-position lookup failed'
        );

        return array_values(array_map(static function (array $row) {
            return (int) ($row['position_id'] ?? 0);
        }, $rows));
    }
}

if (!function_exists('red_admin_article_position_contract_valid')) {
    function red_admin_article_position_contract_valid(
        array $layoutContract,
        array $row,
        array $areaLayouts,
        array $positionColumns
    ) {
        $manifest = isset($layoutContract['manifest']) && is_array($layoutContract['manifest'])
            ? $layoutContract['manifest']
            : $layoutContract;
        $catalog = isset($layoutContract['catalog']) && is_array($layoutContract['catalog'])
            ? $layoutContract['catalog']
            : [];
        $layoutByPosition = [
            'HomePosition' => $areaLayouts['home'] ?? '',
            'SectionPosition' => $areaLayouts['section'] ?? '',
            'CategoryPosition' => $areaLayouts['category'] ?? '',
            'SubCategoryPosition' => $areaLayouts['subcategory'] ?? '',
            'PagePosition' => $areaLayouts['page'] ?? [$row['Layout'] ?? ''],
        ];

        foreach ($positionColumns as $positionColumn) {
            if (!isset(red_admin_article_position_columns()[$positionColumn])) {
                return false;
            }
            $positionId = (int) ($row[$positionColumn] ?? 0);
            if ($positionId === 0) {
                continue;
            }
            if ($positionId < 1 || $positionId > 99) {
                return false;
            }

            $layoutIds = $layoutByPosition[$positionColumn] ?? '';
            $layoutIds = is_array($layoutIds) ? $layoutIds : [$layoutIds];
            if ($layoutIds === []) {
                return false;
            }
            foreach ($layoutIds as $layoutId) {
                try {
                    $layoutId = red_admin_text($layoutId);
                    $definition = isset($catalog[$layoutId])
                        ? $catalog[$layoutId]
                        : red_theme_layout_definition($manifest, $layoutId);
                } catch (Throwable $exception) {
                    return false;
                }
                if ($definition === null || !isset($definition['positions'][$positionId])) {
                    return false;
                }
            }
        }

        return true;
    }
}

if (!function_exists('red_admin_article_validate_position_changes')) {
    function red_admin_article_validate_position_changes(
        $connection,
        array $candidate,
        array $changedData,
        $validateAll = false,
        array $existing = []
    ) {
        $positionColumns = [];
        foreach (red_admin_article_position_dependencies() as $positionColumn => $dependencies) {
            if ($validateAll) {
                $positionColumns[] = $positionColumn;
                continue;
            }
            foreach ($dependencies as $dependency) {
                if (!array_key_exists($dependency, $changedData)) {
                    continue;
                }
                $changedValue = isset(red_admin_article_integer_columns()[$dependency])
                    ? (int) $changedData[$dependency]
                    : red_admin_text($changedData[$dependency]);
                $existingValue = isset(red_admin_article_integer_columns()[$dependency])
                    ? (int) ($existing[$dependency] ?? 0)
                    : red_admin_text($existing[$dependency] ?? '');
                if (!array_key_exists($dependency, $existing) || $changedValue !== $existingValue) {
                    $positionColumns[] = $positionColumn;
                    break;
                }
            }
        }
        if ($positionColumns === []) {
            return true;
        }

        try {
            $contract = red_theme_active_layout_contract($connection);
        } catch (Throwable $exception) {
            error_log('Active theme position validation unavailable: ' . $exception->getMessage());
            return false;
        }

        $language = substr(red_admin_text($candidate['Language'] ?? ''), 0, 2);
        if ($language === '') {
            $language = red_admin_area_language();
        }
        $areaLayouts = [
            'home' => '',
            'section' => '',
            'category' => '',
            'subcategory' => '',
            'page' => [],
        ];
        foreach ($positionColumns as $positionColumn) {
            if ((int) ($candidate[$positionColumn] ?? 0) === 0) {
                continue;
            }
            if ($positionColumn === 'PagePosition') {
                $areaLayouts['page'] = red_admin_article_page_layouts($connection, $candidate, $language);
            } elseif ($positionColumn === 'HomePosition') {
                $areaLayouts['home'] = red_admin_article_area_layout(
                    $connection,
                    'RED_Sections',
                    'Sections',
                    'home',
                    $language
                );
            } elseif ($positionColumn === 'SectionPosition') {
                $areaLayouts['section'] = red_admin_article_area_layout(
                    $connection,
                    'RED_Sections',
                    'Sections',
                    $candidate['Sections'] ?? '',
                    $language
                );
            } elseif ($positionColumn === 'CategoryPosition') {
                $areaLayouts['category'] = red_admin_article_area_layout(
                    $connection,
                    'RED_Categories',
                    'Categories',
                    $candidate['Categories'] ?? '',
                    $language
                );
            } elseif ($positionColumn === 'SubCategoryPosition') {
                $areaLayouts['subcategory'] = red_admin_article_area_layout(
                    $connection,
                    'RED_SubCategories',
                    'SubCategories',
                    $candidate['SubCategories'] ?? '',
                    $language
                );
            }
        }

        return red_admin_article_position_contract_valid(
            $contract,
            $candidate,
            $areaLayouts,
            $positionColumns
        );
    }
}

if (!function_exists('red_admin_article_update_unlocked')) {
    function red_admin_article_update_unlocked($connection, $recordId, $data)
    {
        unset($data['RecordID']);
        if ($recordId <= 0 || empty($data)) {
            return false;
        }

        $existing = red_admin_article_full_record($connection, $recordId);
        if (!$existing) {
            return false;
        }
        if (array_key_exists('Layout', $data)
            && red_admin_area_layout_definition($connection, $data['Layout']) === null
        ) {
            if (red_admin_text($existing['Layout'] ?? '') !== red_admin_text($data['Layout'])) {
                return false;
            }
        }
        $candidate = array_merge($existing, $data);
        $hierarchyChanged = false;
        foreach (['Sections', 'Categories', 'SubCategories'] as $hierarchyField) {
            if (array_key_exists($hierarchyField, $data)
                && strcasecmp(
                    red_admin_text($data[$hierarchyField]),
                    red_admin_text($existing[$hierarchyField] ?? '')
                ) !== 0
            ) {
                $hierarchyChanged = true;
                break;
            }
        }
        if ($hierarchyChanged && !red_admin_article_hierarchy_valid($connection, $candidate)) {
            return false;
        }
        $layoutChanged = array_key_exists('Layout', $data)
            && red_admin_text($data['Layout']) !== red_admin_text($existing['Layout'] ?? '');
        if ($layoutChanged) {
            $routePositions = red_admin_article_route_page_positions($connection, $candidate);
            $routePositions[] = (int) ($candidate['PagePosition'] ?? 0);
            if (!red_admin_area_layout_supports_positions(
                $connection,
                $candidate['Layout'] ?? '',
                array_values(array_unique($routePositions))
            )) {
                return false;
            }
        }
        if (!red_admin_article_validate_position_changes($connection, $candidate, $data, false, $existing)) {
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

if (!function_exists('red_admin_article_update')) {
    function red_admin_article_update($connection, $recordId, $data)
    {
        return red_admin_with_theme_contract_lock(
            $connection,
            function () use ($connection, $recordId, $data) {
                return red_admin_article_update_unlocked($connection, $recordId, $data);
            }
        );
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

                if (function_exists('red_admin_content_revision_checkpoint')
                    && !red_admin_content_revision_checkpoint($connection, $recordId)
                ) {
                    return false;
                }
                $attempted = true;
                $orderColumn = $positionColumn . 'Order';
                $positionOrder = (int) ($positionOrders[$index] ?? 0);
                if (!red_admin_article_update($connection, $recordId, [$orderColumn => $positionOrder])) {
                    return false;
                }
                if (function_exists('red_admin_content_revision_record_current')
                    && !red_admin_content_revision_record_current($connection, $recordId, 'order')
                ) {
                    return false;
                }
            }

            return $attempted;
        });
    }
}

if (!function_exists('red_admin_article_distribution_items')) {
    function red_admin_article_distribution_items($items)
    {
        if (!is_array($items) || $items === [] || count($items) > 100) {
            return null;
        }

        $normalized = [];
        $occupied = [];
        foreach (array_values($items) as $item) {
            if (!is_array($item)) {
                return null;
            }
            $recordId = (int) ($item['recordId'] ?? 0);
            $position = (int) ($item['position'] ?? -1);
            $order = (int) ($item['order'] ?? 0);
            if ($recordId <= 0 || $position < 0 || $position > 99 || $order < 1 || $order > 100) {
                return null;
            }
            if (isset($normalized[$recordId]) || isset($occupied[$position . ':' . $order])) {
                return null;
            }
            $normalized[$recordId] = [
                'recordId' => $recordId,
                'position' => $position,
                'order' => $order,
            ];
            $occupied[$position . ':' . $order] = true;
        }

        ksort($normalized, SORT_NUMERIC);
        return array_values($normalized);
    }
}

if (!function_exists('red_admin_article_distribution_current')) {
    function red_admin_article_distribution_current($connection, $positionColumn, array $items)
    {
        $positionColumn = red_admin_article_position_column($positionColumn, '');
        if ($positionColumn === null || $positionColumn === '') {
            return null;
        }
        $orderColumn = $positionColumn . 'Order';
        $current = [];
        foreach ($items as $item) {
            $recordId = (int) ($item['recordId'] ?? 0);
            $row = red_admin_article_full_record($connection, $recordId);
            if (!$row) {
                return null;
            }
            $current[] = [
                'recordId' => $recordId,
                'position' => (int) ($row[$positionColumn] ?? 0),
                'order' => (int) ($row[$orderColumn] ?? 0),
            ];
        }
        // Historical rows may use order zero. The editor exposes a one-based
        // visual order, but stale-state comparison must keep the exact value.
        return red_admin_article_distribution_expected_items($current);
    }
}

if (!function_exists('red_admin_article_distribution_expected_items')) {
    function red_admin_article_distribution_expected_items($items)
    {
        if (!is_array($items) || $items === [] || count($items) > 100) {
            return null;
        }
        $normalized = [];
        foreach (array_values($items) as $item) {
            if (!is_array($item)) {
                return null;
            }
            $recordId = (int) ($item['recordId'] ?? 0);
            $position = (int) ($item['position'] ?? -1);
            $order = (int) ($item['order'] ?? -1);
            // Expected state mirrors legacy rows exactly. Older installations
            // were not limited to the editor's new one-through-100 order range,
            // so accept the full positive signed INT range for stale checks.
            if ($recordId <= 0 || $position < 0 || $position > 99 || $order < 0 || $order > 2147483647) {
                return null;
            }
            if (isset($normalized[$recordId])) {
                return null;
            }
            $normalized[$recordId] = [
                'recordId' => $recordId,
                'position' => $position,
                'order' => $order,
            ];
        }
        ksort($normalized, SORT_NUMERIC);
        return array_values($normalized);
    }
}

if (!function_exists('red_admin_article_update_distribution_batch')) {
    function red_admin_article_update_distribution_batch(
        $connection,
        $positionColumn,
        $layout,
        $expectedItems,
        $targetItems
    ) {
        $positionColumn = red_admin_article_position_column($positionColumn, '');
        $layout = red_admin_text($layout);
        $expected = red_admin_article_distribution_expected_items($expectedItems);
        $target = red_admin_article_distribution_items($targetItems);
        if ($positionColumn === null || $positionColumn === '' || $layout === '' || !$expected || !$target) {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        if (array_column($expected, 'recordId') !== array_column($target, 'recordId')) {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        $allowedPositions = red_admin_area_layout_position_options($connection, $layout, true);
        if ($allowedPositions === []) {
            return ['ok' => false, 'reason' => 'layout'];
        }
        foreach ($target as $item) {
            if (!array_key_exists((int) $item['position'], $allowedPositions)) {
                return ['ok' => false, 'reason' => 'position'];
            }
        }

        $failureReason = 'failed';
        $changedCount = 0;
        $success = red_admin_article_batch_transaction(
            $connection,
            function () use (
                $connection,
                $positionColumn,
                $expected,
                $target,
                &$failureReason,
                &$changedCount
            ) {
                $current = red_admin_article_distribution_expected_items(
                    red_admin_article_distribution_current($connection, $positionColumn, $expected)
                );
                if ($current === null || $current !== $expected) {
                    $failureReason = 'conflict';
                    return false;
                }

                $orderColumn = $positionColumn . 'Order';
                $currentById = [];
                foreach ($current as $item) {
                    $currentById[(int) $item['recordId']] = $item;
                }
                foreach ($target as $item) {
                    $recordId = (int) $item['recordId'];
                    $before = $currentById[$recordId] ?? null;
                    if (!$before) {
                        $failureReason = 'invalid';
                        return false;
                    }
                    $positionChanged = (int) $before['position'] !== (int) $item['position'];
                    $orderChanged = (int) $before['order'] !== (int) $item['order'];
                    if (!$positionChanged && !$orderChanged) {
                        continue;
                    }
                    if (function_exists('red_admin_content_revision_checkpoint')
                        && !red_admin_content_revision_checkpoint($connection, $recordId)
                    ) {
                        $failureReason = 'revision';
                        return false;
                    }
                    if (!red_admin_article_update($connection, $recordId, [
                        $positionColumn => (int) $item['position'],
                        $orderColumn => (int) $item['order'],
                    ])) {
                        $failureReason = 'position';
                        return false;
                    }
                    if (function_exists('red_admin_content_revision_record_current')
                        && !red_admin_content_revision_record_current(
                            $connection,
                            $recordId,
                            $positionChanged ? 'move' : 'order'
                        )
                    ) {
                        $failureReason = 'revision';
                        return false;
                    }
                    $changedCount++;
                }
                return true;
            }
        );

        return [
            'ok' => (bool) $success,
            'reason' => $success ? 'saved' : $failureReason,
            'changed' => $success ? $changedCount : 0,
            'items' => $success
                ? red_admin_article_distribution_expected_items(
                    red_admin_article_distribution_current($connection, $positionColumn, $target)
                )
                : $expected,
        ];
    }
}

if (!function_exists('red_admin_article_insert_unlocked')) {
    function red_admin_article_insert_unlocked($connection, $recordId, $data)
    {
        if ($recordId <= 0) {
            return false;
        }

        if (!array_key_exists('Layout', $data) || red_admin_text($data['Layout']) === '') {
            $availableLayouts = red_admin_area_layouts($connection);
            $data['Layout'] = $availableLayouts[0] ?? '';
        }
        $layoutDefinition = red_admin_area_layout_definition($connection, $data['Layout']);
        if ($layoutDefinition === null) {
            return false;
        }

        $insertDefaults = red_admin_article_default_insert_data($recordId);
        $insertDefaults['Layout'] = red_admin_text($data['Layout']);
        $insertDefaults['PagePosition'] = red_admin_article_layout_default_position($layoutDefinition);
        if ($insertDefaults['PagePosition'] < 1) {
            return false;
        }
        $data = array_merge($insertDefaults, $data);
        $data['RecordID'] = $recordId;
        if (!red_admin_article_hierarchy_valid($connection, $data)) {
            return false;
        }
        $routePositions = red_admin_article_route_page_positions($connection, $data);
        $routePositions[] = (int) ($data['PagePosition'] ?? 0);
        if (!red_admin_area_layout_supports_positions(
            $connection,
            $data['Layout'] ?? '',
            array_values(array_unique($routePositions))
        )) {
            return false;
        }
        if (!red_admin_article_validate_position_changes($connection, $data, $data, true)) {
            return false;
        }

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

if (!function_exists('red_admin_article_insert')) {
    function red_admin_article_insert($connection, $recordId, $data)
    {
        return red_admin_with_theme_contract_lock(
            $connection,
            function () use ($connection, $recordId, $data) {
                return red_admin_article_insert_unlocked($connection, $recordId, $data);
            }
        );
    }
}

?>
