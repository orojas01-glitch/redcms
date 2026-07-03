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
		$this->metaquery=$rquery[3];
		$this->Table=$rquery[4];
		$this->otherquery=$rquery[5];
		
		switch (article)
		{
			case '':
			//echo 'section or category. select layout from section or category table.';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$result = $db->query("SELECT * FROM RED_".$this->Table." WHERE Active='Y' AND Language='".language."' ".$this->metaquery."");
			//echo "SELECT * FROM RED_".$this->Table." WHERE Active='Y' AND Language='".language."' ".$this->metaquery."";
			
			//echo ($result->num_rows);
			$result_counter = $result->num_rows;
			
			while($row = mysqli_fetch_assoc($result))
			{
				$this->layout=$row['Layout'];
				return $this->layout;		
				$result_counter = ($result_counter - 1);
			}
			//echo 'end'. $result_counter;
			if ($result_counter == 0);
		
			break;
			
			default:
			//echo 'article. select layout from article.';
			//echo "SELECT Layout FROM RED_".$this->Table." WHERE Active='Y' AND Language='" . language . "' ".$this->otherquery."";
            $table = !empty($this->Table) ? $this->Table : 'Articles';    
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$result = $db->query("SELECT Layout FROM RED_".$table." WHERE Active='Y' AND Language='" . language . "' ".$this->otherquery."");
			//echo ($result->num_rows);
			$result_counter = $result->num_rows;
			
			while($row = mysqli_fetch_assoc($result))
			{
				$this->layout=$row['Layout'];
				return $this->layout;	
				$result_counter = ($result_counter - 1);
			}
			//echo 'end'. $result_counter;
			if ($result_counter == 0);
			
			break;
		}
		$db->close();
	}
}
?>