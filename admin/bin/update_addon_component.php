<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/addon_runtime_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/addon_component_editor_endpoint_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$unexpectedKeys = array_diff(
    array_keys($_POST),
    ['ContentRecordID', 'CurrentStateHash', 'componentValues', 'csrf_token']
);
$contentRecordId = filter_var($_POST['ContentRecordID'] ?? null, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);
$stateHash = is_string($_POST['CurrentStateHash'] ?? null)
    ? $_POST['CurrentStateHash']
    : '';
$submittedValues = $_POST['componentValues'] ?? null;
if ($unexpectedKeys !== []
    || $contentRecordId === false
    || !red_addon_component_editor_state_hash_valid($stateHash)
    || !is_array($submittedValues)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'invalid_request']);
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
        $permissionDenied = str_ends_with($context['reason'], 'permission_denied');
        http_response_code($permissionDenied ? 403 : 422);
        echo json_encode(['ok' => false, 'reason' => $context['reason']]);
        exit;
    }
    $validated = red_addon_component_editor_validate_values(
        $context['manifest'],
        $context['component'],
        $submittedValues
    );
    if (empty($validated['valid'])) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'reason' => 'invalid_values',
            'errors' => $validated['errors'] ?? [],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = red_addon_component_editor_update_values(
        $db->connection,
        $context['manifest'],
        $context['component'],
        $contentRecordId,
        (int) $_SESSION['AdminRecordID'],
        $stateHash,
        $submittedValues
    );
    $ok = !empty($result['updated']) || !empty($result['unchanged']);
    if (!$ok) {
        http_response_code(($result['reason'] ?? '') === 'stale_state' ? 409 : 422);
    }
    echo json_encode([
        'ok' => $ok,
        'reason' => (string) ($result['reason'] ?? 'update_failed'),
        'stateHash' => (string) ($result['stateHash'] ?? ''),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} finally {
    $db->close();
}
?>
