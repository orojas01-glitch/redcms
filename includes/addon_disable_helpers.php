<?php
/**
 * Guarded Owner-authorized transition from enabled to installed_disabled.
 *
 * Disablement is intentionally non-executing and non-destructive. It
 * revalidates the complete registry under the database-wide lifecycle lock,
 * refuses enabled dependents, and atomically records state plus one bounded
 * audit fact. It never includes package PHP, runs migrations, removes code,
 * or deletes package data.
 */

require_once __DIR__ . '/addon_install_helpers.php';

if (!function_exists('red_addon_disable_sort_records')) {
    function red_addon_disable_sort_records(array &$records)
    {
        usort($records, static function ($left, $right) {
            $leftJson = json_encode(
                $left,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            $rightJson = json_encode(
                $right,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            return strcmp((string) $leftJson, (string) $rightJson);
        });
    }
}

if (!function_exists('red_addon_disable_transition_plan')) {
    function red_addon_disable_transition_plan(
        $connection,
        array $package,
        $actorAdminRecordId,
        array $catalog
    ) {
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $plan = [
            'valid' => false,
            'transitionReady' => false,
            'database' => red_addon_install_database_name($connection),
            'packageId' => isset($package['id']) && is_string($package['id'])
                ? $package['id']
                : '',
            'version' => '',
            'currentState' => '',
            'targetState' => 'installed_disabled',
            'enabledPackages' => [],
            'enabledDependents' => [],
            'blockers' => [],
            'planSha256' => '',
            'errors' => [],
        ];
        if (!red_addon_install_storage_available($connection)) {
            $plan['errors'][] = 'disable_storage_unavailable';
            return $plan;
        }
        if (empty($package['valid']) || empty($catalog['valid'])) {
            $plan['errors'][] = 'package_catalog_invalid';
            return $plan;
        }
        $snapshot = red_addon_registry_snapshot($package);
        if ($snapshot === null) {
            $plan['errors'][] = 'package_snapshot_invalid';
            return $plan;
        }
        $plan['packageId'] = $snapshot['id'];
        $plan['version'] = $snapshot['version'];

        $actor = red_admin_addon_database_actor(
            $connection,
            $actorAdminRecordId
        );
        if (!red_addon_registry_actor_can_transition($actor, 'disable')) {
            $plan['errors'][] = 'owner_disable_capability_required';
            return $plan;
        }

        $registryCatalog = red_addon_registry_catalog_report(
            $connection,
            $catalog
        );
        if (empty($registryCatalog['valid'])) {
            $plan['errors'][] = 'registry_reconciliation_failed';
            return $plan;
        }
        $packageReport = $registryCatalog['packages'][$snapshot['id']] ?? null;
        if (!is_array($packageReport)
            || ($packageReport['status'] ?? '') !== 'enabled_current'
            || ($packageReport['lifecycleState'] ?? '') !== 'enabled'
        ) {
            $plan['errors'][] = 'package_not_enabled_current';
            return $plan;
        }
        $plan['currentState'] = 'enabled';

        foreach ($registryCatalog['packages'] as $packageId => $report) {
            if ($packageId === $snapshot['id']
                || !is_array($report)
                || ($report['lifecycleState'] ?? '') !== 'enabled'
            ) {
                continue;
            }
            if (($report['status'] ?? '') !== 'enabled_current'
                || !isset($catalog['packages'][$packageId])
                || !is_array($catalog['packages'][$packageId])
            ) {
                $plan['errors'][] = 'enabled_package_not_current';
                return $plan;
            }
            $enabledPackage = $catalog['packages'][$packageId];
            $enabledSnapshot = red_addon_registry_snapshot($enabledPackage);
            if ($enabledSnapshot === null) {
                $plan['errors'][] = 'enabled_package_snapshot_invalid';
                return $plan;
            }
            $plan['enabledPackages'][] = [
                'id' => $enabledSnapshot['id'],
                'version' => $enabledSnapshot['version'],
                'manifestSha256' => $enabledSnapshot['manifestSha256'],
                'inventorySha256' => $enabledSnapshot['inventorySha256'],
                'lifecycleState' => 'enabled',
            ];

            $manifest = is_array($enabledPackage['manifest'] ?? null)
                ? $enabledPackage['manifest']
                : [];
            $required = is_array(
                $manifest['dependencies']['required'] ?? null
            )
                ? $manifest['dependencies']['required']
                : [];
            foreach ($required as $dependency) {
                $dependencyId = is_array($dependency)
                    && isset($dependency['id'])
                    && is_string($dependency['id'])
                    ? $dependency['id']
                    : '';
                if ($dependencyId !== $snapshot['id']) {
                    continue;
                }
                $versionRange = isset($dependency['version'])
                    && is_string($dependency['version'])
                    ? $dependency['version']
                    : '';
                $dependent = [
                    'id' => $enabledSnapshot['id'],
                    'version' => $enabledSnapshot['version'],
                    'versionRange' => $versionRange,
                    'manifestSha256' => $enabledSnapshot['manifestSha256'],
                    'inventorySha256' => $enabledSnapshot['inventorySha256'],
                    'lifecycleState' => 'enabled',
                ];
                $plan['enabledDependents'][] = $dependent;
                $plan['blockers'][] = [
                    'code' => 'enabled_dependent_requires_package',
                    'packageId' => $enabledSnapshot['id'],
                    'versionRange' => $versionRange,
                ];
            }
        }
        red_addon_disable_sort_records($plan['enabledPackages']);
        red_addon_disable_sort_records($plan['enabledDependents']);
        red_addon_disable_sort_records($plan['blockers']);
        $plan['transitionReady'] = $plan['blockers'] === [];

        $material = [
            'database' => $plan['database'],
            'actorAdminRecordId' => $actorAdminRecordId,
            'package' => [
                'id' => $snapshot['id'],
                'version' => $snapshot['version'],
                'type' => $snapshot['type'],
                'manifestSha256' => $snapshot['manifestSha256'],
                'inventorySha256' => $snapshot['inventorySha256'],
            ],
            'currentState' => $plan['currentState'],
            'targetState' => $plan['targetState'],
            'enabledPackages' => $plan['enabledPackages'],
            'enabledDependents' => $plan['enabledDependents'],
            'blockers' => $plan['blockers'],
            'packageExecution' => false,
            'migrationExecution' => false,
            'dataDeletion' => false,
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
        $plan['valid'] = true;
        return $plan;
    }
}

if (!function_exists('red_addon_disable_audit_record')) {
    function red_addon_disable_audit_record(
        $connection,
        $eventName,
        $packageId,
        $packageVersion,
        $actorAdminRecordId,
        $result,
        $detailCode
    ) {
        if ($eventName !== 'addon.disable.completed'
            || $result !== 'succeeded'
        ) {
            return false;
        }
        return red_addon_install_audit_record(
            $connection,
            'addon.disable.completed',
            $packageId,
            $packageVersion,
            $actorAdminRecordId,
            'succeeded',
            $detailCode
        );
    }
}

if (!function_exists('red_addon_disable_update_state')) {
    function red_addon_disable_update_state(
        $connection,
        array $snapshot,
        $actorAdminRecordId
    ) {
        $expectedState = 'enabled';
        $targetState = 'installed_disabled';
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

if (!function_exists('red_addon_disable_package')) {
    function red_addon_disable_package(
        $connection,
        $packageId,
        $projectRoot,
        $actorAdminRecordId,
        $expectedPlanSha256,
        $auditRecorder = null,
        $afterStateUpdate = null
    ) {
        $result = [
            'status' => 'invalid',
            'packageId' => (string) $packageId,
            'version' => '',
            'enabledDependents' => [],
        ];
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!red_addon_valid_package_id($packageId)
            || !red_addon_valid_sha256($expectedPlanSha256)
            || $actorAdminRecordId <= 0
            || !red_addon_install_storage_available($connection)
        ) {
            return $result;
        }
        $auditRecorder = $auditRecorder ?? 'red_addon_disable_audit_record';
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
                $plan = red_addon_disable_transition_plan(
                    $connection,
                    $package,
                    $actorAdminRecordId,
                    $catalog
                );
                if (empty($plan['valid'])) {
                    $result['status'] = $plan['errors'][0]
                        ?? 'plan_invalid';
                    return $result;
                }
                $result['enabledDependents'] =
                    $plan['enabledDependents'];
                if (!hash_equals(
                    $expectedPlanSha256,
                    $plan['planSha256']
                )) {
                    $result['status'] = 'plan_changed';
                    return $result;
                }
                if (empty($plan['transitionReady'])) {
                    $result['status'] = $plan['blockers'][0]['code']
                        ?? 'disable_blocked';
                    return $result;
                }

                if (!mysqli_begin_transaction($connection)) {
                    $result['status'] = 'transaction_failed';
                    return $result;
                }
                try {
                    if (!red_addon_disable_update_state(
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
                        'addon.disable.completed',
                        $snapshot['id'],
                        $snapshot['version'],
                        $actorAdminRecordId,
                        'succeeded',
                        'installed_disabled'
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
                        : 'disable_transaction_failed';
                    return $result;
                }

                $result['status'] = 'installed_disabled';
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
