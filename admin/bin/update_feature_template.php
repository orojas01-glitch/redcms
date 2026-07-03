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
		//echo  'select='.$_POST['templateSelect'][$a]. "\n";
			
		$b = preg_replace ( "'<[^>]+>'U", "", $b);
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT ".$VarFeatures." FROM RED_Articles WHERE RecordID='".$b."'");
		if($result->num_rows > 0) 
		{
			$info = mysqli_fetch_assoc($result); 
			
			//echo 'features='.$info[$VarFeatures]. "\n\n";
			if ($info[$VarFeatures]!=''){
				//echo 'true'."\n\n";
				$templateExists = preg_match('/template/', $info[$VarFeatures]);
				if ($templateExists){
					//echo 'template exists'."\n\n";
					if ($_POST['templateSelect'][$a]==='Y'){
					$QueryFeatures=$info[$VarFeatures];	
					
					
					}else {// got to remove the template
					$QueryFeatures=str_replace ( ",template", "", $info[$VarFeatures]);
					$QueryFeatures=str_replace ( "template,", "", $QueryFeatures);
					$QueryFeatures=str_replace ( "template", "", $QueryFeatures);
					//$QueryFeatures=$info[$VarFeatures];
					
					}
				}else{
					//echo 'template dont exists. but there are features'."\n\n";
					if ($_POST['templateSelect'][$a]==='Y'){
					$QueryFeatures=$info[$VarFeatures].',template';
					
					} else{
					$QueryFeatures=$info[$VarFeatures];
					
					}
				}
			}else{
				//echo 'false'."\n\n";
				if ($_POST['templateSelect'][$a]==='Y'){
				$QueryFeatures='template';
				
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
