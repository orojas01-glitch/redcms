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

/**

/**
* THIS CLASS CONSTRUCT THE ARTICLE(S) WITHIN SECTION, CATEGORY OR ARTICLE SELECTED.
* ONLY FULL-WIDTH IS SET TO WRITE ARTICLES THAT DONT HAVE A POSITION ESTABLISHED.

* OTHER VARIABLES ARE PASS FROM CONFIG.PHP. ( $countpage, $section, $category, $article, $link )

* $section - Main sections: i.e: Services. Portfolio. About. Contact.
* $category - SubCategory: i.e: WebDesign. Technology. Content. Marketing. WebDesign. Multimedia. Print-Identity. Other-Media. About. Contact.
* $article - article selected (alias).  This is set only if last part of the url do NOT has a backslash. (this-is-a-section/)  vs (/this-is-an-article)
* $link - Select link from RED_Menu. This is to obtain the layout of the articles: Full-Width. Two-Columns. Three-Columns. Four-Columns. Multi-Columns1. Multi-Columns2)
* $query - The query was created based on the url.  The first folder determine the language, second is section and third is category, fourth is article selected. Refer to class_build_page.
* $VarPosition - The VarPosition was created based on the url. i.e: SectionPosition, CategoryPosition, PagePosition.  Refer to class_build_page.
* VarFeatured - The VarFeatured was created based on the url. i.e: HomepageFeatured, SectionFeatured, CategoryFeatured. Refer to class_build_page. 
* $position - 5 options: 1. 2. 3. 4. null. 
* $layout - Full-Width. Two-Columns. Three-Columns. Four-Columns. Multi-Columns1. Multi-Columns2.
**/
require_once __DIR__ . '/../includes/public_render_helpers.php';

#[\AllowDynamicProperties]
class Article
{
	
	public function Article($recordid,$layout,$article,$position)
	{
		global $URL;
		//echo $this->query;
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$dimensions = red_public_layout_dimensions($db->connection, $layout, $position);
		$this->Width = $dimensions['Width'];
		$this->WidthDivisor = $dimensions['WidthDivisor'];
		$this->Height = $dimensions['Height'];
		$This = $this;
		// display all active records. Position is Required.
		$rows = red_public_article_render_rows($db->connection, $recordid);
		$result_counter = count($rows);
		foreach($rows as $row)
		{
            
			//echo 'ini'. $result_counter;
			//if ($result_counter == $result->num_rows){
				
			//BUILD LINK AND CLOSELINE
				if ($row['LongDesc']!='' || $row['Link']!='' || $row['Component']=='Article'){
                    
					$Link=$row['Alias'];
					if ($row['SubCategories'])
					$Link=$row['SubCategories'].'/'.$Link;
					if ($row['Categories'])
					$Link=$row['Categories'].'/'.$Link;
					if ($row['Sections']!='home')
					$Link=$row['Sections'].'/'.$Link;
					
					$Link='/'.$Link;
                    
					
					// GET THE LINK FOR THIS LABEL. FIRST, CHECK FOR EXTERNAL LINKS.
				
					if ($row['Link'])
					$Link=$row['Link'];
					
					$targetAttr = isset($target) ? $target : '_self';
				    if ($row['NewWindow']==='Y')
				    $targetAttr='_blank';
					
					$closeline='<a href="'.red_public_html($Link).'" target="'.red_public_html($targetAttr).'" class="btn-default btn5">Leer m&aacute;s</a><div class="clear-1"></div>';
                   
				}else
					$closeline='<div class="clear-1"></div>';
				
				// check the alignment and add the width according to position
				//if ($row['SmallPictAlign']!='Top')
					//$This->Width=$This->Width/2;
				//
				
				
				
				
				if ($article===$row['Alias']){ 
					$title='<h2>'.red_public_display_text($row['Title']).'</h2>';
                    
					//$image='<img src="/images/resize.php?w='.$This->Width.'&amp;img=/images/articles/'.$row['SmallPict2'].'" align="'.$row['SmallPictAlign2'].'" title="'.$row['Title'].'" class="SmallPict_'.$row['SmallPictAlign2'].'">';
					$image='<figure><img src="/images/articles/'.red_public_html($row['SmallPict2']).'" align="'.red_public_html($row['SmallPictAlign2']).'" title="'.red_public_display_text($row['Title']).'" class="SmallPict_'.red_public_html($row['SmallPictAlign2']).'"></figure>';
				}else{
                   
					if ($row['LongDesc']!='' || $row['Link']!='' || $row['Component']=='Article'){
						$title='<h2><a href="'.red_public_html($Link).'" target="'.red_public_html($targetAttr).'" class="link-article">'.red_public_display_text($row['Title']).'</a></h2>';
						$image='<figure><a href="'.red_public_html($Link).'" target="'.red_public_html($targetAttr).'"><img src="/images/articles/'.red_public_html($row['SmallPict']).'" align="'.red_public_html($row['SmallPictAlign']).'" title="'.red_public_display_text($row['Title']).'" class="SmallPict_'.red_public_html($row['SmallPictAlign']).'" border="0" style="margin-bottom:20px;"></a></figure>';
                        
					}else{
						$title='<h2>'.red_public_display_text($row['Title']).'</h2>';
						$image='<figure><img src="/images/resize.php?w='.$This->Width.'&amp;img=/images/articles/'.red_public_html($row['SmallPict']).'" align="'.red_public_html($row['SmallPictAlign']).'" title="'.red_public_display_text($row['Title']).'" class="SmallPict_'.red_public_html($row['SmallPictAlign']).'" border="0"></figure>';
                        
					}
				}
				//
				//
				if ($article===$row['Alias']){ //article landing page
					echo ('<div class="thumb-pad3 clearfix">');
                    echo ('<div class="thumbnail">');
                    echo ('<div class="badgeBox">');
					echo($title);
					
					// add small image 2 if any available for main article landing.
					if ($row['SmallPict2']!=''){
						echo $image;
					} //
					echo($row['LongDesc']);
					echo '<div class="clear-1"></div>';
					//facebook
					echo ('<div class="fb-like" data-href="'.$URL.'" data-width="500" data-layout="" data-action="" data-size="" data-share="true"></div>');
					echo ('</div>');
                 	echo ('</div>');
               	 	echo ('</div>');
					
				}else{ // show more and use small image 1.
					
					echo ('<div class="thumb-pad6 clearfix">');
                    echo ('<div class="thumbnail">');
                    echo ('<div class="badgeBox">');
							echo ('<div class="caption <!--maxheight-->">');
							echo($title);
							// add small image 1 if any available for sections, categories or subcategories. 
							if ($row['SmallPict']!=''){
									echo $image;
								} //
		
							
							echo($row['ShortDesc']);
							echo ('</div>');
							echo $closeline;
					echo ('</div>');
                 	echo ('</div>');
               	 	echo ('</div>');

				}// end article.
				//
		$result_counter = ($result_counter - 1);
		}
		//echo 'end'. $result_counter;
		if ($result_counter == 0);
		$db->close();
	}
	
}
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////

#[\AllowDynamicProperties]
class cp_Article
{
	public function cp_Article($position, $recordid, $VarPosition, $layout)
	{
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		// display all active records. Position is Required.
		$rows = red_public_article_render_rows($db->connection, $recordid, $position === '0');
		
		//echo ($result->num_rows);
		$result_counter = count($rows);
		$adminComponentIds = red_public_admin_component_ids($_SESSION['AdminComponents'] ?? '');
		$canEditArticle = red_public_admin_component_authorized($db->connection, 'Article', $adminComponentIds);
		
		foreach($rows as $row)
		{
			$RecordID=$row['RecordID'];
			$Alias=red_public_js_identifier($row['Alias']);
			$Title=red_public_display_text($row['Title']);
			
			if ($position==='0'){
            	echo '<div style="float:left; padding-right:5px; margin-right:5px;">';
			}
			
				// COMPARE SESSION 'AdminComponents' WITH RED_COMPONENTS.
				// IF VALUE EXIST THEN SHOW UPDATE BUTTON. IF NOT, DISPLAY MESSAGE FOR "ADMIN NOT AUTHORIZED TO UPDATE".
				if(!$canEditArticle){
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
					echo '<h7 id="cp"> '.$Title.'</h7><br/><input type="submit" name="Edit" class="cp red-admin-component-action red-admin-component-action--article" id="cp_article" value="Edit Article"/>';
					echo '</form>';
				}else{
					//echo 'ADMINISTRATOR AUTHORIZED TO UPDATE';
					echo '<script type="text/javascript">'. "\n";
					echo '<!--' ."\n";
					echo 'function edit_content_'.$Alias.'_'.$RecordID.' (content_'.$Alias.'_'.$RecordID.')'. "\n".'{' . "\n"; 
					//echo 'alert (dataString);'. "\n";
					//echo 'return false;'. "\n";
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/admin/bin/edit_article.php", '. "\n";
					echo 'cache: false,'. "\n"; 
					//echo 'data: dataString, '. "\n";
					echo 'data: $("#content_'.$Alias.'_'.$RecordID.'").serialize(), '. "\n";
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
					echo '$(\'#msggbox_alert_'.$position.'\').html("error.")'. "\n";
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
					echo '<form id="content_'.$Alias.'_'.$RecordID.'" class="form" name="content_'.$Alias.'_'.$RecordID.'" method="post" onSubmit="return edit_content_'.$Alias.'_'.$RecordID.'(this);">';
					echo '<h7 id="cp"> '.$Title.'</h7><br/><input type="submit" name="Edit" class="cp red-admin-component-action red-admin-component-action--article" id="cp_article" value="Edit Article"/>';
					echo '<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
					echo '<input type="hidden" name="VarPosition" id="VarPosition" value="'.red_public_html($VarPosition).'" />';
					echo '<input type="hidden" name="Article" id="Article" value="'.red_public_html(red_public_route_value('article')).'" />';
					echo '<input type="hidden" name="Layout" id="Layout" value="'.red_public_html($layout).'" />';
					echo '</form>';
				//END "ADMIN AUTHORIZED TO UPDATE".
				}
				
				//END COMPARE SESSION
				echo '<hr id="cp">';
				//
				
			
			if ($position==='0'){
				echo '</div>';
			}
			
		$result_counter = ($result_counter - 1);
		}
		//echo 'end'. $result_counter;
		if ($result_counter == 0);
		
		$db->close();
	}
	
	
	
	
}
?>
