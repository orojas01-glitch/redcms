<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 4.0 - (2025/03/06)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.tv/documentation/ 
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {
	
	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	
	$x = 0;
    global $updatearticles;
        
	foreach($_POST as $name => $value)
	{
		
		$name = preg_replace ( "'<[^>]+>'U", "", $name);
		
		switch ($name)
		{
			case 'RecordID':
				$RecordID=mysqli_real_escape_string($db->connection,$value);
			break;
			case 'CurrentCategory':
				$CurrentCategory=mysqli_real_escape_string($db->connection,$value);
			break;
			case 'Title':
				$value=mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Categories':
				$value = mysqli_real_escape_string($db->connection,$value);
				$value = preg_replace('/\%/',' percentage',$value);
				$value = preg_replace('/\@/',' at ',$value);
				$value = preg_replace('/\s[\s]+/','-',$value);    // Strip off multiple spaces
				$value = preg_replace('/[\s\W]+/','-',$value);    // Strip off spaces and non-alpha-numeric
				$value = preg_replace('/^[\-]+/','',$value); // Strip off the starting hyphens
				$value = preg_replace('/[\-]+$/','',$value); // // Strip off the ending hyphens
				$value = strtolower($value); 
				$category = $value;
				
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;				
			break;
			case 'Active':
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Layout':
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'QueryLimit':
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'AccessLevel':
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Description':
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Features':				
			break;
			case 'Tags':
				$value = preg_replace('/\%/','',$value);
				$value = preg_replace('/\@/','',$value);
				$value = preg_replace('/\&/','',$value);
				$value = preg_replace('/\s[\s]+/',',',$value);    // Strip off multiple spaces
				$value = preg_replace('/[\s\W]+/',',',$value);    // Strip off spaces and non-alpha-numeric
				$value = preg_replace('/^[\-]+/','',$value); // Strip off the starting hyphens
				$value = preg_replace('/[\-]+$/','',$value); // // Strip off the ending hyphens
				$value = strtolower($value); 
				
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;

		}
	
	}
	
	if($category <> strtolower($CurrentCategory)){
		$updatearticles = true;
		$categories = $category;
	}
	
	if (isset($_POST['Features'])) {
    if (is_array($_POST['Features'])) {
        // Join array elements with commas
        $features = implode(',', $_POST['Features']);
    } else {
        // If not an array, use the value directly
        $features = $_POST['Features'];
    }
    $features = mysqli_real_escape_string($db->connection, $features);
    if ($x === 0) {
        $queryset = "Features='" . $features . "'";
    } else {
        $queryset .= ", Features='" . $features . "'";
    }
    $x++;
} else {
    if ($x === 0) {
        $queryset = "Features=''";
    } else {
        $queryset .= ", Features=''";
    }
    $x++;
}
	
	if ($updatearticles){
		$result = $db->query("SELECT Sections FROM RED_Sections WHERE Language='".language."' AND Sections='".$categories."'");
		$result_counter = $result->num_rows;
		$result2 = $db->query("SELECT Categories FROM RED_Categories WHERE Language='".language."' AND Categories='".$categories."'");
		$result_counter2 = $result2->num_rows;
		$result3 = $db->query("SELECT SubCategories FROM RED_SubCategories WHERE Language='".language."' AND SubCategories='".$categories."'");
		$result_counter3 = $result3->num_rows;
		if ($result_counter > 0){
			echo 'error';
			$updatearticles=false;
		}
		elseif ($result_counter2 > 0){
			echo 'error2';
			$updatearticles=false;
		}
		elseif ($result_counter3 > 0){
			echo 'error3';
			$updatearticles=false;
		}
		if ($updatearticles){
		//update all articles to this renamed section.
		//echo "UPDATE RED_Articles SET Sections = '".$Sections."' WHERE Sections = '".$CurrentSection."'";
		if ($result = $db->update("UPDATE RED_Articles SET Categories = '".$categories."' WHERE Language='".language."' AND Categories = '".$CurrentCategory."'"))
		echo 'update';
		//update navigation menu to this renamed section.
		//echo "UPDATE RED_Menu set Link = replace(Link, '/".$CurrentSection."/', '/".$Sections."/');";
		if ($result = $db->update("UPDATE RED_Menu set Link = replace(Link, '/".$CurrentCategory."/', '/".$categories."/');"))
		echo 'update';
		//update component navigation menu to this renamed section.
		if ($result = $db->update("UPDATE RED_C_Menu set Link = replace(Link, '/".$CurrentCategory."/', '/".$categories."/');"))
		echo 'update';	
		//update category name.
		if ($result = $db->update("UPDATE RED_Categories SET ".$queryset." WHERE RecordID='".$RecordID."'"))
		echo 'yes';
		}
		
	}else{
	
	//echo "UPDATE RED_Sections SET ".$queryset." WHERE RecordID='".$RecordID."'";

	if ($result = $db->update("UPDATE RED_Categories SET ".$queryset." WHERE RecordID='".$RecordID."'"))
		echo 'yes';
	else
		echo 'no';
	
	}
	$db->close();
}
?>
