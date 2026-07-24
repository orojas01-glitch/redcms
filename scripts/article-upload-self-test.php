<?php
/** Focused live-schema checks for new/edit Article image upload persistence. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/includes/config.php';
require_once $repositoryRoot . '/class/class_connection.php';
require_once $repositoryRoot . '/includes/upload_helpers.php';
require_once $repositoryRoot . '/includes/admin_article_helpers.php';

$assertions = 0;
$assert = static function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
};

$reportedName = 'Custom-Icon-Design-Pretty-Office-7-Calendar.256.png';
$assert(strlen($reportedName) === 51, 'reported regression filename remains the 51-character boundary case');
$assert(
    red_upload_clean_filename($reportedName) === $reportedName,
    'safe filename normalization preserves the reported readable filename'
);

$boundedName = red_upload_clean_filename(str_repeat('a', 260) . '.png');
$assert(strlen($boundedName) === 200, 'generated stored names are capped at 200 characters');
$assert(substr($boundedName, -4) === '.png', 'stored-name cap preserves the image extension');
$assert(
    red_admin_article_datetime('2026-07-22', '1970-01-01 00:00:00') === '2026-07-22 00:00:00',
    'native calendar date input normalizes to the existing Article timestamp contract'
);
$assert(
    red_admin_article_datetime('', '1970-01-01 00:00:00') === '1970-01-01 00:00:00'
        && red_admin_article_datetime('', '9999-12-31 23:59:59') === '9999-12-31 23:59:59',
    'blank calendar dates preserve immediate-start and no-expiration sentinels'
);

$temporaryDirectory = sys_get_temp_dir() . '/redcms-article-upload-' . bin2hex(random_bytes(6));
$db = null;
$transactionStarted = false;
$recordId = 0;

try {
    if (!mkdir($temporaryDirectory, 0700)) {
        throw new RuntimeException('Could not create the upload-name test directory.');
    }
    $existingPath = $temporaryDirectory . '/' . $boundedName;
    if (file_put_contents($existingPath, 'collision') !== 9) {
        throw new RuntimeException('Could not create the upload-name collision fixture.');
    }
    [$collisionPath, $collisionName] = red_upload_unique_path($temporaryDirectory, $boundedName);
    $assert($collisionPath === $temporaryDirectory . '/' . $collisionName, 'collision path matches its stored name');
    $assert(strlen($collisionName) <= 200, 'collision suffix stays inside the stored-name limit');
    $assert($collisionName !== $boundedName, 'collision-safe naming does not overwrite the existing file');
    $assert(substr($collisionName, -4) === '.png', 'collision-safe naming preserves the extension');

    $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
    $schemaResult = mysqli_query(
        $db->connection,
        "SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH\n" .
            "FROM INFORMATION_SCHEMA.COLUMNS\n" .
            "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='RED_Articles'\n" .
            "AND COLUMN_NAME IN ('BigPict', 'SmallPict', 'SmallPict2')\n" .
            'ORDER BY COLUMN_NAME'
    );
    if ($schemaResult === false) {
        throw new RuntimeException('Could not inspect Article image column capacity.');
    }
    $capacities = [];
    while ($row = mysqli_fetch_assoc($schemaResult)) {
        $capacities[$row['COLUMN_NAME']] = (int) $row['CHARACTER_MAXIMUM_LENGTH'];
    }
    mysqli_free_result($schemaResult);
    $assert(
        $capacities === ['BigPict' => 255, 'SmallPict' => 255, 'SmallPict2' => 255],
        'all Article image columns accept bounded readable stored names'
    );

    do {
        $recordId = mt_rand(1000000000, 2100000000);
        $stmt = mysqli_prepare($db->connection, 'SELECT COUNT(*) FROM RED_Articles WHERE RecordID=?');
        mysqli_stmt_bind_param($stmt, 'i', $recordId);
        mysqli_stmt_bind_result($stmt, $recordExists);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    } while ((int) $recordExists !== 0);

    $placeholderData = red_admin_article_upload_placeholder_data(
        $db->connection,
        $recordId,
        'SmallPict',
        $reportedName,
        'sp',
        'Article'
    );
    $defaults = red_admin_article_default_insert_data($recordId);
    $assert(is_array($placeholderData), 'new Article image upload builds a complete placeholder row');
    $assert(array_keys($placeholderData) === array_keys($defaults), 'placeholder covers every persisted Article field');
    $assert(
        $placeholderData['Title'] === ''
            && $placeholderData['Component'] === 'Article'
            && $placeholderData['PagePosition'] === 0
            && $placeholderData['Active'] === 'N'
            && $placeholderData['Language'] === 'sp'
            && $placeholderData['SmallPict'] === $reportedName,
        'placeholder is inactive, authorized, and retains the uploaded image'
    );

    if (!mysqli_begin_transaction($db->connection)) {
        throw new RuntimeException('Could not start the placeholder rollback test.');
    }
    $transactionStarted = true;
    $inserted = red_admin_with_theme_contract_lock(
        $db->connection,
        static function () use ($db, $recordId, $reportedName) {
            return red_admin_article_insert_upload_placeholder(
                $db->connection,
                $recordId,
                'SmallPict',
                $reportedName,
                'sp',
                'Article'
            );
        }
    );
    $assert($inserted === true, 'strict-schema placeholder insert succeeds with the reported filename');

    $stmt = mysqli_prepare(
        $db->connection,
        'SELECT Title, Component, Layout, PagePosition, Active, Language, SmallPict FROM RED_Articles WHERE RecordID=?'
    );
    mysqli_stmt_bind_param($stmt, 'i', $recordId);
    $result = mysqli_stmt_execute($stmt) ? mysqli_stmt_get_result($stmt) : false;
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    $assert(
        is_array($row)
            && $row['Title'] === ''
            && $row['Component'] === 'Article'
            && $row['Layout'] === $placeholderData['Layout']
            && (int) $row['PagePosition'] === 0
            && $row['Active'] === 'N'
            && $row['Language'] === 'sp'
            && $row['SmallPict'] === $reportedName,
        'persisted placeholder matches the complete inactive upload contract'
    );

    $fullPlaceholder = red_admin_article_full_record($db->connection, $recordId);
    $assert(
        red_admin_article_is_upload_placeholder($fullPlaceholder),
        'complete inactive upload row is recognized as a promotable placeholder'
    );
    $regularArticle = $fullPlaceholder;
    $regularArticle['Title'] = 'Saved Article';
    $assert(
        !red_admin_article_is_upload_placeholder($regularArticle),
        'saved Article metadata cannot be mistaken for an upload placeholder'
    );
    $promotionData = ['Layout' => $placeholderData['Layout']];
    $assert(
        red_admin_article_prepare_upload_placeholder_promotion($db->connection, $recordId, $promotionData),
        'metadata save can promote an upload placeholder'
    );
    $assert(
        isset($promotionData['PagePosition']) && (int) $promotionData['PagePosition'] > 0,
        'placeholder promotion restores a renderable manifest position'
    );

    $stmt = mysqli_prepare($db->connection, 'UPDATE RED_Articles SET SmallPict2=? WHERE RecordID=?');
    mysqli_stmt_bind_param($stmt, 'si', $reportedName, $recordId);
    $updated = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $assert($updated === true, 'existing Article image update accepts the reported filename');

    mysqli_rollback($db->connection);
    $transactionStarted = false;
    $stmt = mysqli_prepare($db->connection, 'SELECT COUNT(*) FROM RED_Articles WHERE RecordID=?');
    mysqli_stmt_bind_param($stmt, 'i', $recordId);
    mysqli_stmt_bind_result($stmt, $remainingRows);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $assert((int) $remainingRows === 0, 'placeholder regression test rolls back without leaving content');
} finally {
    if ($db instanceof connection) {
        if ($transactionStarted) {
            mysqli_rollback($db->connection);
        }
        $db->close();
    }
    if (isset($existingPath) && is_file($existingPath)) {
        unlink($existingPath);
    }
    if (is_dir($temporaryDirectory)) {
        rmdir($temporaryDirectory);
    }
}

printf("PASS: Article upload contract self-test (%d assertions).\n", $assertions);
