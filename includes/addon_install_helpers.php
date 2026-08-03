<?php
/**
 * Guarded server-local installation of validated first-party add-ons.
 *
 * Installation applies reviewed package SQL and records the package as
 * installed_disabled. It never includes addon.php or enables runtime code.
 */

require_once __DIR__ . '/addon_registry_helpers.php';

if (!function_exists('red_addon_install_storage_available')) {
    function red_addon_install_storage_available($connection)
    {
        if (!red_addon_registry_storage_available($connection)) {
            return false;
        }
        try {
            $result = mysqli_query(
                $connection,
                "SELECT COUNT(*) AS TableCount
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME='RED_Addon_Activity_Log'"
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return (int) ($row['TableCount'] ?? 0) === 1;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_install_audit_event_allowed')) {
    function red_addon_install_audit_event_allowed($eventName)
    {
        return is_string($eventName) && in_array($eventName, [
            'addon.install.started',
            'addon.install.completed',
            'addon.install.failed',
            'addon.enable.completed',
            'addon.disable.completed',
            'addon.settings.updated',
            'addon.action.completed',
        ], true);
    }
}

if (!function_exists('red_addon_install_audit_result_allowed')) {
    function red_addon_install_audit_result_allowed($result)
    {
        return is_string($result) && in_array($result, [
            'started',
            'succeeded',
            'failed',
        ], true);
    }
}

if (!function_exists('red_addon_install_audit_detail_valid')) {
    function red_addon_install_audit_detail_valid($detailCode)
    {
        return is_string($detailCode)
            && preg_match('/\A[a-z0-9][a-z0-9_.-]{0,63}\z/', $detailCode) === 1;
    }
}

if (!function_exists('red_addon_install_audit_record')) {
    function red_addon_install_audit_record(
        $connection,
        $eventName,
        $packageId,
        $packageVersion,
        $actorAdminRecordId,
        $result,
        $detailCode
    ) {
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!$connection
            || !red_addon_install_storage_available($connection)
            || !red_addon_install_audit_event_allowed($eventName)
            || !red_addon_valid_package_id($packageId)
            || !red_addon_valid_semantic_version($packageVersion)
            || $actorAdminRecordId <= 0
            || !red_addon_install_audit_result_allowed($result)
            || !red_addon_install_audit_detail_valid($detailCode)
        ) {
            return false;
        }

        try {
            mysqli_query(
                $connection,
                'DELETE FROM RED_Addon_Activity_Log
                 WHERE OccurredAt < (CURRENT_TIMESTAMP - INTERVAL 180 DAY)
                 ORDER BY OccurredAt ASC
                 LIMIT 500'
            );
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Addon_Activity_Log (
                    EventName, PackageID, PackageVersion,
                    ActorAdminRecordID, Result, DetailCode
                 ) VALUES (?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'sssiss',
                $eventName,
                $packageId,
                $packageVersion,
                $actorAdminRecordId,
                $result,
                $detailCode
            );
            $recorded = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $recorded;
        } catch (Throwable $throwable) {
            error_log('RED-CMS add-on install audit failed: ' . $throwable->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_addon_install_database_name')) {
    function red_addon_install_database_name($connection)
    {
        try {
            $result = mysqli_query($connection, 'SELECT DATABASE() AS DatabaseName');
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return isset($row['DatabaseName']) && is_string($row['DatabaseName'])
                ? $row['DatabaseName']
                : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }
}

if (!function_exists('red_addon_install_lock')) {
    function red_addon_install_lock($connection, $packageId, $timeoutSeconds = 10)
    {
        if (!$connection || !red_addon_valid_package_id($packageId)) {
            return false;
        }
        $timeoutSeconds = max(0, min(30, (int) $timeoutSeconds));
        $databaseName = red_addon_install_database_name($connection);
        if ($databaseName === '') {
            return false;
        }
        $lockName = 'red_addon_install_' . substr(
            hash('sha256', $databaseName . "\0" . $packageId),
            0,
            40
        );
        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT GET_LOCK(?, ?) AS Acquired'
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'si', $lockName, $timeoutSeconds);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return (int) ($row['Acquired'] ?? 0) === 1;
        } catch (Throwable $throwable) {
            error_log('RED-CMS add-on install lock failed: ' . $throwable->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_addon_install_unlock')) {
    function red_addon_install_unlock($connection, $packageId)
    {
        if (!$connection || !red_addon_valid_package_id($packageId)) {
            return false;
        }
        $databaseName = red_addon_install_database_name($connection);
        if ($databaseName === '') {
            return false;
        }
        $lockName = 'red_addon_install_' . substr(
            hash('sha256', $databaseName . "\0" . $packageId),
            0,
            40
        );
        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RELEASE_LOCK(?) AS Released'
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 's', $lockName);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return (int) ($row['Released'] ?? 0) === 1;
        } catch (Throwable $throwable) {
            error_log('RED-CMS add-on install unlock failed: ' . $throwable->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_addon_lifecycle_lock')) {
    function red_addon_lifecycle_lock($connection, $timeoutSeconds = 10)
    {
        return red_addon_install_lock(
            $connection,
            'redcms.lifecycle',
            $timeoutSeconds
        );
    }
}

if (!function_exists('red_addon_lifecycle_unlock')) {
    function red_addon_lifecycle_unlock($connection)
    {
        return red_addon_install_unlock($connection, 'redcms.lifecycle');
    }
}

if (!function_exists('red_addon_install_sql_guard')) {
    function red_addon_install_sql_guard($sql)
    {
        if (!is_string($sql) || trim($sql) === '') {
            return 'migration_empty';
        }
        if (strlen($sql) > 2097152) {
            return 'migration_too_large';
        }
        if (str_contains($sql, "\0")) {
            return 'migration_binary';
        }

        $forbiddenPatterns = [
            '/\bDELIMITER\b/i',
            '/\bSOURCE\b/i',
            '/\bUSE\s+[`A-Za-z0-9_]/i',
            '/\b(?:CREATE|ALTER|DROP)\s+DATABASE\b/i',
            '/\b(?:CREATE|ALTER|DROP)\s+USER\b/i',
            '/\b(?:GRANT|REVOKE)\b/i',
            '/\b(?:INSTALL|UNINSTALL)\s+PLUGIN\b/i',
            '/\bCREATE\s+(?:DEFINER\s*=\s*\S+\s+)?(?:FUNCTION|PROCEDURE|TRIGGER|EVENT)\b/i',
            '/\bSET\s+(?:@@)?GLOBAL\b/i',
            '/\bLOAD\s+DATA\b/i',
            '/\bLOAD_FILE\s*\(/i',
            '/\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i',
            '/\bLOCK\s+TABLES\b/i',
            '/\bUNLOCK\s+TABLES\b/i',
            '/\bSTART\s+TRANSACTION\b/i',
            '/\b(?:BEGIN|COMMIT|ROLLBACK|SAVEPOINT)\b/i',
            '/\bRELEASE\s+SAVEPOINT\b/i',
            '/\bSET\s+(?:(?:SESSION|LOCAL)\s+)?TRANSACTION\b/i',
            '/\bXA\s+(?:START|BEGIN|END|PREPARE|COMMIT|ROLLBACK)\b/i',
            '/\bAUTOCOMMIT\b/i',
            '/\b(?:PREPARE|EXECUTE|DEALLOCATE\s+PREPARE)\b/i',
            '/\b(?:CREATE|ALTER|DROP)\s+(?:OR\s+REPLACE\s+)?VIEW\b/i',
            '/\bRENAME\s+TABLE\b/i',
            '/\b(?:INFORMATION_SCHEMA|PERFORMANCE_SCHEMA|mysql|sys)\s*\./i',
        ];
        foreach ($forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $sql) === 1) {
                return 'migration_forbidden_sql';
            }
        }

        $guardSql = preg_replace(
            '/\bREFERENCES\s+`?RED_Articles`?\s*\(\s*`?RecordID`?\s*\)/i',
            'REFERENCES RED_Addon_Core_Article_Parent (RecordID)',
            $sql
        );
        if (!is_string($guardSql)) {
            return 'migration_forbidden_sql';
        }

        preg_match_all('/`?(RED_[A-Za-z0-9_]*)`?/i', $guardSql, $matches);
        $reservedRegistryTables = [
            'red_addon_installations',
            'red_addon_migrations',
            'red_addon_activity_log',
        ];
        foreach ($matches[1] ?? [] as $tableName) {
            $normalized = strtolower((string) $tableName);
            if (!str_starts_with($normalized, 'red_addon_')
                || in_array($normalized, $reservedRegistryTables, true)
            ) {
                return 'migration_table_scope';
            }
        }

        $scopeSql = preg_replace(
            [
                '/\/\*[\s\S]*?\*\//',
                '/--[^\r\n]*/',
                '/\#[^\r\n]*/',
            ],
            ' ',
            $guardSql
        );
        if (!is_string($scopeSql)) {
            return 'migration_forbidden_sql';
        }
        $tableOperationPatterns = [
            '/(?:\A|;)\s*(?:CREATE\s+(?:TEMPORARY\s+)?|ALTER\s+|DROP\s+|TRUNCATE\s+)TABLE\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*TRUNCATE\s+(?!TABLE\b)(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*(?:INSERT|REPLACE)\s+INTO\s+(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*UPDATE\s+(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*DELETE\s+FROM\s+(`?[A-Za-z0-9_]+`?)/i',
            '/\bREFERENCES\s+(`?[A-Za-z0-9_]+`?)/i',
            '/(?:\A|;)\s*(?:CREATE|DROP)\s+(?:UNIQUE\s+)?INDEX\b[\s\S]*?\bON\s+(`?[A-Za-z0-9_]+`?)/i',
        ];
        foreach ($tableOperationPatterns as $pattern) {
            preg_match_all($pattern, $scopeSql, $operationMatches);
            foreach ($operationMatches[1] ?? [] as $tableName) {
                $normalized = strtolower(trim((string) $tableName, '`'));
                if (!str_starts_with($normalized, 'red_addon_')
                    || in_array($normalized, $reservedRegistryTables, true)
                ) {
                    return 'migration_table_scope';
                }
            }
        }

        return '';
    }
}

if (!function_exists('red_addon_install_dependency_evidence')) {
    function red_addon_install_dependency_evidence(
        $connection,
        array $package,
        array $catalog,
        &$errorCode = ''
    ) {
        $errorCode = '';
        $manifest = isset($package['manifest']) && is_array($package['manifest'])
            ? $package['manifest']
            : [];
        $required = $manifest['dependencies']['required'] ?? [];
        if (!is_array($required) || $required === []) {
            return [];
        }

        $evidence = [];
        foreach ($required as $dependency) {
            $dependencyId = is_array($dependency)
                && isset($dependency['id'])
                && is_string($dependency['id'])
                ? $dependency['id']
                : '';
            $versionRange = is_array($dependency)
                && isset($dependency['version'])
                && is_string($dependency['version'])
                ? $dependency['version']
                : '';
            if (!red_addon_valid_package_id($dependencyId)
                || !red_addon_version_range_valid($versionRange)
                || !isset($catalog['packages'][$dependencyId])
                || !is_array($catalog['packages'][$dependencyId])
            ) {
                $errorCode = 'required_dependency_invalid';
                return [];
            }

            $dependencyPackage = $catalog['packages'][$dependencyId];
            $snapshot = red_addon_registry_snapshot($dependencyPackage);
            if ($snapshot === null
                || !red_addon_version_satisfies(
                    $snapshot['version'],
                    $versionRange
                )
            ) {
                $errorCode = 'required_dependency_incompatible';
                return [];
            }
            $report = red_addon_registry_package_report(
                $connection,
                $dependencyPackage
            );
            if (($report['status'] ?? '') !== 'enabled_current'
                || ($report['lifecycleState'] ?? '') !== 'enabled'
                || !empty($report['errors'])
                || !empty($report['pendingMigrations'])
            ) {
                $errorCode = 'required_dependency_not_enabled';
                return [];
            }

            $evidence[$dependencyId] = [
                'id' => $dependencyId,
                'versionRange' => $versionRange,
                'installedVersion' => $snapshot['version'],
                'manifestSha256' => $snapshot['manifestSha256'],
                'inventorySha256' => $snapshot['inventorySha256'],
                'lifecycleState' => 'enabled',
            ];
        }
        ksort($evidence, SORT_STRING);
        return array_values($evidence);
    }
}

if (!function_exists('red_addon_install_migration_sql')) {
    function red_addon_install_migration_sql(
        array $package,
        array $migration,
        &$errorCode = ''
    ) {
        $errorCode = '';
        $packagePath = isset($package['path']) && is_string($package['path'])
            ? $package['path']
            : '';
        $migrationPath = isset($migration['path']) && is_string($migration['path'])
            ? $migration['path']
            : '';
        $expectedSha256 = isset($migration['sha256']) && is_string($migration['sha256'])
            ? $migration['sha256']
            : '';
        $absolutePath = red_addon_safe_package_file($packagePath, $migrationPath);
        if ($absolutePath === null || !red_addon_valid_sha256($expectedSha256)) {
            $errorCode = 'migration_file_missing';
            return null;
        }
        $actualSha256 = hash_file('sha256', $absolutePath);
        if (!is_string($actualSha256)
            || !hash_equals($expectedSha256, $actualSha256)
        ) {
            $errorCode = 'migration_checksum_drift';
            return null;
        }
        $sql = file_get_contents($absolutePath);
        if (!is_string($sql)) {
            $errorCode = 'migration_file_unreadable';
            return null;
        }
        $errorCode = red_addon_install_sql_guard($sql);
        return $errorCode === '' ? $sql : null;
    }
}

if (!function_exists('red_addon_install_plan')) {
    function red_addon_install_plan(
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
            'packageId' => isset($package['id']) && is_string($package['id'])
                ? $package['id']
                : '',
            'version' => '',
            'resume' => false,
            'requiredDependencies' => [],
            'appliedMigrations' => [],
            'pendingMigrations' => [],
            'planSha256' => '',
            'errors' => [],
        ];
        if (!red_addon_install_storage_available($connection)) {
            $plan['errors'][] = 'install_storage_unavailable';
            return $plan;
        }
        if (empty($package['valid'])) {
            $plan['errors'][] = 'package_invalid';
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
        if (!red_addon_registry_actor_can_transition($actor, 'install')) {
            $plan['errors'][] = 'owner_install_capability_required';
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

        $installation = red_addon_registry_installation(
            $connection,
            $snapshot['id']
        );
        if ($installation !== null) {
            $state = (string) ($installation['LifecycleState'] ?? '');
            if (!in_array($state, ['installing', 'installation_failed'], true)) {
                $plan['errors'][] = 'package_already_recorded';
                return $plan;
            }
            if (!$allowResume) {
                $plan['errors'][] = 'resume_confirmation_required';
                return $plan;
            }
            $identityChecks = [
                'PackageVersion' => $snapshot['version'],
                'PackageType' => $snapshot['type'],
                'ManifestSHA256' => $snapshot['manifestSha256'],
                'InventorySHA256' => $snapshot['inventorySha256'],
            ];
            foreach ($identityChecks as $field => $expected) {
                $actual = isset($installation[$field])
                    && is_scalar($installation[$field])
                    ? (string) $installation[$field]
                    : '';
                if (!hash_equals((string) $expected, $actual)) {
                    $plan['errors'][] = 'resume_identity_drift';
                    return $plan;
                }
            }
            $plan['resume'] = true;
        }

        $applied = red_addon_registry_migrations(
            $connection,
            $snapshot['id']
        );
        foreach ($applied as $migrationId => $row) {
            if (!isset($snapshot['migrations'][$migrationId])) {
                $plan['errors'][] = 'orphaned_migration';
                return $plan;
            }
            $expected = $snapshot['migrations'][$migrationId];
            if (!hash_equals(
                $expected['path'],
                (string) ($row['MigrationPath'] ?? '')
            ) || !hash_equals(
                $expected['sha256'],
                (string) ($row['Checksum'] ?? '')
            )) {
                $plan['errors'][] = 'migration_ledger_drift';
                return $plan;
            }
            $plan['appliedMigrations'][] = $migrationId;
        }

        foreach ($snapshot['migrations'] as $migrationId => $migration) {
            if (isset($applied[$migrationId])) {
                continue;
            }
            $errorCode = '';
            if (red_addon_install_migration_sql(
                $package,
                $migration,
                $errorCode
            ) === null) {
                $plan['errors'][] = $errorCode !== ''
                    ? $errorCode
                    : 'migration_preflight_failed';
                return $plan;
            }
            $plan['pendingMigrations'][] = $migrationId;
        }

        $planMaterial = [
            'database' => $plan['database'],
            'actorAdminRecordId' => $actorAdminRecordId,
            'package' => [
                'id' => $snapshot['id'],
                'version' => $snapshot['version'],
                'type' => $snapshot['type'],
                'manifestSha256' => $snapshot['manifestSha256'],
                'inventorySha256' => $snapshot['inventorySha256'],
            ],
            'resume' => $plan['resume'],
            'requiredDependencies' => $plan['requiredDependencies'],
            'appliedMigrations' => $plan['appliedMigrations'],
            'pendingMigrations' => array_values(array_map(
                static function ($migrationId) use ($snapshot) {
                    return $snapshot['migrations'][$migrationId];
                },
                $plan['pendingMigrations']
            )),
            'completionState' => 'installed_disabled',
            'runtimeLoad' => false,
        ];
        $encoded = json_encode(
            $planMaterial,
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

if (!function_exists('red_addon_install_execute_sql')) {
    function red_addon_install_execute_sql($connection, $sql)
    {
        $guardError = red_addon_install_sql_guard($sql);
        if ($guardError !== '') {
            throw new RuntimeException($guardError);
        }
        if (!mysqli_multi_query($connection, $sql)) {
            throw new RuntimeException('migration_execution_failed');
        }
        do {
            $result = mysqli_store_result($connection);
            if ($result) {
                mysqli_free_result($result);
            }
            if (!mysqli_more_results($connection)) {
                break;
            }
            if (!mysqli_next_result($connection)) {
                throw new RuntimeException('migration_execution_failed');
            }
        } while (true);
        if (mysqli_errno($connection) !== 0) {
            throw new RuntimeException('migration_execution_failed');
        }
        return true;
    }
}

if (!function_exists('red_addon_install_insert_installation')) {
    function red_addon_install_insert_installation(
        $connection,
        array $snapshot,
        $actorAdminRecordId
    ) {
        $state = 'installing';
        $stmt = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Installations (
                PackageID, PackageVersion, PackageType,
                ManifestSHA256, InventorySHA256, LifecycleState,
                InstalledByAdminRecordID, UpdatedByAdminRecordID
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'ssssssii',
            $snapshot['id'],
            $snapshot['version'],
            $snapshot['type'],
            $snapshot['manifestSha256'],
            $snapshot['inventorySha256'],
            $state,
            $actorAdminRecordId,
            $actorAdminRecordId
        );
        $inserted = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $inserted;
    }
}

if (!function_exists('red_addon_install_update_state')) {
    function red_addon_install_update_state(
        $connection,
        $packageId,
        $state,
        $actorAdminRecordId
    ) {
        if (!red_addon_registry_valid_lifecycle_state($state)) {
            return false;
        }
        $stmt = mysqli_prepare(
            $connection,
            'UPDATE RED_Addon_Installations
             SET LifecycleState=?, UpdatedByAdminRecordID=?
             WHERE PackageID=?'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'sis',
            $state,
            $actorAdminRecordId,
            $packageId
        );
        $updated = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $updated;
    }
}

if (!function_exists('red_addon_install_record_migration')) {
    function red_addon_install_record_migration(
        $connection,
        $packageId,
        array $migration,
        $actorAdminRecordId,
        $executionMs
    ) {
        $executionMs = max(0, min(4294967295, (int) $executionMs));
        $stmt = mysqli_prepare(
            $connection,
            'INSERT INTO RED_Addon_Migrations (
                PackageID, MigrationID, MigrationPath, Checksum,
                AppliedByAdminRecordID, ExecutionMs
             ) VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'ssssii',
            $packageId,
            $migration['id'],
            $migration['path'],
            $migration['sha256'],
            $actorAdminRecordId,
            $executionMs
        );
        $recorded = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $recorded;
    }
}

if (!function_exists('red_addon_install_mark_failure')) {
    function red_addon_install_mark_failure(
        $connection,
        array $snapshot,
        $actorAdminRecordId,
        $detailCode,
        $auditRecorder
    ) {
        try {
            if (!mysqli_begin_transaction($connection)) {
                return false;
            }
            if (!red_addon_install_update_state(
                $connection,
                $snapshot['id'],
                'installation_failed',
                $actorAdminRecordId
            )) {
                mysqli_rollback($connection);
                return false;
            }
            if (!$auditRecorder(
                $connection,
                'addon.install.failed',
                $snapshot['id'],
                $snapshot['version'],
                $actorAdminRecordId,
                'failed',
                $detailCode
            )) {
                mysqli_rollback($connection);
                return false;
            }
            return mysqli_commit($connection);
        } catch (Throwable $throwable) {
            try {
                mysqli_rollback($connection);
            } catch (Throwable $rollbackError) {
                error_log(
                    'RED-CMS add-on failure-state rollback failed: ' .
                    $rollbackError->getMessage()
                );
            }
            return false;
        }
    }
}

if (!function_exists('red_addon_install_package')) {
    function red_addon_install_package(
        $connection,
        $packageId,
        $projectRoot,
        $actorAdminRecordId,
        $expectedPlanSha256,
        $allowResume = false,
        $auditRecorder = null,
        $migrationExecutor = null
    ) {
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $result = [
            'status' => 'invalid',
            'packageId' => (string) $packageId,
            'version' => '',
            'appliedMigrations' => [],
            'failedMigration' => '',
        ];
        if (!red_addon_valid_package_id($packageId)
            || !red_addon_valid_sha256($expectedPlanSha256)
            || $actorAdminRecordId <= 0
            || !red_addon_install_storage_available($connection)
        ) {
            return $result;
        }
        if ($auditRecorder === null) {
            $auditRecorder = 'red_addon_install_audit_record';
        }
        if ($migrationExecutor === null) {
            $migrationExecutor = 'red_addon_install_execute_sql';
        }
        if (!is_callable($auditRecorder) || !is_callable($migrationExecutor)) {
            return $result;
        }
        if (!red_addon_install_lock($connection, $packageId)) {
            $result['status'] = 'locked';
            return $result;
        }

        $snapshot = null;
        $installationStarted = false;
        $detailCode = 'unexpected_failure';
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

            $plan = red_addon_install_plan(
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
            if ($plan['resume']) {
                if (!red_addon_install_update_state(
                    $connection,
                    $snapshot['id'],
                    'installing',
                    $actorAdminRecordId
                )) {
                    mysqli_rollback($connection);
                    $result['status'] = 'resume_state_failed';
                    return $result;
                }
                $startDetail = 'resume_started';
            } else {
                if (!red_addon_install_insert_installation(
                    $connection,
                    $snapshot,
                    $actorAdminRecordId
                )) {
                    mysqli_rollback($connection);
                    $result['status'] = 'registry_insert_failed';
                    return $result;
                }
                $startDetail = 'install_started';
            }
            if (!$auditRecorder(
                $connection,
                'addon.install.started',
                $snapshot['id'],
                $snapshot['version'],
                $actorAdminRecordId,
                'started',
                $startDetail
            )) {
                mysqli_rollback($connection);
                $result['status'] = 'audit_start_failed';
                return $result;
            }
            if (!mysqli_commit($connection)) {
                $result['status'] = 'start_commit_failed';
                return $result;
            }
            $installationStarted = true;

            foreach ($plan['pendingMigrations'] as $migrationId) {
                $migration = $snapshot['migrations'][$migrationId];
                $errorCode = '';
                $sql = red_addon_install_migration_sql(
                    $package,
                    $migration,
                    $errorCode
                );
                if ($sql === null) {
                    $detailCode = $errorCode !== ''
                        ? $errorCode
                        : 'migration_revalidation_failed';
                    $result['failedMigration'] = $migrationId;
                    throw new RuntimeException($detailCode);
                }

                $startedAt = hrtime(true);
                try {
                    $migrationExecutor(
                        $connection,
                        $sql
                    );
                } catch (Throwable $throwable) {
                    $detailCode = 'migration_execution_failed';
                    $result['failedMigration'] = $migrationId;
                    throw $throwable;
                }
                $executionMs = (int) round(
                    (hrtime(true) - $startedAt) / 1000000
                );
                try {
                    if (!red_addon_install_record_migration(
                        $connection,
                        $snapshot['id'],
                        $migration,
                        $actorAdminRecordId,
                        $executionMs
                    )) {
                        $detailCode = 'migration_ledger_failed';
                        $result['failedMigration'] = $migrationId;
                        throw new RuntimeException($detailCode);
                    }
                } catch (Throwable $throwable) {
                    $detailCode = 'migration_ledger_failed';
                    $result['failedMigration'] = $migrationId;
                    throw $throwable;
                }
                $result['appliedMigrations'][] = $migrationId;
            }

            if (!mysqli_begin_transaction($connection)) {
                $detailCode = 'completion_transaction_failed';
                throw new RuntimeException($detailCode);
            }
            if (!red_addon_install_update_state(
                $connection,
                $snapshot['id'],
                'installed_disabled',
                $actorAdminRecordId
            )) {
                mysqli_rollback($connection);
                $detailCode = 'completion_state_failed';
                throw new RuntimeException($detailCode);
            }
            if (!$auditRecorder(
                $connection,
                'addon.install.completed',
                $snapshot['id'],
                $snapshot['version'],
                $actorAdminRecordId,
                'succeeded',
                'installed_disabled'
            )) {
                mysqli_rollback($connection);
                $detailCode = 'audit_completion_failed';
                throw new RuntimeException($detailCode);
            }
            if (!mysqli_commit($connection)) {
                mysqli_rollback($connection);
                $detailCode = 'completion_commit_failed';
                throw new RuntimeException($detailCode);
            }

            $result['status'] = 'installed_disabled';
            return $result;
        } catch (Throwable $throwable) {
            if ($installationStarted && is_array($snapshot)) {
                if (!red_addon_install_mark_failure(
                    $connection,
                    $snapshot,
                    $actorAdminRecordId,
                    $detailCode,
                    $auditRecorder
                )) {
                    error_log(
                        'RED-CMS add-on installation failure could not be persisted for ' .
                        $packageId . '.'
                    );
                }
            }
            $result['status'] = $detailCode;
            return $result;
        } finally {
            red_addon_install_unlock($connection, $packageId);
        }
    }
}

?>
