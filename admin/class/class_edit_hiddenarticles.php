<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session(); ?>
<?php
#[\AllowDynamicProperties]
class edit_inactive_article
{
	public function inactive_article_form($layout)
	{
		
		echo '<div class="container_12 cp_padtop"><div class="wrapper"><article class="grid_12 cp_admin"><div class="scroll"><div style="padding:10px;">';
		echo '<form id="edit_inactive_article" name="edit_inactive_article" class="cp"><fieldset>';
		echo '<div class="header">';
		echo '<div class="titleleft longtitle"><strong>Article Title</strong>';
		echo '</div>';
		echo '<div class="titleleft component"><strong>Component</strong>';
		echo '</div>';
		echo '<div class="titleright editico"><strong>Edit</strong>';
		echo '</div>';
		echo '</div>';
		echo '<div class="clear-cp"></div>';
		
        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $result = $db->query("SELECT * FROM RED_Articles WHERE  Language='".language."' AND Active <> 'Y' ORDER BY Updated ASC");
        while($row = mysqli_fetch_assoc($result))
        {
            $Alias=$row['Alias'];
			$Alias=preg_replace('/-/','_',$Alias);
			$Title=preg_replace('/<[^>]*>/', '', $row['Title']);
            $Component=$row['Component'];
            $RecordID=$row['RecordID'];
			
			// COMPARE SESSION 'AdminComponents' WITH RED_COMPONENTS.
				// IF VALUE EXIST THEN SHOW UPDATE BUTTON. IF NOT, DISPLAY MESSAGE FOR "ADMIN NOT AUTHORIZED TO UPDATE".
				$AdminComponents = explode(",", $_SESSION['AdminComponents']);
				//echo($_SESSION['AdminComponents'].'='.count($AdminComponents).'<br/>');
				for ($w=0; $w<count($AdminComponents); $w++)
				{
					
					$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
					$resultC = $db->query("SELECT CompGroup FROM RED_Components WHERE RecordID='".$AdminComponents[$w]."' AND UniqueName='".$Component."'");
					$row = mysqli_fetch_assoc($resultC);
					if ($row) {
                        $CompGroup=$row['CompGroup'];

                        switch ($CompGroup) { 
                            // CHECK IF THIS IS A GROUP COMPONENT. I.E: FORM, GALLERY, SUBMENU. GET RECORDID
                            case 'Y':
                                $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                                $resultE = $db->query("SELECT RecordID FROM RED_C_" . $Component . " WHERE RefID='" . $RecordID . "'");

                                if ($resultE->num_rows > 0) {
                                    $row = $resultE->fetch_assoc();  // Fetch the row as an associative array
                                    $CRecordID = $row['RecordID'];
                                }
                                break;
                        }
                    }
					
					if(($resultC->num_rows==0)&&($w==count($AdminComponents))){
						//echo $w.' ADMINISTRATOR NOT AUTHORIZED TO UPDATE<br />';
						echo '<script type="text/javascript">'. "\n";
						echo '<!--' ."\n";
						echo 'function edit_inactive_article_'.$Alias.' (){'. "\n";
						echo '$(\'#msggbox_edit_inactive_article\').html("You\'re not authorized to edit this content.")'. "\n";
						echo '.fadeIn(1500, function() {'. "\n";
						echo '});'. "\n";
						echo 'return false;'. "\n";
						echo '}'. "\n";
						echo '-->'. "\n";
						echo '</script>';
						echo '<div class="wrapper row2">';
						echo '<label style="display:inline;">';
						echo '<div class="titleleft longtitle">';
						echo '<strong>'.$Title.'</strong>';
						echo '</div>';
						echo '<div class="titleleft component">';
						echo $Component;
						echo '</div>';
						
						echo '<div class="titleright editico">';
						echo '<img src="/admin/images/ico_edit.png" onClick="edit_inactive_article_'.$Alias.'();" title="Edit" style="cursor:pointer">';
						echo '</div>';
						echo '</label>';
						echo '</div>';	
					break;	
					}elseif(($resultC->num_rows==0));
					else{
						//echo $w.' ADMINISTRATOR AUTHORIZED TO UPDATE<br />';
						switch ($CompGroup){ // CHECK IF THIS IS A GROUP COMPONENT. I.E:FORM, GALLERY, SUBMENU.
						case 'Y':
							echo '<script language="JavaScript" type="text/javascript">'. "\n";
							echo '<!--' ."\n";
							echo 'function edit_inactive_article_'.$Alias.' (RecordID,CRecordID){'. "\n";
							echo '$.ajax({'. "\n";
							echo 'type: "POST", '. "\n";
							echo 'url: "/admin/bin/edit_'.strtolower($Component).'.php", '. "\n";
							echo 'data: "RecordID=" + CRecordID + "&ArtRecordID=" + RecordID +"&Layout='.$layout.'", '. "\n";
							echo 'success: function(data) { '. "\n";
							//echo 'alert (data);'. "\n";
							//echo 'return false;'. "\n";
							echo 'if (data) '. "\n";
							echo '{'. "\n";
							echo '$(\'#edit_content_grid\').hide();'. "\n";
							echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
							echo '.fadeIn(1500, function() {'. "\n";
							//echo '$(\'#msggbox_edit_inactive_article\').html("Opening.")'. "\n";
							//echo '.append("<p>Please wait.</p>")'. "\n";
							echo '});'. "\n";
							echo '}'. "\n";
							echo 'else '. "\n";
							echo '{'. "\n";
							echo '$("#msggbox_edit_inactive_article").html("Error. Please try again.")'. "\n";
							echo '.fadeIn(1500, function() {'. "\n";
							echo '$("#msggbox_edit_inactive_article");'. "\n";
							echo '});'. "\n";
							echo '}'. "\n";
							echo '}'. "\n";
							echo '});'. "\n";
							echo 'return false;'. "\n";
							echo '}'. "\n";
							echo '-->'. "\n";
							echo '</script>';
							
							echo '<div class="wrapper row2">';
							echo '<label style="display:inline;">';
							echo '<div class="titleleft longtitle">';
							echo '<strong>'.$Title.'</strong>';
							echo '</div>';
							echo '<div class="titleleft component">';
							echo $Component;
							echo '</div>';
							echo '<div class="titleright editico">';
							echo '<img src="/admin/images/ico_edit.png" onClick="javascript:showdiv(\'editcontent\'); edit_inactive_article_'.$Alias.'(' .$RecordID . ','.$CRecordID.');" title="Edit" style="cursor:pointer">';
							echo '</div>';
							echo '</label>';
							echo '</div>';
							
							
						break;
						default:
							
							echo '<script language="JavaScript" type="text/javascript">'. "\n";
							echo '<!--' ."\n";
							echo 'function edit_inactive_article_'.$Alias.' (RecordID){'. "\n";
							echo '$.ajax({'. "\n";
							echo 'type: "POST", '. "\n";
							echo 'url: "/admin/bin/edit_'.strtolower($Component).'.php", '. "\n";
							echo 'data: "RecordID=" + RecordID + "&Layout='.$layout.'", '. "\n";
							echo 'success: function(data) { '. "\n";
							//echo 'alert (data);'. "\n";
							//echo 'return false;'. "\n";
							echo 'if (data) '. "\n";
							echo '{'. "\n";
							echo '$(\'#edit_content_grid\').hide();'. "\n";
							echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
							echo '.fadeIn(1500, function() {'. "\n";
							//echo '$(\'#msggbox_edit_inactive_article\').html("Opening.")'. "\n";
							//echo '.append("<p>Please wait.</p>")'. "\n";
							echo '});'. "\n";
							echo '}'. "\n";
							echo 'else '. "\n";
							echo '{'. "\n";
							echo '$("#msggbox_edit_inactive_article").html("Error. Please try again.")'. "\n";
							echo '.fadeIn(1500, function() {'. "\n";
							echo '$("#msggbox_edit_inactive_article");'. "\n";
							echo '});'. "\n";
							echo '}'. "\n";
							echo '}'. "\n";
							echo '});'. "\n";
							echo 'return false;'. "\n";
							echo '}'. "\n";
							echo '-->'. "\n";
							echo '</script>';
							echo '<div class="wrapper row2">';
							echo '<label style="display:inline;">';
							echo '<div class="titleleft longtitle">';
							echo '<strong>'.$Title.'</strong>';
							echo '</div>';
							echo '<div class="titleleft component">';
							echo $Component;
							echo '</div>';
							
							echo '<div class="titleright editico">';
							echo '<img src="/admin/images/ico_edit.png" onClick="javascript:showdiv(\'editcontent\'); edit_inactive_article_'.$Alias.'(' .$RecordID . ');" title="Edit" style="cursor:pointer">';
							echo '</div>';
							echo '</label>';
							echo '</div>';
						
						
						break;
						}
						break;
					}
				}
			
        }
        $db->close();
		
		echo '</fieldset></form>';
		echo '</div></div></article></div></div>';
       
	}
}