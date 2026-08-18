<?php
/**
 * Core-owned P3E-8B2 loopback-only provider-contact execution boundary.
 *
 * This helper requires the exact still-active P3E-8A claim, commits a
 * one-time execution-start row before package invocation, resolves only the
 * owning package's stripe.secret-key setting, invokes one integrity-checked
 * adapter operation, and records only a closed loopback outcome. It contains
 * no DNS, socket, HTTP, TLS, cURL, Stripe SDK, browser, or client-deployment
 * primitive. A committed start permanently refuses retry even if execution or
 * result recording later fails.
 */

require_once __DIR__ . '/addon_provider_contact_claim_helpers.php';
require_once __DIR__ . '/addon_adapter_helpers.php';

if (!function_exists('red_addon_provider_contact_execution_result')) {
    function red_addon_provider_contact_execution_result()
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
            'authorizationStateSha256' => '',
            'claimStateSha256' => '',
            'executionStartStateSha256' => '',
            'outcomeStateSha256' => '',
            'authorizationActionId' => '',
            'claimActionId' => '',
            'executionStartActionId' => '',
            'outcomeActionId' => '',
            'authorizationNonceSha256' => '',
            'issuedAtUtc' => '',
            'expiresAtUtc' => '',
            'secretAvailabilitySha256' => '',
            'claimRecorded' => false,
            'executionStartAvailable' => false,
            'executionStarted' => false,
            'startAuditRecorded' => false,
            'registrarValidated' => false,
            'secretResolution' => false,
            'adapterInvoked' => false,
            'boundedOutcome' => null,
            'outcomeRecorded' => false,
            'outcomeAuditRecorded' => false,
            'executionPerformed' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
            'checkoutCreation' => false,
            'payment' => false,
            'webhook' => false,
            'storeLiteMutation' => false,
            'clientDeployment' => false,
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_provider_contact_execution_start_action_id')) {
    function red_addon_provider_contact_execution_start_action_id(
        $nonceSha256
    ) {
        return red_addon_provider_contact_sha256($nonceSha256)
            ? 'provider-contact-attempt-start.' . $nonceSha256
            : '';
    }
}

if (!function_exists('red_addon_provider_contact_outcome_action_id')) {
    function red_addon_provider_contact_outcome_action_id($nonceSha256)
    {
        return red_addon_provider_contact_sha256($nonceSha256)
            ? 'provider-contact-attempt-result.' . $nonceSha256
            : '';
    }
}

if (!function_exists('red_addon_provider_contact_execution_claim_matches')) {
    function red_addon_provider_contact_execution_claim_matches(
        array $row,
        array $authorizationPlan,
        $claimStateSha256
    ) {
        return red_addon_provider_contact_exact_keys($row, [
            'PlanSHA256', 'ContractSHA256', 'PreviousStateSHA256',
            'StateSHA256', 'ActorAdminRecordID',
        ])
            && red_addon_provider_contact_sha256($claimStateSha256)
            && hash_equals(
                (string) ($authorizationPlan['planSha256'] ?? ''),
                (string) ($row['PlanSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($authorizationPlan['authorizationSha256'] ?? ''),
                (string) ($row['ContractSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($authorizationPlan['authorizationStateSha256'] ?? ''),
                (string) ($row['PreviousStateSHA256'] ?? '')
            )
            && hash_equals(
                $claimStateSha256,
                (string) ($row['StateSHA256'] ?? '')
            )
            && (int) ($row['ActorAdminRecordID'] ?? 0)
                === (int) ($authorizationPlan['actorAdminRecordId'] ?? 0);
    }
}

if (!function_exists('red_addon_provider_contact_secret_evidence')) {
    /**
     * Read only value-free availability evidence for stripe.secret-key.
     */
    function red_addon_provider_contact_secret_evidence(
        $connection,
        array $package,
        $declarations = null
    ) {
        $result = [
            'valid' => false,
            'available' => false,
            'settingKey' => 'stripe.secret-key',
            'declarationSha256' => '',
            'evidenceSha256' => '',
            'errors' => [],
        ];
        $snapshot = red_addon_registry_snapshot($package);
        $manifest = $package['manifest'] ?? null;
        if (!($connection instanceof mysqli)
            || !is_array($snapshot)
            || !is_array($manifest)
            || $snapshot['id'] !== 'redcms.store-lite-stripe-checkout'
            || !in_array($snapshot['version'], ['0.1.1', '0.1.3'], true)
        ) {
            $result['errors'][] = 'package_invalid';
            return $result;
        }
        $definition = null;
        foreach (red_addon_settings_schema($manifest) ?? [] as $candidate) {
            if (($candidate['key'] ?? null) === 'stripe.secret-key') {
                if ($definition !== null) {
                    $result['errors'][] = 'setting_schema_invalid';
                    return $result;
                }
                $definition = $candidate;
            }
        }
        if (!is_array($definition)
            || ($definition['type'] ?? null) !== 'secret-reference'
            || ($definition['secret'] ?? null) !== true
        ) {
            $result['errors'][] = 'setting_schema_invalid';
            return $result;
        }

        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT ValueType, ValueJSON, SecretReference
                 FROM RED_Addon_Settings
                 WHERE PackageID=? AND SettingKey=?'
            );
            if (!$statement) {
                $result['errors'][] = 'setting_storage_unavailable';
                return $result;
            }
            $packageId = $snapshot['id'];
            $settingKey = 'stripe.secret-key';
            mysqli_stmt_bind_param(
                $statement,
                'ss',
                $packageId,
                $settingKey
            );
            $executed = mysqli_stmt_execute($statement);
            $query = $executed ? mysqli_stmt_get_result($statement) : false;
            $row = $query ? mysqli_fetch_assoc($query) : null;
            $rows = $query ? mysqli_num_rows($query) : 0;
            if ($query) {
                mysqli_free_result($query);
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $result['errors'][] = 'setting_storage_unavailable';
            return $result;
        }
        $reference = is_array($row) ? ($row['SecretReference'] ?? null) : null;
        if (!$executed
            || $rows !== 1
            || ($row['ValueType'] ?? null) !== 'secret-reference'
            || ($row['ValueJSON'] ?? null) !== null
            || !red_addon_setting_string_is_valid(
                'secret-reference',
                $reference
            )
        ) {
            $result['errors'][] = 'setting_configuration_invalid';
            return $result;
        }
        if ($declarations === null) {
            $declarations = red_addon_secret_reference_declarations();
        }
        if (!is_array($declarations)
            || empty($declarations['valid'])
            || !is_array($declarations['references'] ?? null)
            || !red_addon_provider_contact_sha256(
                $declarations['declarationSha256'] ?? null
            )
        ) {
            $result['errors'][] = 'secret_declaration_invalid';
            return $result;
        }
        $canonical = red_addon_secret_reference_declarations(
            $declarations['references'],
            ''
        );
        if (empty($canonical['valid'])
            || !hash_equals(
                $canonical['declarationSha256'],
                $declarations['declarationSha256']
            )
            || !in_array($reference, $canonical['references'], true)
        ) {
            $result['errors'][] = 'secret_unavailable';
            return $result;
        }
        $material = [
            'schema' => 1,
            'packageId' => $snapshot['id'],
            'packageVersion' => $snapshot['version'],
            'settingKey' => 'stripe.secret-key',
            'referenceSha256' => hash('sha256', $reference),
            'declarationSha256' => $canonical['declarationSha256'],
            'available' => true,
            'valueIncluded' => false,
            'valueSha256Included' => false,
        ];
        $encoded = red_addon_provider_contact_encode($material);
        if (!is_string($encoded)) {
            $result['errors'][] = 'secret_evidence_encoding_failed';
            return $result;
        }
        $result['valid'] = true;
        $result['available'] = true;
        $result['declarationSha256'] = $canonical['declarationSha256'];
        $result['evidenceSha256'] = hash('sha256', $encoded);
        return $result;
    }
}

if (!function_exists('red_addon_provider_contact_execution_start_sha256')) {
    function red_addon_provider_contact_execution_start_sha256(array $plan)
    {
        foreach ([
            'packageId', 'packageVersion', 'actorAdminRecordId',
            'planSha256', 'authorizationSha256', 'claimStateSha256',
            'authorizationNonceSha256', 'secretAvailabilitySha256',
        ] as $key) {
            if (!array_key_exists($key, $plan)) {
                return '';
            }
        }
        $material = [
            'schema' => 1,
            'purpose' => 'provider-contact-loopback-execution-start',
            'packageId' => $plan['packageId'],
            'packageVersion' => $plan['packageVersion'],
            'actorAdminRecordId' => (int) $plan['actorAdminRecordId'],
            'planSha256' => $plan['planSha256'],
            'authorizationSha256' => $plan['authorizationSha256'],
            'claimStateSha256' => $plan['claimStateSha256'],
            'authorizationNonceSha256' =>
                $plan['authorizationNonceSha256'],
            'secretAvailabilitySha256' =>
                $plan['secretAvailabilitySha256'],
            'operation' => 'provider-contact.read-only-probe-loopback',
            'contactTarget' => 'loopback',
            'maximumAttempts' => 1,
            'retryAuthorized' => false,
            'executionStarted' => true,
        ];
        foreach ([
            'planSha256', 'authorizationSha256', 'claimStateSha256',
            'authorizationNonceSha256', 'secretAvailabilitySha256',
        ] as $key) {
            if (!red_addon_provider_contact_sha256($material[$key])) {
                return '';
            }
        }
        $encoded = red_addon_provider_contact_encode($material);
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_provider_contact_execution_start_matches')) {
    function red_addon_provider_contact_execution_start_matches(
        array $row,
        array $plan
    ) {
        return red_addon_provider_contact_exact_keys($row, [
            'PlanSHA256', 'ContractSHA256', 'PreviousStateSHA256',
            'StateSHA256', 'ActorAdminRecordID',
        ])
            && red_addon_provider_contact_sha256(
                $plan['executionStartStateSha256'] ?? null
            )
            && hash_equals(
                (string) ($plan['planSha256'] ?? ''),
                (string) ($row['PlanSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($plan['authorizationSha256'] ?? ''),
                (string) ($row['ContractSHA256'] ?? '')
            )
            && hash_equals(
                (string) ($plan['claimStateSha256'] ?? ''),
                (string) ($row['PreviousStateSHA256'] ?? '')
            )
            && hash_equals(
                (string) $plan['executionStartStateSha256'],
                (string) ($row['StateSHA256'] ?? '')
            )
            && (int) ($row['ActorAdminRecordID'] ?? 0)
                === (int) ($plan['actorAdminRecordId'] ?? 0);
    }
}

if (!function_exists('red_addon_provider_contact_execution_plan')) {
    function red_addon_provider_contact_execution_plan(
        $connection,
        array $package,
        array $catalog,
        $actorAdminRecordId,
        array $readiness,
        array $prepared,
        $evaluatedAtUtc,
        $lockRows = false,
        $declarations = null
    ) {
        $result = red_addon_provider_contact_execution_result();
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result['actorAdminRecordId'] = $actorAdminRecordId;
        if (!($connection instanceof mysqli) || !is_bool($lockRows)) {
            $result['errors'][] = 'execution_evidence_refused';
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
            'issuedAtUtc', 'expiresAtUtc',
        ] as $key) {
            $result[$key] = $authorizationPlan[$key];
        }
        $result['authorizationActionId'] =
            red_addon_provider_contact_action_id(
                $result['authorizationNonceSha256']
            );
        $result['claimActionId'] =
            red_addon_provider_contact_claim_action_id(
                $result['authorizationNonceSha256']
            );
        $result['executionStartActionId'] =
            red_addon_provider_contact_execution_start_action_id(
                $result['authorizationNonceSha256']
            );
        $result['outcomeActionId'] =
            red_addon_provider_contact_outcome_action_id(
                $result['authorizationNonceSha256']
            );
        $result['claimStateSha256'] =
            red_addon_provider_contact_claim_state_sha256(
                $authorizationPlan
            );
        if ($result['authorizationActionId'] === ''
            || $result['claimActionId'] === ''
            || $result['executionStartActionId'] === ''
            || $result['outcomeActionId'] === ''
            || !red_addon_provider_contact_sha256(
                $result['claimStateSha256']
            )
        ) {
            $result['status'] = 'execution_identity_refused';
            $result['errors'][] = 'execution_identity_refused';
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
        $claimRow = red_addon_provider_contact_claim_execution_row(
            $connection,
            $result['packageId'],
            $result['claimActionId'],
            $lockRows
        );
        if (empty($claimRow['valid'])
            || empty($claimRow['found'])
            || !is_array($claimRow['row'])
            || !red_addon_provider_contact_execution_claim_matches(
                $claimRow['row'],
                $authorizationPlan,
                $result['claimStateSha256']
            )
        ) {
            $result['status'] = 'claim_record_refused';
            $result['errors'][] = 'claim_record_refused';
            return $result;
        }
        $result['claimRecorded'] = true;

        $startRow = red_addon_provider_contact_claim_execution_row(
            $connection,
            $result['packageId'],
            $result['executionStartActionId'],
            $lockRows
        );
        if (empty($startRow['valid'])) {
            $result['status'] = 'execution_start_state_refused';
            $result['errors'][] = 'execution_start_state_refused';
            return $result;
        }
        if (!empty($startRow['found'])) {
            $result['status'] = 'execution_already_started';
            $result['errors'][] = 'execution_already_started';
            return $result;
        }
        $secretEvidence = red_addon_provider_contact_secret_evidence(
            $connection,
            $package,
            $declarations
        );
        if (empty($secretEvidence['valid'])
            || empty($secretEvidence['available'])
            || !red_addon_provider_contact_sha256(
                $secretEvidence['evidenceSha256'] ?? null
            )
        ) {
            $result['status'] = 'secret_availability_refused';
            $result['errors'] = $secretEvidence['errors'] ?? [
                'secret_availability_refused',
            ];
            return $result;
        }
        $result['secretAvailabilitySha256'] =
            $secretEvidence['evidenceSha256'];
        $result['executionStartStateSha256'] =
            red_addon_provider_contact_execution_start_sha256($result);
        if (!red_addon_provider_contact_sha256(
            $result['executionStartStateSha256']
        )) {
            $result['status'] = 'execution_start_state_encoding_failed';
            $result['errors'][] = 'execution_start_state_encoding_failed';
            return $result;
        }
        $result['executionStartAvailable'] = true;
        $result['valid'] = true;
        $result['ready'] = true;
        $result['status'] = 'ready';
        return $result;
    }
}

if (!function_exists('red_addon_provider_contact_loopback_execution_plan')) {
    function red_addon_provider_contact_loopback_execution_plan(
        $connection,
        array $package,
        array $catalog,
        $actorAdminRecordId,
        array $readiness,
        array $prepared,
        $evaluatedAtUtc,
        $lockRows = false,
        $declarations = null
    ) {
        $result = red_addon_provider_contact_execution_plan(
            $connection,
            $package,
            $catalog,
            $actorAdminRecordId,
            $readiness,
            $prepared,
            $evaluatedAtUtc,
            $lockRows,
            $declarations
        );
        if (empty($result['ready'])) {
            return $result;
        }
        $contactPlan = $readiness['contactPlan'] ?? null;
        if (!is_array($contactPlan)
            || ($contactPlan['packageVersion'] ?? null) !== '0.1.1'
            || ($contactPlan['runtimeProviderTransport'] ?? null)
                !== 'disabled'
        ) {
            $result['valid'] = false;
            $result['ready'] = false;
            $result['status'] = 'loopback_profile_refused';
            $result['executionStartAvailable'] = false;
            $result['executionStartStateSha256'] = '';
            $result['errors'][] = 'loopback_profile_refused';
        }
        return $result;
    }
}

if (!function_exists('red_addon_provider_contact_execution_reserve')) {
    function red_addon_provider_contact_execution_reserve(
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
                $plan['planSha256'],
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

if (!function_exists('red_addon_provider_contact_execution_start_audit')) {
    function red_addon_provider_contact_execution_start_audit(
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
            'provider_contact_execution_started'
        );
    }
}

if (!function_exists('red_addon_provider_contact_loopback_outcome')) {
    function red_addon_provider_contact_loopback_outcome(array $invocation)
    {
        $indeterminate = [
            'valid' => true,
            'contactTarget' => 'loopback',
            'outcome' => 'indeterminate',
            'statusCode' => null,
            'expectedEffectObserved' => false,
            'responseBytes' => null,
            'transportEvidenceSha256' => '',
            'responseBodyIncluded' => false,
            'responseHeadersIncluded' => false,
            'credentialIncluded' => false,
            'retryAuthorized' => false,
            'mutationAuthorized' => false,
            'networkAccess' => false,
            'providerContact' => false,
            'executionPerformed' => false,
            'errors' => ['loopback_execution_indeterminate'],
        ];
        $data = $invocation['data'] ?? null;
        if (empty($invocation['invoked'])
            || empty($invocation['success'])
            || ($invocation['reason'] ?? null) !== 'completed'
            || !is_array($data)
            || !red_addon_provider_contact_exact_keys($data, [
                'valid', 'contactTarget', 'outcome', 'statusCode',
                'expectedEffectObserved', 'responseBytes',
                'transportEvidenceSha256', 'responseBodyIncluded',
                'responseHeadersIncluded', 'credentialIncluded',
                'retryAuthorized', 'mutationAuthorized', 'networkAccess',
                'providerContact', 'executionPerformed', 'errors',
            ])
            || ($data['valid'] ?? null) !== true
            || ($data['contactTarget'] ?? null) !== 'loopback'
            || !in_array($data['outcome'] ?? null, [
                'resource_miss_observed', 'credential_refused',
                'permission_refused', 'rate_limited',
                'provider_unavailable', 'unexpected_success_status',
                'unexpected_provider_status',
            ], true)
            || !is_int($data['statusCode'] ?? null)
            || $data['statusCode'] < 100
            || $data['statusCode'] > 599
            || !is_int($data['responseBytes'] ?? null)
            || $data['responseBytes'] < 0
            || $data['responseBytes'] > 65536
            || !red_addon_provider_contact_sha256(
                $data['transportEvidenceSha256'] ?? null
            )
            || ($data['expectedEffectObserved'] ?? null)
                !== ($data['statusCode'] === 404
                    && $data['outcome'] === 'resource_miss_observed')
            || ($data['responseBodyIncluded'] ?? null) !== false
            || ($data['responseHeadersIncluded'] ?? null) !== false
            || ($data['credentialIncluded'] ?? null) !== false
            || ($data['retryAuthorized'] ?? null) !== false
            || ($data['mutationAuthorized'] ?? null) !== false
            || ($data['networkAccess'] ?? null) !== false
            || ($data['providerContact'] ?? null) !== false
            || ($data['executionPerformed'] ?? null) !== true
            || ($data['errors'] ?? null) !== []
        ) {
            return $indeterminate;
        }
        $expected = match ($data['statusCode']) {
            404 => 'resource_miss_observed',
            401 => 'credential_refused',
            403 => 'permission_refused',
            429 => 'rate_limited',
            default => $data['statusCode'] >= 500
                ? 'provider_unavailable'
                : ($data['statusCode'] >= 200
                    && $data['statusCode'] <= 299
                        ? 'unexpected_success_status'
                        : 'unexpected_provider_status'),
        };
        return $data['outcome'] === $expected ? $data : $indeterminate;
    }
}

if (!function_exists('red_addon_provider_contact_outcome_sha256')) {
    function red_addon_provider_contact_outcome_sha256(
        array $plan,
        array $outcome
    ) {
        $material = [
            'schema' => 1,
            'purpose' => 'provider-contact-loopback-outcome',
            'packageId' => $plan['packageId'] ?? '',
            'packageVersion' => $plan['packageVersion'] ?? '',
            'actorAdminRecordId' => (int) ($plan['actorAdminRecordId'] ?? 0),
            'planSha256' => $plan['planSha256'] ?? '',
            'claimStateSha256' => $plan['claimStateSha256'] ?? '',
            'executionStartStateSha256' =>
                $plan['executionStartStateSha256'] ?? '',
            'outcome' => $outcome,
        ];
        $encoded = red_addon_provider_contact_encode($material);
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_provider_contact_outcome_audit')) {
    function red_addon_provider_contact_outcome_audit(
        $connection,
        array $plan,
        array $outcome
    ) {
        $detail = $outcome['outcome'] === 'resource_miss_observed'
            ? 'provider_contact_loopback_resource_miss'
            : 'provider_contact_loopback_' . $outcome['outcome'];
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

if (!function_exists('red_addon_provider_contact_execute_loopback')) {
    function red_addon_provider_contact_execute_loopback(
        $connection,
        $projectRoot,
        $actorAdminRecordId,
        array $readiness,
        array $prepared,
        $expectedAuthorizationSha256,
        $expectedClaimStateSha256,
        $expectedExecutionStartStateSha256,
        $evaluatedAtUtc = null,
        $startAuditRecorder = null,
        $outcomeAuditRecorder = null
    ) {
        $result = red_addon_provider_contact_execution_result();
        if (!($connection instanceof mysqli)
            || !is_string($projectRoot)
            || $projectRoot === ''
            || (int) $actorAdminRecordId <= 0
            || !red_addon_provider_contact_sha256(
                $expectedAuthorizationSha256
            )
            || !red_addon_provider_contact_sha256(
                $expectedClaimStateSha256
            )
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
            ?? 'red_addon_provider_contact_execution_start_audit';
        $outcomeAuditRecorder = $outcomeAuditRecorder
            ?? 'red_addon_provider_contact_outcome_audit';
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
                if (!red_addon_provider_contact_lock_state(
                    $connection,
                    (int) $actorAdminRecordId
                )) {
                    throw new RuntimeException('execution_lock_failed');
                }
                $plan = red_addon_provider_contact_loopback_execution_plan(
                    $connection,
                    $package,
                    $catalog,
                    (int) $actorAdminRecordId,
                    $readiness,
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
                $reserved = red_addon_provider_contact_execution_reserve(
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

            $invocation = [];
            $access = null;
            try {
                $registry = red_addon_runtime_register_package($package);
                $adapterId = $packageId . '/checkout';
                $handler = $registry->handler('adapters', $adapterId);
                if (!is_callable($handler)) {
                    throw new RuntimeException('registrar_invalid');
                }
                $result['registrarValidated'] = true;
                $secret = red_addon_runtime_secret_access_for_package(
                    $connection,
                    $package,
                    true,
                    ['stripe.secret-key']
                );
                if (empty($secret['valid'])
                    || (int) ($secret['settingCount'] ?? 0) !== 1
                    || (int) ($secret['resolvedCount'] ?? 0) !== 1
                    || !(($secret['access'] ?? null)
                        instanceof RED_Addon_Runtime_Secret_Access)
                ) {
                    throw new RuntimeException('secret_resolution_failed');
                }
                $access = $secret['access'];
                $result['secretResolution'] = true;
                $invocation = red_addon_adapter_invoke_registered(
                    $adapterId,
                    'provider-contact.read-only-probe-loopback',
                    [
                        'contactTarget' => 'loopback',
                        'contactPlan' => $readiness['contactPlan'],
                        'planSha256' => $plan['planSha256'],
                        'claimStateSha256' => $plan['claimStateSha256'],
                        'executionStartStateSha256' =>
                            $plan['executionStartStateSha256'],
                    ],
                    $packageId,
                    $handler,
                    $package['manifest'],
                    $access
                );
                $result['adapterInvoked'] = !empty($invocation['invoked']);
            } catch (Throwable $throwable) {
                $invocation = [];
            }
            unset($access, $secret, $registry, $handler);

            $outcome = red_addon_provider_contact_loopback_outcome(
                is_array($invocation) ? $invocation : []
            );
            $result['boundedOutcome'] = $outcome;
            $result['executionPerformed'] =
                ($outcome['executionPerformed'] ?? false) === true;
            $result['outcomeStateSha256'] =
                red_addon_provider_contact_outcome_sha256($plan, $outcome);
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
                $startRow = red_addon_provider_contact_claim_execution_row(
                    $connection,
                    $plan['packageId'],
                    $plan['executionStartActionId'],
                    true
                );
                $outcomeRow = red_addon_provider_contact_claim_execution_row(
                    $connection,
                    $plan['packageId'],
                    $plan['outcomeActionId'],
                    true
                );
                if (empty($startRow['valid'])
                    || empty($startRow['found'])
                    || !is_array($startRow['row'])
                    || !red_addon_provider_contact_execution_start_matches(
                        $startRow['row'],
                        $plan
                    )
                    || empty($outcomeRow['valid'])
                    || !empty($outcomeRow['found'])
                ) {
                    throw new RuntimeException('outcome_state_changed');
                }
                $reserved = red_addon_provider_contact_execution_reserve(
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
