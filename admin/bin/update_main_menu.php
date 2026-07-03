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
$payloadFields = array_diff(array_keys($_POST), ['csrf_token', 'Language']);
if (empty($payloadFields)) {
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
	
	
	//process form query
	$i = 0;
	foreach($_POST as $name => $value)
	{
		switch ($name)
		{
		case 'Title':
			$Title=mysqli_real_escape_string($db->connection,$value);
			if ($result = $db->update("UPDATE RED_Menu SET Title='".$Title."' WHERE Title='".preg_replace ( "'<[^>]+>'U", "", $_POST['CurTitle'])."'"));
			$success = true;
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
			if ($result = $db->insert("INSERT INTO RED_Menu (RootOrder, Title, Label, MenuOrder, Active, Language) VALUES ('1', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Title'])."', '".$value."', '".preg_replace ( "'<[^>]+>'U", "", $_POST['NewMenuOrder'])."', 'Y', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Language'])."')"))
			$success = true;
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
		
		
		case 'SubLabelRecordID':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
			
			if( is_array($value)){
				$f=0;
				foreach ($value as $key => $val) {
                if (is_array($val)) {
                    $val = implode(',', $val);
                }    
				if ($f===0)
				$SubLabelRecordID= mysqli_real_escape_string($db->connection,$val);
				else
				$SubLabelRecordID=$SubLabelRecordID.'|'.mysqli_real_escape_string($db->connection,$val);
				
				$f++;
				}
			}
			
			
		//echo 'SubLabelRecordID = '.$SubLabelRecordID. "\n";
		break;
		
		
		case 'SubSubLabelRecordID':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
			
			if( is_array($value)){
				$f=0;
				foreach ($value as $key => $val) {
                if (is_array($val)) {
                        $val = implode(',', $val);
                }
				if ($f===0)
				$SubSubLabelRecordID= mysqli_real_escape_string($db->connection,$val);
				else
				$SubSubLabelRecordID=$SubSubLabelRecordID.'|'.mysqli_real_escape_string($db->connection,$val);
				
				$f++;
				}
			}
			
			
		//echo 'SubSubLabelRecordID = '.$SubSubLabelRecordID. "\n";
		break;
		
		
		/*case 'MainLabel':
			$name = preg_replace ( "'<[^>]+>'U", "", $name);
			
			if( is_array($value)){
				$f=0;
				foreach ($value as $key => $val) {
				
				if ($f===0)
				$MainLabel= mysqli_real_escape_string($db->connection,$val);
				else
				$MainLabel=$MainLabel.'|'.mysqli_real_escape_string($db->connection,$val);
				
				$f++;
				}
			}
		//echo 'MainLabel = '.$MainLabel. "\n";
		break;*/	
                
        case 'MainLabel':
                $name = preg_replace("'<[^>]+>'U", "", $name);
                if (is_array($value)) {
                    $f = 0;
                    foreach ($value as $key => $val) {
                        if (is_array($val)) {
                            $val = implode(',', $val);
                        }
                        if ($f === 0)
                            $MainLabel = mysqli_real_escape_string($db->connection, $val);
                        else
                            $MainLabel .= '|' . mysqli_real_escape_string($db->connection, $val);
                        $f++;
                    }
                }
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
				$MainLabelNewWindow= mysqli_real_escape_string($db->connection,$val);
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
	
	//echo 'FIRST LEVEL'."\n";
	foreach (($_POST['MainLabel'] ?? []) as $a=>$b){
    // $a == $MainLabel Number
		foreach($b as $c=>$ThisMainLabel) {
		//$c == SubLabel Position in Array
			//echo $a;
			/*echo 'ThisMainLabel = '. mysqli_real_escape_string($db->connection,$ThisMainLabel). "\n ";
			echo 'MainLabelRecordID = '.mysqli_real_escape_string($db->connection,$_POST['MainLabelRecordID'][$a][$c]). "\n ";
			echo 'MainMenuOrder= '.mysqli_real_escape_string($db->connection,$_POST['MainMenuOrder'][$a][$c]). "\n ";
			echo 'MainLabelLink = '.mysqli_real_escape_string($db->connection,$_POST['MainLabelLink'][$a][$c]). "\n ";
			echo 'MainLabelNewWindow = '.mysqli_real_escape_string($db->connection,$_POST['MainLabelNewWindow'][$a][$c]). "\n \n ";*/
			
			//echo "UPDATE RED_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisMainLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['MainMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['MainLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['MainLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['MainLabelRecordID'][$a][$c])."'". "\n \n";
			if ($result = $db->update("UPDATE RED_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisMainLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['MainMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['MainLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['MainLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['MainLabelRecordID'][$a][$c])."'"))
			$success = true;
		}
	}
	
	//if ($success)
	//echo 'yes';
	
	
	// 2. SAVE SUBLABELS DATA.
	
	//echo 'SECOND LEVEL'."\n";
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
			
			//echo "UPDATE RED_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisSubLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['SubMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['SubLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['SubLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['SubLabelRecordID'][$a][$c])."'". "\n \n";
			if ($result = $db->update("UPDATE RED_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisSubLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['SubMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['SubLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['SubLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['SubLabelRecordID'][$a][$c])."'"))
			$success = true;
		}
	}
	
	//if ($success)
	//echo 'yes';
	
	
	// 3. SAVE SUBSUBLABELS DATA.
	
	//echo 'THIRD LEVEL'."\n";
	foreach (($_POST['SubSubLabel'] ?? []) as $a=>$b){
    // $a == $MainLabel Number
		foreach($b as $c=>$ThisSubSubLabel) {
		//$c == SubLabel Position in Array
			//echo $c;
			/*echo 'ThisSubSubLabel = '. mysqli_real_escape_string($db->connection,$ThisSubSubLabel). "\n ";
			echo 'SubSubLabelRecordID = '.mysqli_real_escape_string($db->connection,$_POST['SubSubLabelRecordID'][$a][$c]). "\n ";
			echo 'SubSubMenuOrder= '.mysqli_real_escape_string($db->connection,$_POST['SubSubMenuOrder'][$a][$c]). "\n ";
			echo 'SubSubLabelLink = '.mysqli_real_escape_string($db->connection,$_POST['SubSubLabelLink'][$a][$c]). "\n ";
			echo 'SubSubLabelNewWindow = '.mysqli_real_escape_string($db->connection,$_POST['SubSubLabelNewWindow'][$a][$c]). "\n \n ";*/
			
			//echo "UPDATE RED_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisSubSubLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['SubSubMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['SubSubLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['SubSubLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['SubSubLabelRecordID'][$a][$c])."'". "\n \n";
			if ($result = $db->update("UPDATE RED_Menu SET Label='".mysqli_real_escape_string($db->connection,$ThisSubSubLabel)."', MenuOrder='".mysqli_real_escape_string($db->connection,$_POST['SubSubMenuOrder'][$a][$c])."', Link = '".mysqli_real_escape_string($db->connection,$_POST['SubSubLabelLink'][$a][$c])."', NewWindow='".mysqli_real_escape_string($db->connection,$_POST['SubSubLabelNewWindow'][$a][$c])."' WHERE RecordID='".mysqli_real_escape_string($db->connection,$_POST['SubSubLabelRecordID'][$a][$c])."'"))
			$success = true;
		}
	}
	
	//if ($success)
	//echo 'yes';
	
	
	// 4. SAVE NEW SUBLABELS DATA.
	
	//echo 'NEW SECOND LEVEL'."\n";
	foreach (($_POST['NewSubLabel'] ?? []) as $a=>$b){
    // $a == $NewSubLabel Number
		foreach($b as $c=>$ThisNewSubLabel) {
		//$c == NewSubLabel Position in Array
			/*echo 'ThisNewSubLabel = '. mysqli_real_escape_string($db->connection,$ThisNewSubLabel). "\n ";
			echo 'NewSubMenuOrder= '.mysqli_real_escape_string($db->connection,$_POST['NewSubMenuOrder'][$a][$c]). "\n ";
			echo 'NewSubLabelLink = '.mysqli_real_escape_string($db->connection,$_POST['NewSubLabelLink'][$a][$c]). "\n ";
			echo 'NewSubLabelNewWindow = '.mysqli_real_escape_string($db->connection,$_POST['NewSubLabelNewWindow'][$a][$c]). "\n ";
			echo 'NewMainLabelRecordID ='.mysqli_real_escape_string($db->connection,$_POST['NewMainLabelRecordID'][$a][$c]). "\n \n ";*/
			if($ThisNewSubLabel <> '')
			//echo "INSERT INTO RED_Menu (RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, Active, Language) VALUES ('2', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Title'])."', '".mysqli_real_escape_string($db->connection,$ThisNewSubLabel)."', '".mysqli_real_escape_string($db->connection,$_POST['NewMainLabelRecordID'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelLink'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelNewWindow'][$a][$c])."', '".preg_replace ( "'<[^>]+>'U", "", $_POST['NewSubMenuOrder'][$a][$c])."', 'Y', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Language'])."')". "\n \n";
			if ($result = $db->insert("INSERT INTO RED_Menu (RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, Active, Language) VALUES ('2', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Title'])."', '".mysqli_real_escape_string($db->connection,$ThisNewSubLabel)."', '".mysqli_real_escape_string($db->connection,$_POST['NewMainLabelRecordID'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelLink'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelNewWindow'][$a][$c])."', '".preg_replace ( "'<[^>]+>'U", "", $_POST['NewSubMenuOrder'][$a][$c])."', 'Y', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Language'])."')"))
			$success = true;
		}
	}
	
	//if ($success)
	//echo 'yes';
	
	
	// 4. SAVE NEW SUBSUBLABELS DATA.
	
	//echo 'NEW THIRD LEVEL'."\n";
	foreach (($_POST['NewSubSubLabel'] ?? []) as $a=>$b){
    // $a == $NewSubLabel Number
		foreach($b as $c=>$ThisNewSubLabel) {
		//$c == NewSubLabel Position in Array
			/*echo 'ThisNewSubSubLabel = '. mysqli_real_escape_string($db->connection,$ThisNewSubSubLabel). "\n ";
			echo 'NewSubSubMenuOrder= '.mysqli_real_escape_string($db->connection,$_POST['NewSubSubMenuOrder'][$a][$c]). "\n ";
			echo 'NewSubSubLabelLink = '.mysqli_real_escape_string($db->connection,$_POST['NewSubSubLabelLink'][$a][$c]). "\n ";
			echo 'NewSubSubLabelNewWindow = '.mysqli_real_escape_string($db->connection,$_POST['NewSubSubLabelNewWindow'][$a][$c]). "\n ";
			echo 'NewSubLabelRecordID ='.mysqli_real_escape_string($db->connection,$_POST['NewSubLabelRecordID'][$a][$c]). "\n \n ";*/
			if($ThisNewSubLabel <> '')
			//echo "INSERT INTO RED_Menu (RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, Active, Language) VALUES ('3', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Title'])."', '".mysqli_real_escape_string($db->connection,$ThisNewSubLabel)."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelRecordID'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelLink'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelNewWindow'][$a][$c])."', '".preg_replace ( "'<[^>]+>'U", "", $_POST['NewSubMenuOrder'][$a][$c])."', 'Y', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Language'])."')". "\n \n";
			if ($result = $db->insert("INSERT INTO RED_Menu (RootOrder, Title, Label, Parent, Link, NewWindow, MenuOrder, Active, Language) VALUES ('3', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Title'])."', '".mysqli_real_escape_string($db->connection,$ThisNewSubLabel)."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubLabelRecordID'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubSubLabelLink'][$a][$c])."', '".mysqli_real_escape_string($db->connection,$_POST['NewSubSubLabelNewWindow'][$a][$c])."', '".preg_replace ( "'<[^>]+>'U", "", $_POST['NewSubSubMenuOrder'][$a][$c])."', 'Y', '".preg_replace ( "'<[^>]+>'U", "", $_POST['Language'])."')"))
			$success = true;
		}
	}
	
	if ($success)
	echo 'yes';
	
	$db->close();
}
?>
