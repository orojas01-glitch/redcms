<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 2.0 - (2014/02/25)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.com/
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/

/**
* THIS CLASS CONSTRUCT THE QUERY DEPENDING ON THE URL,
* COUNTING THE NUMBER OF (/) IN IT.
* THERE ARE 3 MAIN VARIABLES SET WITHIN THIS CLASS (query, VarPosition, VarFeatured) WHICH ARE THEN
* USE IN THE NEXT CLASS (PAGE_LAYOUT).
* OTHER VARIABLES ARE PASS FROM CONFIG.PHP. ( countpage, section, category, article, $link )

* countpage - Number of deep folders from the url (/).  This is to determine the actual page to get the content on the specific page (url).
* section - Main sections: i.e: Services. Portfolio. About. Contact.
* category - SubCategory: i.e: WebDesign. Technology. Content. Marketing. WebDesign. Multimedia. Print-Identity. Other-Media. About. Contact.
* article - article selected (alias).  This is set only if last part of the url do NOT has a backslash. (this-is-a-section/)  vs (/this-is-an-article)
Multi-Columns2)

* $this->articlequery
* $this->VarPosition
* $this->VarFeatures

* refer to config.php.
**/
#[\AllowDynamicProperties]
class Build_Breadcrumb
{
	private function get_friendlyname($alias,$table)
	{
		
		switch ($table)
		{
			case 'Articles':
				$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$result = $db->query("SELECT Title FROM RED_".$table." WHERE Active='Y' AND Language='" . language . "' AND Alias='".$alias."'");
				//echo ($result->num_rows);
				$result_counter = $result->num_rows;
				
				while($row = mysqli_fetch_assoc($result))
				{
					$this->title=$row['Title'];
					return $this->title;	
					$result_counter = ($result_counter - 1);
				}
				//echo 'end'. $result_counter;
				if ($result_counter == 0);
				
				$db->close();
			break;
			default:
				$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
				$result = $db->query("SELECT Title FROM RED_".$table." WHERE Active='Y' AND Language='" . language . "' AND ".$table."='".$alias."'");
				//echo ($result->num_rows);
				$result_counter = $result->num_rows;
				
				while($row = mysqli_fetch_assoc($result))
				{
					$this->title=$row['Title'];
					return $this->title;	
					$result_counter = ($result_counter - 1);
				}
				//echo 'end'. $result_counter;
				if ($result_counter == 0);
				
				$db->close();
			break;
		}
		
	}
	
	public function get_breadcrumb()
	{
		
		if (section!='home')
		{
			echo '<div class="wrapper">';
			echo '<div class="breadcrumb-1">';
			echo '<ul>';
			
			switch (countpage)
			{
				case 2:
				switch (article)
				{
					case '':
					//echo 'Home';
					echo '<li><span class="bullet">&raquo;&nbsp;</span>'.self::get_friendlyname('Home','Sections').'</li>';
					break;
					
					default:
					//echo 'Article Selected';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/" class="link-breadcrumb-1">'.self::get_friendlyname('Home','Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span>'.self::get_friendlyname(article,'Articles').'</li>';
					break;
				}
				break;
				
				case 3:
				switch (article)
				{
					case '':
					//echo 'section';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/" class="link-breadcrumb-1">'.self::get_friendlyname('Home','Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span>'.self::get_friendlyname(section,'Sections').'</li>';
					break;
					
					default:
					//echo 'Article Selected';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/" class="link-breadcrumb-1">'.self::get_friendlyname('Home','Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/" class="link-breadcrumb-1">'.self::get_friendlyname(section,'Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span>'.self::get_friendlyname(article,'Articles').'</li>';
					
					break;
				}
				break;
				
				case 4:		
				switch (article)
				{
					case '':
					//echo 'Category';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/" class="link-breadcrumb-1">'.self::get_friendlyname('Home','Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/" class="link-breadcrumb-1">'.self::get_friendlyname(section,'Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span>'.self::get_friendlyname(category,'Categories').'</li>';
					break;
					default:
					//echo 'Article Selected';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/" class="link-breadcrumb-1">'.self::get_friendlyname('Home','Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/" class="link-breadcrumb-1">'.self::get_friendlyname(section,'Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/'.category.'/" class="link-breadcrumb-1">'.self::get_friendlyname(category,'Categories').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span>'.self::get_friendlyname(article,'Articles').'</li>';
					break;
				}
				break;
				
				case 5:
				switch (article)
				{
					case '':
					//echo 'sub-category'; 
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/" class="link-breadcrumb-1">'.self::get_friendlyname('Home','Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/" class="link-breadcrumb-1">'.self::get_friendlyname(section,'Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/'.category.'/" class="link-breadcrumb-1">'.self::get_friendlyname(category,'Categories').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span>'.self::get_friendlyname(subcategory,'SubCategories').'</li>';
					
		
					break;
					default:
					//echo 'Article Selected';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/" class="link-breadcrumb-1">'.self::get_friendlyname('Home','Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/" class="link-breadcrumb-1">'.self::get_friendlyname(section,'Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/'.category.'/" class="link-breadcrumb-1">'.self::get_friendlyname(category,'Categories').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><!--<a href="/'.section.'/'.category.'/'.subcategory.'/" class="link-breadcrumb-1">'.self::get_friendlyname(subcategory,'SubCategories').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span>'.self::get_friendlyname(article,'Articles').'</li>';
					break;
				}
				break;
				
				case 6:
					//echo 'Article Selected';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/" class="link-breadcrumb-1">'.self::get_friendlyname('Home','Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/" class="link-breadcrumb-1">'.self::get_friendlyname(section,'Sections').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/'.category.'/" class="link-breadcrumb-1">'.self::get_friendlyname(category,'Categories').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span><a href="/'.section.'/'.category.'/'.subcategory.'/" class="link-breadcrumb-1">'.self::get_friendlyname(subcategory,'SubCategories').'</a></li>';
					echo '<li><span class="bullet">&raquo;&nbsp;</span>'.self::get_friendlyname(article,'Articles').'</li>';
				break;
			}
			
			echo '</ul><hr/>';
			echo '</div>';
			echo '</div>';
	
		}else{
			echo '<div class="clear-1"></div>';
		}
		
	}
	
}
?>