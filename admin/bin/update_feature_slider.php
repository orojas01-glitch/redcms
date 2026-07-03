<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
// FEATURE TEMPLATE:
// FIND AND REPLACE 'template' WITH THE unique feature var name.

if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {
	
	$VarFeatures=$_POST['VarFeatures'];
	$VarFeatures=preg_replace ( "'<[^>]+>'U", "", $VarFeatures);
	
	foreach ($_POST['RecordID'] as $a=>$b){		
		//echo 'a='.$a. "\n";
		//echo 'b='.$b. "\n";
		//echo  'select='.$_POST['sliderSelect'][$a]. "\n";
			
		$b = preg_replace ( "'<[^>]+>'U", "", $b);
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT ".$VarFeatures." FROM RED_Articles WHERE RecordID='".$b."'");
		if($result->num_rows > 0) 
		{
			$info = mysqli_fetch_assoc($result); 
			
			//echo 'features='.$info[$VarFeatures]. "\n\n";
			if ($info[$VarFeatures]!=''){
				//echo 'true'."\n\n";
				$sliderExists = preg_match('/slider/', $info[$VarFeatures]);
				if ($sliderExists){
					//echo 'slider exists'."\n\n";
					if ($_POST['sliderSelect'][$a]==='Y'){
					$QueryFeatures=$info[$VarFeatures];	
					
					
					}else {// got to remove the slider
					$QueryFeatures=str_replace ( ",slider", "", $info[$VarFeatures]);
					$QueryFeatures=str_replace ( "slider,", "", $QueryFeatures);
					$QueryFeatures=str_replace ( "slider", "", $QueryFeatures);
					//$QueryFeatures=$info[$VarFeatures];
					
					}
				}else{
					//echo 'slider dont exists. but there are features'."\n\n";
					if ($_POST['sliderSelect'][$a]==='Y'){
					$QueryFeatures=$info[$VarFeatures].',slider';
					
					} else{
					$QueryFeatures=$info[$VarFeatures];
					
					}
				}
			}else{
				//echo 'false'."\n\n";
				if ($_POST['sliderSelect'][$a]==='Y'){
				$QueryFeatures='slider';
				
				}else{
				$QueryFeatures='';
				
				}
			}
			$FeatureOrder=$_POST['FeatureOrder'][$a];
		}
		
		//echo "UPDATE RED_Articles SET ".$VarFeatures."='".$QueryFeatures."'  WHERE RecordID='".$b."'". "\n";
		if ($result = $db->update("UPDATE RED_Articles SET ".$VarFeatures."='".$QueryFeatures."',".$VarFeatures."_Order='".$FeatureOrder."' WHERE RecordID='".$b."'"))
		$success = true;
	
	$QueryFeatures='';
		
		
	}
		
	
	if ($success)
	echo 'yes';
	
	
	
	$db->close();
}
?>
