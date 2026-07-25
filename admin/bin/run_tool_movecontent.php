<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
red_require_admin_tool(1); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_tool_helpers.php' ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php' ?>
<?php
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		red_admin_require_article_ids_access($db->connection, $_POST['Articles_Sel'] ?? []);
		$success = red_admin_tool_move_articles_update($db->connection, $_POST);
		echo $success ? 'yes' : 'no';
		$db->close();
	?>
