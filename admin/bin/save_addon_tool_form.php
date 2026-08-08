<?php
/**
 * Protected JSON Save bridge for one manifest-declared add-on tool form.
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2)
    . '/includes/addon_admin_tool_form_endpoint_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'reason' => 'method_not_allowed']);
    exit;
}

// Authentication and header CSRF verification deliberately precede body I/O.
red_require_admin(true);
$input = fopen('php://input', 'rb');
$transport = red_addon_admin_tool_form_submission_read_body(
    $_SERVER['CONTENT_TYPE'] ?? null,
    $_SERVER['CONTENT_LENGTH'] ?? null,
    $input
);
if (is_resource($input)) {
    fclose($input);
}
if (($transport['valid'] ?? false) !== true) {
    $reason = (string) ($transport['reason'] ?? 'invalid_request');
    $status = $reason === 'body_too_large'
        ? 413
        : ($reason === 'content_type_invalid' ? 415 : 400);
    http_response_code($status);
    echo json_encode(['ok' => false, 'reason' => $reason]);
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
    $result = red_addon_admin_tool_form_save_dispatch(
        $db->connection,
        $transport['rawBody'],
        (int) ($_SESSION['AdminRecordID'] ?? 0)
    );
} catch (Throwable $throwable) {
    error_log('RED-CMS administrator form Save endpoint failed.');
    $result = red_addon_admin_tool_form_save_failure(
        'temporary_unavailable'
    );
} finally {
    $db->close();
}

http_response_code((int) ($result['httpStatus'] ?? 503));
echo json_encode(
    $result['body']
        ?? ['ok' => false, 'reason' => 'temporary_unavailable']
);

?>
