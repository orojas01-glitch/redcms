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
