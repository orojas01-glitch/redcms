<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';

red_require_admin(true);

if (
    empty($_POST['RecordID']) || !is_array($_POST['RecordID']) ||
    empty($_POST['VarPosition']) || !is_array($_POST['VarPosition']) ||
    empty($_POST['PosOrder']) || !is_array($_POST['PosOrder'])
) {
    echo 'no';
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$success = red_admin_article_update_order_batch(
    $db->connection,
    $_POST['RecordID'],
    $_POST['VarPosition'],
    $_POST['PosOrder']
);

echo $success ? 'yes' : 'no';
$db->close();
?>
