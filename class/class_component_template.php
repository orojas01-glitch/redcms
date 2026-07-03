<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.tv/documentation/ 
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/

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

/**
INSTRUCTIONS:
FIND AND REPLACE "component_template" with the component "UniqueName".

**/

#[\AllowDynamicProperties]
class component_template
{
	
	public function component_template($recordid,$layout,$article,$position)
	{
		
		/**
		* READ THE SPECIFIED IMAGES WIDTH FOR THE LAYOUT
		**/
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$result = $db->query("SELECT * FROM RED_Layouts WHERE UniqueName='" . $layout . "'");
		
		while($row = mysqli_fetch_assoc($result))
		{
			
			switch ($position)
			{
				case '':
				$This->Width=$row['w_Pos1'];
				$This->WidthDivisor=$row['w_div_Pos1'];
				$This->Height=$row['h_Pos1'];
				break;
				case '1':
				$This->Width=$row['w_Pos1'];
				$This->WidthDivisor=$row['w_div_Pos1'];
				$This->Height=$row['h_Pos1'];
				break;
				case '2':
				$This->Width=$row['w_Pos2'];
				$This->WidthDivisor=$row['w_div_Pos2'];
				$This->Height=$row['h_Pos2'];
				break;
				case '3':
				$This->Width=$row['w_Pos3'];
				$This->WidthDivisor=$row['w_div_Pos3'];
				$This->Height=$row['h_Pos3'];
				break;
				case '4':
				$This->Width=$row['w_Pos4'];
				$This->WidthDivisor=$row['w_div_Pos4'];
				$This->Height=$row['h_Pos4'];
				break;
				
			}
		}
		
		/**
		* END
		**/
		
		//echo $this->query;
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		// display all active records. Position is Required.
		$result = $db->query("SELECT * FROM RED_Articles WHERE RecordID='".$recordid."'");
		
		$result_counter = $result->num_rows;
		while($row = mysqli_fetch_assoc($result))
		{
			//echo 'ini'. $result_counter;
			//if ($result_counter == $result->num_rows){
				
			//BUILD LINK AND CLOSELINE
			/*	if ($row['LongDesc']!='' || $row['Link']!=''){
				$Link=$row['Alias'];
				if ($row['SubCategories'])
				$Link=$row['SubCategories'].'/'.$Link;
				if ($row['Categories'])
				$Link=$row['Categories'].'/'.$Link;
				if ($row['Sections']!='Home')
				$Link=$row['Sections'].'/'.$Link;
				
				$Link='/'.$Link;
				
				// GET THE LINK FOR THIS LABEL. FIRST, CHECK FOR EXTERNAL LINKS.
			
				if ($row['Link'])
				$Link=$row['Link'];
				
				if ($row['NewWindow']==='Y')
				$target='_blank';
				
				$closeline='<a href="'.$Link.'" target="'.$target.'" class="link-1">Read More</a><div class="clear-1"></div>';
				} else
				$closeline='<div class="clear-1"></div>';
			//
				
			//
				
				echo('<h5 id="">'.$row['Title'].'</h5>');
				//
						
				if ($article <> ''){
					if ($article===$row['Alias']){
						//echo 'equal';
						// add small image if any available for main article landing. check the alignment and add the width accordingto position
						if ($row['SmallPict2']!=''){
							if ($row['SmallPictAlign2']!='Top')
							$This->Width=$This->Width/2;
							//echo '<img src="/images/resize.php?w='.$This->Width.'&amp;img=/images/articles/'.$row['SmallPict2'].'" align="'.$row['SmallPictAlign2'].'" title="'.$row['Title'].'" class="SmallPict_'.$row['SmallPictAlign2'].'">';
							echo '<img src="/images/articles/'.$row['SmallPict2'].'" align="'.$row['SmallPictAlign2'].'" title="'.$row['Title'].'" class="SmallPict_'.$row['SmallPictAlign2'].'">';
						} //
						echo($row['LongDesc']);
						echo '<div class="clear-1"></div>';
					}else{
					// add small image if any. check the alignment and add the width accordingto position
					if ($row['SmallPict']!=''){
						if ($row['SmallPictAlign']!='Top')
							$This->Width=$This->Width/2;
							echo '<img src="/images/resize.php?w='.$This->Width.'&amp;img=/images/articles/'.$row['SmallPict'].'" align="'.$row['SmallPictAlign'].'" title="'.$row['Title'].'" class="SmallPict_'.$row['SmallPictAlign'].'">';
						} //
						echo($row['ShortDesc']);
						echo $closeline;
					}
				} else {
					// add small image if any. check the alignment and add the width according to position
					if ($row['SmallPict']!=''){
						if ($row['SmallPictAlign']!='Top')
							$This->Width=$This->Width/2;
							echo '<img src="/images/resize.php?w='.$This->Width.'&amp;img=/images/articles/'.$row['SmallPict'].'" align="'.$row['SmallPictAlign'].'" title="'.$row['Title'].'" class="SmallPict_'.$row['SmallPictAlign'].'">';
						} //
					echo($row['ShortDesc']);
					echo $closeline;
				}
			
				
			//*/
					
				
			
		$result_counter = ($result_counter - 1);
		}
		//echo 'end'. $result_counter;
		if ($result_counter == 0);
		
		$db->close();
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function cp_component_template($position, $recordid, $VarPosition)
	{
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		// display all active records. Position is Required.
		$result = $db->query("SELECT * FROM RED_Articles WHERE RecordID='".$recordid."'");
		
		//echo ($result->num_rows);
		$result_counter = $result->num_rows;
		
		while($row = mysqli_fetch_assoc($result))
		{
			$RecordID=$row['RecordID'];
			$Alias=$row['Alias'];
			$Alias=preg_replace('/-/','_',$Alias);
			
			if ($position==='0'){
            	echo '<div style="float:left; padding-right:5px; margin-right:5px;">';
			}
			
				// COMPARE SESSION 'AdminComponents' WITH RED_COMPONENTS.
				// IF VALUE EXIST THEN SHOW UPDATE BUTTON. IF NOT, DISPLAY MESSAGE FOR "ADMIN NOT AUTHORIZED TO UPDATE".
				$AdminComponents = explode(",", $_SESSION['AdminComponents']);
				//echo($_SESSION['AdminComponents'].'='.count($AdminComponents.'<br/>'));
				for ($w=0; $w<=count($AdminComponents); $w++)
				{
				$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$resultC = $db->query("SELECT RecordID FROM RED_Components WHERE RecordID='".$AdminComponents[$w]."' AND UniqueName='component_template'");
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
					echo '<h7 id="cp"> '.$row['Title'].'</h7><br/><input type="submit" name="Edit" class="cp" id="cp_article" value="Edit Article"/>';
					echo '</form>';
				}elseif(($resultC->num_rows==0));
				else{
					//echo 'ADMINISTRATOR AUTHORIZED TO UPDATE';
					echo '<script type="text/javascript">'. "\n";
					echo '<!--' ."\n";
					echo 'function edit_content_'.$Alias.'_'.$RecordID.' (content_'.$Alias.'_'.$RecordID.')'. "\n".'{' . "\n"; 
					//echo 'alert (dataString);'. "\n";
					//echo 'return false;'. "\n";
					echo '$.ajax({ '. "\n";
					echo'type: "POST", '. "\n";
					echo 'url: "/admin/bin/edit_component_template.php", '. "\n";
					echo 'cache: false,'. "\n"; 
					//echo 'data: dataString, '. "\n";
					echo 'data: $("#content_'.$Alias.'_'.$RecordID.'").serialize(), '. "\n";
					echo 'success: function(data) { '. "\n";
					//echo 'alert (data);'. "\n";
					echo 'if (data)'. "\n"; 
					echo '{'. "\n";
					//echo '$(\'#edit_content_grid\').html("<div id=\'message_'.$Alias.'_'.$RecordID.'\'></div>");'. "\n";
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
					echo '<h7 id="cp"> '.$row['Title'].'</h7><br/><input type="submit" name="Edit" class="cp" id="cp_component_template" value="Edit News"/>';
					echo '<input type="hidden" name="RecordID" id="RecordID" value="'.$RecordID.'" />';
					echo '<input type="hidden" name="VarPosition" id="VarPosition" value="'.$VarPosition.'" />';
					echo '<input type="hidden" name="Article" id="Article" value="'.article.'" />';
					echo '</form>';
				
				//END "ADMIN AUTHORIZED TO UPDATE".
				break;
				}
				
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