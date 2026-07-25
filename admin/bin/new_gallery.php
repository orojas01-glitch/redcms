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
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_menu_helpers.php' ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php' ?>
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
		$authorizationDb = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		red_admin_require_component_selection($authorizationDb->connection, 'Gallery', $Type);
		$authorizationDb->close();

		if ($Type === 'Gallery') {
			require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_gallery_ui_helpers.php';

			$galleryDb = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$positionOptions = red_admin_article_layout_position_options($galleryDb->connection, $Layout);
			$sectionOptions = red_admin_article_area_options($galleryDb->connection, 'RED_Sections', 'Sections', $Section);
			$categoryOptions = red_admin_article_area_options($galleryDb->connection, 'RED_Categories', 'Categories', $Category);
			$subCategoryOptions = red_admin_article_area_options($galleryDb->connection, 'RED_SubCategories', 'SubCategories', $SubCategory);
			$articleOptions = red_admin_article_page_options($galleryDb->connection, $Article);
			$galleryDb->close();

			$uploadUrls = [
				'Gallery' => red_admin_gallery_ui_upload_url([
					'RecordID' => $RecordID,
					'ArtRecordID' => $ArtRecordID,
					'UC' => 'Gallery',
					'Insert' => 'false',
					'AuthComponent' => 'Gallery',
					'AuthSubtype' => 'Gallery',
					'Language' => $Language,
				]),
				'BigPict' => red_admin_gallery_ui_upload_url([
					'RecordID' => $ArtRecordID,
					'UC' => 'BigPict',
					'Insert' => 'false',
					'AuthComponent' => 'Gallery',
					'AuthSubtype' => 'Gallery',
					'Language' => $Language,
				]),
				'SmallPict' => red_admin_gallery_ui_upload_url([
					'RecordID' => $ArtRecordID,
					'UC' => 'SmallPict',
					'Insert' => 'false',
					'AuthComponent' => 'Gallery',
					'AuthSubtype' => 'Gallery',
					'Language' => $Language,
				]),
			];

			red_admin_render_gallery_form([
				'mode' => 'create',
				'returnTarget' => 'add_content_grid',
				'submitUrl' => '/admin/bin/insert_gallery.php',
				'positionOptions' => $positionOptions,
				'varPosition' => $VarPosition,
				'sectionOptions' => $sectionOptions,
				'categoryOptions' => $categoryOptions,
				'subCategoryOptions' => $subCategoryOptions,
				'articleOptions' => $articleOptions,
				'uploadUrls' => $uploadUrls,
				'recordId' => $RecordID,
				'artRecordId' => $ArtRecordID,
				'language' => $Language,
				'layout' => $Layout,
				'editedBy' => $_SESSION['alias'] ?? '',
				'csrfToken' => $csrfToken,
			]);
			exit;
		}

		if ($Type === 'Video') {
			require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_video_ui_helpers.php';

			$videoDb = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$positionOptions = red_admin_article_layout_position_options($videoDb->connection, $Layout);
			$linkNavigatorOptions = red_admin_main_menu_link_options($videoDb->connection);
			$sectionOptions = red_admin_article_area_options($videoDb->connection, 'RED_Sections', 'Sections', $Section);
			$categoryOptions = red_admin_article_area_options($videoDb->connection, 'RED_Categories', 'Categories', $Category);
			$subCategoryOptions = red_admin_article_area_options($videoDb->connection, 'RED_SubCategories', 'SubCategories', $SubCategory);
			$articleOptions = red_admin_article_page_options($videoDb->connection, $Article);
			$videoDb->close();

			$uploadUrls = [
				'BigPict' => red_admin_video_upload_url([
					'RecordID' => $ArtRecordID,
					'UC' => 'BigPict',
					'Insert' => 'false',
					'AuthComponent' => 'Gallery',
					'AuthSubtype' => 'Video',
					'Language' => $Language,
				]),
				'SmallPict' => red_admin_video_upload_url([
					'RecordID' => $ArtRecordID,
					'UC' => 'SmallPict',
					'Insert' => 'false',
					'AuthComponent' => 'Gallery',
					'AuthSubtype' => 'Video',
					'Language' => $Language,
				]),
			];

			red_admin_render_video_form([
				'mode' => 'create',
				'returnTarget' => 'add_content_grid',
				'submitUrl' => '/admin/bin/insert_gallery.php',
				'positionOptions' => $positionOptions,
				'varPosition' => $VarPosition,
				'linkNavigatorOptions' => $linkNavigatorOptions,
				'sectionOptions' => $sectionOptions,
				'categoryOptions' => $categoryOptions,
				'subCategoryOptions' => $subCategoryOptions,
				'articleOptions' => $articleOptions,
				'uploadUrls' => $uploadUrls,
				'recordId' => $RecordID,
				'artRecordId' => $ArtRecordID,
				'language' => $Language,
				'layout' => $Layout,
				'editedBy' => $_SESSION['alias'] ?? '',
				'csrfToken' => $csrfToken,
			]);
			exit;
		}

		if ($Type === 'Banner') {
			require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_banner_ui_helpers.php';

			$bannerDb = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$positionOptions = red_admin_article_layout_position_options($bannerDb->connection, $Layout);
			$linkNavigatorOptions = red_admin_main_menu_link_options($bannerDb->connection);
			$sectionOptions = red_admin_article_area_options($bannerDb->connection, 'RED_Sections', 'Sections', $Section);
			$categoryOptions = red_admin_article_area_options($bannerDb->connection, 'RED_Categories', 'Categories', $Category);
			$subCategoryOptions = red_admin_article_area_options($bannerDb->connection, 'RED_SubCategories', 'SubCategories', $SubCategory);
			$articleOptions = red_admin_article_page_options($bannerDb->connection, $Article);
			$bannerDb->close();

			red_admin_render_banner_form([
				'mode' => 'create',
				'returnTarget' => 'add_content_grid',
				'submitUrl' => '/admin/bin/insert_gallery.php',
				'positionOptions' => $positionOptions,
				'varPosition' => $VarPosition,
				'linkNavigatorOptions' => $linkNavigatorOptions,
				'sectionOptions' => $sectionOptions,
				'categoryOptions' => $categoryOptions,
				'subCategoryOptions' => $subCategoryOptions,
				'articleOptions' => $articleOptions,
				'recordId' => $RecordID,
				'artRecordId' => $ArtRecordID,
				'language' => $Language,
				'layout' => $Layout,
				'editedBy' => $_SESSION['alias'] ?? '',
				'csrfToken' => $csrfToken,
			]);
			exit;
		}

?>
<!-- Our CSS stylesheet file -->
<link rel="stylesheet" href="/admin/assets/css/styles.css" />

<!--[if lt IE 9]>
  <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
<![endif]-->
<!-- Including the HTML5 Uploader plugin -->
<script src="/admin/assets/js/jquery.filedrop.js"></script>
<script src="/admin/assets/js/jquery.filedrop2.js"></script>
<script src="/admin/assets/js/jquery.filedrop3.js"></script>
<script type="text/javascript">
window.RED_GALLERY_CREATE_QUEUE_UPLOADS = true;
window.RED_GALLERY_CREATE_CONFIG = {
	recordId: <?php echo json_encode($RecordID); ?>,
	articleRecordId: <?php echo json_encode($ArtRecordID); ?>,
	galleryType: <?php echo json_encode($Type); ?>,
	language: <?php echo json_encode($Language); ?>,
	csrfToken: <?php echo json_encode($csrfToken); ?>,
	maxImageBytes: 2 * 1024 * 1024,
	allowedExtensions: ['jpg', 'jpeg', 'png', 'gif']
};
</script>

<!-- TinyMCE -->
<script type="text/javascript">
tinymce.init({
	mode : "exact",
	<?php
	if ($Type=='Video' || $Type=='Banner')
	echo 'elements : "ShortDesc",';
	?>
    plugins: [
        "advlist autolink lists link image charmap print preview hr anchor pagebreak",
        "searchreplace wordcount visualblocks visualchars code fullscreen",
        "insertdatetime media nonbreaking save table contextmenu directionality",
        "emoticons template paste textcolor moxiemanager"
    ],
   /*content_css: "/css/style.css",*/
    style_formats: [
    {title: "Headers", items: [
        {title: "Header 1", format: "h1"},
        {title: "Header 2", format: "h2"},
        {title: "Header 3", format: "h3"},
        {title: "Header 4", format: "h4"},
        {title: "Header 5", format: "h5"},
        {title: "Header 6", format: "h6"},
		{title: "Example 2", inline: "h6", classes: "p1-1"}
    ]},
    {title: "Inline", items: [
        {title: "Bold", icon: "bold", format: "bold"},
        {title: "Italic", icon: "italic", format: "italic"},
        {title: "Underline", icon: "underline", format: "underline"},
        {title: "Strikethrough", icon: "strikethrough", format: "strikethrough"},
        {title: "Superscript", icon: "superscript", format: "superscript"},
        {title: "Subscript", icon: "subscript", format: "subscript"},
        {title: "Code", icon: "code", format: "code"}
    ]},
    {title: "Blocks", items: [
        {title: "Paragraph", format: "p"},
        {title: "Blockquote", format: "blockquote"},
        {title: "Div", format: "div"},
        {title: "Pre", format: "pre"}
    ]},
    {title: "Alignment", items: [
        {title: "Left", icon: "alignleft", format: "alignleft"},
        {title: "Center", icon: "aligncenter", format: "aligncenter"},
        {title: "Right", icon: "alignright", format: "alignright"},
        {title: "Justify", icon: "alignjustify", format: "alignjustify"}
    ]}
]/*,
    formats: {
        alignleft: {selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'left'},
        aligncenter: {selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'center'},
        alignright: {selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'right'},
        alignfull: {selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img', classes: 'full'},
        bold: {inline: 'span', 'classes': 'bold'},
        italic: {inline: 'span', 'classes': 'italic'},
        underline: {inline: 'span', 'classes': 'underline', exact: true},
        strikethrough: {inline: 'del'},
        customformat: {inline: 'span', styles: {color: '#00ff00', fontSize: '20px'}, attributes: {title: 'My custom format'}}
    }*/
});
</script>
<!-- /TinyMCE --> 

<!-- The main script file -->
<script type="text/javascript">
<!--
$(function(){
$(".cp_slideDown dt").click(function(){$(this).toggleClass("active").parent(".cp_slideDown").find("dd").slideToggle()})
})
//-->
<!--
$(function(){
	if (window.RED_GALLERY_CREATE_QUEUE_UPLOADS) return;
	var dropbox = $('#dropbox'),
		message = $('.message', dropbox);
	
	dropbox.filedrop({
		// The name of the $_FILES entry:
		paramname:'pic',
		
		<?php if (($Type=='Banner') || ($Type=='Video'))
		echo 'maxfiles: 1, '. "\n";
		else
		echo 'maxfiles: 10, '. "\n";
		?>
		maxfilesize: 2,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $RecordID ?>&ArtRecordID=<?php echo $ArtRecordID ?>&UC=Gallery&Insert=true&AuthComponent=Gallery&AuthSubtype=<?php echo rawurlencode($Type) ?>&Language=<?php echo rawurlencode($Language) ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_insert_gallery').html("&nbsp; Pictures Saved.")
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
					<?php if (($Type=='Banner') || ($Type=='Video'))
					echo "alert('Too many files! Please select 1 at most!'); ". "\n";
					else
					echo "alert('Too many files! Please select 10 at most!'); ". "\n";
					?>
					
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
	if (window.RED_GALLERY_CREATE_QUEUE_UPLOADS) return;
	var dropbox2 = $('#dropbox2'),
		message2 = $('.message2', dropbox2);
	
	dropbox2.filedrop2({
		// The name of the $_FILES entry:
		paramname:'pic',
		maxfiles: 1,
		maxfilesize: 2,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $ArtRecordID ?>&UC=BigPict&Insert=true&AuthComponent=Gallery&AuthSubtype=<?php echo rawurlencode($Type) ?>&Language=<?php echo rawurlencode($Language) ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_insert_gallery').html("&nbsp; Picture Saved.")
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
$(function(){
	if (window.RED_GALLERY_CREATE_QUEUE_UPLOADS) return;
	var dropbox3 = $('#dropbox3'),
		message3 = $('.message3', dropbox3);
	
	dropbox3.filedrop3({
		// The name of the $_FILES entry:
		paramname:'pic',
		maxfiles: 1,
		maxfilesize: 2,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $ArtRecordID ?>&UC=SmallPict&Insert=true&AuthComponent=Gallery&AuthSubtype=<?php echo rawurlencode($Type) ?>&Language=<?php echo rawurlencode($Language) ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_insert_gallery').html("&nbsp; Picture Saved.")
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
		
		message3.hide();
		preview.appendTo(dropbox3);
		
		// Associating a preview container
		// with the file, using jQuery's $.data():
		
		$.data(file,preview);
	}

	function showMessage(msg){
		message3.html(msg);
	}

});
//-->
<!--
function run_insert_gallery (insert_gallery)
{
	var saveButton = $('#save');
	var statusBox = $('#msggbox_insert_gallery');
	saveButton.prop('disabled', true);
	statusBox.html('&nbsp; Saving Gallery...').show();
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/insert_gallery.php", 
	data: $("#insert_gallery").serialize(),
	success: function(data) {
	data = $.trim(data);
	if (data=='yes' || data=='yesyes')
	{
	statusBox.html("&nbsp; Gallery saved. Uploading queued pictures...").show();
	var uploadQueued = window.redGalleryCreateUploadQueued || function () {
		return Promise.resolve();
	};
	uploadQueued().then(function () {
		statusBox.html("&nbsp; Gallery and pictures saved.").show();
		window.location.reload();
	}).catch(function (error) {
		statusBox.text(' Gallery saved, but a picture upload failed: ' + error.message).show();
		saveButton.prop('disabled', false);
	});
	}
	else
	{
	statusBox.html("&nbsp; Error. Please try again.").show();
	saveButton.prop('disabled', false);
	}
	},
	error: function(xhr) {
		statusBox.text(' Gallery could not be saved (server status ' + xhr.status + ').').show();
		saveButton.prop('disabled', false);
	}
	});
	return false;
}
//-->
</script>
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('add_content_grid');">Show Content</a> | Add <?php echo $Type?></div>
<form id="insert_gallery" name="insert_gallery" class="cp" method="post" onSubmit="return run_insert_gallery(this);">
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
					$positionOptions=red_admin_article_layout_position_options($db->connection, $Layout);
					foreach ($positionOptions as $w => $positionLabel)
					{
						echo '<option value="'.(int) $w.'">'.red_admin_area_html($positionLabel).' ('.(int) $w.')</option>';
					}
					$db->close();
					?>
                 </select>
                </label>
            </div>
            
        </div>
        <div class="wrapper">
	            <div class="titleright">
	                <label style="display:inline;">Gallery Type: <select name="GalleryType">
	                    <?php
	                    $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	                    echo red_admin_article_gallery_type_options($db->connection, $Type);
						$db->close();
	                    ?>
                    
                    
                    
                    
                    
               		</select>
                </label>
            </div>
            <div class="titleright">
            	<label style="display:inline;"><input name="HomeFeature" type="checkbox" value="Y" />Home Featured</label>   
            </div>
        </div>
		<div class="clear-cp"></div>      

        <?php
		switch ($Type)
		{
			///////////////
			case 'Gallery':
			
				echo '<label>Photo(s):<br />';
				echo '</label>';
				echo '<div id="dropbox" style="width:99%;min-height:80px;">';
				echo '<span class="message">Drop image(s) here to upload.</span>';
				echo '</div>';
				
					echo ('<div class="clear-cp"></div><label>Short Description: <br /><textarea name="ShortDesc" id="ShortDesc" cols="" rows="3"></textarea></label><div class="clear-cp"></div><br />');
				
			break;
			////////////
			case 'Video':
			
				echo ('<div class="wrapper">');
				echo ('<div class="titleleft">');
				echo '<label style="display:inline;">Video URL: <input name="LongDesc" type="text" id="gal_video" value="">';
				echo '</label>';
				echo ('</div>');
					echo ('</div>');

			break;
			/////////////
			case 'Banner':
			

				echo '<label>Banner:<br /></label>';
				echo '<div id="dropbox" style="width:99%;min-height:80px;">';
				echo '<span class="message">Drop Banner here to upload.</span>';
					echo '</div>';

					echo ('<div class="wrapper">');

	                    $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	                    $LinkNavigator = red_admin_main_menu_link_options($db->connection);
		                    $db->close();

					echo('<div class="titleleft"><label style="display:inline;">Link: <input name="Link" type="text" id="Link" value="" /></label>');
					echo ('</div>'); 
					echo ('<div class="titleleft">');
					echo '<script type="text/javascript">'. "\n";
					echo '<!--' ."\n";
					echo '$(\'#LinkNavigator\').bind(\'change\', function() {'. "\n";
					echo '$(\'#Link\').val($(this).val());'. "\n";
					echo '});'. "\n";
					echo '-->'. "\n";
					echo '</script>';
					echo('<select name="LinkNavigator" id="LinkNavigator">');
					echo ($LinkNavigator);
					echo('</select>');
					echo ('</div>');
					
                echo ('<div class="titleleft">');
					echo ('<label style="display:inline;" title="Open New Window">Open Blank <input name="NewWindow" type="checkbox" value="Y" /></label>'); 
                echo ('</div>');
					echo ('</div>');
			break;
			
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
                    <div id="dropbox2" style="width: 150px; min-height:80px; float:left">
                        <span class="message2">Drop image <br />
        here to upload.</span>
                    </div>
                    <div class="clear-cp"></div>
                    <div class="titleleft">
                    <label style="width:75px" title="Used in Article Description">Small Picture:</label>
                    </div>
                    <div id="dropbox3" style="width: 150px; min-height:80px; float:left">
                        <span class="message3">Drop image <br />
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
	                    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	                    echo red_admin_article_area_options($db->connection, 'RED_Sections', 'Sections', $Section);
	                    $db->close();
	                    ?>
                    </select>
                    </label>
                    
                    
                    <label>Category: <select name="Categories">
                    <option value="">- null -</option>
	                    <?php
	                    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	                    echo red_admin_article_area_options($db->connection, 'RED_Categories', 'Categories', $Category);
	                    $db->close();
	                    ?>
                    </select>
                    </label>
                    
                    
                    <label>Sub Category: <select name="SubCategories">
                    <option value="">- null -</option>
	                    <?php
	                    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
	                    echo red_admin_article_area_options($db->connection, 'RED_SubCategories', 'SubCategories', $SubCategory);
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
        <input type="hidden" name="Component" id="Component" value="Gallery" />
        <input type="hidden" name="Layout" id="Layout" value="<?php echo red_admin_area_html($Layout)?>" />
        <?php echo red_csrf_input(); ?>
        <input type="submit" name="submit" value="Save" id="save"/>
        <span id="msggbox_insert_gallery" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
</form>
<script src="/admin/assets/js/gallery-create-uploads.js"></script>
<?php

		}
?>
