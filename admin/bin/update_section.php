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

$payloadFields = array_diff(array_keys($_POST), ['csrf_token', 'RecordID', 'CurrentSection']);
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
$data = red_admin_area_update_payload($_POST, 'Sections');
$newSection = $data['Sections'] ?? '';
$language = red_admin_area_language();
$existing = red_admin_area_record($db->connection, 'RED_Sections', $recordId);
$currentSection = strtolower(red_admin_text($existing['Sections'] ?? ''));
$seoInput = red_admin_seo_collect_post($_POST);

if ($recordId <= 0 || !$existing || !$seoInput['valid']) {
    echo 'no';
    $db->close();
    exit;
}

$renaming = array_key_exists('Sections', $data) && $newSection !== '' && $newSection !== $currentSection;
if ($renaming) {
    $conflict = red_admin_area_alias_conflict($db->connection, $language, $newSection);
    if ($conflict !== '') {
        echo $conflict;
        $db->close();
        exit;
    }

}

$afterSave = $seoInput['present']
    ? red_admin_seo_area_save_callback(
        $db->connection,
        'section',
        $recordId,
        $seoInput['values']
    )
    : null;
$result = red_admin_area_save_existing(
    $db->connection,
    'RED_Sections',
    'Sections',
    $recordId,
    $data,
    $afterSave,
    $seoInput['present'] ? ['RED_Page_SEO'] : []
);
if (is_array($result)) {
    if (!empty($result['routeChanged'])) {
        header('X-RED-Canonical-Alias: ' . rawurlencode((string) $result['alias']));
    }
    echo (string) ($result['response'] ?? 'yes');
} else {
    echo 'no';
}

$db->close();
?>
