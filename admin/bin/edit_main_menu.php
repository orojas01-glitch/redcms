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
		$RecordID = isset($_POST['RecordID']) ? preg_replace("'<[^>]+>'U", "", $_POST['RecordID']) : '';
    $ArtRecordID = isset($_POST['ArtRecordID']) ? preg_replace("'<[^>]+>'U", "", $_POST['ArtRecordID']) : '';
    $VarPosition = isset($_POST['VarPosition']) ? preg_replace("'<[^>]+>'U", "", $_POST['VarPosition']) : '';
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		//echo "SELECT * FROM RED_Menu WHERE RecordID='".$RecordID."' AND RootOrder='1' ORDER BY RootOrder ASC limit 1";
		$result = $db->query("SELECT * FROM RED_Menu WHERE RootOrder='1' AND Language='".language."' ORDER BY RootOrder ASC limit 1");
		$result_counter = $result->num_rows;
		if($result->num_rows > 0) 
		{
		
		$info = mysqli_fetch_assoc($result); 
		$Title = $info['Title'];
		
		}
		
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
		data: "RecordID=" + RecordID + "&T=main",
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
            	<label>Menu Name: <input name="Title" type="text" id="title" value="<?php echo $Title ?>" /></label>
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
		// CREATE LINKNAVIGATOR LINKS
$LinkNavigator = '<option value="">Select a link from available pages of the website...</option>';
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$resultNav0 = $db->query("SELECT * FROM RED_Sections WHERE Active='Y' ORDER BY Sections ASC");

while ($rowNav0 = mysqli_fetch_assoc($resultNav0)) {
    $section = $rowNav0['Sections'];
    $sectionVal = ($section == 'home') ? '' : '/' . $section;
    $LinkNavigator .= '<option value="'.$sectionVal.'/">'.$sectionVal.'/</option>';
    
    // Articles with no categories or subcategories
    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
    $resultNav1 = $db->query("SELECT * FROM RED_Articles WHERE Sections='".$section."' AND Categories='' AND SubCategories='' ORDER BY Updated DESC");
    while ($rowNav1 = mysqli_fetch_assoc($resultNav1)) {
        $alias = $rowNav1['Alias'];
        $LinkNavigator .= '<option value="'.$sectionVal.'/'.$alias.'">'.$sectionVal.'/'.$alias.'</option>';
    }
    
    // Categories
    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
    $resultNav3 = $db->query("SELECT * FROM RED_Categories WHERE Active='Y' ORDER BY Categories ASC");
    while ($rowNav3 = mysqli_fetch_assoc($resultNav3)) {
        $category = $rowNav3['Categories'];
        $LinkNavigator .= '<option value="'.$sectionVal.'/'.$category.'/">'.$sectionVal.'/'.$category.'/</option>';
        
        // Articles in a category without subcategories
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $resultNav4 = $db->query("SELECT * FROM RED_Articles WHERE Sections='".$section."' AND Categories='".$category."' AND SubCategories='' ORDER BY Updated DESC");
        while ($rowNav4 = mysqli_fetch_assoc($resultNav4)) {
            $alias = $rowNav4['Alias'];
            $LinkNavigator .= '<option value="'.$sectionVal.'/'.$category.'/'.$alias.'">'.$sectionVal.'/'.$category.'/'.$alias.'</option>';
        }
        
        // SubCategories
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $resultNav5 = $db->query("SELECT * FROM RED_SubCategories WHERE Active='Y' ORDER BY SubCategories ASC");
        while ($rowNav5 = mysqli_fetch_assoc($resultNav5)) {
            $subCategory = $rowNav5['SubCategories'];
            $LinkNavigator .= '<option value="'.$sectionVal.'/'.$category.'/'.$subCategory.'/">'.$sectionVal.'/'.$category.'/'.$subCategory.'/</option>';
            
            // Articles in a subcategory
            $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
            $resultNav6 = $db->query("SELECT * FROM RED_Articles WHERE Sections='".$section."' AND Categories='".$category."' AND SubCategories='".$subCategory."' ORDER BY Updated DESC");
            while ($rowNav6 = mysqli_fetch_assoc($resultNav6)) {
                $alias = $rowNav6['Alias'];
                $LinkNavigator .= '<option value="'.$sectionVal.'/'.$category.'/'.$subCategory.'/'.$alias.'">'.$sectionVal.'/'.$category.'/'.$subCategory.'/'.$alias.'</option>';
            }
        }
    }
}
// END LINKNAVIGATOR LINKS
		
		/////FIRST LEVEL/////
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		//echo "SSELECT * FROM RED_Menu WHERE RootOrder='1' AND Language='".language."' ORDER BY MenuOrder ASC";
		$result2 = $db->query("SELECT * FROM RED_Menu WHERE RootOrder='1' AND Language='".language."' ORDER BY MenuOrder ASC");
		$result2_counter =$result2->num_rows;
		//echo ('result nav='.$result2_counter.'<br/>');
		$f=1;
		$w=0;
		$z=0;
		while($row = mysqli_fetch_assoc($result2))
		{
			$RootRecordID=$row['RecordID'];
			echo ('<dt><a href="#">' .$row['Label'] . '</a></dt><dd style="padding-right:10px">');
			echo ('<div class="wrapper" style="background-color:#cccccc; padding:5px 5px 5px 5px">');
			echo ('<div class="titleleft">');
			echo('<label style="display:inline;">Level 1 Button: <input name="MainLabel['.$f.'][]" type="text" id="menulabel" value="' .$row['Label'] . '" /></label>');
			echo('<input name="MainLabelRecordID['.$f.'][]" type="hidden" id="mainlabelrecordid" value="' .$row['RecordID'] . '" />');
			echo ('</div>');
			echo ('<div class="titleleft">');
			echo ('<label style="display:inline;">Order: <input name="MainMenuOrder['.$f.'][]" type="text" id="order" value="' .$row['MenuOrder'] . '" /></label>');
			echo ('</div>');
			echo ('<div class="titleright">');
			echo ('<a href="#" id="deletelabel_' .$row['RecordID'] . '"><img src="/admin/images/ico_trashcan.png" onClick="run_deletelabel(' .$row['RecordID'] . ');" title="Delete Parent Label" style="cursor:pointer"></a>');
			echo ('</div>');
			echo ('<div class="clear"></div>');
			
			if ($row['Link']){
			$Link=$row['Link'];
			}
            global $checked;
			if ($row['NewWindow']){
			$checked='checked="checked"';
			}
			
			echo ('<div class="titleleft">');
			echo('<label style="display:inline;">Link: <input name="MainLabelLink['.$f.'][]" type="text" style="width:240px;" id="mainmenulink_'.$f.'" value="' .$Link . '" /></label>');
			echo ('</div>'); 
			echo ('<div class="titleleft">');
			echo '<script type="text/javascript">'. "\n";
			echo '<!--' ."\n";
			echo '$(\'#LinkNavigator_'.$f.'\').bind(\'change\', function() {'. "\n";
			echo '$(\'#mainmenulink_'.$f.'\').val($(this).val());'. "\n";
			echo '});'. "\n";
			echo '-->'. "\n";
			echo '</script>';
			echo('<select name="LinkNavigator" id="LinkNavigator_'.$f.'">');
			echo ($LinkNavigator);
			echo('</select>');
			echo ('</div>');
			echo ('<div class="titleleft">');
			echo ('<label style="display:inline;" title="Open New Window">Open Blank <input name="MainLabelNewWindow['.$f.'][]" type="checkbox" '.$checked.' value="_blank" /></label>');
			echo ('</div>');
			echo ('</div>'); 
			echo ('<div class="clear-cp"></div>');
			
				/////SECOND LEVEL/////
				//echo "SELECT * FROM RED_Menu WHERE Parent='" . $row['RecordID'] . "' AND RootOrder <> '1' AND Language='".language."' ORDER BY MenuOrder ASC<br/>";
				$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$result3 = $db->query("SELECT * FROM RED_Menu WHERE Parent='" . $row['RecordID'] . "' AND RootOrder <> '1' AND Language='".language."' ORDER BY MenuOrder ASC");
					
				$result3_counter =$result3->num_rows;
				//echo ('result3 sub nav='.$result3_counter.'<br/>');
				
				
						
					while($row = mysqli_fetch_assoc($result3))
						{
							$Root2RecordID=$row['RecordID'];
							$checked=$row['NewWindow'];
							$SubRecordID=$row['RecordID'];
							//start second level//
							echo ('<div class="wrapper2" style="margin-left:5px; padding:10px 5px 5px 28px; ">');
								echo ('<div class="titleleft">');
								echo('<label style="display:inline;">Level 2 Button: <input name="SubLabel['.$f.'][]" type="text" id="menusublabel" value="' .$row['Label'] . '" /></label>');
								echo('<input name="SubLabelRecordID['.$f.'][]" type="hidden" id="sublabelrecordid" value="' .$SubRecordID . '" />');
								echo ('</div>');
								echo ('<div class="titleleft">');
								echo ('<label style="display:inline;">Order: <input name="SubMenuOrder['.$f.'][]" type="text" id="order" value="' .$row['MenuOrder'] . '" /></label>');
								echo ('</div>');
								echo ('<div class="titleright">');
								echo ('<a href="#" id="deletelabel_' .$row['RecordID'] . '"><img src="/admin/images/ico_trashcan.png" onClick="run_deletelabel(' .$row['RecordID'] . ');" title="Delete Parent Label" style="cursor:pointer"></a>');
								echo ('</div>');
							
								// GET THE LINK FOR THIS LABEL. 
								
								if ($row['Link']){
								$Link=$row['Link'];
								}
							
								echo ('<div class="clear"></div>');
								echo ('<div class="titleleft">');
								echo('<label style="display:inline;">Link: <input name="SubLabelLink['.$f.'][]" type="text" style="width:240px;" id="submenulink_'.$f.'_'.$w.'" value="' .$Link . '" /></label>');
								echo ('</div>');
								echo ('<div class="titleleft">');
								echo '<script type="text/javascript">'. "\n";
								echo '<!--' ."\n";
								echo '$(\'#SubLinkNavigator_'.$f.'_'.$w.'\').bind(\'change\', function() {'. "\n";
								echo '$(\'#submenulink_'.$f.'_'.$w.'\').val($(this).val());'. "\n";
								echo '});'. "\n";
								echo '-->'. "\n";
								echo '</script>';
								echo('<select name="SubLinkNavigator" id="SubLinkNavigator_'.$f.'_'.$w.'">');
								echo ($LinkNavigator);
								echo('</select>');
								echo ('</div>');
								echo ('<div class="titleleft">');
								if ($checked==='_blank')
								$checked='checked="checked"';
								else
								echo('<input name="SubLabelNewWindow['.$f.']['.$w.']" type="hidden" value="" />');
								
								echo ('<label style="display:inline;" title="Open New Window">Open Blank <input name="SubLabelNewWindow['.$f.']['.$w.']" type="checkbox" '.$checked.' value="_blank" /></label>');
								echo ('</div>');
								echo ('<div class="clear"></div>');
							
								/////THIRD LEVEL/////
								$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
								$result4 =  $db->query("SELECT * FROM RED_Menu WHERE Parent='" . $row['RecordID'] . "' AND RootOrder <> '1' AND Language='".language."' AND RootOrder <> '2' ORDER BY MenuOrder ASC");
								//echo "SELECT * FROM RED_C_Menu WHERE RefID='".$recordid."' AND SubCategory='" . $row['SubCategory'] . "' AND RootOrder <> '1' ORDER BY MenuOrder ASC<br/>";	
								$result4_counter = $result4->num_rows;
								//echo ('result sub nav='.$result3_counter.'<br/>');
								
										
									while($row = mysqli_fetch_assoc($result4))
										{
											$checked=$row['NewWindow'];
											$SubSubRecordID=$row['RecordID'];
											//start third level//
											echo ('<div class="wrapper3"  style="background-color:#F2F2F2; margin-left:5px; margin-right:5px; padding:10px 5px 5px 28px; ">');
												echo ('<div class="titleleft">');
												echo('<label style="display:inline;">Level 3 Button: <input name="SubSubLabel['.$w.'][]" type="text" id="menusubsublabel" value="' .$row['Label'] . '" /></label>');
												echo('<input name="SubSubLabelRecordID['.$w.'][]" type="hidden" id="subsublabelrecordid" value="' .$SubSubRecordID . '" />');
												echo ('</div>');
												echo ('<div class="titleleft">');
												echo ('<label style="display:inline;">Order: <input name="SubSubMenuOrder['.$w.'][]" type="text" id="order" value="' .$row['MenuOrder'] . '" /></label>');
												echo ('</div>');
												echo ('<div class="titleright">');
												echo ('<a href="#" id="deletelabel_' .$row['RecordID'] . '"><img src="/admin/images/ico_trashcan.png" onClick="run_deletelabel(' .$row['RecordID'] . ');" title="Delete Parent Label" style="cursor:pointer"></a>');
												echo ('</div>');
											
												// GET THE LINK FOR THIS LABEL. 
												
												if ($row['Link']){
												$Link=$row['Link'];
												}
											
												echo ('<div class="clear"></div>');
												echo ('<div class="titleleft">');
												echo('<label style="display:inline;">Link: <input name="SubSubLabelLink['.$w.'][]" type="text" style="width:240px;" id="subsubmenulink_'.$w.'_'.$z.'" value="' .$Link . '" /></label>');
												echo ('</div>');
												echo ('<div class="titleleft">');
												echo '<script type="text/javascript">'. "\n";
												echo '<!--' ."\n";
												echo '$(\'#SubSubLinkNavigator_'.$w.'_'.$z.'\').bind(\'change\', function() {'. "\n";
												echo '$(\'#subsubmenulink_'.$w.'_'.$z.'\').val($(this).val());'. "\n";
												echo '});'. "\n";
												echo '-->'. "\n";
												echo '</script>';
												echo('<select name="SubSubLinkNavigator" id="SubSubLinkNavigator_'.$w.'_'.$z.'">');
												echo ($LinkNavigator);
												echo('</select>');
												echo ('</div>');
												echo ('<div class="titleleft">');
												if ($checked==='_blank')
												$checked='checked="checked"';
												else
												echo('<input name="SubSubLabelNewWindow['.$w.']['.$z.']" type="hidden" value="" />');
												
												echo ('<label style="display:inline;" title="Open New Window">Open Blank <input name="SubSubLabelNewWindow['.$w.']['.$z.']" type="checkbox" '.$checked.' value="_blank" /></label>');
												echo ('</div>');
											//end third level//
											
											echo ('</div>'); 
											echo ('<div class="clear-cp"></div>');
											
											
											
										$Link='';
										$checked='';
										$z++;
										}
										
										
										// ADD NEW
										echo ('<div class="wrapper3last" style="background-color:#F2F2F2; margin-left:5px; margin-right:5px; margin-bottom:10px; padding:10px 5px 5px 28px; ">');
										echo ('<div class="titleleft">');
										
										echo ('<label style="display:inline;">New Level 3 Button: ');
										echo ('<input name="NewSubSubLabel['.$w.'][]" type="text" id="menusubsublabel" /></label>');
										echo ('<input name="NewSubLabelRecordID['.$w.'][]" type="hidden" id="newsublabelrecordid" value="' .$Root2RecordID . '" />');
										echo ('</div>');
										echo ('<div class="titleleft">');
										echo ('<label style="display:inline;">Order: <input name="NewSubSubMenuOrder['.$w.'][]" type="text" id="order" /></label>');
										echo ('</div>');
										echo ('<div class="clear"></div>');
										echo ('<div class="titleleft">');
										echo ('<label style="display:inline;">Link: ');
										echo ('<input name="NewSubSubLabelLink['.$w.'][]" type="text" style="width:240px;" id="newsubsublabellink_'.$w.'_'.$z.'" /></label>');
										echo ('</div>');
										echo ('<div class="titleleft">');
										echo '<script type="text/javascript">'. "\n";
										echo '<!--' ."\n";
										echo '$(\'#NewSubSubNavigator_'.$w.'_'.$z.'\').bind(\'change\', function() {'. "\n";
										echo '$(\'#newsubsublabellink_'.$w.'_'.$z.'\').val($(this).val());'. "\n";
										echo '});'. "\n";
										echo '-->'. "\n";
										echo '</script>';
										echo('<select name="NewSubSubNavigator" id="NewSubSubNavigator_'.$w.'_'.$z.'">');
										echo ($LinkNavigator);
										echo('</select>');
										echo ('</div>');
										echo ('<div class="titleleft">');
										echo ('<label style="display:inline;" title="Open New Window">Open Blank <input name="NewSubSubLabelNewWindow['.$w.'][]" type="checkbox" value="_blank" /></label>');
										
										echo ('</div>');
										echo ('</div>');
										//
									
										
							
							
							//end second level//
							echo ('</div>'); 
							echo ('<div class="clear-cp"></div>');
							
							
							
						$Link='';
						$checked='';
						$w++;
						}
						
						// ADD NEW
						
						echo ('<div class="wrapper2last" style="margin-left:5px; margin-bottom:10px; padding:10px 5px 5px 28px; ">');
						echo ('<div class="titleleft">');
						
						echo ('<label style="display:inline;">New Level 2 Button: ');
						echo ('<input name="NewSubLabel['.$f.'][]" type="text" id="newsublabel" /></label>');
						echo ('<input name="NewMainLabelRecordID['.$f.'][]" type="hidden" id="newmainlabelrecordid" value="' .$RootRecordID . '" />');
						echo ('</div>');
						echo ('<div class="titleleft">');
						echo ('<label style="display:inline;">Order: <input name="NewSubMenuOrder['.$f.'][]" type="text" id="order" /></label>');
						echo ('</div>');
						echo ('<div class="clear"></div>');
						echo ('<div class="titleleft">');
						echo ('<label style="display:inline;">Link: ');
						echo ('<input name="NewSubLabelLink['.$f.'][]" type="text" style="width:240px;" id="newmenulink_'.$f.'" /></label>');
						echo ('</div>');
						echo ('<div class="titleleft">');
						echo '<script type="text/javascript">'. "\n";
						echo '<!--' ."\n";
						echo '$(\'#NewLinkNavigator_'.$f.'\').bind(\'change\', function() {'. "\n";
						echo '$(\'#newmenulink_'.$f.'\').val($(this).val());'. "\n";
						echo '});'. "\n";
						echo '-->'. "\n";
						echo '</script>';
						echo('<select name="NewLinkNavigator" id="NewLinkNavigator_'.$f.'">');
						echo ($LinkNavigator);
						echo('</select>');
						echo ('</div>');
						echo ('<div class="titleleft">');
						echo ('<label style="display:inline;" title="Open New Window">Open Blank <input name="NewSubLabelNewWindow['.$f.'][]" type="checkbox" value="_blank" /></label>');
						
						echo ('</div>');
						echo ('</div>');
						//
			
			// clean link
			$Link='';
			$checked='';
			$f++;
		}
		
		echo ('</div></dd>'); 
		?>
        
        </dl>
        
      
        <div class="clear-cp"></div>
        <input type="hidden" name="CurTitle" id="CurTitle" value="<?php echo $Title ?>" />
        <input type="hidden" name="Language" id="Language" value="<?php echo language ?>" />
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
		}
?>
