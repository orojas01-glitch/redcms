<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_area_helpers.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_list_ui_helpers.php";
red_start_session();
red_require_admin(); ?>
<?php
#[\AllowDynamicProperties]
class editcategory
{
	public function category_form()
	{
		
		echo '<script type="text/javascript">'. "\n";
		echo '<!--' ."\n";
		echo 'function editcategories (RecordID)'. "\n".'{' . "\n"; 
		//echo 'alert (RecordID);'. "\n";
		//echo 'return false;'. "\n";
		echo '$.ajax({ '. "\n";
		echo'type: "POST", '. "\n";
		echo 'url: "/admin/bin/edit_category.php", '. "\n";
		echo 'data: "RecordID=" + RecordID, '. "\n";
		echo 'success: function(data) { '. "\n";
		echo 'if (data)'. "\n"; 
		echo '{'. "\n";
		echo '$(\'#edit_category_grid\').hide();'. "\n";
		echo '$(\'#msggbox_edit_category\').html(data)'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_edit_category\');'. "\n";
		echo '});'. "\n";
		echo '}'. "\n";
		echo 'else'. "\n"; 
		echo '{'. "\n";
		echo '$(\'#msggbox_edit_category\').html("Error. Please try again.")'. "\n";
		echo '.fadeIn(1500, function() {'. "\n";
		echo '$(\'#msggbox_edit_category\');'. "\n";
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
        $rows = red_admin_area_list_rows($db->connection, 'RED_Categories', red_admin_area_language());
        $db->close();

        echo '<div class="container_12 cp_padtop red-admin-area-list-container"><div class="wrapper"><article class="grid_12 cp_admin"><div class="red-admin-area-list-shell">';
		echo '<form id="editcategory" name="editcategory" class="cp red-admin-area-list-form"><fieldset>';
		echo '<div class="red-admin-area-list red-admin-area-list--structure" data-red-admin-list="categories" role="table" aria-label="Categories">';
		echo '<div class="red-admin-area-list__header" role="row">';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--primary" role="columnheader">Category <span class="red-admin-area-list__count">'.red_admin_list_ui_html(red_admin_list_ui_item_count(count($rows))).'</span></div>';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--layout" role="columnheader">Layout</div>';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--status" role="columnheader">Status</div>';
		echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--action" role="columnheader">Action</div>';
		echo '</div>';

        if (count($rows) === 0) {
            echo '<div class="red-admin-area-list__empty" role="row"><div role="cell"><strong>No categories yet</strong><span>Use Add Category when this site needs another content level.</span></div></div>';
        }
        foreach($rows as $row)
        {
            $TitleText=red_admin_text($row['Title'] ?? '');
			$Title=red_admin_area_html($TitleText !== '' ? $TitleText : 'Untitled category');
            $LayoutText=red_admin_text($row['Layout'] ?? '');
            $Layout=red_admin_area_html($LayoutText !== '' ? $LayoutText : 'Not assigned');
            $Active=red_admin_text($row['Active'] ?? '');
            $RecordID=(int) ($row['RecordID'] ?? 0);
            $ParentTitleText=red_admin_text($row['ParentTitle'] ?? '');
            $ParentAliasText=red_admin_text($row['ParentAlias'] ?? '');
            $ParentMeta=$ParentTitleText !== ''
                ? 'Under '.red_admin_area_html($ParentTitleText).' <span aria-hidden="true">/</span> '.red_admin_area_html($ParentAliasText)
                : 'Parent Section required';

            $editLabel = 'Edit Category: '.($TitleText !== '' ? $TitleText : 'Untitled category');
            echo '<div class="red-admin-area-list__row" role="row">';
            echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--primary" role="cell"><span class="red-admin-area-list__title">'.$Title.'</span><span class="red-admin-area-list__meta'.($ParentTitleText === '' ? ' red-admin-area-list__meta--warning' : '').'">'.$ParentMeta.'</span></div>';
            echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--layout" role="cell" data-label="Layout"><span class="red-admin-area-list__layout">'.$Layout.'</span></div>';
            echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--status" role="cell" data-label="Status">'.red_admin_list_ui_status($Active).'</div>';
            echo '<div class="red-admin-area-list__cell red-admin-area-list__cell--action" role="cell">'.red_admin_list_ui_action_button('editcategories('.$RecordID.');', $editLabel).'</div>';
			echo '</div>';
        }

		echo '</div></fieldset></form>';
		//echo '<form id="addsection" class="form" name="addsection" method="post" onSubmit="return addsections(this);">';
		//echo '</form>';
		echo '</div></article></div></div>';
       
	}
}
