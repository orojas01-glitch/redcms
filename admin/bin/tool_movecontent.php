<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_tool_helpers.php' ?>



<?php
		$Type=red_admin_tool_post_text('Type');
		$CountPage=red_admin_tool_count_page($_POST['CountPage'] ?? '');
		$Section=red_admin_tool_post_text('Section');
		$Category=red_admin_tool_post_text('Category');
		$SubCategory=red_admin_tool_post_text('SubCategory');
		$Article=red_admin_tool_post_text('Article');
		$Language=red_admin_tool_post_text('Language');
		$Layout=red_admin_tool_post_text('Layout');
		$cparea=red_admin_tool_post_text('cparea');
		$cpareaConfig=red_admin_tool_area_config($cparea);
		if ($CountPage === 0 || ($cparea !== 'Content' && !$cpareaConfig)) {
			echo 'no';
			exit;
		}
		$cpareastyle=$cparea === 'Content' ? 'content' : $cpareaConfig['style'];
		$compgroup=red_admin_tool_post_text('compgroup');
		$SortBy=red_admin_tool_post_text('SortBy');
		$FilterPosition=red_admin_tool_post_text('SelectPosition');
		$rowposition=$cparea === 'Content'
			? red_admin_tool_position_column($_POST['VarPosition'] ?? '')
			: $cpareaConfig['position'];
		if ($FilterPosition === ''){
			$FilterPosition= 'all';
			if ($Section==='home') {
				$rowposition='HomePosition';
			}
		}
		$VarPosition=red_admin_tool_position_column($_POST['VarPosition'] ?? '', $rowposition);
		if ($VarPosition === '') {
			echo 'no';
			exit;
		}
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$This = new stdClass();
		$Positions=red_admin_tool_layout_positions($db->connection, $Layout);
		$Articles=red_admin_tool_move_articles(
			$db->connection,
			$CountPage,
			$Section,
			$Category,
			$SubCategory,
			$Article,
			$VarPosition,
			$FilterPosition,
			$SortBy,
			$rowposition
		);
		$AdminComponentIDs=red_admin_tool_admin_component_ids($_SESSION['AdminComponents'] ?? '');
		$LayoutParam=rawurlencode($Layout);
		$VarPositionParam=rawurlencode($VarPosition);

?>
<!-- Our CSS stylesheet file -->
<link rel="stylesheet" href="/admin/assets/css/styles.css" />
<script type="text/javascript">
<!--
function run_toolmove (toolmove)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/run_tool_movecontent.php", 
	data: $("#toolmove").serialize(),
	success: function(data) {
/*	alert (data);
	return false;*/
	if (data=='yes')
	{
	$('#msggbox_tool_content').html("&nbsp; Content Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_tools_content');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_tool_content').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_tool');
	});
	}
	}
	});
	return false;
}
//-->
<!--
/*http://www.sanwebe.com/2014/01/how-to-select-all-deselect-checkboxes-jquery*/
$(document).ready(function() {
    $('#selecctall1').click(function(event) {  //on click 
        if(this.checked) { // check select status
            $('.checkbox1').each(function() { //loop through each checkbox
                this.checked = true;  //select all checkboxes with class "checkbox1"               
            });
        }else{
            $('.checkbox1').each(function() { //loop through each checkbox
                this.checked = false; //deselect all checkboxes with class "checkbox1"                       
            });         
        }
    });
    
});
//-->
<!--
function MM_SelectPosition(SelectPosition){ 
	var dataString = "Item=Reload&CountPage=<?php echo $CountPage ?>&Section=<?php echo rawurlencode($Section) ?>&Category=<?php echo rawurlencode($Category) ?>&SubCategory=<?php echo rawurlencode($SubCategory) ?>&Article=<?php echo rawurlencode($Article) ?>&Layout=<?php echo rawurlencode($Layout) ?>&VarPosition=<?php echo rawurlencode($VarPosition)?>&cparea=<?php echo rawurlencode($cparea) ?>&SelectPosition=" + encodeURIComponent(SelectPosition.options[SelectPosition.selectedIndex].value);
	//alert (dataString);
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/tool_movecontent.php", 
	data: dataString,
	success: function(data) {
	$('#msggbox_tools_<?php echo $cpareastyle ?>').html(data)
	return false;
	//alert (SelectPosition.options[SelectPosition.selectedIndex].value);
	//return false;
	}
	});
	//return false;  
}
//-->
<!--
$('input:button').click(function() {
    //alert($(this).val());
	var dataString = "CountPage=<?php echo $CountPage ?>&Section=<?php echo rawurlencode($Section) ?>&Category=<?php echo rawurlencode($Category) ?>&SubCategory=<?php echo rawurlencode($SubCategory) ?>&Article=<?php echo rawurlencode($Article) ?>&Layout=<?php echo rawurlencode($Layout) ?>&VarPosition=<?php echo rawurlencode($VarPosition)?>&cparea=<?php echo rawurlencode($cparea) ?>&SelectPosition=<?php if($FilterPosition <>'current') echo rawurlencode($FilterPosition) ?>&SortBy="+ encodeURIComponent($(this).val());
	//alert (dataString);
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/tool_movecontent.php", 
	data: dataString,
	success: function(data) {
	$('#msggbox_tools_<?php echo $cpareastyle ?>').html(data)
	return false;
	}
	});
});
//-->
</script>
<style>
label{color:#000}
.cp_purple{ color:purple; font-weight:bold; font-size:9px;}
.cp_red{ color:red; font-weight:bold; font-size:9px;}
</style>

<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('tools_<?php echo $cpareastyle ?>_grid');">Show Content Tools</a> | Move Content</div>
<div class="container cp_padtop">
        <div class="col-lg-12 col-md-12 col-sm-12 cp_admin">

                
                <div class="wrapper">
                <form id="toolmove" name="toolmove" class="cp" method="post" onSubmit="return run_toolmove(this);">
            	<fieldset>
                
                
                    
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        	<span id="cp_step">
                        	<h4 class="pt-3">Select Article(s):</h4></span><br />
                            <label>Filter by Layout Position: <select name="SelectPosition" id="SelectPosition" onchange="MM_SelectPosition(this)">
                            
                            <?php
                            for ($w=0; $w<=$Positions; $w++)
                            {
								switch ($FilterPosition)
								{
									case 'all':
									if ($w==0)
									echo '<option value="all" selected="selected">View All</option>';
									break;
								
									default:
										if ($w==$FilterPosition)
											echo '<option value="'.$w.'" selected="selected">'.$w.'</option>';
									break;
								}
								
								if ($w==0)
								echo '<option value="all">View All</option>';
								
								echo '<option value="'.$w.'">'.$w.'</option>';
                            }
                            ?>
                            </select></label>
                           <div class="clear-cp"></div>
                            <span id="msggbox_filter_position" style="display:none"></span>
                    </div>
                            <div class="row">
                                
                                    <div class="col-lg-6 col-md-6 col-sm-6" title="Article Title | Update Date">
                                    <?php 
									switch ($SortBy)
									{
										case 'Article Title ▲':
										echo '<input name="SortTitle" type="button" id="SortTitle1" value="Article Title ▼" />';
										break;
										case 'Article Title ▼':
										echo '<input name="SortTitle" type="button" id="SortTitle2" value="Article Title ▲" />';
										break;
										case 'Article Title':
										echo '<input name="SortTitle" type="button" id="SortTitle3" value="Article Title ▼" />';
										break;
										default:
										echo '<input name="SortTitle" type="button" id="SortTitle4" value="Article Title" />';
										break;
									}
									?>
                                    </div>
                                    <div class="col-lg-2 col-md-2 col-sm-2" title="Position">
                                    <?php 
									switch ($SortBy)
									{
										case 'Pos ▲':
										echo '<input name="SortPos" type="button" id="SortPos1" value="Pos ▼" />';
										break;
										case 'Pos ▼':
										echo '<input name="SortPos" type="button" id="SortPos2" value="Pos ▲" />';
										break;
										case 'Pos':
										echo '<input name="SortPos" type="button" id="SortPos3" value="Pos ▼" />';
										break;
										default:
										echo '<input name="SortPos" type="button" id="SortPos4" value="Pos" />';
										break;
									}
									?>
                                    </div>
                                    <div class="col-lg-2 col-md-2 col-sm-2" title="Content Component Type">
                                    <?php 
									switch ($SortBy)
									{
										case 'Comp ▲':
										echo '<input name="SortComp" type="button" id="SortComp1" value="Comp ▼" />';
										break;
										case 'Comp ▼':
										echo '<input name="SortComp" type="button" id="SortComp2" value="Comp ▲" />';
										break;
										case 'Comp':
										echo '<input name="SortComp" type="button" id="SortComp3" value="Comp ▼" />';
										break;
										default:
										echo '<input name="SortComp" type="button" id="SortComp4" value="Comp" />';
										break;
									}
									?>
                                    </div>
                                    <div class="col-lg-2 col-md-2 col-sm-2">edit</div>
								
                            </div>
                            <div class="clear-cp"></div>                            
                            

   
							<?php
                            echo '<div class="wrapper row"><label><input type="checkbox" id="selecctall1"/> Select All</label></div>';
                            $w=0;
                            foreach ($Articles as $row3)
                            {
	                                $RecordID=(int)($row3['RecordID'] ?? 0);
	                                $FunctionSuffix=red_admin_tool_js_suffix($row3['Alias'] ?? '', $RecordID);
									$Title=red_admin_tool_html($row3['Title'] ?? '');
	                                $ComponentName=red_admin_tool_text($row3['Component'] ?? '');
	                                $Component=red_admin_tool_html($ComponentName);
									$ComponentEndpoint=preg_replace('/[^a-z0-9_-]/', '', strtolower($ComponentName));
	                                $HomeFeature=red_admin_tool_text($row3['HomeFeature'] ?? '');
	                                $ThisVarPosition=(int)($row3[$VarPosition] ?? 0);
									$Updated=red_admin_tool_html($row3['Updated'] ?? '');
									$Access=red_admin_tool_component_access($db->connection, $ComponentName, $AdminComponentIDs, $RecordID);
									$CompGroup=$Access['comp_group'];
									$CRecordID=(int)$Access['component_record_id'];

	                                    if(!$Access['authorized']){
                                        //echo ' ADMINISTRATOR NOT AUTHORIZED TO UPDATE<br />';
                                        echo '<script type="text/javascript">'. "\n";
                                        echo '<!--' ."\n";
	                                        echo 'function edit_move_content_'.$FunctionSuffix.' (){'. "\n";
                                        echo '$(\'#msggbox_tool\').html("You\'re not authorized to edit this content.")'. "\n";
                                        echo '.fadeIn(1500, function() {'. "\n";
                                        echo '});'. "\n";
                                        echo 'return false;'. "\n";
                                        echo '}'. "\n";
                                        echo '-->'. "\n";
                                        echo '</script>';
                               
                               
                               
                                        echo '<div class="wrapper row">';
                                        echo '<div class="col-lg-6 col-md-6 col-sm-6">';
                                        echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox1" name="Articles_Sel['.$w.']" value="'.$RecordID.'" />'.$Title;
                                        if ($HomeFeature=='Y')
                                        echo '<font color="red" class="cp_red">*</font></label>';
                                        else
                                        echo '</label>';
                                        echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
										echo '<input type="hidden" name="VarPosition" value="'.$VarPosition.'" />';
                                        echo '</div>';
                                        echo '<div class="col-lg-6 col-md-6 col-sm-6">';
										settype($Positions, "integer");
										if($ThisVarPosition > $Positions){
											echo '<font color="purple" class="cp_purple">';
											echo $ThisVarPosition;
											echo '<sup>&#8224;</sup>';
											echo '</font>';
										}else
											echo $ThisVarPosition;
										echo '&nbsp;</div>';
                                        echo '<div class="col-lg-2 col-md-2 col-sm-2">';
                                        echo $Component;
                                        echo '</div>';
                                        echo '<div class="col-lg-2 col-md-2 col-sm-2">';
                                        echo '<img src="/admin/images/ico_edit.png" onClick="edit_move_content_'.$FunctionSuffix.'();" title="Edit" style="cursor:pointer">';
                                        echo '</div>';
                                        echo '</div>';
	                                        $w++;
                                    
	                                    } else {
                                        //echo $w.' ADMINISTRATOR AUTHORIZED TO UPDATE<br />';
                                        switch ($CompGroup){ // CHECK IF THIS IS A GROUP COMPONENT. I.E:FORM, GALLERY, SUBMENU.
                                        case 'Y':
                                            echo '<script language="JavaScript" type="text/javascript">'. "\n";
                                            echo '<!--' ."\n";
	                                            echo 'function edit_move_content_'.$FunctionSuffix.' (RecordID,CRecordID){'. "\n";
                                            echo '$.ajax({'. "\n";
                                            echo 'type: "POST", '. "\n";
	                                            echo 'url: "/admin/bin/edit_'.$ComponentEndpoint.'.php", '. "\n";
	                                            echo 'data: "RecordID=" + CRecordID + "&ArtRecordID=" + RecordID +"&Layout='.$LayoutParam.'&VarPosition='.$VarPositionParam.'", '. "\n";
                                            echo 'success: function(data) { '. "\n";
                                            //echo 'alert (data);'. "\n";
                                            //echo 'return false;'. "\n";
                                            echo 'if (data) '. "\n";
                                            echo '{'. "\n";
                                           // echo '$(\'#msggbox_tools_content\').hide();'. "\n";
											echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
											
                                            echo '.fadeIn(1500, function() {'. "\n";
                                            //echo '$(\'#msggbox_tools_content\').html("Opening.")'. "\n";
                                            //echo '.append("<p>Please wait.</p>")'. "\n";
                                            echo '});'. "\n";
                                            echo '}'. "\n";
                                            echo 'else '. "\n";
                                            echo '{'. "\n";
                                            echo '$("#msggbox_tools_content").html("Error. Please try again.")'. "\n";
                                            echo '.fadeIn(1500, function() {'. "\n";
                                            echo '$("#msggbox_tools_content");'. "\n";
                                            echo '});'. "\n";
                                            echo '}'. "\n";
                                            echo '}'. "\n";
                                            echo '});'. "\n";
                                            echo 'return false;'. "\n";
                                            echo '}'. "\n";
                                            echo '-->'. "\n";
                                            echo '</script>';
                                
                                            echo '<div class="wrapper row">';
                                            echo '<div class="col-lg-6 col-md-6 col-sm-6">';
                                            echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox1" name="Articles_Sel['.$w.']" value="'.$RecordID.'" />'.$Title;
                                            if ($HomeFeature=='Y')
                                            echo '<font color="red" class="cp_red">*</font></label>';
                                            else
                                            echo '</label>';
                                            echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
											echo '<input type="hidden" name="VarPosition" value="'.$VarPosition.'" />';
                                            echo '</div>';
                                            echo '<div class="col-lg-2 col-md-2 col-sm-2">';
											settype($Positions, "integer");
											if($ThisVarPosition > $Positions){
												echo '<font color="purple" class="cp_purple">';
												echo $ThisVarPosition;
												echo '<sup>&#8224;</sup>';
												echo '</font>';
											}else
												echo $ThisVarPosition;
											echo '&nbsp;</div>';
                                            echo '<div class="col-lg-2 col-md-2 col-sm-2">';
                                            echo $Component;
                                            echo '</div>';
                                            echo '<div class="col-lg-2 col-md-2 col-sm-2">';
                                            echo '<img src="/admin/images/ico_edit.png" onClick="javascript:showdiv(\'editcontent\'); edit_move_content_'.$FunctionSuffix.'(' .$RecordID . ','.$CRecordID.');" title="Edit" style="cursor:pointer">';
                                            echo '</div>';
                                            echo '</div>';
                                            $w++;
                                        break;
                                    
                                        default:
                                        
                                        echo '<script language="JavaScript" type="text/javascript">'. "\n";
                                        echo '<!--' ."\n";
                                        echo 'function edit_move_content_'.$FunctionSuffix.' (RecordID){'. "\n";
                                        echo '$.ajax({'. "\n";
                                        echo 'type: "POST", '. "\n";
                                        echo 'url: "/admin/bin/edit_'.$ComponentEndpoint.'.php", '. "\n";
                                        echo 'data: "RecordID=" + RecordID + "&Layout='.$LayoutParam.'&VarPosition='.$VarPositionParam.'", '. "\n";
                                        echo 'success: function(data) { '. "\n";
                                        //echo 'alert (data);'. "\n";
                                        //echo 'return false;'. "\n";
                                        echo 'if (data) '. "\n";
                                        echo '{'. "\n";
                                        //echo '$(\'#tools_content_grid\').hide();'. "\n";
                                        echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
                                        echo '.fadeIn(1500, function() {'. "\n";
                                        //echo '$(\'#msggbox_toolssection\').html("Opening.")'. "\n";
                                        //echo '.append("<p>Please wait.</p>")'. "\n";
                                        echo '});'. "\n";
                                        echo '}'. "\n";
                                        echo 'else '. "\n";
                                        echo '{'. "\n";
                                        echo '$("#msggbox_tools_content").html("Error. Please try again.")'. "\n";
                                        echo '.fadeIn(1500, function() {'. "\n";
                                        echo '$("#msggbox_tools_content");'. "\n";
                                        echo '});'. "\n";
                                        echo '}'. "\n";
                                        echo '}'. "\n";
                                        echo '});'. "\n";
                                        echo 'return false;'. "\n";
                                        echo '}'. "\n";
                                        echo '-->'. "\n";
                                        echo '</script>';
                                        
                                        echo '<div class="wrapper row">';
                                        echo '<div class="col-lg-6 col-md-6 col-sm-6">';
                                        echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox1" name="Articles_Sel['.$w.']" value="'.$RecordID.'" />'.$Title;
                                        if ($HomeFeature=='Y')
                                        echo '<font color="red" class="cp_red">*</font></label>';
                                        else
                                        echo '</label>';
                                        echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
                                        echo '</div>';
                                       	echo '<div class="col-lg-2 col-md-2 col-sm-2">';
										settype($Positions, "integer");
										if($ThisVarPosition > $Positions){
											echo '<font color="purple" class="cp_purple">';
											echo $ThisVarPosition;
											echo '<sup>&#8224;</sup>';
											echo '</font>';
										}else
											echo $ThisVarPosition;
										echo '&nbsp;</div>';
                                        echo '<div class="col-lg-2 col-md-2 col-sm-2">';
                                        echo $Component;
                                        echo '</div>';
                                        echo '<div class="col-lg-2 col-md-2 col-sm-2">';
                                        echo '<img src="/admin/images/ico_edit.png" onClick="javascript:showdiv(\'editcontent\'); edit_move_content_'.$FunctionSuffix.'(' .$RecordID . ');" title="Edit" style="cursor:pointer">';
                                        echo '</div>';
                                        echo '</div>';
                                        $w++;
                                        
                                    break;
	                                        }
	                            }
                            }
                            ?>
                        
                        </div>

                    <font color="red" style="font-size:9px; padding-left:10px">*Home Featured</font>&nbsp;
                            <font color="purple" style="font-size:9px; padding-left:10px"><sup>&#8224;</sup>Out of Position</font> 
                            <div class="clear-cp"></div>
                    
                    <div class="row">
                        <div class="col-lg-12 col-md-12 col-sm-12">
                        <span id="cp_step">
                            <h4 class="pt-3">Select Article(s) Location:</h4></span>
                    
                        <div class="clear-cp"></div>	
                            <p>
                            <label>Section: <select name="Sections">
                            <option value="">Select...</option>
                            <?php
                            foreach (red_admin_tool_active_area_values($db->connection, 'Sections') as $row3)
                            {
                            $ThisSection=red_admin_tool_html($row3['AreaName'] ?? '');
                            echo '<option value="'.$ThisSection.'">'.$ThisSection.'</option>';
                            }
                            ?>
                            </select></label>
    						</p>
                            
                            <p>
                            <label>Category: <select name="Categories">
                            <option value="">Select...</option>
                            <?php
                            foreach (red_admin_tool_active_area_values($db->connection, 'Categories') as $row3)
                            {
                            $This->Categories=red_admin_tool_html($row3['AreaName'] ?? '');
                            echo '<option value="'.$This->Categories.'">'.$This->Categories.'</option>';
                            }
                            ?>
                            </select>
                            </label>
    						</p>
                            
                            <p>
                            <label>Sub Category: <select name="SubCategories">
                            <option value="">Select...</option>
                            <?php
                            foreach (red_admin_tool_active_area_values($db->connection, 'SubCategories') as $row3)
                            {
                            $This->SubCategories=red_admin_tool_html($row3['AreaName'] ?? '');
                            echo '<option value="'.$This->SubCategories.'">'.$This->SubCategories.'</option>';
                            }
                            ?>
                            </select>
                            </label>
                            </p>
                            
                             <p>
                            <label>Article: <select name="Article">
                            <option value="">Select...</option>
                            <?php
                            foreach (red_admin_tool_active_articles($db->connection, true) as $row3)
                            {
                            $Title=red_admin_tool_html($row3['Title'] ?? '');
							$ThisAlias=red_admin_tool_html($row3['Alias'] ?? '');
                            echo '<option value="'.$ThisAlias.'">'.$ThisAlias.'</option>';
                            }
                            ?>
                            </select>
                            </label>
                            </p>
                            <span id="cp_step">
                        	<h4 class="pt-3"><p>Select Position:</h4></span>
                            <?php
							echo '<p>Current Page uses the Layout <strong>'.red_admin_tool_html($Layout).'</strong>, <br/>which has <strong>'.$Positions.'</strong> Positions</p>';
							?>
                            <p>
                            <label title="Layout Position">Position: <select name="Position">
                            <option value="">Select...</option>
							<?php
                             //echo $Layout;
                           if (isset($row[$VarPosition])) {
                            $ThisPosition = $row[$VarPosition];
                        } else {
                            $ThisPosition = 0; // or any default value you prefer
                        }
                        settype($ThisPosition, "integer");
                            //echo $ThisPosition;
                             //echo '<option value="'.$row[$VarPosition].'">'.$row[$VarPosition].'</option>';
                             
                            //echo $Positions;
                            for ($w=0; $w<=$Positions; $w++)
                            {
                                //echo $w;
                                echo '<option value="'.$w.'">'.$w.'</option>';
                                
                            }
                            ?>
                             </select>
                            </label>
                            </p>
                            
                        </div>
                    </div>
                    
                 </div>
                 
                <?php echo red_csrf_input(); ?>
                <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo red_admin_tool_html($_SESSION['alias'] ?? '')?>" />
                <input type="hidden" name="VarPosition" value="<?php echo red_admin_tool_html($VarPosition) ?>" />
                <input type="submit" name="submit" value="Update" id="save"/>
                <span id="msggbox_tool_content" style="display:none"></span>
                </fieldset>
                </form> 
            </div>
        </div>

</div>

<?php
$db->close();
?>
