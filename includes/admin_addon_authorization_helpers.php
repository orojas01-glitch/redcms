<?php
/**
 * Fail-closed authorization contract for future add-on lifecycle actions.
 *
 * Owner identity and exact capability grants are additive to the legacy
 * AdminType field. No package lifecycle endpoint or runtime loader exists.
 */

require_once __DIR__ . '/admin_audit_helpers.php';

if (!function_exists('red_admin_addon_lifecycle_capabilities')) {
    function red_admin_addon_lifecycle_capabilities()
    {
        return [
            'addons.install',
            'addons.enable',
            'addons.disable',
            'addons.upgrade',
            'addons.uninstall',
            'addons.purge',
        ];
    }
}

if (!function_exists('red_admin_addon_valid_capability')) {
    function red_admin_addon_valid_capability($capability)
    {
        return is_string($capability)
            && in_array($capability, red_admin_addon_lifecycle_capabilities(), true);
    }
}

if (!function_exists('red_admin_addon_actor_capabilities')) {
    function red_admin_addon_actor_capabilities(array $actor)
    {
        $values = $actor['capabilities'] ?? [];
        if (!is_array($values)) {
            return [];
        }

        $capabilities = [];
        foreach ($values as $value) {
            if (red_admin_addon_valid_capability($value)) {
                $capabilities[$value] = $value;
            }
        }
        return array_values($capabilities);
    }
}

if (!function_exists('red_admin_addon_actor_can')) {
    function red_admin_addon_actor_can(array $actor, $capability)
    {
        if (!red_admin_addon_valid_capability($capability)) {
            return false;
        }

        $role = isset($actor['role']) && is_scalar($actor['role'])
            ? strtolower(trim((string) $actor['role']))
            : '';
        if ($role !== 'owner') {
            return false;
        }

        return in_array(
            $capability,
            red_admin_addon_actor_capabilities($actor),
            true
        );
    }
}

if (!function_exists('red_admin_addon_current_actor')) {
    function red_admin_addon_current_actor()
    {
        $capabilities = isset($_SESSION['AdminCapabilities'])
            && is_array($_SESSION['AdminCapabilities'])
            ? $_SESSION['AdminCapabilities']
            : [];

        return [
            'recordId' => (int) ($_SESSION['AdminRecordID'] ?? 0),
            'role' => isset($_SESSION['AdminRole']) && is_scalar($_SESSION['AdminRole'])
                ? (string) $_SESSION['AdminRole']
                : '',
            'capabilities' => $capabilities,
        ];
    }
}

if (!function_exists('red_admin_addon_storage_available')) {
    function red_admin_addon_storage_available($connection)
    {
        if (!$connection) {
            return false;
        }
        try {
            $result = mysqli_query(
                $connection,
                "SELECT COUNT(*) AS TableCount
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND TABLE_NAME IN ('RED_Admin_Roles','RED_Admin_Capabilities')"
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return (int) ($row['TableCount'] ?? 0) === 2;
        } catch (Throwable $throwable) {
            return false;
        }
    }
}

if (!function_exists('red_admin_addon_database_actor')) {
    function red_admin_addon_database_actor($connection, $adminRecordId)
    {
        $actor = [
            'recordId' => (int) $adminRecordId,
            'role' => '',
            'capabilities' => [],
        ];
        if (!$connection
            || $actor['recordId'] <= 0
            || !red_admin_addon_storage_available($connection)
        ) {
            return $actor;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT RoleName FROM RED_Admin_Roles WHERE AdminRecordID=? LIMIT 1'
            );
            if (!$stmt) {
                return $actor;
            }
            mysqli_stmt_bind_param($stmt, 'i', $actor['recordId']);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return $actor;
            }
            $result = mysqli_stmt_get_result($stmt);
            $roleRow = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            $role = isset($roleRow['RoleName']) && is_scalar($roleRow['RoleName'])
                ? strtolower(trim((string) $roleRow['RoleName']))
                : '';
            if ($role !== 'owner') {
                return $actor;
            }

            $stmt = mysqli_prepare(
                $connection,
                'SELECT Capability FROM RED_Admin_Capabilities WHERE AdminRecordID=? ORDER BY Capability ASC'
            );
            if (!$stmt) {
                return $actor;
            }
            mysqli_stmt_bind_param($stmt, 'i', $actor['recordId']);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return $actor;
            }
            $result = mysqli_stmt_get_result($stmt);
            $capabilities = [];
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $capability = isset($row['Capability']) && is_scalar($row['Capability'])
                    ? (string) $row['Capability']
                    : '';
                if (red_admin_addon_valid_capability($capability)) {
                    $capabilities[$capability] = $capability;
                }
            }
            mysqli_stmt_close($stmt);

            $actor['role'] = 'owner';
            $actor['capabilities'] = array_values($capabilities);
            sort($actor['capabilities'], SORT_STRING);
            return $actor;
        } catch (Throwable $throwable) {
            error_log(
                'RED-CMS add-on authorization lookup failed for administrator ' .
                $actor['recordId'] . ': ' . $throwable->getMessage()
            );
            return [
                'recordId' => $actor['recordId'],
                'role' => '',
                'capabilities' => [],
            ];
        }
    }
}

if (!function_exists('red_admin_addon_refresh_session_authorization')) {
    function red_admin_addon_refresh_session_authorization($connection, $adminRecordId)
    {
        $actor = red_admin_addon_database_actor($connection, $adminRecordId);
        $_SESSION['AdminRole'] = $actor['role'];
        $_SESSION['AdminCapabilities'] = $actor['capabilities'];
        return $actor;
    }
}

if (!function_exists('red_admin_addon_is_owner')) {
    function red_admin_addon_is_owner($connection, $adminRecordId)
    {
        $actor = red_admin_addon_database_actor($connection, $adminRecordId);
        return $actor['role'] === 'owner';
    }
}

if (!function_exists('red_admin_addon_owner_count')) {
    function red_admin_addon_owner_count($connection)
    {
        if (!$connection) {
            return 0;
        }
        if (!red_admin_addon_storage_available($connection)) {
            return 0;
        }
        try {
            $result = mysqli_query(
                $connection,
                "SELECT COUNT(*) AS OwnerCount FROM RED_Admin_Roles WHERE LOWER(RoleName)='owner'"
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return $row ? (int) ($row['OwnerCount'] ?? 0) : 0;
        } catch (Throwable $throwable) {
            error_log('RED-CMS Owner count failed: ' . $throwable->getMessage());
            return 0;
        }
    }
}

if (!function_exists('red_admin_addon_manager_account')) {
    function red_admin_addon_manager_account($connection, $adminRecordId)
    {
        $adminRecordId = (int) $adminRecordId;
        if (!$connection || $adminRecordId <= 0) {
            return null;
        }

        try {
            $stmt = mysqli_prepare(
                $connection,
                "SELECT RecordID, Username, AdminType FROM RED_Admin WHERE RecordID=? AND LOWER(AdminType) IN ('webmaster','superadmin') LIMIT 1"
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
            $account = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return $account ?: null;
        } catch (Throwable $throwable) {
            error_log('RED-CMS Owner manager lookup failed: ' . $throwable->getMessage());
            return null;
        }
    }
}

if (!function_exists('red_admin_addon_owner_lock')) {
    function red_admin_addon_owner_lock($connection, $timeoutSeconds = 10)
    {
        $timeoutSeconds = max(0, min(30, (int) $timeoutSeconds));
        try {
            $stmt = mysqli_prepare(
                $connection,
                "SELECT GET_LOCK(CONCAT('red_owner_', LEFT(SHA2(DATABASE(), 256), 48)), ?) AS Acquired"
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'i', $timeoutSeconds);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            return (int) ($row['Acquired'] ?? 0) === 1;
        } catch (Throwable $throwable) {
            error_log('RED-CMS Owner authorization lock failed: ' . $throwable->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_addon_owner_unlock')) {
    function red_admin_addon_owner_unlock($connection)
    {
        try {
            $result = mysqli_query(
                $connection,
                "SELECT RELEASE_LOCK(CONCAT('red_owner_', LEFT(SHA2(DATABASE(), 256), 48))) AS Released"
            );
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            return (int) ($row['Released'] ?? 0) === 1;
        } catch (Throwable $throwable) {
            error_log('RED-CMS Owner authorization unlock failed: ' . $throwable->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_addon_bootstrap_owner')) {
    function red_admin_addon_bootstrap_owner(
        $connection,
        $targetAdminRecordId,
        $actorAdminRecordId,
        $auditRecorder = null
    )
    {
        $targetAdminRecordId = (int) $targetAdminRecordId;
        $actorAdminRecordId = (int) $actorAdminRecordId;
        if (!$connection || $targetAdminRecordId <= 0 || $actorAdminRecordId <= 0) {
            return 'invalid';
        }
        if ($auditRecorder === null) {
            $auditRecorder = 'red_admin_audit_record';
        }
        if (!is_callable($auditRecorder)) {
            return 'invalid';
        }
        if (!red_admin_addon_storage_available($connection)) {
            return 'storage_missing';
        }
        if (!red_admin_addon_owner_lock($connection)) {
            return 'locked';
        }

        $transactionStarted = false;
        try {
            if (red_admin_addon_owner_count($connection) > 0) {
                return 'owner_exists';
            }
            if (!red_admin_addon_manager_account($connection, $targetAdminRecordId)) {
                return 'target_not_manager';
            }
            if (!red_admin_addon_manager_account($connection, $actorAdminRecordId)) {
                return 'actor_not_manager';
            }
            if (!mysqli_begin_transaction($connection)) {
                return 'no';
            }
            $transactionStarted = true;

            $roleName = 'owner';
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Admin_Roles (AdminRecordID, RoleName, AssignedByAdminRecordID) VALUES (?, ?, ?)'
            );
            if (!$stmt) {
                throw new RuntimeException('Could not prepare Owner role assignment.');
            }
            mysqli_stmt_bind_param(
                $stmt,
                'isi',
                $targetAdminRecordId,
                $roleName,
                $actorAdminRecordId
            );
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Could not assign Owner role.');
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Admin_Capabilities (AdminRecordID, Capability, GrantedByAdminRecordID) VALUES (?, ?, ?)'
            );
            if (!$stmt) {
                throw new RuntimeException('Could not prepare Owner lifecycle grants.');
            }
            foreach (red_admin_addon_lifecycle_capabilities() as $capability) {
                mysqli_stmt_bind_param(
                    $stmt,
                    'isi',
                    $targetAdminRecordId,
                    $capability,
                    $actorAdminRecordId
                );
                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    throw new RuntimeException('Could not grant Owner lifecycle capability.');
                }
            }
            mysqli_stmt_close($stmt);

            if (!$auditRecorder(
                $connection,
                'administrator.owner_bootstrapped',
                'administrator',
                $targetAdminRecordId,
                $actorAdminRecordId
            )) {
                throw new RuntimeException('Could not audit Owner bootstrap.');
            }
            if (!mysqli_commit($connection)) {
                throw new RuntimeException('Could not commit Owner bootstrap.');
            }
            $transactionStarted = false;
            return 'yes';
        } catch (Throwable $throwable) {
            if ($transactionStarted) {
                try {
                    mysqli_rollback($connection);
                } catch (Throwable $rollbackError) {
                    error_log('RED-CMS Owner bootstrap rollback failed: ' . $rollbackError->getMessage());
                }
            }
            error_log('RED-CMS Owner bootstrap failed: ' . $throwable->getMessage());
            return 'no';
        } finally {
            red_admin_addon_owner_unlock($connection);
        }
    }
}

if (!function_exists('red_admin_addon_current_actor_can')) {
    function red_admin_addon_current_actor_can($capability)
    {
        return red_admin_addon_actor_can(
            red_admin_addon_current_actor(),
            $capability
        );
    }
}

?>
