<?php
/**
 * Protected, validation-only JSON endpoint for one administrator add-on form.
 *
 * It remains unlinked from the administrator UI and invokes no package writer.
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2)
    . '/includes/addon_admin_tool_form_submission_helpers.php';

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
    $prepared = red_addon_admin_tool_form_submission_prepare(
        $db->connection,
        $transport['rawBody'],
        (int) ($_SESSION['AdminRecordID'] ?? 0)
    );
    $public = red_addon_admin_tool_form_submission_public_result($prepared);
} catch (Throwable $throwable) {
    error_log('RED-CMS administrator form validation endpoint failed.');
    $public = [
        'httpStatus' => 503,
        'body' => ['ok' => false, 'reason' => 'temporary_unavailable'],
    ];
} finally {
    $db->close();
}

http_response_code((int) ($public['httpStatus'] ?? 503));
echo json_encode(
    $public['body'] ?? ['ok' => false, 'reason' => 'temporary_unavailable']
);

?>
