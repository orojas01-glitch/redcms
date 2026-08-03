<?php
/**
 * Protected, unlinked endpoint for one manifest-declared administrator action.
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) .
    '/includes/addon_admin_tool_action_endpoint_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'reason' => 'method_not_allowed']);
    exit;
}

red_require_admin(true);
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/class/class_connection.php';
require_once dirname(__DIR__, 2) . '/includes/addon_runtime_helpers.php';
$request = red_addon_admin_tool_action_endpoint_request($_POST);
if (!is_array($request)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'invalid_request']);
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
try {
    red_addon_runtime_request_bootstrap(
        $db->connection,
        dirname(__DIR__, 2)
    );
    $result = red_addon_admin_tool_action_endpoint_dispatch(
        $db->connection,
        $request,
        (int) ($_SESSION['AdminRecordID'] ?? 0)
    );
} catch (Throwable $throwable) {
    error_log('RED-CMS add-on administrator action endpoint failed.');
    $result = red_addon_admin_tool_action_endpoint_result(
        503,
        false,
        '',
        'action_unavailable'
    );
} finally {
    $db->close();
}

http_response_code((int) ($result['httpStatus'] ?? 503));
echo json_encode(red_addon_admin_tool_action_endpoint_public_body($result));

?>
