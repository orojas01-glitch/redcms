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

#[\AllowDynamicProperties]
class gallery
/** album function vars:
* $component - Main component(s) installed.  Gallery. It can load 3 different kind of media: iframe album. list album.  video.
* $query - passing main variables info. 3 options: Section. Category. Alias. 
* $VarFeatured - CategoryFeatured. SectionFeatured.
* $VarPosition - 3 options: PagePosition. CategoryPosition. SectionPosition.  
* $position - 5 options: 1. 2. 3. 4. null. 

*http://www.no-margin-for-errors.com/projects/prettyphoto-jquery-lightbox-clone/#prettyPhoto
**/
{
	public $vWidth;
    public $vHeight;
	public function album($position, $recordid, $layout, $SmallPict)
	{	
		/**
		* READ THE SPECIFIED IMAGES WIDTH FOR THE LAYOUT
		**/
		
		//$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
//		$result = $db->query("SELECT * FROM RED_Layouts WHERE UniqueName='" . $layout . "'");
//		
//		while($row = mysqli_fetch_assoc($result))
//		{
//			
//			switch ($position)
//			{
//				case '':
//				$This->Width=$row['w_Pos1'];
//				$This->Height=$row['h_Pos1'];
//				$This->WidthDivisor=$row['w_div_Pos1'];
//				$This->vWidth=$row['vw_Pos1'];
//				$This->vHeight=$row['vh_Pos1'];
//				break;
//				case '1':
//				$This->Width=$row['w_Pos1'];
//				$This->Height=$row['h_Pos1'];
//				$This->WidthDivisor=$row['w_div_Pos1'];
//				$This->vWidth=$row['vw_Pos1'];
//				$This->vHeight=$row['vh_Pos1'];
//				break;
//				case '2':
//				$This->Width=$row['w_Pos2'];
//				$This->Height=$row['h_Pos2'];
//				$This->WidthDivisor=$row['w_div_Pos2'];
//				$This->vWidth=$row['vw_Pos2'];
//				$This->vHeight=$row['vh_Pos2'];
//				break;
//				case '3':
//				$This->Width=$row['w_Pos3'];
//				$This->Height=$row['h_Pos3'];
//				$This->WidthDivisor=$row['w_div_Pos3'];
//				$This->vWidth=$row['vw_Pos3'];
//				$This->vHeight=$row['vh_Pos3'];
//				break;
//				case '4':
//				$This->Width=$row['w_Pos4'];
//				$This->Height=$row['h_Pos4'];
//				$This->WidthDivisor=$row['w_div_Pos4'];
//				$This->vWidth=$row['vw_Pos4'];
//				$This->vHeight=$row['vh_Pos4'];
//				break;
//				
//			}
//		}
//		
		
		
		/**
		* END
		**/
		
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_C_Gallery WHERE RefID='".$recordid."'");
		//echo ('start'.$result->num_rows.'<br/>');
		$result_counter = $result->num_rows;
		//
		while($row = mysqli_fetch_assoc($result))
		{
		
			$GalleryType=$row['GalleryType'];
			if ($row['NewWindow']==='Y')
			$target='_blank';
			
			switch($GalleryType)
			{
				//////////////////////////////////////////////
				case 'Gallery':
				
					$Photos=$row['LongDesc'];
					$This->Width=$This->Width/$This->WidthDivisor;
					$photo=explode(',', $Photos);
					$Descriptions=$row['ShortDesc'];
					$tag=explode(',', $Descriptions);
					if ($Photos!=''){
						for ($t=0; $t<count($photo); $t++)
						{
							$Description=explode(';',$tag[$t]);
							$tagtitle=$Description[0];
							$tagurl=$Description[1];
							
							if ($tagurl!='')
								echo '<figure class="img-indent" style="text-align:center"><!--<a class="hover-image" data-gal="prettyPhoto[1]" title="" href="/images/gallery/'.$photo[$t].'">--><img src="/images/resize.php?w='.$This->Width.'&amp;img=/images/gallery/'.$photo[$t].'"><!--</a>--><br/><span class="img-indent-description"><!--<a href="'.$tagurl.'" target="_blank">-->'.$tagtitle.'<!--</a>--></span></figure>';
							else
								echo '<figure class="img-indent" style="text-align:center"><!--<a class="hover-image" data-gal="prettyPhoto[1]" title="" href="/images/gallery/'.$photo[$t].'">--><img src="/images/resize.php?w='.$This->Width.'&amp;img=/images/gallery/'.$photo[$t].'"><!--</a>--><br/><span class="img-indent-description">'.$tagtitle.'</span></figure>';
							
						}
					}
				break;
				
				//////////////////////////////////////////////
				case 'Carrousel':
					echo '<ul id="carousel" class="jcarousel-skin-tango">';
					$Photos=$row['LongDesc'];
					$This->Width=$This->Width/$This->WidthDivisor;
					$photo=explode(',', $Photos);
					$Descriptions=$row['ShortDesc'];
					$tag=explode(',', $Descriptions);
					
					
					for ($t=0; $t<count($photo); $t++)
					{
					$Description=explode(';',$tag[$t]);
					$tagtitle=$Description[0];
					$tagurl=$Description[1];
					
					if ($tagurl!='')
					echo '<li><figure><img src="/images/resize.php?w=120&amp;img=/images/gallery/'.$photo[$t].'"></figure><a href="'.$tagurl.'" target="_blank">'.$tagtitle.'</a></li>';
					else
					echo '<li><figure><img src="/images/resize.php?w=120&amp;img=/images/gallery/'.$photo[$t].'"></figure><span>'.$tagtitle.'</span></li>';
					}
					echo '</ul><div class="clear-1"></div>';
				
				break;
				
				//////////////////////////////////////////////
				case 'Video':
					
					if($row['Title']<>'')
					echo('<h3>'.$row['Title'].'</h3>');
					
					$source=explode('/', $row['LongDesc']);
                    if (isset($source[2]) && isset($source[3])) {
					$VideoSrc = $source[2]; // youtube or vimeo
					$VideoID = $source[3]; // video unique id
                    }else{
                    $VideoSrc = '';
                    $VideoID  = '';
                    }
					
					/*echo 'VideoSrc='.$VideoSrc.'<br/>';
					echo 'VideoID='.$VideoID.'<br/>';*/
					
					/*for ($t=0; $t<count($source); $t++)
					{
						echo $t.'='.$source[$t].'<br/>';	
					}*/
					/*for ($v=0; $v<count($VideoIDVar); $v++)
					{
						echo $v.'='.$VideoIDVar[$v].'<br/>';
					}*/
					$VideoIDVar = explode('=',$VideoID);
					//echo 'count='.(count($VideoIDVar));
					if (count($VideoIDVar)>1){// video unique id is the long version. apply to youtube links
					$VideoID=explode('&',$VideoIDVar[1]);
					$VideoID=$VideoID[0];	
					}
					
					switch (strtolower($VideoSrc))
					{
							case 'vimeo.com':
							$player='<iframe src="https://player.vimeo.com/video/'.$VideoID.'" width="'.$this->vWidth.'" height="'.$this->vHeight.'" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
							echo '<div class="js-video vimeo">';
									echo $player;
								echo '</div>';
							break;
							
							case 'youtu.be':
							$player='<iframe width="'.$this->vWidth.'" height="'.$this->vHeight.'" src="https://www.youtube.com/embed/'.$VideoID.'?wmode=transparent" frameborder="0" allowfullscreen></iframe>';
								echo '<div class="js-video widescreen">';
									echo $player;
								echo '</div>';
							break;
							case 'www.youtube.com':
								$player='<iframe width="'.$this->vWidth.'" height="'.$this->vHeight.'" src="https://www.youtube.com/embed/'.$VideoID.'?wmode=transparent" frameborder="0" allowfullscreen></iframe>';
								echo '<div class="js-video widescreen">';
									echo $player;
								echo '</div>';
							break;
					}
					
					/*if ($SmallPict!=''){
						echo '<figure class="img-indent"><a class="hover-video" data-gal="prettyPhoto" title="" href="'.$row['LongDesc'].'"><div class="play-video"><img src="/images/resize.php?w='.$This->vWidth.'&amp;img=/images/articles/'.$SmallPict.'" alt=""></div></a></figure>';
					} else*/
						//echo '<figure class="img-indent"><a class="hover-video" data-gal="prettyPhoto" title="" href="'.$row['LongDesc'].'">'.$player.'</a></figure>';
						

					if ($row['ShortDesc']!='')
					echo $row['ShortDesc'];
					
					if ($row['Link']<>''){
					$link='href="'.$row['Link'].'" target="'.$target.'"';
					echo '<a '.$link.' class="link-1">Read More</a><div class="clear-1"></div>';
					}
				
				break;
				
				///////////////////////////////////////////////
				case 'Banner':
					
					if ($row['Link']<>''){
					$link='href="'.$row['Link'].'" target="'.$target.'"';
					echo '<figure class="img-indent"><a '.$link.' title=""><img src="/images/gallery/'.$row['LongDesc'].'" alt=""></a></figure>';
                    }else{
                       echo '<figure class="img-indent"><img src="/images/gallery/'.$row['LongDesc'].'" alt=""></figure>'; 
                    }
				break;
				
			}
		
		}
		
	
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	
	public function cp_album($position, $recordid, $layout, $VarFeatures, $VarPosition, $Table)
	{	
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_C_Gallery WHERE RefID='".$recordid."'");
		
		//echo ('start'.$result->num_rows.'<br/>');
		$result_counter = $result->num_rows;
		//
		while($row = mysqli_fetch_assoc($result))
		{
			$Title=$row['Title'];
//			switch ($VarFeatured)
//			{
//			case '':
//			echo '<h5>'.$Title.'</h5>';
//			break;
//			default:
			//echo '<h7 id="cp">'.$Title.'</h7>';
//			}
			
			$RecordID=$row['RecordID'];
			$Alias=$row['Alias'];
			$Alias=preg_replace('/-/','_',$Alias);

				/// COMPARE SESSION 'AdminComponents' WITH RED_COMPONENTS.
				// IF VALUE EXIST THEN SHOW UPDATE BUTTON. IF NOT, DISPLAY MESSAGE FOR "ADMIN NOT AUTHORIZED TO UPDATE".
				$AdminComponents = explode(",", $_SESSION['AdminComponents']);
				//echo($_SESSION['AdminComponents'].'='.count($AdminComponents.'<br/>'));
				for ($w=0; $w<=count($AdminComponents); $w++)
				{
				$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$resultC = $db->query("SELECT RecordID FROM RED_Components WHERE RecordID='".$AdminComponents[$w]."' AND UniqueName='Gallery'");
				//echo ($resultC->num_rows);
				if(($resultC->num_rows==0)&&($w==count($AdminComponents))){
					//echo 'ADMINISTRATOR NOT AUTHORIZED TO UPDATE';
					echo '<script type="text/javascript">'. "\n";
					echo '<!--' ."\n";
					echo 'function edit_content_'.$Alias.'_'.$RecordID.' (content_'.$Alias.'_'.$RecordID.')'. "\n".'{' . "\n"; 
					echo '$(\'#msggbox_alert_'.$position.'\').html("You\'re not authorized to edit this content.")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '-->'. "\n";
					echo '</script>';
					echo '<form id="content_'.$Alias.'_'.$RecordID.'" class="form" name="content_'.$Alias.'_'.$RecordID.'" method="post" onSubmit="return edit_content_'.$Alias.'_'.$RecordID.'(this);">';
					echo '<h7 id="cp"> '.$row['Title'].'</h7><br/><input type="submit" name="Edit" class="cp" id="cp_gallery" value="Edit '.$row['GalleryType'].'"/>';
					echo '</form>';
				}elseif(($resultC->num_rows==0));
				else{
					//echo 'ADMINISTRATOR AUTHORIZED TO UPDATE';
					echo '<script type="text/javascript">'. "\n";
					echo '<!--' ."\n";
					echo 'function edit_gallery_'.$Alias.'_'.$RecordID.' (gallery_'.$Alias.'_'.$RecordID.')'. "\n".'{' . "\n"; 
					//echo 'alert (dataString);'. "\n";
					//echo 'return false;'. "\n";
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/admin/bin/edit_gallery.php", '. "\n";
					echo 'cache: false,'. "\n"; 
					//echo 'data: dataString, '. "\n";
					echo 'data: $("#gallery_'.$Alias.'_'.$RecordID.'").serialize(), '. "\n";
					echo 'success: function(data) { '. "\n";
					//echo 'alert (data);'. "\n";
					echo 'if (data)'. "\n"; 
					echo '{'. "\n";
					//echo '$(\'#edit_content_grid\').html("<div id=\'message_'.$Alias.'_'.$RecordID.'_'.$RecordID.'\'></div>");'. "\n";
					echo '$(\'#edit_content_grid\').hide();'. "\n";
					//echo '$(\'#message_'.$Alias.'_'.$RecordID.'\').html("<h6>View All.</h6>")'. "\n";
					echo '$(\'#msggbox_edit_content\').html(data)'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'_'.$RecordID.'\');'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo 'else'. "\n"; 
					echo '{'. "\n";
					//echo '$(\'#form_'.$Alias.'_'.$RecordID.'\').html("<div id=\'message_'.$Alias.'_'.$RecordID.'\'></div>");'. "\n";
					echo '$(\'#msggbox_edit_content\').html("error.")'. "\n";
					echo '.append("<p>Please try again.</p>")'. "\n";
					echo '.hide()'. "\n";
					echo '.fadeIn(1500, function() {'. "\n";
					echo '$(\'#message_'.$Alias.'_'.$RecordID.'\');'. "\n";
					//echo '$(\'#message\').append("<img id=\'checkmark\' src=\'/'.language.'/images/check.png\' />");'. "\n";
					echo '});'. "\n";
					echo '}'. "\n";
					echo '}'. "\n";
					echo '});'. "\n";
					echo 'return false;'. "\n";
					echo '}'. "\n";
					echo '-->'. "\n";
					echo '</script>';
					echo '<form id="gallery_'.$Alias.'_'.$RecordID.'" class="form" name="gallery_'.$Alias.'_'.$RecordID.'" method="post" onSubmit="return edit_gallery_'.$Alias.'_'.$RecordID.'(this);">';
					echo '<h7 id="cp"> '.$row['Title'].'</h7><br/><input type="submit" name="Edit" class="cp" id="cp_gallery" value="Edit '.$row['GalleryType'].'"/>';
					echo '<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
					echo '<input type="hidden" name="ArtRecordID" id="RecordID" value="'.$recordid.'" />';
					echo '<input type="hidden" name="VarPosition" id="VarPosition" value="'.$VarPosition.'" />';
                    echo '<input type="hidden" name="Article" id="Article" value="'.article.'" />';
					echo '<input type="hidden" name="Layout" id="Layout" value="'.$layout.'" />';
                    
					echo '</form>';
					//END "ADMIN AUTHORIZED TO UPDATE".
				break;
				}
				
				}
				//END COMPARE SESSION
					echo '<hr id="cp">';
				
				
			
			//echo('<br clear="all">');
		
		}
		
	
	}
	
}
?>