<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Component editor is unavailable.';
    exit;
}
require dirname(__DIR__, 2) . '/includes/config.php';
require dirname(__DIR__, 2) . '/class/class_connection.php';
require_once dirname(__DIR__, 2) . '/includes/addon_runtime_helpers.php';
require_once dirname(__DIR__, 2)
    . '/includes/addon_component_editor_create_endpoint_helpers.php';

$unexpectedKeys = array_diff(
    array_keys($_POST),
    ['Component', 'Layout', 'Language', 'csrf_token']
);
$componentId = is_string($_POST['Component'] ?? null)
    ? $_POST['Component']
    : '';
$layout = is_string($_POST['Layout'] ?? null) ? $_POST['Layout'] : '';
$language = is_string($_POST['Language'] ?? null) ? $_POST['Language'] : '';
if ($unexpectedKeys !== []
    || !red_addon_valid_capability($componentId)
    || $layout === ''
    || strlen($layout) > 64
    || preg_match('/\A[a-z]{2}\z/D', $language) !== 1
) {
    http_response_code(400);
    echo red_addon_component_editor_ui_unavailable();
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
try {
    red_addon_runtime_request_bootstrap(
        $db->connection,
        dirname(__DIR__, 2)
    );
    $binding = red_addon_component_editor_create_binding(
        $db->connection,
        $componentId,
        (int) ($_SESSION['AdminRecordID'] ?? 0)
    );
    if (!is_array($binding)
        || red_admin_area_layout_definition($db->connection, $layout) === null
    ) {
        http_response_code(403);
        echo red_addon_component_editor_ui_unavailable();
        exit;
    }
    header('Cache-Control: no-store');
    echo red_addon_component_editor_create_form_render(
        $binding,
        $layout,
        $language,
        red_csrf_token()
    );
} finally {
    $db->close();
}
?>
