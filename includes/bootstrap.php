<?php
/**
 * Shared bootstrap helpers for RED-CMS entry points.
 */

if (!defined('RED_CMS_VERSION')) {
    define('RED_CMS_VERSION', '5.0');
}

if (!function_exists('red_start_session')) {
    function red_start_session()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('red_csrf_token')) {
    function red_csrf_token()
    {
        red_start_session();

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('red_csrf_input')) {
    function red_csrf_input($fieldName = 'csrf_token')
    {
        return '<input type="hidden" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars(red_csrf_token(), ENT_QUOTES, 'UTF-8') . '" />';
    }
}

if (!function_exists('red_verify_csrf')) {
    function red_verify_csrf($fieldName = 'csrf_token')
    {
        red_start_session();

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            return false;
        }

        $requestToken = '';
        if (isset($_POST[$fieldName]) && is_string($_POST[$fieldName])) {
            $requestToken = $_POST[$fieldName];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        return $requestToken !== '' && hash_equals($_SESSION['csrf_token'], $requestToken);
    }
}

if (!function_exists('red_require_admin')) {
    function red_require_admin($requireCsrf = false)
    {
        red_start_session();

        if (empty($_SESSION['alias']) || empty($_SESSION['AdminRecordID']) || empty($_SESSION['AdminPasswordFingerprint'])) {
            http_response_code(403);
            echo 'no';
            exit;
        }

        static $validatedAdminSession = false;
        if (!$validatedAdminSession) {
            $localConfig = [];
            $localConfigFile = __DIR__ . '/config.local.php';
            if (is_file($localConfigFile)) {
                $loadedConfig = require $localConfigFile;
                if (is_array($loadedConfig)) {
                    $localConfig = $loadedConfig;
                }
            }
            $configValue = function ($constantName, $localKey, $environmentNames, $default = '') use ($localConfig) {
                if (defined($constantName)) {
                    return constant($constantName);
                }
                foreach ($environmentNames as $environmentName) {
                    $environmentValue = getenv($environmentName);
                    if ($environmentValue !== false && $environmentValue !== '') {
                        return $environmentValue;
                    }
                }
                return array_key_exists($localKey, $localConfig) ? $localConfig[$localKey] : $default;
            };
            $dbHost = $configValue('DBHOST', 'DBHOST', ['RED_DB_HOST', 'DBHOST'], 'localhost');
            $dbUser = $configValue('DBUSER', 'DBUSER', ['RED_DB_USER', 'DBUSER']);
            $dbPass = $configValue('DBPASS', 'DBPASS', ['RED_DB_PASS', 'DBPASS']);
            $dbName = $configValue('DBNAME', 'DBNAME', ['RED_DB_NAME', 'DBNAME']);

            $connection = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
            if (!$connection) {
                error_log('Admin session validation database connection failed: ' . mysqli_connect_error());
                http_response_code(503);
                echo 'no';
                exit;
            }

            $stmt = mysqli_prepare(
                $connection,
                'SELECT Alias, AdminType, AdminComponents, AdminTools, Password FROM RED_Admin WHERE RecordID=? LIMIT 1'
            );
            $recordId = (int) $_SESSION['AdminRecordID'];
            if (!$stmt) {
                mysqli_close($connection);
                http_response_code(403);
                echo 'no';
                exit;
            }

            mysqli_stmt_bind_param($stmt, 'i', $recordId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $admin = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            mysqli_close($connection);

            $fingerprint = $admin ? hash('sha256', (string) ($admin['Password'] ?? '')) : '';
            if (!$admin || !hash_equals((string) $_SESSION['AdminPasswordFingerprint'], $fingerprint)) {
                unset(
                    $_SESSION['alias'],
                    $_SESSION['AdminRecordID'],
                    $_SESSION['AdminUsername'],
                    $_SESSION['AdminType'],
                    $_SESSION['AdminComponents'],
                    $_SESSION['AdminTools'],
                    $_SESSION['AdminPasswordFingerprint']
                );
                http_response_code(403);
                echo 'no';
                exit;
            }

            $_SESSION['alias'] = (string) $admin['Alias'];
            $_SESSION['AdminType'] = (string) $admin['AdminType'];
            $_SESSION['AdminComponents'] = (string) $admin['AdminComponents'];
            $_SESSION['AdminTools'] = (string) $admin['AdminTools'];
            $validatedAdminSession = true;
        }

        if ($requireCsrf && !red_verify_csrf()) {
            http_response_code(403);
            echo 'csrf';
            exit;
        }
    }
}

if (!function_exists('red_admin_session_id_list')) {
    function red_admin_session_id_list($key)
    {
        $ids = [];
        $value = isset($_SESSION[$key]) && is_scalar($_SESSION[$key]) ? (string) $_SESSION[$key] : '';
        foreach (explode(',', $value) as $id) {
            $id = (int) trim($id);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('red_admin_has_tool_access')) {
    function red_admin_has_tool_access($toolId)
    {
        return in_array((int) $toolId, red_admin_session_id_list('AdminTools'), true);
    }
}

if (!function_exists('red_admin_has_component_access')) {
    function red_admin_has_component_access($componentId)
    {
        return in_array((int) $componentId, red_admin_session_id_list('AdminComponents'), true);
    }
}

if (!function_exists('red_require_admin_tool')) {
    function red_require_admin_tool($toolId)
    {
        if (!red_admin_has_tool_access($toolId)) {
            http_response_code(403);
            echo 'no';
            exit;
        }
    }
}

if (!function_exists('red_admin_can_manage_users')) {
    function red_admin_can_manage_users()
    {
        $adminType = strtolower((string) ($_SESSION['AdminType'] ?? ''));
        return in_array($adminType, ['webmaster', 'superadmin'], true);
    }
}

if (!function_exists('red_admin_can_manage_site')) {
    function red_admin_can_manage_site()
    {
        return red_admin_can_manage_users();
    }
}

if (!function_exists('red_require_admin_site_manager')) {
    function red_require_admin_site_manager($requireCsrf = false)
    {
        red_require_admin($requireCsrf);
        if (!red_admin_can_manage_site()) {
            error_log(
                'RED-CMS site-manager authorization denied for admin ' . (int) ($_SESSION['AdminRecordID'] ?? 0) .
                ' on ' . (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . (string) ($_SERVER['SCRIPT_NAME'] ?? 'unknown')
            );
            http_response_code(403);
            echo 'no';
            exit;
        }
    }
}

if (!function_exists('red_require_admin_user_manager')) {
    function red_require_admin_user_manager($requireCsrf = false)
    {
        red_require_admin($requireCsrf);
        if (!red_admin_can_manage_users()) {
            http_response_code(403);
            echo 'no';
            exit;
        }
    }
}

?>
