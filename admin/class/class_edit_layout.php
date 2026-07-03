<?php
#[\AllowDynamicProperties]
class editlayout
{
	public function layout_form($countpage,$section,$category,$subcategory,$article,$layout)
	{
		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
	
		echo 'function run_update_layout (update_layout)'. "\n".'{' . "\n"; 
				
		//echo 'alert (dataString);'. "\n";
		//echo 'return false;'. "\n";
		
		echo '$.ajax({ '. "\n";
		echo'type: "POST", '. "\n";
		echo 'url: "/admin/bin/update_layout.php", '. "\n";
		echo 'data: $("#update_layout").serialize(), '. "\n";
		echo 'success: function(data) { '. "\n";
		//echo 'alert (data);'. "\n";
		echo 'if (data)'. "\n"; 
		echo '{'. "\n";
		echo '$(\'#msggbox_update_layout\').html("Updated.")'. "\n";
		echo '.hide()'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_update_layout\');'. "\n";
		echo 'window.location.reload();'. "\n";
		echo '});'. "\n";
		echo '}'. "\n";
		echo 'else'. "\n"; 
		echo '{'. "\n";
		//echo '$(\'#form_'.$Alias.'\').html("<div id=\'message_'.$Alias.'\'></div>");'. "\n";
		echo '$(\'#msggbox_update_layout\').html("Error. Please try again.")'. "\n";
		echo '.hide()'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_update_layout\');'. "\n";
		echo '});'. "\n";
		echo '}'. "\n";
		echo '}'. "\n";
		echo '});'. "\n";
		echo 'return false;'. "\n";
		echo '}'. "\n";
		echo '-->'. "\n";
		echo '</script>';
		
		echo '<form id="update_layout" name="update_layout" method="post">';
		echo '<input type="hidden" name="countpage" id="countpage" value="'.$countpage.'" />';
		echo '<input type="hidden" name="sections" id="section" value="'.$section.'" />';
		echo '<input type="hidden" name="categories" id="category" value="'.$category.'" />';
		echo '<input type="hidden" name="subcategories" id="subcategory" value="'.$subcategory.'" />';
		echo '<input type="hidden" name="article" id="article" value="'.$article.'" />';
		echo '<select name="Layout" id="layout" onChange="return run_update_layout(update_layout);">';
		
		//$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
//		$result = $db->query("SELECT UniqueName FROM RED_Layouts");
//		while($row = mysqli_fetch_assoc($result))
//		{
//			$This->layout=$row['UniqueName'];
//			if ($This->layout===$layout)
//			echo '<option value="'.$This->layout.'" selected="selected">'.$This->layout.'</option>';
//			else
//			echo '<option value="'.$This->layout.'">'.$This->layout.'</option>';
//		}
//		$db->close();
        
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $result = $db->query("SELECT UniqueName FROM RED_Layouts");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $uniqueName = htmlspecialchars($row['UniqueName']);
                $selected = $uniqueName === $layout ? ' selected="selected"' : '';
                echo "<option value=\"$uniqueName\"$selected>$uniqueName</option>";
            }
        } else {
            // Handle error, e.g., log it or display a message to the user
        }
        $db->close();

		
		echo '</select>';
		echo '</form>';
		echo '<span id="msggbox_update_layout" style="display:none; height:30px"></span>';
		
	}
}