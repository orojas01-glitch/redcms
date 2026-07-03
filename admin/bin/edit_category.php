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
		$RecordID=preg_replace ( "'<[^>]+>'U", "", $_POST['RecordID']);
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		//echo "SELECT * FROM RED_Articles WHERE RecordID='".$recordid."'";
		$result = $db->query("SELECT * FROM RED_Categories WHERE RecordID='".$RecordID."'");
		$result_counter = $result->num_rows;
		while($row = mysqli_fetch_assoc($result))
		{
		$layout=$row['Layout'];
		$Features=$row['Features'];		
?>
<!-- The main script file -->
<script type="text/javascript">
<!--
function run_update_category (update_category)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/update_category.php", 
	data: $("#update_category").serialize(),
	success: function(data) {
	//alert (data);
	//return false;
	if (data=='yes' || data=='updateupdateyes'|| data=='updateupdateupdateyes'|| data=='updateyes')
	{
	if (data=='updateupdateyes'|| data=='updateupdateupdateyes'|| data=='updateyes'){
	alert ('Your Category name was updated.\n NOTE: \'Articles\' and \'Menu Links\' owned by the previous Category were updated.');
	}
	$('#msggbox_update_category').html("Category Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_category');
	window.location.reload();
	});
	}
	else if(data=='error')
	{
	alert ('There is a Section using the same name.  Please enter a different Category Name.');	
	}
	else if(data=='error2')
	{
	alert ('There is a Category using the same name.  Please enter a different Category Name.');	
	}
	else if(data=='error3')
	{
	alert ('There is a SubCategory using the same name.  Please enter a different Category Name.');	
	}
	else
	{
	$('#msggbox_update_category').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_category');
	});
	}
	}
	});
	return false;
}
//-->
<!--
function run_deleterecord (RecordID)
{
	$(document).ready(function(){           
   $('#deleterecord_'+RecordID).click(function(){
      if(confirm("Are you sure you want to delete this Record? It can't be recovered.")){
         //alert('Successful Request!');
		  $.ajax({ 
		type: "POST", 
		url: "/admin/bin/delete_label.php", 
		data: "RecordID=" + RecordID + "&T=categories",
		success: function(data) {
		//alert (data);
		//return false;
		if (data=='yes')
		{
		$('#msggbox_deleterecord').html("Record Deleted.")
		.hide()
		.fadeIn(1500, function() {
		$('#msggbox_deleterecord');
		window.location.reload();
		});
		}
		else
		{
		$('#msggbox_deleterecord').html("&nbsp; Error. Please try again.")
		.hide()
		.fadeIn(1500, function() {
		$('#msggbox_deleterecord');
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
</script>
<style>
label{color:#000}
</style>
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('edit_category_grid');">Show Categories</a></div>
<form id="update_category" name="update_category" class="cp" method="post" onSubmit="return run_update_category(this);">
<fieldset>
<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
        <div class="wrapper">
         <div class="titleleft">
            	<label>Category Title: <input name="Title" type="text" id="title" value="<?php echo $row['Title']?>" /></label>
            </div>
            <div class="titleleft">
            	<label>Alias: <input name="Categories" type="text" id="title" value="<?php echo $row['Categories']?>" /></label>
            </div>
            <div class="titleright">
            	<a href="#" id="deleterecord_<?php echo $RecordID ?>"><img src="/admin/images/ico_trashcan.png" onClick="run_deleterecord(<?php echo $RecordID ?>);" title="Delete Record" style="cursor:pointer"></a>
             </div>
            <div class="titleright">
            	<label style="display:inline;">Active: <select name="Active">
                <option value="Y" <?php if ($row['Active']=='Y') echo 'selected="selected"'?>>Y</option>
                <option value="N" <?php if ($row['Active']=='N') echo 'selected="selected"'?>>N</option>
                </select>
                </label>
            </div>
        </div>
        <div class="wrapper">
            <div class="titleright">
            	<span id="msggbox_deleterecord" style="display:none"></span>
        	</div>
        </div>
        <div class="wrapper">
            <div class="titleleft">
                <label style="display:inline;">Layout:
                
                <?php
                echo '<select name="Layout" id="layout">';
                $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                $result = $db->query("SELECT UniqueName FROM RED_Layouts");
                while($row2 = mysqli_fetch_assoc($result))
                {
                    $thislayout=$row2['UniqueName'];
                    if ($thislayout===$layout)
                    echo '<option value="'.$thislayout.'" selected="selected">'.$thislayout.'</option>';
                    else
                    echo '<option value="'.$thislayout.'">'.$thislayout.'</option>';
                }
                $db->close();
                
                echo '</select>';
				?>
                </label>  
            </div>
            <div class="titleright">
                <label style="display:inline;" title="Articles Limit">Articles Limit: <input name="QueryLimit" type="text" id="limit" value="<?php echo $row['QueryLimit']?>" /></label>
            </div>
            
        </div>
        <div class="wrapper">
            <div class="titleleft">
            	<label>Features:
                    <select name="Features[]" size="3" multiple>
                    <?php
                    global $selected;
                    $feature=explode(',', $Features);
                    $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                    $result3 = $db->query("SELECT UniqueName FROM RED_Features");
                    $result_counter = $result3->num_rows;
                    while($row3 = mysqli_fetch_assoc($result3))
                    {
                        
                        for ($t=0; $t<count($feature); $t++)
                        {
                            //echo '<option value="'.$t.'">'.$t.'</option>';
                            if ($feature[$t]===$row3['UniqueName']){
                                $selected='selected="selected"';
                                break;
                            }
                        }
                        echo '<option value="'.$row3['UniqueName'].'" '.$selected.'>'.$row3['UniqueName'].'</option>';
                    $selected='';
                    $result_counter = ($result_counter - 1);
                    }
                    ?>
                    </select>
                </label>
            </div>
			<div class="titleright">
            	<label style="display:inline;">Access Level: <select name="AccessLevel">
                <option value="Public" <?php if ($row['AccessLevel']=='Public') echo 'selected="selected"'?>>Public</option>
                <option value="Private" <?php if ($row['AccessLevel']=='Private') echo 'selected="selected"'?>>Private</option>
                </select>
                </label> 
            </div>
        </div>
         
         <label>Tags:
        <input name="Tags" type="text" id="tags" value="<?php echo $row['Tags']?>" /></label>
                 
        <label>Long Description:
        <textarea name="Description" id="ShortDesc" cols="" rows="4"><?php echo $row['Description']?></textarea></label>
		 <div class="wrapper">
         	<div class="titleleft">
                
                <?php
                $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                 $result3 = $db->query("SELECT Title, Alias, Active FROM RED_Articles WHERE Categories='".$row['Categories']."' ORDER BY Updated DESC");
                 echo '<label>Related articles:  '.$result3->num_rows.'';
				 	/*if($result3->num_rows>0){
						echo '</label><dl class="cp_slideDown">';
						echo '<dt>More</dt> ';
						echo '<dd>';
						while($row3 = mysqli_fetch_assoc($result3))
						{
						echo $row3['Title'] . ','. $row3['Alias'] .',' . $row3['Active'] . '<br />';
						}
						echo '</dd>';
         				echo '</dl>';
					}else*/
					echo '</label>';
                ?>
                
            </div>
         </div>
                
         <input type="hidden" name="RecordID" id="RecordID" value="<?php echo $RecordID ?>" />
         <input type="hidden" name="CurrentCategory" id="CurrentCategory" value="<?php echo $row['Categories']?>" />
        <input type="submit" name="submit" value="Save" id="save"/> <span id="msggbox_update_category" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
</form>
<?php
		}
		$db->close();
		}
?>