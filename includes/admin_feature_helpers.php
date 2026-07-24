<?php
/**
 * Helpers for admin RED_Articles feature toggle endpoints.
 */

require_once __DIR__ . '/admin_area_helpers.php';
require_once __DIR__ . '/admin_article_helpers.php';
require_once __DIR__ . '/admin_authorization_helpers.php';
require_once __DIR__ . '/admin_content_revision_helpers.php';

if (!function_exists('red_admin_feature_columns')) {
    function red_admin_feature_columns()
    {
        return [
            'HomeFeatures' => 'HomeFeatures_Order',
            'SectionFeatures' => 'SectionFeatures_Order',
            'CategoryFeatures' => 'CategoryFeatures_Order',
            'SubCategoryFeatures' => 'SubCategoryFeatures_Order',
        ];
    }
}

if (!function_exists('red_admin_feature_order_column')) {
    function red_admin_feature_order_column($featureColumn)
    {
        $featureColumn = red_admin_text($featureColumn);
        $columns = red_admin_feature_columns();

        return $columns[$featureColumn] ?? '';
    }
}

if (!function_exists('red_admin_feature_position_column')) {
    function red_admin_feature_position_column($featureColumn)
    {
        $featureColumn = red_admin_text($featureColumn);
        $columns = [
            'HomeFeatures' => 'HomePosition',
            'SectionFeatures' => 'SectionPosition',
            'CategoryFeatures' => 'CategoryPosition',
            'SubCategoryFeatures' => 'SubCategoryPosition',
        ];

        return $columns[$featureColumn] ?? '';
    }
}

if (!function_exists('red_admin_feature_scope_label')) {
    function red_admin_feature_scope_label($featureColumn)
    {
        $featureColumn = red_admin_text($featureColumn);
        $labels = [
            'HomeFeatures' => 'Home',
            'SectionFeatures' => 'Section',
            'CategoryFeatures' => 'Category',
            'SubCategoryFeatures' => 'Subcategory',
        ];

        return $labels[$featureColumn] ?? 'Current page';
    }
}

if (!function_exists('red_admin_feature_list_contains')) {
    function red_admin_feature_list_contains($value, $featureName)
    {
        $featureName = red_admin_text($featureName);
        if ($featureName === '') {
            return false;
        }

        foreach (explode(',', (string) $value) as $feature) {
            if (red_admin_text($feature) === $featureName) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('red_admin_feature_toggle_list')) {
    function red_admin_feature_toggle_list($value, $featureName, $enabled)
    {
        $featureName = red_admin_text($featureName);
        $features = [];

        foreach (explode(',', (string) $value) as $feature) {
            $feature = red_admin_text($feature);
            if ($feature !== '' && $feature !== $featureName && !in_array($feature, $features, true)) {
                $features[] = $feature;
            }
        }

        if ($enabled && $featureName !== '') {
            $features[] = $featureName;
        }

        return implode(',', $features);
    }
}

if (!function_exists('red_admin_feature_selected')) {
    function red_admin_feature_selected($post, $fieldName, $index)
    {
        if (isset($post[$fieldName]) && is_array($post[$fieldName])) {
            foreach ($post[$fieldName] as $key => $value) {
                if ((string) $key === (string) $index && red_admin_text($value) === 'Y') {
                    return true;
                }
            }
        }

        $literalField = $fieldName . '[' . $index . ']';
        return isset($post[$literalField]) && red_admin_text($post[$literalField]) === 'Y';
    }
}

if (!function_exists('red_admin_feature_current')) {
    function red_admin_feature_current($connection, $recordId, $featureColumn)
    {
        $recordId = (int) $recordId;
        $orderColumn = red_admin_feature_order_column($featureColumn);
        if ($recordId <= 0 || $orderColumn === '') {
            return null;
        }

        try {
            $stmt = mysqli_prepare($connection, "SELECT `$featureColumn` FROM RED_Articles WHERE RecordID=? LIMIT 1");
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

            return $row ? (string) ($row[$featureColumn] ?? '') : null;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Articles feature lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_feature_update')) {
    function red_admin_feature_update($connection, $recordId, $featureColumn, $featureName, $enabled, $order)
    {
        $recordId = (int) $recordId;
        $featureColumn = red_admin_text($featureColumn);
        $orderColumn = red_admin_feature_order_column($featureColumn);
        if ($recordId <= 0 || $orderColumn === '') {
            return false;
        }

        $currentFeatures = red_admin_feature_current($connection, $recordId, $featureColumn);
        if ($currentFeatures === null) {
            return false;
        }

        $features = red_admin_feature_toggle_list($currentFeatures, $featureName, $enabled);
        $order = max(0, (int) red_admin_text($order));

        try {
            $stmt = mysqli_prepare($connection, "UPDATE RED_Articles SET `$featureColumn`=?, `$orderColumn`=? WHERE RecordID=?");
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, 'sii', $features, $order, $recordId);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_Articles feature update failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_feature_update_batch')) {
    function red_admin_feature_update_batch($connection, $post, $featureColumn, $featureName, $selectionField)
    {
        $recordIds = $post['RecordID'] ?? [];
        $allowedSelections = [
            'slider' => 'sliderSelect',
            'template' => 'templateSelect',
        ];

        if (
            red_admin_feature_order_column($featureColumn) === ''
            || !is_array($recordIds)
            || empty($recordIds)
            || !isset($allowedSelections[$featureName])
            || $allowedSelections[$featureName] !== $selectionField
        ) {
            return false;
        }

        return red_admin_article_batch_transaction($connection, function () use ($connection, $post, $recordIds, $featureColumn, $featureName, $selectionField) {
            $attempted = false;
            foreach ($recordIds as $index => $recordId) {
                $attempted = true;
                if (!red_admin_content_revision_checkpoint($connection, $recordId)) {
                    return false;
                }
                if (!red_admin_feature_update(
                    $connection,
                    $recordId,
                    $featureColumn,
                    $featureName,
                    red_admin_feature_selected($post, $selectionField, $index),
                    $post['FeatureOrder'][$index] ?? 0
                )) {
                    return false;
                }
                if (!red_admin_content_revision_record_current($connection, $recordId, 'save')) {
                    return false;
                }
            }

            return $attempted;
        });
    }
}

if (!function_exists('red_admin_feature_html')) {
    function red_admin_feature_html($value)
    {
        return red_admin_area_html($value);
    }
}

if (!function_exists('red_admin_feature_bind_values')) {
    function red_admin_feature_bind_values($stmt, $types, &$values)
    {
        $refs = [];
        foreach ($values as $key => &$value) {
            $refs[$key] = &$value;
        }

        return mysqli_stmt_bind_param($stmt, $types, ...$refs);
    }
}

if (!function_exists('red_admin_feature_fetch_all')) {
    function red_admin_feature_fetch_all($connection, $sql, $types, $values, $logMessage)
    {
        try {
            $stmt = mysqli_prepare($connection, $sql);
            if (!$stmt) {
                return [];
            }

            if ($types !== '' && !red_admin_feature_bind_values($stmt, $types, $values)) {
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

if (!function_exists('red_admin_feature_components')) {
    function red_admin_feature_components($connection)
    {
        $rows = red_admin_feature_fetch_all(
            $connection,
            'SELECT UniqueName, CompGroup FROM RED_Components ORDER BY UniqueName ASC',
            '',
            [],
            'RED_Components feature render lookup failed'
        );

        return $rows;
    }
}

if (!function_exists('red_admin_feature_component_groups')) {
    function red_admin_feature_component_groups($connection)
    {
        $groups = [];
        foreach (red_admin_feature_components($connection) as $component) {
            $uniqueName = red_admin_text($component['UniqueName'] ?? '');
            if ($uniqueName !== '' && !isset($groups[$uniqueName])) {
                $groups[$uniqueName] = red_admin_text($component['CompGroup'] ?? '');
            }
        }

        return $groups;
    }
}

if (!function_exists('red_admin_feature_articles')) {
    function red_admin_feature_articles($connection, $language, $featureColumn, $articleOnly)
    {
        $language = red_admin_text($language);
        $featureColumn = red_admin_text($featureColumn);
        $orderColumn = red_admin_feature_order_column($featureColumn);
        if ($language === '' || $orderColumn === '') {
            return [];
        }

        $componentClause = $articleOnly ? "Component='Article'" : "Component<>'SubMenu'";

        $rows = red_admin_feature_fetch_all(
            $connection,
            "SELECT RecordID, Title, Alias, Component, SliderDesc, BigPict, `$featureColumn`, `$orderColumn` AS FeatureOrder FROM RED_Articles WHERE Active='Y' AND $componentClause AND Language=? ORDER BY Updated DESC",
            's',
            [$language],
            'RED_Articles feature render lookup failed'
        );

        return red_admin_filter_authorized_articles($connection, $rows);
    }
}

if (!function_exists('red_admin_feature_component_tables')) {
    function red_admin_feature_component_tables()
    {
        return [
            'Form' => 'RED_C_Form',
            'Gallery' => 'RED_C_Gallery',
            'SubMenu' => 'RED_C_Menu',
        ];
    }
}

if (!function_exists('red_admin_feature_component_record_id')) {
    function red_admin_feature_component_record_id($connection, $component, $artRecordId)
    {
        $component = red_admin_text($component);
        $artRecordId = (int) $artRecordId;
        $tables = red_admin_feature_component_tables();
        if ($artRecordId <= 0 || !isset($tables[$component])) {
            return 0;
        }

        try {
            $table = $tables[$component];
            $stmt = mysqli_prepare($connection, "SELECT RecordID FROM `$table` WHERE RefID=? LIMIT 1");
            if (!$stmt) {
                return 0;
            }

            $refId = (string) $artRecordId;
            mysqli_stmt_bind_param($stmt, 's', $refId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return 0;
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            return $row ? (int) $row['RecordID'] : 0;
        } catch (mysqli_sql_exception $e) {
            error_log('Feature component record lookup failed: ' . $e->getMessage());
            return 0;
        }
    }
}

?>
