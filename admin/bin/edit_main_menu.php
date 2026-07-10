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
red_require_admin();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_menu_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$language = red_admin_menu_language();
$csrfToken = red_csrf_token();
$Title = red_admin_main_menu_title($db->connection, $language);
$LinkNavigator = red_admin_main_menu_link_options($db->connection);
$mainMenuRows = red_admin_main_menu_items($db->connection, $language);
?>
<script type="text/javascript">
<!--
$(function(){
	$("#cp_accordion dt").click(function(){$(this).next("#cp_accordion dd").slideToggle("slow").siblings("#cp_accordion dd:visible").slideUp("slow");$(this).toggleClass("active");$(this).siblings("#cp_accordion dt").removeClass("active");return false})
})
//-->
<!--
function run_deletelabel (RecordID)
{
	$(document).ready(function(){
   $('#deletelabel_'+RecordID).click(function(){
      if(confirm("Are you sure you want to delete this Menu Label?")){
         //alert('Successful Request!');
		  $.ajax({
		type: "POST",
		url: "/admin/bin/delete_label.php",
		data: "RecordID=" + encodeURIComponent(RecordID) + "&T=main&csrf_token=<?php echo rawurlencode($csrfToken); ?>",
		success: function(data) {
		//alert (data);
		//return false;
		if (data=='yes')
		{
		$('#msggbox_deletelabel').html("Main Menu Updated.")
		.hide()
		.fadeIn(1500, function() {
		$('#msggbox_deletelabel');
		window.location.reload();
		});
		}
		else
		{
		$('#msggbox_deletelabel').html("&nbsp; Error. Please try again.")
		.hide()
		.fadeIn(1500, function() {
		$('#msggbox_deletelabel');
		});
		}
		}
		});
		return false;
      } else {
         //alert('Cancelled Request');
      }
      return false;
   });
});

}
//-->
<!--
function run_update_main_menu (update_main_menu)
{
	$.ajax({
	type: "POST",
	url: "/admin/bin/update_main_menu.php",
	data: $("#update_main_menu").serialize(),
	success: function(data) {
	/*alert (data);
	return false;*/
	if (data=='yes')
	{
	$('#msggbox_update_main_menu').html("Main Menu Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_main_menu');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_update_main_menu').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_main_menu');
	});
	}
	}
	});
	return false;
}
//-->
</script>
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('edit_content_grid');">Show Content</a></div>
<form id="update_main_menu" name="update_main_menu" class="cp" method="post" onSubmit="return run_update_main_menu(this);">
<fieldset>
<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
        <div class="wrapper">
            <div>
			<label>Menu Name: <input name="Title" type="text" id="title" value="<?php echo red_admin_menu_html($Title); ?>" /></label>
            </div>
        </div>
        <label>Menu Item Manager:</label>

        <div class="wrapper">
		<div class="titleleft">
		<label style="display:inline;">New Button:
		  <input name="NewLabel" type="text" id="newlabel" /></label>
		</div>
		<div class="titleleft">
		<label style="display:inline;">Order: <input name="NewMenuOrder" type="text" id="order" /></label>
		</div>
		</div>

        <dl id="cp_accordion">

        <?php
		$f = 1;
		$w = 0;
		$z = 0;
		foreach ($mainMenuRows as $mainRow) {
			$RootRecordID = (int) ($mainRow['RecordID'] ?? 0);
			$mainLabel = red_admin_menu_html($mainRow['Label'] ?? '');
			$mainOrder = red_admin_menu_html($mainRow['MenuOrder'] ?? '');
			$mainLink = red_admin_menu_html($mainRow['Link'] ?? '');
			$mainChecked = red_admin_menu_new_window($mainRow['NewWindow'] ?? '') === '_blank' ? 'checked="checked"' : '';
        ?>
		<dt><a href="#"><?php echo $mainLabel; ?></a></dt><dd style="padding-right:10px">
		<div class="wrapper" style="background-color:#cccccc; padding:5px 5px 5px 5px">
		<div class="titleleft">
		<label style="display:inline;">Level 1 Button: <input name="MainLabel[<?php echo $f; ?>][]" type="text" id="menulabel" value="<?php echo $mainLabel; ?>" /></label>
		<input name="MainLabelRecordID[<?php echo $f; ?>][]" type="hidden" id="mainlabelrecordid" value="<?php echo $RootRecordID; ?>" />
		</div>
		<div class="titleleft">
		<label style="display:inline;">Order: <input name="MainMenuOrder[<?php echo $f; ?>][]" type="text" id="order" value="<?php echo $mainOrder; ?>" /></label>
		</div>
		<div class="titleright">
		<a href="#" id="deletelabel_<?php echo $RootRecordID; ?>"><img src="/admin/images/ico_trashcan.png" onClick="run_deletelabel(<?php echo $RootRecordID; ?>);" title="Delete Parent Label" style="cursor:pointer"></a>
		</div>
		<div class="clear"></div>
		<div class="titleleft">
		<label style="display:inline;">Link: <input name="MainLabelLink[<?php echo $f; ?>][]" type="text" style="width:240px;" id="mainmenulink_<?php echo $f; ?>" value="<?php echo $mainLink; ?>" /></label>
		</div>
		<div class="titleleft">
		<script type="text/javascript">
		<!--
		$('#LinkNavigator_<?php echo $f; ?>').bind('change', function() {
		$('#mainmenulink_<?php echo $f; ?>').val($(this).val());
		});
		-->
		</script>
		<select name="LinkNavigator" id="LinkNavigator_<?php echo $f; ?>">
		<?php echo $LinkNavigator; ?>
		</select>
		</div>
		<div class="titleleft">
		<label style="display:inline;" title="Open New Window">Open Blank <input name="MainLabelNewWindow[<?php echo $f; ?>][]" type="checkbox" <?php echo $mainChecked; ?> value="_blank" /></label>
		</div>
		</div>
		<div class="clear-cp"></div>

			<?php
			foreach (red_admin_main_menu_children($db->connection, $RootRecordID, $language, 2) as $subRow) {
				$Root2RecordID = (int) ($subRow['RecordID'] ?? 0);
				$SubRecordID = $Root2RecordID;
				$subLabel = red_admin_menu_html($subRow['Label'] ?? '');
				$subOrder = red_admin_menu_html($subRow['MenuOrder'] ?? '');
				$subLink = red_admin_menu_html($subRow['Link'] ?? '');
				$subChecked = red_admin_menu_new_window($subRow['NewWindow'] ?? '') === '_blank' ? 'checked="checked"' : '';
			?>
			<div class="wrapper2" style="margin-left:5px; padding:10px 5px 5px 28px; ">
				<div class="titleleft">
				<label style="display:inline;">Level 2 Button: <input name="SubLabel[<?php echo $f; ?>][]" type="text" id="menusublabel" value="<?php echo $subLabel; ?>" /></label>
				<input name="SubLabelRecordID[<?php echo $f; ?>][]" type="hidden" id="sublabelrecordid" value="<?php echo $SubRecordID; ?>" />
				</div>
				<div class="titleleft">
				<label style="display:inline;">Order: <input name="SubMenuOrder[<?php echo $f; ?>][]" type="text" id="order" value="<?php echo $subOrder; ?>" /></label>
				</div>
				<div class="titleright">
				<a href="#" id="deletelabel_<?php echo $SubRecordID; ?>"><img src="/admin/images/ico_trashcan.png" onClick="run_deletelabel(<?php echo $SubRecordID; ?>);" title="Delete Parent Label" style="cursor:pointer"></a>
				</div>
				<div class="clear"></div>
				<div class="titleleft">
				<label style="display:inline;">Link: <input name="SubLabelLink[<?php echo $f; ?>][]" type="text" style="width:240px;" id="submenulink_<?php echo $f; ?>_<?php echo $w; ?>" value="<?php echo $subLink; ?>" /></label>
				</div>
				<div class="titleleft">
				<script type="text/javascript">
				<!--
				$('#SubLinkNavigator_<?php echo $f; ?>_<?php echo $w; ?>').bind('change', function() {
				$('#submenulink_<?php echo $f; ?>_<?php echo $w; ?>').val($(this).val());
				});
				-->
				</script>
				<select name="SubLinkNavigator" id="SubLinkNavigator_<?php echo $f; ?>_<?php echo $w; ?>">
				<?php echo $LinkNavigator; ?>
				</select>
				</div>
				<div class="titleleft">
				<?php if ($subChecked === '') { ?>
				<input name="SubLabelNewWindow[<?php echo $f; ?>][<?php echo $w; ?>]" type="hidden" value="" />
				<?php } ?>
				<label style="display:inline;" title="Open New Window">Open Blank <input name="SubLabelNewWindow[<?php echo $f; ?>][<?php echo $w; ?>]" type="checkbox" <?php echo $subChecked; ?> value="_blank" /></label>
				</div>
				<div class="clear"></div>

				<?php
				foreach (red_admin_main_menu_children($db->connection, $SubRecordID, $language, 3) as $subSubRow) {
					$SubSubRecordID = (int) ($subSubRow['RecordID'] ?? 0);
					$subSubLabel = red_admin_menu_html($subSubRow['Label'] ?? '');
					$subSubOrder = red_admin_menu_html($subSubRow['MenuOrder'] ?? '');
					$subSubLink = red_admin_menu_html($subSubRow['Link'] ?? '');
					$subSubChecked = red_admin_menu_new_window($subSubRow['NewWindow'] ?? '') === '_blank' ? 'checked="checked"' : '';
				?>
				<div class="wrapper3"  style="background-color:#F2F2F2; margin-left:5px; margin-right:5px; padding:10px 5px 5px 28px; ">
					<div class="titleleft">
					<label style="display:inline;">Level 3 Button: <input name="SubSubLabel[<?php echo $w; ?>][]" type="text" id="menusubsublabel" value="<?php echo $subSubLabel; ?>" /></label>
					<input name="SubSubLabelRecordID[<?php echo $w; ?>][]" type="hidden" id="subsublabelrecordid" value="<?php echo $SubSubRecordID; ?>" />
					</div>
					<div class="titleleft">
					<label style="display:inline;">Order: <input name="SubSubMenuOrder[<?php echo $w; ?>][]" type="text" id="order" value="<?php echo $subSubOrder; ?>" /></label>
					</div>
					<div class="titleright">
					<a href="#" id="deletelabel_<?php echo $SubSubRecordID; ?>"><img src="/admin/images/ico_trashcan.png" onClick="run_deletelabel(<?php echo $SubSubRecordID; ?>);" title="Delete Parent Label" style="cursor:pointer"></a>
					</div>
					<div class="clear"></div>
					<div class="titleleft">
					<label style="display:inline;">Link: <input name="SubSubLabelLink[<?php echo $w; ?>][]" type="text" style="width:240px;" id="subsubmenulink_<?php echo $w; ?>_<?php echo $z; ?>" value="<?php echo $subSubLink; ?>" /></label>
					</div>
					<div class="titleleft">
					<script type="text/javascript">
					<!--
					$('#SubSubLinkNavigator_<?php echo $w; ?>_<?php echo $z; ?>').bind('change', function() {
					$('#subsubmenulink_<?php echo $w; ?>_<?php echo $z; ?>').val($(this).val());
					});
					-->
					</script>
					<select name="SubSubLinkNavigator" id="SubSubLinkNavigator_<?php echo $w; ?>_<?php echo $z; ?>">
					<?php echo $LinkNavigator; ?>
					</select>
					</div>
					<div class="titleleft">
					<?php if ($subSubChecked === '') { ?>
					<input name="SubSubLabelNewWindow[<?php echo $w; ?>][<?php echo $z; ?>]" type="hidden" value="" />
					<?php } ?>
					<label style="display:inline;" title="Open New Window">Open Blank <input name="SubSubLabelNewWindow[<?php echo $w; ?>][<?php echo $z; ?>]" type="checkbox" <?php echo $subSubChecked; ?> value="_blank" /></label>
					</div>
				</div>
				<div class="clear-cp"></div>
				<?php
					$z++;
				}
				?>

				<div class="wrapper3last" style="background-color:#F2F2F2; margin-left:5px; margin-right:5px; margin-bottom:10px; padding:10px 5px 5px 28px; ">
				<div class="titleleft">
				<label style="display:inline;">New Level 3 Button:
				<input name="NewSubSubLabel[<?php echo $w; ?>][]" type="text" id="menusubsublabel" /></label>
				<input name="NewSubLabelRecordID[<?php echo $w; ?>][]" type="hidden" id="newsublabelrecordid" value="<?php echo $Root2RecordID; ?>" />
				</div>
				<div class="titleleft">
				<label style="display:inline;">Order: <input name="NewSubSubMenuOrder[<?php echo $w; ?>][]" type="text" id="order" /></label>
				</div>
				<div class="clear"></div>
				<div class="titleleft">
				<label style="display:inline;">Link:
				<input name="NewSubSubLabelLink[<?php echo $w; ?>][]" type="text" style="width:240px;" id="newsubsublabellink_<?php echo $w; ?>_<?php echo $z; ?>" /></label>
				</div>
				<div class="titleleft">
				<script type="text/javascript">
				<!--
				$('#NewSubSubNavigator_<?php echo $w; ?>_<?php echo $z; ?>').bind('change', function() {
				$('#newsubsublabellink_<?php echo $w; ?>_<?php echo $z; ?>').val($(this).val());
				});
				-->
				</script>
				<select name="NewSubSubNavigator" id="NewSubSubNavigator_<?php echo $w; ?>_<?php echo $z; ?>">
				<?php echo $LinkNavigator; ?>
				</select>
				</div>
				<div class="titleleft">
				<label style="display:inline;" title="Open New Window">Open Blank <input name="NewSubSubLabelNewWindow[<?php echo $w; ?>][]" type="checkbox" value="_blank" /></label>
				</div>
				</div>

			</div>
			<div class="clear-cp"></div>
			<?php
				$w++;
			}
			?>

			<div class="wrapper2last" style="margin-left:5px; margin-bottom:10px; padding:10px 5px 5px 28px; ">
			<div class="titleleft">
			<label style="display:inline;">New Level 2 Button:
			<input name="NewSubLabel[<?php echo $f; ?>][]" type="text" id="newsublabel" /></label>
			<input name="NewMainLabelRecordID[<?php echo $f; ?>][]" type="hidden" id="newmainlabelrecordid" value="<?php echo $RootRecordID; ?>" />
			</div>
			<div class="titleleft">
			<label style="display:inline;">Order: <input name="NewSubMenuOrder[<?php echo $f; ?>][]" type="text" id="order" /></label>
			</div>
			<div class="clear"></div>
			<div class="titleleft">
			<label style="display:inline;">Link:
			<input name="NewSubLabelLink[<?php echo $f; ?>][]" type="text" style="width:240px;" id="newmenulink_<?php echo $f; ?>" /></label>
			</div>
			<div class="titleleft">
			<script type="text/javascript">
			<!--
			$('#NewLinkNavigator_<?php echo $f; ?>').bind('change', function() {
			$('#newmenulink_<?php echo $f; ?>').val($(this).val());
			});
			-->
			</script>
			<select name="NewLinkNavigator" id="NewLinkNavigator_<?php echo $f; ?>">
			<?php echo $LinkNavigator; ?>
			</select>
			</div>
			<div class="titleleft">
			<label style="display:inline;" title="Open New Window">Open Blank <input name="NewSubLabelNewWindow[<?php echo $f; ?>][]" type="checkbox" value="_blank" /></label>
			</div>
			</div>
		</dd>
		<?php
			$f++;
		}
		?>

        </dl>


        <div class="clear-cp"></div>
        <?php echo red_csrf_input(); ?>
        <input type="hidden" name="CurTitle" id="CurTitle" value="<?php echo red_admin_menu_html($Title); ?>" />
        <input type="hidden" name="Language" id="Language" value="<?php echo red_admin_menu_html($language); ?>" />
        <input type="submit" name="submit" value="Save" id="save"/>
        <span id="msggbox_update_main_menu" style="display:none"></span>
        <span id="msggbox_deletelabel" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
</form>
<?php
$db->close();
?>
