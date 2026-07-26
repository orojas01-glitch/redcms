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
		$this->Table=$rquery[4];

		if ($this->Table === '') {
			echo 'Page not found';
			return;
		}

		$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
		$context = red_public_seo_route_context($db->connection, $this->Table);
		$db->close();

		$title = !empty($context['rich'])
			? (string) ($context['title'] ?? '')
			: (string) ($context['legacyTitle'] ?? 'Page not found');
		echo red_public_html($title);
	}
}
?>
