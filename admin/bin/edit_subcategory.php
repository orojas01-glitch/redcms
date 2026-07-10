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
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_area_helpers.php';

$recordId = (int) red_admin_post_text('RecordID');
if ($recordId <= 0) {
    echo 'no';
    exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$row = red_admin_area_record($db->connection, 'RED_SubCategories', $recordId);
if (!$row) {
    echo 'no';
    $db->close();
    exit;
}

$RecordID = (int) $row['RecordID'];
$layout = (string) $row['Layout'];
$selectedFeatures = array_flip(array_filter(array_map('trim', explode(',', (string) $row['Features']))));
$layouts = red_admin_area_layouts($db->connection);
$features = red_admin_area_features($db->connection);
$relatedCount = red_admin_area_related_article_count($db->connection, 'SubCategories', $row['SubCategories']);
?>
<!-- The main script file -->
<script type="text/javascript">
<!--
function run_update_subcategory (update_subcategory)
{
	$.ajax({
	type: "POST",
	url: "/admin/bin/update_subcategory.php",
	data: $("#update_subcategory").serialize(),
	success: function(data) {
	if (data=='yes' || data=='updateupdateyes'|| data=='updateupdateupdateyes'|| data=='updateyes')
	{
	if (data=='updateupdateyes'|| data=='updateupdateupdateyes'|| data=='updateyes'){
	alert ('Your SubCategory name was updated.\n NOTE: \'Articles\' and \'Menu Links\' owned by the previous SubCategory were updated.');
	}
	$('#msggbox_update_subcategory').html("SubCategory Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_subcategory');
	window.location.reload();
	});
	}
	else if(data=='error')
	{
	alert ('There is a Section using the same name.  Please enter a different SubCategory Name.');
	}
	else if(data=='error2')
	{
	alert ('There is a Category using the same name.  Please enter a different SubCategory Name.');
	}
	else if(data=='error3')
	{
	alert ('There is a SubCategory using the same name.  Please enter a different SubCategory Name.');
	}
	else
	{
	$('#msggbox_update_subcategory').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_subcategory');
	});
	}
	}
	});
	return false;
}
//-->
<!--
function run_deleterecord (RecordID)
{
	$(document).ready(function(){
   $('#deleterecord_'+RecordID).click(function(){
      if(confirm("Are you sure you want to delete this Record? It can't be recovered.")){
		  $.ajax({
		type: "POST",
		url: "/admin/bin/delete_label.php",
		data: "RecordID=" + RecordID + "&T=subcategories",
		success: function(data) {
		if (data=='yes')
		{
		$('#msggbox_deleterecord').html("Record Deleted.")
		.hide()
		.fadeIn(1500, function() {
		$('#msggbox_deleterecord');
		window.location.reload();
		});
		}
		else
		{
		$('#msggbox_deleterecord').html("&nbsp; Error. Please try again.")
		.hide()
		.fadeIn(1500, function() {
		$('#msggbox_deleterecord');
		});
		}
		}
		});
		return false;
      }
      return false;
   });
});

}
//-->
</script>
<style>
label{color:#000}
</style>
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('edit_subcategory_grid');">Show SubCategories</a></div>
<form id="update_subcategory" name="update_subcategory" class="cp" method="post" onSubmit="return run_update_subcategory(this);">
<fieldset>
<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
        <div class="wrapper">
         <div class="titleleft">
                <label>SubCategory Title: <input name="Title" type="text" id="title" value="<?php echo red_admin_area_html($row['Title']); ?>" /></label>
            </div>
            <div class="titleleft">
                <label>Alias: <input name="SubCategories" type="text" id="title" value="<?php echo red_admin_area_html($row['SubCategories']); ?>" /></label>
            </div>
            <div class="titleright">
                <a href="#" id="deleterecord_<?php echo $RecordID; ?>"><img src="/admin/images/ico_trashcan.png" onClick="run_deleterecord(<?php echo $RecordID; ?>);" title="Delete Record" style="cursor:pointer"></a>
             </div>
            <div class="titleright">
                <label style="display:inline;">Active: <select name="Active">
                <option value="Y" <?php if ($row['Active']=='Y') echo 'selected="selected"'; ?>>Y</option>
                <option value="N" <?php if ($row['Active']=='N') echo 'selected="selected"'; ?>>N</option>
                </select>
                </label>
            </div>
        </div>
         <div class="wrapper">
            <div class="titleright">
                <span id="msggbox_deleterecord" style="display:none"></span>
            </div>
        </div>
        <div class="wrapper">
            <div class="titleleft">
                <label style="display:inline;">Layout:
                <select name="Layout" id="layout">
                <?php foreach ($layouts as $thislayout) { ?>
                    <option value="<?php echo red_admin_area_html($thislayout); ?>" <?php if ($thislayout === $layout) echo 'selected="selected"'; ?>><?php echo red_admin_area_html($thislayout); ?></option>
                <?php } ?>
                </select>
                </label>
            </div>
            <div class="titleright">
                <label style="display:inline;" title="Articles Limit">Articles Limit: <input name="QueryLimit" type="text" id="limit" value="<?php echo red_admin_area_html($row['QueryLimit']); ?>" /></label>
            </div>
        </div>
        <div class="wrapper">
            <div class="titleleft">
                <label>Features:
                    <select name="Features[]" size="3" multiple>
                    <?php foreach ($features as $featureName) { ?>
                        <option value="<?php echo red_admin_area_html($featureName); ?>" <?php if (isset($selectedFeatures[$featureName])) echo 'selected="selected"'; ?>><?php echo red_admin_area_html($featureName); ?></option>
                    <?php } ?>
                    </select>
                </label>
            </div>
			<div class="titleright">
                <label style="display:inline;">Access Level: <select name="AccessLevel">
                <option value="Public" <?php if ($row['AccessLevel']=='Public') echo 'selected="selected"'; ?>>Public</option>
                <option value="Private" <?php if ($row['AccessLevel']=='Private') echo 'selected="selected"'; ?>>Private</option>
                </select>
                </label>
            </div>
        </div>

         <label>Tags:
        <input name="Tags" type="text" id="tags" value="<?php echo red_admin_area_html($row['Tags']); ?>" /></label>

        <label>Long Description:
        <textarea name="Description" id="ShortDesc" cols="" rows="4"><?php echo red_admin_area_html($row['Description']); ?></textarea></label>
		<div class="wrapper">
             <div class="titleleft">
                <label>Related articles:  <?php echo $relatedCount; ?></label>
            </div>
         </div>

         <input type="hidden" name="RecordID" id="RecordID" value="<?php echo $RecordID; ?>" />
         <input type="hidden" name="CurrentSubCategory" id="CurrentSubCategory" value="<?php echo red_admin_area_html($row['SubCategories']); ?>" />
        <input type="submit" name="submit" value="Save" id="save"/> <span id="msggbox_update_subcategory" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
</form>
<?php
$db->close();
?>
