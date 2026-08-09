<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'no';
    exit;
}
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/addon_runtime_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/addon_component_editor_publish_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$unexpectedKeys = array_diff(array_keys($_POST), [
    'ContentRecordID',
    'TargetPageRecordID',
    'PagePosition',
    'PagePositionOrder',
    'ParentStateHash',
    'PackageStateHash',
    'csrf_token',
]);
$integer = static function ($key, $minimum, $maximum = null) {
    $options = ['min_range' => $minimum];
    if ($maximum !== null) {
        $options['max_range'] = $maximum;
    }
    return filter_var(
        $_POST[$key] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => $options]
    );
};
$contentRecordId = $integer('ContentRecordID', 1);
$targetPageRecordId = $integer('TargetPageRecordID', 0);
$pagePosition = $integer('PagePosition', 1, 99);
$pagePositionOrder = $integer('PagePositionOrder', 0, 2147483647);
$parentStateHash = is_string($_POST['ParentStateHash'] ?? null)
    ? $_POST['ParentStateHash']
    : '';
$packageStateHash = is_string($_POST['PackageStateHash'] ?? null)
    ? $_POST['PackageStateHash']
    : '';
if ($unexpectedKeys !== []
    || $contentRecordId === false
    || $targetPageRecordId === false
    || $pagePosition === false
    || $pagePositionOrder === false
    || !red_addon_component_editor_state_hash_valid($parentStateHash)
    || !red_addon_component_editor_state_hash_valid($packageStateHash)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'invalid_request']);
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
try {
    red_addon_runtime_request_bootstrap($db->connection, dirname(__DIR__, 2));
    $actorRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
    $context = red_addon_component_editor_publish_control_context(
        $db->connection,
        $contentRecordId,
        $actorRecordId
    );
    if (empty($context['ready'])) {
        $permissionDenied = ($context['reason'] ?? '')
            === 'publish_permission_denied';
        http_response_code($permissionDenied ? 403 : 422);
        echo json_encode([
            'ok' => false,
            'reason' => (string) ($context['reason'] ?? 'placement_unavailable'),
        ]);
        exit;
    }
    $preflight = red_addon_component_editor_publish_preflight(
        $db->connection,
        $context['manifest'],
        $context['component'],
        $contentRecordId,
        $actorRecordId,
        $targetPageRecordId,
        $pagePosition,
        $pagePositionOrder,
        $parentStateHash,
        $packageStateHash
    );
    if (empty($preflight['ready'])) {
        $reason = (string) ($preflight['reason'] ?? 'preflight_failed');
        http_response_code($reason === 'stale_state' ? 409 : 422);
        echo json_encode(['ok' => false, 'reason' => $reason]);
        exit;
    }
    $result = red_addon_component_editor_publish_values(
        $db->connection,
        $context['manifest'],
        $context['component'],
        $contentRecordId,
        $actorRecordId,
        $targetPageRecordId,
        $pagePosition,
        $pagePositionOrder,
        $parentStateHash,
        $packageStateHash,
        $preflight['planHash']
    );
    if (empty($result['placed'])) {
        $reason = (string) ($result['reason'] ?? 'placement_failed');
        http_response_code(in_array(
            $reason,
            ['stale_state', 'stale_plan'],
            true
        ) ? 409 : 422);
        echo json_encode(['ok' => false, 'reason' => $reason]);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'reason' => 'placed',
        'stateHash' => (string) $result['stateHash'],
    ]);
} finally {
    $db->close();
}
?>
