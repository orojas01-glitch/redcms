<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
$payloadFields = array_diff(array_keys($_POST), ['csrf_token', 'ArtRecordID', 'RecordID', 'Language', 'Component', 'Layout']);
if (empty($payloadFields) || empty($_POST['ArtRecordID']) || empty($_POST['RecordID'])) {
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
	
	
	
	//echo "UPDATE RED_Articles SET ".$queryset." WHERE RecordID='".$RecordID."'";
	$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	
	
	//process article query
	$x = 0;
	foreach($_POST as $name => $value)
	{
		$value = mysqli_real_escape_string($db->connection,$value);
		$name = preg_replace ( "'<[^>]+>'U", "", $name);
		
		switch ($name)
		{
			case 'ArtRecordID':
				$ArtRecordID=mysqli_real_escape_string($db->connection,$value);
				if ($x===0){
				$afieldsetnames = "RecordID";
				$afieldsetvalues = "'".$ArtRecordID."'";
				}else{
				$afieldsetnames = $afieldsetnames . ", RecordID";
				$afieldsetvalues = $afieldsetvalues . ", '" .$ArtRecordID."'";
				}
				$x++;
			break;
			
			case 'Title':
				$Alias = $value;
				$Alias = preg_replace('/\%/',' percentage',$Alias);
				$Alias = preg_replace('/\@/',' at ',$Alias);
				$Alias = preg_replace('/\&/',' and ',$Alias);
				$Alias = preg_replace('/\s[\s]+/','-',$Alias);    // Strip off multiple spaces
				$Alias = preg_replace('/[\s\W]+/','-',$Alias);    // Strip off spaces and non-alpha-numeric
				$Alias = preg_replace('/^[\-]+/','',$Alias); // Strip off the starting hyphens
				$Alias = preg_replace('/[\-]+$/','',$Alias); // // Strip off the ending hyphens
				$Alias = strtolower($Alias); 
				
				if ($x===0){
				$aqueryset = "Alias='".$Alias."', ".$name . "='".$value."'";
				$afieldsetnames = "Alias, Title";
				$afieldsetvalues = "'".$Alias . "', '".$value."'";
				}else{
				$aqueryset = $aqueryset . ", Alias='".$Alias."', ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", Alias, Title";
				$afieldsetvalues = $afieldsetvalues . "'".$Alias . "', '".$value."'";
				}
				$x++;
			break;
			
			case 'Active':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "Active";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", Active";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'Sections':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "Sections";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", Sections";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'Categories':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "Categories";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", Categories";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'SubCategories':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "SubCategories";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", SubCategories";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'Article':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "Article";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", Article";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'HomePosition':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "HomePosition";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", HomePosition";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'SectionPosition':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "SectionPosition";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", SectionPosition";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'CategoryPosition':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "CategoryPosition";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", CategoryPosition";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'SubCategoryPosition':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "SubCategoryPosition";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", SubCategoryPosition";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			case 'PagePosition':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "PagePosition";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", PagePosition";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'HomePositionOrder':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "HomePositionOrder";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", HomePositionOrder";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'SectionPositionOrder':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "SectionPositionOrder";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", SectionPositionOrder";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			case 'CategoryPositionOrder':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "CategoryPositionOrder";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", CategoryPositionOrder";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'SubCategoryPositionOrder':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "SubCategoryPositionOrder";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", SubCategoryPositionOrder";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			case 'PagePositionOrder':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "PagePositionOrder";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", PagePositionOrder";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'HomeFeature':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "HomeFeature";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", HomeFeature";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'StartDate':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "StartDate";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", StartDate";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'ExpDate':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "ExpDate";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", ExpDate";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'SmallPictAlign':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "SmallPictAlign";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", SmallPictAlign";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'ArtRecordID':
				$ArtRecordID=mysqli_real_escape_string($db->connection,$value);
			break;
			
			case 'EditedBy':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "EditedBy";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", EditedBy";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'Language':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "Language";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", Language";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'Component':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "Component";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", Component";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			case 'Layout':
				if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "Layout";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", Layout";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;
			break;
			
			

		}
	
	}
	// CHECK FOR FEATURED CHECKBOXES
	// HOMEFEATURED
	if((isset($_POST['HomeFeature']) && $_POST['HomeFeature'] === 'Y'))
	{
		//echo 'HomeFeatured = true.'. "\n";
		//check if HomePositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT HomePosition FROM RED_Articles WHERE RecordID='".$ArtRecordID."'");
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
			$aqueryset = $aqueryset . ", HomePosition='1'";
			$afieldsetnames = $afieldsetnames . ", HomePosition";
			$afieldsetvalues = $afieldsetvalues . ", '1'";
		}
	}
	else
	{
		//echo 'homeFeatured = false.'. "\n";
		if ($x===0){
			$queryset = "HomeFeature=''";
			$x++;
		}else
			$aqueryset = $aqueryset . ", HomeFeature=''";
	}
	//
	
	// CHECK IF SECTION, CATEGORY, SUB-CATEGORY ASSIGNED HAS A POSITION VALUE
	// SECTIONS
	/*if((isset($_POST['Sections']) && $_POST['Sections'] <> ''))
	{
		//echo 'HomeFeatured = true.'. "\n";
		//check if HomePositionOrder OR SectionPositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT HomePosition, SectionPosition FROM RED_Articles WHERE RecordID='".$ArtRecordID."'");
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
				$aqueryset = $aqueryset . ", HomePosition='1'";
				$afieldsetnames = $afieldsetnames . ", HomePosition";
				$afieldsetvalues = $afieldsetvalues . ", '1'";
			}
		}else{
			if(isset($_POST['SectionPosition']) || $SectionPosition <> '0')
			{
				//echo 'its set already or is being set'. "\n";;
			}
			else
			{
				//echo 'set now'. "\n";;
				$aqueryset = $aqueryset . ", SectionPosition='1'";
				$afieldsetnames = $afieldsetnames . ", SectionPosition";
				$afieldsetvalues = $afieldsetvalues . ", '1'";
			}
		}
		
	}

	// CATEGORIES
	if((isset($_POST['Categories']) && $_POST['Categories'] <> ''))
	{
		//echo 'HomeFeatured = true.'. "\n";
		//check if HomePositionOrder OR SectionPositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT CategoryPosition FROM RED_Articles WHERE RecordID='".$ArtRecordID."'");
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
			$aqueryset = $aqueryset . ", CategoryPosition='1'";
			$afieldsetnames = $afieldsetnames . ", CategoryPosition";
			$afieldsetvalues = $afieldsetvalues . ", '1'";
		}
		
	}
	
	// SUBCATEGORIES
	if((isset($_POST['SubCategory']) && $_POST['SubCategory'] <> ''))
	{
		//echo 'HomeFeatured = true.'. "\n";
		//check if HomePositionOrder OR SectionPositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT SubCategoryPosition FROM RED_Articles WHERE RecordID='".$ArtRecordID."'");
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
			$aqueryset = $aqueryset . ", SubCategoryPosition='1'";
			$afieldsetnames = $afieldsetnames . ", SubCategoryPosition";
			$afieldsetvalues = $afieldsetvalues . ", '1'";
		}
		
	}*/
	
	// ARTICLE
	if((isset($_POST['Article']) && $_POST['Article'] <> ''))
	{
		//echo 'HomeFeatured = true.'. "\n";
		//check if HomePositionOrder OR SectionPositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT PagePosition FROM RED_Articles WHERE RecordID='".$ArtRecordID."'");
		if($result->num_rows > 0) 
		{
			$info = mysqli_fetch_assoc($result); 
			$PagePosition = $info['PagePosition'];
		}
		
		if(isset($_POST['PagePosition']) || $PagePosition <> '0')
		{
			//echo 'its set already or is being set'. "\n";;
		}
		else
		{
			//echo 'set now'. "\n";;
			$aqueryset = $aqueryset . ", pagePosition='1'";
			$afieldsetnames = $afieldsetnames . ", pagePosition";
			$afieldsetvalues = $afieldsetvalues . ", '1'";
		}
		
	}	
	
	
	//echo $aqueryset;
	//process form query
	$i = 0;	
	foreach($_POST as $name => $value)
	{
		$value = mysqli_real_escape_string($db->connection,$value);
		$name = preg_replace ( "'<[^>]+>'U", "", $name);
		
		switch ($name)
		{
			
			case 'Title':
				$Alias = $value;
				$Alias = preg_replace('/\%/',' percentage',$Alias);
				$Alias = preg_replace('/\@/',' at ',$Alias);
				$Alias = preg_replace('/\&/',' and ',$Alias);
				$Alias = preg_replace('/\s[\s]+/','-',$Alias);    // Strip off multiple spaces
				$Alias = preg_replace('/[\s\W]+/','-',$Alias);    // Strip off spaces and non-alpha-numeric
				$Alias = preg_replace('/^[\-]+/','',$Alias); // Strip off the starting hyphens
				$Alias = preg_replace('/[\-]+$/','',$Alias); // // Strip off the ending hyphens
				$Alias = strtolower($Alias); 
				
				if ($i===0){
				$fqueryset = "Alias='".$Alias."', ".$name . "='".$value."'";
				$ffieldsetnames = "Alias, Title";
				$ffieldsetvalues = "'".$Alias . "', '".$value."'";
				}else{
				$fqueryset = $fqueryset . ", Alias='".$Alias."', ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", Alias, Title";
				$ffieldsetvalues = $ffieldsetvalues . "'".$Alias . "', '".$value."'";
				}
				$i++;
				
			break;
			
			case 'RecordID':
				$RecordID=mysqli_real_escape_string($db->connection,$value);
				if ($x===0){
				$ffieldsetnames = $name;
				$ffieldsetvalues = "'".$RecordID."'";
				}else{
				$ffieldsetnames = $ffieldsetnames . ", ".$name;
				$ffieldsetvalues = $ffieldsetvalues . ", '" .$RecordID."'";
				}
				$x++;
			break;
			
			case 'ArtRecordID':
				$ArtRecordID=mysqli_real_escape_string($db->connection,$value);
				if ($x===0){
				$ffieldsetnames = "RefID";
				$ffieldsetvalues = "'".$ArtRecordID."'";
				}else{
				$ffieldsetnames = $ffieldsetnames . ", RefID";
				$ffieldsetvalues = $ffieldsetvalues . ", '" .$ArtRecordID."'";
				}
				$x++;
			break;
			
			
			case 'LongDesc':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "LongDesc";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", LongDesc";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;
			break;
			
			case 'ShortDesc':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "ShortDesc";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", ShortDesc";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;
			break;
			
			case 'NewWindow':
			break;
			
			case 'Link':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "Link";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", Link";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;
			break;
			
			case 'GalleryType':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "GalleryType";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", GalleryType";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;			
			break;
		}
		
		
	
	}
	
	if((isset($_POST['NewWindow']) && $_POST['NewWindow'] === 'Y')){
		if ($i===0){
		$fqueryset = "NewWindow='".mysqli_real_escape_string($db->connection,$_POST['NewWindow'])."'";
		$ffieldsetnames = "NewWindow";
		$ffieldsetvalues = "'".mysqli_real_escape_string($db->connection,$_POST['NewWindow'])."'";
		}else{
		$fqueryset = $fqueryset . ", NewWindow='".mysqli_real_escape_string($db->connection,$_POST['NewWindow'])."'";
		$ffieldsetnames = $ffieldsetnames . ", NewWindow";
		$ffieldsetvalues = $ffieldsetvalues .", '" .mysqli_real_escape_string($db->connection,$_POST['NewWindow'])."'";
		}
	} else{
		if ($i===0){
		$fqueryset = "NewWindow=''";
		$ffieldsetnames = "NewWindow";
		$ffieldsetvalues = "''";
		}else{
		$fqueryset = $fqueryset . ", NewWindow=''";
		$ffieldsetnames = $ffieldsetnames . ", NewWindow";
		$ffieldsetvalues = $ffieldsetvalues .", ''";
		}
	}

	
	$result = $db->query("SELECT RecordID FROM RED_Articles WHERE RecordID='".$ArtRecordID."'");
	if($result->num_rows > 0){
		if ($result = $db->update("UPDATE RED_Articles SET ".$aqueryset." WHERE RecordID='".$ArtRecordID."'"))
			echo 'yes';
		else
			echo 'no';
	}else{
	if ($result = $db->insert("INSERT INTO RED_Articles (".$afieldsetnames.") VALUES (".$afieldsetvalues.")"))
		echo 'yes';
	else
		echo 'no';
	}
		
	
	$result = $db->query("SELECT RecordID FROM RED_C_Gallery WHERE RecordID='".$RecordID."'");
	if($result->num_rows > 0){
		if ($result = $db->update("UPDATE RED_C_Gallery SET ".$fqueryset." WHERE RecordID='".$RecordID."'"))
			echo 'yes';
		else
			echo 'no';	
	}else{
		//echo "INSERT INTO RED_C_Gallery (".$ffieldsetnames.") VALUES (".$ffieldsetvalues.")"."<br/>";
		if ($result = $db->insert("INSERT INTO RED_C_Gallery (".$ffieldsetnames.") VALUES (".$ffieldsetvalues.")"))
			echo 'yes';
		else
			echo 'no';
	}
	$db->close();
}
?>
