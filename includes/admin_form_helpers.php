<?php
/**
 * Helpers for admin RED_C_Form write endpoints.
 */

require_once __DIR__ . '/admin_article_helpers.php';
require_once __DIR__ . '/public_form_helpers.php';
require_once __DIR__ . '/public_form_operation_helpers.php';

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
        // Registration storage identifiers are server-owned. The legacy
        // administrator field is intentionally ignored so a posted value can
        // never target an existing CMS table.
        unset($value);
        return red_admin_form_identifier('RED_Register_' . (int) $artRecordId);
    }
}

if (!function_exists('red_admin_form_uses_registration_table')) {
    function red_admin_form_uses_registration_table($formType)
    {
        return $formType === 'Register';
    }
}

if (!function_exists('red_admin_form_apply_table_name')) {
    function red_admin_form_apply_table_name($post, $artRecordId, &$data, $mode = 'insert')
    {
        $formType = $data['FormType'] ?? red_admin_form_clean_type($post['FormType'] ?? '');
        if (red_admin_form_uses_registration_table($formType)) {
            // Existing Register records may rely on a historical custom table.
            // Updates preserve that value byte-for-byte; only creation receives
            // the deterministic, server-generated identifier.
            if ($mode !== 'insert') {
                unset($data['TableName']);
                return true;
            }

            $tableName = red_admin_form_registration_table_name('', $artRecordId);
            if ($tableName === null) {
                return false;
            }

            $data['TableName'] = $tableName;
            return true;
        }

        // Non-registration forms never receive a storage table, regardless of
        // any forged legacy TableName control in the request.
        if ($mode === 'insert') {
            $data['TableName'] = '';
        } else {
            unset($data['TableName']);
        }

        return true;
    }
}

if (!function_exists('red_admin_form_definition_has_password')) {
    function red_admin_form_definition_has_password($definition)
    {
        foreach (red_admin_form_parse_definition($definition) as $field) {
            if (strcasecmp(red_admin_form_scalar($field['type'] ?? ''), 'password') === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('red_admin_form_schema_is_locked')) {
    function red_admin_form_schema_is_locked($formType)
    {
        return in_array(red_admin_form_clean_type($formType), ['Login', 'Register'], true);
    }
}

if (!function_exists('red_admin_form_data_is_safe')) {
    /**
     * Validate the effective stored Form payload before an administrator write.
     *
     * Public operational forms share the same definition compiler and mailbox
     * rules as their submission endpoints. Login remains the only form type
     * permitted to contain a password field.
     */
    function red_admin_form_data_is_safe($data)
    {
        if (!is_array($data)) {
            return false;
        }

        $formType = red_admin_form_clean_type($data['FormType'] ?? '');
        $definition = red_admin_form_scalar($data['LongDesc'] ?? '');
        if ($formType === '' || ($formType !== 'Login' && red_admin_form_definition_has_password($definition))) {
            return false;
        }

        if (in_array($formType, ['Contact', 'Response', 'Register'], true)) {
            try {
                red_public_contact_compile_fields($definition);
            } catch (Throwable $exception) {
                return false;
            }

            $subject = red_admin_form_scalar($data['Subject'] ?? '');
            if (strlen($subject) > 255 || preg_match('/[\r\n\0]/', $subject)) {
                return false;
            }

            $fromMailboxes = red_public_contact_mailboxes(red_admin_form_scalar($data['Submitter'] ?? ''));
            $toMailboxes = red_public_contact_mailboxes(red_admin_form_scalar($data['Destinatary'] ?? ''));
            $ccMailboxes = red_public_contact_mailboxes(red_admin_form_scalar($data['CC'] ?? ''));
            $bccMailboxes = red_public_contact_mailboxes(red_admin_form_scalar($data['BCC'] ?? ''));
            if (!is_array($fromMailboxes)
                || count($fromMailboxes) !== 1
                || !is_array($toMailboxes)
                || count($toMailboxes) === 0
                || !is_array($ccMailboxes)
                || !is_array($bccMailboxes)
            ) {
                return false;
            }
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
        $typeSql = [
            'textfield' => 'text',
            'textarea' => 'mediumtext',
            'checkbox' => 'text',
            'radio' => 'text',
            'select' => 'text',
            'hidden' => 'text',
        ];

        $columns = [];
        $seen = [];
        foreach (red_admin_form_parse_definition($definition) as $field) {
            $fieldType = strtolower(trim(red_admin_text($field['type'] ?? '')));
            if (!isset($typeSql[$fieldType])) {
                continue;
            }

            $fieldName = red_admin_form_identifier($field['name'] ?? '');
            if ($fieldName === null || isset($seen[$fieldName]) || in_array(strtolower($fieldName), ['recordid', 'updatedate'], true)) {
                continue;
            }

            $seen[$fieldName] = true;
            $comment = mysqli_real_escape_string($connection, red_admin_form_scalar($field['displayname'] ?? ''));
            $columns[] = "`$fieldName` " . $typeSql[$fieldType] . " NOT NULL COMMENT '" . $comment . "'";
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
            return mysqli_query(
                $connection,
                'CREATE TABLE `' . $tableName . '` (' . implode(', ', $columns) . ') '
                . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            ) !== false;
        } catch (mysqli_sql_exception $e) {
            error_log('Registration table create failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_form_registration_table_exists')) {
    function red_admin_form_registration_table_exists($connection, $tableName)
    {
        $tableName = red_admin_form_identifier($tableName);
        if ($tableName === null) {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'
            );
            if (!$stmt) {
                return null;
            }
            mysqli_stmt_bind_param($stmt, 's', $tableName);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }
            mysqli_stmt_bind_result($stmt, $count);
            $fetched = mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return $fetched ? ((int) $count > 0) : null;
        } catch (mysqli_sql_exception $exception) {
            error_log('Registration table existence check failed: ' . $exception->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_form_drop_registration_table')) {
    /** Remove only a table created by the current failed insert request. */
    function red_admin_form_drop_registration_table($connection, $tableName)
    {
        $tableName = red_admin_form_identifier($tableName);
        if ($tableName === null || strpos($tableName, 'RED_Register_') !== 0) {
            return false;
        }

        try {
            return mysqli_query($connection, 'DROP TABLE `' . $tableName . '`') !== false;
        } catch (mysqli_sql_exception $exception) {
            error_log('Registration table rollback failed: ' . $exception->getMessage());
            return false;
        }
    }
}

?>
