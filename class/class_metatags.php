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
* THIS CLASS GETS THE PAGE META TAGS,
* OTHER VARIABLES ARE PASS FROM CONFIG.PHP. ( $countpage, $section, $category, $article, $link )

* $section - Main sections: i.e: Services. Portfolio. About. Contact.
* $category - SubCategory: i.e: WebDesign. Technology. Content. Marketing. WebDesign. Multimedia. Print-Identity. Other-Media. About. Contact.
* $article - article selected (alias).  This is set only if last part of the url do NOT has a backslash. (this-is-a-section/)  vs (/this-is-an-article)
Multi-Columns2)

* $this->section
* $this->link
* $this->PLquery

**/
require_once __DIR__ . '/../includes/public_render_helpers.php';

#[\AllowDynamicProperties]
class Page_Metatags
{
	
	public function Metatags()
	{
				
		$tquery = new Build_Query();
		$rquery=$tquery->get_query();
		
		//$this->articlequery=$rquery[0];
		//$this->VarPosition=$rquery[1];
		//$this->VarFeatured=$rquery[2];
		$this->Table=$rquery[4];
		
		//echo $this->query;
        
        // If the table value is empty, do not execute any query
        if (empty($this->Table)) {
            // Optionally, log an error or perform an alternative action here
            return;
        }
		
		switch (article)
		{
			case '':
			
			
			//echo 'no article. select metatags from other table: '.$this->Table;
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$row = red_public_area_row($db->connection, $this->Table, ['Description', 'Tags']);
			if ($row) {
				$Description = $row['Description'];
				$Tags = $row['Tags'];
				echo '<meta name="description" content="' . red_public_html($Description) . '">' . "\n";
				echo '<meta name="keywords" content="' . red_public_html($Tags) . '">' . "\n";
				echo '<meta property="og:description" content="' . red_public_html($Description) . '">';
			}
		
			break;
			
			default:
			//echo 'article. select metatags from article.';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$row = red_public_article_route_row($db->connection, ['ShortDesc', 'Tags']);
			if ($row) {
				$Description = red_public_plain_text($row['ShortDesc']);
				$Tags = $row['Tags'];
				echo '<meta name="description" content="' . red_public_html($Description) . '">' . "\n";
				echo '<meta name="keywords" content="' . red_public_html($Tags) . '">' . "\n";
				echo '<meta property="og:description" content="' . red_public_html($Description) . '">';
			}
			
			break;
		}
		
	$db->close();
	
	}
	
}
?>
