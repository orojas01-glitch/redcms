<?php
/**
 * Owner-authorized disabled-package upgrade and exact failure recovery.
 *
 * Upgrade never includes addon.php. It accepts only an append-only migration
 * ledger, preserves stored setting definitions, runs while the package is
 * disabled, and finishes disabled. MySQL DDL can commit independently, so a
 * failed migration is retained as explicit upgrade_failed state and may be
 * resumed only from the exact reviewed target package.
 */

require_once __DIR__ . '/addon_install_helpers.php';

if (!function_exists('red_addon_upgrade_setting_evidence')) {
    function red_addon_upgrade_setting_evidence(
        $connection,
        array $manifest,
        $packageId,
        &$errorCode = ''
    ) {
        $errorCode = '';
        $definitions = [];
        foreach ($manifest['settings'] ?? [] as $definition) {
            if (!is_array($definition)
                || !isset($definition['key'], $definition['type'])
                || !is_string($definition['key'])
                || !is_string($definition['type'])
            ) {
                $errorCode = 'target_setting_definition_invalid';
                return [];
            }
            $definitions[$definition['key']] = [
                'key' => $definition['key'],
                'type' => $definition['type'],
                'secret' => !empty($definition['secret']),
            ];
        }
        ksort($definitions, SORT_STRING);

        $stored = [];
        try {
            $statement = mysqli_prepare(
                $connection,
                'SELECT SettingKey, ValueType,
                        CASE WHEN SecretReference IS NULL THEN 0 ELSE 1 END AS IsSecret
                 FROM RED_Addon_Settings
                 WHERE PackageID=?
                 ORDER BY SettingKey'
            );
            if (!$statement) {
                $errorCode = 'setting_storage_unavailable';
                return [];
            }
            mysqli_stmt_bind_param($statement, 's', $packageId);
            if (!mysqli_stmt_execute($statement)) {
                mysqli_stmt_close($statement);
                $errorCode = 'setting_storage_unavailable';
                return [];
            }
            $result = mysqli_stmt_get_result($statement);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $key = (string) $row['SettingKey'];
                $stored[$key] = [
                    'key' => $key,
                    'type' => (string) $row['ValueType'],
                    'secret' => (int) $row['IsSecret'] === 1,
                ];
            }
            mysqli_stmt_close($statement);
        } catch (Throwable $throwable) {
            $errorCode = 'setting_storage_unavailable';
            return [];
        }

        foreach ($stored as $key => $row) {
            if (!isset($definitions[$key])) {
                $errorCode = 'stored_setting_removed';
                return [];
            }
            if ($definitions[$key]['type'] !== $row['type']
                || $definitions[$key]['secret'] !== $row['secret']
            ) {
                $errorCode = 'stored_setting_contract_changed';
                return [];
            }
        }
        $encoded = json_encode(
            ['definitions' => array_values($definitions), 'stored' => array_values($stored)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($encoded)) {
            $errorCode = 'setting_evidence_encoding_failed';
            return [];
        }
        return [
            'definitionCount' => count($definitions),
            'storedCount' => count($stored),
            'contractSha256' => hash('sha256', $encoded),
        ];
    }
}

if (!function_exists('red_addon_upgrade_plan')) {
    function red_addon_upgrade_plan(
        $connection,
        array $package,
        $actorAdminRecordId,
        $allowResume = false,
        array $catalog = []
    ) {
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $plan = [
            'valid' => false,
            'database' => red_addon_install_database_name($connection),
            'packageId' => '',
            'currentVersion' => '',
            'targetVersion' => '',
            'currentState' => '',
            'completionState' => 'installed_disabled',
            'resume' => false,
            'requiredDependencies' => [],
            'settingEvidence' => [],
            'appliedMigrations' => [],
            'pendingMigrations' => [],
            'planSha256' => '',
            'errors' => [],
        ];
        if (!red_addon_install_storage_available($connection)) {
            $plan['errors'][] = 'upgrade_storage_unavailable';
            return $plan;
        }
        if (empty($package['valid']) || empty($catalog['valid'])) {
            $plan['errors'][] = 'package_catalog_invalid';
            return $plan;
        }
        $target = red_addon_registry_snapshot($package);
        if ($target === null) {
            $plan['errors'][] = 'target_snapshot_invalid';
            return $plan;
        }
        $plan['packageId'] = $target['id'];
        $plan['targetVersion'] = $target['version'];

        $actor = red_admin_addon_database_actor($connection, $actorAdminRecordId);
        if (!red_addon_registry_actor_can_transition($actor, 'upgrade')) {
            $plan['errors'][] = 'owner_upgrade_capability_required';
            return $plan;
        }
        $installation = red_addon_registry_installation($connection, $target['id']);
        if (!is_array($installation)) {
            $plan['errors'][] = 'package_not_installed';
            return $plan;
        }
        $plan['currentVersion'] = (string) ($installation['PackageVersion'] ?? '');
        $plan['currentState'] = (string) ($installation['LifecycleState'] ?? '');
        if (!red_addon_valid_semantic_version($plan['currentVersion'])
            || version_compare($target['version'], $plan['currentVersion'], '<=')
        ) {
            $plan['errors'][] = 'target_version_not_newer';
            return $plan;
        }
        if ((string) ($installation['PackageType'] ?? '') !== $target['type']
            || !red_addon_valid_sha256((string) ($installation['ManifestSHA256'] ?? ''))
            || !red_addon_valid_sha256((string) ($installation['InventorySHA256'] ?? ''))
        ) {
            $plan['errors'][] = 'installed_identity_invalid';
            return $plan;
        }
        if ($plan['currentState'] === 'installed_disabled') {
            $plan['resume'] = false;
        } elseif (in_array($plan['currentState'], ['upgrading', 'upgrade_failed'], true)) {
            if (!$allowResume) {
                $plan['errors'][] = 'resume_confirmation_required';
                return $plan;
            }
            $plan['resume'] = true;
        } else {
            $plan['errors'][] = 'package_must_be_disabled';
            return $plan;
        }

        $dependencyError = '';
        $plan['requiredDependencies'] = red_addon_install_dependency_evidence(
            $connection,
            $package,
            $catalog,
            $dependencyError
        );
        if ($dependencyError !== '') {
            $plan['errors'][] = $dependencyError;
            return $plan;
        }

        $settingError = '';
        $plan['settingEvidence'] = red_addon_upgrade_setting_evidence(
            $connection,
            $package['manifest'],
            $target['id'],
            $settingError
        );
        if ($settingError !== '') {
            $plan['errors'][] = $settingError;
            return $plan;
        }

        $applied = red_addon_registry_migrations($connection, $target['id']);
        foreach ($applied as $migrationId => $row) {
            if (!isset($target['migrations'][$migrationId])) {
                $plan['errors'][] = 'applied_migration_removed';
                return $plan;
            }
            $expected = $target['migrations'][$migrationId];
            if (!hash_equals($expected['path'], (string) ($row['MigrationPath'] ?? ''))
                || !hash_equals($expected['sha256'], (string) ($row['Checksum'] ?? ''))
            ) {
                $plan['errors'][] = 'applied_migration_drift';
                return $plan;
            }
            $plan['appliedMigrations'][] = $migrationId;
        }
        foreach ($target['migrations'] as $migrationId => $migration) {
            if (isset($applied[$migrationId])) {
                continue;
            }
            $errorCode = '';
            if (red_addon_install_migration_sql($package, $migration, $errorCode) === null) {
                $plan['errors'][] = $errorCode !== '' ? $errorCode : 'migration_preflight_failed';
                return $plan;
            }
            $plan['pendingMigrations'][] = $migrationId;
        }

        $material = [
            'database' => $plan['database'],
            'actorAdminRecordId' => $actorAdminRecordId,
            'packageId' => $target['id'],
            'current' => [
                'version' => $plan['currentVersion'],
                'type' => (string) $installation['PackageType'],
                'manifestSha256' => (string) $installation['ManifestSHA256'],
                'inventorySha256' => (string) $installation['InventorySHA256'],
                'state' => $plan['currentState'],
            ],
            'target' => [
                'version' => $target['version'],
                'type' => $target['type'],
                'manifestSha256' => $target['manifestSha256'],
                'inventorySha256' => $target['inventorySha256'],
            ],
            'resume' => $plan['resume'],
            'requiredDependencies' => $plan['requiredDependencies'],
            'settingEvidence' => $plan['settingEvidence'],
            'appliedMigrations' => $plan['appliedMigrations'],
            'pendingMigrations' => array_values(array_map(
                static function ($migrationId) use ($target) {
                    return $target['migrations'][$migrationId];
                },
                $plan['pendingMigrations']
            )),
            'completionState' => 'installed_disabled',
            'runtimeLoad' => false,
        ];
        $encoded = json_encode($material, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            $plan['errors'][] = 'plan_encoding_failed';
            return $plan;
        }
        $plan['planSha256'] = hash('sha256', $encoded);
        $plan['valid'] = true;
        return $plan;
    }
}

if (!function_exists('red_addon_upgrade_compare_and_swap_state')) {
    function red_addon_upgrade_compare_and_swap_state(
        $connection,
        array $installation,
        $expectedState,
        $targetState,
        $actorAdminRecordId
    ) {
        $statement = mysqli_prepare(
            $connection,
            'UPDATE RED_Addon_Installations
             SET LifecycleState=?, UpdatedByAdminRecordID=?
             WHERE PackageID=? AND PackageVersion=? AND PackageType=?
               AND ManifestSHA256=? AND InventorySHA256=? AND LifecycleState=?'
        );
        if (!$statement) {
            return false;
        }
        mysqli_stmt_bind_param(
            $statement,
            'sissssss',
            $targetState,
            $actorAdminRecordId,
            $installation['PackageID'],
            $installation['PackageVersion'],
            $installation['PackageType'],
            $installation['ManifestSHA256'],
            $installation['InventorySHA256'],
            $expectedState
        );
        $updated = mysqli_stmt_execute($statement)
            && mysqli_stmt_affected_rows($statement) === 1;
        mysqli_stmt_close($statement);
        return $updated;
    }
}

if (!function_exists('red_addon_upgrade_complete_state')) {
    function red_addon_upgrade_complete_state(
        $connection,
        array $installation,
        array $target,
        $actorAdminRecordId
    ) {
        $state = 'installed_disabled';
        $expectedState = 'upgrading';
        $statement = mysqli_prepare(
            $connection,
            'UPDATE RED_Addon_Installations
             SET PackageVersion=?, PackageType=?, ManifestSHA256=?,
                 InventorySHA256=?, LifecycleState=?, UpdatedByAdminRecordID=?
             WHERE PackageID=? AND PackageVersion=? AND PackageType=?
               AND ManifestSHA256=? AND InventorySHA256=? AND LifecycleState=?'
        );
        if (!$statement) {
            return false;
        }
        mysqli_stmt_bind_param(
            $statement,
            'sssssissssss',
            $target['version'],
            $target['type'],
            $target['manifestSha256'],
            $target['inventorySha256'],
            $state,
            $actorAdminRecordId,
            $installation['PackageID'],
            $installation['PackageVersion'],
            $installation['PackageType'],
            $installation['ManifestSHA256'],
            $installation['InventorySHA256'],
            $expectedState
        );
        $updated = mysqli_stmt_execute($statement)
            && mysqli_stmt_affected_rows($statement) === 1;
        mysqli_stmt_close($statement);
        return $updated;
    }
}

if (!function_exists('red_addon_upgrade_mark_failure')) {
    function red_addon_upgrade_mark_failure(
        $connection,
        array $installation,
        $targetVersion,
        $actorAdminRecordId,
        $detailCode,
        $auditRecorder
    ) {
        try {
            if (!mysqli_begin_transaction($connection)
                || !red_addon_upgrade_compare_and_swap_state(
                    $connection,
                    $installation,
                    'upgrading',
                    'upgrade_failed',
                    $actorAdminRecordId
                )
                || !$auditRecorder(
                    $connection,
                    'addon.upgrade.failed',
                    $installation['PackageID'],
                    $targetVersion,
                    $actorAdminRecordId,
                    'failed',
                    $detailCode
                )
                || !mysqli_commit($connection)
            ) {
                mysqli_rollback($connection);
                return false;
            }
            return true;
        } catch (Throwable $throwable) {
            try {
                mysqli_rollback($connection);
            } catch (Throwable $ignored) {
            }
            return false;
        }
    }
}

if (!function_exists('red_addon_upgrade_package')) {
    function red_addon_upgrade_package(
        $connection,
        $packageId,
        $projectRoot,
        $actorAdminRecordId,
        $expectedPlanSha256,
        $allowResume = false,
        $auditRecorder = null,
        $migrationExecutor = null
    ) {
        $result = [
            'status' => 'invalid',
            'packageId' => (string) $packageId,
            'currentVersion' => '',
            'targetVersion' => '',
            'appliedMigrations' => [],
            'failedMigration' => '',
        ];
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!red_addon_valid_package_id($packageId)
            || !red_addon_valid_sha256($expectedPlanSha256)
            || $actorAdminRecordId <= 0
            || !red_addon_install_storage_available($connection)
        ) {
            return $result;
        }
        $auditRecorder = $auditRecorder ?? 'red_addon_install_audit_record';
        $migrationExecutor = $migrationExecutor ?? 'red_addon_install_execute_sql';
        if (!is_callable($auditRecorder) || !is_callable($migrationExecutor)) {
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
                if (empty($catalog['valid']) || !isset($catalog['packages'][$packageId])) {
                    $result['status'] = 'package_invalid';
                    return $result;
                }
                $package = $catalog['packages'][$packageId];
                $target = red_addon_registry_snapshot($package);
                if ($target === null) {
                    $result['status'] = 'package_invalid';
                    return $result;
                }
                $installation = red_addon_registry_installation($connection, $packageId);
                if (!is_array($installation)) {
                    $result['status'] = 'package_not_installed';
                    return $result;
                }
                $result['currentVersion'] = (string) $installation['PackageVersion'];
                $result['targetVersion'] = $target['version'];
                $plan = red_addon_upgrade_plan(
                    $connection,
                    $package,
                    $actorAdminRecordId,
                    $allowResume,
                    $catalog
                );
                if (empty($plan['valid'])) {
                    $result['status'] = $plan['errors'][0] ?? 'plan_invalid';
                    return $result;
                }
                if (!hash_equals($expectedPlanSha256, $plan['planSha256'])) {
                    $result['status'] = 'plan_changed';
                    return $result;
                }

                if (!mysqli_begin_transaction($connection)) {
                    $result['status'] = 'transaction_failed';
                    return $result;
                }
                $startDetail = $plan['resume'] ? 'resume_started' : 'upgrade_started';
                if (!red_addon_upgrade_compare_and_swap_state(
                    $connection,
                    $installation,
                    $plan['currentState'],
                    'upgrading',
                    $actorAdminRecordId
                ) || !$auditRecorder(
                    $connection,
                    'addon.upgrade.started',
                    $packageId,
                    $target['version'],
                    $actorAdminRecordId,
                    'started',
                    $startDetail
                ) || !mysqli_commit($connection)) {
                    mysqli_rollback($connection);
                    $result['status'] = 'upgrade_start_failed';
                    return $result;
                }

                $detailCode = 'unexpected_failure';
                try {
                    foreach ($plan['pendingMigrations'] as $migrationId) {
                        $migration = $target['migrations'][$migrationId];
                        $errorCode = '';
                        $sql = red_addon_install_migration_sql($package, $migration, $errorCode);
                        if ($sql === null) {
                            $detailCode = $errorCode !== '' ? $errorCode : 'migration_revalidation_failed';
                            $result['failedMigration'] = $migrationId;
                            throw new RuntimeException($detailCode);
                        }
                        $startedAt = hrtime(true);
                        try {
                            $migrationExecutor($connection, $sql);
                        } catch (Throwable $throwable) {
                            $detailCode = 'migration_execution_failed';
                            $result['failedMigration'] = $migrationId;
                            throw $throwable;
                        }
                        $executionMs = (int) round((hrtime(true) - $startedAt) / 1000000);
                        if (!red_addon_install_record_migration(
                            $connection,
                            $packageId,
                            $migration,
                            $actorAdminRecordId,
                            $executionMs
                        )) {
                            $detailCode = 'migration_ledger_failed';
                            $result['failedMigration'] = $migrationId;
                            throw new RuntimeException($detailCode);
                        }
                        $result['appliedMigrations'][] = $migrationId;
                    }

                    if (!mysqli_begin_transaction($connection)
                        || !red_addon_upgrade_complete_state(
                            $connection,
                            $installation,
                            $target,
                            $actorAdminRecordId
                        )
                        || !$auditRecorder(
                            $connection,
                            'addon.upgrade.completed',
                            $packageId,
                            $target['version'],
                            $actorAdminRecordId,
                            'succeeded',
                            'installed_disabled'
                        )
                        || !mysqli_commit($connection)
                    ) {
                        mysqli_rollback($connection);
                        $detailCode = 'completion_transaction_failed';
                        throw new RuntimeException($detailCode);
                    }
                    $result['status'] = 'installed_disabled';
                    return $result;
                } catch (Throwable $throwable) {
                    if (!red_addon_upgrade_mark_failure(
                        $connection,
                        $installation,
                        $target['version'],
                        $actorAdminRecordId,
                        $detailCode,
                        $auditRecorder
                    )) {
                        error_log('RED-CMS add-on upgrade failure state could not be persisted for ' . $packageId . '.');
                    }
                    $result['status'] = $detailCode;
                    return $result;
                }
            } finally {
                red_addon_install_unlock($connection, $packageId);
            }
        } finally {
            red_addon_lifecycle_unlock($connection);
        }
    }
}

?>
