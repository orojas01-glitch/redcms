<?php
/**
 * Legacy Bootstrap public Form component view.
 *
 * Core supplies the exact prepared RED_C_Form record, parsed fields, alias,
 * and action context. This compatibility view intentionally preserves the
 * historical HTML, JavaScript, session, language, request, hidden-field, and
 * submission behavior while performing no query or component dispatch.
 */
$context = red_legacy_public_form_context_validate($redThemeFormContext ?? null);
$rows = $context['rows'];
$optionalFieldAttributes = static function (array $field, array $attributeNames): string {
    $attributes = '';
    foreach ($attributeNames as $attributeName) {
        $value = trim((string) ($field[$attributeName] ?? ''));
        if ($value !== '') {
            $attributes .= ' ' . $attributeName . '="' . red_public_html($value) . '"';
        }
    }
    return $attributes;
};
$textFieldInputType = static function (array $field): string {
    $inputType = strtolower(trim((string) ($field['inputtype'] ?? 'text')));
    return in_array($inputType, ['text', 'email', 'tel', 'url', 'search'], true)
        ? $inputType
        : 'text';
};
$formJsLiteral = static function ($value): string {
    $encoded = json_encode(
        (string) $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    return is_string($encoded) ? $encoded : '""';
};
$preparePublicFormFields = static function ($fields): array {
    $supportedTypes = ['textfield', 'password', 'textarea', 'checkbox', 'radio', 'select', 'hidden', 'button', 'paragraph'];
    $prepared = [];
    foreach (is_array($fields) ? $fields : [] as $field) {
        if (!is_array($field)) {
            continue;
        }
        $field['type'] = strtolower(trim((string) ($field['type'] ?? '')));
        if (!in_array($field['type'], $supportedTypes, true)) {
            continue;
        }
        if ($field['type'] !== 'paragraph'
            && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', (string) ($field['name'] ?? '')) !== 1
        ) {
            continue;
        }
        $prepared[] = $field;
    }
    return $prepared;
};

		//echo ('start'.count($rows).'<br/>');
		$result_counter = count($rows);
		//
		foreach($rows as $preparedForm)
		{

			
			$formRecord = $preparedForm['record'];
			$formarray = $preparePublicFormFields($preparedForm['fields']);
			
			//print_r($formarray);
			
			$AliasArt=$preparedForm['alias']['raw'];
			$Alias=$preparedForm['alias']['javascript'];
			$RecordID=(int) $formRecord['RecordID'];
			$Title=red_public_display_text($formRecord['Title']);
			$FormType=$preparedForm['action']['formType'];
			$FormActionEndpoint=$preparedForm['action']['endpoint'];
			$FormActionPayloadMode=$preparedForm['action']['payloadMode'];
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
				$fieldNameJson = $formJsLiteral($formarray[$row]['name'] ?? '');
				$validationMessage = $formJsLiteral(
					'Campo obligatorio -> '.red_public_plain_text($formarray[$row]['displayname'] ?? '').'.'
				);
				switch ($formarray[$row]['type'])
				{
				case 'textfield': /* textfield has 3 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						switch (strtolower($formarray[$row]['name']))
						{
							case 'email':
								echo 'var FieldControl_'.$row.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
								echo 'var FieldValue_'.$row.'=FieldControl_'.$row.'.value'. "\n";
								echo 'var filter=/^[a-zA-Z]+([_\.-]?[a-zA-Z0-9]+)*@[a-zA-Z0-9]+([\.-]?[a-zA-Z0-9]+)*(\.[a-zA-Z]{2,4})+$/i'. "\n";
								echo 'if (filter.test(FieldValue_'.$row.')){}'. "\n";
								echo 'else{'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
								echo 'alert('.$validationMessage.');'. "\n";
								echo 'FieldControl_'.$row.'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
							case 'telephone':
								echo 'var FieldControl_'.$row.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
								echo 'var FieldValue_'.$row.'=FieldControl_'.$row.'.value'. "\n";
								echo 'var filter=/^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/'. "\n";
								echo 'if (filter.test(FieldValue_'.$row.')){}'. "\n";
								echo 'else{'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
								echo 'alert('.$validationMessage.');'. "\n";
								echo 'FieldControl_'.$row.'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
							case 'fax':
								echo 'var FieldControl_'.$row.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
								echo 'var FieldValue_'.$row.'=FieldControl_'.$row.'.value'. "\n";
								echo 'var filter=/^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/'. "\n";
								echo 'if (filter.test(FieldValue_'.$row.')){}'. "\n";
								echo 'else{'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
								echo 'alert('.$validationMessage.');'. "\n";
								echo 'FieldControl_'.$row.'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
							default:
								echo 'var FieldControl_'.$row.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
								echo 'var FieldValue_'.$row.'=FieldControl_'.$row.'.value'. "\n";
								echo 'if (FieldValue_'.$row.' == "") {'. "\n";
								//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
								echo 'alert('.$validationMessage.');'. "\n";
								echo 'FieldControl_'.$row.'.focus();'. "\n";
								echo 'return false ;' . "\n";
								echo ' }' . "\n";
							break;
						}
					}
				break;
				
				case 'password': /* password has 3 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'var FieldControl_'.$row.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
						echo 'var FieldValue_'.$row.'=FieldControl_'.$row.'.value'. "\n";
						echo 'if (FieldValue_'.$row.' == "") {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
						echo 'alert('.$validationMessage.');'. "\n";
						echo 'FieldControl_'.$row.'.focus();'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
					}
				break;
				
				case 'textarea': /* textarea has 5 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'var FieldControl_'.$row.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
						echo 'var FieldValue_'.$row.'=FieldControl_'.$row.'.value'. "\n";
						echo 'if (FieldValue_'.$row.' == "") {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
						echo 'alert('.$validationMessage.');'. "\n";
						echo 'FieldControl_'.$row.'.focus();'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
					}
				break;
				
				case 'checkbox': /* checkbox has 3 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'var Checkbox_'.$row.'=formElement.querySelectorAll(\'input[name="'.$formarray[$row]['name'].'[]"]:checked\').length'. "\n";
						echo 'if (Checkbox_'.$row.' === 0) {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n";
						echo 'alert('.$validationMessage.');'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
					}
				break;
				
				case 'radio': /* radio has 4 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'var Radio_Order = false;'. "\n";
						echo 'var RadioFields_'.$row.'=formElement.querySelectorAll(\'input[type="radio"][name="'.$formarray[$row]['name'].'"]\');'. "\n";
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
						echo 'alert('.$validationMessage.');'. "\n";
						echo 'return false ;' . "\n";
						echo ' }' . "\n";
						
					}
				break;
				
				case 'select': /* select has 4 keys. */
					if ($formarray[$row]['required']!='false'){
						echo '// ** START **' . "\n";
						echo 'var FieldControl_'.$row.'=formElement.elements.namedItem('.$fieldNameJson.');'. "\n";
						echo 'if (FieldControl_'.$row.'.selectedIndex == 0) {'. "\n";
						//echo '$("span#'.$formarray[$row]['name'].'_error").show(); '. "\n"; 
						echo 'alert('.$validationMessage.');'. "\n";
						echo 'FieldControl_'.$row.'.focus();'. "\n";
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
						$fieldNameJson = $formJsLiteral($formarray[$row]['name'] ?? '');
						switch ($formarray[$row]['type'])
						{
						case 'checkbox':
						if ($row==0)
						echo '\''.$formarray[$row]['name'].'=\' + Array.from(formElement.querySelectorAll(\'input[name="'.$formarray[$row]['name'].'[]"]:checked\')).map(function(input){return input.value;}).join(\',\')';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + Array.from(formElement.querySelectorAll(\'input[name="'.$formarray[$row]['name'].'[]"]:checked\')).map(function(input){return input.value;}).join(\',\')';
						break;
						
						case 'radio':
						if ($row==0)
						//echo '$(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val();'; 
						echo '\''.$formarray[$row]['name'].'=\' + $(\'input:radio[name='.$formarray[$row]['name'].']:checked\', formElement).val()';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + $(\'input:radio[name='.$formarray[$row]['name'].']:checked\', formElement).val()';
						break;
						
						case 'button':
						case 'paragraph':
						break;
						
						default:
						if ($row==0)
						echo '\''.$formarray[$row]['name'].'=\' + formElement.elements.namedItem('.$fieldNameJson.').value';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + formElement.elements.namedItem('.$fieldNameJson.').value';
						break;
						}
					}
					echo '+ \'&alias=\' + formElement.elements.namedItem("alias").value + \'&MySpamTrap=\' + formElement.elements.namedItem("MySpamTrap").value';
					echo ';'. "\n";
					
					//echo 'alert (dataString);'. "\n";
					//echo 'return false;'. "\n";
					
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: '.$formJsLiteral($FormActionEndpoint).', '. "\n";
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
						$fieldNameJson = $formJsLiteral($formarray[$row]['name'] ?? '');
						switch ($formarray[$row]['type'])
						{
						case 'checkbox':
						if ($row==0)
						echo '\''.$formarray[$row]['name'].'=\' + Array.from(formElement.querySelectorAll(\'input[name="'.$formarray[$row]['name'].'[]"]:checked\')).map(function(input){return input.value;}).join(\',\')';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + Array.from(formElement.querySelectorAll(\'input[name="'.$formarray[$row]['name'].'[]"]:checked\')).map(function(input){return input.value;}).join(\',\')';
						break;
						
						case 'radio':
						if ($row==0)
						//echo '$(\'input:radio[name='.$formarray[$row]['name'].']:checked\').val();'; 
						echo '\''.$formarray[$row]['name'].'=\' + $(\'input:radio[name='.$formarray[$row]['name'].']:checked\', formElement).val()';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + $(\'input:radio[name='.$formarray[$row]['name'].']:checked\', formElement).val()';
						break;
						
						case 'button':
						case 'paragraph':
						break;
						
						default:
						if ($row==0)
						echo '\''.$formarray[$row]['name'].'=\' + formElement.elements.namedItem('.$fieldNameJson.').value';
						else
						echo ' + \'&'.$formarray[$row]['name'].'=\' + formElement.elements.namedItem('.$fieldNameJson.').value';
						break;
						}
					}
					echo '+ \'&alias=\' + formElement.elements.namedItem("alias").value + \'&MySpamTrap=\' + formElement.elements.namedItem("MySpamTrap").value';
					echo ';'. "\n";
					
					/*echo 'alert (dataString);'. "\n";
					echo 'return false;'. "\n";*/
					
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: '.$formJsLiteral($FormActionEndpoint).', '. "\n";
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
					echo 'document.location='.$formJsLiteral($_SERVER['REQUEST_URI'] ?? '/').';'. "\n";
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
					echo 'document.location='.$formJsLiteral($_SERVER['REQUEST_URI'] ?? '/').';'. "\n";
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
					echo 'url: '.$formJsLiteral($FormActionEndpoint).', '. "\n";
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
					echo 'url: '.$formJsLiteral($FormActionEndpoint).', '. "\n";
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
				switch ($formarray[$row]['type'])
				{
				
				case 'textfield': /* textfield has 3 keys. */
					$question=red_public_display_text($formarray[$row]['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = red_public_display_text($formarray[$row]['displayname'] ?? '');
					$initialvalue = red_public_html($formarray[$row]['initialvalue'] ?? '');
					$required = $formarray[$row]['required'];
					$inputtype = $textFieldInputType($formarray[$row]);
					$optionalattributes = $optionalFieldAttributes(
						$formarray[$row],
						['autocomplete', 'placeholder', 'inputmode']
					);
					echo '<label for="'.$name.'" class="'.$name.'">'.$displayname;
					if ($formarray[$row]['required']!='false') echo '*';
					echo '</label><input type="'.$inputtype.'" name="'.$name.'" class="text-input" id="'.$name.'" value="'.$initialvalue.'"'.$optionalattributes.' />';
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span><span class="clear"></span>';
					//foreach($formarray[$row] as $key => $value)
					//{
						//echo "<li>".$key.' - '.$value."</li>";
					//}
				break;
				
				case 'password': /* textfield has 3 keys. */
					$question=red_public_display_text($formarray[$row]['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = red_public_display_text($formarray[$row]['displayname'] ?? '');
					$initialvalue = red_public_html($formarray[$row]['initialvalue'] ?? '');
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
					$question=red_public_display_text($formarray[$row]['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = red_public_display_text($formarray[$row]['displayname'] ?? '');
					$initialvalue = red_public_html($formarray[$row]['initialvalue'] ?? '');
					$cols = max(1, min(200, (int) ($formarray[$row]['cols'] ?? 45)));
					$rows = max(1, min(100, (int) ($formarray[$row]['rows'] ?? 5)));
					$required = $formarray[$row]['required'];
					$optionalattributes = $optionalFieldAttributes(
						$formarray[$row],
						['autocomplete', 'placeholder']
					);
					echo '<label for="'.$name.'" class="'.$name.'">'.$displayname;
					if ($formarray[$row]['required']!='false') echo '*';
					if ($formarray[$row]['readonly']!='false')
					echo '</label><textarea name="'.$name.'" class="text-input" id="'.$name.'" cols="'.$cols.'" rows="'.$rows.'"'.$optionalattributes.' readonly>'.$initialvalue.'</textarea>';
					else
					echo '</label><textarea name="'.$name.'" class="text-input" id="'.$name.'" cols="'.$cols.'" rows="'.$rows.'"'.$optionalattributes.'>'.$initialvalue.'</textarea>';
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span><span class="clear"></span>';
				break;
			
			case 'checkbox':
					$question=red_public_display_text($formarray[$row]['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = red_public_display_text($formarray[$row]['displayname'] ?? '');
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
							
							echo '<label class="checkbox"><input type="checkbox" name="'.$name.'[]" value="'.red_public_html($thisvalue).'" id="'.$name.'_'.$w.'" checked="checked" /> '.red_public_display_text($thisvaluelabel).'</label>';
						}
						else{
							$thisrealvalue = explode('^',$thisvalue);
							if (count($thisrealvalue)>1){
							$thisvalue = $thisrealvalue[1];
							$thisvaluelabel = $thisrealvalue[0];
							}else{
							$thisvaluelabel = $thisvalue;
							}
							echo '<label class="checkbox"><input type="checkbox" name="'.$name.'[]" value="'.red_public_html($thisvalue).'" id="'.$name.'_'.$w.'" /> '.red_public_display_text($thisvaluelabel).'</label>';
						}
						$w++;	
					}
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span><span class="clear"></span>';
			break;
			
			case 'radio':  /* radio has 4 keys. */
					$question=red_public_display_text($formarray[$row]['question'] ?? '');
					$required = $formarray[$row]['required'];
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = red_public_display_text($formarray[$row]['displayname'] ?? '');
					
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
							
							echo '<label class="radio"><input type="radio" name="'.$name.'" value="'.red_public_html($thisvalue).'" id="'.$name.'_'.$w.'" checked="checked" />'.red_public_display_text($thisvaluelabel).'</label>';
						}
						else{
							$thisrealvalue = explode('^',$thisvalue);
							if (count($thisrealvalue)>1){
							$thisvalue = $thisrealvalue[1];
							$thisvaluelabel = $thisrealvalue[0];
							}else{
							$thisvaluelabel = $thisvalue;
							}
							echo '<label class="radio"><input type="radio" name="'.$name.'" value="'.red_public_html($thisvalue).'" id="'.$name.'_'.$w.'" />'.red_public_display_text($thisvaluelabel).'</label>';
						}
						$w++;	
					}
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span><span class="clear"></span>';
				break;
				
			case 'select': /* select has 4 keys. */
					$question=red_public_display_text($formarray[$row]['question'] ?? '');
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = red_public_display_text($formarray[$row]['displayname'] ?? '');
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
					echo '<span class="clear"></span><span class="error" id="'.$name.'_error">*This is not a valid '.$name.'.</span>';
				break;
			
			
			case 'hidden': /* button 1 key */
					$name=$formarray[$row]['name'];
					$initialvalue = (string) ($formarray[$row]['initialvalue'] ?? '');
					if ($initialvalue==='referral')
					$initialvalue=str_replace('referral', referral, $initialvalue);
					echo '<input type="hidden" name="'.$name.'" value="'.red_public_html($initialvalue).'" />';
				break;
				
			case 'button': /* button 1 key */
					$question=red_public_display_text($formarray[$row]['question'] ?? '');
					echo '<span class="clear"></span>';
					if ($question <> '')
					echo '<p class="question">'.$question.'</p>';
					$name=$formarray[$row]['name'];
					$displayname = red_public_html($formarray[$row]['displayname'] ?? '');
					echo '<div class="btns">';
					/*echo '<input type="reset" class="groovybutton" onClick="document.getElementById(\''.$Alias.'\').reset()" value="RESET" />&nbsp;';*/
					echo '<input type="submit" name="'.$name.'" class="button" value="'.$displayname.'"/>';
					
					echo '</div>';
				break;
				
			case 'paragraph':
					$paragraph=red_public_display_text($formarray[$row]['paragraph'] ?? '');
					echo '<p class="form-note">'.$paragraph.'</p>';
				break;
				}
					
			}
			echo '</fieldset>';
			echo '<input type="hidden" name="alias" value="'.red_public_html($AliasArt).'" />';
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
