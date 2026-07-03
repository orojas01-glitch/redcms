<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
if(empty($_SESSION['StoreUser'])){
	echo '<script>window.setTimeout(function(){window.location.href = "http://rolandkalt.com";}, 0000);</script>';
}
/*
|-----------------
| Chip Error Manipulation
|------------------
*/

error_reporting(-1);

/*
|-----------------
| Chip Constant Manipulation
|------------------
*/

define( "CHIP_DEMO_FSROOT",				$_SERVER['DOCUMENT_ROOT'] . "/" );

/*
|-----------------
| Chip Download Class
|------------------
*/

require_once("class.chip_download.php");

/*
|-----------------
| Class Instance
|------------------
*/

$download_path = CHIP_DEMO_FSROOT . "images/store/";

$file = $_REQUEST['f'];

$args = array(
		'download_path'		=>	$download_path,
		'file'				=>	$file,		
		'extension_check'	=>	TRUE,
		'referrer_check'	=>	FALSE,
		'referrer'			=>	NULL,
		);
$download = new chip_download( $args );

/*
|-----------------
| Pre Download Hook
|------------------
*/

$download_hook = $download->get_download_hook();
//$download->chip_print($download_hook);
//exit;

/*
|-----------------
| Download
|------------------
*/

if( $download_hook['download'] == TRUE ) {

	/* You can write your logic before proceeding to download */
	
	/* Let's download file */
	$download->get_download();

}

?>