<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
require $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/admin_article_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin_content_revision_helpers.php';

red_require_admin(true);
header('Content-Type: application/json; charset=UTF-8');

$decodeItems = static function ($value) {
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '' || strlen($value) > 24000) {
        return null;
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
};

$positionColumn = red_admin_article_position_column($_POST['VarPosition'] ?? '', '');
$layout = red_admin_text($_POST['Layout'] ?? '');
$expected = $decodeItems($_POST['ExpectedItems'] ?? null);
$target = $decodeItems($_POST['Items'] ?? null);
$expected = red_admin_article_distribution_expected_items($expected);
$target = red_admin_article_distribution_items($target);

if ($positionColumn === null || $positionColumn === '' || $layout === '' || !$expected || !$target) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'reason' => 'invalid']);
    exit;
}

$recordIds = array_column($target, 'recordId');
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_article_ids_access($db->connection, $recordIds);
$result = red_admin_article_update_distribution_batch(
    $db->connection,
    $positionColumn,
    $layout,
    $expected,
    $target
);
$db->close();

if (empty($result['ok'])) {
    http_response_code(($result['reason'] ?? '') === 'conflict' ? 409 : 422);
}
echo json_encode($result);
?>
