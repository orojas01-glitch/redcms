<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session(); ?>
<?php
#[\AllowDynamicProperties]
class editadvanced
{
	public function advanced_form()
	{
		
		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
		echo 'function edit_advanced (RecordID)'. "\n".'{' . "\n"; 
		//echo 'alert (RecordID);'. "\n";
		//echo 'return false;'. "\n";
		echo '$.ajax({ '. "\n";
		echo'type: "POST", '. "\n";
		echo 'url: "/admin/bin/edit_advanced.php", '. "\n";
		echo 'data: "RecordID=" + RecordID, '. "\n";
		echo 'success: function(data) { '. "\n";
		echo 'if (data)'. "\n"; 
		echo '{'. "\n";
		echo '$(\'#edit_advanced_grid\').hide();'. "\n";
		echo '$(\'#msggbox_edit_advanced\').html(data)'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_edit_advanced\');'. "\n";
		echo '});'. "\n";
		echo '}'. "\n";
		echo 'else'. "\n"; 
		echo '{'. "\n";
		echo '$(\'#msggbox_edit_advanced\').html("Error. Please try again.")'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_edit_advanced\');'. "\n";
		echo '});'. "\n";
		//echo 'alert ("no data");'. "\n";
		echo '}'. "\n";
		echo '}'. "\n";
		echo '});'. "\n";
		echo 'return false;'. "\n";
		echo '}'. "\n";
		echo '-->'. "\n";
		echo '</script>';
		
		
        echo '<div class="container_12 cp_padtop"><div class="wrapper"><article class="grid_12 cp_admin""><div style="padding:10px;">';
		echo '<form id="editadvanced" name="editadvanced" class="cp"><fieldset>';
		echo '<div class="header">';
		echo '<div class="titleleft title"><strong>Advanced Item</strong>';
		echo '</div>';
		echo '<div class="titleright editico"><strong>Edit</strong>';
		echo '</div>';
		echo '</div>';
		echo '<div class="clear-cp"></div>';
		
        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $result = $db->query("SELECT * FROM RED_Advanced WHERE Language='".language."' ORDER BY RecordID ASC");
        while($row = mysqli_fetch_assoc($result))
        {
			$Item=preg_replace('/\_/',' ',$row['Item']);
            $RecordID=$row['RecordID'];
                
            echo '<div class="wrapper row2">';
			echo '<label style="display:inline;">';
            echo '<div class="titleleft title">';
            echo '<strong>'.$Item.'</strong>';
            echo '</div>';
            echo '<div class="titleright editico">';
            echo '<img src="/admin/images/ico_edit.png" onClick="edit_advanced(' .$RecordID . ');" title="Edit" style="cursor:pointer">';
            echo '</div>';
			echo '</label>';
			echo '</div>';
        }
        $db->close();
		
		echo '</fieldset></form>';
		//echo '<form id="addsection" class="form" name="addsection" method="post" onSubmit="return addsections(this);">';
		//echo '</form>';
		echo '</div></article></div></div>';
       
	}
}