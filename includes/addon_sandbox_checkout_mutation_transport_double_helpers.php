<?php
/**
 * P3E-9C3A durable start/result runner for one core-owned transport double.
 *
 * The final double has no transport primitive. The runner records start before
 * invocation and records one bounded result afterward. No retry is authorized.
 */

require_once __DIR__ . '/addon_sandbox_checkout_mutation_claim_helpers.php';

if (!class_exists('RED_Addon_Checkout_Mutation_Transport_Double', false)) {
    final class RED_Addon_Checkout_Mutation_Transport_Double
    {
        private string $mode;
        private int $callCount = 0;

        public function __construct(string $mode = 'completed')
        {
            if (!in_array($mode, ['completed', 'fault'], true)) {
                throw new InvalidArgumentException('Invalid transport-double mode.');
            }
            $this->mode = $mode;
        }

        public function execute(array $request): array
        {
            $this->callCount++;
            if ($this->mode === 'fault') {
                throw new RuntimeException('transport_double_fault');
            }
            $encoded = red_addon_provider_contact_encode($request);
            if (!is_string($encoded)) {
                throw new RuntimeException('transport_double_request_invalid');
            }
            return [
                'valid' => true,
                'outcome' => 'transport_double_completed',
                'simulationObserved' => true,
                'transportEvidenceSha256' => hash('sha256', $encoded),
                'responseBodyIncluded' => false,
                'responseHeadersIncluded' => false,
                'credentialIncluded' => false,
                'networkAccess' => false,
                'providerContact' => false,
                'providerMutation' => false,
                'checkoutCreation' => false,
                'payment' => false,
                'webhook' => false,
                'browserNavigation' => false,
                'storeLiteMutation' => false,
                'retryAuthorized' => false,
                'liveMode' => false,
                'clientDeployment' => false,
                'executionPerformed' => true,
                'errors' => [],
            ];
        }

        public function callCount(): int
        {
            return $this->callCount;
        }
    }
}

if (!function_exists('red_addon_checkout_mutation_transport_result')) {
    function red_addon_checkout_mutation_transport_result()
    {
        $result = red_addon_checkout_mutation_claim_result();
        $result['executionStartStateSha256'] = '';
        $result['outcomeStateSha256'] = '';
        $result['executionStartActionId'] = '';
        $result['outcomeActionId'] = '';
        $result['executionStartAvailable'] = false;
        $result['startAuditRecorded'] = false;
        $result['transportDoubleInvoked'] = false;
        $result['boundedOutcome'] = null;
        $result['outcomeRecorded'] = false;
        $result['outcomeAuditRecorded'] = false;
        return $result;
    }
}

if (!function_exists('red_addon_checkout_mutation_start_action_id')) {
    function red_addon_checkout_mutation_start_action_id($nonceSha256)
    {
        return red_addon_provider_contact_sha256($nonceSha256)
            ? 'sandbox-checkout-mutation-start.' . $nonceSha256
            : '';
    }
}

if (!function_exists('red_addon_checkout_mutation_outcome_action_id')) {
    function red_addon_checkout_mutation_outcome_action_id($nonceSha256)
    {
        return red_addon_provider_contact_sha256($nonceSha256)
            ? 'sandbox-checkout-mutation-result.' . $nonceSha256
            : '';
    }
}

if (!function_exists('red_addon_checkout_mutation_claim_row_matches')) {
    function red_addon_checkout_mutation_claim_row_matches(
        array $row,
        array $plan
    ) {
        return red_addon_checkout_synthetic_exact_keys($row, [
            'PlanSHA256', 'ContractSHA256', 'PreviousStateSHA256',
            'StateSHA256', 'ActorAdminRecordID',
        ])
            && hash_equals(
                (string) ($plan['syntheticPlanSha256'] ?? ''),
                (string) ($row['PlanSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($plan['authorizationSha256'] ?? ''),
                (string) ($row['ContractSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($plan['authorizationStateSha256'] ?? ''),
                (string) ($row['PreviousStateSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($plan['claimStateSha256'] ?? ''),
                (string) ($row['StateSHA256'] ?? '')
            )
            && (int) ($row['ActorAdminRecordID'] ?? 0)
                === (int) ($plan['actorAdminRecordId'] ?? 0);
    }
}

if (!function_exists('red_addon_checkout_mutation_start_state_sha256')) {
    function red_addon_checkout_mutation_start_state_sha256(array $plan)
    {
        $material = [
            'schema' => 1,
            'purpose' => 'sandbox-checkout-mutation-transport-double-start',
            'packageId' => $plan['packageId'] ?? '',
            'packageVersion' => $plan['packageVersion'] ?? '',
            'storePackageId' => $plan['storePackageId'] ?? '',
            'storePackageVersion' => $plan['storePackageVersion'] ?? '',
            'actorAdminRecordId' => (int) ($plan['actorAdminRecordId'] ?? 0),
            'ownerSubjectSha256' => $plan['ownerSubjectSha256'] ?? '',
            'syntheticPlanSha256' => $plan['syntheticPlanSha256'] ?? '',
            'inputSha256' => $plan['inputSha256'] ?? '',
            'authorizationSha256' => $plan['authorizationSha256'] ?? '',
            'authorizationStateSha256' =>
                $plan['authorizationStateSha256'] ?? '',
            'claimStateSha256' => $plan['claimStateSha256'] ?? '',
            'authorizationNonceSha256' =>
                $plan['authorizationNonceSha256'] ?? '',
            'operation' => 'checkout.create-sandbox-transport-double',
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
            'executionStarted' => true,
        ];
        foreach ([
            'ownerSubjectSha256', 'syntheticPlanSha256', 'inputSha256',
            'authorizationSha256', 'authorizationStateSha256',
            'claimStateSha256', 'authorizationNonceSha256',
        ] as $key) {
            if (!red_addon_provider_contact_sha256($material[$key])) {
                return '';
            }
        }
        if ($material['packageId'] !== 'redcms.store-lite-stripe-checkout'
            || $material['packageVersion'] !== '0.1.5'
            || $material['storePackageId'] !== 'redcms.store-lite'
            || $material['storePackageVersion'] !== '0.1.35'
            || $material['actorAdminRecordId'] <= 0
        ) {
            return '';
        }
        $encoded = red_addon_provider_contact_encode($material);
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_checkout_mutation_transport_plan')) {
    function red_addon_checkout_mutation_transport_plan(
        $connection,
        array $package,
        array $catalog,
        $actorAdminRecordId,
        array $input,
        array $prepared,
        $evaluatedAtUtc,
        $lockRows = false
    ) {
        $result = red_addon_checkout_mutation_transport_result();
        if (!($connection instanceof mysqli) || !is_bool($lockRows)) {
            $result['errors'][] = 'execution_evidence_refused';
            return $result;
        }
        $authorizationPlan = red_addon_checkout_mutation_authorization_plan(
            $connection,
            $package,
            $catalog,
            $actorAdminRecordId,
            $input,
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
            'packageId', 'packageVersion', 'storePackageId',
            'storePackageVersion', 'lifecycleState', 'actorAdminRecordId',
            'ownerSubjectSha256', 'syntheticPlanSha256', 'inputSha256',
            'authorizationSha256', 'authorizationNonceSha256',
            'authorizationStateSha256', 'issuedAtUtc', 'expiresAtUtc',
            'maximumAttempts', 'ownerAuthorityRevalidated',
            'orderAuthorityRevalidated',
        ] as $key) {
            $result[$key] = $authorizationPlan[$key];
        }
        $result['authorizationActionId'] =
            red_addon_checkout_mutation_action_id(
                $result['authorizationNonceSha256']
            );
        $result['claimActionId'] =
            red_addon_checkout_mutation_claim_action_id(
                $result['authorizationNonceSha256']
            );
        $result['executionStartActionId'] =
            red_addon_checkout_mutation_start_action_id(
                $result['authorizationNonceSha256']
            );
        $result['outcomeActionId'] =
            red_addon_checkout_mutation_outcome_action_id(
                $result['authorizationNonceSha256']
            );

        $authorizationRow =
            red_addon_checkout_mutation_claim_execution_row(
                $connection,
                $result['packageId'],
                $result['authorizationActionId'],
                $lockRows
            );
        if (empty($authorizationRow['valid'])
            || empty($authorizationRow['found'])
            || !is_array($authorizationRow['row'])
            || !red_addon_checkout_mutation_claim_authorization_matches(
                $authorizationRow['row'],
                $authorizationPlan
            )
        ) {
            $result['status'] = 'authorization_record_refused';
            $result['errors'][] = 'authorization_record_refused';
            return $result;
        }
        $result['authorizationRecorded'] = true;
        $result['claimStateSha256'] =
            red_addon_checkout_mutation_claim_state_sha256($result);
        if (!red_addon_provider_contact_sha256($result['claimStateSha256'])) {
            $result['status'] = 'claim_state_encoding_failed';
            $result['errors'][] = 'claim_state_encoding_failed';
            return $result;
        }
        $claimRow = red_addon_checkout_mutation_claim_execution_row(
            $connection,
            $result['packageId'],
            $result['claimActionId'],
            $lockRows
        );
        if (empty($claimRow['valid'])
            || empty($claimRow['found'])
            || !is_array($claimRow['row'])
            || !red_addon_checkout_mutation_claim_row_matches(
                $claimRow['row'],
                $result
            )
        ) {
            $result['status'] = 'claim_record_refused';
            $result['errors'][] = 'claim_record_refused';
            return $result;
        }
        $result['attemptClaimed'] = true;

        $startRow = red_addon_checkout_mutation_claim_execution_row(
            $connection,
            $result['packageId'],
            $result['executionStartActionId'],
            $lockRows
        );
        $outcomeRow = red_addon_checkout_mutation_claim_execution_row(
            $connection,
            $result['packageId'],
            $result['outcomeActionId'],
            $lockRows
        );
        if (empty($startRow['valid']) || empty($outcomeRow['valid'])) {
            $result['status'] = 'execution_state_refused';
            $result['errors'][] = 'execution_state_refused';
            return $result;
        }
        if (!empty($startRow['found']) || !empty($outcomeRow['found'])) {
            $result['status'] = 'execution_already_started';
            $result['errors'][] = 'execution_already_started';
            return $result;
        }
        $result['executionStartStateSha256'] =
            red_addon_checkout_mutation_start_state_sha256($result);
        if (!red_addon_provider_contact_sha256(
            $result['executionStartStateSha256']
        )) {
            $result['status'] = 'execution_start_encoding_failed';
            $result['errors'][] = 'execution_start_encoding_failed';
            return $result;
        }
        $result['claimAvailable'] = false;
        $result['executionStartAvailable'] = true;
        $result['status'] = 'ready';
        $result['ready'] = true;
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_checkout_mutation_transport_reserve')) {
    function red_addon_checkout_mutation_transport_reserve(
        $connection,
        array $plan,
        $actionId,
        $previousStateSha256,
        $stateSha256
    ) {
        if (!is_string($actionId)
            || $actionId === ''
            || !red_addon_provider_contact_sha256($previousStateSha256)
            || !red_addon_provider_contact_sha256($stateSha256)
        ) {
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
                $actionId,
                $targetRecordId,
                $plan['syntheticPlanSha256'],
                $plan['authorizationSha256'],
                $previousStateSha256,
                $stateSha256,
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

if (!function_exists('red_addon_checkout_mutation_start_row_matches')) {
    function red_addon_checkout_mutation_start_row_matches(
        array $row,
        array $plan
    ) {
        return red_addon_checkout_synthetic_exact_keys($row, [
            'PlanSHA256', 'ContractSHA256', 'PreviousStateSHA256',
            'StateSHA256', 'ActorAdminRecordID',
        ])
            && hash_equals($plan['syntheticPlanSha256'], $row['PlanSHA256'])
            && hash_equals($plan['authorizationSha256'], $row['ContractSHA256'])
            && hash_equals($plan['claimStateSha256'], $row['PreviousStateSHA256'])
            && hash_equals(
                $plan['executionStartStateSha256'],
                $row['StateSHA256']
            )
            && (int) $row['ActorAdminRecordID']
                === (int) $plan['actorAdminRecordId'];
    }
}

if (!function_exists('red_addon_checkout_mutation_transport_outcome')) {
    function red_addon_checkout_mutation_transport_outcome(
        array $data,
        $invoked
    ) {
        $indeterminate = [
            'valid' => true,
            'outcome' => 'transport_double_indeterminate',
            'simulationObserved' => false,
            'transportEvidenceSha256' => '',
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'credentialIncluded' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'browserNavigation' => false,
            'storeLiteMutation' => false,
            'retryAuthorized' => false,
            'liveMode' => false,
            'clientDeployment' => false,
            'executionPerformed' => (bool) $invoked,
            'errors' => ['transport_double_indeterminate'],
        ];
        if (!$invoked
            || !red_addon_checkout_synthetic_exact_keys($data, array_keys($indeterminate))
            || ($data['valid'] ?? null) !== true
            || ($data['outcome'] ?? null) !== 'transport_double_completed'
            || ($data['simulationObserved'] ?? null) !== true
            || !red_addon_provider_contact_sha256(
                $data['transportEvidenceSha256'] ?? null
            )
            || ($data['responseBodyIncluded'] ?? null) !== false
            || ($data['responseHeadersIncluded'] ?? null) !== false
            || ($data['credentialIncluded'] ?? null) !== false
            || ($data['networkAccess'] ?? null) !== false
            || ($data['providerContact'] ?? null) !== false
            || ($data['providerMutation'] ?? null) !== false
            || ($data['checkoutCreation'] ?? null) !== false
            || ($data['payment'] ?? null) !== false
            || ($data['webhook'] ?? null) !== false
            || ($data['browserNavigation'] ?? null) !== false
            || ($data['storeLiteMutation'] ?? null) !== false
            || ($data['retryAuthorized'] ?? null) !== false
            || ($data['liveMode'] ?? null) !== false
            || ($data['clientDeployment'] ?? null) !== false
            || ($data['executionPerformed'] ?? null) !== true
            || ($data['errors'] ?? null) !== []
        ) {
            return $indeterminate;
        }
        return $data;
    }
}

if (!function_exists('red_addon_checkout_mutation_outcome_state_sha256')) {
    function red_addon_checkout_mutation_outcome_state_sha256(
        array $plan,
        array $outcome
    ) {
        $material = [
            'schema' => 1,
            'purpose' => 'sandbox-checkout-mutation-transport-double-result',
            'packageId' => $plan['packageId'] ?? '',
            'packageVersion' => $plan['packageVersion'] ?? '',
            'actorAdminRecordId' => (int) ($plan['actorAdminRecordId'] ?? 0),
            'syntheticPlanSha256' => $plan['syntheticPlanSha256'] ?? '',
            'authorizationSha256' => $plan['authorizationSha256'] ?? '',
            'claimStateSha256' => $plan['claimStateSha256'] ?? '',
            'executionStartStateSha256' =>
                $plan['executionStartStateSha256'] ?? '',
            'outcome' => $outcome,
        ];
        $encoded = red_addon_provider_contact_encode($material);
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_checkout_mutation_start_audit')) {
    function red_addon_checkout_mutation_start_audit($connection, array $plan)
    {
        return red_addon_install_audit_record(
            $connection,
            'addon.action.completed',
            $plan['packageId'],
            $plan['packageVersion'],
            $plan['actorAdminRecordId'],
            'succeeded',
            'sandbox_checkout_mutation_execution_started'
        );
    }
}

if (!function_exists('red_addon_checkout_mutation_outcome_audit')) {
    function red_addon_checkout_mutation_outcome_audit(
        $connection,
        array $plan,
        array $outcome
    ) {
        $detail = ($outcome['outcome'] ?? '') === 'transport_double_completed'
            ? 'sandbox_checkout_mutation_transport_double_completed'
            : 'sandbox_checkout_mutation_transport_double_indeterminate';
        return red_addon_install_audit_record(
            $connection,
            'addon.action.completed',
            $plan['packageId'],
            $plan['packageVersion'],
            $plan['actorAdminRecordId'],
            'succeeded',
            $detail
        );
    }
}

if (!function_exists('red_addon_checkout_mutation_execute_transport_double')) {
    function red_addon_checkout_mutation_execute_transport_double(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $input,
        array $prepared,
        $expectedAuthorizationSha256,
        $expectedAuthorizationStateSha256,
        $expectedClaimStateSha256,
        $expectedExecutionStartStateSha256,
        RED_Addon_Checkout_Mutation_Transport_Double $transportDouble,
        $evaluatedAtUtc = null,
        $startAuditRecorder = null,
        $outcomeAuditRecorder = null
    ) {
        $result = red_addon_checkout_mutation_transport_result();
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || $projectRoot === ''
            || (int) $actorAdminRecordId <= 0
            || !red_addon_provider_contact_sha256($expectedAuthorizationSha256)
            || !red_addon_provider_contact_sha256(
                $expectedAuthorizationStateSha256
            )
            || !red_addon_provider_contact_sha256($expectedClaimStateSha256)
            || !red_addon_provider_contact_sha256(
                $expectedExecutionStartStateSha256
            )
            || red_addon_provider_contact_transaction_active($connection)
        ) {
            return $result;
        }
        $evaluatedAtUtc = $evaluatedAtUtc === null
            ? gmdate('Y-m-d\TH:i:s\Z')
            : $evaluatedAtUtc;
        $startAuditRecorder = $startAuditRecorder
            ?? 'red_addon_checkout_mutation_start_audit';
        $outcomeAuditRecorder = $outcomeAuditRecorder
            ?? 'red_addon_checkout_mutation_outcome_audit';
        if (!is_callable($startAuditRecorder)
            || !is_callable($outcomeAuditRecorder)
        ) {
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
            if (!mysqli_begin_transaction($connection)) {
                $result['status'] = 'transaction_failed';
                return $result;
            }
            try {
                if (!red_addon_checkout_mutation_lock_state(
                    $connection,
                    (int) $actorAdminRecordId
                )) {
                    throw new RuntimeException('execution_lock_failed');
                }
                $plan = red_addon_checkout_mutation_transport_plan(
                    $connection,
                    $package,
                    $catalog,
                    (int) $actorAdminRecordId,
                    $input,
                    $prepared,
                    $evaluatedAtUtc,
                    true
                );
                if (empty($plan['ready'])
                    || !hash_equals(
                        $expectedAuthorizationSha256,
                        $plan['authorizationSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedAuthorizationStateSha256,
                        $plan['authorizationStateSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedClaimStateSha256,
                        $plan['claimStateSha256'] ?? ''
                    )
                    || !hash_equals(
                        $expectedExecutionStartStateSha256,
                        $plan['executionStartStateSha256'] ?? ''
                    )
                ) {
                    throw new RuntimeException(
                        ($plan['status'] ?? '') === 'execution_already_started'
                            ? 'execution_already_started'
                            : 'execution_changed'
                    );
                }
                $reserved = red_addon_checkout_mutation_transport_reserve(
                    $connection,
                    $plan,
                    $plan['executionStartActionId'],
                    $plan['claimStateSha256'],
                    $plan['executionStartStateSha256']
                );
                if ($reserved !== 'reserved') {
                    throw new RuntimeException(
                        $reserved === 'duplicate'
                            ? 'execution_already_started'
                            : 'execution_start_failed'
                    );
                }
                if (!$startAuditRecorder($connection, $plan)) {
                    throw new RuntimeException('execution_start_audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('execution_start_commit_failed');
                }
                $result = $plan;
                $result['ready'] = false;
                $result['executionStartAvailable'] = false;
                $result['executionStarted'] = true;
                $result['startAuditRecorded'] = true;
                $result['status'] = 'execution_started';
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result = isset($plan) && is_array($plan) ? $plan : $result;
                $result['ready'] = false;
                $result['executionStartAvailable'] = false;
                $result['status'] = $throwable->getMessage();
                $result['executionStarted'] = false;
                $result['startAuditRecorded'] = false;
                return $result;
            }

            $invoked = false;
            $data = [];
            try {
                $invoked = true;
                $data = $transportDouble->execute([
                    'packageId' => $plan['packageId'],
                    'packageVersion' => $plan['packageVersion'],
                    'syntheticPlanSha256' => $plan['syntheticPlanSha256'],
                    'inputSha256' => $plan['inputSha256'],
                    'claimStateSha256' => $plan['claimStateSha256'],
                    'executionStartStateSha256' =>
                        $plan['executionStartStateSha256'],
                    'operation' =>
                        'checkout.create-sandbox-transport-double',
                ]);
            } catch (Throwable $throwable) {
                $data = [];
            }
            $outcome = red_addon_checkout_mutation_transport_outcome(
                is_array($data) ? $data : [],
                $invoked
            );
            $result['transportDoubleInvoked'] = $invoked;
            $result['boundedOutcome'] = $outcome;
            $result['executionPerformed'] =
                ($outcome['executionPerformed'] ?? false) === true;
            $result['outcomeStateSha256'] =
                red_addon_checkout_mutation_outcome_state_sha256(
                    $plan,
                    $outcome
                );
            if (!red_addon_provider_contact_sha256(
                $result['outcomeStateSha256']
            )) {
                $result['status'] = 'outcome_encoding_failed';
                return $result;
            }
            if (!mysqli_begin_transaction($connection)) {
                $result['status'] = 'outcome_transaction_failed';
                return $result;
            }
            try {
                $startRow = red_addon_checkout_mutation_claim_execution_row(
                    $connection,
                    $plan['packageId'],
                    $plan['executionStartActionId'],
                    true
                );
                $outcomeRow = red_addon_checkout_mutation_claim_execution_row(
                    $connection,
                    $plan['packageId'],
                    $plan['outcomeActionId'],
                    true
                );
                if (empty($startRow['valid'])
                    || empty($startRow['found'])
                    || !is_array($startRow['row'])
                    || !red_addon_checkout_mutation_start_row_matches(
                        $startRow['row'],
                        $plan
                    )
                    || empty($outcomeRow['valid'])
                    || !empty($outcomeRow['found'])
                ) {
                    throw new RuntimeException('outcome_state_changed');
                }
                $reserved = red_addon_checkout_mutation_transport_reserve(
                    $connection,
                    $plan,
                    $plan['outcomeActionId'],
                    $plan['executionStartStateSha256'],
                    $result['outcomeStateSha256']
                );
                if ($reserved !== 'reserved') {
                    throw new RuntimeException('outcome_reservation_failed');
                }
                if (!$outcomeAuditRecorder($connection, $plan, $outcome)) {
                    throw new RuntimeException('outcome_audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('outcome_commit_failed');
                }
                $result['valid'] = true;
                $result['status'] = $outcome['outcome'];
                $result['outcomeRecorded'] = true;
                $result['outcomeAuditRecorded'] = true;
                return $result;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result['status'] = $throwable->getMessage();
                $result['outcomeRecorded'] = false;
                $result['outcomeAuditRecorded'] = false;
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
