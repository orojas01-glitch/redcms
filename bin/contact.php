<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
?>
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
		?>
	
	<html>
	<head>
	<style type="text/css">
	table.standard {font-family:Verdana, Geneva, sans-serif; font-size:14px; border-width: 0px;	border-spacing:0px;	border-style: solid; border-color:#cccccc;	border-collapse: collapse;	background-color: white;}
	table.standard th {	border-width: 0px;	padding:4px; border-style: inset; border-color: #cccccc; background-color: #F5F5F5;}
	table.standard td {	border-width: 0px;	padding: 4px;	border-style: inset; border-color: #cccccc;	background-color: white;}
	</style>
	</head>
	<body>
	<table width="100%" border="1" cellspacing="2" cellpadding="2" class="standard">
	<?php
	
	
	//
	while($row = mysqli_fetch_assoc($result))
	{
		$FromEmail=$row['Submitter'];
		$ToEmail=$row['Destinatary'];
		$CCEmail=$row['CC'];
		$BCCEmail=$row['BCC'];
		$SubjectEmail=$row['Subject'];
		$form=$row['LongDesc'];
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
						
						switch ($formarray[$row]['type'])
						{
						case 'textfield': /* textfield has 3 keys. */
						//echo 'textfield: '.$_POST[$formarray[$row]['name']]. '<br/>';
						echo '<tr><th>'.$formarray[$row]['name'].'*</th><td>' .preg_replace ( "'<[^>]+>'U", "", $_POST[$formarray[$row]['name']]). '</td></tr>';
						break;
						case 'textarea': /* textarea has 5 keys. */
						//echo 'textarea: '.$_POST[$formarray[$row]['name']]. '<br/>';
						echo '<tr><th>'.$formarray[$row]['name'].'*</th><td>' .preg_replace ( "'<[^>]+>'U", "", $_POST[$formarray[$row]['name']]). '</td></tr>';
						break;
						case 'checkbox': /* checkbox has 3 keys. */
						//echo 'checkbox: '.$_POST[$formarray[$row]['name']]. '<br/>';
						echo '<tr><th>'.$formarray[$row]['name'].'*</th><td>' .preg_replace ( "'<[^>]+>'U", "", $_POST[$formarray[$row]['name']]). '</td></tr>';
						break;
						case 'radio': /* radio has 4 keys. */
						//echo 'radio: '.$_POST[$formarray[$row]['name']]. '<br/>';
						echo '<tr><th>'.$formarray[$row]['name'].'*</th><td>' .preg_replace ( "'<[^>]+>'U", "", $_POST[$formarray[$row]['name']]). '</td></tr>';
						break;
						case 'select': /* select has 4 keys. */
						//echo 'select: '.$_POST[$formarray[$row]['name']]. '<br/>';
						echo '<tr><th>'.$formarray[$row]['name'].'*</th><td>' .preg_replace ( "'<[^>]+>'U", "", $_POST[$formarray[$row]['name']]). '</td></tr>';
						break;
						case 'hidden': /* select has 4 keys. */
						//echo 'select: '.$_POST[$formarray[$row]['name']]. '<br/>';
						echo '<tr><th>'.$formarray[$row]['name'].'*</th><td>' .preg_replace ( "'<[^>]+>'U", "", $_POST[$formarray[$row]['name']]). '</td></tr>';
						break;
						}
					} else
					echo '<tr><th>'.$formarray[$row]['name'].'</th><td>' .preg_replace ( "'<[^>]+>'U", "", $_POST[$formarray[$row]['name']]). '</td></tr>';
				break;
				}
			//}
		}

		
	}
	echo '<tr><th>IP</th><td>'.getRealIpAddr().'</td></tr>';
	echo '<tr><th>Country</th><td>'.getlocation(getRealIpAddr()).'</td></tr>';

	?>
	</table>
	<?php //echo round(abs($to_time - $from_time) / 60,2) ?>
	</body>
	</html>
	<?
	unset($_SESSION['contact']);
	if ($send <> 'false'){
	$body = ob_get_contents();
	
	//$to = $ToEmail;
	//$email = $ToEmail;
	//$fromaddress = "orojas@redsphere.tv";
	//$fromname = "Online Contact";

	require("phpmailer.php");
	
	$mail = new PHPMailer();
	
	$from_emailname=explode(',',$FromEmail);
	$thisfrom=$from_emailname[0];
	$thisname=$from_emailname[1];
	//$mail->From     = "mail@redsphere.tv";
	//$mail->FromName = "Contact Form";
	$mail->From     = $thisfrom;
	$mail->FromName = $thisname;
	//$mail->AddAddress("another_address@example.com","Name 2");
	$ToEmails=explode(';',$ToEmail);
	foreach( $ToEmails as $email ){
	$email_name=explode(',',$email);
	$thisemail=$email_name[0];
	$thisname=$email_name[1];
	$mail->AddAddress($thisemail,$thisname);
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
	
	
	$mail->WordWrap = 50;
	$mail->IsHTML(true);
	
	$mail->Subject  =  $SubjectEmail;
	$mail->Body     =  $body;
	$mail->AltBody  =  "This is the text-only body";
	
	if(!$mail->Send()) {
		$recipient = 'orojas01@gmail.com';
		$subject = $SubjectEmail .' failed';
		$content = $body;	
	  mail($recipient, $subject, $content, "From: mail@redsphere.tv\r\nReply-To: $email\r\nX-Mailer: DT_formmail");
	  exit;
	}
	}
	}
	}
?>
