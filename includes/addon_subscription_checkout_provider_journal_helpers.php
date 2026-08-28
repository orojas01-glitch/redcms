<?php
/** Durable hash-only journal for one subscription Checkout provider attempt. */

require_once __DIR__
    . '/addon_subscription_checkout_provider_operation_helpers.php';

if (!function_exists('red_addon_subscription_provider_journal_result')) {
    function red_addon_subscription_provider_journal_result(
        $status = 'unavailable'
    ) {
        return [
            'status' => (string) $status,
            'executionStartStateSha256' => '',
            'resultSha256' => '',
        ];
    }
}

if (!function_exists('red_addon_subscription_provider_journal_transaction_active')) {
    function red_addon_subscription_provider_journal_transaction_active(
        $connection
    ) {
        if (!($connection instanceof mysqli)) {
            return false;
        }
        try {
            if (!mysqli_query(
                $connection,
                'SAVEPOINT redcms_subscription_provider_journal_guard'
            )) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_subscription_provider_journal_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_subscription_provider_journal_storage_ready')) {
    function red_addon_subscription_provider_journal_storage_ready(
        $connection
    ) {
        if (!($connection instanceof mysqli)) {
            return false;
        }
        try {
            $query = mysqli_query(
                $connection,
                "SELECT CONCAT_WS(':', ENGINE,
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME=
                         'RED_Addon_StoreLite_Stripe_Subscription_Checkout_Operations'))
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME=
                     'RED_Addon_StoreLite_Stripe_Subscription_Checkout_Operations'"
            );
            $row = $query ? mysqli_fetch_row($query) : null;
            if ($query) {
                mysqli_free_result($query);
            }
            return is_array($row) && ($row[0] ?? '') === 'InnoDB:11';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_subscription_provider_journal_evidence_valid')) {
    function red_addon_subscription_provider_journal_evidence_valid(
        $operation,
        $evidence
    ) {
        if (!is_string($operation)
            || !in_array($operation, ['inspect', 'start', 'complete'], true)
            || !is_array($evidence)
            || !is_int($evidence['subjectRecordId'] ?? null)
            || $evidence['subjectRecordId'] < 1
            || !is_string($evidence['offerId'] ?? null)
            || preg_match(
                '/\A[a-z0-9][a-z0-9._-]{0,63}\z/D',
                $evidence['offerId']
            ) !== 1
            || !is_string($evidence['intentReference'] ?? null)
            || preg_match(
                '/\Asint_[a-f0-9]{32}\z/D',
                $evidence['intentReference']
            ) !== 1
            || !red_addon_valid_sha256($evidence['planSha256'] ?? null)
        ) {
            return false;
        }
        $expected = $operation === 'inspect'
            ? ['subjectRecordId', 'offerId', 'intentReference', 'planSha256']
            : ($operation === 'start'
                ? [
                    'subjectRecordId', 'offerId', 'intentReference',
                    'planSha256', 'claimStateSha256',
                    'executionStartStateSha256',
                ]
                : [
                    'subjectRecordId', 'offerId', 'intentReference',
                    'planSha256', 'executionStartStateSha256',
                    'resultSha256', 'checkoutSessionRefSha256',
                ]);
        if (array_keys($evidence) !== $expected) {
            return false;
        }
        foreach (array_slice($expected, 4) as $key) {
            if (!red_addon_valid_sha256($evidence[$key] ?? null)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('red_addon_subscription_provider_journal_row')) {
    function red_addon_subscription_provider_journal_row(
        $connection,
        array $evidence,
        $forUpdate = false
    ) {
        $statement = mysqli_prepare(
            $connection,
            'SELECT SubjectRecordID, OfferID, LOWER(HEX(PlanSHA256)),
                    LOWER(HEX(ClaimStateSHA256)),
                    LOWER(HEX(ExecutionStartStateSHA256)),
                    COALESCE(LOWER(HEX(ResultSHA256)), \'\'),
                    COALESCE(LOWER(HEX(CheckoutSessionRefSHA256)), \'\'),
                    AttemptStatus
             FROM RED_Addon_StoreLite_Stripe_Subscription_Checkout_Operations
             WHERE IntentReference=? LIMIT 1'
                . ($forUpdate ? ' FOR UPDATE' : '')
        );
        if (!$statement
            || !mysqli_stmt_execute(
                $statement,
                [$evidence['intentReference']]
            )
        ) {
            if ($statement) {
                mysqli_stmt_close($statement);
            }
            return null;
        }
        $query = mysqli_stmt_get_result($statement);
        $row = $query ? mysqli_fetch_row($query) : null;
        if ($query) {
            mysqli_free_result($query);
        }
        mysqli_stmt_close($statement);
        if ($row === null) {
            return [];
        }
        return [
            'subjectRecordId' => (int) $row[0],
            'offerId' => (string) $row[1],
            'planSha256' => (string) $row[2],
            'claimStateSha256' => (string) $row[3],
            'executionStartStateSha256' => (string) $row[4],
            'resultSha256' => (string) $row[5],
            'checkoutSessionRefSha256' => (string) $row[6],
            'status' => (string) $row[7],
        ];
    }
}

if (!function_exists('red_addon_subscription_provider_journal')) {
    function red_addon_subscription_provider_journal(
        $connection,
        $operation,
        $evidence
    ) {
        $unavailable = red_addon_subscription_provider_journal_result();
        if (!($connection instanceof mysqli)
            || !red_addon_subscription_provider_journal_evidence_valid(
                $operation,
                $evidence
            )
            || !red_addon_subscription_provider_journal_storage_ready(
                $connection
            )
            || red_addon_subscription_provider_journal_transaction_active(
                $connection
            )
        ) {
            return $unavailable;
        }
        if ($operation === 'inspect') {
            $row = red_addon_subscription_provider_journal_row(
                $connection,
                $evidence
            );
            if ($row === []) {
                return red_addon_subscription_provider_journal_result(
                    'absent'
                );
            }
            if (!is_array($row)
                || $row['subjectRecordId'] !== $evidence['subjectRecordId']
                || $row['offerId'] !== $evidence['offerId']
                || $row['planSha256'] !== $evidence['planSha256']
                || !in_array($row['status'], ['started', 'completed'], true)
            ) {
                return $unavailable;
            }
            $result = red_addon_subscription_provider_journal_result(
                $row['status']
            );
            $result['executionStartStateSha256'] =
                $row['executionStartStateSha256'];
            $result['resultSha256'] = $row['resultSha256'];
            return $result;
        }

        $transactionStarted = false;
        try {
            if (!mysqli_begin_transaction($connection)) {
                return $unavailable;
            }
            $transactionStarted = true;
            $row = red_addon_subscription_provider_journal_row(
                $connection,
                $evidence,
                true
            );
            if ($operation === 'start') {
                if ($row !== []) {
                    mysqli_rollback($connection);
                    return $unavailable;
                }
                $statement = mysqli_prepare(
                    $connection,
                    'INSERT INTO
                     RED_Addon_StoreLite_Stripe_Subscription_Checkout_Operations
                     (IntentReference, SubjectRecordID, OfferID, PlanSHA256,
                      ClaimStateSHA256, ExecutionStartStateSHA256,
                      AttemptStatus)
                     SELECT ?, ?, ?, UNHEX(?), UNHEX(?), UNHEX(?), \'started\'
                     FROM RED_Addon_Installations
                     WHERE PackageID=\'redcms.store-lite-stripe-checkout\'
                       AND PackageVersion=\'0.1.16\'
                       AND LifecycleState=\'enabled\''
                );
                $parameters = [
                    $evidence['intentReference'],
                    $evidence['subjectRecordId'],
                    $evidence['offerId'],
                    $evidence['planSha256'],
                    $evidence['claimStateSha256'],
                    $evidence['executionStartStateSha256'],
                ];
            } else {
                if (!is_array($row)
                    || $row === []
                    || $row['status'] !== 'started'
                    || $row['subjectRecordId']
                        !== $evidence['subjectRecordId']
                    || $row['offerId'] !== $evidence['offerId']
                    || $row['planSha256'] !== $evidence['planSha256']
                    || $row['executionStartStateSha256']
                        !== $evidence['executionStartStateSha256']
                ) {
                    mysqli_rollback($connection);
                    return $unavailable;
                }
                $statement = mysqli_prepare(
                    $connection,
                    'UPDATE
                     RED_Addon_StoreLite_Stripe_Subscription_Checkout_Operations
                     SET ResultSHA256=UNHEX(?),
                         CheckoutSessionRefSHA256=UNHEX(?),
                         AttemptStatus=\'completed\', CompletedAt=UTC_TIMESTAMP()
                     WHERE IntentReference=? AND AttemptStatus=\'started\''
                );
                $parameters = [
                    $evidence['resultSha256'],
                    $evidence['checkoutSessionRefSha256'],
                    $evidence['intentReference'],
                ];
            }
            if (!$statement
                || !mysqli_stmt_execute($statement, $parameters)
                || mysqli_stmt_affected_rows($statement) !== 1
            ) {
                if ($statement) {
                    mysqli_stmt_close($statement);
                }
                mysqli_rollback($connection);
                return $unavailable;
            }
            mysqli_stmt_close($statement);
            if (!mysqli_commit($connection)) {
                mysqli_rollback($connection);
                return $unavailable;
            }
            $transactionStarted = false;
            $result = red_addon_subscription_provider_journal_result(
                $operation === 'start' ? 'started' : 'completed'
            );
            $result['executionStartStateSha256'] =
                $evidence['executionStartStateSha256'];
            $result['resultSha256'] = $operation === 'complete'
                ? $evidence['resultSha256'] : '';
            return $result;
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                try {
                    mysqli_rollback($connection);
                } catch (Throwable $ignored) {
                }
            }
            return $unavailable;
        }
    }
}

if (!function_exists('red_addon_subscription_provider_database_journal')) {
    function red_addon_subscription_provider_database_journal($connection)
    {
        return static fn ($operation, $evidence) =>
            red_addon_subscription_provider_journal(
                $connection,
                $operation,
                $evidence
            );
    }
}

?>
