<?php
require_once $_SERVER["DOCUMENT_ROOT"]."/includes/bootstrap.php";
red_start_session();
red_require_admin(true);
require $_SERVER['DOCUMENT_ROOT'].'/includes/config.php';
require $_SERVER['DOCUMENT_ROOT'].'/class/class_connection.php';
require $_SERVER['DOCUMENT_ROOT'].'/includes/admin_gallery_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_authorization_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_content_revision_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/admin_seo_helpers.php';

$artRecordId = isset($_POST['ArtRecordID']) ? (int) $_POST['ArtRecordID'] : 0;
$recordId = isset($_POST['RecordID']) ? (int) $_POST['RecordID'] : 0;
$seoInput = red_admin_seo_collect_post($_POST);

if (
	$artRecordId <= 0
	|| $recordId <= 0
	|| red_admin_authorization_scalar($_POST['Component'] ?? '') !== 'Gallery'
	|| !red_admin_gallery_has_payload($_POST)
	|| !$seoInput['valid']
) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_component_selection($db->connection, 'Gallery', $_POST['GalleryType'] ?? '');

$postedGalleryType = red_admin_gallery_clean_type($_POST['GalleryType'] ?? '');
$galleryPost = $_POST;
if ($postedGalleryType === 'Video') {
	$videoUrlData = red_video_url_data($galleryPost['LongDesc'] ?? '');
	if (!is_array($videoUrlData)) {
		echo 'no';
		$db->close();
		exit;
	}
}
$galleryRecordExists = red_admin_gallery_record_exists($db->connection, $recordId);
$existingGallery = $galleryRecordExists
	? red_admin_gallery_render_record($db->connection, $recordId, $artRecordId)
	: null;
$existingArticle = red_admin_article_full_record($db->connection, $artRecordId);
if ($galleryRecordExists && !$existingGallery) {
	echo 'no';
	$db->close();
	exit;
}

// A create request may be repeated after queued uploads are stored. Permit the
// upload placeholder (blank subtype) and the exact subtype just created, but
// never let crafted IDs turn this upsert path into a Banner/Video mutation.
$existingGalleryType = $existingGallery
	? red_admin_text($existingGallery['GalleryType'] ?? '')
	: '';
if ($existingGallery && !red_admin_gallery_insert_reuse_allowed($existingGalleryType, $postedGalleryType)) {
	echo 'no';
	$db->close();
	exit;
}
if (!red_admin_gallery_insert_target_allowed($existingArticle, $existingGallery, $postedGalleryType)) {
	echo 'no';
	$db->close();
	exit;
}
if ($existingArticle && !red_admin_article_is_upload_placeholder($existingArticle)) {
	red_admin_require_article_access($db->connection, $artRecordId);
}

$articlePost = red_admin_gallery_article_post($galleryPost, $artRecordId, 'insert');
$articleData = red_admin_article_collect_values($articlePost, 'insert');
unset($articleData['NewWindow']);
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

$galleryData = red_admin_gallery_collect_values($galleryPost, 'insert', $recordId, $artRecordId);
$connection = $db->connection;
$success = red_admin_content_revision_create_transaction(
	$connection,
	$artRecordId,
	function () use ($connection, $existingArticle, $artRecordId, $articleData, $recordId, $galleryData, $seoInput) {
		if ($existingArticle) {
			$articleSuccess = red_admin_article_update($connection, $artRecordId, $articleData);
		} else {
			$articleSuccess = red_admin_article_insert($connection, $artRecordId, $articleData);
		}

		$gallerySuccess = $articleSuccess
			? red_admin_gallery_save($connection, $recordId, $artRecordId, $galleryData)
			: false;

		$seoSuccess = $gallerySuccess && $seoInput['present']
			? red_admin_seo_save($connection, 'article', $artRecordId, $seoInput['values'])
			: $gallerySuccess;

		return $articleSuccess && $gallerySuccess && $seoSuccess;
	},
	['RED_Articles', 'RED_C_Gallery', 'RED_Page_SEO']
);

if ($success) {
	red_admin_content_revision_response_headers($connection, $artRecordId);
}
echo $success ? 'yes' : 'no';
$db->close();
?>
