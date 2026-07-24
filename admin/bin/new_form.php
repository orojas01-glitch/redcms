<?php
/**
 * RED-CMS administrator: create a Form component.
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/bootstrap.php';
red_start_session();
red_require_admin();
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_form_ui_helpers.php';

if (empty($_SESSION['alias'])) {
    header('Location: http://'.BASE_URL.'');
    exit;
}

$Type = red_admin_post_text('Type');
$Section = red_admin_post_text('Section');
$Category = red_admin_post_text('Category');
$SubCategory = red_admin_post_text('SubCategory');
$Article = red_admin_post_text('Article');
$VarPosition = red_admin_article_position_column($_POST['VarPosition'] ?? '', 'PagePosition');
if ($VarPosition === null) {
    echo 'no';
    exit;
}

$Language = substr(red_admin_post_text('Language'), 0, 2);
$Layout = red_admin_post_text('Layout');
$RecordID = mt_rand();
$ArtRecordID = mt_rand();
$csrfToken = red_csrf_token();

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_component_selection($db->connection, 'Form', $Type);

$allTypeLabels = red_admin_form_ui_type_options();
$typeOptions = [];
if ($Type === 'Login') {
    $typeOptions['Login'] = $allTypeLabels['Login'];
} else {
    foreach (['Contact', 'Response', 'Register', 'Other'] as $publicType) {
        if (red_admin_component_selection_allowed($db->connection, 'Form', $publicType)) {
            $typeOptions[$publicType] = $allTypeLabels[$publicType];
        }
    }
}
if (!isset($typeOptions[$Type])) {
    $typeOptions = [$Type => ($allTypeLabels[$Type] ?? $Type)] + $typeOptions;
}

$typePresets = [];
foreach (array_keys($typeOptions) as $authorizedType) {
    $presetTemplate = red_admin_article_form_component_template($db->connection, $authorizedType);
    $typePresets[$authorizedType] = [
        'definition' => red_admin_form_ui_creation_definition(
            $authorizedType,
            (string) ($presetTemplate['Template'] ?? '')
        ),
        'response' => (string) ($presetTemplate['ResponseTemplate'] ?? ''),
    ];
}
$componentTemplate = $typePresets[$Type] ?? ['definition' => '', 'response' => ''];
$positionOptions = red_admin_article_layout_position_options($db->connection, $Layout);
$sectionOptions = red_admin_article_area_options($db->connection, 'RED_Sections', 'Sections', $Section);
$categoryOptions = red_admin_article_area_options($db->connection, 'RED_Categories', 'Categories', $Category);
$subCategoryOptions = red_admin_article_area_options($db->connection, 'RED_SubCategories', 'SubCategories', $SubCategory);
$articleOptions = red_admin_article_page_options($db->connection, $Article);
$db->close();

$uploadUrls = [];
foreach (['BigPict', 'SmallPict'] as $uploadCase) {
    $uploadUrls[$uploadCase] = red_admin_form_ui_upload_url([
        'RecordID' => $ArtRecordID,
        'UC' => $uploadCase,
        'Insert' => 'true',
        'AuthComponent' => 'Form',
        'AuthSubtype' => $Type,
        'Language' => $Language,
    ]);
}

$isLogin = $Type === 'Login';
$managedTableName = 'RED_Register_'.$ArtRecordID;
red_admin_render_form_workspace([
    'mode' => 'create',
    'returnTarget' => 'add_content_grid',
    'submitUrl' => '/admin/bin/insert_form.php',
    'title' => $isLogin ? 'Login' : '',
    'alias' => $isLogin ? 'login' : '',
    'active' => 'Y',
    'homeFeature' => '',
    'position' => 0,
    'positionOrder' => '',
    'positionOptions' => $positionOptions,
    'varPosition' => $VarPosition,
    'formType' => $Type,
    'typeOptions' => $typeOptions,
    'definition' => (string) ($componentTemplate['definition'] ?? ''),
    'shortDesc' => '',
    'subject' => '',
    'submitter' => '',
    'destinatary' => '',
    'cc' => '',
    'bcc' => '',
    'response' => (string) ($componentTemplate['response'] ?? ''),
    'tableName' => $managedTableName,
    'schemaLocked' => $isLogin,
    'typePresets' => $typePresets,
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
    'artRecordId' => $ArtRecordID,
    'language' => $Language,
    'layout' => $Layout,
    'editedBy' => red_admin_text($_SESSION['alias'] ?? ''),
    'csrfToken' => $csrfToken,
]);
