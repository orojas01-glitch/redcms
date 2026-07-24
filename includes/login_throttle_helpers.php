<?php
/**
 * Failed administrator-login tracking and temporary throttling helpers.
 *
 * Privacy boundary: store only a normalized username SHA-256 digest, the packed
 * network address supplied by the web server, and the failure timestamp.
 */

if (!function_exists('red_login_throttle_policy')) {
    function red_login_throttle_policy()
    {
        return [
            'window_minutes' => 15,
            'pair_limit' => 5,
            'username_limit' => 15,
            'client_limit' => 30,
            'retention_hours' => 24,
            'cleanup_limit' => 500,
        ];
    }
}

if (!function_exists('red_login_normalize_username')) {
    function red_login_normalize_username($username)
    {
        return strtolower(trim(is_scalar($username) ? (string) $username : ''));
    }
}

if (!function_exists('red_login_username_hash')) {
    function red_login_username_hash($username)
    {
        return hash('sha256', red_login_normalize_username($username), true);
    }
}

if (!function_exists('red_login_client_address')) {
    function red_login_client_address($server = null)
    {
        $server = is_array($server) ? $server : $_SERVER;
        $remoteAddress = isset($server['REMOTE_ADDR']) && is_scalar($server['REMOTE_ADDR'])
            ? trim((string) $server['REMOTE_ADDR'])
            : '';
        $packedAddress = $remoteAddress !== '' ? @inet_pton($remoteAddress) : false;

        if ($packedAddress === false) {
            return inet_pton('0.0.0.0');
        }

        $ipv4MappedPrefix = str_repeat("\0", 10) . "\xff\xff";
        if (strlen($packedAddress) === 16 && substr($packedAddress, 0, 12) === $ipv4MappedPrefix) {
            return substr($packedAddress, 12, 4);
        }

        return $packedAddress;
    }
}

if (!function_exists('red_login_throttle_log_failure')) {
    function red_login_throttle_log_failure($message)
    {
        error_log('RED-CMS login throttling: ' . $message);
    }
}

if (!function_exists('red_login_throttle_cleanup')) {
    function red_login_throttle_cleanup($connection)
    {
        $policy = red_login_throttle_policy();
        $retentionHours = (int) $policy['retention_hours'];
        $cleanupLimit = (int) $policy['cleanup_limit'];

        try {
            $stmt = mysqli_prepare(
                $connection,
                'DELETE FROM RED_Login_Attempts '
                . 'WHERE AttemptedAt < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? HOUR) '
                . 'ORDER BY AttemptedAt ASC LIMIT ?'
            );
            if (!$stmt) {
                red_login_throttle_log_failure('cleanup prepare failed');
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'ii', $retentionHours, $cleanupLimit);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        } catch (mysqli_sql_exception $exception) {
            red_login_throttle_log_failure('cleanup unavailable');
            return false;
        }
    }
}

if (!function_exists('red_login_failure_counts')) {
    function red_login_failure_counts($connection, $usernameHash, $clientAddress)
    {
        $policy = red_login_throttle_policy();
        $windowMinutes = (int) $policy['window_minutes'];

        try {
            $stmt = mysqli_prepare(
                $connection,
                'SELECT '
                . '(SELECT COUNT(*) FROM RED_Login_Attempts '
                . ' WHERE UsernameHash=? AND ClientAddress=? '
                . ' AND AttemptedAt >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? MINUTE)) AS PairFailures, '
                . '(SELECT COUNT(*) FROM RED_Login_Attempts '
                . ' WHERE UsernameHash=? '
                . ' AND AttemptedAt >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? MINUTE)) AS UsernameFailures, '
                . '(SELECT COUNT(*) FROM RED_Login_Attempts '
                . ' WHERE ClientAddress=? '
                . ' AND AttemptedAt >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ? MINUTE)) AS ClientFailures'
            );
            if (!$stmt) {
                red_login_throttle_log_failure('count prepare failed');
                return ['pair' => 0, 'username' => 0, 'client' => 0];
            }
            mysqli_stmt_bind_param(
                $stmt,
                'ssisisi',
                $usernameHash,
                $clientAddress,
                $windowMinutes,
                $usernameHash,
                $windowMinutes,
                $clientAddress,
                $windowMinutes
            );
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                red_login_throttle_log_failure('count execution failed');
                return ['pair' => 0, 'username' => 0, 'client' => 0];
            }
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            return [
                'pair' => (int) ($row['PairFailures'] ?? 0),
                'username' => (int) ($row['UsernameFailures'] ?? 0),
                'client' => (int) ($row['ClientFailures'] ?? 0),
            ];
        } catch (mysqli_sql_exception $exception) {
            red_login_throttle_log_failure('count unavailable');
            return ['pair' => 0, 'username' => 0, 'client' => 0];
        }
    }
}

if (!function_exists('red_login_is_throttled')) {
    function red_login_is_throttled($connection, $usernameHash, $clientAddress)
    {
        $policy = red_login_throttle_policy();
        $counts = red_login_failure_counts($connection, $usernameHash, $clientAddress);

        return $counts['pair'] >= (int) $policy['pair_limit']
            || $counts['username'] >= (int) $policy['username_limit']
            || $counts['client'] >= (int) $policy['client_limit'];
    }
}

if (!function_exists('red_login_record_failure')) {
    function red_login_record_failure($connection, $usernameHash, $clientAddress)
    {
        try {
            $stmt = mysqli_prepare(
                $connection,
                'INSERT INTO RED_Login_Attempts (UsernameHash, ClientAddress) VALUES (?, ?)'
            );
            if (!$stmt) {
                red_login_throttle_log_failure('failure insert prepare failed');
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'ss', $usernameHash, $clientAddress);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        } catch (mysqli_sql_exception $exception) {
            red_login_throttle_log_failure('failure insert unavailable');
            return false;
        }
    }
}

if (!function_exists('red_login_clear_username_failures')) {
    function red_login_clear_username_failures($connection, $usernameHash)
    {
        try {
            $stmt = mysqli_prepare($connection, 'DELETE FROM RED_Login_Attempts WHERE UsernameHash=?');
            if (!$stmt) {
                red_login_throttle_log_failure('success reset prepare failed');
                return false;
            }
            mysqli_stmt_bind_param($stmt, 's', $usernameHash);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        } catch (mysqli_sql_exception $exception) {
            red_login_throttle_log_failure('success reset unavailable');
            return false;
        }
    }
}

if (!function_exists('red_login_dummy_password_hash')) {
    function red_login_dummy_password_hash()
    {
        return '$2y$12$PczEdcFO0Kk3SbNsiFKfJu8AvfRNigSg3UdkCQruOFJQcAUD9uTCC';
    }
}

?>
