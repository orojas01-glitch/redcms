<?php
/**
 * Owner-authorized atomic enablement for the closed P3A adapter profile.
 *
 * Planning composes fresh database, registration-only, ingress, and value-free
 * configuration evidence. Applying recomputes that complete plan under the
 * lifecycle and package locks, then commits only the installed-disabled to
 * enabled compare-and-swap and its bounded audit fact. No registered handler
 * is invoked, no secret value is resolved, no route is exposed, and no network
 * request is opened.
 */

require_once __DIR__ .
    '/addon_payment_adapter_server_event_ingress_helpers.php';
require_once __DIR__ . '/addon_secret_availability_storage_helpers.php';
require_once __DIR__ . '/addon_enable_helpers.php';

if (!function_exists('red_addon_payment_adapter_enablement_result')) {
    function red_addon_payment_adapter_enablement_result($packageId = '')
    {
        $packageId = is_string($packageId) ? $packageId : '';
        return [
            'valid' => false,
            'profileId' => $packageId === 'redcms.store-lite-wompi'
                ? 'store_lite_wompi_adapter_v1'
                : 'store_lite_stripe_checkout_adapter_v1',
            'enableReady' => false,
            'activationSupported' => false,
            'stateMutation' => false,
            'runtimePublication' => false,
            'handlerInvocation' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'routeExposure' => false,
            'packageExecutionAttempted' => false,
            'registrarExecutionCompleted' => false,
            'packageId' => $packageId,
            'version' => '',
            'currentState' => '',
            'targetState' => 'enabled',
            'settingCount' => 0,
            'configuredSettingCount' => 0,
            'secretSettingCount' => 0,
            'availableSecretCount' => 0,
            'databaseSha256' => '',
            'contractSha256' => '',
            'databasePlanSha256' => '',
            'registrarPlanSha256' => '',
            'registrationSha256' => '',
            'ingressPlanSha256' => '',
            'ingressContractSha256' => '',
            'settingsStateSha256' => '',
            'secretAvailabilitySha256' => '',
            'manifestSha256' => '',
            'inventorySha256' => '',
            'gates' => [
                'adapterContract' => 'not_checked',
                'databaseEvidence' => 'not_checked',
                'registrarValidation' => 'not_checked',
                'serverEventIngress' => 'not_checked',
                'settingsConfiguration' => 'not_checked',
                'secretAvailability' => 'not_checked',
                'atomicEnablement' => 'not_checked',
            ],
            'blockers' => [],
            'planSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_payment_adapter_enablement_fingerprint')) {
    function red_addon_payment_adapter_enablement_fingerprint(array $plan)
    {
        $material = $plan;
        unset($material['valid'], $material['planSha256']);
        $encoded = json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_payment_adapter_enablement_plan')) {
    function red_addon_payment_adapter_enablement_plan(
        $connection,
        array $package,
        $actorAdminRecordId,
        array $catalog,
        $declarations = null
    ) {
        $packageId = is_string($package['id'] ?? null) ? $package['id'] : '';
        $result = red_addon_payment_adapter_enablement_result($packageId);
        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $profile = red_addon_payment_adapter_profile($manifest);
        if (!red_addon_payment_adapter_profile_is_valid($profile)) {
            $result['errors'][] = 'payment_adapter_contract_invalid';
            return $result;
        }
        $result['contractSha256'] = $profile['contractSha256'];
        $result['profileId'] = $profile['profileId'];
        $result['settingCount'] =
            $profile['ordinarySettingCount'] + $profile['secretSettingCount'];
        $result['secretSettingCount'] = $profile['secretSettingCount'];
        $result['gates']['adapterContract'] = 'passed';

        $databasePlan = red_addon_payment_adapter_database_preflight(
            $connection,
            $package,
            $actorAdminRecordId,
            $catalog
        );
        if (!red_addon_payment_adapter_database_preflight_is_valid(
            $databasePlan
        ) || empty($databasePlan['databaseEvidenceReady'])) {
            $result['errors'][] = 'database_payment_adapter_evidence_invalid';
            return $result;
        }
        $result['databaseSha256'] = $databasePlan['databaseSha256'];
        $result['databasePlanSha256'] = $databasePlan['planSha256'];
        $result['version'] = $databasePlan['version'];
        $result['currentState'] = $databasePlan['currentState'];
        $result['gates']['databaseEvidence'] = 'passed';

        $registrarPlan = red_addon_payment_adapter_validate_registrar(
            $package,
            $databasePlan
        );
        $result['packageExecutionAttempted'] = !empty(
            $registrarPlan['packageExecutionAttempted']
        );
        $result['registrarExecutionCompleted'] = !empty(
            $registrarPlan['registrarExecutionCompleted']
        );
        if (!red_addon_payment_adapter_registrar_preflight_is_valid(
            $registrarPlan
        )) {
            $result['errors'][] = 'payment_adapter_registrar_evidence_invalid';
            return $result;
        }
        $result['registrarPlanSha256'] = $registrarPlan['planSha256'];
        $result['registrationSha256'] =
            $registrarPlan['registrationSha256'];
        $result['manifestSha256'] = $registrarPlan['manifestSha256'];
        $result['inventorySha256'] = $registrarPlan['inventorySha256'];
        $result['gates']['registrarValidation'] = 'passed';

        $ingressPlan =
            red_addon_payment_adapter_server_event_ingress_plan(
                $package,
                $registrarPlan
            );
        if (!red_addon_payment_adapter_server_event_ingress_plan_is_valid(
            $ingressPlan
        )) {
            $result['errors'][] = 'payment_adapter_ingress_evidence_invalid';
            return $result;
        }
        $result['ingressPlanSha256'] = $ingressPlan['planSha256'];
        $result['ingressContractSha256'] =
            $ingressPlan['ingressContractSha256'];
        $result['gates']['serverEventIngress'] = 'passed';

        $settings = red_addon_secret_availability_storage_evidence(
            $connection,
            $manifest,
            $packageId,
            $declarations
        );
        if (empty($settings['valid'])) {
            $result['errors'][] = 'payment_adapter_configuration_invalid';
            return $result;
        }
        $result['configuredSettingCount'] = $result['settingCount'];
        $result['availableSecretCount'] = (int) (
            $settings['availableCount'] ?? 0
        );
        $result['settingsStateSha256'] = (string) (
            $settings['configurationSha256'] ?? ''
        );
        $result['secretAvailabilitySha256'] = (string) (
            $settings['evidenceSha256'] ?? ''
        );
        if (!red_addon_valid_sha256($result['settingsStateSha256'])
            || !red_addon_valid_sha256(
                $result['secretAvailabilitySha256']
            )
            || ($settings['secretSettingCount'] ?? null)
                !== $result['secretSettingCount']
            || $result['availableSecretCount']
                !== $result['secretSettingCount']
            || empty($settings['available'])
        ) {
            $result['errors'][] = 'payment_adapter_configuration_incomplete';
            return $result;
        }
        $result['gates']['settingsConfiguration'] = 'passed';
        $result['gates']['secretAvailability'] = 'passed';

        $snapshot = red_addon_registry_snapshot($package);
        if (!is_array($snapshot)
            || $snapshot['id'] !== $packageId
            || $snapshot['version'] !== $result['version']
            || !hash_equals(
                $snapshot['manifestSha256'],
                $result['manifestSha256']
            )
            || !hash_equals(
                $snapshot['inventorySha256'],
                $result['inventorySha256']
            )
        ) {
            $result['errors'][] = 'payment_adapter_identity_mismatch';
            return $result;
        }

        $result['gates']['atomicEnablement'] = 'ready';
        $result['activationSupported'] = true;
        $result['enableReady'] = true;
        $result['planSha256'] =
            red_addon_payment_adapter_enablement_fingerprint($result);
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['errors'][] = 'plan_encoding_failed';
            $result['planSha256'] = '';
            $result['activationSupported'] = false;
            $result['enableReady'] = false;
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_payment_adapter_enablement_plan_is_valid')) {
    function red_addon_payment_adapter_enablement_plan_is_valid($plan)
    {
        $planData = is_array($plan) ? $plan : [];
        $packageId = is_string($planData['packageId'] ?? null)
            ? $planData['packageId']
            : '';
        $expectedProfileId = red_addon_payment_adapter_enablement_result(
            $packageId
        )['profileId'];
        $expectedSecretCount = $expectedProfileId
            === 'store_lite_wompi_adapter_v1'
                ? 3
                : 2;
        if (!is_array($plan)
            || array_keys($plan) !== array_keys(
                red_addon_payment_adapter_enablement_result('')
            )
            || empty($plan['valid'])
            || ($plan['profileId'] ?? null) !== $expectedProfileId
            || empty($plan['enableReady'])
            || empty($plan['activationSupported'])
            || ($plan['stateMutation'] ?? null) !== false
            || ($plan['runtimePublication'] ?? null) !== false
            || ($plan['handlerInvocation'] ?? null) !== false
            || ($plan['secretResolution'] ?? null) !== false
            || ($plan['networkAccess'] ?? null) !== false
            || ($plan['routeExposure'] ?? null) !== false
            || ($plan['packageExecutionAttempted'] ?? null) !== true
            || ($plan['registrarExecutionCompleted'] ?? null) !== true
            || !red_addon_valid_package_id($plan['packageId'] ?? null)
            || !red_addon_valid_semantic_version($plan['version'] ?? null)
            || ($plan['currentState'] ?? null) !== 'installed_disabled'
            || ($plan['targetState'] ?? null) !== 'enabled'
            || !is_int($plan['settingCount'] ?? null)
            || $plan['settingCount'] < 2
            || $plan['settingCount'] > 8
            || ($plan['configuredSettingCount'] ?? null)
                !== $plan['settingCount']
            || ($plan['secretSettingCount'] ?? null)
                !== $expectedSecretCount
            || ($plan['availableSecretCount'] ?? null)
                !== $expectedSecretCount
            || !red_addon_valid_sha256($plan['databaseSha256'] ?? null)
            || !red_addon_valid_sha256($plan['contractSha256'] ?? null)
            || !red_addon_valid_sha256($plan['databasePlanSha256'] ?? null)
            || !red_addon_valid_sha256($plan['registrarPlanSha256'] ?? null)
            || !red_addon_valid_sha256($plan['registrationSha256'] ?? null)
            || !red_addon_valid_sha256($plan['ingressPlanSha256'] ?? null)
            || !red_addon_valid_sha256(
                $plan['ingressContractSha256'] ?? null
            )
            || !red_addon_valid_sha256($plan['settingsStateSha256'] ?? null)
            || !red_addon_valid_sha256(
                $plan['secretAvailabilitySha256'] ?? null
            )
            || !red_addon_valid_sha256($plan['manifestSha256'] ?? null)
            || !red_addon_valid_sha256($plan['inventorySha256'] ?? null)
            || ($plan['gates'] ?? null) !== [
                'adapterContract' => 'passed',
                'databaseEvidence' => 'passed',
                'registrarValidation' => 'passed',
                'serverEventIngress' => 'passed',
                'settingsConfiguration' => 'passed',
                'secretAvailability' => 'passed',
                'atomicEnablement' => 'ready',
            ]
            || ($plan['blockers'] ?? null) !== []
            || ($plan['errors'] ?? null) !== []
            || !red_addon_valid_sha256($plan['planSha256'] ?? null)
            || !hash_equals(
                $plan['planSha256'],
                red_addon_payment_adapter_enablement_fingerprint($plan)
            )
        ) {
            return false;
        }
        return true;
    }
}

if (!function_exists('red_addon_payment_adapter_enable_audit_record')) {
    function red_addon_payment_adapter_enable_audit_record(
        $connection,
        $eventName,
        $packageId,
        $packageVersion,
        $actorAdminRecordId,
        $result,
        $detailCode
    ) {
        if ($eventName !== 'addon.enable.completed'
            || $result !== 'succeeded'
            || $detailCode !== 'payment_adapter_enabled'
        ) {
            return false;
        }
        return red_addon_install_audit_record(
            $connection,
            $eventName,
            $packageId,
            $packageVersion,
            $actorAdminRecordId,
            $result,
            $detailCode
        );
    }
}

if (!function_exists('red_addon_payment_adapter_enable_package')) {
    function red_addon_payment_adapter_enable_package(
        $connection,
        $packageId,
        $projectRoot,
        $actorAdminRecordId,
        $expectedPlanSha256,
        $declarations = null,
        $auditRecorder = null,
        $afterStateUpdate = null
    ) {
        $result = [
            'status' => 'invalid',
            'packageId' => is_string($packageId) ? $packageId : '',
            'version' => '',
            'planSha256' => '',
            'registrationSha256' => '',
            'ingressContractSha256' => '',
            'settingsStateSha256' => '',
            'secretAvailabilitySha256' => '',
        ];
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!red_addon_valid_package_id($packageId)
            || !is_string($projectRoot)
            || $projectRoot === ''
            || !red_addon_valid_sha256($expectedPlanSha256)
            || $actorAdminRecordId <= 0
            || !red_addon_install_storage_available($connection)
        ) {
            return $result;
        }
        $auditRecorder = $auditRecorder
            ?? 'red_addon_payment_adapter_enable_audit_record';
        if (!is_callable($auditRecorder)
            || ($afterStateUpdate !== null && !is_callable($afterStateUpdate))
        ) {
            return $result;
        }
        if (!red_addon_lifecycle_lock($connection)) {
            $result['status'] = 'locked';
            return $result;
        }

        try {
            if (!red_addon_install_lock($connection, $packageId)) {
                $result['status'] = 'locked';
                return $result;
            }
            try {
                $catalog = red_addon_discover($projectRoot, [
                    'cmsVersion' => '5.1.0',
                    'phpVersion' => PHP_VERSION,
                ]);
                $package = $catalog['packages'][$packageId] ?? null;
                if (empty($catalog['valid']) || !is_array($package)) {
                    $result['status'] = 'package_invalid';
                    return $result;
                }
                $snapshot = red_addon_registry_snapshot($package);
                if (!is_array($snapshot)) {
                    $result['status'] = 'package_invalid';
                    return $result;
                }
                $result['version'] = $snapshot['version'];
                $plan = red_addon_payment_adapter_enablement_plan(
                    $connection,
                    $package,
                    $actorAdminRecordId,
                    $catalog,
                    $declarations
                );
                if (!red_addon_payment_adapter_enablement_plan_is_valid(
                    $plan
                )) {
                    $result['status'] = $plan['errors'][0]
                        ?? 'plan_invalid';
                    return $result;
                }
                if (!hash_equals(
                    $expectedPlanSha256,
                    $plan['planSha256']
                )) {
                    $result['status'] = 'plan_changed';
                    return $result;
                }
                $result['planSha256'] = $plan['planSha256'];
                $result['registrationSha256'] =
                    $plan['registrationSha256'];
                $result['ingressContractSha256'] =
                    $plan['ingressContractSha256'];
                $result['settingsStateSha256'] =
                    $plan['settingsStateSha256'];
                $result['secretAvailabilitySha256'] =
                    $plan['secretAvailabilitySha256'];

                if (!mysqli_begin_transaction($connection)) {
                    $result['status'] = 'transaction_failed';
                    return $result;
                }
                try {
                    if (!red_addon_enable_update_state(
                        $connection,
                        $snapshot,
                        $actorAdminRecordId
                    )) {
                        throw new RuntimeException(
                            'state_compare_and_swap_failed'
                        );
                    }
                    if ($afterStateUpdate !== null) {
                        $afterStateUpdate($connection, $snapshot);
                    }
                    if (!$auditRecorder(
                        $connection,
                        'addon.enable.completed',
                        $snapshot['id'],
                        $snapshot['version'],
                        $actorAdminRecordId,
                        'succeeded',
                        'payment_adapter_enabled'
                    )) {
                        throw new RuntimeException('audit_completion_failed');
                    }
                    if (!mysqli_commit($connection)) {
                        throw new RuntimeException(
                            'completion_commit_failed'
                        );
                    }
                } catch (Throwable $throwable) {
                    mysqli_rollback($connection);
                    $result['status'] = $throwable->getMessage()
                        === 'state_compare_and_swap_failed'
                            ? 'state_changed'
                            : 'enable_transaction_failed';
                    return $result;
                }
                $result['status'] = 'enabled';
                return $result;
            } finally {
                red_addon_install_unlock($connection, $packageId);
            }
        } finally {
            red_addon_lifecycle_unlock($connection);
        }
    }
}

?>
