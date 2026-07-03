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
		$Type=preg_replace ( "'<[^>]+>'U", "", $_POST['Type']);
		$CountPage=preg_replace ( "'<[^>]+>'U", "", $_POST['CountPage']);
		$Section=preg_replace ( "'<[^>]+>'U", "", $_POST['Section']);
		$Category=preg_replace ( "'<[^>]+>'U", "", $_POST['Category']);
		$SubCategory=preg_replace ( "'<[^>]+>'U", "", $_POST['SubCategory']);
		$VarPosition=preg_replace ( "'<[^>]+>'U", "", $_POST['VarPosition']);
		$Language=preg_replace ( "'<[^>]+>'U", "", $_POST['Language']);
		$Layout=preg_replace ( "'<[^>]+>'U", "", $_POST['Layout']);
        $Article=preg_replace ( "'<[^>]+>'U", "", $_POST['Article']);
        
		$RecordID=mt_rand();

?>
<!-- Our CSS stylesheet file -->
<link rel="stylesheet" href="/admin/assets/css/styles.css" />

<!--[if lt IE 9]>
  <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
<![endif]-->
<!-- Including the HTML5 Uploader plugin -->
<script src="/admin/assets/js/jquery.filedrop.js"></script>
<script src="/admin/assets/js/jquery.filedrop2.js"></script>
<!-- The main script file -->
<script type="text/javascript">
<!--
$(function(){
$(".cp_slideDown dt").click(function(){$(this).toggleClass("active").parent(".cp_slideDown").find("dd").slideToggle()})
})
//-->
<!--
$(function(){
	var dropbox = $('#dropbox'),
		message = $('.message', dropbox);
	
	dropbox.filedrop({
		// The name of the $_FILES entry:
		paramname:'pic',
		maxfiles: 1,
    	maxfilesize: 6,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $RecordID ?>&UC=BigPict&Insert=true&Language=<?php echo $Language?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_insert_content').html("&nbsp; Pictures Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_insert_content');
			//window.location.reload();
			});
		},
		
    	error: function(err, file) {
			switch(err) {
				case 'BrowserNotSupported':
					showMessage('Your browser does not support HTML5 file uploads!');
					break;
				case 'TooManyFiles':
					alert('Too many files! Please select 1 at most!');
					break;
				case 'FileTooLarge':
					alert(file.name+' is too large! Please upload files up to 2mb.');
					break;
				default:
					break;
			}
		},
		
		// Called before each upload is started
		beforeEach: function(file){
			if(!file.type.match(/^image\//)){
				alert('Only images are allowed!');
				
				// Returning false will cause the
				// file to be rejected
				return false;
			}
		},
		
		uploadStarted:function(i, file, len){
			createImage(file);
		},
		
		progressUpdated: function(i, file, progress) {
			$.data(file).find('.progress').width(progress);
		}
    	 
	});
	
	var template = '<div class="preview">'+
						'<span class="imageHolder">'+
							'<img />'+
							'<span class="uploaded"></span>'+
						'</span>'+
						'<div class="progressHolder">'+
							'<div class="progress"></div>'+
						'</div>'+
					'</div>'; 
	
	
	function createImage(file){

		var preview = $(template), 
			image = $('img', preview);
			
		var reader = new FileReader();
		
		image.width = 100;
		image.height = 100;
		
		reader.onload = function(e){
			
			// e.target.result holds the DataURL which
			// can be used as a source of the image:
			
			image.attr('src',e.target.result);
		};
		
		// Reading the file as a DataURL. When finished,
		// this will trigger the onload function above:
		reader.readAsDataURL(file);
		
		message.hide();
		preview.appendTo(dropbox);
		
		// Associating a preview container
		// with the file, using jQuery's $.data():
		
		$.data(file,preview);
	}

	function showMessage(msg){
		message.html(msg);
	}

});
//-->
<!--
$(function(){
	var dropbox2 = $('#dropbox2'),
		message2 = $('.message2', dropbox2);
	
	dropbox2.filedrop2({
		// The name of the $_FILES entry:
		paramname:'pic',
		maxfiles: 1,
    	maxfilesize: 6,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $RecordID ?>&UC=SmallPict&Insert=true&Language=<?php echo $Language?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_insert_content').html("&nbsp; Picture Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_insert_content');
			//window.location.reload();
			});
		},
		
    	error: function(err, file) {
			switch(err) {
				case 'BrowserNotSupported':
					showMessage('Your browser does not support HTML5 file uploads!');
					break;
				case 'TooManyFiles':
					alert('Too many files! Please select 1 at most!');
					break;
				case 'FileTooLarge':
					alert(file.name+' is too large! Please upload files up to 2mb.');
					break;
				default:
					break;
			}
		},
		
		// Called before each upload is started
		beforeEach: function(file){
			if(!file.type.match(/^image\//)){
				alert('Only images are allowed!');
				
				// Returning false will cause the
				// file to be rejected
				return false;
			}
		},
		
		uploadStarted:function(i, file, len){
			createImage(file);
		},
		
		progressUpdated: function(i, file, progress) {
			$.data(file).find('.progress').width(progress);
		}
    	 
	});
	
	var template = '<div class="preview">'+
						'<span class="imageHolder">'+
							'<img />'+
							'<span class="uploaded"></span>'+
						'</span>'+
						'<div class="progressHolder">'+
							'<div class="progress"></div>'+
						'</div>'+
					'</div>'; 
	
	
	function createImage(file){

		var preview = $(template), 
			image = $('img', preview);
			
		var reader = new FileReader();
		
		image.width = 100;
		image.height = 100;
		
		reader.onload = function(e){
			
			// e.target.result holds the DataURL which
			// can be used as a source of the image:
			
			image.attr('src',e.target.result);
		};
		
		// Reading the file as a DataURL. When finished,
		// this will trigger the onload function above:
		reader.readAsDataURL(file);
		
		message2.hide();
		preview.appendTo(dropbox2);
		
		// Associating a preview container
		// with the file, using jQuery's $.data():
		
		$.data(file,preview);
	}

	function showMessage(msg){
		message2.html(msg);
	}

});
//-->
<!--
function run_insert_content (insert_content)
{
	tinyMCE.triggerSave();
	// ** START **
	var Title=insert_content.Title.value
	if (Title == "") {
	alert ("*Please enter a valid Title."); 
	insert_content.Title.focus();
	return false ;
	}
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/insert_content.php", 
	data: $("#insert_content").serialize(),
	success: function(data) {
	//alert (data);
	//return false;
	if (data=='yes')
	{
	$('#msggbox_insert_content').html("&nbsp; Content Added.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_content');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_insert_content').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_content');
	});
	}
	}
	});
	return false;
}
//-->
</script>


<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('add_content_grid');">Show Menu</a> | New Other</div>
<form id="insert_content" name="insert_content" class="cp" method="post" onSubmit="return run_insert_content(this);">
<fieldset>

<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
        <div class="wrapper">
        
            <div class="titleleft">
            	<label>Title: <input name="Title" type="text" id="title" value="" /></label>
            </div>
            <div class="titleright">
                <label style="display:inline;">Active: <select name="Active">
                <option value="Y" selected="selected">Y</option>
                <option value="">N</option>
                </select>
                </label>
            </div>
            <div class="titleright">
                <label style="display:inline;" title="Position Order">Order: <input name="<?php echo $VarPosition.'Order'?>" type="text" id="order" value="" /></label>
            </div>
            <div class="titleright">
                <label title="Layout Position">Layout Position: <select name="<?php echo $VarPosition ?>">
                <?php
				 //echo $Layout;
				 $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$resultC = $db->query("SELECT Positions FROM RED_Layouts WHERE UniqueName='".$Layout."'");
				//echo ($resultC->num_rows);
				while($row = mysqli_fetch_assoc($resultC))
				{
					$Positions=$row['Positions'];
				}
				//echo $Positions;
				for ($w=0; $w<=$Positions; $w++)
				{
					echo '<option value="'.$w.'">'.$w.'</option>';
				}
				?>
                 </select>
                </label>
             </div>
             
        </div>
        <div class="wrapper">
            <div class="titleright">
               	<label style="display:inline;"><input name="HomeFeature" type="checkbox" value="Y" />Home Featured</label>
            </div>
        </div>
		<div class="clear-cp"></div> 	
            
            <label>Paste Code: <br />
            <textarea name="ShortDesc" id="ShortDesc" cols="" rows="12"></textarea>
            </label>
            <div class="clear-cp"></div>
            <br />

        <dl class="cp_slideDown">
 			<dt>More</dt> 
            <dd>
            <div class="wrapper">
            	<div class="titleleft">
                    <div class="titleleft">
                    <label style="width:75px" title="Used in Features">Features Picture:</label>
                    </div>
                    <div id="dropbox" style="width: 150px; min-height:80px; float:left">
                        <span class="message">Drop image <br />
        here to upload.</span>
                    </div>
                    <div class="clear-cp"></div>
                    <div class="titleleft">
                    <label style="width:75px" title="Used in Article Description or Short Articles">Small Picture:</label>
                    </div>
                    <div id="dropbox2" style="width: 150px; min-height:80px; float:left">
                        <span class="message2">Drop image <br />
        here to upload.</span>
                    </div>
                    <div class="titleleft">
                        <label>Pict Alignment:
                        <select name="SmallPictAlign">
                        <option value="">- null -</option>
                        <option value="Top">Top</option>
                        <option value="Left">Left</option>
                        <option value="Right">Right</option>
                        </select>
                        </label>
                    </div>
                    <div class="clear-cp"></div>
                </div>
                
                <div class="titleright"  style="text-align:right">
                    <label>Article Location:</label>
                    <label>Section: <select name="Sections">
                    <option value="">- null -</option>
                    <?php 
                    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                    $result3 = $db->query("SELECT Sections FROM RED_Sections WHERE Active='Y'");
                    if ($result3) {
                    while ($row3 = mysqli_fetch_assoc($result3)) {
                        if ($row3['Sections']==$Section)    
                            echo '<option value="' . $row3['Sections'] . '" selected="selected">' . $row3['Sections'] . '</option>';
                        else
                            echo '<option value="' . $row3['Sections'] . '">' . $row3['Sections'] . '</option>';
                        }
                    }
                    $db->close();
                    ?>
                    </select>
                    </label>
                    
                    
                    <label>Category: <select name="Categories">
                    <option value="">- null -</option>
                    <?php
                    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                    $result3 = $db->query("SELECT Categories FROM RED_Categories WHERE Active='Y'");
                    if ($result3) {
                    while ($row3 = mysqli_fetch_assoc($result3)) {
                        if ($row3['Categories']==$Category)    
                            echo '<option value="' . $row3['Categories'] . '" selected="selected">' . $row3['Categories'] . '</option>';
                        else
                            echo '<option value="' . $row3['Categories'] . '">' . $row3['Categories'] . '</option>';
                        }
                    }
                    $db->close();
                    ?>
                    </select>
                    </label>
                    
                    
                    <label>Sub Category: <select name="SubCategories">
                    <option value="">- null -</option>
                    <?php
                    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                    $result3 = $db->query("SELECT SubCategories FROM RED_SubCategories WHERE Active='Y'");
                    if ($result3) {
                    while ($row3 = mysqli_fetch_assoc($result3)) {
                        if ($row3['SubCategories']==$SubCategory)    
                            echo '<option value="' . $row3['SubCategories'] . '" selected="selected">' . $row3['SubCategories'] . '</option>';
                        else
                            echo '<option value="' . $row3['SubCategories'] . '">' . $row3['SubCategories'] . '</option>';
                        }
                    }
                    $db->close();
                    ?>
                    </select>
                    </label>
                    
                    <label>Article: <select name="Article">
                    <option value="">- null -</option>
                    <?php
                    
                    $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
					$result3 = $db->query("SELECT Title, Alias FROM RED_Articles WHERE Active = 'Y' AND Component='Article' ORDER BY Updated DESC");
					
                    while($row3 = mysqli_fetch_assoc($result3))
                    {
						$thisalias=$row3['Alias'];

						if (strtolower($thisalias)==strtolower($Article))
						echo '<option value="'.$row3['Alias'].'" selected="selected">'.$row3['Alias'].'</option>';
						else
						echo '<option value="'.$row3['Alias'].'">'.$row3['Alias'].'</option>';
                    }
                    ?>
                    </select>
                    </label>
                    <div class="clear-cp"></div>
                    <label title="yyyy-mm-dd hh:mm:ss" >Start Date: <input name="StartDate" type="text" id="date" value="0000-00-00 00:00:00" /></label>
                    <label title="yyyy-mm-dd hh:mm:ss">Exp Date: <input name="ExpDate" type="text" id="date" value="0000-00-00 00:00:00" /></label>
                </div>
            </div>
             <!--<div class="wrapper">
                <div class="titleleft">
                </div>
                <div class="titleright"  style="text-align:right">
                </div>
             </div>
             <br />-->
         </dd>
         </dl>
         <input type="hidden" name="RecordID" id="RecordID" value="<?php echo $RecordID ?>" />
         <input type="hidden" name="Language" id="Language" value="<?php echo $Language ?>" />
         <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo $_SESSION['alias']?>" />
         <input type="hidden" name="Component" id="Component" value="Other" />
        <input type="submit" name="submit" value="Save" id="save"/><span id="msggbox_insert_content" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
</form>
<?php

		}
?>