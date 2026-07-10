<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_tool_helpers.php' ?>
<?php
	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	$success = red_admin_tool_apply_article_updates($db->connection, $_POST, false);
	echo $success ? 'yes' : 'no';
	$db->close();
?>
