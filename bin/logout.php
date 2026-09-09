<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
if(isset($_GET['logout']))
{
	red_destroy_session();
	header('Cache-Control: no-store');
	header("Location:/");
	exit;
}
?>
