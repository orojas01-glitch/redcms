<?php
/** C4C3A durable start/result runner for a core-owned provider double. */

require_once __DIR__
    . '/addon_payment_adapter_wompi_merchant_read_preflight_helpers.php';
require_once __DIR__
    . '/addon_payment_adapter_wompi_transport_double_helpers.php';

if (!class_exists('RED_Addon_Wompi_Merchant_Read_Provider_Double', false)) {
    final class RED_Addon_Wompi_Merchant_Read_Provider_Double
    {
        private string $mode;
        private int $callCount = 0;

        public function __construct(string $mode = 'completed')
        {
            if (!in_array($mode, ['completed', 'fault', 'malformed'], true)) {
                throw new InvalidArgumentException('Invalid provider double mode.');
            }
            $this->mode = $mode;
        }

        public function execute(array $request): array
        {
            $this->callCount++;
            if ($this->mode === 'fault') {
                throw new RuntimeException('merchant_provider_double_fault');
            }
            if ($this->mode === 'malformed') {
                return ['valid' => true, 'status' => 'unexpected'];
            }
            if (!red_addon_wompi_merchant_durable_request_valid($request)) {
                throw new RuntimeException('merchant_provider_request_invalid');
            }
            $requestSha256 = red_addon_wompi_claim_hash($request);
            return [
                'valid' => true,
                'status' => 'merchant_provider_double_completed',
                'simulationObserved' => true,
                'requestSha256' => $requestSha256,
                'contractsSha256' => red_addon_wompi_claim_hash([
                    'schema' => 1,
                    'purpose' => 'wompi-merchant-provider-double-contracts',
                    'requestSha256' => $requestSha256,
                ]),
                'transportEvidenceSha256' => red_addon_wompi_claim_hash([
                    'schema' => 1,
                    'purpose' => 'wompi-merchant-provider-double-transport',
                    'requestSha256' => $requestSha256,
                    'state' => 'simulated_read_observed',
                ]),
                'responseBodyIncluded' => false,
                'responseHeadersIncluded' => false,
                'publicKeyIncluded' => false,
                'rawTokensReturned' => false,
                'networkAccess' => false,
                'providerContact' => false,
                'providerMutation' => false,
                'transactionCreation' => false,
                'payment' => false,
                'eventRegistration' => false,
                'orderMutation' => false,
                'retryAuthorized' => false,
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

if (!function_exists('red_addon_wompi_merchant_durable_result')) {
    function red_addon_wompi_merchant_durable_result($status = 'invalid')
    {
        $result = red_addon_wompi_merchant_read_result($status);
        $result['authorizationNonceSha256'] = '';
        $result['authorizationSha256'] = '';
        $result['issuedAtEpoch'] = 0;
        $result['expiresAtEpoch'] = 0;
        $result['planSha256'] = '';
        $result['requestSha256'] = '';
        $result['executionStartStateSha256'] = '';
        $result['outcomeEvidenceSha256'] = '';
        $result['outcomeStateSha256'] = '';
        $result['executionStartActionId'] = '';
        $result['outcomeActionId'] = '';
        $result['executionStartAvailable'] = false;
        $result['executionStarted'] = false;
        $result['startAuditRecorded'] = false;
        $result['providerDoubleInvoked'] = false;
        $result['executionPerformed'] = false;
        $result['boundedOutcome'] = null;
        $result['outcomeRecorded'] = false;
        $result['outcomeAuditRecorded'] = false;
        $result['replayProtectionActive'] = false;
        return $result;
    }
}

if (!function_exists('red_addon_wompi_merchant_durable_authorize')) {
    function red_addon_wompi_merchant_durable_authorize(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        $authorizationNonceSha256,
        $issuedAtEpoch
    ) {
        $result = [
            'valid' => false,
            'status' => 'authorization_refused',
            'schema' => 1,
            'purpose' => 'wompi-merchant-read-provider-double-authorization',
            'packageId' => 'redcms.store-lite-wompi',
            'packageVersion' => '0.1.5',
            'storePackageId' => 'redcms.store-lite',
            'storePackageVersion' => '0.1.35',
            'actorAdminRecordId' => (int) $actorAdminRecordId,
            'clientScopeSha256' => '',
            'databaseSha256' => '',
            'actorSubjectSha256' => '',
            'publicKeySha256' => '',
            'settingStateSha256' => '',
            'referenceStateSha256' => '',
            'merchantPlanSha256' => '',
            'preflightSha256' => '',
            'authorizationNonceSha256' => is_string($authorizationNonceSha256)
                ? $authorizationNonceSha256 : '',
            'issuedAtEpoch' => is_int($issuedAtEpoch) ? $issuedAtEpoch : 0,
            'expiresAtEpoch' => is_int($issuedAtEpoch)
                ? $issuedAtEpoch + 900 : 0,
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
            'networkDisabled' => true,
            'providerDoubleOnly' => true,
            'realProviderContactAuthorized' => false,
            'providerMutationAuthorized' => false,
            'transactionCreationAuthorized' => false,
            'paymentAuthorized' => false,
            'eventRegistrationAuthorized' => false,
            'orderMutationAuthorized' => false,
            'authorizationSha256' => '',
            'errors' => [],
        ];
        if (!red_addon_wompi_claim_sha256($authorizationNonceSha256)
            || !is_int($issuedAtEpoch)
            || $issuedAtEpoch <= 0
        ) {
            $result['errors'] = ['authorization_refused'];
            return $result;
        }
        $preflight = red_addon_wompi_merchant_read_preflight(
            $connection,
            $projectRoot,
            $actorAdminRecordId
        );
        if (empty($preflight['ready'])) {
            $result['errors'] = ['preflight_refused'];
            return $result;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'publicKeySha256', 'settingStateSha256',
            'referenceStateSha256', 'merchantPlanSha256', 'preflightSha256',
        ] as $key) {
            $result[$key] = $preflight[$key];
        }
        $result['status'] = 'authorized_for_provider_double';
        $fingerprint = $result;
        unset($fingerprint['valid'], $fingerprint['authorizationSha256']);
        $result['authorizationSha256'] =
            red_addon_wompi_claim_hash($fingerprint);
        if (!red_addon_wompi_claim_sha256($result['authorizationSha256'])) {
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_wompi_merchant_durable_authorization_valid')) {
    function red_addon_wompi_merchant_durable_authorization_valid(
        array $authorization,
        $evaluatedAtEpoch
    ) {
        if (!is_int($evaluatedAtEpoch)
            || !red_addon_wompi_claim_exact_keys($authorization, [
                'valid', 'status', 'schema', 'purpose', 'packageId',
                'packageVersion', 'storePackageId', 'storePackageVersion',
                'actorAdminRecordId', 'clientScopeSha256', 'databaseSha256',
                'actorSubjectSha256', 'publicKeySha256',
                'settingStateSha256', 'referenceStateSha256',
                'merchantPlanSha256', 'preflightSha256',
                'authorizationNonceSha256', 'issuedAtEpoch',
                'expiresAtEpoch', 'maximumAttempts', 'retryAuthorized',
                'networkDisabled', 'providerDoubleOnly',
                'realProviderContactAuthorized',
                'providerMutationAuthorized',
                'transactionCreationAuthorized', 'paymentAuthorized',
                'eventRegistrationAuthorized', 'orderMutationAuthorized',
                'authorizationSha256', 'errors',
            ])
        ) {
            return false;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'publicKeySha256', 'settingStateSha256',
            'referenceStateSha256', 'merchantPlanSha256', 'preflightSha256',
            'authorizationNonceSha256', 'authorizationSha256',
        ] as $key) {
            if (!red_addon_wompi_claim_sha256($authorization[$key] ?? null)) {
                return false;
            }
        }
        $fingerprint = $authorization;
        unset($fingerprint['valid'], $fingerprint['authorizationSha256']);
        return ($authorization['valid'] ?? null) === true
            && ($authorization['status'] ?? null)
                === 'authorized_for_provider_double'
            && ($authorization['schema'] ?? null) === 1
            && ($authorization['purpose'] ?? null)
                === 'wompi-merchant-read-provider-double-authorization'
            && ($authorization['packageId'] ?? null)
                === 'redcms.store-lite-wompi'
            && ($authorization['packageVersion'] ?? null) === '0.1.5'
            && ($authorization['storePackageId'] ?? null)
                === 'redcms.store-lite'
            && ($authorization['storePackageVersion'] ?? null) === '0.1.35'
            && is_int($authorization['actorAdminRecordId'] ?? null)
            && $authorization['actorAdminRecordId'] > 0
            && is_int($authorization['issuedAtEpoch'] ?? null)
            && is_int($authorization['expiresAtEpoch'] ?? null)
            && $authorization['expiresAtEpoch']
                === $authorization['issuedAtEpoch'] + 900
            && $evaluatedAtEpoch >= $authorization['issuedAtEpoch']
            && $evaluatedAtEpoch <= $authorization['expiresAtEpoch']
            && ($authorization['maximumAttempts'] ?? null) === 1
            && ($authorization['retryAuthorized'] ?? null) === false
            && ($authorization['networkDisabled'] ?? null) === true
            && ($authorization['providerDoubleOnly'] ?? null) === true
            && ($authorization['realProviderContactAuthorized'] ?? null)
                === false
            && ($authorization['providerMutationAuthorized'] ?? null) === false
            && ($authorization['transactionCreationAuthorized'] ?? null)
                === false
            && ($authorization['paymentAuthorized'] ?? null) === false
            && ($authorization['eventRegistrationAuthorized'] ?? null) === false
            && ($authorization['orderMutationAuthorized'] ?? null) === false
            && ($authorization['errors'] ?? null) === []
            && hash_equals(
                $authorization['authorizationSha256'],
                red_addon_wompi_claim_hash($fingerprint)
            );
    }
}

if (!function_exists('red_addon_wompi_merchant_durable_action_id')) {
    function red_addon_wompi_merchant_durable_action_id($purpose, $nonce)
    {
        return in_array($purpose, ['start', 'result'], true)
            && red_addon_wompi_claim_sha256($nonce)
            ? 'wompi-merchant-read-provider-' . $purpose . '.' . $nonce
            : '';
    }
}

if (!function_exists('red_addon_wompi_merchant_durable_request')) {
    function red_addon_wompi_merchant_durable_request(array $plan)
    {
        $request = [
            'schema' => 1,
            'packageId' => $plan['packageId'] ?? '',
            'packageVersion' => $plan['packageVersion'] ?? '',
            'operation' => 'merchant.acceptance-contracts.provider-double',
            'transportMode' => 'core_provider_double',
            'clientScopeSha256' => $plan['clientScopeSha256'] ?? '',
            'databaseSha256' => $plan['databaseSha256'] ?? '',
            'actorSubjectSha256' => $plan['actorSubjectSha256'] ?? '',
            'publicKeySha256' => $plan['publicKeySha256'] ?? '',
            'settingStateSha256' => $plan['settingStateSha256'] ?? '',
            'referenceStateSha256' => $plan['referenceStateSha256'] ?? '',
            'merchantPlanSha256' => $plan['merchantPlanSha256'] ?? '',
            'preflightSha256' => $plan['preflightSha256'] ?? '',
            'authorizationSha256' => $plan['authorizationSha256'] ?? '',
            'attemptNumber' => 1,
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
            'networkDisabled' => true,
            'realProviderContactAuthorized' => false,
        ];
        return red_addon_wompi_merchant_durable_request_valid($request)
            ? $request : null;
    }
}

if (!function_exists('red_addon_wompi_merchant_durable_request_valid')) {
    function red_addon_wompi_merchant_durable_request_valid(array $request)
    {
        if (!red_addon_wompi_claim_exact_keys($request, [
            'schema', 'packageId', 'packageVersion', 'operation',
            'transportMode', 'clientScopeSha256', 'databaseSha256',
            'actorSubjectSha256', 'publicKeySha256', 'settingStateSha256',
            'referenceStateSha256', 'merchantPlanSha256',
            'preflightSha256', 'authorizationSha256', 'attemptNumber',
            'maximumAttempts', 'retryAuthorized', 'networkDisabled',
            'realProviderContactAuthorized',
        ])) {
            return false;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'publicKeySha256', 'settingStateSha256',
            'referenceStateSha256', 'merchantPlanSha256',
            'preflightSha256', 'authorizationSha256',
        ] as $key) {
            if (!red_addon_wompi_claim_sha256($request[$key] ?? null)) {
                return false;
            }
        }
        return ($request['schema'] ?? null) === 1
            && ($request['packageId'] ?? null) === 'redcms.store-lite-wompi'
            && ($request['packageVersion'] ?? null) === '0.1.5'
            && ($request['operation'] ?? null)
                === 'merchant.acceptance-contracts.provider-double'
            && ($request['transportMode'] ?? null) === 'core_provider_double'
            && ($request['attemptNumber'] ?? null) === 1
            && ($request['maximumAttempts'] ?? null) === 1
            && ($request['retryAuthorized'] ?? null) === false
            && ($request['networkDisabled'] ?? null) === true
            && ($request['realProviderContactAuthorized'] ?? null) === false;
    }
}

if (!function_exists('red_addon_wompi_merchant_durable_plan')) {
    function red_addon_wompi_merchant_durable_plan(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $authorization,
        $evaluatedAtEpoch,
        $lockRows = false
    ) {
        $result = red_addon_wompi_merchant_durable_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result['actorAdminRecordId'] = $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || !is_bool($lockRows)
            || $actorAdminRecordId <= 0
            || !red_addon_wompi_merchant_durable_authorization_valid(
                $authorization,
                $evaluatedAtEpoch
            )
            || $authorization['actorAdminRecordId'] !== $actorAdminRecordId
        ) {
            $result['errors'][] = 'authorization_refused';
            return $result;
        }
        $preflight = red_addon_wompi_merchant_read_preflight(
            $connection,
            $projectRoot,
            $actorAdminRecordId
        );
        if (empty($preflight['ready'])) {
            $result['errors'][] = 'current_state_refused';
            return $result;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'publicKeySha256', 'settingStateSha256',
            'referenceStateSha256', 'merchantPlanSha256', 'preflightSha256',
        ] as $key) {
            if (!hash_equals($authorization[$key], $preflight[$key])) {
                $result['errors'][] = 'current_state_changed';
                return $result;
            }
            $result[$key] = $preflight[$key];
        }
        $result['packageVersion'] = '0.1.5';
        $result['storePackageVersion'] = '0.1.35';
        $result['lifecycleState'] = 'enabled';
        $result['authorizationNonceSha256'] =
            $authorization['authorizationNonceSha256'];
        $result['authorizationSha256'] =
            $authorization['authorizationSha256'];
        $result['issuedAtEpoch'] = $authorization['issuedAtEpoch'];
        $result['expiresAtEpoch'] = $authorization['expiresAtEpoch'];
        $result['planSha256'] = $preflight['preflightSha256'];
        $result['executionStartActionId'] =
            red_addon_wompi_merchant_durable_action_id(
                'start',
                $authorization['authorizationNonceSha256']
            );
        $result['outcomeActionId'] =
            red_addon_wompi_merchant_durable_action_id(
                'result',
                $authorization['authorizationNonceSha256']
            );
        $startRow = red_addon_wompi_transport_execution_row(
            $connection,
            $result['executionStartActionId'],
            $lockRows
        );
        $outcomeRow = red_addon_wompi_transport_execution_row(
            $connection,
            $result['outcomeActionId'],
            $lockRows
        );
        if (empty($startRow['valid']) || empty($outcomeRow['valid'])) {
            $result['errors'][] = 'execution_state_refused';
            return $result;
        }
        if (!empty($startRow['found']) || !empty($outcomeRow['found'])) {
            $result['status'] = 'execution_already_started';
            $result['errors'][] = 'execution_already_started';
            $result['replayProtectionActive'] = true;
            return $result;
        }
        $request = red_addon_wompi_merchant_durable_request($result);
        if (!is_array($request)) {
            $result['errors'][] = 'request_encoding_failed';
            return $result;
        }
        $result['requestSha256'] = red_addon_wompi_claim_hash($request);
        $result['executionStartStateSha256'] =
            red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-merchant-read-provider-double-start',
                'packageId' => $result['packageId'],
                'packageVersion' => $result['packageVersion'],
                'actorAdminRecordId' => $actorAdminRecordId,
                'authorizationSha256' => $result['authorizationSha256'],
                'requestSha256' => $result['requestSha256'],
                'maximumAttempts' => 1,
                'retryAuthorized' => false,
                'executionStarted' => true,
            ]);
        if (!red_addon_wompi_claim_sha256($result['requestSha256'])
            || !red_addon_wompi_claim_sha256(
                $result['executionStartStateSha256']
            )
        ) {
            $result['errors'][] = 'execution_start_encoding_failed';
            return $result;
        }
        $result['valid'] = true;
        $result['ready'] = true;
        $result['status'] = 'ready';
        $result['ownerAuthorityRevalidated'] = true;
        $result['orderAuthorityRevalidated'] = true;
        $result['packageStateRevalidated'] = true;
        $result['settingStateRevalidated'] = true;
        $result['sealedDoubleAvailable'] = true;
        $result['executionStartAvailable'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_wompi_merchant_durable_outcome')) {
    function red_addon_wompi_merchant_durable_outcome(array $data, $invoked)
    {
        $indeterminate = [
            'valid' => true,
            'status' => 'merchant_provider_double_indeterminate',
            'simulationObserved' => false,
            'requestSha256' => '',
            'contractsSha256' => '',
            'transportEvidenceSha256' => '',
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'publicKeyIncluded' => false,
            'rawTokensReturned' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'transactionCreation' => false,
            'payment' => false,
            'eventRegistration' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'executionPerformed' => (bool) $invoked,
            'errors' => ['merchant_provider_double_indeterminate'],
        ];
        if (!$invoked
            || !red_addon_wompi_claim_exact_keys(
                $data,
                array_keys($indeterminate)
            )
            || ($data['valid'] ?? null) !== true
            || ($data['status'] ?? null)
                !== 'merchant_provider_double_completed'
            || ($data['simulationObserved'] ?? null) !== true
            || !red_addon_wompi_claim_sha256($data['requestSha256'] ?? null)
            || !red_addon_wompi_claim_sha256($data['contractsSha256'] ?? null)
            || !red_addon_wompi_claim_sha256(
                $data['transportEvidenceSha256'] ?? null
            )
            || ($data['executionPerformed'] ?? null) !== true
            || ($data['errors'] ?? null) !== []
        ) {
            return $indeterminate;
        }
        foreach ([
            'responseBodyIncluded', 'responseHeadersIncluded',
            'publicKeyIncluded', 'rawTokensReturned', 'networkAccess',
            'providerContact', 'providerMutation', 'transactionCreation',
            'payment', 'eventRegistration', 'orderMutation',
            'retryAuthorized',
        ] as $key) {
            if (($data[$key] ?? null) !== false) {
                return $indeterminate;
            }
        }
        return $data;
    }
}

if (!function_exists('red_addon_wompi_merchant_durable_start_audit')) {
    function red_addon_wompi_merchant_durable_start_audit(
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
            'wompi_merchant_read_provider_double_started'
        );
    }
}

if (!function_exists('red_addon_wompi_merchant_durable_outcome_audit')) {
    function red_addon_wompi_merchant_durable_outcome_audit(
        $connection,
        array $plan,
        array $outcome
    ) {
        $detail = ($outcome['status'] ?? '')
                === 'merchant_provider_double_completed'
            ? 'wompi_merchant_read_provider_double_completed'
            : 'wompi_merchant_read_provider_double_indeterminate';
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

if (!function_exists('red_addon_wompi_merchant_durable_execute')) {
    function red_addon_wompi_merchant_durable_execute(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $authorization,
        $expectedStartStateSha256,
        RED_Addon_Wompi_Merchant_Read_Provider_Double $double,
        $evaluatedAtEpoch,
        $startAuditRecorder = null,
        $outcomeAuditRecorder = null
    ) {
        $result = red_addon_wompi_merchant_durable_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || $actorAdminRecordId <= 0
            || !is_int($evaluatedAtEpoch)
            || !red_addon_wompi_claim_sha256($expectedStartStateSha256)
            || red_addon_wompi_claim_transaction_active($connection)
        ) {
            return $result;
        }
        $startAuditRecorder = $startAuditRecorder
            ?? 'red_addon_wompi_merchant_durable_start_audit';
        $outcomeAuditRecorder = $outcomeAuditRecorder
            ?? 'red_addon_wompi_merchant_durable_outcome_audit';
        if (!is_callable($startAuditRecorder)
            || !is_callable($outcomeAuditRecorder)
            || !red_addon_lifecycle_lock($connection)
        ) {
            $result['status'] = 'lifecycle_locked';
            return $result;
        }
        $packageLocked = false;
        try {
            if (!red_addon_install_lock(
                $connection,
                'redcms.store-lite-wompi'
            )) {
                $result['status'] = 'package_locked';
                return $result;
            }
            $packageLocked = true;
            $plan = red_addon_wompi_merchant_durable_plan(
                $connection,
                $projectRoot,
                $actorAdminRecordId,
                $authorization,
                $evaluatedAtEpoch
            );
            $result = $plan;
            if (empty($plan['ready'])
                || !hash_equals(
                    $expectedStartStateSha256,
                    $plan['executionStartStateSha256'] ?? ''
                )
            ) {
                return $result;
            }
            if (!mysqli_begin_transaction($connection)) {
                $result['status'] = 'transaction_failed';
                return $result;
            }
            try {
                if (!red_addon_wompi_claim_lock_state(
                    $connection,
                    $actorAdminRecordId
                )) {
                    throw new RuntimeException('authority_lock_failed');
                }
                $lockedPlan = red_addon_wompi_merchant_durable_plan(
                    $connection,
                    $projectRoot,
                    $actorAdminRecordId,
                    $authorization,
                    $evaluatedAtEpoch,
                    true
                );
                if (empty($lockedPlan['ready'])
                    || !hash_equals(
                        $expectedStartStateSha256,
                        $lockedPlan['executionStartStateSha256'] ?? ''
                    )
                ) {
                    throw new RuntimeException(
                        ($lockedPlan['status'] ?? '')
                            === 'execution_already_started'
                            ? 'execution_already_started'
                            : 'execution_changed'
                    );
                }
                if (red_addon_wompi_transport_reserve(
                    $connection,
                    $lockedPlan,
                    'start',
                    $lockedPlan['authorizationSha256'],
                    $lockedPlan['preflightSha256'],
                    $lockedPlan['executionStartStateSha256']
                ) !== 'reserved') {
                    throw new RuntimeException('execution_start_failed');
                }
                if (!$startAuditRecorder($connection, $lockedPlan)) {
                    throw new RuntimeException('execution_start_audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('execution_start_commit_failed');
                }
                $result = $lockedPlan;
                $result['ready'] = false;
                $result['executionStartAvailable'] = false;
                $result['executionStarted'] = true;
                $result['startAuditRecorded'] = true;
                $result['replayProtectionActive'] = true;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result['ready'] = false;
                $result['executionStartAvailable'] = false;
                $result['status'] = $throwable->getMessage();
                return $result;
            }

            $invoked = false;
            $data = [];
            try {
                $request = red_addon_wompi_merchant_durable_request($result);
                if (!is_array($request)) {
                    throw new RuntimeException('provider_request_invalid');
                }
                $invoked = true;
                $data = $double->execute($request);
            } catch (Throwable $throwable) {
                $data = [];
            }
            $outcome = red_addon_wompi_merchant_durable_outcome($data, $invoked);
            $result['providerDoubleInvoked'] = $invoked;
            $result['executionPerformed'] = $invoked;
            $result['boundedOutcome'] = $outcome;
            $result['outcomeEvidenceSha256'] =
                red_addon_wompi_claim_hash($outcome);
            $result['outcomeStateSha256'] = red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-merchant-read-provider-double-result',
                'packageId' => $result['packageId'],
                'packageVersion' => $result['packageVersion'],
                'actorAdminRecordId' => $actorAdminRecordId,
                'executionStartStateSha256' =>
                    $result['executionStartStateSha256'],
                'outcomeEvidenceSha256' => $result['outcomeEvidenceSha256'],
                'outcome' => $outcome,
            ]);
            if (!red_addon_wompi_claim_sha256(
                $result['outcomeEvidenceSha256']
            ) || !red_addon_wompi_claim_sha256(
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
                $startRow = red_addon_wompi_transport_execution_row(
                    $connection,
                    $result['executionStartActionId'],
                    true
                );
                $outcomeRow = red_addon_wompi_transport_execution_row(
                    $connection,
                    $result['outcomeActionId'],
                    true
                );
                if (empty($startRow['valid'])
                    || empty($startRow['found'])
                    || !red_addon_wompi_transport_row_matches(
                        $startRow['row'],
                        [
                            'planSha256' => $result['planSha256'],
                            'contractSha256' =>
                                $result['authorizationSha256'],
                            'previousStateSha256' =>
                                $result['preflightSha256'],
                            'stateSha256' =>
                                $result['executionStartStateSha256'],
                            'actorAdminRecordId' => $actorAdminRecordId,
                        ]
                    )
                    || empty($outcomeRow['valid'])
                    || !empty($outcomeRow['found'])
                ) {
                    throw new RuntimeException('outcome_state_refused');
                }
                if (red_addon_wompi_transport_reserve(
                    $connection,
                    $result,
                    'result',
                    $result['outcomeEvidenceSha256'],
                    $result['executionStartStateSha256'],
                    $result['outcomeStateSha256']
                ) !== 'reserved') {
                    throw new RuntimeException('outcome_reservation_failed');
                }
                if (!$outcomeAuditRecorder($connection, $result, $outcome)) {
                    throw new RuntimeException('outcome_audit_failed');
                }
                if (!mysqli_commit($connection)) {
                    throw new RuntimeException('outcome_commit_failed');
                }
                $result['outcomeRecorded'] = true;
                $result['outcomeAuditRecorded'] = true;
                $result['status'] = $outcome['status'];
                $result['valid'] = true;
                return $result;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result['status'] = $throwable->getMessage();
                return $result;
            }
        } finally {
            if ($packageLocked) {
                red_addon_install_unlock(
                    $connection,
                    'redcms.store-lite-wompi'
                );
            }
            red_addon_lifecycle_unlock($connection);
        }
    }
}

?>
