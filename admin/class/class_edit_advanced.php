<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_advanced_helpers.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_list_ui_helpers.php";
red_start_session();
red_require_admin(); ?>
<?php
#[\AllowDynamicProperties]
class editadvanced
{
	public function advanced_form()
	{
		
		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
		if (red_admin_can_manage_users()) {
			echo 'function edit_admin_users ()'. "\n".'{' . "\n";
			echo '$.ajax({ '. "\n";
			echo 'type: "POST", '. "\n";
			echo 'url: "/admin/bin/edit_admin_users.php", '. "\n";
			echo 'success: function(data) { '. "\n";
			echo 'if (data) {'. "\n";
			echo '$(\'#edit_advanced_grid\').hide();'. "\n";
			echo '$(\'#msggbox_edit_advanced\').html(data).fadeIn(300);'. "\n";
			echo '} else {'. "\n";
			echo '$(\'#msggbox_edit_advanced\').html("Error. Please try again.").fadeIn(300);'. "\n";
			echo '}'. "\n";
			echo '}'. "\n";
			echo '});'. "\n";
			echo 'return false;'. "\n";
			echo '}'. "\n";
		}
		if (red_admin_can_manage_site()) {
			echo 'function edit_layout_builder (LayoutID)'. "\n".'{' . "\n";
			echo '$.ajax({ '. "\n";
			echo 'type: "POST", '. "\n";
			echo 'url: "/admin/bin/edit_layout_builder.php", '. "\n";
			echo 'data: {LayoutID: LayoutID || ""}, '. "\n";
			echo 'success: function(data) { '. "\n";
			echo 'if (data) {'. "\n";
			echo '$(\'#edit_advanced_grid\').hide();'. "\n";
			echo '$(\'#msggbox_edit_advanced\').html(data).show();'. "\n";
			echo 'if (window.RedLayoutBuilder) { window.RedLayoutBuilder.init(document.getElementById("msggbox_edit_advanced")); }'. "\n";
			echo '} else {'. "\n";
			echo '$(\'#msggbox_edit_advanced\').html("The Layout Builder could not be opened.").show();'. "\n";
			echo '}'. "\n";
			echo '},'. "\n";
			echo 'error: function() {'. "\n";
			echo '$(\'#msggbox_edit_advanced\').html("The Layout Builder could not be opened. Please try again.").show();'. "\n";
			echo '}'. "\n";
			echo '});'. "\n";
			echo 'return false;'. "\n";
			echo '}'. "\n";
		}
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
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $rows = red_admin_advanced_list_rows($db->connection, red_admin_area_language());
        $db->close();
        $canManageUsers = red_admin_can_manage_users();
        $canManageSite = red_admin_can_manage_site();
        if (!$canManageSite) {
            $rows = array_values(array_filter($rows, static function ($row) {
                return ($row['Item'] ?? '') !== 'Website_Red_Sphere_Credit';
            }));
        }
        $itemCount = count($rows) + ($canManageUsers ? 1 : 0) + ($canManageSite ? 2 : 0);

        echo '<div class="container_12 cp_padtop red-admin-area-list-container"><div class="wrapper"><article class="grid_12 cp_admin"><div class="red-admin-area-list-shell">';
		echo '<form id="editadvanced" name="editadvanced" class="cp red-admin-area-list-form"><fieldset>';
		echo '<div class="red-admin-area-list red-admin-area-list--advanced" data-red-admin-list="advanced" role="table" aria-label="Advanced settings">';
		echo '<div class="red-admin-area-list__header" role="row">';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--primary" role="columnheader">Advanced item <span class="red-admin-area-list__count">'.red_admin_list_ui_html(red_admin_list_ui_item_count($itemCount)).'</span></div>';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--action" role="columnheader">Action</div>';
		echo '</div>';

			if ($canManageUsers) {
				echo '<div class="red-admin-area-list__row" role="row">';
				echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--primary" role="cell"><span class="red-admin-area-list__title">Administrator Users</span></div>';
				echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--action" role="cell">'.red_admin_list_ui_action_button('edit_admin_users();', 'Edit Administrator Users').'</div>';
				echo '</div>';
			}

			if ($canManageSite) {
				echo '<div class="red-admin-area-list__row" role="row">';
				echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--primary" role="cell"><span class="red-admin-area-list__title">Themes</span></div>';
				echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--action" role="cell">'.red_admin_list_ui_action_link('/admin/bin/theme_preview.php', 'Open Themes activation and preview').'</div>';
				echo '</div>';
				echo '<div class="red-admin-area-list__row" role="row">';
				echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--primary" role="cell"><span class="red-admin-area-list__title">Layout Builder</span><span class="red-admin-area-list__meta">Create reusable row and column structures</span></div>';
				echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--action" role="cell">'.red_admin_list_ui_action_button('edit_layout_builder();', 'Open Layout Builder').'</div>';
				echo '</div>';
			}

        if ($itemCount === 0) {
            echo '<div class="red-admin-area-list__empty" role="row"><div role="cell"><strong>No advanced settings available</strong><span>Your account does not currently expose advanced site controls.</span></div></div>';
        }
        foreach($rows as $row)
        {
			$ItemText=red_admin_text(preg_replace('/\_/',' ',$row['Item'] ?? ''));
			if (($row['Item'] ?? '') === 'Website_Red_Sphere_Credit') {
				$ItemText = 'Red Sphere Website Credit';
			}
			$Item=red_admin_advanced_html($ItemText !== '' ? $ItemText : 'Untitled setting');
            $RecordID=(int) ($row['RecordID'] ?? 0);

            $editLabel = 'Edit '.($ItemText !== '' ? $ItemText : 'Untitled setting');
            echo '<div class="red-admin-area-list__row" role="row">';
            echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--primary" role="cell"><span class="red-admin-area-list__title">'.$Item.'</span></div>';
            echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--action" role="cell">'.red_admin_list_ui_action_button('edit_advanced('.$RecordID.');', $editLabel).'</div>';
			echo '</div>';
        }

		echo '</div></fieldset></form>';
		//echo '<form id="addsection" class="form" name="addsection" method="post" onSubmit="return addsections(this);">';
		//echo '</form>';
		echo '</div></article></div></div>';
       
	}
}
