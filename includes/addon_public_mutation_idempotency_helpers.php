<?php
/**
 * Internal core-only opaque idempotency-key foundation for a future public
 * add-on mutation dispatcher.
 *
 * This helper has no HTTP endpoint and never reads request, cookie, or session
 * globals; emits a header; loads package PHP; validates a browser form;
 * invokes a package handler; changes lifecycle state; or reads/writes package
 * data. It stores only a short-lived opaque-subject relation, a hashed
 * declaration/database scope, and a SHA-256 digest of a core-issued key. It
 * does not mark a key consumed: that atomic replay decision belongs to the
 * later core transaction runner.
 */

require_once __DIR__ . '/addon_public_mutation_rate_limit_helpers.php';

if (!function_exists('red_addon_public_mutation_idempotency_policy')) {
    function red_addon_public_mutation_idempotency_policy()
    {
        return [
            'lifetimeSeconds' => 600,
            'cleanupLimit' => 100,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_idempotency_policy_valid')) {
    function red_addon_public_mutation_idempotency_policy_valid(array $policy)
    {
        return ($policy['lifetimeSeconds'] ?? null) === 600
            && ($policy['cleanupLimit'] ?? null) === 100
            && count($policy) === 2;
    }
}

if (!function_exists('red_addon_public_mutation_idempotency_storage_available')) {
    function red_addon_public_mutation_idempotency_storage_available($connection)
    {
        if (!red_addon_public_mutation_rate_limit_storage_available($connection)) {
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
                         'RED_Addon_Public_Mutation_CSRF_Tokens',
                         'RED_Addon_Public_Mutation_Rate_Limits',
                         'RED_Addon_Public_Mutation_Idempotency_Keys'
                       )
                       AND ENGINE='InnoDB'),
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
                       AND SUM(COLUMN_NAME='KeySHA256'
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
                       AND TABLE_NAME=
                         'RED_Addon_Public_Mutation_Idempotency_Keys'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME=
                          'RED_Addon_Public_Mutation_Idempotency_Keys'
                        AND INDEX_NAME='PRIMARY')='RecordID'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME=
                          'RED_Addon_Public_Mutation_Idempotency_Keys'
                        AND INDEX_NAME=
                          'uq_red_addon_public_mutation_idempotency_key')
                       ='SubjectRecordID,ScopeSHA256,KeySHA256'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME=
                          'RED_Addon_Public_Mutation_Idempotency_Keys'
                        AND INDEX_NAME=
                          'idx_red_addon_public_mutation_idempotency_expiry')
                       ='ExpiresAt,RecordID'),
                    (SELECT COUNT(*)=1
                       AND SUM(CONSTRAINT_NAME=
                             'fk_red_addon_public_mutation_idempotency_subject'
                         AND TABLE_NAME=
                             'RED_Addon_Public_Mutation_Idempotency_Keys'
                         AND REFERENCED_TABLE_NAME=
                             'RED_Addon_Public_Mutation_Subjects'
                         AND DELETE_RULE='CASCADE'
                         AND UPDATE_RULE='RESTRICT')=1
                     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA=DATABASE()
                       AND CONSTRAINT_NAME=
                         'fk_red_addon_public_mutation_idempotency_subject')
                 ) AS StorageState"
            );
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            return (string) ($row['StorageState'] ?? '')
                === '4:1:1:1:1:1';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_idempotency_issue_result')) {
    function red_addon_public_mutation_idempotency_issue_result($reason)
    {
        return [
            'valid' => false,
            'issued' => false,
            'idempotencyRecordId' => 0,
            'subjectRecordId' => 0,
            'scopeSha256' => '',
            'key' => '',
            'maxAgeSeconds' => 0,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_idempotency_resolve_result')) {
    function red_addon_public_mutation_idempotency_resolve_result($reason)
    {
        return [
            'valid' => false,
            'idempotencyRecordId' => 0,
            'subjectRecordId' => 0,
            'scopeSha256' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_idempotency_scope_sha256')) {
    function red_addon_public_mutation_idempotency_scope_sha256(
        $connection,
        array $declarationPlan
    ) {
        if (!red_addon_public_mutation_declaration_preflight_is_valid(
            $declarationPlan
        )) {
            return '';
        }
        $database = red_addon_public_mutation_subject_database_name($connection);
        $policy = red_addon_public_mutation_idempotency_policy();
        if ($database === ''
            || !red_addon_public_mutation_idempotency_policy_valid($policy)
        ) {
            return '';
        }
        $encoded = json_encode(
            [
                'schema' => 1,
                'purpose' => 'public-mutation-idempotency',
                'database' => $database,
                'packageId' => $declarationPlan['packageId'],
                'route' => $declarationPlan['route'],
                'mutation' => $declarationPlan['mutation'],
                'contractSha256' => $declarationPlan['contractSha256'],
                'lifetimeSeconds' => $policy['lifetimeSeconds'],
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_public_mutation_idempotency_transaction_active')) {
    function red_addon_public_mutation_idempotency_transaction_active($connection)
    {
        try {
            if (!mysqli_query(
                $connection,
                'SAVEPOINT redcms_public_mutation_idempotency_guard'
            )) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_public_mutation_idempotency_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_idempotency_cleanup')) {
    function red_addon_public_mutation_idempotency_cleanup($connection)
    {
        $policy = red_addon_public_mutation_idempotency_policy();
        if (!red_addon_public_mutation_idempotency_policy_valid($policy)
            || !red_addon_public_mutation_idempotency_storage_available(
                $connection
            )
        ) {
            return false;
        }
        try {
            return mysqli_query(
                $connection,
                'DELETE FROM RED_Addon_Public_Mutation_Idempotency_Keys
                 WHERE ExpiresAt <= UTC_TIMESTAMP()
                 ORDER BY ExpiresAt ASC, RecordID ASC
                 LIMIT ' . (int) $policy['cleanupLimit']
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_idempotency_issue')) {
    function red_addon_public_mutation_idempotency_issue(
        $connection,
        array $subject,
        array $declarationPlan
    ) {
        $result = red_addon_public_mutation_idempotency_issue_result(
            'idempotency_unavailable'
        );
        $policy = red_addon_public_mutation_idempotency_policy();
        if (!red_addon_public_mutation_idempotency_policy_valid($policy)
            || !red_addon_public_mutation_idempotency_storage_available(
                $connection
            )
        ) {
            $result['reason'] = 'idempotency_storage_unavailable';
            return $result;
        }
        $subjectRecordId = red_addon_public_mutation_subject_record_id($subject);
        if ($subjectRecordId < 1
            || !red_addon_public_mutation_subject_is_active(
                $connection,
                $subjectRecordId
            )
        ) {
            $result['reason'] = 'idempotency_subject_invalid';
            return $result;
        }
        $scopeSha256 = red_addon_public_mutation_idempotency_scope_sha256(
            $connection,
            $declarationPlan
        );
        if (!red_addon_valid_sha256($scopeSha256)) {
            $result['reason'] = 'idempotency_scope_invalid';
            return $result;
        }
        $result['subjectRecordId'] = $subjectRecordId;
        $result['scopeSha256'] = $scopeSha256;
        if (red_addon_public_mutation_idempotency_transaction_active(
            $connection
        )) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        if (!red_addon_public_mutation_idempotency_cleanup($connection)) {
            $result['reason'] = 'idempotency_storage_unavailable';
            return $result;
        }
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $key = bin2hex(random_bytes(32));
            } catch (Throwable $throwable) {
                $result['reason'] = 'idempotency_random_unavailable';
                return $result;
            }
            $keySha256 = red_addon_public_mutation_opaque_token_sha256($key);
            if (!red_addon_valid_sha256($keySha256)) {
                $result['reason'] = 'idempotency_random_unavailable';
                return $result;
            }
            try {
                $statement = mysqli_prepare(
                    $connection,
                    'INSERT INTO RED_Addon_Public_Mutation_Idempotency_Keys (
                        SubjectRecordID, ScopeSHA256, KeySHA256,
                        CreatedAt, ExpiresAt
                     ) VALUES (?, ?, ?, UTC_TIMESTAMP(),
                        DATE_ADD(UTC_TIMESTAMP(), INTERVAL 600 SECOND))'
                );
                if (!$statement) {
                    $result['reason'] = 'idempotency_storage_unavailable';
                    return $result;
                }
                mysqli_stmt_bind_param(
                    $statement,
                    'iss',
                    $subjectRecordId,
                    $scopeSha256,
                    $keySha256
                );
                $executed = mysqli_stmt_execute($statement);
                $errorCode = mysqli_stmt_errno($statement);
                $recordId = $executed ? (int) mysqli_insert_id($connection) : 0;
                mysqli_stmt_close($statement);
            } catch (Throwable $throwable) {
                $result['reason'] = 'idempotency_storage_unavailable';
                return $result;
            }
            if ($executed && $recordId > 0) {
                $result['valid'] = true;
                $result['issued'] = true;
                $result['idempotencyRecordId'] = $recordId;
                $result['key'] = $key;
                $result['maxAgeSeconds'] = (int) $policy['lifetimeSeconds'];
                $result['reason'] = 'idempotency_issued';
                return $result;
            }
            if ($errorCode !== 1062) {
                $result['reason'] = 'idempotency_storage_unavailable';
                return $result;
            }
        }
        $result['reason'] = 'idempotency_random_collision';
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_idempotency_resolve')) {
    function red_addon_public_mutation_idempotency_resolve(
        $connection,
        array $subject,
        array $declarationPlan,
        $key
    ) {
        $result = red_addon_public_mutation_idempotency_resolve_result(
            'idempotency_invalid'
        );
        if (!red_addon_public_mutation_idempotency_storage_available(
            $connection
        )) {
            $result['reason'] = 'idempotency_storage_unavailable';
            return $result;
        }
        $subjectRecordId = red_addon_public_mutation_subject_record_id($subject);
        if ($subjectRecordId < 1) {
            $result['reason'] = 'idempotency_subject_invalid';
            return $result;
        }
        $scopeSha256 = red_addon_public_mutation_idempotency_scope_sha256(
            $connection,
            $declarationPlan
        );
        $keySha256 = red_addon_public_mutation_opaque_token_sha256($key);
        if (!red_addon_valid_sha256($scopeSha256)
            || !red_addon_valid_sha256($keySha256)
        ) {
            return $result;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT k.RecordID, k.SubjectRecordID
                 FROM RED_Addon_Public_Mutation_Idempotency_Keys k
                 INNER JOIN RED_Addon_Public_Mutation_Subjects s
                   ON s.RecordID=k.SubjectRecordID
                 WHERE k.SubjectRecordID=?
                   AND BINARY k.ScopeSHA256=BINARY ?
                   AND BINARY k.KeySHA256=BINARY ?
                   AND k.ExpiresAt > UTC_TIMESTAMP()
                   AND s.ExpiresAt > UTC_TIMESTAMP()
                 LIMIT 1'
            );
            if (!$statement) {
                $result['reason'] = 'idempotency_storage_unavailable';
                return $result;
            }
            mysqli_stmt_bind_param(
                $statement,
                'iss',
                $subjectRecordId,
                $scopeSha256,
                $keySha256
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $result['reason'] = 'idempotency_storage_unavailable';
                return $result;
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $result['reason'] = 'idempotency_storage_unavailable';
            return $result;
        }
        $recordId = is_array($row) ? (int) ($row['RecordID'] ?? 0) : 0;
        if ($recordId < 1
            || (int) ($row['SubjectRecordID'] ?? 0) !== $subjectRecordId
        ) {
            return $result;
        }
        $result['valid'] = true;
        $result['idempotencyRecordId'] = $recordId;
        $result['subjectRecordId'] = $subjectRecordId;
        $result['scopeSha256'] = $scopeSha256;
        $result['reason'] = 'idempotency_resolved';
        return $result;
    }
}

?>
