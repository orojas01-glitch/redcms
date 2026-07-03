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
	$success = false;
	
	
	//process article query
	$x = 0;
	foreach($_POST as $name => $value)
	{
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
				
				if ($x===0)
				$aqueryset = "Alias='".$Alias."', ".$name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", Alias='".$Alias."', ".$name . "='".$value."'";
				$x++;
				
			break;
			case 'Active':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Sections':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Categories':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SubCategories':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'Article':
				
				/*$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;*/
			break;
			case 'HomePosition':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SectionPosition':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'CategoryPosition':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SubCategoryPosition':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'PagePosition':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'HomePositionOrder':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SectionPositionOrder':
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'CategoryPositionOrder':
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'SubCategoryPositionOrder':
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
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
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'StartDate':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			case 'ExpDate':
				$name = preg_replace ( "'<[^>]+>'U", "", $name);
				//$value = preg_replace ( "'<[^>]+>'U", "", $value);
				$value = mysqli_real_escape_string($db->connection,$value);
				if ($x===0)
				$aqueryset = $name . "='".$value."'";
				else
				$aqueryset = $aqueryset . ", ".$name . "='".$value."'";
				$x++;
			break;
			case 'ArtRecordID':
				$ArtRecordID=mysqli_real_escape_string($db->connection,$value);
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
		}
	}
	else
	{
		//echo 'homeFeatured = false.'. "\n";
		if ($x===0){
			$aqueryset = "HomeFeature=''";
			$x++;
		}else
			$aqueryset = $aqueryset . ", HomeFeature=''";
	}
	//

	//
	
/*	if (isset($_POST['Article'])){
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
		if ($x===0)
		$aqueryset = "Article='".$articles."'";
		else
		$aqueryset = $aqueryset . ", Article='".$articles."'";
		$x++;
	}else{
		if ($x===0)
		$aqueryset = "Article=''";
		else
		$aqueryset = $aqueryset . ", Article=''";
		$x++;
	}*/
	
	//
	
	//
	if((isset($_POST['Article']) && $_POST['Article'] <> ''))
	{
		//echo 'article = true.'. "\n";
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
		if ($x===0)
		$aqueryset = "Article='".$articles."'";
		else
		$aqueryset = $aqueryset . ", Article='".$articles."'";
		$x++;
		
	}	else{
		if ($x===0)
		$aqueryset = "Article=''";
		else
		$aqueryset = $aqueryset . ", Article=''";
		$x++;
	}
	//
	
	
	
	
	
	
	//process form query
	$i = 0;
	foreach($_POST as $name => $value)
	{
		switch ($name)
		{
		case 'Title':
			$Title=mysqli_real_escape_string($db->connection,$value);
			if($result = $db->update("UPDATE RED_C_Menu SET Title='".$Title."' WHERE RefID='".preg_replace ( "'<[^>]+>'U", "", $_POST['ArtRecordID'])."'"));
			$success = true;
			if ($success)
			echo 'yes';
		break;
		
		case 'MenuType':
			$MenuType=mysqli_real_escape_string($db->connection,$value);
			if($result = $db->update("UPDATE RED_C_Menu SET MenuType='".$MenuType."' WHERE RefID='".preg_replace ( "'<[^>]+>'U", "", $_POST['ArtRecordID'])."'"));
			$success = true;
			if ($success)
			echo 'yes';
		break;
		
		case 'RecordID':
			$RecordID=mysqli_real_escape_string($db->connection,$value);
		break;
		
		case 'NewLabel':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
			//$value = preg_replace ( "'<[^>]+>'U", "", $value);
			$value = mysqli_real_escape_string($db->connection,$value);
			if($value){
			//echo "INSERT INTO RED_C_Menu (RefID, RootOrder, Title, Alias, Label, MenuOrder) VALUES ('".$_POST['ArtRecordID']."', '1', '".$_POST['Title']."', '".$Alias."', '".$value."', '".$_POST['NewLabelOrder']."')";
			if ($result = $db->insert("INSERT INTO RED_C_Menu (RefID, RootOrder, Title, Label, MenuOrder, MenuType) VALUES ('".preg_replace ( "'<[^>]+>'U", "", $_POST['ArtRecordID'])."', '1', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Title'])."', '".$value."', '".preg_replace ( "'<[^>]+>'U", "", $_POST['NewMenuOrder'])."','".preg_replace ( "'<[^>]+>'U", "", $_POST['MenuType'])."')"))
			echo 'yes';
			}
		break;
		
		case 'NewMenuOrder':
		break;
		
		case 'MainLabelRecordID':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
			
			if( is_array($value)){
				$f=0;
				foreach ($value as $key => $val) {
				if (is_array($val)) {
					$val = implode(',', $val);
				}
				
				if ($f===0)
				$MainLabelRecordID= mysqli_real_escape_string($db->connection,$val);
				else
				$MainLabelRecordID=$MainLabelRecordID.'|'.mysqli_real_escape_string($db->connection,$val);
				
				$f++;
				}
			}
			
		//echo 'MainLabelRecordID = '.$MainLabelRecordID. "\n";
		break;	
		
		case 'MainLabel':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
			
			if( is_array($value)){
				$f=0;
				foreach ($value as $key => $val) {
				if (is_array($val)) {
					$val = implode(',', $val);
				}
				
				if ($f===0)
				$MainLabel= mysqli_real_escape_string($db->connection,$val);
				else
				$MainLabel=$MainLabel.'|'.mysqli_real_escape_string($db->connection,$val);
				
				$f++;
				}
			}
		//echo 'MainLabel = '.$MainLabel. "\n";
		break;	
		
		case 'MainMenuOrder':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
			
			if( is_array($value)){
				$f=0;
				foreach ($value as $key => $val) {
				if (is_array($val)) {
					$val = implode(',', $val);
				}
				
				if ($f===0)
				$MainMenuOrder= mysqli_real_escape_string($db->connection,$val);
				else
				$MainMenuOrder=$MainMenuOrder.'|'.mysqli_real_escape_string($db->connection,$val);
				
				$f++;
				}
			}
		//echo 'MainMenuOrder = '.$MainMenuOrder. "\n";
		break;	
		
		case 'MainLabelLink':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
			
			if( is_array($value)){
				$f=0;
				foreach ($value as $key => $val) {
				if (is_array($val)) {
					$val = implode(',', $val);
				}
				
				if ($f===0)
				$MainLabelLink= mysqli_real_escape_string($db->connection,$val);
				else
				$MainLabelLink=$MainLabelLink.'|'.mysqli_real_escape_string($db->connection,$val);
				$f++;
				}
			}
			//echo 'MainLabelLink = '.$MainLabelLink. "\n";
		break;
		
		case 'MainLabelNewWindow':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
			if( is_array($value)){
				$f=0;
				foreach ($value as $key => $val) {
				if (is_array($val)) {
					$val = implode(',', $val);
				}
				if ($f===0)
				$MainLabelLink= mysqli_real_escape_string($db->connection,$val);
				else
				$MainLabelNewWindow=$MainLabelNewWindow.'|'.mysqli_real_escape_string($db->connection,$val);
				$f++;
				}
			}
			//echo 'MainLabelNewWindow = '.$MainLabelNewWindow. "\n";
		break;
			
		}
	
	}
	
	// 1. EXPLODE THE MAIN LABELS AND SAVE DATA.
	
	//$MainLabel = explode("|", $MainLabel);
//	$MainLabelRecordID = explode("|", $MainLabelRecordID);
//	$MainMenuOrder = explode("|", $MainMenuOrder);
//	$MainLabelLink = explode("|", $MainLabelLink);
//	$MainLabelNewWindow = explode("|", $MainLabelNewWindow);
//	
//	for($i=0;$i<count($MainLabel);$i++){	
//		//echo "UPDATE RED_C_Menu SET Label='".$ThisLabel[$i]."', MenuOrder='".$MainMenuOrder[$i]."', Link = '".$MainLabelLink[$i]."', NewWindow='".$MainLabelNewWindow[$i]."' WHERE RecordID='".$MainLabelRecordID[$i]."'". "\n";
//		if ($result = $db->update("UPDATE RED_C_Menu SET Label='".$MainLabel[$i]."', MenuOrder='".$MainMenuOrder[$i]."', Link = '".$MainLabelLink[$i]."', NewWindow='".$MainLabelNewWindow[$i]."' WHERE RecordID='".$MainLabelRecordID[$i]."'"))
//		$success = true;
//	}
	
	foreach (($_POST['MainLabel'] ?? []) as $a=>$b){
    // $a == $MainLabel Number
		foreach($b as $c=>$ThisMainLabel) {
		//$c == SubLabel Position in Array
			//echo $a;
//			
//			echo 'ThisMainLabel = '. mysqli_real_escape_string($db->connection,$ThisMainLabel). "\n ";
//			echo 'MainLabelRecordID = '.mysqli_real_escape_string($db->connection,$_POST['MainLabelRecordID'][$a][$c]). "\n ";
//			echo 'MainMenuOrder= '.mysqli_real_escape_string($db->connection,$_POST['MainMenuOrder'][$a][$c]). "\n ";
//			echo 'MainLabelLink = '.mysqli_real_escape_string($db->connection,$_POST['MainLabelLink'][$a][$c]). "\n ";
//			echo 'MainLabelNewWindow = '.mysqli_real_escape_string($db->connection,$_POST['MainLabelNewWindow'][$a][$c]). "\n \n ";
			
			//echo "UPDATE RED_C_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisSubLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['SubMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['SubLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['SubLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['SubLabelRecordID'][$a][$c])."'";
			if ($result = $db->update("UPDATE RED_C_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisMainLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['MainMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['MainLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['MainLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['MainLabelRecordID'][$a][$c])."'"))
			$success = true;
		}
	}
	
	if ($success)
	echo 'yes';
	
	
	// 2. SAVE SUBLABELS DATA.
	
	foreach (($_POST['SubLabel'] ?? []) as $a=>$b){
    // $a == $MainLabel Number
		foreach($b as $c=>$ThisSubLabel) {
		//$c == SubLabel Position in Array
			//echo $c;
			/*echo 'ThisSubLabel = '. mysqli_real_escape_string($db->connection,$ThisSubLabel). "\n ";
			echo 'SubLabelRecordID = '.mysqli_real_escape_string($db->connection,$_POST['SubLabelRecordID'][$a][$c]). "\n ";
			echo 'SubMenuOrder= '.mysqli_real_escape_string($db->connection,$_POST['SubMenuOrder'][$a][$c]). "\n ";
			echo 'SubLabelLink = '.mysqli_real_escape_string($db->connection,$_POST['SubLabelLink'][$a][$c]). "\n ";
			echo 'SubLabelNewWindow = '.mysqli_real_escape_string($db->connection,$_POST['SubLabelNewWindow'][$a][$c]). "\n \n ";*/
			
			//echo "UPDATE RED_C_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisSubLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['SubMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['SubLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['SubLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['SubLabelRecordID'][$a][$c])."'";
			if ($result = $db->update("UPDATE RED_C_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisSubLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['SubMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['SubLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['SubLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['SubLabelRecordID'][$a][$c])."'"))
			$success = true;
		}
	}
	
	if ($success)
	echo 'yes';
	
	
	// 3. SAVE NEW SUBLABELS DATA.
	
	foreach (($_POST['NewSubLabel'] ?? []) as $a=>$b){
    // $a == $NewSubLabel Number
		foreach($b as $c=>$ThisNewSubLabel) {
		//$c == NewSubLabel Position in Array
			/*echo 'ThisNewSubLabel = '. mysqli_real_escape_string($db->connection,$ThisNewSubLabel). "\n ";
			echo 'NewSubMenuOrder= '.mysqli_real_escape_string($db->connection,$_POST['NewSubMenuOrder'][$a][$c]). "\n ";
			echo 'NewSubLabelLink = '.mysqli_real_escape_string($db->connection,$_POST['NewSubLabelLink'][$a][$c]). "\n ";
			echo 'NewSubLabelNewWindow = '.mysqli_real_escape_string($db->connection,$_POST['NewSubLabelNewWindow'][$a][$c]). "\n ";
			echo 'MainLabelRecordID = '.$MainLabelRecordID[$a-1]. "\n \n ";*/
			if($ThisNewSubLabel <> '')
			//echo "INSERT INTO RED_C_Menu (RefID, RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, MenuType) VALUES ('".preg_replace ( "'<[^>]+>'U", "", $_POST['ArtRecordID'])."', '2', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Title'])."', '".mysqli_real_escape_string($db->connection,$ThisNewSubLabel)."', '".preg_replace ( "'<[^>]+>'U", "", $MainLabelRecordID[$a-1])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelLink'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelNewWindow'][$a][$c])."', '".preg_replace ( "'<[^>]+>'U", "", $_POST['NewSubMenuOrder'][$a][$c])."','".preg_replace ( "'<[^>]+>'U", "", $_POST['MenuType'])."')". "\n ";
			if ($result = $db->insert("INSERT INTO RED_C_Menu (RefID, RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, MenuType) VALUES ('".preg_replace ( "'<[^>]+>'U", "", $_POST['ArtRecordID'])."', '2', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Title'])."', '".mysqli_real_escape_string($db->connection,$ThisNewSubLabel)."', '".preg_replace ( "'<[^>]+>'U", "",$_POST['MainLabelRecordID'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelLink'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelNewWindow'][$a][$c])."', '".preg_replace ( "'<[^>]+>'U", "", $_POST['NewSubMenuOrder'][$a][$c])."','".preg_replace ( "'<[^>]+>'U", "", $_POST['MenuType'])."')"))
			$success = true;
		}
	}
	
	if ($success)
	echo 'yes';
	
	// 4. SAVE ARTICLE DATA.
	
	//echo "UPDATE RED_Articles SET ".$aqueryset." WHERE RecordID='".$ArtRecordID."'";
	if ($result = $db->update("UPDATE RED_Articles SET ".$aqueryset." WHERE RecordID='".$ArtRecordID."'"))
		echo 'yes';

	
	
	$db->close();
}
?>
