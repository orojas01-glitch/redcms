<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
$payloadFields = array_diff(array_keys($_POST), ['csrf_token', 'ArtRecordID', 'RecordID']);
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
			case 'Title':				
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
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
				$aqueryset = $name . "='".$Alias."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$Alias."'";
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
				$aqueryset = $name . "='".$tags."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$tags."'";
				$x++;
			break;
			
			case 'Active':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Sections':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Categories':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SubCategories':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Article':
				/*if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;*/
			break;
			case 'HomePosition':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SectionPosition':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'CategoryPosition':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SubCategoryPosition':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'PagePosition':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'HomePositionOrder':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SectionPositionOrder':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'CategoryPositionOrder':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SubCategoryPositionOrder':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'PagePositionOrder':
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			
			case 'HomeFeature':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'StartDate':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			case 'ExpDate':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'ArtRecordID':
				$ArtRecordID=mysqli_real_escape_string($db->connection,$value);
			break;
			case 'BigPict':
				//echo $name;
				//echo $_POST['Delete'.$d];
				if ($_POST['Delete_BigPict']==='Y'){
					$value='';
				} 

				if ($x===0)
				$aqueryset = "BigPict='".$value."'";
				else
				$aqueryset = $aqueryset . ", BigPict='".$value."'";
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
				$aqueryset = "SmallPict='".$value."'";
				else
				$aqueryset = $aqueryset . ", SmallPict='".$value."'";
				$x++;
				
				$d++;
			break;
			
			case 'Delete_BigPict':
			break;
			
			case 'Delete_SmallPict':
			break;
			
			case 'SmallPictAlign':
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			
			case 'Order':
			break;

		}
	
	}
	// CHECK FOR FEATURED CHECKBOXES
	// HOMEFEATURED
	if((isset($_POST['HomeFeature']) && $_POST['HomeFeature'] === 'Y'))
	{
		//echo 'HomeFeature = true.'. "\n";
		//check if HomePositionOrder is previously set or if is being set
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT HomePosition FROM RED_Articles WHERE RecordID='".$ArtRecordID."'");
		if($result->num_rows > 0) 
		{
			$info = mysqli_fetch_array($result); 
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
	//
	
	if(isset($_POST['Article']) && is_array($_POST['Article'])){

	//echo 'array';
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
			$articles=$_POST['Article'] ?? '';

	}
		$articles = mysqli_real_escape_string($db->connection,$articles);
		if ($x===0)
		$aqueryset = "Article='".$articles."'";
		else


		$aqueryset = $aqueryset . ", Article='".$articles."'";
		$x++;	
	
	
	//process form query
	$i = 0;
	foreach($_POST as $name => $value)
	{
		$value = mysqli_real_escape_string($db->connection,$value);
		$name = preg_replace ( "'<[^>]+>'U", "", $name);
		
		switch ($name)
		{
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
				if ($i===0)
				$fqueryset = $name . "='".$Alias."'";
				else
				$fqueryset = $fqueryset . ", ".$name . "='".$Alias."'";
				$i++;
					
				
			break;
			case 'Tags':
			break;
			case 'ArtRecordID':
			break;
			case 'Active':
			break;
			case 'Sections':
			break;
			case 'Categories':
			break;
			case 'SubCategories':
			break;
			case 'Article':
			break;
			case 'HomeFeature':
			break;
			case 'HomePosition':
			break;
			case 'SectionPosition':
			break;
			case 'CategoryPosition':
			break;
			case 'SubCategoryPosition':
			break;
			case 'PagePosition':
			break;
			case 'HomePositionOrder':
			break;
			case 'SectionPositionOrder':
			break;
			case 'CategoryPositionOrder':
			break;
			case 'SubCategoryPositionOrder':
			break;
			case 'PagePositionOrder':
			break;
			case 'Order':
			break;
			case 'StartDate':
			break;
			case 'ExpDate':
			break;
			case 'BigPict':
			break;
			case 'SmallPict':
			break;
			case 'Delete_BigPict':
			break;
			case 'Delete_SmallPict':
			break;
			case 'SmallPictAlign':
			break;
			case 'Title':
				if ($i===0)
				$fqueryset = $name . "='".$value."'";
				else
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$i++;
				
			break;
			case 'RecordID':
				$RecordID=mysqli_real_escape_string($db->connection,$value);
			break;
			default:
				if ($i===0)
				$fqueryset = $name . "='".$value."'";
				else
				$fqueryset = $fqueryset . ", ".$name . "='".$value."'";
				$i++;
			break;
		}
	
	}
	
	//echo "UPDATE RED_Articles SET ".$aqueryset." WHERE RecordID='".$ArtRecordID."'";
	if ($result = $db->update("UPDATE RED_Articles SET ".$aqueryset." WHERE RecordID='".$ArtRecordID."'"))
		echo 'yes';
	else
		echo 'no';
		
	//echo "UPDATE RED_C_Form SET ".$fqueryset." WHERE RecordID='".$RecordID."'";
	if ($result = $db->update("UPDATE RED_C_Form SET ".$fqueryset." WHERE RecordID='".$RecordID."'"))
		echo 'yes';
	else
		echo 'no';
		
	$db->close();
}
?>
