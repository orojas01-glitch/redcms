<?php
/** Core-owned authenticated add-on settings editor view. */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/class/class_connection.php';
require_once dirname(__DIR__, 2) . '/includes/addon_setting_editor_helpers.php';

$request = red_addon_setting_editor_request($_POST, 'edit');
if (empty($request['valid'])) {
    http_response_code(400);
    echo red_addon_setting_editor_ui_unavailable();
    exit;
}

$package = red_addon_setting_editor_package(
    dirname(__DIR__, 2),
    $request['packageId']
);
if (!is_array($package)) {
    http_response_code(422);
    echo red_addon_setting_editor_ui_unavailable();
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
try {
    $context = red_addon_setting_editor_context(
        $db->connection,
        $package,
        (int) ($_SESSION['AdminRecordID'] ?? 0)
    );
    if (empty($context['ready'])) {
        $permissionDenied = ($context['reason'] ?? '') === 'permission_denied';
        http_response_code($permissionDenied ? 403 : 422);
        echo red_addon_setting_editor_ui_unavailable();
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo red_addon_setting_editor_render($context, red_csrf_token());
    }
} finally {
    $db->close();
}

?>
