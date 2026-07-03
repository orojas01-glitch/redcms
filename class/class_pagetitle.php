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
		$this->metaquery=$rquery[3];
		$this->Table=$rquery[4];
		$this->otherquery=$rquery[5];
		
		//echo $this->Table;
		
		if ($this->Table!='')
		{
		
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$result = $db->query("SELECT * FROM RED_Advanced WHERE Language='".language."' AND Item IN('Website_Title','Website_Slogan')");
			//echo ($result->num_rows);
			$result_counter = $result->num_rows;
			
			while($row = mysqli_fetch_assoc($result))
       		{
				if ($row['Item']==='Website_Title')
				$Website_Title = preg_replace('/<[^>]*>/',' ',$row['Content']);
				elseif ($row['Item']==='Website_Slogan')
				$Website_Slogan = preg_replace('/<[^>]*>/',' ',$row['Content']);	
			}
			
		switch (article)
		{
			case '':
			
			
			//echo 'no article. select metatags from other table:'.$this->Table;
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			//echo "SELECT * FROM RED_".$this->Table." WHERE Active='Y' AND Language='" . language . "' ".$this->metaquery."";
			$result = $db->query("SELECT * FROM RED_".$this->Table." WHERE Active='Y' AND Language='" . language . "' ".$this->metaquery."");
			//echo ($result->num_rows);
			$result_counter = $result->num_rows;
			
			if($result->num_rows > 0) 
			{
				$info = mysqli_fetch_assoc($result); 	
				if (isset($info[$this->Table]) && strtolower($info[$this->Table]) === 'home') {
                    echo $Website_Title . ' | ' . $Website_Slogan;
                } else {
                    $Title = preg_replace('/<[^>]*>/', ' ', $info['Title']);
                    echo $Website_Title . ' | ' . ucwords($Title);
                }
				$result_counter = ($result_counter - 1);
			}
			//echo 'end'. $result_counter;
			if ($result_counter == 0);
		
			break;
			
			default:
			//echo 'article. select metatags from article.';
			$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
			$result = $db->query("SELECT Title FROM RED_".$this->Table." WHERE Active='Y' AND Language='" . language . "' ".$this->otherquery."");
			//echo ($result->num_rows);
			$result_counter = $result->num_rows;
			
			if($result->num_rows > 0) 
			{
				$info = mysqli_fetch_assoc($result); 
				
				$Title = preg_replace('/\-/',' ',$info['Title']);
				echo $Website_Title .' | '.ucwords($Title);
				$result_counter = ($result_counter - 1);
			}
			//echo 'end'. $result_counter;
			if ($result_counter == 0);
			
			break;
		}
		}
		
	
	}
	
}
?>