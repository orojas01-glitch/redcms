<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
$pp_hostname = "www.paypal.com"; // Change to www.sandbox.paypal.com to test against sandbox
//$pp_hostname = "www.sandbox.paypal.com";

// read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-synch';
 
$tx_token = $_GET['tx'];
$auth_token = "4H3FhmyeeeGAhsOOru-of2ZHZpJWT9fF9ogyef3hUUedbsI9aL-eC6Prf6a";
$req .= "&tx=$tx_token&at=$auth_token";
 
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://$pp_hostname/cgi-bin/webscr");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
//set cacert.pem verisign certificate path in curl using 'CURLOPT_CAINFO' field here,
//if your server does not bundled with default verisign certificates.
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Host: $pp_hostname"));
$res = curl_exec($ch);
curl_close($ch);

if(!$res){
    //HTTP ERROR
}else{
     // parse the data
    $lines = explode("\n", $res);
    $keyarray = array();
    if (strcmp ($lines[0], "SUCCESS") == 0) {
        for ($i=1; $i<count($lines);$i++){
        list($key,$val) = explode("=", $lines[$i]);
        $keyarray[urldecode($key)] = urldecode($val);
		//echo $lines[$i]."<br/>";
    }
    // check the payment_status is Completed
    // check that txn_id has not been previously processed
    // check that receiver_email is your Primary PayPal email
    // check that payment_amount/payment_currency are correct
    // process payment
    $firstname = $keyarray['first_name'];
    $lastname = $keyarray['last_name'];
    $itemname = $keyarray['item_name'];
    $amount = $keyarray['payment_gross'];
	$custom = $keyarray['custom'];
	$txn_id = $keyarray['txn_id'];
	$payer_email = $keyarray['payer_email'];
	$outerARR = explode( ',', $custom );
	switch ($outerARR[0])
		{
		case 'store':
			$SaleRecordID=mt_rand();
		//echo "INSERT INTO RED_C_AudioStore_Purchases (RecordID,user_id,audio_id,price,confirmation) VALUES ('".$SaleRecordID."','".$outerARR[2]."','".$outerARR[1]."','".$amount."','".$txn_id."')";
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			if ($result = $db->insert("INSERT INTO RED_C_AudioStore_Purchases (RecordID,user_id,audio_id,price,confirmation) VALUES ('".$SaleRecordID."','".$outerARR[2]."','".$outerARR[1]."','".$amount."','".$txn_id."')"));
				//echo 'true';
			if ($result = $db->update("UPDATE RED_C_AudioStore_Users SET audiolist = CONCAT_WS(',',audiolist,'$outerARR[1]') WHERE RecordID='".$outerARR[2]."'"));
			$db->close();
			
			$emailhtml ='<HTML><HEAD><TITLE>Roland Kalt</TITLE></HEAD>';
			$emailhtml = $emailhtml . '<style type="text/css">';
			$emailhtml = $emailhtml . 'table.standard {font-family:Verdana, Geneva, sans-serif; font-size:14px; border-width: 0px;	border-spacing:0px;	border-style: solid; border-color:#cccccc;	border-collapse: collapse;	background-color: white;}';
			$emailhtml = $emailhtml . 'table.standard th {	border-width: 0px;	padding:4px; border-style: inset; border-color: #cccccc; background-color: #F5F5F5;}';
			$emailhtml = $emailhtml . 'table.standard td {	border-width: 0px;	padding: 4px;	border-style: inset; border-color: #cccccc;	background-color: white;}';
			$emailhtml = $emailhtml . '</style>';
			$emailhtml = $emailhtml .'<table width="100%" border="1" cellspacing="2" cellpadding="2" class="standard">';
			
			$emailhtml= $emailhtml. '<tr><th>Item:</th><td>'.$itemname.'</td></tr>';
			$emailhtml= $emailhtml. '<tr><th>Amount:</th><td>'.$amount.'</td></tr>';
			$emailhtml= $emailhtml. '<tr><th>Confirmation #:</th><td>'.$txn_id.'</td></tr>';
			
			$emailhtml= $emailhtml.'</table></html>';
			
			require("phpmailer.php");
		
			$mail = new PHPMailer();
			
			
			$mail->From     = 'info@rolandkalt.com';
			$mail->FromName = 'Roland Kalt';
			$mail->AddAddress($payer_email,$firstname.' '.$lastname);
			$mail->AddBCC('redspheredevelopment@gmail.com','debug');
			
			$mail->WordWrap = 50;
			$mail->IsHTML(true);
			
			switch(language)
			{
			case 'en':
			$mail->Subject  =  "Roland Kalt - Payment Confirmation";
			break;
			case 'sp':
			$mail->Subject  =  "Roland Kalt - Confirmacion de pago";
			break;
			}
			
			$mail->Body     =  $emailhtml;
			$mail->AltBody  =  "This is the text-only body";
			
			if(!$mail->Send()) {
				$recipient = 'redspheredevelopment@gmail.com';
				$subject = $mail->Subject .' failed';
				$content = $body;	
			  mail($recipient, $subject, $content, "From: mail@red-sphere.com\r\nReply-To: $email\r\nX-Mailer: DT_formmail");
			  //exit;
			}
			
			switch(language)
			{
			case 'en':
			header('Location: http://'.BASE_URL.'/store/player');
			break;
			case 'sp':
			header('Location: http://'.BASE_URL.'/tienda/player');
			break;
			}
		break;
		
		default:
			$emailhtml ='<HTML><HEAD><TITLE>Roland Kalt</TITLE></HEAD>';
			$emailhtml = $emailhtml . '<style type="text/css">';
			$emailhtml = $emailhtml . 'table.standard {font-family:Verdana, Geneva, sans-serif; font-size:14px; border-width: 0px;	border-spacing:0px;	border-style: solid; border-color:#cccccc;	border-collapse: collapse;	background-color: white;}';
			$emailhtml = $emailhtml . 'table.standard th {	border-width: 0px;	padding:4px; border-style: inset; border-color: #cccccc; background-color: #F5F5F5;}';
			$emailhtml = $emailhtml . 'table.standard td {	border-width: 0px;	padding: 4px;	border-style: inset; border-color: #cccccc;	background-color: white;}';
			$emailhtml = $emailhtml . '</style>';
			$emailhtml = $emailhtml .'<table width="100%" border="1" cellspacing="2" cellpadding="2" class="standard">';
			
			$emailhtml= $emailhtml. '<tr><th>Item:</th><td>'.$itemname.'</td></tr>';
			$emailhtml= $emailhtml. '<tr><th>Amount:</th><td>'.$amount.'</td></tr>';
			$emailhtml= $emailhtml. '<tr><th>Confirmation #:</th><td>'.$txn_id.'</td></tr>';
			
			$emailhtml= $emailhtml.'</table></html>';
			
			require("phpmailer.php");
		
			$mail = new PHPMailer();
			
			
			$mail->From     = 'info@rolandkalt.com';
			$mail->FromName = 'Roland Kalt';
			$mail->AddAddress($payer_email,$firstname.' '.$lastname);
			$mail->AddBCC('redspheredevelopment@gmail.com','debug');
			
			$mail->WordWrap = 50;
			$mail->IsHTML(true);
			
			switch(language)
			{
			case 'en':
			$mail->Subject  =  "Roland Kalt - Payment Confirmation";
			break;
			case 'sp':
			$mail->Subject  =  "Roland Kalt - Confirmacion de pago";
			break;
			}
			
			$mail->Body     =  $emailhtml;
			$mail->AltBody  =  "This is the text-only body";
			
			if(!$mail->Send()) {
				$recipient = 'redspheredevelopment@gmail.com';
				$subject = $mail->Subject .' failed';
				$content = $body;	
			  mail($recipient, $subject, $content, "From: mail@red-sphere.com\r\nReply-To: $email\r\nX-Mailer: DT_formmail");
			  //exit;
			}
			
			
			header('Location: http://'.BASE_URL.'/');
			
		break;
		}
     
			/* echo ("<p><h3>Thank you for your purchase!</h3></p>");
			
			echo ("<b>Payment Details</b><br>\n");
			echo ("<li>Name: $firstname $lastname</li>\n");
			echo ("<li> </li>\n");
			echo ("<li> </li>\n");
			echo ("<li>Custom: $custom</li>\n");
			echo ("<li> </li>\n");
			echo ("<li>custom parts type: $outerARR[0]</li>\n");
			echo ("<li>custom parts user id: $outerARR[2]</li>\n");
			echo ("<li>custom parts audio id: $outerARR[1]</li>\n");*/
			
			
			


    }
    else if (strcmp ($lines[0], "FAIL") == 0) {
        // log for manual investigation
    }
}
 
?>