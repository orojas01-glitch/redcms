<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_content_revision_helpers.php';

$recordId = isset($_POST['RecordID']) ? (int) $_POST['RecordID'] : 0;
$component = red_admin_article_clean_value('Component', $_POST['Component'] ?? '');
if ($recordId <= 0 || $component === '' || !red_admin_article_has_payload($_POST)) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_component_selection($db->connection, $component);
$data = red_admin_article_collect_values($_POST, 'insert');
$existingRow = red_admin_article_record($db->connection, $recordId);
if (!red_admin_article_apply_home_position($db->connection, $data, $existingRow)) {
	echo 'no';
	$db->close();
	exit;
}
if ($existingRow && !red_admin_article_prepare_upload_placeholder_promotion($db->connection, $recordId, $data)) {
	echo 'no';
	$db->close();
	exit;
}

$connection = $db->connection;
$success = red_admin_content_revision_create_transaction(
	$connection,
	$recordId,
	function () use ($connection, $existingRow, $recordId, $data) {
		if ($existingRow) {
			return red_admin_article_update($connection, $recordId, $data);
		}
		return red_admin_article_insert($connection, $recordId, $data);
	},
	['RED_Articles']
);

if ($success) {
	red_admin_content_revision_response_headers($connection, $recordId);
}
echo $success ? 'yes' : 'no';
$db->close();
?>
