<?php
/**
 * Shared bootstrap helpers for RED-CMS entry points.
 */

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

        if (empty($_SESSION['alias'])) {
            http_response_code(403);
            echo 'no';
            exit;
        }

        if ($requireCsrf && !red_verify_csrf()) {
            http_response_code(403);
            echo 'csrf';
            exit;
        }
    }
}

?>
