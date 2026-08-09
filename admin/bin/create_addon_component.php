<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'reason' => 'method_not_allowed']);
    exit;
}
require dirname(__DIR__, 2) . '/includes/config.php';
require dirname(__DIR__, 2) . '/class/class_connection.php';
require_once dirname(__DIR__, 2) . '/includes/addon_runtime_helpers.php';
require_once dirname(__DIR__, 2)
    . '/includes/addon_component_editor_create_endpoint_helpers.php';

$unexpectedKeys = array_diff(array_keys($_POST), [
    'Component', 'Title', 'Layout', 'Language', 'componentValues',
    'csrf_token',
]);
$componentId = is_string($_POST['Component'] ?? null)
    ? $_POST['Component']
    : '';
$title = is_string($_POST['Title'] ?? null) ? $_POST['Title'] : '';
$layout = is_string($_POST['Layout'] ?? null) ? $_POST['Layout'] : '';
$language = is_string($_POST['Language'] ?? null) ? $_POST['Language'] : '';
$values = $_POST['componentValues'] ?? null;
if ($unexpectedKeys !== []
    || !red_addon_valid_capability($componentId)
    || $title === ''
    || strlen($title) > 200
    || preg_match('//u', $title) !== 1
    || preg_match('/[\x00-\x1F\x7F]/', $title) === 1
    || $layout === ''
    || strlen($layout) > 64
    || preg_match('/\A[a-z]{2}\z/D', $language) !== 1
    || !is_array($values)
) {
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
    $actorRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
    $binding = red_addon_component_editor_create_binding(
        $db->connection,
        $componentId,
        $actorRecordId
    );
    if (!is_array($binding)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'reason' => 'create_permission_denied']);
        exit;
    }
    $contentRecordId = red_addon_component_editor_create_record_id(
        $db->connection
    );
    if ($contentRecordId < 1) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'reason' => 'record_id_unavailable']);
        exit;
    }
    $metadata = [
        'title' => $title,
        'layout' => $layout,
        'language' => $language,
    ];
    $preflight = red_addon_component_editor_create_preflight(
        $db->connection,
        $binding['manifest'],
        $componentId,
        $contentRecordId,
        $actorRecordId,
        $metadata,
        $values
    );
    if (empty($preflight['ready'])) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'reason' => (string) ($preflight['reason'] ?? 'preflight_failed'),
        ]);
        exit;
    }
    $created = red_addon_component_editor_create_values(
        $db->connection,
        $binding['manifest'],
        $componentId,
        $contentRecordId,
        $actorRecordId,
        $metadata,
        $values,
        $preflight['planHash']
    );
    if (empty($created['created'])) {
        $reason = (string) ($created['reason'] ?? 'create_failed');
        http_response_code($reason === 'stale_plan' ? 409 : 422);
        echo json_encode(['ok' => false, 'reason' => $reason]);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'reason' => 'created',
        'contentRecordId' => $contentRecordId,
    ]);
} finally {
    $db->close();
}
?>
