<?php
/**
 * Owner-authorized, package-declared administrator permission changes.
 *
 * Planning is read-only and consumes only an already validated manifest.
 * The atomic runner never includes package PHP or changes package lifecycle,
 * settings, content, or client boundaries.
 */

require_once __DIR__ . '/addon_registry_helpers.php';

if (!function_exists('red_addon_package_permission_database_name')) {
    function red_addon_package_permission_database_name($connection)
    {
        if (!$connection) {
            return '';
        }
        try {
            $result = mysqli_query($connection, 'SELECT DATABASE() AS DatabaseName');
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return is_string($row['DatabaseName'] ?? null)
                ? (string) $row['DatabaseName']
                : '';
        } catch (Throwable $throwable) {
            return '';
        }
    }
}

if (!function_exists('red_addon_package_permission_storage_available')) {
    function red_addon_package_permission_storage_available($connection)
    {
        if (!$connection || !red_admin_addon_storage_available($connection)) {
            return false;
        }
        try {
            $result = mysqli_query(
                $connection,
                "SELECT CONCAT_WS(':',
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME IN (
                         'RED_Admin_Capabilities',
                         'RED_Addon_Permission_Activity_Log'
                       )
                       AND ENGINE='InnoDB'),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Admin_Capabilities'
                       AND COLUMN_NAME='Capability'
                       AND CHARACTER_MAXIMUM_LENGTH=160),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Permission_Activity_Log'),
                    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE()
                       AND TABLE_NAME='RED_Addon_Permission_Activity_Log'
                       AND COLUMN_NAME='Permission'
                       AND CHARACTER_MAXIMUM_LENGTH=160
                       AND BINARY COLLATION_NAME=BINARY 'utf8mb4_unicode_ci')
                 ) AS StorageState"
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return ($row['StorageState'] ?? '') === '2:1:9:1';
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_package_permission_target')) {
    function red_addon_package_permission_target($connection, $adminRecordId)
    {
        $adminRecordId = (int) $adminRecordId;
        if (!$connection || $adminRecordId <= 0) {
            return null;
        }
        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RecordID, Username FROM RED_Admin WHERE RecordID=? LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }
            mysqli_stmt_bind_param($stmt, 'i', $adminRecordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return null;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return $row ?: null;
        } catch (Throwable $throwable) {
            return null;
        }
    }
}

if (!function_exists('red_addon_package_permission_declared')) {
    function red_addon_package_permission_declared(array $package, $permission)
    {
        if (!is_string($permission) || !red_addon_valid_permission($permission)) {
            return false;
        }
        $manifest = is_array($package['manifest'] ?? null)
            ? $package['manifest']
            : [];
        $permissions = is_array($manifest['permissions'] ?? null)
            ? $manifest['permissions']
            : [];
        return in_array($permission, $permissions, true);
    }
}

if (!function_exists('red_addon_package_permission_has_exact_grant')) {
    function red_addon_package_permission_has_exact_grant(
        $connection,
        $adminRecordId,
        $permission
    ) {
        $adminRecordId = (int) $adminRecordId;
        if (!$connection
            || $adminRecordId <= 0
            || !red_addon_valid_permission($permission)
        ) {
            return false;
        }
        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT COUNT(*) AS GrantCount
                 FROM RED_Admin_Capabilities
                 WHERE AdminRecordID=? AND BINARY Capability=BINARY ?'
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'is', $adminRecordId, $permission);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return (int) ($row['GrantCount'] ?? 0) === 1;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_package_permission_plan_hash')) {
    function red_addon_package_permission_plan_hash(array $plan)
    {
        $evidence = [
            'database' => $plan['database'] ?? '',
            'packageId' => $plan['packageId'] ?? '',
            'version' => $plan['version'] ?? '',
            'manifestSha256' => $plan['manifestSha256'] ?? '',
            'actorAdminRecordId' => $plan['actorAdminRecordId'] ?? 0,
            'targetAdminRecordId' => $plan['targetAdminRecordId'] ?? 0,
            'permission' => $plan['permission'] ?? '',
            'action' => $plan['action'] ?? '',
            'currentState' => $plan['currentState'] ?? '',
            'targetState' => $plan['targetState'] ?? '',
        ];
        $encoded = json_encode(
            $evidence,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        return is_string($encoded) ? hash('sha256', $encoded) : '';
    }
}

if (!function_exists('red_addon_package_permission_plan')) {
    function red_addon_package_permission_plan(
        $connection,
        array $package,
        $actorAdminRecordId,
        $targetAdminRecordId,
        $permission,
        $action
    ) {
        $actorAdminRecordId = (int) $actorAdminRecordId;
        $targetAdminRecordId = (int) $targetAdminRecordId;
        $action = is_string($action) ? strtolower(trim($action)) : '';
        $permission = is_string($permission) ? trim($permission) : '';
        $plan = [
            'valid' => false,
            'changeReady' => false,
            'database' => '',
            'packageId' => '',
            'version' => '',
            'manifestSha256' => '',
            'actorAdminRecordId' => $actorAdminRecordId,
            'targetAdminRecordId' => $targetAdminRecordId,
            'permission' => $permission,
            'action' => $action,
            'currentState' => '',
            'targetState' => '',
            'errors' => [],
            'planSha256' => '',
        ];
        if (!red_addon_package_permission_storage_available($connection)) {
            $plan['errors'][] = 'permission_storage_unavailable';
            return $plan;
        }
        $snapshot = red_addon_registry_snapshot($package);
        if ($snapshot === null) {
            $plan['errors'][] = 'package_manifest_invalid';
            return $plan;
        }
        $plan['database'] = red_addon_package_permission_database_name($connection);
        $plan['packageId'] = $snapshot['id'];
        $plan['version'] = $snapshot['version'];
        $plan['manifestSha256'] = $snapshot['manifestSha256'];
        if ($plan['database'] === '') {
            $plan['errors'][] = 'database_unavailable';
        }
        if (!in_array($action, ['grant', 'revoke'], true)) {
            $plan['errors'][] = 'action_invalid';
        }
        if (!red_addon_package_permission_declared($package, $permission)) {
            $plan['errors'][] = 'permission_not_declared';
        }
        $actor = red_admin_addon_database_actor(
            $connection,
            $actorAdminRecordId
        );
        if (($actor['role'] ?? '') !== 'owner') {
            $plan['errors'][] = 'owner_required';
        }
        if (red_addon_package_permission_target(
            $connection,
            $targetAdminRecordId
        ) === null) {
            $plan['errors'][] = 'target_unavailable';
        }
        if ($plan['errors'] !== []) {
            return $plan;
        }

        $hasGrant = red_addon_package_permission_has_exact_grant(
            $connection,
            $targetAdminRecordId,
            $permission
        );
        $plan['currentState'] = $hasGrant ? 'granted' : 'not_granted';
        $plan['targetState'] = $action === 'grant' ? 'granted' : 'not_granted';
        if ($plan['currentState'] === $plan['targetState']) {
            $plan['errors'][] = $hasGrant ? 'already_granted' : 'not_granted';
            return $plan;
        }
        $plan['valid'] = true;
        $plan['changeReady'] = true;
        $plan['planSha256'] = red_addon_package_permission_plan_hash($plan);
        return $plan;
    }
}

if (!function_exists('red_addon_package_permission_transaction_active')) {
    function red_addon_package_permission_transaction_active($connection)
    {
        if (!$connection) {
            return false;
        }
        try {
            if (!mysqli_query($connection, 'SAVEPOINT redcms_addon_permission_guard')) {
                return false;
            }
            return mysqli_query(
                $connection,
                'RELEASE SAVEPOINT redcms_addon_permission_guard'
            ) === true;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_addon_package_permission_lock')) {
    function red_addon_package_permission_lock($connection, $timeoutSeconds = 10)
    {
        $database = red_addon_package_permission_database_name($connection);
        if ($database === '') {
            return '';
        }
        $lockName = 'redcms-permission:' . substr(hash('sha256', $database), 0, 44);
        $timeoutSeconds = max(0, min(30, (int) $timeoutSeconds));
        $stmt = mysqli_prepare($connection, 'SELECT GET_LOCK(?, ?) AS LockState');
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'si', $lockName, $timeoutSeconds);
        $acquired = mysqli_stmt_execute($stmt);
        $result = $acquired ? mysqli_stmt_get_result($stmt) : null;
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        return (int) ($row['LockState'] ?? 0) === 1 ? $lockName : '';
    }
}

if (!function_exists('red_addon_package_permission_unlock')) {
    function red_addon_package_permission_unlock($connection, $lockName)
    {
        if (!$connection || !is_string($lockName) || $lockName === '') {
            return false;
        }
        $stmt = mysqli_prepare($connection, 'SELECT RELEASE_LOCK(?) AS LockState');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 's', $lockName);
        $released = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $released;
    }
}

if (!function_exists('red_addon_package_permission_execute')) {
    function red_addon_package_permission_execute(
        $connection,
        array $package,
        $actorAdminRecordId,
        $targetAdminRecordId,
        $permission,
        $action,
        $expectedPlanSha256
    ) {
        $result = [
            'changed' => false,
            'status' => 'refused',
            'reason' => 'invalid_request',
        ];
        $transactionActive = $connection
            ? red_addon_package_permission_transaction_active($connection)
            : false;
        if (!$connection
            || !red_addon_valid_sha256($expectedPlanSha256)
            || $transactionActive
        ) {
            $result['reason'] = $transactionActive
                ? 'transaction_already_active'
                : 'invalid_request';
            return $result;
        }
        $lockName = red_addon_package_permission_lock($connection);
        if ($lockName === '') {
            $result['reason'] = 'lock_unavailable';
            return $result;
        }
        $transactionStarted = false;
        try {
            if (!mysqli_begin_transaction($connection)) {
                $result['reason'] = 'transaction_unavailable';
                return $result;
            }
            $transactionStarted = true;
            $actorAdminRecordId = (int) $actorAdminRecordId;
            $targetAdminRecordId = (int) $targetAdminRecordId;
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RecordID FROM RED_Admin
                 WHERE RecordID IN (?, ?) ORDER BY RecordID ASC FOR UPDATE'
            );
            if (!$stmt) {
                throw new RuntimeException('Could not lock administrator rows.');
            }
            mysqli_stmt_bind_param(
                $stmt,
                'ii',
                $actorAdminRecordId,
                $targetAdminRecordId
            );
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Could not lock administrator rows.');
            }
            $locked = mysqli_stmt_get_result($stmt);
            if ($locked) {
                mysqli_free_result($locked);
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare(
                $connection,
                'SELECT AdminRecordID, RoleName FROM RED_Admin_Roles
                 WHERE AdminRecordID=? FOR UPDATE'
            );
            if (!$stmt) {
                throw new RuntimeException('Could not lock Owner authorization.');
            }
            mysqli_stmt_bind_param($stmt, 'i', $actorAdminRecordId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Could not lock Owner authorization.');
            }
            $locked = mysqli_stmt_get_result($stmt);
            if ($locked) {
                mysqli_free_result($locked);
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare(
                $connection,
                'SELECT AdminRecordID, Capability
                 FROM RED_Admin_Capabilities
                 WHERE AdminRecordID IN (?, ?)
                 ORDER BY AdminRecordID ASC, Capability ASC FOR UPDATE'
            );
            if (!$stmt) {
                throw new RuntimeException('Could not lock capability state.');
            }
            mysqli_stmt_bind_param(
                $stmt,
                'ii',
                $actorAdminRecordId,
                $targetAdminRecordId
            );
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Could not lock capability state.');
            }
            $locked = mysqli_stmt_get_result($stmt);
            if ($locked) {
                mysqli_free_result($locked);
            }
            mysqli_stmt_close($stmt);

            $currentPlan = red_addon_package_permission_plan(
                $connection,
                $package,
                $actorAdminRecordId,
                $targetAdminRecordId,
                $permission,
                $action
            );
            if (empty($currentPlan['changeReady'])
                || !hash_equals(
                    (string) $currentPlan['planSha256'],
                    $expectedPlanSha256
                )
            ) {
                mysqli_rollback($connection);
                $transactionStarted = false;
                $result['reason'] = empty($currentPlan['changeReady'])
                    ? (string) (($currentPlan['errors'][0] ?? 'plan_refused'))
                    : 'stale_plan';
                return $result;
            }

            if ($action === 'grant') {
                $stmt = mysqli_prepare(
                    $connection,
                    'INSERT INTO RED_Admin_Capabilities
                     (AdminRecordID, Capability, GrantedByAdminRecordID)
                     VALUES (?, ?, ?)'
                );
                if (!$stmt) {
                    throw new RuntimeException('Could not prepare permission grant.');
                }
                mysqli_stmt_bind_param(
                    $stmt,
                    'isi',
                    $targetAdminRecordId,
                    $permission,
                    $actorAdminRecordId
                );
            } else {
                $stmt = mysqli_prepare(
                    $connection,
                    'DELETE FROM RED_Admin_Capabilities
                     WHERE AdminRecordID=? AND BINARY Capability=BINARY ?'
                );
                if (!$stmt) {
                    throw new RuntimeException('Could not prepare permission revocation.');
                }
                mysqli_stmt_bind_param(
                    $stmt,
                    'is',
                    $targetAdminRecordId,
                    $permission
                );
            }
            if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Permission mutation postcondition failed.');
            }
            mysqli_stmt_close($stmt);

            $eventName = $action === 'grant'
                ? 'addon.permission.granted'
                : 'addon.permission.revoked';
            $auditResult = 'succeeded';
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Addon_Permission_Activity_Log (
                    EventName, PackageID, PackageVersion, Permission,
                    TargetAdminRecordID, ActorAdminRecordID, Result
                 ) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                throw new RuntimeException('Could not prepare permission audit.');
            }
            mysqli_stmt_bind_param(
                $stmt,
                'ssssiis',
                $eventName,
                $currentPlan['packageId'],
                $currentPlan['version'],
                $permission,
                $targetAdminRecordId,
                $actorAdminRecordId,
                $auditResult
            );
            if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Permission audit postcondition failed.');
            }
            mysqli_stmt_close($stmt);

            $postcondition = red_addon_package_permission_has_exact_grant(
                $connection,
                $targetAdminRecordId,
                $permission
            );
            if (($action === 'grant') !== $postcondition) {
                throw new RuntimeException('Permission state postcondition failed.');
            }
            if (!mysqli_commit($connection)) {
                throw new RuntimeException('Could not commit permission change.');
            }
            $transactionStarted = false;
            return [
                'changed' => true,
                'status' => $action === 'grant' ? 'granted' : 'revoked',
                'reason' => 'changed',
            ];
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                mysqli_rollback($connection);
            }
            error_log('RED-CMS package permission change failed: ' . $throwable->getMessage());
            $result['reason'] = 'mutation_failed';
            return $result;
        } finally {
            red_addon_package_permission_unlock($connection, $lockName);
        }
    }
}

?>
