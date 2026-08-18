<?php
/**
 * Core-owned P3E-8B3C2 provider-operation runner boundary.
 *
 * This helper reuses the exact authorization, claim, durable-start, scoped
 * secret, typed invocation, bounded result, and no-retry lifecycle from
 * P3E-8B2. It accepts only adapter 0.1.4 with the provider_read_only profile.
 * Core contains no transport primitive or automatic caller; invoking this
 * helper can reach only the exact integrity-checked package operation.
 */

require_once __DIR__ . '/addon_provider_contact_execution_helpers.php';

if (!function_exists('red_addon_provider_contact_sandbox_start_sha256')) {
    function red_addon_provider_contact_sandbox_start_sha256(array $plan)
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
            'purpose' => 'provider-contact-sandbox-execution-start',
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
            'operation' => 'provider-contact.read-only-probe-sandbox',
            'contactTarget' => 'stripe-sandbox',
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

if (!function_exists('red_addon_provider_contact_sandbox_execution_plan')) {
    function red_addon_provider_contact_sandbox_execution_plan(
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
            || ($contactPlan['packageVersion'] ?? null) !== '0.1.4'
            || ($contactPlan['runtimeProviderTransport'] ?? null)
                !== 'provider_read_only'
        ) {
            $result['valid'] = false;
            $result['ready'] = false;
            $result['status'] = 'provider_profile_refused';
            $result['executionStartAvailable'] = false;
            $result['executionStartStateSha256'] = '';
            $result['errors'][] = 'provider_profile_refused';
            return $result;
        }
        $result['executionStartStateSha256'] =
            red_addon_provider_contact_sandbox_start_sha256($result);
        if (!red_addon_provider_contact_sha256(
            $result['executionStartStateSha256']
        )) {
            $result['valid'] = false;
            $result['ready'] = false;
            $result['status'] = 'provider_start_state_encoding_failed';
            $result['executionStartAvailable'] = false;
            $result['errors'][] = 'provider_start_state_encoding_failed';
        }
        return $result;
    }
}

if (!function_exists('red_addon_provider_contact_sandbox_outcome')) {
    function red_addon_provider_contact_sandbox_outcome(array $invocation)
    {
        // Once a trusted handler was invoked, conservatively record that
        // network/provider contact may have occurred even if its result is
        // missing or malformed. The one attempt remains permanently spent.
        $contactPossible = !empty($invocation['invoked']);
        $indeterminate = [
            'valid' => true,
            'contactTarget' => 'stripe-sandbox',
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
            'networkAccess' => $contactPossible,
            'providerContact' => $contactPossible,
            'executionPerformed' => $contactPossible,
            'errors' => ['provider_execution_indeterminate'],
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
            || ($data['contactTarget'] ?? null) !== 'stripe-sandbox'
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
            || ($data['networkAccess'] ?? null) !== true
            || ($data['providerContact'] ?? null) !== true
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

if (!function_exists('red_addon_provider_contact_sandbox_outcome_sha256')) {
    function red_addon_provider_contact_sandbox_outcome_sha256(
        array $plan,
        array $outcome
    ) {
        $material = [
            'schema' => 1,
            'purpose' => 'provider-contact-sandbox-outcome',
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

if (!function_exists('red_addon_provider_contact_sandbox_outcome_audit')) {
    function red_addon_provider_contact_sandbox_outcome_audit(
        $connection,
        array $plan,
        array $outcome
    ) {
        $detail = $outcome['outcome'] === 'resource_miss_observed'
            ? 'provider_contact_sandbox_resource_miss'
            : 'provider_contact_sandbox_' . $outcome['outcome'];
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

if (!function_exists('red_addon_provider_contact_execute_sandbox')) {
    function red_addon_provider_contact_execute_sandbox(
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
            ?? 'red_addon_provider_contact_sandbox_outcome_audit';
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
                $plan = red_addon_provider_contact_sandbox_execution_plan(
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
                    'provider-contact.read-only-probe-sandbox',
                    [
                        'contactTarget' => 'stripe-sandbox',
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

            $outcome = red_addon_provider_contact_sandbox_outcome(
                is_array($invocation) ? $invocation : []
            );
            $result['boundedOutcome'] = $outcome;
            $result['executionPerformed'] =
                ($outcome['executionPerformed'] ?? false) === true;
            $result['networkAccess'] =
                ($outcome['networkAccess'] ?? false) === true;
            $result['providerContact'] =
                ($outcome['providerContact'] ?? false) === true;
            $result['outcomeStateSha256'] =
                red_addon_provider_contact_sandbox_outcome_sha256(
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
