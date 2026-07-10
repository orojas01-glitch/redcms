<?php
/**
 * Helpers for admin RED_C_Form write endpoints.
 */

require_once __DIR__ . '/admin_article_helpers.php';

if (!function_exists('red_admin_form_scalar')) {
    function red_admin_form_scalar($value)
    {
        return is_array($value) ? '' : (string) $value;
    }
}

if (!function_exists('red_admin_form_columns')) {
    function red_admin_form_columns()
    {
        return [
            'RecordID' => true,
            'RefID' => true,
            'Title' => true,
            'Alias' => true,
            'FormType' => true,
            'ShortDesc' => true,
            'LongDesc' => true,
            'Subject' => true,
            'Submitter' => true,
            'Destinatary' => true,
            'CC' => true,
            'BCC' => true,
            'Response' => true,
            'TableName' => true,
        ];
    }
}

if (!function_exists('red_admin_form_article_post')) {
    function red_admin_form_article_post($post, $artRecordId, $mode)
    {
        $fields = [
            'Title',
            'Alias',
            'Tags',
            'Active',
            'Sections',
            'Categories',
            'SubCategories',
            'Article',
            'HomePosition',
            'SectionPosition',
            'CategoryPosition',
            'SubCategoryPosition',
            'PagePosition',
            'HomePositionOrder',
            'SectionPositionOrder',
            'CategoryPositionOrder',
            'SubCategoryPositionOrder',
            'PagePositionOrder',
            'HomeFeature',
            'StartDate',
            'ExpDate',
            'BigPict',
            'SmallPict',
            'SmallPictAlign',
            'EditedBy',
        ];

        if ($mode === 'insert') {
            $fields[] = 'Language';
            $fields[] = 'Component';
            $fields[] = 'Layout';
        }

        $articlePost = ['RecordID' => $artRecordId];
        foreach ($fields as $fieldName) {
            if (array_key_exists($fieldName, $post)) {
                $articlePost[$fieldName] = $post[$fieldName];
            }
        }

        foreach (['Delete_BigPict', 'Delete_SmallPict'] as $deleteField) {
            if (array_key_exists($deleteField, $post)) {
                $articlePost[$deleteField] = $post[$deleteField];
            }
        }

        if ($mode === 'insert' && !isset($articlePost['Component'])) {
            $articlePost['Component'] = 'Form';
        }

        return $articlePost;
    }
}

if (!function_exists('red_admin_form_has_form_payload')) {
    function red_admin_form_has_form_payload($post)
    {
        $controls = [
            'csrf_token' => true,
            'RecordID' => true,
            'ArtRecordID' => true,
            'submit' => true,
        ];
        $columns = red_admin_form_columns();

        foreach ($post as $name => $value) {
            if (!is_string($name) || isset($controls[$name])) {
                continue;
            }
            if (isset($columns[$name]) && $name !== 'RecordID' && $name !== 'RefID') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('red_admin_form_has_payload')) {
    function red_admin_form_has_payload($post)
    {
        if (red_admin_form_has_form_payload($post)) {
            return true;
        }

        $articlePost = red_admin_form_article_post($post, (int) ($post['ArtRecordID'] ?? 0), 'update');
        unset($articlePost['Language'], $articlePost['Component'], $articlePost['Layout']);

        return red_admin_article_has_payload($articlePost);
    }
}

if (!function_exists('red_admin_form_clean_type')) {
    function red_admin_form_clean_type($value)
    {
        return substr(red_admin_text(red_admin_form_scalar($value)), 0, 20);
    }
}

if (!function_exists('red_admin_form_collect_values')) {
    function red_admin_form_collect_values($post, $mode, $recordId, $artRecordId)
    {
        $data = [];
        if ($mode === 'insert') {
            $data['RecordID'] = $recordId;
            $data['RefID'] = (string) $artRecordId;
        }

        if (array_key_exists('Title', $post)) {
            $data['Title'] = red_admin_form_scalar($post['Title']);
            if ($mode === 'insert' && !array_key_exists('Alias', $post)) {
                $data['Alias'] = red_admin_slug($data['Title'], true);
            }
        }

        if (array_key_exists('Alias', $post)) {
            $data['Alias'] = red_admin_slug($post['Alias'], true);
        }

        if (array_key_exists('FormType', $post)) {
            $data['FormType'] = red_admin_form_clean_type($post['FormType']);
        }

        foreach (['ShortDesc', 'LongDesc', 'Subject', 'Submitter', 'Destinatary', 'CC', 'BCC', 'Response'] as $fieldName) {
            if (array_key_exists($fieldName, $post)) {
                $data[$fieldName] = red_admin_form_scalar($post[$fieldName]);
            }
        }

        return $data;
    }
}

if (!function_exists('red_admin_form_identifier')) {
    function red_admin_form_identifier($identifier)
    {
        $identifier = red_admin_text(red_admin_form_scalar($identifier));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $identifier)) {
            return null;
        }

        return $identifier;
    }
}

if (!function_exists('red_admin_form_registration_table_name')) {
    function red_admin_form_registration_table_name($value, $artRecordId)
    {
        $value = red_admin_text(red_admin_form_scalar($value));
        if ($value === '') {
            $value = 'RED_Register_' . (int) $artRecordId;
        }

        return red_admin_form_identifier($value);
    }
}

if (!function_exists('red_admin_form_uses_registration_table')) {
    function red_admin_form_uses_registration_table($formType)
    {
        return $formType === 'Register';
    }
}

if (!function_exists('red_admin_form_apply_table_name')) {
    function red_admin_form_apply_table_name($post, $artRecordId, &$data)
    {
        $formType = $data['FormType'] ?? red_admin_form_clean_type($post['FormType'] ?? '');
        if (red_admin_form_uses_registration_table($formType)) {
            $tableName = red_admin_form_registration_table_name($post['TableName'] ?? '', $artRecordId);
            if ($tableName === null) {
                return false;
            }

            $data['TableName'] = $tableName;
            return true;
        }

        if (array_key_exists('TableName', $post)) {
            $rawTableName = red_admin_text(red_admin_form_scalar($post['TableName']));
            if ($rawTableName === '') {
                $data['TableName'] = '';
                return true;
            }

            $tableName = red_admin_form_identifier($rawTableName);
            if ($tableName === null) {
                return false;
            }

            $data['TableName'] = $tableName;
        }

        return true;
    }
}

if (!function_exists('red_admin_form_default_insert_data')) {
    function red_admin_form_default_insert_data($recordId, $artRecordId)
    {
        return [
            'RecordID' => $recordId,
            'RefID' => (string) $artRecordId,
            'Title' => '',
            'Alias' => '',
            'FormType' => '',
            'ShortDesc' => '',
            'LongDesc' => '',
            'Subject' => '',
            'Submitter' => '',
            'Destinatary' => '',
            'CC' => '',
            'BCC' => '',
            'Response' => '',
            'TableName' => '',
        ];
    }
}

if (!function_exists('red_admin_form_param_type')) {
    function red_admin_form_param_type($fieldName)
    {
        return $fieldName === 'RecordID' ? 'i' : 's';
    }
}

if (!function_exists('red_admin_form_record_exists')) {
    function red_admin_form_record_exists($connection, $recordId)
    {
        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID FROM RED_C_Form WHERE RecordID=? LIMIT 1');
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
            return $exists;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_C_Form lookup failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_form_render_record')) {
    function red_admin_form_render_record($connection, $recordId, $artRecordId)
    {
        $recordId = (int) red_admin_scalar($recordId);
        $artRecordId = (int) red_admin_scalar($artRecordId);
        if ($recordId <= 0 || $artRecordId <= 0) {
            return null;
        }

        return red_admin_area_fetch_one(
            $connection,
            'SELECT * FROM RED_C_Form WHERE RecordID=? AND RefID=? LIMIT 1',
            'is',
            [$recordId, (string) $artRecordId],
            'RED_C_Form render lookup failed'
        );
    }
}

if (!function_exists('red_admin_form_record_matches')) {
    function red_admin_form_record_matches($connection, $recordId, $artRecordId)
    {
        try {
            $stmt = mysqli_prepare($connection, 'SELECT RecordID FROM RED_C_Form WHERE RecordID=? AND RefID=? LIMIT 1');
            if (!$stmt) {
                return false;
            }

            $refId = (string) $artRecordId;
            mysqli_stmt_bind_param($stmt, 'is', $recordId, $refId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            mysqli_stmt_store_result($stmt);
            $matches = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
            return $matches;
        } catch (mysqli_sql_exception $e) {
            error_log('RED_C_Form ref lookup failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_form_update')) {
    function red_admin_form_update($connection, $recordId, $data)
    {
        unset($data['RecordID'], $data['RefID']);
        if ($recordId <= 0 || empty($data)) {
            return false;
        }

        $sets = [];
        $types = '';
        $values = [];
        foreach ($data as $fieldName => $value) {
            if (!isset(red_admin_form_columns()[$fieldName]) || $fieldName === 'RecordID') {
                continue;
            }

            $sets[] = "`$fieldName`=?";
            $types .= red_admin_form_param_type($fieldName);
            $values[] = $value;
        }

        if (empty($sets)) {
            return false;
        }

        $types .= 'i';
        $values[] = $recordId;

        try {
            $stmt = mysqli_prepare($connection, 'UPDATE RED_C_Form SET ' . implode(', ', $sets) . ' WHERE RecordID=?');
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
            error_log('RED_C_Form update failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_form_insert')) {
    function red_admin_form_insert($connection, $recordId, $artRecordId, $data)
    {
        if ($recordId <= 0 || $artRecordId <= 0) {
            return false;
        }

        $data = array_merge(red_admin_form_default_insert_data($recordId, $artRecordId), $data);
        $data['RecordID'] = $recordId;
        $data['RefID'] = (string) $artRecordId;

        $columns = [];
        $placeholders = [];
        $types = '';
        $values = [];
        foreach ($data as $fieldName => $value) {
            if (!isset(red_admin_form_columns()[$fieldName])) {
                continue;
            }

            $columns[] = "`$fieldName`";
            $placeholders[] = '?';
            $types .= red_admin_form_param_type($fieldName);
            $values[] = $value;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_C_Form (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
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
            error_log('RED_C_Form insert failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_form_save')) {
    function red_admin_form_save($connection, $recordId, $artRecordId, $data)
    {
        if (red_admin_form_record_exists($connection, $recordId)) {
            if (!red_admin_form_record_matches($connection, $recordId, $artRecordId)) {
                return false;
            }

            return red_admin_form_update($connection, $recordId, $data);
        }

        return red_admin_form_insert($connection, $recordId, $artRecordId, $data);
    }
}

if (!function_exists('red_admin_form_parse_definition')) {
    function red_admin_form_parse_definition($definition)
    {
        $rows = [];
        foreach (explode(';', (string) $definition) as $rowText) {
            $rowText = trim($rowText);
            if ($rowText === '') {
                continue;
            }

            $delimiter = strpos($rowText, '|') !== false ? '|' : '*';
            $row = [];
            foreach (explode($delimiter, $rowText) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $pair = explode('=', $part, 2);
                if (count($pair) === 2 && $pair[0] !== '') {
                    $row[$pair[0]] = $pair[1];
                }
            }

            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('red_admin_form_registration_columns_sql')) {
    function red_admin_form_registration_columns_sql($connection, $definition)
    {
        $typeLengths = [
            'textfield' => 100,
            'textarea' => 250,
            'checkbox' => 100,
            'radio' => 100,
            'select' => 100,
            'hidden' => 100,
            'password' => 100,
        ];

        $columns = [];
        $seen = [];
        foreach (red_admin_form_parse_definition($definition) as $field) {
            $fieldType = red_admin_text($field['type'] ?? '');
            if (!isset($typeLengths[$fieldType])) {
                continue;
            }

            $fieldName = red_admin_form_identifier($field['name'] ?? '');
            if ($fieldName === null || isset($seen[$fieldName]) || in_array(strtolower($fieldName), ['recordid', 'updatedate'], true)) {
                continue;
            }

            $seen[$fieldName] = true;
            $comment = mysqli_real_escape_string($connection, red_admin_form_scalar($field['displayname'] ?? ''));
            $columns[] = "`$fieldName` varchar(" . $typeLengths[$fieldType] . ") NOT NULL COMMENT '" . $comment . "'";
        }

        return $columns;
    }
}

if (!function_exists('red_admin_form_create_registration_table')) {
    function red_admin_form_create_registration_table($connection, $tableName, $definition)
    {
        $tableName = red_admin_form_identifier($tableName);
        if ($tableName === null) {
            return false;
        }

        $columns = array_merge(
            ['`RecordID` int(5) NOT NULL AUTO_INCREMENT PRIMARY KEY'],
            red_admin_form_registration_columns_sql($connection, $definition),
            ['`updatedate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP']
        );

        try {
            return mysqli_query($connection, 'CREATE TABLE IF NOT EXISTS `' . $tableName . '` (' . implode(', ', $columns) . ')') !== false;
        } catch (mysqli_sql_exception $e) {
            error_log('Registration table create failed: ' . $e->getMessage());
            return false;
        }
    }
}

?>
