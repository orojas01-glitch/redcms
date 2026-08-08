<?php
/**
 * Protected core-owned creator for one manifest-declared add-on tool form.
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2)
    . '/includes/addon_admin_tool_form_endpoint_helpers.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('no');
}

red_require_admin(true);
$request = red_addon_admin_tool_form_create_endpoint_request($_POST);
if (!is_array($request)) {
    http_response_code(400);
    echo red_addon_admin_tool_form_ui_unavailable();
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/class/class_connection.php';
require_once dirname(__DIR__, 2) . '/includes/addon_runtime_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
try {
    red_addon_runtime_request_bootstrap(
        $db->connection,
        dirname(__DIR__, 2)
    );
    $context = red_addon_admin_tool_form_create_endpoint_context(
        $db->connection,
        $request['tool'],
        $request['form'],
        (int) ($_SESSION['AdminRecordID'] ?? 0)
    );
    if (($context['ready'] ?? false) !== true) {
        http_response_code(
            ($context['reason'] ?? '') === 'permission_denied' ? 403 : 422
        );
        echo red_addon_admin_tool_form_ui_unavailable();
    } else {
        echo red_addon_admin_tool_form_create_endpoint_render($context);
    }
} catch (Throwable $throwable) {
    error_log('RED-CMS administrator form create editor endpoint failed.');
    http_response_code(503);
    echo red_addon_admin_tool_form_ui_unavailable();
} finally {
    $db->close();
}

?>
