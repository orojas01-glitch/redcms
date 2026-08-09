<?php
/** Core-owned authenticated add-on secret-reference replacement endpoint. */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/class/class_connection.php';
require_once dirname(__DIR__, 2)
    . '/includes/addon_secret_replacement_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$request = red_addon_secret_replacement_request($_POST);
if (empty($request['valid'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'invalid_request']);
    exit;
}

$package = red_addon_setting_editor_package(
    dirname(__DIR__, 2),
    $request['packageId']
);
if (!is_array($package)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'reason' => 'settings_unavailable']);
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
try {
    $result = red_addon_secret_replacement_update(
        $db->connection,
        $package,
        (int) ($_SESSION['AdminRecordID'] ?? 0),
        $request['settingReferences'],
        $request['expectedPlanSha256']
    );
} catch (Throwable $throwable) {
    error_log('RED-CMS add-on secret replacement endpoint failed.');
    $result = red_addon_secret_replacement_result();
} finally {
    $db->close();
}

if (!empty($result['ok'])) {
    echo json_encode([
        'ok' => true,
        'status' => $result['status'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$reason = in_array(
    $result['reason'] ?? '',
    [
        'stale_plan',
        'secret_unavailable',
        'secret_unconfigured',
        'invalid_values',
    ],
    true
) ? $result['reason'] : 'settings_unavailable';
http_response_code($reason === 'stale_plan' ? 409 : 422);
echo json_encode(['ok' => false, 'reason' => $reason]);

?>
