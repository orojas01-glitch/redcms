<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_gallery_helpers.php';

$artRecordId = isset($_POST['ArtRecordID']) ? (int) $_POST['ArtRecordID'] : 0;
$recordId = isset($_POST['RecordID']) ? (int) $_POST['RecordID'] : 0;

if ($artRecordId <= 0 || $recordId <= 0 || !red_admin_gallery_has_payload($_POST)) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);

$existingArticle = red_admin_article_record($db->connection, $artRecordId);
if (!$existingArticle || !red_admin_gallery_record_matches($db->connection, $recordId, $artRecordId)) {
	echo 'no';
	$db->close();
	exit;
}

$articlePost = red_admin_gallery_article_post($_POST, $artRecordId, 'update');
$articleData = red_admin_article_collect_values($articlePost, 'update');
unset($articleData['NewWindow']);
red_admin_article_apply_home_position($articleData, $existingArticle);

$galleryData = red_admin_gallery_collect_values($_POST, 'update', $recordId, $artRecordId);
$connection = $db->connection;
$success = red_admin_write_transaction($connection, function () use ($connection, $artRecordId, $articleData, $recordId, $galleryData) {
	$articleSuccess = red_admin_article_update($connection, $artRecordId, $articleData);
	$gallerySuccess = $articleSuccess
		? red_admin_gallery_update($connection, $recordId, $galleryData)
		: false;

	return $articleSuccess && $gallerySuccess;
}, ['RED_Articles', 'RED_C_Gallery']);

echo $success ? 'yes' : 'no';
$db->close();
?>
