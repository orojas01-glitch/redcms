<?php
/**
 * RED-CMS administrator: create an Other/HTML block.
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
red_require_admin();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_other_ui_helpers.php';

if (empty($_SESSION['alias'])) {
    header('Location: http://'.BASE_URL.'');
    exit;
}

$Type = red_admin_post_text('Type');
$CountPage = red_admin_post_text('CountPage');
$Section = red_admin_post_text('Section');
$Category = red_admin_post_text('Category');
$SubCategory = red_admin_post_text('SubCategory');
$VarPosition = red_admin_article_position_column($_POST['VarPosition'] ?? '', 'PagePosition');
if ($VarPosition === null) {
    echo 'no';
    exit;
}

$Language = substr(red_admin_post_text('Language'), 0, 2);
$Layout = red_admin_post_text('Layout');
$Article = red_admin_post_text('Article');
$RecordID = mt_rand();
$csrfToken = red_csrf_token();

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_component_selection($db->connection, 'Other');
$positionOptions = red_admin_article_layout_position_options($db->connection, $Layout);
$sectionOptions = red_admin_article_area_options($db->connection, 'RED_Sections', 'Sections', $Section);
$categoryOptions = red_admin_article_area_options($db->connection, 'RED_Categories', 'Categories', $Category);
$subCategoryOptions = red_admin_article_area_options($db->connection, 'RED_SubCategories', 'SubCategories', $SubCategory);
$articleOptions = red_admin_article_page_options($db->connection, $Article);
$db->close();

$uploadUrls = [];
foreach (['BigPict', 'SmallPict'] as $uploadCase) {
    $uploadUrls[$uploadCase] = red_admin_other_upload_url([
        'RecordID' => $RecordID,
        'UC' => $uploadCase,
        'Insert' => 'true',
        'AuthComponent' => 'Other',
        'Language' => $Language,
    ]);
}

red_admin_render_other_form([
    'mode' => 'create',
    'returnTarget' => 'add_content_grid',
    'submitUrl' => '/admin/bin/insert_content.php',
    'title' => '',
    'active' => 'Y',
    'homeFeature' => '',
    'position' => 0,
    'positionOrder' => '',
    'positionOptions' => $positionOptions,
    'varPosition' => $VarPosition,
    'html' => '',
    'preferredEditorMode' => 'visual',
    'sectionOptions' => $sectionOptions,
    'categoryOptions' => $categoryOptions,
    'subCategoryOptions' => $subCategoryOptions,
    'articleOptions' => $articleOptions,
    'startDateMeta' => ['display' => ''],
    'expirationDateMeta' => ['display' => ''],
    'bigPict' => '',
    'smallPict' => '',
    'smallPictAlign' => '',
    'uploadUrls' => $uploadUrls,
    'recordId' => $RecordID,
    'language' => $Language,
    'layout' => $Layout,
    'editedBy' => red_admin_text($_SESSION['alias']),
    'csrfToken' => $csrfToken,
]);
