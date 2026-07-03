<?php require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
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
		$result = $db->query("SELECT * FROM RED_Advanced WHERE RecordID='".$RecordID."'");
		$result_counter = $result->num_rows;
		while($row = mysqli_fetch_assoc($result))
		{	
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
		url: '/admin/bin/post_file.php?RecordID=<?php echo $RecordID ?>&UC=Webpage_Logo&Language=<?php echo $language?>',
		
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
function MM_jumpCSS(jumpCSS){ 
	var dataString = "Item=Reload&jumpCSS=" + jumpCSS.options[jumpCSS.selectedIndex].value;
	//alert (dataString);
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/update_advanced.php", 
	data: dataString,
	success: function(data) {
	//alert (data);
	$('#CSS').html(data)
	return false;
	//alert (jumpCSS.options[jumpCSS.selectedIndex].value);
	//return false;
	}
	});
	//return false;  
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
			case 'Website_Logo':
				echo'<div class="titleleft">';
				echo'<label title="Website Logo">Website Logo: </label>';
				echo'<input name="'.$row['Item'].'" type="hidden" value="'.$row['Content'].'" />';
				echo'<img src="/images/'.$row['Content'].'" alt="">';
				echo'</div>';
				echo'<div id="dropbox" style="width: 99%; min-height:80px;">';
				echo'<span class="message">Drop logo <br />here to upload.</span>';
				echo'</div>';
			break;
			///////////////
			case 'Website_CSS':
				echo $_POST['jumpCSS'];
				echo'<label>Select CSS to edit: </label><select name="jumpCSS" id="jumpCSS" onchange="MM_jumpCSS(this)">';
				  if ($handle = opendir('../../css/')) {
						while (false !== ($entry = readdir($handle))) {
							if ($entry != "." && $entry != "..") {
								if ($entry=="style.css")
								echo '<option value="'.$entry.'" selected="selected">'.$entry.'</option>';
								else
								echo '<option value="'.$entry.'">'.$entry.'</option>';
							}
						}
						closedir($handle);
					}
				echo'</select><div class="clear-cp"></div>';
				
				$css = file_get_contents('../../css/style.css', true);
				echo'<label>'.preg_replace('/\_/',' ',$row['Item']);
				echo'<textarea name="CSS" id="CSS" cols="" rows="30">'.$css.'</textarea></label>';
				echo'<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
				echo'<input type="hidden" name="Item" id="Item" value="'.$row['Item'].'" />';
				echo'<input type="submit" name="submit" value="Save" id="save"/>';
			break;
			
			case 'Website_Header':
				echo'<label>'.preg_replace('/\_/',' ',$row['Item']);
				echo'<textarea name="Content" id="Content" cols="" rows="4">'.$row['Content'].'</textarea></label>';
				echo'<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
				echo'<input type="hidden" name="Item" id="Item" value="'.$row['Item'].'" />';
				echo'<input type="submit" name="submit" value="Save" id="save"/>';
			break;
			
			case 'Website_Footer':
				echo'<label>'.preg_replace('/\_/',' ',$row['Item']);
				echo'<textarea name="Content" id="Content" cols="" rows="4">'.$row['Content'].'</textarea></label>';
				echo'<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
				echo'<input type="hidden" name="Item" id="Item" value="'.$row['Item'].'" />';
				echo'<input type="submit" name="submit" value="Save" id="save"/>';
			break;
						
			default:
				echo'<label>'.preg_replace('/\_/',' ',$row['Item']);
				echo'<textarea name="ShortLine" id="ShortLine" cols="" rows="4">'.$row['Content'].'</textarea></label>';
				echo'<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
				echo'<input type="hidden" name="Item" id="Item" value="'.$row['Item'].'" />';
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
		}
		$db->close();
		}
?>