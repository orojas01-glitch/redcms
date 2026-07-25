<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin_site_manager(true); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_advanced_helpers.php' ?>
<?php
$Language = red_admin_advanced_language($_POST['Language'] ?? '');
if ($Language === '') {
	echo 'no';
	exit;
}

$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$result = red_admin_advanced_create_language($db->connection, $Language);

if ($result === 'created') {
	echo 'yes';
} elseif ($result === 'exists') {
	echo 'error';
} else {
	echo 'no';
}

$db->close();
?>
