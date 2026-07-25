<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin_site_manager(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_advanced_helpers.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_advanced_ui_helpers.php' ?>
<?php
$RecordID = (int) red_admin_advanced_scalar($_POST['RecordID'] ?? 0);
if ($RecordID <= 0) {
	echo 'no';
	exit;
}

$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$row = red_admin_advanced_record($db->connection, $RecordID);
if (!$row) {
	$db->close();
	echo 'no';
	exit;
}

$language = red_admin_text($row['Language'] ?? (defined('language') ? language : ''));
$csrfToken = red_csrf_token();

if ((string) $row['Item'] === 'Website_Logo') {
	red_admin_render_advanced_logo_editor([
		'content' => (string) ($row['Content'] ?? ''),
		'recordId' => $RecordID,
		'language' => $language,
		'csrfToken' => $csrfToken,
	]);
	$db->close();
	exit;
}

if ((string) $row['Item'] === 'Website_Red_Sphere_Credit') {
	red_admin_render_advanced_credit_editor([
		'content' => (string) ($row['Content'] ?? 'Y'),
		'recordId' => $RecordID,
		'language' => $language,
		'csrfToken' => $csrfToken,
	]);
	$db->close();
	exit;
}

if (in_array((string) $row['Item'], ['Website_Title', 'Website_Slogan'], true)) {
	red_admin_render_advanced_identity_editor([
		'item' => (string) $row['Item'],
		'content' => (string) ($row['Content'] ?? ''),
		'recordId' => $RecordID,
		'language' => $language,
		'csrfToken' => $csrfToken,
	]);
	$db->close();
	exit;
}

if (in_array((string) $row['Item'], ['Website_CSS', 'Website_Footer', 'Website_Header'], true)) {
	$editorContent = (string) ($row['Content'] ?? '');
	$cssTarget = null;
	$cssTargetToken = '';
	if ((string) $row['Item'] === 'Website_CSS') {
		$cssTarget = red_admin_advanced_active_css_target($db->connection, $_SERVER['DOCUMENT_ROOT']);
		$css = $cssTarget !== null ? red_admin_advanced_css_read($cssTarget) : null;
		$editorContent = $css !== null ? $css : '';
		$cssTargetToken = $cssTarget !== null ? red_admin_advanced_css_target_token($cssTarget) : '';
	}

	red_admin_render_advanced_source_editor([
		'item' => (string) $row['Item'],
		'content' => $editorContent,
		'recordId' => $RecordID,
		'language' => $language,
		'csrfToken' => $csrfToken,
		'cssTarget' => $cssTarget,
		'cssTargetToken' => $cssTargetToken,
	]);
	$db->close();
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

<!-- TinyMCE -->
<script type="text/javascript">
tinymce.init({
	mode : "exact",
	elements : "##",
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
	var dropbox = $('#dropbox'),
		message = $('.message', dropbox);
	
	dropbox.filedrop({
		// The name of the $_FILES entry:
		paramname:'pic',
		maxfiles: 1,
    	maxfilesize: 6,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $RecordID ?>&UC=Webpage_Logo&Language=<?php echo rawurlencode($language); ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_update_advanced').html("Logo Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_update_advanced');
			window.location.reload();
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
function run_update_advanced (update_advanced)
{
	<?php
	switch ($row['Item'])
		{
			///////////////
			case 'Website_Title':
			echo 'tinymce.remove(tinyMCE.get(ShortLine));'. "\n";
			break;
			case 'Website_Slogan':
			echo 'tinymce.remove(tinyMCE.get(ShortLine));'. "\n";
			break;
		}
	?>
	
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/update_advanced.php", 
	data: $("#update_advanced").serialize(),
	success: function(data) {
	/*alert (data);
	return false;*/
	if (data=='yes')
	{
	$('#msggbox_update_advanced').html("Advanced Item Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_advanced');
	window.location.reload();
	});
	}
	else if (data=='stale')
	{
	$('#msggbox_update_advanced').html("The active theme or stylesheet changed. Reopen Website CSS before saving.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_advanced');
	});
	}
	else
	{
	$('#msggbox_update_advanced').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_advanced');
	});
	}
	}
	});
	return false;
}
//-->
</script>
<style>
label{color:#000}
</style>
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('edit_advanced_grid');">Show Advanced Items</a></div>
<form id="update_advanced" name="update_advanced" class="cp" method="post" onSubmit="return run_update_advanced(this);">
<fieldset>
<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
              
        <?php 
		switch ($row['Item'])
		{
			///////////////
			default:
				echo'<label>'.red_admin_advanced_html(preg_replace('/\_/',' ',$row['Item']));
				echo'<textarea name="ShortLine" id="ShortLine" cols="" rows="4">'.red_admin_advanced_html($row['Content']).'</textarea></label>';
				echo red_csrf_input();
				echo'<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
				echo'<input type="hidden" name="Item" id="Item" value="'.red_admin_advanced_html($row['Item']).'" />';
				echo'<input type="submit" name="submit" value="Save" id="save"/>';
			break;
		}
		
		?>
        
        <span id="msggbox_update_advanced" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
</form>
<?php
	$db->close();
?>
