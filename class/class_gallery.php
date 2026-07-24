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
require_once __DIR__ . '/../includes/public_render_helpers.php';
require_once __DIR__ . '/../includes/legacy_component_helpers.php';

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
		$context = red_legacy_public_gallery_context_validate(
			red_legacy_public_gallery_context($recordid, $layout, $position)
		);
		$dimensions = $context['dimensions'];
		$this->Width = $dimensions['Width'];
		$this->Height = $dimensions['Height'];
		$this->WidthDivisor = $dimensions['WidthDivisor'];
		$this->vWidth = $dimensions['vWidth'];
		$this->vHeight = $dimensions['vHeight'];
		//
		foreach($context['rows'] as $preparedRow)
		{
			$row = $preparedRow['record'];
			$GalleryType=$row['GalleryType'];
			$target=$preparedRow['link']['target'];
			
			switch($GalleryType)
			{
				//////////////////////////////////////////////
				case 'Gallery':
				
					$this->Width=$preparedRow['gallery']['width'];
					if ($preparedRow['gallery']['photos']){
						foreach ($preparedRow['gallery']['photos'] as $photo)
						{
							$tagtitle=$photo['title'];
							$tagurl=$photo['url'];
							
							if ($tagurl!='')
								echo '<figure class="img-indent" style="text-align:center"><!--<a class="hover-image" data-gal="prettyPhoto[1]" title="" href="/images/gallery/'.red_public_html($photo['file']).'">--><img class="red-gallery-image" src="/images/resize.php?w='.$this->Width.'&amp;img=/images/gallery/'.red_public_html($photo['file']).'"><!--</a>--><br/><span class="img-indent-description"><!--<a href="'.red_public_html($tagurl).'" target="_blank">-->'.red_public_display_text($tagtitle).'<!--</a>--></span></figure>';
							else
								echo '<figure class="img-indent" style="text-align:center"><!--<a class="hover-image" data-gal="prettyPhoto[1]" title="" href="/images/gallery/'.red_public_html($photo['file']).'">--><img class="red-gallery-image" src="/images/resize.php?w='.$this->Width.'&amp;img=/images/gallery/'.red_public_html($photo['file']).'"><!--</a>--><br/><span class="img-indent-description">'.red_public_display_text($tagtitle).'</span></figure>';
							
						}
					}
				break;
				
				//////////////////////////////////////////////
				case 'Video':
					
					if($row['Title']<>'')
					echo('<h3>'.red_public_display_text($row['Title']).'</h3>');
					
					$VideoSrc = $preparedRow['video']['provider']; // youtube or vimeo
					$VideoID = $preparedRow['video']['id']; // video unique id
					
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
					switch ($VideoSrc)
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
					$link='href="'.red_public_html($preparedRow['link']['href']).'" target="'.red_public_html($target).'"';
					echo '<a '.$link.' class="link-1">Read More</a><div class="clear-1"></div>';
					}
				
				break;
				
				///////////////////////////////////////////////
				case 'Banner':
					
					if ($row['Link']<>''){
					$link='href="'.red_public_html($preparedRow['link']['href']).'" target="'.red_public_html($target).'"';
					echo '<figure class="img-indent"><a '.$link.' title=""><img class="red-gallery-image" src="/images/gallery/'.red_public_html($preparedRow['banner']['image']).'" alt=""></a></figure>';
                    }else{
                       echo '<figure class="img-indent"><img class="red-gallery-image" src="/images/gallery/'.red_public_html($preparedRow['banner']['image']).'" alt=""></figure>';
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
		$rows = red_public_gallery_rows($db->connection, $recordid);
		
		//echo ('start'.$result->num_rows.'<br/>');
		$result_counter = count($rows);
		$adminComponentIds = red_public_admin_component_ids($_SESSION['AdminComponents'] ?? '');
		$canEditGallery = red_public_admin_component_authorized($db->connection, 'Gallery', $adminComponentIds);
		//
		foreach($rows as $row)
		{
			$Title=red_public_display_text($row['Title']);
//			switch ($VarFeatured)
//			{
//			case '':
//			echo '<h5>'.$Title.'</h5>';
//			break;
//			default:
			//echo '<h7 id="cp">'.$Title.'</h7>';
//			}
			
			$RecordID=$row['RecordID'];
			$Alias=red_public_js_identifier($row['Alias']);
			$GalleryType=red_public_html($row['GalleryType']);
			$GalleryActionKey=strtolower((string) ($row['GalleryType'] ?? ''));
			if (!in_array($GalleryActionKey, ['banner', 'gallery', 'video'], true)) {
				$GalleryActionKey='gallery';
			}

				/// COMPARE SESSION 'AdminComponents' WITH RED_COMPONENTS.
				// IF VALUE EXIST THEN SHOW UPDATE BUTTON. IF NOT, DISPLAY MESSAGE FOR "ADMIN NOT AUTHORIZED TO UPDATE".
				if(!$canEditGallery){
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
					echo '<h7 id="cp"> '.$Title.'</h7><br/><input type="submit" name="Edit" class="cp red-admin-component-action red-admin-component-action--'.$GalleryActionKey.'" id="cp_gallery" value="Edit '.$GalleryType.'"/>';
					echo '</form>';
				}else{
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
					echo '<h7 id="cp"> '.$Title.'</h7><br/><input type="submit" name="Edit" class="cp red-admin-component-action red-admin-component-action--'.$GalleryActionKey.'" id="cp_gallery" value="Edit '.$GalleryType.'"/>';
					echo '<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
					echo '<input type="hidden" name="ArtRecordID" id="RecordID" value="'.$recordid.'" />';
					echo '<input type="hidden" name="VarPosition" id="VarPosition" value="'.red_public_html($VarPosition).'" />';
                    echo '<input type="hidden" name="Article" id="Article" value="'.red_public_html(red_public_route_value('article')).'" />';
					echo '<input type="hidden" name="Layout" id="Layout" value="'.red_public_html($layout).'" />';
                    
					echo '</form>';
					//END "ADMIN AUTHORIZED TO UPDATE".
				}
				
				//END COMPARE SESSION
					echo '<hr id="cp">';
				
				
			
			//echo('<br clear="all">');
		
		}
		
	
	}
	
}
?>
