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
red_require_admin(true);

$payloadFields = array_diff(array_keys($_POST), ['csrf_token', 'RecordID', 'CurrentSubCategory']);
if (empty($payloadFields) || empty($_POST['RecordID'])) {
    echo 'no';
    exit;
}

require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_area_helpers.php';

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);

$recordId = (int) red_admin_post_text('RecordID');
$currentSubCategory = strtolower(red_admin_post_text('CurrentSubCategory'));
$data = red_admin_area_update_payload($_POST, 'SubCategories');
$newSubCategory = $data['SubCategories'] ?? '';
$language = red_admin_area_language();

if ($recordId <= 0) {
    echo 'no';
    $db->close();
    exit;
}

$renaming = array_key_exists('SubCategories', $data) && $newSubCategory !== '' && $newSubCategory !== $currentSubCategory;
if ($renaming) {
    $conflict = red_admin_area_alias_conflict($db->connection, $language, $newSubCategory);
    if ($conflict !== '') {
        echo $conflict;
        $db->close();
        exit;
    }

    $response = red_admin_area_rename(
        $db->connection,
        'RED_SubCategories',
        'SubCategories',
        $recordId,
        $data,
        $currentSubCategory,
        $newSubCategory,
        $language
    );
    echo $response !== false ? $response : 'no';
} else {
    $areaRows = red_admin_update_area($db->connection, 'RED_SubCategories', 'SubCategories', $recordId, $data);
    echo ($areaRows !== false && $areaRows > 0) ? 'yes' : 'no';
}

$db->close();
?>
