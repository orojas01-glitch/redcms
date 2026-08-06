<?php
/**
 * Core-owned browser subject-cookie lifecycle bridge for a future linked
 * public-mutation dispatcher.
 *
 * The bridge accepts only an explicit database connection, operation, and raw
 * cookie value. It never reads PHP request globals, calls setcookie(), emits a
 * header, starts a session, loads package code, migrates package state, or
 * changes lifecycle/enablement. It returns fixed cookie strings for a caller
 * that has already taken ownership of the HTTP response.
 */

require_once __DIR__ . '/addon_public_mutation_subject_cookie_helpers.php';

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle_result')) {
    function red_addon_public_mutation_subject_cookie_lifecycle_result(
        $reason = 'lifecycle_unavailable'
    ) {
        return [
            'valid' => false,
            'state' => '',
            'subjectRecordId' => 0,
            'previousSubjectRecordId' => 0,
            'setCookieValue' => '',
            'clearCookieValue' => '',
            'reason' => (string) $reason,
        ];
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_clear_serialize')) {
    /**
     * Creates the one fixed host-only deletion cookie without emitting it.
     */
    function red_addon_public_mutation_subject_cookie_clear_serialize()
    {
        return red_addon_public_mutation_subject_cookie_name() .
            '=; Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Strict';
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_clear_serialized_valid')) {
    function red_addon_public_mutation_subject_cookie_clear_serialized_valid(
        $value
    ) {
        return is_string($value)
            && hash_equals(
                red_addon_public_mutation_subject_cookie_clear_serialize(),
                $value
            );
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle_transaction_state')) {
    /**
     * Probes caller transaction state without a privileged performance-schema
     * read or a commit/rollback side effect. MySQL rejects SET TRANSACTION
     * while a transaction is active; on an idle connection, the statement
     * preserves the current session isolation level for the next transaction.
     */
    function red_addon_public_mutation_subject_cookie_lifecycle_transaction_state(
        $connection
    ) {
        if (!$connection) {
            return null;
        }
        try {
            $query = mysqli_query(
                $connection,
                'SELECT @@transaction_isolation AS IsolationLevel'
            );
            $row = $query ? mysqli_fetch_assoc($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            $isolationLevel = strtoupper(
                (string) ($row['IsolationLevel'] ?? '')
            );
            $isolationLevels = [
                'READ-UNCOMMITTED' => 'READ UNCOMMITTED',
                'READ-COMMITTED' => 'READ COMMITTED',
                'REPEATABLE-READ' => 'REPEATABLE READ',
                'SERIALIZABLE' => 'SERIALIZABLE',
            ];
            if (!isset($isolationLevels[$isolationLevel])) {
                return null;
            }
            if (mysqli_query(
                $connection,
                'SET TRANSACTION ISOLATION LEVEL ' .
                    $isolationLevels[$isolationLevel]
            )) {
                return false;
            }
            return mysqli_errno($connection) === 1568 ? true : null;
        } catch (mysqli_sql_exception $exception) {
            return $exception->getCode() === 1568 ? true : null;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle_active_id_for_update')) {
    /**
     * Resolves one active subject while the caller owns a transaction lock.
     */
    function red_addon_public_mutation_subject_cookie_lifecycle_active_id_for_update(
        $connection,
        $cookieValue
    ) {
        if (!$connection
            || !red_addon_public_mutation_valid_opaque_token($cookieValue)
        ) {
            return 0;
        }
        $tokenSha256 = red_addon_public_mutation_opaque_token_sha256(
            $cookieValue
        );
        if (!red_addon_valid_sha256($tokenSha256)) {
            return 0;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT RecordID
                 FROM RED_Addon_Public_Mutation_Subjects
                 WHERE BINARY SubjectTokenSHA256=BINARY ?
                   AND ExpiresAt > UTC_TIMESTAMP()
                 LIMIT 1
                 FOR UPDATE'
            );
            if (!$statement) {
                return 0;
            }
            mysqli_stmt_bind_param($statement, 's', $tokenSha256);
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
            $recordId = is_array($row)
                ? (int) ($row['RecordID'] ?? 0)
                : 0;
            return $recordId > 0 ? $recordId : 0;
        } catch (Throwable $throwable) {
            return 0;
        }
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle_expire_in_transaction')) {
    function red_addon_public_mutation_subject_cookie_lifecycle_expire_in_transaction(
        $connection,
        $subjectRecordId
    ) {
        if (!$connection
            || !is_int($subjectRecordId)
            || $subjectRecordId < 1
        ) {
            return false;
        }
        try {
            $statement = mysqli_prepare(
                $connection,
                'UPDATE RED_Addon_Public_Mutation_Subjects
                 SET ExpiresAt=UTC_TIMESTAMP()
                 WHERE RecordID=?
                   AND ExpiresAt > UTC_TIMESTAMP()'
            );
            if (!$statement) {
                return false;
            }
            mysqli_stmt_bind_param($statement, 'i', $subjectRecordId);
            $executed = mysqli_stmt_execute($statement);
            $affected = mysqli_stmt_affected_rows($statement);
            mysqli_stmt_close($statement);
            return $executed && $affected === 1;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle_set_result')) {
    function red_addon_public_mutation_subject_cookie_lifecycle_set_result(
        $state,
        $subjectRecordId,
        $previousSubjectRecordId,
        $setCookieValue,
        $clearCookieValue,
        $reason
    ) {
        $result = red_addon_public_mutation_subject_cookie_lifecycle_result(
            $reason
        );
        $result['valid'] = true;
        $result['state'] = (string) $state;
        $result['subjectRecordId'] = (int) $subjectRecordId;
        $result['previousSubjectRecordId'] = (int) $previousSubjectRecordId;
        $result['setCookieValue'] = (string) $setCookieValue;
        $result['clearCookieValue'] = (string) $clearCookieValue;
        return $result;
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid')) {
    function red_addon_public_mutation_subject_cookie_lifecycle_serialized_valid(
        $result
    ) {
        if (!is_array($result)
            || array_keys($result) !== [
                'valid',
                'state',
                'subjectRecordId',
                'previousSubjectRecordId',
                'setCookieValue',
                'clearCookieValue',
                'reason',
            ]
            || !is_bool($result['valid'])
            || !is_string($result['state'])
            || !is_int($result['subjectRecordId'])
            || !is_int($result['previousSubjectRecordId'])
            || !is_string($result['setCookieValue'])
            || !is_string($result['clearCookieValue'])
            || !is_string($result['reason'])
        ) {
            return false;
        }
        if (!$result['valid']) {
            return $result ===
                red_addon_public_mutation_subject_cookie_lifecycle_result(
                    $result['reason']
                );
        }
        if (!in_array(
            $result['state'],
            ['issued', 'resolved', 'cleared', 'rotated'],
            true
        )) {
            return false;
        }
        if ($result['state'] === 'issued') {
            return $result['subjectRecordId'] > 0
                && $result['previousSubjectRecordId'] === 0
                && red_addon_public_mutation_subject_cookie_serialization_valid([
                    'valid' => true,
                    'setCookieValue' => $result['setCookieValue'],
                    'reason' => 'subject_cookie_serialized',
                ])
                && $result['clearCookieValue'] === '';
        }
        if ($result['state'] === 'resolved') {
            return $result['subjectRecordId'] > 0
                && $result['previousSubjectRecordId']
                    === $result['subjectRecordId']
                && $result['setCookieValue'] === ''
                && $result['clearCookieValue'] === '';
        }
        if ($result['state'] === 'cleared') {
            return $result['subjectRecordId'] === 0
                && $result['previousSubjectRecordId'] >= 0
                && $result['setCookieValue'] === ''
                && red_addon_public_mutation_subject_cookie_clear_serialized_valid(
                    $result['clearCookieValue']
                );
        }
        return $result['subjectRecordId'] > 0
            && $result['previousSubjectRecordId'] > 0
            && $result['subjectRecordId'] !== $result['previousSubjectRecordId']
            && red_addon_public_mutation_subject_cookie_serialization_valid([
                'valid' => true,
                'setCookieValue' => $result['setCookieValue'],
                'reason' => 'subject_cookie_serialized',
            ])
            && red_addon_public_mutation_subject_cookie_clear_serialized_valid(
                $result['clearCookieValue']
            );
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle_ensure')) {
    function red_addon_public_mutation_subject_cookie_lifecycle_ensure(
        $connection,
        $cookieValue
    ) {
        $result = red_addon_public_mutation_subject_cookie_lifecycle_result();
        if (!red_addon_public_mutation_subject_storage_available($connection)) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        $transactionState =
            red_addon_public_mutation_subject_cookie_lifecycle_transaction_state(
                $connection
            );
        if ($transactionState === null) {
            $result['reason'] = 'transaction_state_unavailable';
            return $result;
        }
        if ($transactionState) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        if (is_string($cookieValue) && $cookieValue !== '') {
            $subject = red_addon_public_mutation_subject_resolve(
                $connection,
                $cookieValue
            );
            if (!empty($subject['valid'])) {
                $subjectRecordId = red_addon_public_mutation_subject_record_id(
                    $subject
                );
                return red_addon_public_mutation_subject_cookie_lifecycle_set_result(
                    'resolved',
                    $subjectRecordId,
                    $subjectRecordId,
                    '',
                    '',
                    'subject_cookie_resolved'
                );
            }
        }
        $issued = red_addon_public_mutation_subject_issue($connection);
        if (empty($issued['valid'])) {
            $result['reason'] = 'subject_issue_failed';
            return $result;
        }
        $serialization = red_addon_public_mutation_subject_cookie_serialize(
            $issued
        );
        if (!red_addon_public_mutation_subject_cookie_serialization_valid(
            $serialization
        )) {
            $result['reason'] = 'cookie_serialization_failed';
            return $result;
        }
        $reason = is_string($cookieValue) && $cookieValue !== ''
            ? 'subject_cookie_reissued'
            : 'subject_cookie_issued';
        return red_addon_public_mutation_subject_cookie_lifecycle_set_result(
            'issued',
            (int) $issued['subjectRecordId'],
            0,
            $serialization['setCookieValue'],
            '',
            $reason
        );
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle_clear')) {
    function red_addon_public_mutation_subject_cookie_lifecycle_clear(
        $connection,
        $cookieValue
    ) {
        $result = red_addon_public_mutation_subject_cookie_lifecycle_result();
        if (!red_addon_public_mutation_subject_storage_available($connection)) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        $clearCookieValue =
            red_addon_public_mutation_subject_cookie_clear_serialize();
        if (!is_string($cookieValue)
            || !red_addon_public_mutation_valid_opaque_token($cookieValue)
        ) {
            return red_addon_public_mutation_subject_cookie_lifecycle_set_result(
                'cleared',
                0,
                0,
                '',
                $clearCookieValue,
                'subject_cookie_cleared'
            );
        }
        $transactionState =
            red_addon_public_mutation_subject_cookie_lifecycle_transaction_state(
                $connection
            );
        if ($transactionState === null) {
            $result['reason'] = 'transaction_state_unavailable';
            return $result;
        }
        if ($transactionState) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        $transactionStarted = false;
        try {
            if (!mysqli_begin_transaction($connection)) {
                $result['reason'] = 'transaction_failed';
                return $result;
            }
            $transactionStarted = true;
            $subjectRecordId =
                red_addon_public_mutation_subject_cookie_lifecycle_active_id_for_update(
                    $connection,
                    $cookieValue
                );
            if ($subjectRecordId > 0
                && !red_addon_public_mutation_subject_cookie_lifecycle_expire_in_transaction(
                    $connection,
                    $subjectRecordId
                )
            ) {
                throw new RuntimeException('subject_expire_failed');
            }
            if (!mysqli_commit($connection)) {
                throw new RuntimeException('transaction_failed');
            }
            $transactionStarted = false;
            return red_addon_public_mutation_subject_cookie_lifecycle_set_result(
                'cleared',
                0,
                $subjectRecordId,
                '',
                $clearCookieValue,
                'subject_cookie_cleared'
            );
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                mysqli_rollback($connection);
            }
            $result['reason'] = $throwable->getMessage() === 'subject_expire_failed'
                ? 'subject_expire_failed'
                : 'transaction_failed';
            return $result;
        }
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle_rotate')) {
    function red_addon_public_mutation_subject_cookie_lifecycle_rotate(
        $connection,
        $cookieValue
    ) {
        $result = red_addon_public_mutation_subject_cookie_lifecycle_result();
        if (!red_addon_public_mutation_subject_storage_available($connection)) {
            $result['reason'] = 'storage_unavailable';
            return $result;
        }
        if (!red_addon_public_mutation_valid_opaque_token($cookieValue)) {
            $result['reason'] = 'rotation_source_invalid';
            return $result;
        }
        $transactionState =
            red_addon_public_mutation_subject_cookie_lifecycle_transaction_state(
                $connection
            );
        if ($transactionState === null) {
            $result['reason'] = 'transaction_state_unavailable';
            return $result;
        }
        if ($transactionState) {
            $result['reason'] = 'transaction_already_active';
            return $result;
        }
        $transactionStarted = false;
        try {
            if (!mysqli_begin_transaction($connection)) {
                $result['reason'] = 'transaction_failed';
                return $result;
            }
            $transactionStarted = true;
            $previousSubjectRecordId =
                red_addon_public_mutation_subject_cookie_lifecycle_active_id_for_update(
                    $connection,
                    $cookieValue
                );
            if ($previousSubjectRecordId < 1) {
                throw new RuntimeException('rotation_source_invalid');
            }
            $issued = red_addon_public_mutation_subject_issue($connection);
            if (empty($issued['valid'])) {
                throw new RuntimeException('subject_issue_failed');
            }
            if (!red_addon_public_mutation_subject_cookie_lifecycle_expire_in_transaction(
                $connection,
                $previousSubjectRecordId
            )) {
                throw new RuntimeException('subject_expire_failed');
            }
            $serialization = red_addon_public_mutation_subject_cookie_serialize(
                $issued
            );
            if (!red_addon_public_mutation_subject_cookie_serialization_valid(
                $serialization
            )) {
                throw new RuntimeException('cookie_serialization_failed');
            }
            if (!mysqli_commit($connection)) {
                throw new RuntimeException('transaction_failed');
            }
            $transactionStarted = false;
            return red_addon_public_mutation_subject_cookie_lifecycle_set_result(
                'rotated',
                (int) $issued['subjectRecordId'],
                $previousSubjectRecordId,
                $serialization['setCookieValue'],
                red_addon_public_mutation_subject_cookie_clear_serialize(),
                'subject_cookie_rotated'
            );
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                mysqli_rollback($connection);
            }
            $result['reason'] = $throwable->getMessage();
            return $result;
        }
    }
}

if (!function_exists('red_addon_public_mutation_subject_cookie_lifecycle')) {
    /**
     * Runs one explicit browser-cookie lifecycle operation.
     */
    function red_addon_public_mutation_subject_cookie_lifecycle(
        $connection,
        $operation,
        $cookieValue = ''
    ) {
        if (!is_string($operation)) {
            return red_addon_public_mutation_subject_cookie_lifecycle_result(
                'operation_invalid'
            );
        }
        if ($operation === 'ensure') {
            return red_addon_public_mutation_subject_cookie_lifecycle_ensure(
                $connection,
                $cookieValue
            );
        }
        if ($operation === 'clear') {
            return red_addon_public_mutation_subject_cookie_lifecycle_clear(
                $connection,
                $cookieValue
            );
        }
        if ($operation === 'rotate') {
            return red_addon_public_mutation_subject_cookie_lifecycle_rotate(
                $connection,
                $cookieValue
            );
        }
        return red_addon_public_mutation_subject_cookie_lifecycle_result(
            'operation_invalid'
        );
    }
}

?>
