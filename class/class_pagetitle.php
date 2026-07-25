<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 2.0 - (2014/02/25)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.com/
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
class Page_Title
{
	
	public function Title()
	{
				
		$tquery = new Build_Query();
		$rquery=$tquery->get_query();
		
		//$this->articlequery=$rquery[0];
		//$this->VarPosition=$rquery[1];
		//$this->VarFeatured=$rquery[2];
		$this->Table=$rquery[4];
		
		//echo $this->Table;
		
		if ($this->Table!='')
		{
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$advanced = red_public_advanced_items($db->connection, ['Website_Title', 'Website_Slogan']);
			$Website_Title = red_public_plain_text($advanced['Website_Title'] ?? '');
			$Website_Slogan = red_public_plain_text($advanced['Website_Slogan'] ?? '');
			
		switch (article)
		{
			case '':
			
			
			//echo 'no article. select metatags from other table:'.$this->Table;
			$info = red_public_area_row($db->connection, $this->Table, [$this->Table, 'Title']);
			if ($info) {
				if (isset($info[$this->Table]) && strtolower($info[$this->Table]) === 'home') {
                    echo red_public_html($Website_Title . ' | ' . $Website_Slogan);
                } else {
                    $Title = red_public_plain_text($info['Title']);
                    echo red_public_html($Website_Title . ' | ' . ucwords($Title));
                }
			} else {
				echo red_public_html(
					$Website_Title !== '' ? $Website_Title . ' | Page not found' : 'Page not found'
				);
			}
		
			break;
			
			default:
			//echo 'article. select metatags from article.';
			$info = red_public_article_route_row($db->connection, ['Title']);
			if ($info) {
				$Title = preg_replace('/\-/',' ',$info['Title']);
				echo red_public_html($Website_Title .' | '.ucwords($Title));
			} else {
				echo red_public_html(
					$Website_Title !== '' ? $Website_Title . ' | Page not found' : 'Page not found'
				);
			}
			
			break;
		}
		$db->close();
		}
		else
		{
			echo 'Page not found';
		}
		
	
	}
	
}
?>
