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
* THIS CLASS DEFINE THE PAGE LAYOUT
**/
require_once __DIR__ . '/../includes/public_render_helpers.php';

#[\AllowDynamicProperties]
class layout
{
	
	public function get_layout()
	{
				
		$tquery = new Build_Query();
		$rquery=$tquery->get_query();
		
		//$this->articlequery=$rquery[0];
		//$this->VarPosition=$rquery[1];
		//$this->VarFeatured=$rquery[2];
		$this->Table=$rquery[4];
		
		switch (article)
		{
			case '':
			//echo 'section or category. select layout from section or category table.';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$row = red_public_area_row($db->connection, $this->Table, ['Layout']);
			$this->layout = $row['Layout'] ?? null;
			$db->close();
			return $this->layout;
		
			break;
			
			default:
			//echo 'article. select layout from article.';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$row = red_public_article_route_row($db->connection, ['Layout']);
			$this->layout = $row['Layout'] ?? null;
			$db->close();
			return $this->layout;
			
			break;
		}
	}
}
?>
