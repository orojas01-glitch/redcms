<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 5.0 - (2026/07/24)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.tv/documentation/ 
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/theme_runtime.php';
require_once __DIR__ . '/includes/theme_activation_helpers.php';

$redThemeRequestBufferLevel = ob_get_level();
ob_start();

try {
    $redThemeRequestedId = red_theme_activation_active_id_from_project(__DIR__);
    $redThemeRuntime = red_theme_runtime_bootstrap(
        $redThemeRequestedId,
        __DIR__,
        'legacy-bootstrap',
        true
    );
    $redThemeAdapter = $redThemeRuntime['adapter'];
} catch (Throwable $exception) {
    while (ob_get_level() > $redThemeRequestBufferLevel) {
        ob_end_clean();
    }
    error_log('RED-CMS theme bootstrap failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Theme rendering is temporarily unavailable.');
}

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

<?php
require_once __DIR__ . '/includes/addon_runtime_helpers.php';

$redAddonRuntimeConnection = null;
try {
    $redAddonRuntimeConnection = mysqli_connect(
        DBHOST,
        DBUSER,
        DBPASS,
        DBNAME
    );
    if (!$redAddonRuntimeConnection
        || !mysqli_set_charset($redAddonRuntimeConnection, 'utf8mb4')
    ) {
        throw new RuntimeException(
            'The add-on runtime database connection is unavailable.'
        );
    }
    $redAddonRuntimeContext = red_addon_runtime_request_bootstrap(
        $redAddonRuntimeConnection,
        __DIR__
    );
} catch (Throwable $exception) {
    while (ob_get_level() > $redThemeRequestBufferLevel) {
        ob_end_clean();
    }
    error_log(
        'RED-CMS add-on request bootstrap failed: ' .
        $exception->getMessage()
    );
    http_response_code(503);
    exit('Site extensions are temporarily unavailable.');
} finally {
    if ($redAddonRuntimeConnection instanceof mysqli) {
        mysqli_close($redAddonRuntimeConnection);
    }
}
?>

<?php try { $redThemeAdapter->renderDocumentStart(); ?>
<?php $redThemeAdapter->renderHeaderBundle(); ?>


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
<?php $redThemeAdapter->renderFooter(); ?>

<?php
$redThemeAdapter->renderDocumentEnd();
ob_end_flush();
} catch (Throwable $exception) {
    while (ob_get_level() > $redThemeRequestBufferLevel) {
        ob_end_clean();
    }
    error_log('RED-CMS active theme render failed; using legacy-bootstrap: ' . $exception->getMessage());
    if (($redThemeRuntime['themeId'] ?? '') === 'legacy-bootstrap') {
        http_response_code(500);
        exit('Theme rendering is temporarily unavailable.');
    }

    try {
        http_response_code(200);
        $redThemeRuntime = red_theme_runtime_bootstrap('legacy-bootstrap', __DIR__);
        $redThemeAdapter = $redThemeRuntime['adapter'];
        ob_start();
        $redThemeAdapter->renderDocumentStart();
        $redThemeAdapter->renderHeaderBundle();
        echo "\n\n<!--==============================content================================-->\n\n";
        $page = new Build_Page();
        $page->get_page_query();
        echo "\n\n<!--==============================footer=================================-->\n";
        $redThemeAdapter->renderFooter();
        $redThemeAdapter->renderDocumentEnd();
        ob_end_flush();
    } catch (Throwable $fallbackException) {
        while (ob_get_level() > $redThemeRequestBufferLevel) {
            ob_end_clean();
        }
        error_log('RED-CMS legacy theme recovery failed: ' . $fallbackException->getMessage());
        http_response_code(500);
        exit('Theme rendering is temporarily unavailable.');
    }
}
?>
