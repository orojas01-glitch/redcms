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
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_build_query.php' ?>
<?php
if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {
	
	$article=preg_replace ( "'<[^>]+>'U", "", $_POST['article']);
	switch ($article)
	{
		case '':
			//echo 'no article. UPDATE layout from Categories or Section.';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			
			$layout=mysqli_real_escape_string($db->connection,$_POST['Layout']);
			$countpage=mysqli_real_escape_string($db->connection,$_POST['countpage']);
			//echo $countpage;
			$section=mysqli_real_escape_string($db->connection,$_POST['sections']);
			$category=mysqli_real_escape_string($db->connection,$_POST['categories']);
			$subcategory=mysqli_real_escape_string($db->connection,$_POST['subcategories']);
			//$articleselected=mysqli_real_escape_string($db->connection,$_POST['articleselected']);
			$tquery = new Build_Query();
			$rquery = $tquery->cp_get_query($countpage, $section, $category, $subcategory, $article);
			$metaquery=$rquery[3];
			$table=$rquery[4];
			
			//echo "UPDATE RED_".$table." SET Layout='".$layout."' WHERE Active='Y' ".$metaquery."";
			if ($result = $db->update("UPDATE RED_".$table." SET Layout='".$layout."' WHERE Active='Y' ".$metaquery.""))
				echo 'yes';
			else
				echo 'no';
		break;
		default:
			//echo 'article. UPDATE layout from Articles.';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			
			$layout=mysqli_real_escape_string($db->connection,$_POST['Layout']);
			$countpage=mysqli_real_escape_string($db->connection,$_POST['countpage']);
			//echo $countpage;
			$section=mysqli_real_escape_string($db->connection,$_POST['sections']);
			$category=mysqli_real_escape_string($db->connection,$_POST['categories']);
			$subcategory=mysqli_real_escape_string($db->connection,$_POST['subcategories']);
			//$articleselected=mysqli_real_escape_string($db->connection,$_POST['articleselected']);
			$tquery = new Build_Query();
			$rquery = $tquery->cp_get_query($countpage, $section, $category, $subcategory, $article);
			$articlequery=$rquery[0];
			$table=$rquery[4];
			
			//echo "UPDATE RED_".$table." SET Layout='".$layout."' WHERE Active='Y' ".$articlequery."";
			if ($result = $db->update("UPDATE RED_".$table." SET Layout='".$layout."' WHERE Active='Y' ".$articlequery.""))
				echo 'yes';
			else
				echo 'no';
		break;
	}
	$db->close();
}
?>
