<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
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
		$RecordID=mt_rand();
		$ArtRecordID=mt_rand();
		$csrfToken=red_csrf_token();

?>
<!-- Our CSS stylesheet file -->
<link rel="stylesheet" href="/admin/assets/css/styles.css" />

<!--[if lt IE 9]>
  <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
<![endif]-->
<!-- Including the HTML5 Uploader plugin -->
<!-- http://tutorialzine.com/2011/09/html5-file-upload-jquery-php/ -->
<script src="/admin/assets/js/jquery.filedrop.js"></script>
<!-- The main script file -->
<script type="text/javascript">
<!--
$(function(){
	var dropbox = $('#dropbox'),
		message = $('.message', dropbox);
	
	dropbox.filedrop({
		// The name of the $_FILES entry:
		paramname:'pic',
		maxfiles: 1,
    	maxfilesize: 10,
		url: '/admin/bin/post_ftp.php?UC=FTP&Language=<?php echo rawurlencode($Language) ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			var storedName = response && response.stored_name ? response.stored_name : file.name;
			$("#msggbox_insert_gallery").html("https://<?php echo BASE_URL . '/images/articles/' ?>" + storedName + "&nbsp; File Saved.")
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_insert_gallery');
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
					alert(file.name+' is too large! Please upload files up to 10mb.');
					break;
				default:
					break;
			}
		},
		
		 //Called before each upload is started
		/*beforeEach: function(file){
			if(!file.type.match(/^image\//)){
				alert('Only images are allowed!');
				
				// Returning false will cause the
				// file to be rejected
				return false;
			}
		},*/
		beforeEach: function(file){
		if(!file.type.match(/^image\//) && !file.type.match(/^application\//) && !file.type.match(/^text\//)){
		alert('This file is not allowed!');
		
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
		
		/*reader.onload = function(e){
			
			// e.target.result holds the DataURL which
			// can be used as a source of the image:
			
			image.attr('src',e.target.result);
		};*/
		reader.onload = function(e){

			// e.target.result holds the DataURL which
			// can be used as a source of the image:
			switch(file.type) {
				case 'application/pdf':
					image.attr('src','http://<?php echo BASE_URL  ?>/admin/assets/img/ico-pdf.png');
					break;
				case 'application/msword':
					image.attr('src','http://<?php echo BASE_URL  ?>/admin/assets/img/ico-doc.png');
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
					image.attr('src','http://<?php echo BASE_URL  ?>/admin/assets/img/ico-doc.png');
					break;
				case 'application/vnd.ms-excel':
					image.attr('src','http://<?php echo BASE_URL  ?>/admin/assets/img/ico-xls.png');
					break;
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
					image.attr('src','http://<?php echo BASE_URL  ?>/admin/assets/img/ico-xls.png');
					break;
				case 'application/vnd.ms-powerpoint':
					image.attr('src','http://<?php echo BASE_URL  ?>/admin/assets/img/ico-ppt.png');
					break;
				case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
					image.attr('src','http://<?php echo BASE_URL  ?>/admin/assets/img/ico-ppt.png');
					break;
				case 'application/x-zip-compressed':
					image.attr('src','http://<?php echo BASE_URL  ?>/admin/assets/img/ico-zip.png');
					break;
				case 'text/plain':
					image.attr('src','http://<?php echo BASE_URL  ?>/admin/assets/img/ico-txt.png');
					break;
				default:
					image.attr('src',e.target.result);
					break;
			}
			//if(file.type==='application/pdf'){
//				image.attr('src','http://myredsphere.com/admin/assets/img/logo.jpg');
//			}
//			else {
//				image.attr('src',e.target.result);
//			}
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
function run_insert_gallery (insert_gallery)
{
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/insert_gallery.php", 
	data: $("#insert_gallery").serialize(),
	success: function(data) {
	//alert (data);
//	return false;
	if (data=='yes' || data=='yesyes')
	{
	$('#msggbox_insert_gallery').html("&nbsp; Gallery Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_gallery');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_insert_gallery').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_insert_gallery');
	});
	}
	}
	});
	return false;
}
//-->
</script>
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('add_content_grid');">Show Content</a></div>

<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">

				<label>Uploader:</label>
				<div id="dropbox" style="width:99%;min-height:80px;">
				<span class="message">Drop file here to upload.</span>
				</div>
        
        <span id="msggbox_insert_gallery" style="display:none"></span>
        </div>
        </article>
    </div>
</div>

<?php

		}
?>
