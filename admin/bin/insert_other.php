<?php
/**
 * Create or promote one core Other record from the dedicated Other editor.
 * The component identity is resolved from persisted core state, never from a
 * submitted Component field.
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_content_revision_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_other_content_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_seo_helpers.php';

$recordId = isset($_POST['RecordID']) ? (int) $_POST['RecordID'] : 0;
$seoInput = red_admin_seo_collect_post($_POST);
$content = red_admin_other_prepare_content($_POST, [], 'create');
if ($recordId <= 0
    || empty($content['ok'])
    || (!red_admin_article_has_payload($_POST) && !$seoInput['present'])
    || !$seoInput['valid']
) {
    echo 'no';
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$existingRow = red_admin_article_full_record($connection, $recordId);
if ($existingRow) {
    red_admin_require_article_access($connection, $recordId);
    $component = red_admin_article_clean_value('Component', $existingRow['Component'] ?? '');
    if ($component !== 'Other') {
        echo 'no';
        $db->close();
        exit;
    }
} else {
    $component = red_admin_other_registry_component($connection);
    if ($component === '') {
        echo 'no';
        $db->close();
        exit;
    }
    red_admin_require_component_selection($connection, $component);
}

$data = red_admin_article_collect_values($_POST, 'insert');
unset($data['Component'], $data['ShortDesc'], $data['LongDesc']);
$data['Component'] = $component;
$data = array_merge($data, $content['data']);
if (!red_admin_article_apply_home_position($connection, $data, $existingRow)) {
    echo 'no';
    $db->close();
    exit;
}
if ($existingRow && !red_admin_article_prepare_upload_placeholder_promotion($connection, $recordId, $data)) {
    echo 'no';
    $db->close();
    exit;
}

$success = red_admin_content_revision_create_transaction(
    $connection,
    $recordId,
    function () use ($connection, $existingRow, $recordId, $data, $seoInput) {
        $saved = $existingRow
            ? red_admin_article_update($connection, $recordId, $data)
            : red_admin_article_insert($connection, $recordId, $data);
        if (!$saved) {
            return false;
        }
        return !$seoInput['present']
            || red_admin_seo_save($connection, 'article', $recordId, $seoInput['values']);
    },
    ['RED_Articles', 'RED_Page_SEO']
);

if ($success) {
    red_admin_content_revision_response_headers($connection, $recordId);
}
echo $success ? 'yes' : 'no';
$db->close();
?>
