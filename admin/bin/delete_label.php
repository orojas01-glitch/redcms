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
red_start_session(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_tool_helpers.php' ?>
<?php
red_require_admin(true);

	$RecordID=(int) ($_POST['RecordID'] ?? 0);
	if ($RecordID <= 0) {
		echo 'no';
		exit;
	}

	$T = red_admin_tool_text($_POST['T'] ?? '');
	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	$response = 'no';

	switch ($T)
	{
		case 'subcategories':
			$response = red_admin_tool_delete_by_id($db->connection, 'RED_SubCategories', $RecordID) ? 'yes' : 'no';
		break;
		case 'categories':
			$response = red_admin_tool_delete_by_id($db->connection, 'RED_Categories', $RecordID) ? 'yes' : 'no';
		break;
		
		case 'sections':
			$response = red_admin_tool_delete_by_id($db->connection, 'RED_Sections', $RecordID) ? 'yes' : 'no';
		break;
		
		case 'sub':
			$response = red_admin_tool_delete_by_id($db->connection, 'RED_C_Menu', $RecordID) ? 'yes' : 'no';
		break;
		
		case 'main':
			$response = red_admin_tool_delete_by_id($db->connection, 'RED_Menu', $RecordID) ? 'yes' : 'no';
		break;
		
		case 'gal':
			$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$response = red_admin_tool_delete_component_article($db->connection, 'RED_C_Gallery', $RecordID, $ArtRecordID) ? 'yesyes' : 'no';
		break;
		
		case 'form':
			$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$response = red_admin_tool_delete_component_article($db->connection, 'RED_C_Form', $RecordID, $ArtRecordID) ? 'yesyes' : 'no';
		break;
		
		case 'monstertemplate':
			$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$response = red_admin_tool_delete_component_article($db->connection, 'RED_C_MonsterTemplate', $RecordID, $ArtRecordID) ? 'yesyes' : 'no';
		break;
		
		case 'allsub':
			$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$response = red_admin_tool_delete_component_article($db->connection, 'RED_C_Menu', $RecordID, $ArtRecordID) ? 'yesyes' : 'no';
		break;
		
		default:
			$response = red_admin_tool_delete_by_id($db->connection, 'RED_Articles', $RecordID) ? 'yes' : 'no';
		break;
		
	}
	echo $response;
	$db->close();
?>
