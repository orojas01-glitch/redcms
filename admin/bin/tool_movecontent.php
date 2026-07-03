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
		$Layout=preg_replace ( "'<[^>]+>'U", "", $_POST['Layout']);
		$cparea=preg_replace ( "'<[^>]+>'U", "", $_POST['cparea']);
		$cpareastyle=strtolower($cparea);
		$compgroup=preg_replace ( "'<[^>]+>'U", "", $_POST['compgroup']);
        if (isset($_POST['SortBy'])) {
        $SortBy = preg_replace("'<[^>]+>'U", "", $_POST['SortBy']);
        } else {
            $SortBy = ''; // or any default value you want
        }

        if (isset($_POST['SelectPosition'])) {
            $FilterPosition = preg_replace("'<[^>]+>'U", "", $_POST['SelectPosition']);
        } else {
            $FilterPosition = ''; // or any default value you want
        }
		
		switch ($cparea){
		case 'Sections':
		$rowposition='SectionPosition';
		break;
		case 'Categories':
		$rowposition='CategoryPosition';
		break;
		case 'SubCategories':
		$rowposition='SubCategoryPosition';
		break;
		default:
		$rowposition=$VarPosition;
		
		break;
		
		}
		
		if ($FilterPosition === ''){
		$FilterPosition= 'all';
		if ($Section==='home')
		$rowposition='HomePosition';
		
		}
		
		$xquery=new Build_Query();
		$rquery=$xquery->cp_get_query($CountPage, $Section, $Category, $SubCategory, $Article);
		$articlequeryfilter=$rquery[0];
		/*$this->VarPosition=$rquery[1];
		$this->VarFeatures=$rquery[2];	
		$this->metaquery=$rquery[3];
		$this->Table=$rquery[4];*/
		
		/*echo $articlequeryfilter.'<br/>';
		echo $this->VarFeatures.'<br/>';
		echo $this->VarPosition;*/

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
	//else
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
	var dataString = "Item=Reload&CountPage=<?php echo $CountPage ?>&Section=<?php echo $Section ?>&Category=<?php echo $Category ?>&SubCategory=<?php echo $SubCategory ?>&Article=<?php echo $Article ?>&Layout=<?php echo $Layout ?>&VarPosition=<?php echo $VarPosition?>&cparea=<?php echo $cparea ?>&SelectPosition=" + SelectPosition.options[SelectPosition.selectedIndex].value;
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
	var dataString = "CountPage=<?php echo $CountPage ?>&Section=<?php echo $Section ?>&Category=<?php echo $Category ?>&SubCategory=<?php echo $SubCategory ?>&Article=<?php echo $Article ?>&Layout=<?php echo $Layout ?>&VarPosition=<?php echo $VarPosition?>&cparea=<?php echo $cparea ?>&SelectPosition=<?php if($FilterPosition <>'current') echo $FilterPosition ?>&SortBy="+ $(this).val();
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
                            //echo $Layout;
                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                            $resultA = $db->query("SELECT Positions FROM RED_Layouts WHERE UniqueName='".$Layout."'");
                            //echo ($resultC->num_rows);
                            while($row4 = mysqli_fetch_assoc($resultA))
                            {
                            $Positions=$row4['Positions'];
                            }
                            //echo $Positions;
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
								if($Section==='home')
								$SortByQ='ORDER BY HomePosition ASC';
								else
								$SortByQ='ORDER BY '.$rowposition.' ASC';
								//echo '1';
								break;
								
								case 'Pos ▼':
								if($Section==='home')
								$SortByQ='ORDER BY HomePosition DESC';
								else
								$SortByQ='ORDER BY '.$rowposition.' DESC';
								//echo '2';
								break;
								
								case 'Pos':
								if($Section==='home')
								$SortByQ='ORDER BY HomePosition ASC';
								else
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
								
								
								default:
								$SortByQ='ORDER BY Updated DESC';
								//echo '3';
								break;
							}
							
							$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                            switch ($FilterPosition)
                            {
                            case 'all':
                                $result3 = $db->query("SELECT * FROM RED_Articles WHERE Active = 'Y' ".$articlequeryfilter." ".$SortByQ."");
                             break;
                            
                            default:
                                $result3 = $db->query("SELECT * FROM RED_Articles WHERE ".$VarPosition." = '".$FilterPosition."' ".$articlequeryfilter." AND Active = 'Y' ".$SortByQ."");	
                            break;
                            }	
                            
                            echo '<div class="wrapper row"><label><input type="checkbox" id="selecctall1"/> Select All</label></div>';
                            $w=0;
                            while($row3 = mysqli_fetch_assoc($result3))
                            {
                                $RecordID=$row3['RecordID'];
                                $Alias=$row3['Alias'];
								$Alias=preg_replace('/-/','_',$Alias);
								$Title=$row3['Title'];
                                $Component=$row3['Component'];
                                $HomeFeature=$row3['HomeFeature'];
                                $ThisVarPosition=$row3[$VarPosition];
								$Updated=$row3['Updated'];
                                
                                // COMPARE SESSION 'AdminComponents' WITH RED_COMPONENTS.
                                // IF VALUE EXIST THEN SHOW UPDATE BUTTON. IF NOT, DISPLAY MESSAGE FOR "ADMIN NOT AUTHORIZED TO UPDATE".
								//echo $_SESSION['AdminComponents'];
                                $ThisAdminComponents = explode(",", $_SESSION['AdminComponents']);
                                //echo($_SESSION['AdminComponents'].'='.count($ThisAdminComponents).'<br/>');
                                for ($w=0; $w<=count($ThisAdminComponents); $w++)
                                {
                                    //echo 'Component = '.$ThisAdminComponents[$w].'<br/>';
									$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                                    $resultC = $db->query("SELECT CompGroup FROM RED_Components WHERE RecordID='".$ThisAdminComponents[$w]."' AND UniqueName='".$Component."'");
                                    if ($resultC && mysqli_num_rows($resultC) > 0) {
                                    $row = mysqli_fetch_assoc($resultC);
                                    $CompGroup=$row['CompGroup'];
                                    }
                                    switch ($CompGroup) { // CHECK IF THIS IS A GROUP COMPONENT. I.E: FORM, GALLERY, SUBMENU. GET RECORDID
                                    case 'Y':
                                        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                                        $resultE = $db->query("SELECT RecordID FROM RED_C_" . $Component . " WHERE RefID='" . $RecordID . "'");
                                        if ($resultE && $resultE->num_rows > 0) {
                                            // Fetch the actual row as an associative array
                                            $row = mysqli_fetch_assoc($resultE);
                                            $CRecordID = $row['RecordID'];
                                        } else {
                                            // Handle the case when no row is returned
                                            $CRecordID = ''; // Or handle it in another appropriate way
                                        }
                                        break;
                                }
                                    
                                    if(($resultC->num_rows==0)&&($w==count($ThisAdminComponents))){
                                        //echo ' ADMINISTRATOR NOT AUTHORIZED TO UPDATE<br />';
                                        echo '<script type="text/javascript">'. "\n";
                                        echo '<!--' ."\n";
                                        echo 'function edit_move_content_'.$Alias.'_'.$RecordID.' (){'. "\n";
                                        echo '$(\'#msggbox_tool\').html("You\'re not authorized to edit this content.")'. "\n";
                                        echo '.fadeIn(1500, function() {'. "\n";
                                        echo '});'. "\n";
                                        echo 'return false;'. "\n";
                                        echo '}'. "\n";
                                        echo '-->'. "\n";
                                        echo '</script>';
                               
                               
                               
                                        echo '<div class="wrapper row">';
                                        echo '<div class="col-lg-6 col-md-6 col-sm-6">';
                                        echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox1" name="Articles_Sel['.$w.']" value="'.$RecordID.'"" />'.$Title;
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
                                        echo '<img src="/admin/images/ico_edit.png" onClick="edit_move_content_'.$Alias.'_'.$RecordID.'();" title="Edit" style="cursor:pointer">';
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
                                            echo 'function edit_move_content_'.$Alias.'_'.$RecordID.' (RecordID,CRecordID){'. "\n";
                                            echo '$.ajax({'. "\n";
                                            echo 'type: "POST", '. "\n";
                                            echo 'url: "/admin/bin/edit_'.strtolower($Component).'.php", '. "\n";
                                            echo 'data: "RecordID=" + CRecordID + "&ArtRecordID=" + RecordID +"&Layout='.$Layout.'&VarPosition='.$VarPosition.'", '. "\n";
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
                                            echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox1" name="Articles_Sel['.$w.']" value="'.$RecordID.'"" />'.$Title;
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
                                            echo '<img src="/admin/images/ico_edit.png" onClick="javascript:showdiv(\'editcontent\'); edit_move_content_'.$Alias.'_'.$RecordID.'(' .$RecordID . ','.$CRecordID.');" title="Edit" style="cursor:pointer">';
                                            echo '</div>';
                                            echo '</div>';
                                            $w++;
                                        break;
                                    
                                        default:
                                        
                                        echo '<script language="JavaScript" type="text/javascript">'. "\n";
                                        echo '<!--' ."\n";
                                        echo 'function edit_move_content_'.$Alias.'_'.$RecordID.' (RecordID){'. "\n";
                                        echo '$.ajax({'. "\n";
                                        echo 'type: "POST", '. "\n";
                                        echo 'url: "/admin/bin/edit_'.strtolower($Component).'.php", '. "\n";
                                        echo 'data: "RecordID=" + RecordID + "&Layout='.$Layout.'&VarPosition='.$VarPosition.'", '. "\n";
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
                                        echo '<label id="checkbox" title="'.$Title.' | '.$Updated.'"><input type="checkbox" class="checkbox1" name="Articles_Sel['.$w.']" value="'.$RecordID.'"" />'.$Title;
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
                                        echo '<img src="/admin/images/ico_edit.png" onClick="javascript:showdiv(\'editcontent\'); edit_move_content_'.$Alias.'_'.$RecordID.'(' .$RecordID . ');" title="Edit" style="cursor:pointer">';
                                        echo '</div>';
                                        echo '</div>';
                                        $w++;
                                        
                                    break;
                                        }
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
                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                            $result3 = $db->query("SELECT Sections, Features FROM RED_Sections WHERE Active='Y'");
                            while($row3 = mysqli_fetch_assoc($result3))
                            {
                            $ThisSection=$row3['Sections'];
                            echo '<option value="'.$ThisSection.'">'.$ThisSection.'</option>';
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
                            </select>
                            </label>
                            </p>
                            
                             <p>
                            <label>Article: <select name="Article">
                            <option value="">Select...</option>
                            <?php
                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                             $result3 = $db->query("SELECT Title, Alias FROM RED_Articles WHERE Active = 'Y' AND Component='Article' ORDER BY Updated DESC");
                            while($row3 = mysqli_fetch_assoc($result3))
                            {
                            $Title=$row3['Title'];
							$ThisAlias=$row3['Alias'];
                            echo '<option value="'.$ThisAlias.'">'.$ThisAlias.'</option>';
                            }
                            ?>
                            </select>
                            </label>
                            </p>
                            <span id="cp_step">
                        	<h4 class="pt-3"><p>Select Position:</h4></span>
                            <?php
							 $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
							$resultC = $db->query("SELECT Positions FROM RED_Layouts WHERE UniqueName='".$Layout."'");
							//echo ($resultC->num_rows);
							while($row4 = mysqli_fetch_assoc($resultC))
							{
								$Positions=$row4['Positions'];
							}
							echo '<p>Current Page uses the Layout <strong>'.$Layout.'</strong>, <br/>which has <strong>'.$Positions.'</strong> Positions</p>';
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
                 
                <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo $_SESSION['alias']?>" />
                <input type="hidden" name="VarPosition" value="<?php echo $VarPosition ?>" />
                <input type="submit" name="submit" value="Update" id="save"/>
                <span id="msggbox_tool_content" style="display:none"></span>
                </fieldset>
                </form> 
            </div>
        </div>

</div>

<?php

		}
?>