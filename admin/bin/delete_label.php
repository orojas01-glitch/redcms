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
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php' ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_content_revision_helpers.php' ?>
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
			if (!red_admin_can_manage_site()) red_admin_authorization_denied();
			$response = red_admin_area_delete_record($db->connection, 'RED_SubCategories', $RecordID) ? 'yes' : 'no';
		break;
		case 'categories':
			if (!red_admin_can_manage_site()) red_admin_authorization_denied();
			$response = red_admin_area_delete_record($db->connection, 'RED_Categories', $RecordID) ? 'yes' : 'no';
		break;
		
		case 'sections':
			if (!red_admin_can_manage_site()) red_admin_authorization_denied();
			$deleteResult = red_admin_section_archive_and_delete($db->connection, $RecordID);
			if (is_array($deleteResult)) {
				header('X-RED-Archived-Articles: ' . (int) ($deleteResult['archivedArticles'] ?? 0));
				$response = 'yes';
			} else {
				$response = 'no';
			}
		break;
		
		case 'sub':
			if (!red_admin_can_manage_site()) red_admin_authorization_denied();
			$response = red_admin_tool_delete_by_id($db->connection, 'RED_C_Menu', $RecordID) ? 'yes' : 'no';
		break;
		
		case 'main':
			if (!red_admin_can_manage_site()) red_admin_authorization_denied();
			$response = red_admin_tool_delete_by_id($db->connection, 'RED_Menu', $RecordID) ? 'yes' : 'no';
		break;
		
		case 'gal':
			$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			red_admin_require_article_access($db->connection, $ArtRecordID);
			$response = red_admin_tool_delete_component_article($db->connection, 'RED_C_Gallery', $RecordID, $ArtRecordID) ? 'yesyes' : 'no';
		break;
		
		case 'form':
			$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			red_admin_require_article_access($db->connection, $ArtRecordID);
			$response = red_admin_tool_delete_component_article($db->connection, 'RED_C_Form', $RecordID, $ArtRecordID) ? 'yesyes' : 'no';
		break;
		
		case 'monstertemplate':
			if (!red_admin_can_manage_site()) red_admin_authorization_denied();
			$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$response = red_admin_tool_delete_component_article($db->connection, 'RED_C_MonsterTemplate', $RecordID, $ArtRecordID) ? 'yesyes' : 'no';
		break;
		
		case 'allsub':
			if (!red_admin_can_manage_site()) red_admin_authorization_denied();
			$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$response = red_admin_tool_delete_component_article($db->connection, 'RED_C_Menu', $RecordID, $ArtRecordID) ? 'yesyes' : 'no';
		break;
		
		default:
			red_admin_require_article_access($db->connection, $RecordID);
			$response = red_admin_content_revision_delete_transaction(
				$db->connection,
				$RecordID,
				function () use ($db, $RecordID) {
					return red_admin_tool_delete_by_id($db->connection, 'RED_Articles', $RecordID)
						&& red_seo_delete_metadata($db->connection, 'article', $RecordID);
				},
				array_merge(
					['RED_Articles'],
					red_seo_table_available($db->connection) ? ['RED_Page_SEO'] : []
				)
			) ? 'yes' : 'no';
		break;
		
	}
	echo $response;
	$db->close();
?>
