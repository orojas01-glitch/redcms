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
require_once __DIR__ . '/includes/bootstrap.php';
red_start_session();
$timezone = $_SESSION['time'] ?? 'America/New_York'; // default timezone
?>
<?php
/*COMMENTS:
class_connection.php: contains database connection
config.php  contains settings about language & ip location.*/
?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php' ?>
<?php
/*COMMENTS:
class_build_page.php: integrates class calls.
class_build_query.php: generates queries for all website structures.
class class_page_layout.php: write the html of the different layouts.
class_limit.php: gets the limit per section, category, subcategories, and articles.
class_layout.php: call page layout.
class_content.php: call all components.*/
?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_build_query.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_build_page.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_layout.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_limit.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_page_layout.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_metatags.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_pagetitle.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_main_menu.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_content.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_article.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_other.php' ?>


<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_gallery.php' ?>
<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_forms.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_build_breadcrumb.php' ?>

<?php require $_SERVER['DOCUMENT_ROOT'].'/class/class_feature_slider.php' ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>
    <?php 
	$page=new Page_Title();
	$page->Title();
	?>
    </title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width; initial-scale=1">
    <?php 
	$page=new Page_Metatags();
	$page->Metatags();
	?>
    
    <meta property="og:image" content="https://adrianagranobles.com/images/articles/image-adrianagranobles-facebookshare.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="author" content="Oscar Rojas">
    <meta name = "format-detection" content = "telephone=no" />
    
    
	<!--CSS-->
    <link rel="stylesheet" href="/css/bootstrap.min.css?v=<?= time(); ?>">
    <link rel="stylesheet" href="/css/forms.css?v=<?= time(); ?>" type="text/css" media="screen">
	<link rel="stylesheet" href="/css/red-css.css?v=<?= time(); ?>" type="text/css" media="screen">
    <link rel="stylesheet" href="/css/style.css?v=<?= time(); ?>" type="text/css" media="screen">
    
    <!--JS-->
    <script src="/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/js/jquery-3.7.1.min.js"></script>
    <script src="/js/superfish.js"></script>
    <script src="/js/jquery.mobilemenu.js"></script>
    <script src="/js/tm-scripts.js"></script>
    
    
    <link rel="icon" href="/logoico.ico" type="image/x-icon">
	<link rel="shortcut icon" href="/logoico.ico" type="image/x-icon">
  
    <div id="fb-root"></div>
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v22.0&appId=228959903787478"></script>
    
    <?php
	include $_SERVER['DOCUMENT_ROOT'].'/admin/mainnav.php' ;
	?>
    
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'].'/includes/header.php' ?>


<!--==============================content================================-->

<?php 
// GET ALL CONTENT FOR THIS PAGE.
// 1. /class/class_build_page.asp -- get_page_query()
// 2. /class/class_build_query.php -- get_query()
// 3. /class/class_page_layout.php -- layout()
// 3a. /class/limit.php -- get_limit()
// 3b. /class/class_layout.php -- get_layout()
// 3c. /class/class_content.php -- cp_article()
$page=new Build_Page();
$page->get_page_query();
 ?>

<!--==============================footer=================================-->
<?php include $_SERVER['DOCUMENT_ROOT'].'/includes/footer.php'; ?>

</body>
</html>
