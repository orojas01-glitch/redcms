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
		$this->metaquery=$rquery[3];
		$this->Table=$rquery[4];
		$this->otherquery=$rquery[5];
		
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
              
			$result = $db->query("SELECT Description, Tags FROM RED_".$this->Table." WHERE Active='Y' AND Language='" . language . "' ".$this->metaquery."");
			//echo ($result->num_rows);
			$result_counter = $result->num_rows;
			
			while($row = mysqli_fetch_assoc($result))
			{
				$Description=$row['Description'];
				$Tags=$row['Tags'];
				echo '<meta name="description" content="'.$Description.'">' . "\n";
    			echo '<meta name="keywords" content="'.$Tags.'">' . "\n";
				echo '<meta property="og:description" content="'.$Description.'">';
				
				//$limit='1';
				//echo 'layout='. $layout . '<br/>';
				//echo 'limit='. $this->limit . '<br/>'; 		
				$result_counter = ($result_counter - 1);
			}
			//echo 'end'. $result_counter;
			if ($result_counter == 0);
		
			break;
			
			default:
			//echo 'article. select metatags from article.';
            $table = !empty($this->Table) ? $this->Table : 'Articles';    
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$result = $db->query("SELECT ShortDesc, Tags FROM RED_".$table." WHERE Active='Y' AND Language='" . language . "' ".$this->otherquery."");
			//echo ($result->num_rows);
			$result_counter = $result->num_rows;
			
			while($row = mysqli_fetch_assoc($result))
			{
				$Description=strip_tags($row['ShortDesc']);
				$Description=preg_replace ( "'<[^>]+>'U", "", $Description);
				$Tags=$row['Tags'];
				echo '<meta name="description" content="'.$Description.'">' . "\n";
    			echo '<meta name="keywords" content="'.$Tags.'">' . "\n";
				echo '<meta property="og:description" content="'.$Description.'">';
				//echo 'layout='. $layout . '<br/>'; 		
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