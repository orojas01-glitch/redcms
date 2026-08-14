<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 5.1 - (2026/08/14)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.tv/documentation/ 
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/
//echo 'session'.$_SESSION['alias'];
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();

if (!function_exists('red_admin_shell_icon')) {
    function red_admin_shell_icon($name)
    {
        $paths = [
            'account' => '<circle cx="12" cy="8" r="3.5"></circle><path d="M5 20v-1.4A5.6 5.6 0 0 1 10.6 13h2.8a5.6 5.6 0 0 1 5.6 5.6V20"></path>',
            'edit' => '<path d="m4 20 4.2-.9L19 6.3a1.8 1.8 0 0 0 0-2.6l-.7-.7a1.8 1.8 0 0 0-2.6 0L3 15.8z"></path><path d="m14.2 4.5 3.3 3.3M4 20h16"></path>',
            'content' => '<path d="M6 4h9l3 3v13H6z"></path><path d="M15 4v4h4M9 12h6M9 16h6"></path>',
            'add' => '<circle cx="12" cy="12" r="8"></circle><path d="M12 8v8M8 12h8"></path>',
            'tools' => '<path d="m14.5 6.5 3-3 3 3-3 3"></path><path d="m16.5 8.5-9.8 9.8a2.1 2.1 0 0 1-3-3l9.8-9.8M4 4l4 4"></path>',
            'inactive' => '<path d="M4 7h16v13H4zM3 4h18v3H3z"></path><path d="M9 11h6"></path>',
            'sections' => '<path d="M4 5h16v5H4zM4 14h7v6H4zM15 14h5v6h-5z"></path>',
            'categories' => '<path d="M3 7h7l2 2h9v10H3z"></path>',
            'subcategories' => '<path d="M5 5h5l2 2h7v5H5zM8 14h11v5H8z"></path><path d="M5 10v7h3"></path>',
            'advanced' => '<circle cx="12" cy="12" r="3"></circle><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"></path>',
            'chevron' => '<path d="m8 10 4 4 4-4"></path>',
        ];

        $path = $paths[(string) $name] ?? $paths['content'];
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
    }
}

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
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_breadcrumb_helpers.php';

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
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_add_menu.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/admin/class/class_add_tools.php';
?>
<a name="atop"></a>
<!-- TinyMCE -->
<script type="text/javascript" src="/admin/assets/js/tiny_mce/tinymce.min.js"></script>
<script type="text/javascript" src="/admin/assets/js/area-rename-redirect.js"></script>
	<script type="text/javascript" src="/admin/assets/js/area-hierarchy.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/area-hierarchy.js')); ?>"></script>
	<script type="text/javascript" src="/admin/assets/js/layout-preview.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/layout-preview.js')); ?>"></script>
	<script type="text/javascript" src="/admin/assets/js/move-content.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/move-content.js')); ?>"></script>
	<script type="text/javascript" src="/admin/assets/js/feature-slider-editor.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/feature-slider-editor.js')); ?>"></script>
	<script type="text/javascript" src="/admin/assets/js/main-menu-editor.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/main-menu-editor.js')); ?>"></script>
	<script type="text/javascript" src="/admin/assets/js/content-revisions.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/content-revisions.js')); ?>"></script>
	<script type="text/javascript" src="/admin/assets/js/addon-component-editor.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/addon-component-editor.js')); ?>"></script>
	<script type="text/javascript" src="/admin/assets/js/addon-admin-tool-form.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/addon-admin-tool-form.js')); ?>"></script>
	<script type="text/javascript" src="/admin/assets/js/layout-distribution.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/layout-distribution.js')); ?>"></script>
	<script type="text/javascript" src="/admin/assets/js/layout-builder.js?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/js/layout-builder.js')); ?>"></script>
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
    $(".cp1_slideDown .red-admin-disclosure").on("click", function(){
        var $button = $(this);
        var $host = $button.closest("dx");
        var $panel = $button.closest(".cp1_slideDown").children("dd").first();
        var willOpen = !$panel.is(":visible");

        $button.attr("aria-expanded", willOpen ? "true" : "false");
        $host.toggleClass("active", willOpen);
        $panel.stop(true, true).slideToggle(180);
    });
})
//-->
function redAdminOpenCurrentUser(recordId)
{
    var $advancedSection = $("#cp_advanced");
    var $advancedPanel = $advancedSection.find(".cp1_slideDown > dd").first();
    var $advancedToggle = $advancedSection.find(".red-admin-disclosure").first();
    var $identityButton = $(".red-admin-session-identity__edit");

    if (!$advancedPanel.is(":visible")) {
        $advancedPanel.stop(true, true).show();
        $advancedSection.find("dx").first().addClass("active");
        $advancedToggle.attr("aria-expanded", "true");
    }

    $advancedSection.find("ul.nav li").removeClass("selected");
    $advancedSection.find('ul.nav a[href="#editadvanced"]').parent().addClass("selected");
    $advancedSection.find(".tab-content").hide();
    $("#editadvanced").show();

    $identityButton.attr("aria-busy", "true").prop("disabled", true);
    $.ajax({
        type: "POST",
        url: "/admin/bin/edit_admin_users.php",
        data: {view: "user", RecordID: recordId},
        success: function(data) {
            $("#edit_advanced_grid").hide();
            $("#msggbox_edit_advanced").html(data).show();
            var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            window.setTimeout(function() {
                var target = document.getElementById("cp_advanced");
                if (target && target.scrollIntoView) {
                    target.scrollIntoView({behavior: reduceMotion ? "auto" : "smooth", block: "start"});
                }
            }, reduceMotion ? 0 : 120);
        },
        error: function() {
            $("#msggbox_edit_advanced").html('<div class="red-admin-session-error" role="alert">The account editor could not be opened. Please try again.</div>').show();
        },
        complete: function() {
            $identityButton.removeAttr("aria-busy").prop("disabled", false);
        }
    });
    return false;
}
function redAdminOpenLayoutBuilder(layoutId)
{
    var $advancedSection = $("#cp_advanced");
    var $advancedPanel = $advancedSection.find(".cp1_slideDown > dd").first();
    var $advancedToggle = $advancedSection.find(".red-admin-disclosure").first();

    if (!$advancedPanel.is(":visible")) {
        $advancedPanel.stop(true, true).show();
        $advancedSection.find("dx").first().addClass("active");
        $advancedToggle.attr("aria-expanded", "true");
    }

    $advancedSection.find("ul.nav li").removeClass("selected");
    $advancedSection.find('ul.nav a[href="#editadvanced"]').parent().addClass("selected");
    $advancedSection.find(".tab-content").hide();
    $("#editadvanced").show();
    $("#edit_advanced_grid").hide();
    $("#msggbox_edit_advanced")
        .html('<div class="red-layout-builder-loading" role="status">Opening Layout Builder…</div>')
        .show();

    $.ajax({
        type: "POST",
        url: "/admin/bin/edit_layout_builder.php",
        data: {LayoutID: layoutId || ""},
        success: function(data) {
            $("#msggbox_edit_advanced").html(data).show();
            if (window.RedLayoutBuilder) {
                window.RedLayoutBuilder.init(document.getElementById("msggbox_edit_advanced"));
            }
            var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            window.setTimeout(function() {
                var target = document.getElementById("cp_advanced");
                if (target && target.scrollIntoView) {
                    target.scrollIntoView({behavior: reduceMotion ? "auto" : "smooth", block: "start"});
                }
            }, reduceMotion ? 0 : 120);
        },
        error: function() {
            $("#msggbox_edit_advanced")
                .html('<div class="red-admin-session-error" role="alert">The Layout Builder could not be opened. Please try again.</div>')
                .show();
        }
    });
    return false;
}
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
<link rel="stylesheet" href="/admin/assets/css/cp.css?v=<?php echo rawurlencode((string) filemtime(__DIR__ . '/assets/css/cp.css')); ?>">
<div id="advanced"><span class="trigger"></span>
<!-- USER INFO -->
<?php
$redAdminSessionAlias = trim((string) ($_SESSION['alias'] ?? ''));
$redAdminSessionUsername = trim((string) ($_SESSION['AdminUsername'] ?? ''));
$redAdminSessionRole = trim((string) ($_SESSION['AdminType'] ?? ''));
$redAdminSessionRecordId = (int) ($_SESSION['AdminRecordID'] ?? 0);
$redAdminSessionName = $redAdminSessionAlias !== ''
    ? $redAdminSessionAlias
    : ($redAdminSessionUsername !== '' ? $redAdminSessionUsername : 'Administrator');
$redAdminSessionInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($redAdminSessionName, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($redAdminSessionName, 0, 1));
$redAdminSessionRoleLabel = $redAdminSessionRole !== ''
    ? ucwords(str_replace(['_', '-'], ' ', $redAdminSessionRole))
    : 'Administrator';
?>
<div class="wrapper red-admin-sessionbar">
    <div class="toptitleleft red-admin-sessionbar__identity">
        <div class="red-admin-session-identity">
            <span class="red-admin-session-identity__avatar" aria-hidden="true"><?php echo htmlspecialchars($redAdminSessionInitial, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="red-admin-session-identity__copy">
                <span class="red-admin-session-identity__eyebrow">Signed in</span>
                <strong><?php echo htmlspecialchars($redAdminSessionName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php if ($redAdminSessionUsername !== '') : ?>
                    <span class="red-admin-session-identity__username">@<?php echo htmlspecialchars($redAdminSessionUsername, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </span>
            <span class="red-admin-session-identity__role"><?php echo htmlspecialchars($redAdminSessionRoleLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if (red_admin_can_manage_users() && $redAdminSessionRecordId > 0) : ?>
                <button
                    type="button"
                    class="red-admin-session-identity__edit"
                    onclick="return redAdminOpenCurrentUser(<?php echo $redAdminSessionRecordId; ?>);"
                    aria-label="Edit the signed-in administrator account"
                >
                    <?php echo red_admin_shell_icon('edit'); ?>
                    <span>Edit profile</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="toptitleright red-admin-sessionbar__actions">
        <a class="red-admin-logout" href="/bin/logout.php?logout" aria-label="Log out of RED-CMS">
            <span>Logout</span>
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M18 12H9"></path>
            </svg>
        </a>
    </div>
</div><hr class="red-admin-sessionbar__divider">

<div class="scrollboxes">

<!-- BOX CONTENT -->
<div class="wrapper" id="cp_content">
<header class="red-admin-content-heading">
    <span class="red-admin-content-heading__icon"><?php echo red_admin_shell_icon('content'); ?></span>
    <span class="red-admin-content-heading__copy">
        <span>Page workspace</span>
        <strong>Content</strong>
    </span>
    <span class="red-admin-content-heading__badge">Current page</span>
</header>
	<!-- BOX PAGE INFO -->
    <?php $redAdminBreadcrumbItems = red_admin_breadcrumb_items(); ?>
    <div class="red-admin-pagebar">
        <nav class="red-admin-breadcrumb" aria-label="Current page">
            <span class="red-admin-breadcrumb__label">Page</span>
            <ol>
                <?php foreach ($redAdminBreadcrumbItems as $redAdminBreadcrumbItem) : ?>
                    <?php
                    $redAdminBreadcrumbLabel = htmlspecialchars(
                        (string) $redAdminBreadcrumbItem['label'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    $redAdminBreadcrumbUrl = htmlspecialchars(
                        (string) $redAdminBreadcrumbItem['url'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                    <li>
                        <?php if ($redAdminBreadcrumbUrl !== '') : ?>
                            <a href="<?php echo $redAdminBreadcrumbUrl; ?>" title="<?php echo $redAdminBreadcrumbLabel; ?>"><?php echo $redAdminBreadcrumbLabel; ?></a>
                        <?php else : ?>
                            <span aria-current="page" title="<?php echo $redAdminBreadcrumbLabel; ?>"><?php echo $redAdminBreadcrumbLabel; ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <div class="red-admin-layout-control">
            <span class="red-admin-layout-control__label">Layout</span>
            <div class="red-admin-layout-control__field">
        <?php
        $tlay = new layout();
        $layout=$tlay->get_layout();
            
        if (red_admin_can_manage_site()) {
            $editlayout=new editlayout();
            $editlayout->layout_form(countpage,section,category,subcategory,article,$layout);
        } else {
            echo '<strong>'.htmlspecialchars((string) $layout, ENT_QUOTES, 'UTF-8').'</strong>';
        }
        ?>
            </div>
        </div>
    </div>
    <div class="cp_tabs">
      <ul class="nav red-admin-local-tabs" aria-label="Content workspace">
        <li class="selected" id="content_first"><a href="#editcontent"><?php echo red_admin_shell_icon('edit'); ?><span>Edit Content</span></a></li>
        <li id="content_second"><a href="#addcontent"><?php echo red_admin_shell_icon('add'); ?><span>Add Content</span></a></li>
        <li id="content_third"><a href="#toolscontent"><?php echo red_admin_shell_icon('tools'); ?><span>Tools</span></a></li>
      </ul>
      <div id="editcontent" class="tab-content">
        <div class="inner">
        <div id="edit_content_grid">
            <!--EDIT CONTENT GRID START-->
            <?php
            if (red_admin_can_manage_site()) {
                $main_menu=new main_menu();
                $main_menu->cp_menu();
            }
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
        <dx class="red-admin-disclosure-host">
            <button class="red-admin-disclosure" type="button" aria-expanded="false" aria-controls="red-admin-panel-inactive">
                <span class="red-admin-disclosure__icon"><?php echo red_admin_shell_icon('inactive'); ?></span>
                <span class="red-admin-disclosure__copy"><strong>Inactive Articles</strong><span>Review drafts and unpublished content</span></span>
                <span class="red-admin-disclosure__chevron"><?php echo red_admin_shell_icon('chevron'); ?></span>
            </button>
        </dx>
        <dd id="red-admin-panel-inactive">
        	<div class="cp_tabs">
              <ul class="nav red-admin-local-tabs red-admin-local-tabs--single" aria-label="Inactive article tools">
                <li class="selected"><a href="#edit_inactive_article"><?php echo red_admin_shell_icon('edit'); ?><span>Edit Inactive Articles</span></a></li>
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


<?php if (red_admin_can_manage_site()) { ?>
<!-- EXPAND SECTIONS -->
<div class="wrapper" id="cp_sections">
    <dl class="cp1_slideDown">
        <dx class="red-admin-disclosure-host">
            <button class="red-admin-disclosure" type="button" aria-expanded="false" aria-controls="red-admin-panel-sections">
                <span class="red-admin-disclosure__icon"><?php echo red_admin_shell_icon('sections'); ?></span>
                <span class="red-admin-disclosure__copy"><strong>Sections</strong><span>Manage the top level of the site structure</span></span>
                <span class="red-admin-disclosure__chevron"><?php echo red_admin_shell_icon('chevron'); ?></span>
            </button>
        </dx>
        <dd id="red-admin-panel-sections">
        
        	<div class="cp_tabs">
              <ul class="nav red-admin-local-tabs" aria-label="Section tools">
                <li class="selected"><a href="#editsection"><?php echo red_admin_shell_icon('edit'); ?><span>Edit Sections</span></a></li>
                <li><a href="#addsection"><?php echo red_admin_shell_icon('add'); ?><span>Add Section</span></a></li>
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
        <dx class="red-admin-disclosure-host">
            <button class="red-admin-disclosure" type="button" aria-expanded="false" aria-controls="red-admin-panel-categories">
                <span class="red-admin-disclosure__icon"><?php echo red_admin_shell_icon('categories'); ?></span>
                <span class="red-admin-disclosure__copy"><strong>Categories</strong><span>Organize content beneath a parent section</span></span>
                <span class="red-admin-disclosure__chevron"><?php echo red_admin_shell_icon('chevron'); ?></span>
            </button>
        </dx>
        <dd id="red-admin-panel-categories">
        
        	<div class="cp_tabs">
              <ul class="nav red-admin-local-tabs" aria-label="Category tools">
                <li class="selected"><a href="#editcategory"><?php echo red_admin_shell_icon('edit'); ?><span>Edit Categories</span></a></li>
                <li><a href="#addcategory"><?php echo red_admin_shell_icon('add'); ?><span>Add Category</span></a></li>
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
        <dx class="red-admin-disclosure-host">
            <button class="red-admin-disclosure" type="button" aria-expanded="false" aria-controls="red-admin-panel-subcategories">
                <span class="red-admin-disclosure__icon"><?php echo red_admin_shell_icon('subcategories'); ?></span>
                <span class="red-admin-disclosure__copy"><strong>Subcategories</strong><span>Refine content inside a parent category</span></span>
                <span class="red-admin-disclosure__chevron"><?php echo red_admin_shell_icon('chevron'); ?></span>
            </button>
        </dx>
        <dd id="red-admin-panel-subcategories">
        
        	<div class="cp_tabs">
              <ul class="nav red-admin-local-tabs" aria-label="Subcategory tools">
                <li class="selected"><a href="#editsubcategory"><?php echo red_admin_shell_icon('edit'); ?><span>Edit Subcategories</span></a></li>
                <li><a href="#addsubcategory"><?php echo red_admin_shell_icon('add'); ?><span>Add Subcategory</span></a></li>
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
        <dx class="red-admin-disclosure-host">
            <button class="red-admin-disclosure" type="button" aria-expanded="false" aria-controls="red-admin-panel-advanced">
                <span class="red-admin-disclosure__icon"><?php echo red_admin_shell_icon('advanced'); ?></span>
                <span class="red-admin-disclosure__copy"><strong>Advanced</strong><span>Identity, themes, users, and shared website settings</span></span>
                <span class="red-admin-disclosure__chevron"><?php echo red_admin_shell_icon('chevron'); ?></span>
            </button>
        </dx>
        <dd id="red-admin-panel-advanced">
        
        	<div class="cp_tabs">
              <ul class="nav red-admin-local-tabs red-admin-local-tabs--single" aria-label="Advanced setting tools">
                <li class="selected"><a href="#editadvanced"><?php echo red_admin_shell_icon('edit'); ?><span>Edit Advanced</span></a></li>
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
            </div>
        
        </dd>
    </dl>
    <div class="red-admin-signature" aria-label="RED-CMS <?php echo htmlspecialchars(RED_CMS_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
        <strong>RED-CMS <?php echo htmlspecialchars(RED_CMS_VERSION, ENT_QUOTES, 'UTF-8'); ?></strong>
    </div>
</div>
<?php } ?>

</div>
</div>

<?php
}
?>
