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

if ($artRecordId <= 0 || $recordId <= 0 || !red_admin_gallery_has_payload($_POST) || !$seoInput['valid']) {
	echo 'no';
	exit;
}

$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
red_admin_require_article_access($db->connection, $artRecordId);
if (array_key_exists('GalleryType', $_POST)) {
	red_admin_require_component_selection($db->connection, 'Gallery', $_POST['GalleryType']);
}

$existingArticle = red_admin_article_full_record($db->connection, $artRecordId);
$existingGallery = red_admin_gallery_render_record($db->connection, $recordId, $artRecordId);
if (
	!$existingArticle
	|| red_admin_article_clean_value('Component', $existingArticle['Component'] ?? '') !== 'Gallery'
	|| !$existingGallery
) {
	echo 'no';
	$db->close();
	exit;
}

$existingGalleryType = red_admin_gallery_clean_type($existingGallery['GalleryType'] ?? '');
$postedGalleryType = array_key_exists('GalleryType', $_POST)
	? red_admin_gallery_clean_type($_POST['GalleryType'])
	: '';
if (
	($postedGalleryType !== '' && $postedGalleryType !== $existingGalleryType)
	|| (array_key_exists('GalleryPresentation', $_POST)
		&& ($existingGalleryType !== 'Gallery' || $postedGalleryType !== 'Gallery'))
) {
	echo 'no';
	$db->close();
	exit;
}

$galleryPost = $_POST;
if ($existingGalleryType === 'Video' && array_key_exists('LongDesc', $galleryPost)) {
	$videoUrlData = red_video_url_data($galleryPost['LongDesc']);
	if (!is_array($videoUrlData)) {
		echo 'no';
		$db->close();
		exit;
	}
}

$articlePost = red_admin_gallery_article_post($galleryPost, $artRecordId, 'update');
$articleData = red_admin_article_collect_values($articlePost, 'update');
unset($articleData['NewWindow']);
if (!red_admin_article_apply_home_position($db->connection, $articleData, $existingArticle)) {
	echo 'no';
	$db->close();
	exit;
}

$galleryData = red_admin_gallery_collect_values($galleryPost, 'update', $recordId, $artRecordId);
if ($existingGalleryType === 'Gallery' && !array_key_exists('GalleryPresentation', $_POST)) {
	unset($galleryData['NewWindow']);
}
$connection = $db->connection;
$success = red_admin_content_revision_transaction(
	$connection,
	$artRecordId,
	function () use ($connection, $artRecordId, $articleData, $recordId, $galleryData, $seoInput) {
		$articleSuccess = red_admin_article_update($connection, $artRecordId, $articleData);
		$gallerySuccess = $articleSuccess
			? red_admin_gallery_update($connection, $recordId, $galleryData)
			: false;

		$seoSuccess = $gallerySuccess && $seoInput['present']
			? red_admin_seo_save($connection, 'article', $artRecordId, $seoInput['values'])
			: $gallerySuccess;

		return $articleSuccess && $gallerySuccess && $seoSuccess;
	},
	['RED_Articles', 'RED_C_Gallery', 'RED_Page_SEO'],
	'save'
);

if ($success) {
	red_admin_content_revision_response_headers($connection, $artRecordId);
}
echo $success ? 'yes' : 'no';
$db->close();
?>
