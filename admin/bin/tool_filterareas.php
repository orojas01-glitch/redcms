<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_build_query.php' ?>



<?php
if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {
		$Type=preg_replace ( "'<[^>]+>'U", "", $_POST['Type']);
		$CountPage=preg_replace ( "'<[^>]+>'U", "", $_POST['CountPage']);
		$Section=preg_replace ( "'<[^>]+>'U", "", $_POST['Section']);
		$Category=preg_replace ( "'<[^>]+>'U", "", $_POST['Category']);
		$SubCategory=preg_replace ( "'<[^>]+>'U", "", $_POST['SubCategory']);
		$Article=preg_replace ( "'<[^>]+>'U", "", $_POST['Article']);
		$VarPosition=preg_replace ( "'<[^>]+>'U", "", $_POST['VarPosition']);
		$Language=preg_replace ( "'<[^>]+>'U", "", $_POST['Language']);
		//$Layout=preg_replace ( "'<[^>]+>'U", "", $_POST['Layout']);
		$cparea=preg_replace ( "'<[^>]+>'U", "", $_POST['cparea']); // where to send the response by #id
		$cpareastyle=strtolower($cparea);
		$SortBy = preg_replace ( "'<[^>]+>'U", "", $_POST['SortBy']);
		//echo $SortBy;
		//echo 'layout='.$Layout.'<br/>';
		
		switch ($cparea){
		case 'Sections':
		$rowposition='SectionPosition';
		$selcol1='cp_selco';
		$singularcparea='Section';
		break;
		case 'Categories':
		$rowposition='CategoryPosition';
		$selcol2='cp_selco';
		$singularcparea='Category';
		break;
		case 'SubCategories':
		$rowposition='SubCategoryPosition';
		$selcol3='cp_selco';
		$singularcparea='SubCategory';
		break;
		default:
		$rowposition=$VarPosition;
		
		break;
		
		}
		
		$FilterPosition=preg_replace ( "'<[^>]+>'U", "", $_POST['SelectArea']);
		if ($FilterPosition === ''){
		$FilterPosition= 'current';
		if ($Section==='home')
		$rowposition='HomePosition';
		
		}else{
		$outerARR = explode( ',', $FilterPosition);
		if(count($outerARR)>1){
			$FilterPosition=$outerARR[0];
			$Layout=$outerARR[1];
		}
		if ($FilterPosition==='home')
		$rowposition='HomePosition';
		}
		
		//LAYOUT
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		if($FilterPosition==='current'){
			switch($cparea){
				case 'Sections':
				$resultL = $db->query("SELECT * FROM RED_".$cparea." WHERE ".$cparea."='".$Section."'");
				while($row4 = mysqli_fetch_assoc($resultL))
				{
					$Layout=$row4['Layout'];
				}
				
				break;
				case 'Categories':
				$resultL = $db->query("SELECT * FROM RED_".$cparea." WHERE ".$cparea."='".$Category."'");
				while($row4 = mysqli_fetch_assoc($resultL))
				{
					$Layout=$row4['Layout'];
				}

				break;
				case 'SubCategories':
				$resultL = $db->query("SELECT * FROM RED_".$cparea." WHERE ".$cparea."='".$SubCategory."'");
				while($row4 = mysqli_fetch_assoc($resultL))
				{
					$Layout=$row4['Layout'];
				}

				break;
			}
		}
		
		else {
			$resultL = $db->query("SELECT * FROM RED_".$cparea." WHERE ".$cparea."='".$FilterPosition."'");
				while($row4 = mysqli_fetch_assoc($resultL))
				{
					$Layout=$row4['Layout'];
				}
		}

		$xquery=new Build_Query();
		$rquery=$xquery->cp_get_query($CountPage, $Section, $Category, $SubCategory, $Article);
		$articlequeryfilter=$rquery[0];
		//$VarPosition=$rquery[1];
		/*$this->VarFeatures=$rquery[2];
		$metaquery=$rquery[3];	
		$Table=$rquery[4];*/
		
		/*echo $articlequeryfilter.'<br/>';
		echo $this->VarFeatures.'<br/>';
		echo $this->VarPosition;*/

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
	var dataString = "Item=Reload&CountPage=<?php echo $CountPage ?>&Section=<?php echo $Section ?>&Category=<?php echo $Category ?>&SubCategory=<?php echo $SubCategory ?>&Article=<?php echo $Article ?>&Layout=<?php echo $Layout ?>&VarPosition=<?php echo $VarPosition?>&cparea=<?php echo $cparea ?>&SelectArea=" + SelectArea.options[SelectArea.selectedIndex].value;
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
	var dataString = "CountPage=<?php echo $CountPage ?>&Section=<?php echo $Section ?>&Category=<?php echo $Category ?>&SubCategory=<?php echo $SubCategory ?>&Article=<?php echo $Article ?>&Layout=<?php echo $Layout ?>&VarPosition=<?php echo $rowposition?>&cparea=<?php echo $cparea ?>&SelectArea=<?php if($FilterPosition <>'current') echo $FilterPosition ?>&SortBy="+ $(this).val();
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
                            <label>Filter by <?php echo $cparea ?>: <select name="SelectArea_<?php echo $cpareastyle ?>" id="SelectArea_<?php echo $cpareastyle ?>" onchange="MM_SelectArea(this)">
                            
                            <?php
                            //echo $Layout;
                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                            $resultC = $db->query("SELECT * FROM RED_".$cparea." ORDER BY CreationDate DESC");
                            //echo ($resultC->num_rows);
                            if($FilterPosition==='current'){
								if(($Section<>'' && $Article <> $Section) && $cparea==='Sections')
								$Selected=$Section;
								elseif(($Category<>'' && $Article<> $Category) && $cparea==='Categories')
								$Selected=$Category;
								elseif(($SubCategory<>'' && $Article<>$SubCategory) && $cparea==='SubCategories')
								$Selected=$SubCategory;
								echo '<option value="" selected="selected">'.$Selected.'</option>';
							}
							while($row4 = mysqli_fetch_assoc($resultC))
                            {
                           		$areaname=$row4[$cparea];
								$availpos=$row4['Layout'];
								if($areaname===$FilterPosition)
								echo '<option value="'.$areaname.','.$availpos.'" selected="selected">'.$areaname.'</option>';
								else
								echo '<option value="'.$areaname.','.$availpos.'">'.$areaname.'</option>';
                            }
                            ?>
                            </select>
                            
							<?php
							// sort by
							switch ($SortBy)
							{
								case 'Article Title ▲':
								$SortByQ='ORDER BY Title ASC';
								break;
								
								case 'Article Title ▼':
								$SortByQ='ORDER BY Title DESC';
								break;
								
								case 'Article Title':
								$SortByQ='ORDER BY Title ASC';
								break;
								
								//
								
								case 'Pos ▲':
								
								$SortByQ='ORDER BY '.$rowposition.' ASC';
								//echo '1';
								break;
								
								case 'Pos ▼':
								
								$SortByQ='ORDER BY '.$rowposition.' DESC';
								//echo '2';
								break;
								
								case 'Pos':
								
								$SortByQ='ORDER BY '.$rowposition.' ASC';
								//echo '2';
								break;
								
								//
								
								case 'Comp ▲':
								$SortByQ='ORDER BY Component ASC';
								break;
								
								case 'Comp ▼':
								$SortByQ='ORDER BY Component DESC';
								break;
								
								case 'Comp':
								$SortByQ='ORDER BY Component ASC';
								break;
								
								//
								
								case 'Article ▲':
								$SortByQ='ORDER BY Article ASC';
								break;
								
								case 'Article ▼':
								$SortByQ='ORDER BY Article DESC';
								break;
								
								case 'Article':
								$SortByQ='ORDER BY Article ASC';
								break;
								
								//
								
								default:
								$SortByQ='ORDER BY Updated DESC';
								//echo '3';
								break;
							}
							$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
								switch ($FilterPosition)
								{
								case 'current':
									switch ($cparea)
									{
										case 'Sections':
										$result3 = $db->query("SELECT * FROM RED_Articles WHERE Active = 'Y' AND (".$cparea."='".$Section."') ".$SortByQ."");
										//echo "SELECT * FROM RED_Articles WHERE Active = 'Y' AND (".$cparea."='".$Section."') ".$SortByQ."";
										break;
										
										case 'Categories':
										if($Category<>'')
										$result3 = $db->query("SELECT * FROM RED_Articles WHERE Active = 'Y' AND (".$cparea."='".$Category."') ".$SortByQ."");
										//echo "SELECT * FROM RED_Articles WHERE Active = 'Y' AND (".$cparea."='".$Category."') ".$SortByQ."";
										break;
										
										case 'SubCategories':
										if($SubCategory<>'')
										$result3 = $db->query("SELECT * FROM RED_Articles WHERE Active = 'Y' AND (".$cparea."='".$SubCategory."') ".$SortByQ."");
										//echo "SELECT * FROM RED_Articles WHERE Active = 'Y' AND (".$cparea."='".$SubCategory."') ".$SortBy."";
										break;
										

									}
									$Counter=$result3->num_rows;
									if (!$Counter)
									$Counter='0';
									
									break;
									
									default:
									$result3 = $db->query("SELECT * FROM RED_Articles WHERE ".$cparea." = '".$FilterPosition."' AND Active = 'Y' ".$SortByQ."");
									$Counter=$result3->num_rows;
									//echo "SELECT * FROM RED_Articles WHERE ".$cparea." = '".$FilterPosition."' AND Active = 'Y' ".$SortByQ."";
									break;
								}
							
							echo '<font color="black" style="font-weight:normal;"> There are <strong>'. $Counter.'</strong> related Articles.</font> ';
							if($Counter>0){
							 $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
							$resultC = $db->query("SELECT Positions FROM RED_Layouts WHERE UniqueName='".$Layout."'");
							//echo ($resultC->num_rows);
							while($row4 = mysqli_fetch_assoc($resultC))
							{
								$Positions=$row4['Positions'];
							}
							echo '&nbsp; <font color="black" style="font-weight:normal;"> This '.$singularcparea.' uses the Layout <strong>'.$Layout.'</strong> which has <strong>'.$Positions.'</strong> Positions. </font>';
							
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
								while($row3 = mysqli_fetch_assoc($result3))
                                {
                                $RecordID=$row3['RecordID'];
                                $Title=$row3['Title'];
                                $Component=$row3['Component'];
								$HomeFeature=$row3['HomeFeature'];
								$This->Sections=$row3['Sections'];
								$This->Position=$row3[$rowposition];
								$This->Categories=$row3['Categories'];
								$This->SubCategories=$row3['SubCategories'];
								$This->Article=$row3['Article'];
								$Updated=$row3['Updated'];
								
								
								// COMPARE SESSION 'AdminComponents' WITH RED_COMPONENTS.
                                // IF VALUE EXIST THEN SHOW UPDATE BUTTON. IF NOT, DISPLAY MESSAGE FOR "ADMIN NOT AUTHORIZED TO UPDATE".
								//echo $_SESSION['AdminComponents'];
                                $This->AdminComponents = explode(",", $_SESSION['AdminComponents']);
                                //echo($_SESSION['AdminComponents'].'='.count($This->AdminComponents).'<br/>');
                                for ($w=0; $w<=count($This->AdminComponents); $w++)
                                {
                                    //echo 'Component = '.$This->AdminComponents[$w].'<br/>';
									$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                                    $resultC = $db->query("SELECT CompGroup FROM RED_Components WHERE RecordID='".$This->AdminComponents[$w]."' AND UniqueName='".$Component."'");
                                    $row = mysqli_fetch_assoc($resultC);
                                    $CompGroup=$row['CompGroup'];
                                    
                                    switch ($CompGroup){ // CHECK IF THIS IS A GROUP COMPONENT. I.E:FORM, GALLERY, SUBMENU.  GET RECORDID
                                        case 'Y':
                                        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                                        $resultE = $db->query("SELECT RecordID FROM RED_C_".$Component." WHERE RefID='".$RecordID."'");
                                        $row = $resultE->num_rows;
                                        $CRecordID=$row['RecordID'];
                                        break;
                                    }
                                    
                                    if(($resultC->num_rows==0)&&($w==count($This->AdminComponents))){
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
										echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox_'.$cparea.'" name="Articles_Sel['.$w.']" value="'.$RecordID.'"" />'.$Title;
										if ($HomeFeature=='Y')
										echo '<font color="red">*</font></label></label>';
										else
										echo '</label>';
										echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
										echo '</div>';
										echo '<div class="grid_1" style="text-align:center; margin-left:0px; margin-right:0px;">';
										settype($Positions, "integer");
										if($This->Position > $Positions){
											echo '<font color="purple">';
											echo $This->Position;
											echo '<sup>&#8224;</sup>';
											echo '</font>';
										}else
											echo $This->Position;
										echo '&nbsp;</div>';
										echo '<div class="grid_1 comp" style="text-align:center;margin-left:0px; margin-right:0px;">'.$Component.'&nbsp;</div>';
										 echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
											if ($cparea==='Sections'){
													if(($This->Position <> 0) and  ($This->Position <= $Positions)){
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
													if(($This->Position <> 0) and  ($This->Position <= $Positions)){
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
													if(($This->Position <> 0) and  ($This->Position <= $Positions)){
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
                                
                                    break;
									
									}elseif(($resultC->num_rows==0));
                                    else{
                                        //echo $w.' ADMINISTRATOR AUTHORIZED TO UPDATE<br />';
                                        switch ($CompGroup){ // CHECK IF THIS IS A GROUP COMPONENT. I.E:FORM, GALLERY, SUBMENU.
                                        case 'Y':
                                            echo '<script language="JavaScript" type="text/javascript">'. "\n";
                                            echo '<!--' ."\n";
                                            echo 'function edit_filter_content_'.$Alias.'_'.$RecordID.' (RecordID,CRecordID){'. "\n";
                                            echo '$.ajax({'. "\n";
                                            echo 'type: "POST", '. "\n";
                                            echo 'url: "/admin/bin/edit_'.strtolower($Component).'.php", '. "\n";
                                            echo 'data: "RecordID=" + CRecordID + "&ArtRecordID=" + RecordID +"&Layout='.$Layout.'&VarPosition='.$rowposition.'", '. "\n";
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
											echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox_'.$cparea.'" name="Articles_Sel['.$w.']" value="'.$RecordID.'"" />'.$Title;
											if ($HomeFeature=='Y')
											echo '<font color="red">*</font></label></label>';
											else
											echo '</label>';
											echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
											echo '<input type="hidden" name="VarPosition" value="'.$This->Position.'" />';
											echo '</div>';
											echo '<div class="grid_1" style="text-align:center; margin-left:0px; margin-right:0px;">';
											settype($Positions, "integer");
											if($This->Position > $Positions){
												echo '<font color="purple">';
												echo $This->Position;
												echo '<sup>&#8224;</sup>';
												echo '</font>';
											}else
											echo $This->Position;
											echo '&nbsp;</div>';
											echo '<div class="grid_1 comp" style="text-align:center;margin-left:0px; margin-right:0px;">'.$Component.'&nbsp;</div>';
											echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
												if ($cparea==='Sections'){
													if(($This->Position <> 0) and  ($This->Position <= $Positions)){
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
													if(($This->Position <> 0) and  ($This->Position <= $Positions)){
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
													if(($This->Position <> 0) and  ($This->Position <= $Positions)){
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
                                        echo 'url: "/admin/bin/edit_'.strtolower($Component).'.php", '. "\n";
                                        echo 'data: "RecordID=" + RecordID + "&Layout='.$Layout.'&VarPosition='.$rowposition.'", '. "\n";
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
											echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox_'.$cparea.'" name="Articles_Sel['.$w.']" value="'.$RecordID.'"" />'.$Title;
											if ($HomeFeature=='Y')
											echo '<font color="red">*</font></label></label>';
											else
											echo '</label>';
											echo '<input type="hidden" name="RecordID['.$w.']" value="'.$RecordID.'" />';
											echo '<input type="hidden" name="VarPosition" value="'.$This->Position.'" />';
											echo '</div>';
											echo '<div class="grid_1" style="text-align:center; margin-left:0px; margin-right:0px;">';
											settype($Positions, "integer");
											if($This->Position > $Positions){
												echo '<font color="purple">';
												echo $This->Position;
												echo '<sup>&#8224;</sup>';
												echo '</font>';
											}else
												echo $This->Position;
											echo '&nbsp;</div>';
											echo '<div class="grid_1 comp" style="text-align:center;margin-left:0px; margin-right:0px;">'.$Component.'&nbsp;</div>';
											 echo '<div class="grid_2" style="text-align:center; margin-left:0px">';
												if ($cparea==='Sections'){
													if(($This->Position <> 0) and  ($This->Position <= $Positions)){
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
													if(($This->Position <> 0) and  ($This->Position <= $Positions)){
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
													if(($This->Position <> 0) and  ($This->Position <= $Positions)){
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
                                    break;
                                }
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
                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                            $result3 = $db->query("SELECT Sections, Features FROM RED_Sections WHERE Active='Y'");
                            while($row3 = mysqli_fetch_assoc($result3))
                            {
                            $This->Section=$row3['Sections'];
                            echo '<option value="'.$This->Section.'">'.$This->Section.'</option>';
                            }
                            ?>
                            </select></label>
    						</p>
                            
                            <p>
                            <label>Category: <select name="Categories">
                            <option value="">Select...</option>
                            <?php
                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                            $result3 = $db->query("SELECT Categories, Features FROM RED_Categories WHERE Active='Y'");
                            while($row3 = mysqli_fetch_assoc($result3))
                            {
                            $This->Categories=$row3['Categories'];
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
                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                            $result3 = $db->query("SELECT SubCategories, Features FROM RED_SubCategories WHERE Active='Y'");
                            while($row3 = mysqli_fetch_assoc($result3))
                            {
                            $This->SubCategories=$row3['SubCategories'];
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
                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                             $result3 = $db->query("SELECT Title, Alias FROM RED_Articles WHERE  Active = 'Y' ORDER BY Updated DESC");
                            while($row3 = mysqli_fetch_assoc($result3))
                            {
                            $This->Title=$row3['Title'];
							$This->Alias=$row3['Alias'];
                            echo '<option value="'.$This->Alias.'">'.$This->Title.'</option>';
                            }
                            ?>
                            <option value="-">N/A</option>
                            </select>
                            </label>
                            </p>
                            
                            
                            
                       
                <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo $_SESSION['alias']?>" />
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