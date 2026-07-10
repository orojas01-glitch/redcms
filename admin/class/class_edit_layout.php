<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_area_helpers.php";
red_start_session();
red_require_admin();

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
		echo '<input type="hidden" name="countpage" id="countpage" value="'.red_admin_area_html($countpage).'" />';
		echo '<input type="hidden" name="sections" id="section" value="'.red_admin_area_html($section).'" />';
		echo '<input type="hidden" name="categories" id="category" value="'.red_admin_area_html($category).'" />';
		echo '<input type="hidden" name="subcategories" id="subcategory" value="'.red_admin_area_html($subcategory).'" />';
		echo '<input type="hidden" name="article" id="article" value="'.red_admin_area_html($article).'" />';
		echo '<select name="Layout" id="layout" onChange="return run_update_layout(update_layout);">';
		
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        foreach (red_admin_area_layouts($db->connection) as $uniqueName) {
            $selected = red_admin_text($uniqueName) === red_admin_text($layout) ? ' selected="selected"' : '';
            $uniqueName = red_admin_area_html($uniqueName);
            echo '<option value="'.$uniqueName.'"'.$selected.'>'.$uniqueName.'</option>';
        }
        $db->close();

		
		echo '</select>';
		echo '</form>';
		echo '<span id="msggbox_update_layout" style="display:none; height:30px"></span>';
		
	}
}
