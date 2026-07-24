<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
red_start_session();
red_require_admin_site_manager(true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/admin_custom_layout_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'ok' => false,
        'reason' => 'method',
        'message' => 'Use POST for Layout Builder actions.',
    ]);
    exit;
}

$operation = red_custom_layout_scalar($_POST['operation'] ?? '');
$layoutId = red_custom_layout_scalar($_POST['layoutId'] ?? '');
$stateHash = red_custom_layout_scalar($_POST['stateHash'] ?? '');
$projectRoot = (string) $_SERVER['DOCUMENT_ROOT'];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);

try {
    switch ($operation) {
        case 'save':
            $definitionJson = red_custom_layout_scalar($_POST['definition'] ?? '');
            if ($definitionJson === '' || strlen($definitionJson) > 131072) {
                throw new InvalidArgumentException('The layout definition is missing or too large.');
            }
            $definition = json_decode($definitionJson, true, 64, JSON_THROW_ON_ERROR);
            $result = red_admin_custom_layout_save_draft(
                $db->connection,
                $layoutId,
                red_custom_layout_scalar($_POST['label'] ?? ''),
                $definition,
                $stateHash,
                $projectRoot
            );
            break;

        case 'publish':
            $result = red_admin_custom_layout_publish(
                $db->connection,
                $layoutId,
                $stateHash,
                $projectRoot
            );
            break;

        case 'archive':
        case 'unarchive':
            $result = red_admin_custom_layout_set_archived(
                $db->connection,
                $layoutId,
                $stateHash,
                $operation === 'archive',
                $projectRoot
            );
            break;

        case 'restore':
            $result = red_admin_custom_layout_restore_revision(
                $db->connection,
                $layoutId,
                (int) red_custom_layout_scalar($_POST['revisionId'] ?? 0),
                $stateHash
            );
            break;

        default:
            $result = [
                'ok' => false,
                'reason' => 'operation',
                'message' => 'Choose a supported Layout Builder action.',
                'changed' => false,
                'layout' => null,
            ];
            break;
    }
} catch (JsonException | InvalidArgumentException $exception) {
    $result = [
        'ok' => false,
        'reason' => 'definition',
        'message' => $exception->getMessage(),
        'changed' => false,
        'layout' => null,
    ];
} catch (Throwable $exception) {
    error_log('Layout Builder endpoint failed: ' . $exception->getMessage());
    $result = [
        'ok' => false,
        'reason' => 'write',
        'message' => 'The Layout Builder action could not be completed.',
        'changed' => false,
        'layout' => null,
    ];
}

$db->close();
if (empty($result['ok'])) {
    $reason = (string) ($result['reason'] ?? '');
    http_response_code($reason === 'conflict' ? 409 : ($reason === 'write' ? 500 : 422));
}
echo json_encode(
    $result,
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
?>
