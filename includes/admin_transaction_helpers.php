<?php
/**
 * Shared transaction helpers for related admin writes.
 */

if (!function_exists('red_admin_transaction_tables_supported')) {
    function red_admin_transaction_tables_supported($connection, array $tables)
    {
        foreach (array_unique($tables) as $table) {
            $table = (string) $table;
            if (!preg_match('/\A[A-Za-z0-9_]+\z/', $table)) {
                return false;
            }

            try {
                $stmt = mysqli_prepare(
                    $connection,
                    'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1'
                );
                if (!$stmt) {
                    return false;
                }

                $engine = '';
                mysqli_stmt_bind_param($stmt, 's', $table);
                mysqli_stmt_bind_result($stmt, $engine);
                $found = mysqli_stmt_execute($stmt) && mysqli_stmt_fetch($stmt) === true;
                mysqli_stmt_close($stmt);

                if (!$found || $engine !== 'InnoDB') {
                    error_log('Admin transaction requires InnoDB table: ' . $table);
                    return false;
                }
            } catch (Throwable $e) {
                error_log('Admin transaction table check failed: ' . $e->getMessage());
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('red_admin_theme_contract_lock_name')) {
    function red_admin_theme_contract_lock_name($connection)
    {
        try {
            $result = mysqli_query($connection, 'SELECT DATABASE() AS database_name');
            if ($result === false) {
                return '';
            }
            $row = mysqli_fetch_assoc($result);
            mysqli_free_result($result);
            $databaseName = is_array($row) ? trim((string) ($row['database_name'] ?? '')) : '';
            if ($databaseName === '') {
                return '';
            }

            return 'redcms-theme:' . substr(hash('sha256', $databaseName), 0, 48);
        } catch (Throwable $exception) {
            error_log('Theme contract lock name lookup failed: ' . $exception->getMessage());
            return '';
        }
    }
}

if (!function_exists('red_admin_theme_contract_lock_state')) {
    function &red_admin_theme_contract_lock_state()
    {
        static $locks = [];
        return $locks;
    }
}

if (!function_exists('red_admin_with_theme_contract_lock')) {
    function red_admin_with_theme_contract_lock($connection, $callback, $timeoutSeconds = 5)
    {
        if (!($connection instanceof mysqli) || !is_callable($callback)) {
            return false;
        }

        $connectionId = spl_object_id($connection);
        $locks =& red_admin_theme_contract_lock_state();
        if (isset($locks[$connectionId])) {
            $locks[$connectionId]['depth']++;
            try {
                return call_user_func($callback);
            } finally {
                $locks[$connectionId]['depth']--;
            }
        }

        $lockName = red_admin_theme_contract_lock_name($connection);
        $timeoutSeconds = max(0, min(30, (int) $timeoutSeconds));
        if ($lockName === '') {
            return false;
        }

        $acquiredLock = false;
        try {
            $stmt = mysqli_prepare($connection, 'SELECT GET_LOCK(?, ?)');
            if (!$stmt) {
                return false;
            }
            $acquired = null;
            mysqli_stmt_bind_param($stmt, 'si', $lockName, $timeoutSeconds);
            mysqli_stmt_bind_result($stmt, $acquired);
            $success = mysqli_stmt_execute($stmt) && mysqli_stmt_fetch($stmt) === true;
            mysqli_stmt_close($stmt);
            if (!$success || (int) $acquired !== 1) {
                return false;
            }
            $acquiredLock = true;
        } catch (Throwable $exception) {
            error_log('Theme contract lock acquisition failed: ' . $exception->getMessage());
            return false;
        }

        if (!$acquiredLock) {
            return false;
        }

        $locks[$connectionId] = ['name' => $lockName, 'depth' => 1];
        try {
            return call_user_func($callback);
        } finally {
            unset($locks[$connectionId]);
            try {
                $release = mysqli_prepare($connection, 'SELECT RELEASE_LOCK(?)');
                if ($release) {
                    mysqli_stmt_bind_param($release, 's', $lockName);
                    mysqli_stmt_execute($release);
                    mysqli_stmt_close($release);
                }
            } catch (Throwable $exception) {
                error_log('Theme contract lock release failed: ' . $exception->getMessage());
            }
        }
    }
}

if (!function_exists('red_admin_write_transaction')) {
    function red_admin_write_transaction($connection, $callback, array $tables = [])
    {
        if (!is_callable($callback) || !red_admin_transaction_tables_supported($connection, $tables)) {
            return false;
        }

        try {
            if (!mysqli_begin_transaction($connection)) {
                return false;
            }

            $success = (bool) call_user_func($callback);
            if ($success && mysqli_commit($connection)) {
                return true;
            }

            mysqli_rollback($connection);
            return false;
        } catch (Throwable $e) {
            mysqli_rollback($connection);
            error_log('Admin write transaction failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('red_admin_theme_contract_write_transaction')) {
    function red_admin_theme_contract_write_transaction($connection, $callback, array $tables = [])
    {
        return red_admin_with_theme_contract_lock(
            $connection,
            function () use ($connection, $callback, $tables) {
                return red_admin_write_transaction($connection, $callback, $tables);
            }
        );
    }
}
