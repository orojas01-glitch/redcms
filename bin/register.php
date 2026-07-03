<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
if(empty($_SESSION['contact']))
	header('Location: http://'.BASE_URL.'');
	else {
		//echo $_SESSION['contact']. "\n";
		$to_time=strtotime(date("H:i:s"));
		$from_time=strtotime($_SESSION['contact']);
		//echo round(abs($to_time - $from_time) / 60,2). "\n";
		//if (round(abs($to_time - $from_time) / 60,2)>2.00)
		//echo 'valid';
		
		//GET THE FORM VARIABLES USING ALIAS
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$RecordID=preg_replace ( "'<[^>]+>'U", "", $_POST['RecordID']);
		$RecordID=mysqli_real_escape_string($db->connection,$RecordID);
		$result = $db->query("SELECT * FROM RED_C_Form WHERE RecordID='".$RecordID."'");
		$result_counter = $result->num_rows;
		if ($result_counter === 0)
		header('Location: http://'.BASE_URL.'');
		else{
			ob_start();
			
			$emailhtml ='<HTML><HEAD><TITLE>'.BASE_URL.'</TITLE></HEAD>';
			$emailhtml = $emailhtml . '<style type="text/css">';
			$emailhtml = $emailhtml . 'table.standard {font-family:Verdana, Geneva, sans-serif; font-size:14px; border-width: 0px;	border-spacing:0px;	border-style: solid; border-color:#cccccc;	border-collapse: collapse;	background-color: white;}';
			$emailhtml = $emailhtml . 'table.standard th {	border-width: 0px;	padding:4px; border-style: inset; border-color: #cccccc; background-color: #F5F5F5;}';
			$emailhtml = $emailhtml . 'table.standard td {	border-width: 0px;	padding: 4px;	border-style: inset; border-color: #cccccc;	background-color: white;}';
			$emailhtml = $emailhtml . '</style>';
			$emailhtml = $emailhtml .'<table width="100%" border="1" cellspacing="2" cellpadding="2" class="standard">';
			//
			while($row = mysqli_fetch_assoc($result))
			{
				$FromEmail=$row['Submitter'];
				$ToEmail=$row['Destinatary'];
				$CCEmail=$row['CC'];
				$BCCEmail=$row['BCC'];
				$SubjectEmail=$row['Subject'];
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
					  $tmp = explode('=', $val);
                        if (isset($tmp[0]) && isset($tmp[1])) {
                            $finalArray[$tmp[0]] = $tmp[1];
                        }
					 
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
								switch ($formarray[$row]['type'])
								{
								case 'textfield': /* textfield has 3 keys. */
								//echo 'textfield: '.$_POST[$formarray[$row]['name']]. '<br/>';
								$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'*</th><td>' .$_POST[$formarray[$row]['name']]. '</td></tr>';
								$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
								$SubjectEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $SubjectEmail);
								$ToEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $ToEmail);
								$FromEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $FromEmail);
								
								if ($row===0){
								$queryset = $formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $formarray[$row]['name'];
								$fieldsetvalues = "'".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}else{
								$queryset = $queryset . ", ".$formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
								$fieldsetvalues = $fieldsetvalues . ", '" .mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}
								
								break;
								case 'textarea': /* textarea has 5 keys. */
								//echo 'textarea: '.$_POST[$formarray[$row]['name']]. '<br/>';
								$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'*</th><td>' .$_POST[$formarray[$row]['name']]. '</td></tr>';
								$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
								$SubjectEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $SubjectEmail);
								$ToEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $ToEmail);
								$FromEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $FromEmail);
								
								if ($row===0){
								$queryset = $formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $formarray[$row]['name'];
								$fieldsetvalues = "'".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}else{
								$queryset = $queryset . ", ".$formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
								$fieldsetvalues = $fieldsetvalues . ", '" .mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}
								
								break;
								case 'checkbox': /* checkbox has 3 keys. */
								//echo 'checkbox: '.$_POST[$formarray[$row]['name']]. '<br/>';
								
								$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'*</th><td>' .$_POST[$formarray[$row]['name']]. '</td></tr>';
								$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
								$SubjectEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $SubjectEmail);
								$ToEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $ToEmail);
								$FromEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $FromEmail);
								
								if ($row===0){
								$queryset = $formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $formarray[$row]['name'];
								$fieldsetvalues = "'".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}else{
								$queryset = $queryset . ", ".$formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
								$fieldsetvalues = $fieldsetvalues . ", '" .mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}
								
								break;
								case 'radio': /* radio has 4 keys. */
								//echo 'radio: '.$_POST[$formarray[$row]['name']]. '<br/>';
								$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'*</th><td>' .$_POST[$formarray[$row]['name']]. '</td></tr>';
								$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
								$SubjectEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $SubjectEmail);
								$ToEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $ToEmail);
								$FromEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $FromEmail);
								
								if ($row===0){
								$queryset = $formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $formarray[$row]['name'];
								$fieldsetvalues = "'".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}else{
								$queryset = $queryset . ", ".$formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
								$fieldsetvalues = $fieldsetvalues . ", '" .mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}
								
								break;
								case 'select': /* select has 4 keys. */
								//echo 'select: '.$_POST[$formarray[$row]['name']]. '<br/>';
								$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'*</th><td>' .$_POST[$formarray[$row]['name']]. '</td></tr>';
								$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
								$SubjectEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $SubjectEmail);
								$ToEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $ToEmail);
								$FromEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $FromEmail);
								
								if ($row===0){
								$queryset = $formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $formarray[$row]['name'];
								$fieldsetvalues = "'".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}else{
								$queryset = $queryset . ", ".$formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
								$fieldsetvalues = $fieldsetvalues . ", '" .mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}
								
								break;
								
								case 'hidden': /* button 1 key */
								$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'*</th><td>' .$_POST[$formarray[$row]['name']]. '</td></tr>';
								$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
								$SubjectEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $SubjectEmail);
								$ToEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $ToEmail);
								$FromEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $FromEmail);
								
								if ($row===0){
								$queryset = $formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $formarray[$row]['name'];
								$fieldsetvalues = "'".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}else{
								$queryset = $queryset . ", ".$formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
								$fieldsetvalues = $fieldsetvalues . ", '" .mysqli_real_escape_string($db->connection,$_POST[$formarray[$row]['name']])."'";
								}
								break;
								}
							} else {
								if(isset($_POST[$formarray[$row]['name']])){
								  if (is_array($_POST[$formarray[$row]['name']])) {
									  $counterrow=0;
									foreach($_POST[$formarray[$row]['name']] as $value){
										if ($counterrow===0){
									 	$all_value= $value;
										} else{
										$all_value= $all_value.", ".$value;	
										}
										$counterrow++;
									}
								  } else {
									$all_value = $_POST[$formarray[$row]['name']];
									
								  }
								}
								$emailhtml= $emailhtml. '<tr><th>'.ucwords(preg_replace('/\_/',' ',$formarray[$row]['name'])).'</th><td>' .$all_value. '</td></tr>';
							
							if ($_POST[$formarray[$row]['name']]<>'')
							$response=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $response);
							$SubjectEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $SubjectEmail);
							//$ToEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $ToEmail);
							//$FromEmail=str_replace(('$'.$formarray[$row]['name']), $_POST[$formarray[$row]['name']], $FromEmail);
							
							if ($row===0){
								$queryset = $formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$all_value)."'";
								$fieldsetnames = $formarray[$row]['name'];
								$fieldsetvalues = "'".mysqli_real_escape_string($db->connection,$all_value)."'";
								}else{
								$queryset = $queryset . ", ".$formarray[$row]['name'] . "='".mysqli_real_escape_string($db->connection,$all_value)."'";
								$fieldsetnames = $fieldsetnames . ", ".$formarray[$row]['name'];
								$fieldsetvalues = $fieldsetvalues . ", '" .mysqli_real_escape_string($db->connection,$all_value)."'";
								}
							}
							$all_value="";
						break;
						}
						
					//}
					
				}
		
				
			}
			$emailhtml= $emailhtml.  '<tr><th>IP</th><td>'.getRealIpAddr().'</td></tr>';
			$emailhtml= $emailhtml.  '<tr><th>Ciudad, Pais</th><td>'.getlocation(getRealIpAddr()).'</td></tr>';
			$emailhtml= $emailhtml.'</table></html>';
			//$emailhtml= $emailhtml. round(abs($to_time - $from_time) / 60,2);
			
			unset($_SESSION['contact']);
			if ($send <> 'false'){
				//$body = ob_get_contents();
				$body=$emailhtml;
				
				//$to = $ToEmail;
				//$email = $ToEmail;
				//$fromaddress = "orojas@redsphere.tv";
				//$fromname = "Online Contact";
				//Import PHPMailer class into the global namespace
                			
                require 'Exception.php';
                require 'PHPMailer.php';
               
				//Create a new PHPMailer instance
                //$mail = new PHPMailer();
                $mail = new PHPMailer\PHPMailer\PHPMailer();
                //Send using SMTP to localhost (faster and safer than using mail()) – requires a local mail server
                //See other examples for how to use a remote server such as gmail
                
                $mail->Host = 'localhost';
                $mail->Port = 25;
				 
				
				$from_emailname=explode(',',$FromEmail);
				$thisfrom = trim($from_emailname[0]);
                $thisname = isset($from_emailname[1]) ? trim($from_emailname[1]) : '';
				//$mail->From     = "mail@redsphere.tv";
				//$mail->FromName = "Contact Form";
				//$mail->From     = $thisfrom;
                //$mail->FromName = $thisname;
                
                $mail->setFrom($thisfrom,$thisname); //IMPORTANT
                
				//$mail->AddAddress("another_address@example.com","Name 2");
				$ToEmails=explode(';',$ToEmail);
				foreach( $ToEmails as $email ){
				$email_name=explode(',',$email);
				$thisemail=$email_name[0];
				$thisname=$email_name[1];
                    
				$mail->AddAddress($thisemail,$thisname); //IMPORTANT
                    
				}
				//$mail->AddCC("orojas@red-sphere.com","Oscar Rojas");
				$CCEmails=explode(';',$CCEmail);
				foreach( $CCEmails as $email ){
				$email_name=explode(',',$email);
				$thisemail=$email_name[0];
				$thisname=$email_name[1];
				$mail->AddCC($thisemail,$thisname);
				}
				//$mail->AddBCC("orojas01@gmail.com","Oscar Rojas");
				$BCCEmails=explode(';',$BCCEmail);
				foreach( $BCCEmails as $email ){
				$email_name=explode(',',$email);
				$thisemail=$email_name[0];
				$thisname=$email_name[1];
				$mail->AddBCC($thisemail,$thisname);
				}
				
				
				//$mail->WordWrap = 50;
				$mail->IsHTML(true);
				$mail->Subject  =  $SubjectEmail;
				$mail->Body     =  $body;
				//$mail->AltBody  =  "This is the text-only body";
				
				if(!$mail->Send()) 
					echo "Tuvimos problemas al enviar. Por favor contáctame via WhatsApp.";
				 else 
                    echo $response;
    
			
				
				//echo "INSERT INTO ".$TableName." (".$fieldsetnames.") VALUES (".$fieldsetvalues.")";
				if ($result = $db->insert("INSERT INTO ".$TableName." (".$fieldsetnames.") VALUES (".$fieldsetvalues.")"))
					echo '';
				else
					echo '';
				$db->close();
			}
		

		
		}
	}
?>
