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
red_require_admin(true);
$payloadFields = array_diff(array_keys($_POST), ['csrf_token', 'RecordID', 'CurrentSection']);
if (empty($payloadFields) || empty($_POST['RecordID'])) {
	echo 'no';
	exit;
}
?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {
	
	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	
	$x = 0;
	$updatearticles = false;
	$section = '';
	$CurrentSection = '';
        
	foreach($_POST as $name => $value)
	{
		//
		$name = preg_replace ( "'<[^>]+>'U", "", $name);
		
		switch ($name)
		{
			case 'RecordID':
				$RecordID=mysqli_real_escape_string($db->connection,$value);
			break;
			case 'CurrentSection':
				$CurrentSection=mysqli_real_escape_string($db->connection,$value);
			break;
			case 'Title':
				$value=mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Sections':
				$value = mysqli_real_escape_string($db->connection,$value);
				$value = preg_replace('/\%/',' percentage',$value);
				$value = preg_replace('/\@/',' at ',$value);
				$value = preg_replace('/\s[\s]+/','-',$value);    // Strip off multiple spaces
				$value = preg_replace('/[\s\W]+/','-',$value);    // Strip off spaces and non-alpha-numeric
				$value = preg_replace('/^[\-]+/','',$value); // Strip off the starting hyphens
				$value = preg_replace('/[\-]+$/','',$value); // // Strip off the ending hyphens
				$value = strtolower($value); 
				$section = $value;
				
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
				$value = mysqli_real_escape_string($db->connection,$value);
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
	
	if($section <> strtolower($CurrentSection)){
		$updatearticles = true;
		$sections = $section;
		//echo 'yes';
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
		//echo 'update articles';
		$result = $db->query("SELECT Sections FROM RED_Sections WHERE Language='".language."' AND Sections='".$sections."'");
		$result_counter = $result->num_rows;
		$result2 = $db->query("SELECT Categories FROM RED_Categories WHERE Language='".language."' AND Categories='".$sections."'");
		$result_counter2 = $result2->num_rows;
		$result3 = $db->query("SELECT SubCategories FROM RED_SubCategories WHERE Language='".language."' AND SubCategories='".$sections."'");
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
		//update all articles to this renamed section.
		//echo "UPDATE RED_Articles SET Sections = '".$sections."' WHERE Sections = '".$CurrentSection."'";
		if ($updatearticles){
		if ($result = $db->update("UPDATE RED_Articles SET Sections = '".$sections."' WHERE Language='".language."' AND Sections = '".$CurrentSection."'"))
		echo 'update';
		//update navigation menu to this renamed section.
		//echo "UPDATE RED_Menu set Link = replace(Link, '/".$CurrentSection."/', '/".$Sections."/');";
		if ($result = $db->update("UPDATE RED_Menu set Link = replace(Link, '/".$CurrentSection."/', '/".$sections."/');"))
		echo 'update';
		//update component navigation menu to this renamed section.
		if ($result = $db->update("UPDATE RED_C_Menu set Link = replace(Link, '/".$CurrentSection."/', '/".$sections."/');"))
		echo 'update';	
		//update section name.
		if ($result = $db->update("UPDATE RED_Sections SET ".$queryset." WHERE RecordID='".$RecordID."'"))
		echo 'yes';
		}
	}else{
	
	
	//echo "UPDATE RED_Sections SET ".$queryset." WHERE RecordID='".$RecordID."'";

	if ($result = $db->update("UPDATE RED_Sections SET ".$queryset." WHERE RecordID='".$RecordID."'"))
		echo 'yes';
	else
		echo 'no';
		
	}
	$db->close();
}
?>
