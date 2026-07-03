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
* THIS CLASS CONSTRUCT THE QUERY DEPENDING ON THE URL,
* COUNTING THE NUMBER OF (/) IN IT.
* THERE ARE 3 MAIN VARIABLES SET WITHIN THIS CLASS (query, VarPosition, VarFeatured) WHICH ARE THEN
* USE IN THE NEXT CLASS (PAGE_LAYOUT).
* OTHER VARIABLES ARE PASS FROM CONFIG.PHP. ( $countpage, $section, $category, $articleselected, $link )

* $countpage - Number of deep folders from the url (/).  This is to determine the actual page to get the content on the specific page (url).
* $section - Main sections: i.e: Services. Portfolio. About. Contact.
* $category - SubCategory: i.e: WebDesign. Technology. Content. Marketing. WebDesign. Multimedia. Print-Identity. Other-Media. About. Contact.
* $articleselected - article selected (alias).  This is set only if last part of the url do NOT has a backslash. (this-is-a-section/)  vs (/this-is-an-article)
Multi-Columns2)

* $this->query
* $this->VarPosition
* $this->VarFeatured

* refer to config.php.
**/
#[\AllowDynamicProperties]
class Build_Page
{
	public function get_page_query()
	{
		$tquery = new Build_Query();
		$rquery=$tquery->get_query();
		
		$this->articlequery=$rquery[0];
		$this->VarPosition=$rquery[1];
		$this->VarFeatures=$rquery[2];	
		//$this->metaquery=$rquery[3];
		//$this->Table=$rquery[4];
		
//		echo $this->articlequery.'<br/>';
//		echo $this->VarFeatures.'<br/>';
//		echo $this->VarPosition;
		
		$comp = new page_layout();
		$comp->layout($this->articlequery, $this->VarFeatures, $this->VarPosition);

	
	}
	
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////////////////////////////////////////
	
	public function cp_get_page_query()
	{
		$tquery = new Build_Query();
		$rquery=$tquery->get_query();
		
		$this->articlequery=$rquery[0];
		$this->VarPosition=$rquery[1];
		$this->VarFeatures=$rquery[2];	
		//$this->metaquery=$rquery[3];
		$this->Table=$rquery[4];
				
		
	
		$comp = new page_layout();
		$comp->cp_layout($this->articlequery, $this->VarFeatures, $this->VarPosition, $this->Table);

	
	}
	

}
?>