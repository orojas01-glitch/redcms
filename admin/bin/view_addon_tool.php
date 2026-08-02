<?php
/**
 * Protected display-only endpoint for manifest-declared add-on tools.
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/class/class_connection.php';
require_once dirname(__DIR__, 2) . '/includes/addon_runtime_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/addon_admin_tool_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('no');
}

red_require_admin(true);
$toolId = is_string($_POST['tool'] ?? null) ? $_POST['tool'] : '';
$actorRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);

try {
    red_addon_runtime_request_bootstrap(
        $db->connection,
        dirname(__DIR__, 2)
    );
    $result = red_addon_admin_tool_dispatch(
        $db->connection,
        $toolId,
        $actorRecordId
    );
} catch (Throwable $throwable) {
    error_log(
        'RED-CMS add-on administrator tool bootstrap failed: ' .
        $throwable->getMessage()
    );
    $db->close();
    http_response_code(503);
    exit('<p class="red-admin-error">Add-on tool is temporarily unavailable.</p>');
}
$db->close();

if (empty($result['authorized'])) {
    http_response_code(403);
    exit('no');
}
if (empty($result['success']) || !is_string($result['html'])) {
    error_log(
        'RED-CMS add-on administrator tool dispatch failed: ' .
        (string) ($result['tool'] ?? '') . ':' .
        (string) ($result['reason'] ?? '')
    );
    http_response_code(503);
    exit('<p class="red-admin-error">Add-on tool is temporarily unavailable.</p>');
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $result['html'];

?>
