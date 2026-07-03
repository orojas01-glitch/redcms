<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session(); ?>
<?php
#[\AllowDynamicProperties]
class editsection
{
	public function section_form()
	{
		
		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
		echo 'function editsections (RecordID)'. "\n".'{' . "\n"; 
		//echo 'alert (RecordID);'. "\n";
		//echo 'return false;'. "\n";
		echo '$.ajax({ '. "\n";
		echo'type: "POST", '. "\n";
		echo 'url: "/admin/bin/edit_section.php", '. "\n";
		echo 'data: "RecordID=" + RecordID, '. "\n";
		echo 'success: function(data) { '. "\n";
		echo 'if (data)'. "\n"; 
		echo '{'. "\n";
		echo '$(\'#edit_section_grid\').hide();'. "\n";
		echo '$(\'#msggbox_edit_section\').html(data)'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_edit_section\');'. "\n";
		echo '});'. "\n";
		echo '}'. "\n";
		echo 'else'. "\n"; 
		echo '{'. "\n";
		echo '$(\'#msggbox_edit_section\').html("Error. Please try again.")'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_edit_section\');'. "\n";
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
		echo '<form id="editsection" name="editsection" class="cp"><fieldset>';
		echo '<div class="header">';
		echo '<div class="titleleft title"><strong>Section Title</strong>';
		echo '</div>';
		echo '<div class="titleleft layout"><strong>Layout</strong>';
		echo '</div>';
		echo '<div class="titleleft menuactive"><strong>Active</strong>';
		echo '</div>';
		echo '<div class="titleright editico"><strong>Edit</strong>';
		echo '</div>';
		echo '</div>';
		echo '<div class="clear-cp"></div>';
		
        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $result = $db->query("SELECT * FROM RED_Sections WHERE Language='".language."' ORDER BY RecordID ASC");
        while($row = mysqli_fetch_assoc($result))
        {
            $Sections=$row['Sections'];
			$Title=$row['Title'];
            $Layout=$row['Layout'];
            $Active=$row['Active'];
            $RecordID=$row['RecordID'];
                
            echo '<div class="wrapper row2">';
			echo '<label style="display:inline;">';
            echo '<div class="titleleft title">';
			if (strtolower($Sections)==='home')
			echo '<strong><a href=/ />'.$Title.'</a></strong>';
            else
			echo '<strong><a href=/'.$Sections.'/ />'.$Title.'</a></strong>';
            echo '</div>';
            echo '<div class="titleleft layout">';
            echo $Layout;
            echo '</div>';
			
            echo '<div class="titleleft menuactive">';
            echo $Active;
            echo '</div>';
            echo '<div class="titleright editico">';
            echo '<img src="/admin/images/ico_edit.png" onClick="editsections(' .$RecordID . ');" title="Edit" style="cursor:pointer">';
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