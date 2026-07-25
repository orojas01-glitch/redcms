<?php
/**
 * Helpers for public form submission endpoints.
 */

if (!function_exists('red_public_form_redirect_home')) {
    function red_public_form_redirect_home()
    {
        header('Location: http://' . BASE_URL . '');
        exit;
    }
}

if (!function_exists('red_public_form_scalar')) {
    function red_public_form_scalar($value)
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $parts[] = red_public_form_scalar($item);
            }

            return implode(', ', $parts);
        }

        return (string) $value;
    }
}

if (!function_exists('red_public_form_clean')) {
    function red_public_form_clean($value)
    {
        return preg_replace("'<[^>]+>'U", '', red_public_form_scalar($value));
    }
}

if (!function_exists('red_public_form_submission_text')) {
    function red_public_form_submission_text($value)
    {
        if (!is_array($value)) {
            return red_public_form_scalar($value);
        }

        $items = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $items[] = (string) $item;
            }
        }

        return implode(', ', $items);
    }
}

if (!function_exists('red_public_form_html')) {
    function red_public_form_html($value)
    {
        return htmlspecialchars(red_public_form_clean($value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('red_public_form_header_value')) {
    function red_public_form_header_value($value)
    {
        return trim(str_replace(["\r", "\n"], ' ', red_public_form_clean($value)));
    }
}

if (!function_exists('red_public_form_post_value')) {
    function red_public_form_post_value($post, $fieldName)
    {
        return array_key_exists($fieldName, $post) ? red_public_form_clean($post[$fieldName]) : '';
    }
}

if (!function_exists('red_public_form_identifier')) {
    function red_public_form_identifier($identifier)
    {
        $identifier = trim(red_public_form_clean($identifier));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $identifier)) {
            return null;
        }

        return $identifier;
    }
}

if (!function_exists('red_public_form_record_id')) {
    function red_public_form_record_id($value)
    {
        $recordId = (int) red_public_form_clean($value);
        return $recordId > 0 ? $recordId : 0;
    }
}

if (!function_exists('red_public_form_fetch_record')) {
    function red_public_form_fetch_record($connection, $recordId)
    {
        $recordId = (int) $recordId;
        if ($recordId <= 0) {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RecordID, FormType, LongDesc, Subject, Submitter, Destinatary, CC, BCC, Response, TableName ' .
                'FROM RED_C_Form WHERE RecordID=? LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }

            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? $result->fetch_assoc() : null;
            mysqli_stmt_close($stmt);
            return $row ?: null;
        } catch (mysqli_sql_exception $e) {
            error_log('Public form lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_public_contact_fetch_record')) {
    /**
     * Resolve one renderable Contact form through its active Form article.
     *
     * This deliberately uses only fixed identifiers in SQL. The posted record
     * id is a bound value, and the stored RefID must resolve to the joined
     * article before the row is returned to the operational adapter.
     */
    function red_public_contact_fetch_record($connection, $recordId)
    {
        $recordId = (int) $recordId;
        if ($recordId <= 0) {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT f.RecordID, f.RefID, f.Alias, f.FormType, f.LongDesc, f.Subject, ' .
                'f.Submitter, f.Destinatary, f.CC, f.BCC, a.RecordID AS ArticleRecordID, ' .
                'a.Component AS ArticleComponent ' .
                'FROM RED_C_Form AS f ' .
                'INNER JOIN RED_Articles AS a ON CAST(f.RefID AS UNSIGNED)=a.RecordID ' .
                "WHERE f.RecordID=? AND f.FormType='Contact' AND a.Component='Form' " .
                "AND a.Active='Y' AND a.StartDate<=NOW() " .
                'AND (YEAR(a.ExpDate)=0 OR a.ExpDate>NOW()) LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }

            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? $result->fetch_assoc() : null;
            mysqli_stmt_close($stmt);
            if (!is_array($row)
                || !preg_match('/\A[1-9][0-9]{0,9}\z/', (string) ($row['RefID'] ?? ''))
                || (int) $row['RefID'] !== (int) ($row['ArticleRecordID'] ?? 0)
                || (string) ($row['FormType'] ?? '') !== 'Contact'
                || (string) ($row['ArticleComponent'] ?? '') !== 'Form'
            ) {
                return null;
            }

            return [
                'recordId' => (int) $row['RecordID'],
                'articleRecordId' => (int) $row['ArticleRecordID'],
                'articleComponent' => (string) $row['ArticleComponent'],
                'alias' => (string) $row['Alias'],
                'formType' => (string) $row['FormType'],
                'definition' => (string) $row['LongDesc'],
                'subject' => (string) $row['Subject'],
                'submitter' => (string) $row['Submitter'],
                'destinatary' => (string) $row['Destinatary'],
                'cc' => (string) $row['CC'],
                'bcc' => (string) $row['BCC'],
            ];
        } catch (mysqli_sql_exception $e) {
            error_log('Public Contact form lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_public_operational_form_fetch_record')) {
    /**
     * Resolve an active, scheduled Response or Register form through its paired
     * Form article. The type is whitelisted before it is bound as a value; all
     * SQL identifiers remain fixed.
     */
    function red_public_operational_form_fetch_record($connection, $recordId, $formType)
    {
        $recordId = (int) $recordId;
        $formType = is_scalar($formType) ? (string) $formType : '';
        if ($recordId <= 0 || !in_array($formType, ['Response', 'Register'], true)) {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT f.RecordID, f.RefID, f.Alias, f.FormType, f.LongDesc, f.Subject, ' .
                'f.Submitter, f.Destinatary, f.CC, f.BCC, f.Response, f.TableName, ' .
                'a.RecordID AS ArticleRecordID, a.Component AS ArticleComponent ' .
                'FROM RED_C_Form AS f ' .
                'INNER JOIN RED_Articles AS a ON CAST(f.RefID AS UNSIGNED)=a.RecordID ' .
                "WHERE f.RecordID=? AND f.FormType=? AND a.Component='Form' " .
                "AND a.Active='Y' AND a.StartDate<=NOW() " .
                'AND (YEAR(a.ExpDate)=0 OR a.ExpDate>NOW()) LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }

            mysqli_stmt_bind_param($stmt, 'is', $recordId, $formType);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? $result->fetch_assoc() : null;
            mysqli_stmt_close($stmt);
            if (!is_array($row)
                || !preg_match('/\A[1-9][0-9]{0,9}\z/', (string) ($row['RefID'] ?? ''))
                || (int) $row['RefID'] !== (int) ($row['ArticleRecordID'] ?? 0)
                || (string) ($row['FormType'] ?? '') !== $formType
                || (string) ($row['ArticleComponent'] ?? '') !== 'Form'
            ) {
                return null;
            }

            return [
                'recordId' => (int) $row['RecordID'],
                'articleRecordId' => (int) $row['ArticleRecordID'],
                'articleComponent' => (string) $row['ArticleComponent'],
                'alias' => (string) $row['Alias'],
                'formType' => (string) $row['FormType'],
                'definition' => (string) $row['LongDesc'],
                'subject' => (string) $row['Subject'],
                'submitter' => (string) $row['Submitter'],
                'destinatary' => (string) $row['Destinatary'],
                'cc' => (string) $row['CC'],
                'bcc' => (string) $row['BCC'],
                'response' => (string) $row['Response'],
                'tableName' => (string) $row['TableName'],
            ];
        } catch (mysqli_sql_exception $exception) {
            error_log('Public operational Form lookup failed: ' . $exception->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_public_contact_mailboxes')) {
    /**
     * Parse the legacy `email,name;email,name` storage format without allowing
     * invalid or header-injecting mailbox values to reach either mail transport.
     * Returns null when any configured entry is malformed.
     */
    function red_public_contact_mailboxes($recipients)
    {
        if (!is_scalar($recipients)) {
            return null;
        }

        $recipients = (string) $recipients;
        if (strlen($recipients) > 4096 || preg_match('/[\r\n\0]/', $recipients)) {
            return null;
        }

        $mailboxes = [];
        foreach (explode(';', $recipients) as $recipient) {
            $recipient = trim($recipient);
            if ($recipient === '') {
                continue;
            }

            $parts = explode(',', $recipient, 2);
            $email = trim($parts[0] ?? '');
            $name = trim($parts[1] ?? '');
            if ($email === ''
                || strlen($email) > 254
                || strlen($name) > 200
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            ) {
                return null;
            }

            $mailboxes[] = ['email' => $email, 'name' => $name];
            if (count($mailboxes) > 50) {
                return null;
            }
        }

        return $mailboxes;
    }
}

if (!function_exists('red_public_form_parse_definition')) {
    function red_public_form_parse_definition($definition)
    {
        $fields = [];
        foreach (explode(';', (string) $definition) as $rowText) {
            $rowText = trim($rowText);
            if ($rowText === '') {
                continue;
            }

            $delimiter = strpos($rowText, '|') !== false ? '|' : '*';
            $field = [];
            foreach (explode($delimiter, $rowText) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $pair = explode('=', $part, 2);
                if (count($pair) === 2 && $pair[0] !== '') {
                    $field[$pair[0]] = $pair[1];
                }
            }

            if (!empty($field)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}

if (!function_exists('red_public_form_is_input_type')) {
    function red_public_form_is_input_type($type)
    {
        return in_array(
            (string) $type,
            ['textfield', 'textarea', 'checkbox', 'radio', 'select', 'hidden', 'password'],
            true
        );
    }
}

if (!function_exists('red_public_form_label')) {
    function red_public_form_label($fieldName)
    {
        return ucwords(preg_replace('/_/', ' ', red_public_form_clean($fieldName)));
    }
}

if (!function_exists('red_public_form_email_row')) {
    function red_public_form_email_row($fieldName, $value, $required)
    {
        $label = red_public_form_html(red_public_form_label($fieldName));
        $suffix = $required ? '*' : '';
        return '<tr><th>' . $label . $suffix . '</th><td>' . nl2br(red_public_form_html($value)) . '</td></tr>';
    }
}

if (!function_exists('red_public_form_replace_response_token')) {
    function red_public_form_replace_response_token($text, $fieldName, $value)
    {
        return str_replace('$' . $fieldName, red_public_form_html($value), (string) $text);
    }
}

if (!function_exists('red_public_form_replace_mail_token')) {
    function red_public_form_replace_mail_token($text, $fieldName, $value)
    {
        return str_replace('$' . $fieldName, red_public_form_header_value($value), (string) $text);
    }
}

if (!function_exists('red_public_form_collect_submission_values')) {
    function red_public_form_collect_submission_values($fields, $post)
    {
        $values = [];
        $seen = [];
        foreach ($fields as $field) {
            $fieldName = red_public_form_identifier($field['name'] ?? '');
            if ($fieldName === null || isset($seen[$fieldName]) || $fieldName === 'MySpamTrap') {
                continue;
            }

            $fieldType = $field['type'] ?? '';
            if (!red_public_form_is_input_type($fieldType)) {
                continue;
            }

            if (in_array(strtolower($fieldName), ['recordid', 'updatedate'], true)) {
                continue;
            }

            $seen[$fieldName] = true;
            $values[$fieldName] = red_public_form_post_value($post, $fieldName);
        }

        return $values;
    }
}

if (!function_exists('red_public_form_bind_values')) {
    function red_public_form_bind_values($stmt, $types, $values)
    {
        $refs = [];
        $refs[] = $types;
        foreach ($values as $index => $value) {
            $refs[] = &$values[$index];
        }

        return call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

if (!function_exists('red_public_form_insert_submission')) {
    function red_public_form_insert_submission($connection, $tableName, $values, $recordId = null)
    {
        $tableName = red_public_form_identifier($tableName);
        if ($tableName === null || empty($values)) {
            return false;
        }

        $columns = [];
        $placeholders = [];
        $types = '';
        $params = [];

        if ($recordId !== null) {
            $columns[] = '`RecordID`';
            $placeholders[] = '?';
            $types .= 'i';
            $params[] = (int) $recordId;
        }

        foreach ($values as $fieldName => $value) {
            $fieldName = red_public_form_identifier($fieldName);
            if ($fieldName === null) {
                continue;
            }

            $columns[] = '`' . $fieldName . '`';
            $placeholders[] = '?';
            $types .= 's';
            $params[] = red_public_form_clean($value);
        }

        if (empty($columns)) {
            return false;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO `' . $tableName . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            if (!$stmt) {
                return false;
            }

            if (!red_public_form_bind_values($stmt, $types, $params)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        } catch (mysqli_sql_exception $e) {
            error_log('Public form insert failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_public_form_store_user')) {
    function red_public_form_store_user($connection, $tableName, $email, $password)
    {
        $tableName = red_public_form_identifier($tableName);
        if ($tableName === null) {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RecordID, full_name FROM `' . $tableName . '` WHERE email=? AND password=? LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }

            mysqli_stmt_bind_param($stmt, 'ss', $email, $password);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }

            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? $result->fetch_assoc() : null;
            mysqli_stmt_close($stmt);
            return $row ?: null;
        } catch (mysqli_sql_exception $e) {
            error_log('Public store login lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_public_form_email_exists')) {
    function red_public_form_email_exists($connection, $tableName, $email)
    {
        $tableName = red_public_form_identifier($tableName);
        if ($tableName === null) {
            return false;
        }

        try {
            $stmt = mysqli_prepare($connection, 'SELECT email FROM `' . $tableName . '` WHERE email=? LIMIT 1');
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param($stmt, 's', $email);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }

            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
            return $exists;
        } catch (mysqli_sql_exception $e) {
            error_log('Public form email lookup failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_public_form_add_recipients')) {
    function red_public_form_add_recipients($mail, $method, $recipients)
    {
        foreach (explode(';', (string) $recipients) as $recipient) {
            $recipient = trim($recipient);
            if ($recipient === '') {
                continue;
            }

            $emailName = explode(',', $recipient, 2);
            $email = trim($emailName[0] ?? '');
            $name = trim($emailName[1] ?? '');
            if ($email === '') {
                continue;
            }

            $mail->$method($email, $name);
        }
    }
}

?>
