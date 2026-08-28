<?php
/** Transactional hash-only journal for verified subscription webhook events. */

require_once __DIR__ . '/addon_subscription_event_coordinator_helpers.php';

if (!function_exists('red_addon_subscription_event_journal_result')) {
    function red_addon_subscription_event_journal_result($status = 'unavailable')
    {
        return [
            'status' => (string) $status,
            'claimStateSha256' => '',
            'eventEvidenceSha256' => '',
            'lifecycleResultSha256' => '',
        ];
    }
}

if (!function_exists('red_addon_subscription_event_journal_storage_ready')) {
    function red_addon_subscription_event_journal_storage_ready($connection)
    {
        if (!($connection instanceof mysqli)) return false;
        try {
            $query = mysqli_query(
                $connection,
                "SELECT CONCAT_WS(':', ENGINE,
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_StoreLite_Stripe_Subscription_Event_Receipts'))
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_StoreLite_Stripe_Subscription_Event_Receipts'"
            );
            $row = $query ? mysqli_fetch_row($query) : null;
            if ($query) mysqli_free_result($query);
            return is_array($row) && ($row[0] ?? '') === 'InnoDB:15';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_subscription_event_journal_transaction_active')) {
    function red_addon_subscription_event_journal_transaction_active($connection)
    {
        if (!($connection instanceof mysqli)) return false;
        try {
            return mysqli_query(
                $connection,
                'SAVEPOINT redcms_subscription_event_journal_guard'
            ) === true
                && mysqli_query(
                    $connection,
                    'RELEASE SAVEPOINT redcms_subscription_event_journal_guard'
                ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_subscription_event_journal_evidence_valid')) {
    function red_addon_subscription_event_journal_evidence_valid(
        $operation,
        $evidence
    ) {
        if (!in_array($operation, ['inspect', 'claim', 'complete'], true)
            || !is_array($evidence)
        ) return false;
        $base = [
            'eventRefSha256', 'rawBodySha256',
            'signatureEvidenceSha256', 'providerEventType',
            'claimStateSha256', 'signedAtEpoch', 'receivedAtEpoch',
        ];
        $expected = $operation === 'complete'
            ? array_merge($base, [
                'status', 'intentReference', 'eventEvidenceSha256',
                'lifecycleResultSha256', 'completedAtEpoch',
            ])
            : $base;
        if (array_keys($evidence) !== $expected) return false;
        foreach ([
            'eventRefSha256', 'rawBodySha256',
            'signatureEvidenceSha256', 'claimStateSha256',
        ] as $key) {
            if (!red_addon_valid_sha256($evidence[$key] ?? null)) return false;
        }
        if (!in_array($evidence['providerEventType'] ?? '', [
            'checkout.session.completed', 'invoice.paid',
            'invoice.payment_failed', 'customer.subscription.deleted',
            'checkout.session.expired',
        ], true)
            || !is_int($evidence['signedAtEpoch'])
            || !is_int($evidence['receivedAtEpoch'])
            || abs($evidence['receivedAtEpoch'] - $evidence['signedAtEpoch']) > 300
        ) return false;
        if ($operation !== 'complete') return true;
        return in_array($evidence['status'], ['applied', 'refused'], true)
            && preg_match(
                '/\Asint_[a-f0-9]{32}\z/D',
                $evidence['intentReference']
            ) === 1
            && red_addon_valid_sha256($evidence['eventEvidenceSha256'])
            && red_addon_valid_sha256($evidence['lifecycleResultSha256'])
            && is_int($evidence['completedAtEpoch'])
            && $evidence['completedAtEpoch'] >= $evidence['receivedAtEpoch'];
    }
}

if (!function_exists('red_addon_subscription_event_journal_row')) {
    function red_addon_subscription_event_journal_row(
        $connection,
        $eventRefSha256,
        $forUpdate = false
    ) {
        $statement = mysqli_prepare(
            $connection,
            "SELECT LOWER(HEX(RawBodySHA256)),
                    LOWER(HEX(SignatureEvidenceSHA256)), ProviderEventType,
                    LOWER(HEX(ClaimStateSHA256)), ReceiptStatus,
                    COALESCE(IntentReference, ''),
                    COALESCE(LOWER(HEX(EventEvidenceSHA256)), ''),
                    COALESCE(LOWER(HEX(LifecycleResultSHA256)), ''),
                    SignedAtEpoch, ReceivedAtEpoch,
                    COALESCE(CompletedAtEpoch, 0)
             FROM RED_Addon_StoreLite_Stripe_Subscription_Event_Receipts
             WHERE EventRefSHA256=UNHEX(?) LIMIT 1"
                . ($forUpdate ? ' FOR UPDATE' : '')
        );
        if (!$statement
            || !mysqli_stmt_execute($statement, [$eventRefSha256])
        ) {
            if ($statement) mysqli_stmt_close($statement);
            return null;
        }
        $query = mysqli_stmt_get_result($statement);
        $row = $query ? mysqli_fetch_row($query) : null;
        if ($query) mysqli_free_result($query);
        mysqli_stmt_close($statement);
        if ($row === null) return [];
        return [
            'rawBodySha256' => (string) $row[0],
            'signatureEvidenceSha256' => (string) $row[1],
            'providerEventType' => (string) $row[2],
            'claimStateSha256' => (string) $row[3],
            'status' => (string) $row[4],
            'intentReference' => (string) $row[5],
            'eventEvidenceSha256' => (string) $row[6],
            'lifecycleResultSha256' => (string) $row[7],
            'signedAtEpoch' => (int) $row[8],
            'receivedAtEpoch' => (int) $row[9],
            'completedAtEpoch' => (int) $row[10],
        ];
    }
}

if (!function_exists('red_addon_subscription_event_journal')) {
    function red_addon_subscription_event_journal(
        $connection,
        $operation,
        $evidence
    ) {
        $unavailable = red_addon_subscription_event_journal_result();
        if (!($connection instanceof mysqli)
            || !red_addon_subscription_event_journal_evidence_valid(
                $operation,
                $evidence
            )
            || !red_addon_subscription_event_journal_storage_ready($connection)
            || red_addon_subscription_event_journal_transaction_active($connection)
        ) return $unavailable;

        if ($operation === 'inspect') {
            $row = red_addon_subscription_event_journal_row(
                $connection,
                $evidence['eventRefSha256']
            );
            if ($row === []) return red_addon_subscription_event_journal_result('absent');
            if (!is_array($row)
                || $row['rawBodySha256'] !== $evidence['rawBodySha256']
                || !red_addon_valid_sha256(
                    $row['signatureEvidenceSha256']
                )
                || $row['providerEventType'] !== $evidence['providerEventType']
                || !red_addon_valid_sha256($row['claimStateSha256'])
                || $row['signedAtEpoch'] < 1
                || $row['receivedAtEpoch'] < 1
                || abs(
                    $row['receivedAtEpoch'] - $row['signedAtEpoch']
                ) > 300
                || !in_array($row['status'], ['verified','applied','refused'], true)
            ) return $unavailable;
            $result = red_addon_subscription_event_journal_result($row['status']);
            $result['claimStateSha256'] = $row['claimStateSha256'];
            $result['eventEvidenceSha256'] = $row['eventEvidenceSha256'];
            $result['lifecycleResultSha256'] = $row['lifecycleResultSha256'];
            return $result;
        }

        $started = false;
        try {
            if (!mysqli_begin_transaction($connection)) return $unavailable;
            $started = true;
            $row = red_addon_subscription_event_journal_row(
                $connection,
                $evidence['eventRefSha256'],
                true
            );
            if ($operation === 'claim') {
                if ($row !== []) {
                    mysqli_rollback($connection);
                    return $unavailable;
                }
                $statement = mysqli_prepare(
                    $connection,
                    "INSERT INTO RED_Addon_StoreLite_Stripe_Subscription_Event_Receipts
                     (EventRefSHA256, RawBodySHA256, SignatureEvidenceSHA256,
                      ProviderEventType, ClaimStateSHA256, ReceiptStatus,
                      SignedAtEpoch, ReceivedAtEpoch)
                     SELECT UNHEX(?), UNHEX(?), UNHEX(?), ?, UNHEX(?),
                            'verified', ?, ?
                     FROM RED_Addon_Installations
                     WHERE PackageID='redcms.store-lite-stripe-checkout'
                       AND PackageVersion='0.1.18'
                       AND LifecycleState='enabled'"
                );
                $parameters = [
                    $evidence['eventRefSha256'],
                    $evidence['rawBodySha256'],
                    $evidence['signatureEvidenceSha256'],
                    $evidence['providerEventType'],
                    $evidence['claimStateSha256'],
                    $evidence['signedAtEpoch'],
                    $evidence['receivedAtEpoch'],
                ];
            } else {
                if (!is_array($row) || $row === []
                    || $row['status'] !== 'verified'
                    || $row['claimStateSha256']
                        !== $evidence['claimStateSha256']
                ) {
                    mysqli_rollback($connection);
                    return $unavailable;
                }
                $statement = mysqli_prepare(
                    $connection,
                    "UPDATE RED_Addon_StoreLite_Stripe_Subscription_Event_Receipts
                     SET ReceiptStatus=?, IntentReference=?,
                         EventEvidenceSHA256=UNHEX(?),
                         LifecycleResultSHA256=UNHEX(?),
                         CompletedAtEpoch=?
                     WHERE EventRefSHA256=UNHEX(?)
                       AND ReceiptStatus='verified'"
                );
                $parameters = [
                    $evidence['status'], $evidence['intentReference'],
                    $evidence['eventEvidenceSha256'],
                    $evidence['lifecycleResultSha256'],
                    $evidence['completedAtEpoch'],
                    $evidence['eventRefSha256'],
                ];
            }
            if (!$statement
                || !mysqli_stmt_execute($statement, $parameters)
                || mysqli_stmt_affected_rows($statement) !== 1
            ) {
                if ($statement) mysqli_stmt_close($statement);
                mysqli_rollback($connection);
                return $unavailable;
            }
            mysqli_stmt_close($statement);
            if (!mysqli_commit($connection)) return $unavailable;
            $started = false;
            $result = red_addon_subscription_event_journal_result(
                $operation === 'claim' ? 'verified' : $evidence['status']
            );
            $result['claimStateSha256'] = $evidence['claimStateSha256'];
            if ($operation === 'complete') {
                $result['eventEvidenceSha256'] =
                    $evidence['eventEvidenceSha256'];
                $result['lifecycleResultSha256'] =
                    $evidence['lifecycleResultSha256'];
            }
            return $result;
        } catch (Throwable $throwable) {
            if ($started) {
                try { mysqli_rollback($connection); } catch (Throwable $ignored) {}
            }
            return $unavailable;
        }
    }
}

?>
