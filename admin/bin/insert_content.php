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
$data = red_admin_article_collect_values($_POST, 'insert');
$existingRow = red_admin_article_record($db->connection, $recordId);
red_admin_article_apply_home_position($data, $existingRow);

if ($existingRow) {
	$success = red_admin_article_update($db->connection, $recordId, $data);
} else {
	$success = red_admin_article_insert($db->connection, $recordId, $data);
}

echo $success ? 'yes' : 'no';
$db->close();
?>
