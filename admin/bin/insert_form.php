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
				/*if ($x===0){
				$aqueryset = $name . "='".$value."'";
				$afieldsetnames = "Article";
				$afieldsetvalues = "'".$value."'";
				}else{
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$afieldsetnames = $afieldsetnames . ", Article";
				$afieldsetvalues = $afieldsetvalues .", '" .$value."'";
				}
				$x++;*/
				
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

		//
		
		if( is_array($_POST['Article'])){
			$f=0;
			foreach ($_POST['Article'] as $key => $val) {
			
			if ($f===0)
			$articles= $val;
			else
			$articles=$articles.','.$val;
			
			$f++;
			}
		}
		else{
			//echo "not array";
			$articles=$_POST['Article'];
			}
		$articles = mysqli_real_escape_string($db->connection,$articles);

		$aqueryset = $aqueryset . ", Article='".$articles."'";
		$afieldsetnames = $afieldsetnames . ", Article";
		$afieldsetvalues = $afieldsetvalues . ", '".$articles."'";
		
	}	else{
		if ($x===0)
		$aqueryset = "Article=''";
		else
		$aqueryset = $aqueryset . ", Article=''";
		$afieldsetnames = $afieldsetnames . ", Article";
		$afieldsetvalues = $afieldsetvalues . ", ''";
		$x++;
	}	
	
	
	//process form query
	$i = 0;
	foreach($_POST as $name => $value)
	{
		$value = mysqli_real_escape_string($db->connection,$value);
		$name = preg_replace ( "'<[^>]+>'U", "", $name);
		
		switch ($name)
		{
			case 'RecordID':
				$RecordID=mysqli_real_escape_string($db->connection,$value);
				if ($x===0){
				$ffieldsetnames = $name;
				$ffieldsetvalues = "'".$RecordID."'";
				}else{
				$ffieldsetnames = $ffieldsetnames . ", ".$name;
				$ffieldsetvalues = $ffieldsetvalues . ", '" .$RecordID."'";
				}
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
			
			case 'FormType':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "FormType";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", FormType";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				if ($value==='Register'||$value==='Register_StoreLogin'){
					$CreateTable=true;
					if ($_POST['TableName']=='')
					$TableName='RED_Register_'.$ArtRecordID;
					else
					$TableName=mysqli_real_escape_string($db->connection,$_POST['TableName']);
					
					$fqueryset = $fqueryset . ", TableName='".$TableName."'";
					$ffieldsetnames = $ffieldsetnames . ", TableName";
					$ffieldsetvalues = $ffieldsetvalues .", '" .$TableName."'";
				}
				$i++;
			break;
			
			/*case 'TableName':
				$TableName=$value;
				if ($TableName <> ""){
					$CreateTable=false;
					if ($i===0){
						$fqueryset = $name . "='".$value."'";
						$ffieldsetnames = "TableName";
						$ffieldsetvalues = "'".$value."'";
					}else{
						$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
						$ffieldsetnames = $ffieldsetnames . ", TableName";
						$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
						}
					$i++;
				}else{
					$Alias=preg_replace('/-/','_',$Alias);
					if ($i===0){
						$fqueryset = $name . "='RED_Register_".substr($Alias, 0, 51)."'";
						$ffieldsetnames = "TableName";
						$ffieldsetvalues = "'RED_Register_".substr($Alias, 0, 51)."'";
					}else{
						$fqueryset = $fqueryset . ", ".$name . "='RED_Register_".substr($Alias, 0, 51)."'";
						$ffieldsetnames = $ffieldsetnames . ", TableName";
						$ffieldsetvalues = $ffieldsetvalues .", 'RED_Register_".substr($Alias, 0, 51)."'";
					}
					$i++;
				}
			break;*/
			
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
				$form=$value;
				$i++;
			break;
			
			case 'Subject':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "Subject";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", Subject";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;
			break;
			
			case 'Submitter':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "Submitter";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", Submitter";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;
			break;
			
			case 'Destinatary':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "Destinatary";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", Destinatary";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;
			break;
			
			case 'CC':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "CC";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", CC";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;
			break;	
			
			case 'BCC':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "BCC";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", BCC";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;
			break;	
			
			case 'Response':
				if ($i===0){
				$fqueryset = $name . "='".$value."'";
				$ffieldsetnames = "Response";
				$ffieldsetvalues = "'".$value."'";
				}else{
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$ffieldsetnames = $ffieldsetnames . ", Response";
				$ffieldsetvalues = $ffieldsetvalues .", '" .$value."'";
				}
				$i++;
			break;	
			
		}
	
	}


	$result = $db->query("SELECT RecordID FROM RED_Articles WHERE RecordID='".$ArtRecordID."'");
	if($result->num_rows > 0){
		if ($result = $db->update("UPDATE RED_Articles SET ".$aqueryset." WHERE RecordID='".$ArtRecordID."'"))
			echo 'yes';
		else
			echo 'no';
	}else{
		//echo "INSERT INTO RED_Articles (".$afieldsetnames.") VALUES (".$afieldsetvalues.")";
	if ($result = $db->insert("INSERT INTO RED_Articles (".$afieldsetnames.") VALUES (".$afieldsetvalues.")"))
		echo 'yes';
	else
		echo 'no';
	}
		
	
	$result = $db->query("SELECT RecordID FROM RED_C_Form WHERE RecordID='".$RecordID."'");
	if($result->num_rows > 0){
		if ($result = $db->update("UPDATE RED_C_Form SET ".$fqueryset." WHERE RecordID='".$RecordID."'"))
			echo 'yes';
		else
			echo 'no';	
	}else{
		//echo "INSERT INTO RED_C_Form (".$ffieldsetnames.") VALUES (".$ffieldsetvalues.")"."<br/>";
		if ($result = $db->insert("INSERT INTO RED_C_Form (".$ffieldsetnames.") VALUES (".$ffieldsetvalues.")"))
			echo 'yes';
		else
			echo 'no';
	}
	
	// 
	if ($CreateTable){
		$outerARR = explode( ';', $form );
		$formarray = array();
		
		//iterate through the newly created array
		foreach( $outerARR as $arrvalue )
		{
			//explode this row into columns
			$innerArr = explode( '|', $arrvalue );
			
			$finalArray = array();
			
			foreach( $innerArr as $val )
			{
			  //echo $val.'<br/>';
			  $tmp = explode( '=', $val );
			  $finalArray[ $tmp[0] ] = $tmp[1];
			 
			}
			//add the newly created array of columns to the output array as a new index
			$formarray[] = $finalArray;
			
			
		}
		
		//print_r($formarray);
	
		
		for ($row = 0; $row < count($formarray); $row++)
		{
			 
			switch ($formarray[$row]['type'])
			{
			case 'textfield': /* textfield has 3 keys. */			
			$fieldstocreate=$fieldstocreate."".$formarray[$row]['name']." varchar(100) NOT NULL COMMENT '".$formarray[$row]['displayname']."',";
			break;
			
			case 'textarea': /* textarea has 5 keys. */
			$fieldstocreate=$fieldstocreate."".$formarray[$row]['name']." varchar(250) NOT NULL COMMENT '".$formarray[$row]['displayname']."',";
			break;
			
			case 'checkbox': /* checkbox has 3 keys. */
			$fieldstocreate=$fieldstocreate."".$formarray[$row]['name']." varchar(100) NOT NULL COMMENT '".$formarray[$row]['displayname']."',";
			break;
			
			case 'radio': /* radio has 4 keys. */
			$fieldstocreate=$fieldstocreate."".$formarray[$row]['name']." varchar(100) NOT NULL COMMENT '".$formarray[$row]['displayname']."',";
			break;
			
			case 'select': /* select has 4 keys. */
			$fieldstocreate=$fieldstocreate."".$formarray[$row]['name']." varchar(100) NOT NULL COMMENT '".$formarray[$row]['displayname']."',";
			break;
			
			case 'select': /* select has 4 keys. */
			$fieldstocreate=$fieldstocreate."".$formarray[$row]['name']." varchar(100) NOT NULL COMMENT '".$formarray[$row]['displayname']."',";
			break;
			
			case 'hidden': /* select has 4 keys. */
			$fieldstocreate=$fieldstocreate."".$formarray[$row]['name']." varchar(100) NOT NULL COMMENT '".$formarray[$row]['displayname']."',";
			break;
			
			case 'password': /* select has 4 keys. */
			$fieldstocreate=$fieldstocreate."".$formarray[$row]['name']." varchar(100) NOT NULL COMMENT '".$formarray[$row]['displayname']."',";
			break;
			}				
		}
		
		// if form type is register
		//echo "CREATE TABLE IF NOT EXISTS RED_Register_".substr($Alias, 0, 51)." (RecordID int(5) NOT NULL NOT NULL AUTO_INCREMENT PRIMARY KEY, ". $fieldstocreate." updatedate timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP)";
		$result = $db->create("CREATE TABLE IF NOT EXISTS ".$TableName." (RecordID int(5) NOT NULL NOT NULL AUTO_INCREMENT PRIMARY KEY, ". $fieldstocreate." updatedate timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP);");
		
		
	}
	$db->close();
	
}
?>
