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
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_menu_helpers.php';

$payloadFields = array_diff(array_keys($_POST), ['csrf_token', 'Language']);
if (empty($payloadFields)) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;
$post = $_POST;
$title = red_admin_menu_value($post, 'Title');
$language = red_admin_menu_value($post, 'Language');

$success = red_admin_write_transaction($connection, function () use ($connection, $post, $title, $language) {
	$attempted = false;
	if (isset($post['Title'])) {
		$attempted = true;
		if (!red_admin_main_menu_rename(
			$connection,
			$title,
			red_admin_menu_value($post, 'CurTitle')
		)) {
			return false;
		}
	}

	$newLabel = red_admin_menu_value($post, 'NewLabel');
	if (red_admin_menu_scalar($newLabel) !== '') {
		$attempted = true;
		if (!red_admin_main_menu_insert_item(
			$connection,
			1,
			$title,
			$newLabel,
			0,
			'',
			'',
			red_admin_menu_value($post, 'NewMenuOrder'),
			$language
		)) {
			return false;
		}
	}

	foreach (($post['MainLabel'] ?? []) as $groupKey => $labels) {
		if (!is_array($labels)) {
			continue;
		}

		foreach ($labels as $itemKey => $label) {
			$attempted = true;
			if (!red_admin_main_menu_update_item(
				$connection,
				red_admin_menu_value($post, 'MainLabelRecordID', $groupKey, $itemKey),
				$label,
				red_admin_menu_value($post, 'MainMenuOrder', $groupKey, $itemKey),
				red_admin_menu_value($post, 'MainLabelLink', $groupKey, $itemKey),
				red_admin_menu_value($post, 'MainLabelNewWindow', $groupKey, $itemKey)
			)) {
				return false;
			}
		}
	}

	foreach (($post['SubLabel'] ?? []) as $groupKey => $labels) {
		if (!is_array($labels)) {
			continue;
		}

		foreach ($labels as $itemKey => $label) {
			$attempted = true;
			if (!red_admin_main_menu_update_item(
				$connection,
				red_admin_menu_value($post, 'SubLabelRecordID', $groupKey, $itemKey),
				$label,
				red_admin_menu_value($post, 'SubMenuOrder', $groupKey, $itemKey),
				red_admin_menu_value($post, 'SubLabelLink', $groupKey, $itemKey),
				red_admin_menu_value($post, 'SubLabelNewWindow', $groupKey, $itemKey)
			)) {
				return false;
			}
		}
	}

	foreach (($post['SubSubLabel'] ?? []) as $groupKey => $labels) {
		if (!is_array($labels)) {
			continue;
		}

		foreach ($labels as $itemKey => $label) {
			$attempted = true;
			if (!red_admin_main_menu_update_item(
				$connection,
				red_admin_menu_value($post, 'SubSubLabelRecordID', $groupKey, $itemKey),
				$label,
				red_admin_menu_value($post, 'SubSubMenuOrder', $groupKey, $itemKey),
				red_admin_menu_value($post, 'SubSubLabelLink', $groupKey, $itemKey),
				red_admin_menu_value($post, 'SubSubLabelNewWindow', $groupKey, $itemKey)
			)) {
				return false;
			}
		}
	}

	foreach (($post['NewSubLabel'] ?? []) as $groupKey => $labels) {
		if (!is_array($labels)) {
			continue;
		}

		foreach ($labels as $itemKey => $label) {
			if (red_admin_menu_scalar($label) === '') {
				continue;
			}

			$attempted = true;
			if (!red_admin_main_menu_insert_item(
				$connection,
				2,
				$title,
				$label,
				red_admin_menu_value($post, 'NewMainLabelRecordID', $groupKey, $itemKey),
				red_admin_menu_value($post, 'NewSubLabelLink', $groupKey, $itemKey),
				red_admin_menu_value($post, 'NewSubLabelNewWindow', $groupKey, $itemKey),
				red_admin_menu_value($post, 'NewSubMenuOrder', $groupKey, $itemKey),
				$language
			)) {
				return false;
			}
		}
	}

	foreach (($post['NewSubSubLabel'] ?? []) as $groupKey => $labels) {
		if (!is_array($labels)) {
			continue;
		}

		foreach ($labels as $itemKey => $label) {
			if (red_admin_menu_scalar($label) === '') {
				continue;
			}

			$attempted = true;
			if (!red_admin_main_menu_insert_item(
				$connection,
				3,
				$title,
				$label,
				red_admin_menu_value($post, 'NewSubLabelRecordID', $groupKey, $itemKey),
				red_admin_menu_value($post, 'NewSubSubLabelLink', $groupKey, $itemKey),
				red_admin_menu_value($post, 'NewSubSubLabelNewWindow', $groupKey, $itemKey),
				red_admin_menu_value($post, 'NewSubSubMenuOrder', $groupKey, $itemKey),
				$language
			)) {
				return false;
			}
		}
	}

	return $attempted;
}, ['RED_Menu']);

echo $success ? 'yes' : 'no';
$db->close();
?>
