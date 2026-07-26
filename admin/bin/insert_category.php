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
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin_site_manager(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_area_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_seo_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);

$title = red_admin_post_text('Categories');
$categories = red_admin_slug($title, true);
$layout = red_admin_post_text('Layout');
// Category limits remain a compatibility safeguard, not an administrator-facing setting.
$queryLimit = '100';
// The legacy Private value is metadata only; do not store a false protection promise.
$accessLevel = 'Public';
$features = red_admin_feature_list($_POST['Features'] ?? []);
$active = red_admin_active_value(red_admin_post_text('Active', 'Y'));
$description = red_admin_post_text('Description');
$tags = red_admin_tag_list($_POST['Tags'] ?? '');
$language = red_admin_post_text('Language', defined('language') ? language : 'sp');
$sectionRecordId = (int) red_admin_post_text('SectionRecordID');
$seoInput = red_admin_seo_collect_post($_POST);

if ($title === ''
    || $categories === ''
    || $language === ''
    || $sectionRecordId <= 0
    || !$seoInput['valid']
) {
    echo 'no';
    $db->close();
    exit;
}

$conflict = red_admin_area_alias_conflict($db->connection, $language, $categories);
if ($conflict !== '') {
    echo $conflict;
    $db->close();
    exit;
}

if (red_admin_seo_insert_area(
    $db->connection,
    'RED_Categories',
    'Categories',
    $title,
    $categories,
    $layout,
    $queryLimit,
    $accessLevel,
    $features,
    $active,
    $description,
    $tags,
    $language,
    $sectionRecordId,
    $seoInput['values']
)) {
    echo 'yes';
} else {
    echo 'no';
}

$db->close();
?>
