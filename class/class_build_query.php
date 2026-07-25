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
* THIS CLASS CONSTRUCT THE ROUTE STATE DEPENDING ON THE URL,
* COUNTING THE NUMBER OF (/) IN IT.
* THERE ARE 3 MAIN VARIABLES SET WITHIN THIS CLASS (query, VarPosition, VarFeatured) WHICH ARE THEN
* USE IN THE NEXT CLASS (PAGE_LAYOUT).
* OTHER VARIABLES ARE PASS FROM CONFIG.PHP. ( countpage, section, category, article, $link )

* countpage - Number of deep folders from the url (/). This is to determine the actual page to get the content on the specific page (url).
* section - Main sections: i.e: Services. Portfolio. About. Contact.
* category - SubCategory: i.e: WebDesign. Technology. Content. Marketing. WebDesign. Multimedia. Print-Identity. Other-Media. About. Contact.
* article - article selected (alias). This is set only if last part of the url do NOT has a backslash. (this-is-a-section/) vs (/this-is-an-article)
Multi-Columns2)

* $this->articlequery
* $this->VarPosition
* $this->VarFeatures

* refer to config.php.
**/
#[\AllowDynamicProperties]
class Build_Query
{
    public $articlequery = '';
    public $VarPosition = '';
    public $VarFeatures = '';
    public $VarFeatured = '';
    public $metaquery = '';
    public $Table = '';
    public $otherquery = '';

    private function route_value($name, $default = '')
    {
        return defined($name) ? (string) constant($name) : $default;
    }

    private function route_state($countpage, $article)
    {
        $countpage = (int) $countpage;
        $hasArticle = (string) $article !== '';

        if ($hasArticle || $countpage === 6) {
            return [
                'articlequery' => '',
                'VarPosition' => 'PagePosition',
                'VarFeatures' => 'ArticleFeatures',
                'metaquery' => '',
                'Table' => 'Articles',
                'otherquery' => '',
            ];
        }

        switch ($countpage) {
            case 2:
                return [
                    'articlequery' => '',
                    'VarPosition' => 'HomePosition',
                    'VarFeatures' => 'HomeFeatures',
                    'metaquery' => '',
                    'Table' => 'Sections',
                    'otherquery' => '',
                ];

            case 3:
                return [
                    'articlequery' => '',
                    'VarPosition' => 'SectionPosition',
                    'VarFeatures' => 'SectionFeatures',
                    'metaquery' => '',
                    'Table' => 'Sections',
                    'otherquery' => '',
                ];

            case 4:
                return [
                    'articlequery' => '',
                    'VarPosition' => 'CategoryPosition',
                    'VarFeatures' => 'CategoryFeatures',
                    'metaquery' => '',
                    'Table' => 'Categories',
                    'otherquery' => '',
                ];

            case 5:
                return [
                    'articlequery' => '',
                    'VarPosition' => 'SubCategoryPosition',
                    'VarFeatures' => 'SubCategoryFeatures',
                    'metaquery' => '',
                    'Table' => 'SubCategories',
                    'otherquery' => '',
                ];
        }

        return [
            'articlequery' => '',
            'VarPosition' => '',
            'VarFeatures' => '',
            'metaquery' => '',
            'Table' => '',
            'otherquery' => '',
        ];
    }

    private function apply_state(array $state)
    {
        $this->articlequery = $state['articlequery'];
        $this->VarPosition = $state['VarPosition'];
        $this->VarFeatures = $state['VarFeatures'];
        $this->VarFeatured = $state['VarFeatures'];
        $this->metaquery = $state['metaquery'];
        $this->Table = $state['Table'];
        $this->otherquery = $state['otherquery'];
    }

    public function get_query()
    {
        $state = $this->route_state(
            $this->route_value('countpage', 0),
            $this->route_value('article')
        );
        $this->apply_state($state);

        return [
            $this->articlequery,
            $this->VarPosition,
            $this->VarFeatures,
            $this->metaquery,
            $this->Table,
            $this->otherquery,
        ];
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////CONTROL PANEL//////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function cp_get_query($countpage, $section, $category, $subcategory, $article)
    {
        $state = $this->route_state($countpage, $article);
        $this->apply_state($state);

        return [
            $this->articlequery,
            $this->VarPosition,
            $this->VarFeatured,
            $this->metaquery,
            $this->Table,
        ];
    }
}
?>
