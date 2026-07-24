<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_form_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_content_revision_helpers.php';

$artRecordId = isset($_POST['ArtRecordID']) ? (int) $_POST['ArtRecordID'] : 0;
$recordId = isset($_POST['RecordID']) ? (int) $_POST['RecordID'] : 0;

if (
	$artRecordId <= 0
	|| $recordId <= 0
	|| red_admin_authorization_scalar($_POST['Component'] ?? '') !== 'Form'
	|| !red_admin_form_has_payload($_POST)
) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_component_selection($db->connection, 'Form', $_POST['FormType'] ?? '');

// Creation must never become an alternate update path for an existing Form.
if (red_admin_form_record_exists($db->connection, $recordId)) {
	echo 'no';
	$db->close();
	exit;
}

$existingArticleFull = red_admin_article_full_record($db->connection, $artRecordId);
if ($existingArticleFull) {
	if (red_admin_article_clean_value('Component', $existingArticleFull['Component'] ?? '') !== 'Form'
		|| !red_admin_article_is_upload_placeholder($existingArticleFull)
	) {
		echo 'no';
		$db->close();
		exit;
	}
}

$articlePost = red_admin_form_article_post($_POST, $artRecordId, 'insert');
$articleData = red_admin_article_collect_values($articlePost, 'insert');
$existingArticle = red_admin_article_record($db->connection, $artRecordId);
if (!red_admin_article_apply_home_position($db->connection, $articleData, $existingArticle)) {
	echo 'no';
	$db->close();
	exit;
}
if ($existingArticle && !red_admin_article_prepare_upload_placeholder_promotion($db->connection, $artRecordId, $articleData)) {
	echo 'no';
	$db->close();
	exit;
}

$formData = [];
if (red_admin_form_has_form_payload($_POST)) {
	$formData = red_admin_form_collect_values($_POST, 'insert', $recordId, $artRecordId);
	if (!red_admin_form_apply_table_name($_POST, $artRecordId, $formData, 'insert')) {
		echo 'no';
		$db->close();
		exit;
	}
	if (!red_admin_form_data_is_safe($formData)) {
		echo 'no';
		$db->close();
		exit;
	}
}

if (empty($formData)) {
	echo 'no';
	$db->close();
	exit;
}

$registrationTableCreated = false;
$registrationTableName = '';
// MySQL DDL can implicitly commit, so create registration tables before the row transaction.
if (red_admin_form_uses_registration_table($formData['FormType'] ?? '') && ($formData['TableName'] ?? '') !== '') {
	$registrationTableName = (string) $formData['TableName'];
	$tableExists = red_admin_form_registration_table_exists($db->connection, $registrationTableName);
	if ($tableExists !== false
		|| !red_admin_form_create_registration_table($db->connection, $registrationTableName, $formData['LongDesc'] ?? '')
	) {
		echo 'no';
		$db->close();
		exit;
	}
	$registrationTableCreated = true;
}

$connection = $db->connection;
$success = red_admin_content_revision_create_transaction(
	$connection,
	$artRecordId,
	function () use ($connection, $existingArticle, $artRecordId, $articleData, $recordId, $formData) {
		if ($existingArticle) {
			$articleSuccess = empty($articleData) ? true : red_admin_article_update($connection, $artRecordId, $articleData);
		} else {
			$articleSuccess = red_admin_article_insert($connection, $artRecordId, $articleData);
		}

		$formSuccess = $articleSuccess && !empty($formData)
			? red_admin_form_save($connection, $recordId, $artRecordId, $formData)
			: $articleSuccess;

		return $articleSuccess && $formSuccess;
	},
	['RED_Articles', 'RED_C_Form']
);

if (!$success && $registrationTableCreated) {
	red_admin_form_drop_registration_table($connection, $registrationTableName);
}

if ($success) {
	red_admin_content_revision_response_headers($connection, $artRecordId);
}
echo $success ? 'yes' : 'no';
$db->close();
?>
