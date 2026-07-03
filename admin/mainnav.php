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
//echo 'session'.$_SESSION['alias'];
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();

require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_build_query.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_build_page.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_layout.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_limit.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_page_layout.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_metatags.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_pagetitle.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_main_menu.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_content.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_article.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_other.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_gallery.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_forms.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_build_breadcrumb.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/class/class_feature_slider.php';

if(isset($_SESSION['alias'])){
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_edit_layout.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_edit_section.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_edit_hiddenarticles.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_new_section.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_edit_category.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_new_category.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_edit_subcategory.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_new_subcategory.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_edit_advanced.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_new_advanced.php'; 
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_add_menu.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_add_tools.php';
?>
<a name="atop"></a>
<!-- TinyMCE -->
<script type="text/javascript" src="/admin/assets/js/tiny_mce/tinymce.min.js"></script>
<!-- /TinyMCE -->
<script type="text/javascript">
<!--
var RED_CSRF_TOKEN = <?php echo json_encode(red_csrf_token()); ?>;
if (window.jQuery) {
	$.ajaxSetup({
		beforeSend: function(xhr, settings) {
			var method = (settings.type || settings.method || 'GET').toUpperCase();
			var url = settings.url || '';
			if (method !== 'GET' && url.indexOf('/admin/bin/') === 0) {
				xhr.setRequestHeader('X-CSRF-Token', RED_CSRF_TOKEN);
			}
		}
	});
}
//-->
<!--
$(function(){
$(".cp1_slideDown dx").click(function(){$(this).toggleClass("active").parent(".cp1_slideDown").find("dd").slideToggle()})
})
//-->
<!--
$(function(){
	$('.cp_tabs').each(function(){$(this).find('.tab-content').not($(this).find('ul.nav .selected a').attr('href')).hide();
	$(this).find('ul.nav a').click(function(){
		if($(this).parent().hasClass('selected')){return false}
		else{
			$(this).parent().addClass('selected').siblings().removeClass('selected');$(this).parents('.cp_tabs').height($('ul.nav').outerHeight()+$($(this).attr('href')).outerHeight()).find('.tab-content').hide();$($(this).attr('href')).fadeIn(300);$(this).parents('.cp_tabs').height('auto');return false}
		})
	})
})
//-->
<!--
$(function(){//right width is the black box
	//$("#advanced .trigger").toggle(function(){$(this).parent().animate({right:-500},"medium")},function(){$(this).parent().animate({right:0},"medium")})
	$("#advanced .trigger").toggle(function(){$(this).parent().animate({top:-500},"medium")},function(){$(this).parent().animate({bottom:0},"medium")})
})
//-->
<!--
function showdiv(id){
	/*alert(id);*/
	if (id=='edit_content_grid')
	 $("#msggbox_edit_content").hide();
	else if(id=='editcontent'){
	$("#msggbox_edit_content").hide();
	$("#edit_content_grid").hide();
	$( "#content_first" ).addClass('selected');
	$( "#content_second" ).removeClass('selected');
	$( "#content_third" ).removeClass('selected');
	}
	else if(id=='edit_inactive_article_grid'){
	$("#msggbox_edit_content").hide();
	$("#edit_content_grid").hide();
	$("#msggbox_edit_inactive_article").hide();
	$( "#content_first" ).addClass('selected');
	$( "#content_second" ).removeClass('selected');
	$( "#content_third" ).removeClass('selected');
	}
	else if(id=='edit_section_grid')
	$("#msggbox_edit_section").hide();
	else if(id=='edit_category_grid')
	$("#msggbox_edit_category").hide();
	else if(id=='edit_subcategory_grid')
	$("#msggbox_edit_subcategory").hide();
	else if(id=='add_content_grid')
	$("#msggbox_add_content").hide();
	else if(id=='edit_advanced_grid')
	$("#msggbox_edit_advanced").hide();
	else if(id=='tools_content_grid')
	$("#msggbox_tools_content").hide();
	else if(id=='tools_sections_grid')
	$("#msggbox_tools_sections").hide();
	else if(id=='tools_categories_grid')
	$("#msggbox_tools_categories").hide();
	else if(id=='tools_subcategories_grid')
	$("#msggbox_tools_subcategories").hide();
	
	 $("#" + id).show(); //Show DIV with certain ID 
}
//-->
</script>
<link rel="stylesheet" href="/admin/assets/css/advanced.css">
<link rel="stylesheet" href="/admin/assets/css/cp.css">
<div id="advanced"><span class="trigger"></span>
<!-- USER INFO -->

<div class="wrapper"><div class="toptitleleft"><h6 id="cp"><?php echo $_SESSION['alias']?></h6></div><div class="toptitleright"><h7 id="logout"><a href='/bin/logout.php?logout'><strong>Logout</strong><img src="/admin/images/logout.png" border="0"></a></h7></div></div><hr>

<div class="scrollboxes">

<!-- BOX CONTENT -->
<div class="wrapper" id="cp_content">
<h6 id="cp">Content</h6>
	<!-- BOX PAGE INFO -->
    <div class="wrapper">
        <div class="toptitleleft"><h7 id="layout">Current Page layout</h7></div>
        <div class="toptitleright">
        <?php
        $tlay = new layout();
        $layout=$tlay->get_layout();
            
        $editlayout=new editlayout();
        $editlayout->layout_form(countpage,section,category,subcategory,article,$layout);
        ?>
        </div>
    </div>
    <div class="cp_tabs">
      <ul class="nav">
        <li class="selected" id="content_first"><a href="#editcontent">Edit Content</a></li>
        <li id="content_second"><a href="#addcontent">Add Content</a></li>
        <li id="content_third"><a href="#toolscontent">Tools</a></li>
      </ul>
      <div id="editcontent" class="tab-content">
        <div class="inner">
        <div id="edit_content_grid">
            <!--EDIT CONTENT GRID START-->
            <?php
            $main_menu=new main_menu();
            $main_menu->cp_menu();
            //

            $feature_slider=new feature_slider();
            $feature_slider->cp_slider();
    
            //
			//

           
            
			// GET ALL EDIT BUTTONS FOR THIS PAGE.
			// 1. /class/class_build_query.php -- cp_get_query()
			// 2. /class/class_page_layout.php -- cp_layout()
			// 2a. /class/limit.php -- get_limit()
			// 2b. /class/class_layout.php -- get_layout()
			// 2c. /class/class_content.php -- cp_article()
			
            $page=new Build_Page();
            $page->cp_get_page_query();
            ?>
            </div>
            <span id="msggbox_edit_content" style="display:none;"></span>
        </div>
      </div>
      <div id="addcontent" class="tab-content">
        <div class="inner">
        <div id="add_content_grid">
            <!--ADD CONTENT GRID START-->
            <?php
            // GET VARPOSITION FOR THE ADD SCRIPT.
			// /class/class_build_query.php -- get_query()
			
            $tquery = new Build_Query();
            $rquery=$tquery->get_query();
            $VarPosition=$rquery[1];
			// ALL ADD CONTENT.
			// /admin/class/class_add_menu.php -- add_menu_grid()
            $add_menu=new add_menu();
            $add_menu->add_menu_grid(countpage,section,category,subcategory,article,$VarPosition,language,$layout);
            // 
            ?>
            </div>
            <span id="msggbox_add_content" style="display:none"></span>
        </div>
      </div>
      
      <div id="toolscontent" class="tab-content">
        <div class="inner">
        <div id="tools_content_grid">
        	<!-- ADD TOOLS GRID START-->
            <?php
            // /admin/class/class_add_tools.php -- add_tools_grid()
            $add_menu=new add_tools();
            $add_menu->add_tools_grid(countpage,section,category,subcategory,article,$VarPosition,language,$layout,'Content','Content');
            // 
            //
            ?>
            </div>
            <span id="msggbox_tools_content" style="display:none"></span>
        </div>
      </div>
    </div>
</div>
<!-- EXPAND inactive_ ARTICLES -->
<div class="wrapper" id="cp_inactive">
    <dl class="cp1_slideDown">
        <dx><h6 id="cp">Inactive Articles</h6></dx>
        <dd>
        	<div class="cp_tabs">
              <ul class="nav">
                <li class="selected"><a href="#edit_inactive_article">Edit Inactive Article</a></li>
              </ul>
              <div id="edit_inactive_article" class="tab-content">
                <div class="inner">
                <div id="edit_inactive_article_grid">
                	<!--EDIT inactive_ ARTICLES GRID START-->
					<?php
                    $edit_inactive_article=new edit_inactive_article();
                    $edit_inactive_article->inactive_article_form($layout);
                    //
                    ?>
                    </div>
                    <span id="msggbox_edit_inactive_article" style="display:none"></span>
                </div>
              </div>
            </div>
        
        </dd>
    </dl>
</div>


<!-- EXPAND SECTIONS -->
<div class="wrapper" id="cp_sections">
    <dl class="cp1_slideDown">
        <dx><h6 id="cp">Sections</h6></dx>
        <dd>
        
        	<div class="cp_tabs">
              <ul class="nav">
                <li class="selected"><a href="#editsection">Edit Section</a></li>
                <li><a href="#addsection" style="display:inline-block">Add Section</a></li>
                <!--<li><a href="#toolssection" style="display:inline-block">Tools</a></li>-->
              </ul>
              <div id="editsection" class="tab-content">
                <div class="inner">
                	<div id="edit_section_grid">
                	<!--EDIT SECTION GRID START-->
					<?php
                    $editsection=new editsection();
                    $editsection->section_form();
                    //
                    ?>
                    </div>
                    <span id="msggbox_edit_section" style="display:none"></span>
                </div>
              </div>
              <div id="addsection" class="tab-content">
                <div class="inner">
                    <?php
                    $editsection=new newsection();
                    $editsection->section_form(language);
                    //
                    ?>
                </div>
              </div>
              <div id="toolssection" class="tab-content">
                <div class="inner">
                	<div id="tools_sections_grid">
					<?php
                    $add_menu=new add_tools();
            		$add_menu->add_tools_grid(countpage,section,category,subcategory,article,$VarPosition,language,$layout,'Content','Sections');
                    // 
                    ?>
                    </div>
                    <span id="msggbox_tools_sections" style="display:none"></span>
                </div>
              </div>
              
            </div>
        
        </dd>
    </dl>
</div>




<!-- EXPAND CATEGORIES -->
<div class="wrapper" id="cp_categories">
    <dl class="cp1_slideDown">
        <dx><h6 id="cp">Categories</h6></dx>
        <dd>
        
        	<div class="cp_tabs">
              <ul class="nav">
                <li class="selected"><a href="#editcategory">Edit Category</a></li>
                <li><a href="#addcategory">Add Category</a></li>
                <!--<li><a href="#toolscategory" style="display:inline-block">Tools</a></li>-->
              </ul>
              <div id="editcategory" class="tab-content">
                <div class="inner">
                	<!--EDIT SECTION GRID START-->
					<?php
                    echo '<div id="edit_category_grid">';
                    $editcategory=new editcategory();
                    $editcategory->category_form();
                    //
                    echo '</div>';
                    ?>
                    <span id="msggbox_edit_category" style="display:none"></span>
                </div>
              </div>
              <div id="addcategory" class="tab-content">
                <div class="inner">
                    <?php
                    $editcategory=new newcategory();
                    $editcategory->category_form(language);
                    //
                    ?>
                </div>
              </div>
              
               <div id="toolscategory" class="tab-content">
                <div class="inner">
                	<div id="tools_categories_grid">
					<?php
                    // /admin/class/class_filter_articles.php -- add_tools_grid()
                    $add_menu=new add_tools();
            		$add_menu->add_tools_grid(countpage,section,category,subcategory,article,$VarPosition,language,$layout,'Areas','Categories');
                    // 
                    ?>
                    </div>
                    <span id="msggbox_tools_categories" style="display:none"></span>
                </div>
              </div>
            </div>
        
        </dd>
    </dl>
</div>
<!-- EXPAND SUB-CATEGORIES -->
<div class="wrapper" id="cp_subcategories">
    <dl class="cp1_slideDown">
        <dx><h6 id="cp">SubCategories</h6></dx>
        <dd>
        
        	<div class="cp_tabs">
              <ul class="nav">
                <li class="selected"><a href="#editsubcategory">Edit SubCategory</a></li>
                <li><a href="#addsubcategory">Add SubCategory</a></li>
                <!--<li><a href="#toolssubcategory">Tools</a></li>-->
              </ul>
              <div id="editsubcategory" class="tab-content">
                <div class="inner">
                	<!--EDIT SECTION GRID START-->
					<?php
                    echo '<div id="edit_subcategory_grid">';
                    $editcategory=new editsubcategory();
                    $editcategory->subcategory_form();
                    //
                    echo '</div>';
                    ?>
                    <span id="msggbox_edit_subcategory" style="display:none"></span>
                </div>
              </div>
              <div id="addsubcategory" class="tab-content">
                <div class="inner">
                    <?php
                    $editsubcategory=new newsubcategory();
                    $editsubcategory->subcategory_form(language);
                    //
                    ?>
                </div>
              </div>
              <div id="toolssubcategory" class="tab-content">
                <div class="inner">
                	<div id="tools_subcategories_grid">
					<?php
                    // /admin/class/class_filter_articles.php -- add_tools_grid()
                    $add_menu=new add_tools();
            		$add_menu->add_tools_grid(countpage,section,category,subcategory,article,$VarPosition,language,$layout,'Areas','SubCategories');
                    // 
                    ?>
                    </div>
                    <span id="msggbox_tools_subcategories" style="display:none"></span>
                </div>
              </div>
              
            </div>
        
        </dd>
    </dl>
</div>
<!-- EXPAND ADVANCED -->
<div class="wrapper" id="cp_advanced">
    <dl class="cp1_slideDown">
        <dx><h6 id="cp">Advanced</h6></dx>
        <dd>
        
        	<div class="cp_tabs">
              <ul class="nav">
                <li class="selected"><a href="#editadvanced">Edit Advanced</a></li>
                <li><a href="#addadvanced">Language</a></li>
              </ul>
              <div id="editadvanced" class="tab-content">
                <div class="inner">
                	<!--EDIT SECTION GRID START-->
					<?php
                    echo '<div id="edit_advanced_grid">';
                    $editadvanced=new editadvanced();
                    $editadvanced->advanced_form();
                    //
                    echo '</div>';
                    ?>
                    <span id="msggbox_edit_advanced" style="display:none"></span>
                </div>
              </div>
               <div id="addadvanced" class="tab-content">
                <div class="inner">
                    <?php
                    $editadvanced=new newadvanced();
                    $editadvanced->advanced_item(language);
                    
                    ?>
                </div>
              </div>
            </div>
        
        </dd>
    </dl>
</div>
</div>

</div>

<?php
}
?>
