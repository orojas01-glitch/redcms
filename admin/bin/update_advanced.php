<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin_site_manager(true); ?>
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
		$row = red_admin_advanced_record($db->connection, $RecordID);
		$cssTarget = red_admin_advanced_active_css_target($db->connection, $_SERVER['DOCUMENT_ROOT']);
		if (!$row
			|| (string) $row['Item'] !== 'Website_CSS'
			|| $cssTarget === null
			|| !array_key_exists('CSS', $_POST)
			|| is_array($_POST['CSS'])
		) {
			echo 'no';
			$db->close();
			exit;
		}

		echo red_admin_advanced_css_write(
			$cssTarget,
			$_POST['css_target_token'] ?? '',
			$_POST['CSS'] ?? ''
		);
	break;
	case 'Website_Red_Sphere_Credit':
		$content = red_admin_advanced_scalar($_POST['ShortLine'] ?? '');
		if ($RecordID <= 0 || !in_array($content, ['Y', 'N'], true)) {
			echo 'no';
			$db->close();
			exit;
		}
		echo red_admin_advanced_update_content(
			$db->connection,
			$RecordID,
			$Item,
			$content
		) ? 'yes' : 'no';
	break;
	default:
		$content = red_admin_advanced_content_from_post($_POST);
		if ($RecordID <= 0 || $content === null) {
			echo 'no';
			$db->close();
			exit;
		}

		echo red_admin_advanced_update_content($db->connection, $RecordID, $Item, $content) ? 'yes' : 'no';
	break;
}

$db->close();
?>
