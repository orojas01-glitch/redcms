<?php
/** C4B4C durable start/result runner for one core-owned Wompi double. */

require_once __DIR__
    . '/addon_payment_adapter_wompi_no_contact_claim_helpers.php';

if (!class_exists('RED_Addon_Wompi_No_Contact_Transport_Double', false)) {
    final class RED_Addon_Wompi_No_Contact_Transport_Double
    {
        private string $mode;
        private int $callCount = 0;

        public function __construct(string $mode = 'completed')
        {
            if (!in_array($mode, ['completed', 'fault', 'malformed'], true)) {
                throw new InvalidArgumentException('Invalid double mode.');
            }
            $this->mode = $mode;
        }

        public function execute(array $request): array
        {
            $this->callCount++;
            if ($this->mode === 'fault') {
                throw new RuntimeException('wompi_transport_double_fault');
            }
            if ($this->mode === 'malformed') {
                return ['valid' => true, 'outcome' => 'unexpected'];
            }
            if (!red_addon_wompi_transport_request_valid($request)) {
                throw new RuntimeException('wompi_transport_request_invalid');
            }
            return [
                'valid' => true,
                'outcome' => 'sealed_double_completed',
                'simulationObserved' => true,
                'requestSha256' => red_addon_wompi_claim_hash($request),
                'responseProjectionSha256' => red_addon_wompi_claim_hash([
                    'schema' => 1,
                    'purpose' => 'wompi-no-contact-double-projection',
                    'requestSha256' => red_addon_wompi_claim_hash($request),
                    'state' => 'pending_observed',
                ]),
                'responseBodyIncluded' => false,
                'responseHeadersIncluded' => false,
                'credentialIncluded' => false,
                'personalDataIncluded' => false,
                'networkAccess' => false,
                'providerContact' => false,
                'providerMutation' => false,
                'transactionCreation' => false,
                'paymentVerified' => false,
                'eventAgreement' => false,
                'paymentApplied' => false,
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

if (!function_exists('red_addon_wompi_transport_result')) {
    function red_addon_wompi_transport_result($status = 'invalid')
    {
        $result = red_addon_wompi_claim_result($status);
        $result['requestSha256'] = '';
        $result['executionStartStateSha256'] = '';
        $result['outcomeStateSha256'] = '';
        $result['outcomeEvidenceSha256'] = '';
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

if (!function_exists('red_addon_wompi_transport_request')) {
    function red_addon_wompi_transport_request(array $plan)
    {
        $request = [
            'schema' => 1,
            'packageId' => $plan['packageId'] ?? '',
            'packageVersion' => $plan['packageVersion'] ?? '',
            'operation' => 'checkout.create-sandbox-no-contact-double',
            'transportMode' => 'core_sealed_double',
            'orderId' => $plan['orderId'] ?? '',
            'amountMinor' => $plan['amountMinor'] ?? 0,
            'currency' => $plan['currency'] ?? '',
            'clientScopeSha256' => $plan['clientScopeSha256'] ?? '',
            'databaseSha256' => $plan['databaseSha256'] ?? '',
            'actorSubjectSha256' => $plan['actorSubjectSha256'] ?? '',
            'planSha256' => $plan['planSha256'] ?? '',
            'wireRequestSha256' => $plan['wireRequestSha256'] ?? '',
            'wireEvidenceSha256' => $plan['wireEvidenceSha256'] ?? '',
            'authorizationStateSha256' =>
                $plan['authorizationStateSha256'] ?? '',
            'claimStateSha256' => $plan['claimStateSha256'] ?? '',
            'attemptNumber' => 1,
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
            'networkDisabled' => true,
            'providerContactDenied' => true,
            'providerMutationDenied' => true,
            'orderMutationDenied' => true,
        ];
        return red_addon_wompi_transport_request_valid($request)
            ? $request
            : null;
    }
}

if (!function_exists('red_addon_wompi_transport_request_valid')) {
    function red_addon_wompi_transport_request_valid(array $request)
    {
        if (!red_addon_wompi_claim_exact_keys($request, [
            'schema', 'packageId', 'packageVersion', 'operation',
            'transportMode', 'orderId', 'amountMinor', 'currency',
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'planSha256', 'wireRequestSha256', 'wireEvidenceSha256',
            'authorizationStateSha256', 'claimStateSha256',
            'attemptNumber', 'maximumAttempts', 'retryAuthorized',
            'networkDisabled', 'providerContactDenied',
            'providerMutationDenied', 'orderMutationDenied',
        ])) {
            return false;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'planSha256', 'wireRequestSha256', 'wireEvidenceSha256',
            'authorizationStateSha256', 'claimStateSha256',
        ] as $key) {
            if (!red_addon_wompi_claim_sha256($request[$key] ?? null)) {
                return false;
            }
        }
        return ($request['schema'] ?? null) === 1
            && ($request['packageId'] ?? null) === 'redcms.store-lite-wompi'
            && ($request['packageVersion'] ?? null) === '0.1.4'
            && ($request['operation'] ?? null)
                === 'checkout.create-sandbox-no-contact-double'
            && ($request['transportMode'] ?? null) === 'core_sealed_double'
            && is_string($request['orderId'] ?? null)
            && preg_match(
                '/\Aord_[a-f0-9]{32}\z/D',
                $request['orderId']
            ) === 1
            && is_int($request['amountMinor'] ?? null)
            && $request['amountMinor'] >= 100
            && $request['amountMinor'] <= 999999999999
            && ($request['currency'] ?? null) === 'COP'
            && ($request['attemptNumber'] ?? null) === 1
            && ($request['maximumAttempts'] ?? null) === 1
            && ($request['retryAuthorized'] ?? null) === false
            && ($request['networkDisabled'] ?? null) === true
            && ($request['providerContactDenied'] ?? null) === true
            && ($request['providerMutationDenied'] ?? null) === true
            && ($request['orderMutationDenied'] ?? null) === true;
    }
}

if (!function_exists('red_addon_wompi_transport_action_id')) {
    function red_addon_wompi_transport_action_id($purpose, $nonceSha256)
    {
        if (!in_array($purpose, ['start', 'result'], true)
            || !red_addon_wompi_claim_sha256($nonceSha256)
        ) {
            return '';
        }
        return 'wompi-no-contact-' . $purpose . '.' . $nonceSha256;
    }
}

if (!function_exists('red_addon_wompi_transport_execution_row')) {
    function red_addon_wompi_transport_execution_row(
        $connection,
        $actionId,
        $lock = false
    ) {
        if (!($connection instanceof mysqli)
            || !is_string($actionId)
            || $actionId === ''
            || !is_bool($lock)
        ) {
            return ['valid' => false, 'found' => false, 'row' => null];
        }
        try {
            $packageId = 'redcms.store-lite-wompi';
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
            return $executed && $rows <= 1
                ? [
                    'valid' => true,
                    'found' => $rows === 1,
                    'row' => $rows === 1 ? $row : null,
                ]
                : ['valid' => false, 'found' => false, 'row' => null];
        } catch (Throwable $throwable) {
            return ['valid' => false, 'found' => false, 'row' => null];
        }
    }
}

if (!function_exists('red_addon_wompi_transport_claim_states')) {
    function red_addon_wompi_transport_claim_states(
        $connection,
        $actorAdminRecordId,
        array $authorization,
        array $claim
    ) {
        $databaseSha256 = red_addon_wompi_claim_database_sha256($connection);
        $actorSubjectSha256 = red_addon_wompi_claim_actor_subject_sha256(
            $connection,
            $actorAdminRecordId
        );
        $authorizationStateSha256 = red_addon_wompi_claim_hash([
            'schema' => 1,
            'purpose' => 'wompi-no-contact-durable-authorization',
            'packageId' => 'redcms.store-lite-wompi',
            'packageVersion' => '0.1.4',
            'databaseSha256' => $databaseSha256,
            'actorAdminRecordId' => (int) $actorAdminRecordId,
            'actorSubjectSha256' => $actorSubjectSha256,
            'orderId' => $authorization['orderId'] ?? '',
            'planSha256' => $authorization['planSha256'] ?? '',
            'wireRequestSha256' =>
                $authorization['wireRequestSha256'] ?? '',
            'authorizationSha256' =>
                $authorization['authorizationSha256'] ?? '',
            'authorizationNonceSha256' =>
                $authorization['authorizationNonceSha256'] ?? '',
            'issuedAtEpoch' => $authorization['issuedAtEpoch'] ?? 0,
            'expiresAtEpoch' => $authorization['expiresAtEpoch'] ?? 0,
            'maximumAttempts' => 1,
            'authorizationRecorded' => true,
            'claimRecorded' => false,
            'executionAuthorized' => false,
        ]);
        $claimStateSha256 = red_addon_wompi_claim_hash([
            'schema' => 1,
            'purpose' => 'wompi-no-contact-durable-claim',
            'authorizationStateSha256' => $authorizationStateSha256,
            'claimSha256' => $claim['claimSha256'] ?? '',
            'claimNonceSha256' => $claim['claimNonceSha256'] ?? '',
            'claimedAtEpoch' => $claim['claimedAtEpoch'] ?? 0,
            'maximumAttempts' => 1,
            'remainingAttempts' => 0,
            'claimRecorded' => true,
            'replayProtectionActive' => true,
            'executionAuthorized' => false,
        ]);
        return [
            'authorizationStateSha256' => $authorizationStateSha256,
            'claimStateSha256' => $claimStateSha256,
        ];
    }
}

if (!function_exists('red_addon_wompi_transport_row_matches')) {
    function red_addon_wompi_transport_row_matches(
        array $row,
        array $expected
    ) {
        return red_addon_wompi_claim_exact_keys($row, [
            'PlanSHA256', 'ContractSHA256', 'PreviousStateSHA256',
            'StateSHA256', 'ActorAdminRecordID',
        ])
            && hash_equals($expected['planSha256'], $row['PlanSHA256'])
            && hash_equals($expected['contractSha256'], $row['ContractSHA256'])
            && hash_equals(
                $expected['previousStateSha256'],
                $row['PreviousStateSHA256']
            )
            && hash_equals($expected['stateSha256'], $row['StateSHA256'])
            && (int) $row['ActorAdminRecordID']
                === (int) $expected['actorAdminRecordId'];
    }
}

if (!function_exists('red_addon_wompi_transport_plan')) {
    function red_addon_wompi_transport_plan(
        $connection,
        array $package,
        array $catalog,
        $actorAdminRecordId,
        array $authorization,
        array $claim,
        $evaluatedAtEpoch,
        $lockRows = false
    ) {
        $result = red_addon_wompi_transport_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result['actorAdminRecordId'] = $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || $actorAdminRecordId <= 0
            || !is_int($evaluatedAtEpoch)
            || !is_bool($lockRows)
            || empty($catalog['valid'])
            || !red_addon_wompi_claim_authorization_valid(
                $authorization,
                $evaluatedAtEpoch
            )
            || !red_addon_wompi_claim_preparation_valid(
                $claim,
                $authorization
            )
        ) {
            $result['errors'][] = 'execution_evidence_refused';
            return $result;
        }
        $actor = red_admin_addon_database_actor(
            $connection,
            $actorAdminRecordId
        );
        if (!red_admin_addon_actor_can($actor, 'addons.enable')
            || !red_addon_package_permission_has_exact_grant(
                $connection,
                $actorAdminRecordId,
                'store.orders.manage'
            )
            || !red_addon_wompi_claim_package_ready(
                $connection,
                $package,
                $catalog
            )
            || !hash_equals(
                $authorization['databaseSha256'],
                red_addon_wompi_claim_database_sha256($connection)
            )
            || !hash_equals(
                $authorization['clientScopeSha256'],
                red_addon_wompi_claim_client_scope_sha256($connection)
            )
            || !hash_equals(
                $authorization['actorSubjectSha256'],
                red_addon_wompi_claim_actor_subject_sha256(
                    $connection,
                    $actorAdminRecordId
                )
            )
            || !hash_equals(
                $authorization['secretAvailabilitySha256'],
                red_addon_wompi_claim_secret_availability_sha256($connection)
            )
        ) {
            $result['errors'][] = 'current_state_refused';
            return $result;
        }
        foreach ([
            'clientScopeSha256', 'databaseSha256', 'actorSubjectSha256',
            'secretAvailabilitySha256', 'orderId', 'amountMinor', 'currency',
            'planSha256', 'wireRequestSha256', 'wireEvidenceSha256',
            'authorizationSha256', 'authorizationNonceSha256',
            'issuedAtEpoch', 'expiresAtEpoch', 'maximumAttempts',
        ] as $key) {
            $result[$key] = $authorization[$key];
        }
        foreach ([
            'claimSha256', 'claimNonceSha256', 'claimedAtEpoch',
            'remainingAttempts',
        ] as $key) {
            $result[$key] = $claim[$key];
        }
        $result['packageStateRevalidated'] = true;
        $result['ownerAuthorityRevalidated'] = true;
        $result['orderAuthorityRevalidated'] = true;
        $result['settingAvailabilityRevalidated'] = true;
        $result['lifecycleState'] = 'enabled';
        $result['authorizationActionId'] = red_addon_wompi_claim_action_id(
            'authorize',
            $authorization['authorizationNonceSha256']
        );
        $result['claimActionId'] = red_addon_wompi_claim_action_id(
            'claim',
            $authorization['authorizationNonceSha256']
        );
        $result['executionStartActionId'] =
            red_addon_wompi_transport_action_id(
                'start',
                $authorization['authorizationNonceSha256']
            );
        $result['outcomeActionId'] = red_addon_wompi_transport_action_id(
            'result',
            $authorization['authorizationNonceSha256']
        );
        $states = red_addon_wompi_transport_claim_states(
            $connection,
            $actorAdminRecordId,
            $authorization,
            $claim
        );
        $result['authorizationStateSha256'] =
            $states['authorizationStateSha256'];
        $result['claimStateSha256'] = $states['claimStateSha256'];
        $authorizationRow = red_addon_wompi_transport_execution_row(
            $connection,
            $result['authorizationActionId'],
            $lockRows
        );
        $claimRow = red_addon_wompi_transport_execution_row(
            $connection,
            $result['claimActionId'],
            $lockRows
        );
        if (empty($authorizationRow['valid'])
            || empty($authorizationRow['found'])
            || !red_addon_wompi_transport_row_matches(
                $authorizationRow['row'],
                [
                    'planSha256' => $result['planSha256'],
                    'contractSha256' => $result['authorizationSha256'],
                    'previousStateSha256' => $result['actorSubjectSha256'],
                    'stateSha256' => $result['authorizationStateSha256'],
                    'actorAdminRecordId' => $actorAdminRecordId,
                ]
            )
            || empty($claimRow['valid'])
            || empty($claimRow['found'])
            || !red_addon_wompi_transport_row_matches(
                $claimRow['row'],
                [
                    'planSha256' => $result['planSha256'],
                    'contractSha256' => $result['claimSha256'],
                    'previousStateSha256' =>
                        $result['authorizationStateSha256'],
                    'stateSha256' => $result['claimStateSha256'],
                    'actorAdminRecordId' => $actorAdminRecordId,
                ]
            )
        ) {
            $result['errors'][] = 'durable_claim_refused';
            return $result;
        }
        $result['authorizationRecorded'] = true;
        $result['claimRecorded'] = true;
        $result['replayProtectionActive'] = true;
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
            return $result;
        }
        $request = red_addon_wompi_transport_request($result);
        if (!is_array($request)) {
            $result['errors'][] = 'request_encoding_failed';
            return $result;
        }
        $result['requestSha256'] = red_addon_wompi_claim_hash($request);
        $result['executionStartStateSha256'] =
            red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-no-contact-transport-double-start',
                'packageId' => $result['packageId'],
                'packageVersion' => $result['packageVersion'],
                'actorAdminRecordId' => $actorAdminRecordId,
                'clientScopeSha256' => $result['clientScopeSha256'],
                'orderId' => $result['orderId'],
                'claimStateSha256' => $result['claimStateSha256'],
                'requestSha256' => $result['requestSha256'],
                'maximumAttempts' => 1,
                'retryAuthorized' => false,
                'executionStarted' => true,
            ]);
        if (!red_addon_wompi_claim_sha256(
            $result['executionStartStateSha256']
        )) {
            $result['errors'][] = 'execution_start_encoding_failed';
            return $result;
        }
        $result['executionStartAvailable'] = true;
        $result['status'] = 'ready';
        $result['ready'] = true;
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_wompi_transport_reserve')) {
    function red_addon_wompi_transport_reserve(
        $connection,
        array $plan,
        $purpose,
        $contractSha256,
        $previousStateSha256,
        $stateSha256
    ) {
        if (!in_array($purpose, ['start', 'result'], true)
            || !red_addon_wompi_claim_sha256($contractSha256)
            || !red_addon_wompi_claim_sha256($previousStateSha256)
            || !red_addon_wompi_claim_sha256($stateSha256)
        ) {
            return 'failed';
        }
        $actionId = $purpose === 'start'
            ? $plan['executionStartActionId']
            : $plan['outcomeActionId'];
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
                $plan['planSha256'],
                $contractSha256,
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

if (!function_exists('red_addon_wompi_transport_outcome')) {
    function red_addon_wompi_transport_outcome(array $data, $invoked)
    {
        $indeterminate = [
            'valid' => true,
            'outcome' => 'sealed_double_indeterminate',
            'simulationObserved' => false,
            'requestSha256' => '',
            'responseProjectionSha256' => '',
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'credentialIncluded' => false,
            'personalDataIncluded' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'providerMutation' => false,
            'transactionCreation' => false,
            'paymentVerified' => false,
            'eventAgreement' => false,
            'paymentApplied' => false,
            'orderMutation' => false,
            'retryAuthorized' => false,
            'executionPerformed' => (bool) $invoked,
            'errors' => ['sealed_double_indeterminate'],
        ];
        if (!$invoked
            || !red_addon_wompi_claim_exact_keys(
                $data,
                array_keys($indeterminate)
            )
            || ($data['valid'] ?? null) !== true
            || ($data['outcome'] ?? null) !== 'sealed_double_completed'
            || ($data['simulationObserved'] ?? null) !== true
            || !red_addon_wompi_claim_sha256($data['requestSha256'] ?? null)
            || !red_addon_wompi_claim_sha256(
                $data['responseProjectionSha256'] ?? null
            )
            || ($data['executionPerformed'] ?? null) !== true
            || ($data['errors'] ?? null) !== []
        ) {
            return $indeterminate;
        }
        foreach ([
            'responseBodyIncluded', 'responseHeadersIncluded',
            'credentialIncluded', 'personalDataIncluded', 'networkAccess',
            'providerContact', 'providerMutation', 'transactionCreation',
            'paymentVerified', 'eventAgreement', 'paymentApplied',
            'orderMutation', 'retryAuthorized',
        ] as $key) {
            if (($data[$key] ?? null) !== false) {
                return $indeterminate;
            }
        }
        return $data;
    }
}

if (!function_exists('red_addon_wompi_transport_start_audit')) {
    function red_addon_wompi_transport_start_audit($connection, array $plan)
    {
        return red_addon_install_audit_record(
            $connection,
            'addon.action.completed',
            $plan['packageId'],
            $plan['packageVersion'],
            $plan['actorAdminRecordId'],
            'succeeded',
            'wompi_no_contact_execution_started'
        );
    }
}

if (!function_exists('red_addon_wompi_transport_outcome_audit')) {
    function red_addon_wompi_transport_outcome_audit(
        $connection,
        array $plan,
        array $outcome
    ) {
        $detail = ($outcome['outcome'] ?? '') === 'sealed_double_completed'
            ? 'wompi_no_contact_double_completed'
            : 'wompi_no_contact_double_indeterminate';
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

if (!function_exists('red_addon_wompi_transport_execute')) {
    function red_addon_wompi_transport_execute(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $authorization,
        array $claim,
        $expectedStartStateSha256,
        RED_Addon_Wompi_No_Contact_Transport_Double $double,
        $evaluatedAtEpoch,
        $startAuditRecorder = null,
        $outcomeAuditRecorder = null
    ) {
        $result = red_addon_wompi_transport_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || $projectRoot === ''
            || $actorAdminRecordId <= 0
            || !is_int($evaluatedAtEpoch)
            || !red_addon_wompi_claim_sha256($expectedStartStateSha256)
            || red_addon_wompi_claim_transaction_active($connection)
        ) {
            return $result;
        }
        $startAuditRecorder = $startAuditRecorder
            ?? 'red_addon_wompi_transport_start_audit';
        $outcomeAuditRecorder = $outcomeAuditRecorder
            ?? 'red_addon_wompi_transport_outcome_audit';
        if (!is_callable($startAuditRecorder)
            || !is_callable($outcomeAuditRecorder)
        ) {
            return $result;
        }
        if (!red_addon_lifecycle_lock($connection)) {
            $result['status'] = 'lifecycle_locked';
            return $result;
        }
        $packageId = 'redcms.store-lite-wompi';
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
            $plan = red_addon_wompi_transport_plan(
                $connection,
                $package,
                $catalog,
                $actorAdminRecordId,
                $authorization,
                $claim,
                $evaluatedAtEpoch
            );
            $result = $plan;
            if (empty($plan['ready'])) {
                return $result;
            }
            if (!hash_equals(
                $plan['executionStartStateSha256'],
                $expectedStartStateSha256
            )) {
                $result['ready'] = false;
                $result['executionStartAvailable'] = false;
                $result['status'] = 'execution_changed';
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
                $lockedPlan = red_addon_wompi_transport_plan(
                    $connection,
                    $package,
                    $catalog,
                    $actorAdminRecordId,
                    $authorization,
                    $claim,
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
                    $lockedPlan['claimSha256'],
                    $lockedPlan['claimStateSha256'],
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
                $request = red_addon_wompi_transport_request($result);
                if (!is_array($request)) {
                    throw new RuntimeException('transport_request_invalid');
                }
                $invoked = true;
                $data = $double->execute($request);
            } catch (Throwable $throwable) {
                $data = [];
            }
            $outcome = red_addon_wompi_transport_outcome($data, $invoked);
            $result['transportDoubleInvoked'] = $invoked;
            $result['executionPerformed'] = $invoked;
            $result['boundedOutcome'] = $outcome;
            $result['outcomeEvidenceSha256'] =
                red_addon_wompi_claim_hash($outcome);
            $result['outcomeStateSha256'] = red_addon_wompi_claim_hash([
                'schema' => 1,
                'purpose' => 'wompi-no-contact-transport-double-result',
                'packageId' => $result['packageId'],
                'packageVersion' => $result['packageVersion'],
                'actorAdminRecordId' => $actorAdminRecordId,
                'executionStartStateSha256' =>
                    $result['executionStartStateSha256'],
                'outcomeEvidenceSha256' =>
                    $result['outcomeEvidenceSha256'],
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
                            'contractSha256' => $result['claimSha256'],
                            'previousStateSha256' =>
                                $result['claimStateSha256'],
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
                $result['status'] = $outcome['outcome'];
                $result['outcomeRecorded'] = true;
                $result['outcomeAuditRecorded'] = true;
                return $result;
            } catch (Throwable $throwable) {
                mysqli_rollback($connection);
                $result['status'] = $throwable->getMessage();
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
