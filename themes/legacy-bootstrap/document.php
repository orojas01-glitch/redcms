<?php
/**
 * Current RED-CMS document shell and ordered public asset tags.
 *
 * This is a compatibility view, not a portable theme template. The adapter
 * supplies only the validated start/end phase; existing CMS classes, dynamic
 * metadata, and the administrator overlay retain their current behavior.
 */

if (!isset($redThemeDocumentPhase) || !in_array($redThemeDocumentPhase, ['start', 'end'], true)) {
    throw new RuntimeException('A valid legacy document render phase is required.');
}

if ($redThemeDocumentPhase === 'end') {
    echo '<script src="/js/public-gallery.js?v=' . time() . '"></script>' . "\n";
    echo "</body>\n</html>\n";
    return;
}
?><!DOCTYPE html>
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
    <link rel="stylesheet" href="/css/public-gallery.css?v=<?= time(); ?>" type="text/css" media="screen">
    
    <!--JS-->
    <script src="/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/js/jquery-3.7.1.min.js"></script>
    <script src="/js/superfish.js"></script>
    <script src="/js/jquery.mobilemenu.js"></script>
    <script src="/js/tm-scripts.js"></script>
    
    
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/logoico.ico" sizes="any">
    <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#c81918">
  
    <div id="fb-root"></div>
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v22.0&appId=228959903787478"></script>
    
    <?php
	include $_SERVER['DOCUMENT_ROOT'].'/admin/mainnav.php' ;
	?>
    
</head>
<body>

<?php
