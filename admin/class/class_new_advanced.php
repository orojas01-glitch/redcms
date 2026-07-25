<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(); ?>
<?php
#[\AllowDynamicProperties]
class newadvanced
{
	public function advanced_item($Language)
	{
		?>
        <!-- The main script file -->
<script type="text/javascript">
<!--
function run_insert_advanced (insert_advanced)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/insert_advanced.php", 
	data: $("#insert_advanced").serialize(),
	success: function(data) {
	/*alert (data);
	return false;*/
	if (data=='yes' || data=='yesyesyesyesyesyes' || data=='yesyesyesyesyesyesyes')
	{
	$('#msggbox_insert_advanced').html("Language Items Added.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_advanced');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_insert_advanced').html("Error. Language exists.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_advanced');
	});
	}
	}
	});
	return false;
}
//-->
</script>
<form id="insert_advanced" name="insert_advanced" class="cp" method="post" onSubmit="return run_insert_advanced(this);">
<fieldset>
<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
        <div class="wrapper">
            <div class="titleleft">
            	<label>New Language ID: <input name="Language" type="text" id="Language" value="" maxlength="2" /></label>
            </div>
        </div>
        <input type="submit" name="submit" value="Save" id="save"/> <span id="msggbox_insert_advanced" style="display:none"></span>
        </div>

		
        
        </article>
    </div>
</div>
</fieldset>
<?php echo red_csrf_input(); ?>
</form>
<?php
	}
}
?>
