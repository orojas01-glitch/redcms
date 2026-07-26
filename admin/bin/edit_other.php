<?php
/**
 * RED-CMS administrator: edit an Other/HTML block.
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
red_require_admin();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_other_ui_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_seo_helpers.php';

$RecordID = (int) ($_POST['RecordID'] ?? 0);
$VarPosition = red_admin_article_position_column($_POST['VarPosition'] ?? '');
if ($RecordID <= 0 || $VarPosition === null) {
    echo 'no';
    exit;
}

$ArticleSelected = red_admin_text($_POST['Article'] ?? '');
$Layout = red_admin_text($_POST['Layout'] ?? '');

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_article_access($db->connection, $RecordID);
$row = red_admin_article_full_record($db->connection, $RecordID);
if (!$row || red_admin_text($row['Component'] ?? '') !== 'Other') {
    $db->close();
    echo 'no';
    exit;
}

if ($Layout === '') {
    $Layout = red_admin_text($row['Layout'] ?? '');
}

$Language = substr(red_admin_text($row['Language'] ?? ''), 0, 2);
$Section = red_admin_text($row['Sections'] ?? '');
$Category = red_admin_text($row['Categories'] ?? '');
$SubCategory = red_admin_text($row['SubCategories'] ?? '');
$Article = red_admin_text($row['Article'] ?? $ArticleSelected);
$currentPosition = (int) ($row[$VarPosition] ?? 0);
$positionOrder = (int) ($row[$VarPosition.'Order'] ?? 0);

$positionOptions = red_admin_article_layout_position_options($db->connection, $Layout);
if (!array_key_exists($currentPosition, $positionOptions)) {
    $positionOptions = [$currentPosition => 'Unavailable; preserved'] + $positionOptions;
}

$sectionOptions = red_admin_other_preserve_option(
    red_admin_article_area_options($db->connection, 'RED_Sections', 'Sections', $Section),
    $Section
);
$categoryOptions = red_admin_other_preserve_option(
    red_admin_article_area_options($db->connection, 'RED_Categories', 'Categories', $Category),
    $Category
);
$subCategoryOptions = red_admin_other_preserve_option(
    red_admin_article_area_options($db->connection, 'RED_SubCategories', 'SubCategories', $SubCategory),
    $SubCategory
);
$articleOptions = red_admin_other_preserve_option(
    red_admin_article_page_options($db->connection, $Article),
    $Article
);
$seoValues = red_admin_seo_values($db->connection, 'article', $RecordID);
$db->close();

$csrfToken = red_csrf_token();
$uploadUrls = [];
foreach (['BigPict', 'SmallPict'] as $uploadCase) {
    $uploadUrls[$uploadCase] = red_admin_other_upload_url([
        'RecordID' => $RecordID,
        'UC' => $uploadCase,
        'Language' => $Language,
    ]);
}

$forwardedProtocol = strtolower(red_admin_text($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$requestUsesHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || $forwardedProtocol === 'https';
$publicUrl = ($requestUsesHttps ? 'https' : 'http').'://'.BASE_URL;
$pathParts = array_values(array_filter([
    $Section,
    $Category,
    $SubCategory,
    red_admin_text($row['Alias'] ?? ''),
], static function ($value) {
    return $value !== '';
}));
if ($pathParts !== []) {
    $publicUrl .= '/'.implode('/', $pathParts);
}

$html = red_admin_article_scalar($row['ShortDesc'] ?? '');
red_admin_render_other_form([
    'mode' => 'edit',
    'returnTarget' => 'edit_content_grid',
    'submitUrl' => '/admin/bin/update_content.php',
    'deleteUrl' => '/admin/bin/delete_label.php',
    'title' => red_admin_text($row['Title'] ?? ''),
    'alias' => red_admin_text($row['Alias'] ?? ''),
    'tags' => red_admin_text($row['Tags'] ?? ''),
    'active' => red_admin_text($row['Active'] ?? ''),
    'homeFeature' => red_admin_text($row['HomeFeature'] ?? ''),
    'position' => $currentPosition,
    'positionOrder' => $positionOrder,
    'positionOptions' => $positionOptions,
    'varPosition' => $VarPosition,
    'html' => $html,
    'preferredEditorMode' => red_admin_other_preferred_editor_mode($html, 'edit'),
    'sectionOptions' => $sectionOptions,
    'categoryOptions' => $categoryOptions,
    'subCategoryOptions' => $subCategoryOptions,
    'articleOptions' => $articleOptions,
    'startDateMeta' => red_admin_other_date_meta($row['StartDate'] ?? '', '1970-01-01'),
    'expirationDateMeta' => red_admin_other_date_meta($row['ExpDate'] ?? '', '9999-12-31'),
    'bigPict' => red_admin_text($row['BigPict'] ?? ''),
    'smallPict' => red_admin_text($row['SmallPict'] ?? ''),
    'smallPictAlign' => red_admin_text($row['SmallPictAlign'] ?? ''),
    'uploadUrls' => $uploadUrls,
    'publicUrl' => $publicUrl,
    'recordId' => $RecordID,
    'editedBy' => red_admin_text($_SESSION['alias'] ?? ''),
    'csrfToken' => $csrfToken,
    'seoValues' => $seoValues,
]);
