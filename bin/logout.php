<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
if(isset($_GET['logout']))
{
	session_destroy();
	header("Location:/");
}
?>
