<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 2.0 - (2014/02/25) 
 * @version: 3.0 - (2015/04/7)
 * @version: 4.0 - (2025/03/06)
 * @PHP 5.5.0
 * @author Oscar Rojas
 * Examples and documentation @: http://red-sphere.com/
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_tool_helpers.php' ?>
<?php
	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	$success = red_admin_tool_update_layout(
		$db->connection,
		$_POST['countpage'] ?? '',
		$_POST['sections'] ?? '',
		$_POST['categories'] ?? '',
		$_POST['subcategories'] ?? '',
		$_POST['article'] ?? '',
		$_POST['Layout'] ?? ''
	);
	echo $success ? 'yes' : 'no';
	$db->close();
?>
