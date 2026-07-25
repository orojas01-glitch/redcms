<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['REQUEST_URI'] = '/admin/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/class/class_connection.php';
require_once dirname(__DIR__) . '/includes/admin_content_revision_helpers.php';

if (!preg_match('/\Aredcms_(?:acceptance|revision)_[A-Za-z0-9_]+\z/', (string) DBNAME)) {
    fwrite(STDERR, "Refusing to run: RED_DB_NAME must name a disposable redcms_acceptance_* or redcms_revision_* database.\n");
    exit(64);
}

$_SESSION['AdminRecordID'] = 2147000870;
$_SESSION['alias'] = 'RevisionQA';
$_SESSION['AdminType'] = 'webmaster';

$assertions = 0;
$assert = static function ($condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$articleId = 2147000871;
$galleryArticleId = 2147000872;
$galleryId = 2147000873;
$formArticleId = 2147000874;
$formId = 2147000875;
$fixtureIds = [$articleId, $galleryArticleId, $formArticleId];
$db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
$connection = $db->connection;

$deleteByIds = static function ($connection, string $table, string $column, array $ids): void {
    if ($ids === []) {
        return;
    }
    $safeIds = implode(',', array_map('intval', $ids));
    mysqli_query($connection, "DELETE FROM `$table` WHERE `$column` IN ($safeIds)");
};

try {
    $assert(red_admin_content_revision_table_available($connection), 'revision table is available');
    $assert(
        red_admin_content_revision_changed_values(
            ['Title' => 'Current', 'EventDate' => '0000-00-00 00:00:00'],
            ['Title' => 'Earlier', 'EventDate' => '0000-00-00 00:00:00']
        ) === ['Title' => 'Earlier'],
        'restore payload omits unchanged legacy zero dates under strict SQL mode'
    );

    $layoutResult = mysqli_query(
        $connection,
        "SELECT Layout FROM RED_Sections WHERE Sections='home' ORDER BY RecordID ASC LIMIT 1"
    );
    $layoutRow = $layoutResult ? mysqli_fetch_assoc($layoutResult) : null;
    if ($layoutResult) {
        mysqli_free_result($layoutResult);
    }
    $layout = (string) ($layoutRow['Layout'] ?? '');
    $assert($layout !== '', 'Home layout fixture is available');

    $articleData = red_admin_article_default_insert_data($articleId);
    $articleData['Title'] = 'Revision article v1';
    $articleData['Alias'] = 'revision-article-qa';
    $articleData['Sections'] = 'home';
    $articleData['Layout'] = $layout;
    $articleData['Component'] = 'Article';
    $articleData['PagePosition'] = 0;
    $articleData['Active'] = 'N';
    $articleData['Language'] = 'sp';
    $articleData['EditedBy'] = 'RevisionQA';

    $assert(
        red_admin_content_revision_create_transaction(
            $connection,
            $articleId,
            static function () use ($connection, $articleId, $articleData) {
                return red_admin_article_insert($connection, $articleId, $articleData);
            },
            ['RED_Articles']
        ),
        'Article creation and its first revision commit together'
    );
    $articleHistory = red_admin_content_revision_list($connection, $articleId);
    $assert(count($articleHistory['revisions']) === 1, 'created Article has one initial revision');
    $assert(($articleHistory['revisions'][0]['operation'] ?? '') === 'create', 'initial Article revision is labeled create');

    $assert(
        red_admin_content_revision_transaction(
            $connection,
            $articleId,
            static function () use ($connection, $articleId) {
                return red_admin_article_update($connection, $articleId, [
                    'Title' => 'Revision article v2',
                    'ShortDesc' => '<p>Second version</p>',
                    'EditedBy' => 'RevisionQA',
                ]);
            },
            ['RED_Articles'],
            'save'
        ),
        'Article update and revision commit together'
    );
    $articleHistory = red_admin_content_revision_list($connection, $articleId);
    $assert(count($articleHistory['revisions']) === 2, 'Article update creates exactly one additional revision');
    $assert(!empty($articleHistory['revisions'][0]['isCurrent']), 'latest Article revision is current');
    $assert(
        in_array('title', $articleHistory['revisions'][0]['changes'] ?? [], true),
        'Article history reports its changed title'
    );

    $firstArticleRevision = $articleHistory['revisions'][1];
    $restore = red_admin_content_revision_restore(
        $connection,
        $articleId,
        (int) $firstArticleRevision['revisionId'],
        (string) $articleHistory['currentHash']
    );
    $assert(!empty($restore['ok']), 'Article can restore its first revision');
    $restoredArticle = red_admin_article_full_record($connection, $articleId);
    $assert(($restoredArticle['Title'] ?? '') === 'Revision article v1', 'Article restore reinstates the earlier title');
    $articleHistory = red_admin_content_revision_list($connection, $articleId);
    $assert(count($articleHistory['revisions']) === 3, 'restore appends a new immutable revision');
    $assert(($articleHistory['revisions'][0]['operation'] ?? '') === 'restore', 'restored state is labeled restore');
    $assert(
        count(array_filter(
            $articleHistory['revisions'],
            static fn(array $revision): bool => !empty($revision['isCurrent'])
        )) === 1
            && !empty($articleHistory['revisions'][0]['isCurrent']),
        'only the newest matching snapshot is labeled current after restore'
    );

    $staleRestore = red_admin_content_revision_restore(
        $connection,
        $articleId,
        (int) $firstArticleRevision['revisionId'],
        str_repeat('0', 64)
    );
    $assert(empty($staleRestore['ok']) && ($staleRestore['reason'] ?? '') === 'conflict', 'stale restore token is rejected');

    $countBeforeNoop = count($articleHistory['revisions']);
    $assert(
        red_admin_content_revision_transaction(
            $connection,
            $articleId,
            static function () use ($connection, $articleId) {
                return red_admin_article_update($connection, $articleId, ['Title' => 'Revision article v1']);
            },
            ['RED_Articles'],
            'save'
        ),
        'no-op compatible Article save succeeds'
    );
    $assert(
        count(red_admin_content_revision_list($connection, $articleId)['revisions']) === $countBeforeNoop,
        'no-op save does not add a duplicate revision'
    );

    $galleryArticle = red_admin_article_default_insert_data($galleryArticleId);
    $galleryArticle['Title'] = 'Revision banner v1';
    $galleryArticle['Alias'] = 'revision-banner-qa';
    $galleryArticle['Sections'] = 'home';
    $galleryArticle['Layout'] = $layout;
    $galleryArticle['Component'] = 'Gallery';
    $galleryArticle['PagePosition'] = 0;
    $galleryArticle['Active'] = 'N';
    $galleryArticle['Language'] = 'sp';
    $galleryArticle['EditedBy'] = 'RevisionQA';
    $galleryData = red_admin_gallery_default_insert_data($galleryId, $galleryArticleId);
    $galleryData['Title'] = 'Revision banner v1';
    $galleryData['Alias'] = 'revision-banner-qa';
    $galleryData['GalleryType'] = 'Banner';
    $galleryData['LongDesc'] = 'banner-v1.png';

    $assert(
        red_admin_content_revision_create_transaction(
            $connection,
            $galleryArticleId,
            static function () use ($connection, $galleryArticleId, $galleryArticle, $galleryId, $galleryData) {
                return red_admin_article_insert($connection, $galleryArticleId, $galleryArticle)
                    && red_admin_gallery_insert($connection, $galleryId, $galleryArticleId, $galleryData);
            },
            ['RED_Articles', 'RED_C_Gallery']
        ),
        'paired Banner creation produces one aggregate revision'
    );
    $galleryHistory = red_admin_content_revision_list($connection, $galleryArticleId);
    $assert(count($galleryHistory['revisions']) === 1, 'paired Banner begins with one revision');
    $assert(($galleryHistory['revisions'][0]['contentType'] ?? '') === 'Banner', 'Gallery subtype is identified as Banner');

    $assert(
        red_admin_content_revision_transaction(
            $connection,
            $galleryArticleId,
            static function () use ($connection, $galleryArticleId, $galleryId) {
                return red_admin_article_update($connection, $galleryArticleId, ['Title' => 'Revision banner v2'])
                    && red_admin_gallery_update($connection, $galleryId, [
                        'Title' => 'Revision banner v2',
                        'LongDesc' => 'banner-v2.png',
                    ]);
            },
            ['RED_Articles', 'RED_C_Gallery'],
            'save'
        ),
        'paired Banner update and revision commit atomically'
    );
    $galleryHistory = red_admin_content_revision_list($connection, $galleryArticleId);
    $galleryRestore = red_admin_content_revision_restore(
        $connection,
        $galleryArticleId,
        (int) $galleryHistory['revisions'][1]['revisionId'],
        (string) $galleryHistory['currentHash']
    );
    $assert(!empty($galleryRestore['ok']), 'paired Banner can restore its first revision');
    $restoredGalleryArticle = red_admin_article_full_record($connection, $galleryArticleId);
    $restoredGallery = red_admin_gallery_render_record($connection, $galleryId, $galleryArticleId);
    $assert(
        ($restoredGalleryArticle['Title'] ?? '') === 'Revision banner v1'
            && ($restoredGallery['LongDesc'] ?? '') === 'banner-v1.png',
        'paired Banner restore reinstates parent and child rows together'
    );

    $formArticle = red_admin_article_default_insert_data($formArticleId);
    $formArticle['Title'] = 'Revision form v1';
    $formArticle['Alias'] = 'revision-form-qa';
    $formArticle['Sections'] = 'home';
    $formArticle['Layout'] = $layout;
    $formArticle['Component'] = 'Form';
    $formArticle['PagePosition'] = 0;
    $formArticle['Active'] = 'N';
    $formArticle['Language'] = 'sp';
    $formArticle['EditedBy'] = 'RevisionQA';
    $formData = red_admin_form_default_insert_data($formId, $formArticleId);
    $formData['Title'] = 'Revision form v1';
    $formData['Alias'] = 'revision-form-qa';
    $formData['FormType'] = 'Other';
    $formData['LongDesc'] = '#|question=|name=name|type=textfield|required=false|displayname=Name|initialvalue=;';

    $assert(
        red_admin_content_revision_create_transaction(
            $connection,
            $formArticleId,
            static function () use ($connection, $formArticleId, $formArticle, $formId, $formData) {
                return red_admin_article_insert($connection, $formArticleId, $formArticle)
                    && red_admin_form_insert($connection, $formId, $formArticleId, $formData);
            },
            ['RED_Articles', 'RED_C_Form']
        ),
        'paired Form creation produces one aggregate revision'
    );
    $formHistory = red_admin_content_revision_list($connection, $formArticleId);
    $assert(
        count($formHistory['revisions']) === 1
            && ($formHistory['revisions'][0]['contentType'] ?? '') === 'Form Other',
        'Form history identifies its subtype'
    );

    $assert(
        red_admin_content_revision_delete_transaction(
            $connection,
            $formArticleId,
            static function () use ($connection, $formId, $formArticleId) {
                return red_admin_form_update($connection, $formId, ['Title' => 'Deleted fixture'])
                    && mysqli_query($connection, 'DELETE FROM RED_C_Form WHERE RecordID=' . $formId) === true
                    && mysqli_query($connection, 'DELETE FROM RED_Articles WHERE RecordID=' . $formArticleId) === true;
            },
            ['RED_Articles', 'RED_C_Form']
        ),
        'delete checkpoint and paired row deletion commit together'
    );
    $deletedRevision = red_admin_content_revision_latest($connection, $formArticleId);
    $assert(
        is_array($deletedRevision) && (int) ($deletedRevision['RevisionNumber'] ?? 0) === 2,
        'deleted content keeps its immutable pre-delete revision history'
    );

    $stmt = mysqli_prepare(
        $connection,
        'SELECT Operation FROM RED_Content_Revisions WHERE ContentRecordID=? ORDER BY RevisionNumber DESC LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'i', $formArticleId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $operationRow = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    $assert(($operationRow['Operation'] ?? '') === 'delete', 'pre-delete snapshot is labeled delete');

    echo "Content revision self-test passed: {$assertions} assertions.\n";
} finally {
    $deleteByIds($connection, 'RED_C_Gallery', 'RecordID', [$galleryId]);
    $deleteByIds($connection, 'RED_C_Form', 'RecordID', [$formId]);
    $deleteByIds($connection, 'RED_Articles', 'RecordID', $fixtureIds);
    $deleteByIds($connection, 'RED_Content_Revisions', 'ContentRecordID', $fixtureIds);
    $db->close();
}
?>
