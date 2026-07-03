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
	
	$x = 0;
	foreach($_POST as $name => $value)
	{
		$value = mysqli_real_escape_string($db->connection,$value);
		$name = preg_replace ( "'<[^>]+>'U", "", $name);
		
		switch ($name)
		{
			case 'RecordID':
				$RecordID=mysqli_real_escape_string($db->connection,$value);
			break;
			case 'Title':				
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
				
			break;
			
			case 'Alias':
				$Alias = strip_tags($value);
				$Alias = preg_replace('/\%/',' percentage',$Alias);
				$Alias = preg_replace('/\@/',' at ',$Alias);
				$Alias = preg_replace('/\&/',' and ',$Alias);
				$Alias = preg_replace('/\s[\s]+/','-',$Alias);    // Strip off multiple spaces
				$Alias = preg_replace('/[\s\W]+/','-',$Alias);    // Strip off spaces and non-alpha-numeric
				$Alias = preg_replace('/^[\-]+/','',$Alias); // Strip off the starting hyphens
				$Alias = preg_replace('/[\-]+$/','',$Alias); // // Strip off the ending hyphens
				$Alias = strtolower($Alias); 
				if ($x===0)
				$queryset = $name . "='".$Alias."'";
				else
				$queryset = $queryset . ", ".$name . "='".$Alias."'";
				$x++;
					
				
			break;
			
			case 'Tags':
				$tags = $value;
				$tags = preg_replace('/\%/','',$tags);
				$tags = preg_replace('/\@/','',$tags);
				$tags = preg_replace('/\&/','',$tags);
				$tags = preg_replace('/\s[\s]+/',',',$tags);    // Strip off multiple spaces
				$tags = preg_replace('/[\s\W]+/',',',$tags);    // Strip off spaces and non-alpha-numeric
				$tags = preg_replace('/^[\-]+/','',$tags); // Strip off the starting hyphens
				$tags = preg_replace('/[\-]+$/','',$tags); // // Strip off the ending hyphens
				$tags = strtolower($tags); 
				if ($x===0)
				$queryset = $name . "='".$tags."'";
				else
				$queryset = $queryset . ", ".$name . "='".$tags."'";
				$x++;
			break;
			
			case 'BigPict':
				//echo $name;
				//echo $_POST['Delete'.$d];
				if ($_POST['Delete_BigPict']==='Y'){
					$value='';
				} 

				if ($x===0)
				$queryset = "BigPict='".$value."'";
				else
				$queryset = $queryset . ", BigPict='".$value."'";
				$x++;
				
				$d++;
			break;
			
			case 'SmallPict':
				//echo $name;
				//echo $_POST['Delete'.$d];
				if ($_POST['Delete_SmallPict']==='Y'){
					$value='';
				} 

				if ($x===0)
				$queryset = "SmallPict='".$value."'";
				else
				$queryset = $queryset . ", SmallPict='".$value."'";
				$x++;
				
				$d++;
			break;
			
			case 'SmallPict2':
				//echo $name;
				//echo $_POST['Delete'.$d];
				if ($_POST['Delete_SmallPict2']==='Y'){
					$value='';
				} 

				if ($x===0)
				$queryset = "SmallPict2='".$value."'";
				else
				$queryset = $queryset . ", SmallPict2='".$value."'";
				$x++;
				
				$d++;
			break;
			
			case 'Delete_BigPict':
			break;
			
			case 'Delete_SmallPict':
			break;
			
			case 'Delete_SmallPict2':
			break;
			
			case 'Order':
			break;
			
			case 'NewWindow':
			break;
			
			case 'LinkNavigator':
			break;
			
			default:
				
				if ($x===0)
				$queryset = $name . "='".$value."'";
				else
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$x++;
			break;
		}
	
	}
	
	
	if((isset($_POST['NewWindow']) && $_POST['NewWindow'] === 'Y')){
		if ($x===0)
		$queryset = "NewWindow='".mysqli_real_escape_string($db->connection,$_POST['NewWindow'])."'";
		else
		$queryset = $queryset . ", NewWindow='".mysqli_real_escape_string($db->connection,$_POST['NewWindow'])."'";
	} else{
		if ($x===0)
		$queryset = "NewWindow=''";
		else
		$queryset = $queryset . ", NewWindow=''";
	}
	
	// CHECK FOR FEATURED CHECKBOXES
	// HOMEFEATURED
	if((isset($_POST['HomeFeature']) && $_POST['HomeFeature'] === 'Y'))
	{
		//echo 'HomeFeatured = true.'. "\n";
		//check if HomePositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT HomePosition FROM RED_Articles WHERE RecordID='".$RecordID."'");
		if($result->num_rows > 0) 
		{
			$info = mysqli_fetch_assoc($result); 
			$HomePosition = $info['HomePosition'];
		}
		if(isset($_POST['HomePosition']) || $HomePosition <> '0')
		{
			//echo 'its set already or is being set'. "\n";;
		}
		else
		{
			//echo 'set now'. "\n";;
			$queryset = $queryset . ", HomePosition='1'";
		}
	}
	else
	{
		//echo 'homeFeatured = false.'. "\n";
		if ($x===0){
			$queryset = "HomeFeature=''";
			$x++;
		}else
			$queryset = $queryset . ", HomeFeature=''";
	}
	//
	

	
	
	
	//echo "UPDATE RED_Articles SET ".$queryset." WHERE RecordID='".$RecordID."'";
	if (empty($RecordID) || empty($queryset)) {
		echo 'no';
		$db->close();
		exit;
	}
	
	if ($result = $db->update("UPDATE RED_Articles SET ".$queryset." WHERE RecordID='".$RecordID."'"))
		echo 'yes';
	else
		echo 'no';
	$db->close();
}
?>
