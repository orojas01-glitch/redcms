<?php
/**
 * Disposable-database SEO persistence and revision checks.
 */

$repositoryRoot = dirname(__DIR__);
$targetDatabase = trim((string) getenv('RED_SEO_TEST_DATABASE'));
if (preg_match('/\Aredcms_(?:acceptance|seo_acceptance)_[A-Za-z0-9_]+\z/', $targetDatabase) !== 1) {
    fwrite(STDERR, "Refusing unsafe SEO acceptance database name.\n");
    exit(64);
}

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = '127.0.0.1';

require_once $repositoryRoot . '/includes/bootstrap.php';
require_once $repositoryRoot . '/includes/config.php';
require_once $repositoryRoot . '/class/class_connection.php';
require_once $repositoryRoot . '/includes/admin_seo_helpers.php';
require_once $repositoryRoot . '/includes/admin_content_revision_helpers.php';

if ($targetDatabase === DBNAME) {
    fwrite(STDERR, "Refusing configured primary database.\n");
    exit(65);
}

$db = new connection(DBHOST, DBUSER, DBPASS, $targetDatabase);
$connection = $db->connection;
$assertions = 0;
$assert = static function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $databaseResult = mysqli_query($connection, 'SELECT DATABASE() AS database_name');
    $databaseRow = $databaseResult ? mysqli_fetch_assoc($databaseResult) : null;
    if ($databaseResult) {
        mysqli_free_result($databaseResult);
    }
    $assert(
        is_array($databaseRow) && ($databaseRow['database_name'] ?? '') === $targetDatabase,
        'connection did not select the exact disposable database'
    );
    $assert(red_seo_table_available($connection), 'SEO metadata table is unavailable after migration');

    $countResult = mysqli_query($connection, 'SELECT COUNT(*) AS row_count FROM RED_Page_SEO');
    $countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
    if ($countResult) {
        mysqli_free_result($countResult);
    }
    $assert((int) ($countRow['row_count'] ?? -1) === 0, 'SEO table must begin empty');

    $articleResult = mysqli_query(
        $connection,
        "SELECT RecordID, Title FROM RED_Articles WHERE Alias='instructions' ORDER BY RecordID LIMIT 1"
    );
    $article = $articleResult ? mysqli_fetch_assoc($articleResult) : null;
    if ($articleResult) {
        mysqli_free_result($articleResult);
    }
    $articleId = (int) ($article['RecordID'] ?? 0);
    $articleTitle = (string) ($article['Title'] ?? '');
    $assert($articleId > 0 && $articleTitle !== '', 'disposable Article fixture is unavailable');

    $values = array_merge(red_seo_empty_values(), [
        'SEO_Title' => 'Exact CUDA Title | Adriana',
        'MetaDescription' => 'A migration-safe description.',
        'CanonicalURL' => 'https://example.com/instructions',
        'RobotsIndex' => 'Y',
        'RobotsFollow' => 'Y',
        'OGTitle' => 'Social CUDA Title',
        'OGDescription' => 'A social description.',
        'OGImage' => '/images/social-card.png',
        'OGImageAlt' => 'Singer at a microphone',
        'OGType' => 'article',
        'OGLocale' => 'es_CO',
        'XCard' => 'summary_large_image',
        'SchemaType' => 'Course',
    ]);
    $saved = red_admin_write_transaction(
        $connection,
        static function () use ($connection, $articleId, $values) {
            return red_seo_save_metadata($connection, 'article', $articleId, $values, 999);
        },
        ['RED_Page_SEO']
    );
    $assert($saved, 'transactional SEO insert failed');

    $stored = red_seo_metadata_row($connection, 'article', $articleId);
    $assert(is_array($stored), 'saved SEO row is unavailable');
    $assert(($stored['SEO_Title'] ?? '') === 'Exact CUDA Title | Adriana', 'exact SEO title changed during persistence');
    $assert(($stored['SchemaType'] ?? '') === 'Course', 'schema type changed during persistence');

    $snapshot = red_admin_content_revision_capture($connection, $articleId);
    $assert((int) ($snapshot['schema'] ?? 0) === 2, 'content revision did not use the SEO-aware schema');
    $assert(($snapshot['seo']['SEO_Title'] ?? '') === 'Exact CUDA Title | Adriana', 'revision snapshot omitted SEO metadata');

    $invalid = $values;
    $invalid['CanonicalURL'] = 'javascript:alert(1)';
    $assert(
        !red_seo_save_metadata($connection, 'article', $articleId, $invalid, 999),
        'invalid canonical URL was persisted'
    );
    $afterInvalid = red_seo_metadata_row($connection, 'article', $articleId);
    $assert(
        ($afterInvalid['CanonicalURL'] ?? '') === 'https://example.com/instructions',
        'invalid save changed the stored canonical URL'
    );

    $deleted = red_admin_write_transaction(
        $connection,
        static function () use ($connection, $articleId) {
            return red_seo_delete_metadata($connection, 'article', $articleId);
        },
        ['RED_Page_SEO']
    );
    $assert($deleted, 'transactional SEO cleanup failed');
    $assert(
        red_seo_metadata_row($connection, 'article', $articleId) === null,
        'SEO cleanup left a row behind'
    );

    $layoutResult = mysqli_query(
        $connection,
        "SELECT Layout FROM RED_Sections WHERE Sections='home' ORDER BY RecordID LIMIT 1"
    );
    $layoutRow = $layoutResult ? mysqli_fetch_assoc($layoutResult) : null;
    if ($layoutResult) {
        mysqli_free_result($layoutResult);
    }
    $layout = (string) ($layoutRow['Layout'] ?? '');
    $assert($layout !== '', 'disposable area layout fixture is unavailable');

    $areaSeoValues = array_merge(red_seo_empty_values(), [
        'SEO_Title' => 'Exact area SEO title',
        'MetaDescription' => 'Area metadata stored beside the route owner.',
        'SchemaType' => 'WebPage',
    ]);
    $sectionId = red_admin_seo_insert_area(
        $connection,
        'RED_Sections',
        'Sections',
        'SEO Acceptance Section',
        'seo-acceptance-section',
        $layout,
        '100',
        'Public',
        '',
        'N',
        'Section description',
        'section, seo',
        'sp',
        0,
        $areaSeoValues
    );
    $assert((int) $sectionId > 0, 'transactional Section and SEO creation failed');
    $assert(
        (red_seo_metadata_row($connection, 'section', $sectionId)['SEO_Title'] ?? '') ===
            'Exact area SEO title',
        'Section SEO metadata was not stored'
    );

    $categoryId = red_admin_seo_insert_area(
        $connection,
        'RED_Categories',
        'Categories',
        'SEO Acceptance Category',
        'seo-acceptance-category',
        $layout,
        '100',
        'Public',
        '',
        'N',
        'Category description',
        'category, seo',
        'sp',
        $sectionId,
        $areaSeoValues
    );
    $assert((int) $categoryId > 0, 'transactional Category and SEO creation failed');
    $assert(
        (red_seo_metadata_row($connection, 'category', $categoryId)['SEO_Title'] ?? '') ===
            'Exact area SEO title',
        'Category SEO metadata was not stored'
    );

    $subcategoryId = red_admin_seo_insert_area(
        $connection,
        'RED_SubCategories',
        'SubCategories',
        'SEO Acceptance Subcategory',
        'seo-acceptance-subcategory',
        $layout,
        '100',
        'Public',
        '',
        'N',
        'Subcategory description',
        'subcategory, seo',
        'sp',
        $categoryId,
        $areaSeoValues
    );
    $assert((int) $subcategoryId > 0, 'transactional Subcategory and SEO creation failed');
    $assert(
        (red_seo_metadata_row($connection, 'subcategory', $subcategoryId)['SEO_Title'] ?? '') ===
            'Exact area SEO title',
        'Subcategory SEO metadata was not stored'
    );

    $updatedAreaSeo = $areaSeoValues;
    $updatedAreaSeo['SEO_Title'] = 'Updated exact area SEO title';
    $categoryUpdate = red_admin_area_save_existing(
        $connection,
        'RED_Categories',
        'Categories',
        $categoryId,
        [
            'Title' => 'SEO Acceptance Category Updated',
            'Categories' => 'seo-acceptance-category',
            'SectionRecordID' => $sectionId,
        ],
        red_admin_seo_area_save_callback(
            $connection,
            'category',
            $categoryId,
            $updatedAreaSeo
        ),
        ['RED_Page_SEO']
    );
    $assert(is_array($categoryUpdate), 'route-aware Category and SEO update failed');
    $assert(
        (red_seo_metadata_row($connection, 'category', $categoryId)['SEO_Title'] ?? '') ===
            'Updated exact area SEO title',
        'Category SEO update did not persist'
    );

    $invalidAreaSeo = $areaSeoValues;
    $invalidAreaSeo['CanonicalURL'] = 'javascript:alert(1)';
    $rolledBackAreaUpdate = red_admin_area_save_existing(
        $connection,
        'RED_Sections',
        'Sections',
        $sectionId,
        [
            'Title' => 'This title must roll back',
            'Sections' => 'seo-acceptance-section',
        ],
        red_admin_seo_area_save_callback(
            $connection,
            'section',
            $sectionId,
            $invalidAreaSeo
        ),
        ['RED_Page_SEO']
    );
    $assert($rolledBackAreaUpdate === false, 'invalid area SEO did not fail the transaction');
    $sectionAfterRollback = red_admin_area_record($connection, 'RED_Sections', $sectionId);
    $assert(
        ($sectionAfterRollback['Title'] ?? '') === 'SEO Acceptance Section',
        'invalid area SEO did not roll back the Section update'
    );

    $assert(
        red_admin_area_delete_record($connection, 'RED_SubCategories', $subcategoryId),
        'Subcategory and SEO cleanup failed'
    );
    $assert(
        red_admin_area_delete_record($connection, 'RED_Categories', $categoryId),
        'Category and SEO cleanup failed'
    );
    $assert(
        is_array(red_admin_section_archive_and_delete($connection, $sectionId)),
        'Section and SEO cleanup failed'
    );
    foreach ([
        ['subcategory', $subcategoryId],
        ['category', $categoryId],
        ['section', $sectionId],
    ] as $owner) {
        $assert(
            red_seo_metadata_row($connection, $owner[0], $owner[1]) === null,
            $owner[0] . ' cleanup left SEO metadata behind'
        );
    }

    $finalSeoCountResult = mysqli_query($connection, 'SELECT COUNT(*) AS row_count FROM RED_Page_SEO');
    $finalSeoCountRow = $finalSeoCountResult ? mysqli_fetch_assoc($finalSeoCountResult) : null;
    if ($finalSeoCountResult) {
        mysqli_free_result($finalSeoCountResult);
    }
    $assert((int) ($finalSeoCountRow['row_count'] ?? -1) === 0, 'area tests left SEO rows behind');

    $articleCheck = mysqli_prepare(
        $connection,
        'SELECT Title FROM RED_Articles WHERE RecordID=? LIMIT 1'
    );
    mysqli_stmt_bind_param($articleCheck, 'i', $articleId);
    mysqli_stmt_execute($articleCheck);
    $articleCheckResult = mysqli_stmt_get_result($articleCheck);
    $articleAfter = $articleCheckResult ? mysqli_fetch_assoc($articleCheckResult) : null;
    mysqli_stmt_close($articleCheck);
    $assert(($articleAfter['Title'] ?? '') === $articleTitle, 'SEO checks changed the Article fixture');

    echo "SEO metadata database self-test passed: {$assertions} assertions.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'SEO metadata database self-test failed: ' . $exception->getMessage() . "\n");
    $db->close();
    exit(1);
}

$db->close();
?>
