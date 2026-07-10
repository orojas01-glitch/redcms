<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_advanced_helpers.php' ?>
<?php
$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$RecordID = isset($_POST['RecordID']) ? (int) red_admin_advanced_scalar($_POST['RecordID']) : 0;
$Item = red_admin_text(red_admin_advanced_scalar($_POST['Item'] ?? ''));

if ($Item === '') {
	echo 'no';
	$db->close();
	exit;
}

switch ($Item)
{
	case 'Website_CSS':
		$cssPath = red_admin_advanced_css_path($_POST['jumpCSS'] ?? '');
		if ($cssPath === '') {
			echo 'no';
			$db->close();
			exit;
		}

		$CSS = red_admin_advanced_scalar($_POST['CSS'] ?? '');
		echo file_put_contents($cssPath, $CSS) !== false ? 'yes' : 'no';
	break;
	case 'Reload':
		$cssPath = red_admin_advanced_css_path($_POST['jumpCSS'] ?? '');
		if ($cssPath === '') {
			echo 'no';
			$db->close();
			exit;
		}

		$CSS = file_get_contents($cssPath);
		echo $CSS !== false ? $CSS : 'no';
	break;
	default:
		$content = red_admin_advanced_content_from_post($_POST);
		if ($RecordID <= 0 || $content === null) {
			echo 'no';
			$db->close();
			exit;
		}

		echo red_admin_advanced_update_content($db->connection, $RecordID, $content) ? 'yes' : 'no';
	break;
}

$db->close();
?>
