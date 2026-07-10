<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/admin_area_helpers.php";
red_start_session();
red_require_admin(); ?>
<?php
#[\AllowDynamicProperties]
class newsubcategory
{
	public function subcategory_form($language)
	{
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $layoutOptions = red_admin_area_layouts($db->connection);
        $featureOptions = red_admin_area_features($db->connection);
		?>
        <!-- The main script file -->
<script type="text/javascript">
<!--
function run_insert_subcategory (insert_subcategory)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/insert_subcategory.php", 
	data: $("#insert_subcategory").serialize(),
	success: function(data) {
	//alert (data);
	//return false;
	if (data=='yes')
	{
	$('#msggbox_insert_subcategory').html("SubCategory Added.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_subcategory');
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
	$('#msggbox_insert_subcategory').html("Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_subcategory');
	});
	}
	}
	});
	return false;
}
//-->
</script>
<form id="insert_subcategory" name="insert_subcategory" class="cp" method="post" onSubmit="return run_insert_subcategory(this);">
<fieldset>
<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
        <div class="wrapper">
            <div class="titleleft">
            	<label>SubCategory: <input name="SubCategories" type="text" id="title" value="" /></label>
            </div>
            <div class="titleright">
            	<label style="display:inline;">Active: <select name="Active">
                <option value="Y">Y</option>
                <option value="N">N</option>
                </select>
                </label>
            </div>
        </div>
        <div class="wrapper">
            <div class="titleleft">
                <label style="display:inline;">Layout:
                
                <?php
                echo '<select name="Layout" id="layout">';
                foreach ($layoutOptions as $layoutOption) {
                    $layoutOption = red_admin_area_html($layoutOption);
                    echo '<option value="' . $layoutOption . '">' . $layoutOption . '</option>';
                }
                echo '</select>';
				?>
                </label>  
            </div>
            <div class="titleright">
                <label style="display:inline;" title="Articles Limit">Articles Limit: <input name="QueryLimit" type="text" id="limit" value="100" /></label>
            </div>
            
        </div>
        <div class="wrapper">
            <div class="titleleft">
            	<label>Features:
                    <select name="Features[]" size="3" multiple>
                    <?php
                    foreach ($featureOptions as $featureOption)
                    {
                        $featureOption = red_admin_area_html($featureOption);
                        echo '<option value="'.$featureOption.'">'.$featureOption.'</option>';
                    }
                    ?>
                    </select>
                </label>
            </div>
			<div class="titleright">
            	<label style="display:inline;">Access Level: <select name="AccessLevel">
                <option value="Public">Public</option>
                <option value="Private">Private</option>
                </select>
                </label> 
            </div>
        </div>
         
         <label>Tags:
        <input name="Tags" type="text" id="tags" value="" /></label>
                 
        <label>Long Description:
        <textarea name="Description" id="ShortDesc" cols="" rows="4"></textarea></label>
		
        <input type="submit" name="submit" value="Save" id="save"/> <span id="msggbox_insert_subcategory" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
<input type="hidden" name="Language" id="Language" value="<?php echo red_admin_area_html($language)?>" />
</form>
<?php
        $db->close();
	}
}
?>
