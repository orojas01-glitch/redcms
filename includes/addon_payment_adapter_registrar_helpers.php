<?php
/**
 * Registration-only validation for the closed P3A payment-adapter profile.
 *
 * The public boundary refreshes the read-only P3A-2 database evidence before
 * the fixed integrity-checked registrar is loaded. The request-local registry
 * is reduced to value-free identifiers and discarded. No registered handler
 * is invoked and no registry is published to request runtime.
 */

require_once __DIR__ . '/addon_payment_adapter_database_preflight_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';

if (!function_exists('red_addon_payment_adapter_registrar_result')) {
    function red_addon_payment_adapter_registrar_result($packageId = '')
    {
        return [
            'valid' => false,
            'profileId' => 'store_lite_stripe_checkout_adapter_v1',
            'registrarEvidenceReady' => false,
            'enableReady' => false,
            'activationSupported' => false,
            'stateMutation' => false,
            'runtimePublication' => false,
            'packageExecutionAttempted' => false,
            'registrarExecutionCompleted' => false,
            'handlerInvocation' => false,
            'secretResolution' => false,
            'networkAccess' => false,
            'routeExposure' => false,
            'packageId' => is_string($packageId) ? $packageId : '',
            'version' => '',
            'adapter' => '',
            'serverEventRoute' => '',
            'registrationCount' => 0,
            'contractSha256' => '',
            'databasePlanSha256' => '',
            'manifestSha256' => '',
            'inventorySha256' => '',
            'registrationSha256' => '',
            'gates' => [
                'adapterContract' => 'not_checked',
                'databaseEvidence' => 'not_checked',
                'registrarValidation' => 'not_checked',
                'serverEventIngress' => 'not_implemented',
                'atomicEnablement' => 'not_implemented',
            ],
            'blockers' => [],
            'planSha256' => '',
            'errors' => [],
        ];
    }
}

if (!function_exists('red_addon_payment_adapter_expected_registrations')) {
    function red_addon_payment_adapter_expected_registrations(array $profile)
    {
        if (!red_addon_payment_adapter_profile_is_valid($profile)) {
            return null;
        }
        return [
            'components' => [],
            'services' => [],
            'adminTools' => [],
            'adapters' => [$profile['adapter']],
            'adminToolActions' => [],
            'adminToolActionStateLoaders' => [],
            'adminToolFormValueLoaders' => [],
            'adminToolFormTargetLoaders' => [],
            'adminToolFormWriters' => [],
            'adminToolFormInitialValueLoaders' => [],
            'adminToolFormCreators' => [],
            'publicMutationHandlers' => [],
            'publicMutationStateLoaders' => [],
            'componentDataLoaders' => [],
            'componentDataCreators' => [],
            'componentDataWriters' => [],
            'componentDataDeleters' => [],
            'routes' => [$profile['serverEventRoute']],
        ];
    }
}

if (!function_exists('red_addon_payment_adapter_registrar_fingerprint')) {
    function red_addon_payment_adapter_registrar_fingerprint(array $plan)
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

if (!function_exists('red_addon_payment_adapter_registration_fingerprint')) {
    function red_addon_payment_adapter_registration_fingerprint(
        array $result,
        array $registrations
    ) {
        $encoded = json_encode(
            [
                'schema' => 1,
                'profileId' => 'store_lite_stripe_checkout_adapter_v1',
                'packageId' => $result['packageId'],
                'version' => $result['version'],
                'contractSha256' => $result['contractSha256'],
                'databasePlanSha256' => $result['databasePlanSha256'],
                'manifestSha256' => $result['manifestSha256'],
                'inventorySha256' => $result['inventorySha256'],
                'registrations' => $registrations,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_payment_adapter_validate_registrar')) {
    function red_addon_payment_adapter_validate_registrar(
        array $package,
        array $databasePlan
    ) {
        $packageId = is_string($package['id'] ?? null) ? $package['id'] : '';
        $result = red_addon_payment_adapter_registrar_result($packageId);
        if (!red_addon_payment_adapter_database_preflight_is_valid(
            $databasePlan
        ) || empty($databasePlan['databaseEvidenceReady'])) {
            $result['errors'][] = 'database_payment_adapter_evidence_invalid';
            return $result;
        }
        $result['databasePlanSha256'] = $databasePlan['planSha256'];
        $result['gates']['databaseEvidence'] = 'passed';

        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $profile = red_addon_payment_adapter_profile($manifest);
        if (!red_addon_payment_adapter_profile_is_valid($profile)
            || !hash_equals(
                $profile['contractSha256'],
                (string) ($databasePlan['contractSha256'] ?? '')
            )
            || ($databasePlan['packageId'] ?? null) !== $packageId
        ) {
            $result['errors'][] = 'payment_adapter_contract_mismatch';
            return $result;
        }
        $result['contractSha256'] = $profile['contractSha256'];
        $result['adapter'] = $profile['adapter'];
        $result['serverEventRoute'] = $profile['serverEventRoute'];
        $result['gates']['adapterContract'] = 'passed';

        $snapshot = red_addon_registry_snapshot($package);
        if (!is_array($snapshot)
            || ($snapshot['id'] ?? null) !== $packageId
            || ($snapshot['version'] ?? null)
                !== ($databasePlan['version'] ?? null)
            || !red_addon_valid_sha256($snapshot['manifestSha256'] ?? null)
            || !red_addon_valid_sha256($snapshot['inventorySha256'] ?? null)
        ) {
            $result['errors'][] = 'payment_adapter_package_invalid';
            return $result;
        }
        $result['version'] = $snapshot['version'];
        $result['manifestSha256'] = $snapshot['manifestSha256'];
        $result['inventorySha256'] = $snapshot['inventorySha256'];

        $expected = red_addon_payment_adapter_expected_registrations($profile);
        $result['packageExecutionAttempted'] = true;
        try {
            $registry = red_addon_runtime_register_package($package);
        } catch (Throwable $throwable) {
            $result['errors'][] = 'payment_adapter_registrar_execution_failed';
            return $result;
        }
        $result['registrarExecutionCompleted'] = true;
        $runtimeSnapshot = $registry->snapshot();
        $registrations = is_array($runtimeSnapshot['registrations'] ?? null)
            ? $runtimeSnapshot['registrations']
            : [];
        if (!is_array($expected)
            || ($runtimeSnapshot['packageId'] ?? null) !== $packageId
            || $registry->manifest() !== $manifest
            || array_keys($registrations) !== array_keys($expected)
            || $registrations !== $expected
        ) {
            $result['errors'][] = 'payment_adapter_registration_shape_invalid';
            return $result;
        }
        foreach ($registrations as $ids) {
            $result['registrationCount'] += count($ids);
        }
        if ($result['registrationCount'] !== 2) {
            $result['errors'][] = 'payment_adapter_registration_shape_invalid';
            return $result;
        }

        $result['registrationSha256'] =
            red_addon_payment_adapter_registration_fingerprint(
                $result,
                $registrations
            );
        if (!red_addon_valid_sha256($result['registrationSha256'])) {
            $result['errors'][] = 'payment_adapter_registration_encoding_failed';
            $result['registrationSha256'] = '';
            return $result;
        }
        $result['gates']['registrarValidation'] = 'passed';
        $result['blockers'] = [
            ['code' => 'atomic_payment_adapter_enablement_required'],
            ['code' => 'server_event_ingress_required'],
        ];
        $result['registrarEvidenceReady'] = true;
        $result['planSha256'] =
            red_addon_payment_adapter_registrar_fingerprint($result);
        if (!red_addon_valid_sha256($result['planSha256'])) {
            $result['errors'][] = 'plan_encoding_failed';
            $result['planSha256'] = '';
            $result['registrarEvidenceReady'] = false;
            return $result;
        }
        $result['valid'] = true;
        return $result;
    }
}

if (!function_exists('red_addon_payment_adapter_registrar_preflight')) {
    function red_addon_payment_adapter_registrar_preflight(
        $connection,
        array $package,
        $actorAdminRecordId,
        array $catalog
    ) {
        $databasePlan = red_addon_payment_adapter_database_preflight(
            $connection,
            $package,
            $actorAdminRecordId,
            $catalog
        );
        return red_addon_payment_adapter_validate_registrar(
            $package,
            $databasePlan
        );
    }
}

if (!function_exists('red_addon_payment_adapter_registrar_preflight_is_valid')) {
    function red_addon_payment_adapter_registrar_preflight_is_valid($plan)
    {
        if (!is_array($plan)
            || array_keys($plan) !== array_keys(
                red_addon_payment_adapter_registrar_result('')
            )
            || empty($plan['valid'])
            || ($plan['profileId'] ?? null)
                !== 'store_lite_stripe_checkout_adapter_v1'
            || empty($plan['registrarEvidenceReady'])
            || ($plan['enableReady'] ?? null) !== false
            || ($plan['activationSupported'] ?? null) !== false
            || ($plan['stateMutation'] ?? null) !== false
            || ($plan['runtimePublication'] ?? null) !== false
            || ($plan['packageExecutionAttempted'] ?? null) !== true
            || ($plan['registrarExecutionCompleted'] ?? null) !== true
            || ($plan['handlerInvocation'] ?? null) !== false
            || ($plan['secretResolution'] ?? null) !== false
            || ($plan['networkAccess'] ?? null) !== false
            || ($plan['routeExposure'] ?? null) !== false
            || !red_addon_valid_package_id($plan['packageId'] ?? null)
            || !red_addon_valid_semantic_version($plan['version'] ?? null)
            || !red_addon_valid_capability($plan['adapter'] ?? null)
            || !red_addon_valid_capability($plan['serverEventRoute'] ?? null)
            || strpos($plan['adapter'], $plan['packageId'] . '/') !== 0
            || strpos(
                $plan['serverEventRoute'],
                $plan['packageId'] . '/'
            ) !== 0
            || ($plan['registrationCount'] ?? null) !== 2
            || !red_addon_valid_sha256($plan['contractSha256'] ?? null)
            || !red_addon_valid_sha256($plan['databasePlanSha256'] ?? null)
            || !red_addon_valid_sha256($plan['manifestSha256'] ?? null)
            || !red_addon_valid_sha256($plan['inventorySha256'] ?? null)
            || !red_addon_valid_sha256($plan['registrationSha256'] ?? null)
            || ($plan['gates'] ?? null) !== [
                'adapterContract' => 'passed',
                'databaseEvidence' => 'passed',
                'registrarValidation' => 'passed',
                'serverEventIngress' => 'not_implemented',
                'atomicEnablement' => 'not_implemented',
            ]
            || ($plan['blockers'] ?? null) !== [
                ['code' => 'atomic_payment_adapter_enablement_required'],
                ['code' => 'server_event_ingress_required'],
            ]
            || ($plan['errors'] ?? null) !== []
            || !red_addon_valid_sha256($plan['planSha256'] ?? null)
            || !hash_equals(
                $plan['planSha256'],
                red_addon_payment_adapter_registrar_fingerprint($plan)
            )
        ) {
            return false;
        }
        return true;
    }
}

?>
