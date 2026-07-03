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
<?php
red_require_admin(true);

	$RecordID=(int) ($_POST['RecordID'] ?? 0);
	if ($RecordID <= 0) {
		echo 'no';
		exit;
	}

	$T = preg_replace("'<[^>]+>'U", "", $_POST['T'] ?? '');	
	switch ($T)
	{
		case 'subcategories':
			$Table='RED_SubCategories';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			$db->close();
		break;
		case 'categories':
			$Table='RED_Categories';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			$db->close();
		break;
		
		case 'sections':
			$Table='RED_Sections';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			$db->close();
		break;
		
		case 'sub':
			$Table='RED_C_Menu';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			$db->close();
		break;
		
		case 'main':
		$Table='RED_Menu';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			$db->close();
		break;
		
		case 'gal':
		$Table1='RED_C_Gallery';
		$Table2='RED_Articles';
		$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table1." WHERE RecordID='".$RecordID."'"))
				echo 'yes';			
			if ($result = $db->delete("DELETE FROM ".$Table2." WHERE RecordID='".$ArtRecordID."'"))
				echo 'yes';
			$db->close();
		break;
		
		case 'form':
		$Table1='RED_C_Form';
		$Table2='RED_Articles';
		$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table1." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			if ($result = $db->delete("DELETE FROM ".$Table2." WHERE RecordID='".$ArtRecordID."'"))
				echo 'yes';
			$db->close();
		break;
		
		case 'monstertemplate':
		$Table1='RED_C_MonsterTemplate';
		$Table2='RED_Articles';
		$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table1." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			if ($result = $db->delete("DELETE FROM ".$Table2." WHERE RecordID='".$ArtRecordID."'"))
				echo 'yes';
			$db->close();
		break;
		
		case 'allsub':
		$Table1='RED_C_Menu';
		$Table2='RED_Articles';
		$ArtRecordID=(int) ($_POST['ArtRecordID'] ?? 0);
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table1." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			if ($result = $db->delete("DELETE FROM ".$Table2." WHERE RecordID='".$ArtRecordID."'"))
				echo 'yes';
			$db->close();
		break;
		
		default:
		$Table='RED_Articles';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->delete("DELETE FROM ".$Table." WHERE RecordID='".$RecordID."'"))
				echo 'yes';
			$db->close();
		break;
		
	}
?>
