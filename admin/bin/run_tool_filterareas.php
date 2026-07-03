<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {
	
	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	
	$Sections = $_POST['Sections'];
	$Categories = $_POST['Categories'];
	$SubCategories = $_POST['SubCategories'];
	$Article = $_POST['Article'];
	$VarPosition = $_POST['VarPosition'];
	$Position = $_POST['Position'];
	
	if ($Sections <>  ''){
		foreach ($_POST['RecordID'] as $a=>$b){		
		//echo 'a='.$a. "\n";
		//echo 'b='.$b. "\n";
		$Articles_Sel= $_POST['Articles_Sel'][$a];
		
			if ($_POST['Articles_Sel'][$a] <> ''){
				if($result = $db->update("UPDATE RED_Articles SET Sections='".$Sections."' WHERE RecordID='".$Articles_Sel."'"))
				$success = true;
				//echo 'sections updated<br/>';
			}
		}
	}
	
	if ($Categories <>  ''){
		if ($Categories ===  '-')
		$Categories = '';
		foreach ($_POST['RecordID'] as $a=>$b){		
		//echo 'a='.$a. "\n";
		//echo 'b='.$b. "\n";
		$Articles_Sel= $_POST['Articles_Sel'][$a];
		
			if ($_POST['Articles_Sel'][$a] <> ''){
				if($result = $db->update("UPDATE RED_Articles SET Categories='".$Categories."' WHERE RecordID='".$Articles_Sel."'"))
				$success = true;
				//echo 'categories updated<br/>';
			}
		}
	}
	
	
	if ($SubCategories <>  ''){
		if ($SubCategories ===  '-')
		$SubCategories = '';
		foreach ($_POST['RecordID'] as $a=>$b){		
		//echo 'a='.$a. "\n";
		//echo 'b='.$b. "\n";
		$Articles_Sel= $_POST['Articles_Sel'][$a];
		
			if ($_POST['Articles_Sel'][$a] <> ''){
				if($result = $db->update("UPDATE RED_Articles SET SubCategories='".$SubCategories."' WHERE RecordID='".$Articles_Sel."'"))
				$success = true;
				//echo 'subcategories updated<br/>';
			}
		}	
	}
	
	if ($Article <>  ''){
		if ($Article ===  '-')
		$Article = '';
		foreach ($_POST['RecordID'] as $a=>$b){		
		//echo 'a='.$a. "\n";
		//echo 'b='.$b. "\n";
		$Articles_Sel= $_POST['Articles_Sel'][$a];
		
			if ($_POST['Articles_Sel'][$a] <> ''){
				if($result = $db->update("UPDATE RED_Articles SET Article='".$Article."' WHERE RecordID='".$Articles_Sel."'"))
				$success = true;
				//echo 'subcategories updated<br/>';
			}
		}	
	}
	
	if ($Position <>  ''){
		foreach ($_POST['RecordID'] as $a=>$b){		
		//echo 'a='.$a. "\n";
		//echo 'b='.$b. "\n";
		$Articles_Sel= $_POST['Articles_Sel'][$a];
		
			if ($_POST['Articles_Sel'][$a] <> ''){
				if($result = $db->update("UPDATE RED_Articles SET ".$VarPosition."='".$Position."' WHERE RecordID='".$Articles_Sel."'"))
				$success = true;
				//echo 'subcategories updated<br/>';
			}
		}	
	}
	
	
	if ($success)
	echo 'yes';
	
	$db->close();
}
?>
