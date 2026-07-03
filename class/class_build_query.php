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
class Build_Query
{
    public $articlequery;  // Declare the property
    public $VarPosition;
    public $VarFeatures;
    public $metaquery;
    public $Table;
    public $otherquery;
    
	public function get_query()
	{
		$db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		if (!defined('article')) {
            define ('article', mysqli_real_escape_string($db->connection,article));
        }
		if (!defined('section')) {
            define ('section', mysqli_real_escape_string($db->connection,section));
        }
		if (!defined('category')) {
            define ('category', mysqli_real_escape_string($db->connection,category));
        }
        if (!defined('subcategory')) {
		define ('subcategory', mysqli_real_escape_string($db->connection,subcategory));
        }
        
		switch (countpage)
		{
			case 2:
			
			switch (article)
			{
				case '':
				//echo 'Home';
				$this->articlequery=("AND (Sections='Home' OR HomeFeature='Y')");
				$this->metaquery=("AND (Sections='Home')");
				
				$this->VarPosition='HomePosition';
				$this->VarFeatures='HomeFeatures';
				$this->Table='Sections';
				
				break;
				default:
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='Home' AND Alias='" . article ."') OR (Article LIKE '%" . article ."%'))");
				$this->metaquery=("");
				
				$this->VarPosition='PagePosition';
				$this->VarFeatures='ArticleFeatures';
				$this->Table='Articles';
				
				$this->otherquery=("AND (Sections='Home' AND Alias='" . article ."')");
				break;
			}
			

			break;
			
			case 3:
			switch (article)
			{
				case '':
				//echo 'section';
				$this->articlequery=("AND (Sections='" . section . "')");
				$this->metaquery=("AND (Sections='" . section . "')");

				$this->VarPosition='SectionPosition';
				$this->VarFeatures='SectionFeatures';
				$this->Table='Sections';
				
				break;
				default:
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='" . section . "' AND Alias='" . article ."') OR (Sections='" . section . "' AND Article LIKE '%" . article ."%'))");
				$this->metaquery=("");
				
				$this->VarPosition='PagePosition';
				$this->VarFeatures='ArticleFeatures';
				$this->Table='Articles';
				
				$this->otherquery=("AND (Sections='" . section . "' AND Alias='" . article ."')");
				
				break;
			}
			break;
			
			case 4:		
			switch (article)
			{
				case '':
				//echo 'Category';
				$this->articlequery=("AND (Sections='" . section . "' AND Categories='" . category . "')");
				$this->metaquery=("AND (Categories='" . category . "')");

				$this->VarPosition='CategoryPosition';
				$this->VarFeatures='CategoryFeatures';
				$this->Table='Categories';
				
				break;
				default:
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='" . section . "' AND Categories='" . category . "' AND Alias='" . article ."') OR (Sections='" . section . "' AND Categories='" . category . "' AND Article LIKE '%" . article ."%'))");
				$this->metaquery=("");

				$this->VarPosition='PagePosition';
				$this->VarFeatures='ArticleFeatures';
				$this->Table='Articles';
				
				$this->otherquery=("AND (Sections='" . section . "' AND Categories='" . category . "' AND Alias='" . article ."')");
				
				break;
			}
			break;
			
			case 5:
			switch (article)
			{
				case '':
				//echo 'sub-category'; 
				$this->articlequery=("AND (Sections='" . section . "' AND Categories='" . category . "' AND SubCategories='" . subcategory ."')");
				$this->metaquery=("AND (SubCategories='" . subcategory ."')");

				$this->VarPosition='SubCategoryPosition';
				$this->VarFeatures='SubCategoryFeatures';
				$this->Table='SubCategories';
				
				break;
				default:
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='" . section . "' AND Categories='" . category . "' AND SubCategories='" . subcategory ."' AND Alias='" . article ."') OR (Sections='" . section . "' AND Categories='" . category . "' AND SubCategories='" . subcategory ."' AND Article LIKE '%" . article ."%'))");
				$this->metaquery=("");

				$this->VarPosition='PagePosition';
				$this->VarFeatures='ArticleFeatures';
				$this->Table='Articles';
				
				$this->otherquery=("AND (Sections='" . section . "' AND Categories='" . category . "' AND SubCategories='" . subcategory ."' AND Alias='" . article ."')");
				
				break;
			}
			
			break;
			
			case 6:
			
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='" . section . "' AND Categories='" . category . "' AND SubCategories='" . subcategory ."' AND Alias='" . article ."') OR (Sections='" . section . "' AND Categories='" . category . "' AND SubCategories='" . subcategory ."' AND Article LIKE '%" . article ."%'))");
				$this->metaquery=("");

				$this->VarPosition='PagePosition';
				$this->VarFeatures='ArticleFeatures';
				$this->Table='Articles';
				
				$this->otherquery=("AND (Sections='" . section . "' AND Categories='" . category . "' AND SubCategories='" . subcategory ."' AND Alias='" . article ."')");
				
			break;
		}
		
/*	echo 'ArticleQuery='.$this->articlequery.'<br/>';
	echo 'MetaQuery='.$this->metaquery.'<br/>';
	echo 'VarFeatured='.$this->VarFeatures.'<br/>';
	echo 'VarPosition='.$this->VarPosition.'<br/>';
	echo 'Table='.$this->Table.'<br/>';*/
	
	return array($this->articlequery,$this->VarPosition,$this->VarFeatures,$this->metaquery,$this->Table,$this->otherquery);
	
	
	
	}
	
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	
	public function cp_get_query($countpage, $section, $category, $subcategory, $article)
	{
		switch ($countpage)
		{
			case 2:
			
			switch ($article)
			{
				case '':
				//echo 'Home';
				$this->articlequery=("AND (Sections='Home' OR HomeFeature='Y')");
				$this->metaquery=("AND (Sections='Home')");
				
				$this->VarPosition='HomePosition';
				$this->VarFeatures='HomeFeatures';
				$this->Table='Sections';
				
				break;
				default:
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='Home' AND Alias='" . $article ."') OR (Article='" . $article ."'))");
				$this->metaquery=("");
				
				$this->VarPosition='PagePosition';
				$this->VarFeatured='ArticleFeatures';
				$this->Table='Articles';
				break;
			}
			

			break;
			
			case 3:
			switch ($article)
			{
				case '':
				//echo 'section';
				$this->articlequery=("AND (Sections='" . $section . "')");
				$this->metaquery=("AND (Sections='" . $section . "')");

				$this->VarPosition='SectionPosition';
				$this->VarFeatured='SectionFeatures';
				$this->Table='Sections';
				
				break;
				default:
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='" . $section . "' AND Alias='" . $article ."') OR (Sections='" . $section . "' AND Article='" . $article ."'))");
				$this->metaquery=("");
				
				$this->VarPosition='PagePosition';
				$this->VarFeatured='ArticleFeatures';
				$this->Table='Articles';
				
				break;
			}
			break;
			
			case 4:		
			switch ($article)
			{
				case '':
				//echo 'Category';
				$this->articlequery=("AND (Sections='" . $section . "' AND Categories='" . $category . "')");
				$this->metaquery=("AND (Categories='" . $category . "')");

				$this->VarPosition='CategoryPosition';
				$this->VarFeatured='CategoryFeatures';
				$this->Table='Categories';
				
				break;
				default:
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='" . $section . "' AND Categories='" . $category . "' AND Alias='" . $article ."') OR (Sections='" . $section . "' AND Categories='" . $category . "' AND Article='" . $article ."'))");
				$this->metaquery=("");

				$this->VarPosition='PagePosition';
				$this->VarFeatured='ArticleFeatures';
				$this->Table='Articles';
				
				break;
			}
			break;
			
			case 5:
			switch ($article)
			{
				case '':
				//echo 'sub-category'; 
				$this->articlequery=("AND (Sections='" . $section . "' AND Categories='" . $category . "' AND SubCategories='" . $subcategory ."')");
				$this->metaquery=("AND (SubCategories='" . $subcategory ."')");

				$this->VarPosition='SubCategoryPosition';
				$this->VarFeatured='SubCategoryFeatures';
				$this->Table='SubCategories';
				
				break;
				default:
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='" . $section . "' AND Categories='" . $category . "' AND SubCategories='" . $subcategory ."' AND Alias='" . $article ."') OR (Sections='" . $section . "' AND Categories='" . $category . "' AND SubCategories='" . $subcategory ."' AND Article='" . $article ."'))");
				$this->metaquery=("");

				$this->VarPosition='PagePosition';
				$this->VarFeatured='ArticleFeatures';
				$this->Table='Articles';
				
				break;
			}
			
			break;
			
			case 6:
			
				//echo 'Article Selected';
				$this->articlequery=("AND ((Sections='" . $section . "' AND Categories='" . $category . "' AND SubCategories='" . $subcategory ."' AND Alias='" . $article ."') OR (Sections='" . $section . "' AND Categories='" . $category . "' AND SubCategories='" . $subcategory ."' AND Article='" . $article ."'))");
				$this->metaquery=("");

				$this->VarPosition='PagePosition';
				$this->VarFeatured='ArticleFeatures';
				$this->Table='Articles';
				
			break;
		}
		
/*	echo 'ArticleQuery='.$this->articlequery.'<br/>';
	echo 'MetaQuery='.$this->metaquery.'<br/>';
	echo 'VarFeatured='.$this->VarFeatured.'<br/>';
	echo 'VarPosition='.$this->VarPosition.'<br/>';
	echo 'Table='.$this->Table.'<br/>';*/
	
	return array($this->articlequery,$this->VarPosition,$this->VarFeatured,$this->metaquery,$this->Table);
	
	
	
	}
	

}
?>