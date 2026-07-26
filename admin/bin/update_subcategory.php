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

$payloadFields = array_diff(array_keys($_POST), ['csrf_token', 'RecordID', 'CurrentSubCategory']);
if (empty($payloadFields) || empty($_POST['RecordID'])) {
    echo 'no';
    exit;
}

require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_area_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_seo_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);

$recordId = (int) red_admin_post_text('RecordID');
$data = red_admin_area_update_payload($_POST, 'SubCategories', 'RED_SubCategories');
$newSubCategory = $data['SubCategories'] ?? '';
$language = red_admin_area_language();
$existing = red_admin_area_record($db->connection, 'RED_SubCategories', $recordId);
$currentSubCategory = strtolower(red_admin_text($existing['SubCategories'] ?? ''));
$seoInput = red_admin_seo_collect_post($_POST);

if ($recordId <= 0
    || !$existing
    || (int) ($data['CategoryRecordID'] ?? 0) <= 0
    || !$seoInput['valid']
) {
    echo 'no';
    $db->close();
    exit;
}

$renaming = array_key_exists('SubCategories', $data)
    && $newSubCategory !== ''
    && $newSubCategory !== $currentSubCategory;
if ($renaming) {
    $conflict = red_admin_area_alias_conflict($db->connection, $language, $newSubCategory);
    if ($conflict !== '') {
        echo $conflict;
        $db->close();
        exit;
    }

}

$afterSave = $seoInput['present']
    ? red_admin_seo_area_save_callback(
        $db->connection,
        'subcategory',
        $recordId,
        $seoInput['values']
    )
    : null;
$result = red_admin_area_save_existing(
    $db->connection,
    'RED_SubCategories',
    'SubCategories',
    $recordId,
    $data,
    $afterSave,
    $seoInput['present'] ? ['RED_Page_SEO'] : []
);
if (is_array($result)) {
    if ($renaming) {
        header('X-RED-Canonical-Alias: ' . rawurlencode((string) $result['alias']));
    }
    if (!empty($result['routeChanged']) && !empty($result['path'])) {
        header('X-RED-Canonical-Path: ' . rawurlencode((string) $result['path']));
    }
    echo (string) ($result['response'] ?? 'yes');
} else {
    echo 'no';
}

$db->close();
?>
