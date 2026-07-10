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
red_start_session();
red_require_admin(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php' ?>
<?php
if(empty($_SESSION['alias']))
	header('Location: http://'.BASE_URL.'');
	else {
		$Type=red_admin_post_text('Type');
		$CountPage=red_admin_post_text('CountPage');
		$Section=red_admin_post_text('Section');
		$Category=red_admin_post_text('Category');
		$SubCategory=red_admin_post_text('SubCategory');
		$Article=red_admin_post_text('Article');
		$VarPosition=red_admin_article_position_column($_POST['VarPosition'] ?? '', 'PagePosition');
		if ($VarPosition === null) {
			echo 'no';
			exit;
		}
		$Language=substr(red_admin_post_text('Language'), 0, 2);
		$Layout=red_admin_post_text('Layout');
		$RecordID=mt_rand();
		$ArtRecordID=mt_rand();
		$csrfToken=red_csrf_token();
		$ResponseTemplate='';

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
		url: '/admin/bin/post_file.php?RecordID=<?php echo $ArtRecordID ?>&UC=BigPict&Insert=true&Language=<?php echo rawurlencode($Language) ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_insert_form').html("Pictures Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_insert_form');
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
		url: '/admin/bin/post_file.php?RecordID=<?php echo $ArtRecordID ?>&UC=SmallPict&Insert=true&Language=<?php echo rawurlencode($Language) ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_insert_form').html("&nbsp; Picture Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_insert_form');
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
function run_insert_form (insert_form)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/insert_form.php", 
	data: $("#insert_form").serialize(),
	success: function(data) {
	//alert (data);
	//return false;
	if($.trim(data)=='yes' || $.trim(data)=='yesyes')
	{
	$('#msggbox_insert_form').html("Form Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_form');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_insert_form').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_form');
	});
	}
	}
	});
	return false;
}
//-->
</script>
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('add_content_grid');">Show Content</a> | New Form <?php echo $Type ?></div>
<form id="insert_form" name="insert_form" class="cp" method="post" onSubmit="return run_insert_form(this);">
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
                    <option value="Y">Y</option>
                    <option value="N">N</option>
                    </select>
                    </label>
            </div>
            <div class="titleright">
            	<label style="display:inline;" title="Position Order">Position Order: <input name="<?php echo $VarPosition.'Order'?>" type="text" id="order" value="" /></label>
            </div>
	            <div class="titleright">
	               <label title="Layout Position">Layout Position: <select name="<?php echo $VarPosition ?>">
	                <?php
					$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
					$Positions=red_admin_article_layout_positions($db->connection, $Layout);
					for ($w=0; $w<=$Positions; $w++)
					{
						echo '<option value="'.$w.'">'.$w.'</option>';
					}
					$db->close();
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
	        <label>Form:
	        <textarea name="LongDesc" id="LongDesc" cols="" rows="3"><?php
	        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	        $componentTemplate = red_admin_article_form_component_template($db->connection, $Type);
	        $Template=$componentTemplate['Template'];
			$ResponseTemplate=$componentTemplate['ResponseTemplate'];
			echo red_admin_area_html($Template);
			$db->close();
			?></textarea></label>
        <?php if ($Type=='Contact' ||$Type=='Response'||$Type=='Register'){?>
        <div class="wrapper">
        <div class="titleleft">
        <label>Email Subject: <br />

        <input name="Subject" type="text" id="subject" value="" /></label>
        <label>From: <br />

        <input name="Submitter" type="text"  id="submitter" value="" title="you@domain.com,Contact Name" /></label>
        </div>
        <div class="titleright">
        <label>To: <br />

        <input name="Destinatary" type="text" id="destinatary" value="" title="contact@domain.com,Contact Name;" /></label>
        <label>CC: <br />

        <input name="CC" type="text" id="CC" value="" title="contact@domain.com,Contact Name;" /></label>
        <label>BCC: <br />

        <input name="BCC" type="text" id="BCC" value="" title="contact@domain.com,Contact Name;" /></label>
        </div>
        </div>
        <?php }
			if ($Type=='Response'||$Type=='Register')
				echo '<label>Response: <textarea name="Response" id="Response" cols="" rows="24">'.red_admin_area_html($ResponseTemplate).'</textarea></label>';
		if ($Type=='Register'){
		echo  '<div class="wrapper">';
            echo '<div class="titleleft">';
            	echo '<label>Table Users Name: <input name="TableName" type="text" id="tablename" value="" /></label>';
           echo '</div>';
		   echo '</div>';
		}
		?>
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
                </div>
                <div class="titleright"  style="text-align:right">
                    <label>Article Location:</label>
	                    <label>Section: <select name="Sections">
	                    <option value="">- null -</option>
	                    <?php
	                    $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	                    echo red_admin_article_area_options($db->connection, 'RED_Sections', 'Sections', $Section, false);
	                    $db->close();
	                    ?>
                    </select>
                    </label>
                    
                    
                    <label>Category: <select name="Categories">
                    <option value="">- null -</option>
	                    <?php
	                    $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	                    echo red_admin_article_area_options($db->connection, 'RED_Categories', 'Categories', $Category, false);
	                    $db->close();
	                    ?>
                    </select>
                    </label>
                    
                    
                    <label>Sub Category: <select name="SubCategories">
                    <option value="">- null -</option>
	                    <?php
	                    $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	                    echo red_admin_article_area_options($db->connection, 'RED_SubCategories', 'SubCategories', $SubCategory, false);
	                    $db->close();
	                    ?>
                    </select>
                    </label>
                    
                    <label>Article: <select name="Article">
                    <option value="">- null -</option>
	                    <?php

	                    $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
						echo red_admin_article_page_options($db->connection, $Article);
	                    $db->close();
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
        <input type="hidden" name="ArtRecordID" id="ArtRecordID" value="<?php echo $ArtRecordID ?>" />
        <input type="hidden" name="RecordID" id="RecordID" value="<?php echo $RecordID ?>" />
        <input type="hidden" name="Language" id="Language" value="<?php echo red_admin_area_html($Language) ?>" />
        <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo red_admin_area_html($_SESSION['alias'])?>" />
        <input type="hidden" name="Component" id="Component" value="Form" />
        <input type="hidden" name="Layout" id="Layout" value="<?php echo red_admin_area_html($Layout)?>" />
        <input type="hidden" name="FormType" id="Layout" value="<?php echo red_admin_area_html($Type)?>" />
        <?php echo red_csrf_input(); ?>
        <input type="submit" name="submit" value="Save" id="save"/>
        <span id="msggbox_insert_form" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
</form>
<?php
		}

?>
