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
red_start_session(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php
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
function run_update_slider (update_slider)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/update_feature_slider.php", 
	data: $("#update_slider").serialize(),
	success: function(data) {
/*	alert (data);
	return false;*/
	if (data=='yes')
	{
	$('#msggbox_update_slider').html("Slider Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_slider');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_update_slider').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_slider');
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
$UniqueNameMem = "";
$checked = "";        
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
<form id="update_slider" name="update_slider" class="cp" method="post" onSubmit="return run_update_slider(this);">
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
		echo '<div class="titleleft cp_checkbox"><strong>#Pos</strong>';
		echo '</div>';
		echo '<div class="titleleft cp_lefttitledesc"><strong>Article Title</strong>';
		echo '</div>';
		
		echo '<div class="titleleft cp_component"><strong>Slider Description</strong>';
		echo '</div>';
		echo '<div class="titleright cp_editico"><strong>Edit</strong>';
		echo '</div>';
		echo '</div>';
		echo '<div class="clear-cp"></div>';
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_Articles WHERE Active='Y' AND Component='Article' AND Language='".$Language."' ORDER BY Updated DESC");
		$result_counter = $result->num_rows;
		$w=0;
		while($row = mysqli_fetch_assoc($result))
		{
			$RecordID= $row['RecordID'];
			$Title=$row['Title'];
			$Alias = $row['Alias'];
			$Alias=preg_replace('/-/','_',$Alias);
			$Component = $row['Component'];
			$SliderDesc = $row['SliderDesc'];
			$BigPict = $row['BigPict'];
			$FeatureOrder = $row[$VarFeatures.'_Order'];
			
			$Features=$row[$VarFeatures];
			$sliderExists = preg_match("/slider/", $Features);
			if ($sliderExists)
			$checked='checked="checked"';
			else
			echo '<input type="hidden" name="sliderSelect['.$w.']" value="" />';
			
			echo '<div class="wrapper bottomline">';
			echo '<label style="display:inline;">';
			echo '<div class="titleleft cp_checkbox">';
			echo '<input name="sliderSelect['.$w.']" type="checkbox" '.$checked.' value="Y">';
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
			echo $SliderDesc;
			echo '</div>';
			echo '<div class="titleleft component">';
			echo $row['Title'];
			echo '</div>';
			echo '<div class="titleleft component">';
			echo preg_replace('/<[^>]*>/', '', $row['SliderDesc']);
			echo '</div>';
			if ($sliderExists){
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
        <span id="msggbox_update_slider" style="display:none"></span>
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