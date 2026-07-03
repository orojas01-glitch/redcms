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

#[\AllowDynamicProperties]
class forms
/** forms function vars:


**/
{
	
	public function form($recordid)
	{	
		
		
		
		//echo $this->query;
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_C_Form WHERE RefID='".$recordid."'");
		
		//echo ('start'.$result->num_rows.'<br/>');
		$result_counter = $result->num_rows;
		//
		while($row = mysqli_fetch_assoc($result))
		{

			
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
				
				foreach ($innerArr as $val) {
    $tmp = explode('=', $val);
    if (isset($tmp[1])) {
        $finalArray[$tmp[0]] = $tmp[1];
    } else {
        // Handle the case where "=" is missing (e.g., assign a default value)
        $finalArray[$tmp[0]] = '';
    }
}
				//add the newly created array of columns to the output array as a new index
				$formarray[] = $finalArray;
				
			}
			
			//print_r($formarray);
			
			$AliasArt=$row['Alias'];
			$Alias=$row['Alias'];
			$Alias=preg_replace('/-/','_',$Alias);
			$RecordID=$row['RecordID'];
			$Title=$row['Title'];
			$FormType=$row['FormType'];
			//$TLink=$row['Link'];
			
			
			echo '<div class="wrapper indent-bot">';
			
			echo '<h2 class="marg">'.$Title.'</h2>';
			
			/**
			JAVASCRIPT CHECKFORM
			**/
			
			echo '<script language="JavaScript" type="text/javascript">'. "\n";
			echo '<!--' ."\n";
			
			echo 'function checkform_'.$Alias.' ('.$Alias.')'. "\n".'{' . "\n"; 
			echo '$(\'.error\').hide(); '. "\n";
			//echo '$(\'input.text-input\').css({backgroundColor:"#FFFFFF"});' . "\n"; 
			//echo '$(\'input.text-input\').focus(function(){' . "\n"; 
			//echo '$(this).css({border:"#C00 thin 1px"});' . "\n"; 
			//echo '});' . "\n"; 
			//echo '$(\'input.text-input\').blur(function(){' . "\n"; 
			//echo '$(this).css({border:"none"});' . "\n"; 
			//echo '});' . "\n"; 
			for ($row = 0; $row < count($formarray); $row++)
			{
				switch ($formarray[$row]['type'])
				{
				case 'textfield': /* textfield has 3 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						switch (strtolower($formarray[$row]['name']))
						{
							case 'email':
								echo 'var '.$formarray[$row]['name'].'='.$Alias.'.'.$formarray[$row]['name'].'.value'. "\n";
								echo 'var filter=/^[a-zA-Z]+([_\.-]?[a-zA-Z0-9]+)*@[a-zA-Z0-9]+([\.-]?[a-zA-Z0-9]+)*(\.[a-zA-Z]{2,4})+$/i'. "\n";
								echo 'if (filter.test('.$formarray[$row]['name'].')){}'. "\n";
								echo 'else{'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
								echo 'alert( "Campo obligatorio -> '.$formarray[$row]['displayname'].'." );'. "\n"; 
								echo $Alias.'.'.$formarray[$row]['name'].'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
							case 'telephone':
								echo 'var '.$formarray[$row]['name'].'='.$Alias.'.'.$formarray[$row]['name'].'.value'. "\n";
								echo 'var filter=/^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/'. "\n";
								echo 'if (filter.test('.$formarray[$row]['name'].')){}'. "\n";
								echo 'else{'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
								echo 'alert( "Campo obligatorio -> '.$formarray[$row]['displayname'].'." );'. "\n"; 
								echo $Alias.'.'.$formarray[$row]['name'].'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
							case 'fax':
								echo 'var '.$formarray[$row]['name'].'='.$Alias.'.'.$formarray[$row]['name'].'.value'. "\n";
								echo 'var filter=/^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/'. "\n";
								echo 'if (filter.test('.$formarray[$row]['name'].')){}'. "\n";
								echo 'else{'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
								echo 'alert( "Campo obligatorio -> '.$formarray[$row]['displayname'].'." );'. "\n"; 
								echo $Alias.'.'.$formarray[$row]['name'].'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
							default:
								echo 'var '.$formarray[$row]['name'].'='.$Alias.'.'.$formarray[$row]['name'].'.value'. "\n";
								echo 'if ('.$formarray[$row]['name'].' == "") {'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
								echo 'alert( "Campo obligatorio -> '.$formarray[$row]['displayname'].'." );'. "\n"; 
								echo $Alias.'.'.$formarray[$row]['name'].'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
						}
					}
				break;
				
				case 'password': /* password has 3 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'var '.$formarray[$row]['name'].'='.$Alias.'.'.$formarray[$row]['name'].'.value'. "\n";
						echo 'if ('.$formarray[$row]['name'].' == "") {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
						echo 'alert( "Campo obligatorio -> '.$formarray[$row]['displayname'].'." );'. "\n"; 
						echo $Alias.'.'.$formarray[$row]['name'].'.focus();'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
					}
				break;
				
				case 'textarea': /* textarea has 5 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'var '.$formarray[$row]['name'].'='.$Alias.'.'.$formarray[$row]['name'].'.value'. "\n";
						echo 'if ('.$formarray[$row]['name'].' == "") {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
						echo 'alert( "Campo obligatorio -> '.$formarray[$row]['displayname'].'." );'. "\n"; 
						echo $Alias.'.'.$formarray[$row]['name'].'.focus();'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
					}
				break;
				
				case 'checkbox': /* checkbox has 3 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'var '.$formarray[$row]['name'].'='.$Alias.'.'.$formarray[$row]['name'].'.checked'. "\n";
						echo 'if ('.$formarray[$row]['name'].' == 0) {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
						echo 'alert( "Campo obligatorio -> '.$formarray[$row]['displayname'].'." );'. "\n"; 
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
					}
				break;
				
				case 'radio': /* radio has 4 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'var Radio_Order = false;'. "\n";
						echo 'for (counter = 0; counter < '.$Alias.'.'.$formarray[$row]['name'].'.length; counter++)'. "\n";
						echo '{'. "\n";
						
						echo 'if ('.$Alias.'.'.$formarray[$row]['name'].'[counter].checked)'. "\n";
						echo '{'. "\n";
						echo 'var Order = '.$Alias.'.'.$formarray[$row]['name'].'[counter].value;'. "\n";
						echo 'Radio_Order = true;'. "\n";
						echo '}'. "\n";
						echo '}'. "\n";
						echo 'if (!Radio_Order) {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
						echo 'alert( "Campo obligatorio -> '.$formarray[$row]['displayname'].'." )'. "\n"; 
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
						
					}
				break;
				
				case 'select': /* select has 4 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'if ('.$Alias.'.'.$formarray[$row]['name'].'.selectedIndex == 0) {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
						echo 'alert( "Campo obligatorio -> '.$formarray[$row]['displayname'].'." );'. "\n"; 
						echo $Alias.'.'.$formarray[$row]['name'].'.focus();'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
						
					}
				break;
				
				case 'hidden': /* select has 4 keys. */
					/*echo '// ** START **' . "\n";
					echo 'var '.$formarray[$row]['name'].'='.$Alias.'.'.$formarray[$row]['name'].'.value'. "\n";
					echo 'if ('.$formarray[$row]['name'].' == "") {'. "\n";
					//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
					echo 'alert( "Error. Please email Webmaster." );'. "\n"; 
					echo 'return false ;' . "\n";
					echo ' }' . "\n";*/
				break;
				}
			}
			
			/**
			JAVASCRIPT CHECKFORM - ACTION DEPENDS ON FORM STYLE.
			**/
			
			switch($FormType)
			{
				case 'Contact':
					//response messages by language
					switch(language)
					{
					case 'en':
					$responsemessagesuccess="Submitted!";
					break;
					case 'sp':
					$responsemessagesuccess="Enviado!";
					break;
					}
				
					$_SESSION['contact']=date("H:i:s");
					echo 'var dataString = ';
					for ($row = 0; $row < count($formarray); $row++)
					{
						switch ($formarray[$row]['type'])
						{
						case 'checkbox':
						if ($row==0)
						echo '\''.$formarray[$row]['name'].'=\' + '.$Alias.'.'.$formarray[$row]['name'].'.checked';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + '.$Alias.'.'.$formarray[$row]['name'].'.checked';
						break;
						
						case 'radio':
						if ($row==0)
						//echo '$(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val();'; 
						echo '\''.$formarray[$row]['name'].'=\' + $(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val()';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + $(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val()';
						break;
						
						case 'button':
						break;
						
						default:
						if ($row==0)
						echo '\''.$formarray[$row]['name'].'=\' + '.$Alias.'.'.$formarray[$row]['name'].'.value';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + '.$Alias.'.'.$formarray[$row]['name'].'.value';
						break;
						}
					}
					echo '+ \'&alias=\' + '.$Alias.'.alias.value + \'&MySpamTrap=\' + '.$Alias.'.MySpamTrap.value';
					echo ';'. "\n";
					
					//echo 'alert (dataString);'. "\n";
					//echo 'return false;'. "\n";
					
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/bin/contact.php", '. "\n";
					echo 'data: $("#'.$Alias.'").serialize(),'. "\n";
					//echo 'data: dataString, '. "\n";
					echo 'success: function(data) { '. "\n";
					//echo 'alert (data);'. "\n";
					//echo 'return false;'. "\n";
					echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#message_'.$Alias.'\').html("<h6><img id=\'checkmark\' src=\'/images/check.png\' align=\'left\' />'.$responsemessagesuccess.'</h6>")'. "\n";
					//echo '.append("<p>'.$responsemessagecontact.'</p>")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '//-->'. "\n";
					echo '</script>';
				
				break;
				
				case 'Login':
				
					//response messages by language
					switch(language)
					{
					case 'en':
					$responsemessagesuccess1="Success!";
					$responsemessagesuccess2="Loading Components...";
					$responsemessageerror1="Error.";
					$responsemessageerror2="Please try again.";
					break;
					case 'sp':
					$responsemessagesuccess1="Exito!";
					$responsemessagesuccess2="Cargando componentes...";
					$responsemessageerror1="Error.";
					$responsemessageerror2="Por favor intente de nuevo.";
					break;
					}
					
					echo 'var dataString = ';
					for ($row = 0; $row < count($formarray); $row++)
					{
						switch ($formarray[$row]['type'])
						{
						case 'checkbox':
						if ($row==0)
						echo '\''.$formarray[$row]['name'].'=\' + '.$Alias.'.'.$formarray[$row]['name'].'.checked';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + '.$Alias.'.'.$formarray[$row]['name'].'.checked';
						break;
						
						case 'radio':
						if ($row==0)
						//echo '$(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val();'; 
						echo '\''.$formarray[$row]['name'].'=\' + $(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val()';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + $(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val()';
						break;
						
						case 'button':
						break;
						
						default:
						if ($row==0)
						echo '\''.$formarray[$row]['name'].'=\' + '.$Alias.'.'.$formarray[$row]['name'].'.value';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + '.$Alias.'.'.$formarray[$row]['name'].'.value';
						break;
						}
					}
					echo '+ \'&alias=\' + '.$Alias.'.alias.value + \'&MySpamTrap=\' + '.$Alias.'.MySpamTrap.value';
					echo ';'. "\n";
					
					/*echo 'alert (dataString);'. "\n";
					echo 'return false;'. "\n";*/
					
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/bin/login.php", '. "\n";
					echo 'data: dataString, '. "\n";
					echo 'success: function(data) { '. "\n";
					//echo 'alert (data);'. "\n";
					echo 'if (data==\'yes\')'. "\n"; 
					echo '{'. "\n";
					echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#message_'.$Alias.'\').html("<h6><img id=\'checkmark\' src=\'/images/check.png\' align=\'left\' />'.$responsemessagesuccess1.'</h6>")'. "\n";
					echo '.append("<p>'.$responsemessagesuccess2.'</p>")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					echo 'document.location=\''.$_SERVER['REQUEST_URI'].'\';'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo 'else'. "\n"; 
					echo '{'. "\n";
					echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#message_'.$Alias.'\').html("<h6><img id=\'checkmark\' src=\'/images/icon-error.png\' align=\'left\' />'.$responsemessageerror1.'</h6>")'. "\n";
					echo '.append("<p>'.$responsemessageerror2.'</p>")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					echo 'document.location=\''.$_SERVER['REQUEST_URI'].'\';'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo '}'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '//-->'. "\n";
					echo '</script>';
				
				
				break;
				
				case 'Response':
					//response messages by language
					switch(language)
					{
					case 'en':
					$responsemessagesuccess="Success!";
					break;
					case 'sp':
					$responsemessagesuccess="Exito!";
					break;
					}
					
					$_SESSION['contact']=date("H:i:s");
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/bin/response.php", '. "\n";
					echo 'data: $("#'.$Alias.'").serialize(),'. "\n";
					//echo 'data: dataString, '. "\n";
					echo 'success: function(data) { '. "\n";
					//echo 'alert (data);'. "\n";
					//echo 'return false;'. "\n";
					echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#message_'.$Alias.'\').html("<h6><img id=\'checkmark\' src=\'/images/check.png\' align=\'left\' />'.$responsemessagesuccess.'</h6>")'. "\n";
					echo '.append(data)'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '//-->'. "\n";
					echo '</script>';
					
					
				break;
				
				case 'Register':
					//response messages by language
					switch(language)
					{
					case 'en':
					$responsemessagesuccess="Success!";
					break;
					case 'sp':
					$responsemessagesuccess="Exito!";
					break;
					}					
					$_SESSION['contact']=date("H:i:s");
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/bin/register.php", '. "\n";
					echo 'data: $("#'.$Alias.'").serialize(),'. "\n";
					//echo 'data: dataString, '. "\n";
					echo 'success: function(data) { '. "\n";
					//echo 'alert (data);'. "\n";
					//echo 'return false;'. "\n";
					//echo 'ga(\'send\', \'event\', \'clases-canto\', \'submit\', \'canto\', 1);'."\n";
					echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#message_'.$Alias.'\').html("<h6><img id=\'checkmark\' src=\'/images/check.png\' align=\'left\' />'.$responsemessagesuccess.'</h6>")'. "\n";
					echo '.append(data)'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '//-->'. "\n";
					echo '</script>';
					
					
				break;
				
				case 'Register_StoreLogin':
					//response messages by language
					
					switch(language)
					{
					case 'en':
					$responsemessagesuccess="Success!";
					$error="Error. This email is register already.";
					echo 'if (confirm ("You are being redirected to PayPal to process your payment. Please DO NOT close your browser.  If you opt to pay using a PayPal account you will be redirected automatically after 10 seconds to your player area. If you opt to pay with a Credit Card you will have to click on \'Return to Roland Kalt\'. \n\nTo continue click Ok. \nIf you are not sure click Cancel and read the instructions.")){;'. "\n";
					break;
					case 'sp':
					$responsemessagesuccess="Exito!";
					$error="Error. Este email ya está registrado.";
					echo 'if (confirm ("Usted será redireccionado a Paypal para procesar su pago. Por favor NO cierre el navegador. Si usted selecciona pagos con su cuenta de Paypal entonces será redireccionado a su area de audio automáticamente despues de 10 segundos. Si selecciona pagar con Tarjeta de Crédito tendrá que hacer clic en \'Return to Roland Kalt\'.\n\nPara continuar clic en Ok. \nSi tiene dudas haga clic en Cancel y lea las instrucciones.")){;'. "\n";
					break;
					}
					$_SESSION['StoreLogin']=date("H:i:s");
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/bin/register_storelogin.php", '. "\n";
					echo 'data: $("#'.$Alias.'").serialize(),'. "\n";
					//echo 'data: dataString, '. "\n";
					echo 'success: function(data) { '. "\n";
					/*echo 'alert (data);'. "\n";
					echo 'return false;'. "\n";*/
					
					echo 'if (data==\'error\')'. "\n"; 
					echo '{'. "\n";
					echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#message_'.$Alias.'\').html("<h6><img id=\'checkmark\' src=\'/images/icon-error.png\' align=\'left\' />'.$error.'</h6>")'. "\n";
					//echo '.append("<p>Please try again.</p>")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					echo 'document.location=\''.$_SERVER['HTTP_REFERER'].'\';'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo 'else'. "\n"; 
					echo '{'. "\n";
					
					echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#message_'.$Alias.'\').html("<h6><img id=\'checkmark\' src=\'/images/check.png\' align=\'left\' />'.$responsemessagesuccess.'</h6>")'. "\n";
					echo '.append(data)'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					
					echo '}'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '}'. "\n";
					echo '//-->'. "\n";
					echo '</script>';
										
					
				break;
				
				case 'StoreLogin':
					switch(language)
					{
					case 'en':
					$responsemessagesuccess="Success!";
					$error="Error. Please try again.";
					break;
					case 'sp':
					$responsemessagesuccess="Exito!";
					$error="Error. Por favor intente de nuevo.";
					break;
					}
					$_SESSION['StoreLogin']=date("H:i:s");
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/bin/storelogin.php", '. "\n";
					echo 'data: $("#'.$Alias.'").serialize(),'. "\n";
					//echo 'data: dataString, '. "\n";
					echo 'success: function(data) { '. "\n";
					/*echo 'alert (data);'. "\n";
					echo 'return false;'. "\n";*/
					echo 'if (data==\'error\')'. "\n"; 
					echo '{'. "\n";
					echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#message_'.$Alias.'\').html("<h6><img id=\'checkmark\' src=\'/images/icon-error.png\' align=\'left\' />'.$error.'</h6>")'. "\n";
					//echo '.append("<p>Please try again.</p>")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					echo 'document.location=\''.$_SERVER['REQUEST_URI'].'\';'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo 'else'. "\n"; 
					echo '{'. "\n";
					echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
					echo '$(\'#message_'.$Alias.'\').html("<h6><img id=\'checkmark\' src=\'/images/check.png\' align=\'left\' />'.$responsemessagesuccess.'</h6>")'. "\n";
					echo '.append(data)'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'\');'. "\n";
					
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo '}'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '//-->'. "\n";
					echo '</script>';
					
					
				break;
				
				default:
				
					echo 'return true;'. "\n";
					echo '}'. "\n";
					echo '//-->'. "\n";
					echo '</script>';
				
				break;
			}
			
			
			
			
			
			/**
			END JAVASCRIPT CHECKFORM
			**/
			
			
			/**
			START FORM
			**/
			
			
			echo '<div id="form_'.$Alias.'">';
			echo '<form id="'.$Alias.'" class="'.$Alias.'" name="'.$Alias.'" method="post" onSubmit="return checkform_'.$Alias.'(this);">';
			echo '<fieldset>';
			for ($row = 0; $row < count($formarray); $row++)
			{
				switch ($formarray[$row]['type'])
				{
				
				case 'textfield': /* textfield has 3 keys. */
					$question=$formarray[$row]['question'];
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = $formarray[$row]['displayname'];
					$initialvalue = $formarray[$row]['initialvalue'];
					$required = $formarray[$row]['required'];
					echo '<label for="'.$name.'" class="'.$name.'">'.$displayname;
					if ($formarray[$row]['required']!='false') echo '*';
					echo '</label><input type="text" name="'.$name.'" class="text-input" id="'.$name.'" value="'.$initialvalue.'" />';
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span><span class="clear"></span>';
					//foreach($formarray[$row] as $key => $value)
					//{
						//echo "<li>".$key.' - '.$value."</li>";
					//}
				break;
				
				case 'password': /* textfield has 3 keys. */
					$question=$formarray[$row]['question'];
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = $formarray[$row]['displayname'];
					$initialvalue = $formarray[$row]['initialvalue'];
					$required = $formarray[$row]['required'];
					echo '<label for="'.$name.'" class="'.$name.'">'.$displayname;
					if ($formarray[$row]['required']!='false') echo '*';
					echo '</label><input type="password" name="'.$name.'" class="text-input" id="'.$name.'" value="'.$initialvalue.'" />';
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span><span class="clear"></span>';
					//foreach($formarray[$row] as $key => $value)
					//{
						//echo "<li>".$key.' - '.$value."</li>";
					//}
				break;
				
				case 'textarea': /* textarea had 5 keys. */
					$question=$formarray[$row]['question'];
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = $formarray[$row]['displayname'];
					$initialvalue = $formarray[$row]['initialvalue'];
					$cols = $formarray[$row]['cols'];
					$rows = $formarray[$row]['rows'];
					$required = $formarray[$row]['required'];
					echo '<label for="'.$name.'" class="'.$name.'">'.$displayname;
					if ($formarray[$row]['required']!='false') echo '*';
					if ($formarray[$row]['readonly']!='false')
					echo '</label><textarea name="'.$name.'" class="text-input" id="'.$name.'" cols="'.$cols.'" rows="'.$rows.'" readonly>'.$initialvalue.'</textarea>';
					else
					echo '</label><textarea name="'.$name.'" class="text-input" id="'.$name.'" cols="'.$cols.'" rows="'.$rows.'">'.$initialvalue.'</textarea>';
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span><span class="clear"></span>';
				break;
			
			case 'checkbox':
					$question=$formarray[$row]['question'];
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = $formarray[$row]['displayname'];
					$required = $formarray[$row]['required'];
					echo '<label class="'.$name.'">'.$displayname;
					if ($formarray[$row]['required']!='false') echo '*';
					echo '</label>';
					
					$value = $formarray[$row]['value'];
					$value = explode(',',$value);
					$w = 0;
					foreach ($value as $thisvalue){
						$selected = explode('*',$thisvalue);
						if (count($selected)>1){
							$thisvalue=$selected[0];
							$thisrealvalue = explode('^',$thisvalue);
							if (count($thisrealvalue)>1){
							$thisvalue = $thisrealvalue[1];
							$thisvaluelabel = $thisrealvalue[0];
							}else{
							$thisvaluelabel = $thisvalue;	
							}
							
							echo '<label class="checkbox"><input type="checkbox" name="'.$name.'[]" value="'.$thisvalue.'" id="'.$name.'_'.$w.'" checked="checked" /> '.$thisvaluelabel.'</label>';
						}
						else{
							$thisrealvalue = explode('^',$thisvalue);
							if (count($thisrealvalue)>1){
							$thisvalue = $thisrealvalue[1];
							$thisvaluelabel = $thisrealvalue[0];
							}else{
							$thisvaluelabel = $thisvalue;
							}
							echo '<label class="checkbox"><input type="checkbox" name="'.$name.'" value="'.$thisvalue.'" id="'.$name.'_'.$w.'" /> '.$thisvaluelabel.'</label>';	
						}
						$w++;	
					}
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span><span class="clear"></span>';
			break;
			
			case 'radio':  /* radio has 4 keys. */
					$question=$formarray[$row]['question'];
					$required = $formarray[$row]['required'];
					if ($question <> '')
					echo '<p class="question">'.$question;
					
					echo '</p>';
					$name=$formarray[$row]['name'];
					$displayname = $formarray[$row]['displayname'];
					
					echo '<label class="'.$name.'">'.$displayname;
					if ($formarray[$row]['required']!='false') echo '*';
					echo '</label>';
					$value = $formarray[$row]['value'];
					$value = explode(',',$value);
					$w = 0;
					foreach ($value as $thisvalue){
						$selected = explode('|',$thisvalue);
						if (count($selected)>1){
							$thisvalue=$selected[0];
							$thisrealvalue = explode('^',$thisvalue);
							if (count($thisrealvalue)>1){
							$thisvalue = $thisrealvalue[1];
							$thisvaluelabel = $thisrealvalue[0];
							}else{
							$thisvaluelabel = $thisvalue;	
							}
							
							echo '<label class="radio"><input type="radio" name="'.$name.'" value="'.$thisvalue.'" id="'.$name.'_'.$w.'" checked="checked" />'.$thisvaluelabel.'';
						}
						else{
							$thisrealvalue = explode('^',$thisvalue);
							if (count($thisrealvalue)>1){
							$thisvalue = $thisrealvalue[1];
							$thisvaluelabel = $thisrealvalue[0];
							}else{
							$thisvaluelabel = $thisvalue;
							}
							echo '<label class="radio"><input type="radio" name="'.$name.'" value="'.$thisvalue.'" id="'.$name.'_'.$w.'" />'.$thisvaluelabel.'</label>';	
						}
						$w++;	
					}
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span><span class="clear"></span>';
					echo '</label>';
				break;
				
			case 'select': /* select has 4 keys. */
					$question=$formarray[$row]['question'];
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = $formarray[$row]['displayname'];
					$required = $formarray[$row]['required'];
					echo '<label for="'.$name.'" class="'.$name.'">'.$displayname;
					if ($formarray[$row]['required']!='false') echo '*';
					echo '</label>&nbsp;<select name="'.$name.'" class="text-input" id="'.$name.'">';
					$value = $formarray[$row]['value'];
					$value = explode(',',$value);
					foreach ($value as $thisvalue){
						$selected = explode('^',$thisvalue);
						if (count($selected)>1){
							$thisvalue=$selected[0];
							switch ($selected[1])
							{
								case 'disabled':
								echo '<option value="'.$thisvalue.'" disabled="disabled">'.$thisvalue.'</option>';
								break;
								case 'selected':
								echo '<option value="'.$thisvalue.'" selected="selected">'.$thisvalue.'</option>';
								break;
							}
							
						}
						else
							echo '<option value="'.$thisvalue.'">'.$thisvalue.'</option>';			
					}
					echo '</select>';
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span>';
				break;
			
			
			case 'hidden': /* button 1 key */
					$name=$formarray[$row]['name'];
					$initialvalue = $formarray[$row]['initialvalue'];
					if ($initialvalue==='referral')
					$initialvalue=str_replace('referral', referral, $initialvalue);
					echo '<input type="hidden" name="'.$name.'" value="'.$initialvalue.'" />';
				break;
				
			case 'button': /* button 1 key */
					$question=$formarray[$row]['question'];
					echo '<span class="clear"></span>';
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = $formarray[$row]['displayname'];
					echo '<div class="btns">';
					/*echo '<input type="reset" class="groovybutton" onClick="document.getElementById(\''.$Alias.'\').reset()" value="RESET" />&nbsp;';*/
					echo '<input type="submit" name="'.$name.'" class="button" value="'.$displayname.'"/>';
					
					echo '</div>';
				break;
				
			case 'paragraph':
					$paragraph=$formarray[$row]['paragraph'];
					echo '<p>'.$paragraph.'</p>';
				break;
				}
					
			}
			echo '</fieldset>';
			echo '<input type="hidden" name="alias" value="'.$AliasArt.'" />';
			echo '<input type="hidden" name="RecordID" value="'.$RecordID.'" />';
			echo '<textarea id="MySpamTrap" name="MySpamTrap" rows="3" cols="4"></textarea>';
			echo '</form>';
			echo '</div>';
			
			/**
			END FORM
			**/
		$result_counter = ($result_counter - 1);
		echo '</div>';
		}
	
	
	
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	
	
	public function cp_form($recordid, $VarFeatures, $VarPosition, $Table, $position, $layout)
	{	
		
		
		
		//echo $this->query;
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_C_Form WHERE RefID='".$recordid."'");
		
		//echo ('start'.$result->num_rows.'<br/>');
		$result_counter = $result->num_rows;
		//
		while($row = mysqli_fetch_assoc($result))
		{
			$RecordID=$row['RecordID'];
			$Alias=$row['Alias'];
			$Alias=preg_replace('/-/','_',$Alias);
			$Title=$row['Title'];
			
			/// COMPARE SESSION 'AdminComponents' WITH RED_COMPONENTS.
				// IF VALUE EXIST THEN SHOW UPDATE BUTTON. IF NOT, DISPLAY MESSAGE FOR "ADMIN NOT AUTHORIZED TO UPDATE".
				$AdminComponents = explode(",", $_SESSION['AdminComponents']);
				//echo($_SESSION['AdminComponents'].'='.count($AdminComponents.'<br/>'));
				for ($w=0; $w<=count($AdminComponents); $w++)
				{
				$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$resultC = $db->query("SELECT RecordID FROM RED_Components WHERE RecordID='".$AdminComponents[$w]."' AND UniqueName='Form'");
				//echo ($resultC->num_rows);
				if(($resultC->num_rows==0)&&($w==count($AdminComponents))){
					//echo 'ADMINISTRATOR NOT AUTHORIZED TO UPDATE';
					echo '<script type="text/javascript">'. "\n";
					echo '<!--' ."\n";
					echo 'function edit_content_'.$Alias.'_'.$RecordID.' (content_'.$Alias.'_'.$RecordID.')'. "\n".'{' . "\n"; 
					echo '$(\'#msggbox_alert_'.$position.'\').html("You\'re not authorized to edit this content.")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '-->'. "\n";
					echo '</script>';
					echo '<form id="content_'.$Alias.'_'.$RecordID.'" class="form" name="content_'.$Alias.'_'.$RecordID.'" method="post" onSubmit="return edit_content_'.$Alias.'_'.$RecordID.'(this);">';
					echo '<h7 id="cp"> '.$row['Title'].'</h7><br/><input type="submit" name="Edit" class="cp" id="cp_form" value="Edit Form"/>';
					echo '</form>';
				}elseif(($resultC->num_rows==0));
				else{
					//echo 'ADMINISTRATOR AUTHORIZED TO UPDATE';
					echo '<script type="text/javascript">'. "\n";
					echo '<!--' ."\n";
					echo 'function edit_forms_'.$Alias.'_'.$RecordID.' (forms_'.$Alias.'_'.$RecordID.')'. "\n".'{' . "\n"; 
					//echo 'alert (dataString);'. "\n";
					//echo 'return false;'. "\n";
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/admin/bin/edit_form.php", '. "\n";
					echo 'cache: false,'. "\n"; 
					//echo 'data: dataString, '. "\n";
					echo 'data: $("#forms_'.$Alias.'_'.$RecordID.'").serialize(), '. "\n";
					echo 'success: function(data) { '. "\n";
					//echo 'alert (data);'. "\n";
					echo 'if (data)'. "\n"; 
					echo '{'. "\n";
					//echo '$(\'#edit_content_grid\').html("<div id=\'message_'.$Alias.'_'.$RecordID.'\'></div>");'. "\n";
					echo '$(\'#edit_content_grid\').hide();'. "\n";
					//echo '$(\'#message_'.$Alias.'_'.$RecordID.'\').html("<h6>View All.</h6>")'. "\n";
					echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'_'.$RecordID.'\');'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo 'else'. "\n"; 
					echo '{'. "\n";
					//echo '$(\'#form_'.$Alias.'_'.$RecordID.'\').html("<div id=\'message_'.$Alias.'_'.$RecordID.'\'></div>");'. "\n";
					echo '$(\'#msggbox_edit_content\').html("error.")'. "\n";
					echo '.append("<p>Please try again.</p>")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'_'.$RecordID.'\');'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo '}'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '-->'. "\n";
					echo '</script>';
					echo '<form id="forms_'.$Alias.'_'.$RecordID.'" class="form" name="forms_'.$Alias.'_'.$RecordID.'" method="post" onSubmit="return edit_forms_'.$Alias.'_'.$RecordID.'(this);">';
					echo '<h7 id="cp"> '.$row['Title'].'</h7><br/><input type="submit" name="Edit" class="cp" id="cp_form" value="Edit Form"/>';
					echo '<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
					echo '<input type="hidden" name="ArtRecordID" id="RecordID" value="'.$recordid.'" />';
					echo '<input type="hidden" name="VarPosition" id="VarPosition" value="'.$VarPosition.'" />';
                    echo '<input type="hidden" name="Article" id="Article" value="'.article.'" />';
					echo '<input type="hidden" name="Layout" id="Layout" value="'.$layout.'" />';
					echo '</form>';
					//END "ADMIN AUTHORIZED TO UPDATE".
				break;
				}
				
				}
				//END COMPARE SESSION
				echo '<hr id="cp">';
			
			$result_counter = ($result_counter - 1);	
		}
	}
}
?>