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
		//echo $_SESSION['StoreLogin']. "\n";
		$emailhtml ='<HTML><HEAD><TITLE>Roland Kalt</TITLE></HEAD>';
		$emailhtml = $emailhtml . '<style type="text/css">';
		$emailhtml = $emailhtml . 'table.standard {font-family:Verdana, Geneva, sans-serif; font-size:14px; border-width: 0px;	border-spacing:0px;	border-style: solid; border-color:#cccccc;	border-collapse: collapse;	background-color: white;}';
		$emailhtml = $emailhtml . 'table.standard th {	border-width: 0px;	padding:4px; border-style: inset; border-color: #cccccc; background-color: #F5F5F5;}';
		$emailhtml = $emailhtml . 'table.standard td {	border-width: 0px;	padding: 4px;	border-style: inset; border-color: #cccccc;	background-color: white;}';
		$emailhtml = $emailhtml . '</style>';
		$emailhtml = $emailhtml .'<table width="100%" border="1" cellspacing="2" cellpadding="2" class="standard">';
		
		$to_time=strtotime(date("H:i:s"));
		$from_time=strtotime($_SESSION['contact']);
		//echo round(abs($to_time - $from_time) / 60,2). "\n";
		//if (round(abs($to_time - $from_time) / 60,2)>2.00)
		//echo 'valid';
		$StoreUser=preg_replace ( "'<[^>]+>'U", "", $_POST['email']);
		$RecordID=preg_replace ( "'<[^>]+>'U", "", $_POST['RecordID']);
		$ClientRecordID=mt_rand();
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
									case 'full_name':
										$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'*</th><td>' .$_POST[$formarray[$row]['name']]. '</td></tr>';
										$full_name=strtolower(mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']]));
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
										if ($row===0){
											$fieldsetnames = $formarray[$row]['name'];
											$fieldsetvalues = "'".mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']])."'";
										}else{
											$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
											$fieldsetvalues = $fieldsetvalues . ", '" .mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']])."'";
										}
									break;
									case 'email':
										$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'*</th><td>' .$_POST[$formarray[$row]['name']]. '</td></tr>';
										$email=strtolower(mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']]));
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
										if ($row===0){
											$fieldsetnames = $formarray[$row]['name'];
											$fieldsetvalues = "'".strtolower(mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']]))."'";
										}else{
											$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
											$fieldsetvalues = $fieldsetvalues . ", '" .strtolower(mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']]))."'";
										}
									break;
									case 'password':
										$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'*</th><td>' .$_POST[$formarray[$row]['name']]. '</td></tr>';
										$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
										if ($row===0){
											$fieldsetnames = $formarray[$row]['name'];
											$fieldsetvalues = "'".mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']])."'";
										}else{
											$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
											$fieldsetvalues = $fieldsetvalues . ", '" .mysqli_real_escape_string($db->connection, $_POST[$formarray[$row]['name']])."'";
										}
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
				$result = $db->query("SELECT email FROM ".$TableName." WHERE email='".$email."'");
				$result_counter = $result->num_rows;
				if ($result_counter > 0){
					$createuser=false;
					echo 'error';
				}
				
				if($createuser){
					//echo "INSERT INTO ".$TableName." (".$fieldsetnames.") VALUES (".$fieldsetvalues.")";
					if ($result = $db->insert("INSERT INTO ".$TableName." (RecordID, ".$fieldsetnames.") VALUES (".$ClientRecordID.", ".$fieldsetvalues.")"))
					$db->close();
					$_SESSION['StoreUser']=$StoreUser;
					
					$emailhtml= $emailhtml.'</table></html>';
					require("phpmailer.php");
				
					$mail = new PHPMailer();
					
					
					$mail->From     = 'info@rolandkalt.com';
					$mail->FromName = 'Roland Kalt';
					$mail->AddAddress($email,$full_name);
					
					$mail->WordWrap = 50;
					$mail->IsHTML(true);
					switch(language)
					{
					case 'en':
					$mail->Subject  =  "Roland Kalt - Account Information";
					break;
					case 'sp':
					$mail->Subject  =  "Roland Kalt - Informacion de cuenta";
					break;
					}
					
					$mail->Body     =  $emailhtml;
					$mail->AltBody  =  "This is the text-only body";
					
					if(!$mail->Send()) {
						$recipient = 'redspheredevelopment@gmail.com';
						$subject = $mail->Subject .' failed';
						$content = $body;	
					  mail($recipient, $subject, $content, "From: mail@red-sphere.com\r\nReply-To: $email\r\nX-Mailer: DT_formmail");
					  exit;
					}
					
					if ($id){
					$response=str_replace(('$userid'), $ClientRecordID, $response); // *important
					echo $response;
					}else{
						switch(language)
						{
						case 'en':
						echo 'Your account was created.';
						break;
						case 'sp':
						echo 'Tu cuenta fué creada.';
						break;
						}
						
					}
					
					
				}
			}
		

		
		}
	}
?>
