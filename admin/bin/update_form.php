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

if ($artRecordId <= 0 || $recordId <= 0 || !red_admin_form_has_payload($_POST)) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_article_access($db->connection, $artRecordId);
if (array_key_exists('FormType', $_POST)) {
	red_admin_require_component_selection($db->connection, 'Form', $_POST['FormType']);
}

$existingArticle = red_admin_article_record($db->connection, $artRecordId);
$existingForm = red_admin_form_render_record($db->connection, $recordId, $artRecordId);
if (!$existingArticle || !$existingForm) {
	echo 'no';
	$db->close();
	exit;
}

$existingFormType = red_admin_form_clean_type($existingForm['FormType'] ?? '');
if (array_key_exists('FormType', $_POST)
	&& red_admin_form_clean_type($_POST['FormType']) !== $existingFormType
) {
	echo 'no';
	$db->close();
	exit;
}
if (red_admin_form_schema_is_locked($existingFormType)
	&& array_key_exists('LongDesc', $_POST)
	&& red_admin_form_scalar($_POST['LongDesc']) !== red_admin_form_scalar($existingForm['LongDesc'] ?? '')
) {
	echo 'no';
	$db->close();
	exit;
}

$articlePost = red_admin_form_article_post($_POST, $artRecordId, 'update');
$articleData = red_admin_article_collect_values($articlePost, 'update');
if (!red_admin_article_apply_home_position($db->connection, $articleData, $existingArticle)) {
	echo 'no';
	$db->close();
	exit;
}

$formData = [];
if (red_admin_form_has_form_payload($_POST)) {
	$formData = red_admin_form_collect_values($_POST, 'update', $recordId, $artRecordId);
	if (!red_admin_form_apply_table_name($_POST, $artRecordId, $formData, 'update')) {
		echo 'no';
		$db->close();
		exit;
	}
	$effectiveFormData = array_merge($existingForm, $formData);
	if (!red_admin_form_data_is_safe($effectiveFormData)) {
		echo 'no';
		$db->close();
		exit;
	}
}

$connection = $db->connection;
$success = red_admin_content_revision_transaction(
	$connection,
	$artRecordId,
	function () use ($connection, $artRecordId, $articleData, $recordId, $formData) {
		$articleSuccess = empty($articleData) ? true : red_admin_article_update($connection, $artRecordId, $articleData);
		$formSuccess = $articleSuccess && !empty($formData)
			? red_admin_form_update($connection, $recordId, $formData)
			: $articleSuccess;

		return $articleSuccess && $formSuccess;
	},
	['RED_Articles', 'RED_C_Form'],
	'save'
);

if ($success) {
	red_admin_content_revision_response_headers($connection, $artRecordId);
}
echo $success ? 'yes' : 'no';
$db->close();
?>
