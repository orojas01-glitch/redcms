<?php
/**
 * Guarded Owner-authorized transition from installed_disabled to enabled.
 *
 * The transition is intentionally narrower than the manifest contract. It
 * accepts only the preflight's registration-only service or core-rendered
 * default public component profile, validates the fixed first-party registrar
 * under the package advisory lock, and then persists state plus its bounded
 * audit fact in one transaction.
 */

require_once __DIR__ . '/addon_enable_preflight_helpers.php';
require_once __DIR__ . '/addon_install_helpers.php';
require_once __DIR__ . '/addon_runtime_helpers.php';

if (!function_exists('red_addon_enable_transition_plan')) {
    function red_addon_enable_transition_plan(
        $connection,
        array $package,
        $actorAdminRecordId,
        array $catalog
    ) {
        $preflight = red_addon_enable_preflight_plan(
            $connection,
            $package,
            $actorAdminRecordId,
            $catalog
        );
        $plan = [
            'valid' => false,
            'transitionReady' => false,
            'database' => (string) ($preflight['database'] ?? ''),
            'packageId' => (string) ($preflight['packageId'] ?? ''),
            'version' => (string) ($preflight['version'] ?? ''),
            'currentState' => (string) ($preflight['currentState'] ?? ''),
            'targetState' => 'enabled',
            'preflightPlanSha256' => (string) ($preflight['planSha256'] ?? ''),
            'activationProfile' => $preflight['activationProfile'] ?? [],
            'planSha256' => '',
            'errors' => [],
        ];
        if (empty($preflight['valid'])) {
            $plan['errors'] = is_array($preflight['errors'] ?? null)
                ? $preflight['errors']
                : ['preflight_invalid'];
            return $plan;
        }
        $activationProfileId =
            (string) ($preflight['activationProfile']['id'] ?? '');
        if (empty($preflight['declarativeGatesReady'])
            || !in_array(
                $activationProfileId,
                ['registration_only_service', 'default_public_component'],
                true
            )
        ) {
            $plan['errors'][] = 'supported_activation_profile_required';
            return $plan;
        }

        $material = [
            'database' => $plan['database'],
            'actorAdminRecordId' => (int) $actorAdminRecordId,
            'packageId' => $plan['packageId'],
            'version' => $plan['version'],
            'currentState' => $plan['currentState'],
            'targetState' => $plan['targetState'],
            'preflightPlanSha256' => $plan['preflightPlanSha256'],
            'activationProfile' => $plan['activationProfile'],
            'registrarValidation' => 'required',
            'stateMutation' => 'atomic_compare_and_swap',
        ];
        $encoded = json_encode(
            $material,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encoded)) {
            $plan['errors'][] = 'plan_encoding_failed';
            return $plan;
        }
        $plan['planSha256'] = hash('sha256', $encoded);
        $plan['transitionReady'] = true;
        $plan['valid'] = true;
        return $plan;
    }
}

if (!function_exists('red_addon_enable_audit_record')) {
    function red_addon_enable_audit_record(
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
        ) {
            return false;
        }
        return red_addon_install_audit_record(
            $connection,
            'addon.enable.completed',
            $packageId,
            $packageVersion,
            $actorAdminRecordId,
            'succeeded',
            $detailCode
        );
    }
}

if (!function_exists('red_addon_enable_update_state')) {
    function red_addon_enable_update_state(
        $connection,
        array $snapshot,
        $actorAdminRecordId
    ) {
        $expectedState = 'installed_disabled';
        $targetState = 'enabled';
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $stmt = mysqli_prepare(
            $connection,
            'UPDATE RED_Addon_Installations
             SET LifecycleState=?, UpdatedByAdminRecordID=?
             WHERE PackageID=? AND PackageVersion=? AND PackageType=?
               AND ManifestSHA256=? AND InventorySHA256=?
               AND LifecycleState=?'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'sissssss',
            $targetState,
            $actorAdminRecordId,
            $snapshot['id'],
            $snapshot['version'],
            $snapshot['type'],
            $snapshot['manifestSha256'],
            $snapshot['inventorySha256'],
            $expectedState
        );
        $updated = mysqli_stmt_execute($stmt)
            && mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);
        return $updated;
    }
}

if (!function_exists('red_addon_enable_package')) {
    function red_addon_enable_package(
        $connection,
        $packageId,
        $projectRoot,
        $actorAdminRecordId,
        $expectedPlanSha256,
        $auditRecorder = null,
        $registrarExecutor = null,
        $afterStateUpdate = null
    ) {
        $result = [
            'status' => 'invalid',
            'packageId' => (string) $packageId,
            'version' => '',
            'runtimeRegistrations' => [],
        ];
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!red_addon_valid_package_id($packageId)
            || !red_addon_valid_sha256($expectedPlanSha256)
            || $actorAdminRecordId <= 0
            || !red_addon_install_storage_available($connection)
        ) {
            return $result;
        }
        $auditRecorder = $auditRecorder ?? 'red_addon_enable_audit_record';
        $registrarExecutor = $registrarExecutor
            ?? 'red_addon_runtime_register_package';
        if (!is_callable($auditRecorder) || !is_callable($registrarExecutor)
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
                if (empty($catalog['valid'])
                    || !isset($catalog['packages'][$packageId])
                ) {
                    $result['status'] = 'package_invalid';
                    return $result;
                }
                $package = $catalog['packages'][$packageId];
                $snapshot = red_addon_registry_snapshot($package);
                if ($snapshot === null) {
                    $result['status'] = 'package_invalid';
                    return $result;
                }
                $result['version'] = $snapshot['version'];
                $plan = red_addon_enable_transition_plan(
                    $connection,
                    $package,
                    $actorAdminRecordId,
                    $catalog
                );
                if (empty($plan['valid'])) {
                    $result['status'] = $plan['errors'][0] ?? 'plan_invalid';
                    return $result;
                }
                if (!hash_equals(
                    $expectedPlanSha256,
                    $plan['planSha256']
                )) {
                    $result['status'] = 'plan_changed';
                    return $result;
                }

                try {
                    $registry = $registrarExecutor($package);
                    if (!$registry instanceof RED_Addon_Runtime_Registry
                        || $registry->packageId() !== $snapshot['id']
                    ) {
                        $result['status'] = 'registrar_validation_failed';
                        return $result;
                    }
                    $result['runtimeRegistrations'] = $registry->snapshot();
                } catch (Throwable $throwable) {
                    error_log(
                        'RED-CMS add-on enable registrar validation failed for ' .
                        $packageId . ': ' . $throwable->getMessage()
                    );
                    $result['status'] = 'registrar_validation_failed';
                    return $result;
                }

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
                        'enabled'
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
