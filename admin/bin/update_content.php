<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_article_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_content_revision_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_other_content_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_seo_helpers.php';

$recordId = isset($_POST['RecordID']) ? (int) $_POST['RecordID'] : 0;
$seoInput = red_admin_seo_collect_post($_POST);
if ($recordId <= 0
	|| (!red_admin_article_has_payload($_POST) && !$seoInput['present'])
	|| !$seoInput['valid']
) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_article_access($db->connection, $recordId);
$existingRow = red_admin_article_full_record($db->connection, $recordId);
if (!$existingRow) {
	echo 'no';
	$db->close();
	exit;
}

$data = red_admin_article_collect_values($_POST, 'update');
$databaseComponent = red_admin_article_clean_value('Component', $existingRow['Component'] ?? '');
$otherContent = null;
$expectedCurrentHash = '';
if ($databaseComponent === 'Other') {
	// Other identity and both stored content values come from the authorized
	// database record. A submitted Component or LongDesc can never redirect
	// this request or select the dedicated-page content independently.
	unset($data['Component'], $data['ShortDesc'], $data['LongDesc']);
	$otherContent = red_admin_other_prepare_content($_POST, $existingRow, 'update');
	$expectedCurrentHash = red_admin_other_current_hash_input($_POST);
	if (empty($otherContent['ok']) || $expectedCurrentHash === '') {
		echo ($otherContent['reason'] ?? '') === 'reconciliation_required'
			|| ($otherContent['reason'] ?? '') === 'selection_required'
			? 'reconcile'
			: 'no';
		$db->close();
		exit;
	}
	$data = array_merge($data, $otherContent['data']);
} else {
	$invalidComponent = array_key_exists('Component', $_POST)
		&& red_admin_article_clean_value('Component', $_POST['Component']) === '';
	if ($invalidComponent) {
		echo 'no';
		$db->close();
		exit;
	}
	if (isset($data['Component'])) {
		red_admin_require_component_selection($db->connection, $data['Component']);
	}
}
if (!red_admin_article_apply_home_position($db->connection, $data, $existingRow)) {
	echo 'no';
	$db->close();
	exit;
}

$connection = $db->connection;
$result = [];
$write = function () use ($connection, $recordId, $data, $seoInput) {
	if (!empty($data) && !red_admin_article_update($connection, $recordId, $data)) {
		return false;
	}
	return !$seoInput['present']
		|| red_admin_seo_save($connection, 'article', $recordId, $seoInput['values']);
};

if ($databaseComponent === 'Other') {
	$result = ($otherContent['action'] ?? '') === 'reconcile'
		? red_admin_content_revision_reconciliation_transaction(
			$connection,
			$recordId,
			$expectedCurrentHash,
			$write,
			['RED_Articles', 'RED_Page_SEO']
		)
		: red_admin_content_revision_guarded_transaction(
			$connection,
			$recordId,
			$expectedCurrentHash,
			$write,
			['RED_Articles', 'RED_Page_SEO'],
			'save'
		);
	$success = !empty($result['ok']);
} else {
	$success = red_admin_content_revision_transaction(
		$connection,
		$recordId,
		$write,
		['RED_Articles', 'RED_Page_SEO'],
		'save'
	);
}
if ($success) {
	red_admin_content_revision_response_headers($connection, $recordId);
}
echo $success ? 'yes' : (($result['reason'] ?? '') === 'conflict' ? 'stale' : 'no');
$db->close();
?>
