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
require_once __DIR__ . '/../includes/public_render_helpers.php';
require_once __DIR__ . '/../includes/legacy_component_helpers.php';

#[\AllowDynamicProperties]
class forms
/** forms function vars:


**/
{
	
	public function form($recordid)
	{	
		$javascriptString = static function ($value) {
			$encoded = json_encode(
				(string) $value,
				JSON_HEX_TAG
				| JSON_HEX_AMP
				| JSON_HEX_APOS
				| JSON_HEX_QUOT
				| JSON_UNESCAPED_SLASHES
				| JSON_UNESCAPED_UNICODE
				| JSON_INVALID_UTF8_SUBSTITUTE
			);

			return is_string($encoded) ? $encoded : '""';
		};
		
		
		//echo $this->query;
		
		$context = red_legacy_public_form_context($recordid);
		$rows = $context['rows'];
		
		//echo ('start'.count($rows).'<br/>');
		$result_counter = count($rows);
		//
		foreach($rows as $preparedForm)
		{

			
			$formRecord = $preparedForm['record'];
			$formarray = $preparedForm['fields'];
			$normalizedFormArray = [];
			$namedFieldTypes = ['textfield', 'password', 'textarea', 'checkbox', 'radio', 'select', 'hidden', 'button'];
			$supportedFieldTypes = array_merge($namedFieldTypes, ['paragraph']);
			foreach ($formarray as $field) {
				if (!is_array($field)) {
					continue;
				}

				$field['type'] = strtolower(trim((string) ($field['type'] ?? '')));
				if (!in_array($field['type'], $supportedFieldTypes, true)) {
					continue;
				}

				if (
					in_array($field['type'], $namedFieldTypes, true)
					&& !preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D', (string) ($field['name'] ?? ''))
				) {
					continue;
				}

				$normalizedFormArray[] = $field;
			}
			$formarray = $normalizedFormArray;
			
			//print_r($formarray);
			
			$AliasArt=$preparedForm['alias']['raw'];
			$Alias=$preparedForm['alias']['javascript'];
			$RecordID=$formRecord['RecordID'];
			$Title=red_public_display_text($formRecord['Title']);
			$FormType=$preparedForm['action']['formType'];
			$FormActionEndpoint=$preparedForm['action']['endpoint'];
			$FormActionPayloadMode=$preparedForm['action']['payloadMode'];
			$FormActionEndpointJson=$javascriptString($FormActionEndpoint);
			$RequestUriJson=$javascriptString($_SERVER['REQUEST_URI'] ?? '/');
			//$TLink=$row['Link'];
			
			
			echo '<div class="wrapper indent-bot">';
			
			echo '<h2 class="marg">'.$Title.'</h2>';
			
			/**
			JAVASCRIPT CHECKFORM
			**/
			
			echo '<script language="JavaScript" type="text/javascript">'. "\n";
			echo '<!--' ."\n";
			
			echo 'function checkform_'.$Alias.' (formElement)'. "\n".'{' . "\n";
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
				$field = $formarray[$row];
				$fieldNameJson = $javascriptString($field['name'] ?? '');
				$fieldControlVariable = 'FieldControl_' . $row;
				$fieldValueVariable = 'FieldValue_' . $row;
				$validationAlertJson = $javascriptString(
					'Campo obligatorio -> ' . red_public_plain_text($field['displayname'] ?? '') . '.'
				);
				switch ($field['type'])
				{
				case 'textfield': /* textfield has 3 keys. */
					if (($field['required'] ?? 'false') != 'false'){
						echo '// ** START **' . "\n";
						switch (strtolower($field['name']))
						{
							case 'email':
								echo 'var '.$fieldControlVariable.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
								echo 'var '.$fieldValueVariable.'='.$fieldControlVariable.'.value;'. "\n";
								echo 'var filter=/^[a-zA-Z]+([_\.-]?[a-zA-Z0-9]+)*@[a-zA-Z0-9]+([\.-]?[a-zA-Z0-9]+)*(\.[a-zA-Z]{2,4})+$/i'. "\n";
								echo 'if (filter.test('.$fieldValueVariable.')){}'. "\n";
								echo 'else{'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
								echo 'alert('.$validationAlertJson.');'. "\n";
								echo $fieldControlVariable.'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
							case 'telephone':
								echo 'var '.$fieldControlVariable.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
								echo 'var '.$fieldValueVariable.'='.$fieldControlVariable.'.value;'. "\n";
								echo 'var filter=/^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/'. "\n";
								echo 'if (filter.test('.$fieldValueVariable.')){}'. "\n";
								echo 'else{'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
								echo 'alert('.$validationAlertJson.');'. "\n";
								echo $fieldControlVariable.'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
							case 'fax':
								echo 'var '.$fieldControlVariable.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
								echo 'var '.$fieldValueVariable.'='.$fieldControlVariable.'.value;'. "\n";
								echo 'var filter=/^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/'. "\n";
								echo 'if (filter.test('.$fieldValueVariable.')){}'. "\n";
								echo 'else{'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
								echo 'alert('.$validationAlertJson.');'. "\n";
								echo $fieldControlVariable.'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
							default:
								echo 'var '.$fieldControlVariable.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
								echo 'var '.$fieldValueVariable.'='.$fieldControlVariable.'.value;'. "\n";
								echo 'if ('.$fieldValueVariable.' == "") {'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
								echo 'alert('.$validationAlertJson.');'. "\n";
								echo $fieldControlVariable.'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
						}
					}
				break;
				
				case 'password': /* password has 3 keys. */
					if (($field['required'] ?? 'false') != 'false'){
						echo '// ** START **' . "\n";
						echo 'var '.$fieldControlVariable.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
						echo 'var '.$fieldValueVariable.'='.$fieldControlVariable.'.value;'. "\n";
						echo 'if ('.$fieldValueVariable.' == "") {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
						echo 'alert('.$validationAlertJson.');'. "\n";
						echo $fieldControlVariable.'.focus();'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
					}
				break;
				
				case 'textarea': /* textarea has 5 keys. */
					if (($field['required'] ?? 'false') != 'false'){
						echo '// ** START **' . "\n";
						echo 'var '.$fieldControlVariable.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
						echo 'var '.$fieldValueVariable.'='.$fieldControlVariable.'.value;'. "\n";
						echo 'if ('.$fieldValueVariable.' == "") {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
						echo 'alert('.$validationAlertJson.');'. "\n";
						echo $fieldControlVariable.'.focus();'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
					}
				break;
				
				case 'checkbox': /* checkbox has 3 keys. */
					if (($field['required'] ?? 'false') != 'false'){
						echo '// ** START **' . "\n";
						echo 'var Checkbox_'.$row.'=formElement.querySelectorAll(\'input[name="'.$field['name'].'[]"]:checked\').length'. "\n";
						echo 'if (Checkbox_'.$row.' === 0) {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
						echo 'alert('.$validationAlertJson.');'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
					}
				break;
				
				case 'radio': /* radio has 4 keys. */
					if (($field['required'] ?? 'false') != 'false'){
						echo '// ** START **' . "\n";
						echo 'var Radio_Order = false;'. "\n";
						echo 'var RadioFields_'.$row.'=formElement.querySelectorAll(\'input[type="radio"][name="'.$field['name'].'"]\');'. "\n";
						echo 'for (counter = 0; counter < RadioFields_'.$row.'.length; counter++)'. "\n";
						echo '{'. "\n";
						
						echo 'if (RadioFields_'.$row.'[counter].checked)'. "\n";
						echo '{'. "\n";
						echo 'var Order = RadioFields_'.$row.'[counter].value;'. "\n";
						echo 'Radio_Order = true;'. "\n";
						echo '}'. "\n";
						echo '}'. "\n";
						echo 'if (!Radio_Order) {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
						echo 'alert('.$validationAlertJson.');'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
						
					}
				break;
				
				case 'select': /* select has 4 keys. */
					if (($field['required'] ?? 'false') != 'false'){
						echo '// ** START **' . "\n";
						echo 'var '.$fieldControlVariable.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
						echo 'if ('.$fieldControlVariable.'.selectedIndex == 0) {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
						echo 'alert('.$validationAlertJson.');'. "\n";
						echo $fieldControlVariable.'.focus();'. "\n";
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
						$field = $formarray[$row];
						$fieldName = (string) ($field['name'] ?? '');
						$fieldNameJson = $javascriptString($fieldName);
						switch ($field['type'])
						{
						case 'checkbox':
						if ($row==0)
						echo '\''.$fieldName.'=\' + Array.from(formElement.querySelectorAll(\'input[name="'.$fieldName.'[]"]:checked\')).map(function(input){return input.value;}).join(\',\')';
						else
						echo ' + \'&'.$fieldName.'=\' + Array.from(formElement.querySelectorAll(\'input[name="'.$fieldName.'[]"]:checked\')).map(function(input){return input.value;}).join(\',\')';
						break;
						
						case 'radio':
						if ($row==0)
						//echo '$(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val();'; 
						echo '\''.$fieldName.'=\' + $(\'input:radio[name='.$fieldName.']:checked\', formElement).val()';
						else
						echo ' + \'&'.$fieldName.'=\' + $(\'input:radio[name='.$fieldName.']:checked\', formElement).val()';
						break;
						
						case 'button':
						case 'paragraph':
						break;
						
						default:
						if ($row==0)
						echo '\''.$fieldName.'=\' + formElement.elements.namedItem('.$fieldNameJson.').value';
						else
						echo ' + \'&'.$fieldName.'=\' + formElement.elements.namedItem('.$fieldNameJson.').value';
						break;
						}
					}
					echo '+ \'&alias=\' + formElement.elements.namedItem("alias").value + \'&MySpamTrap=\' + formElement.elements.namedItem("MySpamTrap").value';
					echo ';'. "\n";
					
					//echo 'alert (dataString);'. "\n";
					//echo 'return false;'. "\n";
					
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: '.$FormActionEndpointJson.', '. "\n";
					if ($FormActionPayloadMode === 'serialized-form')
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
						$field = $formarray[$row];
						$fieldName = (string) ($field['name'] ?? '');
						$fieldNameJson = $javascriptString($fieldName);
						switch ($field['type'])
						{
						case 'checkbox':
						if ($row==0)
						echo '\''.$fieldName.'=\' + Array.from(formElement.querySelectorAll(\'input[name="'.$fieldName.'[]"]:checked\')).map(function(input){return input.value;}).join(\',\')';
						else
						echo ' + \'&'.$fieldName.'=\' + Array.from(formElement.querySelectorAll(\'input[name="'.$fieldName.'[]"]:checked\')).map(function(input){return input.value;}).join(\',\')';
						break;
						
						case 'radio':
						if ($row==0)
						//echo '$(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val();'; 
						echo '\''.$fieldName.'=\' + $(\'input:radio[name='.$fieldName.']:checked\', formElement).val()';
						else
						echo ' + \'&'.$fieldName.'=\' + $(\'input:radio[name='.$fieldName.']:checked\', formElement).val()';
						break;
						
						case 'button':
						case 'paragraph':
						break;
						
						default:
						if ($row==0)
						echo '\''.$fieldName.'=\' + formElement.elements.namedItem('.$fieldNameJson.').value';
						else
						echo ' + \'&'.$fieldName.'=\' + formElement.elements.namedItem('.$fieldNameJson.').value';
						break;
						}
					}
					echo '+ \'&alias=\' + formElement.elements.namedItem("alias").value + \'&MySpamTrap=\' + formElement.elements.namedItem("MySpamTrap").value';
					echo ';'. "\n";
					
					/*echo 'alert (dataString);'. "\n";
					echo 'return false;'. "\n";*/
					
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: '.$FormActionEndpointJson.', '. "\n";
					if ($FormActionPayloadMode === 'data-string')
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
					echo 'document.location='.$RequestUriJson.';'. "\n";
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
					echo 'document.location='.$RequestUriJson.';'. "\n";
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
					echo 'url: '.$FormActionEndpointJson.', '. "\n";
					if ($FormActionPayloadMode === 'serialized-form')
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
					echo 'url: '.$FormActionEndpointJson.', '. "\n";
					if ($FormActionPayloadMode === 'serialized-form')
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
				$field = $formarray[$row];
				switch ($field['type'])
				{
				
				case 'textfield': /* textfield has 3 keys. */
					$question=(string) ($field['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.red_public_display_text($question).'</p>';
					$name=(string) $field['name'];
					$nameHtml=red_public_html($name);
					$displayname = (string) ($field['displayname'] ?? '');
					$initialvalue = (string) ($field['initialvalue'] ?? '');
					$required = $field['required'] ?? 'false';
					echo '<label for="'.$nameHtml.'" class="'.$nameHtml.'">'.red_public_display_text($displayname);
					if ($required!='false') echo '*';
					echo '</label><input type="text" name="'.$nameHtml.'" class="text-input" id="'.$nameHtml.'" value="'.red_public_html($initialvalue).'" />';
					echo '<span class="clear"></span><span class="error" id="'.$nameHtml.'_error">*This is not a valid '.$nameHtml.'.</span><span class="clear"></span>';
					//foreach($formarray[$row] as $key => $value)
					//{
						//echo "<li>".$key.' - '.$value."</li>";
					//}
				break;
				
				case 'password': /* textfield has 3 keys. */
					$question=(string) ($field['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.red_public_display_text($question).'</p>';
					$name=(string) $field['name'];
					$nameHtml=red_public_html($name);
					$displayname = (string) ($field['displayname'] ?? '');
					$initialvalue = (string) ($field['initialvalue'] ?? '');
					$required = $field['required'] ?? 'false';
					echo '<label for="'.$nameHtml.'" class="'.$nameHtml.'">'.red_public_display_text($displayname);
					if ($required!='false') echo '*';
					echo '</label><input type="password" name="'.$nameHtml.'" class="text-input" id="'.$nameHtml.'" value="'.red_public_html($initialvalue).'" />';
					echo '<span class="clear"></span><span class="error" id="'.$nameHtml.'_error">*This is not a valid '.$nameHtml.'.</span><span class="clear"></span>';
					//foreach($formarray[$row] as $key => $value)
					//{
						//echo "<li>".$key.' - '.$value."</li>";
					//}
				break;
				
				case 'textarea': /* textarea had 5 keys. */
					$question=(string) ($field['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.red_public_display_text($question).'</p>';
					$name=(string) $field['name'];
					$nameHtml=red_public_html($name);
					$displayname = (string) ($field['displayname'] ?? '');
					$initialvalue = (string) ($field['initialvalue'] ?? '');
					$cols = max(1, min(200, (int) ($field['cols'] ?? 40)));
					$rows = max(1, min(100, (int) ($field['rows'] ?? 5)));
					$required = $field['required'] ?? 'false';
					echo '<label for="'.$nameHtml.'" class="'.$nameHtml.'">'.red_public_display_text($displayname);
					if ($required!='false') echo '*';
					if (($field['readonly'] ?? 'false')!='false')
					echo '</label><textarea name="'.$nameHtml.'" class="text-input" id="'.$nameHtml.'" cols="'.$cols.'" rows="'.$rows.'" readonly>'.red_public_html($initialvalue).'</textarea>';
					else
					echo '</label><textarea name="'.$nameHtml.'" class="text-input" id="'.$nameHtml.'" cols="'.$cols.'" rows="'.$rows.'">'.red_public_html($initialvalue).'</textarea>';
					echo '<span class="clear"></span><span class="error" id="'.$nameHtml.'_error">*This is not a valid '.$nameHtml.'.</span><span class="clear"></span>';
				break;
			
			case 'checkbox':
					$question=(string) ($field['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.red_public_display_text($question).'</p>';
					$name=(string) $field['name'];
					$nameHtml=red_public_html($name);
					$displayname = (string) ($field['displayname'] ?? '');
					$required = $field['required'] ?? 'false';
					echo '<label class="'.$nameHtml.'">'.red_public_display_text($displayname);
					if ($required!='false') echo '*';
					echo '</label>';
					
					$value = (string) ($field['value'] ?? '');
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
							
							echo '<label class="checkbox"><input type="checkbox" name="'.$nameHtml.'[]" value="'.red_public_html($thisvalue).'" id="'.$nameHtml.'_'.$w.'" checked="checked" /> '.red_public_display_text($thisvaluelabel).'</label>';
						}
						else{
							$thisrealvalue = explode('^',$thisvalue);
							if (count($thisrealvalue)>1){
							$thisvalue = $thisrealvalue[1];
							$thisvaluelabel = $thisrealvalue[0];
							}else{
							$thisvaluelabel = $thisvalue;
							}
							echo '<label class="checkbox"><input type="checkbox" name="'.$nameHtml.'[]" value="'.red_public_html($thisvalue).'" id="'.$nameHtml.'_'.$w.'" /> '.red_public_display_text($thisvaluelabel).'</label>';
						}
						$w++;	
					}
					echo '<span class="clear"></span><span class="error" id="'.$nameHtml.'_error">*This is not a valid '.$nameHtml.'.</span><span class="clear"></span>';
			break;
			
			case 'radio':  /* radio has 4 keys. */
					$question=(string) ($field['question'] ?? '');
					$required = $field['required'] ?? 'false';
					if ($question <> '')
					echo '<p class="question">'.red_public_display_text($question).'</p>';
					$name=(string) $field['name'];
					$nameHtml=red_public_html($name);
					$displayname = (string) ($field['displayname'] ?? '');
					
					echo '<label class="'.$nameHtml.'">'.red_public_display_text($displayname);
					if ($required!='false') echo '*';
					echo '</label>';
					$value = (string) ($field['value'] ?? '');
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
							
							echo '<label class="radio"><input type="radio" name="'.$nameHtml.'" value="'.red_public_html($thisvalue).'" id="'.$nameHtml.'_'.$w.'" checked="checked" />'.red_public_display_text($thisvaluelabel).'</label>';
						}
						else{
							$thisrealvalue = explode('^',$thisvalue);
							if (count($thisrealvalue)>1){
							$thisvalue = $thisrealvalue[1];
							$thisvaluelabel = $thisrealvalue[0];
							}else{
							$thisvaluelabel = $thisvalue;
							}
							echo '<label class="radio"><input type="radio" name="'.$nameHtml.'" value="'.red_public_html($thisvalue).'" id="'.$nameHtml.'_'.$w.'" />'.red_public_display_text($thisvaluelabel).'</label>';
						}
						$w++;	
					}
					echo '<span class="clear"></span><span class="error" id="'.$nameHtml.'_error">*This is not a valid '.$nameHtml.'.</span><span class="clear"></span>';
				break;
				
			case 'select': /* select has 4 keys. */
					$question=(string) ($field['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.red_public_display_text($question).'</p>';
					$name=(string) $field['name'];
					$nameHtml=red_public_html($name);
					$displayname = (string) ($field['displayname'] ?? '');
					$required = $field['required'] ?? 'false';
					echo '<label for="'.$nameHtml.'" class="'.$nameHtml.'">'.red_public_display_text($displayname);
					if ($required!='false') echo '*';
					echo '</label>&nbsp;<select name="'.$nameHtml.'" class="text-input" id="'.$nameHtml.'">';
					$value = (string) ($field['value'] ?? '');
					$value = explode(',',$value);
					foreach ($value as $thisvalue){
						$selected = explode('^',$thisvalue);
						if (count($selected)>1){
							$thisvalue=$selected[0];
							switch ($selected[1])
							{
								case 'disabled':
								echo '<option value="'.red_public_html($thisvalue).'" disabled="disabled">'.red_public_display_text($thisvalue).'</option>';
								break;
								case 'selected':
								echo '<option value="'.red_public_html($thisvalue).'" selected="selected">'.red_public_display_text($thisvalue).'</option>';
								break;
							}
							
						}
						else
							echo '<option value="'.red_public_html($thisvalue).'">'.red_public_display_text($thisvalue).'</option>';
					}
					echo '</select>';
					echo '<span class="clear"></span><span class="error" id="'.$nameHtml.'_error">*This is not a valid '.$nameHtml.'.</span>';
				break;
			
			
			case 'hidden': /* button 1 key */
					$name=(string) $field['name'];
					$initialvalue = (string) ($field['initialvalue'] ?? '');
					if ($initialvalue==='referral')
					$initialvalue=str_replace('referral', referral, $initialvalue);
					echo '<input type="hidden" name="'.red_public_html($name).'" value="'.red_public_html($initialvalue).'" />';
				break;
				
			case 'button': /* button 1 key */
					$question=(string) ($field['question'] ?? '');
					echo '<span class="clear"></span>';
					if ($question <> '')
					echo '<p class="question">'.red_public_display_text($question).'</p>';
					$name=(string) $field['name'];
					$displayname = (string) ($field['displayname'] ?? '');
					echo '<div class="btns">';
					/*echo '<input type="reset" class="groovybutton" onClick="document.getElementById(\''.$Alias.'\').reset()" value="RESET" />&nbsp;';*/
					echo '<input type="submit" name="'.red_public_html($name).'" class="button" value="'.red_public_html($displayname).'"/>';
					
					echo '</div>';
				break;
				
			case 'paragraph':
					$paragraph=(string) ($field['paragraph'] ?? '');
					echo '<p>'.red_public_display_text($paragraph).'</p>';
				break;
				}
					
			}
			echo '</fieldset>';
			echo '<input type="hidden" name="alias" value="'.red_public_html($AliasArt).'" />';
			echo '<input type="hidden" name="RecordID" value="'.red_public_html($RecordID).'" />';
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
		$rows = red_public_form_rows($db->connection, $recordid);
		
		//echo ('start'.count($rows).'<br/>');
		$result_counter = count($rows);
		$adminComponentIds = red_public_admin_component_ids($_SESSION['AdminComponents'] ?? '');
		$canEditForm = red_public_admin_component_authorized($db->connection, 'Form', $adminComponentIds);
		//
		foreach($rows as $formRecord)
		{
			$RecordID=$formRecord['RecordID'];
			$Alias=red_public_js_identifier($formRecord['Alias'], 'form');
			$Title=red_public_display_text($formRecord['Title']);
			$FormActionKey=strcasecmp((string) ($formRecord['FormType'] ?? ''), 'Login') === 0
				? 'form-login'
				: 'form-builder';
			
			/// COMPARE SESSION 'AdminComponents' WITH RED_COMPONENTS.
				// IF VALUE EXIST THEN SHOW UPDATE BUTTON. IF NOT, DISPLAY MESSAGE FOR "ADMIN NOT AUTHORIZED TO UPDATE".
				if(!$canEditForm){
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
					echo '<h7 id="cp"> '.$Title.'</h7><br/><input type="submit" name="Edit" class="cp red-admin-component-action red-admin-component-action--'.$FormActionKey.'" id="cp_form" value="Edit Form"/>';
					echo '</form>';
				}else{
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
					echo '<h7 id="cp"> '.$Title.'</h7><br/><input type="submit" name="Edit" class="cp red-admin-component-action red-admin-component-action--'.$FormActionKey.'" id="cp_form" value="Edit Form"/>';
					echo '<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
					echo '<input type="hidden" name="ArtRecordID" id="RecordID" value="'.$recordid.'" />';
					echo '<input type="hidden" name="VarPosition" id="VarPosition" value="'.red_public_html($VarPosition).'" />';
                    echo '<input type="hidden" name="Article" id="Article" value="'.red_public_html(red_public_route_value('article')).'" />';
					echo '<input type="hidden" name="Layout" id="Layout" value="'.red_public_html($layout).'" />';
					echo '</form>';
					//END "ADMIN AUTHORIZED TO UPDATE".
				}
				
				//END COMPARE SESSION
				echo '<hr id="cp">';
			
			$result_counter = ($result_counter - 1);	
		}
	}
}
?>
