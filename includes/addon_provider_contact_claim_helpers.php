<?php
/**
 * Core-owned P3E-8A atomic claim for one authorized provider-contact attempt.
 *
 * The claim consumes only the exact persisted P3E-7 authorization. It records
 * one immutable attempt claim before any future contact boundary. It never
 * resolves a credential, invokes package PHP, or opens a network connection.
 */

require_once __DIR__ . '/addon_provider_contact_authorization_helpers.php';

if (!function_exists('red_addon_provider_contact_claim_result')) {
    function red_addon_provider_contact_claim_result()
    {
        return [
            'valid' => false,
            'ready' => false,
            'status' => 'invalid',
            'packageId' => '',
            'packageVersion' => '',
            'lifecycleState' => '',
            'actorAdminRecordId' => 0,
            'ownerSubjectSha256' => '',
            'planSha256' => '',
            'authorizationSha256' => '',
            'authorizationNonceSha256' => '',
            'authorizationStateSha256' => '',
            'claimStateSha256' => '',
            'authorizationActionId' => '',
            'claimActionId' => '',
            'issuedAtUtc' => '',
            'expiresAtUtc' => '',
            'maximumAttempts' => 0,
            'ownerAuthorityRevalidated' => false,
            'authorizationRecorded' => false,
            'claimAvailable' => false,
            'attemptClaimed' => false,
            'auditRecorded' => false,
            'executionPerformed' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'storeLiteMutation' => false,
            'clientDeployment' => false,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_provider_contact_claim_action_id')) {
    function red_addon_provider_contact_claim_action_id($nonceSha256)
    {
        return red_addon_provider_contact_sha256($nonceSha256)
            ? 'provider-contact-attempt-claim.' . $nonceSha256
            : '';
    }
}

if (!function_exists('red_addon_provider_contact_claim_execution_row')) {
    function red_addon_provider_contact_claim_execution_row(
        $connection,
        $packageId,
        $actionId,
        $lock = false
    ) {
        if (!($connection instanceof mysqli)
            || !is_string($packageId)
            || $packageId === ''
            || !is_string($actionId)
            || $actionId === ''
            || !is_bool($lock)
        ) {
            return ['valid' => false, 'found' => false, 'row' => null];
        }
        try {
            $sql = 'SELECT PlanSHA256, ContractSHA256, PreviousStateSHA256,
                           StateSHA256, ActorAdminRecordID
                    FROM RED_Addon_Admin_Action_Executions
                    WHERE PackageID=? AND ActionID=? AND TargetRecordID=1';
            if ($lock) {
                $sql .= ' FOR UPDATE';
            }
            $statement = mysqli_prepare($connection, $sql);
            if (!$statement) {
                return ['valid' => false, 'found' => false, 'row' => null];
            }
            mysqli_stmt_bind_param($statement, 'ss', $packageId, $actionId);
            $executed = mysqli_stmt_execute($statement);
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $row = $query ? mysqli_fetch_assoc($query) : null;
            $rows = $query ? mysqli_num_rows($query) : 0;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
            if (!$executed || $rows > 1) {
                return ['valid' => false, 'found' => false, 'row' => null];
            }
            return [
                'valid' => true,
                'found' => $rows === 1,
                'row' => $rows === 1 ? $row : null,
            ];
        } catch (Throwable $throwable) {
            return ['valid' => false, 'found' => false, 'row' => null];
        }
    }
}

if (!function_exists('red_addon_provider_contact_claim_authorization_matches')) {
    function red_addon_provider_contact_claim_authorization_matches(
        array $row,
        array $authorizationPlan
    ) {
        return red_addon_provider_contact_exact_keys($row, [
            'PlanSHA256', 'ContractSHA256', 'PreviousStateSHA256',
            'StateSHA256', 'ActorAdminRecordID',
        ])
            && hash_equals(
                (string) ($authorizationPlan['planSha256'] ?? ''),
                (string) ($row['PlanSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($authorizationPlan['authorizationSha256'] ?? ''),
                (string) ($row['ContractSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($authorizationPlan['ownerSubjectSha256'] ?? ''),
                (string) ($row['PreviousStateSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($authorizationPlan['authorizationStateSha256'] ?? ''),
                (string) ($row['StateSHA256'] ?? '')
            )
            && (int) ($row['ActorAdminRecordID'] ?? 0)
                === (int) ($authorizationPlan['actorAdminRecordId'] ?? 0);
    }
}

if (!function_exists('red_addon_provider_contact_claim_plan')) {
    function red_addon_provider_contact_claim_plan(
        $connection,
        array $package,
        array $catalog,
        $actorAdminRecordId,
        array $readiness,
        array $prepared,
        $evaluatedAtUtc,
        $lockRows = false
    ) {
        $result = red_addon_provider_contact_claim_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result['actorAdminRecordId'] = $actorAdminRecordId;
        if (!($connection instanceof mysqli) || !is_bool($lockRows)) {
            $result['errors'][] = 'claim_evidence_refused';
            return $result;
        }

        $authorizationPlan = red_addon_provider_contact_authorization_plan(
            $connection,
            $package,
            $catalog,
            $actorAdminRecordId,
            $readiness,
            $prepared,
            $evaluatedAtUtc
        );
        if (empty($authorizationPlan['ready'])) {
            $result['status'] = 'authorization_refused';
            $result['errors'] = $authorizationPlan['errors'] ?? [
                'authorization_refused',
            ];
            return $result;
        }

        foreach ([
            'packageId', 'packageVersion', 'lifecycleState',
            'ownerSubjectSha256', 'planSha256', 'authorizationSha256',
            'authorizationNonceSha256', 'authorizationStateSha256',
            'issuedAtUtc', 'expiresAtUtc', 'maximumAttempts',
            'ownerAuthorityRevalidated',
        ] as $key) {
            $result[$key] = $authorizationPlan[$key];
        }
        $result['authorizationActionId'] =
            red_addon_provider_contact_action_id(
                $result['authorizationNonceSha256']
            );
        $result['claimActionId'] = red_addon_provider_contact_claim_action_id(
            $result['authorizationNonceSha256']
        );
        if ($result['authorizationActionId'] === ''
            || $result['claimActionId'] === ''
        ) {
            $result['errors'][] = 'claim_identity_refused';
            return $result;
        }

        $authorizationRow = red_addon_provider_contact_claim_execution_row(
            $connection,
            $result['packageId'],
            $result['authorizationActionId'],
            $lockRows
        );
        if (empty($authorizationRow['valid'])
            || empty($authorizationRow['found'])
            || !is_array($authorizationRow['row'])
            || !red_addon_provider_contact_claim_authorization_matches(
                $authorizationRow['row'],
                $authorizationPlan
            )
        ) {
            $result['status'] = 'authorization_record_refused';
            $result['errors'][] = 'authorization_record_refused';
            return $result;
        }
        $result['authorizationRecorded'] = true;

        $claimRow = red_addon_provider_contact_claim_execution_row(
            $connection,
            $result['packageId'],
            $result['claimActionId'],
            $lockRows
        );
        if (empty($claimRow['valid'])) {
            $result['status'] = 'claim_state_refused';
            $result['errors'][] = 'claim_state_refused';
            return $result;
        }
        if (!empty($claimRow['found'])) {
            $result['status'] = 'attempt_already_claimed';
            $result['errors'][] = 'attempt_already_claimed';
            return $result;
        }

        $stateMaterial = [
            'schema' => 1,
            'purpose' => 'provider-contact-attempt-claim',
            'packageId' => $result['packageId'],
            'packageVersion' => $result['packageVersion'],
            'actorAdminRecordId' => $actorAdminRecordId,
            'ownerSubjectSha256' => $result['ownerSubjectSha256'],
            'planSha256' => $result['planSha256'],
            'authorizationSha256' => $result['authorizationSha256'],
            'authorizationNonceSha256' =>
                $result['authorizationNonceSha256'],
            'authorizationStateSha256' =>
                $result['authorizationStateSha256'],
            'issuedAtUtc' => $result['issuedAtUtc'],
            'expiresAtUtc' => $result['expiresAtUtc'],
            'maximumAttempts' => 1,
            'attemptClaimed' => true,
            'executionPerformed' => false,
        ];
        $encoded = red_addon_provider_contact_encode($stateMaterial);
        if (!is_string($encoded)) {
            $result['status'] = 'claim_state_encoding_failed';
            $result['errors'][] = 'claim_state_encoding_failed';
            return $result;
        }
        $result['claimStateSha256'] = hash('sha256', $encoded);
        $result['claimAvailable'] = true;
        $result['status'] = 'ready';
        $result['ready'] = true;
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_provider_contact_claim_reserve')) {
    function red_addon_provider_contact_claim_reserve(
        $connection,
        array $plan
    ) {
        if (!red_addon_provider_contact_sha256(
            $plan['claimStateSha256'] ?? null
        )) {
            return 'failed';
        }
        $targetRecordId = 1;
        try {
            $statement = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Addon_Admin_Action_Executions (
                    PackageID, ActionID, TargetRecordID, PlanSHA256,
                    ContractSHA256, PreviousStateSHA256, StateSHA256,
                    ActorAdminRecordID
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$statement) {
                return 'failed';
            }
            mysqli_stmt_bind_param(
                $statement,
                'ssissssi',
                $plan['packageId'],
                $plan['claimActionId'],
                $targetRecordId,
                $plan['planSha256'],
                $plan['authorizationSha256'],
                $plan['authorizationStateSha256'],
                $plan['claimStateSha256'],
                $plan['actorAdminRecordId']
            );
            $inserted = mysqli_stmt_execute($statement);
            $errno = mysqli_stmt_errno($statement);
            mysqli_stmt_close($statement);
            if ($inserted) {
                return 'reserved';
            }
            return $errno === 1062 ? 'duplicate' : 'failed';
        } catch (mysqli_sql_exception $exception) {
            return (int) $exception->getCode() === 1062
                ? 'duplicate'
                : 'failed';
        } catch (Throwable $throwable) {
            return 'failed';
        }
    }
}

if (!function_exists('red_addon_provider_contact_claim_audit_record')) {
    function red_addon_provider_contact_claim_audit_record(
        $connection,
        array $plan
    ) {
        return red_addon_install_audit_record(
            $connection,
            'addon.action.completed',
            $plan['packageId'],
            $plan['packageVersion'],
            $plan['actorAdminRecordId'],
            'succeeded',
            'provider_contact_attempt_claimed'
        );
    }
}

if (!function_exists('red_addon_provider_contact_claim')) {
    function red_addon_provider_contact_claim(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $readiness,
        array $prepared,
        $expectedAuthorizationSha256,
        $expectedAuthorizationStateSha256,
        $expectedClaimStateSha256,
        $evaluatedAtUtc = null,
        $auditRecorder = null
    ) {
        $result = red_addon_provider_contact_claim_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || $projectRoot === ''
            || $actorAdminRecordId <= 0
            || !red_addon_provider_contact_sha256(
                $expectedAuthorizationSha256
            )
            || !red_addon_provider_contact_sha256(
                $expectedAuthorizationStateSha256
            )
            || !red_addon_provider_contact_sha256(
                $expectedClaimStateSha256
            )
            || red_addon_provider_contact_transaction_active($connection)
        ) {
            return $result;
        }
        $evaluatedAtUtc = $evaluatedAtUtc === null
            ? gmdate('Y-m-d\TH:i:s\Z')
            : $evaluatedAtUtc;
        $auditRecorder = $auditRecorder
            ?? 'red_addon_provider_contact_claim_audit_record';
        if (!is_callable($auditRecorder)) {
            return $result;
        }

        if (!red_addon_lifecycle_lock($connection)) {
            $result['status'] = 'lifecycle_locked';
            return $result;
        }
        $packageId = 'redcms.store-lite-stripe-checkout';
        $packageLocked = false;
        try {
            if (!red_addon_install_lock($connection, $packageId)) {
                $result['status'] = 'package_locked';
                return $result;
            }
            $packageLocked = true;
            $catalog = red_addon_discover($projectRoot, [
                'cmsVersion' => '5.1.0',
                'phpVersion' => PHP_VERSION,
            ]);
            $package = $catalog['packages'][$packageId] ?? null;
            if (empty($catalog['valid']) || !is_array($package)) {
                $result['status'] = 'package_invalid';
                return $result;
            }
            $plan = red_addon_provider_contact_claim_plan(
                $connection,
                $package,
                $catalog,
                $actorAdminRecordId,
                $readiness,
                $prepared,
                $evaluatedAtUtc
            );
            $result = $plan;
            if (empty($plan['ready'])) {
                return $result;
            }
            if (!hash_equals(
                $plan['authorizationSha256'],
                $expectedAuthorizationSha256
            ) || !hash_equals(
                $plan['authorizationStateSha256'],
                $expectedAuthorizationStateSha256
            ) || !hash_equals(
                $plan['claimStateSha256'],
                $expectedClaimStateSha256
            )) {
                $result['ready'] = false;
                $result['claimAvailable'] = false;
                $result['status'] = 'claim_changed';
                return $result;
            }
            if (!mysqli_begin_transaction($connection)) {
                $result['ready'] = false;
                $result['claimAvailable'] = false;
                $result['status'] = 'transaction_failed';
                return $result;
            }
            try {
                if (!red_addon_provider_contact_lock_state(
                    $connection,
                    $actorAdminRecordId
                )) {
                    throw new RuntimeException('authorization_lock_failed');
                }
                $lockedPlan = red_addon_provider_contact_claim_plan(
                    $connection,
                    $package,
                    $catalog,
                    $actorAdminRecordId,
                    $readiness,
                    $prepared,
                    $evaluatedAtUtc,
                    true
                );
                if (empty($lockedPlan['ready'])
                    || !hash_equals(
                        $expectedAuthorizationSha256,
                        $lockedPlan['authorizationSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedAuthorizationStateSha256,
                        $lockedPlan['authorizationStateSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedClaimStateSha256,
                        $lockedPlan['claimStateSha256'] ?? ''
                    )
                ) {
                    throw new RuntimeException(
                        ($lockedPlan['status'] ?? '')
                            === 'attempt_already_claimed'
                            ? 'attempt_already_claimed'
                            : 'claim_changed'
                    );
                }
                $reservation = red_addon_provider_contact_claim_reserve(
                    $connection,
                    $lockedPlan
                );
                if ($reservation !== 'reserved') {
                    throw new RuntimeException(
                        $reservation === 'duplicate'
                            ? 'attempt_already_claimed'
                            : 'claim_reservation_failed'
                    );
                }
                if (!$auditRecorder($connection, $lockedPlan)) {
                    throw new RuntimeException('audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('commit_failed');
                }
                $result = $lockedPlan;
                $result['status'] = 'claimed';
                $result['ready'] = false;
                $result['claimAvailable'] = false;
                $result['attemptClaimed'] = true;
                $result['auditRecorded'] = true;
                return $result;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result['ready'] = false;
                $result['claimAvailable'] = false;
                $result['status'] = $throwable->getMessage();
                $result['attemptClaimed'] = false;
                $result['auditRecorded'] = false;
                return $result;
            }
        } finally {
            if ($packageLocked) {
                red_addon_install_unlock($connection, $packageId);
            }
            red_addon_lifecycle_unlock($connection);
        }
    }
}

?>
