<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_feature_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';

$featureColumn = isset($_POST['VarFeatures']) ? red_admin_text($_POST['VarFeatures']) : '';
$recordIds = $_POST['RecordID'] ?? [];
if (red_admin_feature_order_column($featureColumn) === '' || !is_array($recordIds) || empty($recordIds)) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_article_ids_access($db->connection, $recordIds);
$success = red_admin_feature_update_batch(
	$db->connection,
	$_POST,
	$featureColumn,
	'template',
	'templateSelect'
);

echo $success ? 'yes' : 'no';
$db->close();
?>
