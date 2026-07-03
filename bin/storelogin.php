<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
if(empty($_SESSION['StoreLogin']))
	header('Location: http://'.BASE_URL.'');
	else {
		//echo $_SESSION['contact']. "\n";
		$to_time=strtotime(date("H:i:s"));
		$from_time=strtotime($_SESSION['contact']);
		//echo round(abs($to_time - $from_time) / 60,2). "\n";
		//if (round(abs($to_time - $from_time) / 60,2)>2.00)
		//echo 'valid';
		$StoreUser=preg_replace ( "'<[^>]+>'U", "", $_POST['email']);
		$RecordID=preg_replace ( "'<[^>]+>'U", "", $_POST['RecordID']);
		//GET THE FORM VARIABLES USING ALIAS
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_C_Form WHERE RecordID='".$RecordID."'");
		$result_counter = $result->num_rows;
		if ($result_counter === 0)
		header('Location: http://'.BASE_URL.'');
		else{
			while($row = mysqli_fetch_assoc($result))
			{
				
				$response=$row['Response'];
				$form=$row['LongDesc'];
				$TableName=$row['TableName'];
				//explode first dimension of the array to create an array of rows
			
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
					 
					 
					 //foreach($_POST as $key => $value)
					//{
						//switch($key)
						switch ($formarray[$row]['name'])
						{
							case 'MySpamTrap':
								if ($_POST[$formarray[$row]['name']]<>'')
								$send='false';
							break;
							default:
							
							if ($formarray[$row]['required']!='false'){
								switch ($formarray[$row]['name'])
								{
									case 'email':
										$email=strtolower(mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']]));
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
									break;
									case 'password':
										$password=mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']]);
									break;
									case 'id':
										$id=strtolower(mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']]));
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
									break;
									case 'price':
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
									break;
									case 'title':
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
									break;
								}
							}else{
								switch ($formarray[$row]['name'])
								{
									case 'id':
										$id=strtolower(mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']]));
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
									break;
									case 'price':
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
									break;
									case 'title':
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
									break;
								}
							}
						break;
						}
						
					//}
					
				}
		
				
			}
			
			unset($_SESSION['StoreLogin']);
			if ($send <> 'false'){
				$createuser=true;	
				//echo "SELECT full_name FROM ".$TableName." WHERE email='".$email."' AND password='".$password."'";			
				$result = $db->query("SELECT * FROM ".$TableName." WHERE email='".$email."' AND password='".$password."'");
				$result_counter = $result->num_rows;
				if ($result_counter>0){
					
					while($row = mysqli_fetch_assoc($result))
					{
						$full_name=$row['full_name'];
						$RecordID=$row['RecordID'];
					}
					$response=str_replace(('$full_name'), $full_name, $response);
					$_SESSION['StoreUser']=$RecordID;
				}else{
					$createuser=false;
					echo 'error';
				}
				if($createuser){
				//echo "INSERT INTO ".$TableName." (".$fieldsetnames.") VALUES (".$fieldsetvalues.")";
				
				if ($id){
					$response=str_replace(('$userid'), $RecordID, $response); // *important
					echo $response;
				}else{
					echo '';
					switch(language)
					{
					case 'en':
					echo 'Please wait...';
					break;
					case 'sp':
					echo 'Por favor espere...';
					break;
					}
					echo '<script>window.setTimeout(function(){window.location.href = "player";}, 2000);</script>';
					
				}
				}
			}
		

		
		}
	}
?>
