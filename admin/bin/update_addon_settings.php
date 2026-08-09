<?php
/** Core-owned authenticated add-on settings writer. */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/class/class_connection.php';
require_once dirname(__DIR__, 2) . '/includes/addon_setting_editor_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$request = red_addon_setting_editor_request($_POST, 'update');
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
    $actorRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
    $context = red_addon_setting_editor_context(
        $db->connection,
        $package,
        $actorRecordId
    );
    if (empty($context['ready'])) {
        $permissionDenied = ($context['reason'] ?? '') === 'permission_denied';
        http_response_code($permissionDenied ? 403 : 422);
        echo json_encode([
            'ok' => false,
            'reason' => $permissionDenied
                ? 'permission_denied'
                : 'settings_unavailable',
        ]);
        exit;
    }
    $result = red_addon_setting_editor_update(
        $db->connection,
        $package,
        $actorRecordId,
        $request['settings'],
        $request['expectedPlanSha256']
    );
} catch (Throwable $throwable) {
    error_log('RED-CMS add-on settings endpoint failed.');
    $result = [
        'ok' => false,
        'status' => '',
        'reason' => 'settings_unavailable',
        'stateSha256' => '',
    ];
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
    ['stale_plan', 'invalid_values', 'secret_submission', 'secret_unconfigured'],
    true
) ? $result['reason'] : 'settings_unavailable';
http_response_code($reason === 'stale_plan' ? 409 : 422);
echo json_encode(['ok' => false, 'reason' => $reason]);

?>
