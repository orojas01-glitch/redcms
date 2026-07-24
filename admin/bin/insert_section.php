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

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);

$title = red_admin_post_text('Sections');
$sections = red_admin_slug($title);
$layout = red_admin_post_text('Layout');
// Section limits remain a compatibility safeguard, not an administrator-facing setting.
$queryLimit = '100';
// The legacy Private value is metadata only; do not store a false protection promise.
$accessLevel = 'Public';
$features = red_admin_feature_list($_POST['Features'] ?? []);
$active = red_admin_active_value(red_admin_post_text('Active', 'Y'));
$description = red_admin_post_text('Description');
$tags = red_admin_tag_list($_POST['Tags'] ?? '');
$language = red_admin_post_text('Language', defined('language') ? language : 'sp');

if ($title === '' || $sections === '' || $language === '') {
    echo 'no';
    $db->close();
    exit;
}

$conflict = red_admin_area_alias_conflict($db->connection, $language, $sections);
if ($conflict !== '') {
    echo $conflict;
    $db->close();
    exit;
}

if (red_admin_insert_area($db->connection, 'RED_Sections', 'Sections', $title, $sections, $layout, $queryLimit, $accessLevel, $features, $active, $description, $tags, $language)) {
    echo 'yes';
} else {
    echo 'no';
}

$db->close();
?>
