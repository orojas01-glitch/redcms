<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin();
red_require_admin_tool(2); ?>
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
		$cparea=red_admin_tool_post_text('cparea'); // where to send the response by #id
		$cpareaConfig=red_admin_tool_area_config($cparea);
		if (!$cpareaConfig || $CountPage === 0) {
			echo 'no';
			exit;
		}
		$cpareastyle=$cpareaConfig['style'];
		$SortBy=red_admin_tool_post_text('SortBy');
		$rowposition=$cpareaConfig['position'];
		$singularcparea=$cpareaConfig['singular'];
		$selcol1=$cparea === 'Sections' ? 'cp_selco' : '';
		$selcol2=$cparea === 'Categories' ? 'cp_selco' : '';
		$selcol3=$cparea === 'SubCategories' ? 'cp_selco' : '';
		$FilterPosition=red_admin_tool_post_text('SelectArea');
		if ($FilterPosition === ''){
			$FilterPosition= 'current';
			if ($Section==='home') {
				$rowposition='HomePosition';
			}
		}else{
			$outerARR = explode( ',', $FilterPosition, 2);
			if(count($outerARR)>1){
				$FilterPosition=red_admin_tool_text($outerARR[0]);
				$Layout=red_admin_tool_text($outerARR[1]);
			}
			if ($FilterPosition==='home') {
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
		if($FilterPosition==='current'){
			switch($cparea){
				case 'Sections':
				$SelectedArea=$Section;
				break;
				case 'Categories':
				$SelectedArea=$Category;
				break;
				case 'SubCategories':
				$SelectedArea=$SubCategory;
				break;
				default:
				$SelectedArea='';
				break;
			}
		}else{
			$SelectedArea=$FilterPosition;
		}
		$Layout=red_admin_tool_area_layout($db->connection, $cparea, $SelectedArea);
		$Positions=red_admin_tool_layout_positions($db->connection, $Layout);
		$PositionOptions=red_admin_tool_layout_position_options($db->connection, $Layout, true);
		$Articles=red_admin_tool_filter_articles($db->connection, $cparea, $SelectedArea, $SortBy);
		$Counter=count($Articles);
		$AdminComponentIDs=red_admin_tool_admin_component_ids($_SESSION['AdminComponents'] ?? '');
		$LayoutParam=rawurlencode($Layout);
		$RowPositionParam=rawurlencode($rowposition);

?>
<script type="text/javascript">
<!--
function run_toolfilter (toolfilter)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/run_tool_filterareas.php", 
	data: $("#toolfilter").serialize(),
	success: function(data) {
/*	alert (data);
	return false;*/
	if (data=='yes')
	{
	$('#msggbox_tool_<?php echo $cpareastyle ?>').html("&nbsp; Content Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_tools_content');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_tool_<?php echo $cpareastyle ?>').html("&nbsp; Error. Please try again.")
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
    $('#selecctall_<?php echo $cparea ?>').click(function(event) {  //on click 
        if(this.checked) { // check select status
            $('.checkbox_<?php echo $cparea ?>').each(function() { //loop through each checkbox
                this.checked = true;  //select all checkboxes with class "checkbox1"               
            });
        }else{
            $('.checkbox_<?php echo $cparea ?>').each(function() { //loop through each checkbox
                this.checked = false; //deselect all checkboxes with class "checkbox1"                       
            });         
        }
    });
    
});
//-->

<!--
function MM_SelectArea(SelectArea){ 
	var dataString = "Item=Reload&CountPage=<?php echo $CountPage ?>&Section=<?php echo rawurlencode($Section) ?>&Category=<?php echo rawurlencode($Category) ?>&SubCategory=<?php echo rawurlencode($SubCategory) ?>&Article=<?php echo rawurlencode($Article) ?>&Layout=<?php echo rawurlencode($Layout) ?>&VarPosition=<?php echo rawurlencode($VarPosition)?>&cparea=<?php echo rawurlencode($cparea) ?>&SelectArea=" + encodeURIComponent(SelectArea.options[SelectArea.selectedIndex].value);
	//alert (dataString);
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/tool_filterareas.php", 
	data: dataString,
	success: function(data) {
	$('#msggbox_tools_<?php echo $cpareastyle ?>').html(data)
	return false;
	//alert (SelectArea.options[SelectArea.selectedIndex].value);
	//return false;
	}
	});
	//return false;  
}
//-->
<!--
$('input:button').click(function() {
    //alert($(this).val());
	var dataString = "CountPage=<?php echo $CountPage ?>&Section=<?php echo rawurlencode($Section) ?>&Category=<?php echo rawurlencode($Category) ?>&SubCategory=<?php echo rawurlencode($SubCategory) ?>&Article=<?php echo rawurlencode($Article) ?>&Layout=<?php echo rawurlencode($Layout) ?>&VarPosition=<?php echo rawurlencode($rowposition)?>&cparea=<?php echo rawurlencode($cparea) ?>&SelectArea=<?php if($FilterPosition <>'current') echo rawurlencode($FilterPosition) ?>&SortBy="+ encodeURIComponent($(this).val());
	//alert (dataString);
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/tool_filterareas.php", 
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
label{color:#000;}
.cp div.row3 .comp{font-size:10px;}
input[type='checkbox']{float:left}
</style>
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('tools_<?php echo $cpareastyle ?>_grid');">Show Tools</a> | Filter Content</div>

<div class="container_12 cp_padtop">
        <article class="grid_12 cp_admin">
                <div class="wrapper">
                <form id="toolfilter" name="toolfilter" class="cp" method="post" onSubmit="return run_toolfilter(this);">
            	<fieldset>
                
                        	<span id="cp_step"><p>Select Article(s):</p></span><br />
                            <label>Filter by <?php echo red_admin_tool_html($cparea) ?>: <select name="SelectArea_<?php echo red_admin_tool_html($cpareastyle) ?>" id="SelectArea_<?php echo red_admin_tool_html($cpareastyle) ?>" onchange="MM_SelectArea(this)">
                            
                            <?php
                            if($FilterPosition==='current'){
								if(($Section<>'' && $Article <> $Section) && $cparea==='Sections')
								$Selected=$Section;
								elseif(($Category<>'' && $Article<> $Category) && $cparea==='Categories')
								$Selected=$Category;
								elseif(($SubCategory<>'' && $Article<>$SubCategory) && $cparea==='SubCategories')
								$Selected=$SubCategory;
								echo '<option value="" selected="selected">'.red_admin_tool_html($Selected ?? '').'</option>';
							}
							foreach (red_admin_tool_area_options($db->connection, $cparea) as $row4)
                            {
								$areaname=red_admin_tool_text($row4['AreaName'] ?? '');
								$availpos=red_admin_tool_text($row4['Layout'] ?? '');
								$optionValue=red_admin_tool_html($areaname.','.$availpos);
								$areaLabel=red_admin_tool_html($areaname);
								if($areaname===$FilterPosition)
								echo '<option value="'.$optionValue.'" selected="selected">'.$areaLabel.'</option>';
								else
								echo '<option value="'.$optionValue.'">'.$areaLabel.'</option>';
                            }
                            ?>
                            </select>
                            
							<?php
							echo '<font color="black" style="font-weight:normal;"> There are <strong>'. $Counter.'</strong> related Articles.</font> ';
							if($Counter>0){
							echo '&nbsp; <font color="black" style="font-weight:normal;"> This '.red_admin_tool_html($singularcparea).' uses the Layout <strong>'.red_admin_tool_html($Layout).'</strong> which has <strong>'.$Positions.'</strong> Positions. </font>';
							
							?>
                            </label>
                            
                           <div class="clear-cp"></div>
                           
                            <div class="wrapper">
                                <div class="header">
                                    <div class="grid_3" style="border-right:#ccc 1px solid; margin-left:0px; margin-right:0px;" title="Article Title | Update Date">
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
                                    <div class="grid_1" style="border-right:#ccc 1px solid; text-align:center; margin-left:0px; margin-right:0px; " title="Position in <?php echo $singularcparea?>">
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
                                    <div class="grid_1" style="border-right:#ccc 1px solid; margin-left:0px; margin-right:0px;" title="Content Component Type">
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
                                    <div class="grid_2 <?php echo $selcol1?>" style="border-right:#ccc 1px solid; text-align:center; margin-left:0px" title="Section">Section</div>
                                    <div class="grid_2 <?php echo $selcol2?>" style="border-right:#ccc 1px solid; text-align:center; margin-left:0px" title="Category">Category</div>
                                    <div class="grid_1 <?php echo $selcol3?>" style="border-right:#ccc 1px solid; text-align:center; margin-left:0px" title="SubCategory">SubCat</div>
                                    <div class="grid_2" style="border-right:#ccc 1px solid; text-align:center; margin-left:0px">
                                    <?php 
									switch ($SortBy)
									{
										case 'Comp ▲':
										echo '<input name="SortArticle" type="button" id="SortArticle1" value="Article ▼" />';
										break;
										case 'Article ▼':
										echo '<input name="SortArticle" type="button" id="SortArticle2" value="Article ▲" />';
										break;
										case 'Article':
										echo '<input name="SortArticle" type="button" id="SortArticle3" value="Article ▼" />';
										break;
										default:
										echo '<input name="SortArticle" type="button" id="SortArticle4" value="Article" />';
										break;
									}
									?>
                                    </div>
                                    <div class="grid_1" style="border-right:#ccc 1px solid;text-align:center; margin-left:0px">edit</div>
								</div>
                            </div>
                             <div class="clear-cp"></div>
                            <font color="red" style="font-size:9px; padding-left:10px">*Home Featured</font>&nbsp;
                            <font color="purple" style="font-size:9px; padding-left:10px"><sup>&#8224;</sup>Out of Position</font>
                             <div class="clear-cp"></div>
								<?php
								//echo $rowposition;
							    echo '<div class="wrapper row2"><div class="titleleft title"><label><input type="checkbox" id="selecctall_'.$cparea.'"/> Select All</label></div></div>';
                                $w=0;
									foreach ($Articles as $row3)
                                {
	                                $RecordID=(int)($row3['RecordID'] ?? 0);
	                                $Alias=preg_replace('/[^A-Za-z0-9_]+/', '_', red_admin_tool_text($row3['Alias'] ?? 'content'));
									$Alias=trim($Alias, '_') ?: 'content';
	                                $Title=red_admin_tool_html($row3['Title'] ?? '');
	                                $Component=red_admin_tool_text($row3['Component'] ?? '');
									$ComponentEndpoint=preg_replace('/[^a-z0-9_-]/', '', strtolower($Component));
									$ComponentHtml=red_admin_tool_html($Component);
									$HomeFeature=red_admin_tool_text($row3['HomeFeature'] ?? '');
									$This->Sections=red_admin_tool_text($row3['Sections'] ?? '');
									$This->Position=(int)($row3[$rowposition] ?? 0);
									$This->Categories=red_admin_tool_text($row3['Categories'] ?? '');
									$This->SubCategories=red_admin_tool_text($row3['SubCategories'] ?? '');
									$This->Article=red_admin_tool_text($row3['Article'] ?? '');
									$Updated=red_admin_tool_html($row3['Updated'] ?? '');
									$Access=red_admin_tool_component_access($db->connection, $Component, $AdminComponentIDs, $RecordID);
									$CompGroup=$Access['comp_group'];
									$CRecordID=(int)$Access['component_record_id'];
									if (!$Access['authorized']) {
										continue;
									}
                                    
                                    if(!$Access['authorized']){
                                        //echo ' ADMINISTRATOR NOT AUTHORIZED TO UPDATE<br />';
                                        echo '<script type="text/javascript">'. "\n";
                                        echo '<!--' ."\n";
                                        echo 'function edit_filter_content_'.$Alias.'_'.$RecordID.' (){'. "\n";
                                        echo '$(\'#msggbox_tool\').html("You\'re not authorized to edit this content.")'. "\n";
                                        echo '.fadeIn(1500, function() {'. "\n";
                                        echo '});'. "\n";
                                        echo 'return false;'. "\n";
                                        echo '}'. "\n";
                                        echo '-->'. "\n";
                                        echo '</script>';
										
										
										
										echo '<div class="wrapper row3">';
										echo '<div class="grid_3" style="margin-left:0px; margin-right:0px;">';
										echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox_'.$cparea.'" name="Articles_Sel['.$w.']" value="'.$RecordID.'" />'.$Title;
										if ($HomeFeature=='Y')
										echo '<font color="red">*</font></label></label>';
										else
										echo '</label>';
										echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
										echo '</div>';
										echo '<div class="grid_1" style="text-align:center; margin-left:0px; margin-right:0px;">';
										if(!array_key_exists((int) $This->Position, $PositionOptions)){
											echo '<font color="purple">';
											echo $This->Position;
											echo '<sup>&#8224;</sup>';
											echo '</font>';
										}else
											echo $This->Position;
										echo '&nbsp;</div>';
										echo '<div class="grid_1 comp" style="text-align:center;margin-left:0px; margin-right:0px;">'.$ComponentHtml.'&nbsp;</div>';
										 echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
											if ($cparea==='Sections'){
											if(((int) $This->Position !== 0) && array_key_exists((int) $This->Position, $PositionOptions)){
														if ($This->Sections==='home')
														echo '<a href="/">'.$This->Sections.'</a>';
														else
														echo '<a href="/'.$This->Sections.'/">'.$This->Sections.'</a>';
													}else
													echo $This->Sections; 
												}else
												echo $This->Sections;
										echo '&nbsp;</div>';
										 echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
											if ($cparea==='Categories'){
											if(((int) $This->Position !== 0) && array_key_exists((int) $This->Position, $PositionOptions)){
														if ($This->Sections==='home')
														echo '<a href="/'.$This->Categories.'/">'.$This->Categories.'</a>';
														else
														echo '<a href="/'.$This->Sections.'/'.$This->Categories.'/">'.$This->Categories.'</a>';
													}else
													echo $This->Categories;
												}else
												echo $This->Categories;
										echo '&nbsp;</div>';
										echo '<div class="grid_1" style="text-align:center; margin-left:0px">';
											if ($cparea==='SubCategories'){
											if(((int) $This->Position !== 0) && array_key_exists((int) $This->Position, $PositionOptions)){
														if ($This->Sections==='home')
														echo '<a href="/'.$This->Categories.'/'.$This->SubCategories.'/">'.$This->SubCategories.'</a>';
														else
														echo '<a href="/'.$This->Sections.'/'.$This->Categories.'/'.$This->SubCategories.'/">'.$This->SubCategories.'</a>';
													}else
													echo $This->SubCategories;
												}else
												echo $This->SubCategories;
										echo '&nbsp;</div>';
										echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
												$Link=$This->Article;
												if ($This->SubCategories)
												$Link=$This->SubCategories.'/'.$Link;
												if ($This->Categories)
												$Link=$This->Categories.'/'.$Link;
												if ($This->Sections!='home')
												$Link=$This->Sections.'/'.$Link;
												$Link='/'.$Link;
												
												if ($This->Article)
												echo '<a href="'.$Link.'">'.$This->Article.'</a>';
												else
												echo '&nbsp;';
											echo '</div>';
										echo '<div class="grid_1" style="text-align:center; margin-left:0px; margin-right:0px">';
                                        echo '<img src="/admin/images/ico_edit.png" onClick="edit_filter_content_'.$Alias.'_'.$RecordID.'();" title="Edit" style="cursor:pointer">';
                                        echo '</div>';
                                        echo '</div>';
	                                        $w++;
									
										} else {
                                        //echo $w.' ADMINISTRATOR AUTHORIZED TO UPDATE<br />';
                                        switch ($CompGroup){ // CHECK IF THIS IS A GROUP COMPONENT. I.E:FORM, GALLERY, SUBMENU.
                                        case 'Y':
                                            echo '<script language="JavaScript" type="text/javascript">'. "\n";
                                            echo '<!--' ."\n";
                                            echo 'function edit_filter_content_'.$Alias.'_'.$RecordID.' (RecordID,CRecordID){'. "\n";
                                            echo '$.ajax({'. "\n";
                                            echo 'type: "POST", '. "\n";
	                                            echo 'url: "/admin/bin/edit_'.$ComponentEndpoint.'.php", '. "\n";
	                                            echo 'data: "RecordID=" + CRecordID + "&ArtRecordID=" + RecordID +"&Layout='.$LayoutParam.'&VarPosition='.$RowPositionParam.'", '. "\n";
                                            echo 'success: function(data) { '. "\n";
                                            //echo 'alert (data);'. "\n";
                                            //echo 'return false;'. "\n";
                                            echo 'if (data) '. "\n";
                                            echo '{'. "\n";
                                           // echo '$(\'#msggbox_tools_sections\').hide();'. "\n";
											echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
											
                                            echo '.fadeIn(1500, function() {'. "\n";
                                            //echo '$(\'#msggbox_tools_sections\').html("Opening.")'. "\n";
                                            //echo '.append("<p>Please wait.</p>")'. "\n";
                                            echo '});'. "\n";
                                            echo '}'. "\n";
                                            echo 'else '. "\n";
                                            echo '{'. "\n";
                                            echo '$("#msggbox_tools_'.$cpareastyle.'").html("Error. Please try again.")'. "\n";
                                            echo '.fadeIn(1500, function() {'. "\n";
                                            echo '$("#msggbox_tools_'.$cpareastyle.'");'. "\n";
                                            echo '});'. "\n";
                                            echo '}'. "\n";
                                            echo '}'. "\n";
                                            echo '});'. "\n";
                                            echo 'return false;'. "\n";
                                            echo '}'. "\n";
                                            echo '-->'. "\n";
                                            echo '</script>';
											
											echo '<div class="wrapper row3">';
											echo '<div class="grid_3" style="margin-left:0px; margin-right:0px;">';
											echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox_'.$cparea.'" name="Articles_Sel['.$w.']" value="'.$RecordID.'" />'.$Title;
											if ($HomeFeature=='Y')
											echo '<font color="red">*</font></label></label>';
											else
											echo '</label>';
											echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
											echo '<input type="hidden" name="VarPosition" value="'.$This->Position.'" />';
											echo '</div>';
											echo '<div class="grid_1" style="text-align:center; margin-left:0px; margin-right:0px;">';
											if(!array_key_exists((int) $This->Position, $PositionOptions)){
												echo '<font color="purple">';
												echo $This->Position;
												echo '<sup>&#8224;</sup>';
												echo '</font>';
											}else
											echo $This->Position;
											echo '&nbsp;</div>';
											echo '<div class="grid_1 comp" style="text-align:center;margin-left:0px; margin-right:0px;">'.$ComponentHtml.'&nbsp;</div>';
											echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
												if ($cparea==='Sections'){
												if(((int) $This->Position !== 0) && array_key_exists((int) $This->Position, $PositionOptions)){
														if ($This->Sections==='home')
														echo '<a href="/">'.$This->Sections.'</a>';
														else
														echo '<a href="/'.$This->Sections.'/">'.$This->Sections.'</a>';
													}else
													echo $This->Sections; 
												}else
												echo $This->Sections;
												
											echo '&nbsp;</div>';
											echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
												
												if ($cparea==='Categories'){
												if(((int) $This->Position !== 0) && array_key_exists((int) $This->Position, $PositionOptions)){
														if ($This->Sections==='home')
														echo '<a href="/'.$This->Categories.'/">'.$This->Categories.'</a>';
														else
														echo '<a href="/'.$This->Sections.'/'.$This->Categories.'/">'.$This->Categories.'</a>';
													}else
													echo $This->Categories;
												}else
												echo $This->Categories;
												
											echo '&nbsp;</div>';
											echo '<div class="grid_1" style="text-align:center; margin-left:0px">';
											
												if ($cparea==='SubCategories'){
												if(((int) $This->Position !== 0) && array_key_exists((int) $This->Position, $PositionOptions)){
														if ($This->Sections==='home')
														echo '<a href="/'.$This->Categories.'/'.$This->SubCategories.'/">'.$This->SubCategories.'</a>';
														else
														echo '<a href="/'.$This->Sections.'/'.$This->Categories.'/'.$This->SubCategories.'/">'.$This->SubCategories.'</a>';
													}else
													echo $This->SubCategories;
												}else
												echo $This->SubCategories;
												
											echo '&nbsp;</div>';
											echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
												$Link=$This->Article;
												if ($This->SubCategories)
												$Link=$This->SubCategories.'/'.$Link;
												if ($This->Categories)
												$Link=$This->Categories.'/'.$Link;
												if ($This->Sections!='home')
												$Link=$This->Sections.'/'.$Link;
												$Link='/'.$Link;
												
												if ($This->Article)
												echo '<a href="'.$Link.'">'.$This->Article.'</a>';
												else
												echo '&nbsp;';
											echo '</div>';
											echo '<div class="grid_1" style="text-align:center; margin-left:0px; margin-right:0px">';
											echo '<img src="/admin/images/ico_edit.png" onClick="javascript:showdiv(\'editcontent\'); edit_filter_content_'.$Alias.'_'.$RecordID.'(' .$RecordID . ','.$CRecordID.');" title="Edit" style="cursor:pointer">';
											echo '</div>';
											echo '</div>';
											$w++;
									
										break;
										
										default:
                                        
                                        echo '<script language="JavaScript" type="text/javascript">'. "\n";
                                        echo '<!--' ."\n";
                                        echo 'function edit_filter_content_'.$Alias.'_'.$RecordID.' (RecordID){'. "\n";
                                        echo '$.ajax({'. "\n";
                                        echo 'type: "POST", '. "\n";
	                                        echo 'url: "/admin/bin/edit_'.$ComponentEndpoint.'.php", '. "\n";
	                                        echo 'data: "RecordID=" + RecordID + "&Layout='.$LayoutParam.'&VarPosition='.$RowPositionParam.'", '. "\n";
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
                                        echo '$("#msggbox_tools_'.$cpareastyle.'").html("Error. Please try again.")'. "\n";
                                        echo '.fadeIn(1500, function() {'. "\n";
                                        echo '$("#msggbox_tools_'.$cpareastyle.'");'. "\n";
                                        echo '});'. "\n";
                                        echo '}'. "\n";
                                        echo '}'. "\n";
                                        echo '});'. "\n";
                                        echo 'return false;'. "\n";
                                        echo '}'. "\n";
                                        echo '-->'. "\n";
                                        echo '</script>';
										
										echo '<div class="wrapper row3">';
											echo '<div class="grid_3" style="margin-left:0px; margin-right:0px;">';
											echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox_'.$cparea.'" name="Articles_Sel['.$w.']" value="'.$RecordID.'" />'.$Title;
											if ($HomeFeature=='Y')
											echo '<font color="red">*</font></label></label>';
											else
											echo '</label>';
											echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
											echo '<input type="hidden" name="VarPosition" value="'.$This->Position.'" />';
											echo '</div>';
											echo '<div class="grid_1" style="text-align:center; margin-left:0px; margin-right:0px;">';
											if(!array_key_exists((int) $This->Position, $PositionOptions)){
												echo '<font color="purple">';
												echo $This->Position;
												echo '<sup>&#8224;</sup>';
												echo '</font>';
											}else
												echo $This->Position;
											echo '&nbsp;</div>';
												echo '<div class="grid_1 comp" style="text-align:center;margin-left:0px; margin-right:0px;">'.$ComponentHtml.'&nbsp;</div>';
											 echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
												if ($cparea==='Sections'){
												if(((int) $This->Position !== 0) && array_key_exists((int) $This->Position, $PositionOptions)){
														if ($This->Sections==='home')
														echo '<a href="/">'.$This->Sections.'</a>';
														else
														echo '<a href="/'.$This->Sections.'/">'.$This->Sections.'</a>';
													}else
													echo $This->Sections; 
												}else
												echo $This->Sections;
											echo '&nbsp;</div>';
											 echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
												if ($cparea==='Categories'){
												if(((int) $This->Position !== 0) && array_key_exists((int) $This->Position, $PositionOptions)){
														if ($This->Sections==='home')
														echo '<a href="/'.$This->Categories.'/">'.$This->Categories.'</a>';
														else
														echo '<a href="/'.$This->Sections.'/'.$This->Categories.'/">'.$This->Categories.'</a>';
													}else
													echo $This->Categories;
												}else
												echo $This->Categories;
											echo '&nbsp;</div>';
											echo '<div class="grid_1" style="text-align:center; margin-left:0px">';
												if ($cparea==='SubCategories'){
												if(((int) $This->Position !== 0) && array_key_exists((int) $This->Position, $PositionOptions)){
														if ($This->Sections==='home')
														echo '<a href="/'.$This->Categories.'/'.$This->SubCategories.'/">'.$This->SubCategories.'</a>';
														else
														echo '<a href="/'.$This->Sections.'/'.$This->Categories.'/'.$This->SubCategories.'/">'.$This->SubCategories.'</a>';
													}else
													echo $This->SubCategories;
												}else
												echo $This->SubCategories;
											echo '&nbsp;</div>';
											echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
												$Link=$This->Article;
												if ($This->SubCategories)
												$Link=$This->SubCategories.'/'.$Link;
												if ($This->Categories)
												$Link=$This->Categories.'/'.$Link;
												if ($This->Sections!='home')
												$Link=$This->Sections.'/'.$Link;
												$Link='/'.$Link;
												
												if ($This->Article)
												echo '<a href="'.$Link.'">'.$This->Article.'</a>';
												else
												echo '&nbsp;';
											echo '</div>';
											echo '<div class="grid_1" style="text-align:center; margin-left:0px; margin-right:0px">';
											echo '<a href="#cp_content" onClick="javascript:showdiv(\'editcontent\'); edit_filter_content_'.$Alias.'_'.$RecordID.'(' .$RecordID . ');">';
											echo '<img src="/admin/images/ico_edit.png" onClick="" title="Edit" style="cursor:pointer"></a>';
											echo '</div>';
											echo '</div>';
											$w++;
                                        
                                    break;
	                                        }
	                            }
							
							}
                            ?>
                         <div class="clear-cp"></div>	
                        <span id="cp_step"><p>Select Article(s) Location:</p></span>
                    
                       
                            <p>
                            <label>Section: <select name="Sections">
                            <option value="">Select...</option>
                            
                            <?php
                            foreach (red_admin_tool_active_area_values($db->connection, 'Sections') as $row3)
                            {
                            $This->Section=red_admin_tool_html($row3['AreaName'] ?? '');
                            echo '<option value="'.$This->Section.'">'.$This->Section.'</option>';
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
                            <option value="-">N/A</option>
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
                            <option value="-">N/A</option>
                            </select>
                            </label>
                            </p>
                            
                             <p>
                            <label>Article: <select name="Article">
                            <option value="">Select...</option>
                            <?php
                            foreach (red_admin_tool_active_articles($db->connection, false) as $row3)
                            {
                            $This->Title=red_admin_tool_html($row3['Title'] ?? '');
							$This->Alias=red_admin_tool_html($row3['Alias'] ?? '');
                            echo '<option value="'.$This->Alias.'">'.$This->Title.'</option>';
                            }
                            ?>
                            <option value="-">N/A</option>
                            </select>
                            </label>
                            </p>
                            
                            
                            
                       
                <?php echo red_csrf_input(); ?>
                <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo red_admin_tool_html($_SESSION['alias'] ?? '')?>" />
                <input type="submit" name="submit" value="Update" id="save"/>
                <span id="msggbox_tool_<?php echo $cpareastyle ?>" style="display:none"></span>
                </fieldset>
                </form> 
             </div>
        </article>

</div>

<?php

		}
?>
