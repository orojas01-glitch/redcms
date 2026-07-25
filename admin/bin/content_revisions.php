<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin_content_revision_helpers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$contentRecordId = (int) ($_POST['ContentRecordID'] ?? 0);
$action = red_admin_content_revision_scalar($_POST['action'] ?? 'list');
if ($contentRecordId <= 0 || !in_array($action, ['list', 'restore'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'invalid']);
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_article_access($db->connection, $contentRecordId);

if ($action === 'list') {
    $history = red_admin_content_revision_list($db->connection, $contentRecordId);
    echo json_encode([
        'ok' => true,
        'available' => (bool) ($history['available'] ?? false),
        'currentHash' => (string) ($history['currentHash'] ?? ''),
        'total' => (int) ($history['total'] ?? 0),
        'revisions' => $history['revisions'] ?? [],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $db->close();
    exit;
}

$revisionId = (int) ($_POST['RevisionID'] ?? 0);
$expectedCurrentHash = red_admin_content_revision_scalar($_POST['CurrentHash'] ?? '');
if ($revisionId <= 0 || !preg_match('/\A[a-f0-9]{64}\z/', $expectedCurrentHash)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'invalid']);
    $db->close();
    exit;
}

$result = red_admin_content_revision_restore(
    $db->connection,
    $contentRecordId,
    $revisionId,
    $expectedCurrentHash
);
if (empty($result['ok'])) {
    http_response_code(($result['reason'] ?? '') === 'conflict' ? 409 : 422);
}
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$db->close();
?>
