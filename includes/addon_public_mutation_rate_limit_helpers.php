<?php
/**
 * Internal core-only fixed-window rate-limit foundation for a future public
 * add-on mutation dispatcher.
 *
 * This helper has no HTTP endpoint and never reads request, cookie, or session
 * globals; emits a header; loads package PHP; validates a browser form;
 * invokes a package handler; changes lifecycle state; or reads/writes package
 * data. It records only a short-lived opaque-subject relation, a hashed
 * declaration/database scope, a fixed window, and a bounded request count.
 */

require_once __DIR__ . '/addon_public_mutation_subject_helpers.php';

if (!function_exists('red_addon_public_mutation_rate_limit_policy')) {
    function red_addon_public_mutation_rate_limit_policy()
    {
        return [
            'windowSeconds' => 60,
            'maxRequests' => 12,
            'cleanupLimit' => 100,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_policy_valid')) {
    function red_addon_public_mutation_rate_limit_policy_valid(array $policy)
    {
        return ($policy['windowSeconds'] ?? null) === 60
            && ($policy['maxRequests'] ?? null) === 12
            && ($policy['cleanupLimit'] ?? null) === 100
            && count($policy) === 3;
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_storage_available')) {
    function red_addon_public_mutation_rate_limit_storage_available($connection)
    {
        if (!red_addon_public_mutation_subject_storage_available($connection)) {
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
                         'RED_Addon_Public_Mutation_Rate_Limits'
                       )
                       AND ENGINE='InnoDB'),
                    (SELECT COUNT(*)=6
                       AND SUM(COLUMN_NAME='RecordID'
                         AND COLUMN_TYPE='int unsigned'
                         AND IS_NULLABLE='NO'
                         AND EXTRA='auto_increment')=1
                       AND SUM(COLUMN_NAME='SubjectRecordID'
                         AND COLUMN_TYPE='int unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ScopeSHA256'
                         AND COLUMN_TYPE='char(64)'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='WindowStartedAt'
                         AND DATA_TYPE='datetime'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='RequestCount'
                         AND COLUMN_TYPE='smallint unsigned'
                         AND IS_NULLABLE='NO')=1
                       AND SUM(COLUMN_NAME='ExpiresAt'
                         AND DATA_TYPE='datetime'
                         AND IS_NULLABLE='NO')=1
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Public_Mutation_Rate_Limits'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_Rate_Limits'
                        AND INDEX_NAME='PRIMARY')='RecordID'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_Rate_Limits'
                        AND INDEX_NAME='uq_red_addon_public_mutation_rate_window')
                       ='SubjectRecordID,ScopeSHA256,WindowStartedAt'),
                    ((SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX
                       SEPARATOR ',')
                      FROM INFORMATION_SCHEMA.STATISTICS
                      WHERE TABLE_SCHEMA=DATABASE()
                        AND TABLE_NAME='RED_Addon_Public_Mutation_Rate_Limits'
                        AND INDEX_NAME='idx_red_addon_public_mutation_rate_expiry')
                       ='ExpiresAt,RecordID'),
                    (SELECT COUNT(*)=1
                       AND SUM(CONSTRAINT_NAME=
                             'fk_red_addon_public_mutation_rate_subject'
                         AND TABLE_NAME='RED_Addon_Public_Mutation_Rate_Limits'
                         AND REFERENCED_TABLE_NAME=
                             'RED_Addon_Public_Mutation_Subjects'
                         AND DELETE_RULE='CASCADE'
                         AND UPDATE_RULE='RESTRICT')=1
                     FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA=DATABASE()
                       AND CONSTRAINT_NAME=
                         'fk_red_addon_public_mutation_rate_subject')
                 ) AS StorageState"
            );
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            return (string) ($row['StorageState'] ?? '')
                === '3:1:1:1:1:1';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_result')) {
    function red_addon_public_mutation_rate_limit_result($reason)
    {
        return [
            'valid' => false,
            'allowed' => false,
            'subjectRecordId' => 0,
            'scopeSha256' => '',
            'windowSeconds' => 0,
            'maxRequests' => 0,
            'retryAfterSeconds' => 0,
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_scope_sha256')) {
    function red_addon_public_mutation_rate_limit_scope_sha256(
        $connection,
        array $declarationPlan
    ) {
        if (!red_addon_public_mutation_declaration_preflight_is_valid(
            $declarationPlan
        )) {
            return '';
        }
        $database = red_addon_public_mutation_subject_database_name($connection);
        $policy = red_addon_public_mutation_rate_limit_policy();
        if ($database === ''
            || !red_addon_public_mutation_rate_limit_policy_valid($policy)
        ) {
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
                'windowSeconds' => $policy['windowSeconds'],
                'maxRequests' => $policy['maxRequests'],
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_transaction_active')) {
    function red_addon_public_mutation_rate_limit_transaction_active($connection)
    {
        try {
            if (!mysqli_query(
                $connection,
                'SAVEPOINT redcms_public_mutation_rate_limit_guard'
            )) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_public_mutation_rate_limit_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_cleanup')) {
    function red_addon_public_mutation_rate_limit_cleanup($connection)
    {
        $policy = red_addon_public_mutation_rate_limit_policy();
        if (!red_addon_public_mutation_rate_limit_policy_valid($policy)
            || !red_addon_public_mutation_rate_limit_storage_available($connection)
        ) {
            return false;
        }
        try {
            return mysqli_query(
                $connection,
                'DELETE FROM RED_Addon_Public_Mutation_Rate_Limits
                 WHERE ExpiresAt <= UTC_TIMESTAMP()
                 ORDER BY ExpiresAt ASC, RecordID ASC
                 LIMIT ' . (int) $policy['cleanupLimit']
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_current_window')) {
    function red_addon_public_mutation_rate_limit_current_window($connection)
    {
        $policy = red_addon_public_mutation_rate_limit_policy();
        if (!red_addon_public_mutation_rate_limit_policy_valid($policy)) {
            return [];
        }
        $windowSeconds = (int) $policy['windowSeconds'];
        $windowStart = 'TIMESTAMPADD(
            SECOND,
            -MOD(TIMESTAMPDIFF(
                SECOND,
                \'1970-01-01 00:00:00\',
                UTC_TIMESTAMP()
            ), ' . $windowSeconds . '),
            UTC_TIMESTAMP()
        )';
        try {
            $query = mysqli_query(
                $connection,
                'SELECT DATE_FORMAT(' . $windowStart .
                    ", '%Y-%m-%d %H:%i:%s') AS WindowStartedAt,
                    DATE_FORMAT(TIMESTAMPADD(SECOND, $windowSeconds,
                        $windowStart), '%Y-%m-%d %H:%i:%s') AS ExpiresAt"
            );
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            $startedAt = is_string($row['WindowStartedAt'] ?? null)
                ? $row['WindowStartedAt']
                : '';
            $expiresAt = is_string($row['ExpiresAt'] ?? null)
                ? $row['ExpiresAt']
                : '';
            if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $startedAt) !== 1
                || preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $expiresAt) !== 1
            ) {
                return [];
            }
            return [
                'windowStartedAt' => $startedAt,
                'expiresAt' => $expiresAt,
            ];
        } catch (Throwable $throwable) {
            return [];
        }
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_locked_subject')) {
    function red_addon_public_mutation_rate_limit_locked_subject(
        $connection,
        $subjectRecordId
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RecordID
                 FROM RED_Addon_Public_Mutation_Subjects
                 WHERE RecordID=?
                   AND ExpiresAt > UTC_TIMESTAMP()
                 LIMIT 1 FOR UPDATE'
            );
            if (!$statement) {
                return 0;
            }
            mysqli_stmt_bind_param($statement, 'i', $subjectRecordId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return 0;
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            return (int) ($row['RecordID'] ?? 0) === $subjectRecordId
                ? $subjectRecordId
                : 0;
        } catch (Throwable $throwable) {
            return 0;
        }
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_locked_row')) {
    function red_addon_public_mutation_rate_limit_locked_row(
        $connection,
        $subjectRecordId,
        $scopeSha256,
        $windowStartedAt
    ) {
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RecordID, RequestCount,
                    GREATEST(0, TIMESTAMPDIFF(
                        SECOND,
                        UTC_TIMESTAMP(),
                        ExpiresAt
                    )) AS RemainingSeconds
                 FROM RED_Addon_Public_Mutation_Rate_Limits
                 WHERE SubjectRecordID=?
                   AND BINARY ScopeSHA256=BINARY ?
                   AND WindowStartedAt=?
                 LIMIT 1 FOR UPDATE'
            );
            if (!$statement) {
                return null;
            }
            mysqli_stmt_bind_param(
                $statement,
                'iss',
                $subjectRecordId,
                $scopeSha256,
                $windowStartedAt
            );
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                return null;
            }
            $query = mysqli_stmt_get_result($statement);
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            if (!is_array($row)) {
                return [];
            }
            $recordId = (int) ($row['RecordID'] ?? 0);
            $requestCount = (int) ($row['RequestCount'] ?? 0);
            $remainingSeconds = (int) ($row['RemainingSeconds'] ?? 0);
            if ($recordId < 1 || $requestCount < 1 || $remainingSeconds < 1) {
                return null;
            }
            return [
                'recordId' => $recordId,
                'requestCount' => $requestCount,
                'remainingSeconds' => $remainingSeconds,
            ];
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_public_mutation_rate_limit_claim')) {
    function red_addon_public_mutation_rate_limit_claim(
        $connection,
        array $subject,
        array $declarationPlan
    ) {
        $result = red_addon_public_mutation_rate_limit_result(
            'rate_limit_unavailable'
        );
        $policy = red_addon_public_mutation_rate_limit_policy();
        if (!red_addon_public_mutation_rate_limit_policy_valid($policy)
            || !red_addon_public_mutation_rate_limit_storage_available($connection)
        ) {
            $result['reason'] = 'rate_limit_storage_unavailable';
            return $result;
        }
        $subjectRecordId = red_addon_public_mutation_subject_record_id($subject);
        if ($subjectRecordId < 1) {
            $result['reason'] = 'rate_limit_subject_invalid';
            return $result;
        }
        $scopeSha256 = red_addon_public_mutation_rate_limit_scope_sha256(
            $connection,
            $declarationPlan
        );
        if (!red_addon_valid_sha256($scopeSha256)) {
            $result['reason'] = 'rate_limit_scope_invalid';
            return $result;
        }
        $result['subjectRecordId'] = $subjectRecordId;
        $result['scopeSha256'] = $scopeSha256;
        $result['windowSeconds'] = (int) $policy['windowSeconds'];
        $result['maxRequests'] = (int) $policy['maxRequests'];
        if (red_addon_public_mutation_rate_limit_transaction_active($connection)) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        if (!red_addon_public_mutation_rate_limit_cleanup($connection)) {
            $result['reason'] = 'rate_limit_storage_unavailable';
            return $result;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $transactionStarted = false;
            try {
                if (!mysqli_begin_transaction($connection)) {
                    $result['reason'] = 'rate_limit_transaction_failed';
                    return $result;
                }
                $transactionStarted = true;
                if (red_addon_public_mutation_rate_limit_locked_subject(
                    $connection,
                    $subjectRecordId
                ) < 1) {
                    mysqli_rollback($connection);
                    $transactionStarted = false;
                    $result['reason'] = 'rate_limit_subject_invalid';
                    return $result;
                }
                $window = red_addon_public_mutation_rate_limit_current_window(
                    $connection
                );
                if ($window === []) {
                    mysqli_rollback($connection);
                    $transactionStarted = false;
                    $result['reason'] = 'rate_limit_storage_unavailable';
                    return $result;
                }
                $row = red_addon_public_mutation_rate_limit_locked_row(
                    $connection,
                    $subjectRecordId,
                    $scopeSha256,
                    $window['windowStartedAt']
                );
                if ($row === null) {
                    mysqli_rollback($connection);
                    $transactionStarted = false;
                    $result['reason'] = 'rate_limit_storage_unavailable';
                    return $result;
                }
                if ($row !== []) {
                    if ($row['requestCount'] >= $result['maxRequests']) {
                        if (!mysqli_commit($connection)) {
                            mysqli_rollback($connection);
                            $transactionStarted = false;
                            $result['reason'] = 'rate_limit_transaction_failed';
                            return $result;
                        }
                        $transactionStarted = false;
                        $result['valid'] = true;
                        $result['retryAfterSeconds'] = max(
                            1,
                            min(
                                $result['windowSeconds'],
                                $row['remainingSeconds']
                            )
                        );
                        $result['reason'] = 'rate_limited';
                        return $result;
                    }
                    $statement = mysqli_prepare(
                        $connection,
                        'UPDATE RED_Addon_Public_Mutation_Rate_Limits
                         SET RequestCount=RequestCount+1
                         WHERE RecordID=?'
                    );
                    if (!$statement) {
                        mysqli_rollback($connection);
                        $transactionStarted = false;
                        $result['reason'] = 'rate_limit_storage_unavailable';
                        return $result;
                    }
                    mysqli_stmt_bind_param($statement, 'i', $row['recordId']);
                    $updated = mysqli_stmt_execute($statement)
                        && mysqli_stmt_affected_rows($statement) === 1;
                    mysqli_stmt_close($statement);
                    if (!$updated || !mysqli_commit($connection)) {
                        mysqli_rollback($connection);
                        $transactionStarted = false;
                        $result['reason'] = 'rate_limit_transaction_failed';
                        return $result;
                    }
                    $transactionStarted = false;
                    $result['valid'] = true;
                    $result['allowed'] = true;
                    $result['reason'] = 'rate_limit_claimed';
                    return $result;
                }

                $requestCount = 1;
                $statement = mysqli_prepare(
                    $connection,
                    'INSERT INTO RED_Addon_Public_Mutation_Rate_Limits (
                        SubjectRecordID, ScopeSHA256, WindowStartedAt,
                        RequestCount, ExpiresAt
                     ) VALUES (?, ?, ?, ?, ?)'
                );
                if (!$statement) {
                    mysqli_rollback($connection);
                    $transactionStarted = false;
                    $result['reason'] = 'rate_limit_storage_unavailable';
                    return $result;
                }
                mysqli_stmt_bind_param(
                    $statement,
                    'issis',
                    $subjectRecordId,
                    $scopeSha256,
                    $window['windowStartedAt'],
                    $requestCount,
                    $window['expiresAt']
                );
                $inserted = mysqli_stmt_execute($statement);
                $errorCode = mysqli_stmt_errno($statement);
                mysqli_stmt_close($statement);
                if ($inserted && mysqli_commit($connection)) {
                    $transactionStarted = false;
                    $result['valid'] = true;
                    $result['allowed'] = true;
                    $result['reason'] = 'rate_limit_claimed';
                    return $result;
                }
                mysqli_rollback($connection);
                $transactionStarted = false;
                if ($errorCode === 1062 || $errorCode === 1213 || $errorCode === 1205) {
                    continue;
                }
                $result['reason'] = $inserted
                    ? 'rate_limit_transaction_failed'
                    : 'rate_limit_storage_unavailable';
                return $result;
            } catch (Throwable $throwable) {
                if ($transactionStarted) {
                    mysqli_rollback($connection);
                }
                $result['reason'] = 'rate_limit_transaction_failed';
                return $result;
            }
        }
        $result['reason'] = 'rate_limit_contention';
        return $result;
    }
}

?>
