<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_form_helpers.php';

$artRecordId = isset($_POST['ArtRecordID']) ? (int) $_POST['ArtRecordID'] : 0;
$recordId = isset($_POST['RecordID']) ? (int) $_POST['RecordID'] : 0;

if ($artRecordId <= 0 || $recordId <= 0 || !red_admin_form_has_payload($_POST)) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);

$existingArticle = red_admin_article_record($db->connection, $artRecordId);
if (!$existingArticle || !red_admin_form_record_matches($db->connection, $recordId, $artRecordId)) {
	echo 'no';
	$db->close();
	exit;
}

$articlePost = red_admin_form_article_post($_POST, $artRecordId, 'update');
$articleData = red_admin_article_collect_values($articlePost, 'update');
red_admin_article_apply_home_position($articleData, $existingArticle);

$formData = [];
if (red_admin_form_has_form_payload($_POST)) {
	$formData = red_admin_form_collect_values($_POST, 'update', $recordId, $artRecordId);
	if (!red_admin_form_apply_table_name($_POST, $artRecordId, $formData)) {
		echo 'no';
		$db->close();
		exit;
	}
}

$connection = $db->connection;
$success = red_admin_write_transaction($connection, function () use ($connection, $artRecordId, $articleData, $recordId, $formData) {
	$articleSuccess = empty($articleData) ? true : red_admin_article_update($connection, $artRecordId, $articleData);
	$formSuccess = $articleSuccess && !empty($formData)
		? red_admin_form_update($connection, $recordId, $formData)
		: $articleSuccess;

	return $articleSuccess && $formSuccess;
}, ['RED_Articles', 'RED_C_Form']);

echo $success ? 'yes' : 'no';
$db->close();
?>
