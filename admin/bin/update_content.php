<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';

$recordId = isset($_POST['RecordID']) ? (int) $_POST['RecordID'] : 0;
if ($recordId <= 0 || !red_admin_article_has_payload($_POST)) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$existingRow = red_admin_article_record($db->connection, $recordId);
if (!$existingRow) {
	echo 'no';
	$db->close();
	exit;
}

$data = red_admin_article_collect_values($_POST, 'update');
red_admin_article_apply_home_position($data, $existingRow);

echo red_admin_article_update($db->connection, $recordId, $data) ? 'yes' : 'no';
$db->close();
?>
