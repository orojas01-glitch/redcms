<?php
/**
 * Internal core-only anonymous-subject and CSRF foundation for a future
 * public add-on mutation dispatcher.
 *
 * This helper has no HTTP endpoint and never reads $_COOKIE, starts a session,
 * emits a header, loads package PHP, validates a browser form, invokes a
 * package handler, changes lifecycle state, or reads/writes package data.
 * Core stores only SHA-256 hashes of random subject and CSRF values. A later
 * core-owned dispatcher must be the only caller that serializes a cookie or
 * embeds a CSRF token in a response.
 */

require_once __DIR__ . '/addon_public_mutation_preflight_helpers.php';

if (!function_exists('red_addon_public_mutation_subject_cookie_name')) {
    function red_addon_public_mutation_subject_cookie_name()
    {
        return 'redcms_public_mutation_subject';
    }
}

if (!function_exists('red_addon_public_mutation_subject_lifetime_seconds')) {
    function red_addon_public_mutation_subject_lifetime_seconds()
    {
        return 1800;
    }
}

if (!function_exists('red_addon_public_mutation_csrf_lifetime_seconds')) {
    function red_addon_public_mutation_csrf_lifetime_seconds()
    {
        return 600;
    }
}

if (!function_exists('red_addon_public_mutation_valid_opaque_token')) {
    function red_addon_public_mutation_valid_opaque_token($value)
    {
        return is_string($value)
            && preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }
}

if (!function_exists('red_addon_public_mutation_opaque_token_sha256')) {
    function red_addon_public_mutation_opaque_token_sha256($value)
    {
        if (!red_addon_public_mutation_valid_opaque_token($value)) {
            return '';
        }
        return hash('sha256', $value);
    }
}

if (!function_exists('red_addon_public_mutation_subject_database_name')) {
    function red_addon_public_mutation_subject_database_name($connection)
    {
        if (!$connection) {
            return '';
        }
        try {
            $query = mysqli_query($connection, 'SELECT DATABASE() AS Name');
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            $database = is_string($row['Name'] ?? null) ? $row['Name'] : '';
            return preg_match('/\A[A-Za-z0-9_]+\z/', $database) === 1
                ? $database
                : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }
}

if (!function_exists('red_addon_public_mutation_subject_storage_available')) {
    function red_addon_public_mutation_subject_storage_available($connection)
    {
        if (!$connection) {
            return false;
        }
        try {
            $query = mysqli_query(
                $connection,
                "SELECT CONCAT_WS(
                    ':',
                    (SELECT COUNT(*)
                     FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME IN (
                         'RED_Addon_Public_Mutation_Subjects',
                         'RED_Addon_Public_Mutation_CSRF_Tokens'
                       )),
                    (SELECT COUNT(*)=4
                       AND SUM(COLUMN_NAME='RecordID'
                         AND DATA_TYPE='int'
                         AND COLUMN_TYPE LIKE 'int% unsigned'
                         AND IS_NULLABLE='NO'
                         AND EXTRA='auto_increment')=1
                       AND SUM(COLUMN_NAME='SubjectTokenSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='CreatedAt'
                         AND DATA_TYPE='datetime'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ExpiresAt'
                         AND DATA_TYPE='datetime'
                         AND IS_NULLABLE='NO')=1
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Public_Mutation_Subjects'),
                    (SELECT COUNT(*)=6
                       AND SUM(COLUMN_NAME='RecordID'
                         AND DATA_TYPE='int'
                         AND COLUMN_TYPE LIKE 'int% unsigned'
                         AND IS_NULLABLE='NO'
                         AND EXTRA='auto_increment')=1
                       AND SUM(COLUMN_NAME='SubjectRecordID'
                         AND DATA_TYPE='int'
                         AND COLUMN_TYPE LIKE 'int% unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ScopeSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='TokenSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='CreatedAt'
                         AND DATA_TYPE='datetime'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ExpiresAt'
                         AND DATA_TYPE='datetime'
                         AND IS_NULLABLE='NO')=1
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_Subjects'
                        AND INDEX_NAME='PRIMARY')='RecordID'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_Subjects'
                        AND INDEX_NAME='uq_red_addon_public_mutation_subject_token')
                       ='SubjectTokenSHA256'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_Subjects'
                        AND INDEX_NAME='idx_red_addon_public_mutation_subject_expiry')
                       ='ExpiresAt,RecordID'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'
                        AND INDEX_NAME='PRIMARY')='RecordID'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'
                        AND INDEX_NAME='uq_red_addon_public_mutation_csrf_token')
                       ='SubjectRecordID,ScopeSHA256,TokenSHA256'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'
                        AND INDEX_NAME='idx_red_addon_public_mutation_csrf_expiry')
                       ='ExpiresAt,RecordID'),
                    (SELECT COUNT(*)=1
                       AND SUM(CONSTRAINT_NAME=
                             'fk_red_addon_public_mutation_csrf_subject'
                         AND TABLE_NAME='RED_Addon_Public_Mutation_CSRF_Tokens'
                         AND REFERENCED_TABLE_NAME=
                             'RED_Addon_Public_Mutation_Subjects'
                         AND DELETE_RULE='CASCADE'
                         AND UPDATE_RULE='RESTRICT')=1
                     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA=DATABASE()
                       AND CONSTRAINT_NAME=
                         'fk_red_addon_public_mutation_csrf_subject')
                 ) AS StorageState"
            );
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            return (string) ($row['StorageState'] ?? '')
                === '2:1:1:1:1:1:1:1:1:1';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_options')) {
    function red_addon_public_mutation_subject_cookie_options()
    {
        return [
            'name' => red_addon_public_mutation_subject_cookie_name(),
            'path' => '/',
            'secure' => true,
            'httpOnly' => true,
            'sameSite' => 'Strict',
            'maxAgeSeconds' => red_addon_public_mutation_subject_lifetime_seconds(),
        ];
    }
}

if (!function_exists('red_addon_public_mutation_subject_issue_result')) {
    function red_addon_public_mutation_subject_issue_result($reason)
    {
        $cookie = red_addon_public_mutation_subject_cookie_options();
        $cookie['value'] = '';
        return [
            'valid' => false,
            'issued' => false,
            'subjectRecordId' => 0,
            'cookie' => $cookie,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_subject_resolve_result')) {
    function red_addon_public_mutation_subject_resolve_result($reason)
    {
        return [
            'valid' => false,
            'subjectRecordId' => 0,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_csrf_issue_result')) {
    function red_addon_public_mutation_csrf_issue_result($reason)
    {
        return [
            'valid' => false,
            'issued' => false,
            'subjectRecordId' => 0,
            'scopeSha256' => '',
            'token' => '',
            'maxAgeSeconds' => 0,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_csrf_verify_result')) {
    function red_addon_public_mutation_csrf_verify_result($reason)
    {
        return [
            'valid' => false,
            'subjectRecordId' => 0,
            'scopeSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_subject_record_id')) {
    function red_addon_public_mutation_subject_record_id($subject)
    {
        if (!is_array($subject) || empty($subject['valid'])) {
            return 0;
        }
        $recordId = $subject['subjectRecordId'] ?? null;
        return is_int($recordId) && $recordId > 0 ? $recordId : 0;
    }
}

if (!function_exists('red_addon_public_mutation_subject_is_active')) {
    function red_addon_public_mutation_subject_is_active($connection, $recordId)
    {
        if (!is_int($recordId)
            || $recordId < 1
            || !red_addon_public_mutation_subject_storage_available($connection)
        ) {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RecordID
                 FROM RED_Addon_Public_Mutation_Subjects
                 WHERE RecordID=?
                   AND ExpiresAt > UTC_TIMESTAMP()
                 LIMIT 1'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param($statement, 'i', $recordId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return false;
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            return is_array($row)
                && (int) ($row['RecordID'] ?? 0) === $recordId;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_subject_cleanup')) {
    function red_addon_public_mutation_subject_cleanup($connection)
    {
        if (!red_addon_public_mutation_subject_storage_available($connection)) {
            return false;
        }
        try {
            $csrfDeleted = mysqli_query(
                $connection,
                'DELETE FROM RED_Addon_Public_Mutation_CSRF_Tokens
                 WHERE ExpiresAt <= UTC_TIMESTAMP()
                 ORDER BY ExpiresAt ASC, RecordID ASC
                 LIMIT 100'
            );
            if (!$csrfDeleted) {
                return false;
            }
            $subjectsDeleted = mysqli_query(
                $connection,
                'DELETE IGNORE FROM RED_Addon_Public_Mutation_Subjects
                 WHERE ExpiresAt <= UTC_TIMESTAMP()
                 ORDER BY ExpiresAt ASC, RecordID ASC
                 LIMIT 100'
            );
            return $subjectsDeleted === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_subject_issue')) {
    function red_addon_public_mutation_subject_issue($connection)
    {
        $result = red_addon_public_mutation_subject_issue_result(
            'subject_unavailable'
        );
        if (!red_addon_public_mutation_subject_storage_available($connection)) {
            $result['reason'] = 'subject_storage_unavailable';
            return $result;
        }
        if (!red_addon_public_mutation_subject_cleanup($connection)) {
            $result['reason'] = 'subject_storage_unavailable';
            return $result;
        }
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (Throwable $throwable) {
                $result['reason'] = 'subject_random_unavailable';
                return $result;
            }
            $tokenSha256 = red_addon_public_mutation_opaque_token_sha256($token);
            if (!red_addon_valid_sha256($tokenSha256)) {
                $result['reason'] = 'subject_random_unavailable';
                return $result;
            }
            try {
                $statement = mysqli_prepare(
                    $connection,
                    'INSERT INTO RED_Addon_Public_Mutation_Subjects (
                        SubjectTokenSHA256, CreatedAt, ExpiresAt
                     ) VALUES (?, UTC_TIMESTAMP(),
                        DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1800 SECOND))'
                );
                if (!$statement) {
                    $result['reason'] = 'subject_storage_unavailable';
                    return $result;
                }
                mysqli_stmt_bind_param($statement, 's', $tokenSha256);
                $executed = mysqli_stmt_execute($statement);
                $errorCode = mysqli_stmt_errno($statement);
                $recordId = $executed ? mysqli_insert_id($connection) : 0;
                mysqli_stmt_close($statement);
            } catch (Throwable $throwable) {
                $result['reason'] = 'subject_storage_unavailable';
                return $result;
            }
            if ($executed && is_int($recordId) && $recordId > 0) {
                $result['valid'] = true;
                $result['issued'] = true;
                $result['subjectRecordId'] = $recordId;
                $result['cookie']['value'] = $token;
                $result['reason'] = 'subject_issued';
                return $result;
            }
            if ($errorCode !== 1062) {
                $result['reason'] = 'subject_storage_unavailable';
                return $result;
            }
        }
        $result['reason'] = 'subject_random_collision';
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_subject_resolve')) {
    function red_addon_public_mutation_subject_resolve($connection, $cookieValue)
    {
        $result = red_addon_public_mutation_subject_resolve_result(
            'subject_invalid'
        );
        if (!red_addon_public_mutation_subject_storage_available($connection)) {
            $result['reason'] = 'subject_storage_unavailable';
            return $result;
        }
        $tokenSha256 = red_addon_public_mutation_opaque_token_sha256($cookieValue);
        if (!red_addon_valid_sha256($tokenSha256)) {
            return $result;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RecordID
                 FROM RED_Addon_Public_Mutation_Subjects
                 WHERE BINARY SubjectTokenSHA256=BINARY ?
                   AND ExpiresAt > UTC_TIMESTAMP()
                 LIMIT 1'
            );
            if (!$statement) {
                $result['reason'] = 'subject_storage_unavailable';
                return $result;
            }
            mysqli_stmt_bind_param($statement, 's', $tokenSha256);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $result['reason'] = 'subject_storage_unavailable';
                return $result;
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $result['reason'] = 'subject_storage_unavailable';
            return $result;
        }
        $recordId = is_array($row) ? (int) ($row['RecordID'] ?? 0) : 0;
        if ($recordId < 1) {
            return $result;
        }
        $result['valid'] = true;
        $result['subjectRecordId'] = $recordId;
        $result['reason'] = 'subject_resolved';
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_csrf_scope_sha256')) {
    function red_addon_public_mutation_csrf_scope_sha256(
        $connection,
        array $declarationPlan
    ) {
        if (!red_addon_public_mutation_declaration_preflight_is_valid(
            $declarationPlan
        )) {
            return '';
        }
        $database = red_addon_public_mutation_subject_database_name($connection);
        if ($database === '') {
            return '';
        }
        $encoded = json_encode(
            [
                'schema' => 1,
                'database' => $database,
                'packageId' => $declarationPlan['packageId'],
                'route' => $declarationPlan['route'],
                'mutation' => $declarationPlan['mutation'],
                'contractSha256' => $declarationPlan['contractSha256'],
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_public_mutation_csrf_issue')) {
    function red_addon_public_mutation_csrf_issue(
        $connection,
        array $subject,
        array $declarationPlan
    ) {
        $result = red_addon_public_mutation_csrf_issue_result('csrf_unavailable');
        if (!red_addon_public_mutation_subject_storage_available($connection)) {
            $result['reason'] = 'csrf_storage_unavailable';
            return $result;
        }
        $subjectRecordId = red_addon_public_mutation_subject_record_id($subject);
        if ($subjectRecordId < 1
            || !red_addon_public_mutation_subject_is_active(
                $connection,
                $subjectRecordId
            )) {
            $result['reason'] = 'csrf_subject_invalid';
            return $result;
        }
        $scopeSha256 = red_addon_public_mutation_csrf_scope_sha256(
            $connection,
            $declarationPlan
        );
        if (!red_addon_valid_sha256($scopeSha256)) {
            $result['reason'] = 'csrf_scope_invalid';
            return $result;
        }
        if (!red_addon_public_mutation_subject_cleanup($connection)) {
            $result['reason'] = 'csrf_storage_unavailable';
            return $result;
        }
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (Throwable $throwable) {
                $result['reason'] = 'csrf_random_unavailable';
                return $result;
            }
            $tokenSha256 = red_addon_public_mutation_opaque_token_sha256($token);
            if (!red_addon_valid_sha256($tokenSha256)) {
                $result['reason'] = 'csrf_random_unavailable';
                return $result;
            }
            try {
                $statement = mysqli_prepare(
                    $connection,
                    'INSERT INTO RED_Addon_Public_Mutation_CSRF_Tokens (
                        SubjectRecordID, ScopeSHA256, TokenSHA256,
                        CreatedAt, ExpiresAt
                     ) VALUES (?, ?, ?, UTC_TIMESTAMP(),
                        DATE_ADD(UTC_TIMESTAMP(), INTERVAL 600 SECOND))'
                );
                if (!$statement) {
                    $result['reason'] = 'csrf_storage_unavailable';
                    return $result;
                }
                mysqli_stmt_bind_param(
                    $statement,
                    'iss',
                    $subjectRecordId,
                    $scopeSha256,
                    $tokenSha256
                );
                $executed = mysqli_stmt_execute($statement);
                $errorCode = mysqli_stmt_errno($statement);
                mysqli_stmt_close($statement);
            } catch (Throwable $throwable) {
                $result['reason'] = 'csrf_storage_unavailable';
                return $result;
            }
            if ($executed) {
                $result['valid'] = true;
                $result['issued'] = true;
                $result['subjectRecordId'] = $subjectRecordId;
                $result['scopeSha256'] = $scopeSha256;
                $result['token'] = $token;
                $result['maxAgeSeconds'] =
                    red_addon_public_mutation_csrf_lifetime_seconds();
                $result['reason'] = 'csrf_issued';
                return $result;
            }
            if ($errorCode !== 1062) {
                $result['reason'] = 'csrf_storage_unavailable';
                return $result;
            }
        }
        $result['reason'] = 'csrf_random_collision';
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_csrf_verify')) {
    function red_addon_public_mutation_csrf_verify(
        $connection,
        array $subject,
        array $declarationPlan,
        $token
    ) {
        $result = red_addon_public_mutation_csrf_verify_result('csrf_invalid');
        if (!red_addon_public_mutation_subject_storage_available($connection)) {
            $result['reason'] = 'csrf_storage_unavailable';
            return $result;
        }
        $subjectRecordId = red_addon_public_mutation_subject_record_id($subject);
        if ($subjectRecordId < 1) {
            $result['reason'] = 'csrf_subject_invalid';
            return $result;
        }
        $scopeSha256 = red_addon_public_mutation_csrf_scope_sha256(
            $connection,
            $declarationPlan
        );
        $tokenSha256 = red_addon_public_mutation_opaque_token_sha256($token);
        if (!red_addon_valid_sha256($scopeSha256)
            || !red_addon_valid_sha256($tokenSha256)) {
            return $result;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT t.SubjectRecordID
                 FROM RED_Addon_Public_Mutation_CSRF_Tokens t
                 INNER JOIN RED_Addon_Public_Mutation_Subjects s
                   ON s.RecordID=t.SubjectRecordID
                 WHERE t.SubjectRecordID=?
                   AND BINARY t.ScopeSHA256=BINARY ?
                   AND BINARY t.TokenSHA256=BINARY ?
                   AND t.ExpiresAt > UTC_TIMESTAMP()
                   AND s.ExpiresAt > UTC_TIMESTAMP()
                 LIMIT 1'
            );
            if (!$statement) {
                $result['reason'] = 'csrf_storage_unavailable';
                return $result;
            }
            mysqli_stmt_bind_param(
                $statement,
                'iss',
                $subjectRecordId,
                $scopeSha256,
                $tokenSha256
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $result['reason'] = 'csrf_storage_unavailable';
                return $result;
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $result['reason'] = 'csrf_storage_unavailable';
            return $result;
        }
        if (!is_array($row)
            || (int) ($row['SubjectRecordID'] ?? 0) !== $subjectRecordId) {
            return $result;
        }
        $result['valid'] = true;
        $result['subjectRecordId'] = $subjectRecordId;
        $result['scopeSha256'] = $scopeSha256;
        $result['reason'] = 'csrf_verified';
        return $result;
    }
}

?>
