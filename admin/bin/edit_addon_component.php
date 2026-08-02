<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/addon_runtime_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/addon_component_editor_endpoint_helpers.php';

$unexpectedKeys = array_diff(
    array_keys($_POST),
    ['ContentRecordID', 'csrf_token']
);
$contentRecordId = filter_var($_POST['ContentRecordID'] ?? null, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);
if ($unexpectedKeys !== [] || $contentRecordId === false) {
    http_response_code(400);
    echo red_addon_component_editor_ui_unavailable();
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
try {
    red_addon_runtime_request_bootstrap($db->connection, dirname(__DIR__, 2));
    $context = red_addon_component_editor_endpoint_context(
        $db->connection,
        $contentRecordId,
        (int) ($_SESSION['AdminRecordID'] ?? 0)
    );
    if (empty($context['ready'])) {
        http_response_code(str_ends_with($context['reason'], 'permission_denied') ? 403 : 422);
        echo red_addon_component_editor_ui_unavailable();
    } else {
        header('Cache-Control: no-store');
        echo red_addon_component_editor_endpoint_render($context, red_csrf_token());
    }
} finally {
    $db->close();
}
?>
