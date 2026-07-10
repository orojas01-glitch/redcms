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
* THIS CLASS DEFINE THE PAGE LAYOUT
**/
require_once __DIR__ . '/../includes/public_render_helpers.php';

#[\AllowDynamicProperties]
class limit
{
	
	public function get_limit()
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
			$row = red_public_area_row($db->connection, $this->Table, ['QueryLimit']);
			$this->limit = $row['QueryLimit'] ?? null;
			$db->close();
			return $this->limit;
			
			break;
			
			default:
			//echo 'article. select limit from article.  Default 4.';
			
				$this->limit='100';
				return $this->limit;
			
			break;
		}
		
	}
}
?>
