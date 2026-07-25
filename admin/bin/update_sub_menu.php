<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin_site_manager(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_menu_helpers.php';

$payloadFields = array_diff(array_keys($_POST), ['csrf_token', 'ArtRecordID', 'RecordID']);
$artRecordId = isset($_POST['ArtRecordID']) ? (int) $_POST['ArtRecordID'] : 0;
$recordId = isset($_POST['RecordID']) ? (int) $_POST['RecordID'] : 0;
if (empty($payloadFields) || $artRecordId <= 0 || $recordId <= 0) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
if (!red_admin_component_menu_record_matches($db->connection, $recordId, $artRecordId)) {
	echo 'no';
	$db->close();
	exit;
}

$connection = $db->connection;
$post = $_POST;
$title = red_admin_menu_value($_POST, 'Title');
$menuType = red_admin_menu_value($_POST, 'MenuType');

$success = red_admin_theme_contract_write_transaction($connection, function () use ($connection, $post, $artRecordId, $recordId, $title, $menuType) {
	if (!red_admin_component_menu_record_matches($connection, $recordId, $artRecordId)) {
		return false;
	}

	$attempted = false;
	if (isset($post['Title'])) {
		$attempted = true;
		if (!red_admin_component_menu_update_title($connection, $artRecordId, $title)) {
			return false;
		}
	}

	if (isset($post['MenuType'])) {
		$attempted = true;
		if (!red_admin_component_menu_update_type($connection, $artRecordId, $menuType)) {
			return false;
		}
	}

	$newLabel = red_admin_menu_value($post, 'NewLabel');
	if (red_admin_menu_scalar($newLabel) !== '') {
		$attempted = true;
		if (!red_admin_component_menu_insert_item(
			$connection,
			$artRecordId,
			1,
			$title,
			$newLabel,
			0,
			'',
			'',
			red_admin_menu_value($post, 'NewMenuOrder'),
			$menuType
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
			if (!red_admin_component_menu_update_item(
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
			if (!red_admin_component_menu_update_item(
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

	foreach (($post['NewSubLabel'] ?? []) as $groupKey => $labels) {
		if (!is_array($labels)) {
			continue;
		}

		foreach ($labels as $itemKey => $label) {
			if (red_admin_menu_scalar($label) === '') {
				continue;
			}

			$attempted = true;
			if (!red_admin_component_menu_insert_item(
				$connection,
				$artRecordId,
				2,
				$title,
				$label,
				red_admin_menu_value($post, 'MainLabelRecordID', $groupKey, $itemKey),
				red_admin_menu_value($post, 'NewSubLabelLink', $groupKey, $itemKey),
				red_admin_menu_value($post, 'NewSubLabelNewWindow', $groupKey, $itemKey),
				red_admin_menu_value($post, 'NewSubMenuOrder', $groupKey, $itemKey),
				$menuType
			)) {
				return false;
			}
		}
	}

	if (red_admin_article_has_payload($post)) {
		$attempted = true;
		$articleRow = red_admin_menu_article_record($connection, $artRecordId);
		if (!$articleRow) {
			return false;
		}

		$data = red_admin_article_collect_values($post, 'update');
		if (!red_admin_article_apply_home_position($connection, $data, $articleRow)) {
			return false;
		}

		if (array_key_exists('Article', $post)) {
			$articleList = red_admin_menu_article_list($post['Article']);
			$data['Article'] = $articleList;
			if ($articleList !== '' && !array_key_exists('PagePosition', $data) && (int) ($articleRow['PagePosition'] ?? 0) === 0) {
				$data['PagePosition'] = 1;
			}
		}

		if (!red_admin_article_update($connection, $artRecordId, $data)) {
			return false;
		}
	}

	return $attempted;
}, ['RED_C_Menu', 'RED_Articles']);

echo $success ? 'yes' : 'no';
$db->close();
?>
