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
red_require_admin(); ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_gallery_helpers.php' ?>
<?php
$RecordID = (int) ($_POST['RecordID'] ?? 0);
$ArtRecordID = (int) ($_POST['ArtRecordID'] ?? 0);
$VarPosition = red_admin_article_position_column($_POST['VarPosition'] ?? '');
if ($RecordID <= 0 || $ArtRecordID <= 0 || $VarPosition === null) {
	echo 'no';
	exit;
}

$Layout = red_admin_text($_POST['Layout'] ?? '');
$ArticleSel = red_admin_text($_POST['Article'] ?? '');

$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$articleRow = red_admin_article_full_record($db->connection, $ArtRecordID);
$row = red_admin_gallery_render_record($db->connection, $RecordID, $ArtRecordID);
if (!$articleRow || !$row) {
	$db->close();
	echo 'no';
	exit;
}

if ($Layout === '') {
	$Layout = red_admin_text($articleRow['Layout'] ?? '');
}

$ActiveValue=$articleRow['Active'];
$VarPositionValue=(int) ($articleRow[$VarPosition] ?? 0);
$StartDate=$articleRow['StartDate'];
//$StartDate=substr($StartDate, 0, 10);
$ExpDate=$articleRow['ExpDate'];
//$ExpDate=substr($ExpDate, 0, 10);
$PosOrder=(int) ($articleRow[$VarPosition.'Order'] ?? 0);
$Section=$articleRow['Sections'];
//echo 'Section:'.$Section.'<br/>';
$Category=$articleRow['Categories'];
//echo 'Category:'.$Category.'<br/>';
$SubCategory=$articleRow['SubCategories'];
//echo 'SubCategory:'.$SubCategory.'<br/>';
$Article=$articleRow['Article'];
$HomeFeature=$articleRow['HomeFeature'];
$BigPict=$articleRow['BigPict'];
$SmallPict=$articleRow['SmallPict'];
$Language=$articleRow['Language'];
$SmallPictAlign=$articleRow['SmallPictAlign'];
$Tags=$articleRow['Tags'];
$csrfToken=red_csrf_token();
		
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

<!-- TinyMCE -->
<script type="text/javascript">
tinymce.init({
	mode : "exact",
	<?php
	if ($row['GalleryType']=='Video' || $row['GalleryType']=='Banner')
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
	var dropbox = $('#dropbox'),
		message = $('.message', dropbox);
	
	dropbox.filedrop({
		// The name of the $_FILES entry:
		paramname:'pic',
		
		<?php if (($row['GalleryType']=='Banner') || ($row['GalleryType']=='Video'))
		echo 'maxfiles: 1, '. "\n";
		else
		echo 'maxfiles: 10, '. "\n";
		?>
    	maxfilesize: 6,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $RecordID ?>&UC=Gallery&Language=<?php echo rawurlencode($Language); ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_update_gallery').html("&nbsp; Pictures Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_update_gallery');
			window.location.reload();
			});
		},
		
    	error: function(err, file) {
			switch(err) {
				case 'BrowserNotSupported':
					showMessage('Your browser does not support HTML5 file uploads!');
					break;
				case 'TooManyFiles':
					<?php if (($row['GalleryType']=='Banner') || ($row['GalleryType']=='Video'))
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
	var dropbox2 = $('#dropbox2'),
		message2 = $('.message2', dropbox2);
	
	dropbox2.filedrop2({
		// The name of the $_FILES entry:
		paramname:'pic',
		maxfiles: 1,
    	maxfilesize: 6,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $ArtRecordID ?>&UC=BigPict&Language=<?php echo rawurlencode($Language); ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_update_gallery').html("&nbsp; Picture Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_update_gallery');
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
	var dropbox3 = $('#dropbox3'),
		message3 = $('.message3', dropbox3);
	
	dropbox3.filedrop3({
		// The name of the $_FILES entry:
		paramname:'pic',
		maxfiles: 1,
    	maxfilesize: 6,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $ArtRecordID ?>&UC=SmallPict&Language=<?php echo rawurlencode($Language); ?>&csrf_token=<?php echo rawurlencode($csrfToken); ?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_update_gallery').html("&nbsp; Picture Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_update_gallery');
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
function run_update_gallery (update_gallery)
{
	$.ajax({ 
	cache: false,
	type: "POST", 
	url: "/admin/bin/update_gallery.php", 
	data: $("#update_gallery").serialize(),
	success: function(data) {
/*	alert (data);
	return false;*/
	if (data=='yes' || data=='yesyes' || data=='noyes' || data=='yesno')
	{
	$('#msggbox_update_gallery').html("&nbsp; Gallery Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_gallery');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_update_gallery').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_gallery');
	});
	}
	}
	});
	return false;
}
//-->
<!--
function run_deleterecord (RecordID,ArtRecordID)
{
	$(document).ready(function(){           
   $('#deleterecord_'+RecordID).click(function(){
      if(confirm("Are you sure you want to delete this Record? It can't be recovered.")){
         //alert('Successful Request!');
		  $.ajax({ 
		type: "POST", 
		url: "/admin/bin/delete_label.php", 
		data: {RecordID: RecordID, ArtRecordID: ArtRecordID, T: "gal", csrf_token: <?php echo json_encode($csrfToken); ?>},
		success: function(data) {
		/*alert (data);
		return false;*/
		if (data=='yesyes')
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
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('edit_content_grid');">Show Content</a> | Edit <?php echo red_admin_area_html($row['GalleryType'])?></div>
<form id="update_gallery" name="update_gallery" class="cp" method="post" onSubmit="return run_update_gallery(this);">
<fieldset>
<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
         <div class="wrapper">
            <div class="titleleft">
                <label>Title: <input name="Title" type="text" id="title" value="<?php echo red_admin_area_html($row['Title'])?>" /></label>
            </div>
            <div class="titleleft">
                <label>Alias: <input name="Alias" type="text" id="alias" value="<?php echo red_admin_area_html($row['Alias'])?>" /></label>
            </div>
            <div class="titleright">
            	<a href="#" id="deleterecord_<?php echo $RecordID ?>"><img src="/admin/images/ico_trashcan.png" onClick="run_deleterecord(<?php echo $RecordID ?>,<?php echo $ArtRecordID ?>);" title="Delete Record" style="cursor:pointer"></a>
             </div>
            <div class="titleright">
                <label style="display:inline;">Active: <select name="Active">
                <?php if ($ActiveValue=='Y'){
				echo '<option value="Y" selected="selected">Y</option>';
				echo '<option value="N">N</option>';
				}else{
				echo '<option value="Y">Y</option>';
				echo '<option value="N" selected="selected">N</option>';
				}
                ?>
                </select>
                </label>
            </div>
            <div class="titleright">
            <label style="display:inline;" title="Position Order">Position Order: <input name="<?php echo red_admin_area_html($VarPosition.'Order')?>" type="text" id="order" value="<?php echo $PosOrder ?>" /></label>
            </div>
            <div class="titleright">
                <label title="Layout Position">Layout Position: <select name="<?php echo red_admin_area_html($VarPosition) ?>">
                <?php
				 //echo $Layout;
				$ThisPosition=$VarPositionValue;
				settype($ThisPosition, "integer");
				 //echo '<option value="'.$row[$VarPosition].'">'.$row[$VarPosition].'</option>';
				$Positions = red_admin_article_layout_positions($db->connection, $Layout);
				//echo $Positions;
				for ($w=0; $w<=$Positions; $w++)
				{
					//echo $w;
					if (intval($ThisPosition)===intval($w))
					echo '<option value="'.$w.'" selected="selected">'.$w.'</option>';
					else
					echo '<option value="'.$w.'">'.$w.'</option>';
					
				}
				?>
                 </select>
                </label>
            </div>
            
        </div>
        <div class="wrapper">
        	<div class="titleleft">
                <label>Tags SEO: <input name="Tags" type="text" id="title" value="<?php echo red_admin_area_html($Tags) ?>" /></label>
           		</div>
            <div class="titleright">
            	<label style="display:inline;"><input name="HomeFeature" type="checkbox" value="Y" <?php if ($HomeFeature==='Y') echo 'checked="checked"' ?> />Home Featured</label>
            </div>
        </div>
        <div class="wrapper">
            <div class="titleright">
            	<span id="msggbox_deleterecord" style="display:none"></span>
        	</div>
        </div>
        <div class="clear-cp"></div> 
         

        <?php
		switch ($row['GalleryType'])
		{
			///////////////
			case 'Gallery':
			
				echo '<label>Photo(s):<br />';
				if ($row['LongDesc']!=''){
				$photo=explode(',', $row['LongDesc']);
				
					for ($t=0; $t<count($photo); $t++)
					{
						$photoName=red_admin_text($photo[$t]);
						if ($photoName==='') {
							continue;
						}
						echo '<div style="float:left; padding-right:5px; margin-right:5px;">';
						echo '<input name="Photo'.$t.'" type="hidden" value="'.red_admin_area_html($photoName).'" />';
						echo '<label><img src="/images/resize.php?w=60&h=45&amp;img=/images/gallery/'.rawurlencode($photoName).'" alt=""><br/>';
						echo '<input name="Delete'.$t.'" type="checkbox" value="Y">Delete</label>';
						echo '</div>';
					}
				}
				echo '</label>';
				echo '<div id="dropbox" style="width:99%;min-height:80px;">';
				echo '<span class="message">Drop image(s) here to upload.</span>';
				echo '</div>';
				
				echo ('<label>Short Description: <br /><textarea name="ShortDesc" id="ShortDesc" cols="" rows="3">'.red_admin_area_html($row['ShortDesc']).'</textarea></label><div class="clear-cp"></div><br />');
				
			break;
			///////////////
			case 'Carrousel':
			
				echo '<label>Photo(s):<br />';
				if ($row['LongDesc']!=''){
				$photo=explode(',', $row['LongDesc']);
				
					for ($t=0; $t<count($photo); $t++)
					{
						$photoName=red_admin_text($photo[$t]);
						if ($photoName==='') {
							continue;
						}
						echo '<div style="float:left; padding-right:5px; margin-right:5px;">';
						echo '<input name="Photo'.$t.'" type="hidden" value="'.red_admin_area_html($photoName).'" />';
						echo '<label><img src="/images/resize.php?w=60&h=45&amp;img=/images/gallery/'.rawurlencode($photoName).'" alt=""><br/>';
						echo '<input name="Delete'.$t.'" type="checkbox" value="Y">Delete</label>';
						echo '</div>';
					}
				}
				echo '</label>';
				echo '<div id="dropbox" style="width:99%;min-height:80px;">';
				echo '<span class="message">Drop image(s) here to upload.<br/>Image Size must be Width: 120px - Height: 107px</span>';
				echo '</div>';
				
			break;
			////////////
			case 'Video':
			
				echo ('<div class="wrapper">');
				echo ('<div class="titleleft">');
				echo '<label style="display:inline;">Video URL: <input name="LongDesc" type="text" id="gal_video" value="'.red_admin_area_html($row['LongDesc']).'">';
				echo '</label>';
				echo ('</div>');
				echo ('</div>');
				
				//echo ('<label>Short Description: <br /><textarea name="ShortDesc" id="ShortDesc" cols="" rows="3">'.$row['ShortDesc'].'</textarea><a href="javascript:;" onclick="tinyMCE.get(\'ShortDesc\').show();return false;">[Show]</a>		<a href="javascript:;" onclick="tinyMCE.get(\'ShortDesc\').hide();return false;">[Hide]</a></label><div class="clear-cp"></div><br />');
			
			break;
			/////////////
			case 'Banner':
			
				
			
				echo ('<div class="wrapper">');
				echo ('<div class="titleleft">');
				echo '<label>Banner:<br />';
				
				if ($row['LongDesc']!=''){
				$photo=explode(',', $row['LongDesc']);
				
					for ($t=0; $t<count($photo); $t++)
					{
						$photoName=red_admin_text($photo[$t]);
						if ($photoName==='') {
							continue;
						}
						echo '<div style="float:left; padding-right:5px; margin-right:5px;">';
						echo '<input name="Photo'.$t.'" type="hidden" value="'.red_admin_area_html($photoName).'" />';
						echo '<label><img src="/images/resize.php?w=60&h=45&amp;img=/images/gallery/'.rawurlencode($photoName).'" alt=""><br/>';
						echo '<input name="Delete'.$t.'" type="checkbox" value="Y">Delete</label>';
						echo '</div>';
					}
				}
				
				echo '</label>';
				echo ('</div>');
				echo ('<div class="titleright">');
				
				echo '<div id="dropbox" style="width:210px;min-height:80px;">';
				echo '<span class="message">Drop Banner here to upload.</span>';
				echo '</div>';
				
				echo ('</div>');
				echo ('</div>');
				
				echo ('<div class="wrapper">');
					echo('<div class="titleleft"><label style="display:inline;">Link: <input name="Link" type="text" id="Link" value="' . red_admin_area_html($row['Link']) . '" /></label>');
					echo ('</div>'); 
					
                echo ('<div class="titleleft">');
					$newWindowChecked = ($row['NewWindow'] ?? '') === 'Y' ? ' checked="checked"' : '';
					echo ('<label style="display:inline;" title="Open New Window">Open Blank <input name="NewWindow" type="checkbox"'.$newWindowChecked.' value="Y" /></label>');
                echo ('</div>');
				echo ('</div>');
				
				//echo ('<div class="clear-cp"></div>');
         		//echo ('<label>Short Description: <br /><textarea name="ShortDesc" id="ShortDesc" cols="" rows="3">'.$row['ShortDesc'].'</textarea><a href="javascript:;" onclick="tinyMCE.get(\'ShortDesc\').show();return false;">[Show]</a>		<a href="javascript:;" onclick="tinyMCE.get(\'ShortDesc\').hide();return false;">[Hide]</a></label><div class="clear-cp"></div><br />');
        
			
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
                        <?php
                        if ($BigPict<>''){
                            ?>
                        
                            <input name="BigPict" type="hidden" value="<?php echo red_admin_area_html($BigPict) ?>" />
                            <img src="/images/resize.php?w=60&h=45&amp;img=/images/articles/<?php echo rawurlencode($BigPict) ?>" alt=""><br/>
                            <label><input name="Delete_BigPict" type="checkbox" value="Y">Delete</label>
                        
                        <?php
                        }
                        ?>
                    
                    </div>
                    <div id="dropbox2" style="width: 150px; min-height:80px; float:left">
                        <span class="message2">Drop image <br />
        here to upload.</span>
                    </div>
                    <div class="clear-cp"></div>
                    <div class="titleleft">
                    <label style="width:75px" title="Used in Article Description or Short Articles">Small Picture:</label>
                        <?php
                        if ($SmallPict<>''){
                            ?>
                        
                            <input name="SmallPict" type="hidden" value="<?php echo red_admin_area_html($SmallPict) ?>" />
                            <img src="/images/resize.php?w=60&h=45&amp;img=/images/articles/<?php echo rawurlencode($SmallPict) ?>" alt=""><br/>
                            <label><input name="Delete_SmallPict" type="checkbox" value="Y">Delete</label>
                        
                        <?php
                        }
                        ?>
                    </div>
                    <div id="dropbox3" style="width: 150px; min-height:80px; float:left">
                        <span class="message3">Drop image <br />
        here to upload.</span>
                    </div>
                    <div class="titleleft">
                        <label>Pict Alignment:
                        <select name="SmallPictAlign">
                        <option value="">- null -</option>
                        <option value="Top" <?php if ($SmallPictAlign=='Top') echo 'selected="selected"'?>>Top</option>
                        <option value="Left" <?php if ($SmallPictAlign=='Left') echo 'selected="selected"'?>>Left</option>
                        <option value="Right" <?php if ($SmallPictAlign=='Right') echo 'selected="selected"'?>>Right</option>
                        </select>
                        </label>
                    </div>
                </div>
                
                <div class="titleright"  style="text-align:right">
                    <label><?php echo red_admin_area_html($row['GalleryType'])?> Location:</label>
                    <label>Section: <select name="Sections">
                    <option value="">- null -</option>
                    <?php 
                    echo red_admin_article_area_options($db->connection, 'RED_Sections', 'Sections', $Section);
                    
                    ?>
                    </select>
                    </label>
                    
                    
                    <label>Category: <select name="Categories">
                    <option value="">- null -</option>
                    <?php
                    echo red_admin_article_area_options($db->connection, 'RED_Categories', 'Categories', $Category);
                    
                    ?>
                    </select>
                    </label>
                    
                    
                    <label>Sub Category: <select name="SubCategories">
                    <option value="">- null -</option>
                    <?php
                    echo red_admin_article_area_options($db->connection, 'RED_SubCategories', 'SubCategories', $SubCategory);
                    
                    ?>
                    </select>
                    </label>
                    
                    <label>Article: <select name="Article">
                    <option value="">- null -</option>
                    <?php
                    
					echo red_admin_article_page_options($db->connection, $Article);
                    
                    ?>
                    </select>
                    </label>
                    <div class="clear-cp"></div>
                    <label title="yyyy-mm-dd hh:mm:ss" >Start Date: <input name="StartDate" type="text" id="date" value="<?php echo red_admin_area_html($StartDate) ?>" /></label>
                    <label title="yyyy-mm-dd hh:mm:ss">Exp Date: <input name="ExpDate" type="text" id="date" value="<?php echo red_admin_area_html($ExpDate) ?>" /></label>
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
        <?php echo red_csrf_input(); ?>
        <input type="hidden" name="ArtRecordID" id="ArtRecordID" value="<?php echo $ArtRecordID ?>" />
        <input type="hidden" name="RecordID" id="RecordID" value="<?php echo $RecordID ?>" />
        <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo red_admin_area_html($_SESSION['alias'] ?? '')?>" />
        <input type="submit" name="submit" value="Save" id="save"/>
        <span id="msggbox_update_gallery" style="display:none"></span>
        </div>
        </article>
    </div>
</div>
</fieldset>
</form>
<?php
		$db->close();
?>
