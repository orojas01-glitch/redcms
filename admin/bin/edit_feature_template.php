<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
// FEATURE TEMPLATE:
// FIND AND REPLACE 'template' WITH THE unique feature var name.

if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {
		$VarFeatures=preg_replace ( "'<[^>]+>'U", "", $_POST['VarFeatures']);
		$Query=preg_replace ( "'<[^>]+>'U", "", $_POST['Query']);
		$Language=preg_replace ( "'<[^>]+>'U", "", $_POST['Language']);
?>
<!-- The main script file -->
<script type="text/javascript">
<!--
function run_update_template (update_template)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/update_feature_template.php", 
	data: $("#update_template").serialize(),
	success: function(data) {
/*	alert (data);
	return false;*/
	if (data=='yes')
	{
	$('#msggbox_update_template').html("Slider Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_template');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_update_template').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_template');
	});
	}
	}
	});
	return false;
}
//-->



<?php
$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$result3 = $db->query("SELECT UniqueName,CompGroup FROM RED_Components ORDER BY UniqueName ASC");
$result_counter = $result3->num_rows;
while($row3 = mysqli_fetch_assoc($result3))
{
	$UniqueName=$row3['UniqueName']; 
	$CompGroup=$row3['CompGroup'];
	if ($UniqueName!=$UniqueNameMem){
		switch ($CompGroup)
		{
		case 'Y':
			echo '<!--' ."\n";
			echo 'function edit_'.strtolower($UniqueName).' (RecordID,ArtRecordID)'. "\n".'{' . "\n"; 
		
			echo '$.ajax({ ' . "\n"; 
			
			echo 'type: "POST", '. "\n"; 
			echo 'url: "/admin/bin/edit_'.strtolower($UniqueName).'.php", '. "\n"; 
			echo 'cache: false,'. "\n"; 
			echo 'data: "RecordID=" + RecordID + "&ArtRecordID=" + ArtRecordID,'. "\n"; 
			echo 'success: function(data) { '. "\n"; 
				
			echo 'if (data)'. "\n"; 
			echo '{'. "\n"; 
			//$('#edit_content_grid').hide();
			echo "$('#msggbox_edit_content').html(data)" . "\n"; 
			echo '.fadeIn(1500, function() { '. "\n"; 
			echo "$('#msggbox_edit_content');". "\n";
			echo '});'. "\n";
			echo '}'. "\n";
			echo 'else'. "\n";
			echo '{'. "\n";
			echo '$(\'#msggbox_edit_content\').html("error.")'. "\n";
			echo '.append("<p>Please try again.</p>")'. "\n";
			echo '.hide()'. "\n";
			echo '.fadeIn(1500, function() {'. "\n";
			echo '$(\'#msggbox_edit_content\');'. "\n";
			echo '});'. "\n";
			echo '}'. "\n";
			echo '}'. "\n";
			echo '});'. "\n";
			echo 'return false;'. "\n";
			echo '}'. "\n";
			echo '-->'. "\n";
		break;
		default:
			echo '<!--' ."\n";
			echo 'function edit_'.strtolower($UniqueName).' (RecordID)'. "\n".'{' . "\n"; 
		
			echo '$.ajax({ ' . "\n"; 
			
			echo 'type: "POST", '. "\n"; 
			echo 'url: "/admin/bin/edit_'.strtolower($UniqueName).'.php", '. "\n"; 
			echo 'cache: false,'. "\n"; 
			echo 'data: "RecordID=" + RecordID,'. "\n"; 
			echo 'success: function(data) { '. "\n"; 
				
			echo 'if (data)'. "\n"; 
			echo '{'. "\n"; 
			//$('#edit_content_grid').hide();
			echo "$('#msggbox_edit_content').html(data)" . "\n"; 
			echo '.fadeIn(1500, function() { '. "\n"; 
			echo "$('#msggbox_edit_content');". "\n"; 
			echo '});'. "\n"; 
			echo '}'. "\n"; 
			echo 'else'. "\n"; 
			echo '{'. "\n"; 
			echo '$(\'#msggbox_edit_content\').html("error.")'. "\n";
			echo '.append("<p>Please try again.</p>")'. "\n";
			echo '.hide()'. "\n";
			echo '.fadeIn(1500, function() {'. "\n";
			echo '$(\'#msggbox_edit_content\');'. "\n";
			echo '});'. "\n";
			echo '}'. "\n";
			echo '}'. "\n";
			echo '});'. "\n";
			echo 'return false;'. "\n";
			echo '}'. "\n";
			echo '-->'. "\n";
		break;
		}
		
	}
	
	$UniqueNameMem=$UniqueName;
	
	$result_counter = ($result_counter - 1);
}
?>
</script>
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('edit_content_grid');">Show Content</a></div>
<form id="update_template" name="update_template" class="cp" method="post" onSubmit="return run_update_template(this);">
<fieldset>
<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
        

        <?php
		echo '<div><label>Select Slider Articles:</label>';
		echo '<div class="clear-cp"></div>';
		echo '<div class="scroll">';
		
		echo '<div class="header">';
		echo '<div class="titleleft cp_checkbox">&nbsp;</div>';
		echo '<div class="titleleft cp_thumbnail"><strong>Thumbnail</strong>';
		echo '</div>';
		echo '<div class="titleleft cp_checkbox"><strong>Order</strong>';
		echo '</div>';
		echo '<div class="titleleft cp_lefttitledesc"><strong>Article Title</strong>';
		echo '</div>';
		echo '<div class="titleleft cp_component"><strong>Component Type</strong>';
		echo '</div>';
		echo '<div class="titleright cp_editico"><strong>Edit</strong>';
		echo '</div>';
		echo '</div>';
		echo '<div class="clear-cp"></div>';
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_Articles WHERE Active='Y' AND Component<>'SubMenu' AND Language='".$Language."' ORDER BY Updated DESC");
		$result_counter = $result->num_rows;
		$w=0;
		while($row = mysqli_fetch_assoc($result))
		{
			$RecordID= $row['RecordID'];
			$Title=$row['Title'];
			$Alias = $row['Alias'];
			$Alias=preg_replace('/-/','_',$Alias);
			$Component = $row['Component'];
			$BigPict = $row['BigPict'];
			
			$Features=$row[$VarFeatures];
			$templateExists = preg_match("/template/", $Features);
			if ($templateExists)
			$checked='checked="checked"';
			else
			echo '<input type="hidden" name="templateSelect['.$w.']" value="" />';
			
			echo '<div class="wrapper row">';
			echo '<label style="display:inline;">';
			echo '<div class="titleleft cp_checkbox">';
			echo '<input name="templateSelect['.$w.']" type="checkbox" '.$checked.' value="Y">';
			echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
			echo '</div>';
			
			
			
			echo '<div class="titleleft cp_thumbnail">';
			if ($BigPict)
			echo '<img src="/images/resize.php?w=57&h=41&amp;img=/images/articles/'.$BigPict.'" title="'.$Title.'">';
			else
			echo '<img src="/images/resize.php?w=57&h=41&amp;img=/images/icon-error.png" title="'.$Title.'">';
			echo '</div>';
			echo '</label>';
			echo '<div class="titleleft cp_checkbox">';
			echo '<input name="FeatureOrder['.$w.']" type="input" style="width:15px;" value="'.$FeatureOrder.'">';
			echo '</div>';
			
			echo '<div class="titleleft cp_lefttitledesc">';
			echo preg_replace('/<[^>]*>/', '', $row['Title']);
			echo '</div>';
			echo '<div class="titleleft component">'.$Component;
			echo '</div>';
			if ($templateExists){
			echo '<div class="titleright editico">';
			// CHECK IF THIS COMPONENT IS A GROUP. IF IT IS, THE FUNCTION MUST CALL 2 RECORDS ID.
			// ONE FOR THE ARTICLES TABLE, AND OTHER FOR THE SECONDARY TABLE.
			// THERE ARE 3 CASES BY DEFAULT:  FORMS, GALLERY AND SUBMENU. SUBMENU IS NOT INCLUDED.
				$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$result4 = $db->query("SELECT CompGroup FROM RED_Components WHERE UniqueName='".$Component."' LIMIT 1");
				$result_counter = $result4->num_rows;
				while($row4 = mysqli_fetch_assoc($result4))
				{
				$CompGroup=$row4['CompGroup']; 	
				}
				switch ($CompGroup)
				{
				case 'Y':
				$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$result2 = $db->query("SELECT RecordID FROM RED_C_".$Component." WHERE RefID='".$RecordID."'");
				while($row2 = mysqli_fetch_assoc($result2))
				{
					$CRecordID=$row2['RecordID'];
				}
				echo '<img src="/admin/images/ico_edit.png" onClick="edit_'.strtolower($Component).'(' .$CRecordID. ','.$RecordID.');" title="Edit" style="cursor:pointer">';
				break;
				default:
				echo '<img src="/admin/images/ico_edit.png" onClick="edit_'.strtolower($Component).'(' .$RecordID . ');" title="Edit" style="cursor:pointer">';
				break;
				}
			echo '</div>';
			}
			echo '</div>';
		$w++;
		$checked='';
		}
		
		echo '</div></div>';

		?>
        <div class="clear-cp"></div>
        <input type="hidden" name="VarFeatures" id="VarFeatures" value="<?php echo $VarFeatures ?>" />
        <input type="submit" name="submit" value="Save" id="save"/>
        <span id="msggbox_update_template" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
</form>

<?php
	$db->close();
	}
?>