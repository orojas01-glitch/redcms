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
				if ($x===0){
				$fieldsetnames = $name;
				$fieldsetvalues = "'".$RecordID."'";
				}else{
				$fieldsetnames = $fieldsetnames . ", ".$name;
				$fieldsetvalues = $fieldsetvalues . ", '" .$RecordID."'";
				}
				$x++;
			break;
			
			case 'Title':
				$Alias = strip_tags($value);
				$tags = strip_tags($value);
				$value = mysqli_real_escape_string($db->connection,$value);
				$Alias = preg_replace('/\%/',' percentage',$Alias);
				$Alias = preg_replace('/\@/',' at ',$Alias);
				$Alias = preg_replace('/\&/',' and ',$Alias);
				$Alias = preg_replace('/\s[\s]+/','-',$Alias);    // Strip off multiple spaces
				$Alias = preg_replace('/[\s\W]+/','-',$Alias);    // Strip off spaces and non-alpha-numeric
				$Alias = preg_replace('/^[\-]+/','',$Alias); // Strip off the starting hyphens
				$Alias = preg_replace('/[\-]+$/','',$Alias); // // Strip off the ending hyphens
				$Alias = strtolower($Alias); 
				$tags = preg_replace('/\%/','',$tags);
				$tags = preg_replace('/\@/','',$tags);
				$tags = preg_replace('/\&/','',$tags);
				$tags = preg_replace('/\s[\s]+/',',',$tags);    // Strip off multiple spaces
				$tags = preg_replace('/[\s\W]+/',',',$tags);    // Strip off spaces and non-alpha-numeric
				$tags = preg_replace('/^[\-]+/','',$tags); // Strip off the starting hyphens
				$tags = preg_replace('/[\-]+$/','',$tags); // // Strip off the ending hyphens
				$tags = strtolower($tags); 
				
				if ($x===0){
				$queryset = "Alias='".$Alias."', ".$name . "='".$value."', Tags='".$tags."'";
				$fieldsetnames = "Alias, Title, Tags";
				$fieldsetvalues = "'".$Alias . "', '".$value."', '".$tags."'";
				}else{
				$queryset = $queryset . ", Alias='".$Alias."', ".$name . "='".$value."', Tags='".$tags."'";
				$fieldsetnames = $fieldsetnames . ", Alias, Title, Tags";
				$fieldsetvalues = $fieldsetvalues . ", '".$Alias . "', '".$value."', '".$tags."'";
				}
				$x++;
				
			break;
			
			case 'Order':
			break;
			
			case 'NewWindow':
			break;
			
			case 'LinkNavigator':
			break;
			
			default:
				if ($x===0){
				$queryset = $name . "='".$value."'";
				$fieldsetnames = $name;
				$fieldsetvalues = "'".$value."'";
				}else{
				$queryset = $queryset . ", ".$name . "='".$value."'";
				$fieldsetnames = $fieldsetnames . ", ".$name;
				$fieldsetvalues = $fieldsetvalues . ", '" .$value."'";
				}
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
			$fieldsetnames = $fieldsetnames . ", HomePosition";
			$fieldsetvalues = $fieldsetvalues . ", '1'";
			
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
	
	// CHECK IF SECTION, CATEGORY, SUB-CATEGORY ASSIGNED HAS A POSITION VALUE
	// SECTIONS
	/*if((isset($_POST['Sections']) && $_POST['Sections'] <> ''))
	{
		//echo 'HomeFeatured = true.'. "\n";
		//check if HomePositionOrder OR SectionPositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT HomePosition, SectionPosition FROM RED_Articles WHERE RecordID='".$RecordID."'");
		if($result->num_rows > 0) 
		{
			$info = mysqli_fetch_assoc($result); 
			$HomePosition = $info['HomePosition'];
			$SectionPosition = $info['SectionPosition'];
		}
		
		if ($_POST['Sections']==='Home'){
			if(isset($_POST['HomePosition']) || $HomePosition <> '0')
			{
				//echo 'its set already or is being set'. "\n";;
			}
			else
			{
				//echo 'set now'. "\n";;
				$queryset = $queryset . ", HomePosition='0'";
				$fieldsetnames = $fieldsetnames . ", HomePosition";
				$fieldsetvalues = $fieldsetvalues . ", '0'";
			}
		}else{
			if(isset($_POST['SectionPosition']) || $SectionPosition <> '0')
			{
				//echo 'its set already or is being set'. "\n";;
			}
			else
			{
				//echo 'set now'. "\n";;
				$queryset = $queryset . ", SectionPosition='0'";
				$fieldsetnames = $fieldsetnames . ", SectionPosition";
				$fieldsetvalues = $fieldsetvalues . ", '0'";
			}
		}
		
	}

	// CATEGORIES
	if((isset($_POST['Categories']) && $_POST['Categories'] <> ''))
	{
		//echo 'HomeFeatured = true.'. "\n";
		//check if HomePositionOrder OR SectionPositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT CategoryPosition FROM RED_Articles WHERE RecordID='".$RecordID."'");
		if($result->num_rows > 0) 
		{
			$info = mysqli_fetch_assoc($result); 
			$CategoryPosition = $info['CategoryPosition'];
		}
		
		if(isset($_POST['CategoryPosition']) || $CategoryPosition <> '0')
		{
			//echo 'its set already or is being set'. "\n";;
		}
		else
		{
			//echo 'set now'. "\n";;
			$queryset = $queryset . ", CategoryPosition='0'";
			$fieldsetnames = $fieldsetnames . ", CategoryPosition";
			$fieldsetvalues = $fieldsetvalues . ", '0'";
		}
		
	}
	
	// SUBCATEGORIES
	if((isset($_POST['SubCategory']) && $_POST['SubCategory'] <> ''))
	{
		//echo 'HomeFeatured = true.'. "\n";
		//check if HomePositionOrder OR SectionPositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT SubCategoryPosition FROM RED_Articles WHERE RecordID='".$RecordID."'");
		if($result->num_rows > 0) 
		{
			$info = mysqli_fetch_assoc($result); 
			$SubCategoryPosition = $info['SubCategoryPosition'];
		}
		
		if(isset($_POST['SubCategoryPosition']) || $SubCategoryPosition <> '0')
		{
			//echo 'its set already or is being set'. "\n";;
		}
		else
		{
			//echo 'set now'. "\n";;
			$queryset = $queryset . ", SubCategoryPosition='0'";
			$fieldsetnames = $fieldsetnames . ", SubCategoryPosition";
			$fieldsetvalues = $fieldsetvalues . ", '0'";
		}
		
	}*/
	
	if (empty($RecordID) || empty($fieldsetnames) || empty($fieldsetvalues)) {
		echo 'no';
		exit;
	}
	
	
	
	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	$result = $db->query("SELECT RecordID FROM RED_Articles WHERE RecordID='".$RecordID."'");
	if($result->num_rows > 0){
		//echo "UPDATE RED_Articles SET ".$queryset." WHERE RecordID='".$RecordID."'";
		if ($result = $db->update("UPDATE RED_Articles SET ".$queryset." WHERE RecordID='".$RecordID."'"))
			echo 'yes';
		else
			echo 'no';
	} else {
		//echo "INSERT INTO RED_Articles (".$fieldsetnames.") values (".$fieldsetvalues.")";
		if ($result = $db->insert("INSERT INTO RED_Articles (".$fieldsetnames.") VALUES (".$fieldsetvalues.")"))
			echo 'yes';
		else
			echo 'no';
	}
	$db->close();
}
?>
