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
		$VarPosition=preg_replace ( "'<[^>]+>'U", "", $_POST['VarPosition']);
		$ArticleSelected=preg_replace ( "'<[^>]+>'U", "", $_POST['Article']);
		$Layout=preg_replace ( "'<[^>]+>'U", "", $_POST['Layout']);
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		//echo "SELECT * FROM RED_Articles WHERE RecordID='".$recordid."'";
		$result = $db->query("SELECT * FROM RED_Articles WHERE RecordID='".$RecordID."'");
		$result_counter = $result->num_rows;
		while($row = mysqli_fetch_assoc($result))
		{
		$StartDate=$row['StartDate'];
		//$StartDate=substr($StartDate, 0, 10);
		$ExpDate=$row['ExpDate'];
		//$ExpDate=substr($ExpDate, 0, 10);
		$PosOrder=$row[$VarPosition.'Order'];
		$Section=$row['Sections'];
		//echo 'Section:'.$Section.'<br/>';
		$Category=$row['Categories'];
		//echo 'Category:'.$Category.'<br/>';
		$SubCategory=$row['SubCategories'];
		//echo 'SubCategory:'.$SubCategory.'<br/>';
		$Article=$row['Article'];
		$HomeFeature=$row['HomeFeature'];
		$BigPict=$row['BigPict'];
		$SmallPict=$row['SmallPict'];
		$SmallPict2=$row['SmallPict2'];
		$Language=$row['Language'];
		$thisLink='https://'.BASE_URL;
		if($row['Sections']<>'')
			$thisLink=$thisLink.'/'.$Section.'/';
		if($row['Categories']<>'')
			$thisLink=$thisLink.''.$Category.'/';
		if($row['SubCategories']<>'')
			$thisLink=$thisLink.''.$SubCategory.'/';
		
		$thisLink=trim($thisLink.$row['Alias']);

		
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
	elements : "ShortDesc,LongDesc",
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
		maxfiles: 1,
    	maxfilesize: 6,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $RecordID ?>&UC=BigPict&Language=<?php echo $Language?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_update_content').html("&nbsp; Pictures Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_update_content');
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
		url: '/admin/bin/post_file.php?RecordID=<?php echo $RecordID ?>&UC=SmallPict&Language=<?php echo $Language?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_update_content').html("&nbsp; Picture Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_update_content');
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
	var dropbox3 = $('#dropbox3'),
		message3 = $('.message3', dropbox3);
	
	dropbox3.filedrop3({
		// The name of the $_FILES entry:
		paramname:'pic',
		maxfiles: 1,
    	maxfilesize: 6,
		url: '/admin/bin/post_file.php?RecordID=<?php echo $RecordID ?>&UC=SmallPict2&Language=<?php echo $Language?>',
		
		uploadFinished:function(i,file,response){
			$.data(file).addClass('done');
			//alert(file.name);
			// response is the JSON object that post_file.php returns
			//document.location.reload();
			$('#msggbox_update_content').html("&nbsp; Picture Saved.")
			.hide()
			.fadeIn(1500, function() {
			$('#msggbox_update_content');
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
function run_update_content (update_content)
{
	tinyMCE.triggerSave();
	$.ajax({ 
	type: "POST", 
	url: "/admin/bin/update_content.php", 
	data: $("#update_content").serialize(),
	success: function(data) {
	/*alert (data);
	return false;*/
	if (data=='yes')
	{
	$('#msggbox_update_content').html("&nbsp; Content Updated.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_content');
	window.location.reload();
	});
	}
	else
	{
	$('#msggbox_update_content').html("&nbsp; Error. Please try again.")
	.hide()
	.fadeIn(1500, function() {
	$('#msggbox_update_content');
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
		data: "RecordID=" + RecordID,
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
<div class="cp_viewall"><a href="javascript:;" class="viewall" onclick="javascript:showdiv('edit_content_grid');">Show Content</a> | Edit Article</div>
<form id="update_content" name="update_content" class="cp" method="post" onSubmit="return run_update_content(this);">
<fieldset>
<div class="container_12 cp_padtop">
    <div class="wrapper">
        <article class="grid_12 cp_admin">
        <div style="padding:10px;">
        <div class="wrapper">
            <div class="titleleft">
            	<label>Title: <input name="Title" type="text" id="title" value="<?php echo $row['Title']?>" /></label>
            </div>
            <div class="titleleft">
            	<label>Alias: <input name="Alias" type="text" id="alias" value="<?php echo $row['Alias']?>" /></label>
				<label>Link: <a href="<?php echo $thisLink?>"><?php echo $thisLink?></a></label>
            </div>
             <div class="titleright">
            	<a href="#" id="deleterecord_<?php echo $RecordID ?>"><img src="/admin/images/ico_trashcan.png" onClick="run_deleterecord(<?php echo $RecordID ?>);" title="Delete Record" style="cursor:pointer"></a>
             </div>
            <div class="titleright">
                <label style="display:inline;">Active: <select name="Active">
                <?php if ($row['Active']=='Y'){
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
				
                <label style="display:inline;" title="Position Order">Position Order: <input name="<?php echo $VarPosition.'Order'?>" type="text" id="order" value="<?php echo $PosOrder ?>" /></label>
                
            </div>
            <div class="titleright">
                <label title="Layout Position">Layout Position: <select name="<?php echo $VarPosition ?>">
                <?php
				 //echo $Layout;
				$thisPosition=$row[$VarPosition];
				settype($thisPosition, "integer");
				echo $thisPosition;
				 //echo '<option value="'.$row[$VarPosition].'">'.$row[$VarPosition].'</option>';
				 $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$resultC = $db->query("SELECT Positions FROM RED_Layouts WHERE UniqueName='".$Layout."'");
				//echo ($resultC->num_rows);
				while($row4 = mysqli_fetch_assoc($resultC))
				{
					$Positions=$row4['Positions'];
				}
				//echo $Positions;
				for ($w=0; $w<=$Positions; $w++)
				{
					//echo $w;
					if (intval($thisPosition)===intval($w))
					echo '<option value="'.$w.'" selected="selected">'.$w.'</option>';
					else
					echo '<option value="'.$w.'">'.$w.'</option>';
					
				}
				?>
                 </select>
                </label>
			</div>
            
            <div class="wrapper">
            	<div class="titleleft">
            	<label>Tags SEO: <input name="Tags" type="text" id="title" value="<?php echo $row['Tags']?>" /></label>
           		</div>
                <div class="titleright">
                <label style="display:inline;"><input name="HomeFeature" type="checkbox" value="Y" <?php if ($HomeFeature==='Y') echo 'checked="checked"' ?> />Home Featured</label>
                </div>
                
            </div>
        </div>
        <div class="wrapper">
            <div class="titleright">
            	<span id="msggbox_deleterecord" style="display:none"></span>
        	</div>
        </div>
        <div class="clear-cp"></div> 

        <?php  
       		echo('<label>Slider Short Description: <br /><textarea name="SliderDesc" id="SliderDesc" cols="" rows="1">'.$row['SliderDesc'].'</textarea><a href="javascript:;" onclick="tinyMCE.get(\'SliderDesc\').show();return false;">[Show]</a>		<a href="javascript:;" onclick="tinyMCE.get(\'SliderDesc\').hide();return false;">[Hide]</a></label><div class="clear-cp"></div> ');
			
			echo('<label>Short Description: <br /><textarea name="ShortDesc" id="ShortDesc" cols="" rows="3">'.$row['ShortDesc'].'</textarea><a href="javascript:;" onclick="tinyMCE.get(\'ShortDesc\').show();return false;">[Show]</a>		<a href="javascript:;" onclick="tinyMCE.get(\'ShortDesc\').hide();return false;">[Hide]</a></label><div class="clear-cp"></div> ');
			
			 echo('<label>Long Description: <br /><textarea name="LongDesc" id="LongDesc" cols="" rows="6">'.$row['LongDesc'].'</textarea><a href="javascript:;" onclick="tinyMCE.get(\'LongDesc\').show();return false;">[Show]</a>		<a href="javascript:;" onclick="tinyMCE.get(\'LongDesc\').hide();return false;">[Hide]</a></label><div class="clear-cp"></div>');
		?>
        
        


        <dl class="cp_slideDown">
 			<dt>More</dt> 
            <dd>
            <div class="wrapper">
            	
                	<?php
                    //CREATE LINKNAVIGATOR LINKS
                    //$LinkNavigator = ('<option value="">Select a link from available pages of the website...</option>');
                    //$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
//                    $resultNav0 = $db->query("SELECT * FROM RED_Sections WHERE Active='Y' AND Sections <> 'administrator' ORDER BY Sections ASC");
//                    $resultNav0_counter = $resultNav0->num_rows;
//                    while($rowNav0 = mysqli_fetch_assoc($resultNav0))
//                    {
//                        $this->Section=$rowNav0['Sections'];
//                        if ($this->Section=='home')
//                        $this->SectionVal='';
//                        else
//                        $this->SectionVal='/'.$this->Section;
//                        $LinkNavigator = $LinkNavigator . ('<option value="'.$this->SectionVal.'/">'.$this->SectionVal.'/</option>');
//                        
//                        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
//                        $resultNav1 = $db->query("SELECT * FROM RED_Articles WHERE  Sections='".$this->Section."' AND Categories='' AND SubCategories='' ORDER BY Updated DESC");
//                        $resultNav1_counter = $resultNav1->num_rows;
//                        while($rowNav1 = mysqli_fetch_assoc($resultNav1))
//                        {
//                            $this->Alias=$rowNav1['Alias'];
//                            $LinkNavigator = $LinkNavigator . ('<option value="'.$this->SectionVal.'/'.$this->Alias.'">'.$this->SectionVal.'/'.$this->Alias.'</option>');
//                        }
//                        
//                        $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
//                        $resultNav3 = $db->query("SELECT * FROM RED_Categories WHERE Active='Y' ORDER BY Categories ASC");
//                        $resultNav3_counter = $resultNav3->num_rows;
//                        while($rowNav3 = mysqli_fetch_assoc($resultNav3))
//                        {
//                            $this->Category=$rowNav3['Categories'];
//                            $LinkNavigator = $LinkNavigator . ('<option value="'.$this->SectionVal.'/'.$this->Category.'/">'.$this->SectionVal.'/'.$this->Category.'/</option>');
//                            
//                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
//                            $resultNav4 = $db->query("SELECT * FROM RED_Articles WHERE  Sections='".$this->Section."' AND Categories='".$this->Category."' AND SubCategories='' ORDER BY Updated DESC");
//                            $resultNav4_counter = $resultNav4->num_rows;
//                            while($rowNav4 = mysqli_fetch_assoc($resultNav4))
//                            {
//                                $this->Alias=$rowNav4['Alias'];
//                                $LinkNavigator = $LinkNavigator . ('<option value="'.$this->SectionVal.'/'.$this->Category.'/'.$this->Alias.'">'.$this->SectionVal.'/'.$this->Category.'/'.$this->Alias.'</option>');
//                            }
//                            
//                            $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
//                            $resultNav5 = $db->query("SELECT * FROM RED_SubCategories WHERE Active='Y' ORDER BY SubCategories ASC");
//                            $resultNav5_counter = $resultNav5->num_rows;
//                            while($rowNav5 = mysqli_fetch_assoc($resultNav5))
//                            {
//                                $this->SubCategory=$rowNav5['SubCategories'];
//                                $LinkNavigator = $LinkNavigator . ('<option value="'.$this->SectionVal.'/'.$this->Category.'/'.$this->SubCategory.'/">'.$this->SectionVal.'/'.$this->Category.'/'.$this->SubCategory.'/</option>');
//                                
//                                $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
//                                $resultNav6 = $db->query("SELECT * FROM RED_Articles WHERE  Sections='".$this->Section."' AND Categories='".$this->Category."' AND SubCategories='".$this->SubCategory."' ORDER BY Updated DESC");
//                                $resultNav6_counter = $resultNav6->num_rows;
//                                while($rowNav6 = mysqli_fetch_assoc($resultNav6))
//                                {
//                                    $this->Alias=$rowNav6['Alias'];
//                                    $LinkNavigator = $LinkNavigator . ('<option value="'.$this->SectionVal.'/'.$this->Category.'/'.$this->SubCategory.'/'.$this->Alias.'">'.$this->SectionVal.'/'.$this->Category.'/'.$this->SubCategory.'/'.$this->Alias.'</option>');	
//                                }
//                                
//                            }
//                            
//                        }
//                        
//                    }
                    //END LINKNAVIGATOR LINKS
					
					echo('<div class="titleleft"><label style="display:inline;">Link: <input name="Link" type="text" id="gal_link" value="' . $row['Link'] . '" /></label>');
					echo ('</div>'); 
					//echo ('<div class="titleleft">');
//					echo '<script type="text/javascript">'. "\n";
//					echo '<!--' ."\n";
//					echo '$(\'#LinkNavigator\').bind(\'change\', function() {'. "\n";
//					echo '$(\'#gal_link\').val($(this).val());'. "\n";
//					echo '});'. "\n";
//					echo '-->'. "\n";
//					echo '</script>';
//					echo('<select name="LinkNavigator" id="LinkNavigator">');
//					echo ($LinkNavigator);
//					echo('</select>');
//					echo ('</div>');
					?>
            		
                <div class="titleleft">
                	<?php
                    global $checked;
					if ($row['NewWindow']==='Y')
					$checked='checked="checked"';
					echo ('<label style="display:inline;" title="Open New Window">Open Blank <input name="NewWindow" type="checkbox" '.$checked.' value="Y" /></label>');
					?>
                </div>
            </div>
            <div class="wrapper">
            	<div class="titleleft">
                    <div class="titleleft">
                    <label style="width:75px" title="Used in Features">Features Picture:</label>
                        <?php
                        if ($BigPict<>''){
                            ?>
                        
                            <input name="BigPict" type="hidden" value="<?php echo $BigPict ?>" />
                            <img src="/images/resize.php?w=60&h=45&amp;img=/images/articles/<?php echo $BigPict ?>" alt=""><br/>
                            <label><input name="Delete_BigPict" type="checkbox" value="Y">Delete</label>
                        
                        <?php
                        }
                        ?>
                   
                    </div>
                    <div id="dropbox" style="width: 150px; min-height:80px; float:left">
                        <span class="message">Drop image <br />
        here to upload.</span>
                    </div>
                    <div class="clear-cp"></div>
                    <div class="titleleft">
                    <label style="width:75px" title="Used in Article Description or Short Articles">Small Picture:</label>
                        <?php
                        if ($SmallPict<>''){
                            ?>
                        
                            <input name="SmallPict" type="hidden" value="<?php echo $SmallPict ?>" />
                            <img src="/images/resize.php?w=60&h=45&amp;img=/images/articles/<?php echo $SmallPict ?>" alt=""><br/>
                            <label><input name="Delete_SmallPict" type="checkbox" value="Y">Delete</label>
                        
                        <?php
                        }
                        ?>
                    
                    </div>
                    
                    <div id="dropbox2" style="width: 150px; min-height:80px; float:left">
                        <span class="message2">Drop image <br />
        here to upload.</span>
                    </div>
                    <div class="titleleft">
                        <label>Pict Alignment:
                        <select name="SmallPictAlign">
                        <option value="">- null -</option>
                        <option value="Top" <?php if ($row['SmallPictAlign']=='Top') echo 'selected="selected"'?>>Top</option>
                        <option value="Left" <?php if ($row['SmallPictAlign']=='Left') echo 'selected="selected"'?>>Left</option>
                        <option value="Right" <?php if ($row['SmallPictAlign']=='Right') echo 'selected="selected"'?>>Right</option>
                        </select>
                        </label>
                    </div>
                    <div class="clear-cp"></div>
                    <div class="titleleft">
                    <label style="width:75px" title="Used in Article Landing Page">Article Picture:</label>
                        <?php
                        if ($SmallPict2<>''){
                            ?>
                        
                            <input name="SmallPict2" type="hidden" value="<?php echo $SmallPict2 ?>" />
                            <img src="/images/resize.php?w=60&h=45&amp;img=/images/articles/<?php echo $SmallPict2 ?>" alt=""><br/>
                            <label><input name="Delete_SmallPict2" type="checkbox" value="Y">Delete</label>
                        
                        <?php
                        }
                        ?>
                    
                    </div>
                    
                    <div id="dropbox3" style="width: 150px; min-height:80px; float:left">
                        <span class="message3">Drop image <br />
        here to upload.</span>
                    </div>
                    <div class="titleleft">
                        <label>Pict Alignment 2:
                        <select name="SmallPictAlign2">
                        <option value="">- null -</option>
                        <option value="Top" <?php if ($row['SmallPictAlign2']=='Top') echo 'selected="selected"'?>>Top</option>
                        <option value="Left" <?php if ($row['SmallPictAlign2']=='Left') echo 'selected="selected"'?>>Left</option>
                        <option value="Right" <?php if ($row['SmallPictAlign2']=='Right') echo 'selected="selected"'?>>Right</option>
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
                    $result3 = $db->query("SELECT Sections FROM RED_Sections WHERE Active='Y'");
                    if ($result3) {
                    while ($row3 = mysqli_fetch_assoc($result3)) {
                        if ($row3['Sections']==$Section)    
                            echo '<option value="' . $row3['Sections'] . '" selected="selected">' . $row3['Sections'] . '</option>';
                        else
                            echo '<option value="' . $row3['Sections'] . '">' . $row3['Sections'] . '</option>';
                        }
                    }
                    
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
                    <label title="yyyy-mm-dd hh:mm:ss" >Start Date: <input name="StartDate" type="text" id="date" value="<?php echo $StartDate ?>" /></label>
                    <label title="yyyy-mm-dd hh:mm:ss">Exp Date: <input name="ExpDate" type="text" id="date" value="<?php echo $ExpDate ?>" /></label>
                </div>
            </div>  
         </dd>
         </dl>  
         <input type="hidden" name="RecordID" id="RecordID" value="<?php echo $RecordID ?>" />
         <input type="hidden" name="EditedBy" id="EditedBy" value="<?php echo $_SESSION['alias']?>" />
        <input type="submit" name="submit" value="Save" id="save"/> <span id="msggbox_update_content" style="display:none"></span>
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