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
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/upload_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';

function red_post_ftp_clean($value)
{
    return preg_replace("'<[^>]+>'U", '', (string) $value);
}

$allowedExtensions = red_upload_ftp_allowed_extensions();
$maxBytes = red_upload_ftp_max_bytes();
$uploadCase = red_post_ftp_clean($_GET['UC'] ?? '');

if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
    red_upload_status('Error! Wrong HTTP method!', 405);
}

if ($uploadCase !== 'FTP') {
    red_upload_status('Invalid upload target.', 400);
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_component_selection($db->connection, 'FTP');
$db->close();

if (!array_key_exists('pic', $_FILES)) {
    red_upload_status('No file was uploaded.', 400);
}

$file = $_FILES['pic'];
$fileInfo = red_upload_validate_file($file, $allowedExtensions, $maxBytes);
$storedName = red_upload_move($file, 'images/articles', $fileInfo['safe_name']);

red_upload_status('File was uploaded successfully!', 200, ['stored_name' => $storedName]);
?>
